<?php

namespace Repositories;

use PDO;

/** Every query is scoped to one organisation. */
class UnitRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** Every unit of the organisation, including inactive ones. Trees are small enough to load whole. */
    public function all(int $organizationId): array
    {
        $stmt = $this->db->prepare(
            'SELECT u.id, u.parent_id, u.name, u.code, u.kind, u.is_active,
                    EXISTS (SELECT 1 FROM kpi_values v WHERE v.unit_id = u.id) AS has_values
             FROM units u
             WHERE u.organization_id = ?
             ORDER BY u.name'
        );
        $stmt->execute([$organizationId]);

        return array_map(fn(array $row) => [
            'id' => (int)$row['id'],
            'parent_id' => $row['parent_id'] === null ? null : (int)$row['parent_id'],
            'name' => $row['name'],
            'code' => $row['code'],
            'kind' => $row['kind'],
            'is_active' => (bool)$row['is_active'],
            'has_values' => (bool)$row['has_values'],
        ], $stmt->fetchAll());
    }

    /** $parentId null creates the organisation's root unit. */
    public function create(int $organizationId, ?int $parentId, string $name, ?string $code, ?string $kind): int
    {
        $stmt = $this->db->prepare('INSERT INTO units (organization_id, parent_id, name, code, kind) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$organizationId, $parentId, $name, $code, $kind]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $organizationId, int $id, array $fields): void
    {
        $allowed = array_intersect_key($fields, array_flip(['parent_id', 'name', 'code', 'kind', 'is_active']));
        if (!$allowed) {
            return;
        }
        $sets = implode(', ', array_map(fn($column) => "{$column} = ?", array_keys($allowed)));
        $stmt = $this->db->prepare("UPDATE units SET {$sets} WHERE id = ? AND organization_id = ?");
        $stmt->execute([...array_values($allowed), $id, $organizationId]);
    }

    public function renameRoot(int $organizationId, string $name): void
    {
        $this->db->prepare('UPDATE units SET name = ? WHERE organization_id = ? AND parent_id IS NULL')->execute([$name, $organizationId]);
    }

    public function delete(int $organizationId, int $id): void
    {
        $this->db->prepare('DELETE FROM units WHERE id = ? AND organization_id = ?')->execute([$id, $organizationId]);
    }
}
