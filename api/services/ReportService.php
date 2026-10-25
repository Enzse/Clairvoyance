<?php

namespace Services;

use Core\Auth;
use Core\HttpException;
use Domain\Aggregator;
use Domain\KpiScope;
use Domain\Period;
use Domain\TargetSet;
use Repositories\KpiRepository;
use Repositories\TargetRepository;
use Repositories\ValueRepository;

/**
 * Read side: every number the frontend shows comes from here, computed by Domain\Aggregator
 * over the leaf units that report each KPI (Domain\KpiScope).
 */
class ReportService
{
    private const MAX_PERIODS = 400;

    private Auth $auth;
    private AccessService $access;
    private KpiRepository $kpis;
    private ValueRepository $values;
    private TargetRepository $targets;
    private ?array $kpiById = null;

    public function __construct(Auth $auth, AccessService $access, KpiRepository $kpis, ValueRepository $values, TargetRepository $targets)
    {
        $this->auth = $auth;
        $this->access = $access;
        $this->kpis = $kpis;
        $this->values = $values;
        $this->targets = $targets;
    }

    /**
     * Every active KPI for a unit at the period containing $date (default: the latest period
     * with data). Each KPI is shown at $grain, or at its own frequency if that is coarser
     * (or if no grain is given).
     */
    public function scorecard(int $unitId, ?string $date, ?string $grain, int $history = 12): array
    {
        $unit = $this->access->requireView($unitId);
        $date ??= $this->defaultDate($unitId, $grain);
        $this->checkDate($date);
        if ($grain !== null && !Period::isValidFrequency($grain)) {
            throw HttpException::badRequest('grain must be one of: ' . implode(', ', Period::FREQUENCIES));
        }

        $rows = [];
        $tree = $this->access->tree();
        foreach ($this->allKpis() as $kpi) {
            // A department's scorecard leaves out KPIs only other departments report
            if (!$kpi['is_active'] || !KpiScope::concerns($tree, $kpi, $unitId)) {
                continue;
            }
            $g = $grain === null ? $kpi['frequency'] : Period::coarser($grain, $kpi['frequency']);
            $current = Period::align($date, $g);
            $starts = $this->range(Period::shift($current, $g, -($history - 1)), $current, $g);
            $series = $this->series($kpi, $unitId, $starts, $g);

            $now = end($series);
            $before = count($series) > 1 ? $series[count($series) - 2] : null;
            $rows[] = $now + [
                'kpi' => $kpi,
                'grain' => $g,
                'previous' => $before ? ['period_start' => $before['period_start'], 'period_label' => $before['period_label'], 'value' => $before['value']] : null,
                'change' => $this->comparison($kpi, $unitId, $series, $g),
                'sparkline' => array_map(fn($p) => ['period_start' => $p['period_start'], 'value' => $p['value'], 'status' => $p['status']], $series),
            ];
        }

        return ['unit' => $unit, 'date' => $date, 'kpis' => $rows];
    }

    /**
     * One KPI for a unit over a date range, one entry per $grain period. Defaults: $to is
     * the latest period with data, $from is 11 periods before it (12 in total).
     */
    public function kpiSeries(int $kpiId, int $unitId, string $grain, ?string $from, ?string $to): array
    {
        $unit = $this->access->requireView($unitId);
        $kpi = $this->kpi($kpiId);
        $this->checkGrain($kpi, $grain);
        $to ??= $this->defaultDate($unitId, $grain);
        $this->checkDate($to);
        $from ??= Period::shift(Period::align($to, $grain), $grain, -11);
        $this->checkDate($from);
        $starts = $this->range(Period::align($from, $grain), Period::align($to, $grain), $grain);

        $periods = $this->series($kpi, $unitId, $starts, $grain);
        return [
            'kpi' => $kpi, 'unit' => $unit, 'grain' => $grain, 'periods' => $periods,
            'change' => $this->comparison($kpi, $unitId, $periods, $grain),
            'missing' => $this->missing($kpi, $unitId, end($starts), $grain),
        ];
    }

    /** Who still owes figures for the period, and how many of its weeks or days each has in. */
    private function missing(array $kpi, int $unitId, string $start, string $grain): array
    {
        $tree = $this->access->tree();
        $leafIds = KpiScope::leaves($tree, $kpi, $unitId);
        [$values] = $this->load($kpi, $leafIds, $start, Period::next($start, $grain));
        $periods = $this->periods($start, $grain, $kpi);
        $rows = [];
        foreach (Aggregator::incompleteUnits($leafIds, $periods, $values) as $id => $reported) {
            $rows[] = ['unit_id' => $id, 'name' => $tree->get($id)['name'], 'reported' => $reported, 'expected' => count($periods)];
        }
        usort($rows, fn($a, $b) => strcmp($a['name'], $b['name']));
        return $rows;
    }

