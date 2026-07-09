<?php
// delete_lot.php with activity logging
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
include 'db_pharmacy.php'; 

// Start session for admin info
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$lot_id = $_GET['id'] ?? null;

if (!$lot_id) {
    echo json_encode(["success" => false, "message" => "Lot ID is required."]);
    exit;
}

// Delete the lot
$stmt = $conn->prepare("DELETE FROM inventory_lots WHERE lot_inventory_id = ?");
$stmt->bind_param("i", $lot_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        // Activity Logging
        $admin_name = $_SESSION['user_first_name'] ?? 'Admin';
        $action = "Delete Inventory Lot";
        $details = "Deleted inventory lot with ID: {$lot_id}";

        $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_name, action, details) VALUES (?, ?, ?)");
        $logStmt->bind_param("sss", $admin_name, $action, $details);
        $logStmt->execute();
        $logStmt->close();

        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "message" => "No lot found with that ID."]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Database Error: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
