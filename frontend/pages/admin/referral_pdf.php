<?php
// ================================================================
// FILE: frontend/pages/admin/referral_pdf.php
// SUPER ADMIN - REFERRAL PDF (PRINT PAGE)
// ✅ BLUE THEME
// ✅ Logo ya Braick inaonekana
// ✅ Print + Download PDF buttons
// ✅ 7 Vital Signs (with SpO2)
// BRAICK DISPENSARY
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// GET REFERRAL ID
// ================================================================
$referral_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($referral_id <= 0) {
    die('Invalid referral ID');
}

// ================================================================
// GET ADMIN INFO
// ================================================================
$admin_phone = '';
$admin_email = '';
$admin_name = 'Admin';

try {
    $stmt = $db->prepare("SELECT full_name, phone, email FROM users WHERE role = 'admin' AND status = 'active' LIMIT 1");
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($admin) {
        $admin_phone = $admin['phone'] ?? '';
        $admin_email = $admin['email'] ?? '';
        $admin_name = $admin['full_name'] ?? 'Admin';
    }
} catch (Exception $e) {}

// ================================================================
// FETCH REFERRAL + PATIENT + DOCTOR DETAILS
// ================================================================
$referral = null;

try {
    $stmt = $db->prepare("
        SELECT 
            r.*,
            p.full_name as patient_name,
            p.patient_id as patient_code,
            p.phone as patient_phone,
            p.email as patient_email,
            p.gender as patient_gender,
            p.date_of_birth as patient_dob,
            p.address as patient_address,
            p.blood_group as patient_blood,
            p.allergies as patient_allergies,
            p.emergency_contact as patient_emergency,
            u_from.full_name as from_doctor_name,
            u_from.specialty as from_doctor_specialty,
            u_from.phone as from_doctor_phone,
            u_from.email as from_doctor_email,
            u_to.full_name as to_doctor_name,
            u_to.specialty as to_doctor_specialty,
            u_to.phone as to_doctor_phone,
            u_to.email as to_doctor_email,
            v.visit_number,
            v.visit_date,
            v.diagnosis as visit_diagnosis,
            v.symptoms as visit_symptoms,
            v.hpi as visit_hpi,
            v.physical_exam as visit_physical_exam,
            v.treatment as visit_treatment,
            v.status as visit_status,
            b.name as branch_name,
            b.location as branch_location,
            b.phone as branch_phone,
            b.email as branch_email
        FROM referrals r
        LEFT JOIN patients p ON r.patient_id = p.id
        LEFT JOIN visits v ON r.visit_id = v.id
        LEFT JOIN users u_from ON r.from_doctor_id = u_from.id
        LEFT JOIN users u_to ON r.to_doctor_id = u_to.id
        LEFT JOIN branches b ON r.branch_id = b.id
        WHERE r.id = ?
    ");
    $stmt->execute([$referral_id]);
    $referral = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$referral) {
        die('Referral not found');
    }
} catch (Exception $e) {
    die("Error fetching referral: " . $e->getMessage());
}

