<?php

// Load Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Keep the session cookie away from page scripts and cross-site requests
    session_set_cookie_params(['path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

// Initialize the application
use Core\Application;

$app = Application::getInstance();

// Log errors instead of printing them into JSON responses (check Apache's error log)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set timezone
date_default_timezone_set('Asia/Manila');

// Set request timeout
set_time_limit(1000);

// Handle CORS for API requests
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = [
    'http://localhost:5173',  // SvelteKit dev server
    'http://localhost:3000',  // Alternative dev port
    'http://127.0.0.1:5173',
    'http://127.0.0.1:3000',
    'http://localhost',       // XAMPP default
    'http://127.0.0.1'
];

if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
} else {
    // For development, allow all origins
    header('Access-Control-Allow-Origin: *');
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 86400'); // Cache preflight for 24 hours

// Handle preflight requests
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
