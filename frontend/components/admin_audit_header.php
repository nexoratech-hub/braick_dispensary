<?php
// ================================================================
// FILE: frontend/components/admin_audit_header.php
// ADMIN AUDIT - HEADER (STANDARD BLUE THEME + BRANCH SELECTOR)
// ✅ Blue Theme ya kawaida (#0B5ED7) - SAME AS ADMIN HEADER
// ✅ Branch Selector inafanya kazi
// ✅ Dark mode support
// ✅ Date/Time display
// ✅ Kwa ADMIN tu
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? 0;
$full_name = $_SESSION['full_name'] ?? 'Admin';
$branch_id = $_SESSION['branch_id'] ?? 1;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? 'admin';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$user_role = $_SESSION['role'] ?? 'admin';

// ✅ Selected branch from GET (for selector)
$selected_branch_id = $_GET['branch'] ?? 'all';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$current_page = basename($_SERVER['PHP_SELF']);
$page_title = ucfirst(str_replace('.php', '', $current_page));
if (empty($page_title) || $page_title == '') {
    $page_title = 'Audit Dashboard';
}

$dark_mode = isset($_COOKIE['dark_mode']) ? $_COOKIE['dark_mode'] : 'false';

// ================================================================
// DATABASE CONNECTION
// ================================================================
if (!isset($db) || $db === null) {
    require_once __DIR__ . '/../../backend/config/database.php';
    try {
        $db = Database::getInstance()->getConnection();
    } catch (Exception $e) {
        error_log("Admin audit header DB error: " . $e->getMessage());
        $db = null;
    }
}

// ================================================================
// GET BRANCHES FOR SELECTOR
// ================================================================
$branches_list = [];
if ($db !== null) {
    try {
        $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
        $branches_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $branches_list = [];
    }
}

// GET SELECTED BRANCH NAME
$selected_branch_name = 'All Branches';
if ($selected_branch_id !== 'all') {
    foreach ($branches_list as $b) {
        if ($b['id'] == $selected_branch_id) {
            $selected_branch_name = $b['name'];
            break;
        }
    }
}

// GET UNREAD NOTIFICATIONS COUNT
$unread_notifications = 0;
if ($user_id > 0 && $db !== null) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    } catch (Exception $e) {
        $unread_notifications = 0;
    }
}

