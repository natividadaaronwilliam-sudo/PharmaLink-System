<?php
session_start();

// SECURITY FIX: this page previously had no login/role check at all — it
// was reachable by anyone who typed the URL, logged in or not, and could
// process real sales against the database.
$__role = strtolower(trim($_SESSION['user_role'] ?? ''));
if (!isset($_SESSION['user_id']) || !in_array($__role, ['cashier/pharmacist', 'admin'], true)) {
    header('Location: index.php');
    exit;
}

// 1. Tiyakin na nag-iisa lang ang Database Connection
// Ang $conn variable ang gagamitin sa lahat ng queries.
require_once 'db_pharmacy.php'; 

// Tiyakin na ang user's first name ay naka-set
$user_first_name = $_SESSION['user_first_name'] ?? 'Cashier';

/* ============================================================
    1. PURCHASE FREQUENCY (Number of orders per customer)
    ============================================================ */
$freq_query = "
    SELECT 
        CONCAT(c.first_name, ' ', c.last_name) AS fullname,
        COUNT(o.order_id) AS order_count 
    FROM customers c
    LEFT JOIN customer_orders o ON c.customer_id = o.customer_id
    GROUP BY c.customer_id
    ORDER BY order_count DESC
";

$freq_result = $conn->query($freq_query);

$freq_labels = [];
$freq_data  = [];

if ($freq_result && $freq_result->num_rows > 0) {
    while ($row = $freq_result->fetch_assoc()) {
        $freq_labels[] = $row['fullname'];
        $freq_data[]  = (int)$row['order_count'];
    }
}

/* ============================================================
    2. CUSTOMER SPENDING TIER (Total spending per customer)
    ============================================================ */
$spend_query = "
    SELECT 
        c.customer_id,
        CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
        c.loyalty_points,
        SUM(co.total_amount) AS total_spent
    FROM customers c
    LEFT JOIN customer_orders co ON c.customer_id = co.customer_id
    GROUP BY c.customer_id
    ORDER BY total_spent DESC
";

$spend_result = $conn->query($spend_query);

$spend_labels = [];
$spend_data = [];
$spend_customers = []; // parallel array: customer_id, name, loyalty_points (same order as spend_data)

if ($spend_result && $spend_result->num_rows > 0) {
    while ($row = $spend_result->fetch_assoc()) {
        $spend_labels[] = $row['customer_name'];
        $spend_data[] = (float)$row['total_spent'];
        $spend_customers[] = [
            'customer_id' => (int)$row['customer_id'],
            'name' => $row['customer_name'],
            'loyalty_points' => (float)($row['loyalty_points'] ?? 0),
        ];
    }
}


/* ============================================================
    3. CUSTOMER TYPE DISTRIBUTION (Loyal vs Regular)
    (Ito ay dating nasa dulo ng code mo, inayos at pinangalanan)
    ============================================================ */
$type_query = "
    SELECT customer_type, COUNT(*) as total 
    FROM customers 
    WHERE is_active = 1 
    GROUP BY customer_type
";
$type_result = $conn->query($type_query);

$type_labels = [];
$type_data = [];

if ($type_result && $type_result->num_rows > 0) {
    while($row = $type_result->fetch_assoc()){
        $type_labels[] = $row['customer_type'];
        $type_data[] = (int)$row['total'];
    }
}

/* ============================================================
    3b. CUSTOMER SEGMENTATION KPIs + QUANTILE SEGMENTS
    Built from the real $spend_data queried above (customers ranked
    by actual total spend from customer_orders), instead of the
    hardcoded placeholder numbers this page used to show.
    ============================================================ */
$seg_total_customers = count($spend_labels);
$seg_active_customers = 0;
foreach ($spend_data as $amt) { if ($amt > 0) $seg_active_customers++; }
$seg_total_revenue = array_sum($spend_data);
$seg_avg_spend = $seg_active_customers > 0 ? $seg_total_revenue / $seg_active_customers : 0;

$seg_top_type = '—';
if (!empty($type_labels)) {
    $max_idx = array_keys($type_data, max($type_data))[0];
    $seg_top_type = $type_labels[$max_idx];
}

$segment_label_sets = [
    2 => ['High Spend', 'Low Spend'],
    3 => ['Loyal Customers', 'Occasional Buyers', 'Low Engagement'],
    4 => ['VIP', 'Regular', 'Occasional', 'Inactive'],
    5 => ['VIP', 'High', 'Medium', 'Low', 'Inactive'],
];
$segment_variants = [];
foreach ($segment_label_sets as $n => $labels) {
    $counts = array_fill(0, $n, 0);
    $sums = array_fill(0, $n, 0.0);
    $members = array_fill(0, $n, []);
    if ($seg_total_customers > 0) {
        // $spend_data is already ORDER BY total_spent DESC, so this splits
        // customers into N real quantile buckets by actual spend.
        foreach ($spend_data as $idx => $amt) {
            $bucket = (int) floor(($idx / $seg_total_customers) * $n);
            if ($bucket >= $n) $bucket = $n - 1;
            $counts[$bucket]++;
            $sums[$bucket] += (float)$amt;

            // Track the specific buyers clustered into this segment so the
            // "View" button can list them and let a cashier add loyalty points.
            $cust = $spend_customers[$idx] ?? null;
            if ($cust) {
                $members[$bucket][] = [
                    'customer_id' => $cust['customer_id'],
                    'name' => $cust['name'],
                    'total_spent' => round((float)$amt, 2),
                    'loyalty_points' => $cust['loyalty_points'],
                ];
            }
        }
    }
    $avgs = [];
    foreach ($counts as $i => $c) { $avgs[$i] = $c > 0 ? round($sums[$i] / $c, 2) : 0; }
    $segment_variants[$n] = ['labels' => $labels, 'counts' => $counts, 'avgSpend' => $avgs, 'members' => $members];
}

/* ============================================================
    4. STAFF PROFILE DATA (Para sa Profile Page)
    ============================================================ */
$cashier_user_id = $_SESSION['user_id'] ?? null; // I-rename natin para mas malinaw
$staff = [];

