<?php

namespace Domain;

/**
 * The organisation tree, built from UnitRepository::all(). A leaf is a unit with no
 * children at all (inactive children still count), and only leaves hold values.
 */
class UnitTree
{
    /** @var array<int, array> */
    private array $units = [];
    /** @var array<int, int[]> */
    private array $children = [];
    private ?int $rootId = null;

    public function __construct(array $units)
    {
        foreach ($units as $unit) {
            $this->units[$unit['id']] = $unit;
            $this->children[$unit['id']] ??= [];
            if ($unit['parent_id'] === null) {
                $this->rootId = $unit['id'];
            } else {
                $this->children[$unit['parent_id']][] = $unit['id'];
            }
        }
    }

    public function get(int $id): ?array
    {
        return $this->units[$id] ?? null;
    }

    public function rootId(): int
    {
        if ($this->rootId === null) {
            throw new \LogicException('The organisation has no root unit');
        }
        return $this->rootId;
    }

    /** @return int[] */
    public function childIds(int $id): array
    {
        return $this->children[$id] ?? [];
    }

    public function isLeaf(int $id): bool
    {
        return empty($this->children[$id]);
    }

    /** @return int[] the unit and everything below it */
    public function subtreeIds(int $id): array
    {
        $ids = [$id];
        foreach ($this->childIds($id) as $childId) {
            array_push($ids, ...$this->subtreeIds($childId));
        }
        return $ids;
    }

    /**
     * Active leaves under (and including) a unit. Inactive units hide their whole subtree.
     * @return int[]
     */
    public function activeLeafIds(int $id, ?string $kind = null): array
    {
        $unit = $this->get($id);
        if (!$unit || !$unit['is_active']) {
            return [];
        }
        if ($this->isLeaf($id)) {
            return ($kind === null || $unit['kind'] === $kind) ? [$id] : [];
        }
        $ids = [];
        foreach ($this->childIds($id) as $childId) {
            array_push($ids, ...$this->activeLeafIds($childId, $kind));
        }
        return $ids;
    }

    public function isInSubtree(int $id, int $ancestorId): bool
    {
        for ($current = $id; $current !== null; $current = $this->units[$current]['parent_id'] ?? null) {
            if ($current === $ancestorId) {
                return true;
            }
        }
        return false;
    }

    /** @return string[] names from the root down to the unit */
    public function pathNames(int $id): array
    {
        $names = [];
        for ($current = $id; $current !== null; $current = $this->units[$current]['parent_id']) {
            array_unshift($names, $this->units[$current]['name']);
        }
        return $names;
    }

    public function findChildByName(int $parentId, string $name): ?int
    {
        $needle = mb_strtolower(trim($name));
        foreach ($this->childIds($parentId) as $childId) {
            if (mb_strtolower($this->units[$childId]['name']) === $needle) {
                return $childId;
            }
        }
        return null;
    }

    public function findByCode(string $code): ?int
    {
        foreach ($this->units as $unit) {
            if ($unit['code'] !== null && strcasecmp($unit['code'], trim($code)) === 0) {
                return $unit['id'];
            }
        }
        return null;
    }
}
