<?php
// get_drug_master_details.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
include 'db_pharmacy.php'; 

$drug_id = $_GET['id'] ?? null;

if (!$drug_id) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Drug ID is required."]);
    exit;
}

$stmt = $conn->prepare("
    SELECT drug_id, generic_name, brand_name, dosage, form, category, minimum_stock, is_active
    FROM drugs_master 
    WHERE drug_id = ?
");
$stmt->bind_param("i", $drug_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $drug = $result->fetch_assoc();
    $drug['minimum_stock'] = (int)$drug['minimum_stock']; // Ensure type is correct
    echo json_encode(["success" => true, "drug" => $drug]);
} else {
    http_response_code(404);
    echo json_encode(["success" => false, "message" => "Drug definition not found."]);
}

$stmt->close();
$conn->close();
?>