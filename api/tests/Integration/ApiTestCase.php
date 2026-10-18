<?php

namespace Tests\Integration;

use Core\App;
use Core\Database;
use Core\Environment;
use Core\Migrator;
use Core\Request;
use PDO;
use Tests\TestCase;

/**
 * Drives the real API in-process (routes, controllers, services, MariaDB) against the
 * test database. Each test class starts from a freshly migrated, empty database.
 */
abstract class ApiTestCase extends TestCase
{
    protected const PASSWORD = 'test-password';
    protected static ?PDO $db = null;
    private static ?App $app = null;

    public static function setUpBeforeClass(): void
    {
        $env = Environment::getInstance();
        Migrator::ensureDatabase($env);
        self::$db ??= Database::connect($env);
        $migrator = new Migrator(self::$db);
        $migrator->dropAllTables();
        $migrator->migrate();
        self::$app = new App(fn() => self::$db);
        $_SESSION = [];
    }

    /** @return array{0: int, 1: array} status code and decoded payload */
    protected function call(string $method, string $path, array $query = [], array $body = [], array $files = []): array
    {
        $response = self::$app->handle(new Request($method, $path, $query, $body, $files));
        // Round-trip through JSON so tests see exactly what a client would
        return [$response->status, json_decode(json_encode($response->payload), true)];
    }

    /** Call and assert success; returns the data. */
    protected function ok(string $method, string $path, array $query = [], array $body = [], array $files = [])
    {
        [$status, $payload] = $this->call($method, $path, $query, $body, $files);
        if ($status >= 300) {
            $this->fail("{$method} {$path} returned {$status}: " . json_encode($payload));
        }
        return $payload['data'];
    }

    protected function assertStatus(int $expected, array $result, string $message = ''): void
    {
        $this->assertSame($expected, $result[0], ($message ? "{$message} " : '') . json_encode($result[1]));
    }

    protected function actingAs(?int $userId): void
    {
        $_SESSION = $userId === null ? [] : ['user_id' => $userId];
    }

    /** Register an account (the first one becomes admin) and log in as it. */
    protected function registerAndLogin(string $email): int
    {
        $user = $this->ok('POST', '/auth/register', [], ['email' => $email, 'password' => self::PASSWORD]);
        $this->actingAs($user['id']);
        return $user['id'];
    }

    protected function upload(string $path): array
    {
        return ['file' => ['name' => basename($path), 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK]];
    }

    protected function csvFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, $contents);
        return $path;
    }

    protected function rootId(): int
    {
        return (int)self::$db->query('SELECT id FROM units WHERE parent_id IS NULL')->fetchColumn();
    }

    protected function createKpi(array $fields): array
    {
        return $this->ok('POST', '/kpis', [], $fields + [
            'frequency' => 'monthly',
            'time_aggregation' => 'sum',
            'unit_aggregation' => 'sum',
            'direction' => 'higher_is_better',
        ]);
    }

    protected function createUnit(int $parentId, string $name, ?string $kind = null): int
    {
        return $this->ok('POST', '/units', [], ['parent_id' => $parentId, 'name' => $name, 'kind' => $kind])['id'];
    }
}
