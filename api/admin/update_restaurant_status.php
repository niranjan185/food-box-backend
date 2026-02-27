<?php
session_start();
header('Content-Type: application/json');
require_once '../../db_connect.php';
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); }

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$restaurant_id = isset($input['restaurant_id']) ? (int)$input['restaurant_id'] : 0;
$action = isset($input['action']) ? strtolower(trim($input['action'])) : '';

if (!$restaurant_id || !in_array($action, ['approve','reject','pending'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

try {
    // Ensure is_verified exists
    $colRes = $conn->query("SHOW COLUMNS FROM restaurant LIKE 'is_verified'");
    if (!$colRes || $colRes->num_rows === 0) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'is_verified column missing on restaurant table']);
        exit;
    }

    // Map action to code (0=pending,1=approved,2=rejected)
    $map = ['pending' => 0, 'approve' => 1, 'reject' => 2];
    $code = $map[$action];

    $stmt = $conn->prepare('UPDATE restaurant SET is_verified = ? WHERE id = ?');
    $stmt->bind_param('ii', $code, $restaurant_id);
    $stmt->execute();

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'error_detail' => $e->getMessage()]);
}
