<?php

namespace Core;

use Repositories\OrganizationRepository;
use Repositories\UserRepository;

/**
 * The logged-in user and the organisation they are working in. The session keeps only ids;
 * both are re-read once per request, so removed members and role changes take effect at once.
 * Roles (admin / member) belong to the user's membership in an organisation, not the user.
 */
class Auth
{
    private UserRepository $users;
    private OrganizationRepository $organizations;
    private bool $loaded = false;
    private ?array $user = null;
    private bool $organizationLoaded = false;
    private ?array $organization = null;

    public function __construct(UserRepository $users, OrganizationRepository $organizations)
    {
        $this->users = $users;
        $this->organizations = $organizations;
    }

    public function user(): ?array
    {
        if (!$this->loaded) {
            $id = $_SESSION['user_id'] ?? null;
            $this->user = $id ? $this->users->find((int)$id) : null;
            // A password reset bumps the account's session version, signing out older sessions
            if ($this->user && (int)($_SESSION['session_version'] ?? 0) !== $this->users->sessionVersion($this->user['id'])) {
                $this->user = null;
            }
            if ($id && !$this->user) {
                unset($_SESSION['user_id'], $_SESSION['org_id'], $_SESSION['session_version']);
            }
            $this->loaded = true;
        }
        return $this->user;
    }

    public function requireUser(): array
    {
        $user = $this->user();
        if (!$user) {
            throw new HttpException(401, 'Please log in');
        }
        return $user;
    }

    /**
     * The selected organisation as {id, name, role}, or null if the user belongs to none.
     * Falls back to the user's first organisation when the selection is missing or stale.
     */
    public function organization(): ?array
    {
        if ($this->organizationLoaded) {
            return $this->organization;
        }
        $this->organizationLoaded = true;
        $user = $this->user();
        if (!$user) {
            return $this->organization = null;
        }

        $id = isset($_SESSION['org_id']) ? (int)$_SESSION['org_id'] : null;
        $role = $id ? $this->organizations->roleOf($id, $user['id']) : null;
        if (!$role) {
            $first = $this->organizations->forUser($user['id'])[0] ?? null;
            $id = $first['id'] ?? null;
            $role = $first['role'] ?? null;
        }
        if (!$id) {
            unset($_SESSION['org_id']);
            return $this->organization = null;
        }
        $_SESSION['org_id'] = $id;
        $found = $this->organizations->find($id);
        return $this->organization = ['id' => $id, 'name' => $found['name'], 'role' => $role, 'unit_label' => $found['unit_label']];
    }

    public function requireOrganization(): array
    {
        $this->requireUser();
        $organization = $this->organization();
        if (!$organization) {
            throw new HttpException(403, 'Create or join an organisation first');
        }
        return $organization;
    }

    public function organizationId(): int
    {
        return $this->requireOrganization()['id'];
    }

    public function requireAdmin(): array
    {
        $organization = $this->requireOrganization();
        if ($organization['role'] !== 'admin') {
            throw HttpException::forbidden("Only administrators of {$organization['name']} can do this");
        }
        return $organization;
    }

    public function isAdmin(): bool
    {
        return ($this->organization()['role'] ?? null) === 'admin';
    }

    /** Work in another organisation the user belongs to. */
    public function selectOrganization(int $organizationId): void
    {
        $user = $this->requireUser();
        if (!$this->organizations->roleOf($organizationId, $user['id'])) {
            throw HttpException::notFound('Organisation not found');
        }
        $_SESSION['org_id'] = $organizationId;
        $this->organizationLoaded = false;
    }

    public function login(array $user): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true); // prevents session fixation
        }
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['session_version'] = $this->users->sessionVersion($user['id']);
        unset($_SESSION['org_id']);
        $this->user = $user;
        $this->loaded = true;
        $this->organizationLoaded = false;
    }

    /** Signs out the user's other sessions and keeps this one. */
    public function signOutOtherSessions(): void
    {
        $user = $this->requireUser();
        $_SESSION['session_version'] = $this->users->bumpSessionVersion($user['id']);
    }

    /** Re-read the user after their record changed during this request. */
    public function refresh(): void
    {
        $this->loaded = false;
        $this->organizationLoaded = false;
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            session_destroy();
        }
        $this->user = null;
        $this->organization = null;
        $this->loaded = true;
        $this->organizationLoaded = true;
    }
}
