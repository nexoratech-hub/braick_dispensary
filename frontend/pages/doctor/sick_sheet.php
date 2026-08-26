<?php
// ================================================================
// FILE: frontend/pages/doctor/sick_sheet.php
// DOCTOR - CREATE SICK SHEET WITH BEAUTIFUL DESIGN
// - External patients saved to external_sick_sheets table
// - Registered patients saved to patient_documents
// - PDF Generation with Official Stamp (SAME AS VISIT PDF)
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// LOGIN PROTECTION
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// GET DOCTOR INFO
// ================================================================
$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['full_name'] ?? 'Doctor';
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;
$doctor_specialty = $_SESSION['specialty'] ?? 'General Medicine';
$profile_pic = $_SESSION['profile_pic'] ?? '';

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
// CHECK FOR PDF LIBRARIES
// ================================================================
$pdf_library = 'none';
$pdf_available = false;

// Check Dompdf
$dompdf_paths = [
    __DIR__ . '/../../../vendor/autoload.php',
    __DIR__ . '/../../../../vendor/autoload.php',
];

foreach ($dompdf_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        if (class_exists('Dompdf\Dompdf')) {
            $pdf_library = 'dompdf';
            $pdf_available = true;
            break;
        }
    }
}

// Check mPDF if Dompdf not found
if (!$pdf_available) {
    $mpdf_paths = [
        __DIR__ . '/../../../vendor/autoload.php',
        __DIR__ . '/../../../../vendor/autoload.php',
    ];
    foreach ($mpdf_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            if (class_exists('Mpdf\Mpdf')) {
                $pdf_library = 'mpdf';
                $pdf_available = true;
                break;
            }
        }
    }
}

// ================================================================
// GET PARAMETERS
// ================================================================
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'manual';

