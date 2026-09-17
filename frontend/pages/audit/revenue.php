<?php
// ================================================================
// FILE: frontend/pages/audit/revenue.php
// AUDIT ROLE - REVENUE REPORT (V13)
// ✅ AUDIT role only - VIEW ONLY
// ✅ Removed "Medications" card (same as Prescription)
// ✅ Removed "Amount" column from Expenses table
// ✅ Item Details column with multi-line wrap
// ✅ Prescription: ONLY from PAID bills
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
        case 'reception': header('Location: /dispensary_system/frontend/pages/reception/dashboard.php'); break;
        case 'pharmacy': header('Location: /dispensary_system/frontend/pages/pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: /dispensary_system/frontend/pages/laboratory/dashboard.php'); break;
        case 'cashier': header('Location: /dispensary_system/frontend/pages/cashier/dashboard.php'); break;
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

// BRANCH DISPLAY
$branch_name_display = 'All Branches';
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([(int)$selected_branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) $branch_name_display = $branch_data['name'];
}

$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// FILTERS
$quick_filter = $_GET['quick'] ?? '1m';
$date_from = $_GET['date_from'] ?? date('Y-m-d');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$hours_filter = isset($_GET['hours']) && $_GET['hours'] !== '' ? (int)$_GET['hours'] : 0;
$payment_method = $_GET['payment_method'] ?? 'all';
$search = trim($_GET['search'] ?? '');

// DATE CONDITIONS
$date_cond_bills = "";
$date_cond_otc = "";
$date_cond_exp = "";
$date_params = [];
$date_label = "";

switch ($quick_filter) {
    case 'today':
        $date_cond_bills = " AND DATE(b.updated_at) = CURDATE()";
        $date_cond_otc = " AND DATE(o.updated_at) = CURDATE()";
        $date_cond_exp = " AND DATE(e.payment_date) = CURDATE()";
        $date_label = "Today";
        break;
    case '1d':
        $date_cond_bills = " AND b.updated_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
        $date_cond_otc = " AND o.updated_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
        $date_cond_exp = " AND e.payment_date >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
        $date_label = "Last 1 Day";
        break;
    case '1w':
        $date_cond_bills = " AND b.updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $date_cond_otc = " AND o.updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $date_cond_exp = " AND e.payment_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $date_label = "Last 1 Week";
        break;
    case '1m':
        $date_cond_bills = " AND b.updated_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        $date_cond_otc = " AND o.updated_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        $date_cond_exp = " AND e.payment_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        $date_label = "Last 1 Month";
        break;
    case '3m':
        $date_cond_bills = " AND b.updated_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
        $date_cond_otc = " AND o.updated_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
        $date_cond_exp = " AND e.payment_date >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
        $date_label = "Last 3 Months";
        break;
    case '6m':
        $date_cond_bills = " AND b.updated_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
        $date_cond_otc = " AND o.updated_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
        $date_cond_exp = " AND e.payment_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
        $date_label = "Last 6 Months";
        break;
    case '1y':
        $date_cond_bills = " AND b.updated_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        $date_cond_otc = " AND o.updated_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        $date_cond_exp = " AND e.payment_date >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        $date_label = "Last 1 Year";
        break;
    case 'all':
        $date_label = "All Time";
        break;
    case 'hours':
        if ($hours_filter > 0) {
            $date_cond_bills = " AND b.updated_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)";
            $date_cond_otc = " AND o.updated_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)";
            $date_cond_exp = " AND e.payment_date >= DATE_SUB(NOW(), INTERVAL ? HOUR)";
            $date_params = [$hours_filter];
            $date_label = "Last {$hours_filter} Hours";
        } else {
            $date_label = "Enter Hours";
        }
        break;
    case 'custom':
        $date_cond_bills = " AND DATE(b.updated_at) BETWEEN ? AND ?";
        $date_cond_otc = " AND DATE(o.updated_at) BETWEEN ? AND ?";
        $date_cond_exp = " AND DATE(e.payment_date) BETWEEN ? AND ?";
        $date_params = [$date_from, $date_to];
        $date_label = date('d M Y', strtotime($date_from)) . ' - ' . date('d M Y', strtotime($date_to));
        break;
    default:
        $date_cond_bills = " AND b.updated_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        $date_cond_otc = " AND o.updated_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        $date_cond_exp = " AND e.payment_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        $date_label = "Last 1 Month";
}

// PAYMENT METHOD
$pay_cond_bills = "";
$pay_cond_otc = "";
$pay_params = [];
if ($payment_method !== 'all') {
    $pay_cond_bills = " AND b.payment_method = ?";
    $pay_cond_otc = " AND o.payment_method = ?";
    $pay_params = [$payment_method];
}

// BRANCH CONDITIONS
$branch_cond_b = "";
$branch_cond_o = "";
$branch_cond_e = "";
$branch_cond_bi = "";
$branch_params_b = [];
$branch_params_o = [];
$branch_params_e = [];
$branch_params_bi = [];

if ($selected_branch_id !== 'all') {
    $branch_cond_b = " AND b.branch_id = ?";
    $branch_cond_o = " AND o.branch_id = ?";
    $branch_cond_e = " AND e.branch_id = ?";
    $branch_cond_bi = " AND bi.branch_id = ?";
    $branch_params_b = [(int)$selected_branch_id];
    $branch_params_o = [(int)$selected_branch_id];
    $branch_params_e = [(int)$selected_branch_id];
    $branch_params_bi = [(int)$selected_branch_id];
}

// STATS
$patient_bills_revenue = 0;
$patient_bills_count = 0;
try {
    $sql = "SELECT COALESCE(SUM(b.total_amount), 0) as total, COUNT(*) as count 
            FROM bills b WHERE b.status = 'paid'
            AND b.patient_id IS NOT NULL AND b.visit_id IS NOT NULL
            AND b.bill_number NOT LIKE 'BILL-OTC-%'
            $branch_cond_b $date_cond_bills $pay_cond_bills";
    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge($branch_params_b, $date_params, $pay_params));
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $patient_bills_revenue = (float)($data['total'] ?? 0);
    $patient_bills_count = (int)($data['count'] ?? 0);
} catch (Exception $e) {}

$otc_revenue = 0;
$otc_count = 0;
try {
    $sql = "SELECT COALESCE(SUM(o.total_amount), 0) as total, COUNT(*) as count 
            FROM otc_sales o WHERE o.payment_status = 'paid'
            $branch_cond_o $date_cond_otc $pay_cond_otc";
    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge($branch_params_o, $date_params, $pay_params));
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $otc_revenue = (float)($data['total'] ?? 0);
    $otc_count = (int)($data['count'] ?? 0);
} catch (Exception $e) {}

// PRESCRIPTION REVENUE
$prescription_revenue = 0;
$prescription_count = 0;
try {
    $sql = "SELECT COALESCE(SUM(bi.total_price - COALESCE(bi.discount_amount, 0)), 0) as total, 
                   COUNT(DISTINCT bi.id) as count 
            FROM bill_items bi 
            INNER JOIN bills b ON bi.bill_id = b.id
            WHERE b.status = 'paid'
            AND bi.reference_type = 'prescription'
            AND bi.item_type = 'medication'
            AND b.patient_id IS NOT NULL
            AND b.visit_id IS NOT NULL
            AND b.bill_number NOT LIKE 'BILL-OTC-%'
            $branch_cond_bi $date_cond_bills";
    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge($branch_params_bi, $date_params));
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $prescription_revenue = (float)($data['total'] ?? 0);
    $prescription_count = (int)($data['count'] ?? 0);
} catch (Exception $e) {}

// BREAKDOWN
$breakdown_types = ['consultation', 'lab_test', 'procedure', 'medication', 'registration', 'equipment'];
$breakdown_data = [];
foreach ($breakdown_types as $type) {
    $breakdown_data[$type] = ['revenue' => 0, 'count' => 0];
    try {
        $sql = "SELECT COALESCE(SUM(bi.total_price - COALESCE(bi.discount_amount, 0)), 0) as total, 
                       COUNT(DISTINCT bi.id) as count 
                FROM bill_items bi 
                INNER JOIN bills b ON bi.bill_id = b.id
                WHERE b.status = 'paid'
                AND bi.item_type = ?
                AND b.patient_id IS NOT NULL 
                AND b.visit_id IS NOT NULL
                AND b.bill_number NOT LIKE 'BILL-OTC-%'
                $branch_cond_bi $date_cond_bills";
        $stmt = $db->prepare($sql);
        $stmt->execute(array_merge([$type], $branch_params_bi, $date_params));
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        $breakdown_data[$type] = [
            'revenue' => (float)($data['total'] ?? 0), 
            'count' => (int)($data['count'] ?? 0)
        ];
    } catch (Exception $e) {}
}

