<?php
header('Content-Type: application/json');
include 'db_pharmacy.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = intval($_POST['customer_id'] ?? 1); // default customer
    $cart_items  = json_decode($_POST['cart_items'] ?? '[]', true);
    $total_amount = floatval($_POST['total_amount'] ?? 0);
    $cash = floatval($_POST['cash'] ?? 0);
    $change = floatval($_POST['change'] ?? 0);
    $user_id = 1; // currently logged-in user ID
    $payment_method = 'Cash';
    $transaction_id = 'TXN' . time();

    if (empty($cart_items)) {
        echo json_encode(["status" => "error", "message" => "Cart is empty"]);
        exit;
    }

    $conn->begin_transaction();

    try {
        // 1️⃣ Insert sale
        $stmt_sale = $conn->prepare("INSERT INTO sales (transaction_id, user_id, customer_id, subtotal, total_amount, cash_received, change_amount, payment_method, date_created) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt_sale->bind_param("siidddds", $transaction_id, $user_id, $customer_id, $total_amount, $total_amount, $cash, $change, $payment_method);
        $stmt_sale->execute();
        $sale_id = $stmt_sale->insert_id;
        $stmt_sale->close();

        // 2️⃣ Insert sale items and update inventory
        $stmt_item = $conn->prepare("INSERT INTO sales_items (sale_id, lot_id, drug_id, quantity, price, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_update = $conn->prepare("UPDATE inventory_lots SET current_stock = current_stock - ? WHERE lot_inventory_id = ? AND current_stock >= ?");

        foreach ($cart_items as $item) {
            $lot_id = intval($item['lot_id']);
            $drug_id = intval($item['drug_id']);
            $qty = intval($item['qty']);
            $price = floatval($item['price']);
            $subtotal = floatval($item['subtotal'] ?? ($price * $qty));

            $stmt_item->bind_param("iiiidd", $sale_id, $lot_id, $drug_id, $qty, $price, $subtotal);
            $stmt_item->execute();

            $stmt_update->bind_param("iii", $qty, $lot_id, $qty);
            $stmt_update->execute();

            if ($stmt_update->affected_rows === 0) {
                throw new Exception("Insufficient stock for lot ID {$lot_id}");
            }
        }

        $stmt_item->close();
        $stmt_update->close();

        $conn->commit();

        echo json_encode([
            "status" => "success",
            "message" => "Sale processed successfully",
            "sale_id" => $sale_id,
            "transaction_id" => $transaction_id
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }

    $conn->close();

} else {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
}
?>
