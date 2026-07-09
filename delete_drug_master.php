<?php
// delete_drug_master.php with activity logging
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
include 'db_pharmacy.php'; 

// Start session for admin info
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$drug_id = $_GET['id'] ?? null;

if (!$drug_id) {
    echo json_encode(["success" => false, "message" => "Drug ID is required."]);
    exit;
}

// Delete the drug
$stmt = $conn->prepare("DELETE FROM drugs_master WHERE drug_id = ?");
$stmt->bind_param("i", $drug_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        // Activity Logging
        $admin_name = $_SESSION['user_first_name'] ?? 'Admin';
        $action = "Delete Drug";
        $details = "Deleted drug with ID: {$drug_id}";

        $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_name, action, details) VALUES (?, ?, ?)");
        $logStmt->bind_param("sss", $admin_name, $action, $details);
        $logStmt->execute();
        $logStmt->close();

        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "message" => "No drug found with that ID."]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Database Error: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
