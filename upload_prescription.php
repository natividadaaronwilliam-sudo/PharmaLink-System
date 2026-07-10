<?php
/**
 * FILE: upload_prescription.php
 * Did not exist — get_prescriptions.php could list a customer's uploaded
 * prescriptions, but there was no endpoint to actually create one. OCR
 * processing (extracted_text / availability_summary) is out of scope here;
 * this stores the file and creates the row as 'pending' for pharmacist review.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
require_once 'db_pharmacy.php';

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id || strtolower($_SESSION['user_role'] ?? '') !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

if (empty($_FILES['prescription_file']['name']) || $_FILES['prescription_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Please choose a file to upload.']);
    exit;
}

$allowed = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
$ext = strtolower(pathinfo($_FILES['prescription_file']['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowed, true)) {
    echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, WEBP, or PDF files are allowed.']);
    exit;
}
if ($_FILES['prescription_file']['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'File must be under 5MB.']);
    exit;
}

$dir = __DIR__ . '/uploads/prescriptions';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}
$filename = 'rx_' . $user_id . '_' . time() . '.' . $ext;

if (!move_uploaded_file($_FILES['prescription_file']['tmp_name'], $dir . '/' . $filename)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save the uploaded file.']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO prescriptions (user_id, filename, ocr_status, created_at) VALUES (?, ?, 'pending', NOW())");
$stmt->bind_param('is', $user_id, $filename);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Prescription uploaded. A pharmacist will review it shortly.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save prescription record: ' . $stmt->error]);
}
$stmt->close();
$conn->close();