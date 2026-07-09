<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db_pharmacy.php';

$user_id = (int)($_SESSION['user_id'] ?? 0);
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

if (!isset($_FILES['prescription_file']) || $_FILES['prescription_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error.']);
    exit;
}

$file = $_FILES['prescription_file'];
$allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
if (!in_array($file['type'], $allowed, true)) {
    echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, WEBP, or PDF allowed.']);
    exit;
}

$uploadDir = __DIR__ . '/uploads/prescriptions/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'rx_' . $user_id . '_' . time() . '.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext);
$dest = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save file.']);
    exit;
}

$stmt = $conn->prepare('INSERT INTO prescriptions (user_id, filename, extracted_text) VALUES (?, ?, ?)');
$note = 'Uploaded by customer';
$stmt->bind_param('iss', $user_id, $filename, $note);
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Prescription uploaded successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error saving prescription.']);
}
$stmt->close();
$conn->close();
