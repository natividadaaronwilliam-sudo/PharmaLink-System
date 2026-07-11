<?php
session_start();
if (!isset($_SESSION['user_id']) || strtolower(trim($_SESSION['user_role'] ?? '')) !== 'admin') {
    header('Location: index.php');
    exit;
}
$user_first_name = $_SESSION['user_first_name'] ?? 'Admin';
include 'db_pharmacy.php'; 

/* ============================================================
    STAFF PROFILE DATA (Para sa Profile Page) — same pattern as cashier.php
    ============================================================ */
$admin_user_id = $_SESSION['user_id'] ?? null;
$staff = [];

if ($admin_user_id && isset($conn)) {
    $sql_staff = "
        SELECT 
            si.first_name, si.middle_name, si.last_name, si.email, si.phone_number, si.address, si.profile_image,
            u.username
        FROM staff_info si
        JOIN users u ON si.user_id = u.user_id
        JOIN role r ON u.role_id = r.role_id
        WHERE si.user_id = ? AND r.role_name = 'Admin'
    ";
    $stmt = $conn->prepare($sql_staff);
    if ($stmt) {
        $stmt->bind_param("i", $admin_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $staff = $result->fetch_assoc();
        }
        $stmt->close();
    }
}

// Whatever was actually used as the account's login (username) is what should
// reflect as the account email in the profile, so it always matches the real
// logged-in staff instead of a separate, possibly-blank staff_info.email value.
$staff_display_email = !empty($staff['email']) ? $staff['email'] : ($staff['username'] ?? '');

// Sales analytics year (default: current year, from POS sales table)
$analyticsYear = (int)($_GET['year'] ?? date('Y'));
if ($analyticsYear < 2000 || $analyticsYear > 2100) {
    $analyticsYear = (int)date('Y');
}

$salesLabels = [];
$salesData = [];
$stmtSales = $conn->prepare("
    SELECT MONTH(date_created) AS month, COALESCE(SUM(total_amount), 0) AS total_sales
    FROM sales
    WHERE YEAR(date_created) = ? AND status = 'completed'
    GROUP BY MONTH(date_created)
    ORDER BY MONTH(date_created)
");
$stmtSales->bind_param('i', $analyticsYear);
$stmtSales->execute();
$monthlySalesMap = [];
$resSales = $stmtSales->get_result();
while ($row = $resSales->fetch_assoc()) {
    $monthlySalesMap[(int)$row['month']] = (float)$row['total_sales'];
}
$stmtSales->close();
for ($m = 1; $m <= 12; $m++) {
    $salesLabels[] = date('M Y', mktime(0, 0, 0, $m, 1, $analyticsYear));
    $salesData[] = $monthlySalesMap[$m] ?? 0;
}

// Top Selling Categories (POS sales_items)
$catLabels = [];
$catData = [];
$stmtCat = $conn->prepare("
    SELECT d.category, COALESCE(SUM(si.quantity), 0) AS total_qty
    FROM sales_items si
    JOIN sales s ON si.sale_id = s.sale_id
    JOIN drugs_master d ON si.drug_id = d.drug_id
    WHERE YEAR(s.date_created) = ? AND s.status = 'completed'
    GROUP BY d.category
    ORDER BY total_qty DESC
    LIMIT 8
");
$stmtCat->bind_param('i', $analyticsYear);
$stmtCat->execute();
$resCat = $stmtCat->get_result();
while ($row = $resCat->fetch_assoc()) {
    $catLabels[] = ucfirst($row['category'] ?? 'Other');
    $catData[] = (int)$row['total_qty'];
}
$stmtCat->close();

// Fetch recent 10 activities
$activityQuery = $conn->query("
    SELECT date, admin_name, action, details
    FROM activity_logs
    ORDER BY date DESC
    LIMIT 10
");
function logActivity($conn, $admin_name, $action, $details) {
    $stmt = $conn->prepare("INSERT INTO activity_logs (admin_name, action, details) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $admin_name, $action, $details);
    $stmt->execute();
    $stmt->close();
}

if(isset($_POST['add_drug'])) {
    $drug_name = $_POST['drug_name'];
    // your existing code to insert drug
    $conn->query("INSERT INTO drugs_master (drug_name, ...) VALUES ('$drug_name', ...)");
    
    // Log the activity
    logActivity($conn, $_SESSION['user_first_name'], "Add Drug", "Added new drug: $drug_name");
}

if(isset($_POST['edit_drug'])) {
    $drug_id = $_POST['drug_id'];
    $drug_name = $_POST['drug_name'];
    $conn->query("UPDATE drugs_master SET drug_name='$drug_name' WHERE id='$drug_id'");
    
    logActivity($conn, $_SESSION['user_first_name'], "Edit Drug", "Edited drug ID $drug_id to $drug_name");
}
if(isset($_GET['delete_drug'])) {
    $drug_id = $_GET['delete_drug'];
    $drug_name = $conn->query("SELECT drug_name FROM drugs_master WHERE id='$drug_id'")->fetch_assoc()['drug_name'];
    $conn->query("DELETE FROM drugs_master WHERE id='$drug_id'");
    
    logActivity($conn, $_SESSION['user_first_name'], "Delete Drug", "Deleted drug: $drug_name (ID $drug_id)");
}

// --- 1. SUPPLIER COUNT (Active + Inactive) ---
$supplier_count = 0;
$inactive_supplier_count = 0;
$sql_suppliers = "SELECT status, COUNT(supplier_id) AS cnt FROM suppliers GROUP BY status";
$result_suppliers = $conn->query($sql_suppliers);
if ($result_suppliers) {
    while ($row = $result_suppliers->fetch_assoc()) {
        if ($row['status'] === 'Active') {
            $supplier_count = (int)$row['cnt'];
        } else {
            $inactive_supplier_count += (int)$row['cnt'];
        }
    }
}
// --- 2. TOTAL SALES (This month, completed POS sales only) ---
$total_sales_amount = 0;
$sales_month_start = date('Y-m-01');
$sales_month_end = date('Y-m-t');
$stmt_month_sales = $conn->prepare("
    SELECT COALESCE(SUM(total_amount), 0) AS total_sales
    FROM sales
    WHERE DATE(date_created) BETWEEN ? AND ? AND status = 'completed'
");
$stmt_month_sales->bind_param('ss', $sales_month_start, $sales_month_end);
$stmt_month_sales->execute();
$row = $stmt_month_sales->get_result()->fetch_assoc();
$total_sales_amount = number_format((float)($row['total_sales'] ?? 0), 2);
$stmt_month_sales->close();
// --- 3. INVENTORY STATUS COUNTS (from normalized stock_status column) ---
require_once 'includes/stock_status.php';
syncAllStockStatus($conn);
$ok_stock_count = 0;
$low_stock_count = 0;
$out_of_stock_count = 0;
$sql_stock_status = "SELECT stock_status, COUNT(*) AS cnt FROM drugs_master WHERE is_active = 1 GROUP BY stock_status";
$result_stock_status = $conn->query($sql_stock_status);
if ($result_stock_status) {
    while ($row = $result_stock_status->fetch_assoc()) {
        switch ($row['stock_status']) {
            case 'low': $low_stock_count = (int)$row['cnt']; break;
            case 'out': $out_of_stock_count = (int)$row['cnt']; break;
            default: $ok_stock_count = (int)$row['cnt']; break;
        }
    }
}

// --- 4. TOTAL STAFF USERS (From 'users' table, excluding customers) ---
$staff_count = 0;
// We join 'users' with 'role' to ensure we only count non-customer roles.
$sql_staff_users = "
    SELECT COUNT(T1.user_id) AS total_staff
    FROM users T1
    JOIN role T2 ON T1.role_id = T2.role_id
    WHERE T2.role_name != 'Customer' AND T2.role_name != 'customer'
";
$result_staff_users = $conn->query($sql_staff_users);
if ($result_staff_users && $result_staff_users->num_rows > 0) {
    $staff_count = $result_staff_users->fetch_assoc()['total_staff'];
}

// --- 5. REGISTERED CUSTOMER COUNT (From 'customers' table) ---
$customer_count = 0;
$sql_customers = "SELECT COUNT(customer_id) AS total_customers FROM customers";
$result_customers = $conn->query($sql_customers);
if ($result_customers && $result_customers->num_rows > 0) {
    $customer_count = $result_customers->fetch_assoc()['total_customers'];
}

// --- 6. GET ALL STAFF FOR TABLE ---
function getStaff(object $conn) : array {
    $staff = [];
    $sql = "SELECT u.user_id, r.role_name, 
                   s.first_name, s.middle_name, s.last_name,
                   -- IDAGDAG ANG EMAIL AT PHONE NUMBER
                   s.email, s.phone_number
            FROM users u
            JOIN staff_info s ON u.user_id = s.user_id
            JOIN role r ON u.role_id = r.role_id
            WHERE u.is_active = 1 AND r.role_name != 'Customer' AND r.role_name != 'customer'
            ORDER BY u.user_id DESC";
            
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $staff[] = $row;
        }
    }
    return $staff;
}

// --- 7. GET ALL CUSTOMERS FOR TABLE ---
function getCustomers(object $conn) : array {
    $customers = [];
    
    // I-adjust ang query para gumamit ng 'customers' table at 'customer_id'
    $sql = "SELECT customer_id, first_name, middle_name, last_name,
                   email, phone_number, customer_type, loyalty_points
            FROM customers
            -- Assuming lahat ng customer ay active. Kung may 'is_active' column, idagdag ang WHERE clause.
            ORDER BY customer_id DESC";
            
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $customers[] = $row;
        }
    }
    return $customers;
}

// Kunin ang data na gagamitin sa tables
$allStaff = getStaff($conn);
$allCustomers = getCustomers($conn);

$conn->close();

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>PharmaLink System - Admin Portal</title>
    <link rel="stylesheet" href="assets/theme.css">
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <style>
        /* ==== Profile page styles (reused from customer.css so Admin/Cashier/Customer profiles look consistent) ==== */

/* =======================================
   9. PROFILE PAGE
   ======================================= */
.customer-profile-page {
    width: 100%;
    max-width: 1180px;
    margin: 0 auto;
    padding: 18px 0 36px;
}

.customer-profile-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    margin-bottom: 18px;
}

.customer-profile-header h2 {
    margin: 0;
    color: var(--ph-blue-900, #1e3a8a);
    font-size: 1.7rem;
    font-weight: 700;
    line-height: 1.2;
}

.customer-profile-header p {
    margin: 6px 0 0;
    color: #6b7280;
    font-size: 0.95rem;
}

.customer-profile-layout {
    display: grid;
    grid-template-columns: minmax(240px, 300px) minmax(420px, 1fr) minmax(260px, 300px);
    gap: 20px;
    align-items: start;
}

.profile-summary-card,
.profile-details-card,
.profile-password-card {
    background: var(--ph-surface, #fff);
    border: 1px solid var(--ph-border-soft, #eef0f6);
    border-radius: var(--ph-radius-lg, 18px);
    box-shadow: var(--ph-shadow-sm, 0 2px 8px rgba(23, 26, 43, 0.07));
}

.profile-summary-card {
    padding: 28px 22px;
    text-align: center;

}

.profile-avatar-wrap {
    position: relative;
    width: 112px;
    height: 112px;
    margin: 0 auto 16px;
}

.profile-avatar-wrap img {
    width: 112px;
    height: 112px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #e5e7eb;
    background: #f8fafc;
}

.profile-photo-btn {
    position: absolute;
    right: 0;
    bottom: 2px;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #1e3a8a;
    color: #fff;
    cursor: pointer;
    box-shadow: 0 4px 10px rgba(30, 58, 138, 0.28);
}

#profile_image_input,
#profile_avatar_input {
    display: none;
}

.profile-summary-card h3 {
    margin: 0;
    color: #1e3a8a;
    font-size: 1.35rem;
    line-height: 1.3;
    overflow-wrap: anywhere;
}

.profile-username {
    margin: 6px 0 0;
    color: #6b7280;
    font-size: 0.9rem;
    overflow-wrap: anywhere;
}

.profile-pill-row {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 8px;
    margin-top: 16px;
}

.profile-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 11px;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 700;
    white-space: nowrap;
}

.profile-pill-blue {
    background: #eff6ff;
    color: #1d4ed8;
}

.profile-pill-gold {
    background: #fffbeb;
    color: #b45309;
}

.profile-details-card {
    padding: 24px;
    min-width: 0;
}

.profile-card-head {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    padding-bottom: 14px;
    margin-bottom: 18px;
    border-bottom: 1px solid #e5e7eb;
}

.profile-card-head h3,
.profile-password-card h3 {
    margin: 0;
    color: #1e3a8a;
    font-size: 1.08rem;
}

.profile-card-head p,
.profile-password-card p {
    margin: 5px 0 0;
    color: #6b7280;
    font-size: 0.86rem;
    line-height: 1.45;
}

.profile-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px 16px;
}

.profile-form-grid label {
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-width: 0;
}

.profile-form-grid label span {
    color: #374151;
    font-size: 0.82rem;
    font-weight: 700;
}

.profile-form-grid input,
.profile-form-grid textarea,
.profile-password-card input {
    width: 100%;
    min-width: 0;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    padding: 10px 12px;
    font-size: 0.94rem;
    background: #fff;
}

.profile-form-grid textarea {
    min-height: 82px;
    resize: vertical;
}

.profile-form-grid input:disabled,
.profile-form-grid textarea:disabled {
    background: #f9fafb;
    color: #6b7280;
    opacity: 1;
}

.profile-field-wide {
    grid-column: 1 / -1;
}

.profile-form-msg {
    min-height: 20px;
    margin: 12px 0 0;
    text-align: center;
    font-size: 0.9rem;
    font-weight: 700;
}

