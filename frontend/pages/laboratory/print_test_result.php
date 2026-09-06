<?php
// ================================================================
// FILE: frontend/pages/laboratory/print_test_result.php
// LABORATORY - PRINT TEST RESULT
// ✅ FIXED: Uses lab_tests.id for matching
// ✅ FIXED: Checks if lab_tests table has data
// ✅ FIXED: Ultrasound form shows data from results column
// ✅ FIXED: Shows all findings
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

$allowed_roles = ['laboratory', 'doctor', 'admin', 'reception', 'cashier'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'User';
$user_role = $_SESSION['role'] ?? 'laboratory';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$result_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($result_id <= 0) {
    die("Invalid result ID");
}

$message = '';
$message_type = '';
$currency = 'TSh';

try {
    // ================================================================
    // GET SYSTEM SETTINGS
    // ================================================================
    $settings = [];
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $currency = $settings['currency'] ?? 'TSh';

    // ================================================================
    // ✅ FIXED: GET LAB TEST - CORRECT COLUMN NAMES
    // ================================================================
    $stmt = $db->prepare("
        SELECT 
            lt.id as lab_test_id,
            lt.visit_id,
            lt.patient_id,
            lt.doctor_id,
            lt.lab_technician_id,
            lt.technician_id,
            lt.test_id as catalog_test_id,
            lt.test_name,
            lt.test_price,
            lt.equipment_used,
            lt.batch_number,
            lt.test_type,
            lt.sample_type,
            lt.test_date,
            lt.results,
            lt.formatted_result,
            lt.reference_range,
            lt.interpretation,
            lt.performed_by,
            lt.status as test_status,
            lt.started_at,
            lt.bill_created,
            lt.branch_id,
            lt.notes,
            lt.created_at,
            lt.completed_at,
            lt.printed_at,
            lt.printed_by,
            lt.updated_at,
            p.full_name as patient_name,
            p.patient_id as patient_number,
            p.phone,
            p.email,
            p.gender,
            p.date_of_birth,
            p.address,
            u.full_name as doctor_name,
            u2.full_name as technician_name,
            v.visit_number,
            v.visit_type,
            v.visit_date
        FROM lab_tests lt
        LEFT JOIN patients p ON lt.patient_id = p.id
        LEFT JOIN visits v ON lt.visit_id = v.id
        LEFT JOIN users u ON lt.doctor_id = u.id
        LEFT JOIN users u2 ON lt.technician_id = u2.id
        WHERE lt.id = ?
    ");
    $stmt->execute([$result_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    // ================================================================
    // ✅ FIXED: Check if result exists in lab_tests
    // ================================================================
    if (!$result) {
        // Check if lab_tests table has any data
        $stmt_check = $db->query("SELECT COUNT(*) as count FROM lab_tests");
        $count = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        if ($count['count'] == 0) {
            die("No test results found in the system. Please complete a test first.");
        }
        
        die("Result not found. Test ID: $result_id does not exist in lab_tests table.");
    }

    // ================================================================
    // PARSE RESULTS - SUPPORTS BOTH JSON AND PLAIN TEXT
    // ================================================================
    $result_data = [];
    $raw_results = $result['results'] ?? '';
    
    // Try to parse as JSON first
    if (!empty($raw_results)) {
        $decoded = json_decode($raw_results, true);
        if (is_array($decoded) && !empty($decoded)) {
            $result_data = $decoded;
        } else {
            // Try to parse as key:value pairs (like "Liver: NORMAL")
            $lines = explode("\n", $raw_results);
            foreach ($lines as $line) {
                $line = trim($line);
                if (strpos($line, ':') !== false) {
                    list($key, $value) = explode(':', $line, 2);
                    $result_data[trim($key)] = trim($value);
                }
            }
        }
    }

    // Also check formatted_result if it has data
    if (!empty($result['formatted_result'])) {
        $formatted = json_decode($result['formatted_result'], true);
        if (is_array($formatted) && !empty($formatted)) {
            foreach ($formatted as $key => $value) {
                if (!isset($result_data[$key]) || empty($result_data[$key])) {
                    $result_data[$key] = $value;
                }
            }
        }
    }

    // ================================================================
    // ✅ FIXED: DETERMINE IF THIS IS ULTRASOUND
    // ================================================================
    $is_ultrasound = false;
    $test_name_lower = strtolower($result['test_name'] ?? '');
    $test_type_lower = strtolower($result['test_type'] ?? '');
    $catalog_test_id = (int)($result['catalog_test_id'] ?? 0);
    
    // Check by name - Ultrasound
    if (stripos($test_name_lower, 'ultrasound') !== false || 
        stripos($test_type_lower, 'ultrasound') !== false) {
        $is_ultrasound = true;
    }
    
    // Check by catalog_test_id (52 = Ultrasound - Transvaginal, etc.)
    if ($catalog_test_id == 52 || $catalog_test_id == 53 || $catalog_test_id == 54) {
        $is_ultrasound = true;
    }
    
    // If test_name contains 'Ultrasound' 
    if (stripos($test_name_lower, 'ultrasound') !== false) {
        $is_ultrasound = true;
    }
    
    // If test_type is 'ultrasound' 
    if ($test_type_lower == 'ultrasound') {
        $is_ultrasound = true;
    }
    
    // ================================================================
    // ✅ FIXED: If ultrasound, try to find template
    // ================================================================
    $template_html = '';
    if ($is_ultrasound) {
        // Try to find matching template
        $stmt_template = $db->prepare("
            SELECT template_html 
            FROM lab_result_templates 
            WHERE category = 'ultrasound' 
            AND is_active = 1
            ORDER BY 
                CASE 
                    WHEN test_type LIKE ? THEN 1
                    WHEN test_type LIKE ? THEN 2
                    WHEN test_type LIKE ? THEN 3
                    ELSE 4
                END
            LIMIT 1
        ");
        
        // Try to match by test type
        $search_term = '%' . $result['test_type'] . '%';
        $search_name = '%' . $result['test_name'] . '%';
        $search_ultrasound = '%Ultrasound%';
        
        $stmt_template->execute([$search_term, $search_name, $search_ultrasound]);
        $template = $stmt_template->fetch(PDO::FETCH_ASSOC);
        
        if ($template) {
            $template_html = $template['template_html'];
        }
    }

} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

// ================================================================
// LOGO PATH
// ================================================================
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

include_once '../../components/laboratory_header.php';
include_once '../../components/laboratory_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Test Result #<?= $result_id ?> - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
    <style>
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-bg: #E8F0FE;
            --success: #059669;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
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
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow: 0 1px 3px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 24px rgba(0,0,0,0.1);
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
        }

        [data-theme="dark"] .ultrasound-report {
            background: #1E293B;
            color: #F1F5F9;
        }
        [data-theme="dark"] .ultrasound-report table td {
            color: #F1F5F9;
        }
        [data-theme="dark"] .ultrasound-report .patient-info {
            background: #2D3748 !important;
        }
        [data-theme="dark"] .ultrasound-report .stamp-box {
            background: #2D3748 !important;
        }
        [data-theme="dark"] .ultrasound-report .report-header h3 {
            color: #F1F5F9;
        }
        [data-theme="dark"] .ultrasound-report h4 {
            color: #6EA8FE;
            border-bottom-color: #6EA8FE;
        }
        [data-theme="dark"] .ultrasound-report table td {
            border-bottom-color: #334155;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        .page-header {
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
        }
        
        .page-header .page-title {
            color: white;
            font-size: 1.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .page-header .page-title i { font-size: 2rem; opacity: 0.9; }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            backdrop-filter: blur(4px);
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
        }
        
        .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }

        .result-card {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 24px 28px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .result-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 6px 20px;
            padding: 10px 16px;
            background: var(--primary-bg);
            border-radius: 10px;
            margin-bottom: 16px;
            border-left: 4px solid var(--primary);
        }
        
        .result-meta .meta-item {
            display: flex;
            flex-direction: column;
        }
        
        .result-meta .meta-item .label {
            font-size: 0.6rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.05em;
        }
        
        .result-meta .meta-item .value {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .result-meta .meta-item .value.primary { color: var(--primary); }
        .result-meta .meta-item .value.success { color: var(--success); }

        /* ================================================================ */
        /* ULTRASOUND FORM STYLES */
        /* ================================================================ */
        .ultrasound-report {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
            font-size: 14px;
            line-height: 1.6;
            color: #333;
        }
        
        .ultrasound-report .report-header {
            text-align: center;
            padding-bottom: 15px;
            border-bottom: 3px solid #0B5ED7;
            margin-bottom: 15px;
        }
        
        .ultrasound-report .report-header h2 {
            color: #0B5ED7;
            font-size: 22px;
            margin: 0;
        }
        
        .ultrasound-report .report-header h3 {
            font-size: 16px;
            color: #333;
            margin: 5px 0 0 0;
        }
        
        .ultrasound-report .report-header p {
            font-size: 11px;
            color: #888;
            margin: 2px 0 0 0;
        }
        
        .ultrasound-report .patient-info {
            margin: 10px 0;
            padding: 8px 12px;
            background: #f8f9fa;
            border-radius: 6px;
            border-left: 4px solid #0B5ED7;
        }
        
        .ultrasound-report .patient-info p {
            margin: 2px 0;
        }
        
        .ultrasound-report h4 {
            color: #0B5ED7;
            border-bottom: 2px solid #0B5ED7;
            padding-bottom: 4px;
            margin-bottom: 8px;
        }
        
        .ultrasound-report table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        
        .ultrasound-report table td {
            padding: 4px 8px;
            border-bottom: 1px solid #ddd;
            vertical-align: middle;
        }
        
        .ultrasound-report table td:first-child {
            width: 35%;
            font-weight: 600;
        }
        
        .ultrasound-report .report-footer {
            margin-top: 15px;
            padding-top: 10px;
            border-top: 2px solid #0B5ED7;
        }
        
        .ultrasound-report .stamp-box {
            text-align: right;
            padding: 8px 16px;
            border: 2px solid #0B5ED7;
            border-radius: 8px;
            background: #f0f7ff;
            min-width: 150px;
        }
        
        .ultrasound-report .stamp-box .stamp-title {
            font-size: 10px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: bold;
        }
        
        .ultrasound-report .stamp-box .stamp-name {
            font-size: 14px;
            font-weight: bold;
            color: #0B5ED7;
            margin-top: 4px;
        }
        
        .ultrasound-report .stamp-box .stamp-line {
            font-size: 10px;
            color: #888;
            border-top: 1px dashed #ccc;
            padding-top: 4px;
            margin-top: 4px;
        }
        
        .ultrasound-report .stamp-box .stamp-date {
            font-size: 9px;
            color: #999;
            margin-top: 2px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 18px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.82rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3); }
        
        .btn-success { background: var(--success); color: white; }
        .btn-success:hover { background: #047857; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3); }
        
        .btn-outline { background: transparent; color: var(--text-secondary); border: 2px solid var(--border-color); }
        .btn-outline:hover { background: var(--bg-body); border-color: var(--primary); color: var(--primary); }
        
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        .footer .footer-brand { color: var(--success); font-weight: 600; }
        
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 12px 18px;
            border-radius: 12px;
            z-index: 999;
            max-width: 380px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            box-shadow: var(--shadow-lg);
        }
        .toast-custom.show { transform: translateY(0); opacity: 1; }
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        
        @media print {
            .main-content { margin-left: 0 !important; padding: 10px !important; }
            .page-header { background: #0B5ED7 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .no-print { display: none !important; }
            .result-card { box-shadow: none !important; border: 1px solid #ddd !important; }
            .ultrasound-report { background: white !important; color: #333 !important; }
            .ultrasound-report .patient-info { background: #f8f9fa !important; }
            .ultrasound-report .stamp-box { background: #f0f7ff !important; }
        }
        
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .result-card { padding: 16px; }
            .result-meta { grid-template-columns: 1fr 1fr; }
            .ultrasound-report { padding: 16px; }
            .ultrasound-report table td { display: block; width: 100% !important; padding: 3px 4px; }
            .ultrasound-report table td:first-child { width: 100% !important; }
        }
        
        @media (max-width: 480px) {
            .result-meta { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-file-medical-alt"></i>
                Test Result
                <span class="role-badge-display">#<?= $result_id ?></span>
                <?php if ($is_ultrasound): ?>
                    <span class="header-badge" style="background:rgba(52,211,153,0.3);border-color:rgba(52,211,153,0.3);">
                        <i class="fas fa-stethoscope"></i> Ultrasound
                    </span>
                <?php endif; ?>
                <span class="header-badge" style="background:rgba(255,255,255,0.1);border-color:rgba(255,255,255,0.1);">
                    <i class="fas fa-tag"></i> Test ID: <?= htmlspecialchars($result['catalog_test_id'] ?? 'N/A') ?>
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-user"></i>
                <?= htmlspecialchars($result['patient_name'] ?? 'Unknown Patient') ?>
                <span style="opacity:0.6;">|</span>
                <span><?= htmlspecialchars($result['test_name'] ?? 'N/A') ?></span>
                <span style="opacity:0.6;">|</span>
                <span style="font-size:0.75rem;opacity:0.7;">Status: <?= ucfirst($result['test_status'] ?? 'Pending') ?></span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;" class="no-print">
            <button onclick="window.print()" class="btn-outline-light">
                <i class="fas fa-print"></i> Print
            </button>
            <button onclick="downloadPDF()" class="btn-outline-light" style="background:rgba(220,38,38,0.2);border-color:rgba(220,38,38,0.3);">
                <i class="fas fa-file-pdf"></i> PDF
            </button>
            <a href="completed_tests.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- RESULT CARD -->
    <div class="result-card" id="resultCard">
        
        <!-- META INFO -->
        <div class="result-meta">
            <div class="meta-item">
                <span class="label">Patient Name</span>
                <span class="value primary"><?= htmlspecialchars($result['patient_name'] ?? 'Unknown') ?></span>
            </div>
            <div class="meta-item">
                <span class="label">Patient ID</span>
                <span class="value"><?= htmlspecialchars($result['patient_number'] ?? 'N/A') ?></span>
            </div>
            <div class="meta-item">
                <span class="label">Gender / Age</span>
                <span class="value"><?= htmlspecialchars($result['gender'] ?? 'N/A') ?> / <?= $result['date_of_birth'] ? date_diff(date_create($result['date_of_birth']), date_create('today'))->y . ' yrs' : 'N/A' ?></span>
            </div>
            <div class="meta-item">
                <span class="label">Test Name</span>
                <span class="value success"><?= htmlspecialchars($result['test_name'] ?? 'N/A') ?></span>
            </div>
            <div class="meta-item">
                <span class="label">Test ID</span>
                <span class="value" style="color:var(--purple);">#<?= htmlspecialchars($result['catalog_test_id'] ?? 'N/A') ?></span>
            </div>
            <div class="meta-item">
                <span class="label">Doctor</span>
                <span class="value">Dr. <?= htmlspecialchars($result['doctor_name'] ?? 'N/A') ?></span>
            </div>
            <div class="meta-item">
                <span class="label">Completed Date</span>
                <span class="value"><?= date('d/m/Y h:i A', strtotime($result['completed_at'] ?? 'now')) ?></span>
            </div>
        </div>

        <!-- ================================================================ -->
        <!-- ULTRASOUND FORM WITH DATA -->
        <!-- ================================================================ -->
        <?php if ($is_ultrasound && !empty($result['results'])): ?>
            <?php if (!empty($template_html)): ?>
                <!-- Use template from database -->
                <?php 
                    // Replace placeholders with actual data
                    $template_output = $template_html;
                    $template_output = str_replace('{patient_name}', htmlspecialchars($result['patient_name'] ?? 'Unknown'), $template_output);
                    $template_output = str_replace('{patient_id}', htmlspecialchars($result['patient_number'] ?? 'N/A'), $template_output);
                    $template_output = str_replace('{age}', $result['date_of_birth'] ? date_diff(date_create($result['date_of_birth']), date_create('today'))->y : 'N/A', $template_output);
                    $template_output = str_replace('{gender}', htmlspecialchars($result['gender'] ?? 'N/A'), $template_output);
                    $template_output = str_replace('{exam_date}', date('d/m/Y', strtotime($result['completed_at'] ?? 'now')), $template_output);
                    $template_output = str_replace('{report_date}', date('d/m/Y'), $template_output);
                    
                    // Fill findings from result_data
                    foreach ($result_data as $key => $value) {
                        if (!empty($value)) {
                            $template_output = str_replace('data-placeholder="' . $key . '"', 'value="' . htmlspecialchars($value) . '"', $template_output);
                            $template_output = str_replace('data-placeholder="' . $key . '"', 'value="' . htmlspecialchars($value) . '"', $template_output);
                        }
                    }
                    
                    // Fill conclusion
                    $conclusion = '';
                    foreach (['conclusion', 'impression', 'interpretation'] as $key) {
                        if (isset($result_data[$key]) && !empty($result_data[$key])) {
                            $conclusion = $result_data[$key];
                            break;
                        }
                    }
                    if (!empty($conclusion)) {
                        $template_output = str_replace('class="form-control conclusion-field"', 'class="form-control conclusion-field" style="background:#f8f9fa;"', $template_output);
                        $template_output = str_replace('<textarea', '<textarea value="' . htmlspecialchars($conclusion) . '"', $template_output);
                        $template_output = str_replace('</textarea>', htmlspecialchars($conclusion) . '</textarea>', $template_output);
                    }
                    
                    echo $template_output;
                ?>
            <?php else: ?>
                <!-- Fallback: Show ultrasound data in table format -->
                <div class="ultrasound-report">
                    <div class="report-header">
                        <div style="display:flex;align-items:center;justify-content:center;gap:15px;margin-bottom:10px;">
                            <img src="<?= $logo_path ?>" alt="Braick Dispensary" style="height:60px;width:auto;max-height:60px;" onerror="this.style.display='none'">
                            <div>
                                <h2 style="color:#0B5ED7;font-size:22px;margin:0;">BRAICK DISPENSARY</h2>
                                <p style="font-size:12px;color:#666;margin:0;">Quality Healthcare Services</p>
                            </div>
                        </div>
                        <h3 style="font-size:16px;color:#333;margin:0;">ULTRASOUND REPORT</h3>
                        <p style="font-size:11px;color:#888;margin:2px 0 0 0;"><?= htmlspecialchars($result['test_name'] ?? 'Ultrasound') ?></p>
                    </div>
                    
                    <div class="patient-info">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:2px 20px;">
                            <p><strong>Patient Name:</strong> <?= htmlspecialchars($result['patient_name'] ?? 'Unknown') ?></p>
                            <p><strong>Age/Sex:</strong> <?= $result['date_of_birth'] ? date_diff(date_create($result['date_of_birth']), date_create('today'))->y . ' yrs' : 'N/A' ?> / <?= htmlspecialchars($result['gender'] ?? 'N/A') ?></p>
                            <p><strong>Date of Exam:</strong> <?= date('d/m/Y', strtotime($result['completed_at'] ?? 'now')) ?></p>
                            <p><strong>Patient ID:</strong> <?= htmlspecialchars($result['patient_number'] ?? 'N/A') ?></p>
                            <p><strong>Report Date:</strong> <?= date('d/m/Y') ?></p>
                            <p><strong>Test ID:</strong> #<?= htmlspecialchars($result['catalog_test_id'] ?? 'N/A') ?></p>
                        </div>
                    </div>
                    
                    <div style="margin:10px 0;">
                        <h4 style="color:#0B5ED7;border-bottom:2px solid #0B5ED7;padding-bottom:4px;margin-bottom:8px;">FINDINGS</h4>
                        <table style="width:100%;border-collapse:collapse;font-size:14px;">
                            <tbody>
                                <?php 
                                $has_findings = false;
                                foreach ($result_data as $key => $value) {
                                    if (empty($value)) continue;
                                    if (in_array(strtolower($key), ['conclusion', 'impression', 'interpretation'])) continue;
                                    $display_key = ucwords(str_replace('_', ' ', $key));
                                    $has_findings = true;
                                    echo '<tr>';
                                    echo '<td style="padding:4px 8px;border-bottom:1px solid #ddd;font-weight:600;">' . htmlspecialchars($display_key) . '</td>';
                                    echo '<td style="padding:4px 8px;border-bottom:1px solid #ddd;">' . htmlspecialchars($value) . '</td>';
                                    echo '</tr>';
                                }
                                
                                if (!$has_findings) {
                                    echo '<tr><td colspan="2" style="padding:10px;text-align:center;color:#999;">No findings recorded</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div style="margin:10px 0;">
                        <h4 style="color:#0B5ED7;border-bottom:2px solid #0B5ED7;padding-bottom:4px;margin-bottom:8px;">IMPRESSION / CONCLUSION</h4>
                        <div style="padding:8px;border:1px solid #ddd;border-radius:4px;min-height:60px;background:#f9f9f9;">
                            <?php 
                                $conclusion = '';
                                foreach (['conclusion', 'impression', 'interpretation'] as $key) {
                                    if (isset($result_data[$key]) && !empty($result_data[$key])) {
                                        $conclusion = $result_data[$key];
                                        break;
                                    }
                                }
                                echo htmlspecialchars($conclusion ?: 'No conclusion provided.');
                            ?>
                        </div>
                    </div>
                    
                    <div class="report-footer">
                        <div style="display:flex;justify-content:space-between;align-items:center;width:100%;flex-wrap:wrap;">
                            <div>
                                <span>Technician: <strong><?= htmlspecialchars($result['technician_name'] ?? $result['performed_by'] ?? 'N/A') ?></strong></span>
                                <span style="margin-left:20px;">Date: <?= date('d/m/Y') ?></span>
                            </div>
                            <div class="stamp-box">
                                <div class="stamp-title">Official Stamp</div>
                                <div class="stamp-name">BRAICK DISPENSARY</div>
                                <div class="stamp-line">Approved By: _________________</div>
                                <div class="stamp-date">Date: <?= date('d/m/Y') ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        <?php elseif (!empty($result['results']) || !empty($result_data)): ?>
            <!-- OTHER TEST TYPE - SHOW TABLE -->
            <div style="padding:20px;">
                <h4 style="color:var(--primary);margin-bottom:12px;">Test Results</h4>
                <?php if (!empty($result_data) && is_array($result_data)): ?>
                    <table style="width:100%;border-collapse:collapse;font-size:14px;">
                        <thead>
                            <tr>
                                <th style="padding:8px 12px;border-bottom:2px solid var(--border-color);text-align:left;font-weight:600;">Parameter</th>
                                <th style="padding:8px 12px;border-bottom:2px solid var(--border-color);text-align:left;font-weight:600;">Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($result_data as $key => $value): ?>
                                <tr>
                                    <td style="padding:6px 12px;border-bottom:1px solid var(--border-color);font-weight:500;"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $key))) ?></td>
                                    <td style="padding:6px 12px;border-bottom:1px solid var(--border-color);"><?= htmlspecialchars($value) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php elseif (!empty($result['results'])): ?>
                    <div style="padding:10px;background:var(--gray-50);border-radius:5px;border:1px solid var(--border-color);">
                        <?= nl2br(htmlspecialchars($result['results'])) ?>
                    </div>
                <?php else: ?>
                    <p style="color:var(--text-secondary);">No result data available</p>
                <?php endif; ?>
                
                <?php if (!empty($result['reference_range'])): ?>
                    <div style="margin-top:10px;padding:8px;background:var(--primary-bg);border-radius:5px;border-left:3px solid var(--primary);">
                        <strong>Reference Range:</strong> <?= htmlspecialchars($result['reference_range']) ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($result['interpretation'])): ?>
                    <div style="margin-top:10px;padding:8px;background:var(--warning-bg);border-radius:5px;border-left:3px solid var(--warning);">
                        <strong>Interpretation:</strong> <?= htmlspecialchars($result['interpretation']) ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div style="padding:40px;text-align:center;color:var(--text-secondary);">
                <i class="fas fa-file-medical-alt" style="font-size:3rem;display:block;margin-bottom:12px;color:var(--border-color);"></i>
                <h3>No Results Available</h3>
                <p>This test does not have any results recorded yet.</p>
            </div>
        <?php endif; ?>
        
    </div>

    <!-- FOOTER -->
    <footer class="footer no-print">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Test Result #<?= $result_id ?>
            <span class="text-gray-300 mx-2">|</span>
            <span style="color:#0B5ED7;font-weight:600;">
                👤 <?= htmlspecialchars($user_full_name) ?>
            </span>
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- TOAST -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle"></i>
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
    // DARK MODE
    // ================================================================
    (function() {
        var htmlElement = document.documentElement;
        var isDark = localStorage.getItem('darkMode') === 'true';
        if (isDark) {
            htmlElement.setAttribute('data-theme', 'dark');
        }
    })();

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
        
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 1024) {
                if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                    sidebar.classList.remove('open');
                }
            }
        });
    }

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
    }
    updateFooterTime();
    setInterval(updateFooterTime, 1000);

    // ================================================================
    // DOWNLOAD PDF
    // ================================================================
    function downloadPDF() {
        var element = document.getElementById('resultCard');
        var opt = {
            margin: [8, 8, 8, 8],
            filename: 'Test_Result_<?= $result_id ?>_<?= date('Y-m-d') ?>.pdf',
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
            pagebreak: { 
                mode: ['css', 'legacy']
            }
        };
        
        html2pdf().set(opt).from(element).save();
    }

    console.log('%c🔬 Braick - Print Test Result (FIXED)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Result ID: <?= $result_id ?>', 'font-size:13px; color:#64748B;');
    console.log('%c🧪 Test Name: <?= htmlspecialchars($result['test_name'] ?? 'N/A') ?>', 'font-size:13px; color:#059669;');
    console.log('%c🏷️ Catalog Test ID: <?= htmlspecialchars($result['catalog_test_id'] ?? 'N/A') ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c📊 Data fields found: <?= count($result_data) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📝 Raw results: <?= htmlspecialchars(substr($result['results'] ?? '', 0, 100)) ?>', 'font-size:13px; color:#64748B;');
    <?php if ($is_ultrasound): ?>
    console.log('%c🩺 ULTRASOUND FORM LOADED WITH DATA', 'font-size:13px; color:#0B5ED7; font-weight:bold;');
    <?php endif; ?>
</script>

</body>
</html>