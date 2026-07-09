<div class="profile-header" style="margin-bottom:20px;">
    <h2 style="color:#574b90;">My Profile</h2>
    <p class="subtitle">View and update your account details.</p>
</div>

<div style="max-width:560px; background:#fff; padding:24px; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.08); margin:0 auto;">
    <div style="text-align:center; margin-bottom:20px;">
        <img id="profile_avatar" src="https://cdn-icons-png.flaticon.com/512/2922/2922510.png" alt="Avatar" style="width:100px;height:100px;border-radius:50%;object-fit:cover;border:3px solid #e5e7eb;">
        <label for="profile_avatar_input" style="display:block;margin-top:8px;color:#574b90;cursor:pointer;font-size:13px;"><i class="fas fa-camera"></i> Change photo</label>
        <input type="file" id="profile_avatar_input" accept="image/*" style="display:none;">
    </div>

    <table style="width:100%; font-size:14px;" id="profileTable">
        <tr><td style="padding:8px 0;font-weight:600;width:35%;">First Name</td><td><input type="text" id="profile_first_name" disabled style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;"></td></tr>
        <tr><td style="padding:8px 0;font-weight:600;">Middle Name</td><td><input type="text" id="profile_middle_name" disabled style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;"></td></tr>
        <tr><td style="padding:8px 0;font-weight:600;">Last Name</td><td><input type="text" id="profile_last_name" disabled style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;"></td></tr>
        <tr><td style="padding:8px 0;font-weight:600;">Email</td><td><input type="email" id="profile_email" disabled style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;"></td></tr>
        <tr><td style="padding:8px 0;font-weight:600;">Phone</td><td><input type="text" id="profile_phone" disabled style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;"></td></tr>
        <tr><td style="padding:8px 0;font-weight:600;vertical-align:top;">Address</td><td><textarea id="profile_address" disabled style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;resize:vertical;"></textarea></td></tr>
        <tr><td style="padding:8px 0;font-weight:600;">Customer Type</td><td><input type="text" id="profile_customer_type" disabled style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;"></td></tr>
        <tr><td style="padding:8px 0;font-weight:600;">Loyalty Points</td><td><input type="text" id="profile_loyalty_points" disabled style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;"></td></tr>
    </table>

    <p id="profile_message" style="text-align:center;font-weight:600;margin-top:10px;"></p>

    <button type="button" id="profile_editBtn" style="width:100%;margin-top:12px;padding:10px;background:#574b90;color:#fff;border:none;border-radius:8px;cursor:pointer;">Edit Profile</button>
    <button type="button" id="profile_saveBtn" style="display:none;width:100%;margin-top:8px;padding:10px;background:#16a34a;color:#fff;border:none;border-radius:8px;cursor:pointer;">Save Changes</button>
    <button type="button" id="profile_cancelBtn" style="display:none;width:100%;margin-top:8px;padding:10px;background:#6b7280;color:#fff;border:none;border-radius:8px;cursor:pointer;">Cancel</button>

    <hr style="margin:20px 0;">
    <h3 style="font-size:15px;margin-bottom:10px;">Change Password</h3>
    <input type="password" id="profile_current_pw" placeholder="Current password" style="width:100%;padding:8px;margin-bottom:8px;border:1px solid #ddd;border-radius:6px;">
    <input type="password" id="profile_new_pw" placeholder="New password" style="width:100%;padding:8px;margin-bottom:8px;border:1px solid #ddd;border-radius:6px;">
    <input type="password" id="profile_confirm_pw" placeholder="Confirm new password" style="width:100%;padding:8px;margin-bottom:8px;border:1px solid #ddd;border-radius:6px;">
    <button type="button" id="profile_change_pw_btn" style="width:100%;padding:10px;background:#dc2626;color:#fff;border:none;border-radius:8px;cursor:pointer;">Update Password</button>
</div>

<script>
(function () {
    const fields = ['profile_first_name','profile_middle_name','profile_last_name','profile_email','profile_phone','profile_address'];
    let original = {};

    function setFields(data, disabled) {
        document.getElementById('profile_first_name').value = data.first_name || '';
        document.getElementById('profile_middle_name').value = data.middle_name || '';
        document.getElementById('profile_last_name').value = data.last_name || '';
        document.getElementById('profile_email').value = data.email || '';
        document.getElementById('profile_phone').value = data.phone_number || '';
        document.getElementById('profile_address').value = data.address || '';
        document.getElementById('profile_customer_type').value = data.customer_type || '';
        document.getElementById('profile_loyalty_points').value = data.loyalty_points || '0';
        if (data.profile_image) {
            document.getElementById('profile_avatar').src = data.profile_image + '?t=' + Date.now();
        }
        fields.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.disabled = disabled;
        });
    }

    fetch('get_customer_profile_data.php')
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                original = { ...res.data };
                setFields(res.data, true);
            }
        });

    document.getElementById('profile_editBtn')?.addEventListener('click', () => {
        setFields(original, false);
        document.getElementById('profile_editBtn').style.display = 'none';
        document.getElementById('profile_saveBtn').style.display = 'block';
        document.getElementById('profile_cancelBtn').style.display = 'block';
    });

    document.getElementById('profile_cancelBtn')?.addEventListener('click', () => {
        setFields(original, true);
        document.getElementById('profile_editBtn').style.display = 'block';
        document.getElementById('profile_saveBtn').style.display = 'none';
        document.getElementById('profile_cancelBtn').style.display = 'none';
        document.getElementById('profile_message').textContent = '';
    });

    document.getElementById('profile_saveBtn')?.addEventListener('click', () => {
        const payload = {
            first_name: document.getElementById('profile_first_name').value.trim(),
            middle_name: document.getElementById('profile_middle_name').value.trim(),
            last_name: document.getElementById('profile_last_name').value.trim(),
            email: document.getElementById('profile_email').value.trim(),
            phone_number: document.getElementById('profile_phone').value.trim(),
            address: document.getElementById('profile_address').value.trim()
        };
        const fd = new FormData();
        Object.keys(payload).forEach(k => fd.append(k, payload[k]));
        fd.append('customer_id', typeof CUSTOMER_ID !== 'undefined' ? CUSTOMER_ID : '');
        fetch('update_customer.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                const msg = document.getElementById('profile_message');
                msg.style.color = d.success ? 'green' : 'red';
                msg.textContent = d.message || (d.success ? 'Profile updated.' : 'Update failed.');
                if (d.success) {
                    original = { ...original, ...payload };
                    document.getElementById('profile_cancelBtn').click();
                }
            });
    });

    document.getElementById('profile_change_pw_btn')?.addEventListener('click', () => {
        fetch('change_password.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                current_password: document.getElementById('profile_current_pw').value,
                new_password: document.getElementById('profile_new_pw').value,
                confirm_password: document.getElementById('profile_confirm_pw').value
            })
        }).then(r => r.json()).then(d => {
            const msg = document.getElementById('profile_message');
            msg.style.color = d.success ? 'green' : 'red';
            msg.textContent = d.message || '';
        });
    });

    document.getElementById('profile_avatar_input')?.addEventListener('change', function () {
        if (!this.files[0]) return;
        const fd = new FormData();
        fd.append('profile_image', this.files[0]);
        fetch('upload_profile_picture.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (d.success) document.getElementById('profile_avatar').src = d.path + '?t=' + Date.now();
            });
    });
})();
</script>
