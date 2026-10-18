<?php

namespace Repositories;

use PDO;

class UnitRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** Every unit, including inactive ones. Organisation trees are small enough to load whole. */
    public function all(): array
    {
        $rows = $this->db->query(
            'SELECT u.id, u.parent_id, u.name, u.code, u.kind, u.is_active,
                    EXISTS (SELECT 1 FROM kpi_values v WHERE v.unit_id = u.id) AS has_values
             FROM units u
             ORDER BY u.name'
        )->fetchAll();

        return array_map(fn(array $row) => [
            'id' => (int)$row['id'],
            'parent_id' => $row['parent_id'] === null ? null : (int)$row['parent_id'],
            'name' => $row['name'],
            'code' => $row['code'],
            'kind' => $row['kind'],
            'is_active' => (bool)$row['is_active'],
            'has_values' => (bool)$row['has_values'],
        ], $rows);
    }

    public function create(int $parentId, string $name, ?string $code, ?string $kind): int
    {
        $stmt = $this->db->prepare('INSERT INTO units (parent_id, name, code, kind) VALUES (?, ?, ?, ?)');
        $stmt->execute([$parentId, $name, $code, $kind]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $fields): void
    {
        $allowed = array_intersect_key($fields, array_flip(['parent_id', 'name', 'code', 'kind', 'is_active']));
        if (!$allowed) {
            return;
        }
        $sets = implode(', ', array_map(fn($column) => "{$column} = ?", array_keys($allowed)));
        $stmt = $this->db->prepare("UPDATE units SET {$sets} WHERE id = ?");
        $stmt->execute([...array_values($allowed), $id]);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM units WHERE id = ?')->execute([$id]);
    }
}
