<?php

namespace Tests\Integration;

/** Invariants that keep the numbers trustworthy. */
class RulesTest extends ApiTestCase
{
    private static int $admin;
    private static int $branch;
    private static int $store;
    private static array $monthly;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $t = new self();
        self::$admin = $t->registerAndLogin('admin@example.com');
        self::$branch = $t->createUnit($t->rootId(), 'Branch', 'Branch');
        self::$store = $t->createUnit(self::$branch, 'Store', 'Store');
        self::$monthly = $t->createKpi(['code' => 'sales', 'name' => 'Sales']);
    }

    protected function setUp(): void
    {
        $this->actingAs(self::$admin);
    }

    // ---- KPI definitions

    public function testWeightedAverageNeedsCompatibleWeight(): void
    {
        $base = ['code' => 'rate', 'name' => 'Rate', 'frequency' => 'monthly', 'time_aggregation' => 'average',
                 'unit_aggregation' => 'weighted_average', 'direction' => 'higher_is_better'];

        $this->assertSame('is required for a weighted average', $this->kpiError($base, 'weight_kpi_id'));

        $weekly = $this->createKpi(['code' => 'visits', 'name' => 'Visits', 'frequency' => 'weekly']);
        $this->assertSame('must be reported monthly like this KPI', $this->kpiError($base + ['weight_kpi_id' => $weekly['id']], 'weight_kpi_id'));

        $kpi = $this->ok('POST', '/kpis', [], $base + ['weight_kpi_id' => self::$monthly['id']]);
        $this->assertSame(self::$monthly['id'], $kpi['weight_kpi_id']);
    }

    public function testKpiCodeIsUniqueAndWellFormed(): void
    {
        $base = ['name' => 'X', 'frequency' => 'monthly', 'time_aggregation' => 'sum', 'unit_aggregation' => 'sum', 'direction' => 'none'];
        $this->assertSame('is already used by another KPI', $this->kpiError($base + ['code' => 'sales'], 'code'));
        $this->assertSame('must start with a letter and use only a-z, 0-9 and _', $this->kpiError($base + ['code' => 'Bad Code'], 'code'));
    }

    public function testFrequencyIsFixedOnceKpiHasValues(): void
    {
        $kpi = $this->createKpi(['code' => 'orders', 'name' => 'Orders']);
        $this->ok('PUT', "/kpis/{$kpi['id']}", [], ['frequency' => 'weekly']); // fine while empty
        $this->ok('PUT', "/kpis/{$kpi['id']}", [], ['frequency' => 'monthly']);
        $this->save($kpi['id'], '2025-06-01', 5);

        $result = $this->call('PUT', "/kpis/{$kpi['id']}", [], ['frequency' => 'weekly']);
        $this->assertStatus(400, $result);
        $this->assertTrue(isset($result[1]['details']['frequency']));
    }

    public function testPartialUpdateKeepsOtherFields(): void
    {
        $kpi = $this->createKpi(['code' => 'returns', 'name' => 'Returns', 'direction' => 'lower_is_better']);
        $updated = $this->ok('PUT', "/kpis/{$kpi['id']}", [], ['name' => 'Returns (units)']);
        $this->assertSame('lower_is_better', $updated['direction']);
        $this->assertStatus(400, $this->call('PUT', "/kpis/{$kpi['id']}", [], ['name' => '']));
    }

    // ---- Values

    public function testValuesSnapToPeriodAndUpsert(): void
    {
        $kpi = $this->createKpi(['code' => 'revenue', 'name' => 'Revenue']);
        $this->assertSame(['created' => 1, 'updated' => 0, 'unchanged' => 0, 'deleted' => 0], $this->save($kpi['id'], '2025-06-17', 100));
        $this->assertSame(['created' => 0, 'updated' => 1, 'unchanged' => 0, 'deleted' => 0], $this->save($kpi['id'], '2025-06-30', -25.5));
        $this->assertSame(['created' => 0, 'updated' => 0, 'unchanged' => 1, 'deleted' => 0], $this->save($kpi['id'], '2025-06-01', -25.5));

        $form = $this->ok('GET', '/values', ['unit' => (string)self::$store, 'date' => '2025-06-09']);
        $row = array_values(array_filter($form['rows'], fn($r) => $r['kpi']['id'] === $kpi['id']))[0];
        $this->assertSame('2025-06-01', $row['period_start']);
        $this->assertNear(-25.5, $row['value']);
        $this->assertSame('admin@example.com', $row['updated_by']);

        $this->assertSame(['created' => 0, 'updated' => 0, 'unchanged' => 0, 'deleted' => 1], $this->save($kpi['id'], '2025-06-01', null));
    }

    public function testInvalidBatchWritesNothing(): void
    {
        $kpi = $this->createKpi(['code' => 'footfall', 'name' => 'Footfall']);
        $result = $this->call('PUT', '/values', [], ['values' => [
            ['kpi_id' => $kpi['id'], 'unit_id' => self::$store, 'period' => '2025-06-01', 'value' => 10],
            ['kpi_id' => $kpi['id'], 'unit_id' => self::$branch, 'period' => '2025-06-01', 'value' => 10], // not a leaf
            ['kpi_id' => $kpi['id'], 'unit_id' => self::$store, 'period' => '2025-02-30', 'value' => 10],  // no such date
        ]]);
        $this->assertStatus(400, $result);
        $this->assertSame(['values.1', 'values.2'], array_keys($result[1]['details']));
        $this->assertSame('0', $this->countValues($kpi['id']));
    }

    public function testKpiCanBeLimitedToAKindOfUnit(): void
    {
        $kpi = $this->createKpi(['code' => 'shelf_space', 'name' => 'Shelf space', 'applies_to_kind' => 'Warehouse']);
        $result = $this->call('PUT', '/values', [], ['values' => [['kpi_id' => $kpi['id'], 'unit_id' => self::$store, 'period' => '2025-06-01', 'value' => 1]]]);
        $this->assertStatus(400, $result);
        $this->assertTrue(strpos($result[1]['details']['values.0'], 'is only reported by warehouses') !== false);
    }

    // ---- Units

    public function testUnitWithValuesMustStayALeaf(): void
    {
        $this->save(self::$monthly['id'], '2025-01-01', 1);
        $result = $this->call('POST', '/units', [], ['parent_id' => self::$store, 'name' => 'Sub-store']);
        $this->assertStatus(400, $result);
        $this->assertSame('already holds values, so it must stay a leaf', $result[1]['details']['parent_id']);
        $this->assertStatus(409, $this->call('DELETE', '/units/' . self::$store));
    }

    public function testTreeCannotGetACycleOrSecondRoot(): void
    {
        $this->assertStatus(400, $this->call('PUT', '/units/' . self::$branch, [], ['parent_id' => self::$store]));
        $this->assertStatus(400, $this->call('POST', '/units', [], ['name' => 'Another root']));
        $this->assertStatus(400, $this->call('PUT', '/units/' . $this->rootId(), [], ['is_active' => false]));
        $this->assertStatus(409, $this->call('DELETE', '/units/' . $this->rootId()));
    }

    public function testSiblingNamesAreUnique(): void
    {
        $result = $this->call('POST', '/units', [], ['parent_id' => $this->rootId(), 'name' => 'branch']);
        $this->assertStatus(400, $result);
    }

    // ---- Import

    public function testImportRejectsBadRowsWithReasons(): void
    {
        $csv = implode("\n", [
            'Unit,Date,Sales',
            'Store,31/01/2025,1200',
            'Store,31/02/2025,1300',     // no such date (would silently become March elsewhere)
            'Nowhere,28/02/2025,10',     // unknown unit
            'Store,15/01/2025,"1,250"',  // same month as row 2
            'Store,31/03/2025,lots',     // not a number
            'Branch,30/04/2025,5',       // not a leaf
        ]);
        $mapping = ['unit_columns' => ['Unit'], 'unit_match' => 'name', 'date_column' => 'Date', 'date_format' => 'DD/MM/YYYY', 'columns' => ['Sales' => 'sales']];

        $preview = $this->ok('POST', '/imports/preview', [], ['mapping' => json_encode($mapping)], $this->upload($this->csvFile($csv)));
        $errors = array_map(fn($e) => $e['row'] . ': ' . $e['message'], $preview['errors']);

        $this->assertSame(5, $preview['error_count'], json_encode($errors));
        $this->assertSame([3, 4, 5, 6, 7], array_column($preview['errors'], 'row'));
        $this->assertTrue(strpos($errors[0], "'31/02/2025' is not a valid DD/MM/YYYY date") !== false, $errors[0]);
        $this->assertTrue(strpos($errors[2], 'Repeats row 2') !== false, $errors[2]);
        $this->assertSame(1, $preview['values_total']);

        $before = $this->countValues(self::$monthly['id']);
        $this->assertStatus(400, $this->call('POST', '/imports', [], ['mapping' => json_encode($mapping)], $this->upload($this->csvFile($csv))));
        $this->assertSame($before, $this->countValues(self::$monthly['id']));

        $result = $this->ok('POST', '/imports', [], ['mapping' => json_encode($mapping), 'skip_invalid' => '1'], $this->upload($this->csvFile($csv)));
        $this->assertSame(5, $result['skipped_errors']);
    }

    public function testImportMappingMustMatchFile(): void
    {
        $mapping = ['unit_columns' => ['Shop'], 'date_column' => 'When', 'date_format' => 'YYYY-MM-DD', 'columns' => ['Sales' => 'no_such_kpi']];
        $result = $this->call('POST', '/imports/preview', [], ['mapping' => json_encode($mapping)], $this->upload($this->csvFile("Unit,Date,Sales\n")));
        $this->assertStatus(400, $result);
        $this->assertSame(['unit_columns', 'date_column', 'columns.Sales'], array_keys($result[1]['details']));
    }

    private function save(int $kpiId, string $period, ?float $value): array
    {
        return $this->ok('PUT', '/values', [], ['values' => [['kpi_id' => $kpiId, 'unit_id' => self::$store, 'period' => $period, 'value' => $value]]]);
    }

    private function kpiError(array $fields, string $field): ?string
    {
        [$status, $payload] = $this->call('POST', '/kpis', [], $fields);
        $this->assertSame(400, $status, json_encode($payload));
        return $payload['details'][$field] ?? null;
    }

    private function countValues(int $kpiId): string
    {
        return (string)self::$db->query("SELECT COUNT(*) FROM kpi_values WHERE kpi_id = {$kpiId}")->fetchColumn();
    }
}