    /** One KPI at one period (default: the latest with data) for each unit directly under $unitId. */
    public function breakdown(int $kpiId, int $unitId, ?string $date, string $grain): array
    {
        $unit = $this->access->requireView($unitId);
        $kpi = $this->kpi($kpiId);
        $this->checkGrain($kpi, $grain);
        $date ??= $this->defaultDate($unitId, $grain);
        $this->checkDate($date);
        $tree = $this->access->tree();
        if ($tree->isLeaf($unitId)) {
            throw HttpException::badRequest("{$unit['name']} has nothing under it to break down");
        }

        $start = Period::align($date, $grain);
        [$values, $weights] = $this->load($kpi, KpiScope::leaves($tree, $kpi, $unitId), $start, Period::next($start, $grain));
        $aggregator = $this->aggregator($kpi);
        $periods = $this->periods($start, $grain, $kpi);

        // Units the KPI doesn't concern are left out, rather than shown as "no data"
        $rows = [];
        foreach ($tree->childIds($unitId) as $childId) {
            $child = $tree->get($childId);
            if (!$child['is_active'] || !KpiScope::concerns($tree, $kpi, $childId)) {
                continue;
            }
            $isLeaf = $tree->isLeaf($childId);
            $result = $aggregator->compute(
                KpiScope::leaves($tree, $kpi, $childId), $periods, $values, $weights, $isLeaf ? null : $childId
            );
            $rows[] = ['unit' => $child + ['is_leaf' => $isLeaf]] + $result;
        }
        usort($rows, fn($a, $b) => strcmp($a['unit']['name'], $b['unit']['name']));

        return [
            'kpi' => $kpi,
            'unit' => $unit,
            'grain' => $grain,
            'period_start' => $start,
            'period_label' => Period::label($start, $grain),
            'units' => $rows,
        ];
    }

    /** Aggregated results for consecutive grain periods, for one KPI and one unit's subtree. */
    private function series(array $kpi, int $unitId, array $starts, string $grain): array
    {
        $tree = $this->access->tree();
        $leafIds = KpiScope::leaves($tree, $kpi, $unitId);
        [$values, $weights] = $this->load($kpi, $leafIds, $starts[0], Period::next(end($starts), $grain));
        $aggregator = $this->aggregator($kpi);
        $scope = $tree->isLeaf($unitId) ? null : $unitId;

        return array_map(fn(string $start) => [
            'period_start' => $start,
            'period_label' => Period::label($start, $grain),
        ] + $aggregator->compute($leafIds, $this->periods($start, $grain, $kpi), $values, $weights, $scope), $starts);
    }

    /** @return array{0: array, 1: array} the KPI's values and its weight KPI's values */
    private function load(array $kpi, array $leafIds, string $from, string $to): array
    {
        $ids = array_filter([$kpi['id'], $kpi['weight_kpi_id']]);
        $grid = $this->values->grid($ids, $leafIds, $from, $to);
        return [$grid[$kpi['id']] ?? [], $kpi['weight_kpi_id'] ? ($grid[$kpi['weight_kpi_id']] ?? []) : []];
    }

    private function aggregator(array $kpi): Aggregator
    {
        $weightKpi = $kpi['weight_kpi_id'] ? $this->kpi($kpi['weight_kpi_id']) : null;
        return new Aggregator($kpi, $weightKpi, new TargetSet($this->targets->forKpis([$kpi['id']])[$kpi['id']]));
    }

