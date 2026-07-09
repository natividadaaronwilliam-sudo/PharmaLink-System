<?php
// reactivate_lot.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
include 'db_pharmacy.php';

if (!$conn) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database connection failed."]);
    exit;
}

$lot_id = $_GET['id'] ?? null;

if (!$lot_id) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Lot ID is required."]);
    exit;
}

$sql = "UPDATE inventory_lots SET is_active = 1 WHERE lot_inventory_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $lot_id);

if ($stmt->execute()) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $admin_name = $_SESSION['user_first_name'] ?? 'Admin';
    $action = "Reactivate Stock Lot";
    $details = "Stock lot ID {$lot_id} has been reactivated.";

    $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_name, action, details) VALUES (?, ?, ?)");
    $logStmt->bind_param("sss", $admin_name, $action, $details);
    $logStmt->execute();
    $logStmt->close();

    echo json_encode(["success" => true, "message" => "Stock lot reactivated successfully."]);
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database Error: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
