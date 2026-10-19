<?php

namespace Repositories;

use PDO;

/** Unit access within one organisation. */
class MembershipRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** @return array<array{unit_id: int, role: string}> */
    public function forUser(int $organizationId, int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT m.unit_id, m.role FROM unit_memberships m JOIN units u ON u.id = m.unit_id
             WHERE m.user_id = ? AND u.organization_id = ? ORDER BY m.unit_id'
        );
        $stmt->execute([$userId, $organizationId]);
        return array_map(fn($row) => ['unit_id' => (int)$row['unit_id'], 'role' => $row['role']], $stmt->fetchAll());
    }

    /** Replace a user's access in one organisation. Units must already be checked to belong to it. */
    public function replace(int $organizationId, int $userId, array $memberships): void
    {
        $this->db->prepare(
            'DELETE m FROM unit_memberships m JOIN units u ON u.id = m.unit_id WHERE m.user_id = ? AND u.organization_id = ?'
        )->execute([$userId, $organizationId]);
        $stmt = $this->db->prepare('INSERT INTO unit_memberships (user_id, unit_id, role) VALUES (?, ?, ?)');
        foreach ($memberships as $m) {
            $stmt->execute([$userId, $m['unit_id'], $m['role']]);
        }
    }
}
