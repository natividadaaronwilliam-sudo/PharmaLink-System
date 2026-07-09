<?php
header('Content-Type: application/json');
require_once 'db_pharmacy.php';

$year = (int)($_GET['year'] ?? date('Y'));
if ($year < 2000 || $year > 2100) {
    $year = (int)date('Y');
}

// Monthly sales from POS (sales table)
$monthlyLabels = [];
$monthlyData = [];
$stmt = $conn->prepare("
    SELECT MONTH(date_created) AS m, COALESCE(SUM(total_amount), 0) AS total
    FROM sales
    WHERE YEAR(date_created) = ? AND status = 'completed'
    GROUP BY MONTH(date_created)
    ORDER BY MONTH(date_created)
");
$stmt->bind_param('i', $year);
$stmt->execute();
$monthlyRows = [];
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $monthlyRows[(int)$row['m']] = (float)$row['total'];
}
$stmt->close();

for ($m = 1; $m <= 12; $m++) {
    $monthlyLabels[] = date('M Y', mktime(0, 0, 0, $m, 1, $year));
    $monthlyData[] = $monthlyRows[$m] ?? 0;
}

// Top categories from sales_items
$catLabels = [];
$catData = [];
$stmt2 = $conn->prepare("
    SELECT d.category, COALESCE(SUM(si.quantity), 0) AS total_qty
    FROM sales_items si
    JOIN sales s ON si.sale_id = s.sale_id
    JOIN drugs_master d ON si.drug_id = d.drug_id
    WHERE YEAR(s.date_created) = ? AND s.status = 'completed'
    GROUP BY d.category
    ORDER BY total_qty DESC
    LIMIT 8
");
$stmt2->bind_param('i', $year);
$stmt2->execute();
$res2 = $stmt2->get_result();
while ($row = $res2->fetch_assoc()) {
    $catLabels[] = ucfirst($row['category'] ?? 'Other');
    $catData[] = (int)$row['total_qty'];
}
$stmt2->close();

echo json_encode([
    'year' => $year,
    'monthly_labels' => $monthlyLabels,
    'monthly_data' => $monthlyData,
    'category_labels' => $catLabels,
    'category_data' => $catData,
]);
$conn->close();
