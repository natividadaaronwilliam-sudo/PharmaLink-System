<?php
/**
 * FILE: login.php
 *
 * DID NOT EXIST. index.php's login form (via login.js) POSTs JSON here for
 * ALL roles (Admin, Cashier/Pharmacist, Customer) — with this file missing,
 * nobody could log in through the main entry point at all.
 *
 * Checks staff (users + staff_info + role) first, then falls back to the
 * customers table, matching customer_login.php's own conventions (same
 * $_SESSION keys) so a customer logging in through either page behaves
 * identically.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
require_once 'db_pharmacy.php';

$input = json_decode(file_get_contents('php://input'), true);
$username = trim($input['username'] ?? '');
$password = $input['password'] ?? '';

if ($username === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Username and password are required.']);
    exit;
}

// ---- 1. Try staff (Admin / Cashier-Pharmacist) ----
$stmt = $conn->prepare(
    "SELECT u.user_id, u.password, r.role_name, s.first_name
     FROM users u
     JOIN role r ON u.role_id = r.role_id
     LEFT JOIN staff_info s ON s.user_id = u.user_id
     WHERE u.username = ? AND u.is_active = 1"
);
$stmt->bind_param('s', $username);
$stmt->execute();
$staff = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($staff && password_verify($password, $staff['password'])) {
    $_SESSION['user_id'] = $staff['user_id'];
    $_SESSION['user_role'] = $staff['role_name'];
    $_SESSION['user_first_name'] = $staff['first_name'] ?? $username;

    echo json_encode([
        'success'   => true,
        'role'      => $staff['role_name'],
        'firstName' => $staff['first_name'] ?? $username,
    ]);
    $conn->close();
    exit;
}

// ---- 2. Fall back to customers ----
$stmt = $conn->prepare("SELECT customer_id, first_name, last_name, password FROM customers WHERE username = ?");
$stmt->bind_param('s', $username);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($customer && password_verify($password, $customer['password'])) {
    $_SESSION['user_id'] = $customer['customer_id'];
    $_SESSION['customer_id'] = $customer['customer_id'];
    $_SESSION['customer_name'] = $customer['first_name'] . ' ' . $customer['last_name'];
    $_SESSION['user_first_name'] = $customer['first_name'];
    $_SESSION['user_role'] = 'Customer';

    echo json_encode([
        'success'   => true,
        'role'      => 'Customer',
        'firstName' => $customer['first_name'],
    ]);
    $conn->close();
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid username or password.']);
$conn->close();