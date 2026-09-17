<?php
// ================================================================
// FILE: frontend/pages/audit/employees.php
// AUDIT ROLE - EMPLOYEES PERFORMANCE REPORT
// ✅ Kwa AUDIT role tu
// ✅ Performance metrics kwa kila employee
// ✅ Role-based performance (Doctor, Reception, Cashier, Pharmacy, Lab)
// ✅ Top performers leaderboard
// ✅ Blue theme: #0B5ED7
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

// ================================================================
// ✅ AUDIT ROLE TU
// ================================================================
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

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Audit User';
$user_role = $_SESSION['role'] ?? 'audit';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

$selected_branch_id = $_GET['branch'] ?? 'all';

// ================================================================
// ✅ DATABASE PATH - juu mara 3 kutoka pages/audit/
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// CURRENCY
$currency = 'TSh';
try {
    $stmt = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'currency'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['setting_value'])) $currency = $row['setting_value'];
} catch (Exception $e) {}

// ✅ NAMBA KAMILI
function formatMoney($amount) {
    if ($amount === null || $amount === '') return '0';
    return number_format((float)$amount, 0, '.', ',');
}

// BRANCH FILTER
$filter_by_branch = false;
$filter_branch_id = 0;
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $filter_by_branch = true;
    $filter_branch_id = (int)$selected_branch_id;
}

$branch_cond_u = $filter_by_branch ? " AND u.branch_id = ?" : "";
$branch_params = $filter_by_branch ? [$filter_branch_id] : [];

// BRANCHES LIST
$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$display_branch_name = 'All Branches';
if ($filter_by_branch) {
    foreach ($branches as $b) {
        if ($b['id'] == $filter_branch_id) { $display_branch_name = $b['name']; break; }
    }
}

// DATE FILTERS
$quick_filter = $_GET['quick'] ?? '1m';
$date_from = $_GET['date_from'] ?? date('Y-m-d');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$date_label = "";

switch ($quick_filter) {
    case 'today': $date_label = "Today"; break;
    case '1w': $date_label = "Last 1 Week"; break;
    case '1m': $date_label = "Last 1 Month"; break;
    case '3m': $date_label = "Last 3 Months"; break;
    case '6m': $date_label = "Last 6 Months"; break;
    case '1y': $date_label = "Last 1 Year"; break;
    case 'all': $date_label = "All Time"; break;
    case 'custom':
        $date_label = date('d M Y', strtotime($date_from)) . ' - ' . date('d M Y', strtotime($date_to));
        break;
    default: $date_label = "Last 1 Month";
}

function buildDateCond($quick, $column, &$params, $date_from, $date_to) {
    switch ($quick) {
        case 'today': return " AND DATE($column) = CURDATE()";
        case '1w': return " AND $column >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        case '1m': return " AND $column >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        case '3m': return " AND $column >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
        case '6m': return " AND $column >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
        case '1y': return " AND $column >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        case 'all': return "";
        case 'custom':
            $params[] = $date_from;
            $params[] = $date_to;
            return " AND DATE($column) BETWEEN ? AND ?";
        default: return " AND $column >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
    }
}

// ================================================================
// EMPLOYEES LIST
// ================================================================
$employees = [];
try {
    $sql = "SELECT u.id, u.username, u.full_name, u.email, u.phone, u.role,
                   u.branch_id, u.status, u.is_online, u.last_online, u.profile_pic,
                   u.specialty, u.created_at, b.name as branch_name
            FROM users u
            LEFT JOIN branches b ON u.branch_id = b.id
            WHERE u.role != 'admin' AND u.status = 'active'
            $branch_cond_u
            ORDER BY u.role ASC, u.full_name ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute($branch_params);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $employees = []; }

// ================================================================
// PERFORMANCE METRICS
// ================================================================
$performance = [];

