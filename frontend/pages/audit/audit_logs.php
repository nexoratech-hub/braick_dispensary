<?php
// ================================================================
// FILE: frontend/pages/audit/audit_logs.php
// AUDIT - SYSTEM ACTIVITY LOGS REPORT (V3 - BRANCH LOCKED)
// ✅ Branch LOCKED kwa user's branch (audit hawezi kubadilisha)
// ✅ Activity logs zote za system (branch yake pekee)
// ✅ DELETE single log
// ✅ DELETE ALL (bulk delete with filters)
// ✅ Filter by user, action, date
// ✅ Fonts: Inter + JetBrains Mono
// ✅ Blue theme: #0B5ED7
// ✅ PDF Export
// ✅ Namba kamili
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

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

// ✅ AUDIT: Branch LOCKED to user's branch
$selected_branch_id = (int)$user_branch_id;

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

// ================================================================
// HANDLE DELETE ACTIONS
// ================================================================
$alert_message = '';
$alert_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // DELETE SINGLE LOG - ✅ BRANCH LOCKED
    if ($_POST['action'] === 'delete_log' && !empty($_POST['log_id'])) {
        try {
            $log_id = (int)$_POST['log_id'];
            // ✅ Ensure log belongs to user's branch
            $stmt = $db->prepare("SELECT action FROM activity_logs WHERE id = ? AND branch_id = ?");
            $stmt->execute([$log_id, $selected_branch_id]);
            $log = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($log) {
                $db->prepare("DELETE FROM activity_logs WHERE id = ? AND branch_id = ?")->execute([$log_id, $selected_branch_id]);
                $alert_message = "Log #$log_id deleted successfully!";
                $alert_type = 'success';
            } else {
                $alert_message = "Log not found or access denied!";
                $alert_type = 'error';
            }
        } catch (Exception $e) {
            $alert_message = "Error deleting log: " . $e->getMessage();
            $alert_type = 'error';
        }
    }
    
    // DELETE ALL (with filters) - ✅ BRANCH LOCKED
    if ($_POST['action'] === 'delete_all_logs') {
        try {
            $del_quick = $_POST['del_quick'] ?? 'all';
            $del_user_id = $_POST['del_user_id'] ?? 'all';
            $del_role = $_POST['del_role'] ?? 'all';
            $del_action = $_POST['del_action'] ?? 'all';
            $del_date_from = $_POST['del_date_from'] ?? date('Y-m-d');
            $del_date_to = $_POST['del_date_to'] ?? date('Y-m-d');
            
            // ✅ BRANCH LOCKED - Always use user's branch
            $where = ["al.branch_id = ?"];
            $params = [$selected_branch_id];
            
            // Date filter
            switch ($del_quick) {
                case 'today': $where[] = "DATE(al.created_at) = CURDATE()"; break;
                case '1d': $where[] = "al.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)"; break;
                case '1w': $where[] = "al.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"; break;
                case '1m': $where[] = "al.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)"; break;
                case '3m': $where[] = "al.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)"; break;
                case '6m': $where[] = "al.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)"; break;
                case '1y': $where[] = "al.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)"; break;
                case 'all': /* no date filter */ break;
                case 'custom':
                    $where[] = "DATE(al.created_at) BETWEEN ? AND ?";
                    $params[] = $del_date_from;
                    $params[] = $del_date_to;
                    break;
            }
            
            // User filter
            if ($del_user_id !== 'all' && is_numeric($del_user_id)) {
                $where[] = "al.user_id = ?";
                $params[] = (int)$del_user_id;
            }
            
            // Action filter
            if ($del_action !== 'all') {
                $where[] = "al.action = ?";
                $params[] = $del_action;
            }
            
            // Role filter (needs JOIN)
            $join_sql = "";
            if ($del_role !== 'all') {
                $join_sql = " LEFT JOIN users u ON al.user_id = u.id ";
                $where[] = "u.role = ?";
                $params[] = $del_role;
            }
            
            $where_sql = implode(" AND ", $where);
            
            $sql = "DELETE al FROM activity_logs al $join_sql WHERE $where_sql";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $deleted_count = $stmt->rowCount();
            
            $alert_message = "Successfully deleted $deleted_count log(s)!";
            $alert_type = 'success';
        } catch (Exception $e) {
            $alert_message = "Error deleting logs: " . $e->getMessage();
            $alert_type = 'error';
        }
    }
}

// ================================================================
// ✅ BRANCH LOCKED - Always filter by user's branch
// ================================================================
$branch_cond = " AND al.branch_id = ?";
$branch_params = [$selected_branch_id];

// BRANCH INFO
$display_branch_name = $user_branch_name;
try {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$selected_branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) $display_branch_name = $branch_data['name'];
} catch (Exception $e) {}

