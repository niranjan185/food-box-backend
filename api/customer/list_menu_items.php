<?php
session_start();
header('Content-Type: application/json');
require_once '../../db_connect.php';
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); }

try {
    $restaurant_id = isset($_GET['restaurant_id']) ? (int)$_GET['restaurant_id'] : 0;
    if ($restaurant_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'restaurant_id is required']);
        exit;
    }

    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $category = isset($_GET['category']) ? trim($_GET['category']) : '';
    $diet = isset($_GET['diet']) ? trim($_GET['diet']) : '';

    $sql = "SELECT mi.id, mi.restaurant_id, mi.name, mi.description, mi.price, mi.category, mi.image_url, mi.is_available
            FROM menu_items mi
            WHERE mi.restaurant_id = ?";
    $types = 'i';
    $params = [$restaurant_id];

    if ($search !== '') {
        $sql .= " AND (mi.name LIKE CONCAT('%', ?, '%') OR mi.description LIKE CONCAT('%', ?, '%'))";
        $types .= 'ss';
        $params[] = $search;
        $params[] = $search;
    }
    if ($category !== '') {
        $sql .= " AND mi.category = ?";
        $types .= 's';
        $params[] = $category;
    }
    // Optional diet filter if column exists
    $dietColExists = false;
    $res = $conn->query("SHOW COLUMNS FROM menu_items LIKE 'diet_type'");
    if ($res && $res->num_rows > 0) { $dietColExists = true; }
    if ($dietColExists && $diet !== '') {
        $sql .= " AND mi.diet_type = ?";
        $types .= 's';
        $params[] = $diet;
    }

    $sql .= " ORDER BY mi.id DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode(['success' => true, 'menu_items' => $rows]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'error_detail' => $e->getMessage()]);
}
