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

    /** Most recent period_start with any value for these units, or null if they have none. */
    public function latestPeriod(array $unitIds): ?string
    {
        if (!$unitIds) {
            return null;
        }
        $in = implode(',', array_fill(0, count($unitIds), '?'));
        $stmt = $this->db->prepare("SELECT MAX(period_start) FROM kpi_values WHERE unit_id IN ({$in})");
        $stmt->execute(array_values($unitIds));
        return $stmt->fetchColumn() ?: null;
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

    /**
     * Stores the value and records the change in kpi_value_changes. Call inside a transaction.
     * @return string 'created', 'updated' or 'unchanged'
     */
    public function upsert(int $kpiId, int $unitId, string $periodStart, float $value, ?string $note, ?int $importId, ?int $userId): string
    {
        $old = $this->current($kpiId, $unitId, $periodStart);
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
        $status = [1 => 'created', 2 => 'updated'][$stmt->rowCount()] ?? 'unchanged';
        if ($status !== 'unchanged') {
            $this->recordChange($kpiId, $unitId, $periodStart, $old, $value, $note, $importId, $userId);
        }
        return $status;
    }

    /** Deletes the value and records that. Call inside a transaction. */
    public function delete(int $kpiId, int $unitId, string $periodStart, ?int $userId = null): bool
    {
        $old = $this->current($kpiId, $unitId, $periodStart);
        if (!$old) {
            return false;
        }
        $this->db->prepare('DELETE FROM kpi_values WHERE kpi_id = ? AND unit_id = ? AND period_start = ?')
            ->execute([$kpiId, $unitId, $periodStart]);
        $this->recordChange($kpiId, $unitId, $periodStart, $old, null, null, null, $userId);
        return true;
    }

    /**
     * Changes to one KPI in these units, newest first, optionally for one period and only those
     * older than the change with id $before.
     * @return array<array> with who made each change (name or email) and the import's file name
     */
    public function changes(int $kpiId, array $unitIds, ?string $periodStart, ?int $before = null, int $limit = 50): array
    {
        if (!$unitIds) {
            return [];
        }
        $in = implode(',', array_fill(0, count($unitIds), '?'));
        $params = [$kpiId, ...array_values($unitIds)];
        $filters = '';
        if ($periodStart !== null) {
            $filters .= ' AND c.period_start = ?';
            $params[] = $periodStart;
        }
        if ($before !== null) {
            $filters .= ' AND c.id < ?';
            $params[] = $before;
        }
        $stmt = $this->db->prepare(
            "SELECT c.id, c.unit_id, un.name AS unit_name, c.period_start, c.old_value, c.new_value, c.old_note, c.new_note,
                    c.changed_at, COALESCE(u.name, u.email) AS changed_by, i.filename AS import_file
             FROM kpi_value_changes c
             JOIN units un ON un.id = c.unit_id
             LEFT JOIN users u ON u.id = c.changed_by
             LEFT JOIN imports i ON i.id = c.import_id
             WHERE c.kpi_id = ? AND c.unit_id IN ({$in}){$filters}
             ORDER BY c.id DESC LIMIT " . max(1, min(200, $limit))
        );
        $stmt->execute($params);
        return array_map(fn($r) => [
            'id' => (int)$r['id'],
            'unit_id' => (int)$r['unit_id'],
            'old_value' => $r['old_value'] === null ? null : (float)$r['old_value'],
            'new_value' => $r['new_value'] === null ? null : (float)$r['new_value'],
        ] + $r, $stmt->fetchAll());
    }

    /** The stored value and note, locked until the transaction ends. */
    private function current(int $kpiId, int $unitId, string $periodStart): ?array
    {
        $stmt = $this->db->prepare('SELECT value, note FROM kpi_values WHERE kpi_id = ? AND unit_id = ? AND period_start = ? FOR UPDATE');
        $stmt->execute([$kpiId, $unitId, $periodStart]);
        return $stmt->fetch() ?: null;
    }

    private function recordChange(int $kpiId, int $unitId, string $periodStart, ?array $old, ?float $value, ?string $note, ?int $importId, ?int $userId): void
    {
        $this->db->prepare(
            'INSERT INTO kpi_value_changes (kpi_id, unit_id, period_start, old_value, new_value, old_note, new_note, import_id, changed_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$kpiId, $unitId, $periodStart, $old['value'] ?? null, $value, $old['note'] ?? null, $note, $importId, $userId]);
    }
}
