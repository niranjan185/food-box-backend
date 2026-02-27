<?php
session_start();
header('Content-Type: application/json');
require_once '../../db_connect.php';
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); }

try {
    // Optional filter: status=pending|approved|rejected|all
    $status = isset($_GET['status']) ? strtolower(trim($_GET['status'])) : 'pending';
    $where = [];
    $types = '';
    $params = [];

    // Determine if is_verified column exists
    $hasIsVerified = false;
    $colRes = $conn->query("SHOW COLUMNS FROM restaurant LIKE 'is_verified'");
    if ($colRes && $colRes->num_rows > 0) { $hasIsVerified = true; }

    if ($hasIsVerified && $status && $status !== 'all') {
        // Map: pending=0, approved=1, rejected=2 (if 2 does not exist in your data, it's fine; query will return 0 rows)
        $map = ['pending' => 0, 'approved' => 1, 'rejected' => 2];
        if (isset($map[$status])) {
            $where[] = 'is_verified = ?';
            $types .= 'i';
            $params[] = $map[$status];
        }
    }

    // Build safe column list based on existence
    $selectCols = ['id', 'restaurant_name'];
    foreach (['owner_name','owner','email','phone','address','created_at','is_verified'] as $opt) {
        $r = $conn->query("SHOW COLUMNS FROM restaurant LIKE '".$conn->real_escape_string($opt)."'");
        if ($r && $r->num_rows > 0) { $selectCols[] = $opt; }
    }

    $sql = 'SELECT ' . implode(', ', array_map(fn($c) => "`$c`", $selectCols)) . ' FROM restaurant';
    if (!empty($where)) { $sql .= ' WHERE ' . implode(' AND ', $where); }
    $sql .= ' ORDER BY id DESC';

    $stmt = $conn->prepare($sql);
    if (!empty($params)) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode(['success' => true, 'restaurants' => $rows]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'error_detail' => $e->getMessage()]);
}
