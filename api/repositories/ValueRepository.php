<?php

namespace Repositories;

use PDO;

class ValueRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Values for the given KPIs and units with period_start in [$from, $to).
     * @return array<int, array<int, array<string, float>>> [kpiId][unitId][periodStart] => value
     */
    public function grid(array $kpiIds, array $unitIds, string $from, string $to): array
    {
        if (!$kpiIds || !$unitIds) {
            return [];
        }
        $kpiIn = implode(',', array_fill(0, count($kpiIds), '?'));
        $unitIn = implode(',', array_fill(0, count($unitIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT kpi_id, unit_id, period_start, value FROM kpi_values
             WHERE kpi_id IN ({$kpiIn}) AND unit_id IN ({$unitIn}) AND period_start >= ? AND period_start < ?"
        );
        $stmt->execute([...array_values($kpiIds), ...array_values($unitIds), $from, $to]);

        $grid = [];
        foreach ($stmt->fetchAll() as $row) {
            $grid[(int)$row['kpi_id']][(int)$row['unit_id']][$row['period_start']] = (float)$row['value'];
        }
        return $grid;
    }

    /** Stored values for one unit at the given periods, keyed "kpiId|periodStart". */
    public function forUnit(int $unitId, array $periodStarts): array
    {
        $periodStarts = array_values(array_unique($periodStarts));
        if (!$periodStarts) {
            return [];
        }
        $in = implode(',', array_fill(0, count($periodStarts), '?'));
        $stmt = $this->db->prepare(
            "SELECT v.kpi_id, v.period_start, v.value, v.note, v.updated_at, u.email AS updated_by
             FROM kpi_values v LEFT JOIN users u ON u.id = v.updated_by
             WHERE v.unit_id = ? AND v.period_start IN ({$in})"
        );
        $stmt->execute([$unitId, ...$periodStarts]);

        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[$row['kpi_id'] . '|' . $row['period_start']] = $row;
        }
        return $rows;
    }

    /** @return string 'created', 'updated' or 'unchanged' */
    public function upsert(int $kpiId, int $unitId, string $periodStart, float $value, ?string $note, ?int $importId, ?int $userId): string
    {
        $stmt = $this->db->prepare(
            'INSERT INTO kpi_values (kpi_id, unit_id, period_start, value, note, import_id, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               import_id  = IF(value <=> VALUES(value) AND note <=> VALUES(note), import_id, VALUES(import_id)),
               updated_by = IF(value <=> VALUES(value) AND note <=> VALUES(note), updated_by, VALUES(updated_by)),
               value = VALUES(value),
               note = VALUES(note)'
        );
        // Provenance columns are assigned first so they still compare against the old value
        $stmt->execute([$kpiId, $unitId, $periodStart, $value, $note, $importId, $userId]);
        // MySQL reports 1 for an insert, 2 for an update and 0 when nothing changed
        return [1 => 'created', 2 => 'updated'][$stmt->rowCount()] ?? 'unchanged';
    }

    public function delete(int $kpiId, int $unitId, string $periodStart): bool
    {
        $stmt = $this->db->prepare('DELETE FROM kpi_values WHERE kpi_id = ? AND unit_id = ? AND period_start = ?');
        $stmt->execute([$kpiId, $unitId, $periodStart]);
        return $stmt->rowCount() > 0;
    }
}
