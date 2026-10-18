<?php

namespace Services;

use Domain\UnitTree;

/**
 * Finds the unit for a CSV row, planning new units (as "new:N" placeholders) when allowed.
 */
final class UnitResolver
{
    private UnitTree $tree;
    private int $parentId;
    private bool $create;
    private array $planned = [];        // ref => [parent, name, kind, label]
    private array $plannedChildren = []; // parent ref => [lowercase name => ref]

    public function __construct(UnitTree $tree, int $parentId, bool $create)
    {
        $this->tree = $tree;
        $this->parentId = $parentId;
        $this->create = $create;
    }

    /** @return array{0: int|string, 1: string} unit id or placeholder, and a readable path */
    public function resolve(string $match, array $names, array $kinds): array
    {
        foreach ($names as $name) {
            if ($name === '') {
                throw new \DomainException('The unit is missing');
            }
        }

        if ($match === 'code') {
            $id = $this->tree->findByCode($names[0]);
            if ($id === null) {
                throw new \DomainException("No unit has code '{$names[0]}'");
            }
            return [$id, $this->label($id)];
        }

        if ($match === 'name') {
            $matches = array_values(array_filter(
                $this->tree->subtreeIds($this->parentId),
                fn($id) => $this->tree->isLeaf($id) && mb_strtolower($this->tree->get($id)['name']) === mb_strtolower($names[0])
            ));
            if (count($matches) > 1) {
                throw new \DomainException("More than one unit is named '{$names[0]}'; match by path or code instead");
            }
            if ($matches) {
                return [$matches[0], $this->label($matches[0])];
            }
            [$ref] = $this->child($this->parentId, $names[0], $kinds[0]);
        } else {
            $ref = $this->parentId;
            foreach ($names as $level => $name) {
                [$ref] = $this->child($ref, $name, $kinds[$level]);
            }
        }

        if (is_int($ref) && !$this->tree->isLeaf($ref)) {
            throw new \DomainException($this->label($ref) . ' has units under it; values go on the units at the bottom of the tree');
        }
        return [$ref, $this->labelOf($ref)];
    }

    public function plannedKind(string $ref): ?string
    {
        return $this->planned[$ref]['kind'];
    }

    public function newUnits(): array
    {
        return $this->planned;
    }

    /** Existing or planned units that get a new unit under them. @return array<int|string, string> */
    public function parentsOfNewUnits(): array
    {
        $parents = [];
        foreach ($this->planned as $unit) {
            $parents[$unit['parent']] = $this->labelOf($unit['parent']);
        }
        return $parents;
    }

    /** @return array{0: int|string} */
    private function child($parentRef, string $name, string $kind): array
    {
        $key = mb_strtolower($name);
        if (isset($this->plannedChildren[$parentRef][$key])) {
            return [$this->plannedChildren[$parentRef][$key]];
        }
        if (is_int($parentRef)) {
            $id = $this->tree->findChildByName($parentRef, $name);
            if ($id !== null) {
                return [$id];
            }
            if ($this->tree->get($parentRef)['has_values']) {
                throw new \DomainException($this->label($parentRef) . " holds values, so '{$name}' can't be created under it");
            }
        }
        $path = $this->labelOf($parentRef) . ' > ' . $name;
        if (!$this->create) {
            throw new \DomainException("Unknown unit {$path} (turn on 'create missing units' to add it)");
        }
        $ref = 'new:' . (count($this->planned) + 1);
        $this->planned[$ref] = ['parent' => $parentRef, 'name' => $name, 'kind' => $kind, 'label' => $path];
        $this->plannedChildren[$parentRef][$key] = $ref;
        return [$ref];
    }

    private function labelOf($ref): string
    {
        return is_int($ref) ? $this->label($ref) : $this->planned[$ref]['label'];
    }

    private function label(int $id): string
    {
        return implode(' > ', $this->tree->pathNames($id));
    }
}
