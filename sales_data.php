<?php
include 'db_pharmacy.php';

$result = $conn->query("SELECT DATE(date_created) AS date, SUM(total_amount) AS total FROM sales GROUP BY DATE(date_created) ORDER BY date ASC");

$data = [];
while($row = $result->fetch_assoc()){
    $data[] = ['date' => $row['date'], 'total' => (float)$row['total']];
}

echo json_encode($data);
?>
