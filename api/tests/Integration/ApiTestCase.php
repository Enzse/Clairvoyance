<?php

namespace Tests\Integration;

use Core\App;
use Core\Database;
use Core\Environment;
use Core\Migrator;
use Core\Request;
use PDO;
use Tests\Support\RecordingMailer;
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
    /** Not configured unless a test turns it on, like an install without a mail server */
    protected static RecordingMailer $mailer;
    /** Client address for calls; null gives each call a different one so IP limits stay out of the way */
    protected ?string $clientIp = null;

    public static function setUpBeforeClass(): void
    {
        $env = Environment::getInstance();
        Migrator::ensureDatabase($env);
        self::$db ??= Database::connect($env);
        $migrator = new Migrator(self::$db);
        $migrator->dropAllTables();
        $migrator->migrate();
        self::$mailer = new RecordingMailer();
        self::$app = new App(fn() => self::$db, fn() => self::$mailer);
        $_SESSION = [];
    }

    /** @return array{0: int, 1: array} status code and decoded payload */
    protected function call(string $method, string $path, array $query = [], array $body = [], array $files = []): array
    {
        // Like a real query string: values are strings and nulls are left out
        $query = array_map('strval', array_filter($query, fn($value) => $value !== null));
        $ip = $this->clientIp ?? '10.' . random_int(0, 255) . '.' . random_int(0, 255) . '.' . random_int(1, 254);
        $response = self::$app->handle(new Request($method, $path, $query, $body, $files, $ip));
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

    /** Act as this user (a fresh sign-in), in their first organisation unless one is given. */
    protected function actingAs(?int $userId, ?int $organizationId = null): void
    {
        if ($userId === null) {
            $_SESSION = [];
            return;
        }
        $version = self::$db->prepare('SELECT session_version FROM users WHERE id = ?');
        $version->execute([$userId]);
        $_SESSION = array_filter(['user_id' => $userId, 'org_id' => $organizationId]) + ['session_version' => (int)$version->fetchColumn()];
    }

    /**
     * Sign up and, like onboarding does, create an organisation (making this person its
     * admin). Pass null to stop after sign-up. Returns the user id.
     */
    protected function registerAndLogin(string $email, ?string $organization = 'Test Org'): int
    {
        $user = $this->ok('POST', '/auth/register', [], ['email' => $email, 'password' => self::PASSWORD, 'accept_terms' => true]);
        $this->actingAs($user['id']);
        if ($organization !== null) {
            $this->ok('POST', '/organizations', [], ['name' => $organization]);
        }
        return $user['id'];
    }

    /** Invite someone to the acting admin's organisation and accept as them. */
    protected function inviteAndJoin(int $adminId, int $userId, string $email, string $role = 'member', array $access = []): void
    {
        $this->actingAs($adminId);
        $invite = $this->ok('POST', '/invitations', [], ['email' => $email, 'role' => $role, 'access' => $access]);
        $this->actingAs($userId);
        $this->ok('POST', '/invitations/accept', [], ['token' => $invite['token']]);
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

    /** The root unit of the acting user's current organisation. */
    protected function rootId(): int
    {
        foreach ($this->ok('GET', '/units') as $unit) {
            if ($unit['parent_id'] === null) {
                return $unit['id'];
            }
        }
        $this->fail('The acting user sees no root unit');
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
