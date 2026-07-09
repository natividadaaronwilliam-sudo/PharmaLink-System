<?php
// FILE: login.php (FINAL AND FIXED VERSION)

session_start();
header('Content-Type: application/json');
require 'db_pharmacy.php'; // I-assume na ito ang tamang path sa inyong connection file

// Function para magpadala ng JSON response
function sendJsonResponse($success, $message = null, $role = null, $firstName = null) {
    echo json_encode(['success' => $success, 'message' => $message, 'role' => $role, 'firstName' => $firstName]);
    exit;
}

// 1. Tanggapin ang JSON input mula sa JavaScript (Fetch API)
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

$username = $data['username'] ?? '';
$password = $data['password'] ?? '';

if (empty($username) || empty($password)) {
    sendJsonResponse(false, "Username and password are required.");
}

// 2. Database Query: Kunin ang Password Hash, Role Name, at First Name
$stmt = $conn->prepare("
    SELECT 
        u.user_id, 
        u.password AS hashed_password, 
        r.role_name,
        COALESCE(s.first_name, c.first_name, r.role_name) AS display_name
    FROM users u
    JOIN role r ON u.role_id = r.role_id
    LEFT JOIN staff_info s ON u.user_id = s.user_id 
    LEFT JOIN customers c ON u.user_id = c.customer_id 
    WHERE u.username = ? AND u.is_active = 1  -- <-- DITO IDADAGDAG ANG IS_ACTIVE CHECK
");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    $stmt->close();

    // 3. Password Verification
    if (password_verify($password, $user['hashed_password'])) {
        
        // Login Successful!
        
$displayName = $user['display_name'];
    
    // Kung ang role ay 'Admin' o 'Cashier', huwag na itong i-ucwords
    if (strtolower($displayName) !== 'admin' && strtolower($displayName) !== 'cashier') {
        // I-capitalize ang bawat salita
        $displayName = ucwords(strtolower($displayName)); 
    }

    // 2. I-set ang Session Variables
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['user_role'] = $user['role_name'];
    $_SESSION['user_first_name'] = $displayName; 
    
    // 3. Ipadala ang success response pabalik sa JavaScript
    sendJsonResponse(
        true, 
        "Login successful!", 
        $user['role_name'], 
        $displayName // Ipadala ang na-format na pangalan
    );

    } else {
        sendJsonResponse(false, "Invalid username or password.");
    }
} else {
    $stmt->close();
    sendJsonResponse(false, "Invalid username or password.");
}

$conn->close();
?>