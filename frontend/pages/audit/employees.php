<?php
// ================================================================
// FILE: frontend/pages/audit/employees.php
// AUDIT - EMPLOYEES PERFORMANCE REPORT (V8 - BRANCH LOCKED CLEAN)
// ✅ AUDIT ANAONA BRANCH YAKE TU (HAWEZI KUBADILISHA)
// ✅ ONDOA "Branch ONLY" tag kwenye header
// ✅ ONDOA "Revenue Generated" cards
// ✅ ONDOA "Revenue" column kwenye table
// ✅ ONDOA "Total Revenue" kwenye Role Summary
// ✅ Baki: Total Employees + Activity metrics tu
// ✅ AUDIT ROLE TU
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

// ✅ AUDIT ROLE TU
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

// ✅ AUDIT ANAONA BRANCH YAKE TU - HAWEZI KUBADILISHA
$selected_branch_id = $user_branch_id;

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    $db->exec("SET time_zone = '+03:00'");
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

function formatMoney($amount) {
    if ($amount === null || $amount === '') return '0';
    return number_format((float)$amount, 0, '.', ',');
}

// ✅ BRANCH FILTER - LAZIMISHA BRANCH YA ALIYE LOGIN
$filter_by_branch = true;
$filter_branch_id = (int)$user_branch_id;

$branch_cond_u = " AND u.branch_id = ?";
$branch_params = [$filter_branch_id];

