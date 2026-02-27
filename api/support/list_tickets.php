<?php
header('Content-Type: application/json');
require_once '../../db_connect.php';

// Check if user is admin
session_start();
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Get query parameters
$status = isset($_GET['status']) ? $_GET['status'] : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = ($page - 1) * $limit;

try {
    // Build the query
    $query = "SELECT 
                st.*, 
                c.name as customer_name,
                c.email as customer_email,
                o.order_number,
                (SELECT COUNT(*) FROM support_ticket_replies str WHERE str.ticket_id = st.id) as reply_count,
                (SELECT created_at FROM support_ticket_replies str WHERE str.ticket_id = st.id ORDER BY created_at DESC LIMIT 1) as last_reply
              FROM support_tickets st
              LEFT JOIN customers c ON st.customer_id = c.id
              LEFT JOIN orders o ON st.order_id = o.id
              WHERE 1=1";
    
    $params = [];
    $types = '';
    
    // Add status filter
    if ($status && in_array($status, ['open', 'in_progress', 'resolved', 'closed'])) {
        $query .= " AND st.status = ?";
        $params[] = $status;
        $types .= 's';
    }
    
    // Add search filter
    if (!empty($search)) {
        $query .= " AND (
            st.topic LIKE ? OR 
            st.message LIKE ? OR 
            c.name LIKE ? OR 
            c.email LIKE ? OR
            o.order_number LIKE ?
        )";
        $searchTerm = "%$search%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        $types .= str_repeat('s', 5);
    }
    
    // Get total count for pagination
    $countQuery = "SELECT COUNT(*) as total FROM ($query) as t";
    $stmt = $conn->prepare($countQuery);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'];
    
    // Add sorting and pagination
    $query .= " ORDER BY 
        CASE 
            WHEN st.status = 'open' THEN 1
            WHEN st.status = 'in_progress' THEN 2
            WHEN st.status = 'resolved' THEN 3
            WHEN st.status = 'closed' THEN 4
            ELSE 5
        END,
        st.updated_at DESC
        LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';
    
    // Get paginated results
    $stmt = $conn->prepare($query);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $tickets = [];
    
    while ($row = $result->fetch_assoc()) {
        $tickets[] = [
            'id' => (int)$row['id'],
            'customer_id' => (int)$row['customer_id'],
            'customer_name' => $row['customer_name'],
            'customer_email' => $row['customer_email'],
            'order_id' => $row['order_id'] ? (int)$row['order_id'] : null,
            'order_number' => $row['order_number'],
            'topic' => $row['topic'],
            'message' => $row['message'],
            'status' => $row['status'],
            'contact_email' => $row['contact_email'],
            'contact_phone' => $row['contact_phone'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'reply_count' => (int)$row['reply_count'],
            'last_reply' => $row['last_reply']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $tickets,
        'pagination' => [
            'total' => (int)$total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
