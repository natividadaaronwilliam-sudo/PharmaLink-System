<?php
header('Content-Type: application/json');
session_start();
require_once 'db_pharmacy.php';
require_once 'includes/stock_status.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['items'])) {
    echo json_encode(['status' => 'error', 'message' => 'Cart is empty']);
    exit;
}

$user_id = (int)($_SESSION['user_id'] ?? ($data['user_id'] ?? 0));
$has_customer = !empty($data['customer_id']);
$customer_id = $has_customer ? (int)$data['customer_id'] : 0;
$subtotal = (float)($data['subtotal'] ?? 0);
$discount = (float)($data['discount_total'] ?? 0);
$tax = (float)($data['tax_total'] ?? 0);
$total = (float)($data['total_amount'] ?? 0);
$cash = (float)($data['cash_received'] ?? 0);
$change = (float)($data['change_amount'] ?? 0);
$payment = $data['payment_method'] ?? 'cash';
$transaction_id = 'TXN-' . date('YmdHis') . '-' . random_int(1000, 9999);

$conn->begin_transaction();
try {
    if ($has_customer) {
        $stmt_sale = $conn->prepare("
            INSERT INTO sales (transaction_id, user_id, customer_id, subtotal, discount_amount, tax_amount,
                               total_amount, cash_received, change_amount, payment_method, status, date_created)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', NOW())
        ");
        $stmt_sale->bind_param('siidddddds', $transaction_id, $user_id, $customer_id, $subtotal, $discount, $tax, $total, $cash, $change, $payment);
    } else {
        $stmt_sale = $conn->prepare("
            INSERT INTO sales (transaction_id, user_id, customer_id, subtotal, discount_amount, tax_amount,
                               total_amount, cash_received, change_amount, payment_method, status, date_created)
            VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, 'completed', NOW())
        ");
        $stmt_sale->bind_param('sidddddds', $transaction_id, $user_id, $subtotal, $discount, $tax, $total, $cash, $change, $payment);
    }
    $stmt_sale->execute();
    $sale_id = $stmt_sale->insert_id;
    $stmt_sale->close();

    $stmt_item = $conn->prepare("
        INSERT INTO sales_items (sale_id, lot_id, drug_id, quantity, price, subtotal, discount_amount, promo_name, vat_exempt)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt_update = $conn->prepare("
        UPDATE inventory_lots SET current_stock = current_stock - ?
        WHERE lot_inventory_id = ? AND current_stock >= ?
    ");

    $affected_lots = [];
    foreach ($data['items'] as $item) {
        $lot_id = (int)$item['lot_id'];
        $drug_id = (int)$item['drug_id'];
        $qty = (int)$item['qty'];
        $price = (float)$item['price'];
        $item_sub = (float)($item['subtotal'] ?? ($price * $qty));
        $item_disc = (float)($item['discount_amount'] ?? 0);
        $promo = $item['promo_name'] ?? null;
        $vat = (int)($item['vat_exempt'] ?? 0);

        $stmt_item->bind_param('iiiidddsi', $sale_id, $lot_id, $drug_id, $qty, $price, $item_sub, $item_disc, $promo, $vat);
        $stmt_item->execute();

        $stmt_update->bind_param('iii', $qty, $lot_id, $qty);
        $stmt_update->execute();
        if ($stmt_update->affected_rows === 0) {
            throw new Exception("Insufficient stock for lot ID {$lot_id}");
        }
        $affected_lots[] = $lot_id;
    }
    $stmt_item->close();
    $stmt_update->close();

    syncStockStatusForLots($conn, $affected_lots);
    $conn->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Sale processed successfully',
        'sale_id' => $sale_id,
        'transaction_id' => $transaction_id,
    ]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
