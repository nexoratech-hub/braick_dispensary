<?php
// ================================================================
// FILE: frontend/pages/doctor/generate_sick_sheet_pdf.php
// GENERATE SICK SHEET PDF - WITH BRAICK LOGO (MULTIPLE PATHS)
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
if ($_SESSION['role'] !== 'doctor' && $_SESSION['role'] !== 'admin') {
    header('Location: ../dashboard.php');
    exit;
}

// ================================================================
// GET USER DATA
// ================================================================
$user_id = $_SESSION['user_id'] ?? 0;
$full_name = $_SESSION['full_name'] ?? 'Doctor';
$role = $_SESSION['role'] ?? 'doctor';
$branch_id = $_SESSION['branch_id'] ?? 1;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';

// ================================================================
// GET PARAMETERS
// ================================================================
$sick_sheet_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$visit_id = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$source = isset($_GET['source']) ? $_GET['source'] : 'external';

if ($sick_sheet_id == 0 && $visit_id == 0 && $patient_id == 0) {
    die("Invalid request. Please provide a sick sheet ID, visit ID, or patient ID.");
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
// FIND LOGO - MULTIPLE PATHS
// ================================================================
$logo_url = '';
$logo_paths = [
    // Absolute paths with document root
    $_SERVER['DOCUMENT_ROOT'] . '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png',
    $_SERVER['DOCUMENT_ROOT'] . '/dispensary_system/assets/uploads/profiles/braick_logo.png',
    $_SERVER['DOCUMENT_ROOT'] . '/frontend/assets/uploads/profiles/braick_logo.png',
    $_SERVER['DOCUMENT_ROOT'] . '/assets/uploads/profiles/braick_logo.png',
    
    // Relative paths
    __DIR__ . '/../../assets/uploads/profiles/braick_logo.png',
    __DIR__ . '/../../../assets/uploads/profiles/braick_logo.png',
    __DIR__ . '/../../../frontend/assets/uploads/profiles/braick_logo.png',
    
    // XAMPP paths
    'C:/xampp/htdocs/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png',
    'C:/xampp/htdocs/dispensary_system/assets/uploads/profiles/braick_logo.png',
    
    // WAMP paths
    'C:/wamp64/www/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png',
    'C:/wamp64/www/dispensary_system/assets/uploads/profiles/braick_logo.png',
];

$logo_found = false;
foreach ($logo_paths as $path) {
    if (file_exists($path)) {
        $logo_found = true;
        // Convert to URL path
        $logo_url = str_replace($_SERVER['DOCUMENT_ROOT'], '', $path);
        $logo_url = str_replace('\\', '/', $logo_url);
        
        // If still not a valid URL, try to construct
        if (strpos($logo_url, '/') !== 0) {
            $logo_url = '/' . $logo_url;
        }
        break;
    }
}

// Fallback if logo not found - use inline SVG/Base64
if (!$logo_found) {
    // Use a simple SVG as fallback
    $logo_url = 'data:image/svg+xml;base64,' . base64_encode('
        <svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100">
            <rect width="100" height="100" rx="15" fill="#0B5ED7"/>
            <text x="50" y="65" text-anchor="middle" fill="white" font-size="40" font-weight="bold" font-family="Arial">B</text>
            <text x="50" y="85" text-anchor="middle" fill="rgba(255,255,255,0.7)" font-size="10" font-family="Arial">BRAICK</text>
        </svg>
    ');
}

// ================================================================
// GET DATA - SEARCH BOTH TABLES
// ================================================================
$sick_sheet = null;
$is_external = true;
$data_source = 'none';

// ================================================================
// SEARCH 1: external_sick_sheets (External Patients)
// ================================================================
if ($sick_sheet_id > 0) {
    $stmt = $db->prepare("
        SELECT 
            ess.*,
            u.full_name as doctor_name,
            u.email as doctor_email,
            u.phone as doctor_phone,
            u.specialty as doctor_specialty,
            b.name as branch_name,
            b.location as branch_location,
            b.phone as branch_phone,
            b.email as branch_email,
            'external' as source_type
        FROM external_sick_sheets ess
        LEFT JOIN users u ON ess.doctor_id = u.id
        LEFT JOIN branches b ON ess.branch_id = b.id
        WHERE ess.id = ?
    ");
    $stmt->execute([$sick_sheet_id]);
    $sick_sheet = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($sick_sheet) {
        $is_external = true;
        $data_source = 'external_sick_sheets';
    }
}

// ================================================================
// SEARCH 2: patient_documents (Registered Patients)
// ================================================================
if (!$sick_sheet && $sick_sheet_id > 0) {
    $stmt = $db->prepare("
        SELECT 
            pd.*,
            p.full_name as patient_full_name,
            p.patient_id as patient_code,
            p.phone as patient_phone,
            p.gender,
            p.date_of_birth,
            p.address,
            p.blood_group,
            p.allergies,
            u.full_name as doctor_name,
            u.email as doctor_email,
            u.phone as doctor_phone,
            u.specialty as doctor_specialty,
            b.name as branch_name,
            b.location as branch_location,
            b.phone as branch_phone,
            b.email as branch_email,
            'registered' as source_type
        FROM patient_documents pd
        LEFT JOIN patients p ON pd.patient_id = p.id
        LEFT JOIN users u ON pd.doctor_id = u.id
        LEFT JOIN branches b ON pd.branch_id = b.id
        WHERE pd.id = ? AND pd.document_type = 'sick_sheet' AND pd.status = 'active'
    ");
    $stmt->execute([$sick_sheet_id]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($doc) {
        $sick_sheet = [
            'id' => $doc['id'],
            'document_number' => $doc['document_number'] ?? 'SS-' . date('Ymd') . '-' . str_pad($doc['id'], 4, '0', STR_PAD_LEFT),
            'full_name' => $doc['patient_full_name'] ?? 'N/A',
            'patient_id' => $doc['patient_code'] ?? 'N/A',
            'phone' => $doc['patient_phone'] ?? '',
            'gender' => $doc['gender'] ?? '',
            'date_of_birth' => $doc['date_of_birth'] ?? '',
            'address' => $doc['address'] ?? '',
            'blood_group' => $doc['blood_group'] ?? '',
            'allergies' => $doc['allergies'] ?? '',
            'symptoms' => '',
            'diagnosis' => $doc['sick_sheet_diagnosis'] ?? '',
            'treatment' => '',
            'instructions' => $doc['sick_sheet_recommendations'] ?? '',
            'temperature' => null,
            'bp_systolic' => null,
            'bp_diastolic' => null,
            'pulse_rate' => null,
            'weight' => null,
            'height' => null,
            'bmi' => null,
            'lab_results' => '',
            'medications' => '',
            'procedures' => '',
            'sick_days' => $doc['sick_sheet_days'] ?? 3,
            'sick_from' => $doc['sick_sheet_from_date'] ?? date('Y-m-d'),
            'sick_to' => $doc['sick_sheet_to_date'] ?? date('Y-m-d', strtotime('+3 days')),
            'sick_reason' => $doc['sick_sheet_diagnosis'] ?? 'Medical condition requiring rest',
            'sick_restrictions' => $doc['sick_sheet_restrictions'] ?? 'No heavy lifting, complete rest',
            'doctor_id' => $doc['doctor_id'] ?? $user_id,
            'doctor_name' => $doc['doctor_name'] ?? $full_name,
            'doctor_email' => $doc['doctor_email'] ?? '',
            'doctor_phone' => $doc['doctor_phone'] ?? '',
            'doctor_specialty' => $doc['doctor_specialty'] ?? '',
            'branch_name' => $doc['branch_name'] ?? $branch_name,
            'branch_location' => $doc['branch_location'] ?? '',
            'branch_phone' => $doc['branch_phone'] ?? '',
            'branch_email' => $doc['branch_email'] ?? '',
            'created_at' => $doc['upload_date'] ?? date('Y-m-d H:i:s'),
            'patient_type' => 'registered',
            'source_type' => 'patient_documents'
        ];
        $is_external = false;
        $data_source = 'patient_documents';
    }
}

// ================================================================
// SEARCH 3: From visit data (Fallback)
// ================================================================
if (!$sick_sheet && $visit_id > 0) {
    $stmt = $db->prepare("
        SELECT 
            v.*,
            p.id as patient_id,
            p.full_name as patient_name,
            p.patient_id as patient_number,
            p.date_of_birth,
            p.gender,
            p.phone as patient_phone,
            p.email as patient_email,
            p.address,
            p.blood_group,
            p.allergies,
            u.full_name as doctor_name,
            u.email as doctor_email,
            u.phone as doctor_phone,
            u.specialty as doctor_specialty,
            b.name as branch_name,
            b.location as branch_location,
            b.phone as branch_phone,
            b.email as branch_email
        FROM visits v
        LEFT JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON v.doctor_id = u.id
        LEFT JOIN branches b ON v.branch_id = b.id
        WHERE v.id = ?
    ");
    $stmt->execute([$visit_id]);
    $visit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($visit) {
        $sick_sheet = [
            'id' => $visit['id'],
            'document_number' => 'SS-' . date('Ymd') . '-' . str_pad($visit['id'], 4, '0', STR_PAD_LEFT),
            'full_name' => $visit['patient_name'] ?? 'N/A',
            'patient_id' => $visit['patient_number'] ?? 'N/A',
            'phone' => $visit['patient_phone'] ?? '',
            'gender' => $visit['gender'] ?? '',
            'date_of_birth' => $visit['date_of_birth'] ?? '',
            'address' => $visit['address'] ?? '',
            'blood_group' => $visit['blood_group'] ?? '',
            'allergies' => $visit['allergies'] ?? '',
            'symptoms' => $visit['symptoms'] ?? '',
            'diagnosis' => $visit['diagnosis'] ?? '',
            'treatment' => $visit['treatment'] ?? '',
            'instructions' => $visit['notes'] ?? '',
            'temperature' => null,
            'bp_systolic' => null,
            'bp_diastolic' => null,
            'pulse_rate' => null,
            'weight' => null,
            'height' => null,
            'bmi' => null,
            'lab_results' => '',
            'medications' => '',
            'procedures' => '',
            'sick_days' => 3,
            'sick_from' => date('Y-m-d'),
            'sick_to' => date('Y-m-d', strtotime('+3 days')),
            'sick_reason' => $visit['diagnosis'] ?? 'Medical condition requiring rest',
            'sick_restrictions' => 'No heavy lifting, complete rest',
            'doctor_id' => $visit['doctor_id'] ?? $user_id,
            'doctor_name' => $visit['doctor_name'] ?? $full_name,
            'doctor_email' => $visit['doctor_email'] ?? '',
            'doctor_phone' => $visit['doctor_phone'] ?? '',
            'doctor_specialty' => $visit['doctor_specialty'] ?? '',
            'branch_name' => $visit['branch_name'] ?? $branch_name,
            'branch_location' => $visit['branch_location'] ?? '',
            'branch_phone' => $visit['branch_phone'] ?? '',
            'branch_email' => $visit['branch_email'] ?? '',
            'created_at' => date('Y-m-d H:i:s'),
            'patient_type' => 'registered',
            'source_type' => 'visit'
        ];
        $is_external = false;
        $data_source = 'visit';
    }
}

// If no data found, show error
if (!$sick_sheet) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Sick Sheet Not Found</title>
        <style>
            body { font-family: 'Segoe UI', Arial, sans-serif; background: #F8FAFC; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; padding: 20px; }
            .error-container { background: white; border-radius: 16px; padding: 40px 50px; max-width: 500px; width: 100%; box-shadow: 0 8px 32px rgba(0,0,0,0.1); text-align: center; border-top: 4px solid #DC2626; }
            .error-icon { font-size: 48px; color: #DC2626; margin-bottom: 16px; }
            .error-title { font-size: 24px; font-weight: 700; color: #1E293B; margin-bottom: 8px; }
            .error-message { color: #64748B; font-size: 16px; margin-bottom: 20px; line-height: 1.6; }
            .btn { display: inline-block; padding: 10px 24px; background: #0B5ED7; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; transition: all 0.3s ease; }
            .btn:hover { background: #0A4CA8; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(11,94,215,0.3); }
            .btn-secondary { background: #E2E8F0; color: #475569; margin-left: 10px; }
            .btn-secondary:hover { background: #CBD5E1; box-shadow: none; }
            .text-sm { font-size: 12px; color: #94A3B8; margin-top: 12px; }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-icon">📄</div>
            <div class="error-title">Sick Sheet Not Found</div>
            <div class="error-message">
                The sick sheet you are looking for could not be found.
                Please check the ID or try again.
            </div>
            <div>
                <a href="javascript:history.back()" class="btn">← Go Back</a>
                <a href="documents.php" class="btn btn-secondary">Documents</a>
            </div>
            <div class="text-sm">If you believe this is an error, please contact system administrator.</div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ================================================================
// GENERATE HTML - A4 SIZE WITH LOGO
// ================================================================

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Sick Sheet - <?= htmlspecialchars($sick_sheet['full_name'] ?? 'Patient') ?></title>
    <style>
        /* ================================================================
           GLOBAL STYLES
           ================================================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #E2E8F0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            min-height: 100vh;
            padding: 30px 20px;
        }
        
        /* ================================================================
           BUTTONS BAR - TOP
           ================================================================ */
        .controls-bar {
            width: 100%;
            max-width: 800px;
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            background: white;
            padding: 14px 24px;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            border: 1px solid #E2E8F0;
        }
        
        .controls-bar .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
            min-width: 120px;
            justify-content: center;
        }
        
        .controls-bar .btn-primary {
            background: #0B5ED7;
            color: white;
        }
        .controls-bar .btn-primary:hover {
            background: #0A4CA8;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11,94,215,0.3);
        }
        
        .controls-bar .btn-success {
            background: #059669;
            color: white;
        }
        .controls-bar .btn-success:hover {
            background: #047857;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5,150,105,0.3);
        }
        
        .controls-bar .btn-danger {
            background: #DC2626;
            color: white;
        }
        .controls-bar .btn-danger:hover {
            background: #B91C1C;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220,38,38,0.3);
        }
        
        .controls-bar .btn-outline {
            background: transparent;
            color: #475569;
            border: 2px solid #E2E8F0;
        }
        .controls-bar .btn-outline:hover {
            background: #F1F5F9;
            border-color: #0B5ED7;
            color: #0B5ED7;
        }
        
        .controls-bar .btn i {
            font-size: 1rem;
        }
        
        /* ================================================================
           A4 PAPER CONTAINER
           ================================================================ */
        .a4-container {
            width: 210mm;
            min-height: 297mm;
            background: white;
            box-shadow: 0 4px 30px rgba(0,0,0,0.15);
            border-radius: 4px;
            overflow: hidden;
            padding: 40px 45px;
            position: relative;
            margin: 0 auto;
        }
        
        /* ================================================================
           PDF CONTENT - WITH LOGO LIKE VISIT
           ================================================================ */
        .pdf-content {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
            color: #1E293B;
            line-height: 1.5;
        }
        
        /* Header with Logo */
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 3px solid #0B5ED7;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        
        .header-logo {
            width: 60px;
            height: 60px;
            flex-shrink: 0;
            border-radius: 10px;
            overflow: hidden;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #E2E8F0;
        }
        
        .header-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        .header-text .logo-title {
            font-size: 20px;
            font-weight: 700;
            color: #0B5ED7;
            letter-spacing: 1px;
        }
        
        .header-text .logo-sub {
            font-size: 10px;
            color: #64748B;
            margin-top: 1px;
        }
        
        .header-text .branch-info {
            font-size: 8px;
            color: #64748B;
            margin-top: 1px;
        }
        
        .header-right {
            text-align: right;
        }
        
        .header-right .document-number {
            font-size: 9px;
            color: #64748B;
            font-weight: 600;
        }
        
        .header-right .source-badge {
            font-size: 7px;
            background: <?= $is_external ? '#D97706' : '#059669' ?>;
            color: white;
            padding: 2px 10px;
            border-radius: 10px;
            display: inline-block;
            margin-top: 2px;
        }
        
        /* Title */
        .page-title {
            font-size: 16px;
            font-weight: 700;
            color: #0B5ED7;
            text-align: center;
            margin: 4px 0 1px 0;
        }
        
        .page-subtitle {
            font-size: 9px;
            color: #64748B;
            text-align: center;
            margin-bottom: 8px;
        }
        
        /* Section Titles */
        .section-title {
            font-size: 11px;
            font-weight: 700;
            color: #0B5ED7;
            border-bottom: 2px solid #0B5ED7;
            padding-bottom: 3px;
            margin: 12px 0 8px 0;
        }
        
        .section-title.green { color: #059669; border-color: #059669; }
        .section-title.purple { color: #7C3AED; border-color: #7C3AED; }
        .section-title.orange { color: #D97706; border-color: #D97706; }
        .section-title.red { color: #DC2626; border-color: #DC2626; }
        
        /* Grids */
        .row-2col { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .row-6col { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr 1fr 1fr; gap: 6px; }
        
        /* Info Cards */
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
        
        .external-tag {
            font-size: 6px;
            background: #D97706;
            color: white;
            padding: 1px 6px;
            border-radius: 8px;
            margin-left: 4px;
            display: inline-block;
        }
        
        /* Vital Signs */
        .vital-item {
            background: #F8FAFC;
            border-radius: 4px;
            padding: 6px 8px;
            text-align: center;
            border: 1px solid #E2E8F0;
        }
        
        .vital-item .vital-label {
            font-size: 6px;
            font-weight: 600;
            color: #64748B;
            text-transform: uppercase;
            display: block;
        }
        
        .vital-item .vital-value {
            font-size: 11px;
            font-weight: 700;
            display: block;
            margin-top: 1px;
        }
        
        .vital-item .vital-unit {
            font-size: 7px;
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
        
        /* Sick Box */
        .sick-box {
            background: #E8F0FE;
            border-radius: 4px;
            padding: 10px 14px;
            border: 2px solid #0B5ED7;
            margin: 6px 0;
        }
        
        .sick-box .sick-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 10px;
        }
        
        .sick-box .sick-item .slabel {
            font-size: 7px;
            font-weight: 600;
            color: #64748B;
            text-transform: uppercase;
            display: block;
        }
        
        .sick-box .sick-item .svalue {
            font-size: 12px;
            font-weight: 700;
            color: #0B5ED7;
            display: block;
            margin-top: 1px;
        }
        
        /* Restriction Box */
        .restriction-box {
            background: #FEF3C7;
            border-radius: 4px;
            padding: 6px 12px;
            border: 1px solid #F59E0B;
            margin: 4px 0;
        }
        
        .restriction-box .rlabel {
            font-size: 8px;
            font-weight: 700;
            color: #92400E;
        }
        
        .restriction-box .rvalue {
            font-size: 9px;
            color: #92400E;
        }
        
        /* Footer with Stamp */
        .footer-section {
            margin-top: 16px;
            padding-top: 10px;
            border-top: 2px solid #E2E8F0;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
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
        
        /* Official Stamp */
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
        
        /* Footer Note */
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
        
        .text-sm { font-size: 8px; }
        .text-gray { color: #64748B; }
        .font-bold { font-weight: 700; }
        .mt-2 { margin-top: 4px; }
        
        /* ================================================================
           PRINT STYLES
           ================================================================ */
        @media print {
            body {
                background: white;
                padding: 0;
                margin: 0;
            }
            .controls-bar {
                display: none !important;
            }
            .a4-container {
                width: 100%;
                min-height: auto;
                box-shadow: none;
                border-radius: 0;
                padding: 30px 35px;
                margin: 0;
            }
            .pdf-content {
                font-size: 9px;
            }
            .info-card .value { font-size: 9px; }
            .vital-item .vital-value { font-size: 10px; }
            .sick-box .sick-item .svalue { font-size: 11px; }
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 800px) {
            .a4-container {
                width: 100%;
                padding: 20px 24px;
            }
            .controls-bar {
                flex-direction: column;
                padding: 12px 16px;
            }
            .controls-bar .btn {
                width: 100%;
            }
            .row-2col { grid-template-columns: 1fr; }
            .row-6col { grid-template-columns: 1fr 1fr 1fr; }
            .sick-box .sick-grid { grid-template-columns: 1fr; }
            .footer-section { flex-direction: column; }
            .stamp-container { justify-content: flex-start; }
            .header { flex-direction: column; text-align: center; }
            .header-right { text-align: center; margin-top: 6px; }
            .header-left { flex-direction: column; }
        }
        
        @media (max-width: 480px) {
            .a4-container {
                padding: 12px 14px;
            }
            .controls-bar .btn {
                font-size: 0.75rem;
                padding: 8px 14px;
                min-width: auto;
            }
            .row-6col { grid-template-columns: 1fr 1fr; }
            .detail-row { flex-direction: column; }
            .detail-label { width: 100%; margin-bottom: 1px; }
            .signature-area { flex-direction: column; gap: 6px; }
            .header-logo { width: 45px; height: 45px; }
            .header-text .logo-title { font-size: 16px; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- CONTROLS BAR -->
<!-- ================================================================ -->
<div class="controls-bar">
    <button onclick="window.print()" class="btn btn-primary">
        <i class="fas fa-print"></i> Print
    </button>
    <button onclick="downloadPDF()" class="btn btn-success">
        <i class="fas fa-download"></i> Download PDF
    </button>
    <a href="documents.php" class="btn btn-outline">
        <i class="fas fa-times"></i> Cancel
    </a>
    <a href="dashboard.php" class="btn btn-outline">
        <i class="fas fa-home"></i> Dashboard
    </a>
</div>

<!-- ================================================================ -->
<!-- A4 CONTAINER -->
<!-- ================================================================ -->
<div class="a4-container" id="pdfContent">

    <div class="pdf-content">

        <!-- Header with Logo - LIKE VISIT -->
        <div class="header">
            <div class="header-left">
                <!-- BRAICK LOGO - REAL IMAGE -->
                <div class="header-logo">
                    <img src="<?= $logo_url ?>" 
                         alt="Braick Dispensary" 
                         style="width:100%;height:100%;object-fit:contain;"
                         onerror="this.parentElement.innerHTML='<div style=\'width:60px;height:60px;background:#0B5ED7;border-radius:12px;display:flex;align-items:center;justify-content:center;color:white;font-size:24px;font-weight:800;font-family:Arial;\'>B</div>'">
                </div>
                <div class="header-text">
                    <div class="logo-title">BRAICK DISPENSARY</div>
                    <div class="logo-sub">Quality Healthcare Services</div>
                    <div class="branch-info">
                        <?= htmlspecialchars($sick_sheet['branch_name'] ?? $branch_name) ?> | 
                        <?= htmlspecialchars($sick_sheet['branch_location'] ?? '') ?> | 
                        Tel: <?= htmlspecialchars($sick_sheet['branch_phone'] ?? '') ?>
                    </div>
                </div>
            </div>
            <div class="header-right">
                <div class="document-number">
                    Document #: <?= htmlspecialchars($sick_sheet['document_number'] ?? 'N/A') ?>
                </div>
                <div class="source-badge">
                    <?= $is_external ? '⚠️ EXTERNAL PATIENT' : '✅ REGISTERED' ?>
                </div>
            </div>
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
                    <span class="value">
                        <?= htmlspecialchars($sick_sheet['full_name'] ?? 'N/A') ?>
                        <?php if ($is_external): ?>
                            <span class="external-tag">⚠️ EXTERNAL</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="info-card" style="margin-top:4px;">
                    <span class="label">Patient ID</span>
                    <span class="value font-mono"><?= htmlspecialchars($sick_sheet['patient_id'] ?? 'N/A') ?></span>
                </div>
                <div class="info-card" style="margin-top:4px;">
                    <span class="label">Gender</span>
                    <span class="value"><?= ucfirst($sick_sheet['gender'] ?? 'N/A') ?></span>
                </div>
                <div class="info-card" style="margin-top:4px;">
                    <span class="label">Date of Birth</span>
                    <span class="value"><?= !empty($sick_sheet['date_of_birth']) ? date('d M Y', strtotime($sick_sheet['date_of_birth'])) : 'N/A' ?></span>
                </div>
            </div>
            <div>
                <div class="info-card green">
                    <span class="label">Phone</span>
                    <span class="value"><?= htmlspecialchars($sick_sheet['phone'] ?? 'N/A') ?></span>
                </div>
                <div class="info-card" style="margin-top:4px;">
                    <span class="label">Blood Group</span>
                    <span class="value"><?= htmlspecialchars($sick_sheet['blood_group'] ?? 'N/A') ?></span>
                </div>
                <div class="info-card" style="margin-top:4px;">
                    <span class="label">Address</span>
                    <span class="value" style="font-weight:400;font-size:9px;"><?= htmlspecialchars($sick_sheet['address'] ?? 'N/A') ?></span>
                </div>
                <div class="info-card" style="margin-top:4px;">
                    <span class="label">Allergies</span>
                    <span class="value" style="font-weight:400;color:#DC2626;"><?= htmlspecialchars($sick_sheet['allergies'] ?? 'None reported') ?></span>
                </div>
            </div>
        </div>

        <!-- Doctor Information -->
        <div class="section-title green">👨‍⚕️ Doctor Information</div>
        <div class="row-2col">
            <div class="info-card" style="border-left:3px solid #059669;">
                <span class="label">Doctor Name</span>
                <span class="value">Dr. <?= htmlspecialchars($sick_sheet['doctor_name'] ?? $full_name) ?></span>
            </div>
            <div class="info-card" style="border-left:3px solid #059669;">
                <span class="label">Specialty</span>
                <span class="value"><?= htmlspecialchars($sick_sheet['doctor_specialty'] ?? 'Medical Doctor') ?></span>
            </div>
        </div>

        <!-- Clinical Details -->
        <div class="section-title purple">🩺 Clinical Details</div>

        <div class="detail-row">
            <span class="detail-label">Symptoms</span>
            <span class="detail-value"><?= nl2br(htmlspecialchars($sick_sheet['symptoms'] ?? 'Not specified')) ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Diagnosis</span>
            <span class="detail-value"><strong><?= nl2br(htmlspecialchars($sick_sheet['diagnosis'] ?? 'Not specified')) ?></strong></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Treatment</span>
            <span class="detail-value"><?= nl2br(htmlspecialchars($sick_sheet['treatment'] ?? 'Not specified')) ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Instructions</span>
            <span class="detail-value"><?= nl2br(htmlspecialchars($sick_sheet['instructions'] ?? 'Not specified')) ?></span>
        </div>

        <!-- Sick Leave Details -->
        <div class="section-title orange">📅 Sick Leave Details</div>

        <div class="sick-box">
            <div class="sick-grid">
                <div class="sick-item">
                    <span class="slabel">Sick Days</span>
                    <span class="svalue"><?= $sick_sheet['sick_days'] ?? 0 ?> days</span>
                </div>
                <div class="sick-item">
                    <span class="slabel">From</span>
                    <span class="svalue"><?= !empty($sick_sheet['sick_from']) ? date('d M Y', strtotime($sick_sheet['sick_from'])) : 'N/A' ?></span>
                </div>
                <div class="sick-item">
                    <span class="slabel">To</span>
                    <span class="svalue"><?= !empty($sick_sheet['sick_to']) ? date('d M Y', strtotime($sick_sheet['sick_to'])) : 'N/A' ?></span>
                </div>
            </div>
        </div>

        <!-- Reason & Restrictions -->
        <div class="section-title orange">📝 Reason & Recommendations</div>

        <div class="detail-row">
            <span class="detail-label">Reason</span>
            <span class="detail-value"><?= htmlspecialchars($sick_sheet['sick_reason'] ?? 'Medical condition requiring rest') ?></span>
        </div>

        <div class="restriction-box">
            <span class="rlabel">⚠ Restrictions:</span>
            <span class="rvalue"><?= htmlspecialchars($sick_sheet['sick_restrictions'] ?? 'No heavy lifting, complete rest') ?></span>
        </div>

        <!-- Footer with Stamp -->
        <div class="footer-section">
            <div class="footer-left">
                <div class="doctor-name">Dr. <?= htmlspecialchars($sick_sheet['doctor_name'] ?? $full_name) ?></div>
                <div class="doctor-details">
                    <?= htmlspecialchars($sick_sheet['doctor_specialty'] ?? 'Medical Doctor') ?><br>
                    Tel: <?= htmlspecialchars($sick_sheet['doctor_phone'] ?? '') ?>
                    <?php if (!empty($sick_sheet['doctor_email'])): ?>
                        | Email: <?= htmlspecialchars($sick_sheet['doctor_email'] ?? '') ?>
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
            
            <!-- Official Stamp -->
            <div class="stamp-container">
                <div class="stamp">
                    <div class="stamp-title">Official Stamp</div>
                    <div class="stamp-name">BRAICK DISPENSARY</div>
                    <div class="stamp-line"></div>
                    <div class="stamp-doctor">Dr. <?= htmlspecialchars($sick_sheet['doctor_name'] ?? $full_name) ?></div>
                    <div class="stamp-signature">_________________________</div>
                    <div class="stamp-date">Date: <?= date('d M Y') ?></div>
                </div>
            </div>
        </div>

        <!-- Footer Note -->
        <div class="footer-note">
            <div>
                <span class="brand">🏥 Braick Dispensary</span> 
                <span style="color:#94A3B8;">|</span> 
                <?= htmlspecialchars($sick_sheet['document_number'] ?? 'N/A') ?>
                <span style="color:#94A3B8;">|</span> 
                Generated: <?= date('d M Y, h:i A') ?>
                <span style="color:#94A3B8;">|</span> 
                Source: <span style="color:#0B5ED7;"><?= $data_source ?></span>
            </div>
            <div class="slogan">⭐ Braick Dispensary - Tunajali Afya Yako ⭐</div>
        </div>

    </div><!-- end pdf-content -->

</div><!-- end a4-container -->

<!-- ================================================================ -->
<!-- FONT AWESOME FOR ICONS -->
<!-- ================================================================ -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DOWNLOAD PDF - Using html2canvas + jsPDF
    // ================================================================
    function downloadPDF() {
        var btn = document.querySelector('.controls-bar .btn-success');
        var originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
        btn.disabled = true;
        
        // Load html2canvas and jsPDF from CDN
        var script1 = document.createElement('script');
        script1.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
        document.head.appendChild(script1);
        
        var script2 = document.createElement('script');
        script2.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';
        document.head.appendChild(script2);
        
        setTimeout(function() {
            if (typeof html2canvas !== 'undefined' && typeof jspdf !== 'undefined') {
                var element = document.getElementById('pdfContent');
                
                html2canvas(element, {
                    scale: 2,
                    useCORS: true,
                    logging: false,
                    backgroundColor: '#ffffff'
                }).then(function(canvas) {
                    var imgData = canvas.toDataURL('image/png');
                    var { jsPDF } = jspdf;
                    var pdf = new jsPDF('p', 'mm', 'a4');
                    var pdfWidth = pdf.internal.pageSize.getWidth();
                    var pdfHeight = (canvas.height * pdfWidth) / canvas.width;
                    
                    pdf.addImage(imgData, 'PNG', 0, 0, pdfWidth, pdfHeight);
                    pdf.save('Sick_Sheet_<?= htmlspecialchars($sick_sheet['full_name'] ?? 'Patient') ?>_<?= date('Ymd') ?>.pdf');
                    
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }).catch(function(err) {
                    alert('Error generating PDF. Please use Print > Save as PDF.');
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                });
            } else {
                // Fallback: Use print
                alert('PDF library loading. Please use Print > Save as PDF.');
                btn.innerHTML = originalText;
                btn.disabled = false;
                window.print();
            }
        }, 1500);
    }

    // ================================================================
    // CHECK LOGO LOADED
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        var logoImg = document.querySelector('.header-logo img');
        if (logoImg) {
            logoImg.onerror = function() {
                this.parentElement.innerHTML = '<div style="width:60px;height:60px;background:#0B5ED7;border-radius:12px;display:flex;align-items:center;justify-content:center;color:white;font-size:24px;font-weight:800;font-family:Arial;">B</div>';
            };
        }
    });

    console.log('📄 Sick Sheet - <?= htmlspecialchars($sick_sheet['full_name'] ?? 'Patient') ?>');
    console.log('📋 Document #: <?= htmlspecialchars($sick_sheet['document_number'] ?? 'N/A') ?>');
    console.log('🔍 Source: <?= $data_source ?>');
    console.log('🖼️ Logo Path: <?= $logo_url ?>');
    console.log('⭐ Braick Dispensary - Tunajali Afya Yako');
</script>

</body>
</html>

<?php
exit;
?>