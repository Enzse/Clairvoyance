<?php

namespace Services;

use Core\Auth;
use Core\HttpException;
use Core\Validator;
use PDO;
use Repositories\MembershipRepository;
use Repositories\UserRepository;

class UserService
{
    private const MIN_PASSWORD = 8;

    private PDO $db;
    private Auth $auth;
    private AccessService $access;
    private UserRepository $users;
    private MembershipRepository $memberships;

    public function __construct(PDO $db, Auth $auth, AccessService $access, UserRepository $users, MembershipRepository $memberships)
    {
        $this->db = $db;
        $this->auth = $auth;
        $this->access = $access;
        $this->users = $users;
        $this->memberships = $memberships;
    }

    /**
     * Public sign-up. The first account on a fresh install becomes the admin; everyone
     * else is a member who sees nothing until an admin grants access to units.
     */
    public function register(array $input): array
    {
        [$email, $password, $name] = $this->credentials($input);
        $role = $this->users->count() === 0 ? 'admin' : 'member';
        return $this->users->find($this->users->create($email, $password, $role, $name));
    }

    public function login(array $input): array
    {
        $v = new Validator($input);
        $email = $v->string('email', true);
        $password = $v->secret('password', true);
        $v->check();

        $user = $this->users->verifyCredentials($email, $password);
        if (!$user) {
            throw new HttpException(401, 'Wrong email or password');
        }
        $this->auth->login($user);
        return $this->me();
    }

    /** The current user with their memberships. */
    public function me(): array
    {
        $user = $this->auth->requireUser();
        $user['memberships'] = $this->memberships->forUser($user['id']);
        return $user;
    }

    public function list(): array
    {
        return array_map(function ($user) {
            $user['memberships'] = $this->memberships->forUser($user['id']);
            return $user;
        }, $this->users->all());
    }

    public function create(array $input): array
    {
        [$email, $password, $name] = $this->credentials($input);
        $v = new Validator($input);
        $role = $v->enum('role', ['admin', 'member']) ?? 'member';
        $v->check();
        return $this->users->find($this->users->create($email, $password, $role, $name));
    }

    public function update(int $id, array $input): array
    {
        $user = $this->users->find($id) ?? throw HttpException::notFound('User not found');
        $v = new Validator($input);
        $fields = [
            'name' => $v->string('name', false, 255),
            'role' => $v->enum('role', ['admin', 'member'], $v->has('role')),
            'password' => $v->secret('password', $v->has('password')),
        ];
        if ($fields['password'] !== null && strlen($fields['password']) < self::MIN_PASSWORD) {
            $v->addError('password', 'must be at least ' . self::MIN_PASSWORD . ' characters');
        }
        if ($fields['role'] === 'member' && $user['role'] === 'admin' && $this->users->countAdmins() === 1) {
            $v->addError('role', 'this is the last admin');
        }
        $v->check();

        $this->users->update($id, array_filter($fields, fn($key) => $v->has($key), ARRAY_FILTER_USE_KEY));
        return $this->users->find($id);
    }

    public function delete(int $id): void
    {
        $user = $this->users->find($id) ?? throw HttpException::notFound('User not found');
        if ($id === $this->auth->requireUser()['id']) {
            throw HttpException::conflict('You cannot delete your own account');
        }
        if ($user['role'] === 'admin' && $this->users->countAdmins() === 1) {
            throw HttpException::conflict('This is the last admin');
        }
        $this->users->delete($id);
    }

    public function memberships(int $userId): array
    {
        $this->users->find($userId) ?? throw HttpException::notFound('User not found');
        return $this->memberships->forUser($userId);
    }

    /** Replace a user's memberships with [{unit_id, role}]. */
    public function replaceMemberships(int $userId, array $input): array
    {
        $this->users->find($userId) ?? throw HttpException::notFound('User not found');
        $v = new Validator($input);
        $rows = $v->array('memberships', true) ?? [];
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
                $v->addError("memberships.{$i}", implode('; ', array_map(fn($f, $m) => "{$f} {$m}", array_keys($e->getDetails()), $e->getDetails())));
            }
        }
        $v->check('Some memberships are invalid');

        $this->db->beginTransaction();
        try {
            $this->memberships->replace($userId, array_values($clean));
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        return $this->memberships->forUser($userId);
    }

    /** @return array{0: string, 1: string, 2: ?string} */
    private function credentials(array $input): array
    {
        $v = new Validator($input);
        $email = $v->string('email', true);
        $password = $v->secret('password', true);
        $name = $v->string('name', false, 255);
        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $v->addError('email', 'is not a valid email address');
        }
        if ($password !== null && strlen($password) < self::MIN_PASSWORD) {
            $v->addError('password', 'must be at least ' . self::MIN_PASSWORD . ' characters');
        }
        if ($email !== null && $this->users->findByEmail($email)) {
            $v->addError('email', 'already has an account');
        }
        $v->check();
        return [mb_strtolower($email), $password, $name];
    }
}
