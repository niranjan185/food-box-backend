<?php
session_start();
header('Content-Type: application/json');
require_once '../../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$customer_id = (int)$_SESSION['user_id'];

try {
    // Try selecting email if column exists; fallback if not
    $profile = null;
    try {
        $stmt = $conn->prepare("SELECT id, full_name, phone, email FROM customer WHERE id = ?");
        $stmt->bind_param('i', $customer_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows) {
            $profile = $res->fetch_assoc();
        }
    } catch (Throwable $ignored) {
        $stmt = $conn->prepare("SELECT id, full_name, phone FROM customer WHERE id = ?");
        $stmt->bind_param('i', $customer_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows) {
            $row = $res->fetch_assoc();
            $row['email'] = null;
            $profile = $row;
        }
    }

    if (!$profile) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Profile not found']);
        exit;
    }

    echo json_encode(['success' => true, 'profile' => $profile]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'error_detail' => $e->getMessage()]);
}
