<?php
header('Content-Type: application/json');
include 'db_pharmacy.php';

$sql = "SELECT 
            dm.drug_id, dm.category, dm.brand_name, dm.generic_name,
            dm.dosage, dm.form, il.price, il.lot_inventory_id, il.current_stock
        FROM drugs_master dm
        JOIN inventory_lots il ON dm.drug_id = il.drug_id
        WHERE il.current_stock > 0
          AND il.is_active = 1
          AND il.expiration_date >= CURDATE()
          AND dm.is_active = 1
        ORDER BY il.expiration_date ASC";

$result = $conn->query($sql);
$products = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $row['category'] = ucwords(strtolower($row['category']));
        $row['generic_name'] = ucwords(strtolower($row['generic_name']));
        $row['brand_name'] = ucwords(strtolower($row['brand_name']));
        $row['form'] = ucwords(strtolower($row['form']));
        $products[] = $row;
    }
}

echo json_encode($products);
