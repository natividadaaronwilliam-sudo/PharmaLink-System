<?php
header('Content-Type: application/json');
require_once 'db_pharmacy.php';

$limit = min(50, max(1, (int)($_GET['limit'] ?? 10)));

$stmt = $conn->prepare("
    SELECT date, admin_name, action, details
    FROM activity_logs
    ORDER BY date DESC
    LIMIT ?
");
$stmt->bind_param('i', $limit);
$stmt->execute();
$result = $stmt->get_result();

$logs = [];
while ($row = $result->fetch_assoc()) {
    $logs[] = [
        'date' => date('Y-m-d H:i', strtotime($row['date'])),
        'admin_name' => $row['admin_name'],
        'action' => $row['action'],
        'details' => $row['details'],
    ];
}

echo json_encode(['logs' => $logs]);
$stmt->close();
$conn->close();
