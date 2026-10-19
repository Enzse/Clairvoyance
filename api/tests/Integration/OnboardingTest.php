<?php

namespace Tests\Integration;

/** Sign-up, creating an organisation, invitations, the setup checklist and KPI scope. */
class OnboardingTest extends ApiTestCase
{
    private static int $admin;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $t = new self();
        self::$admin = $t->registerAndLogin('founder@example.com', null);
    }

    public function test1CreatingAnOrganisationMakesYouItsAdmin(): void
    {
        $this->actingAs(self::$admin);
        $me = $this->ok('POST', '/organizations', [], ['name' => 'Bean There Coffee']);

        $this->assertSame('Bean There Coffee', $me['organization']['name']);
        $this->assertSame('admin', $me['organization']['role']);
        $units = $this->ok('GET', '/units');
        $this->assertSame(1, count($units));
        $this->assertSame('Bean There Coffee', $units[0]['name']);
        $this->assertTrue($units[0]['is_leaf']); // a one-location business reports at the top
    }

    public function test2ChecklistTracksSetup(): void
    {
        $this->actingAs(self::$admin);
        $setup = $this->ok('GET', '/organization/setup');
        $this->assertSame(['structure' => false, 'kpis' => false, 'targets' => false, 'data' => false, 'people' => false], $setup['steps']);

        $downtown = $this->createUnit($this->rootId(), 'Downtown', 'Branch');
        $kpi = $this->createKpi(['code' => 'net_sales', 'name' => 'Net sales']);
        $this->ok('PUT', "/kpis/{$kpi['id']}/targets", [], ['targets' => [['unit_id' => null, 'effective_from' => '2025-01-01', 'value' => 1000]]]);
        $this->ok('PUT', '/values', [], ['values' => [['kpi_id' => $kpi['id'], 'unit_id' => $downtown, 'period' => '2025-06-01', 'value' => 900]]]);
        $this->ok('POST', '/invitations', [], ['email' => 'barista@example.com']);

        $setup = $this->ok('GET', '/organization/setup');
        $this->assertSame(['structure' => true, 'kpis' => true, 'targets' => true, 'data' => true, 'people' => true], $setup['steps']);
        $this->assertSame(false, $setup['dismissed']);
        $this->assertTrue($this->ok('POST', '/organization/setup/dismiss')['dismissed']);
    }

    public function test3RenamingKeepsOrganisationAndRootInStep(): void
    {
        $this->actingAs(self::$admin);
        $this->ok('PUT', '/organization', [], ['name' => 'Bean There Café']);
        $this->assertSame('Bean There Café', $this->unitName($this->rootId()));

        $this->ok('PUT', '/units/' . $this->rootId(), [], ['name' => 'Bean There']);
        $this->assertSame('Bean There', $this->ok('GET', '/auth/me')['organization']['name']);
    }

    public function test4InvitationLifecycle(): void
    {
        $this->actingAs(self::$admin);
        $downtown = $this->unitId('Downtown');
        $invite = $this->ok('POST', '/invitations', [], [
            'email' => 'Manager@Example.com', 'role' => 'member', 'access' => [['unit_id' => $downtown, 'role' => 'contributor']],
        ]);
        $this->assertSame('manager@example.com', $invite['email']);
        $this->assertSame(48, strlen($invite['token']));
        $this->assertSame(64, strlen((string)self::$db->query("SELECT token_hash FROM invitations WHERE id = {$invite['id']}")->fetchColumn()));
        $this->assertSame(0, (int)self::$db->query("SELECT COUNT(*) FROM invitations WHERE token_hash = '{$invite['token']}'")->fetchColumn());

        // Anyone with the link can see what it's for, without signing in
        $this->actingAs(null);
        $lookup = $this->ok('GET', '/invitations/lookup', ['token' => $invite['token']]);
        $this->assertSame(['organization_name' => 'Bean There', 'email' => 'manager@example.com', 'role' => 'member', 'invited_by' => 'founder@example.com', 'has_account' => false], $lookup);
        $this->assertStatus(401, $this->call('POST', '/invitations/accept', [], ['token' => $invite['token']]));

        // Signed in as someone else: refused
        $other = $this->registerAndLogin('other@example.com', null);
        $wrong = $this->call('POST', '/invitations/accept', [], ['token' => $invite['token']]);
        $this->assertStatus(403, $wrong);
        $this->assertSame("This invitation is for manager@example.com, but you're signed in as other@example.com", $wrong[1]['error']);

        // The right person signs up and accepts: in the organisation, with the access
        $manager = $this->registerAndLogin('manager@example.com', null);
        $me = $this->ok('POST', '/invitations/accept', [], ['token' => $invite['token']]);
        $this->assertSame('Bean There', $me['organization']['name']);
        $this->assertSame('member', $me['organization']['role']);
        $this->assertSame([['unit_id' => $downtown, 'role' => 'contributor']], $me['memberships']);

        $this->assertStatus(410, $this->call('POST', '/invitations/accept', [], ['token' => $invite['token']]));
        $this->assertStatus(404, $this->call('GET', '/invitations/lookup', ['token' => 'not-a-real-token']));
    }

    public function test5InvitationEdgeCases(): void
    {
        $this->actingAs(self::$admin);
        $this->assertStatus(409, $this->call('POST', '/invitations', [], ['email' => 'manager@example.com']));

        $first = $this->ok('POST', '/invitations', [], ['email' => 'late@example.com']);
        $second = $this->ok('POST', '/invitations', [], ['email' => 'late@example.com']);
        $this->assertStatus(404, $this->call('GET', '/invitations/lookup', ['token' => $first['token']]));
        $this->assertSame(1, count(array_filter($this->ok('GET', '/invitations'), fn($i) => $i['email'] === 'late@example.com')));

        self::$db->exec("UPDATE invitations SET expires_at = '2000-01-01 00:00:00' WHERE id = {$second['id']}");
        $this->assertStatus(410, $this->call('GET', '/invitations/lookup', ['token' => $second['token']]));

        $revoked = $this->ok('POST', '/invitations', [], ['email' => 'nope@example.com']);
        $this->ok('DELETE', "/invitations/{$revoked['id']}");
        $this->assertStatus(404, $this->call('GET', '/invitations/lookup', ['token' => $revoked['token']]));
    }

    public function test6AcceptingNeverDemotesAnAdmin(): void
    {
        $this->actingAs(self::$admin);
        $invite = $this->ok('POST', '/invitations', [], ['email' => 'cofounder@example.com', 'role' => 'admin']);
        $cofounder = $this->registerAndLogin('cofounder@example.com', null);
        $this->ok('POST', '/invitations/accept', [], ['token' => $invite['token']]);

        $this->actingAs(self::$admin);
        $this->ok('DELETE', "/members/{$cofounder}");
        $memberInvite = $this->ok('POST', '/invitations', [], ['email' => 'cofounder@example.com', 'role' => 'member']);
        self::$db->exec("INSERT INTO organization_users (organization_id, user_id, role) SELECT organization_id, {$cofounder}, 'admin' FROM invitations WHERE id = {$memberInvite['id']}");
        $this->actingAs($cofounder);
        $this->assertSame('admin', $this->ok('POST', '/invitations/accept', [], ['token' => $memberInvite['token']])['organization']['role']);
    }

    public function test6bTheOrganisationChoosesItsWordForUnits(): void
    {
        $this->actingAs(self::$admin);
        $before = $this->ok('GET', '/auth/me')['organization'];
        $this->assertSame('team', $before['unit_label'], 'the default');
        $me = $this->ok('PUT', '/organization', [], ['unit_label' => 'Branch']);
        $this->assertSame('branch', $me['organization']['unit_label']);
        $this->assertSame($before['name'], $me['organization']['name'], 'the name is left alone');
        $this->assertStatus(400, $this->call('PUT', '/organization', [], ['unit_label' => '<b>']));

        // Messages use it: entering figures for a roll-up of branches
        $region = $this->createUnit($this->rootId(), 'Metro', 'Region');
        $this->createUnit($region, 'Uptown', 'Branch');
        $kpi = $this->createKpi(['code' => 'covers', 'name' => 'Covers']);
        $refused = $this->call('PUT', '/values', [], ['values' => [['kpi_id' => $kpi['id'], 'unit_id' => $region, 'period' => '2025-06-01', 'value' => 1]]]);
        $this->assertTrue(strpos(json_encode($refused[1]), 'adds up the branches under it') !== false, json_encode($refused[1]));
    }

    public function test7KpiScopedToADepartment(): void
    {
        $this->actingAs(self::$admin);
        $root = $this->rootId();
        $kitchen = $this->createUnit($root, 'Kitchen', 'Department');
        $waste = $this->createKpi(['code' => 'food_waste', 'name' => 'Food waste', 'direction' => 'lower_is_better', 'scope_unit_ids' => [$kitchen]]);
        $this->assertSame([$kitchen], $waste['scope_unit_ids']);

        // Only the kitchen reports it
        $this->ok('PUT', '/values', [], ['values' => [['kpi_id' => $waste['id'], 'unit_id' => $kitchen, 'period' => '2025-06-01', 'value' => 12]]]);
        $downtown = $this->unitId('Downtown');
        $refused = $this->call('PUT', '/values', [], ['values' => [['kpi_id' => $waste['id'], 'unit_id' => $downtown, 'period' => '2025-06-01', 'value' => 1]]]);
        $this->assertStatus(400, $refused);
        $this->assertSame("Downtown doesn't report Food waste", $refused[1]['details']['values.0']);
        $this->assertSame(false, in_array('food_waste', array_map(fn($r) => $r['kpi']['code'], $this->ok('GET', '/values', ['unit' => (string)$downtown, 'date' => '2025-06-01'])['rows']), true));

        // Downtown's scorecard leaves it out; the organisation's counts only the kitchen
        $codes = fn(int $unit) => array_map(fn($r) => $r['kpi']['code'], $this->ok('GET', '/scorecard', ['unit' => (string)$unit, 'date' => '2025-06-01'])['kpis']);
        $this->assertSame(false, in_array('food_waste', $codes($downtown), true));
        $row = array_values(array_filter($this->ok('GET', '/scorecard', ['unit' => (string)$root, 'date' => '2025-06-01'])['kpis'], fn($r) => $r['kpi']['code'] === 'food_waste'))[0];
        $this->assertSame(1, $row['reported']);
        $this->assertSame(1, $row['expected']);

        // The breakdown lists only units the KPI concerns
        $breakdown = $this->ok('GET', "/kpis/{$waste['id']}/breakdown", ['unit' => (string)$root, 'date' => '2025-06-01', 'grain' => 'monthly']);
        $this->assertSame(['Kitchen'], array_map(fn($r) => $r['unit']['name'], $breakdown['units']));

        // Choosing the whole organisation means "everyone"
        $this->assertSame([], $this->ok('PUT', "/kpis/{$waste['id']}", [], ['scope_unit_ids' => [$root]])['scope_unit_ids']);
    }

    private function unitId(string $name): int
    {
        foreach ($this->ok('GET', '/units') as $u) {
            if ($u['name'] === $name) {
                return $u['id'];
            }
        }
        $this->fail("No unit {$name}");
    }

    private function unitName(int $id): string
    {
        return array_column($this->ok('GET', '/units'), 'name', 'id')[$id];
    }
}
