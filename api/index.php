<?php

/**
 * Front controller: .htaccess sends every request under api/ here, so no other file
 * in this folder is ever served or executed directly.
 */

require_once __DIR__ . '/bootstrap.php';

use Core\App;
use Core\Environment;
use Core\Request;

// CORS for the SvelteKit dev server(s) listed in ALLOWED_ORIGINS
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = array_map('trim', explode(',', Environment::getInstance()->get('ALLOWED_ORIGINS', '')));
if ($origin !== '' && in_array($origin, $allowed, true)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Vary: Origin');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

(new App())->handle(Request::fromGlobals())->send();
