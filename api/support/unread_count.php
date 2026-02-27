<?php
require_once __DIR__ . '/session_helper.php';
require_once '../../db_connect.php';

// Check admin authentication
requireAdminAuth();

// Debug: Log session data
error_log('Admin ID: ' . $_SESSION['admin_id']);

try {
    // Count unread support tickets
    $query = "SELECT COUNT(*) as count 
              FROM support_tickets 
              WHERE status IN ('open', 'in_progress')";
    
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception('Database query failed: ' . $conn->error);
    }
    
    $row = $result->fetch_assoc();
    $count = (int)($row['count'] ?? 0);
    
    // Debug log
    error_log("Found $count unread tickets for admin " . $_SESSION['admin_id']);
    
    echo json_encode([
        'success' => true,
        'count' => $count,
        'debug' => [
            'admin_id' => $_SESSION['admin_id'],
            'session_id' => session_id()
        ]
    ]);
    
} catch (Exception $e) {
    error_log('Error in unread_count.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Internal server error',
        'debug' => [
            'message' => $e->getMessage(),
            'admin_id' => $_SESSION['admin_id'] ?? 'not set',
            'session_id' => session_id()
        ]
    ]);
}
?>
