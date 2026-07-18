<?php
// ================================================================
// FILE: frontend/pages/cashier/view_bill.php
// CASHIER - VIEW BILL DETAILS (GREEN THEME)
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION - Cashier
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    $_SESSION['user_id'] = 10;
    $_SESSION['full_name'] = 'Cashier Dodoma';
    $_SESSION['role'] = 'cashier';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'cashier.dodoma';
    $_SESSION['is_admin'] = false;
}

// ================================================================
// PATH SAHIHI
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

$bill_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';

$bill = null;
$items = [];
$payments = [];
$currency = 'TSh';
$message = '';
$message_type = '';

if ($bill_id <= 0) {
    header('Location: pending_bills.php');
    exit;
}

try {
    $db = getDB();
    
    // ================================================================
    // GET BILL DETAILS
    // ================================================================
    $stmt = $db->prepare("
        SELECT pb.*, 
               p.full_name as patient_name, 
               p.patient_id,
               p.phone,
               p.address,
               p.gender,
               u.full_name as created_by_name,
               b.name as branch_name,
               v.visit_number,
               v.visit_type,
               v.created_at as visit_date
        FROM patient_bills pb
        JOIN patients p ON pb.patient_id = p.id
        JOIN users u ON pb.created_by = u.id
        JOIN branches b ON pb.branch_id = b.id
        LEFT JOIN visits v ON pb.visit_id = v.id
        WHERE pb.id = ? AND pb.branch_id = ?
    ");
    $stmt->execute([$bill_id, $user_branch_id]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$bill) {
        header('Location: pending_bills.php');
        exit;
    }
    
    // ================================================================
    // GET BILL ITEMS
    // ================================================================
    $stmt = $db->prepare("
        SELECT * FROM bill_items 
        WHERE bill_id = ? 
        ORDER BY id
    ");
    $stmt->execute([$bill_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ================================================================
    // GET PAYMENT HISTORY
    // ================================================================
    $stmt = $db->prepare("
        SELECT p.*, u.full_name as received_by_name
        FROM payments p
        LEFT JOIN users u ON p.received_by = u.id
        WHERE p.bill_id = ?
        ORDER BY p.received_at DESC
    ");
    $stmt->execute([$bill_id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ================================================================
    // GET SYSTEM SETTINGS
    // ================================================================
    $settings = [];
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $currency = $settings['currency'] ?? 'TSh';
    
} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
}

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/cashier_header.php';
include_once '../../components/cashier_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Bill - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
            --success: #059669;
            --success-dark: #047857;
            --success-light: #34D399;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-dark: #B91C1C;
            --danger-light: #F87171;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --white: #FFFFFF;
            --gray-50: #F8FAFC;
            --gray-100: #F1F5F9;
            --gray-200: #E2E8F0;
            --gray-300: #CBD5E1;
            --gray-400: #94A3B8;
            --gray-500: #64748B;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1E293B;
            --gray-900: #0F172A;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow: 0 1px 3px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.07);
            --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
            --shadow-xl: 0 20px 25px rgba(0,0,0,0.1);
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --table-stripe: #E8F0FE;
            --table-hover: #D1FAE5;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
            --table-stripe: #1E293B;
            --table-hover: #1A3A2A;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--success); border-radius: 10px; }
        
        /* ================================================================
           TOP NAV
           ================================================================ */
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
            transition: all 0.3s ease;
        }
        
        .top-nav .search-wrapper {
            display: flex;
            align-items: center;
            background: var(--bg-body);
            border-radius: 10px;
            border: 2px solid var(--border-color);
            transition: all 0.3s;
            flex: 1;
            max-width: 500px;
        }
        
        .top-nav .search-wrapper:focus-within {
            border-color: var(--success);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.15);
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
        
        .top-nav .search-wrapper input::placeholder {
            color: var(--text-secondary);
        }
        
        .top-nav .search-wrapper .search-btn {
            background: var(--success);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 0 10px 10px 0;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .top-nav .search-wrapper .search-btn:hover {
            background: var(--success-dark);
        }
        
        .top-nav .datetime {
            font-size: 0.78rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .top-nav .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-color);
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .top-nav .avatar:hover {
            border-color: var(--success);
            transform: scale(1.05);
        }
        
        .top-nav .icon-btn {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            transition: all 0.3s;
            background: transparent;
            border: none;
            cursor: pointer;
            position: relative;
        }
        
        .top-nav .icon-btn:hover {
            background: var(--bg-body);
            color: var(--success);
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
            border-radius: 10px;
            padding: 6px 12px;
            cursor: pointer;
            font-size: 0.82rem;
            color: var(--text-primary);
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .dark-toggle-btn:hover {
            border-color: var(--success);
            background: var(--bg-card);
        }
        
        .dark-toggle-btn i { font-size: 0.9rem; }
        
        /* ================================================================
           MAIN CONTENT
           ================================================================ */
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        /* ================================================================
           PAGE HEADER - GREEN THEME
           ================================================================ */
        .page-header {
            background: linear-gradient(135deg, var(--success), var(--success-dark));
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 20px rgba(5, 150, 105, 0.25);
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .page-header .page-title {
            color: white;
            font-size: 1.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-title i {
            font-size: 2rem;
            opacity: 0.9;
        }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-subtitle strong {
            color: white;
            font-weight: 600;
        }
        
        .page-header .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            backdrop-filter: blur(4px);
        }
        
        .page-header .header-badge {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            backdrop-filter: blur(4px);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 18px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.82rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(4px);
            position: relative;
            z-index: 1;
        }
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        /* ================================================================
           BILL DETAIL CARD
           ================================================================ */
        .detail-card {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 20px 24px;
            border: 1px solid var(--border-color);
            transition: all 0.3s;
            box-shadow: var(--shadow-sm);
        }
        
        .detail-card:hover {
            border-color: var(--success);
            box-shadow: var(--shadow-md);
        }
        
        .detail-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        
        .detail-value {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .detail-value .text-muted {
            color: var(--text-secondary);
            font-weight: 400;
        }
        
        /* ================================================================
           STATUS BADGE
           ================================================================ */
        .status-badge {
            display: inline-block;
            padding: 3px 14px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-badge.pending {
            background: #FEF3C7;
            color: #D97706;
        }
        
        .status-badge.partial {
            background: #FEF3C7;
            color: #D97706;
        }
        
        .status-badge.paid {
            background: #D1FAE5;
            color: #059669;
        }
        
        .status-badge.cancelled {
            background: #FEE2E2;
            color: #DC2626;
        }
        
        [data-theme="dark"] .status-badge.pending {
            background: #3D2E0A;
            color: #FBBF24;
        }
        
        [data-theme="dark"] .status-badge.partial {
            background: #3D2E0A;
            color: #FBBF24;
        }
        
        [data-theme="dark"] .status-badge.paid {
            background: #1A3A2A;
            color: #34D399;
        }
        
        [data-theme="dark"] .status-badge.cancelled {
            background: #3A1A1A;
            color: #F87171;
        }
        
        /* ================================================================
           TABLE
           ================================================================ */
        .table-wrap {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
        }
        
        .data-table thead th {
            text-align: left;
            padding: 8px 12px;
            font-weight: 700;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: white;
            background: var(--success);
            border-bottom: 3px solid var(--success-dark);
            white-space: nowrap;
        }
        
        .data-table thead th:first-child {
            border-radius: 8px 0 0 0;
        }
        
        .data-table thead th:last-child {
            border-radius: 0 8px 0 0;
        }
        
        .data-table td {
            padding: 8px 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .data-table tbody tr:hover td {
            background: var(--table-hover);
        }
        
        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background: var(--success-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        
        .btn-outline:hover {
            background: var(--bg-body);
            border-color: var(--success);
            color: var(--success);
        }
        
        .btn-sm { 
            padding: 4px 10px; 
            font-size: 0.7rem; 
            border-radius: 6px; 
        }
        
        /* ================================================================
           PAYMENT HISTORY
           ================================================================ */
        .payment-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 12px;
            border-bottom: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .payment-item:hover {
            background: var(--bg-body);
            border-radius: 8px;
        }
        
        .payment-item:last-child {
            border-bottom: none;
        }
        
        /* ================================================================
           FOOTER
           ================================================================ */
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand { 
            color: var(--success); 
            font-weight: 600; 
        }
        
        /* ================================================================
           BADGES
           ================================================================ */
        .role-badge-display {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            background: var(--primary-bg);
            color: var(--primary);
            text-transform: uppercase;
        }
        
        [data-theme="dark"] .role-badge-display {
            background: #1E3A5F;
            color: #6EA8FE;
        }
        
        .branch-badge-display {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            background: var(--success-bg);
            color: var(--success);
        }
        
        [data-theme="dark"] .branch-badge-display {
            background: #1A3A2A;
            color: #34D399;
        }
        
        /* ================================================================
           TOAST
           ================================================================ */
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 14px 20px;
            border-radius: 12px;
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
        
        .toast-custom.show {
            transform: translateY(0);
            opacity: 1;
        }
        
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
            .detail-card { padding: 16px 18px; }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .detail-card { padding: 12px 14px; }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .top-nav .search-wrapper { max-width: 120px; }
            .top-nav .search-wrapper .search-btn { padding: 8px 10px; font-size: 0.7rem; }
            .detail-card { padding: 10px 12px; }
            .btn { padding: 6px 12px; font-size: 0.7rem; }
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
            <input type="text" id="searchInput" placeholder="Search patients...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <span class="branch-badge-display">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($branch_name) ?>
        </span>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= ($unread_notifications ?? 0) > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3EA%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-file-invoice"></i>
                Bill Details
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;">CASHIER</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-receipt"></i>
                View complete bill information
                
                <span class="header-badge">
                    <i class="fas fa-hashtag"></i>
                    <?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?>
                </span>
                
                <span class="header-badge">
                    <i class="fas fa-user"></i>
                    <?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?>
                </span>
            </p>
        </div>
        <div class="header-right" style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="pending_bills.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <?php if ($bill['status'] === 'pending' || $bill['status'] === 'partial'): ?>
                <a href="make_payment.php?bill_id=<?= $bill['id'] ?>" class="btn-outline-light">
                    <i class="fas fa-money-bill-wave"></i> Make Payment
                </a>
            <?php endif; ?>
            <?php if ($bill['status'] === 'paid'): ?>
                <a href="print_receipt.php?bill_id=<?= $bill['id'] ?>&print=1" class="btn-outline-light" target="_blank">
                    <i class="fas fa-print"></i> Print Receipt
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="p-4 rounded-xl mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200 dark:bg-green-900/20 dark:text-green-300 dark:border-green-800' : 'bg-red-100 text-red-700 border border-red-200 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800' ?>" style="max-width:1200px;margin:0 auto 16px;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- BILL DETAILS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-5" style="max-width:1200px;margin:0 auto;">
        
        <!-- Bill Info -->
        <div class="detail-card lg:col-span-2">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-3">
                <i class="fas fa-info-circle" style="color:var(--success);"></i> Bill Information
            </h3>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <p class="detail-label">Bill Number</p>
                    <p class="detail-value"><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Status</p>
                    <p class="detail-value">
                        <span class="status-badge <?= $bill['status'] ?? 'pending' ?>">
                            <?= ucfirst($bill['status'] ?? 'Pending') ?>
                        </span>
                    </p>
                </div>
                <div>
                    <p class="detail-label">Created Date</p>
                    <p class="detail-value"><?= isset($bill['created_at']) ? date('F d, Y h:i A', strtotime($bill['created_at'])) : 'N/A' ?></p>
                </div>
                <div>
                    <p class="detail-label">Created By</p>
                    <p class="detail-value"><?= htmlspecialchars($bill['created_by_name'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Branch</p>
                    <p class="detail-value"><?= htmlspecialchars($bill['branch_name'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Visit #</p>
                    <p class="detail-value"><?= htmlspecialchars($bill['visit_number'] ?? 'N/A') ?></p>
                </div>
                <?php if (!empty($bill['visit_type'])): ?>
                <div>
                    <p class="detail-label">Visit Type</p>
                    <p class="detail-value capitalize"><?= htmlspecialchars($bill['visit_type']) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Patient Info -->
        <div class="detail-card">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-3">
                <i class="fas fa-user" style="color:var(--success);"></i> Patient Information
            </h3>
            <div class="space-y-2">
                <div>
                    <p class="detail-label">Name</p>
                    <p class="detail-value">
                        <a href="patient_bills.php?patient_id=<?= $bill['patient_id'] ?>" class="hover:underline" style="color:var(--success);">
                            <?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?>
                        </a>
                    </p>
                </div>
                <div>
                    <p class="detail-label">Patient ID</p>
                    <p class="detail-value"><?= htmlspecialchars($bill['patient_id'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Phone</p>
                    <p class="detail-value"><?= htmlspecialchars($bill['phone'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Gender</p>
                    <p class="detail-value"><?= htmlspecialchars($bill['gender'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Address</p>
                    <p class="detail-value"><?= htmlspecialchars($bill['address'] ?? 'N/A') ?></p>
                </div>
                <div class="mt-2">
                    <a href="patient_bills.php?patient_id=<?= $bill['patient_id'] ?>" class="btn btn-success btn-sm w-full justify-center">
                        <i class="fas fa-file-invoice"></i> View All Bills
                    </a>
                </div>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- BILL ITEMS & SUMMARY -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-5" style="max-width:1200px;margin:0 auto;">
        
        <!-- Bill Items -->
        <div class="detail-card lg:col-span-2">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-3">
                <i class="fas fa-list" style="color:var(--success);"></i> Bill Items
                <span class="text-xs font-normal text-gray-400">(<?= count($items) ?> items)</span>
            </h3>
            
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="border-radius: 8px 0 0 0;">#</th>
                            <th>Item</th>
                            <th>Qty</th>
                            <th style="text-align:right;">Unit Price</th>
                            <th style="text-align:right;border-radius: 0 8px 0 0;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($items) > 0): ?>
                            <?php $i = 1; foreach ($items as $item): ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td><?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></td>
                                    <td><?= $item['quantity'] ?? 1 ?></td>
                                    <td style="text-align:right;"><?= $currency ?> <?= number_format($item['unit_price'] ?? 0, 0) ?></td>
                                    <td style="text-align:right;font-weight:600;"><?= $currency ?> <?= number_format($item['total_price'] ?? 0, 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-gray-400">No items found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Summary -->
        <div class="detail-card">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-3">
                <i class="fas fa-calculator" style="color:var(--success);"></i> Summary
            </h3>
            
            <div class="space-y-2">
                <div class="flex justify-between py-1 border-b border-gray-100 dark:border-gray-700">
                    <span class="text-gray-500 text-sm">Subtotal</span>
                    <span class="font-semibold text-sm"><?= $currency ?> <?= number_format($bill['subtotal'] ?? 0, 0) ?></span>
                </div>
                
                <?php if (($bill['discount_amount'] ?? 0) > 0): ?>
                <div class="flex justify-between py-1 border-b border-gray-100 dark:border-gray-700">
                    <span class="text-gray-500 text-sm">Discount (<?= $bill['discount_percent'] ?? 0 ?>%)</span>
                    <span class="font-semibold text-sm" style="color:#DC2626;">-<?= $currency ?> <?= number_format($bill['discount_amount'] ?? 0, 0) ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (($bill['tax_amount'] ?? 0) > 0): ?>
                <div class="flex justify-between py-1 border-b border-gray-100 dark:border-gray-700">
                    <span class="text-gray-500 text-sm">Tax</span>
                    <span class="font-semibold text-sm"><?= $currency ?> <?= number_format($bill['tax_amount'] ?? 0, 0) ?></span>
                </div>
                <?php endif; ?>
                
                <div class="flex justify-between py-2 border-b-2 border-gray-300 dark:border-gray-600">
                    <span class="font-bold text-sm">Total</span>
                    <span class="font-bold text-lg" style="color:var(--success);"><?= $currency ?> <?= number_format($bill['total_amount'] ?? 0, 0) ?></span>
                </div>
                
                <?php if (($bill['amount_paid'] ?? 0) > 0): ?>
                <div class="flex justify-between py-1">
                    <span class="text-gray-500 text-sm">Amount Paid</span>
                    <span class="font-semibold text-sm" style="color:var(--success);"><?= $currency ?> <?= number_format($bill['amount_paid'] ?? 0, 0) ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (($bill['balance'] ?? 0) > 0): ?>
                <div class="flex justify-between py-1">
                    <span class="text-gray-500 text-sm">Balance</span>
                    <span class="font-semibold text-sm" style="color:#DC2626;"><?= $currency ?> <?= number_format($bill['balance'] ?? 0, 0) ?></span>
                </div>
                <?php endif; ?>
                
                <!-- Action Buttons -->
                <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                    <?php if ($bill['status'] === 'pending' || $bill['status'] === 'partial'): ?>
                        <a href="make_payment.php?bill_id=<?= $bill['id'] ?>" class="btn btn-success w-full justify-center">
                            <i class="fas fa-money-bill-wave"></i> Make Payment
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($bill['status'] === 'paid'): ?>
                        <a href="print_receipt.php?bill_id=<?= $bill['id'] ?>&print=1" class="btn btn-primary w-full justify-center" target="_blank">
                            <i class="fas fa-print"></i> Print Receipt
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- PAYMENT HISTORY -->
    <!-- ================================================================ -->
    <?php if (count($payments) > 0): ?>
    <div class="detail-card" style="max-width:1200px;margin:0 auto;">
        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-3">
            <i class="fas fa-history" style="color:var(--success);"></i> Payment History
            <span class="text-xs font-normal text-gray-400">(<?= count($payments) ?> payments)</span>
        </h3>
        
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="border-radius: 8px 0 0 0;">#</th>
                        <th>Receipt #</th>
                        <th>Amount</th>
                        <th>Payment Method</th>
                        <th>Reference #</th>
                        <th>Received By</th>
                        <th style="border-radius: 0 8px 0 0;">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($payments as $payment): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td>
                                <span class="font-mono text-xs font-bold">
                                    <?= htmlspecialchars($payment['receipt_number'] ?? 'N/A') ?>
                                </span>
                            </td>
                            <td>
                                <span class="font-semibold" style="color:var(--success);">
                                    <?= $currency ?> <?= number_format($payment['amount'] ?? 0, 0) ?>
                                </span>
                            </td>
                            <td>
                                <span class="capitalize"><?= htmlspecialchars($payment['payment_method'] ?? 'N/A') ?></span>
                            </td>
                            <td>
                                <span class="text-xs font-mono"><?= htmlspecialchars($payment['reference_number'] ?? 'N/A') ?></span>
                            </td>
                            <td><?= htmlspecialchars($payment['received_by_name'] ?? 'N/A') ?></td>
                            <td class="text-xs">
                                <?= isset($payment['received_at']) ? date('d/m/Y h:i A', strtotime($payment['received_at'])) : 'N/A' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            View Bill
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
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
<!-- GLOBAL STATS AUTO-UPDATE -->
<!-- ================================================================ -->
<script src="/dispensary_system/frontend/assets/js/global_stats.js"></script>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC JAVASCRIPT -->
<!-- ================================================================ -->
<script>
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

    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
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

    function updateDateTime() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        document.getElementById('currentDateTime').textContent = dateStr + ' • ' + timeStr;
        document.getElementById('footerTimestamp').textContent = 'Last updated: ' + timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            window.location.href = 'search.php?q=' + encodeURIComponent(query);
        }
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

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

    console.log('%c🟢 Braick - View Bill (Green Theme)', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c📋 Bill #: <?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c👤 Patient: <?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c💰 Total: <?= $currency ?> <?= number_format($bill['total_amount'] ?? 0, 0) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Status: <?= ucfirst($bill['status'] ?? 'Pending') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c🟢 Green theme applied', 'font-size:13px; color:#059669;');
</script>

</body>
</html>