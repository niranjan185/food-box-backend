<?php
session_start();
header('Content-Type: application/json');
require_once '../../db_connect.php';
// Enable mysqli exceptions to catch DB errors properly during transaction
if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$customer_id = (int)$_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$delivery_address = isset($input['delivery_address']) ? trim($input['delivery_address']) : '';
$address_id = isset($input['address_id']) ? (int)$input['address_id'] : 0; // customer_addresses.id
// Optional direct geo coordinates from client
$delivery_lat = isset($input['delivery_lat']) ? (float)$input['delivery_lat'] : null;
$delivery_lng = isset($input['delivery_lng']) ? (float)$input['delivery_lng'] : null;

try {
    $conn->begin_transaction();

    $q = $conn->prepare("SELECT c.id AS cart_id, c.quantity, m.id AS menu_item_id, m.name, m.price, m.restaurant_id, r.restaurant_name
                          FROM cart c
                          JOIN menu_items m ON c.menu_item_id = m.id
                          JOIN restaurant r ON m.restaurant_id = r.id
                          WHERE c.customer_id = ?");
    $q->bind_param('i', $customer_id);
    $q->execute();
    $res = $q->get_result();
    $cart_items = $res->fetch_all(MYSQLI_ASSOC);

    if (count($cart_items) === 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Cart is empty']);
        $conn->rollback();
        exit;
    }

    // Ensure all items are from the same restaurant for this basic implementation
    $restaurant_ids = array_values(array_unique(array_map(fn($it) => (int)$it['restaurant_id'], $cart_items)));
    if (count($restaurant_ids) !== 1) {
        http_response_code(400);
        echo json_encode(['error' => 'Mixed-restaurant cart is not supported in this version']);
        $conn->rollback();
        exit;
    }
    $restaurant_id = $restaurant_ids[0];

    // Resolve delivery address either by provided address_id or fallback to default
    $resolved_address_id = 0;
    if ($address_id > 0) {
        try {
            $addrStmt = $conn->prepare("SELECT id, CONCAT_WS(', ', street, NULLIF(apartment,''), city, state, zip_code, country) AS full_addr
                                        FROM customer_addresses WHERE id = ? AND customer_id = ? LIMIT 1");
            $addrStmt->bind_param('ii', $address_id, $customer_id);
            $addrStmt->execute();
            $addrRes = $addrStmt->get_result();
            if ($addrRes && $addrRes->num_rows) {
                $row = $addrRes->fetch_assoc();
                $resolved_address_id = (int)$row['id'];
                if ($delivery_address === '') { $delivery_address = trim((string)$row['full_addr']); }
            }
        } catch (Throwable $ignored) {}
    }
    if ($resolved_address_id === 0 && $delivery_address === '') {
        try {
            $addrStmt = $conn->prepare("SELECT id, CONCAT_WS(', ', street, NULLIF(apartment,''), city, state, zip_code, country) AS full_addr
                                         FROM customer_addresses WHERE customer_id = ? AND is_default = 1 LIMIT 1");
            $addrStmt->bind_param('i', $customer_id);
            $addrStmt->execute();
            $addrRes = $addrStmt->get_result();
            if ($addrRes && $addrRes->num_rows) {
                $row = $addrRes->fetch_assoc();
                $resolved_address_id = (int)$row['id'];
                $delivery_address = trim((string)$row['full_addr']);
            }
        } catch (Throwable $ignored) { /* table may not exist yet */ }
    }

    // Compute total
    $total_amount = 0.0;
    foreach ($cart_items as $it) {
        $total_amount += ((float)$it['price']) * ((int)$it['quantity']);
    }

    // Insert order (conditionally include delivery_address, delivery_address_id, delivery_lat, delivery_lng if columns exist)
    $hasDeliveryAddress = false;
    $hasDeliveryAddressId = false;
    $hasDeliveryLat = false;
    $hasDeliveryLng = false;
    try {
        $col = $conn->prepare("SHOW COLUMNS FROM orders LIKE 'delivery_address'");
        $col->execute();
        $colRes = $col->get_result();
        $hasDeliveryAddress = ($colRes && $colRes->num_rows > 0);
    } catch (Throwable $ignored) {}
    try {
        $col = $conn->prepare("SHOW COLUMNS FROM orders LIKE 'delivery_address_id'");
        $col->execute();
        $colRes = $col->get_result();
        $hasDeliveryAddressId = ($colRes && $colRes->num_rows > 0);
    } catch (Throwable $ignored) {}
    try {
        $col = $conn->prepare("SHOW COLUMNS FROM orders LIKE 'delivery_lat'");
        $col->execute();
        $colRes = $col->get_result();
        $hasDeliveryLat = ($colRes && $colRes->num_rows > 0);
    } catch (Throwable $ignored) {}
    try {
        $col = $conn->prepare("SHOW COLUMNS FROM orders LIKE 'delivery_lng'");
        $col->execute();
        $colRes = $col->get_result();
        $hasDeliveryLng = ($colRes && $colRes->num_rows > 0);
    } catch (Throwable $ignored) {}

    // Build dynamic insert combinations to include lat/lng when available
    if ($hasDeliveryAddress && $hasDeliveryAddressId && $hasDeliveryLat && $hasDeliveryLng && $delivery_lat !== null && $delivery_lng !== null) {
        $stmt = $conn->prepare("INSERT INTO orders (customer_id, restaurant_id, total_amount, delivery_address, delivery_address_id, delivery_lat, delivery_lng, status, created_at)
                                 VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
        $stmt->bind_param('iidsidd', $customer_id, $restaurant_id, $total_amount, $delivery_address, $resolved_address_id, $delivery_lat, $delivery_lng);
    } else if ($hasDeliveryAddress && $hasDeliveryAddressId) {
        $stmt = $conn->prepare("INSERT INTO orders (customer_id, restaurant_id, total_amount, delivery_address, delivery_address_id, status, created_at)
                                 VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
        $stmt->bind_param('iidsi', $customer_id, $restaurant_id, $total_amount, $delivery_address, $resolved_address_id);
    } else if ($hasDeliveryAddress && $hasDeliveryLat && $hasDeliveryLng && $delivery_lat !== null && $delivery_lng !== null) {
        $stmt = $conn->prepare("INSERT INTO orders (customer_id, restaurant_id, total_amount, delivery_address, delivery_lat, delivery_lng, status, created_at)
                                 VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())");
        $stmt->bind_param('iidsdd', $customer_id, $restaurant_id, $total_amount, $delivery_address, $delivery_lat, $delivery_lng);
    } else if ($hasDeliveryAddress) {
        $stmt = $conn->prepare("INSERT INTO orders (customer_id, restaurant_id, total_amount, delivery_address, status, created_at)
                                 VALUES (?, ?, ?, ?, 'pending', NOW())");
        $stmt->bind_param('iids', $customer_id, $restaurant_id, $total_amount, $delivery_address);
    } else if ($hasDeliveryAddressId) {
        $stmt = $conn->prepare("INSERT INTO orders (customer_id, restaurant_id, total_amount, delivery_address_id, status, created_at)
                                 VALUES (?, ?, ?, ?, 'pending', NOW())");
        $stmt->bind_param('iiii', $customer_id, $restaurant_id, $total_amount, $resolved_address_id);
    } else if ($hasDeliveryLat && $hasDeliveryLng && $delivery_lat !== null && $delivery_lng !== null) {
        $stmt = $conn->prepare("INSERT INTO orders (customer_id, restaurant_id, total_amount, delivery_lat, delivery_lng, status, created_at)
                                 VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
        $stmt->bind_param('iiddd', $customer_id, $restaurant_id, $total_amount, $delivery_lat, $delivery_lng);
    } else {
        $stmt = $conn->prepare("INSERT INTO orders (customer_id, restaurant_id, total_amount, status, created_at)
                                 VALUES (?, ?, ?, 'pending', NOW())");
        $stmt->bind_param('iid', $customer_id, $restaurant_id, $total_amount);
    }
    $stmt->execute();
    $order_id = $stmt->insert_id;

    // Insert order items (use schema column price_at_time)
    $oi = $conn->prepare("INSERT INTO order_items (order_id, menu_item_id, quantity, price_at_time)
                          VALUES (?, ?, ?, ?)");
    foreach ($cart_items as $it) {
        $mid = (int)$it['menu_item_id'];
        $qty = (int)$it['quantity'];
        $price = (float)$it['price'];
        $oi->bind_param('iiid', $order_id, $mid, $qty, $price);
        $oi->execute();
    }

    // Clear cart
    $clr = $conn->prepare('DELETE FROM cart WHERE customer_id = ?');
    $clr->bind_param('i', $customer_id);
    $clr->execute();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'order' => [
            'order_id' => $order_id,
            'restaurant_id' => $restaurant_id,
            'restaurant_name' => $cart_items[0]['restaurant_name'],
            'delivery_address' => $delivery_address,
            'estimated_delivery' => '30-45 minutes',
            'items' => array_map(function($it){
                return [
                    'menu_item_id' => (int)$it['menu_item_id'],
                    'name' => $it['name'],
                    'quantity' => (int)$it['quantity'],
                    'price' => (float)$it['price']
                ];
            }, $cart_items),
            'total_amount' => $total_amount
        ]
    ]);
} catch (Throwable $e) {
    // Always attempt rollback on failure
    if (isset($conn)) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
    }
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
