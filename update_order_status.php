<?php
// update_order_status.php
// Called from cashier.php's Online Order Review modal (reviewOrder / updateOrderStatus).
// This endpoint was referenced but never existed, which is why clicking
// "Set to Ready for Pickup" or "Cancel Order" never actually did anything.
header('Content-Type: application/json');
require_once 'db_pharmacy.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$data = json_decode(file_get_contents('php://input'), true);

$order_id  = isset($data['order_id']) ? (int)$data['order_id'] : null;
$newStatus = $data['status'] ?? '';

$allowedStatuses = ['Ready for Pickup', 'Cancelled', 'Completed', 'Pending'];

if (!$order_id || !in_array($newStatus, $allowedStatuses, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID or status.']);
    exit;
}

// Fetch current status/customer first, so we know what we're transitioning from
$stmt = $conn->prepare("SELECT order_status, customer_id FROM customer_orders WHERE order_id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Order not found.']);
    exit;
}

$currentStatus = $order['order_status'];

// Note: stock is only ever deducted when an order is finalized into an actual
// sale (status -> 'Completed', handled by the normal sale-processing endpoint).
// A 'Pending' or 'Ready for Pickup' order never touched inventory_lots.current_stock,
// so cancelling it here must NOT add stock back — there's nothing to restore.
$conn->begin_transaction();
try {
    $updateStmt = $conn->prepare("UPDATE customer_orders SET order_status = ? WHERE order_id = ?");
    $updateStmt->bind_param("si", $newStatus, $order_id);
    $updateStmt->execute();
    $updateStmt->close();

    $admin_name = $_SESSION['user_first_name'] ?? 'Cashier';
    $action = "Order Status Update";
    $details = "Order #{$order_id} status changed from '{$currentStatus}' to '{$newStatus}'.";
    $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_name, action, details) VALUES (?, ?, ?)");
    $logStmt->bind_param("sss", $admin_name, $action, $details);
    $logStmt->execute();
    $logStmt->close();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => "Order status updated to {$newStatus}."]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error updating order status: ' . $e->getMessage()]);
}

$conn->close();
?>
