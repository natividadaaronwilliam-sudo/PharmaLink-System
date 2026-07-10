<?php
/**
 * FILE: includes/stock_status.php
 *
 * This file was referenced (require_once 'includes/stock_status.php') by
 * dashboard_data.php and fetch_dashboard.php but did not exist in the
 * uploaded project, which meant BOTH the admin dashboard and the low-stock /
 * out-of-stock counters were fatal-erroring (or silently stale) instead of
 * reflecting the real, current database state.
 *
 * syncAllStockStatus() recalculates drugs_master.stock_status for every
 * active drug from the actual sum of its active inventory_lots.current_stock,
 * compared against drugs_master.minimum_stock. It is safe to call on every
 * dashboard/notification request (cheap single UPDATE...JOIN), which is what
 * keeps "low stock" / "out of stock" numbers accurate in real time right
 * after a sale or a stock adjustment.
 *
 * Requires: drugs_master.stock_status ENUM/VARCHAR column ('ok'|'low'|'out').
 * If that column does not exist yet, run:
 *   ALTER TABLE drugs_master ADD COLUMN stock_status VARCHAR(10) NOT NULL DEFAULT 'out';
 */

if (!function_exists('syncAllStockStatus')) {
    function syncAllStockStatus(mysqli $conn): void
    {
        // Aggregate current stock per drug across its active lots.
        $sql = "
            UPDATE drugs_master dm
            LEFT JOIN (
                SELECT drug_id, COALESCE(SUM(current_stock), 0) AS on_hand
                FROM inventory_lots
                WHERE is_active = 1
                GROUP BY drug_id
            ) stock ON stock.drug_id = dm.drug_id
            SET dm.stock_status = CASE
                WHEN COALESCE(stock.on_hand, 0) <= 0 THEN 'out'
                WHEN COALESCE(stock.on_hand, 0) <= dm.minimum_stock THEN 'low'
                ELSE 'ok'
            END
            WHERE dm.is_active = 1
        ";

        // Best-effort: don't let a stock-status sync failure break the page
        // that called it (dashboard, notifications, etc.) — just log it.
        if (!$conn->query($sql)) {
            error_log('syncAllStockStatus failed: ' . $conn->error);
        }
    }
}

/**
 * Recalculates stock_status for a single drug. Cheaper than syncAllStockStatus
 * and is called right after a sale/order/lot change so the affected drug's
 * status is correct immediately, without waiting on the next full sync.
 */
if (!function_exists('syncStockStatusForDrug')) {
    function syncStockStatusForDrug(mysqli $conn, int $drug_id): void
    {
        $stmt = $conn->prepare("
            UPDATE drugs_master dm
            LEFT JOIN (
                SELECT drug_id, COALESCE(SUM(current_stock), 0) AS on_hand
                FROM inventory_lots
                WHERE is_active = 1 AND drug_id = ?
                GROUP BY drug_id
            ) stock ON stock.drug_id = dm.drug_id
            SET dm.stock_status = CASE
                WHEN COALESCE(stock.on_hand, 0) <= 0 THEN 'out'
                WHEN COALESCE(stock.on_hand, 0) <= dm.minimum_stock THEN 'low'
                ELSE 'ok'
            END
            WHERE dm.drug_id = ?
        ");
        if ($stmt) {
            $stmt->bind_param('ii', $drug_id, $drug_id);
            $stmt->execute();
            $stmt->close();
        }
    }
}