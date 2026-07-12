<?php
// ================================================================
// FILE: frontend/components/doctor_header.php
// DOCTOR - SHARED HEADER (WITH PROFILE PICTURE SUPPORT)
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// SESSION DATA - Ensure doctor session exists
// ================================================================
if (!isset($_SESSION['doctor_id'])) {
    $_SESSION['doctor_id'] = 1;
    $_SESSION['full_name'] = 'Dr. Sarah Mwamba';
    $_SESSION['role'] = 'doctor';
    $_SESSION['branch_id'] = 1;
    $_SESSION['specialty'] = 'Cardiology';
    $_SESSION['profile_pic'] = '';
}

// ================================================================
// GET DOCTOR PROFILE PICTURE
// ================================================================
$doctor_id = $_SESSION['doctor_id'] ?? 1;
$full_name = $_SESSION['full_name'] ?? 'Dr. Unknown';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$specialty = $_SESSION['specialty'] ?? 'General Practitioner';

// Build avatar URL
$avatar_url = '';
$show_initial = true;
$initial = strtoupper(substr($full_name, 0, 1));

// Check if profile picture exists in session and file system
if (!empty($profile_pic)) {
    $file_path = $_SERVER['DOCUMENT_ROOT'] . '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic;
    if (file_exists($file_path)) {
        $avatar_url = '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic;
        $show_initial = false;
    } else {
        // File doesn't exist, clear session
        $_SESSION['profile_pic'] = '';
        $profile_pic = '';
    }
}

// ================================================================
// FAVICON - LOGO
// ================================================================
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// Get current page name
$current_page = basename($_SERVER['PHP_SELF']);
$page_title = ucfirst(str_replace('.php', '', $current_page));
if (empty($page_title) || $page_title == '') {
    $page_title = 'Dashboard';
}

