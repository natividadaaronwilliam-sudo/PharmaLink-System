<?php
// FILE: get_order_details.php (Unified Handler for Online Orders and Walk-in Sales)

header('Content-Type: application/json');
include 'db_pharmacy.php'; 

if ($conn->connect_error) {
    http_response_code(500);
    // Mas mahusay na gumamit ng exit/die dito para iwasan ang karagdagang execution
    die(json_encode(['success' => false, 'message' => 'Database connection failed.']));
}

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : null;

if (!$orderId) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Order ID is required.']));
}

$is_walkin_sale = false;
$customer_id = 0;
$status = '';
$orderItems = [];

try {
    // 1. Tiyakin kung ang ID ay nasa CUSTOMER_ORDERS (Online Order)
    $stmt = $conn->prepare("SELECT customer_id, order_status AS status FROM customer_orders WHERE order_id = ?");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $header_result = $stmt->get_result();
    $orderHeader = $header_result->fetch_assoc();
    $stmt->close();

    if ($orderHeader) {
        // --- ITO AY ONLINE ORDER ---
        $customer_id = (int)$orderHeader['customer_id'];
        $status = $orderHeader['status'];
        $is_walkin_sale = false;
        
    } else {
        // 2. Kung hindi, hanapin sa SALES (Walk-in/POS Sale)
        $stmt = $conn->prepare("SELECT customer_id, status FROM sales WHERE sale_id = ?");
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        $header_result = $stmt->get_result();
        $saleHeader = $header_result->fetch_assoc();
        $stmt->close();
        
        if (!$saleHeader) {
            http_response_code(404);
            throw new Exception("Order or Sale not found with ID: " . $orderId);
        }
        
        // --- ITO AY WALK-IN SALE ---
        $customer_id = (int)$saleHeader['customer_id'];
        $status = $saleHeader['status'];
        $is_walkin_sale = true;
    }
    
    // 3. Kumuha ng Customer Name
    $customer_name = "Walk-in Customer"; // Default para sa POS transactions (customer_id = 0 o NULL)

    if ($customer_id > 0) { 
        $customer_name_sql = "SELECT first_name, middle_name, last_name FROM customers WHERE customer_id = ?";
        $stmt_cust = $conn->prepare($customer_name_sql);
        $stmt_cust->bind_param("i", $customer_id);
        $stmt_cust->execute();
        $cust_result = $stmt_cust->get_result();
        $customer_data = $cust_result->fetch_assoc();
        $stmt_cust->close();

        if ($customer_data) {
            $parts = [ 
                trim($customer_data['first_name']), 
                trim($customer_data['middle_name']), 
                trim($customer_data['last_name'])
            ];
            $customer_name = implode(' ', array_filter($parts));
        }
    }
    
    // 4. Kumuha ng Order/Sale Items (Conditional Logic)
    
    if ($is_walkin_sale) {
        // Query para sa SALES_ITEMS (Walk-in Sale)
        // Gumagamit ng sales_items, sale_id, at drug_id join
        $items_sql = "SELECT 
                          si.quantity AS ordered_qty, 
                          si.price AS price_per_unit, 
                          dm.brand_name, 
                          dm.generic_name,
                          dm.dosage
                        FROM sales_items si
                        LEFT JOIN drugs_master dm ON si.drug_id = dm.drug_id
                        WHERE si.sale_id = ?";
    } else {
        // Query para sa ORDER_DETAILS (Online Order)
        // Gumagamit ng order_details, order_id, at drug_id join
        $items_sql = "SELECT 
                          od.quantity AS ordered_qty, 
                          od.price_per_unit, 
                          dm.brand_name, 
                          dm.generic_name,
                          dm.dosage
                        FROM order_details od
                        LEFT JOIN drugs_master dm ON od.drug_id = dm.drug_id
                        WHERE od.order_id = ?";
    }
    

    $stmt = $conn->prepare($items_sql);
    $stmt->bind_param("i", $orderId); 
    $stmt->execute();
    $items_result = $stmt->get_result();
    while ($row = $items_result->fetch_assoc()) {
        $orderItems[] = $row;
    }
    $stmt->close();


    // 5. Output Final JSON
    echo json_encode([
        'success' => true,
        'order_id' => $orderId,
        'customer_name' => $customer_name, 
        'customer_id' => $customer_id, 
        'status' => $status,
        'items' => $orderItems
    ]);

} catch (Exception $e) {
    http_response_code(500);
    // Huwag lang mag-rely sa $e->getMessage(), gumamit din ng PHP error logging
    error_log("Order Details Error: " . $e->getMessage() . " for ID " . $orderId);
    echo json_encode(['success' => false, 'message' => 'Processing error: ' . $e->getMessage()]);
}

$conn->close();
?>