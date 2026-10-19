<?php

namespace Repositories;

use PDO;

/**
 * Every query is scoped to one organisation. Each KPI comes back with `scope_unit_ids`: the
 * units whose leaves report it (empty = the whole organisation).
 */
class KpiRepository
{
    public const FIELDS = [
        'code', 'name', 'description', 'frequency', 'time_aggregation', 'unit_aggregation', 'weight_kpi_id',
        'direction', 'tolerance_pct', 'applies_to_kind', 'decimals', 'prefix', 'suffix', 'is_active',
    ];

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function all(int $organizationId, bool $activeOnly = false): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM kpis WHERE organization_id = ?' . ($activeOnly ? ' AND is_active = 1' : '') . ' ORDER BY name'
        );
        $stmt->execute([$organizationId]);
        return $this->withScopes($stmt->fetchAll());
    }

    public function find(int $organizationId, int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM kpis WHERE id = ? AND organization_id = ?');
        $stmt->execute([$id, $organizationId]);
        return $this->withScopes($stmt->fetchAll())[0] ?? null;
    }

    public function findByCode(int $organizationId, string $code): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM kpis WHERE code = ? AND organization_id = ?');
        $stmt->execute([$code, $organizationId]);
        return $this->withScopes($stmt->fetchAll())[0] ?? null;
    }

    public function create(int $organizationId, array $fields): int
    {
        $fields = ['organization_id' => $organizationId] + array_intersect_key($fields, array_flip(self::FIELDS));
        $columns = implode(', ', array_keys($fields));
        $placeholders = implode(', ', array_fill(0, count($fields), '?'));
        $this->db->prepare("INSERT INTO kpis ({$columns}) VALUES ({$placeholders})")->execute(array_values($fields));
        return (int)$this->db->lastInsertId();
    }

    public function update(int $organizationId, int $id, array $fields): void
    {
        $fields = array_intersect_key($fields, array_flip(self::FIELDS));
        if (!$fields) {
            return;
        }
        $sets = implode(', ', array_map(fn($column) => "{$column} = ?", array_keys($fields)));
        $this->db->prepare("UPDATE kpis SET {$sets} WHERE id = ? AND organization_id = ?")
            ->execute([...array_values($fields), $id, $organizationId]);
    }

    /** Replace the units a KPI is reported by. Call with ids already checked to be in the KPI's organisation. */
    public function replaceScope(int $kpiId, array $unitIds): void
    {
        $this->db->prepare('DELETE FROM kpi_scope_units WHERE kpi_id = ?')->execute([$kpiId]);
        $stmt = $this->db->prepare('INSERT INTO kpi_scope_units (kpi_id, unit_id) VALUES (?, ?)');
        foreach (array_unique($unitIds) as $unitId) {
            $stmt->execute([$kpiId, $unitId]);
        }
    }

    public function hasValues(int $id): bool
    {
        $stmt = $this->db->prepare('SELECT EXISTS (SELECT 1 FROM kpi_values WHERE kpi_id = ?)');
        $stmt->execute([$id]);
        return (bool)$stmt->fetchColumn();
    }

    /** Active KPIs that use this one as their weight. */
    public function activeDependents(int $organizationId, int $id): array
    {
        $stmt = $this->db->prepare('SELECT name FROM kpis WHERE weight_kpi_id = ? AND is_active = 1 AND organization_id = ?');
        $stmt->execute([$id, $organizationId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function withScopes(array $rows): array
    {
        if (!$rows) {
            return [];
        }
        $ids = array_map(fn($r) => (int)$r['id'], $rows);
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("SELECT kpi_id, unit_id FROM kpi_scope_units WHERE kpi_id IN ({$in}) ORDER BY unit_id");
        $stmt->execute($ids);
        $scopes = [];
        foreach ($stmt->fetchAll() as $s) {
            $scopes[(int)$s['kpi_id']][] = (int)$s['unit_id'];
        }

        return array_map(function (array $row) use ($scopes) {
            $row['id'] = (int)$row['id'];
            $row['organization_id'] = (int)$row['organization_id'];
            $row['weight_kpi_id'] = $row['weight_kpi_id'] === null ? null : (int)$row['weight_kpi_id'];
            $row['tolerance_pct'] = (float)$row['tolerance_pct'];
            $row['decimals'] = (int)$row['decimals'];
            $row['is_active'] = (bool)$row['is_active'];
            $row['scope_unit_ids'] = $scopes[$row['id']] ?? [];
            return $row;
        }, $rows);
    }
}
