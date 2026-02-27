<?php
session_start();
header('Content-Type: application/json');
require_once '../../db_connect.php';
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); }

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$delivery_id = isset($input['delivery_id']) ? (int)$input['delivery_id'] : 0;
$action = isset($input['action']) ? strtolower(trim($input['action'])) : '';
if (!$delivery_id || !in_array($action, ['activate','deactivate'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

try {
    // Determine which column controls active state
    $col = null; $mode = 'tinyint';
    $r = $conn->query("SHOW COLUMNS FROM delivery LIKE 'is_active'");
    if ($r && $r->num_rows > 0) { $col = 'is_active'; $mode = 'tinyint'; }
    if ($col === null) {
        $r = $conn->query("SHOW COLUMNS FROM delivery LIKE 'status'");
        if ($r && $r->num_rows > 0) { $col = 'status'; $mode = 'enum'; }
    }
    if ($col === null) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Active status column not found on delivery table']);
        exit;
    }

    if ($mode === 'tinyint') {
        $val = ($action === 'activate') ? 1 : 0;
        $stmt = $conn->prepare('UPDATE delivery SET is_active = ? WHERE id = ?');
        $stmt->bind_param('ii', $val, $delivery_id);
    } else { // enum/text status
        $val = ($action === 'activate') ? 'active' : 'inactive';
        $stmt = $conn->prepare('UPDATE delivery SET status = ? WHERE id = ?');
        $stmt->bind_param('si', $val, $delivery_id);
    }
    $stmt->execute();

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'error_detail' => $e->getMessage()]);
}
