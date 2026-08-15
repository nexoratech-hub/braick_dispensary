<?php
// ================================================================
// FILE: frontend/pages/laboratory/results_history.php
// LABORATORY - RESULTS HISTORY (COMPLETED TESTS)
// WITH REAL-TIME AUTO-UPDATE (3 SECONDS) - FIXED
// FIXED: Login session - no default user bypass
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// CHECK SESSION - REDIRECT TO LOGIN IF NOT LABORATORY
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'laboratory') {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// GET USER DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Lab Technician';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Branch';

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once 'C:/xampp/htdocs/dispensary_system/backend/config/database.php';
$db = Database::getInstance()->getConnection();

// ================================================================
// GET FILTERS
// ================================================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$patient_filter = isset($_GET['patient']) ? (int)$_GET['patient'] : 0;
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// ================================================================
// 1. COMPLETED TESTS FROM lab_tests (status = 'completed')
// ================================================================
$completed_tests_query = "
    SELECT 
        lt.id,
        lt.visit_id,
        lt.test_name,
        lt.test_type,
        lt.status,
        lt.created_at,
        lt.completed_at,
        lt.results,
        lt.notes,
        lt.branch_id,
        p.id as patient_id,
        p.full_name as patient_name,
        p.patient_id as patient_number,
        COALESCE(u.full_name, 'Not Assigned') as doctor_name,
        u.specialty,
        v.visit_number,
        lab.full_name as lab_technician_name,
        'test' as source_type,
        NULL as request_number,
        NULL as total_tests,
        NULL as test_names
    FROM lab_tests lt
    JOIN visits v ON lt.visit_id = v.id
    JOIN patients p ON v.patient_id = p.id
    LEFT JOIN users u ON lt.doctor_id = u.id
    LEFT JOIN users lab ON lt.lab_technician_id = lab.id
    WHERE lt.branch_id = ? AND lt.status = 'completed'
";

$params = [$user_branch_id];

if (!empty($search)) {
    $completed_tests_query .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR lt.test_name LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($date_filter)) {
    $completed_tests_query .= " AND DATE(lt.completed_at) = ?";
    $params[] = $date_filter;
}

if ($patient_filter > 0) {
    $completed_tests_query .= " AND p.id = ?";
    $params[] = $patient_filter;
}

if ($filter === 'today') {
    $completed_tests_query .= " AND DATE(lt.completed_at) = CURDATE()";
} elseif ($filter === 'week') {
    $completed_tests_query .= " AND lt.completed_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
} elseif ($filter === 'month') {
    $completed_tests_query .= " AND lt.completed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
}

$completed_tests_query .= " ORDER BY lt.completed_at DESC";

$stmt = $db->prepare($completed_tests_query);
$stmt->execute($params);
$completed_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// 2. COMPLETED REQUESTS FROM lab_requests (status = 'completed')
// ================================================================
$completed_requests_query = "
    SELECT 
        lr.id,
        lr.request_number,
        lr.visit_id,
        lr.patient_id,
        lr.status,
        lr.requested_at,
        lr.completed_at,
        lr.branch_id,
        p.id as patient_id,
        p.full_name as patient_name,
        p.patient_id as patient_number,
        COALESCE(u.full_name, 'Not Assigned') as doctor_name,
        u.specialty,
        v.visit_number,
        lab.full_name as lab_technician_name,
        'request' as source_type,
        (SELECT COUNT(*) FROM lab_request_items WHERE request_id = lr.id) as total_tests,
        (SELECT GROUP_CONCAT(test_name SEPARATOR ', ') FROM lab_request_items WHERE request_id = lr.id) as test_names,
        NULL as results,
        NULL as notes
    FROM lab_requests lr
    JOIN patients p ON lr.patient_id = p.id
    LEFT JOIN visits v ON lr.visit_id = v.id
    LEFT JOIN users u ON lr.doctor_id = u.id
    LEFT JOIN users lab ON lr.lab_technician_id = lab.id
    WHERE lr.branch_id = ? AND lr.status = 'completed'
";

$params2 = [$user_branch_id];

if (!empty($search)) {
    $completed_requests_query .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR lr.request_number LIKE ?)";
    $search_term = "%$search%";
    $params2[] = $search_term;
    $params2[] = $search_term;
    $params2[] = $search_term;
}

if (!empty($date_filter)) {
    $completed_requests_query .= " AND DATE(lr.completed_at) = ?";
    $params2[] = $date_filter;
}