if ($cashier_user_id && isset($conn)) {
    // 1. Tiyakin na ang staff_info ay may data
    // 2. I-JOIN ang users table (na dapat ay naka-link sa role table)
    
    // Assuming: users table has a role_id column that links to role.role_id
    $sql_staff = "
        SELECT 
            si.first_name, si.middle_name, si.last_name, si.email, si.phone_number, si.address, si.profile_image,
            u.username
        FROM staff_info si
        JOIN users u ON si.user_id = u.user_id  /* Link staff_info to users table */
        JOIN role r ON u.role_id = r.role_id    /* Link users table to role table */
        WHERE si.user_id = ? AND r.role_name = 'Cashier/Pharmacist'
    ";
    
    $stmt = $conn->prepare($sql_staff);
    
    if ($stmt) {
        // I-bind ang $cashier_user_id (na naglalaman ng user_id)
        $stmt->bind_param("i", $cashier_user_id);
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
// HINDI na kailangan i-close ang $conn dito, gamitin lang ito sa dulo ng page.
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PharmaLink System - Cashier/Pharmacist Dashboard</title>
    <link rel="stylesheet" href="assets/theme.css">
    <link rel="stylesheet" href="cashier.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
<!-- SIDEBAR NAVIGATION -->
<div class="sidebar">
  <div class="sidebar-header">
    <br>
    <h2>PharmaLink</h2>
    <span class="role-badge">CASHIER</span>
  </div>
  <div class="sidebar-nav">
    <div class="nav-item active" data-page="dashboard-page">
      <i class="fas fa-home"></i><span>Dashboard</span>
    </div>
    <div class="nav-item" data-page="pos-page">
      <i class="fas fa-cash-register"></i><span>Point of Sale</span>
    </div>
    <div class="nav-item" data-page="customer-segmentation-page">
      <i class="fas fa-users"></i><span>Customer Segmentation</span>
    </div>
    <div class="nav-item" data-page="profile-page">
      <i class="fas fa-user-circle"></i><span>Profile</span>
    </div>
  </div>
  <a href="logout.php" class="nav-item" style="margin-top:auto; border-top:px solid rgba(255,255,255,0.04); color:white;">
    <i class="fas fa-sign-out-alt"></i><span>Logout</span> </a>
  </div>
  


<!-- MAIN CONTENT -->
    <div class="main">
<div class="header">
    <h3>Cashier/Pharmacy Portal</h3>
    <div class="header-right">
        <?php $notif_mode = 'staff'; require 'includes/notification_bell.php'; ?>
<span id="headerWelcomeName">Welcome, <?php echo htmlspecialchars($user_first_name); ?></span>    </div>
</div>

    <div class="content">

  

    
  <div id="dashboard-page" class="page active">
        <h2 style="color:#1e3a8a;">Dashboard Overview</h2>
        <p class="subtitle">Monitor daily sales, transactions, and inventory status.</p>
        
        <div class="date-filter-container" style="display: flex; gap: 15px; margin-top: 20px; align-items: center;">
            <label for="startDate">Start Date:</label>
            <input type="date" id="startDate" name="startDate" style="padding: 5px; border: 1px solid #ccc; border-radius: 4px;">
            
            <label for="endDate">End Date:</label>
            <input type="date" id="endDate" name="endDate" style="padding: 5px; border: 1px solid #ccc; border-radius: 4px;">
            
            <button id="applyFilterBtn" class="btn btn-primary" style="padding: 6px 15px; background-color: #667eea; color: white; border: none; border-radius: 4px; cursor: pointer;">Apply Filter</button>
        </div>

    <div class="stats-cards">
        <div class="stat-card" title="Number of completed transactions in the selected date range">
            <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
            <div class="stat-content">
                <h3 id="stat-transactions">0</h3><p>Total Transactions</p>
            </div>
        </div>
        <div class="stat-card" title="Total peso sales for the selected period">
            <div class="stat-icon"><i class="fas fa-dollar-sign"></i></div>
            <div class="stat-content">
                <h3 id="stat-sales">₱0.00</h3><p>Total Sales</p>
            </div>
        </div>
        <div class="stat-card" title="Total units/items sold (not just peso amount)">
            <div class="stat-icon"><i class="fas fa-pills"></i></div>
            <div class="stat-content">
                <h3 id="stat-items-sold">0</h3><p>Items Sold</p>
            </div>
        </div>
        <div class="stat-card" title="Active drugs with stock at or below minimum (excludes out-of-stock)">
            <div class="stat-icon"><i class="fas fa-warehouse"></i></div>
            <div class="stat-content">
                <h3 id="stat-low-stock">0</h3><p>Low Stock Items</p>
            </div>
        </div>
        <div class="stat-card" title="Active drugs with zero stock across all lots">
            <div class="stat-icon"><i class="fas fa-circle-xmark"></i></div>
            <div class="stat-content">
                <h3 id="stat-out-stock">0</h3><p>Out of Stock</p>
            </div>
        </div>
        <div class="stat-card" title="Online orders awaiting processing">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-content">
                <h3 id="stat-pending-orders">0</h3><p>Pending Orders</p>
            </div>
        </div>
    </div>
    <div class="dashboard-grid">
        <div class="chart-card">
            <h3>Daily Sales Performance</h3>
            <canvas id="dailySalesChart"></canvas>
        </div>

        <div class="chart-card">
            <h3>Top Selling Medicines</h3>
            <canvas id="topMedicinesChart"></canvas>
        </div>

<div class="table-card">
    <h3>Recent Transactions</h3>
    <div class="table-wrapper">
        <table>
            <thead>
<tr><th>Date</th><th>Time</th><th>Customer</th><th>Amount</th><th>Status</th></tr>            
<tbody id="recentTransactionsBody">
                </tbody>
        </table>
    </div>
</div>

        <div class="table-card">
            <h3>Pending Prescriptions</h3>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr><th>Patient</th><th>Medicine</th><th>Status</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>John Doe</td><td>Amoxicillin</td><td><span class="status pending">Pending</span></td><td><button class="btn-process">Process</button></td></tr>
                        <tr><td>Jane Smith</td><td>Paracetamol</td><td><span class="status in-progress">In Progress</span></td><td><button class="btn-complete">Complete</button></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        </div>
</div>
<div id="pos-page" class="page">
    <h2>Point of Sale</h2>
    <p>Process transactions and manage customer purchases efficiently.</p>

    <div class="pos-mode-selector" style="margin-bottom: 20px; display: flex; gap: 10px;">
        <button id="mode-products-btn" class="btn pos-mode-btn active-mode" 
style="padding: 10px 15px; border: none; border-radius: 5px; cursor: pointer;">            <i class="fas fa-cash-register"></i> **Standard Sale**
        </button>
        <button id="mode-orders-btn" class="btn pos-mode-btn" 
style="padding: 10px 15px; border: none; border-radius: 5px; cursor: pointer;">            <i class="fas fa-list-alt"></i> **Online Order Management** <span id="pendingOrdersBadge" style="background: red; color: white; padding: 3px 6px; border-radius: 50%; font-size: 0.8em; display: none;">0</span>
        </button>
    </div>
    <div class="pos-top" style="display: flex; gap: 30px; margin-top: 20px; flex-wrap: wrap;">
        
        <div class="pos-left" style="flex: 2; min-width: 300px; background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 3px 10px rgba(0,0,0,0.05);">
            
            <div id="standard-sale-content">
              <div id="posTableWrapper" style="overflow-x: auto;"> 
                <h3>Available Products</h3>
<div class="filter-row">
    <input type="text" id="searchInput" placeholder="Search products...">
    
    <select id="categoryFilter">

    </select>
</div>  
                <table style="width:100%; border-collapse: collapse;">
                    <thead style="background:#f4f4ff; text-align:left;">
                        <tr>
                            <th>Category</th>
                            <th>Brand Name</th>
                            <th>Generic Name</th>
                            <th>Dosage</th>
                            <th>Form</th>
                            <th>Stock</th>
                            <th>Price</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="posInventoryBody">
                        </tbody>
                </table>
              </div>
            </div>

            <div id="online-order-content" style="display: none;">
                <h3>Online Orders Queue</h3>
                <p class="subtitle">Review prescriptions, process orders, or load "Ready for Pickup" transactions.</p>
                <div class="table-wrapper">
                    <table id="onlineOrdersTable" style="width:100%; border-collapse: collapse; margin-top: 15px;">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="onlineOrdersBody">

                        </tbody>
                    </table>
                </div>
            </div>
            </div> 
        <div class="pos-right" style="flex: 1; min-width: 280px; background:#fff; padding:20px; border-radius:10px; box-shadow:0 3px 10px rgba(0,0,0,0.05);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <h3>Current Cart</h3>
                
                <button id="removeAllCartIcon" 
                        class="btn btn-danger btn-sm" 
                        style="background: none; border: 1px solid #ff4b5c; color: #ff4b5c; padding: 5px 8px; border-radius: 5px; cursor: pointer;" 
                        title="Remove All Items from Cart"
                        onclick="clearCart()">
                    <i class="fas fa-trash-can"></i> Clear Cart
                </button>
            </div>
            
            <p>
<div id="customer-inline" style="display: flex; align-items: center; justify-content: space-between; padding: 8px 0;">
    <span style="font-size: 1.1em; color: #555;">Customer: <strong id="selectedCustomer" style="color: #007bff;">Guest</strong></span>
    <button id="selectCustomerBtn" title="Select Customer" style="padding: 4px; border-radius: 50%; background: none; border: 1px solid #ccc;">
        <i class="fa fa-pencil-alt" style="font-size: 1.1em; color: #555;"></i>
    </button>
</div></p>

            <div id="customerModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; 
            background:rgba(0,0,0,0.5); justify-content:center; align-items:center;">
                <div style="background:white; padding:20px; border-radius:8px; width:300px; max-height:400px; overflow:auto;">
                    <h4>Select Customer (Optional)</h4>
                    <input type="text" id="customerSearch" placeholder="Type customer name..." style="width:100%; padding:5px; margin-bottom:10px;">
                    <ul id="customerList" style="list-style:none; padding:0; max-height:250px; overflow:auto;"></ul>
                    <button id="closeModal" style="margin-top:10px;">Close</button>
                </div>
            </div>

            <table style="width:100%; border-collapse: collapse; margin-bottom:10px;">
                <thead style="background:#f4f4ff;"></thead>
                <tbody id="cartTable"></tbody>
            </table>
            
            <div class="summary">
                <p>Total: <span id="total">₱0.00</span></p> 
                <div style="margin-bottom: 10px; padding: 5px; border: 1px solid #007bff; border-radius: 5px;">
                    <label for="scPwdToggle" style="font-weight: bold; color: #007bff; display: flex; align-items: center; gap: 5px; font-size: 0.9em;">
                        <input type="checkbox" id="scPwdToggle" style="width:16px; height:16px;">
                        Apply Senior Citizen/PWD Discount 
                    </label>
                </div>

                <p style="font-size:0.9em; color:#555;">Subtotal: <span id="subtotalDisplay">₱0.00</span></p>
                <p style="font-size:0.9em; color:#555;"><span id="taxLabel">Tax (0%):</span> <span id="taxDisplay" style="float:right;">₱0.00</span></p>
                <label>Discount (%):</label>
                <input type="number" id="discountInput" placeholder="Enter % off" min="0" max="100" value="0" style="width:100%; padding:6px; border-radius:5px; border:1px solid #ccc; margin-bottom:5px;">
                <p style="color:#d9534f;">Discount Applied: <span id="discountApplied">₱0.00</span></p>
                
                <label>Cash:</label>
                <input type="number" id="cashInput" placeholder="Enter amount" min="0" style="width:100%; padding:6px; border-radius:5px; border:1px solid #ccc; margin-bottom:5px;">
                <p>Change: <span id="change">₱0.00</span></p>
                <button id="confirmSale" style="width:100%; padding:8px; border:none; border-radius:6px; background:#667eea; color:white; cursor:pointer;">Confirm Sale</button>
            </div>
        </div>
        </div> 

        </div>
        
        
        <!-- CUSTOMER SEGMENTATION PAGE -->

<div id="customer-segmentation-page" class="page" style="font-family:'Segoe UI', Arial, sans-serif; background:#f5f7fb; padding:24px; margin:-20px; border-radius:8px;">

  <h2 style="color:#1e293b; margin:0 0 4px; font-size:26px; font-weight:700;">Customer Segmentation</h2>
  <p style="color:#64748b; margin:0 0 26px; font-size:14.5px;">Real-time customer trends, spending tiers, and segment breakdowns — computed directly from live order &amp; sales data.</p>

  <!-- KPI Row -->
  <div class="dashboard-cards" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px,1fr)); gap:18px; margin-bottom:22px;">
    <div class="card" style="padding:20px; border-radius:14px; box-shadow:0 4px 16px rgba(30,41,59,0.06); background:#fff; border:1px solid #eef0f4;">
      <i class="fas fa-users" style="color:#2563eb; font-size:20px;"></i>
      <h3 style="margin:10px 0 2px; font-size:13px; font-weight:600; letter-spacing:.3px; text-transform:uppercase; color:#94a3b8;">Total Customers</h3>
      <p style="margin:0; font-size:1.7em; font-weight:800; color:#1e293b;"><?= (int)$seg_total_customers ?></p>
    </div>
    <div class="card" style="padding:20px; border-radius:14px; box-shadow:0 4px 16px rgba(30,41,59,0.06); background:#fff; border:1px solid #eef0f4;">
      <i class="fas fa-user-check" style="color:#16a34a; font-size:20px;"></i>
      <h3 style="margin:10px 0 2px; font-size:13px; font-weight:600; letter-spacing:.3px; text-transform:uppercase; color:#94a3b8;">Active Buyers</h3>
      <p style="margin:0; font-size:1.7em; font-weight:800; color:#1e293b;"><?= (int)$seg_active_customers ?></p>
    </div>
    <div class="card" style="padding:20px; border-radius:14px; box-shadow:0 4px 16px rgba(30,41,59,0.06); background:#fff; border:1px solid #eef0f4;">
      <i class="fas fa-peso-sign" style="color:#f59e0b; font-size:20px;"></i>
      <h3 style="margin:10px 0 2px; font-size:13px; font-weight:600; letter-spacing:.3px; text-transform:uppercase; color:#94a3b8;">Avg. Spend / Customer</h3>
      <p style="margin:0; font-size:1.7em; font-weight:800; color:#1e293b;">₱<?= number_format($seg_avg_spend, 2) ?></p>
    </div>
    <div class="card" style="padding:20px; border-radius:14px; box-shadow:0 4px 16px rgba(30,41,59,0.06); background:#fff; border:1px solid #eef0f4;">
      <i class="fas fa-star" style="color:#8b5cf6; font-size:20px;"></i>
      <h3 style="margin:10px 0 2px; font-size:13px; font-weight:600; letter-spacing:.3px; text-transform:uppercase; color:#94a3b8;">Top Customer Type</h3>
      <p style="margin:0; font-size:1.7em; font-weight:800; color:#1e293b;"><?= htmlspecialchars($seg_top_type) ?></p>
    </div>
  </div>

  <!-- Segmentation controls + pie -->
  <div class="chart-card" style="background:#fff; border-radius:14px; box-shadow:0 4px 16px rgba(30,41,59,0.06); border:1px solid #eef0f4; padding:18px 22px; margin-bottom:16px;">
    <div style="display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:10px; margin-bottom:14px;">
      <h3 style="color:#1e293b; margin:0; font-size:16px; font-weight:700;">Customer Segments <span style="color:#94a3b8; font-weight:400; font-size:13px;">— by real spend</span></h3>
      <div class="ph-select-wrap" style="display:flex; gap:10px; align-items:center; background:#f1f5f9; padding:6px 6px 6px 14px; border-radius:8px;">
        <label for="segmentCountTop" style="font-weight:600; color:#475569; font-size:13px;">Segments</label>
        <select id="segmentCountTop" style="padding:6px 10px; border-radius:6px; border:1px solid #e2e8f0; background:#fff; cursor:pointer; font-size:13px; font-weight:600; color:#1e293b;">
          <option value="2">2 Segments</option>
          <option value="3" selected>3 Segments</option>
          <option value="4">4 Segments</option>
          <option value="5">5 Segments</option>
        </select>
      </div>
    </div>
    <div style="display:grid; grid-template-columns: minmax(0,1fr) minmax(0,1.15fr); gap:24px; align-items:center;">
      <div>
        <div style="position:relative; width:100%; height:350px; overflow:hidden;">
          <canvas id="customerSegmentationPie"></canvas>
        </div>
        <!-- Custom legend (replaces Chart.js's default one, which has no
             fixed box and was spilling out below the card) -->
        <div id="segmentLegend" style="display:flex; flex-wrap:wrap; justify-content:center; gap:14px; margin-top:16px; padding-top:16px; border-top:1px solid #f1f5f9;"></div>
      </div>
      <div class="table-wrap" style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; font-size:14px;">
          <thead>
            <tr style="text-align:left;">
              <th style="padding:10px 12px; color:#94a3b8; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; border-bottom:2px solid #f1f5f9;">Segment</th>
              <th style="padding:10px 12px; color:#94a3b8; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; border-bottom:2px solid #f1f5f9;">Customers</th>
              <th style="padding:10px 12px; color:#94a3b8; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; border-bottom:2px solid #f1f5f9;">Avg. Spend</th>
              <th style="padding:10px 12px; color:#94a3b8; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; border-bottom:2px solid #f1f5f9;">Action</th>
            </tr>
          </thead>
          <tbody id="segmentTableBody"></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Segment Members panel — shown after clicking "View" on a segment row.
       Lists the specific buyers clustered into that segment, and lets the
       cashier manually add loyalty points to any of them. -->
  <div id="segmentMembersPanel" class="chart-card" style="display:none; background:#fff; border-radius:14px; box-shadow:0 4px 16px rgba(30,41,59,0.06); border:1px solid #eef0f4; padding:18px 22px; margin-bottom:16px;">
    <div style="display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:10px; margin-bottom:14px;">
      <h3 style="color:#1e293b; margin:0; font-size:16px; font-weight:700;">
        Buyers in <span id="segmentMembersLabel">—</span>
      </h3>
      <button id="segmentMembersCloseBtn" type="button" style="background:#f1f5f9; border:none; color:#475569; font-weight:700; padding:6px 12px; border-radius:6px; cursor:pointer; font-size:13px;">Close</button>
    </div>
    <div class="table-wrap" style="overflow-x:auto;">
      <table style="width:100%; border-collapse:collapse; font-size:14px;">
        <thead>
          <tr style="text-align:left;">
            <th style="padding:10px 12px; color:#94a3b8; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; border-bottom:2px solid #f1f5f9;">Customer</th>
            <th style="padding:10px 12px; color:#94a3b8; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; border-bottom:2px solid #f1f5f9;">Total Spent</th>
            <th style="padding:10px 12px; color:#94a3b8; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; border-bottom:2px solid #f1f5f9;">Loyalty Points</th>
            <th style="padding:10px 12px; color:#94a3b8; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; border-bottom:2px solid #f1f5f9;">Add Points</th>
          </tr>
        </thead>
        <tbody id="segmentMembersBody"></tbody>
      </table>
    </div>
    <p id="segmentMembersMessage" style="text-align:center; font-weight:600; margin-top:10px;"></p>
  </div>

  <!-- Real data charts -->
  <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap:16px;">
    <div class="chart-card" style="background:#fff; border-radius:14px; box-shadow:0 4px 16px rgba(30,41,59,0.06); border:1px solid #eef0f4; padding:16px 18px;">
      <h3 style="color:#1e293b; margin:0 0 10px; font-size:14.5px; font-weight:700;">Customer Type Distribution</h3>
      <div style="position:relative; width:100%; height:350px; overflow:hidden;">
        <canvas id="customerTypeChart"></canvas>
      </div>
    </div>
    <div class="chart-card" style="background:#fff; border-radius:14px; box-shadow:0 4px 16px rgba(30,41,59,0.06); border:1px solid #eef0f4; padding:16px 18px;">
      <h3 style="color:#1e293b; margin:0 0 10px; font-size:14.5px; font-weight:700;">Purchase Frequency <span style="color:#94a3b8; font-weight:400; font-size:12px;">(orders / customer)</span></h3>
      <div style="position:relative; width:100%; height:350px; overflow:hidden;">
        <canvas id="purchaseFrequencyChart"></canvas>
      </div>
    </div>
    <div class="chart-card" style="background:#fff; border-radius:14px; box-shadow:0 4px 16px rgba(30,41,59,0.06); border:1px solid #eef0f4; padding:16px 18px;">
      <h3 style="color:#1e293b; margin:0 0 10px; font-size:14.5px; font-weight:700;">Spending Tier <span style="color:#94a3b8; font-weight:400; font-size:12px;">(total ₱ / customer)</span></h3>
      <div style="position:relative; width:100%; height:350px; overflow:hidden;">
        <canvas id="spendingTierChart"></canvas>
      </div>
    </div>
  </div>

