<?php

namespace Tests\Unit;

use Domain\KpiScope;
use Domain\UnitTree;
use Tests\TestCase;

/**
 * Company(1) > Sales(2) > North team(4), South team(5)
 *            > Support(3)
 *            > Stores(6) > Downtown(7, Store), Warehouse(8, Warehouse)
 */
class KpiScopeTest extends TestCase
{
    private UnitTree $tree;

    protected function setUp(): void
    {
        $unit = fn($id, $parent, $name, $kind = null) => ['id' => $id, 'parent_id' => $parent, 'name' => $name, 'code' => null, 'kind' => $kind, 'is_active' => true, 'has_values' => false];
        $this->tree = new UnitTree([
            $unit(1, null, 'Company'), $unit(2, 1, 'Sales'), $unit(3, 1, 'Support'),
            $unit(4, 2, 'North team'), $unit(5, 2, 'South team'),
            $unit(6, 1, 'Stores'), $unit(7, 6, 'Downtown', 'Store'), $unit(8, 6, 'Warehouse', 'Warehouse'),
        ]);
    }

    public function testUnscopedKpiCoversEveryLeaf(): void
    {
        $kpi = ['scope_unit_ids' => []];
        $this->assertSame([4, 5, 3, 7, 8], KpiScope::leaves($this->tree, $kpi, 1));
        $this->assertTrue(KpiScope::concerns($this->tree, $kpi, 3));
    }

    public function testScopedKpiOnlyCoversItsUnits(): void
    {
        $revenue = ['scope_unit_ids' => [2]]; // Sales only
        $this->assertSame([4, 5], KpiScope::leaves($this->tree, $revenue, 1));
        $this->assertSame([], KpiScope::leaves($this->tree, $revenue, 3));
        $this->assertTrue(KpiScope::covers($this->tree, $revenue, 4));
        $this->assertSame(false, KpiScope::covers($this->tree, $revenue, 3));
    }

    public function testScorecardsShowKpisThatOverlapTheUnit(): void
    {
        $revenue = ['scope_unit_ids' => [2]];
        $this->assertTrue(KpiScope::concerns($this->tree, $revenue, 1));   // company: contains Sales
        $this->assertTrue(KpiScope::concerns($this->tree, $revenue, 4));   // a team inside Sales
        $this->assertSame(false, KpiScope::concerns($this->tree, $revenue, 3)); // Support
    }

    public function testScopeAndKindCombine(): void
    {
        $stockOuts = ['scope_unit_ids' => [6], 'applies_to_kind' => 'Store'];
        $this->assertSame([7], KpiScope::leaves($this->tree, $stockOuts, 1));
    }
}
