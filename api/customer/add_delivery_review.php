<?php
session_start();
header('Content-Type: application/json');
require_once '../../db_connect.php';
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); }

try {
    // support multiple session keys
    $customer_id = 0;
    if (isset($_SESSION['customer_id'])) { $customer_id = (int)$_SESSION['customer_id']; }
    else if (isset($_SESSION['user_id'])) { $customer_id = (int)$_SESSION['user_id']; }
    if ($customer_id <= 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $order_id = isset($input['order_id']) ? (int)$input['order_id'] : 0;
    $rating = isset($input['rating']) ? (int)$input['rating'] : 0;
    $comment = isset($input['comment']) ? trim($input['comment']) : '';

    if ($order_id <= 0 || $rating < 1 || $rating > 5) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid input']);
        exit;
    }

    // Ensure table exists
    $conn->query("CREATE TABLE IF NOT EXISTS delivery_reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        user_id INT NOT NULL,
        delivery_id INT NULL,
        rating TINYINT NOT NULL,
        comment TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_delivery_review (order_id, user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Find delivery partner for the order if present
    $delivery_id = null;
    $hasDeliveryCol = false;
    $res = $conn->query("SHOW COLUMNS FROM orders LIKE 'delivery_partner_id'");
    if ($res && $res->num_rows > 0) { $hasDeliveryCol = true; }
    if ($hasDeliveryCol) {
        $st = $conn->prepare("SELECT delivery_partner_id FROM orders WHERE id = ?");
        $st->bind_param('i', $order_id);
        $st->execute();
        $r = $st->get_result()->fetch_assoc();
        if ($r) { $delivery_id = $r['delivery_partner_id'] !== null ? (int)$r['delivery_partner_id'] : null; }
    }

    // Insert or update (in case of retry)
    $sql = "INSERT INTO delivery_reviews (order_id, user_id, delivery_id, rating, comment)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iiiss', $order_id, $customer_id, $delivery_id, $rating, $comment);
    $stmt->execute();

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'error_detail' => $e->getMessage()]);
}
