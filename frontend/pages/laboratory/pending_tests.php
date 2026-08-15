<?php
// ================================================================
// FILE: frontend/pages/laboratory/pending_tests.php
// LABORATORY - PENDING TESTS (USING lab_tests TABLE)
// WITH REAL-TIME AUTO-UPDATE (3 SECONDS)
// WITH FULL LOGIN SESSION PROTECTION
// BRAICK DISPENSARY
// ================================================================

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION - CHECK IF USER IS LOGGED IN
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    // User is not logged in - redirect to login
    header('Location: ../login.php');
    exit;
}

// ================================================================
// CHECK IF USER IS LABORATORY OR ADMIN
// ================================================================
if ($_SESSION['role'] !== 'laboratory' && $_SESSION['role'] !== 'admin') {
    // User is not laboratory - redirect to their dashboard
    $role = $_SESSION['role'];
    switch ($role) {
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET LAB TECHNICIAN INFO FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Lab Technician';
$user_role = $_SESSION['role'];
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_specialty = $_SESSION['specialty'] ?? 'Laboratory';
$user_username = $_SESSION['username'] ?? 'lab.technician';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// IF ADMIN VIEWING LAB PAGE, USE THEIR BRANCH
// ================================================================
if ($_SESSION['role'] === 'admin') {
    $user_branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : $user_branch_id;
}

// ================================================================
// INCLUDE DATABASE - CORRECT PATH
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die('Database connection error: ' . $e->getMessage());
}

// ================================================================
// GET FILTERS
// ================================================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$message = isset($_GET['message']) ? trim($_GET['message']) : '';
$message_type = isset($_GET['type']) ? trim($_GET['type']) : 'info';

// ================================================================
// BUILD QUERY - Get pending tests (status NULL or 'pending')
// ================================================================
$query = "
    SELECT lt.*, 
           p.full_name as patient_name, p.patient_id, p.phone,
           u.full_name as doctor_name, u.specialty,
           v.visit_number,
           TIMESTAMPDIFF(MINUTE, lt.created_at, NOW()) as waiting_time
    FROM lab_tests lt
    JOIN visits v ON lt.visit_id = v.id
    JOIN patients p ON v.patient_id = p.id
    JOIN users u ON lt.doctor_id = u.id
    WHERE lt.branch_id = ? AND (lt.status IS NULL OR lt.status = 'pending')
";

$params = [$user_branch_id];

if (!empty($search)) {
    $query .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR lt.test_name LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($date_filter)) {
    $query .= " AND DATE(lt.created_at) = ?";
    $params[] = $date_filter;
}

$query .= " ORDER BY lt.created_at ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET COUNTS
// ================================================================
$stmt = $db->prepare("
    SELECT COUNT(*) as count FROM lab_tests 
    WHERE branch_id = ? AND (status IS NULL OR status = 'pending')
");
$stmt->execute([$user_branch_id]);
$pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$stmt = $db->prepare("
    SELECT COUNT(*) as count FROM lab_tests 
    WHERE branch_id = ? AND status = 'in_progress'
");
$stmt->execute([$user_branch_id]);
$in_progress_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$today = date('Y-m-d');
$stmt = $db->prepare("
    SELECT COUNT(*) as count FROM lab_tests 
    WHERE branch_id = ? AND status = 'completed' AND DATE(completed_at) = ?
");
$stmt->execute([$user_branch_id, $today]);
$completed_today_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// ================================================================
// PROFILE PICTURE
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

// Logo path
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE HEADER & SIDEBAR - CORRECT PATHS
// ================================================================
include_once __DIR__ . '/../../components/laboratory_header.php';
include_once __DIR__ . '/../../components/laboratory_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Tests - Laboratory</title>
    
    <link rel="icon" href="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" type="image/png">
    
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
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --purple: #7C3AED;
            --purple-bg: #EDE9FE;
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
            --radius: 10px;
            --radius-lg: 14px;
            --transition: all 0.3s ease;
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
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
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            background: var(--bg-body);
            color: var(--text-primary);
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            margin: 0;
            padding: 0;
            line-height: 1.6;
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        /* ================================================================
           TOP NAV (same as before)
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
        
        .notif-dot {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            border: 2px solid var(--bg-nav);
            animation: pulse-dot 2s infinite;
        }
        
        .notif-dot.has-notif { background: var(--danger); }
        .notif-dot.no-notif { background: var(--gray-400); animation: none; }
        
        @keyframes pulse-dot {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
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
        
        .branch-badge {
            display: inline-block;
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
        
        .role-badge {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        /* ================================================================
           MAIN CONTENT
           ================================================================ */
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        /* ================================================================
           PAGE HEADER
           ================================================================ */
        .page-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.25);
            position: relative;
            overflow: hidden;
        }
        
        .page-header .page-title {
            color: white;
            font-size: 1.6rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-title i {
            font-size: 2rem;
            opacity: 0.9;
        }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .update-badge {
            font-size: 0.65rem;
            color: var(--text-secondary);
            background: var(--bg-body);
            padding: 2px 12px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        /* ================================================================
           STATS CARDS
           ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 14px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 14px 18px;
            border: 2px solid var(--border-color);
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
        }
        
        .stat-card .number {
            font-size: 1.6rem;
            font-weight: 700;
            line-height: 1.2;
        }
        
        .stat-card .number.pending { color: #D97706; }
        .stat-card .number.in-progress { color: #0B5ED7; }
        .stat-card .number.completed { color: #059669; }
        .stat-card .number.total { color: #7C3AED; }
        
        .stat-card .label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        /* ================================================================
           CARD
           ================================================================ */
        .card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            border: 1px solid var(--border-color);
            transition: var(--transition);
            box-shadow: var(--shadow);
        }
        
        .card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        [data-theme="dark"] .card {
            background: var(--gray-800);
            border-color: var(--gray-700);
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
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .card-title .title-blue { color: var(--primary); }
        
        .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            padding-top: 12px;
            border-top: 1px solid var(--border-color);
            margin-top: 12px;
        }
        
        .form-control {
            padding: 4px 10px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.8rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
        }
        
        /* ================================================================
           TABLE
           ================================================================ */
        .table-wrap {
            overflow-x: auto;
            max-height: 500px;
            overflow-y: auto;
        }
        
        .table-wrap::-webkit-scrollbar {
            width: 5px;
            height: 5px;
        }
        
        .table-wrap::-webkit-scrollbar-track {
            background: var(--bg-body);
            border-radius: 4px;
        }
        
        .table-wrap::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 4px;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
            min-width: 800px;
        }
        
        .data-table thead th {
            text-align: left;
            padding: 8px 12px;
            font-weight: 700;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: white;
            background: #0B5ED7;
            border-bottom: 3px solid #0A4CA8;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .data-table thead th:first-child {
            border-radius: 8px 0 0 0;
        }
        
        .data-table thead th:last-child {
            border-radius: 0 8px 0 0;
        }
        
        .data-table td {
            padding: 8px 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .test-row:hover td {
            background: var(--primary-bg);
        }
        
        [data-theme="dark"] .test-row:hover td {
            background: #1E3A5F;
        }
        
        /* ================================================================
           BADGES & STATUS
           ================================================================ */
        .status-badge {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 12px;
            border-radius: 12px;
        }
        
        .status-badge.pending {
            background: #FEF3C7;
            color: #D97706;
        }
        
        .status-badge.in_progress {
            background: #E8F0FE;
            color: #0B5ED7;
        }
        
        .status-badge.completed {
            background: #D1FAE5;
            color: #059669;
        }
        
        .status-badge.cancelled {
            background: #FEE2E2;
            color: #DC2626;
        }
        
        [data-theme="dark"] .status-badge.pending {
            background: #3D2E0A;
            color: #FBBF24;
        }
        
        [data-theme="dark"] .status-badge.in_progress {
            background: #1E3A5F;
            color: #6EA8FE;
        }
        
        [data-theme="dark"] .status-badge.completed {
            background: #1A3A2A;
            color: #34D399;
        }
        
        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 5px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.7rem;
            transition: all 0.3s ease;
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
            transform: scale(1.05);
        }
        
        .btn-green {
            background: #059669;
            color: white;
        }
        
        .btn-green:hover {
            background: #047857;
            transform: scale(1.05);
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
        
        .btn-sm {
            padding: 3px 8px;
            font-size: 0.65rem;
            border-radius: 4px;
        }
        
        /* ================================================================
           WAITING TIME
           ================================================================ */
        .waiting-time {
            font-size: 0.7rem;
            font-weight: 500;
        }
        
        .waiting-time.long {
            color: #DC2626;
        }
        
        .waiting-time.medium {
            color: #D97706;
        }
        
        /* ================================================================
           EMPTY STATE
           ================================================================ */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--border-color);
            display: block;
            margin-bottom: 10px;
        }
        
        /* ================================================================
           TOAST
           ================================================================ */
        .toast-custom {
            position: fixed;
            bottom: 30px;
            right: 30px;
            padding: 14px 22px;
            border-radius: var(--radius);
            z-index: 9999;
            max-width: 380px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s ease;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #ffffff;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        }
        
        .toast-custom.show {
            transform: translateY(0);
            opacity: 1;
        }
        
        .toast-custom.success {
            background: var(--success);
        }
        .toast-custom.error {
            background: var(--danger);
        }
        .toast-custom.info {
            background: var(--primary);
        }
        .toast-custom.warning {
            background: var(--warning);
        }
        
        /* ================================================================
           FOOTER
           ================================================================ */
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand {
            color: var(--primary);
            font-weight: 600;
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .data-table { font-size: 0.7rem; min-width: 650px; }
            .data-table thead th,
            .data-table td { padding: 6px 8px; }
            .card-footer { flex-direction: column; align-items: center; text-align: center; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .page-header .page-title { font-size: 1.1rem; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
            .stat-card { padding: 10px 12px; }
            .stat-card .number { font-size: 1.2rem; }
            .btn { padding: 2px 6px; font-size: 0.55rem; }
            .data-table td { padding: 4px 6px; font-size: 0.65rem; }
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
            <input type="text" id="searchInput" placeholder="Search pending tests..." value="<?= htmlspecialchars($search) ?>">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    <div class="flex items-center gap-3">
        <span class="branch-badge"><i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($user_branch_name) ?></span>
        <span class="datetime" id="currentDateTime"></span>
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= $unread_notifications > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        <a href="profile.php">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($user_full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-clock mr-2" style="color: #D97706;"></i> Pending Tests
                <span class="role-badge ml-2">LABORATORY</span>
                <span class="update-badge ml-2" id="updateBadge">
                    <i class="fas fa-sync-alt fa-spin"></i> Live
                </span>
            </h1>
            <p class="page-subtitle">
                Manage all pending laboratory tests
                <span class="ml-2 inline-flex bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full text-xs border border-yellow-200">
                    <i class="fas fa-clock mr-1"></i> <?= $pending_count ?> Pending
                </span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-spinner mr-1"></i> <?= $in_progress_count ?> In Progress
                </span>
                <span class="ml-2 inline-flex bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs border border-green-200">
                    <i class="fas fa-check-circle mr-1"></i> <?= $completed_today_count ?> Completed Today
                </span>
            </p>
        </div>
        <div>
            <a href="dashboard.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-grid">
        <div class="stat-card">
            <p class="number pending" id="statPending"><?= $pending_count ?></p>
            <p class="label">⏳ Pending Tests</p>
        </div>
        <div class="stat-card">
            <p class="number in-progress" id="statInProgress"><?= $in_progress_count ?></p>
            <p class="label">🔬 In Progress</p>
        </div>
        <div class="stat-card">
            <p class="number completed" id="statCompletedToday"><?= $completed_today_count ?></p>
            <p class="label">✅ Completed Today</p>
        </div>
        <div class="stat-card">
            <p class="number total" id="statTotal"><?= count($tests) ?></p>
            <p class="label">📋 Total Pending</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-sm font-medium text-gray-600 mr-2">Date:</span>
            <input type="date" id="dateFilter" value="<?= $date_filter ?>"
                   onchange="window.location.href='pending_tests.php?date='+this.value+'&search=<?= urlencode($search) ?>'"
                   class="form-control" style="width:auto;padding:4px 10px;font-size:0.8rem;">
            
            <?php if (!empty($search)): ?>
                <a href="pending_tests.php" class="btn btn-outline btn-sm">
                    <i class="fas fa-times"></i> Clear Search
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- TESTS TABLE -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-blue mr-2"></i> Pending Tests
                <span class="text-sm font-normal text-gray-400" id="testCount">(<?= count($tests) ?>)</span>
            </h3>
            <span class="text-sm text-gray-400">Scroll to view all</span>
        </div>
        
        <div class="table-wrap">
            <table class="data-table" id="testTable">
                <thead>
                    <tr>
                        <th style="border-radius: 8px 0 0 0;">#</th>
                        <th>Test Name</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Visit #</th>
                        <th>Status</th>
                        <th>Waiting</th>
                        <th>Requested</th>
                        <th style="border-radius: 0 8px 0 0;">Actions</th>
                    </tr>
                </thead>
                <tbody id="testTableBody">
                    <?php if (count($tests) > 0): ?>
                        <?php $i = 1; foreach ($tests as $test): 
                            $waiting = $test['waiting_time'] ?? 0;
                            $waiting_class = $waiting > 60 ? 'long' : ($waiting > 30 ? 'medium' : '');
                            $waiting_text = $waiting < 1 ? 'Just now' : ($waiting < 60 ? $waiting . ' min' : floor($waiting / 60) . 'h ' . ($waiting % 60) . 'm');
                            $status = $test['status'] ?? 'pending';
                            $color = '#' . substr(md5($test['patient_name'] ?? 'Unknown'), 0, 6);
                        ?>
                            <tr class="test-row" data-id="<?= $test['id'] ?>">
                                <td><?= $i++ ?></td>
                                <td>
                                    <div class="font-medium text-sm"><?= htmlspecialchars($test['test_name']) ?></div>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($test['test_type'] ?? 'N/A') ?></div>
                                </td>
                                <td>
                                    <div class="font-medium text-sm"><?= htmlspecialchars($test['patient_name']) ?></div>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($test['patient_id'] ?? 'N/A') ?></div>
                                </td>
                                <td>
                                    <div class="text-sm">Dr. <?= htmlspecialchars($test['doctor_name']) ?></div>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($test['specialty'] ?? 'GP') ?></div>
                                </td>
                                <td class="font-mono text-xs"><?= htmlspecialchars($test['visit_number'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="status-badge <?= $status ?>">
                                        <?= $status === 'pending' ? '⏳ Pending' : ucfirst($status) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="waiting-time <?= $waiting_class ?>"><?= $waiting_text ?></span>
                                </td>
                                <td class="text-xs"><?= date('M d, Y h:i A', strtotime($test['created_at'])) ?></td>
                                <td>
                                    <div class="flex gap-1">
                                        <a href="view_test.php?id=<?= $test['id'] ?>" class="btn btn-blue btn-sm" title="View & Update">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="view_test.php?id=<?= $test['id'] ?>&action=start" class="btn btn-outline btn-sm" title="Start" style="border-color:#0B5ED7;color:#0B5ED7;">
                                            <i class="fas fa-play"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9">
                                <div class="empty-state">
                                    <i class="fas fa-check-circle" style="color: #059669; font-size: 3rem;"></i>
                                    <p>No pending tests found</p>
                                    <p class="text-sm mt-1">All tests have been processed</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="card-footer">
            <span class="text-sm text-gray-500">
                <i class="fas fa-flask mr-1"></i> 
                Showing <strong id="recordCount"><?= count($tests) ?></strong> pending test(s)
            </span>
            <span class="text-sm text-gray-500">
                <i class="fas fa-store-alt mr-1"></i> 
                Branch: <strong><?= htmlspecialchars($user_branch_name) ?></strong>
            </span>
            <span class="text-sm text-gray-500">
                <i class="fas fa-clock mr-1"></i> 
                <span id="footerTimestamp">Last updated: <?= date('h:i:s A') ?></span>
            </span>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Pending Tests
            <span class="text-gray-300 mx-2">|</span>
            Logged in as: <strong><?= htmlspecialchars($user_full_name) ?></strong>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle"></i>
    <div>
        <p id="toastTitle">Notification</p>
        <p id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE
    // ================================================================
    var darkModeToggle = document.getElementById('darkModeToggle');
    var darkIcon = document.getElementById('darkIcon');
    var darkText = document.getElementById('darkText');
    var htmlElement = document.documentElement;
    
    var savedDarkMode = localStorage.getItem('darkMode');
    if (savedDarkMode === 'true') {
        htmlElement.setAttribute('data-theme', 'dark');
        darkIcon.className = 'fas fa-sun';
        darkText.textContent = 'Light';
    }
    
    darkModeToggle?.addEventListener('click', function() {
        var isDark = htmlElement.getAttribute('data-theme') === 'dark';
        if (isDark) {
            htmlElement.removeAttribute('data-theme');
            darkIcon.className = 'fas fa-moon';
            darkText.textContent = 'Dark';
            localStorage.setItem('darkMode', 'false');
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
        }
    });

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    sidebarToggle?.addEventListener('click', function() {
        sidebar.classList.toggle('open');
    });
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
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
        document.getElementById('currentDateTime').textContent = dateStr + ' • ' + timeStr;
        document.getElementById('footerTimestamp').textContent = 'Last updated: ' + timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        var date = '<?= $date_filter ?>';
        window.location.href = 'pending_tests.php?search=' + encodeURIComponent(query) + '&date=' + date;
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

    // ================================================================
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        
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

    // ================================================================
    // AUTO-UPDATE (3 SECONDS)
    // ================================================================
    var updateInterval = null;
    var isUpdating = false;
    var lastHash = null;
    
    function fetchAndUpdate() {
        if (isUpdating) return;
        isUpdating = true;
        
        var date = '<?= $date_filter ?>';
        var search = '<?= urlencode($search) ?>';
        var url = 'get_lab_tests_stats.php?status=pending&date=' + date + '&search=' + search + '&t=' + new Date().getTime();
        
        fetch(url)
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(function(data) {
                if (data.success) {
                    if (lastHash !== data.hash) {
                        lastHash = data.hash;
                        updateTable(data);
                        
                        document.getElementById('statPending').textContent = data.pending_count || 0;
                        document.getElementById('statInProgress').textContent = data.in_progress_count || 0;
                        document.getElementById('statCompletedToday').textContent = data.completed_today_count || 0;
                        document.getElementById('statTotal').textContent = data.total || 0;
                        document.getElementById('testCount').textContent = '(' + (data.total || 0) + ')';
                        document.getElementById('recordCount').textContent = data.total || 0;
                        
                        var now = new Date();
                        var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                        document.getElementById('footerTimestamp').textContent = 'Last updated: ' + timeStr;
                        document.getElementById('updateBadge').innerHTML = '<i class="fas fa-check-circle" style="color:#34D399;"></i> Live ' + timeStr;
                    }
                }
                isUpdating = false;
            })
            .catch(function(error) {
                console.error('Error fetching pending tests:', error);
                document.getElementById('updateBadge').innerHTML = '<i class="fas fa-exclamation-circle" style="color:#EF4444;"></i> Error';
                isUpdating = false;
            });
    }
    
    function updateTable(data) {
        var tbody = document.getElementById('testTableBody');
        var tests = data.tests || [];
        
        if (tests.length > 0) {
            var html = '';
            var i = 1;
            tests.forEach(function(test) {
                var waiting = test.waiting_time || 0;
                var waitingClass = waiting > 60 ? 'long' : (waiting > 30 ? 'medium' : '');
                var waitingText = waiting < 1 ? 'Just now' : (waiting < 60 ? waiting + ' min' : Math.floor(waiting / 60) + 'h ' + (waiting % 60) + 'm');
                var status = test.status || 'pending';
                
                html += `
                    <tr class="test-row" data-id="${test.id}">
                        <td>${i++}</td>
                        <td>
                            <div class="font-medium text-sm">${escapeHtml(test.test_name)}</div>
                            <div class="text-xs text-gray-400">${escapeHtml(test.test_type || 'N/A')}</div>
                        </td>
                        <td>
                            <div class="font-medium text-sm">${escapeHtml(test.patient_name)}</div>
                            <div class="text-xs text-gray-400">${escapeHtml(test.patient_id || 'N/A')}</div>
                        </td>
                        <td>
                            <div class="text-sm">Dr. ${escapeHtml(test.doctor_name)}</div>
                            <div class="text-xs text-gray-400">${escapeHtml(test.specialty || 'GP')}</div>
                        </td>
                        <td class="font-mono text-xs">${escapeHtml(test.visit_number || 'N/A')}</td>
                        <td>
                            <span class="status-badge ${status}">
                                ${status === 'pending' ? '⏳ Pending' : status === 'in_progress' ? '🔬 In Progress' : status}
                            </span>
                        </td>
                        <td>
                            <span class="waiting-time ${waitingClass}">${waitingText}</span>
                        </td>
                        <td class="text-xs">${formatDate(test.created_at)}</td>
                        <td>
                            <div class="flex gap-1">
                                <a href="view_test.php?id=${test.id}" class="btn btn-blue btn-sm" title="View & Update">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="view_test.php?id=${test.id}&action=start" class="btn btn-outline btn-sm" title="Start" style="border-color:#0B5ED7;color:#0B5ED7;">
                                    <i class="fas fa-play"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        } else {
            tbody.innerHTML = `
                <tr>
                    <td colspan="9">
                        <div class="empty-state">
                            <i class="fas fa-check-circle" style="color: #059669; font-size: 3rem;"></i>
                            <p>No pending tests found</p>
                            <p class="text-sm mt-1">All tests have been processed</p>
                        </div>
                    </td>
                </tr>
            `;
        }
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function formatDate(datetime) {
        if (!datetime) return 'N/A';
        var d = new Date(datetime);
        if (isNaN(d.getTime())) return 'N/A';
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) + ' ' +
               d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    }
    
    function startAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
        }
        fetchAndUpdate();
        updateInterval = setInterval(fetchAndUpdate, 3000);
    }
    
    function stopAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
            updateInterval = null;
        }
    }
    
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopAutoUpdate();
        } else {
            startAutoUpdate();
        }
    });
    
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            searchInput?.focus();
            searchInput?.select();
        }
    });
    
    startAutoUpdate();

    console.log('%c🧪 Braick - Pending Tests (Auto-Update)', 'font-size:18px; font-weight:bold; color:#D97706;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?> (ID: <?= $user_id ?>)', 'font-size:13px; color:#64748B;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($user_branch_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Pending: <?= $pending_count ?> | In Progress: <?= $in_progress_count ?> | Completed Today: <?= $completed_today_count ?>', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>