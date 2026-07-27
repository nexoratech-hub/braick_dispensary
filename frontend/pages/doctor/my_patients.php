<?php
// ================================================================
// FILE: frontend/pages/doctor/my_patients.php
// DOCTOR - MY PATIENTS LIST
// - Two sections: Pending Patients (Active) and Completed Patients
// - Table format with blue headers
// - Pending: View & Consult buttons
// - Completed: View button only (view_patient.php)
// - Uses SHARED HEADER (dark mode, date/time, status toggle inherited)
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// IF NO SESSION, USE DR. JOHN MUSHI (ID: 5) AS DEFAULT
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    $_SESSION['user_id'] = 5;
    $_SESSION['doctor_id'] = 5;
    $_SESSION['full_name'] = 'Dr. John Mushi';
    $_SESSION['username'] = 'dr.john';
    $_SESSION['email'] = 'john@braick.com';
    $_SESSION['phone'] = '+255 700 000 011';
    $_SESSION['role'] = 'doctor';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['specialty'] = 'General Medicine';
    $_SESSION['profile_pic'] = '';
    $_SESSION['is_online'] = 1;
}

$doctor_id = $_SESSION['user_id'] ?? 5;
$doctor_name = $_SESSION['full_name'] ?? 'Dr. John Mushi';
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;
$doctor_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';

// ================================================================
// GET SEARCH PARAMETER
// ================================================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// GET PENDING PATIENTS (Active - not completed/cancelled)
// ================================================================
$pending_params = [$doctor_id, $doctor_branch_id];
$search_condition = "";

if (!empty($search)) {
    $search_condition = "AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR p.phone LIKE ?)";
    $pending_params[] = "%$search%";
    $pending_params[] = "%$search%";
    $pending_params[] = "%$search%";
}

$pending_sql = "
    SELECT 
        p.*,
        v.id as latest_visit_id,
        v.visit_number as latest_visit_number,
        v.status as latest_visit_status,
        v.created_at as latest_visit_date,
        v.visit_type as latest_visit_type,
        (SELECT COUNT(*) FROM visits WHERE patient_id = p.id) as total_visits,
        (SELECT full_name FROM users WHERE id = p.assigned_doctor_id) as assigned_doctor_name
    FROM patients p
    LEFT JOIN visits v ON v.patient_id = p.id AND v.id = (
        SELECT id FROM visits WHERE patient_id = p.id ORDER BY created_at DESC LIMIT 1
    )
    WHERE p.assigned_doctor_id = ? 
    AND p.branch_id = ?
    AND (v.status IS NULL OR v.status NOT IN ('completed', 'cancelled'))
    $search_condition
    ORDER BY p.created_at DESC
";

$stmt = $db->prepare($pending_sql);
$stmt->execute($pending_params);
$pending_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
$pending_count = count($pending_patients);

// ================================================================
// GET COMPLETED PATIENTS
// ================================================================
$completed_params = [$doctor_id, $doctor_branch_id];
$search_condition_completed = "";

if (!empty($search)) {
    $search_condition_completed = "AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR p.phone LIKE ?)";
    $completed_params[] = "%$search%";
    $completed_params[] = "%$search%";
    $completed_params[] = "%$search%";
}

$completed_sql = "
    SELECT 
        p.*,
        v.id as latest_visit_id,
        v.visit_number as latest_visit_number,
        v.status as latest_visit_status,
        v.created_at as latest_visit_date,
        v.visit_type as latest_visit_type,
        (SELECT COUNT(*) FROM visits WHERE patient_id = p.id) as total_visits,
        (SELECT full_name FROM users WHERE id = p.assigned_doctor_id) as assigned_doctor_name
    FROM patients p
    LEFT JOIN visits v ON v.patient_id = p.id AND v.id = (
        SELECT id FROM visits WHERE patient_id = p.id ORDER BY created_at DESC LIMIT 1
    )
    WHERE p.assigned_doctor_id = ? 
    AND p.branch_id = ?
    AND v.status IN ('completed', 'cancelled')
    $search_condition_completed
    ORDER BY v.completed_at DESC, p.created_at DESC
