<?php

namespace Services;

use Core\Auth;
use Core\HttpException;
use Core\Validator;
use PDO;
use Repositories\MembershipRepository;
use Repositories\OrganizationRepository;

/** The people in the current organisation: roles, unit access, removal. Admins only. */
class MemberService
{
    private PDO $db;
    private Auth $auth;
    private AccessService $access;
    private OrganizationRepository $organizations;
    private MembershipRepository $memberships;

    public function __construct(PDO $db, Auth $auth, AccessService $access, OrganizationRepository $organizations, MembershipRepository $memberships)
    {
        $this->db = $db;
        $this->auth = $auth;
        $this->access = $access;
        $this->organizations = $organizations;
        $this->memberships = $memberships;
    }

    public function list(): array
    {
        $orgId = $this->auth->requireAdmin()['id'];
        return array_map(
            fn($m) => $m + ['memberships' => $this->memberships->forUser($orgId, $m['id'])],
            $this->organizations->members($orgId)
        );
    }

    public function setRole(int $userId, array $input): array
    {
        $orgId = $this->auth->requireAdmin()['id'];
        $current = $this->requireMember($orgId, $userId);
        $v = new Validator($input);
        $role = $v->enum('role', ['admin', 'member'], true);
        $v->check();
        if ($current === 'admin' && $role === 'member' && $this->organizations->countAdmins($orgId) === 1) {
            throw HttpException::conflict('This is the last administrator. Make someone else an administrator first');
        }
        $this->organizations->setRole($orgId, $userId, $role);
        return $this->list();
    }

    /** Remove someone from the organisation (their account and entered figures stay). */
    public function remove(int $userId): void
    {
        $orgId = $this->auth->requireAdmin()['id'];
        $role = $this->requireMember($orgId, $userId);
        if ($role === 'admin' && $this->organizations->countAdmins($orgId) === 1) {
            throw HttpException::conflict('This is the last administrator. Make someone else an administrator first');
        }
        $this->organizations->removeMember($orgId, $userId);
    }

    /** Replace a member's unit access with [{unit_id, role}]. */
    public function replaceMemberships(int $userId, array $input): array
    {
        $orgId = $this->auth->requireAdmin()['id'];
        $this->requireMember($orgId, $userId);
        $clean = $this->validAccess($input['memberships'] ?? null, 'memberships');

        $this->db->beginTransaction();
        try {
            $this->memberships->replace($orgId, $userId, $clean);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        return $this->memberships->forUser($orgId, $userId);
    }

    /**
     * Validate a list of {unit_id, role} against the current organisation's units.
     * @return array<array{unit_id: int, role: string}>
     */
    public function validAccess($rows, string $field): array
    {
        $v = new Validator([$field => $rows]);
        $rows = $v->array($field, true) ?? [];
        $v->check();

        $clean = [];
        foreach (array_values($rows) as $i => $row) {
            $rv = new Validator(is_array($row) ? $row : []);
            $unitId = $rv->int('unit_id', true, 1);
            $role = $rv->enum('role', ['viewer', 'contributor'], true);
            if ($unitId !== null && !$this->access->tree()->get($unitId)) {
                $rv->addError('unit_id', 'unit not found');
            }
            if ($unitId !== null && isset($clean[$unitId])) {
                $rv->addError('unit_id', 'is listed twice');
            }
            try {
                $rv->check();
                $clean[$unitId] = ['unit_id' => $unitId, 'role' => $role];
            } catch (HttpException $e) {
                $v->addError("{$field}.{$i}", implode('; ', array_map(fn($f, $m) => "{$f} {$m}", array_keys($e->getDetails()), $e->getDetails())));
            }
        }
        $v->check('Some access entries are invalid');
        return array_values($clean);
    }

    private function requireMember(int $orgId, int $userId): string
    {
        return $this->organizations->roleOf($orgId, $userId) ?? throw HttpException::notFound('This person is not in your organisation');
    }
}
