<?php
session_start();
header('Content-Type: application/json');
require_once 'db_pharmacy.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$current = $data['current_password'] ?? '';
$new = $data['new_password'] ?? '';
$confirm = $data['confirm_password'] ?? '';

if ($current === '' || $new === '' || $confirm === '') {
    echo json_encode(['success' => false, 'message' => 'All password fields are required.']);
    exit;
}
if (strlen($new) < 6) {
    echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters.']);
    exit;
}
if ($new !== $confirm) {
    echo json_encode(['success' => false, 'message' => 'New passwords do not match.']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$stmt = $conn->prepare('SELECT password FROM users WHERE user_id = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || !password_verify($current, $row['password'])) {
    echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
    exit;
}

$hash = password_hash($new, PASSWORD_BCRYPT);
$upd = $conn->prepare('UPDATE users SET password = ? WHERE user_id = ?');
$upd->bind_param('si', $hash, $user_id);
if ($upd->execute()) {
    echo json_encode(['success' => true, 'message' => 'Password updated successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update password.']);
}
$upd->close();
$conn->close();
