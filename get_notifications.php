<?php
// FILE: includes/get_notifications.php

header('Content-Type: application/json');
// Tiyakin na ang db_pharmacy.php ay konektado at naglalaman ng $conn
include 'db_pharmacy.php'; 

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ⚠️ IMPORTANT: Tiyakin na ito ang tama mong session variable!
$customer_id = $_SESSION['user_id'] ?? null;

if (!$customer_id) {
    // Ibalik ang walang laman na array kung walang customer na naka-login
    http_response_code(200);
    die(json_encode(['count' => 0, 'notifications' => []]));
}

$notifications = [];

try {
    // 1. Kumuha ng mga order na 'Ready for Pickup' o 'Completed' at ang 'is_read' ay 0
    // ASSUMPTION: Mayroon kang 'is_read' column sa 'customer_orders' table.
    // Kung wala, kailangan mo itong idagdag!
    $sql = "SELECT order_id, order_status, order_date 
            FROM customer_orders 
            WHERE customer_id = ? 
              AND (order_status = 'Ready for Pickup' OR order_status = 'Completed')
              AND is_read = 0 
            ORDER BY order_date DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $notifications[] = [
            'order_id' => $row['order_id'],
            'message' => "Order #{$row['order_id']} status updated to: {$row['order_status']}",
            'status' => $row['order_status'],
            'date' => (new DateTime($row['order_date']))->format('M d, Y h:i A')
        ];
    }
    
    $stmt->close();
    
    echo json_encode(['count' => count($notifications), 'notifications' => $notifications]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['count' => 0, 'error' => $e->getMessage()]);
}

$conn->close();

// Kung wala kang is_read column, kailangan mong idagdag ito sa iyong 'customer_orders' table:
// ALTER TABLE customer_orders ADD COLUMN is_read BOOLEAN DEFAULT 0;
?>