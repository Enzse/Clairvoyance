<?php

namespace Core;

/**
 * JSON response. Controllers may return plain arrays (sent as 200) or a Response
 * when they need another status code.
 */
class Response
{
    public int $status;
    public array $payload;

    public function __construct(int $status, array $payload)
    {
        $this->status = $status;
        $this->payload = $payload;
    }

    public static function ok($data = null): self
    {
        return new self(200, ['success' => true, 'data' => $data]);
    }

    public static function created($data = null): self
    {
        return new self(201, ['success' => true, 'data' => $data]);
    }

    public static function error(int $status, string $message, array $details = []): self
    {
        $payload = ['success' => false, 'error' => $message];
        if ($details) {
            $payload['details'] = $details;
        }
        return new self($status, $payload);
    }

    public function send(): void
    {
        http_response_code($this->status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
