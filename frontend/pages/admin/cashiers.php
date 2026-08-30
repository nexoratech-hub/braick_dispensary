<?php
// ================================================================
// FILE: frontend/pages/admin/cashiers.php
// SUPER ADMIN - VIEW ALL CASHIERS
// BRAICK DISPENSARY - USING EXISTING DB TABLES
// FIXED: Invalid ID error removed, Discounts included in calculations
// FIXED: Uses shared admin_sidebar.php with all menus
// ================================================================

// ================================================================
// START SESSION
// ================================================================
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
// CHECK IF USER HAS ADMIN ACCESS
// ================================================================
if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET USER DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$dark_mode = isset($_COOKIE['dark_mode']) ? $_COOKIE['dark_mode'] : 'false';

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// GET FILTER PARAMETERS - FIXED: Remove invalid_id error
// ================================================================
$selected_branch_id = isset($_GET['branch']) ? trim($_GET['branch']) : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'all';

// Remove any error parameter from URL - FIXED
if (isset($_GET['error'])) {
    header('Location: cashiers.php?branch=' . $selected_branch_id . '&search=' . urlencode($search) . '&status=' . $status_filter);
    exit;
}

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
// GET FINANCIAL SUMMARY - INCLUDING DISCOUNTS
// ================================================================
function getFinancialSummary($db, $branch_id = 'all') {
    $results = [];
    
    $branch_condition = "";
    $params = [];
    
    if ($branch_id !== 'all' && is_numeric($branch_id)) {
        $branch_condition = " AND b.branch_id = ?";
        $params[] = (int)$branch_id;
    }
    
    $params_otc = $params;
    $branch_condition_otc = str_replace('b.branch_id', 'os.branch_id', $branch_condition);
    
    $params_expenses = [];
    $branch_condition_expenses = "";
    if ($branch_id !== 'all' && is_numeric($branch_id)) {
        $branch_condition_expenses = " AND branch_id = ?";
        $params_expenses[] = (int)$branch_id;
    }
    
    // 1. BILLS REVENUE
    $sql_bills = "
        SELECT COALESCE(SUM(b.total_amount), 0) as bills_revenue
        FROM bills b
        WHERE b.status = 'paid' $branch_condition
    ";
    $stmt = $db->prepare($sql_bills);
    $stmt->execute($params);
    $bills_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['bills_revenue'] ?? 0;
    $results['bills_revenue'] = $bills_revenue;
    
    // 2. BILLS REVENUE WITH DISCOUNTS
    $sql_bills_paid = "
        SELECT COALESCE(SUM(b.paid_amount), 0) as bills_paid
        FROM bills b
        WHERE b.status IN ('paid', 'partial') $branch_condition
    ";
    $stmt = $db->prepare($sql_bills_paid);
    $stmt->execute($params);
    $bills_paid = $stmt->fetch(PDO::FETCH_ASSOC)['bills_paid'] ?? 0;
    $results['bills_paid'] = $bills_paid;
    
    // 3. TOTAL DISCOUNT AMOUNT
    $sql_discounts = "
        SELECT 
            COALESCE(SUM(b.discount_amount), 0) as pharmacy_discounts,
            COALESCE(SUM(b.cashier_discount), 0) as cashier_discounts,
            COALESCE(SUM(b.total_discount), 0) as total_discounts
        FROM bills b
        WHERE b.status IN ('paid', 'partial') $branch_condition
    ";
    $stmt = $db->prepare($sql_discounts);
    $stmt->execute($params);
    $discounts = $stmt->fetch(PDO::FETCH_ASSOC);
    $results['pharmacy_discounts'] = $discounts['pharmacy_discounts'] ?? 0;
    $results['cashier_discounts'] = $discounts['cashier_discounts'] ?? 0;
    $results['total_discounts'] = $discounts['total_discounts'] ?? 0;
    
    // 4. OTC REVENUE
    $sql_otc = "
        SELECT COALESCE(SUM(os.total_amount), 0) as otc_revenue
        FROM otc_sales os
        WHERE os.payment_status = 'paid' $branch_condition_otc
    ";
    $stmt = $db->prepare($sql_otc);
    $stmt->execute($params_otc);
    $otc_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['otc_revenue'] ?? 0;
    $results['otc_revenue'] = $otc_revenue;
    
    // 5. TOTAL REVENUE
    $results['total_revenue'] = $bills_paid + $otc_revenue;
    
    // 6. EXPENSES
    $sql_expenses = "
        SELECT COALESCE(SUM(amount), 0) as total_expenses
        FROM expenses
        WHERE status = 'paid' $branch_condition_expenses
    ";
    $stmt = $db->prepare($sql_expenses);
    $stmt->execute($params_expenses);
    $results['total_expenses'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_expenses'] ?? 0;
    
    // 7. NET PROFIT
    $results['net_profit'] = $results['total_revenue'] - $results['total_expenses'];
    
    // 8. PRESCRIPTION REVENUE
    $sql_prescription = "
        SELECT COALESCE(SUM(bi.total_price), 0) as prescription_revenue
        FROM bill_items bi
        INNER JOIN bills b ON bi.bill_id = b.id
        WHERE bi.item_type = 'medication' 
        AND b.status IN ('paid', 'partial') 
        $branch_condition
    ";
    $stmt = $db->prepare($sql_prescription);
    $stmt->execute($params);
    $results['prescription_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['prescription_revenue'] ?? 0;
    
    // 9. PAID BILLS
    $sql_paid = "
        SELECT COUNT(*) as paid_bills
        FROM bills b
        WHERE b.status = 'paid' $branch_condition
    ";
    $stmt = $db->prepare($sql_paid);
    $stmt->execute($params);
    $results['paid_bills'] = $stmt->fetch(PDO::FETCH_ASSOC)['paid_bills'] ?? 0;
    
    // 10. PENDING BILLS
    $sql_pending = "
        SELECT COUNT(*) as pending_bills
        FROM bills b
        WHERE b.status = 'pending' $branch_condition
    ";
    $stmt = $db->prepare($sql_pending);
    $stmt->execute($params);
    $results['pending_bills'] = $stmt->fetch(PDO::FETCH_ASSOC)['pending_bills'] ?? 0;
    
    // 11. PARTIAL BILLS
    $sql_partial = "
        SELECT COUNT(*) as partial_bills
        FROM bills b
        WHERE b.status = 'partial' $branch_condition
    ";
    $stmt = $db->prepare($sql_partial);
    $stmt->execute($params);
    $results['partial_bills'] = $stmt->fetch(PDO::FETCH_ASSOC)['partial_bills'] ?? 0;
    
    // 12. CANCELLED BILLS
    $sql_cancelled = "
        SELECT COUNT(*) as cancelled_bills
        FROM bills b
        WHERE b.status = 'cancelled' $branch_condition
    ";
    $stmt = $db->prepare($sql_cancelled);
    $stmt->execute($params);
    $results['cancelled_bills'] = $stmt->fetch(PDO::FETCH_ASSOC)['cancelled_bills'] ?? 0;
    
    // 13. TOTAL BILLS
    $sql_total = "
        SELECT COUNT(*) as total_bills
        FROM bills b
        WHERE 1=1 $branch_condition
    ";
    $stmt = $db->prepare($sql_total);
    $stmt->execute($params);
    $results['total_bills'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_bills'] ?? 0;
    
    return $results;
}

// Get financial summary
$financial = getFinancialSummary($db, $selected_branch_id);

// ================================================================
// BUILD QUERY FOR BRANCHES - WITH DISCOUNTS INCLUDED
// ================================================================
$query = "
    SELECT 
        b.*,
        COALESCE(
            (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'cashier' AND status = 'active'), 
            0
        ) as active_cashiers,
        COALESCE(
            (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND role = 'cashier'), 
            0
        ) as total_cashiers,
        COALESCE(
            (SELECT COUNT(*) 
             FROM bills bl
             WHERE bl.branch_id = b.id), 
            0
        ) as total_bills,
        COALESCE(
            (SELECT COUNT(*) 
             FROM bills bl
             WHERE bl.branch_id = b.id AND bl.status = 'pending'), 
            0
        ) as pending_bills,
        COALESCE(
            (SELECT COUNT(*) 
             FROM bills bl
             WHERE bl.branch_id = b.id AND bl.status = 'paid'), 
            0
        ) as paid_bills,
        COALESCE(
            (SELECT COUNT(*) 
             FROM bills bl
             WHERE bl.branch_id = b.id AND bl.status = 'partial'), 
            0
        ) as partial_bills,
        COALESCE(
            (SELECT COUNT(*) 
             FROM bills bl
             WHERE bl.branch_id = b.id AND bl.status = 'cancelled'), 
            0
        ) as cancelled_bills,
        COALESCE(
            (SELECT COALESCE(SUM(bl.paid_amount), 0) 
             FROM bills bl
             WHERE bl.branch_id = b.id AND bl.status IN ('paid', 'partial')), 
            0
        ) as bills_revenue,
        COALESCE(
            (SELECT COALESCE(SUM(bl.discount_amount), 0) 
             FROM bills bl
             WHERE bl.branch_id = b.id AND bl.status IN ('paid', 'partial')), 
            0
        ) as pharmacy_discounts,
        COALESCE(
            (SELECT COALESCE(SUM(bl.cashier_discount), 0) 
             FROM bills bl
             WHERE bl.branch_id = b.id AND bl.status IN ('paid', 'partial')), 
            0
        ) as cashier_discounts,
        COALESCE(
            (SELECT COALESCE(SUM(bl.total_discount), 0) 
             FROM bills bl
             WHERE bl.branch_id = b.id AND bl.status IN ('paid', 'partial')), 
            0
        ) as total_discounts,
        COALESCE(
            (SELECT COALESCE(SUM(os.total_amount), 0) 
             FROM otc_sales os
             WHERE os.branch_id = b.id AND os.payment_status = 'paid'), 
            0
        ) as otc_revenue,
        COALESCE(
            (SELECT COALESCE(SUM(bi.total_price), 0) 
             FROM bill_items bi
             INNER JOIN bills bl ON bi.bill_id = bl.id
             WHERE bi.item_type = 'medication' 
             AND bl.status IN ('paid', 'partial') 
             AND bl.branch_id = b.id), 
            0
        ) as prescription_revenue,
        COALESCE(
            (SELECT COALESCE(SUM(amount), 0) 
             FROM expenses 
             WHERE branch_id = b.id AND status = 'paid'), 
            0
        ) as branch_expenses,
        COALESCE(
            (
                (SELECT COALESCE(SUM(bl.paid_amount), 0) 
                 FROM bills bl
                 WHERE bl.branch_id = b.id AND bl.status IN ('paid', 'partial'))
                + 
                (SELECT COALESCE(SUM(os.total_amount), 0) 
                 FROM otc_sales os
                 WHERE os.branch_id = b.id AND os.payment_status = 'paid')
            ), 
            0
        ) as total_revenue
    FROM branches b
    WHERE 1=1
";

$params = [];

// Branch filter
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $check_stmt = $db->prepare("SELECT id FROM branches WHERE id = ? AND status = 'active'");
    $check_stmt->execute([(int)$selected_branch_id]);
    if ($check_stmt->fetch()) {
        $query .= " AND b.id = ?";
        $params[] = (int)$selected_branch_id;
    } else {
        $selected_branch_id = 'all';
    }
}

// Status filter
if ($status_filter !== 'all') {
    $query .= " AND b.status = ?";
    $params[] = $status_filter;
} else {
    $query .= " AND b.status = 'active'";
}

// Search filter
if (!empty($search)) {
    $query .= " AND (b.name LIKE ? OR b.location LIKE ? OR b.phone LIKE ? OR b.email LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$query .= " ORDER BY b.name ASC";

// Execute query
$cashiers = [];
try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $cashiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching cashiers: " . $e->getMessage());
    $cashiers = [];
}

// ================================================================
// GET SUMMARY STATISTICS - INCLUDING DISCOUNTS
// ================================================================
$total_cashiers = count($cashiers);
$total_cashiers_count = 0;
$total_bills = 0;
$total_pending = 0;
$total_paid = 0;
$total_partial = 0;
$total_cancelled = 0;
$total_revenue = 0;
$total_bills_revenue = 0;
$total_otc_revenue = 0;
$total_prescription_revenue = 0;
$total_expenses = 0;
$total_pharmacy_discounts = 0;
$total_cashier_discounts = 0;
$total_discounts = 0;

foreach ($cashiers as $c) {
    $total_cashiers_count += ($c['total_cashiers'] ?? 0);
    $total_bills += ($c['total_bills'] ?? 0);
    $total_pending += ($c['pending_bills'] ?? 0);
    $total_paid += ($c['paid_bills'] ?? 0);
    $total_partial += ($c['partial_bills'] ?? 0);
    $total_cancelled += ($c['cancelled_bills'] ?? 0);
    $total_bills_revenue += ($c['bills_revenue'] ?? 0);
    $total_otc_revenue += ($c['otc_revenue'] ?? 0);
    $total_prescription_revenue += ($c['prescription_revenue'] ?? 0);
    $total_expenses += ($c['branch_expenses'] ?? 0);
    $total_revenue += ($c['total_revenue'] ?? 0);
    $total_pharmacy_discounts += ($c['pharmacy_discounts'] ?? 0);
    $total_cashier_discounts += ($c['cashier_discounts'] ?? 0);
    $total_discounts += ($c['total_discounts'] ?? 0);
}

$net_profit = $total_revenue - $total_expenses;

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
// FUNCTION FORMAT CURRENCY
// ================================================================
function format_currency($amount) {
    if ($amount == 0) {
        return 'TSh 0';
    }
    return 'TSh ' . number_format($amount, 0);
}

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR - FIXED: Uses shared admin sidebar
// ================================================================
include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cashiers - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_url ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #3B82F6;
            --primary-bg: #EFF6FF;
            --primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            --primary-gradient-strong: linear-gradient(135deg, #0A4CA8, #083C8A);
            
            --card-blue: #0B5ED7;
            --card-red: #DC2626;
            --card-green: #059669;
            --card-orange: #D97706;
        }
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --primary: #3B82F6;
            --primary-dark: #2563EB;
            --primary-light: #60A5FA;
            --primary-bg: #1E3A5F;
            --card-blue: #2563EB;
            --card-red: #DC2626;
            --card-green: #059669;
            --card-orange: #D97706;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 24px 28px;
            min-height: calc(100vh - 68px);
            transition: background 0.3s ease;
        }
        
        /* ================================================================
           8 CARDS - CUSTOM COLORS
           ================================================================ */
        .stats-grid-8 {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 24px;
        }
        
        .stat-card-8 {
            border-radius: 14px;
            padding: 16px 18px;
            border: none;
            display: flex;
            flex-direction: column;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
            color: white;
            position: relative;
            overflow: hidden;
            min-height: 100px;
            cursor: default;
        }
        
        .stat-card-8::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 160px;
            height: 160px;
            background: rgba(255,255,255,0.06);
            border-radius: 50%;
            pointer-events: none;
            transition: all 0.5s ease;
        }
        .stat-card-8::after {
            content: '';
            position: absolute;
            bottom: -40%;
            left: -10%;
            width: 120px;
            height: 120px;
            background: rgba(255,255,255,0.04);
            border-radius: 50%;
            pointer-events: none;
            transition: all 0.5s ease;
        }
        .stat-card-8:hover {
            transform: translateY(-4px) scale(1.01);
            box-shadow: 0 10px 32px rgba(0,0,0,0.2);
        }
        .stat-card-8:hover::before { transform: scale(1.3); right: -10%; }
        .stat-card-8:hover::after { transform: scale(1.4); bottom: -30%; }
        
        .stat-card-8 .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
            background: rgba(255,255,255,0.18);
            color: white;
            border: 1px solid rgba(255,255,255,0.12);
            backdrop-filter: blur(8px);
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
            margin-bottom: 4px;
        }
        .stat-card-8:hover .stat-icon {
            transform: scale(1.05) rotate(-2deg);
            background: rgba(255,255,255,0.3);
        }
        .stat-card-8 .stat-content {
            position: relative;
            z-index: 1;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .stat-card-8 .stat-label {
            font-size: 0.6rem;
            color: rgba(255,255,255,0.85);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin: 0 0 1px 0;
        }
        .stat-card-8 .stat-number {
            font-size: 1.8rem;
            font-weight: 800;
            color: white;
            margin: 0;
            line-height: 1.1;
            letter-spacing: -0.02em;
        }
        .stat-card-8 .stat-sub {
            font-size: 0.6rem;
            color: rgba(255,255,255,0.9);
            margin-top: 3px;
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }
        .stat-card-8 .stat-arrow {
            position: absolute;
            right: 12px;
            bottom: 12px;
            color: rgba(255,255,255,0.12);
            font-size: 0.7rem;
            transition: all 0.3s ease;
            z-index: 1;
        }
        .stat-card-8:hover .stat-arrow {
            transform: translateX(6px);
            color: rgba(255,255,255,0.4);
        }
        
        /* ================================================================
           CARD COLOR CLASSES
           ================================================================ */
        .card-blue { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
        .card-blue:hover { box-shadow: 0 10px 32px rgba(11, 94, 215, 0.4); }
        
        .card-red { background: linear-gradient(135deg, #DC2626, #B91C1C); }
        .card-red:hover { box-shadow: 0 10px 32px rgba(220, 38, 38, 0.4); }
        
        .card-green { background: linear-gradient(135deg, #059669, #047857); }
        .card-green:hover { box-shadow: 0 10px 32px rgba(5, 150, 105, 0.4); }
        
        .card-orange { background: linear-gradient(135deg, #D97706, #B45309); }
        .card-orange:hover { box-shadow: 0 10px 32px rgba(217, 119, 6, 0.4); }
        
        [data-theme="dark"] .card-blue { background: linear-gradient(135deg, #2563EB, #1D4ED8); }
        [data-theme="dark"] .card-red { background: linear-gradient(135deg, #DC2626, #B91C1C); }
        [data-theme="dark"] .card-green { background: linear-gradient(135deg, #059669, #047857); }
        [data-theme="dark"] .card-orange { background: linear-gradient(135deg, #D97706, #B45309); }
        
        /* ================================================================
           FILTER BAR
           ================================================================ */
        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
            align-items: center;
            background: var(--bg-card);
            padding: 14px 18px;
            border-radius: 12px;
            border: 2px solid var(--border-color);
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        .filter-bar .filter-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .filter-bar select, .filter-bar input {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 8px 14px;
            font-size: 0.8rem;
            color: var(--text-primary);
            outline: none;
            transition: all 0.3s;
            min-width: 150px;
        }
        .filter-bar select:focus, .filter-bar input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.1);
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        .btn-primary {
            background: var(--primary-gradient);
            color: white;
        }
        .btn-primary:hover {
            background: var(--primary-gradient-strong);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
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
        
        /* ================================================================
           BRANCH CARDS
           ================================================================ */
        .cashier-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }
        .cashier-card {
            background: var(--bg-card);
            border-radius: 18px;
            border: 2px solid var(--border-color);
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            position: relative;
        }
        .cashier-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .cashier-card:hover {
            transform: translateY(-6px);
            border-color: var(--primary);
            box-shadow: 0 20px 40px rgba(0,0,0,0.12);
        }
        .cashier-card:hover::before { opacity: 1; }
        
        .cashier-card .card-top {
            padding: 16px 20px;
            background: var(--primary-gradient-strong);
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .cashier-card .card-top .name {
            font-size: 1rem;
            font-weight: 600;
            color: white;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .cashier-card .card-top .name i {
            color: rgba(255,255,255,0.8);
        }
        .cashier-card .card-top .location-text {
            font-size: 0.7rem;
            color: rgba(255,255,255,0.85);
            display: flex;
            align-items: center;
            gap: 4px;
            margin-top: 2px;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            color: white;
            letter-spacing: 0.02em;
        }
        .badge-success { background: #059669; }
        .badge-danger { background: #DC2626; }
        .badge-warning { background: #D97706; color: #1E293B; }
        .badge-info { background: #0B5ED7; }
        
        .cashier-card .card-body { padding: 16px 20px; }
        .cashier-card .card-body .info-row {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 4px 0;
            font-size: 0.78rem;
            color: var(--text-secondary);
        }
        .cashier-card .card-body .info-row i {
            width: 18px;
            color: var(--primary);
            font-size: 0.75rem;
        }
        
        .revenue-breakdown {
            display: flex;
            gap: 12px;
            justify-content: center;
            padding: 8px 0 2px;
            border-top: 1px solid var(--border-color);
            margin-top: 8px;
            font-size: 0.6rem;
            color: var(--text-secondary);
            flex-wrap: wrap;
        }
        .revenue-breakdown .item { display: flex; align-items: center; gap: 4px; }
        .revenue-breakdown .item .label { font-weight: 500; }
        .revenue-breakdown .item .amount { font-weight: 700; }
        .revenue-breakdown .item .amount.blue { color: #0B5ED7; }
        .revenue-breakdown .item .amount.green { color: #059669; }
        .revenue-breakdown .item .amount.red { color: #DC2626; }
        .revenue-breakdown .item .amount.teal { color: #0D9488; }
        
        .cashier-card .card-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 4px;
            padding: 10px 14px;
            background: var(--bg-body);
            border-top: 2px solid var(--border-color);
            border-bottom: 2px solid var(--border-color);
        }
        [data-theme="dark"] .cashier-card .card-stats { background: #0F172A; }
        .cashier-card .card-stats .stat-item { text-align: center; padding: 4px 0; border-radius: 6px; }
        .cashier-card .card-stats .stat-item .num { font-size: 0.9rem; font-weight: 700; color: var(--text-primary); }
        .cashier-card .card-stats .stat-item .num.green { color: #059669; }
        .cashier-card .card-stats .stat-item .num.orange { color: #D97706; }
        .cashier-card .card-stats .stat-item .num.purple { color: #7C3AED; }
        .cashier-card .card-stats .stat-item .num.teal { color: #0D9488; }
        .cashier-card .card-stats .stat-item .num.red { color: #DC2626; }
        .cashier-card .card-stats .stat-item .label { font-size: 0.5rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.04em; font-weight: 700; }
        
        .cashier-card .card-actions {
            padding: 12px 18px;
            display: flex;
            gap: 8px;
            justify-content: flex-end;
            flex-wrap: wrap;
        }
        .cashier-card .card-actions .btn-action {
            padding: 5px 16px;
            border-radius: 8px;
            font-size: 0.65rem;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.3s;
            border: 2px solid var(--border-color);
            color: var(--text-secondary);
            background: transparent;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .cashier-card .card-actions .btn-action:hover {
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-2px);
        }
        .cashier-card .card-actions .btn-action.primary {
            background: var(--primary-gradient-strong);
            color: white;
            border-color: var(--primary);
        }
        .cashier-card .card-actions .btn-action.primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(11, 94, 215, 0.35);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
            background: var(--bg-card);
            border-radius: 18px;
            border: 2px dashed var(--border-color);
        }
        .empty-state i { font-size: 3.5rem; color: var(--border-color); margin-bottom: 16px; }
        .empty-state h3 { font-size: 1.2rem; color: var(--text-primary); margin-bottom: 8px; }
        
        .page-header-box {
            background: var(--primary-gradient);
            border-radius: 16px;
            padding: 20px 28px;
            margin-bottom: 24px;
            box-shadow: 0 6px 24px rgba(11, 94, 215, 0.2);
            position: relative;
            overflow: hidden;
        }
        .page-header-box::before {
            content: '';
            position: absolute;
            top: -60%;
            right: -10%;
            width: 350px;
            height: 350px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }
        .page-header-box .page-title {
            color: white;
            font-size: 1.6rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        .page-header-box .page-title .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            backdrop-filter: blur(4px);
        }
        .page-header-box .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
            margin-top: 4px;
        }
        .page-header-box .page-subtitle strong { color: white; font-weight: 600; }
        .page-header-box .header-badge {
            background: rgba(255,255,255,0.12);
            color: white;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 500;
            backdrop-filter: blur(4px);
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .page-header-box .header-badge i { opacity: 0.8; }
        .page-header-box .header-badge.revenue {
            background: rgba(251, 191, 36, 0.2);
            border-color: rgba(251, 191, 36, 0.3);
            color: #FBBF24;
        }
        .page-header-box .header-badge.bills {
            background: rgba(52, 211, 153, 0.2);
            border-color: rgba(52, 211, 153, 0.3);
            color: #6EE7B7;
        }
        .page-header-box .header-badge.expenses {
            background: rgba(239, 68, 68, 0.2);
            border-color: rgba(239, 68, 68, 0.3);
            color: #F87171;
        }
        .page-header-box .header-badge.discount {
            background: rgba(251, 191, 36, 0.25);
            border-color: rgba(251, 191, 36, 0.35);
            color: #FCD34D;
        }
        
        .btn-outline-light {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 18px;
            border-radius: 12px;
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
        .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        .footer {
            padding: 14px 0;
            border-top: 2px solid var(--border-color);
            margin-top: 20px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
            transition: border-color 0.3s ease, color 0.3s ease;
        }
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
            .stats-grid-8 { grid-template-columns: repeat(4, 1fr); }
        }
        @media (max-width: 768px) {
            .stats-grid-8 { grid-template-columns: 1fr 1fr; }
            .cashier-grid { grid-template-columns: 1fr; }
            .page-header-box .page-title { font-size: 1.3rem; }
            .page-header-box { padding: 16px 18px; }
            .stat-card-8 { padding: 14px 16px; min-height: 90px; }
            .stat-card-8 .stat-number { font-size: 1.4rem; }
            .stat-card-8 .stat-icon { width: 38px; height: 38px; font-size: 1rem; }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .filter-bar select, .filter-bar input { width: 100%; min-width: unset; }
        }
        @media (max-width: 480px) {
            .stats-grid-8 { grid-template-columns: 1fr; }
            .stat-card-8 { padding: 12px 14px; min-height: 80px; }
            .stat-card-8 .stat-number { font-size: 1.2rem; }
            .stat-card-8 .stat-icon { width: 34px; height: 34px; font-size: 0.85rem; }
            .page-header-box .page-title { font-size: 1rem; flex-direction: column; align-items: flex-start; }
            .page-header-box .page-subtitle { font-size: 0.75rem; flex-direction: column; align-items: flex-start; gap: 4px; }
            .cashier-card .card-stats { grid-template-columns: repeat(2, 1fr); }
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- MAIN CONTENT - Sidebar is loaded from admin_sidebar.php -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- PAGE HEADER - BLUE BOX -->
    <!-- ================================================================ -->
    <div class="page-header-box animate-fade-in-up">
        <div>
            <h1 class="page-title">
                <i class="fas fa-cash-register"></i>
                Cashiers Dashboard
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <strong><?= $total_cashiers ?></strong> branches found
                <span class="header-badge revenue">
                    <i class="fas fa-money-bill-wave"></i> TSh <?= number_format($financial['total_revenue'], 0) ?> Revenue
                </span>
                <span class="header-badge bills">
                    <i class="fas fa-file-invoice"></i> <?= number_format($financial['total_bills']) ?> Bills
                </span>
                <span class="header-badge expenses">
                    <i class="fas fa-arrow-up"></i> Expenses: TSh <?= number_format($financial['total_expenses'], 0) ?>
                </span>
                <span class="header-badge discount">
                    <i class="fas fa-tag"></i> Discounts: TSh <?= number_format($total_discounts, 0) ?>
                </span>
            </p>
        </div>
        <div style="position:relative;z-index:1;">
            <a href="add_cashier.php" class="btn-outline-light">
                <i class="fas fa-plus"></i> Add Cashier
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- 8 CARDS - CUSTOM COLORS -->
    <!-- ================================================================ -->
    <div class="stats-grid-8 animate-fade-in-up" style="animation-delay:0.05s;">
        
        <!-- 1. Total Revenue - BLUE -->
        <div class="stat-card-8 card-blue">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-content">
                <p class="stat-label">Total Revenue</p>
                <p class="stat-number">TSh <?= number_format($financial['total_revenue'], 0) ?></p>
                <p class="stat-sub">From bills + OTC (after discounts)</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>
        
        <!-- 2. Total Expenses - RED -->
        <div class="stat-card-8 card-red">
            <div class="stat-icon"><i class="fas fa-arrow-up"></i></div>
            <div class="stat-content">
                <p class="stat-label">Total Expenses</p>
                <p class="stat-number">TSh <?= number_format($financial['total_expenses'], 0) ?></p>
                <p class="stat-sub">From expenses table</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>
        
        <!-- 3. Net Profit - GREEN -->
        <div class="stat-card-8 card-green">
            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
            <div class="stat-content">
                <p class="stat-label">Net Profit</p>
                <p class="stat-number">TSh <?= number_format($financial['net_profit'], 0) ?></p>
                <p class="stat-sub">Revenue - Expenses</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>
        
        <!-- 4. Total Discounts - ORANGE -->
        <div class="stat-card-8 card-orange">
            <div class="stat-icon"><i class="fas fa-tag"></i></div>
            <div class="stat-content">
                <p class="stat-label">Total Discounts</p>
                <p class="stat-number">TSh <?= number_format($total_discounts, 0) ?></p>
                <p class="stat-sub">Pharmacy + Cashier discounts</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>
        
        <!-- 5. Prescription Revenue - BLUE -->
        <div class="stat-card-8 card-blue">
            <div class="stat-icon"><i class="fas fa-prescription"></i></div>
            <div class="stat-content">
                <p class="stat-label">Prescription Revenue</p>
                <p class="stat-number">TSh <?= number_format($financial['prescription_revenue'], 0) ?></p>
                <p class="stat-sub">From medication items</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>
        
        <!-- 6. OTC Revenue - BLUE -->
        <div class="stat-card-8 card-blue">
            <div class="stat-icon"><i class="fas fa-cash-register"></i></div>
            <div class="stat-content">
                <p class="stat-label">OTC Revenue</p>
                <p class="stat-number">TSh <?= number_format($financial['otc_revenue'], 0) ?></p>
                <p class="stat-sub">Over-the-counter sales</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>
        
        <!-- 7. Paid Bills - GREEN -->
        <div class="stat-card-8 card-green">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-content">
                <p class="stat-label">Paid Bills</p>
                <p class="stat-number"><?= number_format($financial['paid_bills']) ?></p>
                <p class="stat-sub">✅ Completed payments</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>
        
        <!-- 8. Pending Bills - ORANGE -->
        <div class="stat-card-8 card-orange">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-content">
                <p class="stat-label">Pending Bills</p>
                <p class="stat-number"><?= number_format($financial['pending_bills']) ?></p>
                <p class="stat-sub">⏳ Awaiting payment</p>
            </div>
            <i class="fas fa-arrow-right stat-arrow"></i>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- FILTER BAR -->
    <!-- ================================================================ -->
    <div class="filter-bar animate-fade-in-up" style="animation-delay:0.1s;">
        <span class="filter-label"><i class="fas fa-filter"></i> Filter</span>
        <form method="GET" class="flex flex-wrap gap-3 items-center w-full">
            <?php 
            $preserve_params = ['search', 'status'];
            foreach ($_GET as $key => $value) {
                if (in_array($key, $preserve_params) && !empty($value)) {
                    echo '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($value) . '">';
                }
            }
            ?>
            
            <select name="branch" class="flex-1 min-w-[150px]" onchange="this.form.submit()">
                <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $selected_branch_id == $b['id'] ? 'selected' : '' ?>>
                        🏥 <?= htmlspecialchars($b['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <select name="status" class="flex-1 min-w-[150px]" onchange="this.form.submit()">
                <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
            
            <input type="text" name="search" placeholder="Search by name, location..." value="<?= htmlspecialchars($search) ?>" class="flex-1 min-w-[200px]">
            
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Apply</button>
            <a href="cashiers.php" class="btn btn-outline"><i class="fas fa-times"></i> Reset</a>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- BRANCH GRID - SHOWS ALL ACTIVE BRANCHES WITH DISCOUNTS -->
    <!-- ================================================================ -->
    <?php if (count($cashiers) > 0): ?>
        <div class="cashier-grid animate-fade-in-up" style="animation-delay:0.15s;">
            <?php foreach ($cashiers as $cashier): ?>
                <?php 
                $net = ($cashier['total_revenue'] ?? 0) - ($cashier['branch_expenses'] ?? 0);
                ?>
                <div class="cashier-card">
                    <div class="card-top">
                        <div>
                            <div class="name">
                                <i class="fas fa-store-alt"></i>
                                <?= htmlspecialchars($cashier['name']) ?>
                            </div>
                            <div class="location-text">
                                <i class="fas fa-map-marker-alt"></i>
                                <?= htmlspecialchars($cashier['location'] ?? 'N/A') ?>
                            </div>
                        </div>
                        <span class="badge badge-<?= ($cashier['status'] ?? 'active') === 'active' ? 'success' : 'danger' ?>" style="font-size:0.6rem;padding:2px 12px;">
                            <?= ucfirst($cashier['status'] ?? 'Active') ?>
                        </span>
                    </div>
                    
                    <div class="card-body">
                        <div class="info-row">
                            <i class="fas fa-phone"></i>
                            <?= htmlspecialchars($cashier['phone'] ?? 'N/A') ?>
                        </div>
                        <div class="info-row">
                            <i class="fas fa-envelope"></i>
                            <?= htmlspecialchars($cashier['email'] ?? 'N/A') ?>
                        </div>
                        <div class="info-row">
                            <i class="fas fa-user-tie"></i>
                            <?= ($cashier['active_cashiers'] ?? 0) ?> Active / <?= ($cashier['total_cashiers'] ?? 0) ?> Cashiers
                        </div>
                        <div class="info-row">
                            <i class="fas fa-tag" style="color:#D97706;"></i>
                            Discounts: TSh <?= number_format($cashier['total_discounts'] ?? 0, 0) ?>
                            <span style="font-size:0.55rem;color:var(--text-secondary);margin-left:4px;">
                                (Pharm: TSh <?= number_format($cashier['pharmacy_discounts'] ?? 0, 0) ?> | Cash: TSh <?= number_format($cashier['cashier_discounts'] ?? 0, 0) ?>)
                            </span>
                        </div>
                        
                        <div class="revenue-breakdown">
                            <span class="item">
                                <span class="label">Bills:</span>
                                <span class="amount blue">TSh <?= number_format($cashier['bills_revenue'] ?? 0, 0) ?></span>
                            </span>
                            <span class="item">
                                <span class="label">OTC:</span>
                                <span class="amount green">TSh <?= number_format($cashier['otc_revenue'] ?? 0, 0) ?></span>
                            </span>
                            <span class="item">
                                <span class="label">Exp:</span>
                                <span class="amount red">TSh <?= number_format($cashier['branch_expenses'] ?? 0, 0) ?></span>
                            </span>
                            <span class="item" style="font-weight:700;color:var(--primary);">
                                <span class="label">Net:</span>
                                <span class="amount teal">TSh <?= number_format($net, 0) ?></span>
                            </span>
                        </div>
                    </div>
                    
                    <div class="card-stats">
                        <div class="stat-item">
                            <div class="num green"><?= number_format($cashier['total_bills'] ?? 0) ?></div>
                            <div class="label">Bills</div>
                        </div>
                        <div class="stat-item">
                            <div class="num <?= ($cashier['pending_bills'] ?? 0) > 0 ? 'orange' : 'green' ?>">
                                <?= number_format($cashier['pending_bills'] ?? 0) ?>
                            </div>
                            <div class="label">Pending</div>
                        </div>
                        <div class="stat-item">
                            <div class="num purple"><?= number_format($cashier['paid_bills'] ?? 0) ?></div>
                            <div class="label">Paid</div>
                        </div>
                        <div class="stat-item">
                            <div class="num teal">TSh <?= number_format($cashier['total_revenue'] ?? 0, 0) ?></div>
                            <div class="label">Revenue</div>
                        </div>
                    </div>
                    
                    <!-- CARD ACTIONS -->
                    <div class="card-actions">
                        <a href="cashiers.php?id=<?= (int)$cashier['id'] ?>" class="btn-action primary">
                            <i class="fas fa-eye"></i> View
                        </a>
                        <a href="edit_cashier.php?id=<?= (int)$cashier['id'] ?>" class="btn-action">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="text-center text-sm text-gray-500 dark:text-gray-400 py-2">
            Showing <strong><?= count($cashiers) ?></strong> branch<?= count($cashiers) > 1 ? 'es' : '' ?>
        </div>
    <?php else: ?>
        <div class="empty-state animate-fade-in-up">
            <i class="fas fa-store-alt"></i>
            <h3>No Branches Found</h3>
            <p>No active branches found. <a href="branches.php" class="text-primary hover:underline">Manage branches</a></p>
        </div>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Cashiers
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
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
            document.cookie = "dark_mode=false; path=/";
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
            document.cookie = "dark_mode=true; path=/";
        }
    });

    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        url.searchParams.delete('error');
        window.location.href = url.toString();
    }

    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        var url = new URL(window.location.href);
        url.searchParams.delete('error');
        if (query.length > 0) {
            url.searchParams.set('search', query);
        } else {
            url.searchParams.delete('search');
        }
        window.location.href = url.toString();
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

    function updateDateTime() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var dtEl = document.getElementById('currentDateTime');
        if (dtEl) dtEl.textContent = dateStr + ' • ' + timeStr;
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    console.log('%c💰 Braick Dispensary - Cashiers (FIXED)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Uses shared admin_sidebar.php with ALL menus', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Fixed: invalid_id error removed', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Fixed: Discounts included in calculations', 'font-size:13px; color:#34D399;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🏢 Total Branches: <?= $total_cashiers ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c💰 Total Discounts: TSh <?= number_format($total_discounts, 0) ?>', 'font-size:13px; color:#D97706;');
</script>

</body>
</html>