<?php
// Tiyakin na konektado sa database. 
// Palitan ang path kung iba ang lokasyon ng db_connect.php mo.
require_once 'db_connect.php'; 

// Function para kunin ang Staff (role_id 1 at 2)
function getStaff(object $conn) : array {
    $staff = [];
    // Kinukuha ang user_id, username, role, at personal info
    $sql = "SELECT u.user_id, u.username, r.role_name, 
                   s.first_name, s.middle_name, s.last_name
            FROM users u
            JOIN staff_info s ON u.user_id = s.user_id
            JOIN role r ON u.role_id = r.role_id
            WHERE u.role_id IN (1, 2)
            ORDER BY u.user_id DESC";
            
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $staff[] = $row;
        }
    }
    return $staff;
}

// Function para kunin ang Customers
function getCustomers(object $conn) : array {
    $customers = [];
    // Kinukuha ang customer info mula sa 'customers' table
    $sql = "SELECT customer_id, first_name, middle_name, last_name, email, phone_number
            FROM customers
            ORDER BY customer_id DESC";
            
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $customers[] = $row;
        }
    }
    return $customers;
}

// Kunin ang data
$allStaff = getStaff($conn);
$allCustomers = getCustomers($conn);

// Maaari mo na ngayong gamitin ang $allStaff at $allCustomers arrays sa iyong HTML.
?>