<?php
/**
 * FILE: update_staff.php
 * DID NOT EXIST — the "Edit Staff User" form in admin.php submits here.
 * Response uses `success`/`message` (this endpoint's JS reads res.success),
 * unlike user_actions.php which reads res.status/res.msg.
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

$user_id = (int)($_POST['user_id'] ?? 0);
$first_name = trim($_POST['first_name'] ?? '');
$middle_name = trim($_POST['middle_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone_number = trim($_POST['phone_number'] ?? '');
$address = trim($_POST['address'] ?? '');
$role = trim($_POST['role'] ?? '');
$password = $_POST['password'] ?? '';

if ($user_id <= 0 || $first_name === '' || $last_name === '' || $role === '') {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

$roleStmt = $conn->prepare("SELECT role_id FROM role WHERE role_name = ?");
$roleStmt->bind_param('s', $role);
$roleStmt->execute();
$roleRow = $roleStmt->get_result()->fetch_assoc();
$roleStmt->close();
if (!$roleRow) {
    echo json_encode(['success' => false, 'message' => "Role '$role' does not exist."]);
    exit;
}
$role_id = (int)$roleRow['role_id'];

$conn->begin_transaction();
try {
    $infoStmt = $conn->prepare(
        "UPDATE staff_info SET first_name=?, middle_name=?, last_name=?, email=?, phone_number=?, address=? WHERE user_id=?"
    );
    $infoStmt->bind_param('ssssssi', $first_name, $middle_name, $last_name, $email, $phone_number, $address, $user_id);
    if (!$infoStmt->execute()) {
        throw new Exception('Failed to update staff info: ' . $infoStmt->error);
    }
    $infoStmt->close();

    if ($password !== '') {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $userStmt = $conn->prepare("UPDATE users SET role_id=?, password=? WHERE user_id=?");
        $userStmt->bind_param('isi', $role_id, $hash, $user_id);
    } else {
        $userStmt = $conn->prepare("UPDATE users SET role_id=? WHERE user_id=?");
        $userStmt->bind_param('ii', $role_id, $user_id);
    }
    if (!$userStmt->execute()) {
        throw new Exception('Failed to update user account: ' . $userStmt->error);
    }
    $userStmt->close();

    $admin_name = $_SESSION['user_first_name'] ?? 'Admin';
    $details = "Updated staff ID $user_id ($first_name $last_name).";
    $log = $conn->prepare("INSERT INTO activity_logs (admin_name, action, details) VALUES (?, 'Update Staff', ?)");
    $log->bind_param('ss', $admin_name, $details);
    $log->execute();
    $log->close();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Staff updated successfully.']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}