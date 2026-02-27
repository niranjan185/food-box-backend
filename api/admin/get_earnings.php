<?php
session_start();
header('Content-Type: application/json');
require_once '../../db_connect.php';
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); }

try {
    // Detect columns on orders
    $ordersCols = [];
    $colRes = $conn->query("SHOW COLUMNS FROM orders");
    while ($col = $colRes->fetch_assoc()) {
        $ordersCols[$col['Field']] = true;
    }

    $hasCreatedAt = isset($ordersCols['created_at']) || isset($ordersCols['order_date']) || isset($ordersCols['placed_at']);
    $dateCol = isset($ordersCols['created_at']) ? 'created_at' : (isset($ordersCols['order_date']) ? 'order_date' : (isset($ordersCols['placed_at']) ? 'placed_at' : null));

    // Commission columns
    $hasPlatform = isset($ordersCols['platform_commission']);
    $hasCustomerComm = isset($ordersCols['customer_commission']);
    $hasDeliveryComm = isset($ordersCols['delivery_commission']);
    $hasTotalAmount = isset($ordersCols['total_amount']);

    // Rates (fallback when explicit columns are not present)
    $RESTAURANT_RATE = 0.10; // 10% from restaurant side
    $DELIVERY_RATE   = 0.10; // 10% from delivery side

    // Identify a delivery earning column (what the rider earns or the delivery fee charged)
    $deliveryEarningCol = null;
    foreach (['delivery_fee','delivery_charge','delivery_amount','delivery_payout','driver_payout','rider_payout','courier_fee','courier_earning'] as $cand) {
        if (isset($ordersCols[$cand])) { $deliveryEarningCol = $cand; break; }
    }

    // Build commission expression: prefer explicit columns; else fall back to total_amount * RESTAURANT_RATE and deliveryEarning * DELIVERY_RATE
    $commissionExprParts = [];
    if ($hasPlatform) $commissionExprParts[] = 'COALESCE(o.platform_commission,0)';
    if ($hasCustomerComm) $commissionExprParts[] = 'COALESCE(o.customer_commission,0)';
    if ($hasDeliveryComm) $commissionExprParts[] = 'COALESCE(o.delivery_commission,0)';
    $fallbackParts = [];
    if (empty($commissionExprParts)) {
        if ($hasTotalAmount) {
            $fallbackParts[] = '(COALESCE(o.total_amount,0) * ' . $RESTAURANT_RATE . ')';
        }
        if ($deliveryEarningCol) {
            $fallbackParts[] = '(COALESCE(o.`' . $deliveryEarningCol . '`,0) * ' . $DELIVERY_RATE . ')';
        }
    }
    $allParts = array_merge($commissionExprParts, $fallbackParts);
    $commissionExpr = !empty($allParts) ? implode(' + ', $allParts) : '0';

    // Total commissions all-time and count of orders with non-null id
    $sqlTotal = "SELECT COALESCE(SUM($commissionExpr),0) AS total_commission, COUNT(o.id) AS total_orders FROM orders o";
    $resTotal = $conn->query($sqlTotal);
    $rowTotal = $resTotal->fetch_assoc();
    $totalCommission = (float)$rowTotal['total_commission'];
    $totalOrders = (int)$rowTotal['total_orders'];

    // Current month commissions
    $monthCommission = 0.0;
    if ($hasCreatedAt && $dateCol) {
        $stmtMonth = $conn->prepare("SELECT COALESCE(SUM($commissionExpr),0) AS m FROM orders o WHERE YEAR(o.`$dateCol`) = YEAR(CURDATE()) AND MONTH(o.`$dateCol`) = MONTH(CURDATE())");
        $stmtMonth->execute();
        $resMonth = $stmtMonth->get_result()->fetch_assoc();
        $monthCommission = (float)$resMonth['m'];
    }

    // Average commission per order
    $avgPerOrder = $totalOrders > 0 ? ($totalCommission / $totalOrders) : 0.0;

    // Earnings by category (restaurant/customer vs delivery)
    $restaurantSide = 0.0; // customer+platform or fallback rate
    $deliverySide = 0.0;   // delivery side or fallback rate
    if ($hasPlatform || $hasCustomerComm) {
        $parts = [];
        if ($hasPlatform) $parts[] = 'COALESCE(o.platform_commission,0)';
        if ($hasCustomerComm) $parts[] = 'COALESCE(o.customer_commission,0)';
        if (!empty($parts)) {
            $sqlCatA = 'SELECT COALESCE(SUM(' . implode(' + ', $parts) . '),0) AS s FROM orders o';
            $rowA = $conn->query($sqlCatA)->fetch_assoc();
            $restaurantSide = (float)$rowA['s'];
        }
    } else if ($hasTotalAmount) {
        $sqlCatA = 'SELECT COALESCE(SUM(COALESCE(o.total_amount,0) * ' . $RESTAURANT_RATE . '),0) AS s FROM orders o';
        $rowA = $conn->query($sqlCatA)->fetch_assoc();
        $restaurantSide = (float)$rowA['s'];
    }

    if ($hasDeliveryComm) {
        $sqlCatB = 'SELECT COALESCE(SUM(COALESCE(o.delivery_commission,0)),0) AS s FROM orders o';
        $rowB = $conn->query($sqlCatB)->fetch_assoc();
        $deliverySide = (float)$rowB['s'];
    } else if ($deliveryEarningCol) {
        $sqlCatB = 'SELECT COALESCE(SUM(COALESCE(o.`' . $deliveryEarningCol . '`,0) * ' . $DELIVERY_RATE . '),0) AS s FROM orders o';
        $rowB = $conn->query($sqlCatB)->fetch_assoc();
        $deliverySide = (float)$rowB['s'];
    }

    $otherSide = max(0.0, $totalCommission - ($restaurantSide + $deliverySide));

    // Last 6 months series
    $labels = [];
    $values = [];
    if ($hasCreatedAt && $dateCol) {
        $stmtSeries = $conn->prepare(
            "SELECT DATE_FORMAT(o.`$dateCol`, '%b %Y') AS m, COALESCE(SUM($commissionExpr),0) AS s " .
            "FROM orders o " .
            "WHERE o.`$dateCol` >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 5 MONTH) " .
            "GROUP BY DATE_FORMAT(o.`$dateCol`, '%Y-%m') " .
            "ORDER BY MIN(o.`$dateCol`) ASC"
        );
        $stmtSeries->execute();
        $rs = $stmtSeries->get_result();
        while ($r = $rs->fetch_assoc()) {
            $labels[] = $r['m'];
            $values[] = (float)$r['s'];
        }
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'totals' => [
                'total_earnings' => $totalCommission,
                'monthly_earnings' => $monthCommission,
                'avg_commission_per_order' => $avgPerOrder,
                'total_orders' => $totalOrders
            ],
            'by_category' => [
                'restaurant_commission' => $restaurantSide,
                'delivery_commission' => $deliverySide,
                'other' => $otherSide
            ],
            'monthly_series' => [
                'labels' => $labels,
                'values' => $values
            ]
        ]
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'error_detail' => $e->getMessage()]);
}
