<?php
/**
 * FILE: includes/notification_bell.php
 *
 * SINGLE SHARED SOURCE for the notification bell markup used by all three
 * headers (Admin, Cashier, Customer) — before this, the same bell+dropdown
 * HTML was copy-pasted independently in admin.php, cashier.php, and
 * customer_header.php, which is exactly how they drifted out of sync
 * (customer's ended up with its own hardcoded yellow styling while
 * admin/cashier used the shared theme.css look).
 *
 * Usage: set $notif_mode before including this file.
 *   $notif_mode = 'staff';    // Admin / Cashier — auto-wired by assets/theme.js
 *   $notif_mode = 'customer'; // Customer portal — wired by customer.js
 *
 * Both modes render the exact same HTML shape/classes; only the element
 * IDs differ, because each side's JS (theme.js vs customer.js) talks to a
 * different backend (get_staff_notifications.php vs get_notifications.php)
 * and needs its own hook to attach to. Visual appearance (via theme.css's
 * .notif-header / .notif-item / .notif-empty classes) is identical either way.
 */
$bell_id = $notif_mode === 'customer' ? 'customerNotificationBell' : 'staffNotificationBell';
$dropdown_id = $notif_mode === 'customer' ? 'notification-dropdown' : 'staff-notification-dropdown';
?>
<div class="notification" id="<?= $bell_id ?>">
    <i class="fas fa-bell"></i>
    <div id="<?= $dropdown_id ?>" class="staff-notif-dropdown"></div>
</div>