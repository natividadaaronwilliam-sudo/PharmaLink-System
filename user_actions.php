<?php
/**
 * FILE: user_actions.php
 *
 * DID NOT EXIST. admin.php's "Add Staff User" and "Add Customer" forms both
 * POST here (via sendUserAction()) — with the file missing, the request hit
 * PHP's default 404 HTML page, and the browser tried to JSON.parse() that
 * HTML, which is exactly the "Unexpected token '<' ... is not valid JSON"
 * error you saw.
 *
 * NOTE: the JS expects `status` ('success'/'error') and `msg` keys in the
 * response (not `success`/`message` like most other endpoints in this
 * project) — matched exactly here since that's what admin.php already reads.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
require_once 'db_pharmacy.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Admin') {
    echo json_encode(['status' => 'error', 'msg' => 'Not authorized.']);
    exit;
}

$action = $_POST['action'] ?? '';
if ($action !== 'add') {
    echo json_encode(['status' => 'error', 'msg' => 'Unknown action.']);
    exit;
}

$role = trim($_POST['role'] ?? '');
$first_name = trim($_POST['first_name'] ?? '');
$middle_name = trim($_POST['middle_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$email = trim($_POST['email'] ?? '');
$phone_number = trim($_POST['phone_number'] ?? '');
$address = trim($_POST['address'] ?? '');

if ($first_name === '' || $last_name === '' || $username === '' || $password === '' || $role === '') {
    echo json_encode(['status' => 'error', 'msg' => 'Please fill in all required fields.']);
    exit;
}

$hash = password_hash($password, PASSWORD_BCRYPT);

if ($role === 'Customer') {
    // ---- Add Customer ----
    $customer_type = trim($_POST['customer_type'] ?? 'Regular');
    $loyalty_points = (float)($_POST['loyalty_points'] ?? 0);

    if ($email === '' || $phone_number === '' || $address === '') {
        echo json_encode(['status' => 'error', 'msg' => 'Email, phone number, and address are required for customers.']);
        exit;
    }

    $dupCheck = $conn->prepare("SELECT customer_id FROM customers WHERE username = ?");
    $dupCheck->bind_param('s', $username);
    $dupCheck->execute();
    if ($dupCheck->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'error', 'msg' => 'That username is already taken.']);
        exit;
    }
    $dupCheck->close();

    $stmt = $conn->prepare(
        "INSERT INTO customers (first_name, middle_name, last_name, username, password, email, phone_number, address, customer_type, loyalty_points, is_active)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)"
    );
    $stmt->bind_param('ssssssssdd', $first_name, $middle_name, $last_name, $username, $hash, $email, $phone_number, $address, $customer_type, $loyalty_points);

    if ($stmt->execute()) {
        $new_id = $stmt->insert_id;
        $stmt->close();

        $admin_name = $_SESSION['user_first_name'] ?? 'Admin';
        $details = "Added new customer: $first_name $last_name (ID $new_id).";
        $log = $conn->prepare("INSERT INTO activity_logs (admin_name, action, details) VALUES (?, 'Add Customer', ?)");
        $log->bind_param('ss', $admin_name, $details);
        $log->execute();
        $log->close();

        echo json_encode(['status' => 'success', 'user_id' => $new_id]);
    } else {
        echo json_encode(['status' => 'error', 'msg' => 'Failed to add customer: ' . $stmt->error]);
    }
    exit;
}

if (in_array($role, ['Admin', 'Cashier/Pharmacist'], true)) {
    // ---- Add Staff (Admin or Cashier/Pharmacist) ----
    $roleStmt = $conn->prepare("SELECT role_id FROM role WHERE role_name = ?");
    $roleStmt->bind_param('s', $role);
    $roleStmt->execute();
    $roleRow = $roleStmt->get_result()->fetch_assoc();
    $roleStmt->close();

    if (!$roleRow) {
        echo json_encode(['status' => 'error', 'msg' => "Role '$role' does not exist in the role table."]);
        exit;
    }
    $role_id = (int)$roleRow['role_id'];

    $dupCheck = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
    $dupCheck->bind_param('s', $username);
    $dupCheck->execute();
    if ($dupCheck->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'error', 'msg' => 'That username is already taken.']);
        exit;
    }
    $dupCheck->close();

    $conn->begin_transaction();
    try {
        $userStmt = $conn->prepare("INSERT INTO users (username, password, role_id, is_active) VALUES (?, ?, ?, 1)");
        $userStmt->bind_param('ssi', $username, $hash, $role_id);
        if (!$userStmt->execute()) {
            throw new Exception('Failed to create user account: ' . $userStmt->error);
        }
        $user_id = $userStmt->insert_id;
        $userStmt->close();

        $infoStmt = $conn->prepare(
            "INSERT INTO staff_info (user_id, first_name, middle_name, last_name, email, phone_number, address) VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $infoStmt->bind_param('issssss', $user_id, $first_name, $middle_name, $last_name, $email, $phone_number, $address);
        if (!$infoStmt->execute()) {
            throw new Exception('Failed to save staff details: ' . $infoStmt->error);
        }
        $infoStmt->close();

        $admin_name = $_SESSION['user_first_name'] ?? 'Admin';
        $details = "Added new staff: $first_name $last_name ($role, ID $user_id).";
        $log = $conn->prepare("INSERT INTO activity_logs (admin_name, action, details) VALUES (?, 'Add Staff', ?)");
        $log->bind_param('ss', $admin_name, $details);
        $log->execute();
        $log->close();

        $conn->commit();
        echo json_encode(['status' => 'success', 'user_id' => $user_id]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'msg' => "Unrecognized role '$role'."]);