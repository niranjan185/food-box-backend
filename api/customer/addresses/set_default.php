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
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$id = isset($input['id']) ? (int)$input['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid address id']);
    exit;
}

try {
    $conn->begin_transaction();
    // Verify ownership
    $own = $conn->prepare('SELECT id FROM customer_addresses WHERE id = ? AND customer_id = ?');
    $own->bind_param('ii', $id, $customer_id);
    $own->execute();
    $ownRes = $own->get_result();
    if (!$ownRes || !$ownRes->num_rows) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Address not found']);
        $conn->rollback();
        exit;
    }

    $clear = $conn->prepare('UPDATE customer_addresses SET is_default = 0 WHERE customer_id = ?');
    $clear->bind_param('i', $customer_id);
    $clear->execute();

    $set = $conn->prepare('UPDATE customer_addresses SET is_default = 1 WHERE id = ?');
    $set->bind_param('i', $id);
    $set->execute();

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    try { $conn->rollback(); } catch (Throwable $ignored) {}
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'error_detail' => $e->getMessage()]);
}
