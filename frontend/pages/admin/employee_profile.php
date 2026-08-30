<?php
// ================================================================
// FILE: frontend/pages/admin/employee_profile.php
// SUPER ADMIN - EMPLOYEE PROFILE
// BRAICK DISPENSARY - FIXED FOR EXISTING DATABASE
// WITH SESSION MANAGEMENT & LOGIN PROTECTION
// ================================================================

// ================================================================
// START SESSION
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../../auth/login.php');
    exit;
}

// ================================================================
// ROLE CHECK - ONLY ADMIN CAN ACCESS
// ================================================================
if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../../auth/login.php'); break;
    }
    exit;
}

// ================================================================
// GET ADMIN DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// GET EMPLOYEE ID
// ================================================================
$employee_id = (int)($_GET['id'] ?? 0);
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($employee_id <= 0) {
    header('Location: employees.php?branch=' . $selected_branch_id);
    exit;
}

// ================================================================
// GET EMPLOYEE DATA
// ================================================================
$stmt = $db->prepare("
    SELECT u.*, b.name as branch_name 
    FROM users u
    LEFT JOIN branches b ON u.branch_id = b.id
    WHERE u.id = ? AND u.role != 'admin'
");
$stmt->execute([$employee_id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    header('Location: employees.php?branch=' . $selected_branch_id);
    exit;
}

// ================================================================
// AVAILABLE ROLES (from users table ENUM)
// ================================================================
$available_roles = [
    'doctor' => ['name' => 'Medical Doctor', 'icon' => 'fa-user-md', 'color' => '#059669'],
    'pharmacy' => ['name' => 'Pharmacy Staff', 'icon' => 'fa-prescription-bottle', 'color' => '#7C3AED'],
    'reception' => ['name' => 'Receptionist', 'icon' => 'fa-headset', 'color' => '#0B5ED7'],
    'laboratory' => ['name' => 'Lab Technician', 'icon' => 'fa-flask', 'color' => '#0D9488'],
    'cashier' => ['name' => 'Cashier', 'icon' => 'fa-cash-register', 'color' => '#D97706'],
    'admin' => ['name' => 'Administrator', 'icon' => 'fa-user-tie', 'color' => '#DC2626']
];

// ================================================================
// GET BRANCHES FOR SELECTOR
// ================================================================
$branches_list = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
$branches_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STATISTICS FOR SIDEBAR
// ================================================================
$total_employees = 0;
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role != 'admin'");
$total_employees = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$total_doctors = 0;
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'doctor' AND status = 'active'");
$total_doctors = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$total_branches = 0;
$stmt = $db->query("SELECT COUNT(*) as count FROM branches WHERE status = 'active'");
$total_branches = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$pending_lab_tests = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM lab_tests WHERE status = 'pending'");
    $pending_lab_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {
    $pending_lab_tests = 0;
}

$pending_prescriptions = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM prescriptions WHERE status = 'pending'");
    $pending_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {
    $pending_prescriptions = 0;
}

// ================================================================
// BRANCH NAME FOR DISPLAY
// ================================================================
$branch_name = 'All Branches';
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $branch_id = (int)$selected_branch_id;
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) {
        $branch_name = $branch_data['name'];
    }
}

