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

try {
    $stmt = $conn->prepare("SELECT id, name, description, price, category, image_url, is_available FROM menu_items WHERE restaurant_id = ? ORDER BY id DESC");
    $stmt->bind_param('i', $restaurant_id);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode(['items' => $items]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
