<?php

namespace Controllers;

use Core\Auth;
use Core\Request;
use Core\Response;
use Services\UserService;

class AuthController
{
    private Auth $auth;
    private UserService $users;

    public function __construct(Auth $auth, UserService $users)
    {
        $this->auth = $auth;
        $this->users = $users;
    }

    public function register(Request $request): Response
    {
        return Response::created($this->users->register($request->body));
    }

    public function login(Request $request): array
    {
        return $this->users->login($request->body);
    }

    public function logout(): array
    {
        $this->auth->logout();
        return [];
    }

    public function me(): array
    {
        return $this->users->me();
    }
}
