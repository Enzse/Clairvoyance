<?php

namespace Repositories;

use PDO;

/** One-time links sent by email (verify an address, reset a password). Only hashes are stored. */
class UserTokenRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** Replaces any earlier link for the same purpose, so only the newest one works. */
    public function replace(int $userId, string $purpose, string $tokenHash, int $expiresAt): void
    {
        $this->db->prepare('DELETE FROM user_tokens WHERE user_id = ? AND purpose = ?')->execute([$userId, $purpose]);
        $this->db->prepare('INSERT INTO user_tokens (user_id, purpose, token_hash, expires_at) VALUES (?, ?, ?, ?)')
            ->execute([$userId, $purpose, $tokenHash, date('Y-m-d H:i:s', $expiresAt)]);
    }

    /** The token if it exists for this purpose, hasn't been used and hasn't expired. */
    public function findUsable(string $purpose, string $tokenHash): ?array
    {
        $stmt = $this->db->prepare('SELECT id, user_id, expires_at, used_at FROM user_tokens WHERE purpose = ? AND token_hash = ?');
        $stmt->execute([$purpose, $tokenHash]);
        $row = $stmt->fetch();
        if (!$row || $row['used_at'] !== null || strtotime($row['expires_at']) < time()) {
            return null;
        }
        return ['id' => (int)$row['id'], 'user_id' => (int)$row['user_id']];
    }

    public function markUsed(int $id): void
    {
        $this->db->prepare('UPDATE user_tokens SET used_at = ? WHERE id = ?')->execute([date('Y-m-d H:i:s'), $id]);
    }

    public function deleteExpired(): int
    {
        $stmt = $this->db->prepare('DELETE FROM user_tokens WHERE expires_at < ? OR used_at IS NOT NULL');
        $stmt->execute([date('Y-m-d H:i:s', time() - 86400)]);
        return $stmt->rowCount();
    }
}
