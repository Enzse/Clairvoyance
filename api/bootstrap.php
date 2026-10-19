<?php

require_once __DIR__ . '/autoload.php';

// Log errors to storage/logs/api.log instead of printing them into JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
date_default_timezone_set('UTC');

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    $env = Core\Environment::getInstance();
    $storage = __DIR__ . '/storage';
    foreach (['sessions', 'logs'] as $dir) {
        if (!is_dir("{$storage}/{$dir}")) {
            mkdir("{$storage}/{$dir}", 0700, true);
        }
    }
    ini_set('error_log', "{$storage}/logs/api.log");

    // Sessions live in our own folder: shared hosts clean the system temp folder on their own
    // schedule, which would sign people out at random. Signed in for up to 7 days.
    $lifetime = 7 * 86400;
    ini_set('session.save_path', "{$storage}/sessions");
    ini_set('session.gc_maxlifetime', (string)$lifetime);
    ini_set('session.gc_probability', '1');
    ini_set('session.gc_divisor', '100');
    ini_set('session.use_strict_mode', '1');
    session_name('clairvoyance_session');
    // Keep the cookie away from page scripts and cross-site requests, and off plain HTTP in production
    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => '/',
        'secure' => strncmp($env->get('APP_URL'), 'https://', 8) === 0,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
