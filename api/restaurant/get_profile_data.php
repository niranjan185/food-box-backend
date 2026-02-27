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
    // Detect existing columns to avoid errors on missing fields
    $desired = ['id','restaurant_name','owner_name','email','phone','cuisine_type','address','opening_time','closing_time','logo_url','status','lat','lng'];
    $exists = [];
    if ($res = $conn->query("SHOW COLUMNS FROM restaurant")) {
        while ($row = $res->fetch_assoc()) {
            $exists[$row['Field']] = true;
        }
    }
    $selectCols = [];
    foreach ($desired as $col) {
        if (!empty($exists[$col])) { $selectCols[] = $col; }
    }
    if (empty($selectCols)) { $selectCols = ['id','restaurant_name','phone']; }

    $sql = "SELECT " . implode(", ", $selectCols) . " FROM restaurant WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $restaurant_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];

    // Build a profile with all expected keys (frontend safety)
    $profile = [];
    foreach ($desired as $col) {
        $profile[$col] = array_key_exists($col, $row) ? $row[$col] : null;
    }

    echo json_encode(['profile' => $profile]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'error_detail' => $e->getMessage()]);
}
