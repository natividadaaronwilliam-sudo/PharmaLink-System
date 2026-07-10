<?php
/**
 * FILE: transactions.php
 *
 * THIS FILE DID NOT EXIST IN THE UPLOADED PROJECT.
 *
 * cashier.php's "Confirm Sale" button has always POSTed to transactions.php,
 * but since the file was missing, every checkout got a 404 from the server.
 * The product table's stock number only ever changed on-screen (see the
 * "VISUAL STOCK UPDATE" comments in cashier.php) — nothing was ever written
 * back to inventory_lots, which is why stock in the database never actually
 * decreased after a sale.
 *
 * This endpoint:
 *   1. Validates the cart against the REAL, current stock in the database
 *      (never trusts the quantity the browser displayed).
 *   2. Creates one row in `sales` and one row per cart item in `sales_items`.
 *   3. Decrements inventory_lots.current_stock for each lot sold, inside a
 *      single DB transaction, so stock and the sale record can never go out
 *      of sync (if anything fails, everything is rolled back and nothing is
 *      charged or deducted).
 *   4. Re-syncs drugs_master.stock_status so "Low stock" / "Out of stock"
 *      badges are correct immediately, everywhere in the app.
 */

session_start();
header('Content-Type: application/json');
require_once 'db_pharmacy.php';
require_once 'includes/stock_status.php';

if (!isset($conn) || $conn->connect_error) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}

// Only logged-in Cashier/Admin staff can record a sale.
$allowed_roles = ['Cashier/Pharmacist', 'Admin'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', $allowed_roles, true)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authorized. Please log in again.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or empty request body.']);
    exit;
}

$items = $input['items'] ?? [];
if (!is_array($items) || count($items) === 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Cart is empty.']);
    exit;
}

// Never trust the user_id the browser sends (cashier.js currently sends a
// hardcoded placeholder) — use the actual logged-in session's user instead.
$user_id        = (int)$_SESSION['user_id'];
$customer_id    = isset($input['customer_id']) && $input['customer_id'] !== null && $input['customer_id'] !== ''
                    ? (int)$input['customer_id'] : null;
$subtotal       = (float)($input['subtotal'] ?? 0);
$discount_total = (float)($input['discount_total'] ?? 0);
$tax_total      = (float)($input['tax_total'] ?? 0);
$total_amount   = (float)($input['total_amount'] ?? 0);
$cash_received  = (float)($input['cash_received'] ?? 0);
$change_amount  = (float)($input['change_amount'] ?? 0);
$payment_method = $input['payment_method'] ?? 'cash';

if ($total_amount <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid transaction total.']);
    exit;
}
if ($cash_received < $total_amount) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Insufficient cash received.']);
    exit;
}

$conn->begin_transaction();

try {
    // ---- 1. Lock and validate real stock for every line item first ----
    $lockStmt = $conn->prepare(
        "SELECT current_stock FROM inventory_lots WHERE lot_inventory_id = ? AND is_active = 1 FOR UPDATE"
    );

    $affectedDrugIds = [];

    foreach ($items as $item) {
        $lot_id = (int)($item['lot_id'] ?? 0);
        $qty    = (int)($item['qty'] ?? 0);

        if ($lot_id <= 0 || $qty <= 0) {
            throw new Exception("Invalid item in cart (lot #$lot_id, qty $qty).");
        }

        $lockStmt->bind_param('i', $lot_id);
        $lockStmt->execute();
        $res = $lockStmt->get_result();
        $lot = $res->fetch_assoc();

        if (!$lot) {
            throw new Exception("Item (lot #$lot_id) no longer exists or is inactive.");
        }
        if ((int)$lot['current_stock'] < $qty) {
            throw new Exception("Not enough stock for lot #$lot_id — only {$lot['current_stock']} left, but $qty requested. Please refresh and try again.");
        }
    }
    $lockStmt->close();

    // ---- 2. Insert the sale header ----
    $saleStmt = $conn->prepare(
        "INSERT INTO sales
            (customer_id, user_id, subtotal, discount_total, tax_total, total_amount, cash_received, change_amount, payment_method, status, date_created)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', NOW())"
    );
    $saleStmt->bind_param(
        'iidddddss',
        $customer_id, $user_id, $subtotal, $discount_total, $tax_total, $total_amount, $cash_received, $change_amount, $payment_method
    );
    if (!$saleStmt->execute()) {
        throw new Exception('Failed to save sale: ' . $saleStmt->error);
    }
    $sale_id = $saleStmt->insert_id;
    $saleStmt->close();

    // ---- 3. Insert each line item + decrement real stock ----
    $itemStmt = $conn->prepare(
        "INSERT INTO sales_items
            (sale_id, drug_id, lot_inventory_id, quantity, price, subtotal, discount_amount, promo_name, vat_exempt)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stockStmt = $conn->prepare(
        "UPDATE inventory_lots SET current_stock = current_stock - ? WHERE lot_inventory_id = ?"
    );

    foreach ($items as $item) {
        $drug_id         = (int)($item['drug_id'] ?? 0);
        $lot_id          = (int)($item['lot_id'] ?? 0);
        $qty             = (int)($item['qty'] ?? 0);
        $price           = (float)($item['price'] ?? 0);
        $line_subtotal   = (float)($item['subtotal'] ?? ($price * $qty));
        $discount_amount = (float)($item['discount_amount'] ?? 0);
        $promo_name      = $item['promo_name'] ?? null;
        $vat_exempt      = (int)($item['vat_exempt'] ?? 0);

        $itemStmt->bind_param(
            'iiiidddsi',
            $sale_id, $drug_id, $lot_id, $qty, $price, $line_subtotal, $discount_amount, $promo_name, $vat_exempt
        );
        if (!$itemStmt->execute()) {
            throw new Exception('Failed to save sale item: ' . $itemStmt->error);
        }

        $stockStmt->bind_param('ii', $qty, $lot_id);
        if (!$stockStmt->execute()) {
            throw new Exception('Failed to update stock for lot #' . $lot_id . ': ' . $stockStmt->error);
        }

        if ($drug_id > 0) {
            $affectedDrugIds[$drug_id] = true;
        }
    }
    $itemStmt->close();
    $stockStmt->close();

    // ---- 4. Re-sync stock_status for every drug affected by this sale ----
    foreach (array_keys($affectedDrugIds) as $drug_id) {
        syncStockStatusForDrug($conn, $drug_id);
    }

    // ---- 5. Activity log ----
    $staff_name = $_SESSION['user_first_name'] ?? 'Cashier';
    $details = "Sale #$sale_id — total ₱" . number_format($total_amount, 2) . ", " . count($items) . " item(s).";
    $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_name, action, details) VALUES (?, 'POS Sale', ?)");
    $logStmt->bind_param('ss', $staff_name, $details);
    $logStmt->execute();
    $logStmt->close();

    $conn->commit();

    echo json_encode([
        'status'         => 'success',
        'sale_id'        => $sale_id,
        'transaction_id' => $sale_id,
        'message'        => 'Transaction saved successfully.',
    ]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(409);
    echo json_encode([
        'status'   => 'error',
        'message'  => $e->getMessage(),
        'db_error' => $conn->error ?: null,
    ]);
}

$conn->close();