</div>


<!-- CASHIER PROFILE PAGE (REPLACEMENT SECTION) -->
<div id="profile-page" class="page"
    style="
                padding:30px; 
                font-family:Arial, sans-serif; 
                /* Binalik sa default display (block) */
            ">

 <div class="profile-card" style="
   background:white; 
        padding:30px; 
        width:500px; /* Kailangan ng width para gumana ang margin: auto */
   border-radius:12px; 
        box-shadow:0 4px 15px rgba(0,0,0,0.08);
        
        margin: 0 auto; /* Eto ang magse-center */
    ">

    <!-- Header -->
    <div style="text-align:center; margin-bottom:25px;">
      <div style="position:relative; width:110px; margin:0 auto 10px auto;">
        <img id="profile_avatar" src="<?= !empty($staff['profile_image']) ? htmlspecialchars($staff['profile_image']) : 'https://cdn-icons-png.flaticon.com/512/2922/2922510.png' ?>"
             style="width:110px; height:110px; border-radius:50%; object-fit:cover; border:3px solid #e5e7eb;">
        <label for="profile_avatar_input" title="Change profile picture" style="
              position:absolute; bottom:0; right:0;
              background:#1e3a8a; color:white; width:32px; height:32px;
              border-radius:50%; display:flex; align-items:center; justify-content:center;
              cursor:pointer; box-shadow:0 2px 6px rgba(0,0,0,0.25); font-size:14px;">
          <i class="fas fa-camera"></i>
        </label>
        <input type="file" id="profile_avatar_input" accept="image/png,image/jpeg,image/webp,image/gif" style="display:none;">
      </div>

      <h2 style="margin:0; color:#1e3a8a;">
        <?= htmlspecialchars($staff['first_name'] ?? '') ?> <?= htmlspecialchars($staff['last_name'] ?? '') ?>
      </h2>

      <p style="margin:3px 0; color:#4b5563;">
        <?= htmlspecialchars($staff['middle_name'] ?? '') ?>
      </p>
    </div>

    <hr style="margin:20px 0; border-top:1px solid #e5e7eb;">

    <!-- Account Details -->
    <h3 style="color:#1e3a8a; margin-bottom:15px;">My Account Details</h3>

    <table id="profileTable" style="width:100%; border-collapse:collapse; font-size:14px; color:#374151;">
      <tr>
        <td><strong>First Name:</strong></td>
        <td><input type="text" id="first_name" class="p-input" value="<?= htmlspecialchars($staff['first_name'] ?? '') ?>" disabled></td>
      </tr>

      <tr style="background:#f9fafb;">
        <td><strong>Middle Name:</strong></td>
        <td><input type="text" id="middle_name" class="p-input" value="<?= htmlspecialchars($staff['middle_name'] ?? '') ?>" disabled></td>
      </tr>

      <tr>
        <td><strong>Last Name:</strong></td>
        <td><input type="text" id="last_name" class="p-input" value="<?= htmlspecialchars($staff['last_name'] ?? '') ?>" disabled></td>
      </tr>

      <tr style="background:#f9fafb;">
        <td><strong>Email:</strong></td>
        <td><input type="email" id="email" class="p-input" value="<?= htmlspecialchars($staff_display_email) ?>" disabled></td>
      </tr>

      <tr>
        <td><strong>Phone:</strong></td>
        <td><input type="text" id="phone_number" class="p-input" value="<?= htmlspecialchars($staff['phone_number'] ?? '') ?>" disabled></td>
      </tr>

      <tr style="background:#f9fafb;">
        <td><strong>Address:</strong></td>
        <td><textarea id="address" class="p-input" disabled><?= htmlspecialchars($staff['address'] ?? '') ?></textarea></td>
      </tr>
    </table>

    <!-- BUTTONS -->
    <div style="display:flex; gap:10px;">
      <button id="profile_editBtn" style="
          background:#1e3a8a; color:white; padding:10px; width:100%;
          border:none; border-radius:8px; cursor:pointer;">
        Edit Profile
      </button>

      <button id="saveBtn" style="
          background:#1e3a8a; color:white; padding:10px; width:100%;
          border:none; border-radius:8px; cursor:pointer; display:none;">
        Save Changes
      </button>

      <button id="profile_cancelBtn" style="
          background:#6b7280; color:white; padding:10px; width:100%;
          border:none; border-radius:8px; cursor:pointer; display:none;">
        Cancel
      </button>
    </div>

    <p id="message" style="text-align:center; margin-top:10px;"></p>

  </div><!-- /Account Details card -->

  <div class="profile-card" style="
        background:#fff7f7;
        border:1px solid #fecaca;
        padding:24px 30px;
        width:500px;
        margin: 24px auto 0 auto;
        border-radius:12px;
        box-shadow:0 4px 15px rgba(0,0,0,0.08);
      ">
    <h3 style="color:#b91c1c; margin-bottom:4px;"><i class="fas fa-lock" style="margin-right:8px;"></i>Change Password</h3>
    <p style="color:#7f1d1d; font-size:13px; margin:0 0 16px;">Separate from your account details above — this only changes your login password.</p>
    <table style="width:100%; border-collapse:collapse; font-size:14px; color:#374151; margin-bottom:12px;">
      <tr>
        <td style="padding:8px 4px; font-weight:bold; width:35%;">Current:</td>
        <td style="padding:8px 4px;"><input type="password" id="current_password" autocomplete="current-password" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:6px;"></td>
      </tr>
      <tr>
        <td style="padding:8px 4px; font-weight:bold;">New:</td>
        <td style="padding:8px 4px;"><input type="password" id="new_password" autocomplete="new-password" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:6px;"></td>
      </tr>
      <tr>
        <td style="padding:8px 4px; font-weight:bold;">Confirm:</td>
        <td style="padding:8px 4px;"><input type="password" id="confirm_password" autocomplete="new-password" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:6px;"></td>
      </tr>
    </table>
    <button id="changePasswordBtn" type="button" style="background:#dc2626; color:white; padding:10px; width:100%; border:none; border-radius:8px; cursor:pointer; margin-bottom:10px;">Update Password</button>
    <p id="password-message" style="text-align:center; font-weight:600;"></p>

  </div><!-- /Change Password card -->
</div>
<!-- /#profile-page -->


<!--- REVIEW ORDER MODAL -->

<div id="orderReviewModal" class="modal" style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; overflow:auto; background-color: rgba(0,0,0,0.4);">
    <div class="modal-content" style="background-color:#fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 600px;">
        <span class="close" onclick="document.getElementById('orderReviewModal').style.display='none'" style="color:#aaa; float:right; font-size:28px; font-weight:bold; cursor:pointer;">&times;</span>
        <h2 id="reviewOrderTitle">Review Order #ORD-XXX</h2>
        <p>Customer: <strong id="reviewCustomerName"></strong></p>
        <p>Status: <span id="reviewOrderStatus"></span></p>
        
        <hr>
        
        <h3>Items Ordered:</h3>
        <table id="reviewItemsTable" style="width:100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="border-bottom: 1px solid #ccc;">Product</th>
                    <th style="border-bottom: 1px solid #ccc; width: 60px;">Qty</th>
                    <th style="border-bottom: 1px solid #ccc; width: 80px;">Stock</th>
                    <th style="border-bottom: 1px solid #ccc; width: 80px;">Status</th>
                </tr>
            </thead>
            <tbody id="reviewItemsBody">
                </tbody>
        </table>

        <div id="prescriptionContainer" style="margin-top: 20px; text-align: center; border: 1px dashed #ccc; padding: 10px; display:none;">
            <h4>Prescription Image:</h4>
            <img id="prescriptionImage" src="" alt="Prescription" style="max-width: 100%; height: auto; cursor: pointer;">
        </div>

        <hr>

        <div style="text-align: right; margin-top: 20px;">
            <button id="processOrderBtn" class="btn-success" style="padding: 10px 20px; background: #5cb85c; color: white; border: none; cursor: pointer;">Set to Ready for Pickup</button>
            <button id="cancelOrderBtn" class="btn-danger" style="padding: 10px 20px; background: #d9534f; color: white; border: none; cursor: pointer; margin-left: 10px;">Cancel Order</button>
        </div>

        <p id="reviewMessage" style="color: red; margin-top: 10px;"></p>
    </div>
</div>


<script>
// =======================================================
// 1. GLOBAL VARIABLES & CONSTANTS (Available to ALL functions)
// =======================================================
let cart = []; 
const TAX_RATE = 0.12; // 12% Tax rate
let taxLabel;

// Declare variables that will hold the DOM elements
let tbody, cartTable, totalDisplay, subtotalDisplay, taxDisplay, cashInput, changeDisplay, discountInput, discountApplied, confirmSale;
let selectedCustomer = null;

let currentOnlineOrderId = null;

// =======================================================
// 2. HELPER FUNCTIONS (Need to be outside DOMContentLoaded)
// =======================================================

/**
 * Updates the quantity of an item in the cart array when the input field changes.
 * @param {HTMLElement} inputElement - The quantity input field that triggered the change.
 */
window.updateCartQty = function(inputElement) {
    const index = parseInt(inputElement.dataset.index);
    let newQty = parseInt(inputElement.value);
    
    const item = cart[index];
    const maxStock = item.max_stock; // Gamitin ang Original Max Stock mula sa cart item

    if (newQty < 1 || isNaN(newQty)) {
        newQty = 1;
        inputElement.value = 1;
    }
    
    // Check laban sa Original Max Stock
    if (newQty > maxStock) {
        alert("⚠️ Cannot exceed max stock of " + maxStock);
        newQty = maxStock;
        inputElement.value = maxStock;
    }

    // 1. I-update ang quantity sa cart array
    item.qty = newQty;
    
    // 2. VISUAL STOCK UPDATE: I-update ang stock sa table (index 5)
    const addBtn = document.querySelector(`.add-btn[data-lot-id="${item.lot_id}"]`);
    if (addBtn) {
        const row = addBtn.closest('tr');
        if (row && row.cells.length > 5) {
             const newRemainingStock = maxStock - newQty; // Computation laban sa max stock
             row.cells[5].textContent = newRemainingStock; 

             // I-disable ang button kung ubos na
             addBtn.disabled = (newRemainingStock <= 0);
             addBtn.style.opacity = (newRemainingStock <= 0) ? 0.5 : 1;
        }
    }
    
    // 3. Re-render ang cart
    updateCart(); 
};

/**
 * Fetches and renders the list of pending online orders to the onlineOrdersBody table.
 */
function fetchOnlineOrders() {
    const onlineOrdersBody = document.getElementById('onlineOrdersBody');
    const pendingOrdersBadge = document.getElementById('pendingOrdersBadge');
    
    if (!onlineOrdersBody || !pendingOrdersBadge) return; 

    // Display loading message
    onlineOrdersBody.innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 20px;">' + 
                                 '<i class="fas fa-spinner fa-spin"></i> Loading online orders...</td></tr>';
    
    // Tiyakin na tama ang path papunta sa PHP file
    fetch("get_online_orders.php") 
        .then(res => res.json())
        .then(data => {
            onlineOrdersBody.innerHTML = ""; // Clear loading message
            
            if (data.success && data.orders.length > 0) {
                let pendingCount = 0;
                
                data.orders.forEach(order => {
                    const statusClass = order.status_class;
                    let actionButton = '';
                    
                    if (statusClass.includes('pending') || statusClass.includes('processing')) {
                        actionButton = `<button class="btn-review" onclick="reviewOrder('${order.order_id}')" 
                                        style="background:#f0ad4e; color:white; border:none; padding:5px 10px; border-radius:3px; cursor:pointer;">
                                            Review
                                        </button>`;
                        pendingCount++;
                    } else if (statusClass.includes('ready')) {
                        actionButton = `<button class="btn-complete" onclick="loadOrderToCart('${order.order_id}')"
                                        style="background:#5cb85c; color:white; border:none; padding:5px 10px; border-radius:3px; cursor:pointer;">
                                            Load to Cart
                                        </button>`;
                        pendingCount++; 
                    } else {
                        actionButton = '—';
                    }

                    const row = `
                        <tr>
                            <td>${order.display_id}</td>
                            <td>${order.customer_name}</td>
                            <td><span class="status ${statusClass}" style="padding: 2px 8px; border-radius: 5px; font-size: 0.9em; background: #f4f4ff;">${order.status}</span></td>
                            <td>${actionButton}</td>
                        </tr>
                    `;
                    onlineOrdersBody.innerHTML += row;
                });
                
                // Update Badge
                if (pendingCount > 0) {
                    pendingOrdersBadge.textContent = pendingCount;
                    pendingOrdersBadge.style.display = 'inline';
                } else {
                    pendingOrdersBadge.style.display = 'none';
                }

            } else {
                onlineOrdersBody.innerHTML = `<tr><td colspan="4" style="text-align: center; padding: 20px;">No online orders currently pending or ready.</td></tr>`;
                pendingOrdersBadge.style.display = 'none';
            }
        })
        .catch(err => {
            console.error("Error fetching online orders:", err);
            onlineOrdersBody.innerHTML = `<tr><td colspan="4" style="color:red; text-align: center;">Failed to load data. Please check 'get_online_orders.php'.</td></tr>`;
            pendingOrdersBadge.style.display = 'none';
        });
}

