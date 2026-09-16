<?php
// ================================================================
// FILE: frontend/pages/laboratory/results_history.php
// LABORATORY - RESULTS HISTORY (GROUPED BY PATIENT) - BLUE THEME
// ================================================================
// ✅ GROUPED BY PATIENT - Kila patient ana card yake
// ✅ BLUE THEME - Rangi zote ni blue
// ✅ LIVE SEARCH - Filter patients kwa jina, ID, test, doctor
// ✅ PRINT BUTTONS - Per patient + per test
// ✅ Same style as pending_tests.php na completed_tests.php
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'laboratory') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Lab Technician';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_username = $_SESSION['username'] ?? 'lab.technician';
$profile_pic = $_SESSION['profile_pic'] ?? '';

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
// BUILD QUERY - COMPLETED TESTS
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
        lt.sample_type,
        lt.reference_range,
        lt.formatted_result,
        p.id as patient_id,
        p.full_name as patient_name,
        p.patient_id as patient_code,
        p.phone,
        p.gender,
        p.date_of_birth,
        p.blood_group,
        u.full_name as doctor_name,
        u.specialty,
        v.visit_number,
        v.visit_type,
        v.diagnosis,
        lab.full_name as lab_technician_name,
        ltc.category as test_category,
        ltc.price as test_price
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

// Filter by time period
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

$query .= " ORDER BY p.full_name ASC, lt.completed_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$all_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// ✅ GROUP TESTS BY PATIENT
// ================================================================
$patients = [];
foreach ($all_tests as $test) {
    $patient_id = $test['patient_id'] ?? 0;
    
    if (!isset($patients[$patient_id])) {
        $patients[$patient_id] = [
            'patient_id' => $patient_id,
            'patient_name' => $test['patient_name'] ?? 'Unknown Patient',
            'patient_code' => $test['patient_code'] ?? 'N/A',
            'phone' => $test['phone'] ?? '',
            'gender' => $test['gender'] ?? '',
            'date_of_birth' => $test['date_of_birth'] ?? '',
            'blood_group' => $test['blood_group'] ?? '',
            'visit_number' => $test['visit_number'] ?? '',
            'visit_type' => $test['visit_type'] ?? '',
            'doctor_name' => $test['doctor_name'] ?? 'N/A',
            'specialty' => $test['specialty'] ?? 'GP',
            'tests' => [],
            'latest_completed' => $test['completed_at'],
            'earliest_completed' => $test['completed_at'],
            'total_price' => 0
        ];
    }
    
    $patients[$patient_id]['tests'][] = $test;
    $patients[$patient_id]['total_price'] += (float)($test['test_price'] ?? 0);
    
    if (strtotime($test['completed_at']) > strtotime($patients[$patient_id]['latest_completed'])) {
        $patients[$patient_id]['latest_completed'] = $test['completed_at'];
    }
    if (strtotime($test['completed_at']) < strtotime($patients[$patient_id]['earliest_completed'])) {
        $patients[$patient_id]['earliest_completed'] = $test['completed_at'];
    }
}

$patients_array = array_values($patients);
foreach ($patients_array as &$p) {
    $p['test_count'] = count($p['tests']);
}
unset($p);

// Sort by latest completed
usort($patients_array, function($a, $b) {
    return strtotime($b['latest_completed']) - strtotime($a['latest_completed']);
});

$total_patients = count($patients_array);
$total_completed_shown = count($all_tests);

// ================================================================
// GET STATISTICS
// ================================================================
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ? AND status = 'completed'");
$stmt->execute([$user_branch_id]);
$total_completed = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$today = date('Y-m-d');
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ? AND status = 'completed' AND DATE(completed_at) = ?");
$stmt->execute([$user_branch_id, $today]);
$completed_today = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ? AND status = 'completed' AND completed_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$stmt->execute([$user_branch_id]);
$completed_week = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ? AND status = 'completed' AND completed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
$stmt->execute([$user_branch_id]);
$completed_month = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ? AND status = 'completed' AND completed_at >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)");
$stmt->execute([$user_branch_id]);
$completed_3months = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ? AND status = 'completed' AND completed_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)");
$stmt->execute([$user_branch_id]);
$completed_6months = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ? AND status = 'completed' AND completed_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)");
$stmt->execute([$user_branch_id]);
$completed_1year = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// GET PATIENTS LIST FOR FILTER
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

$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

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

