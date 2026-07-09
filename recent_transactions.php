<?php
// recent_transactions.php - Kumuha ng huling 5 transactions para sa Dashboard

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

// I-include ang DB connection file. Assume it creates $conn.
include 'db_pharmacy.php'; 

// Tiyakin na ang $conn ay valid
if (!$conn) {
    echo json_encode(["status" => "error", "message" => "Database connection failed."]);
    exit;
}

$start_date = $_GET['startDate'] ?? date('Y-m-d', strtotime('-30 days')); 
$end_date = $_GET['endDate'] ?? date('Y-m-d'); 

$sql = "
    SELECT 
        s.date_created, 
        -- ⭐ FIXED: Pagsamahin ang First Name at Last Name ⭐
        COALESCE(CONCAT(c.first_name, ' ', c.last_name), 'Guest') AS customer_name,
        s.total_amount,
        s.status
    FROM 
        sales s
    LEFT JOIN 
        customers c ON s.customer_id = c.customer_id
    WHERE 
        DATE(s.date_created) BETWEEN ? AND ?
    ORDER BY 
        s.date_created DESC
    LIMIT 5
";

// Ginamit ang Prepared Statements para sa seguridad at mas matibay na code.
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(["status" => "error", "message" => "SQL Prepare failed: " . $conn->error]);
    exit;
}
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
$transactions = [];

while ($row = $result->fetch_assoc()) {
    $row['time_display'] = date('H:i', strtotime($row['date_created'])); 
    $row['total_amount'] = number_format((float)$row['total_amount'], 2, '.', '');
    $transactions[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode($transactions);
?>