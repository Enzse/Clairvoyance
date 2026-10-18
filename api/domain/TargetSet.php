<?php

namespace Domain;

/**
 * Targets for one KPI. A row applies to periods starting on or after its effective_from,
 * until a later row for the same unit takes over. unit_id null is the default for every unit.
 */
final class TargetSet
{
    /** @var array<int, array<array{effective_from: string, value: float}>> keyed by unit id, 0 = default */
    private array $byUnit = [];

    public function __construct(array $rows)
    {
        foreach ($rows as $row) {
            $this->byUnit[(int)($row['unit_id'] ?? 0)][] = [
                'effective_from' => $row['effective_from'],
                'value' => (float)$row['value'],
            ];
        }
        foreach ($this->byUnit as &$list) {
            usort($list, fn($a, $b) => strcmp($b['effective_from'], $a['effective_from']));
        }
    }

    /** Target set on this unit itself for the period, ignoring the default. */
    public function explicit(int $unitId, string $periodStart): ?float
    {
        return $this->lookup($unitId, $periodStart);
    }

    /** The unit's own target, else the default. */
    public function resolve(int $unitId, string $periodStart): ?float
    {
        return $this->lookup($unitId, $periodStart) ?? $this->lookup(0, $periodStart);
    }

    public function hasExplicit(int $unitId): bool
    {
        return !empty($this->byUnit[$unitId]);
    }

    private function lookup(int $key, string $periodStart): ?float
    {
        foreach ($this->byUnit[$key] ?? [] as $row) {
            if ($row['effective_from'] <= $periodStart) {
                return $row['value'];
            }
        }
        return null;
    }
}
