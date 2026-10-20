<?php

namespace Tests\Integration;

/**
 * Roles and unit access inside one organisation.
 * Test Org > North > Store A, Store B
 *          > South > Store C
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
        self::$member = $t->registerAndLogin('member@example.com', null);
        $t->inviteAndJoin(self::$admin, self::$member, 'member@example.com');
    }

    public function testSignUpAloneGivesNoOrganisation(): void
    {
        $this->actingAs(null);
        $me = $this->ok('POST', '/auth/register', [], ['email' => 'loner@example.com', 'password' => self::PASSWORD, 'role' => 'admin']);
        $this->assertNull($me['organization']);
        $this->assertSame([], $me['organizations']);
        $result = $this->call('GET', '/units');
        $this->assertStatus(403, $result);
        $this->assertSame('Create or join an organisation first', $result[1]['error']);
    }

    public function testCreatorIsAdminAndInviteesAreMembers(): void
    {
        $this->actingAs(self::$admin);
        $roles = array_column($this->ok('GET', '/members'), 'role', 'email');
        $this->assertSame(['admin@example.com' => 'admin', 'member@example.com' => 'member'], $roles);
    }

    public function testRegistrationValidatesInput(): void
    {
        $this->actingAs(null);
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
        $this->assertSame('Test Org', $me['organization']['name']);
        $this->assertSame('member', $me['organization']['role']);
    }

    public function testAnonymousRequestsNeedLogin(): void
    {
        $this->actingAs(null);
        foreach ([['GET', '/units'], ['GET', '/kpis'], ['GET', '/auth/me'], ['GET', '/members'], ['POST', '/organizations']] as [$method, $path]) {
            $this->assertStatus(401, $this->call($method, $path), "{$method} {$path}");
        }
    }

    public function testMemberWithoutUnitAccessSeesNothing(): void
    {
        $this->setMemberships([]);
        $this->assertSame([], $this->ok('GET', '/units'));
        $this->assertStatus(403, $this->call('GET', '/scorecard', ['unit' => (string)self::$units['north'], 'date' => '2025-06-01']));
    }

    public function testViewerSeesSubtreeOnlyAndCannotWrite(): void
    {
        $this->setMemberships([['unit_id' => self::$units['north'], 'role' => 'viewer']]);

        $visible = array_column($this->ok('GET', '/units'), 'name');
        sort($visible);
        $this->assertSame(['North', 'Store A', 'Store B'], $visible);
        $this->ok('GET', '/scorecard', ['unit' => (string)self::$units['north'], 'date' => '2025-06-01']);
        $this->assertStatus(403, $this->call('GET', '/scorecard', ['unit' => (string)self::$units['south'], 'date' => '2025-06-01']));

        $result = $this->call('PUT', '/values', [], ['values' => [$this->entry(self::$units['a'], 100)]]);
        $this->assertStatus(400, $result);
        $this->assertTrue(strpos(json_encode($result[1]), 'not enter them') !== false);
    }

    public function testContributorWritesOnlyInsideTheirSubtree(): void
    {
        $this->setMemberships([['unit_id' => self::$units['a'], 'role' => 'contributor']]);

        $this->ok('PUT', '/values', [], ['values' => [$this->entry(self::$units['a'], 100)]]);
        $this->assertStatus(400, $this->call('PUT', '/values', [], ['values' => [$this->entry(self::$units['c'], 100)]]));
    }

    public function testMembersCannotChangeTheOrganisation(): void
    {
        $this->setMemberships([['unit_id' => self::$units['north'], 'role' => 'contributor']]);
        $this->assertStatus(403, $this->call('POST', '/kpis', [], ['code' => 'x', 'name' => 'X', 'frequency' => 'monthly', 'time_aggregation' => 'sum', 'unit_aggregation' => 'sum', 'direction' => 'none']));
        $this->assertStatus(403, $this->call('POST', '/units', [], ['parent_id' => self::$units['north'], 'name' => 'Mine']));
        $this->assertStatus(403, $this->call('PUT', '/kpis/' . self::$kpi . '/targets', [], ['targets' => []]));
        // Targets cover units outside their access, so the raw list stays with admins
        $this->assertStatus(403, $this->call('GET', '/kpis/' . self::$kpi . '/targets'));
        $this->assertStatus(403, $this->call('GET', '/members'));
        $this->assertStatus(403, $this->call('POST', '/invitations', [], ['email' => 'friend@example.com']));
        $this->assertStatus(403, $this->call('PUT', '/organization', [], ['name' => 'Mine now']));
        $this->ok('GET', '/kpis'); // but can read definitions
    }

    public function testRoleChangeTakesEffectImmediately(): void
    {
        $this->actingAs(self::$admin);
        $this->ok('PUT', '/members/' . self::$member, [], ['role' => 'admin']);
        $this->actingAs(self::$member);
        $this->ok('GET', '/members');

        $this->actingAs(self::$admin);
        $this->ok('PUT', '/members/' . self::$member, [], ['role' => 'member']);
        $this->actingAs(self::$member);
        $this->assertStatus(403, $this->call('GET', '/members'));
    }

    public function testRemovedMemberLosesAccessAtOnce(): void
    {
        $leaver = $this->registerAndLogin('leaver@example.com', null);
        $this->inviteAndJoin(self::$admin, $leaver, 'leaver@example.com');
        $this->actingAs($leaver);
        $this->ok('GET', '/kpis');

        $this->actingAs(self::$admin);
        $this->ok('DELETE', "/members/{$leaver}");
        $this->actingAs($leaver);
        $this->assertNull($this->ok('GET', '/auth/me')['organization']);
        $this->assertStatus(403, $this->call('GET', '/kpis'));
    }

    public function testDeletedAccountSessionStopsWorking(): void
    {
        $gone = $this->registerAndLogin('gone@example.com', null);
        self::$db->exec("DELETE FROM users WHERE id = {$gone}");
        $this->actingAs($gone);
        $this->assertStatus(401, $this->call('GET', '/auth/me'));
    }

    public function testLastAdminIsProtected(): void
    {
        $this->actingAs(self::$admin);
        $this->assertStatus(409, $this->call('PUT', '/members/' . self::$admin, [], ['role' => 'member']));
        $this->assertStatus(409, $this->call('DELETE', '/members/' . self::$admin));
    }

    public function testPeopleManageTheirOwnAccount(): void
    {
        $this->actingAs(self::$member);
        $this->assertSame('Mia', $this->ok('PUT', '/auth/me', [], ['name' => 'Mia'])['name']);

        $wrong = $this->call('PUT', '/auth/me', [], ['current_password' => 'nope-nope', 'new_password' => 'brand-new-pass']);
        $this->assertStatus(400, $wrong);
        $this->assertSame('is not right', $wrong[1]['details']['current_password']);

        $this->ok('PUT', '/auth/me', [], ['current_password' => self::PASSWORD, 'new_password' => 'brand-new-pass']);
        $this->actingAs(null);
        $this->ok('POST', '/auth/login', [], ['email' => 'member@example.com', 'password' => 'brand-new-pass']);
        $this->actingAs(self::$member);
        $this->ok('PUT', '/auth/me', [], ['current_password' => 'brand-new-pass', 'new_password' => self::PASSWORD]);
    }

    private function setMemberships(array $memberships): void
    {
        $this->actingAs(self::$admin);
        $this->ok('PUT', '/members/' . self::$member . '/memberships', [], ['memberships' => $memberships]);
        $this->actingAs(self::$member);
    }

    private function entry(int $unitId, float $value): array
    {
        return ['kpi_id' => self::$kpi, 'unit_id' => $unitId, 'period' => '2025-06-01', 'value' => $value];
    }
}
