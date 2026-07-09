<?php
// Safe migration runner — adds normalized columns if missing
require_once 'db_pharmacy.php';

function columnExists(mysqli $conn, string $table, string $column): bool
{
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $res && $res->num_rows > 0;
}

$messages = [];

if (!columnExists($conn, 'suppliers', 'inactive_reason')) {
    $conn->query("ALTER TABLE suppliers ADD COLUMN inactive_reason VARCHAR(255) DEFAULT NULL AFTER status");
    $messages[] = 'Added suppliers.inactive_reason';
}

if (!columnExists($conn, 'drugs_master', 'stock_status')) {
    $conn->query("ALTER TABLE drugs_master ADD COLUMN stock_status ENUM('ok','low','out') NOT NULL DEFAULT 'ok' AFTER minimum_stock");
    $messages[] = 'Added drugs_master.stock_status';
}

require_once 'includes/stock_status.php';
syncAllStockStatus($conn);
$messages[] = 'Synced stock_status for all active drugs';

echo json_encode(['success' => true, 'messages' => $messages]);
$conn->close();
