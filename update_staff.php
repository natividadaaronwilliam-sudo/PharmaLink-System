<?php
// update_staff.php
// Handles the "Update Staff" form submitted from admin.php (Edit Staff modal).
// Updates users (role_id, optional password) + staff_info (name/contact fields).
require_once 'db_pharmacy.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_POST['user_id'])) {
    echo json_encode(["success" => false, "message" => "Invalid request."]);
    exit;
}

$user_id      = intval($_POST['user_id']);
$first_name   = trim($_POST['first_name'] ?? '');
$middle_name  = trim($_POST['middle_name'] ?? '');
$last_name    = trim($_POST['last_name'] ?? '');
$email        = trim($_POST['email'] ?? '');
$phone_number = trim($_POST['phone_number'] ?? '');
$address      = trim($_POST['address'] ?? '');
$role_name    = trim($_POST['role'] ?? '');
$password     = $_POST['password'] ?? '';

if ($first_name === '' || $last_name === '' || $role_name === '') {
    echo json_encode(["success" => false, "message" => "Please fill in all required fields."]);
    exit;
}

// Resolve role_id from role table (don't hardcode ids)
$role_id = null;
$roleStmt = $conn->prepare("SELECT role_id FROM role WHERE role_name = ?");
$roleStmt->bind_param("s", $role_name);
$roleStmt->execute();
$roleResult = $roleStmt->get_result();
if ($roleResult->num_rows === 1) {
    $role_id = $roleResult->fetch_assoc()['role_id'];
}
$roleStmt->close();

if ($role_id === null) {
    echo json_encode(["success" => false, "message" => "Invalid role selected."]);
    exit;
}

$conn->begin_transaction();

try {
    // 1. Update staff_info (name/contact details)
    $stmt = $conn->prepare("UPDATE staff_info SET first_name = ?, middle_name = ?, last_name = ?, email = ?, phone_number = ?, address = ? WHERE user_id = ?");
    $stmt->bind_param("ssssssi", $first_name, $middle_name, $last_name, $email, $phone_number, $address, $user_id);
    $stmt->execute();
    $stmt->close();

    // 2. Update users (role, and password only if a new one was provided)
    if ($password !== '') {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET role_id = ?, password = ? WHERE user_id = ?");
        $stmt->bind_param("isi", $role_id, $hashed, $user_id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET role_id = ? WHERE user_id = ?");
        $stmt->bind_param("ii", $role_id, $user_id);
    }
    $stmt->execute();
    $stmt->close();

    // 3. Activity log (same pattern used by deactivate_staff.php)
    $admin_name = $_SESSION['user_first_name'] ?? 'Admin';
    $action = "Edit Staff";
    $details = "Staff account ID {$user_id} was updated.";
    $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_name, action, details) VALUES (?, ?, ?)");
    $logStmt->bind_param("sss", $admin_name, $action, $details);
    $logStmt->execute();
    $logStmt->close();

    $conn->commit();
    echo json_encode(["success" => true, "message" => "Staff account updated successfully."]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["success" => false, "message" => "Error updating staff: " . $e->getMessage()]);
}

$conn->close();
?>
