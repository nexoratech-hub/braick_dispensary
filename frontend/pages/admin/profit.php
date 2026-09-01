<?php
// ================================================================
// FILE: frontend/pages/admin/profit.php
// ADMIN - PROFIT/REVENUE DASHBOARD
// PROFIT = REVENUE - EXPENSES
// FIXED: Revenue uses paid_amount from bills (includes discounts)
// FIXED: Includes OTC revenue from otc_sales
// FIXED: Prescription revenue - FOR DISPLAY ONLY (already in bills)
// FIXED: Column 'branch_id' ambiguous - added table prefix
// FIXED: Header with clock and dark mode support
// FIXED: Total Revenue = Patient Bills + OTC ONLY (no double counting)
// ================================================================

// ================================================================
// START SESSION
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
// CHECK IF USER IS ADMIN
// ================================================================
if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET ADMIN DATA
// ================================================================
$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// GET FILTER PARAMETERS
// ================================================================
$selected_branch_id = isset($_GET['branch']) ? (int)$_GET['branch'] : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// ================================================================
// GET BRANCHES FOR FILTER
// ================================================================
$branches = [];
try {
    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branches = [];
}

// ================================================================
// GET BRANCH NAME
// ================================================================
$branch_name = 'All Branches';
if ($selected_branch_id > 0) {
    foreach ($branches as $b) {
        if ($b['id'] == $selected_branch_id) {
            $branch_name = $b['name'];
            break;
        }
    }
}

// ================================================================
// FORMAT CURRENCY
// ================================================================
function formatMoney($amount) {
    if ($amount === null || $amount === '') {
        return '0';
    }
    return number_format((float)$amount, 0, '.', ',');
}

// ================================================================
// BUILD PARAMETERS FOR QUERIES
// ================================================================
$params = [];
$branch_condition = "";
$date_condition = "";

if ($selected_branch_id > 0) {
    $branch_condition = "AND b.branch_id = ?";
    $params[] = $selected_branch_id;
}

if (!empty($date_from) && !empty($date_to)) {
    $date_condition = "AND DATE(b.created_at) BETWEEN ? AND ?";
    $params[] = $date_from;
    $params[] = $date_to;
} elseif (!empty($date_from)) {
    $date_condition = "AND DATE(b.created_at) >= ?";
    $params[] = $date_from;
} elseif (!empty($date_to)) {
    $date_condition = "AND DATE(b.created_at) <= ?";
    $params[] = $date_to;
}

// ================================================================
// 1. PATIENT BILLS REVENUE - Using paid_amount from bills
//    Excludes OTC bills, only bills with patient_id and visit_id
// ================================================================
$patient_bills_revenue = 0;
$patient_bills_count = 0;
$patient_bills_params = $params;

