<?php
session_start();
header('Content-Type: application/json');
require_once '../../db_connect.php';
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); }

try {
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';

    // Detect delivery table columns
    $possibleCols = ['full_name','name','email','phone','is_active','status'];
    $existing = [];
    foreach ($possibleCols as $col) {
        $r = $conn->query("SHOW COLUMNS FROM delivery LIKE '".$conn->real_escape_string($col)."'");
        if ($r && $r->num_rows > 0) { $existing[$col] = true; }
    }

    // Detect orders delivery foreign key column
    $deliveryFK = null;
    foreach (['delivery_id','delivery_partner_id','rider_id','driver_id'] as $fk) {
        $r = $conn->query("SHOW COLUMNS FROM orders LIKE '".$conn->real_escape_string($fk)."'");
        if ($r && $r->num_rows > 0) { $deliveryFK = $fk; break; }
    }

    // Build select
    $select = ['d.id'];
    $nameExpr = 'NULL AS full_name';
    if (!empty($existing['full_name'])) { $nameExpr = 'd.full_name'; }
    else if (!empty($existing['name'])) { $nameExpr = 'd.name AS full_name'; }
    $select[] = $nameExpr;
    $select[] = !empty($existing['email']) ? 'd.email' : 'NULL AS email';
    $select[] = !empty($existing['phone']) ? 'd.phone' : 'NULL AS phone';

    // Active status as tinyint 0/1
    if (!empty($existing['is_active'])) {
        $select[] = 'd.is_active';
    } else if (!empty($existing['status'])) {
        $select[] = 'CASE WHEN d.status IN ("active", "1", 1) THEN 1 ELSE 0 END AS is_active';
    } else {
        $select[] = '0 AS is_active';
    }

    // Total deliveries from orders if FK detected
    if ($deliveryFK) {
        $select[] = "COALESCE(COUNT(o.id),0) AS total_deliveries";
    } else {
        $select[] = '0 AS total_deliveries';
    }

    // Average rating from delivery_reviews
    $select[] = 'COALESCE(AVG(dr.rating),0) AS rating';
    $select[] = 'COALESCE(COUNT(dr.id),0) AS rating_count';

    $sql = 'SELECT ' . implode(', ', $select) . ' FROM delivery d';
    if ($deliveryFK) {
        $sql .= ' LEFT JOIN orders o ON o.`'.$deliveryFK.'` = d.id';
    } else {
        $sql .= ' LEFT JOIN orders o ON 1=0'; // keeps SQL valid for COUNT
    }
    // join delivery_reviews for ratings
    $sql .= ' LEFT JOIN delivery_reviews dr ON dr.delivery_id = d.id';

    $where = [];
    $types = '';
    $params = [];
    if ($q !== '') {
        $likeConds = [];
        $likeCount = 0;
        if (!empty($existing['full_name'])) { $likeConds[] = "d.full_name LIKE CONCAT('%', ?, '%')"; $likeCount++; }
        if (!empty($existing['name'])) { $likeConds[] = "d.name LIKE CONCAT('%', ?, '%')"; $likeCount++; }
        if (!empty($existing['phone'])) { $likeConds[] = "d.phone LIKE CONCAT('%', ?, '%')"; $likeCount++; }
        if (!empty($existing['email'])) { $likeConds[] = "d.email LIKE CONCAT('%', ?, '%')"; $likeCount++; }
        if (!empty($likeConds)) {
            $where[] = '(' . implode(' OR ', $likeConds) . ')';
            $types .= str_repeat('s', $likeCount);
            for ($i=0; $i<$likeCount; $i++) { $params[] = $q; }
        }
    }

    if (!empty($where)) { $sql .= ' WHERE ' . implode(' AND ', $where); }

    $sql .= ' GROUP BY d.id, full_name, email, phone, is_active ORDER BY d.id DESC';

    $stmt = $conn->prepare($sql);
    if (!empty($params)) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Normalize types
    foreach ($rows as &$r) {
        $r['is_active'] = (int)($r['is_active'] ?? 0);
        $r['total_deliveries'] = (int)($r['total_deliveries'] ?? 0);
    }

    echo json_encode(['success' => true, 'partners' => $rows]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'error_detail' => $e->getMessage()]);
}
