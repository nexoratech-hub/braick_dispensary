<?php
// ================================================================
// FILE: frontend/components/laboratory_header.php
// LABORATORY - SHARED HEADER (FIXED)
// ✅ SIDEBAR TOGGLE BUTTON INAFANYA KAZI
// ✅ DATE/TIME CARD - CSS NZURI
// ✅ SEARCH BAR WIDTH IMEPUNGUZWA
// ✅ DESIGN INAFANANA NA PHARMACY
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// START SESSION
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION - CHECK IF USER IS LOGGED IN
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

// ================================================================
// CHECK IF USER HAS ACCESS (Laboratory or Admin)
// ================================================================
$allowed_roles = ['laboratory', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: /dispensary_system/frontend/pages/doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: /dispensary_system/frontend/pages/pharmacy/dashboard.php'); break;
        case 'cashier': header('Location: /dispensary_system/frontend/pages/cashier/dashboard.php'); break;
        case 'reception': header('Location: /dispensary_system/frontend/pages/reception/dashboard.php'); break;
        default: header('Location: /dispensary_system/frontend/pages/login.php'); break;
    }
    exit;
}

// ================================================================
// GET USER DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Laboratory';
$user_role = $_SESSION['role'] ?? 'laboratory';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    $db = null;
}

// ================================================================
// GET UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
$notifications_list = [];

if ($db !== null && $user_id > 0) {
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) as total 
            FROM notifications 
            WHERE user_id = ? AND is_read = 0
        ");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $unread_notifications = (int)($result['total'] ?? 0);
        
        $stmt = $db->prepare("
            SELECT id, title, message, type, link, is_read, created_at 
            FROM notifications 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $stmt->execute([$user_id]);
        $notifications_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Notification error: " . $e->getMessage());
        $unread_notifications = 0;
        $notifications_list = [];
    }
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

// ================================================================
// LOGO PATH
// ================================================================
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// GET CURRENT PAGE
// ================================================================
$current_page = basename($_SERVER['PHP_SELF']);
$page_title = ucfirst(str_replace('.php', '', $current_page));
if (empty($page_title) || $page_title == '') {
    $page_title = 'Dashboard';
}

