<?php
if (!isset($conn) || !($conn instanceof mysqli)) {
    require_once __DIR__ . '/../db_pharmacy.php';
}

$customer_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$customer = [];

if ($customer_id > 0) {
    $stmt = $conn->prepare("SELECT first_name, middle_name, last_name, email, phone_number, address, customer_type, loyalty_points, profile_image, username FROM customers WHERE customer_id = ? LIMIT 1");
    $stmt->bind_param('i', $customer_id);
    $stmt->execute();
    $customer = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
}

$avatar_src = !empty($customer['profile_image'])
    ? htmlspecialchars($customer['profile_image'])
    : 'https://cdn-icons-png.flaticon.com/512/2922/2922510.png';

$first_name = $customer['first_name'] ?? '';
$middle_name = $customer['middle_name'] ?? '';
$last_name = $customer['last_name'] ?? '';
$email = $customer['email'] ?? '';
$phone_number = $customer['phone_number'] ?? '';
$address = $customer['address'] ?? '';
$username = $customer['username'] ?? '';
$customer_type = $customer['customer_type'] ?? 'Regular';
$loyalty_points = isset($customer['loyalty_points']) ? number_format((float)$customer['loyalty_points'], 2) : '0.00';
$full_name = trim($first_name . ' ' . $last_name);
?>
<div class="customer-profile-page">
    <div class="customer-profile-header">
        <div>
            <h2>My Profile</h2>
            <p>Manage your account information and login password.</p>
        </div>
    </div>

    <div class="customer-profile-layout">
        <aside class="profile-summary-card">
            <div class="profile-avatar-wrap">
                <img id="profilePreview" src="<?= $avatar_src ?>" alt="Profile photo">
                <label for="profile_image_input" class="profile-photo-btn" title="Change profile picture">
                    <i class="fas fa-camera"></i>
                </label>
                <input type="file" id="profile_image_input" name="profile_image" accept="image/png,image/jpeg,image/webp" form="customerProfileForm">
            </div>

            <h3 id="profileCardName"><?= htmlspecialchars($full_name ?: 'Customer') ?></h3>
            <p class="profile-username">@<?= htmlspecialchars($username ?: 'customer') ?></p>

            <div class="profile-pill-row">
                <span class="profile-pill profile-pill-blue"><i class="fas fa-id-card"></i><?= htmlspecialchars($customer_type) ?> Customer</span>
                <span class="profile-pill profile-pill-gold"><i class="fas fa-star"></i><?= htmlspecialchars($loyalty_points) ?> Points</span>
            </div>
        </aside>

        <section class="profile-details-card">
            <div class="profile-card-head">
                <div>
                    <h3>Account Details</h3>
                    <p>These values are loaded from your customer record.</p>
                </div>
            </div>

            <form id="customerProfileForm" enctype="multipart/form-data">
                <div class="profile-form-grid">
                    <label>
                        <span>First Name</span>
                        <input type="text" name="first_name" class="p-input" value="<?= htmlspecialchars($first_name) ?>" disabled required>
                    </label>
                    <label>
                        <span>Middle Name</span>
                        <input type="text" name="middle_name" class="p-input" value="<?= htmlspecialchars($middle_name) ?>" disabled>
                    </label>
                    <label>
                        <span>Last Name</span>
                        <input type="text" name="last_name" class="p-input" value="<?= htmlspecialchars($last_name) ?>" disabled required>
                    </label>
                    <label>
                        <span>Email</span>
                        <input type="email" name="email" class="p-input" value="<?= htmlspecialchars($email) ?>" disabled required>
                    </label>
                    <label>
                        <span>Phone</span>
                        <input type="text" name="phone_number" class="p-input" value="<?= htmlspecialchars($phone_number) ?>" disabled>
                    </label>
                    <label class="profile-field-wide">
                        <span>Address</span>
                        <textarea name="address" class="p-input" disabled><?= htmlspecialchars($address) ?></textarea>
                    </label>
                </div>

                <p id="profileFormMsg" class="profile-form-msg" aria-live="polite"></p>

                <div class="profile-action-row">
                    <button type="button" id="profile_editBtn" class="profile-btn profile-btn-primary"><i class="fas fa-pen"></i>Edit Profile</button>
                    <button type="submit" id="profile_saveBtn" class="profile-btn profile-btn-success" hidden><i class="fas fa-check"></i>Save Changes</button>
                    <button type="button" id="profile_cancelBtn" class="profile-btn profile-btn-muted" hidden>Cancel</button>
                </div>
            </form>
        </section>

        <aside class="profile-password-card">
            <h3><i class="fas fa-lock"></i>Change Password</h3>
            <p>Separate from your account details. This only changes your login password.</p>
            <form id="customerPasswordForm">
                <input type="password" name="current_password" autocomplete="current-password" placeholder="Current password" required>
                <input type="password" name="new_password" autocomplete="new-password" placeholder="New password (min 6 characters)" required>
                <input type="password" name="confirm_password" autocomplete="new-password" placeholder="Confirm new password" required>
                <button type="submit" class="profile-btn profile-btn-danger">Update Password</button>
                <p id="passwordFormMsg" class="profile-form-msg" aria-live="polite"></p>
            </form>
        </aside>
    </div>