    /**
     * Change from the second-to-last to the last period of a series. When both are complete,
     * the totals are compared ("all"). When either is missing figures, totals would mistake a
     * unit that hasn't reported for a drop, so only the units that reported fully in both
     * periods are compared ("like_for_like"), as retailers compare same-store sales. Null when
     * no unit can be compared.
     */
    private function comparison(array $kpi, int $unitId, array $series, string $grain): ?array
    {
        if (count($series) < 2) {
            return null;
        }
        [$before, $now] = array_slice($series, -2);
        if ($before['value'] === null || $now['value'] === null) {
            return null;
        }
        // Complete periods, or a KPI where a missing figure counts as 0: compare the totals
        $complete = $now['reported'] === $now['expected'] && $before['reported'] === $before['expected'];
        if ($complete || !empty($kpi['missing_is_zero'])) {
            return $this->change($kpi, $now['value'], $before['value']) + ['basis' => 'all', 'units_compared' => null, 'units_total' => null, 'left_out' => []];
        }

        $tree = $this->access->tree();
        $leafIds = KpiScope::leaves($tree, $kpi, $unitId);
        [$values, $weights] = $this->load($kpi, $leafIds, $before['period_start'], Period::next($now['period_start'], $grain));
        $periodsBefore = $this->periods($before['period_start'], $grain, $kpi);
        $periodsNow = $this->periods($now['period_start'], $grain, $kpi);
        $comparable = array_values(array_intersect(
            Aggregator::completeUnits($leafIds, $periodsBefore, $values),
            Aggregator::completeUnits($leafIds, $periodsNow, $values)
        ));
        if (!$comparable) {
            return null;
        }
        $aggregator = $this->aggregator($kpi);
        $then = $aggregator->compute($comparable, $periodsBefore, $values, $weights)['value'];
        $latest = $aggregator->compute($comparable, $periodsNow, $values, $weights)['value'];
        $change = $this->change($kpi, $latest, $then);
        // Named, so "4 of 5" can say which one wasn't counted
        $leftOut = array_map(fn($id) => $tree->get($id)['name'], array_values(array_diff($leafIds, $comparable)));
        sort($leftOut);
        return $change === null ? null : $change + [
            'basis' => 'like_for_like', 'units_compared' => count($comparable), 'units_total' => count($leafIds), 'left_out' => $leftOut,
        ];
    }

    /** Difference between two values and whether it moved in the good direction. */
    private function change(array $kpi, ?float $now, ?float $before): ?array
    {
        if ($now === null || $before === null) {
            return null;
        }
        $delta = round($now - $before, 6);
        $direction = null;
        if ($kpi['direction'] !== 'none') {
            $better = $kpi['direction'] === 'higher_is_better' ? $delta > 0 : $delta < 0;
            $direction = $delta == 0 ? 'flat' : ($better ? 'better' : 'worse');
        }
        return [
            'value' => $delta,
            'percent' => $before != 0 ? round($delta / abs($before) * 100, 2) : null,
            'direction' => $direction,
        ];
    }

    private function range(string $first, string $last, string $grain): array
    {
        if ($first > $last) {
            throw HttpException::badRequest('from must not be after to');
        }
        $starts = [];
        for ($p = $first; $p <= $last; $p = Period::next($p, $grain)) {
            $starts[] = $p;
            if (count($starts) > self::MAX_PERIODS) {
                throw HttpException::badRequest('Range too long; at most ' . self::MAX_PERIODS . ' periods');
            }
        }
        return $starts;
    }

    private function allKpis(): array
    {
        if ($this->kpiById === null) {
            $this->kpiById = array_column($this->kpis->all($this->auth->organizationId()), null, 'id');
        }
        return $this->kpiById;
    }

    private function kpi(int $id): array
    {
        return $this->allKpis()[$id] ?? throw HttpException::notFound('KPI not found');
    }

    private function checkGrain(array $kpi, string $grain): void
    {
        if (!Period::isValidFrequency($grain)) {
            throw HttpException::badRequest('grain must be one of: ' . implode(', ', Period::FREQUENCIES));
        }
        if (!Period::isAtLeast($grain, $kpi['frequency'])) {
            throw HttpException::badRequest("{$kpi['name']} is reported {$kpi['frequency']}, so it can't be shown {$grain}");
        }
    }

    /** The KPI's own periods in a viewed period that have started: future days aren't missing. */
    private function periods(string $start, string $grain, array $kpi): array
    {
        return Period::elapsedWithin($start, $grain, $kpi['frequency'], date('Y-m-d'));
    }

    /**
     * Where a scorecard opens when no date is given: today for days and weeks (an empty today
     * should look empty), otherwise the latest period with any figure under the unit.
     */
    private function defaultDate(int $unitId, ?string $grain): string
    {
        if ($grain === 'daily' || $grain === 'weekly') {
            return date('Y-m-d');
        }
        $leaves = $this->access->tree()->activeLeafIds($unitId);
        return $this->values->latestPeriod($leaves) ?? date('Y-m-d');
    }

    private function checkDate(string $date): void
    {
        if (!Period::isValidDate($date)) {
            throw HttpException::badRequest('Dates must be YYYY-MM-DD');
        }
    }
}
