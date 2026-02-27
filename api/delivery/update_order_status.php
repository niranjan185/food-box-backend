<?php
session_start();
header('Content-Type: application/json');
require_once '../../db_connect.php';

// Require authenticated delivery driver
$session_keys = ['delivery_id','driver_id','delivery_boy_id','rider_id'];
$driver_id = null;
foreach ($session_keys as $k) {
    if (isset($_SESSION[$k])) { $driver_id = (int)$_SESSION[$k]; break; }
}
// Fallback: app uses generic user_id + user_type ('customer' | 'delivery')
if (!$driver_id && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'delivery' && isset($_SESSION['user_id'])) {
    $driver_id = (int)$_SESSION['user_id'];
}
if (!$driver_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$order_id = isset($input['order_id']) ? (int)$input['order_id'] : 0;
$status = isset($input['status']) ? trim($input['status']) : '';

// For delivery side, allow only safe transitions we expect the driver to perform
$allowed = ['out_for_delivery', 'delivered'];
if ($order_id <= 0 || !in_array($status, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

try {
    // Ensure order exists
    $stmt = $conn->prepare('SELECT id, status FROM orders WHERE id = ?');
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        exit;
    }
    $order = $res->fetch_assoc();

    // Basic precondition checks
    $current = $order['status'];
    $isValid = false;
    if ($status === 'out_for_delivery' && in_array($current, ['preparing', 'ready'], true)) {
        $isValid = true; // driver marks pickup (optional based on your flow)
    }
    if ($status === 'delivered' && in_array($current, ['out_for_delivery'], true)) {
        $isValid = true; // driver completes delivery
    }

    if (!$isValid) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Invalid status transition']);
        exit;
    }

    if ($status === 'delivered') {
        // Compute earning as 10% of total_amount (adjust rule as needed)
        $amtStmt = $conn->prepare('SELECT total_amount, COALESCE(driver_earning,0) AS driver_earning FROM orders WHERE id = ?');
        $amtStmt->bind_param('i', $order_id);
        $amtStmt->execute();
        $row = $amtStmt->get_result()->fetch_assoc();
        $existingEarn = isset($row['driver_earning']) ? (float)$row['driver_earning'] : 0.0;
        $totalAmount = isset($row['total_amount']) ? (float)$row['total_amount'] : 0.0;
        $calcEarn = round($totalAmount * 0.10, 2);

        if ($existingEarn <= 0) {
            $upd = $conn->prepare('UPDATE orders SET status = ?, driver_earning = ? WHERE id = ?');
            $upd->bind_param('sdi', $status, $calcEarn, $order_id);
            $upd->execute();
        } else {
            $upd = $conn->prepare('UPDATE orders SET status = ? WHERE id = ?');
            $upd->bind_param('si', $status, $order_id);
            $upd->execute();
        }
    } else {
        $upd = $conn->prepare('UPDATE orders SET status = ? WHERE id = ?');
        $upd->bind_param('si', $status, $order_id);
        $upd->execute();
    }

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'error_detail' => $e->getMessage()]);
}
