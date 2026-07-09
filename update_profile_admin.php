<?php
// update_profile_admin.php
// Saves the logged-in Admin's own profile edits (staff_info table).
session_start();
require_once 'db_pharmacy.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Admin') {
    echo json_encode(["success" => false, "message" => "Not authorized."]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    echo json_encode(["success" => false, "message" => "Invalid request body."]);
    exit;
}

$user_id      = $_SESSION['user_id'];
$first_name   = trim($data['first_name'] ?? '');
$middle_name  = trim($data['middle_name'] ?? '');
$last_name    = trim($data['last_name'] ?? '');
$email        = trim($data['email'] ?? '');
$phone_number = trim($data['phone_number'] ?? '');
$address      = trim($data['address'] ?? '');

if ($first_name === '' || $last_name === '') {
    echo json_encode(["success" => false, "message" => "First and last name are required."]);
    exit;
}

$stmt = $conn->prepare("UPDATE staff_info SET first_name=?, middle_name=?, last_name=?, email=?, phone_number=?, address=? WHERE user_id=?");
$stmt->bind_param("ssssssi", $first_name, $middle_name, $last_name, $email, $phone_number, $address, $user_id);

if ($stmt->execute()) {
    // Keep the session display name in sync so headers/greetings update immediately
    $_SESSION['user_first_name'] = $first_name;
    echo json_encode(["success" => true, "message" => "Profile updated successfully."]);
} else {
    echo json_encode(["success" => false, "message" => "Error updating profile: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
