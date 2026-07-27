<?php
// ================================================================
// FILE: frontend/pages/doctor/patient_profile.php
// DOCTOR - PATIENT PROFILE
// - View complete patient information
// - Personal details, visit history, lab results, prescriptions, bills
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
// GET PATIENT ID
// ================================================================
$patient_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($patient_id <= 0) {
    header('Location: my_patients.php?error=invalid_patient');
    exit;
}

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// GET PATIENT DETAILS
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT p.*, 
               u.full_name as assigned_doctor_name,
               u.specialty as assigned_doctor_specialty,
               b.name as branch_name
        FROM patients p
        LEFT JOIN users u ON p.assigned_doctor_id = u.id
        LEFT JOIN branches b ON p.branch_id = b.id
        WHERE p.id = ? AND p.branch_id = ?
    ");
    $stmt->execute([$patient_id, $doctor_branch_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        header('Location: my_patients.php?error=patient_not_found');
        exit;
    }
} catch (Exception $e) {
    error_log("Patient fetch error: " . $e->getMessage());
    header('Location: my_patients.php?error=database_error');
    exit;
}

// ================================================================
// GET VISIT HISTORY
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT v.*, 
               u.full_name as doctor_name,
               u.specialty as doctor_specialty,
               (SELECT COUNT(*) FROM lab_tests WHERE visit_id = v.id) as lab_count,
               (SELECT COUNT(*) FROM prescriptions WHERE visit_id = v.id) as prescription_count,
               (SELECT COUNT(*) FROM patient_bills WHERE visit_id = v.id) as bill_count,
               (SELECT COALESCE(SUM(total_amount), 0) FROM patient_bills WHERE visit_id = v.id) as total_bill_amount,
               (SELECT COALESCE(SUM(paid_amount), 0) FROM patient_bills WHERE visit_id = v.id) as total_paid_amount
        FROM visits v
        LEFT JOIN users u ON v.doctor_id = u.id
        WHERE v.patient_id = ?
        ORDER BY v.created_at DESC
    ");
    $stmt->execute([$patient_id]);
    $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Visits fetch error: " . $e->getMessage());
    $visits = [];
}

// ================================================================
// GET LAB RESULTS
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT lt.*, 
               v.visit_number,
               u.full_name as doctor_name,
               (SELECT full_name FROM users WHERE id = lt.lab_technician_id) as technician_name
        FROM lab_tests lt
        JOIN visits v ON lt.visit_id = v.id
        LEFT JOIN users u ON v.doctor_id = u.id
        WHERE v.patient_id = ?
        ORDER BY lt.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$patient_id]);
    $lab_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Lab results fetch error: " . $e->getMessage());
    $lab_results = [];
}

// ================================================================
// GET PRESCRIPTIONS
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT p.*, 
               v.visit_number,
               u.full_name as doctor_name
        FROM prescriptions p
        JOIN visits v ON p.visit_id = v.id
        LEFT JOIN users u ON v.doctor_id = u.id
        WHERE v.patient_id = ?
        ORDER BY p.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$patient_id]);
    $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Prescriptions fetch error: " . $e->getMessage());
    $prescriptions = [];
}

// ================================================================
// GET BILLS
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT b.*, 
               v.visit_number
        FROM patient_bills b
        JOIN visits v ON b.visit_id = v.id
        WHERE v.patient_id = ?
        ORDER BY b.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$patient_id]);
    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Bills fetch error: " . $e->getMessage());
    $bills = [];
}

// ================================================================
// CALCULATE AGE
// ================================================================
function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}

// ================================================================
// GET STATUS BADGE CLASS
// ================================================================
function getStatusBadgeClass($status) {
    $map = [
        'pending' => 'badge-warning',
        'assigned' => 'badge-info',
        'with_doctor' => 'badge-info',
        'lab_test' => 'badge-purple',
        'prescribed' => 'badge-purple',
        'completed' => 'badge-success',
        'cancelled' => 'badge-danger',
        'referred' => 'badge-purple'
    ];
    return $map[$status] ?? 'badge-info';
}

