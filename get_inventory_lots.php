<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
include 'db_pharmacy.php';

$status_filter = $_GET['status'] ?? 'active';
$exclude_expired = ($_GET['exclude_expired'] ?? '1') === '1';

$sql = "
    SELECT 
        l.lot_inventory_id, l.lot_number, l.expiration_date, l.current_stock, l.price,
        l.supplier, l.is_active,
        d.drug_id, d.generic_name, d.brand_name, d.dosage, d.form, d.category,
        d.minimum_stock, d.stock_status
    FROM inventory_lots l
    JOIN drugs_master d ON l.drug_id = d.drug_id
";

$where = [];
if ($status_filter === 'active') {
    $where[] = 'l.is_active = 1';
} elseif ($status_filter === 'archived') {
    $where[] = 'l.is_active = 0';
}
if ($exclude_expired) {
    $where[] = 'l.expiration_date >= CURDATE()';
}
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY l.expiration_date ASC';

$result = $conn->query($sql);
$inventory = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $row['current_stock'] = (int)$row['current_stock'];
        $row['minimum_stock'] = (int)$row['minimum_stock'];
        $row['price'] = (float)$row['price'];
        $row['is_active'] = (int)$row['is_active'];
        $inventory[] = $row;
    }
}

echo json_encode($inventory);
$conn->close();