// ================================================================
// GET VITAL SIGNS (from visit)
// ================================================================
$vital_signs = null;
if (!empty($referral['visit_id'])) {
    try {
        $stmt = $db->prepare("
            SELECT temperature, blood_pressure_systolic, blood_pressure_diastolic,
                   pulse_rate, oxygen_saturation, weight, height, bmi, notes, recorded_at,
                   u.full_name as recorded_by_name
            FROM vital_signs vs
            LEFT JOIN users u ON vs.recorded_by = u.id
            WHERE vs.visit_id = ?
            ORDER BY vs.recorded_at DESC LIMIT 1
        ");
        $stmt->execute([$referral['visit_id']]);
        $vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// ================================================================
// GET LAB TESTS
// ================================================================
$lab_tests = [];
if (!empty($referral['visit_id'])) {
    try {
        $stmt = $db->prepare("
            SELECT lt.test_name, lt.results, lt.status
            FROM lab_tests lt
            WHERE lt.visit_id = ?
            ORDER BY lt.created_at DESC
        ");
        $stmt->execute([$referral['visit_id']]);
        $lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// ================================================================
// HELPER FUNCTIONS
// ================================================================
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

function getVitalStatus($value, $type) {
    if ($value === null || $value === '' || $value === '--') return ['label' => 'N/A', 'color' => '#64748B', 'bg' => '#F1F5F9'];
    switch ($type) {
        case 'temperature':
            if ($value > 37.5) return ['label' => 'HIGH', 'color' => '#DC2626', 'bg' => '#FEE2E2'];
            if ($value < 36.0) return ['label' => 'LOW', 'color' => '#D97706', 'bg' => '#FEF3C7'];
            return ['label' => 'NORMAL', 'color' => '#059669', 'bg' => '#D1FAE5'];
        case 'systolic':
            if ($value > 140) return ['label' => 'HIGH', 'color' => '#DC2626', 'bg' => '#FEE2E2'];
            if ($value < 90) return ['label' => 'LOW', 'color' => '#D97706', 'bg' => '#FEF3C7'];
            return ['label' => 'NORMAL', 'color' => '#059669', 'bg' => '#D1FAE5'];
        case 'pulse':
            if ($value > 100) return ['label' => 'HIGH', 'color' => '#DC2626', 'bg' => '#FEE2E2'];
            if ($value < 60) return ['label' => 'LOW', 'color' => '#D97706', 'bg' => '#FEF3C7'];
            return ['label' => 'NORMAL', 'color' => '#059669', 'bg' => '#D1FAE5'];
        case 'spo2':
            if ($value >= 95) return ['label' => 'NORMAL', 'color' => '#059669', 'bg' => '#D1FAE5'];
            if ($value >= 90) return ['label' => 'LOW', 'color' => '#D97706', 'bg' => '#FEF3C7'];
            return ['label' => 'CRITICAL', 'color' => '#DC2626', 'bg' => '#FEE2E2'];
        case 'bmi':
            if ($value >= 30) return ['label' => 'OBESE', 'color' => '#DC2626', 'bg' => '#FEE2E2'];
            if ($value >= 25) return ['label' => 'OVERWEIGHT', 'color' => '#D97706', 'bg' => '#FEF3C7'];
            if ($value >= 18.5) return ['label' => 'NORMAL', 'color' => '#059669', 'bg' => '#D1FAE5'];
            return ['label' => 'UNDERWEIGHT', 'color' => '#D97706', 'bg' => '#FEF3C7'];
        default:
            return ['label' => 'N/A', 'color' => '#64748B', 'bg' => '#F1F5F9'];
    }
}

function getLogoHTML() {
    $logo_paths = [
        '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png',
        '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.PNG',
        '/dispensary_system/frontend/assets/uploads/profiles/logo.png',
        '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.jpg',
        '/dispensary_system/frontend/assets/uploads/profiles/logo.jpg',
        '/dispensary_system/frontend/assets/img/braick_logo.png',
        '/dispensary_system/frontend/assets/img/logo.png',
    ];
    
    foreach ($logo_paths as $path) {
        if (file_exists($_SERVER['DOCUMENT_ROOT'] . $path)) {
            return '<img src="' . $path . '" alt="Braick Logo" style="height:70px;width:auto;max-height:70px;object-fit:contain;display:block;">';
        }
    }
    
    return '<div style="display:inline-block;background:#0B5ED7;color:white;padding:10px 24px;border-radius:8px;font-size:22px;font-weight:bold;letter-spacing:2px;">BRAICK</div>';
}

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
if (!file_exists($_SERVER['DOCUMENT_ROOT'] . $logo_url)) {
    $logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.PNG';
}

$branch_name = $referral['branch_name'] ?? 'Braick Dispensary';
$branch_location = $referral['branch_location'] ?? '';
$branch_phone = $referral['branch_phone'] ?? ($admin_phone ?: '');
$branch_email = $referral['branch_email'] ?? ($admin_email ?: '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Referral PDF - <?= htmlspecialchars($referral['referral_number'] ?? 'Referral') ?></title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            background: #E8F0FE;
            font-family: 'Segoe UI', Arial, sans-serif;
            padding: 20px;
            color: #1E293B;
        }
        
        /* ================================================================
           TOOLBAR
           ================================================================ */
        .pdf-toolbar {
            max-width: 900px;
            margin: 0 auto 16px auto;
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            border-radius: 12px;
            padding: 14px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.25);
        }
        
        .pdf-toolbar .toolbar-title {
            color: white;
            font-weight: 700;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .pdf-toolbar .toolbar-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .pdf-toolbar .toolbar-btn {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            backdrop-filter: blur(4px);
        }
        
        .pdf-toolbar .toolbar-btn:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }
        
        .pdf-toolbar .toolbar-btn.primary {
            background: white;
            color: #0B5ED7;
            border: none;
        }
        
        .pdf-toolbar .toolbar-btn.primary:hover {
            background: #F0F7FF;
        }
        
        .pdf-toolbar .toolbar-btn.danger {
            background: rgba(220, 38, 38, 0.3);
            border-color: rgba(220, 38, 38, 0.5);
        }
        
        .pdf-toolbar .toolbar-btn.danger:hover {
            background: rgba(220, 38, 38, 0.5);
        }
        
        /* ================================================================
           PDF CONTAINER
           ================================================================ */
        .pdf-container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .pdf-content {
            background: white;
            padding: 40px 45px;
            color: #1E293B;
            line-height: 1.6;
            font-size: 10pt;
        }
        
        /* ================================================================
           PDF HEADER - LOGO
           ================================================================ */
        .pdf-header {
            text-align: center;
            border-bottom: 3px double #0B5ED7;
            padding-bottom: 16px;
            margin-bottom: 20px;
        }
        
        .pdf-header .logo-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 18px;
            margin-bottom: 8px;
            flex-wrap: wrap;
        }
        
        .pdf-header .logo-img {
            height: 70px;
            width: auto;
            max-height: 70px;
            object-fit: contain;
            display: block;
        }
        
        .pdf-header .brand-info {
            text-align: left;
        }
        
        .pdf-header .brand-name {
            font-size: 26px;
            font-weight: 800;
            color: #0B5ED7;
            letter-spacing: 2px;
            line-height: 1.1;
        }
        
        .pdf-header .brand-slogan {
            font-size: 11px;
            color: #059669;
            letter-spacing: 3px;
            text-transform: uppercase;
            font-weight: 600;
            margin-top: 4px;
        }
        
        .pdf-header .doc-title {
            font-size: 15px;
            font-weight: 700;
            color: #0B5ED7;
            background: #E8F0FE;
            padding: 6px 24px;
            border-radius: 20px;
            display: inline-block;
            margin-top: 8px;
            border: 2px solid #6EA8FE;
        }
        
        .pdf-header .header-info {
            font-size: 9px;
            color: #64748B;
            margin-top: 8px;
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        /* ================================================================
           SECTION TITLE
           ================================================================ */
        .pdf-section-title {
            font-size: 12pt;
            font-weight: 700;
            color: #0B5ED7;
            border-bottom: 2px solid #0B5ED7;
            padding-bottom: 5px;
            margin-bottom: 10px;
            margin-top: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .pdf-section-title:first-of-type {
            margin-top: 0;
        }
        
        /* ================================================================
           INFO GRID
           ================================================================ */
        .pdf-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px 20px;
            margin-bottom: 12px;
        }
        
        .pdf-info-row {
            display: flex;
            padding: 4px 0;
            border-bottom: 1px solid #E2E8F0;
        }
        
        .pdf-info-label {
            font-weight: 600;
            color: #64748B;
            width: 140px;
            flex-shrink: 0;
            font-size: 10pt;
        }
        
        .pdf-info-value {
            font-size: 10pt;
            color: #1E293B;
            word-break: break-word;
            flex: 1;
        }
        
        .pdf-info-value.bold {
            font-weight: 700;
        }
        
        .pdf-info-value.blue {
            color: #0B5ED7;
            font-weight: 700;
        }
        
        .pdf-info-value.danger {
            color: #DC2626;
            font-weight: 700;
        }
        
        .pdf-full-width {
            grid-column: 1 / -1;
        }
        
        /* ================================================================
           VITAL SIGNS
           ================================================================ */
        .pdf-vitals {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 6px;
            margin-bottom: 12px;
        }
        
        .pdf-vital-card {
            background: #F8FAFC;
            border-radius: 6px;
            padding: 8px 4px;
            text-align: center;
            border: 1px solid #E2E8F0;
        }
        
        .pdf-vital-card .vital-icon {
            font-size: 14px;
            display: block;
            margin-bottom: 2px;
        }
        
        .pdf-vital-card .vital-label {
            font-size: 6.5px;
            font-weight: 700;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
        }
        
        .pdf-vital-card .vital-value {
            font-size: 11px;
            font-weight: 700;
            color: #0B5ED7;
            display: block;
            margin-top: 3px;
        }
        
        .pdf-vital-card .vital-unit {
            font-size: 6.5px;
            font-weight: 400;
            color: #64748B;
        }
        
        .pdf-vital-card .vital-status {
            font-size: 6px;
            font-weight: 700;
            padding: 1px 6px;
            border-radius: 4px;
            display: inline-block;
            margin-top: 3px;
        }
        
        /* ================================================================
           TABLES
           ================================================================ */
        .pdf-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10pt;
            margin-bottom: 12px;
            border: 1px solid #E2E8F0;
            border-radius: 6px;
            overflow: hidden;
        }
        
        .pdf-table thead th {
            background: #0B5ED7;
            color: white;
            padding: 8px 12px;
            text-align: left;
            font-size: 9pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .pdf-table tbody td {
            padding: 8px 12px;
            border-bottom: 1px solid #E2E8F0;
            font-size: 10pt;
            vertical-align: top;
        }
        
        .pdf-table tbody tr:nth-child(even) td {
            background: #F8FAFC;
        }
        
        .pdf-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        /* ================================================================
           BADGES
           ================================================================ */
        .pdf-badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 12px;
            font-size: 9pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .pdf-badge-blue { background: #DBEAFE; color: #0B5ED7; }
        .pdf-badge-darkblue { background: #BFDBFE; color: #0A4CA8; }
        .pdf-badge-deepblue { background: #C7D2FE; color: #1E40AF; }
        .pdf-badge-navy { background: #E0E7FF; color: #1E3A8A; }
        .pdf-badge-gray { background: #E2E8F0; color: #64748B; }
        
        /* ================================================================
           REASON BOX
           ================================================================ */
        .pdf-reason-box {
            background: #E8F0FE;
            border-left: 4px solid #0B5ED7;
            border-radius: 6px;
            padding: 12px 16px;
            margin-bottom: 12px;
            font-size: 10pt;
            line-height: 1.6;
            color: #1E293B;
        }
        
        .pdf-reason-box .reason-label {
            font-weight: 700;
            color: #0B5ED7;
            display: block;
            margin-bottom: 4px;
            font-size: 9pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* ================================================================
           FOOTER WITH STAMP
           ================================================================ */
        .pdf-footer {
            margin-top: 24px;
            padding-top: 14px;
            border-top: 3px double #0B5ED7;
            text-align: center;
        }
        
        .pdf-footer .footer-slogan {
            font-size: 11pt;
            font-weight: 700;
            color: #0B5ED7;
            margin-bottom: 6px;
        }
        
        .pdf-footer .footer-info {
            font-size: 8.5pt;
            color: #64748B;
            margin-bottom: 12px;
            display: flex;
            justify-content: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        
        .pdf-footer .stamp-box {
            display: inline-block;
            border: 3px solid #0B5ED7;
            border-radius: 10px;
            padding: 10px 28px;
            background: #E8F0FE;
            margin-top: 6px;
        }
        
        .pdf-footer .stamp-title {
            font-size: 8px;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-weight: 700;
        }
        
        .pdf-footer .stamp-name {
            font-size: 16px;
            font-weight: 800;
            color: #0B5ED7;
            letter-spacing: 1px;
            margin: 4px 0;
        }
        
        .pdf-footer .stamp-line {
            font-size: 8px;
            color: #64748B;
        }
        
        .pdf-footer .footer-note {
            font-size: 7.5px;
            color: #94A3B8;
            margin-top: 10px;
            font-style: italic;
        }
        
        /* ================================================================
           PRINT STYLES
           ================================================================ */
        @media print {
            body {
                background: white !important;
                padding: 0 !important;
            }
            .pdf-toolbar {
                display: none !important;
            }
            .pdf-container {
                box-shadow: none !important;
                border-radius: 0 !important;
                max-width: 100% !important;
            }
            .pdf-content {
                padding: 15mm !important;
            }
            .pdf-section-title {
                page-break-after: avoid;
                break-after: avoid;
            }
            .pdf-table {
                page-break-inside: avoid;
                break-inside: avoid;
            }
            .pdf-vital-card {
                page-break-inside: avoid;
                break-inside: avoid;
            }
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 768px) {
            body { padding: 10px; }
            .pdf-content { padding: 20px 16px; }
            .pdf-grid-2 { grid-template-columns: 1fr; }
            .pdf-vitals { grid-template-columns: repeat(4, 1fr); }
            .pdf-header .brand-name { font-size: 18px; }
            .pdf-toolbar { padding: 10px 14px; }
            .pdf-toolbar .toolbar-title { font-size: 0.8rem; }
            .pdf-toolbar .toolbar-btn { padding: 6px 12px; font-size: 0.7rem; }
        }
        
        @media (max-width: 480px) {
            .pdf-vitals { grid-template-columns: repeat(2, 1fr); }
            .pdf-header .logo-row { gap: 10px; }
            .pdf-header .brand-name { font-size: 16px; }
        }
    </style>
</head>
<body>

<!-- ================================================================
     TOOLBAR
     ================================================================ -->
<div class="pdf-toolbar">
    <div class="toolbar-title">
        <i class="fas fa-file-pdf"></i>
        Referral PDF - <?= htmlspecialchars($referral['referral_number'] ?? 'N/A') ?>
    </div>
    <div class="toolbar-actions">
        <button onclick="window.print()" class="toolbar-btn">
            <i class="fas fa-print"></i> Print
        </button>
        <button onclick="downloadPDF()" class="toolbar-btn primary" id="downloadBtn">
            <i class="fas fa-download"></i> Download PDF
        </button>
        <a href="view_referral.php?id=<?= $referral_id ?>" class="toolbar-btn">
            <i class="fas fa-arrow-left"></i> Back
        </a>
        <a href="javascript:window.close()" class="toolbar-btn danger">
            <i class="fas fa-times"></i> Close
        </a>
    </div>
</div>

<!-- ================================================================
     PDF CONTAINER
     ================================================================ -->
<div class="pdf-container">
    <div class="pdf-content" id="pdfContent">
        
        <!-- ================================================================
             HEADER WITH LOGO
             ================================================================ -->
        <div class="pdf-header">
            <div class="logo-row">
                <?= getLogoHTML() ?>
                <div class="brand-info">
                    <div class="brand-name">BRAICK DISPENSARY</div>
                    <div class="brand-slogan">Tunajali Afya Yako</div>
                </div>
            </div>
            <div class="doc-title">
                📋 EXTERNAL REFERRAL LETTER
            </div>
            <div class="header-info">
                <?php if (!empty($branch_phone)): ?>
                    <span><strong>📞 Phone:</strong> <?= htmlspecialchars($branch_phone) ?></span>
                <?php endif; ?>
                <?php if (!empty($branch_location)): ?>
                    <span><strong>📍 Location:</strong> <?= htmlspecialchars($branch_location) ?></span>
                <?php endif; ?>
                <span><strong>📅 Date:</strong> <?= date('d/m/Y') ?></span>
                <?php if (!empty($branch_email)): ?>
                    <span><strong>✉️ Email:</strong> <?= htmlspecialchars($branch_email) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <!-- ================================================================
             REFERRAL HEADER INFO
             ================================================================ -->
        <div class="pdf-grid-2">
            <div class="pdf-info-row">
                <span class="pdf-info-label">Referral Number:</span>
                <span class="pdf-info-value blue"><?= htmlspecialchars($referral['referral_number'] ?? 'N/A') ?></span>
            </div>
            <div class="pdf-info-row">
                <span class="pdf-info-label">Referral Date:</span>
                <span class="pdf-info-value"><?= !empty($referral['created_at']) ? date('d/m/Y h:i A', strtotime($referral['created_at'])) : 'N/A' ?></span>
            </div>
            <div class="pdf-info-row">
                <span class="pdf-info-label">Referral Type:</span>
                <span class="pdf-info-value bold">
                    <i class="fas <?= ($referral['referral_type'] ?? '') === 'internal' ? 'fa-hospital' : 'fa-globe-africa' ?>" style="color:#0B5ED7;"></i>
                    <?= ucfirst($referral['referral_type'] ?? 'N/A') ?>
                </span>
            </div>
            <div class="pdf-info-row">
                <span class="pdf-info-label">Status:</span>
                <span class="pdf-info-value">
                    <?php
                    $status_map = [
                        'pending' => ['label' => 'Pending', 'class' => 'pdf-badge-blue'],
                        'referred' => ['label' => 'Referred', 'class' => 'pdf-badge-darkblue'],
                        'accepted' => ['label' => 'Accepted', 'class' => 'pdf-badge-blue'],
                        'completed' => ['label' => 'Completed', 'class' => 'pdf-badge-deepblue'],
                        'cancelled' => ['label' => 'Cancelled', 'class' => 'pdf-badge-gray'],
                    ];
                    $st = $status_map[$referral['status'] ?? 'pending'] ?? $status_map['pending'];
                    ?>
                    <span class="pdf-badge <?= $st['class'] ?>"><?= $st['label'] ?></span>
                </span>
            </div>
            <?php if (!empty($referral['urgency'])): ?>
            <div class="pdf-info-row">
                <span class="pdf-info-label">Urgency:</span>
                <span class="pdf-info-value">
                    <span class="pdf-badge <?= $referral['urgency'] === 'emergency' ? 'pdf-badge-navy' : ($referral['urgency'] === 'urgent' ? 'pdf-badge-deepblue' : 'pdf-badge-blue') ?>">
                        <i class="fas fa-bolt"></i> <?= ucfirst($referral['urgency']) ?>
                    </span>
                </span>
            </div>
            <?php endif; ?>
            <?php if (!empty($referral['visit_number'])): ?>
            <div class="pdf-info-row">
                <span class="pdf-info-label">Visit Number:</span>
                <span class="pdf-info-value bold" style="font-family:monospace;"><?= htmlspecialchars($referral['visit_number']) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- ================================================================
             PATIENT INFORMATION
             ================================================================ -->
        <div class="pdf-section-title">
            <i class="fas fa-user" style="color:#0B5ED7;"></i>
            👤 PATIENT INFORMATION
        </div>
        
        <div class="pdf-grid-2">
            <div class="pdf-info-row">
                <span class="pdf-info-label">Full Name:</span>
                <span class="pdf-info-value bold"><?= htmlspecialchars($referral['patient_name'] ?? 'N/A') ?></span>
            </div>
            <div class="pdf-info-row">
                <span class="pdf-info-label">Patient ID:</span>
                <span class="pdf-info-value" style="font-family:monospace;"><?= htmlspecialchars($referral['patient_code'] ?? 'N/A') ?></span>
            </div>
            <div class="pdf-info-row">
                <span class="pdf-info-label">Date of Birth:</span>
                <span class="pdf-info-value">
                    <?= !empty($referral['patient_dob']) ? date('d/m/Y', strtotime($referral['patient_dob'])) . ' (' . calculateAge($referral['patient_dob']) . ' yrs)' : 'N/A' ?>
                </span>
            </div>
            <div class="pdf-info-row">
                <span class="pdf-info-label">Gender:</span>
                <span class="pdf-info-value"><?= htmlspecialchars($referral['patient_gender'] ?? 'N/A') ?></span>
            </div>
            <div class="pdf-info-row">
                <span class="pdf-info-label">Phone:</span>
                <span class="pdf-info-value"><?= htmlspecialchars($referral['patient_phone'] ?? 'N/A') ?></span>
            </div>
            <div class="pdf-info-row">
                <span class="pdf-info-label">Emergency Contact:</span>
                <span class="pdf-info-value danger"><?= htmlspecialchars($referral['patient_emergency'] ?? 'N/A') ?></span>
            </div>
            <div class="pdf-info-row">
                <span class="pdf-info-label">Blood Group:</span>
                <span class="pdf-info-value"><?= htmlspecialchars($referral['patient_blood'] ?? 'N/A') ?></span>
            </div>
            <div class="pdf-info-row">
                <span class="pdf-info-label">Allergies:</span>
                <span class="pdf-info-value danger"><?= htmlspecialchars($referral['patient_allergies'] ?? 'None') ?></span>
            </div>
            <div class="pdf-info-row pdf-full-width">
                <span class="pdf-info-label">Address:</span>
                <span class="pdf-info-value"><?= htmlspecialchars($referral['patient_address'] ?? 'N/A') ?></span>
            </div>
        </div>

        <!-- ================================================================
             FROM / TO INFORMATION
             ================================================================ -->
        <div class="pdf-section-title">
            <i class="fas fa-exchange-alt" style="color:#0B5ED7;"></i>
            📤 REFERRAL PATH
        </div>
        
        <div class="pdf-grid-2">
            <!-- FROM DOCTOR -->
            <div class="pdf-info-row pdf-full-width" style="background:#DBEAFE; padding:10px 14px; border-radius:6px;">
                <span class="pdf-info-label" style="color:#0B5ED7;">📤 Referred FROM:</span>
                <span class="pdf-info-value">
                    <strong style="font-size:11pt; color:#0B5ED7;">Dr. <?= htmlspecialchars($referral['from_doctor_name'] ?? 'N/A') ?></strong>
                    <?php if (!empty($referral['from_doctor_specialty'])): ?>
                        <div style="font-size:9pt; color:#64748B; margin-top:2px;">
                            <i class="fas fa-stethoscope"></i> <?= htmlspecialchars($referral['from_doctor_specialty']) ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($referral['from_doctor_phone'])): ?>
                        <div style="font-size:9pt; color:#64748B;">
                            <i class="fas fa-phone"></i> <?= htmlspecialchars($referral['from_doctor_phone']) ?>
                        </div>
                    <?php endif; ?>
                </span>
            </div>
            
            <!-- TO DOCTOR / HOSPITAL -->
            <div class="pdf-info-row pdf-full-width" style="background:#E8F0FE; padding:10px 14px; border-radius:6px;">
                <span class="pdf-info-label" style="color:#0B5ED7;">📥 Referred TO:</span>
                <span class="pdf-info-value">
                    <?php if (($referral['referral_type'] ?? '') === 'internal'): ?>
                        <strong style="font-size:11pt; color:#0B5ED7;">Dr. <?= htmlspecialchars($referral['to_doctor_name'] ?? 'N/A') ?></strong>
                        <?php if (!empty($referral['to_doctor_specialty'])): ?>
                            <div style="font-size:9pt; color:#64748B; margin-top:2px;">
                                <i class="fas fa-stethoscope"></i> <?= htmlspecialchars($referral['to_doctor_specialty']) ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($referral['to_doctor_phone'])): ?>
                            <div style="font-size:9pt; color:#64748B;">
                                <i class="fas fa-phone"></i> <?= htmlspecialchars($referral['to_doctor_phone']) ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <strong style="font-size:11pt; color:#0B5ED7;">🏥 <?= htmlspecialchars($referral['to_hospital_name'] ?? 'N/A') ?></strong>
                        <?php if (!empty($referral['to_hospital_address'])): ?>
                            <div style="font-size:9pt; color:#64748B; margin-top:2px;">
                                <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($referral['to_hospital_address']) ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($referral['to_hospital_phone'])): ?>
                            <div style="font-size:9pt; color:#64748B;">
                                <i class="fas fa-phone"></i> <?= htmlspecialchars($referral['to_hospital_phone']) ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($referral['expert_type'])): ?>
                            <div style="font-size:9pt; color:#64748B;">
                                <i class="fas fa-user-tie"></i> Expert: <?= htmlspecialchars($referral['expert_type']) ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <!-- ================================================================
             VITAL SIGNS
             ================================================================ -->
        <?php if ($vital_signs): 
            $temp_status = getVitalStatus($vital_signs['temperature'] ?? null, 'temperature');
            $bp_status = getVitalStatus($vital_signs['blood_pressure_systolic'] ?? null, 'systolic');
            $pulse_status = getVitalStatus($vital_signs['pulse_rate'] ?? null, 'pulse');
            $spo2_status = getVitalStatus($vital_signs['oxygen_saturation'] ?? null, 'spo2');
            $bmi_status = getVitalStatus($vital_signs['bmi'] ?? null, 'bmi');
        ?>
        <div class="pdf-section-title">
            <i class="fas fa-heartbeat" style="color:#0B5ED7;"></i>
            ❤️ VITAL SIGNS (7 Signs)
            <?php if (!empty($vital_signs['recorded_at'])): ?>
                <span style="font-size:8pt; font-weight:400; color:#64748B; margin-left:8px;">
                    (Recorded: <?= date('d/m/Y h:i A', strtotime($vital_signs['recorded_at'])) ?>)
                </span>
            <?php endif; ?>
        </div>
        
        <div class="pdf-vitals">
            <div class="pdf-vital-card">
                <span class="vital-icon">🌡️</span>
                <span class="vital-label">Temperature</span>
                <span class="vital-value"><?= htmlspecialchars($vital_signs['temperature'] ?? '--') ?> <span class="vital-unit">°C</span></span>
                <span class="vital-status" style="background:<?= $temp_status['bg'] ?>; color:<?= $temp_status['color'] ?>;">
                    <?= $temp_status['label'] ?>
                </span>
            </div>
            <div class="pdf-vital-card">
                <span class="vital-icon">❤️</span>
                <span class="vital-label">Blood Pressure</span>
                <span class="vital-value"><?= htmlspecialchars($vital_signs['blood_pressure_systolic'] ?? '--') ?>/<?= htmlspecialchars($vital_signs['blood_pressure_diastolic'] ?? '--') ?> <span class="vital-unit">mmHg</span></span>
                <span class="vital-status" style="background:<?= $bp_status['bg'] ?>; color:<?= $bp_status['color'] ?>;">
                    <?= $bp_status['label'] ?>
                </span>
            </div>
            <div class="pdf-vital-card">
                <span class="vital-icon">💓</span>
                <span class="vital-label">Pulse Rate</span>
                <span class="vital-value"><?= htmlspecialchars($vital_signs['pulse_rate'] ?? '--') ?> <span class="vital-unit">bpm</span></span>
                <span class="vital-status" style="background:<?= $pulse_status['bg'] ?>; color:<?= $pulse_status['color'] ?>;">
                    <?= $pulse_status['label'] ?>
                </span>
            </div>
            <div class="pdf-vital-card" style="background:#E0F2FE; border-color:#0EA5E9;">
                <span class="vital-icon">🫁</span>
                <span class="vital-label">Oxygen (SpO2)</span>
                <span class="vital-value" style="color:#0284C7;"><?= htmlspecialchars($vital_signs['oxygen_saturation'] ?? '--') ?> <span class="vital-unit">%</span></span>
                <span class="vital-status" style="background:<?= $spo2_status['bg'] ?>; color:<?= $spo2_status['color'] ?>;">
                    <?= $spo2_status['label'] ?>
                </span>
            </div>
            <div class="pdf-vital-card">
                <span class="vital-icon">⚖️</span>
                <span class="vital-label">Weight</span>
                <span class="vital-value"><?= htmlspecialchars($vital_signs['weight'] ?? '--') ?> <span class="vital-unit">kg</span></span>
            </div>
            <div class="pdf-vital-card">
                <span class="vital-icon">📏</span>
                <span class="vital-label">Height</span>
                <span class="vital-value"><?= htmlspecialchars($vital_signs['height'] ?? '--') ?> <span class="vital-unit">cm</span></span>
            </div>
            <div class="pdf-vital-card">
                <span class="vital-icon">📊</span>
                <span class="vital-label">BMI</span>
                <span class="vital-value"><?= htmlspecialchars($vital_signs['bmi'] ?? '--') ?> <span class="vital-unit">kg/m²</span></span>
                <span class="vital-status" style="background:<?= $bmi_status['bg'] ?>; color:<?= $bmi_status['color'] ?>;">
                    <?= $bmi_status['label'] ?>
                </span>
            </div>
        </div>
        
        <div style="font-size:7.5pt; color:#64748B; text-align:right; margin-top:-8px; margin-bottom:12px; font-style:italic;">
            🫁 SpO2 (Oxygen Saturation) Normal Range: 95-100%
        </div>
        <?php endif; ?>

        <!-- ================================================================
             CLINICAL HISTORY
             ================================================================ -->
        <?php if (!empty($referral['visit_symptoms']) || !empty($referral['visit_hpi']) || !empty($referral['visit_physical_exam'])): ?>
        <div class="pdf-section-title">
            <i class="fas fa-file-medical-alt" style="color:#0B5ED7;"></i>
            📋 CLINICAL HISTORY
        </div>
        
        <table class="pdf-table">
            <thead>
                <tr>
                    <th style="width:33.33%;">Symptoms</th>
                    <th style="width:33.33%;">HPI</th>
                    <th style="width:33.33%;">Physical Examination</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?= nl2br(htmlspecialchars($referral['visit_symptoms'] ?: 'No symptoms recorded')) ?></td>
                    <td><?= nl2br(htmlspecialchars($referral['visit_hpi'] ?: 'No HPI recorded')) ?></td>
                    <td><?= nl2br(htmlspecialchars($referral['visit_physical_exam'] ?: 'No physical exam recorded')) ?></td>
                </tr>
            </tbody>
        </table>
        <?php endif; ?>

        <!-- ================================================================
             LAB TESTS
             ================================================================ -->
        <?php if (count($lab_tests) > 0): ?>
        <div class="pdf-section-title">
            <i class="fas fa-flask" style="color:#0B5ED7;"></i>
            🧪 LABORATORY TESTS
        </div>
        
        <table class="pdf-table">
            <thead>
                <tr>
                    <th style="width:50%;">Test Name</th>
                    <th style="width:50%;">Results</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lab_tests as $test): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></strong></td>
                        <td>
                            <?php if (!empty($test['results'])): ?>
                                <span style="color:#0B5ED7; font-weight:700;">✅ <?= htmlspecialchars($test['results']) ?></span>
                            <?php else: ?>
                                <span style="color:#94A3B8;">⏳ Pending</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <!-- ================================================================
             DIAGNOSIS
             ================================================================ -->
        <div class="pdf-section-title">
            <i class="fas fa-stethoscope" style="color:#0B5ED7;"></i>
            🩺 DIAGNOSIS
        </div>
        
        <table class="pdf-table">
            <thead>
                <tr>
                    <th style="width:33.33%;">Disease Name</th>
                    <th style="width:33.33%;">Disease Code</th>
                    <th style="width:33.33%;">Treatment</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong style="color:#0B5ED7;"><?= htmlspecialchars($referral['diagnosis'] ?? 'No diagnosis recorded') ?></strong></td>
                    <td style="font-family:monospace;"><?= htmlspecialchars($referral['visit_diagnosis'] ? 'N/A' : 'N/A') ?></td>
                    <td><?= nl2br(htmlspecialchars($referral['visit_treatment'] ?? 'No treatment recorded')) ?></td>
                </tr>
            </tbody>
        </table>

        <!-- ================================================================
             REASON FOR REFERRAL
             ================================================================ -->
        <?php if (!empty($referral['reason'])): ?>
        <div class="pdf-section-title">
            <i class="fas fa-comment-medical" style="color:#0B5ED7;"></i>
            📋 REASON FOR REFERRAL
        </div>
        
        <div class="pdf-reason-box">
            <span class="reason-label">🩺 Clinical Reason:</span>
            <?= nl2br(htmlspecialchars($referral['reason'])) ?>
        </div>
        <?php endif; ?>

        <!-- ================================================================
             TREATMENT GIVEN
             ================================================================ -->
        <?php if (!empty($referral['treatment_given'])): ?>
        <div class="pdf-section-title">
            <i class="fas fa-pills" style="color:#0B5ED7;"></i>
            💊 TREATMENT GIVEN
        </div>
        
        <div class="pdf-reason-box" style="background:#DBEAFE; border-left-color:#0A4CA8;">
            <?= nl2br(htmlspecialchars($referral['treatment_given'])) ?>
        </div>
        <?php endif; ?>

        <!-- ================================================================
             ADDITIONAL NOTES
             ================================================================ -->
        <?php if (!empty($referral['notes'])): ?>
        <div class="pdf-section-title">
            <i class="fas fa-sticky-note" style="color:#0B5ED7;"></i>
            📝 ADDITIONAL NOTES
        </div>
        
        <div class="pdf-reason-box" style="background:#F1F5F9; border-left-color:#64748B;">
            <?= nl2br(htmlspecialchars($referral['notes'])) ?>
        </div>
        <?php endif; ?>

        <!-- ================================================================
             DOCTOR SIGNATURE
             ================================================================ -->
        <div class="pdf-section-title">
            <i class="fas fa-user-md" style="color:#0B5ED7;"></i>
            ✍️ REFERRING DOCTOR
        </div>
        
        <div class="pdf-grid-2">
            <div class="pdf-info-row">
                <span class="pdf-info-label">Doctor Name:</span>
                <span class="pdf-info-value bold">Dr. <?= htmlspecialchars($referral['from_doctor_name'] ?? $admin_name) ?></span>
            </div>
            <div class="pdf-info-row">
                <span class="pdf-info-label">Specialty:</span>
                <span class="pdf-info-value"><?= htmlspecialchars($referral['from_doctor_specialty'] ?? 'General Medicine') ?></span>
            </div>
            <div class="pdf-info-row">
                <span class="pdf-info-label">Phone:</span>
                <span class="pdf-info-value"><?= htmlspecialchars($referral['from_doctor_phone'] ?? $admin_phone) ?></span>
            </div>
            <div class="pdf-info-row">
                <span class="pdf-info-label">Branch:</span>
                <span class="pdf-info-value"><?= htmlspecialchars($branch_name) ?></span>
            </div>
        </div>
        
        <!-- Signature Line -->
        <div style="margin-top:30px; display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:20px;">
            <div style="flex:1; min-width:200px;">
                <div style="border-bottom:1px solid #1E293B; height:30px;"></div>
                <div style="font-size:9pt; color:#64748B; margin-top:4px;">Doctor's Signature</div>
            </div>
            <div style="flex:1; min-width:200px;">
                <div style="border-bottom:1px solid #1E293B; height:30px;"></div>
                <div style="font-size:9pt; color:#64748B; margin-top:4px;">Date</div>
            </div>
        </div>

        <!-- ================================================================
             FOOTER WITH OFFICIAL STAMP
             ================================================================ -->
        <div class="pdf-footer">
            <div class="footer-slogan">
                💙 BRAICK DISPENSARY - TUNAJALI AFYA YAKO
            </div>
            <div class="footer-info">
                <span><strong>Admin:</strong> <?= htmlspecialchars($admin_name) ?></span>
                <span><strong>Phone:</strong> <?= htmlspecialchars($admin_phone ?: $branch_phone) ?></span>
                <?php if (!empty($admin_email)): ?>
                    <span><strong>Email:</strong> <?= htmlspecialchars($admin_email) ?></span>
                <?php endif; ?>
                <span><strong>Date:</strong> <?= date('d/m/Y') ?></span>
            </div>
            
            <div class="stamp-box">
                <div class="stamp-title">Official Stamp</div>
                <div class="stamp-name">BRAICK DISPENSARY</div>
                <div class="stamp-line">Approved By: _________________</div>
                <div class="stamp-line">Date: <?= date('d/m/Y') ?></div>
            </div>
            
            <div class="footer-note">
                This is a computer-generated document. No signature required.
            </div>
        </div>
        
    </div>
</div>

<!-- ================================================================
     JAVASCRIPT - DOWNLOAD PDF
     ================================================================ -->
<script>
function downloadPDF() {
    var element = document.getElementById('pdfContent');
    var btn = document.getElementById('downloadBtn');
    var originalText = btn.innerHTML;
    
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
    btn.disabled = true;
    
    var opt = {
        margin: [10, 10, 10, 10],
        filename: 'Referral_<?= htmlspecialchars($referral['referral_number'] ?? 'letter') ?>_<?= date('Ymd') ?>.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: {
            scale: 2,
            useCORS: true,
            backgroundColor: '#ffffff',
            logging: false,
            allowTaint: true
        },
        jsPDF: {
            unit: 'mm',
            format: 'a4',
            orientation: 'portrait'
        },
        pagebreak: { mode: ['css', 'legacy'] }
    };
    
    html2pdf().set(opt).from(element).save().then(function() {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }).catch(function(err) {
        console.error('PDF Error:', err);
        btn.innerHTML = originalText;
        btn.disabled = false;
        alert('Failed to generate PDF. Please try again.');
    });
}

// ESC to close
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        if (window.opener) {
            window.close();
        } else {
            window.location.href = 'view_referral.php?id=<?= $referral_id ?>';
        }
    }
});

console.log('%c📄 Admin - Referral PDF', 'font-size:16px;font-weight:bold;color:#0B5ED7;');
console.log('%c✅ Referral: <?= htmlspecialchars($referral['referral_number'] ?? 'N/A') ?>', 'font-size:12px;color:#64748B;');
console.log('%c✅ Patient: <?= htmlspecialchars($referral['patient_name'] ?? 'N/A') ?>', 'font-size:12px;color:#64748B;');
console.log('%c✅ Logo ya Braick inaonekana', 'font-size:12px;color:#34D399;');
console.log('%c✅ Blue theme everywhere', 'font-size:12px;color:#34D399;');
</script>

</body>
</html>