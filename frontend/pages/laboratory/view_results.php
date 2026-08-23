<?php
// ================================================================
// FILE: frontend/pages/laboratory/view_results.php
// LABORATORY - VIEW RESULTS FOR A SPECIFIC TEST
// ✅ USING NEW DATABASE: dispensary_db
// ✅ ONLY ONE TABLE: lab_tests (NO lab_requests)
// WITH FULL LOGIN SESSION PROTECTION
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// CHECK SESSION - REDIRECT TO LOGIN IF NOT LABORATORY
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'laboratory') {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// GET USER DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Lab Technician';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Branch';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// INCLUDE DATABASE - NEW DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die('Database connection error: ' . $e->getMessage());
}

// ================================================================
// GET TEST ID
// ================================================================
$test_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($test_id <= 0) {
    header('Location: completed_tests.php');
    exit;
}

// ================================================================
// GET TEST DETAILS - FROM lab_tests ONLY
// ================================================================
$query = "
    SELECT 
        lt.id as test_id,
        lt.visit_id,
        lt.doctor_id,
        lt.lab_technician_id,
        lt.technician_id,
        lt.test_name,
        lt.test_price,
        lt.test_type,
        lt.sample_type,
        lt.test_date,
        lt.results,
        lt.reference_range,
        lt.status as test_status,
        lt.notes,
        lt.branch_id,
        lt.created_at,
        lt.completed_at,
        lt.updated_at,
        lt.formatted_result,
        lt.printed_at,
        lt.printed_by,
        p.full_name as patient_name,
        p.patient_id as patient_code,
        p.phone,
        p.gender,
        p.date_of_birth,
        p.blood_group,
        p.address,
        p.allergies,
        u.full_name as doctor_name,
        u.specialty as doctor_specialty,
        v.visit_number,
        v.visit_type,
        v.diagnosis,
        v.symptoms,
        v.status as visit_status,
        v.is_completed
    FROM lab_tests lt
    LEFT JOIN visits v ON lt.visit_id = v.id
    LEFT JOIN patients p ON v.patient_id = p.id
    LEFT JOIN users u ON lt.doctor_id = u.id
    WHERE lt.id = ? AND lt.branch_id = ?
";

$stmt = $db->prepare($query);
$stmt->execute([$test_id, $user_branch_id]);
$test = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$test) {
    header('Location: completed_tests.php');
    exit;
}

