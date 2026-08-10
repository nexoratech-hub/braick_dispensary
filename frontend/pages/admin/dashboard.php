<?php
// ================================================================
// FILE: frontend/pages/admin/dashboard.php
// SUPER ADMIN DASHBOARD - MODERN DESIGN
// NO AUTO REFRESH - MANUAL REFRESH ONLY
// SOLID COLORS - NO GRADIENTS
// REVENUE FROM: patient_bills + otc_sales ONLY
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['user_id'] = 1;
    $_SESSION['full_name'] = 'Admin John';
    $_SESSION['role'] = 'admin';
    $_SESSION['branch_id'] = 1;
}

// Include database
require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// BRANCH SELECTION
// ================================================================
$selected_branch_id = $_GET['branch'] ?? 'all';
$branch_name = 'All Branches';

// ================================================================
// FUNCTION TO CHECK IF COLUMN EXISTS
// ================================================================
function columnExists($db, $table, $column) {
    try {
        $stmt = $db->query("SHOW COLUMNS FROM $table LIKE '$column'");
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

// ================================================================
// FUNCTION TO GET BRANCH FILTER (SAFE)
// ================================================================
function getBranchFilter($db, $selected_branch_id, $table) {
    if ($selected_branch_id === 'all') {
        return '';
    }
    if (columnExists($db, $table, 'branch_id')) {
        return " AND $table.branch_id = " . (int)$selected_branch_id;
    }
    return '';
}

// ================================================================
// BRANCH NAME
// ================================================================
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $branch_id = (int)$selected_branch_id;
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) {
        $branch_name = $branch_data['name'];
    }
} else {
    $selected_branch_id = 'all';
}

// ================================================================
// ================================================================
// TOTAL REVENUE - FROM patient_bills + otc_sales ONLY
// ================================================================
// ================================================================

$today = date('Y-m-d');

// Build branch filter for patient_bills
$branch_filter_pb = "";
if ($selected_branch_id !== 'all') {
    $branch_filter_pb = " AND branch_id = " . (int)$selected_branch_id;
}

// Build branch filter for otc_sales
$branch_filter = "";
if ($selected_branch_id !== 'all') {
    $branch_filter = " AND branch_id = " . (int)$selected_branch_id;
}

