<?php
header('Content-Type: application/json');
require_once '../../db_connect.php';
require_once __DIR__ . '/session_helper.php';

// Ensure admin authentication
requireAdminAuth();

try {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $ticketId = isset($input['ticket_id']) ? (int)$input['ticket_id'] : 0;
    $status = trim((string)($input['status'] ?? ''));
    $allowed = ['open','in_progress','resolved','closed'];

    if ($ticketId <= 0 || !in_array($status, $allowed, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid ticket_id or status']);
        exit;
    }

    $stmt = $conn->prepare('UPDATE support_tickets SET status = ? WHERE id = ?');
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param('si', $status, $ticketId);
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