// =======================================================
// 3. ONLINE ORDER MANAGEMENT FUNCTIONS
// =======================================================

/**
 * Switches the POS interface between Standard Sale and Online Order Management mode.
 * @param {string} mode - 'standard' or 'orders'
 */
function switchPOSMode(mode) {
    const standardContent = document.getElementById('standard-sale-content');
    const ordersContent = document.getElementById('online-order-content');
    const modeProductsBtn = document.getElementById('mode-products-btn');
    const modeOrdersBtn = document.getElementById('mode-orders-btn');

    if (mode === 'orders') {
        standardContent.style.display = 'none';
        ordersContent.style.display = 'block';
        modeProductsBtn.classList.remove('active-mode');
        modeOrdersBtn.classList.add('active-mode');
        // I-fetch/i-render dito ang listahan ng orders sa onlineOrdersBody
fetchOnlineOrders();

} else { // 'standard' mode
        standardContent.style.display = 'block';
        ordersContent.style.display = 'none';
        modeProductsBtn.classList.add('active-mode');
        modeOrdersBtn.classList.remove('active-mode');
    }
}


window.clearCart = function() {
    if (cart.length === 0) {
        return;
    }

    // 1. Recover stock for ALL items in the cart
    cart.forEach(item => {
        const lotId = item.lot_id;
        const maxStock = item.max_stock; // Ito ang ORIGINAL max stock (e.g., 5)
        
        const addBtn = document.querySelector(`.add-btn[data-lot-id="${lotId}"]`);
        
        if (addBtn) {
            // ⭐ CRITICAL FIX: I-set ang visual at data-stock pabalik sa Original Max Stock ⭐
            
            // I-update ang button's data attribute pabalik sa Original Max Stock
            addBtn.dataset.stock = maxStock;
            
            // Re-enable the button and reset styling
            addBtn.disabled = false;
            addBtn.style.opacity = 1;
            addBtn.style.cursor = 'pointer';

            // Update the visual stock text sa inventory table row
            const row = addBtn.closest('tr');
            if (row && row.cells.length > 5) {
                row.cells[5].textContent = maxStock; // Ibalik ang 5 sa stock column
            }
        }
    });

    // 2. Clear the cart array
    cart = [];
    
    // 3. Update the cart display, totals, and reset payment inputs
    updateCart(); 
    
    // Reset cash and change inputs
    if (cashInput) cashInput.value = '';
    if (changeDisplay) changeDisplay.textContent = '₱0.00';
    
    document.getElementById('selectedCustomer').textContent = 'Guest';
    selectedCustomer = null;

    alert("Cart has been cleared.");
};

/**
 * Nagpapakita ng resibo sa bagong bintana na may Print Button.
 * @param {object} responseData - Data mula sa transactions.php (para sa transaction_id).
 * @param {object} saleData - Data ng bentahan (para sa items, totals).
 * @param {Window} printWindow - Ang pre-opened window object.
 */
function printReceipt(responseData, saleData, printWindow) {
    const transactionId = responseData.transaction_id || 'N/A';
    const date = new Date().toLocaleString();
    
    // ⭐ FIX #1: GUMAMIT NG TAMANG LOGIC PARA SA CUSTOMER DISPLAY ⭐
    let customerDisplay;
    
    if (saleData.customer_id !== null) {
        // Kapag may customer ID (online order). I-check na ang saleData.customer_id ay hindi NULL
        customerDisplay = `Customer ID: ${saleData.customer_id}`; 
    } else {
        // Kapag Guest (walk-in)
        customerDisplay = `Customer: Guest`; 
    }
    
    // 1. Generate Items List HTML
    let itemsHtml = saleData.items.map(item => {
        // Ginamit ang saleData.items na may lot_id, hindi na kailangan ang global cart.
        // Pero kung kailangan mo pa rin ng name, gamitin natin ang lot_id.
        // Assume na tama ang 'cart' variable na may full item details.
        const cartItem = cart.find(c => c.lot_id === item.lot_id);
        const itemName = cartItem ? cartItem.name : 'Unknown Item'; 
        return `
            <tr>
                <td style="text-align: left;">${item.qty} x ₱${item.price.toFixed(2)}</td>
                <td style="text-align: left;">${itemName}</td>
                <td style="text-align: right;">₱${(item.price * item.qty).toFixed(2)}</td>
            </tr>
        `;
    }).join('');

    // 2. Construct Full Receipt HTML (MAY DAGDAG NA PRINT BUTTON)
    const receiptHtml = `
        <html>
        <head>
            <title>Receipt ${transactionId}</title>
            <style>
                body { font-family: 'Courier New', Courier, monospace; font-size: 11px; margin: 0; padding: 10px; }
                .receipt { width: 280px; margin: 0 auto; }
                .center { text-align: center; }
                .right { text-align: right; }
                table { width: 100%; border-collapse: collapse; margin-top: 5px; }
                th, td { padding: 2px 0; }
                .border-top { border-top: 1px dashed #000; }
                .border-bottom { border-bottom: 1px dashed #000; }
                @media print { 
                    .no-print { display: none; } 
                }
            </style>
        </head>
        <body>
            <div class="receipt">
                <div class="center">
                    <h4>PHARMALINK SYSTEM</h4>
                    <p>123 PLP St, Kapasigan, Pasig City<br>
                    TIN: 000-000-000-000</p>
                </div>
                <div class="border-top"></div>
                <p>Date: ${date}</p>
                <p>Trans ID: ${transactionId}</p>
                <p>${customerDisplay}</p> <!-- ⭐ FIX #2: GINAMIT NA ANG customerDisplay ⭐ -->
                <div class="border-bottom"></div>
                <table>
                    <thead>
                        <tr>
                            <th style="text-align: left; width: 30%;">QTY/Price</th>
                            <th style="text-align: left;">Description</th>
                            <th style="text-align: right; width: 25%;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${itemsHtml}
                    </tbody>
                </table>
                <div class="border-top"></div>
                <table>
                    <tr><td class="right">Subtotal:</td><td class="right">₱${saleData.subtotal.toFixed(2)}</td></tr>
                    <tr><td class="right">Discount:</td><td class="right">₱${saleData.discount_total.toFixed(2)}</td></tr>
                    <tr><td class="right">VAT:</td><td class="right">₱${saleData.tax_total.toFixed(2)}</td></tr>
                    <tr class="border-top">
                        <td class="right"><strong>TOTAL DUE:</strong></td>
                        <td class="right"><strong>₱${saleData.total_amount.toFixed(2)}</strong></td>
                    </tr>
                    <tr><td class="right">CASH:</td><td class="right">₱${saleData.cash_received.toFixed(2)}</td></tr>
                    <tr><td class="right">CHANGE:</td><td class="right">₱${saleData.change_amount.toFixed(2)}</td></tr>
                </table>
                <div class="border-bottom"></div>
                
                <div class="center no-print" style="margin-top: 20px;">
                    <button onclick="window.print()" style="padding: 10px 20px; cursor: pointer;">Print Receipt</button>
                    <button onclick="opener.location.reload(); window.close()" style="padding: 10px 20px; cursor: pointer;">Close</button>
                </div>

                <div class="center" style="margin-top: 10px;"><p>Thank you! Come again.</p></div>
            </div>
        </body>
        </html>
    `;

    // 3. Write to the Existing Window (NO AUTOMATIC PRINT)
    printWindow.document.write(receiptHtml);
    printWindow.document.close();
    printWindow.focus(); 
}

function loadTransactionHistory() {
    const historyTable = document.getElementById("historyTable");
    if (!historyTable) return;

    fetch("get_transactions.php") 
        .then(res => res.json())
        .then(transactions => {
            historyTable.innerHTML = ""; // Clear existing rows

            if (transactions.error) {
                 historyTable.innerHTML = `<tr><td colspan="5">Error: ${transactions.message}</td></tr>`;
                 return;
            }

            transactions.forEach(t => {
                const row = document.createElement("tr");
                row.innerHTML = `
                    <td>${t.transaction_id}</td>
                    <td>${t.date_created.split(' ')[0]}</td>
                    <td>₱${parseFloat(t.total_amount).toFixed(2)}</td>
                    <td>₱${parseFloat(t.cash_received).toFixed(2)}</td>
                    <td>₱${parseFloat(t.change_amount).toFixed(2)}</td>
                `;
                historyTable.appendChild(row);
            });
            
            if (transactions.length === 0) {
                 historyTable.innerHTML = `<tr><td colspan="5">No recent transactions found.</td></tr>`;
            }

        })
        .catch(err => {
            console.error("Error loading transactions:", err);
            historyTable.innerHTML = `<tr><td colspan="5">Failed to fetch transaction data.</td></tr>`;
        });
}


function calculateChange() {
    // Check if totalDisplay has been initialized
    if (!totalDisplay) return; 

    const total = parseFloat(totalDisplay.textContent.replace('₱','')) || 0; 
    const cash = parseFloat(cashInput.value) || 0;
    const change = cash - total;
    // Only display change if cash is sufficient
    changeDisplay.textContent = '₱' + (change >= 0 ? change.toFixed(2) : '0.00'); 
}

window.removeItem = function(index) {
    const removedItem = cart[index];
    const lotId = removedItem.lot_id;
    
    // 1. Remove the item from the cart array
    cart.splice(index, 1);
    
    // 2. Update the cart display (totals and rows)
    updateCart();

    // 3. VISUAL STOCK RECOVERY: Kalkulahin ang total quantity ng lot na ito na nasa cart pa
    const remainingInCart = cart.filter(item => item.lot_id === lotId)
                                .reduce((sum, item) => sum + item.qty, 0);

    const maxStock = removedItem.max_stock;
    const newRemainingStock = maxStock - remainingInCart;
    
    // 4. I-update ang visual stock sa inventory table
    const addBtn = document.querySelector(`.add-btn[data-lot-id="${lotId}"]`);
    if (addBtn) {
        const row = addBtn.closest('tr');
        if (row && row.cells.length > 5) {
             row.cells[5].textContent = newRemainingStock; 
        }

        // I-re-enable ang button kung may stock na ulit
        addBtn.disabled = (newRemainingStock <= 0);
        addBtn.style.opacity = (newRemainingStock <= 0) ? 0.5 : 1;
        addBtn.style.cursor = (newRemainingStock <= 0) ? 'not-allowed' : 'pointer';
    }
}
window.getCustomerIdForSale = function() {
    // You need access to the `selectedCustomer` variable. 
    // Since `selectedCustomer` is currently scoped *inside* DOMContentLoaded,
    // we need to make it global (see Step 2).
    
    // TEMPORARY FIX: Assume selectedCustomer is now a global variable (see Step 2)
    return selectedCustomer ? selectedCustomer.customer_id : null;
};

