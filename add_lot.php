<?php
// add_lot.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
include 'db_pharmacy.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!$conn) {
    echo json_encode(["success" => false, "message" => "Database connection failed."]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!$data || empty($data['drug_master_id']) || empty($data['lot_number']) || empty($data['expiration_date'])) {
    echo json_encode(["success" => false, "message" => "Missing required fields."]);
    exit;
}

$drug_id         = (int)$data['drug_master_id'];
$lot_number      = trim($data['lot_number']);
$expiration_date = $data['expiration_date'];
$current_stock   = isset($data['current_stock']) ? (int)$data['current_stock'] : 0;
$price           = isset($data['price']) ? (float)$data['price'] : 0;
$supplier        = !empty($data['supplier']) ? (int)$data['supplier'] : null;

if ($supplier !== null) {
    $supplierStmt = $conn->prepare("SELECT supplier_id FROM suppliers WHERE supplier_id = ? AND status = 'Active' LIMIT 1");
    $supplierStmt->bind_param("i", $supplier);
    $supplierStmt->execute();
    $supplierResult = $supplierStmt->get_result();

    if (!$supplierResult || $supplierResult->num_rows === 0) {
        $supplierStmt->close();
        echo json_encode(["success" => false, "message" => "Please select an active supplier."]);
        exit;
    }

    $supplierStmt->close();
}

$sql = "
    INSERT INTO inventory_lots (drug_id, lot_number, expiration_date, current_stock, price, supplier, is_active)
    VALUES (?, ?, ?, ?, ?, ?, 1)
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("issidi", $drug_id, $lot_number, $expiration_date, $current_stock, $price, $supplier);

if ($stmt->execute()) {
    $lot_id = $conn->insert_id;
    require_once 'includes/stock_status.php';
    syncDrugStockStatus($conn, $drug_id);

    $admin_name = $_SESSION['user_first_name'] ?? 'Admin';
    $action = "Add Stock Lot";
    $details = "Added new stock lot '{$lot_number}' (Lot ID {$lot_id}) for drug ID {$drug_id}.";

    $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_name, action, details) VALUES (?, ?, ?)");
    $logStmt->bind_param("sss", $admin_name, $action, $details);
    $logStmt->execute();
    $logStmt->close();

    echo json_encode(["success" => true, "id" => $lot_id]);
} else {
    echo json_encode(["success" => false, "message" => "Database Error: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
