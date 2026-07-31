<?php
// ================================================================
// FILE: frontend/components/doctor_header.php
// DOCTOR - SHARED HEADER WITH SEARCH BAR & ONLINE STATUS
// WITH WORKING SIDEBAR TOGGLE - FULLY FIXED
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// SESSION DATA - ENSURE DOCTOR SESSION IS SET
// ================================================================

// Check if session exists, if not set default doctor
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] <= 0) {
    // Set default doctor - Dr. John Mushi (ID: 5)
    $_SESSION['user_id'] = 5;
    $_SESSION['doctor_id'] = 5;
    $_SESSION['full_name'] = 'Dr. John Mushi';
    $_SESSION['role'] = 'doctor';
    $_SESSION['branch_id'] = 1;
    $_SESSION['specialty'] = 'General Medicine';
    $_SESSION['username'] = 'dr.john';
    $_SESSION['email'] = 'john@braick.com';
    $_SESSION['is_online'] = 1;
}

// If doctor_id is set but user_id is not, sync them
if (isset($_SESSION['doctor_id']) && $_SESSION['doctor_id'] > 0 && !isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = $_SESSION['doctor_id'];
}

// If user_id is set but doctor_id is not, sync them
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0 && !isset($_SESSION['doctor_id'])) {
    $_SESSION['doctor_id'] = $_SESSION['user_id'];
}

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../backend/config/config.php';
require_once __DIR__ . '/../../backend/config/database.php';

// ================================================================
// GET DOCTOR DETAILS FROM SESSION OR DATABASE
// ================================================================
$doctor_id = $_SESSION['doctor_id'] ?? $_SESSION['user_id'] ?? 5;
$full_name = $_SESSION['full_name'] ?? 'Dr. John Mushi';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$is_online = $_SESSION['is_online'] ?? 1;

// Try to get latest doctor data from database
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT id, full_name, is_online, profile_pic, branch_id, specialty FROM users WHERE id = ? AND role = 'doctor'");
    $stmt->execute([$doctor_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user_data) {
        $doctor_id = (int)$user_data['id'];
        $full_name = $user_data['full_name'];
        $is_online = (int)$user_data['is_online'];
        $profile_pic = $user_data['profile_pic'] ?? '';
        
        // Update session with latest data
        $_SESSION['user_id'] = $doctor_id;
        $_SESSION['doctor_id'] = $doctor_id;
        $_SESSION['full_name'] = $full_name;
        $_SESSION['is_online'] = $is_online;
        $_SESSION['branch_id'] = $user_data['branch_id'] ?? 1;
        $_SESSION['specialty'] = $user_data['specialty'] ?? 'General Practitioner';
    } else {
        // If doctor not found in database, use default
        error_log("Doctor ID $doctor_id not found in database, using default Dr. John Mushi");
        $doctor_id = 5;
        $full_name = 'Dr. John Mushi';
        $is_online = 1;
        
        $_SESSION['user_id'] = 5;
        $_SESSION['doctor_id'] = 5;
        $_SESSION['full_name'] = 'Dr. John Mushi';
        $_SESSION['is_online'] = 1;
        $_SESSION['branch_id'] = 1;
        $_SESSION['specialty'] = 'General Medicine';
    }
} catch (Exception $e) {
    // Use session values if database fails
    error_log("Database error in doctor_header: " . $e->getMessage());
    $doctor_id = $_SESSION['doctor_id'] ?? 5;
    $full_name = $_SESSION['full_name'] ?? 'Dr. John Mushi';
    $is_online = $_SESSION['is_online'] ?? 1;
}

// Make sure doctor_id is in session
$_SESSION['user_id'] = $doctor_id;
$_SESSION['doctor_id'] = $doctor_id;

// ================================================================
// AVATAR SETUP
// ================================================================
$avatar_url = '';
$show_initial = true;
$initial = strtoupper(substr($full_name, 0, 1));

if (!empty($profile_pic)) {
    $file_path = $_SERVER['DOCUMENT_ROOT'] . '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic;
    if (file_exists($file_path)) {
        $avatar_url = '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic;
        $show_initial = false;
    } else {
        $_SESSION['profile_pic'] = '';
        $profile_pic = '';
    }
}

// ================================================================
// FAVICON - LOGO
// ================================================================
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