// ================================================================
// 1. PATIENT BILLS REVENUE (ALL PAID BILLS)
// ================================================================
$stmt = $db->query("
    SELECT COALESCE(SUM(total_amount), 0) as total 
    FROM patient_bills 
    WHERE status = 'paid'
    $branch_filter_pb
");
$patient_bills_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// ================================================================
// 2. OTC REVENUE (OTC Sales - Paid)
// ================================================================
$stmt = $db->query("
    SELECT COALESCE(SUM(net_amount), 0) as total 
    FROM otc_sales 
    WHERE payment_status = 'paid'
    $branch_filter
");
$otc_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// ================================================================
// 3. TOTAL REVENUE = patient_bills + otc_sales
// ================================================================
$total_revenue = $patient_bills_revenue + $otc_revenue;

// ================================================================
// 4. OTC DETAILS FOR DISPLAY
// ================================================================
$stmt = $db->query("
    SELECT COUNT(*) as count, 
           COALESCE(SUM(net_amount), 0) as total_paid,
           COALESCE(SUM(total_amount), 0) as total_all
    FROM otc_sales 
    WHERE payment_status = 'paid'
    $branch_filter
");
$otc_details = $stmt->fetch(PDO::FETCH_ASSOC);
$otc_count = $otc_details['count'] ?? 0;
$otc_total_paid = $otc_details['total_paid'] ?? 0;

// ================================================================
// 5. PATIENT BILLS DETAILS
// ================================================================
$stmt = $db->query("
    SELECT COUNT(*) as count, 
           COALESCE(SUM(total_amount), 0) as total_paid
    FROM patient_bills 
    WHERE status = 'paid'
    $branch_filter_pb
");
$pb_details = $stmt->fetch(PDO::FETCH_ASSOC);
$pb_count = $pb_details['count'] ?? 0;
$pb_total_paid = $pb_details['total_paid'] ?? 0;

// ================================================================
// OTHER STATISTICS
// ================================================================

// Total Prescriptions Count
if ($selected_branch_id !== 'all') {
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM prescriptions p
        INNER JOIN patient_bills pb ON pb.prescription_id = p.id
        WHERE p.status != 'cancelled' 
        AND pb.branch_id = ?
    ");
    $stmt->execute([(int)$selected_branch_id]);
    $total_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} else {
    $stmt = $db->query("
        SELECT COUNT(*) as count 
        FROM prescriptions 
        WHERE status != 'cancelled'
    ");
    $total_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
}

// OTC Sales Count
$stmt = $db->query("
    SELECT COUNT(*) as count 
    FROM otc_sales 
    WHERE payment_status = 'paid'
    $branch_filter
");
$total_otc_sales = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Total Patients
$stmt = $db->query("
    SELECT COUNT(*) as count 
    FROM patients 
    WHERE 1=1 
    $branch_filter
");
$total_patients = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Expired Medicines
$today_date = date('Y-m-d');
$stmt = $db->query("
    SELECT 
        SUM(CASE WHEN expiry_date < '$today_date' AND expiry_date IS NOT NULL THEN 1 ELSE 0 END) as expired,
        SUM(CASE WHEN expiry_date BETWEEN '$today_date' AND DATE_ADD('$today_date', INTERVAL 30 DAY) AND expiry_date IS NOT NULL THEN 1 ELSE 0 END) as expiring_soon
    FROM medications_inventory 
    WHERE status = 'active' 
    $branch_filter
");
$expiry_data = $stmt->fetch(PDO::FETCH_ASSOC);
$total_expired = $expiry_data['expired'] ?? 0;
$expiring_soon = $expiry_data['expiring_soon'] ?? 0;

// Low Stock
$stmt = $db->query("
    SELECT 
        SUM(CASE WHEN quantity <= 0 THEN 1 ELSE 0 END) as out_of_stock,
        SUM(CASE WHEN quantity > 0 AND quantity <= reorder_level THEN 1 ELSE 0 END) as low_stock
    FROM medications_inventory 
    WHERE status = 'active' 
    $branch_filter
");
$stock_data = $stmt->fetch(PDO::FETCH_ASSOC);
$out_of_stock = $stock_data['out_of_stock'] ?? 0;
$total_low_stock = ($out_of_stock + ($stock_data['low_stock'] ?? 0));

// Total Employees
$stmt = $db->query("
    SELECT COUNT(*) as count 
    FROM users 
    WHERE role != 'admin' AND status = 'active' 
    $branch_filter
");
$total_employees = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Total Branches
$stmt = $db->query("SELECT COUNT(*) as count FROM branches WHERE status = 'active'");
$total_branches = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Pending Lab Tests
$stmt = $db->query("
    SELECT COUNT(*) as count 
    FROM lab_tests 
    WHERE status = 'pending' 
    $branch_filter
");
$pending_lab_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Pending Prescriptions
if ($selected_branch_id !== 'all') {
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM prescriptions p
        INNER JOIN patient_bills pb ON pb.prescription_id = p.id
        WHERE p.status = 'pending' 
        AND pb.branch_id = ?
    ");
    $stmt->execute([(int)$selected_branch_id]);
    $pending_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} else {
    $stmt = $db->query("
        SELECT COUNT(*) as count 
        FROM prescriptions 
        WHERE status = 'pending'
    ");
    $pending_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
}

// Today's Appointments
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM appointments 
    WHERE DATE(appointment_date) = ? 
    AND status IN ('scheduled', 'confirmed') 
    $branch_filter
");
$stmt->execute([$today]);
$today_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Today's Patients
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM patients 
    WHERE DATE(created_at) = ? 
    $branch_filter
");
$stmt->execute([$today]);
$today_patients = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// GET BRANCHES
// ================================================================
$branches = [];
$stmt = $db->query("SELECT id, name, location FROM branches WHERE status = 'active'");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET RECENT PATIENTS
// ================================================================
$recent_patients = [];
$has_patient_branch = columnExists($db, 'patients', 'branch_id');

if ($selected_branch_id !== 'all' && $has_patient_branch) {
    $stmt = $db->prepare("
        SELECT p.*, b.name as branch_name 
        FROM patients p
        LEFT JOIN branches b ON p.branch_id = b.id
        WHERE p.branch_id = ?
        ORDER BY p.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([(int)$selected_branch_id]);
    $recent_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $db->query("
        SELECT p.*, b.name as branch_name 
        FROM patients p
        LEFT JOIN branches b ON p.branch_id = b.id
        ORDER BY p.created_at DESC
        LIMIT 5
    ");
    $recent_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ================================================================
// GET RECENT ACTIVITIES
// ================================================================
$recent_activities = [];
try {
    $stmt = $db->query("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 5");
    $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent_activities = [
        ['action' => 'System Started', 'details' => 'Super Admin logged in', 'created_at' => date('Y-m-d H:i:s')],
    ];
}

// ================================================================
// CHART DATA - Last 7 Days Revenue (from patient_bills + otc_sales)
// ================================================================
$chart_labels = [];
$chart_values = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('D', strtotime($date));
    
    $daily_total = 0;
    
    // 1. From patient_bills (paid bills)
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(total_amount), 0) as total 
        FROM patient_bills 
        WHERE DATE(created_at) = ? 
        AND status = 'paid'
        $branch_filter_pb
    ");
    $stmt->execute([$date]);
    $daily_total += $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // 2. From otc_sales (paid)
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(net_amount), 0) as total 
        FROM otc_sales 
        WHERE DATE(created_at) = ? 
        AND payment_status = 'paid'
        $branch_filter
    ");
    $stmt->execute([$date]);
    $daily_total += $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    $chart_values[] = (float)$daily_total;
}

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// VARIABLES FOR SIDEBAR & HEADER
// ================================================================
$total_employees = $total_employees ?? 0;
$total_branches = $total_branches ?? 0;
$pending_lab_tests = $pending_lab_tests ?? 0;
$pending_prescriptions = $pending_prescriptions ?? 0;
$selected_branch_id = $selected_branch_id ?? 'all';

// ================================================================
// INCLUDE SHARED HEADER
// ================================================================
include_once '../../components/admin_header.php';

// ================================================================
// INCLUDE SHARED SIDEBAR
// ================================================================
include_once '../../components/admin_sidebar.php';
?>

<!-- ================================================================ -->
<!-- TOP NAVIGATION - SHARED HEADER -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <input type="text" id="searchInput" placeholder="Search patients, doctors, medicines...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <select id="branchSelector" class="branch-selector" onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches as $branch): ?>
                <option value="<?= $branch['id'] ?>" <?= $selected_branch_id == $branch['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($branch['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn" title="Toggle Dark Mode">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $logo_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3EA%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-5">
        <div>
            <h1 class="page-title">
                <i class="fas fa-home mr-2"></i> Super Admin Dashboard
            </h1>
            <p class="page-subtitle">
                Welcome back, <?= htmlspecialchars($_SESSION['full_name'] ?? 'Admin') ?>!
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name) ?>
                </span>
                <span class="ml-2 date-badge">
                    <i class="fas fa-calendar-day mr-1"></i> <?= date('F d, Y') ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="reports.php?branch=<?= $selected_branch_id ?>" class="btn btn-blue btn-sm">
                <i class="fas fa-file-export"></i> Generate Report
            </a>
            <button onclick="location.reload()" class="btn btn-outline btn-sm">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- ROW 1: 4 MAIN CARDS - SOLID COLORS - SAME HEIGHT -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
        
        <!-- 1. Total Revenue - SOLID BLUE -->
        <a href="reports.php?type=revenue&branch=<?= $selected_branch_id ?>" style="text-decoration: none; display: block; height: 100%;">
            <div class="stat-card solid-blue">
                <div class="flex items-start justify-between h-full">
                    <div class="flex flex-col justify-between h-full">
                        <div>
                            <p class="stat-label">Total Revenue</p>
                            <p class="stat-number" id="totalRevenue">TSh <?= number_format($total_revenue) ?></p>
                            <p class="stat-sub-amount">Patient Bills + OTC Sales</p>
                        </div>
                        <p class="stat-trend"><i class="fas fa-arrow-up"></i> All time</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                </div>
                <i class="fas fa-arrow-right stat-arrow"></i>
            </div>
        </a>
        
        <!-- 2. Patient Bills - SOLID GREEN -->
        <a href="bills.php?branch=<?= $selected_branch_id ?>" style="text-decoration: none; display: block; height: 100%;">
            <div class="stat-card solid-green">
                <div class="flex items-start justify-between h-full">
                    <div class="flex flex-col justify-between h-full">
                        <div>
                            <p class="stat-label">Patient Bills</p>
                            <p class="stat-number" id="patientBillsRevenue">TSh <?= number_format($patient_bills_revenue) ?></p>
                            <p class="stat-sub-amount"><?= $pb_count ?> paid bills</p>
                        </div>
                        <p class="stat-trend"><i class="fas fa-file-invoice"></i> All bills</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                </div>
                <i class="fas fa-arrow-right stat-arrow"></i>
            </div>
        </a>
        
        <!-- 3. OTC Sales - SOLID DARK BLUE -->
        <a href="../pharmacy/otc_sales.php?branch=<?= $selected_branch_id ?>" style="text-decoration: none; display: block; height: 100%;">
            <div class="stat-card solid-dark-blue">
                <div class="flex items-start justify-between h-full">
                    <div class="flex flex-col justify-between h-full">
                        <div>
                            <p class="stat-label">OTC Sales</p>
                            <p class="stat-number" id="otcRevenue">TSh <?= number_format($otc_revenue) ?></p>
                            <p class="stat-sub-amount"><?= $otc_count ?> transactions</p>
                        </div>
                        <p class="stat-trend"><i class="fas fa-cash-register"></i> Over the counter</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-cash-register"></i>
                    </div>
                </div>
                <i class="fas fa-arrow-right stat-arrow"></i>
            </div>
        </a>
        
        <!-- 4. Total Patients - SOLID TEAL -->
        <a href="patients.php?branch=<?= $selected_branch_id ?>" style="text-decoration: none; display: block; height: 100%;">
            <div class="stat-card solid-teal">
                <div class="flex items-start justify-between h-full">
                    <div class="flex flex-col justify-between h-full">
                        <div>
                            <p class="stat-label">Total Patients</p>
                            <p class="stat-number" id="totalPatients"><?= number_format($total_patients) ?></p>
                            <p class="stat-sub-amount">Registered patients</p>
                        </div>
                        <p class="stat-trend"><i class="fas fa-users"></i> All time</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <i class="fas fa-arrow-right stat-arrow"></i>
            </div>
        </a>
        
    </div>

    <!-- ================================================================ -->
    <!-- ROW 2: 4 CARDS - SOLID COLORS - SAME HEIGHT -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
        
        <!-- 5. Low Stock - SOLID ORANGE -->
        <a href="inventory.php?filter=low_stock&branch=<?= $selected_branch_id ?>" style="text-decoration: none; display: block; height: 100%;">
            <div class="stat-card solid-orange">
                <div class="flex items-start justify-between h-full">
                    <div class="flex flex-col justify-between h-full">
                        <div>
                            <p class="stat-label">Stock Alerts</p>
                            <p class="stat-number" id="lowStock"><?= number_format($total_low_stock) ?></p>
                            <p class="stat-sub-amount"><?= $out_of_stock ?> out of stock</p>
                        </div>
                        <p class="stat-trend"><i class="fas fa-pills"></i> Needs restock</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
                <i class="fas fa-arrow-right stat-arrow"></i>
            </div>
        </a>
        
        <!-- 6. Expired Medicines - SOLID RED -->
        <a href="inventory.php?filter=expired&branch=<?= $selected_branch_id ?>" style="text-decoration: none; display: block; height: 100%;">
            <div class="stat-card solid-red">
                <div class="flex items-start justify-between h-full">
                    <div class="flex flex-col justify-between h-full">
                        <div>
                            <p class="stat-label">Expired Medicines</p>
                            <p class="stat-number" id="expiredMedicines"><?= number_format($total_expired) ?></p>
                            <p class="stat-sub-amount"><i class="fas fa-clock"></i> <?= $expiring_soon ?> expiring soon</p>
                        </div>
                        <p class="stat-trend"><i class="fas fa-exclamation-circle"></i> Needs disposal</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-skull"></i>
                    </div>
                </div>
                <i class="fas fa-arrow-right stat-arrow"></i>
            </div>
        </a>
        
        <!-- 7. Total Employees - SOLID PURPLE -->
        <a href="employees.php?branch=<?= $selected_branch_id ?>" style="text-decoration: none; display: block; height: 100%;">
            <div class="stat-card solid-purple">
                <div class="flex items-start justify-between h-full">
                    <div class="flex flex-col justify-between h-full">
                        <div>
                            <p class="stat-label">Total Employees</p>
                            <p class="stat-number" id="totalEmployees"><?= number_format($total_employees) ?></p>
                            <p class="stat-sub-amount">Active staff</p>
                        </div>
                        <p class="stat-trend"><i class="fas fa-user-tie"></i> All staff</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-user-tie"></i>
                    </div>
                </div>
                <i class="fas fa-arrow-right stat-arrow"></i>
            </div>
        </a>
        
        <!-- 8. Pending Tasks - SOLID CYAN -->
        <a href="pending_tasks.php?branch=<?= $selected_branch_id ?>" style="text-decoration: none; display: block; height: 100%;">
            <div class="stat-card solid-cyan">
                <div class="flex items-start justify-between h-full">
                    <div class="flex flex-col justify-between h-full">
                        <div>
                            <p class="stat-label">Pending Tasks</p>
                            <p class="stat-number" style="font-size: 2rem;">
                                <?php 
                                    $pending_total = ($pending_lab_tests ?? 0) + ($pending_prescriptions ?? 0);
                                    echo number_format($pending_total);
                                ?>
                            </p>
                            <p class="stat-sub-amount">
                                Lab: <?= $pending_lab_tests ?? 0 ?> · Prescription: <?= $pending_prescriptions ?? 0 ?>
                            </p>
                        </div>
                        <p class="stat-trend"><i class="fas fa-tasks"></i> Needs attention</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                </div>
                <i class="fas fa-arrow-right stat-arrow"></i>
            </div>
        </a>
        
    </div>

    <!-- ================================================================ -->
    <!-- QUICK STATS - ROW 3 -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5">
        
        <div class="quick-stat-card">
            <div class="qs-icon blue">
                <i class="fas fa-calendar-day"></i>
            </div>
            <div>
                <p class="qs-label">Today's Patients</p>
                <p class="qs-value blue-text" id="todayPatients"><?= number_format($today_patients) ?></p>
            </div>
        </div>
        
        <div class="quick-stat-card">
            <div class="qs-icon orange">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div>
                <p class="qs-label">Today's Appointments</p>
                <p class="qs-value orange-text" id="todayAppointments"><?= number_format($today_appointments) ?></p>
            </div>
        </div>
        
        <div class="quick-stat-card">
            <div class="qs-icon purple">
                <i class="fas fa-flask"></i>
            </div>
            <div>
                <p class="qs-label">Pending Lab Tests</p>
                <p class="qs-value purple-text" id="pendingLabTests"><?= number_format($pending_lab_tests) ?></p>
            </div>
        </div>
        
        <div class="quick-stat-card">
            <div class="qs-icon red">
                <i class="fas fa-prescription"></i>
            </div>
            <div>
                <p class="qs-label">Pending Prescriptions</p>
                <p class="qs-value red-text" id="pendingPrescriptions"><?= number_format($pending_prescriptions) ?></p>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- CHART - Revenue -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-chart-line title-blue mr-2"></i> Revenue Overview (Last 7 Days)
                <span class="text-xs text-gray-400 font-normal">TSh <?= number_format(array_sum($chart_values)) ?> total</span>
            </h3>
        </div>
        <canvas id="revenueChart" height="120"></canvas>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT PATIENTS & ACTIVITIES -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-user-injured title-blue mr-2"></i> Recent Patients
                </h3>
                <a href="patients.php?branch=<?= $selected_branch_id ?>" class="text-xs text-blue-600 font-medium hover:underline">View All →</a>
            </div>
            <div class="overflow-x-auto max-h-60 overflow-y-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Patient ID</th>
                            <th>Name</th>
                            <th>Branch</th>
                            <th>Registered</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($recent_patients) > 0): ?>
                            <?php foreach ($recent_patients as $patient): ?>
                                <tr>
                                    <td class="font-mono text-xs"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></td>
                                    <td class="font-medium"><?= htmlspecialchars($patient['full_name'] ?? 'Unknown') ?></td>
                                    <td class="text-xs"><?= htmlspecialchars($patient['branch_name'] ?? 'N/A') ?></td>
                                    <td class="text-xs"><?= date('M d, Y', strtotime($patient['created_at'])) ?></td>
                                    <td>
                                        <a href="patient_details.php?id=<?= $patient['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                           class="text-blue-600 text-xs hover:underline">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center text-gray-400 text-sm py-3">No patients found</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-clock title-green mr-2"></i> Recent Activities
                </h3>
                <a href="system_logs.php" class="text-xs text-blue-600 font-medium hover:underline">View All →</a>
            </div>
            <div class="space-y-2 max-h-60 overflow-y-auto">
                <?php foreach ($recent_activities as $activity): ?>
                    <div class="flex items-start gap-3 p-2 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/20 transition">
                        <div class="w-7 h-7 rounded-full bg-blue-600 flex items-center justify-center flex-shrink-0 mt-0.5 text-white">
                            <i class="fas fa-circle text-[6px]"></i>
                        </div>
                        <div>
                            <p class="font-medium text-sm text-gray-800 dark:text-gray-200"><?= htmlspecialchars($activity['action'] ?? 'Action') ?></p>
                            <p class="text-xs text-gray-500 dark:text-gray-400"><?= htmlspecialchars($activity['details'] ?? '') ?></p>
                            <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-0.5">
                                <?= isset($activity['created_at']) ? time_ago($activity['created_at']) : 'Just now' ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- QUICK REPORTS -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-file-alt title-blue mr-2"></i> Quick Reports
            </h3>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="reports.php?type=daily&branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm">
                <i class="fas fa-calendar-day"></i> Daily
            </a>
            <a href="reports.php?type=weekly&branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm">
                <i class="fas fa-calendar-week"></i> Weekly
            </a>
            <a href="reports.php?type=monthly&branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm">
                <i class="fas fa-calendar-alt"></i> Monthly
            </a>
            <a href="reports.php?type=revenue&branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm">
                <i class="fas fa-money-bill-wave"></i> Revenue
            </a>
            <a href="reports.php?type=medicine&branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm">
                <i class="fas fa-pills"></i> Medicine
            </a>
            <a href="reports.php?type=laboratory&branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm">
                <i class="fas fa-flask"></i> Laboratory
            </a>
            <div class="flex-1"></div>
            <button onclick="window.print()" class="btn btn-outline btn-sm">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Super Admin Dashboard v3.0
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<style>
    /* ================================================================
       CUSTOM STYLES FOR DASHBOARD
       ================================================================ */
    
    /* Stat Cards - SOLID COLORS - SAME HEIGHT */
    .stat-card {
        border-radius: 16px;
        padding: 22px 24px;
        border: none;
        transition: all 0.3s ease;
        color: white;
        text-decoration: none;
        display: block;
        position: relative;
        overflow: hidden;
        min-height: 150px;
        height: 100%;
        cursor: pointer;
    }
    
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }
    
    .stat-card:active {
        transform: scale(0.97);
    }
    
    /* Solid Colors - No Gradients */
    .stat-card.solid-blue { background: #0B5ED7; }
    .stat-card.solid-green { background: #059669; }
    .stat-card.solid-dark-blue { background: #0A4CA8; }
    .stat-card.solid-teal { background: #0D9488; }
    .stat-card.solid-red { background: #EF4444; }
    .stat-card.solid-orange { background: #F59E0B; }
    .stat-card.solid-purple { background: #7B2FBE; }
    .stat-card.solid-cyan { background: #0891B2; }
    
    /* Card hover effects */
    .stat-card.solid-blue:hover { background: #0B5ED7; box-shadow: 0 8px 25px rgba(11, 94, 215, 0.35); }
    .stat-card.solid-green:hover { background: #059669; box-shadow: 0 8px 25px rgba(5, 150, 105, 0.35); }
    .stat-card.solid-dark-blue:hover { background: #0A4CA8; box-shadow: 0 8px 25px rgba(10, 76, 168, 0.35); }
    .stat-card.solid-teal:hover { background: #0D9488; box-shadow: 0 8px 25px rgba(13, 148, 136, 0.35); }
    .stat-card.solid-red:hover { background: #EF4444; box-shadow: 0 8px 25px rgba(239, 68, 68, 0.35); }
    .stat-card.solid-orange:hover { background: #F59E0B; box-shadow: 0 8px 25px rgba(245, 158, 11, 0.35); }
    .stat-card.solid-purple:hover { background: #7B2FBE; box-shadow: 0 8px 25px rgba(123, 47, 190, 0.35); }
    .stat-card.solid-cyan:hover { background: #0891B2; box-shadow: 0 8px 25px rgba(8, 145, 178, 0.35); }
    
    .stat-card .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        background: rgba(255,255,255,0.2);
        color: white;
        flex-shrink: 0;
    }
    
    .stat-card .stat-number {
        font-size: 2rem;
        font-weight: 700;
        color: white;
        line-height: 1.2;
    }
    
    .stat-card .stat-label {
        font-size: 0.85rem;
        color: rgba(255,255,255,0.9);
        font-weight: 500;
        margin-bottom: 4px;
    }
    
    .stat-card .stat-trend {
        font-size: 0.7rem;
        font-weight: 500;
        padding: 4px 12px;
        border-radius: 20px;
        background: rgba(255,255,255,0.15);
        color: white;
        display: inline-block;
        margin-top: 6px;
    }
    
    .stat-card .stat-sub-amount {
        font-size: 0.75rem;
        color: rgba(255,255,255,0.8);
        margin-top: 2px;
    }
    
    .stat-card .stat-arrow {
        position: absolute;
        right: 18px;
        bottom: 18px;
        color: rgba(255,255,255,0.4);
        font-size: 1rem;
        transition: all 0.3s ease;
    }
    
    .stat-card:hover .stat-arrow {
        transform: translateX(4px);
        color: rgba(255,255,255,0.9);
    }
    
    /* Quick Stat Cards */
    .quick-stat-card {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 14px 18px;
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 14px;
    }
    
    .quick-stat-card:hover {
        border-color: var(--blue-600);
        box-shadow: var(--shadow-md);
        transform: translateY(-2px);
    }
    
    .quick-stat-card .qs-icon {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        flex-shrink: 0;
    }
    
    .quick-stat-card .qs-icon.blue { background: #EFF6FF; color: #0B5ED7; }
    .quick-stat-card .qs-icon.orange { background: #FFFBEB; color: #F59E0B; }
    .quick-stat-card .qs-icon.purple { background: #F5F3FF; color: #7B2FBE; }
    .quick-stat-card .qs-icon.red { background: #FEF2F2; color: #EF4444; }
    
    [data-theme="dark"] .quick-stat-card .qs-icon.blue { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .quick-stat-card .qs-icon.orange { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .quick-stat-card .qs-icon.purple { background: #2D1B4E; color: #A78BFA; }
    [data-theme="dark"] .quick-stat-card .qs-icon.red { background: #3A1A1A; color: #F87171; }
    
    .quick-stat-card .qs-label {
        font-size: 0.7rem;
        color: var(--text-muted);
        font-weight: 500;
    }
    
    .quick-stat-card .qs-value {
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--text-primary);
        line-height: 1.2;
    }
    
    .quick-stat-card .qs-value.blue-text { color: #0B5ED7; }
    .quick-stat-card .qs-value.orange-text { color: #F59E0B; }
    .quick-stat-card .qs-value.purple-text { color: #7B2FBE; }
    .quick-stat-card .qs-value.red-text { color: #EF4444; }
    
    [data-theme="dark"] .quick-stat-card .qs-value.blue-text { color: #6EA8FE; }
    [data-theme="dark"] .quick-stat-card .qs-value.orange-text { color: #FBBF24; }
    [data-theme="dark"] .quick-stat-card .qs-value.purple-text { color: #A78BFA; }
    [data-theme="dark"] .quick-stat-card .qs-value.red-text { color: #F87171; }
    
    /* Page Header */
    .page-header .date-badge {
        background: var(--bg-card);
        color: var(--text-primary);
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 1px solid var(--border-color);
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .stat-card {
            padding: 18px 16px;
            min-height: 120px;
        }
        .stat-card .stat-number {
            font-size: 1.5rem;
        }
        .stat-card .stat-icon {
            width: 40px;
            height: 40px;
            font-size: 1rem;
        }
    }
</style>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE
    // ================================================================
    var darkModeToggle = document.getElementById('darkModeToggle');
    var darkIcon = document.getElementById('darkIcon');
    var darkText = document.getElementById('darkText');
    var htmlElement = document.documentElement;
    
    var savedDarkMode = localStorage.getItem('darkMode');
    if (savedDarkMode === 'true') {
        htmlElement.setAttribute('data-theme', 'dark');
        darkIcon.className = 'fas fa-sun';
        darkText.textContent = 'Light';
    }
    
    darkModeToggle?.addEventListener('click', function() {
        var isDark = htmlElement.getAttribute('data-theme') === 'dark';
        if (isDark) {
            htmlElement.removeAttribute('data-theme');
            darkIcon.className = 'fas fa-moon';
            darkText.textContent = 'Dark';
            localStorage.setItem('darkMode', 'false');
            document.cookie = "dark_mode=false; path=/";
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
            document.cookie = "dark_mode=true; path=/";
        }
    });

    // ================================================================
    // DOM ELEMENTS
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');

    sidebarToggle?.addEventListener('click', function() {
        sidebar.classList.toggle('open');
    });
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            var branch = '<?= $selected_branch_id ?>';
            window.location.href = 'search.php?q=' + encodeURIComponent(query) + '&branch=' + branch;
        }
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        window.location.href = url.toString();
    }

    function updateDateTime() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var dtEl = document.getElementById('currentDateTime');
        if (dtEl) dtEl.textContent = dateStr + ' • ' + timeStr;
        
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // REVENUE CHART
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        var ctx = document.getElementById('revenueChart')?.getContext('2d');
        if (ctx) {
            if (typeof Chart !== 'undefined') {
                var labels = <?= json_encode($chart_labels) ?>;
                var values = <?= json_encode($chart_values) ?>;
                
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Revenue (TSh)',
                            data: values,
                            borderColor: '#0B5ED7',
                            backgroundColor: 'rgba(11, 94, 215, 0.1)',
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: '#0B5ED7',
                            pointBorderColor: '#ffffff',
                            pointBorderWidth: 2,
                            pointRadius: 5,
                            pointHoverRadius: 7,
                            borderWidth: 3
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { 
                                display: true,
                                labels: {
                                    font: { size: 11, weight: '600' },
                                    boxWidth: 12,
                                    padding: 15,
                                    color: '#64748B'
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return 'TSh ' + context.raw.toLocaleString();
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return 'TSh ' + value.toLocaleString();
                                    },
                                    font: { size: 10 }
                                },
                                grid: { color: 'rgba(0,0,0,0.05)' }
                            },
                            x: { 
                                grid: { display: false },
                                ticks: { font: { size: 10 } }
                            }
                        },
                        interaction: { intersect: false, mode: 'index' }
                    }
                });
            }
        }
    });

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            searchInput?.focus();
        }
    });

    console.log('%c🏥 Braick Dispensary - Super Admin Dashboard v3.0', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👋 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c💰 Total Revenue: TSh <?= number_format($total_revenue) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📋 Revenue from: patient_bills + otc_sales ONLY', 'font-size:13px; color:#0B5ED7;');
    console.log('%c   Patient Bills: TSh <?= number_format($patient_bills_revenue) ?>', 'font-size:12px; color:#059669;');
    console.log('%c   OTC Sales: TSh <?= number_format($otc_revenue) ?>', 'font-size:12px; color:#0A4CA8;');
    console.log('%c🔄 No Auto Refresh - Manual Refresh Only', 'font-size:13px; color:#EF4444;');
</script>

</body>
</html>