<?php

namespace Services;

use Core\HttpException;
use Core\Validator;
use Repositories\UnitRepository;

/**
 * Admin changes to the organisation tree. Invariants: one root; no cycles; sibling names
 * are unique; a unit that holds values stays a leaf (values only live on leaves).
 */
class UnitService
{
    private const CODE_PATTERN = '/^[A-Za-z0-9_.\-]+$/';

    private UnitRepository $units;
    private AccessService $access;

    public function __construct(UnitRepository $units, AccessService $access)
    {
        $this->units = $units;
        $this->access = $access;
    }

    public function create(array $input): array
    {
        $v = new Validator($input);
        $parentId = $v->int('parent_id', true, 1);
        $name = $v->string('name', true, 255);
        $code = $v->string('code', false, 64, self::CODE_PATTERN, 'may only use letters, digits, _ . and -');
        $kind = $v->string('kind', false, 64);
        $v->check();

        $tree = $this->access->tree();
        $parent = $tree->get($parentId);
        if (!$parent) {
            $v->addError('parent_id', 'unit not found');
        } elseif ($parent['has_values']) {
            $v->addError('parent_id', 'already holds values, so it must stay a leaf');
        } elseif ($tree->findChildByName($parentId, $name) !== null) {
            $v->addError('name', 'is already used by another unit under the same parent');
        }
        $this->checkCode($v, $code, null);
        $v->check();

        $id = $this->units->create($parentId, $name, $code, $kind);
        return $this->access->refreshTree()->get($id);
    }

    public function update(int $id, array $input): array
    {
        $tree = $this->access->tree();
        $unit = $tree->get($id) ?? throw HttpException::notFound('Unit not found');
        $isRoot = $unit['parent_id'] === null;

        $v = new Validator($input);
        $fields = [
            'name' => $v->string('name', $v->has('name'), 255),
            'code' => $v->string('code', false, 64, self::CODE_PATTERN, 'may only use letters, digits, _ . and -'),
            'kind' => $v->string('kind', false, 64),
            'is_active' => $v->bool('is_active', $v->has('is_active')),
            'parent_id' => $v->int('parent_id', $v->has('parent_id'), 1),
        ];
        $v->check();
        $fields = array_filter($fields, fn($key) => $v->has($key), ARRAY_FILTER_USE_KEY);

        $parentId = $fields['parent_id'] ?? $unit['parent_id'];
        if ($isRoot && (isset($fields['parent_id']) || ($fields['is_active'] ?? true) === false)) {
            $v->addError(isset($fields['parent_id']) ? 'parent_id' : 'is_active', 'the organisation root cannot be moved or deactivated');
        }
        if (isset($fields['parent_id']) && $fields['parent_id'] !== $unit['parent_id']) {
            $newParent = $tree->get($fields['parent_id']);
            if (!$newParent) {
                $v->addError('parent_id', 'unit not found');
            } elseif ($tree->isInSubtree($fields['parent_id'], $id)) {
                $v->addError('parent_id', 'cannot move a unit inside itself');
            } elseif ($newParent['has_values']) {
                $v->addError('parent_id', 'already holds values, so it must stay a leaf');
            }
        }
        $name = $fields['name'] ?? $unit['name'];
        if (!$isRoot && $parentId !== null) {
            $sibling = $tree->findChildByName($parentId, $name);
            if ($sibling !== null && $sibling !== $id) {
                $v->addError('name', 'is already used by another unit under the same parent');
            }
        }
        if (array_key_exists('code', $fields)) {
            $this->checkCode($v, $fields['code'], $id);
        }
        $v->check();

        if (isset($fields['is_active'])) {
            $fields['is_active'] = (int)$fields['is_active'];
        }
        $this->units->update($id, $fields);
        return $this->access->refreshTree()->get($id);
    }

    public function delete(int $id): void
    {
        $tree = $this->access->tree();
        $unit = $tree->get($id) ?? throw HttpException::notFound('Unit not found');
        if ($unit['parent_id'] === null) {
            throw HttpException::conflict('The organisation root cannot be deleted');
        }
        if (!$tree->isLeaf($id)) {
            throw HttpException::conflict('Delete or move the units under it first');
        }
        if ($unit['has_values']) {
            throw HttpException::conflict('This unit has data; deactivate it instead');
        }
        $this->units->delete($id);
        $this->access->refreshTree();
    }

    private function checkCode(Validator $v, ?string $code, ?int $selfId): void
    {
        if ($code === null) {
            return;
        }
        $clash = $this->access->tree()->findByCode($code);
        if ($clash !== null && $clash !== $selfId) {
            $v->addError('code', 'is already used by another unit');
        }
    }
}
