<?php
// FILE: get_customer_profile_data.php
// Returns the CURRENTLY LOGGED-IN customer's own profile + points as JSON.
// Deliberately uses $_SESSION['user_id'] only (never a client-supplied id)
// so one customer can never pull another customer's data.

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'db_pharmacy.php';

header('Content-Type: application/json');

$customer_id = $_SESSION['user_id'] ?? null;

if (!$customer_id) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

$stmt = $conn->prepare("
    SELECT first_name, middle_name, last_name, email, phone_number, address,
           customer_type, loyalty_points, profile_image
    FROM customers
    WHERE customer_id = ?
");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $customer = $result->fetch_assoc();
    $customer['loyalty_points'] = number_format((float)$customer['loyalty_points'], 2);
    echo json_encode(['success' => true, 'data' => $customer]);
} else {
    echo json_encode(['success' => false, 'message' => 'Customer not found.']);
}
$stmt->close();
$conn->close();
