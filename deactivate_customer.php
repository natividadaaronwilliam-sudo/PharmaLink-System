<?php
require_once 'db_pharmacy.php'; 

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_POST['customer_id'])) {
    $customer_id = intval($_POST['customer_id']);

    // Soft Delete → Set inactive
    $stmt = $conn->prepare("UPDATE customers SET is_active = 0 WHERE customer_id = ?");
    $stmt->bind_param("i", $customer_id);

    if ($stmt->execute()) {
        $stmt->close();

        // -----------------------------
        // INSERT INTO ACTIVITY LOGS
        // -----------------------------
        $admin_name = $_SESSION['user_first_name'] ?? 'System';
        $action = "Deactivate Customer";
        $details = "Customer ID $customer_id was deactivated.";

        $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_name, action, details) VALUES (?, ?, ?)");
        $logStmt->bind_param("sss", $admin_name, $action, $details);
        $logStmt->execute();
        $logStmt->close();

        echo json_encode([
            "success" => true,
            "message" => "Customer account successfully deactivated."
        ]);

    } else {
        echo json_encode([
            "success" => false,
            "message" => "Error deactivating customer: " . $stmt->error
        ]);
    }

} else {
    echo json_encode([
        "success" => false,
        "message" => "Invalid request."
    ]);
}

$conn->close();
?>
