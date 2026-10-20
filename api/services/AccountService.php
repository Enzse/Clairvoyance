<?php

namespace Services;

use Core\Auth;
use Core\HttpException;
use Core\RateLimiter;
use Core\Validator;
use PDO;
use Repositories\MembershipRepository;
use Repositories\OrganizationRepository;
use Repositories\UserRepository;

/** Signing up, signing in, and a person's own account. */
class AccountService
{
    public const MIN_PASSWORD = 8;
    /** The date the Terms and Privacy Policy were last changed (web: routes/terms, routes/privacy) */
    public const TERMS_VERSION = '2026-10-20';

    private PDO $db;
    private Auth $auth;
    private UserRepository $users;
    private OrganizationRepository $organizations;
    private MembershipRepository $memberships;
    private AccountEmailService $verification;
    private RateLimiter $limiter;

    public function __construct(PDO $db, Auth $auth, UserRepository $users, OrganizationRepository $organizations, MembershipRepository $memberships,
                                AccountEmailService $verification, RateLimiter $limiter)
    {
        $this->db = $db;
        $this->verification = $verification;
        $this->limiter = $limiter;
        $this->auth = $auth;
        $this->users = $users;
        $this->organizations = $organizations;
        $this->memberships = $memberships;
    }

    /** Creates the account and signs in. The next step is creating or joining an organisation. */
    public function register(array $input, string $ip): array
    {
        $this->limiter->attempt("register:{$ip}", 10, 3600);
        $v = new Validator($input);
        $email = $v->string('email', true);
        $password = $v->secret('password', true);
        $name = $v->string('name', false, 255);
        if (!$v->bool('accept_terms')) {
            $v->addError('accept_terms', 'must be agreed to before creating an account');
        }
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

        $user = $this->users->find($this->users->create(mb_strtolower($email), $password, $name, self::TERMS_VERSION));
        $this->auth->login($user);
        $this->verification->sendVerification($user);
        return $this->me();
    }

    /** Five wrong passwords lock that email for 15 minutes; many failures lock the address. */
    public function login(array $input, string $ip): array
    {
        $v = new Validator($input);
        $email = $v->string('email', true);
        $password = $v->secret('password', true);
        $v->check();

        $emailBucket = RateLimiter::emailBucket('login', $email);
        $this->limiter->check($emailBucket, 5, 900);
        $this->limiter->check("login-ip:{$ip}", 30, 900);
        $user = $this->users->verifyCredentials(mb_strtolower($email), $password);
        if (!$user) {
            $this->limiter->hit($emailBucket, 900);
            $this->limiter->hit("login-ip:{$ip}", 900);
            throw new HttpException(401, 'Wrong email or password');
        }
        $this->limiter->clear($emailBucket);
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
            'email_verified' => $this->verification->isVerified($user),
            'email_delivery' => $this->verification->deliveryEnabled(),
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
        if ($new !== null) {
            $this->auth->signOutOtherSessions();
        }
        return $this->users->find($user['id']) + ['organizations' => $this->organizations->forUser($user['id'])];
    }

    /**
     * Delete your own account (password required). Organisations where you're the only person
     * go with it. Refused while you're the only administrator of one that has other people.
     * Figures you entered stay, without your name.
     */
    public function delete(array $input): void
    {
        $user = $this->auth->requireUser();
        $v = new Validator($input);
        $password = $v->secret('password', true);
        if ($password !== null && !$this->users->passwordMatches($user['id'], $password)) {
            $v->addError('password', 'is not right');
        }
        $v->check();

        $alone = [];
        foreach ($this->organizations->forUser($user['id']) as $organization) {
            if ($organization['role'] !== 'admin' || $this->organizations->countAdmins($organization['id']) > 1) {
                continue;
            }
            if ($this->organizations->countMembers($organization['id']) > 1) {
                throw HttpException::conflict("You're the only administrator of {$organization['name']}. Make someone else an administrator first, or delete the organisation");
            }
            $alone[] = $organization['id'];
        }

        $this->db->beginTransaction();
        try {
            foreach ($alone as $organizationId) {
                $this->organizations->deleteWithData($organizationId);
            }
            $this->users->delete($user['id']);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        $this->auth->logout();
    }
}
