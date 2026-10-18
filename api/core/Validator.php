<?php

namespace Core;

/**
 * Reads fields from request input and collects every problem, so the client gets all
 * field errors at once. Missing optional fields return null; use has() for partial updates.
 *
 *   $v = new Validator($request->body);
 *   $name = $v->string('name', true, 255);
 *   $v->check();   // throws a 400 with ['details' => [field => message]]
 */
class Validator
{
    private array $data;
    private array $errors = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function has(string $field): bool
    {
        return array_key_exists($field, $this->data);
    }

    public function string(string $field, bool $required = false, int $max = 255, ?string $pattern = null, ?string $patternHint = null): ?string
    {
        $value = $this->data[$field] ?? null;
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return $this->missing($field, $required);
        }
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            return $this->error($field, 'must be text');
        }
        $value = trim((string)$value);
        if (mb_strlen($value) > $max) {
            return $this->error($field, "must be at most {$max} characters");
        }
        if ($pattern !== null && !preg_match($pattern, $value)) {
            return $this->error($field, $patternHint ?? 'has an invalid format');
        }
        return $value;
    }

    /** Like string() but never trimmed, for passwords. */
    public function secret(string $field, bool $required = false, int $max = 1000): ?string
    {
        $value = $this->data[$field] ?? null;
        if ($value === null || $value === '') {
            return $this->missing($field, $required);
        }
        if (!is_string($value)) {
            return $this->error($field, 'must be text');
        }
        return strlen($value) > $max ? $this->error($field, "must be at most {$max} characters") : $value;
    }

    public function enum(string $field, array $allowed, bool $required = false): ?string
    {
        $value = $this->string($field, $required, 64);
        if ($value !== null && !in_array($value, $allowed, true)) {
            return $this->error($field, 'must be one of: ' . implode(', ', $allowed));
        }
        return $value;
    }

    public function number(string $field, bool $required = false, ?float $min = null, ?float $max = null): ?float
    {
        $value = $this->data[$field] ?? null;
        if ($value === null || $value === '') {
            return $this->missing($field, $required);
        }
        if (!is_numeric($value)) {
            return $this->error($field, 'must be a number');
        }
        $value = (float)$value;
        if (($min !== null && $value < $min) || ($max !== null && $value > $max)) {
            return $this->error($field, 'must be between ' . ($min ?? '-∞') . ' and ' . ($max ?? '∞'));
        }
        return $value;
    }

    public function int(string $field, bool $required = false, ?int $min = null, ?int $max = null): ?int
    {
        $value = $this->data[$field] ?? null;
        if ($value === null || $value === '') {
            return $this->missing($field, $required);
        }
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            return $this->error($field, 'must be a whole number');
        }
        $value = (int)$value;
        if (($min !== null && $value < $min) || ($max !== null && $value > $max)) {
            return $this->error($field, 'must be between ' . ($min ?? '-∞') . ' and ' . ($max ?? '∞'));
        }
        return $value;
    }

    public function bool(string $field, bool $required = false): ?bool
    {
        $value = $this->data[$field] ?? null;
        if ($value === null) {
            return $this->missing($field, $required);
        }
        $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $bool ?? $this->error($field, 'must be true or false');
    }

    public function date(string $field, bool $required = false): ?string
    {
        $value = $this->string($field, $required, 10);
        if ($value !== null && !\Domain\Period::isValidDate($value)) {
            return $this->error($field, 'must be a date in YYYY-MM-DD format');
        }
        return $value;
    }

    public function array(string $field, bool $required = false): ?array
    {
        $value = $this->data[$field] ?? null;
        if ($value === null) {
            return $this->missing($field, $required);
        }
        return is_array($value) ? $value : $this->error($field, 'must be a list');
    }

    /** Record a problem found outside the field readers. */
    public function addError(string $field, string $message): void
    {
        $this->errors[$field] ??= $message;
    }

    public function check(string $message = 'Please correct the highlighted fields'): void
    {
        if ($this->errors) {
            throw HttpException::badRequest($message, $this->errors);
        }
    }

    private function missing(string $field, bool $required)
    {
        if ($required) {
            $this->errors[$field] ??= 'is required';
        }
        return null;
    }

    private function error(string $field, string $message)
    {
        $this->errors[$field] ??= $message;
        return null;
    }
}
