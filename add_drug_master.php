<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
include 'db_pharmacy.php'; 

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode(["success" => false, "message" => "No data received"]);
    exit;
}

// Insert the new drug
$stmt = $conn->prepare("
    INSERT INTO drugs_master (generic_name, brand_name, dosage, form, category, minimum_stock)
    VALUES (?, ?, ?, ?, ?, ?)
");
$stmt->bind_param(
    "sssssi",
    $data['generic_name'],
    $data['brand_name'],
    $data['dosage'],
    $data['form'],
    $data['category'],
    $data['minimum_stock']
);

if ($stmt->execute()) {
    $drug_id = $conn->insert_id;

    // Activity Logging
    $admin_name = $_SESSION['user_first_name'] ?? 'Admin';
    $action = "Add Drug";
    $details = "Added new drug: {$data['generic_name']} ({$data['dosage']}, {$data['form']})";

    $logStmt = $conn->prepare("
        INSERT INTO activity_logs (admin_name, action, details)
        VALUES (?, ?, ?)
    ");
    $logStmt->bind_param("sss", $admin_name, $action, $details);
    $logStmt->execute();
    $logStmt->close();

    echo json_encode(["success" => true, "id" => $drug_id]);
} else {
    $error_msg = strpos($stmt->error, 'Duplicate entry') !== false 
                 ? "Error: This exact drug (Generic Name, Dosage, Form) already exists." 
                 : "Database Error: " . $stmt->error;
    
    echo json_encode(["success" => false, "message" => $error_msg]);
}

$stmt->close();
$conn->close();
?>
