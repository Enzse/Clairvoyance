<?php

namespace Services;

use Core\Auth;
use Core\HttpException;
use Core\Validator;
use PDO;
use Repositories\InvitationRepository;
use Repositories\MembershipRepository;
use Repositories\OrganizationRepository;
use Repositories\UserRepository;

/**
 * Invitations to join an organisation. An admin creates one for an email address with a role
 * and optional unit access; the link carries a random token (only its hash is stored). The
 * person signs in or signs up with that email and accepts. The link is emailed when a mail
 * server is set up, and is always shown to the admin to pass on themselves.
 */
class InvitationService
{
    private const DAYS_VALID = 14;

    private PDO $db;
    private Auth $auth;
    private InvitationRepository $invitations;
    private OrganizationRepository $organizations;
    private MembershipRepository $memberships;
    private UserRepository $users;
    private MemberService $members;
    private AccountService $accounts;
    private InvitationMailer $mailer;

    public function __construct(PDO $db, Auth $auth, InvitationRepository $invitations, OrganizationRepository $organizations,
                                MembershipRepository $memberships, UserRepository $users, MemberService $members, AccountService $accounts,
                                InvitationMailer $mailer)
    {
        $this->mailer = $mailer;
        $this->db = $db;
        $this->auth = $auth;
        $this->invitations = $invitations;
        $this->organizations = $organizations;
        $this->memberships = $memberships;
        $this->users = $users;
        $this->members = $members;
        $this->accounts = $accounts;
    }

    /**
     * @return array the invitation plus `token`, which is only ever returned here, and whether
     *   it was emailed (see InvitationMailer::send)
     */
    public function create(array $input): array
    {
        $organization = $this->auth->requireAdmin();
        $v = new Validator($input);
        $email = $v->string('email', true);
        $role = $v->enum('role', ['admin', 'member']) ?? 'member';
        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $v->addError('email', 'is not a valid email address');
        }
        $v->check();
        $email = mb_strtolower($email);
        $access = $role === 'admin' ? [] : $this->members->validAccess($input['access'] ?? [], 'access');

        $existing = $this->users->findByEmail($email);
        if ($existing && $this->organizations->roleOf($organization['id'], $existing['id'])) {
            throw HttpException::conflict("{$email} is already in {$organization['name']}");
        }

        $token = bin2hex(random_bytes(24));
        $this->db->beginTransaction();
        try {
            // A new invitation for the same person replaces the old link
            $previous = $this->invitations->findPendingByEmail($organization['id'], $email);
            if ($previous) {
                $this->invitations->delete($organization['id'], (int)$previous['id']);
            }
            $this->invitations->create(
                $organization['id'], $email, $role, $access, hash('sha256', $token),
                $this->auth->requireUser()['id'], date('Y-m-d H:i:s', strtotime('+' . self::DAYS_VALID . ' days'))
            );
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        $invite = $this->invitations->findByTokenHash(hash('sha256', $token));
        $sent = $this->mailer->send($invite, $token, $this->auth->requireUser());
        return $this->invitations->findByTokenHash(hash('sha256', $token)) + ['token' => $token] + $sent;
    }

    public function pending(): array
    {
        return $this->invitations->pending($this->auth->requireAdmin()['id']);
    }

    public function revoke(int $id): void
    {
        if (!$this->invitations->delete($this->auth->requireAdmin()['id'], $id)) {
            throw HttpException::notFound('Invitation not found');
        }
    }

    /** What the invitation page shows before signing in. Reveals nothing beyond the invite itself. */
    public function lookup(string $token): array
    {
        $invite = $this->usable($token);
        return [
            'organization_name' => $invite['organization_name'],
            'email' => $invite['email'],
            'role' => $invite['role'],
            'invited_by' => $invite['invited_by'],
            'has_account' => $this->users->findByEmail($invite['email']) !== null,
        ];
    }

    /** Join the organisation as the signed-in user, then switch to it. */
    public function accept(array $input): array
    {
        $user = $this->auth->requireUser();
        $v = new Validator($input);
        $token = $v->string('token', true, 128);
        $v->check();
        $invite = $this->usable($token);
        if (strcasecmp($invite['email'], $user['email']) !== 0) {
            throw HttpException::forbidden("This invitation is for {$invite['email']}, but you're signed in as {$user['email']}");
        }

        $orgId = $invite['organization_id'];
        $this->db->beginTransaction();
        try {
            // Never demote someone who is already in the organisation
            $role = $this->organizations->roleOf($orgId, $user['id']);
            if ($role === null || ($role === 'member' && $invite['role'] === 'admin')) {
                $this->organizations->addMember($orgId, $user['id'], $invite['role']);
            }
            $this->grantAccess($orgId, $user['id'], $invite['access']);
            $this->invitations->markAccepted($invite['id']);
            // Opening a link that was emailed to this address proves it is theirs
            if ($invite['emailed_at'] !== null) {
                $this->users->markVerified($user['id']);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        $this->auth->refresh();
        $this->auth->selectOrganization($orgId);
        return $this->accounts->me();
    }

    /** Adds the invitation's unit access to what the person already has (keeping the higher role). */
    private function grantAccess(int $orgId, int $userId, array $access): void
    {
        if (!$access) {
            return;
        }
        $merged = [];
        foreach ([...$this->memberships->forUser($orgId, $userId), ...$access] as $m) {
            $unitId = (int)$m['unit_id'];
            if (!isset($merged[$unitId]) || $m['role'] === 'contributor') {
                $merged[$unitId] = ['unit_id' => $unitId, 'role' => $m['role']];
            }
        }
        // Units deleted since the invitation was sent are dropped
        $stmt = $this->db->prepare('SELECT id FROM units WHERE organization_id = ?');
        $stmt->execute([$orgId]);
        $existing = array_flip(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
        $this->memberships->replace($orgId, $userId, array_values(array_filter($merged, fn($m) => isset($existing[$m['unit_id']]))));
    }

    private function usable(string $token): array
    {
        $invite = $this->invitations->findByTokenHash(hash('sha256', $token));
        if (!$invite) {
            throw HttpException::notFound("This invitation link isn't valid. Ask for a new one");
        }
        if ($invite['accepted_at'] !== null) {
            throw new HttpException(410, 'This invitation has already been used');
        }
        if (strtotime($invite['expires_at']) < time()) {
            throw new HttpException(410, 'This invitation has expired. Ask for a new one');
        }
        return $invite;
    }
}
