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
$menu_item_id = $data['menu_item_id'] ?? null;
$quantity = $data['quantity'] ?? 1;
$customer_id = $_SESSION['user_id'];

if (!$menu_item_id || $quantity < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

try {
    // Check if item already exists in cart
    $check_stmt = $conn->prepare("SELECT id, quantity FROM cart WHERE customer_id = ? AND menu_item_id = ?");
    $check_stmt->bind_param("ii", $customer_id, $menu_item_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing cart item
        $cart_item = $result->fetch_assoc();
        $new_quantity = $cart_item['quantity'] + $quantity;
        
        $update_stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
        $update_stmt->bind_param("ii", $new_quantity, $cart_item['id']);
        $update_stmt->execute();
    } else {
        // Add new cart item
        $insert_stmt = $conn->prepare("INSERT INTO cart (customer_id, menu_item_id, quantity) VALUES (?, ?, ?)");
        $insert_stmt->bind_param("iii", $customer_id, $menu_item_id, $quantity);
        $insert_stmt->execute();
    }
    
    // Get updated cart count
    $count_stmt = $conn->prepare("SELECT SUM(quantity) as cart_count FROM cart WHERE customer_id = ?");
    $count_stmt->bind_param("i", $customer_id);
    $count_stmt->execute();
    $cart_count = $count_stmt->get_result()->fetch_assoc()['cart_count'] ?? 0;
    
    echo json_encode([
        'success' => true,
        'message' => 'Item added to cart',
        'cart_count' => $cart_count
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>