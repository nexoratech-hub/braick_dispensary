<?php
// ================================================================
// FILE: frontend/pages/audit/lab_tests.php
// AUDIT - LAB TESTS GROUPED BY PATIENT → VISIT (V5 - VIEW ONLY)
// ✅ Same as admin/audit/lab_tests.php
// ✅ NO Edit button (view only)
// ✅ NO Delete button (view only)
// ✅ Only View button
// ✅ Blue theme + Table nav < >
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

// AUDIT ROLE ONLY
if ($_SESSION['role'] !== 'audit') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'admin': header('Location: /dispensary_system/frontend/pages/admin/dashboard.php'); break;
        case 'doctor': header('Location: /dispensary_system/frontend/pages/doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: /dispensary_system/frontend/pages/pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: /dispensary_system/frontend/pages/laboratory/dashboard.php'); break;
        case 'cashier': header('Location: /dispensary_system/frontend/pages/cashier/dashboard.php'); break;
        case 'reception': header('Location: /dispensary_system/frontend/pages/reception/dashboard.php'); break;
        default: header('Location: /dispensary_system/frontend/pages/login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Audit User';
$user_role = $_SESSION['role'] ?? 'audit';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// FILTERS
// ================================================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$selected_branch_id = isset($_GET['branch']) ? trim($_GET['branch']) : 'all';
$quick_filter = isset($_GET['quick']) ? $_GET['quick'] : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

$quick_date_from = '';
$quick_date_to = date('Y-m-d');

switch ($quick_filter) {
    case 'today':
        $quick_date_from = date('Y-m-d');
        $quick_date_to = date('Y-m-d');
        break;
    case '1w':
        $quick_date_from = date('Y-m-d', strtotime('-7 days'));
        break;
    case '1m':
        $quick_date_from = date('Y-m-d', strtotime('-1 month'));
        break;
    case '3m':
        $quick_date_from = date('Y-m-d', strtotime('-3 months'));
        break;
    case '6m':
        $quick_date_from = date('Y-m-d', strtotime('-6 months'));
        break;
    case '1y':
        $quick_date_from = date('Y-m-d', strtotime('-1 year'));
        break;
    case 'custom':
        $quick_date_from = $date_from;
        $quick_date_to = $date_to;
        break;
    case 'all':
    default:
        $quick_date_from = '';
        $quick_date_to = '';
        break;
}

// ================================================================
// BRANCHES LIST
// ================================================================
$branches_list = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$selected_branch_name = 'All Branches';
if ($selected_branch_id !== 'all') {
    foreach ($branches_list as $b) {
        if ($b['id'] == $selected_branch_id) { $selected_branch_name = $b['name']; break; }
    }
}

// ================================================================
// BUILD WHERE CLAUSE
// ================================================================
$where_clause = " WHERE 1=1";
$params = [];

if (!empty($search)) {
    $where_clause .= " AND (
        lt.test_name LIKE ? 
        OR pat.full_name LIKE ? 
        OR pat.patient_id LIKE ? 
        OR pat.phone LIKE ?
    )";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($status_filter)) {
    $where_clause .= " AND lt.status = ?";
    $params[] = $status_filter;
}

if ($selected_branch_id !== 'all') {
    $where_clause .= " AND lt.branch_id = ?";
    $params[] = (int)$selected_branch_id;
}

if (!empty($quick_date_from)) {
    $where_clause .= " AND DATE(lt.test_date) >= ?";
    $params[] = $quick_date_from;
}

if (!empty($quick_date_to)) {
    $where_clause .= " AND DATE(lt.test_date) <= ?";
    $params[] = $quick_date_to;
}

// ================================================================
// FETCH ALL LAB TESTS
// ================================================================
$sql = "
    SELECT 
        lt.*,
        pat.full_name as patient_name,
        pat.patient_id as patient_number,
        pat.phone as patient_phone,
        pat.gender as patient_gender,
        pat.date_of_birth,
        doc.full_name as doctor_name,
        tech.full_name as lab_technician_name,
        recv.full_name as received_by_name,
        b.name as branch_name,
        v.visit_number,
        v.visit_date,
        v.created_at as visit_created_at,
        v.status as visit_status
    FROM lab_tests lt
    LEFT JOIN patients pat ON lt.patient_id = pat.id
    LEFT JOIN users doc ON lt.doctor_id = doc.id
    LEFT JOIN users tech ON lt.lab_technician_id = tech.id
    LEFT JOIN users recv ON lt.performed_by = recv.id
    LEFT JOIN branches b ON lt.branch_id = b.id
    LEFT JOIN visits v ON lt.visit_id = v.id
    $where_clause
    ORDER BY lt.created_at DESC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$all_lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GROUP BY PATIENT → VISIT
// ================================================================
$patients_data = [];

foreach ($all_lab_tests as $test) {
    $patient_id = $test['patient_id'] ?? 0;
    $visit_id = $test['visit_id'] ?? 0;
    
    if (!isset($patients_data[$patient_id])) {
        $patients_data[$patient_id] = [
            'patient_id' => $patient_id,
            'patient_name' => $test['patient_name'] ?? 'Unknown Patient',
            'patient_number' => $test['patient_number'] ?? 'N/A',
            'patient_phone' => $test['patient_phone'] ?? '',
            'patient_gender' => $test['patient_gender'] ?? '',
            'date_of_birth' => $test['date_of_birth'] ?? '',
            'visits' => [],
            'total_amount' => 0,
            'total_tests' => 0,
            'latest_date' => null
        ];
    }
    
    if (!isset($patients_data[$patient_id]['visits'][$visit_id])) {
        $patients_data[$patient_id]['visits'][$visit_id] = [
            'visit_id' => $visit_id,
            'visit_number' => $test['visit_number'] ?? 'N/A',
            'visit_date' => $test['visit_date'] ?? $test['visit_created_at'] ?? $test['created_at'],
            'doctor_name' => $test['doctor_name'] ?? 'N/A',
            'visit_status' => $test['visit_status'] ?? 'N/A',
            'tests' => [],
            'total_amount' => 0,
            'statuses' => []
        ];
    }
    
    $patients_data[$patient_id]['visits'][$visit_id]['tests'][] = $test;
    $patients_data[$patient_id]['visits'][$visit_id]['total_amount'] += $test['test_price'] ?? 0;
    $patients_data[$patient_id]['visits'][$visit_id]['statuses'][] = $test['status'];
    
    $patients_data[$patient_id]['total_amount'] += $test['test_price'] ?? 0;
    $patients_data[$patient_id]['total_tests']++;
    if (!$patients_data[$patient_id]['latest_date'] || $test['created_at'] > $patients_data[$patient_id]['latest_date']) {
        $patients_data[$patient_id]['latest_date'] = $test['created_at'];
    }
}

foreach ($patients_data as &$patient) {
    foreach ($patient['visits'] as &$visit) {
        $statuses = $visit['statuses'];
        if (in_array('pending', $statuses)) $visit['overall_status'] = 'pending';
        elseif (in_array('in_progress', $statuses)) $visit['overall_status'] = 'in_progress';
        elseif (in_array('completed', $statuses)) $visit['overall_status'] = 'completed';
        elseif (in_array('cancelled', $statuses)) $visit['overall_status'] = 'cancelled';
        else $visit['overall_status'] = 'pending';
    }
    unset($visit);
}
unset($patient);

$patients_data = array_filter($patients_data, function($p) {
    return !empty($p['visits']) && $p['total_tests'] > 0;
});

