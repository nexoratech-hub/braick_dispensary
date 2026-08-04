<?php
// ================================================================
// FILE: frontend/pages/laboratory/view_test.php
// VIEW LAB TEST - WITH PDF DOWNLOAD
// FIXED: Header and sidebar included
// FIXED: PDF download working
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION - Lab Technician Default
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'laboratory') {
    $_SESSION['user_id'] = 8;
    $_SESSION['full_name'] = 'Lab Technician Dodoma';
    $_SESSION['role'] = 'laboratory';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'lab.dodoma';
}

$user_id = $_SESSION['user_id'] ?? 8;
$user_full_name = $_SESSION['full_name'] ?? 'Lab Technician Dodoma';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';
$db = Database::getInstance()->getConnection();

// ================================================================
// GET TEST ID
// ================================================================
$test_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$print_mode = isset($_GET['print']) && $_GET['print'] == 1;

if ($test_id <= 0) {
    header('Location: pending_requests.php');
    exit;
}

// ================================================================
// GET TEST DETAILS
// ================================================================
$stmt = $db->prepare("
    SELECT 
        lt.*,
        p.full_name as patient_name,
        p.patient_id as patient_code,
        p.gender,
        p.date_of_birth,
        p.phone,
        p.address,
        v.visit_number,
        v.visit_date,
        u.full_name as doctor_name,
        u.specialty as doctor_specialty,
        t.full_name as technician_name,
        b.name as branch_name
    FROM lab_tests lt
    JOIN visits v ON lt.visit_id = v.id
    JOIN patients p ON v.patient_id = p.id
    LEFT JOIN users u ON lt.doctor_id = u.id
    LEFT JOIN users t ON lt.lab_technician_id = t.id
    LEFT JOIN branches b ON lt.branch_id = b.id
    WHERE lt.id = ?
");
$stmt->execute([$test_id]);
$test = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$test) {
    header('Location: pending_requests.php?error=test_not_found');
    exit;
}

// ================================================================
// GET RESULT TEMPLATE IF EXISTS
// ================================================================
$template_html = null;
if (!empty($test['result_template_id'])) {
    $stmt = $db->prepare("SELECT template_html FROM lab_result_templates WHERE id = ? AND is_active = 1");
    $stmt->execute([$test['result_template_id']]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($template) {
        $template_html = $template['template_html'];
    }
}

// ================================================================
// HELPER FUNCTIONS
// ================================================================
function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}

function getStatusBadgeClass($status) {
    $map = [
        'pending' => 'badge-warning',
        'in_progress' => 'badge-info',
        'completed' => 'badge-success',
        'cancelled' => 'badge-danger'
    ];
    return $map[$status] ?? 'badge-info';
}

function getStatusLabel($status) {
    $map = [
        'pending' => '⏳ Pending',
        'in_progress' => '🔬 In Progress',
        'completed' => '✅ Completed',
        'cancelled' => '❌ Cancelled'
    ];
    return $map[$status] ?? $status;
}

