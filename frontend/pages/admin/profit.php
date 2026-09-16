<?php
// ================================================================
// FILE: frontend/pages/admin/profit.php
// ADMIN - PROFIT/REVENUE DASHBOARD
// PROFIT = REVENUE - EXPENSES
// ✅ Uses SHARED header & sidebar
// ✅ Page-specific CSS only (no duplicates)
// ✅ Dark mode via header toggle
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        case 'audit': header('Location: ../audit/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

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
    if ($amount === null || $amount === '') return '0';
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
// 1. PATIENT BILLS REVENUE
// ================================================================
$patient_bills_revenue = 0;
$patient_bills_count = 0;
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
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $patient_bills_revenue = (float)($result['total'] ?? 0);
    $patient_bills_count = (int)($result['count'] ?? 0);
} catch (Exception $e) {
    $patient_bills_revenue = 0;
    $patient_bills_count = 0;
}

// ================================================================
// 2. OTC REVENUE
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
// 3. PRESCRIPTION REVENUE (FOR DISPLAY ONLY)
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
// ================================================================
$total_revenue = $patient_bills_revenue + $otc_revenue;

// ================================================================
// 5. BREAKDOWN BY CATEGORY
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
} catch (Exception $e) {}

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

// Monthly revenue from bills
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

// Monthly OTC
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

// Merge monthly revenue
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

