<?php
session_start();
header('Content-Type: application/json');
require_once '../../db_connect.php';
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); }

try {
    // Expect logged-in customer (support multiple session keys)
    $customer_id = 0;
    if (isset($_SESSION['customer_id'])) { $customer_id = (int)$_SESSION['customer_id']; }
    else if (isset($_SESSION['user_id'])) { $customer_id = (int)$_SESSION['user_id']; }
    if ($customer_id <= 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $restaurant_id = isset($input['restaurant_id']) ? (int)$input['restaurant_id'] : 0;
    if ($restaurant_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'restaurant_id is required']);
        exit;
    }

    // Ensure favorites table exists
    $conn->query("CREATE TABLE IF NOT EXISTS customer_favorites (
        customer_id INT NOT NULL,
        restaurant_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (customer_id, restaurant_id),
        INDEX idx_cf_customer (customer_id),
        INDEX idx_cf_restaurant (restaurant_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $stmt = $conn->prepare("INSERT IGNORE INTO customer_favorites (customer_id, restaurant_id) VALUES (?, ?)");
    $stmt->bind_param('ii', $customer_id, $restaurant_id);
    $stmt->execute();

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'error_detail' => $e->getMessage()]);
}
