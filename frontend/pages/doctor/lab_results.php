<?php
// ================================================================
// FILE: frontend/pages/doctor/lab_results.php
// DOCTOR - VIEW LAB RESULTS IN TABLE FORMAT
// - View all lab results for patients in a clean table
// - Filter by status (pending, completed, all)
// - Auto-update every 3 seconds
// - Uses SHARED HEADER (dark mode, date/time, status toggle inherited)
// - Table headers with BLUE background
// - NO RESULT COLUMN - View button shows full result
// - Enhanced action buttons with better CSS
// - Print button REMOVED
// - Buttons size REDUCED
// BRAICK DISPENSARY
// ================================================================

// Start session
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
// GET DOCTOR INFO FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['full_name'] ?? 'Dr. John Mushi';
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;
$doctor_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$doctor_specialty = $_SESSION['specialty'] ?? 'General Medicine';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$is_admin = ($_SESSION['role'] === 'admin');

// ================================================================
// GET FILTER PARAMETER
// ================================================================
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Allowed filters
$allowed_filters = ['all', 'pending', 'in_progress', 'completed', 'cancelled'];
if (!in_array($filter, $allowed_filters)) {
    $filter = 'all';
}

// ================================================================
// INCLUDE DATABASE - CORRECT PATH
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die('Database connection error: ' . $e->getMessage());
}

// ================================================================
// GET LAB RESULTS
// ================================================================
$params = [];
$search_condition = "";
$status_condition = "";
$doctor_condition = "";

if ($is_admin) {
    // Admin can see all lab results
    $doctor_condition = "";
} else {
    // Doctor can only see their own lab results
    $doctor_condition = "v.doctor_id = ?";
    $params[] = $user_id;
}

