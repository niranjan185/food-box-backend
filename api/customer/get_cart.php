<?php
session_start();
require_once '../../db_connect.php';

// Check if user is logged in as customer
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $customer_id = $_SESSION['user_id'];
    
    $query = "SELECT c.id as cart_id, c.quantity, m.id as menu_item_id, m.name, m.price, m.image_url, m.restaurant_id, r.restaurant_name 
              FROM cart c
              JOIN menu_items m ON c.menu_item_id = m.id
              JOIN restaurant r ON m.restaurant_id = r.id
              WHERE c.customer_id = ?";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $cart_items = $result->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode([
        'success' => true,
        'cart_items' => $cart_items
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>