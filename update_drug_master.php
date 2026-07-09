<?php
// update_drug_master.php with activity logging
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
include 'db_pharmacy.php'; 

// Start session for admin info
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); 
    echo json_encode(["success" => false, "message" => "Method not supported. Use POST."]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !isset($data['drug_id'])) {
    http_response_code(400); 
    echo json_encode(["success" => false, "message" => "Invalid data. Drug ID is required."]);
    exit;
}

$drug_id = (int)$data['drug_id'];
$generic_name = $data['generic_name'];
$brand_name = $data['brand_name'];
$dosage = $data['dosage'];
$form = $data['form'];
$category = $data['category'];
$minimum_stock = (int)$data['minimum_stock'];

$sql = "
    UPDATE drugs_master
    SET 
        generic_name = ?, 
        brand_name = ?, 
        dosage = ?, 
        form = ?, 
        category = ?, 
        minimum_stock = ?
    WHERE 
        drug_id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param(
    "sssssii",
    $generic_name,
    $brand_name,
    $dosage,
    $form,
    $category,
    $minimum_stock,
    $drug_id
);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0 || $stmt->errno === 0) {
        // Activity Logging
        $admin_name = $_SESSION['user_first_name'] ?? 'Admin';
        $action = "Update Drug";
        $details = "Updated drug ID {$drug_id}: {$generic_name} ({$dosage}, {$form})";
        $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_name, action, details) VALUES (?, ?, ?)");
        $logStmt->bind_param("sss", $admin_name, $action, $details);
        $logStmt->execute();
        $logStmt->close();

        echo json_encode(["success" => true, "message" => "Drug definition updated successfully."]);
    } else {
        echo json_encode(["success" => false, "message" => "No changes made or Drug ID not found."]);
    }
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database Error: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