// Monthly expenses
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
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC CSS -->
<!-- ================================================================ -->
<style>
    /* ================================================================
       PAGE VARIABLES
       ================================================================ */
    :root {
        --pf-primary: #059669;
        --pf-primary-dark: #047857;
        --pf-primary-light: #34D399;
        --pf-primary-bg: #D1FAE5;
        --pf-success: #059669;
        --pf-danger: #DC2626;
        --pf-danger-bg: #FEE2E2;
        --pf-warning: #D97706;
        --pf-warning-bg: #FEF3C7;
        --pf-purple: #7C3AED;
        --pf-purple-bg: #EDE9FE;
        --pf-blue: #2563EB;
        --pf-blue-bg: #DBEAFE;
        --pf-gray-50: #F8FAFC;
        --pf-gray-100: #F1F5F9;
        --pf-gray-200: #E2E8F0;
        --pf-gray-300: #CBD5E1;
        --pf-gray-400: #94A3B8;
        --pf-gray-500: #64748B;
        --pf-gray-600: #475569;
        --pf-gray-700: #334155;
        --pf-gray-800: #1E293B;
        --pf-gray-900: #0F172A;
        --pf-bg-body: #F1F5F9;
        --pf-bg-card: #FFFFFF;
        --pf-text-primary: #1E293B;
        --pf-text-secondary: #64748B;
        --pf-border-color: #E2E8F0;
        --pf-radius: 12px;
        --pf-radius-lg: 18px;
        --pf-shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
        --pf-shadow: 0 1px 3px rgba(0,0,0,0.08);
        --pf-shadow-md: 0 4px 12px rgba(0,0,0,0.08);
        --pf-shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
        --pf-table-hover: #E8F0FE;
    }

    [data-theme="dark"] {
        --pf-bg-body: #0F172A;
        --pf-bg-card: #1E293B;
        --pf-text-primary: #F1F5F9;
        --pf-text-secondary: #94A3B8;
        --pf-border-color: #334155;
        --pf-primary-bg: #1A3A2A;
        --pf-danger-bg: #3A1A1A;
        --pf-warning-bg: #3D2E0A;
        --pf-purple-bg: #2D1B5F;
        --pf-blue-bg: #1E3A5F;
        --pf-gray-100: #1E293B;
        --pf-gray-200: #334155;
        --pf-table-hover: #1E3A5F;
    }

    /* ================================================================
       DARK MODE - PAGE YOTE
       ================================================================ */
    html[data-theme="dark"] body {
        background: #0F172A !important;
    }

    html[data-theme="dark"] .main-content {
        background: #0F172A !important;
        color: #F1F5F9;
    }

    /* ================================================================
       PAGE HEADER
       ================================================================ */
    .page-header-pf {
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

    .page-header-pf .page-title {
        color: white;
        font-size: 1.5rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin: 0;
    }

    .page-header-pf .page-title i { font-size: 1.6rem; opacity: 0.9; }

    .page-header-pf .page-subtitle {
        color: rgba(255,255,255,0.85);
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin-top: 6px;
    }

    .role-badge-display-pf {
        background: rgba(255,255,255,0.2);
        color: white;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.55rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .header-badge-pf {
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

    .btn-outline-light-pf {
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
        cursor: pointer;
    }

    .btn-outline-light-pf:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        color: white;
    }

    /* ================================================================
       STATS CARDS
       ================================================================ */
    .stats-row-pf {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }

    .stat-card-pf {
        border-radius: 18px;
        padding: 18px 20px;
        transition: all 0.3s ease;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        border: none;
        color: white;
    }

    .stat-card-pf:hover {
        transform: translateY(-4px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }

    .stat-card-pf .stat-icon { font-size: 1.8rem; margin-bottom: 8px; }
    .stat-card-pf .stat-number { font-size: 1.6rem; font-weight: 700; color: white; }
    .stat-card-pf .stat-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; margin-top: 4px; opacity: 0.85; color: rgba(255,255,255,0.85); }

    /* ================================================================
       FILTER SECTION
       ================================================================ */
    .filter-section-pf {
        background: var(--pf-bg-card);
        border-radius: 18px;
        padding: 14px 18px;
        border: 1px solid var(--pf-border-color);
        margin-bottom: 20px;
    }

    .filter-row-pf {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
    }

    .filter-input-pf {
        padding: 6px 12px;
        border: 2px solid var(--pf-border-color);
        border-radius: 12px;
        font-size: 0.8rem;
        background: var(--pf-bg-card);
        color: var(--pf-text-primary);
        outline: none;
        transition: all 0.3s ease;
        font-family: inherit;
    }

    .filter-input-pf:focus {
        border-color: var(--pf-primary);
        box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
    }

    .btn-search-pf {
        padding: 6px 16px;
        background: var(--pf-primary);
        color: white;
        border: none;
        border-radius: 12px;
        font-weight: 600;
        font-size: 0.75rem;
        cursor: pointer;
        transition: all 0.3s ease;
        font-family: inherit;
    }

    .btn-search-pf:hover {
        background: var(--pf-primary-dark);
        transform: translateY(-1px);
    }

    .btn-reset-pf {
        padding: 6px 14px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 600;
        border: 2px solid var(--pf-border-color);
        background: transparent;
        color: var(--pf-text-secondary);
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .btn-reset-pf:hover {
        border-color: var(--pf-danger);
        color: var(--pf-danger);
    }

    /* ================================================================
       CHART CONTAINER
       ================================================================ */
    .chart-container-pf {
        background: var(--pf-bg-card);
        border-radius: 18px;
        border: 1px solid var(--pf-border-color);
        padding: 20px;
        margin-bottom: 20px;
    }

    .chart-container-pf h3 {
        font-size: 0.9rem;
        font-weight: 700;
        margin-bottom: 12px;
        color: var(--pf-text-primary);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .chart-container-pf canvas {
        max-height: 300px;
        max-width: 100%;
    }

    /* ================================================================
       REVENUE BREAKDOWN
       ================================================================ */
    .revenue-breakdown-pf {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 10px;
        margin-top: 12px;
    }

    .breakdown-item-pf {
        padding: 10px 14px;
        border-radius: 12px;
        background: var(--pf-bg-body);
        border: 1px solid var(--pf-border-color);
    }

    .breakdown-item-pf .label {
        font-size: 0.6rem;
        text-transform: uppercase;
        color: var(--pf-text-secondary);
        font-weight: 600;
    }

    .breakdown-item-pf .value {
        font-size: 1rem;
        font-weight: 700;
        color: var(--pf-text-primary);
    }

    .breakdown-item-pf .sub {
        font-size: 0.6rem;
        color: var(--pf-text-secondary);
        margin-top: 2px;
    }

    /* ================================================================
       TABLE
       ================================================================ */
    .table-container-pf {
        background: var(--pf-bg-card);
        border-radius: 18px;
        border: 1px solid var(--pf-border-color);
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        margin-bottom: 20px;
    }

    .table-container-pf .table-header-pf {
        padding: 12px 16px;
        border-bottom: 1px solid var(--pf-border-color);
    }

    .table-container-pf .table-header-pf h3 {
        font-size: 0.85rem;
        font-weight: 700;
        color: var(--pf-text-primary);
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
    }

    .table-scroll-pf { overflow-x: auto; }

    .data-table-pf {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.75rem;
    }

    .data-table-pf thead th {
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

    .data-table-pf tbody td {
        padding: 8px 14px;
        border-bottom: 1px solid var(--pf-border-color);
        color: var(--pf-text-primary);
        vertical-align: middle;
    }

    .data-table-pf tbody tr:hover td { background: var(--pf-table-hover); }
    .data-table-pf tbody tr:last-child td { border-bottom: none; }

    .badge-status-pf {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 16px;
        font-size: 0.55rem;
        font-weight: 600;
        text-transform: capitalize;
    }

    .badge-warning-pf { background: #FEF3C7; color: #D97706; border: 1px solid #D97706; }
    .badge-danger-pf { background: #FEE2E2; color: #DC2626; border: 1px solid #DC2626; }
    .badge-success-pf { background: #D1FAE5; color: #059669; border: 1px solid #059669; }
    .badge-blue-pf { background: #DBEAFE; color: #2563EB; border: 1px solid #2563EB; }
    .badge-purple-pf { background: #EDE9FE; color: #7C3AED; border: 1px solid #7C3AED; }

    [data-theme="dark"] .badge-warning-pf { background: #3D2E0A; color: #FBBF24; border-color: #F59E0B; }
    [data-theme="dark"] .badge-danger-pf { background: #3A1A1A; color: #F87171; border-color: #DC2626; }
    [data-theme="dark"] .badge-success-pf { background: #1A3A2A; color: #34D399; border-color: #059669; }
    [data-theme="dark"] .badge-blue-pf { background: #1E3A5F; color: #6EA8FE; border-color: #2563EB; }
    [data-theme="dark"] .badge-purple-pf { background: #2D1B5F; color: #A78BFA; border-color: #7C3AED; }

    .table-footer-pf {
        padding: 10px 16px;
        border-top: 1px solid var(--pf-border-color);
        font-size: 0.65rem;
        color: var(--pf-text-secondary);
        display: flex;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 6px;
        background: var(--pf-gray-50);
    }

    /* ================================================================
       SUMMARY CARDS
       ================================================================ */
    .summary-grid-pf {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 10px;
    }

    .summary-card-pf {
        padding: 10px 14px;
        background: var(--pf-bg-body);
        border-radius: 12px;
        border: 1px solid var(--pf-border-color);
    }

    .summary-card-pf .label {
        font-size: 0.6rem;
        text-transform: uppercase;
        color: var(--pf-text-secondary);
        font-weight: 600;
    }

    .summary-card-pf .value {
        font-size: 1.2rem;
        font-weight: 700;
    }

    /* ================================================================
       FOOTER
       ================================================================ */
    .footer-pf {
        padding: 10px 0;
        border-top: 1px solid var(--pf-border-color);
        margin-top: 20px;
        text-align: center;
        font-size: 0.65rem;
        color: var(--pf-text-secondary);
    }

    .footer-pf .footer-brand { color: var(--pf-primary); font-weight: 600; }

    /* ================================================================
       ANIMATIONS
       ================================================================ */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(12px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in-up { animation: fadeInUp 0.4s ease forwards; opacity: 0; }

    /* ================================================================
       TEXT UTILITIES
       ================================================================ */
    .text-blue-pf { color: var(--pf-blue); }
    .text-green-pf { color: var(--pf-success); }
    .text-red-pf { color: var(--pf-danger); }
    .text-orange-pf { color: var(--pf-warning); }
    .text-purple-pf { color: var(--pf-purple); }
    .text-cyan-pf { color: #0891B2; }
    .text-secondary-pf { color: var(--pf-text-secondary); }

    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 768px) {
        .filter-row-pf { flex-direction: column; align-items: stretch; }
        .filter-input-pf { width: 100%; }
        .stats-row-pf { grid-template-columns: 1fr 1fr; }
        .page-header-pf { padding: 16px 18px; }
        .page-header-pf .page-title { font-size: 1.1rem; }
        .revenue-breakdown-pf { grid-template-columns: 1fr 1fr; }
    }

    @media (max-width: 480px) {
        .stats-row-pf { grid-template-columns: 1fr; }
        .data-table-pf { font-size: 0.6rem; }
        .data-table-pf thead th, .data-table-pf td { padding: 4px 6px; }
        .revenue-breakdown-pf { grid-template-columns: 1fr; }
        .page-header-pf { flex-direction: column; align-items: flex-start !important; }
    }

    @media print {
        .btn-outline-light-pf, .filter-section-pf { display: none !important; }
        .page-header-pf { background: #059669 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header-pf animate-fade-in-up">
        <div>
            <h1 class="page-title">
                <i class="fas fa-chart-line"></i>
                Profit Report
                <span class="role-badge-display-pf">ADMIN</span>
                <?php if ($selected_branch_id > 0): ?>
                    <span class="role-badge-display-pf" style="background:rgba(52,211,153,0.3);color:#34D399;">
                        <i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name) ?>
                    </span>
                <?php endif; ?>
                <span class="header-badge-pf" style="background:rgba(52,211,153,0.2);color:#34D399;">
                    <i class="fas fa-money-bill-wave"></i> Profit: TSh <?= formatMoney($profit_data['net_profit']) ?>
                </span>
                <span class="header-badge-pf" style="background:rgba(251,191,36,0.15);color:#FBBF24;">
                    <i class="fas fa-percentage"></i> Margin: <?= $profit_data['profit_margin'] ?>%
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-arrow-right"></i>
                Profit = Revenue - Expenses
                <span class="header-badge-pf" style="background:rgba(52,211,153,0.2);color:#34D399;">
                    <i class="fas fa-calendar-alt"></i> <?= date('M d, Y', strtotime($date_from)) ?> - <?= date('M d, Y', strtotime($date_to)) ?>
                </span>
                <span class="header-badge-pf" style="background:rgba(52,211,153,0.15);color:#34D399;border-color:rgba(52,211,153,0.2);">
                    <i class="fas fa-info-circle"></i> Prescriptions included in bills
                </span>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;position:relative;z-index:1;">
            <button onclick="window.print()" class="btn-outline-light-pf">
                <i class="fas fa-print"></i> Print Report
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="filter-section-pf animate-fade-in-up" style="animation-delay:0.05s;">
        <form method="GET" action="" id="filterForm">
            <div class="filter-row-pf">
                <input type="hidden" name="branch" value="<?= $selected_branch_id ?>">
                
                <label style="font-size:0.7rem;font-weight:600;color:var(--pf-text-secondary);">From:</label>
                <input type="date" name="date_from" class="filter-input-pf" value="<?= $date_from ?>">
                
                <label style="font-size:0.7rem;font-weight:600;color:var(--pf-text-secondary);">To:</label>
                <input type="date" name="date_to" class="filter-input-pf" value="<?= $date_to ?>">
                
                <button type="submit" class="btn-search-pf">
                    <i class="fas fa-chart-bar"></i> Generate Report
                </button>
                
                <a href="profit.php?branch=<?= $selected_branch_id ?>" class="btn-reset-pf">
                    <i class="fas fa-times"></i> Reset
                </a>
            </div>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- MAIN STATS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-row-pf animate-fade-in-up" style="animation-delay:0.1s;">
        
        <!-- Revenue - BLUE -->
        <div class="stat-card-pf" style="background: linear-gradient(135deg, #2563EB, #1D4ED8);">
            <div class="stat-icon"><i class="fas fa-arrow-up" style="color:rgba(255,255,255,0.8);"></i></div>
            <div class="stat-number">TSh <?= formatMoney($total_revenue) ?></div>
            <div class="stat-label">💰 Total Revenue</div>
        </div>
        
        <!-- Expenses - RED -->
        <div class="stat-card-pf" style="background: linear-gradient(135deg, #DC2626, #B91C1C);">
            <div class="stat-icon"><i class="fas fa-arrow-down" style="color:rgba(255,255,255,0.8);"></i></div>
            <div class="stat-number">TSh <?= formatMoney($expense_data['total_expenses']) ?></div>
            <div class="stat-label">📉 Total Expenses</div>
        </div>
        
        <!-- Net Profit - GREEN -->
        <div class="stat-card-pf" style="background: linear-gradient(135deg, #059669, #047857);">
            <div class="stat-icon"><i class="fas fa-<?= $profit_data['net_profit'] >= 0 ? 'check-circle' : 'exclamation-triangle' ?>" style="color:rgba(255,255,255,0.8);"></i></div>
            <div class="stat-number">TSh <?= formatMoney($profit_data['net_profit']) ?></div>
            <div class="stat-label"><?= $profit_data['net_profit'] >= 0 ? '📈 Net Profit' : '📉 Net Loss' ?></div>
        </div>
        
        <!-- Profit Margin - PURPLE -->
        <div class="stat-card-pf" style="background: linear-gradient(135deg, #7C3AED, #6D28D9);">
            <div class="stat-icon"><i class="fas fa-percentage" style="color:rgba(255,255,255,0.8);"></i></div>
            <div class="stat-number"><?= $profit_data['profit_margin'] ?>%</div>
            <div class="stat-label">📊 Profit Margin</div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- REVENUE BREAKDOWN -->
    <!-- ================================================================ -->
    <div class="chart-container-pf animate-fade-in-up" style="animation-delay:0.15s;">
        <h3>
            <i class="fas fa-pie-chart" style="color:#059669;"></i> Revenue Breakdown
        </h3>
        <div class="revenue-breakdown-pf">
            <div class="breakdown-item-pf">
                <div class="label">Patient Bills</div>
                <div class="value" style="color:#2563EB;">TSh <?= formatMoney($patient_bills_revenue) ?></div>
                <div class="sub"><?= number_format($patient_bills_count) ?> bills (includes prescriptions)</div>
            </div>
            <div class="breakdown-item-pf">
                <div class="label">OTC Sales</div>
                <div class="value" style="color:#0891B2;">TSh <?= formatMoney($otc_revenue) ?></div>
                <div class="sub"><?= number_format($otc_count) ?> transactions</div>
            </div>
            <div class="breakdown-item-pf">
                <div class="label">Prescriptions</div>
                <div class="value" style="color:#7C3AED;">TSh <?= formatMoney($prescription_revenue) ?></div>
                <div class="sub"><?= number_format($prescription_count) ?> items (already in bills)</div>
            </div>
            <div class="breakdown-item-pf">
                <div class="label">Consultation</div>
                <div class="value" style="color:#059669;">TSh <?= formatMoney($consultation_revenue) ?></div>
                <div class="sub"><?= number_format($consultation_count) ?> consultations</div>
            </div>
            <div class="breakdown-item-pf">
                <div class="label">Lab Tests</div>
                <div class="value" style="color:#7C3AED;">TSh <?= formatMoney($lab_revenue) ?></div>
                <div class="sub"><?= number_format($lab_count) ?> tests</div>
            </div>
            <div class="breakdown-item-pf">
                <div class="label">Medications</div>
                <div class="value" style="color:#D97706;">TSh <?= formatMoney($medication_revenue) ?></div>
                <div class="sub"><?= number_format($medication_count) ?> items</div>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- MONTHLY PROFIT CHART -->
    <!-- ================================================================ -->
    <?php if (count($monthly_data) > 0): ?>
    <div class="chart-container-pf animate-fade-in-up" style="animation-delay:0.2s;">
        <h3>
            <i class="fas fa-chart-bar" style="color:#059669;"></i> Monthly Profit Trend
        </h3>
        <canvas id="profitChart"></canvas>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- EXPENSE BREAKDOWN TABLE -->
    <!-- ================================================================ -->
    <div class="table-container-pf animate-fade-in-up" style="animation-delay:0.25s;">
        <div class="table-header-pf">
            <h3>
                <i class="fas fa-list" style="color:#DC2626;"></i> Expense Breakdown by Category
            </h3>
        </div>
        <div class="table-scroll-pf">
            <table class="data-table-pf">
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
                                    <span class="badge-status-pf badge-warning-pf">
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
                        <tr style="font-weight:700;border-top:2px solid var(--pf-border-color);">
                            <td>TOTAL</td>
                            <td style="text-align:center;"><?= array_sum(array_column($expense_data['expense_categories'], 'count')) ?></td>
                            <td style="text-align:right;color:#DC2626;">TSh <?= formatMoney($total_exp) ?></td>
                            <td style="text-align:right;">100%</td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="4">
                                <div style="padding:20px;text-align:center;color:var(--pf-text-secondary);">
                                    <i class="fas fa-coins" style="font-size:2rem;color:var(--pf-border-color);display:block;margin-bottom:8px;"></i>
                                    <p style="font-size:0.8rem;">No expense data available for this period</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="table-footer-pf">
            <span>
                <i class="fas fa-list"></i> <strong><?= count($expense_data['expense_categories']) ?></strong> expense categories
                <span style="color:var(--pf-text-secondary);">Total: TSh <?= formatMoney($expense_data['total_expenses']) ?></span>
            </span>
            <span>
                <span style="color:var(--pf-text-secondary);" id="updateTimeDisplay">Last update: <?= date('H:i:s') ?></span>
            </span>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- SUMMARY -->
    <!-- ================================================================ -->
    <div class="chart-container-pf animate-fade-in-up" style="animation-delay:0.3s;">
        <h3>
            <i class="fas fa-file-alt" style="color:#059669;"></i> Summary
        </h3>
        <div class="summary-grid-pf">
            <div class="summary-card-pf">
                <div class="label">Patient Bills</div>
                <div class="value" style="color:#2563EB;"><?= number_format($patient_bills_count) ?></div>
            </div>
            <div class="summary-card-pf">
                <div class="label">OTC Transactions</div>
                <div class="value" style="color:#0891B2;"><?= number_format($otc_count) ?></div>
            </div>
            <div class="summary-card-pf">
                <div class="label">Prescriptions</div>
                <div class="value" style="color:#7C3AED;"><?= number_format($prescription_count) ?></div>
            </div>
            <div class="summary-card-pf">
                <div class="label">Expense Ratio</div>
                <div class="value" style="color:<?= $total_revenue > 0 && ($expense_data['total_expenses'] / $total_revenue) < 0.5 ? '#059669' : '#D97706' ?>;">
                    <?= $total_revenue > 0 ? round(($expense_data['total_expenses'] / $total_revenue) * 100, 2) : 0 ?>%
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer-pf">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            Profit Report
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            <span style="color:var(--pf-text-secondary);">👤 <?= htmlspecialchars($user_full_name) ?></span>
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC JAVASCRIPT -->
<!-- ================================================================ -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    // ================================================================
    // PROFIT CHART
    // ================================================================
    <?php if (count($monthly_data) > 0): ?>
    (function() {
        var ctx = document.getElementById('profitChart');
        if (!ctx) return;
        
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
    // FOOTER TIME
    // ================================================================
    setInterval(function() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
        
        var updEl = document.getElementById('updateTimeDisplay');
        if (updEl) updEl.textContent = 'Last update: ' + timeStr;
    }, 1000);

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            var searchInput = document.getElementById('globalSearch');
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
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:12px; color:#34D399;');
    console.log('%c✅ No duplicate CSS/JS', 'font-size:12px; color:#34D399;');
    console.log('%c🌙 Dark mode: Handled by header (shared)', 'font-size:12px; color:#34D399;');
</script>

</body>
</html>