<?php
// ================================================================
// FILE: frontend/components/admin_header.php
// SUPER ADMIN - SHARED HEADER
// FAVICON: LOGO INAONEKANA
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// FAVICON - PATH KAMILI (Logo inaonekana)
// ================================================================
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// Get current page name
$current_page = basename($_SERVER['PHP_SELF']);
$page_title = ucfirst(str_replace('.php', '', $current_page));

if (empty($page_title) || $page_title == '') {
    $page_title = 'Dashboard';
}

$dark_mode = isset($_COOKIE['dark_mode']) ? $_COOKIE['dark_mode'] : 'false';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $dark_mode === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Braick Dispensary - <?= $page_title ?></title>
    
    <!-- ================================================================
         FAVICON - INAONEKANA 100%
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
           BRAICK DISPENSARY - DARK MODE VARIABLES
           ================================================================ */
        :root {
            --blue-600: #0B5ED7;
            --blue-700: #0B4EA8;
            --blue-800: #0B3D8A;
            --green-600: #059669;
            --green-700: #047857;
            
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --shadow: 0 1px 3px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.06);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.08);
            --input-bg: #FFFFFF;
            --input-border: #E2E8F0;
            --table-stripe: #E8F0FE;
            --table-hover: #D1FAE5;
            --modal-overlay: rgba(0,0,0,0.5);
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
            --input-bg: #1E293B;
            --input-border: #334155;
            --table-stripe: #1E293B;
            --table-hover: #1A3A2A;
            --modal-overlay: rgba(0,0,0,0.7);
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
        ::-webkit-scrollbar-thumb { background: #0B5ED7; border-radius: 10px; }
        
        /* ================================================================
           TOP NAV - DARK MODE SUPPORT
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
            transition: background 0.3s ease, border-color 0.3s ease;
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
            border-color: #0B5ED7;
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
            background: #0B5ED7;
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
            background: #0A4CA8;
        }
        
        .top-nav .branch-selector {
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 6px 12px;
            background: var(--bg-card);
            font-size: 0.82rem;
            font-weight: 500;
            cursor: pointer;
            outline: none;
            min-width: 160px;
            color: var(--text-primary);
            transition: all 0.3s;
        }
        
        .top-nav .branch-selector:focus {
            border-color: #0B5ED7;
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15);
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
            border-color: #0B5ED7;
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
            color: #0B5ED7;
        }
        
        .notif-dot {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 8px;
            height: 8px;
            background: #059669;
            border-radius: 50%;
            border: 2px solid var(--bg-nav);
            animation: pulse-dot 2s infinite;
        }
        
        @keyframes pulse-dot {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }
        
        /* Dark Mode Toggle Button */
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
            border-color: #0B5ED7;
            background: var(--bg-card);
        }
        
        .dark-toggle-btn i {
            font-size: 0.9rem;
        }
        
        /* ================================================================
           MAIN CONTENT
           ================================================================ */
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 24px 28px;
            min-height: calc(100vh - 68px);
            transition: background 0.3s ease;
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
        
        .stat-card.blue { background: #0B5ED7; }
        .stat-card.blue-dark { background: #0B3D8A; }
        .stat-card.blue-light { background: #1A73E8; }
        .stat-card.green { background: #059669; }
        .stat-card.green-dark { background: #047857; }
        .stat-card.green-light { background: #0AA84F; }
        
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
            border-color: #0B5ED7;
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
        
        .card-title .title-blue { color: #0B5ED7; }
        .card-title .title-green { color: #059669; }
        
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
            background: #0B5ED7;
            color: white;
        }
        .btn-blue:hover {
            background: #0A4CA8;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .btn-green {
            background: #059669;
            color: white;
        }
        .btn-green:hover {
            background: #047857;
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
            border-color: #0B5ED7;
            color: #0B5ED7;
        }
        
        .btn-sm { padding: 3px 10px; font-size: 0.7rem; border-radius: 6px; }
        
        /* ================================================================
           TABLES
           ================================================================ */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
        }
        
        .data-table th {
            text-align: left;
            padding: 8px 12px;
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.6rem;
            text-transform: uppercase;
            border-bottom: 2px solid var(--border-color);
        }
        
        .data-table td {
            padding: 8px 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        
        .data-table tr:hover td {
            background: var(--table-hover);
        }
        
        /* ================================================================
           BADGES
           ================================================================ */
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
        
        .badge-blue { background: #0B5ED7; }
        .badge-green { background: #059669; }
        .badge-gray { background: #64748B; }
        
        /* ================================================================
           PAGE HEADER
           ================================================================ */
        .page-header {
            border-bottom: 3px solid #0B5ED7;
            padding-bottom: 12px;
        }
        
        .page-header .page-title {
            color: #0B3D8A;
            font-size: 1.8rem;
            font-weight: 700;
        }
        
        [data-theme="dark"] .page-header .page-title {
            color: #6EA8FE;
        }
        
        .page-header .page-subtitle {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .page-header .branch-tag {
            background: #059669;
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
        
        .footer .footer-brand { color: #0B5ED7; font-weight: 600; }
        
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
        .toast-custom.success { background: #059669; }
        .toast-custom.error { background: #EF4444; }
        .toast-custom.info { background: #0B5ED7; }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .branch-selector { min-width: 120px; font-size: 0.7rem; }
            .top-nav .datetime { display: none; }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .stat-card .stat-number { font-size: 1.4rem; }
            .top-nav .search-wrapper { max-width: 120px; }
            .top-nav .search-wrapper .search-btn { padding: 8px 10px; font-size: 0.7rem; }
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