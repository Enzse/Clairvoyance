<?php

namespace Services;

use Domain\UnitTree;
use Domain\Words;

/**
 * Finds the unit for a CSV row, planning new units (as "new:N" placeholders) when allowed.
 */
final class UnitResolver
{
    private UnitTree $tree;
    private int $parentId;
    private bool $create;
    private string $noun;              // the organisation's word, e.g. "branch"
    private array $planned = [];        // ref => [parent, name, kind, label]
    private array $plannedChildren = []; // parent ref => [lowercase name => ref]

    public function __construct(UnitTree $tree, int $parentId, bool $create, string $noun = 'team')
    {
        $this->noun = $noun;
        $this->tree = $tree;
        $this->parentId = $parentId;
        $this->create = $create;
    }

    /** @return array{0: int|string, 1: string} unit id or placeholder, and a readable path */
    public function resolve(string $match, array $names, array $kinds): array
    {
        foreach ($names as $name) {
            if ($name === '') {
                throw new \DomainException("The {$this->noun} is missing");
            }
        }

        if ($match === 'code') {
            $id = $this->tree->findByCode($names[0]);
            if ($id === null) {
                throw new \DomainException("No {$this->noun} has code '{$names[0]}'");
            }
            return [$id, $this->label($id)];
        }

        if ($match === 'name') {
            $matches = array_values(array_filter(
                $this->tree->subtreeIds($this->parentId),
                fn($id) => $this->tree->isLeaf($id) && mb_strtolower($this->tree->get($id)['name']) === mb_strtolower($names[0])
            ));
            if (count($matches) > 1) {
                throw new \DomainException("More than one {$this->noun} is named '{$names[0]}'; match by path or code instead");
            }
            if ($matches) {
                return [$matches[0], $this->label($matches[0])];
            }
            // Say why nothing matched: the name belongs to a group, or to nothing at all
            foreach ($this->tree->subtreeIds($this->parentId) as $id) {
                if (mb_strtolower($this->tree->get($id)['name']) === mb_strtolower($names[0])) {
                    throw new \DomainException($this->label($id) . ' has ' . Words::plural($this->noun) . ' inside it; figures go on those instead');
                }
            }
            // The same name apart from punctuation, spaces or capitals: most likely a typo on one side
            $loose = fn(string $text) => preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($text));
            foreach ($this->tree->subtreeIds($this->parentId) as $id) {
                $other = $this->tree->get($id)['name'];
                if ($this->tree->isLeaf($id) && $loose($other) === $loose($names[0])) {
                    throw new \DomainException("No {$this->noun} is called '{$names[0]}'. Did you mean '{$other}'? Fix the name in the file or in Structure");
                }
            }
            if (!$this->create) {
                throw new UnknownUnitException("No {$this->noun} is called '{$names[0]}' in " . $this->label($this->parentId) . ' (check the spelling, or turn on adding new ones)');
            }
            [$ref] = $this->child($this->parentId, $names[0], $kinds[0]);
        } else {
            $ref = $this->parentId;
            foreach ($names as $level => $name) {
                [$ref] = $this->child($ref, $name, $kinds[$level]);
            }
        }

        if (is_int($ref) && !$this->tree->isLeaf($ref)) {
            throw new \DomainException($this->label($ref) . ' adds up the ' . Words::plural($this->noun) . ' under it; figures go on those instead');
        }
        return [$ref, $this->labelOf($ref)];
    }

    /** The unit itself if it exists, otherwise the nearest existing unit above where it will be created. */
    public function existingAncestor($ref): int
    {
        while (!is_int($ref)) {
            $ref = $this->planned[$ref]['parent'];
        }
        return $ref;
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
            throw new UnknownUnitException("There's no {$this->noun} {$path} (turn on adding new ones to create it)");
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
