<?php

namespace Services;

use Core\Auth;
use Core\HttpException;
use Core\Validator;
use Repositories\MembershipRepository;
use Repositories\OrganizationRepository;
use Repositories\UserRepository;

/** Signing up, signing in, and a person's own account. */
class AccountService
{
    public const MIN_PASSWORD = 8;

    private Auth $auth;
    private UserRepository $users;
    private OrganizationRepository $organizations;
    private MembershipRepository $memberships;

    public function __construct(Auth $auth, UserRepository $users, OrganizationRepository $organizations, MembershipRepository $memberships)
    {
        $this->auth = $auth;
        $this->users = $users;
        $this->organizations = $organizations;
        $this->memberships = $memberships;
    }

    /** Creates the account and signs in. The next step is creating or joining an organisation. */
    public function register(array $input): array
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
            $v->addError('email', 'already has an account. Sign in instead');
        }
        $v->check();

        $user = $this->users->find($this->users->create(mb_strtolower($email), $password, $name));
        $this->auth->login($user);
        return $this->me();
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

    /**
     * The signed-in person, every organisation they belong to, the one they're working in
     * (null means they still need to create or join one), and their unit access there.
     */
    public function me(): array
    {
        $user = $this->auth->requireUser();
        $current = $this->auth->organization();
        return $user + [
            'organizations' => $this->organizations->forUser($user['id']),
            'organization' => $current,
            'memberships' => $current ? $this->memberships->forUser($current['id'], $user['id']) : [],
        ];
    }

    /** Change your own name and/or password (the current password is required for the latter). */
    public function update(array $input): array
    {
        $user = $this->auth->requireUser();
        $v = new Validator($input);
        $name = $v->string('name', false, 255);
        $current = $v->secret('current_password', $v->has('new_password'));
        $new = $v->secret('new_password');
        if ($new !== null && strlen($new) < self::MIN_PASSWORD) {
            $v->addError('new_password', 'must be at least ' . self::MIN_PASSWORD . ' characters');
        }
        if ($new !== null && $current !== null && !$this->users->passwordMatches($user['id'], $current)) {
            $v->addError('current_password', 'is not right');
        }
        $v->check();

        $fields = [];
        if ($v->has('name')) {
            $fields['name'] = $name;
        }
        if ($new !== null) {
            $fields['password'] = $new;
        }
        $this->users->update($user['id'], $fields);
        return $this->users->find($user['id']) + ['organizations' => $this->organizations->forUser($user['id'])];
    }
}
