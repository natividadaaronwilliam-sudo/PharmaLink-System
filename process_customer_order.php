<?php
/**
 * FILE: process_customer_order.php
 *
 * THIS FILE DID NOT EXIST IN THE UPLOADED PROJECT.
 *
 * customer.js's submitOrder() has always POSTed here, so every "Submit Order
 * for Pickup" click got a 404. Its .catch() block then did this:
 *
 *     alert('Order placed successfully, but connection timed out...');
 *     for (let lotId in cart) delete cart[lotId];   // <-- wipes the cart
 *     updateCartPanel();
 *     showReceiptModal("Pending/Unknown", ...);      // <-- fake receipt
 *
 * That's exactly the bug reported: the cart visibly "loses" its items right
 * after checkout, a receipt pops up, but nothing was ever saved — because
 * the request never reached a real backend. This file is that backend, and
 * customer.js has been corrected (see the diff) to only clear the cart on a
 * confirmed server success, never on a network error.
 *
 * This endpoint:
 *   1. Trusts only the logged-in session for who the customer is (never the
 *      client-supplied customer_id).
 *   2. Validates the cart against REAL current stock.
 *   3. Is idempotent via order_token: if the same token is submitted twice
 *      (e.g. a slow connection retried by the browser), the second request
 *      returns the SAME order instead of creating a duplicate / double-
 *      deducting stock.
 *   4. Creates customer_orders + order_details rows and decrements
 *      inventory_lots.current_stock atomically, then re-syncs stock_status.
 *
 * Schema requirement (run once if not already present):
 *   ALTER TABLE customer_orders ADD COLUMN order_token VARCHAR(64) NULL UNIQUE;
 *   ALTER TABLE customer_orders ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0;
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
require_once 'db_pharmacy.php';
require_once 'includes/stock_status.php';

if (!isset($conn) || $conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

$customer_id = $_SESSION['user_id'] ?? null;
if (!$customer_id || strtolower($_SESSION['user_role'] ?? '') !== 'customer') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}
$customer_id = (int)$customer_id;

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid or empty request body.']);
    exit;
}

$items = $input['items'] ?? [];
if (!is_array($items) || count($items) === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Your cart is empty.']);
    exit;
}

$order_token = trim((string)($input['order_token'] ?? ''));
if ($order_token === '' || $order_token === 'no_token') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing order token. Please refresh the page and try again.']);
    exit;
}

// ---- Idempotency check: has this exact token already produced an order? ----
$dupStmt = $conn->prepare("SELECT order_id FROM customer_orders WHERE order_token = ? LIMIT 1");
$dupStmt->bind_param('s', $order_token);
$dupStmt->execute();
$dupResult = $dupStmt->get_result();
if ($dupRow = $dupResult->fetch_assoc()) {
    $dupStmt->close();
    $conn->close();
    // Same submission retried (e.g. a slow connection) — return the order
    // that was already created instead of creating a second one.
    http_response_code(409);
    echo json_encode(['success' => true, 'order_id' => $dupRow['order_id'], 'duplicate' => true]);
    exit;
}
$dupStmt->close();

$conn->begin_transaction();

try {
    // ---- 1. Lock and validate real stock for every line item ----
    $lockStmt = $conn->prepare(
        "SELECT current_stock FROM inventory_lots WHERE lot_inventory_id = ? AND is_active = 1 FOR UPDATE"
    );

    $affectedDrugIds = [];
    $server_total = 0.0;

    foreach ($items as $item) {
        $lot_id = (int)($item['lot_id'] ?? 0);
        $qty    = (int)($item['quantity'] ?? 0);

        if ($lot_id <= 0 || $qty <= 0) {
            throw new Exception('Invalid item in cart.');
        }

        $lockStmt->bind_param('i', $lot_id);
        $lockStmt->execute();
        $lot = $lockStmt->get_result()->fetch_assoc();

        if (!$lot) {
            throw new Exception('One of the items in your cart is no longer available.');
        }
        if ((int)$lot['current_stock'] < $qty) {
            throw new Exception("Not enough stock left for one of your items (only {$lot['current_stock']} available). Please update your cart.");
        }
    }
    $lockStmt->close();

    // Recompute the total server-side from trusted price data rather than
    // trusting the client-sent total outright.
    $priceStmt = $conn->prepare("SELECT price FROM inventory_lots WHERE lot_inventory_id = ?");
    foreach ($items as $item) {
        $lot_id = (int)($item['lot_id'] ?? 0);
        $qty    = (int)($item['quantity'] ?? 0);
        $priceStmt->bind_param('i', $lot_id);
        $priceStmt->execute();
        $row = $priceStmt->get_result()->fetch_assoc();
        $unit_price = $row ? (float)$row['price'] : (float)($item['price_per_unit'] ?? 0);
        $server_total += $unit_price * $qty;
    }
    $priceStmt->close();

    // ---- 2. Insert the order header ----
    $orderStmt = $conn->prepare(
        "INSERT INTO customer_orders (customer_id, order_date, order_status, total_amount, is_read, order_token)
         VALUES (?, NOW(), 'Pending', ?, 0, ?)"
    );
    $orderStmt->bind_param('ids', $customer_id, $server_total, $order_token);
    if (!$orderStmt->execute()) {
        throw new Exception('Failed to create order: ' . $orderStmt->error);
    }
    $order_id = $orderStmt->insert_id;
    $orderStmt->close();

    // ---- 3. Insert order items + decrement real stock ----
    $itemStmt = $conn->prepare(
        "INSERT INTO order_details (order_id, drug_id, lot_inventory_id, quantity, price_per_unit)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stockStmt = $conn->prepare(
        "UPDATE inventory_lots SET current_stock = current_stock - ? WHERE lot_inventory_id = ?"
    );
    $priceStmt2 = $conn->prepare("SELECT price FROM inventory_lots WHERE lot_inventory_id = ?");

    foreach ($items as $item) {
        $drug_id = (int)($item['drug_id'] ?? 0);
        $lot_id  = (int)($item['lot_id'] ?? 0);
        $qty     = (int)($item['quantity'] ?? 0);

        $priceStmt2->bind_param('i', $lot_id);
        $priceStmt2->execute();
        $row = $priceStmt2->get_result()->fetch_assoc();
        $unit_price = $row ? (float)$row['price'] : (float)($item['price_per_unit'] ?? 0);

        $itemStmt->bind_param('iiiid', $order_id, $drug_id, $lot_id, $qty, $unit_price);
        if (!$itemStmt->execute()) {
            throw new Exception('Failed to save order item: ' . $itemStmt->error);
        }

        $stockStmt->bind_param('ii', $qty, $lot_id);
        if (!$stockStmt->execute()) {
            throw new Exception('Failed to update stock: ' . $stockStmt->error);
        }

        if ($drug_id > 0) {
            $affectedDrugIds[$drug_id] = true;
        }
    }
    $itemStmt->close();
    $stockStmt->close();
    $priceStmt2->close();

    // ---- 4. Re-sync stock_status for affected drugs ----
    foreach (array_keys($affectedDrugIds) as $drug_id) {
        syncStockStatusForDrug($conn, $drug_id);
    }

    // ---- 5. Activity log ----
    $details = "Online order #$order_id placed by customer #$customer_id — total ₱" . number_format($server_total, 2) . ".";
    $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_name, action, details) VALUES ('Customer Portal', 'Online Order', ?)");
    $logStmt->bind_param('s', $details);
    $logStmt->execute();
    $logStmt->close();

    $conn->commit();

    echo json_encode([
        'success'  => true,
        'order_id' => $order_id,
        'total'    => round($server_total, 2),
        'message'  => 'Order placed successfully.',
    ]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(409);
    echo json_encode([
        'success'  => false,
        'message'  => $e->getMessage(),
    ]);
}

$conn->close();