foreach ($employees as $emp) {
    $emp_id = (int)$emp['id'];
    $role = $emp['role'];
    
    $perf = [
        'id' => $emp_id,
        'full_name' => $emp['full_name'],
        'username' => $emp['username'],
        'email' => $emp['email'],
        'phone' => $emp['phone'],
        'role' => $role,
        'branch_name' => $emp['branch_name'] ?? 'N/A',
        'status' => $emp['status'],
        'is_online' => $emp['is_online'] ?? 0,
        'last_online' => $emp['last_online'],
        'profile_pic' => $emp['profile_pic'],
        'specialty' => $emp['specialty'],
        'created_at' => $emp['created_at'],
        'transactions' => 0,
        'revenue' => 0,
        'items_processed' => 0,
        'patients_handled' => 0,
        'metric_label' => 'Activity',
        'activities' => 0,
        'performance_score' => 0,
    ];
    
    switch ($role) {
        case 'doctor':
            try {
                $d_params = [$emp_id];
                $dc = buildDateCond($quick_filter, 'b.created_at', $d_params, $date_from, $date_to);
                if ($filter_by_branch) $d_params[] = $filter_branch_id;
                
                $sql = "SELECT COUNT(DISTINCT b.id) as txn, 
                               COALESCE(SUM(b.total_amount), 0) as rev,
                               COUNT(DISTINCT b.patient_id) as patients
                        FROM bills b
                        WHERE b.created_by = ? $dc";
                if ($filter_by_branch) $sql .= " AND b.branch_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($d_params);
                $r = $stmt->fetch(PDO::FETCH_ASSOC);
                $perf['transactions'] = (int)($r['txn'] ?? 0);
                $perf['revenue'] = (float)($r['rev'] ?? 0);
                $perf['patients_handled'] = (int)($r['patients'] ?? 0);
                $perf['metric_label'] = 'Bills';
            } catch (Exception $e) {}
            
            try {
                $d_params = [$emp_id];
                $dc = buildDateCond($quick_filter, 'v.created_at', $d_params, $date_from, $date_to);
                if ($filter_by_branch) $d_params[] = $filter_branch_id;
                $sql = "SELECT COUNT(*) as cnt FROM visits v WHERE v.doctor_id = ? $dc";
                if ($filter_by_branch) $sql .= " AND v.branch_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($d_params);
                $perf['items_processed'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            } catch (Exception $e) {}
            break;
            
        case 'reception':
            try {
                $d_params = [$emp_id];
                $dc = buildDateCond($quick_filter, 'p.created_at', $d_params, $date_from, $date_to);
                if ($filter_by_branch) $d_params[] = $filter_branch_id;
                $sql = "SELECT COUNT(*) as cnt FROM patients p WHERE p.created_by = ? $dc";
                if ($filter_by_branch) $sql .= " AND p.branch_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($d_params);
                $perf['patients_handled'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
                $perf['transactions'] = $perf['patients_handled'];
                $perf['metric_label'] = 'Patients';
            } catch (Exception $e) {}
            
            try {
                $d_params = [$emp_id];
                $dc = buildDateCond($quick_filter, 'v.created_at', $d_params, $date_from, $date_to);
                if ($filter_by_branch) $d_params[] = $filter_branch_id;
                $sql = "SELECT COUNT(*) as cnt FROM visits v WHERE v.created_by = ? $dc";
                if ($filter_by_branch) $sql .= " AND v.branch_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($d_params);
                $perf['items_processed'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            } catch (Exception $e) {}
            break;
            
        case 'cashier':
            try {
                $d_params = [$emp_id];
                $dc = buildDateCond($quick_filter, 'b.updated_at', $d_params, $date_from, $date_to);
                if ($filter_by_branch) $d_params[] = $filter_branch_id;
                $sql = "SELECT COUNT(*) as cnt, COALESCE(SUM(b.paid_amount), 0) as rev
                        FROM bills b WHERE b.created_by = ? AND b.status = 'paid' $dc";
                if ($filter_by_branch) $sql .= " AND b.branch_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($d_params);
                $r = $stmt->fetch(PDO::FETCH_ASSOC);
                $perf['transactions'] += (int)($r['cnt'] ?? 0);
                $perf['revenue'] += (float)($r['rev'] ?? 0);
                $perf['metric_label'] = 'Transactions';
            } catch (Exception $e) {}
            
            try {
                $d_params = [$emp_id];
                $dc = buildDateCond($quick_filter, 'o.updated_at', $d_params, $date_from, $date_to);
                if ($filter_by_branch) $d_params[] = $filter_branch_id;
                $sql = "SELECT COUNT(*) as cnt, COALESCE(SUM(o.total_amount), 0) as rev
                        FROM otc_sales o WHERE o.sold_by = ? AND o.payment_status = 'paid' $dc";
                if ($filter_by_branch) $sql .= " AND o.branch_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($d_params);
                $r = $stmt->fetch(PDO::FETCH_ASSOC);
                $perf['transactions'] += (int)($r['cnt'] ?? 0);
                $perf['revenue'] += (float)($r['rev'] ?? 0);
            } catch (Exception $e) {}
            break;
            
        case 'pharmacy':
            try {
                $d_params = [$emp_id];
                $dc = buildDateCond($quick_filter, 'p.updated_at', $d_params, $date_from, $date_to);
                if ($filter_by_branch) $d_params[] = $filter_branch_id;
                $sql = "SELECT COUNT(*) as cnt FROM prescriptions p 
                        WHERE p.dispensed_by = ? AND p.status IN ('dispensed', 'confirmed') $dc";
                if ($filter_by_branch) $sql .= " AND p.branch_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($d_params);
                $perf['items_processed'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
                $perf['transactions'] = $perf['items_processed'];
                $perf['metric_label'] = 'Prescriptions';
            } catch (Exception $e) {}
            
            try {
                $d_params = [$emp_id];
                $dc = buildDateCond($quick_filter, 'o.updated_at', $d_params, $date_from, $date_to);
                if ($filter_by_branch) $d_params[] = $filter_branch_id;
                $sql = "SELECT COALESCE(SUM(osi.quantity), 0) as qty, COALESCE(SUM(osi.total_price), 0) as rev
                        FROM otc_sale_items osi
                        INNER JOIN otc_sales o ON osi.sale_id = o.id
                        WHERE o.sold_by = ? AND o.payment_status = 'paid' $dc";
                if ($filter_by_branch) $sql .= " AND o.branch_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($d_params);
                $r = $stmt->fetch(PDO::FETCH_ASSOC);
                $perf['revenue'] += (float)($r['rev'] ?? 0);
            } catch (Exception $e) {}
            break;
            
        case 'laboratory':
            try {
                $d_params = [$emp_id];
                $dc = buildDateCond($quick_filter, 'lt.updated_at', $d_params, $date_from, $date_to);
                if ($filter_by_branch) $d_params[] = $filter_branch_id;
                $sql = "SELECT COUNT(*) as cnt, COALESCE(SUM(lt.test_price), 0) as rev
                        FROM lab_tests lt
                        WHERE lt.lab_technician_id = ? AND lt.status = 'completed' $dc";
                if ($filter_by_branch) $sql .= " AND lt.branch_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($d_params);
                $r = $stmt->fetch(PDO::FETCH_ASSOC);
                $perf['transactions'] = (int)($r['cnt'] ?? 0);
                $perf['items_processed'] = $perf['transactions'];
                $perf['revenue'] = (float)($r['rev'] ?? 0);
                $perf['metric_label'] = 'Tests';
            } catch (Exception $e) {}
            break;
            
        case 'audit':
            try {
                $d_params = [$emp_id];
                $dc = buildDateCond($quick_filter, 'al.created_at', $d_params, $date_from, $date_to);
                $sql = "SELECT COUNT(*) as cnt FROM activity_logs al WHERE al.user_id = ? $dc";
                $stmt = $db->prepare($sql);
                $stmt->execute($d_params);
                $perf['activities'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
                $perf['transactions'] = $perf['activities'];
                $perf['metric_label'] = 'Activities';
            } catch (Exception $e) {}
            break;
    }
    
    try {
        $a_params = [$emp_id];
        $ac = buildDateCond($quick_filter, 'al.created_at', $a_params, $date_from, $date_to);
        $sql = "SELECT COUNT(*) as cnt FROM activity_logs al WHERE al.user_id = ? $ac";
        $stmt = $db->prepare($sql);
        $stmt->execute($a_params);
        $perf['activities'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
    } catch (Exception $e) {}
    
    $perf['performance_score'] = ($perf['transactions'] * 10) + ($perf['revenue'] / 1000) + ($perf['activities'] * 2);
    
    $performance[] = $perf;
}

usort($performance, function($a, $b) {
    return $b['performance_score'] <=> $a['performance_score'];
});

// ================================================================
// STATISTICS
// ================================================================
$total_employees = count($performance);
$total_online = 0;
$total_revenue = 0;
$total_transactions = 0;
$role_counts = [];

foreach ($performance as $p) {
    if (!empty($p['is_online'])) $total_online++;
    $total_revenue += $p['revenue'];
    $total_transactions += $p['transactions'];
    $role = $p['role'];
    if (!isset($role_counts[$role])) $role_counts[$role] = 0;
    $role_counts[$role]++;
}

$top_performers = array_slice($performance, 0, 5);

// ROLE SUMMARY
$role_summary = [];
foreach ($performance as $p) {
    $r = $p['role'];
    if (!isset($role_summary[$r])) {
        $role_summary[$r] = ['count' => 0, 'revenue' => 0, 'transactions' => 0];
    }
    $role_summary[$r]['count']++;
    $role_summary[$r]['revenue'] += $p['revenue'];
    $role_summary[$r]['transactions'] += $p['transactions'];
}

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

// ================================================================
// ✅ HEADER NA SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/audit_header.php';
include_once __DIR__ . '/../../components/audit_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Performance - Braick Audit</title>
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
:root {
    --font-primary: 'Inter', -apple-system, sans-serif;
    --font-mono: 'JetBrains Mono', 'Courier New', monospace;
    --primary: #0B5ED7;
    --primary-dark: #0A4CA8;
    --primary-light: #3B82F6;
    --primary-bg: #E8F0FE;
    --bg-body: #F1F5F9;
    --bg-card: #FFFFFF;
    --text-primary: #1E293B;
    --text-secondary: #64748B;
    --border-color: #E2E8F0;
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
    --highlight-bg: #FEF08A;
    --highlight-text: #713F12;
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
    --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
    --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
}
[data-theme="dark"] {
    --bg-body: #0F172A;
    --bg-card: #1E293B;
    --text-primary: #F1F5F9;
    --text-secondary: #94A3B8;
    --border-color: #334155;
    --primary: #3B82F6;
    --primary-dark: #2563EB;
    --primary-light: #60A5FA;
    --primary-bg: #1E3A5F;
    --success-bg: #1A3A2A;
    --danger-bg: #3A1A1A;
    --warning-bg: #3A2A1A;
    --purple-bg: #2D1B4E;
    --cyan-bg: #0E3A47;
    --highlight-bg: #78350F;
    --highlight-text: #FEF3C7;
}
* { font-family: var(--font-primary); -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }
html, body { font-family: var(--font-primary); background: var(--bg-body); color: var(--text-primary); }
.money-number, .stat-value, .money-cell, .card-value, .font-mono, .score-badge, .rank-badge {
    font-family: var(--font-mono) !important;
    font-feature-settings: 'tnum';
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.02em;
}

.page-header {
    background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 100%);
    border-radius: 16px; padding: 20px 24px; margin-bottom: 20px;
    display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center;
    gap: 14px; box-shadow: 0 6px 20px rgba(11, 94, 215, 0.25);
    position: relative; overflow: hidden;
}
.page-header::before {
    content: ''; position: absolute; top: -50%; right: -20%;
    width: 350px; height: 350px;
    background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
    border-radius: 50%; pointer-events: none;
}
.page-header .page-title {
    color: white; font-size: 1.4rem; font-weight: 800;
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
    position: relative; z-index: 1; letter-spacing: -0.02em;
}
.page-header .page-title i { font-size: 1.5rem; color: #93C5FD; }
.page-header .page-subtitle {
    color: rgba(255,255,255,0.85); font-size: 0.78rem;
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    margin-top: 4px; position: relative; z-index: 1;
}
.branch-tag {
    background: rgba(255,255,255,0.15); color: white;
    padding: 3px 10px; border-radius: 16px; font-size: 0.65rem;
    font-weight: 500; display: inline-flex; align-items: center; gap: 4px;
    backdrop-filter: blur(4px); border: 1px solid rgba(255,255,255,0.1);
}
.branch-tag.filter-tag {
    background: linear-gradient(135deg, #10B981, #059669);
    font-weight: 700;
}
.btn-header {
    background: rgba(255,255,255,0.15); color: white;
    border: 1px solid rgba(255,255,255,0.25);
    padding: 8px 14px; border-radius: 9px;
    font-weight: 600; font-size: 0.75rem;
    transition: all 0.3s ease; text-decoration: none;
    display: inline-flex; align-items: center; gap: 6px;
    backdrop-filter: blur(4px); position: relative; z-index: 1; cursor: pointer;
}
.btn-header:hover { background: rgba(255,255,255,0.28); transform: translateY(-2px); }
.btn-pdf-export {
    background: linear-gradient(135deg, #DC2626, #B91C1C) !important;
    font-weight: 700 !important;
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.4);
}

.filter-card {
    background: var(--bg-card); border-radius: 14px; padding: 16px 18px;
    border: 2px solid var(--border-color); margin-bottom: 18px;
    box-shadow: var(--shadow-sm);
}
.filter-section { margin-bottom: 14px; }
.filter-section:last-child { margin-bottom: 0; }
.filter-section-title {
    font-size: 0.68rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: 0.06em; color: var(--text-secondary);
    margin-bottom: 8px; display: flex; align-items: center; gap: 5px;
}
.quick-filters { display: flex; gap: 6px; flex-wrap: wrap; }
.quick-btn {
    padding: 6px 12px; border-radius: 8px;
    border: 2px solid var(--border-color); background: var(--bg-card);
    color: var(--text-secondary); font-weight: 700; font-size: 0.7rem;
    cursor: pointer; transition: all 0.3s ease;
    display: inline-flex; align-items: center; gap: 5px; text-decoration: none;
}
.quick-btn:hover { border-color: var(--primary); color: var(--primary); transform: translateY(-2px); }
.quick-btn.active {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white; border-color: transparent;
    box-shadow: 0 4px 10px rgba(11, 94, 215, 0.3);
}
.filter-form {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 10px; align-items: end; margin-top: 10px;
    padding-top: 12px; border-top: 2px dashed var(--border-color);
}
.filter-group { display: flex; flex-direction: column; gap: 4px; }
.filter-group label {
    font-size: 0.65rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.05em;
    color: var(--text-secondary);
    display: flex; align-items: center; gap: 4px;
}
.filter-group input, .filter-group select {
    padding: 8px 12px; border-radius: 8px;
    border: 2px solid var(--border-color); background: var(--bg-card);
    color: var(--text-primary); font-size: 0.78rem; font-weight: 600;
    outline: none; transition: all 0.3s ease; height: 36px;
}
.filter-btn-primary {
    padding: 8px 16px; border-radius: 8px;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white; border: none; font-weight: 700; font-size: 0.75rem;
    cursor: pointer; transition: all 0.3s ease;
    display: inline-flex; align-items: center; gap: 6px;
    justify-content: center; height: 36px;
}
.filter-btn-secondary {
    padding: 8px 16px; border-radius: 8px;
    background: transparent; color: var(--text-secondary);
    border: 2px solid var(--border-color);
    font-weight: 700; font-size: 0.75rem; cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex; align-items: center; gap: 6px;
    justify-content: center; height: 36px; text-decoration: none;
}

.stats-grid {
    display: grid; grid-template-columns: repeat(4, 1fr);
    gap: 14px; margin-bottom: 20px;
}
.stat-card {
    background: var(--bg-card); border-radius: 14px; padding: 14px 16px;
    border: 2px solid var(--border-color);
    transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: var(--shadow-sm); position: relative; overflow: hidden;
    min-height: 130px; display: flex; flex-direction: column;
    justify-content: space-between;
}
.stat-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0;
    height: 3px; transition: height 0.3s ease;
}
.stat-card:hover::before { height: 5px; }
.stat-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); }
.stat-card .stat-icon {
    width: 36px; height: 36px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; color: white; margin-bottom: 8px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.15);
    transition: transform 0.3s ease;
}
.stat-card:hover .stat-icon { transform: scale(1.1) rotate(-5deg); }
.stat-card .stat-label {
    font-size: 0.62rem; color: var(--text-secondary);
    font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.06em; margin-bottom: 4px;
}
.stat-card .stat-value {
    font-size: 1.25rem; font-weight: 900; color: var(--text-primary);
    line-height: 1.1; letter-spacing: -0.03em;
    display: flex; align-items: baseline; gap: 4px; flex-wrap: wrap;
}
.stat-card .stat-value .currency-symbol {
    font-size: 0.72rem; font-weight: 700;
    color: var(--text-secondary); font-family: var(--font-primary);
}
.stat-card .stat-sub {
    font-size: 0.6rem; color: var(--text-secondary);
    margin-top: 8px; padding-top: 8px;
    border-top: 1px dashed var(--border-color);
    display: flex; align-items: center; gap: 4px; font-weight: 600;
}
.stat-card.blue::before { background: linear-gradient(90deg, #0B5ED7, #3B82F6, #0B5ED7); background-size: 200% 100%; animation: shimmer 3s infinite linear; }
.stat-card.blue .stat-icon { background: linear-gradient(135deg, #0B5ED7, #3B82F6); }
.stat-card.blue .stat-value .money-number { color: var(--primary); }
.stat-card.green::before { background: linear-gradient(90deg, #059669, #34D399, #059669); background-size: 200% 100%; animation: shimmer 3s infinite linear; }
.stat-card.green .stat-icon { background: linear-gradient(135deg, #059669, #34D399); }
.stat-card.green .stat-value .money-number { color: var(--success); }
.stat-card.purple::before { background: linear-gradient(90deg, #7C3AED, #A78BFA, #7C3AED); background-size: 200% 100%; animation: shimmer 3s infinite linear; }
.stat-card.purple .stat-icon { background: linear-gradient(135deg, #7C3AED, #A78BFA); }
.stat-card.purple .stat-value .money-number { color: var(--purple); }
.stat-card.cyan::before { background: linear-gradient(90deg, #0891B2, #06B6D4, #0891B2); background-size: 200% 100%; animation: shimmer 3s infinite linear; }
.stat-card.cyan .stat-icon { background: linear-gradient(135deg, #0891B2, #06B6D4); }
.stat-card.cyan .stat-value .money-number { color: var(--cyan); }
@keyframes shimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

.table-card {
    background: var(--bg-card); border-radius: 14px;
    border: 2px solid var(--border-color); overflow: hidden;
    box-shadow: var(--shadow-sm); margin-bottom: 18px;
}
.table-card .table-header {
    padding: 12px 18px;
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    display: flex; justify-content: space-between; align-items: center;
    flex-wrap: wrap; gap: 8px;
}
.table-card .table-header .title {
    color: white; font-size: 0.85rem; font-weight: 800;
    display: flex; align-items: center; gap: 8px;
}
.table-card .table-header .title i { color: #93C5FD; font-size: 0.95rem; }
.table-card .table-header .count {
    color: rgba(255,255,255,0.9); font-size: 0.68rem; font-weight: 700;
    background: rgba(255,255,255,0.15); padding: 3px 10px;
    border-radius: 10px; backdrop-filter: blur(4px);
}
.table-toolbar {
    display: flex; justify-content: space-between; align-items: center;
    gap: 10px; flex-wrap: wrap; padding: 10px 16px;
    background: var(--primary-bg); border-bottom: 2px solid var(--border-color);
}
[data-theme="dark"] .table-toolbar { background: rgba(10, 46, 92, 0.3); }
.table-toolbar-left { display: flex; align-items: center; gap: 8px; flex: 1; min-width: 200px; }
.table-toolbar-right { display: flex; align-items: center; gap: 6px; }
.search-box { position: relative; flex: 1; max-width: 380px; }
.search-box input {
    width: 100%; padding: 8px 32px 8px 32px;
    border-radius: 8px; border: 2px solid var(--border-color);
    background: var(--bg-card); color: var(--text-primary);
    font-size: 0.78rem; font-weight: 500; outline: none;
    transition: all 0.3s ease; height: 36px;
}
.search-box input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15);
}
.search-box .search-icon {
    position: absolute; left: 10px; top: 50%;
    transform: translateY(-50%); color: var(--text-secondary);
    font-size: 0.75rem; pointer-events: none;
}
.search-box .search-clear {
    position: absolute; right: 6px; top: 50%;
    transform: translateY(-50%); background: transparent;
    border: none; color: var(--text-secondary); cursor: pointer;
    font-size: 0.75rem; padding: 4px 6px; border-radius: 5px;
    display: none;
}
.search-box.has-value .search-clear { display: block; }
.search-count {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 4px 10px; border-radius: 8px; font-size: 0.65rem;
    font-weight: 700; background: var(--primary); color: white;
    white-space: nowrap; height: 28px;
}
.search-count.has-results { background: var(--success); }
.search-count.no-results { background: var(--danger); }
.scroll-btn {
    width: 32px; height: 32px; border-radius: 8px;
    border: 2px solid var(--border-color); background: var(--bg-card);
    color: var(--text-primary); cursor: pointer;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 0.75rem; font-weight: 700;
    transition: all 0.25s ease; flex-shrink: 0;
}
.scroll-btn:hover {
    background: var(--primary); color: white;
    border-color: var(--primary); transform: translateY(-2px);
}
.table-scroll-wrapper { overflow-x: auto; scroll-behavior: smooth; }
.table-scroll-wrapper::-webkit-scrollbar { height: 6px; }
.table-scroll-wrapper::-webkit-scrollbar-track { background: var(--border-color); border-radius: 10px; }
.table-scroll-wrapper::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
mark.search-highlight {
    background: var(--highlight-bg); color: var(--highlight-text);
    padding: 1px 3px; border-radius: 4px; font-weight: 800;
}

.data-table { width: 100%; border-collapse: collapse; font-size: 0.78rem; }
.data-table thead th {
    text-align: left; padding: 9px 12px;
    font-weight: 800; font-size: 0.6rem; text-transform: uppercase;
    letter-spacing: 0.06em; color: white;
    background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    white-space: nowrap;
}
.data-table tbody td {
    padding: 9px 12px; border-bottom: 1px solid var(--border-color);
    color: var(--text-primary); vertical-align: middle; font-weight: 500;
}
.data-table tbody tr { transition: background 0.2s ease; }
.data-table tbody tr:hover td { background: var(--primary-bg); }
[data-theme="dark"] .data-table tbody tr:hover td { background: #0A2E5C; }
.data-table tbody tr:last-child td { border-bottom: none; }
.data-table tbody tr.hidden-row { display: none !important; }

.rank-badge {
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 28px; border-radius: 50%;
    font-weight: 800; font-size: 0.7rem;
    background: var(--primary-bg); color: var(--primary);
}
.rank-badge.gold { background: linear-gradient(135deg, #FCD34D, #F59E0B); color: #78350F; }
.rank-badge.silver { background: linear-gradient(135deg, #E5E7EB, #9CA3AF); color: #374151; }
.rank-badge.bronze { background: linear-gradient(135deg, #FBBF24, #D97706); color: #78350F; }

.emp-cell { display: flex; align-items: center; gap: 10px; }
.emp-avatar {
    width: 36px; height: 36px; border-radius: 50%;
    background: linear-gradient(135deg, #0B5ED7, #3B82F6);
    color: white; display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: 0.75rem; flex-shrink: 0;
    text-transform: uppercase; font-family: var(--font-mono);
}
.emp-info { display: flex; flex-direction: column; gap: 1px; }
.emp-name { font-size: 0.78rem; font-weight: 700; color: var(--text-primary); }
.emp-meta { font-size: 0.6rem; color: var(--text-secondary); display: flex; align-items: center; gap: 4px; }

.role-tag {
    display: inline-flex; align-items: center; gap: 3px;
    padding: 3px 10px; border-radius: 12px;
    font-size: 0.6rem; font-weight: 800;
    text-transform: uppercase; white-space: nowrap;
}
.role-tag.doctor { background: #EDE9FE; color: #7C3AED; }
.role-tag.reception { background: #DBEAFE; color: #1E40AF; }
.role-tag.pharmacy { background: #D1FAE5; color: #059669; }
.role-tag.cashier { background: #FEF3C7; color: #D97706; }
.role-tag.laboratory { background: #CFFAFE; color: #0891B2; }
.role-tag.audit { background: #FCE7F3; color: #BE185D; }
[data-theme="dark"] .role-tag.doctor { background: #2D1B4E; color: #A78BFA; }
[data-theme="dark"] .role-tag.reception { background: #1E3A8A; color: #93C5FD; }
[data-theme="dark"] .role-tag.pharmacy { background: #1A3A2A; color: #34D399; }
[data-theme="dark"] .role-tag.cashier { background: #78350F; color: #FDE68A; }
[data-theme="dark"] .role-tag.laboratory { background: #0E3A47; color: #67E8F9; }
[data-theme="dark"] .role-tag.audit { background: #4C1D95; color: #FBCFE8; }

.status-dot {
    width: 8px; height: 8px; border-radius: 50%;
    display: inline-block; margin-right: 6px;
}
.status-dot.online { background: var(--success); box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.2); }
.status-dot.offline { background: var(--text-secondary); }

.score-badge {
    display: inline-flex; align-items: center; justify-content: center;
    padding: 4px 10px; border-radius: 20px;
    font-size: 0.72rem; font-weight: 800;
    font-family: var(--font-mono);
}
.score-badge.excellent { background: linear-gradient(135deg, #059669, #34D399); color: white; }
.score-badge.good { background: linear-gradient(135deg, #0891B2, #06B6D4); color: white; }
.score-badge.average { background: linear-gradient(135deg, #D97706, #FBBF24); color: white; }
.score-badge.low { background: linear-gradient(135deg, #DC2626, #F87171); color: white; }

.money-cell {
    font-family: var(--font-mono); font-weight: 800;
    font-size: 0.8rem; color: var(--success);
    text-align: right; letter-spacing: -0.02em;
}
.money-cell .currency-prefix {
    font-size: 0.65rem; color: var(--text-secondary);
    margin-right: 2px; font-family: var(--font-primary); font-weight: 600;
}

.top-performer-card {
    background: var(--bg-card); border-radius: 12px;
    padding: 14px 16px; border: 2px solid var(--border-color);
    display: flex; align-items: center; gap: 12px;
    transition: all 0.3s ease;
}
.top-performer-card:hover {
    border-color: var(--primary);
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(11, 94, 215, 0.12);
}
.top-performer-card.rank-1 { border-color: #FCD34D; background: linear-gradient(135deg, #FFFBEB, var(--bg-card)); }
.top-performer-card.rank-2 { border-color: #9CA3AF; }
.top-performer-card.rank-3 { border-color: #D97706; }
[data-theme="dark"] .top-performer-card.rank-1 { background: linear-gradient(135deg, #422006, var(--bg-card)); }

@media (max-width: 1200px) { .stats-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 1024px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 768px) {
    .page-header { padding: 16px 18px; }
    .page-header .page-title { font-size: 1.15rem; }
    .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
    .stat-card { padding: 12px; min-height: 115px; }
    .data-table { font-size: 0.7rem; }
    .data-table thead th, .data-table tbody td { padding: 7px 8px; }
}
@media (max-width: 480px) { .stats-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-users"></i>
                Employee Performance
                <span class="branch-tag"><i class="fas fa-shield-alt"></i> AUDIT</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-store-alt"></i>
                <strong><?= htmlspecialchars($display_branch_name) ?></strong>
                <span class="branch-tag">
                    <i class="fas fa-users"></i> <?= number_format($total_employees) ?> Employees
                </span>
                <span class="branch-tag">
                    <i class="fas fa-circle" style="color:#34D399;"></i> <?= number_format($total_online) ?> Online
                </span>
                <span class="branch-tag filter-tag">
                    <i class="fas fa-filter"></i> <?= htmlspecialchars($date_label) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <button onclick="window.print()" class="btn-header">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="dashboard.php?branch=<?= $selected_branch_id ?>" class="btn-header">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- FILTER CARD -->
    <div class="filter-card">
        <div class="filter-section">
            <div class="filter-section-title">
                <i class="fas fa-bolt"></i> Quick Filters
            </div>
            <div class="quick-filters">
                <a href="?branch=<?= $selected_branch_id ?>&quick=today" class="quick-btn <?= $quick_filter === 'today' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-day"></i> Today
                </a>
                <a href="?branch=<?= $selected_branch_id ?>&quick=1w" class="quick-btn <?= $quick_filter === '1w' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-week"></i> 1W
                </a>
                <a href="?branch=<?= $selected_branch_id ?>&quick=1m" class="quick-btn <?= $quick_filter === '1m' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-alt"></i> 1M
                </a>
                <a href="?branch=<?= $selected_branch_id ?>&quick=3m" class="quick-btn <?= $quick_filter === '3m' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-alt"></i> 3M
                </a>
                <a href="?branch=<?= $selected_branch_id ?>&quick=6m" class="quick-btn <?= $quick_filter === '6m' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-alt"></i> 6M
                </a>
                <a href="?branch=<?= $selected_branch_id ?>&quick=1y" class="quick-btn <?= $quick_filter === '1y' ? 'active' : '' ?>">
                    <i class="fas fa-calendar"></i> 1Y
                </a>
                <a href="?branch=<?= $selected_branch_id ?>&quick=all" class="quick-btn <?= $quick_filter === 'all' ? 'active' : '' ?>">
                    <i class="fas fa-infinity"></i> All
                </a>
                <a href="?branch=<?= $selected_branch_id ?>&quick=custom&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" 
                   class="quick-btn <?= $quick_filter === 'custom' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-check"></i> Custom
                </a>
            </div>
        </div>

        <form method="GET" id="filterForm">
            <input type="hidden" name="branch" value="<?= htmlspecialchars($selected_branch_id) ?>">
            <input type="hidden" name="quick" value="<?= htmlspecialchars($quick_filter) ?>">
            <?php if ($quick_filter === 'custom'): ?>
            <div class="filter-form">
                <div class="filter-group">
                    <label><i class="fas fa-calendar"></i> From</label>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-calendar"></i> To</label>
                    <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
                </div>
                <button type="submit" class="filter-btn-primary">
                    <i class="fas fa-filter"></i> Apply
                </button>
                <a href="?branch=<?= $selected_branch_id ?>" class="filter-btn-secondary">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- SUMMARY STATS -->
    <div class="stats-grid">
        <div class="stat-card blue">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-label">Total Employees</div>
            <div class="stat-value">
                <span class="money-number"><?= number_format($total_employees) ?></span>
            </div>
            <div class="stat-sub">
                <i class="fas fa-circle" style="color:var(--success);font-size:0.5rem;"></i>
                <?= number_format($total_online) ?> online
            </div>
        </div>
        
        <div class="stat-card green">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-label">Total Revenue Generated</div>
            <div class="stat-value">
                <span class="currency-symbol"><?= $currency ?></span>
                <span class="money-number"><?= formatMoney($total_revenue) ?></span>
            </div>
            <div class="stat-sub">
                <i class="fas fa-chart-line"></i> By all employees
            </div>
        </div>
        
        <div class="stat-card purple">
            <div class="stat-icon"><i class="fas fa-receipt"></i></div>
            <div class="stat-label">Total Transactions</div>
            <div class="stat-value">
                <span class="money-number"><?= number_format($total_transactions) ?></span>
            </div>
            <div class="stat-sub">
                <i class="fas fa-list"></i> Processed
            </div>
        </div>
        
        <div class="stat-card cyan">
            <div class="stat-icon"><i class="fas fa-trophy"></i></div>
            <div class="stat-label">Avg per Employee</div>
            <div class="stat-value">
                <span class="money-number"><?= $total_employees > 0 ? number_format($total_transactions / $total_employees, 1) : 0 ?></span>
            </div>
            <div class="stat-sub">
                <i class="fas fa-balance-scale"></i> Transactions
            </div>
        </div>
    </div>

    <!-- TOP PERFORMERS -->
    <?php if (count($top_performers) > 0): ?>
    <div class="table-card">
        <div class="table-header">
            <span class="title"><i class="fas fa-trophy"></i> Top Performers (Leaderboard)</span>
            <span class="count">Top <?= count($top_performers) ?></span>
        </div>
        <div style="padding:16px;display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:12px;">
            <?php foreach ($top_performers as $idx => $p): 
                $rank = $idx + 1;
                $rank_class = $rank === 1 ? 'gold' : ($rank === 2 ? 'silver' : ($rank === 3 ? 'bronze' : ''));
                $name_parts = explode(' ', trim($p['full_name']));
                $initials = strtoupper(substr($p['full_name'], 0, 1));
                if (count($name_parts) >= 2) {
                    $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1));
                }
            ?>
                <div class="top-performer-card rank-<?= $rank ?>">
                    <span class="rank-badge <?= $rank_class ?>"><?= $rank ?></span>
                    <div class="emp-avatar"><?= htmlspecialchars($initials) ?></div>
                    <div style="flex:1;min-width:0;">
                        <div class="emp-name"><?= htmlspecialchars($p['full_name']) ?></div>
                        <div class="emp-meta">
                            <span class="role-tag <?= htmlspecialchars($p['role']) ?>"><?= strtoupper($p['role']) ?></span>
                            <span style="font-size:0.6rem;color:var(--text-secondary);">
                                <?= $p['branch_name'] ?>
                            </span>
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-family:var(--font-mono);font-weight:800;color:var(--primary);font-size:0.85rem;">
                            <?= number_format($p['transactions']) ?>
                        </div>
                        <div style="font-size:0.58rem;color:var(--text-secondary);text-transform:uppercase;font-weight:700;">
                            <?= $p['metric_label'] ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ALL EMPLOYEES PERFORMANCE TABLE -->
    <div class="table-card">
        <div class="table-header">
            <span class="title"><i class="fas fa-chart-line"></i> Employee Performance Report</span>
            <span class="count"><?= count($performance) ?> employees</span>
        </div>
        
        <div class="table-toolbar">
            <div class="table-toolbar-left">
                <div class="search-box" id="empSearchBox">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="empSearch" 
                           placeholder="Search employee name, role, email..."
                           oninput="filterTable('empTable', this.value, 'empCount')"
                           autocomplete="off">
                    <button type="button" class="search-clear" onclick="clearSearch('empTable', 'empSearch', 'empCount')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <span class="search-count" id="empCount">
                    <i class="fas fa-list"></i>
                    <span class="count-text"><?= count($performance) ?> records</span>
                </span>
            </div>
            <div class="table-toolbar-right">
                <button type="button" class="scroll-btn" onclick="scrollTable('empWrapper', 'left')">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button type="button" class="scroll-btn" onclick="scrollTable('empWrapper', 'right')">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
        
        <div class="table-scroll-wrapper" id="empWrapper">
            <table class="data-table" id="empTable" style="min-width:1400px;">
                <thead>
                    <tr>
                        <th style="width:50px;text-align:center;">Rank</th>
                        <th>Employee</th>
                        <th style="text-align:center;">Role</th>
                        <th>Branch</th>
                        <th style="text-align:center;">Status</th>
                        <th style="text-align:center;">Activity</th>
                        <th style="text-align:center;">Activities</th>
                        <th style="text-align:right;">Revenue</th>
                        <th style="text-align:center;">Score</th>
                        <th style="text-align:center;">Performance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($performance) > 0): ?>
                        <?php $rank = 1; foreach ($performance as $p): 
                            $rank_class = $rank === 1 ? 'gold' : ($rank === 2 ? 'silver' : ($rank === 3 ? 'bronze' : ''));
                            
                            $name_parts = explode(' ', trim($p['full_name']));
                            $initials = strtoupper(substr($p['full_name'], 0, 1));
                            if (count($name_parts) >= 2) {
                                $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1));
                            }
                            
                            $score = $p['performance_score'];
                            $perf_class = 'low'; $perf_label = 'Low';
                            if ($score >= 500) { $perf_class = 'excellent'; $perf_label = 'Excellent'; }
                            elseif ($score >= 200) { $perf_class = 'good'; $perf_label = 'Good'; }
                            elseif ($score >= 50) { $perf_class = 'average'; $perf_label = 'Average'; }
                            
                            $max_score = !empty($performance) ? max(array_column($performance, 'performance_score')) : 1;
                            if ($max_score == 0) $max_score = 1;
                            $progress_pct = ($score / $max_score) * 100;
                        ?>
                            <tr>
                                <td style="text-align:center;">
                                    <span class="rank-badge <?= $rank_class ?>"><?= $rank++ ?></span>
                                </td>
                                <td class="searchable-cell">
                                    <div class="emp-cell">
                                        <div class="emp-avatar"><?= htmlspecialchars($initials) ?></div>
                                        <div class="emp-info">
                                            <span class="emp-name"><?= htmlspecialchars($p['full_name']) ?></span>
                                            <span class="emp-meta">
                                                <i class="fas fa-envelope"></i>
                                                <?= htmlspecialchars($p['email'] ?? 'N/A') ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td style="text-align:center;" class="searchable-cell">
                                    <span class="role-tag <?= htmlspecialchars($p['role']) ?>">
                                        <?= strtoupper($p['role']) ?>
                                    </span>
                                </td>
                                <td class="searchable-cell">
                                    <span style="font-size:0.7rem;color:var(--primary);font-weight:600;">
                                        <i class="fas fa-store-alt"></i> <?= htmlspecialchars($p['branch_name']) ?>
                                    </span>
                                </td>
                                <td style="text-align:center;">
                                    <?php if (!empty($p['is_online'])): ?>
                                        <span style="font-size:0.7rem;color:var(--success);font-weight:700;">
                                            <span class="status-dot online"></span>Online
                                        </span>
                                    <?php else: ?>
                                        <span style="font-size:0.7rem;color:var(--text-secondary);font-weight:700;">
                                            <span class="status-dot offline"></span>Offline
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;font-weight:800;color:var(--primary);font-family:var(--font-mono);">
                                    <?= number_format($p['transactions']) ?>
                                    <div style="font-size:0.58rem;color:var(--text-secondary);font-weight:600;font-family:var(--font-primary);">
                                        <?= $p['metric_label'] ?>
                                    </div>
                                </td>
                                <td style="text-align:center;font-weight:700;color:var(--purple);font-family:var(--font-mono);" class="searchable-cell">
                                    <?= number_format($p['activities']) ?>
                                </td>
                                <td class="money-cell">
                                    <span class="currency-prefix"><?= $currency ?></span><?= formatMoney($p['revenue']) ?>
                                </td>
                                <td style="text-align:center;">
                                    <span class="score-badge <?= $perf_class ?>">
                                        <?= number_format($score, 0) ?>
                                    </span>
                                </td>
                                <td style="text-align:center;min-width:120px;">
                                    <div style="background:var(--border-color);height:8px;border-radius:4px;overflow:hidden;">
                                        <div style="background:linear-gradient(90deg,#0B5ED7,#3B82F6);height:100%;width:<?= $progress_pct ?>%;border-radius:4px;transition:width 0.5s ease;"></div>
                                    </div>
                                    <div style="font-size:0.6rem;color:var(--text-secondary);margin-top:3px;font-weight:700;">
                                        <?= $perf_label ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" style="text-align:center;padding:50px 20px;color:var(--text-secondary);">
                                <i class="fas fa-users" style="font-size:2.5rem;opacity:0.3;display:block;margin-bottom:12px;"></i>
                                <p style="font-weight:600;">No employees found</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ROLE SUMMARY -->
    <div class="table-card">
        <div class="table-header">
            <span class="title"><i class="fas fa-chart-pie"></i> Performance by Role</span>
            <span class="count"><?= count($role_summary) ?> roles</span>
        </div>
        <div class="table-scroll-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Role</th>
                        <th style="text-align:center;">Employees</th>
                        <th style="text-align:center;">Total Transactions</th>
                        <th style="text-align:right;">Total Revenue</th>
                        <th style="text-align:right;">Avg per Employee</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($role_summary as $role => $data): 
                        $avg_trans = $data['count'] > 0 ? $data['transactions'] / $data['count'] : 0;
                    ?>
                        <tr>
                            <td>
                                <span class="role-tag <?= htmlspecialchars($role) ?>">
                                    <i class="fas fa-user-tag"></i> <?= strtoupper($role) ?>
                                </span>
                            </td>
                            <td style="text-align:center;font-weight:700;font-family:var(--font-mono);">
                                <?= number_format($data['count']) ?>
                            </td>
                            <td style="text-align:center;font-weight:700;color:var(--primary);font-family:var(--font-mono);">
                                <?= number_format($data['transactions']) ?>
                            </td>
                            <td class="money-cell">
                                <span class="currency-prefix"><?= $currency ?></span><?= formatMoney($data['revenue']) ?>
                            </td>
                            <td class="money-cell" style="color:var(--purple);">
                                <span class="currency-prefix"><?= $currency ?></span><?= number_format($avg_trans, 1) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<script>
function filterTable(tableId, searchTerm, countId) {
    var table = document.getElementById(tableId);
    if (!table) return;
    var tbody = table.querySelector('tbody');
    if (!tbody) return;
    var rows = tbody.querySelectorAll('tr');
    var term = searchTerm.trim().toLowerCase();
    var visibleCount = 0;
    
    var searchBox = document.getElementById(tableId.replace('Table', 'SearchBox'));
    if (searchBox) searchBox.classList.toggle('has-value', term.length > 0);
    
    rows.forEach(function(row) {
        if (row.querySelector('td[colspan]')) return;
        var rowText = '';
        var cells = row.querySelectorAll('.searchable-cell');
        if (cells.length > 0) {
            cells.forEach(function(cell) { rowText += ' ' + cell.textContent; });
        } else {
            rowText = row.textContent;
        }
        rowText = rowText.toLowerCase();
        resetHighlights(row);
        
        if (term === '' || rowText.indexOf(term) !== -1) {
            row.classList.remove('hidden-row');
            visibleCount++;
            if (term !== '') highlightMatches(row, term);
        } else {
            row.classList.add('hidden-row');
        }
    });
    
    var countEl = document.getElementById(countId);
    if (countEl) {
        var countText = countEl.querySelector('.count-text');
        var icon = countEl.querySelector('i');
        if (term === '') {
            countEl.className = 'search-count';
            if (icon) icon.className = 'fas fa-list';
            if (countText) countText.textContent = visibleCount + ' records';
        } else if (visibleCount > 0) {
            countEl.className = 'search-count has-results';
            if (icon) icon.className = 'fas fa-check-circle';
            if (countText) countText.textContent = visibleCount + ' found';
        } else {
            countEl.className = 'search-count no-results';
            if (icon) icon.className = 'fas fa-times-circle';
            if (countText) countText.textContent = 'No results';
        }
    }
}

function highlightMatches(row, term) {
    var cells = row.querySelectorAll('.searchable-cell');
    cells.forEach(function(cell) {
        var tempDiv = document.createElement('div');
        tempDiv.innerHTML = cell.innerHTML;
        var walker = document.createTreeWalker(tempDiv, NodeFilter.SHOW_TEXT, null, false);
        var textNodes = [];
        var node;
        while (node = walker.nextNode()) textNodes.push(node);
        
        textNodes.forEach(function(textNode) {
            var text = textNode.nodeValue;
            var lowerText = text.toLowerCase();
            var lowerTerm = term.toLowerCase();
            if (lowerText.indexOf(lowerTerm) === -1) return;
            var fragments = [];
            var lastIndex = 0;
            var index;
            while ((index = lowerText.indexOf(lowerTerm, lastIndex)) !== -1) {
                if (index > lastIndex) fragments.push(document.createTextNode(text.substring(lastIndex, index)));
                var mark = document.createElement('mark');
                mark.className = 'search-highlight';
                mark.textContent = text.substring(index, index + term.length);
                fragments.push(mark);
                lastIndex = index + term.length;
            }
            if (lastIndex < text.length) fragments.push(document.createTextNode(text.substring(lastIndex)));
            if (fragments.length > 0) {
                var parent = textNode.parentNode;
                fragments.forEach(function(frag) { parent.insertBefore(frag, textNode); });
                parent.removeChild(textNode);
            }
        });
        cell.innerHTML = tempDiv.innerHTML;
    });
}

function resetHighlights(row) {
    var marks = row.querySelectorAll('mark.search-highlight');
    marks.forEach(function(mark) {
        var parent = mark.parentNode;
        parent.replaceChild(document.createTextNode(mark.textContent), mark);
        parent.normalize();
    });
}

function clearSearch(tableId, inputId, countId) {
    var input = document.getElementById(inputId);
    if (input) {
        input.value = '';
        filterTable(tableId, '', countId);
        input.focus();
    }
}

function scrollTable(wrapperId, direction) {
    var wrapper = document.getElementById(wrapperId);
    if (!wrapper) return;
    wrapper.scrollBy({ left: direction === 'left' ? -300 : 300, behavior: 'smooth' });
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        var activeInput = document.activeElement;
        if (activeInput && activeInput.id && activeInput.id.indexOf('Search') !== -1) {
            var tableId = activeInput.id.replace('Search', 'Table');
            var countId = activeInput.id.replace('Search', 'Count');
            clearSearch(tableId, activeInput.id, countId);
        }
    }
});

console.log('%c👥 Employee Performance Report - AUDIT', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
console.log('%c✅ AUDIT ROLE', 'font-size:13px; color:#34D399; font-weight:bold;');
console.log('%c👥 Total Employees: <?= $total_employees ?>', 'font-size:13px; color:#0B5ED7;');
console.log('%c✅ Online: <?= $total_online ?>', 'font-size:13px; color:#059669;');
console.log('%c💰 Total Revenue: <?= $currency ?> <?= number_format($total_revenue, 0) ?>', 'font-size:13px; color:#059669;');
</script>

</body>
</html>