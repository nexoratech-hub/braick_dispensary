<?php
// ================================================================
// FILE: frontend/pages/admin/dashboard.php
// SUPER ADMIN DASHBOARD - BRANCH FILTER FIXED
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION FOR DIRECT ACCESS (NO LOGIN REQUIRED)
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['user_id'] = 1;
    $_SESSION['full_name'] = 'Admin John';
    $_SESSION['role'] = 'admin';
    $_SESSION['branch_id'] = 1;
}

// Include database and helpers
require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// BRANCH SELECTION
// ================================================================
$selected_branch_id = $_GET['branch'] ?? 'all';
$branch_name = 'All Branches';

// ================================================================
// FUNCTION TO CHECK IF COLUMN EXISTS IN TABLE
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
    // Check if branch_id column exists in the table
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
// FETCH STATISTICS WITH SAFE BRANCH FILTER
// ================================================================
$today = date('Y-m-d');

// 1. Total Patients
$filter = getBranchFilter($db, $selected_branch_id, 'patients');
$stmt = $db->query("SELECT COUNT(*) as count FROM patients WHERE 1=1 $filter");
$total_patients = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 2. Today's Patients
$filter = getBranchFilter($db, $selected_branch_id, 'patients');
$stmt = $db->prepare("SELECT COUNT(*) as count FROM patients WHERE DATE(created_at) = ? $filter");
$stmt->execute([$today]);
$today_patients = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 3. Total Revenue (pharmacy_sales)
$filter = getBranchFilter($db, $selected_branch_id, 'pharmacy_sales');
$stmt = $db->query("SELECT COALESCE(SUM(total), 0) as revenue FROM pharmacy_sales WHERE payment_status = 'paid' $filter");
$total_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['revenue'] ?? 0;

// 4. Today's Revenue
$filter = getBranchFilter($db, $selected_branch_id, 'pharmacy_sales');
$stmt = $db->prepare("SELECT COALESCE(SUM(total), 0) as revenue FROM pharmacy_sales WHERE DATE(sale_date) = ? AND payment_status = 'paid' $filter");
$stmt->execute([$today]);
$today_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['revenue'] ?? 0;

// 5. Total Doctors (users table)
$filter = getBranchFilter($db, $selected_branch_id, 'users');
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'doctor' AND status = 'active' $filter");
$total_doctors = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 6. Total Employees
$filter = getBranchFilter($db, $selected_branch_id, 'users');
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role != 'admin' $filter");
$total_employees = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 7. Today's Appointments
$filter = getBranchFilter($db, $selected_branch_id, 'appointments');
$stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE DATE(appointment_date) = ? AND status IN ('scheduled', 'confirmed') $filter");
$stmt->execute([$today]);
$today_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 8. Total Branches (no filter needed)
$stmt = $db->query("SELECT COUNT(*) as count FROM branches WHERE status = 'active'");
$total_branches = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 9. Pending Prescriptions
$filter = getBranchFilter($db, $selected_branch_id, 'prescriptions');
$stmt = $db->query("SELECT COUNT(*) as count FROM prescriptions WHERE status = 'pending' $filter");
$pending_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 10. Low Stock Medicines
$filter = getBranchFilter($db, $selected_branch_id, 'medications_inventory');
$stmt = $db->query("SELECT COUNT(*) as count FROM medications_inventory WHERE quantity <= reorder_level AND status = 'active' $filter");
$low_stock = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 11. Pending Lab Tests
$filter = getBranchFilter($db, $selected_branch_id, 'lab_tests');
$stmt = $db->query("SELECT COUNT(*) as count FROM lab_tests WHERE status = 'pending' $filter");
$pending_lab_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// GET BRANCHES
// ================================================================
$branches = [];
$stmt = $db->query("SELECT id, name, location FROM branches WHERE status = 'active'");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET RECENT PATIENTS - FIXED (SAFE QUERY)
// ================================================================
$recent_patients = [];

// Check if branch_id exists in patients table
$has_patient_branch = columnExists($db, 'patients', 'branch_id');

