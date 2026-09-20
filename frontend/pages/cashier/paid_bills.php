<?php
// ================================================================
// FILE: frontend/pages/cashier/paid_bills.php
// CASHIER - PAID BILLS LIST (V2 - PAYMENT-BASED)
// ✅ Counts each PAYMENT (not each bill) — bill with multiple payments shows multiple rows
// ✅ Date filter uses payments.received_at
// ✅ Shows who received each payment (received_by)
// ✅ REMOVED: View button (only Print remains)
// ✅ KEPT: Scroll buttons, row number, search bar
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

$allowed_roles = ['cashier', 'reception', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Cashier';
$user_role = $_SESSION['role'] ?? 'cashier';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$user_phone = $_SESSION['phone'] ?? '';

$is_reception = ($user_role === 'reception');

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    $db->exec("SET time_zone = '+03:00'");
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$message = '';
$message_type = '';
$currency = 'TSh';

// GET ADMIN CONTACT NUMBERS
$admin_phones = [];
try {
    $stmt = $db->prepare("
        SELECT phone FROM users 
        WHERE role = 'admin' AND branch_id = ? AND status = 'active'
        ORDER BY id ASC
    ");
    $stmt->execute([$user_branch_id]);
    $admin_phones = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $admin_phones = [];
}

// GET BRANCH PHONE
$branch_phone = '';
try {
    $stmt = $db->prepare("SELECT phone FROM branches WHERE id = ?");
    $stmt->execute([$user_branch_id]);
    $branch_phone = $stmt->fetchColumn();
} catch (Exception $e) {
    $branch_phone = '';
}

// GET FILTER PARAMETERS
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$paid_bills = [];
$total_bills = 0;

// GET SYSTEM SETTINGS
try {
    $settings = [];
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $currency = $settings['currency'] ?? 'TSh';
} catch (Exception $e) {
    $currency = 'TSh';
}

// ================================================================
// DATE FILTER (uses payments.received_at for bills, otc_sales.created_at for OTC)
// ================================================================
$date_condition_bills = "";
$date_condition_otc = "";
$params_bills = [$user_branch_id];
$params_otc = [$user_branch_id];

switch ($filter) {
    case 'today':
        $date_condition_bills = "AND DATE(p.received_at) = CURDATE()";
        $date_condition_otc = "AND DATE(o.created_at) = CURDATE()";
        break;
    case 'week':
        $date_condition_bills = "AND p.received_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $date_condition_otc = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case 'month':
        $date_condition_bills = "AND p.received_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        $date_condition_otc = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        break;
    case '3months':
        $date_condition_bills = "AND p.received_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
        $date_condition_otc = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
        break;
    case '6months':
        $date_condition_bills = "AND p.received_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
        $date_condition_otc = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
        break;
    case 'year':
        $date_condition_bills = "AND p.received_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        $date_condition_otc = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        break;
    case 'custom':
        if (!empty($start_date) && !empty($end_date)) {
            $date_condition_bills = "AND DATE(p.received_at) BETWEEN ? AND ?";
            $date_condition_otc = "AND DATE(o.created_at) BETWEEN ? AND ?";
            $params_bills[] = $start_date;
            $params_bills[] = $end_date;
            $params_otc[] = $start_date;
            $params_otc[] = $end_date;
        }
        break;
    default:
        $date_condition_bills = "";
        $date_condition_otc = "";
        break;
}

// BUILD SEARCH CONDITION
$search_condition_bills = "";
$search_condition_otc = "";
if (!empty($search)) {
    $search_condition_bills = "AND (pat.full_name LIKE ? OR b.bill_number LIKE ? OR pat.phone LIKE ? OR p.receipt_number LIKE ? OR u_recv.full_name LIKE ?)";
    $params_bills[] = "%$search%";
    $params_bills[] = "%$search%";
    $params_bills[] = "%$search%";
    $params_bills[] = "%$search%";
    $params_bills[] = "%$search%";
    
    $search_condition_otc = "AND (o.customer_name LIKE ? OR o.sale_number LIKE ? OR o.customer_phone LIKE ? OR u_sold.full_name LIKE ?)";
    $params_otc[] = "%$search%";
    $params_otc[] = "%$search%";
    $params_otc[] = "%$search%";
    $params_otc[] = "%$search%";
}

// ================================================================
// GET PAID PAYMENTS - EACH PAYMENT IS ONE ROW
// ================================================================
try {
    $all_paid = [];
    
    // 1. PAYMENTS FOR REGULAR BILLS (each payment = one row)
    $sql_payments = "
        SELECT 
            p.id as payment_id,
            p.receipt_number,
            p.amount as payment_amount,
            p.payment_method,
            p.received_at as paid_date,
            p.received_by,
            b.id as bill_id,
            b.bill_number,
            b.patient_id,
            b.status as bill_status,
            b.premium_amount,
            b.total_amount as bill_total,
            b.paid_amount as bill_paid,
            b.balance as bill_balance,
            pat.full_name as patient_name,
            pat.patient_id as patient_code,
            pat.phone as patient_phone,
            pat.gender as patient_gender,
            u_recv.full_name as received_by_name,
            u_recv.role as received_by_role,
            u_created.full_name as cashier_name,
            'Regular' as bill_type,
            b.id as reference_id,
            b.bill_number as reference_number,
            (SELECT COUNT(*) FROM bill_items WHERE bill_id = b.id AND status != 'cancelled') as item_count,
            v.visit_number,
            v.visit_type
        FROM payments p
        INNER JOIN bills b ON p.bill_id = b.id
        LEFT JOIN patients pat ON b.patient_id = pat.id
        LEFT JOIN users u_recv ON p.received_by = u_recv.id
        LEFT JOIN users u_created ON b.created_by = u_created.id
        LEFT JOIN visits v ON b.visit_id = v.id
        WHERE b.branch_id = ? 
        AND b.patient_id IS NOT NULL
        AND b.visit_id IS NOT NULL
        AND b.bill_number NOT LIKE 'BILL-OTC-%'
        $date_condition_bills
        $search_condition_bills
    ";
    
    $stmt = $db->prepare($sql_payments);
    $stmt->execute($params_bills);
    $payment_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($payment_rows as $row) {
        $all_paid[] = $row;
    }
    
    // 2. OTC PAID SALES
    $sql_otc = "
        SELECT 
            o.id as payment_id,
            o.sale_number as receipt_number,
            o.total_amount as payment_amount,
            o.payment_method,
            o.created_at as paid_date,
            o.sold_by as received_by,
            NULL as bill_id,
            o.sale_number as bill_number,
            o.patient_id,
            'paid' as bill_status,
            0 as premium_amount,
            o.total_amount as bill_total,
            o.total_amount as bill_paid,
            0 as bill_balance,
            COALESCE(o.customer_name, 'Walk-in Customer') as patient_name,
            o.patient_id as patient_code,
            o.customer_phone as patient_phone,
            NULL as patient_gender,
            u_sold.full_name as received_by_name,
            u_sold.role as received_by_role,
            u_sold.full_name as cashier_name,
            'OTC' as bill_type,
            o.id as reference_id,
            o.sale_number as reference_number,
            (SELECT COUNT(*) FROM otc_sale_items WHERE sale_id = o.id) as item_count,
            NULL as visit_number,
            'OTC Sale' as visit_type
        FROM otc_sales o
        LEFT JOIN users u_sold ON o.sold_by = u_sold.id
        WHERE o.branch_id = ? 
        AND o.payment_status = 'paid'
        $date_condition_otc
        $search_condition_otc
    ";
    
    $stmt = $db->prepare($sql_otc);
    $stmt->execute($params_otc);
    $otc_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($otc_rows as $row) {
        $all_paid[] = $row;
    }
    
    // SORT BY PAID DATE (newest first)
    usort($all_paid, function($a, $b) {
        return strtotime($b['paid_date']) - strtotime($a['paid_date']);
    });
    
    $paid_bills = $all_paid;
    $total_bills = count($paid_bills);
    
} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $paid_bills = [];
    $total_bills = 0;
}

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

include_once '../../components/cashier_header.php';
include_once '../../components/cashier_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paid Bills - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
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
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --premium-color: #D97706;
            --premium-bg: #FEF3C7;
            --otc-color: #8B5CF6;
            --otc-bg: #EDE9FE;
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
            --shadow-md: 0 4px 6px rgba(0,0,0,0.07);
            --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --table-stripe: #E8F0FE;
            --table-hover: #D1FAE5;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
            --table-stripe: #1E293B;
            --table-hover: #1A3A2A;
            --otc-bg: #2A1A3A;
            --premium-bg: #3D2E0A;
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
        ::-webkit-scrollbar-thumb { background: var(--success); border-radius: 10px; }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--success), var(--success-dark));
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 20px rgba(5, 150, 105, 0.25);
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
        
        .page-header .page-subtitle strong { color: white; font-weight: 600; }
        
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
        
        .page-header .header-badge {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            backdrop-filter: blur(4px);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 18px;
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
            white-space: nowrap;
        }
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        /* ✅ 4 SUMMARY CARDS - COUNTS ONLY */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 24px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .summary-card {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 16px 18px;
            border: 2px solid var(--border-color);
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow: hidden;
        }
        
        .summary-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }
        
        .summary-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
            border-color: var(--success-light);
        }
        
        .summary-card .card-label {
            font-size: 0.6rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: block;
        }
        
        .summary-card .card-value {
            font-size: 1.5rem;
            font-weight: 700;
            display: block;
            margin-top: 4px;
            font-family: monospace;
        }
        
        .summary-card .card-sub {
            font-size: 0.5rem;
            color: var(--text-secondary);
            display: block;
            margin-top: 2px;
        }
        
        .summary-card.total-card { border-color: var(--primary); }
        .summary-card.total-card::before { background: var(--primary); }
        .summary-card.total-card .card-value { color: var(--primary); }
        
        .summary-card.paid-card { border-color: var(--success); }
        .summary-card.paid-card::before { background: var(--success); }
        .summary-card.paid-card .card-value { color: var(--success); }
        
        .summary-card.premium-card { border-color: var(--premium-color); }
        .summary-card.premium-card::before { background: var(--premium-color); }
        .summary-card.premium-card .card-value { color: var(--premium-color); }
        
        .summary-card.otc-card { border-color: var(--otc-color); }
        .summary-card.otc-card::before { background: var(--otc-color); }
        .summary-card.otc-card .card-value { color: var(--otc-color); }
        
        .filter-section {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 16px 20px;
            border: 1px solid var(--border-color);
            margin-bottom: 20px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
            box-shadow: var(--shadow-sm);
        }
        
        .filter-section:hover {
            border-color: var(--success);
            box-shadow: var(--shadow-md);
        }
        
        .filter-btn {
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            border: 2px solid var(--border-color);
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .filter-btn:hover {
            border-color: var(--success);
            color: var(--success);
            background: var(--success-bg);
        }
        
        .filter-btn.active {
            background: var(--success);
            color: white;
            border-color: var(--success);
        }
        
        .filter-btn.active:hover {
            background: var(--success-dark);
            border-color: var(--success-dark);
        }
        
        .filter-btn i { margin-right: 4px; }
        
        .filter-group {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px;
        }
        
        .filter-group .filter-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-right: 4px;
        }
        
        .date-picker-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .date-picker-group .form-control {
            padding: 4px 10px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.75rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: all 0.3s;
            width: auto;
        }
        
        .date-picker-group .form-control:focus {
            border-color: var(--success);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
        }
        
        .date-picker-group .btn-apply {
            padding: 4px 14px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            background: var(--success);
            color: white;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .date-picker-group .btn-apply:hover {
            background: var(--success-dark);
            transform: translateY(-1px);
        }
        
        .card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 20px 24px;
            border: 1px solid var(--border-color);
            transition: all 0.3s;
            box-shadow: var(--shadow-sm);
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .card:hover {
            border-color: var(--success);
            box-shadow: var(--shadow-md);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .card-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .table-search-bar {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 12px;
            padding: 10px 14px;
            background: linear-gradient(135deg, #ECFDF5, #D1FAE5);
            border: 2px solid var(--success);
            border-radius: 12px;
            animation: slideDown 0.4s ease;
        }
        
        [data-theme="dark"] .table-search-bar {
            background: linear-gradient(135deg, #1A3A2A, #0F2A1E);
        }
        
        .table-search-bar .search-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            background: var(--bg-card);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 0 14px;
            transition: all 0.3s ease;
            flex: 1;
            min-width: 250px;
            height: 38px;
        }
        
        .table-search-bar .search-wrapper:focus-within {
            border-color: var(--success);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.15);
        }
        
        .table-search-bar .search-wrapper .search-icon {
            color: var(--success);
            font-size: 0.85rem;
            margin-right: 8px;
        }
        
        .table-search-bar .search-wrapper input {
            flex: 1;
            background: transparent;
            border: none;
            outline: none;
            color: var(--text-primary);
            font-size: 0.8rem;
            padding: 0;
            font-weight: 500;
        }
        
        .table-search-bar .search-wrapper input::placeholder {
            color: var(--text-secondary);
            opacity: 0.7;
        }
        
        .table-search-bar .search-wrapper .search-clear {
            background: var(--gray-200);
            border: none;
            color: var(--text-secondary);
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
        
        .table-search-bar .search-wrapper .search-clear.visible { display: flex; }
        .table-search-bar .search-wrapper .search-clear:hover {
            background: var(--danger);
            color: white;
        }
        
        .table-search-bar .search-results-count {
            color: var(--success);
            font-size: 0.7rem;
            font-weight: 700;
            background: var(--bg-card);
            padding: 5px 12px;
            border-radius: 10px;
            white-space: nowrap;
            border: 1px solid var(--success);
            display: none;
        }
        
        .table-search-bar .search-results-count.visible { display: inline-block; }
        
        .table-search-bar .search-hint {
            font-size: 0.6rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
        }
        
        .table-search-bar .search-hint kbd {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 1px 6px;
            font-size: 0.55rem;
            font-family: monospace;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        mark.search-highlight {
            background: #FEF08A;
            color: #713F12;
            padding: 0 2px;
            border-radius: 3px;
            font-weight: 700;
            box-shadow: 0 0 0 1px rgba(245, 158, 11, 0.3);
            animation: highlight-pulse 0.6s ease;
        }
        
        [data-theme="dark"] mark.search-highlight {
            background: #FCD34D;
            color: #422006;
        }
        
        @keyframes highlight-pulse {
            0% { background-color: #FDE047; transform: scale(1.05); }
            50% { background-color: #FCD34D; transform: scale(1.1); }
            100% { background-color: #FEF08A; transform: scale(1); }
        }
        
        .table-scroll-btn {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 1.5px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-primary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            transition: all 0.25s ease;
            padding: 0;
            flex-shrink: 0;
        }
        
        .table-scroll-btn:hover {
            background: var(--success);
            color: white;
            border-color: var(--success);
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
        }
        
        .table-scroll-btn:active {
            transform: scale(0.9);
        }
        
        .table-header-actions {
            display: flex;
            gap: 6px;
            align-items: center;
            margin-left: 8px;
            padding-left: 12px;
            border-left: 2px solid var(--border-color);
        }
        
        .table-wrap {
            overflow-x: auto;
            scroll-behavior: smooth;
        }
        
        .table-wrap::-webkit-scrollbar { height: 8px; }
        .table-wrap::-webkit-scrollbar-track { background: var(--gray-100); border-radius: 10px; }
        .table-wrap::-webkit-scrollbar-thumb { background: var(--success); border-radius: 10px; }
        .table-wrap::-webkit-scrollbar-thumb:hover { background: var(--success-dark); }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.78rem;
            min-width: 1050px;
        }
        
        .data-table thead th {
            text-align: left;
            padding: 8px 10px;
            font-weight: 700;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: white;
            background: var(--success);
            border-bottom: 3px solid var(--success-dark);
            white-space: nowrap;
        }
        
        .data-table thead th:first-child { border-radius: 8px 0 0 0; }
        .data-table thead th:last-child { border-radius: 0 8px 0 0; }
        
        .data-table td {
            padding: 7px 10px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .data-table tbody tr:hover td {
            background: var(--table-hover);
        }
        
        .row-number-cell {
            text-align: center;
            font-weight: 700;
            font-size: 0.75rem;
            color: var(--success);
            background: var(--success-bg);
            font-family: 'Courier New', monospace;
            width: 45px;
            min-width: 45px;
        }
        
        [data-theme="dark"] .row-number-cell {
            background: #1A3A2A;
            color: #34D399;
        }
        
        .data-table tbody tr:hover .row-number-cell {
            background: var(--success);
            color: white;
        }
        
        [data-theme="dark"] .data-table tbody tr:hover .row-number-cell {
            background: var(--success-dark);
            color: white;
        }
        
        .status-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.55rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-badge.paid { background: #D1FAE5; color: #059669; }
        .status-badge.otc { background: #EDE9FE; color: #6D28D9; }
        
        .bill-type-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.5rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .bill-type-badge.regular { background: #E8F0FE; color: #0B5ED7; }
        .bill-type-badge.otc { background: #EDE9FE; color: #6D28D9; }
        
        /* ✅ PRINT BUTTON STYLING */
        .btn-print-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.65rem;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            text-decoration: none;
            background: #059669;
            color: #FFFFFF;
            white-space: nowrap;
            width: 100%;
            min-width: 70px;
        }
        
        .btn-print-action i { font-size: 0.75rem; }
        
        .btn-print-action:hover {
            background: var(--success-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.4);
            color: white;
        }
        
        .btn-print-action.otc {
            background: #7C3AED;
        }
        
        .btn-print-action.otc:hover {
            background: #6D28D9;
            box-shadow: 0 4px 12px rgba(124, 58, 237, 0.4);
        }
        
        /* ✅ RECEIVED BY BADGE */
        .received-by-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 600;
            background: var(--primary-bg);
            color: var(--primary);
            white-space: nowrap;
        }
        
        .received-by-badge i { font-size: 0.6rem; }
        
        .received-by-badge.cashier { background: #FEF3C7; color: #D97706; }
        .received-by-badge.reception { background: #DBEAFE; color: #1E40AF; }
        .received-by-badge.admin { background: #FCE7F3; color: #BE185D; }
        .received-by-badge.pharmacy { background: #D1FAE5; color: #059669; }
        
        .amount-cell {
            font-family: monospace;
            font-weight: 700;
            color: var(--success);
            text-align: right;
            font-size: 0.8rem;
            white-space: nowrap;
        }
        
        .stat-card {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 16px 20px;
            border: 1px solid var(--border-color);
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
        }
        
        .stat-card:hover {
            border-color: var(--success);
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        
        .stat-card .stat-number { font-size: 1.8rem; font-weight: 700; }
        .stat-card .stat-number.green { color: var(--success); }
        .stat-card .stat-number.purple { color: var(--otc-color); }
        .stat-card .stat-number.blue { color: var(--primary); }
        .stat-card .stat-label { font-size: 0.7rem; color: var(--text-secondary); font-weight: 500; }
        .stat-card .stat-icon { font-size: 1.4rem; margin-bottom: 4px; }
        
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        .footer .footer-brand { color: var(--success); font-weight: 600; }
        
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 14px 20px;
            border-radius: 12px;
            z-index: 999;
            max-width: 400px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            box-shadow: var(--shadow-lg);
        }
        .toast-custom.show { transform: translateY(0); opacity: 1; }
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }
        
        @keyframes fadeInOut {
            0% { opacity: 0; transform: translateY(20px) scale(0.9); }
            30% { opacity: 1; transform: translateY(0) scale(1); }
            80% { opacity: 1; transform: translateY(0) scale(1); }
            100% { opacity: 0; transform: translateY(-15px) scale(0.9); }
        }
        
        .scroll-toast {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: var(--success);
            color: white;
            padding: 10px 20px;
            border-radius: 24px;
            font-size: 0.8rem;
            font-weight: 600;
            z-index: 9999;
            box-shadow: 0 6px 20px rgba(5, 150, 105, 0.4);
            display: flex;
            align-items: center;
            gap: 8px;
            animation: fadeInOut 1.2s ease forwards;
            pointer-events: none;
        }
        .scroll-toast i { font-size: 1rem; }
        
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
            .summary-cards { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .filter-section { padding: 12px 14px; }
            .filter-btn { font-size: 0.6rem; padding: 3px 10px; }
            .card { padding: 14px 16px; }
            .summary-cards { grid-template-columns: 1fr 1fr; }
            .table-scroll-btn { width: 28px; height: 28px; font-size: 0.7rem; }
            .table-header-actions { margin-left: 4px; padding-left: 8px; }
            .table-search-bar .search-wrapper { min-width: 100%; }
            .table-search-bar { flex-direction: column; align-items: stretch; }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .filter-btn { font-size: 0.55rem; padding: 2px 8px; }
            .date-picker-group { flex-direction: column; align-items: stretch; }
            .date-picker-group .form-control { width: 100%; }
            .date-picker-group .btn-apply { width: 100%; justify-content: center; }
            .card { padding: 10px 12px; }
            .data-table { font-size: 0.6rem; min-width: 700px; }
            .summary-cards { grid-template-columns: 1fr 1fr; }
            .table-scroll-btn { width: 26px; height: 26px; font-size: 0.65rem; }
            .row-number-cell { width: 35px; min-width: 35px; font-size: 0.65rem; }
            .btn-print-action { font-size: 0.55rem; padding: 4px 10px; min-width: 60px; }
        }
    </style>
    
    <script>
        (function() {
            var darkMode = localStorage.getItem('darkMode');
            if (darkMode === 'true') {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>
</head>
<body>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-check-circle"></i>
                Paid Bills
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;"><?= strtoupper($user_role) ?></span>
                <?php if ($is_reception): ?>
                    <span class="role-badge-display" style="background:rgba(52,211,153,0.3);color:#34D399;border-color:rgba(52,211,153,0.3);">
                        <i class="fas fa-check-circle"></i> Full Access
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-file-invoice"></i>
                All payments received in <strong><?= htmlspecialchars($user_branch_name) ?></strong>
                
                <span class="header-badge">
                    <i class="fas fa-receipt"></i>
                    <?= $total_bills ?> Payments
                </span>
                
                <span class="header-badge" style="background:rgba(139,92,246,0.2);border-color:rgba(139,92,246,0.2);">
                    <i class="fas fa-shopping-cart"></i>
                    Including OTC Sales
                </span>
            </p>
        </div>
        <div class="header-right" style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <button onclick="manualRefresh()" class="btn-outline-light" id="refreshBtn">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="p-4 rounded-xl mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200' ?>" style="max-width:1200px;margin:0 auto 16px;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- ✅ 4 SUMMARY CARDS - COUNTS ONLY -->
    <div class="summary-cards" id="summaryCards">
        <div class="summary-card total-card">
            <span class="card-label">📋 Total Payments</span>
            <span class="card-value" id="summaryTotal"><?= $total_bills ?></span>
            <span class="card-sub">All payment records</span>
        </div>
        
        <div class="summary-card paid-card">
            <span class="card-label">✅ Regular Payments</span>
            <span class="card-value" id="summaryPaid">
                <?php 
                    $regular_count = 0;
                    foreach ($paid_bills as $b) {
                        if (($b['bill_type'] ?? '') === 'Regular') $regular_count++;
                    }
                    echo $regular_count;
                ?>
            </span>
            <span class="card-sub">Bill payments</span>
        </div>
        
        <div class="summary-card otc-card">
            <span class="card-label">🛒 OTC Sales</span>
            <span class="card-value" id="summaryOTC">
                <?php 
                    $otc_count = 0;
                    foreach ($paid_bills as $b) {
                        if (($b['bill_type'] ?? '') === 'OTC') $otc_count++;
                    }
                    echo $otc_count;
                ?>
            </span>
            <span class="card-sub">Over-the-counter</span>
        </div>
        
        <div class="summary-card premium-card">
            <span class="card-label">👑 Premium Bills</span>
            <span class="card-value" id="summaryPremium">
                <?php 
                    $premium_count = 0;
                    foreach ($paid_bills as $b) {
                        if ((float)($b['premium_amount'] ?? 0) > 0) $premium_count++;
                    }
                    echo $premium_count;
                ?>
            </span>
            <span class="card-sub">With premium charge</span>
        </div>
    </div>

    <!-- FILTERS -->
    <div class="filter-section">
        <div class="filter-group" style="margin-bottom:8px;">
            <span class="filter-label"><i class="fas fa-calendar-alt"></i> Filter:</span>
            
            <a href="?filter=all&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">
                <i class="fas fa-globe"></i> All
            </a>
            <a href="?filter=today&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === 'today' ? 'active' : '' ?>">
                <i class="fas fa-calendar-day"></i> Today
            </a>
            <a href="?filter=week&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === 'week' ? 'active' : '' ?>">
                <i class="fas fa-calendar-week"></i> 1 Week
            </a>
            <a href="?filter=month&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === 'month' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i> 1 Month
            </a>
            <a href="?filter=3months&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === '3months' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i> 3 Months
            </a>
            <a href="?filter=6months&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === '6months' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i> 6 Months
            </a>
            <a href="?filter=year&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === 'year' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i> 1 Year
            </a>
        </div>
        
        <form method="GET" action="" class="filter-group" style="border-top:1px solid var(--border-color);padding-top:8px;margin-top:4px;">
            <input type="hidden" name="filter" value="custom">
            <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
            
            <span class="filter-label"><i class="fas fa-calendar-plus"></i> Custom:</span>
            
            <div class="date-picker-group">
                <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>" placeholder="Start Date">
                <span style="color:var(--text-secondary);font-size:0.7rem;">to</span>
                <input type="date" name="end_date" class="form-control" value="<?= $end_date ?>" placeholder="End Date">
                <button type="submit" class="btn-apply">
                    <i class="fas fa-check"></i> Apply
                </button>
                <?php if ($filter === 'custom' && !empty($start_date) && !empty($end_date)): ?>
                    <a href="?filter=all&search=<?= urlencode($search) ?>" class="btn-apply" style="background:#DC2626;">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- QUICK STATS -->
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-5" style="max-width:1200px;margin:0 auto;">
        <div class="stat-card">
            <div class="stat-icon">📋</div>
            <p class="stat-number green"><?= $total_bills ?></p>
            <p class="stat-label">Total Payments</p>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📄</div>
            <p class="stat-number blue">
                <?php 
                    $regular_count = 0;
                    foreach ($paid_bills as $b) {
                        if (($b['bill_type'] ?? '') === 'Regular') $regular_count++;
                    }
                    echo $regular_count;
                ?>
            </p>
            <p class="stat-label">Regular Payments</p>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🛒</div>
            <p class="stat-number purple">
                <?php 
                    $otc_count = 0;
                    foreach ($paid_bills as $b) {
                        if (($b['bill_type'] ?? '') === 'OTC') $otc_count++;
                    }
                    echo $otc_count;
                ?>
            </p>
            <p class="stat-label">OTC Sales</p>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📅</div>
            <p class="stat-number blue">
                <?php 
                    if ($filter === 'today') echo 'Today';
                    elseif ($filter === 'week') echo '7 Days';
                    elseif ($filter === 'month') echo '30 Days';
                    elseif ($filter === '3months') echo '90 Days';
                    elseif ($filter === '6months') echo '180 Days';
                    elseif ($filter === 'year') echo '365 Days';
                    elseif ($filter === 'custom') echo 'Custom';
                    else echo 'All Time';
                ?>
            </p>
            <p class="stat-label">Date Range</p>
        </div>
    </div>

    <!-- PAID BILLS TABLE -->
    <div class="card" style="max-width:1200px;margin:0 auto;">
        
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
            <h3 class="card-title">
                <i class="fas fa-list" style="color:var(--success);"></i> Payments & OTC Sales
                <span class="text-sm font-normal text-gray-400">(<?= $total_bills ?> records)</span>
            </h3>
            
            <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                <span class="text-xs text-gray-400">
                    <i class="fas fa-clock"></i> Updated: <?= date('h:i:s A') ?>
                </span>
                <span class="text-xs text-gray-400" style="background:var(--primary-bg);padding:2px 8px;border-radius:8px;">
                    <i class="fas fa-file-invoice"></i> Regular: <?= $regular_count ?>
                </span>
                <span class="text-xs text-gray-400" style="background:var(--otc-bg);padding:2px 8px;border-radius:8px;color:var(--otc-color);">
                    <i class="fas fa-shopping-cart"></i> OTC: <?= $otc_count ?>
                </span>
                
                <div class="table-header-actions">
                    <button class="table-scroll-btn" 
                            onclick="scrollTableById('paidBillsTableWrap', 'left')" 
                            title="Scroll Left (◀)">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button class="table-scroll-btn" 
                            onclick="scrollTableById('paidBillsTableWrap', 'right')" 
                            title="Scroll Right (▶)">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- TABLE SEARCH BAR -->
        <div class="table-search-bar">
            <div class="search-wrapper">
                <i class="fas fa-search search-icon"></i>
                <input type="text" 
                       id="tableSearch" 
                       placeholder="Search by receipt #, bill #, patient, received by..." 
                       autocomplete="off"
                       value="<?= htmlspecialchars($search) ?>">
                <button type="button" class="search-clear" id="searchClear" title="Clear search">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <span class="search-results-count" id="searchResultsCount"></span>
            <span class="search-hint">
                <i class="fas fa-keyboard"></i> <kbd>Ctrl</kbd>+<kbd>K</kbd>
            </span>
        </div>
        
        <div class="table-wrap" id="paidBillsTableWrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="min-width:45px;text-align:center;">#</th>
                        <th style="min-width:120px;">Receipt #</th>
                        <th style="min-width:100px;">Bill #</th>
                        <th style="min-width:60px;text-align:center;">Type</th>
                        <th style="min-width:140px;">Patient</th>
                        <th style="min-width:60px;text-align:center;">Items</th>
                        <th style="min-width:130px;">Received By</th>
                        <th style="min-width:90px;text-align:right;">Amount</th>
                        <th style="min-width:90px;text-align:center;">Date</th>
                        <th style="min-width:80px;text-align:center;">Print</th>
                    </tr>
                </thead>
                <tbody id="paidBillsTableBody">
                    <?php if (is_array($paid_bills) && count($paid_bills) > 0): ?>
                        <?php 
                        $row_number = 1;
                        foreach ($paid_bills as $bill): 
                            $is_otc = ($bill['bill_type'] ?? '') === 'OTC';
                            $bill_premium = (float)($bill['premium_amount'] ?? 0);
                            $payment_amount = (float)($bill['payment_amount'] ?? 0);
                            $received_by_name = $bill['received_by_name'] ?? 'N/A';
                            $received_by_role = strtolower($bill['received_by_role'] ?? 'user');
                            
                            $search_data = strtolower(
                                ($bill['receipt_number'] ?? '') . ' ' .
                                ($bill['bill_number'] ?? '') . ' ' .
                                ($bill['patient_name'] ?? '') . ' ' .
                                ($bill['patient_phone'] ?? '') . ' ' .
                                ($bill['received_by_name'] ?? '') . ' ' .
                                ($bill['cashier_name'] ?? '') . ' ' .
                                ($bill['visit_number'] ?? '') . ' ' .
                                ($bill['bill_type'] ?? '')
                            );
                        ?>
                            <tr class="bill-row" data-search="<?= htmlspecialchars($search_data) ?>">
                                <td class="row-number-cell">
                                    <?= $row_number++ ?>
                                </td>
                                <td>
                                    <span style="font-size:0.65rem;color:var(--success);font-weight:800;background:var(--success-bg);padding:3px 7px;border-radius:5px;display:inline-block;font-family:monospace;" data-searchable>
                                        <?= htmlspecialchars($bill['receipt_number'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size:0.65rem;font-weight:700;font-family:monospace;<?= $is_otc ? 'color:#7C3AED;' : 'color:#0B5ED7;' ?>" data-searchable>
                                        <?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?>
                                    </span>
                                    <?php if ($bill_premium > 0): ?>
                                        <span style="display:block;margin-top:2px;font-size:0.5rem;background:#FEF3C7;color:#D97706;padding:1px 6px;border-radius:8px;font-weight:600;">
                                            <i class="fas fa-crown"></i> Premium
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <?php if ($is_otc): ?>
                                        <span class="bill-type-badge otc">
                                            <i class="fas fa-shopping-cart"></i> OTC
                                        </span>
                                    <?php else: ?>
                                        <span class="bill-type-badge regular">
                                            <i class="fas fa-file-invoice"></i> Reg
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-weight:500;font-size:0.8rem;" data-searchable><?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?></div>
                                    <div style="font-size:0.6rem;color:var(--text-secondary);" data-searchable>
                                        <?= htmlspecialchars($bill['patient_phone'] ?? 'No phone') ?>
                                    </div>
                                </td>
                                <td style="text-align:center;">
                                    <span style="font-weight:600;font-size:0.8rem;"><?= $bill['item_count'] ?? 0 ?></span>
                                </td>
                                <td>
                                    <span class="received-by-badge <?= htmlspecialchars($received_by_role) ?>" data-searchable>
                                        <i class="fas fa-user"></i>
                                        <?= htmlspecialchars($received_by_name) ?>
                                    </span>
                                    <div style="font-size:0.55rem;color:var(--text-secondary);margin-top:2px;text-transform:uppercase;font-weight:600;">
                                        <?= htmlspecialchars(strtoupper($received_by_role)) ?>
                                    </div>
                                </td>
                                <td class="amount-cell">
                                    <?= $currency ?> <?= number_format($payment_amount, 0) ?>
                                </td>
                                <td style="text-align:center;">
                                    <div style="font-size:0.7rem;">
                                        <?= isset($bill['paid_date']) ? date('d/m/y', strtotime($bill['paid_date'])) : 'N/A' ?>
                                    </div>
                                    <div style="font-size:0.55rem;color:var(--text-secondary);">
                                        <?= isset($bill['paid_date']) ? date('h:i A', strtotime($bill['paid_date'])) : '' ?>
                                    </div>
                                </td>
                                <td style="text-align:center;">
                                    <?php if ($is_otc): ?>
                                        <a href="print_receipt.php?type=otc&sale_id=<?= $bill['reference_id'] ?>&print=1" 
                                           class="btn-print-action otc" 
                                           title="Print OTC Receipt" 
                                           target="_blank">
                                            <i class="fas fa-print"></i> Print
                                        </a>
                                    <?php else: ?>
                                        <a href="print_receipt.php?payment_id=<?= $bill['payment_id'] ?>&bill_id=<?= $bill['bill_id'] ?>&print=1" 
                                           class="btn-print-action" 
                                           title="Print Payment Receipt" 
                                           target="_blank">
                                            <i class="fas fa-print"></i> Print
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" style="text-align:center;padding:30px 20px;color:var(--text-secondary);">
                                <i class="fas fa-check-circle" style="font-size:2.5rem;display:block;margin-bottom:12px;color:var(--success);"></i>
                                <p style="font-size:1rem;font-weight:600;">No payments found</p>
                                <p style="font-size:0.8rem;margin-top:4px;">
                                    <?php if ($filter !== 'all'): ?>
                                        No payments received for the selected date range
                                    <?php else: ?>
                                        No payments have been made yet
                                    <?php endif; ?>
                                </p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- NO SEARCH RESULTS -->
        <div id="noSearchResults" style="display:none;text-align:center;padding:40px 20px;">
            <i class="fas fa-search" style="font-size:3rem;color:var(--success);display:block;margin-bottom:12px;"></i>
            <p style="font-size:1rem;font-weight:600;color:var(--text-primary);">No payments match your search</p>
            <p style="font-size:0.8rem;color:var(--text-secondary);margin-top:4px;">Try a different keyword</p>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Payments & OTC Sales (Payment-Based)
            <span class="text-gray-300 mx-2">|</span>
            <span class="text-gray-400">👤 <?= htmlspecialchars($user_full_name) ?></span>
            <?php if ($is_reception): ?>
                <span class="text-gray-300 mx-2">|</span>
                <span style="color:#34D399;">👀 Reception Access</span>
            <?php endif; ?>
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- TOAST -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<script>
    // SCROLL TABLE
    function scrollTableById(tableId, direction) {
        var wrapper = document.getElementById(tableId);
        if (!wrapper) {
            console.log('⚠️ Table not found: ' + tableId);
            showScrollToast('⚠️ Table not found');
            return;
        }
        
        var scrollAmount = 300;
        
        if (direction === 'left') {
            wrapper.scrollLeft -= scrollAmount;
        } else {
            wrapper.scrollLeft += scrollAmount;
        }
        
        var msg = direction === 'left' ? '⬅️ Scrolled Left' : '➡️ Scrolled Right';
        showScrollToast(msg);
        
        console.log('📜 Scrolled ' + direction + ' on ' + tableId);
    }
    
    function showScrollToast(message) {
        var existing = document.querySelector('.scroll-toast');
        if (existing) {
            existing.remove();
        }
        
        var toast = document.createElement('div');
        toast.className = 'scroll-toast';
        toast.innerHTML = '<i class="fas fa-arrows-alt-h"></i> ' + message;
        document.body.appendChild(toast);
        
        setTimeout(function() {
            if (toast.parentNode) {
                toast.remove();
            }
        }, 1200);
    }
    
    // DARK MODE
    (function() {
        var htmlElement = document.documentElement;
        function syncDarkMode() {
            var isDark = localStorage.getItem('darkMode') === 'true';
            if (isDark) {
                htmlElement.setAttribute('data-theme', 'dark');
            } else {
                htmlElement.removeAttribute('data-theme');
            }
        }
        syncDarkMode();
        window.addEventListener('storage', function(e) {
            if (e.key === 'darkMode') syncDarkMode();
        });
    })();

    // SIDEBAR TOGGLE
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
        
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 1024) {
                if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                    sidebar.classList.remove('open');
                }
            }
        });
    }

    // ================================================================
    // ✅ LIVE TABLE SEARCH
    // ================================================================
    var tableSearch = document.getElementById('tableSearch');
    var searchClear = document.getElementById('searchClear');
    var searchResultsCount = document.getElementById('searchResultsCount');
    var billRows = document.querySelectorAll('.bill-row');
    var noSearchResults = document.getElementById('noSearchResults');
    var totalRows = billRows.length;
    
    var originalHTMLMap = new WeakMap();
    
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function escapeRegex(text) {
        return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }
    
    function highlightText(element, query) {
        if (!element) return;
        
        if (!originalHTMLMap.has(element)) {
            originalHTMLMap.set(element, element.innerHTML);
        }
        
        var originalHTML = originalHTMLMap.get(element);
        
        if (!query || query.trim() === '') {
            element.innerHTML = originalHTML;
            return;
        }
        
        var tempDiv = document.createElement('div');
        tempDiv.innerHTML = originalHTML;
        var textContent = tempDiv.textContent || tempDiv.innerText || '';
        
        if (!textContent.trim()) {
            element.innerHTML = originalHTML;
            return;
        }
        
        var escapedQuery = escapeRegex(query);
        var regex = new RegExp('(' + escapedQuery + ')', 'gi');
        
        if (!textContent.match(regex)) {
            element.innerHTML = originalHTML;
            return;
        }
        
        var escapedText = escapeHtml(textContent);
        var highlightedHTML = escapedText.replace(regex, '<mark class="search-highlight">$1</mark>');
        
        element.innerHTML = highlightedHTML;
    }
    
    function removeAllHighlights() {
        document.querySelectorAll('[data-searchable]').forEach(function(el) {
            if (originalHTMLMap.has(el)) {
                el.innerHTML = originalHTMLMap.get(el);
            }
        });
    }
    
    function performLiveSearch() {
        var query = tableSearch.value.toLowerCase().trim();
        
        if (query.length > 0) {
            searchClear.classList.add('visible');
        } else {
            searchClear.classList.remove('visible');
        }
        
        removeAllHighlights();
        
        var visibleCount = 0;
        
        billRows.forEach(function(row) {
            var searchData = row.getAttribute('data-search') || '';
            
            if (query === '' || searchData.includes(query)) {
                row.style.display = '';
                visibleCount++;
                
                if (query !== '') {
                    var searchableElements = row.querySelectorAll('[data-searchable]');
                    searchableElements.forEach(function(el) {
                        highlightText(el, query);
                    });
                }
            } else {
                row.style.display = 'none';
            }
        });
        
        if (visibleCount === 0 && query !== '') {
            noSearchResults.style.display = 'block';
        } else {
            noSearchResults.style.display = 'none';
        }
        
        if (query !== '') {
            searchResultsCount.textContent = visibleCount + ' of ' + totalRows + ' found';
            searchResultsCount.classList.add('visible');
        } else {
            searchResultsCount.classList.remove('visible');
        }
        
        console.log('🔍 Search: "' + query + '" → ' + visibleCount + ' results');
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
    function updateFooterTime() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var footerTimestamp = document.getElementById('footerTimestamp');
        if (footerTimestamp) {
            footerTimestamp.textContent = 'Last updated: ' + timeStr;
        }
    }
    updateFooterTime();
    setInterval(updateFooterTime, 1000);

    // TOAST
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        
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

    // MANUAL REFRESH
    function manualRefresh() {
        var btn = document.getElementById('refreshBtn');
        if (btn) {
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
            btn.disabled = true;
        }
        
        setTimeout(function() {
            window.location.reload();
        }, 1000);
    }

    // KEYBOARD SHORTCUTS
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 'ArrowLeft') {
            e.preventDefault();
            scrollTableById('paidBillsTableWrap', 'left');
        }
        if (e.ctrlKey && e.key === 'ArrowRight') {
            e.preventDefault();
            scrollTableById('paidBillsTableWrap', 'right');
        }
    });

    console.log('%c✅ Braick - Paid Bills V2 (PAYMENT-BASED)', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c✅ Each PAYMENT = one row (bill with multiple payments shows multiple rows)', 'font-size:13px; color:#34D399; font-weight:bold;');
    console.log('%c✅ Date filter uses payments.received_at', 'font-size:13px; color:#34D399; font-weight:bold;');
    console.log('%c✅ Shows who received each payment', 'font-size:13px; color:#34D399; font-weight:bold;');
    console.log('%c✅ REMOVED: View button (only Print remains)', 'font-size:13px; color:#34D399;');
    console.log('%c📋 Total Payments: <?= $total_bills ?>', 'font-size:13px; color:#64748B;');
</script>

</body>
</html>