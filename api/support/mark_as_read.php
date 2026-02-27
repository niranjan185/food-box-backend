<?php
header('Content-Type: application/json');
require_once '../../db_connect.php';
require_once __DIR__ . '/session_helper.php';

// Ensure admin authentication
requireAdminAuth();

try {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $ticketId = isset($input['ticket_id']) ? (int)$input['ticket_id'] : 0;
    if ($ticketId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid ticket_id']);
        exit;
    }

    // If support_tickets has an is_read column, update it; otherwise, no-op success
    $hasIsRead = false;
    $res = $conn->query("SHOW COLUMNS FROM support_tickets LIKE 'is_read'");
    if ($res && $res->num_rows > 0) {
        $hasIsRead = true;
    }

    if ($hasIsRead) {
        $stmt = $conn->prepare('UPDATE support_tickets SET is_read = 1 WHERE id = ?');
        if ($stmt) {
            $stmt->bind_param('i', $ticketId);
            $stmt->execute();
        }
    }

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
