<?php

namespace Tests\Integration;

/**
 * Org > North > Store A, Store B
 *     > South > Store C
 */
class AccessTest extends ApiTestCase
{
    private static int $admin;
    private static int $member;
    private static array $units;
    private static int $kpi;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $t = new self();
        self::$admin = $t->registerAndLogin('admin@example.com');
        $north = $t->createUnit($t->rootId(), 'North', 'Region');
        $south = $t->createUnit($t->rootId(), 'South', 'Region');
        self::$units = [
            'north' => $north,
            'south' => $south,
            'a' => $t->createUnit($north, 'Store A', 'Store'),
            'b' => $t->createUnit($north, 'Store B', 'Store'),
            'c' => $t->createUnit($south, 'Store C', 'Store'),
        ];
        self::$kpi = $t->createKpi(['code' => 'sales', 'name' => 'Sales'])['id'];
        self::$member = $t->ok('POST', '/auth/register', [], ['email' => 'member@example.com', 'password' => self::PASSWORD])['id'];
    }

    public function testFirstAccountIsAdminAndLaterOnesAreMembers(): void
    {
        $this->actingAs(self::$admin);
        $users = array_column($this->ok('GET', '/users'), 'role', 'email');
        $this->assertSame('admin', $users['admin@example.com']);
        $this->assertSame('member', $users['member@example.com']);
    }

    public function testRegistrationIgnoresRequestedRole(): void
    {
        $this->actingAs(null);
        $user = $this->ok('POST', '/auth/register', [], ['email' => 'sneaky@example.com', 'password' => self::PASSWORD, 'role' => 'admin']);
        $this->assertSame('member', $user['role']);
    }

    public function testRegistrationValidatesInput(): void
    {
        $result = $this->call('POST', '/auth/register', [], ['email' => 'not-an-email', 'password' => 'short']);
        $this->assertStatus(400, $result);
        $this->assertSame(['email', 'password'], array_keys($result[1]['details']));
    }

    public function testLogin(): void
    {
        $this->actingAs(null);
        $this->assertStatus(401, $this->call('POST', '/auth/login', [], ['email' => 'member@example.com', 'password' => 'wrong-password']));
        $me = $this->ok('POST', '/auth/login', [], ['email' => 'MEMBER@example.com', 'password' => self::PASSWORD]);
        $this->assertSame(self::$member, $me['id']);
        $this->assertSame(self::$member, $_SESSION['user_id']);
    }

    public function testAnonymousRequestsNeedLogin(): void
    {
        $this->actingAs(null);
        foreach ([['GET', '/units'], ['GET', '/kpis'], ['GET', '/auth/me'], ['GET', '/users']] as [$method, $path]) {
            $this->assertStatus(401, $this->call($method, $path), "{$method} {$path}");
        }
    }

    public function testMemberWithoutMembershipsSeesNothing(): void
    {
        $this->setMemberships([]);
        $this->assertSame([], $this->ok('GET', '/units'));
        $this->assertStatus(403, $this->call('GET', '/scorecard', ['unit' => (string)$this->rootId(), 'date' => '2025-06-01']));
    }

    public function testViewerSeesSubtreeOnlyAndCannotWrite(): void
    {
        $this->setMemberships([['unit_id' => self::$units['north'], 'role' => 'viewer']]);

        $visible = array_column($this->ok('GET', '/units'), 'name');
        sort($visible);
        $this->assertSame(['North', 'Store A', 'Store B'], $visible);
        $this->ok('GET', '/scorecard', ['unit' => (string)self::$units['north'], 'date' => '2025-06-01']);
        $this->assertStatus(403, $this->call('GET', '/scorecard', ['unit' => (string)self::$units['south'], 'date' => '2025-06-01']));
        $this->assertStatus(403, $this->call('GET', '/scorecard', ['unit' => (string)$this->rootId(), 'date' => '2025-06-01']));

        $result = $this->call('PUT', '/values', [], ['values' => [$this->entry(self::$units['a'], 100)]]);
        $this->assertStatus(400, $result);
        $this->assertTrue(strpos(json_encode($result[1]), 'not enter data') !== false);
    }

    public function testContributorWritesOnlyInsideTheirSubtree(): void
    {
        $this->setMemberships([['unit_id' => self::$units['a'], 'role' => 'contributor']]);

        $this->ok('PUT', '/values', [], ['values' => [$this->entry(self::$units['a'], 100)]]);
        $this->assertStatus(400, $this->call('PUT', '/values', [], ['values' => [$this->entry(self::$units['c'], 100)]]));
    }

    public function testMembersCannotChangeDefinitions(): void
    {
        $this->setMemberships([['unit_id' => $this->rootId(), 'role' => 'contributor']]);
        $this->assertStatus(403, $this->call('POST', '/kpis', [], ['code' => 'x', 'name' => 'X', 'frequency' => 'monthly', 'time_aggregation' => 'sum', 'unit_aggregation' => 'sum', 'direction' => 'none']));
        $this->assertStatus(403, $this->call('POST', '/units', [], ['parent_id' => $this->rootId(), 'name' => 'Mine']));
        $this->assertStatus(403, $this->call('PUT', '/kpis/' . self::$kpi . '/targets', [], ['targets' => []]));
        $this->assertStatus(403, $this->call('GET', '/users'));
        $this->ok('GET', '/kpis'); // but can read them
    }

    public function testDeletedUserSessionStopsWorking(): void
    {
        $this->actingAs(self::$admin);
        $temp = $this->ok('POST', '/users', [], ['email' => 'temp@example.com', 'password' => self::PASSWORD]);
        $this->actingAs($temp['id']);
        $this->ok('GET', '/auth/me');

        $this->actingAs(self::$admin);
        $this->ok('DELETE', "/users/{$temp['id']}");
        $this->actingAs($temp['id']);
        $this->assertStatus(401, $this->call('GET', '/auth/me'));
    }

    public function testRoleChangeTakesEffectImmediately(): void
    {
        $this->actingAs(self::$admin);
        $this->ok('PUT', '/users/' . self::$member, [], ['role' => 'admin']);
        $this->actingAs(self::$member);
        $this->ok('GET', '/users');

        $this->actingAs(self::$admin);
        $this->ok('PUT', '/users/' . self::$member, [], ['role' => 'member']);
        $this->actingAs(self::$member);
        $this->assertStatus(403, $this->call('GET', '/users'));
    }

    public function testLastAdminIsProtected(): void
    {
        $this->actingAs(self::$admin);
        $this->assertStatus(400, $this->call('PUT', '/users/' . self::$admin, [], ['role' => 'member']));
        $this->assertStatus(409, $this->call('DELETE', '/users/' . self::$admin));
    }

    private function setMemberships(array $memberships): void
    {
        $this->actingAs(self::$admin);
        $this->ok('PUT', '/users/' . self::$member . '/memberships', [], ['memberships' => $memberships]);
        $this->actingAs(self::$member);
    }

    private function entry(int $unitId, float $value): array
    {
        return ['kpi_id' => self::$kpi, 'unit_id' => $unitId, 'period' => '2025-06-01', 'value' => $value];
    }
}
