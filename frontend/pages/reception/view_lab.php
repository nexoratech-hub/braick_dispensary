<?php
// ================================================================
// FILE: frontend/pages/reception/view_lab.php
// RECEPTION - VIEW LAB TEST DETAILS
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// START SESSION
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
// CHECK USER ROLE
// ================================================================
$allowed_roles = ['reception', 'admin', 'doctor', 'laboratory', 'cashier'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// SESSION DATA
// ================================================================
$user_id = $_SESSION['user_id'] ?? 0;
$full_name = $_SESSION['full_name'] ?? 'User';
$role = $_SESSION['role'] ?? 'reception';
$branch_id = $_SESSION['branch_id'] ?? 1;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// PATH
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

// ================================================================
// GET PARAMETERS
// ================================================================
$lab_test_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($lab_test_id <= 0) {
    header('Location: lab_tests.php?error=invalid_id');
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // ================================================================
    // ✅ GET LAB TEST DETAILS
    // ================================================================
    $sql = "
        SELECT lt.*, 
               p.full_name as patient_name,
               p.patient_id as patient_code,
               p.phone as patient_phone,
               p.gender as patient_gender,
               p.date_of_birth as patient_dob,
               u_doctor.full_name as doctor_name,
               u_technician.full_name as technician_name,
               u_technician2.full_name as performed_by_name,
               v.visit_number,
               v.status as visit_status,
               b.name as branch_name
        FROM lab_tests lt
        LEFT JOIN patients p ON lt.patient_id = p.id
        LEFT JOIN users u_doctor ON lt.doctor_id = u_doctor.id
        LEFT JOIN users u_technician ON lt.lab_technician_id = u_technician.id
        LEFT JOIN users u_technician2 ON lt.performed_by = u_technician2.id
        LEFT JOIN visits v ON lt.visit_id = v.id
        LEFT JOIN branches b ON lt.branch_id = b.id
        WHERE lt.id = ?
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$lab_test_id]);
    $lab_test = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$lab_test) {
        header('Location: lab_tests.php?error=not_found');
        exit;
    }
    
    // ================================================================
    // ✅ GET TEST CATALOG DETAILS
    // ================================================================
    $test_catalog = null;
    if (!empty($lab_test['test_id'])) {
        $stmt = $db->prepare("
            SELECT * FROM lab_tests_catalog 
            WHERE id = ?
        ");
        $stmt->execute([$lab_test['test_id']]);
        $test_catalog = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // ================================================================
    // ✅ CALCULATE AGE
    // ================================================================
    $age = 'N/A';
    if (!empty($lab_test['patient_dob'])) {
        $dob = new DateTime($lab_test['patient_dob']);
        $now = new DateTime();
        $diff = $now->diff($dob);
        $age = $diff->y . ' yrs';
    }
    
    // ================================================================
    // ✅ GET STATUS BADGE
    // ================================================================
    function getStatusBadge($status) {
        $statuses = [
            'pending' => ['class' => 'warning', 'icon' => 'fa-clock', 'text' => 'Pending'],
            'in_progress' => ['class' => 'info', 'icon' => 'fa-spinner fa-spin', 'text' => 'In Progress'],
            'completed' => ['class' => 'success', 'icon' => 'fa-check-circle', 'text' => 'Completed'],
            'cancelled' => ['class' => 'danger', 'icon' => 'fa-times-circle', 'text' => 'Cancelled']
        ];
        return $statuses[$status] ?? $statuses['pending'];
    }
    
    $status_info = getStatusBadge($lab_test['status']);
    
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
    $lab_test = null;
}

// ================================================================
// GET UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    if (isset($_SESSION['user_id'])) {
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
        $unread_notifications = $stmt->fetch()['total'] ?? 0;
    }
} catch (Exception $e) {
    $unread_notifications = 0;
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once '../../components/reception_header.php';
include_once '../../components/reception_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Lab Test - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #2563EB;
            --primary-dark: #1D4ED8;
            --primary-light: #60A5FA;
            --primary-bg: #EFF6FF;
            --primary-gradient: linear-gradient(135deg, #2563EB, #1D4ED8);
            
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
            
            --info: #0891B2;
            --info-bg: #CFFAFE;
            
            --purple: #7C3AED;
            --purple-dark: #5B21B6;
            --purple-light: #A78BFA;
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
            
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow: 0 1px 3px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
            --shadow-xl: 0 20px 30px rgba(0,0,0,0.12);
            
            --bg-body: #F0F4F8;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --radius: 12px;
            --radius-lg: 18px;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --primary: #3B82F6;
            --primary-dark: #2563EB;
            --primary-bg: #1E3A5F;
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
            --purple-bg: #2D1B5F;
            --success-bg: #1A3A2A;
            --warning-bg: #3D2A1A;
            --danger-bg: #3A1A1A;
            --info-bg: #1A2A3A;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
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
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow-sm);
        }
        
        .top-nav .search-wrapper {
            display: flex;
            align-items: center;
            background: var(--bg-body);
            border-radius: var(--radius);
            border: 2px solid var(--border-color);
            transition: all 0.3s;
            flex: 1;
            max-width: 500px;
        }
        
        .top-nav .search-wrapper:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
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
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 0 var(--radius) var(--radius) 0;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .top-nav .search-wrapper .search-btn:hover {
            transform: scale(1.02);
        }
        
        .top-nav .datetime {
            font-size: 0.78rem;
            color: var(--text-secondary);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .top-nav .datetime i {
            color: var(--primary-light);
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
            border-radius: var(--radius);
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
        
        .dark-toggle-btn i { font-size: 0.9rem; }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        /* Page Header */
        .page-header {
            background: var(--primary-gradient);
            border-radius: var(--radius-lg);
            padding: 24px 32px;
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 8px 32px rgba(37, 99, 235, 0.25);
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -60%;
            right: -10%;
            width: 400px;
            height: 400px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
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
            font-size: 1.8rem;
            opacity: 0.9;
        }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 18px;
            border-radius: var(--radius);
            font-weight: 500;
            font-size: 0.82rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(4px);
            position: relative;
            z-index: 1;
        }
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            backdrop-filter: blur(4px);
        }
        
        /* Cards */
        .card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 24px 28px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            box-shadow: var(--shadow-md);
            margin-bottom: 20px;
        }
        
        .card:hover {
            border-color: var(--primary-light);
            box-shadow: var(--shadow-lg);
        }
        
        .card-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .card-title i {
            color: var(--primary);
        }
        
        .info-row {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            width: 140px;
            font-weight: 500;
            color: var(--text-secondary);
            flex-shrink: 0;
        }
        
        .info-value {
            flex: 1;
            color: var(--text-primary);
        }
        
        /* Badge */
        .badge {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .badge-success {
            background: var(--success-bg);
            color: var(--success);
        }
        
        .badge-warning {
            background: var(--warning-bg);
            color: var(--warning);
        }
        
        .badge-danger {
            background: var(--danger-bg);
            color: var(--danger);
        }
        
        .badge-info {
            background: var(--info-bg);
            color: var(--info);
        }
        
        .badge-purple {
            background: var(--purple-bg);
            color: var(--purple);
        }
        
        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.78rem;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        .btn-success:hover {
            background: var(--success-dark);
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
        
        /* Footer */
        .footer-modern {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        .footer-modern .footer-brand {
            color: var(--primary);
            font-weight: 500;
        }
        
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .card { padding: 16px; }
            .info-label { width: 100px; font-size: 0.8rem; }
            .info-value { font-size: 0.8rem; }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
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
        
        .live-indicator-modern {
            display: inline-block;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #34D399;
            animation: pulse-dot 1.5s infinite;
            margin-right: 4px;
        }
        
        .results-box {
            background: var(--bg-body);
            border-radius: var(--radius);
            padding: 16px 20px;
            border: 1px solid var(--border-color);
            font-family: 'Courier New', monospace;
            white-space: pre-wrap;
            word-wrap: break-word;
            min-height: 80px;
        }
        
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        @media (max-width: 768px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
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
            <input type="text" id="searchInput" placeholder="Search..." value="">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <span class="branch-badge-display">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($branch_name) ?>
        </span>
        
        <span class="datetime" id="currentDateTime">
            <i class="fas fa-clock" style="color:var(--primary-light);"></i>
            <span id="clockDisplay" style="font-weight:500;"><?= date('d M Y • h:i:s A') ?></span>
        </span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= ($unread_notifications ?? 0) > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <?php if (isset($lab_test) && $lab_test): ?>
    
    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-flask"></i>
                Lab Test Details
                <span class="role-badge-display"><?= strtoupper($role) ?></span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-vial"></i>
                Test #<?= $lab_test['id'] ?> for patient <strong><?= htmlspecialchars($lab_test['patient_name'] ?? 'Unknown') ?></strong>
                <span class="badge <?= 'badge-' . $status_info['class'] ?>">
                    <i class="fas <?= $status_info['icon'] ?>"></i>
                    <?= $status_info['text'] ?>
                </span>
            </p>
        </div>
        <div class="header-right" style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="lab_tests.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <?php if ($role === 'laboratory' && $lab_test['status'] !== 'completed'): ?>
                <a href="edit_lab_test.php?id=<?= $lab_test['id'] ?>" class="btn-outline-light">
                    <i class="fas fa-edit"></i> Edit
                </a>
            <?php endif; ?>
            <?php if ($lab_test['status'] === 'completed'): ?>
                <a href="#" onclick="window.print()" class="btn-outline-light">
                    <i class="fas fa-print"></i> Print
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- GRID: PATIENT INFO + TEST INFO -->
    <!-- ================================================================ -->
    <div class="grid-2">
        
        <!-- Patient Information -->
        <div class="card animate-fade-in-up">
            <div class="card-title">
                <i class="fas fa-user"></i> Patient Information
            </div>
            <div class="info-row">
                <span class="info-label">Full Name</span>
                <span class="info-value">
                    <a href="view_patient.php?id=<?= $lab_test['patient_id'] ?>" class="text-primary hover:underline">
                        <?= htmlspecialchars($lab_test['patient_name'] ?? 'Unknown') ?>
                    </a>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Patient ID</span>
                <span class="info-value" style="font-family:monospace;"><?= htmlspecialchars($lab_test['patient_code'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Gender</span>
                <span class="info-value"><?= htmlspecialchars($lab_test['patient_gender'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Age</span>
                <span class="info-value"><?= $age ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Phone</span>
                <span class="info-value">
                    <?php if (!empty($lab_test['patient_phone'])): ?>
                        <a href="tel:<?= htmlspecialchars($lab_test['patient_phone']) ?>" class="text-primary hover:underline">
                            <?= htmlspecialchars($lab_test['patient_phone']) ?>
                        </a>
                    <?php else: ?>
                        N/A
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Visit Number</span>
                <span class="info-value" style="font-family:monospace;">
                    <?= htmlspecialchars($lab_test['visit_number'] ?? 'N/A') ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Visit Status</span>
                <span class="info-value">
                    <span class="badge badge-<?= $lab_test['visit_status'] === 'completed' ? 'success' : 'warning' ?>">
                        <?= ucfirst($lab_test['visit_status'] ?? 'N/A') ?>
                    </span>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Branch</span>
                <span class="info-value"><?= htmlspecialchars($lab_test['branch_name'] ?? 'N/A') ?></span>
            </div>
        </div>
        
        <!-- Test Information -->
        <div class="card animate-fade-in-up" style="animation-delay:0.1s;">
            <div class="card-title">
                <i class="fas fa-flask"></i> Test Information
            </div>
            <div class="info-row">
                <span class="info-label">Test Name</span>
                <span class="info-value">
                    <strong><?= htmlspecialchars($lab_test['test_name'] ?? 'N/A') ?></strong>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Test Code</span>
                <span class="info-value" style="font-family:monospace;">
                    <?= htmlspecialchars($test_catalog['test_code'] ?? $lab_test['test_id'] ?? 'N/A') ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Category</span>
                <span class="info-value"><?= htmlspecialchars($test_catalog['category'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Price</span>
                <span class="info-value">
                    <strong>TSh <?= number_format($lab_test['test_price'] ?? 0, 2) ?></strong>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Sample Type</span>
                <span class="info-value"><?= htmlspecialchars($lab_test['sample_type'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Test Date</span>
                <span class="info-value">
                    <?= $lab_test['test_date'] ? date('d M Y', strtotime($lab_test['test_date'])) : 'Not set' ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Status</span>
                <span class="info-value">
                    <span class="badge <?= 'badge-' . $status_info['class'] ?>">
                        <i class="fas <?= $status_info['icon'] ?>"></i>
                        <?= $status_info['text'] ?>
                    </span>
                </span>
            </div>
            <?php if ($lab_test['status'] === 'completed'): ?>
                <div class="info-row">
                    <span class="info-label">Completed At</span>
                    <span class="info-value">
                        <?= $lab_test['completed_at'] ? date('d M Y h:i A', strtotime($lab_test['completed_at'])) : 'N/A' ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- DOCTOR & TECHNICIAN INFO -->
    <!-- ================================================================ -->
    <div class="grid-2">
        
        <!-- Doctor Information -->
        <div class="card animate-fade-in-up" style="animation-delay:0.15s;">
            <div class="card-title">
                <i class="fas fa-user-md"></i> Requesting Doctor
            </div>
            <div class="info-row">
                <span class="info-label">Doctor Name</span>
                <span class="info-value">
                    <?php if (!empty($lab_test['doctor_name'])): ?>
                        <a href="view_user.php?id=<?= $lab_test['doctor_id'] ?>" class="text-primary hover:underline">
                            <?= htmlspecialchars($lab_test['doctor_name']) ?>
                        </a>
                    <?php else: ?>
                        <span class="text-gray-400">Not assigned</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Requested At</span>
                <span class="info-value">
                    <?= $lab_test['created_at'] ? date('d M Y h:i A', strtotime($lab_test['created_at'])) : 'N/A' ?>
                </span>
            </div>
        </div>
        
        <!-- Technician Information -->
        <div class="card animate-fade-in-up" style="animation-delay:0.2s;">
            <div class="card-title">
                <i class="fas fa-microscope"></i> Lab Technician
            </div>
            <div class="info-row">
                <span class="info-label">Technician</span>
                <span class="info-value">
                    <?php if (!empty($lab_test['technician_name'])): ?>
                        <a href="view_user.php?id=<?= $lab_test['lab_technician_id'] ?>" class="text-primary hover:underline">
                            <?= htmlspecialchars($lab_test['technician_name']) ?>
                        </a>
                    <?php else: ?>
                        <span class="text-gray-400">Not assigned</span>
                    <?php endif; ?>
                </span>
            </div>
            <?php if (!empty($lab_test['performed_by_name'])): ?>
                <div class="info-row">
                    <span class="info-label">Performed By</span>
                    <span class="info-value"><?= htmlspecialchars($lab_test['performed_by_name']) ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($lab_test['started_at'])): ?>
                <div class="info-row">
                    <span class="info-label">Started At</span>
                    <span class="info-value">
                        <?= date('d M Y h:i A', strtotime($lab_test['started_at'])) ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RESULTS -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up" style="animation-delay:0.25s;">
        <div class="card-title">
            <i class="fas fa-file-medical-alt"></i> Test Results
            <?php if ($lab_test['status'] === 'completed'): ?>
                <span class="badge badge-success">
                    <i class="fas fa-check-circle"></i> Finalized
                </span>
            <?php else: ?>
                <span class="badge badge-warning">
                    <i class="fas fa-clock"></i> Pending Results
                </span>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($lab_test['results'])): ?>
            <div class="results-box">
                <?= nl2br(htmlspecialchars($lab_test['results'])) ?>
            </div>
        <?php else: ?>
            <div class="text-center py-6 text-gray-400">
                <i class="fas fa-file-alt text-3xl block mb-2"></i>
                <p>No results available yet</p>
                <p class="text-sm">Results will appear here once the test is completed</p>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($lab_test['interpretation'])): ?>
            <div class="mt-4">
                <h4 class="font-semibold text-sm text-gray-500 mb-2">Interpretation</h4>
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 text-sm">
                    <?= nl2br(htmlspecialchars($lab_test['interpretation'])) ?>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($lab_test['reference_range'])): ?>
            <div class="mt-3">
                <span class="text-sm text-gray-500">Reference Range: </span>
                <span class="text-sm font-medium"><?= htmlspecialchars($lab_test['reference_range']) ?></span>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($lab_test['notes'])): ?>
            <div class="mt-3">
                <span class="text-sm text-gray-500">Notes: </span>
                <span class="text-sm"><?= nl2br(htmlspecialchars($lab_test['notes'])) ?></span>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- ACTION BUTTONS -->
    <!-- ================================================================ -->
    <?php if ($role === 'laboratory' || $role === 'admin'): ?>
    <div class="flex flex-wrap gap-3 mt-4">
        <?php if ($lab_test['status'] === 'pending'): ?>
            <a href="start_lab_test.php?id=<?= $lab_test['id'] ?>" class="btn btn-primary">
                <i class="fas fa-play"></i> Start Test
            </a>
        <?php endif; ?>
        
        <?php if ($lab_test['status'] === 'in_progress'): ?>
            <a href="edit_lab_test.php?id=<?= $lab_test['id'] ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> Add Results
            </a>
            <a href="complete_lab_test.php?id=<?= $lab_test['id'] ?>" class="btn btn-success" onclick="return confirm('Mark this test as completed?')">
                <i class="fas fa-check"></i> Complete Test
            </a>
        <?php endif; ?>
        
        <?php if ($lab_test['status'] === 'completed'): ?>
            <a href="#" onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print"></i> Print Results
            </a>
            <a href="download_lab_result.php?id=<?= $lab_test['id'] ?>" class="btn btn-success">
                <i class="fas fa-download"></i> Download PDF
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php else: ?>
    
    <!-- ================================================================ -->
    <!-- ERROR - LAB TEST NOT FOUND -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-exclamation-triangle"></i>
                Lab Test Not Found
                <span class="role-badge-display">RECEPTION</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-flask"></i>
                The lab test you are looking for does not exist or has been deleted.
            </p>
        </div>
        <div class="header-right" style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="lab_tests.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to Lab Tests
            </a>
        </div>
    </div>
    
    <div class="card text-center py-12">
        <i class="fas fa-flask text-6xl text-gray-300 block mb-4"></i>
        <h2 class="text-xl font-semibold text-gray-600">Lab Test Not Found</h2>
        <p class="text-gray-400 mt-2">The requested lab test could not be found in the system.</p>
        <a href="lab_tests.php" class="btn btn-primary mt-4">
            <i class="fas fa-arrow-left"></i> Return to Lab Tests
        </a>
    </div>
    
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer-modern">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Lab Test Details
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp"><?= date('h:i:s A') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // CLOCK
    // ================================================================
    function updateClock() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var el = document.getElementById('clockDisplay');
        if (el) {
            el.textContent = dateStr + ' • ' + timeStr;
        }
        var footerTimestamp = document.getElementById('footerTimestamp');
        if (footerTimestamp) {
            footerTimestamp.textContent = timeStr;
        }
    }
    setInterval(updateClock, 1000);
    updateClock();

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
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            window.location.href = 'lab_tests.php?search=' + encodeURIComponent(query);
        } else {
            window.location.href = 'lab_tests.php';
        }
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

    console.log('%c🔬 Braick - View Lab Test', 'font-size:18px; font-weight:bold; color:#2563EB;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📋 Lab Test ID: <?= $lab_test_id ?>', 'font-size:13px; color:#64748B;');
    console.log('%c👤 Patient: <?= htmlspecialchars($lab_test['patient_name'] ?? 'Unknown') ?>', 'font-size:13px; color:#2563EB;');
    console.log('%c📊 Status: <?= $status_info['text'] ?? 'Unknown' ?>', 'font-size:13px; color:#2563EB;');
</script>

</body>
</html>