$consultation_revenue = $breakdown_data['consultation']['revenue'];
$consultation_count = $breakdown_data['consultation']['count'];
$lab_revenue = $breakdown_data['lab_test']['revenue'];
$lab_count = $breakdown_data['lab_test']['count'];
$procedure_revenue = $breakdown_data['procedure']['revenue'];
$procedure_count = $breakdown_data['procedure']['count'];
$medication_revenue = $breakdown_data['medication']['revenue'];
$medication_count = $breakdown_data['medication']['count'];
$registration_revenue = $breakdown_data['registration']['revenue'];
$registration_count = $breakdown_data['registration']['count'];
$equipment_revenue = $breakdown_data['equipment']['revenue'];
$equipment_count = $breakdown_data['equipment']['count'];

$total_revenue = $patient_bills_revenue + $otc_revenue;
$total_transactions = $patient_bills_count + $otc_count;

// EXPENSES
$total_expenses = 0;
$expenses_count = 0;
try {
    $sql = "SELECT COALESCE(SUM(e.amount), 0) as total, COUNT(*) as count 
            FROM expenses e WHERE e.status = 'paid'
            $branch_cond_e $date_cond_exp";
    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge($branch_params_e, $date_params));
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_expenses = (float)($data['total'] ?? 0);
    $expenses_count = (int)($data['count'] ?? 0);
} catch (Exception $e) {}

$net_profit = $total_revenue - $total_expenses;
$profit_percentage = ($total_revenue > 0) ? round(($net_profit / $total_revenue) * 100, 1) : 0;

// TRANSACTIONS - With Item Details
$transactions = [];
try {
    $search_cond_bills = "";
    $search_cond_otc = "";
    $search_params_b = [];
    $search_params_o = [];
    
    if (!empty($search)) {
        $search_cond_bills = " AND (b.bill_number LIKE ? OR p.full_name LIKE ? OR u.full_name LIKE ? OR b.payment_method LIKE ?)";
        $search_params_b = ["%$search%", "%$search%", "%$search%", "%$search%"];
        $search_cond_otc = " AND (o.sale_number LIKE ? OR o.customer_name LIKE ? OR u2.full_name LIKE ? OR o.payment_method LIKE ?)";
        $search_params_o = ["%$search%", "%$search%", "%$search%", "%$search%"];
    }
    
    $union_sql = "
        SELECT 'bill' as transaction_type, b.id, b.bill_number as reference_number,
            b.total_amount as amount, b.payment_method, b.status, b.created_at, b.updated_at,
            COALESCE(p.full_name, 'Walk-in') as customer_name,
            COALESCE(u.full_name, 'N/A') as received_by_name,
            COALESCE(u.role, 'user') as received_by_role,
            br.name as branch_name, NULL as customer_phone,
            (SELECT COUNT(*) FROM bill_items WHERE bill_id = b.id AND status != 'cancelled') as item_count,
            (SELECT GROUP_CONCAT(DISTINCT item_type ORDER BY item_type SEPARATOR ',') 
             FROM bill_items WHERE bill_id = b.id AND status != 'cancelled') as item_types,
            (SELECT GROUP_CONCAT(CONCAT(item_name, ' (', quantity, ')') SEPARATOR '\n') 
             FROM bill_items WHERE bill_id = b.id AND status != 'cancelled') as items_summary,
            b.notes
        FROM bills b
        LEFT JOIN patients p ON b.patient_id = p.id
        LEFT JOIN users u ON b.created_by = u.id
        LEFT JOIN branches br ON b.branch_id = br.id
        WHERE b.patient_id IS NOT NULL AND b.visit_id IS NOT NULL
          AND b.bill_number NOT LIKE 'BILL-OTC-%'
          $branch_cond_b $date_cond_bills $pay_cond_bills $search_cond_bills
        
        UNION ALL
        
        SELECT 'otc' as transaction_type, o.id, o.sale_number as reference_number,
            o.total_amount as amount, o.payment_method, o.payment_status as status,
            o.created_at, o.updated_at,
            COALESCE(o.customer_name, 'Walk-in') as customer_name,
            COALESCE(u2.full_name, 'N/A') as received_by_name,
            COALESCE(u2.role, 'user') as received_by_role,
            br2.name as branch_name, o.customer_phone,
            (SELECT COUNT(*) FROM otc_sale_items WHERE sale_id = o.id) as item_count,
            'medication' as item_types,
            (SELECT GROUP_CONCAT(CONCAT(item_name, ' (', quantity, ')') SEPARATOR '\n') 
             FROM otc_sale_items WHERE sale_id = o.id) as items_summary,
            o.notes
        FROM otc_sales o
        LEFT JOIN users u2 ON o.sold_by = u2.id
        LEFT JOIN branches br2 ON o.branch_id = br2.id
        WHERE o.payment_status = 'paid'
          $branch_cond_o $date_cond_otc $pay_cond_otc $search_cond_otc
        
        ORDER BY updated_at DESC LIMIT 200
    ";
    
    $union_params = array_merge(
        $branch_params_b, $date_params, $pay_params, $search_params_b,
        $branch_params_o, $date_params, $pay_params, $search_params_o
    );
    
    $stmt = $db->prepare($union_sql);
    $stmt->execute($union_params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// EXPENSES LIST
$expenses_list = [];
try {
    $sql = "SELECT 
                e.id, e.expense_number, e.category, e.description, e.amount,
                e.payment_method, e.payment_date, e.status, e.receipt_number,
                e.notes, e.created_at, e.updated_at, e.created_by,
                u.full_name as recorded_by_name,
                u.username as recorded_by_username,
                u.role as recorded_by_role,
                u.profile_pic as recorded_by_pic,
                br.name as branch_name
            FROM expenses e
            LEFT JOIN users u ON e.created_by = u.id
            LEFT JOIN branches br ON e.branch_id = br.id
            WHERE e.status = 'paid'
            $branch_cond_e $date_cond_exp
            ORDER BY e.payment_date DESC, e.created_at DESC
            LIMIT 200";
    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge($branch_params_e, $date_params));
    $expenses_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// MONTHLY CHART
$monthly_labels = [];
$monthly_patient = [];
$monthly_otc = [];
$monthly_expenses = [];

for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $monthly_labels[] = date('M Y', strtotime("-$i months"));
    
    $p_b = [$month];
    $p_o = [$month];
    $p_e = [$month];
    
    if ($selected_branch_id !== 'all') {
        $p_b[] = (int)$selected_branch_id;
        $p_o[] = (int)$selected_branch_id;
        $p_e[] = (int)$selected_branch_id;
    }
    
    $sql = "SELECT COALESCE(SUM(b.total_amount), 0) as total FROM bills b
            WHERE b.status = 'paid' AND b.patient_id IS NOT NULL AND b.visit_id IS NOT NULL
            AND b.bill_number NOT LIKE 'BILL-OTC-%' AND DATE_FORMAT(b.updated_at, '%Y-%m') = ?";
    if ($selected_branch_id !== 'all') $sql .= " AND b.branch_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($p_b);
    $monthly_patient[] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    
    $sql = "SELECT COALESCE(SUM(total_amount), 0) as total FROM otc_sales
            WHERE payment_status = 'paid' AND DATE_FORMAT(updated_at, '%Y-%m') = ?";
    if ($selected_branch_id !== 'all') $sql .= " AND branch_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($p_o);
    $monthly_otc[] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    
    $sql = "SELECT COALESCE(SUM(amount), 0) as total FROM expenses
            WHERE status = 'paid' AND DATE_FORMAT(payment_date, '%Y-%m') = ?";
    if ($selected_branch_id !== 'all') $sql .= " AND branch_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($p_e);
    $monthly_expenses[] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
}

// DAILY CHART
$daily_labels = [];
$daily_patient = [];
$daily_otc = [];

for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $daily_labels[] = date('d M', strtotime($date));
    
    $p_b = [$date];
    $p_o = [$date];
    
    if ($selected_branch_id !== 'all') {
        $p_b[] = (int)$selected_branch_id;
        $p_o[] = (int)$selected_branch_id;
    }
    
    $sql = "SELECT COALESCE(SUM(b.total_amount), 0) as total FROM bills b
            WHERE b.status = 'paid' AND b.patient_id IS NOT NULL AND b.visit_id IS NOT NULL
            AND b.bill_number NOT LIKE 'BILL-OTC-%' AND DATE(b.updated_at) = ?";
    if ($selected_branch_id !== 'all') $sql .= " AND b.branch_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($p_b);
    $daily_patient[] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    
    $sql = "SELECT COALESCE(SUM(total_amount), 0) as total FROM otc_sales
            WHERE payment_status = 'paid' AND DATE(updated_at) = ?";
    if ($selected_branch_id !== 'all') $sql .= " AND branch_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($p_o);
    $daily_otc[] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
}

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
    <title>Revenue Report - Braick Audit</title>
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <style>
:root {
    --font-primary: 'Inter', -apple-system, sans-serif;
    --font-mono: 'JetBrains Mono', 'Courier New', monospace;
    --primary: #0B5ED7;
    --primary-dark: #0A4CA8;
    --primary-light: #3B82F6;
    --primary-bg: #E8F0FE;
    --primary-soft: #DBEAFE;
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
    --primary-soft: #1E40AF;
    --success-bg: #1A3A2A;
    --danger-bg: #3A1A1A;
    --warning-bg: #3A2A1A;
    --purple-bg: #2D1B4E;
    --cyan-bg: #0E3A47;
}