if ($selected_branch_id !== 'all' && $has_patient_branch) {
    // With branch filter
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
    // No branch filter or branch_id doesn't exist
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
        ['action' => 'Dashboard Loaded', 'details' => 'Dashboard loaded successfully', 'created_at' => date('Y-m-d H:i:s', strtotime('-1 minute'))],
    ];
}

// ================================================================
// CHART DATA - Last 7 Days Revenue
// ================================================================
$chart_labels = [];
$chart_values = [];
$filter = getBranchFilter($db, $selected_branch_id, 'pharmacy_sales');
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('D', strtotime($date));
    
    $stmt = $db->prepare("SELECT COALESCE(SUM(total), 0) as revenue FROM pharmacy_sales WHERE DATE(sale_date) = ? AND payment_status = 'paid' $filter");
    $stmt->execute([$date]);
    $rev = $stmt->fetch(PDO::FETCH_ASSOC);
    $chart_values[] = (float)$rev['revenue'];
}

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// VARIABLES FOR SIDEBAR & HEADER
// ================================================================
$total_employees = $total_employees ?? 0;
$total_doctors = $total_doctors ?? 0;
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

<style>
    /* ================================================================
       DASHBOARD ADDITIONAL STYLES
       ================================================================ */
    
    /* Modern Module Cards */
    .module-card {
        background: var(--bg-card);
        border-radius: 18px;
        padding: 20px 18px;
        border: 2px solid var(--border-color);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        text-decoration: none;
        color: var(--text-primary);
        display: block;
        position: relative;
        overflow: hidden;
        cursor: pointer;
        animation: fadeInUp 0.5s ease forwards;
        opacity: 0;
    }
    
    .module-card:nth-child(1) { animation-delay: 0.05s; }
    .module-card:nth-child(2) { animation-delay: 0.10s; }
    .module-card:nth-child(3) { animation-delay: 0.15s; }
    .module-card:nth-child(4) { animation-delay: 0.20s; }
    .module-card:nth-child(5) { animation-delay: 0.25s; }
    .module-card:nth-child(6) { animation-delay: 0.30s; }
    
    .module-card:hover {
        transform: translateY(-6px);
        border-color: #0B5ED7;
        box-shadow: 0 12px 30px rgba(11, 94, 215, 0.15);
    }
    
    .module-card:active {
        transform: translateY(-2px) scale(0.98);
    }
    
    .module-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(135deg, rgba(11, 94, 215, 0.05), rgba(5, 150, 105, 0.05));
        opacity: 0;
        transition: opacity 0.3s ease;
        border-radius: 18px;
    }
    
    .module-card:hover::before {
        opacity: 1;
    }
    
    .module-card .module-icon {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        color: white;
        transition: all 0.3s ease;
        position: relative;
        z-index: 1;
    }
    
    .module-card:hover .module-icon {
        transform: scale(1.05) rotate(-3deg);
    }
    
    .module-card .module-icon.blue { 
        background: linear-gradient(135deg, #0B5ED7, #1A73E8);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }
    
    .module-card .module-icon.green { 
        background: linear-gradient(135deg, #059669, #0AA84F);
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
    }
    
    .module-card .module-icon.purple { 
        background: linear-gradient(135deg, #7B2FBE, #9B4DCA);
        box-shadow: 0 4px 12px rgba(123, 47, 190, 0.3);
    }
    
    .module-card .module-icon.orange { 
        background: linear-gradient(135deg, #F59E0B, #FBBF24);
        box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
    }
    
    .module-card .module-icon.teal { 
        background: linear-gradient(135deg, #0D9488, #14B8A6);
        box-shadow: 0 4px 12px rgba(13, 148, 136, 0.3);
    }
    
    .module-card .module-icon.red { 
        background: linear-gradient(135deg, #EF4444, #F87171);
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
    }
    
    .module-card .module-name {
        font-size: 0.95rem;
        font-weight: 600;
        margin-top: 10px;
        color: var(--text-primary);
        transition: color 0.3s ease;
        position: relative;
        z-index: 1;
    }
    
    .module-card:hover .module-name {
        color: #0B5ED7;
    }
    
    .module-card .module-count {
        font-size: 0.75rem;
        color: var(--text-secondary);
        margin-top: 2px;
        position: relative;
        z-index: 1;
    }
    
    .module-card .module-arrow {
        position: absolute;
        right: 16px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--border-color);
        transition: all 0.3s ease;
        font-size: 0.9rem;
        opacity: 0;
        z-index: 1;
    }
    
    .module-card:hover .module-arrow {
        opacity: 1;
        color: #0B5ED7;
        transform: translateY(-50%) translateX(4px);
    }
    
    .module-card .module-badge {
        position: absolute;
        top: 12px;
        right: 12px;
        background: #EF4444;
        color: white;
        font-size: 0.6rem;
        font-weight: 700;
        padding: 2px 10px;
        border-radius: 20px;
        min-width: 20px;
        text-align: center;
        z-index: 1;
        animation: pulse-badge 2s infinite;
    }
    
    .module-card .module-badge.green {
        background: #059669;
    }
    
    .module-card .module-badge.blue {
        background: #0B5ED7;
    }
    
    .module-card .module-badge.orange {
        background: #F59E0B;
    }
    
    [data-theme="dark"] .module-card {
        border-color: #334155;
        background: #1E293B;
    }
    
    [data-theme="dark"] .module-card:hover {
        border-color: #0B5ED7;
        box-shadow: 0 12px 30px rgba(11, 94, 215, 0.2);
    }
    
    [data-theme="dark"] .module-card .module-name {
        color: #F1F5F9;
    }
    
    [data-theme="dark"] .module-card:hover .module-name {
        color: #6EA8FE;
    }
    
    [data-theme="dark"] .module-card .module-count {
        color: #94A3B8;
    }
    
    @keyframes pulse-badge {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }
    
    /* Section Header */
    .section-header {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 16px;
    }
    
    .section-header h2 {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--text-primary);
    }
    
    .section-header .section-icon {
        color: #0B5ED7;
        font-size: 1.2rem;
    }
    
    .section-header .section-sub {
        font-size: 0.75rem;
        color: var(--text-secondary);
        font-weight: 400;
        margin-left: 6px;
    }
</style>

<!-- ================================================================ -->
<!-- TOP NAVIGATION -->
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
        
        <!-- Dark Mode Toggle -->
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
                <i class="fas fa-home mr-2" style="color: var(--blue-600);"></i> Super Admin Dashboard
            </h1>
            <p class="page-subtitle">
                Welcome back, <?= htmlspecialchars($_SESSION['full_name'] ?? 'Admin') ?>!
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name) ?>
                </span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-calendar-day mr-1"></i> <?= date('F d, Y') ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="reports.php?branch=<?= $selected_branch_id ?>" class="btn btn-blue btn-sm">
                <i class="fas fa-file-export"></i> Generate Report
            </a>
            <button onclick="refreshData()" class="btn btn-outline btn-sm" id="refreshBtn">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS - 8 CARDS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 mb-5">
        
        <div class="stat-card blue animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Total Patients</p>
                    <p class="stat-number"><?= number_format($total_patients) ?></p>
                    <span class="stat-trend"><i class="fas fa-arrow-up"></i> Registered</span>
                </div>
                <div class="stat-icon"><i class="fas fa-users"></i></div>
            </div>
        </div>
        
        <div class="stat-card green animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Today's Patients</p>
                    <p class="stat-number"><?= number_format($today_patients) ?></p>
                    <span class="stat-trend"><i class="fas fa-arrow-up"></i> New today</span>
                </div>
                <div class="stat-icon"><i class="fas fa-user-plus"></i></div>
            </div>
        </div>
        
        <div class="stat-card blue-dark animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Total Revenue</p>
                    <p class="stat-number">TSh <?= number_format($total_revenue) ?></p>
                    <span class="stat-trend"><i class="fas fa-arrow-up"></i> All time</span>
                </div>
                <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            </div>
        </div>
        
        <div class="stat-card green-dark animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Today's Revenue</p>
                    <p class="stat-number">TSh <?= number_format($today_revenue) ?></p>
                    <span class="stat-trend"><i class="fas fa-arrow-up"></i> Today</span>
                </div>
                <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
            </div>
        </div>
        
        <div class="stat-card blue-light animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Total Employees</p>
                    <p class="stat-number"><?= number_format($total_employees) ?></p>
                    <span class="stat-trend"><i class="fas fa-users"></i> All staff</span>
                </div>
                <div class="stat-icon"><i class="fas fa-users"></i></div>
            </div>
        </div>
        
        <div class="stat-card green-light animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Today's Appointments</p>
                    <p class="stat-number"><?= number_format($today_appointments) ?></p>
                    <span class="stat-trend"><i class="fas fa-calendar-check"></i> Scheduled</span>
                </div>
                <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
            </div>
        </div>
        
        <div class="stat-card blue animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Pending Prescriptions</p>
                    <p class="stat-number"><?= number_format($pending_prescriptions) ?></p>
                    <span class="stat-trend"><i class="fas fa-clock"></i> Pending</span>
                </div>
                <div class="stat-icon"><i class="fas fa-prescription"></i></div>
            </div>
        </div>
        
        <div class="stat-card green animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Low Stock Medicines</p>
                    <p class="stat-number"><?= number_format($low_stock) ?></p>
                    <span class="stat-trend"><i class="fas fa-exclamation-triangle"></i> Needs restock</span>
                </div>
                <div class="stat-icon"><i class="fas fa-pills"></i></div>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- MODULE NAVIGATION CARDS -->
    <!-- ================================================================ -->
    <div class="mb-5">
        <div class="section-header">
            <i class="fas fa-th-large section-icon"></i>
            <h2>Module Navigation <span class="section-sub">Click to access any module</span></h2>
        </div>
        
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
            
            <a href="../doctor/dashboard.php?branch=<?= $selected_branch_id ?>" class="module-card">
                <div class="flex items-start justify-between">
                    <div class="module-icon blue"><i class="fas fa-user-md"></i></div>
                    <i class="fas fa-chevron-right module-arrow"></i>
                </div>
                <div class="module-name">Doctors</div>
                <div class="module-count"><?= $total_doctors ?> active</div>
                <span class="module-badge green"><?= $total_doctors ?></span>
            </a>
            
            <a href="../reception/dashboard.php?branch=<?= $selected_branch_id ?>" class="module-card">
                <div class="flex items-start justify-between">
                    <div class="module-icon green"><i class="fas fa-headset"></i></div>
                    <i class="fas fa-chevron-right module-arrow"></i>
                </div>
                <div class="module-name">Reception</div>
                <div class="module-count">Manage patients</div>
            </a>
            
            <a href="../laboratory/dashboard.php?branch=<?= $selected_branch_id ?>" class="module-card">
                <div class="flex items-start justify-between">
                    <div class="module-icon purple"><i class="fas fa-flask"></i></div>
                    <i class="fas fa-chevron-right module-arrow"></i>
                </div>
                <div class="module-name">Laboratory</div>
                <div class="module-count"><?= $pending_lab_tests ?> pending</div>
                <?php if ($pending_lab_tests > 0): ?>
                    <span class="module-badge orange"><?= $pending_lab_tests ?></span>
                <?php endif; ?>
            </a>
            
            <a href="../pharmacy/dashboard.php?branch=<?= $selected_branch_id ?>" class="module-card">
                <div class="flex items-start justify-between">
                    <div class="module-icon orange"><i class="fas fa-pills"></i></div>
                    <i class="fas fa-chevron-right module-arrow"></i>
                </div>
                <div class="module-name">Pharmacy</div>
                <div class="module-count"><?= $pending_prescriptions ?> pending</div>
                <?php if ($pending_prescriptions > 0): ?>
                    <span class="module-badge orange"><?= $pending_prescriptions ?></span>
                <?php endif; ?>
            </a>
            
            <a href="../cashier/dashboard.php?branch=<?= $selected_branch_id ?>" class="module-card">
                <div class="flex items-start justify-between">
                    <div class="module-icon teal"><i class="fas fa-cash-register"></i></div>
                    <i class="fas fa-chevron-right module-arrow"></i>
                </div>
                <div class="module-name">Cashier</div>
                <div class="module-count">Process payments</div>
            </a>
            
            <a href="reports.php?branch=<?= $selected_branch_id ?>" class="module-card">
                <div class="flex items-start justify-between">
                    <div class="module-icon red"><i class="fas fa-chart-bar"></i></div>
                    <i class="fas fa-chevron-right module-arrow"></i>
                </div>
                <div class="module-name">Reports</div>
                <div class="module-count">View analytics</div>
            </a>
            
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- CHART - Revenue (Height: 120px) -->
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
                    <div class="flex items-start gap-3 p-2 rounded-lg hover:bg-blue-50 transition">
                        <div class="w-7 h-7 rounded-full bg-blue-600 flex items-center justify-center flex-shrink-0 mt-0.5 text-white">
                            <i class="fas fa-circle text-[6px]"></i>
                        </div>
                        <div>
                            <p class="font-medium text-sm text-gray-800"><?= htmlspecialchars($activity['action'] ?? 'Action') ?></p>
                            <p class="text-xs text-gray-500"><?= htmlspecialchars($activity['details'] ?? '') ?></p>
                            <p class="text-[10px] text-gray-400 mt-0.5">
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
            <button onclick="downloadPDF()" class="btn btn-blue btn-sm">
                <i class="fas fa-file-pdf"></i> PDF
            </button>
            <button onclick="exportExcel()" class="btn btn-green btn-sm">
                <i class="fas fa-file-excel"></i> Excel
            </button>
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
            Super Admin Dashboard v2.0
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
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
        }
    });

    // ================================================================
    // DOM ELEMENTS
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    var refreshBtn = document.getElementById('refreshBtn');
    var branchSelector = document.getElementById('branchSelector');
    var toast = document.getElementById('toast');

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
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

    // ================================================================
    // SEARCH
    // ================================================================
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

    // ================================================================
    // BRANCH SWITCHER
    // ================================================================
    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        window.location.href = url.toString();
    }

    // ================================================================
    // REFRESH
    // ================================================================
    function refreshData() {
        if (refreshBtn) {
            refreshBtn.innerHTML = '<span class="spinner"></span> Refreshing...';
            refreshBtn.disabled = true;
        }
        setTimeout(function() { location.reload(); }, 800);
    }

    // ================================================================
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        
        toast.className = 'toast-custom ' + type;
        toastTitle.textContent = title;
        toastMessage.textContent = message;
        toast.style.display = 'flex';
        
        toast.classList.add('show');
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 3500);
    }

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
                            backgroundColor: '#D2E3FC',
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: '#0B5ED7',
                            pointBorderColor: '#0B5ED7',
                            pointRadius: 4,
                            pointHoverRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
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
                                    }
                                },
                                grid: { color: '#E2E8F0' }
                            },
                            x: { grid: { display: false } }
                        },
                        interaction: { intersect: false, mode: 'index' }
                    }
                });
            }
        }
    });

    // ================================================================
    // DATE & TIME
    // ================================================================
    function updateDateTime() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        document.getElementById('currentDateTime').textContent = dateStr + ' • ' + timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // DOWNLOAD & EXPORT
    // ================================================================
    function downloadPDF() {
        showToast('Downloading PDF', 'Generating PDF report...', 'info');
        var branch = '<?= $selected_branch_id ?>';
        window.location.href = 'reports.php?export=pdf&branch=' + branch;
    }
    
    function exportExcel() {
        showToast('Exporting Excel', 'Preparing Excel export...', 'info');
        var branch = '<?= $selected_branch_id ?>';
        window.location.href = 'reports.php?export=excel&branch=' + branch;
    }

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            searchInput?.focus();
        }
        if (e.key === 'F5' || (e.ctrlKey && e.key === 'r')) {
            e.preventDefault();
            showToast('Manual Refresh', 'Click the Refresh button to reload data', 'info');
        }
    });

    // ================================================================
    // WELCOME
    // ================================================================
    setTimeout(function() {
        showToast('Welcome', 'Super Admin Dashboard loaded successfully', 'success');
    }, 500);

    console.log('%c🏥 Braick Dispensary - Super Admin Dashboard', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👋 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Total Employees: <?= number_format($total_employees) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📈 Chart Height: 120px', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🔄 Auto Refresh: DISABLED', 'font-size:13px; color:#EF4444;');
    console.log('%c🌙 Dark Mode: ' + (localStorage.getItem('darkMode') === 'true' ? 'ON' : 'OFF'), 'font-size:13px; color:#64748B;');
</script>

</body>
</html>