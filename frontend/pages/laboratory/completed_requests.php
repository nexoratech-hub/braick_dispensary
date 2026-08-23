<?php
// ================================================================
// FILE: frontend/pages/laboratory/completed_requests.php
// LABORATORY - COMPLETED REQUESTS (FROM lab_tests ONLY)
// USING NEW DATABASE: dispensary_db
// WITH REAL-TIME AUTO-UPDATE (3 SECONDS)
// WITH FULL LOGIN SESSION PROTECTION
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
// CHECK IF USER IS LABORATORY OR ADMIN
// ================================================================
if ($_SESSION['role'] !== 'laboratory' && $_SESSION['role'] !== 'admin') {
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
$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Lab Technician';
$user_role = $_SESSION['role'] ?? 'laboratory';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_username = $_SESSION['username'] ?? 'lab.technician';
$profile_pic = $_SESSION['profile_pic'] ?? '';

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
// GET FILTERS
// ================================================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$patient_filter = isset($_GET['patient']) ? (int)$_GET['patient'] : 0;
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// ================================================================
// ✅ GET COMPLETED TESTS FROM lab_tests ONLY
// ================================================================
$query = "
    SELECT 
        lt.id,
        lt.visit_id,
        lt.patient_id,
        lt.doctor_id,
        lt.lab_technician_id,
        lt.test_id,
        lt.test_name,
        lt.test_type,
        lt.test_price,
        lt.sample_type,
        lt.test_date,
        lt.results,
        lt.reference_range,
        lt.interpretation,
        lt.performed_by,
        lt.status,
        lt.branch_id,
        lt.notes,
        lt.created_at,
        lt.completed_at,
        lt.updated_at,
        p.id as patient_id,
        p.full_name as patient_name,
        p.patient_id as patient_code,
        p.phone,
        p.gender,
        p.date_of_birth,
        COALESCE(u.full_name, 'Not Assigned') as doctor_name,
        u.specialty,
        v.visit_number,
        v.visit_type,
        lab.full_name as lab_technician_name,
        'test' as source_type,
        1 as total_tests,
        CASE WHEN lt.status = 'completed' THEN 1 ELSE 0 END as completed_tests,
        lt.test_name as test_names
    FROM lab_tests lt
    LEFT JOIN patients p ON lt.patient_id = p.id
    LEFT JOIN visits v ON lt.visit_id = v.id
    LEFT JOIN users u ON lt.doctor_id = u.id
    LEFT JOIN users lab ON lt.lab_technician_id = lab.id
    WHERE lt.branch_id = ? AND lt.status = 'completed'
";

$params = [$user_branch_id];

if (!empty($search)) {
    $query .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR lt.test_name LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($date_filter)) {
    $query .= " AND DATE(lt.completed_at) = ?";
    $params[] = $date_filter;
}

if ($patient_filter > 0) {
    $query .= " AND p.id = ?";
    $params[] = $patient_filter;
}

if ($filter === 'today') {
    $query .= " AND DATE(lt.completed_at) = CURDATE()";
} elseif ($filter === 'week') {
    $query .= " AND lt.completed_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
} elseif ($filter === 'month') {
    $query .= " AND lt.completed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
}

$query .= " ORDER BY lt.completed_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$completed_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STATISTICS
// ================================================================

// Total Completed Tests
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ? AND status = 'completed'");
$stmt->execute([$user_branch_id]);
$total_completed = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Completed Today
$today = date('Y-m-d');
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ? AND status = 'completed' AND DATE(completed_at) = ?");
$stmt->execute([$user_branch_id, $today]);
$completed_today = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// This Week
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ? AND status = 'completed' AND completed_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$stmt->execute([$user_branch_id]);
$completed_week = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// This Month
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ? AND status = 'completed' AND completed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
$stmt->execute([$user_branch_id]);
$completed_month = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Total Tests All
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ?");
$stmt->execute([$user_branch_id]);
$total_tests_all = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Completion Rate
$completion_rate = $total_tests_all > 0 ? round(($total_completed / $total_tests_all) * 100, 1) : 0;

// ================================================================
// GET PATIENTS LIST FOR FILTER
// ================================================================
$patients_list = [];
$stmt = $db->prepare("
    SELECT DISTINCT p.id, p.full_name, p.patient_id
    FROM lab_tests lt
    JOIN patients p ON lt.patient_id = p.id
    WHERE lt.branch_id = ? AND lt.status = 'completed'
");
$stmt->execute([$user_branch_id]);
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
    <title>Completed Tests - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
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
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --radius: 10px;
            --radius-lg: 14px;
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 25px rgba(0,0,0,0.12);
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            background: var(--bg-body);
            color: var(--text-primary);
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); border-radius: 4px; }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 4px; }
        
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
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow);
        }
        
        .top-nav .search-wrapper {
            display: flex;
            align-items: center;
            background: var(--bg-body);
            border-radius: var(--radius);
            border: 2px solid var(--border-color);
            transition: all 0.3s;
            flex: 1;
            max-width: 500px;
        }
        
        .top-nav .search-wrapper:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.12);
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
            border-radius: 0 var(--radius) var(--radius) 0;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .top-nav .search-wrapper .search-btn:hover {
            background: var(--primary-dark);
            transform: scale(1.02);
        }
        
        .top-nav .datetime {
            font-size: 0.78rem;
            color: var(--text-secondary);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
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
            border-radius: var(--radius);
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
        
        /* ================================================================
           MAIN CONTENT
           ================================================================ */
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
        
        .role-badge {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            backdrop-filter: blur(4px);
        }
        
        .update-badge {
            background: rgba(255,255,255,0.12);
            color: rgba(255,255,255,0.8);
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            backdrop-filter: blur(4px);
        }
        
        .btn-outline-light {
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
        }
        
        .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }
        
        /* ================================================================
           STATS CARDS
           ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
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
            box-shadow: var(--shadow-md);
        }
        
        .stat-card .number {
            font-size: 1.6rem;
            font-weight: 700;
            line-height: 1.2;
        }
        
        .stat-card .number.total { color: #059669; }
        .stat-card .number.today { color: #0B5ED7; }
        .stat-card .number.week { color: #7C3AED; }
        .stat-card .number.month { color: #D97706; }
        .stat-card .number.rate { color: #0D9488; }
        
        .stat-card .label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        /* ================================================================
           CARD
           ================================================================ */
        .card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            border: 1px solid var(--border-color);
            transition: var(--transition);
            box-shadow: var(--shadow);
        }
        
        .card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .card-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .card-title .title-blue { color: var(--primary); }
        
        .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            padding-top: 12px;
            border-top: 1px solid var(--border-color);
            margin-top: 12px;
        }
        
        /* ================================================================
           FILTERS
           ================================================================ */
        .filter-group {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
        }
        
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
        
        .btn-sm { padding: 3px 8px; font-size: 0.65rem; border-radius: 4px; }
        
        /* ================================================================
           TABLE
           ================================================================ */
        .table-wrap {
            overflow-x: auto;
            max-height: 500px;
            overflow-y: auto;
        }
        
        .table-wrap::-webkit-scrollbar { width: 5px; height: 5px; }
        .table-wrap::-webkit-scrollbar-track { background: var(--bg-body); border-radius: 4px; }
        .table-wrap::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 4px; }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
            min-width: 800px;
        }
        
        .data-table thead th {
            text-align: left;
            padding: 8px 12px;
            font-weight: 700;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: white;
            background: var(--primary);
            border-bottom: 3px solid var(--primary-dark);
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .data-table thead th:first-child { border-radius: 8px 0 0 0; }
        .data-table thead th:last-child { border-radius: 0 8px 0 0; }
        
        .data-table tbody td {
            padding: 8px 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .data-table tbody tr:hover td {
            background: var(--primary-bg);
        }
        
        .data-table tbody tr:nth-child(even) td {
            background: var(--gray-50);
        }
        
        [data-theme="dark"] .data-table tbody tr:nth-child(even) td {
            background: #1A1A2E;
        }
        
        .status-badge-completed {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 12px;
            border-radius: 12px;
            background: var(--success-bg);
            color: var(--success);
            border: 1px solid var(--success);
        }
        
        [data-theme="dark"] .status-badge-completed {
            background: #1A3A2A;
            color: #34D399;
            border-color: #34D399;
        }
        
        /* SINGLE VIEW BUTTON */
        .btn-view {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 5px 14px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.7rem;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border: none;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(11, 94, 215, 0.25);
        }
        
        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(11, 94, 215, 0.4);
            color: white;
        }
        
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
            box-shadow: var(--shadow-lg);
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
        
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .card { padding: 14px 16px; }
            .data-table { font-size: 0.7rem; min-width: 650px; }
            .filter-group { flex-wrap: wrap; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .stat-card .number { font-size: 1.2rem; }
            .page-header .page-title { font-size: 1.1rem; }
            .card { padding: 10px 12px; }
            .data-table th, .data-table td { padding: 4px 6px; font-size: 0.65rem; }
            .btn-view { font-size: 0.6rem; padding: 3px 8px; }
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
            <input type="text" id="searchInput" placeholder="Search completed tests..." value="<?= htmlspecialchars($search) ?>">
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
                <i class="fas fa-check-circle mr-2" style="color: #34D399;"></i> Completed Tests
                <span class="role-badge">LABORATORY</span>
                <span class="update-badge ml-2" id="updateBadge">
                    <i class="fas fa-sync-alt fa-spin"></i> Live
                </span>
            </h1>
            <p class="page-subtitle">
                View all completed laboratory tests
                <span class="ml-2 inline-flex bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs border border-green-200">
                    <i class="fas fa-check-circle mr-1"></i> <?= $total_completed ?> Total
                </span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-calendar-day mr-1"></i> <?= $completed_today ?> Today
                </span>
                <span class="ml-2 inline-flex bg-teal-100 text-teal-700 px-3 py-1 rounded-full text-xs border border-teal-200">
                    <i class="fas fa-percentage mr-1"></i> <?= $completion_rate ?>% Rate
                </span>
            </p>
        </div>
        <div>
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <a href="javascript:window.print()" class="btn-outline-light">
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
            <p class="label">✅ Total Completed</p>
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
        <div class="stat-card">
            <p class="number rate" id="statRate"><?= $completion_rate ?>%</p>
            <p class="label">📊 Completion Rate</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <div class="filter-group">
            <span class="text-sm font-medium text-gray-600 mr-2">Filter:</span>
            <a href="completed_requests.php" class="filter-btn <?= $filter === 'all' || empty($filter) ? 'active' : '' ?>">All</a>
            <a href="completed_requests.php?filter=today" class="filter-btn <?= $filter === 'today' ? 'active' : '' ?>">Today</a>
            <a href="completed_requests.php?filter=week" class="filter-btn <?= $filter === 'week' ? 'active' : '' ?>">This Week</a>
            <a href="completed_requests.php?filter=month" class="filter-btn <?= $filter === 'month' ? 'active' : '' ?>">This Month</a>
            
            <span class="text-sm font-medium text-gray-600 ml-4 mr-2">Date:</span>
            <input type="date" id="dateFilter" value="<?= $date_filter ?>"
                   onchange="window.location.href='completed_requests.php?date='+this.value+'&filter=<?= $filter ?>&search=<?= urlencode($search) ?>&patient=<?= $patient_filter ?>'"
                   class="form-control" style="width:auto;">
            
            <?php if (!empty($patients_list)): ?>
                <span class="text-sm font-medium text-gray-600 ml-4 mr-2">Patient:</span>
                <select id="patientFilter" class="form-control" style="width:auto;min-width:120px;"
                        onchange="window.location.href='completed_requests.php?patient='+this.value+'&filter=<?= $filter ?>&date=<?= $date_filter ?>&search=<?= urlencode($search) ?>'">
                    <option value="0">All Patients</option>
                    <?php foreach ($patients_list as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $patient_filter == $p['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['full_name']) ?> (<?= htmlspecialchars($p['patient_id']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
            
            <?php if (!empty($search) || !empty($date_filter) || $patient_filter > 0): ?>
                <a href="completed_requests.php" class="btn btn-outline btn-sm">
                    <i class="fas fa-times"></i> Clear
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- COMPLETED TABLE -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-blue mr-2"></i> Completed Tests
                <span class="text-sm font-normal text-gray-400" id="itemCount">(<?= count($completed_tests) ?>)</span>
            </h3>
            <span class="text-sm text-gray-400">Scroll to view all</span>
        </div>
        
        <div class="table-wrap">
            <table class="data-table" id="completedTable">
                <thead>
                    <tr>
                        <th style="border-radius: 8px 0 0 0;">#</th>
                        <th>Test Name</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Status</th>
                        <th>Completed</th>
                        <th style="border-radius: 0 8px 0 0;">Action</th>
                    </tr>
                </thead>
                <tbody id="completedTableBody">
                    <?php if (count($completed_tests) > 0): ?>
                        <?php $i = 1; foreach ($completed_tests as $test): 
                            $completed_date = $test['completed_at'] ?? $test['created_at'] ?? '';
                            $patient_name = $test['patient_name'] ?? 'Unknown';
                            $patient_code = $test['patient_code'] ?? 'N/A';
                            $doctor_name = $test['doctor_name'] ?? 'Not Assigned';
                            $specialty = $test['specialty'] ?? 'GP';
                            $has_result = !empty($test['results']);
                        ?>
                            <tr class="item-row" data-id="<?= $test['id'] ?>">
                                <td><?= $i++ ?></td>
                                <td>
                                    <div class="font-medium text-sm"><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></div>
                                    <?php if (!empty($test['test_type'])): ?>
                                        <div class="text-xs text-gray-400"><?= htmlspecialchars($test['test_type']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($test['test_price']) && $test['test_price'] > 0): ?>
                                        <div class="text-xs text-gray-400">TSh <?= number_format($test['test_price']) ?></div>
                                    <?php endif; ?>
                                    <?php if ($has_result): ?>
                                        <span class="text-xs text-green-600">📋 Has Results</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="font-medium text-sm"><?= htmlspecialchars($patient_name) ?></div>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($patient_code) ?></div>
                                    <?php if (!empty($test['phone'])): ?>
                                        <div class="text-xs text-gray-400">📱 <?= htmlspecialchars($test['phone']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="text-sm"><?= htmlspecialchars($doctor_name) ?></div>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($specialty) ?></div>
                                </td>
                                <td>
                                    <span class="status-badge-completed">✅ Completed</span>
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
                                    <!-- SINGLE VIEW BUTTON -->
                                    <a href="view_test.php?id=<?= $test['id'] ?>" class="btn-view" title="View Details">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="fas fa-check-circle" style="color: #059669; font-size: 3rem;"></i>
                                    <p>No completed tests found</p>
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
                Showing <strong id="recordCount"><?= count($completed_tests) ?></strong> completed test(s)
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
    <!-- QUICK ACTIONS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-5">
        <a href="pending_tests.php" class="card text-center hover:border-yellow-500 transition">
            <i class="fas fa-clock text-yellow-600 text-2xl block mb-2"></i>
            <span class="text-sm font-medium">Pending Tests</span>
            <p class="text-xs text-gray-400">View waiting tests</p>
        </a>
        <a href="in_progress.php" class="card text-center hover:border-blue-500 transition">
            <i class="fas fa-spinner text-blue-600 text-2xl block mb-2"></i>
            <span class="text-sm font-medium">In Progress</span>
            <p class="text-xs text-gray-400">View ongoing tests</p>
        </a>
        <a href="dashboard.php" class="card text-center hover:border-purple-500 transition">
            <i class="fas fa-chart-bar text-purple-600 text-2xl block mb-2"></i>
            <span class="text-sm font-medium">Dashboard</span>
            <p class="text-xs text-gray-400">View all statistics</p>
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Completed Tests
            <span class="text-gray-300 mx-2">|</span>
            Logged in as: <strong><?= htmlspecialchars($user_full_name) ?></strong>
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
        var el = document.getElementById('currentDateTime');
        if (el) el.textContent = dateStr + ' • ' + timeStr;
        var footer = document.getElementById('footerTimestamp');
        if (footer) footer.textContent = 'Last updated: ' + timeStr;
        var badge = document.getElementById('updateBadge');
        if (badge) badge.innerHTML = '<i class="fas fa-check-circle" style="color:#34D399;"></i> Live • ' + timeStr;
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
        window.location.href = 'completed_requests.php?search=' + encodeURIComponent(query) + '&filter=' + filter + '&date=' + date + '&patient=' + patient;
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
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }
        if (e.key === 'F5') {
            e.preventDefault();
            window.location.reload();
        }
    });

    console.log('%c🧪 Braick - Completed Tests (NEW DATABASE)', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c✅ Using NEW DATABASE: dispensary_db', 'font-size:13px; color:#34D399;');
    console.log('%c📊 Using lab_tests table only', 'font-size:13px; color:#0B5ED7;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($user_branch_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Total: <?= $total_completed ?> | Today: <?= $completed_today ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📈 Completion Rate: <?= $completion_rate ?>%', 'font-size:13px; color:#7C3AED;');
    console.log('%c✅ Single VIEW button only', 'font-size:13px; color:#059669;');
</script>

</body>
</html>