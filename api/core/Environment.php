<?php

namespace Core;

/**
 * Configuration from api/.env, with defaults. Real environment variables win over the file
 * (CI sets them). Values can be overridden with set() before the database connection is
 * made (tests do this).
 */
class Environment
{
    private static ?Environment $instance = null;

    private array $values = [
        'DB_HOST' => 'localhost',
        'DB_NAME' => 'clairvoyance_v3',
        'DB_USER' => 'root',
        'DB_PASS' => '',
        // Where people open the app: used in emailed links, and https here marks the cookie Secure
        'APP_URL' => 'http://localhost:5173',
        // Other origins allowed to call the API with cookies; empty when served from APP_URL itself
        'ALLOWED_ORIGINS' => 'http://localhost:5173',
        // SMTP. Without MAIL_HOST nothing is emailed: invitations are links to copy, and
        // email addresses can't be verified, so verification isn't required.
        'MAIL_HOST' => '',
        'MAIL_PORT' => '587',
        'MAIL_ENCRYPTION' => 'tls',   // tls = STARTTLS (587), ssl = implicit TLS (465), none (local catchers)
        'MAIL_USER' => '',
        'MAIL_PASS' => '',
        'MAIL_FROM' => '',
        'MAIL_FROM_NAME' => 'Clairvoyance',
    ];
    /** Keys set() in code, which beat real environment variables too */
    private array $overrides = [];

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
        if (!array_key_exists($key, $this->overrides)) {
            $real = getenv($key);
            if ($real !== false) {
                return $real;
            }
        }
        return $this->values[$key] ?? $default;
    }

    public function set(string $key, string $value): void
    {
        $this->values[$key] = $value;
        $this->overrides[$key] = true;
    }
}
