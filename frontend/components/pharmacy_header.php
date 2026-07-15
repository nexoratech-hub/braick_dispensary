<?php
// ================================================================
// FILE: frontend/components/pharmacy_header.php
// PHARMACY - SHARED HEADER (DARK MODE FIXED)
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// INCLUDE CONFIG
// ================================================================
require_once __DIR__ . '/../../backend/config/config.php';

// ================================================================
// SESSION - Default to pharm.peter (Peter Ngalula)
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pharmacy') {
    $_SESSION['user_id'] = 5;
    $_SESSION['full_name'] = 'Peter Ngalula';
    $_SESSION['role'] = 'pharmacy';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'pharm.peter';
    $_SESSION['is_admin'] = false;
    $_SESSION['profile_pic'] = '';
}

$user_id = $_SESSION['user_id'] ?? 5;
$user_full_name = $_SESSION['full_name'] ?? 'Peter Ngalula';
$user_role = $_SESSION['role'] ?? 'pharmacy';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';

// ================================================================
// GET DATABASE CONNECTION - KUTOKA config.php
// ================================================================
$db = getDB();

// ================================================================
// GET UNREAD NOTIFICATIONS COUNT
// ================================================================
$unread_notifications = 0;
$notifications_list = [];

try {
    // Get count of unread notifications
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    $unread_notifications = $result['total'] ?? 0;
    
    // Get latest 5 notifications
    $stmt = $db->prepare("
        SELECT id, title, message, type, link, is_read, created_at 
        FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $notifications_list = $stmt->fetchAll();
    
} catch (Exception $e) {
    $unread_notifications = 0;
    $notifications_list = [];
}

// ================================================================
// PROFILE PICTURE
// ================================================================
$profile_pic = $_SESSION['profile_pic'] ?? '';
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
// DARK MODE - SESSION BASED (instead of cookie)
// ================================================================
if (!isset($_SESSION['dark_mode'])) {
    $_SESSION['dark_mode'] = 'light';
}

// Check if toggle was clicked
if (isset($_GET['toggle_dark'])) {
    $_SESSION['dark_mode'] = ($_SESSION['dark_mode'] === 'dark') ? 'light' : 'dark';
    // Redirect back to the same page without the query string
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

$dark_mode = $_SESSION['dark_mode'];
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $dark_mode ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Braick Dispensary - Pharmacy <?= $page_title ?></title>
    
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
            
            --prescription: #0B5ED7;
            --prescription-bg: #E8F0FE;
            --otc: #059669;
            --otc-bg: #D1FAE5;
            
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
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
        
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
        
        /* ================================================================
           NOTIFICATION BELL WITH DROPDOWN
           ================================================================ */
        .notif-bell-wrapper {
            position: relative;
        }
        
        .notif-dot {
            position: absolute;
            top: -2px;
            right: -2px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            font-size: 0.55rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--bg-nav);
            animation: pulse-dot 2s infinite;
        }
        
        .notif-dot.has-notif {
            background: var(--danger);
            color: white;
        }
        
        .notif-dot.no-notif {
            background: var(--gray-400);
            color: white;
            animation: none;
        }
        
        @keyframes pulse-dot {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        /* Notification Dropdown */
        .notif-dropdown {
            position: absolute;
            top: 50px;
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
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .notif-dropdown .notif-header {
            padding: 12px 16px;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--bg-body);
        }
        
        .notif-dropdown .notif-header .notif-title {
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--text-primary);
        }
        
        .notif-dropdown .notif-header .notif-mark-all {
            font-size: 0.7rem;
            color: var(--primary);
            cursor: pointer;
            text-decoration: none;
            font-weight: 500;
        }
        
        .notif-dropdown .notif-header .notif-mark-all:hover {
            text-decoration: underline;
        }
        
        .notif-dropdown .notif-list {
            max-height: 320px;
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
            padding: 10px 16px;
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
            font-size: 0.8rem;
            color: var(--text-primary);
        }
        
        .notif-dropdown .notif-item .notif-item-message {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 2px;
            line-height: 1.4;
        }
        
        .notif-dropdown .notif-item .notif-item-time {
            font-size: 0.6rem;
            color: var(--text-secondary);
            margin-top: 4px;
            display: block;
        }
        
        .notif-dropdown .notif-item.unread {
            border-left: 3px solid var(--primary);
            background: var(--primary-bg);
        }
        
        .notif-dropdown .notif-item.unread:hover {
            background: #D1FAE5;
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
            font-size: 0.85rem;
        }
        
        .notif-dropdown .notif-footer {
            padding: 10px 16px;
            border-top: 2px solid var(--border-color);
            text-align: center;
            background: var(--bg-body);
        }
        
        .notif-dropdown .notif-footer a {
            font-size: 0.75rem;
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
        
        .role-badge {
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            background: var(--primary-bg);
            color: var(--primary);
            text-transform: uppercase;
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
        }
        
        [data-theme="dark"] .branch-badge {
            background: #1A3A2A;
            color: #34D399;
        }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 24px 28px;
            min-height: calc(100vh - 68px);
            transition: background 0.3s ease;
        }
        
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
        
        .stat-card.blue { background: var(--primary); }
        .stat-card.blue-dark { background: var(--primary-dark); }
        .stat-card.green { background: var(--success); }
        .stat-card.green-dark { background: var(--success-dark); }
        .stat-card.purple { background: #7C3AED; }
        .stat-card.orange { background: #D97706; }
        .stat-card.red { background: var(--danger); }
        .stat-card.teal { background: #0D9488; }
        .stat-card.pink { background: #DB2777; }
        
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
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.08);
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
        
        .card-title .title-blue { color: var(--primary); }
        .card-title .title-green { color: var(--success); }
        .card-title .title-purple { color: #7C3AED; }
        .card-title .title-orange { color: #D97706; }
        .card-title .title-pink { color: #DB2777; }
        
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
        
        .btn-blue {
            background: var(--primary);
            color: white;
        }
        .btn-blue:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
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
        
        .btn-purple {
            background: #7C3AED;
            color: white;
        }
        .btn-purple:hover {
            background: #6D28D9;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(124, 58, 237, 0.3);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        .btn-outline:hover {
            background: var(--bg-body);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .btn-sm { padding: 3px 10px; font-size: 0.7rem; border-radius: 6px; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-danger:hover { background: var(--danger-dark); transform: translateY(-2px); }
        .btn-warning { background: #D97706; color: white; }
        .btn-warning:hover { background: #B45309; transform: translateY(-2px); }
        
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
        
        .badge-blue { background: var(--primary); }
        .badge-green { background: var(--success); }
        .badge-gray { background: var(--gray-500); }
        .badge-yellow { background: #D97706; }
        .badge-red { background: var(--danger); }
        .badge-purple { background: #7C3AED; }
        .badge-pink { background: #DB2777; }
        
        .badge-prescription { background: #0B5ED7; color: white; }
        .badge-otc { background: #059669; color: white; }
        .badge-pending { background: #D97706; color: white; }
        .badge-dispensed { background: #059669; color: white; }
        .badge-cancelled { background: #DC2626; color: white; }
        .badge-paid { background: #059669; color: white; }
        .badge-partial { background: #D97706; color: white; }
        
        .page-header {
            border-bottom: 3px solid var(--primary);
            padding-bottom: 12px;
        }
        
        .page-header .page-title {
            color: var(--primary-dark);
            font-size: 1.8rem;
            font-weight: 700;
        }
        
        [data-theme="dark"] .page-header .page-title {
            color: var(--primary-light);
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
            background: var(--primary);
            border-bottom: 3px solid var(--primary-dark);
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
            background: #D1FAE5;
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
        
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
            .notif-dropdown { right: -20px; width: 320px; }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .notif-dropdown { right: -30px; width: 300px; }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .stat-card .stat-number { font-size: 1.4rem; }
            .notif-dropdown { right: -40px; width: 280px; }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.4s ease forwards;
            opacity: 0;
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
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <input type="text" id="searchInput" placeholder="Search prescriptions...">
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
        
        <!-- ================================================================ -->
        <!-- DARK MODE TOGGLE - FIXED: Using link instead of button -->
        <!-- ================================================================ -->
        <a href="?toggle_dark=1" class="dark-toggle-btn" id="darkModeLink">
            <i id="darkIcon" class="fas <?= $dark_mode === 'dark' ? 'fa-sun' : 'fa-moon' ?>"></i>
            <span id="darkText"><?= $dark_mode === 'dark' ? 'Light' : 'Dark' ?></span>
        </a>
        
        <!-- Notification Bell -->
        <div class="notif-bell-wrapper">
            <button class="icon-btn" id="notifBellBtn" onclick="toggleNotifications()">
                <i class="fas fa-bell text-lg"></i>
                <span class="notif-dot <?= $unread_notifications > 0 ? 'has-notif' : 'no-notif' ?>">
                    <?= $unread_notifications > 0 ? $unread_notifications : '' ?>
                </span>
            </button>
            
            <!-- Notification Dropdown -->
            <div class="notif-dropdown" id="notifDropdown">
                <div class="notif-header">
                    <span class="notif-title">
                        <i class="fas fa-bell mr-1"></i> Notifications
                        <?php if ($unread_notifications > 0): ?>
                            <span class="badge badge-red" style="font-size:0.6rem; padding:1px 8px;">
                                <?= $unread_notifications ?> new
                            </span>
                        <?php endif; ?>
                    </span>
                    <?php if ($unread_notifications > 0): ?>
                        <a href="#" class="notif-mark-all" onclick="markAllRead(event)">Mark all as read</a>
                    <?php endif; ?>
                </div>
                
                <div class="notif-list">
                    <?php if (count($notifications_list) > 0): ?>
                        <?php foreach ($notifications_list as $notif): ?>
                            <a href="<?= !empty($notif['link']) ? $notif['link'] : '#' ?>" 
                               class="notif-item <?= $notif['is_read'] == 0 ? 'unread' : '' ?>"
                               onclick="markNotificationRead(<?= $notif['id'] ?>, event)">
                                <div class="notif-item-title">
                                    <?= htmlspecialchars($notif['title']) ?>
                                    <?php if ($notif['is_read'] == 0): ?>
                                        <span class="badge badge-blue" style="font-size:0.5rem; padding:0 6px;">New</span>
                                    <?php endif; ?>
                                </div>
                                <div class="notif-item-message"><?= htmlspecialchars($notif['message']) ?></div>
                                <span class="notif-item-time">
                                    <i class="far fa-clock mr-1"></i>
                                    <?php 
                                        // Simple time ago function
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
                            <p style="font-size:0.7rem; color:var(--text-secondary);">All caught up!</p>
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
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($user_full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- JAVASCRIPT - All functionality -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // TOGGLE NOTIFICATION DROPDOWN
    // ================================================================
    function toggleNotifications() {
        var dropdown = document.getElementById('notifDropdown');
        if (dropdown) {
            dropdown.classList.toggle('open');
        }
    }

    // Close dropdown when clicking outside
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

    // ================================================================
    // MARK ALL NOTIFICATIONS AS READ
    // ================================================================
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
    // SEARCH FUNCTIONALITY
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
        var el = document.getElementById('currentDateTime');
        if (el) {
            el.textContent = dateStr + ' • ' + timeStr;
        }
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // SIDEBAR TOGGLE (Mobile)
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        var sidebar = document.getElementById('sidebar');
        var sidebarToggle = document.getElementById('sidebarToggle');
        
        if (sidebarToggle && sidebar) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('open');
            });
        }
        
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 1024) {
                if (sidebar && sidebarToggle) {
                    if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                        sidebar.classList.remove('open');
                    }
                }
            }
        });
    });

    console.log('%c💊 Braick Dispensary - Pharmacy Header (DARK MODE FIXED)', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:12px; color:#059669;');
    console.log('%c🌙 Dark Mode: <?= $dark_mode ?>', 'font-size:12px; color:#D97706;');
    console.log('%c🔔 Unread Notifications: <?= $unread_notifications ?>', 'font-size:12px; color:#D97706;');
    console.log('%c✅ Dark mode toggles via page reload (Session based)', 'font-size:12px; color:#34D399;');
</script>
</body>
</html>