<?php
require 'db_pharmacy.php';
header('Content-Type: application/json');

// Start session to get admin name
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['name'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

// Insert supplier
$stmt = $conn->prepare("INSERT INTO suppliers (supplier_name, contact_number, email, address) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $data['name'], $data['contact'], $data['email'], $data['address']);

if ($stmt->execute()) {
    // Activity logging
    $admin_name = $_SESSION['user_first_name'] ?? 'Admin';
    $action = "Add Supplier";
    $details = "Added new supplier: {$data['name']}";

    $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_name, action, details) VALUES (?, ?, ?)");
    $logStmt->bind_param("sss", $admin_name, $action, $details);
    $logStmt->execute();
    $logStmt->close();

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $conn->error]);
}

$stmt->close();
$conn->close();
?>
