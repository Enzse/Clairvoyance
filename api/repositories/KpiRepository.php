<?php

namespace Repositories;

use PDO;

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

    public function all(bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM kpis' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY name';
        return array_map([$this, 'cast'], $this->db->query($sql)->fetchAll());
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM kpis WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? $this->cast($row) : null;
    }

    public function findByCode(string $code): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM kpis WHERE code = ?');
        $stmt->execute([$code]);
        $row = $stmt->fetch();
        return $row ? $this->cast($row) : null;
    }

    public function create(array $fields): int
    {
        $fields = array_intersect_key($fields, array_flip(self::FIELDS));
        $columns = implode(', ', array_keys($fields));
        $placeholders = implode(', ', array_fill(0, count($fields), '?'));
        $this->db->prepare("INSERT INTO kpis ({$columns}) VALUES ({$placeholders})")->execute(array_values($fields));
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $fields): void
    {
        $fields = array_intersect_key($fields, array_flip(self::FIELDS));
        if (!$fields) {
            return;
        }
        $sets = implode(', ', array_map(fn($column) => "{$column} = ?", array_keys($fields)));
        $this->db->prepare("UPDATE kpis SET {$sets} WHERE id = ?")->execute([...array_values($fields), $id]);
    }

    public function hasValues(int $id): bool
    {
        $stmt = $this->db->prepare('SELECT EXISTS (SELECT 1 FROM kpi_values WHERE kpi_id = ?)');
        $stmt->execute([$id]);
        return (bool)$stmt->fetchColumn();
    }

    /** Active KPIs that use this one as their weight. */
    public function activeDependents(int $id): array
    {
        $stmt = $this->db->prepare('SELECT name FROM kpis WHERE weight_kpi_id = ? AND is_active = 1');
        $stmt->execute([$id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function cast(array $row): array
    {
        $row['id'] = (int)$row['id'];
        $row['weight_kpi_id'] = $row['weight_kpi_id'] === null ? null : (int)$row['weight_kpi_id'];
        $row['tolerance_pct'] = (float)$row['tolerance_pct'];
        $row['decimals'] = (int)$row['decimals'];
        $row['is_active'] = (bool)$row['is_active'];
        return $row;
    }
}
