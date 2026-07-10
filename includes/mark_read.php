<?php
/**
 * FILE: includes/mark_read.php
 * Called by customer.js (window.markNotificationRead) but did not exist —
 * every notification click silently 404'd and the badge count never
 * actually cleared in the database.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
require_once __DIR__ . '/../db_pharmacy.php';

$customer_id = $_SESSION['user_id'] ?? null;
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if (!$customer_id || $order_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

// Scoped to the logged-in customer so nobody can mark another customer's
// notification as read by guessing an order_id.
$stmt = $conn->prepare("UPDATE customer_orders SET is_read = 1 WHERE order_id = ? AND customer_id = ?");
$stmt->bind_param('ii', $order_id, $customer_id);
$stmt->execute();

echo json_encode(['success' => true]);
$stmt->close();
$conn->close();