.profile-action-row {
    display: flex;
    gap: 10px;
    margin-top: 14px;
}

.profile-btn {
    border: none;
    border-radius: 9px;
    padding: 11px 16px;
    color: #fff;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 42px;
}

.profile-btn[hidden] {
    display: none;
}

.profile-btn-primary { background: #1e3a8a; }
.profile-btn-success { background: #10b981; flex: 1; }
.profile-btn-muted { background: #6b7280; min-width: 112px; }
.profile-btn-danger { background: #dc2626; width: 100%; }

.profile-password-card {
    padding: 24px;
    background: #fff7f7;
    border-color: #fecaca;
}

.profile-password-card h3 {
    color: #b91c1c;
    display: flex;
    align-items: center;
    gap: 8px;
}

.profile-password-card p {
    color: #7f1d1d;
    margin-bottom: 16px;
}

.profile-password-card form {
    display: grid;
    gap: 12px;
}

@media (max-width: 1180px) {
    .customer-profile-layout {
        grid-template-columns: minmax(240px, 300px) minmax(420px, 1fr);
    }

    .profile-password-card {
        grid-column: 2;
    }
}

@media (max-width: 900px) {
    .customer-profile-layout {
        grid-template-columns: 1fr;
    }

    .profile-password-card {
        grid-column: auto;
    }
}

@media (max-width: 620px) {
    .customer-profile-page {
        padding-top: 8px;
    }

    .profile-details-card,
    .profile-password-card,
    .profile-summary-card {
        padding: 18px;
    }

    .profile-form-grid {
        grid-template-columns: 1fr;
    }

    .profile-action-row {
        flex-direction: column;
    }

    .profile-btn-muted {
        width: 100%;
    }
}

        /* Page-specific tweaks only — shared cards, tables, modals, buttons,
           badges and toasts now all live in assets/theme.css */
        .save, .update{ width:100%; margin-top:10px; padding:12px 15px !important; font-size:1.05rem; }
        #inventory .controls{ margin-bottom:30px; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>PharmaLink</h2>
            <span class="role">ADMIN</span>
        </div>
        <div class="sidebar-nav">
            <div class="nav-item active" data-target="dashboard"><i class="fas fa-home"></i><span>Dashboard</span></div>
            <div class="nav-item" data-target="inventory"><i class="fas fa-boxes"></i><span>Inventory</span></div>
            <div class="nav-item" data-target="supplier-section"><i class="fas fa-truck"></i><span>Supplier</span></div>
            <div class="nav-item" data-target="reports"><i class="fas fa-chart-line"></i><span>Reports</span></div>
            <div class="nav-item" data-target="user-management"><i class="fas fa-users"></i><span>User Management</span></div>
            <div class="nav-item" data-target="profile"><i class="fas fa-user"></i><span>Profile</span></div>
        </div>
        <a href="logout.php" class="nav-item" style="margin-top:auto;border-top:1px solid rgba(255,255,255,0.04);">
            <i class="fas fa-sign-out-alt"></i><span style="margin-left:6px">Logout</span>
        </a>
    </div>

    <div class="main">
<div class="header">
    <h3>Admin Portal</h3>
    <div class="header-right">
        <?php $notif_mode = 'staff'; require 'includes/notification_bell.php'; ?>
<span id="headerWelcomeName">Welcome, <?php echo htmlspecialchars($user_first_name); ?></span>    </div>
</div>

        <div class="content">

            <section id="dashboard">
                <div style="display:flex; flex-wrap:wrap; justify-content:space-between; align-items:flex-start; gap:12px; margin-bottom:10px;">
                    <div>
                        <h2 style="color:#1e3a8a; margin-bottom:6px;">Admin Dashboard Overview</h2>
                        <p style="color:#555; margin:0;">Welcome to your administrative dashboard. Monitor sales, inventory, suppliers, and activity logs in real time.</p>
                    </div>
                    <div id="dashboard-datetime" style="background:#fff; padding:10px 16px; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.06); text-align:right; min-width:220px;">
                        <div style="font-size:13px; color:#6b7280;">Server Date &amp; Time</div>
                        <div id="live-clock" style="font-size:15px; font-weight:600; color:#1e3a8a;"><?= date('l, M j, Y — g:i:s A') ?></div>
                    </div>
                </div>

                <div style="display:flex; flex-wrap:wrap; gap:10px; align-items:center; margin:18px 0 22px;">
                    <label style="font-weight:600; color:#374151;">Sales period:</label>
                    <select id="sales-period-filter" style="padding:8px 12px; border-radius:6px; border:1px solid #d1d5db;">
                        <option value="today">Today</option>
                        <option value="month" selected>This Month</option>
                        <option value="year">This Year</option>
                    </select>
                    <span id="sales-period-label" style="color:#6b7280; font-size:13px;">Showing sales for this month</span>
                </div>

 <div class="dashboard-cards" style="display:flex; flex-wrap:wrap; gap:20px; margin-bottom:30px;">
    <div class="dash-card card" data-nav="user-management" data-tooltip="Staff accounts (Admin &amp; Cashier). The number below includes registered customers. Click to open User Management." role="button" tabindex="0" style="flex:1; min-width:200px; padding:15px; border-radius:8px; box-shadow:0 2px 10px rgba(0,0,0,0.05); background:#fff; transition: transform 0.2s;">
        <span class="dash-card-tip" tabindex="0"><i class="fas fa-circle-info"></i><span class="dash-card-tip-text">Staff accounts (Admin &amp; Cashier). The number below includes registered customers. Click to open User Management.</span></span>
        <i class="fas fa-user-tie" style="color:#2563eb; font-size:24px;"></i>
        <h3 style="margin-top:10px;">Total Staff Users</h3>
        <p class="value" id="card-staff-count" style="color:#2563eb; font-size:1.5em;"><?php echo $staff_count; ?></p>
        <small style="color: #666; display:block; margin-top:5px;">+ <span id="card-customer-count"><?php echo $customer_count; ?></span> Registered Customers</small>
        <span class="dash-card-hint">Click to manage users →</span>
    </div>

    <div class="dash-card card" data-nav="sales-analytics" data-tooltip="Total peso sales from completed POS transactions for the selected period (Today / Month / Year). Click to jump to Sales Analytics." role="button" tabindex="0" style="flex:1; min-width:200px; padding:15px; border-radius:8px; box-shadow:0 2px 10px rgba(0,0,0,0.05); background:#fff; transition: transform 0.2s;">
        <span class="dash-card-tip" tabindex="0"><i class="fas fa-circle-info"></i><span class="dash-card-tip-text">Total peso sales from completed POS transactions for the selected period (Today / Month / Year). Click to jump to Sales Analytics.</span></span>
        <i class="fas fa-money-bill-wave" style="color:#16a34a; font-size:24px;"></i>
        <h3 style="margin-top:10px;">Total Sales <span id="sales-card-period">(This Month)</span></h3>
        <p class="value" style="color:#16a34a; font-size:1.5em;">₱<span id="card-total-sales"><?php echo $total_sales_amount; ?></span></p>
        <small style="color: #666; display:block; margin-top:5px;">Completed cashier transactions only</small>
        <span class="dash-card-hint">Click to view charts →</span>
    </div>

    <div class="dash-card card" data-nav="supplier-section" data-tooltip="Suppliers currently marked Active. Inactive suppliers were manually deactivated — open Suppliers to see the reason." role="button" tabindex="0" style="flex:1; min-width:200px; padding:15px; border-radius:8px; box-shadow:0 2px 10px rgba(0,0,0,0.05); background:#fff; transition: transform 0.2s;">
        <span class="dash-card-tip" tabindex="0"><i class="fas fa-circle-info"></i><span class="dash-card-tip-text">Suppliers currently marked Active. Inactive suppliers were manually deactivated — open Suppliers to see the reason.</span></span>
        <i class="fas fa-truck" style="color:#8b5cf6; font-size:24px;"></i>
        <h3 style="margin-top:10px;">Active Suppliers</h3>
        <p class="value" id="card-supplier-count" style="color:#8b5cf6; font-size:1.5em;"><?php echo $supplier_count; ?></p>
        <small style="color: #666; display:block; margin-top:5px;"><span id="card-inactive-suppliers"><?php echo $inactive_supplier_count; ?></span> inactive</small>
        <span class="dash-card-hint">Click to manage suppliers →</span>
    </div>

    <div class="dash-card card" data-nav="inventory" data-inv-filter="low" data-tooltip="Active drugs with stock above zero but at or below the minimum level. Click to open Inventory filtered to low-stock items." role="button" tabindex="0" style="flex:1; min-width:200px; padding:15px; border-radius:8px; box-shadow:0 2px 10px rgba(0,0,0,0.05); background:#fff; transition: transform 0.2s;">
        <span class="dash-card-tip" tabindex="0"><i class="fas fa-circle-info"></i><span class="dash-card-tip-text">Active drugs with stock above zero but at or below the minimum level. Click to open Inventory filtered to low-stock items.</span></span>
        <i class="fas fa-exclamation-triangle" style="color:#f59e0b; font-size:24px;"></i>
        <h3 style="margin-top:10px;">Low Stock Alert</h3>
        <p class="value" id="card-low-stock" style="color:#f59e0b; font-size:1.5em;"><?php echo $low_stock_count; ?></p>
        <small style="color: #666; display:block; margin-top:5px;">Needs restock soon</small>
        <span class="dash-card-hint">Click to view low-stock items →</span>
    </div>

    <div class="dash-card card" data-nav="inventory" data-inv-filter="out" data-tooltip="Active drugs with zero total stock across all lots. Click to open Inventory filtered to out-of-stock items." role="button" tabindex="0" style="flex:1; min-width:200px; padding:15px; border-radius:8px; box-shadow:0 2px 10px rgba(0,0,0,0.05); background:#fff; transition: transform 0.2s;">
        <span class="dash-card-tip" tabindex="0"><i class="fas fa-circle-info"></i><span class="dash-card-tip-text">Active drugs with zero total stock across all lots. Click to open Inventory filtered to out-of-stock items.</span></span>
        <i class="fas fa-times-circle" style="color:#ef4444; font-size:24px;"></i>
        <h3 style="margin-top:10px;">Out of Stock</h3>
        <p class="value" id="card-out-of-stock" style="color:#ef4444; font-size:1.5em;"><?php echo $out_of_stock_count; ?></p>
        <small style="color: #666; display:block; margin-top:5px;">Zero stock level</small>
        <span class="dash-card-hint">Click to view out-of-stock items →</span>
    </div>
</div>

<!-- Quick Actions -->
<div style="margin-bottom:28px; background:#fff; padding:16px 20px; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.05);">
    <h3 style="color:#1e3a8a; margin:0 0 12px 0; font-size:16px;">Quick Actions</h3>
    <div style="display:flex; flex-wrap:wrap; gap:10px;">
        <button type="button" class="quick-action-btn" title="Go to Inventory" onclick="document.querySelector('.nav-item[data-target=inventory]')?.click()" style="padding:8px 14px; border:none; border-radius:6px; background:#2563eb; color:#fff; cursor:pointer;"><i class="fas fa-boxes"></i> Inventory</button>
        <button type="button" class="quick-action-btn" title="Manage suppliers" onclick="document.querySelector('.nav-item[data-target=supplier-section]')?.click()" style="padding:8px 14px; border:none; border-radius:6px; background:#8b5cf6; color:#fff; cursor:pointer;"><i class="fas fa-truck"></i> Suppliers</button>
        <button type="button" class="quick-action-btn" title="View sales forecast" onclick="document.querySelector('.nav-item[data-target=reports]')?.click()" style="padding:8px 14px; border:none; border-radius:6px; background:#16a34a; color:#fff; cursor:pointer;"><i class="fas fa-chart-line"></i> Forecast</button>
        <button type="button" class="quick-action-btn" title="Manage users" onclick="document.querySelector('.nav-item[data-target=user-management]')?.click()" style="padding:8px 14px; border:none; border-radius:6px; background:#f59e0b; color:#fff; cursor:pointer;"><i class="fas fa-users"></i> Users</button>
    </div>
</div>

<!-- Fast / Slow Moving Items -->
<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:20px; margin-bottom:30px;">
    <div style="background:#fff; border-radius:10px; padding:16px; box-shadow:0 2px 8px rgba(0,0,0,0.05);">
        <h4 style="color:#16a34a; margin:0 0 10px;" title="Top sellers in the last 30 days"><i class="fas fa-fire"></i> Fast-Moving Items</h4>
        <ul id="fast-moving-list" style="margin:0; padding-left:18px; color:#374151; font-size:14px;"><li>Loading...</li></ul>
    </div>
    <div style="background:#fff; border-radius:10px; padding:16px; box-shadow:0 2px 8px rgba(0,0,0,0.05);">
        <h4 style="color:#f59e0b; margin:0 0 10px;" title="Items with stock but very few sales in 90 days"><i class="fas fa-hourglass-half"></i> Slow-Moving Items</h4>
        <ul id="slow-moving-list" style="margin:0; padding-left:18px; color:#374151; font-size:14px;"><li>Loading...</li></ul>
    </div>
</div>

<div style="margin-top:20px;" id="sales-analytics-section">
    <div style="display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:10px; margin-bottom:16px;">
        <h3 style="color:#1e3a8a;margin-bottom:0;">Sales Analytics</h3>
        <div>
            <label for="analytics-year" style="font-weight:600; color:#374151; margin-right:8px;">Year:</label>
            <select id="analytics-year" style="padding:6px 10px; border-radius:6px; border:1px solid #d1d5db;">
                <?php for ($y = (int)date('Y'); $y >= (int)date('Y') - 4; $y--): ?>
                <option value="<?= $y ?>" <?= $y === $analyticsYear ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
    </div>
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap:20px;">
        <div style="background:white; border-radius:10px; padding:15px; box-shadow:0 2px 6px rgba(0,0,0,0.1); box-sizing:border-box;">
            <h4 style="color:#333; margin-bottom:10px; font-size:16px;">Monthly Sales Trend</h4>
            <div style="position:relative; width:100%; height:250px;">
                <canvas id="monthlySalesChart"></canvas>
            </div>
        </div>
        <div style="background:white; border-radius:10px; padding:15px; box-shadow:0 2px 6px rgba(0,0,0,0.1); display:flex; flex-direction:column; align-items:center; justify-content:center; box-sizing:border-box;">
            <h4 style="color:#333; margin-bottom:10px; font-size:16px;">Top Selling Categories</h4>
            <div style="position:relative; width:100%; max-width:350px; height:300px;">
                <canvas id="topCategoriesChart"></canvas>
            </div>
        </div>
    </div>
</div>

<div style="margin-top:50px;">
    <h3 style="color:#1e3a8a; margin-bottom:15px; font-weight:600;">📝 Recent Activity Logs</h3>
    <div class="table-wrap" style="overflow-x:auto; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.05); background:#fff;">
        <table style="width:100%; border-collapse:collapse; min-width:600px;">
            <thead>
                <tr style="background:#f3f4f6; color:#1f2937; text-align:left; font-weight:600;">
                    <th style="padding:12px 15px; border-bottom:2px solid #e5e7eb;">Date</th>
                    <th style="padding:12px 15px; border-bottom:2px solid #e5e7eb;">Admin</th>
                    <th style="padding:12px 15px; border-bottom:2px solid #e5e7eb;">Action</th>
                    <th style="padding:12px 15px; border-bottom:2px solid #e5e7eb;">Details</th>
                </tr>
            </thead>
            <tbody id="activity-logs-body">
                <?php if($activityQuery->num_rows > 0): ?>
                    <?php while($row = $activityQuery->fetch_assoc()): ?>
                        <tr style="transition: background 0.3s; cursor:default;">
                            <td style="padding:12px 15px; border-bottom:1px solid #f1f5f9; color:#374151;"><?= date('Y-m-d H:i', strtotime($row['date'])) ?></td>
                            <td style="padding:12px 15px; border-bottom:1px solid #f1f5f9; color:#374151;"><?= htmlspecialchars($row['admin_name']) ?></td>
                            <td style="padding:12px 15px; border-bottom:1px solid #f1f5f9; color:#374151;"><?= htmlspecialchars($row['action']) ?></td>
                            <td style="padding:12px 15px; border-bottom:1px solid #f1f5f9; color:#374151;"><?= htmlspecialchars($row['details']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="padding:15px; text-align:center; color:#6b7280;">No recent activities.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


            </section>

            <section id="inventory" style="display:none;">
                <div class="dashboard-header">
                    <h2 style="color:#1e3a8a;margin-bottom:6px">Inventory Management</h2>
                    <p class="subtitle" style="color:#666">Manage drug master list, stock lots, suppliers and monitor expiring/low stock items.</p>
                </div>

                <div class="dashboard-cards">
                    <div class="card inv-summary-card" data-filter="all" title="Click to show all non-expired lots" style="cursor:pointer;"><i class="fas fa-boxes" style="color:#2563eb;"></i><h3 id="card-total">Total Items</h3><p class="value" id="total-items" style="color:#2563eb;">0</p></div>
                    <div class="card inv-summary-card" data-filter="low" title="Click to filter low-stock lots" style="cursor:pointer;"><i class="fas fa-exclamation-triangle" style="color:#f59e0b;"></i><h3 id="card-low">Low Stock</h3><p class="value" id="low-stock" style="color:#f59e0b;">0</p></div>
                    <div class="card inv-summary-card" data-filter="out" title="Click to filter out-of-stock lots" style="cursor:pointer;"><i class="fas fa-times-circle" style="color:#ef4444;"></i><h3>Out of Stock</h3><p class="value" id="out-stock" style="color:#ef4444;">0</p></div>
                    <div class="card inv-summary-card" data-filter="expiring" title="Click to filter lots expiring within 90 days" style="cursor:pointer;"><i class="fas fa-calendar-alt" style="color:#f97316;"></i><h3 id="card-exp">Expiring Soon</h3><p class="value" id="expiring-soon" style="color:#f97316;">0</p></div>
                </div>

                <div class="controls" style="margin-bottom:14px;">
                    <div class="left">
                        <button class="add-btn secondary" id="addDrugMasterBtn"><i class="fa fa-flask"></i> Define New Drug</button>
                        <button class="add-btn" id="addItemBtn" style="background:#16a34a;"><i class="fa fa-box-open"></i> Add Stock Lot</button>
                    </div>
                </div>

                <h3 style="color:#1e3a8a;margin-bottom:8px">💊 Standard Drug Definitions (Master List)</h3>
                <div class="table-wrap" style="margin-bottom:20px;">

                    <div style="display:flex; align-items:center; gap:15px; flex-wrap:wrap; margin-bottom:10px;">
                        <div class="filter" style="margin-bottom:0;">
                            <label for="drugMasterSearch">Search:</label>
                            <input type="text" id="drugMasterSearch"
                                placeholder="Search Generic, Brand, Dosage, Form, Category..."
                                style="padding: 8px; border-radius: 4px; border: 1px solid #ccc; width: 260px;">
                        </div>
                        <div class="filter" style="margin-bottom:0;">
                            <label for="drugMasterFilter">Show:</label>
                            <select id="drugMasterFilter" style="padding: 8px; border-radius: 4px; border: 1px solid #ccc;">
                                <option value="active">Active (Default)</option>
                                <option value="archived">Archived Only</option>
                                <option value="all">All</option>
                            </select>
                        </div>
                    </div>
                    <table id="drugMasterTable">
                        <thead>
                            <tr>
                                <th>Generic Name</th>
                                <th>Brand Name</th>
                                <th>Dosage</th>
                                <th>Form</th>
                                <th>Category</th>
                                <th>Min Stock</th>
                                <th>Status</th>
                                <th style="width:12%">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="drugMasterBody"></tbody>
                    </table>
                </div>

                <h3 style="color:#1e3a8a;margin:20px 0 8px;">🧪 Current Inventory</h3>
                
                <div class="table-wrap">

                    <div style="display:flex; align-items:center; gap:15px; flex-wrap:wrap; margin-bottom:10px;">
                        <div class="filter" style="margin-bottom:0;">
                            <label for="inventorySearch">Search:</label>
                            <input type="text" id="inventorySearch"
                                placeholder="Search Generic, Brand, Dosage, Form, Category..."
                                style="padding: 8px; border-radius: 4px; border: 1px solid #ccc; width: 260px;">
                        </div>
                        <div class="filter" style="margin-bottom:0;">
                            <label for="lotStatusFilter">Record Status:</label>
                            <select id="lotStatusFilter" class="filter-select" style="padding: 8px; border-radius: 4px; border: 1px solid #ccc;">
                                <option value="active" selected>Active (Default)</option>
                                <option value="archived">Archived</option>
                                <option value="all">All</option>
                            </select>
                        </div>
                    </div>
                    <table id="inventoryTable">
                        <thead>
                            <tr>
                                <th>Generic Name</th>
                                <th>Brand Name</th>
                                <th>Dosage</th>
                                <th>Form</th>
                                <th>Category</th>
                                <th>Lot Number</th>
                                <th>Expiration Date</th>
                                <th>Current Stock</th>
                                <th>Minimum Stock</th>
                                <th>Price</th>
                                <th>Supplier</th>
                                <th>Status</th>
                                <th style="width:12%">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="inventoryBody"></tbody>
                    </table>
                </div>
            </section>

<section id="user-management" style="display:none; padding: 30px;">
    <h2 style="color: #1e3a8a; margin-bottom: 25px;">👤 User Management</h2>

    <!-- Staff Accounts -->
<div style="margin-bottom: 50px; font-family: 'Poppins', sans-serif;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap:wrap; gap:12px;">
        <h3 style="font-size: 22px; font-weight: 600; color: #1f2937; margin:0;">Staff Accounts</h3>
        <button class="add-btn add-staff-btn-trigger" style="background: #2563eb; color: #fff; padding: 10px 18px; border: none; border-radius: 8px; cursor: pointer; font-weight:600; transition: 0.3s;">
            <i class="fas fa-plus" style="margin-right:6px;"></i>Add Staff User
        </button>
    </div>

    <div class="toolbar-bar" style="display:flex; align-items:center; gap:14px; flex-wrap:wrap; background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px; padding:10px 14px; margin-bottom:20px;">
        <div style="position:relative; flex:1; min-width:220px;">
            <i class="fas fa-search" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#9ca3af; font-size:13px;"></i>
            <input type="text" id="staff-search" placeholder="Search staff by name or email..." autocomplete="off"
                   style="width:100%; box-sizing:border-box; height:38px; padding: 0 12px 0 32px; border: 1px solid #d1d5db; border-radius: 6px; font-size:14px;">
        </div>
        <div style="display:flex; align-items:center; gap:8px;">
            <label for="staff-role-filter" style="font-size:14px; color:#4b5563; font-weight:600; white-space:nowrap;">Role:</label>
            <select id="staff-role-filter" style="height:38px; box-sizing:border-box; padding: 0 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size:14px;">
                <option value="All">All Roles</option>
                <option value="Admin">Admin</option>
                <option value="Cashier/Pharmacist">Cashier/Pharmacist</option>
            </select>
        </div>
    </div>

    <div class="table-wrap" style="overflow-x:auto; background:#fff; border-radius:8px; box-shadow:0 2px 6px rgba(0,0,0,0.1);">
        <table style="width: 100%; border-collapse: collapse; min-width: 600px;">
            <thead style="background: #f3f4f6; text-align: left;">
                <tr>
                    <th style="padding: 12px 16px; width: 25%;">Name</th>
                    <th style="padding: 12px 16px; width: 15%;">Role</th>
                    <th style="padding: 12px 16px; width: 25%;">Email</th> 
                    <th style="padding: 12px 16px; width: 15%;">Phone</th>
                    <th style="padding: 12px 16px; width: 20%;">Actions</th>
                </tr>
            </thead>
            <tbody id="staff-table-body">
                <?php foreach ($allStaff as $staffRow): 
                    $fullName = trim($staffRow['first_name'] . ' ' . $staffRow['middle_name'] . ' ' . $staffRow['last_name']);
                ?>
                <tr data-id="<?= $staffRow['user_id'] ?>" data-role="<?= $staffRow['role_name'] ?>" style="border-top:1px solid #e5e7eb;">
                    <td style="padding: 12px 16px;"><?= htmlspecialchars($fullName) ?></td>
                    <td style="padding: 12px 16px;"><?= htmlspecialchars($staffRow['role_name']) ?></td>
                    <td style="padding: 12px 16px;"><?= htmlspecialchars($staffRow['email']) ?></td> 
                    <td style="padding: 12px 16px;"><?= htmlspecialchars($staffRow['phone_number']) ?></td>
                    <td class="action-btn-group" style="padding: 12px 16px; display:flex; gap:8px;">
                        <button class="add-btn edit-staff-btn" data-id="<?= $staffRow['user_id'] ?>" style="background: #1e90ff; color:#fff; padding: 6px 12px; border:none; border-radius:4px; cursor:pointer;">Edit</button>
                        <button class="add-btn delete-staff-btn" data-id="<?= $staffRow['user_id'] ?>" style="background: #e63946; color:#fff; padding: 6px 12px; border:none; border-radius:4px; cursor:pointer;">Deactivate</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="ph-pagination" id="staff-pagination" style="display:flex; justify-content:flex-end; align-items:center; gap:10px; margin-top:12px; font-size:14px; color:#4b5563;">
        <button id="staff-prev-btn" style="padding:6px 12px; border:1px solid #ccc; border-radius:6px; background:#fff; cursor:pointer;">&lt; Prev</button>
        <span id="staff-page-info">Page 1</span>
        <button id="staff-next-btn" style="padding:6px 12px; border:1px solid #ccc; border-radius:6px; background:#fff; cursor:pointer;">Next &gt;</button>
    </div>
</div>
<div style="margin-top: 50px; font-family: 'Poppins', sans-serif;">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap:wrap; gap:12px;">
        <h3 style="font-size: 22px; font-weight: 600; color: #1f2937; margin:0;">Customer Accounts</h3>
        <button class="add-btn" style="background: #16a34a; color: #fff; padding: 10px 18px; border: none; border-radius: 8px; cursor: pointer; font-weight:600; transition: 0.3s;">
            <i class="fas fa-plus" style="margin-right:6px;"></i>Add Customer
        </button>
    </div>

    <div class="toolbar-bar" style="display:flex; align-items:center; gap:14px; flex-wrap:wrap; background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px; padding:10px 14px; margin-bottom:20px;">
        <div style="position:relative; flex:1; min-width:220px;">
            <i class="fas fa-search" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#9ca3af; font-size:13px;"></i>
            <input type="text" id="customer-search" placeholder="Search customers by name or email..." autocomplete="off"
                   style="width:100%; box-sizing:border-box; height:38px; padding: 0 12px 0 32px; border: 1px solid #d1d5db; border-radius: 6px; font-size:14px;">
        </div>
    </div>

    <!-- Table -->
    <div class="table-wrap" style="overflow-x:auto; background:#fff; border-radius:8px; box-shadow:0 2px 6px rgba(0,0,0,0.1);">
        <table style="width: 100%; border-collapse: collapse; min-width: 400px;">
            <thead style="background: #f3f4f6; text-align: left;">
                <tr>
                    <th style="padding: 12px 16px; width: 25%;">Name</th>
                    <th style="padding: 12px 16px; width: 25%;">Email</th>
                    <th style="padding: 12px 16px; width: 15%;">Phone</th>
                    <th style="padding: 12px 16px; width: 15%;">Points</th>
                    <th style="padding: 12px 16px; width: 20%;">Actions</th>
                </tr>
            </thead>
<tbody id="customer-table-body">
                <?php foreach ($allCustomers as $customer): 
                    $fullName = trim($customer['first_name'] . ' ' . $customer['middle_name'] . ' ' . $customer['last_name']);
                ?>
                <tr data-id="<?= $customer['customer_id'] ?>" style="border-top:1px solid #e5e7eb;">
                    <td style="padding: 12px 16px;"><?= htmlspecialchars($fullName) ?></td>
                    <td style="padding: 12px 16px;"><?= htmlspecialchars($customer['email']) ?></td>
                    <td style="padding: 12px 16px;"><?= htmlspecialchars($customer['phone_number']) ?></td>
                    <td style="padding: 12px 16px;"><?= htmlspecialchars($customer['loyalty_points']) ?></td>
                    <td class="action-btn-group" style="padding: 12px 16px; display:flex; gap:8px;">
                        <button class="edit-customer-btn" data-id="<?= $customer['customer_id'] ?>" style="background: #1e90ff; color:#fff; padding: 6px 12px; border:none; border-radius:4px; cursor:pointer;">Edit</button>
                        <button class="delete-customer-btn" data-id="<?= $customer['customer_id'] ?>" style="background: #e63946; color:#fff; padding: 6px 12px; border:none; border-radius:4px; cursor:pointer;">Deactivate</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="ph-pagination" id="customer-pagination" style="display:flex; justify-content:flex-end; align-items:center; gap:10px; margin-top:12px; font-size:14px; color:#4b5563;">
        <button id="customer-prev-btn" style="padding:6px 12px; border:1px solid #ccc; border-radius:6px; background:#fff; cursor:pointer;">&lt; Prev</button>
        <span id="customer-page-info">Page 1</span>
        <button id="customer-next-btn" style="padding:6px 12px; border:1px solid #ccc; border-radius:6px; background:#fff; cursor:pointer;">Next &gt;</button>
    </div>
</div>
            </section>

            
<section id="supplier-section" style="display:none; padding:0px;">
    <h2 style="color:#1e3a8a; margin-bottom: 10px;">🚚 Supplier Management</h2>
    <p style="color:#666; margin-bottom: 25px;">Manage your suppliers and the medicines they supply.</p>

    <div class="controls" style="
        margin-bottom: 20px; 
        display: flex; 
        justify-content: space-between; /* Itutulak ang kaliwa at kanang grupo */
        align-items: center;
        gap: 20px; /* Dagdag space para sa pagitan ng buong grupo at button */
    ">
        
        <div class="control-group-left" style="display: flex; align-items: center; gap: 15px;">
            
            <div class="search-container" style="max-width: 300px;">
                <input type="text" id="supplier-search" placeholder="Search suppliers by name..." 
                       style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
            </div>

            <div class="filter-container" style="display: flex; align-items: center; gap: 5px;">
                <label for="supplier-status-filter" style="color:#374151; font-weight: 500;">Status:</label>
                <select id="supplier-status-filter" style="padding: 8px; border-radius: 4px; border: 1px solid #ccc;">
                    <option value="all">All</option>
                    <option value="active" selected>Active </option>
                    <option value="inactive">Inactive </option>
                </select>
            </div>
            
        </div>
        <button class="add-btn btn-add-supplier"
                style="background:#10b981; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">
            <i class="fas fa-plus"></i> Add Supplier
        </button>
        </div>
    <div class="supplier-list" style="
        display: grid; 
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); 
        gap: 20px;
    ">
        <p class="empty-message">Loading suppliers...</p>
    </div>
</section>

            <section id="reports" style="display:none; padding:30px;">
     <h2 style="color:#1e3a8a; margin-bottom: 10px;">📈 Sales Forecasting</h2>
<p style="color:#666; margin-bottom: 25px;">
    View predicted sales trends based on historical data. The forecast chart shows expected sales, and the top items chart shows forecasted best-selling products.
</p>

<div class="forecast-controls" style="margin-bottom:30px; display:flex; align-items:center; gap:10px;">
    <label for="forecastPeriod" style="font-weight:bold;">Forecast Period:</label>
    <select id="forecastPeriod" 
            style="padding:8px 12px; border-radius:5px; border:1px solid #ccc; font-size:14px; cursor:pointer;"
            onmouseover="this.style.borderColor='#2563eb';" 
            onmouseout="this.style.borderColor='#ccc';"
            onfocus="this.style.outline='2px solid #2563eb'; outlineOffset='2px';"
            onblur="this.style.outline='none';">
        <option value="7">Next 7 Days</option>
        <option value="30" selected>Next 30 Days</option>
        <option value="90">Next 90 Days</option>
    </select>
    <button class="generate-btn" 
            style="background:#16a34a; color:white; border:none; padding:8px 15px; border-radius:5px; cursor:pointer; font-weight:bold;"
            onmouseover="this.style.background='#13803d';"
            onmouseout="this.style.background='#16a34a';"
            onfocus="this.style.outline='2px solid #2563eb'; outlineOffset='2px';"
            onblur="this.style.outline='none';">
        Generate Forecast
    </button>
</div>

<!-- Forecast Cards -->
<div class="forecast-cards" style="display:flex; gap:15px; margin-bottom:30px;">
    <div class="card" style="flex:1; padding:15px; border-radius:8px; box-shadow:0 0 10px rgba(0,0,0,0.05); background:#fff;">
        <h3 style="color:#16a34a;">Predicted Total Sales</h3>
        <p class="value" style="font-size:1.5em; color:#16a34a;">₱0</p>
    </div>
    <div class="card" style="flex:1; padding:15px; border-radius:8px; box-shadow:0 0 10px rgba(0,0,0,0.05); background:#fff;">
        <h3 style="color:#2563eb;">Predicted Items Sold</h3>
        <p class="value" style="font-size:1.5em; color:#2563eb;">0</p>
    </div>
    <div class="card" style="flex:1; padding:15px; border-radius:8px; box-shadow:0 0 10px rgba(0,0,0,0.05); background:#fff;">
        <h3 style="color:#f59e0b;">Top Forecasted Category</h3>
        <p class="value" style="font-size:1.5em; color:#f59e0b;">N/A</p>
    </div>
</div>

<!-- 70/30 Layout: Main Forecast & Top Items Chart -->
<div class="forecast-main" style="display:flex; align-items:flex-start; gap:20px; margin-bottom:30px;">
    <!-- Main Forecast Chart (70%) -->
    <div class="forecast-chart" style="flex:7; position:relative; height:420px; max-height:420px; overflow:hidden; background:linear-gradient(180deg,#ffffff,#f8fafc); border-radius:12px; padding:16px; box-shadow:0 4px 16px rgba(30,58,138,0.08); box-sizing:border-box;">
        <h3 style="color:#1e3a8a; margin:0 0 8px 0; font-size:15px; font-weight:600; display:flex; align-items:center; gap:8px;">
            📊 Predicted Sales Trend
            <span id="forecastEngineBadge" style="font-size:11px; font-weight:600; padding:2px 8px; border-radius:999px; background:#eef2ff; color:#4338ca; display:none;"></span>
        </h3>
        <div style="position:relative; width:100%; height:calc(100% - 28px);">
            <canvas id="forecastChart"></canvas>
        </div>
        <!-- Centered hint text -->
        <div id="forecastHint" 
             style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); color:#888; font-size:1.2em; text-align:center; pointer-events:none;">
            Forecast will appear here
        </div>
    </div>

    <!-- Top Forecasted Items Chart (30%) -->
    <div class="top-items-chart" style="flex:3; height:420px; max-height:420px; overflow:hidden; background:linear-gradient(180deg,#ffffff,#f8fafc); border-radius:12px; padding:16px; box-shadow:0 4px 16px rgba(30,58,138,0.08); box-sizing:border-box; display:flex; flex-direction:column;">
        <h3 style="color:#1e3a8a; text-align:center; margin:0 0 10px 0; font-size:15px; font-weight:600;">🔥 Top Forecasted Items</h3>
        <div style="position:relative; width:100%; flex:1; min-height:0;">
            <canvas id="topItemsChart"></canvas>
        </div>
        <p style="text-align:center; color:#94a3b8; margin:8px 0 0 0; font-size:12px;">Bar chart of forecasted best-selling items.</p>
    </div>
</div>
</section>

<section id="profile" style="display:none; padding:30px;">
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
                <img id="profile_avatar" src="<?= !empty($staff['profile_image']) ? htmlspecialchars($staff['profile_image']) : 'https://cdn-icons-png.flaticon.com/512/2922/2922510.png' ?>" alt="Profile photo">
                <label for="profile_avatar_input" class="profile-photo-btn" title="Change profile picture">
                    <i class="fas fa-camera"></i>
                </label>
                <input type="file" id="profile_avatar_input" accept="image/png,image/jpeg,image/webp,image/gif">
            </div>

            <h3 id="profileCardName"><?= htmlspecialchars(trim(($staff['first_name'] ?? $user_first_name) . ' ' . ($staff['last_name'] ?? ''))) ?></h3>
            <p class="profile-username">@<?= htmlspecialchars($staff['username'] ?? 'admin') ?></p>

            <div class="profile-pill-row">
                <span class="profile-pill profile-pill-blue"><i class="fas fa-shield-halved"></i>Administrator</span>
            </div>
        </aside>

        <div class="profile-details-card">
            <div class="profile-card-head">
                <div>
                    <h3>Account Details</h3>
                    <p>These values are loaded live from your staff record and saved straight to the database.</p>
                </div>
            </div>

            <div class="profile-form-grid">
                <label>
                    <span>First Name</span>
                    <input type="text" id="first_name" class="p-input" value="<?= htmlspecialchars($staff['first_name'] ?? '') ?>" disabled required>
                </label>
                <label>
                    <span>Middle Name</span>
                    <input type="text" id="middle_name" class="p-input" value="<?= htmlspecialchars($staff['middle_name'] ?? '') ?>" disabled>
                </label>
                <label>
                    <span>Last Name</span>
                    <input type="text" id="last_name" class="p-input" value="<?= htmlspecialchars($staff['last_name'] ?? '') ?>" disabled required>
                </label>
                <label>
                    <span>Email</span>
                    <input type="email" id="email" class="p-input" value="<?= htmlspecialchars($staff_display_email) ?>" disabled required>
                </label>
                <label>
                    <span>Phone</span>
                    <input type="text" id="phone_number" class="p-input" value="<?= htmlspecialchars($staff['phone_number'] ?? '') ?>" disabled>
                </label>
                <label class="profile-field-wide">
                    <span>Address</span>
                    <textarea id="address" class="p-input" disabled><?= htmlspecialchars($staff['address'] ?? '') ?></textarea>
                </label>
            </div>

            <p id="message" class="profile-form-msg" aria-live="polite"></p>

            <div class="profile-action-row">
                <button type="button" id="profile_editBtn" class="profile-btn profile-btn-primary"><i class="fas fa-pen"></i>Edit Profile</button>
                <button type="button" id="saveBtn" class="profile-btn profile-btn-success" hidden><i class="fas fa-check"></i>Save Changes</button>
                <button type="button" id="profile_cancelBtn" class="profile-btn profile-btn-muted" hidden>Cancel</button>
            </div>
        </div>

        <aside class="profile-password-card">
            <h3><i class="fas fa-lock"></i>Change Password</h3>
            <p>This is separate from your account details above — updating it does not change your name, email, or contact info.</p>
            <form id="adminPasswordForm">
                <input type="password" id="current_password" autocomplete="current-password" placeholder="Current password">
                <input type="password" id="new_password" autocomplete="new-password" placeholder="New password (min 6 characters)">
                <input type="password" id="confirm_password" autocomplete="new-password" placeholder="Confirm new password">
                <button type="submit" id="changePasswordBtn" class="profile-btn profile-btn-danger">Update Password</button>
                <p id="password-message" class="profile-form-msg" aria-live="polite"></p>
            </form>
        </aside>
    </div>
  </div>
</section>

<script>
(function () {
  const profileOriginal = {};
  document.querySelectorAll("#profile .p-input").forEach(i => { profileOriginal[i.id] = i.value; });

  const editBtn = document.getElementById("profile_editBtn");
  const saveBtn = document.getElementById("saveBtn");
  const cancelBtn = document.getElementById("profile_cancelBtn");
  const msg = document.getElementById("message");

  editBtn.onclick = () => {
      document.querySelectorAll("#profile .p-input").forEach(i => i.disabled = false);
      saveBtn.hidden = false;
      cancelBtn.hidden = false;
      editBtn.hidden = true;
      msg.textContent = "";
  };

  cancelBtn.onclick = () => {
      document.querySelectorAll("#profile .p-input").forEach(i => {
          i.value = profileOriginal[i.id];
          i.disabled = true;
      });
      saveBtn.hidden = true;
      cancelBtn.hidden = true;
      editBtn.hidden = false;
      msg.textContent = "";
  };

  saveBtn.onclick = () => {
      const data = {
          first_name: document.getElementById("first_name").value.trim(),
          middle_name: document.getElementById("middle_name").value.trim(),
          last_name: document.getElementById("last_name").value.trim(),
          email: document.getElementById("email").value.trim(),
          phone_number: document.getElementById("phone_number").value.trim(),
          address: document.getElementById("address").value.trim()
      };

      msg.style.color = "#6b7280";
      msg.textContent = "Saving...";

      fetch("update_profile_admin.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(data)
      })
      .then(res => res.json())
      .then(response => {
          if (response.success) {
              msg.style.color = "#16a34a";
              msg.textContent = response.message || "Profile updated successfully!";

              // Use what the server actually verified was persisted in the DB
              // (not just what we typed) as the new source of truth for the form.
              const saved = {
                  first_name: response.first_name ?? data.first_name,
                  middle_name: response.middle_name ?? data.middle_name,
                  last_name: response.last_name ?? data.last_name,
                  email: response.email ?? data.email,
                  phone_number: response.phone_number ?? data.phone_number,
                  address: response.address ?? data.address
              };
              Object.keys(saved).forEach(key => {
                  profileOriginal[key] = saved[key];
                  const field = document.getElementById(key);
                  if (field) field.value = saved[key];
              });
              document.querySelectorAll("#profile .p-input").forEach(i => i.disabled = true);
              saveBtn.hidden = true;
              cancelBtn.hidden = true;
              editBtn.hidden = false;

              const nameHeader = document.getElementById("profileCardName");
              if (nameHeader) nameHeader.textContent = `${saved.first_name} ${saved.last_name}`.trim();

              const headerWelcome = document.getElementById("headerWelcomeName");
              if (headerWelcome) headerWelcome.textContent = `Welcome, ${saved.first_name}`;
          } else {
              msg.style.color = "#e74c3c";
              msg.textContent = response.message || "Update failed.";
          }
      })
      .catch(err => {
          msg.style.color = "#e74c3c";
          msg.textContent = "Request error.";
          console.error("AJAX error:", err);
      });
  };

  document.getElementById("adminPasswordForm").addEventListener("submit", (e) => {
      e.preventDefault();
      const pwMsg = document.getElementById("password-message");
      fetch("change_password.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
              current_password: document.getElementById("current_password").value,
              new_password: document.getElementById("new_password").value,
              confirm_password: document.getElementById("confirm_password").value
          })
      })
      .then(res => res.json())
      .then(r => {
          pwMsg.style.color = r.success ? "#16a34a" : "#e74c3c";
          pwMsg.textContent = r.message || (r.success ? "Password updated." : "Failed.");
          if (r.success) {
              ["current_password","new_password","confirm_password"].forEach(id => document.getElementById(id).value = "");
          }
      })
      .catch(() => { pwMsg.style.color = "#e74c3c"; pwMsg.textContent = "Request error."; });
  });

  document.getElementById("profile_avatar_input").addEventListener("change", function () {
      const file = this.files[0];
      if (!file) return;
      const formData = new FormData();
      formData.append("profile_image", file);

      fetch("upload_profile_picture.php", { method: "POST", body: formData })
          .then(res => res.json())
          .then(response => {
              if (response.success) {
                  document.getElementById("profile_avatar").src = response.path + "?t=" + Date.now();
                  msg.style.color = "#16a34a";
                  msg.textContent = "Profile picture updated!";
              } else {
                  msg.style.color = "#e74c3c";
                  msg.textContent = response.message || "Failed to upload picture.";
              }
          })
          .catch(err => {
              console.error("Avatar upload error:", err);
              msg.style.color = "#e74c3c";
              msg.textContent = "Upload error.";
          });
  });
})();
</script>


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function () {
    const topItemsCtx = document.getElementById('topItemsChart').getContext('2d');
    const forecastChartCtx = document.getElementById('forecastChart').getContext('2d');
    const forecastHint = document.getElementById('forecastHint');
    const forecastPeriodSelect = document.getElementById('forecastPeriod');
    const generateBtn = document.querySelector('.generate-btn');

    const forecastCards = document.querySelectorAll('.forecast-cards .card .value');
    // forecastCards[0] = Predicted Total Sales, [1] = Predicted Items Sold, [2] = Top Forecasted Category

    const PALETTE = ['#2563eb', '#16a34a', '#f59e0b', '#8b5cf6', '#ec4899'];

    // Gradient bars — same hue family as PharmaLink's brand blue, gives the
    // "top items" chart some depth instead of flat fills.
    function barGradient(ctx, color) {
        color = color || PALETTE[0]; // guard: Chart.js can resolve this before a real index/color exists
        const chartArea = ctx.chart.chartArea;
        if (!chartArea) return color;
        const g = ctx.chart.ctx.createLinearGradient(chartArea.left, 0, chartArea.right, 0);
        g.addColorStop(0, color + 'cc');
        g.addColorStop(1, color);
        return g;
    }

    const topItemsChart = new Chart(topItemsCtx, {
        type: 'bar',
        data: {
            labels: [],
            datasets: [{
                label: 'Predicted Units Sold',
                data: [],
                backgroundColor: (ctx) => barGradient(ctx, PALETTE[(ctx.dataIndex ?? 0) % PALETTE.length]),
                borderRadius: 6,
                borderSkipped: false,
                maxBarThickness: 26
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 600, easing: 'easeOutQuart' },
            layout: { padding: { right: 8 } },
            plugins: {
                legend: { display: false },
                tooltip: { enabled: true, backgroundColor: '#1e3a8a', padding: 10, cornerRadius: 8 }
            },
            scales: {
                x: { beginAtZero: true, ticks: { precision: 0, font: { size: 11 } }, grid: { color: '#eef2f7' } },
                y: { ticks: { color: '#334155', font: { size: 12 } }, grid: { display: false } }
            }
        }
    });

    const forecastChart = new Chart(forecastChartCtx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [
                {
                    // Prophet's upper confidence bound — invisible line, fills
                    // down to the "Lower bound" dataset right after it to draw
                    // the shaded uncertainty band. Empty data (linear-regression
                    // fallback has no interval) simply draws nothing.
                    label: 'Upper bound',
                    data: [],
                    borderWidth: 0,
                    pointRadius: 0,
                    fill: '+1',
                    backgroundColor: 'rgba(37,99,235,0.10)',
                    tension: 0.35
                },
                {
                    label: 'Lower bound',
                    data: [],
                    borderWidth: 0,
                    pointRadius: 0,
                    fill: false,
                    tension: 0.35
                },
                {
                    label: 'Forecasted Sales (₱)',
                    data: [],
                    borderColor: '#2563eb',
                    backgroundColor: (ctx) => {
                        const chartArea = ctx.chart.chartArea;
                        if (!chartArea) return 'rgba(37,99,235,0.12)';
                        const g = ctx.chart.ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                        g.addColorStop(0, 'rgba(37,99,235,0.35)');
                        g.addColorStop(1, 'rgba(37,99,235,0.02)');
                        return g;
                    },
                    fill: true,
                    tension: 0.35,
                    borderWidth: 2.5,
                    pointRadius: 3,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#2563eb',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 1.5
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 700, easing: 'easeOutQuart' },
            interaction: { mode: 'index', intersect: false },
            layout: { padding: { top: 4, right: 8 } },
            plugins: {
                legend: {
                    display: true,
                    labels: {
                        boxWidth: 12,
                        font: { size: 11 },
                        filter: (item) => item.text !== 'Upper bound' && item.text !== 'Lower bound'
                    }
                },
                tooltip: {
                    backgroundColor: '#1e3a8a',
                    padding: 10,
                    cornerRadius: 8,
                    filter: (item) => item.dataset.label === 'Forecasted Sales (₱)',
                    callbacks: {
                        label: (ctx) => `₱${Number(ctx.parsed.y).toLocaleString(undefined, {minimumFractionDigits: 2})}`
                    }
                }
            },
            scales: {
                x: {
                    ticks: { font: { size: 10 }, maxRotation: 0, autoSkip: true, maxTicksLimit: 8 },
                    grid: { display: false }
                },
                y: {
                    title: { display: true, text: 'Predicted Sales (₱)', font: { size: 11 } },
                    beginAtZero: true,
                    ticks: { font: { size: 10 }, callback: (v) => '₱' + Number(v).toLocaleString() },
                    grid: { color: '#eef2f7' }
                }
            }
        }
    });

    // Charts are built while the Reports section is still display:none
    // (it's hidden until the nav item is clicked), so Chart.js has no real
    // box to measure and can end up with a bad internal size that then
    // compounds every redraw — this is what caused the ever-growing chart
    // area. Exposing the instances lets the nav click handler force a
    // resize() once the section is actually visible and measurable.
    window.__forecastChart = forecastChart;
    window.__topItemsChart = topItemsChart;

    function setCards(totalSales, itemsSold, topCategory) {
        if (forecastCards[0]) forecastCards[0].textContent = '₱' + Number(totalSales).toLocaleString(undefined, {minimumFractionDigits: 2});
        if (forecastCards[1]) forecastCards[1].textContent = Number(itemsSold).toLocaleString();
        if (forecastCards[2]) forecastCards[2].textContent = topCategory || 'N/A';
    }

    function generateForecast() {
        const period = forecastPeriodSelect ? forecastPeriodSelect.value : 30;

        if (generateBtn) {
            generateBtn.disabled = true;
            generateBtn.textContent = 'Generating...';
        }
        forecastHint.style.display = 'block';
        forecastHint.textContent = 'Crunching the numbers...';

        fetch(`generate_forecast.php?period=${period}`)
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    forecastHint.textContent = data.message || 'Unable to generate forecast.';
                    return;
                }
                if (data.insufficient_data) {
                    forecastHint.style.display = 'block';
                    forecastHint.textContent = data.message;
                    forecastChart.data.labels = [];
                    forecastChart.data.datasets.forEach(ds => ds.data = []);
                    forecastChart.update();
                    topItemsChart.data.labels = [];
                    topItemsChart.data.datasets[0].data = [];
                    topItemsChart.update();
                    setCards(0, 0, 'N/A');
                    const badge = document.getElementById('forecastEngineBadge');
                    if (badge) badge.style.display = 'none';
                    return;
                }

                forecastChart.data.labels = data.forecast.labels;
                forecastChart.data.datasets[0].data = data.forecast.upper || [];
                forecastChart.data.datasets[1].data = data.forecast.lower || [];
                forecastChart.data.datasets[2].data = data.forecast.values;
                forecastChart.resize();
                forecastChart.update();
                if (data.forecast.values.length > 0) forecastHint.style.display = 'none';

                topItemsChart.data.labels = data.top_items.labels;
                topItemsChart.data.datasets[0].data = data.top_items.data;
                topItemsChart.resize();
                topItemsChart.update();

                setCards(data.predicted_total_sales, data.predicted_items_sold, data.top_category);

                const badge = document.getElementById('forecastEngineBadge');
                if (badge) {
                    if (data.engine === 'prophet') {
                        badge.textContent = '🤖 Prophet ML';
                        badge.title = 'Forecast generated by Facebook Prophet (linear trend + weekly seasonality).';
                        badge.style.background = '#eef2ff';
                        badge.style.color = '#4338ca';
                        badge.style.display = 'inline-block';
                    } else if (data.engine === 'linear_regression_fallback') {
                        badge.textContent = '📐 Trend model';
                        badge.title = 'Prophet is not available on this server, so a simplified trend model was used instead.';
                        badge.style.background = '#fef3c7';
                        badge.style.color = '#92400e';
                        badge.style.display = 'inline-block';
                    } else {
                        badge.style.display = 'none';
                    }
                }
            })
            .catch(err => {
                console.error('Forecast error:', err);
                forecastHint.style.display = 'block';
                forecastHint.textContent = 'Something went wrong generating the forecast.';
            })
            .finally(() => {
                if (generateBtn) {
                    generateBtn.disabled = false;
                    generateBtn.textContent = 'Generate Forecast';
                }
            });
    }

    if (generateBtn) generateBtn.addEventListener('click', generateForecast);

    // Auto-generate once when the Reports tab is first opened
    const reportsNavItem = document.querySelector('.nav-item[data-target="reports"]');
    let forecastLoaded = false;
    if (reportsNavItem) {
        reportsNavItem.addEventListener('click', () => {
            if (!forecastLoaded) {
                forecastLoaded = true;
                generateForecast();
            }
        });
    }
})();
</script>




        </div></div><div id="addItemModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Stock Lot</h2>
                <span class="close" id="closeModal">&times;</span>
            </div>
            <form id="addItemForm">
                <label for="drug_master_id">Select Standard Drug:</label>
                <select id="drug_master_id" required>
                    <option value="">Loading Drug List...</option>
                </select>
                <label for="lot_number">Lot/Batch Number:</label>
                <input type="text" id="lot_number" placeholder="Lot Number (e.g., AB1234)" required>
                <label for="expiration_date">Expiration Date:</label>
                <input type="date" id="expiration_date" required>
                <label for="current_stock">Initial Stock Quantity:</label>
                <input type="number" id="current_stock" placeholder="Current Stock" required>
                <label for="price">Unit Price:</label>
                <input type="number" step="0.01" id="price" placeholder="Price" required>
