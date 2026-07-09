<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/db_pharmacy.php';

$customer_id = (int)($_GET['customer_id'] ?? 0);
if (!$customer_id || $customer_id !== (int)($_SESSION['user_id'] ?? 0)) {
    echo '<p style="color:red;padding:20px;">Unauthorized.</p>';
    exit;
}

$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

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
?>
<div class="orders-table">
    <table>
        <thead><tr><th>Sale ID</th><th>Date</th><th>Amount</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
        <?php if ($result->num_rows === 0): ?>
            <tr><td colspan="5" style="text-align:center;color:#888;padding:20px;">No walk-in purchases found.</td></tr>
        <?php else: while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td>#<?= (int)$row['order_id'] ?></td>
                <td><?= date('M d, Y h:i A', strtotime($row['order_date'])) ?></td>
                <td>₱<?= number_format((float)$row['total_amount'], 2) ?></td>
                <td><?= htmlspecialchars(ucfirst($row['order_status'])) ?></td>
                <td><button type="button" onclick="showOrderDetails(<?= (int)$row['order_id'] ?>)" style="padding:6px 10px;background:#007bff;color:#fff;border:none;border-radius:4px;cursor:pointer;">View</button></td>
            </tr>
        <?php endwhile; endif; ?>
        </tbody>
    </table>
</div>
<?php
$stmt->close();
$conn->close();
