<?php

namespace Core\Mail;

interface Mailer
{
    /** False when no mail server is set up: nothing can be emailed. */
    public function isConfigured(): bool;

    /** @throws \RuntimeException when the message can't be handed to the mail server */
    public function send(Message $message): void;
}
