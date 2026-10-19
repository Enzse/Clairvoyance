<?php

namespace Tests\Integration;

/** Throttling, signing out other sessions, and the health check. */
class SecurityTest extends ApiTestCase
{
    private static int $user;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$user = (new self())->registerAndLogin('owner@example.com');
    }

    public function test1FiveWrongPasswordsLockTheEmail(): void
    {
        $this->actingAs(null);
        for ($i = 0; $i < 5; $i++) {
            $this->assertStatus(401, $this->call('POST', '/auth/login', [], ['email' => 'owner@example.com', 'password' => 'wrong']));
        }
        // Even the right password is refused while locked, and the message says for how long
        $locked = $this->call('POST', '/auth/login', [], ['email' => 'OWNER@example.com', 'password' => self::PASSWORD]);
        $this->assertStatus(429, $locked);
        $this->assertTrue(strpos($locked[1]['error'], 'Try again in 15 minutes') !== false, $locked[1]['error']);
        // Other accounts are unaffected
        $this->assertStatus(401, $this->call('POST', '/auth/login', [], ['email' => 'someone@example.com', 'password' => 'wrong']));
    }

    public function test2TheLockEndsWithItsWindow(): void
    {
        self::$db->exec("UPDATE rate_limits SET window_start = window_start - 901");
        $this->ok('POST', '/auth/login', [], ['email' => 'owner@example.com', 'password' => self::PASSWORD]);
    }

    public function test3ASuccessfulLoginResetsTheCount(): void
    {
        $this->actingAs(null);
        for ($i = 0; $i < 4; $i++) {
            $this->call('POST', '/auth/login', [], ['email' => 'owner@example.com', 'password' => 'wrong']);
        }
        $this->ok('POST', '/auth/login', [], ['email' => 'owner@example.com', 'password' => self::PASSWORD]);
        $this->assertStatus(401, $this->call('POST', '/auth/login', [], ['email' => 'owner@example.com', 'password' => 'wrong']));
        $this->assertStatus(401, $this->call('POST', '/auth/login', [], ['email' => 'owner@example.com', 'password' => 'wrong']));
    }

    public function test4OneAddressCanOnlyRegisterSoManyAccounts(): void
    {
        $this->clientIp = '203.0.113.7';
        for ($i = 1; $i <= 10; $i++) {
            $this->ok('POST', '/auth/register', [], ['email' => "bulk{$i}@example.com", 'password' => self::PASSWORD]);
        }
        $this->assertStatus(429, $this->call('POST', '/auth/register', [], ['email' => 'bulk11@example.com', 'password' => self::PASSWORD]));
        $this->clientIp = '203.0.113.8';
        $this->ok('POST', '/auth/register', [], ['email' => 'bulk11@example.com', 'password' => self::PASSWORD]);
    }

    public function test5ChangingYourPasswordSignsOutOtherSessions(): void
    {
        $this->actingAs(self::$user);
        $otherDevice = $_SESSION;
        $this->ok('PUT', '/auth/me', [], ['current_password' => self::PASSWORD, 'new_password' => 'a-new-password']);
        $this->ok('GET', '/auth/me'); // this session stays signed in

        $_SESSION = $otherDevice;
        $this->assertStatus(401, $this->call('GET', '/auth/me'));
        $this->assertSame([], $_SESSION);
    }

    public function test6HealthCheck(): void
    {
        $this->actingAs(null);
        $this->assertSame(true, $this->ok('GET', '/health')['ok']);
    }
}
