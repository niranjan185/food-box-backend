<?php
session_start();
header('Content-Type: application/json');
require_once '../../db_connect.php';

try {
    $base = "SELECT m.*, r.restaurant_name, COALESCE(rv.avg_rating, 0) AS avg_rating
             FROM menu_items m
             JOIN restaurant r ON m.restaurant_id = r.id
             LEFT JOIN (
                 SELECT restaurant_id, AVG(rating) AS avg_rating
                 FROM reviews
                 GROUP BY restaurant_id
             ) rv ON rv.restaurant_id = r.id
             WHERE m.is_available = 1";

    $conditions = [];
    $params = [];
    $types = '';

    if (isset($_GET['category']) && $_GET['category'] !== 'all') {
        $conditions[] = 'm.category = ?';
        $params[] = $_GET['category'];
        $types .= 's';
    }

    if (isset($_GET['price'])) {
        switch ($_GET['price']) {
            case 'low':
                $conditions[] = 'm.price < 100';
                break;
            case 'medium':
                $conditions[] = 'm.price BETWEEN 100 AND 300';
                break;
            case 'high':
                $conditions[] = 'm.price > 300';
                break;
        }
    }

    // Optional cuisine filter (by restaurant profile cuisine_type)
    if (isset($_GET['cuisine']) && $_GET['cuisine'] !== '') {
        $conditions[] = 'r.cuisine_type = ?';
        $params[] = $_GET['cuisine'];
        $types .= 's';
    }

    // Optional diet filter using menu_items.diet_type ('veg' or 'non_veg')
    if (isset($_GET['diet']) && in_array($_GET['diet'], ['veg','non_veg'], true)) {
        $conditions[] = 'm.diet_type = ?';
        $params[] = $_GET['diet'];
        $types .= 's';
    }

    // Optional minimum rating filter using aggregated restaurant average
    if (isset($_GET['rating_min']) && $_GET['rating_min'] !== '') {
        $ratingMin = floatval($_GET['rating_min']);
        if ($ratingMin >= 1 && $ratingMin <= 5) {
            $conditions[] = 'COALESCE(rv.avg_rating, 0) >= ?';
            $params[] = $ratingMin;
            $types .= 'd';
        }
    }

    if (isset($_GET['search']) && $_GET['search'] !== '') {
        $conditions[] = '(m.name LIKE ? OR r.restaurant_name LIKE ?)';
        $search = '%' . $_GET['search'] . '%';
        $params[] = $search;
        $params[] = $search;
        $types .= 'ss';
    }

    $query = $base . (count($conditions) ? (' AND ' . implode(' AND ', $conditions)) : '');

    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $menu_items = $result->fetch_all(MYSQLI_ASSOC);

    echo json_encode(['success' => true, 'menu_items' => $menu_items]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
?>