<?php
header('Content-Type: application/json');
include 'db_pharmacy.php';

// ⭐ DITO ANG BAGONG QUERY ⭐
$sql = "SELECT 
            customer_id, 
            CONCAT_WS(' ', first_name, middle_name, last_name) AS name 
        FROM 
            customers 
        WHERE
            is_active = 1
        ORDER BY 
            last_name ASC"; // I-order muna natin sa Last Name

$result = $conn->query($sql);

$customers = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
}

echo json_encode($customers);
$conn->close();