* { font-family: var(--font-primary); -webkit-font-smoothing: antialiased; }
html, body { font-family: var(--font-primary); background: var(--bg-body); color: var(--text-primary); }

.money-number, .stat-value, .money-cell, .card-value, .font-mono {
    font-family: var(--font-mono) !important;
    font-feature-settings: 'tnum';
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.02em;
}

.page-header {
    background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 100%);
    border-radius: 16px; padding: 20px 24px; margin-bottom: 18px;
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
    border-color: rgba(255,255,255,0.2);
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

.filter-card {
    background: var(--bg-card); border-radius: 14px; padding: 16px 18px;
    border: 2px solid var(--border-color); margin-bottom: 18px;
    box-shadow: var(--shadow-sm);
}
.filter-section { margin-bottom: 12px; }
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
.quick-btn.hours-active {
    background: linear-gradient(135deg, #10B981, #059669);
    color: white; border-color: transparent;
}
.quick-btn.custom-active {
    background: linear-gradient(135deg, #F59E0B, #D97706);
    color: white; border-color: transparent;
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
.filter-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(11, 94, 215, 0.35);
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
.filter-btn-secondary:hover { border-color: var(--danger); color: var(--danger); }

.stats-grid {
    display: grid; grid-template-columns: repeat(4, 1fr);
    gap: 14px; margin-bottom: 18px;
}
.stat-card {
    background: var(--bg-card); border-radius: 14px; padding: 14px 16px;
    border: 2px solid var(--border-color);
    transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: var(--shadow-sm); position: relative; overflow: hidden;
    min-height: 130px; display: flex; flex-direction: column;
    justify-content: space-between; cursor: pointer;
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
.stat-card .stat-sub i { font-size: 0.55rem; }

.stat-card.revenue::before { background: linear-gradient(90deg, #0B5ED7, #3B82F6, #0B5ED7); background-size: 200% 100%; animation: shimmer 3s infinite linear; }
.stat-card.revenue:hover { border-color: #0B5ED7; }
.stat-card.revenue .stat-icon { background: linear-gradient(135deg, #0B5ED7, #3B82F6); }
.stat-card.revenue .stat-value .money-number { color: var(--primary); }
.stat-card.prescription::before { background: linear-gradient(90deg, #7C3AED, #A78BFA, #7C3AED); background-size: 200% 100%; animation: shimmer 3s infinite linear; }
.stat-card.prescription:hover { border-color: #7C3AED; }
.stat-card.prescription .stat-icon { background: linear-gradient(135deg, #7C3AED, #A78BFA); }
.stat-card.prescription .stat-value .money-number { color: var(--purple); }
.stat-card.otc::before { background: linear-gradient(90deg, #0891B2, #06B6D4, #0891B2); background-size: 200% 100%; animation: shimmer 3s infinite linear; }
.stat-card.otc:hover { border-color: #0891B2; }
.stat-card.otc .stat-icon { background: linear-gradient(135deg, #0891B2, #06B6D4); }
.stat-card.otc .stat-value .money-number { color: var(--cyan); }
.stat-card.consultation::before { background: linear-gradient(90deg, #059669, #34D399, #059669); background-size: 200% 100%; animation: shimmer 3s infinite linear; }
.stat-card.consultation:hover { border-color: #059669; }
.stat-card.consultation .stat-icon { background: linear-gradient(135deg, #059669, #34D399); }
.stat-card.consultation .stat-value .money-number { color: var(--success); }
.stat-card.lab::before { background: linear-gradient(90deg, #3B82F6, #93C5FD, #3B82F6); background-size: 200% 100%; animation: shimmer 3s infinite linear; }
.stat-card.lab:hover { border-color: #3B82F6; }
.stat-card.lab .stat-icon { background: linear-gradient(135deg, #3B82F6, #93C5FD); }
.stat-card.lab .stat-value .money-number { color: var(--primary); }
.stat-card.expenses::before { background: linear-gradient(90deg, #DC2626, #F87171, #DC2626); background-size: 200% 100%; animation: shimmer 3s infinite linear; }
.stat-card.expenses:hover { border-color: #DC2626; }
.stat-card.expenses .stat-icon { background: linear-gradient(135deg, #DC2626, #F87171); }
.stat-card.expenses .stat-value .money-number { color: var(--danger); }
.stat-card.profit {
    background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card) 70%, rgba(11, 94, 215, 0.05) 100%);
}
.stat-card.profit::before { background: linear-gradient(90deg, #0B5ED7, #10B981, #0B5ED7); background-size: 200% 100%; animation: shimmer 3s infinite linear; }
.stat-card.profit:hover { border-color: #0B5ED7; }
.stat-card.profit .stat-icon { background: linear-gradient(135deg, #0B5ED7, #10B981); }
.stat-card.profit .stat-value .money-number { color: var(--primary); }
.stat-card.profit.loss .stat-value .money-number { color: var(--danger); }
.stat-card.profit.loss::before { background: linear-gradient(90deg, #DC2626, #F87171, #DC2626); background-size: 200% 100%; animation: shimmer 3s infinite linear; }
.stat-card.profit.loss .stat-icon { background: linear-gradient(135deg, #DC2626, #F87171); }
@keyframes shimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

.chart-grid {
    display: grid; grid-template-columns: 1fr 1fr;
    gap: 18px; margin-bottom: 18px;
}
.chart-card {
    background: var(--bg-card); border-radius: 14px;
    border: 2px solid var(--border-color); overflow: hidden;
    box-shadow: var(--shadow-sm); transition: all 0.3s ease;
}
.chart-card:hover { box-shadow: var(--shadow-md); border-color: var(--primary); }
.chart-card .chart-header {
    padding: 12px 16px; border-bottom: 2px solid var(--border-color);
    display: flex; justify-content: space-between; align-items: center;
    flex-wrap: wrap; gap: 8px; background: var(--primary-bg);
}
.chart-card .chart-header .chart-title {
    font-size: 0.85rem; font-weight: 800; color: var(--text-primary);
    display: flex; align-items: center; gap: 8px;
}
.chart-card .chart-header .chart-title i { color: var(--primary); font-size: 0.95rem; }
.chart-card .chart-body { padding: 16px; height: 260px; position: relative; }

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
.table-card .table-header.profit {
    background: linear-gradient(135deg, #059669, #047857);
}
.table-card .table-header.profit.loss {
    background: linear-gradient(135deg, #DC2626, #B91C1C);
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
.table-card.expenses-red { border-color: #DC2626; }
.table-card.expenses-red:hover {
    border-color: #DC2626;
    box-shadow: 0 10px 30px rgba(220, 38, 38, 0.15);
}
.table-card.expenses-red .table-header {
    background: linear-gradient(135deg, #DC2626, #B91C1C) !important;
}
.table-card.expenses-red .table-header .title i { color: #FCA5A5 !important; }
.table-card.expenses-red .table-toolbar {
    background: var(--danger-bg) !important;
    border-bottom-color: rgba(220, 38, 38, 0.2) !important;
}
[data-theme="dark"] .table-card.expenses-red .table-toolbar { background: #3A1A1A !important; }
.table-card.expenses-red .search-box input:focus {
    border-color: #DC2626;
    box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.15);
}
.table-card.expenses-red .search-count { background: #DC2626; }
.table-card.expenses-red .search-count.has-results { background: #059669; }
.table-card.expenses-red .search-count.no-results { background: #B91C1C; }
.table-card.expenses-red .scroll-btn:hover {
    background: #DC2626; border-color: #DC2626;
    box-shadow: 0 4px 10px rgba(220, 38, 38, 0.3);
}
.table-card.expenses-red .data-table thead th {
    background: linear-gradient(135deg, #DC2626, #B91C1C);
}
.table-card.expenses-red .data-table tbody tr:hover td {
    background: var(--danger-bg) !important;
}
[data-theme="dark"] .table-card.expenses-red .data-table tbody tr:hover td {
    background: #3A1A1A !important;
}
.expense-ref-badge {
    font-size: 0.68rem; color: #DC2626; font-weight: 800;
    background: var(--danger-bg); padding: 3px 7px;
    border-radius: 5px; display: inline-block;
    font-family: var(--font-mono);
}
[data-theme="dark"] .expense-ref-badge { background: #3A1A1A; color: #F87171; }
.received-by-avatar.red {
    background: linear-gradient(135deg, #DC2626, #F87171) !important;
}
.expense-category-badge {
    font-size: 0.7rem; font-weight: 700;
    background: var(--warning-bg); color: var(--warning);
    padding: 3px 8px; border-radius: 6px; display: inline-block;
}
[data-theme="dark"] .expense-category-badge { background: #3A2A1A; color: #FBBF24; }

.table-toolbar {
    display: flex; justify-content: space-between; align-items: center;
    gap: 10px; flex-wrap: wrap; padding: 10px 16px;
    background: var(--primary-bg); border-bottom: 2px solid var(--border-color);
}
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
.search-box .search-clear:hover { background: var(--border-color); color: var(--danger); }
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
    background: #FEF08A; color: #713F12;
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
.data-table tbody tr:last-child td { border-bottom: none; }
.data-table tbody tr.hidden-row { display: none !important; }
.data-table tbody tr.total-row td {
    border-top: 3px solid var(--primary);
    font-weight: 800; font-size: 0.85rem;
    background: var(--primary-bg) !important;
    color: var(--primary);
}
.money-cell {
    font-family: var(--font-mono); font-weight: 800;
    font-size: 0.8rem; color: var(--success);
    text-align: right; letter-spacing: -0.02em;
}
.money-cell .currency-prefix {
    font-size: 0.65rem; color: var(--text-secondary);
    margin-right: 2px; font-family: var(--font-primary); font-weight: 600;
}
.money-cell.red { color: #DC2626 !important; }

.type-badge {
    display: inline-flex; align-items: center; gap: 3px;
    padding: 3px 8px; border-radius: 6px;
    font-size: 0.6rem; font-weight: 800;
    text-transform: uppercase; white-space: nowrap;
}
.type-badge.bill { background: #DBEAFE; color: #1E40AF; border: 1px solid #93C5FD; }
.type-badge.otc { background: #CFFAFE; color: #0E7490; border: 1px solid #67E8F9; }
[data-theme="dark"] .type-badge.bill { background: #1E3A8A; color: #93C5FD; border-color: #3B82F6; }
[data-theme="dark"] .type-badge.otc { background: #0E3A47; color: #67E8F9; border-color: #06B6D4; }

.status-badge {
    display: inline-flex; align-items: center; gap: 3px;
    padding: 3px 8px; border-radius: 8px;
    font-size: 0.6rem; font-weight: 800;
    text-transform: uppercase; white-space: nowrap;
}
.status-badge.paid { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
.status-badge.pending { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning); }
.status-badge.partial { background: var(--cyan-bg); color: var(--cyan); border: 1px solid var(--cyan); }
.status-badge.cancelled { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger); }

.received-by { display: flex; align-items: center; gap: 8px; }
.received-by-avatar {
    width: 28px; height: 28px; border-radius: 50%;
    background: linear-gradient(135deg, #0B5ED7, #3B82F6);
    color: white; display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: 0.65rem; flex-shrink: 0;
    text-transform: uppercase;
}
.received-by-info { display: flex; flex-direction: column; gap: 1px; }
.received-by-name { font-size: 0.72rem; font-weight: 700; color: var(--text-primary); }
.received-by-role { font-size: 0.55rem; font-weight: 800; color: var(--text-secondary); text-transform: uppercase; }

.role-tag {
    display: inline-block; padding: 1px 5px; border-radius: 4px;
    font-size: 0.5rem; font-weight: 800; text-transform: uppercase;
}
.role-tag.cashier { background: #FEF3C7; color: #D97706; }
.role-tag.reception { background: #DBEAFE; color: #1E40AF; }
.role-tag.pharmacy { background: #D1FAE5; color: #059669; }
.role-tag.admin { background: #FCE7F3; color: #BE185D; }
.role-tag.doctor { background: #EDE9FE; color: #7C3AED; }
.role-tag.laboratory { background: #CFFAFE; color: #0891B2; }
.role-tag.audit { background: #FCE7F3; color: #BE185D; }
.role-tag.user { background: var(--border-color); color: var(--text-secondary); }

.payment-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 8px; border-radius: 8px;
    font-size: 0.62rem; font-weight: 700;
    background: var(--primary-bg); color: var(--primary);
    white-space: nowrap;
}
.action-buttons {
    display: flex; gap: 4px; justify-content: center; align-items: center;
}
.btn-action {
    width: 28px; height: 28px; border-radius: 7px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 0.7rem; border: none; cursor: pointer;
    transition: all 0.25s ease; text-decoration: none;
}
.btn-action:hover { transform: translateY(-2px) scale(1.1); }
.btn-action.view { background: rgba(11, 94, 215, 0.12); color: #0B5ED7; }
.btn-action.view:hover { background: #0B5ED7; color: white; box-shadow: 0 4px 10px rgba(11, 94, 215, 0.4); }

.dot-indicator {
    display: inline-block; width: 8px; height: 8px;
    border-radius: 50%; margin-right: 6px;
    vertical-align: middle; box-shadow: 0 2px 4px rgba(0,0,0,0.15);
}

/* ITEM DETAILS CELL */
.item-details-cell {
    max-width: 200px;
    min-width: 140px;
    padding: 8px 10px !important;
}
.item-list {
    display: flex;
    flex-direction: column;
    gap: 3px;
    max-height: 120px;
    overflow-y: auto;
}
.item-list::-webkit-scrollbar { width: 4px; }
.item-list::-webkit-scrollbar-track { background: var(--border-color); border-radius: 10px; }
.item-list::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }

.item-line {
    display: flex;
    align-items: flex-start;
    gap: 4px;
    font-size: 0.68rem;
    line-height: 1.35;
    color: var(--text-primary);
    font-weight: 600;
    word-break: break-word;
}
.item-line .item-bullet {
    color: var(--primary);
    font-weight: 900;
    flex-shrink: 0;
    font-size: 0.7rem;
    line-height: 1.3;
}
.item-line .item-name {
    flex: 1;
    word-break: break-word;
}
.item-line .item-qty {
    font-family: var(--font-mono);
    font-size: 0.6rem;
    font-weight: 800;
    color: var(--primary);
    background: var(--primary-bg);
    padding: 1px 5px;
    border-radius: 4px;
    flex-shrink: 0;
    white-space: nowrap;
}

.item-type-tag {
    display: inline-block;
    font-size: 0.5rem;
    font-weight: 800;
    text-transform: uppercase;
    padding: 1px 5px;
    border-radius: 3px;
    margin-top: 4px;
    background: var(--primary-bg);
    color: var(--primary);
    letter-spacing: 0.03em;
}
.item-type-tag.medication { background: #FEF3C7; color: #D97706; }
.item-type-tag.lab_test { background: #DBEAFE; color: #1E40AF; }
.item-type-tag.consultation { background: #D1FAE5; color: #059669; }
.item-type-tag.procedure { background: #CCFBF1; color: #0D9488; }
.item-type-tag.registration { background: #F1F5F9; color: #64748B; }
.item-type-tag.equipment { background: #EDE9FE; color: #7C3AED; }
[data-theme="dark"] .item-type-tag.medication { background: #78350F; color: #FDE68A; }
[data-theme="dark"] .item-type-tag.lab_test { background: #1E3A8A; color: #93C5FD; }
[data-theme="dark"] .item-type-tag.consultation { background: #1A3A2A; color: #34D399; }
[data-theme="dark"] .item-type-tag.procedure { background: #134E4A; color: #5EEAD4; }
[data-theme="dark"] .item-type-tag.registration { background: #334155; color: #94A3B8; }
[data-theme="dark"] .item-type-tag.equipment { background: #2D1B4E; color: #A78BFA; }

@media (max-width: 1200px) { .stats-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 1024px) {
    .chart-grid { grid-template-columns: 1fr; }
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 768px) {
    .page-header { padding: 16px 18px; }
    .page-header .page-title { font-size: 1.15rem; }
    .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
    .stat-card { padding: 12px; min-height: 115px; }
    .stat-card .stat-icon { width: 32px; height: 32px; font-size: 0.9rem; }
    .stat-card .stat-value { font-size: 1.1rem; }
    .data-table { font-size: 0.7rem; }
    .data-table thead th, .data-table tbody td { padding: 7px 8px; }
    .quick-btn { font-size: 0.65rem; padding: 5px 10px; }
    .table-toolbar { flex-direction: column; align-items: stretch; }
    .table-toolbar-right { justify-content: flex-end; }
    .item-details-cell { max-width: 160px; min-width: 120px; }
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
                <i class="fas fa-chart-line"></i>
                Revenue Report
                <span class="branch-tag"><i class="fas fa-shield-alt"></i> AUDIT</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-store-alt"></i>
                <strong><?= htmlspecialchars($branch_name_display) ?></strong>
                <span class="branch-tag">
                    <i class="fas fa-money-bill-wave"></i> <?= $currency ?> <?= number_format($total_revenue, 0) ?>
                </span>
                <span class="branch-tag">
                    <i class="fas fa-receipt"></i> <?= number_format($total_transactions) ?> Transactions
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
                <a href="?branch=<?= $selected_branch_id ?>&quick=1d" class="quick-btn <?= $quick_filter === '1d' ? 'active' : '' ?>">
                    <i class="fas fa-clock"></i> 1D
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
                <a href="?branch=<?= $selected_branch_id ?>&quick=hours&hours=<?= $hours_filter > 0 ? $hours_filter : 24 ?>" 
                   class="quick-btn <?= $quick_filter === 'hours' ? 'hours-active' : '' ?>">
                    <i class="fas fa-hourglass-half"></i> Hours
                </a>
                <a href="?branch=<?= $selected_branch_id ?>&quick=custom&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" 
                   class="quick-btn <?= $quick_filter === 'custom' ? 'custom-active' : '' ?>">
                    <i class="fas fa-calendar-check"></i> Custom
                </a>
            </div>
        </div>

        <form method="GET" id="filterForm">
            <input type="hidden" name="branch" value="<?= htmlspecialchars($selected_branch_id) ?>">
            <input type="hidden" name="quick" value="<?= htmlspecialchars($quick_filter) ?>">
            
            <div class="filter-form">
                <?php if ($quick_filter === 'hours'): ?>
                    <div class="filter-group">
                        <label><i class="fas fa-hourglass-half"></i> Hours Ago</label>
                        <input type="number" name="hours" 
                               value="<?= $hours_filter > 0 ? $hours_filter : '' ?>" 
                               placeholder="e.g. 12, 24, 48..." min="1" max="8760">
                    </div>
                <?php endif; ?>
                
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
                    <label><i class="fas fa-credit-card"></i> Payment Method</label>
                    <select name="payment_method">
                        <option value="all" <?= $payment_method === 'all' ? 'selected' : '' ?>>All Methods</option>
                        <option value="cash" <?= $payment_method === 'cash' ? 'selected' : '' ?>>Cash</option>
                        <option value="m-pesa" <?= $payment_method === 'm-pesa' ? 'selected' : '' ?>>M-Pesa</option>
                        <option value="airtel_money" <?= $payment_method === 'airtel_money' ? 'selected' : '' ?>>Airtel Money</option>
                        <option value="tigo_pesa" <?= $payment_method === 'tigo_pesa' ? 'selected' : '' ?>>Tigo Pesa</option>
                        <option value="halopesa" <?= $payment_method === 'halopesa' ? 'selected' : '' ?>>Halopesa</option>
                        <option value="bank" <?= $payment_method === 'bank' ? 'selected' : '' ?>>Bank</option>
                        <option value="card" <?= $payment_method === 'card' ? 'selected' : '' ?>>Card</option>
                        <option value="insurance" <?= $payment_method === 'insurance' ? 'selected' : '' ?>>Insurance</option>
                        <option value="other" <?= $payment_method === 'other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                
                <button type="submit" class="filter-btn-primary">
                    <i class="fas fa-filter"></i> Apply
                </button>
                
                <a href="?branch=<?= $selected_branch_id ?>" class="filter-btn-secondary">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </div>
        </form>
    </div>

    <!-- STATS GRID - 7 CARDS (MEDICATION REMOVED) -->
    <div class="stats-grid">
        <div class="stat-card revenue">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-label">Total Revenue</div>
            <div class="stat-value">
                <span class="currency-symbol"><?= $currency ?></span>
                <span class="money-number"><?= number_format($total_revenue, 0) ?></span>
            </div>
            <div class="stat-sub"><i class="fas fa-info-circle"></i> Bills + OTC</div>
        </div>
        
        <div class="stat-card revenue">
            <div class="stat-icon"><i class="fas fa-file-invoice"></i></div>
            <div class="stat-label">Patient Bills</div>
            <div class="stat-value">
                <span class="currency-symbol"><?= $currency ?></span>
                <span class="money-number"><?= number_format($patient_bills_revenue, 0) ?></span>
            </div>
            <div class="stat-sub"><i class="fas fa-check-circle"></i> <?= number_format($patient_bills_count) ?> bills</div>
        </div>
        
        <div class="stat-card otc">
            <div class="stat-icon"><i class="fas fa-cash-register"></i></div>
            <div class="stat-label">OTC Sales</div>
            <div class="stat-value">
                <span class="currency-symbol"><?= $currency ?></span>
                <span class="money-number"><?= number_format($otc_revenue, 0) ?></span>
            </div>
            <div class="stat-sub"><i class="fas fa-receipt"></i> <?= number_format($otc_count) ?> transactions</div>
        </div>
        
        <div class="stat-card prescription">
            <div class="stat-icon"><i class="fas fa-prescription"></i></div>
            <div class="stat-label">Prescription</div>
            <div class="stat-value">
                <span class="currency-symbol"><?= $currency ?></span>
                <span class="money-number"><?= number_format($prescription_revenue, 0) ?></span>
            </div>
            <div class="stat-sub"><i class="fas fa-pills"></i> <?= number_format($prescription_count) ?> items</div>
        </div>
        
        <div class="stat-card consultation">
            <div class="stat-icon"><i class="fas fa-stethoscope"></i></div>
            <div class="stat-label">Consultation</div>
            <div class="stat-value">
                <span class="currency-symbol"><?= $currency ?></span>
                <span class="money-number"><?= number_format($consultation_revenue, 0) ?></span>
            </div>
            <div class="stat-sub"><i class="fas fa-notes-medical"></i> <?= number_format($consultation_count) ?> visits</div>
        </div>
        
        <div class="stat-card lab">
            <div class="stat-icon"><i class="fas fa-flask"></i></div>
            <div class="stat-label">Lab Tests</div>
            <div class="stat-value">
                <span class="currency-symbol"><?= $currency ?></span>
                <span class="money-number"><?= number_format($lab_revenue, 0) ?></span>
            </div>
            <div class="stat-sub"><i class="fas fa-microscope"></i> <?= number_format($lab_count) ?> tests</div>
        </div>
        
        <div class="stat-card expenses">
            <div class="stat-icon"><i class="fas fa-receipt"></i></div>
            <div class="stat-label">Expenses</div>
            <div class="stat-value">
                <span class="currency-symbol"><?= $currency ?></span>
                <span class="money-number"><?= number_format($total_expenses, 0) ?></span>
            </div>
            <div class="stat-sub"><i class="fas fa-list"></i> <?= number_format($expenses_count) ?> paid</div>
        </div>
        
        <div class="stat-card profit <?= $net_profit < 0 ? 'loss' : '' ?>">
            <div class="stat-icon"><i class="fas fa-<?= $net_profit >= 0 ? 'chart-line' : 'exclamation-triangle' ?>"></i></div>
            <div class="stat-label"><?= $net_profit >= 0 ? 'Net Profit' : 'Net Loss' ?></div>
            <div class="stat-value">
                <span class="currency-symbol"><?= $currency ?></span>
                <span class="money-number"><?= number_format(abs($net_profit), 0) ?></span>
            </div>
            <div class="stat-sub"><i class="fas fa-percentage"></i> <?= $profit_percentage ?>% margin</div>
        </div>
    </div>

    <!-- CHARTS -->
    <div class="chart-grid">
        <div class="chart-card">
            <div class="chart-header">
                <span class="chart-title"><i class="fas fa-calendar-alt"></i> Monthly Revenue</span>
                <span style="font-size:0.68rem;color:var(--text-secondary);font-weight:700;">Last 12 months</span>
            </div>
            <div class="chart-body"><canvas id="monthlyChart"></canvas></div>
        </div>
        
        <div class="chart-card">
            <div class="chart-header">
                <span class="chart-title"><i class="fas fa-calendar-day"></i> Daily Revenue</span>
                <span style="font-size:0.68rem;color:var(--text-secondary);font-weight:700;">Last 30 days</span>
            </div>
            <div class="chart-body"><canvas id="dailyChart"></canvas></div>
        </div>
    </div>

    <!-- ALL TRANSACTIONS TABLE -->
    <div class="table-card">
        <div class="table-header">
            <span class="title"><i class="fas fa-list"></i> All Transactions</span>
            <span class="count"><?= count($transactions) ?> records</span>
        </div>
        
        <div class="table-toolbar">
            <div class="table-toolbar-left">
                <div class="search-box" id="transSearchBox">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="transSearch" 
                           placeholder="Search bill #, customer, item name..."
                           oninput="filterTable('transTable', this.value, 'transCount')"
                           autocomplete="off">
                    <button type="button" class="search-clear" onclick="clearSearch('transTable', 'transSearch', 'transCount')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <span class="search-count" id="transCount">
                    <i class="fas fa-list"></i>
                    <span class="count-text"><?= count($transactions) ?> records</span>
                </span>
            </div>
            <div class="table-toolbar-right">
                <button type="button" class="scroll-btn" onclick="scrollTable('transWrapper', 'left')">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button type="button" class="scroll-btn" onclick="scrollTable('transWrapper', 'right')">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
        
        <div class="table-scroll-wrapper" id="transWrapper">
            <table class="data-table" id="transTable" style="min-width:1500px;">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>Reference #</th>
                        <th style="text-align:center;">Type</th>
                        <th>Customer / Patient</th>
                        <th style="text-align:center;">Items</th>
                        <th>Item Details</th>
                        <th>Payment</th>
                        <th style="text-align:center;">Status</th>
                        <th>Received By</th>
                        <th>Branch</th>
                        <th style="text-align:right;">Amount</th>
                        <th>Date & Time</th>
                        <th style="text-align:center;">View</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($transactions) > 0): ?>
                        <?php $row_num = 1; foreach ($transactions as $trans): 
                            $is_otc = ($trans['transaction_type'] ?? '') === 'otc';
                            $status = strtolower($trans['status'] ?? 'pending');
                            $status_class = 'pending';
                            if ($status === 'paid') $status_class = 'paid';
                            elseif ($status === 'partial') $status_class = 'partial';
                            elseif ($status === 'cancelled') $status_class = 'cancelled';
                            
                            $role = strtolower($trans['received_by_role'] ?? 'user');
                            $name_parts = explode(' ', trim($trans['received_by_name'] ?? 'N/A'));
                            $initials = count($name_parts) >= 2 
                                ? strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1))
                                : strtoupper(substr($trans['received_by_name'] ?? 'NA', 0, 2));
                            
                            $item_types_str = $trans['item_types'] ?? '';
                            $items_summary = $trans['items_summary'] ?? '';
                            $item_count = (int)($trans['item_count'] ?? 0);
                            $item_lines = !empty($items_summary) ? explode("\n", $items_summary) : [];
                        ?>
                            <tr>
                                <td style="text-align:center;font-weight:700;color:var(--text-secondary);"><?= $row_num++ ?></td>
                                <td class="searchable-cell">
                                    <span style="font-size:0.68rem;color:var(--primary);font-weight:800;background:var(--primary-bg);padding:3px 7px;border-radius:5px;display:inline-block;font-family:var(--font-mono);">
                                        <?= htmlspecialchars($trans['reference_number'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td style="text-align:center;" class="searchable-cell">
                                    <?php if ($is_otc): ?>
                                        <span class="type-badge otc"><i class="fas fa-shopping-cart"></i> OTC</span>
                                    <?php else: ?>
                                        <span class="type-badge bill"><i class="fas fa-file-invoice"></i> BILL</span>
                                    <?php endif; ?>
                                </td>
                                <td class="searchable-cell">
                                    <div style="font-weight:600;font-size:0.75rem;"><?= htmlspecialchars($trans['customer_name'] ?? 'Walk-in') ?></div>
                                    <?php if (!empty($trans['customer_phone'])): ?>
                                        <div style="font-size:0.62rem;color:var(--text-secondary);">
                                            <i class="fas fa-phone"></i> <?= htmlspecialchars($trans['customer_phone']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;" class="searchable-cell">
                                    <div style="font-weight:800;color:var(--primary);font-family:var(--font-mono);font-size:0.85rem;">
                                        <?= $item_count ?>
                                    </div>
                                    <div style="font-size:0.55rem;font-weight:700;text-transform:uppercase;color:var(--text-secondary);">
                                        <?= $is_otc ? 'Meds' : 'Items' ?>
                                    </div>
                                </td>
                                <td class="searchable-cell item-details-cell">
                                    <?php if (count($item_lines) > 0): ?>
                                        <div class="item-list">
                                            <?php foreach ($item_lines as $line): 
                                                $line = trim($line);
                                                if (empty($line)) continue;
                                                $item_name = $line;
                                                $item_qty = '';
                                                if (preg_match('/^(.+?)\s*\((\d+)\)\s*$/', $line, $m)) {
                                                    $item_name = trim($m[1]);
                                                    $item_qty = $m[2];
                                                }
                                            ?>
                                                <div class="item-line">
                                                    <span class="item-bullet">•</span>
                                                    <span class="item-name"><?= htmlspecialchars($item_name) ?></span>
                                                    <?php if ($item_qty): ?>
                                                        <span class="item-qty">×<?= htmlspecialchars($item_qty) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php if (!empty($item_types_str)): 
                                            $types = array_map('trim', explode(',', $item_types_str));
                                        ?>
                                            <div style="display:flex;flex-wrap:wrap;gap:2px;margin-top:4px;">
                                                <?php foreach ($types as $type): ?>
                                                    <span class="item-type-tag <?= htmlspecialchars($type) ?>">
                                                        <?= htmlspecialchars(str_replace('_', ' ', $type)) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color:var(--text-secondary);font-size:0.68rem;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="searchable-cell">
                                    <span class="payment-badge">
                                        <i class="fas fa-credit-card"></i>
                                        <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $trans['payment_method'] ?? 'Cash'))) ?>
                                    </span>
                                </td>
                                <td style="text-align:center;" class="searchable-cell">
                                    <?php if ($status_class === 'paid'): ?>
                                        <span class="status-badge paid"><i class="fas fa-check-circle"></i> PAID</span>
                                    <?php elseif ($status_class === 'partial'): ?>
                                        <span class="status-badge partial"><i class="fas fa-hourglass-half"></i> PARTIAL</span>
                                    <?php elseif ($status_class === 'cancelled'): ?>
                                        <span class="status-badge cancelled"><i class="fas fa-times-circle"></i> CANCELLED</span>
                                    <?php else: ?>
                                        <span class="status-badge pending"><i class="fas fa-clock"></i> PENDING</span>
                                    <?php endif; ?>
                                </td>
                                <td class="searchable-cell">
                                    <div class="received-by">
                                        <div class="received-by-avatar"><?= htmlspecialchars($initials) ?></div>
                                        <div class="received-by-info">
                                            <span class="received-by-name"><?= htmlspecialchars($trans['received_by_name'] ?? 'N/A') ?></span>
                                            <span class="received-by-role">
                                                <span class="role-tag <?= htmlspecialchars($role) ?>"><?= htmlspecialchars(strtoupper($role)) ?></span>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td class="searchable-cell">
                                    <span style="font-size:0.7rem;color:var(--text-secondary);">
                                        <i class="fas fa-store-alt"></i> <?= htmlspecialchars($trans['branch_name'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td class="money-cell">
                                    <span class="currency-prefix"><?= $currency ?></span><?= number_format($trans['amount'] ?? 0, 0) ?>
                                </td>
                                <td>
                                    <div style="font-size:0.68rem;font-weight:600;"><?= date('H:i', strtotime($trans['updated_at'])) ?></div>
                                    <div style="font-size:0.58rem;color:var(--text-secondary);"><?= date('d M Y', strtotime($trans['updated_at'])) ?></div>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($is_otc): ?>
                                            <a href="view_otc.php?id=<?= $trans['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                               class="btn-action view" title="View OTC Sale">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="view_bill.php?id=<?= $trans['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                               class="btn-action view" title="View Bill">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="13" style="text-align:center;padding:50px 20px;color:var(--text-secondary);">
                                <i class="fas fa-inbox" style="font-size:2.5rem;opacity:0.3;display:block;margin-bottom:12px;"></i>
                                <p style="font-weight:600;">No transactions found</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- REVENUE BREAKDOWN -->
    <div class="table-card">
        <div class="table-header">
            <span class="title"><i class="fas fa-list"></i> Revenue Breakdown by Source</span>
            <span class="count">Total: <?= $currency ?> <?= number_format($total_revenue, 0) ?></span>
        </div>
        <div class="table-scroll-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Source</th>
                        <th style="text-align:right;">Revenue</th>
                        <th style="text-align:right;">% of Total</th>
                        <th style="text-align:right;">Transactions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span class="dot-indicator" style="background:#0B5ED7;"></span> Patient Bills</td>
                        <td class="money-cell"><span class="currency-prefix"><?= $currency ?></span><?= number_format($patient_bills_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--text-secondary);font-weight:600;"><?= $total_revenue > 0 ? round(($patient_bills_revenue / $total_revenue) * 100, 1) : 0 ?>%</td>
                        <td style="text-align:right;color:var(--text-secondary);font-weight:600;"><?= number_format($patient_bills_count) ?></td>
                    </tr>
                    <tr>
                        <td><span class="dot-indicator" style="background:#0891B2;"></span> OTC Sales</td>
                        <td class="money-cell"><span class="currency-prefix"><?= $currency ?></span><?= number_format($otc_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--text-secondary);font-weight:600;"><?= $total_revenue > 0 ? round(($otc_revenue / $total_revenue) * 100, 1) : 0 ?>%</td>
                        <td style="text-align:right;color:var(--text-secondary);font-weight:600;"><?= number_format($otc_count) ?></td>
                    </tr>
                    <tr style="background:var(--primary-bg);">
                        <td><span class="dot-indicator" style="background:#7C3AED;"></span> Prescriptions <span style="font-size:0.62rem;color:var(--text-secondary);font-weight:400;">(in Bills)</span></td>
                        <td class="money-cell" style="color:#7C3AED;"><span class="currency-prefix"><?= $currency ?></span><?= number_format($prescription_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--text-secondary);font-weight:600;">—</td>
                        <td style="text-align:right;color:var(--text-secondary);font-weight:600;"><?= number_format($prescription_count) ?></td>
                    </tr>
                    <tr>
                        <td style="padding-left:28px;"><span class="dot-indicator" style="background:#059669;"></span> Consultation</td>
                        <td class="money-cell" style="color:#059669;"><span class="currency-prefix"><?= $currency ?></span><?= number_format($consultation_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--text-secondary);font-weight:600;"><?= $total_revenue > 0 ? round(($consultation_revenue / $total_revenue) * 100, 1) : 0 ?>%</td>
                        <td style="text-align:right;color:var(--text-secondary);font-weight:600;"><?= number_format($consultation_count) ?></td>
                    </tr>
                    <tr>
                        <td style="padding-left:28px;"><span class="dot-indicator" style="background:#3B82F6;"></span> Lab Tests</td>
                        <td class="money-cell" style="color:#3B82F6;"><span class="currency-prefix"><?= $currency ?></span><?= number_format($lab_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--text-secondary);font-weight:600;"><?= $total_revenue > 0 ? round(($lab_revenue / $total_revenue) * 100, 1) : 0 ?>%</td>
                        <td style="text-align:right;color:var(--text-secondary);font-weight:600;"><?= number_format($lab_count) ?></td>
                    </tr>
                    <tr>
                        <td style="padding-left:28px;"><span class="dot-indicator" style="background:#D97706;"></span> Medications</td>
                        <td class="money-cell" style="color:#D97706;"><span class="currency-prefix"><?= $currency ?></span><?= number_format($medication_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--text-secondary);font-weight:600;"><?= $total_revenue > 0 ? round(($medication_revenue / $total_revenue) * 100, 1) : 0 ?>%</td>
                        <td style="text-align:right;color:var(--text-secondary);font-weight:600;"><?= number_format($medication_count) ?></td>
                    </tr>
                    <tr>
                        <td style="padding-left:28px;"><span class="dot-indicator" style="background:#0D9488;"></span> Procedures</td>
                        <td class="money-cell" style="color:#0D9488;"><span class="currency-prefix"><?= $currency ?></span><?= number_format($procedure_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--text-secondary);font-weight:600;"><?= $total_revenue > 0 ? round(($procedure_revenue / $total_revenue) * 100, 1) : 0 ?>%</td>
                        <td style="text-align:right;color:var(--text-secondary);font-weight:600;"><?= number_format($procedure_count) ?></td>
                    </tr>
                    <tr>
                        <td style="padding-left:28px;"><span class="dot-indicator" style="background:#64748B;"></span> Registration</td>
                        <td class="money-cell" style="color:#64748B;"><span class="currency-prefix"><?= $currency ?></span><?= number_format($registration_revenue, 0) ?></td>
                        <td style="text-align:right;color:var(--text-secondary);font-weight:600;"><?= $total_revenue > 0 ? round(($registration_revenue / $total_revenue) * 100, 1) : 0 ?>%</td>
                        <td style="text-align:right;color:var(--text-secondary);font-weight:600;"><?= number_format($registration_count) ?></td>
                    </tr>
                    <tr class="total-row">
                        <td style="font-weight:800;font-size:0.85rem;"><i class="fas fa-calculator"></i> TOTAL REVENUE</td>
                        <td style="text-align:right;font-weight:800;font-size:0.85rem;font-family:var(--font-mono);">
                            <?= $currency ?> <?= number_format($total_revenue, 0) ?>
                        </td>
                        <td style="text-align:right;font-weight:800;font-size:0.85rem;">100%</td>
                        <td style="text-align:right;font-weight:800;font-size:0.85rem;"><?= number_format($total_transactions) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- EXPENSES TABLE - RED THEME - AMOUNT COLUMN REMOVED -->
    <div class="table-card expenses-red">
        <div class="table-header">
            <span class="title">
                <i class="fas fa-receipt"></i> 
                All Expenses
                <span style="font-size:0.68rem;font-weight:600;background:rgba(255,255,255,0.2);padding:2px 8px;border-radius:8px;margin-left:6px;">
                    <?= count($expenses_list) ?> records
                </span>
            </span>
            <span class="count">
                <i class="fas fa-money-bill-wave"></i>
                Total: <?= $currency ?> <?= number_format($total_expenses, 0) ?>
            </span>
        </div>
        
        <?php if (count($expenses_list) > 0): ?>
        <div class="table-toolbar">
            <div class="table-toolbar-left">
                <div class="search-box" id="expSearchBox">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="expSearch" 
                           placeholder="Search expense #, category, recorded by..."
                           oninput="filterTable('expTable', this.value, 'expCount')"
                           autocomplete="off">
                    <button type="button" class="search-clear" onclick="clearSearch('expTable', 'expSearch', 'expCount')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <span class="search-count" id="expCount">
                    <i class="fas fa-list"></i>
                    <span class="count-text"><?= count($expenses_list) ?> records</span>
                </span>
            </div>
            <div class="table-toolbar-right">
                <button type="button" class="scroll-btn" onclick="scrollTable('expWrapper', 'left')">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button type="button" class="scroll-btn" onclick="scrollTable('expWrapper', 'right')">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
        
        <div class="table-scroll-wrapper" id="expWrapper">
            <table class="data-table" id="expTable" style="min-width:1200px;">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>Expense #</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Payment</th>
                        <th style="text-align:center;">Status</th>
                        <th>Recorded By</th>
                        <th>Branch</th>
                        <th>Date</th>
                        <th style="text-align:center;">View</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $exp_num = 1; foreach ($expenses_list as $exp): 
                        $role = strtolower($exp['recorded_by_role'] ?? 'user');
                        $name_parts = explode(' ', trim($exp['recorded_by_name'] ?? 'N/A'));
                        $initials = count($name_parts) >= 2 
                            ? strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1))
                            : strtoupper(substr($exp['recorded_by_name'] ?? 'NA', 0, 2));
                    ?>
                        <tr>
                            <td style="text-align:center;font-weight:700;color:var(--text-secondary);"><?= $exp_num++ ?></td>
                            <td class="searchable-cell">
                                <span class="expense-ref-badge">
                                    <?= htmlspecialchars($exp['expense_number'] ?? 'N/A') ?>
                                </span>
                            </td>
                            <td class="searchable-cell">
                                <span class="expense-category-badge">
                                    <i class="fas fa-tag"></i> <?= htmlspecialchars($exp['category'] ?? 'N/A') ?>
                                </span>
                            </td>
                            <td class="searchable-cell" style="max-width:250px;">
                                <div style="font-size:0.75rem;font-weight:500;"><?= htmlspecialchars($exp['description'] ?? 'N/A') ?></div>
                            </td>
                            <td class="searchable-cell">
                                <span class="payment-badge">
                                    <i class="fas fa-credit-card"></i>
                                    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $exp['payment_method'] ?? 'Cash'))) ?>
                                </span>
                            </td>
                            <td style="text-align:center;" class="searchable-cell">
                                <?php if ($exp['status'] === 'paid'): ?>
                                    <span class="status-badge paid"><i class="fas fa-check-circle"></i> PAID</span>
                                <?php elseif ($exp['status'] === 'pending'): ?>
                                    <span class="status-badge pending"><i class="fas fa-clock"></i> PENDING</span>
                                <?php else: ?>
                                    <span class="status-badge cancelled"><?= strtoupper($exp['status']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="searchable-cell">
                                <div class="received-by">
                                    <div class="received-by-avatar red">
                                        <?= htmlspecialchars($initials) ?>
                                    </div>
                                    <div class="received-by-info">
                                        <span class="received-by-name"><?= htmlspecialchars($exp['recorded_by_name'] ?? 'N/A') ?></span>
                                        <span class="received-by-role">
                                            <span class="role-tag <?= htmlspecialchars($role) ?>"><?= htmlspecialchars(strtoupper($role)) ?></span>
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td class="searchable-cell">
                                <span style="font-size:0.7rem;color:var(--text-secondary);">
                                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($exp['branch_name'] ?? 'N/A') ?>
                                </span>
                            </td>
                            <td>
                                <div style="font-size:0.68rem;font-weight:600;"><?= date('d M Y', strtotime($exp['payment_date'])) ?></div>
                                <div style="font-size:0.58rem;color:var(--text-secondary);"><?= date('H:i', strtotime($exp['created_at'])) ?></div>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="view_expense.php?id=<?= $exp['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                       class="btn-action view" title="View Expense">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <tr class="total-row">
                        <td colspan="8" style="font-weight:800;font-size:0.85rem;text-align:right;color:#DC2626;">
                            <i class="fas fa-calculator"></i> TOTAL EXPENSES
                        </td>
                        <td colspan="2" style="text-align:center;font-weight:800;color:#DC2626;font-size:0.85rem;font-family:var(--font-mono);">
                            <?= $currency ?> <?= number_format($total_expenses, 0) ?>
                            <span style="font-size:0.7rem;font-weight:600;color:var(--text-secondary);margin-left:6px;">
                                (<?= number_format($expenses_count) ?> records)
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div style="padding:50px 20px;text-align:center;color:var(--text-secondary);">
            <i class="fas fa-receipt" style="font-size:3rem;opacity:0.3;display:block;margin-bottom:12px;"></i>
            <p style="font-weight:600;">No expenses found for this period</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- PROFIT SUMMARY -->
    <div class="table-card" style="border-color:<?= $net_profit >= 0 ? 'var(--success)' : 'var(--danger)' ?>;">
        <div class="table-header profit <?= $net_profit < 0 ? 'loss' : '' ?>">
            <span class="title"><i class="fas fa-chart-pie"></i> Profit Summary</span>
            <span class="count"><?= $profit_percentage ?>% Margin</span>
        </div>
        <div class="table-scroll-wrapper">
            <table class="data-table">
                <tbody>
                    <tr>
                        <td style="font-weight:600;font-size:0.85rem;padding:14px 18px;">
                            <i class="fas fa-money-bill-wave" style="color:var(--primary);margin-right:8px;"></i>
                            Total Revenue
                        </td>
                        <td style="text-align:right;font-weight:800;color:var(--primary);font-size:0.9rem;font-family:var(--font-mono);padding:14px 18px;">
                            <?= $currency ?> <?= number_format($total_revenue, 0) ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight:600;font-size:0.85rem;padding:14px 18px;">
                            <i class="fas fa-receipt" style="color:var(--danger);margin-right:8px;"></i>
                            Less: Total Expenses
                        </td>
                        <td style="text-align:right;font-weight:800;color:var(--danger);font-size:0.9rem;font-family:var(--font-mono);padding:14px 18px;">
                            - <?= $currency ?> <?= number_format($total_expenses, 0) ?>
                        </td>
                    </tr>
                    <tr class="total-row" style="border-top-color:<?= $net_profit >= 0 ? 'var(--success)' : 'var(--danger)' ?>;background:<?= $net_profit >= 0 ? 'var(--success-bg)' : 'var(--danger-bg)' ?> !important;">
                        <td style="font-weight:800;font-size:1rem;padding:16px 18px;color:<?= $net_profit >= 0 ? 'var(--success)' : 'var(--danger)' ?>;">
                            <i class="fas fa-<?= $net_profit >= 0 ? 'chart-line' : 'exclamation-triangle' ?>"></i>
                            <?= $net_profit >= 0 ? 'NET PROFIT' : 'NET LOSS' ?>
                        </td>
                        <td style="text-align:right;font-weight:900;font-size:1.1rem;color:<?= $net_profit >= 0 ? 'var(--success)' : 'var(--danger)' ?>;font-family:var(--font-mono);padding:16px 18px;">
                            <?= $currency ?> <?= number_format(abs($net_profit), 0) ?>
                        </td>
                    </tr>
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
    var amount = 300;
    wrapper.scrollBy({ left: direction === 'left' ? -amount : amount, behavior: 'smooth' });
}

document.addEventListener('DOMContentLoaded', function() {
    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    var textColor = isDark ? '#94A3B8' : '#64748B';
    var gridColor = isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';
    
    var ctxM = document.getElementById('monthlyChart')?.getContext('2d');
    if (ctxM && typeof Chart !== 'undefined') {
        new Chart(ctxM, {
            type: 'bar',
            data: {
                labels: <?= json_encode($monthly_labels) ?>,
                datasets: [
                    { 
                        label: 'Patient Bills', 
                        data: <?= json_encode($monthly_patient) ?>, 
                        backgroundColor: function(context) {
                            const chart = context.chart;
                            const {ctx, chartArea} = chart;
                            if (!chartArea) return '#0B5ED7';
                            const gradient = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top);
                            gradient.addColorStop(0, '#0A4CA8');
                            gradient.addColorStop(1, '#3B82F6');
                            return gradient;
                        },
                        borderRadius: 5, 
                        barPercentage: 0.7 
                    },
                    { label: 'OTC Sales', data: <?= json_encode($monthly_otc) ?>, backgroundColor: '#0891B2', borderRadius: 5, barPercentage: 0.7 },
                    { label: 'Expenses', data: <?= json_encode($monthly_expenses) ?>, backgroundColor: '#DC2626', borderRadius: 5, barPercentage: 0.7 }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top', labels: { font: { family: 'Inter', size: 10, weight: '700' }, boxWidth: 10, padding: 8, color: textColor, usePointStyle: true, pointStyle: 'circle' } },
                    tooltip: { 
                        backgroundColor: '#0B5ED7', titleColor: '#FFFFFF', bodyColor: '#DBEAFE', padding: 10, cornerRadius: 8,
                        callbacks: { label: function(c) { return c.dataset.label + ': <?= $currency ?> ' + c.raw.toLocaleString(); } }
                    }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { callback: function(v) { return (v/1000).toFixed(0) + 'K'; }, font: { family: 'JetBrains Mono', size: 10, weight: '600' }, color: textColor }, grid: { color: gridColor } },
                    x: { grid: { display: false }, ticks: { font: { family: 'Inter', size: 10, weight: '600' }, color: textColor } }
                }
            }
        });
    }
    
    var ctxD = document.getElementById('dailyChart')?.getContext('2d');
    if (ctxD && typeof Chart !== 'undefined') {
        new Chart(ctxD, {
            type: 'line',
            data: {
                labels: <?= json_encode($daily_labels) ?>,
                datasets: [
                    { label: 'Patient Bills', data: <?= json_encode($daily_patient) ?>, borderColor: '#0B5ED7', backgroundColor: 'rgba(11, 94, 215, 0.1)', fill: true, tension: 0.4, pointRadius: 2, borderWidth: 2.5 },
                    { label: 'OTC Sales', data: <?= json_encode($daily_otc) ?>, borderColor: '#0891B2', backgroundColor: 'rgba(8, 145, 178, 0.1)', fill: true, tension: 0.4, pointRadius: 2, borderWidth: 2.5 }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top', labels: { font: { family: 'Inter', size: 10, weight: '700' }, boxWidth: 10, padding: 8, color: textColor, usePointStyle: true, pointStyle: 'circle' } },
                    tooltip: { 
                        backgroundColor: '#0B5ED7', titleColor: '#FFFFFF', bodyColor: '#DBEAFE', padding: 10, cornerRadius: 8,
                        callbacks: { label: function(c) { return c.dataset.label + ': <?= $currency ?> ' + c.raw.toLocaleString(); } }
                    }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { callback: function(v) { return (v/1000).toFixed(0) + 'K'; }, font: { family: 'JetBrains Mono', size: 10, weight: '600' }, color: textColor }, grid: { color: gridColor } },
                    x: { grid: { display: false }, ticks: { font: { family: 'Inter', size: 9, weight: '600' }, color: textColor, maxTicksLimit: 15 } }
                },
                interaction: { intersect: false, mode: 'index' }
            }
        });
    }
});

console.log('%c📊 Revenue Report V13 - AUDIT (VIEW ONLY)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
console.log('%c✅ AUDIT ROLE', 'font-size:13px; color:#34D399; font-weight:bold;');
console.log('%c✅ VIEW ONLY - No Edit/Delete', 'font-size:13px; color:#FCD34D; font-weight:bold;');
console.log('%c✅ Removed "Medications" card', 'font-size:13px; color:#34D399;');
console.log('%c✅ Removed "Amount" column from Expenses table', 'font-size:13px; color:#34D399;');
console.log('%c💰 Total Revenue: <?= $currency ?> <?= number_format($total_revenue, 0) ?>', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>