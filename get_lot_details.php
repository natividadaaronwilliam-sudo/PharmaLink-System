<?php
// get_lot_details.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

// *** OPTIONAL TEMPORARY DEBUGGING: Uncomment to see PHP errors ***
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

include 'db_pharmacy.php'; // Your database connection file

// Check for database connection failure immediately
if (!$conn) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database connection failed."]);
    exit;
}

$lot_id = $_GET['id'] ?? null;

if (!$lot_id) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Lot ID is required."]);
    exit;
}

// SQL to fetch the lot details, INCLUDING the critical drug_master fields
$sql = "
    SELECT 
        l.lot_inventory_id,
        l.lot_number,
        l.expiration_date,
        l.current_stock,
        l.price,
        l.supplier,
        d.generic_name,
        d.dosage,
        d.form,
        d.category,             /* ADDED */
        d.minimum_stock         /* ADDED: This was likely the cause of previous errors in other functions */
    FROM 
        inventory_lots l
    JOIN 
        drugs_master d ON l.drug_id = d.drug_id
    WHERE 
        l.lot_inventory_id = ?
";

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "SQL Prepare Failed: " . $conn->error]);
    exit;
}

$stmt->bind_param("i", $lot_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $lot = $result->fetch_assoc();
    
    // Ensure numeric types are explicit for JavaScript
    $lot['current_stock'] = (int)$lot['current_stock'];
    $lot['price'] = (float)$lot['price'];
    $lot['minimum_stock'] = (int)$lot['minimum_stock'];
    
    echo json_encode(["success" => true, "lot" => $lot]);
} else {
    http_response_code(404);
    echo json_encode(["success" => false, "message" => "Lot not found or linked drug details missing."]);
}

$stmt->close();
$conn->close();
?>