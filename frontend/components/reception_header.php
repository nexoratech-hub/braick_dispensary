<?php
// ================================================================
// FILE: frontend/components/reception_header.php
// SHARED HEADER - BRAICK DISPENSARY
// Top navigation + global CSS + helpers + favicon
// ================================================================

// ================================================================
// SESSION
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// ACCESS CONTROL
// ================================================================
$allowed_roles = ['reception', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    switch ($_SESSION['role']) {
        case 'doctor':     header('Location: ../doctor/dashboard.php');     break;
        case 'pharmacy':   header('Location: ../pharmacy/dashboard.php');   break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier':    header('Location: ../cashier/dashboard.php');    break;
        default:           header('Location: ../login.php');                break;
    }
    exit;
}

// ================================================================
// SESSION DATA
// ================================================================
$user_id     = $user_id     ?? ($_SESSION['user_id']     ?? 0);
$full_name   = $full_name   ?? ($_SESSION['full_name']   ?? 'User');
$role        = $role        ?? ($_SESSION['role']        ?? 'reception');
$branch_id   = $branch_id   ?? ($_SESSION['branch_id']   ?? 1);
$branch_name = $branch_name ?? ($_SESSION['branch_name'] ?? 'Dodoma');
$username    = $username    ?? ($_SESSION['username']    ?? '');
$profile_pic = $profile_pic ?? ($_SESSION['profile_pic'] ?? '');

// ================================================================
// DATABASE — PATH SAHIHI
// ================================================================
if (!class_exists('Database')) {
    require_once __DIR__ . '/../../backend/config/database.php';
}

// ================================================================
// UNREAD NOTIFICATIONS
// ================================================================
if (!isset($unread_notifications)) {
    $unread_notifications = 0;
    try {
        $db = $db ?? Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        $unread_notifications = (int)($stmt->fetch()['total'] ?? 0);
    } catch (Exception $e) {
        $unread_notifications = 0;
    }
}

