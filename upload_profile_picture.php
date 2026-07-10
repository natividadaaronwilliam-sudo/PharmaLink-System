<?php
/**
 * FILE: upload_profile_picture.php
 * DID NOT EXIST — used by both admin.php and cashier.php's profile avatar
 * upload input. Shared because both roles store their photo the same way
 * (staff_info.profile_image, keyed by the logged-in session's user_id).
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

if (empty($_FILES['profile_image']['name']) || $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Please choose an image to upload.']);
    exit;
}

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
$filename = 'staff_' . $user_id . '_' . time() . '.' . $ext;

if (!move_uploaded_file($_FILES['profile_image']['tmp_name'], $dir . '/' . $filename)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save the uploaded file.']);
    exit;
}

$path = 'uploads/profile_pictures/' . $filename;
$stmt = $conn->prepare("UPDATE staff_info SET profile_image = ? WHERE user_id = ?");
$stmt->bind_param('si', $path, $user_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'path' => $path]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save profile picture: ' . $stmt->error]);
}
$stmt->close();