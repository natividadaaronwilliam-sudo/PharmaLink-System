<?php
// user_actions.php
// Handles admin.php "Add Staff" and "Add Customer" form submissions (action=add).
// This endpoint was referenced by admin.php's sendUserAction() but did not exist,
// which is why nothing ever reached the database.
require_once 'db_pharmacy.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = $_POST['action'] ?? '';

if ($action !== 'add') {
    echo json_encode(["status" => "error", "msg" => "Unsupported action."]);
    exit;
}

$role          = trim($_POST['role'] ?? '');
$first_name    = trim($_POST['first_name'] ?? '');
$middle_name   = trim($_POST['middle_name'] ?? '');
$last_name     = trim($_POST['last_name'] ?? '');
$username      = trim($_POST['username'] ?? '');
$password      = $_POST['password'] ?? '';
$email         = trim($_POST['email'] ?? '');
$phone_number  = trim($_POST['phone_number'] ?? '');
$address       = trim($_POST['address'] ?? '');

if ($first_name === '' || $last_name === '' || $username === '' || $password === '' || $role === '') {
    echo json_encode(["status" => "error", "msg" => "Please fill in all required fields."]);
    exit;
}

$admin_name = $_SESSION['user_first_name'] ?? 'Admin';
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

if ($role === 'Customer') {
    // Customers live entirely in the `customers` table (their own username/password).
    $customer_type  = trim($_POST['customer_type'] ?? 'Regular');
    $loyalty_points = isset($_POST['loyalty_points']) ? floatval($_POST['loyalty_points']) : 0;

    // Check for duplicate username/email up front so we can give a clear message
    $check = $conn->prepare("SELECT customer_id FROM customers WHERE username = ? OR email = ?");
    $check->bind_param("ss", $username, $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(["status" => "error", "msg" => "Username or email already in use."]);
        $check->close();
        $conn->close();
        exit;
    }
    $check->close();

    $stmt = $conn->prepare("INSERT INTO customers (first_name, middle_name, last_name, username, address, phone_number, is_active, customer_type, password, loyalty_points, email) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssssds", $first_name, $middle_name, $last_name, $username, $address, $phone_number, $customer_type, $hashed_password, $loyalty_points, $email);

    if ($stmt->execute()) {
        $new_id = $stmt->insert_id;
        $stmt->close();

        $log_action = "Add Customer";
        $details = "New customer '{$first_name} {$last_name}' (ID {$new_id}) was added.";
        $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_name, action, details) VALUES (?, ?, ?)");
        $logStmt->bind_param("sss", $admin_name, $log_action, $details);
        $logStmt->execute();
        $logStmt->close();

        echo json_encode(["status" => "success", "user_id" => $new_id]);
    } else {
        echo json_encode(["status" => "error", "msg" => "Error adding customer: " . $stmt->error]);
    }

} elseif ($role === 'Admin' || $role === 'Cashier/Pharmacist') {
    // Staff: a row in `users` (login/role) + a row in `staff_info` (profile details)
    $check = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
    $check->bind_param("s", $username);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(["status" => "error", "msg" => "Username already in use."]);
        $check->close();
        $conn->close();
        exit;
    }
    $check->close();

    $roleStmt = $conn->prepare("SELECT role_id FROM role WHERE role_name = ?");
    $roleStmt->bind_param("s", $role);
    $roleStmt->execute();
    $roleResult = $roleStmt->get_result();
    if ($roleResult->num_rows !== 1) {
        echo json_encode(["status" => "error", "msg" => "Invalid role selected."]);
        $roleStmt->close();
        $conn->close();
        exit;
    }
    $role_id = $roleResult->fetch_assoc()['role_id'];
    $roleStmt->close();

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("INSERT INTO users (username, password, role_id, is_active) VALUES (?, ?, ?, 1)");
        $stmt->bind_param("ssi", $username, $hashed_password, $role_id);
        $stmt->execute();
        $new_user_id = $stmt->insert_id;
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO staff_info (user_id, first_name, middle_name, last_name, email, phone_number, address) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssss", $new_user_id, $first_name, $middle_name, $last_name, $email, $phone_number, $address);
        $stmt->execute();
        $stmt->close();

        $log_action = "Add Staff";
        $details = "New {$role} account '{$first_name} {$last_name}' (ID {$new_user_id}) was added.";
        $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_name, action, details) VALUES (?, ?, ?)");
        $logStmt->bind_param("sss", $admin_name, $log_action, $details);
        $logStmt->execute();
        $logStmt->close();

        $conn->commit();
        echo json_encode(["status" => "success", "user_id" => $new_user_id]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["status" => "error", "msg" => "Error adding staff: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "msg" => "Invalid role."]);
}

$conn->close();
?>
