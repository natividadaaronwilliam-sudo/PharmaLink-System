<?php
// FILE: customer_login.php (UPDATED LOGIC to use Username)
session_start();

// 1. Database Connection (Ensure $conn is available)
require 'db_pharmacy.php'; 

$error = '';
$username_input = ''; // To preserve input on failure

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username_input = trim($_POST['username'] ?? ''); // Read input as username
    $password = $_POST['password'] ?? '';

    if (empty($username_input) || empty($password)) {
        $error = "Username and password are required.";
    } else {
        // Retrieve user data using USERNAME
        $stmt = $conn->prepare("SELECT customer_id, first_name, last_name, password FROM customers WHERE username = ?");
        $stmt->bind_param("s", $username_input); // Bind username
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $customer = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $customer['password'])) {
                // Login successful: Set session variables
                $_SESSION['customer_id'] = $customer['customer_id'];
                $_SESSION['customer_name'] = $customer['first_name'] . ' ' . $customer['last_name'];

                // NOTE: Every customer-facing page/endpoint (home, orders, profile,
                // notifications, prescription upload, avatar upload) reads the
                // logged-in customer id from $_SESSION['user_id'] / ['user_first_name']
                // / ['user_role'] (same convention used by the staff login in login.php).
                // These were never being set here, which made all of those pages think
                // no one was logged in. Setting them fixes that across the whole portal.
                $_SESSION['user_id'] = $customer['customer_id'];
                $_SESSION['user_first_name'] = $customer['first_name'];
                $_SESSION['user_role'] = 'Customer';
                
                // Redirect to the main customer page
                header("Location: customer.php");
                exit();
            } else {
                $error = "Invalid username or password.";
            }
        } else {
            $error = "Invalid username or password.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Login</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .container { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); width: 350px; }
        h2 { text-align: center; color: #333; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="password"] { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: 10px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        button:hover { background-color: #1e7e34; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; text-align: center; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .switch-link { display: block; text-align: center; margin-top: 15px; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Customer Login</h2>
        
        <?php if (isset($error) && $error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username_input); ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">Login</button>
        </form>
        
        <a href="customer_register.php" class="switch-link">Don't have an account? Register here.</a>
    </div>
</body>
</html>