<?php
/**
 * Main entry point for the Clairvoyance KPI API
 */

// CORS: Allow credentials and set origin dynamically for local dev
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin && preg_match('/^http:\/\/localhost(:[0-9]+)?$/', $origin)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// For debugging - log all requests
error_log("API Request: " . $_SERVER['REQUEST_URI']);

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Load dependencies
require_once __DIR__ . '/vendor/autoload.php'; // Composer autoloader
require_once __DIR__ . '/config/database.php';

// Initialize request processing
$request = $_SERVER['REQUEST_URI'];

// Check if there's a query parameter called 'request' from .htaccess
if (isset($_GET['request'])) {
    // This means .htaccess is correctly rewriting the URL
    $requestPath = $_GET['request'];
    error_log("Request from GET parameter: " . $requestPath);
    $request = explode('/', $requestPath);
} else {
    // Parse the URL manually
    $request = explode('?', $request)[0]; // Remove query parameters
    $request = trim($request, '/');
    error_log("Parsed request path: " . $request);
    
    // Split the path into segments
    $request = explode('/', $request);
}

// Debug - show request segments
error_log("Request segments: " . print_r($request, true));

// Remove path segments leading to 'api' if they exist
$apiPos = array_search('api', $request);
if ($apiPos !== false) {
    // Remove everything up to and including 'api'
    $request = array_slice($request, $apiPos + 1);
    error_log("Removed path prefix, new request: " . print_r($request, true));
}

// Route the request to the appropriate file
try {
    $resource = $request[0] ?? '';

    if (empty($resource)) {
        // Default route
        echo json_encode(['status' => 'success', 'message' => 'Welcome to Clairvoyance KPI API']);
        exit();
    }

    // Sanitize the resource name to prevent directory traversal
    $resource = preg_replace('/[^a-zA-Z0-9_\-]/', '', $resource);
    $routeFile = __DIR__ . '/routes/' . $resource . '.php';

    if (file_exists($routeFile)) {
        error_log("Routing to: " . $routeFile);
        require_once $routeFile;
    } else {
        error_log("Resource not found: " . $resource);
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Resource not found']);
    }
} catch (Throwable $e) {
    // Details go to the error log only; clients get a generic message
    error_log("API Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}