function getTimeAgo($datetime) {
    if (empty($datetime)) return 'N/A';
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 172800) return 'Yesterday';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return date('d/m/Y', $time);
}

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
        :root {
            /* BLUE THEME */
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-darker: #083C8A;
            --primary-light: #6EA8FE;
            --primary-lighter: #93C5FD;
            --primary-bg: #E8F0FE;
            --primary-bg-dark: #1E3A5F;
            --info: #3B82F6;
            --info-dark: #2563EB;
            --info-bg: #DBEAFE;
            --success: #059669;
            --success-dark: #047857;
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
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --radius: 10px;
            --radius-lg: 14px;
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 30px rgba(0,0,0,0.12);
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
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
        
        /* PAGE HEADER - BLUE */
        .page-header {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8, #083C8A);
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.3);
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
        
        .page-header .page-title i { font-size: 2rem; opacity: 0.9; }
        
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
            cursor: pointer;
        }
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
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
        
        /* STATS - 7 BLUE CARDS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            border-radius: 12px;
            padding: 14px 16px;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            color: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            text-decoration: none;
            display: block;
            min-height: 90px;
            border: none;
        }
        
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 12px 40px rgba(0,0,0,0.15); }
        .stat-card .stat-icon { font-size: 1.1rem; opacity: 0.9; margin-bottom: 2px; display: block; }
        .stat-card .stat-number { font-size: 1.5rem; font-weight: 700; color: white; line-height: 1.2; }
        .stat-card .stat-label { font-size: 0.6rem; color: rgba(255,255,255,0.85); font-weight: 500; margin-top: 2px; }
        .stat-card .stat-sub { font-size: 0.5rem; color: rgba(255,255,255,0.55); margin-top: 2px; }
        
        /* ALL BLUE VARIANTS */
        .stat-card.blue-1 { background: linear-gradient(135deg, #3B82F6, #0B5ED7, #0A4CA8); }
        .stat-card.blue-2 { background: linear-gradient(135deg, #0EA5E9, #0284C7, #075985); }
        .stat-card.blue-3 { background: linear-gradient(135deg, #0B5ED7, #083C8A, #062E6B); }
        .stat-card.blue-4 { background: linear-gradient(135deg, #4F46E5, #4338CA, #3730A3); }
        .stat-card.blue-5 { background: linear-gradient(135deg, #06B6D4, #0891B2, #0E7490); }
        .stat-card.blue-6 { background: linear-gradient(135deg, #3B82F6, #1D4ED8, #1E3A8A); }
        .stat-card.blue-7 { background: linear-gradient(135deg, #6366F1, #4F46E5, #4338CA); }
        
        /* ACTIVE STATE */
        .stat-card.active {
            box-shadow: 0 0 0 4px rgba(255,255,255,0.4), 0 12px 40px rgba(0,0,0,0.2);
            transform: translateY(-4px);
        }
        
        /* ACTION BAR */
        .action-bar {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 12px 20px;
            border: 1px solid var(--border-color);
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            box-shadow: var(--shadow);
        }
        
        .action-bar .action-info {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .action-bar .info-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 600;
        }
        
        .action-bar .info-badge.blue {
            background: var(--primary-bg);
            color: var(--primary);
            border: 1px solid var(--primary);
        }
        
        .action-bar .info-badge.info {
            background: var(--info-bg);
            color: var(--info);
            border: 1px solid var(--info);
        }
        
        .action-bar .info-badge.purple {
            background: var(--purple-bg);
            color: var(--purple);
            border: 1px solid var(--purple);
        }
        
        /* FILTER BAR */
        .filter-bar {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 12px 20px;
            border: 1px solid var(--border-color);
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            box-shadow: var(--shadow);
        }
        
        .filter-bar .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .filter-bar input,
        .filter-bar select {
            padding: 8px 14px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.82rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: all 0.3s;
            height: 38px;
        }
        
        .filter-bar input:focus,
        .filter-bar select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
        }
        
        .filter-bar .btn-filter {
            padding: 8px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.82rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            height: 38px;
        }
        
        .filter-bar .btn-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .filter-bar .btn-reset {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        
        .filter-bar .btn-reset:hover {
            border-color: var(--danger);
            color: var(--danger);
        }
        
        /* SEARCH TOOLBAR */
        .search-toolbar {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            border-radius: var(--radius-lg);
            padding: 12px 20px;
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            box-shadow: 0 4px 16px rgba(11, 94, 215, 0.2);
        }
        
        .search-toolbar .toolbar-title {
            color: white;
            font-size: 0.9rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .search-toolbar .search-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            background: rgba(255,255,255,0.15);
            border: 2px solid rgba(255,255,255,0.25);
            border-radius: 10px;
            padding: 0 14px;
            transition: all 0.3s ease;
            min-width: 320px;
            height: 40px;
        }
        
        .search-toolbar .search-wrapper:focus-within {
            background: rgba(255,255,255,0.25);
            border-color: rgba(255,255,255,0.5);
            box-shadow: 0 0 0 3px rgba(255,255,255,0.1);
        }
        
        .search-toolbar .search-wrapper .search-icon {
            color: rgba(255,255,255,0.8);
            font-size: 0.9rem;
            margin-right: 8px;
        }
        
        .search-toolbar .search-wrapper input {
            flex: 1;
            background: transparent;
            border: none;
            outline: none;
            color: white;
            font-size: 0.85rem;
            padding: 0;
            font-weight: 500;
        }
        
        .search-toolbar .search-wrapper input::placeholder {
            color: rgba(255,255,255,0.6);
        }
        
        .search-toolbar .search-wrapper .search-clear {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            transition: all 0.2s ease;
            margin-left: 8px;
        }
        
        .search-toolbar .search-wrapper .search-clear.visible { display: flex; }
        .search-toolbar .search-wrapper .search-clear:hover { background: rgba(255,255,255,0.35); }
        
        .search-results-count {
            color: rgba(255,255,255,0.9);
            font-size: 0.7rem;
            font-weight: 600;
            background: rgba(255,255,255,0.2);
            padding: 3px 12px;
            border-radius: 12px;
            margin-left: 8px;
            white-space: nowrap;
            display: none;
        }
        
        /* PATIENT CARD */
        .patient-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px solid var(--border-color);
            margin-bottom: 20px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }
        
        .patient-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        /* PATIENT HEADER - BLUE */
        .patient-header {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            color: white;
            padding: 14px 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .patient-header:hover {
            background: linear-gradient(135deg, #0A4CA8, #083C8A);
        }
        
        .patient-header .patient-info {
            display: flex;
            align-items: center;
            gap: 14px;
            flex: 1;
            min-width: 250px;
        }
        
        .patient-header .patient-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: rgba(255,255,255,0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.3rem;
            color: white;
            flex-shrink: 0;
            border: 2px solid rgba(255,255,255,0.4);
            backdrop-filter: blur(4px);
        }
        
        .patient-header .patient-name {
            font-weight: 700;
            font-size: 1.05rem;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .patient-header .patient-meta {
            display: flex;
            gap: 14px;
            font-size: 0.75rem;
            opacity: 0.9;
            flex-wrap: wrap;
            margin-top: 3px;
        }
        
        .patient-header .patient-meta span {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .patient-header .patient-stats {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .patient-header .patient-stats .stat-pill {
            background: rgba(255,255,255,0.2);
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,0.15);
        }
        
        .patient-header .chevron {
            font-size: 0.9rem;
            transition: transform 0.3s ease;
        }
        
        .patient-header .chevron.rotated {
            transform: rotate(180deg);
        }
        
        .patient-body {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease, padding 0.3s ease;
            background: var(--bg-card);
        }
        
        .patient-body.open {
            max-height: 5000px;
            padding: 16px 22px 20px;
        }
        
        /* PATIENT ACTIONS */
        .patient-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding-bottom: 14px;
            border-bottom: 2px dashed var(--border-color);
            margin-bottom: 14px;
            flex-wrap: wrap;
        }
        
        .patient-actions .patient-actions-info {
            font-size: 0.8rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        /* PRINT PATIENT BUTTON */
        .btn-print-patient {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 20px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.78rem;
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            color: white;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
            text-decoration: none;
        }
        
        .btn-print-patient:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(11, 94, 215, 0.5);
        }
        
        /* TABLE */
        .table-scroll { overflow-x: auto; border-radius: 10px; }
        
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
            background: var(--primary);
            border-bottom: 3px solid var(--primary-dark);
            white-space: nowrap;
        }
        
        .data-table thead th i { margin-right: 5px; opacity: 0.7; }
        
        .data-table tbody td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .data-table tbody tr:hover td { background: var(--primary-bg); }
        .data-table tbody tr:last-child td { border-bottom: none; }
        .data-table tbody tr:nth-child(even) td { background: var(--gray-50); }
        
        [data-theme="dark"] .data-table tbody tr:nth-child(even) td { background: #1A1A2E; }
        [data-theme="dark"] .data-table tbody tr:hover td { background: #1E3A5F; }
        
        /* BADGES */
        .badge-status {
            display: inline-block;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
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
            border-color: #059669;
        }
        
        /* TIME AGO */
        .time-ago {
            font-size: 0.7rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        
        .time-ago.recent { 
            background: var(--success-bg); 
            color: var(--success); 
        }
        
        .time-ago.today { 
            background: var(--info-bg); 
            color: var(--info); 
        }
        
        .time-ago.older { 
            background: var(--gray-100); 
            color: var(--text-secondary); 
        }
        
        [data-theme="dark"] .time-ago.recent { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .time-ago.today { background: #1E3A5F; color: #93C5FD; }
        [data-theme="dark"] .time-ago.older { background: #1E293B; color: #94A3B8; }
        
        /* RESULT PREVIEW */
        .result-preview {
            font-size: 0.7rem;
            color: var(--text-secondary);
            padding: 4px 8px;
            background: var(--bg-card);
            border-radius: 6px;
            border-left: 3px solid var(--success);
            font-family: monospace;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 250px;
        }
        
        /* ACTION BUTTONS */
        .action-buttons-vertical {
            display: flex;
            flex-direction: column;
            gap: 5px;
            align-items: stretch;
            justify-content: center;
            min-width: 80px;
        }
        
        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            padding: 6px 12px;
            border-radius: 7px;
            font-weight: 700;
            font-size: 0.68rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
            white-space: nowrap;
            width: 100%;
            height: 30px;
        }
        
        .btn-action i { font-size: 0.75rem; }
        
        .btn-action.btn-view-action {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            color: white;
            box-shadow: 0 2px 6px rgba(11, 94, 215, 0.3);
        }
        
        .btn-action.btn-view-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.5);
        }
        
        .btn-action.btn-print-action {
            background: linear-gradient(135deg, #64748B, #475569);
            color: white;
            box-shadow: 0 2px 6px rgba(100, 116, 139, 0.3);
        }
        
        .btn-action.btn-print-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(100, 116, 139, 0.5);
        }
        
        /* EMPTY STATE */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px dashed var(--border-color);
        }
        
        .empty-state i {
            font-size: 3.5rem;
            color: var(--primary);
            display: block;
            margin-bottom: 12px;
        }
        
        .empty-state p { font-size: 1rem; font-weight: 600; color: var(--text-primary); }
        .empty-state .sub { font-size: 0.85rem; color: var(--text-secondary); margin-top: 4px; font-weight: 400; }
        .empty-state.no-results i { color: var(--primary); }
        
        /* TOAST */
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 14px 20px;
            border-radius: 12px;
            z-index: 9999;
            max-width: 400px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            box-shadow: var(--shadow-lg);
            font-size: 0.85rem;
        }
        
        .toast-custom.show { transform: translateY(0); opacity: 1; }
        .toast-custom.success { background: linear-gradient(135deg, #059669, #047857); }
        .toast-custom.error { background: linear-gradient(135deg, #DC2626, #B91C1C); }
        .toast-custom.info { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
        .toast-custom.warning { background: linear-gradient(135deg, #D97706, #B45309); }
        
        /* FOOTER */
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        /* RESPONSIVE */
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
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
            .stat-card { padding: 12px 14px; min-height: 90px; }
            .stat-card .stat-number { font-size: 1.4rem; }
            .search-toolbar { flex-direction: column; align-items: stretch; }
            .search-toolbar .search-wrapper { min-width: 100%; }
            .action-bar { flex-direction: column; align-items: stretch; }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .filter-bar .filter-group { width: 100%; flex-direction: column; }
            .filter-bar input, .filter-bar select { width: 100%; }
            .patient-header { flex-direction: column; align-items: stretch; }
            .patient-actions { flex-direction: column; align-items: stretch; }
            .btn-print-patient { width: 100%; justify-content: center; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
            .stat-card { padding: 10px 12px; min-height: 75px; }
            .stat-card .stat-number { font-size: 1.1rem; }
            .page-header .page-title { font-size: 1.1rem; }
            .btn-action { font-size: 0.6rem; padding: 4px 8px; height: 28px; }
            .data-table td { padding: 6px 8px; font-size: 0.7rem; }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up { animation: fadeInUp 0.5s ease forwards; opacity: 0; }
        
        /* Print styles */
        @media print {
            .page-header, .action-bar, .filter-bar, .search-toolbar,
            .patient-actions, .btn-print-patient, .btn-action,
            .footer, .toast-custom, .stats-grid { display: none !important; }
            
            .main-content { margin: 0; padding: 20px; }
            
            .patient-card { page-break-inside: avoid; box-shadow: none; }
            .patient-header { background: #0B5ED7 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .patient-body { max-height: none !important; padding: 20px !important; }
            
            .data-table thead th { background: #0B5ED7 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>

<main class="main-content">

    <!-- PAGE HEADER - BLUE -->
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
                View all completed laboratory test results (Grouped by Patient)
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
                <span class="branch-tag" style="background:rgba(255,255,255,0.15);">
                    <i class="fas fa-flask"></i> <?= $total_completed ?> Total
                </span>
                <span class="branch-tag" style="background:rgba(52,211,153,0.25);color:#A7F3D0;">
                    <i class="fas fa-calendar-day"></i> <?= $completed_today ?> Today
                </span>
                <span class="branch-tag" style="background:rgba(255,255,255,0.15);">
                    <i class="fas fa-filter"></i> <?= getFilterLabel($filter) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <a href="completed_tests.php" class="btn-outline-light">
                <i class="fas fa-check-circle"></i> Completed
            </a>
            <button onclick="window.print()" class="btn-outline-light">
                <i class="fas fa-print"></i> Print Page
            </button>
        </div>
    </div>

    <!-- STATS CARDS - 7 BLUE VARIANTS -->
    <div class="stats-grid animate-fade-in-up">
        <a href="?filter=all<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($date_filter) ? '&date=' . $date_filter : '' ?><?= $patient_filter > 0 ? '&patient=' . $patient_filter : '' ?>" 
           class="stat-card blue-1 <?= $filter === 'all' ? 'active' : '' ?>">
            <span class="stat-icon">📊</span>
            <div class="stat-number"><?= $total_completed ?></div>
            <div class="stat-label">All</div>
            <div class="stat-sub">All time</div>
        </a>
        
        <a href="?filter=today<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($date_filter) ? '&date=' . $date_filter : '' ?><?= $patient_filter > 0 ? '&patient=' . $patient_filter : '' ?>" 
           class="stat-card blue-2 <?= $filter === 'today' ? 'active' : '' ?>">
            <span class="stat-icon">📅</span>
            <div class="stat-number"><?= $completed_today ?></div>
            <div class="stat-label">Today</div>
            <div class="stat-sub"><?= date('d M') ?></div>
        </a>
        
        <a href="?filter=week<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($date_filter) ? '&date=' . $date_filter : '' ?><?= $patient_filter > 0 ? '&patient=' . $patient_filter : '' ?>" 
           class="stat-card blue-3 <?= $filter === 'week' ? 'active' : '' ?>">
            <span class="stat-icon">📆</span>
            <div class="stat-number"><?= $completed_week ?></div>
            <div class="stat-label">Week</div>
            <div class="stat-sub">Last 7 days</div>
        </a>
        
        <a href="?filter=month<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($date_filter) ? '&date=' . $date_filter : '' ?><?= $patient_filter > 0 ? '&patient=' . $patient_filter : '' ?>" 
           class="stat-card blue-4 <?= $filter === 'month' ? 'active' : '' ?>">
            <span class="stat-icon">📈</span>
            <div class="stat-number"><?= $completed_month ?></div>
            <div class="stat-label">Month</div>
            <div class="stat-sub">Last 30 days</div>
        </a>
        
        <a href="?filter=3months<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($date_filter) ? '&date=' . $date_filter : '' ?><?= $patient_filter > 0 ? '&patient=' . $patient_filter : '' ?>" 
           class="stat-card blue-5 <?= $filter === '3months' ? 'active' : '' ?>">
            <span class="stat-icon">🗓️</span>
            <div class="stat-number"><?= $completed_3months ?></div>
            <div class="stat-label">3 Months</div>
            <div class="stat-sub">Last quarter</div>
        </a>
        
        <a href="?filter=6months<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($date_filter) ? '&date=' . $date_filter : '' ?><?= $patient_filter > 0 ? '&patient=' . $patient_filter : '' ?>" 
           class="stat-card blue-6 <?= $filter === '6months' ? 'active' : '' ?>">
            <span class="stat-icon">📊</span>
            <div class="stat-number"><?= $completed_6months ?></div>
            <div class="stat-label">6 Months</div>
            <div class="stat-sub">Half year</div>
        </a>
        
        <a href="?filter=1year<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($date_filter) ? '&date=' . $date_filter : '' ?><?= $patient_filter > 0 ? '&patient=' . $patient_filter : '' ?>" 
           class="stat-card blue-7 <?= $filter === '1year' ? 'active' : '' ?>">
            <span class="stat-icon">📅</span>
            <div class="stat-number"><?= $completed_1year ?></div>
            <div class="stat-label">1 Year</div>
            <div class="stat-sub">Last 12 months</div>
        </a>
    </div>

    <!-- ACTION BAR -->
    <div class="action-bar animate-fade-in-up">
        <div class="action-info">
            <span class="info-badge blue">
                <i class="fas fa-check-circle"></i> <?= $total_completed_shown ?> Result(s) Shown
            </span>
            <span class="info-badge info">
                <i class="fas fa-flask"></i> <?= $completed_today ?> Today
            </span>
            <span class="info-badge purple">
                <i class="fas fa-users"></i> <?= $total_patients ?> Patients
            </span>
        </div>
    </div>

    <!-- FILTER BAR -->
    <div class="filter-bar animate-fade-in-up">
        <form method="GET" action="" class="filter-group">
            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
            
            <input type="date" name="date" value="<?= htmlspecialchars($date_filter) ?>" title="Filter by date">
            
            <?php if (!empty($patients_list)): ?>
                <select name="patient" title="Filter by patient">
                    <option value="0">👤 All Patients</option>
                    <?php foreach ($patients_list as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $patient_filter == $p['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
            
            <button type="submit" class="btn-filter">
                <i class="fas fa-filter"></i> Apply Filters
            </button>
            
            <?php if (!empty($search) || !empty($date_filter) || $patient_filter > 0 || $filter !== 'all'): ?>
                <a href="results_history.php" class="btn-filter btn-reset">
                    <i class="fas fa-times"></i> Clear All
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- SEARCH TOOLBAR -->
    <div class="search-toolbar animate-fade-in-up">
        <div class="toolbar-title">
            <i class="fas fa-list"></i>
            Patient Results List
            <span style="background:rgba(255,255,255,0.2);padding:2px 10px;border-radius:12px;font-size:0.65rem;font-weight:600;" id="headerCount">
                <?= $total_patients ?>
            </span>
        </div>
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <div class="search-wrapper">
                <i class="fas fa-search search-icon"></i>
                <input type="text" 
                       id="tableSearch" 
                       placeholder="Search patient name, ID, test, doctor..." 
                       autocomplete="off"
                       value="<?= htmlspecialchars($search) ?>">
                <button type="button" class="search-clear" id="searchClear" title="Clear search">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <span class="search-results-count" id="searchResultsCount"></span>
        </div>
    </div>

    <!-- PATIENTS LIST -->
    <div id="patientsContainer">
        <?php if (count($patients_array) > 0): ?>
            <?php foreach ($patients_array as $patient): 
                $patient_id = $patient['patient_id'];
                $test_count = $patient['test_count'];
                $age = calculateAge($patient['date_of_birth']);
                $latest_time = getTimeAgo($patient['latest_completed']);
                $diff_hours = (time() - strtotime($patient['latest_completed'])) / 3600;
                $time_class = 'older';
                if ($diff_hours < 1) $time_class = 'recent';
                elseif ($diff_hours < 24) $time_class = 'today';
            ?>
                <div class="patient-card animate-fade-in-up" data-patient-id="<?= $patient_id ?>">
                    <!-- PATIENT HEADER - BLUE -->
                    <div class="patient-header" onclick="togglePatient(<?= $patient_id ?>)">
                        <div class="patient-info">
                            <div class="patient-avatar" style="background: <?= '#' . substr(md5($patient['patient_name']), 0, 6) ?>;">
                                <?= strtoupper(substr($patient['patient_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <div class="patient-name">
                                    <?= htmlspecialchars($patient['patient_name']) ?>
                                    <?php if ($test_count > 1): ?>
                                        <span style="background:rgba(255,255,255,0.25);padding:2px 10px;border-radius:12px;font-size:0.6rem;font-weight:600;">
                                            <i class="fas fa-flask"></i> <?= $test_count ?> Tests
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="patient-meta">
                                    <span><i class="fas fa-id-card"></i> <?= htmlspecialchars($patient['patient_code']) ?></span>
                                    <?php if (!empty($patient['phone'])): ?>
                                        <span><i class="fas fa-phone"></i> <?= htmlspecialchars($patient['phone']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($patient['gender'])): ?>
                                        <span><i class="fas fa-<?= $patient['gender'] === 'Female' ? 'venus' : 'mars' ?>"></i> <?= htmlspecialchars($patient['gender']) ?> • <?= $age ?> yrs</span>
                                    <?php endif; ?>
                                    <?php if (!empty($patient['visit_number'])): ?>
                                        <span><i class="fas fa-stethoscope"></i> <?= htmlspecialchars($patient['visit_number']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="patient-stats">
                            <span class="stat-pill">
                                <i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($patient['doctor_name']) ?>
                            </span>
                            <?php if ($patient['total_price'] > 0): ?>
                                <span class="stat-pill" style="background:rgba(52,211,153,0.25);color:#A7F3D0;">
                                    <i class="fas fa-money-bill-wave"></i> TSh <?= number_format($patient['total_price'], 0) ?>
                                </span>
                            <?php endif; ?>
                            <span class="stat-pill" style="background:rgba(255,255,255,0.3);">
                                <i class="fas fa-clock"></i> <?= $latest_time ?>
                            </span>
                            <i class="fas fa-chevron-down chevron" id="chevron-<?= $patient_id ?>"></i>
                        </div>
                    </div>
                    
                    <!-- PATIENT BODY -->
                    <div class="patient-body" id="body-<?= $patient_id ?>">
                        
                        <!-- PATIENT ACTIONS -->
                        <div class="patient-actions">
                            <div class="patient-actions-info">
                                <i class="fas fa-info-circle" style="color:var(--primary);"></i>
                                <strong><?= $test_count ?></strong> completed test(s) for this patient.
                            </div>
                            <!-- ✅ PRINT ALL PATIENT RESULTS BUTTON -->
                            <a href="print_patient_results.php?patient_id=<?= $patient_id ?>" 
                               class="btn-print-patient" 
                               target="_blank"
                               title="Print All Results for <?= htmlspecialchars($patient['patient_name']) ?>">
                                <i class="fas fa-print"></i>
                                PRINT ALL (<?= $test_count ?>)
                            </a>
                        </div>
                        
                        <!-- PATIENT TESTS TABLE -->
                        <div class="table-scroll">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th style="width:50px;">#</th>
                                        <th><i class="fas fa-flask"></i> Test</th>
                                        <th><i class="fas fa-file-medical-alt"></i> Result</th>
                                        <th><i class="fas fa-info-circle"></i> Status</th>
                                        <th><i class="fas fa-clock"></i> Completed</th>
                                        <th style="text-align:center;width:120px;"><i class="fas fa-cog"></i> Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $i = 1; foreach ($patient['tests'] as $test): 
                                        $test_id = $test['id'];
                                        $view_link = "view_test.php?id=" . $test_id;
                                        $print_link = "view_test.php?id=" . $test_id . "&print=1";
                                        $completed_time = getTimeAgo($test['completed_at']);
                                        $diff_h = (time() - strtotime($test['completed_at'])) / 3600;
                                        $t_class = 'older';
                                        if ($diff_h < 1) $t_class = 'recent';
                                        elseif ($diff_h < 24) $t_class = 'today';
                                        $result_text = $test['results'] ?? '';
                                        $has_result = !empty($result_text);
                                    ?>
                                        <tr class="test-row"
                                            data-search="<?= htmlspecialchars(strtolower($test['test_name'] . ' ' . $patient['patient_name'] . ' ' . $patient['patient_code'] . ' ' . ($test['doctor_name'] ?? '') . ' ' . $patient['phone'] . ' ' . $result_text)) ?>">
                                            <td class="row-number"><?= $i++ ?></td>
                                            <td>
                                                <div style="font-weight:600;font-size:0.85rem;"><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></div>
                                                <div style="font-size:0.65rem;color:var(--text-secondary);"><?= htmlspecialchars($test['test_category'] ?? 'N/A') ?></div>
                                                <?php if (!empty($test['sample_type'])): ?>
                                                    <div style="font-size:0.6rem;color:var(--text-secondary);">
                                                        <i class="fas fa-vial"></i> <?= htmlspecialchars($test['sample_type']) ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($test['test_price']) && $test['test_price'] > 0): ?>
                                                    <div style="font-size:0.6rem;color:var(--success);font-weight:600;">TSh <?= number_format($test['test_price'], 0) ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($test['reference_range'])): ?>
                                                    <div style="font-size:0.6rem;color:var(--text-secondary);">
                                                        <i class="fas fa-chart-line"></i> Ref: <?= htmlspecialchars($test['reference_range']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($has_result): ?>
                                                    <div class="result-preview" title="<?= htmlspecialchars($result_text) ?>">
                                                        <i class="fas fa-file-medical-alt"></i>
                                                        <?= htmlspecialchars(strlen($result_text) > 50 ? substr($result_text, 0, 50) . '...' : $result_text) ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span style="font-size:0.7rem;color:var(--warning);">
                                                        <i class="fas fa-clock"></i> No result
                                                    </span>
                                                <?php endif; ?>
                                                <?php if (!empty($test['lab_technician_name'])): ?>
                                                    <div style="font-size:0.55rem;color:var(--success);margin-top:2px;">
                                                        <i class="fas fa-user-check"></i> By: <?= htmlspecialchars($test['lab_technician_name']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge-status badge-completed">
                                                    ✅ Completed
                                                </span>
                                            </td>
                                            <td>
                                                <span class="time-ago <?= $t_class ?>">
                                                    <i class="fas fa-check-circle"></i>
                                                    <?= $completed_time ?>
                                                </span>
                                                <?php if (!empty($test['completed_at'])): ?>
                                                    <div style="font-size:0.55rem;color:var(--text-secondary);margin-top:2px;">
                                                        <?= date('d/m/Y h:i A', strtotime($test['completed_at'])) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="action-buttons-vertical">
                                                    <a href="<?= $view_link ?>" class="btn-action btn-view-action" title="View Result">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                    <?php if ($has_result): ?>
                                                        <a href="<?= $print_link ?>" class="btn-action btn-print-action" title="Print Result" target="_blank">
                                                            <i class="fas fa-print"></i> Print
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <!-- NO SEARCH RESULTS -->
            <div id="noSearchResults" style="display:none;">
                <div class="empty-state no-results">
                    <i class="fas fa-search"></i>
                    <p>No patients match your search</p>
                    <p class="sub">Try a different keyword</p>
                </div>
            </div>
            
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-history"></i>
                <p>No completed results found</p>
                <p class="sub">Completed tests will appear here ✅</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Results History (Grouped by Patient)
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
        <p style="font-weight:600;font-size:0.8rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.7rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<script>
    // DARK MODE
    var darkModeToggle = document.getElementById('darkModeToggle');
    var darkIcon = document.getElementById('darkIcon');
    var darkText = document.getElementById('darkText');
    var htmlElement = document.documentElement;
    
    var savedDarkMode = localStorage.getItem('darkMode');
    if (savedDarkMode === 'true') {
        htmlElement.setAttribute('data-theme', 'dark');
        if (darkIcon) darkIcon.className = 'fas fa-sun';
        if (darkText) darkText.textContent = 'Light';
    }
    
    darkModeToggle?.addEventListener('click', function() {
        var isDark = htmlElement.getAttribute('data-theme') === 'dark';
        if (isDark) {
            htmlElement.removeAttribute('data-theme');
            if (darkIcon) darkIcon.className = 'fas fa-moon';
            if (darkText) darkText.textContent = 'Dark';
            localStorage.setItem('darkMode', 'false');
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            if (darkIcon) darkIcon.className = 'fas fa-sun';
            if (darkText) darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
        }
    });

    // SIDEBAR
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

    // TOGGLE PATIENT CARD
    function togglePatient(patientId) {
        var body = document.getElementById('body-' + patientId);
        var chevron = document.getElementById('chevron-' + patientId);
        
        if (body) {
            body.classList.toggle('open');
        }
        if (chevron) {
            chevron.classList.toggle('rotated');
        }
    }
    
    // Auto-open first patient card
    document.addEventListener('DOMContentLoaded', function() {
        var firstBody = document.querySelector('.patient-body');
        var firstChevron = document.querySelector('.chevron');
        if (firstBody) {
            setTimeout(function() {
                firstBody.classList.add('open');
                if (firstChevron) firstChevron.classList.add('rotated');
            }, 300);
        }
    });

    // LIVE SEARCH
    var tableSearch = document.getElementById('tableSearch');
    var searchClear = document.getElementById('searchClear');
    var searchResultsCount = document.getElementById('searchResultsCount');
    var patientCards = document.querySelectorAll('.patient-card');
    var noSearchResults = document.getElementById('noSearchResults');
    var headerCount = document.getElementById('headerCount');
    
    var totalPatients = patientCards.length;
    if (headerCount) headerCount.textContent = totalPatients;
    
    function performLiveSearch() {
        var query = tableSearch.value.toLowerCase().trim();
        
        if (query.length > 0) {
            searchClear.classList.add('visible');
        } else {
            searchClear.classList.remove('visible');
        }
        
        var visiblePatients = 0;
        
        patientCards.forEach(function(card) {
            var testRows = card.querySelectorAll('.test-row');
            var hasMatch = false;
            
            testRows.forEach(function(row) {
                var searchData = row.getAttribute('data-search') || '';
                if (query === '' || searchData.includes(query)) {
                    hasMatch = true;
                }
            });
            
            if (hasMatch) {
                card.style.display = '';
                visiblePatients++;
                
                if (query !== '') {
                    var body = card.querySelector('.patient-body');
                    var chevron = card.querySelector('.chevron');
                    if (body && !body.classList.contains('open')) {
                        body.classList.add('open');
                        if (chevron) chevron.classList.add('rotated');
                    }
                }
                
                var visibleRows = 0;
                testRows.forEach(function(row) {
                    var searchData = row.getAttribute('data-search') || '';
                    if (query === '' || searchData.includes(query)) {
                        row.style.display = '';
                        visibleRows++;
                        var rowNum = row.querySelector('.row-number');
                        if (rowNum) rowNum.textContent = visibleRows;
                    } else {
                        row.style.display = 'none';
                    }
                });
            } else {
                card.style.display = 'none';
            }
        });
        
        if (visiblePatients === 0 && query !== '') {
            noSearchResults.style.display = '';
        } else {
            noSearchResults.style.display = 'none';
        }
        
        if (headerCount) headerCount.textContent = visiblePatients;
        
        if (query !== '') {
            searchResultsCount.textContent = visiblePatients + ' patients found';
            searchResultsCount.style.display = 'inline-block';
        } else {
            searchResultsCount.style.display = 'none';
        }
    }
    
    if (tableSearch) {
        tableSearch.addEventListener('input', performLiveSearch);
        tableSearch.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                tableSearch.value = '';
                performLiveSearch();
                tableSearch.blur();
            }
        });
    }
    
    if (searchClear) {
        searchClear.addEventListener('click', function() {
            tableSearch.value = '';
            performLiveSearch();
            tableSearch.focus();
        });
    }
    
    if (tableSearch && tableSearch.value.trim() !== '') {
        performLiveSearch();
    }
    
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            if (tableSearch) {
                tableSearch.focus();
                tableSearch.select();
            }
        }
    });

    // DATE & TIME
    function updateDateTime() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        
        var footerTimestamp = document.getElementById('footerTimestamp');
        if (footerTimestamp) footerTimestamp.textContent = 'Last updated: ' + timeStr;
        
        var updateBadge = document.getElementById('updateBadge');
        if (updateBadge) {
            updateBadge.innerHTML = '<i class="fas fa-sync-alt fa-spin"></i> Live • ' + timeStr;
        }
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // TOAST
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

    console.log('%c🧪 Braick - Results History (BLUE THEME)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ BLUE THEME applied', 'font-size:13px; color:#0B5ED7; font-weight:bold;');
    console.log('%c✅ Grouped by Patient', 'font-size:13px; color:#0B5ED7;');
    console.log('%c✅ Live search filter', 'font-size:13px; color:#0B5ED7;');
    console.log('%c✅ Print button per patient + per test', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📊 Total: <?= $total_completed ?> | Today: <?= $completed_today ?> | Patients: <?= $total_patients ?>', 'font-size:13px; color:#64748B;');
</script>

</body>
</html>