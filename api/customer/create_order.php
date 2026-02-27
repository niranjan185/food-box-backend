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
    $customer_id = (int)$_SESSION['user_id'];

    // Load current cart
    $cartSql = $conn->prepare(
        "SELECT c.menu_item_id, c.quantity, m.price, m.restaurant_id
         FROM cart c
         JOIN menu_items m ON m.id = c.menu_item_id
         WHERE c.customer_id = ?"
    );
    $cartSql->bind_param('i', $customer_id);
    $cartSql->execute();
    $cartRes = $cartSql->get_result();
    $items = $cartRes->fetch_all(MYSQLI_ASSOC);

    if (count($items) === 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Cart is empty']);
        exit;
    }

    // Ensure single restaurant in cart (common business rule)
    $restaurantIds = array_unique(array_map(fn($it) => (int)$it['restaurant_id'], $items));
    if (count($restaurantIds) !== 1) {
        http_response_code(400);
        echo json_encode(['error' => 'Please order from one restaurant at a time']);
        exit;
    }
    $restaurant_id = (int)$restaurantIds[0];

    // Totals (align with cart.html and payment.html)
    $TAX_RATE = 0.05;     // 5%
    $subtotal = 0.0;
    foreach ($items as $it) {
        $subtotal += ((float)$it['price']) * ((int)$it['quantity']);
    }
    $delivery_fee = $subtotal > 0 ? 40.00 : 0.00;
    $tax = ($subtotal) * $TAX_RATE;
    $total_amount = $subtotal + $delivery_fee + $tax;

    // Optional: read client payload (address, payment method, geo)
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $payment_method = isset($input['payment_method']) ? substr($input['payment_method'], 0, 32) : 'card';
    $address_id = isset($input['address_id']) ? (int)$input['address_id'] : null;
    $delivery_address = isset($input['delivery_address']) ? trim((string)$input['delivery_address']) : null;
    $delivery_lat = isset($input['delivery_lat']) && $input['delivery_lat'] !== null ? (float)$input['delivery_lat'] : null;
    $delivery_lng = isset($input['delivery_lng']) && $input['delivery_lng'] !== null ? (float)$input['delivery_lng'] : null;

    $conn->begin_transaction();

    // Detect optional columns
    $hasAddrIdCol = false;
    $colStmt = $conn->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'delivery_address_id'");
    $colStmt->execute();
    $hasAddrIdCol = $colStmt->get_result()->num_rows > 0;

    $hasPayCol = false;
    $colStmt2 = $conn->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'payment_method'");
    $colStmt2->execute();
    $hasPayCol = $colStmt2->get_result()->num_rows > 0;

    $hasAddrTextCol = false;
    $colStmt3 = $conn->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'delivery_address'");
    $colStmt3->execute();
    $hasAddrTextCol = $colStmt3->get_result()->num_rows > 0;

    $hasLatCol = false; $hasLngCol = false;
    $colStmt4 = $conn->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'delivery_lat'");
    $colStmt4->execute();
    $hasLatCol = $colStmt4->get_result()->num_rows > 0;
    $colStmt5 = $conn->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'delivery_lng'");
    $colStmt5->execute();
    $hasLngCol = $colStmt5->get_result()->num_rows > 0;

    // Build INSERT based on available columns and provided values
    // Priority: use address_id if provided; else use delivery_address text if available; always include lat/lng if columns exist and values provided
    $cols = ['customer_id','restaurant_id','total_amount','status','created_at'];
    $placeholders = ['?','?','?','"pending"','NOW()'];
    $types = 'iid';
    $vals = [$customer_id, $restaurant_id, $total_amount];

    if ($hasPayCol) { $cols[] = 'payment_method'; $placeholders[] = '?'; $types .= 's'; $vals[] = $payment_method; }
    if ($hasAddrIdCol && $address_id) { $cols[] = 'delivery_address_id'; $placeholders[] = '?'; $types .= 'i'; $vals[] = $address_id; }
    if ($hasAddrTextCol && !$address_id && $delivery_address) { $cols[] = 'delivery_address'; $placeholders[] = '?'; $types .= 's'; $vals[] = $delivery_address; }
    if ($hasLatCol && $delivery_lat !== null) { $cols[] = 'delivery_lat'; $placeholders[] = '?'; $types .= 'd'; $vals[] = $delivery_lat; }
    if ($hasLngCol && $delivery_lng !== null) { $cols[] = 'delivery_lng'; $placeholders[] = '?'; $types .= 'd'; $vals[] = $delivery_lng; }

    $sql = 'INSERT INTO orders (' . implode(',', $cols) . ') VALUES (' . implode(',', $placeholders) . ')';
    $insOrder = $conn->prepare($sql);
    if (!$insOrder) { throw new Exception('Failed to prepare order insert: ' . $conn->error); }
    $insOrder->bind_param($types, ...$vals);
    $insOrder->execute();
    $order_id = $conn->insert_id;

    // Insert order items
    $insItem = $conn->prepare('INSERT INTO order_items (order_id, menu_item_id, quantity, price_at_time) VALUES (?, ?, ?, ?)');
    if (!$insItem) {
        throw new Exception('Failed to prepare order_items insert: ' . $conn->error);
    }
    foreach ($items as $it) {
        $mid = (int)$it['menu_item_id'];
        $qty = (int)$it['quantity'];
        $price = (float)$it['price'];
        $insItem->bind_param('iiid', $order_id, $mid, $qty, $price);
        $insItem->execute();
    }

    // Clear cart
    $clr = $conn->prepare('DELETE FROM cart WHERE customer_id = ?');
    $clr->bind_param('i', $customer_id);
    $clr->execute();

    $conn->commit();

    echo json_encode(['success' => true, 'order_id' => $order_id, 'total_amount' => $total_amount]);
} catch (Throwable $e) {
    if ($conn && $conn->errno === 0) {
        // best-effort rollback
        $conn->rollback();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'error_detail' => $e->getMessage()]);
}
