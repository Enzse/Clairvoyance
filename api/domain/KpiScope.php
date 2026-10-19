<?php

namespace Domain;

/**
 * Which units report a KPI. A KPI's `scope_unit_ids` lists units whose leaves report it (empty
 * = the whole organisation); `applies_to_kind` optionally narrows that to leaves of one kind.
 * So "Revenue" can belong to the Sales department only, and "Stock-outs" to every store.
 */
final class KpiScope
{
    /** Active leaves under $unitId that report the KPI. */
    public static function leaves(UnitTree $tree, array $kpi, int $unitId): array
    {
        $leaves = $tree->activeLeafIds($unitId, $kpi['applies_to_kind'] ?? null);
        return array_values(array_filter($leaves, fn(int $leaf) => self::covers($tree, $kpi, $leaf)));
    }

    /** Whether a unit (or a new unit created under it) falls inside the KPI's scope. */
    public static function covers(UnitTree $tree, array $kpi, int $unitId): bool
    {
        $scope = $kpi['scope_unit_ids'] ?? [];
        if (!$scope) {
            return true;
        }
        foreach ($scope as $scopeId) {
            if ($tree->isInSubtree($unitId, $scopeId)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether the KPI belongs on this unit's scorecard: the unit's subtree and the scope
     * overlap. A department's scorecard leaves out other departments' KPIs.
     */
    public static function concerns(UnitTree $tree, array $kpi, int $unitId): bool
    {
        $scope = $kpi['scope_unit_ids'] ?? [];
        if (!$scope) {
            return true;
        }
        foreach ($scope as $scopeId) {
            if ($tree->isInSubtree($unitId, $scopeId) || $tree->isInSubtree($scopeId, $unitId)) {
                return true;
            }
        }
        return false;
    }
}
