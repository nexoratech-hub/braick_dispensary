<?php
// ================================================================
// FILE: frontend/pages/laboratory/print_result.php
// LABORATORY - PRINT TEST RESULT(S) - WITH OFFICIAL STAMP
// ================================================================
// ✅ Print ALL test results za patient mmoja
// ✅ Professional A4 layout
// ✅ Auto-trigger print dialog
// ✅ Support: single test, group view, patient+visit view
// ✅ Braick logo header
// ✅ OFFICIAL STAMP (badala ya Verified By)
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SESSION['role'] !== 'laboratory' && $_SESSION['role'] !== 'admin') {
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

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Lab Technician';
$user_role = $_SESSION['role'];
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_username = $_SESSION['username'] ?? 'lab.technician';

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die('Database connection error: ' . $e->getMessage());
}

// ================================================================
// GET PARAMETERS
// ================================================================
$test_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$visit_id = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
$view_all = isset($_GET['view']) && $_GET['view'] === 'all';

$tests = [];
$patient_info = [];

try {
    // ============================================================
    // CASE 1: PRINT ALL FOR A PATIENT (by patient_id)
    // ============================================================
    if ($patient_id > 0) {
        // Get ALL completed tests for this patient
        $query = "
            SELECT 
                lt.*,
                p.full_name as patient_name,
                p.patient_id as patient_code,
                p.phone, p.gender, p.date_of_birth, p.blood_group,
                p.allergies, p.address,
                u.full_name as doctor_name,
                u.specialty as doctor_specialty,
                v.visit_number, v.visit_type, v.diagnosis, v.symptoms,
                v.hpi, v.physical_exam, v.treatment
            FROM lab_tests lt
            LEFT JOIN visits v ON lt.visit_id = v.id
            LEFT JOIN patients p ON v.patient_id = p.id
            LEFT JOIN users u ON lt.doctor_id = u.id
            WHERE lt.patient_id = ?
            AND lt.branch_id = ?
            AND lt.status = 'completed'
        ";
        $params = [$patient_id, $user_branch_id];
        
        // Kama visit_id imetolewa, filter kwa visit hiyo
        if ($visit_id > 0) {
            $query .= " AND lt.visit_id = ?";
            $params[] = $visit_id;
        }
        
        $query .= " ORDER BY lt.visit_id DESC, lt.completed_at ASC";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    // ============================================================
    // CASE 2: PRINT SINGLE TEST (by test_id)
    // ============================================================
    elseif ($test_id > 0) {
        $stmt = $db->prepare("
            SELECT 
                lt.*,
                p.full_name as patient_name,
                p.patient_id as patient_code,
                p.phone, p.gender, p.date_of_birth, p.blood_group,
                p.allergies, p.address,
                u.full_name as doctor_name,
                u.specialty as doctor_specialty,
                v.visit_number, v.visit_type, v.diagnosis, v.symptoms,
                v.hpi, v.physical_exam, v.treatment
            FROM lab_tests lt
            LEFT JOIN visits v ON lt.visit_id = v.id
            LEFT JOIN patients p ON v.patient_id = p.id
            LEFT JOIN users u ON lt.doctor_id = u.id
            WHERE lt.id = ? AND lt.branch_id = ?
        ");
        $stmt->execute([$test_id, $user_branch_id]);
        $single = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$single) {
            die('Test not found');
        }
        
        // Get patient_id from this test
        $patient_id = $single['patient_id'] ?? 0;
        
        // Get ALL completed tests for this patient
        if ($patient_id > 0) {
            $stmt2 = $db->prepare("
                SELECT 
                    lt.*,
                    p.full_name as patient_name,
                    p.patient_id as patient_code,
                    p.phone, p.gender, p.date_of_birth, p.blood_group,
                    p.allergies, p.address,
                    u.full_name as doctor_name,
                    u.specialty as doctor_specialty,
                    v.visit_number, v.visit_type, v.diagnosis, v.symptoms,
                    v.hpi, v.physical_exam, v.treatment
                FROM lab_tests lt
                LEFT JOIN visits v ON lt.visit_id = v.id
                LEFT JOIN patients p ON v.patient_id = p.id
                LEFT JOIN users u ON lt.doctor_id = u.id
                WHERE lt.patient_id = ?
                AND lt.branch_id = ?
                AND lt.status = 'completed'
                ORDER BY lt.visit_id DESC, lt.completed_at ASC
            ");
            $stmt2->execute([$patient_id, $user_branch_id]);
            $tests = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $tests = [$single];
        }
    }
    else {
        die('No test specified');
    }
    
    if (empty($tests)) {
        die('No tests found');
    }
    
    // Build patient info from first test
    $first = $tests[0];
    $patient_info = [
        'patient_name' => $first['patient_name'] ?? 'N/A',
        'patient_code' => $first['patient_code'] ?? 'N/A',
        'phone' => $first['phone'] ?? '',
        'gender' => $first['gender'] ?? '',
        'date_of_birth' => $first['date_of_birth'] ?? '',
        'blood_group' => $first['blood_group'] ?? '',
        'allergies' => $first['allergies'] ?? '',
        'address' => $first['address'] ?? '',
        'doctor_name' => $first['doctor_name'] ?? '',
        'doctor_specialty' => $first['doctor_specialty'] ?? '',
        'visit_number' => $first['visit_number'] ?? '',
        'visit_type' => $first['visit_type'] ?? '',
        'diagnosis' => $first['diagnosis'] ?? '',
        'symptoms' => $first['symptoms'] ?? '',
        'hpi' => $first['hpi'] ?? '',
        'physical_exam' => $first['physical_exam'] ?? '',
        'treatment' => $first['treatment'] ?? '',
    ];
    
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}

function calculateAge($dob) {
    if (empty($dob) || $dob === '0000-00-00') return 'N/A';
    try {
        $birthDate = new DateTime($dob);
        $today = new DateTime('today');
        return $birthDate->diff($today)->y;
    } catch (Exception $e) {
        return 'N/A';
    }
}

function formatDateFull($datetime) {
    if (empty($datetime)) return 'N/A';
    return date('d M Y, h:i A', strtotime($datetime));
}

// Get branch info
$branch_info = [];
try {
    $stmt = $db->prepare("SELECT * FROM branches WHERE id = ?");
    $stmt->execute([$user_branch_id]);
    $branch_info = $stmt->fetch(PDO::FETCH_ASSOC) ?? [];
} catch (Exception $e) {
    $branch_info = [];
}

$branch_name_display = $branch_info['name'] ?? $user_branch_name;
$branch_location = $branch_info['location'] ?? '';
$branch_phone = $branch_info['phone'] ?? '';
$branch_email = $branch_info['email'] ?? '';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// Generate unique print ID
$print_id = 'LAB-' . strtoupper(substr(md5($patient_id . time()), 0, 8));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Result - <?= htmlspecialchars($patient_info['patient_name']) ?> - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================ */
        /* PRINT-OPTIMIZED STYLES */
        /* ================================================================ */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', 'Arial', sans-serif;
            background: #E2E8F0;
            color: #1E293B;
            line-height: 1.5;
            padding: 20px;
        }
        
        /* Screen-only controls */
        .screen-controls {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .ctrl-btn {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            box-shadow: 0 4px 16px rgba(11, 94, 215, 0.4);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .ctrl-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(11, 94, 215, 0.5);
        }
        
        .ctrl-btn.green {
            background: linear-gradient(135deg, #059669, #047857);
            box-shadow: 0 4px 16px rgba(5, 150, 105, 0.4);
        }
        
        .ctrl-btn.gray {
            background: linear-gradient(135deg, #64748B, #475569);
            box-shadow: 0 4px 16px rgba(100, 116, 139, 0.4);
        }
        
        /* Main paper container */
        .paper {
            background: white;
            max-width: 210mm;
            margin: 60px auto 20px;
            padding: 15mm 12mm;
            box-shadow: 0 4px 30px rgba(0,0,0,0.15);
            border-radius: 8px;
        }
        
        /* ================================================================ */
        /* HEADER */
        /* ================================================================ */
        .print-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 12px;
            border-bottom: 3px solid #0B5ED7;
            margin-bottom: 16px;
            gap: 20px;
        }
        
        .print-header .clinic-info {
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 1;
        }
        
        .print-header .clinic-logo {
            height: 70px;
            width: auto;
            object-fit: contain;
            flex-shrink: 0;
        }
        
        .print-header .clinic-details h1 {
            font-size: 1.6rem;
            color: #0B5ED7;
            font-weight: 900;
            letter-spacing: -0.5px;
            margin-bottom: 2px;
        }
        
        .print-header .clinic-details .tagline {
            font-size: 0.75rem;
            color: #64748B;
            font-style: italic;
            font-weight: 500;
        }
        
        .print-header .clinic-details .contact {
            font-size: 0.7rem;
            color: #475569;
            margin-top: 4px;
            line-height: 1.4;
        }
        
        .print-header .clinic-details .contact span {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin-right: 12px;
        }
        
        .print-header .report-info {
            text-align: right;
            flex-shrink: 0;
        }
        
        .print-header .report-info .report-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: #0A4CA8;
            text-transform: uppercase;
            letter-spacing: 1px;
            background: #E8F0FE;
            padding: 6px 14px;
            border-radius: 6px;
            display: inline-block;
            margin-bottom: 6px;
        }
        
        .print-header .report-info .report-meta {
            font-size: 0.7rem;
            color: #64748B;
            line-height: 1.6;
        }
        
        .print-header .report-info .report-meta strong {
            color: #1E293B;
            font-family: monospace;
        }
        
        /* ================================================================ */
        /* PATIENT INFO BOX */
        /* ================================================================ */
        .patient-box {
            background: linear-gradient(135deg, #E8F0FE, #F1F5F9);
            border-left: 5px solid #0B5ED7;
            border-radius: 6px;
            padding: 12px 18px;
            margin-bottom: 18px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px 24px;
        }
        
        .patient-box .info-item {
            display: flex;
            gap: 8px;
            align-items: baseline;
            font-size: 0.8rem;
        }
        
        .patient-box .info-item .label {
            font-weight: 700;
            color: #0A4CA8;
            min-width: 90px;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .patient-box .info-item .value {
            color: #1E293B;
            font-weight: 600;
            flex: 1;
        }
        
        .patient-box .info-item .value.patient-name {
            font-size: 0.95rem;
            font-weight: 800;
            color: #0B5ED7;
        }
        
        /* ================================================================ */
        /* CLINICAL INFO BOX */
        /* ================================================================ */
        .clinical-box {
            background: #FFFBEB;
            border-left: 5px solid #D97706;
            border-radius: 6px;
            padding: 12px 18px;
            margin-bottom: 18px;
        }
        
        .clinical-box .clinical-title {
            font-size: 0.75rem;
            font-weight: 800;
            color: #D97706;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .clinical-box .clinical-item {
            margin-bottom: 6px;
            font-size: 0.8rem;
        }
        
        .clinical-box .clinical-item .label {
            font-weight: 700;
            color: #92400E;
            font-size: 0.7rem;
            text-transform: uppercase;
            display: inline-block;
            min-width: 130px;
        }
        
        .clinical-box .clinical-item .value {
            color: #1E293B;
            font-weight: 500;
        }
        
        /* ================================================================ */
        /* SECTION TITLE */
        /* ================================================================ */
        .section-title {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            justify-content: space-between;
        }
        
        .section-title .count-badge {
            background: rgba(255,255,255,0.25);
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 700;
        }
        
        /* ================================================================ */
        /* TEST RESULT TABLE */
        /* ================================================================ */
        .test-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
            margin-bottom: 16px;
            page-break-inside: auto;
        }
        
        .test-table thead {
            background: #0B5ED7;
            color: white;
        }
        
        .test-table thead th {
            padding: 8px 10px;
            text-align: left;
            font-weight: 700;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border: 1px solid #0A4CA8;
            white-space: nowrap;
        }
        
        .test-table tbody td {
            padding: 8px 10px;
            border: 1px solid #CBD5E1;
            vertical-align: top;
            color: #1E293B;
        }
        
        .test-table tbody tr:nth-child(even) {
            background: #F8FAFC;
        }
        
        .test-table .col-no {
            width: 35px;
            text-align: center;
            font-weight: 700;
            color: #0A4CA8;
        }
        
        .test-table .col-test-name {
            font-weight: 700;
            color: #0B5ED7;
            min-width: 130px;
        }
        
        .test-table .col-test-name .test-type {
            font-size: 0.65rem;
            color: #64748B;
            font-weight: 500;
            font-style: italic;
            display: block;
            margin-top: 1px;
        }
        
        .test-table .col-result {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            white-space: pre-wrap;
            word-break: break-word;
            min-width: 180px;
        }
        
        .test-table .col-reference {
            font-family: 'Courier New', monospace;
            font-size: 0.75rem;
            color: #475569;
            min-width: 100px;
        }
        
        .test-table .col-date {
            font-size: 0.7rem;
            color: #64748B;
            white-space: nowrap;
        }
        
        .test-table .col-status {
            text-align: center;
            white-space: nowrap;
        }
        
        .test-table .status-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 10px;
            font-size: 0.6rem;
            font-weight: 700;
            background: #D1FAE5;
            color: #047857;
            border: 1px solid #059669;
        }
        
        /* ================================================================ */
        /* SIGNATURES + OFFICIAL STAMP */
        /* ================================================================ */
        .signature-section {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px dashed #CBD5E1;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 30px;
            page-break-inside: avoid;
        }
        
        .signature-section .signature-block {
            flex: 1;
            text-align: center;
            max-width: 250px;
        }
        
        .signature-section .signature-line {
            border-bottom: 2px solid #1E293B;
            height: 40px;
            margin-bottom: 6px;
        }
        
        .signature-section .signature-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: #1E293B;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .signature-section .signature-sub {
            font-size: 0.65rem;
            color: #64748B;
            margin-top: 2px;
        }
        
        /* ================================================================ */
        /* OFFICIAL STAMP - BADILISHA VERIFIED BY */
        /* ================================================================ */
        .signature-section .stamp-box {
            text-align: center;
            padding: 14px 24px;
            border: 3px solid #0B5ED7;
            border-radius: 10px;
            background: linear-gradient(135deg, #E8F0FE, #F1F5F9);
            min-width: 220px;
            display: inline-block;
            margin: 0 auto;
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.15);
            position: relative;
        }
        
        .signature-section .stamp-box::before {
            content: '';
            position: absolute;
            top: 5px;
            left: 5px;
            right: 5px;
            bottom: 5px;
            border: 1px dashed #0B5ED7;
            border-radius: 6px;
            pointer-events: none;
            opacity: 0.35;
        }
        
        .signature-section .stamp-box .stamp-title {
            font-size: 0.6rem;
            color: #0B5ED7;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-weight: 700;
            margin-bottom: 8px;
            position: relative;
            z-index: 1;
        }
        
        .signature-section .stamp-box .stamp-name {
            font-size: 1.05rem;
            font-weight: 900;
            color: #0A4CA8;
            letter-spacing: 1px;
            margin-bottom: 8px;
            position: relative;
            z-index: 1;
            line-height: 1.2;
        }
        
        .signature-section .stamp-box .stamp-line {
            font-size: 0.7rem;
            color: #475569;
            letter-spacing: 1px;
            margin-bottom: 6px;
            position: relative;
            z-index: 1;
            font-family: monospace;
        }
        
        .signature-section .stamp-box .stamp-date {
            font-size: 0.65rem;
            color: #64748B;
            position: relative;
            z-index: 1;
            font-weight: 600;
        }
        
        /* ================================================================ */
        /* REPORT FOOTER */
        /* ================================================================ */
        .report-footer {
            margin-top: 30px;
            padding-top: 12px;
            border-top: 1px solid #CBD5E1;
            text-align: center;
            font-size: 0.65rem;
            color: #64748B;
            line-height: 1.6;
        }
        
        .report-footer .print-id {
            font-family: monospace;
            font-weight: 700;
            color: #0B5ED7;
        }
        
        .report-footer .footer-brand {
            color: #0B5ED7;
            font-weight: 700;
        }
        
        /* ================================================================ */
        /* PRINT MEDIA */
        /* ================================================================ */
        @media print {
            @page {
                size: A4;
                margin: 10mm;
            }
            
            body {
                background: white !important;
                padding: 0 !important;
                font-size: 11px;
            }
            
            .screen-controls { display: none !important; }
            
            .paper {
                max-width: 100%;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
                border-radius: 0 !important;
            }
            
            .test-table {
                font-size: 10px;
            }
            
            .test-table thead th {
                font-size: 9px;
                padding: 6px 8px;
            }
            
            .test-table tbody td {
                padding: 6px 8px;
            }
            
            .print-header .clinic-details h1 {
                font-size: 1.4rem;
            }
            
            .test-result-card {
                page-break-inside: avoid;
            }
            
            .signature-section {
                page-break-inside: avoid;
            }
            
            /* Ensure colors print */
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            
            /* Stamp print-friendly */
            .signature-section .stamp-box {
                border: 3px solid #0B5ED7 !important;
                background: #E8F0FE !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
        
        /* ================================================================ */
        /* RESPONSIVE (screen) */
        /* ================================================================ */
        @media (max-width: 768px) {
            body { padding: 10px; }
            .paper { margin: 80px 0 10px; padding: 15px; }
            .screen-controls { 
                top: auto; 
                bottom: 20px; 
                right: 20px; 
                left: 20px; 
                justify-content: center;
            }
            .print-header { flex-direction: column; }
            .print-header .report-info { text-align: left; }
            .patient-box { grid-template-columns: 1fr; }
            .signature-section { flex-direction: column; align-items: center; }
            .signature-section .signature-block { max-width: 100%; }
            .signature-section .stamp-box { min-width: 100%; }
        }
        
        /* Loading overlay */
        .loading-overlay {
            position: fixed;
            inset: 0;
            background: rgba(255,255,255,0.95);
            z-index: 99999;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 16px;
        }
        
        .loading-overlay .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #E2E8F0;
            border-top-color: #0B5ED7;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .loading-overlay .loading-text {
            color: #0B5ED7;
            font-weight: 700;
            font-size: 1rem;
        }
    </style>
</head>
<body>

<!-- LOADING OVERLAY -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="spinner"></div>
    <div class="loading-text">
        <i class="fas fa-print"></i> Preparing to print...
    </div>
</div>

<!-- SCREEN-ONLY CONTROLS -->
<div class="screen-controls">
    <button onclick="window.print()" class="ctrl-btn green">
        <i class="fas fa-print"></i> Print Now
    </button>
    <a href="view_test.php?patient_id=<?= $patient_id ?><?= $visit_id ? '&visit_id=' . $visit_id : '' ?>&view=all" class="ctrl-btn">
        <i class="fas fa-arrow-left"></i> Back
    </a>
    <a href="completed_tests.php" class="ctrl-btn gray">
        <i class="fas fa-list"></i> All Tests
    </a>
</div>

<!-- PAPER -->
<div class="paper">

    <!-- ================================================================ -->
    <!-- HEADER -->
    <!-- ================================================================ -->
    <div class="print-header">
        <div class="clinic-info">
            <img src="<?= $logo_path ?>" alt="Braick Logo" class="clinic-logo" onerror="this.style.display='none'">
            <div class="clinic-details">
                <h1>BRAICK DISPENSARY</h1>
                <div class="tagline">Tunajali Afya Yako</div>
                <div class="contact">
                    <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($branch_location ?: $branch_name_display) ?></span>
                    <?php if (!empty($branch_phone)): ?>
                        <span><i class="fas fa-phone"></i> <?= htmlspecialchars($branch_phone) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($branch_email)): ?>
                        <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($branch_email) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="report-info">
            <div class="report-title">
                <i class="fas fa-flask"></i> Lab Report
            </div>
            <div class="report-meta">
                <div>Report ID: <strong><?= $print_id ?></strong></div>
                <div>Printed: <strong><?= date('d M Y, h:i A') ?></strong></div>
                <div>Branch: <strong><?= htmlspecialchars($branch_name_display) ?></strong></div>
            </div>
        </div>
    </div>
    
    <!-- ================================================================ -->
    <!-- PATIENT INFO -->
    <!-- ================================================================ -->
    <div class="patient-box">
        <div class="info-item">
            <span class="label">Patient Name:</span>
            <span class="value patient-name"><?= htmlspecialchars($patient_info['patient_name']) ?></span>
        </div>
        <div class="info-item">
            <span class="label">Patient ID:</span>
            <span class="value" style="font-family:monospace;"><?= htmlspecialchars($patient_info['patient_code']) ?></span>
        </div>
        <?php if (!empty($patient_info['gender'])): ?>
        <div class="info-item">
            <span class="label">Gender / Age:</span>
            <span class="value">
                <?= htmlspecialchars($patient_info['gender']) ?> 
                • <?= calculateAge($patient_info['date_of_birth']) ?> yrs
            </span>
        </div>
        <?php endif; ?>
        <?php if (!empty($patient_info['blood_group'])): ?>
        <div class="info-item">
            <span class="label">Blood Group:</span>
            <span class="value"><?= htmlspecialchars($patient_info['blood_group']) ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($patient_info['phone'])): ?>
        <div class="info-item">
            <span class="label">Phone:</span>
            <span class="value"><?= htmlspecialchars($patient_info['phone']) ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($patient_info['doctor_name'])): ?>
        <div class="info-item">
            <span class="label">Doctor:</span>
            <span class="value">Dr. <?= htmlspecialchars($patient_info['doctor_name']) ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($patient_info['visit_number'])): ?>
        <div class="info-item">
            <span class="label">Visit #:</span>
            <span class="value" style="font-family:monospace;"><?= htmlspecialchars($patient_info['visit_number']) ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($patient_info['allergies'])): ?>
        <div class="info-item" style="grid-column:1/-1;">
            <span class="label" style="color:#DC2626;">⚠️ Allergies:</span>
            <span class="value" style="color:#DC2626;font-weight:700;">
                <?= htmlspecialchars($patient_info['allergies']) ?>
            </span>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- ================================================================ -->
    <!-- CLINICAL INFO (if available) -->
    <!-- ================================================================ -->
    <?php 
    $has_clinical = !empty($patient_info['diagnosis']) || 
                    !empty($patient_info['symptoms']) || 
                    !empty($patient_info['treatment']);
    ?>
    
    <?php if ($has_clinical): ?>
    <div class="clinical-box">
        <div class="clinical-title">
            <i class="fas fa-stethoscope"></i> Clinical Information
        </div>
        <?php if (!empty($patient_info['symptoms'])): ?>
        <div class="clinical-item">
            <span class="label">Symptoms:</span>
            <span class="value"><?= htmlspecialchars($patient_info['symptoms']) ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($patient_info['diagnosis'])): ?>
        <div class="clinical-item">
            <span class="label">Diagnosis:</span>
            <span class="value" style="color:#047857;font-weight:700;">
                <?= htmlspecialchars($patient_info['diagnosis']) ?>
            </span>
        </div>
        <?php endif; ?>
        <?php if (!empty($patient_info['treatment'])): ?>
        <div class="clinical-item">
            <span class="label">Treatment Plan:</span>
            <span class="value"><?= htmlspecialchars($patient_info['treatment']) ?></span>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- ================================================================ -->
    <!-- TEST RESULTS TABLE -->
    <!-- ================================================================ -->
    <div class="section-title">
        <span><i class="fas fa-flask"></i> Laboratory Test Results</span>
        <span class="count-badge">
            <?= count($tests) ?> <?= count($tests) == 1 ? 'Test' : 'Tests' ?>
        </span>
    </div>
    
    <table class="test-table">
        <thead>
            <tr>
                <th class="col-no">#</th>
                <th class="col-test-name">Test Name</th>
                <th class="col-result">Result</th>
                <th class="col-reference">Reference Range</th>
                <th class="col-date">Completed</th>
                <th class="col-status">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php $i = 1; foreach ($tests as $test): ?>
                <tr>
                    <td class="col-no"><?= $i++ ?></td>
                    <td class="col-test-name">
                        <strong><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></strong>
                        <?php if (!empty($test['test_type'])): ?>
                            <span class="test-type"><?= htmlspecialchars($test['test_type']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($test['sample_type'])): ?>
                            <span class="test-type">
                                <i class="fas fa-vial"></i> Sample: <?= htmlspecialchars($test['sample_type']) ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="col-result">
                        <?php if (!empty($test['results'])): ?>
                            <?= nl2br(htmlspecialchars($test['results'])) ?>
                        <?php else: ?>
                            <span style="color:#94A3B8;font-style:italic;">No result recorded</span>
                        <?php endif; ?>
                    </td>
                    <td class="col-reference">
                        <?= !empty($test['reference_range']) ? htmlspecialchars($test['reference_range']) : '—' ?>
                    </td>
                    <td class="col-date">
                        <?= !empty($test['completed_at']) ? date('d M Y', strtotime($test['completed_at'])) : 'N/A' ?>
                        <br>
                        <span style="font-size:0.65rem;">
                            <?= !empty($test['completed_at']) ? date('h:i A', strtotime($test['completed_at'])) : '' ?>
                        </span>
                    </td>
                    <td class="col-status">
                        <span class="status-badge">
                            <i class="fas fa-check-circle"></i> Completed
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <!-- ================================================================ -->
    <!-- SIGNATURES + OFFICIAL STAMP -->
    <!-- ================================================================ -->
    <div class="signature-section">
        <!-- Lab Technician Signature -->
        <div class="signature-block">
            <div class="signature-line"></div>
            <div class="signature-label">Lab Technician</div>
            <div class="signature-sub"><?= htmlspecialchars($user_full_name) ?></div>
            <div class="signature-sub">Date: <?= date('d M Y') ?></div>
        </div>
        
        <!-- ✅ OFFICIAL STAMP (badala ya Verified By) -->
        <div class="stamp-box">
            <div class="stamp-title">Official Stamp</div>
            <div class="stamp-name">BRAICK DISPENSARY</div>
            <div class="stamp-line">______________________</div>
            <div class="stamp-date">Date: <?= date('d M Y') ?></div>
        </div>
    </div>
    
    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <div class="report-footer">
        <div>
            <span class="footer-brand">Braick Dispensary</span> Management System
            • This is a computer-generated report
            • Report ID: <span class="print-id"><?= $print_id ?></span>
        </div>
        <div style="margin-top:4px;">
            Generated on <?= date('d M Y, h:i A') ?> • 
            Powered by Braick Health System • 
            &copy; <?= date('Y') ?> All rights reserved
        </div>
    </div>
    