// ================================================================
// GET STATUS LABEL
// ================================================================
function getStatusLabel($status) {
    $map = [
        'pending' => 'Pending',
        'assigned' => 'Assigned',
        'with_doctor' => 'With Doctor',
        'lab_test' => 'Lab Test',
        'prescribed' => 'Prescribed',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'referred' => 'Referred'
    ];
    return $map[$status] ?? ucfirst($status);
}

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
    <title>Patient Profile - Braick Dispensary</title>
    
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
        
        /* ================================================================
           PROFILE CARD
           ================================================================ */
        .profile-card {
            background: var(--bg-card);
            border-radius: 14px;
            border: 1px solid var(--border-color);
            overflow: hidden;
            margin-bottom: 24px;
            box-shadow: var(--shadow);
        }
        
        .profile-card .profile-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            padding: 24px 28px;
            display: flex;
            align-items: center;
            gap: 24px;
            flex-wrap: wrap;
        }
        
        .profile-card .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            font-weight: 700;
            color: white;
            border: 3px solid rgba(255,255,255,0.3);
            flex-shrink: 0;
        }
        
        .profile-card .profile-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
        }
        
        .profile-card .profile-id {
            font-size: 0.85rem;
            color: rgba(255,255,255,0.8);
            font-family: monospace;
        }
        
        .profile-card .profile-details {
            color: rgba(255,255,255,0.85);
            font-size: 0.9rem;
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
        }
        
        .profile-card .profile-details .detail-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .profile-card .profile-body {
            padding: 20px 28px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        
        .profile-card .profile-body .info-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .profile-card .profile-body .info-group .label {
            font-size: 0.65rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .profile-card .profile-body .info-group .value {
            font-size: 0.9rem;
            color: var(--text-primary);
        }
        
        /* ================================================================
           SECTION HEADERS
           ================================================================ */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 28px 0 16px 0;
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
        
        .section-header .section-title .count-badge.primary {
            background: var(--primary-bg);
            color: var(--primary);
        }
        
        .section-header .section-title .count-badge.success {
            background: var(--success-bg);
            color: var(--success);
        }
        
        .section-header .section-title .count-badge.purple {
            background: var(--purple-bg);
            color: var(--purple);
        }
        
        .section-header .section-title .count-badge.warning {
            background: var(--warning-bg);
            color: var(--warning);
        }
        
        /* ================================================================
           TABLE CONTAINER
           ================================================================ */
        .table-container {
            background: var(--bg-card);
            border-radius: 14px;
            border: 1px solid var(--border-color);
            overflow: hidden;
            box-shadow: var(--shadow);
            overflow-x: auto;
        }
        
        .table-container table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        
        .table-container thead {
            background: #0B5ED7 !important;
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8) !important;
        }
        
        .table-container thead th {
            padding: 12px 16px;
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
        
        .table-container tbody td {
            padding: 10px 16px;
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
           BADGES
           ================================================================ */
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
        .badge-purple { background: var(--purple-bg); color: var(--purple); }
        
        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 5px 14px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.7rem;
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
            font-size: 0.95rem;
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
        }
        
        @media (max-width: 768px) {
            .page-header-custom { padding: 16px 18px; }
            .page-header-custom .page-title { font-size: 1.3rem; }
            .profile-card .profile-header { flex-direction: column; text-align: center; }
            .profile-card .profile-body { grid-template-columns: 1fr; }
            .table-container table { font-size: 0.75rem; }
            .table-container thead th, .table-container tbody td { padding: 6px 10px; }
            .section-header { flex-direction: column; align-items: flex-start; gap: 8px; }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .profile-card .profile-header { padding: 16px; }
            .profile-card .profile-body { padding: 14px 16px; }
            .table-container thead th, .table-container tbody td { padding: 4px 8px; font-size: 0.65rem; }
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
                <i class="fas fa-user-circle"></i>
                Patient Profile
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;">DOCTOR</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-id-card"></i>
                Complete patient information and history
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="my_patients.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <a href="consultation.php?patient_id=<?= $patient_id ?>" class="btn-outline-light">
                <i class="fas fa-stethoscope"></i> New Visit
            </a>
            <a href="refer_patient.php?patient_id=<?= $patient_id ?>" class="btn-outline-light">
                <i class="fas fa-share-alt"></i> Refer
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PROFILE CARD -->
    <!-- ================================================================ -->
    <div class="profile-card">
        <div class="profile-header">
            <?php 
                $initial = strtoupper(substr($patient['full_name'] ?? 'U', 0, 1));
                $colors = ['#0B5ED7', '#059669', '#7C3AED', '#DC2626', '#D97706', '#0D9488', '#DB2777'];
                $color = $colors[abs(crc32($patient['full_name'] ?? 'U')) % count($colors)];
                $age = calculateAge($patient['date_of_birth'] ?? '');
            ?>
            <div class="profile-avatar" style="background:<?= $color ?>;">
                <?= $initial ?>
            </div>
            <div style="flex:1;">
                <div class="profile-name"><?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></div>
                <div class="profile-id">ID: <?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></div>
                <div class="profile-details">
                    <span class="detail-item"><i class="fas fa-venus-mars"></i> <?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></span>
                    <span class="detail-item"><i class="fas fa-calendar-alt"></i> <?= $age ?> years</span>
                    <span class="detail-item"><i class="fas fa-tint"></i> <?= htmlspecialchars($patient['blood_group'] ?? 'N/A') ?></span>
                    <span class="detail-item"><i class="fas fa-phone"></i> <?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></span>
                    <span class="detail-item"><i class="fas fa-store-alt"></i> <?= htmlspecialchars($patient['branch_name'] ?? 'N/A') ?></span>
                </div>
            </div>
            <div>
                <span class="badge badge-info">Registered: <?= date('M d, Y', strtotime($patient['created_at'])) ?></span>
            </div>
        </div>
        <div class="profile-body">
            <div class="info-group">
                <span class="label"><i class="fas fa-envelope"></i> Email</span>
                <span class="value"><?= htmlspecialchars($patient['email'] ?? 'N/A') ?></span>
            </div>
            <div class="info-group">
                <span class="label"><i class="fas fa-map-marker-alt"></i> Address</span>
                <span class="value"><?= htmlspecialchars($patient['address'] ?? 'N/A') ?></span>
            </div>
            <div class="info-group">
                <span class="label"><i class="fas fa-user-md"></i> Assigned Doctor</span>
                <span class="value"><?= !empty($patient['assigned_doctor_name']) ? 'Dr. ' . htmlspecialchars($patient['assigned_doctor_name']) : 'Not Assigned' ?></span>
            </div>
            <div class="info-group">
                <span class="label"><i class="fas fa-stethoscope"></i> Specialty</span>
                <span class="value"><?= htmlspecialchars($patient['assigned_doctor_specialty'] ?? 'N/A') ?></span>
            </div>
            <div class="info-group">
                <span class="label"><i class="fas fa-exclamation-triangle"></i> Allergies</span>
                <span class="value" style="color:<?= !empty($patient['allergies']) ? 'var(--danger)' : 'var(--text-secondary)' ?>;">
                    <?= !empty($patient['allergies']) ? htmlspecialchars($patient['allergies']) : 'None reported' ?>
                </span>
            </div>
            <div class="info-group">
                <span class="label"><i fa="fas fa-phone-alt"></i> Emergency Contact</span>
                <span class="value"><?= htmlspecialchars($patient['emergency_contact'] ?? 'N/A') ?></span>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- VISIT HISTORY -->
    <!-- ================================================================ -->
    <div class="section-header">
        <div class="section-title">
            <i class="fas fa-notes-medical" style="color:var(--primary);"></i>
            Visit History
            <span class="count-badge primary"><?= count($visits) ?></span>
        </div>
    </div>

    <div class="table-container" style="margin-bottom:24px;">
        <?php if (count($visits) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th><i class="fas fa-hashtag"></i> #</th>
                        <th><i class="fas fa-file-medical"></i> Visit</th>
                        <th><i class="fas fa-user-md"></i> Doctor</th>
                        <th><i class="fas fa-info-circle"></i> Status</th>
                        <th><i class="fas fa-calendar"></i> Date</th>
                        <th><i class="fas fa-flask"></i> Lab</th>
                        <th><i class="fas fa-prescription"></i> Rx</th>
                        <th><i class="fas fa-receipt"></i> Bill</th>
                        <th><i class="fas fa-cog"></i> Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $counter = 1;
                    foreach ($visits as $visit): 
                    ?>
                        <tr>
                            <td><?= $counter++ ?></td>
                            <td>
                                <strong><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></strong>
                                <div style="font-size:0.65rem;color:var(--text-secondary);">
                                    <?= ucfirst($visit['visit_type'] ?? 'New') ?> visit
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($visit['doctor_name'])): ?>
                                    Dr. <?= htmlspecialchars($visit['doctor_name']) ?>
                                    <div style="font-size:0.6rem;color:var(--text-secondary);">
                                        <?= htmlspecialchars($visit['doctor_specialty'] ?? '') ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-xs text-gray-400">Not assigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= getStatusBadgeClass($visit['status']) ?>">
                                    <?= getStatusLabel($visit['status']) ?>
                                </span>
                            </td>
                            <td style="font-size:0.75rem;color:var(--text-secondary);">
                                <?= date('M d, Y', strtotime($visit['created_at'])) ?>
                                <div style="font-size:0.6rem;">
                                    <?= date('h:i A', strtotime($visit['created_at'])) ?>
                                </div>
                            </td>
                            <td style="text-align:center;">
                                <?= $visit['lab_count'] ?? 0 ?>
                            </td>
                            <td style="text-align:center;">
                                <?= $visit['prescription_count'] ?? 0 ?>
                            </td>
                            <td>
                                <?php if (($visit['bill_count'] ?? 0) > 0): ?>
                                    <div style="font-size:0.7rem;">
                                        TSh <?= number_format($visit['total_bill_amount'] ?? 0, 0) ?>
                                        <div style="font-size:0.6rem;color:var(--success);">
                                            Paid: TSh <?= number_format($visit['total_paid_amount'] ?? 0, 0) ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="text-xs text-gray-400">No bills</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display:flex;gap:4px;flex-wrap:wrap;">
                                    <a href="consultation.php?visit_id=<?= $visit['id'] ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-stethoscope"></i>
                                    </a>
                                    <a href="view_visit.php?id=<?= $visit['id'] ?>" class="btn btn-outline btn-sm">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-notes-medical"></i>
                <div class="empty-title">No Visit History</div>
                <div class="empty-sub">This patient has no recorded visits yet</div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- LAB RESULTS -->
    <!-- ================================================================ -->
    <div class="section-header">
        <div class="section-title">
            <i class="fas fa-flask" style="color:var(--purple);"></i>
            Lab Results
            <span class="count-badge purple"><?= count($lab_results) ?></span>
        </div>
        <?php if (count($lab_results) > 0): ?>
            <a href="lab_results.php?patient_id=<?= $patient_id ?>" class="btn btn-outline btn-sm">
                View All <i class="fas fa-arrow-right"></i>
            </a>
        <?php endif; ?>
    </div>

    <div class="table-container" style="margin-bottom:24px;">
        <?php if (count($lab_results) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th><i class="fas fa-hashtag"></i> #</th>
                        <th><i class="fas fa-vial"></i> Test Name</th>
                        <th><i class="fas fa-user-md"></i> Doctor</th>
                        <th><i class="fas fa-info-circle"></i> Status</th>
                        <th><i class="fas fa-file-alt"></i> Result</th>
                        <th><i class="fas fa-calendar"></i> Date</th>
                        <th><i class="fas fa-cog"></i> Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $counter = 1;
                    foreach ($lab_results as $lab): 
                    ?>
                        <tr>
                            <td><?= $counter++ ?></td>
                            <td>
                                <strong><?= htmlspecialchars($lab['test_name'] ?? 'N/A') ?></strong>
                                <div style="font-size:0.6rem;color:var(--text-secondary);">
                                    <?= htmlspecialchars($lab['visit_number'] ?? 'N/A') ?>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($lab['doctor_name'])): ?>
                                    Dr. <?= htmlspecialchars($lab['doctor_name']) ?>
                                <?php else: ?>
                                    <span class="text-xs text-gray-400">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= getStatusBadgeClass($lab['status']) ?>">
                                    <?= getStatusLabel($lab['status']) ?>
                                </span>
                            </td>
                            <td style="max-width:150px;font-size:0.8rem;word-break:break-word;">
                                <?php if (!empty($lab['results']) && $lab['status'] === 'completed'): ?>
                                    <?= htmlspecialchars(substr($lab['results'], 0, 60)) ?>
                                    <?php if (strlen($lab['results']) > 60): ?>...<?php endif; ?>
                                <?php else: ?>
                                    <span class="text-xs text-gray-400">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:0.75rem;color:var(--text-secondary);">
                                <?= date('M d, Y', strtotime($lab['created_at'])) ?>
                                <div style="font-size:0.6rem;">
                                    <?= date('h:i A', strtotime($lab['created_at'])) ?>
                                </div>
                            </td>
                            <td>
                                <div style="display:flex;gap:4px;flex-wrap:wrap;">
                                    <a href="view_lab_result.php?id=<?= $lab['id'] ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($lab['status'] === 'completed' && !empty($lab['results'])): ?>
                                        <a href="print_lab_result.php?id=<?= $lab['id'] ?>" target="_blank" class="btn btn-outline btn-sm">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-flask"></i>
                <div class="empty-title">No Lab Results</div>
                <div class="empty-sub">No lab tests have been performed for this patient</div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- PRESCRIPTIONS -->
    <!-- ================================================================ -->
    <div class="section-header">
        <div class="section-title">
            <i class="fas fa-prescription" style="color:var(--success);"></i>
            Prescriptions
            <span class="count-badge success"><?= count($prescriptions) ?></span>
        </div>
        <?php if (count($prescriptions) > 0): ?>
            <a href="prescriptions.php?patient_id=<?= $patient_id ?>" class="btn btn-outline btn-sm">
                View All <i class="fas fa-arrow-right"></i>
            </a>
        <?php endif; ?>
    </div>

    <div class="table-container" style="margin-bottom:24px;">
        <?php if (count($prescriptions) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th><i class="fas fa-hashtag"></i> #</th>
                        <th><i class="fas fa-pills"></i> Medication</th>
                        <th><i class="fas fa-user-md"></i> Doctor</th>
                        <th><i class="fas fa-info-circle"></i> Status</th>
                        <th><i class="fas fa-calendar"></i> Date</th>
                        <th><i class="fas fa-cog"></i> Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $counter = 1;
                    foreach ($prescriptions as $prescription): 
                    ?>
                        <tr>
                            <td><?= $counter++ ?></td>
                            <td>
                                <strong><?= htmlspecialchars($prescription['medication'] ?? 'N/A') ?></strong>
                                <div style="font-size:0.6rem;color:var(--text-secondary);">
                                    <?= htmlspecialchars($prescription['visit_number'] ?? 'N/A') ?>
                                </div>
                                <?php if (!empty($prescription['dosage'])): ?>
                                    <div style="font-size:0.65rem;color:var(--text-secondary);">
                                        <?= htmlspecialchars($prescription['dosage']) ?> • 
                                        <?= htmlspecialchars($prescription['frequency'] ?? '') ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($prescription['doctor_name'])): ?>
                                    Dr. <?= htmlspecialchars($prescription['doctor_name']) ?>
                                <?php else: ?>
                                    <span class="text-xs text-gray-400">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $prescription['status'] === 'dispensed' ? 'badge-success' : 'badge-warning' ?>">
                                    <?= ucfirst($prescription['status'] ?? 'Pending') ?>
                                </span>
                            </td>
                            <td style="font-size:0.75rem;color:var(--text-secondary);">
                                <?= date('M d, Y', strtotime($prescription['created_at'])) ?>
                                <div style="font-size:0.6rem;">
                                    <?= date('h:i A', strtotime($prescription['created_at'])) ?>
                                </div>
                            </td>
                            <td>
                                <div style="display:flex;gap:4px;flex-wrap:wrap;">
                                    <a href="view_prescription.php?id=<?= $prescription['id'] ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($prescription['status'] === 'dispensed'): ?>
                                        <a href="print_prescription.php?id=<?= $prescription['id'] ?>" target="_blank" class="btn btn-outline btn-sm">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-prescription"></i>
                <div class="empty-title">No Prescriptions</div>
                <div class="empty-sub">No prescriptions have been issued for this patient</div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- BILLS -->
    <!-- ================================================================ -->
    <div class="section-header">
        <div class="section-title">
            <i class="fas fa-receipt" style="color:var(--warning);"></i>
            Billing History
            <span class="count-badge warning"><?= count($bills) ?></span>
        </div>
        <?php if (count($bills) > 0): ?>
            <a href="bills.php?patient_id=<?= $patient_id ?>" class="btn btn-outline btn-sm">
                View All <i class="fas fa-arrow-right"></i>
            </a>
        <?php endif; ?>
    </div>

    <div class="table-container">
        <?php if (count($bills) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th><i class="fas fa-hashtag"></i> #</th>
                        <th><i class="fas fa-file-invoice"></i> Bill Number</th>
                        <th><i class="fas fa-notes-medical"></i> Visit</th>
                        <th><i class="fas fa-money-bill-wave"></i> Total</th>
                        <th><i class="fas fa-check-circle"></i> Paid</th>
                        <th><i class="fas fa-info-circle"></i> Status</th>
                        <th><i class="fas fa-calendar"></i> Date</th>
                        <th><i class="fas fa-cog"></i> Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $counter = 1;
                    foreach ($bills as $bill): 
                        $balance = ($bill['total_amount'] ?? 0) - ($bill['paid_amount'] ?? 0);
                        $status = $bill['status'] ?? 'pending';
                    ?>
                        <tr>
                            <td><?= $counter++ ?></td>
                            <td>
                                <strong><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></strong>
                            </td>
                            <td style="font-size:0.75rem;">
                                <?= htmlspecialchars($bill['visit_number'] ?? 'N/A') ?>
                            </td>
                            <td>
                                <strong>TSh <?= number_format($bill['total_amount'] ?? 0, 0) ?></strong>
                            </td>
                            <td style="color:var(--success);">
                                TSh <?= number_format($bill['paid_amount'] ?? 0, 0) ?>
                                <?php if ($balance > 0): ?>
                                    <div style="font-size:0.6rem;color:var(--danger);">
                                        Balance: TSh <?= number_format($balance, 0) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $status === 'paid' ? 'badge-success' : ($status === 'pending' ? 'badge-warning' : 'badge-info') ?>">
                                    <?= ucfirst($status) ?>
                                </span>
                            </td>
                            <td style="font-size:0.75rem;color:var(--text-secondary);">
                                <?= date('M d, Y', strtotime($bill['created_at'])) ?>
                                <div style="font-size:0.6rem;">
                                    <?= date('h:i A', strtotime($bill['created_at'])) ?>
                                </div>
                            </td>
                            <td>
                                <div style="display:flex;gap:4px;flex-wrap:wrap;">
                                    <a href="view_bill.php?id=<?= $bill['id'] ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($status === 'paid'): ?>
                                        <a href="print_receipt.php?id=<?= $bill['id'] ?>" target="_blank" class="btn btn-outline btn-sm">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-receipt"></i>
                <div class="empty-title">No Billing History</div>
                <div class="empty-sub">No bills have been generated for this patient</div>
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
            Patient Profile
            <span class="text-gray-300 mx-2">|</span>
            <?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?>
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

    console.log('%c👨‍⚕️ Braick - Patient Profile (Using Shared Header)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Patient: <?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📊 Visits: <?= count($visits) ?> | Lab Results: <?= count($lab_results) ?> | Prescriptions: <?= count($prescriptions) ?> | Bills: <?= count($bills) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c✅ Uses shared header for dark mode, date/time, status toggle', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>