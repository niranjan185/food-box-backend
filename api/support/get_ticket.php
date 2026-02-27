<?php
header('Content-Type: application/json');
require_once '../../db_connect.php';

// Check if user is admin
session_start();
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Get ticket ID from query string
$ticketId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$ticketId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ticket ID is required']);
    exit;
}

try {
    // Get ticket details
    $query = "SELECT 
                st.*, 
                c.name as customer_name,
                c.email as customer_email,
                o.order_number
              FROM support_tickets st
              LEFT JOIN customers c ON st.customer_id = c.id
              LEFT JOIN orders o ON st.order_id = o.id
              WHERE st.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $ticketId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Ticket not found']);
        exit;
    }
    
    $ticket = $result->fetch_assoc();
    
    // Get ticket replies
    $repliesQuery = "SELECT 
                       str.*,
                       CASE 
                           WHEN str.user_type = 'admin' THEN 'Admin'
                           ELSE c.name 
                       END as user_name,
                       CASE 
                           WHEN str.user_type = 'admin' THEN 'admin'
                           ELSE 'customer'
                       END as user_type_display
                     FROM support_ticket_replies str
                     LEFT JOIN customers c ON str.user_type = 'customer' AND str.user_id = c.id
                     WHERE str.ticket_id = ?
                     ORDER BY str.created_at ASC";
    
    $stmt = $conn->prepare($repliesQuery);
    $stmt->bind_param('i', $ticketId);
    $stmt->execute();
    $repliesResult = $stmt->get_result();
    $replies = [];
    
    while ($row = $repliesResult->fetch_assoc()) {
        $replies[] = [
            'id' => (int)$row['id'],
            'ticket_id' => (int)$row['ticket_id'],
            'user_id' => (int)$row['user_id'],
            'user_type' => $row['user_type'],
            'user_name' => $row['user_name'],
            'message' => $row['message'],
            'created_at' => $row['created_at'],
            'user_type_display' => $row['user_type_display']
        ];
    }
    
    // Mark ticket as read for admin
    $markReadQuery = "UPDATE support_tickets SET is_read = 1 WHERE id = ?";
    $stmt = $conn->prepare($markReadQuery);
    $stmt->bind_param('i', $ticketId);
    $stmt->execute();
    
    // Format response
    $response = [
        'id' => (int)$ticket['id'],
        'customer_id' => (int)$ticket['customer_id'],
        'customer_name' => $ticket['customer_name'],
        'customer_email' => $ticket['customer_email'],
        'order_id' => $ticket['order_id'] ? (int)$ticket['order_id'] : null,
        'order_number' => $ticket['order_number'],
        'topic' => $ticket['topic'],
        'message' => $ticket['message'],
        'status' => $ticket['status'],
        'contact_email' => $ticket['contact_email'],
        'contact_phone' => $ticket['contact_phone'],
        'created_at' => $ticket['created_at'],
        'updated_at' => $ticket['updated_at'],
        'replies' => $replies
    ];
    
    echo json_encode(['success' => true, 'data' => $response]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
