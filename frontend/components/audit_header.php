<?php
// ================================================================
// FILE: frontend/components/audit_header.php
// AUDIT - SHARED HEADER (BLUE THEME - #0B5ED7)
// ✅ Blue theme matching audit sidebar (#0B5ED7, #3B82F6)
// ✅ No branch selector (audit has full access)
// ✅ Dark mode support
// ✅ Date/Time display
// ================================================================

$user_id = $_SESSION['user_id'] ?? 0;
$full_name = $_SESSION['full_name'] ?? 'Audit User';
$branch_id = $_SESSION['branch_id'] ?? 1;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? 'audit';
$profile_pic = $_SESSION['profile_pic'] ?? '';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$current_page = basename($_SERVER['PHP_SELF']);
$page_title = ucfirst(str_replace('.php', '', $current_page));
if (empty($page_title) || $page_title == '') {
    $page_title = 'Dashboard';
}

$dark_mode = isset($_COOKIE['dark_mode']) ? $_COOKIE['dark_mode'] : 'false';

// GET UNREAD NOTIFICATIONS COUNT
$unread_notifications = 0;
if ($user_id > 0) {
    try {
        require_once __DIR__ . '/../../backend/config/database.php';
        if (class_exists('Database')) {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$user_id]);
            $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        }
    } catch (Exception $e) {
        $unread_notifications = 0;
    }
}

