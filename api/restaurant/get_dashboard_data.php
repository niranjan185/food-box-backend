<?php
session_start();
header('Content-Type: application/json');
require_once '../../db_connect.php';

if (!isset($_SESSION['restaurant_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$restaurant_id = (int)$_SESSION['restaurant_id'];

try {
    // Restaurant name
    $stmt = $conn->prepare("SELECT restaurant_name FROM restaurant WHERE id = ?");
    $stmt->bind_param('i', $restaurant_id);
    $stmt->execute();
    $restaurant = $stmt->get_result()->fetch_assoc();

    // New orders count (pending)
    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM orders WHERE restaurant_id = ? AND status = 'pending'");
    $stmt->bind_param('i', $restaurant_id);
    $stmt->execute();
    $new_orders_count = (int)$stmt->get_result()->fetch_assoc()['cnt'];

    // Today's total sales
    $stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount),0) AS total FROM orders WHERE restaurant_id = ? AND DATE(created_at) = CURDATE() AND status IN ('confirmed','preparing','ready','out_for_delivery','delivered')");
    $stmt->bind_param('i', $restaurant_id);
    $stmt->execute();
    $total_sales_today = (float)$stmt->get_result()->fetch_assoc()['total'];

    // Pending deliveries count
    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM orders WHERE restaurant_id = ? AND status = 'out_for_delivery'");
    $stmt->bind_param('i', $restaurant_id);
    $stmt->execute();
    $pending_deliveries_count = (int)$stmt->get_result()->fetch_assoc()['cnt'];

    // Recent orders (last 5)
    $stmt = $conn->prepare(
        "SELECT o.id AS order_id, o.total_amount, o.status, o.created_at AS order_date, c.full_name AS customer_name
         FROM orders o
         JOIN customer c ON c.id = o.customer_id
         WHERE o.restaurant_id = ?
         ORDER BY o.created_at DESC
         LIMIT 5"
    );
    $stmt->bind_param('i', $restaurant_id);
    $stmt->execute();
    $recent_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Top selling items (last 30 days)
    $stmt = $conn->prepare(
        "SELECT mi.name, mi.image_url, SUM(oi.quantity) AS total_quantity_sold, SUM(oi.quantity * oi.price_at_time) AS total_revenue
         FROM order_items oi
         JOIN orders o ON o.id = oi.order_id
         JOIN menu_items mi ON mi.id = oi.menu_item_id
         WHERE o.restaurant_id = ? AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
         GROUP BY mi.id
         ORDER BY total_quantity_sold DESC
         LIMIT 5"
    );
    $stmt->bind_param('i', $restaurant_id);
    $stmt->execute();
    $top_selling_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'restaurant_name' => $restaurant['restaurant_name'] ?? 'Restaurant',
        'new_orders_count' => $new_orders_count,
        'total_sales_today' => $total_sales_today,
        'pending_deliveries_count' => $pending_deliveries_count,
        'recent_orders' => $recent_orders,
        'top_selling_items' => $top_selling_items,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
