<?php
// deactivate_staff.php
require_once 'db_pharmacy.php'; 

if (isset($_POST['user_id'])) {
    $user_id = $conn->real_escape_string($_POST['user_id']);

    // Soft Delete: Set is_active to 0
    $sql = "UPDATE users SET is_active = 0 WHERE user_id = '$user_id'";
    
    if ($conn->query($sql) === TRUE) {
        // Activity Logging
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $admin_name = $_SESSION['user_first_name'] ?? 'Admin';
        $action = "Deactivate Staff";
        $details = "Staff account ID {$user_id} has been deactivated.";

        $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_name, action, details) VALUES (?, ?, ?)");
        $logStmt->bind_param("sss", $admin_name, $action, $details);
        $logStmt->execute();
        $logStmt->close();

        echo json_encode(["success" => true, "message" => "Staff account successfully deactivated."]);
    } else {
        echo json_encode(["success" => false, "message" => "Error deactivating account: " . $conn->error]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Invalid request."]);
}

$conn->close();
?>
