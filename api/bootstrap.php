<?php

require_once __DIR__ . '/vendor/autoload.php';

// Log errors instead of printing them into JSON responses (check Apache's error log)
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
date_default_timezone_set('UTC');

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    // Keep the session cookie away from page scripts and cross-site requests
    session_set_cookie_params(['path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
