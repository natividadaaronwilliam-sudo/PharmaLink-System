<?php
if (!isset($conn) || !($conn instanceof mysqli)) {
    require_once __DIR__ . '/../db_pharmacy.php';
}
$customer_id = $_SESSION['user_id'] ?? 0;

$total_orders = 0;
$total_spent = '0.00';
$loyalty_points = 0;

if ($customer_id) {
    $stmt = $conn->prepare("SELECT COUNT(order_id) AS total_orders, COALESCE(SUM(total_amount),0) AS total_spent
                             FROM customer_orders WHERE customer_id = ? AND order_status IN ('Completed','Delivered')");
    $stmt->bind_param('i', $customer_id);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $total_orders = (int)($stats['total_orders'] ?? 0);
    $total_spent = number_format((float)($stats['total_spent'] ?? 0), 2);

    $stmt2 = $conn->prepare("SELECT loyalty_points FROM customers WHERE customer_id = ?");
    $stmt2->bind_param('i', $customer_id);
    $stmt2->execute();
    $pts = $stmt2->get_result()->fetch_assoc();
    $stmt2->close();
    $loyalty_points = $pts['loyalty_points'] ?? 0;
}
?>
<div class="dashboard-header" style="margin-bottom:20px;">
    <h2 style="color:#1e3a8a;">Welcome back, <?= htmlspecialchars($_SESSION['user_first_name'] ?? 'Customer') ?>!</h2>
    <p style="color:#555;">Here's a quick look at your PharmaLink account.</p>
</div>

<div class="dashboard-cards" style="display:flex; flex-wrap:wrap; gap:20px; margin-bottom:28px;">
    <div class="card" style="flex:1; min-width:200px; padding:18px; border-radius:10px; background:#fff; box-shadow:0 2px 10px rgba(0,0,0,0.05);">
        <i class="fas fa-receipt" style="color:#2563eb; font-size:22px;"></i>
        <h3 style="margin-top:10px;">Completed Orders</h3>
        <p style="color:#2563eb; font-size:1.6em; font-weight:700;"><?= $total_orders ?></p>
    </div>
    <div class="card" style="flex:1; min-width:200px; padding:18px; border-radius:10px; background:#fff; box-shadow:0 2px 10px rgba(0,0,0,0.05);">
        <i class="fas fa-money-bill-wave" style="color:#16a34a; font-size:22px;"></i>
        <h3 style="margin-top:10px;">Total Spent</h3>
        <p style="color:#16a34a; font-size:1.6em; font-weight:700;">₱<?= $total_spent ?></p>
    </div>
    <div class="card" style="flex:1; min-width:200px; padding:18px; border-radius:10px; background:#fff; box-shadow:0 2px 10px rgba(0,0,0,0.05);">
        <i class="fas fa-star" style="color:#f59e0b; font-size:22px;"></i>
        <h3 style="margin-top:10px;">Loyalty Points</h3>
        <p style="color:#f59e0b; font-size:1.6em; font-weight:700;"><?= htmlspecialchars((string)$loyalty_points) ?></p>
    </div>
</div>

<div style="display:flex; gap:20px; flex-wrap:wrap;">
    <!-- Recent Orders (70%) -->
    <div class="card" style="flex:7; min-width:320px; padding:20px; border-radius:10px; background:#fff; box-shadow:0 2px 10px rgba(0,0,0,0.05);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
            <h3 style="color:#1e3a8a; margin:0;"><i class="fas fa-receipt"></i> Recent Orders</h3>
            <a href="javascript:void(0)" onclick="document.querySelector('.nav-item[data-target=orders]').click()" style="font-size:0.85em; color:#2563eb; text-decoration:none; font-weight:600;">View All &rarr;</a>
        </div>
        <?php
        $recent_orders = [];
        if ($customer_id) {
            $rstmt = $conn->prepare(
                "SELECT order_id, order_date, order_status, total_amount
                 FROM customer_orders WHERE customer_id = ? ORDER BY order_date DESC LIMIT 5"
            );
            $rstmt->bind_param('i', $customer_id);
            $rstmt->execute();
            $rres = $rstmt->get_result();
            while ($row = $rres->fetch_assoc()) {
                $recent_orders[] = $row;
            }
            $rstmt->close();
        }
        function home_status_class($status) {
            return strtolower(str_replace(' ', '-', trim($status)));
        }
        ?>
        <?php if (empty($recent_orders)): ?>
            <p style="text-align:center; color:#888; padding:30px 0;">You haven't placed any orders yet.</p>
        <?php else: ?>
            <table style="width:100%; border-collapse:collapse; font-size:0.9em;">
                <thead>
                    <tr style="border-bottom:2px solid #e5e7eb; text-align:left; color:#6b7280;">
                        <th style="padding:8px 6px;">Order #</th>
                        <th style="padding:8px 6px;">Date</th>
                        <th style="padding:8px 6px;">Status</th>
                        <th style="padding:8px 6px; text-align:right;">Total</th>
                        <th style="padding:8px 6px; text-align:center;"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_orders as $ro): ?>
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:10px 6px;">#<?= (int)$ro['order_id'] ?></td>
                        <td style="padding:10px 6px; color:#6b7280;"><?= htmlspecialchars(date('M d, Y', strtotime($ro['order_date']))) ?></td>
                        <td style="padding:10px 6px;">
                            <span class="<?= htmlspecialchars(home_status_class($ro['order_status'])) ?>" style="padding:4px 10px;border-radius:12px;font-size:0.82em;font-weight:600;">
                                <?= htmlspecialchars($ro['order_status']) ?>
                            </span>
                        </td>
                        <td style="padding:10px 6px; text-align:right; font-weight:600;">₱<?= number_format((float)$ro['total_amount'], 2) ?></td>
                        <td style="padding:10px 6px; text-align:center;">
                            <button type="button" onclick="window.showOrderDetails(<?= (int)$ro['order_id'] ?>)" style="padding:5px 10px;border:none;border-radius:6px;background:#eff6ff;color:#2563eb;cursor:pointer;font-size:0.85em;">View</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Quick Actions (30%) -->
    <div class="quick-actions" style="flex:3; min-width:220px; background:#fff; border-radius:10px; padding:20px; box-shadow:0 2px 8px rgba(0,0,0,0.05); align-self:flex-start;">
        <h3 style="color:#1e3a8a; margin-bottom:14px;">Quick Actions</h3>
        <div style="display:flex; flex-direction:column; gap:10px;">
            <button class="quick-action-btn" onclick="document.querySelector('.nav-item[data-target=products]').click()" style="padding:10px 16px;border:none;border-radius:6px;background:#2563eb;color:#fff;cursor:pointer;text-align:left;"><i class="fas fa-capsules"></i> Shop Now</button>
            <button class="quick-action-btn" onclick="document.querySelector('.nav-item[data-target=orders]').click()" style="padding:10px 16px;border:none;border-radius:6px;background:#16a34a;color:#fff;cursor:pointer;text-align:left;"><i class="fas fa-receipt"></i> View Orders</button>
            <button class="quick-action-btn" onclick="document.querySelector('.nav-item[data-target=prescription]').click()" style="padding:10px 16px;border:none;border-radius:6px;background:#8b5cf6;color:#fff;cursor:pointer;text-align:left;"><i class="fas fa-file-prescription"></i> Upload Prescription</button>
            <button class="quick-action-btn" onclick="document.querySelector('.nav-item[data-target=profile]').click()" style="padding:10px 16px;border:none;border-radius:6px;background:#f59e0b;color:#fff;cursor:pointer;text-align:left;"><i class="fas fa-user"></i> My Profile</button>
        </div>
    </div>
</div>