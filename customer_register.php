<?php
// FILE: customer_registration.php (FINAL VERSION - Dual Table Insert)

// Tiyakin na ang file path ay tama para sa inyong database connection
require 'db_pharmacy.php'; 

// Set initial values
$first_name = $middle_name = $last_name = $username = $email = $phone_number = '';
$error = '';
$success = '';

// --- CONFIGURATION ---
// I-set ang role_id para sa 'Customer' role (Tiyakin na tama ang ID na ito sa inyong 'role' table)
$customer_role_id = 3; 
$customer_type_default = 'Regular'; 
$loyalty_points_default = 0.00;
$default_address = 'N/A';
// ---------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? ''); 
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // 1. Validation
    if (empty($first_name) || empty($last_name) || empty($username) || empty($email) || empty($phone_number) || empty($password) || empty($confirm_password)) {
        $error = "All fields (except Middle Name) are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        
        // 2. Check for existence
        
        // Check Username existence in 'users' table
        $stmt_check_user = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmt_check_user->bind_param("s", $username);
        $stmt_check_user->execute();
        $stmt_check_user->store_result();
        
        // Check Email existence in 'customers' table
        $stmt_check_email = $conn->prepare("SELECT customer_id FROM customers WHERE email = ?");
        $stmt_check_email->bind_param("s", $email);
        $stmt_check_email->execute();
        $stmt_check_email->store_result();

        if ($stmt_check_user->num_rows > 0) {
            $error = "Username is already taken. Please choose another one.";
        } elseif ($stmt_check_email->num_rows > 0) {
            $error = "Email address is already registered.";
        }
        
        $stmt_check_user->close();
        $stmt_check_email->close();

        if ($error === '') {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $success_flag = false;

            // --- START TRANSACTION ---
            $conn->begin_transaction();

            try {
                // A. INSERT INTO USERS TABLE (Authentication credentials)
                $stmt_user = $conn->prepare("
                    INSERT INTO users (username, password, role_id) 
                    VALUES (?, ?, ?)
                ");
                $stmt_user->bind_param("ssi", $username, $hashed_password, $customer_role_id);

                if (!$stmt_user->execute()) {
                    throw new Exception("Error inserting into users table: " . $stmt_user->error);
                }
                
                // Kunin ang auto-generated user_id (Ito ay gagamitin bilang customer_id)
                $user_id_fk = $conn->insert_id; 
                $stmt_user->close();

                // B. INSERT INTO CUSTOMERS TABLE (Profile details)
                $stmt_customer = $conn->prepare("
                    INSERT INTO customers 
                    (customer_id, first_name, middle_name, last_name, username, address, phone_number, customer_type, password, loyalty_points, email) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                // Binding String: "issssssssds" (1 int, 9 strings, 1 decimal)
                $stmt_customer->bind_param(
                    "issssssssds", 
                    $user_id_fk,            // 1. int (customer_id = user_id)
                    $first_name,            // 2. string
                    $middle_name,           // 3. string
                    $last_name,             // 4. string
                    $username,              // 5. string
                    $default_address,       // 6. string
                    $phone_number,          // 7. string
                    $customer_type_default, // 8. string
                    $hashed_password,       // 9. string
                    $loyalty_points_default,// 10. decimal
                    $email                  // 11. string
                );

                if (!$stmt_customer->execute()) {
                    throw new Exception("Error inserting into customers table: " . $stmt_customer->error);
                }
                $stmt_customer->close();
                
                // Commit the transaction
                $conn->commit();
                $success = "Registration successful! You can now log in.";
                $success_flag = true;

            } catch (Exception $e) {
                // Rollback the transaction on failure
                $conn->rollback();
                $error = "Registration failed: " . $e->getMessage();
            }

            // Clear inputs only on successful registration
            if ($success_flag) {
                $first_name = $middle_name = $last_name = $username = $email = $phone_number = ''; 
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Customer Registration</title>
    <link rel="stylesheet" href="assets/theme.css" />
    <link rel="stylesheet" href="login.css" /> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <style>
        /* CSS Overrides (Inline style para sa registration card, na dapat ay nasa login.css) */
        .login-card { 
            max-height: 90vh; 
            overflow-y: auto; 
            padding: 25px 30px; 
            text-align: center;
        }
        .input-group {
            margin-bottom: 15px; 
        }
        /* Error/Success Messages Styling */
        .message { padding: 10px; margin: 10px 0; border-radius: 8px; text-align: center; font-size: 0.9em; }
        .error { background-color: #721c24; color: #f8d7da; border: 1px solid #f5c6cb; }
        .success { background-color: #155724; color: #d4edda; border: 1px solid #c3e6cb; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card"> 
            
            <div class="login-icon">
                <i class="fa-solid fa-user-plus"></i>
            </div>
            <h2>Register</h2>
            <p>Create a customer account</p>
            
            <?php if ($error): ?>
                <div class="message error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="message success"><?php echo $success; ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                
                <div class="input-group">
                    <i class="fa-solid fa-file-signature"></i>
                    <input type="text" id="last_name" name="last_name" placeholder="Last Name" value="<?php echo htmlspecialchars($last_name ?? ''); ?>" required>
                </div>

                <div class="input-group">
                    <i class="fa-solid fa-file-signature"></i>
                    <input type="text" id="first_name" name="first_name" placeholder="First Name" value="<?php echo htmlspecialchars($first_name ?? ''); ?>" required>
                </div>

                <div class="input-group">
                    <i class="fa-solid fa-file-signature"></i>
                    <input type="text" id="middle_name" name="middle_name" placeholder="Middle Name (Optional)" value="<?php echo htmlspecialchars($middle_name ?? ''); ?>">
                </div>
                
                <div class="input-group">
                    <i class="fa-solid fa-user"></i>
                    <input type="text" id="username" name="username" placeholder="Username" value="<?php echo htmlspecialchars($username ?? ''); ?>" required>
                </div>

                <div class="input-group">
                    <i class="fa-solid fa-envelope"></i>
                    <input type="email" id="email" name="email" placeholder="Email Address" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                </div>
                
                <div class="input-group">
                    <i class="fa-solid fa-phone"></i>
                    <input type="text" id="phone_number" name="phone_number" placeholder="Phone Number" value="<?php echo htmlspecialchars($phone_number ?? ''); ?>" required>
                </div>

                <div class="input-group">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" id="password" name="password" placeholder="Password" required>
                </div>

                <div class="input-group">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required>
                </div>

                <button type="submit" class="login-btn">Register</button>

                <p class="signup-text">Already have an account? <a href="index.php">Sign in</a></p>
            </form>
            
        </div>
    </div>
    <script src="assets/theme.js"></script>
    <?php if ($success): ?>
    <script>
        if (confirm("Registration successful! Go back to the login page now?")) {
            window.location.href = "index.php";
        }
    </script>
    <?php endif; ?>
</body>
</html>