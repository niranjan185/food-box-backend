<?php
session_start();
header('Content-Type: application/json');
require_once '../../db_connect.php';
// Enable mysqli exceptions so we return clear error_detail
if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$customer_id = (int)$_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true) ?: [];

$full_name = isset($input['full_name']) ? trim($input['full_name']) : null;
$phone = isset($input['phone']) ? trim($input['phone']) : null;
$email = isset($input['email']) ? trim($input['email']) : null; // optional: only if column exists

if ($full_name === null && $phone === null && $email === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No fields to update']);
    exit;
}

try {
    // Build dynamic UPDATE to handle optional email column
    $fields = [];
    $params = [];
    $types = '';

    if ($full_name !== null) { $fields[] = 'full_name = ?'; $params[] = $full_name; $types .= 's'; }
    if ($phone !== null) { $fields[] = 'phone = ?'; $params[] = $phone; $types .= 's'; }

    // Try including email, but fall back if column doesn't exist
    if ($email !== null) {
        $includeEmail = false;
        $colStmt = $conn->prepare("SHOW COLUMNS FROM customer LIKE 'email'");
        $colStmt->execute();
        $colRes = $colStmt->get_result();
        if ($colRes && $colRes->num_rows > 0) { $includeEmail = true; }
        if ($includeEmail) { $fields[] = 'email = ?'; $params[] = $email; $types .= 's'; }
    }

    if (empty($fields)) {
        echo json_encode(['success' => true]);
        exit;
    }

    $params[] = $customer_id; $types .= 'i';
    $sql = 'UPDATE customer SET ' . implode(', ', $fields) . ' WHERE id = ?';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'error_detail' => $e->getMessage()]);
}
