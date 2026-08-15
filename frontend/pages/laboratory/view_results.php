<?php
// ================================================================
// FILE: frontend/pages/laboratory/view_results.php
// LABORATORY - VIEW RESULTS FOR A SPECIFIC REQUEST
// FIXED: Login session - no default user bypass
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// CHECK SESSION - REDIRECT TO LOGIN IF NOT LABORATORY
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'laboratory') {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// GET USER DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Lab Technician';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Branch';

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once 'C:/xampp/htdocs/dispensary_system/backend/config/database.php';
$db = Database::getInstance()->getConnection();

// ================================================================
// GET REQUEST ID
// ================================================================
$request_id = isset($_GET['request_id']) ? (int)$_GET['request_id'] : 0;

if ($request_id <= 0) {
    header('Location: in_progress.php');
    exit;
}

// ================================================================
// GET REQUEST DETAILS
// ================================================================
$query = "
    SELECT lr.*, 
           p.full_name as patient_name, p.patient_id, p.phone, p.email,
           COALESCE(u.full_name, 'Not Assigned') as doctor_name,
           u.specialty,
           v.visit_number,
           b.name as branch_name,
           lab.full_name as lab_technician_name
    FROM lab_requests lr
    JOIN patients p ON lr.patient_id = p.id
    LEFT JOIN users u ON lr.doctor_id = u.id
    LEFT JOIN visits v ON lr.visit_id = v.id
    LEFT JOIN branches b ON lr.branch_id = b.id
    LEFT JOIN users lab ON lr.lab_technician_id = lab.id
    WHERE lr.id = ? AND lr.branch_id = ?
";

$stmt = $db->prepare($query);
$stmt->execute([$request_id, $user_branch_id]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    header('Location: in_progress.php');
    exit;
}

// ================================================================
// GET REQUEST ITEMS WITH RESULTS
// ================================================================
$stmt = $db->prepare("
    SELECT * FROM lab_request_items 
    WHERE request_id = ? 
    ORDER BY id
");
$stmt->execute([$request_id]);
$test_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/laboratory_header.php';
include_once __DIR__ . '/../../components/laboratory_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Results - Laboratory</title>
    <link rel="icon" href="/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           ROOT VARIABLES
           ================================================================ */
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
            --success: #059669;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --purple: #7C3AED;
            --purple-bg: #EDE9FE;
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
            --radius: 10px;
            --radius-lg: 14px;
            --transition: all 0.3s ease;
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --text-dark: #0F172A;
            --border-color: #E2E8F0;
            --table-hover: #F1F5F9;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --text-dark: #F1F5F9;
            --border-color: #334155;
            --primary-bg: #1E3A5F;
            --primary-light: #6EA8FE;
            --gray-100: #1E293B;
            --gray-200: #334155;
            --gray-300: #475569;
            --table-hover: #1E293B;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            background: var(--bg-body);
            color: var(--text-primary);
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            margin: 0;
            padding: 0;
            line-height: 1.6;
            transition: background 0.3s ease, color 0.3s ease;
        }
        
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
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15);
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
            background: var(--primary);
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
            background: var(--primary-dark);
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
            border-color: var(--primary);
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
            color: var(--primary);
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
            border-color: var(--primary);
            background: var(--bg-card);
        }
        
        .dark-toggle-btn i { font-size: 0.9rem; }
        
        .branch-badge {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            background: var(--success-bg);
            color: var(--success);
        }
        
        [data-theme="dark"] .branch-badge {
            background: #1A3A2A;
            color: #34D399;
        }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        [data-theme="dark"] .main-content {
            background: var(--gray-900);
            color: var(--gray-100);
        }
        
        /* ================================================================
           PAGE HEADER
           ================================================================ */
        .page-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.25);
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
            font-size: 1.6rem;
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
        
        .print-button {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 18px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.82rem;
            transition: all 0.3s;
            cursor: pointer;
            backdrop-filter: blur(4px);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            position: relative;
            z-index: 1;
        }
        
        .print-button:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        /* ================================================================
           DETAIL CARDS
           ================================================================ */
        .detail-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 20px 24px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .detail-card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.06);
        }
        
        [data-theme="dark"] .detail-card {
            background: var(--gray-800);
            border-color: var(--gray-700);
        }
        
        [data-theme="dark"] .detail-card:hover {
            border-color: var(--primary);
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
        
        /* ================================================================
           STATUS BADGES
           ================================================================ */
        .status-badge {
            display: inline-block;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 4px 16px;
            border-radius: 20px;
        }
        
        .status-badge.pending { background: #FEF3C7; color: #D97706; }
        .status-badge.in_progress { background: #E8F0FE; color: #0B5ED7; }
        .status-badge.completed { background: #D1FAE5; color: #059669; }
        .status-badge.cancelled { background: #FEE2E2; color: #DC2626; }
        
        [data-theme="dark"] .status-badge.pending { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .status-badge.in_progress { background: #1E3A5F; color: #6EA8FE; }
        [data-theme="dark"] .status-badge.completed { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .status-badge.cancelled { background: #3A1A1A; color: #F87171; }
        
        /* ================================================================
           RESULT ITEMS
           ================================================================ */
        .result-item {
            background: var(--bg-body);
            border-radius: 12px;
            padding: 16px 20px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            margin-bottom: 12px;
        }
        
        .result-item:hover {
            border-color: var(--primary);
        }
        
        .result-item.completed {
            border-color: #059669;
            background: rgba(5, 150, 105, 0.05);
        }
        
        .result-item.in_progress {
            border-color: #0B5ED7;
            background: rgba(11, 94, 215, 0.05);
        }
        
        .result-item.pending {
            border-color: #D97706;
            background: rgba(217, 119, 6, 0.05);
        }
        
        [data-theme="dark"] .result-item {
            background: var(--gray-700);
            border-color: var(--gray-600);
        }
        
        [data-theme="dark"] .result-item.completed {
            border-color: #34D399;
            background: rgba(5, 150, 105, 0.1);
        }
        
        [data-theme="dark"] .result-item.in_progress {
            border-color: #6EA8FE;
            background: rgba(11, 94, 215, 0.1);
        }
        
        [data-theme="dark"] .result-item.pending {
            border-color: #FBBF24;
            background: rgba(217, 119, 6, 0.1);
        }
        
        .result-status-badge {
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 12px;
            border-radius: 12px;
        }
        
        .result-status-badge.pending { background: #FEF3C7; color: #D97706; }
        .result-status-badge.in_progress { background: #E8F0FE; color: #0B5ED7; }
        .result-status-badge.completed { background: #D1FAE5; color: #059669; }
        .result-status-badge.cancelled { background: #FEE2E2; color: #DC2626; }
        
        [data-theme="dark"] .result-status-badge.pending { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .result-status-badge.in_progress { background: #1E3A5F; color: #6EA8FE; }
        [data-theme="dark"] .result-status-badge.completed { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .result-status-badge.cancelled { background: #3A1A1A; color: #F87171; }
        
        .result-display {
            background: var(--bg-card);
            padding: 12px 16px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            font-family: monospace;
            font-size: 0.85rem;
            white-space: pre-wrap;
            word-wrap: break-word;
            margin-top: 8px;
        }
        
        .result-display.empty {
            color: var(--text-secondary);
            font-style: italic;
            font-family: inherit;
        }
        
        [data-theme="dark"] .result-display {
            background: var(--gray-800);
            border-color: var(--gray-600);
        }
        
        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 16px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.78rem;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-blue { background: #0B5ED7; color: white; }
        .btn-blue:hover { background: #0A4CA8; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3); }
        
        .btn-green { background: #059669; color: white; }
        .btn-green:hover { background: #047857; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3); }
        
        .btn-outline { background: transparent; color: var(--text-secondary); border: 2px solid var(--border-color); }
        .btn-outline:hover { background: var(--bg-body); border-color: #0B5ED7; color: #0B5ED7; }
        
        .btn-sm { padding: 3px 10px; font-size: 0.7rem; border-radius: 6px; }
        
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
            color: var(--primary);
            font-weight: 600;
        }
        
        [data-theme="dark"] .footer {
            border-color: var(--gray-700);
            color: var(--gray-400);
        }
        
        /* ================================================================
           PRINT
           ================================================================ */
        @media print {
            .top-nav, .sidebar, .btn, .print-button, .no-print { display: none !important; }
            .main-content { margin: 0 !important; padding: 20px !important; }
            .detail-card, .result-item { border: 1px solid #ddd !important; box-shadow: none !important; }
            .status-badge, .result-status-badge { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .grid-cols-1.lg\:grid-cols-3 { grid-template-columns: 1fr; }
            .detail-card { padding: 16px; }
            .result-item { padding: 12px 16px; }
            .main-content { padding: 10px; }
        }
        
        @media (max-width: 480px) {
            .page-title { font-size: 1.1rem; }
            .detail-card { padding: 12px; }
            .grid-cols-2 { grid-template-columns: 1fr; }
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
            <input type="text" id="searchInput" placeholder="Search...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <span class="branch-badge">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($user_branch_name) ?>
        </span>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= $unread_notifications ?? 0 > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $profile_pic_url ?? '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png' ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($user_full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <?php if ($request): ?>
    
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-flask mr-2"></i> Lab Results
            </h1>
            <p class="page-subtitle">
                <span class="font-mono font-semibold"><?= htmlspecialchars($request['request_number']) ?></span>
                <span class="ml-2 inline-flex bg-white/20 text-white px-3 py-1 rounded-full text-xs">
                    <i class="fas fa-user mr-1"></i> <?= htmlspecialchars($request['patient_name']) ?>
                </span>
                <span class="ml-2 inline-flex bg-white/20 text-white px-3 py-1 rounded-full text-xs">
                    <i class="fas fa-flask mr-1"></i> <?= count($test_items) ?> tests
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap no-print">
            <a href="in_progress.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <button onclick="window.print()" class="print-button">
                <i class="fas fa-print"></i> Print Results
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- REQUEST OVERVIEW -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">
        
        <div class="detail-card lg:col-span-2">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">
                <i class="fas fa-info-circle text-primary mr-2"></i> Request Information
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                <div>
                    <p class="detail-label">Request Number</p>
                    <p class="detail-value font-mono text-sm"><?= htmlspecialchars($request['request_number']) ?></p>
                </div>
                <div>
                    <p class="detail-label">Status</p>
                    <p class="detail-value">
                        <span class="status-badge <?= $request['status'] ?>">
                            <?= ucfirst(str_replace('_', ' ', $request['status'])) ?>
                        </span>
                    </p>
                </div>
                <div>
                    <p class="detail-label">Doctor</p>
                    <p class="detail-value"><?= htmlspecialchars($request['doctor_name']) ?></p>
                </div>
                <div>
                    <p class="detail-label">Requested</p>
                    <p class="detail-value"><?= date('M d, Y h:i A', strtotime($request['requested_at'])) ?></p>
                </div>
                <div>
                    <p class="detail-label">Visit Number</p>
                    <p class="detail-value"><?= htmlspecialchars($request['visit_number'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Tests</p>
                    <p class="detail-value"><?= count($test_items) ?></p>
                </div>
            </div>
        </div>
        
        <div class="detail-card">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">
                <i class="fas fa-user text-primary mr-2"></i> Patient Information
            </h3>
            <div class="space-y-2">
                <div>
                    <p class="detail-label">Name</p>
                    <p class="detail-value"><?= htmlspecialchars($request['patient_name']) ?></p>
                </div>
                <div>
                    <p class="detail-label">Patient ID</p>
                    <p class="detail-value"><?= htmlspecialchars($request['patient_id'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Phone</p>
                    <p class="detail-value"><?= htmlspecialchars($request['phone'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Email</p>
                    <p class="detail-value"><?= htmlspecialchars($request['email'] ?? 'N/A') ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- TEST RESULTS -->
    <!-- ================================================================ -->
    <div class="detail-card">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">
            <i class="fas fa-flask text-purple-600 mr-2"></i> Test Results
        </h3>
        
        <?php if (count($test_items) > 0): ?>
            <?php foreach ($test_items as $index => $item): 
                $status = $item['status'] ?? 'pending';
                $has_result = !empty($item['result']);
            ?>
                <div class="result-item <?= $status ?>">
                    <div class="flex flex-wrap justify-between items-start gap-3">
                        <div class="flex-1">
                            <div class="flex items-center gap-3 flex-wrap">
                                <span class="font-semibold">#<?= $index + 1 ?></span>
                                <span class="font-medium"><?= htmlspecialchars($item['test_name']) ?></span>
                                <span class="result-status-badge <?= $status ?>">
                                    <?php if ($status === 'pending'): ?>
                                        ⏳ Pending
                                    <?php elseif ($status === 'in_progress'): ?>
                                        🔬 In Progress
                                    <?php elseif ($status === 'completed'): ?>
                                        ✅ Completed
                                    <?php else: ?>
                                        <?= ucfirst($status) ?>
                                    <?php endif; ?>
                                </span>
                                <?php if (!empty($item['price']) && $item['price'] > 0): ?>
                                    <span class="text-xs text-gray-500">TSh <?= number_format($item['price']) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($item['reference_range'])): ?>
                                <p class="text-xs text-gray-400 mt-1">
                                    Reference Range: <?= htmlspecialchars($item['reference_range']) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="text-xs text-gray-400">
                            <?php if ($item['completed_at']): ?>
                                Completed: <?= date('M d, Y h:i A', strtotime($item['completed_at'])) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Result Display -->
                    <div class="result-display <?= $has_result ? '' : 'empty' ?>">
                        <?php if ($has_result): ?>
                            <?= nl2br(htmlspecialchars($item['result'])) ?>
                            <?php if (!empty($item['comments'])): ?>
                                <div class="text-xs text-gray-400 mt-2">
                                    <strong>Notes:</strong> <?= htmlspecialchars($item['comments']) ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            No result available yet
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="text-center py-8 text-gray-400">
                <i class="fas fa-flask text-3xl block mb-2"></i>
                <p>No test items found</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- SUMMARY SECTION -->
    <!-- ================================================================ -->
    <?php
    $total_tests = count($test_items);
    $completed_tests = 0;
    $pending_tests = 0;
    $in_progress_tests = 0;
    
    foreach ($test_items as $item) {
        $status = $item['status'] ?? 'pending';
        if ($status === 'completed') $completed_tests++;
        elseif ($status === 'in_progress') $in_progress_tests++;
        else $pending_tests++;
    }
    ?>
    
    <div class="detail-card mt-5">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">
            <i class="fas fa-chart-pie text-blue-600 mr-2"></i> Summary
        </h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="text-center p-3 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-800">
                <p class="text-2xl font-bold text-green-600"><?= $completed_tests ?></p>
                <p class="text-xs text-gray-500">✅ Completed</p>
            </div>
            <div class="text-center p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                <p class="text-2xl font-bold text-blue-600"><?= $in_progress_tests ?></p>
                <p class="text-xs text-gray-500">🔬 In Progress</p>
            </div>
            <div class="text-center p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-200 dark:border-yellow-800">
                <p class="text-2xl font-bold text-yellow-600"><?= $pending_tests ?></p>
                <p class="text-xs text-gray-500">⏳ Pending</p>
            </div>
            <div class="text-center p-3 bg-purple-50 dark:bg-purple-900/20 rounded-lg border border-purple-200 dark:border-purple-800">
                <p class="text-2xl font-bold text-purple-600"><?= $total_tests ?></p>
                <p class="text-xs text-gray-500">📊 Total Tests</p>
            </div>
        </div>
        
        <?php if ($request['status'] === 'completed' && $completed_tests === $total_tests && $total_tests > 0): ?>
            <div class="mt-4 p-3 bg-green-100 dark:bg-green-900/30 rounded-lg border border-green-300 dark:border-green-700 text-center">
                <i class="fas fa-check-circle text-green-600 mr-2"></i>
                <span class="text-green-700 dark:text-green-400 font-medium">All tests are complete. Results have been sent to the doctor.</span>
            </div>
        <?php endif; ?>
    </div>

    <?php else: ?>
        <div class="text-center py-8 text-gray-400">
            <i class="fas fa-flask text-4xl block mb-3"></i>
            <p class="text-lg">Request not found</p>
            <a href="in_progress.php" class="text-blue-600 hover:underline">Back to requests</a>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer no-print">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            View Results
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

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
    // SIDEBAR TOGGLE
    // ================================================================
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

    console.log('%c🧪 Braick - View Results', 'font-size:18px; font-weight:bold; color:#7C3AED;');
    console.log('%c🔐 Session-based login active - redirects to login if not authenticated', 'font-size:12px; color:#34D399;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📋 Request: <?= htmlspecialchars($request['request_number'] ?? 'N/A') ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c👤 Patient: <?= htmlspecialchars($request['patient_name'] ?? 'N/A') ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Tests: <?= count($test_items) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c✅ Completed: <?= $completed_tests ?? 0 ?> | 🔬 In Progress: <?= $in_progress_tests ?? 0 ?> | ⏳ Pending: <?= $pending_tests ?? 0 ?>', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>