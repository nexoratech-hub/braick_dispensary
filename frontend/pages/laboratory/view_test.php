<?php
// ================================================================
// FILE: frontend/pages/laboratory/view_test.php
// LABORATORY - VIEW TEST DETAILS
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION - Lab Technician
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'laboratory') {
    $_SESSION['user_id'] = 8;
    $_SESSION['full_name'] = 'Lab Technician Dodoma';
    $_SESSION['role'] = 'laboratory';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'lab.dodoma';
    $_SESSION['is_admin'] = false;
}

$user_id = $_SESSION['user_id'] ?? 8;
$user_full_name = $_SESSION['full_name'] ?? 'Lab Technician Dodoma';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';

// ================================================================
// GET LAB TEST ID
// ================================================================
$lab_test_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($lab_test_id <= 0) {
    header('Location: in_progress.php?error=invalid_id');
    exit;
}

// ================================================================
// INCLUDE CONFIG
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

$db = getDB();
$message = '';
$message_type = '';
$lab_test = null;

// ================================================================
// GET LAB TEST DETAILS
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT lt.*, 
               p.full_name as patient_name,
               p.patient_id as patient_code,
               p.phone,
               p.gender,
               p.date_of_birth,
               p.address,
               p.blood_group,
               u.full_name as doctor_name,
               u.specialty,
               v.visit_number,
               v.visit_type,
               v.diagnosis,
               v.symptoms,
               lr.request_number,
               lr.status as request_status,
               lr.created_at as request_date,
               (
                   SELECT COUNT(*) FROM lab_tests WHERE visit_id = lt.visit_id
               ) as total_tests,
               (
                   SELECT COUNT(*) FROM lab_tests WHERE visit_id = lt.visit_id AND status = 'completed'
               ) as completed_tests
        FROM lab_tests lt
        JOIN visits v ON lt.visit_id = v.id
        JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON lt.doctor_id = u.id
        LEFT JOIN lab_requests lr ON lt.visit_id = lr.visit_id
        WHERE lt.id = ? AND lt.branch_id = ?
    ");
    $stmt->execute([$lab_test_id, $user_branch_id]);
    $lab_test = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$lab_test) {
        header('Location: in_progress.php?error=test_not_found');
        exit;
    }
    
} catch (Exception $e) {
    header('Location: in_progress.php?error=database_error');
    exit;
}

// ================================================================
// GET STATISTICS
// ================================================================
$stats = [];
try {
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_tests,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tests,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_tests,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tests
        FROM lab_tests 
        WHERE visit_id = ?
    ");
    $stmt->execute([$lab_test['visit_id']]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $stats = ['total_tests' => 0, 'completed_tests' => 0, 'pending_tests' => 0, 'in_progress_tests' => 0];
}

// ✅ FIX: Use null coalescing operator to avoid undefined array key errors
$total_tests = $stats['total_tests'] ?? 0;
$completed_tests = $stats['completed_tests'] ?? 0;
$pending_tests = $stats['pending_tests'] ?? 0;
$in_progress_tests = $stats['in_progress_tests'] ?? 0;
$completed_percentage = ($total_tests > 0) ? round(($completed_tests / $total_tests) * 100, 1) : 0;

// ================================================================
// HELPER FUNCTIONS
// ================================================================
function getStatusBadgeClass($status) {
    $map = [
        'pending' => 'badge-warning',
        'in_progress' => 'badge-info',
        'completed' => 'badge-success',
        'cancelled' => 'badge-danger'
    ];
    return $map[$status] ?? 'badge-warning';
}

function getStatusLabel($status) {
    $map = [
        'pending' => '⏳ Pending',
        'in_progress' => '🔄 In Progress',
        'completed' => '✅ Completed',
        'cancelled' => '❌ Cancelled'
    ];
    return $map[$status] ?? ucfirst($status);
}

function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}

function formatDate($datetime) {
    if (empty($datetime)) return 'N/A';
    return date('d/m/Y h:i A', strtotime($datetime));
}

