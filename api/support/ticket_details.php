<?php
header('Content-Type: application/json');
require_once '../../db_connect.php';
require_once __DIR__ . '/session_helper.php';

// Ensure admin is authenticated
requireAdminAuth();

try {
    $ticketId = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : 0;
    if ($ticketId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid ticket_id']);
        exit;
    }

    // Fetch ticket (no joins to non-existent tables)
    $ticketQuery = "SELECT id, customer_id, order_id, topic, message, status, contact_email, contact_phone, created_at
                    FROM support_tickets
                    WHERE id = ? LIMIT 1";
    $stmt = $conn->prepare($ticketQuery);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param('i', $ticketId);
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }
    $ticketRes = $stmt->get_result();
    if (!$ticketRes || $ticketRes->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Ticket not found']);
        exit;
    }
    $ticket = $ticketRes->fetch_assoc();

    // Fetch replies (if table exists). If not, return empty list.
    $replies = [];
    $checkTable = $conn->query("SHOW TABLES LIKE 'support_ticket_replies'");
    if ($checkTable && $checkTable->num_rows > 0) {
        $repliesQuery = "SELECT user_type, message, created_at FROM support_ticket_replies WHERE ticket_id = ? ORDER BY created_at ASC";
        $stmt2 = $conn->prepare($repliesQuery);
        if ($stmt2) {
            $stmt2->bind_param('i', $ticketId);
            if ($stmt2->execute()) {
                $repliesRes = $stmt2->get_result();
                while ($row = $repliesRes->fetch_assoc()) {
                    $replies[] = [
                        'message' => $row['message'],
                        'created_at' => $row['created_at'],
                        'is_admin' => ($row['user_type'] === 'admin')
                    ];
                }
            }
        }
    }

    $payload = [
        'id' => (int)$ticket['id'],
        'customer_id' => isset($ticket['customer_id']) ? (int)$ticket['customer_id'] : null,
        'customer_name' => null,
        'order_id' => isset($ticket['order_id']) && $ticket['order_id'] !== null ? (int)$ticket['order_id'] : null,
        'topic' => $ticket['topic'],
        'message' => $ticket['message'],
        'status' => $ticket['status'],
        'contact_email' => $ticket['contact_email'],
        'contact_phone' => $ticket['contact_phone'],
        'created_at' => $ticket['created_at'],
        'updated_at' => $ticket['created_at'],
        'replies' => $replies
    ];

    echo json_encode(['success' => true, 'ticket' => $payload]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