</div>

<script>
(function() {
    const form = document.getElementById('customerProfileForm');
    const inputs = form ? form.querySelectorAll('.p-input') : [];
    const editBtn = document.getElementById('profile_editBtn');
    const saveBtn = document.getElementById('profile_saveBtn');
    const cancelBtn = document.getElementById('profile_cancelBtn');
    const msg = document.getElementById('profileFormMsg');
    const previewImg = document.getElementById('profilePreview');
    const fileInput = document.getElementById('profile_image_input');
    const original = {};
    let originalPreview = previewImg ? previewImg.src : '';

    if (!form || !editBtn || !saveBtn || !cancelBtn) return;

    inputs.forEach(input => { original[input.name] = input.value; });

    function setEditing(isEditing) {
        inputs.forEach(input => { input.disabled = !isEditing; });
        editBtn.hidden = isEditing;
        saveBtn.hidden = !isEditing;
        cancelBtn.hidden = !isEditing;
        if (msg) msg.textContent = '';
    }

    editBtn.addEventListener('click', () => setEditing(true));

    cancelBtn.addEventListener('click', () => {
        inputs.forEach(input => { input.value = original[input.name] || ''; });
        if (fileInput) fileInput.value = '';
        if (previewImg) previewImg.src = originalPreview;
        setEditing(false);
    });

    if (fileInput && previewImg) {
        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                previewImg.src = URL.createObjectURL(this.files[0]);
            }
        });
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        if (msg) {
            msg.textContent = 'Saving...';
            msg.style.color = '#6b7280';
        }

        fetch('update_customer_profile.php', { method: 'POST', body: new FormData(form) })
            .then(response => response.json())
            .then(data => {
                if (msg) {
                    msg.textContent = data.message || (data.success ? 'Profile updated.' : 'Failed to update profile.');
                    msg.style.color = data.success ? '#16a34a' : '#e74c3c';
                }

                if (!data.success) return;

                const customer = data.customer || {};
                inputs.forEach(input => {
                    if (Object.prototype.hasOwnProperty.call(customer, input.name)) {
                        input.value = customer[input.name] || '';
                    }
                    original[input.name] = input.value;
                });

                setEditing(false);

                const fullName = `${form.first_name.value} ${form.last_name.value}`.trim() || 'Customer';
                const cardName = document.getElementById('profileCardName');
                if (cardName) cardName.textContent = fullName;

                const headerWelcome = document.getElementById('headerWelcomeName');
                if (headerWelcome) headerWelcome.textContent = 'Welcome, ' + (form.first_name.value || 'Customer');

                if (data.profile_image && previewImg) {
                    previewImg.src = data.profile_image + '?t=' + Date.now();
                    originalPreview = previewImg.src;
                } else if (previewImg) {
                    originalPreview = previewImg.src;
                }
                if (fileInput) fileInput.value = '';
            })
            .catch(() => {
                if (msg) {
                    msg.textContent = 'Network error. Please try again.';
                    msg.style.color = '#e74c3c';
                }
            });
    });

    const passwordForm = document.getElementById('customerPasswordForm');
    if (passwordForm) {
        passwordForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const pmsg = document.getElementById('passwordFormMsg');
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
                .then(response => response.json())
                .then(data => {
                    if (pmsg) {
                        pmsg.textContent = data.message || (data.success ? 'Password updated.' : 'Failed to update password.');
                        pmsg.style.color = data.success ? '#16a34a' : '#e74c3c';
                    }
                    if (data.success) passwordForm.reset();
                })
                .catch(() => {
                    if (pmsg) {
                        pmsg.textContent = 'Network error. Please try again.';
                        pmsg.style.color = '#e74c3c';
                    }
                });
        });
    }
})();
</script>