<?php
session_start();
require_once '../../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $customer_id = $_SESSION['user_id'];
    
    // Get customer details
    $stmt = $conn->prepare("SELECT full_name, phone FROM customer WHERE id = ?");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $customer = $result->fetch_assoc();

    // Get recent orders
    $orders_query = "SELECT o.*, r.restaurant_name 
                    FROM orders o 
                    JOIN restaurant r ON o.restaurant_id = r.id 
                    WHERE o.customer_id = ? 
                    ORDER BY o.created_at DESC LIMIT 5";
    $stmt = $conn->prepare($orders_query);
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $recent_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Get cart count
    $cart_query = "SELECT COUNT(*) as cart_count FROM cart WHERE customer_id = ?";
    $stmt = $conn->prepare($cart_query);
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $cart_count = $stmt->get_result()->fetch_assoc()['cart_count'];

    $response = [
        'success' => true,
        'customer_name' => $customer['full_name'],
        'phone' => $customer['phone'],
        'recent_orders' => $recent_orders,
        'cart_count' => $cart_count
    ];

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
?>