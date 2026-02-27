<?php
session_start();
header('Content-Type: application/json');
require_once '../../db_connect.php';

$restaurant_id = $_SESSION['restaurant_id'] ?? null;
if (!$restaurant_id) {
    http_response_code(401);
    echo json_encode(['success'=>false,'error'=>'Unauthorized']);
    exit;
}
$restaurant_id = (int)$restaurant_id;

try {
    $conn->query("CREATE TABLE IF NOT EXISTS restaurant_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        restaurant_id INT NOT NULL,
        type VARCHAR(50) NOT NULL,
        ref_id INT NULL,
        title VARCHAR(200) NOT NULL,
        body TEXT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        INDEX idx_rest (restaurant_id),
        INDEX idx_read (is_read),
        INDEX idx_type (type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $type = isset($_GET['type']) ? trim((string)$_GET['type']) : 'support_ticket';
    $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM restaurant_notifications WHERE restaurant_id = ? AND is_read = 0 AND type = ?');
    $stmt->bind_param('is', $restaurant_id, $type);
    $stmt->execute();
    $c = ($stmt->get_result()->fetch_assoc()['c'] ?? 0);

    echo json_encode(['success'=>true,'unread'=> (int)$c]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Server error','error_detail'=>$e->getMessage()]);
}
