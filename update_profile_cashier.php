<?php
/**
 * FILE: update_profile_cashier.php
 * DID NOT EXIST — the Cashier "Save" button on the Profile page submits here.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
require_once 'db_pharmacy.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['Cashier/Pharmacist', 'Admin'], true)) {
    echo json_encode(['success' => false, 'message' => 'Not authorized.']);
    exit;
}
$user_id = (int)$_SESSION['user_id'];

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$first_name = trim($data['first_name'] ?? '');
$middle_name = trim($data['middle_name'] ?? '');
$last_name = trim($data['last_name'] ?? '');
$email = trim($data['email'] ?? '');
$phone_number = trim($data['phone_number'] ?? '');
$address = trim($data['address'] ?? '');

if ($first_name === '' || $last_name === '') {
    echo json_encode(['success' => false, 'message' => 'First and last name are required.']);
    exit;
}

$stmt = $conn->prepare(
    "UPDATE staff_info SET first_name=?, middle_name=?, last_name=?, email=?, phone_number=?, address=? WHERE user_id=?"
);
$stmt->bind_param('ssssssi', $first_name, $middle_name, $last_name, $email, $phone_number, $address, $user_id);

try {
    $stmt->execute();
    $stmt->close();

    // A MySQL UPDATE that matches zero rows is NOT an error — it just quietly
    // changes nothing. Without this check, a mismatched user_id looks exactly
    // like a successful save from the frontend's point of view.
    $checkStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM staff_info WHERE user_id = ?");
    $checkStmt->bind_param('i', $user_id);
    $checkStmt->execute();
    $exists = (int) $checkStmt->get_result()->fetch_assoc()['cnt'];
    $checkStmt->close();

    if ($exists === 0) {
        echo json_encode([
            'success' => false,
            'message' => "No staff profile record found for this account (user_id={$user_id}). Nothing was saved — check that a matching row exists in staff_info.",
        ]);
        exit;
    }

    $_SESSION['user_first_name'] = $first_name;
    echo json_encode(['success' => true, 'message' => 'Profile updated successfully.']);
} catch (mysqli_sql_exception $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to update profile: ' . $e->getMessage()]);
}