// ================================================================
// UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {}

// ================================================================
// PROFILE PICTURE
// ================================================================
$profile_pic = $_SESSION['profile_pic'] ?? '';
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/laboratory_header.php';
include_once __DIR__ . '/../../components/laboratory_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Test - Laboratory</title>
    
    <link rel="icon" href="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
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
        
        .branch-badge-display {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            background: var(--success-bg);
            color: var(--success);
        }
        
        [data-theme="dark"] .branch-badge-display {
            background: #1A3A2A;
            color: #34D399;
        }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
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
        
        .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .btn-outline-light {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 18px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.82rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(4px);
        }
        
        .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }
        
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
        
        .card-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .card-title .title-blue { color: var(--primary); }
        .card-title .title-green { color: var(--success); }
        .card-title .title-purple { color: #7C3AED; }
        
        .detail-row {
            display: flex;
            padding: 6px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .detail-row:last-child { border-bottom: none; }
        
        .detail-label {
            font-weight: 600;
            color: var(--text-secondary);
            width: 140px;
            flex-shrink: 0;
            font-size: 0.8rem;
        }
        
        .detail-value {
            flex: 1;
            color: var(--text-primary);
            font-size: 0.85rem;
        }
        
        .badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        
        .badge-success { background: var(--success-bg); color: var(--success); }
        .badge-warning { background: var(--warning-bg); color: var(--warning); }
        .badge-info { background: var(--primary-bg); color: var(--primary); }
        .badge-danger { background: var(--danger-bg); color: var(--danger); }
        .badge-purple { background: #EDE9FE; color: #7C3AED; }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 20px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.8rem;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            text-decoration: none;
            font-family: inherit;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
            box-shadow: 0 2px 8px rgba(11, 94, 215, 0.2);
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(11, 94, 215, 0.3);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
            box-shadow: 0 2px 8px rgba(5, 150, 105, 0.2);
        }
        
        .btn-success:hover {
            background: var(--success-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(5, 150, 105, 0.3);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        
        .btn-outline:hover {
            background: var(--gray-50);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .btn-sm {
            padding: 4px 12px;
            font-size: 0.7rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .stat-box {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 14px 16px;
            border: 2px solid var(--border-color);
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .stat-box:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
        }
        
        .stat-box .number {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .stat-box .number.green { color: var(--success); }
        .stat-box .number.orange { color: var(--warning); }
        .stat-box .number.purple { color: #7C3AED; }
        .stat-box .label { font-size: 0.7rem; color: var(--text-secondary); font-weight: 500; margin-top: 2px; }
        
        .alert {
            padding: 14px 20px;
            border-radius: var(--radius);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.9rem;
            border: 1px solid transparent;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .alert-success { background: var(--success-bg); color: var(--success); border-color: var(--success); }
        .alert-error { background: var(--danger-bg); color: var(--danger); border-color: var(--danger); }
        .alert-warning { background: var(--warning-bg); color: var(--warning); border-color: var(--warning); }
        .alert-info { background: var(--primary-bg); color: var(--primary); border-color: var(--primary); }
        
        .result-box {
            background: var(--bg-body);
            border-radius: var(--radius);
            padding: 16px 20px;
            border: 1px solid var(--border-color);
            white-space: pre-wrap;
            font-family: monospace;
            font-size: 0.85rem;
            max-height: 400px;
            overflow-y: auto;
        }
        
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
        
        [data-theme="dark"] .footer {
            border-color: var(--gray-700);
            color: var(--gray-400);
        }
        
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
        
        .toast-custom.show { transform: translateY(0); opacity: 1; }
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }
        
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .card { padding: 14px 16px; }
            .detail-row { flex-direction: column; }
            .detail-label { width: 100%; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .result-box { font-size: 0.7rem; max-height: 250px; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .card { padding: 10px 12px; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>

<!-- TOP NAVIGATION -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <input type="text" id="searchInput" placeholder="Search...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    <div class="flex items-center gap-3">
        <span class="branch-badge-display">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($user_branch_name) ?>
        </span>
        <span class="datetime" id="currentDateTime"></span>
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
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
                <i class="fas fa-flask"></i>
                Test Details
                <span class="role-badge-display">LABORATORY</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-prescription"></i>
                <strong><?= htmlspecialchars($lab_test['test_name'] ?? 'N/A') ?></strong>
                <span class="separator">|</span>
                Patient: <strong><?= htmlspecialchars($lab_test['patient_name'] ?? 'N/A') ?></strong>
                <span class="separator">|</span>
                Status: <span class="badge <?= getStatusBadgeClass($lab_test['status'] ?? 'pending') ?>">
                    <?= getStatusLabel($lab_test['status'] ?? 'pending') ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <?php if (($lab_test['status'] ?? '') !== 'completed'): ?>
                <a href="add_result.php?id=<?= $lab_test['id'] ?>" class="btn-outline-light">
                    <i class="fas fa-edit"></i> Add Result
                </a>
            <?php endif; ?>
            <a href="in_progress.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-box">
            <p class="number purple"><?= $total_tests ?></p>
            <p class="label">Total Tests</p>
        </div>
        <div class="stat-box">
            <p class="number green"><?= $completed_tests ?></p>
            <p class="label">Completed</p>
        </div>
        <div class="stat-box">
            <p class="number orange"><?= $pending_tests ?></p>
            <p class="label">Pending</p>
        </div>
        <div class="stat-box">
            <p class="number purple"><?= $completed_percentage ?>%</p>
            <p class="label">Completion Rate</p>
        </div>
    </div>

    <!-- Test Details -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        
        <!-- Left: Patient Info -->
        <div class="card">
            <h3 class="card-title">
                <i class="fas fa-user-circle title-blue mr-2"></i>
                Patient Information
            </h3>
            
            <div class="detail-row">
                <span class="detail-label">Full Name</span>
                <span class="detail-value"><strong><?= htmlspecialchars($lab_test['patient_name'] ?? 'N/A') ?></strong></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Patient ID</span>
                <span class="detail-value"><?= htmlspecialchars($lab_test['patient_code'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Gender</span>
                <span class="detail-value"><?= htmlspecialchars($lab_test['gender'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Date of Birth</span>
                <span class="detail-value"><?= !empty($lab_test['date_of_birth']) ? date('d/m/Y', strtotime($lab_test['date_of_birth'])) : 'N/A' ?> (<?= calculateAge($lab_test['date_of_birth'] ?? '') ?> yrs)</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Phone</span>
                <span class="detail-value"><?= htmlspecialchars($lab_test['phone'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Blood Group</span>
                <span class="detail-value"><?= htmlspecialchars($lab_test['blood_group'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Address</span>
                <span class="detail-value"><?= htmlspecialchars($lab_test['address'] ?? 'N/A') ?></span>
            </div>
        </div>
        
        <!-- Right: Test Info -->
        <div class="card">
            <h3 class="card-title">
                <i class="fas fa-flask title-purple mr-2"></i>
                Test Information
            </h3>
            
            <div class="detail-row">
                <span class="detail-label">Test Name</span>
                <span class="detail-value"><strong><?= htmlspecialchars($lab_test['test_name'] ?? 'N/A') ?></strong></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Test Price</span>
                <span class="detail-value">TSh <?= number_format($lab_test['test_price'] ?? 0, 2) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Status</span>
                <span class="detail-value">
                    <span class="badge <?= getStatusBadgeClass($lab_test['status'] ?? 'pending') ?>">
                        <?= getStatusLabel($lab_test['status'] ?? 'pending') ?>
                    </span>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Visit Number</span>
                <span class="detail-value"><?= htmlspecialchars($lab_test['visit_number'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Doctor</span>
                <span class="detail-value">Dr. <?= htmlspecialchars($lab_test['doctor_name'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Request Number</span>
                <span class="detail-value"><?= htmlspecialchars($lab_test['request_number'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Request Date</span>
                <span class="detail-value"><?= formatDate($lab_test['request_date'] ?? $lab_test['created_at'] ?? '') ?></span>
            </div>
            <?php if (!empty($lab_test['completed_at'])): ?>
                <div class="detail-row">
                    <span class="detail-label">Completed</span>
                    <span class="detail-value"><?= formatDate($lab_test['completed_at']) ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($lab_test['diagnosis'])): ?>
                <div class="detail-row">
                    <span class="detail-label">Diagnosis</span>
                    <span class="detail-value"><?= htmlspecialchars($lab_test['diagnosis']) ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Result -->
    <div class="card mt-5">
        <h3 class="card-title">
            <i class="fas fa-file-medical-alt title-green mr-2"></i>
            Result
            <?php if (($lab_test['status'] ?? '') === 'completed'): ?>
                <span class="badge badge-success ml-2">✅ Completed</span>
            <?php elseif (($lab_test['status'] ?? '') === 'in_progress'): ?>
                <span class="badge badge-warning ml-2">🔄 In Progress</span>
            <?php else: ?>
                <span class="badge badge-warning ml-2">⏳ Pending</span>
            <?php endif; ?>
        </h3>
        
        <?php if (!empty($lab_test['results'])): ?>
            <div class="result-box">
                <?= nl2br(htmlspecialchars($lab_test['results'])) ?>
            </div>
            <?php if (!empty($lab_test['notes'])): ?>
                <div class="mt-3 text-sm text-gray-500">
                    <i class="fas fa-sticky-note"></i> <strong>Notes:</strong> <?= htmlspecialchars($lab_test['notes']) ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="text-center py-8 text-gray-400">
                <i class="fas fa-flask text-3xl block mb-2"></i>
                <p>No result entered yet</p>
                <p class="text-sm mt-1">Result will appear here once completed</p>
                <?php if (($lab_test['status'] ?? '') !== 'completed'): ?>
                    <a href="add_result.php?id=<?= $lab_test['id'] ?>" class="btn btn-primary mt-3">
                        <i class="fas fa-edit"></i> Add Result
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Actions -->
    <?php if (($lab_test['status'] ?? '') !== 'completed'): ?>
        <div class="card mt-5" style="border-color:var(--primary-light);background:var(--primary-bg);">
            <h3 class="card-title">
                <i class="fas fa-cogs title-blue mr-2"></i>
                Actions
            </h3>
            <div class="flex flex-wrap gap-3">
                <a href="add_result.php?id=<?= $lab_test['id'] ?>" class="btn btn-success">
                    <i class="fas fa-edit"></i> Add Result
                </a>
                <a href="in_progress.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            View Test
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- Toast -->
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
    // Dark Mode
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

    // Sidebar Toggle
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    sidebarToggle?.addEventListener('click', function() {
        sidebar.classList.toggle('open');
    });

    // Date & Time
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

    // Toast
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        if (!toast) return;
        toast.className = 'toast-custom ' + type;
        toastTitle.textContent = title;
        toastMessage.textContent = message;
        toast.style.display = 'flex';
        toast.classList.add('show');
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 5000);
    }

    console.log('%c🔬 View Test - Laboratory', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Test: <?= htmlspecialchars($lab_test['test_name'] ?? 'N/A') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c👤 Patient: <?= htmlspecialchars($lab_test['patient_name'] ?? 'N/A') ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Status: <?= $lab_test['status'] ?? 'pending' ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c✅ Total Tests: <?= $total_tests ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c✅ Completed: <?= $completed_tests ?>', 'font-size:13px; color:#059669;');
    console.log('%c✅ Completion Rate: <?= $completed_percentage ?>%', 'font-size:13px; color:#059669;');
    <?php if (($lab_test['status'] ?? '') === 'completed'): ?>
        console.log('%c💾 Result: <?= substr($lab_test['results'] ?? '', 0, 100) ?>...', 'font-size:13px; color:#34D399;');
    <?php endif; ?>
</script>

</body>
</html>