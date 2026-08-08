<?php
// ================================================================
// FILE: frontend/pages/doctor/view_lab_result.php
// DOCTOR - VIEW LAB RESULT DETAILS
// BRAICK DISPENSARY - BLUE THEME
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
// GET LAB TEST ID
// ================================================================
$lab_test_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($lab_test_id <= 0) {
    header('Location: lab_results.php?error=invalid_id');
    exit;
}

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// FETCH LAB TEST DETAILS - REMOVED NON-EXISTENT COLUMNS
// ================================================================
$sql = "
    SELECT 
        lt.id,
        lt.test_name,
        lt.status,
        lt.results,
        lt.reference_range,
        lt.interpretation,
        lt.notes as lab_notes,
        lt.created_at,
        lt.completed_at,
        lt.updated_at,
        lt.lab_technician_id,
        lt.formatted_result,
        p.id as patient_id,
        p.patient_id as patient_code,
        p.full_name as patient_name,
        p.phone,
        p.gender,
        p.date_of_birth,
        p.blood_group,
        p.allergies,
        p.address,
        u.full_name as doctor_name,
        u.specialty as doctor_specialty,
        v.visit_number,
        v.visit_type,
        v.id as visit_id,
        v.symptoms,
        v.complaint,
        v.diagnosis,
        v.treatment,
        (SELECT full_name FROM users WHERE id = lt.lab_technician_id) as technician_name
    FROM lab_tests lt
    JOIN visits v ON lt.visit_id = v.id
    JOIN patients p ON v.patient_id = p.id
    LEFT JOIN users u ON v.doctor_id = u.id
    WHERE lt.id = ?
";

$stmt = $db->prepare($sql);
$stmt->execute([$lab_test_id]);
$lab_test = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lab_test) {
    header('Location: lab_results.php?error=notfound');
    exit;
}

// ================================================================
// CALCULATE AGE FROM DATE OF BIRTH
// ================================================================
$age = null;
if (!empty($lab_test['date_of_birth'])) {
    $birthDate = new DateTime($lab_test['date_of_birth']);
    $today = new DateTime('today');
    $age = $birthDate->diff($today)->y;
}

