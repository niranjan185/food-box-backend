<?php
header('Content-Type: application/json');
require_once '../../db_connect.php';
require_once __DIR__ . '/session_helper.php';

// Ensure admin is authenticated (uses unified session config)
requireAdminAuth();

try {
    // Get the limit parameter (default to 5 if not specified)
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
    $limit = max(1, min($limit, 50)); // Ensure limit is between 1 and 50

    // Simplified query using only support_tickets to avoid unknown columns/tables
    $query = "SELECT id, topic, message, status, created_at
              FROM support_tickets
              WHERE status IN ('open', 'in_progress')
              ORDER BY created_at DESC
              LIMIT ?";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $stmt->bind_param('i', $limit);

    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    if (!$result) {
        throw new Exception('Get result failed: ' . $stmt->error);
    }

    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = [
            'id' => (int)$row['id'],
            'subject' => $row['topic'] ?? 'New support ticket',
            'message' => $row['message'] ?? '',
            'status' => $row['status'] ?? 'open',
            'created_at' => $row['created_at'],
            'is_new' => true
        ];
    }
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
