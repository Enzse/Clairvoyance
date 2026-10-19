<?php

namespace Tests\Integration;

/**
 * Change from the previous period. A unit that hasn't reported yet must not look like a drop,
 * so incomplete periods are compared like-for-like: only units complete in both periods count.
 */
class ComparisonTest extends ApiTestCase
{
    private static int $root;
    private static array $units = [];
    private static array $sales;
    private static array $rate;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $t = new self();
        $t->registerAndLogin('admin@example.com', 'Shops');
        self::$root = $t->rootId();
        foreach (['A', 'B', 'C'] as $name) {
            self::$units[$name] = $t->createUnit(self::$root, "Store {$name}", 'Store');
        }
        self::$sales = $t->createKpi(['code' => 'sales', 'name' => 'Sales']);
        self::$rate = $t->createKpi(['code' => 'rate', 'name' => 'Rate', 'time_aggregation' => 'average', 'unit_aggregation' => 'average']);
        $t->enter(self::$sales, ['A' => 100, 'B' => 200, 'C' => 300], '2025-08-01');
        $t->enter(self::$sales, ['A' => 110, 'B' => 190], '2025-09-01'); // C hasn't reported September yet
        $t->enter(self::$rate, ['A' => 50, 'B' => 70], '2025-08-01');
        $t->enter(self::$rate, ['A' => 60], '2025-09-01');
    }

    private function enter(array $kpi, array $byUnit, string $period): void
    {
        $this->ok('PUT', '/values', [], ['values' => array_map(
            fn($name, $value) => ['kpi_id' => $kpi['id'], 'unit_id' => self::$units[$name], 'period' => $period, 'value' => $value],
            array_keys($byUnit), $byUnit
        )]);
    }

    private function row(string $code, string $date): array
    {
        foreach ($this->ok('GET', '/scorecard', ['unit' => self::$root, 'date' => $date])['kpis'] as $row) {
            if ($row['kpi']['code'] === $code) {
                return $row;
            }
        }
        $this->fail("No {$code} row");
    }

    public function test1AMissingReportIsNotADrop(): void
    {
        $row = $this->row('sales', '2025-09-01');
        $this->assertSame(300.0, (float)$row['value'], 'the total still shows what has been reported');
        $this->assertSame('like_for_like', $row['change']['basis']);
        $this->assertSame([2, 3], [$row['change']['units_compared'], $row['change']['units_total']]);
        // A and B: 300 in August, 300 in September. Comparing totals would say -300.
        $this->assertSame(0.0, (float)$row['change']['value']);
        $this->assertSame('flat', $row['change']['direction']);
    }

    public function test2AveragesAreComparedOverTheSameUnits(): void
    {
        // Only A reported September: 50 -> 60. The plain averages (60 vs 60) would hide that.
        $row = $this->row('rate', '2025-09-01');
        $this->assertSame(10.0, (float)$row['change']['value']);
        $this->assertSame('better', $row['change']['direction']);
    }

    public function test3CompletePeriodsCompareTotals(): void
    {
        $this->enter(self::$sales, ['C' => 310], '2025-09-01');
        $row = $this->row('sales', '2025-09-01');
        $this->assertSame('all', $row['change']['basis']);
        $this->assertSame(10.0, (float)$row['change']['value']);
    }

    public function test4TheKpiPageGetsTheSameChange(): void
    {
        $series = $this->ok('GET', '/kpis/' . self::$rate['id'] . '/series', ['unit' => self::$root, 'grain' => 'monthly', 'to' => '2025-09-01']);
        $this->assertSame('like_for_like', $series['change']['basis']);
        $this->assertSame(10.0, (float)$series['change']['value']);
    }

    public function test5NoChangeWhenNoUnitCanBeCompared(): void
    {
        $this->enter(self::$sales, ['C' => 320], '2025-10-01');
        $this->enter(self::$sales, ['A' => 5], '2025-11-01');
        // October: only C. November: only A. Nobody reported both.
        $this->assertSame(null, $this->row('sales', '2025-11-01')['change']);
    }

    public function test6QuarterlyViewNeedsEveryMonthInBothQuarters(): void
    {
        // Nobody reported July, so no unit is complete in Q3 and Q4 can't be compared with it
        $row =$this->ok('GET', '/scorecard', ['unit' => self::$root, 'date' => '2025-10-01', 'grain' => 'quarterly'])['kpis'];
        $sales = array_values(array_filter($row, fn($r) => $r['kpi']['code'] === 'sales'))[0];
        $this->assertSame(null, $sales['change']);
    }
}