// Check dark mode from cookie
$dark_mode = isset($_COOKIE['dark_mode']) ? $_COOKIE['dark_mode'] : 'false';
$is_dark = $dark_mode === 'true';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $is_dark ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Braick Dispensary - Doctor <?= $page_title ?></title>
    
    <!-- ================================================================
         FAVICON
         ================================================================ -->
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="apple-touch-icon" href="<?= $logo_path ?>">
    
    <!-- ================================================================
         EXTERNAL RESOURCES
         ================================================================ -->
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
        }
        
        .avatar-link:hover {
            transform: scale(1.05);
        }
        
        .avatar-link .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            background: var(--bg-card);
        }
        
        .avatar-link:hover .avatar {
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
        }
        
        .avatar-link:hover .avatar-placeholder {
            border-color: var(--primary);
            transform: scale(1.05);
        }
        
        /* Online status indicator on avatar */
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
            animation: none;
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
           STAT CARDS
           ================================================================ */
        .stat-card {
            border-radius: 20px;
            padding: 18px 20px;
            border: none;
            transition: all 0.3s;
            color: white;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .stat-card.blue { background: var(--primary); }
        .stat-card.blue-dark { background: var(--primary-dark); }
        .stat-card.green { background: var(--green); }
        .stat-card.green-dark { background: var(--green-dark); }
        .stat-card.orange { background: var(--orange); }
        .stat-card.purple { background: var(--purple); }
        
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
        }
        
        .stat-card .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: white;
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
        }
        
        /* ================================================================
           CARDS
           ================================================================ */
        .card {
            background: var(--bg-card);
            border-radius: 20px;
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
        
        /* ================================================================
           BUTTONS
           ================================================================ */
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
            background: var(--green);
            color: white;
        }
        .btn-green:hover {
            background: var(--green-dark);
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
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .btn-sm { padding: 3px 10px; font-size: 0.7rem; border-radius: 6px; }
        
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
           APPOINTMENT ITEMS
           ================================================================ */
        .appointment-item {
            display: flex;
            align-items: center;
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-color);
            transition: all 0.2s ease;
            border-radius: 8px;
        }
        
        .appointment-item:hover {
            background: var(--bg-table-hover);
        }
        
        .appointment-item:last-child {
            border-bottom: none;
        }
        
        .appointment-time {
            font-weight: 600;
            font-size: 0.8rem;
            color: var(--text-primary);
            min-width: 70px;
        }
        
        .appointment-patient {
            flex: 1;
            font-weight: 500;
            font-size: 0.85rem;
            color: var(--text-primary);
        }
        
        .appointment-type {
            font-size: 0.7rem;
            color: var(--text-secondary);
            padding: 2px 10px;
            border-radius: 12px;
            background: var(--bg-body);
        }
        
        .appointment-status {
            font-size: 0.65rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 12px;
        }
        
        .appointment-status.completed { background: #ECFDF5; color: var(--green); }
        .appointment-status.confirmed { background: #EFF6FF; color: var(--primary); }
        .appointment-status.scheduled { background: #FEF3C7; color: var(--orange); }
        .appointment-status.pending { background: #FEF3C7; color: var(--orange); }
        .appointment-status.cancelled { background: #FEE2E2; color: var(--red); }
        
        [data-theme="dark"] .appointment-status.completed { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .appointment-status.confirmed { background: #1E3A5F; color: #6EA8FE; }
        [data-theme="dark"] .appointment-status.scheduled { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .appointment-status.pending { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .appointment-status.cancelled { background: #3A1A1A; color: #F87171; }
        
        /* ================================================================
           ACTIVITY ITEMS
           ================================================================ */
        .activity-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 12px;
            border-bottom: 1px solid var(--border-color);
            transition: all 0.2s ease;
            border-radius: 8px;
        }
        
        .activity-item:hover {
            background: var(--bg-table-hover);
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            flex-shrink: 0;
        }
        
        .activity-icon.blue { background: #EFF6FF; color: var(--primary); }
        .activity-icon.green { background: #ECFDF5; color: var(--green); }
        .activity-icon.yellow { background: #FEF3C7; color: var(--orange); }
        .activity-icon.purple { background: #F3E8FF; color: var(--purple); }
        
        [data-theme="dark"] .activity-icon.blue { background: #1E3A5F; color: #6EA8FE; }
        [data-theme="dark"] .activity-icon.green { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .activity-icon.yellow { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .activity-icon.purple { background: #2A1A3A; color: #9B4DCA; }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-content .action {
            font-weight: 500;
            font-size: 0.85rem;
            color: var(--text-primary);
        }
        
        .activity-content .details {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }
        
        .activity-time {
            font-size: 0.65rem;
            color: var(--text-secondary);
        }
        
        /* ================================================================
           PATIENT ITEMS
           ================================================================ */
        .patient-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 12px;
            border-bottom: 1px solid var(--border-color);
            transition: all 0.2s ease;
            border-radius: 8px;
        }
        
        .patient-item:hover {
            background: var(--bg-table-hover);
        }
        
        .patient-item:last-child {
            border-bottom: none;
        }
        
        .patient-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.8rem;
            color: white;
            flex-shrink: 0;
        }
        
        .patient-info {
            flex: 1;
        }
        
        .patient-info .name {
            font-weight: 500;
            font-size: 0.85rem;
            color: var(--text-primary);
        }
        
        .patient-info .id {
            font-size: 0.65rem;
            color: var(--text-secondary);
            font-family: monospace;
        }
        
        .patient-info .phone {
            font-size: 0.65rem;
            color: var(--text-secondary);
        }
        
        .patient-last-visit {
            font-size: 0.65rem;
            color: var(--text-secondary);
        }
        
        /* ================================================================
           QUICK ACTIONS
           ================================================================ */
        .quick-action {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 16px 12px;
            border-radius: 12px;
            border: 2px solid var(--border-color);
            background: var(--bg-card);
            transition: all 0.3s ease;
            text-decoration: none;
            color: var(--text-primary);
            cursor: pointer;
        }
        
        .quick-action:hover {
            border-color: var(--primary);
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(11, 94, 215, 0.12);
        }
        
        .quick-action i {
            font-size: 1.5rem;
            margin-bottom: 6px;
        }
        
        .quick-action .label {
            font-size: 0.7rem;
            font-weight: 500;
            text-align: center;
        }
        
        .quick-action .icon-blue { color: var(--primary); }
        .quick-action .icon-green { color: var(--green); }
        .quick-action .icon-purple { color: var(--purple); }
        .quick-action .icon-orange { color: var(--orange); }
        
        /* ================================================================
           CHART
           ================================================================ */
        .chart-container {
            height: 200px !important;
            max-height: 200px !important;
        }
        
        .chart-container canvas {
            height: 100% !important;
            max-height: 200px !important;
        }
        
        /* ================================================================
           TOAST
           ================================================================ */
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
        .toast-custom.success { background: var(--green); }
        .toast-custom.error { background: var(--red); }
        .toast-custom.info { background: var(--primary); }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .sidebar-toggle-btn { display: block; }
            .top-nav .search-wrapper { max-width: 300px; }
            .main-content { margin-left: 0; }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .top-nav .status-toggle { display: none; }
            .main-content { padding: 16px; }
            .appointment-item { flex-wrap: wrap; gap: 4px; }
            .appointment-time { min-width: 60px; }
            .patient-item { flex-wrap: wrap; }
            .chart-container { height: 160px !important; }
            .stat-card .stat-number { font-size: 1.4rem; }
        }
        
        @media (max-width: 640px) {
            .top-nav { padding: 0 12px; }
            .top-nav .search-wrapper { max-width: 100px; }
            .top-nav .search-wrapper .search-btn { padding: 8px 8px; font-size: 0.65rem; }
            .top-nav .search-wrapper .search-btn span { display: none; }
            .dark-toggle-btn { padding: 4px 8px; font-size: 0.7rem; }
            .dark-toggle-btn span { display: none; }
            .main-content { padding: 10px; }
            .page-header .page-title { font-size: 1.2rem; }
            .avatar-link .avatar { width: 32px; height: 32px; }
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
        
        /* ================================================================
           GET USER COLOR FUNCTION (for avatar placeholder)
           ================================================================ */
        .avatar-color-1 { background: #0B5ED7; }
        .avatar-color-2 { background: #059669; }
        .avatar-color-3 { background: #7C3AED; }
        .avatar-color-4 { background: #DC2626; }
        .avatar-color-5 { background: #D97706; }
        .avatar-color-6 { background: #0D9488; }
        .avatar-color-7 { background: #DB2777; }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- TOP NAVIGATION -->
<!-- ================================================================ -->
<nav class="top-nav">
    
    <!-- Left Side -->
    <div class="flex items-center gap-3 flex-1">
        <button id="sidebarToggle" class="sidebar-toggle-btn" aria-label="Toggle Sidebar">
            <i class="fas fa-bars"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <input type="text" id="searchInput" placeholder="Search patients, doctors..." autocomplete="off">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> <span>Search</span>
            </button>
        </div>
    </div>
    
    <!-- Right Side -->
    <div class="flex items-center gap-3">
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="statusToggle" class="status-toggle" title="Toggle Status">
            <span class="status-dot online" id="statusDot"></span>
            <span id="statusText">Online</span>
        </button>
        
        <!-- Dark Mode Button -->
        <button id="darkModeToggle" class="dark-toggle-btn" title="Toggle Dark Mode">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn" id="notifBtn" title="Notifications">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot" id="notifDot" style="display: none;"></span>
        </button>
        
        <!-- ================================================================
             USER AVATAR - Shows profile picture or initial
             ================================================================ -->
        <a href="profile.php" class="avatar-link" title="Profile">
            <?php if ($show_initial): ?>
                <div class="avatar-placeholder avatar-color-<?= (abs(crc32($full_name)) % 7) + 1 ?>">
                    <?= $initial ?>
                </div>
            <?php else: ?>
                <img src="<?= $avatar_url ?>" alt="Profile" class="avatar">
            <?php endif; ?>
            <span class="status-ring" id="avatarStatusRing"></span>
        </a>
        
    </div>
</nav>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE TOGGLE - FULLY WORKING
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
            if (darkIcon) darkIcon.className = 'fas fa-sun';
            if (darkText) darkText.textContent = 'Light';
        }
        
        if (darkModeToggle) {
            darkModeToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var isDark = htmlElement.getAttribute('data-theme') === 'dark';
                
                if (isDark) {
                    htmlElement.removeAttribute('data-theme');
                    if (darkIcon) darkIcon.className = 'fas fa-moon';
                    if (darkText) darkText.textContent = 'Dark';
                    setCookie('dark_mode', 'false', 365);
                } else {
                    htmlElement.setAttribute('data-theme', 'dark');
                    if (darkIcon) darkIcon.className = 'fas fa-sun';
                    if (darkText) darkText.textContent = 'Light';
                    setCookie('dark_mode', 'true', 365);
                }
            });
        }
    })();

    // ================================================================
    // ONLINE STATUS TOGGLE
    // ================================================================
    (function() {
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
    })();

    // ================================================================
    // SEARCH FUNCTIONALITY
    // ================================================================
    (function() {
        var searchBtn = document.getElementById('searchBtn');
        var searchInput = document.getElementById('searchInput');
        
        function performSearch() {
            var query = searchInput.value.trim();
            if (query.length > 0) {
                window.location.href = '../search.php?q=' + encodeURIComponent(query) + '&module=doctor';
            } else {
                searchInput.focus();
                searchInput.style.borderColor = '#EF4444';
                setTimeout(function() {
                    searchInput.style.borderColor = '';
                }, 2000);
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
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            var searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }
        if (e.key === 'Escape') {
            var searchInput = document.getElementById('searchInput');
            if (searchInput && document.activeElement === searchInput) {
                searchInput.value = '';
                searchInput.blur();
            }
        }
        if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
            e.preventDefault();
            var darkBtn = document.getElementById('darkModeToggle');
            if (darkBtn) {
                darkBtn.click();
            }
        }
    });

    console.log('%c👨‍⚕️ Braick - Doctor Header (With Profile Picture)', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📸 Profile Picture: <?= !empty($profile_pic) ? '✅ Loaded' : '❌ Using Initial' ?>', 'font-size:12px; color:#059669;');
    console.log('%c🌙 Dark Mode: ' + (document.documentElement.getAttribute('data-theme') === 'dark' ? 'ON' : 'OFF'), 'font-size:12px; color:#6EA8FE;');
    console.log('%c🔍 Ctrl+K to search | Ctrl+D to toggle dark mode', 'font-size:12px; color:#64748B;');
</script>

</body>
</html>