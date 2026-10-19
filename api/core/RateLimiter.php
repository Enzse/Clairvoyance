<?php

namespace Core;

use PDO;

/**
 * Counts attempts per bucket (e.g. "login:<email hash>") in fixed windows, in the database so
 * it works on shared hosting without Redis. A bucket is blocked once it reaches its maximum
 * until the window ends.
 */
class RateLimiter
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** Throws 429 if the bucket has already reached $max attempts in the current window. */
    public function check(string $bucket, int $max, int $seconds): void
    {
        $stmt = $this->db->prepare('SELECT window_start, hits FROM rate_limits WHERE bucket = ?');
        $stmt->execute([$bucket]);
        $row = $stmt->fetch();
        $now = time();
        if ($row && (int)$row['window_start'] > $now - $seconds && (int)$row['hits'] >= $max) {
            $minutes = (int)ceil(((int)$row['window_start'] + $seconds - $now) / 60);
            throw new HttpException(429, 'Too many attempts. Try again in ' . ($minutes === 1 ? '1 minute' : "{$minutes} minutes"));
        }
    }

    /** Counts one attempt, starting a new window if the last one has ended. */
    public function hit(string $bucket, int $seconds): void
    {
        $now = time();
        $expired = $now - $seconds;
        // hits is assigned first, so it still compares against the old window_start
        $this->db->prepare(
            'INSERT INTO rate_limits (bucket, window_start, hits) VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE
               hits = IF(window_start <= ?, 1, hits + 1),
               window_start = IF(window_start <= ?, ?, window_start)'
        )->execute([$bucket, $now, $expired, $expired, $now]);
    }

    /** Check, then count: for actions limited whether or not they succeed. */
    public function attempt(string $bucket, int $max, int $seconds): void
    {
        $this->check($bucket, $max, $seconds);
        $this->hit($bucket, $seconds);
    }

    public function clear(string $bucket): void
    {
        $this->db->prepare('DELETE FROM rate_limits WHERE bucket = ?')->execute([$bucket]);
    }

    /** Bucket name for an email address, without storing the address itself. */
    public static function emailBucket(string $action, string $email): string
    {
        return $action . ':' . hash('sha256', mb_strtolower(trim($email)));
    }
}
