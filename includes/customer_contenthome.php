<?php
$cid = (int)($_SESSION['user_id'] ?? 0);
$total_orders = 0;
$total_spent = 0;
$loyalty_points = 0;

if ($cid && isset($conn)) {
    $stmt = $conn->prepare("
        SELECT COUNT(order_id) AS total_orders, COALESCE(SUM(total_amount), 0) AS total_spent
        FROM customer_orders
        WHERE customer_id = ? AND order_status IN ('Completed', 'Delivered', 'Ready for Pickup')
    ");
    $stmt->bind_param('i', $cid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $total_orders = (int)($row['total_orders'] ?? 0);
    $total_spent = (float)($row['total_spent'] ?? 0);
    $stmt->close();

    $stmt2 = $conn->prepare('SELECT loyalty_points FROM customers WHERE customer_id = ?');
    $stmt2->bind_param('i', $cid);
    $stmt2->execute();
    $pts = $stmt2->get_result()->fetch_assoc();
    $loyalty_points = (float)($pts['loyalty_points'] ?? 0);
    $stmt2->close();
}
?>
<div class="dashboard-header">
    <h2>Welcome back!</h2>
    <p class="subtitle">Browse medicines, upload prescriptions, and track your orders.</p>
</div>

<div class="dashboard-cards">
    <div class="card"><i class="fas fa-shopping-bag"></i><h3>My Orders</h3><p class="value" style="font-size:1.6em;font-weight:700;color:#574b90;"><?= $total_orders ?></p></div>
    <div class="card"><i class="fas fa-peso-sign"></i><h3>Total Spent</h3><p class="value" style="font-size:1.6em;font-weight:700;color:#16a34a;">₱<?= number_format($total_spent, 2) ?></p></div>
    <div class="card"><i class="fas fa-star"></i><h3>Loyalty Points</h3><p class="value" style="font-size:1.6em;font-weight:700;color:#f59e0b;"><?= number_format($loyalty_points, 0) ?></p></div>
</div>

<div class="section-row">
    <div class="quick-actions">
        <h3>Quick Actions</h3>
        <div class="btn-container">
            <button type="button" onclick="loadContent('products')"><i class="fas fa-pills"></i> Shop Now</button>
            <button type="button" onclick="loadContent('orders')"><i class="fas fa-list"></i> View Orders</button>
            <button type="button" onclick="loadContent('prescription')"><i class="fas fa-file-medical"></i> Upload Rx</button>
            <button type="button" onclick="loadContent('profile')"><i class="fas fa-user"></i> My Profile</button>
        </div>
    </div>
    <div class="tips-card">
        <h3>Health Tips</h3>
        <div class="tip"><i class="fas fa-check-circle"></i><span>Take medicines as prescribed by your doctor.</span></div>
        <div class="tip"><i class="fas fa-check-circle"></i><span>Check expiry dates before purchasing.</span></div>
        <div class="tip"><i class="fas fa-check-circle"></i><span>Upload your prescription for faster processing.</span></div>
    </div>
</div>