function updateCart() {
    if (!cartTable || !totalDisplay) return; // Basic safety check

    cartTable.innerHTML = "";
    let subtotalBeforeDiscount = 0; 
    
    // 1. Loop through cart to calculate base subtotal and build cart rows
    cart.forEach((item, index) => {
        const itemSubtotal = item.price * item.qty;
        subtotalBeforeDiscount += itemSubtotal;

        const displayPrice = `(₱${item.price.toFixed(2)})`;
        // Descriptive name fix is already applied when adding to cart
        const displayName = `${item.name} <span style="font-weight:normal; color:#666; font-size:0.9em;">${displayPrice}</span>`;
        
const row = `
    <tr>
        <td>${displayName}</td>
        <td>
            <input type="number" 
                   min="1" 
                   value="${item.qty}" 
                   style="width: 60px; text-align: center;"
                   data-index="${index}" 
                   onchange="updateCartQty(this)">
        </td>
        <td>₱${itemSubtotal.toFixed(2)}</td> 
        <td>
            <button onclick="removeItem(${index})" style="background:#ff4b5c;color:white;border:none;padding:4px 8px;border-radius:5px; cursor:pointer">X</button>
        </td>
    </tr>
`;
cartTable.innerHTML += row;
    });
    
    const scPwdChecked = document.getElementById("scPwdToggle").checked;
    let scDiscount = 0;
    let vatExemptSale = 0;
    let taxAmount = 0;
    let totalAfterDiscount = 0;

    if (scPwdChecked) {
        // Step 1: Compute VAT-exempt sale
        vatExemptSale = subtotalBeforeDiscount / (1 + TAX_RATE);

        // Step 2: Compute 20% SC/PWD discount
        scDiscount = vatExemptSale * 0.20;

        // Step 3: Amount collectible after SC discount
        totalAfterDiscount = vatExemptSale - scDiscount;

        // VAT is 0 for SC/PWD
        taxAmount = 0;

    } else {
        // Non-SC/PWD: Normal computation
        totalAfterDiscount = subtotalBeforeDiscount;

        // Compute VAT from total
        taxAmount = totalAfterDiscount * TAX_RATE / (1 + TAX_RATE);
    }

    // 3️⃣ Apply custom discount (%) on top of SC/PWD or normal total
    const discountPercent = parseFloat(discountInput.value) || 0;
    const discountAmount = totalAfterDiscount * (discountPercent / 100);
    const finalTotal = totalAfterDiscount - discountAmount;

    // 4️⃣ Update displays
    subtotalDisplay.textContent = '₱' + subtotalBeforeDiscount.toFixed(2);
    discountApplied.textContent = '₱' + (scDiscount + discountAmount).toFixed(2);
    taxDisplay.textContent = '₱' + taxAmount.toFixed(2);
    totalDisplay.textContent = '₱' + finalTotal.toFixed(2);

    calculateChange();
}


/**
 * Loads a 'Ready for Pickup' order's items into the Current Cart.
 * Ito ang ginagawa kapag nag-click ng "Load to Cart" ang Cashier.
 * @param {string} orderId - Ang ID ng online order (e.g., 4).
 */
window.loadOrderToCart = function(orderId) {
    
    // Tiyakin na ang online order ay na-fetch at ready for processing.
    fetch(`get_order_details.php?order_id=${orderId}`)
        .then(response => response.json())
        .then(orderData => { 
            if (!orderData.success) {
                alert("Error loading order items: " + orderData.message);
                return;
            }

            // 1. Clear the existing cart
            clearCart(); 

            // Customer Name formatting (OK)
            const rawName = orderData.customer_name;
            const formattedCustomerName = rawName
                .toLowerCase()
                .split(' ')
                .map(word => word.charAt(0).toUpperCase() + word.slice(1))
                .join(' ');

// Dapat i-store ang buong customer object (ID at Pangalan)
selectedCustomer = { 
    customer_id: orderData.customer_id, // Kinuha ang ID mula sa PHP
    name: formattedCustomerName         // Kinuha ang formatte

};
            currentOnlineOrderId = orderData.order_id;
            
document.getElementById('selectedCustomer').textContent = formattedCustomerName;
            // 3. Add order items to the cart
            orderData.items.forEach(item => {
                const orderedQty = parseInt(item.ordered_qty);
                const lotId = item.lot_inventory_id; // ⭐ IDINAGDAG ANG DEKLARASYON DITO ⭐
                
                // Tiyakin na ang keys ay tugma sa PHP (get_order_details.php) response at cart structure
                cart.push({
                    lot_id: lotId,
                    drug_id: item.drug_id,
                    name: item.brand_name + ' / ' + item.generic_name, 
                    price: parseFloat(item.price_per_unit), 
                    qty: orderedQty,
                    max_stock: parseInt(item.current_stock) 
                });
                
                // ⭐ INAYOS AT MAS MATATAG NA LOGIC PARA SA STOCK UPDATE ⭐
                try {
                    // Hahanapin ang element gamit ang lotId
                    const addBtn = document.querySelector(`.add-btn[data-lot-id="${lotId}"]`);

                    if (addBtn) {
                        const row = addBtn.closest('tr'); 

                        // Tiyakin na mayroong row at may 5th cell (index 4)
                        if (row && row.cells.length > 5) {
                            const stockCell = row.cells[5];
                          
                            // I-parse ang stock, default sa 0 kung hindi valid number
                            const currentDisplayStock = parseInt(stockCell.textContent) || 0; 
                            const newRemainingStock = currentDisplayStock - orderedQty;
                            
                            // Update ang stock cell
                            stockCell.textContent = newRemainingStock; 
                            
                            // Disable ang button
                            addBtn.disabled = (newRemainingStock <= 0);
                            addBtn.style.opacity = (newRemainingStock <= 0) ? 0.5 : 1;
                        }
                    }
                } catch (e) {
                    console.warn(`Warning: Could not safely update stock UI for lot ${lotId}. Error:`, e);
                }
                // ⭐ END OF REVISED LOGIC ⭐
            });
            
            // 4. Render ang data sa screen
            updateCart(); 

            // 5. I-switch ang view pabalik sa standard POS at magbigay ng feedback
            switchPOSMode('standard'); 
            alert(`Order #ORD-${orderId} successfully loaded to cart. Ready for payment.`);
            
        })
        .catch(err => {
            console.error("Error loading order for cart:", err);
            alert("Failed to load order to cart due to a network error. Check console for details.");
        });
}
/**
 * [CONCEPTUAL] Opens a modal/screen to review a pending order (e.g., check prescription).
 * @param {string} orderId 
 */
/**
 * Opens a modal/screen to review a pending order (e.g., check prescription).
 * @param {string} orderId - Ang ID ng online order.
 */
window.reviewOrder = function(orderId) {
    const modal = document.getElementById('orderReviewModal');
    const itemsBody = document.getElementById('reviewItemsBody');
    const messageP = document.getElementById('reviewMessage');
    const processBtn = document.getElementById('processOrderBtn');
    const cancelBtn = document.getElementById('cancelOrderBtn');
    
    // I-reset ang modal state
    itemsBody.innerHTML = '<tr><td colspan="4" style="text-align:center;">Loading...</td></tr>';
    messageP.textContent = '';
    processBtn.disabled = true;
    cancelBtn.disabled = true;

    document.getElementById('reviewOrderTitle').textContent = `Review Order #ORD-${orderId}`;
    modal.style.display = 'flex';

    fetch(`get_order_details.php?order_id=${orderId}`)
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                itemsBody.innerHTML = `<tr><td colspan="4" style="color:red; text-align:center;">Error: ${data.message}</td></tr>`;
                return;
            }

            document.getElementById('reviewCustomerName').textContent = data.customer_name;
            document.getElementById('reviewOrderStatus').textContent = data.status;
            
            // Handle Prescription Display
            const prescContainer = document.getElementById('prescriptionContainer');
            const prescImage = document.getElementById('prescriptionImage');
            if (data.prescription_path) {
                prescImage.src = data.prescription_path;
                prescContainer.style.display = 'block';
                prescImage.onclick = () => window.open(data.prescription_path, '_blank');
            } else {
                prescContainer.style.display = 'none';
            }

            itemsBody.innerHTML = '';
            let isReadyToProcess = true;

            data.items.forEach(item => {
                const orderedQty = parseInt(item.ordered_qty);
                const currentStock = parseInt(item.current_stock);
                let stockStatus = '';
                let statusColor = '';
                
                if (currentStock >= orderedQty) {
                    stockStatus = 'In Stock';
                    statusColor = 'green';
                } else if (currentStock > 0) {
                    stockStatus = `Low Stock (${currentStock} left)`;
                    statusColor = 'orange';
                    isReadyToProcess = false; // Hindi pwedeng i-process agad kung kulang
                } else {
                    stockStatus = 'Out of Stock';
                    statusColor = 'red';
                    isReadyToProcess = false; // Hindi pwedeng i-process
                }

                itemsBody.innerHTML += `
                    <tr>
                        <td style="text-align:left;">${item.brand_name} / ${item.generic_name}</td>
                        <td style="text-align:center;">${orderedQty}</td>
                        <td style="text-align:center;">${currentStock}</td>
                        <td style="text-align:center; color:${statusColor}; font-weight:bold;">${stockStatus}</td>
                    </tr>
                `;
            });

            // Set Button states
            if (isReadyToProcess) {
                processBtn.disabled = false;
                processBtn.textContent = 'Set to Ready for Pickup';
                messageP.textContent = 'All items are currently in stock.';
                messageP.style.color = 'green';
            } else {
                processBtn.disabled = true;
                processBtn.textContent = 'Cannot Process (Check Items)';
                messageP.textContent = '⚠️ WARNING: One or more items are out of stock or low stock.';
                messageP.style.color = 'red';
            }
            // Laging pwedeng i-cancel
            cancelBtn.disabled = false;

            // I-bind ang events sa buttons
            processBtn.onclick = () => updateOrderStatus(orderId, 'Ready for Pickup', modal);
            cancelBtn.onclick = () => updateOrderStatus(orderId, 'Cancelled', modal, true);

        })
        .catch(err => {
            console.error("Error fetching order details:", err);
            itemsBody.innerHTML = `<tr><td colspan="4" style="color:red; text-align:center;">Failed to load order details.</td></tr>`;
        });
}


// Function para kunin at i-display ang categories



// ----------------------------------------------------
// D. NEW HELPER FUNCTION: Update Order Status
// ----------------------------------------------------
/**
 * Updates the order status on the backend.
 * @param {string} orderId 
 * @param {string} newStatus 
 * @param {HTMLElement} modal - The modal element to close.
 * @param {boolean} isCancellation - Flag for cancellation message.
 */
function updateOrderStatus(orderId, newStatus, modal, isCancellation = false) {
    if (isCancellation && !confirm(`Are you sure you want to CANCEL Order #ORD-${orderId}? This cannot be undone.`)) {
        return;
    }

    // ⭐ DITO KA MAGPAPASA NG AJAX REQUEST SA ISANG PHP SCRIPT (e.g., update_order_status.php) ⭐
    // Ito ay conceptual fetch lang. Kailangan mo pang gawin ang update_order_status.php
    fetch('update_order_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: orderId, status: newStatus })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert(`Order #ORD-${orderId} successfully set to ${newStatus}.`);
            modal.style.display = 'none';
            // I-refresh ang order list sa likod ng modal
            fetchOnlineOrders(); 
        } else {
            alert(`Failed to update status: ${data.message}`);
        }
    })
    .catch(err => {
        console.error("Status update error:", err);
        alert("An error occurred during status update.");
    });
}

function loadCategories() {
    fetch('get_categories.php')
    .then(response => response.json())
    .then(categories => {

        const filterSelect = document.getElementById('categoryFilter');

        if (!filterSelect) {
            console.warn("Category dropdown not ready yet. Retrying in 200ms...");
            setTimeout(loadCategories, 200);
            return;
        }

        let optionsHTML = '<option value="">All Categories</option>';

        categories.forEach(category => {
            optionsHTML += `<option value="${category}">${category}</option>`;
        });

        filterSelect.innerHTML = optionsHTML;

        console.log("Categories loaded:", categories);
    })
    .catch(error => console.error("Error fetching categories:", error));
}


// Tawagin ang function kapag nag-load ang page
document.addEventListener('DOMContentLoaded', function() {

// O kung gumagamit ka ng jQuery:
// $(document).ready(loadCategories);

loadInventory();

});

document.querySelectorAll('.nav-item').forEach(nav => {
    nav.addEventListener('click', function () {
        let target = this.dataset.page;

        // If POS page is opened, load categories
if (target === "pos-page") {
    console.log("POS page activated → Loading categories & POS inventory...");
    loadCategories();
    loadPOSInventory();

        }
    });
});

// =======================================================
// 4. EVENT LISTENERS SETUP
// =======================================================
document.addEventListener('DOMContentLoaded', () => {
    // ... [existing DOMContentLoaded code here] ...

    // --- POS MODE SWITCH LISTENERS ---
    document.getElementById('mode-products-btn').addEventListener('click', () => {
        switchPOSMode('standard');
    });

    document.getElementById('mode-orders-btn').addEventListener('click', () => {
        switchPOSMode('orders');
    });

    // ... [rest of existing DOMContentLoaded code here] ...

    // I-initialize ang mode sa 'standard' pag-load ng page
    // switchPOSMode('standard'); // (optional, dahil default na ang CSS)
});