$current_page = basename($_SERVER['PHP_SELF']);
$page_title = ucfirst(str_replace('.php', '', $current_page));
if (empty($page_title) || $page_title == '') {
    $page_title = 'Dashboard';
}

$dark_mode = isset($_COOKIE['dark_mode']) ? $_COOKIE['dark_mode'] : 'false';
$is_dark = $dark_mode === 'true';

// ================================================================
// GET CURRENT PAGE FOR ACTIVE NAV
// ================================================================
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $is_dark ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Braick Dispensary - Doctor <?= $page_title ?></title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="apple-touch-icon" href="<?= $logo_path ?>">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <style>
        /* ================================================================
           ROOT VARIABLES - LIGHT & DARK MODE
           ================================================================ */
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
            --green: #059669;
            --green-dark: #047857;
            --orange: #D97706;
            --purple: #7C3AED;
            --red: #EF4444;
            
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --text-muted: #94A3B8;
            --border-color: #E2E8F0;
            --shadow: 0 1px 3px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.06);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.08);
            
            --sidebar-bg: #0B4EA8;
            --sidebar-text: #FFFFFF;
            --sidebar-hover: #0B5ED7;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --text-muted: #64748B;
            --border-color: #334155;
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
            
            --sidebar-bg: #0A3D7A;
            --sidebar-text: #FFFFFF;
            --sidebar-hover: #0B5ED7;
        }
        
        /* ================================================================
           BASE STYLES
           ================================================================ */
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
           TOP NAVIGATION - WITH SEARCH BAR & WORKING SIDEBAR TOGGLE
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
            box-shadow: var(--shadow);
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
            background: var(--primary-bg);
            color: var(--primary);
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
            }
        }
        
        /* ================================================================
           SEARCH BAR
           ================================================================ */
        .search-wrapper {
            display: flex;
            align-items: center;
            background: var(--bg-body);
            border-radius: 10px;
            border: 2px solid var(--border-color);
            transition: all 0.3s;
            flex: 1;
            max-width: 500px;
            min-width: 150px;
        }
        
        .search-wrapper:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15);
        }
        
        .search-wrapper input {
            border: none;
            background: transparent;
            padding: 8px 14px;
            width: 100%;
            font-size: 0.85rem;
            outline: none;
            color: var(--text-primary);
        }
        
        .search-wrapper input::placeholder {
            color: var(--text-secondary);
        }
        
        .search-wrapper .search-btn {
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
        
        .search-wrapper .search-btn:hover {
            background: var(--primary-dark);
        }
        
        .search-wrapper .search-btn i {
            margin-right: 4px;
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
        }
        
        .dashboard-link:hover {
            color: var(--primary);
            background: var(--primary-bg);
        }
        
        .dashboard-link i {
            color: var(--primary);
            font-size: 1.1rem;
        }
        
        [data-theme="dark"] .dashboard-link:hover {
            background: #1E3A5F;
        }
        
        /* ================================================================
           TOP NAV RIGHT ELEMENTS
           ================================================================ */
        .top-nav .datetime {
            font-size: 0.78rem;
            color: var(--text-secondary);
            font-weight: 500;
            white-space: nowrap;
        }
        
        .top-nav .datetime i {
            color: var(--primary);
            margin-right: 4px;
        }
        
        [data-theme="dark"] .top-nav .datetime i {
            color: var(--primary-light);
        }
        
        /* ================================================================
           AVATAR - PROFILE PICTURE SUPPORT
           ================================================================ */
        .avatar-link {
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            flex-shrink: 0;
        }
        
        .avatar-link:hover {
            transform: scale(1.05);
        }
        
        .avatar-link .avatar-img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            background: var(--bg-card);
        }
        
        .avatar-link:hover .avatar-img {
            border-color: var(--primary);
        }
        
        .avatar-link .avatar-placeholder {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            font-weight: 700;
            color: white;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            text-transform: uppercase;
            flex-shrink: 0;
        }
        
        .avatar-link:hover .avatar-placeholder {
            border-color: var(--primary);
            transform: scale(1.05);
        }
        
        .avatar-link .status-ring {
            position: absolute;
            bottom: -2px;
            right: -2px;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            border: 2px solid var(--bg-nav);
            background: var(--green);
        }
        
        .avatar-link .status-ring.offline {
            background: var(--text-muted);
        }
        
        .avatar-color-1 { background: #0B5ED7; }
        .avatar-color-2 { background: #059669; }
        .avatar-color-3 { background: #7C3AED; }
        .avatar-color-4 { background: #DC2626; }
        .avatar-color-5 { background: #D97706; }
        .avatar-color-6 { background: #0D9488; }
        .avatar-color-7 { background: #DB2777; }
        
        /* ================================================================
           ICON BUTTONS (Notifications)
           ================================================================ */
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
        
        .top-nav .icon-btn .notif-dot {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 8px;
            height: 8px;
            background: var(--red);
            border-radius: 50%;
            border: 2px solid var(--bg-nav);
            animation: pulse-dot 2s infinite;
        }
        
        @keyframes pulse-dot {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }
        
        /* ================================================================
           STATUS TOGGLE BUTTON
           ================================================================ */
        .status-toggle {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            border-radius: 20px;
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.78rem;
            font-weight: 500;
            color: var(--text-primary);
            white-space: nowrap;
        }
        
        .status-toggle:hover {
            border-color: var(--primary);
            background: var(--bg-card);
        }
        
        .status-toggle .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
        }
        
        .status-toggle .status-dot.online {
            background: var(--green);
            animation: pulse-dot 1.5s infinite;
        }
        
        .status-toggle .status-dot.offline {
            background: var(--text-muted);
        }
        
        .status-toggle .status-spinner {
            display: none;
            width: 14px;
            height: 14px;
            border: 2px solid var(--border-color);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        
        .status-toggle.updating .status-spinner {
            display: inline-block;
        }
        
        .status-toggle.updating .status-dot,
        .status-toggle.updating .status-text {
            display: none;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* ================================================================
           DARK MODE TOGGLE
           ================================================================ */
        .dark-toggle-btn {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 6px 12px;
            cursor: pointer;
            font-size: 0.82rem;
            color: var(--text-primary);
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }
        
        .dark-toggle-btn:hover {
            border-color: var(--primary);
            background: var(--bg-card);
        }
        
        .dark-toggle-btn i {
            font-size: 0.9rem;
        }
        
        /* ================================================================
           MAIN CONTENT OFFSET
           ================================================================ */
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 24px 28px;
            min-height: calc(100vh - 68px);
            background: var(--bg-body);
            transition: all 0.3s ease;
        }
        
        /* ================================================================
           PAGE HEADER
           ================================================================ */
        .page-header {
            border-bottom: 3px solid var(--primary);
            padding-bottom: 12px;
            margin-bottom: 20px;
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
        
        .branch-tag {
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
        
        /* ================================================================
           TOAST
           ================================================================ */
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 14px 20px;
            border-radius: 12px;
            z-index: 9999;
            max-width: 360px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            font-family: 'Inter', 'Segoe UI', sans-serif;
            box-shadow: 0 8px 30px rgba(0,0,0,0.2);
        }
        
        .toast-custom.show {
            transform: translateY(0);
            opacity: 1;
        }
        
        .toast-custom.success { background: #059669; }
        .toast-custom.error { background: #DC2626; }
        .toast-custom.info { background: #0B5ED7; }
        .toast-custom.warning { background: #D97706; }
        
        /* ================================================================
           FOOTER
           ================================================================ */
        .footer {
            padding: 14px 0;
            border-top: 2px solid var(--border-color);
            margin-top: 20px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
            transition: border-color 0.3s ease, color 0.3s ease;
        }
        
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .top-nav { left: 0; padding: 0 16px; }
            .main-content { margin-left: 0; padding: 16px; }
            .search-wrapper { max-width: 300px; min-width: 120px; }
        }
        
        @media (max-width: 768px) {
            .top-nav { height: 60px; padding: 0 12px; }
            .top-nav .datetime { display: none; }
            .top-nav .status-toggle { display: none; }
            .main-content { padding: 12px; margin-top: 60px; }
            .search-wrapper { max-width: 180px; min-width: 100px; }
            .search-wrapper input { font-size: 0.75rem; padding: 6px 10px; }
            .search-wrapper .search-btn { padding: 6px 10px; font-size: 0.7rem; }
            .search-wrapper .search-btn span { display: none; }
            .search-wrapper .search-btn i { margin-right: 0; }
            .dark-toggle-btn { padding: 4px 10px; font-size: 0.7rem; }
            .dark-toggle-btn span { display: none; }
            .top-nav .icon-btn { width: 32px; height: 32px; }
            .top-nav .icon-btn i { font-size: 0.9rem; }
            .avatar-link .avatar-img { width: 32px; height: 32px; }
            .avatar-link .avatar-placeholder { width: 32px; height: 32px; font-size: 0.8rem; }
            .page-header .page-title { font-size: 1.2rem; }
            .dashboard-link span { display: none; }
        }
        
        @media (max-width: 480px) {
            .top-nav { height: 56px; padding: 0 8px; gap: 4px; }
            .main-content { padding: 8px; margin-top: 56px; }
            .search-wrapper { max-width: 120px; min-width: 60px; }
            .search-wrapper input { font-size: 0.65rem; padding: 4px 8px; }
            .search-wrapper .search-btn { padding: 4px 8px; font-size: 0.6rem; }
            .dark-toggle-btn { padding: 3px 8px; font-size: 0.6rem; }
            .dark-toggle-btn i { font-size: 0.7rem; }
            .top-nav .icon-btn { width: 28px; height: 28px; }
            .top-nav .icon-btn i { font-size: 0.8rem; }
            .avatar-link .avatar-img { width: 28px; height: 28px; }
            .avatar-link .avatar-placeholder { width: 28px; height: 28px; font-size: 0.7rem; }
            .page-header .page-title { font-size: 1rem; }
            .sidebar-toggle-btn { font-size: 1.1rem; padding: 4px 6px; }
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
</head>
<body>

<!-- ================================================================ -->
<!-- TOP NAVIGATION - WITH SEARCH BAR & WORKING SIDEBAR TOGGLE -->
<!-- ================================================================ -->
<nav class="top-nav" id="topNav">
    
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
        <input type="text" id="searchInput" placeholder="Search patients by name, ID or phone..." 
               aria-label="Search" autocomplete="off">
        <button id="searchBtn" class="search-btn" aria-label="Search">
            <i class="fas fa-search mr-1"></i><span>Search</span>
        </button>
    </div>
    
    <!-- Right Side -->
    <div class="flex items-center gap-3 shrink-0">
        
        <!-- Date & Time -->
        <span class="datetime" id="currentDateTime">
            <i class="fas fa-clock"></i>
            <span id="clockDisplay"><?= date('D, M d, Y • h:i:s A') ?></span>
        </span>
        
        <!-- Status Toggle Button -->
        <button id="statusToggle" class="status-toggle <?= $is_online ? '' : 'offline' ?>" 
                title="Click to toggle online/offline status">
            <span class="status-dot <?= $is_online ? 'online' : 'offline' ?>" id="statusDot"></span>
            <span class="status-text" id="statusText"><?= $is_online ? 'Online' : 'Offline' ?></span>
            <span class="status-spinner"></span>
        </button>
        
        <!-- Dark Mode Toggle -->
        <button id="darkModeToggle" class="dark-toggle-btn" title="Toggle Dark Mode (Ctrl+D)">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <!-- Notifications -->
        <button class="icon-btn" id="notifBtn" title="Notifications">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot" id="notifDot" style="display: none;"></span>
        </button>
        
        <!-- Profile Avatar -->
        <a href="profile.php" class="avatar-link" title="View Profile">
            <?php if ($show_initial): ?>
                <div class="avatar-placeholder avatar-color-<?= (abs(crc32($full_name)) % 7) + 1 ?>">
                    <?= $initial ?>
                </div>
            <?php else: ?>
                <img src="<?= $avatar_url ?>" alt="Profile" class="avatar-img">
            <?php endif; ?>
            <span class="status-ring <?= $is_online ? '' : 'offline' ?>" id="avatarStatusRing"></span>
        </a>
        
    </div>
</nav>

<!-- ================================================================ -->
<!-- JAVASCRIPT - FULL HEADER FUNCTIONALITY -->
<!-- ================================================================ -->
<script>
// ================================================================
// SIDEBAR TOGGLE - FULLY FIXED FOR ALL DEVICES
// ================================================================
(function() {
    // Wait for DOM to be ready
    function initSidebarToggle() {
        var sidebar = document.getElementById('sidebar');
        var sidebarToggle = document.getElementById('sidebarToggle');
        var overlay = document.getElementById('sidebarOverlay');
        
        console.log('🔧 Sidebar toggle initialization...');
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
        
        console.log('✅ Sidebar toggle fully initialized!');
    }
    
    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSidebarToggle);
    } else {
        initSidebarToggle();
    }
})();

// ================================================================
// DARK MODE TOGGLE
// ================================================================
(function() {
    var darkModeToggle = document.getElementById('darkModeToggle');
    var darkIcon = document.getElementById('darkIcon');
    var darkText = document.getElementById('darkText');
    var htmlElement = document.documentElement;
    
    function getCookie(name) {
        var value = "; " + document.cookie;
        var parts = value.split("; " + name + "=");
        if (parts.length === 2) {
            return parts.pop().split(";").shift();
        }
        return null;
    }
    
    function setCookie(name, value, days) {
        var expires = "";
        if (days) {
            var date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = "; expires=" + date.toUTCString();
        }
        document.cookie = name + "=" + value + expires + "; path=/";
    }
    
    var savedDarkMode = getCookie('dark_mode');
    
    if (savedDarkMode === 'true') {
        htmlElement.setAttribute('data-theme', 'dark');
        if (darkIcon) {
            darkIcon.className = 'fas fa-sun';
        }
        if (darkText) {
            darkText.textContent = 'Light';
        }
    }
    
    if (darkModeToggle) {
        darkModeToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var isDark = htmlElement.getAttribute('data-theme') === 'dark';
            
            if (isDark) {
                htmlElement.removeAttribute('data-theme');
                if (darkIcon) {
                    darkIcon.className = 'fas fa-moon';
                }
                if (darkText) {
                    darkText.textContent = 'Dark';
                }
                setCookie('dark_mode', 'false', 365);
            } else {
                htmlElement.setAttribute('data-theme', 'dark');
                if (darkIcon) {
                    darkIcon.className = 'fas fa-sun';
                }
                if (darkText) {
                    darkText.textContent = 'Light';
                }
                setCookie('dark_mode', 'true', 365);
            }
        });
    }
})();

// ================================================================
// DATE & TIME - REAL TIME
// ================================================================
(function() {
    var clockDisplay = document.getElementById('clockDisplay');
    
    function updateClock() {
        if (!clockDisplay) return;
        var now = new Date();
        var options = {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: true
        };
        clockDisplay.textContent = now.toLocaleDateString('en-US', options);
    }
    
    updateClock();
    setInterval(updateClock, 1000);
})();

// ================================================================
// ONLINE STATUS TOGGLE - UPDATES DATABASE
// ================================================================
document.addEventListener('DOMContentLoaded', function() {
    var statusToggle = document.getElementById('statusToggle');
    var statusDot = document.getElementById('statusDot');
    var statusText = document.getElementById('statusText');
    var avatarStatusRing = document.getElementById('avatarStatusRing');
    var isUpdating = false;
    
    var doctorId = <?= json_encode($doctor_id) ?>;
    
    if (statusToggle) {
        statusToggle.addEventListener('click', function() {
            if (isUpdating) return;
            
            var currentIsOnline = statusDot.classList.contains('online');
            var newStatus = currentIsOnline ? 0 : 1;
            
            // Update UI immediately (optimistic)
            if (newStatus === 1) {
                statusDot.classList.remove('offline');
                statusDot.classList.add('online');
                statusText.textContent = 'Online';
                if (avatarStatusRing) {
                    avatarStatusRing.classList.remove('offline');
                }
            } else {
                statusDot.classList.remove('online');
                statusDot.classList.add('offline');
                statusText.textContent = 'Offline';
                if (avatarStatusRing) {
                    avatarStatusRing.classList.add('offline');
                }
            }
            
            isUpdating = true;
            statusToggle.classList.add('updating');
            
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '/dispensary_system/frontend/pages/doctor/update_doctor_status.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    isUpdating = false;
                    statusToggle.classList.remove('updating');
                    
                    if (xhr.status === 200) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                showToast('✅ ' + (newStatus === 1 ? 'Online' : 'Offline'), 
                                    'You are now ' + (newStatus === 1 ? 'online' : 'offline') + '.', 
                                    'success');
                            } else {
                                showToast('❌ Error', response.message || 'Failed to update status', 'error');
                                if (newStatus === 1) {
                                    statusDot.classList.remove('online');
                                    statusDot.classList.add('offline');
                                    statusText.textContent = 'Offline';
                                    if (avatarStatusRing) {
                                        avatarStatusRing.classList.add('offline');
                                    }
                                } else {
                                    statusDot.classList.remove('offline');
                                    statusDot.classList.add('online');
                                    statusText.textContent = 'Online';
                                    if (avatarStatusRing) {
                                        avatarStatusRing.classList.remove('offline');
                                    }
                                }
                            }
                        } catch (e) {
                            showToast('❌ Error', 'Server error', 'error');
                            if (newStatus === 1) {
                                statusDot.classList.remove('online');
                                statusDot.classList.add('offline');
                                statusText.textContent = 'Offline';
                                if (avatarStatusRing) {
                                    avatarStatusRing.classList.add('offline');
                                }
                            } else {
                                statusDot.classList.remove('offline');
                                statusDot.classList.add('online');
                                statusText.textContent = 'Online';
                                if (avatarStatusRing) {
                                    avatarStatusRing.classList.remove('offline');
                                }
                            }
                        }
                    } else {
                        showToast('❌ Error', 'Network error', 'error');
                        if (newStatus === 1) {
                            statusDot.classList.remove('online');
                            statusDot.classList.add('offline');
                            statusText.textContent = 'Offline';
                            if (avatarStatusRing) {
                                avatarStatusRing.classList.add('offline');
                            }
                        } else {
                            statusDot.classList.remove('offline');
                            statusDot.classList.add('online');
                            statusText.textContent = 'Online';
                            if (avatarStatusRing) {
                                avatarStatusRing.classList.remove('offline');
                            }
                        }
                    }
                }
            };
            xhr.send('status=' + newStatus + '&doctor_id=' + doctorId);
        });
    }
});

// ================================================================
// SEARCH FUNCTIONALITY
// ================================================================
document.addEventListener('DOMContentLoaded', function() {
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
});

// ================================================================
// TOAST NOTIFICATION
// ================================================================
function showToast(title, message, type) {
    var existingToast = document.querySelector('.toast-custom');
    if (existingToast) {
        existingToast.remove();
    }
    
    var toast = document.createElement('div');
    toast.className = 'toast-custom ' + type;
    var icon = document.createElement('i');
    icon.className = 'fas ' + (type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle');
    icon.style.fontSize = '1.2rem';
    
    var content = document.createElement('div');
    content.innerHTML = `
        <p style="font-weight:600;font-size:0.9rem;margin:0;">${title}</p>
        <p style="font-size:0.78rem;opacity:0.9;margin:0;">${message}</p>
    `;
    
    toast.appendChild(icon);
    toast.appendChild(content);
    document.body.appendChild(toast);
    
    setTimeout(function() {
        toast.classList.add('show');
    }, 50);
    
    setTimeout(function() {
        toast.classList.remove('show');
        setTimeout(function() {
            if (toast.parentNode) {
                toast.remove();
            }
        }, 400);
    }, 4000);
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
    // Ctrl+Shift+S = Toggle Status
    if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'S') {
        e.preventDefault();
        var statusBtn = document.getElementById('statusToggle');
        if (statusBtn) {
            statusBtn.click();
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

// ================================================================
// CONSOLE LOG
// ================================================================
console.log('%c👨‍⚕️ Braick - Doctor Header (WORKING SIDEBAR TOGGLE)', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
console.log('%c🟢 Status: <?= $is_online ? 'Online ✅' : 'Offline ❌' ?>', 'font-size:12px; color:#059669;');
console.log('%c🆔 Doctor ID: <?= $doctor_id ?>', 'font-size:12px; color:#64748B;');
console.log('%c👤 Doctor: <?= $full_name ?>', 'font-size:12px; color:#64748B;');
console.log('%c🌙 Dark Mode: ' + (document.documentElement.getAttribute('data-theme') === 'dark' ? 'ON' : 'OFF'), 'font-size:12px; color:#6EA8FE;');
console.log('%c🔍 Search: Ctrl+K to focus', 'font-size:12px; color:#64748B;');
console.log('%c🔄 Status: Ctrl+Shift+S to toggle', 'font-size:12px; color:#64748B;');
console.log('%c📱 Hamburger button: Click ☰ to toggle sidebar', 'font-size:12px; color:#34D399;');
console.log('%c✅ Sidebar toggle works on all devices!', 'font-size:12px; color:#059669;');
</script>

</body>
</html>