<?php

namespace Repositories;

use PDO;

class ImportRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function start(int $organizationId, ?int $userId, string $filename, int $rowsTotal): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO imports (organization_id, user_id, filename, rows_total, values_created, values_updated) VALUES (?, ?, ?, ?, 0, 0)'
        );
        $stmt->execute([$organizationId, $userId, $filename, $rowsTotal]);
        return (int)$this->db->lastInsertId();
    }

    public function finish(int $id, int $created, int $updated): void
    {
        $this->db->prepare('UPDATE imports SET values_created = ?, values_updated = ? WHERE id = ?')->execute([$created, $updated, $id]);
    }

    public function recent(int $organizationId, int $limit = 20): array
    {
        $stmt = $this->db->prepare(
            'SELECT i.id, i.filename, i.rows_total, i.values_created, i.values_updated, i.created_at, u.email AS user_email
             FROM imports i LEFT JOIN users u ON u.id = i.user_id
             WHERE i.organization_id = ?
             ORDER BY i.id DESC LIMIT ?'
        );
        $stmt->bindValue(1, $organizationId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
