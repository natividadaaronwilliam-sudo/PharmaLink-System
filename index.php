<?php
// FILE: index.php (Ang Login Page)

session_start(); 

// Kung naka-login na ang user, i-redirect na agad
if (isset($_SESSION['user_id'])) {
    $role = strtolower($_SESSION['user_role'] ?? '');
    switch($role) {
        case 'admin':
            header('Location: admin.php');
            exit;
        case 'cashier':
            header('Location: cashier.php');
            exit;
        case 'customer':
            header('Location: customer.php');
            exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Pharmacy Login</title>
    <link rel="stylesheet" href="assets/theme.css" />
    <link rel="stylesheet" href="login.css" /> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
</head>
<body>
    <div class="login-page" id="loginPage">
        <div class="login-branding">
            <div class="brand-mark">
                <i class="fa-solid fa-capsules"></i>
            </div>
            <h1>PharmaLink</h1>
            <p>One connected platform for inventory, sales, and customer care — built for pharmacy teams that move fast.</p>
            <ul class="brand-points">
                <li><i class="fa-solid fa-circle-check"></i> Real-time inventory &amp; sales tracking</li>
                <li><i class="fa-solid fa-circle-check"></i> Built for Admin, Cashier &amp; Customer roles</li>
                <li><i class="fa-solid fa-circle-check"></i> Secure, role-based access</li>
            </ul>
        </div>

        <div class="login-container">
            <div class="login-card"> 
                
                <div class="login-icon">
                    <i class="fa-solid fa-user-lock"></i>
                </div>
                <h2>Welcome Back</h2>
                <p>Sign in to access your account</p>
                
                <form id="loginForm" method="POST">
                    
                    <div class="input-group">
                        <i class="fa-solid fa-user"></i>
                        <input type="text" id="email" name="username" placeholder="Username" required>
                    </div>

                    <div class="input-group">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" id="password" name="password" placeholder="Password" required>
                        <i class="fas fa-eye toggle-password"></i> 
                    </div>

                    <button type="submit" class="login-btn">Log In</button>

                    <p class="signup-text">Don't have an account? <a href="#" id="openSignupModal">Sign up</a></p>
                </form>
                
            </div>
        </div>
    </div>

    <!-- Sign Up Popup Modal -->
    <div class="signup-modal-overlay" id="signupModalOverlay">
        <div class="login-card signup-modal-card">
            <span class="signup-modal-close" id="closeSignupModal">&times;</span>

            <div class="login-icon">
                <i class="fa-solid fa-user-plus"></i>
            </div>
            <h2>Register</h2>
            <p>Create a customer account</p>

            <div id="signupMessage"></div>

            <form id="signupForm">
                <div class="input-group">
                    <i class="fa-solid fa-file-signature"></i>
                    <input type="text" name="first_name" placeholder="First Name" required>
                </div>
                <div class="input-group">
                    <i class="fa-solid fa-file-signature"></i>
                    <input type="text" name="middle_name" placeholder="Middle Name (Optional)">
                </div>
                <div class="input-group">
                    <i class="fa-solid fa-file-signature"></i>
                    <input type="text" name="last_name" placeholder="Last Name" required>
                </div>
                <div class="input-group">
                    <i class="fa-solid fa-user"></i>
                    <input type="text" name="username" placeholder="Username" required>
                </div>
                <div class="input-group">
                    <i class="fa-solid fa-envelope"></i>
                    <input type="email" name="email" placeholder="Email Address" required>
                </div>
                <div class="input-group">
                    <i class="fa-solid fa-phone"></i>
                    <input type="text" name="phone_number" placeholder="Phone Number" required>
                </div>
                <div class="input-group">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" name="password" placeholder="Password" required>
                </div>
                <div class="input-group">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" name="confirm_password" placeholder="Confirm Password" required>
                </div>

                <button type="submit" class="login-btn">Register</button>
            </form>
        </div>
    </div>

    <!-- Post-registration confirmation popup -->
    <div class="signup-modal-overlay" id="registerSuccessOverlay">
        <div class="login-card signup-modal-card" style="text-align:center;">
            <div class="login-icon"><i class="fa-solid fa-circle-check"></i></div>
            <h2>Registered!</h2>
            <p>Your account was created successfully. Go back to the login page now?</p>
            <button type="button" class="login-btn" id="registerSuccessYes" style="margin-bottom:10px;">Yes, take me to Log In</button>
            <button type="button" class="login-btn" id="registerSuccessNo" style="background:#334155;">Stay here</button>
        </div>
    </div>
    
    <script src="login.js"></script>
    <script src="assets/theme.js"></script>
    <script>
    (function () {
        const loginPage = document.getElementById('loginPage');
        const openBtn = document.getElementById('openSignupModal');
        const closeBtn = document.getElementById('closeSignupModal');
        const overlay = document.getElementById('signupModalOverlay');
        const signupForm = document.getElementById('signupForm');
        const signupMessage = document.getElementById('signupMessage');
        const successOverlay = document.getElementById('registerSuccessOverlay');
        const successYes = document.getElementById('registerSuccessYes');
        const successNo = document.getElementById('registerSuccessNo');

        function openModal() {
            overlay.classList.add('active');
            loginPage.classList.add('blurred');
        }
        function closeModal() {
            overlay.classList.remove('active');
            loginPage.classList.remove('blurred');
            signupMessage.innerHTML = '';
        }

        openBtn.addEventListener('click', (e) => { e.preventDefault(); openModal(); });
        closeBtn.addEventListener('click', closeModal);
        overlay.addEventListener('click', (e) => { if (e.target === overlay) closeModal(); });

        signupForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const formData = new FormData(signupForm);
            const payload = Object.fromEntries(formData.entries());

            const res = await fetch('register_customer.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const result = await res.json();

            if (result.success) {
                overlay.classList.remove('active');
                successOverlay.classList.add('active');
                signupForm.reset();
            } else {
                signupMessage.innerHTML = `<div class="message error">${result.message}</div>`;
            }
        });

        // Post-registration popup: go back to login, or stay on the page
        successYes.addEventListener('click', () => {
            window.location.href = 'index.php';
        });
        successNo.addEventListener('click', () => {
            successOverlay.classList.remove('active');
            loginPage.classList.remove('blurred');
        });
    })();
    </script>
</body>
</html>