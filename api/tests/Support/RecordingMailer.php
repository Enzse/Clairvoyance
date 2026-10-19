<?php

namespace Tests\Support;

use Core\Mail\Mailer;
use Core\Mail\Message;

/** Test double: keeps sent messages instead of delivering them. */
class RecordingMailer implements Mailer
{
    public bool $configured = false;
    public bool $failing = false;
    /** @var Message[] */
    public array $sent = [];

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function send(Message $message): void
    {
        if ($this->failing) {
            throw new \RuntimeException('Mail server unavailable');
        }
        $this->sent[] = $message;
    }

    public function last(): ?Message
    {
        return $this->sent ? $this->sent[count($this->sent) - 1] : null;
    }

    /** The token from the last emailed link with this path prefix, e.g. "/verify-email/". */
    public function tokenFrom(string $pathPrefix): ?string
    {
        foreach (array_reverse($this->sent) as $message) {
            if (preg_match('#' . preg_quote($pathPrefix, '#') . '([0-9a-f]+)#', $message->text, $m)) {
                return $m[1];
            }
        }
        return null;
    }
}
