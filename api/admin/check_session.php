<?php
require_once __DIR__ . '/../session_config.php';

// Set CORS headers
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, *');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// session_config.php has already started the session

// Check if admin is logged in
if (isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id'])) {
    // Session is valid
    echo json_encode([
        'success' => true,
        'message' => 'Session is valid',
        'admin' => [
            'id' => $_SESSION['admin_id'],
            'email' => $_SESSION['admin_email'] ?? ''
        ]
    ]);
    exit;
}

// If we get here, the user is not logged in
http_response_code(401);
echo json_encode([
    'success' => false,
    'error' => 'Not authenticated',
    'session' => $_SESSION // For debugging
]);
?>
