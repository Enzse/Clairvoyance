<?php

namespace Repositories;

use PDO;

class UserRepository
{
    private const PUBLIC_COLUMNS = 'id, email, name, role, created_at, updated_at';

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

    public function all(): array
    {
        $rows = $this->db->query('SELECT ' . self::PUBLIC_COLUMNS . ' FROM users ORDER BY email')->fetchAll();
        return array_map([$this, 'cast'], $rows);
    }

    public function count(): int
    {
        return (int)$this->db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    public function countAdmins(): int
    {
        return (int)$this->db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    }

    public function create(string $email, string $password, string $role, ?string $name): int
    {
        $stmt = $this->db->prepare('INSERT INTO users (email, name, password, role) VALUES (?, ?, ?, ?)');
        $stmt->execute([$email, $name, password_hash($password, PASSWORD_DEFAULT), $role]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $fields): void
    {
        $allowed = array_intersect_key($fields, array_flip(['name', 'role']));
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
