<?php
if (!isset($conn) || !($conn instanceof mysqli)) {
    require_once __DIR__ . '/../db_pharmacy.php';
}
$customer_id = $_SESSION['user_id'] ?? 0;
$customer = [];
if ($customer_id) {
    $stmt = $conn->prepare("SELECT first_name, middle_name, last_name, email, phone_number, address, customer_type, loyalty_points, profile_image FROM customers WHERE customer_id = ?");
    $stmt->bind_param('i', $customer_id);
    $stmt->execute();
    $customer = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
}
$avatar_src = !empty($customer['profile_image']) ? htmlspecialchars($customer['profile_image']) : 'https://via.placeholder.com/110?text=%20';
?>
<div class="profile-container" style="max-width:700px;">
    <h2 style="color:#1e3a8a; margin-bottom:18px;">My Profile</h2>

    <div class="profile-card" style="background:#fff; border-radius:10px; padding:24px; box-shadow:0 2px 10px rgba(0,0,0,0.05); margin-bottom:20px;">
        <form id="customerProfileForm" enctype="multipart/form-data">
            <div style="display:flex; align-items:center; gap:18px; margin-bottom:20px;">
                <img id="profilePreview" src="<?= $avatar_src ?>" alt="Profile" style="width:90px;height:90px;border-radius:50%;object-fit:cover;border:2px solid #e5e7eb;">
                <div>
                    <label for="profile_image_input" style="display:inline-block;padding:8px 14px;background:#2563eb;color:#fff;border-radius:6px;cursor:pointer;font-size:0.9em;">
                        <i class="fas fa-camera"></i> Change Photo
                    </label>
                    <input type="file" id="profile_image_input" name="profile_image" accept="image/png,image/jpeg,image/webp" style="display:none;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                <div>
                    <label style="font-size:0.85em;color:#555;">First Name</label>
                    <input type="text" name="first_name" value="<?= htmlspecialchars($customer['first_name'] ?? '') ?>" required style="width:100%;padding:9px;border:1px solid #d1d5db;border-radius:6px;">
                </div>
                <div>
                    <label style="font-size:0.85em;color:#555;">Middle Name</label>
                    <input type="text" name="middle_name" value="<?= htmlspecialchars($customer['middle_name'] ?? '') ?>" style="width:100%;padding:9px;border:1px solid #d1d5db;border-radius:6px;">
                </div>
                <div>
                    <label style="font-size:0.85em;color:#555;">Last Name</label>
                    <input type="text" name="last_name" value="<?= htmlspecialchars($customer['last_name'] ?? '') ?>" required style="width:100%;padding:9px;border:1px solid #d1d5db;border-radius:6px;">
                </div>
                <div>
                    <label style="font-size:0.85em;color:#555;">Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($customer['email'] ?? '') ?>" required style="width:100%;padding:9px;border:1px solid #d1d5db;border-radius:6px;">
                </div>
                <div>
                    <label style="font-size:0.85em;color:#555;">Phone Number</label>
                    <input type="text" name="phone_number" value="<?= htmlspecialchars($customer['phone_number'] ?? '') ?>" style="width:100%;padding:9px;border:1px solid #d1d5db;border-radius:6px;">
                </div>
                <div>
                    <label style="font-size:0.85em;color:#555;">Customer Type</label>
                    <input type="text" value="<?= htmlspecialchars($customer['customer_type'] ?? 'Regular') ?>" disabled style="width:100%;padding:9px;border:1px solid #e5e7eb;border-radius:6px;background:#f9fafb;color:#888;">
                </div>
                <div style="grid-column:1/-1;">
                    <label style="font-size:0.85em;color:#555;">Address</label>
                    <input type="text" name="address" value="<?= htmlspecialchars($customer['address'] ?? '') ?>" style="width:100%;padding:9px;border:1px solid #d1d5db;border-radius:6px;">
                </div>
            </div>

            <div style="margin-top:16px; display:flex; justify-content:space-between; align-items:center;">
                <span style="color:#f59e0b; font-weight:600;"><i class="fas fa-star"></i> <?= htmlspecialchars((string)($customer['loyalty_points'] ?? 0)) ?> Loyalty Points</span>
                <button type="submit" class="update-btn" style="padding:10px 20px;border:none;border-radius:6px;background:#16a34a;color:#fff;cursor:pointer;">Save Changes</button>
            </div>
            <p id="profileFormMsg" style="margin-top:10px;font-size:0.9em;"></p>
        </form>
    </div>

    <div class="profile-card" style="background:#fff; border-radius:10px; padding:24px; box-shadow:0 2px 10px rgba(0,0,0,0.05);">
        <h3 style="margin-bottom:14px;">Change Password</h3>
        <form id="customerPasswordForm">
            <div style="display:grid; gap:12px; max-width:380px;">
                <input type="password" name="current_password" placeholder="Current password" required style="padding:9px;border:1px solid #d1d5db;border-radius:6px;">
                <input type="password" name="new_password" placeholder="New password (min 6 characters)" required style="padding:9px;border:1px solid #d1d5db;border-radius:6px;">
                <input type="password" name="confirm_password" placeholder="Confirm new password" required style="padding:9px;border:1px solid #d1d5db;border-radius:6px;">
            </div>
            <button type="submit" style="margin-top:14px;padding:10px 20px;border:none;border-radius:6px;background:#2563eb;color:#fff;cursor:pointer;">Update Password</button>
            <p id="passwordFormMsg" style="margin-top:10px;font-size:0.9em;"></p>
        </form>
    </div>
</div>

<script>
(function() {
    const previewImg = document.getElementById('profilePreview');
    const fileInput = document.getElementById('profile_image_input');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                previewImg.src = URL.createObjectURL(this.files[0]);
            }
        });
    }

    const profileForm = document.getElementById('customerProfileForm');
    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const msg = document.getElementById('profileFormMsg');
            msg.textContent = 'Saving...';
            msg.style.color = '#666';
            fetch('update_customer_profile.php', { method: 'POST', body: new FormData(profileForm) })
                .then(r => r.json())
                .then(data => {
                    msg.textContent = data.message;
                    msg.style.color = data.success ? '#16a34a' : '#e74c3c';
                    if (data.success) {
                        const welcomeSpan = document.querySelector('.header-right span');
                        if (welcomeSpan) welcomeSpan.textContent = 'Welcome, ' + profileForm.first_name.value;
                    }
                })
                .catch(() => { msg.textContent = 'Network error. Please try again.'; msg.style.color = '#e74c3c'; });
        });
    }

    const passwordForm = document.getElementById('customerPasswordForm');
    if (passwordForm) {
        passwordForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const msg = document.getElementById('passwordFormMsg');
            const body = {
                current_password: passwordForm.current_password.value,
                new_password: passwordForm.new_password.value,
                confirm_password: passwordForm.confirm_password.value,
            };
            fetch('change_password.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body)
            })
                .then(r => r.json())
                .then(data => {
                    msg.textContent = data.message;
                    msg.style.color = data.success ? '#16a34a' : '#e74c3c';
                    if (data.success) passwordForm.reset();
                })
                .catch(() => { msg.textContent = 'Network error. Please try again.'; msg.style.color = '#e74c3c'; });
        });
    }
})();
</script>