<?php
// ================================================================
// FILE: frontend/pages/laboratory/results_history.php
// LABORATORY - RESULTS HISTORY (COMPLETED TESTS)
// USING NEW DATABASE: dispensary_db (lab_tests table)
// WITH REAL-TIME AUTO-UPDATE (3 SECONDS)
// WITH FILTERS: Today, Week, Month, 3 Months, 6 Months, 1 Year, All
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// SESSION START
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
// GET FILTERS
// ================================================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$patient_filter = isset($_GET['patient']) ? (int)$_GET['patient'] : 0;
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// ================================================================
// BUILD QUERY - COMPLETED TESTS FROM lab_tests
// ================================================================
$query = "
    SELECT 
        lt.id,
        lt.visit_id,
        lt.test_name,
        lt.test_type,
        lt.status,
        lt.created_at,
        lt.completed_at,
        lt.results,
        lt.interpretation,
        lt.notes,
        lt.branch_id,
        lt.lab_technician_id,
        p.id as patient_id,
        p.full_name as patient_name,
        p.patient_id as patient_code,
        p.phone,
        p.gender,
        p.date_of_birth,
        u.full_name as doctor_name,
        u.specialty,
        v.visit_number,
        v.visit_type,
        lab.full_name as lab_technician_name,
        ltc.category as test_category,
        ltc.price as test_price,
        ltc.reference_range
    FROM lab_tests lt
    LEFT JOIN patients p ON lt.patient_id = p.id
    LEFT JOIN users u ON lt.doctor_id = u.id
    LEFT JOIN visits v ON lt.visit_id = v.id
    LEFT JOIN users lab ON lt.lab_technician_id = lab.id
    LEFT JOIN lab_tests_catalog ltc ON lt.test_id = ltc.id
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

// ================================================================
// FILTER BY TIME PERIOD
// ================================================================
if ($filter === 'today') {
    $query .= " AND DATE(lt.completed_at) = CURDATE()";
} elseif ($filter === 'week') {
    $query .= " AND lt.completed_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
} elseif ($filter === 'month') {
    $query .= " AND lt.completed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
} elseif ($filter === '3months') {
    $query .= " AND lt.completed_at >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
} elseif ($filter === '6months') {
    $query .= " AND lt.completed_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
} elseif ($filter === '1year') {
    $query .= " AND lt.completed_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
}
// 'all' - no date filter

$query .= " ORDER BY lt.completed_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$completed_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STATISTICS - NEW DATABASE
// ================================================================

// Total Completed Tests
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM lab_tests 
    WHERE branch_id = ? AND status = 'completed'
");
$stmt->execute([$user_branch_id]);
$total_completed = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Completed Today
$today = date('Y-m-d');
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM lab_tests 
    WHERE branch_id = ? AND status = 'completed' AND DATE(completed_at) = ?
");
$stmt->execute([$user_branch_id, $today]);
$completed_today = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// This Week
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM lab_tests 
    WHERE branch_id = ? AND status = 'completed' 
    AND completed_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
");
$stmt->execute([$user_branch_id]);
$completed_week = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// This Month
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM lab_tests 
    WHERE branch_id = ? AND status = 'completed' 
    AND completed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
");
$stmt->execute([$user_branch_id]);
$completed_month = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Last 3 Months
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM lab_tests 
    WHERE branch_id = ? AND status = 'completed' 
    AND completed_at >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
");
$stmt->execute([$user_branch_id]);
$completed_3months = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Last 6 Months
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM lab_tests 
    WHERE branch_id = ? AND status = 'completed' 
    AND completed_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
");
$stmt->execute([$user_branch_id]);
$completed_6months = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Last 1 Year
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM lab_tests 
    WHERE branch_id = ? AND status = 'completed' 
    AND completed_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
