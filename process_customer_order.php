<?php
// FILE: process_customer_order.php
// Endpoint para tanggapin ang order ng customer mula sa customer.js

// 🛑 1. SESSION START (CRITICAL for token validation)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. I-include ang iyong Database Connection File
// Tiyakin na ang file na ito ay naglalaman ng $conn variable na konektado sa MySQL.
include 'db_pharmacy.php'; 

// Set header para mag-expect ng JSON response
header('Content-Type: application/json');

// Tiyakin na ang request method ay POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Only POST requests are accepted.']);
    if (isset($conn) && $conn) $conn->close(); 
    exit;
}

// 2. Kumuha ng JSON input at i-decode
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// 3. I-validate ang basic data
if (!isset($data['customer_id']) || !isset($data['total_amount']) || !isset($data['items']) || empty($data['items']) || !isset($data['order_token'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Incomplete order data provided.']);
    if (isset($conn) && $conn) $conn->close();
    exit;
}

// Kumuha ng data mula sa JSON
$customer_id = (int)$data['customer_id'];
$total_amount = (float)$data['total_amount'];
$sc_pwd_applied = isset($data['sc_pwd_applied']) ? ($data['sc_pwd_applied'] ? 1 : 0) : 0; 
$items = $data['items'];
$status = 'Pending'; // Default status for new online orders

// 🛑 2. TOKEN VALIDATION
$submitted_token = $data['order_token'];
$session_token = $_SESSION['order_token'] ?? null;

if (!$session_token || $submitted_token !== $session_token) {
    // 🛑 FIX: Kapag nakakita ng duplicate (Quiet Mode), magbalik na lang ng success 
    //        message (o isang blankong 200 OK) para ang JS ay hindi mag-pop up ng error.
    
    // Tiyakin lang na walang data.success = false ang ipinasa, para hindi mag-alert ang JS.
    http_response_code(200); // OK
    echo json_encode([
        'success' => true, 
        'message' => 'Order already processed (Duplicate submission blocked silently).',
        // Magbalik ng order_id na 0 o -1 para malaman ng JS na ito ay special case
        'order_id' => 0 
    ]); 
    
    // Hindi na kailangang i-rollback o i-close ang connection kung hindi naman nag-open ng transaction
    if (isset($conn) && $conn) $conn->close();
    exit;
}

// 🛑 3. TOKEN DESTRUCTION (CRITICAL)
// Alisin ang token MULA SA SESSION kaagad BAGO ang transaction.
// Ito ang pumipigil sa pangalawang request (galing sa refresh/back button) na mag-success.
unset($_SESSION['order_token']);


// 4. Simulan ang Database Transaction
// Ito ay kritikal para matiyak na sabay-sabay magiging successful ang header at details inserts.
$conn->begin_transaction();

try {
    // 5. INSERT sa customer_orders table
    $stmt_order = $conn->prepare("INSERT INTO customer_orders 
        (customer_id, order_date, total_amount, order_status, sc_pwd_applied) 
        VALUES (?, NOW(), ?, ?, ?)");
        
    $stmt_order->bind_param("idsi", $customer_id, $total_amount, $status, $sc_pwd_applied);
    
    if (!$stmt_order->execute()) {
        throw new Exception("Error inserting order header: " . $stmt_order->error);
    }
    
    $order_id = $conn->insert_id;
    $stmt_order->close();

    // 6. INSERT sa order_details table (Loop sa bawat item)
    $stmt_details = $conn->prepare("INSERT INTO order_details 
        (order_id, drug_id, lot_inventory_id, quantity, price_per_unit) 
        VALUES (?, ?, ?, ?, ?)");

    foreach ($items as $item) {
        $lot_id = (int)$item['lot_id'];
        $drug_id = (int)$item['drug_id'];
        $quantity = (int)$item['quantity'];
        $price_per_unit = (float)$item['price_per_unit'];

        $stmt_details->bind_param("iiidi", $order_id, $drug_id, $lot_id, $quantity, $price_per_unit);
        
        if (!$stmt_details->execute()) {
            throw new Exception("Error inserting order detail: " . $stmt_details->error);
        }
    }
    $stmt_details->close();
    
    // 7. COMMIT ang transaction kung successful lahat
    $conn->commit();
    
    // Success Response (ibalik ang order_id sa JS)
    echo json_encode([
        'success' => true, 
        'order_id' => $order_id, 
        'message' => 'Order submitted successfully and pending cashier verification.'
    ]);

} catch (Exception $e) {
    // 8. ROLLBACK kung may error
    $conn->rollback();
    
    http_response_code(500); // Internal Server Error
    error_log("Order submission failed for customer ID $customer_id. Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Order failed due to server error. Please try again.']);

} finally {
    // 9. Isara ang database connection
    if (isset($conn) && $conn) $conn->close();
}

?>