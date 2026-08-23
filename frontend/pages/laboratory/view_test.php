<?php
// ================================================================
// FILE: frontend/pages/laboratory/view_test.php
// VIEW LAB TEST - WITH PDF DOWNLOAD & ULTRASOUND TEMPLATES
// USING NEW DATABASE: dispensary_db
// WITH FULL LOGIN SESSION PROTECTION
// MODERN DESIGN - PHARMACY STYLE
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// SESSION START
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION - CHECK IF USER IS LOGGED IN
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// CHECK IF USER IS LABORATORY OR ADMIN
// ================================================================
$allowed_roles = ['laboratory', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
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

// ================================================================
// GET USER DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Lab Technician';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_username = $_SESSION['username'] ?? 'lab.technician';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// DATABASE CONNECTION - NEW DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// GET TEST ID
// ================================================================
$test_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$print_mode = isset($_GET['print']) && $_GET['print'] == 1;

if ($test_id <= 0) {
    header('Location: pending_tests.php');
    exit;
}

// ================================================================
// GET TEST DETAILS - FIXED: Removed result_template_id
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
        p.emergency_contact,
        p.blood_group,
        p.allergies,
        v.visit_number,
        v.visit_date,
        v.visit_type,
        u.full_name as doctor_name,
        u.specialty as doctor_specialty,
        u.email as doctor_email,
        u.phone as doctor_phone,
        t.full_name as technician_name,
        b.name as branch_name,
        ltc.category as test_category,
        ltc.price as test_price,
        ltc.reference_range,
        ltc.description as test_description
    FROM lab_tests lt
    LEFT JOIN patients p ON lt.patient_id = p.id
    LEFT JOIN visits v ON lt.visit_id = v.id
    LEFT JOIN users u ON lt.doctor_id = u.id
    LEFT JOIN users t ON lt.lab_technician_id = t.id
    LEFT JOIN branches b ON lt.branch_id = b.id
    LEFT JOIN lab_tests_catalog ltc ON lt.test_id = ltc.id
    WHERE lt.id = ? AND lt.branch_id = ?
");
$stmt->execute([$test_id, $user_branch_id]);
$test = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$test) {
    header('Location: pending_tests.php?error=test_not_found');
    exit;
}

// ================================================================
// GET UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
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
        'in_progress' => '🔄 In Progress',
        'completed' => '✅ Completed',
        'cancelled' => '❌ Cancelled'
    ];
    return $map[$status] ?? $status;
}

function getStatusIcon($status) {
    $map = [
        'pending' => 'fa-clock',
        'in_progress' => 'fa-spinner fa-spin',
        'completed' => 'fa-check-circle',
        'cancelled' => 'fa-times-circle'
    ];
    return $map[$status] ?? 'fa-circle';
}

function formatDate($datetime) {
    if (empty($datetime)) return 'N/A';
    return date('d/m/Y h:i A', strtotime($datetime));
}

function formatDateShort($datetime) {
    if (empty($datetime)) return 'N/A';
    return date('d/m/Y', strtotime($datetime));
}

