<?php

namespace Tests\Integration;

/**
 * Acceptance test from docs/data-model.md: import the DepEd reference file through the
 * API and check the worked-example numbers (NCR, Q2 2025).
 */
class DepedImportTest extends ApiTestCase
{
    private const FIXTURE = __DIR__ . '/../../demo/deped.csv';

    private static array $kpis = [];
    private static int $adminId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $t = new self();
        self::$adminId = $t->registerAndLogin('admin@example.com');

        $students = $t->createKpi(['code' => 'total_students', 'name' => 'Total students', 'time_aggregation' => 'last', 'direction' => 'none']);
        $defs = [
            $students,
            $t->createKpi(['code' => 'attendance_rate', 'name' => 'Attendance rate', 'suffix' => '%', 'decimals' => 1,
                'time_aggregation' => 'average', 'unit_aggregation' => 'weighted_average', 'weight_kpi_id' => $students['id'], 'tolerance_pct' => 3]),
            $t->createKpi(['code' => 'average_grade', 'name' => 'Average grade', 'decimals' => 1,
                'time_aggregation' => 'average', 'unit_aggregation' => 'weighted_average', 'weight_kpi_id' => $students['id']]),
            $t->createKpi(['code' => 'teachers', 'name' => 'Teachers', 'time_aggregation' => 'last', 'direction' => 'none']),
            $t->createKpi(['code' => 'teacher_absences', 'name' => 'Teacher absences', 'direction' => 'lower_is_better']),
            $t->createKpi(['code' => 'budget_utilization', 'name' => 'Budget utilization', 'suffix' => '%',
                'time_aggregation' => 'last', 'unit_aggregation' => 'average']),
        ];
        foreach ($defs as $kpi) {
            self::$kpis[$kpi['code']] = $kpi;
        }
        $t->ok('PUT', "/kpis/{$defs[1]['id']}/targets", [], ['targets' => [['unit_id' => null, 'effective_from' => '2025-01-01', 'value' => 95]]]);
        $t->ok('PUT', "/kpis/{$defs[4]['id']}/targets", [], ['targets' => [['unit_id' => null, 'effective_from' => '2025-01-15', 'value' => 1.4]]]);
    }

    private function mapping(): array
    {
        return [
            'unit_columns' => ['Region', 'Division', 'School_Name'],
            'unit_kinds' => ['Region', 'Division', 'School'],
            'create_missing_units' => true,
            'date_column' => 'Report_Date',
            'date_format' => 'YYYY-MM-DD',
            'columns' => [
                'Total_Students' => 'total_students',
                'Attendance_Rate' => 'attendance_rate',
                'Average_Grade' => 'average_grade',
                'Teachers' => 'teachers',
                'Teacher_Absences' => 'teacher_absences',
                'Budget_Utilization' => 'budget_utilization',
            ],
        ];
    }

    public function test1PreviewWritesNothingAndReportsThePlan(): void
    {
        $this->actingAs(self::$adminId);
        $preview = $this->ok('POST', '/imports/preview', [], ['mapping' => json_encode($this->mapping())], $this->upload(self::FIXTURE));

        $this->assertSame(0, $preview['error_count'], json_encode($preview['errors']));
        $this->assertSame(60, $preview['rows_total']);
        $this->assertSame(360, $preview['values_total']);
        $this->assertSame(360, $preview['will_create']);
        $this->assertSame(14, count($preview['new_units'])); // 4 regions, 5 divisions, 5 schools
        $this->assertSame('Jan 2025', $preview['sample'][0]['period_label']); // 2025-01-31 snapped to January
        $this->assertSame('0', (string)self::$db->query('SELECT COUNT(*) FROM kpi_values')->fetchColumn());
    }

    public function test2CommitCreatesUnitsAndValues(): void
    {
        $this->actingAs(self::$adminId);
        $result = $this->ok('POST', '/imports', [], ['mapping' => json_encode($this->mapping())], $this->upload(self::FIXTURE));

        $this->assertSame(360, $result['values_created']);
        $this->assertSame(14, $result['units_created']);
        $school = self::$db->query("SELECT kind FROM units WHERE name = 'Manila Science High School'")->fetchColumn();
        $this->assertSame('School', $school);
    }

    public function test3WorkedExampleNcrQ2(): void
    {
        $this->actingAs(self::$adminId);
        $card = $this->ok('GET', '/scorecard', ['unit' => (string)$this->unitId('NCR'), 'date' => '2025-06-15', 'grain' => 'quarterly']);
        $byCode = [];
        foreach ($card['kpis'] as $row) {
            $byCode[$row['kpi']['code']] = $row;
        }

        $this->assertSame('Q2 2025', $byCode['total_students']['period_label']);
        $this->assertNear(3067, $byCode['total_students']['value']);
        $this->assertNear(9, $byCode['teacher_absences']['value']);
        $this->assertNear(96.0273, $byCode['attendance_rate']['value']);
        $this->assertSame('green', $byCode['attendance_rate']['status']);
        $this->assertNear(8.4, $byCode['teacher_absences']['target']);
        $this->assertSame('amber', $byCode['teacher_absences']['status']);
        $this->assertSame(6, $byCode['total_students']['reported']);
        $this->assertSame(6, $byCode['total_students']['expected']);
        $this->assertSame(12, count($byCode['total_students']['sparkline']));
        $this->assertSame('Q1 2025', $byCode['total_students']['previous']['period_label']);
    }

    public function test4MonthlyChangeVsPreviousPeriod(): void
    {
        $this->actingAs(self::$adminId);
        $card = $this->ok('GET', '/scorecard', ['unit' => (string)$this->unitId('Manila Science High School'), 'date' => '2025-06-01']);
        $attendance = $this->row($card, 'attendance_rate');

        $this->assertSame('Jun 2025', $attendance['period_label']);
        $this->assertNear(96.6, $attendance['value']);
        $this->assertNear(-0.4, $attendance['change']['value']); // May was 97.0
        $this->assertSame('worse', $attendance['change']['direction']);
    }

    public function test5BreakdownOfOrganisationByRegion(): void
    {
        $this->actingAs(self::$adminId);
        $kpi = self::$kpis['total_students']['id'];
        $data = $this->ok('GET', "/kpis/{$kpi}/breakdown", ['unit' => (string)$this->rootId(), 'date' => '2025-06-01', 'grain' => 'monthly']);
        $byName = array_column(array_map(fn($r) => ['name' => $r['unit']['name'], 'value' => $r['value']], $data['units']), 'value', 'name');

        $this->assertSame(['NCR', 'Region IV-A', 'Region VII', 'Region XI'], array_keys($byName));
        $this->assertNear(1815 + 1252, $byName['NCR']);
    }

    public function test6SeriesAcrossTheYear(): void
    {
        $this->actingAs(self::$adminId);
        $kpi = self::$kpis['teacher_absences']['id'];
        $data = $this->ok('GET', "/kpis/{$kpi}/series", ['unit' => (string)$this->rootId(), 'grain' => 'quarterly', 'from' => '2025-01-01', 'to' => '2025-12-31']);

        $this->assertSame(['Q1 2025', 'Q2 2025', 'Q3 2025', 'Q4 2025'], array_column($data['periods'], 'period_label'));
        $total = (float)self::$db->query("SELECT SUM(value) FROM kpi_values v JOIN kpis k ON k.id = v.kpi_id WHERE k.code = 'teacher_absences'")->fetchColumn();
        $this->assertNear($total, array_sum(array_column($data['periods'], 'value')));
    }

    public function test6bDatesDefaultToLatestPeriodWithData(): void
    {
        $this->actingAs(self::$adminId);
        $card = $this->ok('GET', '/scorecard', ['unit' => (string)$this->rootId()]);
        $this->assertSame('2025-12-01', $card['date']);
        $this->assertSame('Dec 2025', $card['kpis'][0]['period_label']);

        $kpi = self::$kpis['total_students']['id'];
        $series = $this->ok('GET', "/kpis/{$kpi}/series", ['unit' => (string)$this->rootId(), 'grain' => 'monthly']);
        $this->assertSame(12, count($series['periods']));
        $this->assertSame('Jan 2025', $series['periods'][0]['period_label']);
        $this->assertSame('Dec 2025', $series['periods'][11]['period_label']);
    }

    public function test7ReimportIsIdempotent(): void
    {
        $this->actingAs(self::$adminId);
        $result = $this->ok('POST', '/imports', [], ['mapping' => json_encode($this->mapping())], $this->upload(self::FIXTURE));

        $this->assertSame(0, $result['values_created']);
        $this->assertSame(0, $result['values_updated']);
        $this->assertSame(360, $result['unchanged']);
        $this->assertSame(0, $result['units_created']);
        $this->assertSame('360', (string)self::$db->query('SELECT COUNT(*) FROM kpi_values')->fetchColumn());
    }

    public function test8ChangedValueIsReportedAsUpdate(): void
    {
        $this->actingAs(self::$adminId);
        $csv = "Region,Division,School_Name,Report_Date,Total_Students\nNCR,Manila,Manila Science High School,2025-06-30,1300\n";
        $mapping = ['columns' => ['Total_Students' => 'total_students']] + $this->mapping();
        $preview = $this->ok('POST', '/imports/preview', [], ['mapping' => json_encode($mapping)], $this->upload($this->csvFile($csv)));
        $this->assertSame(1, $preview['will_update']);

        $result = $this->ok('POST', '/imports', [], ['mapping' => json_encode($mapping)], $this->upload($this->csvFile($csv)));
        $this->assertSame(1, $result['values_updated']);
    }

    public function test9MatchingByNameLooksInsideGroupsAndExplainsMisses(): void
    {
        $this->actingAs(self::$adminId);
        $csv = "School_Name,Report_Date,Total_Students\nManila Science High School,2025-06-30,1300\nManila,2025-06-30,5\nNowhere High,2025-06-30,5\n";
        $mapping = ['unit_columns' => ['School_Name'], 'unit_match' => 'name', 'unit_kinds' => ['School'], 'create_missing_units' => false, 'columns' => ['Total_Students' => 'total_students']] + $this->mapping();
        $preview = $this->ok('POST', '/imports/preview', [], ['mapping' => json_encode($mapping)], $this->upload($this->csvFile($csv)));

        // The school sits two groups down and is still found by its name
        $this->assertSame(2, $preview['error_count']);
        $messages = array_column($preview['errors'], 'message');
        $this->assertTrue(str_contains($messages[0], 'Manila has') && str_contains($messages[0], 'inside it'), $messages[0]);
        $this->assertTrue(str_contains($messages[1], "is called 'Nowhere High'"), $messages[1]);
        // Only the name that matches nothing could be added as a new school
        $this->assertSame(1, $preview['unknown_units']);

        // A near miss names the unit it probably meant, and isn't offered for adding
        $csv = "School_Name,Report_Date,Total_Students
manila science high-school,2025-06-30,1300
";
        $near = $this->ok('POST', '/imports/preview', [], ['mapping' => json_encode($mapping)], $this->upload($this->csvFile($csv)));
        $this->assertTrue(str_contains($near['errors'][0]['message'], "Did you mean 'Manila Science High School'?"), $near['errors'][0]['message']);
        $this->assertSame(0, $near['unknown_units']);
    }

    private function unitId(string $name): int
    {
        $stmt = self::$db->prepare('SELECT id FROM units WHERE name = ?');
        $stmt->execute([$name]);
        return (int)$stmt->fetchColumn();
    }

    private function row(array $card, string $code): array
    {
        foreach ($card['kpis'] as $row) {
            if ($row['kpi']['code'] === $code) {
                return $row;
            }
        }
        $this->fail("No scorecard row for {$code}");
    }
}