// ================================================================
// HELPER: time_ago() — GUARDED
// ================================================================
if (!function_exists('time_ago')) {
    function time_ago($timestamp) {
        if (empty($timestamp)) return 'Just now';
        $time = strtotime($timestamp);
        $diff = time() - $time;

        if ($diff < 60)     return 'Just now';
        if ($diff < 3600)   return floor($diff / 60)    . 'm ago';
        if ($diff < 86400)  return floor($diff / 3600)  . 'h ago';
        if ($diff < 604800) return floor($diff / 86400) . 'd ago';
        return date('M d, Y', $time);
    }
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
if (!isset($profile_pic_url)) {
    $profile_pic_url = !empty($profile_pic)
        ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic
        : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';
}

// ================================================================
// ✅ FAVICON / LOGO PATH
// ================================================================
if (!isset($logo_path)) {
    $logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- ============================================================
         ✅ FAVICON — Icon ya Braick kwenye browser tab
         ============================================================ -->
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($logo_path) ?>">
    <link rel="shortcut icon" type="image/png" href="<?= htmlspecialchars($logo_path) ?>">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($logo_path) ?>">

    <!-- ============================================================
         TITLE (kama dashboard haijaset title)
         ============================================================ -->
    <?php if (!isset($page_title)): ?>
        <title>Reception - Braick Dispensary</title>
    <?php endif; ?>

    <!-- Tailwind + Font Awesome -->
    <script src="https://cdn.tailwindcss.com"></script>
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
        }

        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --primary-bg: #1E3A5F;
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background .3s ease, color .3s ease;
        }

        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }

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
            padding: 0 20px;
            border-bottom: 2px solid var(--border-color);
            transition: all .3s ease;
            gap: 12px;
        }

        .top-nav .nav-left {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 0 1 auto;
            min-width: 0;
        }

        .top-nav .nav-right {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        /* ============================================================
           SEARCH BAR — width ndogo
           ============================================================ */
        .top-nav .search-wrapper {
            display: flex;
            align-items: center;
            background: var(--bg-body);
            border-radius: 10px;
            border: 2px solid var(--border-color);
            transition: all .3s;
            width: 320px;
            max-width: 100%;
        }

        .top-nav .search-wrapper:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15);
        }

        .top-nav .search-wrapper .search-icon {
            color: var(--text-secondary);
            padding-left: 12px;
            font-size: 0.8rem;
        }

        .top-nav .search-wrapper input {
            border: none;
            background: transparent;
            padding: 7px 10px;
            width: 100%;
            font-size: 0.82rem;
            outline: none;
            color: var(--text-primary);
        }

        .top-nav .search-wrapper input::placeholder {
            color: var(--text-secondary);
            font-size: 0.78rem;
        }

        .top-nav .search-wrapper .search-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 7px 14px;
            border-radius: 0 8px 8px 0;
            cursor: pointer;
            font-size: 0.78rem;
            transition: all .3s;
            white-space: nowrap;
            font-weight: 500;
        }

        .top-nav .search-wrapper .search-btn:hover {
            background: var(--primary-dark);
        }

        /* ============================================================
           DATE & TIME BOX — BLUE GRADIENT BOX
           ============================================================ */
        .datetime-box {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 7px 14px;
            border-radius: 10px;
            background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 100%);
            color: #fff;
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.15);
            transition: all .3s ease;
            white-space: nowrap;
        }

        .datetime-box:hover {
            box-shadow: 0 6px 18px rgba(11, 94, 215, 0.35);
            transform: translateY(-1px);
        }

        .datetime-box .dt-icon {
            font-size: 0.95rem;
            opacity: 0.9;
            color: #fff;
        }

        .datetime-box .dt-content {
            display: flex;
            flex-direction: column;
            line-height: 1.15;
        }

        .datetime-box .dt-date {
            font-size: 0.62rem;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.85);
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .datetime-box .dt-time {
            font-size: 0.85rem;
            font-weight: 700;
            color: #fff;
            font-variant-numeric: tabular-nums;
            letter-spacing: 0.3px;
        }

        [data-theme="dark"] .datetime-box {
            background: linear-gradient(135deg, #1E40AF 0%, #1E3A8A 100%);
            border-color: rgba(255, 255, 255, 0.1);
        }

        /* ============================================================
           BRANCH BADGE
           ============================================================ */
        .branch-badge-display {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.65rem;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 10px;
            background: var(--success-bg);
            color: var(--success);
            border: 1px solid rgba(5, 150, 105, 0.15);
            white-space: nowrap;
        }

        [data-theme="dark"] .branch-badge-display {
            background: #1A3A2A;
            color: #34D399;
            border-color: rgba(52, 211, 153, 0.15);
        }

        /* ============================================================
           DARK MODE TOGGLE
           ============================================================ */
        .dark-toggle-btn {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 6px 10px;
            cursor: pointer;
            font-size: 0.78rem;
            color: var(--text-primary);
            transition: all .3s;
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 500;
        }

        .dark-toggle-btn:hover {
            border-color: var(--primary);
            background: var(--bg-card);
            color: var(--primary);
        }

        .dark-toggle-btn i { font-size: 0.85rem; }

        /* ============================================================
           ICON BUTTON
           ============================================================ */
        .top-nav .icon-btn {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            transition: all .3s;
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            cursor: pointer;
            position: relative;
        }

        .top-nav .icon-btn:hover {
            background: var(--bg-card);
            color: var(--primary);
            border-color: var(--primary);
        }

        /* ============================================================
           NOTIFICATION DOT
           ============================================================ */
        .notif-dot {
            position: absolute;
            top: 4px;
            right: 4px;
            width: 9px;
            height: 9px;
            border-radius: 50%;
            border: 2px solid var(--bg-nav);
            animation: pulse-dot 2s infinite;
        }

        .notif-dot.has-notif { background: var(--danger); }
        .notif-dot.no-notif  { background: var(--gray-400); animation: none; }

        @keyframes pulse-dot {
            0%, 100% { transform: scale(1); }
            50%      { transform: scale(1.2); }
        }

        /* ============================================================
           AVATAR
           ============================================================ */
        .top-nav .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-color);
            cursor: pointer;
            transition: all .3s;
        }

        .top-nav .avatar:hover {
            border-color: var(--primary);
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.2);
        }

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
            transition: all .4s cubic-bezier(.4, 0, .2, 1);
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
        .toast-custom.error   { background: var(--danger); }
        .toast-custom.info    { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }

        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1400px) {
            .top-nav .search-wrapper { width: 260px; }
        }

        @media (max-width: 1200px) {
            .top-nav .search-wrapper { width: 220px; }
            .datetime-box .dt-date { display: none; }
        }

        @media (max-width: 1024px) {
            .top-nav { left: 0; padding: 0 14px; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { width: 240px; }
        }

        @media (max-width: 768px) {
            .top-nav .search-wrapper { width: 160px; }
            .datetime-box { display: none; }
            .branch-badge-display { display: none; }
        }

        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .top-nav .search-wrapper { width: 120px; }
            .top-nav .search-wrapper .search-btn { padding: 7px 10px; font-size: 0.7rem; }
            .top-nav .search-wrapper .search-btn span { display: none; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- TOP NAVIGATION -->
<!-- ================================================================ -->
<nav class="top-nav">

    <!-- LEFT: Hamburger + Search -->
    <div class="nav-left">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars"></i>
        </button>

        <div class="search-wrapper">
            <i class="fas fa-search search-icon"></i>
            <input type="text" id="searchInput" placeholder="Search patients...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> <span>Search</span>
            </button>
        </div>
    </div>

    <!-- RIGHT: Branch + DateTime + Dark + Bell + Avatar -->
    <div class="nav-right">

        <span class="branch-badge-display">
            <i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name) ?>
        </span>

        <!-- DATE & TIME — BLUE BOX -->
        <div class="datetime-box" id="datetimeBox">
            <i class="fas fa-calendar-day dt-icon"></i>
            <div class="dt-content">
                <span class="dt-date" id="dtDate"><?= date('D, M d, Y') ?></span>
                <span class="dt-time" id="dtTime"><?= date('h:i:s A') ?></span>
            </div>
        </div>

        <button id="darkModeToggle" class="dark-toggle-btn" title="Toggle dark mode">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>

        <button class="icon-btn" onclick="window.location.href='notifications.php'" title="Notifications">
            <i class="fas fa-bell"></i>
            <span class="notif-dot <?= $unread_notifications > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>

        <a href="profile.php" title="Profile">
            <img src="<?= htmlspecialchars($profile_pic_url) ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<script>
    // ================================================================
    // DARK MODE
    // ================================================================
    (function () {
        var html = document.documentElement;
        var darkToggle = document.getElementById('darkModeToggle');
        var darkIcon = document.getElementById('darkIcon');
        var darkText = document.getElementById('darkText');

        if (localStorage.getItem('darkMode') === 'true') {
            html.setAttribute('data-theme', 'dark');
            if (darkIcon) darkIcon.className = 'fas fa-sun';
            if (darkText) darkText.textContent = 'Light';
        }

        if (darkToggle) {
            darkToggle.addEventListener('click', function () {
                if (html.getAttribute('data-theme') === 'dark') {
                    html.removeAttribute('data-theme');
                    if (darkIcon) darkIcon.className = 'fas fa-moon';
                    if (darkText) darkText.textContent = 'Dark';
                    localStorage.setItem('darkMode', 'false');
                    document.cookie = 'dark_mode=false; path=/';
                } else {
                    html.setAttribute('data-theme', 'dark');
                    if (darkIcon) darkIcon.className = 'fas fa-sun';
                    if (darkText) darkText.textContent = 'Light';
                    localStorage.setItem('darkMode', 'true');
                    document.cookie = 'dark_mode=true; path=/';
                }
            });
        }
    })();

    // ================================================================
    // LIVE DATE & TIME
    // ================================================================
    (function () {
        var dtDate = document.getElementById('dtDate');
        var dtTime = document.getElementById('dtTime');

        function updateDateTime() {
            if (!dtDate || !dtTime) return;
            var now = new Date();

            dtDate.textContent = now.toLocaleDateString('en-US', {
                weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
            });

            dtTime.textContent = now.toLocaleTimeString('en-US', {
                hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
            });
        }

        updateDateTime();
        setInterval(updateDateTime, 1000);
    })();

    // ================================================================
    // SEARCH
    // ================================================================
    (function () {
        var btn = document.getElementById('searchBtn');
        var input = document.getElementById('searchInput');
        if (btn && input) {
            btn.addEventListener('click', function () {
                var q = input.value.trim();
                if (q.length > 0) {
                    window.location.href = 'search.php?q=' + encodeURIComponent(q);
                }
            });
            input.addEventListener('keypress', function (e) {
                if (e.key === 'Enter') btn.click();
            });
        }
    })();

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    (function () {
        var toggleBtn = document.getElementById('sidebarToggle');
        if (!toggleBtn) return;

        toggleBtn.addEventListener('click', function () {
            var sidebar = document.getElementById('sidebar');
            if (sidebar) sidebar.classList.toggle('open');
        });

        document.addEventListener('click', function (e) {
            var sidebar = document.getElementById('sidebar');
            if (!sidebar) return;
            if (window.innerWidth <= 1024) {
                if (!sidebar.contains(e.target) && e.target !== toggleBtn) {
                    sidebar.classList.remove('open');
                }
            }
        });
    })();

    // ================================================================
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        if (!toast) return;
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');

        toast.className = 'toast-custom ' + (type || 'info');
        if (toastTitle)   toastTitle.textContent = title;
        if (toastMessage) toastMessage.textContent = message;
        toast.style.display = 'flex';

        setTimeout(function () { toast.classList.add('show'); }, 10);

        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function () {
            toast.classList.remove('show');
            setTimeout(function () { toast.style.display = 'none'; }, 400);
        }, 3500);
    }
</script>