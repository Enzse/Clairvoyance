<?php

namespace Tests\Integration;

/**
 * Organisations must never see or touch each other's data. Acme has units, a KPI, targets and
 * figures; Globex's admin tries every endpoint with Acme's ids.
 */
class TenancyTest extends ApiTestCase
{
    private static int $acmeAdmin;
    private static int $globexAdmin;
    private static array $acme = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $t = new self();

        self::$acmeAdmin = $t->registerAndLogin('boss@acme.test', 'Acme');
        $root = $t->rootId();
        $store = $t->createUnit($root, 'Acme Store', 'Store');
        $kpi = $t->createKpi(['code' => 'sales', 'name' => 'Sales']);
        $t->ok('PUT', "/kpis/{$kpi['id']}/targets", [], ['targets' => [['unit_id' => null, 'effective_from' => '2025-01-01', 'value' => 10]]]);
        $t->ok('PUT', '/values', [], ['values' => [['kpi_id' => $kpi['id'], 'unit_id' => $store, 'period' => '2025-06-01', 'value' => 42]]]);
        self::$acme = ['root' => $root, 'store' => $store, 'kpi' => $kpi['id'], 'org' => (int)$t->ok('GET', '/auth/me')['organization']['id']];

        self::$globexAdmin = $t->registerAndLogin('boss@globex.test', 'Globex');
    }

    protected function setUp(): void
    {
        $this->actingAs(self::$globexAdmin);
    }

    public function testListsOnlyShowOwnData(): void
    {
        $this->assertSame(['Globex'], array_column($this->ok('GET', '/units'), 'name'));
        $this->assertSame([], $this->ok('GET', '/kpis', ['include_inactive' => '1']));
        $this->assertSame(['boss@globex.test'], array_column($this->ok('GET', '/members'), 'email'));
        $this->assertSame([], $this->ok('GET', '/imports'));
    }

    public function testCannotReadOtherOrganisationsData(): void
    {
        $kpi = self::$acme['kpi'];
        $unit = (string)self::$acme['store'];
        foreach ([
            ['GET', "/kpis/{$kpi}", []],
            ['GET', "/kpis/{$kpi}/targets", []],
            ['GET', "/kpis/{$kpi}/series", ['unit' => $this->own(), 'grain' => 'monthly']],
            ['GET', "/kpis/{$kpi}/breakdown", ['unit' => $this->own(), 'grain' => 'monthly']],
            ['GET', '/scorecard', ['unit' => $unit]],
            ['GET', '/values', ['unit' => $unit, 'date' => '2025-06-01']],
        ] as [$method, $path, $query]) {
            $result = $this->call($method, $path, $query);
            $this->assertStatus(404, $result, "{$method} {$path}");
            $this->assertTrue(strpos(json_encode($result[1]), '42') === false, "{$method} {$path} leaked a value");
        }
    }

    public function testCannotChangeOtherOrganisationsData(): void
    {
        $kpi = self::$acme['kpi'];
        $store = self::$acme['store'];
        $this->assertStatus(404, $this->call('PUT', "/kpis/{$kpi}", [], ['name' => 'Hacked']));
        $this->assertStatus(404, $this->call('PUT', "/kpis/{$kpi}/targets", [], ['targets' => []]));
        $this->assertStatus(404, $this->call('PUT', "/units/{$store}", [], ['name' => 'Hacked']));
        $this->assertStatus(404, $this->call('DELETE', "/units/{$store}"));
        $this->assertStatus(400, $this->call('POST', '/units', [], ['parent_id' => $store, 'name' => 'Inside Acme']));
        $this->assertStatus(400, $this->call('PUT', '/values', [], ['values' => [['kpi_id' => $kpi, 'unit_id' => $store, 'period' => '2025-06-01', 'value' => 0]]]));
        $this->assertStatus(404, $this->call('PUT', '/members/' . self::$acmeAdmin, [], ['role' => 'member']));
        $this->assertStatus(404, $this->call('DELETE', '/members/' . self::$acmeAdmin));
        $this->assertStatus(404, $this->call('PUT', '/members/' . self::$acmeAdmin . '/memberships', [], ['memberships' => []]));

        $this->actingAs(self::$acmeAdmin);
        $this->assertSame('Sales', $this->ok('GET', "/kpis/{$kpi}")['name']);
        $this->assertSame('Acme Store', array_column($this->ok('GET', '/units'), 'name', 'id')[$store]);
        $this->assertNear(42, (float)self::$db->query("SELECT value FROM kpi_values WHERE kpi_id = {$kpi}")->fetchColumn());
    }

    public function testCannotReferenceOtherOrganisationsIdsInOwnData(): void
    {
        $base = ['name' => 'Rate', 'frequency' => 'monthly', 'time_aggregation' => 'average', 'direction' => 'higher_is_better'];
        $weighted = $this->call('POST', '/kpis', [], $base + ['code' => 'rate', 'unit_aggregation' => 'weighted_average', 'weight_kpi_id' => self::$acme['kpi']]);
        $this->assertSame('KPI not found', $weighted[1]['details']['weight_kpi_id'] ?? null);

        $scoped = $this->call('POST', '/kpis', [], $base + ['code' => 'rate2', 'unit_aggregation' => 'average', 'scope_unit_ids' => [self::$acme['store']]]);
        $this->assertStatus(400, $scoped);

        $memberships = $this->call('PUT', '/members/' . self::$globexAdmin . '/memberships', [], ['memberships' => [['unit_id' => self::$acme['store'], 'role' => 'viewer']]]);
        $this->assertStatus(400, $memberships);

        $invite = $this->call('POST', '/invitations', [], ['email' => 'spy@globex.test', 'access' => [['unit_id' => self::$acme['store'], 'role' => 'contributor']]]);
        $this->assertStatus(400, $invite);
    }

    public function testSameKpiCodeCanExistInEveryOrganisation(): void
    {
        $kpi = $this->createKpi(['code' => 'sales', 'name' => 'Globex sales']);
        $this->assertSame('sales', $kpi['code']);
    }

    public function testImportCannotUseOtherOrganisationsKpis(): void
    {
        $mapping = ['unit_columns' => ['Unit'], 'unit_match' => 'name', 'date_column' => 'Date', 'date_format' => 'YYYY-MM-DD', 'columns' => ['Value' => 'acme_only']];
        $this->actingAs(self::$acmeAdmin);
        $this->createKpi(['code' => 'acme_only', 'name' => 'Acme only']);
        $this->actingAs(self::$globexAdmin);
        $result = $this->call('POST', '/imports/preview', [], ['mapping' => json_encode($mapping)], $this->upload($this->csvFile("Unit,Date,Value\nAcme Store,2025-06-01,5\n")));
        $this->assertStatus(400, $result);
        $this->assertSame("no KPI with code 'acme_only'", $result[1]['details']['columns.Value']);
    }

    public function testCannotSwitchIntoAnotherOrganisation(): void
    {
        $this->assertStatus(404, $this->call('POST', '/organizations/select', [], ['organization_id' => self::$acme['org']]));
        // A stale or forged session organisation falls back to the user's own
        $this->actingAs(self::$globexAdmin, self::$acme['org']);
        $this->assertSame('Globex', $this->ok('GET', '/auth/me')['organization']['name']);
        $this->assertSame(['Globex'], array_column($this->ok('GET', '/units'), 'name'));
    }

    public function testSomeoneInBothOrganisationsSeesOneAtATime(): void
    {
        $consultant = $this->registerAndLogin('consultant@example.com', null);
        $this->inviteAndJoin(self::$acmeAdmin, $consultant, 'consultant@example.com', 'admin');
        $this->inviteAndJoin(self::$globexAdmin, $consultant, 'consultant@example.com', 'admin');

        $this->actingAs($consultant);
        $this->assertSame(['Acme', 'Globex'], array_column($this->ok('GET', '/auth/me')['organizations'], 'name'));
        $this->ok('POST', '/organizations/select', [], ['organization_id' => self::$acme['org']]);
        $this->assertSame('Acme', $this->ok('GET', '/auth/me')['organization']['name']);
        $this->assertTrue(in_array('Acme Store', array_column($this->ok('GET', '/units'), 'name'), true));
        $this->assertSame(false, in_array('Globex', array_column($this->ok('GET', '/units'), 'name'), true));
    }

    private function own(): string
    {
        return (string)$this->rootId();
    }
}
