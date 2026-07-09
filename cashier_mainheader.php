


<?php
// FILE: includes/main_header.php

// 1. Tiyakin na nagsimula na ang session bago gamitin ang $_SESSION
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. Kunin ang first_name mula sa session, gamit ang 'Guest' kung walang laman
// IMPORTANT: Palitan ang 'user_first_name' kung iba ang session variable name niyo
$customer_name = $_SESSION['user_first_name'] ?? 'Guest';?>

<div class="header">
    <h3>Cashier/Pharmacist Portal</h3>
    <div class="header-right">
<span>Welcome, <?php echo htmlspecialchars(string: $user_first_name); ?>!</span>    </div>
</div>