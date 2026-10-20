<?php

namespace Tests\Unit;

use Domain\UnitTree;
use Tests\TestCase;

class UnitTreeTest extends TestCase
{
    private UnitTree $tree;

    protected function setUp(): void
    {
        // Org(1) > NCR(2) > Manila(3) > School A(5), School B(6)
        //        > Cebu(4, inactive) > School C(7)
        //        NCR(2) > Office(8, kind Office)
        $this->tree = new UnitTree([
            $this->unit(1, null, 'Org'),
            $this->unit(2, 1, 'NCR', 'Region'),
            $this->unit(3, 2, 'Manila', 'Division'),
            $this->unit(4, 1, 'Cebu', 'Region', false),
            $this->unit(5, 3, 'School A', 'School'),
            $this->unit(6, 3, 'School B', 'School'),
            $this->unit(7, 4, 'School C', 'School'),
            $this->unit(8, 2, 'Office', 'Office'),
        ]);
    }

    public function testLeavesSkipInactiveSubtrees(): void
    {
        $this->assertSame([5, 6, 8], $this->sorted($this->tree->activeLeafIds(1)));
        $this->assertSame([], $this->tree->activeLeafIds(4));
    }

    public function testLeavesFilteredByKind(): void
    {
        $this->assertSame([5, 6], $this->sorted($this->tree->activeLeafIds(1, 'School')));
    }

    public function testLeafIsUnitWithoutChildren(): void
    {
        $this->assertTrue($this->tree->isLeaf(5));
        $this->assertSame(false, $this->tree->isLeaf(4)); // inactive children still count
    }

    public function testAnEmptyGroupDoesNotReport(): void
    {
        $tree = new UnitTree([
            $this->unit(1, null, 'Org'),
            ['is_group' => true] + $this->unit(2, 1, 'West', 'Region'),
            $this->unit(3, 1, 'Downtown', 'Branch'),
            ['is_group' => true, 'has_values' => true] + $this->unit(4, 1, 'Old group', 'Group'),
        ]);
        $this->assertSame(false, $tree->isLeaf(2));
        $this->assertSame(false, $tree->hasChildren(2));
        $this->assertSame([3, 4], $this->sorted($tree->activeLeafIds(1)));
        // One that already holds figures keeps reporting
        $this->assertTrue($tree->isLeaf(4));
    }

    public function testSubtreeAndAncestry(): void
    {
        $this->assertSame([2, 3, 5, 6, 8], $this->sorted($this->tree->subtreeIds(2)));
        $this->assertTrue($this->tree->isInSubtree(5, 2));
        $this->assertTrue($this->tree->isInSubtree(5, 5));
        $this->assertSame(false, $this->tree->isInSubtree(7, 2));
        $this->assertSame(['Org', 'NCR', 'Manila', 'School A'], $this->tree->pathNames(5));
        $this->assertSame(1, $this->tree->rootId());
    }

    public function testFindChildByNameIgnoresCase(): void
    {
        $this->assertSame(5, $this->tree->findChildByName(3, ' school a '));
        $this->assertNull($this->tree->findChildByName(3, 'School C'));
    }

    private function unit(int $id, ?int $parentId, string $name, ?string $kind = null, bool $active = true): array
    {
        return ['id' => $id, 'parent_id' => $parentId, 'name' => $name, 'code' => null, 'kind' => $kind, 'is_active' => $active, 'has_values' => false];
    }

    private function sorted(array $ids): array
    {
        sort($ids);
        return $ids;
    }
}
