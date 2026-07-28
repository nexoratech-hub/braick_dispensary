<?php
// ================================================================
// FILE: frontend/pages/doctor/services.php
// SERVICES MANAGEMENT - SINGLE PAGE WITH TABS
// Procedures, Tools, Lab Tests
// WITH BRANCH FILTERING - NO EDIT/DELETE BUTTONS
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
    $_SESSION['specialty'] = 'General Medicine';
    $_SESSION['profile_pic'] = '';
    $_SESSION['is_online'] = 1;
}

$doctor_id = $_SESSION['user_id'] ?? 5;
$doctor_name = $_SESSION['full_name'] ?? 'Dr. John Mushi';
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;

// ================================================================
// GET BRANCH NAME
// ================================================================
$branch_name = 'Main Branch';
try {
    require_once __DIR__ . '/../../../backend/config/database.php';
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ?");
    $stmt->execute([$doctor_branch_id]);
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch) {
        $branch_name = $branch['name'];
    }
} catch (Exception $e) {
    $branch_name = 'Branch';
}

// ================================================================
// GET ACTIVE TAB
// ================================================================
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'procedures';

// ================================================================
// HANDLE FORM SUBMISSIONS - ADD ONLY (NO EDIT/DELETE)
// ================================================================
$message = '';
$message_type = '';

// ================================================================
// FUNCTION TO GENERATE PROCEDURE CODE AUTOMATICALLY
// ================================================================
function generateProcedureCode($db, $branch_id) {
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) as count FROM procedures WHERE branch_id = ? OR branch_id IS NULL
        ");
        $stmt->execute([$branch_id]);
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        $next_num = str_pad($count + 1, 3, '0', STR_PAD_LEFT);
        return 'PROC-' . date('Y') . '-' . $next_num;
    } catch (Exception $e) {
        return 'PROC-' . date('Ymd') . '-' . rand(100, 999);
    }
}