// =======================================================
// 3. DOM LOADED & EVENT BINDING
// =======================================================
document.addEventListener("DOMContentLoaded", function() {
    // Assign global variables to their DOM elements
    tbody = document.getElementById("posInventoryBody");
    cartTable = document.getElementById("cartTable");
    totalDisplay = document.getElementById("total");
    subtotalDisplay = document.getElementById("subtotalDisplay");
    taxDisplay = document.getElementById("taxDisplay");
    changeDisplay = document.getElementById("change");
    cashInput = document.getElementById("cashInput");
    discountInput = document.getElementById("discountInput");
    discountApplied = document.getElementById("discountApplied");
    confirmSale = document.getElementById("confirmSale");
    taxLabel = document.getElementById("taxLabel"); // Assign the new DOM element

    // ===== INPUT EVENT BINDING =====
    discountInput.addEventListener("input", updateCart);
    cashInput.addEventListener("input", calculateChange);

    // ===== SC/PWD TOGGLE =====
    const scPwdToggle = document.getElementById("scPwdToggle");
    scPwdToggle.addEventListener("change", updateCart);

    loadTransactionHistory();

    // Siyasatin ang IDs:
    const startDateInput = document.getElementById('startDate');
    const endDateInput = document.getElementById('endDate');
    const applyFilterBtn = document.getElementById('applyFilterBtn');
    
    // Tiyakin na ang tatlong ito ay NAKITA (hindi null)
    if (startDateInput && endDateInput && applyFilterBtn) {
        
console.log("Dashboard Inputs Found. Setting defaults...");
        
        // 1. Set default dates to today
        const today = new Date().toISOString().split('T')[0];
        startDateInput.value = today;
        endDateInput.value = today;
        
        // 2. Initial load ng dashboard data
        console.log("Calling handleDateFilter() for initial load...");
        handleDateFilter(); 

        // 3. Add listener for the button
        applyFilterBtn.addEventListener('click', handleDateFilter);
    } else {
        console.error("CRITICAL: One or more Dashboard IDs (startDate, endDate, applyFilterBtn) were not found in the HTML.");
    }

const calculatedTaxRate = (TAX_RATE * 100).toFixed(0); // e.g., 12
    taxLabel.textContent = `Tax (${calculatedTaxRate}%):`;

// ===== LOAD PRODUCTS =====
// Wrapped in a named function (was a one-shot inline fetch) so it can also
// be called again right after a successful sale — that's what keeps the
// on-screen stock numbers accurate against the real database instead of
// relying only on the client-side "visual" decrement, which could drift
// out of sync if another cashier or an online order sells the same item.
function loadPOSProducts() {
fetch("get_products.php")
    .then(res => res.json())
    .then(products => {
        tbody.innerHTML = "";

        products.forEach(p => {
            const stock = parseInt(p.current_stock);
            const row = document.createElement("tr");

            // ✅ FIX: ADD DATA ATTRIBUTE FOR FILTERING
            row.dataset.category = p.category;

            row.innerHTML = `
                <td>${p.category}</td>
                <td>${p.brand_name}</td>
                <td>${p.generic_name}</td>
                <td>${p.dosage}</td>
                <td>${p.form}</td>
                <td>${p.current_stock}</td>

                <td>₱${parseFloat(p.price).toFixed(2)}</td>
                <td>

                        <button class="add-btn"
                            data-drug-id="${p.drug_id}"
                            data-lot-id="${p.lot_inventory_id}"
                            data-name="${p.brand_name} / ${p.generic_name} ${p.dosage} ${p.form}"
                            data-price="${p.price}"
                            data-stock="${stock}"
                            ${stock <= 0 ? "disabled style='opacity:0.5;cursor:not-allowed;'" : ""}
                        >Add</button>
                    </div>
                </td>
            `;
            tbody.appendChild(row);
        });

        // add
document.querySelectorAll(".add-btn").forEach(btn => {
    btn.addEventListener("click", () => {
        const lotId = btn.dataset.lotId;
        // ⭐ CRITICAL: Gamitin ang data-stock bilang ORIGINAL_MAX_STOCK ⭐
        const maxStock = parseInt(btn.dataset.stock);
        const name = btn.dataset.name;
        const price = parseFloat(btn.dataset.price);

        const existing = cart.find(item => item.lot_id === lotId);
        const currentQtyInCart = existing ? existing.qty : 0;
        
        // 1. Check laban sa Original Max Stock (e.g., 5)
        if (currentQtyInCart >= maxStock) {
            btn.disabled = true;
            btn.style.opacity = 0.5;
            alert("⚠️ Only " + maxStock + " in stock — cannot add more.");
            return;
        }

        if (existing) {
            existing.qty++;
        } else {
            cart.push({
                drug_id: btn.dataset.drugId,
                lot_id: lotId,
                name,
                price,
                qty: 1,
                max_stock: maxStock // Store the maximum stock for validation
            });
        }

        updateCart();

        // 2. VISUAL STOCK UPDATE: I-update ang stock sa table (index 5)
        const row = btn.closest('tr');
        if (row && row.cells.length > 5) {
            const newVisualStock = maxStock - (currentQtyInCart + 1);
            row.cells[5].textContent = newVisualStock; 
        }

        // 3. I-disable ang button kung ubos na
        if ((currentQtyInCart + 1) >= maxStock) {
            btn.disabled = true;
            btn.style.opacity = 0.5;
        }
    });
});
    })
    .catch(err => console.error("Error loading products:", err));
}
loadPOSProducts();
             // If fetch fails, this alert helps diagnose:
             // alert("Failed to load products. Check console for details.");


// ===== CONFIRM SALE LOGIC (Updated) =====
// ===== CONFIRM SALE LOGIC (Updated for Receipt) =====
confirmSale.addEventListener("click", () => {
    // Read final values from the display (which is updated by updateCart)
    const total = parseFloat(totalDisplay.textContent.replace('₱','')) || 0;
    const cash = parseFloat(cashInput.value) || 0;
    const tax = parseFloat(taxDisplay.textContent.replace('₱','')) || 0; 
    const appliedDiscount = parseFloat(discountApplied.textContent.replace('₱','')) || 0; 
    
    // CRITICAL FIX #1: TIYAKIN NA LAGING MAY NAME AT ID ANG CUSTOMER!
    const customerId = selectedCustomer ? selectedCustomer.customer_id : null;
    const customerName = selectedCustomer ? selectedCustomer.name : "Guest"; 

    // Calculate change here for use in saleData
    const change = cash - total;


    if (cart.length === 0) { 
        alert("No items in cart."); 
        return; 
    }
    if (cash < total) { 
        alert("Insufficient cash. Required: ₱" + total.toFixed(2)); 
        return; 
    }

    // 1. PRE-OPEN THE RECEIPT WINDOW HERE (CRITICAL FOR POP-UP BLOCKERS)
    // Center the receipt popup window on the screen instead of letting the
    // browser place it wherever (usually top-left).
    const receiptW = 300, receiptH = 500;
    const receiptLeft = Math.max(0, Math.round((window.screen.width - receiptW) / 2));
    const receiptTop = Math.max(0, Math.round((window.screen.height - receiptH) / 2));
    const printWindow = window.open('', '_blank', `width=${receiptW},height=${receiptH},left=${receiptLeft},top=${receiptTop}`);
    if (!printWindow) {
        alert("Please allow pop-ups to print the receipt. Transaction aborted.");
        return; 
    }

    // Default content habang naghihintay ng server response
    printWindow.document.write("<html><body><p>Processing transaction...</p></body></html>");
    printWindow.document.close();

    // Prepare sale data for backend
    const saleData = {
        customer_id: customerId,
        customer_name: customerName, 
        user_id: 1, // placeholder
        subtotal: parseFloat(subtotalDisplay.textContent.replace('₱','')) || 0,
        discount_total: appliedDiscount,
        tax_total: tax,
        total_amount: total, // Final Total Due
        cash_received: cash,
        change_amount: change, 
        payment_method: "cash",
        items: cart.map(item => ({
            drug_id: item.drug_id,
            lot_id: item.lot_id,
            qty: item.qty,
            price: item.price,
            subtotal: item.price * item.qty, 
            discount_amount: item.discount_amount || 0,
            promo_name: item.promo_name || null,
            vat_exempt: item.vat_exempt ? 1 : 0
        }))
    };

    // Send to backend
    fetch("transactions.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(saleData)
    })
    .then(res => {
        if (!res.ok) {
            return res.text().then(text => {
                throw new Error("Server Response Error: " + text.substring(0, 200));
            });
        }
        return res.text();
    })
    .then(text => {
        let data;
        try {
            const cleanText = text.trim(); 
            data = JSON.parse(cleanText); 
        } catch (e) {
            throw new Error("JSON Parsing Failed. Raw Response: [" + text.substring(0, 100) + "]");
        }

        if (data.status === "success") {
            alert("Transaction Successful!\nChange: ₱" + change.toFixed(2));

            // TAWAGIN ANG PRINT FUNCTION
            printReceipt(data, saleData, printWindow); 
            
            // I-clear ang cart at i-reset ang POS state
            clearCart(); 
            loadTransactionHistory();

            // Pull the real, current stock from the database now that the
            // sale has been committed — keeps the POS grid accurate even
            // if another cashier or an online order affected the same items.
            loadPOSProducts();

            // ⭐ ONLINE ORDER FINALIZATION LOGIC ⭐
            if (currentOnlineOrderId !== null) {
                
                // I-update ang status ng order sa Completed
                fetch('update_order_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        order_id: currentOnlineOrderId, 
                        status: 'Completed' 
                    })
                })
                .then(res => res.json())
                .then(updateData => {
                    if (updateData.success) {
                        console.log(`Online Order #${currentOnlineOrderId} marked as Completed.`);
                        
                        // I-REFRESH ANG ONLINE ORDER QUEUE UI
                        if (typeof fetchOnlineOrders === 'function') {
                            fetchOnlineOrders(); 
                        }

                        // I-reset ang global variable
                        currentOnlineOrderId = null; 
                        
                    } else {
                        console.error("Failed to update Online Order status:", updateData.message);
                    }
                })
                .catch(err => {
                    console.error("Network error during Online Order status update:", err);
                });
            }
            
        } else {
            let errorMsg = "Error saving transaction: " + data.message;
            if (data.db_error) {
                errorMsg += "\nDB Error: " + data.db_error.substring(0, 100) + "...";
            }
            alert(errorMsg);
            printWindow.close(); 
        }
    })
    .catch(err => {
        console.error("Fetch error:", err);
        alert("CRITICAL ERROR: " + err.message); 
        if(printWindow) printWindow.close(); 
    });
});



  

 // ===== PAGE NAVIGATION (REVISED) =====
const navItems = document.querySelectorAll('.nav-item');
const pages = document.querySelectorAll('.page');

navItems.forEach(item => {
    item.addEventListener('click', () => {
        const target = item.dataset.page;

        // Switch active tab
        navItems.forEach(n => n.classList.remove('active'));
        item.classList.add('active');

        // Switch visible page
        pages.forEach(p => p.classList.remove('active'));
        document.getElementById(target).classList.add('active');

        // 🔥 CRITICAL: Run loaders/functions based on the target page
        if (target === "pos-page") {
            setTimeout(() => {
                console.log("POS page is now active → loading categories & inventory...");
                // Tiyaking defined ang loadCategories() at loadPOSInventory()
                loadCategories();
                loadPOSInventory();
            }, 10); // small delay to allow rendering
        } 
        
        // ⭐ BAGONG DAGDAG: LOAD DASHBOARD DATA
        else if (target === "dashboard-page") {
            console.log("Dashboard page is now active → loading stats and charts...");
            // Tiyaking defined ang handleDateFilter() at ang mga input IDs ay tama
            handleDateFilter(); 
        }

        // 🔧 FIX: the Customer Segmentation charts are NOT built at page
        // load anymore (that's what caused the cut-off/overlapping donut —
        // Chart.js was measuring a 0-size box since the tab was hidden).
        // They're now created lazily, right here, the first time this tab
        // is actually visible and has its real, final size.
        else if (target === "customer-segmentation-page") {
            setTimeout(() => {
                if (window.initSegmentationCharts) window.initSegmentationCharts();
                if (window.kmeansPieChart) window.kmeansPieChart.resize();
                if (window.customerTypeChartInstance) window.customerTypeChartInstance.resize();
                if (window.purchaseFrequencyChartInstance) window.purchaseFrequencyChartInstance.resize();
                if (window.spendingTierChartInstance) window.spendingTierChartInstance.resize();
            }, 10);
        }
    });
});


  // ===== CHARTS =====
  // NOTE: dailySalesChart and topMedicinesChart are initialized with REAL
  // data by renderDailySalesChart()/renderTopMedicinesChart() below, called
  // from handleDateFilter(). (This used to double-initialize fake charts
  // here first, which both wasted a render and, for topMedicinesChart,
  // could throw a "canvas already in use" error once the real chart tried
  // to take over the same canvas.)


    const selectBtn = document.getElementById("selectCustomerBtn");
  const modal = document.getElementById("customerModal");
  const customerList = document.getElementById("customerList");
  const searchInput = document.getElementById("customerSearch");
  const selectedCustomerSpan = document.getElementById("selectedCustomer");
  const closeModal = document.getElementById("closeModal");

  let customers = [];

  // Fetch customers from database
  fetch("get_customers.php")
    .then(res => res.json())
    .then(data => { customers = data; renderCustomerList(customers); })
    .catch(err => console.error("Error loading customers:", err));

  // Open modal
  selectBtn.addEventListener("click", () => {
    modal.style.display = "flex";
    renderCustomerList(customers);
    searchInput.value = "";
    searchInput.focus();
  });

  // Close modal
  closeModal.addEventListener("click", () => {
    modal.style.display = "none";
  });

  // Render list
  function renderCustomerList(list) {
    customerList.innerHTML = "";
    if (list.length === 0) {
      customerList.innerHTML = "<li style='padding:5px;'>No customer found</li>";
      return;
    }

    list.forEach(c => {
      const li = document.createElement("li");
      li.textContent = c.name;
      li.style.padding = "8px";
      li.style.cursor = "pointer";
      li.style.borderBottom = "1px solid #eee";
      li.addEventListener("click", () => {
        selectedCustomer = c;
        selectedCustomerSpan.textContent = c.name;
        modal.style.display = "none";
      });
      customerList.appendChild(li);
    });
  }

  /**
 * Renders the Daily Sales line chart using real, last-7-days totals.
 */
