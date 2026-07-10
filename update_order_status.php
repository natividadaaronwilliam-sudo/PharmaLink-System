<?php
/**
 * FILE: update_order_status.php
 * Did not exist — cashier.php's online-order finalization step called this
 * after a walk-in checkout to mark the matching online order "Completed",
 * and it was silently failing (404) every time.
 */
session_start();
header('Content-Type: application/json');
require_once 'db_pharmacy.php';

$allowed_roles = ['Cashier/Pharmacist', 'Admin'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', $allowed_roles, true)) {
    echo json_encode(['success' => false, 'message' => 'Not authorized.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$order_id = isset($input['order_id']) ? (int)$input['order_id'] : 0;
$status   = trim($input['status'] ?? '');

$allowed_statuses = ['Pending', 'Processing', 'Ready for Pickup', 'Completed', 'Cancelled'];
if ($order_id <= 0 || !in_array($status, $allowed_statuses, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid order_id or status.']);
    exit;
}

$stmt = $conn->prepare("UPDATE customer_orders SET order_status = ? WHERE order_id = ?");
$stmt->bind_param('si', $status, $order_id);

if ($stmt->execute()) {
    $staff_name = $_SESSION['user_first_name'] ?? 'Staff';
    $details = "Order #$order_id status set to '$status'.";
    $log = $conn->prepare("INSERT INTO activity_logs (admin_name, action, details) VALUES (?, 'Update Order Status', ?)");
    $log->bind_param('ss', $staff_name, $details);
    $log->execute();
    $log->close();

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $stmt->error]);
}

$stmt->close();
$conn->close();