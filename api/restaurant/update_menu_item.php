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

$fields = ['name','description','price','category','image_url','is_available'];
$setParts = [];
$params = [];
$types = '';

foreach ($fields as $f) {
    if (array_key_exists($f, $input)) {
        $setParts[] = "$f = ?";
        if ($f === 'price') {
            $params[] = (float)$input[$f];
            $types .= 'd';
        } elseif ($f === 'is_available') {
            $params[] = (int)!!$input[$f];
            $types .= 'i';
        } else {
            $params[] = $input[$f] === '' ? null : $input[$f];
            $types .= 's';
        }
    }
}

if (empty($setParts)) {
    echo json_encode(['success' => true]);
    exit;
}

try {
    // Ensure the item belongs to this restaurant
    $chk = $conn->prepare('SELECT id FROM menu_items WHERE id = ? AND restaurant_id = ?');
    $chk->bind_param('ii', $item_id, $restaurant_id);
    $chk->execute();
    if ($chk->get_result()->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }

    $sql = 'UPDATE menu_items SET ' . implode(', ', $setParts) . ' WHERE id = ?';
    $types .= 'i';
    $params[] = $item_id;

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