// SESSION CHECK
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'audit') {
    $current_file = basename($_SERVER['PHP_SELF']);
    if ($current_file !== 'login.php') {
        header('Location: /dispensary_system/frontend/pages/login.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $dark_mode === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Braick Dispensary - Audit <?= htmlspecialchars($page_title) ?></title>
    
    <!-- FAVICON -->
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="apple-touch-icon" href="<?= $logo_path ?>">
    
    <!-- EXTERNAL RESOURCES -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <style>
        /* ================================================================
           ✅ GLOBAL THEME VARIABLES - BLUE AUDIT THEME (#0B5ED7)
           ================================================================ */
        :root {
            --page-primary: #0B5ED7;
            --page-primary-dark: #0A4CA8;
            --page-primary-light: #3B82F6;
            --page-primary-bg: #E8F0FE;
            --page-accent: #60A5FA;
            --page-accent-dark: #3B82F6;
            --page-bg-body: #F1F5F9;
            --page-bg-card: #FFFFFF;
            --page-bg-nav: #FFFFFF;
            --page-hover: #F8FAFC;
            --page-text-primary: #1E293B;
            --page-text-secondary: #64748B;
            --page-text-muted: #94A3B8;
            --page-border: #E2E8F0;
            --page-input-bg: #FFFFFF;
            --page-input-border: #D1D5DB;
            --page-success: #059669;
            --page-success-bg: #D1FAE5;
            --page-danger: #DC2626;
            --page-danger-bg: #FEE2E2;
            --page-warning: #D97706;
            --page-warning-bg: #FEF3C7;
            --page-purple: #7C3AED;
            --page-purple-bg: #EDE9FE;
            --page-shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
            --page-shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --page-shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
        }

        [data-theme="dark"] {
            --page-primary: #3B82F6;
            --page-primary-dark: #2563EB;
            --page-primary-light: #60A5FA;
            --page-primary-bg: #1E3A5F;
            --page-accent: #93C5FD;
            --page-accent-dark: #60A5FA;
            --page-bg-body: #0F172A;
            --page-bg-card: #1E293B;
            --page-bg-nav: #1E293B;
            --page-hover: #0F172A;
            --page-text-primary: #F1F5F9;
            --page-text-secondary: #94A3B8;
            --page-text-muted: #64748B;
            --page-border: #334155;
            --page-input-bg: #0F172A;
            --page-input-border: #334155;
            --page-success: #34D399;
            --page-success-bg: #1A3A2A;
            --page-danger: #F87171;
            --page-danger-bg: #3A1A1A;
            --page-warning: #FBBF24;
            --page-warning-bg: #3A2A1A;
            --page-purple: #A78BFA;
            --page-purple-bg: #2D1B4E;
            --page-shadow-sm: 0 1px 3px rgba(0,0,0,0.3);
            --page-shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --page-shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        html {
            background: var(--page-bg-body);
            transition: background 0.3s ease;
            min-height: 100%;
        }

        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--page-bg-body);
            color: var(--page-text-primary);
            transition: background 0.3s ease, color 0.3s ease;
            min-height: 100vh;
        }

        html[data-theme="dark"] { background: #0F172A !important; }
        html[data-theme="dark"] body {
            background: #0F172A !important;
            color: #F1F5F9 !important;
        }

        .main-content {
            margin-left: 260px;
            margin-top: 72px;
            padding: 24px 28px;
            min-height: calc(100vh - 72px);
            background: var(--page-bg-body);
            transition: all 0.3s ease;
        }

        html[data-theme="dark"] .main-content {
            background: #0F172A !important;
            color: #F1F5F9;
        }

        /* ================================================================
           ✅ TOP NAV - BLUE BORDER (#0B5ED7)
           ================================================================ */
        .top-nav {
            position: fixed;
            top: 0;
            left: 260px;
            right: 0;
            height: 72px;
            background: #FFFFFF;
            z-index: 40;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            border-bottom: 2px solid var(--page-primary);
            transition: all 0.3s ease;
            box-shadow: 0 2px 12px rgba(11, 94, 215, 0.08);
        }
        
        [data-theme="dark"] .top-nav {
            background: #1E293B;
            border-bottom-color: var(--page-primary-light);
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.3);
        }

        /* SEARCH BAR */
        .top-nav .search-wrapper {
            display: flex;
            align-items: center;
            background: #F1F5F9;
            border-radius: 12px;
            border: 2px solid transparent;
            transition: all 0.3s ease;
            flex: 1;
            max-width: 480px;
            height: 42px;
        }
        
        .top-nav .search-wrapper:hover { background: #E2E8F0; }
        
        .top-nav .search-wrapper:focus-within {
            background: #FFFFFF;
            border-color: var(--page-primary);
            box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.12);
        }
        
        [data-theme="dark"] .top-nav .search-wrapper { background: #0F172A; }
        [data-theme="dark"] .top-nav .search-wrapper:hover { background: #1E293B; }
        [data-theme="dark"] .top-nav .search-wrapper:focus-within {
            background: #0F172A;
            border-color: var(--page-accent);
            box-shadow: 0 0 0 4px rgba(96, 165, 250, 0.15);
        }
        
        .top-nav .search-wrapper .search-icon {
            color: #94A3B8;
            margin-left: 14px;
            font-size: 0.9rem;
        }
        
        .top-nav .search-wrapper input {
            border: none;
            background: transparent;
            padding: 10px 12px;
            width: 100%;
            font-size: 0.85rem;
            outline: none;
            color: #1E293B;
            font-weight: 500;
        }
        
        [data-theme="dark"] .top-nav .search-wrapper input { color: #F1F5F9; }
        
        .top-nav .search-wrapper input::placeholder {
            color: #94A3B8;
            font-weight: 400;
        }
        
        /* ✅ SEARCH BUTTON - BLUE GRADIENT */
        .top-nav .search-wrapper .search-btn {
            background: linear-gradient(135deg, var(--page-primary), var(--page-primary-dark));
            color: white;
            border: none;
            padding: 8px 18px;
            border-radius: 0 10px 10px 0;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 600;
            transition: all 0.3s ease;
            white-space: nowrap;
            height: 100%;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .top-nav .search-wrapper .search-btn:hover {
            background: linear-gradient(135deg, var(--page-primary-dark), #083D8A);
            transform: translateX(1px);
        }

        /* DATE & TIME */
        .datetime-wrapper {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: linear-gradient(135deg, #E8F0FE 0%, #DBEAFE 100%);
            border: 2px solid #BFDBFE;
            border-radius: 12px;
            padding: 6px 14px;
            height: 42px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(11, 94, 215, 0.08);
        }
        
        .datetime-wrapper:hover {
            background: linear-gradient(135deg, #DBEAFE 0%, #BFDBFE 100%);
            border-color: #60A5FA;
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.15);
        }
        
        [data-theme="dark"] .datetime-wrapper {
            background: linear-gradient(135deg, #1E3A5F 0%, #1E40AF 100%);
            border-color: var(--page-primary-light);
            box-shadow: 0 2px 8px rgba(96, 165, 250, 0.15);
        }
        
        [data-theme="dark"] .datetime-wrapper:hover {
            background: linear-gradient(135deg, #1E40AF 0%, #1E3A5F 100%);
            border-color: var(--page-accent);
            box-shadow: 0 4px 12px rgba(96, 165, 250, 0.25);
        }
        
        .datetime-wrapper .datetime-icon {
            color: var(--page-primary);
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            background: rgba(11, 94, 215, 0.1);
            border-radius: 8px;
        }
        
        [data-theme="dark"] .datetime-wrapper .datetime-icon {
            color: var(--page-accent);
            background: rgba(96, 165, 250, 0.15);
        }
        
        .datetime-wrapper .datetime-content {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }
        
        .datetime-wrapper .datetime-date {
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--page-primary);
            text-transform: uppercase;
            letter-spacing: 0.03em;
            white-space: nowrap;
        }
        
        [data-theme="dark"] .datetime-wrapper .datetime-date { color: #93C5FD; }
        
        .datetime-wrapper .datetime-time {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--page-primary-dark);
            font-family: 'Courier New', monospace;
            letter-spacing: 0.05em;
            white-space: nowrap;
        }
        
        [data-theme="dark"] .datetime-wrapper .datetime-time { color: #DBEAFE; }

        /* DARK MODE TOGGLE */
        .dark-toggle-btn {
            background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%);
            border: 2px solid #334155;
            border-radius: 12px;
            padding: 8px 14px;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 600;
            color: #F1F5F9;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            height: 42px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }
        
        .dark-toggle-btn:hover {
            background: linear-gradient(135deg, #334155 0%, #1E293B 100%);
            border-color: var(--page-primary);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.25);
        }
        
        .dark-toggle-btn i {
            font-size: 0.9rem;
            transition: transform 0.4s ease;
        }
        
        .dark-toggle-btn:hover i { transform: rotate(20deg); }
        
        [data-theme="dark"] .dark-toggle-btn {
            background: linear-gradient(135deg, #FCD34D 0%, #F59E0B 100%);
            border-color: #F59E0B;
            color: #78350F;
        }
        
        [data-theme="dark"] .dark-toggle-btn:hover {
            background: linear-gradient(135deg, #FDE68A 0%, #FBBF24 100%);
            border-color: #FCD34D;
        }

        /* ICON BUTTONS */
        .top-nav .icon-btn {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748B;
            transition: all 0.3s ease;
            background: #F1F5F9;
            border: 2px solid transparent;
            cursor: pointer;
            position: relative;
            text-decoration: none;
        }
        
        .top-nav .icon-btn:hover {
            background: #E8F0FE;
            color: var(--page-primary);
            border-color: #BFDBFE;
            transform: translateY(-1px);
        }
        
        [data-theme="dark"] .top-nav .icon-btn {
            background: #1E293B;
            color: #94A3B8;
        }
        
        [data-theme="dark"] .top-nav .icon-btn:hover {
            background: #1E3A5F;
            color: var(--page-accent);
            border-color: var(--page-primary-light);
        }
        
        .notif-dot {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 10px;
            height: 10px;
            background: #059669;
            border-radius: 50%;
            border: 2px solid #FFFFFF;
            animation: pulse-dot 2s infinite;
        }
        
        [data-theme="dark"] .notif-dot { border-color: #1E293B; }
        
        .notif-dot.has-notif { background: #EF4444; }
        .notif-dot.no-notif { background: #94A3B8; animation: none; }
        
        @keyframes pulse-dot {
            0%, 100% { 
                transform: scale(1); 
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.6);
            }
            50% { 
                transform: scale(1.15); 
                box-shadow: 0 0 0 6px rgba(239, 68, 68, 0);
            }
        }

        /* AVATAR */
        .top-nav .avatar {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            object-fit: cover;
            border: 2px solid #E2E8F0;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
        
        .top-nav .avatar:hover {
            border-color: var(--page-primary);
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.25);
        }
        
        [data-theme="dark"] .top-nav .avatar { border-color: #334155; }
        [data-theme="dark"] .top-nav .avatar:hover { border-color: var(--page-accent); }

        /* ✅ AUDIT BADGE - BLUE GRADIENT (#0B5ED7) */
        .audit-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: linear-gradient(135deg, var(--page-primary), var(--page-primary-dark));
            color: white;
            padding: 6px 14px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            box-shadow: 0 2px 8px rgba(11, 94, 215, 0.3);
            margin-right: 8px;
            position: relative;
            overflow: hidden;
        }
        
        .audit-badge::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            animation: shine 3s infinite;
        }
        
        @keyframes shine {
            0% { left: -100%; }
            50%, 100% { left: 100%; }
        }
        
        .audit-badge i { font-size: 0.75rem; }
        
        .audit-badge .audit-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #34D399;
            animation: pulse-dot 1.5s infinite;
            box-shadow: 0 0 8px rgba(52, 211, 153, 0.8);
        }

        /* FOOTER */
        .footer {
            padding: 14px 0;
            border-top: 2px solid var(--page-border);
            margin-top: 20px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--page-text-secondary);
            transition: all 0.3s ease;
        }
        
        .footer .footer-brand { 
            color: var(--page-primary); 
            font-weight: 600; 
        }
        
        [data-theme="dark"] .footer .footer-brand { color: var(--page-accent); }

        /* RESPONSIVE */
        @media (max-width: 1200px) {
            .datetime-wrapper .datetime-date { font-size: 0.65rem; }
            .datetime-wrapper .datetime-time { font-size: 0.7rem; }
        }
        
        @media (max-width: 1024px) {
            .top-nav { left: 0; padding: 0 16px; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 280px; }
            .datetime-wrapper { padding: 6px 10px; }
        }
        
        @media (max-width: 768px) {
            .top-nav { 
                height: 64px; 
                padding: 0 12px;
                gap: 8px;
            }
            .main-content { margin-top: 64px; }
            .top-nav .search-wrapper { max-width: 160px; height: 38px; }
            .top-nav .search-wrapper input { font-size: 0.75rem; padding: 8px; }
            .top-nav .search-wrapper .search-btn { padding: 8px 10px; font-size: 0.7rem; }
            .top-nav .search-wrapper .search-btn span { display: none; }
            .datetime-wrapper { display: none; }
            .top-nav .icon-btn { width: 38px; height: 38px; }
            .top-nav .avatar { width: 38px; height: 38px; }
            .dark-toggle-btn { padding: 6px 10px; height: 38px; font-size: 0.7rem; }
            .dark-toggle-btn span { display: none; }
            .audit-badge { padding: 4px 10px; font-size: 0.6rem; }
        }
        
        @media (max-width: 640px) {
            .top-nav .search-wrapper { max-width: 120px; }
            .audit-badge span { display: none; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- TOP NAVIGATION -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div style="display:flex;align-items:center;gap:16px;flex:1;">
        <button id="sidebarToggle" class="icon-btn" style="display:none;">
            <i class="fas fa-bars" style="font-size:1.1rem;"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search search-icon"></i>
            <input type="text" id="globalSearch" placeholder="Search audit logs, reports, records...">
            <button class="search-btn" onclick="performGlobalSearch()">
                <i class="fas fa-search"></i> <span>Search</span>
            </button>
        </div>
    </div>
    
    <div style="display:flex;align-items:center;gap:12px;">
        <!-- Audit Badge -->
        <span class="audit-badge">
            <span class="audit-dot"></span>
            <i class="fas fa-shield-alt"></i>
            <span>AUDIT</span>
        </span>
        
        <!-- Date & Time -->
        <div class="datetime-wrapper" id="datetimeWrapper">
            <div class="datetime-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="datetime-content">
                <span class="datetime-date" id="datetimeDate">--</span>
                <span class="datetime-time" id="datetimeTime">--:--:--</span>
            </div>
        </div>
        
        <!-- Dark Mode Toggle -->
        <button id="darkModeToggle" class="dark-toggle-btn" title="Toggle Dark Mode">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <!-- Notifications -->
        <a href="notifications.php" class="icon-btn" title="Notifications">
            <i class="fas fa-bell" style="font-size:1.1rem;"></i>
            <span class="notif-dot <?= $unread_notifications > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </a>
        
        <!-- Profile Avatar -->
        <a href="profile.php" title="Profile">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='<?= $logo_path ?>'">
        </a>
    </div>
</nav>

<script>
// DARK MODE TOGGLE
(function() {
    var darkModeToggle = document.getElementById('darkModeToggle');
    var darkIcon = document.getElementById('darkIcon');
    var darkText = document.getElementById('darkText');
    var htmlElement = document.documentElement;
    
    if (localStorage.getItem('darkMode') === 'true') {
        htmlElement.setAttribute('data-theme', 'dark');
        if (darkIcon) darkIcon.className = 'fas fa-sun';
        if (darkText) darkText.textContent = 'Light';
    } else {
        htmlElement.setAttribute('data-theme', 'light');
    }
    
    if (darkModeToggle) {
        darkModeToggle.addEventListener('click', function() {
            var isDark = htmlElement.getAttribute('data-theme') === 'dark';
            
            if (isDark) {
                htmlElement.setAttribute('data-theme', 'light');
                if (darkIcon) darkIcon.className = 'fas fa-moon';
                if (darkText) darkText.textContent = 'Dark';
                localStorage.setItem('darkMode', 'false');
                document.cookie = 'dark_mode=false; path=/; max-age=31536000';
                document.body.style.background = '#F1F5F9';
                document.body.style.color = '#1E293B';
            } else {
                htmlElement.setAttribute('data-theme', 'dark');
                if (darkIcon) darkIcon.className = 'fas fa-sun';
                if (darkText) darkText.textContent = 'Light';
                localStorage.setItem('darkMode', 'true');
                document.cookie = 'dark_mode=true; path=/; max-age=31536000';
                document.body.style.background = '#0F172A';
                document.body.style.color = '#F1F5F9';
            }
            
            document.dispatchEvent(new CustomEvent('darkModeChanged', {
                detail: { isDark: !isDark }
            }));
        });
    }
})();

// DATE & TIME
function updateDateTime() {
    var now = new Date();
    var dateStr = now.toLocaleDateString('en-US', {
        weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
    });
    var timeStr = now.toLocaleTimeString('en-US', {
        hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
    });
    
    var dateEl = document.getElementById('datetimeDate');
    var timeEl = document.getElementById('datetimeTime');
    if (dateEl) dateEl.textContent = dateStr;
    if (timeEl) timeEl.textContent = timeStr;
}
updateDateTime();
setInterval(updateDateTime, 1000);

// GLOBAL SEARCH
function performGlobalSearch() {
    var query = document.getElementById('globalSearch').value.trim();
    if (query.length > 0) {
        window.location.href = 'search_results.php?q=' + encodeURIComponent(query);
    }
}

document.getElementById('globalSearch')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') performGlobalSearch();
});

// SIDEBAR TOGGLE
document.getElementById('sidebarToggle')?.addEventListener('click', function() {
    var sidebar = document.getElementById('sidebar');
    if (sidebar) sidebar.classList.toggle('open');
});

console.log('%c🔍 Audit Header - BLUE THEME', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
console.log('%c✅ Blue: #0B5ED7 → #0A4CA8', 'font-size:13px; color:#0B5ED7; font-weight:bold;');
console.log('%c✅ Accent: #60A5FA (Light Blue)', 'font-size:13px; color:#60A5FA;');
console.log('%c✅ Matching sidebar theme', 'font-size:13px; color:#34D399;');
console.log('%c✅ Border-bottom: #0B5ED7', 'font-size:13px; color:#34D399;');
</script>