// Build search condition
if (!empty($search)) {
    $search_condition = "AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR lt.test_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Build status condition based on filter
switch ($filter) {
    case 'pending':
        $status_condition = "AND lt.status = 'pending'";
        break;
    case 'in_progress':
        $status_condition = "AND lt.status = 'in_progress'";
        break;
    case 'completed':
        $status_condition = "AND lt.status = 'completed'";
        break;
    case 'cancelled':
        $status_condition = "AND lt.status = 'cancelled'";
        break;
    default:
        $status_condition = "";
        break;
}

$sql = "
    SELECT 
        lt.id,
        lt.test_name,
        lt.status,
        lt.results,
        lt.reference_range,
        lt.notes,
        lt.created_at,
        lt.completed_at,
        lt.updated_at,
        lt.lab_technician_id,
        lt.test_price,
        p.id as patient_id,
        p.full_name as patient_name,
        p.patient_id as patient_code,
        p.phone,
        p.gender,
        p.date_of_birth,
        p.blood_group,
        u.full_name as doctor_name,
        v.visit_number,
        v.visit_type,
        v.id as visit_id,
        (SELECT full_name FROM users WHERE id = lt.lab_technician_id) as technician_name
    FROM lab_tests lt
    JOIN visits v ON lt.visit_id = v.id
    JOIN patients p ON v.patient_id = p.id
    LEFT JOIN users u ON v.doctor_id = u.id
    WHERE 1=1
    " . ($is_admin ? "" : "AND " . $doctor_condition) . "
    $status_condition
    $search_condition
    ORDER BY 
        CASE lt.status 
            WHEN 'pending' THEN 1 
            WHEN 'in_progress' THEN 2 
            WHEN 'completed' THEN 3 
            WHEN 'cancelled' THEN 4 
        END,
        lt.created_at DESC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total_lab_tests = count($lab_tests);

// ================================================================
// GET COUNTS FOR BADGES
// ================================================================
$pending_count = 0;
$in_progress_count = 0;
$completed_count = 0;
$cancelled_count = 0;

if ($is_admin) {
    // Admin counts - all doctors
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM lab_tests lt
        JOIN visits v ON lt.visit_id = v.id
        WHERE lt.status = 'pending'
    ");
    $stmt->execute();
    $pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM lab_tests lt
        JOIN visits v ON lt.visit_id = v.id
        WHERE lt.status = 'in_progress'
    ");
    $stmt->execute();
    $in_progress_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM lab_tests lt
        JOIN visits v ON lt.visit_id = v.id
        WHERE lt.status = 'completed'
    ");
    $stmt->execute();
    $completed_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM lab_tests lt
        JOIN visits v ON lt.visit_id = v.id
        WHERE lt.status = 'cancelled'
    ");
    $stmt->execute();
    $cancelled_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} else {
    // Doctor counts - only their tests
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM lab_tests lt
        JOIN visits v ON lt.visit_id = v.id
        WHERE v.doctor_id = ? AND lt.status = 'pending'
    ");
    $stmt->execute([$user_id]);
    $pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM lab_tests lt
        JOIN visits v ON lt.visit_id = v.id
        WHERE v.doctor_id = ? AND lt.status = 'in_progress'
    ");
    $stmt->execute([$user_id]);
    $in_progress_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM lab_tests lt
        JOIN visits v ON lt.visit_id = v.id
        WHERE v.doctor_id = ? AND lt.status = 'completed'
    ");
    $stmt->execute([$user_id]);
    $completed_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM lab_tests lt
        JOIN visits v ON lt.visit_id = v.id
        WHERE v.doctor_id = ? AND lt.status = 'cancelled'
    ");
    $stmt->execute([$user_id]);
    $cancelled_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR - CORRECT PATHS
// ================================================================
include_once __DIR__ . '/../../components/doctor_header.php';
include_once __DIR__ . '/../../components/doctor_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Results - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
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
            position: relative;
            z-index: 1;
        }
        
        .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }
        
        .live-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(52, 211, 153, 0.2);
            color: #34D399;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            border: 1px solid rgba(52, 211, 153, 0.3);
        }
        .live-badge i { font-size: 0.4rem; }
        
        /* ================================================================
           FILTER TABS
           ================================================================ */
        .filter-tabs {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-bottom: 20px;
            background: var(--bg-card);
            padding: 8px 12px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            max-width: 1400px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .filter-tab {
            padding: 8px 20px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: transparent;
            border: 2px solid transparent;
        }
        
        .filter-tab:hover {
            background: var(--bg-body);
            color: var(--primary);
        }
        
        .filter-tab.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            box-shadow: 0 2px 8px rgba(11, 94, 215, 0.25);
        }
        
        .filter-tab .tab-badge {
            font-size: 0.6rem;
            padding: 1px 8px;
            border-radius: 20px;
            background: rgba(255,255,255,0.2);
            color: white;
        }
        
        .filter-tab:not(.active) .tab-badge {
            background: var(--gray-200);
            color: var(--gray-500);
        }
        
        .filter-tab .tab-badge.pending-badge { background: #D97706; color: white; }
        .filter-tab .tab-badge.in-progress-badge { background: #0B5ED7; color: white; }
        .filter-tab .tab-badge.completed-badge { background: #059669; color: white; }
        .filter-tab .tab-badge.cancelled-badge { background: #DC2626; color: white; }
        
        /* ================================================================
           TABLE CONTAINER
           ================================================================ */
        .table-container {
            background: var(--bg-card);
            border-radius: 14px;
            border: 1px solid var(--border-color);
            overflow: hidden;
            max-width: 1400px;
            margin-left: auto;
            margin-right: auto;
            box-shadow: var(--shadow);
            overflow-x: auto;
        }
        
        .table-container table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        
        /* ================================================================
           TABLE HEADER - BLUE BACKGROUND
           ================================================================ */
        .table-container thead {
            background: #0B5ED7 !important;
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8) !important;
        }
        
        .table-container thead th {
            padding: 12px 14px;
            text-align: left;
            font-weight: 700;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #FFFFFF !important;
            border-bottom: none;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .table-container thead th i {
            margin-right: 6px;
            opacity: 0.8;
        }
        
        /* ================================================================
           TABLE BODY
           ================================================================ */
        .table-container tbody td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .table-container tbody tr:hover {
            background: var(--gray-50);
        }
        
        [data-theme="dark"] .table-container tbody tr:hover {
            background: var(--gray-700);
        }
        
        .table-container tbody tr:last-child td {
            border-bottom: none;
        }
        
        .table-container tbody tr:nth-child(even) {
            background: var(--gray-50);
        }
        
        [data-theme="dark"] .table-container tbody tr:nth-child(even) {
            background: var(--gray-700);
        }
        
        /* ================================================================
           STATUS BADGES
           ================================================================ */
        .status-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.55rem;
            font-weight: 600;
            text-transform: uppercase;
            white-space: nowrap;
        }
        
        .status-badge.pending {
            background: var(--warning-bg);
            color: var(--warning);
        }
        
        .status-badge.in_progress {
            background: var(--primary-bg);
            color: var(--primary);
        }
        
        .status-badge.completed {
            background: var(--success-bg);
            color: var(--success);
        }
        
        .status-badge.cancelled {
            background: var(--danger-bg);
            color: var(--danger);
        }
        
        /* ================================================================
           PATIENT CELL
           ================================================================ */
        .patient-cell .patient-name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.85rem;
        }
        
        .patient-cell .patient-id {
            font-size: 0.65rem;
            color: var(--text-secondary);
            font-family: monospace;
        }
        
        .patient-cell .patient-details {
            font-size: 0.65rem;
            color: var(--text-secondary);
        }
        
        /* ================================================================
           ENHANCED ACTION BUTTONS - REDUCED SIZE
           ================================================================ */
        .actions-cell {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.6rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
            white-space: nowrap;
            font-family: inherit;
            position: relative;
            overflow: hidden;
        }
        
        .btn::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .btn:active::after {
            width: 300px;
            height: 300px;
        }
        
        .btn i {
            font-size: 0.55rem;
        }
        
        /* View Button - SMALL */
        .btn-view {
            background: var(--primary);
            color: white;
            box-shadow: 0 1px 4px rgba(11, 94, 215, 0.2);
        }
        .btn-view:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(11, 94, 215, 0.3);
        }
        .btn-view i {
            color: white;
        }
        
        /* Consultation Button - SMALL */
        .btn-consult {
            background: var(--success);
            color: white;
            box-shadow: 0 1px 4px rgba(5, 150, 105, 0.2);
        }
        .btn-consult:hover {
            background: #047857;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(5, 150, 105, 0.3);
        }
        .btn-consult i {
            color: white;
        }
        
        /* Waiting Status - SMALL */
        .waiting-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.55rem;
            font-weight: 500;
            color: var(--text-secondary);
            background: var(--gray-100);
            padding: 2px 8px;
            border-radius: 16px;
            border: 1px solid var(--border-color);
        }
        
        [data-theme="dark"] .waiting-badge {
            background: var(--gray-700);
            border-color: var(--gray-600);
        }
        
        .waiting-badge i {
            font-size: 0.5rem;
        }
        
        .waiting-badge.waiting {
            color: var(--warning);
        }
        
        .waiting-badge.processing {
            color: var(--primary);
        }
        
        /* ================================================================
           EMPTY STATE
           ================================================================ */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }
        .empty-state i {
            font-size: 3rem;
            color: var(--border-color);
            display: block;
            margin-bottom: 12px;
        }
        .empty-state .empty-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        .empty-state .empty-sub {
            font-size: 0.85rem;
            color: var(--text-secondary);
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
           ANIMATIONS
           ================================================================ */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up { animation: fadeInUp 0.5s ease forwards; opacity: 0; }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
            .sidebar-toggle-btn { display: block; }
            .table-container table { font-size: 0.75rem; }
            .table-container thead th, .table-container tbody td { padding: 8px 10px; }
            .btn { padding: 2px 6px; font-size: 0.55rem; }
            .btn i { font-size: 0.5rem; }
        }
        
        @media (max-width: 768px) {
            .page-header-custom { padding: 16px 18px; }
            .page-header-custom .page-title { font-size: 1.3rem; }
            .filter-tabs { padding: 6px 8px; }
            .filter-tab { padding: 6px 12px; font-size: 0.7rem; }
            .table-container { border-radius: 10px; }
            .table-container table { font-size: 0.7rem; }
            .table-container thead th, .table-container tbody td { padding: 6px 8px; }
            .actions-cell { gap: 3px; }
            .btn { padding: 2px 6px; font-size: 0.5rem; gap: 3px; }
            .btn i { font-size: 0.45rem; }
            .waiting-badge { font-size: 0.5rem; padding: 2px 6px; gap: 3px; }
            .waiting-badge i { font-size: 0.45rem; }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .filter-tabs { flex-wrap: wrap; }
            .filter-tab { flex: 1; justify-content: center; font-size: 0.6rem; padding: 4px 8px; }
            .table-container thead th, .table-container tbody td { padding: 4px 6px; font-size: 0.6rem; }
            .actions-cell { flex-direction: row; gap: 3px; flex-wrap: wrap; }
            .btn { padding: 2px 5px; font-size: 0.45rem; gap: 2px; }
            .btn i { font-size: 0.4rem; }
            .waiting-badge { font-size: 0.45rem; padding: 1px 5px; }
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
                Lab Results
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;">DOCTOR</span>
                <?php if ($is_admin): ?>
                    <span class="role-badge-display" style="background:rgba(220,38,38,0.3);color:white;border:1px solid rgba(220,38,38,0.3);">👑 Admin</span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-table"></i>
                View lab results for your patients
                
                <span class="header-badge">
                    <i class="fas fa-vial"></i>
                    <?= $total_lab_tests ?> Total
                </span>
                
                <span class="live-badge" id="liveBadge">
                    <i class="fas fa-circle" style="color:#34D399;"></i>
                    Live
                    <span id="liveTime" style="font-weight:400;font-size:0.55rem;"><?= date('H:i:s') ?></span>
                </span>
                
                <?php if ($is_admin): ?>
                    <span class="header-badge" style="background:rgba(220,38,38,0.2);border-color:rgba(220,38,38,0.3);color:#F87171;">
                        <i class="fas fa-user-shield"></i> Admin View
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <button onclick="manualRefresh()" class="btn-outline-light" id="refreshBtn">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FILTER TABS -->
    <!-- ================================================================ -->
    <div class="filter-tabs">
        <a href="lab_results.php?filter=all<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
           class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">
            <i class="fas fa-list"></i> All
            <span class="tab-badge"><?= $total_lab_tests ?></span>
        </a>
        
        <a href="lab_results.php?filter=pending<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
           class="filter-tab <?= $filter === 'pending' ? 'active' : '' ?>">
            <i class="fas fa-clock"></i> Pending
            <span class="tab-badge pending-badge"><?= $pending_count ?></span>
        </a>
        
        <a href="lab_results.php?filter=in_progress<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
           class="filter-tab <?= $filter === 'in_progress' ? 'active' : '' ?>">
            <i class="fas fa-spinner"></i> In Progress
            <span class="tab-badge in-progress-badge"><?= $in_progress_count ?></span>
        </a>
        
        <a href="lab_results.php?filter=completed<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
           class="filter-tab <?= $filter === 'completed' ? 'active' : '' ?>">
            <i class="fas fa-check-circle"></i> Completed
            <span class="tab-badge completed-badge"><?= $completed_count ?></span>
        </a>
        
        <a href="lab_results.php?filter=cancelled<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
           class="filter-tab <?= $filter === 'cancelled' ? 'active' : '' ?>">
            <i class="fas fa-times-circle"></i> Cancelled
            <span class="tab-badge cancelled-badge"><?= $cancelled_count ?></span>
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- LAB RESULTS TABLE - NO PRINT BUTTON -->
    <!-- ================================================================ -->
    <div class="table-container" id="labResultsContainer">
        <?php if (count($lab_tests) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th><i class="fas fa-hashtag"></i> #</th>
                        <th><i class="fas fa-user"></i> Patient</th>
                        <th><i class="fas fa-vial"></i> Test Name</th>
                        <th><i class="fas fa-info-circle"></i> Status</th>
                        <th><i class="fas fa-user-md"></i> Technician</th>
                        <th><i class="fas fa-calendar"></i> Requested</th>
                        <th><i class="fas fa-cog"></i> Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $counter = 1;
                    foreach ($lab_tests as $lab): 
                    ?>
                        <tr class="animate-fade-in-up" data-lab-id="<?= $lab['id'] ?>" data-status="<?= $lab['status'] ?>">
                            <td style="font-size:0.7rem;color:var(--text-secondary);"><?= $counter++ ?></td>
                            <td class="patient-cell">
                                <div class="patient-name"><?= htmlspecialchars($lab['patient_name'] ?? 'N/A') ?></div>
                                <div class="patient-id"><?= htmlspecialchars($lab['patient_code'] ?? 'N/A') ?></div>
                                <div class="patient-details">
                                    <?= htmlspecialchars($lab['gender'] ?? 'N/A') ?> • 
                                    <?= htmlspecialchars($lab['phone'] ?? 'N/A') ?>
                                </div>
                            </td>
                            <td>
                                <strong style="font-size:0.8rem;"><?= htmlspecialchars($lab['test_name'] ?? 'N/A') ?></strong>
                                <div style="font-size:0.6rem;color:var(--text-secondary);">
                                    <?= htmlspecialchars($lab['visit_number'] ?? 'N/A') ?>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge <?= $lab['status'] ?? 'pending' ?>">
                                    <?= ucfirst(str_replace('_', ' ', $lab['status'] ?? 'Pending')) ?>
                                </span>
                            </td>
                            <td style="font-size:0.7rem;">
                                <?php if (!empty($lab['technician_name'])): ?>
                                    <?= htmlspecialchars($lab['technician_name']) ?>
                                <?php else: ?>
                                    <span class="text-xs text-gray-400">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:0.65rem;color:var(--text-secondary);">
                                <?= date('M d, Y', strtotime($lab['created_at'])) ?>
                                <div style="font-size:0.55rem;">
                                    <?= date('h:i A', strtotime($lab['created_at'])) ?>
                                </div>
                                <?php if (!empty($lab['completed_at'])): ?>
                                    <div style="font-size:0.55rem;color:var(--success);">
                                        <i class="fas fa-check-circle"></i> <?= date('M d, h:i A', strtotime($lab['completed_at'])) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="actions-cell">
                                    <?php if ($lab['status'] === 'completed' && !empty($lab['results'])): ?>
                                        <a href="view_lab_result.php?id=<?= $lab['id'] ?>" class="btn btn-view">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <!-- PRINT BUTTON REMOVED -->
                                    <?php elseif ($lab['status'] === 'pending'): ?>
                                        <span class="waiting-badge waiting">
                                            <i class="fas fa-clock"></i> Waiting
                                        </span>
                                    <?php elseif ($lab['status'] === 'in_progress'): ?>
                                        <span class="waiting-badge processing">
                                            <i class="fas fa-spinner fa-spin"></i> Processing
                                        </span>
                                    <?php elseif ($lab['status'] === 'cancelled'): ?>
                                        <span class="waiting-badge" style="color:var(--danger);border-color:var(--danger-bg);background:var(--danger-bg);">
                                            <i class="fas fa-times-circle"></i> Cancelled
                                        </span>
                                    <?php endif; ?>
                                    <a href="consultation.php?visit_id=<?= $lab['visit_id'] ?>" class="btn btn-consult">
                                        <i class="fas fa-stethoscope"></i> Consult
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-flask"></i>
                <div class="empty-title">No Lab Results Found</div>
                <div class="empty-sub">
                    <?php if ($filter === 'pending'): ?>
                        No pending lab tests for your patients
                    <?php elseif ($filter === 'in_progress'): ?>
                        No lab tests currently in progress
                    <?php elseif ($filter === 'completed'): ?>
                        No completed lab results yet
                    <?php elseif ($filter === 'cancelled'): ?>
                        No cancelled lab tests
                    <?php else: ?>
                        No lab results found for your patients
                    <?php endif; ?>
                    <?php if (!empty($search)): ?>
                        <br>Try adjusting your search criteria
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Lab Results
            <?php if ($is_admin): ?>
                <span class="text-gray-300 mx-2">|</span>
                <span style="color:#DC2626;">👑 Admin Mode</span>
            <?php endif; ?>
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
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
        
        var liveTime = document.getElementById('liveTime');
        if (liveTime) liveTime.textContent = timeStr;
    }
    updateFooterTime();

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
    // AUTO-UPDATE - EVERY 3 SECONDS
    // ================================================================
    var updateInterval = null;
    var isUpdating = false;
    var lastHash = null;
    var updateCount = 0;

    function fetchAndUpdateLabResults() {
        if (isUpdating) return;
        isUpdating = true;
        updateCount++;
        
        var filter = '<?= $filter ?>';
        var search = '<?= addslashes($search) ?>';
        
        fetch('get_lab_results.php?filter=' + filter + '&search=' + encodeURIComponent(search) + '&t=' + Date.now())
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    if (lastHash !== data.hash) {
                        lastHash = data.hash;
                        updateLabResults(data);
                        updateFooterTime();
                        
                        if (updateCount > 1) {
                            showToast('🔄 Updated', 'Lab results auto-updated at ' + data.timestamp, 'info');
                        }
                    }
                }
                isUpdating = false;
            })
            .catch(function(error) {
                console.error('Update error:', error);
                isUpdating = false;
            });
    }

    function updateLabResults(data) {
        var container = document.getElementById('labResultsContainer');
        if (!container) return;
        
        // Update counts in filter tabs
        var allTab = document.querySelector('.filter-tab[href*="filter=all"] .tab-badge');
        var pendingTab = document.querySelector('.filter-tab[href*="filter=pending"] .tab-badge');
        var inProgressTab = document.querySelector('.filter-tab[href*="filter=in_progress"] .tab-badge');
        var completedTab = document.querySelector('.filter-tab[href*="filter=completed"] .tab-badge');
        var cancelledTab = document.querySelector('.filter-tab[href*="filter=cancelled"] .tab-badge');
        
        if (allTab) allTab.textContent = data.total;
        if (pendingTab) pendingTab.textContent = data.pending_count;
        if (inProgressTab) inProgressTab.textContent = data.in_progress_count;
        if (completedTab) completedTab.textContent = data.completed_count;
        if (cancelledTab) cancelledTab.textContent = data.cancelled_count;
        
        // Update page header total
        var totalBadge = document.querySelector('.header-badge');
        if (totalBadge) {
            totalBadge.innerHTML = '<i class="fas fa-vial"></i> ' + data.total + ' Total';
        }
        
        // Update lab results table
        if (data.html) {
            container.innerHTML = data.html;
        }
        
        // Update live time
        var liveTime = document.getElementById('liveTime');
        if (liveTime) liveTime.textContent = data.timestamp.split(' ')[1];
    }

    function startAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
        }
        fetchAndUpdateLabResults();
        updateInterval = setInterval(fetchAndUpdateLabResults, 3000);
        console.log('%c🔄 Auto-update started (every 3s)', 'font-size:12px; color:#34D399;');
    }
    
    function stopAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
            updateInterval = null;
            console.log('%c⏹️ Auto-update stopped', 'font-size:12px; color:#DC2626;');
        }
    }

    function manualRefresh() {
        var btn = document.getElementById('refreshBtn');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
        btn.disabled = true;
        
        lastHash = null;
        fetchAndUpdateLabResults();
        
        setTimeout(function() {
            btn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh';
            btn.disabled = false;
            showToast('✅ Refreshed', 'Lab results updated manually', 'success');
        }, 1500);
    }

    // ================================================================
    // VISIBILITY CHANGE - PAUSE WHEN HIDDEN
    // ================================================================
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopAutoUpdate();
        } else {
            startAutoUpdate();
        }
    });

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            var searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }
        if (e.key === 'F5') {
            e.preventDefault();
            manualRefresh();
        }
    });

    // ================================================================
    // INITIALIZE
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            startAutoUpdate();
        }, 2000);
    });

    console.log('%c🧪 Braick - Lab Results (Table Format - Blue Headers)', 'font-size:18px; font-weight:bold; color:#7C3AED;');
    console.log('%c👤 User ID: <?= $user_id ?> | Role: <?= $_SESSION['role'] ?>', 'font-size:13px; color:#64748B;');
    <?php if ($is_admin): ?>
    console.log('%c👑 Admin Mode - Viewing All Lab Results', 'font-size:13px; color:#DC2626;');
    <?php endif; ?>
    console.log('%c📊 Pending: <?= $pending_count ?> | In Progress: <?= $in_progress_count ?> | Completed: <?= $completed_count ?> | Cancelled: <?= $cancelled_count ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📋 Filter: <?= ucfirst($filter) ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c🔵 Table headers with blue gradient background', 'font-size:13px; color:#0B5ED7;');
    console.log('%c❌ Result column removed - Use View button to see full result', 'font-size:13px; color:#DC2626;');
    console.log('%c✅ Enhanced action buttons - REDUCED SIZE', 'font-size:13px; color:#34D399;');
    console.log('%c🖨️ Print button REMOVED', 'font-size:13px; color:#DC2626;');
</script>

</body>
</html>