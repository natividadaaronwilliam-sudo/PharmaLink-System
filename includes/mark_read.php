<?php
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/db_pharmacy.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$orderId = isset($_REQUEST['order_id']) ? (int)$_REQUEST['order_id'] : 0;
if (!$orderId) {
    echo json_encode(['success' => false, 'message' => 'Order ID is required.']);
    exit;
}

$stmt = $conn->prepare('UPDATE customer_orders SET is_read = 1 WHERE order_id = ?');
$stmt->bind_param('i', $orderId);
$stmt->execute();
echo json_encode(['success' => $stmt->affected_rows > 0]);
$stmt->close();
$conn->close();
