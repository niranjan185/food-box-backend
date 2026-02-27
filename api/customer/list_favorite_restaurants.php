<?php
session_start();
header('Content-Type: application/json');
require_once '../../db_connect.php';
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); }

try {
    $customer_id = 0;
    if (isset($_SESSION['customer_id'])) { $customer_id = (int)$_SESSION['customer_id']; }
    else if (isset($_SESSION['user_id'])) { $customer_id = (int)$_SESSION['user_id']; }
    if ($customer_id <= 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit;
    }

    $q = isset($_GET['q']) ? trim($_GET['q']) : '';

    // Build query of favorite restaurants joined with restaurant table
    $sql = "SELECT r.id, r.restaurant_name, r.owner_name, r.email, r.phone, r.cuisine_type, r.address, r.opening_time, r.closing_time, r.logo_url, r.status, r.is_verified
            FROM customer_favorites cf
            INNER JOIN restaurant r ON r.id = cf.restaurant_id
            WHERE cf.customer_id = ?";

    $types = 'i';
    $params = [$customer_id];

    // Filter to verified and not closed if status exists
    $hasStatus = false;
    $res = $conn->query("SHOW COLUMNS FROM restaurant LIKE 'status'");
    if ($res && $res->num_rows > 0) { $hasStatus = true; }
    $sql .= " AND r.is_verified = 1";
    if ($hasStatus) { $sql .= " AND (r.status IS NULL OR r.status <> 'closed')"; }

    if ($q !== '') {
        $sql .= " AND (r.restaurant_name LIKE CONCAT('%', ?, '%') OR r.cuisine_type LIKE CONCAT('%', ?, '%') OR r.address LIKE CONCAT('%', ?, '%'))";
        $types .= 'sss';
        $params[] = $q; $params[] = $q; $params[] = $q;
    }

    $sql .= ' ORDER BY r.id DESC';

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode(['success' => true, 'restaurants' => $rows]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'error_detail' => $e->getMessage()]);
}
