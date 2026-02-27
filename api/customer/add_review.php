<?php
session_start();
header('Content-Type: application/json');
require_once '../../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $order_id = isset($input['order_id']) ? (int)$input['order_id'] : 0;
    $rating = isset($input['rating']) ? (int)$input['rating'] : 0;
    $comment = isset($input['comment']) ? trim($input['comment']) : '';

    if ($order_id <= 0 || $rating < 1 || $rating > 5) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid input']);
        exit;
    }

    $customer_id = (int)$_SESSION['user_id'];

    // Validate order belongs to user and is delivered; also fetch restaurant_id
    $stmt = $conn->prepare("SELECT id, restaurant_id FROM orders WHERE id = ? AND customer_id = ? AND status = 'delivered'");
    $stmt->bind_param('ii', $order_id, $customer_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Order not found or not eligible for review']);
        exit;
    }
    $order = $res->fetch_assoc();
    $restaurant_id = (int)$order['restaurant_id'];

    // Check if reviews table has order_id column
    $hasOrderIdCol = false;
    $colStmt = $conn->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reviews' AND COLUMN_NAME = 'order_id'");
    $colStmt->execute();
    $hasOrderIdCol = $colStmt->get_result()->num_rows > 0;

    if ($hasOrderIdCol) {
        // Prevent duplicate review per order by same user
        $chk = $conn->prepare('SELECT id FROM reviews WHERE user_id = ? AND order_id = ? LIMIT 1');
        $chk->bind_param('ii', $customer_id, $order_id);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            http_response_code(409);
            echo json_encode(['error' => 'You have already reviewed this order']);
            exit;
        }

        $ins = $conn->prepare('INSERT INTO reviews (order_id, restaurant_id, user_id, rating, comment) VALUES (?, ?, ?, ?, ?)');
        $ins->bind_param('iiiis', $order_id, $restaurant_id, $customer_id, $rating, $comment);
        $ins->execute();
    } else {
        // Fallback: prevent duplicate review per restaurant by same user (less strict)
        $chk = $conn->prepare('SELECT id FROM reviews WHERE user_id = ? AND restaurant_id = ? LIMIT 1');
        $chk->bind_param('ii', $customer_id, $restaurant_id);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            http_response_code(409);
            echo json_encode(['error' => 'You have already reviewed this restaurant']);
            exit;
        }

        $ins = $conn->prepare('INSERT INTO reviews (restaurant_id, user_id, rating, comment) VALUES (?, ?, ?, ?)');
        $ins->bind_param('iiis', $restaurant_id, $customer_id, $rating, $comment);
        $ins->execute();
    }

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'error_detail' => $e->getMessage()]);
}
