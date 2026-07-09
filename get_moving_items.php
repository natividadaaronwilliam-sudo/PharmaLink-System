<?php
header('Content-Type: application/json');
require_once 'db_pharmacy.php';

$days = 30;

// Fast-moving: highest quantity sold in last N days
$fast = [];
$stmt = $conn->prepare("
    SELECT d.generic_name, d.brand_name, COALESCE(SUM(si.quantity), 0) AS qty_sold
    FROM sales_items si
    JOIN sales s ON si.sale_id = s.sale_id
    JOIN drugs_master d ON si.drug_id = d.drug_id
    WHERE s.date_created >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
      AND s.status = 'completed'
    GROUP BY d.drug_id, d.generic_name, d.brand_name
    ORDER BY qty_sold DESC
    LIMIT 5
");
$stmt->bind_param('i', $days);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $fast[] = [
        'name' => trim($row['generic_name'] . ($row['brand_name'] ? ' (' . $row['brand_name'] . ')' : '')),
        'qty_sold' => (int)$row['qty_sold'],
    ];
}
$stmt->close();

// Slow-moving: active drugs with stock but zero/low sales in last 90 days
$slow = [];
$res2 = $conn->query("
    SELECT d.generic_name, d.brand_name, d.stock_status,
           COALESCE(SUM(il.current_stock), 0) AS on_hand
    FROM drugs_master d
    LEFT JOIN inventory_lots il ON d.drug_id = il.drug_id AND il.is_active = 1
    LEFT JOIN (
        SELECT si.drug_id, SUM(si.quantity) AS sold
        FROM sales_items si
        JOIN sales s ON si.sale_id = s.sale_id
        WHERE s.date_created >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
          AND s.status = 'completed'
        GROUP BY si.drug_id
    ) recent ON d.drug_id = recent.drug_id
    WHERE d.is_active = 1
    GROUP BY d.drug_id, d.generic_name, d.brand_name, d.stock_status
    HAVING on_hand > 0 AND COALESCE(recent.sold, 0) <= 2
    ORDER BY on_hand DESC, recent.sold ASC
    LIMIT 5
");
if ($res2) {
    while ($row = $res2->fetch_assoc()) {
        $slow[] = [
            'name' => trim($row['generic_name'] . ($row['brand_name'] ? ' (' . $row['brand_name'] . ')' : '')),
            'on_hand' => (int)$row['on_hand'],
            'stock_status' => $row['stock_status'] ?? 'ok',
        ];
    }
}

echo json_encode(['fast_moving' => $fast, 'slow_moving' => $slow]);
$conn->close();
