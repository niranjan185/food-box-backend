<?php
session_start();
header('Content-Type: application/json');
require_once '../../db_connect.php';

// Auth: restaurant session
$restaurant_id = $_SESSION['restaurant_id'] ?? null;
if (!$restaurant_id) {
    http_response_code(401);
    echo json_encode(['success'=>false,'error'=>'Unauthorized']);
    exit;
}
$restaurant_id = (int)$restaurant_id;

// Pagination/filter basics
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = min(100, max(10, (int)($_GET['limit'] ?? 25)));
$offset = ($page - 1) * $limit;
$status = isset($_GET['status']) && $_GET['status'] !== '' ? trim($_GET['status']) : null;
$search = isset($_GET['q']) ? trim($_GET['q']) : '';

try {
    // Tickets tied to this restaurant via order_id -> orders.restaurant_id
    // Some tickets may have order_id NULL; those are not shown to restaurants (global support handles them)
    $where = 'WHERE o.restaurant_id = ?';
    $types = 'i';
    $params = [$restaurant_id];
    if ($status) { $where .= ' AND t.status = ?'; $types .= 's'; $params[] = $status; }
    if ($search !== '') {
        $where .= ' AND (t.topic LIKE CONCAT("%", ?, "%") OR t.message LIKE CONCAT("%", ?, "%"))';
        $types .= 'ss';
        $params[] = $search; $params[] = $search;
    }

    $sql = "SELECT t.id, t.customer_id, t.order_id, t.topic, t.message, t.contact_email, t.contact_phone, t.status, t.created_at,
                   c.name AS customer_name, r.restaurant_name
            FROM support_tickets t
            JOIN orders o ON o.id = t.order_id
            LEFT JOIN customers c ON c.id = t.customer_id
            LEFT JOIN restaurant r ON r.id = o.restaurant_id
            $where
            ORDER BY t.created_at DESC
            LIMIT ? OFFSET ?";
    $types .= 'ii';
    $params[] = $limit; $params[] = $offset;

    $stmt = $conn->prepare($sql);
    if (!$stmt) { throw new Exception('Prepare failed: '.$conn->error); }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) { $rows[] = $row; }

    echo json_encode(['success'=>true,'tickets'=>$rows,'page'=>$page,'limit'=>$limit]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Server error','error_detail'=>$e->getMessage()]);
}
