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

$name = trim($input['name'] ?? '');
$description = trim($input['description'] ?? '');
$price = isset($input['price']) ? (float)$input['price'] : null;
$category = trim($input['category'] ?? '');
$image_url = trim($input['image_url'] ?? '');
$is_available = isset($input['is_available']) ? (int)!!$input['is_available'] : 1;

if ($name === '' || $price === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Name and price are required']);
    exit;
}

try {
    $stmt = $conn->prepare('INSERT INTO menu_items (restaurant_id, name, description, price, category, image_url, is_available) VALUES (?,?,?,?,?,?,?)');
    $stmt->bind_param('issdssi', $restaurant_id, $name, $description, $price, $category, $image_url, $is_available);
    $stmt->execute();

    echo json_encode(['success' => true, 'item_id' => $stmt->insert_id]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