<label for="supplier">Supplier:</label>
<select id="supplier" name="supplier" required>
    <option value="">Select Supplier</option>
</select>
                <button type="submit" class="save" style="background:#16a34a;">Save Stock Lot</button>
            </form>
        </div>
    </div>

    <div id="addDrugModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Define New Standard Drug 🧬</h2>
                <span class="close" id="closeDrugModal">&times;</span>
            </div>
            <form id="addDrugForm">
                <label for="new_generic_name">Generic Name:</label>
                <input type="text" id="new_generic_name" placeholder="Paracetamol" required>
                <label for="new_brand_name">Brand Name (Optional):</label>
                <input type="text" id="new_brand_name" placeholder="Tylenol or leave blank">
                <label for="new_dosage">Dosage:</label>
                <input type="text" id="new_dosage" placeholder="500mg" required>
                <label for="new_form">Form:</label>
                <input type="text" id="new_form" placeholder="Tablet, Syrup, Capsule" required>
                <label for="new_category">Category:</label>
                <select id="new_category" required>
                    <option value="">Select Category</option>
                    <option value="antibiotic">Antibiotic</option>
                    <option value="analgesic">Analgesic</option>
                    <option value="antipyretic">Antipyretic</option>
                    <option value="antihistamine">Antihistamine</option>
                    <option value="antacid">Antacid</option>
                    <option value="antihypertensive">Antihypertensive</option>
                    <option value="antidiabetic">Antidiabetic</option>
                    <option value="vitamin">Vitamin / Supplement</option>
                </select>
                <label for="new_minimum_stock">Minimum Stock Level:</label>
                <input type="number" id="new_minimum_stock" placeholder="e.g. 50" required>
                <button type="submit" style="background: #2563eb;" class="save">Create Drug Definition</button>
            </form>
        </div>
    </div>

    <div id="editDrugMasterModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Drug Definition: <span id="currentMasterDrugName"></span></h2>
                <span class="close" id="closeEditDrugMasterModal">&times;</span>
            </div>
            <form id="editDrugMasterForm">
                <input type="hidden" id="edit_master_drug_id">
                <label for="edit_generic_name">Generic Name:</label>
                <input type="text" id="edit_generic_name" required>
                <label for="edit_brand_name">Brand Name (Optional):</label>
                <input type="text" id="edit_brand_name">
                <label for="edit_dosage">Dosage:</label>
                <input type="text" id="edit_dosage" required>
                <label for="edit_form">Form:</label>
                <input type="text" id="edit_form" required>
                <label for="edit_category">Category:</label>
                <select id="edit_category" required>
                    <option value="antibiotic">Antibiotic</option>
                    <option value="analgesic">Analgesic</option>
                    <option value="antipyretic">Antipyretic</option>
                    <option value="antihistamine">Antihistamine</option>
                    <option value="antacid">Antacid</option>
                    <option value="antihypertensive">Antihypertensive</option>
                    <option value="antidiabetic">Antidiabetic</option>
                    <option value="vitamin">Vitamin / Supplement</option>
                </select>
                <label for="edit_minimum_stock">Minimum Stock Level:</label>
                <input type="number" id="edit_minimum_stock" required>
                <button type="submit" style="background: #f59e0b;" class="update">Update Definition</button>
            </form>
        </div>
    </div>

    <div id="editLotModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Stock Lot: <span id="editDrugName"></span></h2>
                <span class="close" id="closeEditModal">&times;</span>
            </div>
            <form id="editLotForm">
                <input type="hidden" id="edit_lot_inventory_id">
                <label>Standard Drug:</label>
                <p id="edit_drug_label" style="font-weight: bold; margin-top: 0; margin-bottom: 10px;"></p>
                <label for="edit_lot_number">Lot/Batch Number:</label>
                <input type="text" id="edit_lot_number" required>
                <label for="edit_expiration_date">Expiration Date:</label>
                <input type="date" id="edit_expiration_date" required>
                <label for="edit_current_stock">Current Stock Quantity:</label>
                <input type="number" id="edit_current_stock" required>
                <label for="edit_price">Unit Price (₱):</label>
                <input type="number" step="0.01" id="edit_price" required>
        
