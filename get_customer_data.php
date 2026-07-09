<?php
// get_customer_data.php (REVISED for single 'customers' table)
require_once 'db_pharmacy.php'; 

if (isset($_POST['customer_id'])) {
    // Note: Pinalitan ang user_id ng customer_id
    $customer_id = $conn->real_escape_string($_POST['customer_id']);

    $sql = "SELECT customer_id, first_name, middle_name, last_name,
                   username, address, phone_number, 
                   customer_type, loyalty_points, email
            FROM customers
            WHERE customer_id = '$customer_id'";
    
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows === 1) {
        $data = $result->fetch_assoc();
        echo json_encode(["success" => true, "data" => $data]);
    } else {
        echo json_encode(["success" => false, "message" => "Customer not found or database error."]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Invalid request."]);
}
$conn->close();
?>