<?php
session_start();
header('Content-Type: application/json');
require_once '../../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$customer_id = (int)$_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$current_password = isset($input['current_password']) ? $input['current_password'] : null;
$new_password = isset($input['new_password']) ? $input['new_password'] : null;

if (!$current_password || !$new_password) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing fields']);
    exit;
}

try {
    // Fetch current hash
    $stmt = $conn->prepare('SELECT password FROM customer WHERE id = ?');
    $stmt->bind_param('i', $customer_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res || !$res->num_rows) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Customer not found']);
        exit;
    }
    $row = $res->fetch_assoc();
    $hash = $row['password'];

    if (!password_verify($current_password, $hash)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Current password is incorrect']);
        exit;
    }

    if (strlen($new_password) < 6) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'New password must be at least 6 characters']);
        exit;
    }

    $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
    $upd = $conn->prepare('UPDATE customer SET password = ? WHERE id = ?');
    $upd->bind_param('si', $new_hash, $customer_id);
    $upd->execute();

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'error_detail' => $e->getMessage()]);
}
