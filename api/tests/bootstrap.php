<?php

/**
 * Test Bootstrap
 * 
 * This file sets up the testing environment for our model tests.
 */

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Set up test environment
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone for consistent testing
date_default_timezone_set('UTC');

// Tests delete rows, so they run against a separate database (override in api/.env.test).
// This must happen before config/database.php is loaded, since it reads DB_NAME once.
require_once __DIR__ . '/../config/environment.php';
$env = Environment::getInstance();
$env->set('DB_NAME', 'clairvoyance_v3_test');

if (file_exists(__DIR__ . '/../.env.test')) {
    $lines = file(__DIR__ . '/../.env.test', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($key, $value) = explode('=', $line, 2);
        $env->set(trim($key), trim($value));
    }
}

if (substr($env->get('DB_NAME'), -5) !== '_test') {
    fwrite(STDERR, "Refusing to run: DB_NAME '{$env->get('DB_NAME')}' must end in _test.\n");
    exit(1);
}

// Initialize the application
use Core\Application;

$app = Application::getInstance();

echo "🧪 Test Environment Initialized\n";
echo "📦 Application loaded successfully\n";
echo "🔗 Dependency injection container ready\n\n";