// SESSION CHECK - ADMIN ONLY
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
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
    <title>Braick Dispensary - Admin Audit <?= htmlspecialchars($page_title) ?></title>
    
    <!-- FAVICON -->
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="apple-touch-icon" href="<?= $logo_path ?>">
    
    <!-- EXTERNAL RESOURCES -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <!-- ✅ GOOGLE FONTS - POPPINS + INTER -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        /* ================================================================
           ✅ BLUE THEME VARIABLES - STANDARD BLUE
           ================================================================ */
        :root {
            --font-primary: 'Poppins', 'Inter', -apple-system, sans-serif;
            --font-number: 'Inter', 'Poppins', sans-serif;
            --font-mono: 'JetBrains Mono', 'Courier New', monospace;
            
            /* ✅ BLUE THEME (Standard) */
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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: var(--font-primary);
        }

        html {
            background: var(--page-bg-body);
            transition: background 0.3s ease;
            min-height: 100%;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        body {
            background: var(--page-bg-body);
            color: var(--page-text-primary);
            transition: background 0.3s ease, color 0.3s ease;
            min-height: 100vh;
        }

        html[data-theme="dark"] {
            background: #0F172A !important;
        }
        
        html[data-theme="dark"] body {
            background: #0F172A !important;
            color: #F1F5F9 !important;
        }

        /* ================================================================
           MAIN CONTENT
           ================================================================ */
        .main-content {
            margin-left: 260px;         /* ✅ Match sidebar width */
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
           TOP NAV - BLUE BORDER
           ================================================================ */
        .top-nav {
            position: fixed;
            top: 0;
            left: 260px;                /* ✅ Match sidebar width */
            right: 0;
            height: 72px;
            background: #FFFFFF;
            z-index: 40;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 22px;
            border-bottom: 2px solid var(--page-primary);
            transition: all 0.3s ease;
            box-shadow: 0 2px 12px rgba(11, 94, 215, 0.08);
            gap: 12px;
        }
        
        [data-theme="dark"] .top-nav {
            background: #1E293B;
            border-bottom-color: var(--page-primary-light);
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.3);
        }

        /* LEFT SIDE */
        .top-nav-left {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
            min-width: 0;
        }

        /* ✅ BRANCH SELECTOR - BLUE THEME */
        .branch-selector-wrapper {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #E8F0FE 0%, #DBEAFE 100%);
            border: 2px solid #BFDBFE;
            border-radius: 11px;
            padding: 0 12px;
            height: 40px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(11, 94, 215, 0.08);
            position: relative;
            min-width: 175px;
        }
        
        .branch-selector-wrapper:hover {
            background: linear-gradient(135deg, #DBEAFE 0%, #BFDBFE 100%);
            border-color: #60A5FA;
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.15);
            transform: translateY(-1px);
        }
        
        [data-theme="dark"] .branch-selector-wrapper {
            background: linear-gradient(135deg, #1E3A5F 0%, #1E40AF 100%);
            border-color: var(--page-primary-light);
            box-shadow: 0 2px 8px rgba(96, 165, 250, 0.15);
        }
        
        [data-theme="dark"] .branch-selector-wrapper:hover {
            background: linear-gradient(135deg, #1E40AF 0%, #1E3A8A 100%);
            border-color: var(--page-accent);
            box-shadow: 0 4px 12px rgba(96, 165, 250, 0.25);
        }
        
        .branch-selector-wrapper .branch-icon {
            color: var(--page-primary);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            background: rgba(11, 94, 215, 0.12);
            border-radius: 7px;
            flex-shrink: 0;
        }
        
        [data-theme="dark"] .branch-selector-wrapper .branch-icon {
            color: var(--page-accent);
            background: rgba(96, 165, 250, 0.18);
        }
        
        .branch-selector-wrapper select {
            border: none;
            background: transparent;
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--page-primary);
            outline: none;
            cursor: pointer;
            padding: 6px 20px 6px 0;
            width: 100%;
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 12 12'%3E%3Cpath fill='%230B5ED7' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 4px center;
            letter-spacing: 0.02em;
            font-family: var(--font-primary);
        }
        
        [data-theme="dark"] .branch-selector-wrapper select {
            color: #93C5FD;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 12 12'%3E%3Cpath fill='%2393C5FD' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
        }
        
        .branch-selector-wrapper select option {
            background: #FFFFFF;
            color: #0B5ED7;
            padding: 8px;
            font-weight: 600;
        }
        
        [data-theme="dark"] .branch-selector-wrapper select option {
            background: #1E293B;
            color: #F1F5F9;
        }

        /* SEARCH BAR */
        .top-nav .search-wrapper {
            display: flex;
            align-items: center;
            background: #F1F5F9;
            border-radius: 11px;
            border: 2px solid transparent;
            transition: all 0.3s ease;
            flex: 1;
            max-width: 380px;
            height: 40px;
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
            box-shadow: 0 0 0 4px rgba(96, 165, 250, 0.18);
        }
        
        .top-nav .search-wrapper .search-icon {
            color: #94A3B8;
            margin-left: 14px;
            font-size: 0.85rem;
        }
        
        .top-nav .search-wrapper input {
            border: none;
            background: transparent;
            padding: 10px 12px;
            width: 100%;
            font-size: 0.82rem;
            outline: none;
            color: #1E293B;
            font-weight: 500;
            font-family: var(--font-primary);
        }
        
        [data-theme="dark"] .top-nav .search-wrapper input { color: #F1F5F9; }
        
        .top-nav .search-wrapper input::placeholder {
            color: #94A3B8;
            font-weight: 400;
        }
        
        .top-nav .search-wrapper .search-btn {
            background: linear-gradient(135deg, var(--page-primary), var(--page-primary-dark));
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 0 9px 9px 0;
            cursor: pointer;
            font-size: 0.78rem;
            font-weight: 700;
            transition: all 0.3s ease;
            white-space: nowrap;
            height: 100%;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: var(--font-primary);
        }
        
        .top-nav .search-wrapper .search-btn:hover {
            background: linear-gradient(135deg, var(--page-primary-dark), #083D8A);
            transform: translateX(1px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.35);
        }

        /* RIGHT SIDE */
        .top-nav-right {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        /* ✅ DATE & TIME - BLUE THEME */
        .datetime-wrapper {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            background: linear-gradient(135deg, #E8F0FE 0%, #DBEAFE 100%);
            border: 2px solid #BFDBFE;
            border-radius: 11px;
            padding: 5px 12px;
            height: 40px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(11, 94, 215, 0.08);
        }
        
        .datetime-wrapper:hover {
            background: linear-gradient(135deg, #DBEAFE 0%, #BFDBFE 100%);
            border-color: #60A5FA;
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.15);
            transform: translateY(-1px);
        }
        
        [data-theme="dark"] .datetime-wrapper {
            background: linear-gradient(135deg, #1E3A5F 0%, #1E40AF 100%);
            border-color: var(--page-primary-light);
            box-shadow: 0 2px 8px rgba(96, 165, 250, 0.15);
        }
        
        [data-theme="dark"] .datetime-wrapper:hover {
            background: linear-gradient(135deg, #1E40AF 0%, #1E3A8A 100%);
            border-color: var(--page-accent);
            box-shadow: 0 4px 12px rgba(96, 165, 250, 0.25);
        }
        
        .datetime-wrapper .datetime-icon {
            color: var(--page-primary);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 26px;
            height: 26px;
            background: rgba(11, 94, 215, 0.12);
            border-radius: 7px;
        }
        
        [data-theme="dark"] .datetime-wrapper .datetime-icon {
            color: var(--page-accent);
            background: rgba(96, 165, 250, 0.18);
        }
        
        .datetime-wrapper .datetime-content {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }
        
        .datetime-wrapper .datetime-date {
            font-size: 0.65rem;
            font-weight: 700;
            color: var(--page-primary);
            text-transform: uppercase;
            letter-spacing: 0.03em;
            white-space: nowrap;
            font-family: var(--font-primary);
        }
        
        [data-theme="dark"] .datetime-wrapper .datetime-date { color: #93C5FD; }
        
        .datetime-wrapper .datetime-time {
            font-size: 0.72rem;
            font-weight: 600;
            color: var(--page-primary-dark);
            font-family: var(--font-mono);
            letter-spacing: 0.05em;
            white-space: nowrap;
        }
        
        [data-theme="dark"] .datetime-wrapper .datetime-time { color: #DBEAFE; }

        /* DARK MODE TOGGLE */
        .dark-toggle-btn {
            background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%);
            border: 2px solid #334155;
            border-radius: 11px;
            padding: 8px 14px;
            cursor: pointer;
            font-size: 0.78rem;
            font-weight: 700;
            color: #F1F5F9;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            height: 40px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            font-family: var(--font-primary);
        }
        
        .dark-toggle-btn:hover {
            background: linear-gradient(135deg, #334155 0%, #1E293B 100%);
            border-color: var(--page-primary);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.25);
        }
        
        .dark-toggle-btn i {
            font-size: 0.88rem;
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
            width: 40px;
            height: 40px;
            border-radius: 11px;
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
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.2);
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
            top: 7px;
            right: 7px;
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
            width: 40px;
            height: 40px;
            border-radius: 11px;
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

        /* ✅ ADMIN AUDIT BADGE - BLUE THEME */
        .audit-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: linear-gradient(135deg, var(--page-primary), var(--page-primary-dark));
            color: white;
            padding: 6px 14px;
            border-radius: 10px;
            font-size: 0.68rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            box-shadow: 0 3px 12px rgba(11, 94, 215, 0.35);
            margin-right: 4px;
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
        
        .audit-badge i { font-size: 0.72rem; }
        
        .audit-badge .audit-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #34D399;
            animation: pulse-dot-green 1.5s infinite;
            box-shadow: 0 0 8px rgba(52, 211, 153, 0.8);
        }
        
        @keyframes pulse-dot-green {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(0.8); }
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
            font-weight: 700; 
        }
        
        [data-theme="dark"] .footer .footer-brand { color: var(--page-accent); }

        /* RESPONSIVE */
        @media (max-width: 1200px) {
            .datetime-wrapper .datetime-date { font-size: 0.6rem; }
            .datetime-wrapper .datetime-time { font-size: 0.68rem; }
            .branch-selector-wrapper { min-width: 150px; }
        }
        
        @media (max-width: 1024px) {
            .top-nav { left: 0; padding: 0 16px; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 240px; }
            .datetime-wrapper { padding: 5px 10px; }
        }
        
        @media (max-width: 900px) {
            .datetime-wrapper { display: none; }
        }
        
        @media (max-width: 768px) {
            .top-nav { 
                height: 64px; 
                padding: 0 12px;
                gap: 8px;
            }
            .main-content { margin-top: 64px; }
            .top-nav .search-wrapper { 
                max-width: 140px; 
                height: 38px; 
            }
            .top-nav .search-wrapper input { font-size: 0.72rem; padding: 8px; }
            .top-nav .search-wrapper .search-btn { padding: 8px 10px; font-size: 0.68rem; }
            .top-nav .search-wrapper .search-btn span { display: none; }
            .top-nav .icon-btn { width: 38px; height: 38px; }
            .top-nav .avatar { width: 38px; height: 38px; }
            .dark-toggle-btn { padding: 6px 10px; height: 38px; font-size: 0.7rem; }
            .dark-toggle-btn span { display: none; }
            .audit-badge { padding: 4px 10px; font-size: 0.58rem; }
            .branch-selector-wrapper { 
                min-width: 130px; 
                height: 38px;
                padding: 0 10px;
            }
            .branch-selector-wrapper select { font-size: 0.7rem; }
        }
        
        @media (max-width: 640px) {
            .top-nav .search-wrapper { display: none; }
            .audit-badge span { display: none; }
            .branch-selector-wrapper { min-width: 110px; }
            .branch-selector-wrapper select { font-size: 0.66rem; }
        }
        
        @media (max-width: 480px) {
            .branch-selector-wrapper .branch-icon { display: none; }
            .branch-selector-wrapper { min-width: 90px; padding: 0 8px; }
        }
        
        /* ✅ UTILITY - NUMBER FONT */
        .font-number { 
            font-family: var(--font-number) !important; 
            font-variant-numeric: tabular-nums; 
        }
        .font-mono { 
            font-family: var(--font-mono) !important; 
            font-variant-numeric: tabular-nums; 
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- TOP NAVIGATION -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="top-nav-left">
        <button id="sidebarToggle" class="icon-btn" style="display:none;">
            <i class="fas fa-bars" style="font-size:1.1rem;"></i>
        </button>
        
        <!-- ✅ BRANCH SELECTOR -->
        <div class="branch-selector-wrapper">
            <span class="branch-icon">
                <i class="fas fa-store-alt"></i>
            </span>
            <select id="headerBranchSelector" onchange="switchHeaderBranch(this.value)">
                <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>
                    🌐 All Branches
                </option>
                <?php foreach ($branches_list as $branch): ?>
                    <option value="<?= $branch['id'] ?>" <?= $selected_branch_id == $branch['id'] ? 'selected' : '' ?>>
                        🏥 <?= htmlspecialchars($branch['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <!-- SEARCH -->
        <div class="search-wrapper">
            <i class="fas fa-search search-icon"></i>
            <input type="text" id="globalSearch" placeholder="Search audit logs, reports...">
            <button class="search-btn" onclick="performGlobalSearch()">
                <i class="fas fa-search"></i> <span>Search</span>
            </button>
        </div>
    </div>
    
    <div class="top-nav-right">
        <!-- Admin Audit Badge -->
        <span class="audit-badge">
            <span class="audit-dot"></span>
            <i class="fas fa-crown"></i>
            <span>ADMIN AUDIT</span>
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
        <a href="/dispensary_system/frontend/pages/admin/notifications.php" class="icon-btn" title="Notifications">
            <i class="fas fa-bell" style="font-size:1.05rem;"></i>
            <span class="notif-dot <?= $unread_notifications > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </a>
        
        <!-- Profile Avatar -->
        <a href="/dispensary_system/frontend/pages/admin/profile.php" title="Profile">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='<?= $logo_path ?>'">
        </a>
    </div>
</nav>

<script>
// ================================================================
// ✅ BRANCH SWITCHER
// ================================================================
function switchHeaderBranch(branchId) {
    var url = new URL(window.location.href);
    url.searchParams.set('branch', branchId);
    if (url.searchParams.has('id')) {
        url.searchParams.delete('id');
    }
    window.location.href = url.toString();
}

// ================================================================
// DARK MODE TOGGLE
// ================================================================
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
    
    var dateEl = document.getElementById('datetimeDate');
    var timeEl = document.getElementById('datetimeTime');
    if (dateEl) dateEl.textContent = dateStr;
    if (timeEl) timeEl.textContent = timeStr;
}
updateDateTime();
setInterval(updateDateTime, 1000);

// ================================================================
// GLOBAL SEARCH
// ================================================================
function performGlobalSearch() {
    var query = document.getElementById('globalSearch').value.trim();
    if (query.length > 0) {
        window.location.href = '/dispensary_system/frontend/pages/admin/audit/search_results.php?q=' + encodeURIComponent(query);
    }
}

document.getElementById('globalSearch')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') performGlobalSearch();
});

// ================================================================
// SIDEBAR TOGGLE (fallback)
// ================================================================
document.getElementById('sidebarToggle')?.addEventListener('click', function() {
    var sidebar = document.getElementById('sidebar');
    if (sidebar) sidebar.classList.toggle('open');
});

console.log('%c👑 Admin Audit Header - BLUE THEME + BRANCH SELECTOR', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
console.log('%c✅ Blue Theme: #0B5ED7 → #0A4CA8', 'font-size:13px; color:#3B82F6; font-weight:bold;');
console.log('%c✅ Accent: #60A5FA (Light Blue)', 'font-size:13px; color:#60A5FA;');
console.log('%c✅ Branch Selector: YES', 'font-size:13px; color:#34D399;');
console.log('%c👤 Admin: <?= htmlspecialchars($full_name) ?>', 'font-size:13px; color:#34D399;');
console.log('%c🏢 Branch: <?= htmlspecialchars($selected_branch_name) ?>', 'font-size:13px; color:#34D399;');
</script>