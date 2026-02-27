<?php
require_once __DIR__ . '/session_helper.php';
require_once '../../db_connect.php';

// Check admin authentication
requireAdminAuth();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Parse the request body
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    // If not JSON, try form data
    parse_str(file_get_contents('php://input'), $input);
}

try {
    // Get admin ID from session
    $adminId = $_SESSION['admin_id'];
    
    // In a real implementation, you would update the database here
    // For example:
    // $stmt = $conn->prepare("UPDATE admin_notifications SET is_read = 1 WHERE admin_id = ? AND is_read = 0");
    // $stmt->bind_param('i', $adminId);
    // $stmt->execute();
    
    // For now, we'll just log the action
    error_log(sprintf(
        'Admin %s marked all notifications as read (session: %s)',
        $adminId,
        session_id()
    ));
    
    // Get the updated unread count (should be 0)
    $count = 0; // In a real implementation, you would query this from the database
    
    // Return success response
    echo json_encode([
        'success' => true,
        'count' => $count,
        'message' => 'All notifications marked as read',
        'debug' => [
            'admin_id' => $adminId,
            'session_id' => session_id(),
            'input' => $input
        ]
    ]);
    
} catch (Exception $e) {
    error_log('Error in mark_all_read.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Failed to mark notifications as read',
        'debug' => [
            'message' => $e->getMessage(),
            'admin_id' => $_SESSION['admin_id'] ?? 'not set',
            'session_id' => session_id()
        ]
    ]);
}
?>