<label for="edit_supplier">Supplier:</label>
<select id="edit_supplier" name="supplier" required>
</select>
                <button type="submit" style="background: #f59e0b;" class="update">Update Lot</button>
            </form>
        </div>
    </div>

    <script src="inventory.js"></script>
    <script src="supplier.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Flag to ensure User Management events are initialized only once
    let userManagementInitialized = false;

    // ==========================================================
    // 1. Sidebar Nav Toggle + Section Switch
    // ==========================================================
    const navItems = document.querySelectorAll('.nav-item[data-target]');
    // Note: Sa pinagsama mong code, may .content section, pero ginawa kong direct section select
    const sections = document.querySelectorAll('section'); 

    // Initial State: Hide all sections, show Dashboard
    sections.forEach(sec => sec.style.display = 'none');
    const dashboardSection = document.getElementById('dashboard');
    if (dashboardSection) dashboardSection.style.display = 'block';
    
    // Set Dashboard as active initially
    const dashboardNav = document.querySelector('.nav-item[data-target="dashboard"]');
    if (dashboardNav) dashboardNav.classList.add('active');

    navItems.forEach(item => {
        item.addEventListener('click', function() {
            // Tab Switching Logic
            navItems.forEach(i => i.classList.remove('active'));
            sections.forEach(sec => sec.style.display = 'none');
            
            const targetId = this.getAttribute('data-target');
            this.classList.add('active');
            
            const targetSection = document.getElementById(targetId);
            if (targetSection) {
                targetSection.style.display = 'block';
            }

            // Module Initialization (Run specific logic when a tab is clicked)
            if (targetId === 'user-management' && !userManagementInitialized) {
                // Initialize user management events when first viewed
                initializeUserManagementEvents();
                userManagementInitialized = true;
            } else if (targetId === 'supplier-section') {
                if (typeof initializeSupplierModule === 'function') {
                    initializeSupplierModule();
                }
            } else if (targetId === 'reports') {
                // The forecast charts were built while this section was still
                // display:none, so they need an explicit resize() now that
                // they have a real, measurable box — otherwise Chart.js's
                // internal size stays wrong and grows a little more on every
                // redraw. requestAnimationFrame waits one paint so the
                // 'block' display change above has actually taken effect.
                requestAnimationFrame(() => {
                    if (window.__forecastChart) window.__forecastChart.resize();
                    if (window.__topItemsChart) window.__topItemsChart.resize();
                });
            }
            // Add other module initializations here (e.g., inventory)
        });
    });

    // ==========================================================
    // 2. Modal Toggles (Inalis ang mga hindi na ginagamit at inayos ang logic)
    // ==========================================================
    const addDrugMasterBtn = document.getElementById('addDrugMasterBtn');
    const addItemBtn = document.getElementById('addItemBtn');
    const addDrugModal = document.getElementById('addDrugModal');
    const addItemModal = document.getElementById('addItemModal');
    
    // Note: Sa dynamic modals (Staff/Customer), hindi na kailangan ang close buttons
    // dahil auto-generated na ang close logic sa createModal function.
    
    // Standard Modals (Inventory/Drug)
    if(addDrugMasterBtn) addDrugMasterBtn.addEventListener('click', ()=>{ if(addDrugModal) addDrugModal.style.display='flex'; });
    if(addItemBtn) addItemBtn.addEventListener('click', ()=>{ if(addItemModal) addItemModal.style.display='flex'; });

    // Closing Logic for standard Modals
    const closeDrugModal = document.getElementById('closeDrugModal');
    const closeModal = document.getElementById('closeModal');
    const closeEditModal = document.getElementById('closeEditModal');
    const closeEditDrugMasterModal = document.getElementById('closeEditDrugMasterModal');

    if(closeModal) closeModal.addEventListener('click', ()=>{ if(addItemModal) addItemModal.style.display='none'; });
    if(closeDrugModal) closeDrugModal.addEventListener('click', ()=>{ if(addDrugModal) addDrugModal.style.display='none'; });
    if(closeEditModal) closeEditModal.addEventListener('click', ()=>{ const editLotModal = document.getElementById('editLotModal'); if(editLotModal) editLotModal.style.display='none'; });
    if(closeEditDrugMasterModal) closeEditDrugMasterModal.addEventListener('click', ()=>{ const editDrugMasterModal = document.getElementById('editDrugMasterModal'); if(editDrugMasterModal) editDrugMasterModal.style.display='none'; });

    // Close Modals on outside click (Generic)
    window.addEventListener('click', e => {
        ['addItemModal','addDrugModal','editDrugMasterModal','editLotModal'].forEach(id=>{
            const modal = document.getElementById(id);
            if(modal && e.target === modal) modal.style.display='none';
        });
        // Added check for dynamically generated modals (class: modal-overlay)
        if (e.target.classList.contains('modal-overlay')) {
             e.target.remove();
        }
    });

