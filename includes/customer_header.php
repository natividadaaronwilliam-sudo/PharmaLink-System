<?php
$customer_name = htmlspecialchars($_SESSION['user_first_name'] ?? 'Customer');
$customer_full = htmlspecialchars(trim(($_SESSION['user_first_name'] ?? '') . ' ' . ($_SESSION['customer_name'] ?? '')));
if ($customer_full === '') {
    $customer_full = $customer_name;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PharmaLink — Customer Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="customer.css">
</head>
<body>

<aside class="sidebar">
    <h2>PharmaLink</h2>
    <div class="role">Customer</div>

    <div class="nav-item active" data-target="home"><i class="fas fa-home"></i> Home</div>
    <div class="nav-item" data-target="products"><i class="fas fa-pills"></i> Products</div>
    <div class="nav-item" data-target="prescription"><i class="fas fa-file-medical"></i> Prescription</div>
    <div class="nav-item" data-target="orders" data-order-type="online"><i class="fas fa-shopping-bag"></i> My Orders</div>
    <div class="nav-item" data-target="profile"><i class="fas fa-user"></i> Profile</div>

    <a href="logout.php" class="nav-item" style="margin-top:auto;"><i class="fas fa-sign-out-alt"></i> Logout</a>
</aside>

<div class="main">
    <div class="header">
        <div>
            <strong>Welcome, <?= $customer_name ?></strong>
            <div style="font-size:13px;color:#666;">Customer Portal</div>
        </div>
        <div class="header-right">
            <div class="notification" style="position:relative;">
                <i class="fas fa-bell"></i>
                <div id="notification-dropdown"></div>
            </div>
        </div>
    </div>

    <div class="content">
