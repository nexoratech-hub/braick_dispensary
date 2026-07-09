<?php
// ================================================================
// CASHIER DASHBOARD - BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// BRANCH SELECTION - FROM URL PARAMETER
// ================================================================
// Force session for direct access (Cashier role)
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 106;
    $_SESSION['full_name'] = 'Cashier Peter Mushi';
    $_SESSION['role'] = 'cashier';
    $_SESSION['branch_id'] = 1;
}

// Get branch from URL parameter
$selected_branch_id = $_GET['branch'] ?? $_SESSION['branch_id'] ?? 1;

// If branch is passed via URL, update session
if (isset($_GET['branch']) && is_numeric($_GET['branch'])) {
    $_SESSION['branch_id'] = (int)$_GET['branch'];
    $selected_branch_id = (int)$_GET['branch'];
}

$user_branch_id = $selected_branch_id;

// Include database
$root_path = $_SERVER['DOCUMENT_ROOT'] . '/dispensary_system/';
require_once $root_path . 'backend/config/database.php';
require_once $root_path . 'backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// Get branch name
$branch_name = 'Default Branch';
$stmt = $db->prepare("SELECT name, location FROM branches WHERE id = ? AND status = 'active'");
$stmt->execute([$user_branch_id]);
$branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
if ($branch_data) {
    $branch_name = $branch_data['name'];
    $branch_location = $branch_data['location'] ?? '';
} else {
    // If branch not found, get first active branch
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' LIMIT 1");
    $default = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($default) {
        $branch_name = $default['name'];
        $_SESSION['branch_id'] = $default['id'];
        $user_branch_id = $default['id'];
    }
}

$today = date('Y-m-d');

