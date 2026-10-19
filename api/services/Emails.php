<?php

namespace Services;

use Core\Environment;
use Core\Mail\Message;

/** The emails the app sends, each as plain text plus a simple HTML version. */
class Emails
{
    private string $appUrl;

    public function __construct(Environment $env)
    {
        $this->appUrl = rtrim((string)$env->get('APP_URL'), '/');
    }

    public function link(string $path): string
    {
        return $this->appUrl . $path;
    }

    public function verifyEmail(string $to, ?string $name, string $token): Message
    {
        return $this->compose($to, 'Confirm your email address', $name, [
            'Confirm that this is your email address to finish setting up your Clairvoyance account.',
        ], 'Confirm email address', $this->link("/verify-email/{$token}"), 'The link works for 48 hours. If you didn\'t create an account, ignore this email.');
    }

    public function resetPassword(string $to, ?string $name, string $token): Message
    {
        return $this->compose($to, 'Reset your password', $name, [
            'Someone asked to reset the password for your Clairvoyance account. Choose a new one here:',
        ], 'Choose a new password', $this->link("/reset-password/{$token}"), 'The link works for 1 hour and only once. If you didn\'t ask for this, ignore this email: your password stays the same.');
    }

    public function invitation(string $to, string $organization, ?string $invitedBy, string $token, string $expiresAt): Message
    {
        $who = $invitedBy ? "{$invitedBy} invited you" : 'You have been invited';
        return $this->compose($to, "Join {$organization} on Clairvoyance", null, [
            "{$who} to join {$organization} on Clairvoyance, where the team tracks its KPIs.",
            "Sign in or create an account with this email address ({$to}) to accept.",
        ], "Join {$organization}", $this->link("/invite/{$token}"), 'The invitation expires on ' . date('j F Y', strtotime($expiresAt)) . '.');
    }

    /** @param string[] $paragraphs */
    private function compose(string $to, string $subject, ?string $name, array $paragraphs, string $action, string $url, string $footnote): Message
    {
        $greeting = $name ? "Hi {$name}," : 'Hi,';
        $text = implode("\n\n", [$greeting, ...$paragraphs, "{$action}: {$url}", $footnote, '— Clairvoyance']);

        $e = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $body = '<p>' . $e($greeting) . '</p>';
        foreach ($paragraphs as $p) {
            $body .= '<p>' . $e($p) . '</p>';
        }
        $html = '<!doctype html><html><body style="margin:0;padding:24px;background:#f4f6f9;font-family:Arial,Helvetica,sans-serif;color:#1c2533">'
            . '<div style="max-width:520px;margin:0 auto;background:#ffffff;border-radius:8px;padding:28px;line-height:1.5;font-size:15px">'
            . '<p style="margin:0 0 20px;font-weight:bold;color:#0c2340;font-size:17px">Clairvoyance</p>'
            . $body
            . '<p style="margin:24px 0"><a href="' . $e($url) . '" style="background:#0c2340;color:#ffffff;text-decoration:none;padding:11px 18px;border-radius:6px;display:inline-block;font-weight:bold">' . $e($action) . '</a></p>'
            . '<p style="font-size:13px;color:#5b6576">Or paste this link into your browser:<br><span style="word-break:break-all">' . $e($url) . '</span></p>'
            . '<p style="font-size:13px;color:#5b6576">' . $e($footnote) . '</p>'
            . '</div></body></html>';

        return new Message($to, $subject, $text, $html);
    }
}