// Chukua jina la branch ya aliye login
$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$display_branch_name = $user_branch_name;
foreach ($branches as $b) {
    if ($b['id'] == $filter_branch_id) { $display_branch_name = $b['name']; break; }
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

// EMPLOYEES LIST - WA BRANCH YA ALIYE LOGIN TU
$employees = [];
try {
    $sql = "SELECT u.id, u.username, u.full_name, u.email, u.phone, u.role,
                   u.branch_id, u.status, u.is_online, u.last_online, u.profile_pic,
                   u.specialty, u.created_at, b.name as branch_name
            FROM users u
            LEFT JOIN branches b ON u.branch_id = b.id
            WHERE u.status = 'active'
            $branch_cond_u
            ORDER BY 
                CASE u.role
                    WHEN 'admin' THEN 1
                    WHEN 'doctor' THEN 2
                    WHEN 'pharmacy' THEN 3
                    WHEN 'laboratory' THEN 4
                    WHEN 'cashier' THEN 5
                    WHEN 'reception' THEN 6
                    WHEN 'audit' THEN 7
                    ELSE 8
                END,
                u.full_name ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute($branch_params);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $employees = []; }

// PERFORMANCE METRICS
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
        'sub_details' => [],
    ];
    
    switch ($role) {
        // DOCTOR
        case 'doctor':
            $doctor_visits = 0;
            $doctor_prescriptions = 0;
            $doctor_bills = 0;
            $doctor_patients = 0;
            
            try {
                $d_params = [$emp_id];
                $dc = buildDateCond($quick_filter, 'v.visit_date', $d_params, $date_from, $date_to);
                $d_params[] = $filter_branch_id;
                $sql = "SELECT COUNT(*) as cnt, COUNT(DISTINCT v.patient_id) as patients
                        FROM visits v WHERE v.doctor_id = ? $dc AND v.branch_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($d_params);
                $r = $stmt->fetch(PDO::FETCH_ASSOC);
                $doctor_visits = (int)($r['cnt'] ?? 0);
                $doctor_patients = (int)($r['patients'] ?? 0);
            } catch (Exception $e) {}
            
            try {
                $d_params = [$emp_id];
                $dc = buildDateCond($quick_filter, 'p.created_at', $d_params, $date_from, $date_to);
                $d_params[] = $filter_branch_id;
                $sql = "SELECT COUNT(*) as cnt FROM prescriptions p WHERE p.doctor_id = ? $dc AND p.branch_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($d_params);
                $doctor_prescriptions = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            } catch (Exception $e) {}
            
            try {
                $d_params = [$emp_id];
                $dc = buildDateCond($quick_filter, 'b.created_at', $d_params, $date_from, $date_to);
                $d_params[] = $filter_branch_id;
                $sql = "SELECT COUNT(*) as cnt FROM bills b WHERE b.created_by = ? $dc AND b.branch_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($d_params);
                $doctor_bills = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            } catch (Exception $e) {}
            
            $perf['transactions'] = $doctor_visits + $doctor_prescriptions + $doctor_bills;
            $perf['patients_handled'] = $doctor_patients;
            $perf['items_processed'] = $doctor_visits;
            $perf['metric_label'] = 'Visits';
            $perf['sub_details'] = [
                'visits' => $doctor_visits,
                'prescriptions' => $doctor_prescriptions,
                'bills' => $doctor_bills,
            ];
            break;
        
        // RECEPTION
        case 'reception':
            $rec_patients = 0;
            $rec_visits = 0;
            
            try {
                $d_params = [$emp_id];
                $dc = buildDateCond($quick_filter, 'p.created_at', $d_params, $date_from, $date_to);
                $d_params[] = $filter_branch_id;
                $sql = "SELECT COUNT(*) as cnt FROM patients p WHERE p.created_by = ? $dc AND p.branch_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($d_params);
                $rec_patients = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            } catch (Exception $e) {}
            
            try {
                $d_params = [$emp_id];
                $dc = buildDateCond($quick_filter, 'v.created_at', $d_params, $date_from, $date_to);
                $d_params[] = $filter_branch_id;
                $sql = "SELECT COUNT(*) as cnt FROM visits v WHERE v.created_by = ? $dc AND v.branch_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($d_params);
                $rec_visits = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            } catch (Exception $e) {}
            
            $perf['transactions'] = $rec_patients + $rec_visits;
            $perf['patients_handled'] = $rec_patients;
            $perf['items_processed'] = $rec_visits;
            $perf['metric_label'] = 'Patients';
            $perf['sub_details'] = [
                'patients' => $rec_patients,
                'visits' => $rec_visits,
            ];
            break;
        
        // CASHIER
        case 'cashier':
            $cashier_payments = 0;
            $cashier_otc = 0;
            
            try {
                $d_params = [$emp_id];
                $dc = buildDateCond($quick_filter, 'p.received_at', $d_params, $date_from, $date_to);
                $d_params[] = $filter_branch_id;
                $sql = "SELECT COUNT(*) as cnt FROM payments p
                        WHERE p.received_by = ? AND p.bill_id IS NOT NULL AND p.amount > 0 $dc AND p.branch_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($d_params);
                $cashier_payments = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            } catch (Exception $e) {}
            
            try {
                $d_params = [$emp_id];
                $dc = buildDateCond($quick_filter, 'o.created_at', $d_params, $date_from, $date_to);
                $d_params[] = $filter_branch_id;
                $sql = "SELECT COUNT(*) as cnt FROM otc_sales o 
                        WHERE o.sold_by = ? AND o.payment_status = 'paid' $dc AND o.branch_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($d_params);
                $cashier_otc = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            } catch (Exception $e) {}
            
            $perf['transactions'] = $cashier_payments + $cashier_otc;
            $perf['items_processed'] = $cashier_payments;
            $perf['metric_label'] = 'Payments';
            $perf['sub_details'] = [
                'payments' => $cashier_payments,
                'otc_sales' => $cashier_otc,
            ];
            break;
        
        // PHARMACY
        case 'pharmacy':
            $pharm_prescriptions = 0;
            $pharm_otc = 0;
            
            try {
                $d_params = [$emp_id];
                $dc = buildDateCond($quick_filter, 'p.dispensed_at', $d_params, $date_from, $date_to);
                $d_params[] = $filter_branch_id;
                $sql = "SELECT COUNT(*) as cnt FROM prescriptions p 
                        WHERE p.dispensed_by = ? AND p.status IN ('dispensed', 'confirmed') $dc AND p.branch_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($d_params);
                $pharm_prescriptions = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            } catch (Exception $e) {}
            
            try {
                $d_params = [$emp_id];
                $dc = buildDateCond($quick_filter, 'o.created_at', $d_params, $date_from, $date_to);
                $d_params[] = $filter_branch_id;
                $sql = "SELECT COUNT(*) as cnt FROM otc_sales o 
                        WHERE o.sold_by = ? AND o.payment_status = 'paid' $dc AND o.branch_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($d_params);
                $pharm_otc = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            } catch (Exception $e) {}
            
            $perf['transactions'] = $pharm_prescriptions + $pharm_otc;
            $perf['items_processed'] = $pharm_prescriptions;
            $perf['metric_label'] = 'Prescriptions';
            $perf['sub_details'] = [
                'prescriptions' => $pharm_prescriptions,
                'otc_sales' => $pharm_otc,
            ];
            break;
        
        // LABORATORY
        case 'laboratory':
            $lab_completed = 0;
            $lab_in_progress = 0;
            
            try {
                $d_params = [$emp_id];
                $dc = buildDateCond($quick_filter, 'lt.completed_at', $d_params, $date_from, $date_to);
                $d_params[] = $filter_branch_id;
                $sql = "SELECT COUNT(*) as cnt FROM lab_tests lt
                        WHERE lt.lab_technician_id = ? AND lt.status = 'completed' $dc AND lt.branch_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($d_params);
                $lab_completed = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            } catch (Exception $e) {}
            
            try {
                $d_params = [$emp_id];
                $dc = buildDateCond($quick_filter, 'lt.created_at', $d_params, $date_from, $date_to);
                $d_params[] = $filter_branch_id;
                $sql = "SELECT COUNT(*) as cnt FROM lab_tests lt 
                        WHERE lt.lab_technician_id = ? AND lt.status IN ('pending', 'in_progress') $dc AND lt.branch_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($d_params);
                $lab_in_progress = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            } catch (Exception $e) {}
            
            $perf['transactions'] = $lab_completed;
            $perf['items_processed'] = $lab_completed;
            $perf['metric_label'] = 'Tests';
            $perf['sub_details'] = [
                'completed' => $lab_completed,
                'in_progress' => $lab_in_progress,
            ];
            break;
        
        // AUDIT
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
        
        // ADMIN
        case 'admin':
            $admin_activities = 0;
            $admin_bills_created = 0;
            $admin_otc_created = 0;
            $admin_payments = 0;
            
            try {
                $d_params = [$emp_id];
                $dc = buildDateCond($quick_filter, 'al.created_at', $d_params, $date_from, $date_to);
                $sql = "SELECT COUNT(*) as cnt FROM activity_logs al WHERE al.user_id = ? $dc";
                $stmt = $db->prepare($sql);
                $stmt->execute($d_params);
                $admin_activities = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            } catch (Exception $e) {}
            
            try {
                $d_params = [$emp_id];
                $dc = buildDateCond($quick_filter, 'b.created_at', $d_params, $date_from, $date_to);
                $d_params[] = $filter_branch_id;
                $sql = "SELECT COUNT(*) as cnt FROM bills b WHERE b.created_by = ? $dc AND b.branch_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($d_params);
                $admin_bills_created = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            } catch (Exception $e) {}
            
            try {
                $d_params = [$emp_id];
                $dc = buildDateCond($quick_filter, 'o.created_at', $d_params, $date_from, $date_to);
                $d_params[] = $filter_branch_id;
                $sql = "SELECT COUNT(*) as cnt FROM otc_sales o WHERE o.sold_by = ? AND o.payment_status = 'paid' $dc AND o.branch_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($d_params);
                $admin_otc_created = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            } catch (Exception $e) {}
            
            try {
                $d_params = [$emp_id];
                $dc = buildDateCond($quick_filter, 'p.received_at', $d_params, $date_from, $date_to);
                $d_params[] = $filter_branch_id;
                $sql = "SELECT COUNT(*) as cnt FROM payments p 
                        WHERE p.received_by = ? AND p.bill_id IS NOT NULL AND p.amount > 0 $dc AND p.branch_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($d_params);
                $admin_payments = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            } catch (Exception $e) {}
            
            $perf['transactions'] = $admin_activities + $admin_bills_created + $admin_otc_created + $admin_payments;
            $perf['activities'] = $admin_activities;
            $perf['items_processed'] = $admin_bills_created + $admin_otc_created;
            $perf['metric_label'] = 'Actions';
            $perf['sub_details'] = [
                'activities' => $admin_activities,
                'bills_created' => $admin_bills_created,
                'otc_created' => $admin_otc_created,
                'payments' => $admin_payments,
            ];
            break;
    }
    
    if ($role !== 'admin' && $role !== 'audit') {
        try {
            $a_params = [$emp_id];
            $ac = buildDateCond($quick_filter, 'al.created_at', $a_params, $date_from, $date_to);
            $sql = "SELECT COUNT(*) as cnt FROM activity_logs al WHERE al.user_id = ? $ac";
            $stmt = $db->prepare($sql);
            $stmt->execute($a_params);
            $perf['activities'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
        } catch (Exception $e) {}
    }
    
    // Performance score: Transactions + Activities (NO revenue)
    $perf['performance_score'] = ($perf['transactions'] * 10) + ($perf['activities'] * 2);
    
    $performance[] = $perf;
}

