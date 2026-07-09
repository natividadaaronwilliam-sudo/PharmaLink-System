<?php
header('Content-Type: application/json');
require_once 'db_pharmacy.php';

$start = $_GET['startDate'] ?? date('Y-m-d');
$end = $_GET['endDate'] ?? date('Y-m-d');

$stmt = $conn->prepare("
    SELECT CONCAT(d.generic_name, IF(d.brand_name IS NOT NULL AND d.brand_name != '', CONCAT(' (', d.brand_name, ')'), '')) AS label,
           COALESCE(SUM(si.quantity), 0) AS qty
    FROM sales_items si
    JOIN sales s ON si.sale_id = s.sale_id
    JOIN drugs_master d ON si.drug_id = d.drug_id
    WHERE DATE(s.date_created) BETWEEN ? AND ?
      AND s.status = 'completed'
    GROUP BY d.drug_id, d.generic_name, d.brand_name
    ORDER BY qty DESC
    LIMIT 8
");
$stmt->bind_param('ss', $start, $end);
$stmt->execute();
$res = $stmt->get_result();

$labels = [];
$data = [];
while ($row = $res->fetch_assoc()) {
    $labels[] = $row['label'];
    $data[] = (int)$row['qty'];
}

echo json_encode(['labels' => $labels, 'data' => $data]);
$stmt->close();
$conn->close();
