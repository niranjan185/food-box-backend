<?php
// Include unified session configuration (one directory up from support/)
require_once __DIR__ . '/../session_config.php';

// Set CORS headers
header('Access-Control-Allow-Credentials: true');
// Compute a safe origin (never use * with credentials)
$__origin = $_SERVER['HTTP_ORIGIN'] ?? ((isset($_SERVER['HTTP_HOST']) ? ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']) : ''));
if ($__origin) {
    header('Access-Control-Allow-Origin: ' . $__origin);
}
header('Vary: Origin');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, *');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Check admin authentication
function requireAdminAuth() {
    // Debug helpful context
    error_log('requireAdminAuth: session_id=' . session_id() . ' has admin_id=' . ($_SESSION['admin_id'] ?? 'null'));

    if (!isset($_SESSION['admin_id'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Unauthorized',
            'session_id' => session_id(),
            'session_status' => session_status()
        ]);
        exit;
    }
    // Touch session activity
    $_SESSION['last_activity'] = time();
    return true;
}
?>
