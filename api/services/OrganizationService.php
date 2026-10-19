<?php

namespace Services;

use Core\Auth;
use Core\HttpException;
use Core\Validator;
use PDO;
use Repositories\OrganizationRepository;
use Repositories\UnitRepository;
use Repositories\UserRepository;

/**
 * Organisations (tenants). Each has one unit tree whose root carries the organisation's name.
 */
class OrganizationService
{
    private PDO $db;
    private Auth $auth;
    private OrganizationRepository $organizations;
    private UnitRepository $units;
    private AccountService $accounts;
    private UserRepository $users;

    public function __construct(PDO $db, Auth $auth, OrganizationRepository $organizations, UnitRepository $units, AccountService $accounts,
                                UserRepository $users)
    {
        $this->users = $users;
        $this->db = $db;
        $this->auth = $auth;
        $this->organizations = $organizations;
        $this->units = $units;
        $this->accounts = $accounts;
    }

    /** Create an organisation, make the current user its admin, and switch to it. */
    public function create(array $input): array
    {
        $user = $this->auth->requireUser();
        $name = $this->validName($input);

        $this->db->beginTransaction();
        try {
            $id = $this->organizations->create($name);
            $this->units->create($id, null, $name, null, null);
            $this->organizations->addMember($id, $user['id'], 'admin');
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        $this->auth->selectOrganization($id);
        return $this->accounts->me();
    }

    /**
     * Change the organisation's name and/or the word it uses for the parts that report
     * figures (unit_label: "branch", "department"…, singular).
     */
    public function update(array $input): array
    {
        $organization = $this->auth->requireAdmin();
        $v = new Validator($input);
        $name = $v->string('name', false, 255);
        $label = $v->string('unit_label', false, 40);
        if ($label !== null && !preg_match('/^\p{L}[\p{L} \'-]*$/u', $label)) {
            $v->addError('unit_label', 'should be a word such as branch, department or team');
        }
        if ($name === null && $label === null) {
            $v->addError('name', 'is required');
        }
        $v->check();

        if ($name !== null) {
            $this->organizations->rename($organization['id'], $name);
            $this->units->renameRoot($organization['id'], $name);
        }
        if ($label !== null) {
            $this->organizations->setUnitLabel($organization['id'], mb_strtolower($label));
        }
        $this->auth->refresh();
        return $this->accounts->me();
    }

    public function select(array $input): array
    {
        $v = new Validator($input);
        $id = $v->int('organization_id', true, 1);
        $v->check();
        $this->auth->selectOrganization($id);
        return $this->accounts->me();
    }

    /** Leave the current organisation. Its last administrator has to hand over or delete it. */
    public function leave(): array
    {
        $user = $this->auth->requireUser();
        $organization = $this->auth->requireOrganization();
        if ($organization['role'] === 'admin' && $this->organizations->countAdmins($organization['id']) === 1) {
            throw HttpException::conflict($this->organizations->countMembers($organization['id']) === 1
                ? "You're the only person in {$organization['name']}. Delete the organisation instead"
                : "You're the only administrator of {$organization['name']}. Make someone else an administrator first");
        }
        $this->organizations->removeMember($organization['id'], $user['id']);
        $this->auth->refresh();
        return $this->accounts->me();
    }

    /**
     * Delete the current organisation with all its units, KPIs, figures and history. Needs its
     * exact name and the admin's password, because it can't be undone.
     */
    public function delete(array $input): array
    {
        $user = $this->auth->requireUser();
        $organization = $this->auth->requireAdmin();
        $v = new Validator($input);
        $name = $v->string('name', true, 255);
        $password = $v->secret('password', true);
        if ($name !== null && $name !== $organization['name']) {
            $v->addError('name', "doesn't match. Type {$organization['name']} exactly");
        }
        if ($password !== null && !$this->users->passwordMatches($user['id'], $password)) {
            $v->addError('password', 'is not right');
        }
        $v->check();

        $this->db->beginTransaction();
        try {
            $this->organizations->deleteWithData($organization['id']);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        $this->auth->refresh();
        return $this->accounts->me();
    }

    private function validName(array $input): string
    {
        $v = new Validator($input);
        $name = $v->string('name', true, 255);
        $v->check();
        return $name;
    }
}