// ================================================================
// LOGO PATH
// ================================================================
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE HEADER & SIDEBAR - FIXED
// ================================================================
include_once __DIR__ . '/../../components/laboratory_header.php';
include_once __DIR__ . '/../../components/laboratory_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Test - <?= htmlspecialchars($test['test_name'] ?? 'N/A') ?> - Braick Dispensary</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
    <style>
        /* ================================================================
           BLUE THEME - MAIN STYLES
           ================================================================ */
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
            --primary-gradient: linear-gradient(135deg, #0B5ED7 0%, #1A7FE8 100%);
            --success: #059669;
            --success-dark: #047857;
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
            --radius: 12px;
            --radius-lg: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 16px rgba(11,94,215,0.10);
            --shadow-lg: 0 8px 32px rgba(11,94,215,0.15);
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            background: var(--gray-50);
            color: var(--gray-800);
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            margin: 0;
            padding: 0;
            line-height: 1.6;
        }
        
        [data-theme="dark"] body {
            background: var(--gray-900);
            color: var(--gray-100);
        }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
            background: var(--gray-50);
            color: var(--gray-800);
            transition: var(--transition);
        }
        
        [data-theme="dark"] .main-content {
            background: var(--gray-900);
            color: var(--gray-100);
        }
        
        /* ================================================================
           SCROLLBAR
           ================================================================ */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: var(--gray-100); border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: var(--primary-light); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--primary); }
        [data-theme="dark"] ::-webkit-scrollbar-track { background: var(--gray-700); }
        [data-theme="dark"] ::-webkit-scrollbar-thumb { background: var(--primary-dark); }
        
        /* ================================================================
           PAGE HEADER - BLUE GRADIENT
           ================================================================ */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 28px;
            padding: 24px 28px;
            background: var(--primary-gradient);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            position: relative;
            color: white;
        }
        .page-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, rgba(255,255,255,0.3), rgba(255,255,255,0.6), rgba(255,255,255,0.3));
            border-radius: 0 0 4px 4px;
        }
        .page-header-left { flex: 1; }
        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin: 0;
            color: white;
        }
        .page-title i { color: rgba(255,255,255,0.8); }
        
        .page-badge {
            font-size: 0.7rem;
            font-weight: 600;
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 16px;
            border-radius: 20px;
            font-family: monospace;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .page-subtitle {
            font-size: 0.9rem;
            opacity: 0.85;
            margin-top: 6px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            color: rgba(255,255,255,0.9);
        }
        .page-subtitle strong { color: white; font-weight: 700; }
        
        .status-badge {
            display: inline-block;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 4px 16px;
            border-radius: 20px;
            text-transform: capitalize;
        }
        .badge-warning { background: var(--warning-bg); color: var(--warning); }
        .badge-info { background: var(--primary-bg); color: var(--primary); }
        .badge-success { background: var(--success-bg); color: var(--success); }
        .badge-danger { background: var(--danger-bg); color: var(--danger); }
        .badge-purple { background: var(--purple-bg); color: var(--purple); }
        
        .branch-badge {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            border: 1px solid rgba(255,255,255,0.2);
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .page-header-right .btn-outline {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.25);
        }
        .page-header-right .btn-outline:hover {
            background: rgba(255,255,255,0.25);
            border-color: rgba(255,255,255,0.4);
            color: white;
            transform: translateY(-2px);
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 22px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.85rem;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            text-decoration: none;
            font-family: inherit;
        }
        .btn-primary {
            background: var(--primary-gradient);
            color: #ffffff;
            box-shadow: 0 2px 12px rgba(11,94,215,0.25);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(11,94,215,0.35);
        }
        .btn-success {
            background: linear-gradient(135deg, #059669, #10B981);
            color: #ffffff;
            box-shadow: 0 2px 12px rgba(5,150,105,0.25);
        }
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(5,150,105,0.35);
        }
        .btn-outline {
            background: transparent;
            color: var(--gray-600);
            border: 2px solid var(--gray-200);
        }
        .btn-outline:hover {
            background: var(--primary-bg);
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-2px);
        }
        .btn-danger {
            background: linear-gradient(135deg, #DC2626, #EF4444);
            color: #ffffff;
        }
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(220,38,38,0.35);
        }
        .btn-sm {
            padding: 6px 16px;
            font-size: 0.75rem;
            border-radius: 8px;
        }
        
        /* ================================================================
           CARDS - BLUE THEME
           ================================================================ */
        .detail-card {
            background: linear-gradient(135deg, #ffffff 0%, #f0f7ff 100%);
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            border: 1px solid var(--primary-light);
            transition: var(--transition);
            margin-bottom: 20px;
            box-shadow: var(--shadow-md);
            position: relative;
            overflow: hidden;
        }
        .detail-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
            border-radius: 0 0 4px 4px;
        }
        .detail-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }
        [data-theme="dark"] .detail-card {
            background: linear-gradient(135deg, var(--gray-800) 0%, #1a2a4a 100%);
            border-color: var(--primary-dark);
        }
        [data-theme="dark"] .detail-card::before {
            background: var(--primary-gradient);
        }
        [data-theme="dark"] .detail-card:hover {
            border-color: var(--primary);
        }
        
        .card-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--primary-dark);
            border-bottom: 2px solid var(--primary-light);
            padding-bottom: 12px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        [data-theme="dark"] .card-title {
            color: var(--primary-light);
            border-color: var(--primary-dark);
        }
        .card-title i { font-size: 1.1rem; }
        .title-blue { color: var(--primary); }
        .title-green { color: var(--success); }
        .title-purple { color: var(--purple); }
        .title-orange { color: var(--warning); }
        .title-red { color: var(--danger); }
        
        .detail-row {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid var(--gray-100);
        }
        .detail-row:last-child { border-bottom: none; }
        [data-theme="dark"] .detail-row { border-color: var(--gray-700); }
        
        .detail-label {
            font-weight: 600;
            color: var(--gray-500);
            width: 180px;
            flex-shrink: 0;
            font-size: 0.85rem;
        }
        .detail-value {
            flex: 1;
            color: var(--gray-800);
            font-size: 0.9rem;
        }
        [data-theme="dark"] .detail-value { color: var(--gray-200); }
        
        .row-2col { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        
        /* ================================================================
           RESULT DISPLAY
           ================================================================ */
        .result-box {
            background: var(--primary-bg);
            border-radius: var(--radius);
            padding: 16px 20px;
            border: 2px solid var(--primary-light);
            margin-top: 8px;
        }
        [data-theme="dark"] .result-box {
            background: #1E3A5F;
            border-color: var(--primary-dark);
        }
        
        .result-box .result-value {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary-dark);
        }
        [data-theme="dark"] .result-box .result-value {
            color: var(--primary-light);
        }
        
        .result-box .result-label {
            font-size: 0.7rem;
            color: var(--gray-500);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        /* ================================================================
           PDF MODAL - BEAUTIFUL DESIGN
           ================================================================ */
        .pdf-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 9999;
            backdrop-filter: blur(8px);
        }
        .pdf-modal-overlay.active { display: flex; align-items: center; justify-content: center; }
        
        .pdf-modal {
            background: white;
            border-radius: var(--radius-lg);
            width: 95%;
            max-width: 1100px;
            max-height: 95vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        [data-theme="dark"] .pdf-modal { background: var(--gray-800); }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(40px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        
        .pdf-modal-header {
            padding: 16px 24px;
            border-bottom: 2px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            background: var(--primary-gradient);
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }
        [data-theme="dark"] .pdf-modal-header { 
            border-color: var(--gray-700);
            background: var(--primary-gradient);
        }
        
        .pdf-modal-header .modal-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .pdf-modal-header .modal-actions {
            display: flex;
            gap: 10px;
        }
        
        .pdf-modal-header .modal-actions .btn {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.25);
        }
        .pdf-modal-header .modal-actions .btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }
        .pdf-modal-header .modal-actions .btn-danger {
            background: rgba(220,38,38,0.4);
            border-color: rgba(220,38,38,0.3);
        }
        .pdf-modal-header .modal-actions .btn-danger:hover {
            background: rgba(220,38,38,0.6);
        }
        
        .pdf-modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 24px 32px;
            background: #f8fafc;
        }
        [data-theme="dark"] .pdf-modal-body { 
            background: var(--gray-800);
        }
        
        .pdf-modal-body .pdf-content {
            max-width: 100%;
            font-size: 0.85rem;
            color: var(--gray-800);
            background: white;
            padding: 32px 40px;
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
        }
        [data-theme="dark"] .pdf-modal-body .pdf-content { 
            color: var(--gray-200);
            background: var(--gray-700);
        }
        
        /* PDF Header with Logo */
        .pdf-content .pdf-header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 3px solid var(--primary);
            margin-bottom: 24px;
            position: relative;
        }
        
        .pdf-content .pdf-header .pdf-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            margin-bottom: 6px;
        }
        .pdf-content .pdf-header .pdf-logo img {
            height: 60px;
            width: auto;
            object-fit: contain;
        }
        .pdf-content .pdf-header .pdf-logo .clinic-name {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--primary);
            letter-spacing: -0.5px;
        }
        .pdf-content .pdf-header .clinic-sub {
            font-size: 0.85rem;
            color: var(--gray-500);
            letter-spacing: 1px;
        }
        .pdf-content .pdf-header .test-info {
            font-size: 1rem;
            font-weight: 700;
            color: var(--primary);
            margin-top: 6px;
            background: var(--primary-bg);
            padding: 4px 16px;
            border-radius: 20px;
            display: inline-block;
        }
        [data-theme="dark"] .pdf-content .pdf-header .test-info {
            background: #1E3A5F;
        }
        
        .pdf-content .section-title {
            font-weight: 700;
            font-size: 1.05rem;
            color: var(--primary);
            border-bottom: 2px solid var(--primary-light);
            padding-bottom: 6px;
            margin: 20px 0 12px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        [data-theme="dark"] .pdf-content .section-title {
            border-color: var(--primary-dark);
        }
        
        .pdf-content .pdf-row {
            display: flex;
            padding: 4px 0;
            border-bottom: 1px solid var(--gray-100);
        }
        [data-theme="dark"] .pdf-content .pdf-row {
            border-color: var(--gray-600);
        }
        .pdf-content .pdf-row .pdf-label {
            font-weight: 600;
            color: var(--gray-500);
            width: 160px;
            flex-shrink: 0;
        }
        .pdf-content .pdf-row .pdf-value {
            flex: 1;
            color: var(--gray-800);
        }
        [data-theme="dark"] .pdf-content .pdf-row .pdf-value {
            color: var(--gray-200);
        }
        
        .pdf-content .pdf-footer {
            text-align: center;
            padding-top: 20px;
            border-top: 2px solid var(--gray-200);
            margin-top: 24px;
            font-size: 0.7rem;
            color: var(--gray-400);
        }
        [data-theme="dark"] .pdf-content .pdf-footer {
            border-color: var(--gray-600);
        }
        
        .pdf-content .pdf-footer .footer-brand {
            color: var(--primary);
            font-weight: 700;
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
        }
        @media (max-width: 768px) {
            .main-content { padding: 12px; }
            .row-2col { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; }
            .detail-row { flex-direction: column; }
            .detail-label { width: 100%; margin-bottom: 2px; }
            .pdf-modal { width: 100%; max-height: 100vh; border-radius: 0; }
            .pdf-modal-header { flex-direction: column; gap: 10px; align-items: stretch; }
            .pdf-modal-header .modal-actions { justify-content: center; flex-wrap: wrap; }
            .pdf-modal-body .pdf-content { padding: 16px; }
            .pdf-content .pdf-row { flex-direction: column; }
            .pdf-content .pdf-row .pdf-label { width: 100%; }
        }
        @media (max-width: 480px) {
            .page-title { font-size: 1.1rem; }
            .detail-card { padding: 12px 16px; }
            .btn { font-size: 0.75rem; padding: 6px 12px; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-left">
            <h1 class="page-title">
                <i class="fas fa-flask"></i> Test Details
                <span class="page-badge">#<?= htmlspecialchars($test['id'] ?? 'N/A') ?></span>
                <span class="status-badge <?= getStatusBadgeClass($test['status'] ?? 'pending') ?>">
                    <?= getStatusLabel($test['status'] ?? 'pending') ?>
                </span>
            </h1>
            <p class="page-subtitle">
                Test: <strong><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></strong>
                <span class="separator">|</span>
                Patient: <strong><?= htmlspecialchars($test['patient_name'] ?? 'N/A') ?></strong>
                <span class="separator">|</span>
                Visit: <strong><?= htmlspecialchars($test['visit_number'] ?? 'N/A') ?></strong>
                <span class="branch-badge"><i class="fas fa-store-alt"></i> <?= htmlspecialchars($test['branch_name'] ?? 'N/A') ?></span>
            </p>
        </div>
        <div class="page-header-right" style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="pending_requests.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <?php if ($test['status'] !== 'cancelled'): ?>
                <button onclick="generatePDF()" class="btn btn-primary btn-sm">
                    <i class="fas fa-file-pdf"></i> Download PDF
                </button>
            <?php endif; ?>
            <button onclick="window.print()" class="btn btn-outline btn-sm">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PATIENT INFORMATION -->
    <!-- ================================================================ -->
    <div class="detail-card">
        <h3 class="card-title"><i class="fas fa-user title-blue"></i> Patient Information</h3>
        <div class="row-2col">
            <div>
                <div class="detail-row"><span class="detail-label">Full Name</span><span class="detail-value"><?= htmlspecialchars($test['patient_name'] ?? 'N/A') ?></span></div>
                <div class="detail-row"><span class="detail-label">Patient ID</span><span class="detail-value"><?= htmlspecialchars($test['patient_code'] ?? 'N/A') ?></span></div>
                <div class="detail-row"><span class="detail-label">Gender</span><span class="detail-value"><?= htmlspecialchars($test['gender'] ?? 'N/A') ?></span></div>
                <div class="detail-row"><span class="detail-label">Date of Birth</span><span class="detail-value"><?= !empty($test['date_of_birth']) ? date('M d, Y', strtotime($test['date_of_birth'])) : 'N/A' ?> (<?= calculateAge($test['date_of_birth'] ?? '') ?> years)</span></div>
            </div>
            <div>
                <div class="detail-row"><span class="detail-label">Phone</span><span class="detail-value"><?= htmlspecialchars($test['phone'] ?? 'N/A') ?></span></div>
                <div class="detail-row"><span class="detail-label">Address</span><span class="detail-value"><?= htmlspecialchars($test['address'] ?? 'N/A') ?></span></div>
                <div class="detail-row"><span class="detail-label">Visit Number</span><span class="detail-value"><?= htmlspecialchars($test['visit_number'] ?? 'N/A') ?></span></div>
                <div class="detail-row"><span class="detail-label">Visit Date</span><span class="detail-value"><?= date('M d, Y h:i A', strtotime($test['visit_date'] ?? 'now')) ?></span></div>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- TEST INFORMATION -->
    <!-- ================================================================ -->
    <div class="detail-card">
        <h3 class="card-title"><i class="fas fa-vial title-purple"></i> Test Information</h3>
        <div class="row-2col">
            <div>
                <div class="detail-row"><span class="detail-label">Test Name</span><span class="detail-value"><strong><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></strong></span></div>
                <div class="detail-row"><span class="detail-label">Test Price</span><span class="detail-value">TSh <?= number_format($test['test_price'] ?? 0, 0) ?></span></div>
                <div class="detail-row"><span class="detail-label">Test Type</span><span class="detail-value"><?= htmlspecialchars($test['test_type'] ?? 'N/A') ?></span></div>
                <div class="detail-row"><span class="detail-label">Sample Type</span><span class="detail-value"><?= htmlspecialchars($test['sample_type'] ?? 'N/A') ?></span></div>
            </div>
            <div>
                <div class="detail-row"><span class="detail-label">Doctor</span><span class="detail-value"><?= htmlspecialchars($test['doctor_name'] ?? 'Not Assigned') ?></span></div>
                <div class="detail-row"><span class="detail-label">Technician</span><span class="detail-value"><?= htmlspecialchars($test['technician_name'] ?? 'Not Assigned') ?></span></div>
                <div class="detail-row"><span class="detail-label">Status</span><span class="detail-value"><span class="status-badge <?= getStatusBadgeClass($test['status'] ?? 'pending') ?>"><?= getStatusLabel($test['status'] ?? 'pending') ?></span></span></div>
                <div class="detail-row"><span class="detail-label">Created</span><span class="detail-value"><?= date('M d, Y h:i A', strtotime($test['created_at'])) ?></span></div>
                <?php if (!empty($test['completed_at'])): ?>
                    <div class="detail-row"><span class="detail-label">Completed</span><span class="detail-value"><?= date('M d, Y h:i A', strtotime($test['completed_at'])) ?></span></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- TEST RESULTS -->
    <!-- ================================================================ -->
    <div class="detail-card">
        <h3 class="card-title"><i class="fas fa-file-medical-alt title-green"></i> Test Results</h3>
        
        <?php if ($test['status'] === 'completed' && !empty($test['results'])): ?>
            <?php if ($template_html): ?>
                <!-- Display formatted result from template -->
                <div class="result-box">
                    <div style="overflow-x:auto;">
                        <?= $test['formatted_result'] ?? $test['results'] ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="result-box">
                    <div style="margin-bottom:4px;">
                        <span class="result-label">Result</span>
                        <div class="result-value"><?= htmlspecialchars($test['results'] ?? 'N/A') ?></div>
                    </div>
                    <?php if (!empty($test['reference_range'])): ?>
                        <div style="margin-top:8px;padding-top:8px;border-top:1px solid var(--gray-200);">
                            <span class="result-label">Reference Range</span>
                            <div style="font-size:0.9rem;color:var(--gray-700);"><?= htmlspecialchars($test['reference_range']) ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($test['interpretation'])): ?>
                        <div style="margin-top:8px;padding-top:8px;border-top:1px solid var(--gray-200);">
                            <span class="result-label">Interpretation</span>
                            <div style="font-size:0.9rem;color:var(--gray-700);"><?= htmlspecialchars($test['interpretation']) ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php elseif ($test['status'] === 'in_progress'): ?>
            <div style="text-align:center;padding:30px 20px;color:var(--gray-500);">
                <i class="fas fa-spinner fa-spin text-3xl" style="color:var(--primary);"></i>
                <p style="margin-top:12px;font-size:1rem;font-weight:600;">Test is In Progress</p>
                <p style="font-size:0.85rem;">Results will appear once the test is completed</p>
            </div>
        <?php elseif ($test['status'] === 'cancelled'): ?>
            <div style="text-align:center;padding:30px 20px;color:var(--gray-500);">
                <i class="fas fa-times-circle text-3xl" style="color:var(--danger);"></i>
                <p style="margin-top:12px;font-size:1rem;font-weight:600;">Test Cancelled</p>
                <p style="font-size:0.85rem;">This test has been cancelled</p>
            </div>
        <?php else: ?>
            <div style="text-align:center;padding:30px 20px;color:var(--gray-500);">
                <i class="fas fa-clock text-3xl" style="color:var(--warning);"></i>
                <p style="margin-top:12px;font-size:1rem;font-weight:600;">Test Pending</p>
                <p style="font-size:0.85rem;">Results not yet available</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- NOTES -->
    <!-- ================================================================ -->
    <?php if (!empty($test['notes'])): ?>
        <div class="detail-card">
            <h3 class="card-title"><i class="fas fa-sticky-note title-orange"></i> Notes</h3>
            <div class="detail-row">
                <span class="detail-label">Notes</span>
                <span class="detail-value"><?= nl2br(htmlspecialchars($test['notes'] ?? '')) ?></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="footer" style="padding:16px 0;border-top:2px solid var(--gray-200);margin-top:24px;text-align:center;font-size:0.7rem;color:var(--gray-500);">
        <p>
            <span style="color:var(--primary);font-weight:600;">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Test Details
            <span class="text-gray-300 mx-2">|</span>
            <?= htmlspecialchars($test['test_name'] ?? 'N/A') ?>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- PDF MODAL - BEAUTIFUL DESIGN WITH LOGO -->
<!-- ================================================================ -->
<div class="pdf-modal-overlay" id="pdfModal">
    <div class="pdf-modal">
        <div class="pdf-modal-header">
            <div class="modal-title">
                <i class="fas fa-file-pdf" style="color:rgba(255,255,255,0.8);"></i>
                PDF Preview - <?= htmlspecialchars($test['test_name'] ?? 'Test') ?>
            </div>
            <div class="modal-actions">
                <button onclick="downloadPDF()" class="btn btn-sm">
                    <i class="fas fa-download"></i> Download
                </button>
                <button onclick="window.print()" class="btn btn-sm">
                    <i class="fas fa-print"></i> Print
                </button>
                <button onclick="closePDFModal()" class="btn btn-sm btn-danger">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
        <div class="pdf-modal-body" id="pdfModalBody">
            <div class="pdf-content" id="pdfContent">
                <!-- PDF content will be generated by JavaScript -->
            </div>
        </div>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE
    // ================================================================
    if (localStorage.getItem('darkMode') === 'true') {
        document.documentElement.setAttribute('data-theme', 'dark');
    }

    // ================================================================
    // GENERATE PDF - BEAUTIFUL DESIGN WITH LOGO - FIXED
    // ================================================================
    function generatePDF() {
        var modal = document.getElementById('pdfModal');
        var content = document.getElementById('pdfContent');
        
        var statusLabel = '<?= getStatusLabel($test['status'] ?? 'pending') ?>';
        var statusClass = '<?= $test['status'] ?? 'pending' ?>';
        var statusColor = statusClass === 'completed' ? '#059669' : (statusClass === 'in_progress' ? '#0B5ED7' : (statusClass === 'cancelled' ? '#DC2626' : '#D97706'));
        
        // Get patient info
        var patientName = '<?= addslashes($test['patient_name'] ?? 'N/A') ?>';
        var patientCode = '<?= addslashes($test['patient_code'] ?? 'N/A') ?>';
        var gender = '<?= addslashes($test['gender'] ?? 'N/A') ?>';
        var dob = '<?= !empty($test['date_of_birth']) ? date('M d, Y', strtotime($test['date_of_birth'])) : 'N/A' ?>';
        var age = '<?= calculateAge($test['date_of_birth'] ?? '') ?>';
        var phone = '<?= addslashes($test['phone'] ?? 'N/A') ?>';
        var visitNumber = '<?= addslashes($test['visit_number'] ?? 'N/A') ?>';
        var testName = '<?= addslashes($test['test_name'] ?? 'N/A') ?>';
        var testPrice = '<?= number_format($test['test_price'] ?? 0, 0) ?>';
        var testType = '<?= addslashes($test['test_type'] ?? 'N/A') ?>';
        var sampleType = '<?= addslashes($test['sample_type'] ?? 'N/A') ?>';
        var doctorName = '<?= addslashes($test['doctor_name'] ?? 'Not Assigned') ?>';
        var technicianName = '<?= addslashes($test['technician_name'] ?? 'Not Assigned') ?>';
        var createdDate = '<?= date('M d, Y h:i A', strtotime($test['created_at'])) ?>';
        var completedDate = '<?= !empty($test['completed_at']) ? date('M d, Y h:i A', strtotime($test['completed_at'])) : '' ?>';
        var testResults = '<?= addslashes($test['results'] ?? '') ?>';
        var referenceRange = '<?= addslashes($test['reference_range'] ?? '') ?>';
        var interpretation = '<?= addslashes($test['interpretation'] ?? '') ?>';
        var notes = '<?= addslashes($test['notes'] ?? '') ?>';
        var branchName = '<?= addslashes($test['branch_name'] ?? '') ?>';
        var testId = '<?= $test['id'] ?>';
        
        // Build PDF content
        var html = `
            <div class="pdf-header">
                <div class="pdf-logo">
                    <img src="/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png" alt="Braick Logo" onerror="this.style.display='none'">
                    <span class="clinic-name">Braick Dispensary</span>
                </div>
                <div class="clinic-sub">Quality Healthcare Services • ${branchName}</div>
                <div class="test-info">🧪 ${testName}</div>
                <div style="font-size:0.8rem;color:var(--gray-500);margin-top:4px;">
                    Status: <span style="color:${statusColor};font-weight:700;">${statusLabel}</span> • 
                    ID: #${testId} • 
                    Date: ${createdDate}
                </div>
            </div>
            
            <!-- Patient Information -->
            <div class="section-title">👤 Patient Information</div>
            <div class="pdf-row"><span class="pdf-label">Full Name</span><span class="pdf-value">${patientName}</span></div>
            <div class="pdf-row"><span class="pdf-label">Patient ID</span><span class="pdf-value">${patientCode}</span></div>
            <div class="pdf-row"><span class="pdf-label">Gender</span><span class="pdf-value">${gender}</span></div>
            <div class="pdf-row"><span class="pdf-label">Date of Birth</span><span class="pdf-value">${dob} (${age} years)</span></div>
            <div class="pdf-row"><span class="pdf-label">Phone</span><span class="pdf-value">${phone}</span></div>
            <div class="pdf-row"><span class="pdf-label">Visit Number</span><span class="pdf-value">${visitNumber}</span></div>
            
            <!-- Test Information -->
            <div class="section-title">🧪 Test Information</div>
            <div class="pdf-row"><span class="pdf-label">Test Name</span><span class="pdf-value"><strong>${testName}</strong></span></div>
            <div class="pdf-row"><span class="pdf-label">Test Price</span><span class="pdf-value">TSh ${testPrice}</span></div>
            <div class="pdf-row"><span class="pdf-label">Test Type</span><span class="pdf-value">${testType}</span></div>
            <div class="pdf-row"><span class="pdf-label">Sample Type</span><span class="pdf-value">${sampleType}</span></div>
            <div class="pdf-row"><span class="pdf-label">Doctor</span><span class="pdf-value">${doctorName}</span></div>
            <div class="pdf-row"><span class="pdf-label">Technician</span><span class="pdf-value">${technicianName}</span></div>
            <div class="pdf-row"><span class="pdf-label">Status</span><span class="pdf-value" style="color:${statusColor};font-weight:700;">${statusLabel}</span></div>
            <div class="pdf-row"><span class="pdf-label">Created</span><span class="pdf-value">${createdDate}</span></div>
            ${completedDate ? `<div class="pdf-row"><span class="pdf-label">Completed</span><span class="pdf-value">${completedDate}</span></div>` : ''}
            
            <!-- Results -->
            <div class="section-title">📊 Test Results</div>
            <?php if ($test['status'] === 'completed' && !empty($test['results'])): ?>
                <?php if ($template_html): ?>
                    <div style="padding:12px;background:var(--primary-bg);border-radius:8px;border:1px solid var(--primary-light);margin-top:4px;">
                        <?= addslashes($test['formatted_result'] ?? $test['results']) ?>
                    </div>
                <?php else: ?>
                    <div class="pdf-row"><span class="pdf-label">Result</span><span class="pdf-value" style="font-weight:700;color:#059669;"><?= addslashes($test['results'] ?? 'N/A') ?></span></div>
                    <?php if (!empty($test['reference_range'])): ?>
                        <div class="pdf-row"><span class="pdf-label">Reference Range</span><span class="pdf-value"><?= addslashes($test['reference_range']) ?></span></div>
                    <?php endif; ?>
                    <?php if (!empty($test['interpretation'])): ?>
                        <div class="pdf-row"><span class="pdf-label">Interpretation</span><span class="pdf-value"><?= addslashes($test['interpretation']) ?></span></div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php elseif ($test['status'] === 'in_progress'): ?>
                <div class="pdf-row" style="border:none;padding:12px 0;"><span class="pdf-value" style="color:#0B5ED7;font-weight:600;">⏳ Test is In Progress - Results pending</span></div>
            <?php elseif ($test['status'] === 'cancelled'): ?>
                <div class="pdf-row" style="border:none;padding:12px 0;"><span class="pdf-value" style="color:#DC2626;font-weight:600;">❌ Test Cancelled</span></div>
            <?php else: ?>
                <div class="pdf-row" style="border:none;padding:12px 0;"><span class="pdf-value" style="color:#D97706;font-weight:600;">⏳ Test Pending - Results not yet available</span></div>
            <?php endif; ?>
            
            <!-- Notes -->
            <?php if (!empty($test['notes'])): ?>
                <div class="section-title">📝 Notes</div>
                <div class="pdf-row"><span class="pdf-label">Notes</span><span class="pdf-value"><?= nl2br(addslashes($test['notes'] ?? '')) ?></span></div>
            <?php endif; ?>
            
            <!-- Footer -->
            <div class="pdf-footer">
                <p>
                    <span class="footer-brand">Braick Dispensary</span> Management System
                    <br>
                    Generated on <?= date('M d, Y h:i A') ?> • All rights reserved
                    <br>
                    <span style="font-size:0.65rem;color:var(--gray-400);">
                        This is a computer generated document. No signature required.
                    </span>
                </p>
            </div>
        `;
        
        content.innerHTML = html;
        modal.classList.add('active');
    }
    
    function closePDFModal() {
        document.getElementById('pdfModal').classList.remove('active');
    }
    
    function downloadPDF() {
        var element = document.getElementById('pdfContent');
        var opt = {
            margin: [8, 8, 8, 8],
            filename: 'Test_<?= htmlspecialchars($test['test_name'] ?? 'test') ?>_<?= $test['id'] ?>.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { 
                scale: 2, 
                useCORS: true,
                backgroundColor: '#ffffff'
            },
            jsPDF: { 
                unit: 'mm', 
                format: 'a4', 
                orientation: 'portrait' 
            },
            pagebreak: { mode: 'avoid-all' }
        };
        
        html2pdf().set(opt).from(element).save();
    }

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closePDFModal();
        }
    });

    // ================================================================
    // CLICK OUTSIDE TO CLOSE PDF MODAL
    // ================================================================
    document.getElementById('pdfModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closePDFModal();
        }
    });

    console.log('%c🧪 View Test - <?= htmlspecialchars($test['test_name'] ?? 'N/A') ?>', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🆔 Test ID: <?= $test['id'] ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c👤 Patient: <?= htmlspecialchars($test['patient_name'] ?? 'N/A') ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Status: <?= getStatusLabel($test['status'] ?? 'pending') ?>', 'font-size:13px; color:#D97706;');
    console.log('%c✅ PDF Download with beautiful design and logo', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>