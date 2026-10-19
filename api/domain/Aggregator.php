<?php

namespace Domain;

/**
 * Combines one KPI's values for a scope (a set of leaf units) over one viewed period
 * (a set of native periods), following docs/data-model.md:
 *
 *  1. Over time, within each leaf unit, using the KPI's time_aggregation.
 *  2. Across leaf units, using its unit_aggregation (weighted_average uses the weight
 *     KPI's values, combined over time by the weight KPI's own rule).
 *
 * Targets are combined the same way over the same unit-period cells that have values, so
 * value and target always describe the same data. If nothing was reported, the target
 * covers every expected cell. A target is null if any of those cells has none.
 */
final class Aggregator
{
    private array $kpi;
    private ?array $weightKpi;
    private TargetSet $targets;

    public function __construct(array $kpi, ?array $weightKpi, TargetSet $targets)
    {
        if ($kpi['unit_aggregation'] === 'weighted_average' && $weightKpi === null) {
            throw new \InvalidArgumentException('weighted_average needs a weight KPI');
        }
        $this->kpi = $kpi;
        $this->weightKpi = $weightKpi;
        $this->targets = $targets;
    }

    /**
     * @param int[]    $leafIds  leaf units in scope
     * @param string[] $periods  native period starts inside the viewed period
     * @param array    $values   [unitId][periodStart] => float
     * @param array    $weights  [unitId][periodStart] => float, values of the weight KPI
     * @param int|null $scopeUnitId a non-leaf scope whose own explicit targets take precedence
     * @return array{value: ?float, target: ?float, status: ?string, reported: int, expected: int}
     */
    public function compute(array $leafIds, array $periods, array $values, array $weights = [], ?int $scopeUnitId = null): array
    {
        $cells = [];      // [unitId => periods with a value]
        $perUnit = [];    // [unitId => value combined over time]
        foreach ($leafIds as $unitId) {
            $reported = array_values(array_filter($periods, fn($p) => isset($values[$unitId][$p])));
            if ($reported) {
                $cells[$unitId] = $reported;
                $perUnit[$unitId] = $this->overTime($this->kpi['time_aggregation'], $values[$unitId], $reported);
            }
        }
        $reportedCount = array_sum(array_map('count', $cells));

        $value = $perUnit ? $this->acrossUnits($perUnit, $weights, $periods) : null;

        $targetCells = $cells ?: array_fill_keys($leafIds, $periods);
        $target = $this->target($targetCells, $weights, $periods, $scopeUnitId);

        return [
            'value' => $value === null ? null : round($value, 6),
            'target' => $target === null ? null : round($target, 6),
            'status' => Status::evaluate($this->kpi['direction'], $value, $target, (float)$this->kpi['tolerance_pct']),
            'reported' => $reportedCount,
            'expected' => count($leafIds) * count($periods),
        ];
    }

    /**
     * Leaf units with a value for every one of the periods. Comparing two periods is only fair
     * over units complete in both; otherwise a branch that hasn't reported looks like a drop.
     * @return int[]
     */
    public static function completeUnits(array $leafIds, array $periods, array $values): array
    {
        return array_values(array_filter(
            $leafIds,
            fn($unitId) => $periods && !array_filter($periods, fn($p) => !isset($values[$unitId][$p]))
        ));
    }

    private function target(array $cells, array $weights, array $periods, ?int $scopeUnitId): ?float
    {
        if (!$cells) {
            return null;
        }

        if ($scopeUnitId !== null && $this->targets->hasExplicit($scopeUnitId)) {
            $scopePeriods = array_values(array_unique(array_merge(...array_values($cells))));
            sort($scopePeriods);
            $series = [];
            foreach ($scopePeriods as $p) {
                $series[$p] = $this->targets->explicit($scopeUnitId, $p);
            }
            if (!in_array(null, $series, true)) {
                return $this->overTime($this->kpi['time_aggregation'], $series, $scopePeriods);
            }
        }

        $perUnit = [];
        foreach ($cells as $unitId => $unitPeriods) {
            $series = [];
            foreach ($unitPeriods as $p) {
                $t = $this->targets->resolve($unitId, $p);
                if ($t === null) {
                    return null;
                }
                $series[$p] = $t;
            }
            $perUnit[$unitId] = $this->overTime($this->kpi['time_aggregation'], $series, $unitPeriods);
        }
        return $this->acrossUnits($perUnit, $weights, $periods);
    }

    private function overTime(string $rule, array $series, array $periods): float
    {
        $values = array_map(fn($p) => (float)$series[$p], $periods);
        switch ($rule) {
            case 'sum':
                return array_sum($values);
            case 'average':
                return array_sum($values) / count($values);
            case 'last':
                return $values[array_search(max($periods), $periods, true)];
        }
        throw new \InvalidArgumentException("Unknown time aggregation: {$rule}");
    }

    /** @param array<int, float> $perUnit */
    private function acrossUnits(array $perUnit, array $weights, array $periods): ?float
    {
        switch ($this->kpi['unit_aggregation']) {
            case 'sum':
                return array_sum($perUnit);
            case 'average':
                return array_sum($perUnit) / count($perUnit);
            case 'weighted_average':
                $weighted = 0.0;
                $totalWeight = 0.0;
                foreach ($perUnit as $unitId => $v) {
                    $w = $this->weightFor($unitId, $weights, $periods);
                    if ($w === null || $w < 0) {
                        continue; // units without a usable weight are left out
                    }
                    $weighted += $v * $w;
                    $totalWeight += $w;
                }
                return $totalWeight > 0 ? $weighted / $totalWeight : null;
        }
        throw new \InvalidArgumentException("Unknown unit aggregation: {$this->kpi['unit_aggregation']}");
    }

    private function weightFor(int $unitId, array $weights, array $periods): ?float
    {
        $reported = array_values(array_filter($periods, fn($p) => isset($weights[$unitId][$p])));
        if (!$reported) {
            return null;
        }
        return $this->overTime($this->weightKpi['time_aggregation'], $weights[$unitId], $reported);
    }
}
