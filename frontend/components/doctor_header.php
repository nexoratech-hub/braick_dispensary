<?php
// ================================================================
// FILE: frontend/components/doctor_header.php
// DOCTOR - SHARED HEADER WITH SEARCH BAR & SEARCH BUTTON
// WITHOUT NOTIFICATIONS - CLEAN AND FOCUSED
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
// CHECK IF USER HAS ACCESS (Doctor only)
// ================================================================
if ($_SESSION['role'] !== 'doctor') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'admin': header('Location: /dispensary_system/frontend/pages/admin/dashboard.php'); break;
        case 'pharmacy': header('Location: /dispensary_system/frontend/pages/pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: /dispensary_system/frontend/pages/laboratory/dashboard.php'); break;
        case 'cashier': header('Location: /dispensary_system/frontend/pages/cashier/dashboard.php'); break;
        case 'reception': header('Location: /dispensary_system/frontend/pages/reception/dashboard.php'); break;
        default: header('Location: /dispensary_system/frontend/pages/login.php'); break;
    }
    exit;
}

// ================================================================
// GET USER DATA FROM SESSION
// ================================================================
$doctor_id = $_SESSION['user_id'] ?? 0;
$full_name = $_SESSION['full_name'] ?? 'Doctor';
$user_role = $_SESSION['role'] ?? 'doctor';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$is_online = $_SESSION['is_online'] ?? 0;

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
// GET DOCTOR DETAILS FROM DATABASE (REFRESH SESSION DATA)
// ================================================================
if ($db !== null && $doctor_id > 0) {
    try {
        $stmt = $db->prepare("SELECT id, full_name, is_online, profile_pic, branch_id, specialty FROM users WHERE id = ? AND role = 'doctor' AND status = 'active'");
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
        }
    } catch (Exception $e) {
        // Use session values if database fails
        error_log("Database error in doctor_header: " . $e->getMessage());
    }
}

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
// DARK MODE - COOKIE BASED
// ================================================================
$dark_mode_cookie = isset($_COOKIE['dark_mode']) ? $_COOKIE['dark_mode'] : 'false';
$is_dark = $dark_mode_cookie === 'true';
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
           TOP NAVIGATION - WITH SEARCH BAR
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
            padding: 0 20px;
            border-bottom: 2px solid var(--border-color);
            transition: all 0.3s ease;
            gap: 12px;
        }
        
        /* ================================================================
           SEARCH BAR WITH SEARCH BUTTON
           ================================================================ */
        .search-wrapper {
            display: flex;
            align-items: center;
            background: var(--bg-body);
            border-radius: 10px;
            border: 2px solid var(--border-color);
            transition: all 0.3s;
            flex: 1;
            max-width: 480px;
            min-width: 160px;
            overflow: hidden;
        }
        
        .search-wrapper:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15);
        }
        
        .search-wrapper .search-icon {
            padding: 0 10px 0 14px;
            color: var(--text-muted);
            font-size: 0.85rem;
            flex-shrink: 0;
        }
        
        .search-wrapper input {
            border: none;
            background: transparent;
            padding: 8px 0;
            width: 100%;
            font-size: 0.85rem;
            outline: none;
            color: var(--text-primary);
            min-width: 60px;
        }
        
        .search-wrapper input::placeholder {
            color: var(--text-secondary);
        }
        
        .search-wrapper .search-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 8px 18px;
            cursor: pointer;
            font-size: 0.82rem;
            font-weight: 500;
            transition: all 0.3s;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 6px;
            border-radius: 0;
            flex-shrink: 0;
        }
        
        .search-wrapper .search-btn:hover {
            background: var(--primary-dark);
        }
        
        .search-wrapper .search-btn:active {
            transform: scale(0.97);
        }
        
        .search-wrapper .search-btn i {
            font-size: 0.85rem;
        }
        
        .search-wrapper .search-btn .btn-text {
            display: inline;
        }
        
        .search-wrapper .search-clear {
            display: none;
            background: transparent;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 4px 8px;
            font-size: 0.8rem;
            flex-shrink: 0;
            transition: color 0.3s;
        }
        
        .search-wrapper .search-clear:hover {
            color: var(--red);
        }
        
        .search-wrapper .search-clear.visible {
            display: block;
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
        
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.8); }
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
            text-decoration: none;
        }
        
        .dark-toggle-btn:hover {
            border-color: var(--primary);
            background: var(--bg-card);
        }
        
        .dark-toggle-btn i {
            font-size: 0.9rem;
        }
        
        .sidebar-toggle-btn {
            display: none;
            background: transparent;
            border: none;
            color: var(--text-primary);
            font-size: 1.3rem;
            cursor: pointer;
            padding: 4px 8px;
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
            .top-nav { left: 0; }
            .sidebar-toggle-btn { display: block; }
            .main-content { margin-left: 0; }
            .search-wrapper { max-width: 320px; }
        }
        
        @media (max-width: 768px) {
            .top-nav .datetime { display: none; }
            .top-nav .status-toggle { display: none; }
            .main-content { padding: 16px; }
            .search-wrapper { max-width: 220px; }
            .search-wrapper .search-btn { padding: 6px 12px; font-size: 0.7rem; }
        }
        
        @media (max-width: 640px) {
            .top-nav { padding: 0 12px; gap: 8px; }
            .search-wrapper { max-width: 150px; min-width: 90px; }
            .search-wrapper .search-icon { padding: 0 6px 0 10px; font-size: 0.7rem; }
            .search-wrapper input { font-size: 0.75rem; padding: 6px 0; }
            .search-wrapper .search-btn { padding: 5px 10px; font-size: 0.65rem; }
            .search-wrapper .search-btn .btn-text { display: none; }
            .search-wrapper .search-btn i { font-size: 0.7rem; }
            .dark-toggle-btn { padding: 4px 8px; font-size: 0.7rem; }
            .dark-toggle-btn span { display: none; }
            .main-content { padding: 10px; }
            .page-header .page-title { font-size: 1.2rem; }
            .avatar-link .avatar-img { width: 32px; height: 32px; }
            .avatar-link .avatar-placeholder { width: 32px; height: 32px; font-size: 0.8rem; }
        }
        
        @media (max-width: 480px) {
            .search-wrapper { max-width: 120px; min-width: 60px; }
            .search-wrapper .search-btn { padding: 4px 8px; }
            .search-wrapper input { font-size: 0.65rem; padding: 4px 0; }
            .status-toggle { display: none !important; }
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
<!-- TOP NAVIGATION - WITH SEARCH BAR & SEARCH BUTTON -->
<!-- ================================================================ -->
<nav class="top-nav">
    
    <!-- Left Side -->
    <div class="flex items-center gap-3 flex-1 min-w-0">
        <button id="sidebarToggle" class="sidebar-toggle-btn" aria-label="Toggle Sidebar">
            <i class="fas fa-bars"></i>
        </button>
        
        <a href="/dispensary_system/frontend/pages/doctor/dashboard.php" class="flex items-center gap-2 text-gray-700 hover:text-primary transition shrink-0" style="color:var(--text-primary);">
            <i class="fas fa-home text-primary"></i>
            <span class="font-semibold text-sm hidden sm:inline">Dashboard</span>
        </a>
    </div>
    
    <!-- Search Bar with Search Button -->
    <div class="search-wrapper">
        <i class="fas fa-search search-icon"></i>
        <input type="text" id="searchInput" placeholder="Search patients by name, ID or phone..." 
               autocomplete="off">
        <button class="search-clear" id="searchClear" aria-label="Clear search">
            <i class="fas fa-times-circle"></i>
        </button>
        <button id="searchBtn" class="search-btn">
            <i class="fas fa-search"></i>
            <span class="btn-text">Search</span>
        </button>
    </div>
    
    <!-- Right Side -->
    <div class="flex items-center gap-3 shrink-0">
        
        <span class="datetime" id="currentDateTime"></span>
        
        <!-- Status Toggle -->
        <button id="statusToggle" class="status-toggle <?= $is_online ? '' : 'offline' ?>" title="Toggle Online Status">
            <span class="status-dot <?= $is_online ? 'online' : 'offline' ?>" id="statusDot"></span>
            <span class="status-text" id="statusText"><?= $is_online ? 'Online' : 'Offline' ?></span>
            <span class="status-spinner"></span>
        </button>
        
        <!-- Dark Mode Toggle -->
        <a href="#" id="darkModeLink" class="dark-toggle-btn">
            <i id="darkIcon" class="fas <?= $is_dark ? 'fa-sun' : 'fa-moon' ?>"></i>
            <span id="darkText"><?= $is_dark ? 'Light' : 'Dark' ?></span>
        </a>
        
        <!-- Profile Avatar -->
        <a href="/dispensary_system/frontend/pages/doctor/profile.php" class="avatar-link" title="Profile">
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
<!-- JAVASCRIPT - DARK MODE, STATUS, SEARCH, DATE/TIME -->
<!-- ================================================================ -->
<script>
// ================================================================
// DARK MODE TOGGLE - COOKIE BASED
// ================================================================
(function() {
    var darkModeLink = document.getElementById('darkModeLink');
    var darkIcon = document.getElementById('darkIcon');
    var darkText = document.getElementById('darkText');
    var htmlElement = document.documentElement;
    
    function setDarkMode(enabled) {
        if (enabled) {
            htmlElement.setAttribute('data-theme', 'dark');
            if (darkIcon) darkIcon.className = 'fas fa-sun';
            if (darkText) darkText.textContent = 'Light';
            document.cookie = 'dark_mode=true; path=/; max-age=31536000';
        } else {
            htmlElement.removeAttribute('data-theme');
            if (darkIcon) darkIcon.className = 'fas fa-moon';
            if (darkText) darkText.textContent = 'Dark';
            document.cookie = 'dark_mode=false; path=/; max-age=31536000';
        }
    }
    
    if (darkModeLink) {
        darkModeLink.addEventListener('click', function(e) {
            e.preventDefault();
            var isDark = htmlElement.getAttribute('data-theme') === 'dark';
            setDarkMode(!isDark);
        });
    }
})();

// ================================================================
// SIDEBAR TOGGLE
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
                                // Revert UI
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
                            // Revert UI
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
                        // Revert UI
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
// SEARCH - WITH SEARCH BUTTON, CLEAR BUTTON, ENTER KEY
// ================================================================
document.addEventListener('DOMContentLoaded', function() {
    var searchInput = document.getElementById('searchInput');
    var searchBtn = document.getElementById('searchBtn');
    var searchClear = document.getElementById('searchClear');
    
    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            window.location.href = '/dispensary_system/frontend/pages/doctor/search.php?q=' + encodeURIComponent(query);
        } else {
            // Highlight empty search
            searchInput.style.borderColor = 'var(--red)';
            searchInput.style.boxShadow = '0 0 0 3px rgba(239, 68, 68, 0.15)';
            setTimeout(function() {
                searchInput.style.borderColor = '';
                searchInput.style.boxShadow = '';
            }, 2000);
            searchInput.focus();
        }
    }
    
    // Search Button Click
    if (searchBtn) {
        searchBtn.addEventListener('click', function(e) {
            e.preventDefault();
            performSearch();
        });
    }
    
    // Enter Key
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                performSearch();
            }
        });
        
        // Show/hide clear button
        searchInput.addEventListener('input', function() {
            if (searchClear) {
                if (this.value.length > 0) {
                    searchClear.classList.add('visible');
                } else {
                    searchClear.classList.remove('visible');
                }
            }
        });
        
        // Escape to clear
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                this.value = '';
                this.blur();
                if (searchClear) {
                    searchClear.classList.remove('visible');
                }
            }
        });
    }
    
    // Clear Button
    if (searchClear) {
        searchClear.addEventListener('click', function() {
            searchInput.value = '';
            searchInput.focus();
            searchClear.classList.remove('visible');
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
        var darkBtn = document.getElementById('darkModeLink');
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
            var clearBtn = document.getElementById('searchClear');
            if (clearBtn) {
                clearBtn.classList.remove('visible');
            }
        }
    }
});