// Today's Payments
$stmt = $db->prepare("SELECT COUNT(*) as count FROM payments WHERE DATE(payment_date) = ? AND branch_id = ? AND payment_status = 'paid'");
$stmt->execute([$today, $user_branch_id]);
$today_payments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Today's Revenue
$stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE DATE(payment_date) = ? AND branch_id = ? AND payment_status = 'paid'");
$stmt->execute([$today, $user_branch_id]);
$today_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Pending Payments
$stmt = $db->prepare("SELECT COUNT(*) as count FROM payments WHERE payment_status = 'pending' AND branch_id = ?");
$stmt->execute([$user_branch_id]);
$pending_payments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Total Payments
$stmt = $db->prepare("SELECT COUNT(*) as count FROM payments WHERE branch_id = ? AND payment_status = 'paid'");
$stmt->execute([$user_branch_id]);
$total_payments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Total Revenue
$stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE branch_id = ? AND payment_status = 'paid'");
$stmt->execute([$user_branch_id]);
$total_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Recent Payments
$recent_payments = [];
$stmt = $db->prepare("
    SELECT p.*, pat.full_name as patient_name
    FROM payments p
    LEFT JOIN patients pat ON p.patient_id = pat.id
    WHERE p.branch_id = ? AND p.payment_status = 'paid'
    ORDER BY p.payment_date DESC
    LIMIT 5
");
$stmt->execute([$user_branch_id]);
$recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Payment methods breakdown
$stmt = $db->prepare("
    SELECT payment_method, COUNT(*) as count, COALESCE(SUM(amount), 0) as total
    FROM payments
    WHERE DATE(payment_date) = ? AND branch_id = ? AND payment_status = 'paid'
    GROUP BY payment_method
");
$stmt->execute([$today, $user_branch_id]);
$payment_methods = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $payment_methods[$row['payment_method']] = $row;
}

// Logo path
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$logo_fallback = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='48' height='48'%3E%3Crect width='48' height='48' fill='%230AA84F' rx='12'/%3E%3Ctext x='24' y='32' text-anchor='middle' fill='white' font-size='20' font-weight='bold'%3EB%3C/text%3E%3C/svg%3E";
$avatar_fallback = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='38' height='38'%3E%3Crect width='38' height='38' fill='%230AA84F' rx='50%25'/%3E%3Ctext x='19' y='25' text-anchor='middle' fill='white' font-size='18' font-weight='bold'%3EC%3C/text%3E%3C/svg%3E";
?>
<!DOCTYPE html>
<html lang="en" id="htmlRoot">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cashier Dashboard - Braick Dispensary</title>
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_url ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #0AA84F;
            --primary-dark: #08944A;
            --primary-light: rgba(10, 168, 79, 0.15);
            --secondary: #0B5ED7;
            --secondary-light: rgba(11, 94, 215, 0.70);
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --shadow: 0 1px 3px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.06);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.08);
            --radius: 18px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: var(--transition);
        }
        
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--secondary); border-radius: 10px; }
        
        /* ===== SIDEBAR - GREEN BACKGROUND WITH BLUE HOVER 70% ===== */
        .sidebar {
            position: fixed; top: 0; left: 0; bottom: 0;
            width: 270px; background: var(--primary); 
            color: white;
            z-index: 50; overflow-y: auto;
            transition: transform 0.3s ease;
        }
        .sidebar-brand { padding: 22px 20px 16px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-brand .logo { width: 48px; height: 48px; border-radius: 12px; object-fit: cover; background: white; padding: 4px; }
        .sidebar-brand .branch-badge {
            display: inline-block;
            background: rgba(255,255,255,0.15);
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            margin-top: 4px;
        }
        .sidebar-nav { padding: 14px 10px; }
        .sidebar-nav .nav-label { 
            font-size: 0.55rem; 
            text-transform: uppercase; 
            letter-spacing: 0.1em; 
            color: rgba(255,255,255,0.4); 
            padding: 0 12px; 
            margin: 12px 0 6px; 
            font-weight: 700; 
        }
        .sidebar-link {
            display: flex; 
            align-items: center; 
            gap: 12px;
            padding: 9px 14px; 
            border-radius: 10px;
            color: rgba(255,255,255,0.8); 
            text-decoration: none;
            transition: var(--transition); 
            font-size: 0.85rem; 
            font-weight: 500;
            margin: 2px 0;
        }
        /* Hover effect - BLUE with 70% opacity - FORCES hover on ALL links */
        .sidebar-link:hover { 
            background: rgba(11, 94, 215, 0.70) !important; 
            color: #FFFFFF !important; 
        }
        /* Active link - BLUE with 70% opacity */
        .sidebar-link.active { 
            background: rgba(11, 94, 215, 0.70); 
            color: white; 
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3); 
        }
        /* When active AND hover - keep the same blue */
        .sidebar-link.active:hover { 
            background: rgba(11, 94, 215, 0.70) !important; 
            color: white !important; 
        }
        .sidebar-link.active i { color: white; }
        .sidebar-link i { 
            width: 20px; 
            text-align: center; 
            font-size: 1rem; 
        }
        .sidebar-link .badge { 
            margin-left: auto; 
            background: rgba(255,255,255,0.15); 
            padding: 1px 9px; 
            border-radius: 20px; 
            font-size: 0.65rem; 
            font-weight: 600; 
        }
        .sidebar-link.active .badge { 
            background: rgba(255,255,255,0.25); 
            color: white; 
        }
        
        /* ===== TOP NAV ===== */
        .top-nav {
            position: fixed; top: 0; left: 270px; right: 0;
            height: 68px; background: var(--bg-card); z-index: 40;
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 24px; border-bottom: 1px solid var(--border-color);
            transition: var(--transition);
        }
        .top-nav .branch-badge {
            background: rgba(10, 168, 79, 0.08);
            color: var(--primary);
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            border: 1px solid rgba(10, 168, 79, 0.12);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .top-nav .datetime {
            font-size: 0.78rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        .top-nav .avatar {
            width: 38px; height: 38px; border-radius: 50%;
            object-fit: cover; border: 2px solid var(--border-color);
            cursor: pointer; transition: var(--transition);
        }
        .top-nav .avatar:hover { border-color: var(--primary); }
        .top-nav .icon-btn {
            width: 38px; height: 38px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: var(--text-secondary); transition: var(--transition);
            background: transparent; border: none; cursor: pointer;
            position: relative;
        }
        .top-nav .icon-btn:hover { background: var(--bg-body); color: var(--primary); }
        .notif-dot {
            position: absolute; top: 6px; right: 6px;
            width: 8px; height: 8px; background: #EF4444;
            border-radius: 50%; border: 2px solid var(--bg-card);
        }
        .dark-toggle {
            background: var(--bg-body); border: 1px solid var(--border-color);
            border-radius: 10px; padding: 6px 12px;
            cursor: pointer; font-size: 0.85rem;
            color: var(--text-primary);
            transition: var(--transition);
            display: flex; align-items: center; gap: 6px;
        }
        .dark-toggle:hover { border-color: var(--primary); }
        
        /* ===== MAIN CONTENT ===== */
        .main-content {
            margin-left: 270px; margin-top: 68px;
            padding: 24px 28px;
            min-height: calc(100vh - 68px);
            transition: var(--transition);
        }
        
        /* ===== STAT CARDS - BLUE & GREEN ONLY ===== */
        .stat-card {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 20px 24px;
            border: 1px solid var(--border-color);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        .stat-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }
        /* Green Card */
        .stat-card.green::after { background: linear-gradient(90deg, #0AA84F, #34D399); }
        .stat-card.green .stat-icon { background: rgba(10, 168, 79, 0.12); color: #0AA84F; }
        .stat-card.green .stat-number { color: #0AA84F; }
        .stat-card.green { border-left: 4px solid #0AA84F; background: rgba(10, 168, 79, 0.06); }
        
        /* Blue Card */
        .stat-card.blue::after { background: linear-gradient(90deg, #0B5ED7, #1E88E5); }
        .stat-card.blue .stat-icon { background: rgba(11, 94, 215, 0.12); color: #0B5ED7; }
        .stat-card.blue .stat-number { color: #0B5ED7; }
        .stat-card.blue { border-left: 4px solid #0B5ED7; background: rgba(11, 94, 215, 0.06); }
        
        .stat-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-lg);
        }
        .stat-card .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        .stat-card .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
        }
        .stat-card .stat-label {
            font-size: 0.82rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        .stat-card .stat-trend {
            font-size: 0.65rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
        }
        .stat-card .stat-trend.up { background: rgba(10, 168, 79, 0.12); color: #0AA84F; }
        .stat-card .stat-trend.down { background: rgba(239, 68, 68, 0.12); color: #EF4444; }
        
        /* ===== QUICK ACTION ===== */
        .quick-action {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 18px 12px;
            border-radius: var(--radius);
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            transition: var(--transition);
            text-decoration: none;
            color: var(--text-primary);
            gap: 6px;
            text-align: center;
        }
        .quick-action:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary);
        }
        .quick-action .qa-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        .quick-action .qa-label { font-size: 0.7rem; font-weight: 500; }
        
        /* ===== PAYMENT METHOD CARDS ===== */
        .payment-method-card {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 14px 18px;
            border: 1px solid var(--border-color);
            transition: var(--transition);
        }
        .payment-method-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        .payment-method-card .pm-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }
        
        /* ===== TABLES ===== */
        .data-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
        .data-table th {
            text-align: left; padding: 10px 14px;
            font-weight: 600; color: var(--text-secondary);
            font-size: 0.65rem; text-transform: uppercase;
            border-bottom: 2px solid var(--border-color);
        }
        .data-table td { padding: 10px 14px; border-bottom: 1px solid var(--border-color); color: var(--text-primary); }
        .data-table tr:hover td { background: var(--bg-body); }
        .status-badge {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        .status-badge.paid { background: rgba(10, 168, 79, 0.12); color: #0AA84F; }
        .status-badge.pending { background: rgba(217, 119, 6, 0.12); color: #D97706; }
        
        /* ===== BUTTONS ===== */
        .btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 18px; border-radius: 10px;
            font-weight: 600; font-size: 0.8rem;
            transition: var(--transition); cursor: pointer;
            border: none; text-decoration: none;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .btn-secondary { background: var(--secondary); color: white; }
        .btn-secondary:hover { background: #0A4CA8; transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .btn-outline { background: transparent; color: var(--text-secondary); border: 1px solid var(--border-color); }
        .btn-outline:hover { background: var(--bg-body); border-color: var(--primary); color: var(--primary); }
        .btn-sm { padding: 4px 12px; font-size: 0.7rem; border-radius: 6px; }
        
        /* ===== FOOTER ===== */
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 20px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
        }
        @media (max-width: 768px) {
            .stat-card .stat-number { font-size: 1.4rem; }
        }
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .stat-card .stat-number { font-size: 1.2rem; }
            .top-nav .branch-badge { font-size: 0.6rem; padding: 2px 8px; }
            .top-nav .datetime { display: none; }
            .top-nav .dark-toggle span { display: none; }
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
        
        .spinner {
            display: inline-block;
            width: 14px; height: 14px;
            border: 2px solid var(--border-color);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .toast-custom {
            position: fixed; bottom: 24px; right: 24px;
            padding: 12px 18px;
            border-radius: 12px;
            z-index: 999;
            max-width: 360px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            font-family: 'Inter', sans-serif;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .toast-custom.show {
            transform: translateY(0);
            opacity: 1;
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- SIDEBAR - GREEN BACKGROUND WITH BLUE HOVER 70% -->
<!-- ================================================================ -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="flex items-center gap-3">
            <img src="<?= $logo_url ?>" alt="Braick Logo" class="logo"
                 onerror="this.src='<?= $logo_fallback ?>'">
            <div>
                <p class="font-bold text-base leading-tight">Braick Dispensary</p>
                <p class="text-xs opacity-70">Cashier</p>
                <span class="branch-badge"><i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($branch_name) ?></span>
            </div>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-label">Main Menu</div>
        <a href="dashboard.php?branch=<?= $user_branch_id ?>" class="sidebar-link active"><i class="fas fa-home"></i> Dashboard</a>
        <a href="payments.php?branch=<?= $user_branch_id ?>" class="sidebar-link"><i class="fas fa-money-bill-wave"></i> Payments</a>
        <a href="receipts.php?branch=<?= $user_branch_id ?>" class="sidebar-link"><i class="fas fa-receipt"></i> Receipts</a>
        <a href="daily_report.php?branch=<?= $user_branch_id ?>" class="sidebar-link"><i class="fas fa-file-invoice-dollar"></i> Daily Report</a>
        <a href="reports.php?branch=<?= $user_branch_id ?>" class="sidebar-link"><i class="fas fa-chart-bar"></i> Reports</a>
        <a href="profile.php" class="sidebar-link"><i class="fas fa-user-circle"></i> Profile</a>
        <a href="<?= $root_path ?>logout.php" class="sidebar-link" style="margin-top:8px;border-top:1px solid rgba(255,255,255,0.1);padding-top:12px;">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </nav>
</aside>

<!-- ================================================================ -->
<!-- TOP NAVIGATION -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        <div>
            <h1 class="text-lg font-bold text-primary">Cashier Dashboard</h1>
        </div>
    </div>
    <div class="flex items-center gap-3">
        <span class="branch-badge">
            <i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name) ?>
        </span>
        <span class="datetime" id="currentDateTime"></span>
        <button id="darkModeToggle" class="dark-toggle">
            <i class="fas fa-moon" id="darkIcon"></i>
            <span id="darkText">Dark</span>
        </button>
        <button class="icon-btn" id="notifToggle">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot"></span>
        </button>
        <a href="profile.php">
            <img src="<?= $logo_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='<?= $avatar_fallback ?>'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="flex flex-wrap justify-between items-center gap-3 mb-5">
        <div>
            <h1 class="text-2xl font-bold text-primary">Cashier Dashboard</h1>
            <p class="text-sm text-secondary">Welcome, <?= htmlspecialchars($_SESSION['full_name'] ?? 'Cashier') ?>! 
                <span class="inline-flex ml-2" style="background: rgba(10, 168, 79, 0.08); color: #0AA84F; padding: 3px 14px; border-radius: 20px; font-size: 0.7rem; border: 1px solid rgba(10, 168, 79, 0.15);">
                    <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($branch_name) ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="payments.php?branch=<?= $user_branch_id ?>" class="btn btn-primary btn-sm"><i class="fas fa-money-bill-wave"></i> Receive Payment</a>
            <button onclick="refreshData()" class="btn btn-outline btn-sm" id="refreshBtn">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STAT CARDS - BLUE & GREEN ONLY -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5">
        
        <!-- Card 1: Today's Payments - GREEN -->
        <div class="stat-card green animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Today's Payments</p>
                    <p class="stat-number"><?= $today_payments ?></p>
                    <span class="stat-trend up"><i class="fas fa-arrow-up"></i> Today</span>
                </div>
                <div class="stat-icon"><i class="fas fa-receipt"></i></div>
            </div>
        </div>
        
        <!-- Card 2: Today's Revenue - BLUE -->
        <div class="stat-card blue animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Today's Revenue</p>
                    <p class="stat-number">TSh <?= number_format($today_revenue) ?></p>
                    <span class="stat-trend up"><i class="fas fa-arrow-up"></i> +12%</span>
                </div>
                <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            </div>
        </div>
        
        <!-- Card 3: Pending Payments - GREEN -->
        <div class="stat-card green animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Pending Payments</p>
                    <p class="stat-number"><?= $pending_payments ?></p>
                    <span class="stat-trend down"><i class="fas fa-arrow-down"></i> Unpaid</span>
                </div>
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
            </div>
        </div>
        
        <!-- Card 4: Total Revenue - BLUE -->
        <div class="stat-card blue animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Total Revenue</p>
                    <p class="stat-number">TSh <?= number_format($total_revenue) ?></p>
                    <span class="stat-trend up"><i class="fas fa-arrow-up"></i> All time</span>
                </div>
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- PAYMENT METHODS BREAKDOWN -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
        <div class="payment-method-card">
            <div class="flex items-center gap-3">
                <div class="pm-icon" style="background: rgba(10, 168, 79, 0.08); color: #0AA84F;">
                    <i class="fas fa-money-bill"></i>
                </div>
                <div>
                    <p class="text-xs text-secondary">Cash</p>
                    <p class="font-semibold text-sm">TSh <?= number_format($payment_methods['cash']['total'] ?? 0) ?></p>
                    <p class="text-xs text-secondary"><?= $payment_methods['cash']['count'] ?? 0 ?> transactions</p>
                </div>
            </div>
        </div>
        <div class="payment-method-card">
            <div class="flex items-center gap-3">
                <div class="pm-icon" style="background: rgba(11, 94, 215, 0.08); color: #0B5ED7;">
                    <i class="fas fa-mobile-alt"></i>
                </div>
                <div>
                    <p class="text-xs text-secondary">Mobile Money</p>
                    <p class="font-semibold text-sm">TSh <?= number_format($payment_methods['mobile_money']['total'] ?? 0) ?></p>
                    <p class="text-xs text-secondary"><?= $payment_methods['mobile_money']['count'] ?? 0 ?> transactions</p>
                </div>
            </div>
        </div>
        <div class="payment-method-card">
            <div class="flex items-center gap-3">
                <div class="pm-icon" style="background: rgba(10, 168, 79, 0.08); color: #0AA84F;">
                    <i class="fas fa-university"></i>
                </div>
                <div>
                    <p class="text-xs text-secondary">Bank</p>
                    <p class="font-semibold text-sm">TSh <?= number_format($payment_methods['bank']['total'] ?? 0) ?></p>
                    <p class="text-xs text-secondary"><?= $payment_methods['bank']['count'] ?? 0 ?> transactions</p>
                </div>
            </div>
        </div>
        <div class="payment-method-card">
            <div class="flex items-center gap-3">
                <div class="pm-icon" style="background: rgba(11, 94, 215, 0.08); color: #0B5ED7;">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <div>
                    <p class="text-xs text-secondary">Insurance</p>
                    <p class="font-semibold text-sm">TSh <?= number_format($payment_methods['insurance']['total'] ?? 0) ?></p>
                    <p class="text-xs text-secondary"><?= $payment_methods['insurance']['count'] ?? 0 ?> transactions</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- QUICK ACTIONS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-5 gap-3 mb-5">
        <a href="payments.php?branch=<?= $user_branch_id ?>" class="quick-action">
            <div class="qa-icon" style="background: rgba(10, 168, 79, 0.08); color: #0AA84F;"><i class="fas fa-hand-holding-usd"></i></div>
            <span class="qa-label">Receive Payment</span>
        </a>
        <a href="receipts.php?branch=<?= $user_branch_id ?>" class="quick-action">
            <div class="qa-icon" style="background: rgba(11, 94, 215, 0.08); color: #0B5ED7;"><i class="fas fa-print"></i></div>
            <span class="qa-label">Print Receipt</span>
        </a>
        <a href="daily_report.php?branch=<?= $user_branch_id ?>" class="quick-action">
            <div class="qa-icon" style="background: rgba(10, 168, 79, 0.08); color: #0AA84F;"><i class="fas fa-file-invoice-dollar"></i></div>
            <span class="qa-label">Daily Report</span>
        </a>
        <a href="reports.php?branch=<?= $user_branch_id ?>" class="quick-action">
            <div class="qa-icon" style="background: rgba(11, 94, 215, 0.08); color: #0B5ED7;"><i class="fas fa-chart-bar"></i></div>
            <span class="qa-label">Reports</span>
        </a>
        <a href="profile.php" class="quick-action">
            <div class="qa-icon" style="background: rgba(10, 168, 79, 0.08); color: #0AA84F;"><i class="fas fa-user-circle"></i></div>
            <span class="qa-label">Profile</span>
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT PAYMENTS -->
    <!-- ================================================================ -->
    <div class="card" style="background: var(--bg-card); border-radius: var(--radius); padding: 18px 20px; border: 1px solid var(--border-color);">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
            <h3 class="card-title" style="font-size: 0.9rem; font-weight: 600; color: var(--text-primary);">
                <i class="fas fa-receipt text-primary mr-2"></i> Recent Payments
            </h3>
            <a href="payments.php?branch=<?= $user_branch_id ?>" class="text-xs text-primary font-medium">View All →</a>
        </div>
        <div class="overflow-x-auto max-h-60 overflow-y-auto">
            <table class="data-table">
                <thead>
                    <tr><th>Receipt #</th><th>Patient</th><th>Service</th><th>Amount</th><th>Status</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php if (count($recent_payments) > 0): ?>
                        <?php foreach ($recent_payments as $payment): ?>
                            <tr>
                                <td class="font-mono text-xs">#<?= htmlspecialchars($payment['receipt_number'] ?? 'N/A') ?></td>
                                <td class="text-sm"><?= htmlspecialchars($payment['patient_name'] ?? 'Unknown') ?></td>
                                <td class="text-xs"><?= htmlspecialchars($payment['payment_type'] ?? 'N/A') ?></td>
                                <td class="font-semibold text-sm text-green-600">TSh <?= number_format($payment['amount'] ?? 0) ?></td>
                                <td><span class="status-badge paid">Paid</span></td>
                                <td>
                                    <button onclick="printReceipt(<?= $payment['id'] ?>)" class="text-primary text-xs hover:underline">
                                        <i class="fas fa-print"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center text-secondary text-sm py-3">No payments recorded yet</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p class="font-medium text-sm">Braick Dispensary Management System</p>
        <p class="text-xs">Cashier Dashboard v1.0 &copy; <?= date('Y') ?> All rights reserved</p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const darkToggle = document.getElementById('darkModeToggle');
    const darkIcon = document.getElementById('darkIcon');
    const darkText = document.getElementById('darkText');
    const refreshBtn = document.getElementById('refreshBtn');

    // Sidebar Toggle
    sidebarToggle?.addEventListener('click', () => {
        sidebar.classList.toggle('open');
    });
    
    document.addEventListener('click', (e) => {
        if (window.innerWidth <= 1024) {
            if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    // Dark Mode
    let isDark = false;
    darkToggle?.addEventListener('click', () => {
        isDark = !isDark;
        const html = document.getElementById('htmlRoot');
        if (isDark) {
            html.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
        } else {
            html.removeAttribute('data-theme');
            darkIcon.className = 'fas fa-moon';
            darkText.textContent = 'Dark';
        }
        localStorage.setItem('darkMode', isDark ? 'true' : 'false');
    });
    
    if (localStorage.getItem('darkMode') === 'true') {
        isDark = true;
        document.getElementById('htmlRoot').setAttribute('data-theme', 'dark');
        darkIcon.className = 'fas fa-sun';
        darkText.textContent = 'Light';
    }

    // Refresh
    function refreshData() {
        if (refreshBtn) {
            refreshBtn.innerHTML = '<span class="spinner"></span> Refreshing...';
            refreshBtn.disabled = true;
        }
        setTimeout(() => { location.reload(); }, 800);
    }

    // Toast
    function showToast(title, message, type = 'info') {
        const existing = document.querySelector('.toast-custom');
        if (existing) existing.remove();
        const colors = {
            info: { bg: '#0AA84F', icon: 'fa-info-circle' },
            success: { bg: '#0AA84F', icon: 'fa-check-circle' },
            error: { bg: '#EF4444', icon: 'fa-exclamation-circle' },
            warning: { bg: '#F59E0B', icon: 'fa-exclamation-triangle' }
        };
        const style = colors[type] || colors.info;
        const toast = document.createElement('div');
        toast.className = 'toast-custom';
        toast.style.cssText = `
            background: ${style.bg}; color: white;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        `;
        toast.innerHTML = `
            <i class="fas ${style.icon}" style="font-size:1.1rem;"></i>
            <div>
                <p style="font-weight:600;font-size:0.85rem;margin:0;">${title}</p>
                <p style="font-size:0.75rem;opacity:0.9;margin:0;">${message}</p>
            </div>
        `;
        document.body.appendChild(toast);
        setTimeout(() => toast.classList.add('show'), 50);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 400);
        }, 3500);
    }

    // Print Receipt
    function printReceipt(id) {
        showToast('Print Receipt', 'Printing receipt #' + id + '...', 'info');
        window.open('print_receipt.php?id=' + id, '_blank');
    }

    // DateTime
    function updateDateTime() {
        const now = new Date();
        const dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        const timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        document.getElementById('currentDateTime').textContent = dateStr + ' • ' + timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // Welcome toast
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => {
            showToast('Welcome', 'Cashier Dashboard loaded successfully', 'success');
        }, 300);
    });

    console.log('%c🏥 Braick Dispensary - Cashier Dashboard', 'font-size:18px; font-weight:bold; color:#0AA84F;');
    console.log('%c👋 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>