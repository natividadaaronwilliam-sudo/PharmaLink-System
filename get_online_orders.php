<?php
// FILE: get_online_orders.php

header('Content-Type: application/json');
// Tiyaking tama ang path papunta sa inyong database connection file
require_once 'db_pharmacy.php'; 

// Kumuha ng Orders na HINDI pa 'Completed'
$sql = "
    SELECT 
        co.order_id, 
        co.order_date,
        co.order_status, 
        c.first_name, 
        c.last_name
    FROM 
        customer_orders co
    JOIN 
        customers c ON co.customer_id = c.customer_id
    WHERE 
        co.order_status IN ('Pending', 'Processing', 'Ready for Pickup')
    ORDER BY 
        co.order_date ASC
";

$result = $conn->query($sql);
$orders = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        // I-format ang order status para sa CSS class
        $status_class = strtolower(str_replace(' ', '-', $row['order_status']));
        
        $orders[] = [
            // I-sanitize ang order_id, kunin lang ang number kung may na prefix sa JS
            'order_id' => $row['order_id'], 
            'display_id' => 'ORD-' . $row['order_id'], // Para lang sa display
            'customer_name' => htmlspecialchars($row['first_name'] . ' ' . $row['last_name']),
            'status' => htmlspecialchars($row['order_status']),
            'status_class' => $status_class,
            'date' => date('M d, Y H:i A', strtotime($row['order_date']))
        ];
    }
    echo json_encode(['success' => true, 'orders' => $orders]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}

$conn->close();
?>