console.log('%c👨‍⚕️ Braick - Doctor Header (WITH SEARCH BUTTON)', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
console.log('%c👤 User: <?= htmlspecialchars($full_name) ?>', 'font-size:12px; color:#059669;');
console.log('%c👤 Role: <?= htmlspecialchars($user_role) ?>', 'font-size:12px; color:#64748B;');
console.log('%c🏢 Branch: <?= htmlspecialchars($user_branch_name) ?>', 'font-size:12px; color:#6EA8FE;');
console.log('%c🟢 Status: <?= $is_online ? 'Online ✅' : 'Offline ❌' ?>', 'font-size:12px; color:#059669;');
console.log('%c🆔 Doctor ID: <?= $doctor_id ?>', 'font-size:12px; color:#64748B;');
console.log('%c📸 Profile Picture: <?= !empty($profile_pic) ? '✅ Loaded' : '❌ Using Initial' ?>', 'font-size:12px; color:#64748B;');
console.log('%c🌙 Dark Mode: <?= $is_dark ? 'ON' : 'OFF' ?>', 'font-size:12px; color:#6EA8FE;');
console.log('%c🔍 Search: Click button, Enter, or Ctrl+K', 'font-size:12px; color:#64748B;');
console.log('%c🔄 Status: Ctrl+Shift+S to toggle online/offline', 'font-size:12px; color:#64748B;');
console.log('%c🔒 Login protection: Active', 'font-size:12px; color:#34D399;');
</script>

</body>
</html>