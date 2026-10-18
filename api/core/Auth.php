<?php

namespace Core;

use Repositories\UserRepository;

/**
 * The logged-in user. Only the user id is kept in the session; the user is re-read
 * once per request, so deleted accounts and role changes take effect immediately.
 */
class Auth
{
    private UserRepository $users;
    private bool $loaded = false;
    private ?array $user = null;

    public function __construct(UserRepository $users)
    {
        $this->users = $users;
    }

    public function user(): ?array
    {
        if (!$this->loaded) {
            $id = $_SESSION['user_id'] ?? null;
            $this->user = $id ? $this->users->find((int)$id) : null;
            if ($id && !$this->user) {
                unset($_SESSION['user_id']);
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

    public function requireAdmin(): array
    {
        $user = $this->requireUser();
        if ($user['role'] !== 'admin') {
            throw HttpException::forbidden('Only admins can do this');
        }
        return $user;
    }

    public function isAdmin(): bool
    {
        return ($this->user()['role'] ?? null) === 'admin';
    }

    public function login(array $user): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true); // prevents session fixation
        }
        $_SESSION['user_id'] = $user['id'];
        $this->user = $user;
        $this->loaded = true;
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
    }
}
