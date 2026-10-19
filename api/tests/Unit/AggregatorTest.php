<?php

namespace Tests\Unit;

use Domain\Aggregator;
use Domain\TargetSet;
use Tests\TestCase;

/**
 * The worked example in docs/data-model.md: NCR region, Q2 2025, two schools
 * (1 = Doña Carmen, 2 = Manila Science), from api/demo/deped.csv.
 */
class AggregatorTest extends TestCase
{
    private const Q2 = ['2025-04-01', '2025-05-01', '2025-06-01'];
    private const SCHOOLS = [1, 2];

    private array $students;
    private array $attendance;
    private array $absences;

    protected function setUp(): void
    {
        $this->students = $this->monthly([1810, 1812, 1815], [1247, 1250, 1252]);
        $this->attendance = $this->monthly([95.9, 95.2, 94.9], [97.5, 97.0, 96.6]);
        $this->absences = $this->monthly([2, 3, 3], [0, 0, 1]);
    }

    public function testStockKpiTakesLatestMonthThenSumsSchools(): void
    {
        $result = $this->students()->compute(self::SCHOOLS, self::Q2, $this->students);

        $this->assertNear(3067, $result['value']);
        $this->assertSame(6, $result['reported']);
        $this->assertSame(6, $result['expected']);
    }

    public function testFlowKpiSumsOverTimeAndSchools(): void
    {
        $result = $this->absencesKpi([])->compute(self::SCHOOLS, self::Q2, $this->absences);

        $this->assertNear(9, $result['value']);
    }

    public function testRateKpiIsWeightedByEnrolment(): void
    {
        $result = $this->attendanceKpi([])->compute(self::SCHOOLS, self::Q2, $this->attendance, $this->students);

        $this->assertNear(96.0273, $result['value']);
    }

    public function testPlainAverageTreatsSchoolsEqually(): void
    {
        $kpi = ['time_aggregation' => 'average', 'unit_aggregation' => 'average', 'direction' => 'none', 'tolerance_pct' => 10];
        $result = (new Aggregator($kpi, null, new TargetSet([])))->compute(self::SCHOOLS, self::Q2, $this->attendance);

        $this->assertNear(96.1833, $result['value']);
    }

    public function testDefaultTargetAggregatesLikeValues(): void
    {
        // 1,800 students per school -> 3,600 for the region
        $kpi = $this->students([$this->target(null, '2025-01-01', 1800)]);
        $this->assertNear(3600, $kpi->compute(self::SCHOOLS, self::Q2, $this->students)['target']);
    }

    public function testStatusGreenWhenTargetMet(): void
    {
        $result = $this->attendanceKpi([$this->target(null, '2025-01-01', 95)])
            ->compute(self::SCHOOLS, self::Q2, $this->attendance, $this->students);

        $this->assertNear(95, $result['target']);
        $this->assertSame('green', $result['status']);
    }

    public function testLowerIsBetterAmberWithinTolerance(): void
    {
        // Target 1.4 absences per school-month -> 8.4 for the quarter; 10% band -> amber up to 9.24
        $result = $this->absencesKpi([$this->target(null, '2025-01-01', 1.4)])
            ->compute(self::SCHOOLS, self::Q2, $this->absences);

        $this->assertNear(8.4, $result['target']);
        $this->assertSame('amber', $result['status']);
    }

    public function testUnitSpecificTargetOverridesDefault(): void
    {
        $kpi = $this->students([
            $this->target(null, '2025-01-01', 1800),
            $this->target(2, '2025-01-01', 1300),
        ]);
        $this->assertNear(3100, $kpi->compute(self::SCHOOLS, self::Q2, $this->students)['target']);
    }

    public function testLaterTargetTakesEffectFromItsDate(): void
    {
        // Absences sum over time: Apr at 2, May-Jun at 1 -> 4 per school, 8 total
        $kpi = $this->absencesKpi([
            $this->target(null, '2025-01-01', 2),
            $this->target(null, '2025-05-01', 1),
        ]);
        $this->assertNear(8, $kpi->compute(self::SCHOOLS, self::Q2, $this->absences)['target']);
    }

    public function testMissingReportIsCountedAndTargetCoversSameCells(): void
    {
        unset($this->absences[2]['2025-06-01']); // Manila Science did not report June

        $result = $this->absencesKpi([$this->target(null, '2025-01-01', 2)])
            ->compute(self::SCHOOLS, self::Q2, $this->absences);

        $this->assertNear(8, $result['value']);
        $this->assertNear(10, $result['target']); // 5 reported school-months x 2
        $this->assertSame(5, $result['reported']);
        $this->assertSame(6, $result['expected']);
    }

