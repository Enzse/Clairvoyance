<?php

namespace Repositories;

use PDO;

class MembershipRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** @return array<array{unit_id: int, role: string}> */
    public function forUser(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT unit_id, role FROM unit_memberships WHERE user_id = ? ORDER BY unit_id');
        $stmt->execute([$userId]);
        return array_map(fn($row) => ['unit_id' => (int)$row['unit_id'], 'role' => $row['role']], $stmt->fetchAll());
    }

    /** Replace all of a user's memberships. Call inside a transaction. */
    public function replace(int $userId, array $memberships): void
    {
        $this->db->prepare('DELETE FROM unit_memberships WHERE user_id = ?')->execute([$userId]);
        $stmt = $this->db->prepare('INSERT INTO unit_memberships (user_id, unit_id, role) VALUES (?, ?, ?)');
        foreach ($memberships as $m) {
            $stmt->execute([$userId, $m['unit_id'], $m['role']]);
        }
    }
}
