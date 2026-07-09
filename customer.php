<?php
// FILE: customer.php (FINAL FIXED VERSION)
// Ito ang entry point ng Customer Portal

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || strtolower($_SESSION['user_role'] ?? '') !== 'customer') {
    header('Location: index.php');
    exit;
}

$order_token = bin2hex(random_bytes(32));
$_SESSION['order_token'] = $order_token;
require_once 'db_pharmacy.php';

$customer_id_js = (int)$_SESSION['user_id'];

// 3. Include Header (contains <!DOCTYPE>, <head>, CSS link, Sidebar, and Main Header)
require_once 'includes/customer_header.php'; 
?>

<section id="home" class="active">
    <?php include 'includes/customer_contenthome.php'; ?>
</section>

<section id="products">
    <?php include 'includes/customer_contentproducts.php'; ?>
</section>

<section id="prescription">
    <?php include 'includes/customer_contentpx.php'; ?>
</section>

<section id="orders">
    <?php include 'includes/customer_contentorders.php'; ?>
</section>

<section id="profile">
    <?php include 'includes/customer_contentprofile.php'; ?>
</section>

<?php
// 4. Include Footer (HTML)
require_once 'includes/customer_footer.php'; 
?>

<script>
    // I-set ang global JS variable BAGO mag-load ng customer.js
    // Ang customer ID ay galing sa PHP session
    const GLOBAL_UNIQUE_TOKEN_FROM_PHP_SESSION = '<?php echo $order_token; ?>';
    const CUSTOMER_ID = <?php echo $customer_id_js; ?>;


</script>
<script src="customer.js"></script>

<?php
// 5. Close Database Connection sa dulo ng main script
if (isset($conn) && $conn instanceof mysqli) {
$conn->close();
}
?>