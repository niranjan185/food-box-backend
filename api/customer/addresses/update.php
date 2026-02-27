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
$label = isset($input['label']) ? trim($input['label']) : null;
$street = isset($input['street']) ? trim($input['street']) : null;
$apartment = isset($input['apartment']) ? trim($input['apartment']) : null;
$city = isset($input['city']) ? trim($input['city']) : null;
$state = isset($input['state']) ? trim($input['state']) : null;
$zip_code = isset($input['zip_code']) ? trim($input['zip_code']) : null;
$country = isset($input['country']) ? trim($input['country']) : null;
$is_default = isset($input['is_default']) ? (int)!!$input['is_default'] : null;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid address id']);
    exit;
}

try {
    $conn->begin_transaction();

    // Ownership check
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

    $fields = [];
    $params = [];
    $types = '';

    if ($label !== null) { $fields[] = 'label = ?'; $params[] = $label; $types .= 's'; }
    if ($street !== null) { $fields[] = 'street = ?'; $params[] = $street; $types .= 's'; }
    if ($apartment !== null) { $fields[] = 'apartment = ?'; $params[] = $apartment; $types .= 's'; }
    if ($city !== null) { $fields[] = 'city = ?'; $params[] = $city; $types .= 's'; }
    if ($state !== null) { $fields[] = 'state = ?'; $params[] = $state; $types .= 's'; }
    if ($zip_code !== null) { $fields[] = 'zip_code = ?'; $params[] = $zip_code; $types .= 's'; }
    if ($country !== null) { $fields[] = 'country = ?'; $params[] = $country; $types .= 's'; }

    if ($is_default !== null) {
        if ($is_default) {
            $clr = $conn->prepare('UPDATE customer_addresses SET is_default = 0 WHERE customer_id = ?');
            $clr->bind_param('i', $customer_id);
            $clr->execute();
            $fields[] = 'is_default = 1';
        } else {
            $fields[] = 'is_default = 0';
        }
    }

    if (!empty($fields)) {
        $params[] = $id; $types .= 'i';
        $sql = 'UPDATE customer_addresses SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $conn->prepare($sql);
        if ($types !== 'i') {
            $stmt->bind_param($types, ...$params);
        } else {
            $stmt->bind_param('i', $id);
        }
        $stmt->execute();
    }

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    try { $conn->rollback(); } catch (Throwable $ignored) {}
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'error_detail' => $e->getMessage()]);
}
