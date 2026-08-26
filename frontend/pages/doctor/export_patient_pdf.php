<?php
// ================================================================
// FILE: frontend/pages/doctor/export_patient_pdf.php
// EXPORT PATIENT PDF - FULL PATIENT REPORT (VISIT STYLE)
// BRAICK DISPENSARY - WITH OFFICIAL STAMP
// ================================================================

// Start session
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
// CHECK IF USER IS DOCTOR OR ADMIN
// ================================================================
if ($_SESSION['role'] !== 'doctor' && $_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET USER INFO FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Doctor';
$user_role = $_SESSION['role'];
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$is_admin = ($_SESSION['role'] === 'admin');

// ================================================================
// GET PATIENT ID
// ================================================================
$patient_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($patient_id <= 0) {
    die('Invalid patient ID');
}

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die('Database connection error: ' . $e->getMessage());
}

// ================================================================
// GET PATIENT DATA
// ================================================================
if ($is_admin) {
    $stmt = $db->prepare("
        SELECT p.*, b.name as branch_name, u.full_name as assigned_doctor_name
        FROM patients p
        LEFT JOIN branches b ON p.branch_id = b.id
        LEFT JOIN users u ON p.assigned_doctor_id = u.id
        WHERE p.id = ?
    ");
    $stmt->execute([$patient_id]);
} else {
    $stmt = $db->prepare("
        SELECT p.*, b.name as branch_name, u.full_name as assigned_doctor_name
        FROM patients p
        LEFT JOIN branches b ON p.branch_id = b.id
        LEFT JOIN users u ON p.assigned_doctor_id = u.id
        WHERE p.id = ? AND (p.assigned_doctor_id = ? OR p.id IN (SELECT DISTINCT patient_id FROM visits WHERE doctor_id = ?))
    ");
    $stmt->execute([$patient_id, $user_id, $user_id]);
}
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    die('Patient not found or you do not have access');
}

// ================================================================
// GET ALL DATA
// ================================================================

// Get all visits
if ($is_admin) {
    $stmt = $db->prepare("
        SELECT v.*, u.full_name as doctor_name
        FROM visits v
        LEFT JOIN users u ON v.doctor_id = u.id
        WHERE v.patient_id = ?
        ORDER BY v.created_at DESC
    ");
} else {
    $stmt = $db->prepare("
        SELECT v.*, u.full_name as doctor_name
        FROM visits v
        LEFT JOIN users u ON v.doctor_id = u.id
        WHERE v.patient_id = ? AND v.doctor_id = ?
        ORDER BY v.created_at DESC
    ");
    $stmt->execute([$patient_id, $user_id]);
}
$stmt->execute([$patient_id]);
$all_visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all bills
$stmt = $db->prepare("
    SELECT pb.*
    FROM bills pb
    WHERE pb.patient_id = ?
    ORDER BY pb.created_at DESC
");
$stmt->execute([$patient_id]);
$all_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all prescriptions
if ($is_admin) {
    $stmt = $db->prepare("
        SELECT p.*, u.full_name as doctor_name
        FROM prescriptions p
        LEFT JOIN users u ON p.doctor_id = u.id
        WHERE p.patient_id = ?
        ORDER BY p.created_at DESC
    ");
} else {
    $stmt = $db->prepare("
        SELECT p.*, u.full_name as doctor_name
        FROM prescriptions p
        LEFT JOIN users u ON p.doctor_id = u.id
        WHERE p.patient_id = ? AND p.doctor_id = ?
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$patient_id, $user_id]);
}
$stmt->execute([$patient_id]);
$all_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all lab tests
if ($is_admin) {
    $stmt = $db->prepare("
        SELECT lt.*, u.full_name as doctor_name
        FROM lab_tests lt
        INNER JOIN visits v ON lt.visit_id = v.id
        LEFT JOIN users u ON lt.doctor_id = u.id
        WHERE v.patient_id = ?
        ORDER BY lt.created_at DESC
    ");
} else {
    $stmt = $db->prepare("
        SELECT lt.*, u.full_name as doctor_name
        FROM lab_tests lt
        INNER JOIN visits v ON lt.visit_id = v.id
        LEFT JOIN users u ON lt.doctor_id = u.id
        WHERE v.patient_id = ? AND v.doctor_id = ?
        ORDER BY lt.created_at DESC
    ");
    $stmt->execute([$patient_id, $user_id]);
}
$stmt->execute([$patient_id]);
$all_lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all vital signs
$stmt = $db->prepare("
    SELECT vs.*, u.full_name as recorded_by_name
    FROM vital_signs vs
    LEFT JOIN users u ON vs.recorded_by = u.id
    WHERE vs.patient_id = ?
    ORDER BY vs.recorded_at DESC
");
$stmt->execute([$patient_id]);
$all_vital_signs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// CALCULATE TOTALS
// ================================================================
$total_visits = count($all_visits);
$total_bills = count($all_bills);
$total_prescriptions = count($all_prescriptions);
$total_lab_tests = count($all_lab_tests);
$total_vital_signs = count($all_vital_signs);

$total_bill_amount = 0;
foreach ($all_bills as $bill) {
    if ($bill['status'] !== 'cancelled') {
        $total_bill_amount += $bill['total_amount'] ?? 0;
    }
}

// ================================================================
// LOG ACTIVITY
// ================================================================
try {
    $stmt = $db->prepare("
        INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) 
        VALUES (?, ?, 'export_patient_pdf', ?, NOW())
    ");
    $stmt->execute([
        $user_id,
        $user_branch_id,
        "Exported patient PDF report for: " . $patient['full_name'] . " (ID: " . $patient['patient_id'] . ")" . 
        ($is_admin ? " (Admin)" : "")
    ]);
} catch (Exception $e) {}

// ================================================================
// GET BRANCH INFO
// ================================================================
$branch_location = '';
$branch_phone = '';
$branch_email = '';
try {
    $stmt = $db->prepare("SELECT name, location, phone, email FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$user_branch_id]);
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch) {
        $user_branch_name = $branch['name'] ?? $user_branch_name;
        $branch_location = $branch['location'] ?? '';
        $branch_phone = $branch['phone'] ?? '';
        $branch_email = $branch['email'] ?? '';
    }
} catch (Exception $e) {}

// ================================================================
// LOGO PATH
// ================================================================
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$logo_base64 = '';
$logo_absolute = $_SERVER['DOCUMENT_ROOT'] . $logo_path;
if (file_exists($logo_absolute)) {
    $logo_data = file_get_contents($logo_absolute);
    $logo_base64 = 'data:image/png;base64,' . base64_encode($logo_data);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Report - <?= htmlspecialchars($patient['full_name']) ?></title>
    
    <style>
        /* ================================================================
           PDF STYLES - SAME AS VISIT PDF
           ================================================================ */
        @page {
            margin: 15mm;
            size: A4;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
            color: #1E293B;
            line-height: 1.5;
            background: white;
            padding: 0;
        }
        
        /* ================================================================
           CONTAINER
           ================================================================ */
        .report-container {
            max-width: 100%;
            padding: 20px 30px;
        }
        
        /* ================================================================
           HEADER - SAME AS VISIT
           ================================================================ */
        .header {
            text-align: center;
            border-bottom: 3px solid #0B5ED7;
            padding-bottom: 12px;
            margin-bottom: 16px;
            position: relative;
        }
        
        .header .logo-title {
            font-size: 22px;
            font-weight: 700;
            color: #0B5ED7;
            letter-spacing: 1px;
        }
        
        .header .logo-sub {
            font-size: 11px;
            color: #64748B;
            margin-top: 2px;
        }
        
        .header .branch-info {
            font-size: 9px;
            color: #64748B;
            margin-top: 2px;
        }
        
        .header .report-number {
            font-size: 10px;
            color: #64748B;
            font-weight: 600;
            position: absolute;
            right: 0;
            top: 2px;
        }
        
        .header .logo-img {
            max-height: 50px;
            margin-bottom: 4px;
        }
        
        /* ================================================================
           TITLE
           ================================================================ */
        .page-title {
            font-size: 16px;
            font-weight: 700;
            color: #0B5ED7;
            text-align: center;
            margin: 4px 0 2px 0;
        }
        
        .page-subtitle {
            font-size: 10px;
            color: #64748B;
            text-align: center;
            margin-bottom: 10px;
        }
        
        /* ================================================================
           SECTION TITLES
           ================================================================ */
        .section-title {
            font-size: 12px;
            font-weight: 700;
            color: #0B5ED7;
            border-bottom: 2px solid #0B5ED7;
            padding-bottom: 4px;
            margin: 14px 0 10px 0;
        }
        
        .section-title.green { color: #059669; border-color: #059669; }
        .section-title.purple { color: #7C3AED; border-color: #7C3AED; }
        .section-title.orange { color: #D97706; border-color: #D97706; }
        .section-title.red { color: #DC2626; border-color: #DC2626; }
        
        /* ================================================================
           GRIDS
           ================================================================ */
        .row-2col { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .row-3col { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; }
        .row-4col { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 8px; }
        
        /* ================================================================
           INFO CARDS - SAME AS VISIT
           ================================================================ */
        .info-card {
            background: #F8FAFC;
            border-radius: 4px;
            padding: 8px 12px;
            border: 1px solid #E2E8F0;
        }
        
        .info-card .label {
            font-size: 7px;
            font-weight: 600;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: block;
        }
        
        .info-card .value {
            font-size: 10px;
            font-weight: 600;
            color: #1E293B;
            display: block;
            margin-top: 1px;
        }
        
        .info-card.blue { border-left: 3px solid #0B5ED7; }
        .info-card.green { border-left: 3px solid #059669; }
        .info-card.purple { border-left: 3px solid #7C3AED; }
        .info-card.orange { border-left: 3px solid #D97706; }
        .info-card.red { border-left: 3px solid #DC2626; }
        
        /* ================================================================
           SUMMARY CARDS
           ================================================================ */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 8px;
            margin-bottom: 10px;
        }
        
        .summary-card {
            background: #F8FAFC;
            border-radius: 4px;
            padding: 10px 12px;
            text-align: center;
            border: 1px solid #E2E8F0;
        }
        
        .summary-card .number {
            font-size: 16px;
            font-weight: 700;
            color: #0B5ED7;
        }
        
        .summary-card .label {
            font-size: 7px;
            color: #64748B;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.03em;
            display: block;
        }
        
        .summary-card .amount {
            font-size: 8px;
            font-weight: 600;
            color: #059669;
            margin-top: 2px;
        }
        
        /* ================================================================
           TABLES
           ================================================================ */
        .table-wrap { overflow-x: auto; }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8px;
            margin-top: 4px;
        }
        
        .data-table thead th {
            background: #0B5ED7;
            color: white;
            font-weight: 600;
            padding: 6px 10px;
            text-align: left;
            font-size: 7px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        
        .data-table thead th:first-child { border-radius: 4px 0 0 0; }
        .data-table thead th:last-child { border-radius: 0 4px 0 0; }
        
        .data-table tbody td {
            padding: 5px 10px;
            border-bottom: 1px solid #E2E8F0;
            color: #1E293B;
            vertical-align: middle;
            font-size: 8px;
        }
        
        .data-table tbody tr:nth-child(even) { background: #F8FAFC; }
        
        /* ================================================================
           BADGES
           ================================================================ */
        .badge {
            display: inline-block;
            padding: 1px 8px;
            border-radius: 10px;
            font-size: 6px;
            font-weight: 600;
        }
        .badge-success { background: #D1FAE5; color: #059669; }
        .badge-warning { background: #FEF3C7; color: #D97706; }
        .badge-danger { background: #FEE2E2; color: #EF4444; }
        .badge-info { background: #E8F0FE; color: #0B5ED7; }
        .badge-secondary { background: #E2E8F0; color: #64748B; }
        .badge-purple { background: #EDE9FE; color: #7C3AED; }
        
        /* ================================================================
           DETAIL ROWS
           ================================================================ */
        .detail-row {
            display: flex;
            padding: 4px 0;
            border-bottom: 1px solid #E2E8F0;
        }
        .detail-row:last-child { border-bottom: none; }
        
        .detail-label {
            font-weight: 600;
            color: #64748B;
            width: 100px;
            flex-shrink: 0;
            font-size: 9px;
        }
        
        .detail-value {
            flex: 1;
            color: #1E293B;
            font-size: 9px;
        }
        
        /* ================================================================
           FOOTER WITH STAMP - SAME AS VISIT
           ================================================================ */
        .footer-section {
            margin-top: 20px;
            padding-top: 12px;
            border-top: 2px solid #E2E8F0;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .footer-left {
            flex: 1;
        }
        
        .footer-left .doctor-name {
            font-size: 10px;
            font-weight: 600;
            color: #1E293B;
        }
        
        .footer-left .doctor-details {
            font-size: 8px;
            color: #64748B;
            margin-top: 1px;
        }
        
        .signature-area {
            display: flex;
            gap: 20px;
            margin-top: 8px;
        }
        
        .signature-area .sig-item {
            text-align: center;
        }
        
        .signature-area .sig-line {
            width: 80px;
            border-bottom: 1px solid #1E293B;
            height: 16px;
            margin: 0 auto 1px auto;
        }
        
        .signature-area .sig-label {
            font-size: 7px;
            color: #64748B;
        }
        
        /* ================================================================
           OFFICIAL STAMP - SAME AS VISIT
           ================================================================ */
        .stamp-container {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            min-width: 150px;
        }
        
        .stamp {
            border: 2px solid #0B5ED7;
            border-radius: 4px;
            padding: 6px 14px;
            text-align: center;
            background: #F8FAFC;
            width: 140px;
        }
        
        .stamp .stamp-title {
            font-size: 7px;
            font-weight: 600;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .stamp .stamp-name {
            font-size: 12px;
            font-weight: 700;
            color: #0B5ED7;
            margin-top: 1px;
        }
        
        .stamp .stamp-line {
            border-top: 1px dashed #CBD5E1;
            margin: 3px 0;
        }
        
        .stamp .stamp-doctor {
            font-size: 8px;
            color: #0B5ED7;
            font-weight: 600;
        }
        
        .stamp .stamp-signature {
            font-size: 8px;
            color: #1E293B;
        }
        
        .stamp .stamp-date {
            font-size: 7px;
            color: #94A3B8;
            margin-top: 1px;
        }
        
        /* ================================================================
           FOOTER NOTE - SAME AS VISIT
           ================================================================ */
        .footer-note {
            text-align: center;
            font-size: 6px;
            color: #94A3B8;
            margin-top: 10px;
            padding-top: 6px;
            border-top: 1px solid #E2E8F0;
        }
        
        .footer-note .brand {
            color: #0B5ED7;
            font-weight: 600;
        }
        
        .footer-note .slogan {
            font-size: 8px;
            color: #0B5ED7;
            font-weight: 600;
            margin-top: 2px;
        }
        
        /* ================================================================
           TOOLBAR - PRINT HIDE
           ================================================================ */
        .toolbar {
            display: none !important;
        }
        
        /* ================================================================
           PRINT OPTIMIZATION
           ================================================================ */
        @media print {
            body { padding: 0; }
            .report-container { padding: 10px 15px; }
            .info-card { break-inside: avoid; }
            .stamp { break-inside: avoid; }
            .summary-card { break-inside: avoid; }
            .data-table tbody tr:nth-child(even) { background: #F8FAFC; }
        }
        
        @media (max-width: 768px) {
            .row-2col { grid-template-columns: 1fr; }
            .row-3col { grid-template-columns: 1fr 1fr; }
            .row-4col { grid-template-columns: 1fr 1fr; }
            .summary-grid { grid-template-columns: 1fr 1fr; }
            .footer-section { flex-direction: column; }
            .stamp-container { justify-content: flex-start; }
            .header .report-number { position: static; text-align: right; margin-top: 4px; }
            .detail-row { flex-direction: column; }
            .detail-label { width: 100%; margin-bottom: 1px; }
            .signature-area { flex-direction: column; gap: 6px; }
        }
    </style>
</head>
<body>

<div class="report-container">

    <!-- ================================================================ -->
    <!-- HEADER - SAME AS VISIT -->
    <!-- ================================================================ -->
    <div class="header">
        <?php if ($logo_base64): ?>
            <img src="<?= $logo_base64 ?>" alt="Braick Dispensary" class="logo-img">
        <?php endif; ?>
        <div class="logo-title">🏥 BRAICK DISPENSARY</div>
        <div class="logo-sub">Quality Healthcare Services</div>
        <div class="branch-info">
            <?= htmlspecialchars($user_branch_name) ?> | 
            <?= htmlspecialchars($branch_location) ?> | 
            Tel: <?= htmlspecialchars($branch_phone) ?>
            <?php if (!empty($branch_email)): ?>
                | Email: <?= htmlspecialchars($branch_email) ?>
            <?php endif; ?>
        </div>
        <div class="report-number">Report #: PAT-<?= str_pad($patient_id, 4, '0', STR_PAD_LEFT) ?></div>
    </div>

    <!-- ================================================================ -->
    <!-- TITLE -->
    <!-- ================================================================ -->
    <div class="page-title">📋 PATIENT MEDICAL REPORT</div>
    <div class="page-subtitle">Complete Patient Health Summary</div>

    <!-- ================================================================ -->
    <!-- PATIENT INFORMATION -->
    <!-- ================================================================ -->
    <div class="section-title">👤 Patient Information</div>
    <div class="row-2col">
        <div>
            <div class="info-card blue">
                <span class="label">Full Name</span>
                <span class="value"><?= htmlspecialchars($patient['full_name']) ?></span>
            </div>
            <div class="info-card" style="margin-top:4px;">
                <span class="label">Patient ID</span>
                <span class="value font-mono"><?= htmlspecialchars($patient['patient_id']) ?></span>
            </div>
            <div class="info-card" style="margin-top:4px;">
                <span class="label">Gender</span>
                <span class="value"><?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></span>
            </div>
            <div class="info-card" style="margin-top:4px;">
                <span class="label">Date of Birth</span>
                <span class="value"><?= $patient['date_of_birth'] ? date('d M Y', strtotime($patient['date_of_birth'])) : 'N/A' ?></span>
            </div>
        </div>
        <div>
            <div class="info-card green">
                <span class="label">Phone</span>
                <span class="value"><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></span>
            </div>
            <div class="info-card" style="margin-top:4px;">
                <span class="label">Blood Group</span>
                <span class="value"><?= htmlspecialchars($patient['blood_group'] ?? 'N/A') ?></span>
            </div>
            <div class="info-card" style="margin-top:4px;">
                <span class="label">Address</span>
                <span class="value" style="font-weight:400;font-size:9px;"><?= htmlspecialchars($patient['address'] ?? 'N/A') ?></span>
            </div>
            <div class="info-card" style="margin-top:4px;">
                <span class="label">Allergies</span>
                <span class="value" style="font-weight:400;color:#DC2626;"><?= htmlspecialchars($patient['allergies'] ?? 'None reported') ?></span>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- SUMMARY STATISTICS -->
    <!-- ================================================================ -->
    <div class="section-title green">📊 Summary Statistics</div>
    <div class="summary-grid">
        <div class="summary-card">
            <span class="number"><?= $total_visits ?></span>
            <span class="label">Visits</span>
        </div>
        <div class="summary-card">
            <span class="number"><?= $total_bills ?></span>
            <span class="label">Bills</span>
            <span class="amount">TSh <?= number_format($total_bill_amount) ?></span>
        </div>
        <div class="summary-card">
            <span class="number"><?= $total_prescriptions ?></span>
            <span class="label">Prescriptions</span>
        </div>
        <div class="summary-card">
            <span class="number"><?= $total_lab_tests ?></span>
            <span class="label">Lab Tests</span>
        </div>
        <div class="summary-card">
            <span class="number"><?= $total_vital_signs ?></span>
            <span class="label">Vital Signs</span>
        </div>
        <div class="summary-card">
            <span class="number"><?= $patient['id'] ?></span>
            <span class="label">Patient ID</span>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- VITAL SIGNS -->
    <!-- ================================================================ -->
    <?php if (count($all_vital_signs) > 0): ?>
    <div class="section-title purple">❤️ Vital Signs History (<?= count($all_vital_signs) ?>)</div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Temp (°C)</th>
                    <th>BP (mmHg)</th>
                    <th>Pulse</th>
                    <th>Weight</th>
                    <th>Height</th>
                    <th>BMI</th>
                    <th>Recorded By</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; foreach ($all_vital_signs as $vs): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><?= date('d M Y', strtotime($vs['recorded_at'])) ?></td>
                    <td><?= $vs['temperature'] ?? '-' ?></td>
                    <td><?= ($vs['blood_pressure_systolic'] ?? '') ? $vs['blood_pressure_systolic'] . '/' . ($vs['blood_pressure_diastolic'] ?? '') : '-' ?></td>
                    <td><?= $vs['pulse_rate'] ?? '-' ?></td>
                    <td><?= $vs['weight'] ?? '-' ?></td>
                    <td><?= $vs['height'] ?? '-' ?></td>
                    <td><?= $vs['bmi'] ?? '-' ?></td>
                    <td><?= htmlspecialchars($vs['recorded_by_name'] ?? 'N/A') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- VISITS -->
    <!-- ================================================================ -->
    <?php if (count($all_visits) > 0): ?>
    <div class="section-title">📋 Visit History (<?= count($all_visits) ?>)</div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Visit #</th>
                    <th>Doctor</th>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; foreach ($all_visits as $visit): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><?= htmlspecialchars($visit['visit_number']) ?></td>
                    <td><?= htmlspecialchars($visit['doctor_name'] ?? 'N/A') ?></td>
                    <td><?= date('d M Y', strtotime($visit['visit_date'])) ?></td>
                    <td><?= ucfirst($visit['visit_type'] ?? 'N/A') ?></td>
                    <td><span class="badge <?= $visit['status'] === 'completed' ? 'badge-success' : ($visit['status'] === 'pending' ? 'badge-warning' : 'badge-info') ?>"><?= ucfirst($visit['status'] ?? 'N/A') ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- PRESCRIPTIONS -->
    <!-- ================================================================ -->
    <?php if (count($all_prescriptions) > 0): ?>
    <div class="section-title purple">💊 Prescription History (<?= count($all_prescriptions) ?>)</div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Prescription #</th>
                    <th>Doctor</th>
                    <th>Diagnosis</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; foreach ($all_prescriptions as $prescription): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><?= htmlspecialchars($prescription['prescription_number']) ?></td>
                    <td><?= htmlspecialchars($prescription['doctor_name'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($prescription['diagnosis'] ?? 'N/A') ?></td>
                    <td><span class="badge <?= $prescription['status'] === 'dispensed' ? 'badge-success' : ($prescription['status'] === 'pending' ? 'badge-warning' : 'badge-info') ?>"><?= ucfirst($prescription['status'] ?? 'N/A') ?></span></td>
                    <td><?= date('d M Y', strtotime($prescription['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- LAB TESTS -->
    <!-- ================================================================ -->
    <?php if (count($all_lab_tests) > 0): ?>
    <div class="section-title orange">🧪 Lab Test History (<?= count($all_lab_tests) ?>)</div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Test Name</th>
                    <th>Doctor</th>
                    <th>Price</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; foreach ($all_lab_tests as $test): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($test['doctor_name'] ?? 'N/A') ?></td>
                    <td>TSh <?= number_format($test['test_price'] ?? 0) ?></td>
                    <td><span class="badge <?= $test['status'] === 'completed' ? 'badge-success' : ($test['status'] === 'pending' ? 'badge-warning' : 'badge-info') ?>"><?= ucfirst(str_replace('_', ' ', $test['status'] ?? 'N/A')) ?></span></td>
                    <td><?= date('d M Y', strtotime($test['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- BILLS -->
    <!-- ================================================================ -->
    <?php if (count($all_bills) > 0): ?>
    <div class="section-title green">💰 Bill History (<?= count($all_bills) ?>)</div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Bill #</th>
                    <th>Total</th>
                    <th>Paid</th>
                    <th>Balance</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; foreach ($all_bills as $bill): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><?= htmlspecialchars($bill['bill_number']) ?></td>
                    <td>TSh <?= number_format($bill['total_amount'] ?? 0) ?></td>
                    <td>TSh <?= number_format($bill['paid_amount'] ?? 0) ?></td>
                    <td>TSh <?= number_format($bill['balance'] ?? 0) ?></td>
                    <td><span class="badge <?= $bill['status'] === 'paid' ? 'badge-success' : ($bill['status'] === 'pending' ? 'badge-warning' : 'badge-info') ?>"><?= ucfirst($bill['status'] ?? 'N/A') ?></span></td>
                    <td><?= date('d M Y', strtotime($bill['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER WITH STAMP - SAME AS VISIT -->
    <!-- ================================================================ -->
    <div class="footer-section">
        <div class="footer-left">
            <div class="doctor-name">Dr. <?= htmlspecialchars($user_full_name) ?></div>
            <div class="doctor-details">
                <?= htmlspecialchars($patient['assigned_doctor_name'] ?? 'Medical Doctor') ?><br>
                <?php if (!empty($branch_phone)): ?>
                    Tel: <?= htmlspecialchars($branch_phone) ?>
                <?php endif; ?>
                <?php if (!empty($branch_email)): ?>
                    | Email: <?= htmlspecialchars($branch_email) ?>
                <?php endif; ?>
            </div>
            
            <div class="signature-area">
                <div class="sig-item">
                    <div class="sig-line"></div>
                    <span class="sig-label">Doctor's Signature</span>
                </div>
                <div class="sig-item">
                    <div class="sig-line"></div>
                    <span class="sig-label">Date</span>
                </div>
            </div>
        </div>
        
        <!-- OFFICIAL STAMP - SAME AS VISIT -->
        <div class="stamp-container">
            <div class="stamp">
                <div class="stamp-title">Official Stamp</div>
                <div class="stamp-name">BRAICK DISPENSARY</div>
                <div class="stamp-line"></div>
                <div class="stamp-doctor">Dr. <?= htmlspecialchars($user_full_name) ?></div>
                <div class="stamp-signature">_________________________</div>
                <div class="stamp-date">Date: <?= date('d M Y') ?></div>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER NOTE - SAME AS VISIT -->
    <!-- ================================================================ -->
    <div class="footer-note">
        <div>
            <span class="brand">🏥 Braick Dispensary</span> 
            <span style="color:#94A3B8;">|</span> 
            Patient Report: <?= htmlspecialchars($patient['full_name']) ?>
            <span style="color:#94A3B8;">|</span> 
            Generated: <?= date('d M Y, h:i A') ?>
            <?php if ($is_admin): ?>
                <span style="color:#94A3B8;">|</span> 
                <span style="color:#DC2626;">👑 Admin</span>
            <?php endif; ?>
        </div>
        <div class="slogan">⭐ Braick Dispensary - Tunajali Afya Yako ⭐</div>
    </div>

</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // AUTO PRINT
    // ================================================================
    window.onload = function() {
        // Small delay to ensure everything renders
        setTimeout(function() {
            window.print();
        }, 500);
    };

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        // Escape = Close
        if (e.key === 'Escape') {
            window.close();
        }
        // Ctrl+P = Print (already handled by browser)
    });

    console.log('%c📄 Braick Dispensary - Patient Report', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Patient: <?= htmlspecialchars($patient['full_name']) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📋 ID: <?= htmlspecialchars($patient['patient_id']) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📊 Visits: <?= $total_visits ?> | Bills: <?= $total_bills ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c💊 Prescriptions: <?= $total_prescriptions ?> | 🧪 Lab Tests: <?= $total_lab_tests ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c❤️ Vital Signs: <?= $total_vital_signs ?>', 'font-size:13px; color:#EC4899;');
    console.log('%c⭐ Braick Dispensary - Tunajali Afya Yako', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🖨️ Auto-print in 500ms | ESC to Close', 'font-size:13px; color:#64748B;');
</script>

</body>
</html>