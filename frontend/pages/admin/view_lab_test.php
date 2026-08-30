<?php
// ================================================================
// FILE: frontend/pages/admin/view_lab_test.php
// ADMIN - VIEW LAB TEST DETAILS
// BRAICK DISPENSARY - BLUE THEME
// USING YOUR DATABASE
// ================================================================

// ================================================================
// START SESSION
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// LOGIN PROTECTION - CHECK IF USER IS LOGGED IN
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// CHECK IF USER IS ADMIN
// ================================================================
if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET ADMIN DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// VERIFY USER EXISTS IN DATABASE
// ================================================================
$stmt = $db->prepare("SELECT id, full_name, role, status FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['status'] !== 'active') {
    session_destroy();
    header('Location: ../login.php');
    exit;
}

// ================================================================
// GET UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// ================================================================
// GET PARAMETERS
// ================================================================
$lab_test_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_branch_id = isset($_GET['branch']) ? trim($_GET['branch']) : 'all';

if ($lab_test_id <= 0) {
    header('Location: lab_tests.php?branch=' . urlencode($selected_branch_id) . '&error=invalid_id');
    exit;
}

// ================================================================
// FETCH LAB TEST DETAILS - USING YOUR DATABASE
// ================================================================
try {
    $stmt = $db->prepare("
        SELECT 
            lt.*,
            lt.id as lab_test_id,
            p.id as patient_id,
            p.full_name as patient_name,
            p.patient_id as patient_code,
            p.phone as patient_phone,
            p.gender as patient_gender,
            p.date_of_birth,
            p.blood_group,
            p.allergies,
            p.address,
            u.full_name as doctor_name,
            u.specialty as doctor_specialty,
            u2.full_name as technician_name,
            u2.profile_pic as technician_profile_pic,
            v.visit_number,
            v.visit_type,
            v.id as visit_id,
            v.symptoms,
            v.complaint,
            v.diagnosis,
            v.treatment,
            b.name as branch_name,
            ltc.test_code,
            ltc.category as test_category,
            ltc.reference_range as catalog_reference_range
        FROM lab_tests lt
        LEFT JOIN visits v ON lt.visit_id = v.id
        LEFT JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON lt.doctor_id = u.id
        LEFT JOIN users u2 ON lt.technician_id = u2.id
        LEFT JOIN branches b ON lt.branch_id = b.id
        LEFT JOIN lab_tests_catalog ltc ON lt.test_id = ltc.id
        WHERE lt.id = ?
    ");
    $stmt->execute([$lab_test_id]);
    $lab_test = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lab_test) {
        header('Location: lab_tests.php?branch=' . urlencode($selected_branch_id) . '&error=notfound');
        exit;
    }
} catch (Exception $e) {
    error_log("Error fetching lab test: " . $e->getMessage());
    header('Location: lab_tests.php?branch=' . urlencode($selected_branch_id) . '&error=database_error');
    exit;
}

// ================================================================
// CALCULATE AGE
// ================================================================
$age = null;
if (!empty($lab_test['date_of_birth'])) {
    $birthDate = new DateTime($lab_test['date_of_birth']);
    $today = new DateTime('today');
    $age = $birthDate->diff($today)->y;
}

// ================================================================
// GET BRANCHES FOR FILTER
// ================================================================
$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branches = [];
}

// ================================================================
// HANDLE DELETE REQUEST
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    try {
        // Check if lab test exists
        $stmt = $db->prepare("SELECT id, test_name FROM lab_tests WHERE id = ?");
        $stmt->execute([$lab_test_id]);
        $test = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($test) {
            // Delete the lab test
            $stmt = $db->prepare("DELETE FROM lab_tests WHERE id = ?");
            $stmt->execute([$lab_test_id]);
            
            // Log activity
            $details = "Deleted lab test: " . htmlspecialchars($test['test_name']) . " (ID: #$lab_test_id)";
            $stmt = $db->prepare("
                INSERT INTO activity_logs (user_id, branch_id, action, details, created_at)
                VALUES (?, ?, 'lab_test_deleted', ?, NOW())
            ");
            $stmt->execute([$user_id, $lab_test['branch_id'] ?? 1, $details]);
            
            $_SESSION['toast'] = [
                'message' => '✅ Lab test deleted successfully!',
                'type' => 'success'
            ];
            header('Location: lab_tests.php?branch=' . urlencode($selected_branch_id));
            exit;
        }
    } catch (Exception $e) {
        $message = "❌ Error deleting lab test: " . $e->getMessage();
        $message_type = "danger";
    }
}

