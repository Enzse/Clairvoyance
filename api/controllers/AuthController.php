<?php

namespace Controllers;

use Core\Auth;
use Core\Request;
use Core\Response;
use Services\AccountEmailService;
use Services\AccountService;

class AuthController
{
    private Auth $auth;
    private AccountService $accounts;
    private AccountEmailService $emails;

    public function __construct(Auth $auth, AccountService $accounts, AccountEmailService $emails)
    {
        $this->emails = $emails;
        $this->auth = $auth;
        $this->accounts = $accounts;
    }

    public function register(Request $request): Response
    {
        return Response::created($this->accounts->register($request->body, $request->ip));
    }

    public function login(Request $request): array
    {
        return $this->accounts->login($request->body, $request->ip);
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

    /** POST /auth/verify-email {token}: works signed in or not */
    public function verifyEmail(Request $request): array
    {
        $this->emails->verify($request->body);
        return $this->auth->user() ? $this->accounts->me() : [];
    }

    /** POST /auth/verify-email/resend */
    public function resendVerification(): array
    {
        $this->emails->resendVerification();
        return [];
    }

    /** POST /auth/forgot-password {email}: the same answer whether or not the account exists */
    public function forgotPassword(Request $request): array
    {
        $this->emails->forgotPassword($request->body, $request->ip);
        return [];
    }

    /** POST /auth/reset-password {token, password}: signs in */
    public function resetPassword(Request $request): array
    {
        $this->emails->resetPassword($request->body);
        return $this->accounts->me();
    }

    /** DELETE /auth/me {password}: delete your account and sign out */
    public function delete(Request $request): array
    {
        $this->accounts->delete($request->body);
        return [];
    }
}
