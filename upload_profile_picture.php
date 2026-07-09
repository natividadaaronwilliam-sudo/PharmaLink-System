<?php
// upload_profile_picture.php
// Shared avatar upload handler for Admin, Cashier, and Customer profile pages.
// Saves the file under uploads/profile_pictures/ and stores the relative
// path in staff_info.profile_image (Admin/Cashier) or customers.profile_image (Customer).
session_start();
require_once 'db_pharmacy.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    echo json_encode(["success" => false, "message" => "Not authorized."]);
    exit;
}

if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(["success" => false, "message" => "No file uploaded or upload error."]);
    exit;
}

$file = $_FILES['profile_image'];

// Validate type
$allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!isset($allowed[$mime])) {
    echo json_encode(["success" => false, "message" => "Unsupported file type. Use PNG, JPG, WEBP, or GIF."]);
    exit;
}

// Validate size (max 5MB)
if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(["success" => false, "message" => "File too large (max 5MB)."]);
    exit;
}

$uploadDir = __DIR__ . '/uploads/profile_pictures/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['user_role'];
$ext = $allowed[$mime];
$role_prefix = ($role === 'Customer') ? 'customer_' : 'staff_';
$filename = 'profile_' . $role_prefix . $user_id . '_' . time() . '.' . $ext;
$destPath = $uploadDir . $filename;
$relativePath = 'uploads/profile_pictures/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    echo json_encode(["success" => false, "message" => "Failed to save uploaded file."]);
    exit;
}

if ($role === 'Customer') {
    $stmt = $conn->prepare("UPDATE customers SET profile_image = ? WHERE customer_id = ?");
} else {
    // Admin or Cashier/Pharmacist
    $stmt = $conn->prepare("UPDATE staff_info SET profile_image = ? WHERE user_id = ?");
}
$stmt->bind_param("si", $relativePath, $user_id);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "path" => $relativePath]);
} else {
    echo json_encode(["success" => false, "message" => "Error saving picture: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