// ================================================================
// GET BRANCH DETAILS
// ================================================================
$branch_name = $doctor_branch_name;
$branch_phone = '+255 700 000 001';
$branch_email = 'info@braick.com';
$branch_address = 'Dodoma City, Tanzania';

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
    <title>Lab Result - <?= htmlspecialchars($lab_test['test_name']) ?> - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!-- html2pdf for PDF generation -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
    <style>
        /* ================================================================
           ROOT VARIABLES - BLUE THEME
           ================================================================ */
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
            --primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            
            --success: #059669;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --purple: #7C3AED;
            --purple-bg: #EDE9FE;
            
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 10px 30px rgba(0,0,0,0.4);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
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
           PAGE HEADER - BLUE THEME
           ================================================================ */
        .page-header-custom {
            background: var(--primary-gradient);
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
        
        .page-header-custom .page-subtitle strong {
            color: white;
            font-weight: 600;
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
        
        /* ================================================================
           STATUS BADGE
           ================================================================ */
        .status-badge {
            display: inline-block;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-badge.completed {
            background: var(--success-bg);
            color: var(--success);
        }
        
        .status-badge.pending {
            background: var(--warning-bg);
            color: var(--warning);
        }
        
        .status-badge.in_progress {
            background: var(--primary-bg);
            color: var(--primary);
        }
        
        .status-badge.cancelled {
            background: var(--danger-bg);
            color: var(--danger);
        }
        
        /* ================================================================
           REPORT CARD - BLUE THEME
           ================================================================ */
        .report-card {
            background: var(--bg-card);
            border-radius: 16px;
            border: 1px solid var(--border-color);
            overflow: hidden;
            box-shadow: var(--shadow-md);
            max-width: 1100px;
            margin: 0 auto;
            transition: all 0.3s ease;
        }
        
        .report-card:hover {
            box-shadow: var(--shadow-lg);
        }
        
        /* Report Header - BLUE */
        .report-header {
            background: var(--primary-gradient);
            padding: 24px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            border-bottom: 3px solid var(--primary-dark);
        }
        
        .report-header .logo-section {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .report-header .logo-section .logo-img {
            height: 50px;
            width: auto;
            border-radius: 8px;
        }
        
        .report-header .logo-section .brand-text {
            color: white;
        }
        
        .report-header .logo-section .brand-text h2 {
            font-size: 1.2rem;
            font-weight: 700;
            margin: 0;
            letter-spacing: 0.5px;
        }
        
        .report-header .logo-section .brand-text p {
            font-size: 0.7rem;
            opacity: 0.8;
            margin: 0;
        }
        
        .report-header .report-title {
            color: white;
            text-align: right;
        }
        
        .report-header .report-title h3 {
            font-size: 1rem;
            font-weight: 600;
            margin: 0;
        }
        
        .report-header .report-title p {
            font-size: 0.7rem;
            opacity: 0.8;
            margin: 0;
        }
        
        /* Report Body */
        .report-body {
            padding: 28px 32px;
        }
        
        /* Patient Info - Blue Border */
        .patient-info-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            padding: 16px 20px;
            background: var(--primary-bg);
            border-radius: 12px;
            border-left: 4px solid var(--primary);
            margin-bottom: 24px;
        }
        
        .patient-info-section .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .patient-info-section .info-item .label {
            font-size: 0.6rem;
            text-transform: uppercase;
            color: var(--text-secondary);
            font-weight: 600;
            letter-spacing: 0.05em;
        }
        
        .patient-info-section .info-item .value {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        /* Test Result Section */
        .result-section {
            margin-top: 20px;
        }
        
        .result-section .section-title {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--primary);
            border-bottom: 2px solid var(--primary);
            padding-bottom: 8px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .result-section .section-title i {
            color: var(--primary);
        }
        
        .result-detail {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        
        .result-detail .result-item {
            background: var(--bg-body);
            padding: 12px 16px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
        }
        
        .result-detail .result-item .label {
            font-size: 0.65rem;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        
        .result-detail .result-item .value {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-top: 2px;
        }
        
        .result-detail .result-item .value.blue { color: var(--primary); }
        .result-detail .result-item .value.green { color: var(--success); }
        .result-detail .result-item .value.red { color: var(--danger); }
        
        /* Full Width Result */
        .result-full {
            background: var(--bg-body);
            padding: 16px 20px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            margin-top: 12px;
        }
        
        .result-full .label {
            font-size: 0.65rem;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        
        .result-full .value {
            font-size: 0.9rem;
            color: var(--text-primary);
            margin-top: 4px;
            white-space: pre-wrap;
            line-height: 1.6;
        }
        
        /* ================================================================
           BUTTONS - BLUE THEME
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
            font-family: inherit;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn-primary {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 2px 12px rgba(11, 94, 215, 0.25);
        }
        
        .btn-primary:hover {
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.35);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
            box-shadow: 0 2px 12px rgba(5, 150, 105, 0.25);
        }
        
        .btn-success:hover {
            box-shadow: 0 4px 20px rgba(5, 150, 105, 0.35);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        
        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .btn-sm {
            padding: 5px 12px;
            font-size: 0.7rem;
        }
        
        .btn-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            padding: 16px 32px 24px;
            border-top: 1px solid var(--border-color);
            background: var(--bg-body);
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
            .main-content { margin-left: 0; padding: 16px; }
            .report-body { padding: 20px; }
            .result-detail { grid-template-columns: 1fr; }
        }
        
        @media (max-width: 768px) {
            .page-header-custom { padding: 16px 18px; }
            .page-header-custom .page-title { font-size: 1.3rem; }
            .report-header { padding: 16px 20px; flex-direction: column; text-align: center; }
            .report-header .report-title { text-align: center; }
            .report-body { padding: 16px; }
            .patient-info-section { grid-template-columns: 1fr 1fr; }
            .btn-actions { padding: 12px 16px 16px; flex-direction: column; }
            .btn-actions .btn { justify-content: center; }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .patient-info-section { grid-template-columns: 1fr; }
            .report-header .logo-section .brand-text h2 { font-size: 1rem; }
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
           PRINT STYLES
           ================================================================ */
        @media print {
            .top-nav, .sidebar, .btn-outline-light, .btn-actions,
            .footer, #sidebarToggle, .dark-toggle-btn, .icon-btn,
            .search-wrapper, #refreshBtn { display: none !important; }
            
            .main-content { margin: 0; padding: 10px; }
            .report-card { border: 1px solid #ddd; box-shadow: none; }
            .report-header { background: #0B5ED7 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .report-header .logo-section .brand-text h2 { color: white; }
            .report-header .logo-section .brand-text p { color: rgba(255,255,255,0.8); }
            .report-header .report-title h3 { color: white; }
            .report-header .report-title p { color: rgba(255,255,255,0.8); }
            .patient-info-section { background: #E8F0FE !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; border-left-color: #0B5ED7 !important; }
            .btn { display: none !important; }
            .page-header-custom { background: #0B5ED7 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .page-title, .page-subtitle, .role-badge-display, .header-badge { color: white !important; }
            .status-badge { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- PAGE HEADER - BLUE THEME -->
    <!-- ================================================================ -->
    <div class="page-header-custom">
        <div>
            <h1 class="page-title">
                <i class="fas fa-flask"></i>
                Lab Result Details
                <span class="role-badge-display">DOCTOR</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-vial"></i>
                <strong><?= htmlspecialchars($lab_test['test_name']) ?></strong>
                <span class="header-badge">
                    <i class="fas fa-user"></i>
                    <?= htmlspecialchars($lab_test['patient_name']) ?>
                </span>
                <span class="header-badge">
                    <i class="fas fa-<?= $lab_test['status'] === 'completed' ? 'check-circle' : 'clock' ?>"></i>
                    <?= ucfirst(str_replace('_', ' ', $lab_test['status'] ?? 'Pending')) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="lab_results.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <a href="consultation.php?visit_id=<?= $lab_test['visit_id'] ?>" class="btn-outline-light">
                <i class="fas fa-stethoscope"></i> Consultation
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- LAB RESULT REPORT - BLUE THEME -->
    <!-- ================================================================ -->
    <div class="report-card" id="labResultReport">
        
        <!-- Report Header - BLUE -->
        <div class="report-header">
            <div class="logo-section">
                <div>
                    <img src="/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png" 
                         alt="Braick Dispensary" 
                         class="logo-img"
                         onerror="this.style.display='none'">
                </div>
                <div class="brand-text">
                    <h2>BRAICK DISPENSARY</h2>
                    <p>Quality Healthcare Services</p>
                </div>
            </div>
            <div class="report-title">
                <h3><i class="fas fa-flask" style="margin-right:6px;"></i> LABORATORY REPORT</h3>
                <p>Report #: <?= 'LAB-' . str_pad($lab_test['id'], 4, '0', STR_PAD_LEFT) ?></p>
            </div>
        </div>
        
        <!-- Report Body -->
        <div class="report-body">
            
            <!-- Patient Information - Blue Border -->
            <div class="patient-info-section">
                <div class="info-item">
                    <span class="label"><i class="fas fa-user"></i> Patient Name</span>
                    <span class="value"><?= htmlspecialchars($lab_test['patient_name'] ?? 'N/A') ?></span>
                </div>
                <div class="info-item">
                    <span class="label"><i class="fas fa-id-card"></i> Patient ID</span>
                    <span class="value"><?= htmlspecialchars($lab_test['patient_code'] ?? 'N/A') ?></span>
                </div>
                <div class="info-item">
                    <span class="label"><i class="fas fa-calendar-alt"></i> Age / Gender</span>
                    <span class="value"><?= $age !== null ? $age . ' yrs' : 'N/A' ?> / <?= htmlspecialchars($lab_test['gender'] ?? 'N/A') ?></span>
                </div>
                <div class="info-item">
                    <span class="label"><i class="fas fa-phone"></i> Phone</span>
                    <span class="value"><?= htmlspecialchars($lab_test['phone'] ?? 'N/A') ?></span>
                </div>
                <div class="info-item">
                    <span class="label"><i class="fas fa-tint"></i> Blood Group</span>
                    <span class="value"><?= htmlspecialchars($lab_test['blood_group'] ?? 'N/A') ?></span>
                </div>
                <div class="info-item">
                    <span class="label"><i class="fas fa-user-md"></i> Requested By</span>
                    <span class="value">Dr. <?= htmlspecialchars($lab_test['doctor_name'] ?? 'N/A') ?></span>
                </div>
                <div class="info-item">
                    <span class="label"><i class="fas fa-calendar-plus"></i> Request Date</span>
                    <span class="value"><?= date('M d, Y h:i A', strtotime($lab_test['created_at'] ?? 'now')) ?></span>
                </div>
                <div class="info-item">
                    <span class="label"><i class="fas fa-check-circle"></i> Status</span>
                    <span class="value">
                        <span class="status-badge <?= $lab_test['status'] ?? 'pending' ?>">
                            <?= ucfirst(str_replace('_', ' ', $lab_test['status'] ?? 'Pending')) ?>
                        </span>
                    </span>
                </div>
            </div>
            
            <!-- Test Results -->
            <div class="result-section">
                <div class="section-title">
                    <i class="fas fa-vial"></i>
                    Test Results
                </div>
                
                <div class="result-detail">
                    <div class="result-item">
                        <div class="label">Test Name</div>
                        <div class="value blue"><?= htmlspecialchars($lab_test['test_name'] ?? 'N/A') ?></div>
                    </div>
                    <div class="result-item">
                        <div class="label">Visit Number</div>
                        <div class="value"><?= htmlspecialchars($lab_test['visit_number'] ?? 'N/A') ?></div>
                    </div>
                    <?php if (!empty($lab_test['technician_name'])): ?>
                    <div class="result-item">
                        <div class="label">Lab Technician</div>
                        <div class="value"><?= htmlspecialchars($lab_test['technician_name']) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($lab_test['completed_at'])): ?>
                    <div class="result-item">
                        <div class="label">Completed Date</div>
                        <div class="value green"><?= date('M d, Y h:i A', strtotime($lab_test['completed_at'])) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Reference Range -->
                <?php if (!empty($lab_test['reference_range'])): ?>
                <div class="result-full" style="margin-top:12px;">
                    <div class="label"><i class="fas fa-chart-bar"></i> Reference Range</div>
                    <div class="value"><?= htmlspecialchars($lab_test['reference_range']) ?></div>
                </div>
                <?php endif; ?>
                
                <!-- Results -->
                <div class="result-full">
                    <div class="label"><i class="fas fa-file-medical-alt"></i> Results</div>
                    <div class="value">
                        <?php if (!empty($lab_test['formatted_result'])): ?>
                            <?= $lab_test['formatted_result'] ?>
                        <?php elseif (!empty($lab_test['results'])): ?>
                            <?= nl2br(htmlspecialchars($lab_test['results'])) ?>
                        <?php else: ?>
                            <span class="text-gray-400">No results available yet</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Interpretation -->
                <?php if (!empty($lab_test['interpretation'])): ?>
                <div class="result-full" style="margin-top:12px;border-color:var(--primary);background:var(--primary-bg);">
                    <div class="label"><i class="fas fa-stethoscope"></i> Interpretation</div>
                    <div class="value" style="color:var(--primary);"><?= nl2br(htmlspecialchars($lab_test['interpretation'])) ?></div>
                </div>
                <?php endif; ?>
                
                <!-- Notes -->
                <?php if (!empty($lab_test['lab_notes'])): ?>
                <div class="result-full" style="margin-top:12px;">
                    <div class="label"><i class="fas fa-sticky-note"></i> Notes</div>
                    <div class="value" style="font-style:italic;color:var(--text-secondary);"><?= nl2br(htmlspecialchars($lab_test['lab_notes'])) ?></div>
                </div>
                <?php endif; ?>
                
                <!-- Technician Info -->
                <?php if (!empty($lab_test['technician_name']) && !empty($lab_test['completed_at'])): ?>
                <div style="margin-top:16px;text-align:right;font-size:0.7rem;color:var(--text-secondary);">
                    <i class="fas fa-user-check" style="color:var(--success);"></i>
                    Result added by: <strong><?= htmlspecialchars($lab_test['technician_name']) ?></strong>
                    on <?= date('M d, Y h:i A', strtotime($lab_test['completed_at'])) ?>
                </div>
                <?php endif; ?>
            </div>
            
        </div>
        
        <!-- ================================================================ -->
        <!-- ACTION BUTTONS - Includes Print PDF Button -->
        <!-- ================================================================ -->
        <div class="btn-actions">
            <button onclick="printPDF()" class="btn btn-success">
                <i class="fas fa-file-pdf"></i> Print PDF
            </button>
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print"></i> Print
            </button>
            <?php if ($lab_test['status'] === 'completed'): ?>
                <a href="consultation.php?visit_id=<?= $lab_test['visit_id'] ?>" class="btn btn-primary">
                    <i class="fas fa-stethoscope"></i> Open Consultation
                </a>
            <?php endif; ?>
            <a href="lab_results.php" class="btn btn-outline" style="margin-left:auto;">
                <i class="fas fa-arrow-left"></i> Back to Results
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Lab Result Details
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp"><?= date('H:i:s') ?></span>
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
            footerTimestamp.textContent = timeStr;
        }
    }
    updateFooterTime();
    setInterval(updateFooterTime, 1000);

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
    // PRINT PDF - USING html2pdf
    // ================================================================
    function printPDF() {
        var element = document.getElementById('labResultReport');
        var btn = document.querySelector('.btn-success');
        var originalText = btn.innerHTML;
        
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating PDF...';
        btn.disabled = true;
        
        var opt = {
            margin:        [10, 10, 10, 10],
            filename:     'Lab_Result_<?= preg_replace('/[^a-zA-Z0-9]/', '_', $lab_test['patient_name'] ?? 'Patient') ?>_<?= date('Y-m-d') ?>.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2, useCORS: true, letterRendering: true },
            jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };
        
        // Add print styles for PDF
        var style = document.createElement('style');
        style.innerHTML = `
            .btn-actions { display: none !important; }
            .btn-outline-light { display: none !important; }
            .top-nav { display: none !important; }
            .sidebar { display: none !important; }
            .footer { display: none !important; }
            .page-header-custom { display: none !important; }
            .main-content { margin: 0 !important; padding: 0 !important; }
            .report-card { border: 1px solid #ddd !important; box-shadow: none !important; border-radius: 0 !important; }
            .report-header { background: #0B5ED7 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .report-header .brand-text h2 { color: white !important; }
            .report-header .brand-text p { color: rgba(255,255,255,0.8) !important; }
            .report-header .report-title h3 { color: white !important; }
            .report-header .report-title p { color: rgba(255,255,255,0.8) !important; }
            .patient-info-section { background: #E8F0FE !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; border-left: 4px solid #0B5ED7 !important; }
            .status-badge { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .status-badge.completed { background: #D1FAE5 !important; color: #059669 !important; }
            .status-badge.pending { background: #FEF3C7 !important; color: #D97706 !important; }
            .result-item { background: #F8FAFC !important; }
            .result-full { background: #F8FAFC !important; }
        `;
        document.head.appendChild(style);
        
        // Generate PDF
        html2pdf().set(opt).from(element).save().then(function() {
            // Remove the style after PDF is generated
            document.head.removeChild(style);
            btn.innerHTML = originalText;
            btn.disabled = false;
            showToast('✅ Success', 'PDF downloaded successfully!', 'success');
        }).catch(function(error) {
            console.error('PDF generation error:', error);
            document.head.removeChild(style);
            btn.innerHTML = originalText;
            btn.disabled = false;
            showToast('❌ Error', 'Failed to generate PDF: ' + error.message, 'error');
        });
    }

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if (e.key === 'p' && (e.ctrlKey || e.metaKey)) {
            // Allow normal print shortcut
        }
        if (e.key === 'Escape') {
            window.location.href = 'lab_results.php';
        }
    });

    console.log('%c🧪 Braick - Lab Result Details', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🔬 Test: <?= htmlspecialchars($lab_test['test_name']) ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c👤 Patient: <?= htmlspecialchars($lab_test['patient_name']) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📋 Status: <?= ucfirst($lab_test['status'] ?? 'Pending') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c🔵 Blue Theme Applied', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📄 Print PDF button available', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>