$patients_array = array_values($patients_data);
foreach ($patients_array as &$p) {
    $p['visit_count'] = count($p['visits']);
    $p['visits'] = array_values($p['visits']);
}
unset($p);

// ================================================================
// STATS
// ================================================================
$stats_where = " WHERE 1=1";
$stats_params = [];
if ($selected_branch_id !== 'all') {
    $stats_where .= " AND lt.branch_id = ?";
    $stats_params[] = (int)$selected_branch_id;
}
if (!empty($quick_date_from)) {
    $stats_where .= " AND DATE(lt.test_date) >= ?";
    $stats_params[] = $quick_date_from;
}
if (!empty($quick_date_to)) {
    $stats_where .= " AND DATE(lt.test_date) <= ?";
    $stats_params[] = $quick_date_to;
}

$stmt = $db->prepare("SELECT COUNT(*) as total FROM lab_tests lt $stats_where");
$stmt->execute($stats_params);
$total_all = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as total FROM lab_tests lt $stats_where AND lt.status = 'pending'");
$stmt->execute($stats_params);
$pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as total FROM lab_tests lt $stats_where AND lt.status = 'in_progress'");
$stmt->execute($stats_params);
$in_progress_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as total FROM lab_tests lt $stats_where AND lt.status = 'completed'");
$stmt->execute($stats_params);
$completed_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as total FROM lab_tests lt $stats_where AND lt.status = 'cancelled'");
$stmt->execute($stats_params);
$cancelled_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt = $db->prepare("SELECT COALESCE(SUM(lt.test_price), 0) as total FROM lab_tests lt $stats_where");
$stmt->execute($stats_params);
$total_amount_all = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt = $db->prepare("SELECT COALESCE(SUM(lt.test_price), 0) as total FROM lab_tests lt $stats_where AND lt.status = 'completed'");
$stmt->execute($stats_params);
$completed_amount = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt = $db->prepare("SELECT COALESCE(SUM(lt.test_price), 0) as total FROM lab_tests lt $stats_where AND lt.status = 'pending'");
$stmt->execute($stats_params);
$pending_amount = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$total_patients_count = count($patients_array);

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    return (new DateTime($dob))->diff(new DateTime('today'))->y;
}

function buildFilterUrl($params_to_update = []) {
    $current = $_GET;
    foreach ($params_to_update as $key => $value) {
        if ($value === null || $value === '') unset($current[$key]);
        else $current[$key] = $value;
    }
    return '?' . http_build_query($current);
}

function getLabStatusBadge($status) {
    $map = [
        'pending' => ['class' => 'warning', 'icon' => '⏳', 'label' => 'Pending'],
        'in_progress' => ['class' => 'info', 'icon' => '🔄', 'label' => 'In Progress'],
        'completed' => ['class' => 'success', 'icon' => '✅', 'label' => 'Completed'],
        'cancelled' => ['class' => 'danger', 'icon' => '❌', 'label' => 'Cancelled']
    ];
    return $map[$status] ?? ['class' => 'warning', 'icon' => '❓', 'label' => ucfirst($status)];
}

include_once __DIR__ . '/../../components/audit_header.php';
include_once __DIR__ . '/../../components/audit_sidebar.php';
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
:root {
    --font-mono: 'JetBrains Mono', 'Courier New', monospace;
    --font-main: 'Inter', -apple-system, sans-serif;
    --primary: #0B5ED7;
    --primary-dark: #0A4CA8;
    --primary-darker: #083C8A;
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
    --cyan: #0891B2;
    --cyan-bg: #CFFAFE;
    --gray-50: #F8FAFC;
    --bg-body: #F1F5F9;
    --bg-card: #FFFFFF;
    --text-primary: #1E293B;
    --text-secondary: #64748B;
    --border-color: #E2E8F0;
    --radius: 10px;
    --radius-lg: 14px;
    --shadow: 0 1px 3px rgba(0,0,0,0.06);
    --shadow-md: 0 4px 16px rgba(0,0,0,0.08);
}

[data-theme="dark"] {
    --bg-body: #0F172A;
    --bg-card: #1E293B;
    --text-primary: #F1F5F9;
    --text-secondary: #94A3B8;
    --border-color: #334155;
}

body { font-family: var(--font-main) !important; }

.stat-number, .stat-amount, .stat-value, .stat-pill,
.patient-avatar, .patient-meta span, .amount-cell,
.data-table td:first-child, .visit-number-display,
.visit-date-display, .med-qty, .quick-filter-btn,
.filter-btn, input[type="date"], input[type="text"],
.med-search-panel .search-box input,
.prescription-tag, .lab-value, .lab-tech-name {
    font-family: var(--font-mono) !important;
    font-variant-numeric: tabular-nums;
}

.data-table thead th {
    font-family: var(--font-mono) !important;
    letter-spacing: 0.02em;
}

/* PAGE HEADER - BLUE THEME */
.page-header-custom {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8, #083C8A);
    border-radius: 16px;
    padding: 20px 28px;
    margin-bottom: 20px;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    box-shadow: 0 4px 20px rgba(11, 94, 215, 0.3);
    position: relative;
    overflow: hidden;
}

.page-header-custom::before {
    content: '';
    position: absolute;
    top: -50%; right: -20%;
    width: 300px; height: 300px;
    background: rgba(255,255,255,0.05);
    border-radius: 50%;
    pointer-events: none;
}

.page-header-custom .page-title {
    color: white;
    font-size: 1.4rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
    margin: 0;
}

.page-header-custom .page-title i { font-size: 1.6rem; opacity: 0.9; }
.page-header-custom .page-subtitle {
    color: rgba(255,255,255,0.85);
    font-size: 0.82rem;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
    margin-top: 4px;
}

.page-header-custom .role-badge-display {
    background: rgba(255,255,255,0.2);
    color: white;
    padding: 3px 12px;
    border-radius: 20px;
    font-size: 0.6rem;
    font-weight: 600;
    text-transform: uppercase;
}

