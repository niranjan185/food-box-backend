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

// Detect existing columns in restaurant table
$desired = ['restaurant_name','owner_name','email','phone','cuisine_type','address','opening_time','closing_time','logo_url','status','lat','lng'];
$exists = [];
if ($res = $conn->query("SHOW COLUMNS FROM restaurant")) {
    while ($row = $res->fetch_assoc()) { $exists[$row['Field']] = true; }
}

$setParts = [];
$params = [];
$types = '';

foreach ($desired as $f) {
    if (!empty($exists[$f]) && array_key_exists($f, $input)) {
        $setParts[] = "$f = ?";
        $params[] = $input[$f] === '' ? null : $input[$f];
        $types .= 's';
    }
}

if (empty($setParts)) {
    echo json_encode(['success' => true]);
    exit;
}

try {
    $sql = 'UPDATE restaurant SET ' . implode(', ', $setParts) . ' WHERE id = ?';
    $types .= 'i';
    $params[] = $restaurant_id;

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'error_detail' => $e->getMessage()]);
}
