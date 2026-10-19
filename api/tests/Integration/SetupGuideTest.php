<?php

namespace Tests\Integration;

/** The getting-started guide: steps done from the data, skipping, finishing, hiding. */
class SetupGuideTest extends ApiTestCase
{
    private static int $admin;
    private static int $member;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $t = new self();
        self::$admin = $t->registerAndLogin('admin@example.com', 'Guide Co');
        self::$member = $t->registerAndLogin('member@example.com', null);
        $t->inviteAndJoin(self::$admin, self::$member, 'member@example.com');
    }

    public function test1OnlyKpisAndFiguresAreNeeded(): void
    {
        $this->actingAs(self::$admin);
        $setup = $this->ok('GET', '/organization/setup');
        $this->assertSame(['structure', 'targets', 'people'], $setup['skippable']);
        $this->assertSame(true, $setup['steps']['people'], 'someone joined');
        $this->assertSame(false, $setup['complete']);
        $this->assertStatus(400, $this->call('POST', '/organization/setup/skip', [], ['step' => 'kpis']));
    }

    public function test2SkippingAndTakingItBack(): void
    {
        $this->actingAs(self::$admin);
        $setup = $this->ok('POST', '/organization/setup/skip', [], ['step' => 'targets']);
        $this->assertSame(['targets'], $setup['skipped']);
        $setup = $this->ok('POST', '/organization/setup/skip', [], ['step' => 'structure']);
        $this->assertSame(['structure', 'targets'], $setup['skipped']);
        $setup = $this->ok('POST', '/organization/setup/skip', [], ['step' => 'structure', 'skipped' => false]);
        $this->assertSame(['targets'], $setup['skipped']);
    }

    public function test3CompleteOnceEverythingIsDoneOrSkipped(): void
    {
        $this->actingAs(self::$admin);
        $this->ok('POST', '/organization/setup/skip', [], ['step' => 'structure']);
        $kpi = $this->createKpi(['code' => 'sales', 'name' => 'Sales']);
        $this->assertSame(false, $this->ok('GET', '/organization/setup')['complete'], 'no figures yet');

        $this->ok('PUT', '/values', [], ['values' => [['kpi_id' => $kpi['id'], 'unit_id' => $this->rootId(), 'period' => '2025-06-01', 'value' => 5]]]);
        $setup = $this->ok('GET', '/organization/setup');
        $this->assertSame(true, $setup['complete']);
        $this->assertSame(['kpis' => 1, 'values' => 1], ['kpis' => $setup['counts']['kpis'], 'values' => $setup['counts']['values']]);
    }

    public function test4ASkippedStepThatGetsDoneCountsAsDone(): void
    {
        $this->actingAs(self::$admin);
        $kpi = $this->ok('GET', '/kpis')[0];
        $this->ok('PUT', "/kpis/{$kpi['id']}/targets", [], ['targets' => [['unit_id' => null, 'effective_from' => '2025-01-01', 'value' => 10]]]);
        $setup = $this->ok('GET', '/organization/setup');
        $this->assertSame(true, $setup['steps']['targets']);
        $this->assertSame(['structure'], $setup['skipped'], 'targets is done now, not skipped');
        // The KPI list shows the target in force
        $listed = $this->ok('GET', '/kpis')[0];
        $this->assertSame([10.0, 0], [(float)$listed['target'], $listed['unit_target_count']]);
    }

    public function test5HidingAndShowingAgain(): void
    {
        $this->actingAs(self::$admin);
        $this->assertSame(true, $this->ok('POST', '/organization/setup/dismiss')['dismissed']);
        $this->assertSame(false, $this->ok('POST', '/organization/setup/dismiss', [], ['dismissed' => false])['dismissed']);
    }

    public function test6MembersHaveNoGuide(): void
    {
        $this->actingAs(self::$member);
        $this->assertStatus(403, $this->call('GET', '/organization/setup'));
        $this->assertStatus(403, $this->call('POST', '/organization/setup/skip', [], ['step' => 'targets']));
    }
}