// ================================================================
// GET ALL TESTS FOR THIS VISIT (for navigation)
// ================================================================
$stmt = $db->prepare("
    SELECT id, test_name, status, completed_at 
    FROM lab_tests 
    WHERE visit_id = ? 
    ORDER BY id
");
$stmt->execute([$test['visit_id']]);
$visit_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// ================================================================
// PROFILE PICTURE
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

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
    <title>View Results - Laboratory</title>
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
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
            --text-dark: #0F172A;
            --border-color: #E2E8F0;
            --table-hover: #F1F5F9;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --text-dark: #F1F5F9;
            --border-color: #334155;
            --primary-bg: #1E3A5F;
            --primary-light: #6EA8FE;
            --gray-100: #1E293B;
            --gray-200: #334155;
            --gray-300: #475569;
            --table-hover: #1E293B;
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
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
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
            backdrop-filter: blur(4px);
        }
        
        .header-badge {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            backdrop-filter: blur(4px);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(255,255,255,0.1);
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
            position: relative;
            z-index: 1;
        }
        
        .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }
        
        .print-button {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 18px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.82rem;
            transition: all 0.3s;
            cursor: pointer;
            backdrop-filter: blur(4px);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            position: relative;
            z-index: 1;
        }
        
        .print-button:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }
        
        /* ================================================================
           DETAIL CARDS
           ================================================================ */
        .detail-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 20px 24px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .detail-card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.06);
        }
        
        .detail-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        
        .detail-value {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        /* ================================================================
           STATUS BADGES
           ================================================================ */
        .status-badge {
            display: inline-block;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 4px 16px;
            border-radius: 20px;
        }
        
        .status-badge.completed { background: #D1FAE5; color: #059669; }
        .status-badge.in_progress { background: #E8F0FE; color: #0B5ED7; }
        .status-badge.pending { background: #FEF3C7; color: #D97706; }
        .status-badge.cancelled { background: #FEE2E2; color: #DC2626; }
        
        [data-theme="dark"] .status-badge.completed { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .status-badge.in_progress { background: #1E3A5F; color: #6EA8FE; }
        [data-theme="dark"] .status-badge.pending { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .status-badge.cancelled { background: #3A1A1A; color: #F87171; }
        
        /* ================================================================
           RESULT DISPLAY
           ================================================================ */
        .result-display {
            background: var(--bg-body);
            padding: 16px 20px;
            border-radius: 12px;
            border: 2px solid var(--border-color);
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            white-space: pre-wrap;
            word-wrap: break-word;
            margin-top: 12px;
            min-height: 60px;
        }
        
        .result-display.empty {
            color: var(--text-secondary);
            font-style: italic;
            font-family: inherit;
        }
        
        [data-theme="dark"] .result-display {
            background: var(--gray-800);
            border-color: var(--gray-600);
        }
        
        .result-item {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 16px 20px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            margin-bottom: 12px;
        }
        
        .result-item:hover {
            border-color: var(--primary);
        }
        
        .result-item.completed {
            border-left: 4px solid var(--success);
        }
        
        .result-item.in_progress {
            border-left: 4px solid var(--primary);
        }
        
        .result-item.pending {
            border-left: 4px solid var(--warning);
        }
        
        .result-status-badge {
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 12px;
            border-radius: 12px;
        }
        
        .result-status-badge.completed { background: #D1FAE5; color: #059669; }
        .result-status-badge.in_progress { background: #E8F0FE; color: #0B5ED7; }
        .result-status-badge.pending { background: #FEF3C7; color: #D97706; }
        .result-status-badge.cancelled { background: #FEE2E2; color: #DC2626; }
        
        [data-theme="dark"] .result-status-badge.completed { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .result-status-badge.in_progress { background: #1E3A5F; color: #6EA8FE; }
        [data-theme="dark"] .result-status-badge.pending { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .result-status-badge.cancelled { background: #3A1A1A; color: #F87171; }
        
        /* ================================================================
           NAVIGATION TABS
           ================================================================ */
        .nav-tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }
        
        .nav-tab {
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            border: 2px solid var(--border-color);
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .nav-tab:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .nav-tab.active {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }
        
        .nav-tab.completed {
            border-color: var(--success);
            color: var(--success);
        }
        
        .nav-tab.completed.active {
            background: var(--success);
            color: white;
        }
        
        .nav-tab.in_progress {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .nav-tab.in_progress.active {
            background: var(--primary);
            color: white;
        }
        
        .nav-tab.pending {
            border-color: var(--warning);
            color: var(--warning);
        }
        
        .nav-tab.pending.active {
            background: var(--warning);
            color: white;
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
           PRINT
           ================================================================ */
        @media print {
            .top-nav, .sidebar, .btn, .print-button, .no-print { display: none !important; }
            .main-content { margin: 0 !important; padding: 20px !important; }
            .detail-card, .result-item { border: 1px solid #ddd !important; box-shadow: none !important; }
            .status-badge, .result-status-badge { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .grid-cols-1.lg\:grid-cols-3 { grid-template-columns: 1fr; }
            .detail-card { padding: 16px; }
            .result-item { padding: 12px 16px; }
            .main-content { padding: 10px; }
        }
        
        @media (max-width: 480px) {
            .page-title { font-size: 1.1rem; }
            .detail-card { padding: 12px; }
            .grid-cols-2 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <?php if ($test): ?>
    
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-flask mr-2"></i> Test Results
                <span class="role-badge-display">LABORATORY</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-vial"></i>
                <strong><?= htmlspecialchars($test['test_name']) ?></strong>
                <span class="header-badge">
                    <i class="fas fa-user mr-1"></i> <?= htmlspecialchars($test['patient_name']) ?>
                </span>
                <span class="header-badge">
                    <i class="fas fa-prescription mr-1"></i> <?= htmlspecialchars($test['visit_number'] ?? 'N/A') ?>
                </span>
                <span class="header-badge">
                    <i class="fas fa-check-circle mr-1"></i> <?= ucfirst($test['test_status']) ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap no-print">
            <a href="completed_tests.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <button onclick="window.print()" class="print-button">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- VISIT TESTS NAVIGATION -->
    <!-- ================================================================ -->
    <?php if (count($visit_tests) > 1): ?>
    <div class="detail-card mb-4">
        <div class="flex items-center justify-between flex-wrap gap-2">
            <span class="text-sm font-medium text-gray-500">All Tests in this Visit:</span>
            <div class="nav-tabs">
                <?php foreach ($visit_tests as $vt): 
                    $is_active = ($vt['id'] == $test_id);
                    $status_class = $vt['status'] ?? 'pending';
                ?>
                    <a href="view_results.php?id=<?= $vt['id'] ?>" 
                       class="nav-tab <?= $status_class ?> <?= $is_active ? 'active' : '' ?>">
                        <?= htmlspecialchars($vt['test_name']) ?>
                        <span class="text-xs opacity-70">
                            (<?= ucfirst($vt['status'] ?? 'pending') ?>)
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- TEST OVERVIEW -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">
        
        <div class="detail-card lg:col-span-2">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">
                <i class="fas fa-info-circle text-primary mr-2"></i> Test Information
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                <div>
                    <p class="detail-label">Test Name</p>
                    <p class="detail-value"><?= htmlspecialchars($test['test_name']) ?></p>
                </div>
                <div>
                    <p class="detail-label">Status</p>
                    <p class="detail-value">
                        <span class="status-badge <?= $test['test_status'] ?>">
                            <?= ucfirst($test['test_status'] ?? 'Pending') ?>
                        </span>
                    </p>
                </div>
                <div>
                    <p class="detail-label">Test Type</p>
                    <p class="detail-value"><?= htmlspecialchars($test['test_type'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Sample Type</p>
                    <p class="detail-value"><?= htmlspecialchars($test['sample_type'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Doctor</p>
                    <p class="detail-value">Dr. <?= htmlspecialchars($test['doctor_name'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Completed At</p>
                    <p class="detail-value"><?= $test['completed_at'] ? date('M d, Y h:i A', strtotime($test['completed_at'])) : 'N/A' ?></p>
                </div>
            </div>
        </div>
        
        <div class="detail-card">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">
                <i class="fas fa-user text-primary mr-2"></i> Patient Information
            </h3>
            <div class="space-y-2">
                <div>
                    <p class="detail-label">Name</p>
                    <p class="detail-value"><?= htmlspecialchars($test['patient_name']) ?></p>
                </div>
                <div>
                    <p class="detail-label">Patient ID</p>
                    <p class="detail-value"><?= htmlspecialchars($test['patient_code'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Gender</p>
                    <p class="detail-value"><?= htmlspecialchars($test['gender'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="detail-label">Phone</p>
                    <p class="detail-value"><?= htmlspecialchars($test['phone'] ?? 'N/A') ?></p>
                </div>
                <?php if (!empty($test['date_of_birth'])): ?>
                <div>
                    <p class="detail-label">Age</p>
                    <p class="detail-value"><?= date_diff(date_create($test['date_of_birth']), date_create('today'))->y ?> years</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- TEST RESULT -->
    <!-- ================================================================ -->
    <div class="detail-card">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">
            <i class="fas fa-file-medical-alt text-purple-600 mr-2"></i> Test Result
        </h3>
        
        <div class="result-item <?= $test['test_status'] ?? 'pending' ?>">
            <div class="flex flex-wrap justify-between items-start gap-3">
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="font-semibold"><?= htmlspecialchars($test['test_name']) ?></span>
                    <span class="result-status-badge <?= $test['test_status'] ?? 'pending' ?>">
                        <?php if ($test['test_status'] === 'completed'): ?>
                            ✅ Completed
                        <?php elseif ($test['test_status'] === 'in_progress'): ?>
                            🔬 In Progress
                        <?php elseif ($test['test_status'] === 'pending'): ?>
                            ⏳ Pending
                        <?php else: ?>
                            <?= ucfirst($test['test_status'] ?? 'Pending') ?>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="text-xs text-gray-400">
                    <?php if ($test['test_date']): ?>
                        Test Date: <?= date('M d, Y', strtotime($test['test_date'])) ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!empty($test['reference_range'])): ?>
                <p class="text-xs text-gray-400 mt-2">
                    <strong>Reference Range:</strong> <?= htmlspecialchars($test['reference_range']) ?>
                </p>
            <?php endif; ?>
            
            <!-- Result Display -->
            <div class="result-display <?= empty($test['results']) ? 'empty' : '' ?>">
                <?php if (!empty($test['results'])): ?>
                    <?= nl2br(htmlspecialchars($test['results'])) ?>
                <?php else: ?>
                    <i class="fas fa-info-circle mr-1"></i> No result available yet
                <?php endif; ?>
            </div>
            
            <?php if (!empty($test['notes'])): ?>
                <div class="mt-3 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        <strong>Notes:</strong> <?= htmlspecialchars($test['notes']) ?>
                    </p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($test['formatted_result'])): ?>
                <div class="mt-3 p-3 bg-purple-50 dark:bg-purple-900/20 rounded-lg border border-purple-200 dark:border-purple-800">
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        <strong>Formatted Result:</strong> <?= nl2br(htmlspecialchars($test['formatted_result'])) ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- DIAGNOSIS (if available) -->
    <!-- ================================================================ -->
    <?php if (!empty($test['diagnosis'])): ?>
    <div class="detail-card mt-5">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">
            <i class="fas fa-stethoscope text-blue-600 mr-2"></i> Diagnosis
        </h3>
        <p class="text-gray-700 dark:text-gray-300"><?= nl2br(htmlspecialchars($test['diagnosis'])) ?></p>
    </div>
    <?php endif; ?>

    <?php else: ?>
        <div class="text-center py-8 text-gray-400">
            <i class="fas fa-flask text-4xl block mb-3"></i>
            <p class="text-lg">Test not found</p>
            <a href="completed_tests.php" class="text-blue-600 hover:underline">Back to completed tests</a>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer no-print">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            View Results
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

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
        var el = document.getElementById('currentDateTime');
        if (el) {
            el.textContent = dateStr + ' • ' + timeStr;
        }
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    console.log('%c🧪 Laboratory - View Results (NEW DATABASE)', 'font-size:18px; font-weight:bold; color:#7C3AED;');
    console.log('%c✅ USING NEW DATABASE: dispensary_db', 'font-size:13px; color:#34D399;');
    console.log('%c✅ ONLY ONE TABLE: lab_tests', 'font-size:13px; color:#34D399;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📋 Test: <?= htmlspecialchars($test['test_name'] ?? 'N/A') ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c👤 Patient: <?= htmlspecialchars($test['patient_name'] ?? 'N/A') ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Status: <?= $test['test_status'] ?? 'N/A' ?>', 'font-size:13px; color:#64748B;');
</script>

</body>
</html>