// ==========================================================
// 3. User Management Functions (Inihiwalay para mas malinis)
// ==========================================================

// Helper function to send AJAX (Para sa Add actions)
function sendUserAction(data, callback){
    fetch('user_actions.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams(data)
    })
    .then(res => {
        if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
        return res.json();
    })
    .then(callback)
    .catch(err => alert("Error: " + err.message));
}

// Helper: Validate & Format Name (Unified)
function formatAndValidateName(name){
    name = name.trim();
    if(!name) return null;
    // Allow only letters, spaces, hyphen, apostrophe, period (for names like J.P.)
    if(!/^[a-zA-Z\s'.-]+$/.test(name)) return null; 
    // Capitalize each word
    return name.split(' ').filter(n => n).map(n => n.charAt(0).toUpperCase() + n.slice(1).toLowerCase()).join(' ');
}

// Dynamic Modal Generation Function
function createModal(title, contentHTML) {
    const modalContainer = document.createElement('div');
    modalContainer.className = 'modal-overlay';
    modalContainer.style.cssText = "display:flex; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; justify-content:center; align-items:center;";
    
    modalContainer.innerHTML = `
        <div class="modal-content" style="background:#fff; padding:20px; border-radius:8px; width:350px; position:relative; max-height:80vh; overflow-y:auto;">
            <span class="close-modal" style="position:absolute; top:10px; right:10px; cursor:pointer; font-size:20px;">&times;</span>
            <h3 class="modal-title" style="margin-top:0; margin-bottom:15px;">${title}</h3>
            ${contentHTML}
        </div>
    `;
    document.body.appendChild(modalContainer);

    const closeBtn = modalContainer.querySelector('.close-modal');
    closeBtn.onclick = () => modalContainer.remove();
    modalContainer.onclick = (e) => { if(e.target === modalContainer) modalContainer.remove(); };

    return {
        element: modalContainer,
        content: modalContainer.querySelector('.modal-content'),
        remove: () => modalContainer.remove()
    };
}

// --- Edit/Delete Staff/Customer Logic (Unified) ---

// **Ito ang inayos na EDIT function**
function attachEditEvents(staffTable, customerTable){ 
    // Kunin ang lahat ng Edit buttons sa parehong tables
    const allEditButtons = [
        ...staffTable.querySelectorAll('.edit-staff-btn'),
        ...customerTable.querySelectorAll('.edit-customer-btn')
    ];
    
    allEditButtons.forEach(btn=>{
        // Para malaman kung staff o customer ang button
        const isStaff = btn.classList.contains('edit-staff-btn');
        const fetchFile = isStaff ? 'get_staff_data.php' : 'get_customer_data.php';
        const updateFile = isStaff ? 'update_staff.php' : 'update_customer.php';
        const roleKey = isStaff ? 'user_id' : 'customer_id';

        btn.onclick = ()=>{
            const row = btn.closest('tr');
            const userId = row.dataset.id;
            
            // 1. Fetch Data
            fetch(fetchFile, {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded'},
                body: new URLSearchParams({[roleKey]: userId}) // user_id or customer_id
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    const data = res.data;
                    const modalTitle = isStaff ? `Edit Staff User: ${data.first_name}` : `Edit Customer: ${data.first_name}`;
                    let modalContent;
                    
                    if (isStaff) {
                        // Staff Modal Content (Old logic, just formatted to fit the new function)
                        modalContent = `
                            <form id="edit-form" data-id="${data.user_id}" autocomplete="off">
                                <label>First Name:</label>
                                <input type="text" name="first_name" value="${data.first_name}" required style="width:100%; margin-bottom:10px; padding:6px;">
                                <label>Middle Name (Optional):</label>
                                <input type="text" name="middle_name" value="${data.middle_name || ''}" style="width:100%; margin-bottom:10px; padding:6px;">
                                <label>Last Name:</label>
                                <input type="text" name="last_name" value="${data.last_name}" required style="width:100%; margin-bottom:10px; padding:6px;">
                                
                                <label>Email:</label> <input type="email" name="email" value="${data.email || ''}" style="width:100%; margin-bottom:10px; padding:6px;">
                                <label>Phone Number:</label> <input type="text" name="phone_number" value="${data.phone_number || ''}" style="width:100%; margin-bottom:10px; padding:6px;">
                                <label>Address:</label> <input type="text" name="address" value="${data.address || ''}" style="width:100%; margin-bottom:10px; padding:6px;">
                                
                                <label>Username (Cannot change):</label>
                                <input type="text" name="username" value="${data.username}" disabled style="width:100%; margin-bottom:10px; padding:6px; background:#f0f0f0;">
                                
                                <label>Role:</label>
                                <select name="role" required style="width:100%; margin-bottom:10px; padding:6px;">
                                    <option value="Admin" ${data.role_name === 'Admin' ? 'selected' : ''}>Admin</option>
                                    <option value="Cashier/Pharmacist" ${data.role_name === 'Cashier/Pharmacist' ? 'selected' : ''}>Cashier/Pharmacist</option> 
                                </select>
                                
                                <p style="font-size:12px; color:#ef4444; margin:10px 0;">* Leave password blank to keep current password.</p>
                                <label>New Password (Optional):</label>
                                <input type="password" name="password" autocomplete="new-password" style="width:100%; margin-bottom:15px; padding:6px;">
                                
                                <button type="submit" style="width:100%; padding:8px; background:#1e90ff; color:#fff; border:none; border-radius:4px;">Update Staff</button>
                            </form>`;
                    } else {
                        // Customer Modal Content
                        modalContent = `
                            <form id="edit-form" data-id="${data.customer_id}" autocomplete="off">
                                <label>First Name:</label>
                                <input type="text" name="first_name" value="${data.first_name}" required style="width:100%; margin-bottom:10px; padding:6px;">
                                <label>Middle Name (Optional):</label>
                                <input type="text" name="middle_name" value="${data.middle_name || ''}" style="width:100%; margin-bottom:10px; padding:6px;">
                                <label>Last Name:</label>
                                <input type="text" name="last_name" value="${data.last_name}" required style="width:100%; margin-bottom:10px; padding:6px;">
                                <label>Email:</label> <input type="email" name="email" value="${data.email || ''}" required style="width:100%; margin-bottom:10px; padding:6px;">
                                <label>Phone Number:</label> <input type="text" name="phone_number" value="${data.phone_number || ''}" required style="width:100%; margin-bottom:10px; padding:6px;">
                                <label>Address:</label> <input type="text" name="address" value="${data.address || ''}" required style="width:100%; margin-bottom:10px; padding:6px;">
                                <label>Customer Type:</label>
                                <select name="customer_type" required style="width:100%; margin-bottom:10px; padding:6px;">
                                    <option value="Regular" ${data.customer_type === 'Regular' ? 'selected' : ''}>Regular</option>
                                    <option value="Senior" ${data.customer_type === 'Senior' ? 'selected' : ''}>Senior</option>
                                    <option value="PWD" ${data.customer_type === 'PWD' ? 'selected' : ''}>PWD</option>
                                    <option value="Other" ${data.customer_type === 'Other' ? 'selected' : ''}>Other</option>
                                </select>
    <label>Points:</label>
    <input type="number" step="0.01" name="loyalty_points" id="loyalty_points" value="0" min="0" required style="width:100%; margin-bottom:10px; padding:6px;">
                                <p style="font-size:12px; color:#ef4444; margin:10px 0;">* Leave password blank to keep current password.</p>
                                <label>New Password (Optional):</label>
                                <input type="password" name="password" autocomplete="new-password" style="width:100%; margin-bottom:15px; padding:6px;">
                                
                                <button type="submit" style="width:100%; padding:8px; background:#1e90ff; color:#fff; border:none; border-radius:4px;">Update Customer</button>
                            </form>`;
                    }

                    const modal = createModal(modalTitle, modalContent);
                    const editForm = modal.element.querySelector('#edit-form');

                    // 3. Attach Submit Handler
                    editForm.onsubmit = e => {
                        e.preventDefault();
                        const formData = new FormData(editForm);
                        formData.append(roleKey, userId); 
                        
                        // Send update to the correct per-role endpoint (update_staff.php / update_customer.php)
                        fetch(updateFile, {
                            method: 'POST',
                            body: new URLSearchParams(formData) 
                        })
                        .then(res => res.json())
                        .then(res => {
                            if (res.success) {
                                alert(res.message);
                                
                                // Visual Update ng Table Row
                                const fullName = `${formData.get('first_name')} ${formData.get('middle_name') || ''} ${formData.get('last_name')}`.trim().replace(/\s+/g, ' ');
                                const tableRow = document.querySelector(`tr[data-id="${userId}"]`);
                                
                                if (tableRow) {
                                    if (isStaff) {
                                        // Staff table columns: 0:Name, 1:Role, 2:Email (Optional), 3:Phone (Optional)
                                        tableRow.children[0].textContent = fullName;
                                        tableRow.children[1].textContent = formData.get('role');
                                        tableRow.children[2].textContent = formData.get('email');
                                        tableRow.children[3].textContent = formData.get('phone_number');
                                        tableRow.dataset.role = formData.get('role'); // For filter
                                    } else {
                                        // Customer table columns: 0:Name, 1:Email, 2:Phone, 3:Actions
                                        tableRow.children[0].textContent = fullName;
                                        tableRow.children[1].textContent = formData.get('email');
                                        tableRow.children[2].textContent = formData.get('phone_number');
                                        tableRow.children[3].textContent = formData.get('loyalty_points'); 
                                    }
                                }

                                modal.remove(); 
                            } else {
                                alert('Update Failed: ' + res.message);
                            }
                        })
                        .catch(err => alert("Error submitting update: " + err.message));
                    };
                    
                } else {
                    alert(res.message);
                }
            })
            .catch(err => alert("Error fetching data: " + err.message));
        }
    });
}

// **Ito ang inayos na DELETE function**
function attachDeleteEvents(staffTable, customerTable){
    // Kunin ang lahat ng Delete buttons sa parehong tables
    const allDeleteButtons = [
        ...staffTable.querySelectorAll('.delete-staff-btn'),
        ...customerTable.querySelectorAll('.delete-customer-btn')
    ];
    
    allDeleteButtons.forEach(btn=>{
        const isStaff = btn.classList.contains('delete-staff-btn');
        const userRole = isStaff ? 'Staff' : 'Customer';
        const actionFile = isStaff ? 'deactivate_staff.php' : 'deactivate_customer.php'; 
        const roleKey = isStaff ? 'user_id' : 'customer_id';

        btn.onclick = ()=>{
            const row = btn.closest('tr');
            const userId = row.dataset.id;
            
            const confirmMsg = userRole === 'Staff' 
                ? `Are you sure you want to DEACTIVATE this Staff user? (ID: ${userId})`
                : `Are you sure you want to DEACTIVATE this Customer user? Sales history will be preserved. (ID: ${userId})`;

            if(!confirm(confirmMsg)) return;

            const actionData = {
                [roleKey]: userId
            };
            
            // --- GUMAMIT NG VANILLA FETCH/AJAX PARA I-TARGET ANG SPECIFIC DEACTIVATE FILE ---
            fetch(actionFile, {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded'},
                body: new URLSearchParams(actionData)
            })
            .then(res => res.json())
            .then(res=>{
                if(res.success){
                    row.remove();
                    alert(`${userRole} account deactivated!`);
                } else alert(res.message);
            })
            .catch(err => alert("Error: " + err.message));
        }
    });
}


// Main function to attach all User Management Events
function initializeUserManagementEvents() {
    const staffTable = document.querySelector('#user-management div:nth-of-type(1) table tbody');
const customerTable = document.getElementById('customer-table-body');    const addStaffBtn = document.querySelector('#user-management button[style*="background: #2563eb;"]');
    const addCustomerBtn = document.querySelector('#user-management button[style*="background: #16a34a;"]');

    // ---------------------------------------------------------
    // Search + Role Filter + Pagination (10 rows per page)
    // ---------------------------------------------------------
    const staffFilter = document.getElementById('staff-role-filter');
    const PH_PAGE_SIZE = 10;
    let staffPage = 1;
    let customerPage = 1;

    const staffSearchInput = document.getElementById('staff-search');
    const customerSearchInput = document.getElementById('customer-search');

    function staffMatches(row) {
        const term = (staffSearchInput ? staffSearchInput.value : '').trim().toLowerCase();
        const role = staffFilter ? staffFilter.value : 'All';
        const rowRole = row.dataset.role || '';
        const text = row.textContent.toLowerCase();
        const matchesRole = (role === 'All' || rowRole === role);
        const matchesSearch = term === '' || text.includes(term);
        return matchesRole && matchesSearch;
    }

    function customerMatches(row) {
        const term = (customerSearchInput ? customerSearchInput.value : '').trim().toLowerCase();
        const text = row.textContent.toLowerCase();
        return term === '' || text.includes(term);
    }

    function paginateTable(tableBody, matchFn, page, pageInfoEl, prevBtn, nextBtn) {
        if (!tableBody) return page;
        const rows = Array.from(tableBody.querySelectorAll('tr'));
        const filtered = rows.filter(matchFn);
        const totalPages = Math.max(1, Math.ceil(filtered.length / PH_PAGE_SIZE));
        if (page > totalPages) page = totalPages;
        if (page < 1) page = 1;

        rows.forEach(r => { r.style.display = 'none'; });
        filtered.slice((page - 1) * PH_PAGE_SIZE, page * PH_PAGE_SIZE).forEach(r => { r.style.display = ''; });

        if (pageInfoEl) pageInfoEl.textContent = `Page ${page} of ${totalPages} (${filtered.length} result${filtered.length === 1 ? '' : 's'})`;
        if (prevBtn) prevBtn.disabled = page <= 1;
        if (nextBtn) nextBtn.disabled = page >= totalPages;
        [prevBtn, nextBtn].forEach(b => { if (b) b.style.opacity = b.disabled ? '0.5' : '1'; if (b) b.style.cursor = b.disabled ? 'not-allowed' : 'pointer'; });

        return page;
    }

    const staffPageInfo = document.getElementById('staff-page-info');
    const staffPrevBtn = document.getElementById('staff-prev-btn');
    const staffNextBtn = document.getElementById('staff-next-btn');
    const customerPageInfo = document.getElementById('customer-page-info');
    const customerPrevBtn = document.getElementById('customer-prev-btn');
    const customerNextBtn = document.getElementById('customer-next-btn');

    function refreshStaffPagination() {
        staffPage = paginateTable(staffTable, staffMatches, staffPage, staffPageInfo, staffPrevBtn, staffNextBtn);
    }
    function refreshCustomerPagination() {
        customerPage = paginateTable(customerTable, customerMatches, customerPage, customerPageInfo, customerPrevBtn, customerNextBtn);
    }

    if (staffFilter) {
        staffFilter.onchange = function () { staffPage = 1; refreshStaffPagination(); };
    }
    if (staffSearchInput) {
        staffSearchInput.addEventListener('input', () => { staffPage = 1; refreshStaffPagination(); });
    }
    if (customerSearchInput) {
        customerSearchInput.addEventListener('input', () => { customerPage = 1; refreshCustomerPagination(); });
    }
    if (staffPrevBtn) staffPrevBtn.addEventListener('click', () => { staffPage--; refreshStaffPagination(); });
    if (staffNextBtn) staffNextBtn.addEventListener('click', () => { staffPage++; refreshStaffPagination(); });
    if (customerPrevBtn) customerPrevBtn.addEventListener('click', () => { customerPage--; refreshCustomerPagination(); });
    if (customerNextBtn) customerNextBtn.addEventListener('click', () => { customerPage++; refreshCustomerPagination(); });

    // Re-paginate automatically whenever rows are added/removed (e.g. after
    // Add/Edit/Deactivate actions elsewhere in this file mutate the tables).
    if (staffTable) {
        new MutationObserver(() => refreshStaffPagination()).observe(staffTable, { childList: true });
    }
    if (customerTable) {
        new MutationObserver(() => refreshCustomerPagination()).observe(customerTable, { childList: true });
    }

    refreshStaffPagination();
    refreshCustomerPagination();

    // --- Staff Management (Add Logic) ---
    if(addStaffBtn){
        addStaffBtn.onclick = () => {
            const staffModalContent = `
                <form id="staff-form" autocomplete="off">
                    <label>First Name:</label>
                    <input type="text" name="first_name" required style="width:100%; margin-bottom:10px; padding:6px;">
                    <label>Middle Name (Optional):</label>
                    <input type="text" name="middle_name" style="width:100%; margin-bottom:10px; padding:6px;">
                    <label>Last Name:</label>
                    <input type="text" name="last_name" required style="width:100%; margin-bottom:10px; padding:6px;">
                    
                    <label>Email:</label> <input type="email" name="email" style="width:100%; margin-bottom:10px; padding:6px;">
                    <label>Phone Number:</label> <input type="text" name="phone_number" style="width:100%; margin-bottom:10px; padding:6px;">
                    <label>Address:</label> <input type="text" name="address" style="width:100%; margin-bottom:10px; padding:6px;">
                    
                    <label>Username:</label>
                    <input type="text" name="username" autocomplete="off" required style="width:100%; margin-bottom:10px; padding:6px;">
                    <label>Role:</label>
                    <select name="role" required style="width:100%; margin-bottom:10px; padding:6px;">
                        <option value="">Select Role</option>
                        <option value="Admin">Admin</option>
                        <option value="Cashier/Pharmacist">Cashier/Pharmacist</option> 
                    </select>
                 <label>Password:</label>
                    <input type="password" name="password" autocomplete="new-password" required style="width:100%; margin-bottom:15px; padding:6px;">
                    <button type="submit" style="width:100%; padding:8px; background:#2563eb; color:#fff; border:none; border-radius:4px;">Add Staff</button>
                </form>`;

            const modal = createModal('Add Staff User', staffModalContent);
            const staffForm = modal.element.querySelector('#staff-form');

            staffForm.onsubmit = e => {
                e.preventDefault();
                const form = e.target;
                
                let first_name = formatAndValidateName(form.first_name.value);
                let middle_name = formatAndValidateName(form.middle_name.value) || '';
                let last_name = formatAndValidateName(form.last_name.value);
                const username = form.username.value.trim();
                const role = form.role.value;
                const password = form.password.value;
                const email = form.email.value.trim(); 
                const phone_number = form.phone_number.value.trim(); 
                const address = form.address.value.trim(); 

                if(!first_name || !last_name) return alert("Invalid Name...");
                if(!username || !role || !password) return alert('Please fill all required fields.');

                sendUserAction({
                    action:'add', 
                    role, 
                    first_name, 
                    middle_name, 
                    last_name, 
                    username, 
                    password,
                    email,
                    phone_number,
                    address
                }, res => {
                    if(res.status==='success'){
                        const fullName = `${first_name} ${middle_name} ${last_name}`.trim().replace(/\s+/g, ' ');
                        const newRow = document.createElement('tr');
                        newRow.dataset.id = res.user_id; 
                        newRow.dataset.role = role; // Importante ito para sa Filter!
                        newRow.innerHTML = `
                            <td>${fullName}</td>
                            <td>${role}</td>
                            <td>${email}</td>
                            <td>${phone_number}</td>
                            <td class="action-btn-group">
                                <button class="add-btn edit-staff-btn" style="background: #1e90ff; padding: 6px 12px;">Edit</button>
                                <button class="add-btn delete-staff-btn" style="background: #e63946; padding: 6px 12px;">Delete</button>
                            </td>
                        `;
                        // Append to the start of the table
                        staffTable.prepend(newRow); 

                        // Re-attach events for the entire table (covers new and existing)
                        attachEditEvents(staffTable, customerTable);
                        attachDeleteEvents(staffTable, customerTable);

                        alert("Staff added successfully!");
                        modal.remove();
                    } else alert(res.msg);
                });
            };
        };
    }

    // --- Customer Management (Add Logic) ---
    if(addCustomerBtn) addCustomerBtn.addEventListener('click', () => {
 const customerModalContent = `
<form id="addCustomerForm" autocomplete="off">
    <label>First Name:</label>
    <input type="text" name="first_name" required style="width:100%; margin-bottom:10px; padding:6px;">

    <label>Middle Name (Optional):</label>
    <input type="text" name="middle_name" style="width:100%; margin-bottom:10px; padding:6px;">

    <label>Last Name:</label>
    <input type="text" name="last_name" required style="width:100%; margin-bottom:10px; padding:6px;">

    <label>Username:</label>
    <input type="text" name="username" autocomplete="off" required style="width:100%; margin-bottom:10px; padding:6px;">

    <label>Email:</label>
    <input type="email" name="email" required style="width:100%; margin-bottom:10px; padding:6px;">

    <label>Phone Number:</label>
    <input type="text" name="phone_number" required style="width:100%; margin-bottom:10px; padding:6px;">

    <label>Address:</label>
    <input type="text" name="address" required style="width:100%; margin-bottom:10px; padding:6px;">

    <label>Customer Type:</label>
    <select name="customer_type" required style="width:100%; margin-bottom:10px; padding:6px;">
        <option value="Regular">Regular</option>
        <option value="Senior">Senior</option>
        <option value="PWD">PWD</option>
        <option value="Other">Other</option>
    </select>

    <label>Points:</label>
<input type="number" step="0.01" name="loyalty_points" id="loyalty_points" value="0" min="0" required style="width:100%; margin-bottom:10px; padding:6px;">
    <label>Password:</label>
    <input type="password" name="password" autocomplete="new-password" required style="width:100%; margin-bottom:15px; padding:6px;">

    <button type="submit" style="width:100%; padding:8px; background:#16a34a; color:#fff; border:none; border-radius:4px;">
        Add Customer
    </button>
</form>
`;
        
        const modal = createModal('Add Customer', customerModalContent);
        const form = modal.element.querySelector('#addCustomerForm');
        
        form.onsubmit = (e) => {
            e.preventDefault();

            // Collect and validate names
            const first_name = formatAndValidateName(form.first_name.value);
            const middle_name = formatAndValidateName(form.middle_name.value) || '';
            const last_name = formatAndValidateName(form.last_name.value);
            const username = form.username.value.trim();
            const email = form.email.value.trim();
            const phone_number = form.phone_number.value.trim();
            const address = form.address.value.trim();
            const customer_type = form.customer_type.value;
            const loyalty_points = parseFloat(form.loyalty_points.value) || 0;
            const password = form.password.value;

            if(!first_name || !last_name) return alert("Invalid Name (First/Last)! Only letters, spaces, hyphen, period, and apostrophe allowed.");
            if(!username || !email || !phone_number || !address || !customer_type || !password) return alert("Please fill in all required fields.");

            // Send to backend
            sendUserAction({
                action: 'add',
                role: 'Customer', 
                first_name,
                middle_name,
                last_name,
                username,
                email,
                phone_number,
                address,
                customer_type,
                loyalty_points,
                password
            }, res => {
                if(res.status === 'success'){
                    const fullName = `${first_name} ${middle_name} ${last_name}`.trim().replace(/\s+/g, ' ');
                    const newRow = document.createElement('tr');
                    newRow.dataset.id = res.user_id; // customer_id is returned as user_id
                    newRow.innerHTML = `
                        <td>${fullName}</td>
                        <td>${email}</td>
                        <td>${phone_number}</td>
                        <td>${loyalty_points}</td> 
                        <td class="action-btn-group">
                            <button class="edit-customer-btn" style="background: #1e90ff; padding: 6px 12px;">Edit</button>
                            <button class="delete-customer-btn" style="background: #e63946; padding: 6px 12px;">Deactivate</button>
                        </td>
                    `;
                    customerTable.prepend(newRow); 
                    
                    // Re-attach events for the entire table (covers new and existing)
                    attachEditEvents(staffTable, customerTable);
                    attachDeleteEvents(staffTable, customerTable);
                    
                    alert("Customer added successfully!");
                    modal.remove();
                } else {
                    alert(res.msg || "Error adding customer.");
                }
            });
        };
    });

    // --- Initialization ---
    // 1. Attach events to existing rows when the section is loaded
    attachEditEvents(staffTable, customerTable);
    attachDeleteEvents(staffTable, customerTable);

    // 2. Observer to reattach events to dynamically added rows
    const observeAndReattach = (table) => {
        new MutationObserver((mutationsList) => {
            let foundNewRow = false;
            for(const mutation of mutationsList) {
                if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                   foundNewRow = true; 
                }
            }
            if (foundNewRow) {
                // Re-attach events on the entire table to cover new elements
                attachEditEvents(staffTable, customerTable);
                attachDeleteEvents(staffTable, customerTable);
            }
        }).observe(table, {childList:true, subtree: true});
    };
    
    observeAndReattach(staffTable);
    observeAndReattach(customerTable);
}

