<?php

namespace Services;

use Core\HttpException;
use Core\Validator;
use Core\Auth;
use Repositories\OrganizationRepository;
use Repositories\UnitRepository;

/**
 * Admin changes to the organisation tree. Invariants: one root; no cycles; sibling names
 * are unique; a unit that holds values stays a leaf (values only live on leaves).
 */
class UnitService
{
    private const CODE_PATTERN = '/^[A-Za-z0-9_.-]+$/';
    /** A name from the web app's icon set */
    private const ICON_PATTERN = '/^[a-z][a-z0-9-]*$/';

    private Auth $auth;
    private UnitRepository $units;
    private OrganizationRepository $organizations;
    private AccessService $access;

    public function __construct(Auth $auth, UnitRepository $units, OrganizationRepository $organizations, AccessService $access)
    {
        $this->units = $units;
        $this->access = $access;
        $this->auth = $auth;
        $this->organizations = $organizations;
    }

    public function create(array $input): array
    {
        $v = new Validator($input);
        $parentId = $v->int('parent_id', true, 1);
        $name = $v->string('name', true, 255);
        $code = $v->string('code', false, 64, self::CODE_PATTERN, 'may only use letters, digits, _ . and -');
        $kind = $v->string('kind', false, 64);
        $icon = $v->string('icon', false, 32, self::ICON_PATTERN, 'is not an icon name');
        $v->check();

        $tree = $this->access->tree();
        $parent = $tree->get($parentId);
        if (!$parent) {
            $v->addError('parent_id', 'not found');
        } elseif ($parent['has_values']) {
            $v->addError('parent_id', 'has figures of its own, so nothing can go inside it');
        } elseif ($tree->findChildByName($parentId, $name) !== null) {
            $v->addError('name', 'is already used by another one in the same place');
        }
        $this->checkCode($v, $code, null);
        $v->check();

        $id = $this->units->create($this->auth->organizationId(), $parentId, $name, $code, $kind, $icon);
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
            'icon' => $v->string('icon', false, 32, self::ICON_PATTERN, 'is not an icon name'),
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
                $v->addError('parent_id', 'not found');
            } elseif ($tree->isInSubtree($fields['parent_id'], $id)) {
                $v->addError('parent_id', "can't be moved inside itself");
            } elseif ($newParent['has_values']) {
                $v->addError('parent_id', 'has figures of its own, so nothing can go inside it');
            }
        }
        $name = $fields['name'] ?? $unit['name'];
        if (!$isRoot && $parentId !== null) {
            $sibling = $tree->findChildByName($parentId, $name);
            if ($sibling !== null && $sibling !== $id) {
                $v->addError('name', 'is already used by another one in the same place');
            }
        }
        if (array_key_exists('code', $fields)) {
            $this->checkCode($v, $fields['code'], $id);
        }
        $v->check();

        if (isset($fields['is_active'])) {
            $fields['is_active'] = (int)$fields['is_active'];
        }
        $this->units->update($this->auth->organizationId(), $id, $fields);
        if ($isRoot && isset($fields['name'])) {
            // The root unit is the organisation itself
            $this->organizations->rename($this->auth->organizationId(), $fields['name']);
        }
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
            throw HttpException::conflict("Delete or move what's under it first");
        }
        if ($unit['has_values']) {
            throw HttpException::conflict('This has figures; deactivate it instead, so they stay in the history');
        }
        $this->units->delete($this->auth->organizationId(), $id);
        $this->access->refreshTree();
    }

    private function checkCode(Validator $v, ?string $code, ?int $selfId): void
    {
        if ($code === null) {
            return;
        }
        $clash = $this->access->tree()->findByCode($code);
        if ($clash !== null && $clash !== $selfId) {
            $v->addError('code', 'is already used by another one');
        }
    }
}
