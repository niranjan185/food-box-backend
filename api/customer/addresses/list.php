<?php
session_start();
header('Content-Type: application/json');
require_once '../../../db_connect.php';
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); }

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
$customer_id = (int)$_SESSION['user_id'];

try {
    // Ensure table exists (will throw if not) so we can surface a clear error
    $conn->query("SELECT 1 FROM customer_addresses LIMIT 0");

    $stmt = $conn->prepare("SELECT id, label, street, apartment, city, state, zip_code, country, is_default, created_at
                             FROM customer_addresses WHERE customer_id = ? ORDER BY is_default DESC, created_at DESC");
    $stmt->bind_param('i', $customer_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode(['success' => true, 'addresses' => $rows]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'error_detail' => $e->getMessage()]);
}
