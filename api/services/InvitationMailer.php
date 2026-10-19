<?php

namespace Services;

use Core\Mail\Mailer;
use Core\RateLimiter;
use Repositories\InvitationRepository;

/**
 * Emails invitation links. Only confirmed addresses can have the app email strangers on their
 * behalf, so an unconfirmed admin's invitations stay links to copy until they confirm.
 */
class InvitationMailer
{
    private Mailer $mailer;
    private Emails $emails;
    private InvitationRepository $invitations;
    private AccountEmailService $verification;
    private RateLimiter $limiter;

    public function __construct(Mailer $mailer, Emails $emails, InvitationRepository $invitations, AccountEmailService $verification, RateLimiter $limiter)
    {
        $this->mailer = $mailer;
        $this->emails = $emails;
        $this->invitations = $invitations;
        $this->verification = $verification;
        $this->limiter = $limiter;
    }

    /**
     * @return array{emailed: bool, email_problem: ?string} why it wasn't emailed, if it wasn't:
     *   'not_configured', 'unverified' or 'failed'
     */
    public function send(array $invite, string $token, array $inviter): array
    {
        if (!$this->mailer->isConfigured()) {
            return ['emailed' => false, 'email_problem' => 'not_configured'];
        }
        if (!$this->verification->isVerified($inviter)) {
            return ['emailed' => false, 'email_problem' => 'unverified'];
        }
        $this->limiter->attempt("invite-email:{$invite['organization_id']}", 100, 86400);
        try {
            $this->mailer->send($this->emails->invitation(
                $invite['email'], $invite['organization_name'], $inviter['name'] ?: $inviter['email'], $token, $invite['expires_at']
            ));
        } catch (\RuntimeException $e) {
            error_log("Invitation email to {$invite['email']} failed: {$e->getMessage()}");
            return ['emailed' => false, 'email_problem' => 'failed'];
        }
        $this->invitations->markEmailed($invite['id']);
        return ['emailed' => true, 'email_problem' => null];
    }
}