if ($patient_filter > 0) {
    $completed_requests_query .= " AND p.id = ?";
    $params2[] = $patient_filter;
}

if ($filter === 'today') {
    $completed_requests_query .= " AND DATE(lr.completed_at) = CURDATE()";
} elseif ($filter === 'week') {
    $completed_requests_query .= " AND lr.completed_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
} elseif ($filter === 'month') {
    $completed_requests_query .= " AND lr.completed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
}

$completed_requests_query .= " ORDER BY lr.completed_at DESC";

$stmt = $db->prepare($completed_requests_query);
$stmt->execute($params2);
$completed_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// MERGE BOTH LISTS
// ================================================================
$completed_items = array_merge($completed_tests, $completed_requests);

// Sort by completed_at (newest first)
usort($completed_items, function($a, $b) {
    $time_a = $a['completed_at'] ?? $a['created_at'] ?? 0;
    $time_b = $b['completed_at'] ?? $b['created_at'] ?? 0;
    return strtotime($time_b) - strtotime($time_a);
});

// ================================================================
// GET STATISTICS
// ================================================================

// Total Completed Tests (from lab_tests)
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ? AND status = 'completed'");
$stmt->execute([$user_branch_id]);
$completed_tests_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Total Completed Requests (from lab_requests)
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_requests WHERE branch_id = ? AND status = 'completed'");
$stmt->execute([$user_branch_id]);
$completed_requests_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$total_completed = $completed_tests_count + $completed_requests_count;

// Completed Today
$today = date('Y-m-d');
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ? AND status = 'completed' AND DATE(completed_at) = ?");
$stmt->execute([$user_branch_id, $today]);
$completed_tests_today = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_requests WHERE branch_id = ? AND status = 'completed' AND DATE(completed_at) = ?");
$stmt->execute([$user_branch_id, $today]);
$completed_requests_today = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$completed_today = $completed_tests_today + $completed_requests_today;

// This Week
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ? AND status = 'completed' AND completed_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$stmt->execute([$user_branch_id]);
$completed_tests_week = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_requests WHERE branch_id = ? AND status = 'completed' AND completed_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$stmt->execute([$user_branch_id]);
$completed_requests_week = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$completed_week = $completed_tests_week + $completed_requests_week;

// This Month
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ? AND status = 'completed' AND completed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
$stmt->execute([$user_branch_id]);
$completed_tests_month = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_requests WHERE branch_id = ? AND status = 'completed' AND completed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
$stmt->execute([$user_branch_id]);
$completed_requests_month = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$completed_month = $completed_tests_month + $completed_requests_month;