usort($performance, function($a, $b) {
    return $b['performance_score'] <=> $a['performance_score'];
});

// STATISTICS
$total_employees = count($performance);
$total_transactions = 0;

foreach ($performance as $p) {
    $total_transactions += $p['transactions'];
}

$top_performers = array_slice($performance, 0, 5);

// ROLE SUMMARY
$role_summary = [];
foreach ($performance as $p) {
    $r = $p['role'];
    if (!isset($role_summary[$r])) {
        $role_summary[$r] = ['count' => 0, 'transactions' => 0];
    }
    $role_summary[$r]['count']++;
    $role_summary[$r]['transactions'] += $p['transactions'];
}

// PDF DATA
$pdf_data = [
    'currency' => $currency,
    'branchName' => $display_branch_name,
    'filterLabel' => $date_label,
    'userName' => $user_full_name,
    'totalEmployees' => (int)$total_employees,
    'totalTransactions' => (int)$total_transactions,
    'topPerformers' => array_map(function($p) {
        return [
            'name' => $p['full_name'],
            'role' => $p['role'],
            'transactions' => $p['transactions'],
            'metric_label' => $p['metric_label'],
        ];
    }, $top_performers),
    'performance' => array_map(function($p) {
        return [
            'name' => $p['full_name'],
            'role' => $p['role'],
            'branch' => $p['branch_name'],
            'transactions' => $p['transactions'],
            'activities' => $p['activities'],
            'metric_label' => $p['metric_label'],
            'score' => round($p['performance_score'], 1),
        ];
    }, array_slice($performance, 0, 50)),
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
.branch-tag.audit-tag {
    background: linear-gradient(135deg, #0EA5E9, #0284C7);
    font-weight: 800;
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

/* Stats Grid - 2 cards tu */
.stats-grid {
    display: grid; grid-template-columns: repeat(2, 1fr);
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
    flex-wrap: wrap;
}
.stat-card.blue::before { background: linear-gradient(90deg, #0B5ED7, #3B82F6, #0B5ED7); background-size: 200% 100%; animation: shimmer 3s infinite linear; }
.stat-card.blue .stat-icon { background: linear-gradient(135deg, #0B5ED7, #3B82F6); }
.stat-card.blue .stat-value .money-number { color: var(--primary); }
.stat-card.purple::before { background: linear-gradient(90deg, #7C3AED, #A78BFA, #7C3AED); background-size: 200% 100%; animation: shimmer 3s infinite linear; }
.stat-card.purple .stat-icon { background: linear-gradient(135deg, #7C3AED, #A78BFA); }
.stat-card.purple .stat-value .money-number { color: var(--purple); }
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

/* Top 5 Rank Badges */
.rank-cell { text-align: center; position: relative; padding: 8px 6px !important; }
.rank-1-wrapper { display: inline-flex; flex-direction: column; align-items: center; gap: 3px; }
.rank-1-icon {
    width: 42px; height: 42px; border-radius: 50%;
    background: linear-gradient(135deg, #FFD700, #FFA500, #FF8C00);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem; color: #78350F;
    box-shadow: 0 0 0 3px #FFFBEB, 0 0 0 5px #FCD34D, 0 8px 20px rgba(252, 211, 77, 0.6), 0 0 30px rgba(252, 211, 77, 0.4);
    position: relative;
    animation: goldPulse 2s infinite ease-in-out;
}
.rank-1-icon::after {
    content: '👑';
    position: absolute; top: -12px; right: -8px;
    font-size: 1rem; transform: rotate(15deg);
    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
}
@keyframes goldPulse {
    0%, 100% { 
        transform: scale(1); 
        box-shadow: 0 0 0 3px #FFFBEB, 0 0 0 5px #FCD34D, 0 8px 20px rgba(252, 211, 77, 0.6), 0 0 30px rgba(252, 211, 77, 0.4);
    }
    50% { 
        transform: scale(1.08); 
        box-shadow: 0 0 0 3px #FFFBEB, 0 0 0 5px #FCD34D, 0 12px 28px rgba(252, 211, 77, 0.8), 0 0 40px rgba(252, 211, 77, 0.6);
    }
}
.rank-1-label {
    font-size: 0.55rem; font-weight: 900;
    color: #B45309; text-transform: uppercase;
    letter-spacing: 0.08em;
    background: linear-gradient(135deg, #FEF3C7, #FDE68A);
    padding: 2px 8px; border-radius: 10px;
    border: 1px solid #FCD34D;
    white-space: nowrap;
}
.rank-2-wrapper { display: inline-flex; flex-direction: column; align-items: center; gap: 3px; }
.rank-2-icon {
    width: 40px; height: 40px; border-radius: 50%;
    background: linear-gradient(135deg, #E5E7EB, #9CA3AF, #6B7280);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem; color: #1F2937;
    box-shadow: 0 0 0 3px #F9FAFB, 0 0 0 5px #D1D5DB, 0 6px 16px rgba(156, 163, 175, 0.5);
    position: relative;
}
.rank-2-icon::after {
    content: '🥈';
    position: absolute; bottom: -8px; right: -6px;
    font-size: 0.85rem;
    filter: drop-shadow(0 2px 3px rgba(0,0,0,0.25));
}
.rank-2-label {
    font-size: 0.55rem; font-weight: 900;
    color: #374151; text-transform: uppercase;
    letter-spacing: 0.08em;
    background: linear-gradient(135deg, #F3F4F6, #E5E7EB);
    padding: 2px 8px; border-radius: 10px;
    border: 1px solid #D1D5DB;
    white-space: nowrap;
}
.rank-3-wrapper { display: inline-flex; flex-direction: column; align-items: center; gap: 3px; }
.rank-3-icon {
    width: 38px; height: 38px; border-radius: 50%;
    background: linear-gradient(135deg, #FB923C, #D97706, #B45309);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.15rem; color: #FFF7ED;
    box-shadow: 0 0 0 3px #FFF7ED, 0 0 0 5px #FDBA74, 0 6px 16px rgba(217, 119, 6, 0.5);
    position: relative;
}
.rank-3-icon::after {
    content: '🥉';
    position: absolute; bottom: -8px; right: -6px;
    font-size: 0.85rem;
    filter: drop-shadow(0 2px 3px rgba(0,0,0,0.25));
}
.rank-3-label {
    font-size: 0.55rem; font-weight: 900;
    color: #7C2D12; text-transform: uppercase;
    letter-spacing: 0.08em;
    background: linear-gradient(135deg, #FED7AA, #FDBA74);
    padding: 2px 8px; border-radius: 10px;
    border: 1px solid #FB923C;
    white-space: nowrap;
}
.rank-4-wrapper { display: inline-flex; flex-direction: column; align-items: center; gap: 3px; }
.rank-4-icon {
    width: 36px; height: 36px; border-radius: 50%;
    background: linear-gradient(135deg, #60A5FA, #3B82F6, #2563EB);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.05rem; color: white;
    box-shadow: 0 0 0 3px #EFF6FF, 0 0 0 4px #93C5FD, 0 5px 14px rgba(59, 130, 246, 0.45);
}
.rank-4-label {
    font-size: 0.55rem; font-weight: 900;
    color: #1E40AF; text-transform: uppercase;
    letter-spacing: 0.08em;
    background: linear-gradient(135deg, #DBEAFE, #BFDBFE);
    padding: 2px 8px; border-radius: 10px;
    border: 1px solid #93C5FD;
    white-space: nowrap;
}
.rank-5-wrapper { display: inline-flex; flex-direction: column; align-items: center; gap: 3px; }
.rank-5-icon {
    width: 36px; height: 36px; border-radius: 50%;
    background: linear-gradient(135deg, #A78BFA, #8B5CF6, #7C3AED);
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; color: white;
    box-shadow: 0 0 0 3px #F5F3FF, 0 0 0 4px #C4B5FD, 0 5px 14px rgba(139, 92, 246, 0.45);
}
.rank-5-label {
    font-size: 0.55rem; font-weight: 900;
    color: #5B21B6; text-transform: uppercase;
    letter-spacing: 0.08em;
    background: linear-gradient(135deg, #EDE9FE, #DDD6FE);
    padding: 2px 8px; border-radius: 10px;
    border: 1px solid #C4B5FD;
    white-space: nowrap;
}
.rank-default {
    display: inline-flex; align-items: center; justify-content: center;
    width: 34px; height: 34px; border-radius: 50%;
    background: var(--primary-bg); color: var(--primary);
    font-weight: 800; font-size: 0.75rem;
    font-family: var(--font-mono);
    border: 2px solid var(--border-color);
}

/* Row highlighting */
.top-row-1 { background: linear-gradient(90deg, rgba(252, 211, 77, 0.12), transparent) !important; border-left: 4px solid #FCD34D !important; }
.top-row-2 { background: linear-gradient(90deg, rgba(156, 163, 175, 0.12), transparent) !important; border-left: 4px solid #9CA3AF !important; }
.top-row-3 { background: linear-gradient(90deg, rgba(217, 119, 6, 0.12), transparent) !important; border-left: 4px solid #D97706 !important; }
.top-row-4 { background: linear-gradient(90deg, rgba(59, 130, 246, 0.08), transparent) !important; border-left: 4px solid #3B82F6 !important; }
.top-row-5 { background: linear-gradient(90deg, rgba(139, 92, 246, 0.08), transparent) !important; border-left: 4px solid #8B5CF6 !important; }

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
.role-tag.admin { background: linear-gradient(135deg, #FCD34D, #F59E0B); color: #78350F; font-weight: 900; }
[data-theme="dark"] .role-tag.doctor { background: #2D1B4E; color: #A78BFA; }
[data-theme="dark"] .role-tag.reception { background: #1E3A8A; color: #93C5FD; }
[data-theme="dark"] .role-tag.pharmacy { background: #1A3A2A; color: #34D399; }
[data-theme="dark"] .role-tag.cashier { background: #78350F; color: #FDE68A; }
[data-theme="dark"] .role-tag.laboratory { background: #0E3A47; color: #67E8F9; }
[data-theme="dark"] .role-tag.audit { background: #4C1D95; color: #FBCFE8; }
[data-theme="dark"] .role-tag.admin { background: linear-gradient(135deg, #78350F, #B45309); color: #FDE68A; }

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

/* Sub-details expandable row */
.sub-details-toggle {
    cursor: pointer;
    color: var(--primary);
    font-size: 0.65rem;
    font-weight: 700;
    text-decoration: underline;
    text-decoration-style: dotted;
    margin-top: 2px;
    display: inline-block;
}
.sub-details-toggle:hover { color: var(--primary-dark); }
.sub-details-row {
    display: none;
    background: linear-gradient(135deg, rgba(11, 94, 215, 0.04), rgba(124, 58, 237, 0.02)) !important;
}
.sub-details-row.open {
    display: table-row;
}
.sub-details-row td {
    padding: 10px 18px !important;
    border-bottom: 2px solid var(--primary) !important;
}
.sub-details-content {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    font-size: 0.68rem;
}
.sub-detail-item {
    background: var(--bg-card);
    border: 1.5px solid var(--border-color);
    border-radius: 8px;
    padding: 6px 12px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.sub-detail-item i { color: var(--primary); }
.sub-detail-item strong { font-family: var(--font-mono); font-weight: 800; color: var(--primary); }

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
.top-performer-card.rank-4 { border-color: #3B82F6; }
.top-performer-card.rank-5 { border-color: #8B5CF6; }
[data-theme="dark"] .top-performer-card.rank-1 { background: linear-gradient(135deg, #422006, var(--bg-card)); }

@media (max-width: 1200px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 1024px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 768px) {
    .page-header { padding: 16px 18px; }
    .page-header .page-title { font-size: 1.15rem; }
    .stats-grid { grid-template-columns: 1fr; gap: 10px; }
    .stat-card { padding: 12px; min-height: 115px; }
    .data-table { font-size: 0.7rem; }
    .data-table thead th, .data-table tbody td { padding: 7px 8px; }
    .rank-1-icon { width: 36px; height: 36px; font-size: 1.1rem; }
    .rank-2-icon, .rank-3-icon { width: 34px; height: 34px; font-size: 1rem; }
    .rank-4-icon, .rank-5-icon { width: 32px; height: 32px; font-size: 0.9rem; }
}

@media print {
    .btn-header, .filter-card, .table-toolbar, .scroll-btn, .sub-details-toggle { display: none !important; }
    .sub-details-row { display: table-row !important; }
    .page-header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
    </style>
</head>
<body>

<script id="pdfDataScript" type="application/json">
<?= json_encode($pdf_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-users"></i>
                Employee Performance
                <span class="branch-tag audit-tag"><i class="fas fa-shield-alt"></i> AUDIT</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-store-alt"></i>
                <strong><?= htmlspecialchars($display_branch_name) ?></strong>
                <span class="branch-tag">
                    <i class="fas fa-users"></i> <?= number_format($total_employees) ?> Employees
                </span>
                <span class="branch-tag filter-tag">
                    <i class="fas fa-filter"></i> <?= htmlspecialchars($date_label) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <button onclick="openPDFWindow()" class="btn-header btn-pdf-export">
                <i class="fas fa-file-pdf"></i> PDF
            </button>
            <button onclick="window.print()" class="btn-header">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="dashboard.php" class="btn-header">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- FILTER CARD - HAKUNA BRANCH SELECTOR -->
    <div class="filter-card">
        <div class="filter-section">
            <div class="filter-section-title">
                <i class="fas fa-bolt"></i> Quick Filters
            </div>
            <div class="quick-filters">
                <a href="?quick=today" class="quick-btn <?= $quick_filter === 'today' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-day"></i> Today
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
                <a href="?" class="filter-btn-secondary">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- SUMMARY STATS - 2 CARDS TU (No Revenue) -->
    <div class="stats-grid">
        <div class="stat-card blue">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-label">Total Employees</div>
            <div class="stat-value">
                <span class="money-number"><?= number_format($total_employees) ?></span>
            </div>
            <div class="stat-sub">
                <i class="fas fa-user-tie"></i> Active staff
            </div>
        </div>
        
        <div class="stat-card purple">
            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
            <div class="stat-label">Total Activities</div>
            <div class="stat-value">
                <span class="money-number"><?= number_format($total_transactions) ?></span>
            </div>
            <div class="stat-sub">
                <i class="fas fa-list"></i> All transactions
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
                
                $icon = '';
                switch ($rank) {
                    case 1: $icon = '👑'; break;
                    case 2: $icon = '🥈'; break;
                    case 3: $icon = '🥉'; break;
                    case 4: $icon = '⭐'; break;
                    case 5: $icon = '💎'; break;
                    default: $icon = $rank;
                }
            ?>
                <div class="top-performer-card rank-<?= $rank ?>">
                    <span class="rank-badge <?= $rank_class ?>" style="font-size:1rem;">
                        <?= $icon ?>
                    </span>
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

    <!-- ALL EMPLOYEES PERFORMANCE TABLE - NO REVENUE COLUMN -->
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
            <table class="data-table" id="empTable" style="min-width:1100px;">
                <thead>
                    <tr>
                        <th style="width:80px;text-align:center;">Rank</th>
                        <th>Employee</th>
                        <th style="text-align:center;">Role</th>
                        <th>Branch</th>
                        <th style="text-align:center;">Activity</th>
                        <th style="text-align:center;">Activities</th>
                        <th style="text-align:center;">Score</th>
                        <th style="text-align:center;">Performance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($performance) > 0): ?>
                        <?php $rank = 1; foreach ($performance as $p): 
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
                            
                            $row_class = '';
                            if ($rank <= 5) $row_class = 'top-row-' . $rank;
                            
                            $has_sub_details = !empty($p['sub_details']);
                        ?>
                            <tr class="<?= $row_class ?>">
                                <td class="rank-cell">
                                    <?php if ($rank == 1): ?>
                                        <div class="rank-1-wrapper">
                                            <div class="rank-1-icon">🥇</div>
                                            <span class="rank-1-label">1st Place</span>
                                        </div>
                                    <?php elseif ($rank == 2): ?>
                                        <div class="rank-2-wrapper">
                                            <div class="rank-2-icon">🥈</div>
                                            <span class="rank-2-label">2nd</span>
                                        </div>
                                    <?php elseif ($rank == 3): ?>
                                        <div class="rank-3-wrapper">
                                            <div class="rank-3-icon">🥉</div>
                                            <span class="rank-3-label">3rd</span>
                                        </div>
                                    <?php elseif ($rank == 4): ?>
                                        <div class="rank-4-wrapper">
                                            <div class="rank-4-icon">
                                                <i class="fas fa-star"></i>
                                            </div>
                                            <span class="rank-4-label">4th</span>
                                        </div>
                                    <?php elseif ($rank == 5): ?>
                                        <div class="rank-5-wrapper">
                                            <div class="rank-5-icon">
                                                <i class="fas fa-gem"></i>
                                            </div>
                                            <span class="rank-5-label">5th</span>
                                        </div>
                                    <?php else: ?>
                                        <span class="rank-default"><?= $rank ?></span>
                                    <?php endif; ?>
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
                                            <?php if ($has_sub_details): ?>
                                                <span class="sub-details-toggle" onclick="toggleSubDetails(<?= $p['id'] ?>)">
                                                    <i class="fas fa-chevron-down"></i> View details
                                                </span>
                                            <?php endif; ?>
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
                                <td style="text-align:center;font-weight:800;color:var(--primary);font-family:var(--font-mono);">
                                    <?= number_format($p['transactions']) ?>
                                    <div style="font-size:0.58rem;color:var(--text-secondary);font-weight:600;font-family:var(--font-primary);">
                                        <?= $p['metric_label'] ?>
                                    </div>
                                </td>
                                <td style="text-align:center;font-weight:700;color:var(--purple);font-family:var(--font-mono);" class="searchable-cell">
                                    <?= number_format($p['activities']) ?>
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
                            
                            <?php if ($has_sub_details): ?>
                            <tr class="sub-details-row" id="sub-details-<?= $p['id'] ?>">
                                <td colspan="8">
                                    <div class="sub-details-content">
                                        <span style="font-weight:800;color:var(--primary);text-transform:uppercase;font-size:0.6rem;letter-spacing:0.06em;">
                                            <i class="fas fa-info-circle"></i> Breakdown:
                                        </span>
                                        <?php foreach ($p['sub_details'] as $key => $value): 
                                            $icon = 'fa-chart-bar';
                                            $label = ucwords(str_replace('_', ' ', $key));
                                            
                                            switch ($key) {
                                                case 'visits': $icon = 'fa-notes-medical'; break;
                                                case 'prescriptions': $icon = 'fa-prescription'; break;
                                                case 'bills': $icon = 'fa-file-invoice-dollar'; break;
                                                case 'patients': $icon = 'fa-user-injured'; break;
                                                case 'payments': $icon = 'fa-money-bill-wave'; break;
                                                case 'otc_sales': $icon = 'fa-cash-register'; break;
                                                case 'completed': $icon = 'fa-check-circle'; break;
                                                case 'in_progress': $icon = 'fa-spinner'; break;
                                                case 'activities': $icon = 'fa-history'; break;
                                                case 'bills_created': $icon = 'fa-file-invoice'; break;
                                                case 'otc_created': $icon = 'fa-shopping-cart'; break;
                                            }
                                        ?>
                                            <span class="sub-detail-item">
                                                <i class="fas <?= $icon ?>"></i>
                                                <?= $label ?>:
                                                <strong><?= number_format($value) ?></strong>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                        <?php $rank++; endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align:center;padding:50px 20px;color:var(--text-secondary);">
                                <i class="fas fa-users" style="font-size:2.5rem;opacity:0.3;display:block;margin-bottom:12px;"></i>
                                <p style="font-weight:600;">No employees found</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ROLE SUMMARY - NO REVENUE COLUMNS -->
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
                        <th style="text-align:center;">Avg per Employee</th>
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
                            <td style="text-align:center;font-weight:700;color:var(--purple);font-family:var(--font-mono);">
                                <?= number_format($avg_trans, 1) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

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

function toggleSubDetails(empId) {
    var row = document.getElementById('sub-details-' + empId);
    if (!row) return;
    row.classList.toggle('open');
    var toggle = event.target.closest('.sub-details-toggle');
    if (toggle) {
        if (row.classList.contains('open')) {
            toggle.innerHTML = '<i class="fas fa-chevron-up"></i> Hide details';
        } else {
            toggle.innerHTML = '<i class="fas fa-chevron-down"></i> View details';
        }
    }
}

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
'<title>Employee Performance - ' + data.branchName + '</title>' +
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
'.summary-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; margin-bottom: 28px; }' +
'.summary-card { background: white; border-radius: 14px; padding: 16px 14px; border: 2px solid #E2E8F0; position: relative; overflow: hidden; }' +
'.summary-card::before { content: ""; position: absolute; top: 0; left: 0; right: 0; height: 4px; }' +
'.summary-card.blue::before { background: linear-gradient(90deg, #0B5ED7, #3B82F6); }' +
'.summary-card.purple::before { background: linear-gradient(90deg, #7C3AED, #A78BFA); }' +
'.summary-card .card-label { font-size: 0.62rem; color: #64748B; font-weight: 700; text-transform: uppercase; margin-bottom: 4px; }' +
'.summary-card .card-value { font-size: 1.15rem; font-weight: 800; font-family: "JetBrains Mono", monospace; color: #0B5ED7; }' +
'.summary-card.purple .card-value { color: #7C3AED; }' +
'.pdf-table { width: 100%; border-collapse: collapse; font-size: 0.75rem; margin-bottom: 28px; }' +
'.pdf-table thead th { text-align: left; padding: 10px 12px; font-weight: 800; font-size: 0.6rem; text-transform: uppercase; color: white; background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }' +
'.pdf-table tbody td { padding: 9px 12px; border-bottom: 1px solid #E2E8F0; }' +
'.pdf-table tbody tr:nth-child(even) td { background: #F8FAFC; }' +
'.pdf-table .qty { font-family: "JetBrains Mono", monospace; font-weight: 800; color: #0B5ED7; text-align: right; }' +
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
'<div><div class="pdf-title">Employee Performance Report</div>' +
'<div class="pdf-subtitle"><strong>Braick Dispensary</strong>' +
'<span class="tag">📍 ' + data.branchName + '</span>' +
'<span class="tag">📅 ' + data.filterLabel + '</span>' +
'<span class="tag">🛡️ AUDIT</span></div></div>' +
'<div style="text-align:right;"><div class="pdf-generated">Generated On</div>' +
'<div class="pdf-date">' + dateStr + ' • ' + timeStr + '</div></div>' +
'</div>' +
'<div class="pdf-body">' +
'<div class="section-title">Summary</div>' +
'<div class="summary-grid">' +
'<div class="summary-card blue"><div class="card-label">Total Employees</div><div class="card-value">' + data.totalEmployees + '</div></div>' +
'<div class="summary-card purple"><div class="card-label">Total Activities</div><div class="card-value">' + data.totalTransactions + '</div></div>' +
'</div>' +
'<div class="section-title">🏆 Top Performers</div>' +
'<table class="pdf-table"><thead><tr><th>#</th><th>Employee</th><th>Role</th><th style="text-align:right;">Activity</th></tr></thead><tbody>';
    
    if (data.topPerformers && data.topPerformers.length > 0) {
        data.topPerformers.forEach(function(p, i) {
            var medal = '';
            if (i === 0) medal = '🥇 ';
            else if (i === 1) medal = '🥈 ';
            else if (i === 2) medal = '🥉 ';
            else if (i === 3) medal = '⭐ ';
            else if (i === 4) medal = '💎 ';
            
            html += '<tr><td>' + medal + (i + 1) + '</td><td><strong>' + p.name + '</strong></td>' +
                '<td>' + (p.role || '').toUpperCase() + '</td>' +
                '<td class="qty">' + p.transactions + '</td></tr>';
        });
    }
    
    html += '</tbody></table>' +
'<div class="section-title">📊 All Employees Performance</div>' +
'<table class="pdf-table"><thead><tr><th>#</th><th>Employee</th><th>Role</th><th>Branch</th><th style="text-align:right;">Activity</th><th style="text-align:right;">Activities</th><th style="text-align:right;">Score</th></tr></thead><tbody>';
    
    if (data.performance && data.performance.length > 0) {
        data.performance.forEach(function(p, i) {
            html += '<tr><td>' + (i + 1) + '</td>' +
                '<td><strong>' + p.name + '</strong></td>' +
                '<td>' + (p.role || '').toUpperCase() + '</td>' +
                '<td>' + p.branch + '</td>' +
                '<td class="qty">' + p.transactions + '</td>' +
                '<td class="qty">' + p.activities + '</td>' +
                '<td class="qty">' + p.score + '</td></tr>';
        });
    }
    
    html += '</tbody></table>' +
'</div>' +
'<div class="pdf-footer">' +
'<div><strong>Braick Dispensary Management System</strong></div>' +
'<div>👤 ' + data.userName + ' | 📍 ' + data.branchName + ' | 🕐 ' + timeStr + '</div>' +
'</div></div></body></html>';
    
    w.document.open();
    w.document.write(html);
    w.document.close();
}

console.log('%c🔍 Audit Employee Performance V8 - BRANCH LOCKED CLEAN', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
console.log('%c✅ AUDIT ANAONA BRANCH YAKE TU', 'font-size:13px; color:#34D399; font-weight:bold;');
console.log('%c✅ HAWEZI KUBADILISHA BRANCH', 'font-size:13px; color:#34D399; font-weight:bold;');
console.log('%c✅ HAKUNA "Branch ONLY" tag kwenye header', 'font-size:13px; color:#34D399; font-weight:bold;');
console.log('%c👥 Branch: <?= htmlspecialchars($display_branch_name) ?>', 'font-size:13px; color:#0B5ED7;');
console.log('%c👥 Total Employees: <?= $total_employees ?>', 'font-size:13px; color:#0B5ED7;');
console.log('%c📊 Total Activities: <?= $total_transactions ?>', 'font-size:13px; color:#7C3AED;');
</script>

</body>
</html>