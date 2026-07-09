<?php
// deactivate_drug_master.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
include 'db_pharmacy.php'; 

if (!$conn) {
    echo json_encode(["success" => false, "message" => "Database connection failed."]);
    exit;
}

$drug_id = $_GET['id'] ?? null;

if (!$drug_id) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Drug ID is required."]);
    exit;
}

// STEP 1: Fetch the drug name first (for activity log)
$drugQuery = $conn->prepare("SELECT generic_name, brand_name FROM drugs_master WHERE drug_id = ?");
$drugQuery->bind_param("i", $drug_id);
$drugQuery->execute();
$drugResult = $drugQuery->get_result();

if ($drugResult->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "Drug not found."]);
    exit;
}

$drugData = $drugResult->fetch_assoc();
$drugName = $drugData['generic_name'];
$brandName = $drugData['brand_name'] ? " ({$drugData['brand_name']})" : "";
$fullDrugName = $drugName . $brandName; // Example: Paracetamol (Biogesic)
$drugQuery->close();


// STEP 2: Perform soft delete
$sql = "UPDATE drugs_master SET is_active = 0 WHERE drug_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $drug_id);

if ($stmt->execute()) {

    // STEP 3: Log activity
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $admin_name = $_SESSION['user_first_name'] ?? 'Admin';

    $action = "Deactivate Drug";
    $details = "Drug '{$fullDrugName}' (ID: {$drug_id}) has been deactivated.";

    $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_name, action, details) VALUES (?, ?, ?)");
    $logStmt->bind_param("sss", $admin_name, $action, $details);
    $logStmt->execute();
    $logStmt->close();

    echo json_encode(["success" => true, "message" => "Drug archived successfully."]);
} 
else {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database Error: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
