<?php

namespace Repositories;

use PDO;

class TargetRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** @return array<int, array> [kpiId => list of {unit_id, effective_from, value}] */
    public function forKpis(array $kpiIds): array
    {
        $byKpi = array_fill_keys($kpiIds, []);
        if (!$kpiIds) {
            return $byKpi;
        }
        $in = implode(',', array_fill(0, count($kpiIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT kpi_id, unit_id, effective_from, value FROM kpi_targets
             WHERE kpi_id IN ({$in}) ORDER BY unit_id IS NOT NULL, unit_id, effective_from"
        );
        $stmt->execute(array_values($kpiIds));
        foreach ($stmt->fetchAll() as $row) {
            $byKpi[(int)$row['kpi_id']][] = [
                'unit_id' => $row['unit_id'] === null ? null : (int)$row['unit_id'],
                'effective_from' => $row['effective_from'],
                'value' => (float)$row['value'],
            ];
        }
        return $byKpi;
    }

    /** Replace all of a KPI's targets. Call inside a transaction. */
    public function replace(int $kpiId, array $targets): void
    {
        $this->db->prepare('DELETE FROM kpi_targets WHERE kpi_id = ?')->execute([$kpiId]);
        $stmt = $this->db->prepare('INSERT INTO kpi_targets (kpi_id, unit_id, effective_from, value) VALUES (?, ?, ?, ?)');
        foreach ($targets as $t) {
            $stmt->execute([$kpiId, $t['unit_id'], $t['effective_from'], $t['value']]);
        }
    }
}