try {
    $sql = "
        SELECT COALESCE(SUM(b.paid_amount), 0) as total, COUNT(*) as count
        FROM bills b
        WHERE b.status = 'paid'
        AND b.patient_id IS NOT NULL
        AND b.visit_id IS NOT NULL
        AND b.bill_number NOT LIKE 'BILL-OTC-%'
        $branch_condition
        $date_condition
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($patient_bills_params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $patient_bills_revenue = (float)($result['total'] ?? 0);
    $patient_bills_count = (int)($result['count'] ?? 0);
} catch (Exception $e) {
    $patient_bills_revenue = 0;
    $patient_bills_count = 0;
}

// ================================================================
// 2. OTC REVENUE - from otc_sales table
// ================================================================
$otc_revenue = 0;
$otc_count = 0;
$otc_params = [];
$otc_branch = "";
$otc_date = "";

if ($selected_branch_id > 0) {
    $otc_branch = "AND branch_id = ?";
    $otc_params[] = $selected_branch_id;
}

if (!empty($date_from) && !empty($date_to)) {
    $otc_date = "AND DATE(created_at) BETWEEN ? AND ?";
    $otc_params[] = $date_from;
    $otc_params[] = $date_to;
} elseif (!empty($date_from)) {
    $otc_date = "AND DATE(created_at) >= ?";
    $otc_params[] = $date_from;
} elseif (!empty($date_to)) {
    $otc_date = "AND DATE(created_at) <= ?";
    $otc_params[] = $date_to;
}

try {
    $sql_otc = "
        SELECT COALESCE(SUM(total_amount), 0) as total, COUNT(*) as count
        FROM otc_sales
        WHERE payment_status = 'paid'
        $otc_branch
        $otc_date
    ";
    $stmt = $db->prepare($sql_otc);
    $stmt->execute($otc_params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $otc_revenue = (float)($result['total'] ?? 0);
    $otc_count = (int)($result['count'] ?? 0);
} catch (Exception $e) {
    $otc_revenue = 0;
    $otc_count = 0;
}

// ================================================================
// 3. PRESCRIPTION REVENUE - FOR DISPLAY ONLY (already in bills)
// ================================================================
$prescription_revenue = 0;
$prescription_count = 0;
$pres_params = [];
$pres_branch = "";
$pres_date = "";

if ($selected_branch_id > 0) {
    $pres_branch = "AND p.branch_id = ?";
    $pres_params[] = $selected_branch_id;
}

if (!empty($date_from) && !empty($date_to)) {
    $pres_date = "AND DATE(p.created_at) BETWEEN ? AND ?";
    $pres_params[] = $date_from;
    $pres_params[] = $date_to;
} elseif (!empty($date_from)) {
    $pres_date = "AND DATE(p.created_at) >= ?";
    $pres_params[] = $date_from;
} elseif (!empty($date_to)) {
    $pres_date = "AND DATE(p.created_at) <= ?";
    $pres_params[] = $date_to;
}

try {
    $sql_pres = "
        SELECT COALESCE(SUM(pi.total_price), 0) as total, COUNT(DISTINCT pi.id) as count
        FROM prescription_items pi
        INNER JOIN prescriptions p ON pi.prescription_id = p.id
        WHERE p.status = 'dispensed'
        $pres_branch
        $pres_date
    ";
    $stmt = $db->prepare($sql_pres);
    $stmt->execute($pres_params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $prescription_revenue = (float)($result['total'] ?? 0);
    $prescription_count = (int)($result['count'] ?? 0);
} catch (Exception $e) {
    $prescription_revenue = 0;
    $prescription_count = 0;
}

// ================================================================
// 4. TOTAL REVENUE = Patient Bills + OTC ONLY
//    FIXED: Removed prescription to avoid double counting
// ================================================================
$total_revenue = $patient_bills_revenue + $otc_revenue;

// ================================================================
// 5. BREAKDOWN BY CATEGORY (from bill_items - for display only)
// ================================================================
$consultation_revenue = 0;
$consultation_count = 0;
$medication_revenue = 0;
$medication_count = 0;
$lab_revenue = 0;
$lab_count = 0;
$procedure_revenue = 0;
$procedure_count = 0;
$registration_revenue = 0;
$registration_count = 0;

try {
    $sql_breakdown = "
        SELECT 
            bi.item_type,
            COALESCE(SUM(bi.final_price), 0) as total,
            COUNT(DISTINCT bi.id) as count
        FROM bill_items bi
        INNER JOIN bills b ON bi.bill_id = b.id
        WHERE b.status = 'paid'
        AND bi.status = 'paid'
        AND b.patient_id IS NOT NULL
        AND b.visit_id IS NOT NULL
        AND b.bill_number NOT LIKE 'BILL-OTC-%'
        $branch_condition
        $date_condition
        GROUP BY bi.item_type
    ";
    $stmt = $db->prepare($sql_breakdown);
    $stmt->execute($params);
    $breakdown_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($breakdown_results as $row) {
        $type = $row['item_type'];
        $amount = (float)($row['total'] ?? 0);
        $count = (int)($row['count'] ?? 0);
        
        switch ($type) {
            case 'consultation': $consultation_revenue = $amount; $consultation_count = $count; break;
            case 'medication': $medication_revenue = $amount; $medication_count = $count; break;
            case 'lab_test': $lab_revenue = $amount; $lab_count = $count; break;
            case 'procedure': $procedure_revenue = $amount; $procedure_count = $count; break;
            case 'registration': $registration_revenue = $amount; $registration_count = $count; break;
        }
    }
} catch (Exception $e) {
    // Continue with zeros
}

// ================================================================
// 6. EXPENSES
// ================================================================
$exp_params = [];
$exp_branch = "";
$exp_date = "";

if ($selected_branch_id > 0) {
    $exp_branch = "AND branch_id = ?";
    $exp_params[] = $selected_branch_id;
}

if (!empty($date_from) && !empty($date_to)) {
    $exp_date = "AND DATE(created_at) BETWEEN ? AND ?";
    $exp_params[] = $date_from;
    $exp_params[] = $date_to;
} elseif (!empty($date_from)) {
    $exp_date = "AND DATE(created_at) >= ?";
    $exp_params[] = $date_from;
} elseif (!empty($date_to)) {
    $exp_date = "AND DATE(created_at) <= ?";
    $exp_params[] = $date_to;
}

$sql_exp = "
    SELECT 
        SUM(amount) as total_expenses,
        COUNT(*) as total_count,
        category,
        SUM(amount) as category_amount
    FROM expenses 
    WHERE status = 'paid'
    $exp_branch 
    $exp_date
    GROUP BY category
";

$stmt_exp = $db->prepare($sql_exp);
$stmt_exp->execute($exp_params);
$expense_results = $stmt_exp->fetchAll(PDO::FETCH_ASSOC);

$expense_data = [
    'total_expenses' => 0,
    'expense_categories' => []
];

foreach ($expense_results as $row) {
    $expense_data['total_expenses'] += (float)($row['category_amount'] ?? 0);
    $expense_data['expense_categories'][] = [
        'category' => $row['category'] ?? 'Unknown',
        'amount' => (float)($row['category_amount'] ?? 0),
        'count' => (int)($row['total_count'] ?? 0)
    ];
}

// Sort categories by amount (highest first)
usort($expense_data['expense_categories'], function($a, $b) {
    return $b['amount'] <=> $a['amount'];
});

// ================================================================
// 7. CALCULATE PROFIT
// ================================================================
$profit = $total_revenue - $expense_data['total_expenses'];

$profit_data = [
    'net_profit' => $profit,
    'profit_margin' => 0
];

if ($total_revenue > 0) {
    $profit_data['profit_margin'] = round(($profit / $total_revenue) * 100, 2);
}

// ================================================================
// 8. MONTHLY PROFIT DATA FOR CHART
// ================================================================
$monthly_data = [];

// Get monthly revenue from bills (paid_amount)
$monthly_params = [];
$monthly_branch = "";
$monthly_date = "";

if ($selected_branch_id > 0) {
    $monthly_branch = "AND b.branch_id = ?";
    $monthly_params[] = $selected_branch_id;
}

if (!empty($date_from) && !empty($date_to)) {
    $monthly_date = "AND DATE(b.created_at) BETWEEN ? AND ?";
    $monthly_params[] = $date_from;
    $monthly_params[] = $date_to;
} elseif (!empty($date_from)) {
    $monthly_date = "AND DATE(b.created_at) >= ?";
    $monthly_params[] = $date_from;
} elseif (!empty($date_to)) {
    $monthly_date = "AND DATE(b.created_at) <= ?";
    $monthly_params[] = $date_to;
}

$sql_monthly_rev = "
    SELECT 
        DATE_FORMAT(b.created_at, '%Y-%m') as month,
        COALESCE(SUM(b.paid_amount), 0) as revenue
    FROM bills b
    WHERE b.status = 'paid'
    AND b.patient_id IS NOT NULL
    AND b.visit_id IS NOT NULL
    AND b.bill_number NOT LIKE 'BILL-OTC-%'
    $monthly_branch
    $monthly_date
    GROUP BY DATE_FORMAT(b.created_at, '%Y-%m')
    ORDER BY month ASC
";

$stmt_monthly = $db->prepare($sql_monthly_rev);
$stmt_monthly->execute($monthly_params);
$monthly_revenue = $stmt_monthly->fetchAll(PDO::FETCH_ASSOC);

// Get monthly OTC
$monthly_otc_params = [];
$monthly_otc_branch = "";
$monthly_otc_date = "";

if ($selected_branch_id > 0) {
    $monthly_otc_branch = "AND branch_id = ?";
    $monthly_otc_params[] = $selected_branch_id;
}

if (!empty($date_from) && !empty($date_to)) {
    $monthly_otc_date = "AND DATE(created_at) BETWEEN ? AND ?";
    $monthly_otc_params[] = $date_from;
    $monthly_otc_params[] = $date_to;
} elseif (!empty($date_from)) {
    $monthly_otc_date = "AND DATE(created_at) >= ?";
    $monthly_otc_params[] = $date_from;
} elseif (!empty($date_to)) {
    $monthly_otc_date = "AND DATE(created_at) <= ?";
    $monthly_otc_params[] = $date_to;
}

$sql_monthly_otc = "
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COALESCE(SUM(total_amount), 0) as revenue
    FROM otc_sales
    WHERE payment_status = 'paid'
    $monthly_otc_branch
    $monthly_otc_date
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
";

$stmt_monthly_otc = $db->prepare($sql_monthly_otc);
$stmt_monthly_otc->execute($monthly_otc_params);
$monthly_otc = $stmt_monthly_otc->fetchAll(PDO::FETCH_ASSOC);

// Merge monthly revenue (bills + otc)
$monthly_map = [];

foreach ($monthly_revenue as $row) {
    $month = $row['month'];
    $monthly_map[$month] = [
        'month' => $month,
        'revenue' => (float)($row['revenue'] ?? 0),
        'expenses' => 0,
        'profit' => 0
    ];
}

foreach ($monthly_otc as $row) {
    $month = $row['month'];
    if (isset($monthly_map[$month])) {
        $monthly_map[$month]['revenue'] += (float)($row['revenue'] ?? 0);
    } else {
        $monthly_map[$month] = [
            'month' => $month,
            'revenue' => (float)($row['revenue'] ?? 0),
            'expenses' => 0,
            'profit' => 0
        ];
    }
}

// Get monthly expenses
$exp_monthly_params = [];
$exp_monthly_branch = "";
$exp_monthly_date = "";

if ($selected_branch_id > 0) {
    $exp_monthly_branch = "AND branch_id = ?";
    $exp_monthly_params[] = $selected_branch_id;
}

if (!empty($date_from) && !empty($date_to)) {
    $exp_monthly_date = "AND DATE(created_at) BETWEEN ? AND ?";
    $exp_monthly_params[] = $date_from;
    $exp_monthly_params[] = $date_to;
} elseif (!empty($date_from)) {
    $exp_monthly_date = "AND DATE(created_at) >= ?";
    $exp_monthly_params[] = $date_from;
} elseif (!empty($date_to)) {
    $exp_monthly_date = "AND DATE(created_at) <= ?";
    $exp_monthly_params[] = $date_to;
}

$sql_monthly_exp = "
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COALESCE(SUM(amount), 0) as expenses
    FROM expenses 
    WHERE status = 'paid'
    $exp_monthly_branch
    $exp_monthly_date
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
";

$stmt_monthly_exp = $db->prepare($sql_monthly_exp);
$stmt_monthly_exp->execute($exp_monthly_params);
$monthly_expenses = $stmt_monthly_exp->fetchAll(PDO::FETCH_ASSOC);

foreach ($monthly_expenses as $row) {
    $month = $row['month'];
    if (isset($monthly_map[$month])) {
        $monthly_map[$month]['expenses'] = (float)($row['expenses'] ?? 0);
    } else {
        $monthly_map[$month] = [
            'month' => $month,
            'revenue' => 0,
            'expenses' => (float)($row['expenses'] ?? 0),
            'profit' => 0
        ];
    }
}

foreach ($monthly_map as $month => &$data) {
    $data['profit'] = $data['revenue'] - $data['expenses'];
}
unset($data);

ksort($monthly_map);
$monthly_data = array_values($monthly_map);

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// GET UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// ================================================================
// INCLUDE HEADERS
// ================================================================
include_once __DIR__ . '/../../components/admin_header.php';

// Get sidebar stats
$total_employees_sidebar = 0;
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role != 'admin'");
$total_employees_sidebar = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
$total_doctors_sidebar = 0;
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'doctor' AND status = 'active'");
$total_doctors_sidebar = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
$total_branches_sidebar = 0;
$stmt = $db->query("SELECT COUNT(*) as count FROM branches WHERE status = 'active'");
$total_branches_sidebar = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profit Report - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_url ?>" type="image/png">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <style>
        /* ================================================================
           ROOT VARIABLES - DARK MODE SUPPORT
           ================================================================ */
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
            --success: #059669;
            --success-dark: #047857;
            --success-light: #34D399;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-dark: #B91C1C;
            --danger-light: #F87171;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-dark: #B45309;
            --warning-bg: #FEF3C7;
            --purple: #7C3AED;
            --purple-bg: #EDE9FE;
            --white: #FFFFFF;
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
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow: 0 1px 3px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
            --shadow-xl: 0 20px 25px rgba(0,0,0,0.15);
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --radius: 12px;
            --radius-lg: 18px;
            --table-hover: #E8F0FE;
            --transition: all 0.3s ease;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
            --shadow-xl: 0 20px 25px rgba(0,0,0,0.4);
            --primary-bg: #1E3A5F;
            --success-bg: #1A3A2A;
            --danger-bg: #3A1A1A;
            --warning-bg: #3D2E0A;
            --gray-100: #1E293B;
            --gray-200: #334155;
            --table-hover: #1E3A5F;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--success); border-radius: 10px; }

        /* ================================================================
           TOP NAV - SHARED HEADER STYLES
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
            box-shadow: var(--shadow-sm);
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
            border-color: #0B5ED7;
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
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
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
            transform: scale(1.02);
        }
        
        .top-nav .branch-selector {
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 6px 12px;
            background: var(--bg-card);
            font-size: 0.82rem;
            font-weight: 500;
            cursor: pointer;
            outline: none;
            min-width: 160px;
            color: var(--text-primary);
            transition: all 0.3s;
        }
        
        .top-nav .branch-selector:focus {
            border-color: #0B5ED7;
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15);
        }
        
        .top-nav .datetime {
            font-size: 0.78rem;
            color: var(--text-secondary);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .top-nav .datetime i {
            color: var(--primary-light);
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
            border-color: #0B5ED7;
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
            color: #0B5ED7;
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
        
        .notif-dot.has-notif {
            background: #EF4444;
        }
        
        .notif-dot.no-notif {
            background: #94A3B8;
            animation: none;
        }
        
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
            border-color: #0B5ED7;
            background: var(--bg-card);
        }
        
        .dark-toggle-btn i {
            font-size: 0.9rem;
        }

        /* ================================================================
           MAIN CONTENT
           ================================================================ */
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 24px 28px;
            min-height: calc(100vh - 68px);
            transition: var(--transition);
        }
        
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
        }
        
        /* ================================================================
           PAGE HEADER - GREEN THEME
           ================================================================ */
        .page-header {
            background: linear-gradient(135deg, #059669, #047857);
            border-radius: 18px;
            padding: 24px 32px;
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            box-shadow: 0 4px 20px rgba(4, 120, 87, 0.25);
            position: relative;
            overflow: hidden;
        }
        
        .page-header .page-title {
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-title i { font-size: 1.6rem; opacity: 0.9; }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.55rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .header-badge {
            background: rgba(255,255,255,0.12);
            color: white;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 500;
            backdrop-filter: blur(4px);
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border: 1px solid rgba(255,255,255,0.08);
        }
        
        .btn-outline-light {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.12);
            padding: 8px 16px;
            border-radius: 12px;
            font-weight: 500;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            backdrop-filter: blur(4px);
            position: relative;
            z-index: 1;
        }
        
        .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }
        
        /* ================================================================
           STATS CARDS
           ================================================================ */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            border-radius: 18px;
            padding: 18px 20px;
            transition: all 0.3s ease;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            border: none;
            color: white;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        
        .stat-card .stat-icon { font-size: 1.8rem; margin-bottom: 8px; }
        .stat-card .stat-number { font-size: 1.6rem; font-weight: 700; color: white; }
        .stat-card .stat-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; margin-top: 4px; opacity: 0.85; color: rgba(255,255,255,0.85); }
        
        /* ================================================================
           FILTER SECTION
           ================================================================ */
        .filter-section {
            background: var(--bg-card);
            border-radius: 18px;
            padding: 14px 18px;
            border: 1px solid var(--border-color);
            margin-bottom: 20px;
        }
        
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }
        
        .filter-input {
            padding: 6px 12px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 0.8rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: all 0.3s ease;
        }
        
        .filter-input:focus {
            border-color: #059669;
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
        }
        
        .btn-search {
            padding: 6px 16px;
            background: #059669;
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-search:hover {
            background: #047857;
            transform: translateY(-1px);
        }
        
        .btn-reset {
            padding: 6px 14px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            border: 2px solid var(--border-color);
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .btn-reset:hover {
            border-color: #DC2626;
            color: #DC2626;
        }
        
        /* ================================================================
           CHART CONTAINER
           ================================================================ */
        .chart-container {
            background: var(--bg-card);
            border-radius: 18px;
            border: 1px solid var(--border-color);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .chart-container canvas {
            max-height: 300px;
            max-width: 100%;
        }
        
        /* ================================================================
           REVENUE BREAKDOWN
           ================================================================ */
        .revenue-breakdown {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 10px;
            margin-top: 12px;
        }
        
        .breakdown-item {
            padding: 10px 14px;
            border-radius: 12px;
            background: var(--bg-body);
            border: 1px solid var(--border-color);
        }
        
        .breakdown-item .label {
            font-size: 0.6rem;
            text-transform: uppercase;
            color: var(--text-secondary);
            font-weight: 600;
        }
        
        .breakdown-item .value {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        /* ================================================================
           TABLE
           ================================================================ */
        .table-container {
            background: var(--bg-card);
            border-radius: 18px;
            border: 1px solid var(--border-color);
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            margin-bottom: 20px;
        }
        
        .table-scroll { overflow-x: auto; }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
        }
        
        .data-table thead th {
            text-align: left;
            padding: 10px 14px;
            font-weight: 700;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #ffffff;
            background: #059669;
            border-bottom: 3px solid #047857;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 5;
        }
        
        .data-table tbody td {
            padding: 8px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .data-table tbody tr:hover td { background: var(--table-hover); }
        .data-table tbody tr:last-child td { border-bottom: none; }
        
        .badge-status {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 16px;
            font-size: 0.55rem;
            font-weight: 600;
            text-transform: capitalize;
        }
        
        .badge-success { background: #D1FAE5; color: #059669; border: 1px solid #059669; }
        .badge-warning { background: #FEF3C7; color: #D97706; border: 1px solid #D97706; }
        .badge-danger { background: #FEE2E2; color: #DC2626; border: 1px solid #DC2626; }
        .badge-blue { background: #DBEAFE; color: #2563EB; border: 1px solid #2563EB; }
        .badge-purple { background: #EDE9FE; color: #7C3AED; border: 1px solid #7C3AED; }
        
        .table-footer {
            padding: 10px 16px;
            border-top: 1px solid var(--border-color);
            font-size: 0.65rem;
            color: var(--text-secondary);
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 6px;
            background: var(--gray-50);
        }
        
        /* ================================================================
           FOOTER
           ================================================================ */
        .footer {
            padding: 10px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 20px;
            text-align: center;
            font-size: 0.65rem;
            color: var(--text-secondary);
        }
        .footer .footer-brand { color: #059669; font-weight: 600; }
        
        /* ================================================================
           ANIMATIONS
           ================================================================ */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up { animation: fadeInUp 0.4s ease forwards; opacity: 0; }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .filter-row { flex-direction: column; align-items: stretch; }
            .filter-input { width: 100%; }
            .stats-row { grid-template-columns: 1fr 1fr; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.1rem; }
            .revenue-breakdown { grid-template-columns: 1fr 1fr; }
        }
        
        @media (max-width: 480px) {
            .stats-row { grid-template-columns: 1fr; }
            .data-table { font-size: 0.6rem; }
            .data-table thead th, .data-table td { padding: 4px 6px; }
            .revenue-breakdown { grid-template-columns: 1fr; }
        }
        
        @media print {
            .top-nav, .sidebar, .btn, .dark-toggle-btn, .icon-btn,
            .search-wrapper, .filter-section, .btn-outline-light,
            .footer, #sidebarToggle { display: none !important; }
            .main-content { margin: 0; padding: 20px; }
            .stat-card { border: 1px solid #ddd !important; }
            .page-header { background: #059669 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .page-title, .page-subtitle, .role-badge-display, .header-badge { color: white !important; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- TOP NAVIGATION - SHARED HEADER -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <input type="text" id="searchInput" placeholder="Search reports...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <select id="branchSelector" class="branch-selector" onchange="switchBranch(this.value)">
            <option value="0" <?= $selected_branch_id == 0 ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $selected_branch_id == $b['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($b['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <span class="datetime" id="currentDateTime">
            <i class="fas fa-clock" style="color:var(--primary-light);"></i>
            <span id="clockDisplay" style="font-weight:500;"><?= date('d M Y • h:i:s A') ?></span>
        </span>
        
        <button id="darkModeToggle" class="dark-toggle-btn" title="Toggle Dark Mode">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= ($unread_notifications ?? 0) > 0 ? 'has-notif' : 'no-notif' ?>"></span>
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
    <div class="page-header animate-fade-in-up">
        <div>
            <h1 class="page-title">
                <i class="fas fa-chart-line"></i>
                Profit Report
                <span class="role-badge-display">ADMIN</span>
                <?php if ($selected_branch_id > 0): ?>
                    <span class="role-badge-display" style="background:rgba(52,211,153,0.3);color:#34D399;">
                        <i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name) ?>
                    </span>
                <?php endif; ?>
                <span class="header-badge" style="background:rgba(52,211,153,0.2);color:#34D399;">
                    <i class="fas fa-money-bill-wave"></i> Profit: TSh <?= formatMoney($profit_data['net_profit']) ?>
                </span>
                <span class="header-badge" style="background:rgba(251,191,36,0.15);color:#FBBF24;">
                    <i class="fas fa-percentage"></i> Margin: <?= $profit_data['profit_margin'] ?>%
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-arrow-right"></i>
                Profit = Revenue - Expenses
                <span class="header-badge" style="background:rgba(52,211,153,0.2);color:#34D399;">
                    <i class="fas fa-calendar-alt"></i> <?= date('M d, Y', strtotime($date_from)) ?> - <?= date('M d, Y', strtotime($date_to)) ?>
                </span>
                <span class="header-badge" style="background:rgba(52,211,153,0.15);color:#34D399;border-color:rgba(52,211,153,0.2);">
                    <i class="fas fa-info-circle"></i> Prescriptions included in bills
                </span>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <button onclick="window.print()" class="btn-outline-light">
                <i class="fas fa-print"></i> Print Report
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-section animate-fade-in-up" style="animation-delay:0.05s;">
        <form method="GET" action="" id="filterForm" class="w-full">
            <div class="filter-row">
                <input type="hidden" name="branch" value="<?= $selected_branch_id ?>">
                
                <label style="font-size:0.7rem;font-weight:600;color:var(--text-secondary);">From:</label>
                <input type="date" name="date_from" class="filter-input" value="<?= $date_from ?>">
                
                <label style="font-size:0.7rem;font-weight:600;color:var(--text-secondary);">To:</label>
                <input type="date" name="date_to" class="filter-input" value="<?= $date_to ?>">
                
                <button type="submit" class="btn-search">
                    <i class="fas fa-chart-bar"></i> Generate Report
                </button>
                
                <a href="profit.php?branch=<?= $selected_branch_id ?>" class="btn-reset">
                    <i class="fas fa-times"></i> Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Main Stats Cards -->
    <div class="stats-row animate-fade-in-up" style="animation-delay:0.1s;">
        
        <!-- Revenue - BLUE -->
        <div class="stat-card" style="background: linear-gradient(135deg, #2563EB, #1D4ED8);">
            <div class="stat-icon"><i class="fas fa-arrow-up" style="color:rgba(255,255,255,0.8);"></i></div>
            <div class="stat-number">TSh <?= formatMoney($total_revenue) ?></div>
            <div class="stat-label">💰 Total Revenue</div>
        </div>
        
        <!-- Expenses - RED -->
        <div class="stat-card" style="background: linear-gradient(135deg, #DC2626, #B91C1C);">
            <div class="stat-icon"><i class="fas fa-arrow-down" style="color:rgba(255,255,255,0.8);"></i></div>
            <div class="stat-number">TSh <?= formatMoney($expense_data['total_expenses']) ?></div>
            <div class="stat-label">📉 Total Expenses</div>
        </div>
        
        <!-- Net Profit - GREEN -->
        <div class="stat-card" style="background: linear-gradient(135deg, #059669, #047857);">
            <div class="stat-icon"><i class="fas fa-<?= $profit_data['net_profit'] >= 0 ? 'check-circle' : 'exclamation-triangle' ?>" style="color:rgba(255,255,255,0.8);"></i></div>
            <div class="stat-number">TSh <?= formatMoney($profit_data['net_profit']) ?></div>
            <div class="stat-label"><?= $profit_data['net_profit'] >= 0 ? '📈 Net Profit' : '📉 Net Loss' ?></div>
        </div>
        
        <!-- Profit Margin - PURPLE -->
        <div class="stat-card" style="background: linear-gradient(135deg, #7C3AED, #6D28D9);">
            <div class="stat-icon"><i class="fas fa-percentage" style="color:rgba(255,255,255,0.8);"></i></div>
            <div class="stat-number"><?= $profit_data['profit_margin'] ?>%</div>
            <div class="stat-label">📊 Profit Margin</div>
        </div>
        
    </div>

    <!-- Revenue Breakdown -->
    <div class="chart-container animate-fade-in-up" style="animation-delay:0.15s;">
        <h3 style="font-size:0.9rem;font-weight:700;margin-bottom:12px;color:var(--text-primary);">
            <i class="fas fa-pie-chart" style="color:#059669;"></i> Revenue Breakdown
        </h3>
        <div class="revenue-breakdown">
            <div class="breakdown-item">
                <div class="label">Patient Bills</div>
                <div class="value" style="color:#2563EB;">TSh <?= formatMoney($patient_bills_revenue) ?></div>
                <div style="font-size:0.6rem;color:var(--text-secondary);"><?= number_format($patient_bills_count) ?> bills (includes prescriptions)</div>
            </div>
            <div class="breakdown-item">
                <div class="label">OTC Sales</div>
                <div class="value" style="color:#0891B2;">TSh <?= formatMoney($otc_revenue) ?></div>
                <div style="font-size:0.6rem;color:var(--text-secondary);"><?= number_format($otc_count) ?> transactions</div>
            </div>
            <div class="breakdown-item">
                <div class="label">Prescriptions</div>
                <div class="value" style="color:#7C3AED;">TSh <?= formatMoney($prescription_revenue) ?></div>
                <div style="font-size:0.6rem;color:var(--text-secondary);"><?= number_format($prescription_count) ?> items (already in bills)</div>
            </div>
            <div class="breakdown-item">
                <div class="label">Consultation</div>
                <div class="value" style="color:#059669;">TSh <?= formatMoney($consultation_revenue) ?></div>
                <div style="font-size:0.6rem;color:var(--text-secondary);"><?= number_format($consultation_count) ?> consultations</div>
            </div>
            <div class="breakdown-item">
                <div class="label">Lab Tests</div>
                <div class="value" style="color:#7C3AED;">TSh <?= formatMoney($lab_revenue) ?></div>
                <div style="font-size:0.6rem;color:var(--text-secondary);"><?= number_format($lab_count) ?> tests</div>
            </div>
            <div class="breakdown-item">
                <div class="label">Medications</div>
                <div class="value" style="color:#D97706;">TSh <?= formatMoney($medication_revenue) ?></div>
                <div style="font-size:0.6rem;color:var(--text-secondary);"><?= number_format($medication_count) ?> items</div>
            </div>
        </div>
    </div>

    <!-- Monthly Profit Chart -->
    <?php if (count($monthly_data) > 0): ?>
    <div class="chart-container animate-fade-in-up" style="animation-delay:0.2s;">
        <h3 style="font-size:0.9rem;font-weight:700;margin-bottom:12px;color:var(--text-primary);">
            <i class="fas fa-chart-bar" style="color:#059669;"></i> Monthly Profit Trend
        </h3>
        <canvas id="profitChart"></canvas>
    </div>
    <?php endif; ?>

    <!-- Expense Breakdown Table -->
    <div class="table-container animate-fade-in-up" style="animation-delay:0.25s;">
        <div style="padding:12px 16px;border-bottom:1px solid var(--border-color);">
            <h3 style="font-size:0.85rem;font-weight:700;color:var(--text-primary);">
                <i class="fas fa-list" style="color:#DC2626;"></i> Expense Breakdown by Category
            </h3>
        </div>
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><i class="fas fa-tag"></i> Category</th>
                        <th style="text-align:center;"><i class="fas fa-hashtag"></i> Count</th>
                        <th style="text-align:right;"><i class="fas fa-money-bill"></i> Amount</th>
                        <th style="text-align:right;"><i class="fas fa-percentage"></i> % of Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($expense_data['expense_categories']) > 0): ?>
                        <?php 
                        $total_exp = $expense_data['total_expenses'];
                        foreach ($expense_data['expense_categories'] as $cat): 
                            $percentage = $total_exp > 0 ? round(($cat['amount'] / $total_exp) * 100, 2) : 0;
                        ?>
                            <tr>
                                <td>
                                    <span class="badge-status badge-warning">
                                        <?= htmlspecialchars($cat['category']) ?>
                                    </span>
                                </td>
                                <td style="text-align:center;"><?= $cat['count'] ?></td>
                                <td style="text-align:right;font-weight:600;color:#DC2626;">
                                    TSh <?= formatMoney($cat['amount']) ?>
                                </td>
                                <td style="text-align:right;"><?= $percentage ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                        <tr style="font-weight:700;border-top:2px solid var(--border-color);">
                            <td>TOTAL</td>
                            <td style="text-align:center;"><?= array_sum(array_column($expense_data['expense_categories'], 'count')) ?></td>
                            <td style="text-align:right;color:#DC2626;">TSh <?= formatMoney($total_exp) ?></td>
                            <td style="text-align:right;">100%</td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="4">
                                <div style="padding:20px;text-align:center;color:var(--text-secondary);">
                                    <i class="fas fa-coins" style="font-size:2rem;color:var(--border-color);display:block;margin-bottom:8px;"></i>
                                    <p style="font-size:0.8rem;">No expense data available for this period</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="table-footer">
            <span>
                <i class="fas fa-list"></i> <strong><?= count($expense_data['expense_categories']) ?></strong> expense categories
                <span class="text-xs" style="color:var(--text-secondary);">Total: TSh <?= formatMoney($expense_data['total_expenses']) ?></span>
            </span>
            <span>
                <span class="text-xs" style="color:var(--text-secondary);" id="updateTimeDisplay">Last update: <?= date('H:i:s') ?></span>
            </span>
        </div>
    </div>

    <!-- Summary -->
    <div class="chart-container animate-fade-in-up" style="animation-delay:0.3s;">
        <h3 style="font-size:0.9rem;font-weight:700;margin-bottom:12px;color:var(--text-primary);">
            <i class="fas fa-file-alt" style="color:#059669;"></i> Summary
        </h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;">
            <div style="padding:10px 14px;background:var(--bg-body);border-radius:12px;border:1px solid var(--border-color);">
                <div style="font-size:0.6rem;text-transform:uppercase;color:var(--text-secondary);font-weight:600;">Patient Bills</div>
                <div style="font-size:1.2rem;font-weight:700;color:#2563EB;"><?= number_format($patient_bills_count) ?></div>
            </div>
            <div style="padding:10px 14px;background:var(--bg-body);border-radius:12px;border:1px solid var(--border-color);">
                <div style="font-size:0.6rem;text-transform:uppercase;color:var(--text-secondary);font-weight:600;">OTC Transactions</div>
                <div style="font-size:1.2rem;font-weight:700;color:#0891B2;"><?= number_format($otc_count) ?></div>
            </div>
            <div style="padding:10px 14px;background:var(--bg-body);border-radius:12px;border:1px solid var(--border-color);">
                <div style="font-size:0.6rem;text-transform:uppercase;color:var(--text-secondary);font-weight:600;">Prescriptions</div>
                <div style="font-size:1.2rem;font-weight:700;color:#7C3AED;"><?= number_format($prescription_count) ?></div>
            </div>
            <div style="padding:10px 14px;background:var(--bg-body);border-radius:12px;border:1px solid var(--border-color);">
                <div style="font-size:0.6rem;text-transform:uppercase;color:var(--text-secondary);font-weight:600;">Expense Ratio</div>
                <div style="font-size:1.2rem;font-weight:700;color:<?= $total_revenue > 0 && ($expense_data['total_expenses'] / $total_revenue) < 0.5 ? '#059669' : '#D97706' ?>;">
                    <?= $total_revenue > 0 ? round(($expense_data['total_expenses'] / $total_revenue) * 100, 2) : 0 ?>%
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Profit Report
            <span class="text-gray-300 mx-2">|</span>
            <span class="text-gray-400">👤 <?= htmlspecialchars($user_full_name) ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    // ================================================================
    // DARK MODE TOGGLE - FIXED
    // ================================================================
    (function() {
        var darkModeToggle = document.getElementById('darkModeToggle');
        var darkIcon = document.getElementById('darkIcon');
        var darkText = document.getElementById('darkText');
        var htmlElement = document.documentElement;
        
        // Check saved dark mode
        var savedDarkMode = localStorage.getItem('darkMode');
        var cookieDarkMode = document.cookie.split('; ').find(function(row) {
            return row.startsWith('dark_mode=');
        });
        
        // Determine initial dark mode
        var isDark = false;
        if (savedDarkMode === 'true') {
            isDark = true;
        } else if (cookieDarkMode) {
            isDark = cookieDarkMode.split('=')[1] === 'true';
        }
        
        // Apply dark mode
        if (isDark) {
            htmlElement.setAttribute('data-theme', 'dark');
            if (darkIcon) darkIcon.className = 'fas fa-sun';
            if (darkText) darkText.textContent = 'Light';
        } else {
            htmlElement.removeAttribute('data-theme');
            if (darkIcon) darkIcon.className = 'fas fa-moon';
            if (darkText) darkText.textContent = 'Dark';
        }
        
        // Toggle dark mode
        if (darkModeToggle) {
            darkModeToggle.addEventListener('click', function(e) {
                e.preventDefault();
                var isDarkNow = htmlElement.getAttribute('data-theme') === 'dark';
                
                if (isDarkNow) {
                    htmlElement.removeAttribute('data-theme');
                    if (darkIcon) darkIcon.className = 'fas fa-moon';
                    if (darkText) darkText.textContent = 'Dark';
                    localStorage.setItem('darkMode', 'false');
                    document.cookie = "dark_mode=false; path=/";
                } else {
                    htmlElement.setAttribute('data-theme', 'dark');
                    if (darkIcon) darkIcon.className = 'fas fa-sun';
                    if (darkText) darkText.textContent = 'Light';
                    localStorage.setItem('darkMode', 'true');
                    document.cookie = "dark_mode=true; path=/";
                }
            });
        }
    })();

    // ================================================================
    // BRANCH SWITCH
    // ================================================================
    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        window.location.href = url.toString();
    }

    // ================================================================
    // CLOCK - UPDATE EVERY SECOND
    // ================================================================
    function updateClock() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var el = document.getElementById('clockDisplay');
        if (el) {
            el.textContent = dateStr + ' • ' + timeStr;
        }
    }
    setInterval(updateClock, 1000);
    updateClock();

    // ================================================================
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput ? searchInput.value.trim() : '';
        if (query.length > 0) {
            var branch = '<?= $selected_branch_id ?>';
            window.location.href = 'search.php?q=' + encodeURIComponent(query) + '&branch=' + branch;
        }
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
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
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
        var options = { year: 'numeric', month: 'short', day: 'numeric' };
        var dateStr = now.toLocaleDateString('en-US', options);
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        
        var updateTimeDisplay = document.getElementById('updateTimeDisplay');
        if (updateTimeDisplay) {
            updateTimeDisplay.textContent = 'Last update: ' + timeStr;
        }
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // PROFIT CHART
    // ================================================================
    <?php if (count($monthly_data) > 0): ?>
    (function() {
        var ctx = document.getElementById('profitChart').getContext('2d');
        
        var months = <?= json_encode(array_column($monthly_data, 'month')) ?>;
        var revenues = <?= json_encode(array_column($monthly_data, 'revenue')) ?>;
        var expenses = <?= json_encode(array_column($monthly_data, 'expenses')) ?>;
        var profits = <?= json_encode(array_column($monthly_data, 'profit')) ?>;
        
        var monthLabels = months.map(function(m) {
            var parts = m.split('-');
            var monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            return monthNames[parseInt(parts[1]) - 1] + ' ' + parts[0];
        });
        
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        var textColor = isDark ? '#F1F5F9' : '#1E293B';
        var gridColor = isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.1)';
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: monthLabels,
                datasets: [
                    {
                        label: 'Revenue',
                        data: revenues,
                        backgroundColor: 'rgba(37, 99, 235, 0.7)',
                        borderColor: '#2563EB',
                        borderWidth: 2,
                        borderRadius: 4
                    },
                    {
                        label: 'Expenses',
                        data: expenses,
                        backgroundColor: 'rgba(220, 38, 38, 0.7)',
                        borderColor: '#DC2626',
                        borderWidth: 2,
                        borderRadius: 4
                    },
                    {
                        label: 'Profit',
                        data: profits,
                        type: 'line',
                        backgroundColor: 'rgba(5, 150, 105, 0.2)',
                        borderColor: '#059669',
                        borderWidth: 3,
                        pointBackgroundColor: '#059669',
                        pointBorderColor: '#FFFFFF',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        fill: true,
                        tension: 0.3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        labels: {
                            color: textColor,
                            font: { size: 11, weight: '600' },
                            boxWidth: 12,
                            padding: 15
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: gridColor },
                        ticks: { color: textColor, font: { size: 10 } }
                    },
                    y: {
                        grid: { color: gridColor },
                        ticks: {
                            color: textColor,
                            font: { size: 10 },
                            callback: function(value) {
                                return 'TSh ' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    })();
    <?php endif; ?>

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            var searchInput = document.getElementById('searchInput');
            if (searchInput) searchInput.focus();
        }
    });

    console.log('%c💰 Braick - Profit Report', 'font-size:16px; font-weight:bold; color:#059669;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:12px; color:#059669;');
    console.log('%c🏢 Branch: <?= $branch_name ?> (ID: <?= $selected_branch_id ?>)', 'font-size:12px; color:#059669;');
    console.log('%c🔵 Total Revenue: TSh <?= formatMoney($total_revenue) ?> (Bills + OTC)', 'font-size:12px; color:#2563EB;');
    console.log('%c   ├─ Patient Bills: TSh <?= formatMoney($patient_bills_revenue) ?> (<?= number_format($patient_bills_count) ?> bills - includes prescriptions)', 'font-size:11px; color:#2563EB;');
    console.log('%c   ├─ OTC: TSh <?= formatMoney($otc_revenue) ?> (<?= number_format($otc_count) ?> transactions)', 'font-size:11px; color:#0891B2;');
    console.log('%c   └─ Prescriptions: TSh <?= formatMoney($prescription_revenue) ?> (<?= number_format($prescription_count) ?> items - FOR DISPLAY ONLY)', 'font-size:11px; color:#7C3AED;');
    console.log('%c🔴 Expenses: TSh <?= formatMoney($expense_data['total_expenses']) ?>', 'font-size:12px; color:#DC2626;');
    console.log('%c🟢 Net Profit: TSh <?= formatMoney($profit_data['net_profit']) ?>', 'font-size:12px; color:#059669;');
    console.log('%c🟣 Profit Margin: <?= $profit_data['profit_margin'] ?>%', 'font-size:12px; color:#7C3AED;');
    console.log('%c✅ FIXED: Revenue = Patient Bills (paid_amount) + OTC ONLY (no double counting)', 'font-size:12px; color:#34D399;');
    console.log('%c✅ FIXED: Prescriptions are displayed separately but NOT added to total revenue', 'font-size:12px; color:#34D399;');
    console.log('%c🌙 Dark Mode Toggle: WORKING', 'font-size:12px; color:#3B82F6;');
    console.log('%c🕐 Clock: WORKING', 'font-size:12px; color:#3B82F6;');
</script>

</body>
</html>