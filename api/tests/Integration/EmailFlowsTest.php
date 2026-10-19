<?php

namespace Tests\Integration;

/** Email verification, password reset and emailed invitations, with a mail server set up. */
class EmailFlowsTest extends ApiTestCase
{
    private static int $admin;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$mailer->configured = true;
        self::$admin = (new self())->registerAndLogin('ana@example.com', 'Bean There');
    }

    public function test1SigningUpSendsAConfirmationLink(): void
    {
        $this->actingAs(self::$admin);
        $message = self::$mailer->last();
        $this->assertSame('ana@example.com', $message->to);
        $this->assertTrue(strpos($message->text, 'http://localhost:5173/verify-email/') !== false, $message->text);

        $me = $this->ok('GET', '/auth/me');
        $this->assertSame(false, $me['email_verified']);
        $this->assertSame(true, $me['email_delivery']);
    }

    public function test2UnconfirmedAdminsInvitationsAreNotEmailed(): void
    {
        $this->actingAs(self::$admin);
        $before = count(self::$mailer->sent);
        $invite = $this->ok('POST', '/invitations', [], ['email' => 'ben@example.com']);
        $this->assertSame(false, $invite['emailed']);
        $this->assertSame('unverified', $invite['email_problem']);
        $this->assertTrue(strlen($invite['token']) > 20, 'the link can still be copied');
        $this->assertSame($before, count(self::$mailer->sent));
    }

    public function test3ResendingReplacesTheLinkAndConfirmingWorksSignedOut(): void
    {
        $this->actingAs(self::$admin);
        $first = self::$mailer->tokenFrom('/verify-email/');
        $this->ok('POST', '/auth/verify-email/resend');
        $second = self::$mailer->tokenFrom('/verify-email/');
        $this->assertTrue($first !== $second);

        $this->actingAs(null);
        $this->assertStatus(410, $this->call('POST', '/auth/verify-email', [], ['token' => $first]));
        $this->ok('POST', '/auth/verify-email', [], ['token' => $second]);
        $this->assertStatus(410, $this->call('POST', '/auth/verify-email', [], ['token' => $second]), 'used once');

        $this->actingAs(self::$admin);
        $this->assertSame(true, $this->ok('GET', '/auth/me')['email_verified']);
    }

    public function test4ConfirmedAdminsInvitationsAreEmailedAndAcceptingConfirms(): void
    {
        $this->actingAs(self::$admin);
        $invite = $this->ok('POST', '/invitations', [], ['email' => 'ben@example.com']);
        $this->assertSame(true, $invite['emailed']);
        $message = self::$mailer->last();
        $this->assertSame('ben@example.com', $message->to);
        $this->assertSame('Join Bean There on Clairvoyance', $message->subject);
        $this->assertSame($invite['token'], self::$mailer->tokenFrom('/invite/'));

        $ben = $this->registerAndLogin('ben@example.com', null);
        $this->assertSame(false, $this->ok('GET', '/auth/me')['email_verified']);
        $me = $this->ok('POST', '/invitations/accept', [], ['token' => $invite['token']]);
        $this->assertSame(true, $me['email_verified']);
        $this->assertSame('Bean There', $me['organization']['name']);
        $this->assertTrue($ben > 0);
    }

    public function test5ForgotPasswordAnswersTheSameForUnknownEmails(): void
    {
        $this->actingAs(null);
        $before = count(self::$mailer->sent);
        $this->ok('POST', '/auth/forgot-password', [], ['email' => 'nobody@example.com']);
        $this->assertSame($before, count(self::$mailer->sent));

        $this->ok('POST', '/auth/forgot-password', [], ['email' => 'Ana@Example.com']);
        $this->assertSame('ana@example.com', self::$mailer->last()->to);
        $this->assertSame('Reset your password', self::$mailer->last()->subject);
    }

    public function test6ResettingSignsInAndSignsOutEverywhereElse(): void
    {
        $this->actingAs(self::$admin);
        $oldSession = $_SESSION;
        $token = self::$mailer->tokenFrom('/reset-password/');

        $this->actingAs(null);
        $short = $this->call('POST', '/auth/reset-password', [], ['token' => $token, 'password' => 'short']);
        $this->assertStatus(400, $short);
        $me = $this->ok('POST', '/auth/reset-password', [], ['token' => $token, 'password' => 'brand-new-password']);
        $this->assertSame('ana@example.com', $me['email']);
        $this->assertStatus(410, $this->call('POST', '/auth/reset-password', [], ['token' => $token, 'password' => 'another-password']));

        $_SESSION = $oldSession;
        $this->assertStatus(401, $this->call('GET', '/auth/me'));
        $this->actingAs(null);
        $this->ok('POST', '/auth/login', [], ['email' => 'ana@example.com', 'password' => 'brand-new-password']);
    }

    public function test7ResetLinksExpire(): void
    {
        $this->actingAs(null);
        $this->ok('POST', '/auth/forgot-password', [], ['email' => 'ben@example.com']);
        self::$db->exec("UPDATE user_tokens SET expires_at = '2000-01-01 00:00:00' WHERE purpose = 'reset_password'");
        $token = self::$mailer->tokenFrom('/reset-password/');
        $this->assertStatus(410, $this->call('POST', '/auth/reset-password', [], ['token' => $token, 'password' => 'brand-new-password']));
    }

    public function test8ForgotPasswordIsThrottledPerEmail(): void
    {
        $this->actingAs(null);
        // ben@ already asked once in test 7
        $this->ok('POST', '/auth/forgot-password', [], ['email' => 'ben@example.com']);
        $this->ok('POST', '/auth/forgot-password', [], ['email' => 'ben@example.com']);
        $this->assertStatus(429, $this->call('POST', '/auth/forgot-password', [], ['email' => 'ben@example.com']));
    }

    public function test9AFailingMailServerDoesNotBreakSignUp(): void
    {
        self::$mailer->failing = true;
        try {
            $this->registerAndLogin('carla@example.com', null);
            $this->assertSame(false, $this->ok('GET', '/auth/me')['email_verified']);
        } finally {
            self::$mailer->failing = false;
        }
    }

    public function test9bWithoutAMailServerAddressesCountAsConfirmed(): void
    {
        self::$mailer->configured = false;
        try {
            $this->registerAndLogin('dev@example.com', null);
            $me = $this->ok('GET', '/auth/me');
            $this->assertSame(true, $me['email_verified']);
            $this->assertSame(false, $me['email_delivery']);
            $this->assertStatus(503, $this->call('POST', '/auth/forgot-password', [], ['email' => 'dev@example.com']));
        } finally {
            self::$mailer->configured = true;
        }
    }
}
