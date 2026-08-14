<?php
// ================================================================
// FILE: frontend/components/cashier_header.php
// CASHIER - SHARED HEADER (GREEN THEME)
// WITH PROFILE PICTURE - SHOWS ON ALL PAGES
// WITH DATE AND TIME - LIVE UPDATE
// WITH DARK MODE - FULLY WORKING
// WITH SIDEBAR TOGGLE - FULLY WORKING ON MOBILE
// ALLOWS: Cashier, Reception, Admin
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// START SESSION
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// SESSION CHECK - REDIRECT TO LOGIN IF NOT ALLOWED
// ================================================================
$allowed_roles = ['cashier', 'reception', 'admin'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

// ================================================================
// GET SESSION DATA
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'User';
$user_role = $_SESSION['role'] ?? 'cashier';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? 'user';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// INCLUDE DATABASE - CORRECT PATH
// ================================================================
require_once __DIR__ . '/../../backend/config/database.php';

// ================================================================
// GET UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// ================================================================
// PROFILE PICTURE - CHECK IF EXISTS
// ================================================================
$profile_pic_exists = false;
$profile_pic_url = '';

if (!empty($profile_pic)) {
    $file_path = $_SERVER['DOCUMENT_ROOT'] . '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic;
    if (file_exists($file_path)) {
        $profile_pic_exists = true;
        $profile_pic_url = '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic;
    } else {
        $_SESSION['profile_pic'] = '';
        $profile_pic = '';
    }
}

$default_letter = strtoupper(substr($user_full_name, 0, 1));

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
// DARK MODE - Check from cookie/localStorage via JS
// ================================================================
$dark_mode = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light';

// ================================================================
// CHECK IF USER IS RECEPTION (for display message)
// ================================================================
$is_reception = ($user_role === 'reception');
$is_admin = ($user_role === 'admin');
$is_cashier = ($user_role === 'cashier');
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $dark_mode ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Braick Dispensary - Cashier <?= $page_title ?></title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <style>
        /* ================================================================
           ROOT VARIABLES - LIGHT MODE (DEFAULT)
           ================================================================ */
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
        
        /* ================================================================
           ROOT VARIABLES - DARK MODE
           ================================================================ */
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
            --gray-50: #1E293B;
            --gray-100: #334155;
            --gray-200: #475569;
            --gray-300: #64748B;
            --gray-400: #94A3B8;
            --gray-500: #CBD5E1;
            --gray-600: #E2E8F0;
            --gray-700: #F1F5F9;
            --gray-800: #F8FAFC;
            --gray-900: #FFFFFF;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
            min-height: 100vh;
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
            gap: 12px;
        }
        
        /* ================================================================
           SIDEBAR TOGGLE BUTTON - CRITICAL: VISIBLE ON MOBILE
           ================================================================ */
        .sidebar-toggle-btn {
            display: none;
            background: transparent;
            border: none;
            color: var(--text-primary);
            font-size: 1.4rem;
            cursor: pointer;
            padding: 6px 10px;
            border-radius: 8px;
            transition: all 0.3s ease;
            line-height: 1;
            flex-shrink: 0;
        }
        
        .sidebar-toggle-btn:hover {
            background: var(--success-bg);
            color: var(--success);
        }
        
        .sidebar-toggle-btn:active {
            transform: scale(0.9);
        }
        
        /* Show on mobile */
        @media (max-width: 1024px) {
            .sidebar-toggle-btn {
                display: block !important;
            }
            .top-nav {
                left: 0;
                padding: 0 16px;
            }
        }
        
        /* ================================================================
           SEARCH WRAPPER
           ================================================================ */
        .top-nav .search-wrapper {
            display: flex;
            align-items: center;
            background: var(--bg-body);
            border-radius: 10px;
            border: 2px solid var(--border-color);
            transition: all 0.3s;
            flex: 1;
            max-width: 500px;
            min-width: 120px;
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
        
        /* ================================================================
           DASHBOARD LINK
           ================================================================ */
        .dashboard-link {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-primary);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            padding: 4px 8px;
            border-radius: 8px;
            white-space: nowrap;
        }
        
        .dashboard-link:hover {
            color: var(--success);
            background: var(--success-bg);
        }
        
        .dashboard-link i {
            color: var(--success);
            font-size: 1.1rem;
        }
        
        [data-theme="dark"] .dashboard-link:hover {
            background: #1A3A2A;
        }
        
        /* ================================================================
           TOP NAV RIGHT ELEMENTS
           ================================================================ */
        .top-nav .datetime {
            font-size: 0.78rem;
            color: var(--text-secondary);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }
        
        .top-nav .datetime .date-part {
            color: var(--text-secondary);
        }
        
        .top-nav .datetime .time-part {
            color: var(--success);
            font-weight: 600;
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
        
        .top-nav .avatar-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            font-weight: 700;
            color: white;
            background: var(--success);
            border: 2px solid var(--success);
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .top-nav .avatar-avatar:hover {
            transform: scale(1.05);
            border-color: var(--success-light);
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
        
        .notif-dot.has-notif {
            background: var(--danger);
        }
        
        .notif-dot.no-notif {
            background: var(--gray-400);
            animation: none;
        }
        
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
            white-space: nowrap;
        }
        
        .dark-toggle-btn:hover {
            border-color: var(--success);
            background: var(--bg-card);
        }
        
        .role-badge {
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            background: var(--primary-bg);
            color: var(--primary);
            text-transform: uppercase;
            white-space: nowrap;
        }
        
        [data-theme="dark"] .role-badge {
            background: #1E3A5F;
            color: #6EA8FE;
        }
        
        .branch-badge {
            font-size: 0.6rem;
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
        
        /* Reception Badge - REMOVED */
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 24px 28px;
            min-height: calc(100vh - 68px);
            transition: background 0.3s ease;
        }
        
        /* ================================================================
           STAT CARD
           ================================================================ */
        .stat-card {
            border-radius: 16px;
            padding: 18px 20px;
            border: none;
            transition: all 0.3s;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .stat-card:active {
            transform: scale(0.98);
        }
        
        .stat-card .stat-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            background: rgba(255,255,255,0.15);
            color: white;
            flex-shrink: 0;
        }
        
        .stat-card .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: white;
            line-height: 1.2;
        }
        
        .stat-card .stat-label {
            font-size: 0.75rem;
            color: rgba(255,255,255,0.8);
            font-weight: 500;
        }
        
        .stat-card .stat-trend {
            font-size: 0.65rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            background: rgba(255,255,255,0.15);
            color: white;
            display: inline-block;
        }
        
        .stat-card .nav-arrow {
            opacity: 0;
            transition: all 0.3s ease;
            margin-left: 8px;
            font-size: 0.8rem;
        }
        
        .stat-card:hover .nav-arrow {
            opacity: 1;
            transform: translateX(4px);
        }
        
        .card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 18px 20px;
            border: 2px solid var(--border-color);
            transition: all 0.3s;
        }
        
        .card:hover {
            border-color: var(--success);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.08);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .card-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
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
        
        .btn-green {
            background: var(--success);
            color: white;
        }
        .btn-green:hover {
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
        
        .btn-sm { padding: 3px 10px; font-size: 0.7rem; border-radius: 6px; }
        
        .badge {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: white;
            border: none;
        }
        
        .badge-pending { background: #D97706; color: white; }
        .badge-partial { background: #0B5ED7; color: white; }
        .badge-paid { background: #059669; color: white; }
        .badge-cancelled { background: #DC2626; color: white; }
        .badge-green { background: #059669; color: white; }
        .badge-red { background: #DC2626; color: white; }
        .badge-yellow { background: #D97706; color: white; }
        
        .page-header {
            border-bottom: 3px solid var(--success);
            padding-bottom: 12px;
        }
        
        .page-header .page-title {
            color: var(--success-dark);
            font-size: 1.8rem;
            font-weight: 700;
        }
        
        [data-theme="dark"] .page-header .page-title {
            color: var(--success-light);
        }
        
        .page-header .page-subtitle {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .page-header .branch-tag {
            background: var(--success);
            color: white;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
        }
        
        .data-table th {
            text-align: left;
            padding: 10px 14px;
            font-weight: 700;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #fff;
            background: var(--success);
            border-bottom: 3px solid var(--success-dark);
            white-space: nowrap;
        }
        
        .data-table th:first-child {
            border-radius: 8px 0 0 0;
        }
        
        .data-table th:last-child {
            border-radius: 0 8px 0 0;
        }
        
        .data-table tbody tr:nth-child(even) {
            background: var(--primary-bg);
        }
        
        .data-table tbody tr:nth-child(odd) {
            background: var(--bg-card);
        }
        
        .data-table tbody tr:hover {
            background: var(--success-bg);
        }
        
        [data-theme="dark"] .data-table tbody tr:hover {
            background: #1A3A2A;
        }
        
        .data-table td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 12px 18px;
            border-radius: 12px;
            z-index: 999;
            max-width: 360px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
        }
        
        .toast-custom.show {
            transform: translateY(0);
            opacity: 1;
        }
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: #D97706; }
        
        .footer {
            padding: 14px 0;
            border-top: 2px solid var(--border-color);
            margin-top: 20px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
            transition: all 0.3s ease;
        }
        
        .footer .footer-brand { color: var(--success); font-weight: 600; }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .top-nav { left: 0; padding: 0 16px; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; min-width: 100px; }
        }
        
        @media (max-width: 768px) {
            .top-nav { height: 60px; padding: 0 12px; }
            .top-nav .search-wrapper { max-width: 180px; min-width: 80px; }
            .top-nav .search-wrapper input { font-size: 0.75rem; padding: 6px 10px; }
            .top-nav .search-wrapper .search-btn { padding: 6px 10px; font-size: 0.7rem; }
            .top-nav .search-wrapper .search-btn span { display: none; }
            .top-nav .search-wrapper .search-btn i { margin-right: 0; }
            .top-nav .datetime .date-part { display: none; }
            .top-nav .datetime .time-part { font-size: 0.7rem; }
            .top-nav .icon-btn { width: 32px; height: 32px; }
            .top-nav .icon-btn i { font-size: 0.9rem; }
            .top-nav .avatar, .top-nav .avatar-avatar { width: 32px; height: 32px; font-size: 0.8rem; }
            .dark-toggle-btn { padding: 4px 10px; font-size: 0.7rem; }
            .dark-toggle-btn span { display: none; }
            .branch-badge { font-size: 0.5rem; padding: 2px 8px; }
            .role-badge { font-size: 0.5rem; padding: 2px 8px; }
            .main-content { padding: 12px; margin-top: 60px; }
            .sidebar-toggle-btn { font-size: 1.2rem; padding: 4px 8px; }
            .dashboard-link span { display: none; }
        }
        
        @media (max-width: 480px) {
            .top-nav { height: 56px; padding: 0 8px; gap: 4px; }
            .main-content { padding: 8px; margin-top: 56px; }
            .top-nav .search-wrapper { max-width: 100px; min-width: 60px; }
            .top-nav .search-wrapper input { font-size: 0.6rem; padding: 4px 6px; }
            .top-nav .search-wrapper .search-btn { padding: 4px 6px; font-size: 0.55rem; }
            .top-nav .datetime .time-part { font-size: 0.6rem; }
            .dark-toggle-btn { padding: 3px 6px; font-size: 0.6rem; }
            .dark-toggle-btn i { font-size: 0.7rem; }
            .top-nav .icon-btn { width: 28px; height: 28px; }
            .top-nav .icon-btn i { font-size: 0.8rem; }
            .top-nav .avatar, .top-nav .avatar-avatar { width: 28px; height: 28px; font-size: 0.7rem; }
            .branch-badge { font-size: 0.45rem; padding: 2px 6px; }
            .role-badge { font-size: 0.45rem; padding: 2px 6px; }
            .sidebar-toggle-btn { font-size: 1rem; padding: 2px 6px; }
            .page-header .page-title { font-size: 1rem; }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.4s ease forwards;
            opacity: 0;
        }
    </style>
    
    <!-- Preload dark mode to prevent flash -->
    <script>
        (function() {
            var darkMode = localStorage.getItem('darkMode');
            if (darkMode === 'true') {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>
</head>
<body>

<!-- ================================================================ -->
<!-- TOP NAVIGATION - WITH SIDEBAR TOGGLE -->
<!-- ================================================================ -->
<nav class="top-nav">
    
    <!-- Left Side -->
    <div class="flex items-center gap-3 flex-1 min-w-0">
        
        <!-- ================================================================ -->
        <!-- SIDEBAR TOGGLE BUTTON - CRITICAL: Must have id="sidebarToggle" -->
        <!-- ================================================================ -->
        <button id="sidebarToggle" class="sidebar-toggle-btn" aria-label="Toggle Sidebar Menu" title="Toggle Sidebar">
            <i class="fas fa-bars"></i>
        </button>
        
        <!-- Dashboard Link -->
        <a href="dashboard.php" class="dashboard-link" title="Go to Dashboard">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
    </div>
    
    <!-- Search Bar -->
    <div class="search-wrapper">
        <i class="fas fa-search text-gray-400 ml-3"></i>
        <input type="text" id="searchInput" placeholder="Search patients..." aria-label="Search" autocomplete="off">
        <button id="searchBtn" class="search-btn" aria-label="Search">
            <i class="fas fa-search mr-1"></i><span>Search</span>
        </button>
    </div>
    
    <!-- Right Side -->
    <div class="flex items-center gap-3 shrink-0">
        
        <!-- Role Badge -->
        <span class="role-badge">
            <i class="fas fa-user mr-1"></i> <?= strtoupper($user_role) ?>
        </span>
        
        <!-- Branch Badge -->
        <span class="branch-badge">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($user_branch_name) ?>
        </span>
        
        <!-- Date & Time -->
        <span class="datetime" id="currentDateTime">
            <span class="date-part" id="datePart"><?= date('D, M d, Y') ?></span>
            <span class="time-part" id="timePart"><?= date('h:i:s A') ?></span>
        </span>
        
        <!-- Dark Mode Toggle -->
        <button id="darkModeToggle" class="dark-toggle-btn" title="Toggle Dark Mode">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <!-- Notifications -->
        <button class="icon-btn" id="notifBtn" title="Notifications">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= $unread_notifications > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <!-- Profile Avatar -->
        <a href="profile.php" title="View Profile">
            <?php if ($profile_pic_exists && !empty($profile_pic)): ?>
                <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar" style="object-fit:cover;">
            <?php else: ?>
                <div class="avatar-avatar">
                    <?= $default_letter ?>
                </div>
            <?php endif; ?>
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- JAVASCRIPT - DARK MODE + DATE/TIME + SIDEBAR TOGGLE -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // SIDEBAR TOGGLE - FULLY WORKING ON MOBILE
    // ================================================================
    (function() {
        // Wait for DOM to be ready
        function initSidebarToggle() {
            var sidebar = document.getElementById('sidebar');
            var sidebarToggle = document.getElementById('sidebarToggle');
            var overlay = document.getElementById('sidebarOverlay');
            
            console.log('🔧 Cashier Header - Sidebar toggle initialization...');
            console.log('📱 Sidebar element:', sidebar);
            console.log('🔘 Toggle button:', sidebarToggle);
            
            // Create overlay if not exists
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.id = 'sidebarOverlay';
                overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.6);z-index:9998;display:none;backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);';
                document.body.appendChild(overlay);
                console.log('✅ Sidebar overlay created');
            }
            
            if (!sidebar) {
                console.error('❌ Sidebar element not found!');
                return;
            }
            
            // Toggle function
            function openSidebar() {
                sidebar.classList.add('open');
                overlay.style.display = 'block';
                document.body.style.overflow = 'hidden';
                console.log('🔓 Sidebar opened');
            }
            
            function closeSidebar() {
                sidebar.classList.remove('open');
                overlay.style.display = 'none';
                document.body.style.overflow = '';
                console.log('🔒 Sidebar closed');
            }
            
            function toggleSidebar() {
                if (sidebar.classList.contains('open')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            }
            
            // ================================================================
            // EVENT: Toggle button (hamburger icon)
            // ================================================================
            if (sidebarToggle) {
                // Remove all existing listeners to avoid duplicates
                var newToggle = sidebarToggle.cloneNode(true);
                sidebarToggle.parentNode.replaceChild(newToggle, sidebarToggle);
                var freshToggle = document.getElementById('sidebarToggle');
                
                freshToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('🔘 Hamburger clicked!');
                    toggleSidebar();
                });
                console.log('✅ Toggle button event attached');
            } else {
                console.warn('⚠️ Toggle button not found - trying fallback');
                // Try to find by class
                var fallbackBtn = document.querySelector('.sidebar-toggle-btn');
                if (fallbackBtn) {
                    fallbackBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        toggleSidebar();
                    });
                    console.log('✅ Fallback toggle button attached');
                }
            }
            
            // ================================================================
            // EVENT: Close sidebar when clicking overlay
            // ================================================================
            if (overlay) {
                overlay.addEventListener('click', function(e) {
                    if (e.target === overlay) {
                        closeSidebar();
                    }
                });
            }
            
            // ================================================================
            // EVENT: Close sidebar with ESC key
            // ================================================================
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && sidebar.classList.contains('open')) {
                    closeSidebar();
                }
            });
            
            // ================================================================
            // EVENT: Auto-close on window resize (desktop)
            // ================================================================
            window.addEventListener('resize', function() {
                if (window.innerWidth > 1024 && sidebar.classList.contains('open')) {
                    closeSidebar();
                }
            });
            
            console.log('✅ Cashier Header - Sidebar toggle fully initialized!');
        }
        
        // Run on DOM ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initSidebarToggle);
        } else {
            initSidebarToggle();
        }
    })();

    // ================================================================
    // DARK MODE - PERSISTENT & FULLY WORKING
    // ================================================================
    (function() {
        var darkModeToggle = document.getElementById('darkModeToggle');
        var darkIcon = document.getElementById('darkIcon');
        var darkText = document.getElementById('darkText');
        var htmlElement = document.documentElement;
        
        // Function to apply dark mode
        function applyDarkMode(isDark) {
            if (isDark) {
                htmlElement.setAttribute('data-theme', 'dark');
                if (darkIcon) darkIcon.className = 'fas fa-sun';
                if (darkText) darkText.textContent = 'Light';
                localStorage.setItem('darkMode', 'true');
                document.cookie = "dark_mode=true; path=/";
            } else {
                htmlElement.removeAttribute('data-theme');
                if (darkIcon) darkIcon.className = 'fas fa-moon';
                if (darkText) darkText.textContent = 'Dark';
                localStorage.setItem('darkMode', 'false');
                document.cookie = "dark_mode=false; path=/";
            }
        }
        
        // Check saved preference
        var savedDarkMode = localStorage.getItem('darkMode');
        if (savedDarkMode === 'true') {
            applyDarkMode(true);
        } else {
            applyDarkMode(false);
        }
        
        // Toggle dark mode on button click
        if (darkModeToggle) {
            darkModeToggle.addEventListener('click', function(e) {
                e.preventDefault();
                var isDark = htmlElement.getAttribute('data-theme') === 'dark';
                applyDarkMode(!isDark);
                
                // Show toast notification
                showToast('Dark Mode', isDark ? 'Switched to Light Mode ☀️' : 'Switched to Dark Mode 🌙', 'info');
            });
        }
        
        // Listen for dark mode changes from other pages
        window.addEventListener('storage', function(e) {
            if (e.key === 'darkMode') {
                var isDark = e.newValue === 'true';
                applyDarkMode(isDark);
            }
        });
    })();

    // ================================================================
    // DATE & TIME - LIVE UPDATE
    // ================================================================
    function updateDateTime() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', 
            month: 'short', 
            day: 'numeric', 
            year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', 
            minute: '2-digit', 
            second: '2-digit', 
            hour12: true
        });
        
        var datePart = document.getElementById('datePart');
        var timePart = document.getElementById('timePart');
        
        if (datePart) {
            datePart.textContent = dateStr;
        }
        if (timePart) {
            timePart.textContent = timeStr;
        }
    }
    
    // Update immediately and every second
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // TOAST FUNCTION
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        if (!toast) {
            // Create toast if not exists
            toast = document.createElement('div');
            toast.id = 'toast';
            toast.className = 'toast-custom';
            toast.innerHTML = `
                <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
                <div>
                    <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
                    <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
                </div>
            `;
            document.body.appendChild(toast);
        }
        
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        
        toast.className = 'toast-custom ' + (type || 'info');
        toastTitle.textContent = title || 'Notification';
        toastMessage.textContent = message || '';
        toast.style.display = 'flex';
        
        toast.classList.add('show');
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() {
                toast.style.display = 'none';
            }, 400);
        }, 3000);
    }

    // ================================================================
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            window.location.href = 'search.php?q=' + encodeURIComponent(query);
        }
    }
    
    if (searchBtn) {
        searchBtn.addEventListener('click', performSearch);
    }
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                performSearch();
            }
        });
        
        // Ctrl+K to focus search
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                searchInput.focus();
                searchInput.select();
            }
            if (e.key === 'Escape' && document.activeElement === searchInput) {
                searchInput.value = '';
                searchInput.blur();
            }
        });
    }

    // ================================================================
    // NOTIFICATIONS
    // ================================================================
    var notifBtn = document.getElementById('notifBtn');
    if (notifBtn) {
        notifBtn.addEventListener('click', function() {
            window.location.href = 'notifications.php';
        });
    }

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        // Ctrl+D = Toggle Dark Mode
        if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
            e.preventDefault();
            var darkBtn = document.getElementById('darkModeToggle');
            if (darkBtn) {
                darkBtn.click();
            }
        }
        // Ctrl+K = Focus Search
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            var searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }
        // Escape = Clear Search
        if (e.key === 'Escape') {
            var searchInput = document.getElementById('searchInput');
            if (searchInput && document.activeElement === searchInput) {
                searchInput.value = '';
                searchInput.blur();
            }
        }
    });

    console.log('%c🟢 Cashier Header - With Sidebar Toggle', 'font-size:16px; font-weight:bold; color:#059669;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c👤 Role: <?= htmlspecialchars($user_role) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($user_branch_name) ?>', 'font-size:13px; color:#6EA8FE;');
    console.log('%c📸 Profile Pic: <?= $profile_pic_exists ? '✅ Uploaded' : '❌ Default' ?>', 'font-size:13px; color:#059669;');
    console.log('%c✅ ALLOWED ROLES: Cashier, Reception, Admin', 'font-size:13px; color:#34D399;');
    console.log('%c🌙 Dark Mode: ' + (localStorage.getItem('darkMode') === 'true' ? '🌙 Dark' : '☀️ Light'), 'font-size:13px; color:#D97706;');
    console.log('%c📅 Date/Time: ' + new Date().toLocaleString(), 'font-size:13px; color:#0B5ED7;');
    console.log('%c📱 Hamburger button: Click ☰ to toggle sidebar', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Sidebar toggle works on all devices!', 'font-size:13px; color:#059669;');
    console.log('%c🔐 Session-based login active', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>