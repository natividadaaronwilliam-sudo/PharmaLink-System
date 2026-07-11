<?php
// update_lot.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
include 'db_pharmacy.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not supported. Use POST."]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !isset($data['lot_inventory_id'])) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid data. Lot ID is required."]);
    exit;
}

$lot_id           = (int)$data['lot_inventory_id'];
$lot_number       = trim($data['lot_number'] ?? '');
$expiration_date  = $data['expiration_date'] ?? null;
$current_stock    = isset($data['current_stock']) ? (int)$data['current_stock'] : 0;
$price            = isset($data['price']) ? (float)$data['price'] : 0;
$supplier         = !empty($data['supplier']) ? (int)$data['supplier'] : null;

$sql = "
    UPDATE inventory_lots
    SET lot_number = ?, expiration_date = ?, current_stock = ?, price = ?, supplier = ?
    WHERE lot_inventory_id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssidii", $lot_number, $expiration_date, $current_stock, $price, $supplier, $lot_id);

try {
    $stmt->execute();
    require_once 'includes/stock_status.php';
    $drugStmt = $conn->prepare('SELECT drug_id FROM inventory_lots WHERE lot_inventory_id = ?');
    $drugStmt->bind_param('i', $lot_id);
    $drugStmt->execute();
    $drugRow = $drugStmt->get_result()->fetch_assoc();
    $drugStmt->close();
    if ($drugRow) {
        syncStockStatusForDrug($conn, (int)$drugRow['drug_id']);
    }
    $admin_name = $_SESSION['user_first_name'] ?? 'Admin';
    $action = "Update Stock Lot";
    $details = "Updated stock lot ID {$lot_id} ({$lot_number}).";
    $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_name, action, details) VALUES (?, ?, ?)");
    $logStmt->bind_param("sss", $admin_name, $action, $details);
    $logStmt->execute();
    $logStmt->close();

    echo json_encode(["success" => true, "message" => "Stock lot updated successfully."]);
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    if ($e->getCode() === 1062) {
        echo json_encode(["success" => false, "message" => "This lot number already exists for this drug. Please use a different lot/batch number."]);
    } else {
        echo json_encode(["success" => false, "message" => "Database error while updating the stock lot: " . $e->getMessage()]);
    }
}

$stmt->close();
$conn->close();
?>