</div>

<script>
    // ================================================================
    // AUTO-TRIGGER PRINT (baada ya page kumaliza ku-load)
    // ================================================================
    window.addEventListener('load', function() {
        // Subiri kidogo ili logo ikamilike ku-load
        setTimeout(function() {
            // Ficha loading overlay
            var overlay = document.getElementById('loadingOverlay');
            if (overlay) {
                overlay.style.opacity = '0';
                overlay.style.transition = 'opacity 0.3s ease';
                setTimeout(function() {
                    overlay.style.display = 'none';
                }, 300);
            }
            
            // Auto-trigger print dialog
            setTimeout(function() {
                window.print();
            }, 200);
        }, 800);
    });
    
    // ================================================================
    // KEYBOARD SHORTCUT
    // ================================================================
    document.addEventListener('keydown', function(e) {
        // Ctrl+P → Print
        if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
            e.preventDefault();
            window.print();
        }
        // ESC → Close tab
        if (e.key === 'Escape') {
            window.close();
        }
    });
    
    // ================================================================
    // AFTER PRINT - Show message
    // ================================================================
    window.addEventListener('afterprint', function() {
        console.log('%c✅ Print dialog closed', 'color:#0B5ED7;font-weight:bold;');
    });
    
    console.log('%c🖨️ Braick - Print Result (With Official Stamp)', 'font-size:18px;font-weight:bold;color:#0B5ED7;');
    console.log('%c✅ Print ALL test results za patient', 'font-size:13px;color:#34D399;');
    console.log('%c✅ Auto-trigger print dialog', 'font-size:13px;color:#34D399;');
    console.log('%c✅ OFFICIAL STAMP badala ya Verified By', 'font-size:13px;color:#34D399;font-weight:bold;');
    console.log('%c✅ Patient: <?= htmlspecialchars($patient_info['patient_name']) ?>', 'font-size:13px;color:#64748B;');
    console.log('%c📊 Tests: <?= count($tests) ?>', 'font-size:13px;color:#64748B;');
</script>

</body>
</html>