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

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$ticket_id = isset($input['ticket_id']) ? (int)$input['ticket_id'] : 0;
$status = trim((string)($input['status'] ?? ''));
$allowed = ['open','in_progress','resolved','closed'];
if (!$ticket_id || !in_array($status, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'Invalid ticket_id or status']);
    exit;
}

try {
    // Ensure the ticket belongs to this restaurant via its order
    $check = $conn->prepare('SELECT 1 FROM support_tickets t JOIN orders o ON o.id = t.order_id WHERE t.id = ? AND o.restaurant_id = ?');
    $check->bind_param('ii', $ticket_id, $restaurant_id);
    $check->execute();
    if ($check->get_result()->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['success'=>false,'error'=>'Forbidden']);
        exit;
    }

    $upd = $conn->prepare('UPDATE support_tickets SET status = ? WHERE id = ?');
    $upd->bind_param('si', $status, $ticket_id);
    $upd->execute();

    echo json_encode(['success'=>true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Server error','error_detail'=>$e->getMessage()]);
}
