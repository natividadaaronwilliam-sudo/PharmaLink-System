<?php
/**
 * FILE: update_profile_admin.php
 * DID NOT EXIST — the Admin "Save" button on the Profile page submits here.
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
    "INSERT INTO staff_info (user_id, first_name, middle_name, last_name, email, phone_number, address)
     VALUES (?, ?, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
        first_name = VALUES(first_name),
        middle_name = VALUES(middle_name),
        last_name = VALUES(last_name),
        email = VALUES(email),
        phone_number = VALUES(phone_number),
        address = VALUES(address)"
);
$stmt->bind_param('issssss', $user_id, $first_name, $middle_name, $last_name, $email, $phone_number, $address);

try {
    $stmt->execute();
    $stmt->close();

    // Don't just trust the write — read the row back from the DB and compare
    // against what we tried to save. This is what actually guarantees "saved"
    // means saved, instead of reporting success based on the query not
    // throwing an error (which is exactly how the old bug went unnoticed:
    // the UPDATE ran "successfully" while quietly touching zero rows).
    $verifyStmt = $conn->prepare(
        "SELECT first_name, middle_name, last_name, email, phone_number, address FROM staff_info WHERE user_id = ?"
    );
    $verifyStmt->bind_param('i', $user_id);
    $verifyStmt->execute();
    $saved = $verifyStmt->get_result()->fetch_assoc();
    $verifyStmt->close();

    $matches = $saved
        && $saved['first_name'] === $first_name
        && $saved['middle_name'] === $middle_name
        && $saved['last_name'] === $last_name
        && $saved['email'] === $email
        && $saved['phone_number'] === $phone_number
        && $saved['address'] === $address;

    if (!$matches) {
        echo json_encode([
            'success' => false,
            'message' => 'Save did not persist correctly. Please try again or contact support.',
        ]);
        exit;
    }

    $_SESSION['user_first_name'] = $saved['first_name'];
    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully.',
        'first_name' => $saved['first_name'],
        'middle_name' => $saved['middle_name'],
        'last_name' => $saved['last_name'],
        'email' => $saved['email'],
        'phone_number' => $saved['phone_number'],
        'address' => $saved['address'],
    ]);
} catch (mysqli_sql_exception $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to update profile: ' . $e->getMessage()]);
}