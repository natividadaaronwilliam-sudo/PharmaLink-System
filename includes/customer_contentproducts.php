<?php
$products = [];
$sql = "SELECT dm.drug_id, dm.generic_name, dm.brand_name, dm.dosage, dm.form, dm.category,
               il.lot_inventory_id, il.price, il.current_stock
        FROM drugs_master dm
        JOIN inventory_lots il ON dm.drug_id = il.drug_id
        WHERE il.current_stock > 0 AND il.is_active = 1
          AND il.expiration_date >= CURDATE() AND dm.is_active = 1
        ORDER BY dm.generic_name ASC, il.expiration_date ASC";
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $products[] = $row;
    }
}

$categories = [];
foreach ($products as $p) {
    if (!empty($p['category'])) {
        $categories[strtolower($p['category'])] = ucfirst($p['category']);
    }
}
asort($categories);
?>
<div class="products-header">
    <h2>Available Products</h2>
    <p class="subtitle">Add items to your cart and submit for pharmacy pickup.</p>
</div>

<div style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:16px;">
    <input type="text" id="searchInput" placeholder="Search medicines..." style="flex:1; min-width:200px; padding:10px; border:1px solid #ccc; border-radius:6px;">
    <select id="categoryFilter" style="padding:10px; border:1px solid #ccc; border-radius:6px;">
        <option value="">All Categories</option>
        <?php foreach ($categories as $val => $label): ?>
        <option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($label) ?></option>
        <?php endforeach; ?>
    </select>
</div>

<div class="products-container">
    <div class="product-grid-wrapper">
        <div class="product-grid" id="productGrid">
            <?php if (empty($products)): ?>
                <p style="padding:20px;color:#666;">No products available right now.</p>
            <?php else: ?>
                <?php foreach ($products as $p):
                    $name = trim($p['generic_name'] . ' ' . $p['dosage'] . ' ' . $p['form']);
                    $display = $name . ($p['brand_name'] ? ' (' . $p['brand_name'] . ')' : '');
                    $cat = strtolower($p['category'] ?? '');
                    $searchKey = strtolower($display . ' ' . ($p['brand_name'] ?? '') . ' ' . $cat);
                ?>
                <div class="product" style="width:calc(33.333% - 10px); min-width:180px;"
                     data-category="<?= htmlspecialchars($cat) ?>"
                     data-name-search="<?= htmlspecialchars($searchKey) ?>">
                    <h4 style="font-size:14px; margin-bottom:6px;"><?= htmlspecialchars($display) ?></h4>
                    <p style="color:#666; font-size:13px;"><?= htmlspecialchars(ucfirst($cat)) ?></p>
                    <p style="font-weight:bold; color:#28a745; margin:8px 0;">₱<?= number_format((float)$p['price'], 2) ?></p>
                    <p style="font-size:12px; color:#555;">Stock: <?= (int)$p['current_stock'] ?></p>
                    <button type="button" class="add-btn"
                        data-lot-id="<?= (int)$p['lot_inventory_id'] ?>"
                        data-drug-id="<?= (int)$p['drug_id'] ?>"
                        data-name="<?= htmlspecialchars($display) ?>"
                        data-price="<?= (float)$p['price'] ?>"
                        data-stock="<?= (int)$p['current_stock'] ?>">
                        Add to Cart
                    </button>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div id="cart-panel" style="display:none;">
        <h3 style="margin-bottom:12px;"><i class="fas fa-shopping-cart"></i> Your Cart</h3>
        <table style="width:100%; border-collapse:collapse; font-size:14px;">
            <thead>
                <tr style="border-bottom:2px solid #eee;">
                    <th style="text-align:left; padding:6px 0;">Item</th>
                    <th style="text-align:center;">Qty</th>
                    <th style="text-align:right;">Total</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="cart-items"></tbody>
        </table>
        <p id="emptyCartMessage" style="text-align:center; color:#888; padding:16px 0;">Your cart is empty.</p>
        <div class="summary">
            <p>Subtotal: <span id="subtotalDisplay">₱0.00</span></p>
            <p>Total: <span id="total-price">₱0.00</span></p>
            <button type="button" id="checkout-btn">Submit Order for Pickup</button>
            <button type="button" id="clear-cart-btn" style="width:100%; margin-top:8px; padding:8px; background:#6b7280; color:#fff; border:none; border-radius:6px; cursor:pointer;">Clear Cart</button>
        </div>
    </div>
</div>
