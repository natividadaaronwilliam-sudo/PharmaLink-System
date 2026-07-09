<?php
// update_customer.php
// Handles the "Update Customer" form submitted from admin.php (Edit Customer modal).
require_once 'db_pharmacy.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_POST['customer_id'])) {
    echo json_encode(["success" => false, "message" => "Invalid request."]);
    exit;
}

$customer_id    = intval($_POST['customer_id']);
$first_name     = trim($_POST['first_name'] ?? '');
$middle_name    = trim($_POST['middle_name'] ?? '');
$last_name      = trim($_POST['last_name'] ?? '');
$email          = trim($_POST['email'] ?? '');
$phone_number   = trim($_POST['phone_number'] ?? '');
$address        = trim($_POST['address'] ?? '');
$customer_type  = trim($_POST['customer_type'] ?? 'Regular');
$loyalty_points = isset($_POST['loyalty_points']) ? floatval($_POST['loyalty_points']) : 0;
$password       = $_POST['password'] ?? '';

if ($first_name === '' || $last_name === '' || $email === '') {
    echo json_encode(["success" => false, "message" => "Please fill in all required fields."]);
    exit;
}

try {
    if ($password !== '') {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE customers SET first_name=?, middle_name=?, last_name=?, email=?, phone_number=?, address=?, customer_type=?, loyalty_points=?, password=? WHERE customer_id=?");
        $stmt->bind_param("sssssssdsi", $first_name, $middle_name, $last_name, $email, $phone_number, $address, $customer_type, $loyalty_points, $hashed, $customer_id);
    } else {
        $stmt = $conn->prepare("UPDATE customers SET first_name=?, middle_name=?, last_name=?, email=?, phone_number=?, address=?, customer_type=?, loyalty_points=? WHERE customer_id=?");
        $stmt->bind_param("sssssssdi", $first_name, $middle_name, $last_name, $email, $phone_number, $address, $customer_type, $loyalty_points, $customer_id);
    }

    if ($stmt->execute()) {
        $stmt->close();

        // Activity log (same pattern used by deactivate_customer.php)
        $admin_name = $_SESSION['user_first_name'] ?? 'Admin';
        $action = "Edit Customer";
        $details = "Customer ID {$customer_id} was updated.";
        $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_name, action, details) VALUES (?, ?, ?)");
        $logStmt->bind_param("sss", $admin_name, $action, $details);
        $logStmt->execute();
        $logStmt->close();

        echo json_encode(["success" => true, "message" => "Customer updated successfully."]);
    } else {
        echo json_encode(["success" => false, "message" => "Error updating customer: " . $stmt->error]);
    }
} catch (Exception $e) {
    // Likely a duplicate email/username unique-key violation
    echo json_encode(["success" => false, "message" => "Error updating customer: " . $e->getMessage()]);
}

$conn->close();
?>
