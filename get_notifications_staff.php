<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db_pharmacy.php';

$user_role = $_SESSION['user_role'] ?? null;
if (!$user_role || !in_array($user_role, ['Admin', 'Cashier/Pharmacist'])) {
    echo json_encode(['count' => 0, 'notifications' => []]);
    exit;
}

$notifications = [];

// Low stock — drugs_master.stock_status = 'low' (not out of stock)
$sql_low = "
    SELECT dm.generic_name, dm.brand_name, dm.minimum_stock,
           COALESCE(SUM(il.current_stock), 0) AS on_hand
    FROM drugs_master dm
    LEFT JOIN inventory_lots il ON dm.drug_id = il.drug_id AND il.is_active = 1
    WHERE dm.is_active = 1 AND dm.stock_status = 'low'
    GROUP BY dm.drug_id, dm.generic_name, dm.brand_name, dm.minimum_stock
    ORDER BY on_hand ASC
    LIMIT 10
";
$result = $conn->query($sql_low);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $notifications[] = [
            'type' => 'low_stock',
            'icon' => 'fa-box-open',
            'message' => "Low stock: {$row['generic_name']} ({$row['brand_name']}) — {$row['on_hand']} left (min {$row['minimum_stock']}). Reorder soon.",
        ];
    }
}

// Out of stock alert
$sql_out = "
    SELECT generic_name, brand_name
    FROM drugs_master
    WHERE is_active = 1 AND stock_status = 'out'
    LIMIT 5
";
$result_out = $conn->query($sql_out);
if ($result_out) {
    while ($row = $result_out->fetch_assoc()) {
        $notifications[] = [
            'type' => 'out_of_stock',
            'icon' => 'fa-circle-xmark',
            'message' => "Out of stock: {$row['generic_name']} ({$row['brand_name']}) — restock immediately.",
        ];
    }
}

// Expiring within 30 days (non-expired lots with stock)
$sql_expiring = "
    SELECT il.lot_number, il.expiration_date, d.generic_name, d.brand_name,
           DATEDIFF(il.expiration_date, CURDATE()) AS days_left
    FROM inventory_lots il
    JOIN drugs_master d ON il.drug_id = d.drug_id
    WHERE il.is_active = 1
      AND il.current_stock > 0
      AND il.expiration_date > CURDATE()
      AND il.expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY il.expiration_date ASC
    LIMIT 8
";
$result2 = $conn->query($sql_expiring);
if ($result2) {
    while ($row = $result2->fetch_assoc()) {
        $notifications[] = [
            'type' => 'expiring',
            'icon' => 'fa-triangle-exclamation',
            'message' => "Expiring soon: {$row['generic_name']} ({$row['brand_name']}), lot {$row['lot_number']} — {$row['days_left']} day(s) left.",
        ];
    }
}

echo json_encode(['count' => count($notifications), 'notifications' => $notifications]);
$conn->close();
