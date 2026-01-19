<?php
// Main API entry point
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Parse the request
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);

// Remove base path to get actual API endpoint
$api_base = '/bsdo/api';
if (strpos($path, $api_base) === 0) {
    $path = substr($path, strlen($api_base));
}

// Include config
require_once __DIR__ . '/../config.php';

// Route the request
switch ($path) {
    case '/auth/login':
        require_once 'auth/login.php';
        break;
    case '/auth/register':
        require_once 'auth/register.php';
        break;
    case '/auth/logout':
        require_once 'auth/logout.php';
        break;
    case '/products/list':
        require_once 'products/list.php';
        break;
    case '/products/detail':
        require_once 'products/detail.php';
        break;
    case '/users/profile':
        require_once 'users/profile.php';
        break;
    case '/inquiries/create':
        require_once 'inquiries/create.php';
        break;
    case '/orders/create':
        require_once 'orders/create.php';
        break;
    case '/live_streams/list':
        require_once 'live_streams/list.php';
        break;
    case '/cart/add':
        require_once 'cart/add.php';
        break;
    case '/cart/remove':
        require_once 'cart/remove.php';
        break;
    case '/cart/list':
        require_once 'cart/list.php';
        break;
    default:
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Endpoint not found'
        ]);
        break;
}
?>