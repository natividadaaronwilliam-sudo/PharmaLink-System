<?php
// get_drugs_master.php (COMPLETE)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

// Ensure this file path is correct
include 'db_pharmacy.php'; 

// Check for database connection failure immediately
if (!$conn) {
    // If connection fails, log the error and return a JSON error
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database connection failed."]);
    exit;
}

// 1. Check for filter status
$status_filter = $_GET['status'] ?? 'active'; // Default to 'active'

// Base SQL to fetch all standardized drugs, including the status column
$sql = "SELECT drug_id, generic_name, brand_name, dosage, form, category, minimum_stock, is_active 
        FROM drugs_master ";
        
// 2. Conditionally add the WHERE clause
if ($status_filter === 'active') {
    // Only show drugs where is_active is 1
    $sql .= "WHERE is_active = 1 ";
}
// If $status_filter is 'all', the WHERE clause is skipped, fetching all records.

// 3. Add the ORDER BY clause
$sql .= "ORDER BY generic_name ASC";


// Execute the final query
$result = $conn->query($sql);

$drugs = [];

if ($result === false) {
    // Handle SQL execution error
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "SQL Error: " . $conn->error]);
    $conn->close();
    exit;
}

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        // Ensure minimum_stock and is_active are treated as integers for JavaScript
        $row['minimum_stock'] = (int)$row['minimum_stock'];
        $row['is_active'] = (int)$row['is_active']; 
        $drugs[] = $row;
    }
}

// Output the data as JSON
echo json_encode($drugs);

$conn->close();
?>