";

$stmt = $db->prepare($completed_sql);
$stmt->execute($completed_params);
$completed_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
$completed_count = count($completed_patients);

// ================================================================
// GET TOTAL COUNT
// ================================================================
$total_count = $pending_count + $completed_count;

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/doctor_header.php';
include_once __DIR__ . '/../../components/doctor_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Patients - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" type="image/png">
    
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
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --gray-50: #1E293B;
            --gray-100: #334155;
            --gray-200: #475569;
            --gray-300: #64748B;
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
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
        
        /* Page Header */
        .page-header-custom {
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
        
        .page-header-custom::before {
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
        
        .page-header-custom .page-title {
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
        
        .page-header-custom .page-title i {
            font-size: 2rem;
            opacity: 0.9;
        }
        
        .page-header-custom .page-subtitle {
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
        
        .live-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(52, 211, 153, 0.2);
            color: #34D399;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            border: 1px solid rgba(52, 211, 153, 0.3);
        }
        .live-badge i { font-size: 0.4rem; }
        
        /* ================================================================
           SECTION HEADERS
           ================================================================ */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 28px 0 16px 0;
            max-width: 1400px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .section-header .section-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-header .section-title .count-badge {
            font-size: 0.7rem;
            padding: 2px 12px;
            border-radius: 20px;
            font-weight: 600;
        }
        
        .section-header .section-title .count-badge.pending {
            background: var(--warning-bg);
            color: var(--warning);
        }
        
        .section-header .section-title .count-badge.completed {
            background: var(--primary-bg);
            color: var(--primary);
        }
        
        .section-header .section-title .count-badge.total {
            background: var(--success-bg);
            color: var(--success);
        }
        
        /* ================================================================
           TABLE CONTAINER
           ================================================================ */
        .table-container {
            background: var(--bg-card);
            border-radius: 14px;
            border: 1px solid var(--border-color);
            overflow: hidden;
            max-width: 1400px;
            margin-left: auto;
            margin-right: auto;
            box-shadow: var(--shadow);
            overflow-x: auto;
            margin-bottom: 10px;
        }
        
        .table-container table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        
        /* ================================================================
           TABLE HEADER - BLUE BACKGROUND
           ================================================================ */
        .table-container thead {
            background: #0B5ED7 !important;
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8) !important;
        }
        
        .table-container thead th {
            padding: 14px 16px;
            text-align: left;
            font-weight: 700;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #FFFFFF !important;
            border-bottom: none;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .table-container thead th i {
            margin-right: 6px;
            opacity: 0.8;
        }
        
        /* ================================================================
           TABLE BODY
           ================================================================ */
        .table-container tbody td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .table-container tbody tr:hover {
            background: var(--gray-50);
        }
        
        [data-theme="dark"] .table-container tbody tr:hover {
            background: var(--gray-700);
        }
        
        .table-container tbody tr:last-child td {
            border-bottom: none;
        }
        
        .table-container tbody tr:nth-child(even) {
            background: var(--gray-50);
        }
        
        [data-theme="dark"] .table-container tbody tr:nth-child(even) {
            background: var(--gray-700);
        }
        
        /* ================================================================
           PATIENT CELL
           ================================================================ */
        .patient-cell .patient-name {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .patient-cell .patient-id {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-family: monospace;
        }
        
        .patient-cell .patient-details {
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        /* ================================================================
           STATUS BADGE
           ================================================================ */
        .status-badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            text-transform: uppercase;
            white-space: nowrap;
        }
        
        .status-badge.pending {
            background: var(--warning-bg);
            color: var(--warning);
        }
        
        .status-badge.with_doctor {
            background: var(--primary-bg);
            color: var(--primary);
        }
        
        .status-badge.assigned {
            background: var(--primary-bg);
            color: var(--primary);
        }
        
        .status-badge.lab_test {
            background: var(--purple-bg);
            color: var(--purple);
        }
        
        .status-badge.prescribed {
            background: var(--purple-bg);
            color: var(--purple);
        }
        
        .status-badge.completed {
            background: var(--success-bg);
            color: var(--success);
        }
        
        .status-badge.cancelled {
            background: var(--danger-bg);
            color: var(--danger);
        }
        
        /* ================================================================
           ACTIONS
           ================================================================ */
        .actions-cell {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.65rem;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            text-decoration: none;
            white-space: nowrap;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        .btn-success:hover {
            background: #047857;
            transform: translateY(-1px);
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
        
        .btn-purple {
            background: var(--purple);
            color: white;
        }
        .btn-purple:hover {
            background: #6D28D9;
            transform: translateY(-1px);
        }
        
        .btn-sm {
            padding: 3px 10px;
            font-size: 0.6rem;
        }
        
        /* ================================================================
           EMPTY STATE
           ================================================================ */
        .empty-state {
            text-align: center;
            padding: 30px 20px;
            color: var(--text-secondary);
        }
        .empty-state i {
            font-size: 2rem;
            color: var(--border-color);
            display: block;
            margin-bottom: 8px;
        }
        .empty-state .empty-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        .empty-state .empty-sub {
            font-size: 0.8rem;
            color: var(--text-secondary);
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
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        }
        .toast-custom.show {
            transform: translateY(0);
            opacity: 1;
        }
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }
        
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
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
            .sidebar-toggle-btn { display: block; }
            .table-container table { font-size: 0.75rem; }
            .table-container thead th, .table-container tbody td { padding: 8px 12px; }
        }
        
        @media (max-width: 768px) {
            .page-header-custom { padding: 16px 18px; }
            .page-header-custom .page-title { font-size: 1.3rem; }
            .table-container { border-radius: 10px; }
            .table-container table { font-size: 0.7rem; }
            .table-container thead th, .table-container tbody td { padding: 6px 10px; }
            .section-header { flex-direction: column; align-items: flex-start; gap: 8px; }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .table-container thead th, .table-container tbody td { padding: 4px 8px; font-size: 0.65rem; }
            .actions-cell { flex-direction: column; gap: 4px; }
            .btn { font-size: 0.55rem; padding: 2px 8px; }
            .patient-cell .patient-details { font-size: 0.6rem; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header-custom">
        <div>
            <h1 class="page-title">
                <i class="fas fa-users"></i>
                My Patients
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;">DOCTOR</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-user-md"></i>
                Manage your assigned patients
                
                <span class="header-badge">
                    <i class="fas fa-user"></i>
                    <?= $total_count ?> Total
                </span>
                
                <span class="header-badge" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.3);">
                    <i class="fas fa-clock" style="color:#D97706;"></i>
                    <?= $pending_count ?> Pending
                </span>
                
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);">
                    <i class="fas fa-check-circle" style="color:#059669;"></i>
                    <?= $completed_count ?> Completed
                </span>
                
                <span class="live-badge" id="liveBadge">
                    <i class="fas fa-circle" style="color:#34D399;"></i>
                    Live
                    <span id="liveTime" style="font-weight:400;font-size:0.55rem;"><?= date('H:i:s') ?></span>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <button onclick="manualRefresh()" class="btn-outline-light" id="refreshBtn">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- SECTION 1: PENDING PATIENTS -->
    <!-- ================================================================ -->
    <div class="section-header">
        <div class="section-title">
            <i class="fas fa-clock" style="color:var(--warning);"></i>
            Pending Patients
            <span class="count-badge pending"><?= $pending_count ?></span>
        </div>
        <div style="font-size:0.8rem;color:var(--text-secondary);">
            <i class="fas fa-info-circle"></i> Patients with active consultations
        </div>
    </div>

    <div class="table-container" id="pendingContainer">
        <?php if ($pending_count > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th><i class="fas fa-hashtag"></i> #</th>
                        <th><i class="fas fa-user"></i> Patient</th>
                        <th><i class="fas fa-phone"></i> Contact</th>
                        <th><i class="fas fa-notes-medical"></i> Visit</th>
                        <th><i class="fas fa-info-circle"></i> Status</th>
                        <th><i class="fas fa-calendar"></i> Last Visit</th>
                        <th><i class="fas fa-cog"></i> Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $counter = 1;
                    foreach ($pending_patients as $patient): 
                        $age = 'N/A';
                        if (!empty($patient['date_of_birth'])) {
                            $dob = new DateTime($patient['date_of_birth']);
                            $today = new DateTime('today');
                            $age = $dob->diff($today)->y;
                        }
                        $visit_status = $patient['latest_visit_status'] ?? 'pending';
                    ?>
                        <tr>
                            <td><?= $counter++ ?></td>
                            <td class="patient-cell">
                                <div class="patient-name"><?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></div>
                                <div class="patient-id"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></div>
                                <div class="patient-details">
                                    <?= htmlspecialchars($patient['gender'] ?? 'N/A') ?> • <?= $age ?> yrs
                                    <?php if (!empty($patient['blood_group'])): ?>
                                        • Blood: <?= htmlspecialchars($patient['blood_group']) ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td style="font-size:0.8rem;">
                                <?= htmlspecialchars($patient['phone'] ?? 'N/A') ?>
                                <?php if (!empty($patient['email'])): ?>
                                    <div style="font-size:0.65rem;color:var(--text-secondary);">
                                        <?= htmlspecialchars($patient['email']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($patient['latest_visit_number'])): ?>
                                    <strong><?= htmlspecialchars($patient['latest_visit_number']) ?></strong>
                                    <div style="font-size:0.65rem;color:var(--text-secondary);">
                                        <?= ucfirst($patient['visit_type'] ?? 'New') ?> visit
                                    </div>
                                <?php else: ?>
                                    <span class="text-xs text-gray-400">No visits</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge <?= $visit_status ?>">
                                    <?= ucfirst(str_replace('_', ' ', $visit_status)) ?>
                                </span>
                            </td>
                            <td style="font-size:0.7rem;color:var(--text-secondary);">
                                <?php if (!empty($patient['latest_visit_date'])): ?>
                                    <?= date('M d, Y', strtotime($patient['latest_visit_date'])) ?>
                                    <div style="font-size:0.6rem;">
                                        <?= date('h:i A', strtotime($patient['latest_visit_date'])) ?>
                                    </div>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="actions-cell">
                                    <?php if (!empty($patient['latest_visit_id'])): ?>
                                        <a href="consultation.php?visit_id=<?= $patient['latest_visit_id'] ?>" class="btn btn-success btn-sm">
                                            <i class="fas fa-stethoscope"></i> Consult
                                        </a>
                                    <?php else: ?>
                                        <a href="consultation.php?patient_id=<?= $patient['id'] ?>" class="btn btn-success btn-sm">
                                            <i class="fas fa-stethoscope"></i> Consult
                                        </a>
                                    <?php endif; ?>
                                    <a href="patient_profile.php?id=<?= $patient['id'] ?>" class="btn btn-outline btn-sm">
                                        <i class="fas fa-user"></i> View
                                    </a>
                                    <a href="refer_patient.php?patient_id=<?= $patient['id'] ?>" class="btn btn-purple btn-sm">
                                        <i class="fas fa-share-alt"></i> Refer
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-check-circle" style="color:var(--success);"></i>
                <div class="empty-title">No Pending Patients</div>
                <div class="empty-sub">All your patients have completed their consultations</div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- SECTION 2: COMPLETED PATIENTS -->
    <!-- ================================================================ -->
    <div class="section-header" style="margin-top:36px;">
        <div class="section-title">
            <i class="fas fa-check-circle" style="color:var(--success);"></i>
            Completed Patients
            <span class="count-badge completed"><?= $completed_count ?></span>
        </div>
        <div style="font-size:0.8rem;color:var(--text-secondary);">
            <i class="fas fa-info-circle"></i> Patients with completed consultations
        </div>
    </div>

    <div class="table-container" id="completedContainer">
        <?php if ($completed_count > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th><i class="fas fa-hashtag"></i> #</th>
                        <th><i class="fas fa-user"></i> Patient</th>
                        <th><i class="fas fa-phone"></i> Contact</th>
                        <th><i class="fas fa-notes-medical"></i> Visit</th>
                        <th><i class="fas fa-info-circle"></i> Status</th>
                        <th><i class="fas fa-calendar"></i> Completed</th>
                        <th><i class="fas fa-cog"></i> Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $counter = 1;
                    foreach ($completed_patients as $patient): 
                        $age = 'N/A';
                        if (!empty($patient['date_of_birth'])) {
                            $dob = new DateTime($patient['date_of_birth']);
                            $today = new DateTime('today');
                            $age = $dob->diff($today)->y;
                        }
                        $visit_status = $patient['latest_visit_status'] ?? 'completed';
                    ?>
                        <tr>
                            <td><?= $counter++ ?></td>
                            <td class="patient-cell">
                                <div class="patient-name"><?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></div>
                                <div class="patient-id"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></div>
                                <div class="patient-details">
                                    <?= htmlspecialchars($patient['gender'] ?? 'N/A') ?> • <?= $age ?> yrs
                                    <?php if (!empty($patient['blood_group'])): ?>
                                        • Blood: <?= htmlspecialchars($patient['blood_group']) ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td style="font-size:0.8rem;">
                                <?= htmlspecialchars($patient['phone'] ?? 'N/A') ?>
                                <?php if (!empty($patient['email'])): ?>
                                    <div style="font-size:0.65rem;color:var(--text-secondary);">
                                        <?= htmlspecialchars($patient['email']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($patient['latest_visit_number'])): ?>
                                    <strong><?= htmlspecialchars($patient['latest_visit_number']) ?></strong>
                                    <div style="font-size:0.65rem;color:var(--text-secondary);">
                                        <?= ucfirst($patient['visit_type'] ?? 'New') ?> visit
                                    </div>
                                <?php else: ?>
                                    <span class="text-xs text-gray-400">No visits</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge <?= $visit_status ?>">
                                    <?= ucfirst(str_replace('_', ' ', $visit_status)) ?>
                                </span>
                            </td>
                            <td style="font-size:0.7rem;color:var(--text-secondary);">
                                <?php if (!empty($patient['latest_visit_date'])): ?>
                                    <?= date('M d, Y', strtotime($patient['latest_visit_date'])) ?>
                                    <div style="font-size:0.6rem;">
                                        <?= date('h:i A', strtotime($patient['latest_visit_date'])) ?>
                                    </div>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="actions-cell">
                                    <a href="view_patient.php?id=<?= $patient['id'] ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="patient_profile.php?id=<?= $patient['id'] ?>" class="btn btn-outline btn-sm">
                                        <i class="fas fa-address-card"></i> Profile
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-clock" style="color:var(--text-secondary);"></i>
                <div class="empty-title">No Completed Patients</div>
                <div class="empty-sub">Patients will appear here once their consultations are completed</div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            My Patients
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
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
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
    }
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (sidebar && !sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    // ================================================================
    // DATE & TIME
    // ================================================================
    function updateFooterTime() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var footerTimestamp = document.getElementById('footerTimestamp');
        if (footerTimestamp) {
            footerTimestamp.textContent = 'Last updated: ' + timeStr;
        }
        
        var liveTime = document.getElementById('liveTime');
        if (liveTime) liveTime.textContent = timeStr;
    }
    updateFooterTime();

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
    // AUTO-UPDATE - EVERY 3 SECONDS
    // ================================================================
    var updateInterval = null;
    var isUpdating = false;
    var lastHash = null;
    var updateCount = 0;

    function fetchAndUpdatePatients() {
        if (isUpdating) return;
        isUpdating = true;
        updateCount++;
        
        var search = '<?= addslashes($search) ?>';
        
        fetch('get_patients.php?search=' + encodeURIComponent(search) + '&t=' + Date.now())
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    if (lastHash !== data.hash) {
                        lastHash = data.hash;
                        updatePatients(data.data);
                        updateFooterTime();
                        
                        if (updateCount > 1) {
                            showToast('🔄 Updated', 'Patients auto-updated at ' + data.timestamp, 'info');
                        }
                    }
                }
                isUpdating = false;
            })
            .catch(function(error) {
                console.error('Update error:', error);
                isUpdating = false;
            });
    }

    function updatePatients(data) {
        // Update pending container
        var pendingContainer = document.getElementById('pendingContainer');
        if (pendingContainer && data.pending_html) {
            pendingContainer.innerHTML = data.pending_html;
        }
        
        // Update completed container
        var completedContainer = document.getElementById('completedContainer');
        if (completedContainer && data.completed_html) {
            completedContainer.innerHTML = data.completed_html;
        }
        
        // Update counts in header
        var totalBadge = document.querySelector('.header-badge:first-child');
        var pendingBadge = document.querySelector('.header-badge:nth-child(2)');
        var completedBadge = document.querySelector('.header-badge:nth-child(3)');
        
        if (totalBadge) totalBadge.innerHTML = '<i class="fas fa-user"></i> ' + data.total + ' Total';
        if (pendingBadge) pendingBadge.innerHTML = '<i class="fas fa-clock" style="color:#D97706;"></i> ' + data.pending + ' Pending';
        if (completedBadge) completedBadge.innerHTML = '<i class="fas fa-check-circle" style="color:#059669;"></i> ' + data.completed + ' Completed';
        
        // Update section badges
        var pendingSectionBadge = document.querySelector('.section-header:first-child .count-badge.pending');
        var completedSectionBadge = document.querySelector('.section-header:last-child .count-badge.completed');
        
        if (pendingSectionBadge) pendingSectionBadge.textContent = data.pending;
        if (completedSectionBadge) completedSectionBadge.textContent = data.completed;
        
        // Update live time
        var liveTime = document.getElementById('liveTime');
        if (liveTime) liveTime.textContent = data.timestamp.split(' ')[1];
    }

    function startAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
        }
        fetchAndUpdatePatients();
        updateInterval = setInterval(fetchAndUpdatePatients, 3000);
        console.log('%c🔄 Auto-update started (every 3s)', 'font-size:12px; color:#34D399;');
    }
    
    function stopAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
            updateInterval = null;
            console.log('%c⏹️ Auto-update stopped', 'font-size:12px; color:#DC2626;');
        }
    }

    function manualRefresh() {
        var btn = document.getElementById('refreshBtn');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
        btn.disabled = true;
        
        lastHash = null;
        fetchAndUpdatePatients();
        
        setTimeout(function() {
            btn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh';
            btn.disabled = false;
            showToast('✅ Refreshed', 'Patients updated manually', 'success');
        }, 1500);
    }

    // ================================================================
    // VISIBILITY CHANGE - PAUSE WHEN HIDDEN
    // ================================================================
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopAutoUpdate();
        } else {
            startAutoUpdate();
        }
    });

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
        if (e.key === 'F5') {
            e.preventDefault();
            manualRefresh();
        }
    });

    // ================================================================
    // INITIALIZE
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            startAutoUpdate();
        }, 2000);
    });

    console.log('%c👨‍⚕️ Braick - My Patients (Two Sections - Table Format)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📊 Pending: <?= $pending_count ?> | Completed: <?= $completed_count ?>', 'font-size:13px; color:#64748B;');
    console.log('%c🔵 Table headers with blue gradient background', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📋 Pending: View & Consult buttons | Completed: View only', 'font-size:13px; color:#059669;');
    console.log('%c✅ Uses shared header for dark mode, date/time, status toggle', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>