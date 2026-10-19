<?php

namespace Core;

/**
 * An error meant for the client: its message and details are sent in the response.
 * Anything else that is thrown is logged and reported as a generic 500.
 */
class HttpException extends \RuntimeException
{
    private int $status;
    private array $details;

    public function __construct(int $status, string $message, array $details = [])
    {
        parent::__construct($message);
        $this->status = $status;
        $this->details = $details;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getDetails(): array
    {
        return $this->details;
    }

    public static function badRequest(string $message, array $details = []): self
    {
        return new self(400, $message, $details);
    }

    public static function forbidden(string $message = 'You do not have access to this'): self
    {
        return new self(403, $message);
    }

    public static function notFound(string $message = 'Not found'): self
    {
        return new self(404, $message);
    }

    public static function conflict(string $message): self
    {
        return new self(409, $message);
    }
}
