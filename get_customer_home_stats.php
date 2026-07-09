<?php
// FILE: get_customer_home_stats.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'db_pharmacy.php';
header('Content-Type: application/json');

$customer_id = $_SESSION['user_id'] ?? null;
if (!$customer_id) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

$stmt = $conn->prepare("
    SELECT COUNT(order_id) AS total_orders, SUM(total_amount) AS total_spent
    FROM customer_orders
    WHERE customer_id = ? AND order_status IN ('Completed', 'Delivered')
");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$orders = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt2 = $conn->prepare("SELECT loyalty_points FROM customers WHERE customer_id = ?");
$stmt2->bind_param("i", $customer_id);
$stmt2->execute();
$points = $stmt2->get_result()->fetch_assoc();
$stmt2->close();

echo json_encode([
    'success' => true,
    'total_orders' => (int)($orders['total_orders'] ?? 0),
    'total_spent' => number_format((float)($orders['total_spent'] ?? 0), 2),
    'loyalty_points' => $points['loyalty_points'] ?? 0,
    'available_vouchers' => 0,
]);
$conn->close();
