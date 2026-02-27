<?php
session_start();
header('Content-Type: application/json');
require_once '../../db_connect.php';
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); }

try {
    // Optional: query param q for name/email search
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';

    // Determine which customer columns exist
    $possibleCustomerCols = ['full_name','email','phone'];
    $existingCols = [];
    foreach ($possibleCustomerCols as $col) {
        $r = $conn->query("SHOW COLUMNS FROM customer LIKE '".$conn->real_escape_string($col)."'");
        if ($r && $r->num_rows > 0) { $existingCols[] = $col; }
    }

    // Prefer orders.total_amount if present
    $hasTotalAmount = false;
    $colRes = $conn->query("SHOW COLUMNS FROM orders LIKE 'total_amount'");
    if ($colRes && $colRes->num_rows > 0) { $hasTotalAmount = true; }
    $sumExpr = $hasTotalAmount ? 'COALESCE(SUM(o.total_amount),0)' : '0';

    // Build select list with safe aliases for missing fields
    $selectParts = ['c.id'];
    foreach ($possibleCustomerCols as $col) {
        if (in_array($col, $existingCols, true)) {
            $selectParts[] = "c.`$col`";
        } else {
            $selectParts[] = "NULL AS `$col`";
        }
    }
    $selectParts[] = 'COALESCE(COUNT(o.id),0) AS total_orders';
    $selectParts[] = "$sumExpr AS total_spent";

    $sql = 'SELECT ' . implode(', ', $selectParts) . ' FROM customer c LEFT JOIN orders o ON o.customer_id = c.id';

    $types = '';
    $params = [];
    $where = [];

    if ($q !== '') {
        $likeConds = [];
        $likeParamsCount = 0;
        if (in_array('full_name', $existingCols, true)) { $likeConds[] = "c.full_name LIKE CONCAT('%', ?, '%')"; $likeParamsCount++; }
        if (in_array('email', $existingCols, true)) { $likeConds[] = "c.email LIKE CONCAT('%', ?, '%')"; $likeParamsCount++; }
        if (!empty($likeConds)) {
            $where[] = '(' . implode(' OR ', $likeConds) . ')';
            $types .= str_repeat('s', $likeParamsCount);
            for ($i = 0; $i < $likeParamsCount; $i++) { $params[] = $q; }
        }
    }

    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    // GROUP BY id and any existing customer columns we selected
    $groupBy = ['c.id'];
    foreach ($existingCols as $col) { $groupBy[] = "c.`$col`"; }
    $sql .= ' GROUP BY ' . implode(', ', $groupBy) . ' ORDER BY c.id DESC';

    $stmt = $conn->prepare($sql);
    if (!empty($params)) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Normalize nulls
    foreach ($rows as &$r) {
        $r['total_orders'] = (int)($r['total_orders'] ?? 0);
        $r['total_spent'] = (float)($r['total_spent'] ?? 0);
    }

    echo json_encode(['success' => true, 'customers' => $rows]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'error_detail' => $e->getMessage()]);
}
