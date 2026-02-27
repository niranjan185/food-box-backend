<?php
session_start();
header('Content-Type: application/json');
require_once '../../db_connect.php';

if (!isset($_SESSION['restaurant_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$restaurant_id = (int)$_SESSION['restaurant_id'];
$restaurant_name = isset($_SESSION['restaurant_name']) ? $_SESSION['restaurant_name'] : null;

try {
    // Today's date (server time)
    $today = date('Y-m-d');

    // New orders count (today): pending or confirmed created today
    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM orders WHERE restaurant_id = ? AND DATE(created_at) = ? AND (status IN ('pending','confirmed') OR status IS NULL OR status = '')");
    $stmt->bind_param('is', $restaurant_id, $today);
    $stmt->execute();
    $new_orders = (int)$stmt->get_result()->fetch_assoc()['cnt'];

    // Pending deliveries count: out_for_delivery (regardless of date)
    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM orders WHERE restaurant_id = ? AND status = 'out_for_delivery'");
    $stmt->bind_param('i', $restaurant_id);
    $stmt->execute();
    $pending_deliveries = (int)$stmt->get_result()->fetch_assoc()['cnt'];

    // Total sales today: sum total_amount for delivered today
    $stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount),0) AS total FROM orders WHERE restaurant_id = ? AND status = 'delivered' AND DATE(created_at) = ?");
    $stmt->bind_param('is', $restaurant_id, $today);
    $stmt->execute();
    $total_sales_today = (float)$stmt->get_result()->fetch_assoc()['total'];

    // Recent orders (last 5)
    $stmt = $conn->prepare(
        "SELECT o.id, COALESCE(NULLIF(o.status,''),'pending') AS status, o.total_amount, o.created_at AS order_date, c.full_name AS customer_name\n         FROM orders o\n         JOIN customer c ON c.id = o.customer_id\n         WHERE o.restaurant_id = ?\n         ORDER BY o.created_at DESC\n         LIMIT 5"
    );
    $stmt->bind_param('i', $restaurant_id);
    $stmt->execute();
    $recent_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'success' => true,
        'restaurant_name' => $restaurant_name,
        'new_orders' => $new_orders,
        'pending_deliveries' => $pending_deliveries,
        'total_sales_today' => $total_sales_today,
        'recent_orders' => $recent_orders,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