// Check if the user-management section is the initial view and initialize if so
const userManagementSection = document.getElementById('user-management');
if (userManagementSection && userManagementSection.style.display === 'block') {
    initializeUserManagementEvents();
    // Assuming userManagementInitialized is defined globally or managed elsewhere
    // userManagementInitialized = true; 
}
});

</script>

<script>
// Monthly Sales Trend + Top Categories (year-aware)
let monthlySalesChartInstance = null;
let topCategoriesChartInstance = null;
const catColors = ['#2563eb','#f59e0b','#10b981','#8b5cf6','#ef4444','#f97316','#0ea5e9','#ec4899'];

function renderSalesCharts(labels, salesData, catLabels, catData) {
    const salesCtx = document.getElementById('monthlySalesChart')?.getContext('2d');
    if (salesCtx) {
        if (monthlySalesChartInstance) monthlySalesChartInstance.destroy();
        monthlySalesChartInstance = new Chart(salesCtx, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label: 'Sales',
                    data: salesData,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37,99,235,0.08)',
                    fill: true,
                    tension: 0.35,
                    borderWidth: 2.5,
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => '₱' + Number(ctx.parsed.y).toLocaleString('en-US', { minimumFractionDigits: 2 })
                        }
                    }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { callback: v => '₱' + Number(v).toLocaleString('en-US') } }
                }
            }
        });
    }

    const catCtx = document.getElementById('topCategoriesChart')?.getContext('2d');
    if (catCtx) {
        const catTotal = catData.reduce((a, b) => a + Number(b), 0);
        if (topCategoriesChartInstance) topCategoriesChartInstance.destroy();
        topCategoriesChartInstance = new Chart(catCtx, {
            type: 'doughnut',
            data: {
                labels: catLabels.length ? catLabels : ['No data'],
                datasets: [{
                    data: catData.length ? catData : [1],
                    backgroundColor: catColors,
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: { position: 'right' },
                    tooltip: {
                        callbacks: {
                            label: ctx => {
                                const pct = catTotal ? ((ctx.parsed / catTotal) * 100).toFixed(1) : 0;
                                return `${ctx.label}: ${ctx.parsed} (${pct}%)`;
                            }
                        }
                    }
                }
            }
        });
    }
}

