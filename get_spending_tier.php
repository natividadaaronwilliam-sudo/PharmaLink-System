<?php
include 'db_connection.php';

$sql = "
    SELECT 
        c.customer_id,
        SUM(co.total_amount) AS total_spent
    FROM customers c
    LEFT JOIN customer_orders co ON c.customer_id = co.customer_id
    GROUP BY c.customer_id
";

$result = $conn->query($sql);

$low = 0;
$medium = 0;
$high = 0;

while ($row = $result->fetch_assoc()) {
    $spent = floatval($row['total_spent']);

    if ($spent == 0) {
        // No purchase yet → count as low spender
        $low++;
    } elseif ($spent < 200) {
        $low++;
    } elseif ($spent < 500) {
        $medium++;
    } else {
        $high++;
    }
}

echo json_encode([
    "low" => $low,
    "medium" => $medium,
    "high" => $high
]);
?>