// ================================================================
// ADD PROCEDURE - NO CODE INPUT (AUTO-GENERATED)
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_procedure'])) {
    $procedure_name = trim($_POST['procedure_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    
    if (empty($procedure_name) || $price <= 0) {
        $message = "❌ Procedure name and price are required!";
        $message_type = 'error';
    } else {
        try {
            // Auto-generate procedure code
            $procedure_code = generateProcedureCode($db, $doctor_branch_id);
            
            $stmt = $db->prepare("
                INSERT INTO procedures (
                    procedure_name, procedure_code, category, branch_id, 
                    price, description, is_active, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 1, ?, NOW())
            ");
            $stmt->execute([$procedure_name, $procedure_code, $category, $doctor_branch_id, $price, $description, $doctor_id]);
            $message = "✅ Procedure added successfully! Code: " . $procedure_code;
            $message_type = 'success';
        } catch (Exception $e) {
            $message = "❌ Error: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// ================================================================
// ADD TOOL - NO PROCEDURE NAME (ONLY TOOL NAME + PRICE)
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_tool'])) {
    $tool_name = trim($_POST['tool_name'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    
    if (empty($tool_name) || $price <= 0) {
        $message = "❌ Tool name and price are required!";
        $message_type = 'error';
    } else {
        try {
            $stmt = $db->prepare("
                INSERT INTO procedure_tools (
                    tool_name, branch_id, price, is_active, created_at
                ) VALUES (?, ?, ?, 1, NOW())
            ");
            $stmt->execute([$tool_name, $doctor_branch_id, $price]);
            $message = "✅ Tool added successfully!";
            $message_type = 'success';
        } catch (Exception $e) {
            $message = "❌ Error: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// ================================================================
// ADD LAB TEST - WITH CATEGORY DROPDOWN
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_lab_test'])) {
    $test_name = trim($_POST['test_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    
    if (empty($test_name) || $price <= 0) {
        $message = "❌ Test name and price are required!";
        $message_type = 'error';
    } else {
        try {
            $stmt = $db->prepare("
                INSERT INTO lab_tests_catalog (
                    test_name, category, branch_id, price, is_active, created_at
                ) VALUES (?, ?, ?, ?, 1, NOW())
            ");
            $stmt->execute([$test_name, $category, $doctor_branch_id, $price]);
            $message = "✅ Lab test added successfully!";
            $message_type = 'success';
        } catch (Exception $e) {
            // If branch_id column doesn't exist, try without it
            try {
                $stmt = $db->prepare("
                    INSERT INTO lab_tests_catalog (
                        test_name, category, price, is_active, created_at
                    ) VALUES (?, ?, ?, 1, NOW())
                ");
                $stmt->execute([$test_name, $category, $price]);
                $message = "✅ Lab test added successfully!";
                $message_type = 'success';
            } catch (Exception $e2) {
                $message = "❌ Error: " . $e2->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// ================================================================
// FETCH DATA - WITH BRANCH FILTERING
// ================================================================

// Procedures (only for this branch)
$procedures = [];
try {
    $stmt = $db->prepare("
        SELECT * FROM procedures 
        WHERE branch_id = ? OR branch_id IS NULL 
        ORDER BY procedure_name
    ");
    $stmt->execute([$doctor_branch_id]);
    $procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $procedures = []; }

// Tools (only for this branch) - now without procedure_name requirement
$tools = [];
try {
    $stmt = $db->prepare("
        SELECT * FROM procedure_tools 
        WHERE branch_id = ? OR branch_id IS NULL 
        ORDER BY tool_name
    ");
    $stmt->execute([$doctor_branch_id]);
    $tools = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $tools = []; }

// Lab Tests (only for this branch)
$lab_tests = [];
try {
    $stmt = $db->prepare("
        SELECT * FROM lab_tests_catalog 
        WHERE branch_id = ? OR branch_id IS NULL 
        ORDER BY test_name
    ");
    $stmt->execute([$doctor_branch_id]);
    $lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $lab_tests = []; }

// ================================================================
// LAB TEST CATEGORIES FOR DROPDOWN
// ================================================================
$lab_categories = [
    'Hematology',
    'Biochemistry',
    'Microbiology',
    'Immunology',
    'Serology',
    'Urinalysis',
    'Stool Analysis',
    'Pathology',
    'Radiology',
    'Cardiology',
    'Endocrinology',
    'Other'
];

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/doctor_header.php';
include_once __DIR__ . '/../../components/doctor_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services Management - Braick Dispensary</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
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
            --radius-lg: 14px;
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            background: var(--gray-50);
            color: var(--gray-800);
            font-family: 'Inter', 'Segoe UI', sans-serif;
        }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
            background: var(--gray-50);
            transition: all 0.3s ease;
        }
        
        [data-theme="dark"] .main-content {
            background: var(--gray-900);
            color: var(--gray-100);
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 28px;
            padding: 24px 28px;
            background: #ffffff;
            border-radius: var(--radius-lg);
            border: 1px solid var(--gray-200);
            box-shadow: var(--shadow);
        }
        [data-theme="dark"] .page-header {
            background: var(--gray-800);
            border-color: var(--gray-700);
        }
        
        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .page-title i { color: var(--primary); }
        [data-theme="dark"] .page-title { color: var(--gray-100); }
        
        .page-subtitle {
            font-size: 0.9rem;
            color: var(--gray-500);
        }
        .page-subtitle strong { color: var(--gray-700); }
        [data-theme="dark"] .page-subtitle strong { color: var(--gray-200); }
        
        .branch-badge {
            display: inline-block;
            background: var(--primary-bg);
            color: var(--primary);
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            border: 1px solid var(--primary-light);
        }
        [data-theme="dark"] .branch-badge {
            background: #1E3A5F;
            color: var(--primary-light);
            border-color: var(--primary);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }
        
        .stat-card {
            background: #ffffff;
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            border: 1px solid var(--gray-200);
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 16px;
        }
        [data-theme="dark"] .stat-card {
            background: var(--gray-800);
            border-color: var(--gray-700);
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        .stat-icon.purple { background: var(--purple-bg); color: var(--purple); }
        .stat-icon.orange { background: var(--warning-bg); color: var(--warning); }
        .stat-icon.teal { background: #CCFBF1; color: #0D9488; }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--gray-800);
        }
        [data-theme="dark"] .stat-number { color: var(--gray-100); }
        .stat-label {
            font-size: 0.8rem;
            color: var(--gray-500);
        }
        
        /* TABS */
        .tabs {
            display: flex;
            gap: 4px;
            background: var(--gray-100);
            padding: 4px;
            border-radius: var(--radius);
            margin-bottom: 24px;
            border: 1px solid var(--gray-200);
        }
        [data-theme="dark"] .tabs {
            background: var(--gray-700);
            border-color: var(--gray-600);
        }
        
        .tab-btn {
            padding: 10px 24px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
            background: transparent;
            color: var(--gray-500);
            flex: 1;
            text-align: center;
        }
        .tab-btn:hover {
            background: rgba(255,255,255,0.5);
            color: var(--gray-700);
        }
        .tab-btn.active {
            background: #ffffff;
            color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        [data-theme="dark"] .tab-btn.active {
            background: var(--gray-800);
            color: var(--primary-light);
        }
        .tab-btn i { margin-right: 8px; }
        
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        
        /* TABLE - WITH BLUE HEADERS */
        .table-container {
            background: #ffffff;
            border-radius: var(--radius-lg);
            border: 1px solid var(--gray-200);
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        [data-theme="dark"] .table-container {
            background: var(--gray-800);
            border-color: var(--gray-700);
        }
        
        .table-header {
            padding: 16px 24px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        [data-theme="dark"] .table-header {
            border-color: var(--gray-700);
        }
        
        .table-header h3 {
            font-size: 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .table-header h3 i { color: var(--primary); }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        
        /* BLUE HEADER STYLES */
        thead th {
            text-align: left;
            padding: 12px 18px;
            font-weight: 700;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #ffffff;
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            border-bottom: 3px solid #0A4CA8;
        }
        thead th:first-child {
            border-radius: 8px 0 0 0;
        }
        thead th:last-child {
            border-radius: 0 8px 0 0;
        }
        [data-theme="dark"] thead th {
            background: linear-gradient(135deg, #1E3A5F, #0A3D7A);
            color: #ffffff;
            border-bottom-color: #0A3D7A;
        }
        
        td {
            padding: 10px 18px;
            border-bottom: 1px solid var(--gray-200);
            color: var(--gray-700);
        }
        [data-theme="dark"] td {
            color: var(--gray-300);
            border-color: var(--gray-700);
        }
        tr:hover td {
            background: var(--gray-50);
        }
        [data-theme="dark"] tr:hover td {
            background: var(--gray-700);
        }
        tr:nth-child(even) td {
            background: var(--gray-50);
        }
        [data-theme="dark"] tr:nth-child(even) td {
            background: var(--gray-750);
        }
        
        /* VIEW BUTTON */
        .btn-view {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 14px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.7rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            background: var(--primary-bg);
            color: var(--primary);
            border: 1px solid var(--primary-light);
        }
        .btn-view:hover {
            background: var(--primary);
            color: #ffffff;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(11,94,215,0.3);
        }
        [data-theme="dark"] .btn-view {
            background: #1E3A5F;
            color: var(--primary-light);
            border-color: var(--primary);
        }
        [data-theme="dark"] .btn-view:hover {
            background: var(--primary);
            color: #ffffff;
        }
        
        .badge {
            display: inline-block;
            font-size: 0.65rem;
            font-weight: 600;
            padding: 2px 12px;
            border-radius: 20px;
        }
        .badge-success { background: var(--success-bg); color: var(--success); }
        .badge-danger { background: var(--danger-bg); color: var(--danger); }
        .badge-warning { background: var(--warning-bg); color: var(--warning); }
        .badge-info { background: var(--primary-bg); color: var(--primary); }
        .badge-purple { background: var(--purple-bg); color: var(--purple); }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.8rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-success { background: var(--success); color: white; }
        .btn-success:hover { background: #047857; }
        .btn-sm { padding: 4px 12px; font-size: 0.7rem; }
        
        .alert {
            padding: 14px 20px;
            border-radius: var(--radius);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid transparent;
            animation: slideDown 0.3s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .alert-success { background: var(--success-bg); color: var(--success); border-color: var(--success); }
        .alert-error { background: var(--danger-bg); color: var(--danger); border-color: var(--danger); }
        .alert-warning { background: var(--warning-bg); color: var(--warning); border-color: var(--warning); }
        
        .form-group { margin-bottom: 14px; }
        .form-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--gray-600);
            margin-bottom: 4px;
        }
        .form-control {
            width: 100%;
            padding: 8px 14px;
            border: 2px solid var(--gray-200);
            border-radius: 8px;
            font-size: 0.85rem;
            background: #ffffff;
            color: var(--gray-800);
            outline: none;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11,94,215,0.12);
        }
        [data-theme="dark"] .form-control {
            background: var(--gray-700);
            color: var(--gray-100);
            border-color: var(--gray-600);
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
        
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            backdrop-filter: blur(4px);
            align-items: center;
            justify-content: center;
        }
        .modal-overlay.show {
            display: flex;
        }
        .modal {
            background: #ffffff;
            border-radius: var(--radius-lg);
            padding: 32px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        [data-theme="dark"] .modal {
            background: var(--gray-800);
        }
        .modal-title {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 16px;
            justify-content: flex-end;
        }
        
        .footer {
            padding: 16px 0;
            border-top: 2px solid var(--gray-200);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--gray-500);
        }
        [data-theme="dark"] .footer { border-color: var(--gray-700); }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray-500);
        }
        .empty-state i {
            font-size: 3rem;
            color: var(--gray-300);
            display: block;
            margin-bottom: 12px;
        }
        .empty-state .sub-text {
            font-size: 0.8rem;
            color: var(--gray-400);
            margin-top: 4px;
        }
        
        .code-badge {
            display: inline-block;
            background: var(--gray-100);
            color: var(--gray-600);
            padding: 1px 10px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-family: monospace;
            border: 1px solid var(--gray-300);
        }
        [data-theme="dark"] .code-badge {
            background: var(--gray-700);
            color: var(--gray-400);
            border-color: var(--gray-600);
        }
        
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px; }
            .stats-grid { grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; }
            .tabs { flex-direction: column; }
            .tab-btn { flex: none; }
            .table-container { overflow-x: auto; }
        }
    </style>
</head>
<body>

<main class="main-content">
    
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-cog"></i> Services Management
            </h1>
            <p class="page-subtitle">
                Manage <strong>Procedures</strong>, <strong>Tools</strong> and <strong>Lab Tests</strong>
                <span class="branch-badge">
                    <i class="fas fa-store"></i> <?= htmlspecialchars($branch_name) ?>
                </span>
            </p>
        </div>
        <div>
            <a href="../doctor/dashboard.php" class="btn btn-primary btn-sm">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>
    
    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon purple"><i class="fas fa-syringe"></i></div>
            <div>
                <div class="stat-number"><?= count($procedures) ?></div>
                <div class="stat-label">Procedures</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon orange"><i class="fas fa-tools"></i></div>
            <div>
                <div class="stat-number"><?= count($tools) ?></div>
                <div class="stat-label">Tools</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon teal"><i class="fas fa-microscope"></i></div>
            <div>
                <div class="stat-number"><?= count($lab_tests) ?></div>
                <div class="stat-label">Lab Tests</div>
            </div>
        </div>
    </div>
    
    <!-- Message -->
    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>
    
    <!-- Tabs -->
    <div class="tabs">
        <button class="tab-btn <?= $active_tab === 'procedures' ? 'active' : '' ?>" data-tab="procedures">
            <i class="fas fa-syringe"></i> Procedures (<?= count($procedures) ?>)
        </button>
        <button class="tab-btn <?= $active_tab === 'tools' ? 'active' : '' ?>" data-tab="tools">
            <i class="fas fa-tools"></i> Tools (<?= count($tools) ?>)
        </button>
        <button class="tab-btn <?= $active_tab === 'lab_tests' ? 'active' : '' ?>" data-tab="lab_tests">
            <i class="fas fa-microscope"></i> Lab Tests (<?= count($lab_tests) ?>)
        </button>
    </div>
    
    <!-- ================================================================ -->
    <!-- TAB 1: PROCEDURES -->
    <!-- ================================================================ -->
    <div class="tab-content <?= $active_tab === 'procedures' ? 'active' : '' ?>" id="tab-procedures">
        <div class="table-container">
            <div class="table-header">
                <h3><i class="fas fa-syringe"></i> Procedures - <?= htmlspecialchars($branch_name) ?></h3>
                <button class="btn btn-primary btn-sm" onclick="openModal('procedureModal')">
                    <i class="fas fa-plus"></i> Add Procedure
                </button>
            </div>
            <?php if (count($procedures) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th style="width:5%;">#</th>
                            <th style="width:30%;">Procedure Name</th>
                            <th style="width:15%;">Code</th>
                            <th style="width:20%;">Category</th>
                            <th style="width:15%;text-align:right;">Price (TSh)</th>
                            <th style="width:10%;text-align:center;">Status</th>
                            <th style="width:5%;text-align:center;">View</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($procedures as $proc): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><strong><?= htmlspecialchars($proc['procedure_name']) ?></strong></td>
                                <td><span class="code-badge"><?= htmlspecialchars($proc['procedure_code'] ?? 'N/A') ?></span></td>
                                <td><?= htmlspecialchars($proc['category'] ?? '-') ?></td>
                                <td style="text-align:right;font-weight:600;color:var(--success);">
                                    <?= number_format($proc['price'], 0) ?>
                                </td>
                                <td style="text-align:center;">
                                    <span class="badge <?= $proc['is_active'] ? 'badge-success' : 'badge-danger' ?>">
                                        <?= $proc['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td style="text-align:center;">
                                    <button class="btn-view" onclick="viewProcedure(<?= htmlspecialchars(json_encode($proc)) ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-syringe"></i>
                    <p>No procedures added for this branch yet.</p>
                    <p class="sub-text">Click "Add Procedure" to add your first procedure.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- ================================================================ -->
    <!-- TAB 2: TOOLS -->
    <!-- ================================================================ -->
    <div class="tab-content <?= $active_tab === 'tools' ? 'active' : '' ?>" id="tab-tools">
        <div class="table-container">
            <div class="table-header">
                <h3><i class="fas fa-tools"></i> Tools - <?= htmlspecialchars($branch_name) ?></h3>
                <button class="btn btn-primary btn-sm" onclick="openModal('toolModal')">
                    <i class="fas fa-plus"></i> Add Tool
                </button>
            </div>
            <?php if (count($tools) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th style="width:5%;">#</th>
                            <th style="width:40%;">Tool Name</th>
                            <th style="width:25%;text-align:right;">Price (TSh)</th>
                            <th style="width:15%;text-align:center;">Status</th>
                            <th style="width:15%;text-align:center;">View</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($tools as $tool): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><strong><?= htmlspecialchars($tool['tool_name']) ?></strong></td>
                                <td style="text-align:right;font-weight:600;color:var(--success);">
                                    <?= number_format($tool['price'], 0) ?>
                                </td>
                                <td style="text-align:center;">
                                    <span class="badge <?= $tool['is_active'] ? 'badge-success' : 'badge-danger' ?>">
                                        <?= $tool['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td style="text-align:center;">
                                    <button class="btn-view" onclick="viewTool(<?= htmlspecialchars(json_encode($tool)) ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-tools"></i>
                    <p>No tools added for this branch yet.</p>
                    <p class="sub-text">Click "Add Tool" to add your first tool.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- ================================================================ -->
    <!-- TAB 3: LAB TESTS -->
    <!-- ================================================================ -->
    <div class="tab-content <?= $active_tab === 'lab_tests' ? 'active' : '' ?>" id="tab-lab_tests">
        <div class="table-container">
            <div class="table-header">
                <h3><i class="fas fa-microscope"></i> Lab Tests - <?= htmlspecialchars($branch_name) ?></h3>
                <button class="btn btn-primary btn-sm" onclick="openModal('labTestModal')">
                    <i class="fas fa-plus"></i> Add Lab Test
                </button>
            </div>
            <?php if (count($lab_tests) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th style="width:5%;">#</th>
                            <th style="width:35%;">Test Name</th>
                            <th style="width:25%;">Category</th>
                            <th style="width:20%;text-align:right;">Price (TSh)</th>
                            <th style="width:10%;text-align:center;">Status</th>
                            <th style="width:5%;text-align:center;">View</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($lab_tests as $test): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><strong><?= htmlspecialchars($test['test_name']) ?></strong></td>
                                <td>
                                    <?php if (!empty($test['category'])): ?>
                                        <span class="badge badge-purple"><?= htmlspecialchars($test['category']) ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-info">Uncategorized</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:right;font-weight:600;color:var(--success);">
                                    <?= number_format($test['price'], 0) ?>
                                </td>
                                <td style="text-align:center;">
                                    <span class="badge <?= $test['is_active'] ? 'badge-success' : 'badge-danger' ?>">
                                        <?= $test['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td style="text-align:center;">
                                    <button class="btn-view" onclick="viewLabTest(<?= htmlspecialchars(json_encode($test)) ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-microscope"></i>
                    <p>No lab tests added for this branch yet.</p>
                    <p class="sub-text">Click "Add Lab Test" to add your first test.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <footer class="footer">
        <p>
            <span style="color:var(--primary);font-weight:600;">Braick Dispensary</span> 
            Services Management &copy; <?= date('Y') ?> | 
            Branch: <?= htmlspecialchars($branch_name) ?>
        </p>
    </footer>
</main>

<!-- ================================================================ -->
<!-- ADD PROCEDURE MODAL - No Code Input (Auto-generated) -->
<!-- ================================================================ -->
<div class="modal-overlay" id="procedureModal">
    <div class="modal">
        <h3 class="modal-title">
            <i class="fas fa-syringe"></i> Add Procedure
        </h3>
        <form method="POST">
            <div class="form-group">
                <label class="form-label">Procedure Name <span style="color:red;">*</span></label>
                <input type="text" name="procedure_name" class="form-control" required placeholder="e.g. Wound Dressing">
            </div>
            <div class="form-group">
                <label class="form-label">Category</label>
                <input type="text" name="category" class="form-control" placeholder="e.g. Surgery, Wound Care">
            </div>
            <div class="form-group">
                <label class="form-label">Price (TSh) <span style="color:red;">*</span></label>
                <input type="number" name="price" class="form-control" required min="0" step="100" placeholder="e.g. 15000">
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="2" placeholder="Optional description"></textarea>
            </div>
            <div class="form-group" style="background:var(--gray-50);padding:10px 14px;border-radius:8px;font-size:0.75rem;color:var(--gray-500);">
                <i class="fas fa-info-circle"></i> 
                Procedure code will be generated automatically: <strong id="previewCode">PROC-<?= date('Y') ?>-001</strong>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-danger" onclick="closeModal('procedureModal')">Cancel</button>
                <button type="submit" name="add_procedure" class="btn btn-primary">Add Procedure</button>
            </div>
        </form>
    </div>
</div>

<!-- ================================================================ -->
<!-- ADD TOOL MODAL - Only Tool Name + Price (No Procedure Name) -->
<!-- ================================================================ -->
<div class="modal-overlay" id="toolModal">
    <div class="modal">
        <h3 class="modal-title">
            <i class="fas fa-tools"></i> Add Tool
        </h3>
        <form method="POST">
            <div class="form-group">
                <label class="form-label">Tool Name <span style="color:red;">*</span></label>
                <input type="text" name="tool_name" class="form-control" required placeholder="e.g. Syringe, Scalpel, Bandage">
            </div>
            <div class="form-group">
                <label class="form-label">Price (TSh) <span style="color:red;">*</span></label>
                <input type="number" name="price" class="form-control" required min="0" step="100" placeholder="e.g. 500">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-danger" onclick="closeModal('toolModal')">Cancel</button>
                <button type="submit" name="add_tool" class="btn btn-primary">Add Tool</button>
            </div>
        </form>
    </div>
</div>

<!-- ================================================================ -->
<!-- ADD LAB TEST MODAL - With Category Dropdown -->
<!-- ================================================================ -->
<div class="modal-overlay" id="labTestModal">
    <div class="modal">
        <h3 class="modal-title">
            <i class="fas fa-microscope"></i> Add Lab Test
        </h3>
        <form method="POST">
            <div class="form-group">
                <label class="form-label">Test Name <span style="color:red;">*</span></label>
                <input type="text" name="test_name" class="form-control" required placeholder="e.g. Complete Blood Count">
            </div>
            <div class="form-group">
                <label class="form-label">Category</label>
                <div class="form-row">
                    <select name="category" class="form-control" id="labCategorySelect">
                        <option value="">-- Select Category --</option>
                        <?php foreach ($lab_categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                        <?php endforeach; ?>
                        <option value="other">-- Other (Type manually) --</option>
                    </select>
                    <input type="text" name="category_manual" class="form-control" id="labCategoryManual" 
                           placeholder="Or type custom category..." style="display:none;">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Price (TSh) <span style="color:red;">*</span></label>
                <input type="number" name="price" class="form-control" required min="0" step="100" placeholder="e.g. 5000">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-danger" onclick="closeModal('labTestModal')">Cancel</button>
                <button type="submit" name="add_lab_test" class="btn btn-primary">Add Lab Test</button>
            </div>
        </form>
    </div>
</div>

<!-- ================================================================ -->
<!-- VIEW DETAIL MODAL -->
<!-- ================================================================ -->
<div class="modal-overlay" id="viewModal">
    <div class="modal" style="max-width:450px;">
        <h3 class="modal-title" id="viewModalTitle">
            <i class="fas fa-eye"></i> Details
        </h3>
        <div id="viewModalContent" style="padding:10px 0;">
            <!-- Content loaded dynamically -->
        </div>
        <div class="modal-actions">
            <button type="button" class="btn btn-danger" onclick="closeModal('viewModal')">Close</button>
        </div>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // Tabs
    document.querySelectorAll('.tab-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.tab-btn').forEach(function(b) { b.classList.remove('active'); });
            this.classList.add('active');
            
            var tab = this.dataset.tab;
            document.querySelectorAll('.tab-content').forEach(function(content) {
                content.classList.remove('active');
            });
            document.getElementById('tab-' + tab).classList.add('active');
            
            var url = new URL(window.location.href);
            url.searchParams.set('tab', tab);
            window.history.pushState({}, '', url);
        });
    });
    
    // Modal functions
    function openModal(id) {
        document.getElementById(id).classList.add('show');
        document.body.style.overflow = 'hidden';
    }
    
    function closeModal(id) {
        document.getElementById(id).classList.remove('show');
        document.body.style.overflow = '';
    }
    
    // Close modal on overlay click
    document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('show');
                document.body.style.overflow = '';
            }
        });
    });
    
    // ================================================================
    // LAB CATEGORY - Toggle manual input
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        var select = document.getElementById('labCategorySelect');
        var manual = document.getElementById('labCategoryManual');
        var nameField = document.querySelector('input[name="test_name"]');
        
        if (select && manual) {
            select.addEventListener('change', function() {
                if (this.value === 'other') {
                    manual.style.display = 'block';
                    manual.required = true;
                    manual.focus();
                } else {
                    manual.style.display = 'none';
                    manual.required = false;
                    manual.value = '';
                    // If a category is selected, update the hidden input
                    if (this.value) {
                        // We'll handle this in form submission
                    }
                }
            });
        }
    });
    
    // ================================================================
    // VIEW FUNCTIONS
    // ================================================================
    function viewProcedure(data) {
        document.getElementById('viewModalTitle').innerHTML = '<i class="fas fa-syringe"></i> Procedure Details';
        document.getElementById('viewModalContent').innerHTML = `
            <div style="padding:8px 0;border-bottom:1px solid var(--gray-200);">
                <div style="font-size:0.7rem;color:var(--gray-500);">Procedure Name</div>
                <div style="font-size:1rem;font-weight:600;">${escapeHtml(data.procedure_name)}</div>
            </div>
            <div style="padding:8px 0;border-bottom:1px solid var(--gray-200);">
                <div style="font-size:0.7rem;color:var(--gray-500);">Code</div>
                <div><span class="code-badge">${escapeHtml(data.procedure_code || 'N/A')}</span></div>
            </div>
            <div style="padding:8px 0;border-bottom:1px solid var(--gray-200);">
                <div style="font-size:0.7rem;color:var(--gray-500);">Category</div>
                <div>${escapeHtml(data.category || '-')}</div>
            </div>
            <div style="padding:8px 0;border-bottom:1px solid var(--gray-200);">
                <div style="font-size:0.7rem;color:var(--gray-500);">Price</div>
                <div style="font-size:1.2rem;font-weight:700;color:var(--success);">TSh ${Number(data.price).toLocaleString()}</div>
            </div>
            <div style="padding:8px 0;border-bottom:1px solid var(--gray-200);">
                <div style="font-size:0.7rem;color:var(--gray-500);">Description</div>
                <div>${escapeHtml(data.description || 'No description')}</div>
            </div>
            <div style="padding:8px 0;">
                <div style="font-size:0.7rem;color:var(--gray-500);">Status</div>
                <div><span class="badge ${data.is_active ? 'badge-success' : 'badge-danger'}">${data.is_active ? 'Active' : 'Inactive'}</span></div>
            </div>
        `;
        openModal('viewModal');
    }
    
    function viewTool(data) {
        document.getElementById('viewModalTitle').innerHTML = '<i class="fas fa-tools"></i> Tool Details';
        document.getElementById('viewModalContent').innerHTML = `
            <div style="padding:8px 0;border-bottom:1px solid var(--gray-200);">
                <div style="font-size:0.7rem;color:var(--gray-500);">Tool Name</div>
                <div style="font-size:1rem;font-weight:600;">${escapeHtml(data.tool_name)}</div>
            </div>
            <div style="padding:8px 0;border-bottom:1px solid var(--gray-200);">
                <div style="font-size:0.7rem;color:var(--gray-500);">Price</div>
                <div style="font-size:1.2rem;font-weight:700;color:var(--success);">TSh ${Number(data.price).toLocaleString()}</div>
            </div>
            <div style="padding:8px 0;">
                <div style="font-size:0.7rem;color:var(--gray-500);">Status</div>
                <div><span class="badge ${data.is_active ? 'badge-success' : 'badge-danger'}">${data.is_active ? 'Active' : 'Inactive'}</span></div>
            </div>
        `;
        openModal('viewModal');
    }
    
    function viewLabTest(data) {
        document.getElementById('viewModalTitle').innerHTML = '<i class="fas fa-microscope"></i> Lab Test Details';
        document.getElementById('viewModalContent').innerHTML = `
            <div style="padding:8px 0;border-bottom:1px solid var(--gray-200);">
                <div style="font-size:0.7rem;color:var(--gray-500);">Test Name</div>
                <div style="font-size:1rem;font-weight:600;">${escapeHtml(data.test_name)}</div>
            </div>
            <div style="padding:8px 0;border-bottom:1px solid var(--gray-200);">
                <div style="font-size:0.7rem;color:var(--gray-500);">Category</div>
                <div>${escapeHtml(data.category || 'Uncategorized')}</div>
            </div>
            <div style="padding:8px 0;border-bottom:1px solid var(--gray-200);">
                <div style="font-size:0.7rem;color:var(--gray-500);">Price</div>
                <div style="font-size:1.2rem;font-weight:700;color:var(--success);">TSh ${Number(data.price).toLocaleString()}</div>
            </div>
            <div style="padding:8px 0;">
                <div style="font-size:0.7rem;color:var(--gray-500);">Status</div>
                <div><span class="badge ${data.is_active ? 'badge-success' : 'badge-danger'}">${data.is_active ? 'Active' : 'Inactive'}</span></div>
            </div>
        `;
        openModal('viewModal');
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // ================================================================
    // GENERATE PREVIEW CODE FOR PROCEDURE
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        var nameField = document.querySelector('input[name="procedure_name"]');
        if (nameField) {
            // Show preview code
            var count = <?= count($procedures) ?>;
            var nextNum = String(count + 1).padStart(3, '0');
            document.getElementById('previewCode').textContent = 'PROC-<?= date('Y') ?>-' + nextNum;
        }
    });
    
    console.log('%c⚙️ Services Management - ' + '<?= htmlspecialchars($branch_name) ?>', 'font-size:16px; font-weight:bold; color:#7C3AED;');
    console.log('%c📋 Procedures: <?= count($procedures) ?>', 'font-size:12px; color:#7C3AED;');
    console.log('%c🔧 Tools: <?= count($tools) ?>', 'font-size:12px; color:#D97706;');
    console.log('%c🧪 Lab Tests: <?= count($lab_tests) ?>', 'font-size:12px; color:#0D9488;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?> (ID: <?= $doctor_branch_id ?>)', 'font-size:12px; color:#0B5ED7;');
    console.log('%c✅ Changes applied:', 'font-size:12px; color:#34D399;');
    console.log('%c   - Procedure Code auto-generated', 'font-size:11px; color:#34D399;');
    console.log('%c   - Tools: Only Tool Name + Price (no Procedure Name)', 'font-size:11px; color:#34D399;');
    console.log('%c   - Lab Tests: Category dropdown with manual option', 'font-size:11px; color:#34D399;');
    console.log('%c   - Table headers: Blue background', 'font-size:11px; color:#34D399;');
    console.log('%c   - View button added (no edit/delete)', 'font-size:11px; color:#34D399;');
    console.log('%c   - Services filtered by branch', 'font-size:11px; color:#34D399;');
</script>

</body>
</html>