    public function testStockUsesLatestReportedMonthWhenLastIsMissing(): void
    {
        unset($this->students[2]['2025-06-01']);
        $this->assertNear(1815 + 1250, $this->students()->compute(self::SCHOOLS, self::Q2, $this->students)['value']);
    }

    public function testIncompleteTargetsGiveNoTargetAndNoStatus(): void
    {
        $result = $this->absencesKpi([$this->target(1, '2025-01-01', 2)]) // only school 1 has a target
            ->compute(self::SCHOOLS, self::Q2, $this->absences);

        $this->assertNull($result['target']);
        $this->assertNull($result['status']);
    }

    public function testNoDataGivesNullValueButStillShowsTarget(): void
    {
        $result = $this->absencesKpi([$this->target(null, '2025-01-01', 2)])
            ->compute(self::SCHOOLS, self::Q2, []);

        $this->assertNull($result['value']);
        $this->assertNear(12, $result['target']);
        $this->assertNull($result['status']);
        $this->assertSame(0, $result['reported']);
    }

    public function testExplicitScopeTargetWins(): void
    {
        $kpi = $this->students([
            $this->target(null, '2025-01-01', 1800),
            $this->target(10, '2025-01-01', 3000), // unit 10 = the region itself
        ]);
        $this->assertNear(3000, $kpi->compute(self::SCHOOLS, self::Q2, $this->students, [], 10)['target']);
    }

    public function testUnitWithoutWeightIsLeftOutOfWeightedAverage(): void
    {
        unset($this->students[2]); // no enrolment for Manila Science
        $result = $this->attendanceKpi([])->compute(self::SCHOOLS, self::Q2, $this->attendance, $this->students);

        $this->assertNear((95.9 + 95.2 + 94.9) / 3, $result['value']);
    }

    public function testEmptyScope(): void
    {
        $result = $this->students()->compute([], self::Q2, $this->students);

        $this->assertNull($result['value']);
        $this->assertNull($result['target']);
        $this->assertSame(0, $result['expected']);
    }

    public function testNegativeValuesAreAllowed(): void
    {
        $kpi = ['time_aggregation' => 'sum', 'unit_aggregation' => 'sum', 'direction' => 'higher_is_better', 'tolerance_pct' => 10];
        $values = $this->monthly([-5, 2, 1], [3, -10, 0]);
        $this->assertNear(-9, (new Aggregator($kpi, null, new TargetSet([])))->compute(self::SCHOOLS, self::Q2, $values)['value']);
    }

    private function students(array $targets = []): Aggregator
    {
        return new Aggregator(self::STUDENTS_KPI, null, new TargetSet($targets));
    }

    private function absencesKpi(array $targets): Aggregator
    {
        $kpi = ['time_aggregation' => 'sum', 'unit_aggregation' => 'sum', 'direction' => 'lower_is_better', 'tolerance_pct' => 10];
        return new Aggregator($kpi, null, new TargetSet($targets));
    }

    private function attendanceKpi(array $targets): Aggregator
    {
        $kpi = ['time_aggregation' => 'average', 'unit_aggregation' => 'weighted_average', 'direction' => 'higher_is_better', 'tolerance_pct' => 3];
        return new Aggregator($kpi, self::STUDENTS_KPI, new TargetSet($targets));
    }

    private const STUDENTS_KPI = ['time_aggregation' => 'last', 'unit_aggregation' => 'sum', 'direction' => 'none', 'tolerance_pct' => 10];

    private function monthly(array $school1, array $school2): array
    {
        return [
            1 => array_combine(self::Q2, $school1),
            2 => array_combine(self::Q2, $school2),
        ];
    }

    public function testCompleteUnitsHaveEveryPeriod(): void
    {
        $values = [1 => ['2025-01-01' => 5, '2025-02-01' => 6], 2 => ['2025-01-01' => 7], 3 => []];
        $this->assertSame([1], Aggregator::completeUnits([1, 2, 3], ['2025-01-01', '2025-02-01'], $values));
        $this->assertSame([1, 2], Aggregator::completeUnits([1, 2, 3], ['2025-01-01'], $values));
        $this->assertSame([], Aggregator::completeUnits([1, 2], [], $values));
    }

    private function target(?int $unitId, string $from, float $value): array
    {
        return ['unit_id' => $unitId, 'effective_from' => $from, 'value' => $value];
    }
}
