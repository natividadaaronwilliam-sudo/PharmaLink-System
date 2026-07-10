<?php
// FILE: get_prescriptions.php
// Returns the currently-logged-in customer's prescription upload history.

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'db_pharmacy.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

$stmt = $conn->prepare("SELECT id, filename, extracted_text, availability_summary, ocr_status, created_at FROM prescriptions WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) {
    $row['availability_summary'] = $row['availability_summary'] ? json_decode($row['availability_summary'], true) : [];
    $rows[] = $row;
}

echo json_encode(['success' => true, 'prescriptions' => $rows]);
$stmt->close();
$conn->close();