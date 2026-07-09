<?php
/**
 * Keeps drugs_master.stock_status in sync with aggregated active lot stock.
 * Values: 'ok' | 'low' | 'out'
 */
function syncDrugStockStatus(mysqli $conn, int $drug_id): void
{
    $stmt = $conn->prepare("
        SELECT dm.minimum_stock,
               COALESCE(SUM(CASE WHEN il.is_active = 1 THEN il.current_stock ELSE 0 END), 0) AS total_stock
        FROM drugs_master dm
        LEFT JOIN inventory_lots il ON dm.drug_id = il.drug_id
        WHERE dm.drug_id = ? AND dm.is_active = 1
        GROUP BY dm.drug_id, dm.minimum_stock
    ");
    $stmt->bind_param('i', $drug_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return;
    }

    $stock = (int)$row['total_stock'];
    $min = (int)$row['minimum_stock'];
    if ($stock === 0) {
        $status = 'out';
    } elseif ($stock <= $min) {
        $status = 'low';
    } else {
        $status = 'ok';
    }

    $upd = $conn->prepare("UPDATE drugs_master SET stock_status = ? WHERE drug_id = ?");
    $upd->bind_param('si', $status, $drug_id);
    $upd->execute();
    $upd->close();
}

function syncAllStockStatus(mysqli $conn): void
{
    $res = $conn->query("SELECT drug_id FROM drugs_master WHERE is_active = 1");
    if (!$res) {
        return;
    }
    while ($row = $res->fetch_assoc()) {
        syncDrugStockStatus($conn, (int)$row['drug_id']);
    }
}

function syncStockStatusForLots(mysqli $conn, array $lot_ids): void
{
    if (empty($lot_ids)) {
        return;
    }
    $ids = array_map('intval', $lot_ids);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));

    $stmt = $conn->prepare("SELECT DISTINCT drug_id FROM inventory_lots WHERE lot_inventory_id IN ($placeholders)");
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        syncDrugStockStatus($conn, (int)$row['drug_id']);
    }
    $stmt->close();
}
