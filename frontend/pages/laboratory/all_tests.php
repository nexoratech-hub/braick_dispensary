<?php
// ================================================================
// FILE: frontend/pages/laboratory/all_tests.php
// LABORATORY - ALL TESTS (LAB TESTS REQUESTS)
// ✅ SCROLL BUTTONS (< >) - FULLY WORKING - FIXED
// ✅ PURE BLUE THEME (#2563EB)
// ✅ VIEW BUTTON REPLACED UPDATE BUTTON
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// SESSION START
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
// GET USER DATA
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Lab Technician';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_username = $_SESSION['username'] ?? 'lab.technician';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// DATABASE CONNECTION
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// MESSAGE VARIABLES
// ================================================================
$message = '';
$message_type = '';

// ================================================================
// GET FILTERS
// ================================================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$filter_date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$filter_date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

// ================================================================
// BUILD QUERY FOR LAB TESTS
// ================================================================
$query = "
    SELECT 
        lt.*,
        p.full_name as patient_name,
        p.phone as patient_phone,
        p.gender as patient_gender,
        p.date_of_birth as patient_dob,
        p.marital_status as patient_marital,
        p.address as patient_address,
        p.blood_group as patient_blood_group,
        p.allergies as patient_allergies,
        p.emergency_contact as patient_emergency,
        TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as patient_age,
        d.full_name as doctor_name,
        t.full_name as technician_name,
        ltc.test_name as catalog_test_name,
        ltc.test_code,
        ltc.category,
        ltc.price,
        ltc.reference_range,
        ltc.description as test_description,
        GROUP_CONCAT(DISTINCT e.equipment_name SEPARATOR ', ') as equipment_names
    FROM lab_tests lt
    LEFT JOIN patients p ON lt.patient_id = p.id
    LEFT JOIN users d ON lt.doctor_id = d.id
    LEFT JOIN users t ON lt.technician_id = t.id
    LEFT JOIN lab_tests_catalog ltc ON lt.test_id = ltc.id
    LEFT JOIN lab_test_equipment le ON ltc.id = le.lab_test_id
    LEFT JOIN medical_equipment e ON le.equipment_id = e.id
    WHERE lt.branch_id = ?
";

$params = [$user_branch_id];

if (!empty($search)) {
    $query .= " AND (p.full_name LIKE ? OR lt.test_name LIKE ? OR ltc.test_code LIKE ? OR p.phone LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if ($filter_status !== 'all' && !empty($filter_status)) {
    $query .= " AND lt.status = ?";
    $params[] = $filter_status;
}

if (!empty($filter_date_from)) {
    $query .= " AND DATE(lt.created_at) >= ?";
    $params[] = $filter_date_from;
}

if (!empty($filter_date_to)) {
    $query .= " AND DATE(lt.created_at) <= ?";
    $params[] = $filter_date_to;
}

$query .= " GROUP BY lt.id ORDER BY lt.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STATISTICS
// ================================================================

// Total tests
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ?");
$stmt->execute([$user_branch_id]);
$total_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Pending tests
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ? AND status = 'pending'");
$stmt->execute([$user_branch_id]);
$pending_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// In progress tests
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ? AND status = 'in_progress'");
$stmt->execute([$user_branch_id]);
$in_progress_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Completed tests
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ? AND status = 'completed'");
$stmt->execute([$user_branch_id]);
$completed_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Cancelled tests
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ? AND status = 'cancelled'");
$stmt->execute([$user_branch_id]);
$cancelled_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// UNREAD NOTIFICATIONS
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
// PROFILE PICTURE
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// HELPER FUNCTIONS
// ================================================================
function getStatusBadge($status) {
    $status_map = [
        'pending' => ['class' => 'badge-pending', 'icon' => 'fa-clock', 'label' => 'Pending'],
        'in_progress' => ['class' => 'badge-progress', 'icon' => 'fa-spinner fa-spin', 'label' => 'In Progress'],
        'completed' => ['class' => 'badge-completed', 'icon' => 'fa-check-circle', 'label' => 'Completed'],
        'cancelled' => ['class' => 'badge-cancelled', 'icon' => 'fa-times-circle', 'label' => 'Cancelled']
    ];
    
    $status_info = $status_map[$status] ?? $status_map['pending'];
    
    return '<span class="badge-status ' . $status_info['class'] . '">
        <i class="fas ' . $status_info['icon'] . '"></i> ' . $status_info['label'] . '
    </span>';
}

// Calculate age from date_of_birth
function calculateAge($dob) {
    if (empty($dob) || $dob === '0000-00-00') {
        return 'N/A';
    }
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    $age = $birthDate->diff($today)->y;
    return $age;
}

