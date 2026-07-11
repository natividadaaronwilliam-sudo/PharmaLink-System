<?php
if (!isset($conn) || !($conn instanceof mysqli)) {
    require_once __DIR__ . '/../db_pharmacy.php';
}

// Same "in-stock, active, not expired" query used by get_products.php, so
// what the customer can add to cart always matches real, current inventory.
$sql = "SELECT
            dm.drug_id, dm.category, dm.brand_name, dm.generic_name,
            dm.dosage, dm.form, il.price, il.lot_inventory_id, il.current_stock
        FROM drugs_master dm
        JOIN inventory_lots il ON dm.drug_id = il.drug_id
        WHERE il.current_stock > 0
          AND il.is_active = 1
          AND il.expiration_date >= CURDATE()
          AND dm.is_active = 1
        ORDER BY dm.generic_name ASC";
$result = $conn->query($sql);

$catResult = $conn->query("SELECT DISTINCT category FROM drugs_master WHERE is_active = 1 ORDER BY category ASC");
?>
<div class="products-header" style="margin-bottom:16px;">
    <h2 style="margin:0;">Shop Products</h2>
</div>

<div class="toolbar-bar" style="display:flex; align-items:center; gap:14px; flex-wrap:wrap; background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px; padding:10px 14px; margin-bottom:20px;">
    <div style="position:relative; flex:1; min-width:220px;">
        <i class="fas fa-search" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#9ca3af; font-size:13px;"></i>
        <input type="text" id="searchInput" placeholder="Search medicine..." autocomplete="off"
               style="width:100%; box-sizing:border-box; height:38px; padding:0 12px 0 32px; border:1px solid #d1d5db; border-radius:6px; font-size:14px;">
    </div>
    <select id="categoryFilter" style="height:38px; box-sizing:border-box; padding:0 10px; border:1px solid #d1d5db; border-radius:6px; font-size:14px;">
        <option value="">All Categories</option>
        <?php while ($cat = $catResult->fetch_assoc()): ?>
            <option value="<?= htmlspecialchars(ucwords(strtolower($cat['category']))) ?>"><?= htmlspecialchars(ucwords(strtolower($cat['category']))) ?></option>
        <?php endwhile; ?>
    </select>
</div>

<div class="products-container" style="display:flex; gap:24px; align-items:flex-start; flex-wrap:wrap;">
    <div class="product-grid-wrapper" style="flex:3; min-width:280px;">
        <div class="product-grid" id="productGrid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(220px,1fr)); gap:16px;">
            <?php if ($result && $result->num_rows > 0): while ($p = $result->fetch_assoc()):
                $stock = (int)$p['current_stock'];
                $displayName = trim(ucwords(strtolower($p['brand_name'])) . ' / ' . ucwords(strtolower($p['generic_name'])) . ' ' . $p['dosage'] . ' ' . ucwords(strtolower($p['form'])));
            ?>
            <div class="product" data-category="<?= htmlspecialchars(ucwords(strtolower($p['category']))) ?>" data-name-search="<?= htmlspecialchars($displayName) ?>"
                 style="background:#fff; border-radius:10px; padding:14px; box-shadow:0 2px 8px rgba(0,0,0,0.06);">
                <span style="display:inline-block;font-size:0.75em;color:#2563eb;background:#eff6ff;padding:2px 8px;border-radius:10px;margin-bottom:6px;"><?= htmlspecialchars(ucwords(strtolower($p['category']))) ?></span>
                <h4 style="margin:4px 0;"><?= htmlspecialchars(ucwords(strtolower($p['brand_name']))) ?></h4>
                <p style="font-size:0.85em;color:#666;margin:0 0 6px;"><?= htmlspecialchars(ucwords(strtolower($p['generic_name']))) ?> · <?= htmlspecialchars($p['dosage']) ?> · <?= htmlspecialchars(ucwords(strtolower($p['form']))) ?></p>
                <p style="font-weight:700;color:#16a34a;margin:0 0 4px;">₱<?= number_format((float)$p['price'], 2) ?></p>
                <p style="font-size:0.8em;color:#888;margin:0 0 10px;"><?= $stock ?> in stock</p>
                <button type="button" class="add-btn"
                    data-drug-id="<?= (int)$p['drug_id'] ?>"
                    data-lot-id="<?= (int)$p['lot_inventory_id'] ?>"
                    data-name="<?= htmlspecialchars($displayName) ?>"
                    data-price="<?= (float)$p['price'] ?>"
                    data-stock="<?= $stock ?>"
                    style="width:100%;padding:8px;border:none;border-radius:6px;background:#2563eb;color:#fff;cursor:pointer;">
                    <i class="fas fa-cart-plus"></i> Add to Cart
                </button>
            </div>
            <?php endwhile; else: ?>
                <p style="grid-column:1/-1;text-align:center;color:#666;padding:30px;">No products available right now.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="cart-panel" id="cart-panel" style="flex:1; min-width:280px; background:#fff; border-radius:10px; padding:18px; box-shadow:0 2px 8px rgba(0,0,0,0.06); position:sticky; top:20px;">
        <div class="cart-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
            <h3 style="margin:0;"><i class="fas fa-shopping-cart"></i> Your Cart</h3>
            <button type="button" id="clear-cart-btn" style="background:none;border:none;color:#e74c3c;cursor:pointer;font-size:0.85em;">Clear</button>
        </div>

        <p id="emptyCartMessage" style="text-align:center;color:#888;padding:16px 0;">Your cart is empty.</p>

        <table class="cart-table" style="width:100%; border-collapse:collapse;">
            <tbody id="cart-items"></tbody>
        </table>

        <div class="summary" style="border-top:1px dashed #ccc; margin-top:12px; padding-top:12px;">
            <p style="display:flex; justify-content:space-between;">Subtotal: <strong>₱<span id="subtotalDisplay">0.00</span></strong></p>
            <p style="display:flex; justify-content:space-between; font-size:1.1em;">Total: <strong>₱<span id="total-price">0.00</span></strong></p>
        </div>

        <button type="button" id="checkout-btn" disabled style="width:100%;margin-top:10px;padding:12px;border:none;border-radius:8px;background:#16a34a;color:#fff;font-weight:600;cursor:pointer;">
            Submit Order for Pickup
        </button>
        <p style="font-size:0.78em;color:#888;margin-top:8px;text-align:center;">Payment is completed in-store upon pickup.</p>
    </div>
</div>