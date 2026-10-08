<?php
// ================================================================
// FILE: frontend/pages/reception/lab_tests.php
// RECEPTION - VIEW LAB TEST DETAILS
// ✅ FIXED: Uses visit_id and test_id to view lab test details
// ✅ Redirects to assign_doctor.php if no valid data
// ✅ USING SHARED HEADER & SIDEBAR
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
        default:         header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// SESSION DATA
// ================================================================
$user_id     = $_SESSION['user_id'] ?? 0;
$full_name   = $_SESSION['full_name'] ?? 'User';
$role        = $_SESSION['role'] ?? 'reception';
$branch_id   = $_SESSION['branch_id'] ?? 1;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

// ================================================================
// TIME AGO (guard against redeclaration)
// ================================================================
if (!function_exists('time_ago')) {
    function time_ago($timestamp) {
        if (empty($timestamp)) return 'Just now';
        $time = strtotime($timestamp);
        $diff = time() - $time;
        if ($diff < 60)     return 'Just now';
        if ($diff < 3600)   return floor($diff / 60) . 'm ago';
        if ($diff < 86400)  return floor($diff / 3600) . 'h ago';
        if ($diff < 604800) return floor($diff / 86400) . 'd ago';
        return date('M d, Y', $time);
    }
}

// ================================================================
// GET PARAMETERS
// ================================================================
$lab_test_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$patient_id  = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$visit_id    = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;

// ✅ If no ID provided, redirect to assign_doctor.php
if ($lab_test_id <= 0 && $patient_id <= 0 && $visit_id <= 0) {
    header('Location: assign_doctor.php');
    exit;
}