function renderDailySalesChart() {
    const ctx = document.getElementById('dailySalesChart');
    if (!ctx || typeof Chart === 'undefined') return;

    fetch('get_daily_sales.php')
        .then(res => res.json())
        .then(data => {
            if (window.dailySalesChartInstance) {
                window.dailySalesChartInstance.destroy();
            }
            window.dailySalesChartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{ label: 'Sales (₱)', data: data.data, borderColor: '#667eea', backgroundColor: 'rgba(102,126,234,0.1)', fill: true, tension: 0.3 }]
                },
                options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
            });
        })
        .catch(err => console.error('Failed to load daily sales:', err));
}

/**
 * Renders the Top Selling Medicines Bar Chart.
 */
function renderTopMedicinesChart(start, end) {
    const ctx = document.getElementById('topMedicinesChart');
    // NOTE: Tiyaking naka-link ang Chart.js sa HTML mo
    if (!ctx || typeof Chart === 'undefined') {
        console.warn("Chart.js or canvas 'topMedicinesChart' not available.");
        return;
    }
    
    // Destroy existing chart instance (para hindi mag-overlap)
    if (window.topMedicinesChartInstance) {
        window.topMedicinesChartInstance.destroy();
    }

    const apiEndpoint = `top_selling_data.php?startDate=${start}&endDate=${end}`;

    fetch(apiEndpoint)
        .then(response => {
             // Tiyakin na nagbabalik ng JSON ang PHP file
            if (!response.ok) {
                console.error("top_selling_data.php returned non-OK status.");
                return response.text().then(text => { throw new Error(text); });
            }
            return response.json();
        })
        .then(data => {
            const labels = data.map(item => `${item.brand_name} / ${item.generic_name}`);
            const quantities = data.map(item => item.total_quantity_sold);
            const cardHeader = ctx.closest('.chart-card')?.querySelector('h3') || document.getElementById('topMedicinesHeader');
            
            if (data.length === 0) {
                ctx.style.display = 'none';
                if (cardHeader) cardHeader.textContent = 'Top Selling Medicines (No Sales Found)';
                return;
            } else {
                ctx.style.display = 'block';
                if (cardHeader) cardHeader.textContent = 'Top Selling Medicines';
            }

            window.topMedicinesChartInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Quantity Sold',
                        data: quantities,
                        backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b'], 
                        hoverBackgroundColor: '#2e59d9',
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    scales: {
                        x: { beginAtZero: true, title: { display: true, text: 'Units Sold' } }
                    },
                    plugins: {
                        legend: { display: false },
                        title: { display: false }
                    }
                }
            });
        })
        .catch(error => {
            console.error('Fetch error for Top Selling Data. Check PHP/MySQLi code:', error);
        });
}

/**
 * Fetches dashboard stats and updates the four stat cards.
 */
function updateDashboardStats(start, end) {
    const apiEndpoint = `dashboard_data.php?startDate=${start}&endDate=${end}`; 

    fetch(apiEndpoint)
        .then(response => {
            if (!response.ok) {
                console.error("dashboard_data.php returned non-OK status.");
                return response.text().then(text => { throw new Error(text); });
            }
            return response.json();
        })
        .then(data => {
            const formatter = new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2 });
            
            // Update Stat Cards (TIYAKING TAMA ANG IDs NA ITO SA HTML)
            if(document.getElementById('stat-transactions')) document.getElementById('stat-transactions').textContent = data.transaction_count || '0'; 
            if(document.getElementById('stat-sales')) document.getElementById('stat-sales').textContent = '₱' + formatter.format(data.total_sales || 0); 
            if(document.getElementById('stat-items-sold')) document.getElementById('stat-items-sold').textContent = data.items_sold || '0';
            if(document.getElementById('stat-low-stock')) document.getElementById('stat-low-stock').textContent = data.low_stock_count || '0'; 
            if(document.getElementById('stat-out-stock')) document.getElementById('stat-out-stock').textContent = data.out_of_stock_count || '0';
            if(document.getElementById('stat-pending-orders')) document.getElementById('stat-pending-orders').textContent = data.pending_orders || '0';
        })
        .catch(error => {
            console.error('Fetch error for Dashboard Stats. Check PHP/MySQLi code:', error);
        });
}

/**
 * Fetches and renders the 5 most recent transactions.
 * Gumagamit ng start at end date mula sa filter.
 */
function loadRecentTransactions(start, end) {
    const tableBody = document.getElementById('recentTransactionsBody'); // Titingnan natin ito mamaya
    if (!tableBody) return;

    const apiEndpoint = `recent_transactions.php?startDate=${start}&endDate=${end}`;

    fetch(apiEndpoint)
        .then(response => {
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            return response.json();
        })
        .then(transactions => {
            let html = '';
            
if (transactions.length === 0) {
                // Tiyaking 5 ang colspan (4 dati + 1 bagong Date column)
                html = '<tr><td colspan="5" style="text-align: center;">No transactions found for this period.</td></tr>';
            } else {
                transactions.forEach(t => {
                    const statusClass = t.status.toLowerCase().replace(' ', '-');

const transactionDate = new Date(t.date_created).toLocaleDateString('en-US', {
                        month: 'short', 
                        day: 'numeric', 
                        year: 'numeric' // O '2-digit' kung gusto mo 25
                    });

                    html += `
                        <tr>
                            <td>${transactionDate}</td> 
                            <td>${t.time_display}</td>
                            <td>${t.customer_name}</td>
                            <td>₱${t.total_amount}</td>
                            <td><span class="status ${statusClass}">${t.status}</span></td>
                        </tr>
                    `;
                });
            }
            tableBody.innerHTML = html;
        })
        .catch(error => {
            console.error('Error loading recent transactions:', error);
tableBody.innerHTML = '<tr><td colspan="5" style="color: red;">Failed to load data.</td></tr>';        });
}

// SA LOOB NG IYONG JS CODE
function handleDateFilter() {
    const startDate = document.getElementById('startDate')?.value;
    const endDate = document.getElementById('endDate')?.value;
    
    if (startDate && endDate) {
        // 1. Stat Cards
        updateDashboardStats(startDate, endDate); 
        
        // 2. Top Selling Chart
        renderTopMedicinesChart(startDate, endDate);

        // 3. Daily Sales Chart (real last-7-days data)
        renderDailySalesChart();
        
        // ⭐ BAGONG DAGDAG: Recent Transactions
        loadRecentTransactions(startDate, endDate); 
    }
}

// ===== REAL-TIME DASHBOARD REFRESH =====
// Re-runs the same real DB-backed fetches every 15s, but only while the
// Dashboard page is actually the one showing, so a sale rung up elsewhere
// (or by this same cashier) is reflected without a manual refresh.
setInterval(() => {
    const dashboardPage = document.getElementById('dashboard-page');
    const isVisible = dashboardPage && dashboardPage.style.display !== 'none' && dashboardPage.classList.contains('active');
    if (isVisible) handleDateFilter();
}, 15000);


// ===== REAL-TIME ONLINE ORDERS QUEUE =====
// Polls the online orders list + badge every 10s so a new customer checkout,
// or a status change made from another cashier terminal, shows up automatically.
setInterval(() => {
    fetchOnlineOrders();
}, 10000);

// === Category filter ===
document.getElementById('categoryFilter').addEventListener('change', function() {
  const selected = this.value.toLowerCase();
  const rows = document.querySelectorAll('#posInventoryBody tr');

  rows.forEach(row => {
    // This now works because row.dataset.category is set above
    const category = row.dataset.category.toLowerCase(); 
    if (selected === '' || category === selected) {
      row.style.display = '';
    } else {
      row.style.display = 'none';
    }
  });
});


// === Combine with search filter ===
document.getElementById('searchInput').addEventListener('input', function() {
  const search = this.value.toLowerCase();
  const selected = document.getElementById('categoryFilter').value.toLowerCase();
  const rows = document.querySelectorAll('#posInventoryBody tr');

  rows.forEach(row => {
    // This now works because row.dataset.category is set above
    const category = row.dataset.category.toLowerCase(); 
    const brand = row.cells[1].textContent.toLowerCase();
    const generic = row.cells[2].textContent.toLowerCase();
    const dosage = row.cells[3].textContent.toLowerCase();
    const form = row.cells[4].textContent.toLowerCase();
  
    const matchesSearch = brand.includes(search) || generic.includes(search) || dosage.includes(search) || form.includes(search);
    const matchesCategory = (selected === '' || category === selected);

    if (matchesSearch && matchesCategory) {
      row.style.display = '';
    } else {
      row.style.display = 'none';
    }
  });
});


});


// ---------------------- SHARED: soft drop-shadow for a subtle 3D lift ----------------------
const shadowPlugin = {
    id: 'softShadow',
    beforeDatasetsDraw(chart) {
        const ctx = chart.ctx;
        ctx.save();
        ctx.shadowColor = 'rgba(15, 23, 42, 0.22)';
        ctx.shadowBlur = 14;
        ctx.shadowOffsetX = 0;
        ctx.shadowOffsetY = 6;
    },
    afterDatasetsDraw(chart) {
        chart.ctx.restore();
    }
};

// Muted, professional palette (replaces the bright primary-color set)
const SEGMENT_COLORS = ['#3b5b92', '#3f8f7a', '#c08a3e', '#7a6aa8', '#b25c5c'];

// ---------------------- CUSTOMER SEGMENTATION TAB CHARTS ----------------------
// These 4 charts live inside the "customer-segmentation-page" tab, which is
// display:none until the user clicks its nav item. Building Chart.js charts
// while their container has zero/incorrect size is what caused the donut
// looking chopped off — Chart.js locks in bad internal dimensions at
// creation time and a later .resize() call doesn't reliably undo that.
// The real fix: don't create these charts until the tab is actually visible.
let kmeansPieChart = null;
let segmentationChartsReady = false;

function initSegmentationCharts() {
    if (segmentationChartsReady) return;
    segmentationChartsReady = true;

    // ---- Purchase Frequency (real data) ----
    const freqCanvas = document.getElementById('purchaseFrequencyChart');
    if (freqCanvas) {
        const ctx = freqCanvas.getContext('2d');
        const grad = ctx.createLinearGradient(0, 0, 0, 180);
        grad.addColorStop(0, '#4c6a97');
        grad.addColorStop(1, '#7c93b8');
        window.purchaseFrequencyChartInstance = new Chart(ctx, {
            type: 'bar',
            plugins: [shadowPlugin],
            data: {
                labels: <?php echo json_encode($freq_labels); ?>,
                datasets: [{
                    label: 'Number of Orders',
                    data: <?php echo json_encode($freq_data); ?>,
                    backgroundColor: grad,
                    borderRadius: 6,
                    borderSkipped: false,
                    maxBarThickness: 42,
                    hoverBackgroundColor: '#3b5b92'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { backgroundColor: '#1e293b', padding: 10, cornerRadius: 8, titleFont: { weight: '600' } }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { color: '#64748b', font: { size: 11.5 } } },
                    y: { beginAtZero: true, precision: 0, grid: { color: '#f1f5f9', borderDash: [4, 4] }, ticks: { color: '#94a3b8' } }
                }
            }
        });
    }

    // ---- Customer Spending Tier (real data) ----
    const spendCanvas = document.getElementById('spendingTierChart');
    if (spendCanvas) {
        const ctx = spendCanvas.getContext('2d');
        const grad = ctx.createLinearGradient(0, 0, 0, 180);
        grad.addColorStop(0, '#a8506a');
        grad.addColorStop(1, '#c98499');
        window.spendingTierChartInstance = new Chart(ctx, {
            type: 'bar',
            plugins: [shadowPlugin],
            data: {
                labels: <?php echo json_encode($spend_labels); ?>,
                datasets: [{
                    label: 'Total Spending (₱)',
                    data: <?php echo json_encode($spend_data); ?>,
                    backgroundColor: grad,
                    borderRadius: 6,
                    borderSkipped: false,
                    maxBarThickness: 42,
                    hoverBackgroundColor: '#a8506a'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1e293b', padding: 10, cornerRadius: 8, titleFont: { weight: '600' },
                        callbacks: { label: (c) => `₱${Number(c.raw).toLocaleString('en-US', {minimumFractionDigits:2})}` }
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { color: '#64748b', font: { size: 11.5 } } },
                    y: { beginAtZero: true, grid: { color: '#f1f5f9', borderDash: [4, 4] }, ticks: { color: '#94a3b8' } }
                }
            }
        });
    }

    // ---- Customer Type Distribution (real data) ----
    const typeCanvas = document.getElementById('customerTypeChart');
    if (typeCanvas) {
        window.customerTypeChartInstance = new Chart(typeCanvas.getContext('2d'), {
            type: 'doughnut',
            plugins: [shadowPlugin],
            data: {
                labels: <?php echo json_encode($type_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($type_data); ?>,
                    backgroundColor: SEGMENT_COLORS,
                    borderColor: '#fff',
                    borderWidth: 3,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: { position: 'bottom', labels: { color: '#475569', font: { size: 12.5 }, boxWidth: 10, boxHeight: 10, padding: 14, usePointStyle: true, pointStyle: 'circle' } },
                    tooltip: { backgroundColor: '#1e293b', padding: 10, cornerRadius: 8 }
                }
            }
        });
    }

    // ---- Customer Segments donut (K-means style, real data) ----
    const ctxPieEl = document.getElementById('customerSegmentationPie');
    if (ctxPieEl) {
        kmeansPieChart = new Chart(ctxPieEl.getContext('2d'), {
            type: 'doughnut',
            plugins: [shadowPlugin],
            data: { labels: [], datasets: [{ data: [], backgroundColor: SEGMENT_COLORS, borderColor: '#fff', borderWidth: 3, hoverOffset: 8 }] },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '55%',
                plugins: {
                    // Using a custom HTML legend below the chart instead (fixed box,
                    // stays inside the card, and looks more like a real dashboard).
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1e293b', padding: 10, cornerRadius: 8,
                        callbacks: {
                            label: function (context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0) || 1;
                                const value = context.raw;
                                const percent = ((value / total) * 100).toFixed(1);
                                return `${context.label}: ${value} customers (${percent}%)`;
                            }
                        }
                    }
                }
            }
        });
        window.kmeansPieChart = kmeansPieChart;
    }

    const segmentSelect = document.getElementById('segmentCountTop');
    if (segmentSelect) {
        applySegmentCount(segmentSelect.value);
        segmentSelect.addEventListener('change', () => applySegmentCount(segmentSelect.value));
    }
}
window.initSegmentationCharts = initSegmentationCharts;
</script>


