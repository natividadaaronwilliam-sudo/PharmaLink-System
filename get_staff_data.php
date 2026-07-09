<?php
// get_staff_data.php
require_once 'db_pharmacy.php'; 

if (isset($_POST['user_id'])) {
    $user_id = $conn->real_escape_string($_POST['user_id']);

    $sql = "SELECT u.user_id, r.role_name, 
                   s.first_name, s.middle_name, s.last_name,
                   s.email, s.phone_number, s.address, u.username
            FROM users u
            JOIN staff_info s ON u.user_id = s.user_id
            JOIN role r ON u.role_id = r.role_id
            WHERE u.user_id = '$user_id'";
    
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows === 1) {
        $data = $result->fetch_assoc();
        echo json_encode(["success" => true, "data" => $data]);
    } else {
        echo json_encode(["success" => false, "message" => "Staff not found or database error."]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Invalid request."]);
}
$conn->close();
?>