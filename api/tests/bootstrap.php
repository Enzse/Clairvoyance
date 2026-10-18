<?php

/**
 * Test environment. Tests delete data, so they run against a separate database
 * (override DB_NAME or other settings in api/.env.test) and refuse anything not ending in _test.
 */

require_once __DIR__ . '/../vendor/autoload.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');
date_default_timezone_set('UTC');

// Warnings and notices are bugs: fail the test instead of printing and carrying on
set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$env = Core\Environment::getInstance();
$env->set('DB_NAME', 'clairvoyance_v3_test');

if (file_exists(__DIR__ . '/../.env.test')) {
    foreach (file(__DIR__ . '/../.env.test', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
        [$key, $value] = explode('=', $line, 2);
        $env->set(trim($key), trim($value));
    }
}

if (substr($env->get('DB_NAME'), -5) !== '_test') {
    fwrite(STDERR, "Refusing to run: DB_NAME '{$env->get('DB_NAME')}' must end in _test.\n");
    exit(1);
}
