<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
include 'db_pharmacy.php';
require_once 'includes/stock_status.php';

syncAllStockStatus($conn);

$start_date = $_GET['startDate'] ?? date('Y-m-d');
$end_date = $_GET['endDate'] ?? date('Y-m-d');

// Transactions Count
$stmt = $conn->prepare("
    SELECT COUNT(sale_id) AS transaction_count
    FROM sales
    WHERE DATE(date_created) BETWEEN ? AND ? AND status = 'completed'
");
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$transaction_count = (int)($stmt->get_result()->fetch_assoc()['transaction_count'] ?? 0);
$stmt->close();

// Total Sales
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(total_amount), 0) AS total_sales
    FROM sales
    WHERE DATE(date_created) BETWEEN ? AND ? AND status = 'completed'
");
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$total_sales = (float)($stmt->get_result()->fetch_assoc()['total_sales'] ?? 0);
$stmt->close();

// Items sold (quantity) in period — not just peso sales
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(si.quantity), 0) AS items_sold
    FROM sales_items si
    JOIN sales s ON si.sale_id = s.sale_id
    WHERE DATE(s.date_created) BETWEEN ? AND ? AND s.status = 'completed'
");
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$items_sold = (int)($stmt->get_result()->fetch_assoc()['items_sold'] ?? 0);
$stmt->close();

// Low stock only (excludes out of stock) — uses normalized column
$res_low = $conn->query("SELECT COUNT(*) AS cnt FROM drugs_master WHERE is_active = 1 AND stock_status = 'low'");
$low_stock_count = (int)($res_low->fetch_assoc()['cnt'] ?? 0);

$res_out = $conn->query("SELECT COUNT(*) AS cnt FROM drugs_master WHERE is_active = 1 AND stock_status = 'out'");
$out_of_stock_count = (int)($res_out->fetch_assoc()['cnt'] ?? 0);

// Pending Orders
$res_pending = $conn->query("
    SELECT COUNT(order_id) AS pending_orders
    FROM customer_orders
    WHERE order_status IN ('Pending', 'Processing')
");
$pending_orders = (int)($res_pending->fetch_assoc()['pending_orders'] ?? 0);

echo json_encode([
    'transaction_count' => $transaction_count,
    'total_sales' => number_format($total_sales, 2, '.', ''),
    'items_sold' => $items_sold,
    'low_stock_count' => $low_stock_count,
    'out_of_stock_count' => $out_of_stock_count,
    'pending_orders' => $pending_orders,
]);
$conn->close();
