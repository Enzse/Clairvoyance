<?php

namespace Core\Mail;

use Core\Environment;

/**
 * Sends mail through an SMTP server (the hosting mailbox, Brevo, a local catcher such as
 * Mailpit). Supports STARTTLS (MAIL_ENCRYPTION=tls, usually port 587), implicit TLS (ssl,
 * port 465) and plain connections (none, only for local catchers), with AUTH LOGIN.
 */
class SmtpMailer implements Mailer
{
    private const TIMEOUT = 15;

    private Environment $env;
    /** @var resource|null */
    private $socket = null;

    public function __construct(Environment $env)
    {
        $this->env = $env;
    }

    public function isConfigured(): bool
    {
        return trim((string)$this->env->get('MAIL_HOST')) !== '';
    }

    public function send(Message $message): void
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Email is not set up (MAIL_HOST is empty)');
        }
        $host = $this->env->get('MAIL_HOST');
        $port = (int)$this->env->get('MAIL_PORT');
        $encryption = strtolower((string)$this->env->get('MAIL_ENCRYPTION'));
        $user = (string)$this->env->get('MAIL_USER');
        $from = $this->env->get('MAIL_FROM') ?: $user;
        $hostname = parse_url((string)$this->env->get('APP_URL'), PHP_URL_HOST) ?: 'localhost';

        $remote = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . "{$host}:{$port}";
        $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host]]);
        $socket = @stream_socket_client($remote, $errno, $error, self::TIMEOUT, STREAM_CLIENT_CONNECT, $context);
        if (!$socket) {
            throw new \RuntimeException("Can't connect to the mail server {$remote}: {$error}");
        }
        $this->socket = $socket;
        stream_set_timeout($socket, self::TIMEOUT);

        try {
            $this->expect([220]);
            $this->command("EHLO {$hostname}", [250]);
            if ($encryption === 'tls') {
                $this->command('STARTTLS', [220]);
                $method = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT') ? STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT : 0);
                if (!stream_socket_enable_crypto($socket, true, $method)) {
                    throw new \RuntimeException('The mail server refused STARTTLS');
                }
                $this->command("EHLO {$hostname}", [250]);
            }
            if ($user !== '') {
                $this->command('AUTH LOGIN', [334]);
                $this->command(base64_encode($user), [334], 'username');
                $this->command(base64_encode((string)$this->env->get('MAIL_PASS')), [235], 'password');
            }
            $this->command("MAIL FROM:<{$from}>", [250]);
            $this->command("RCPT TO:<{$message->to}>", [250, 251]);
            $this->command('DATA', [354]);
            $this->command($message->toMime($from, (string)$this->env->get('MAIL_FROM_NAME'), $hostname) . "\r\n.", [250], 'message');
            $this->command('QUIT', [221]);
        } finally {
            fclose($socket);
            $this->socket = null;
        }
    }

    /** @param int[] $expected reply codes that mean success */
    private function command(string $line, array $expected, ?string $label = null): void
    {
        if (fwrite($this->socket, $line . "\r\n") === false) {
            throw new \RuntimeException('Lost the connection to the mail server');
        }
        $this->expect($expected, $label ?? strtok($line, ' '));
    }

    /** Reads one reply (possibly several "250-" lines) and checks its code. */
    private function expect(array $expected, string $after = 'connecting'): void
    {
        $reply = '';
        while (($line = fgets($this->socket, 1024)) !== false) {
            $reply .= $line;
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }
        $code = (int)substr($reply, 0, 3);
        if (!in_array($code, $expected, true)) {
            throw new \RuntimeException("Mail server error after {$after}: " . trim($reply ?: 'no reply (timed out)'));
        }
    }
}
