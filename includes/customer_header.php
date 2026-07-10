<?php
/**
 * FILE: includes/customer_header.php
 *
 * DID NOT EXIST. customer.php does `require_once 'includes/customer_header.php'`
 * unconditionally near the top of the page — with the whole includes/ folder
 * empty, that call fatal-errored on every single request, meaning the
 * customer portal could not render at all before this fix.
 *
 * Sidebar and top header intentionally mirror admin.php's markup 1:1 (same
 * .sidebar / .sidebar-header / .sidebar-nav / .nav-item / .header /
 * .header-right / .notification structure) so the customer portal looks and
 * behaves consistently with the Admin/Cashier portals, as requested.
 */
if (!isset($conn) || !($conn instanceof mysqli)) {
    require_once __DIR__ . '/../db_pharmacy.php';
}

$customer_first_name = $_SESSION['user_first_name'] ?? 'Customer';

// Small profile snapshot for the header + avatar. Falls back gracefully if
// the row can't be found so the header never fatal-errors.
$header_customer = ['profile_image' => null];
if (!empty($_SESSION['user_id'])) {
    $hstmt = $conn->prepare("SELECT profile_image FROM customers WHERE customer_id = ?");
    $hstmt->bind_param('i', $_SESSION['user_id']);
    $hstmt->execute();
    $hres = $hstmt->get_result();
    if ($hres && $hres->num_rows > 0) {
        $header_customer = $hres->fetch_assoc();
    }
    $hstmt->close();
}
$avatar_src = !empty($header_customer['profile_image'])
    ? htmlspecialchars($header_customer['profile_image'])
    : 'https://via.placeholder.com/36?text=%20';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PharmaLink — Customer Portal</title>
    <link rel="stylesheet" href="assets/theme.css">
    <link rel="stylesheet" href="customer.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>PharmaLink</h2>
            <span class="role">CUSTOMER</span>
        </div>
        <div class="sidebar-nav">
            <div class="nav-item active" data-target="home"><i class="fas fa-house"></i><span>Home</span></div>
            <div class="nav-item" data-target="products"><i class="fas fa-capsules"></i><span>Shop Products</span></div>
            <div class="nav-item" data-target="prescription"><i class="fas fa-file-prescription"></i><span>Prescriptions</span></div>
            <div class="nav-item" data-target="orders" data-order-type="online"><i class="fas fa-receipt"></i><span>My Orders</span></div>
            <div class="nav-item" data-target="profile"><i class="fas fa-user"></i><span>Profile</span></div>
        </div>
        <a href="logout.php" class="nav-item" style="margin-top:auto;border-top:1px solid rgba(255,255,255,0.08);">
            <i class="fas fa-sign-out-alt"></i><span style="margin-left:6px">Logout</span>
        </a>
    </div>

    <div class="main">
        <div class="header">
            <h3>Customer Portal</h3>
            <div class="header-right">
                <div class="notification" id="customerNotificationBell">
                    <i class="fas fa-bell"></i>
                    <div id="notification-dropdown"></div>
                </div>
                <img src="<?= $avatar_src ?>" alt="Profile" style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:1px solid #ddd;">
                <span>Welcome, <?= htmlspecialchars($customer_first_name) ?></span>
            </div>
        </div>

        <div class="content">