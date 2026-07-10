<?php
/**
 * FILE: update_customer.php
 * DID NOT EXIST — the "Edit Customer" form in admin.php submits here.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
require_once 'db_pharmacy.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Admin') {
    echo json_encode(['success' => false, 'message' => 'Not authorized.']);
    exit;
}

$customer_id = (int)($_POST['customer_id'] ?? 0);
$first_name = trim($_POST['first_name'] ?? '');
$middle_name = trim($_POST['middle_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone_number = trim($_POST['phone_number'] ?? '');
$address = trim($_POST['address'] ?? '');
$customer_type = trim($_POST['customer_type'] ?? 'Regular');
$loyalty_points = (float)($_POST['loyalty_points'] ?? 0);
$password = $_POST['password'] ?? '';

if ($customer_id <= 0 || $first_name === '' || $last_name === '') {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

if ($password !== '') {
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $conn->prepare(
        "UPDATE customers SET first_name=?, middle_name=?, last_name=?, email=?, phone_number=?, address=?, customer_type=?, loyalty_points=?, password=? WHERE customer_id=?"
    );
    $stmt->bind_param('sssssssdsi', $first_name, $middle_name, $last_name, $email, $phone_number, $address, $customer_type, $loyalty_points, $hash, $customer_id);
} else {
    $stmt = $conn->prepare(
        "UPDATE customers SET first_name=?, middle_name=?, last_name=?, email=?, phone_number=?, address=?, customer_type=?, loyalty_points=? WHERE customer_id=?"
    );
    $stmt->bind_param('sssssssdi', $first_name, $middle_name, $last_name, $email, $phone_number, $address, $customer_type, $loyalty_points, $customer_id);
}

if ($stmt->execute()) {
    $admin_name = $_SESSION['user_first_name'] ?? 'Admin';
    $details = "Updated customer ID $customer_id ($first_name $last_name).";
    $log = $conn->prepare("INSERT INTO activity_logs (admin_name, action, details) VALUES (?, 'Update Customer', ?)");
    $log->bind_param('ss', $admin_name, $details);
    $log->execute();
    $log->close();

    echo json_encode(['success' => true, 'message' => 'Customer updated successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update customer: ' . $stmt->error]);
}
$stmt->close();