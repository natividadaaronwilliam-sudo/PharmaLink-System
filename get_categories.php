<?php
// get_categories.php
header("Content-Type: application/json");

include 'db_pharmacy.php'; // I-include ang iyong database connection

// Fetch distinct categories from drugs_master table
$sql = "SELECT DISTINCT category FROM drugs_master ORDER BY category ASC";
$result = $conn->query($sql);

$categories = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Optional: Ensure category names are in Title Case before returning
        $row['category'] = ucwords(strtolower($row['category'])); 
        
        // Huwag isama ang blank o null categories
        if (!empty($row['category'])) {
            $categories[] = $row['category'];
        }
    }
}

$conn->close();

echo json_encode($categories);
?>