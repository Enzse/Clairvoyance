<?php

namespace Tests\Integration;

/**
 * A KPI reported every day, read during the month: days still to come aren't missing, the
 * Day view opens on today, and a KPI can count a day with no figure as 0.
 */
class DailyKpiTest extends ApiTestCase
{
    private static int $store;
    private static array $sales;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $t = new self();
        $t->registerAndLogin('admin@example.com', 'Daily Co');
        self::$store = $t->createUnit($t->rootId(), 'Store', 'Store');
        self::$sales = $t->createKpi(['code' => 'sales', 'name' => 'Sales', 'frequency' => 'daily', 'direction' => 'higher_is_better', 'tolerance_pct' => 0]);
        $t->ok('PUT', '/kpis/' . self::$sales['id'] . '/targets', [], ['targets' => [['unit_id' => null, 'effective_from' => '2020-01-01', 'value' => 1]]]);
    }

    private function row(array $query): array
    {
        return $this->ok('GET', '/scorecard', ['unit' => self::$store] + $query)['kpis'][0];
    }

    public function test1OnlyDaysSoFarAreExpected(): void
    {
        // A figure two days ago (or on the 1st, early in the month), nothing since
        $today = date('Y-m-d');
        $day = max(date('Y-m-01'), date('Y-m-d', strtotime('-2 days')));
        $this->ok('PUT', '/values', [], ['values' => [['kpi_id' => self::$sales['id'], 'unit_id' => self::$store, 'period' => $day, 'value' => 1]]]);

        $month = $this->row(['date' => $today, 'grain' => 'monthly']);
        $this->assertSame((int)date('j'), $month['expected'], 'the days of the month so far, not all of them');
        $this->assertSame(1, $month['reported']);
    }

    public function test2TheDayViewOpensOnToday(): void
    {
        $day = $this->row(['grain' => 'daily']);
        $this->assertSame(date('Y-m-d'), $day['period_start']);
        $this->assertSame(null, $day['value'], 'nothing entered today, so nothing to call on target');
    }

    public function test3ADayWithNoFigureCanCountAsZero(): void
    {
        $this->ok('PUT', '/kpis/' . self::$sales['id'], [], ['missing_is_zero' => true]);
        $month = $this->row(['date' => date('Y-m-d'), 'grain' => 'monthly']);
        $this->assertNear(1, $month['value']);
        $this->assertNear((int)date('j'), $month['target']);
        // Behind, unless it's the first day of the month and the one figure is today's target
        $this->assertSame(date('j') === '1' ? 'green' : 'red', $month['status']);
        $this->assertSame(1, $month['reported'], 'real figures are still counted as reports');
    }
}