.page-header-custom .branch-tag {
    background: rgba(255,255,255,0.15);
    color: white;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 0.65rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.page-header-custom .btn-outline-light {
    background: rgba(255,255,255,0.12);
    color: white;
    border: 1px solid rgba(255,255,255,0.2);
    padding: 6px 14px;
    border-radius: 8px;
    font-weight: 500;
    font-size: 0.75rem;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    position: relative;
    z-index: 1;
}

.page-header-custom .btn-outline-light:hover {
    background: rgba(255,255,255,0.25);
    transform: translateY(-2px);
    color: white;
}

/* STATS - BLUE THEME */
.stats-grid-5 {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 12px;
    margin-bottom: 20px;
}

.stat-card-custom {
    border-radius: 12px;
    padding: 14px 16px;
    display: flex;
    flex-direction: column;
    transition: all 0.4s ease;
    box-shadow: 0 4px 16px rgba(0,0,0,0.12);
    color: white;
    position: relative;
    overflow: hidden;
    min-height: 95px;
    text-decoration: none;
}

.stat-card-custom::before {
    content: '';
    position: absolute;
    top: -50%; right: -20%;
    width: 140px; height: 140px;
    background: rgba(255,255,255,0.06);
    border-radius: 50%;
}

.stat-card-custom:hover { transform: translateY(-4px) scale(1.01); box-shadow: 0 10px 32px rgba(0,0,0,0.2); }

.stat-card-custom .stat-icon {
    width: 38px; height: 38px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    background: rgba(255,255,255,0.18);
    color: white;
    border: 1px solid rgba(255,255,255,0.12);
    margin-bottom: 4px;
}

.stat-card-custom .stat-label { font-size: 0.55rem; color: rgba(255,255,255,0.85); font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; margin: 0 0 1px 0; }
.stat-card-custom .stat-number { font-size: 1.7rem; font-weight: 800; color: white; margin: 0; line-height: 1.1; }
.stat-card-custom .stat-amount { font-size: 0.75rem; font-weight: 600; color: rgba(255,255,255,0.9); margin-top: 2px; }

.card-blue-1 { background: linear-gradient(135deg, #3B82F6, #0B5ED7, #0A4CA8); }
.card-blue-2 { background: linear-gradient(135deg, #0EA5E9, #0284C7, #075985); }
.card-blue-3 { background: linear-gradient(135deg, #0B5ED7, #083C8A, #062E6B); }
.card-blue-4 { background: linear-gradient(135deg, #4F46E5, #4338CA, #3730A3); }
.card-blue-5 { background: linear-gradient(135deg, #DC2626, #B91C1C, #991B1B); }

/* FILTER SECTION */
.filter-section {
    background: var(--bg-card);
    border-radius: 12px;
    padding: 12px 16px;
    border: 1px solid var(--border-color);
    margin-bottom: 14px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
}

.filter-section .filter-label {
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--text-secondary);
    margin-right: 4px;
}

.filter-btn {
    padding: 3px 12px;
    border-radius: 18px;
    font-size: 0.68rem;
    font-weight: 500;
    border: 2px solid var(--border-color);
    background: transparent;
    color: var(--text-secondary);
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
}

.filter-btn:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-bg); }
.filter-btn.active { background: var(--primary); color: white; border-color: var(--primary); }
.filter-btn i { margin-right: 4px; font-size: 0.6rem; }

/* QUICK FILTERS */
.quick-filters {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    align-items: center;
    background: var(--bg-card);
    border-radius: 12px;
    padding: 10px 16px;
    border: 1px solid var(--border-color);
    margin-bottom: 14px;
}

.quick-filters .filter-label {
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--text-secondary);
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-right: 4px;
}

.quick-filter-btn {
    padding: 5px 12px;
    border-radius: 18px;
    font-size: 0.68rem;
    font-weight: 700;
    border: 2px solid var(--border-color);
    background: var(--bg-card);
    color: var(--text-secondary);
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    white-space: nowrap;
}

.quick-filter-btn:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-bg); transform: translateY(-1px); }
.quick-filter-btn.active { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); border-color: var(--primary); color: white; }
.quick-filter-btn.today.active { background: linear-gradient(135deg, #059669, #047857); border-color: #059669; }
.quick-filter-btn.custom.active { background: linear-gradient(135deg, #D97706, #B45309); border-color: #D97706; }

/* CUSTOM DATE */
.custom-date-row {
    display: none;
    padding: 12px 16px;
    background: var(--bg-card);
    border-radius: 10px;
    margin-bottom: 14px;
    gap: 10px;
    flex-wrap: wrap;
    align-items: end;
    border: 2px solid var(--primary);
}

.custom-date-row.show { display: flex; }
.custom-date-row .form-group { flex: 1; min-width: 140px; }
.custom-date-row .form-group label { font-size: 0.7rem; font-weight: 600; color: var(--text-secondary); display: block; margin-bottom: 4px; }
.custom-date-row .form-control {
    width: 100%;
    padding: 8px 12px;
    border: 2px solid var(--border-color);
    border-radius: 8px;
    font-size: 0.8rem;
    background: var(--bg-body);
    color: var(--text-primary);
    outline: none;
    font-family: var(--font-mono);
}
.btn-apply-filters {
    padding: 8px 20px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 0.8rem;
    border: none;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    cursor: pointer;
    height: 40px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

/* SEARCH PANEL - BLUE */
.med-search-panel {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    border-radius: 12px;
    padding: 12px 16px;
    margin-bottom: 14px;
    box-shadow: 0 3px 12px rgba(11, 94, 215, 0.2);
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.med-search-panel .search-label {
    color: white;
    font-size: 0.72rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
}

.med-search-panel .search-label i {
    background: rgba(255,255,255,0.2);
    width: 26px; height: 26px;
    border-radius: 7px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
}

.med-search-panel .search-box {
    position: relative;
    display: flex;
    align-items: center;
    background: rgba(255,255,255,0.18);
    border: 2px solid rgba(255,255,255,0.3);
    border-radius: 8px;
    padding: 0 12px;
    height: 38px;
    flex: 1;
    min-width: 200px;
}

.med-search-panel .search-box:focus-within {
    background: rgba(255,255,255,0.28);
    border-color: rgba(255,255,255,0.6);
    box-shadow: 0 0 0 3px rgba(255,255,255,0.1);
}

.med-search-panel .search-box .search-icon { color: rgba(255,255,255,0.9); font-size: 0.85rem; margin-right: 8px; }

.med-search-panel .search-box input {
    flex: 1;
    background: transparent;
    border: none;
    outline: none;
    color: white;
    font-size: 0.82rem;
    font-weight: 500;
    font-family: var(--font-mono);
}

.med-search-panel .search-box input::placeholder { color: rgba(255,255,255,0.65); font-size: 0.78rem; }

.med-search-panel .search-box .search-clear-med {
    background: rgba(255,255,255,0.25);
    border: none;
    color: white;
    width: 20px; height: 20px;
    border-radius: 50%;
    cursor: pointer;
    display: none;
    align-items: center;
    justify-content: center;
    font-size: 0.6rem;
    margin-left: 6px;
}

.med-search-panel .search-box .search-clear-med.visible { display: flex; }

.med-search-panel .search-count {
    color: rgba(255,255,255,0.9);
    font-size: 0.68rem;
    font-weight: 700;
    background: rgba(255,255,255,0.2);
    padding: 3px 10px;
    border-radius: 12px;
    display: none;
}

.med-search-panel .search-count.show { display: inline-block; }

/* PATIENT CARD */
.patient-card {
    background: var(--bg-card);
    border-radius: 14px;
    border: 2px solid var(--border-color);
    margin-bottom: 16px;
    overflow: hidden;
    box-shadow: var(--shadow);
}

.patient-card:hover { border-color: var(--primary); }
.patient-card.filtered-out { display: none; }

.patient-header {
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    color: white;
    padding: 12px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    cursor: pointer;
}

.patient-header:hover { background: linear-gradient(135deg, #0A4CA8, #083C8A); }
.patient-header .patient-info { display: flex; align-items: center; gap: 12px; flex: 1; min-width: 250px; }

.patient-header .patient-avatar {
    width: 44px; height: 44px;
    border-radius: 50%;
    background: rgba(255,255,255,0.25);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.2rem;
    color: white;
    border: 2px solid rgba(255,255,255,0.4);
}

.patient-header .patient-name { font-weight: 700; font-size: 1rem; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.patient-header .patient-meta { display: flex; gap: 12px; font-size: 0.72rem; opacity: 0.9; flex-wrap: wrap; margin-top: 2px; }
.patient-header .patient-meta span { display: flex; align-items: center; gap: 4px; }
.patient-header .patient-stats { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }

.patient-header .patient-stats .stat-pill {
    background: rgba(255,255,255,0.2);
    padding: 4px 12px;
    border-radius: 18px;
    font-size: 0.68rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    border: 1px solid rgba(255,255,255,0.15);
}

.patient-header .chevron { font-size: 0.85rem; transition: transform 0.3s ease; }
.patient-header .chevron.rotated { transform: rotate(180deg); }

.patient-body {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.4s ease, padding 0.3s ease;
}

.patient-body.open { max-height: 20000px; padding: 14px 20px 18px; }

/* PATIENT ACTIONS */
.patient-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding-bottom: 12px;
    border-bottom: 2px dashed var(--border-color);
    margin-bottom: 16px;
    flex-wrap: wrap;
}

.patient-actions .patient-actions-info {
    font-size: 0.75rem;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.patient-actions .patient-actions-info strong {
    font-weight: 800;
    color: var(--primary);
}

.patient-actions-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.btn-patient-view {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 18px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 0.75rem;
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    color: white;
    text-decoration: none;
    border: none;
    cursor: pointer;
    box-shadow: 0 3px 10px rgba(11, 94, 215, 0.3);
}

.btn-patient-view:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(11, 94, 215, 0.5); color: white; }

/* VISIT SECTION */
.visit-section {
    border: 2px solid var(--border-color);
    border-radius: 12px;
    margin-bottom: 16px;
    overflow: hidden;
    transition: all 0.3s ease;
}

.visit-section:last-child { margin-bottom: 0; }
.visit-section:hover { border-color: var(--primary); box-shadow: 0 4px 12px rgba(11, 94, 215, 0.1); }

.visit-section-header {
    background: linear-gradient(135deg, #E8F0FE, #D6E4FF);
    padding: 10px 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    border-bottom: 2px solid var(--primary-light);
}

[data-theme="dark"] .visit-section-header { background: linear-gradient(135deg, #1E3A5F, #16294A); }

.visit-section-header .visit-info-left {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    flex: 1;
}

.visit-section-header .visit-icon-badge {
    width: 36px; height: 36px;
    border-radius: 9px;
    background: linear-gradient(135deg, #FCD34D, #F59E0B);
    color: #78350F;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    box-shadow: 0 3px 10px rgba(245, 158, 11, 0.3);
    flex-shrink: 0;
}

.visit-section-header .visit-number-display {
    font-weight: 800;
    font-size: 0.85rem;
    color: #78350F;
    background: rgba(255,255,255,0.6);
    padding: 3px 10px;
    border-radius: 7px;
    border: 1px solid rgba(245, 158, 11, 0.3);
}

[data-theme="dark"] .visit-section-header .visit-number-display {
    background: rgba(255,255,255,0.1);
    color: #FCD34D;
}

.visit-section-header .visit-date-display {
    font-size: 0.72rem;
    color: var(--text-secondary);
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-weight: 600;
}

.visit-section-header .visit-doctor-display {
    font-size: 0.72rem;
    color: var(--primary);
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: rgba(255,255,255,0.6);
    padding: 3px 10px;
    border-radius: 20px;
    border: 1px solid var(--primary-light);
}

[data-theme="dark"] .visit-section-header .visit-doctor-display {
    background: rgba(255,255,255,0.1);
    color: #93C5FD;
}

.visit-section-header .visit-stats-right {
    display: flex;
    gap: 6px;
    align-items: center;
    flex-wrap: wrap;
}

.visit-section-header .visit-mini-stat {
    background: rgba(255,255,255,0.7);
    padding: 3px 10px;
    border-radius: 16px;
    font-size: 0.65rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: var(--text-primary);
}

[data-theme="dark"] .visit-section-header .visit-mini-stat {
    background: rgba(255,255,255,0.1);
    color: var(--text-primary);
}

.visit-section-header .visit-mini-stat .stat-value {
    font-weight: 800;
    color: var(--primary);
}

/* TABLE NAV < > */
.table-nav-group {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    border: 1px solid rgba(255,255,255,0.3);
    border-radius: 8px;
    padding: 2px;
    box-shadow: 0 2px 8px rgba(11, 94, 215, 0.25);
}

.table-nav-btn {
    background: rgba(255,255,255,0.15);
    border: none;
    color: white;
    width: 28px;
    height: 28px;
    border-radius: 6px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.72rem;
    font-weight: 700;
    transition: all 0.2s ease;
    padding: 0;
}

.table-nav-btn:hover:not(:disabled) {
    background: rgba(255,255,255,0.35);
    transform: scale(1.1);
}

.table-nav-btn:active:not(:disabled) {
    transform: scale(0.92);
}

.table-nav-btn:disabled {
    opacity: 0.35;
    cursor: not-allowed;
}

.table-nav-indicator {
    font-size: 0.6rem;
    font-weight: 800;
    font-family: var(--font-mono);
    color: white;
    padding: 0 6px;
    min-width: 38px;
    text-align: center;
    white-space: nowrap;
    background: rgba(255,255,255,0.15);
    border-radius: 5px;
    height: 24px;
    line-height: 24px;
    letter-spacing: 0.02em;
}

/* TABLE SCROLL WRAPPER */
.table-scroll-wrapper {
    position: relative;
}

.table-scroll-wrapper::before,
.table-scroll-wrapper::after {
    content: '';
    position: absolute;
    top: 0;
    bottom: 0;
    width: 24px;
    pointer-events: none;
    z-index: 2;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.table-scroll-wrapper::before {
    left: 0;
    background: linear-gradient(to right, rgba(255,255,255,0.95), transparent);
}

.table-scroll-wrapper::after {
    right: 0;
    background: linear-gradient(to left, rgba(255,255,255,0.95), transparent);
}

[data-theme="dark"] .table-scroll-wrapper::before {
    background: linear-gradient(to right, rgba(30,41,59,0.95), transparent);
}

[data-theme="dark"] .table-scroll-wrapper::after {
    background: linear-gradient(to left, rgba(30,41,59,0.95), transparent);
}

.table-scroll-wrapper.can-scroll-left::before { opacity: 1; }
.table-scroll-wrapper.can-scroll-right::after { opacity: 1; }

.visit-section-body {
    padding: 12px 16px 14px;
    background: var(--bg-card);
}

/* TABLE */
.table-scroll {
    overflow-x: auto;
    scroll-behavior: smooth;
    border-radius: 10px;
}

.table-scroll::-webkit-scrollbar { height: 8px; }
.table-scroll::-webkit-scrollbar-track { background: var(--bg-body); border-radius: 10px; }
.table-scroll::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }

.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.78rem;
    min-width: 1100px;
}

.data-table thead th {
    text-align: left;
    padding: 8px 12px;
    font-weight: 700;
    font-size: 0.6rem;
    text-transform: uppercase;
    color: white;
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    border-bottom: 3px solid #083C8A;
    white-space: nowrap;
}

.data-table tbody td {
    padding: 8px 12px;
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
.status-badge {
    padding: 3px 10px;
    border-radius: 18px;
    font-size: 0.6rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 3px;
    white-space: nowrap;
}

.status-badge.warning { background: #FEF3C7; color: #D97706; }
.status-badge.success { background: #D1FAE5; color: #059669; }
.status-badge.danger { background: #FEE2E2; color: #EF4444; }
.status-badge.info { background: #E8F0FE; color: #0B5ED7; }
.status-badge.purple { background: #EDE9FE; color: #7C3AED; }
.status-badge.cyan { background: #CFFAFE; color: #0891B2; }

[data-theme="dark"] .status-badge.warning { background: #3A2A1A; color: #FBBF24; }
[data-theme="dark"] .status-badge.success { background: #1A3A2A; color: #34D399; }
[data-theme="dark"] .status-badge.danger { background: #3A1A1A; color: #F87171; }
[data-theme="dark"] .status-badge.info { background: #1E3A5F; color: #6EA8FE; }
[data-theme="dark"] .status-badge.purple { background: #2D1B4E; color: #C4B5FD; }

.amount-cell { 
    font-weight: 700; 
    color: var(--primary); 
    font-size: 0.8rem; 
}

.lab-value {
    font-family: var(--font-mono);
    font-weight: 700;
    color: var(--success);
    font-size: 0.85rem;
}

.lab-value.pending-value {
    color: var(--text-secondary);
    font-style: italic;
    font-weight: 500;
}

.lab-tech-name {
    font-size: 0.72rem;
    color: var(--text-primary);
    font-weight: 600;
}

.lab-tech-name.not-assigned {
    color: var(--text-secondary);
    font-style: italic;
    font-weight: 400;
}

/* ✅ VIEW ONLY BUTTON (Audit role) */
.btn-action-sm {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 3px;
    padding: 4px 8px;
    border-radius: 5px;
    font-weight: 700;
    font-size: 0.6rem;
    cursor: pointer;
    border: none;
    text-decoration: none;
}

.btn-action-sm.view { background: var(--primary); color: white; }
.btn-action-sm.view:hover { background: var(--primary-dark); }

.action-buttons-group {
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
}

/* EMPTY STATE */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-secondary);
    background: var(--bg-card);
    border-radius: 14px;
    border: 2px dashed var(--border-color);
}

.empty-state i { font-size: 3.5rem; color: var(--primary); display: block; margin-bottom: 12px; }
.empty-state p { font-size: 1rem; font-weight: 600; color: var(--text-primary); }
.empty-state .sub { font-size: 0.85rem; color: var(--text-secondary); margin-top: 4px; font-weight: 400; }

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
@media (max-width: 1200px) { .stats-grid-5 { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 1024px) { .stats-grid-5 { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 768px) {
    .page-header-custom { padding: 14px 16px; }
    .page-header-custom .page-title { font-size: 1.15rem; }
    .stats-grid-5 { grid-template-columns: 1fr 1fr; gap: 8px; }
    .stat-card-custom { min-height: 80px; padding: 10px 12px; }
    .stat-card-custom .stat-number { font-size: 1.3rem; }
    .med-search-panel { flex-direction: column; align-items: stretch; }
    .patient-header { flex-direction: column; align-items: stretch; }
    .patient-actions { flex-direction: column; align-items: stretch; }
    .patient-actions-buttons { width: 100%; }
    .btn-patient-view { flex: 1; justify-content: center; }
    .visit-section-header { flex-direction: column; align-items: stretch; }
    .visit-stats-right { justify-content: flex-start; }
}
</style>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header-custom">
        <div>
            <h1 class="page-title">
                <i class="fas fa-flask"></i>
                Lab Tests
                <span class="role-badge-display">AUDIT • VIEW ONLY</span>
                <?php if ($selected_branch_id !== 'all'): ?>
                    <span class="branch-tag">
                        <i class="fas fa-store-alt"></i> <?= htmlspecialchars($selected_branch_name) ?>
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <span class="branch-tag"><i class="fas fa-users"></i> <?= $total_patients_count ?> Patients</span>
                <span class="branch-tag"><i class="fas fa-flask"></i> <?= $total_all ?> Tests</span>
                <span class="branch-tag" style="background:rgba(52,211,153,0.25);color:#A7F3D0;">
                    <i class="fas fa-money-bill-wave"></i> TSh <?= number_format($total_amount_all, 0) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="dashboard.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <button onclick="window.location.reload()" class="btn-outline-light">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- STATS - BLUE THEME -->
    <div class="stats-grid-5">
        <a href="lab_tests.php?branch=<?= $selected_branch_id ?>" class="stat-card-custom card-blue-1">
            <div class="stat-icon"><i class="fas fa-flask"></i></div>
            <div class="stat-content">
                <p class="stat-label">Total Tests</p>
                <p class="stat-number"><?= $total_all ?></p>
                <p class="stat-amount">TSh <?= number_format($total_amount_all, 0) ?></p>
            </div>
        </a>
        <a href="lab_tests.php?branch=<?= $selected_branch_id ?>&status=pending" class="stat-card-custom card-blue-2">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-content">
                <p class="stat-label">Pending</p>
                <p class="stat-number"><?= $pending_count ?></p>
                <p class="stat-amount">TSh <?= number_format($pending_amount, 0) ?></p>
            </div>
        </a>
        <a href="lab_tests.php?branch=<?= $selected_branch_id ?>&status=in_progress" class="stat-card-custom card-blue-3">
            <div class="stat-icon"><i class="fas fa-spinner"></i></div>
            <div class="stat-content">
                <p class="stat-label">In Progress</p>
                <p class="stat-number"><?= $in_progress_count ?></p>
                <p class="stat-amount">In Progress</p>
            </div>
        </a>
        <a href="lab_tests.php?branch=<?= $selected_branch_id ?>&status=completed" class="stat-card-custom card-blue-4">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-content">
                <p class="stat-label">Completed</p>
                <p class="stat-number"><?= $completed_count ?></p>
                <p class="stat-amount">TSh <?= number_format($completed_amount, 0) ?></p>
            </div>
        </a>
        <a href="lab_tests.php?branch=<?= $selected_branch_id ?>&status=cancelled" class="stat-card-custom card-blue-5">
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            <div class="stat-content">
                <p class="stat-label">Cancelled</p>
                <p class="stat-number"><?= $cancelled_count ?></p>
                <p class="stat-amount">Cancelled</p>
            </div>
        </a>
    </div>

    <!-- QUICK DATE FILTERS -->
    <div class="quick-filters">
        <span class="filter-label"><i class="fas fa-bolt"></i> Quick:</span>
        
        <a href="<?= buildFilterUrl(['quick' => 'all', 'date_from' => null, 'date_to' => null]) ?>" 
           class="quick-filter-btn <?= $quick_filter === 'all' ? 'active' : '' ?>">
            <i class="fas fa-infinity"></i> All
        </a>
        <a href="<?= buildFilterUrl(['quick' => 'today', 'date_from' => null, 'date_to' => null]) ?>" 
           class="quick-filter-btn today <?= $quick_filter === 'today' ? 'active' : '' ?>">
            <i class="fas fa-calendar-day"></i> Today
        </a>
        <a href="<?= buildFilterUrl(['quick' => '1w', 'date_from' => null, 'date_to' => null]) ?>" 
           class="quick-filter-btn <?= $quick_filter === '1w' ? 'active' : '' ?>">
            <i class="fas fa-calendar-week"></i> 1 Week
        </a>
        <a href="<?= buildFilterUrl(['quick' => '1m', 'date_from' => null, 'date_to' => null]) ?>" 
           class="quick-filter-btn <?= $quick_filter === '1m' ? 'active' : '' ?>">
            <i class="fas fa-calendar-alt"></i> 1 Month
        </a>
        <a href="<?= buildFilterUrl(['quick' => '3m', 'date_from' => null, 'date_to' => null]) ?>" 
           class="quick-filter-btn <?= $quick_filter === '3m' ? 'active' : '' ?>">
            <i class="fas fa-calendar-alt"></i> 3 Months
        </a>
        <a href="<?= buildFilterUrl(['quick' => '1y', 'date_from' => null, 'date_to' => null]) ?>" 
           class="quick-filter-btn <?= $quick_filter === '1y' ? 'active' : '' ?>">
            <i class="fas fa-calendar"></i> 1 Year
        </a>
        <a href="javascript:void(0)" onclick="toggleCustomDate()" 
           class="quick-filter-btn custom <?= $quick_filter === 'custom' ? 'active' : '' ?>">
            <i class="fas fa-calendar-check"></i> Custom
        </a>
        
        <?php if ($quick_filter !== 'all' || $status_filter || $search): ?>
            <a href="lab_tests.php?branch=<?= $selected_branch_id ?>" 
               class="quick-filter-btn" 
               style="border-color:var(--danger);color:var(--danger);margin-left:auto;">
                <i class="fas fa-times"></i> Clear All
            </a>
        <?php endif; ?>
    </div>

    <!-- CUSTOM DATE FILTER -->
    <div class="custom-date-row <?= $quick_filter === 'custom' ? 'show' : '' ?>" id="customDateRow">
        <form method="GET" style="display:contents;">
            <input type="hidden" name="quick" value="custom">
            <input type="hidden" name="branch" value="<?= htmlspecialchars($selected_branch_id) ?>">
            <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
            <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
            
            <div class="form-group">
                <label><i class="fas fa-calendar-alt"></i> Date From</label>
                <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>" required>
            </div>
            <div class="form-group">
                <label><i class="fas fa-calendar-alt"></i> Date To</label>
                <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>" required>
            </div>
            <button type="submit" class="btn-apply-filters">
                <i class="fas fa-check"></i> Apply
            </button>
        </form>
    </div>

    <!-- STATUS FILTERS -->
    <div class="filter-section">
        <span class="filter-label"><i class="fas fa-filter"></i> Status:</span>
        <a href="<?= buildFilterUrl(['status' => null]) ?>" class="filter-btn <?= empty($status_filter) ? 'active' : '' ?>">
            <i class="fas fa-globe"></i> All
        </a>
        <a href="<?= buildFilterUrl(['status' => 'pending']) ?>" class="filter-btn <?= $status_filter === 'pending' ? 'active' : '' ?>">
            <i class="fas fa-clock"></i> Pending
        </a>
        <a href="<?= buildFilterUrl(['status' => 'in_progress']) ?>" class="filter-btn <?= $status_filter === 'in_progress' ? 'active' : '' ?>">
            <i class="fas fa-spinner"></i> In Progress
        </a>
        <a href="<?= buildFilterUrl(['status' => 'completed']) ?>" class="filter-btn <?= $status_filter === 'completed' ? 'active' : '' ?>">
            <i class="fas fa-check-circle"></i> Completed
        </a>
        <a href="<?= buildFilterUrl(['status' => 'cancelled']) ?>" class="filter-btn <?= $status_filter === 'cancelled' ? 'active' : '' ?>">
            <i class="fas fa-times-circle"></i> Cancelled
        </a>
    </div>

    <!-- SEARCH PANEL -->
    <div class="med-search-panel">
        <div class="search-label">
            <i class="fas fa-search"></i>
            <span>Search</span>
        </div>
        <div class="search-box">
            <i class="fas fa-search search-icon"></i>
            <input type="text" 
                   id="labSearchInput" 
                   placeholder="Search patient name, ID, test name, phone..."
                   autocomplete="off">
            <button type="button" class="search-clear-med" id="labSearchClear">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <span class="search-count" id="labSearchCount"></span>
    </div>

    <!-- PATIENTS LIST -->
    <div id="patientsContainer">
        <?php if (count($patients_array) > 0): ?>
            <?php foreach ($patients_array as $patient): 
                $patient_id = $patient['patient_id'];
                $test_count = $patient['total_tests'];
                $visit_count = $patient['visit_count'];
                $age = calculateAge($patient['date_of_birth']);
            ?>
                <div class="patient-card" 
                     data-patient-id="<?= $patient_id ?>"
                     data-patient-search="<?= htmlspecialchars(strtolower($patient['patient_name'] . ' ' . $patient['patient_number'] . ' ' . $patient['patient_phone'])) ?>">
                    
                    <div class="patient-header" onclick="togglePatient(<?= $patient_id ?>)">
                        <div class="patient-info">
                            <div class="patient-avatar" style="background: <?= '#' . substr(md5($patient['patient_name']), 0, 6) ?>;">
                                <?= strtoupper(substr($patient['patient_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <div class="patient-name">
                                    <span class="patient-name-text"><?= htmlspecialchars($patient['patient_name']) ?></span>
                                    <span style="background:rgba(255,255,255,0.25);padding:1px 8px;border-radius:10px;font-size:0.58rem;font-weight:600;">
                                        <i class="fas fa-calendar-check"></i> <?= $visit_count ?> visit(s)
                                    </span>
                                    <span style="background:rgba(255,255,255,0.25);padding:1px 8px;border-radius:10px;font-size:0.58rem;font-weight:600;">
                                        <i class="fas fa-flask"></i> <?= $test_count ?> test(s)
                                    </span>
                                </div>
                                <div class="patient-meta">
                                    <span><i class="fas fa-id-card"></i> <?= htmlspecialchars($patient['patient_number']) ?></span>
                                    <?php if (!empty($patient['patient_phone'])): ?>
                                        <span><i class="fas fa-phone"></i> <?= htmlspecialchars($patient['patient_phone']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($patient['patient_gender'])): ?>
                                        <span><i class="fas fa-<?= $patient['patient_gender'] === 'Female' ? 'venus' : 'mars' ?>"></i> <?= htmlspecialchars($patient['patient_gender']) ?> • <?= $age ?> yrs</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="patient-stats">
                            <span class="stat-pill" style="background:rgba(255,255,255,0.3);">
                                <i class="fas fa-money-bill-wave"></i> TSh <?= number_format($patient['total_amount'] ?? 0, 0) ?>
                            </span>
                            <i class="fas fa-chevron-down chevron" id="chevron-<?= $patient_id ?>"></i>
                        </div>
                    </div>
                    
                    <div class="patient-body" id="body-<?= $patient_id ?>">
                        
                        <div class="patient-actions">
                            <div class="patient-actions-info">
                                <i class="fas fa-info-circle" style="color:var(--primary);"></i>
                                <strong><?= $visit_count ?></strong> visit(s) • <strong><?= $test_count ?></strong> lab test(s) •
                                Total: <strong>TSh <?= number_format($patient['total_amount'] ?? 0, 0) ?></strong>
                            </div>
                            <div class="patient-actions-buttons">
                                <a href="patient_lab_tests.php?patient_id=<?= $patient_id ?>&branch=<?= $selected_branch_id ?>" 
                                   class="btn-patient-view" title="View All Lab Tests">
                                    <i class="fas fa-eye"></i> VIEW ALL
                                </a>
                            </div>
                        </div>
                        
                        <?php if (!empty($patient['visits'])): ?>
                            <?php foreach ($patient['visits'] as $visit): 
                                $visit_id = $visit['visit_id'];
                                $visit_number = $visit['visit_number'];
                                $visit_date = $visit['visit_date'];
                                $visit_doctor = $visit['doctor_name'];
                                $visit_status = $visit['overall_status'];
                                $visit_status_info = getLabStatusBadge($visit_status);
                                
                                $visit_test_count = count($visit['tests']);
                                $visit_amount = $visit['total_amount'];
                                
                                $table_unique_id = $patient_id . '-' . $visit_id;
                            ?>
                                <div class="visit-section">
                                    <div class="visit-section-header">
                                        <div class="visit-info-left">
                                            <div class="visit-icon-badge">
                                                <i class="fas fa-calendar-check"></i>
                                            </div>
                                            <span class="visit-number-display"><?= htmlspecialchars($visit_number) ?></span>
                                            <span class="visit-date-display">
                                                <i class="fas fa-calendar-day"></i>
                                                <?= !empty($visit_date) ? date('d M Y', strtotime($visit_date)) : 'N/A' ?>
                                            </span>
                                            <span class="visit-doctor-display">
                                                <i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($visit_doctor) ?>
                                            </span>
                                            <span class="status-badge <?= $visit_status_info['class'] ?>" style="font-size:0.58rem;padding:3px 9px;">
                                                <?= $visit_status_info['icon'] ?> <?= $visit_status_info['label'] ?>
                                            </span>
                                        </div>
                                        
                                        <div class="visit-stats-right">
                                            <span class="visit-mini-stat">
                                                <i class="fas fa-flask"></i> 
                                                Tests: <span class="stat-value"><?= $visit_test_count ?></span>
                                            </span>
                                            <span class="visit-mini-stat">
                                                <i class="fas fa-money-bill-wave"></i>
                                                <span class="stat-value">TSh <?= number_format($visit_amount, 0) ?></span>
                                            </span>
                                            
                                            <!-- TABLE NAVIGATION < > -->
                                            <div class="table-nav-group" title="Slide table left / right">
                                                <button type="button" 
                                                        class="table-nav-btn scroll-left" 
                                                        onclick="scrollTable('table-<?= $table_unique_id ?>', -1)"
                                                        title="Slide Left">
                                                    <i class="fas fa-chevron-left"></i>
                                                </button>
                                                <span class="table-nav-indicator" id="indicator-<?= $table_unique_id ?>">0%</span>
                                                <button type="button" 
                                                        class="table-nav-btn scroll-right" 
                                                        onclick="scrollTable('table-<?= $table_unique_id ?>', 1)"
                                                        title="Slide Right">
                                                    <i class="fas fa-chevron-right"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="visit-section-body">
                                        <div class="table-scroll-wrapper" id="wrapper-<?= $table_unique_id ?>">
                                            <div class="table-scroll" id="table-<?= $table_unique_id ?>">
                                                <table class="data-table">
                                                    <thead>
                                                        <tr>
                                                            <th style="width:45px;">#</th>
                                                            <th><i class="fas fa-flask"></i> Test Name</th>
                                                            <th><i class="fas fa-microscope"></i> Type / Sample</th>
                                                            <th><i class="fas fa-user-nurse"></i> Lab Technician</th>
                                                            <th><i class="fas fa-file-medical"></i> Result</th>
                                                            <th><i class="fas fa-money-bill-wave"></i> Price</th>
                                                            <th><i class="fas fa-info-circle"></i> Status</th>
                                                            <th><i class="fas fa-user-check"></i> Paid By</th>
                                                            <th><i class="fas fa-eye"></i> Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php $ti = 1; foreach ($visit['tests'] as $test): 
                                                            $status_info = getLabStatusBadge($test['status']);
                                                            $test_search = strtolower(
                                                                ($test['test_name'] ?? '') . ' ' . 
                                                                ($test['lab_technician_name'] ?? '') . ' ' . 
                                                                ($test['status'] ?? '')
                                                            );
                                                        ?>
                                                            <tr class="test-row" data-search="<?= htmlspecialchars($test_search) ?>">
                                                                <td style="text-align:center;font-weight:700;color:var(--text-secondary);font-size:0.7rem;"><?= $ti++ ?></td>
                                                                <td>
                                                                    <span style="font-weight:700;color:var(--primary);">
                                                                        <?= htmlspecialchars($test['test_name'] ?? 'N/A') ?>
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <?php if (!empty($test['test_type'])): ?>
                                                                        <span class="status-badge purple" style="font-size:0.55rem;">
                                                                            <?= htmlspecialchars($test['test_type']) ?>
                                                                        </span>
                                                                    <?php endif; ?>
                                                                    <?php if (!empty($test['sample_type'])): ?>
                                                                        <div style="font-size:0.65rem;color:var(--text-secondary);margin-top:2px;">
                                                                            <i class="fas fa-vial"></i> <?= htmlspecialchars($test['sample_type']) ?>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                    <?php if (empty($test['test_type']) && empty($test['sample_type'])): ?>
                                                                        <span style="color:var(--text-secondary);font-size:0.7rem;">—</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <?php if (!empty($test['lab_technician_name'])): ?>
                                                                        <span class="lab-tech-name">
                                                                            <i class="fas fa-user-nurse" style="color:var(--cyan);"></i>
                                                                            <?= htmlspecialchars($test['lab_technician_name']) ?>
                                                                        </span>
                                                                    <?php else: ?>
                                                                        <span class="lab-tech-name not-assigned">
                                                                            <i class="fas fa-user-slash"></i> Not assigned
                                                                        </span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <?php if (!empty($test['results'])): ?>
                                                                        <span class="lab-value">
                                                                            <?= htmlspecialchars($test['results']) ?>
                                                                            <?php if (!empty($test['formatted_result'])): ?>
                                                                                <span style="font-size:0.65rem;color:var(--text-secondary);">
                                                                                    (<?= htmlspecialchars($test['formatted_result']) ?>)
                                                                                </span>
                                                                            <?php endif; ?>
                                                                        </span>
                                                                    <?php else: ?>
                                                                        <span class="lab-value pending-value">
                                                                            <i class="fas fa-hourglass-half"></i> Waiting...
                                                                        </span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td class="amount-cell">
                                                                    TSh <?= number_format($test['test_price'] ?? 0, 0) ?>
                                                                </td>
                                                                <td>
                                                                    <span class="status-badge <?= $status_info['class'] ?>">
                                                                        <?= $status_info['icon'] ?> <?= $status_info['label'] ?>
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <?php if (!empty($test['received_by_name'])): ?>
                                                                        <span class="lab-tech-name" style="color:var(--success);">
                                                                            <i class="fas fa-check-circle"></i>
                                                                            <?= htmlspecialchars($test['received_by_name']) ?>
                                                                        </span>
                                                                    <?php else: ?>
                                                                        <span class="lab-tech-name not-assigned">
                                                                            <i class="fas fa-hourglass"></i> Not paid
                                                                        </span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <!-- ✅ VIEW ONLY (NO EDIT/DELETE) -->
                                                                    <div class="action-buttons-group">
                                                                        <a href="view_lab_test.php?id=<?= $test['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                                                           class="btn-action-sm view" 
                                                                           title="View Lab Test">
                                                                            <i class="fas fa-eye"></i> View
                                                                        </a>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                    </div>
                </div>
            <?php endforeach; ?>
            
            <div id="noSearchResults" style="display:none;">
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <p>No results found</p>
                    <p class="sub">Try a different keyword</p>
                </div>
            </div>
            
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-flask"></i>
                <p>No lab tests found</p>
                <p class="sub">
                    <?= !empty($search) || !empty($status_filter) || $quick_filter !== 'all' ? 'Try adjusting your filters' : 'No lab tests have been created yet' ?>
                </p>
            </div>
        <?php endif; ?>
    </div>

    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span style="margin:0 8px;">|</span>
            Lab Tests (Grouped by Patient → Visit)
            <span style="margin:0 8px;">|</span>
            <span style="color:var(--warning);font-weight:700;font-size:0.65rem;">
                <i class="fas fa-eye"></i> VIEW ONLY (AUDIT)
            </span>
            <span style="margin:0 8px;">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
        </p>
    </footer>

</main>

<script>
// ================================================================
// TOGGLE CUSTOM DATE
// ================================================================
function toggleCustomDate() {
    var row = document.getElementById('customDateRow');
    if (!row) return;
    row.classList.toggle('show');
}

// ================================================================
// TOGGLE PATIENT
// ================================================================
function togglePatient(patientId) {
    var body = document.getElementById('body-' + patientId);
    var chevron = document.getElementById('chevron-' + patientId);
    if (body) body.classList.toggle('open');
    if (chevron) chevron.classList.toggle('rotated');
    
    setTimeout(function() {
        if (body) {
            body.querySelectorAll('.table-scroll').forEach(function(tbl) {
                if (tbl.id) updateTableNav(tbl.id);
            });
        }
    }, 450);
}

document.addEventListener('DOMContentLoaded', function() {
    var firstBody = document.querySelector('.patient-body');
    var firstChevron = document.querySelector('.chevron');
    if (firstBody) {
        setTimeout(function() {
            firstBody.classList.add('open');
            if (firstChevron) firstChevron.classList.add('rotated');
            
            firstBody.querySelectorAll('.table-scroll').forEach(function(tbl) {
                if (tbl.id) updateTableNav(tbl.id);
            });
        }, 300);
    }
});

// ================================================================
// TABLE NAVIGATION < >
// ================================================================
function scrollTable(tableId, direction) {
    var table = document.getElementById(tableId);
    if (!table) return;
    
    var scrollAmount = 300;
    var currentScroll = table.scrollLeft;
    var maxScroll = table.scrollWidth - table.clientWidth;
    
    var newScroll = currentScroll + (direction * scrollAmount);
    
    if (newScroll < 0) newScroll = 0;
    if (newScroll > maxScroll) newScroll = maxScroll;
    
    table.scrollTo({
        left: newScroll,
        behavior: 'smooth'
    });
    
    setTimeout(function() {
        updateTableNav(tableId);
    }, 350);
}

function updateTableNav(tableId) {
    var table = document.getElementById(tableId);
    if (!table) return;
    
    var uniqueId = tableId.replace('table-', '');
    var indicator = document.getElementById('indicator-' + uniqueId);
    var wrapper = document.getElementById('wrapper-' + uniqueId);
    
    var currentScroll = table.scrollLeft;
    var maxScroll = table.scrollWidth - table.clientWidth;
    
    var percent = 0;
    if (maxScroll > 0) {
        percent = Math.round((currentScroll / maxScroll) * 100);
    }
    if (indicator) indicator.textContent = percent + '%';
    
    if (wrapper) {
        var parentHeader = wrapper.closest('.visit-section').querySelector('.visit-section-header');
        if (parentHeader) {
            var leftBtn = parentHeader.querySelector('.scroll-left');
            var rightBtn = parentHeader.querySelector('.scroll-right');
            
            if (leftBtn) leftBtn.disabled = (currentScroll <= 1);
            if (rightBtn) rightBtn.disabled = (currentScroll >= maxScroll - 1);
        }
        
        if (currentScroll > 1) wrapper.classList.add('can-scroll-left');
        else wrapper.classList.remove('can-scroll-left');
        
        if (currentScroll < maxScroll - 1) wrapper.classList.add('can-scroll-right');
        else wrapper.classList.remove('can-scroll-right');
    }
}

function initAllTableNav() {
    document.querySelectorAll('.table-scroll').forEach(function(table) {
        var tableId = table.id;
        if (!tableId) return;
        
        table.addEventListener('scroll', function() {
            updateTableNav(tableId);
        });
        
        updateTableNav(tableId);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    initAllTableNav();
});

window.addEventListener('resize', function() {
    initAllTableNav();
});

// ================================================================
// SEARCH
// ================================================================
(function() {
    var searchInput = document.getElementById('labSearchInput');
    var searchClear = document.getElementById('labSearchClear');
    var searchCount = document.getElementById('labSearchCount');
    
    function escapeRegExp(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }
    
    function highlightText(text, query) {
        if (!query || !text) return text;
        var escaped = escapeRegExp(query);
        var regex = new RegExp('(' + escaped + ')', 'gi');
        return text.replace(regex, '<mark style="background:#FEF08A;color:#854D0E;padding:1px 4px;border-radius:4px;font-weight:800;">$1</mark>');
    }
    
    function filterAll(query) {
        var patientCards = document.querySelectorAll('.patient-card');
        var noSearchResults = document.getElementById('noSearchResults');
        var visiblePatients = 0;
        var queryLower = query.toLowerCase().trim();
        
        patientCards.forEach(function(card) {
            if (queryLower === '') {
                var nameEl = card.querySelector('.patient-name-text');
                if (nameEl && nameEl.getAttribute('data-original')) {
                    nameEl.innerHTML = nameEl.getAttribute('data-original');
                }
            }
            
            var patientSearchData = card.getAttribute('data-patient-search') || '';
            var testRows = card.querySelectorAll('.test-row');
            var hasMatch = false;
            
            if (queryLower === '') {
                hasMatch = true;
            } else if (patientSearchData.includes(queryLower)) {
                hasMatch = true;
                var nameEl = card.querySelector('.patient-name-text');
                if (nameEl) {
                    var orig = nameEl.getAttribute('data-original') || nameEl.textContent;
                    nameEl.setAttribute('data-original', orig);
                    nameEl.innerHTML = highlightText(orig, query);
                }
            }
            
            testRows.forEach(function(row) {
                var searchData = row.getAttribute('data-search') || '';
                if (queryLower === '' || searchData.includes(queryLower) || patientSearchData.includes(queryLower)) {
                    hasMatch = true;
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
            
            if (hasMatch) {
                card.classList.remove('filtered-out');
                visiblePatients++;
            } else {
                card.classList.add('filtered-out');
            }
        });
        
        if (noSearchResults) {
            noSearchResults.style.display = (visiblePatients === 0 && queryLower !== '') ? '' : 'none';
        }
        
        if (queryLower !== '') {
            searchCount.textContent = visiblePatients + ' patient' + (visiblePatients !== 1 ? 's' : '');
            searchCount.classList.add('show');
            searchClear.classList.add('visible');
        } else {
            searchCount.classList.remove('show');
            searchClear.classList.remove('visible');
        }
        
        setTimeout(initAllTableNav, 100);
    }
    
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            filterAll(this.value);
        });
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                this.value = '';
                filterAll('');
                this.blur();
            }
        });
    }
    
    if (searchClear) {
        searchClear.addEventListener('click', function() {
            searchInput.value = '';
            filterAll('');
            searchInput.focus();
        });
    }
})();

// ================================================================
// FOOTER TIME
// ================================================================
setInterval(function() {
    var now = new Date();
    var timeStr = now.toLocaleTimeString('en-US', {
        hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
    });
    var ftEl = document.getElementById('footerTimestamp');
    if (ftEl) ftEl.textContent = 'Last updated: ' + timeStr;
}, 1000);

console.log('%c🔍 Audit - Lab Tests (VIEW ONLY)', 'font-size:16px;font-weight:bold;color:#0B5ED7;');
console.log('%c✅ Same as admin/audit/lab_tests.php', 'font-size:12px;color:#34D399;font-weight:bold;');
console.log('%c✅ NO Edit button (audit view only)', 'font-size:12px;color:#DC2626;font-weight:bold;');
console.log('%c✅ NO Delete button (audit view only)', 'font-size:12px;color:#DC2626;font-weight:bold;');
console.log('%c✅ Only View button', 'font-size:12px;color:#34D399;');
console.log('%c✅ Table nav < > kwa kila visit', 'font-size:12px;color:#0891B2;');
console.log('%c✅ Blue theme', 'font-size:12px;color:#0B5ED7;font-weight:bold;');
</script>

</body>
</html>