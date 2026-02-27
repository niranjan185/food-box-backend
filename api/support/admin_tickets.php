<?php
header('Content-Type: application/json');
require_once '../../db_connect.php';
require_once __DIR__ . '/session_helper.php';

// Ensure admin is authenticated (unified session config)
requireAdminAuth();

try {
    // Fallback query using only support_tickets (no joins)
    $query = "SELECT 
                id, customer_id, order_id, topic, message, status, contact_email, contact_phone, created_at
              FROM support_tickets
              ORDER BY 
                CASE 
                    WHEN status = 'open' THEN 1
                    WHEN status = 'in_progress' THEN 2
                    WHEN status = 'resolved' THEN 3
                    ELSE 4
                END,
                created_at DESC";

    $result = $conn->query($query);
    if (!$result) {
        throw new Exception('Database query failed: ' . $conn->error);
    }

    $tickets = [];
    while ($row = $result->fetch_assoc()) {
        $tickets[] = [
            'id' => (int)$row['id'],
            'customer_id' => isset($row['customer_id']) ? (int)$row['customer_id'] : null,
            'customer_name' => null,
            'order_id' => isset($row['order_id']) && $row['order_id'] !== null ? (int)$row['order_id'] : null,
            'order_number' => null,
            'topic' => $row['topic'] ?? '',
            'message' => $row['message'] ?? '',
            'status' => $row['status'] ?? 'open',
            'contact_email' => $row['contact_email'] ?? '',
            'contact_phone' => $row['contact_phone'] ?? '',
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['created_at'] ?? null,
            'reply_count' => 0,
            'last_reply' => null
        ];
    }
    
    echo json_encode([
        'success' => true,
        'tickets' => $tickets
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
