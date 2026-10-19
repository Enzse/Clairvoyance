<?php

namespace Tests\Integration;

/** Leaving an organisation, deleting one with all its data, deleting your account. */
class AccountLifecycleTest extends ApiTestCase
{
    private static int $owner;
    private static int $helper;
    private static int $neighbour;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $t = new self();
        self::$owner = $t->registerAndLogin('owner@example.com', 'Doomed Co');
        self::$helper = $t->registerAndLogin('helper@example.com', null);
        // A second organisation that must survive everything below
        self::$neighbour = $t->registerAndLogin('neighbour@example.com', 'Neighbour Ltd');
        $t->actingAs(self::$owner);
        $t->seed();
        $t->actingAs(self::$neighbour);
        $t->seed();
    }

    /** Units in two levels, a weighted KPI, a scoped KPI, targets, figures, history, people. */
    private function seed(): void
    {
        $region = $this->createUnit($this->rootId(), 'North', 'Region');
        $a = $this->createUnit($region, 'Store A', 'Store');
        $b = $this->createUnit($region, 'Store B', 'Store');
        $customers = $this->createKpi(['code' => 'customers', 'name' => 'Customers']);
        $rate = $this->createKpi(['code' => 'rate', 'name' => 'Rate', 'time_aggregation' => 'average', 'unit_aggregation' => 'weighted_average', 'weight_kpi_id' => $customers['id']]);
        $scoped = $this->createKpi(['code' => 'scoped', 'name' => 'Scoped', 'scope_unit_ids' => [$region]]);
        $this->ok('PUT', "/kpis/{$customers['id']}/targets", [], ['targets' => [['unit_id' => $a, 'effective_from' => '2025-01-01', 'value' => 10]]]);
        foreach ([[$customers, 5], [$rate, 50], [$scoped, 1]] as [$kpi, $value]) {
            $this->ok('PUT', '/values', [], ['values' => [
                ['kpi_id' => $kpi['id'], 'unit_id' => $a, 'period' => '2025-06-01', 'value' => $value],
                ['kpi_id' => $kpi['id'], 'unit_id' => $b, 'period' => '2025-06-01', 'value' => $value + 1],
            ]]);
        }
        $this->ok('PUT', '/values', [], ['values' => [['kpi_id' => $customers['id'], 'unit_id' => $a, 'period' => '2025-06-01', 'value' => 7]]]);
        $this->ok('POST', '/invitations', [], ['email' => 'pending@example.com']);
    }

    private function count(string $sql, array $params = []): int
    {
        $stmt = self::$db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function test1TheOnlyAdminCannotLeave(): void
    {
        $this->actingAs(self::$owner);
        $this->assertStatus(409, $this->call('POST', '/organization/leave'));
    }

    public function test2MembersCanLeave(): void
    {
        $this->inviteAndJoin(self::$owner, self::$helper, 'helper@example.com', 'member', [['unit_id' => $this->rootIdOf(self::$owner), 'role' => 'viewer']]);
        $this->actingAs(self::$helper);
        $me = $this->ok('POST', '/organization/leave');
        $this->assertSame(null, $me['organization']);
        $this->assertSame(0, $this->count('SELECT COUNT(*) FROM unit_memberships WHERE user_id = ?', [self::$helper]));
    }

    public function test3TheOnlyAdminOfAnOrganisationWithOthersCannotDeleteTheirAccount(): void
    {
        $this->inviteAndJoin(self::$owner, self::$helper, 'helper@example.com');
        $this->actingAs(self::$owner);
        $refused = $this->call('DELETE', '/auth/me', [], ['password' => self::PASSWORD]);
        $this->assertStatus(409, $refused);
        $this->assertTrue(strpos($refused[1]['error'], 'Doomed Co') !== false);
    }

    public function test4DeletingNeedsTheExactNameAndPassword(): void
    {
        $this->actingAs(self::$helper);
        $this->assertStatus(403, $this->call('DELETE', '/organization', [], ['name' => 'Doomed Co', 'password' => self::PASSWORD]), 'members cannot');

        $this->actingAs(self::$owner);
        $wrong = $this->call('DELETE', '/organization', [], ['name' => 'doomed co', 'password' => 'nope']);
        $this->assertStatus(400, $wrong);
        $this->assertTrue(isset($wrong[1]['details']['name'], $wrong[1]['details']['password']));
    }

    public function test5DeletingAnOrganisationRemovesEverythingInItAndNothingElse(): void
    {
        $this->actingAs(self::$owner);
        $orgId = $this->ok('GET', '/auth/me')['organization']['id'];
        $neighbourCounts = $this->neighbourCounts();

        $me = $this->ok('DELETE', '/organization', [], ['name' => 'Doomed Co', 'password' => self::PASSWORD]);
        $this->assertSame(null, $me['organization']);
        $this->assertSame([], $me['organizations']);

        foreach (['organizations WHERE id = ?', 'units WHERE organization_id = ?', 'kpis WHERE organization_id = ?', 'organization_users WHERE organization_id = ?', 'invitations WHERE organization_id = ?'] as $table) {
            $this->assertSame(0, $this->count("SELECT COUNT(*) FROM {$table}", [$orgId]), $table);
        }
        $this->assertSame($neighbourCounts, $this->neighbourCounts(), 'the other organisation is untouched');
        // Both people still have their accounts
        $this->assertSame(2, $this->count("SELECT COUNT(*) FROM users WHERE email IN ('owner@example.com', 'helper@example.com')"));
    }

    public function test6DeletingYourAccountTakesYourSoloOrganisationsWithIt(): void
    {
        $this->actingAs(self::$neighbour);
        $orgId = $this->ok('GET', '/auth/me')['organization']['id'];
        $this->assertStatus(400, $this->call('DELETE', '/auth/me', [], ['password' => 'wrong']));
        $this->ok('DELETE', '/auth/me', [], ['password' => self::PASSWORD]);

        $this->assertSame([], $_SESSION);
        $this->assertSame(0, $this->count('SELECT COUNT(*) FROM users WHERE id = ?', [self::$neighbour]));
        $this->assertSame(0, $this->count('SELECT COUNT(*) FROM organizations WHERE id = ?', [$orgId]));
        $this->assertSame(0, $this->count('SELECT COUNT(*) FROM kpi_values'));
        $this->assertStatus(401, $this->call('POST', '/auth/login', [], ['email' => 'neighbour@example.com', 'password' => self::PASSWORD]));
    }

    private function neighbourCounts(): array
    {
        $org = $this->count("SELECT o.id FROM organizations o WHERE o.name = 'Neighbour Ltd'");
        return [
            $this->count('SELECT COUNT(*) FROM units WHERE organization_id = ?', [$org]),
            $this->count('SELECT COUNT(*) FROM kpis WHERE organization_id = ?', [$org]),
            $this->count('SELECT COUNT(*) FROM kpi_values v JOIN kpis k ON k.id = v.kpi_id WHERE k.organization_id = ?', [$org]),
            $this->count('SELECT COUNT(*) FROM kpi_targets t JOIN kpis k ON k.id = t.kpi_id WHERE k.organization_id = ?', [$org]),
        ];
    }

    private function rootIdOf(int $userId): int
    {
        $this->actingAs($userId);
        return $this->rootId();
    }
}
