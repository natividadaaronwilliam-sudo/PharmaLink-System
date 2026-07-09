// FILE: login.js 

document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const passwordInput = document.getElementById('password');
    const togglePassword = document.querySelector('.toggle-password');

    if (loginForm) {
        loginForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const usernameValue = document.getElementById('email').value.trim();
            const passwordValue = passwordInput.value.trim();

            const response = await fetch('login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username: usernameValue, password: passwordValue })
            });

            const result = await response.json();

            if (result.success) {
                // Gumamit ng result.firstName na ibinalik ng login.php
                alert(`Logged in as ${result.role.toUpperCase()}! Welcome, ${result.firstName || 'User'}!`); 
                
                const userRole = result.role.toLowerCase(); 

                switch(userRole) {
                    case 'admin':
                        window.location.href = "admin.php"; 
                        break;
                    case 'cashier/pharmacist':
                        window.location.href = "cashier.php"; 
                        break;
                    case 'customer':
                        window.location.href = "customer.php"; 
                        break;
                    default:
                        alert("Login successful, but user role is unrecognized. Please contact support.");
                }
            } else {
                alert(result.message || "Invalid username or password!");
            }
        });
    }

    if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', () => {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            togglePassword.classList.toggle('fa-eye-slash');
            togglePassword.classList.toggle('fa-eye');
        });
    }
});