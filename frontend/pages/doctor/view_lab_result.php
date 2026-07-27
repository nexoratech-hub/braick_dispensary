<?php
// ================================================================
// FILE: frontend/pages/doctor/view_lab_result.php
// DOCTOR - VIEW LAB RESULT
// - Display complete lab result details in a clean layout
// - Printable format with Braick logo
// - HORIZONTAL patient information with 2 fields per row
// - HORIZONTAL test information with 2 fields per row
// - RESULT BOX only shows filled results (no empty fields)
// - Print optimized
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
// GET LAB RESULT DETAILS
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT 
            lt.*,
            p.full_name as patient_name,
            p.patient_id as patient_code,
            p.phone,
            p.gender,
            p.date_of_birth,
            p.address,
            p.blood_group,
            p.allergies,
            p.emergency_contact,
            u.full_name as doctor_name,
            u.specialty as doctor_specialty,
            v.visit_number,
            v.visit_type,
            v.created_at as visit_date,
            v.symptoms,
            v.diagnosis,
            (SELECT full_name FROM users WHERE id = lt.lab_technician_id) as technician_name,
            b.name as branch_name
        FROM lab_tests lt
        JOIN visits v ON lt.visit_id = v.id
        JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON v.doctor_id = u.id
        LEFT JOIN branches b ON v.branch_id = b.id
        WHERE lt.id = ? AND v.branch_id = ?
    ");
    $stmt->execute([$lab_test_id, $doctor_branch_id]);
    $lab_result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$lab_result) {
        header('Location: lab_results.php?error=not_found');
        exit;
    }
} catch (Exception $e) {
    error_log("Lab result fetch error: " . $e->getMessage());
    header('Location: lab_results.php?error=database_error');
    exit;
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
// GET STATUS LABEL
// ================================================================
function getStatusLabel($status) {
    $map = [
        'pending' => 'Pending',
        'in_progress' => 'In Progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled'
    ];
    return $map[$status] ?? ucfirst($status);
}

// ================================================================
// GET STATUS BADGE CLASS
// ================================================================
function getStatusBadgeClass($status) {
    $map = [
        'pending' => 'badge-warning',
        'in_progress' => 'badge-info',
        'completed' => 'badge-success',
        'cancelled' => 'badge-danger'
    ];
    return $map[$status] ?? 'badge-info';
}

// ================================================================
// FORMAT RESULT FOR DISPLAY
// ================================================================
function formatResult($result) {
    if (empty($result)) return null;
    
    $result = htmlspecialchars($result);
    
    $patterns = [
        '/\b(Positive|Reactive|Detected|Abnormal|Elevated|High|Increased)\b/i' => '<span class="result-positive">$1</span>',
        '/\b(Negative|Non-reactive|Not Detected|Normal|Low|Within Range|Decreased)\b/i' => '<span class="result-negative">$1</span>',
        '/\b(Borderline|Equivocal|Indeterminate)\b/i' => '<span class="result-borderline">$1</span>',
        '/(\d+\.?\d*\s*(?:mg\/dL|g\/dL|mmol\/L|µmol\/L|ng\/mL|pg\/mL|cells\/mm³|%|mm|cm|kg|lbs|°C|°F)?)/i' => '<span class="result-number">$1</span>'
    ];
    
    foreach ($patterns as $pattern => $replacement) {
        $result = preg_replace($pattern, $replacement, $result);
    }
    
    return $result;
}

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/doctor_header.php';
include_once __DIR__ . '/../../components/doctor_sidebar.php';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$formatted_result = formatResult($lab_result['results'] ?? '');
$interpretation = 'Normal';
if (!empty($lab_result['results'])) {
    $result_lower = strtolower($lab_result['results']);
    if (preg_match('/positive|reactive|detected|abnormal|elevated|high/', $result_lower)) {
        $interpretation = '<span class="result-positive">Abnormal / Positive</span>';
    } elseif (preg_match('/negative|non-reactive|not detected|normal|low|within range/', $result_lower)) {
        $interpretation = '<span class="result-negative">Normal / Negative</span>';
    } elseif (preg_match('/borderline|equivocal|indeterminate/', $result_lower)) {
        $interpretation = '<span class="result-borderline">Borderline / Equivocal</span>';
    }
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Result - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    
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
           RESULT CARD
           ================================================================ */
        .result-card {
            background: var(--bg-card);
            border-radius: 14px;
            border: 1px solid var(--border-color);
            overflow: hidden;
            margin-bottom: 24px;
            box-shadow: var(--shadow);
        }
        
        .result-card .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            background: var(--gray-50);
        }
        
        [data-theme="dark"] .result-card .card-header {
            background: var(--gray-700);
        }
        
        .result-card .card-header .test-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .result-card .card-body {
            padding: 24px;
        }
        
        /* ================================================================
           INFO GRID - HORIZONTAL LAYOUT WITH 2 FIELDS PER ROW
           ================================================================ */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px 30px;
        }
        
        .info-grid .info-item {
            display: flex;
            align-items: center;
            padding: 6px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .info-grid .info-item:last-child {
            border-bottom: none;
        }
        
        .info-grid .info-item .label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            min-width: 120px;
            flex-shrink: 0;
        }
        
        .info-grid .info-item .value {
            font-size: 0.9rem;
            color: var(--text-primary);
            font-weight: 500;
        }
        
        .info-grid .info-item .value.highlight {
            color: var(--primary);
            font-weight: 600;
        }
        
        .info-grid .info-item .value .badge {
            font-size: 0.7rem;
        }
        
        /* ================================================================
           SECTION HEADERS
           ================================================================ */
        .section-divider {
            margin: 24px 0 16px 0;
            padding-top: 16px;
            border-top: 2px solid var(--border-color);
        }
        
        .section-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title i {
            font-size: 1rem;
        }
        
        /* ================================================================
           RESULT SECTION - ONLY SHOWS FILLED RESULTS
           ================================================================ */
        .result-section {
            margin-top: 8px;
        }
        
        .result-container {
            background: var(--gray-50);
            border-radius: 12px;
            border: 2px solid var(--primary-light);
            padding: 0;
            overflow: hidden;
        }
        
        [data-theme="dark"] .result-container {
            background: var(--gray-700);
            border-color: var(--primary);
        }
        
        .result-container .result-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            border-bottom: 1px solid var(--border-color);
        }
        
        .result-container .result-row:last-child {
            border-bottom: none;
        }
        
        .result-container .result-row .result-field {
            padding: 14px 20px;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .result-container .result-row .result-field:first-child {
            border-right: 1px solid var(--border-color);
        }
        
        .result-container .result-row .result-field .field-label {
            font-size: 0.65rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .result-container .result-row .result-field .field-value {
            font-size: 1rem;
            line-height: 1.6;
            color: var(--text-primary);
            white-space: pre-wrap;
            word-break: break-word;
        }
        
        .result-container .result-row .result-field .field-value .result-positive {
            color: #059669;
            font-weight: 700;
            background: rgba(5, 150, 105, 0.12);
            padding: 1px 8px;
            border-radius: 4px;
            display: inline-block;
        }
        
        [data-theme="dark"] .result-container .result-row .result-field .field-value .result-positive {
            background: rgba(52, 211, 153, 0.2);
            color: #34D399;
        }
        
        .result-container .result-row .result-field .field-value .result-negative {
            color: #DC2626;
            font-weight: 700;
            background: rgba(220, 38, 38, 0.12);
            padding: 1px 8px;
            border-radius: 4px;
            display: inline-block;
        }
        
        [data-theme="dark"] .result-container .result-row .result-field .field-value .result-negative {
            background: rgba(248, 113, 113, 0.2);
            color: #F87171;
        }
        
        .result-container .result-row .result-field .field-value .result-borderline {
            color: #D97706;
            font-weight: 700;
            background: rgba(217, 119, 6, 0.12);
            padding: 1px 8px;
            border-radius: 4px;
            display: inline-block;
        }
        
        [data-theme="dark"] .result-container .result-row .result-field .field-value .result-borderline {
            background: rgba(251, 191, 36, 0.2);
            color: #FBBF24;
        }
        
        .result-container .result-row .result-field .field-value .result-number {
            background: var(--primary-bg);
            padding: 1px 6px;
            border-radius: 4px;
            font-weight: 600;
            color: var(--primary);
            display: inline-block;
        }
        
        [data-theme="dark"] .result-container .result-row .result-field .field-value .result-number {
            background: #1E3A5F;
            color: #6EA8FE;
        }
        
        .result-container .result-row .result-field .field-value .no-result {
            color: var(--text-secondary);
            font-style: italic;
        }
        
        /* Full width row for reference range */
        .result-container .result-row.full-width {
            grid-template-columns: 1fr;
        }
        
        .result-container .result-row.full-width .result-field {
            border-right: none !important;
        }
        
        .result-container .result-row.highlight-row {
            background: var(--primary-bg);
        }
        
        [data-theme="dark"] .result-container .result-row.highlight-row {
            background: #1E3A5F;
        }
        
        /* ================================================================
           NOTES
           ================================================================ */
        .notes-section {
            margin-top: 16px;
            padding: 16px 20px;
            background: var(--gray-50);
            border-radius: 10px;
            border: 1px solid var(--border-color);
        }
        
        [data-theme="dark"] .notes-section {
            background: var(--gray-700);
        }
        
        .notes-section .notes-label {
            font-size: 0.65rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .notes-section .notes-value {
            font-size: 0.9rem;
            color: var(--text-primary);
            margin-top: 4px;
        }
        
        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
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
            .info-grid { grid-template-columns: 1fr; }
            .info-grid .info-item { flex-direction: column; align-items: flex-start; gap: 2px; }
            .info-grid .info-item .label { min-width: auto; }
            .result-card .card-header { flex-direction: column; align-items: flex-start; }
            .result-card .card-body { padding: 16px; }
            .result-container .result-row { grid-template-columns: 1fr; }
            .result-container .result-row .result-field:first-child { border-right: none; border-bottom: 1px solid var(--border-color); }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .result-card .card-body { padding: 12px; }
            .result-container .result-row .result-field { padding: 10px 14px; }
            .result-container .result-row .result-field .field-value { font-size: 0.9rem; }
            .info-grid .info-item .value { font-size: 0.85rem; }
        }
        
        /* ================================================================
           PRINT STYLES
           ================================================================ */
        @media print {
            .top-nav { display: none !important; }
            .sidebar { display: none !important; }
            .main-content { margin-left: 0 !important; margin-top: 0 !important; padding: 20px !important; }
            .page-header-custom { background: #0B5ED7 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .page-header-custom .page-title { color: white !important; }
            .page-header-custom .page-subtitle { color: rgba(255,255,255,0.85) !important; }
            .btn-outline-light { display: none !important; }
            .no-print { display: none !important; }
            .result-card { border: 1px solid #ddd !important; box-shadow: none !important; }
            .badge { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .result-container { background: #f5f5f5 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; border-color: #0B5ED7 !important; }
            .result-container .result-row .result-field .field-value .result-positive { background: rgba(5, 150, 105, 0.15) !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .result-container .result-row .result-field .field-value .result-negative { background: rgba(220, 38, 38, 0.15) !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .result-container .result-row .result-field .field-value .result-number { background: rgba(11, 94, 215, 0.15) !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .result-container .result-row.highlight-row { background: #E8F0FE !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .notes-section { background: #f5f5f5 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .result-card .card-header { background: #f5f5f5 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .footer { border-top: 1px solid #ddd !important; }
            .info-grid .info-item { border-bottom: 1px solid #ddd !important; }
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
                <i class="fas fa-flask"></i>
                Lab Result
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;">DOCTOR</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-file-medical-alt"></i>
                View complete lab result details
                
                <span class="header-badge" style="background:rgba(255,255,255,0.15);color:white;padding:4px 14px;border-radius:20px;font-size:0.7rem;border:1px solid rgba(255,255,255,0.1);">
                    <i class="fas fa-vial"></i>
                    <?= htmlspecialchars($lab_result['test_name'] ?? 'N/A') ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="lab_results.php" class="btn-outline-light no-print">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <button onclick="window.print()" class="btn-outline-light no-print">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="consultation.php?visit_id=<?= $lab_result['visit_id'] ?>" class="btn-outline-light no-print">
                <i class="fas fa-stethoscope"></i> Consultation
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RESULT CARD -->
    <!-- ================================================================ -->
    <div class="result-card">
        <div class="card-header">
            <div>
                <div class="test-title"><?= htmlspecialchars($lab_result['test_name'] ?? 'N/A') ?></div>
                <div style="font-size:0.8rem;color:var(--text-secondary);">
                    <i class="fas fa-file-medical"></i> <?= htmlspecialchars($lab_result['visit_number'] ?? 'N/A') ?>
                    <span class="mx-1">•</span>
                    <i class="fas fa-user"></i> <?= htmlspecialchars($lab_result['patient_name'] ?? 'N/A') ?>
                    <span class="mx-1">•</span>
                    <i class="fas fa-calendar"></i> <?= date('M d, Y', strtotime($lab_result['created_at'])) ?>
                </div>
            </div>
            <div>
                <span class="badge <?= getStatusBadgeClass($lab_result['status']) ?>">
                    <?= getStatusLabel($lab_result['status']) ?>
                </span>
            </div>
        </div>
        
        <div class="card-body">
            
            <!-- ================================================================ -->
            <!-- PATIENT INFORMATION - 2 FIELDS PER ROW -->
            <!-- ================================================================ -->
            <div class="section-title">
                <i class="fas fa-user-circle"></i> Patient Information
            </div>
            <div class="info-grid">
                <div class="info-item">
                    <span class="label">Patient Name</span>
                    <span class="value highlight"><?= htmlspecialchars($lab_result['patient_name'] ?? 'N/A') ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Patient ID</span>
                    <span class="value"><?= htmlspecialchars($lab_result['patient_code'] ?? 'N/A') ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Gender</span>
                    <span class="value"><?= htmlspecialchars($lab_result['gender'] ?? 'N/A') ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Age</span>
                    <span class="value"><?= calculateAge($lab_result['date_of_birth'] ?? '') ?> years</span>
                </div>
                <div class="info-item">
                    <span class="label">Phone</span>
                    <span class="value"><?= htmlspecialchars($lab_result['phone'] ?? 'N/A') ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Blood Group</span>
                    <span class="value"><?= htmlspecialchars($lab_result['blood_group'] ?? 'N/A') ?></span>
                </div>
                <?php if (!empty($lab_result['allergies'])): ?>
                    <div class="info-item" style="grid-column: 1 / -1;">
                        <span class="label">Allergies</span>
                        <span class="value" style="color:var(--danger);"><?= htmlspecialchars($lab_result['allergies']) ?></span>
                    </div>
                <?php endif; ?>
                <div class="info-item" style="grid-column: 1 / -1;">
                    <span class="label">Address</span>
                    <span class="value"><?= htmlspecialchars($lab_result['address'] ?? 'N/A') ?></span>
                </div>
            </div>

            <!-- ================================================================ -->
            <!-- TEST INFORMATION - 2 FIELDS PER ROW -->
            <!-- ================================================================ -->
            <div class="section-divider">
                <div class="section-title">
                    <i class="fas fa-vial"></i> Test Information
                </div>
            </div>
            <div class="info-grid">
                <div class="info-item">
                    <span class="label">Test Name</span>
                    <span class="value highlight"><?= htmlspecialchars($lab_result['test_name'] ?? 'N/A') ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Status</span>
                    <span class="value">
                        <span class="badge <?= getStatusBadgeClass($lab_result['status']) ?>">
                            <?= getStatusLabel($lab_result['status']) ?>
                        </span>
                    </span>
                </div>
                <div class="info-item">
                    <span class="label">Visit Number</span>
                    <span class="value"><?= htmlspecialchars($lab_result['visit_number'] ?? 'N/A') ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Visit Type</span>
                    <span class="value"><?= ucfirst(htmlspecialchars($lab_result['visit_type'] ?? 'N/A')) ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Doctor</span>
                    <span class="value">Dr. <?= htmlspecialchars($lab_result['doctor_name'] ?? 'N/A') ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Specialty</span>
                    <span class="value"><?= htmlspecialchars($lab_result['doctor_specialty'] ?? 'N/A') ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Lab Technician</span>
                    <span class="value"><?= htmlspecialchars($lab_result['technician_name'] ?? 'Not Assigned') ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Branch</span>
                    <span class="value"><?= htmlspecialchars($lab_result['branch_name'] ?? 'N/A') ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Requested Date</span>
                    <span class="value"><?= date('M d, Y h:i A', strtotime($lab_result['created_at'])) ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Completed Date</span>
                    <span class="value">
                        <?php if (!empty($lab_result['completed_at'])): ?>
                            <?= date('M d, Y h:i A', strtotime($lab_result['completed_at'])) ?>
                        <?php else: ?>
                            <span class="text-gray-400">Not completed yet</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>

            <!-- ================================================================ -->
            <!-- RESULT - ONLY SHOWS FILLED RESULTS -->
            <!-- ================================================================ -->
            <div class="section-divider">
                <div class="section-title">
                    <i class="fas fa-file-alt"></i> Result
                </div>
            </div>
            <div class="result-section">
                <div class="result-container">
                    
                    <?php if (!empty($formatted_result)): ?>
                        <!-- Row 1: Result Value -->
                        <div class="result-row highlight-row">
                            <div class="result-field">
                                <span class="field-label"><i class="fas fa-check-circle"></i> Result</span>
                                <span class="field-value"><?= nl2br($formatted_result) ?></span>
                            </div>
                            <div class="result-field">
                                <span class="field-label"><i class="fas fa-flag"></i> Interpretation</span>
                                <span class="field-value"><?= $interpretation ?></span>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- No Result -->
                        <div class="result-row full-width highlight-row">
                            <div class="result-field">
                                <span class="field-label"><i class="fas fa-info-circle"></i> Status</span>
                                <span class="field-value" style="font-style:italic;color:var(--text-secondary);">
                                    No result available yet. Test is still <?= strtolower(getStatusLabel($lab_result['status'])) ?>.
                                </span>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Reference Range (if available) -->
                    <?php if (!empty($lab_result['reference_range'])): ?>
                        <div class="result-row full-width">
                            <div class="result-field">
                                <span class="field-label"><i class="fas fa-arrows-left-right"></i> Reference Range</span>
                                <span class="field-value"><?= htmlspecialchars($lab_result['reference_range']) ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Technician & Completed Date (if available) -->
                    <?php if (!empty($lab_result['technician_name']) || !empty($lab_result['completed_at'])): ?>
                        <div class="result-row">
                            <div class="result-field">
                                <span class="field-label"><i class="fas fa-user-md"></i> Technician</span>
                                <span class="field-value"><?= htmlspecialchars($lab_result['technician_name'] ?? '—') ?></span>
                            </div>
                            <div class="result-field">
                                <span class="field-label"><i class="fas fa-calendar-check"></i> Completed</span>
                                <span class="field-value">
                                    <?php if (!empty($lab_result['completed_at'])): ?>
                                        <?= date('M d, Y h:i A', strtotime($lab_result['completed_at'])) ?>
                                    <?php else: ?>
                                        <span style="color:var(--text-secondary);font-style:italic;">Not completed yet</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Notes (if available) -->
                    <?php if (!empty($lab_result['notes'])): ?>
                        <div class="result-row full-width">
                            <div class="result-field">
                                <span class="field-label"><i class="fas fa-sticky-note"></i> Notes</span>
                                <span class="field-value"><?= nl2br(htmlspecialchars($lab_result['notes'])) ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                </div>
            </div>
            
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Lab Result
            <span class="text-gray-300 mx-2">|</span>
            <?= htmlspecialchars($lab_result['test_name'] ?? 'N/A') ?>
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

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            window.location.href = 'lab_results.php';
        }
    });

    console.log('%c🧪 Braick - View Lab Result (Redesigned Layout)', 'font-size:18px; font-weight:bold; color:#7C3AED;');
    console.log('%c📋 Test: <?= htmlspecialchars($lab_result['test_name'] ?? 'N/A') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c👤 Patient: <?= htmlspecialchars($lab_result['patient_name'] ?? 'N/A') ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Status: <?= getStatusLabel($lab_result['status']) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c✅ Patient & Test info: 2 fields per row', 'font-size:13px; color:#34D399;');
    console.log('%c📦 Result box: Only shows filled results', 'font-size:13px; color:#34D399;');
    console.log('%c🖨️ Print optimized', 'font-size:13px; color:#64748B;');
</script>

</body>
</html>