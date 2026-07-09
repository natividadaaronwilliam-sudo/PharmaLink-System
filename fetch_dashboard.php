<?php
include 'db_pharmacy.php';
require_once 'includes/stock_status.php';

syncAllStockStatus($conn);

$period = $_GET['period'] ?? 'month';
$today = date('Y-m-d');

switch ($period) {
    case 'today':
        $sales_start = $today;
        $sales_end = $today;
        $period_label = 'Today';
        break;
    case 'year':
        $sales_start = date('Y-01-01');
        $sales_end = date('Y-12-31');
        $period_label = 'This Year';
        break;
    case 'month':
    default:
        $sales_start = date('Y-m-01');
        $sales_end = date('Y-m-t');
        $period_label = 'This Month';
        break;
}

$response = ['period' => $period, 'period_label' => $period_label];

// Staff Count
$staff_count = 0;
$sql = "SELECT COUNT(u.user_id) AS total_staff 
        FROM users u 
        JOIN role r ON u.role_id=r.role_id 
        WHERE LOWER(r.role_name) != 'customer'";
$res = $conn->query($sql);
if ($res && $row = $res->fetch_assoc()) {
    $staff_count = (int)$row['total_staff'];
}

// Customers Count
$customer_count = 0;
$res = $conn->query("SELECT COUNT(customer_id) AS total_customers FROM customers");
if ($res && $row = $res->fetch_assoc()) {
    $customer_count = (int)$row['total_customers'];
}

// Total Sales (filtered period, completed only)
$total_sales = 0;
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(total_amount), 0) AS total_sales
    FROM sales
    WHERE DATE(date_created) BETWEEN ? AND ? AND status = 'completed'
");
$stmt->bind_param('ss', $sales_start, $sales_end);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$total_sales = (float)($row['total_sales'] ?? 0);
$stmt->close();

// Active / Inactive suppliers
$supplier_count = 0;
$inactive_supplier_count = 0;
$res = $conn->query("SELECT status, COUNT(*) AS cnt FROM suppliers GROUP BY status");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        if ($row['status'] === 'Active') {
            $supplier_count = (int)$row['cnt'];
        } else {
            $inactive_supplier_count += (int)$row['cnt'];
        }
    }
}

// Stock counts from normalized column
$ok_stock = $low_stock = $out_stock = 0;
$res = $conn->query("SELECT stock_status, COUNT(*) AS cnt FROM drugs_master WHERE is_active = 1 GROUP BY stock_status");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        switch ($row['stock_status']) {
            case 'low': $low_stock = (int)$row['cnt']; break;
            case 'out': $out_stock = (int)$row['cnt']; break;
            default: $ok_stock = (int)$row['cnt']; break;
        }
    }
}

$response += [
    'staff_count' => $staff_count,
    'customer_count' => $customer_count,
    'total_sales' => $total_sales,
    'supplier_count' => $supplier_count,
    'inactive_supplier_count' => $inactive_supplier_count,
    'ok_stock' => $ok_stock,
    'low_stock' => $low_stock,
    'out_stock' => $out_stock,
    'server_datetime' => date('l, F j, Y — g:i:s A'),
];

header('Content-Type: application/json');
echo json_encode($response);
$conn->close();