// ================================================================
// GET BRANCH STAFF COUNT
// ================================================================
$branch_staff_count = 0;
if (!empty($employee['branch_id'])) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE branch_id = ? AND role != 'admin'");
        $stmt->execute([$employee['branch_id']]);
        $branch_staff_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    } catch (Exception $e) {
        $branch_staff_count = 0;
    }
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE HEADERS
// ================================================================
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Profile - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_url ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #0B5ED7;
            --primary-bg: #E8F0FE;
            --success: #059669;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --bg-body: #F0F4F8;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --radius: 12px;
            --radius-lg: 18px;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --primary: #3B82F6;
            --primary-bg: #1E3A5F;
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        .top-nav {
            position: fixed;
            top: 0;
            left: 270px;
            right: 0;
            height: 68px;
            background: var(--bg-nav);
            z-index: 40;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            border-bottom: 2px solid var(--border-color);
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow-sm);
        }
        
        .top-nav .search-wrapper {
            display: flex;
            align-items: center;
            background: var(--bg-body);
            border-radius: var(--radius);
            border: 2px solid var(--border-color);
            flex: 1;
            max-width: 500px;
        }
        
        .top-nav .search-wrapper input {
            border: none;
            background: transparent;
            padding: 8px 14px;
            width: 100%;
            font-size: 0.85rem;
            outline: none;
            color: var(--text-primary);
        }
        
        .top-nav .search-wrapper .search-btn {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 0 var(--radius) var(--radius) 0;
            cursor: pointer;
            font-size: 0.85rem;
        }
        
        .top-nav .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-color);
            cursor: pointer;
        }
        
        .top-nav .icon-btn {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            background: transparent;
            border: none;
            cursor: pointer;
            position: relative;
        }
        
        .notif-dot {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            border: 2px solid var(--bg-nav);
            animation: pulse-dot 2s infinite;
        }
        
        .notif-dot.has-notif { background: var(--danger); }
        .notif-dot.no-notif { background: var(--gray-400); animation: none; }
        
        @keyframes pulse-dot {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }
        
        .dark-toggle-btn {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            padding: 6px 12px;
            cursor: pointer;
            font-size: 0.82rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .branch-selector {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            padding: 6px 12px;
            font-size: 0.78rem;
            color: var(--text-primary);
            outline: none;
            cursor: pointer;
        }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        .page-header {
            border-bottom: 3px solid var(--primary);
            padding-bottom: 16px;
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }
        
        .page-header .page-title {
            color: var(--primary);
            font-size: 1.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .page-header .page-title i { font-size: 2rem; opacity: 0.9; }
        
        .page-header .page-subtitle {
            color: var(--text-secondary);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .page-header .branch-tag {
            background: var(--primary);
            color: white;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 24px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
        }
        
        .card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        .card-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--primary);
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 12px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        [data-theme="dark"] .card-title {
            color: #6EA8FE;
        }
        
        .info-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .info-value {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .badge {
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: white;
            border: none;
        }
        
        .badge-success { background: var(--success); }
        .badge-danger { background: var(--danger); }
        .badge-info { background: var(--primary); }
        .badge-warning { background: var(--warning); color: #1E293B; }
        .badge-purple { background: #7C3AED; }
        
        [data-theme="dark"] .badge-warning { color: #1E293B; }
        
        .role-badge {
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--primary-bg);
            color: var(--primary);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .role-badge:hover {
            transform: scale(1.02);
            box-shadow: 0 2px 8px rgba(11, 94, 215, 0.15);
        }
        
        .stat-box {
            text-align: center;
            padding: 16px;
            border-radius: var(--radius);
            background: var(--bg-body);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .stat-box:hover {
            border-color: var(--primary);
            background: var(--primary-bg);
            transform: translateY(-2px);
        }
        
        .stat-box .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .stat-box .stat-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 8px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
            min-height: 38px;
        }
        
        .btn:hover { transform: translateY(-2px); }
        
        .btn-green {
            background: var(--success);
            color: white;
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
        }
        
        .btn-green:hover { box-shadow: 0 8px 20px rgba(5, 150, 105, 0.4); }
        
        .btn-outline {
            background: transparent;
            color: var(--text-primary);
            border: 2px solid var(--border-color);
        }
        
        .btn-outline:hover {
            background: var(--bg-body);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .btn-sm {
            padding: 6px 14px;
            font-size: 0.75rem;
            min-height: 32px;
        }
        
        .empty-state {
            background: var(--warning-bg);
            border: 1px solid var(--warning);
            border-radius: 10px;
            padding: 14px 18px;
            color: #92400E;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        [data-theme="dark"] .empty-state {
            background: #3A2A1A;
            border-color: #F59E0B;
            color: #FBBF24;
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: 700;
            background: var(--primary-bg);
            color: var(--primary);
            border: 4px solid var(--primary);
            flex-shrink: 0;
        }
        
        .footer {
            padding: 14px 0;
            border-top: 2px solid var(--border-color);
            margin-top: 20px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        .grid { display: grid; }
        .grid-cols-1 { grid-template-columns: 1fr; }
        .grid-cols-2 { grid-template-columns: 1fr 1fr; }
        .lg\:grid-cols-2 { grid-template-columns: 1fr 1fr; }
        .gap-4 { gap: 16px; }
        .gap-5 { gap: 20px; }
        .mb-5 { margin-bottom: 20px; }
        .mt-2 { margin-top: 8px; }
        .mt-3 { margin-top: 12px; }
        .space-y-3 > * + * { margin-top: 12px; }
        .space-y-5 > * + * { margin-top: 20px; }
        .flex { display: flex; }
        .flex-wrap { flex-wrap: wrap; }
        .flex-col { flex-direction: column; }
        .items-center { align-items: center; }
        .justify-center { justify-content: center; }
        .justify-between { justify-content: space-between; }
        .gap-2 { gap: 8px; }
        .gap-3 { gap: 12px; }
        .gap-6 { gap: 24px; }
        .text-center { text-align: center; }
        .text-xs { font-size: 0.65rem; }
        .text-sm { font-size: 0.75rem; }
        .text-2xl { font-size: 1.5rem; }
        .font-bold { font-weight: 700; }
        .font-normal { font-weight: 400; }
        .capitalize { text-transform: capitalize; }
        .text-gray-400 { color: var(--text-secondary); }
        .text-gray-500 { color: var(--text-secondary); }
        .text-gray-800 { color: var(--text-primary); }
        .ml-1 { margin-left: 4px; }
        .ml-2 { margin-left: 8px; }
        .mr-1 { margin-right: 4px; }
        .mr-2 { margin-right: 8px; }
        .md\:flex-row { flex-direction: row; }
        .md\:text-left { text-align: left; }
        .md\:justify-start { justify-content: flex-start; }
        
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 14px 20px;
            border-radius: var(--radius);
            z-index: 999;
            max-width: 400px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            box-shadow: var(--shadow-lg);
        }
        .toast-custom.show { transform: translateY(0); opacity: 1; }
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
        
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { flex-direction: column; align-items: flex-start !important; }
            .page-header .page-title { font-size: 1.3rem; }
            .card { padding: 16px; }
            .lg\:grid-cols-2 { grid-template-columns: 1fr; }
            .grid-cols-2 { grid-template-columns: 1fr; }
            .profile-avatar { width: 70px; height: 70px; font-size: 2rem; }
            .stat-box .stat-number { font-size: 1.2rem; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
        }
        
        @media print {
            .top-nav, .sidebar, .btn, .dark-toggle-btn, .icon-btn,
            .search-wrapper, .footer, #sidebarToggle { display: none !important; }
            .main-content { margin: 0; padding: 20px; }
            .card { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd; }
            .page-header { border-color: #0B5ED7 !important; }
            .badge { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        }
    </style>
</head>
<body>

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
            <input type="text" id="searchInput" placeholder="Search employees...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <select id="branchSelector" class="branch-selector" onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches_list as $branch): ?>
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
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($user_full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-user-circle"></i> Employee Profile
            </h1>
            <p class="page-subtitle">
                View employee details, role and branch information
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name) ?>
                </span>
                <span class="ml-2 inline-flex bg-primary-bg text-primary px-3 py-1 rounded-full text-xs border border-primary/20">
                    <i class="fas fa-user mr-1"></i> <?= htmlspecialchars($employee['full_name']) ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="edit_employee.php?id=<?= $employee['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-green btn-sm">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="employees.php?branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PROFILE HEADER -->
    <!-- ================================================================ -->
    <div class="card mb-5 animate-fade-in-up">
        <div class="flex flex-col md:flex-row items-center gap-6">
            <div class="profile-avatar">
                <?= strtoupper(substr($employee['full_name'], 0, 1)) ?>
            </div>
            
            <div class="flex-1 text-center md:text-left">
                <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-200"><?= htmlspecialchars($employee['full_name']) ?></h2>
                <p class="text-gray-500 dark:text-gray-400">
                    <i class="fas fa-briefcase mr-1"></i> 
                    <?= htmlspecialchars($available_roles[$employee['role']]['name'] ?? ucfirst($employee['role'])) ?>
                </p>
                <div class="flex flex-wrap gap-2 mt-2 justify-center md:justify-start">
                    <span class="badge badge-info">
                        <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($employee['branch_name'] ?? 'Not Assigned') ?>
                    </span>
                    <span class="badge <?= ($employee['status'] ?? 'active') === 'active' ? 'badge-success' : 'badge-danger' ?>">
                        <i class="fas fa-circle text-[6px]"></i> <?= ucfirst($employee['status'] ?? 'Active') ?>
                    </span>
                    <span class="badge badge-warning">
                        <i class="fas fa-id-card mr-1"></i> <?= htmlspecialchars($employee['username']) ?>
                    </span>
                    <?php if (!empty($employee['specialty'])): ?>
                        <span class="badge badge-purple">
                            <i class="fas fa-stethoscope mr-1"></i> <?= htmlspecialchars($employee['specialty']) ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATS ROW -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5 animate-fade-in-up">
        <div class="stat-box">
            <p class="stat-number">1</p>
            <p class="stat-label"><i class="fas fa-user-tag mr-1"></i> Role</p>
        </div>
        <div class="stat-box">
            <p class="stat-number"><?= $branch_staff_count ?></p>
            <p class="stat-label"><i class="fas fa-users mr-1"></i> Branch Staff</p>
        </div>
        <div class="stat-box">
            <p class="stat-number"><?= date('d/m/Y', strtotime($employee['created_at'])) ?></p>
            <p class="stat-label"><i class="fas fa-calendar-plus mr-1"></i> Joined</p>
        </div>
        <div class="stat-box">
            <p class="stat-number">
                <?php 
                    $online_status = ($employee['is_online'] ?? 0) == 1 ? '🟢' : '⚪';
                    echo $online_status;
                ?>
            </p>
            <p class="stat-label"><i class="fas fa-circle mr-1"></i> Status</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- DETAILS GRID -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        
        <!-- PERSONAL INFORMATION -->
        <div class="card animate-fade-in-up">
            <h3 class="card-title">
                <i class="fas fa-user-circle"></i> Personal Information
            </h3>
            <div class="space-y-3">
                <div>
                    <p class="info-label">Full Name</p>
                    <p class="info-value"><?= htmlspecialchars($employee['full_name']) ?></p>
                </div>
                <div>
                    <p class="info-label">Username</p>
                    <p class="info-value"><?= htmlspecialchars($employee['username']) ?></p>
                </div>
                <div>
                    <p class="info-label">Email</p>
                    <p class="info-value"><?= htmlspecialchars($employee['email']) ?></p>
                </div>
                <div>
                    <p class="info-label">Phone</p>
                    <p class="info-value"><?= htmlspecialchars($employee['phone'] ?? 'Not provided') ?></p>
                </div>
                <div>
                    <p class="info-label">Primary Role</p>
                    <p class="info-value capitalize"><?= htmlspecialchars($available_roles[$employee['role']]['name'] ?? ucfirst($employee['role'])) ?></p>
                </div>
                <?php if (!empty($employee['specialty'])): ?>
                    <div>
                        <p class="info-label">Specialty</p>
                        <p class="info-value"><?= htmlspecialchars($employee['specialty']) ?></p>
                    </div>
                <?php endif; ?>
                <div>
                    <p class="info-label">Branch</p>
                    <p class="info-value"><?= htmlspecialchars($employee['branch_name'] ?? 'Not Assigned') ?></p>
                </div>
                <div>
                    <p class="info-label">Status</p>
                    <p class="info-value">
                        <span class="badge <?= ($employee['status'] ?? 'active') === 'active' ? 'badge-success' : 'badge-danger' ?>">
                            <i class="fas fa-circle text-[6px]"></i> <?= ucfirst($employee['status'] ?? 'Active') ?>
                        </span>
                    </p>
                </div>
                <div>
                    <p class="info-label">Online Status</p>
                    <p class="info-value">
                        <?php if (($employee['is_online'] ?? 0) == 1): ?>
                            <span class="badge badge-success"><i class="fas fa-circle mr-1"></i> Online</span>
                        <?php else: ?>
                            <span class="badge badge-danger"><i class="fas fa-circle mr-1"></i> Offline</span>
                        <?php endif; ?>
                    </p>
                </div>
                <div>
                    <p class="info-label">Joined</p>
                    <p class="info-value"><?= date('F d, Y h:i A', strtotime($employee['created_at'])) ?></p>
                </div>
            </div>
        </div>

        <!-- ROLE INFORMATION -->
        <div class="card animate-fade-in-up">
            <h3 class="card-title">
                <i class="fas fa-user-tag"></i> Role Information
            </h3>
            
            <div class="space-y-3">
                <div>
                    <p class="info-label">Primary Role</p>
                    <p class="info-value">
                        <span class="role-badge">
                            <i class="fas <?= $available_roles[$employee['role']]['icon'] ?? 'fa-user' ?>" 
                               style="color: <?= $available_roles[$employee['role']]['color'] ?? '#0B5ED7' ?>"></i>
                            <?= htmlspecialchars($available_roles[$employee['role']]['name'] ?? ucfirst($employee['role'])) ?>
                        </span>
                    </p>
                </div>
                
                <?php if (!empty($employee['specialty'])): ?>
                    <div>
                        <p class="info-label">Specialty / Department</p>
                        <p class="info-value">
                            <span class="role-badge" style="background:var(--success-bg);color:var(--success);">
                                <i class="fas fa-stethoscope"></i>
                                <?= htmlspecialchars($employee['specialty']) ?>
                            </span>
                        </p>
                    </div>
                <?php endif; ?>
                
                <div>
                    <p class="info-label">Role Description</p>
                    <p class="info-value text-sm text-gray-500 dark:text-gray-400">
                        <?php 
                            $role_desc = [
                                'doctor' => 'Provides medical consultations, diagnoses, and treatments to patients.',
                                'pharmacy' => 'Dispenses medications and manages pharmacy inventory.',
                                'reception' => 'Handles patient registration, appointments, and front desk operations.',
                                'laboratory' => 'Conducts laboratory tests and analyzes samples.',
                                'cashier' => 'Handles patient billing, payments, and financial transactions.',
                                'admin' => 'Manages system settings, user accounts, and overall system operations.'
                            ];
                            echo htmlspecialchars($role_desc[$employee['role']] ?? 'No description available.');
                        ?>
                    </p>
                </div>
                
                <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--border-color);">
                    <p class="info-label">Role Key</p>
                    <p class="info-value">
                        <code style="background:var(--bg-body);padding:4px 12px;border-radius:6px;font-size:0.8rem;">
                            <?= htmlspecialchars($employee['role']) ?>
                        </code>
                    </p>
                </div>
                
                <div>
                    <p class="info-label">Available Roles</p>
                    <div class="flex flex-wrap gap-2 mt-1">
                        <?php foreach ($available_roles as $key => $role): ?>
                            <span class="text-xs px-2 py-1 rounded-full" 
                                  style="background:var(--bg-body);color:var(--text-secondary);border:1px solid var(--border-color);">
                                <?= htmlspecialchars($role['name']) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Employee Profile
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
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

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
            setTimeout(function() {
                toast.style.display = 'none';
            }, 400);
        }, 3500);
    }

    console.log('%c👤 Braick - Employee Profile', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📋 Employee: <?= htmlspecialchars($employee['full_name']) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($employee['branch_name'] ?? 'Not Assigned') ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c🔑 Role: <?= htmlspecialchars($employee['role']) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c✅ Using users table only (no roles or departments tables)', 'font-size:13px; color:#34D399;');
    console.log('%c🔒 Login protection: ACTIVE', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>