// ================================================================
// GET PATIENTS LIST FOR FILTER
// ================================================================
$patients_list = [];
$stmt = $db->prepare("
    SELECT DISTINCT p.id, p.full_name, p.patient_id
    FROM lab_tests lt
    JOIN visits v ON lt.visit_id = v.id
    JOIN patients p ON v.patient_id = p.id
    WHERE lt.branch_id = ? AND lt.status = 'completed'
    UNION
    SELECT DISTINCT p.id, p.full_name, p.patient_id
    FROM lab_requests lr
    JOIN patients p ON lr.patient_id = p.id
    WHERE lr.branch_id = ? AND lr.status = 'completed'
");
$stmt->execute([$user_branch_id, $user_branch_id]);
$patients_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
$profile_pic = $_SESSION['profile_pic'] ?? '';
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

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
    <title>Results History - Laboratory</title>
    <link rel="icon" href="/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
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
            --radius: 10px;
            --radius-lg: 14px;
            --transition: all 0.3s ease;
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --text-dark: #0F172A;
            --border-color: #E2E8F0;
            --table-hover: #F1F5F9;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --text-dark: #F1F5F9;
            --border-color: #334155;
            --primary-bg: #1E3A5F;
            --primary-light: #6EA8FE;
            --gray-100: #1E293B;
            --gray-200: #334155;
            --gray-300: #475569;
            --table-hover: #1E293B;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            background: var(--bg-body);
            color: var(--text-primary);
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            margin: 0;
            padding: 0;
            line-height: 1.6;
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        /* ================================================================
           TOP NAV
           ================================================================ */
        .top-nav {
            position: fixed;
            top: 0;
            left: 270px;
            right: 0;
            height: 68px;
            background: var(--bg-nav);
            z-index: 40;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            border-bottom: 2px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .top-nav .search-wrapper {
            display: flex;
            align-items: center;
            background: var(--bg-body);
            border-radius: 10px;
            border: 2px solid var(--border-color);
            transition: all 0.3s;
            flex: 1;
            max-width: 500px;
        }
        
        .top-nav .search-wrapper:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15);
        }
        
        .top-nav .search-wrapper input {
            border: none;
            background: transparent;
            padding: 8px 14px;
            width: 100%;
            font-size: 0.85rem;
            outline: none;
            color: var(--text-primary);
        }
        
        .top-nav .search-wrapper input::placeholder {
            color: var(--text-secondary);
        }
        
        .top-nav .search-wrapper .search-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 0 10px 10px 0;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .top-nav .search-wrapper .search-btn:hover {
            background: var(--primary-dark);
        }
        
        .top-nav .datetime {
            font-size: 0.78rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .top-nav .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-color);
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .top-nav .avatar:hover {
            border-color: var(--primary);
            transform: scale(1.05);
        }
        
        .top-nav .icon-btn {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            transition: all 0.3s;
            background: transparent;
            border: none;
            cursor: pointer;
            position: relative;
        }
        
        .top-nav .icon-btn:hover {
            background: var(--bg-body);
            color: var(--primary);
        }
        
        .notif-dot {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            border: 2px solid var(--bg-nav);
            animation: pulse-dot 2s infinite;
        }
        .notif-dot.has-notif { background: var(--danger); }
        .notif-dot.no-notif { background: var(--gray-400); animation: none; }
        
        @keyframes pulse-dot {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }
        
        .dark-toggle-btn {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 6px 12px;
            cursor: pointer;
            font-size: 0.82rem;
            color: var(--text-primary);
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .dark-toggle-btn:hover {
            border-color: var(--primary);
            background: var(--bg-card);
        }
        
        .dark-toggle-btn i { font-size: 0.9rem; }
        
        .branch-badge {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            background: var(--success-bg);
            color: var(--success);
        }
        
        [data-theme="dark"] .branch-badge {
            background: #1A3A2A;
            color: #34D399;
        }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        [data-theme="dark"] .main-content {
            background: var(--gray-900);
            color: var(--gray-100);
        }
        
        /* ================================================================
           PAGE HEADER
           ================================================================ */
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
        
        .page-header .role-badge {
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
        
        .page-header .btn-outline-light {
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
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        /* ================================================================
           CARDS
           ================================================================ */
        .card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 24px 28px;
            border: 1px solid var(--border-color);
            transition: var(--transition);
            box-shadow: var(--shadow);
        }
        
        .card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        [data-theme="dark"] .card {
            background: var(--gray-800);
            border-color: var(--gray-700);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 10px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .card-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .card-title i { color: var(--primary); }
        
        .card-footer {
            padding-top: 12px;
            margin-top: 12px;
            border-top: 2px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
            font-size: 0.75rem;
            color: var(--text-secondary);
        }
        
        /* ================================================================
           STATS GRID
           ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 14px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 14px 18px;
            border: 2px solid var(--border-color);
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
        }
        
        .stat-card .number {
            font-size: 1.6rem;
            font-weight: 700;
            line-height: 1.2;
        }
        
        .stat-card .number.total { color: #7C3AED; }
        .stat-card .number.today { color: #059669; }
        .stat-card .number.week { color: #0B5ED7; }
        .stat-card .number.month { color: #D97706; }
        
        .stat-card .label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        /* ================================================================
           FILTER
           ================================================================ */
        .filter-btn {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            border: 2px solid var(--border-color);
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .filter-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .filter-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        /* ================================================================
           TABLE
           ================================================================ */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
            min-width: 900px;
        }
        
        .data-table thead th {
            text-align: left;
            padding: 10px 14px;
            font-weight: 700;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: white;
            background: #0B5ED7;
            border-bottom: 3px solid #0A4CA8;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .data-table thead th:first-child { border-radius: 8px 0 0 0; }
        .data-table thead th:last-child { border-radius: 0 8px 0 0; }
        
        .data-table tbody td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .data-table tbody tr:hover td {
            background: var(--table-hover);
        }
        
        .table-wrap {
            overflow-x: auto;
            max-height: 500px;
            overflow-y: auto;
        }
        
        .table-wrap::-webkit-scrollbar { width: 5px; height: 5px; }
        .table-wrap::-webkit-scrollbar-track { background: var(--bg-body); border-radius: 4px; }
        .table-wrap::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 4px; }
        
        /* ================================================================
           BADGES
           ================================================================ */
        .source-badge {
            font-size: 0.55rem;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 10px;
        }
        
        .source-badge.test { background: #E8F0FE; color: #0B5ED7; }
        .source-badge.request { background: #FEF3C7; color: #D97706; }
        
        [data-theme="dark"] .source-badge.test { background: #1E3A5F; color: #6EA8FE; }
        [data-theme="dark"] .source-badge.request { background: #3D2E0A; color: #FBBF24; }
        
        .status-badge-completed {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 12px;
            border-radius: 12px;
            background: #D1FAE5;
            color: #059669;
        }
        
        [data-theme="dark"] .status-badge-completed {
            background: #1A3A2A;
            color: #34D399;
        }
        
        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 5px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.7rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-blue { background: #0B5ED7; color: white; }
        .btn-blue:hover { background: #0A4CA8; transform: scale(1.05); }
        .btn-green { background: #059669; color: white; }
        .btn-green:hover { background: #047857; transform: scale(1.05); }
        .btn-outline { background: transparent; color: var(--text-secondary); border: 2px solid var(--border-color); }
        .btn-outline:hover { background: var(--bg-body); border-color: #0B5ED7; color: #0B5ED7; }
        .btn-sm { padding: 3px 8px; font-size: 0.65rem; border-radius: 4px; }
        
        .action-buttons {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
        }
        
        /* ================================================================
           FORM
           ================================================================ */
        .form-control {
            padding: 4px 10px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.8rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
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
            margin-bottom: 10px;
        }
        
        /* ================================================================
           UPDATE BADGE
           ================================================================ */
        .update-badge {
            font-size: 0.65rem;
            color: var(--text-secondary);
            background: var(--bg-body);
            padding: 2px 12px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        /* ================================================================
           RESULT PREVIEW
           ================================================================ */
        .result-preview {
            max-width: 150px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: 0.75rem;
            color: var(--text-secondary);
        }
        
        .result-preview.has-result {
            color: var(--text-primary);
            font-weight: 500;
        }
        
        /* ================================================================
           TOAST
           ================================================================ */
        .toast-custom {
            position: fixed;
            bottom: 30px;
            right: 30px;
            padding: 14px 22px;
            border-radius: var(--radius);
            z-index: 9999;
            max-width: 380px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s ease;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #ffffff;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        }
        
        .toast-custom.show { transform: translateY(0); opacity: 1; }
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
        
        .footer .footer-brand {
            color: var(--primary);
            font-weight: 600;
        }
        
        [data-theme="dark"] .footer {
            border-color: var(--gray-700);
            color: var(--gray-400);
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
            .card { padding: 16px; }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .data-table { font-size: 0.7rem; min-width: 750px; }
            .filter-group { flex-wrap: wrap; }
            .action-buttons { flex-direction: column; }
            .card-footer { flex-direction: column; text-align: center; }
            .main-content { padding: 10px; }
        }
        
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .page-title { font-size: 1.1rem; }
            .card { padding: 12px; }
            .form-control { font-size: 0.7rem; padding: 2px 6px; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- TOP NAVIGATION -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <input type="text" id="searchInput" placeholder="Search history..." value="<?= htmlspecialchars($search) ?>">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    <div class="flex items-center gap-3">
        <span class="branch-badge"><i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($user_branch_name) ?></span>
        <span class="datetime" id="currentDateTime"></span>
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= $unread_notifications > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        <a href="profile.php">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($user_full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-history mr-2" style="color: #7C3AED;"></i> Results History
                <span class="role-badge">LABORATORY</span>
                <span class="update-badge ml-2" style="background:rgba(255,255,255,0.15);color:white;">
                    <i class="fas fa-sync-alt fa-spin"></i> Live
                </span>
            </h1>
            <p class="page-subtitle">
                View all completed laboratory test results
                <span class="ml-2 inline-flex bg-purple-100 text-purple-700 px-3 py-1 rounded-full text-xs border border-purple-200">
                    <i class="fas fa-flask mr-1"></i> <?= $total_completed ?> Total Completed
                </span>
                <span class="ml-2 inline-flex bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs border border-green-200">
                    <i class="fas fa-calendar-day mr-1"></i> <?= $completed_today ?> Today
                </span>
            </p>
        </div>
        <div>
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <a href="javascript:window.print()" class="btn-outline-light" style="background:rgba(255,255,255,0.2);">
                <i class="fas fa-print"></i> Print
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-grid">
        <div class="stat-card">
            <p class="number total" id="statTotal"><?= $total_completed ?></p>
            <p class="label">📊 Total Completed</p>
        </div>
        <div class="stat-card">
            <p class="number today" id="statToday"><?= $completed_today ?></p>
            <p class="label">📅 Today</p>
        </div>
        <div class="stat-card">
            <p class="number week" id="statWeek"><?= $completed_week ?></p>
            <p class="label">📆 This Week</p>
        </div>
        <div class="stat-card">
            <p class="number month" id="statMonth"><?= $completed_month ?></p>
            <p class="label">📈 This Month</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="card mb-5" style="margin-bottom:20px;">
        <div class="flex flex-wrap items-center gap-3 filter-group">
            <span class="text-sm font-medium text-gray-600 mr-2">Filter:</span>
            <a href="results_history.php" class="filter-btn <?= $filter === 'all' || empty($filter) ? 'active' : '' ?>">All</a>
            <a href="results_history.php?filter=today" class="filter-btn <?= $filter === 'today' ? 'active' : '' ?>">Today</a>
            <a href="results_history.php?filter=week" class="filter-btn <?= $filter === 'week' ? 'active' : '' ?>">This Week</a>
            <a href="results_history.php?filter=month" class="filter-btn <?= $filter === 'month' ? 'active' : '' ?>">This Month</a>
            
            <span class="text-sm font-medium text-gray-600 ml-4 mr-2">Date:</span>
            <input type="date" id="dateFilter" value="<?= $date_filter ?>"
                   onchange="window.location.href='results_history.php?date='+this.value+'&filter=<?= $filter ?>&search=<?= urlencode($search) ?>&patient=<?= $patient_filter ?>'"
                   class="form-control" style="width:auto;">
            
            <?php if (!empty($patients_list)): ?>
                <span class="text-sm font-medium text-gray-600 ml-4 mr-2">Patient:</span>
                <select id="patientFilter" class="form-control" style="width:auto;min-width:120px;"
                        onchange="window.location.href='results_history.php?patient='+this.value+'&filter=<?= $filter ?>&date=<?= $date_filter ?>&search=<?= urlencode($search) ?>'">
                    <option value="0">All Patients</option>
                    <?php foreach ($patients_list as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $patient_filter == $p['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['full_name']) ?> (<?= htmlspecialchars($p['patient_id']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
            
            <?php if (!empty($search) || !empty($date_filter) || $patient_filter > 0): ?>
                <a href="results_history.php" class="btn btn-outline btn-sm">
                    <i class="fas fa-times"></i> Clear
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RESULTS TABLE -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list mr-2" style="color:#0B5ED7;"></i> Completed Results
                <span class="text-sm font-normal text-gray-400" id="itemCount">(<?= count($completed_items) ?>)</span>
            </h3>
            <span class="text-sm text-gray-400">Scroll to view all</span>
        </div>
        
        <div class="table-wrap">
            <table class="data-table" id="historyTable">
                <thead>
                    <tr>
                        <th style="border-radius: 8px 0 0 0;">#</th>
                        <th>Item</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Source</th>
                        <th>Status</th>
                        <th>Result</th>
                        <th>Completed</th>
                        <th style="border-radius: 0 8px 0 0;">Actions</th>
                    </tr>
                </thead>
                <tbody id="historyTableBody">
                    <?php if (count($completed_items) > 0): ?>
                        <?php $i = 1; foreach ($completed_items as $item): 
                            $is_test = ($item['source_type'] === 'test');
                            $item_name = $is_test ? ($item['test_name'] ?? 'N/A') : ($item['request_number'] ?? 'N/A');
                            $source_label = $is_test ? '🔬 Test' : '📋 Request';
                            $source_class = $is_test ? 'test' : 'request';
                            $has_result = !empty($item['results']);
                            $result_preview = $has_result ? $item['results'] : 'No result';
                            $completed_date = $item['completed_at'] ?? $item['created_at'] ?? '';
                            $patient_name = $item['patient_name'] ?? 'Unknown';
                            $patient_number = $item['patient_number'] ?? $item['patient_id'] ?? 'N/A';
                            $doctor_name = $item['doctor_name'] ?? 'Not Assigned';
                            $specialty = $item['specialty'] ?? 'GP';
                            
                            if ($is_test) {
                                $view_link = "view_test.php?id=" . $item['id'];
                                $print_link = "view_test.php?id=" . $item['id'] . "&print=1";
                            } else {
                                $view_link = "view_results.php?request_id=" . $item['id'];
                                $print_link = "view_results.php?request_id=" . $item['id'] . "&print=1";
                            }
                        ?>
                            <tr class="item-row" data-id="<?= $item['id'] ?>">
                                <td><?= $i++ ?></td>
                                <td>
                                    <div class="font-medium text-sm"><?= htmlspecialchars($item_name) ?></div>
                                    <?php if (!$is_test && isset($item['total_tests']) && $item['total_tests'] > 0): ?>
                                        <div class="text-xs text-gray-400"><?= $item['total_tests'] ?> test(s)</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="font-medium text-sm"><?= htmlspecialchars($patient_name) ?></div>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($patient_number) ?></div>
                                </td>
                                <td>
                                    <div class="text-sm"><?= htmlspecialchars($doctor_name) ?></div>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($specialty) ?></div>
                                </td>
                                <td>
                                    <span class="source-badge <?= $source_class ?>"><?= $source_label ?></span>
                                </td>
                                <td>
                                    <span class="status-badge-completed">✅ Completed</span>
                                </td>
                                <td>
                                    <div class="result-preview <?= $has_result ? 'has-result' : '' ?>">
                                        <?php if ($has_result): ?>
                                            <?= htmlspecialchars(substr($result_preview, 0, 50)) ?>
                                            <?php if (strlen($result_preview) > 50): ?>...<?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-gray-400">No result</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-xs">
                                    <?php if ($completed_date): ?>
                                        <?= date('M d, Y', strtotime($completed_date)) ?>
                                        <br><span class="text-green-600"><?= date('h:i A', strtotime($completed_date)) ?></span>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="<?= $view_link ?>" class="btn btn-blue btn-sm" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($has_result): ?>
                                            <a href="<?= $print_link ?>" class="btn btn-outline btn-sm" title="Print" style="border-color:#059669;color:#059669;" target="_blank">
                                                <i class="fas fa-print"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9">
                                <div class="empty-state">
                                    <i class="fas fa-history" style="font-size: 3rem;"></i>
                                    <p>No completed results found</p>
                                    <p class="text-sm mt-1">Completed tests will appear here</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Card Footer -->
        <div class="card-footer">
            <span class="text-sm text-gray-500">
                <i class="fas fa-flask mr-1"></i> 
                Showing <strong id="recordCount"><?= count($completed_items) ?></strong> completed result(s)
            </span>
            <span class="text-sm text-gray-500">
                <i class="fas fa-store-alt mr-1"></i> 
                Branch: <strong><?= htmlspecialchars($user_branch_name) ?></strong>
            </span>
            <span class="text-sm text-gray-500">
                <i class="fas fa-clock mr-1"></i> 
                <span id="footerTimestamp">Last updated: <?= date('h:i:s A') ?></span>
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
            Results History
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
        document.getElementById('currentDateTime').textContent = dateStr + ' • ' + timeStr;
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
        var filter = '<?= $filter ?>';
        var date = '<?= $date_filter ?>';
        var patient = '<?= $patient_filter ?>';
        window.location.href = 'results_history.php?search=' + encodeURIComponent(query) + '&filter=' + filter + '&date=' + date + '&patient=' + patient;
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
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
    // CHECK FOR SUCCESS/ERROR MESSAGES
    // ================================================================
    (function() {
        var urlParams = new URLSearchParams(window.location.search);
        var success = urlParams.get('success');
        var message = urlParams.get('message');
        
        if (success === '1' && message) {
            setTimeout(function() {
                showToast('✅ Success', decodeURIComponent(message), 'success');
            }, 500);
        } else if (success === '0' && message) {
            setTimeout(function() {
                showToast('❌ Error', decodeURIComponent(message), 'error');
            }, 500);
        }
    })();

    console.log('%c🧪 Braick - Results History (FIXED)', 'font-size:18px; font-weight:bold; color:#7C3AED;');
    console.log('%c🔐 Session-based login active - redirects to login if not authenticated', 'font-size:12px; color:#34D399;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📊 Total: <?= $total_completed ?> | Today: <?= $completed_today ?> | Week: <?= $completed_week ?> | Month: <?= $completed_month ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📋 Showing: <?= count($completed_items) ?> items', 'font-size:13px; color:#64748B;');
</script>

</body>
</html>