// ================================================================
// PROFILE PICTURE
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/laboratory_header.php';
include_once __DIR__ . '/../../components/laboratory_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Test - <?= htmlspecialchars($test['test_name'] ?? 'N/A') ?> - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
    <style>
        /* ================================================================
           ROOT VARIABLES
           ================================================================ */
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
            --primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
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
            --radius: 10px;
            --radius-lg: 14px;
            --transition: all 0.3s ease;
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 30px rgba(0,0,0,0.12);
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
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
           PAGE HEADER
           ================================================================ */
        .page-header {
            background: var(--primary-gradient);
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
        
        .page-header::before {
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
        
        .page-header .branch-tag {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            backdrop-filter: blur(4px);
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 16px;
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
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        .status-badge-lg {
            display: inline-block;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge-warning { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning); }
        .badge-info { background: var(--primary-bg); color: var(--primary); border: 1px solid var(--primary); }
        .badge-success { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
        .badge-danger { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger); }
        
        /* ================================================================
           CARDS
           ================================================================ */
        .card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            padding: 22px 26px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }
        
        .card:hover {
            border-color: var(--primary-light);
            box-shadow: var(--shadow-md);
        }
        
        .card-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 12px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .card-title i {
            color: var(--primary);
            font-size: 1.1rem;
        }
        
        .card-title .badge-count {
            background: var(--primary);
            color: white;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        
        .detail-row {
            display: flex;
            padding: 6px 0;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.85rem;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            font-weight: 600;
            color: var(--text-secondary);
            width: 140px;
            flex-shrink: 0;
        }
        
        .detail-value {
            flex: 1;
            color: var(--text-primary);
        }
        
        /* ================================================================
           RESULT BOX
           ================================================================ */
        .result-box {
            background: var(--primary-bg);
            border-radius: var(--radius);
            padding: 16px 20px;
            border: 2px solid var(--primary-light);
            margin-top: 8px;
        }
        
        .result-box .result-label {
            font-size: 0.65rem;
            color: var(--text-secondary);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .result-box .result-value {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary-dark);
            margin-top: 2px;
        }
        
        .result-box .result-meta {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 6px;
            padding-top: 6px;
            border-top: 1px solid var(--border-color);
        }
        
        [data-theme="dark"] .result-box {
            background: #1E3A5F;
            border-color: var(--primary-dark);
        }
        
        [data-theme="dark"] .result-box .result-value {
            color: var(--primary-light);
        }
        
        /* ================================================================
           ULTRASOUND RESULT DISPLAY
           ================================================================ */
        .ultrasound-result-container {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px solid var(--primary-light);
            padding: 24px 28px;
            margin-top: 8px;
            box-shadow: var(--shadow-md);
        }
        
        .ultrasound-result-container .ultrasound-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid var(--primary);
            padding-bottom: 12px;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .ultrasound-result-container .ultrasound-header .header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .ultrasound-result-container .ultrasound-header .header-left img {
            height: 45px;
            width: auto;
            object-fit: contain;
        }
        
        .ultrasound-result-container .ultrasound-header .header-left .clinic-name {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .ultrasound-result-container .ultrasound-header .header-left .clinic-sub {
            font-size: 0.65rem;
            color: var(--text-secondary);
        }
        
        .ultrasound-result-container .ultrasound-header .test-title {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--primary-dark);
            background: var(--primary-bg);
            padding: 4px 16px;
            border-radius: 20px;
        }
        
        .ultrasound-result-container .patient-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px 20px;
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 12px;
        }
        
        .ultrasound-result-container .patient-info-grid .info-item {
            font-size: 0.8rem;
        }
        
        .ultrasound-result-container .patient-info-grid .info-item .info-label {
            font-weight: 600;
            color: var(--text-secondary);
        }
        
        .ultrasound-result-container .patient-info-grid .info-item .info-value {
            color: var(--text-primary);
        }
        
        .ultrasound-result-container .findings-section {
            margin-bottom: 12px;
        }
        
        .ultrasound-result-container .findings-section .section-label {
            font-weight: 700;
            color: var(--primary);
            font-size: 0.85rem;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 4px;
            margin-bottom: 8px;
        }
        
        .ultrasound-result-container .findings-section .finding-row {
            display: flex;
            padding: 3px 0;
            font-size: 0.8rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .ultrasound-result-container .findings-section .finding-row:last-child {
            border-bottom: none;
        }
        
        .ultrasound-result-container .findings-section .finding-row .finding-label {
            font-weight: 600;
            color: var(--text-secondary);
            width: 160px;
            flex-shrink: 0;
        }
        
        .ultrasound-result-container .findings-section .finding-row .finding-value {
            flex: 1;
            color: var(--text-primary);
        }
        
        .ultrasound-result-container .conclusion-section {
            background: var(--primary-bg);
            padding: 12px 16px;
            border-radius: var(--radius);
            border-left: 4px solid var(--primary);
            margin-top: 8px;
        }
        
        .ultrasound-result-container .conclusion-section .conclusion-label {
            font-weight: 700;
            color: var(--primary);
            font-size: 0.8rem;
        }
        
        .ultrasound-result-container .conclusion-section .conclusion-text {
            font-size: 0.85rem;
            color: var(--text-primary);
            margin-top: 2px;
        }
        
        .ultrasound-result-container .report-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 12px;
            border-top: 2px solid var(--border-color);
            margin-top: 16px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .ultrasound-result-container .report-footer .footer-left {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.75rem;
            color: var(--text-secondary);
        }
        
        .ultrasound-result-container .report-footer .footer-left .signature-line {
            display: inline-block;
            width: 120px;
            border-bottom: 1px solid var(--text-secondary);
            margin-left: 4px;
        }
        
        .ultrasound-result-container .report-footer .stamp-box {
            text-align: center;
            padding: 6px 16px;
            border: 2px solid var(--primary);
            border-radius: 8px;
            background: var(--primary-bg);
            min-width: 150px;
        }
        
        .ultrasound-result-container .report-footer .stamp-box .stamp-title {
            font-size: 0.6rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
        }
        
        .ultrasound-result-container .report-footer .stamp-box .stamp-name {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .ultrasound-result-container .report-footer .stamp-box .stamp-line {
            font-size: 0.65rem;
            color: var(--text-secondary);
        }
        
        .ultrasound-result-container .report-footer .stamp-box .stamp-date {
            font-size: 0.6rem;
            color: var(--text-muted);
        }
        
        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.82rem;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-sm {
            padding: 5px 12px;
            font-size: 0.7rem;
            border-radius: 6px;
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
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background: var(--success-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
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
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background: #991B1B;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
        }
        
        /* ================================================================
           PDF MODAL
           ================================================================ */
        .pdf-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 9999;
            backdrop-filter: blur(4px);
            justify-content: center;
            align-items: center;
        }
        
        .pdf-modal-overlay.active {
            display: flex;
        }
        
        .pdf-modal {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            width: 95%;
            max-width: 1100px;
            max-height: 95vh;
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow-lg);
            animation: slideUp 0.3s ease;
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        
        .pdf-modal-header {
            padding: 16px 24px;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            background: var(--primary-gradient);
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
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
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .pdf-modal-header .modal-actions .btn {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .pdf-modal-header .modal-actions .btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }
        
        .pdf-modal-header .modal-actions .btn-danger-modal {
            background: rgba(220,38,38,0.3);
            border-color: rgba(220,38,38,0.2);
        }
        
        .pdf-modal-header .modal-actions .btn-danger-modal:hover {
            background: rgba(220,38,38,0.5);
        }
        
        .pdf-modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 24px 32px;
            background: var(--bg-body);
        }
        
        .pdf-modal-body .pdf-content {
            max-width: 100%;
            font-size: 0.85rem;
            background: var(--bg-card);
            padding: 32px 40px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
        }
        
        /* PDF Content Styles */
        .pdf-content .pdf-header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 3px solid var(--primary);
            margin-bottom: 24px;
        }
        
        .pdf-content .pdf-header .pdf-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            margin-bottom: 6px;
        }
        
        .pdf-content .pdf-header .pdf-logo img {
            height: 55px;
            width: auto;
            object-fit: contain;
        }
        
        .pdf-content .pdf-header .clinic-name {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--primary);
            letter-spacing: -0.5px;
        }
        
        .pdf-content .pdf-header .clinic-sub {
            font-size: 0.8rem;
            color: var(--text-secondary);
            letter-spacing: 0.5px;
        }
        
        .pdf-content .pdf-header .test-info {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--primary);
            margin-top: 6px;
            background: var(--primary-bg);
            padding: 4px 16px;
            border-radius: 20px;
            display: inline-block;
        }
        
        .pdf-content .section-title {
            font-weight: 700;
            font-size: 1rem;
            color: var(--primary);
            border-bottom: 2px solid var(--primary-light);
            padding-bottom: 6px;
            margin: 18px 0 10px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .pdf-content .pdf-row {
            display: flex;
            padding: 4px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .pdf-content .pdf-row .pdf-label {
            font-weight: 600;
            color: var(--text-secondary);
            width: 160px;
            flex-shrink: 0;
        }
        
        .pdf-content .pdf-row .pdf-value {
            flex: 1;
            color: var(--text-primary);
        }
        
        .pdf-content .pdf-footer {
            text-align: center;
            padding-top: 20px;
            border-top: 2px solid var(--border-color);
            margin-top: 24px;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .pdf-content .pdf-footer .footer-brand {
            color: var(--primary);
            font-weight: 700;
        }
        
        /* ================================================================
           TOAST
           ================================================================ */
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 12px 18px;
            border-radius: 10px;
            z-index: 999;
            max-width: 380px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            box-shadow: var(--shadow-lg);
            font-size: 0.85rem;
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
            .ultrasound-result-container .patient-info-grid { grid-template-columns: 1fr; }
            .ultrasound-result-container .findings-section .finding-row { flex-direction: column; }
            .ultrasound-result-container .findings-section .finding-row .finding-label { width: 100%; }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .detail-row { flex-direction: column; }
            .detail-label { width: 100%; margin-bottom: 2px; }
            .card { padding: 14px 16px; }
            .pdf-modal-body .pdf-content { padding: 16px; }
            .pdf-content .pdf-row { flex-direction: column; }
            .pdf-content .pdf-row .pdf-label { width: 100%; }
            .btn { padding: 4px 10px; font-size: 0.7rem; }
            .pdf-modal-header { flex-direction: column; gap: 10px; align-items: stretch; }
            .pdf-modal-header .modal-actions { justify-content: center; }
            .ultrasound-result-container { padding: 12px 14px; }
            .ultrasound-result-container .report-footer { flex-direction: column; align-items: flex-start; }
            .ultrasound-result-container .report-footer .stamp-box { width: 100%; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .page-header .page-title { font-size: 1.1rem; }
            .page-header .btn-outline-light { padding: 4px 8px; font-size: 0.65rem; }
            .result-box { padding: 10px 14px; }
            .result-box .result-value { font-size: 0.95rem; }
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
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-flask"></i>
                Test Details
                <span class="role-badge-display">LABORATORY</span>
                <span class="status-badge-lg <?= getStatusBadgeClass($test['status'] ?? 'pending') ?>" style="background:rgba(255,255,255,0.2);color:white;border-color:rgba(255,255,255,0.2);">
                    <i class="fas <?= getStatusIcon($test['status'] ?? 'pending') ?>"></i>
                    <?= getStatusLabel($test['status'] ?? 'pending') ?>
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-vial"></i>
                Test: <strong><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></strong>
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($test['branch_name'] ?? $user_branch_name) ?>
                </span>
                <span class="branch-tag">
                    <i class="fas fa-hashtag"></i> ID: <?= $test['id'] ?>
                </span>
                <?php if (!empty($test['patient_name'])): ?>
                    <span class="branch-tag">
                        <i class="fas fa-user"></i> <?= htmlspecialchars($test['patient_name']) ?>
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <?php if ($test['status'] !== 'cancelled'): ?>
                <button onclick="generatePDF()" class="btn-outline-light">
                    <i class="fas fa-file-pdf"></i> Download PDF
                </button>
            <?php endif; ?>
            <button onclick="window.print()" class="btn-outline-light">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="pending_tests.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PATIENT INFORMATION -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up">
        <h3 class="card-title">
            <i class="fas fa-user-circle"></i>
            Patient Information
            <?php if (!empty($test['gender'])): ?>
                <span class="badge-count">
                    <?= $test['gender'] === 'Female' ? '👩' : '👨' ?>
                    <?= $test['gender'] ?>
                    <?php if (!empty($test['date_of_birth'])): ?>
                        • <?= calculateAge($test['date_of_birth']) ?> yrs
                    <?php endif; ?>
                </span>
            <?php endif; ?>
        </h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:2px 20px;">
            <div class="detail-row"><span class="detail-label">Full Name</span><span class="detail-value"><strong><?= htmlspecialchars($test['patient_name'] ?? 'N/A') ?></strong></span></div>
            <div class="detail-row"><span class="detail-label">Patient ID</span><span class="detail-value"><?= htmlspecialchars($test['patient_code'] ?? 'N/A') ?></span></div>
            <div class="detail-row"><span class="detail-label">Gender</span><span class="detail-value"><?= htmlspecialchars($test['gender'] ?? 'N/A') ?></span></div>
            <div class="detail-row"><span class="detail-label">Date of Birth</span><span class="detail-value"><?= !empty($test['date_of_birth']) ? date('d/m/Y', strtotime($test['date_of_birth'])) : 'N/A' ?></span></div>
            <div class="detail-row"><span class="detail-label">Phone</span><span class="detail-value"><?= htmlspecialchars($test['phone'] ?? 'N/A') ?></span></div>
            <div class="detail-row"><span class="detail-label">Blood Group</span><span class="detail-value"><?= htmlspecialchars($test['blood_group'] ?? 'N/A') ?></span></div>
            <div class="detail-row"><span class="detail-label">Allergies</span><span class="detail-value"><?= htmlspecialchars($test['allergies'] ?? 'None') ?></span></div>
            <div class="detail-row"><span class="detail-label">Emergency Contact</span><span class="detail-value"><?= htmlspecialchars($test['emergency_contact'] ?? 'N/A') ?></span></div>
            <div class="detail-row" style="grid-column: span 2;"><span class="detail-label">Address</span><span class="detail-value"><?= htmlspecialchars($test['address'] ?? 'N/A') ?></span></div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- TEST INFORMATION -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up">
        <h3 class="card-title">
            <i class="fas fa-vial"></i>
            Test Information
            <span class="badge-count">
                <i class="fas fa-tag"></i> <?= htmlspecialchars($test['test_category'] ?? 'N/A') ?>
            </span>
            <?php if (!empty($test['test_price']) && $test['test_price'] > 0): ?>
                <span class="badge-count" style="background:var(--success);">
                    TSh <?= number_format($test['test_price']) ?>
                </span>
            <?php endif; ?>
        </h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:2px 20px;">
            <div class="detail-row"><span class="detail-label">Test Name</span><span class="detail-value"><strong><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></strong></span></div>
            <div class="detail-row"><span class="detail-label">Test Type</span><span class="detail-value"><?= htmlspecialchars($test['test_type'] ?? 'N/A') ?></span></div>
            <div class="detail-row"><span class="detail-label">Sample Type</span><span class="detail-value"><?= htmlspecialchars($test['sample_type'] ?? 'N/A') ?></span></div>
            <div class="detail-row"><span class="detail-label">Reference Range</span><span class="detail-value"><?= htmlspecialchars($test['reference_range'] ?? 'N/A') ?></span></div>
            <div class="detail-row"><span class="detail-label">Doctor</span><span class="detail-value"><?= htmlspecialchars($test['doctor_name'] ?? 'Not Assigned') ?></span></div>
            <div class="detail-row"><span class="detail-label">Technician</span><span class="detail-value"><?= htmlspecialchars($test['technician_name'] ?? 'Not Assigned') ?></span></div>
            <div class="detail-row"><span class="detail-label">Visit Number</span><span class="detail-value"><?= htmlspecialchars($test['visit_number'] ?? 'N/A') ?></span></div>
            <div class="detail-row"><span class="detail-label">Visit Type</span><span class="detail-value"><?= ucfirst(htmlspecialchars($test['visit_type'] ?? 'N/A')) ?></span></div>
            <div class="detail-row"><span class="detail-label">Created</span><span class="detail-value"><?= formatDate($test['created_at'] ?? '') ?></span></div>
            <?php if (!empty($test['started_at'])): ?>
                <div class="detail-row"><span class="detail-label">Started</span><span class="detail-value"><?= formatDate($test['started_at']) ?></span></div>
            <?php endif; ?>
            <?php if (!empty($test['completed_at'])): ?>
                <div class="detail-row"><span class="detail-label">Completed</span><span class="detail-value"><?= formatDate($test['completed_at']) ?></span></div>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($test['test_description'])): ?>
            <div class="detail-row" style="margin-top:10px;border-top:2px solid var(--border-color);padding-top:10px;">
                <span class="detail-label">Description</span>
                <span class="detail-value"><?= htmlspecialchars($test['test_description']) ?></span>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- TEST RESULTS - WITH ULTRASOUND TEMPLATE SUPPORT -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up" style="border-color:<?= $test['status'] === 'completed' ? 'var(--success)' : 'var(--warning)' ?>;border-left:4px solid <?= $test['status'] === 'completed' ? 'var(--success)' : 'var(--warning)' ?>;">
        <h3 class="card-title">
            <i class="fas fa-file-medical-alt" style="color:<?= $test['status'] === 'completed' ? 'var(--success)' : 'var(--warning)' ?>;"></i>
            Test Results
            <?php if ($test['status'] === 'completed'): ?>
                <span class="badge-count" style="background:var(--success);">
                    <i class="fas fa-check-circle"></i> Completed
                </span>
            <?php elseif ($test['status'] === 'in_progress'): ?>
                <span class="badge-count" style="background:var(--primary);">
                    <i class="fas fa-spinner fa-spin"></i> In Progress
                </span>
            <?php elseif ($test['status'] === 'cancelled'): ?>
                <span class="badge-count" style="background:var(--danger);">
                    <i class="fas fa-times-circle"></i> Cancelled
                </span>
            <?php else: ?>
                <span class="badge-count" style="background:var(--warning);">
                    <i class="fas fa-clock"></i> Pending
                </span>
            <?php endif; ?>
        </h3>
        
        <?php if ($test['status'] === 'completed' && !empty($test['results'])): ?>
            
            <?php 
            // Check if this is an ultrasound test (category contains 'ultrasound')
            $is_ultrasound = stripos($test['test_category'] ?? '', 'ultrasound') !== false;
            $is_obstetric = stripos($test['test_name'] ?? '', 'obstetric') !== false;
            $is_abdominal = stripos($test['test_name'] ?? '', 'abdominal') !== false;
            
            // Parse formatted_result for ultrasound
            $ultrasound_data = [];
            if (!empty($test['formatted_result'])) {
                $ultrasound_data = json_decode($test['formatted_result'], true);
            }
            ?>
            
            <?php if ($is_ultrasound && !empty($test['formatted_result'])): ?>
                
                <!-- ULTRASOUND RESULT DISPLAY -->
                <div class="ultrasound-result-container">
                    <div class="ultrasound-header">
                        <div class="header-left">
                            <img src="/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png" alt="Braick Logo" onerror="this.style.display='none'">
                            <div>
                                <div class="clinic-name">BRAICK DISPENSARY</div>
                                <div class="clinic-sub">Quality Healthcare Services</div>
                            </div>
                        </div>
                        <div class="test-title"><?= htmlspecialchars($test['test_name'] ?? 'Ultrasound Report') ?></div>
                    </div>
                    
                    <div class="patient-info-grid">
                        <div class="info-item">
                            <span class="info-label">Patient Name:</span>
                            <span class="info-value"><?= htmlspecialchars($test['patient_name'] ?? 'N/A') ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Patient ID:</span>
                            <span class="info-value"><?= htmlspecialchars($test['patient_code'] ?? 'N/A') ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Age/Sex:</span>
                            <span class="info-value"><?= calculateAge($test['date_of_birth'] ?? '') ?> yrs / <?= htmlspecialchars($test['gender'] ?? 'N/A') ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Date of Exam:</span>
                            <span class="info-value"><?= formatDateShort($test['created_at'] ?? '') ?></span>
                        </div>
                    </div>
                    
                    <div class="findings-section">
                        <div class="section-label">📋 FINDINGS</div>
                        <?php 
                        $ultrasound_fields = [
                            'liver' => 'Liver',
                            'gallbladder' => 'Gallbladder',
                            'pancreas' => 'Pancreas',
                            'spleen' => 'Spleen',
                            'peritoneum' => 'Peritoneum',
                            'kidneys' => 'Kidneys',
                            'bladder' => 'Urinary Bladder',
                            'uterus' => 'Uterus',
                            'right_ovary' => 'Right Ovary',
                            'left_ovary' => 'Left Ovary',
                            'pouch_douglas' => 'Pouch of Douglas',
                            'presentation' => 'Presentation and Lie',
                            'placenta' => 'Placenta',
                            'fetal_activity' => 'Fetal Activity',
                            'amniotic_fluid' => 'Amniotic Fluid',
                            'anatomical_structures' => 'Anatomical Structures',
                            'maternal_kidney' => 'Maternal Kidney',
                            'embryo' => 'Embryo',
                            'crl' => 'CRL (Crown Rump Length)',
                            'ga' => 'Gestational Age (GA)',
                            'fetal_pole' => 'Fetal Pole',
                            'yolk_sac' => 'Yolk Sac',
                            'myometrium' => 'Myometrium',
                            'cervix' => 'Cervix',
                            'adnexa' => 'Adnexal Areas',
                            'maternal_organs' => 'Maternal Organs',
                            'prostate' => 'Prostate'
                        ];
                        ?>
                        <?php foreach ($ultrasound_fields as $key => $label): ?>
                            <?php if (isset($ultrasound_data[$key]) && !empty($ultrasound_data[$key])): ?>
                                <div class="finding-row">
                                    <span class="finding-label"><?= $label ?>:</span>
                                    <span class="finding-value"><?= nl2br(htmlspecialchars($ultrasound_data[$key])) ?></span>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (isset($ultrasound_data['conclusion']) && !empty($ultrasound_data['conclusion'])): ?>
                        <div class="conclusion-section">
                            <div class="conclusion-label">📌 IMPRESSION / CONCLUSION</div>
                            <div class="conclusion-text"><?= nl2br(htmlspecialchars($ultrasound_data['conclusion'])) ?></div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="report-footer">
                        <div class="footer-left">
                            <span>Technician: <?= htmlspecialchars($test['technician_name'] ?? '_________________') ?></span>
                            <span style="margin-left:20px;">Date: <?= date('d/m/Y H:i') ?></span>
                        </div>
                        <div class="stamp-box">
                            <div class="stamp-title">Official Stamp</div>
                            <div class="stamp-name">BRAICK DISPENSARY</div>
                            <div class="stamp-line">Approved By: _________________</div>
                            <div class="stamp-date">Date: <?= date('d/m/Y H:i') ?></div>
                        </div>
                    </div>
                </div>
                
            <?php else: ?>
                
                <!-- NORMAL RESULT DISPLAY -->
                <div class="result-box">
                    <div>
                        <span class="result-label">Result</span>
                        <div class="result-value"><?= nl2br(htmlspecialchars($test['results'] ?? 'N/A')) ?></div>
                    </div>
                    <?php if (!empty($test['reference_range'])): ?>
                        <div class="result-meta">
                            <span class="result-label">Reference Range:</span>
                            <span style="font-weight:500;"><?= htmlspecialchars($test['reference_range']) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($test['interpretation'])): ?>
                        <div class="result-meta">
                            <span class="result-label">Interpretation:</span>
                            <span style="font-weight:500;"><?= nl2br(htmlspecialchars($test['interpretation'])) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                
            <?php endif; ?>
            
        <?php elseif ($test['status'] === 'in_progress'): ?>
            <div style="text-align:center;padding:30px 20px;color:var(--text-secondary);">
                <i class="fas fa-spinner fa-spin text-4xl" style="color:var(--primary);"></i>
                <p style="margin-top:12px;font-size:1rem;font-weight:600;">Test In Progress</p>
                <p style="font-size:0.85rem;">Results will appear once the test is completed</p>
                <?php if (!empty($test['started_at'])): ?>
                    <p style="font-size:0.75rem;color:var(--text-muted);margin-top:4px;">Started: <?= formatDate($test['started_at']) ?></p>
                <?php endif; ?>
            </div>
        <?php elseif ($test['status'] === 'cancelled'): ?>
            <div style="text-align:center;padding:30px 20px;color:var(--text-secondary);">
                <i class="fas fa-times-circle text-4xl" style="color:var(--danger);"></i>
                <p style="margin-top:12px;font-size:1rem;font-weight:600;">Test Cancelled</p>
                <p style="font-size:0.85rem;">This test has been cancelled</p>
                <?php if (!empty($test['notes'])): ?>
                    <p style="font-size:0.75rem;color:var(--text-muted);margin-top:4px;">Reason: <?= htmlspecialchars($test['notes']) ?></p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div style="text-align:center;padding:30px 20px;color:var(--text-secondary);">
                <i class="fas fa-clock text-4xl" style="color:var(--warning);"></i>
                <p style="margin-top:12px;font-size:1rem;font-weight:600;">Test Pending</p>
                <p style="font-size:0.85rem;">Results not yet available</p>
                <p style="font-size:0.75rem;color:var(--text-muted);margin-top:4px;">Created: <?= formatDate($test['created_at'] ?? '') ?></p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- NOTES -->
    <!-- ================================================================ -->
    <?php if (!empty($test['notes']) && $test['status'] !== 'cancelled'): ?>
        <div class="card animate-fade-in-up">
            <h3 class="card-title">
                <i class="fas fa-sticky-note" style="color:var(--warning);"></i>
                Notes
            </h3>
            <div class="detail-row">
                <span class="detail-label">Notes</span>
                <span class="detail-value"><?= nl2br(htmlspecialchars($test['notes'] ?? '')) ?></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            View Test
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- PDF MODAL -->
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
                <button onclick="closePDFModal()" class="btn btn-sm btn-danger-modal">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
        <div class="pdf-modal-body" id="pdfModalBody">
            <div class="pdf-content" id="pdfContent">
                <!-- PDF content generated by JavaScript -->
            </div>
        </div>
    </div>
</div>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle"></i>
    <div>
        <p style="font-weight:600;font-size:0.8rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.7rem;opacity:0.9;margin:0;" id="toastMessage"></p>
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
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            if (sidebar) sidebar.classList.toggle('open');
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
        var currentDateTime = document.getElementById('currentDateTime');
        if (currentDateTime) {
            currentDateTime.textContent = dateStr + ' • ' + timeStr;
        }
        var footerTimestamp = document.getElementById('footerTimestamp');
        if (footerTimestamp) {
            footerTimestamp.textContent = 'Last updated: ' + timeStr;
        }
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            window.location.href = 'search.php?q=' + encodeURIComponent(query);
        }
    }
    
    if (searchBtn) {
        searchBtn.addEventListener('click', performSearch);
    }
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') performSearch();
        });
    }

    // ================================================================
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        
        if (!toast) return;
        
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
    // GENERATE PDF - FIXED: No result_template_id
    // ================================================================
    function generatePDF() {
        var modal = document.getElementById('pdfModal');
        var content = document.getElementById('pdfContent');
        
        var statusLabel = '<?= getStatusLabel($test['status'] ?? 'pending') ?>';
        var statusClass = '<?= $test['status'] ?? 'pending' ?>';
        var statusColor = statusClass === 'completed' ? '#059669' : (statusClass === 'in_progress' ? '#0B5ED7' : (statusClass === 'cancelled' ? '#DC2626' : '#D97706'));
        
        // Check if ultrasound based on category or test_name
        var isUltrasound = <?= (stripos($test['test_category'] ?? '', 'ultrasound') !== false || stripos($test['test_name'] ?? '', 'ultrasound') !== false) ? 'true' : 'false' ?>;
        var ultrasoundData = <?= !empty($test['formatted_result']) ? json_encode(json_decode($test['formatted_result'], true)) : '{}' ?>;
        
        var html = '';
        
        if (isUltrasound && ultrasoundData && Object.keys(ultrasoundData).length > 0) {
            // ================================================================
            // ULTRASOUND PDF TEMPLATE
            // ================================================================
            var findingsHtml = '';
            var ultrasoundFields = {
                'liver': 'Liver',
                'gallbladder': 'Gallbladder',
                'pancreas': 'Pancreas',
                'spleen': 'Spleen',
                'peritoneum': 'Peritoneum',
                'kidneys': 'Kidneys',
                'bladder': 'Urinary Bladder',
                'uterus': 'Uterus',
                'right_ovary': 'Right Ovary',
                'left_ovary': 'Left Ovary',
                'pouch_douglas': 'Pouch of Douglas',
                'presentation': 'Presentation and Lie',
                'placenta': 'Placenta',
                'fetal_activity': 'Fetal Activity',
                'amniotic_fluid': 'Amniotic Fluid',
                'anatomical_structures': 'Anatomical Structures',
                'maternal_kidney': 'Maternal Kidney',
                'embryo': 'Embryo',
                'crl': 'CRL (Crown Rump Length)',
                'ga': 'Gestational Age (GA)',
                'fetal_pole': 'Fetal Pole',
                'yolk_sac': 'Yolk Sac',
                'myometrium': 'Myometrium',
                'cervix': 'Cervix',
                'adnexa': 'Adnexal Areas',
                'maternal_organs': 'Maternal Organs',
                'prostate': 'Prostate'
            };
            
            for (var key in ultrasoundFields) {
                if (ultrasoundData[key] && ultrasoundData[key].trim() !== '') {
                    findingsHtml += `
                        <div class="pdf-row">
                            <span class="pdf-label">${ultrasoundFields[key]}:</span>
                            <span class="pdf-value">${ultrasoundData[key]}</span>
                        </div>
                    `;
                }
            }
            
            html = `
                <div class="pdf-header">
                    <div class="pdf-logo">
                        <img src="/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png" alt="Braick Logo" onerror="this.style.display='none'">
                        <span class="clinic-name">BRAICK DISPENSARY</span>
                    </div>
                    <div class="clinic-sub">Quality Healthcare Services • <?= htmlspecialchars($test['branch_name'] ?? $user_branch_name) ?></div>
                    <div class="test-info">🧪 <?= htmlspecialchars($test['test_name'] ?? 'Ultrasound Report') ?></div>
                    <div style="font-size:0.75rem;color:var(--text-secondary);margin-top:4px;">
                        Status: <span style="color:${statusColor};font-weight:700;">${statusLabel}</span> • 
                        ID: #<?= $test['id'] ?> • 
                        Date: <?= formatDate($test['created_at'] ?? '') ?>
                    </div>
                </div>
                
                <!-- Patient Information -->
                <div class="section-title">👤 Patient Information</div>
                <div class="pdf-row"><span class="pdf-label">Patient Name</span><span class="pdf-value"><?= htmlspecialchars($test['patient_name'] ?? 'N/A') ?></span></div>
                <div class="pdf-row"><span class="pdf-label">Patient ID</span><span class="pdf-value"><?= htmlspecialchars($test['patient_code'] ?? 'N/A') ?></span></div>
                <div class="pdf-row"><span class="pdf-label">Age/Sex</span><span class="pdf-value"><?= calculateAge($test['date_of_birth'] ?? '') ?> yrs / <?= htmlspecialchars($test['gender'] ?? 'N/A') ?></span></div>
                <div class="pdf-row"><span class="pdf-label">Date of Exam</span><span class="pdf-value"><?= formatDateShort($test['created_at'] ?? '') ?></span></div>
                <div class="pdf-row"><span class="pdf-label">Phone</span><span class="pdf-value"><?= htmlspecialchars($test['phone'] ?? 'N/A') ?></span></div>
                <div class="pdf-row"><span class="pdf-label">Blood Group</span><span class="pdf-value"><?= htmlspecialchars($test['blood_group'] ?? 'N/A') ?></span></div>
                <div class="pdf-row"><span class="pdf-label">Doctor</span><span class="pdf-value"><?= htmlspecialchars($test['doctor_name'] ?? 'Not Assigned') ?></span></div>
                <div class="pdf-row"><span class="pdf-label">Technician</span><span class="pdf-value"><?= htmlspecialchars($test['technician_name'] ?? 'Not Assigned') ?></span></div>
                
                <!-- Findings -->
                <div class="section-title">📋 Findings</div>
                ${findingsHtml || '<div style="padding:8px 12px;color:var(--text-secondary);">No findings recorded</div>'}
                
                <!-- Conclusion -->
                ${ultrasoundData.conclusion ? `
                    <div class="section-title">📌 Impression / Conclusion</div>
                    <div style="padding:12px 16px;background:var(--primary-bg);border-radius:8px;border:1px solid var(--primary-light);">
                        ${ultrasoundData.conclusion}
                    </div>
                ` : ''}
                
                <!-- Footer -->
                <div class="pdf-footer">
                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;padding:12px 0;border-top:2px solid var(--border-color);margin-top:16px;">
                        <div style="font-size:0.75rem;color:var(--text-secondary);">
                            Technician: ${ultrasoundData.technician_name || '<?= htmlspecialchars($test['technician_name'] ?? '_________________') ?>'}
                            <span style="margin-left:20px;">Date: <?= date('d/m/Y H:i') ?></span>
                        </div>
                        <div style="text-align:center;padding:6px 16px;border:2px solid var(--primary);border-radius:8px;background:var(--primary-bg);min-width:150px;">
                            <div style="font-size:0.6rem;color:var(--text-secondary);text-transform:uppercase;letter-spacing:1px;font-weight:700;">Official Stamp</div>
                            <div style="font-size:0.8rem;font-weight:700;color:var(--primary);">BRAICK DISPENSARY</div>
                            <div style="font-size:0.65rem;color:var(--text-secondary);">Approved By: _________________</div>
                            <div style="font-size:0.6rem;color:var(--text-muted);">Date: <?= date('d/m/Y H:i') ?></div>
                        </div>
                    </div>
                    <div style="margin-top:12px;text-align:center;font-size:0.65rem;color:var(--text-muted);">
                        <span class="footer-brand">Braick Dispensary</span> • Generated on <?= date('M d, Y h:i A') ?> • All rights reserved
                    </div>
                </div>
            `;
        } else {
            // ================================================================
            // NORMAL PDF TEMPLATE
            // ================================================================
            var resultsContent = '<?= addslashes(nl2br(htmlspecialchars($test['results'] ?? ''))) ?>';
            var interpretationContent = '<?= addslashes(nl2br(htmlspecialchars($test['interpretation'] ?? ''))) ?>';
            
            html = `
                <div class="pdf-header">
                    <div class="pdf-logo">
                        <img src="/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png" alt="Braick Logo" onerror="this.style.display='none'">
                        <span class="clinic-name">BRAICK DISPENSARY</span>
                    </div>
                    <div class="clinic-sub">Quality Healthcare Services • <?= htmlspecialchars($test['branch_name'] ?? $user_branch_name) ?></div>
                    <div class="test-info">🧪 <?= htmlspecialchars($test['test_name'] ?? 'Test Report') ?></div>
                    <div style="font-size:0.75rem;color:var(--text-secondary);margin-top:4px;">
                        Status: <span style="color:${statusColor};font-weight:700;">${statusLabel}</span> • 
                        ID: #<?= $test['id'] ?> • 
                        Date: <?= formatDate($test['created_at'] ?? '') ?>
                    </div>
                </div>
                
                <!-- Patient Information -->
                <div class="section-title">👤 Patient Information</div>
                <div class="pdf-row"><span class="pdf-label">Full Name</span><span class="pdf-value"><?= htmlspecialchars($test['patient_name'] ?? 'N/A') ?></span></div>
                <div class="pdf-row"><span class="pdf-label">Patient ID</span><span class="pdf-value"><?= htmlspecialchars($test['patient_code'] ?? 'N/A') ?></span></div>
                <div class="pdf-row"><span class="pdf-label">Gender</span><span class="pdf-value"><?= htmlspecialchars($test['gender'] ?? 'N/A') ?></span></div>
                <div class="pdf-row"><span class="pdf-label">Date of Birth</span><span class="pdf-value"><?= !empty($test['date_of_birth']) ? date('d/m/Y', strtotime($test['date_of_birth'])) : 'N/A' ?> (<?= calculateAge($test['date_of_birth'] ?? '') ?> years)</span></div>
                <div class="pdf-row"><span class="pdf-label">Phone</span><span class="pdf-value"><?= htmlspecialchars($test['phone'] ?? 'N/A') ?></span></div>
                <div class="pdf-row"><span class="pdf-label">Blood Group</span><span class="pdf-value"><?= htmlspecialchars($test['blood_group'] ?? 'N/A') ?></span></div>
                <div class="pdf-row"><span class="pdf-label">Allergies</span><span class="pdf-value"><?= htmlspecialchars($test['allergies'] ?? 'None') ?></span></div>
                <div class="pdf-row"><span class="pdf-label">Emergency Contact</span><span class="pdf-value"><?= htmlspecialchars($test['emergency_contact'] ?? 'N/A') ?></span></div>
                <div class="pdf-row"><span class="pdf-label">Address</span><span class="pdf-value"><?= htmlspecialchars($test['address'] ?? 'N/A') ?></span></div>
                <div class="pdf-row"><span class="pdf-label">Visit Number</span><span class="pdf-value"><?= htmlspecialchars($test['visit_number'] ?? 'N/A') ?></span></div>
                
                <!-- Test Information -->
                <div class="section-title">🧪 Test Information</div>
                <div class="pdf-row"><span class="pdf-label">Test Name</span><span class="pdf-value"><strong><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></strong></span></div>
                <div class="pdf-row"><span class="pdf-label">Category</span><span class="pdf-value"><?= htmlspecialchars($test['test_category'] ?? 'N/A') ?></span></div>
                <div class="pdf-row"><span class="pdf-label">Test Type</span><span class="pdf-value"><?= htmlspecialchars($test['test_type'] ?? 'N/A') ?></span></div>
                <div class="pdf-row"><span class="pdf-label">Sample Type</span><span class="pdf-value"><?= htmlspecialchars($test['sample_type'] ?? 'N/A') ?></span></div>
                <div class="pdf-row"><span class="pdf-label">Reference Range</span><span class="pdf-value"><?= htmlspecialchars($test['reference_range'] ?? 'N/A') ?></span></div>
                <div class="pdf-row"><span class="pdf-label">Price</span><span class="pdf-value">TSh <?= number_format($test['test_price'] ?? 0, 0) ?></span></div>
                <div class="pdf-row"><span class="pdf-label">Doctor</span><span class="pdf-value"><?= htmlspecialchars($test['doctor_name'] ?? 'Not Assigned') ?></span></div>
                <div class="pdf-row"><span class="pdf-label">Technician</span><span class="pdf-value"><?= htmlspecialchars($test['technician_name'] ?? 'Not Assigned') ?></span></div>
                <div class="pdf-row"><span class="pdf-label">Visit Type</span><span class="pdf-value"><?= ucfirst(htmlspecialchars($test['visit_type'] ?? 'N/A')) ?></span></div>
                <div class="pdf-row"><span class="pdf-label">Created</span><span class="pdf-value"><?= formatDate($test['created_at'] ?? '') ?></span></div>
                <?php if (!empty($test['started_at'])): ?>
                    <div class="pdf-row"><span class="pdf-label">Started</span><span class="pdf-value"><?= formatDate($test['started_at']) ?></span></div>
                <?php endif; ?>
                <?php if (!empty($test['completed_at'])): ?>
                    <div class="pdf-row"><span class="pdf-label">Completed</span><span class="pdf-value"><?= formatDate($test['completed_at']) ?></span></div>
                <?php endif; ?>
                
                <!-- Results -->
                <div class="section-title">📊 Test Results</div>
                <?php if (!empty($test['results'])): ?>
                    <div style="padding:12px 16px;background:var(--primary-bg);border-radius:8px;border:1px solid var(--primary-light);margin-top:4px;">
                        <div style="font-weight:700;color:var(--primary-dark);font-size:1rem;"><?= nl2br(htmlspecialchars($test['results'])) ?></div>
                        <?php if (!empty($test['interpretation'])): ?>
                            <div style="margin-top:6px;font-size:0.85rem;color:var(--text-secondary);"><?= nl2br(htmlspecialchars($test['interpretation'])) ?></div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div style="padding:12px 16px;color:var(--text-secondary);font-weight:500;">
                        ${statusLabel === 'In Progress' ? '⏳ Test is In Progress - Results pending' : 
                          statusLabel === 'Cancelled' ? '❌ Test Cancelled' : 
                          '⏳ Test Pending - Results not yet available'}
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($test['notes'])): ?>
                    <div class="section-title">📝 Notes</div>
                    <div style="padding:8px 12px;background:var(--warning-bg);border-radius:6px;border:1px solid var(--warning);">
                        <?= nl2br(htmlspecialchars($test['notes'])) ?>
                    </div>
                <?php endif; ?>
                
                <!-- Footer -->
                <div class="pdf-footer">
                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;padding:12px 0;border-top:2px solid var(--border-color);margin-top:16px;">
                        <div style="font-size:0.75rem;color:var(--text-secondary);">
                            Technician: <?= htmlspecialchars($test['technician_name'] ?? '_________________') ?>
                            <span style="margin-left:20px;">Date: <?= date('d/m/Y H:i') ?></span>
                        </div>
                        <div style="text-align:center;padding:6px 16px;border:2px solid var(--primary);border-radius:8px;background:var(--primary-bg);min-width:150px;">
                            <div style="font-size:0.6rem;color:var(--text-secondary);text-transform:uppercase;letter-spacing:1px;font-weight:700;">Official Stamp</div>
                            <div style="font-size:0.8rem;font-weight:700;color:var(--primary);">BRAICK DISPENSARY</div>
                            <div style="font-size:0.65rem;color:var(--text-secondary);">Approved By: _________________</div>
                            <div style="font-size:0.6rem;color:var(--text-muted);">Date: <?= date('d/m/Y H:i') ?></div>
                        </div>
                    </div>
                    <div style="margin-top:12px;text-align:center;font-size:0.65rem;color:var(--text-muted);">
                        <span class="footer-brand">Braick Dispensary</span> • Generated on <?= date('M d, Y h:i A') ?> • All rights reserved
                    </div>
                </div>
            `;
        }
        
        content.innerHTML = html;
        modal.classList.add('active');
    }
    
    function closePDFModal() {
        document.getElementById('pdfModal').classList.remove('active');
    }
    
    function downloadPDF() {
        var element = document.getElementById('pdfContent');
        var opt = {
            margin: [10, 10, 10, 10],
            filename: 'Test_<?= htmlspecialchars($test['test_name'] ?? 'test') ?>_<?= $test['id'] ?>.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { 
                scale: 2, 
                useCORS: true,
                backgroundColor: '#ffffff',
                logging: false
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

    console.log('%c🧪 Braick - View Test', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Ultrasound template support added', 'font-size:13px; color:#34D399;');
    console.log('%c✅ PDF with Official Stamp and Technician signature', 'font-size:13px; color:#34D399;');
    console.log('%c✅ "New DB" removed from page', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Fixed: Removed result_template_id from query', 'font-size:13px; color:#34D399;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($user_branch_name) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🆔 Test ID: <?= $test['id'] ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c👤 Patient: <?= htmlspecialchars($test['patient_name'] ?? 'N/A') ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Status: <?= getStatusLabel($test['status'] ?? 'pending') ?>', 'font-size:13px; color:#D97706;');
    console.log('%c🖼️ Ultrasound: <?= (stripos($test['test_category'] ?? '', 'ultrasound') !== false) ? 'YES' : 'NO' ?>', 'font-size:13px; color:#7C3AED;');
</script>

</body>
</html>