// ================================================================
// DARK MODE
// ================================================================
$dark_mode = isset($_COOKIE['dark_mode']) ? $_COOKIE['dark_mode'] : 'light';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $dark_mode ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Braick Dispensary - Laboratory <?= $page_title ?></title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
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
            --warning-light: #FBBF24;
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
            --primary: #3B82F6;
            --primary-dark: #2563EB;
            --primary-light: #93C5FD;
            --primary-bg: #1E3A5F;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
        
        /* ================================================================
           TOP NAVIGATION
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
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        
        /* ================================================================
           SIDEBAR TOGGLE BUTTON IN HEADER (KAMA PHARMACY)
           ================================================================ */
        .header-toggle-btn {
            display: none;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 8px 14px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.3s ease;
            align-items: center;
            gap: 8px;
            box-shadow: 0 2px 12px rgba(11, 94, 215, 0.25);
            position: relative;
        }
        
        .header-toggle-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.35);
        }
        
        .header-toggle-btn .toggle-label {
            font-size: 0.65rem;
            font-weight: 600;
            letter-spacing: 0.05em;
        }
        
        [data-theme="dark"] .header-toggle-btn {
            background: #0A4CA8;
            box-shadow: 0 2px 12px rgba(10, 76, 168, 0.3);
        }
        
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .header-toggle-btn { display: flex !important; }
        }
        
        @media (max-width: 768px) {
            .header-toggle-btn {
                padding: 6px 10px;
                font-size: 0.8rem;
                border-radius: 8px;
            }
            .header-toggle-btn .toggle-label {
                font-size: 0.55rem;
            }
        }
        
        @media (max-width: 480px) {
            .header-toggle-btn {
                padding: 5px 8px;
                font-size: 0.7rem;
                border-radius: 6px;
            }
            .header-toggle-btn .toggle-label {
                display: none;
            }
        }
        
        /* ================================================================
           SEARCH BAR - SMALLER WIDTH (KAMA PHARMACY)
           ================================================================ */
        .top-nav .search-wrapper {
            display: flex;
            align-items: center;
            background: var(--bg-body);
            border-radius: 10px;
            border: 2px solid var(--border-color);
            transition: all 0.3s;
            flex: 0 1 320px;
            max-width: 320px;
            min-width: 160px;
            position: relative;
        }
        
        .top-nav .search-wrapper:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15);
        }
        
        .top-nav .search-wrapper input {
            border: none;
            background: transparent;
            padding: 8px 12px;
            width: 100%;
            font-size: 0.8rem;
            outline: none;
            color: var(--text-primary);
        }
        
        .top-nav .search-wrapper input::placeholder {
            color: var(--text-secondary);
            font-size: 0.75rem;
        }
        
        .top-nav .search-wrapper .search-icon {
            padding: 0 8px 0 12px;
            color: var(--text-secondary);
            font-size: 0.8rem;
        }
        
        .top-nav .search-wrapper .search-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 6px 14px;
            border-radius: 0 10px 10px 0;
            cursor: pointer;
            font-size: 0.75rem;
            transition: all 0.3s;
            white-space: nowrap;
            font-weight: 500;
        }
        
        .top-nav .search-wrapper .search-btn:hover {
            background: var(--primary-dark);
        }
        
        @media (max-width: 1024px) {
            .top-nav .search-wrapper {
                flex: 0 1 220px;
                max-width: 220px;
            }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper {
                flex: 0 1 160px;
                max-width: 160px;
                min-width: 100px;
            }
            .top-nav .search-wrapper .search-btn {
                padding: 4px 10px;
                font-size: 0.65rem;
            }
            .top-nav .search-wrapper input {
                padding: 6px 8px;
                font-size: 0.7rem;
            }
        }
        
        @media (max-width: 480px) {
            .top-nav .search-wrapper {
                flex: 0 1 120px;
                max-width: 120px;
                min-width: 80px;
            }
            .top-nav .search-wrapper .search-btn {
                display: none;
            }
            .top-nav .search-wrapper .search-icon {
                padding: 0 4px 0 8px;
                font-size: 0.7rem;
            }
        }
        
        /* ================================================================
           DATE & TIME CARD - CSS NZURI (KAMA PHARMACY)
           ================================================================ */
        .datetime-card {
            display: flex;
            align-items: center;
            gap: 6px;
            background: var(--primary-bg);
            padding: 5px 14px 5px 10px;
            border-radius: 10px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            white-space: nowrap;
        }
        
        .datetime-card:hover {
            border-color: var(--primary);
            box-shadow: 0 2px 12px rgba(11, 94, 215, 0.1);
        }
        
        .datetime-card .dt-icon {
            color: var(--primary);
            font-size: 0.75rem;
            opacity: 0.7;
        }
        
        .datetime-card .dt-text {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 500;
            letter-spacing: 0.01em;
        }
        
        .datetime-card .dt-time {
            font-size: 0.8rem;
            color: var(--primary);
            font-weight: 700;
            font-family: 'Courier New', monospace;
            padding: 0 4px;
        }
        
        .datetime-card .dt-divider {
            width: 1px;
            height: 18px;
            background: var(--border-color);
            margin: 0 4px;
        }
        
        [data-theme="dark"] .datetime-card {
            background: #1E3A5F;
            border-color: #334155;
        }
        
        [data-theme="dark"] .datetime-card .dt-time {
            color: #60A5FA;
        }
        
        .datetime-card .dt-separator {
            color: var(--border-color);
            font-size: 0.6rem;
            margin: 0 2px;
        }
        
        @media (max-width: 768px) {
            .datetime-card {
                padding: 4px 10px 4px 8px;
                gap: 4px;
            }
            .datetime-card .dt-text {
                font-size: 0.6rem;
            }
            .datetime-card .dt-time {
                font-size: 0.7rem;
            }
            .datetime-card .dt-divider {
                height: 14px;
            }
        }
        
        @media (max-width: 480px) {
            .datetime-card {
                padding: 3px 6px;
                border-radius: 6px;
            }
            .datetime-card .dt-text {
                display: none;
            }
            .datetime-card .dt-time {
                font-size: 0.65rem;
            }
            .datetime-card .dt-divider {
                display: none;
            }
            .datetime-card .dt-icon {
                font-size: 0.6rem;
            }
        }
        
        /* ================================================================
           AVATAR
           ================================================================ */
        .top-nav .avatar {
            width: 36px;
            height: 36px;
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
        
        /* ================================================================
           ICON BUTTON
           ================================================================ */
        .top-nav .icon-btn {
            width: 36px;
            height: 36px;
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
        
        /* ================================================================
           NOTIFICATION BELL
           ================================================================ */
        .notif-bell-wrapper {
            position: relative;
        }
        
        .notif-dot {
            position: absolute;
            top: -2px;
            right: -2px;
            min-width: 18px;
            height: 18px;
            border-radius: 50%;
            font-size: 0.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--bg-nav);
            padding: 0 4px;
            line-height: 1;
        }
        
        .notif-dot.has-notif {
            background: var(--danger);
            color: white;
            animation: pulse-dot 2s infinite;
        }
        
        .notif-dot.no-notif {
            background: var(--gray-400);
            color: white;
            font-size: 0.45rem;
            animation: none;
            min-width: 16px;
            height: 16px;
        }
        
        @keyframes pulse-dot {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        /* ================================================================
           NOTIFICATION DROPDOWN
           ================================================================ */
        .notif-dropdown {
            position: absolute;
            top: 46px;
            right: 0;
            width: 360px;
            max-height: 420px;
            background: var(--bg-card);
            border-radius: 12px;
            border: 2px solid var(--border-color);
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            z-index: 100;
            display: none;
            overflow: hidden;
        }
        
        .notif-dropdown.open {
            display: block;
            animation: fadeInDown 0.3s ease;
        }
        
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .notif-dropdown .notif-header {
            padding: 10px 16px;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--bg-body);
        }
        
        .notif-dropdown .notif-header .notif-title {
            font-weight: 600;
            font-size: 0.8rem;
            color: var(--text-primary);
        }
        
        .notif-dropdown .notif-header .notif-mark-all {
            font-size: 0.65rem;
            color: var(--primary);
            cursor: pointer;
            text-decoration: none;
            font-weight: 500;
        }
        
        .notif-dropdown .notif-header .notif-mark-all:hover {
            text-decoration: underline;
        }
        
        .notif-dropdown .notif-list {
            max-height: 300px;
            overflow-y: auto;
            padding: 4px 0;
        }
        
        .notif-dropdown .notif-list::-webkit-scrollbar {
            width: 4px;
        }
        
        .notif-dropdown .notif-list::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 4px;
        }
        
        .notif-dropdown .notif-item {
            padding: 8px 14px;
            border-bottom: 1px solid var(--border-color);
            transition: background 0.2s ease;
            cursor: pointer;
            text-decoration: none;
            display: block;
        }
        
        .notif-dropdown .notif-item:hover {
            background: var(--primary-bg);
        }
        
        .notif-dropdown .notif-item:last-child {
            border-bottom: none;
        }
        
        .notif-dropdown .notif-item .notif-item-title {
            font-weight: 600;
            font-size: 0.75rem;
            color: var(--text-primary);
        }
        
        .notif-dropdown .notif-item .notif-item-message {
            font-size: 0.7rem;
            color: var(--text-secondary);
            margin-top: 2px;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .notif-dropdown .notif-item .notif-item-time {
            font-size: 0.55rem;
            color: var(--text-secondary);
            margin-top: 4px;
            display: block;
        }
        
        .notif-dropdown .notif-item.unread {
            border-left: 3px solid var(--primary);
            background: var(--primary-bg);
        }
        
        .notif-dropdown .notif-item.unread:hover {
            background: var(--success-bg);
        }
        
        .notif-dropdown .notif-empty {
            padding: 30px 20px;
            text-align: center;
            color: var(--text-secondary);
        }
        
        .notif-dropdown .notif-empty i {
            font-size: 2rem;
            color: var(--border-color);
            display: block;
            margin-bottom: 8px;
        }
        
        .notif-dropdown .notif-empty p {
            font-size: 0.8rem;
        }
        
        .notif-dropdown .notif-footer {
            padding: 8px 16px;
            border-top: 2px solid var(--border-color);
            text-align: center;
            background: var(--bg-body);
        }
        
        .notif-dropdown .notif-footer a {
            font-size: 0.7rem;
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }
        
        .notif-dropdown .notif-footer a:hover {
            text-decoration: underline;
        }
        
        [data-theme="dark"] .notif-dropdown .notif-item.unread {
            background: #1E3A5F;
        }
        
        [data-theme="dark"] .notif-dropdown .notif-item.unread:hover {
            background: #1A3A2A;
        }
        
        /* ================================================================
           DARK MODE TOGGLE
           ================================================================ */
        .dark-toggle-btn {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 4px 10px;
            cursor: pointer;
            font-size: 0.75rem;
            color: var(--text-primary);
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 4px;
            text-decoration: none;
        }
        
        .dark-toggle-btn:hover {
            border-color: var(--primary);
            background: var(--bg-card);
        }
        
        .dark-toggle-btn i { font-size: 0.8rem; }
        
        /* ================================================================
           BRANCH BADGE
           ================================================================ */
        .branch-badge {
            display: inline-block;
            font-size: 0.55rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            background: var(--success-bg);
            color: var(--success);
            white-space: nowrap;
        }
        
        [data-theme="dark"] .branch-badge {
            background: #1A3A2A;
            color: #34D399;
        }
        
        /* ================================================================
           BADGE
           ================================================================ */
        .badge {
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: white;
            border: none;
        }
        
        .badge-blue { background: var(--primary); }
        .badge-green { background: var(--success); }
        .badge-gray { background: var(--gray-500); }
        .badge-yellow { background: #D97706; }
        .badge-red { background: var(--danger); }
        .badge-purple { background: #7C3AED; }
        .badge-pink { background: #DB2777; }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .notif-dropdown { right: -20px; width: 320px; }
        }
        
        @media (max-width: 768px) {
            .top-nav .datetime { display: none; }
            .notif-dropdown { right: -30px; width: 300px; }
            .branch-badge { font-size: 0.5rem; padding: 1px 8px; }
        }
        
        @media (max-width: 640px) {
            .notif-dropdown { right: -40px; width: 280px; }
            .dark-toggle-btn { padding: 3px 6px; font-size: 0.65rem; }
            .dark-toggle-btn span { display: none; }
            .top-nav .avatar { width: 30px; height: 30px; }
            .top-nav .icon-btn { width: 30px; height: 30px; font-size: 0.8rem; }
        }
        
        .spinner {
            display: inline-block;
            width: 14px;
            height: 14px;
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
<!-- TOP NAVIGATION -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        
        <!-- ================================================================ -->
        <!-- SIDEBAR TOGGLE BUTTON IN HEADER -->
        <!-- ================================================================ -->
        <button class="header-toggle-btn" id="sidebarToggleBtn" aria-label="Toggle Sidebar">
            <i class="fas fa-bars"></i>
            <span class="toggle-label">MENU</span>
        </button>
        
        <!-- ================================================================ -->
        <!-- SEARCH BAR - SMALLER WIDTH -->
        <!-- ================================================================ -->
        <div class="search-wrapper">
            <span class="search-icon"><i class="fas fa-search"></i></span>
            <input type="text" id="searchInput" placeholder="Search tests...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        
        <!-- Branch Badge -->
        <span class="branch-badge">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($user_branch_name) ?>
        </span>
        
        <!-- ================================================================ -->
        <!-- DATE & TIME CARD - CSS NZURI -->
        <!-- ================================================================ -->
        <div class="datetime-card" id="datetimeCard">
            <span class="dt-icon"><i class="fas fa-calendar-alt"></i></span>
            <span class="dt-text" id="dateText"><?= date('M d, Y') ?></span>
            <span class="dt-divider"></span>
            <span class="dt-icon"><i class="fas fa-clock"></i></span>
            <span class="dt-time" id="timeText"><?= date('h:i:s A') ?></span>
        </div>
        
        <!-- ================================================================ -->
        <!-- DARK MODE TOGGLE -->
        <!-- ================================================================ -->
        <button class="dark-toggle-btn" id="darkModeToggle" title="Toggle Dark Mode">
            <i id="darkIcon" class="fas <?= $dark_mode === 'dark' ? 'fa-sun' : 'fa-moon' ?>"></i>
            <span id="darkText"><?= $dark_mode === 'dark' ? 'Light' : 'Dark' ?></span>
        </button>
        
        <!-- ================================================================ -->
        <!-- NOTIFICATION BELL -->
        <!-- ================================================================ -->
        <div class="notif-bell-wrapper">
            <button class="icon-btn" id="notifBellBtn" onclick="toggleNotifications()" title="Notifications">
                <i class="fas fa-bell text-lg"></i>
                <span class="notif-dot <?= $unread_notifications > 0 ? 'has-notif' : 'no-notif' ?>">
                    <?php if ($unread_notifications > 0): ?>
                        <?= $unread_notifications > 9 ? '9+' : $unread_notifications ?>
                    <?php else: ?>
                        <i class="fas fa-check" style="font-size:0.35rem;"></i>
                    <?php endif; ?>
                </span>
            </button>
            
            <!-- Notification Dropdown -->
            <div class="notif-dropdown" id="notifDropdown">
                <div class="notif-header">
                    <span class="notif-title">
                        <i class="fas fa-bell mr-1"></i> Notifications
                        <?php if ($unread_notifications > 0): ?>
                            <span class="badge badge-red" style="font-size:0.55rem; padding:1px 8px;">
                                <?= $unread_notifications ?> new
                            </span>
                        <?php endif; ?>
                    </span>
                    <?php if ($unread_notifications > 0): ?>
                        <a href="#" class="notif-mark-all" onclick="markAllRead(event)">Mark all as read</a>
                    <?php endif; ?>
                </div>
                
                <div class="notif-list" id="notifList">
                    <?php if (count($notifications_list) > 0): ?>
                        <?php foreach ($notifications_list as $notif): ?>
                            <a href="<?= !empty($notif['link']) ? $notif['link'] : '#' ?>" 
                               class="notif-item <?= $notif['is_read'] == 0 ? 'unread' : '' ?>"
                               onclick="markNotificationRead(<?= $notif['id'] ?>, event)">
                                <div class="notif-item-title">
                                    <?= htmlspecialchars($notif['title']) ?>
                                    <?php if ($notif['is_read'] == 0): ?>
                                        <span class="badge badge-blue" style="font-size:0.45rem; padding:0 6px;">New</span>
                                    <?php endif; ?>
                                </div>
                                <div class="notif-item-message"><?= htmlspecialchars($notif['message']) ?></div>
                                <span class="notif-item-time">
                                    <i class="far fa-clock mr-1"></i>
                                    <?php 
                                        $time = strtotime($notif['created_at']);
                                        $diff = time() - $time;
                                        if ($diff < 60) {
                                            echo 'Just now';
                                        } elseif ($diff < 3600) {
                                            echo floor($diff / 60) . ' min ago';
                                        } elseif ($diff < 86400) {
                                            echo floor($diff / 3600) . ' hours ago';
                                        } else {
                                            echo date('M d, Y', $time);
                                        }
                                    ?>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="notif-empty">
                            <i class="fas fa-bell-slash"></i>
                            <p>No notifications</p>
                            <p style="font-size:0.65rem; color:var(--text-secondary);">All caught up!</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="notif-footer">
                    <a href="notifications.php">View all notifications</a>
                </div>
            </div>
        </div>
        
        <!-- Profile Avatar -->
        <a href="profile.php">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2236%22 height=%2236%22%3E%3Crect width=%2236%22 height=%2236%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2218%22 y=%2224%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2216%22 font-weight=%22bold%22%3E<?= strtoupper(substr($user_full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE TOGGLE
    // ================================================================
    (function() {
        var darkModeToggle = document.getElementById('darkModeToggle');
        var darkIcon = document.getElementById('darkIcon');
        var darkText = document.getElementById('darkText');
        var htmlElement = document.documentElement;
        
        var savedDarkMode = localStorage.getItem('darkMode');
        if (savedDarkMode === 'true') {
            htmlElement.setAttribute('data-theme', 'dark');
            if (darkIcon) darkIcon.className = 'fas fa-sun';
            if (darkText) darkText.textContent = 'Light';
        } else if (savedDarkMode === 'false') {
            htmlElement.removeAttribute('data-theme');
            if (darkIcon) darkIcon.className = 'fas fa-moon';
            if (darkText) darkText.textContent = 'Dark';
        } else {
            var cookieDark = document.cookie.match(/dark_mode=([^;]+)/);
            if (cookieDark && cookieDark[1] === 'true') {
                htmlElement.setAttribute('data-theme', 'dark');
                if (darkIcon) darkIcon.className = 'fas fa-sun';
                if (darkText) darkText.textContent = 'Light';
                localStorage.setItem('darkMode', 'true');
            }
        }
        
        if (darkModeToggle) {
            darkModeToggle.addEventListener('click', function() {
                var isDark = htmlElement.getAttribute('data-theme') === 'dark';
                if (isDark) {
                    htmlElement.removeAttribute('data-theme');
                    if (darkIcon) darkIcon.className = 'fas fa-moon';
                    if (darkText) darkText.textContent = 'Dark';
                    localStorage.setItem('darkMode', 'false');
                    document.cookie = "dark_mode=false; path=/; max-age=31536000";
                } else {
                    htmlElement.setAttribute('data-theme', 'dark');
                    if (darkIcon) darkIcon.className = 'fas fa-sun';
                    if (darkText) darkText.textContent = 'Light';
                    localStorage.setItem('darkMode', 'true');
                    document.cookie = "dark_mode=true; path=/; max-age=31536000";
                }
                console.log('🌙 Dark mode toggled to:', htmlElement.getAttribute('data-theme'));
            });
        }
    })();

    // ================================================================
    // SIDEBAR TOGGLE - INAFANYA KAZI KAMA PHARMACY
    // ================================================================
    (function() {
        function initToggle() {
            var toggleBtn = document.getElementById('sidebarToggleBtn');
            var sidebar = document.getElementById('sidebar');
            var overlay = document.getElementById('sidebarOverlay');
            
            if (!toggleBtn) {
                console.log('⚠️ Sidebar toggle button not found');
                return;
            }
            
            if (!sidebar) {
                console.log('⚠️ Sidebar not found');
                return;
            }
            
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.id = 'sidebarOverlay';
                overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.6);z-index:9998;display:none;backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);';
                document.body.appendChild(overlay);
            }
            
            function openSidebar() {
                sidebar.classList.add('open');
                overlay.style.display = 'block';
                overlay.classList.add('active');
                document.body.style.overflow = 'hidden';
                if (toggleBtn) {
                    toggleBtn.innerHTML = '<i class="fas fa-times"></i><span class="toggle-label">CLOSE</span>';
                }
                console.log('🔓 Sidebar opened');
            }
            
            function closeSidebar() {
                sidebar.classList.remove('open');
                overlay.style.display = 'none';
                overlay.classList.remove('active');
                document.body.style.overflow = '';
                if (toggleBtn) {
                    toggleBtn.innerHTML = '<i class="fas fa-bars"></i><span class="toggle-label">MENU</span>';
                }
                console.log('🔒 Sidebar closed');
            }
            
            function toggleSidebar() {
                if (sidebar.classList.contains('open')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            }
            
            // MAIN TOGGLE - BUTTON CLICK
            toggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleSidebar();
            });
            
            // Overlay click
            if (overlay) {
                overlay.addEventListener('click', function(e) {
                    if (e.target === overlay) {
                        closeSidebar();
                    }
                });
            }
            
            // ESC key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && sidebar.classList.contains('open')) {
                    closeSidebar();
                }
            });
            
            // Resize auto-close
            window.addEventListener('resize', function() {
                if (window.innerWidth > 1024 && sidebar.classList.contains('open')) {
                    closeSidebar();
                }
            });
            
            console.log('✅ Laboratory Sidebar toggle initialized');
        }
        
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initToggle);
        } else {
            initToggle();
        }
    })();

    // ================================================================
    // TOGGLE NOTIFICATION DROPDOWN
    // ================================================================
    function toggleNotifications() {
        var dropdown = document.getElementById('notifDropdown');
        if (dropdown) {
            dropdown.classList.toggle('open');
        }
    }

    document.addEventListener('click', function(e) {
        var wrapper = document.querySelector('.notif-bell-wrapper');
        var dropdown = document.getElementById('notifDropdown');
        if (wrapper && dropdown) {
            if (!wrapper.contains(e.target)) {
                dropdown.classList.remove('open');
            }
        }
    });

    // ================================================================
    // MARK NOTIFICATION AS READ
    // ================================================================
    function markNotificationRead(id, event) {
        if (event) event.preventDefault();
        if (!id) return;
        
        fetch('../../backend/api/mark_notification_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'id=' + id
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        })
        .catch(error => console.error('Error:', error));
    }

    function markAllRead(event) {
        if (event) event.preventDefault();
        
        fetch('../../backend/api/mark_all_notifications_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        })
        .catch(error => console.error('Error:', error));
    }

    // ================================================================
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            var currentPage = '<?= basename($_SERVER['PHP_SELF']) ?>';
            window.location.href = currentPage + '?search=' + encodeURIComponent(query);
        }
    }
    
    if (searchBtn) {
        searchBtn.addEventListener('click', performSearch);
    }
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') performSearch();
        });
    }

    // ================================================================
    // DATE & TIME - UPDATES EVERY SECOND
    // ================================================================
    function updateDateTime() {
        var now = new Date();
        
        var dateStr = now.toLocaleDateString('en-US', {
            month: 'short', day: 'numeric', year: 'numeric'
        });
        var dateEl = document.getElementById('dateText');
        if (dateEl) dateEl.textContent = dateStr;
        
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var timeEl = document.getElementById('timeText');
        if (timeEl) timeEl.textContent = timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // CONSOLE LOG
    // ================================================================
    console.log('%c🧪 Braick Dispensary - Laboratory Header (FIXED)', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Sidebar toggle button in header (like Pharmacy)', 'font-size:12px; color:#34D399;');
    console.log('%c✅ Search bar width reduced', 'font-size:12px; color:#34D399;');
    console.log('%c✅ Date/Time card with good CSS', 'font-size:12px; color:#34D399;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:12px; color:#059669;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($user_branch_name) ?>', 'font-size:12px; color:#6EA8FE;');
    console.log('%c🔔 Unread Notifications: <?= $unread_notifications ?>', 'font-size:12px; color:#DC2626;');
</script>
</body>
</html>