<!-- Chart.js Library -->


<script>
// Real, DB-driven segment variants (2-5 buckets), computed server-side from
// actual customer spend — replaces the old hardcoded example numbers.
const segmentVariants = <?php echo json_encode($segment_variants); ?>;

function renderSegmentLegend(variant) {
    const el = document.getElementById('segmentLegend');
    if (!el) return;
    el.innerHTML = variant.labels.map((label, i) => `
        <div style="display:flex; align-items:center; gap:7px; font-size:13px; color:#475569; font-weight:600;">
            <span style="width:10px; height:10px; border-radius:50%; background:${SEGMENT_COLORS[i % SEGMENT_COLORS.length]}; display:inline-block;"></span>
            ${label}
        </div>
    `).join('');
}

let currentSegmentVariant = null;

function renderSegmentTable(variant) {
    const body = document.getElementById('segmentTableBody');
    if (!body) return;
    body.innerHTML = variant.labels.map((label, i) => `
        <tr>
            <td style="padding:11px 12px; border-top:1px solid #f1f5f9; font-weight:600; color:#334155;">
                <span style="width:8px; height:8px; border-radius:50%; background:${SEGMENT_COLORS[i % SEGMENT_COLORS.length]}; display:inline-block; margin-right:8px;"></span>${label}
            </td>
            <td style="padding:11px 12px; border-top:1px solid #f1f5f9; color:#334155;">${variant.counts[i]}</td>
            <td style="padding:11px 12px; border-top:1px solid #f1f5f9; color:#334155;">₱${Number(variant.avgSpend[i]).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
            <td style="padding:11px 12px; border-top:1px solid #f1f5f9;">
                <button type="button" class="btn-view-segment" data-segment-index="${i}" style="background:#2563eb; color:#fff; border:none; padding:6px 14px; border-radius:6px; cursor:pointer; font-size:12.5px; font-weight:600;">View</button>
            </td>
        </tr>
    `).join('');

    // (Re)bind the View buttons for this render
    body.querySelectorAll('.btn-view-segment').forEach(btn => {
        btn.onclick = () => showSegmentMembers(parseInt(btn.dataset.segmentIndex, 10));
    });
}

function renderSegmentMemberRow(member) {
    return `
        <tr data-customer-id="${member.customer_id}">
            <td style="padding:11px 12px; border-top:1px solid #f1f5f9; color:#334155; font-weight:600;">${member.name}</td>
            <td style="padding:11px 12px; border-top:1px solid #f1f5f9; color:#334155;">₱${Number(member.total_spent).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
            <td style="padding:11px 12px; border-top:1px solid #f1f5f9; color:#334155;" class="member-points-cell">${Number(member.loyalty_points).toLocaleString('en-US', {maximumFractionDigits:2})}</td>
            <td style="padding:11px 12px; border-top:1px solid #f1f5f9;">
                <div style="display:flex; gap:6px; align-items:center;">
                    <input type="number" class="add-points-input" placeholder="Points" style="width:80px; padding:6px 8px; border:1px solid #e2e8f0; border-radius:6px; font-size:13px;">
                    <button type="button" class="btn-add-points" style="background:#16a34a; color:#fff; border:none; padding:6px 12px; border-radius:6px; cursor:pointer; font-size:12.5px; font-weight:600;">Add</button>
                </div>
            </td>
        </tr>
    `;
}

function showSegmentMembers(segmentIndex) {
    if (!currentSegmentVariant) return;
    const label = currentSegmentVariant.labels[segmentIndex];
    const members = (currentSegmentVariant.members && currentSegmentVariant.members[segmentIndex]) || [];

    const panel = document.getElementById('segmentMembersPanel');
    const labelEl = document.getElementById('segmentMembersLabel');
    const body = document.getElementById('segmentMembersBody');
    const msg = document.getElementById('segmentMembersMessage');
    if (!panel || !body) return;

    labelEl.textContent = label;
    msg.textContent = '';
    body.innerHTML = members.length
        ? members.map(renderSegmentMemberRow).join('')
        : `<tr><td colspan="4" style="padding:14px 12px; text-align:center; color:#94a3b8;">No customers in this segment.</td></tr>`;

    // Bind Add buttons for the freshly rendered rows
    body.querySelectorAll('.btn-add-points').forEach(btn => {
        btn.onclick = () => {
            const row = btn.closest('tr');
            const customerId = row.dataset.customerId;
            const input = row.querySelector('.add-points-input');
            const points = parseFloat(input.value);

            if (isNaN(points) || points === 0) {
                msg.style.color = '#dc2626';
                msg.textContent = 'Enter a valid points amount first.';
                return;
            }

            btn.disabled = true;
            fetch('add_loyalty_points.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ customer_id: customerId, points: points })
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                if (data.success) {
                    row.querySelector('.member-points-cell').textContent =
                        Number(data.new_balance).toLocaleString('en-US', { maximumFractionDigits: 2 });
                    input.value = '';
                    msg.style.color = '#16a34a';
                    msg.textContent = `Points updated for ${row.cells[0].textContent}.`;
                } else {
                    msg.style.color = '#dc2626';
                    msg.textContent = data.message || 'Failed to update points.';
                }
            })
            .catch(() => {
                btn.disabled = false;
                msg.style.color = '#dc2626';
                msg.textContent = 'Network error while updating points.';
            });
        };
    });

    panel.style.display = 'block';
    panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

document.getElementById('segmentMembersCloseBtn')?.addEventListener('click', () => {
    document.getElementById('segmentMembersPanel').style.display = 'none';
});

function applySegmentCount(n) {
    const variant = segmentVariants[n];
    if (!variant || !kmeansPieChart) return;
    currentSegmentVariant = variant;
    kmeansPieChart.data.labels = variant.labels;
    kmeansPieChart.data.datasets[0].data = variant.counts;
    kmeansPieChart.update();
    renderSegmentLegend(variant);
    renderSegmentTable(variant);
    // Hide the members panel when the segment count changes since bucket
    // indices no longer line up with whatever was being viewed before.
    const panel = document.getElementById('segmentMembersPanel');
    if (panel) panel.style.display = 'none';
}

// profile

  // Keep a snapshot of the original values so Cancel can restore them
  // without reloading the whole page (which used to kick the user back
  // to the Dashboard tab).
  const profileOriginal = {};
  document.querySelectorAll(".p-input").forEach(i => {
      profileOriginal[i.id] = i.value;
  });

  // ENABLE EDIT MODE
  document.getElementById("profile_editBtn").onclick = () => {
      document.querySelectorAll(".p-input").forEach(i => i.disabled = false);
      document.getElementById("saveBtn").style.display = "block";
      document.getElementById("profile_cancelBtn").style.display = "block";
      document.getElementById("profile_editBtn").style.display = "none";
      document.getElementById("message").innerHTML = "";
  };

  // CANCEL EDIT (stay on the Profile page, just restore old values)
  document.getElementById("profile_cancelBtn").onclick = () => {
      document.querySelectorAll(".p-input").forEach(i => {
          i.value = profileOriginal[i.id];
          i.disabled = true;
      });
      document.getElementById("saveBtn").style.display = "none";
      document.getElementById("profile_cancelBtn").style.display = "none";
      document.getElementById("profile_editBtn").style.display = "block";
      document.getElementById("message").innerHTML = "";
  };

  // SAVE PROFILE (AJAX) — stays on the Profile page after saving
  document.getElementById("saveBtn").onclick = () => {

      const data = {
          first_name: document.getElementById("first_name").value.trim(),
          middle_name: document.getElementById("middle_name").value.trim(),
          last_name: document.getElementById("last_name").value.trim(),
          email: document.getElementById("email").value.trim(),
          phone_number: document.getElementById("phone_number").value.trim(),
          address: document.getElementById("address").value.trim()
      };

      fetch("update_profile_cashier.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(data)
      })
      .then(res => res.json())
      .then(response => {
          const msg = document.getElementById("message");
          if (response.success) {
              msg.style.color = "green";
              msg.innerHTML = "Profile updated successfully!";

              // Update the snapshot with the newly saved values and lock
              // the fields again, WITHOUT reloading the page.
              Object.keys(data).forEach(key => { profileOriginal[key] = data[key]; });
              document.querySelectorAll(".p-input").forEach(i => i.disabled = true);
              document.getElementById("saveBtn").style.display = "none";
              document.getElementById("profile_cancelBtn").style.display = "none";
              document.getElementById("profile_editBtn").style.display = "block";

              // Refresh the name shown in the header/card without a reload
              const nameHeader = document.querySelector("#profile-page h2");
              if (nameHeader) {
                  nameHeader.textContent = `${data.first_name} ${data.last_name}`;
              }
              const middleNamePara = document.querySelector("#profile-page .profile-card > div p");
              if (middleNamePara) {
                  middleNamePara.textContent = data.middle_name;
              }

              const headerWelcome = document.getElementById("headerWelcomeName");
              if (headerWelcome) headerWelcome.textContent = `Welcome, ${data.first_name}`;
          } else {
              msg.style.color = "red";
              msg.innerHTML = response.message || "Update failed.";
          }
      })
      .catch(err => {
          const msg = document.getElementById("message");
          msg.style.color = "red";
          msg.innerHTML = "Request error.";
          console.error("AJAX error:", err);
      });
  };

  // PROFILE PICTURE UPLOAD
  document.getElementById("changePasswordBtn")?.addEventListener("click", () => {
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
          pwMsg.style.color = r.success ? "green" : "red";
          pwMsg.textContent = r.message || (r.success ? "Password updated." : "Failed.");
          if (r.success) {
              ["current_password","new_password","confirm_password"].forEach(id => document.getElementById(id).value = "");
          }
      })
      .catch(() => { pwMsg.style.color = "red"; pwMsg.textContent = "Request error."; });
  });

  document.getElementById("profile_avatar_input").addEventListener("change", function () {
      const file = this.files[0];
      if (!file) return;

      const formData = new FormData();
      formData.append("profile_image", file);

      fetch("upload_profile_picture.php", { method: "POST", body: formData })
          .then(res => res.json())
          .then(response => {
              const msg = document.getElementById("message");
              if (response.success) {
                  document.getElementById("profile_avatar").src = response.path + "?t=" + Date.now();
                  msg.style.color = "green";
                  msg.innerHTML = "Profile picture updated!";
              } else {
                  msg.style.color = "red";
                  msg.innerHTML = response.message || "Failed to upload picture.";
              }
          })
          .catch(err => {
              console.error("Avatar upload error:", err);
              const msg = document.getElementById("message");
              msg.style.color = "red";
              msg.innerHTML = "Upload error.";
          });
  });
  
</script>




<script src="assets/theme.js"></script>
</body>
</html>