// ================================================================
// GET REGISTERED PATIENTS FOR SELECT
// ================================================================
$patients = [];
try {
    $stmt = $db->prepare("
        SELECT DISTINCT p.id, p.full_name, p.patient_id, p.phone, p.gender, p.date_of_birth, p.address, p.blood_group, p.allergies
        FROM patients p
        JOIN visits v ON p.id = v.patient_id
        WHERE v.doctor_id = ?
        ORDER BY p.full_name
    ");
    $stmt->execute([$doctor_id]);
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ================================================================
// GET REGISTERED PATIENT DATA IF SELECTED
// ================================================================
$patient_data = null;
$recent_visit = null;
$vital_signs = null;
$lab_tests = [];
$diagnosis = '';
$prescriptions = [];
$procedures = [];

if ($patient_id > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM patients WHERE id = ?");
        $stmt->execute([$patient_id]);
        $patient_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($patient_data) {
            $stmt = $db->prepare("
                SELECT * FROM visits 
                WHERE patient_id = ? AND doctor_id = ? 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$patient_id, $doctor_id]);
            $recent_visit = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($recent_visit) {
                $diagnosis = $recent_visit['diagnosis'] ?? '';
                
                $stmt = $db->prepare("
                    SELECT * FROM vital_signs 
                    WHERE patient_id = ? AND visit_id = ?
                    ORDER BY recorded_at DESC 
                    LIMIT 1
                ");
                $stmt->execute([$patient_id, $recent_visit['id']]);
                $vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $stmt = $db->prepare("
                    SELECT * FROM lab_tests 
                    WHERE patient_id = ? AND visit_id = ?
                    ORDER BY created_at DESC
                ");
                $stmt->execute([$patient_id, $recent_visit['id']]);
                $lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $stmt = $db->prepare("
                    SELECT * FROM prescriptions 
                    WHERE patient_id = ? AND visit_id = ?
                    ORDER BY created_at DESC
                ");
                $stmt->execute([$patient_id, $recent_visit['id']]);
                $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $stmt = $db->prepare("
                    SELECT * FROM procedures 
                    WHERE patient_id = ? AND visit_id = ?
                    ORDER BY created_at DESC
                ");
                $stmt->execute([$patient_id, $recent_visit['id']]);
                $procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    } catch (Exception $e) {}
}

// ================================================================
// GET BRANCH NAME
// ================================================================
$branch_name = 'Dodoma';
$branch_location = '';
$branch_phone = '';
$branch_email = '';
try {
    $stmt = $db->prepare("SELECT name, location, phone, email FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$doctor_branch_id]);
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch) {
        $branch_name = $branch['name'] ?? 'Dodoma';
        $branch_location = $branch['location'] ?? '';
        $branch_phone = $branch['phone'] ?? '';
        $branch_email = $branch['email'] ?? '';
    }
} catch (Exception $e) {}

// ================================================================
// FUNCTION: GENERATE SICK SHEET HTML CONTENT - VISIT STYLE
// ================================================================
function generateSickSheetHTML($data) {
    $branch_name = $data['branch_name'] ?? 'Braick Dispensary';
    $branch_location = $data['branch_location'] ?? '';
    $branch_phone = $data['branch_phone'] ?? '';
    $branch_email = $data['branch_email'] ?? '';
    
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Sick Sheet - ' . htmlspecialchars($data['patient_name'] ?? 'Patient') . '</title>
        <style>
            /* ================================================================
               PDF STYLES - SAME AS VISIT PDF
               ================================================================ */
            @page {
                margin: 15mm;
                size: A4;
            }
            
            body {
                font-family: Arial, Helvetica, sans-serif;
                font-size: 11px;
                color: #1E293B;
                line-height: 1.5;
                background: white;
                padding: 0;
                margin: 0;
            }
            
            /* Header - Braick Dispensary Letterhead */
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
            
            .header .document-number {
                font-size: 10px;
                color: #64748B;
                font-weight: 600;
                position: absolute;
                right: 0;
                top: 2px;
            }
            
            /* Title */
            .page-title {
                font-size: 18px;
                font-weight: 700;
                color: #0B5ED7;
                text-align: center;
                margin: 6px 0 2px 0;
            }
            
            .page-subtitle {
                font-size: 10px;
                color: #64748B;
                text-align: center;
                margin-bottom: 10px;
            }
            
            /* Section Titles */
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
            
            /* Grids */
            .row-2col { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
            .row-3col { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }
            .row-4col { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 10px; }
            .row-6col { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr 1fr 1fr; gap: 8px; }
            
            /* Info Cards */
            .info-card {
                background: #F8FAFC;
                border-radius: 6px;
                padding: 10px 14px;
                border: 1px solid #E2E8F0;
            }
            
            .info-card .label {
                font-size: 8px;
                font-weight: 600;
                color: #64748B;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                display: block;
            }
            
            .info-card .value {
                font-size: 11px;
                font-weight: 600;
                color: #1E293B;
                display: block;
                margin-top: 2px;
            }
            
            .info-card.blue { border-left: 4px solid #0B5ED7; }
            .info-card.green { border-left: 4px solid #059669; }
            .info-card.purple { border-left: 4px solid #7C3AED; }
            .info-card.orange { border-left: 4px solid #D97706; }
            .info-card.red { border-left: 4px solid #DC2626; }
            .info-card.teal { border-left: 4px solid #0D9488; }
            
            .info-card .value .external-tag {
                font-size: 7px;
                background: #D97706;
                color: white;
                padding: 1px 8px;
                border-radius: 10px;
                margin-left: 6px;
                display: inline-block;
            }
            
            /* Vital Signs */
            .vital-item {
                background: #F8FAFC;
                border-radius: 6px;
                padding: 8px 10px;
                text-align: center;
                border: 1px solid #E2E8F0;
            }
            
            .vital-item .vital-label {
                font-size: 7px;
                font-weight: 600;
                color: #64748B;
                text-transform: uppercase;
                display: block;
            }
            
            .vital-item .vital-value {
                font-size: 13px;
                font-weight: 700;
                display: block;
                margin-top: 2px;
            }
            
            .vital-item .vital-unit {
                font-size: 8px;
                font-weight: 400;
                color: #64748B;
            }
            
            .vital-item.temp .vital-value { color: #DC2626; }
            .vital-item.bp .vital-value { color: #0B5ED7; }
            .vital-item.pulse .vital-value { color: #7C3AED; }
            .vital-item.weight .vital-value { color: #D97706; }
            .vital-item.bmi .vital-value { color: #059669; }
            
            /* Detail Rows */
            .detail-row {
                display: flex;
                padding: 6px 0;
                border-bottom: 1px solid #E2E8F0;
            }
            .detail-row:last-child { border-bottom: none; }
            
            .detail-label {
                font-weight: 600;
                color: #64748B;
                width: 120px;
                flex-shrink: 0;
                font-size: 10px;
            }
            
            .detail-value {
                flex: 1;
                color: #1E293B;
                font-size: 10px;
            }
            
            /* Sick Box */
            .sick-box {
                background: #E8F0FE;
                border-radius: 6px;
                padding: 12px 16px;
                border: 2px solid #0B5ED7;
                margin: 8px 0;
            }
            
            .sick-box .sick-grid {
                display: grid;
                grid-template-columns: 1fr 1fr 1fr;
                gap: 12px;
            }
            
            .sick-box .sick-item .slabel {
                font-size: 8px;
                font-weight: 600;
                color: #64748B;
                text-transform: uppercase;
                display: block;
            }
            
            .sick-box .sick-item .svalue {
                font-size: 13px;
                font-weight: 700;
                color: #0B5ED7;
                display: block;
                margin-top: 2px;
            }
            
            /* Restriction Box */
            .restriction-box {
                background: #FEF3C7;
                border-radius: 6px;
                padding: 8px 14px;
                border: 1px solid #F59E0B;
                margin: 6px 0;
            }
            
            .restriction-box .rlabel {
                font-size: 9px;
                font-weight: 700;
                color: #92400E;
            }
            
            .restriction-box .rvalue {
                font-size: 10px;
                color: #92400E;
            }
            
            /* Footer with Stamp - Match Visit PDF */
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
                font-size: 11px;
                font-weight: 600;
                color: #1E293B;
            }
            
            .footer-left .doctor-details {
                font-size: 9px;
                color: #64748B;
                margin-top: 2px;
            }
            
            .signature-area {
                display: flex;
                gap: 30px;
                margin-top: 10px;
            }
            
            .signature-area .sig-item {
                text-align: center;
            }
            
            .signature-area .sig-line {
                width: 100px;
                border-bottom: 1px solid #1E293B;
                height: 20px;
                margin: 0 auto 2px auto;
            }
            
            .signature-area .sig-label {
                font-size: 8px;
                color: #64748B;
            }
            
            /* Official Stamp - Match Visit PDF */
            .stamp-container {
                display: flex;
                justify-content: flex-end;
                align-items: center;
                min-width: 180px;
            }
            
            .stamp {
                border: 2px solid #0B5ED7;
                border-radius: 6px;
                padding: 8px 18px;
                text-align: center;
                background: #F8FAFC;
                width: 170px;
            }
            
            .stamp .stamp-title {
                font-size: 8px;
                font-weight: 600;
                color: #64748B;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            
            .stamp .stamp-name {
                font-size: 13px;
                font-weight: 700;
                color: #0B5ED7;
                margin-top: 2px;
            }
            
            .stamp .stamp-line {
                border-top: 1px dashed #CBD5E1;
                margin: 4px 0;
            }
            
            .stamp .stamp-doctor {
                font-size: 9px;
                color: #0B5ED7;
                font-weight: 600;
            }
            
            .stamp .stamp-signature {
                font-size: 9px;
                color: #1E293B;
            }
            
            .stamp .stamp-date {
                font-size: 8px;
                color: #94A3B8;
                margin-top: 2px;
            }
            
            /* Footer Note - Match Visit PDF */
            .footer-note {
                text-align: center;
                font-size: 7px;
                color: #94A3B8;
                margin-top: 12px;
                padding-top: 8px;
                border-top: 1px solid #E2E8F0;
            }
            
            .footer-note .brand {
                color: #0B5ED7;
                font-weight: 600;
            }
            
            .footer-note .slogan {
                font-size: 9px;
                color: #0B5ED7;
                font-weight: 600;
                margin-top: 3px;
            }
            
            /* Badge */
            .badge {
                display: inline-block;
                padding: 2px 10px;
                border-radius: 10px;
                font-size: 7px;
                font-weight: 600;
            }
            .badge-success { background: #D1FAE5; color: #059669; }
            .badge-warning { background: #FEF3C7; color: #D97706; }
            .badge-info { background: #E8F0FE; color: #0B5ED7; }
            .badge-purple { background: #EDE9FE; color: #7C3AED; }
            .badge-red { background: #FEE2E2; color: #DC2626; }
            
            /* Print Optimization */
            @media print {
                .no-print { display: none; }
                .page-break { page-break-before: always; }
                body { padding: 0; }
                .info-card { break-inside: avoid; }
                .stamp { break-inside: avoid; }
            }
            
            /* Responsive */
            @media (max-width: 768px) {
                .row-2col { grid-template-columns: 1fr; }
                .row-3col { grid-template-columns: 1fr 1fr; }
                .row-4col { grid-template-columns: 1fr 1fr; }
                .row-6col { grid-template-columns: 1fr 1fr 1fr; }
                .sick-box .sick-grid { grid-template-columns: 1fr; }
                .footer-section { flex-direction: column; }
                .stamp-container { justify-content: flex-start; }
                .detail-row { flex-direction: column; }
                .detail-label { width: 100%; margin-bottom: 2px; }
                .signature-area { flex-direction: column; gap: 10px; }
                .header .document-number { position: static; text-align: right; margin-top: 4px; }
            }
        </style>
    </head>
    <body>
        
        <!-- Header -->
        <div class="header">
            <div class="logo-title">🏥 BRAICK DISPENSARY</div>
            <div class="logo-sub">Quality Healthcare Services</div>
            <div class="branch-info">
                ' . htmlspecialchars($branch_name) . ' | 
                ' . htmlspecialchars($branch_location) . ' | 
                Tel: ' . htmlspecialchars($branch_phone) . '
                ' . (!empty($branch_email) ? '| Email: ' . htmlspecialchars($branch_email) : '') . '
            </div>
            <div class="document-number">Document #: ' . htmlspecialchars($data['document_number'] ?? 'N/A') . '</div>
        </div>
        
        <!-- Title -->
        <div class="page-title">📋 MEDICAL SICK SHEET</div>
        <div class="page-subtitle">Certificate of Sickness</div>
        
        <!-- Patient Information -->
        <div class="section-title">👤 Patient Information</div>
        <div class="row-2col">
            <div>
                <div class="info-card blue">
                    <span class="label">Full Name</span>
                    <span class="value">' . htmlspecialchars($data['patient_name'] ?? 'N/A') . '
                        ' . ($data['patient_type'] === 'external' ? '<span class="external-tag">⚠️ EXTERNAL</span>' : '') . '
                    </span>
                </div>
                <div class="info-card" style="margin-top:6px;">
                    <span class="label">Patient ID</span>
                    <span class="value font-mono">' . htmlspecialchars($data['patient_id'] ?? 'N/A') . '</span>
                </div>
                <div class="info-card" style="margin-top:6px;">
                    <span class="label">Gender</span>
                    <span class="value">' . ucfirst($data['gender'] ?? 'N/A') . '</span>
                </div>
                <div class="info-card" style="margin-top:6px;">
                    <span class="label">Date of Birth</span>
                    <span class="value">' . (!empty($data['date_of_birth']) ? date('d M Y', strtotime($data['date_of_birth'])) : 'N/A') . '</span>
                </div>
            </div>
            <div>
                <div class="info-card green">
                    <span class="label">Phone</span>
                    <span class="value">' . htmlspecialchars($data['phone'] ?? 'N/A') . '</span>
                </div>
                <div class="info-card" style="margin-top:6px;">
                    <span class="label">Blood Group</span>
                    <span class="value">' . htmlspecialchars($data['blood_group'] ?? 'N/A') . '</span>
                </div>
                <div class="info-card" style="margin-top:6px;">
                    <span class="label">Address</span>
                    <span class="value" style="font-weight:400;font-size:10px;">' . htmlspecialchars($data['address'] ?? 'N/A') . '</span>
                </div>
                <div class="info-card" style="margin-top:6px;">
                    <span class="label">Allergies</span>
                    <span class="value" style="font-weight:400;color:#DC2626;">' . htmlspecialchars($data['allergies'] ?? 'None reported') . '</span>
                </div>
            </div>
        </div>
        
        <!-- Doctor Information -->
        <div class="section-title green">👨‍⚕️ Doctor Information</div>
        <div class="row-2col">
            <div class="info-card" style="border-left:4px solid #059669;">
                <span class="label">Doctor Name</span>
                <span class="value">Dr. ' . htmlspecialchars($data['doctor_name'] ?? '') . '</span>
            </div>
            <div class="info-card" style="border-left:4px solid #059669;">
                <span class="label">Specialty</span>
                <span class="value">' . htmlspecialchars($data['doctor_specialty'] ?? 'Medical Doctor') . '</span>
            </div>
        </div>
        
        <!-- Vital Signs -->
        <div class="section-title purple">❤️ Vital Signs</div>
        <div class="row-6col">
            <div class="vital-item temp">
                <span class="vital-label">🌡️ Temperature</span>
                <span class="vital-value">' . ($data['temperature'] ?? '--') . ' <span class="vital-unit">°C</span></span>
            </div>
            <div class="vital-item bp">
                <span class="vital-label">💓 Blood Pressure</span>
                <span class="vital-value">' . ((isset($data['bp_systolic']) && isset($data['bp_diastolic'])) ? $data['bp_systolic'] . '/' . $data['bp_diastolic'] . ' <span class="vital-unit">mmHg</span>' : '--') . '</span>
            </div>
            <div class="vital-item pulse">
                <span class="vital-label">💓 Pulse Rate</span>
                <span class="vital-value">' . ($data['pulse_rate'] ?? '--') . ' <span class="vital-unit">bpm</span></span>
            </div>
            <div class="vital-item weight">
                <span class="vital-label">⚖️ Weight</span>
                <span class="vital-value">' . ($data['weight'] ?? '--') . ' <span class="vital-unit">kg</span></span>
            </div>
            <div class="vital-item" style="border-color:#E2E8F0;">
                <span class="vital-label">📏 Height</span>
                <span class="vital-value" style="color:#64748B;">' . ($data['height'] ?? '--') . ' <span class="vital-unit">cm</span></span>
            </div>
            <div class="vital-item bmi">
                <span class="vital-label">📊 BMI</span>
                <span class="vital-value">' . ($data['bmi'] ?? '--') . ' <span class="vital-unit">kg/m²</span></span>
            </div>
        </div>
        
        <!-- Clinical Details -->
        <div class="section-title purple">🩺 Clinical Details</div>
        
        <div class="detail-row">
            <span class="detail-label">Symptoms</span>
            <span class="detail-value">' . nl2br(htmlspecialchars($data['symptoms'] ?? 'Not specified')) . '</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Diagnosis</span>
            <span class="detail-value"><strong>' . nl2br(htmlspecialchars($data['diagnosis'] ?? 'Not specified')) . '</strong></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Treatment</span>
            <span class="detail-value">' . nl2br(htmlspecialchars($data['treatment'] ?? 'Not specified')) . '</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Instructions</span>
            <span class="detail-value">' . nl2br(htmlspecialchars($data['instructions'] ?? 'Not specified')) . '</span>
        </div>
        
        <!-- Lab Results -->
        ' . (!empty($data['lab_results']) ? '
        <div class="section-title teal">🧪 Lab Results</div>
        <div class="detail-row">
            <span class="detail-label">Results</span>
            <span class="detail-value">' . nl2br(htmlspecialchars($data['lab_results'])) . '</span>
        </div>
        ' : '') . '
        
        <!-- Medications -->
        ' . (!empty($data['medications']) ? '
        <div class="section-title teal">💊 Medications</div>
        <div class="detail-row">
            <span class="detail-label">Medications</span>
            <span class="detail-value">' . nl2br(htmlspecialchars($data['medications'])) . '</span>
        </div>
        ' : '') . '
        
        <!-- Procedures -->
        ' . (!empty($data['procedures']) ? '
        <div class="section-title teal">💉 Procedures</div>
        <div class="detail-row">
            <span class="detail-label">Procedures</span>
            <span class="detail-value">' . nl2br(htmlspecialchars($data['procedures'])) . '</span>
        </div>
        ' : '') . '
        
        <!-- Sick Leave Details -->
        <div class="section-title orange">📅 Sick Leave Details</div>
        
        <div class="sick-box">
            <div class="sick-grid">
                <div class="sick-item">
                    <span class="slabel">Sick Days</span>
                    <span class="svalue">' . ($data['sick_days'] ?? 0) . ' days</span>
                </div>
                <div class="sick-item">
                    <span class="slabel">From</span>
                    <span class="svalue">' . (!empty($data['sick_from']) ? date('d M Y', strtotime($data['sick_from'])) : 'N/A') . '</span>
                </div>
                <div class="sick-item">
                    <span class="slabel">To</span>
                    <span class="svalue">' . (!empty($data['sick_to']) ? date('d M Y', strtotime($data['sick_to'])) : 'N/A') . '</span>
                </div>
            </div>
        </div>
        
        <!-- Reason & Restrictions -->
        <div class="section-title orange">📝 Reason & Recommendations</div>
        
        <div class="detail-row">
            <span class="detail-label">Reason</span>
            <span class="detail-value">' . htmlspecialchars($data['sick_reason'] ?? 'Medical condition requiring rest') . '</span>
        </div>
        
        <div class="restriction-box">
            <span class="rlabel">⚠ Restrictions:</span>
            <span class="rvalue">' . htmlspecialchars($data['sick_restrictions'] ?? 'No heavy lifting, complete rest') . '</span>
        </div>
        
        <!-- Footer with Stamp - Match Visit PDF -->
        <div class="footer-section">
            <div class="footer-left">
                <div class="doctor-name">Dr. ' . htmlspecialchars($data['doctor_name'] ?? '') . '</div>
                <div class="doctor-details">
                    ' . htmlspecialchars($data['doctor_specialty'] ?? 'Medical Doctor') . '<br>
                    Tel: ' . htmlspecialchars($data['doctor_phone'] ?? '') . '
                    ' . (!empty($data['doctor_email']) ? '| Email: ' . htmlspecialchars($data['doctor_email']) : '') . '
                </div>
                
                <div class="signature-area">
                    <div class="sig-item">
                        <div class="sig-line"></div>
                        <span class="sig-label">Doctor\'s Signature</span>
                    </div>
                    <div class="sig-item">
                        <div class="sig-line"></div>
                        <span class="sig-label">Date</span>
                    </div>
                </div>
            </div>
            
            <!-- Official Stamp - Match Visit PDF -->
            <div class="stamp-container">
                <div class="stamp">
                    <div class="stamp-title">Official Stamp</div>
                    <div class="stamp-name">BRAICK DISPENSARY</div>
                    <div class="stamp-line"></div>
                    <div class="stamp-doctor">Dr. ' . htmlspecialchars($data['doctor_name'] ?? '') . '</div>
                    <div class="stamp-signature">_________________________</div>
                    <div class="stamp-date">Date: ' . date('d M Y') . '</div>
                </div>
            </div>
        </div>
        
        <!-- Footer Note - Match Visit PDF -->
        <div class="footer-note">
            <div>
                <span class="brand">🏥 Braick Dispensary</span> 
                <span style="color:#94A3B8;">|</span> 
                ' . htmlspecialchars($data['document_number'] ?? 'N/A') . '
                <span style="color:#94A3B8;">|</span> 
                Generated: ' . date('d M Y, h:i A') . '
            </div>
            <div class="slogan">⭐ Braick Dispensary - Tunajali Afya Yako ⭐</div>
        </div>
        
    </body>
    </html>';
}

// ================================================================
// FUNCTION: GENERATE PDF
// ================================================================
function generatePDF($html) {
    global $pdf_library, $pdf_available;
    
    if (!$pdf_available) {
        return $html;
    }
    
    try {
        if ($pdf_library === 'dompdf') {
            $dompdf = new Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            return $dompdf->output();
        } elseif ($pdf_library === 'mpdf') {
            $mpdf = new Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'P',
                'default_font_size' => 10,
                'default_font' => 'Arial'
            ]);
            $mpdf->WriteHTML($html);
            return $mpdf->Output('', 'S');
        }
    } catch (Exception $e) {
        error_log("PDF Generation Error: " . $e->getMessage());
        return $html;
    }
    
    return $html;
}

// ================================================================
// HANDLE FORM SUBMISSION
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id_post = (int)($_POST['patient_id'] ?? 0);
    $mode_post = $_POST['mode'] ?? 'manual';
    $patient_type = $_POST['patient_type'] ?? 'external';
    
    // Get form data
    $full_name = trim($_POST['full_name'] ?? '');
    $patient_number = trim($_POST['patient_number'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $date_of_birth = $_POST['date_of_birth'] ?? '';
    $address = trim($_POST['address'] ?? '');
    $blood_group = $_POST['blood_group'] ?? '';
    $allergies = trim($_POST['allergies'] ?? '');
    
    $symptoms = trim($_POST['symptoms'] ?? '');
    $diagnosis_input = trim($_POST['diagnosis'] ?? '');
    $treatment = trim($_POST['treatment'] ?? '');
    $instructions = trim($_POST['instructions'] ?? '');
    
    $temperature = $_POST['temperature'] ?? null;
    $bp_systolic = $_POST['bp_systolic'] ?? null;
    $bp_diastolic = $_POST['bp_diastolic'] ?? null;
    $pulse_rate = $_POST['pulse_rate'] ?? null;
    $weight = $_POST['weight'] ?? null;
    $height = $_POST['height'] ?? null;
    
    $lab_results = trim($_POST['lab_results'] ?? '');
    $medications = trim($_POST['medications'] ?? '');
    $procedures_input = trim($_POST['procedures_input'] ?? '');
    
    $sick_days = (int)($_POST['sick_days'] ?? 0);
    $sick_from = $_POST['sick_from'] ?? '';
    $sick_to = $_POST['sick_to'] ?? '';
    $sick_reason = trim($_POST['sick_reason'] ?? '');
    $sick_restrictions = trim($_POST['sick_restrictions'] ?? '');
    
    try {
        // Validate
        if (empty($full_name)) {
            throw new Exception('Patient full name is required');
        }
        if ($sick_days <= 0) {
            throw new Exception('Sick days must be greater than 0');
        }
        if (empty($sick_from) || empty($sick_to)) {
            throw new Exception('Sick sheet dates are required');
        }
        if (empty($diagnosis_input)) {
            throw new Exception('Diagnosis is required');
        }
        
        // ================================================================
        // HANDLE PATIENT TYPE
        // ================================================================
        $patient_id_save = null;
        $is_external = true;
        
        if ($patient_id_post > 0 && $patient_type === 'registered') {
            $stmt = $db->prepare("SELECT id, full_name, patient_id, phone, gender, date_of_birth, address, blood_group, allergies FROM patients WHERE id = ?");
            $stmt->execute([$patient_id_post]);
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($patient) {
                $patient_id_save = $patient['id'];
                $full_name = $patient['full_name'];
                $patient_number = $patient['patient_id'];
                $phone = $patient['phone'] ?? $phone;
                $gender = $patient['gender'] ?? $gender;
                $date_of_birth = $patient['date_of_birth'] ?? $date_of_birth;
                $address = $patient['address'] ?? $address;
                $blood_group = $patient['blood_group'] ?? $blood_group;
                $allergies = $patient['allergies'] ?? $allergies;
                $is_external = false;
            } else {
                throw new Exception('Selected patient not found');
            }
        } else {
            $is_external = true;
            $patient_id_save = null;
            if (empty($patient_number)) {
                $patient_number = 'EXT-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            }
        }
        
        // ================================================================
        // GENERATE DOCUMENT
        // ================================================================
        $document_number = 'SS-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $document_name = 'Sick Sheet - ' . $full_name . ' - ' . date('Y-m-d');
        
        // Build content data
        $content_data = [
            'document_number' => $document_number,
            'patient_name' => $full_name,
            'patient_id' => !empty($patient_number) ? $patient_number : 'N/A',
            'phone' => $phone,
            'gender' => $gender,
            'date_of_birth' => $date_of_birth,
            'address' => $address,
            'blood_group' => $blood_group,
            'allergies' => $allergies,
            'symptoms' => $symptoms,
            'diagnosis' => $diagnosis_input,
            'treatment' => $treatment,
            'instructions' => $instructions,
            'temperature' => $temperature,
            'bp_systolic' => $bp_systolic,
            'bp_diastolic' => $bp_diastolic,
            'pulse_rate' => $pulse_rate,
            'weight' => $weight,
            'height' => $height,
            'bmi' => null,
            'lab_results' => $lab_results,
            'medications' => $medications,
            'procedures' => $procedures_input,
            'sick_days' => $sick_days,
            'sick_from' => $sick_from,
            'sick_to' => $sick_to,
            'sick_reason' => $sick_reason,
            'sick_restrictions' => $sick_restrictions,
            'doctor_name' => $doctor_name,
            'doctor_specialty' => $doctor_specialty,
            'doctor_phone' => '',
            'doctor_email' => '',
            'branch_name' => $branch_name,
            'branch_location' => $branch_location,
            'branch_phone' => $branch_phone,
            'branch_email' => $branch_email,
            'created_at' => date('Y-m-d H:i:s'),
            'patient_type' => $is_external ? 'external' : 'registered'
        ];
        
        // Generate HTML content
        $html_content = generateSickSheetHTML($content_data);
        
        // Generate PDF or keep HTML
        $file_content = generatePDF($html_content);
        $is_pdf = ($file_content !== $html_content);
        
        $file_extension = $is_pdf ? 'pdf' : 'html';
        $file_type = $is_pdf ? 'application/pdf' : 'text/html';
        
        // Save file
        $file_name = 'sick_sheet_' . $document_number . '.' . $file_extension;
        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/dispensary_system/frontend/assets/uploads/sick_sheets/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_path = '/dispensary_system/frontend/assets/uploads/sick_sheets/' . $file_name;
        file_put_contents($upload_dir . $file_name, $file_content);
        
        // ================================================================
        // SAVE TO APPROPRIATE TABLE
        // ================================================================
        if ($is_external) {
            $stmt = $db->prepare("
                INSERT INTO external_sick_sheets (
                    document_number, full_name, patient_id, phone, gender, date_of_birth,
                    address, blood_group, allergies, symptoms, diagnosis, treatment,
                    instructions, temperature, bp_systolic, bp_diastolic, pulse_rate,
                    weight, height, lab_results, medications, procedures,
                    sick_days, sick_from, sick_to, sick_reason, sick_restrictions,
                    doctor_id, branch_id, file_name, file_path, file_type, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, NOW()
                )
            ");
            
            $stmt->execute([
                $document_number,
                $full_name,
                $patient_number,
                $phone,
                $gender,
                !empty($date_of_birth) ? $date_of_birth : null,
                $address,
                $blood_group,
                $allergies,
                $symptoms,
                $diagnosis_input,
                $treatment,
                $instructions,
                $temperature,
                $bp_systolic,
                $bp_diastolic,
                $pulse_rate,
                $weight,
                $height,
                $lab_results,
                $medications,
                $procedures_input,
                $sick_days,
                !empty($sick_from) ? $sick_from : null,
                !empty($sick_to) ? $sick_to : null,
                $sick_reason,
                $sick_restrictions,
                $doctor_id,
                $doctor_branch_id,
                $file_name,
                $file_path,
                $file_type
            ]);
            
            $document_id = $db->lastInsertId();
            
        } else {
            $description = 'Sick Sheet for ' . $full_name . ' - ' . $sick_days . ' days';
            
            $stmt = $db->prepare("
                INSERT INTO patient_documents (
                    document_number, patient_id, doctor_id, branch_id, uploaded_by,
                    document_type, document_name, document_title, description,
                    file_name, file_path, file_size, file_type,
                    sick_sheet_days, sick_sheet_from_date, sick_sheet_to_date,
                    sick_sheet_diagnosis, sick_sheet_recommendations, sick_sheet_restrictions,
                    is_verified, status, upload_date
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, NOW()
                )
            ");
            
            $stmt->execute([
                $document_number,
                $patient_id_save,
                $doctor_id,
                $doctor_branch_id,
                $doctor_id,
                'sick_sheet',
                $document_name,
                'Sick Sheet',
                $description,
                $file_name,
                $file_path,
                strlen($file_content),
                $file_type,
                $sick_days,
                !empty($sick_from) ? $sick_from : null,
                !empty($sick_to) ? $sick_to : null,
                $diagnosis_input,
                $instructions,
                $sick_restrictions,
                1,
                'active'
            ]);
            
            $document_id = $db->lastInsertId();
        }
        
        // Success message with download link
        $download_url = '/dispensary_system/frontend/assets/uploads/sick_sheets/' . $file_name;
        $message = '✅ Sick sheet created successfully! ';
        $message .= '<a href="' . $download_url . '" target="_blank" style="color:#059669;font-weight:600;">📄 View/Download PDF</a>';
        $message_type = 'success';
        
    } catch (Exception $e) {
        $message = '❌ Error: ' . $e->getMessage();
        $message_type = 'error';
    }
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
include_once __DIR__ . '/../../components/doctor_header.php';
include_once __DIR__ . '/../../components/doctor_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Sick Sheet - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           BEAUTIFUL DESIGN - MATCHING BRAICK THEME
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
            --radius: 10px;
            --radius-lg: 16px;
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 32px rgba(0,0,0,0.12);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--gray-50);
            color: var(--gray-800);
            transition: background 0.3s ease, color 0.3s ease;
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
        }
        
        /* ================================================================
           PAGE HEADER - GRADIENT
           ================================================================ */
        .page-header {
            background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%);
            border-radius: var(--radius-lg);
            padding: 28px 36px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 8px 32px rgba(220, 38, 38, 0.25);
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
        }
        
        .page-header .page-title {
            color: white;
            font-size: 1.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-title i { 
            color: rgba(255,255,255,0.85);
            font-size: 1.6rem;
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
            margin-top: 4px;
        }
        
        .page-header .badge-doctor {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .page-header .badge-sick {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .page-header .badge-pdf {
            background: rgba(5, 150, 105, 0.3);
            color: #34D399;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            border: 1px solid rgba(5, 150, 105, 0.2);
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
        }
        
        /* ================================================================
           FORM CARD
           ================================================================ */
        .form-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 32px 36px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            max-width: 1100px;
            margin: 0 auto;
            box-shadow: var(--shadow-md);
        }
        [data-theme="dark"] .form-card {
            background: var(--gray-800);
            border-color: var(--gray-700);
        }
        .form-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-lg);
        }
        
        .form-card .form-header {
            display: flex;
            align-items: center;
            gap: 18px;
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .form-card .form-header .form-icon {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, #DC2626, #B91C1C);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            flex-shrink: 0;
            box-shadow: 0 4px 16px rgba(220, 38, 38, 0.3);
        }
        
        .form-card .form-header .form-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        [data-theme="dark"] .form-card .form-header .form-title {
            color: var(--gray-100);
        }
        
        .form-card .form-header .form-subtitle {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 2px;
        }
        
        .form-label {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 5px;
            display: block;
        }
        [data-theme="dark"] .form-label {
            color: var(--gray-300);
        }
        
        .form-label .required {
            color: var(--danger);
            margin-left: 2px;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 16px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.85rem;
            transition: all 0.3s ease;
            outline: none;
            background: var(--bg-card);
            color: var(--text-primary);
        }
        [data-theme="dark"] .form-control {
            background: var(--gray-700);
            color: var(--gray-100);
            border-color: var(--gray-600);
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.08);
        }
        [data-theme="dark"] .form-control:focus {
            border-color: var(--primary-light);
            box-shadow: 0 0 0 4px rgba(110, 168, 254, 0.08);
        }
        
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .grid-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 16px;
        }
        .form-row {
            margin-bottom: 16px;
        }
        
        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        .btn-danger {
            background: linear-gradient(135deg, #DC2626, #B91C1C);
            color: white;
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
        }
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220, 38, 38, 0.4);
        }
        .btn-primary {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            color: white;
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(11, 94, 215, 0.4);
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
        [data-theme="dark"] .btn-outline:hover {
            background: var(--gray-700);
        }
        
        .form-actions {
            display: flex;
            gap: 12px;
            padding-top: 20px;
            margin-top: 20px;
            border-top: 2px solid var(--border-color);
            flex-wrap: wrap;
        }
        
        /* ================================================================
           ALERT
           ================================================================ */
        .alert {
            padding: 14px 20px;
            border-radius: var(--radius);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.9rem;
            border: 2px solid transparent;
            max-width: 1100px;
            margin-left: auto;
            margin-right: auto;
        }
        .alert-success {
            background: var(--success-bg);
            color: var(--success);
            border-color: var(--success);
        }
        .alert-error {
            background: var(--danger-bg);
            color: var(--danger);
            border-color: var(--danger);
        }
        .alert-info {
            background: var(--primary-bg);
            color: var(--primary);
            border-color: var(--primary);
        }
        .alert a {
            color: var(--success);
            font-weight: 600;
        }
        
        /* ================================================================
           MODE TOGGLES
           ================================================================ */
        .patient-type-toggle {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
            background: var(--gray-100);
            padding: 4px;
            border-radius: var(--radius);
            border: 2px solid var(--border-color);
        }
        [data-theme="dark"] .patient-type-toggle {
            background: var(--gray-700);
            border-color: var(--gray-600);
        }
        
        .patient-type-btn {
            flex: 1;
            padding: 10px 16px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s;
            background: transparent;
            color: var(--text-secondary);
        }
        .patient-type-btn.active {
            background: var(--primary);
            color: white;
            box-shadow: 0 2px 12px rgba(11, 94, 215, 0.3);
        }
        .patient-type-btn.external-active {
            background: #D97706;
            color: white;
            box-shadow: 0 2px 12px rgba(217, 119, 6, 0.3);
        }
        
        .info-box {
            padding: 14px 18px;
            border-radius: var(--radius);
            border-left: 4px solid var(--primary);
            margin-bottom: 12px;
            background: var(--primary-bg);
        }
        [data-theme="dark"] .info-box {
            background: #1E3A5F;
        }
        .info-box.warning {
            background: var(--warning-bg);
            border-left-color: var(--warning);
        }
        [data-theme="dark"] .info-box.warning {
            background: #3D2E0A;
        }
        
        /* ================================================================
           FOOTER
           ================================================================ */
        .footer {
            padding: 14px 0;
            border-top: 2px solid var(--border-color);
            margin-top: 20px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 14px 20px;
            border-radius: var(--radius);
            z-index: 999;
            max-width: 400px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            box-shadow: var(--shadow-lg);
        }
        .toast-custom.show { transform: translateY(0); opacity: 1; }
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
            .form-card { padding: 20px; }
        }
        @media (max-width: 768px) {
            .form-card { padding: 14px; }
            .grid-2 { grid-template-columns: 1fr; }
            .grid-3 { grid-template-columns: 1fr; }
            .page-header .page-title { font-size: 1.3rem; }
            .page-header { padding: 20px; }
            .form-actions { flex-direction: column; }
            .form-actions .btn { width: 100%; justify-content: center; }
            .patient-type-toggle { flex-direction: column; }
            .alert { flex-direction: column; text-align: center; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .form-card { padding: 10px; }
            .page-header .page-title { font-size: 1.1rem; }
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
        <div>
            <h1 class="page-title">
                <i class="fas fa-file-medical"></i>
                Create Sick Sheet
                <span class="badge-doctor">DOCTOR</span>
                <span class="badge-sick">⚠️ EXTERNAL OK</span>
                <?php if ($pdf_available): ?>
                    <span class="badge-pdf">✅ PDF Ready (<?= $pdf_library ?>)</span>
                <?php else: ?>
                    <span class="badge-sick" style="background:rgba(217,119,6,0.3);color:#FBBF24;">⚠️ HTML Mode</span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-user-md"></i>
                Dr. <?= htmlspecialchars($doctor_name) ?>
                <i class="fas fa-stethoscope"></i>
                <?= htmlspecialchars($doctor_specialty) ?>
                <i class="fas fa-store-alt"></i>
                <?= htmlspecialchars($branch_name) ?>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="documents.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Documents
            </a>
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-home"></i> Dashboard
            </a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'error' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- SICK SHEET FORM -->
    <!-- ================================================================ -->
    <div class="form-card">
        <div class="form-header">
            <div class="form-icon">
                <i class="fas fa-file-medical"></i>
            </div>
            <div>
                <h3 class="form-title">
                    <i class="fas fa-file-medical" style="color:#DC2626;"></i>
                    Sick Sheet Form
                    <span style="background:#DC2626;color:white;padding:2px 12px;border-radius:16px;font-size:0.6rem;margin-left:8px;">OFFICIAL</span>
                </h3>
                <p class="form-subtitle">
                    <i class="fas fa-info-circle"></i>
                    Select a registered patient OR create for external patient
                </p>
            </div>
        </div>

        <!-- Patient Type Toggle -->
        <div class="patient-type-toggle">
            <button type="button" class="patient-type-btn active" id="btnRegistered" onclick="setPatientType('registered')">
                <i class="fas fa-user-check"></i> Registered Patient
            </button>
            <button type="button" class="patient-type-btn" id="btnExternal" onclick="setPatientType('external')">
                <i class="fas fa-user-plus"></i> External Patient (No Registration)
            </button>
        </div>

        <form method="POST" action="" id="sickSheetForm">
            <input type="hidden" name="patient_type" id="patientTypeHidden" value="registered">
            <input type="hidden" name="mode" id="formMode" value="manual">
            <input type="hidden" name="patient_id" id="patientIdHidden" value="<?= $patient_id ?>">

            <!-- SELECT REGISTERED PATIENT -->
            <div id="registeredSection">
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-user"></i> Select Registered Patient
                    </label>
                    <select name="select_patient_id" class="form-control" id="patientSelect" onchange="loadPatientData(this.value)">
                        <option value="">-- Select Patient --</option>
                        <?php foreach ($patients as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= $p['id'] == $patient_id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['full_name']) ?> (<?= htmlspecialchars($p['patient_id']) ?>)
                                <?php if ($p['phone']): ?> • <?= htmlspecialchars($p['phone']) ?><?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <strong>Registered Patient:</strong> Patient is already registered. Data saved to patient documents.
                </div>
            </div>

            <!-- EXTERNAL PATIENT -->
            <div id="externalSection" style="display:none;">
                <div class="info-box warning">
                    <i class="fas fa-exclamation-triangle" style="color:#D97706;"></i>
                    <strong>External Patient:</strong> Patient is NOT registered. Data saved to external sick sheets table.
                    <span style="display:block;font-size:0.75rem;color:var(--text-secondary);margin-top:4px;">
                        Patient will NOT appear in the main patients list
                    </span>
                </div>
            </div>

            <!-- PATIENT DETAILS -->
            <div class="grid-2" id="patientDetailsSection" style="margin-top:16px;">
                <div>
                    <div class="form-row">
                        <label class="form-label">Full Name <span class="required">*</span></label>
                        <input type="text" name="full_name" class="form-control" id="fullName" 
                               value="<?= $patient_data ? htmlspecialchars($patient_data['full_name']) : '' ?>" 
                               placeholder="Enter patient name" required>
                    </div>
                    <div class="form-row">
                        <label class="form-label">Patient ID <span class="text-xs text-gray-400">(Optional)</span></label>
                        <input type="text" name="patient_number" class="form-control" id="patientNumber" 
                               value="<?= $patient_data ? htmlspecialchars($patient_data['patient_id']) : '' ?>" 
                               placeholder="Leave empty for auto-generation">
                    </div>
                    <div class="form-row">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" id="phone" 
                               value="<?= $patient_data ? htmlspecialchars($patient_data['phone']) : '' ?>" 
                               placeholder="Enter phone number">
                    </div>
                    <div class="form-row">
                        <label class="form-label">Gender</label>
                        <select name="gender" class="form-control" id="gender">
                            <option value="">-- Select --</option>
                            <option value="Male" <?= ($patient_data && $patient_data['gender'] === 'Male') ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= ($patient_data && $patient_data['gender'] === 'Female') ? 'selected' : '' ?>>Female</option>
                            <option value="Other" <?= ($patient_data && $patient_data['gender'] === 'Other') ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>
                </div>
                <div>
                    <div class="form-row">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" name="date_of_birth" class="form-control" id="dob" 
                               value="<?= $patient_data ? $patient_data['date_of_birth'] : '' ?>">
                    </div>
                    <div class="form-row">
                        <label class="form-label">Address</label>
                        <input type="text" name="address" class="form-control" id="address" 
                               value="<?= $patient_data ? htmlspecialchars($patient_data['address']) : '' ?>" 
                               placeholder="Enter address">
                    </div>
                    <div class="form-row">
                        <label class="form-label">Blood Group</label>
                        <select name="blood_group" class="form-control" id="bloodGroup">
                            <option value="">-- Select --</option>
                            <option value="A+" <?= ($patient_data && $patient_data['blood_group'] === 'A+') ? 'selected' : '' ?>>A+</option>
                            <option value="A-" <?= ($patient_data && $patient_data['blood_group'] === 'A-') ? 'selected' : '' ?>>A-</option>
                            <option value="B+" <?= ($patient_data && $patient_data['blood_group'] === 'B+') ? 'selected' : '' ?>>B+</option>
                            <option value="B-" <?= ($patient_data && $patient_data['blood_group'] === 'B-') ? 'selected' : '' ?>>B-</option>
                            <option value="AB+" <?= ($patient_data && $patient_data['blood_group'] === 'AB+') ? 'selected' : '' ?>>AB+</option>
                            <option value="AB-" <?= ($patient_data && $patient_data['blood_group'] === 'AB-') ? 'selected' : '' ?>>AB-</option>
                            <option value="O+" <?= ($patient_data && $patient_data['blood_group'] === 'O+') ? 'selected' : '' ?>>O+</option>
                            <option value="O-" <?= ($patient_data && $patient_data['blood_group'] === 'O-') ? 'selected' : '' ?>>O-</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <label class="form-label">Allergies</label>
                        <input type="text" name="allergies" class="form-control" id="allergies" 
                               value="<?= $patient_data ? htmlspecialchars($patient_data['allergies']) : '' ?>" 
                               placeholder="Enter allergies (e.g. Penicillin)">
                    </div>
                </div>
            </div>

            <!-- CLINICAL INFORMATION -->
            <div style="margin-top:16px;padding-top:16px;border-top:2px solid var(--border-color);">
                <h4 style="font-size:0.95rem;font-weight:600;margin-bottom:12px;color:var(--primary);">
                    <i class="fas fa-stethoscope"></i> Clinical Information
                </h4>
                <div class="grid-2">
                    <div class="form-row">
                        <label class="form-label">Symptoms</label>
                        <textarea name="symptoms" class="form-control" id="symptoms" rows="2" placeholder="Enter symptoms..."><?= $recent_visit ? htmlspecialchars($recent_visit['symptoms'] ?? '') : '' ?></textarea>
                    </div>
                    <div class="form-row">
                        <label class="form-label">Diagnosis <span class="required">*</span></label>
                        <textarea name="diagnosis" class="form-control" id="diagnosis" rows="2" placeholder="Enter diagnosis..." required><?= htmlspecialchars($diagnosis) ?></textarea>
                    </div>
                    <div class="form-row">
                        <label class="form-label">Treatment</label>
                        <textarea name="treatment" class="form-control" id="treatment" rows="2" placeholder="Enter treatment..."><?= $recent_visit ? htmlspecialchars($recent_visit['treatment'] ?? '') : '' ?></textarea>
                    </div>
                    <div class="form-row">
                        <label class="form-label">Instructions</label>
                        <textarea name="instructions" class="form-control" id="instructions" rows="2" placeholder="Enter instructions..."></textarea>
                    </div>
                </div>
            </div>

            <!-- VITAL SIGNS -->
            <div style="margin-top:16px;padding-top:16px;border-top:2px solid var(--border-color);">
                <h4 style="font-size:0.95rem;font-weight:600;margin-bottom:12px;color:#DC2626;">
                    <i class="fas fa-heartbeat"></i> Vital Signs
                </h4>
                <div class="grid-3">
                    <div class="form-row">
                        <label class="form-label">🌡️ Temperature</label>
                        <input type="number" name="temperature" class="form-control" step="0.1" 
                               value="<?= $vital_signs ? $vital_signs['temperature'] : '' ?>" placeholder="36.5">
                    </div>
                    <div class="form-row">
                        <label class="form-label">❤️ Blood Pressure</label>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="number" name="bp_systolic" class="form-control" style="flex:1;" 
                                   value="<?= $vital_signs ? $vital_signs['blood_pressure_systolic'] : '' ?>" placeholder="120">
                            <span style="font-weight:700;">/</span>
                            <input type="number" name="bp_diastolic" class="form-control" style="flex:1;" 
                                   value="<?= $vital_signs ? $vital_signs['blood_pressure_diastolic'] : '' ?>" placeholder="80">
                        </div>
                    </div>
                    <div class="form-row">
                        <label class="form-label">💓 Pulse Rate</label>
                        <input type="number" name="pulse_rate" class="form-control" 
                               value="<?= $vital_signs ? $vital_signs['pulse_rate'] : '' ?>" placeholder="72">
                    </div>
                    <div class="form-row">
                        <label class="form-label">⚖️ Weight</label>
                        <input type="number" name="weight" class="form-control" step="0.1" 
                               value="<?= $vital_signs ? $vital_signs['weight'] : '' ?>" placeholder="65">
                    </div>
                    <div class="form-row">
                        <label class="form-label">📏 Height</label>
                        <input type="number" name="height" class="form-control" step="0.1" 
                               value="<?= $vital_signs ? $vital_signs['height'] : '' ?>" placeholder="170">
                    </div>
                </div>
            </div>

            <!-- LAB & MEDICATIONS -->
            <div style="margin-top:16px;padding-top:16px;border-top:2px solid var(--border-color);">
                <h4 style="font-size:0.95rem;font-weight:600;margin-bottom:12px;color:#7C3AED;">
                    <i class="fas fa-flask"></i> Lab Results & Medications
                </h4>
                <div class="grid-2">
                    <div class="form-row">
                        <label class="form-label">Lab Results</label>
                        <textarea name="lab_results" class="form-control" id="labResults" rows="3" placeholder="Enter lab results..."><?php 
                            if (!empty($lab_tests)) {
                                $results = [];
                                foreach ($lab_tests as $lt) {
                                    $results[] = $lt['test_name'] . ': ' . ($lt['results'] ?? 'Pending');
                                }
                                echo htmlspecialchars(implode("\n", $results));
                            }
                        ?></textarea>
                    </div>
                    <div class="form-row">
                        <label class="form-label">Medications</label>
                        <textarea name="medications" class="form-control" id="medications" rows="3" placeholder="Enter medications..."><?php 
                            if (!empty($prescriptions)) {
                                $meds = [];
                                foreach ($prescriptions as $pres) {
                                    $stmt = $db->prepare("SELECT * FROM prescription_items WHERE prescription_id = ?");
                                    $stmt->execute([$pres['id']]);
                                    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    foreach ($items as $item) {
                                        $meds[] = $item['medication_name'] . ' ' . ($item['dosage'] ?? '') . ' - ' . ($item['frequency'] ?? '');
                                    }
                                }
                                echo htmlspecialchars(implode("\n", array_unique($meds)));
                            }
                        ?></textarea>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label">Procedures</label>
                    <textarea name="procedures_input" class="form-control" id="proceduresInput" rows="2" placeholder="Enter procedures..."><?php 
                        if (!empty($procedures)) {
                            $procs = [];
                            foreach ($procedures as $proc) {
                                $procs[] = $proc['procedure_name'] . ' (' . ($proc['status'] ?? 'Pending') . ')';
                            }
                            echo htmlspecialchars(implode("\n", $procs));
                        }
                    ?></textarea>
                </div>
            </div>

            <!-- SICK SHEET DETAILS -->
            <div style="margin-top:16px;padding-top:16px;border-top:2px solid var(--border-color);">
                <h4 style="font-size:0.95rem;font-weight:600;margin-bottom:12px;color:#D97706;">
                    <i class="fas fa-calendar-alt"></i> Sick Sheet Details
                    <span style="background:#DC2626;color:white;padding:2px 10px;border-radius:12px;font-size:0.55rem;margin-left:8px;">REQUIRED</span>
                </h4>
                <div class="grid-3">
                    <div class="form-row">
                        <label class="form-label">Days <span class="required">*</span></label>
                        <input type="number" name="sick_days" class="form-control" id="sickDays" value="3" min="1" required>
                    </div>
                    <div class="form-row">
                        <label class="form-label">From Date <span class="required">*</span></label>
                        <input type="date" name="sick_from" class="form-control" id="sickFrom" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-row">
                        <label class="form-label">To Date <span class="required">*</span></label>
                        <input type="date" name="sick_to" class="form-control" id="sickTo" value="<?= date('Y-m-d', strtotime('+3 days')) ?>" required>
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-row">
                        <label class="form-label">Reason</label>
                        <input type="text" name="sick_reason" class="form-control" value="Medical condition requiring rest" placeholder="Enter reason">
                    </div>
                    <div class="form-row">
                        <label class="form-label">Restrictions</label>
                        <input type="text" name="sick_restrictions" class="form-control" value="No heavy lifting, complete rest" placeholder="Enter restrictions">
                    </div>
                </div>
            </div>

            <!-- FORM ACTIONS -->
            <div class="form-actions">
                <button type="submit" class="btn btn-danger" id="submitBtn">
                    <i class="fas fa-file-medical"></i> Create Sick Sheet
                </button>
                <button type="reset" class="btn btn-outline">
                    <i class="fas fa-undo"></i> Reset
                </button>
                <a href="documents.php" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>

            <div class="mt-4 pt-3 text-xs text-center border-t border-gray-200 dark:border-gray-700" style="color:var(--text-secondary);">
                <i class="fas fa-info-circle mr-1"></i>
                <strong>External Patients:</strong> Saved to separate table - NOT in main patients list.
                <span class="mx-2">|</span>
                <?php if ($pdf_available): ?>
                    <span style="color:#34D399;font-weight:600;">✅ PDF Ready (<?= $pdf_library ?>)</span>
                <?php else: ?>
                    <span style="color:#FBBF24;font-weight:600;">⚠️ HTML Mode</span>
                <?php endif; ?>
                <span class="mx-2">|</span>
                <span style="color:#DC2626;font-weight:600;">⭐ Official Stamp applied</span>
                <span class="mx-2">|</span>
                <span style="color:#0B5ED7;font-weight:600;">🏥 Braick Dispensary - Tunajali Afya Yako</span>
            </div>
        </form>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">🏥 Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Create Sick Sheet
            <span class="text-gray-300 mx-2">|</span>
            Dr. <?= htmlspecialchars($doctor_name) ?>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
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
    // PATIENT TYPE TOGGLE
    // ================================================================
    function setPatientType(type) {
        document.getElementById('patientTypeHidden').value = type;
        var registeredSection = document.getElementById('registeredSection');
        var externalSection = document.getElementById('externalSection');
        var btnRegistered = document.getElementById('btnRegistered');
        var btnExternal = document.getElementById('btnExternal');
        var patientSelect = document.getElementById('patientSelect');
        
        if (type === 'registered') {
            registeredSection.style.display = 'block';
            externalSection.style.display = 'none';
            btnRegistered.className = 'patient-type-btn active';
            btnExternal.className = 'patient-type-btn';
            patientSelect.disabled = false;
        } else {
            registeredSection.style.display = 'none';
            externalSection.style.display = 'block';
            btnRegistered.className = 'patient-type-btn';
            btnExternal.className = 'patient-type-btn external-active';
            patientSelect.disabled = true;
            patientSelect.value = '';
            document.getElementById('patientIdHidden').value = '';
        }
    }

    // ================================================================
    // LOAD PATIENT DATA
    // ================================================================
    function loadPatientData(patientId) {
        if (!patientId) return;
        document.getElementById('patientIdHidden').value = patientId;
        setPatientType('registered');
        window.location.href = 'sick_sheet.php?patient_id=' + patientId + '&mode=select';
    }

    // ================================================================
    // AUTO-CALCULATE SICK DAYS
    // ================================================================
    document.getElementById('sickFrom')?.addEventListener('change', function() {
        var from = new Date(this.value);
        var to = new Date(document.getElementById('sickTo').value);
        if (from && to && to >= from) {
            var days = Math.ceil((to - from) / (1000 * 60 * 60 * 24)) + 1;
            document.getElementById('sickDays').value = days;
        }
    });
    
    document.getElementById('sickTo')?.addEventListener('change', function() {
        var to = new Date(this.value);
        var from = new Date(document.getElementById('sickFrom').value);
        if (from && to && to >= from) {
            var days = Math.ceil((to - from) / (1000 * 60 * 60 * 24)) + 1;
            document.getElementById('sickDays').value = days;
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
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 3500);
    }

    // ================================================================
    // INITIALIZE
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        var patientId = <?= $patient_id ?>;
        if (patientId > 0) {
            setPatientType('registered');
        } else {
            setPatientType('external');
        }
        
        <?php if ($message && $message_type === 'success'): ?>
            showToast('✅ Success', 'Sick sheet created successfully!', 'success');
        <?php elseif ($message && $message_type === 'error'): ?>
            showToast('❌ Error', '<?= addslashes(strip_tags($message)) ?>', 'error');
        <?php endif; ?>
    });

    console.log('%c📄 Sick Sheet - <?= htmlspecialchars($doctor_name) ?>', 'font-size:16px; font-weight:bold; color:#DC2626;');
    console.log('%c✅ Design matches Visit PDF style', 'font-size:12px; color:#34D399;');
    console.log('%c✅ Official Stamp included', 'font-size:12px; color:#0B5ED7;');
    console.log('%c⭐ Braick Dispensary - Tunajali Afya Yako', 'font-size:12px; color:#DC2626;');
</script>

</body>
</html>