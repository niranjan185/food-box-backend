<?php
require_once __DIR__ . '/session_config.php';
require_once '../db_connect.php';

// CORS for credentialed requests
header('Access-Control-Allow-Credentials: true');
$__origin = $_SERVER['HTTP_ORIGIN'] ?? ((isset($_SERVER['HTTP_HOST']) ? ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']) : ''));
if ($__origin) {
    header('Access-Control-Allow-Origin: ' . $__origin);
}
header('Vary: Origin');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    // Support JSON or x-www-form-urlencoded
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $email = trim($input['email'] ?? '');
    $password = trim($input['password'] ?? '');

    if ($email === '' || $password === '') {
        throw new Exception('Email and password are required');
    }

    // Hardcoded admin credentials for now
    $adminEmail = 'admin@foodbox.com';
    $adminPassword = 'Admin@123';

    if ($email === $adminEmail && $password === $adminPassword) {
        // Regenerate for security
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['admin_id'] = 1;
        $_SESSION['admin_email'] = $email;
        $_SESSION['admin_login_time'] = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['is_admin'] = true;
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';

        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'session_id' => session_id(),
            'admin' => [ 'id' => 1, 'email' => $email ]
        ]);
        exit;
    }

    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid credentials',
        'session_id' => session_id()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Login failed: ' . $e->getMessage(),
        'session_id' => session_id()
    ]);
}
?>
