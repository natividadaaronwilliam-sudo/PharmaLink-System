<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/db_pharmacy.php';

$customer_id = (int)($_GET['customer_id'] ?? $_SESSION['user_id'] ?? 0);
$session_id = (int)($_SESSION['user_id'] ?? 0);

if (!$customer_id || $customer_id !== $session_id) {
    echo '<p style="color:red;padding:20px;">Unauthorized access.</p>';
    exit;
}

$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$type = $_GET['type'] ?? 'online';

function statusClass(string $status): string {
    return strtolower(str_replace(' ', '-', $status));
}
?>
<div class="orders-header">
    <h2>My Orders</h2>
    <p class="subtitle">Track your online orders and in-store purchases.</p>
</div>

<div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px; align-items:end;">
    <div>
        <label style="font-size:13px;">From</label><br>
        <input type="date" id="order_start_date" value="<?= htmlspecialchars($start_date) ?>" style="padding:8px; border:1px solid #ccc; border-radius:6px;">
    </div>
    <div>
        <label style="font-size:13px;">To</label><br>
        <input type="date" id="order_end_date" value="<?= htmlspecialchars($end_date) ?>" style="padding:8px; border:1px solid #ccc; border-radius:6px;">
    </div>
    <button type="button" id="apply_order_filter_btn" style="padding:8px 14px; background:#574b90; color:#fff; border:none; border-radius:6px; cursor:pointer;">Apply Filter</button>
    <div style="margin-left:auto; display:flex; gap:8px;">
        <button type="button" class="order-type-tab" data-type="online" style="padding:8px 12px; border:1px solid #574b90; background:<?= $type === 'online' ? '#574b90' : '#fff' ?>; color:<?= $type === 'online' ? '#fff' : '#574b90' ?>; border-radius:6px; cursor:pointer;">Online Orders</button>
        <button type="button" class="order-type-tab" data-type="walkin" style="padding:8px 12px; border:1px solid #574b90; background:<?= $type === 'walkin' ? '#574b90' : '#fff' ?>; color:<?= $type === 'walkin' ? '#fff' : '#574b90' ?>; border-radius:6px; cursor:pointer;">Walk-in Sales</button>
    </div>
</div>

<div class="orders-table" id="orders-table-wrap">
    <table>
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Date</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $rows = [];
        if ($type === 'walkin') {
            $sql = "SELECT sale_id AS order_id, date_created AS order_date, total_amount, status AS order_status
                    FROM sales WHERE customer_id = ? AND status = 'completed'";
            $types = 'i';
            $params = [$customer_id];
            if ($start_date && $end_date) {
                $sql .= ' AND DATE(date_created) BETWEEN ? AND ?';
                $types .= 'ss';
                $params[] = $start_date;
                $params[] = $end_date;
            }
            $sql .= ' ORDER BY date_created DESC';
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $row['order_status'] = ucfirst($row['order_status']);
                $rows[] = $row;
            }
            $stmt->close();
        } else {
            $sql = "SELECT order_id, order_date, total_amount, order_status FROM customer_orders WHERE customer_id = ?";
            $types = 'i';
            $params = [$customer_id];
            if ($start_date && $end_date) {
                $sql .= ' AND DATE(order_date) BETWEEN ? AND ?';
                $types .= 'ss';
                $params[] = $start_date;
                $params[] = $end_date;
            }
            $sql .= ' ORDER BY order_date DESC';
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            $stmt->close();
        }

        if (empty($rows)): ?>
            <tr><td colspan="5" style="text-align:center; color:#888; padding:20px;">No orders found.</td></tr>
        <?php else:
            foreach ($rows as $row):
                $cls = statusClass($row['order_status']);
        ?>
            <tr>
                <td>#<?= (int)$row['order_id'] ?></td>
                <td><?= date('M d, Y h:i A', strtotime($row['order_date'])) ?></td>
                <td>₱<?= number_format((float)$row['total_amount'], 2) ?></td>
                <td><span class="status <?= htmlspecialchars($cls) ?>"><?= htmlspecialchars($row['order_status']) ?></span></td>
                <td><button type="button" onclick="showOrderDetails(<?= (int)$row['order_id'] ?>)" style="padding:6px 10px; background:#007bff; color:#fff; border:none; border-radius:4px; cursor:pointer;">View</button></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<script>
document.querySelectorAll('.order-type-tab').forEach(btn => {
    btn.addEventListener('click', () => {
        const t = btn.dataset.type || 'online';
        const start = document.getElementById('order_start_date')?.value || '';
        const end = document.getElementById('order_end_date')?.value || '';
        let url = `includes/customer_contentorders.php?customer_id=<?= $customer_id ?>&type=${t}`;
        if (start) url += `&start_date=${start}`;
        if (end) url += `&end_date=${end}`;
        fetch(url).then(r => r.text()).then(html => {
            const section = document.getElementById('orders');
            if (section) section.innerHTML = html;
        });
    });
});
</script>
