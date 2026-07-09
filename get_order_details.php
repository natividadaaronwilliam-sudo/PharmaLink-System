<?php
// FILE: get_order_details.php (UPDATED VERSION)

header('Content-Type: application/json');
include 'db_pharmacy.php'; 

if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Database connection failed.']));
}

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : null;

if (!$orderId) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Order ID is required.']));
}

$orderHeader = null;
$orderItems = [];

try {
    // 1. Kumuha ng Order Header (Customer ID at Status)
    $header_sql = "SELECT co.customer_id, co.order_status 
                   FROM customer_orders co 
                   WHERE co.order_id = ?";
    
    $stmt = $conn->prepare($header_sql);
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $header_result = $stmt->get_result();
    $orderHeader = $header_result->fetch_assoc();
    $stmt->close();

    if (!$orderHeader) {
        http_response_code(404);
        throw new Exception("Order not found.");
    }
    
    // ⭐ BAGONG LOGIC PARA SA CUSTOMER NAME ⭐
    $customer_id = $orderHeader['customer_id'];
    
    // Kumuha ng buong pangalan mula sa 'customers' table
    $customer_name_sql = "SELECT first_name, middle_name, last_name FROM customers WHERE customer_id = ?";
    $stmt_cust = $conn->prepare($customer_name_sql);
    $stmt_cust->bind_param("i", $customer_id);
    $stmt_cust->execute();
    $cust_result = $stmt_cust->get_result();
    $customer_data = $cust_result->fetch_assoc();
    $stmt_cust->close();

    $customer_name = "Customer N/A";
    if ($customer_data) {
        // Pagsamahin ang pangalan, tinitiyak na walang extra space kung walang middle name
        $parts = [
            trim($customer_data['first_name']), 
            trim($customer_data['middle_name']), 
            trim($customer_data['last_name'])
        ];
        // Ginagamit ang array_filter para alisin ang mga blank parts (tulad ng middle name)
        $customer_name = implode(' ', array_filter($parts));
    }
    
    // 2. Kumuha ng Order Items at Kasalukuyang Stock
    $items_sql = "SELECT 
                    od.drug_id, 
                    od.lot_inventory_id, 
                    od.quantity AS ordered_qty, 
                    od.price_per_unit, 
                    dm.brand_name, 
                    dm.generic_name,
                    il.current_stock
                  FROM 
                    order_details od
                  JOIN 
                    drugs_master dm ON od.drug_id = dm.drug_id
                  JOIN 
                    inventory_lots il ON od.lot_inventory_id = il.lot_inventory_id
                  WHERE 
                    od.order_id = ?";

    $stmt = $conn->prepare($items_sql);
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $items_result = $stmt->get_result();
    while ($row = $items_result->fetch_assoc()) {
        $orderItems[] = $row;
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'order_id' => $orderId,
        'customer_name' => $customer_name,
        'customer_id' => $orderHeader['customer_id'], 
        'status' => $orderHeader['order_status'],
        'items' => $orderItems
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Processing error: ' . $e->getMessage()]);
}

$conn->close();
