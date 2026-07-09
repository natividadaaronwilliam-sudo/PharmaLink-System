<?php
// register_customer.php
// AJAX (JSON in/out) version of the registration logic used by the new
// "Sign up" popup modal on index.php. Mirrors customer_register.php's
// dual-insert (users + customers) logic exactly, just without the HTML page.
header('Content-Type: application/json');
require 'db_pharmacy.php';

$customer_role_id = 3;
$customer_type_default = 'Regular';
$loyalty_points_default = 0.00;
$default_address = 'N/A';

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$first_name       = trim($data['first_name'] ?? '');
$middle_name      = trim($data['middle_name'] ?? '');
$last_name        = trim($data['last_name'] ?? '');
$username         = trim($data['username'] ?? '');
$email            = trim($data['email'] ?? '');
$phone_number     = trim($data['phone_number'] ?? '');
$password         = $data['password'] ?? '';
$confirm_password = $data['confirm_password'] ?? '';

if (empty($first_name) || empty($last_name) || empty($username) || empty($email) || empty($phone_number) || empty($password) || empty($confirm_password)) {
    echo json_encode(['success' => false, 'message' => 'All fields (except Middle Name) are required.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
    exit;
}
if ($password !== $confirm_password) {
    echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
    exit;
}

$stmt_check_user = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
$stmt_check_user->bind_param("s", $username);
$stmt_check_user->execute();
$stmt_check_user->store_result();

$stmt_check_email = $conn->prepare("SELECT customer_id FROM customers WHERE email = ?");
$stmt_check_email->bind_param("s", $email);
$stmt_check_email->execute();
$stmt_check_email->store_result();

if ($stmt_check_user->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Username is already taken. Please choose another one.']);
    $stmt_check_user->close();
    $stmt_check_email->close();
    $conn->close();
    exit;
} elseif ($stmt_check_email->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Email address is already registered.']);
    $stmt_check_user->close();
    $stmt_check_email->close();
    $conn->close();
    exit;
}
$stmt_check_user->close();
$stmt_check_email->close();

$hashed_password = password_hash($password, PASSWORD_DEFAULT);
$conn->begin_transaction();

try {
    $stmt_user = $conn->prepare("INSERT INTO users (username, password, role_id) VALUES (?, ?, ?)");
    $stmt_user->bind_param("ssi", $username, $hashed_password, $customer_role_id);
    if (!$stmt_user->execute()) {
        throw new Exception("Error inserting into users table: " . $stmt_user->error);
    }
    $user_id_fk = $conn->insert_id;
    $stmt_user->close();

    $stmt_customer = $conn->prepare("
        INSERT INTO customers
        (customer_id, first_name, middle_name, last_name, username, address, phone_number, customer_type, password, loyalty_points, email)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt_customer->bind_param(
        "issssssssds",
        $user_id_fk,
        $first_name,
        $middle_name,
        $last_name,
        $username,
        $default_address,
        $phone_number,
        $customer_type_default,
        $hashed_password,
        $loyalty_points_default,
        $email
    );
    if (!$stmt_customer->execute()) {
        throw new Exception("Error inserting into customers table: " . $stmt_customer->error);
    }
    $stmt_customer->close();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Registration successful! You can now log in.']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()]);
}

$conn->close();
?>