// ================================================================
// STATUS BADGE CLASS
// ================================================================
function getStatusBadge($status) {
    $classes = [
        'pending' => 'warning',
        'in_progress' => 'info',
        'completed' => 'success',
        'cancelled' => 'danger'
    ];
    return $classes[$status] ?? 'secondary';
}

function getStatusIcon($status) {
    $icons = [
        'pending' => 'fa-clock',
        'in_progress' => 'fa-spinner fa-spin',
        'completed' => 'fa-check-circle',
        'cancelled' => 'fa-times-circle'
    ];
    return $icons[$status] ?? 'fa-circle';
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Lab Test - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_url ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           ROOT VARIABLES - BOLDER BLUE THEME
           ================================================================ */
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #3B82F6;
            --primary-bg: #EFF6FF;
            --primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            --primary-gradient-strong: linear-gradient(135deg, #0A4CA8, #073B8A);
            
            --success: #059669;
            --success-dark: #047857;
            --success-light: #34D399;
            --success-bg: #D1FAE5;
            
            --danger: #DC2626;
            --danger-dark: #B91C1C;
            --danger-light: #F87171;
            --danger-bg: #FEE2E2;
            
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            
            --purple: #7C3AED;
            --purple-bg: #EDE9FE;
            
            --teal: #0D9488;
            --teal-bg: #ECFDF5;
            
            --white: #FFFFFF;
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
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
            
            --bg-body: #F0F4F8;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --radius: 12px;
            --radius-lg: 18px;
            --table-hover: #F8FAFC;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --primary: #3B82F6;
            --primary-dark: #2563EB;
            --primary-light: #60A5FA;
            --primary-bg: #1E3A5F;
            --primary-gradient: linear-gradient(135deg, #2563EB, #1D4ED8);
            --primary-gradient-strong: linear-gradient(135deg, #1D4ED8, #1E40AF);
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
            --table-hover: #1E293B;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        /* ================================================================
           PAGE HEADER - BOLDER BLUE THEME
           ================================================================ */
        .page-header {
            background: var(--primary-gradient-strong);
            border-radius: var(--radius-lg);
            padding: 28px 36px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 8px 32px rgba(10, 76, 168, 0.35);
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -60%;
            right: -10%;
            width: 400px;
            height: 400px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .page-header::after {
            content: '';
            position: absolute;
            bottom: -40%;
            left: -5%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.03);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .page-header .page-title {
            color: white;
            font-size: 1.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-title i {
            font-size: 2rem;
            opacity: 0.9;
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
        }
        
        .page-header .page-subtitle strong {
            color: white;
            font-weight: 600;
        }
        
        .page-header .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            backdrop-filter: blur(4px);
        }
        
        .page-header .header-badge {
            background: rgba(255,255,255,0.12);
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
            transition: all 0.3s ease;
        }
        
        .page-header .header-badge:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-1px);
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
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        /* ================================================================
           BUTTONS - FULL CSS STYLED
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
            box-shadow: var(--shadow-sm);
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }
        
        .btn:active {
            transform: translateY(0px);
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }
        
        .btn-primary {
            background: var(--primary-gradient);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-gradient-strong);
            box-shadow: 0 4px 16px rgba(11, 94, 215, 0.35);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #059669, #047857);
            color: white;
        }
        
        .btn-success:hover {
            background: linear-gradient(135deg, #047857, #065F46);
            box-shadow: 0 4px 16px rgba(5, 150, 105, 0.35);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #DC2626, #B91C1C);
            color: white;
        }
        
        .btn-danger:hover {
            background: linear-gradient(135deg, #B91C1C, #991B1B);
            box-shadow: 0 4px 16px rgba(220, 38, 38, 0.35);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #D97706, #B45309);
            color: white;
        }
        
        .btn-warning:hover {
            background: linear-gradient(135deg, #B45309, #92400E);
            box-shadow: 0 4px 16px rgba(217, 119, 6, 0.35);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-primary);
            border: 2px solid var(--border-color);
        }
        
        .btn-outline:hover {
            background: var(--bg-body);
            border-color: var(--primary);
            color: var(--primary);
            box-shadow: 0 4px 16px rgba(11, 94, 215, 0.15);
        }
        
        .btn-sm {
            padding: 5px 12px;
            font-size: 0.7rem;
            border-radius: 6px;
        }
        
        .btn-lg {
            padding: 14px 32px;
            font-size: 1rem;
        }
        
        .btn-block {
            width: 100%;
            justify-content: center;
        }
        
        .btn i {
            font-size: 0.9rem;
        }
        
        .btn-sm i {
            font-size: 0.7rem;
        }
        
        .btn-lg i {
            font-size: 1.1rem;
        }
        
        /* ================================================================
           INFO BOX
           ================================================================ */
        .info-box {
            background: var(--primary-bg);
            border-radius: var(--radius);
            padding: 16px 20px;
            border-left: 4px solid var(--primary);
            margin-bottom: 20px;
            max-width: 900px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .info-box .info-item {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            font-size: 0.85rem;
            flex-wrap: wrap;
            gap: 4px;
        }
        
        .info-box .info-item .label {
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .info-box .info-item .value {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .info-box .info-item .value .text-blue-600 {
            color: var(--primary) !important;
        }
        
        .info-box .info-item .value .text-gray-400 {
            color: var(--text-secondary) !important;
        }
        
        /* ================================================================
           RESULT CARD
           ================================================================ */
        .result-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 24px 28px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
            max-width: 900px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .result-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        .result-card .result-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        
        .result-card .result-value {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
            white-space: pre-wrap;
            line-height: 1.6;
        }
        
        /* ================================================================
           BADGES
           ================================================================ */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            color: white;
            letter-spacing: 0.02em;
        }
        
        .badge-success { background: #059669; }
        .badge-danger { background: #DC2626; }
        .badge-warning { background: #D97706; color: #1E293B; }
        .badge-info { background: #0B5ED7; }
        .badge-secondary { background: #64748B; }
        .badge-purple { background: #7C3AED; }
        
        [data-theme="dark"] .badge-warning { color: #1E293B; }
        
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
            border-top: 2px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand {
            color: var(--primary);
            font-weight: 700;
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
            .info-box .info-item { flex-direction: column; }
            .result-card { padding: 16px; }
            .btn-actions { flex-direction: column; }
            .btn-actions .btn { width: 100%; justify-content: center; }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .result-card { padding: 14px; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .page-header { flex-direction: column; align-items: flex-start !important; }
        }
        
        /* ================================================================
           ANIMATIONS
           ================================================================ */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
        
        /* ================================================================
           PRINT STYLES
           ================================================================ */
        @media print {
            .top-nav, .sidebar, .btn, .dark-toggle-btn, .icon-btn,
            .search-wrapper, .page-header .btn-outline-light,
            .footer, #sidebarToggle { display: none !important; }
            .main-content { margin: 0; padding: 20px; }
            .result-card { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd; }
            .info-box { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd; }
            .page-header {
                background: #0B5ED7 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .page-title, .page-subtitle, .header-badge, .role-badge-display {
                color: white !important;
            }
            .badge { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- MAIN CONTENT - Sidebar is loaded from admin_sidebar.php -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-flask"></i>
                Lab Test Details
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-vial"></i>
                <strong><?= htmlspecialchars($lab_test['test_name'] ?? 'N/A') ?></strong>
                <span class="header-badge">
                    <i class="fas <?= getStatusIcon($lab_test['status'] ?? 'pending') ?>"></i>
                    <?= ucfirst($lab_test['status'] ?? 'Pending') ?>
                </span>
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-user"></i>
                    <?= htmlspecialchars($lab_test['patient_name'] ?? 'N/A') ?>
                </span>
                <span class="header-badge" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.3);color:#FBBF24;">
                    <i class="fas fa-money-bill-wave"></i>
                    TSh <?= number_format($lab_test['test_price'] ?? 0, 0) ?>
                </span>
                <span class="header-badge" style="background:rgba(255,255,255,0.15);">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($user_full_name) ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap" style="position:relative;z-index:1;">
            <a href="edit_lab_test.php?id=<?= $lab_test_id ?>&branch=<?= urlencode($selected_branch_id) ?>" class="btn-outline-light">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="lab_tests.php?branch=<?= urlencode($selected_branch_id) ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- TEST INFORMATION -->
    <!-- ================================================================ -->
    <div class="info-box animate-fade-in-up" style="animation-delay:0.05s;">
        <div class="info-item">
            <span class="label">Test ID</span>
            <span class="value">#<?= $lab_test_id ?></span>
        </div>
        <div class="info-item">
            <span class="label">Test Name</span>
            <span class="value">
                <strong><?= htmlspecialchars($lab_test['test_name'] ?? 'N/A') ?></strong>
                <?php if (!empty($lab_test['test_code'])): ?>
                    <span class="text-gray-400 text-sm">(<?= htmlspecialchars($lab_test['test_code']) ?>)</span>
                <?php endif; ?>
            </span>
        </div>
        <div class="info-item">
            <span class="label">Category</span>
            <span class="value"><?= htmlspecialchars($lab_test['test_category'] ?? 'N/A') ?></span>
        </div>
        <div class="info-item">
            <span class="label">Patient</span>
            <span class="value">
                <?php if (!empty($lab_test['patient_id']) && !empty($lab_test['patient_name'])): ?>
                    <a href="view_patient.php?id=<?= $lab_test['patient_id'] ?>&branch=<?= urlencode($selected_branch_id) ?>" class="text-blue-600 hover:underline">
                        <?= htmlspecialchars($lab_test['patient_name']) ?>
                    </a>
                    <?php if (!empty($lab_test['patient_code'])): ?>
                        (<?= htmlspecialchars($lab_test['patient_code']) ?>)
                    <?php endif; ?>
                    <?php if ($age !== null): ?>
                        <span class="text-gray-400 text-sm">| <?= $age ?> yrs</span>
                    <?php endif; ?>
                <?php else: ?>
                    <?= htmlspecialchars($lab_test['patient_name'] ?? 'N/A') ?>
                <?php endif; ?>
            </span>
        </div>
        <div class="info-item">
            <span class="label">Visit</span>
            <span class="value">
                <?php if (!empty($lab_test['visit_number']) && !empty($lab_test['visit_id'])): ?>
                    <a href="view_visit.php?id=<?= $lab_test['visit_id'] ?>&branch=<?= urlencode($selected_branch_id) ?>" class="text-blue-600 hover:underline">
                        <?= htmlspecialchars($lab_test['visit_number']) ?>
                    </a>
                <?php else: ?>
                    <?= htmlspecialchars($lab_test['visit_number'] ?? 'N/A') ?>
                <?php endif; ?>
            </span>
        </div>
        <div class="info-item">
            <span class="label">Doctor</span>
            <span class="value">
                <?php if (!empty($lab_test['doctor_name'])): ?>
                    Dr. <?= htmlspecialchars($lab_test['doctor_name']) ?>
                    <?php if (!empty($lab_test['doctor_specialty'])): ?>
                        <span class="text-gray-400 text-sm">(<?= htmlspecialchars($lab_test['doctor_specialty']) ?>)</span>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="text-gray-400">Not assigned</span>
                <?php endif; ?>
            </span>
        </div>
        <div class="info-item">
            <span class="label">Technician</span>
            <span class="value">
                <?php if (!empty($lab_test['technician_name'])): ?>
                    <?= htmlspecialchars($lab_test['technician_name']) ?>
                <?php else: ?>
                    <span class="text-gray-400">Not assigned</span>
                <?php endif; ?>
            </span>
        </div>
        <div class="info-item">
            <span class="label">Branch</span>
            <span class="value"><?= htmlspecialchars($lab_test['branch_name'] ?? 'N/A') ?></span>
        </div>
        <div class="info-item">
            <span class="label">Status</span>
            <span class="value">
                <span class="badge badge-<?= getStatusBadge($lab_test['status'] ?? 'pending') ?>">
                    <i class="fas <?= getStatusIcon($lab_test['status'] ?? 'pending') ?>"></i>
                    <?= ucfirst($lab_test['status'] ?? 'Pending') ?>
                </span>
            </span>
        </div>
        <div class="info-item">
            <span class="label">Price</span>
            <span class="value">TSh <?= number_format($lab_test['test_price'] ?? 0, 0) ?></span>
        </div>
        <div class="info-item">
            <span class="label">Created</span>
            <span class="value"><?= date('M d, Y h:i A', strtotime($lab_test['created_at'] ?? 'now')) ?></span>
        </div>
        <?php if (!empty($lab_test['completed_at'])): ?>
        <div class="info-item">
            <span class="label">Completed</span>
            <span class="value" style="color:var(--success);"><?= date('M d, Y h:i A', strtotime($lab_test['completed_at'])) ?></span>
        </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- REFERENCE RANGE -->
    <!-- ================================================================ -->
    <?php if (!empty($lab_test['reference_range'])): ?>
    <div class="result-card animate-fade-in-up" style="animation-delay:0.1s;">
        <div class="result-label"><i class="fas fa-chart-bar"></i> Reference Range</div>
        <div class="result-value"><?= htmlspecialchars($lab_test['reference_range']) ?></div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- RESULTS -->
    <!-- ================================================================ -->
    <div class="result-card animate-fade-in-up" style="animation-delay:0.15s;">
        <div class="result-label"><i class="fas fa-file-medical-alt"></i> Results</div>
        <div class="result-value">
            <?php if (!empty($lab_test['formatted_result'])): ?>
                <?= $lab_test['formatted_result'] ?>
            <?php elseif (!empty($lab_test['results'])): ?>
                <?= nl2br(htmlspecialchars($lab_test['results'])) ?>
            <?php else: ?>
                <span class="text-gray-400">No results available yet</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- INTERPRETATION -->
    <!-- ================================================================ -->
    <?php if (!empty($lab_test['interpretation'])): ?>
    <div class="result-card animate-fade-in-up" style="animation-delay:0.2s;border-color:var(--primary);background:var(--primary-bg);">
        <div class="result-label" style="color:var(--primary);"><i class="fas fa-stethoscope"></i> Interpretation</div>
        <div class="result-value" style="color:var(--primary);"><?= nl2br(htmlspecialchars($lab_test['interpretation'])) ?></div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- NOTES -->
    <!-- ================================================================ -->
    <?php if (!empty($lab_test['notes'])): ?>
    <div class="result-card animate-fade-in-up" style="animation-delay:0.25s;">
        <div class="result-label"><i class="fas fa-sticky-note"></i> Notes</div>
        <div class="result-value" style="font-style:italic;color:var(--text-secondary);"><?= nl2br(htmlspecialchars($lab_test['notes'])) ?></div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- CLINICAL INFORMATION -->
    <!-- ================================================================ -->
    <?php if (!empty($lab_test['symptoms']) || !empty($lab_test['complaint']) || !empty($lab_test['diagnosis']) || !empty($lab_test['treatment'])): ?>
    <div class="result-card animate-fade-in-up" style="animation-delay:0.3s;">
        <h3 class="text-sm font-bold text-primary mb-4">
            <i class="fas fa-notes-medical"></i> Clinical Information
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php if (!empty($lab_test['symptoms'])): ?>
            <div>
                <div class="result-label"><i class="fas fa-exclamation-triangle"></i> Symptoms</div>
                <div class="result-value"><?= htmlspecialchars($lab_test['symptoms']) ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($lab_test['complaint'])): ?>
            <div>
                <div class="result-label"><i class="fas fa-comment-medical"></i> Complaint</div>
                <div class="result-value"><?= htmlspecialchars($lab_test['complaint']) ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($lab_test['diagnosis'])): ?>
            <div>
                <div class="result-label"><i class="fas fa-stethoscope"></i> Diagnosis</div>
                <div class="result-value"><?= htmlspecialchars($lab_test['diagnosis']) ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($lab_test['treatment'])): ?>
            <div>
                <div class="result-label"><i class="fas fa-prescription"></i> Treatment</div>
                <div class="result-value"><?= htmlspecialchars($lab_test['treatment']) ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- ACTION BUTTONS -->
    <!-- ================================================================ -->
    <div class="result-card animate-fade-in-up" style="animation-delay:0.35s;">
        <h3 class="text-sm font-bold text-primary mb-4">
            <i class="fas fa-bolt"></i> Actions
        </h3>
        <div class="flex flex-wrap gap-3">
            <!-- Edit Button -->
            <a href="edit_lab_test.php?id=<?= $lab_test_id ?>&branch=<?= urlencode($selected_branch_id) ?>" class="btn btn-warning">
                <i class="fas fa-edit"></i> Edit
            </a>
            
            <!-- Complete Button (if not completed) -->
            <?php if (isset($lab_test['status']) && $lab_test['status'] !== 'completed' && $lab_test['status'] !== 'cancelled'): ?>
            <a href="edit_lab_test.php?action=complete&id=<?= $lab_test_id ?>&branch=<?= urlencode($selected_branch_id) ?>" class="btn btn-success">
                <i class="fas fa-check-circle"></i> Mark Complete
            </a>
            <?php endif; ?>
            
            <!-- Delete Button -->
            <button onclick="confirmDelete()" class="btn btn-danger">
                <i class="fas fa-trash"></i> Delete
            </button>
            
            <!-- Back Button -->
            <a href="lab_tests.php?branch=<?= urlencode($selected_branch_id) ?>" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back to List
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
            Lab Test Details - <?= htmlspecialchars($lab_test['test_name'] ?? 'N/A') ?>
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- DELETE CONFIRMATION MODAL -->
<!-- ================================================================ -->
<div id="deleteModal" class="modal" style="display:none;">
    <div class="modal-overlay" onclick="closeDeleteModal()"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-exclamation-triangle text-red-500 mr-2"></i> Confirm Delete</h3>
            <button onclick="closeDeleteModal()" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to delete this lab test?</p>
            <p class="text-sm text-gray-500 mt-2">
                <i class="fas fa-info-circle"></i> 
                <strong><?= htmlspecialchars($lab_test['test_name'] ?? 'N/A') ?></strong>
            </p>
            <p class="text-sm text-red-600 mt-2 font-semibold">
                ⚠️ This action cannot be undone!
            </p>
        </div>
        <div class="modal-footer">
            <button onclick="closeDeleteModal()" class="btn btn-outline btn-sm">Cancel</button>
            <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="delete">
                <button type="submit" class="btn btn-danger btn-sm">
                    <i class="fas fa-trash"></i> Yes, Delete
                </button>
            </form>
        </div>
    </div>
</div>

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
            document.cookie = "dark_mode=false; path=/";
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
            document.cookie = "dark_mode=true; path=/";
        }
    });

    // ================================================================
    // DOM ELEMENTS
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    sidebarToggle?.addEventListener('click', function() {
        sidebar.classList.toggle('open');
    });
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    // ================================================================
    // DATE & TIME
    // ================================================================
    function updateDateTime() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var dtEl = document.getElementById('currentDateTime');
        if (dtEl) dtEl.textContent = dateStr + ' • ' + timeStr;
        
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

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
    // DELETE MODAL
    // ================================================================
    function confirmDelete() {
        document.getElementById('deleteModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').style.display = 'none';
        document.body.style.overflow = '';
    }

    // Close modal on overlay click
    document.getElementById('deleteModal')?.addEventListener('click', function(e) {
        if (e.target === this || e.target.classList.contains('modal-overlay')) {
            closeDeleteModal();
        }
    });

    // Close modal on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeDeleteModal();
        }
    });

    // ================================================================
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            var branch = '<?= $selected_branch_id ?>';
            window.location.href = 'search.php?q=' + encodeURIComponent(query) + '&branch=' + branch;
        }
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        url.searchParams.delete('branch_id');
        window.location.href = url.toString();
    }

    console.log('%c🧪 Braick Dispensary - View Lab Test', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🔬 Test: <?= htmlspecialchars($lab_test['test_name'] ?? 'N/A') ?> (ID: <?= $lab_test_id ?>)', 'font-size:13px; color:#7C3AED;');
    console.log('%c👤 Patient: <?= htmlspecialchars($lab_test['patient_name'] ?? 'N/A') ?>', 'font-size:13px; color:#059669;');
    console.log('%c📋 Status: <?= ucfirst($lab_test['status'] ?? 'Pending') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c✅ Using database tables: lab_tests, visits, patients, users, branches, lab_tests_catalog', 'font-size:13px; color:#34D399;');
    console.log('%c🔵 Blue Theme Applied with beautiful CSS', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🔒 Login session: ACTIVE', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Admin has FULL ACCESS: View, Edit, Delete', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>