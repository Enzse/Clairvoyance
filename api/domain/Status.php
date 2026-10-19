<?php

namespace Domain;

/**
 * Green: target met. Amber: within tolerance_pct of the target. Red: worse.
 * No status when the KPI has no direction, or the value or target is missing.
 */
final class Status
{
    public static function evaluate(string $direction, ?float $value, ?float $target, float $tolerancePct): ?string
    {
        if ($direction === 'none' || $value === null || $target === null) {
            return null;
        }
        // Compare at storage precision so float noise can't flip a boundary
        $value = round($value, 6);
        $target = round($target, 6);
        $band = round(abs($target) * $tolerancePct / 100, 6);

        if ($direction === 'higher_is_better') {
            if ($value >= $target) return 'green';
            return $value >= $target - $band ? 'amber' : 'red';
        }
        if ($direction === 'lower_is_better') {
            if ($value <= $target) return 'green';
            return $value <= $target + $band ? 'amber' : 'red';
        }
        throw new \InvalidArgumentException("Unknown direction: {$direction}");
    }
}
