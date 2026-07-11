<?php
/**
 * FILE: update_customer_profile.php
 * Did not exist — the customer Profile page had nowhere to actually save
 * edits (name/email/phone/address/photo) to, so "profile reflections" could
 * never be accurate: whatever was typed in never reached the database.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
require_once 'db_pharmacy.php';

$customer_id = $_SESSION['user_id'] ?? null;
if (!$customer_id || strtolower($_SESSION['user_role'] ?? '') !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}
$customer_id = (int)$customer_id;

$first_name = trim($_POST['first_name'] ?? '');
$middle_name = trim($_POST['middle_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone_number = trim($_POST['phone_number'] ?? '');
$address = trim($_POST['address'] ?? '');

if ($first_name === '' || $last_name === '' || $email === '') {
    echo json_encode(['success' => false, 'message' => 'First name, last name, and email are required.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}

$profile_image_sql = '';
$profile_image_path = null;

// Optional profile picture upload
if (!empty($_FILES['profile_image']['name']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, or WEBP images are allowed.']);
        exit;
    }
    if ($_FILES['profile_image']['size'] > 3 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Image must be under 3MB.']);
        exit;
    }
    $dir = __DIR__ . '/uploads/profile_pictures';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $filename = 'customer_' . $customer_id . '_' . time() . '.' . $ext;
    if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $dir . '/' . $filename)) {
        $profile_image_path = 'uploads/profile_pictures/' . $filename;
    }
}

if ($profile_image_path) {
    $stmt = $conn->prepare(
        "UPDATE customers SET first_name=?, middle_name=?, last_name=?, email=?, phone_number=?, address=?, profile_image=? WHERE customer_id=?"
    );
    $stmt->bind_param('sssssssi', $first_name, $middle_name, $last_name, $email, $phone_number, $address, $profile_image_path, $customer_id);
} else {
    $stmt = $conn->prepare(
        "UPDATE customers SET first_name=?, middle_name=?, last_name=?, email=?, phone_number=?, address=? WHERE customer_id=?"
    );
    $stmt->bind_param('ssssssi', $first_name, $middle_name, $last_name, $email, $phone_number, $address, $customer_id);
}

if ($stmt->execute()) {
    $freshStmt = $conn->prepare("SELECT first_name, middle_name, last_name, email, phone_number, address, customer_type, loyalty_points, profile_image, username FROM customers WHERE customer_id = ? LIMIT 1");
    $freshStmt->bind_param('i', $customer_id);
    $freshStmt->execute();
    $customer = $freshStmt->get_result()->fetch_assoc() ?: [];
    $freshStmt->close();

    // Keep the session's display name in sync so the header greeting is
    // immediately correct without needing to log out and back in.
    $_SESSION['user_first_name'] = $customer['first_name'] ?? $first_name;

    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully.',
        'profile_image' => $customer['profile_image'] ?? $profile_image_path,
        'customer' => $customer
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update profile: ' . $stmt->error]);
}
$stmt->close();
$conn->close();