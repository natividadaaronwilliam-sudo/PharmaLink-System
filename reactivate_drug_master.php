<?php
// reactivate_drug_master.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

// Include your database connection file
include 'db_pharmacy.php'; 

// Check for database connection failure
if (!$conn) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database connection failed."]);
    exit;
}

// Get the drug ID from the URL query parameter
$drug_id = $_GET['id'] ?? null;

// Optionally, get the admin/user performing the action
$admin_id = $_GET['admin_id'] ?? null; // or from session if logged in

if (!$drug_id) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Drug ID is required."]);
    exit;
}

// SQL to REACTIVATE the drug (set is_active back to 1)
$sql = "UPDATE drugs_master SET is_active = 1 WHERE drug_id = ?";
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "SQL Prepare Failed: " . $conn->error]);
    exit;
}

// Bind the drug_id as an integer (i)
$stmt->bind_param("i", $drug_id);

if ($stmt->execute()) {
    // Insert activity log
    if ($admin_id) {
        $activity_sql = "INSERT INTO activity_logs (admin_id, action, details, date) VALUES (?, ?, ?, NOW())";
        $activity_stmt = $conn->prepare($activity_sql);
        if ($activity_stmt) {
            $action = "Reactivate Drug";
            $details = "Reactivated drug_master ID: $drug_id";
            $activity_stmt->bind_param("iss", $admin_id, $action, $details);
            $activity_stmt->execute();
            $activity_stmt->close();
        }
    }

    echo json_encode(["success" => true, "message" => "Drug definition reactivated successfully."]);
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database Error: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