");
$stmt->execute([$user_branch_id]);
$completed_1year = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// GET PATIENTS LIST FOR FILTER - NEW DATABASE
// ================================================================
$patients_list = [];
$stmt = $db->prepare("
    SELECT DISTINCT p.id, p.full_name, p.patient_id
    FROM lab_tests lt
    LEFT JOIN patients p ON lt.patient_id = p.id
    WHERE lt.branch_id = ? AND lt.status = 'completed'
    AND p.id IS NOT NULL
    ORDER BY p.full_name ASC
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
// HELPER FUNCTIONS
// ================================================================
function formatDate($datetime) {
    if (empty($datetime)) return 'N/A';
    return date('d/m/Y h:i A', strtotime($datetime));
}

function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}

function getFilterLabel($filter) {
    $map = [
        'today' => 'Today',
        'week' => 'This Week',
        'month' => 'This Month',
        '3months' => '3 Months',
        '6months' => '6 Months',
        '1year' => '1 Year',
        'all' => 'All Time'
    ];
    return $map[$filter] ?? 'All Time';
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
    <title>Results History - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
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
            --teal: #0D9488;
            --teal-bg: #CCFBF1;
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
           PAGE HEADER - PURPLE THEME
           ================================================================ */
        .page-header {
            background: linear-gradient(135deg, #7C3AED, #6D28D9);
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 20px rgba(124, 58, 237, 0.25);
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
        
        .update-badge-light {
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
        
        /* ================================================================
           STATS CARDS - 7 CARDS
           ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 12px 14px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            text-align: center;
            cursor: pointer;
            text-decoration: none;
            display: block;
        }
        
        .stat-card:hover {
            border-color: var(--primary);
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        
        .stat-card.active {
            border-color: var(--purple);
            background: var(--purple-bg);
        }
        
        [data-theme="dark"] .stat-card.active {
            background: #2D1B4E;
            border-color: #A78BFA;
        }
        
        .stat-card .stat-number {
            font-size: 1.4rem;
            font-weight: 700;
            line-height: 1.2;
        }
        
        .stat-card .stat-number.purple { color: var(--purple); }
        .stat-card .stat-number.green { color: var(--success); }
        .stat-card .stat-number.blue { color: var(--primary); }
        .stat-card .stat-number.orange { color: var(--warning); }
        .stat-card .stat-number.teal { color: var(--teal); }
        .stat-card .stat-number.red { color: var(--danger); }
        
        .stat-card .stat-label {
            font-size: 0.6rem;
            color: var(--text-secondary);
            font-weight: 500;
            margin-top: 1px;
        }
        
        /* ================================================================
           FILTER SECTION
           ================================================================ */
        .filter-section {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 16px 20px;
            border: 1px solid var(--border-color);
            margin-bottom: 20px;
        }
        
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }
        
        .filter-btn {
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            border: 2px solid var(--border-color);
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            white-space: nowrap;
        }
        
        .filter-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-bg);
        }
        
        .filter-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .filter-btn.purple.active {
            background: var(--purple);
            border-color: var(--purple);
        }
        
        .filter-input {
            padding: 7px 12px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.8rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: var(--transition);
        }
        
        .filter-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
        }
        
        .filter-input[type="date"] {
            width: 160px;
        }
        
        .btn-search {
            padding: 7px 18px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .btn-search:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
            padding: 6px 16px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }
        
        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-bg);
        }
        
        /* ================================================================
           TABLE
           ================================================================ */
        .table-container {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        
        .table-scroll {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
        }
        
        .data-table thead th {
            text-align: left;
            padding: 10px 14px;
            font-weight: 700;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #ffffff;
            background: var(--purple);
            border-bottom: 3px solid #6D28D9;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 5;
        }
        
        .data-table thead th i {
            margin-right: 5px;
            opacity: 0.7;
        }
        
        .data-table thead th:first-child {
            border-radius: 8px 0 0 0;
        }
        
        .data-table thead th:last-child {
            border-radius: 0 8px 0 0;
        }
        
        .data-table tbody td {
            padding: 8px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .data-table tbody tr:hover td {
            background: var(--primary-bg);
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
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        
        .badge-completed {
            background: var(--success-bg);
            color: var(--success);
            border: 1px solid var(--success);
        }
        
        [data-theme="dark"] .badge-completed {
            background: #1A3A2A;
            color: #34D399;
        }
        
        .result-preview {
            max-width: 180px;
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
            transition: var(--transition);
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-sm {
            padding: 3px 10px;
            font-size: 0.65rem;
            border-radius: 4px;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(11, 94, 215, 0.25);
        }
        
        .btn-purple {
            background: var(--purple);
            color: white;
        }
        
        .btn-purple:hover {
            background: #6D28D9;
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(124, 58, 237, 0.25);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background: var(--success-dark);
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(5, 150, 105, 0.25);
        }
        
        .btn-view {
            background: var(--gray-500);
            color: white;
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 0.6rem;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        
        .btn-view:hover {
            background: var(--gray-600);
            transform: translateY(-1px);
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
        
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            align-items: center;
        }
        
        /* ================================================================
           TABLE FOOTER
           ================================================================ */
        .table-footer {
            padding: 10px 16px;
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
            background: var(--purple);
            color: white;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
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
            font-size: 0.95rem;
        }
        
        .empty-state .sub {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 4px;
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
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(4, 1fr); }
        }
        
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .filter-row { flex-direction: column; align-items: stretch; }
            .filter-input { width: 100%; }
            .filter-input[type="date"] { width: 100%; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .data-table { font-size: 0.7rem; }
            .data-table thead th, .data-table tbody td { padding: 5px 8px; }
            .stat-card { padding: 10px 12px; }
            .stat-card .stat-number { font-size: 1.2rem; }
            .action-buttons { flex-direction: column; gap: 2px; }
            .filter-btn { font-size: 0.6rem; padding: 4px 10px; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 6px; }
            .stat-card { padding: 8px 10px; }
            .stat-card .stat-number { font-size: 1rem; }
            .page-header .page-title { font-size: 1.1rem; }
            .data-table td { padding: 4px 6px; font-size: 0.6rem; }
            .btn { padding: 2px 6px; font-size: 0.5rem; }
            .filter-btn { font-size: 0.55rem; padding: 3px 8px; }
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
        
        .animate-fade-in-up:nth-child(1) { animation-delay: 0.05s; }
        .animate-fade-in-up:nth-child(2) { animation-delay: 0.1s; }
        .animate-fade-in-up:nth-child(3) { animation-delay: 0.15s; }
        .animate-fade-in-up:nth-child(4) { animation-delay: 0.2s; }
        .animate-fade-in-up:nth-child(5) { animation-delay: 0.25s; }
        .animate-fade-in-up:nth-child(6) { animation-delay: 0.3s; }
        .animate-fade-in-up:nth-child(7) { animation-delay: 0.35s; }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- PAGE HEADER - PURPLE THEME -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-history"></i>
                Results History
                <span class="role-badge-display">LABORATORY</span>
                <span class="update-badge-light" id="updateBadge">
                    <i class="fas fa-sync-alt fa-spin"></i> Live
                </span>
            </h1>
            <p class="page-subtitle">
                View all completed laboratory test results
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
                <span class="branch-tag" style="background:rgba(124,58,237,0.2);border-color:rgba(124,58,237,0.2);color:#A78BFA;">
                    <i class="fas fa-flask"></i> <?= $total_completed ?> Total
                </span>
                <span class="branch-tag" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.2);color:#34D399;">
                    <i class="fas fa-calendar-day"></i> <?= $completed_today ?> Today
                </span>
                <span class="branch-tag" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.2);color:#FBBF24;">
                    <i class="fas fa-filter"></i> <?= getFilterLabel($filter) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <button onclick="window.print()" class="btn-outline-light" style="background:rgba(255,255,255,0.15);">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATS CARDS - 7 CARDS -->
    <!-- ================================================================ -->
    <div class="stats-grid animate-fade-in-up">
        <a href="?filter=all<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($date_filter) ? '&date=' . $date_filter : '' ?><?= $patient_filter > 0 ? '&patient=' . $patient_filter : '' ?>" 
           class="stat-card <?= $filter === 'all' ? 'active' : '' ?>">
            <p class="stat-number purple"><?= $total_completed ?></p>
            <p class="stat-label">📊 All</p>
        </a>
        <a href="?filter=today<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($date_filter) ? '&date=' . $date_filter : '' ?><?= $patient_filter > 0 ? '&patient=' . $patient_filter : '' ?>" 
           class="stat-card <?= $filter === 'today' ? 'active' : '' ?>">
            <p class="stat-number green"><?= $completed_today ?></p>
            <p class="stat-label">📅 Today</p>
        </a>
        <a href="?filter=week<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($date_filter) ? '&date=' . $date_filter : '' ?><?= $patient_filter > 0 ? '&patient=' . $patient_filter : '' ?>" 
           class="stat-card <?= $filter === 'week' ? 'active' : '' ?>">
            <p class="stat-number blue"><?= $completed_week ?></p>
            <p class="stat-label">📆 Week</p>
        </a>
        <a href="?filter=month<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($date_filter) ? '&date=' . $date_filter : '' ?><?= $patient_filter > 0 ? '&patient=' . $patient_filter : '' ?>" 
           class="stat-card <?= $filter === 'month' ? 'active' : '' ?>">
            <p class="stat-number orange"><?= $completed_month ?></p>
            <p class="stat-label">📈 Month</p>
        </a>
        <a href="?filter=3months<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($date_filter) ? '&date=' . $date_filter : '' ?><?= $patient_filter > 0 ? '&patient=' . $patient_filter : '' ?>" 
           class="stat-card <?= $filter === '3months' ? 'active' : '' ?>">
            <p class="stat-number teal"><?= $completed_3months ?></p>
            <p class="stat-label">📊 3 Months</p>
        </a>
        <a href="?filter=6months<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($date_filter) ? '&date=' . $date_filter : '' ?><?= $patient_filter > 0 ? '&patient=' . $patient_filter : '' ?>" 
           class="stat-card <?= $filter === '6months' ? 'active' : '' ?>">
            <p class="stat-number purple"><?= $completed_6months ?></p>
            <p class="stat-label">📊 6 Months</p>
        </a>
        <a href="?filter=1year<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($date_filter) ? '&date=' . $date_filter : '' ?><?= $patient_filter > 0 ? '&patient=' . $patient_filter : '' ?>" 
           class="stat-card <?= $filter === '1year' ? 'active' : '' ?>">
            <p class="stat-number red"><?= $completed_1year ?></p>
            <p class="stat-label">📊 1 Year</p>
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- FILTER SECTION -->
    <!-- ================================================================ -->
    <div class="filter-section animate-fade-in-up">
        <div class="filter-row">
            <a href="?filter=all<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($date_filter) ? '&date=' . $date_filter : '' ?><?= $patient_filter > 0 ? '&patient=' . $patient_filter : '' ?>" 
               class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">📋 All</a>
            <a href="?filter=today<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($date_filter) ? '&date=' . $date_filter : '' ?><?= $patient_filter > 0 ? '&patient=' . $patient_filter : '' ?>" 
               class="filter-btn <?= $filter === 'today' ? 'active' : '' ?>">📅 Today</a>
            <a href="?filter=week<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($date_filter) ? '&date=' . $date_filter : '' ?><?= $patient_filter > 0 ? '&patient=' . $patient_filter : '' ?>" 
               class="filter-btn <?= $filter === 'week' ? 'active' : '' ?>">📆 Week</a>
            <a href="?filter=month<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($date_filter) ? '&date=' . $date_filter : '' ?><?= $patient_filter > 0 ? '&patient=' . $patient_filter : '' ?>" 
               class="filter-btn <?= $filter === 'month' ? 'active' : '' ?>">📈 Month</a>
            <a href="?filter=3months<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($date_filter) ? '&date=' . $date_filter : '' ?><?= $patient_filter > 0 ? '&patient=' . $patient_filter : '' ?>" 
               class="filter-btn <?= $filter === '3months' ? 'active' : '' ?>">📊 3M</a>
            <a href="?filter=6months<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($date_filter) ? '&date=' . $date_filter : '' ?><?= $patient_filter > 0 ? '&patient=' . $patient_filter : '' ?>" 
               class="filter-btn <?= $filter === '6months' ? 'active' : '' ?>">📊 6M</a>
            <a href="?filter=1year<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($date_filter) ? '&date=' . $date_filter : '' ?><?= $patient_filter > 0 ? '&patient=' . $patient_filter : '' ?>" 
               class="filter-btn <?= $filter === '1year' ? 'active' : '' ?>">📊 1Y</a>
            
            <div style="flex:1;"></div>
            
            <form method="GET" class="filter-row" style="flex:1;gap:8px;">
                <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                <input type="text" name="search" class="filter-input" placeholder="🔍 Search patient or test..." 
                       value="<?= htmlspecialchars($search) ?>" style="flex:1;min-width:150px;">
                <input type="date" name="date" class="filter-input" value="<?= htmlspecialchars($date_filter) ?>">
                
                <?php if (!empty($patients_list)): ?>
                    <select name="patient" class="filter-input" style="min-width:140px;">
                        <option value="0">All Patients</option>
                        <?php foreach ($patients_list as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= $patient_filter == $p['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
                
                <button type="submit" class="btn-search">
                    <i class="fas fa-search"></i> Filter
                </button>
                <?php if (!empty($search) || !empty($date_filter) || $patient_filter > 0 || $filter !== 'all'): ?>
                    <a href="results_history.php" class="btn-outline">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RESULTS TABLE -->
    <!-- ================================================================ -->
    <div class="table-container animate-fade-in-up">
        <div class="table-scroll">
            <table class="data-table" id="historyTable">
                <thead>
                    <tr>
                        <th style="border-radius: 8px 0 0 0;">#</th>
                        <th><i class="fas fa-flask"></i> Test</th>
                        <th><i class="fas fa-user"></i> Patient</th>
                        <th><i class="fas fa-user-md"></i> Doctor</th>
                        <th><i class="fas fa-info-circle"></i> Status</th>
                        <th><i class="fas fa-file-medical"></i> Result</th>
                        <th><i class="fas fa-calendar-check"></i> Completed</th>
                        <th style="border-radius: 0 8px 0 0;"><i class="fas fa-cog"></i> Actions</th>
                    </tr>
                </thead>
                <tbody id="historyTableBody">
                    <?php if (count($completed_tests) > 0): ?>
                        <?php $i = 1; foreach ($completed_tests as $test): 
                            $age = calculateAge($test['date_of_birth'] ?? '');
                            $has_result = !empty($test['results']);
                            $result_preview = $has_result ? $test['results'] : 'No result';
                            $completed_date = $test['completed_at'] ?? $test['created_at'] ?? '';
                        ?>
                            <tr class="test-row" data-id="<?= $test['id'] ?>">
                                <td><?= $i++ ?></td>
                                <td>
                                    <div class="font-medium text-sm"><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></div>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($test['test_category'] ?? 'N/A') ?></div>
                                    <?php if (!empty($test['test_price']) && $test['test_price'] > 0): ?>
                                        <div class="text-xs text-gray-400">TSh <?= number_format($test['test_price']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($test['reference_range'])): ?>
                                        <div class="text-xs text-gray-400">Ref: <?= htmlspecialchars($test['reference_range']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="font-medium text-sm"><?= htmlspecialchars($test['patient_name'] ?? 'Unknown') ?></div>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($test['patient_code'] ?? 'N/A') ?></div>
                                    <div class="text-xs text-gray-400">
                                        <?= htmlspecialchars($test['gender'] ?? 'N/A') ?> • <?= $age ?> yrs
                                    </div>
                                    <?php if (!empty($test['phone'])): ?>
                                        <div class="text-xs text-gray-400">📱 <?= htmlspecialchars($test['phone']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="text-sm">Dr. <?= htmlspecialchars($test['doctor_name'] ?? 'N/A') ?></div>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($test['specialty'] ?? 'GP') ?></div>
                                    <?php if (!empty($test['visit_number'])): ?>
                                        <div class="text-xs text-gray-400">Visit: <?= htmlspecialchars($test['visit_number']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($test['lab_technician_name'])): ?>
                                        <div class="text-xs text-green-600">By: <?= htmlspecialchars($test['lab_technician_name']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge-status badge-completed">
                                        <i class="fas fa-check-circle"></i> Completed
                                    </span>
                                </td>
                                <td>
                                    <div class="result-preview <?= $has_result ? 'has-result' : '' ?>">
                                        <?php if ($has_result): ?>
                                            <?= htmlspecialchars(substr($result_preview, 0, 50)) ?>
                                            <?php if (strlen($result_preview) > 50): ?>...<?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-gray-400 italic">No result</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($test['interpretation'])): ?>
                                        <div class="text-xs text-gray-400"><?= htmlspecialchars(substr($test['interpretation'], 0, 40)) ?>...</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="text-xs"><?= formatDate($completed_date) ?></span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view_test.php?id=<?= $test['id'] ?>" class="btn-view" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($has_result): ?>
                                            <a href="view_test.php?id=<?= $test['id'] ?>&print=1" class="btn btn-outline btn-sm" title="Print Result" style="border-color:var(--success);color:var(--success);" target="_blank">
                                                <i class="fas fa-print"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <i class="fas fa-history" style="color: var(--primary);"></i>
                                    <p>No completed results found</p>
                                    <p class="sub">Completed tests will appear here</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Table Footer -->
        <div class="table-footer">
            <span>
                <i class="fas fa-list"></i> Showing <strong id="recordCount"><?= count($completed_tests) ?></strong> result(s)
                <span class="text-xs text-gray-400 ml-2">🏥 <?= htmlspecialchars($user_branch_name) ?></span>
                <span class="text-xs text-gray-400 ml-2">📊 <?= getFilterLabel($filter) ?></span>
            </span>
            <span>
                <span class="count-badge" id="totalCountBadge"><?= $total_completed ?></span> Total completed
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
            Results History
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
        var updateTimeDisplay = document.getElementById('updateTimeDisplay');
        if (updateTimeDisplay) {
            updateTimeDisplay.textContent = 'Last update: ' + timeStr;
        }
        var updateBadge = document.getElementById('updateBadge');
        if (updateBadge) {
            updateBadge.innerHTML = '<i class="fas fa-check-circle" style="color:#34D399;"></i> Live • ' + timeStr;
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
        var filter = document.querySelector('input[name="filter"]')?.value || 'all';
        var date = document.querySelector('input[name="date"]')?.value || '';
        var patient = document.querySelector('select[name="patient"]')?.value || 0;
        var params = [];
        if (query) params.push('search=' + encodeURIComponent(query));
        if (filter !== 'all') params.push('filter=' + filter);
        if (date) params.push('date=' + date);
        if (patient > 0) params.push('patient=' + patient);
        window.location.href = 'results_history.php' + (params.length > 0 ? '?' + params.join('&') : '');
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

    console.log('%c🧪 Braick - Results History (NEW DATABASE)', 'font-size:18px; font-weight:bold; color:#7C3AED;');
    console.log('%c📊 Using NEW DATABASE: dispensary_db', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Tables: lab_tests, patients, users, visits, lab_tests_catalog', 'font-size:13px; color:#34D399;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?> (ID: <?= $user_id ?>)', 'font-size:13px; color:#059669;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($user_branch_name) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📊 Total: <?= $total_completed ?> | Today: <?= $completed_today ?> | Week: <?= $completed_week ?> | Month: <?= $completed_month ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📊 3 Months: <?= $completed_3months ?> | 6 Months: <?= $completed_6months ?> | 1 Year: <?= $completed_1year ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📋 Showing: <?= count($completed_tests) ?> completed tests', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🎨 Purple theme applied with 7 stat cards', 'font-size:13px; color:#7C3AED;');
    console.log('%c✅ Filters: Today, Week, Month, 3 Months, 6 Months, 1 Year, All', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>