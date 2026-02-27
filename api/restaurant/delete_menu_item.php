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
$item_id = isset($input['item_id']) ? (int)$input['item_id'] : 0;

if ($item_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid item_id']);
    exit;
}

try {
    // Ensure item belongs to this restaurant
    $chk = $conn->prepare('SELECT id FROM menu_items WHERE id = ? AND restaurant_id = ?');
    $chk->bind_param('ii', $item_id, $restaurant_id);
    $chk->execute();
    if ($chk->get_result()->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }

    $del = $conn->prepare('DELETE FROM menu_items WHERE id = ?');
    $del->bind_param('i', $item_id);
    $del->execute();

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
