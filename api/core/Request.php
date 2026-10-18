<?php

namespace Core;

class Request
{
    public string $method;
    public string $path;
    public array $query;
    public array $body;
    public array $files;

    public function __construct(string $method, string $path, array $query = [], array $body = [], array $files = [])
    {
        $this->method = strtoupper($method);
        $this->path = '/' . trim($path, '/');
        $this->query = $query;
        $this->body = $body;
        $this->files = $files;
    }

    public static function fromGlobals(): self
    {
        // Path relative to the API folder, e.g. /clairvoyance-v3/api/kpis/3 -> /kpis/3
        $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        if ($base !== '' && strpos($uriPath, $base) === 0) {
            $uriPath = substr($uriPath, strlen($base));
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $body = json_decode(file_get_contents('php://input'), true);
            if (!is_array($body)) {
                $body = [];
            }
        } else {
            $body = $_POST;
        }

        return new self($_SERVER['REQUEST_METHOD'] ?? 'GET', $uriPath, $_GET, $body, $_FILES);
    }

    /** Value from the JSON/form body. */
    public function input(string $key, $default = null)
    {
        return $this->body[$key] ?? $default;
    }

    public function queryParam(string $key, ?string $default = null): ?string
    {
        $value = $this->query[$key] ?? $default;
        return is_string($value) ? trim($value) : $default;
    }

    public function requiredQuery(string $key): string
    {
        $value = $this->queryParam($key);
        if ($value === null || $value === '') {
            throw HttpException::badRequest("Missing query parameter: {$key}");
        }
        return $value;
    }

    public function queryInt(string $key, bool $required = true): ?int
    {
        $value = $required ? $this->requiredQuery($key) : $this->queryParam($key);
        if ($value === null || $value === '') {
            return null;
        }
        if (!ctype_digit($value)) {
            throw HttpException::badRequest("Query parameter {$key} must be a positive integer");
        }
        return (int)$value;
    }
}