renderSalesCharts(
    <?php echo json_encode($salesLabels); ?>,
    <?php echo json_encode($salesData); ?>,
    <?php echo json_encode($catLabels); ?>,
    <?php echo json_encode($catData); ?>
);

document.getElementById('analytics-year')?.addEventListener('change', function () {
    fetch('get_sales_analytics.php?year=' + this.value)
        .then(r => r.json())
        .then(d => renderSalesCharts(d.monthly_labels, d.monthly_data, d.category_labels, d.category_data))
        .catch(err => console.error('Analytics reload error:', err));
});
</script>

<script>
  // Grab all sidebar nav items and all main sections
  const navItems = document.querySelectorAll('.nav-item');
  const sections = document.querySelectorAll('section');

  navItems.forEach(item => {
      item.addEventListener('click', () => {
          // Remove 'active' class from all nav items
          navItems.forEach(i => i.classList.remove('active'));
          item.classList.add('active');

          // Hide all sections
          sections.forEach(sec => sec.style.display = 'none');

          // Show the targeted section
          const target = item.getAttribute('data-target');
          const targetSection = document.getElementById(target);
          if(targetSection) targetSection.style.display = 'block';
      });
  });

  // Optionally, show the dashboard section by default on page load
  window.addEventListener('DOMContentLoaded', () => {
      sections.forEach(sec => sec.style.display = 'none'); // hide all
      const defaultSection = document.getElementById('dashboard');
      if(defaultSection) defaultSection.style.display = 'block';
  });
