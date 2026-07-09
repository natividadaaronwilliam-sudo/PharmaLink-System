<?php
header("Content-Type: application/json");
require_once 'db_pharmacy.php';

$labels = [];
$data = [];

for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i day"));
    $labels[] = date('D', strtotime($day));

    $stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) AS total FROM sales WHERE DATE(date_created) = ? AND status = 'completed'");
    $stmt->bind_param("s", $day);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $data[] = round((float)($row['total'] ?? 0), 2);
    $stmt->close();
}

echo json_encode(['labels' => $labels, 'data' => $data]);
$conn->close();
