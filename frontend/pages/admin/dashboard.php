<?php
// ================================================================
// FILE: frontend/pages/admin/dashboard.php
// SUPER ADMIN DASHBOARD - NO AUTO REFRESH
// BRAICK DISPENSARY
// ================================================================

session_start();

// Check if logged in as super admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit;
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
// FETCH STATISTICS
// ================================================================
$today = date('Y-m-d');

// Total Patients
$stmt = $db->query("SELECT COUNT(*) as count FROM patients");
$total_patients = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Today's Patients
$stmt = $db->prepare("SELECT COUNT(*) as count FROM patients WHERE DATE(created_at) = ?");
$stmt->execute([$today]);
$today_patients = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Total Revenue
$stmt = $db->query("SELECT COALESCE(SUM(total), 0) as revenue FROM pharmacy_sales WHERE payment_status = 'paid'");
$total_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['revenue'] ?? 0;

// Today's Revenue
$stmt = $db->prepare("SELECT COALESCE(SUM(total), 0) as revenue FROM pharmacy_sales WHERE DATE(sale_date) = ? AND payment_status = 'paid'");
$stmt->execute([$today]);
$today_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['revenue'] ?? 0;

// Total Doctors
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'doctor' AND status = 'active'");
$total_doctors = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Total Employees
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role != 'admin'");
$total_employees = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Today's Appointments
$stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE DATE(appointment_date) = ? AND status IN ('scheduled', 'confirmed')");
$stmt->execute([$today]);
$today_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Total Branches
$stmt = $db->query("SELECT COUNT(*) as count FROM branches WHERE status = 'active'");
$total_branches = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Pending Prescriptions
$stmt = $db->query("SELECT COUNT(*) as count FROM prescriptions WHERE status = 'pending'");
$pending_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Low Stock Medicines
$stmt = $db->query("SELECT COUNT(*) as count FROM medications_inventory WHERE quantity <= reorder_level AND status = 'active'");
$low_stock = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Pending Lab Tests
$stmt = $db->query("SELECT COUNT(*) as count FROM lab_tests WHERE status = 'pending'");
$pending_lab_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

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
$stmt = $db->query("
    SELECT p.*, b.name as branch_name 
    FROM patients p
    LEFT JOIN branches b ON p.branch_id = b.id
    ORDER BY p.created_at DESC
    LIMIT 5
");
$recent_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('D', strtotime($date));
    
    $stmt = $db->prepare("SELECT COALESCE(SUM(total), 0) as revenue FROM pharmacy_sales WHERE DATE(sale_date) = ? AND payment_status = 'paid'");
    $stmt->execute([$date]);
    $rev = $stmt->fetch(PDO::FETCH_ASSOC);
    $chart_values[] = (float)$rev['revenue'];
}

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED SIDEBAR
// ================================================================
// Variables needed by sidebar
$total_employees = $total_employees ?? 0;
$total_doctors = $total_doctors ?? 0;
$total_branches = $total_branches ?? 0;
$pending_lab_tests = $pending_lab_tests ?? 0;
$pending_prescriptions = $pending_prescriptions ?? 0;
$selected_branch_id = $selected_branch_id ?? 'all';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <style>
        :root {
            --blue-600: #0B5ED7;
            --blue-700: #0B4EA8;
            --green-600: #059669;
            --gray-50: #F8FAFC;
            --gray-100: #F1F5F9;
            --gray-200: #E2E8F0;
            --gray-300: #CBD5E1;
            --gray-400: #94A3B8;
            --gray-500: #64748B;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1E293B;
            --white: #FFFFFF;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: var(--gray-50);
            color: var(--gray-800);
        }
        
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--gray-100); }
        ::-webkit-scrollbar-thumb { background: var(--blue-600); border-radius: 10px; }
        
        /* ================================================================
           TOP NAV - WHITE
           ================================================================ */
        .top-nav {
            position: fixed; top: 0; left: 270px; right: 0;
            height: 68px; background: white; z-index: 40;
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 24px; border-bottom: 2px solid #D2E3FC;
        }
        
        .top-nav .search-wrapper {
            display: flex; align-items: center;
            background: var(--gray-50); border-radius: 10px;
            border: 2px solid var(--gray-200);
            transition: all 0.3s;
        }
        
        .top-nav .search-wrapper:focus-within {
            border-color: var(--blue-600);
            box-shadow: 0 0 0 3px #D2E3FC;
        }
        
        .top-nav .search-wrapper input {
            border: none; background: transparent;
            padding: 8px 14px; width: 280px;
            font-size: 0.85rem; outline: none;
            color: var(--gray-700);
        }
        
        .top-nav .search-wrapper input::placeholder { color: var(--gray-400); }
        
        .top-nav .search-wrapper .search-btn {
            background: var(--blue-600); color: white;
            border: none; padding: 8px 16px;
            border-radius: 0 10px 10px 0;
            cursor: pointer; font-size: 0.85rem;
            transition: all 0.3s;
        }
        
        .top-nav .search-wrapper .search-btn:hover { background: #0B3D8A; }
        
        .top-nav .branch-selector {
            border: 2px solid var(--gray-200);
            border-radius: 10px; padding: 6px 12px;
            background: white; font-size: 0.82rem;
            font-weight: 500; cursor: pointer; outline: none;
            min-width: 180px; color: var(--gray-700);
            transition: all 0.3s;
        }
        
        .top-nav .branch-selector:focus {
            border-color: var(--blue-600);
            box-shadow: 0 0 0 3px #D2E3FC;
        }
        
        .top-nav .datetime {
            font-size: 0.78rem; color: var(--gray-500); font-weight: 500;
        }
        
        .top-nav .avatar {
            width: 38px; height: 38px; border-radius: 50%;
            object-fit: cover; border: 2px solid var(--gray-200);
            cursor: pointer; transition: all 0.3s;
        }
        
        .top-nav .avatar:hover { border-color: var(--blue-600); }
        
        .top-nav .icon-btn {
            width: 38px; height: 38px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: var(--gray-500); transition: all 0.3s;
            background: transparent; border: none; cursor: pointer;
            position: relative;
        }
        
        .top-nav .icon-btn:hover { background: #E8F0FE; color: var(--blue-600); }
        
        .notif-dot {
            position: absolute; top: 6px; right: 6px;
            width: 8px; height: 8px; background: var(--green-600);
            border-radius: 50%; border: 2px solid white;
        }
        
        /* ================================================================
           MAIN CONTENT
           ================================================================ */
        .main-content {
            margin-left: 270px; margin-top: 68px;
            padding: 24px 28px;
            min-height: calc(100vh - 68px);
        }
        
        /* ================================================================
           STAT CARDS - BLUE & GREEN
           ================================================================ */
        .stat-card {
            border-radius: 16px;
            padding: 18px 20px;
            border: none;
            transition: all 0.3s;
            color: white;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .stat-card.blue { background: var(--blue-600); }
        .stat-card.blue-dark { background: #0B3D8A; }
        .stat-card.blue-light { background: #1A73E8; }
        .stat-card.green { background: var(--green-600); }
        .stat-card.green-dark { background: #047857; }
        .stat-card.green-light { background: #0AA84F; }
        
        .stat-card .stat-icon {
            width: 42px; height: 42px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem;
            background: rgba(255,255,255,0.15);
            color: white;
        }
        
        .stat-card .stat-number {
            font-size: 1.8rem; font-weight: 700; color: white;
        }
        
        .stat-card .stat-label {
            font-size: 0.75rem; color: rgba(255,255,255,0.8); font-weight: 500;
        }
        
        .stat-card .stat-trend {
            font-size: 0.65rem; font-weight: 600;
            padding: 2px 10px; border-radius: 20px;
            background: rgba(255,255,255,0.15);
            color: white;
        }
        
        /* ================================================================
           CARDS
           ================================================================ */
        .card {
            background: white;
            border-radius: 16px;
            padding: 18px 20px;
            border: 2px solid var(--gray-200);
            transition: all 0.3s;
        }
        
        .card:hover {
            border-color: var(--blue-600);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.08);
        }
        
        .card-header {
            display: flex; justify-content: space-between;
            align-items: center; margin-bottom: 12px;
            flex-wrap: wrap; gap: 8px;
        }
        
        .card-title {
            font-size: 0.9rem; font-weight: 600; color: var(--gray-800);
        }
        
        .card-title .title-blue { color: var(--blue-600); }
        .card-title .title-green { color: var(--green-600); }
        
        /* ================================================================
           MODULE NAVIGATION CARDS
           ================================================================ */
        .module-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            border: 2px solid var(--gray-200);
            transition: all 0.3s;
            text-decoration: none;
            color: var(--gray-800);
            display: block;
            position: relative;
            overflow: hidden;
        }
        
        .module-card:hover {
            transform: translateY(-4px);
            border-color: var(--blue-600);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .module-card .module-icon {
            width: 52px; height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            color: white;
        }
        
        .module-card .module-icon.blue { background: var(--blue-600); }
        .module-card .module-icon.green { background: var(--green-600); }
        .module-card .module-icon.purple { background: #7B2FBE; }
        .module-card .module-icon.orange { background: #F59E0B; }
        .module-card .module-icon.red { background: #EF4444; }
        .module-card .module-icon.teal { background: #0D9488; }
        
        .module-card .module-name {
            font-size: 0.95rem;
            font-weight: 600;
            margin-top: 10px;
        }
        
        .module-card .module-count {
            font-size: 0.75rem;
            color: var(--gray-500);
        }
        
        .module-card .module-arrow {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-300);
            transition: all 0.3s;
        }
        
        .module-card:hover .module-arrow {
            color: var(--blue-600);
            transform: translateY(-50%) translateX(4px);
        }
        
        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 7px 16px; border-radius: 10px;
            font-weight: 600; font-size: 0.78rem;
            transition: all 0.3s; cursor: pointer;
            border: none; text-decoration: none;
        }
        
        .btn-blue {
            background: var(--blue-600); color: white;
        }
        .btn-blue:hover {
            background: #0B3D8A;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .btn-green {
            background: var(--green-600); color: white;
        }
        .btn-green:hover {
            background: #047857;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
        }
        
        .btn-outline {
            background: transparent; color: var(--gray-600);
            border: 2px solid var(--gray-200);
        }
        .btn-outline:hover {
            background: #E8F0FE;
            border-color: var(--blue-600);
            color: var(--blue-600);
        }
        
        .btn-sm { padding: 3px 10px; font-size: 0.7rem; border-radius: 6px; }
        
        /* ================================================================
           TABLES
           ================================================================ */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
        }
        
        .data-table th {
            text-align: left;
            padding: 8px 12px;
            font-weight: 600;
            color: var(--gray-500);
            font-size: 0.6rem;
            text-transform: uppercase;
            border-bottom: 2px solid #D2E3FC;
        }
        
        .data-table td {
            padding: 8px 12px;
            border-bottom: 1px solid var(--gray-100);
            color: var(--gray-700);
        }
        
        .data-table tr:hover td {
            background: #E8F0FE;
        }
        
        /* ================================================================
           BADGES
           ================================================================ */
        .badge {
            padding: 3px 10px; border-radius: 20px;
            font-size: 0.65rem; font-weight: 600;
            display: inline-flex; align-items: center; gap: 4px;
            color: white;
            border: none;
        }
        
        .badge-blue { background: var(--blue-600); }
        .badge-green { background: var(--green-600); }
        .badge-gray { background: var(--gray-400); }
        
        /* ================================================================
           PAGE HEADER
           ================================================================ */
        .page-header {
            border-bottom: 3px solid var(--blue-600);
            padding-bottom: 12px;
        }
        
        .page-header .page-title {
            color: #0B3D8A;
            font-size: 1.8rem;
            font-weight: 700;
        }
        
        .page-header .page-subtitle {
            color: var(--gray-500);
            font-size: 0.9rem;
        }
        
        .page-header .branch-tag {
            background: var(--green-600);
            color: white;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        /* ================================================================
           FOOTER
           ================================================================ */
        .footer {
            padding: 14px 0;
            border-top: 2px solid var(--gray-200);
            margin-top: 20px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--gray-500);
        }
        
        .footer .footer-brand {
            color: var(--blue-600);
            font-weight: 600;
        }
        
        /* ================================================================
           TOAST
           ================================================================ */
        .toast-custom {
            position: fixed; bottom: 24px; right: 24px;
            padding: 12px 18px; border-radius: 12px;
            z-index: 999; max-width: 360px;
            transform: translateY(100px); opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex; align-items: center; gap: 10px;
            color: white;
        }
        
        .toast-custom.show { transform: translateY(0); opacity: 1; }
        .toast-custom.success { background: var(--green-600); }
        .toast-custom.error { background: #EF4444; }
        .toast-custom.info { background: var(--blue-600); }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper input { width: 160px; }
        }
        
        @media (max-width: 768px) {
            .stat-card .stat-number { font-size: 1.6rem; }
        }
        
        @media (max-width: 640px) {
            .top-nav .search-wrapper input { width: 100px; }
            .top-nav .branch-selector { min-width: 120px; font-size: 0.7rem; }
            .top-nav .datetime { display: none; }
            .main-content { padding: 10px; }
            .stat-card .stat-number { font-size: 1.4rem; }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.4s ease forwards;
            opacity: 0;
        }
        
        .animate-fade-in-up:nth-child(1) { animation-delay: 0.03s; }
        .animate-fade-in-up:nth-child(2) { animation-delay: 0.06s; }
        .animate-fade-in-up:nth-child(3) { animation-delay: 0.09s; }
        .animate-fade-in-up:nth-child(4) { animation-delay: 0.12s; }
        .animate-fade-in-up:nth-child(5) { animation-delay: 0.15s; }
        .animate-fade-in-up:nth-child(6) { animation-delay: 0.18s; }
        .animate-fade-in-up:nth-child(7) { animation-delay: 0.21s; }
        .animate-fade-in-up:nth-child(8) { animation-delay: 0.24s; }
        
        .spinner {
            display: inline-block;
            width: 14px; height: 14px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- SHARED SIDEBAR - SAME FOR ALL PAGES -->
<!-- ================================================================ -->
<?php include_once '../../components/admin_sidebar.php'; ?>

<!-- ================================================================ -->
<!-- TOP NAVIGATION -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <div class="search-wrapper">
            <input type="text" id="searchInput" placeholder="Search patients, doctors, medicines...">
            <button id="searchBtn" class="search-btn"><i class="fas fa-search"></i></button>
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
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $logo_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2238%22 height=%2238%22%3E%3Crect width=%2238%22 height=%2238%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2219%22 y=%2225%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3EA%3C/text%3E%3C/svg%3E'">
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
            <h1 class="page-title">Super Admin Dashboard</h1>
            <p class="page-subtitle">
                Welcome back, <?= htmlspecialchars($_SESSION['full_name'] ?? 'Admin') ?>!
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name) ?>
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
                    <p class="stat-label">Total Doctors</p>
                    <p class="stat-number"><?= number_format($total_doctors) ?></p>
                    <span class="stat-trend"><i class="fas fa-arrow-up"></i> Active</span>
                </div>
                <div class="stat-icon"><i class="fas fa-user-md"></i></div>
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
        <h2 class="text-lg font-semibold text-gray-800 mb-4">
            <i class="fas fa-th-large text-[#0B5ED7] mr-2"></i> Module Navigation
            <span class="text-sm font-normal text-gray-400">(Click to access any module)</span>
        </h2>
        
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
            
            <a href="../doctor/dashboard.php?branch=<?= $selected_branch_id ?>" class="module-card">
                <div class="flex items-start justify-between">
                    <div class="module-icon blue"><i class="fas fa-user-md"></i></div>
                    <i class="fas fa-chevron-right module-arrow"></i>
                </div>
                <div class="module-name">Doctors</div>
                <div class="module-count"><?= $total_doctors ?> active</div>
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
            </a>
            
            <a href="../pharmacy/dashboard.php?branch=<?= $selected_branch_id ?>" class="module-card">
                <div class="flex items-start justify-between">
                    <div class="module-icon orange"><i class="fas fa-pills"></i></div>
                    <i class="fas fa-chevron-right module-arrow"></i>
                </div>
                <div class="module-name">Pharmacy</div>
                <div class="module-count"><?= $pending_prescriptions ?> pending</div>
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
    <!-- CHART - Revenue -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-chart-line title-blue mr-2"></i> Revenue Overview (Last 7 Days)
                <span class="text-xs text-gray-400 font-normal">TSh <?= number_format(array_sum($chart_values)) ?> total</span>
            </h3>
        </div>
        <canvas id="revenueChart" height="200"></canvas>
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
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const searchBtn = document.getElementById('searchBtn');
    const searchInput = document.getElementById('searchInput');
    const refreshBtn = document.getElementById('refreshBtn');
    const branchSelector = document.getElementById('branchSelector');
    const toast = document.getElementById('toast');

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
    // REFRESH - MANUAL ONLY (NO AUTO REFRESH)
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
    var ctx = document.getElementById('revenueChart')?.getContext('2d');
    if (ctx) {
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

    // ================================================================
    // DATE & TIME - UPDATES EVERY SECOND (CLOCK ONLY, NOT PAGE)
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
        // F5 or Ctrl+R - Show message instead of refreshing
        if (e.key === 'F5' || (e.ctrlKey && e.key === 'r')) {
            e.preventDefault();
            showToast('Manual Refresh', 'Click the Refresh button to reload data', 'info');
        }
    });

    // ================================================================
    // WELCOME
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            showToast('Welcome', 'Super Admin Dashboard loaded successfully', 'success');
        }, 300);
    });

    console.log('%c🏥 Braick Dispensary - Super Admin Dashboard', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👋 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Total Patients: <?= number_format($total_patients) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c💰 Total Revenue: TSh <?= number_format($total_revenue) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🔄 Auto Refresh: DISABLED', 'font-size:13px; color:#EF4444;');
    console.log('%c🔗 Shared Sidebar: ACTIVE', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>