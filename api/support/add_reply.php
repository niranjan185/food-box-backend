<?php
// Always return JSON and suppress HTML error output
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);
require_once '../../db_connect.php';
require_once __DIR__ . '/session_helper.php';

// Ensure admin authentication (unified session config)
requireAdminAuth();

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($input['ticket_id']) || !isset($input['message']) || empty(trim($input['message']))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ticket ID and message are required']);
    exit;
}

$ticketId = (int)$input['ticket_id'];
$message = trim($input['message']);
$markAsResolved = isset($input['mark_resolved']) && $input['mark_resolved'] === true;
$adminId = $_SESSION['admin_id'];

// Start transaction
$conn->begin_transaction();

try {
    // Check if ticket exists and is not closed
    $ticketQuery = "SELECT status FROM support_tickets WHERE id = ? FOR UPDATE";
    $stmt = $conn->prepare($ticketQuery);
    $stmt->bind_param('i', $ticketId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Ticket not found');
    }
    
    $ticket = $result->fetch_assoc();
    
    if ($ticket['status'] === 'closed') {
        throw new Exception('Cannot add reply to a closed ticket');
    }
    
    // Ensure replies table exists (create minimal schema if missing)
    $tblCheck = $conn->query("SHOW TABLES LIKE 'support_ticket_replies'");
    if (!$tblCheck || $tblCheck->num_rows === 0) {
        $conn->query("CREATE TABLE IF NOT EXISTS support_ticket_replies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ticket_id INT NOT NULL,
            user_id INT NOT NULL,
            user_type ENUM('admin','customer') NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ticket (ticket_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    }

    // Add reply (assumes support_ticket_replies exists)
    $insertQuery = "INSERT INTO support_ticket_replies (ticket_id, user_id, user_type, message) VALUES (?, ?, 'admin', ?)";
    $stmt = $conn->prepare($insertQuery);
    $stmt->bind_param('iis', $ticketId, $adminId, $message);
    $stmt->execute();
    $replyId = $conn->insert_id;
    
    // Update ticket status if marked as resolved
    $newStatus = $ticket['status'];
    if ($markAsResolved && $ticket['status'] !== 'resolved') {
        $newStatus = 'resolved';
    } elseif (!$markAsResolved && $ticket['status'] !== 'in_progress') {
        $newStatus = 'in_progress';
    }
    
    if ($newStatus !== $ticket['status']) {
        // Some schemas may not have updated_at; update only status
        $updateQuery = "UPDATE support_tickets SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param('si', $newStatus, $ticketId);
        $stmt->execute();
    }
    
    // Commit transaction
    $conn->commit();
    
    // Get the created reply
    $replyQuery = "SELECT 
                     str.*,
                     'Admin' as user_name,
                     'admin' as user_type_display
                   FROM support_ticket_replies str
                   WHERE str.id = ?";
    
    $stmt = $conn->prepare($replyQuery);
    $stmt->bind_param('i', $replyId);
    $stmt->execute();
    $reply = $stmt->get_result()->fetch_assoc();
    
    // Format response
    $response = [
        'id' => (int)$reply['id'],
        'ticket_id' => (int)$reply['ticket_id'],
        'user_id' => (int)$reply['user_id'],
        'user_type' => $reply['user_type'],
        'user_name' => $reply['user_name'],
        'message' => $reply['message'],
        'created_at' => $reply['created_at'],
        'user_type_display' => $reply['user_type_display']
    ];
    
    echo json_encode([
        'success' => true, 
        'message' => 'Reply added successfully',
        'data' => $response,
        'ticket_status' => $newStatus
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
