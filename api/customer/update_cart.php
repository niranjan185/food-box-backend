<?php
session_start();
require_once '../../db_connect.php';

// Check if user is logged in as customer
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$cart_id = $data['cart_id'] ?? null;
$quantity = $data['quantity'] ?? 1;
$customer_id = $_SESSION['user_id'];

if (!$cart_id || $quantity < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

try {
    // Verify cart item belongs to customer
    $check_stmt = $conn->prepare("SELECT id FROM cart WHERE id = ? AND customer_id = ?");
    $check_stmt->bind_param("ii", $cart_id, $customer_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized access to cart item']);
        exit;
    }
    
    // Update cart item quantity
    $update_stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
    $update_stmt->bind_param("ii", $quantity, $cart_id);
    $update_stmt->execute();
    
    // Get updated cart count
    $count_stmt = $conn->prepare("SELECT SUM(quantity) as cart_count FROM cart WHERE customer_id = ?");
    $count_stmt->bind_param("i", $customer_id);
    $count_stmt->execute();
    $cart_count = $count_stmt->get_result()->fetch_assoc()['cart_count'] ?? 0;
    
    echo json_encode([
        'success' => true,
        'message' => 'Cart updated successfully',
        'cart_count' => $cart_count
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>