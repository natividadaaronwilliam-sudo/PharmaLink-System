<?php
// add_loyalty_points.php
// Lets a logged-in Cashier/Admin manually add (or subtract) loyalty points
// for a specific customer, used from the Customer Segmentation "View" panel
// where clustered buyers are listed per segment.
session_start();
require_once 'db_pharmacy.php';

header('Content-Type: application/json');

$allowed_roles = ['Cashier/Pharmacist', 'Admin'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', $allowed_roles, true)) {
    echo json_encode(["success" => false, "message" => "Not authorized."]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    echo json_encode(["success" => false, "message" => "Invalid request body."]);
    exit;
}

$customer_id = isset($data['customer_id']) ? (int)$data['customer_id'] : 0;
$points      = isset($data['points']) ? (float)$data['points'] : 0;

if ($customer_id <= 0 || $points == 0) {
    echo json_encode(["success" => false, "message" => "Please provide a customer and a non-zero points value."]);
    exit;
}

$conn->begin_transaction();
try {
    // Lock the row so concurrent adjustments don't clobber each other
    $stmt = $conn->prepare("SELECT loyalty_points FROM customers WHERE customer_id = ? FOR UPDATE");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->rollback();
        echo json_encode(["success" => false, "message" => "Customer not found."]);
        exit;
    }
    $current = (float)$result->fetch_assoc()['loyalty_points'];
    $stmt->close();

    $new_balance = $current + $points;
    if ($new_balance < 0) $new_balance = 0;

    $update = $conn->prepare("UPDATE customers SET loyalty_points = ? WHERE customer_id = ?");
    $update->bind_param("di", $new_balance, $customer_id);
    $update->execute();
    $update->close();

    $staff_name = $_SESSION['user_first_name'] ?? 'Staff';
    $action = "Adjust Loyalty Points";
    $details = "Customer ID {$customer_id} points " . ($points > 0 ? "+" : "") . "{$points} (new balance: {$new_balance}) — via Customer Segmentation.";
    $log = $conn->prepare("INSERT INTO activity_logs (admin_name, action, details) VALUES (?, ?, ?)");
    $log->bind_param("sss", $staff_name, $action, $details);
    $log->execute();
    $log->close();

    $conn->commit();
    echo json_encode(["success" => true, "new_balance" => $new_balance]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["success" => false, "message" => "Error updating points: " . $e->getMessage()]);
}

$conn->close();