<?php

namespace Core;

/**
 * Configuration from api/.env, with defaults. Values can be overridden with set()
 * before the database connection is made (tests do this).
 */
class Environment
{
    private static ?Environment $instance = null;

    private array $values = [
        'DB_HOST' => 'localhost',
        'DB_NAME' => 'clairvoyance_v3',
        'DB_USER' => 'root',
        'DB_PASS' => '',
        'ALLOWED_ORIGINS' => 'http://localhost:5173',
    ];

    private function __construct()
    {
        $file = __DIR__ . '/../.env';
        if (!file_exists($file)) {
            return;
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            $this->values[$key] = trim($value, "\"'");
        }
    }

    public static function getInstance(): Environment
    {
        return self::$instance ??= new self();
    }

    public function get(string $key, ?string $default = null): ?string
    {
        return $this->values[$key] ?? $default;
    }

    public function set(string $key, string $value): void
    {
        $this->values[$key] = $value;
    }
}