</script>



<script>
// ===== REAL-TIME DASHBOARD =====
(function () {
    const periodLabels = { today: 'Today', month: 'This Month', year: 'This Year' };

    function tickClock() {
        const el = document.getElementById('live-clock');
        if (!el) return;
        const now = new Date();
        el.textContent = now.toLocaleString('en-PH', {
            weekday: 'long', year: 'numeric', month: 'short', day: 'numeric',
            hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true
        });
    }
    tickClock();
    setInterval(tickClock, 1000);

    function refreshActivityLogs() {
        fetch('get_activity_logs.php?limit=10')
            .then(r => r.json())
            .then(d => {
                const body = document.getElementById('activity-logs-body');
                if (!body) return;
                if (!d.logs || !d.logs.length) {
                    body.innerHTML = '<tr><td colspan="4" style="padding:15px;text-align:center;color:#6b7280;">No recent activities.</td></tr>';
                    return;
                }
                body.innerHTML = d.logs.map(log => `
                    <tr>
                        <td style="padding:12px 15px;border-bottom:1px solid #f1f5f9;">${log.date}</td>
                        <td style="padding:12px 15px;border-bottom:1px solid #f1f5f9;">${log.admin_name || ''}</td>
                        <td style="padding:12px 15px;border-bottom:1px solid #f1f5f9;">${log.action}</td>
                        <td style="padding:12px 15px;border-bottom:1px solid #f1f5f9;">${log.details}</td>
                    </tr>`).join('');
            })
            .catch(err => console.error('Logs refresh error:', err));
    }

    function refreshMovingItems() {
        fetch('get_moving_items.php')
            .then(r => r.json())
            .then(d => {
                const fast = document.getElementById('fast-moving-list');
                const slow = document.getElementById('slow-moving-list');
                if (fast) {
                    fast.innerHTML = (d.fast_moving || []).length
                        ? d.fast_moving.map(i => `<li>${i.name} — <strong>${i.qty_sold}</strong> sold (30d)</li>`).join('')
                        : '<li>No sales data yet</li>';
                }
                if (slow) {
                    slow.innerHTML = (d.slow_moving || []).length
                        ? d.slow_moving.map(i => `<li>${i.name} — ${i.on_hand} on hand</li>`).join('')
                        : '<li>No slow movers detected</li>';
                }
            })
            .catch(err => console.error('Moving items error:', err));
    }

    function refreshDashboardCards() {
        const period = document.getElementById('sales-period-filter')?.value || 'month';
        fetch('fetch_dashboard.php?period=' + period)
            .then(res => res.json())
            .then(data => {
                const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
                set('card-staff-count', data.staff_count);
                set('card-customer-count', data.customer_count);
                set('card-total-sales', Number(data.total_sales || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                set('card-supplier-count', data.supplier_count);
                set('card-inactive-suppliers', data.inactive_supplier_count);
                set('card-low-stock', data.low_stock);
                set('card-out-of-stock', data.out_stock);
                const periodEl = document.getElementById('sales-card-period');
                if (periodEl) periodEl.textContent = '(' + (data.period_label || periodLabels[period] || 'This Month') + ')';
                const labelEl = document.getElementById('sales-period-label');
                if (labelEl) labelEl.textContent = 'Showing sales for ' + (data.period_label || periodLabels[period] || 'this month').toLowerCase();
            })
            .catch(err => console.error('Dashboard card refresh error:', err));
    }

    document.getElementById('sales-period-filter')?.addEventListener('change', refreshDashboardCards);
    refreshDashboardCards();
    refreshActivityLogs();
    refreshMovingItems();
    setInterval(refreshDashboardCards, 15000);
    setInterval(refreshActivityLogs, 30000);
    setInterval(refreshMovingItems, 60000);
})();

// Dashboard cards — clickable navigation + tooltips
(function () {
    function openAdminSection(target, invFilter) {
        if (target === 'sales-analytics') {
            document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));
            document.querySelector('.nav-item[data-target="dashboard"]')?.classList.add('active');
            document.querySelectorAll('section').forEach(s => s.style.display = 'none');
            const dash = document.getElementById('dashboard');
            if (dash) dash.style.display = 'block';
            const analytics = document.getElementById('sales-analytics-section');
            if (analytics) analytics.scrollIntoView({ behavior: 'smooth', block: 'start' });
            return;
        }

        const nav = document.querySelector('.nav-item[data-target="' + target + '"]');
        if (nav) nav.click();

        if (target === 'inventory' && invFilter && typeof window.applyInventoryCardFilter === 'function') {
            setTimeout(() => window.applyInventoryCardFilter(invFilter), 350);
        }
    }

    document.querySelectorAll('.dash-card[data-nav]').forEach(card => {
        const go = () => openAdminSection(card.dataset.nav, card.dataset.invFilter || null);

        card.addEventListener('click', (e) => {
            if (e.target.closest('.dash-card-tip')) return;
            go();
        });
        card.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                go();
            }
        });
    });
})();
</script>
<script src="assets/theme.js"></script>
</body>
</html>