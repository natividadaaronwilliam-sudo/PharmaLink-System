<?php
// FILE: includes/mark_read.php

header('Content-Type: application/json');
include 'db_pharmacy.php'; 

if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Database connection failed.']));
}

$orderId = isset($_REQUEST['order_id']) ? (int)$_REQUEST['order_id'] : null;

if (!$orderId) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Order ID is required.']));
}

try {
    // I-update ang is_read column sa 1 (nabasa na)
    $sql = "UPDATE customer_orders SET is_read = 1 WHERE order_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true]);
    } else {
        // Posibleng na-mark as read na o wala talagang ganyang order ID
        echo json_encode(['success' => false, 'message' => 'Order not found or already marked as read.']);
    }
    
    $stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error updating status: ' . $e->getMessage()]);
}

$conn->close();
?>