// Format date
function formatDate($date) {
    if (empty($date) || $date === '0000-00-00 00:00:00') {
        return 'N/A';
    }
    return date('d/m/Y H:i', strtotime($date));
}

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
    <title>All Tests - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           ROOT VARIABLES - PURE BLUE THEME
           ================================================================ */
        :root {
            --primary: #2563EB;
            --primary-dark: #1D4ED8;
            --primary-light: #60A5FA;
            --primary-bg: #DBEAFE;
            --success: #059669;
            --success-bg: #ECFDF5;
            --success-text: #065F46;
            --danger: #DC2626;
            --danger-bg: #FEF2F2;
            --warning: #D97706;
            --warning-bg: #FFFBEB;
            --purple: #7C3AED;
            --purple-bg: #F5F3FF;
            --teal: #0D9488;
            --teal-bg: #F0FDFA;
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
            --radius: 8px;
            --radius-lg: 12px;
            --transition: all 0.3s ease;
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
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
            --primary-bg: #1E3A5F;
            --success-bg: #1A3A2A;
            --success-text: #34D399;
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
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 4px; }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 24px 28px;
            min-height: calc(100vh - 68px);
        }
        
        /* ================================================================
           PAGE HEADER - PURE BLUE
           ================================================================ */
        .page-header {
            background: linear-gradient(135deg, #2563EB, #1D4ED8);
            border-radius: 12px;
            padding: 22px 30px;
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            box-shadow: 0 4px 20px rgba(37, 99, 235, 0.3);
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
            font-size: 1.6rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-title i {
            font-size: 1.7rem;
            opacity: 0.9;
        }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .role-badge-display {
            background: rgba(255,255,255,0.15);
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
            background: rgba(255,255,255,0.1);
            color: rgba(255,255,255,0.9);
            padding: 3px 12px;
            border-radius: 16px;
            font-size: 0.7rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,0.08);
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.15);
            padding: 7px 16px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.8rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
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
           STATS CARDS
           ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 14px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: var(--bg-card);
            border-radius: 10px;
            padding: 14px 18px;
            border: 1px solid var(--border-color);
            transition: var(--transition);
            text-align: center;
        }
        
        .stat-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .stat-card .stat-number {
            font-size: 1.6rem;
            font-weight: 700;
            line-height: 1.2;
        }
        
        .stat-card .stat-number.blue { color: var(--primary); }
        .stat-card .stat-number.green { color: var(--success); }
        .stat-card .stat-number.yellow { color: var(--warning); }
        .stat-card .stat-number.orange { color: #F97316; }
        .stat-card .stat-number.red { color: var(--danger); }
        
        .stat-card .stat-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 500;
            margin-top: 2px;
        }
        
        /* ================================================================
           CARD
           ================================================================ */
        .card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            padding: 16px 20px;
            margin-bottom: 16px;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }
        
        .card:hover {
            border-color: var(--primary-light);
            box-shadow: var(--shadow-md);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 12px;
        }
        
        .card-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .card-title i {
            color: var(--primary);
            font-size: 1rem;
        }
        
        .card-title .badge-count {
            background: var(--primary);
            color: white;
            padding: 2px 12px;
            border-radius: 16px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        
        /* ================================================================
           TABLE HEADER WITH SCROLL BUTTONS
           ================================================================ */
        .table-wrapper {
            position: relative;
            overflow: hidden;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
        }
        
        .table-scroll-container {
            overflow-x: auto;
            overflow-y: auto;
            max-height: 550px;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 4px;
        }
        
        .table-scroll-container::-webkit-scrollbar {
            height: 8px;
            width: 6px;
        }
        
        .table-scroll-container::-webkit-scrollbar-track {
            background: var(--gray-100);
            border-radius: 4px;
        }
        
        .table-scroll-container::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 4px;
        }
        
        .table-scroll-container::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }
        
        .scroll-arrows {
            display: flex;
            gap: 6px;
            align-items: center;
        }
        
        .scroll-btn {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            border: none;
            background: var(--primary);
            color: white;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 700;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 10px rgba(37, 99, 235, 0.3);
            position: relative;
            z-index: 20;
        }
        
        .scroll-btn:hover {
            background: var(--primary-dark);
            transform: scale(1.1);
            box-shadow: 0 4px 20px rgba(37, 99, 235, 0.5);
        }
        
        .scroll-btn:active {
            transform: scale(0.95);
        }
        
        .scroll-btn i {
            font-size: 0.8rem;
        }
        
        .scroll-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
            transform: none !important;
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
            font-size: 0.85rem;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-sm {
            padding: 5px 14px;
            font-size: 0.75rem;
            border-radius: 6px;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.35);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background: #047857;
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(5, 150, 105, 0.35);
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background: #B91C1C;
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(220, 38, 38, 0.35);
        }
        
        .btn-warning {
            background: var(--warning);
            color: white;
        }
        
        .btn-warning:hover {
            background: #B45309;
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(217, 119, 6, 0.35);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 1.5px solid var(--border-color);
        }
        
        .btn-outline:hover {
            background: var(--bg-body);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .btn-view {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 16px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.75rem;
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
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.35);
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
        
        .btn-view i {
            font-size: 0.8rem;
        }
        
        /* ================================================================
           FILTERS
           ================================================================ */
        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        
        .filter-input {
            padding: 6px 12px;
            border: 1.5px solid var(--border-color);
            border-radius: 6px;
            font-size: 0.8rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: var(--transition);
        }
        
        .filter-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .filter-select {
            padding: 6px 12px;
            border: 1.5px solid var(--border-color);
            border-radius: 6px;
            font-size: 0.8rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            cursor: pointer;
        }
        
        .filter-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        input[type="date"].filter-input {
            min-width: 140px;
        }
        
        /* ================================================================
           TABLE
           ================================================================ */
        .table-container {
            overflow-x: visible;
            border-radius: var(--radius-lg);
            min-width: 100%;
            display: inline-block;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            min-width: 1300px;
        }
        
        .data-table thead {
            background: linear-gradient(135deg, #2563EB, #1D4ED8);
            color: white;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .data-table thead th {
            padding: 10px 14px;
            text-align: left;
            font-weight: 700;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 5;
        }
        
        .data-table thead th:first-child {
            border-radius: 6px 0 0 0;
        }
        
        .data-table thead th:last-child {
            border-radius: 0 6px 0 0;
        }
        
        .data-table tbody tr {
            transition: var(--transition);
            border-bottom: 1px solid var(--border-color);
        }
        
        .data-table tbody tr:hover {
            background: var(--primary-bg);
        }
        
        .data-table tbody td {
            padding: 8px 14px;
            vertical-align: middle;
            color: var(--text-primary);
            font-size: 0.82rem;
            white-space: nowrap;
        }
        
        .data-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .data-table tbody tr:nth-child(even) td {
            background: var(--gray-50);
        }
        
        [data-theme="dark"] .data-table tbody tr:nth-child(even) td {
            background: #1A1A2E;
        }
        
        /* ================================================================
           BADGES
           ================================================================ */
        .badge-status {
            display: inline-block;
            padding: 3px 14px;
            border-radius: 16px;
            font-size: 0.65rem;
            font-weight: 600;
            white-space: nowrap;
        }
        
        .badge-status i {
            font-size: 0.55rem;
            margin-right: 4px;
        }
        
        .badge-pending {
            background: #FEF3C7;
            color: #92400E;
            border: 1px solid #FCD34D;
        }
        
        .badge-progress {
            background: #DBEAFE;
            color: #1E40AF;
            border: 1px solid #93C5FD;
        }
        
        .badge-completed {
            background: #D1FAE5;
            color: #065F46;
            border: 1px solid #6EE7B7;
        }
        
        .badge-cancelled {
            background: #FEE2E2;
            color: #991B1B;
            border: 1px solid #FCA5A5;
        }
        
        [data-theme="dark"] .badge-pending {
            background: #3A2A1A;
            color: #FBBF24;
            border-color: #FBBF24;
        }
        
        [data-theme="dark"] .badge-progress {
            background: #1A2A3A;
            color: #60A5FA;
            border-color: #60A5FA;
        }
        
        [data-theme="dark"] .badge-completed {
            background: #1A3A2A;
            color: #34D399;
            border-color: #34D399;
        }
        
        [data-theme="dark"] .badge-cancelled {
            background: #3A1A1A;
            color: #F87171;
            border-color: #F87171;
        }
        
        .code-badge {
            display: inline-block;
            font-family: monospace;
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 4px;
            background: var(--gray-100);
            color: var(--gray-600);
            border: 1px solid var(--gray-300);
        }
        
        [data-theme="dark"] .code-badge {
            background: var(--gray-700);
            color: var(--gray-400);
            border-color: var(--gray-600);
        }
        
        .patient-tag {
            display: inline-block;
            font-size: 0.7rem;
            color: var(--primary);
            background: var(--primary-bg);
            padding: 2px 10px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            white-space: nowrap;
        }
        
        [data-theme="dark"] .patient-tag {
            background: #1E3A5F;
            color: var(--primary-light);
            border-color: var(--primary);
        }
        
        .equipment-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            margin-top: 2px;
        }
        
        .equipment-tag {
            font-size: 0.6rem;
            background: var(--teal-bg);
            color: var(--teal);
            padding: 1px 8px;
            border-radius: 10px;
            border: 1px solid var(--teal);
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        
        [data-theme="dark"] .equipment-tag {
            background: #1A3A3A;
            color: #34D399;
            border-color: #34D399;
        }
        
        .equipment-tag i {
            font-size: 0.4rem;
        }
        
        /* ================================================================
           VIEW MODAL
           ================================================================ */
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
            padding: 28px;
            max-width: 750px;
            width: 95%;
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
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .modal-title i {
            color: var(--primary);
        }
        
        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 14px;
            justify-content: flex-end;
        }
        
        .modal-section {
            background: var(--bg-body);
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 12px;
        }
        
        [data-theme="dark"] .modal-section {
            background: #1A1A2E;
        }
        
        .modal-section-title {
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-secondary);
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .modal-section-value {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-primary);
        }
        
        .modal-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        
        .modal-grid .full-width {
            grid-column: span 2;
        }
        
        /* ================================================================
           EMPTY STATE
           ================================================================ */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--border-color);
            display: block;
            margin-bottom: 12px;
        }
        
        .empty-state p {
            font-size: 0.9rem;
        }
        
        .empty-state .sub {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 4px;
        }
        
        /* ================================================================
           TABLE FOOTER
           ================================================================ */
        .table-footer {
            padding: 8px 16px;
            border-top: 1px solid var(--border-color);
            font-size: 0.7rem;
            color: var(--text-secondary);
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
            background: var(--gray-50);
        }
        
        [data-theme="dark"] .table-footer {
            border-color: var(--gray-700);
            color: var(--gray-400);
            background: var(--gray-800);
        }
        
        .count-badge {
            background: var(--primary);
            color: white;
            padding: 2px 12px;
            border-radius: 16px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        
        /* ================================================================
           TOAST
           ================================================================ */
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 12px 18px;
            border-radius: 8px;
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
            padding: 12px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 20px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 14px; }
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
            .modal-grid { grid-template-columns: 1fr; }
            .modal-grid .full-width { grid-column: span 1; }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .filter-input, .filter-select { width: 100%; }
            .card { padding: 12px 14px; }
            .data-table { font-size: 0.7rem; }
            .data-table thead th, .data-table tbody td { padding: 6px 10px; }
            .modal { padding: 16px; }
            .scroll-btn { width: 28px; height: 28px; font-size: 0.7rem; }
            .btn-view { padding: 4px 12px; font-size: 0.65rem; }
            .modal-grid { grid-template-columns: 1fr; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 8px; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
            .stat-card { padding: 10px 12px; }
            .stat-card .stat-number { font-size: 1.2rem; }
            .page-header .page-title { font-size: 1.1rem; }
            .scroll-btn { width: 24px; height: 24px; font-size: 0.6rem; }
            .btn-view { padding: 3px 10px; font-size: 0.6rem; }
        }
        
        /* ================================================================
           ANIMATIONS
           ================================================================ */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.4s ease forwards;
            opacity: 0;
        }
        
        .animate-fade-in-up:nth-child(1) { animation-delay: 0.05s; }
        .animate-fade-in-up:nth-child(2) { animation-delay: 0.1s; }
        .animate-fade-in-up:nth-child(3) { animation-delay: 0.15s; }
        .animate-fade-in-up:nth-child(4) { animation-delay: 0.2s; }
        .animate-fade-in-up:nth-child(5) { animation-delay: 0.25s; }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- PAGE HEADER - PURE BLUE -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-flask"></i>
                All Tests
                <span class="role-badge-display">LABORATORY</span>
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-list"></i>
                View and manage all laboratory test requests
                <span class="branch-tag">
                    <i class="fas fa-clock"></i> <?= $pending_tests ?> Pending
                </span>
                <span class="branch-tag">
                    <i class="fas fa-spinner"></i> <?= $in_progress_tests ?> In Progress
                </span>
                <span class="branch-tag">
                    <i class="fas fa-check-circle"></i> <?= $completed_tests ?> Completed
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="test_catalog.php" class="btn-outline-light">
                <i class="fas fa-list-ul"></i> Catalog
            </a>
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- MESSAGE -->
    <!-- ================================================================ -->
    <?php if ($message): ?>
        <div class="p-3 rounded-lg mb-3 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200 dark:bg-green-900/20 dark:text-green-300 dark:border-green-800' : ($message_type === 'warning' ? 'bg-yellow-100 text-yellow-700 border border-yellow-200 dark:bg-yellow-900/20 dark:text-yellow-300 dark:border-yellow-800' : 'bg-red-100 text-red-700 border border-red-200 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800') ?>" id="messageBox" style="font-size:0.85rem;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle') ?> mr-2"></i>
            <?= $message ?>
            <button onclick="this.parentElement.style.display='none'" class="float-right text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300">
                <i class="fas fa-times"></i>
            </button>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- STATS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-grid animate-fade-in-up">
        <div class="stat-card">
            <p class="stat-number blue"><?= $total_tests ?></p>
            <p class="stat-label">📊 Total Tests</p>
        </div>
        <div class="stat-card">
            <p class="stat-number yellow"><?= $pending_tests ?></p>
            <p class="stat-label">⏳ Pending</p>
        </div>
        <div class="stat-card">
            <p class="stat-number orange"><?= $in_progress_tests ?></p>
            <p class="stat-label">🔄 In Progress</p>
        </div>
        <div class="stat-card">
            <p class="stat-number green"><?= $completed_tests ?></p>
            <p class="stat-label">✅ Completed</p>
        </div>
        <div class="stat-card">
            <p class="stat-number red"><?= $cancelled_tests ?></p>
            <p class="stat-label">❌ Cancelled</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up">
        <form method="GET" class="filter-bar">
            <input type="text" name="search" class="filter-input" placeholder="🔍 Search patient, test..." value="<?= htmlspecialchars($search) ?>" style="flex:1;min-width:150px;">
            
            <select name="status" class="filter-select">
                <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>All Status</option>
                <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>⏳ Pending</option>
                <option value="in_progress" <?= $filter_status === 'in_progress' ? 'selected' : '' ?>>🔄 In Progress</option>
                <option value="completed" <?= $filter_status === 'completed' ? 'selected' : '' ?>>✅ Completed</option>
                <option value="cancelled" <?= $filter_status === 'cancelled' ? 'selected' : '' ?>>❌ Cancelled</option>
            </select>
            
            <input type="date" name="date_from" class="filter-input" placeholder="From" value="<?= htmlspecialchars($filter_date_from) ?>">
            <input type="date" name="date_to" class="filter-input" placeholder="To" value="<?= htmlspecialchars($filter_date_to) ?>">
            
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fas fa-search"></i> Filter
            </button>
            
            <?php if (!empty($search) || $filter_status !== 'all' || !empty($filter_date_from) || !empty($filter_date_to)): ?>
                <a href="all_tests.php" class="btn btn-outline btn-sm">
                    <i class="fas fa-times"></i> Clear
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- TESTS TABLE - WITH WORKING SCROLL BUTTONS (FIXED) -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list" style="color:var(--primary);"></i>
                Test Requests
                <span class="badge-count" style="background:var(--primary);"><?= count($tests) ?></span>
            </h3>
            <div class="scroll-arrows">
                <button class="scroll-btn" id="scrollLeftBtn" title="Scroll Left">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button class="scroll-btn" id="scrollRightBtn" title="Scroll Right">
                    <i class="fas fa-chevron-right"></i>
                </button>
                <span class="text-xs text-gray-400 ml-2">
                    <i class="fas fa-clock mr-1"></i> <?= date('h:i:s A') ?>
                </span>
            </div>
        </div>
        
        <div class="table-wrapper">
            <div class="table-scroll-container" id="tableScrollContainer">
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width:40px;text-align:center;border-radius:6px 0 0 0;">#</th>
                                <th style="min-width:150px;">Patient</th>
                                <th style="min-width:160px;">Test</th>
                                <th style="width:100px;">Code</th>
                                <th style="width:120px;">Category</th>
                                <th style="width:110px;text-align:center;">Status</th>
                                <th style="width:120px;">Date</th>
                                <th style="width:100px;">Equipment</th>
                                <th style="width:120px;">Requested By</th>
                                <th style="width:85px;text-align:center;border-radius:0 6px 0 0;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($tests) > 0): ?>
                                <?php $i = 1; foreach ($tests as $test): 
                                    $equipment_names = $test['equipment_names'] ?? '';
                                    $equipment_names_arr = !empty($equipment_names) ? explode(', ', $equipment_names) : [];
                                    $patient_age = !empty($test['patient_dob']) ? calculateAge($test['patient_dob']) : 'N/A';
                                    $requested_by = !empty($test['doctor_name']) ? $test['doctor_name'] : (!empty($test['technician_name']) ? $test['technician_name'] : 'Unknown');
                                ?>
                                    <tr>
                                        <td style="text-align:center;font-weight:700;font-size:0.8rem;"><?= $i++ ?></td>
                                        <td>
                                            <div style="font-size:0.85rem;font-weight:600;"><?= htmlspecialchars($test['patient_name'] ?? 'Unknown') ?></div>
                                            <div style="font-size:0.65rem;color:var(--text-secondary);">
                                                <?php if (!empty($test['patient_gender'])): ?>
                                                    <?= htmlspecialchars($test['patient_gender']) ?>
                                                <?php endif; ?>
                                                <?php if ($patient_age !== 'N/A'): ?>
                                                    • <?= $patient_age ?> yrs
                                                <?php endif; ?>
                                                <?php if (!empty($test['patient_phone'])): ?>
                                                    • <?= htmlspecialchars($test['patient_phone']) ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-size:0.85rem;font-weight:500;"><?= htmlspecialchars($test['test_name'] ?? $test['catalog_test_name'] ?? 'N/A') ?></div>
                                            <?php if (!empty($test['reference_range'])): ?>
                                                <div style="font-size:0.6rem;color:var(--text-secondary);">
                                                    Ref: <?= htmlspecialchars($test['reference_range']) ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($test['test_price'])): ?>
                                                <div style="font-size:0.6rem;color:var(--success);">
                                                    TSh <?= number_format($test['test_price'], 0) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($test['test_code'])): ?>
                                                <span class="code-badge"><?= htmlspecialchars($test['test_code']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted" style="font-size:0.65rem;">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size:0.8rem;"><?= htmlspecialchars($test['category'] ?? 'N/A') ?></td>
                                        <td style="text-align:center;">
                                            <?= getStatusBadge($test['status'] ?? 'pending') ?>
                                        </td>
                                        <td style="font-size:0.75rem;">
                                            <?php if (!empty($test['created_at'])): ?>
                                                <?= date('d/m/Y H:i', strtotime($test['created_at'])) ?>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($equipment_names_arr)): ?>
                                                <div class="equipment-tags">
                                                    <?php foreach (array_slice($equipment_names_arr, 0, 2) as $eq_name): ?>
                                                        <span class="equipment-tag">
                                                            <i class="fas fa-microscope"></i>
                                                            <?= htmlspecialchars($eq_name) ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                    <?php if (count($equipment_names_arr) > 2): ?>
                                                        <span class="equipment-tag" style="background:var(--primary-bg);color:var(--primary);border-color:var(--primary);">
                                                            +<?= count($equipment_names_arr) - 2 ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted" style="font-size:0.65rem;">None</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="patient-tag">
                                                <i class="fas fa-user-md"></i>
                                                <?= htmlspecialchars($requested_by) ?>
                                            </span>
                                        </td>
                                        <td style="text-align:center;">
                                            <button onclick="viewTestDetails(<?= htmlspecialchars(json_encode($test)) ?>)" 
                                                    class="btn-view" title="View Details">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10">
                                        <div class="empty-state">
                                            <i class="fas fa-flask" style="color: var(--primary);"></i>
                                            <p>No test requests found</p>
                                            <p class="sub">Tests will appear here when requested by doctors</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="table-footer">
            <span>
                <i class="fas fa-list"></i> Showing <strong><?= count($tests) ?></strong> test(s)
                <span class="text-xs text-gray-400 ml-2">🏥 <?= htmlspecialchars($user_branch_name) ?></span>
            </span>
            <span>
                <span class="count-badge"><?= $pending_tests ?></span> Pending
                <span class="text-xs text-gray-400 ml-2" id="updateTimeDisplay">Last update: <?= date('H:i:s') ?></span>
            </span>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            All Tests
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- VIEW DETAILS MODAL -->
<!-- ================================================================ -->
<div class="modal-overlay" id="viewModal">
    <div class="modal">
        <h3 class="modal-title">
            <i class="fas fa-flask"></i> Test Details
        </h3>
        <div id="viewModalContent">
            <!-- Content loaded dynamically -->
        </div>
        <div class="modal-actions">
            <button type="button" class="btn btn-outline" onclick="closeModal('viewModal')">
                <i class="fas fa-times"></i> Close
            </button>
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
<!-- JAVASCRIPT - FIXED SCROLL BUTTONS -->
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
        var updateTimeDisplay = document.getElementById('updateTimeDisplay');
        if (updateTimeDisplay) {
            updateTimeDisplay.textContent = 'Last update: ' + timeStr;
        }
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // VIEW TEST DETAILS
    // ================================================================
    function viewTestDetails(data) {
        console.log('📋 Viewing test details:', data);
        
        // Get equipment names
        var equipmentNames = data.equipment_names || '';
        var equipmentHtml = '';
        if (equipmentNames) {
            var names = equipmentNames.split(', ');
            equipmentHtml = '<div class="equipment-tags" style="margin-top:4px;">';
            names.forEach(function(name) {
                equipmentHtml += '<span class="equipment-tag"><i class="fas fa-microscope"></i> ' + escapeHtml(name) + '</span>';
            });
            equipmentHtml += '</div>';
        } else {
            equipmentHtml = '<span class="text-muted" style="font-size:0.8rem;">No equipment linked</span>';
        }
        
        // Determine requested by
        var requestedBy = data.doctor_name || data.technician_name || 'Unknown';
        var requestedIcon = data.doctor_name ? 'fa-user-md' : 'fa-user';
        
        // Build HTML
        var html = `
            <div class="modal-grid">
                <!-- Patient Info -->
                <div class="modal-section">
                    <div class="modal-section-title"><i class="fas fa-user"></i> Patient Information</div>
                    <div class="modal-section-value" style="font-weight:600;font-size:1rem;">${escapeHtml(data.patient_name || 'Unknown')}</div>
                    <div style="font-size:0.8rem;color:var(--text-secondary);margin-top:4px;">
                        ${data.patient_gender ? escapeHtml(data.patient_gender) : ''} 
                        ${data.patient_age ? '• ' + data.patient_age + ' yrs' : ''}
                        ${data.patient_phone ? '• ' + escapeHtml(data.patient_phone) : ''}
                    </div>
                    <div style="font-size:0.75rem;color:var(--text-secondary);margin-top:2px;">
                        ${data.patient_address ? '📍 ' + escapeHtml(data.patient_address) : ''}
                        ${data.patient_blood_group ? '• Blood: ' + escapeHtml(data.patient_blood_group) : ''}
                    </div>
                </div>
                
                <!-- Test Info -->
                <div class="modal-section">
                    <div class="modal-section-title"><i class="fas fa-flask"></i> Test Information</div>
                    <div class="modal-section-value" style="font-weight:600;font-size:1rem;">${escapeHtml(data.test_name || data.catalog_test_name || 'N/A')}</div>
                    <div style="font-size:0.8rem;color:var(--text-secondary);margin-top:4px;">
                        ${data.test_code ? 'Code: <span class="code-badge">' + escapeHtml(data.test_code) + '</span>' : ''}
                        ${data.category ? '• ' + escapeHtml(data.category) : ''}
                    </div>
                    <div style="font-size:0.8rem;color:var(--text-secondary);margin-top:2px;">
                        ${data.test_price ? '💰 TSh ' + Number(data.test_price).toLocaleString() : ''}
                        ${data.reference_range ? '• Ref: ' + escapeHtml(data.reference_range) : ''}
                    </div>
                </div>
                
                <!-- Status & Dates -->
                <div class="modal-section">
                    <div class="modal-section-title"><i class="fas fa-info-circle"></i> Status & Dates</div>
                    <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
                        <div>Status: ${getStatusBadgeHTML(data.status || 'pending')}</div>
                    </div>
                    <div style="font-size:0.75rem;color:var(--text-secondary);margin-top:4px;">
                        <div>📅 Created: ${data.created_at ? formatDate(data.created_at) : 'N/A'}</div>
                        ${data.completed_at ? '<div>✅ Completed: ' + formatDate(data.completed_at) + '</div>' : ''}
                        ${data.started_at ? '<div>🔄 Started: ' + formatDate(data.started_at) + '</div>' : ''}
                    </div>
                </div>
                
                <!-- Requested By -->
                <div class="modal-section">
                    <div class="modal-section-title"><i class="fas fa-user-md"></i> Requested By</div>
                    <div class="modal-section-value">
                        <i class="fas ${requestedIcon}"></i> ${escapeHtml(requestedBy)}
                    </div>
                    ${data.technician_name ? '<div style="font-size:0.75rem;color:var(--text-secondary);margin-top:2px;">🧪 Technician: ' + escapeHtml(data.technician_name) + '</div>' : ''}
                </div>
                
                <!-- Equipment -->
                <div class="modal-section full-width">
                    <div class="modal-section-title"><i class="fas fa-tools"></i> Equipment Used</div>
                    ${equipmentHtml}
                </div>
                
                <!-- Results -->
                <div class="modal-section full-width">
                    <div class="modal-section-title"><i class="fas fa-file-medical-alt"></i> Results / Notes</div>
                    <div class="modal-section-value" style="font-weight:400;white-space:pre-wrap;">
                        ${data.results ? escapeHtml(data.results) : '<span class="text-muted" style="font-size:0.8rem;">No results recorded yet</span>'}
                    </div>
                </div>
                
                ${data.test_description ? `
                <div class="modal-section full-width">
                    <div class="modal-section-title"><i class="fas fa-info-circle"></i> Test Description</div>
                    <div class="modal-section-value" style="font-weight:400;font-size:0.85rem;">
                        ${escapeHtml(data.test_description)}
                    </div>
                </div>
                ` : ''}
                
                ${data.patient_allergies ? `
                <div class="modal-section full-width">
                    <div class="modal-section-title"><i class="fas fa-exclamation-triangle" style="color:var(--danger);"></i> Allergies</div>
                    <div class="modal-section-value" style="color:var(--danger);">
                        ${escapeHtml(data.patient_allergies)}
                    </div>
                </div>
                ` : ''}
            </div>
        `;
        
        document.getElementById('viewModalContent').innerHTML = html;
        openModal('viewModal');
    }

    // ================================================================
    // GET STATUS BADGE HTML (for modal)
    // ================================================================
    function getStatusBadgeHTML(status) {
        var statusMap = {
            'pending': '<span class="badge-status badge-pending"><i class="fas fa-clock"></i> Pending</span>',
            'in_progress': '<span class="badge-status badge-progress"><i class="fas fa-spinner fa-spin"></i> In Progress</span>',
            'completed': '<span class="badge-status badge-completed"><i class="fas fa-check-circle"></i> Completed</span>',
            'cancelled': '<span class="badge-status badge-cancelled"><i class="fas fa-times-circle"></i> Cancelled</span>'
        };
        return statusMap[status] || statusMap['pending'];
    }

    // ================================================================
    // FORMAT DATE
    // ================================================================
    function formatDate(dateStr) {
        if (!dateStr || dateStr === '0000-00-00 00:00:00') return 'N/A';
        var d = new Date(dateStr);
        return d.toLocaleDateString('en-GB', {day:'2-digit', month:'2-digit', year:'numeric'}) + 
               ' ' + d.toLocaleTimeString('en-GB', {hour:'2-digit', minute:'2-digit'});
    }

    // ================================================================
    // SCROLL BUTTONS - FIXED & FULLY WORKING
    // ================================================================
    (function() {
        var scrollContainer = document.getElementById('tableScrollContainer');
        var scrollLeftBtn = document.getElementById('scrollLeftBtn');
        var scrollRightBtn = document.getElementById('scrollRightBtn');
        
        if (!scrollContainer || !scrollLeftBtn || !scrollRightBtn) {
            console.error('❌ Scroll elements not found!');
            return;
        }
        
        console.log('✅ Scroll elements found, initializing...');
        
        function scrollTable(direction) {
            var currentScroll = scrollContainer.scrollLeft;
            var containerWidth = scrollContainer.clientWidth;
            var contentWidth = scrollContainer.scrollWidth;
            var maxScroll = contentWidth - containerWidth;
            var scrollAmount = Math.min(containerWidth * 0.8, 400);
            
            if (direction === 'left') {
                var newScroll = currentScroll - scrollAmount;
                if (newScroll < 0) newScroll = 0;
                scrollContainer.scrollLeft = newScroll;
            } else {
                var newScroll = currentScroll + scrollAmount;
                if (newScroll > maxScroll) newScroll = maxScroll;
                scrollContainer.scrollLeft = newScroll;
            }
            updateButtonStates();
        }
        
        function updateButtonStates() {
            var currentScroll = scrollContainer.scrollLeft;
            var maxScroll = scrollContainer.scrollWidth - scrollContainer.clientWidth;
            
            if (currentScroll <= 1) {
                scrollLeftBtn.disabled = true;
                scrollLeftBtn.style.opacity = '0.4';
                scrollLeftBtn.style.cursor = 'not-allowed';
            } else {
                scrollLeftBtn.disabled = false;
                scrollLeftBtn.style.opacity = '1';
                scrollLeftBtn.style.cursor = 'pointer';
            }
            
            if (currentScroll >= maxScroll - 1 || maxScroll <= 0) {
                scrollRightBtn.disabled = true;
                scrollRightBtn.style.opacity = '0.4';
                scrollRightBtn.style.cursor = 'not-allowed';
            } else {
                scrollRightBtn.disabled = false;
                scrollRightBtn.style.opacity = '1';
                scrollRightBtn.style.cursor = 'pointer';
            }
        }
        
        scrollLeftBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            scrollTable('left');
        });
        
        scrollRightBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            scrollTable('right');
        });
        
        scrollContainer.addEventListener('scroll', function() {
            updateButtonStates();
        });
        
        window.addEventListener('resize', function() {
            updateButtonStates();
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') {
                return;
            }
            if (e.key === 'ArrowLeft') { e.preventDefault(); scrollTable('left'); }
            if (e.key === 'ArrowRight') { e.preventDefault(); scrollTable('right'); }
        });
        
        setTimeout(function() {
            updateButtonStates();
            console.log('✅ Scroll buttons initialized successfully!');
        }, 100);
        
        console.log('✅ Scroll buttons are WORKING!');
        console.log('   Use: Click < or > buttons');
        console.log('   Use: Keyboard ← and → arrows');
    })();

    // ================================================================
    // MODAL FUNCTIONS
    // ================================================================
    function openModal(id) {
        document.getElementById(id).classList.add('show');
        document.body.style.overflow = 'hidden';
    }
    
    function closeModal(id) {
        document.getElementById(id).classList.remove('show');
        document.body.style.overflow = '';
    }
    
    document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('show');
                document.body.style.overflow = '';
            }
        });
    });

    // ================================================================
    // ESCAPE HTML
    // ================================================================
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal('viewModal');
        }
    });

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

    console.log('%c📋 Braick - All Tests (VIEW BUTTON)', 'font-size:18px; font-weight:bold; color:#2563EB;');
    console.log('%c✅ Scroll buttons (< >) are FULLY WORKING', 'font-size:13px; color:#34D399;');
    console.log('%c✅ View button replaced Update button', 'font-size:13px; color:#34D399;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($user_branch_name) ?>', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>