// ✅ If patient_id or visit_id is provided but no id, get the latest lab test
if ($lab_test_id <= 0) {
    try {
        $db = Database::getInstance()->getConnection();

        if ($visit_id > 0) {
            $stmt = $db->prepare("SELECT id FROM lab_tests WHERE visit_id = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$visit_id]);
        } elseif ($patient_id > 0) {
            $stmt = $db->prepare("SELECT id FROM lab_tests WHERE patient_id = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$patient_id]);
        } else {
            header('Location: assign_doctor.php');
            exit;
        }

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $lab_test_id = $result['id'];
        } else {
            header('Location: assign_doctor.php?error=no_lab_test');
            exit;
        }
    } catch (Exception $e) {
        header('Location: assign_doctor.php?error=db_error');
        exit;
    }
}

// ================================================================
// STATUS BADGE HELPER (guard against redeclaration)
// ================================================================
if (!function_exists('getStatusBadge')) {
    function getStatusBadge($status) {
        $statuses = [
            'pending'     => ['class' => 'warning', 'icon' => 'fa-clock',           'text' => 'Pending'],
            'in_progress' => ['class' => 'info',    'icon' => 'fa-spinner fa-spin', 'text' => 'In Progress'],
            'completed'   => ['class' => 'success', 'icon' => 'fa-check-circle',    'text' => 'Completed'],
            'cancelled'   => ['class' => 'danger',  'icon' => 'fa-times-circle',    'text' => 'Cancelled']
        ];
        return $statuses[$status] ?? $statuses['pending'];
    }
}

// ================================================================
// FETCH LAB TEST DETAILS
// ================================================================
$lab_test = null;
$test_catalog = null;
$visit_lab_tests = [];
$age = 'N/A';
$status_info = getStatusBadge('pending');
$error_message = '';

try {
    $db = Database::getInstance()->getConnection();

    // ---------- GET LAB TEST DETAILS ----------
    $sql = "
        SELECT lt.*, 
               p.full_name as patient_name,
               p.patient_id as patient_code,
               p.phone as patient_phone,
               p.gender as patient_gender,
               p.date_of_birth as patient_dob,
               p.address as patient_address,
               p.blood_group as patient_blood_group,
               p.allergies as patient_allergies,
               u_doctor.full_name as doctor_name,
               u_technician.full_name as technician_name,
               u_technician2.full_name as performed_by_name,
               v.visit_number,
               v.status as visit_status,
               b.name as branch_name,
               ltc.test_code,
               ltc.category as test_category
        FROM lab_tests lt
        LEFT JOIN patients p ON lt.patient_id = p.id
        LEFT JOIN users u_doctor ON lt.doctor_id = u_doctor.id
        LEFT JOIN users u_technician ON lt.lab_technician_id = u_technician.id
        LEFT JOIN users u_technician2 ON lt.performed_by = u_technician2.id
        LEFT JOIN visits v ON lt.visit_id = v.id
        LEFT JOIN branches b ON lt.branch_id = b.id
        LEFT JOIN lab_tests_catalog ltc ON lt.test_id = ltc.id
        WHERE lt.id = ?
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([$lab_test_id]);
    $lab_test = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lab_test) {
        header('Location: assign_doctor.php?error=not_found');
        exit;
    }

    // ---------- GET TEST CATALOG ----------
    if (!empty($lab_test['test_id'])) {
        $stmt = $db->prepare("SELECT * FROM lab_tests_catalog WHERE id = ?");
        $stmt->execute([$lab_test['test_id']]);
        $test_catalog = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ---------- CALCULATE AGE ----------
    if (!empty($lab_test['patient_dob'])) {
        $dob = new DateTime($lab_test['patient_dob']);
        $now = new DateTime();
        $diff = $now->diff($dob);
        $age = $diff->y . ' yrs';
    }

    // ---------- STATUS INFO ----------
    $status_info = getStatusBadge($lab_test['status']);

    // ---------- GET ALL LAB TESTS FOR THIS VISIT ----------
    if (!empty($lab_test['visit_id'])) {
        $stmt = $db->prepare("
            SELECT id, test_name, status 
            FROM lab_tests 
            WHERE visit_id = ? 
            ORDER BY id
        ");
        $stmt->execute([$lab_test['visit_id']]);
        $visit_lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
    header('Location: assign_doctor.php?error=db_error');
    exit;
}

// ================================================================
// UNREAD NOTIFICATIONS
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
// ASSETS
// ================================================================
$profile_pic_url = !empty($profile_pic)
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/reception_header.php';
include_once '../../components/reception_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= (isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true') ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Test Details - Braick Dispensary</title>

    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        /* ================================================================
           LAB TEST DETAILS - PAGE-SPECIFIC STYLES ONLY
           (Shared styles are in reception_header.php)
           ================================================================ */

        /* ---------- PAGE HEADER ---------- */
        .page-header {
            background: linear-gradient(135deg, var(--primary, #0B5ED7) 0%, var(--primary-dark, #0A4CA8) 100%);
            border-radius: 20px;
            padding: 26px 32px;
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 10px 30px rgba(11, 94, 215, 0.25);
            position: relative;
            overflow: hidden;
            isolation: isolate;
        }
        .page-header::before,
        .page-header::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
        }
        .page-header::before {
            width: 340px; height: 340px;
            top: -180px; right: -80px;
            background: rgba(255,255,255,0.06);
        }
        .page-header::after {
            width: 200px; height: 200px;
            bottom: -120px; left: -40px;
            background: rgba(255,255,255,0.04);
        }

        .page-header .page-title {
            color: #fff;
            font-size: 1.6rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
            letter-spacing: -0.02em;
            margin: 0;
        }
        .page-header .page-title i { font-size: 1.85rem; opacity: 0.95; }

        .page-header .page-subtitle {
            color: rgba(255,255,255,0.9);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 6px;
            position: relative;
            z-index: 1;
        }
        .page-header .page-subtitle strong { color: #fff; font-weight: 600; }

        .role-badge-display {
            background: rgba(255,255,255,0.22);
            color: #fff;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            backdrop-filter: blur(6px);
        }

        .header-badge {
            background: rgba(255,255,255,0.15);
            color: #fff;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            backdrop-filter: blur(6px);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(255,255,255,0.15);
        }

        .btn-outline-light {
            background: rgba(255,255,255,0.15);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.25);
            padding: 9px 18px;
            border-radius: 11px;
            font-weight: 500;
            font-size: 0.82rem;
            transition: all 0.25s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(6px);
            position: relative;
            z-index: 1;
            cursor: pointer;
        }
        .btn-outline-light:hover {
            background: rgba(255,255,255,0.28);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.18);
            color: #fff;
        }

        .update-badge-light {
            background: rgba(255,255,255,0.12);
            color: rgba(255,255,255,0.85);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            backdrop-filter: blur(6px);
            font-weight: 500;
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

        /* ================================================================
           CARD
           ================================================================ */
        .card {
            background: var(--bg-card, #fff);
            border-radius: 18px;
            padding: 22px 26px;
            border: 1px solid var(--border-color, #E2E8F0);
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm, 0 1px 3px rgba(0,0,0,0.06));
            margin-bottom: 20px;
        }
        .card:hover {
            border-color: var(--primary, #0B5ED7);
            box-shadow: var(--shadow-md, 0 4px 16px rgba(0,0,0,0.08));
        }

        .card-title {
            font-size: 0.98rem;
            font-weight: 700;
            color: var(--text-primary, #1E293B);
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border-color, #E2E8F0);
        }
        .card-title i { color: var(--primary, #0B5ED7); }

        /* ================================================================
           INFO ROWS
           ================================================================ */
        .info-row {
            display: flex;
            padding: 9px 0;
            border-bottom: 1px solid var(--border-color, #E2E8F0);
            align-items: flex-start;
            gap: 12px;
        }
        .info-row:last-child { border-bottom: none; }

        .info-label {
            width: 145px;
            font-weight: 500;
            color: var(--text-secondary, #64748B);
            flex-shrink: 0;
            font-size: 0.82rem;
        }
        .info-value {
            flex: 1;
            color: var(--text-primary, #1E293B);
            font-size: 0.85rem;
            word-break: break-word;
        }
        .info-value a {
            color: var(--primary, #0B5ED7);
            text-decoration: none;
            font-weight: 500;
        }
        .info-value a:hover { text-decoration: underline; }

        /* ================================================================
           BADGES
           ================================================================ */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            white-space: nowrap;
        }
        .badge-success { background: #D1FAE5; color: #059669; }
        .badge-warning { background: #FEF3C7; color: #D97706; }
        .badge-danger  { background: #FEE2E2; color: #DC2626; }
        .badge-info    { background: #CFFAFE; color: #0891B2; }
        .badge-purple  { background: #EDE9FE; color: #7C3AED; }

        [data-theme="dark"] .badge-success { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .badge-warning { background: #3D2A1A; color: #FBBF24; }
        [data-theme="dark"] .badge-danger  { background: #3A1A1A; color: #F87171; }
        [data-theme="dark"] .badge-info    { background: #1A2A3A; color: #67E8F9; }
        [data-theme="dark"] .badge-purple  { background: #2D1B5F; color: #A78BFA; }

        /* ================================================================
           RESULTS BOX
           ================================================================ */
        .results-box {
            background: var(--bg-body, #F1F5F9);
            border-radius: 12px;
            padding: 18px 22px;
            border: 1px solid var(--border-color, #E2E8F0);
            font-family: 'Courier New', Consolas, monospace;
            white-space: pre-wrap;
            word-wrap: break-word;
            min-height: 90px;
            font-size: 0.85rem;
            line-height: 1.6;
            color: var(--text-primary, #1E293B);
        }

        /* ================================================================
           GRID
           ================================================================ */
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        /* ================================================================
           TEST NAV (multiple tests in visit)
           ================================================================ */
        .test-nav {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            padding: 10px 14px;
            background: var(--bg-body, #F1F5F9);
            border-radius: 12px;
            border: 1px solid var(--border-color, #E2E8F0);
            align-items: center;
        }
        .test-nav-item {
            padding: 5px 12px;
            border-radius: 8px;
            font-size: 0.72rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.25s ease;
            border: 2px solid transparent;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .test-nav-item.active {
            background: var(--primary, #0B5ED7);
            color: #fff;
            border-color: var(--primary, #0B5ED7);
            box-shadow: 0 3px 10px rgba(11, 94, 215, 0.25);
        }
        .test-nav-item.pending {
            background: #FEF3C7;
            color: #D97706;
            border-color: #FCD34D;
        }
        .test-nav-item.completed {
            background: #D1FAE5;
            color: #059669;
            border-color: #6EE7B7;
        }
        [data-theme="dark"] .test-nav-item.pending {
            background: #3D2A1A; color: #FBBF24; border-color: #5A4020;
        }
        [data-theme="dark"] .test-nav-item.completed {
            background: #1A3A2A; color: #34D399; border-color: #1F5A3E;
        }
        .test-nav-item:hover:not(.active) {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 10px 20px;
            border-radius: 11px;
            font-weight: 600;
            font-size: 0.82rem;
            transition: all 0.25s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
            white-space: nowrap;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary, #0B5ED7), var(--primary-dark, #0A4CA8));
            color: #fff;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(11, 94, 215, 0.3);
            color: #fff;
        }
        .btn-success {
            background: linear-gradient(135deg, #059669, #047857);
            color: #fff;
        }
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(5, 150, 105, 0.3);
            color: #fff;
        }
        .btn-outline {
            background: transparent;
            color: var(--text-secondary, #64748B);
            border: 2px solid var(--border-color, #E2E8F0);
        }
        .btn-outline:hover {
            background: var(--bg-body, #F1F5F9);
            border-color: var(--primary, #0B5ED7);
            color: var(--primary, #0B5ED7);
        }

        /* ================================================================
           EMPTY STATE
           ================================================================ */
        .empty-state {
            text-align: center;
            padding: 28px 12px;
            color: var(--text-secondary, #64748B);
        }
        .empty-state i {
            font-size: 2.5rem;
            display: block;
            margin-bottom: 10px;
            opacity: 0.4;
        }
        .empty-state p { font-size: 0.85rem; }

        /* ================================================================
           ANIMATIONS
           ================================================================ */
        @keyframes pulse-dot {
            0%, 100% { transform: scale(1); opacity: 1; }
            50%      { transform: scale(1.25); opacity: 0.75; }
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(15px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }

        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 768px) {
            .page-header { padding: 18px 20px; border-radius: 16px; }
            .page-header .page-title { font-size: 1.3rem; }
            .card { padding: 16px 18px; border-radius: 14px; }
            .info-label { width: 110px; font-size: 0.78rem; }
            .info-value { font-size: 0.8rem; }
            .grid-2 { grid-template-columns: 1fr; }
            .btn { width: 100%; justify-content: center; }
        }
        @media (max-width: 480px) {
            .page-header .page-title { font-size: 1.1rem; }
            .card { padding: 14px 14px; }
            .info-row { flex-direction: column; gap: 3px; }
            .info-label { width: 100%; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- MAIN CONTENT (Header & Sidebar already included above) -->
<!-- ================================================================ -->
<main class="main-content">

    <?php if (isset($lab_test) && $lab_test): ?>

    <!-- ============================================================ -->
    <!-- PAGE HEADER -->
    <!-- ============================================================ -->
    <div class="page-header animate-fade-in-up">
        <div>
            <h1 class="page-title">
                <i class="fas fa-flask"></i>
                Lab Test Details
                <span class="role-badge-display"><?= strtoupper(htmlspecialchars($role)) ?></span>
                <span class="update-badge-light">
                    <span class="live-indicator-modern"></span> Live
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-vial"></i>
                Test #<?= (int)$lab_test['id'] ?> for patient
                <strong><?= htmlspecialchars($lab_test['patient_name'] ?? 'Unknown') ?></strong>

                <span class="header-badge">
                    <span class="badge badge-<?= htmlspecialchars($status_info['class']) ?>">
                        <i class="fas <?= htmlspecialchars($status_info['icon']) ?>"></i>
                        <?= htmlspecialchars($status_info['text']) ?>
                    </span>
                </span>

                <span class="header-badge">
                    <i class="fas fa-tag"></i>
                    TSh <?= number_format((float)($lab_test['test_price'] ?? 0), 0) ?>
                </span>

                <?php if (!empty($lab_test['test_category'])): ?>
                    <span class="header-badge">
                        <i class="fas fa-folder"></i>
                        <?= htmlspecialchars($lab_test['test_category']) ?>
                    </span>
                <?php endif; ?>

                <?php if (!empty($lab_test['visit_number'])): ?>
                    <span class="header-badge">
                        <i class="fas fa-file-medical"></i>
                        <?= htmlspecialchars($lab_test['visit_number']) ?>
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="assign_doctor.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <?php if ($role === 'laboratory' && $lab_test['status'] !== 'completed'): ?>
                <a href="edit_lab_test.php?id=<?= (int)$lab_test['id'] ?>" class="btn-outline-light">
                    <i class="fas fa-edit"></i> Edit
                </a>
            <?php endif; ?>
            <?php if ($lab_test['status'] === 'completed'): ?>
                <a href="#" onclick="window.print(); return false;" class="btn-outline-light">
                    <i class="fas fa-print"></i> Print
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- TEST NAVIGATION (if multiple tests in same visit) -->
    <!-- ============================================================ -->
    <?php if (count($visit_lab_tests) > 1): ?>
        <div class="card animate-fade-in-up" style="padding:14px 18px;">
            <div class="test-nav">
                <span style="font-size:0.72rem;font-weight:600;color:var(--text-secondary);padding:4px 8px;">
                    <i class="fas fa-list"></i> Tests in this visit:
                </span>
                <?php foreach ($visit_lab_tests as $test):
                    $is_active = ($test['id'] == $lab_test['id']);
                    $status_class = $test['status'] === 'completed' ? 'completed' : 'pending';
                ?>
                    <a href="lab_tests.php?id=<?= (int)$test['id'] ?>"
                       class="test-nav-item <?= $is_active ? 'active' : $status_class ?>">
                        #<?= (int)$test['id'] ?> <?= htmlspecialchars(substr($test['test_name'], 0, 20)) ?>
                        <?php if ($is_active): ?>
                            <i class="fas fa-chevron-right"></i>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- ============================================================ -->
    <!-- GRID: PATIENT INFO + TEST INFO -->
    <!-- ============================================================ -->
    <div class="grid-2">

        <!-- Patient Information -->
        <div class="card animate-fade-in-up" style="animation-delay:0.05s;">
            <div class="card-title">
                <i class="fas fa-user"></i> Patient Information
            </div>
            <div class="info-row">
                <span class="info-label">Full Name</span>
                <span class="info-value">
                    <a href="view_patient.php?id=<?= (int)$lab_test['patient_id'] ?>">
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
                <span class="info-value"><?= htmlspecialchars($age) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Blood Group</span>
                <span class="info-value"><?= htmlspecialchars($lab_test['patient_blood_group'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Phone</span>
                <span class="info-value">
                    <?php if (!empty($lab_test['patient_phone'])): ?>
                        <a href="tel:<?= htmlspecialchars($lab_test['patient_phone']) ?>">
                            <?= htmlspecialchars($lab_test['patient_phone']) ?>
                        </a>
                    <?php else: ?>
                        N/A
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Address</span>
                <span class="info-value"><?= htmlspecialchars($lab_test['patient_address'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Allergies</span>
                <span class="info-value"><?= htmlspecialchars($lab_test['patient_allergies'] ?? 'None') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Visit Number</span>
                <span class="info-value" style="font-family:monospace;"><?= htmlspecialchars($lab_test['visit_number'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Visit Status</span>
                <span class="info-value">
                    <span class="badge badge-<?= ($lab_test['visit_status'] ?? '') === 'completed' ? 'success' : 'warning' ?>">
                        <?= ucfirst(htmlspecialchars($lab_test['visit_status'] ?? 'N/A')) ?>
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
                <span class="info-value"><strong><?= htmlspecialchars($lab_test['test_name'] ?? 'N/A') ?></strong></span>
            </div>
            <div class="info-row">
                <span class="info-label">Test Code</span>
                <span class="info-value" style="font-family:monospace;">
                    <?= htmlspecialchars($test_catalog['test_code'] ?? $lab_test['test_code'] ?? 'N/A') ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Category</span>
                <span class="info-value"><?= htmlspecialchars($lab_test['test_category'] ?? $test_catalog['category'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Price</span>
                <span class="info-value"><strong>TSh <?= number_format((float)($lab_test['test_price'] ?? 0), 0) ?></strong></span>
            </div>
            <div class="info-row">
                <span class="info-label">Sample Type</span>
                <span class="info-value"><?= htmlspecialchars($lab_test['sample_type'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Test Date</span>
                <span class="info-value">
                    <?= !empty($lab_test['test_date']) ? date('d M Y', strtotime($lab_test['test_date'])) : 'Not set' ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Status</span>
                <span class="info-value">
                    <span class="badge badge-<?= htmlspecialchars($status_info['class']) ?>">
                        <i class="fas <?= htmlspecialchars($status_info['icon']) ?>"></i>
                        <?= htmlspecialchars($status_info['text']) ?>
                    </span>
                </span>
            </div>
            <?php if ($lab_test['status'] === 'completed'): ?>
                <div class="info-row">
                    <span class="info-label">Completed At</span>
                    <span class="info-value">
                        <?= !empty($lab_test['completed_at']) ? date('d M Y h:i A', strtotime($lab_test['completed_at'])) : 'N/A' ?>
                    </span>
                </div>
            <?php endif; ?>
            <?php if (!empty($lab_test['reference_range'])): ?>
                <div class="info-row">
                    <span class="info-label">Reference Range</span>
                    <span class="info-value"><?= htmlspecialchars($lab_test['reference_range']) ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- DOCTOR & TECHNICIAN INFO -->
    <!-- ============================================================ -->
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
                        <a href="view_user.php?id=<?= (int)$lab_test['doctor_id'] ?>">
                            <?= htmlspecialchars($lab_test['doctor_name']) ?>
                        </a>
                    <?php else: ?>
                        <span class="badge badge-purple">
                            <i class="fas fa-flask"></i> Lab Only (No Doctor)
                        </span>
                    <?php endif; ?>
                </span>
            </div>
            <?php if (!empty($lab_test['doctor_name'])): ?>
                <div class="info-row">
                    <span class="info-label">Requested At</span>
                    <span class="info-value">
                        <?= !empty($lab_test['created_at']) ? date('d M Y h:i A', strtotime($lab_test['created_at'])) : 'N/A' ?>
                    </span>
                </div>
            <?php endif; ?>
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
                        <a href="view_user.php?id=<?= (int)$lab_test['lab_technician_id'] ?>">
                            <?= htmlspecialchars($lab_test['technician_name']) ?>
                        </a>
                    <?php else: ?>
                        <span style="color:var(--text-secondary);">
                            <i class="fas fa-info-circle"></i> Not assigned
                        </span>
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

    <!-- ============================================================ -->
    <!-- RESULTS -->
    <!-- ============================================================ -->
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
            <div class="results-box"><?= nl2br(htmlspecialchars($lab_test['results'])) ?></div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-file-alt"></i>
                <p><strong>No results available yet</strong></p>
                <p style="font-size:0.78rem;">Results will appear here once the test is completed</p>
            </div>
        <?php endif; ?>

        <?php if (!empty($lab_test['interpretation'])): ?>
            <div style="margin-top:16px;">
                <h4 style="font-weight:600;font-size:0.82rem;color:var(--text-secondary);margin-bottom:6px;">
                    <i class="fas fa-comment-medical"></i> Interpretation
                </h4>
                <div style="background:var(--bg-body);border-radius:10px;padding:12px 16px;font-size:0.82rem;color:var(--text-primary);border:1px solid var(--border-color);">
                    <?= nl2br(htmlspecialchars($lab_test['interpretation'])) ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($lab_test['notes'])): ?>
            <div style="margin-top:12px;">
                <span style="font-size:0.8rem;color:var(--text-secondary);">
                    <i class="fas fa-sticky-note"></i> Notes:
                </span>
                <span style="font-size:0.82rem;color:var(--text-primary);">
                    <?= nl2br(htmlspecialchars($lab_test['notes'])) ?>
                </span>
            </div>
        <?php endif; ?>
    </div>

    <!-- ============================================================ -->
    <!-- ACTION BUTTONS -->
    <!-- ============================================================ -->
    <?php if ($role === 'laboratory' || $role === 'admin'): ?>
        <div style="display:flex;flex-wrap:wrap;gap:12px;margin-top:20px;">
            <?php if ($lab_test['status'] === 'pending'): ?>
                <a href="start_lab_test.php?id=<?= (int)$lab_test['id'] ?>" class="btn btn-primary">
                    <i class="fas fa-play"></i> Start Test
                </a>
            <?php endif; ?>

            <?php if ($lab_test['status'] === 'in_progress'): ?>
                <a href="edit_lab_test.php?id=<?= (int)$lab_test['id'] ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Add Results
                </a>
                <a href="complete_lab_test.php?id=<?= (int)$lab_test['id'] ?>" class="btn btn-success"
                   onclick="return confirm('Mark this test as completed?');">
                    <i class="fas fa-check"></i> Complete Test
                </a>
            <?php endif; ?>

            <?php if ($lab_test['status'] === 'completed'): ?>
                <a href="#" onclick="window.print(); return false;" class="btn btn-primary">
                    <i class="fas fa-print"></i> Print Results
                </a>
                <a href="download_lab_result.php?id=<?= (int)$lab_test['id'] ?>" class="btn btn-success">
                    <i class="fas fa-download"></i> Download PDF
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php else: ?>

    <!-- ============================================================ -->
    <!-- ERROR - LAB TEST NOT FOUND -->
    <!-- ============================================================ -->
    <div class="page-header animate-fade-in-up">
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
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="assign_doctor.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <div class="card" style="text-align:center;padding:48px 24px;">
        <i class="fas fa-flask" style="font-size:4rem;color:var(--text-secondary);opacity:0.3;display:block;margin-bottom:16px;"></i>
        <h2 style="font-size:1.25rem;font-weight:600;color:var(--text-primary);margin-bottom:8px;">Lab Test Not Found</h2>
        <p style="color:var(--text-secondary);margin-bottom:20px;">The requested lab test could not be found in the system.</p>
        <a href="assign_doctor.php" class="btn btn-primary">
            <i class="fas fa-arrow-left"></i> Back to Assign Doctor
        </a>
    </div>

    <?php endif; ?>

    <!-- ============================================================ -->
    <!-- FOOTER -->
    <!-- ============================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span style="color:var(--gray-300);margin:0 8px;">|</span>
            Lab Test Details
            <span style="color:var(--gray-300);margin:0 8px;">|</span>
            <span id="footerTimestamp"><?= date('h:i:s A') ?></span>
            <span style="color:var(--gray-300);margin:0 8px;">|</span>
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
    // DATE & TIME UPDATER
    // ================================================================
    function updateDateTime() {
        const now = new Date();
        const timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        const ft = document.getElementById('footerTimestamp');
        if (ft) ft.textContent = timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        const toast = document.getElementById('toast');
        if (!toast) return;
        document.getElementById('toastTitle').textContent = title;
        document.getElementById('toastMessage').textContent = message;
        toast.className = 'toast-custom ' + type;
        toast.style.display = 'flex';
        toast.classList.add('show');
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => { toast.style.display = 'none'; }, 400);
        }, 3500);
    }

    console.log('%c🔬 Braick - Lab Test Details', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📋 Lab Test ID: <?= (int)$lab_test_id ?>', 'font-size:13px; color:#64748B;');
    console.log('%c👤 Patient: <?= htmlspecialchars($lab_test['patient_name'] ?? 'Unknown') ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📊 Status: <?= htmlspecialchars($status_info['text'] ?? 'Unknown') ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c✅ Using shared header & sidebar', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>