// BRANCHES LIST - Kwa display tu (haipo kwenye filter kwa sababu branch imelock)
$branches = [];
try {
    $stmt = $db->prepare("SELECT id, name FROM branches WHERE id = ?");
    $stmt->execute([$selected_branch_id]);
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// FILTERS
$quick_filter = $_GET['quick'] ?? '1m';
$date_from = $_GET['date_from'] ?? date('Y-m-d');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$user_filter = $_GET['user_id'] ?? 'all';
$action_filter = $_GET['action'] ?? 'all';
$role_filter = $_GET['role'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$date_label = "";
$date_cond = "";
$date_params = [];

switch ($quick_filter) {
    case 'today':
        $date_cond = " AND DATE(al.created_at) = CURDATE()";
        $date_label = "Today";
        break;
    case '1d':
        $date_cond = " AND al.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
        $date_label = "Last 1 Day";
        break;
    case '1w':
        $date_cond = " AND al.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $date_label = "Last 1 Week";
        break;
    case '1m':
        $date_cond = " AND al.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        $date_label = "Last 1 Month";
        break;
    case '3m':
        $date_cond = " AND al.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
        $date_label = "Last 3 Months";
        break;
    case '6m':
        $date_cond = " AND al.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
        $date_label = "Last 6 Months";
        break;
    case '1y':
        $date_cond = " AND al.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        $date_label = "Last 1 Year";
        break;
    case 'all':
        $date_label = "All Time";
        break;
    case 'custom':
        $date_cond = " AND DATE(al.created_at) BETWEEN ? AND ?";
        $date_params = [$date_from, $date_to];
        $date_label = date('d M Y', strtotime($date_from)) . ' - ' . date('d M Y', strtotime($date_to));
        break;
    default:
        $date_cond = " AND al.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        $date_label = "Last 1 Month";
}

$user_cond = "";
$user_params = [];
if ($user_filter !== 'all' && is_numeric($user_filter)) {
    $user_cond = " AND al.user_id = ?";
    $user_params = [(int)$user_filter];
}

$action_cond = "";
$action_params = [];
if ($action_filter !== 'all') {
    $action_cond = " AND al.action = ?";
    $action_params = [$action_filter];
}

$role_cond = "";
$role_params = [];
if ($role_filter !== 'all') {
    $role_cond = " AND u.role = ?";
    $role_params = [$role_filter];
}

$search_cond = "";
$search_params = [];
if (!empty($search)) {
    $search_cond = " AND (al.action LIKE ? OR al.details LIKE ? OR u.full_name LIKE ? OR u.username LIKE ? OR al.ip_address LIKE ?)";
    $search_params = ["%$search%", "%$search%", "%$search%", "%$search%", "%$search%"];
}

// ================================================================
// AUDIT LOGS QUERY - ✅ BRANCH LOCKED
// ================================================================
$logs = [];
try {
    $sql = "SELECT al.id, al.user_id, al.branch_id, al.action, al.details,
                   al.ip_address, al.created_at,
                   u.full_name as user_name, u.username, u.role as user_role,
                   u.profile_pic, u.email, u.phone,
                   b.name as branch_name
            FROM activity_logs al
            LEFT JOIN users u ON al.user_id = u.id
            LEFT JOIN branches b ON al.branch_id = b.id
            WHERE 1=1
            $branch_cond $date_cond $user_cond $action_cond $role_cond $search_cond
            ORDER BY al.created_at DESC
            LIMIT 500";
    $stmt = $db->prepare($sql);
    $all_params = array_merge($branch_params, $date_params, $user_params, $action_params, $role_params, $search_params);
    $stmt->execute($all_params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $logs = []; }

// STATISTICS
$total_logs = count($logs);
$today_logs = 0;
$unique_users = [];
$unique_actions = [];
$unique_ips = [];

foreach ($logs as $log) {
    if (date('Y-m-d', strtotime($log['created_at'])) === date('Y-m-d')) $today_logs++;
    if (!empty($log['user_id'])) $unique_users[$log['user_id']] = true;
    if (!empty($log['action'])) $unique_actions[$log['action']] = true;
    if (!empty($log['ip_address'])) $unique_ips[$log['ip_address']] = true;
}

$total_unique_users = count($unique_users);
$total_unique_actions = count($unique_actions);
$total_unique_ips = count($unique_ips);

// TOP ACTIONS SUMMARY
$action_summary = [];
foreach ($logs as $log) {
    $act = $log['action'] ?? 'unknown';
    if (!isset($action_summary[$act])) $action_summary[$act] = 0;
    $action_summary[$act]++;
}
arsort($action_summary);
$top_actions = array_slice($action_summary, 0, 10, true);

// TOP USERS SUMMARY
$user_summary = [];
foreach ($logs as $log) {
    $uid = $log['user_id'] ?? 0;
    $uname = $log['user_name'] ?? 'Unknown';
    if (!isset($user_summary[$uid])) {
        $user_summary[$uid] = [
            'name' => $uname,
            'role' => $log['user_role'] ?? 'user',
            'count' => 0
        ];
    }
    $user_summary[$uid]['count']++;
}
uasort($user_summary, function($a, $b) { return $b['count'] <=> $a['count']; });
$top_users = array_slice($user_summary, 0, 10, true);

// ✅ GET USERS LIST - ONLY FROM USER'S BRANCH
$users_list = [];
try {
    $stmt = $db->prepare("SELECT id, full_name, role FROM users WHERE status = 'active' AND branch_id = ? ORDER BY full_name");
    $stmt->execute([$selected_branch_id]);
    $users_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ✅ GET ACTIONS LIST - ONLY FROM USER'S BRANCH
$actions_list = [];
try {
    $stmt = $db->prepare("SELECT DISTINCT action FROM activity_logs WHERE branch_id = ? ORDER BY action");
    $stmt->execute([$selected_branch_id]);
    $actions_list = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

// PDF DATA
$pdf_data = [
    'currency' => $currency,
    'branchName' => $display_branch_name,
    'filterLabel' => $date_label,
    'auditName' => $user_full_name,
    'totalLogs' => (int)$total_logs,
    'todayLogs' => (int)$today_logs,
    'uniqueUsers' => (int)$total_unique_users,
    'uniqueActions' => (int)$total_unique_actions,
    'uniqueIPs' => (int)$total_unique_ips,
    'topActions' => array_map(function($k, $v) {
        return ['action' => $k, 'count' => $v];
    }, array_keys($top_actions), array_values($top_actions)),
    'topUsers' => array_values(array_map(function($uid, $u) {
        return ['name' => $u['name'], 'role' => $u['role'], 'count' => $u['count']];
    }, array_keys($top_users), array_values($top_users))),
    'logs' => array_map(function($l) {
        return [
            'user_name' => $l['user_name'] ?? 'System',
            'role' => $l['user_role'] ?? 'system',
            'action' => $l['action'] ?? 'unknown',
            'details' => $l['details'] ?? '',
            'ip_address' => $l['ip_address'] ?? 'N/A',
            'branch' => $l['branch_name'] ?? 'N/A',
            'created_at' => $l['created_at'],
        ];
    }, array_slice($logs, 0, 100)),
];

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

include_once __DIR__ . '/../../components/audit_header.php';
include_once __DIR__ . '/../../components/audit_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs - Braick Audit</title>
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
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
    --danger-dark: #B91C1C;
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
    --shadow-xl: 0 20px 50px rgba(0,0,0,0.15);
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
.money-number, .stat-value, .font-mono, .badge-number, .ip-badge {
    font-family: var(--font-mono) !important;
    font-feature-settings: 'tnum';
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.02em;
}

/* ALERT */
.alert {
    padding: 12px 18px;
    border-radius: 12px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 600;
    font-size: 0.85rem;
    animation: slideDown 0.4s ease;
}
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
.alert.success { background: var(--success-bg); color: var(--success); border-left: 4px solid var(--success); }
.alert.error { background: var(--danger-bg); color: var(--danger); border-left: 4px solid var(--danger); }
.alert i { font-size: 1.1rem; }

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
.branch-tag.locked {
    background: linear-gradient(135deg, #FCD34D, #F59E0B);
    color: #78350F;
    font-weight: 800;
    box-shadow: 0 2px 8px rgba(252, 211, 77, 0.4);
    border-color: rgba(255,255,255,0.2);
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
.btn-delete-all {
    background: linear-gradient(135deg, #991B1B, #7F1D1D) !important;
    font-weight: 700 !important;
    box-shadow: 0 4px 12px rgba(153, 27, 27, 0.4);
}
.btn-delete-all:hover {
    background: linear-gradient(135deg, #DC2626, #991B1B) !important;
    transform: translateY(-2px) scale(1.02);
    box-shadow: 0 8px 24px rgba(220, 38, 38, 0.6);
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
.filter-group input:focus, .filter-group select:focus {
    border-color: var(--primary); box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15);
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

/* BRANCH LOCK BANNER */
.branch-lock-banner {
    background: linear-gradient(135deg, #FEF3C7, #FDE68A);
    border: 2px solid #FCD34D;
    border-left: 4px solid #F59E0B;
    border-radius: 12px;
    padding: 12px 18px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 0.82rem;
    color: #78350F;
    font-weight: 700;
    box-shadow: 0 4px 12px rgba(217, 119, 6, 0.12);
}
.branch-lock-banner i {
    font-size: 1.3rem;
    color: #D97706;
    flex-shrink: 0;
}
.branch-lock-banner .lock-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.branch-lock-banner .lock-title {
    font-size: 0.85rem;
    font-weight: 900;
    display: flex;
    align-items: center;
    gap: 6px;
}
.branch-lock-banner .lock-desc {
    font-size: 0.72rem;
    opacity: 0.85;
    font-weight: 600;
}
[data-theme="dark"] .branch-lock-banner {
    background: linear-gradient(135deg, #3A2A0F, #4A3A12);
    border-color: #78350F;
    color: #FCD34D;
}
[data-theme="dark"] .branch-lock-banner i {
    color: #FBBF24;
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

.user-cell { display: flex; align-items: center; gap: 10px; }
.user-avatar {
    width: 36px; height: 36px; border-radius: 50%;
    background: linear-gradient(135deg, #0B5ED7, #3B82F6);
    color: white; display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: 0.75rem; flex-shrink: 0;
    text-transform: uppercase; font-family: var(--font-mono);
}
.user-info { display: flex; flex-direction: column; gap: 1px; }
.user-name { font-size: 0.78rem; font-weight: 700; color: var(--text-primary); }
.user-meta { font-size: 0.6rem; color: var(--text-secondary); display: flex; align-items: center; gap: 4px; }

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
.role-tag.admin { background: #FCE7F3; color: #BE185D; }
.role-tag.audit { background: #FEF3C7; color: #B45309; }
.role-tag.system { background: var(--border-color); color: var(--text-secondary); }
[data-theme="dark"] .role-tag.doctor { background: #2D1B4E; color: #A78BFA; }
[data-theme="dark"] .role-tag.reception { background: #1E3A8A; color: #93C5FD; }
[data-theme="dark"] .role-tag.pharmacy { background: #1A3A2A; color: #34D399; }
[data-theme="dark"] .role-tag.cashier { background: #78350F; color: #FDE68A; }
[data-theme="dark"] .role-tag.laboratory { background: #0E3A47; color: #67E8F9; }
[data-theme="dark"] .role-tag.admin { background: #4C1D95; color: #FBCFE8; }
[data-theme="dark"] .role-tag.audit { background: #3A2A1A; color: #FBBF24; }

.action-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 10px; border-radius: 8px;
    font-size: 0.62rem; font-weight: 800;
    text-transform: uppercase; white-space: nowrap;
    background: var(--primary-bg); color: var(--primary);
    font-family: var(--font-mono);
}
.action-badge.delete { background: var(--danger-bg); color: var(--danger); }
.action-badge.create { background: var(--success-bg); color: var(--success); }
.action-badge.update { background: var(--warning-bg); color: var(--warning); }
.action-badge.login { background: var(--purple-bg); color: var(--purple); }
.action-badge.view { background: var(--cyan-bg); color: var(--cyan); }
[data-theme="dark"] .action-badge.delete { background: #3A1A1A; color: #F87171; }
[data-theme="dark"] .action-badge.create { background: #1A3A2A; color: #34D399; }
[data-theme="dark"] .action-badge.update { background: #3A2A1A; color: #FBBF24; }
[data-theme="dark"] .action-badge.login { background: #2D1B4E; color: #A78BFA; }
[data-theme="dark"] .action-badge.view { background: #0E3A47; color: #67E8F9; }

.ip-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 8px; border-radius: 6px;
    font-size: 0.62rem; font-weight: 700;
    background: var(--border-color); color: var(--text-secondary);
    font-family: var(--font-mono);
}

.details-cell {
    font-size: 0.72rem;
    color: var(--text-secondary);
    max-width: 350px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.date-cell {
    font-size: 0.68rem;
    font-weight: 600;
    color: var(--text-primary);
    font-family: var(--font-mono);
}
.time-cell {
    font-size: 0.58rem;
    color: var(--text-secondary);
    font-family: var(--font-mono);
}

.btn-action {
    width: 28px;
    height: 28px;
    border-radius: 7px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    border: none;
    cursor: pointer;
    transition: all 0.25s ease;
    text-decoration: none;
}
.btn-action:hover { transform: translateY(-2px) scale(1.1); }
.btn-action.delete {
    background: rgba(220, 38, 38, 0.12);
    color: #DC2626;
}
.btn-action.delete:hover {
    background: #DC2626;
    color: white;
    box-shadow: 0 4px 10px rgba(220, 38, 38, 0.4);
}

.summary-list {
    padding: 14px 18px;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 10px;
}
.summary-item {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 14px; border-radius: 10px;
    background: var(--bg-body);
    border: 1px solid var(--border-color);
    transition: all 0.3s ease;
}
.summary-item:hover {
    background: var(--primary-bg);
    border-color: var(--primary);
    transform: translateX(3px);
}
.summary-item .rank {
    width: 24px; height: 24px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: 0.65rem;
    background: var(--primary-bg); color: var(--primary);
    flex-shrink: 0; font-family: var(--font-mono);
}
.summary-item .rank.gold { background: linear-gradient(135deg, #FCD34D, #F59E0B); color: #78350F; }
.summary-item .rank.silver { background: linear-gradient(135deg, #E5E7EB, #9CA3AF); color: #374151; }
.summary-item .rank.bronze { background: linear-gradient(135deg, #FBBF24, #D97706); color: #78350F; }
.summary-item .label {
    flex: 1; font-size: 0.75rem; font-weight: 700;
    color: var(--text-primary);
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.summary-item .value {
    font-size: 0.85rem; font-weight: 800;
    color: var(--primary);
    font-family: var(--font-mono);
}

/* MODAL */
.modal-overlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(6px);
    z-index: 99999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.modal-overlay.active { display: flex; }
.modal-box {
    background: var(--bg-card);
    border-radius: 20px;
    max-width: 520px;
    width: 100%;
    padding: 30px;
    box-shadow: var(--shadow-xl);
    animation: modalPop 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    text-align: center;
    max-height: 90vh;
    overflow-y: auto;
}
@keyframes modalPop {
    0% { opacity: 0; transform: scale(0.8) translateY(20px); }
    100% { opacity: 1; transform: scale(1) translateY(0); }
}
.modal-icon {
    width: 68px;
    height: 68px;
    border-radius: 50%;
    background: var(--danger-bg);
    color: var(--danger);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    margin: 0 auto 16px;
    animation: iconPulse 1.5s infinite;
}
@keyframes iconPulse {
    0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.4); }
    50% { transform: scale(1.05); box-shadow: 0 0 0 12px rgba(220, 38, 38, 0); }
}
.modal-title {
    font-size: 1.2rem;
    font-weight: 800;
    margin-bottom: 8px;
    color: var(--text-primary);
}
.modal-text {
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin-bottom: 22px;
    line-height: 1.7;
}
.modal-text strong {
    color: var(--primary);
    background: var(--primary-bg);
    padding: 2px 8px;
    border-radius: 6px;
    font-family: var(--font-mono);
    font-weight: 700;
}
.modal-warning {
    color: var(--danger);
    font-weight: 700;
    display: block;
    margin-top: 6px;
    font-size: 0.78rem;
}
.modal-actions {
    display: flex;
    gap: 10px;
    justify-content: center;
}
.modal-btn {
    padding: 10px 24px;
    border-radius: 11px;
    font-weight: 700;
    font-size: 0.82rem;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.modal-btn.cancel { background: var(--border-color); color: var(--text-primary); }
.modal-btn.cancel:hover { background: #CBD5E1; transform: translateY(-2px); }
.modal-btn.danger { background: linear-gradient(135deg, #DC2626, #B91C1C); color: white; }
.modal-btn.danger:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(220, 38, 38, 0.5); }

.modal-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-bottom: 20px;
    text-align: left;
}
.modal-form-grid .full-width { grid-column: 1 / -1; }
.modal-form-grid label {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--text-secondary);
    display: block;
    margin-bottom: 4px;
}
.modal-form-grid select,
.modal-form-grid input {
    width: 100%;
    padding: 8px 12px;
    border-radius: 8px;
    border: 2px solid var(--border-color);
    background: var(--bg-card);
    color: var(--text-primary);
    font-size: 0.78rem;
    font-weight: 600;
    outline: none;
    height: 38px;
}

@media (max-width: 1200px) { .stats-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 1024px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 768px) {
    .page-header { padding: 16px 18px; }
    .page-header .page-title { font-size: 1.15rem; }
    .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
    .stat-card { padding: 12px; min-height: 115px; }
    .data-table { font-size: 0.7rem; }
    .data-table thead th, .data-table tbody td { padding: 7px 8px; }
    .details-cell { max-width: 150px; }
    .modal-form-grid { grid-template-columns: 1fr; }
}
@media (max-width: 480px) { .stats-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<script id="pdfDataScript" type="application/json">
<?= json_encode($pdf_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>

<main class="main-content">

    <!-- ALERT -->
    <?php if (!empty($alert_message)): ?>
        <div class="alert <?= $alert_type ?>" id="alertBox">
            <i class="fas fa-<?= $alert_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
            <span><?= htmlspecialchars($alert_message) ?></span>
        </div>
        <script>
            setTimeout(function() {
                var el = document.getElementById('alertBox');
                if (el) { el.style.transition = 'all 0.5s ease'; el.style.opacity = '0'; setTimeout(function() { el.remove(); }, 500); }
            }, 4000);
        </script>
    <?php endif; ?>

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-history"></i>
                Audit Logs
                <span class="branch-tag"><i class="fas fa-user-shield"></i> AUDIT</span>
                <span class="branch-tag locked"><i class="fas fa-lock"></i> <?= htmlspecialchars($display_branch_name) ?></span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-store-alt"></i>
                <strong><?= htmlspecialchars($display_branch_name) ?></strong>
                <span class="branch-tag">
                    <i class="fas fa-list"></i> <?= number_format($total_logs) ?> Logs
                </span>
                <span class="branch-tag">
                    <i class="fas fa-calendar-day"></i> <?= number_format($today_logs) ?> Today
                </span>
                <span class="branch-tag">
                    <i class="fas fa-users"></i> <?= number_format($total_unique_users) ?> Users
                </span>
                <span class="branch-tag filter-tag">
                    <i class="fas fa-filter"></i> <?= htmlspecialchars($date_label) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <button onclick="openDeleteAllModal()" class="btn-header btn-delete-all">
                <i class="fas fa-trash-alt"></i> Delete All
            </button>
            <button onclick="openPDFWindow()" class="btn-header btn-pdf-export">
                <i class="fas fa-file-pdf"></i> PDF
            </button>
            <button onclick="window.print()" class="btn-header">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="/dispensary_system/frontend/pages/audit/dashboard.php" 
               class="btn-header">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- ✅ BRANCH LOCK BANNER -->
    <div class="branch-lock-banner">
        <i class="fas fa-lock"></i>
        <div class="lock-info">
            <div class="lock-title">
                <i class="fas fa-shield-alt"></i> Branch Access Locked
            </div>
            <div class="lock-desc">
                Unatuma na kuona Activity Logs za <strong><?= htmlspecialchars($display_branch_name) ?></strong> pekee. 
                Logs za branches nyingine hazipatikani.
            </div>
        </div>
    </div>

    <!-- FILTER CARD -->
    <div class="filter-card">
        <div class="filter-section">
            <div class="filter-section-title">
                <i class="fas fa-bolt"></i> Quick Date Filters
            </div>
            <div class="quick-filters">
                <a href="?quick=today" class="quick-btn <?= $quick_filter === 'today' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-day"></i> Today
                </a>
                <a href="?quick=1d" class="quick-btn <?= $quick_filter === '1d' ? 'active' : '' ?>">
                    <i class="fas fa-clock"></i> 1D
                </a>
                <a href="?quick=1w" class="quick-btn <?= $quick_filter === '1w' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-week"></i> 1W
                </a>
                <a href="?quick=1m" class="quick-btn <?= $quick_filter === '1m' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-alt"></i> 1M
                </a>
                <a href="?quick=3m" class="quick-btn <?= $quick_filter === '3m' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-alt"></i> 3M
                </a>
                <a href="?quick=6m" class="quick-btn <?= $quick_filter === '6m' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-alt"></i> 6M
                </a>
                <a href="?quick=1y" class="quick-btn <?= $quick_filter === '1y' ? 'active' : '' ?>">
                    <i class="fas fa-calendar"></i> 1Y
                </a>
                <a href="?quick=all" class="quick-btn <?= $quick_filter === 'all' ? 'active' : '' ?>">
                    <i class="fas fa-infinity"></i> All
                </a>
                <a href="?quick=custom&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" 
                   class="quick-btn <?= $quick_filter === 'custom' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-check"></i> Custom
                </a>
            </div>
        </div>

        <form method="GET" id="filterForm">
            <div class="filter-form">
                <div class="filter-group">
                    <label><i class="fas fa-filter"></i> Date Range</label>
                    <select name="quick" onchange="this.form.submit()">
                        <option value="today" <?= $quick_filter === 'today' ? 'selected' : '' ?>>Today</option>
                        <option value="1d" <?= $quick_filter === '1d' ? 'selected' : '' ?>>Last 1 Day</option>
                        <option value="1w" <?= $quick_filter === '1w' ? 'selected' : '' ?>>Last 1 Week</option>
                        <option value="1m" <?= $quick_filter === '1m' ? 'selected' : '' ?>>Last 1 Month</option>
                        <option value="3m" <?= $quick_filter === '3m' ? 'selected' : '' ?>>Last 3 Months</option>
                        <option value="6m" <?= $quick_filter === '6m' ? 'selected' : '' ?>>Last 6 Months</option>
                        <option value="1y" <?= $quick_filter === '1y' ? 'selected' : '' ?>>Last 1 Year</option>
                        <option value="all" <?= $quick_filter === 'all' ? 'selected' : '' ?>>All Time</option>
                        <option value="custom" <?= $quick_filter === 'custom' ? 'selected' : '' ?>>Custom Range</option>
                    </select>
                </div>
                
                <?php if ($quick_filter === 'custom'): ?>
                    <div class="filter-group">
                        <label><i class="fas fa-calendar"></i> From</label>
                        <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-calendar"></i> To</label>
                        <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
                    </div>
                <?php endif; ?>
                
                <div class="filter-group">
                    <label><i class="fas fa-user"></i> User (Branch Only)</label>
                    <select name="user_id">
                        <option value="all">All Users</option>
                        <?php foreach ($users_list as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $user_filter == $u['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['full_name']) ?> (<?= $u['role'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label><i class="fas fa-tag"></i> Role</label>
                    <select name="role">
                        <option value="all" <?= $role_filter === 'all' ? 'selected' : '' ?>>All Roles</option>
                        <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="doctor" <?= $role_filter === 'doctor' ? 'selected' : '' ?>>Doctor</option>
                        <option value="reception" <?= $role_filter === 'reception' ? 'selected' : '' ?>>Reception</option>
                        <option value="pharmacy" <?= $role_filter === 'pharmacy' ? 'selected' : '' ?>>Pharmacy</option>
                        <option value="laboratory" <?= $role_filter === 'laboratory' ? 'selected' : '' ?>>Laboratory</option>
                        <option value="cashier" <?= $role_filter === 'cashier' ? 'selected' : '' ?>>Cashier</option>
                        <option value="audit" <?= $role_filter === 'audit' ? 'selected' : '' ?>>Audit</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label><i class="fas fa-cog"></i> Action</label>
                    <select name="action">
                        <option value="all">All Actions</option>
                        <?php foreach ($actions_list as $a): ?>
                            <option value="<?= htmlspecialchars($a) ?>" <?= $action_filter === $a ? 'selected' : '' ?>>
                                <?= htmlspecialchars(str_replace('_', ' ', ucfirst($a))) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="filter-btn-primary">
                    <i class="fas fa-filter"></i> Apply
                </button>
                
                <a href="?" class="filter-btn-secondary">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </div>
        </form>
    </div>

    <!-- SUMMARY STATS -->
    <div class="stats-grid">
        <div class="stat-card blue">
            <div class="stat-icon"><i class="fas fa-list"></i></div>
            <div class="stat-label">Total Logs</div>
            <div class="stat-value">
                <span class="money-number"><?= number_format($total_logs) ?></span>
            </div>
            <div class="stat-sub">
                <i class="fas fa-history"></i> In selected period
            </div>
        </div>
        
        <div class="stat-card green">
            <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
            <div class="stat-label">Today's Logs</div>
            <div class="stat-value">
                <span class="money-number"><?= number_format($today_logs) ?></span>
            </div>
            <div class="stat-sub">
                <i class="fas fa-clock"></i> Logged today
            </div>
        </div>
        
        <div class="stat-card purple">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-label">Active Users</div>
            <div class="stat-value">
                <span class="money-number"><?= number_format($total_unique_users) ?></span>
            </div>
            <div class="stat-sub">
                <i class="fas fa-user-check"></i> Branch users
            </div>
        </div>
        
        <div class="stat-card cyan">
            <div class="stat-icon"><i class="fas fa-cog"></i></div>
            <div class="stat-label">Unique Actions</div>
            <div class="stat-value">
                <span class="money-number"><?= number_format($total_unique_actions) ?></span>
            </div>
            <div class="stat-sub">
                <i class="fas fa-tasks"></i> Different actions
            </div>
        </div>
    </div>

    <!-- TOP ACTIONS + TOP USERS -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:18px;">
        <?php if (count($top_actions) > 0): ?>
        <div class="table-card" style="margin-bottom:0;">
            <div class="table-header">
                <span class="title"><i class="fas fa-chart-bar"></i> Top Actions (Branch)</span>
                <span class="count">Top <?= count($top_actions) ?></span>
            </div>
            <div class="summary-list">
                <?php $rank = 1; foreach ($top_actions as $action => $count): 
                    $rank_class = $rank === 1 ? 'gold' : ($rank === 2 ? 'silver' : ($rank === 3 ? 'bronze' : ''));
                ?>
                    <div class="summary-item">
                        <span class="rank <?= $rank_class ?>"><?= $rank++ ?></span>
                        <span class="label"><?= htmlspecialchars(str_replace('_', ' ', ucfirst($action))) ?></span>
                        <span class="value"><?= number_format($count) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (count($top_users) > 0): ?>
        <div class="table-card" style="margin-bottom:0;">
            <div class="table-header">
                <span class="title"><i class="fas fa-user-check"></i> Most Active Users (Branch)</span>
                <span class="count">Top <?= count($top_users) ?></span>
            </div>
            <div class="summary-list">
                <?php $rank = 1; foreach ($top_users as $uid => $u): 
                    $rank_class = $rank === 1 ? 'gold' : ($rank === 2 ? 'silver' : ($rank === 3 ? 'bronze' : ''));
                ?>
                    <div class="summary-item">
                        <span class="rank <?= $rank_class ?>"><?= $rank++ ?></span>
                        <span class="label">
                            <?= htmlspecialchars($u['name']) ?>
                            <span class="role-tag <?= htmlspecialchars($u['role']) ?>" style="font-size:0.5rem;padding:1px 6px;margin-left:4px;">
                                <?= strtoupper($u['role']) ?>
                            </span>
                        </span>
                        <span class="value"><?= number_format($u['count']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- AUDIT LOGS TABLE -->
    <div class="table-card">
        <div class="table-header">
            <span class="title"><i class="fas fa-history"></i> Activity Logs - <?= htmlspecialchars($display_branch_name) ?></span>
            <span class="count"><?= count($logs) ?> records</span>
        </div>
        
        <div class="table-toolbar">
            <div class="table-toolbar-left">
                <div class="search-box" id="logSearchBox">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="logSearch" 
                           placeholder="Search action, details, user, IP..."
                           oninput="filterTable('logTable', this.value, 'logCount')"
                           autocomplete="off">
                    <button type="button" class="search-clear" onclick="clearSearch('logTable', 'logSearch', 'logCount')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <span class="search-count" id="logCount">
                    <i class="fas fa-list"></i>
                    <span class="count-text"><?= count($logs) ?> records</span>
                </span>
            </div>
            <div class="table-toolbar-right">
                <button type="button" class="scroll-btn" onclick="scrollTable('logWrapper', 'left')">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button type="button" class="scroll-btn" onclick="scrollTable('logWrapper', 'right')">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
        
        <div class="table-scroll-wrapper" id="logWrapper">
            <table class="data-table" id="logTable" style="min-width:1500px;">
                <thead>
                    <tr>
                        <th style="width:50px;">#</th>
                        <th>User</th>
                        <th style="text-align:center;">Role</th>
                        <th style="text-align:center;">Action</th>
                        <th>Details</th>
                        <th>Branch</th>
                        <th>IP Address</th>
                        <th>Date & Time</th>
                        <th style="text-align:center;width:80px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($logs) > 0): ?>
                        <?php $row_num = 1; foreach ($logs as $log): 
                            $u_name = $log['user_name'] ?? 'System';
                            $u_role = strtolower($log['user_role'] ?? 'system');
                            
                            $name_parts = explode(' ', trim($u_name));
                            $initials = strtoupper(substr($u_name, 0, 1));
                            if (count($name_parts) >= 2) {
                                $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1));
                            }
                            
                            $action = $log['action'] ?? 'unknown';
                            $action_lower = strtolower($action);
                            
                            $act_class = 'action-badge';
                            if (strpos($action_lower, 'delete') !== false || strpos($action_lower, 'remove') !== false) {
                                $act_class .= ' delete';
                            } elseif (strpos($action_lower, 'create') !== false || strpos($action_lower, 'add') !== false) {
                                $act_class .= ' create';
                            } elseif (strpos($action_lower, 'update') !== false || strpos($action_lower, 'edit') !== false) {
                                $act_class .= ' update';
                            } elseif (strpos($action_lower, 'login') !== false || strpos($action_lower, 'logout') !== false) {
                                $act_class .= ' login';
                            } elseif (strpos($action_lower, 'view') !== false) {
                                $act_class .= ' view';
                            }
                            
                            $action_display = ucwords(str_replace('_', ' ', $action));
                        ?>
                            <tr>
                                <td style="text-align:center;font-weight:700;color:var(--text-secondary);font-family:var(--font-mono);"><?= $row_num++ ?></td>
                                <td class="searchable-cell">
                                    <div class="user-cell">
                                        <div class="user-avatar"><?= htmlspecialchars($initials) ?></div>
                                        <div class="user-info">
                                            <span class="user-name"><?= htmlspecialchars($u_name) ?></span>
                                            <?php if (!empty($log['username'])): ?>
                                                <span class="user-meta">
                                                    <i class="fas fa-at"></i><?= htmlspecialchars($log['username']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td style="text-align:center;" class="searchable-cell">
                                    <span class="role-tag <?= htmlspecialchars($u_role) ?>">
                                        <?= strtoupper($u_role) ?>
                                    </span>
                                </td>
                                <td style="text-align:center;" class="searchable-cell">
                                    <span class="<?= $act_class ?>">
                                        <?= htmlspecialchars($action_display) ?>
                                    </span>
                                </td>
                                <td class="searchable-cell">
                                    <div class="details-cell" title="<?= htmlspecialchars($log['details'] ?? '') ?>">
                                        <?= htmlspecialchars($log['details'] ?? '-') ?>
                                    </div>
                                </td>
                                <td class="searchable-cell">
                                    <span style="font-size:0.7rem;color:var(--primary);font-weight:600;">
                                        <i class="fas fa-store-alt"></i> <?= htmlspecialchars($log['branch_name'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td class="searchable-cell">
                                    <span class="ip-badge">
                                        <i class="fas fa-network-wired"></i>
                                        <?= htmlspecialchars($log['ip_address'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="date-cell"><?= date('d M Y', strtotime($log['created_at'])) ?></div>
                                    <div class="time-cell"><?= date('H:i:s', strtotime($log['created_at'])) ?></div>
                                </td>
                                <td style="text-align:center;">
                                    <button type="button" class="btn-action delete" 
                                            title="Delete Log"
                                            onclick="confirmDelete(<?= $log['id'] ?>, '<?= htmlspecialchars(addslashes($action_display)) ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align:center;padding:50px 20px;color:var(--text-secondary);">
                                <i class="fas fa-history" style="font-size:2.5rem;opacity:0.3;display:block;margin-bottom:12px;"></i>
                                <p style="font-weight:600;">No activity logs found for <?= htmlspecialchars($display_branch_name) ?></p>
                                <p style="font-size:0.75rem;opacity:0.8;">Try changing filters</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<!-- DELETE SINGLE MODAL -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box">
        <div class="modal-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h3 class="modal-title">Delete Log?</h3>
        <p class="modal-text">
            Are you sure you want to delete this log?<br>
            <strong id="deleteActionName">#</strong><br>
            <span class="modal-warning">
                <i class="fas fa-exclamation-circle"></i> This action cannot be undone!
            </span>
        </p>
        <form method="POST" id="deleteForm">
            <input type="hidden" name="action" value="delete_log">
            <input type="hidden" name="log_id" id="deleteLogId" value="">
            <div class="modal-actions">
                <button type="button" class="modal-btn cancel" onclick="closeDeleteModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="modal-btn danger">
                    <i class="fas fa-trash"></i> Yes, Delete
                </button>
            </div>
        </form>
    </div>
</div>

<!-- DELETE ALL MODAL -->
<div class="modal-overlay" id="deleteAllModal">
    <div class="modal-box">
        <div class="modal-icon">
            <i class="fas fa-trash-alt"></i>
        </div>
        <h3 class="modal-title">Delete All Logs?</h3>
        <p class="modal-text">
            This will delete <strong>ALL logs matching the filters</strong> below.<br>
            <span class="modal-warning">
                <i class="fas fa-lock"></i> Branch: <strong><?= htmlspecialchars($display_branch_name) ?></strong> pekee<br>
                <i class="fas fa-exclamation-circle"></i> This action CANNOT be undone!
            </span>
        </p>
        <form method="POST" id="deleteAllForm">
            <input type="hidden" name="action" value="delete_all_logs">
            <div class="modal-form-grid">
                <div class="full-width">
                    <label><i class="fas fa-filter"></i> Date Range</label>
                    <select name="del_quick">
                        <option value="today">Today Only</option>
                        <option value="1d">Last 1 Day</option>
                        <option value="1w">Last 1 Week</option>
                        <option value="1m" selected>Last 1 Month</option>
                        <option value="3m">Last 3 Months</option>
                        <option value="6m">Last 6 Months</option>
                        <option value="1y">Last 1 Year</option>
                        <option value="all">ALL TIME (⚠️ Everything)</option>
                    </select>
                </div>
                
                <div>
                    <label><i class="fas fa-user"></i> User</label>
                    <select name="del_user_id">
                        <option value="all">All Users</option>
                        <?php foreach ($users_list as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label><i class="fas fa-tag"></i> Role</label>
                    <select name="del_role">
                        <option value="all">All Roles</option>
                        <option value="admin">Admin</option>
                        <option value="doctor">Doctor</option>
                        <option value="reception">Reception</option>
                        <option value="pharmacy">Pharmacy</option>
                        <option value="laboratory">Laboratory</option>
                        <option value="cashier">Cashier</option>
                        <option value="audit">Audit</option>
                    </select>
                </div>
                
                <div class="full-width">
                    <label><i class="fas fa-cog"></i> Action Type</label>
                    <select name="del_action">
                        <option value="all">All Actions</option>
                        <?php foreach ($actions_list as $a): ?>
                            <option value="<?= htmlspecialchars($a) ?>">
                                <?= htmlspecialchars(str_replace('_', ' ', ucfirst($a))) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="full-width">
                    <label><i class="fas fa-store-alt"></i> Branch (Locked)</label>
                    <select disabled style="opacity:0.7;cursor:not-allowed;background:var(--warning-bg);color:var(--warning);font-weight:800;">
                        <option><?= htmlspecialchars($display_branch_name) ?> (LOCKED)</option>
                    </select>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="modal-btn cancel" onclick="closeDeleteAllModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="modal-btn danger">
                    <i class="fas fa-trash-alt"></i> Yes, Delete All
                </button>
            </div>
        </form>
    </div>
</div>

<script>
var PDF_DATA = {};
try {
    var pdfDataScript = document.getElementById('pdfDataScript');
    if (pdfDataScript) {
        PDF_DATA = JSON.parse(pdfDataScript.textContent);
    }
} catch (e) {
    console.error('Failed to parse PDF data:', e);
}

// ================================================================
// DELETE SINGLE MODAL
// ================================================================
function confirmDelete(logId, actionName) {
    document.getElementById('deleteLogId').value = logId;
    document.getElementById('deleteActionName').textContent = actionName;
    document.getElementById('deleteModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('active');
    document.body.style.overflow = '';
}

// ================================================================
// DELETE ALL MODAL
// ================================================================
function openDeleteAllModal() {
    document.getElementById('deleteAllModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeDeleteAllModal() {
    document.getElementById('deleteAllModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Close modals on outside click
document.querySelectorAll('.modal-overlay').forEach(function(m) {
    m.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
            document.body.style.overflow = '';
        }
    });
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeDeleteModal();
        closeDeleteAllModal();
    }
});

// ================================================================
// SEARCH FILTER
// ================================================================
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
        var html = cell.innerHTML;
        var tempDiv = document.createElement('div');
        tempDiv.innerHTML = html;
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

// ================================================================
// PDF EXPORT
// ================================================================
function openPDFWindow() {
    var data = PDF_DATA;
    if (!data || Object.keys(data).length === 0) {
        alert('PDF data not loaded. Please refresh the page.');
        return;
    }
    
    var now = new Date();
    var dateStr = now.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
    
    var w = window.open('', '_blank', 'width=1200,height=800,scrollbars=yes,resizable=yes');
    if (!w) { alert('Please allow popups to export PDF'); return; }
    
    var html = '<!DOCTYPE html><html><head><meta charset="UTF-8">' +
'<title>Audit Logs - ' + data.branchName + '</title>' +
'<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;600;700;800&display=swap" rel="stylesheet">' +
'<style>' +
'* { margin: 0; padding: 0; box-sizing: border-box; font-family: "Inter", sans-serif; }' +
'body { background: #F1F5F9; padding: 30px 20px; color: #1E293B; line-height: 1.5; }' +
'.container { max-width: 1100px; margin: 0 auto; background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 20px 60px rgba(10, 46, 92, 0.15); }' +
'.pdf-header { background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 100%); padding: 30px 36px; color: white; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 20px; }' +
'.pdf-title { font-size: 1.6rem; font-weight: 800; margin-bottom: 6px; }' +
'.pdf-subtitle { font-size: 0.82rem; color: rgba(255,255,255,0.85); display: flex; gap: 8px; flex-wrap: wrap; }' +
'.pdf-subtitle .tag { background: rgba(255,255,255,0.15); padding: 3px 12px; border-radius: 20px; font-size: 0.7rem; }' +
'.pdf-generated { font-size: 0.68rem; color: rgba(255,255,255,0.7); text-transform: uppercase; letter-spacing: 0.08em; }' +
'.pdf-date { font-size: 0.95rem; font-weight: 700; font-family: "JetBrains Mono", monospace; }' +
'.pdf-body { padding: 32px 36px; }' +
'.section-title { font-size: 1rem; font-weight: 800; color: #0B5ED7; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 3px solid #E8F0FE; }' +
'.summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 28px; }' +
'.summary-card { background: white; border-radius: 14px; padding: 16px 14px; border: 2px solid #E2E8F0; position: relative; overflow: hidden; }' +
'.summary-card::before { content: ""; position: absolute; top: 0; left: 0; right: 0; height: 4px; }' +
'.summary-card.blue::before { background: linear-gradient(90deg, #0B5ED7, #3B82F6); }' +
'.summary-card.green::before { background: linear-gradient(90deg, #059669, #34D399); }' +
'.summary-card.purple::before { background: linear-gradient(90deg, #7C3AED, #A78BFA); }' +
'.summary-card.cyan::before { background: linear-gradient(90deg, #0891B2, #06B6D4); }' +
'.summary-card .card-label { font-size: 0.62rem; color: #64748B; font-weight: 700; text-transform: uppercase; margin-bottom: 4px; }' +
'.summary-card .card-value { font-size: 1.15rem; font-weight: 800; font-family: "JetBrains Mono", monospace; color: #0B5ED7; }' +
'.summary-card.green .card-value { color: #059669; }' +
'.summary-card.purple .card-value { color: #7C3AED; }' +
'.summary-card.cyan .card-value { color: #0891B2; }' +
'.pdf-table { width: 100%; border-collapse: collapse; font-size: 0.72rem; margin-bottom: 28px; }' +
'.pdf-table thead th { text-align: left; padding: 10px 12px; font-weight: 800; font-size: 0.6rem; text-transform: uppercase; color: white; background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }' +
'.pdf-table tbody td { padding: 8px 12px; border-bottom: 1px solid #E2E8F0; }' +
'.pdf-table tbody tr:nth-child(even) td { background: #F8FAFC; }' +
'.pdf-table .qty { font-family: "JetBrains Mono", monospace; font-weight: 800; color: #0B5ED7; text-align: right; }' +
'.pdf-table .time { font-family: "JetBrains Mono", monospace; font-size: 0.65rem; color: #64748B; }' +
'.pdf-footer { background: #F8FAFC; padding: 18px 36px; border-top: 3px solid #E8F0FE; display: flex; justify-content: space-between; font-size: 0.72rem; color: #64748B; }' +
'.action-bar { position: fixed; top: 20px; right: 20px; display: flex; gap: 10px; z-index: 9999; }' +
'.action-btn { padding: 12px 22px; border-radius: 12px; border: none; font-weight: 700; font-size: 0.85rem; cursor: pointer; }' +
'.action-btn.print { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); color: white; }' +
'.action-btn.close { background: white; color: #DC2626; border: 2px solid #DC2626; }' +
'@media print { body { background: white; padding: 0; } .action-bar { display: none !important; } }' +
'</style></head><body>' +
'<div class="action-bar">' +
'<button class="action-btn print" onclick="window.print()">🖨️ Print / Save PDF</button>' +
'<button class="action-btn close" onclick="window.close()">✕ Close</button>' +
'</div>' +
'<div class="container">' +
'<div class="pdf-header">' +
'<div><div class="pdf-title">Audit Logs Report</div>' +
'<div class="pdf-subtitle"><strong>Braick Dispensary</strong>' +
'<span class="tag">📍 ' + data.branchName + '</span>' +
'<span class="tag">📅 ' + data.filterLabel + '</span>' +
'<span class="tag">🔒 AUDIT</span></div></div>' +
'<div style="text-align:right;"><div class="pdf-generated">Generated On</div>' +
'<div class="pdf-date">' + dateStr + ' • ' + timeStr + '</div></div>' +
'</div>' +
'<div class="pdf-body">' +
'<div class="section-title">Summary</div>' +
'<div class="summary-grid">' +
'<div class="summary-card blue"><div class="card-label">Total Logs</div><div class="card-value">' + data.totalLogs.toLocaleString() + '</div></div>' +
'<div class="summary-card green"><div class="card-label">Today</div><div class="card-value">' + data.todayLogs.toLocaleString() + '</div></div>' +
'<div class="summary-card purple"><div class="card-label">Unique Users</div><div class="card-value">' + data.uniqueUsers.toLocaleString() + '</div></div>' +
'<div class="summary-card cyan"><div class="card-label">Unique Actions</div><div class="card-value">' + data.uniqueActions.toLocaleString() + '</div></div>' +
'</div>' +
'<div class="section-title">🏆 Top Actions</div>' +
'<table class="pdf-table"><thead><tr><th>#</th><th>Action</th><th style="text-align:right;">Count</th></tr></thead><tbody>';
    
    if (data.topActions && data.topActions.length > 0) {
        data.topActions.forEach(function(a, i) {
            html += '<tr><td>' + (i + 1) + '</td><td>' + a.action.replace(/_/g, ' ').toUpperCase() + '</td>' +
                '<td class="qty">' + a.count.toLocaleString() + '</td></tr>';
        });
    }
    
    html += '</tbody></table>' +
'<div class="section-title">👥 Most Active Users</div>' +
'<table class="pdf-table"><thead><tr><th>#</th><th>User</th><th>Role</th><th style="text-align:right;">Activities</th></tr></thead><tbody>';
    
    if (data.topUsers && data.topUsers.length > 0) {
        data.topUsers.forEach(function(u, i) {
            html += '<tr><td>' + (i + 1) + '</td><td><strong>' + u.name + '</strong></td>' +
                '<td>' + u.role.toUpperCase() + '</td>' +
                '<td class="qty">' + u.count.toLocaleString() + '</td></tr>';
        });
    }
    
    html += '</tbody></table>' +
'<div class="section-title">📋 Recent Logs (Top 100)</div>' +
'<table class="pdf-table"><thead><tr><th>User</th><th>Action</th><th>Details</th><th>IP</th><th>Date & Time</th></tr></thead><tbody>';
    
    if (data.logs && data.logs.length > 0) {
        data.logs.forEach(function(l) {
            var dt = new Date(l.created_at);
            var dtStr = dt.toLocaleDateString('en-GB') + ' ' + dt.toLocaleTimeString('en-GB', {hour: '2-digit', minute: '2-digit'});
            html += '<tr><td>' + l.user_name + '<br><small style="color:#64748B;">' + l.role.toUpperCase() + '</small></td>' +
                '<td>' + l.action.replace(/_/g, ' ').toUpperCase() + '</td>' +
                '<td>' + (l.details || '-').substring(0, 80) + '</td>' +
                '<td class="time">' + l.ip_address + '</td>' +
                '<td class="time">' + dtStr + '</td></tr>';
        });
    }
    
    html += '</tbody></table>' +
'</div>' +
'<div class="pdf-footer">' +
'<div><strong>Braick Dispensary Management System</strong></div>' +
'<div>👤 ' + data.auditName + ' | 📍 ' + data.branchName + ' | 🕐 ' + timeStr + '</div>' +
'</div></div></body></html>';
    
    w.document.open();
    w.document.write(html);
    w.document.close();
}

console.log('%c📋 Audit Logs V3 (BRANCH LOCKED)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
console.log('%c🔒 Branch: <?= htmlspecialchars($display_branch_name) ?>', 'font-size:13px; color:#D97706; font-weight:bold;');
console.log('%c✅ Audit anaona logs za branch yake pekee', 'font-size:13px; color:#059669; font-weight:bold;');
console.log('%c✅ Delete single log (branch locked)', 'font-size:13px; color:#DC2626; font-weight:bold;');
console.log('%c✅ Delete All (branch locked)', 'font-size:13px; color:#DC2626; font-weight:bold;');
console.log('%c📋 Total Logs: <?= number_format($total_logs) ?>', 'font-size:13px; color:#059669;');
</script>

</body>
</html>