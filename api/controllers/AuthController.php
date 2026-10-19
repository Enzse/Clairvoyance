<?php

namespace Controllers;

use Core\Auth;
use Core\Request;
use Core\Response;
use Services\AccountService;

class AuthController
{
    private Auth $auth;
    private AccountService $accounts;

    public function __construct(Auth $auth, AccountService $accounts)
    {
        $this->auth = $auth;
        $this->accounts = $accounts;
    }

    public function register(Request $request): Response
    {
        return Response::created($this->accounts->register($request->body));
    }

    public function login(Request $request): array
    {
        return $this->accounts->login($request->body);
    }

    public function logout(): array
    {
        $this->auth->logout();
        return [];
    }

    public function me(): array
    {
        return $this->accounts->me();
    }

    /** PUT /auth/me {name, current_password, new_password} */
    public function update(Request $request): array
    {
        return $this->accounts->update($request->body);
    }
}
