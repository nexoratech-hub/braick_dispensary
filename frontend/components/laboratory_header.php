<?php
// ================================================================
// FILE: frontend/components/laboratory_header.php
// LABORATORY - SHARED HEADER (FIXED - Sidebar Toggle Working)
// WITH LOGIN PROTECTION
// DATE & TIME now displayed correctly
// NOTIFICATIONS - REMOVED
// SMALLER SEARCH BAR
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
// DARK MODE - SESSION BASED
// ================================================================
if (!isset($_SESSION['dark_mode'])) {
    $_SESSION['dark_mode'] = 'light';
}

if (isset($_GET['toggle_dark'])) {
    $_SESSION['dark_mode'] = ($_SESSION['dark_mode'] === 'dark') ? 'light' : 'dark';
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
    <title>Braick Dispensary - Laboratory <?= $page_title ?></title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
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
            
            --pending: #D97706;
            --pending-bg: #FEF3C7;
            --in-progress: #0B5ED7;
            --in-progress-bg: #E8F0FE;
            --completed: #059669;
            --completed-bg: #D1FAE5;
            --cancelled: #DC2626;
            --cancelled-bg: #FEE2E2;
            
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
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        
        /* ================================================================
           SEARCH BAR - SMALLER SIZE
           ================================================================ */
        .top-nav .search-wrapper {
            display: flex;
            align-items: center;
            background: var(--bg-body);
            border-radius: 8px;
            border: 2px solid var(--border-color);
            transition: all 0.3s;
            flex: 0 1 280px;
            max-width: 280px;
            min-width: 140px;
        }
        
        .top-nav .search-wrapper:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15);
        }
        
        .top-nav .search-wrapper input {
            border: none;
            background: transparent;
            padding: 6px 10px;
            width: 100%;
            font-size: 0.78rem;
            outline: none;
            color: var(--text-primary);
        }
        
        .top-nav .search-wrapper input::placeholder {
            color: var(--text-secondary);
            font-size: 0.7rem;
        }
        
        .top-nav .search-wrapper .search-icon {
            padding: 0 0 0 10px;
            color: var(--text-secondary);
            font-size: 0.7rem;
        }
        
        .top-nav .search-wrapper .search-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 0 8px 8px 0;
            cursor: pointer;
            font-size: 0.7rem;
            transition: all 0.3s;
            white-space: nowrap;
            font-weight: 500;
        }
        
        .top-nav .search-wrapper .search-btn:hover {
            background: var(--primary-dark);
        }
        
        .top-nav .avatar {
            width: 38px;
            height: 38px;
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
        
        .dark-toggle-btn {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 5px 10px;
            cursor: pointer;
            font-size: 0.75rem;
            color: var(--text-primary);
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
        }
        
        .dark-toggle-btn:hover {
            border-color: var(--primary);
            background: var(--bg-card);
        }
        
        .role-badge {
            font-size: 0.55rem;
            font-weight: 600;
            padding: 2px 8px;
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
            font-size: 0.55rem;
            font-weight: 600;
            padding: 2px 8px;
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
        
        .datetime-wrapper {
            display: flex;
            align-items: center;
            gap: 5px;
            background: var(--bg-body);
            padding: 3px 10px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 500;
            white-space: nowrap;
        }
        
        .datetime-wrapper .datetime-icon {
            color: var(--primary);
            font-size: 0.65rem;
        }
        
        .datetime-wrapper .datetime-separator {
            color: var(--border-color);
            margin: 0 2px;
        }
        
        .sidebar-toggle-btn {
            display: none;
            background: transparent;
            border: none;
            color: var(--text-secondary);
            font-size: 1.1rem;
            cursor: pointer;
            padding: 4px 6px;
            transition: all 0.3s;
        }
        
        .sidebar-toggle-btn:hover {
            color: var(--primary);
        }
        
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { 
                flex: 0 1 200px;
                max-width: 200px;
            }
            .sidebar-toggle-btn {
                display: block;
            }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { 
                flex: 0 1 140px;
                max-width: 140px;
                min-width: 80px;
            }
            .top-nav .search-wrapper input {
                font-size: 0.65rem;
                padding: 4px 6px;
            }
            .top-nav .search-wrapper .search-btn {
                padding: 4px 8px;
                font-size: 0.6rem;
            }
            .top-nav .search-wrapper .search-icon {
                padding: 0 0 0 6px;
                font-size: 0.6rem;
            }
            .datetime-wrapper {
                font-size: 0.6rem;
                padding: 2px 6px;
            }
            .datetime-wrapper .datetime-icon {
                display: none;
            }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .top-nav .search-wrapper { 
                flex: 0 1 100px;
                max-width: 100px;
                min-width: 60px;
            }
            .top-nav .search-wrapper .search-btn {
                display: none;
            }
            .top-nav .search-wrapper .search-icon {
                padding: 0 0 0 6px;
            }
            .datetime-wrapper {
                font-size: 0.5rem;
                padding: 2px 4px;
            }
            .dark-toggle-btn span {
                display: none;
            }
            .dark-toggle-btn {
                padding: 4px 6px;
                font-size: 0.65rem;
            }
        }
        
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
        <!-- SIDEBAR TOGGLE BUTTON -->
        <button id="sidebarToggleBtn" class="sidebar-toggle-btn" aria-label="Toggle Sidebar">
            <i class="fas fa-bars"></i>
        </button>
        
        <!-- SEARCH BAR - SMALLER -->
        <div class="search-wrapper">
            <span class="search-icon"><i class="fas fa-search"></i></span>
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
        
        <!-- DATE & TIME -->
        <span class="datetime-wrapper" id="datetimeWrapper">
            <i class="fas fa-calendar-alt datetime-icon"></i>
            <span id="currentDate"><?= date('M d, Y') ?></span>
            <span class="datetime-separator">|</span>
            <i class="fas fa-clock datetime-icon"></i>
            <span id="currentTime"><?= date('h:i:s A') ?></span>
        </span>
        
        <!-- DARK MODE TOGGLE -->
        <a href="?toggle_dark=1" class="dark-toggle-btn" id="darkModeLink">
            <i id="darkIcon" class="fas <?= $dark_mode === 'dark' ? 'fa-sun' : 'fa-moon' ?>"></i>
            <span id="darkText"><?= $dark_mode === 'dark' ? 'Light' : 'Dark' ?></span>
        </a>
        
        <!-- Profile Avatar -->
        <a href="profile.php">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2238%22 height=%2238%22%3E%3Crect width=%2238%22 height=%2238%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2219%22 y=%2224%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2216%22 font-weight=%22bold%22%3E<?= strtoupper(substr($user_full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    (function() {
        function initSidebarToggle() {
            var sidebar = document.getElementById('sidebar');
            var toggleBtn = document.getElementById('sidebarToggleBtn');
            var closeBtn = document.getElementById('sidebarCloseBtn');
            var overlay = document.getElementById('sidebarOverlay');
            
            if (!sidebar) {
                console.warn('⚠️ Sidebar not found, retrying...');
                setTimeout(initSidebarToggle, 500);
                return;
            }
            
            if (!toggleBtn) {
                console.warn('⚠️ Toggle button not found, retrying...');
                setTimeout(initSidebarToggle, 500);
                return;
            }
            
            console.log('✅ Sidebar toggle initialized');
            
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.id = 'sidebarOverlay';
                overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.6);z-index:9998;display:none;backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);';
                document.body.appendChild(overlay);
                overlay = document.getElementById('sidebarOverlay');
            }
            
            function openSidebar() {
                sidebar.classList.add('open');
                if (overlay) {
                    overlay.style.display = 'block';
                    overlay.classList.add('active');
                }
                document.body.style.overflow = 'hidden';
                if (toggleBtn) {
                    toggleBtn.innerHTML = '<i class="fas fa-times"></i>';
                }
                console.log('🔓 Sidebar opened');
            }
            
            function closeSidebar() {
                sidebar.classList.remove('open');
                if (overlay) {
                    overlay.style.display = 'none';
                    overlay.classList.remove('active');
                }
                document.body.style.overflow = '';
                if (toggleBtn) {
                    toggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
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
            
            toggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('🔘 Toggle button clicked!');
                toggleSidebar();
            });
            
            if (closeBtn) {
                closeBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    closeSidebar();
                });
            }
            
            if (overlay) {
                overlay.addEventListener('click', function(e) {
                    if (e.target === overlay) {
                        closeSidebar();
                    }
                });
            }
            
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && sidebar.classList.contains('open')) {
                    closeSidebar();
                }
            });
            
            window.addEventListener('resize', function() {
                if (window.innerWidth > 1024 && sidebar.classList.contains('open')) {
                    closeSidebar();
                }
            });
            
            document.addEventListener('click', function(e) {
                if (window.innerWidth <= 1024) {
                    if (sidebar.classList.contains('open') && 
                        !sidebar.contains(e.target) && 
                        e.target !== toggleBtn) {
                        closeSidebar();
                    }
                }
            });
            
            if (window.innerWidth > 1024) {
                if (!sidebar.classList.contains('open')) {
                    sidebar.classList.add('open');
                    if (toggleBtn) {
                        toggleBtn.innerHTML = '<i class="fas fa-times"></i>';
                    }
                }
            }
            
            console.log('✅ Sidebar toggle ready!');
            console.log('📱 Current state:', sidebar.classList.contains('open') ? 'OPEN' : 'CLOSED');
        }
        
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(initSidebarToggle, 300);
            });
        } else {
            setTimeout(initSidebarToggle, 300);
        }
        
        setTimeout(function() {
            var sidebar = document.getElementById('sidebar');
            var toggleBtn = document.getElementById('sidebarToggleBtn');
            if (!sidebar || !toggleBtn) {
                console.log('🔄 Retrying sidebar toggle initialization...');
                initSidebarToggle();
            }
        }, 1500);
        
        setTimeout(function() {
            var sidebar = document.getElementById('sidebar');
            var toggleBtn = document.getElementById('sidebarToggleBtn');
            if (!sidebar || !toggleBtn) {
                console.log('🔄 Second retry for sidebar toggle...');
                initSidebarToggle();
            }
        }, 3000);
    })();

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
    // DATE & TIME - Updates every second
    // ================================================================
    function updateDateTime() {
        var now = new Date();
        
        var dateStr = now.toLocaleDateString('en-US', {
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
        
        var dateEl = document.getElementById('currentDate');
        var timeEl = document.getElementById('currentTime');
        
        if (dateEl) dateEl.textContent = dateStr;
        if (timeEl) timeEl.textContent = timeStr;
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
        
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'toast';
            toast.className = 'toast-custom';
            toast.style.display = 'none';
            toast.innerHTML = '<i class="fas fa-info-circle"></i><div><p id="toastTitle" style="font-weight:600;font-size:0.85rem;margin:0;"></p><p id="toastMessage" style="font-size:0.75rem;opacity:0.9;margin:0;"></p></div>';
            document.body.appendChild(toast);
            toastTitle = document.getElementById('toastTitle');
            toastMessage = document.getElementById('toastMessage');
        }
        
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

    console.log('%c🔬 Braick Dispensary - Laboratory Header', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:12px; color:#059669;');
    console.log('%c👤 Role: <?= htmlspecialchars($user_role) ?>', 'font-size:12px; color:#64748B;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($user_branch_name) ?>', 'font-size:12px; color:#6EA8FE;');
    console.log('%c🌙 Dark Mode: <?= $dark_mode ?>', 'font-size:12px; color:#D97706;');
    console.log('%c✅ Search bar size reduced (280px max)', 'font-size:13px; font-weight:bold; color:#34D399;');
    console.log('%c✅ Sidebar toggle: Click ☰ button', 'font-size:13px; color:#34D399;');
    console.log('%c❌ Notifications button removed', 'font-size:13px; color:#DC2626;');
</script>
</body>
</html>