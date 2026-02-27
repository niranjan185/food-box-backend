<?php
session_start();
header('Content-Type: application/json');
require_once '../../db_connect.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? 'customer') !== 'customer') {
    http_response_code(401);
    echo json_encode(['success'=>false,'error'=>'Unauthorized']);
    exit;
}
$customer_id = (int)$_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true) ?: [];

$topic = trim((string)($input['topic'] ?? ''));
$message = trim((string)($input['message'] ?? ''));
$order_id = isset($input['order_id']) && $input['order_id'] !== '' ? (int)$input['order_id'] : null;
$contact_email = trim((string)($input['email'] ?? ''));
$contact_phone = trim((string)($input['phone'] ?? ''));

if ($topic === '' || $message === '') {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'Topic and message are required']);
    exit;
}

try {
    // Ensure table exists (best-effort)
    $conn->query("CREATE TABLE IF NOT EXISTS support_tickets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NOT NULL,
        order_id INT NULL,
        topic VARCHAR(200) NOT NULL,
        message TEXT NOT NULL,
        contact_email VARCHAR(190) NULL,
        contact_phone VARCHAR(32) NULL,
        status VARCHAR(32) NOT NULL DEFAULT 'open',
        created_at DATETIME NOT NULL,
        INDEX idx_customer (customer_id),
        INDEX idx_order (order_id),
        INDEX idx_status (status),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $stmt = $conn->prepare('INSERT INTO support_tickets (customer_id, order_id, topic, message, contact_email, contact_phone, status, created_at) VALUES (?,?,?,?,?,?,"open", NOW())');
    if (!$stmt) { throw new Exception('Prepare failed: '.$conn->error); }
    // Types: i (customer_id), i (order_id), s (topic), s (message), s (email), s (phone)
    if ($order_id) {
        $stmt->bind_param('iissss', $customer_id, $order_id, $topic, $message, $contact_email, $contact_phone);
    } else {
        // Pass NULL for order_id via null variable
        $null = null;
        $stmt->bind_param('iissss', $customer_id, $null, $topic, $message, $contact_email, $contact_phone);
    }
    $stmt->execute();
    $ticket_id = $stmt->insert_id;

    // Create restaurant notification if ticket is tied to an order
    if ($order_id) {
        try {
            // Ensure notifications table exists
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

            $r = $conn->prepare('SELECT o.restaurant_id, r.restaurant_name FROM orders o JOIN restaurant r ON r.id = o.restaurant_id WHERE o.id = ?');
            if ($r) {
                $r->bind_param('i', $order_id);
                $r->execute();
                if ($row = $r->get_result()->fetch_assoc()) {
                    $rid = (int)$row['restaurant_id'];
                    $title = "New support ticket #$ticket_id";
                    $body = "Topic: $topic\nMessage: " . substr($message,0,300);
                    $insN = $conn->prepare('INSERT INTO restaurant_notifications (restaurant_id, type, ref_id, title, body, is_read, created_at) VALUES (?,?,?,?,?,0, NOW())');
                    if ($insN) { $typ = 'support_ticket'; $insN->bind_param('isiss', $rid, $typ, $ticket_id, $title, $body); $insN->execute(); }
                }
            }
        } catch (Throwable $ignored) {}
    }

    // Email notifications (best-effort, ignore failures)
    try {
        $supportEmail = 'support@foodbox.local'; // change as needed
        $subject = "New Support Ticket #$ticket_id: $topic";
        $body = "Ticket ID: $ticket_id\nCustomer ID: $customer_id\nOrder ID: ".($order_id ?: 'N/A')."\nTopic: $topic\nMessage:\n$message\nContact: ".$contact_email.' / '.$contact_phone;
        @mail($supportEmail, $subject, $body, 'From: no-reply@foodbox.local');

        if ($order_id) {
            $r = $conn->prepare('SELECT r.email FROM orders o JOIN restaurant r ON r.id = o.restaurant_id WHERE o.id = ?');
            if ($r) {
                $r->bind_param('i', $order_id);
                $r->execute();
                $em = $r->get_result()->fetch_assoc()['email'] ?? '';
                if ($em) { @mail($em, $subject, $body, 'From: no-reply@foodbox.local'); }
            }
        }
        // SMS hook placeholder (integrate provider like Twilio here)
        // e.g., call external API with $contact_phone
    } catch (Throwable $ignored) {}

    echo json_encode(['success'=>true, 'ticket_id'=>$ticket_id]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Server error','error_detail'=>$e->getMessage()]);
}
