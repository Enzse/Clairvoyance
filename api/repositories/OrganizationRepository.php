<?php

namespace Repositories;

use PDO;

class OrganizationRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(string $name): int
    {
        $this->db->prepare('INSERT INTO organizations (name) VALUES (?)')->execute([$name]);
        return (int)$this->db->lastInsertId();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT id, name, setup_dismissed_at, created_at FROM organizations WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? ['id' => (int)$row['id']] + $row : null;
    }

    public function rename(int $id, string $name): void
    {
        $this->db->prepare('UPDATE organizations SET name = ? WHERE id = ?')->execute([$name, $id]);
    }

    public function dismissSetup(int $id): void
    {
        $this->db->prepare('UPDATE organizations SET setup_dismissed_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$id]);
    }

    /** The user's role in the organisation, or null if they don't belong to it. */
    public function roleOf(int $organizationId, int $userId): ?string
    {
        $stmt = $this->db->prepare('SELECT role FROM organization_users WHERE organization_id = ? AND user_id = ?');
        $stmt->execute([$organizationId, $userId]);
        return $stmt->fetchColumn() ?: null;
    }

    /** @return array<array{id: int, name: string, role: string}> organisations the user belongs to */
    public function forUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT o.id, o.name, ou.role FROM organization_users ou
             JOIN organizations o ON o.id = ou.organization_id
             WHERE ou.user_id = ? ORDER BY o.name'
        );
        $stmt->execute([$userId]);
        return array_map(fn($r) => ['id' => (int)$r['id'], 'name' => $r['name'], 'role' => $r['role']], $stmt->fetchAll());
    }

    /** @return array<array> users in the organisation with their role */
    public function members(int $organizationId): array
    {
        $stmt = $this->db->prepare(
            'SELECT u.id, u.email, u.name, ou.role, ou.created_at AS joined_at FROM organization_users ou
             JOIN users u ON u.id = ou.user_id
             WHERE ou.organization_id = ? ORDER BY u.email'
        );
        $stmt->execute([$organizationId]);
        return array_map(fn($r) => ['id' => (int)$r['id']] + $r, $stmt->fetchAll());
    }

    public function addMember(int $organizationId, int $userId, string $role): void
    {
        $this->db->prepare(
            'INSERT INTO organization_users (organization_id, user_id, role) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE role = VALUES(role)'
        )->execute([$organizationId, $userId, $role]);
    }

    public function setRole(int $organizationId, int $userId, string $role): void
    {
        $this->db->prepare('UPDATE organization_users SET role = ? WHERE organization_id = ? AND user_id = ?')
            ->execute([$role, $organizationId, $userId]);
    }

    /** Removes the user from the organisation, including their unit access there. */
    public function removeMember(int $organizationId, int $userId): void
    {
        $this->db->prepare(
            'DELETE m FROM unit_memberships m JOIN units u ON u.id = m.unit_id
             WHERE m.user_id = ? AND u.organization_id = ?'
        )->execute([$userId, $organizationId]);
        $this->db->prepare('DELETE FROM organization_users WHERE organization_id = ? AND user_id = ?')
            ->execute([$organizationId, $userId]);
    }

    public function countAdmins(int $organizationId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM organization_users WHERE organization_id = ? AND role = 'admin'");
        $stmt->execute([$organizationId]);
        return (int)$stmt->fetchColumn();
    }

    /** Facts for the getting-started checklist. */
    public function setupFacts(int $organizationId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                (SELECT COUNT(*) FROM units WHERE organization_id = ? AND parent_id IS NOT NULL) AS units,
                (SELECT COUNT(*) FROM kpis WHERE organization_id = ?) AS kpis,
                (SELECT COUNT(*) FROM kpi_targets t JOIN kpis k ON k.id = t.kpi_id WHERE k.organization_id = ?) AS targets,
                (SELECT COUNT(*) FROM kpi_values v JOIN kpis k ON k.id = v.kpi_id WHERE k.organization_id = ?) AS kpi_values,
                (SELECT COUNT(*) FROM organization_users WHERE organization_id = ?) AS members,
                (SELECT COUNT(*) FROM invitations WHERE organization_id = ? AND accepted_at IS NULL) AS invitations'
        );
        // Native prepares can't reuse a placeholder, so the id is passed once per subquery
        $stmt->execute(array_fill(0, 6, $organizationId));
        return array_map('intval', $stmt->fetch());
    }
}
