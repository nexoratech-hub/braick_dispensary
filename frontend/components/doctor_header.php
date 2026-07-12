<?php
// ================================================================
// FILE: frontend/components/doctor_header.php
// DOCTOR - SHARED HEADER (SEARCH BAR REMOVED)
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// SESSION DATA
// ================================================================
if (!isset($_SESSION['doctor_id'])) {
    $_SESSION['doctor_id'] = 2;
    $_SESSION['full_name'] = 'Dr. Sarah Mwamba';
    $_SESSION['role'] = 'doctor';
    $_SESSION['branch_id'] = 1;
    $_SESSION['specialty'] = 'Cardiology';
    $_SESSION['profile_pic'] = '';
}

// ================================================================
// GET PROFILE PICTURE
// ================================================================
$doctor_id = $_SESSION['doctor_id'] ?? 2;
$full_name = $_SESSION['full_name'] ?? 'Dr. Unknown';
$profile_pic = $_SESSION['profile_pic'] ?? '';

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
           TOP NAVIGATION - NO SEARCH BAR
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
        
        .status-toggle {
            display: flex;
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
        }
        
        .status-toggle:hover {
            border-color: var(--primary);
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
        }
        
        @media (max-width: 768px) {
            .top-nav .datetime { display: none; }
            .top-nav .status-toggle { display: none; }
            .main-content { padding: 16px; }
            .stat-card .stat-number { font-size: 1.4rem; }
        }
        
        @media (max-width: 640px) {
            .top-nav { padding: 0 12px; }
            .dark-toggle-btn { padding: 4px 8px; font-size: 0.7rem; }
            .dark-toggle-btn span { display: none; }
            .main-content { padding: 10px; }
            .page-header .page-title { font-size: 1.2rem; }
            .avatar-link .avatar-img { width: 32px; height: 32px; }
            .avatar-link .avatar-placeholder { width: 32px; height: 32px; font-size: 0.8rem; }
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
<!-- TOP NAVIGATION - NO SEARCH BAR -->
<!-- ================================================================ -->
<nav class="top-nav">
    
    <!-- Left Side - Brand -->
    <div class="flex items-center gap-3 flex-1">
        <button id="sidebarToggle" class="sidebar-toggle-btn" aria-label="Toggle Sidebar">
            <i class="fas fa-bars"></i>
        </button>
        
        <a href="dashboard.php" class="flex items-center gap-2 text-gray-700 hover:text-primary transition">
            <i class="fas fa-home text-primary"></i>
            <span class="font-semibold text-sm hidden sm:inline">Dashboard</span>
        </a>
        
        <span class="text-gray-300 text-sm hidden md:inline">|</span>
        <span class="text-sm text-gray-500 hidden md:inline"><?= htmlspecialchars($full_name) ?></span>
    </div>
    
    <!-- Right Side -->
    <div class="flex items-center gap-3">
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="statusToggle" class="status-toggle" title="Toggle Status">
            <span class="status-dot online" id="statusDot"></span>
            <span id="statusText">Online</span>
        </button>
        
        <button id="darkModeToggle" class="dark-toggle-btn" title="Toggle Dark Mode">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn" id="notifBtn" title="Notifications">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot" id="notifDot" style="display: none;"></span>
        </button>
        
        <a href="profile.php" class="avatar-link" title="Profile">
            <?php if ($show_initial): ?>
                <div class="avatar-placeholder avatar-color-<?= (abs(crc32($full_name)) % 7) + 1 ?>">
                    <?= $initial ?>
                </div>
            <?php else: ?>
                <img src="<?= $avatar_url ?>" alt="Profile" class="avatar-img">
            <?php endif; ?>
            <span class="status-ring" id="avatarStatusRing"></span>
        </a>
        
    </div>
</nav>

<!-- ================================================================ -->
<!-- JAVASCRIPT - DARK MODE, STATUS, DATE/TIME -->
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
// ONLINE STATUS TOGGLE
// ================================================================
document.addEventListener('DOMContentLoaded', function() {
    var statusToggle = document.getElementById('statusToggle');
    var statusDot = document.getElementById('statusDot');
    var statusText = document.getElementById('statusText');
    var avatarStatusRing = document.getElementById('avatarStatusRing');
    
    if (statusToggle) {
        statusToggle.addEventListener('click', function() {
            var isOnline = statusDot.classList.contains('online');
            if (isOnline) {
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
        });
    }
});

// ================================================================
// KEYBOARD SHORTCUTS
// ================================================================
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
        e.preventDefault();
        var darkBtn = document.getElementById('darkModeToggle');
        if (darkBtn) {
            darkBtn.click();
        }
    }
});

console.log('%c👨‍⚕️ Braick - Doctor Header (NO SEARCH BAR)', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
console.log('%c📸 Profile Picture: <?= !empty($profile_pic) ? '✅ Loaded' : '❌ Using Initial' ?>', 'font-size:12px; color:#059669;');
console.log('%c🌙 Dark Mode: ' + (document.documentElement.getAttribute('data-theme') === 'dark' ? 'ON' : 'OFF'), 'font-size:12px; color:#6EA8FE;');
console.log('%c🔍 Search: Use search bar on each page', 'font-size:12px; color:#64748B;');
</script>

</body>
</html>