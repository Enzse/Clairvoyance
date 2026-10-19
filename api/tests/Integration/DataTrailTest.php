<?php

namespace Tests\Integration;

/** Change history of figures, and exporting figures in a shape the importer reads back. */
class DataTrailTest extends ApiTestCase
{
    private static int $admin;
    private static int $clerk;
    private static array $kpi;
    private static array $rate;
    private static int $north;
    private static int $a;
    private static int $b;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $t = new self();
        self::$admin = $t->registerAndLogin('admin@example.com', 'Trail Co');
        self::$north = $t->createUnit($t->rootId(), 'North', 'Region');
        self::$a = $t->createUnit(self::$north, 'Store A', 'Store');
        self::$b = $t->createUnit(self::$north, 'Store B', 'Store');
        self::$kpi = $t->createKpi(['code' => 'sales', 'name' => 'Sales']);
        self::$rate = $t->createKpi(['code' => 'returns', 'name' => 'Return rate', 'time_aggregation' => 'average', 'unit_aggregation' => 'average', 'decimals' => 1]);
        self::$clerk = $t->registerAndLogin('clerk@example.com', null);
        $t->inviteAndJoin(self::$admin, self::$clerk, 'clerk@example.com', 'member', [['unit_id' => self::$a, 'role' => 'contributor']]);
    }

    private function save(int $unit, ?float $value, string $period = '2025-06-01', int $kpi = 0): void
    {
        $this->ok('PUT', '/values', [], ['values' => [['kpi_id' => $kpi ?: self::$kpi['id'], 'unit_id' => $unit, 'period' => $period, 'value' => $value]]]);
    }

    /** [old, new] as floats (JSON turns 120.0 into 120) */
    private function pair(array $change): array
    {
        return array_map(fn($v) => $v === null ? null : (float)$v, [$change['old_value'], $change['new_value']]);
    }

    private function history(int $unit, ?string $date = null): array
    {
        return $this->ok('GET', '/kpis/' . self::$kpi['id'] . '/history', ['unit' => $unit, 'date' => $date]);
    }

    public function test1EveryChangeIsRecordedWithWhoMadeIt(): void
    {
        $this->actingAs(self::$clerk);
        $this->save(self::$a, 100);
        $this->save(self::$a, 100); // unchanged: not a change
        $this->save(self::$a, 120);
        $this->save(self::$a, null); // deleted

        $changes = $this->history(self::$a);
        $this->assertSame(3, count($changes));
        // Newest first: the deletion, the update, the first entry
        $this->assertSame([120.0, null], $this->pair($changes[0]));
        $this->assertSame([100.0, 120.0], $this->pair($changes[1]));
        $this->assertSame([null, 100.0], $this->pair($changes[2]));
        $this->assertSame('clerk@example.com', $changes[0]['changed_by']);
        $this->assertSame('Jun 2025', $changes[0]['period_label']);
        $this->assertSame('Store A', $changes[0]['unit_name']);
    }

    public function test2ImportsAreRecordedWithTheFileName(): void
    {
        $this->actingAs(self::$admin);
        $csv = "Unit,Date,Sales\nStore B,2025-06-15,55\n";
        $mapping = ['unit_columns' => ['Unit'], 'unit_match' => 'name', 'date_column' => 'Date', 'date_format' => 'YYYY-MM-DD', 'columns' => ['Sales' => 'sales']];
        $this->ok('POST', '/imports', [], ['mapping' => json_encode($mapping)], ['file' => ['name' => 'june.csv', 'tmp_name' => $this->csvFile($csv), 'error' => UPLOAD_ERR_OK]]);

        $changes = $this->history(self::$b);
        $this->assertSame(1, count($changes));
        $this->assertSame('june.csv', $changes[0]['import_file']);
        $this->assertSame(55.0, (float)$changes[0]['new_value']);
    }

    public function test3HistoryCoversTheUnitsInsideAndFiltersByPeriod(): void
    {
        $this->actingAs(self::$admin);
        $this->save(self::$a, 80, '2025-07-01');
        $this->assertSame(5, count($this->history(self::$north)));
        $july = $this->history(self::$north, '2025-07-20');
        $this->assertSame(1, count($july));
        $this->assertSame('2025-07-01', $july[0]['period_start']);
    }

    public function test4HistoryFollowsAccess(): void
    {
        $this->actingAs(self::$clerk);
        $this->assertStatus(403, $this->call('GET', '/kpis/' . self::$kpi['id'] . '/history', ['unit' => self::$b]));
        $this->assertStatus(403, $this->call('GET', '/kpis/' . self::$kpi['id'] . '/history', ['unit' => self::$north]));
    }

    public function test5ExportGivesTheLeavesAndTheirFiguresInTheRange(): void
    {
        $this->actingAs(self::$admin);
        $this->save(self::$a, 2.5, '2025-06-01', self::$rate['id']);
        $export = $this->ok('GET', '/exports/values', ['unit' => self::$north, 'from' => '2025-06-01', 'to' => '2025-06-30']);

        $this->assertSame(['Store A', 'Store B'], array_column($export['units'], 'name'));
        $this->assertSame(['North', 'Store A'], $export['units'][0]['path']);
        $this->assertSame(['Return rate', 'Sales'], array_column($export['kpis'], 'name'));
        $values = array_map(fn($v) => [$v['kpi_id'], $v['unit_id'], $v['period_start'], (float)$v['value']], $export['values']);
        sort($values);
        $expected = [[self::$kpi['id'], self::$b, '2025-06-01', 55.0], [self::$rate['id'], self::$a, '2025-06-01', 2.5]];
        sort($expected);
        $this->assertSame($expected, $values, 'July is outside the range; the deleted June figure is gone');
    }

    public function test6AnExportImportsBackUnchanged(): void
    {
        $this->actingAs(self::$admin);
        $export = $this->ok('GET', '/exports/values', ['unit' => self::$north, 'from' => '2025-01-01', 'to' => '2025-12-31']);
        // Written the way the web app writes it: one row per unit and period, KPI names as headers
        $names = array_column($export['units'], 'name', 'id');
        $kpiNames = array_column($export['kpis'], 'name', 'id');
        $rows = [];
        foreach ($export['values'] as $v) {
            $rows[$names[$v['unit_id']] . '|' . $v['period_start']][$kpiNames[$v['kpi_id']]] = $v['value'];
        }
        $csv = 'Unit,Period,' . implode(',', $kpiNames) . "\n";
        foreach ($rows as $key => $cells) {
            [$unit, $period] = explode('|', $key);
            $csv .= "{$unit},{$period}," . implode(',', array_map(fn($k) => $cells[$k] ?? '', $kpiNames)) . "\n";
        }
        $mapping = ['unit_columns' => ['Unit'], 'unit_match' => 'name', 'date_column' => 'Period', 'date_format' => 'YYYY-MM-DD',
                    'columns' => ['Sales' => 'sales', 'Return rate' => 'returns']];
        $preview = $this->ok('POST', '/imports/preview', [], ['mapping' => json_encode($mapping)], $this->upload($this->csvFile($csv)));
        $this->assertSame(count($export['values']), $preview['unchanged']);
        $this->assertSame(0, $preview['will_create'] + $preview['will_update']);
    }

    public function test7ExportFollowsAccessAndChecksDates(): void
    {
        $this->actingAs(self::$clerk);
        $this->assertStatus(403, $this->call('GET', '/exports/values', ['unit' => self::$north, 'from' => '2025-01-01', 'to' => '2025-12-31']));
        $own = $this->ok('GET', '/exports/values', ['unit' => self::$a, 'from' => '2025-01-01', 'to' => '2025-12-31']);
        $this->assertSame(['Store A'], array_column($own['units'], 'name'));
        $this->assertStatus(400, $this->call('GET', '/exports/values', ['unit' => self::$a, 'from' => '2025-12-31', 'to' => '2025-01-01']));
    }
}
