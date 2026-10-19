<?php

namespace Repositories;

use PDO;

class InvitationRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(int $organizationId, string $email, string $role, array $access, string $tokenHash, int $invitedBy, string $expiresAt): int
    {
        $this->db->prepare(
            'INSERT INTO invitations (organization_id, email, role, access, token_hash, invited_by, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$organizationId, $email, $role, json_encode($access), $tokenHash, $invitedBy, $expiresAt]);
        return (int)$this->db->lastInsertId();
    }

    public function findByTokenHash(string $tokenHash): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT i.*, o.name AS organization_name, u.email AS invited_by_email, u.name AS invited_by_name
             FROM invitations i
             JOIN organizations o ON o.id = i.organization_id
             LEFT JOIN users u ON u.id = i.invited_by
             WHERE i.token_hash = ?'
        );
        $stmt->execute([$tokenHash]);
        $row = $stmt->fetch();
        return $row ? $this->cast($row) : null;
    }

    /** Invitations not yet accepted, newest first. */
    public function pending(int $organizationId): array
    {
        $stmt = $this->db->prepare(
            'SELECT i.*, NULL AS organization_name, u.email AS invited_by_email, u.name AS invited_by_name
             FROM invitations i LEFT JOIN users u ON u.id = i.invited_by
             WHERE i.organization_id = ? AND i.accepted_at IS NULL ORDER BY i.id DESC'
        );
        $stmt->execute([$organizationId]);
        return array_map([$this, 'cast'], $stmt->fetchAll());
    }

    public function findPendingByEmail(int $organizationId, string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT id FROM invitations WHERE organization_id = ? AND email = ? AND accepted_at IS NULL');
        $stmt->execute([$organizationId, $email]);
        return $stmt->fetch() ?: null;
    }

    public function markAccepted(int $id): void
    {
        $this->db->prepare('UPDATE invitations SET accepted_at = NOW() WHERE id = ?')->execute([$id]);
    }

    public function delete(int $organizationId, int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM invitations WHERE id = ? AND organization_id = ? AND accepted_at IS NULL');
        $stmt->execute([$id, $organizationId]);
        return $stmt->rowCount() > 0;
    }

    private function cast(array $row): array
    {
        return [
            'id' => (int)$row['id'],
            'organization_id' => (int)$row['organization_id'],
            'organization_name' => $row['organization_name'],
            'email' => $row['email'],
            'role' => $row['role'],
            'access' => json_decode($row['access'] ?? '[]', true) ?: [],
            'invited_by' => $row['invited_by_name'] ?: $row['invited_by_email'],
            'expires_at' => $row['expires_at'],
            'accepted_at' => $row['accepted_at'],
            'created_at' => $row['created_at'],
        ];
    }
}
