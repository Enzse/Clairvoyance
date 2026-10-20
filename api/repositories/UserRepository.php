<?php

namespace Repositories;

use PDO;

/** Accounts. Roles are per organisation (see OrganizationRepository). */
class UserRepository
{
    private const PUBLIC_COLUMNS = 'id, email, name, email_verified_at, created_at, updated_at';

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT ' . self::PUBLIC_COLUMNS . ' FROM users WHERE id = ?');
        $stmt->execute([$id]);
        return $this->cast($stmt->fetch() ?: null);
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT ' . self::PUBLIC_COLUMNS . ' FROM users WHERE email = ?');
        $stmt->execute([$email]);
        return $this->cast($stmt->fetch() ?: null);
    }

    /** The user if the password matches, otherwise null. */
    public function verifyCredentials(string $email, string $password): ?array
    {
        $stmt = $this->db->prepare('SELECT id, password FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($password, $row['password'])) {
            return null;
        }
        return $this->find((int)$row['id']);
    }

    public function passwordMatches(int $id, string $password): bool
    {
        $stmt = $this->db->prepare('SELECT password FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $hash = $stmt->fetchColumn();
        return $hash && password_verify($password, $hash);
    }

    /** $termsVersion: the Terms the person agreed to while signing up */
    public function create(string $email, string $password, ?string $name, string $termsVersion): int
    {
        $stmt = $this->db->prepare('INSERT INTO users (email, name, password, terms_accepted_at, terms_version) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$email, $name, password_hash($password, PASSWORD_DEFAULT), date('Y-m-d H:i:s'), $termsVersion]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $fields): void
    {
        $allowed = array_intersect_key($fields, array_flip(['name']));
        if (isset($fields['password'])) {
            $allowed['password'] = password_hash($fields['password'], PASSWORD_DEFAULT);
        }
        if (!$allowed) {
            return;
        }
        $sets = implode(', ', array_map(fn($column) => "{$column} = ?", array_keys($allowed)));
        $stmt = $this->db->prepare("UPDATE users SET {$sets} WHERE id = ?");
        $stmt->execute([...array_values($allowed), $id]);
    }

    public function markVerified(int $id): void
    {
        $this->db->prepare('UPDATE users SET email_verified_at = ? WHERE id = ? AND email_verified_at IS NULL')
            ->execute([date('Y-m-d H:i:s'), $id]);
    }

    /** Sessions remember this number; changing it signs out every session that has the old one. */
    public function sessionVersion(int $id): int
    {
        $stmt = $this->db->prepare('SELECT session_version FROM users WHERE id = ?');
        $stmt->execute([$id]);
        return (int)$stmt->fetchColumn();
    }

    public function bumpSessionVersion(int $id): int
    {
        $this->db->prepare('UPDATE users SET session_version = session_version + 1 WHERE id = ?')->execute([$id]);
        return $this->sessionVersion($id);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    }

    private function cast(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }
        $row['id'] = (int)$row['id'];
        return $row;
    }
}
