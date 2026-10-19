<?php

namespace Services;

use Core\Auth;
use Core\HttpException;
use Domain\UnitTree;
use Repositories\MembershipRepository;
use Repositories\UnitRepository;

/**
 * Who can see and enter data for which units of the current organisation. Organisation
 * admins can do everything; members get access through memberships, each covering a unit
 * and its whole subtree. The tree only ever contains the current organisation's units, so
 * an id from another organisation is simply "not found".
 */
class AccessService
{
    private const RANK = ['viewer' => 1, 'contributor' => 2, 'admin' => 3];

    private Auth $auth;
    private MembershipRepository $memberships;
    private UnitRepository $units;
    private ?UnitTree $tree = null;
    private ?array $userMemberships = null;

    public function __construct(Auth $auth, MembershipRepository $memberships, UnitRepository $units)
    {
        $this->auth = $auth;
        $this->memberships = $memberships;
        $this->units = $units;
    }

    public function tree(): UnitTree
    {
        return $this->tree ??= new UnitTree($this->units->all($this->auth->organizationId()));
    }

    /** Call after changing units so later checks in this request see the change. */
    public function refreshTree(): UnitTree
    {
        $this->tree = null;
        return $this->tree();
    }

    /** 'admin', 'contributor', 'viewer' or null for the current user on this unit. */
    public function roleFor(int $unitId): ?string
    {
        if ($this->auth->requireOrganization()['role'] === 'admin') {
            return 'admin';
        }
        $best = null;
        foreach ($this->userMemberships() as $m) {
            if ($this->tree()->isInSubtree($unitId, $m['unit_id'])
                && ($best === null || self::RANK[$m['role']] > self::RANK[$best])) {
                $best = $m['role'];
            }
        }
        return $best;
    }

    public function requireView(int $unitId): array
    {
        $unit = $this->requireUnit($unitId);
        if ($this->roleFor($unitId) === null) {
            throw HttpException::forbidden("You don't have access to this part of the organisation");
        }
        return $unit;
    }

    public function requireContribute(int $unitId): array
    {
        $unit = $this->requireUnit($unitId);
        $role = $this->roleFor($unitId);
        if ($role === null) {
            throw HttpException::forbidden("You don't have access to this part of the organisation");
        }
        if (self::RANK[$role] < self::RANK['contributor']) {
            throw HttpException::forbidden('You can view these figures but not enter them');
        }
        return $unit;
    }

    /**
     * Units the user can see, each with their access role. For members, the units they were
     * granted become the top of their tree.
     */
    public function visibleUnits(): array
    {
        $tree = $this->tree();
        $ids = $this->auth->isAdmin()
            ? ($tree->hasRoot() ? $tree->subtreeIds($tree->rootId()) : [])
            : array_unique(array_merge([], ...array_map(fn($m) => $tree->subtreeIds($m['unit_id']), $this->userMemberships())));

        $visible = [];
        foreach ($ids as $id) {
            $unit = $tree->get($id);
            $unit['is_leaf'] = $tree->isLeaf($id);
            $unit['access'] = $this->roleFor($id);
            $visible[] = $unit;
        }
        return $visible;
    }

    private function requireUnit(int $unitId): array
    {
        $unit = $this->tree()->get($unitId);
        if (!$unit) {
            throw HttpException::notFound('Unit not found');
        }
        return $unit;
    }

    private function userMemberships(): array
    {
        return $this->userMemberships ??= $this->memberships->forUser($this->auth->organizationId(), $this->auth->requireUser()['id']);
    }
}
