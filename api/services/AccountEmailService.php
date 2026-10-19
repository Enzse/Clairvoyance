<?php

namespace Services;

use Core\Auth;
use Core\HttpException;
use Core\Mail\Mailer;
use Core\RateLimiter;
use Core\Validator;
use Repositories\UserRepository;
use Repositories\UserTokenRepository;

/**
 * Confirming an email address and resetting a forgotten password, both by a one-time link.
 * Without a mail server neither is possible: addresses then count as trusted, and a forgotten
 * password needs scripts/reset_password.php.
 */
class AccountEmailService
{
    private const VERIFY_HOURS = 48;
    private const RESET_HOURS = 1;

    private Auth $auth;
    private UserRepository $users;
    private UserTokenRepository $tokens;
    private Mailer $mailer;
    private Emails $emails;
    private RateLimiter $limiter;

    public function __construct(Auth $auth, UserRepository $users, UserTokenRepository $tokens, Mailer $mailer, Emails $emails, RateLimiter $limiter)
    {
        $this->auth = $auth;
        $this->users = $users;
        $this->tokens = $tokens;
        $this->mailer = $mailer;
        $this->emails = $emails;
        $this->limiter = $limiter;
    }

    public function deliveryEnabled(): bool
    {
        return $this->mailer->isConfigured();
    }

    /** Whether the user's address is confirmed (always true when nothing can be emailed). */
    public function isVerified(array $user): bool
    {
        return $user['email_verified_at'] !== null || !$this->mailer->isConfigured();
    }

    /** Emails a confirmation link. A failure is logged, not shown: the person can resend. */
    public function sendVerification(array $user): void
    {
        if (!$this->mailer->isConfigured() || $user['email_verified_at'] !== null) {
            return;
        }
        $token = $this->newToken($user['id'], 'verify_email', self::VERIFY_HOURS);
        try {
            $this->mailer->send($this->emails->verifyEmail($user['email'], $user['name'], $token));
        } catch (\RuntimeException $e) {
            error_log("Verification email to {$user['email']} failed: {$e->getMessage()}");
        }
    }

    public function resendVerification(): void
    {
        $user = $this->auth->requireUser();
        if ($this->isVerified($user)) {
            return;
        }
        $this->limiter->attempt("verify:{$user['id']}", 3, 3600);
        $this->sendVerification($user);
    }

    /** Works without being signed in: the link may be opened on another device. */
    public function verify(array $input): void
    {
        $token = $this->tokenFrom($input);
        $found = $this->tokens->findUsable('verify_email', hash('sha256', $token));
        if (!$found) {
            throw new HttpException(410, 'This confirmation link has expired or was already used. Sign in to get a new one');
        }
        $this->users->markVerified($found['user_id']);
        $this->tokens->markUsed($found['id']);
        $this->auth->refresh();
    }

    /** Always answers the same way, so it can't be used to find out who has an account. */
    public function forgotPassword(array $input, string $ip): void
    {
        $v = new Validator($input);
        $email = $v->string('email', true);
        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $v->addError('email', 'is not a valid email address');
        }
        $v->check();
        if (!$this->mailer->isConfigured()) {
            throw new HttpException(503, "Password reset by email isn't set up on this server. Ask whoever runs it to reset your password");
        }
        $this->limiter->attempt("forgot-ip:{$ip}", 10, 3600);
        $this->limiter->attempt(RateLimiter::emailBucket('forgot', $email), 3, 3600);

        $user = $this->users->findByEmail(mb_strtolower($email));
        if (!$user) {
            return;
        }
        $token = $this->newToken($user['id'], 'reset_password', self::RESET_HOURS);
        try {
            $this->mailer->send($this->emails->resetPassword($user['email'], $user['name'], $token));
        } catch (\RuntimeException $e) {
            error_log("Password reset email to {$user['email']} failed: {$e->getMessage()}");
        }
    }

    /** Sets the new password, signs out every other session and signs in here. */
    public function resetPassword(array $input): void
    {
        $token = $this->tokenFrom($input);
        $v = new Validator($input);
        $password = $v->secret('password', true);
        if ($password !== null && strlen($password) < AccountService::MIN_PASSWORD) {
            $v->addError('password', 'must be at least ' . AccountService::MIN_PASSWORD . ' characters');
        }
        $v->check();

        $found = $this->tokens->findUsable('reset_password', hash('sha256', $token));
        if (!$found) {
            throw new HttpException(410, 'This reset link has expired or was already used. Ask for a new one');
        }
        $this->users->update($found['user_id'], ['password' => $password]);
        // Receiving the link proves the address is theirs
        $this->users->markVerified($found['user_id']);
        $this->tokens->markUsed($found['id']);
        $this->users->bumpSessionVersion($found['user_id']);
        $this->auth->login($this->users->find($found['user_id']));
    }

    private function newToken(int $userId, string $purpose, int $hours): string
    {
        $token = bin2hex(random_bytes(24));
        $this->tokens->replace($userId, $purpose, hash('sha256', $token), time() + $hours * 3600);
        return $token;
    }

    private function tokenFrom(array $input): string
    {
        $v = new Validator($input);
        $token = $v->string('token', true, 128);
        $v->check();
        return $token;
    }
}
