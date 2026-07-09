<?php
require 'db_pharmacy.php';
header('Content-Type: application/json');

$query = "
    SELECT 
        s.supplier_id, s.supplier_name, s.contact_number, s.email,
        s.address, s.status, s.inactive_reason,
        GROUP_CONCAT(DISTINCT d.generic_name SEPARATOR ', ') AS medicines
    FROM suppliers s
    LEFT JOIN inventory_lots i ON s.supplier_id = i.supplier
    LEFT JOIN drugs_master d ON i.drug_id = d.drug_id
    GROUP BY s.supplier_id
";

$result = $conn->query($query);
$suppliers = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $row['medicines_supplied'] = $row['medicines'] ? explode(', ', $row['medicines']) : [];
        unset($row['medicines']);
        $suppliers[] = $row;
    }
}

echo json_encode($suppliers);
$conn->close();
