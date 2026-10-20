<?php

namespace Tests\Integration;

/** The month's data-entry form and how far each unit has got with it. */
class EntryProgressTest extends ApiTestCase
{
    private static int $admin;
    private static int $clerk;
    private static array $sales;
    private static array $visits;
    private static int $a;
    private static int $b;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $t = new self();
        self::$admin = $t->registerAndLogin('admin@example.com', 'Progress Co');
        $north = $t->createUnit($t->rootId(), 'North', 'Region');
        self::$a = $t->createUnit($north, 'Store A', 'Store');
        self::$b = $t->createUnit($north, 'Store B', 'Store');
        self::$sales = $t->createKpi(['code' => 'sales', 'name' => 'Sales']);
        self::$visits = $t->createKpi(['code' => 'visits', 'name' => 'Visits', 'frequency' => 'weekly']);
        self::$clerk = $t->registerAndLogin('clerk@example.com', null);
        $t->inviteAndJoin(self::$admin, self::$clerk, 'clerk@example.com', 'member', [['unit_id' => self::$a, 'role' => 'contributor']]);
    }

    private function save(int $kpi, int $unit, string $period, float $value): void
    {
        $this->ok('PUT', '/values', [], ['values' => [['kpi_id' => $kpi, 'unit_id' => $unit, 'period' => $period, 'value' => $value]]]);
    }

    public function test1TheFormListsEachWeekOfTheMonth(): void
    {
        $this->actingAs(self::$admin);
        $this->save(self::$visits['id'], self::$a, '2025-05-26', 40);
        $this->save(self::$visits['id'], self::$a, '2025-06-02', 50);

        $rows = $this->ok('GET', '/values', ['unit' => self::$a, 'date' => '2025-06-15'])['rows'];
        $weeks = array_values(array_filter($rows, fn($r) => $r['kpi']['code'] === 'visits'));
        $this->assertSame(['2025-06-02', '2025-06-09', '2025-06-16', '2025-06-23', '2025-06-30'], array_column($weeks, 'period_start'));
        // Each week comes with the one before it
        $this->assertNear(50, $weeks[0]['value']);
        $this->assertNear(40, $weeks[0]['previous_value']);
        $this->assertNear(50, $weeks[1]['previous_value']);
        $this->assertSame(1, count(array_filter($rows, fn($r) => $r['kpi']['code'] === 'sales')));
    }

    public function test2ProgressCountsEveryPeriodOfEveryKpi(): void
    {
        $this->actingAs(self::$admin);
        $this->save(self::$sales['id'], self::$a, '2025-06-01', 900);

        $progress = $this->ok('GET', '/values/progress', ['date' => '2025-06-15']);
        $this->assertSame('2025-06-01', $progress['month']);
        $byUnit = array_column($progress['units'], null, 'unit_id');
        // Groups take no figures; each store needs 1 monthly + 5 weekly figures
        $this->assertSame([self::$a, self::$b], array_keys($byUnit));
        $this->assertSame(['unit_id' => self::$a, 'expected' => 6, 'entered' => 2], $byUnit[self::$a]);
        $this->assertSame(['unit_id' => self::$b, 'expected' => 6, 'entered' => 0], $byUnit[self::$b]);
    }

    public function test3MembersOnlySeeWhatTheyCanEnter(): void
    {
        $this->actingAs(self::$clerk);
        $units = $this->ok('GET', '/values/progress', ['date' => '2025-06-01'])['units'];
        $this->assertSame([self::$a], array_column($units, 'unit_id'));
        $this->assertStatus(400, $this->call('GET', '/values/progress', ['date' => 'June']));
    }

    public function test4AnEmptyGroupIsNotAskedForFigures(): void
    {
        $this->actingAs(self::$admin);
        // Made as a group and given a term of its own: still not asked for figures while empty
        $west = $this->ok('POST', '/units', [], ['parent_id' => $this->rootId(), 'name' => 'West', 'kind' => 'Region', 'is_group' => true])['id'];
        $units = array_column($this->ok('GET', '/values/progress', ['date' => '2025-06-01'])['units'], 'unit_id');
        $this->assertSame(false, in_array($west, $units, true));
        $this->assertStatus(400, $this->call('GET', '/values', ['unit' => $west, 'date' => '2025-06-01']));
        // Still empty, so it can be deleted
        $this->assertStatus(200, $this->call('DELETE', "/units/{$west}"));
    }

    public function test5AKpisOnlyUnitCannotBeDeleted(): void
    {
        $this->actingAs(self::$admin);
        $east = $this->createUnit($this->rootId(), 'East', 'Store');
        $this->createKpi(['code' => 'east_only', 'name' => 'East footfall', 'scope_unit_ids' => [$east]]);
        // Deleting it would quietly hand the KPI to everyone
        $response = $this->call('DELETE', "/units/{$east}");
        $this->assertStatus(409, $response);
        $this->assertSame(true, str_contains(json_encode($response), 'East footfall'));
    }
}
