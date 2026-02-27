<?php
session_start();
header('Content-Type: application/json');
require_once '../../db_connect.php';

if (!isset($_SESSION['restaurant_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$restaurant_id = (int)$_SESSION['restaurant_id'];
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$current_password = $input['current_password'] ?? '';
$new_password = $input['new_password'] ?? '';

if ($current_password === '' || $new_password === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing fields']);
    exit;
}

try {
    $stmt = $conn->prepare('SELECT password FROM restaurant WHERE id = ?');
    $stmt->bind_param('i', $restaurant_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row || !password_verify($current_password, $row['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Current password is incorrect']);
        exit;
    }

    $hash = password_hash($new_password, PASSWORD_DEFAULT);
    $upd = $conn->prepare('UPDATE restaurant SET password = ? WHERE id = ?');
    $upd->bind_param('si', $hash, $restaurant_id);
    $upd->execute();

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
