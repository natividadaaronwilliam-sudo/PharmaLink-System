<?php
require 'db_pharmacy.php';
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['supplier_id']) || empty($data['name'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$supplier_id = (int)$data['supplier_id'];
$name = trim($data['name']);
$contact = trim($data['contact'] ?? '');
$email = trim($data['email'] ?? '');
$address = trim($data['address'] ?? '');
$status = ($data['status'] ?? 'Active') === 'Inactive' ? 'Inactive' : 'Active';
$inactive_reason = trim($data['inactive_reason'] ?? '');

if ($status === 'Active') {
    $inactive_reason = null;
} elseif ($inactive_reason === '') {
    $inactive_reason = 'Manually deactivated by admin';
}

$stmt = $conn->prepare("
    UPDATE suppliers
    SET supplier_name = ?, contact_number = ?, email = ?, address = ?, status = ?, inactive_reason = ?
    WHERE supplier_id = ?
");
$stmt->bind_param("ssssssi", $name, $contact, $email, $address, $status, $inactive_reason, $supplier_id);

if ($stmt->execute()) {
    $admin_name = $_SESSION['user_first_name'] ?? 'Admin';
    $details = "Updated supplier: {$name} (ID {$supplier_id})" . ($status === 'Inactive' ? " — Reason: {$inactive_reason}" : '');
    $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_name, action, details) VALUES (?, ?, ?)");
    $action = 'Edit Supplier';
    $logStmt->bind_param("sss", $admin_name, $action, $details);
    $logStmt->execute();
    $logStmt->close();
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $conn->error]);
}

$stmt->close();
$conn->close();
