<?php
// ================================================================
// FILE: frontend/pages/cashier/paid_bills.php
// CASHIER - PAID BILLS LIST (V4 - COUNTS ONLY, NO AMOUNTS)
// ================================================================
// ✅ REMOVED: Amounts kwenye summary cards
// ✅ REMOVED: Amount column kwenye table
// ✅ Counts only - Pharmacy vs Cashier kivyake
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

// GET FILTER PARAMETERS
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$premium_filter = isset($_GET['premium_filter']) ? $_GET['premium_filter'] : 'all';

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
// DATE FILTER
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

// ✅ PREMIUM/DISCOUNT FILTER
$premium_discount_condition = "";
if ($premium_filter === 'pharm_premium') {
    $premium_discount_condition = " AND b.pharmacy_premium > 0";
} elseif ($premium_filter === 'cashier_premium') {
    $premium_discount_condition = " AND b.cashier_premium > 0";
} elseif ($premium_filter === 'any_premium') {
    $premium_discount_condition = " AND (b.pharmacy_premium > 0 OR b.cashier_premium > 0)";
} elseif ($premium_filter === 'pharm_discount') {
    $premium_discount_condition = " AND b.pharmacy_discount > 0";
} elseif ($premium_filter === 'cashier_discount') {
    $premium_discount_condition = " AND b.cashier_discount > 0";
} elseif ($premium_filter === 'any_discount') {
    $premium_discount_condition = " AND (b.pharmacy_discount > 0 OR b.cashier_discount > 0)";
} elseif ($premium_filter === 'both_premium') {
    $premium_discount_condition = " AND b.pharmacy_premium > 0 AND b.cashier_premium > 0";
} elseif ($premium_filter === 'both_discount') {
    $premium_discount_condition = " AND b.pharmacy_discount > 0 AND b.cashier_discount > 0";
}

// ================================================================
// GET PAID PAYMENTS - PHARMACY vs CASHIER SEPARATED
// ================================================================
try {
    $all_paid = [];
    
    // 1. PAYMENTS FOR REGULAR BILLS
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
            b.pharmacy_premium,
            b.cashier_premium,
            b.pharmacy_discount,
            b.cashier_discount,
            b.discount_amount,
            b.total_discount,
            pat.full_name as patient_name,
            pat.patient_id as patient_code,
            pat.phone as patient_phone,
            u_recv.full_name as received_by_name,
            u_recv.role as received_by_role,
            u_created.full_name as cashier_name,
            'Regular' as bill_type,
            b.id as reference_id,
            b.bill_number as reference_number,
            (SELECT COUNT(*) FROM bill_items WHERE bill_id = b.id AND status != 'cancelled') as item_count
        FROM payments p
        INNER JOIN bills b ON p.bill_id = b.id
        LEFT JOIN patients pat ON b.patient_id = pat.id
        LEFT JOIN users u_recv ON p.received_by = u_recv.id
        LEFT JOIN users u_created ON b.created_by = u_created.id
        WHERE b.branch_id = ? 
        AND b.patient_id IS NOT NULL
        AND b.visit_id IS NOT NULL
        AND b.bill_number NOT LIKE 'BILL-OTC-%'
        $date_condition_bills
        $search_condition_bills
        $premium_discount_condition
    ";
    
    $stmt = $db->prepare($sql_payments);
    $stmt->execute($params_bills);
    $payment_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($payment_rows as $row) {
        // ✅ Fix pharmacy_discount (kama 0, chukua discount_amount)
        $pharm_disc = (float)($row['pharmacy_discount'] ?? 0);
        if ($pharm_disc == 0 && (float)($row['discount_amount'] ?? 0) > 0) {
            $pharm_disc = (float)$row['discount_amount'];
        }
        $row['pharmacy_discount'] = $pharm_disc;
        
        // ✅ Fix pharmacy_premium (kama 0, chukua premium_amount legacy)
        $pharm_prem = (float)($row['pharmacy_premium'] ?? 0);
        if ($pharm_prem == 0 && (float)($row['premium_amount'] ?? 0) > 0) {
            $pharm_prem = (float)$row['premium_amount'];
        }
        $row['pharmacy_premium'] = $pharm_prem;
        
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
            0 as pharmacy_premium,
            0 as cashier_premium,
            0 as pharmacy_discount,
            o.discount_amount as cashier_discount,
            o.discount_amount as discount_amount,
            o.discount_amount as total_discount,
            COALESCE(o.customer_name, 'Walk-in Customer') as patient_name,
            o.patient_id as patient_code,
            o.customer_phone as patient_phone,
            u_sold.full_name as received_by_name,
            u_sold.role as received_by_role,
            u_sold.full_name as cashier_name,
            'OTC' as bill_type,
            o.id as reference_id,
            o.sale_number as reference_number,
            (SELECT COUNT(*) FROM otc_sale_items WHERE sale_id = o.id) as item_count
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

// ================================================================
// ✅ CALCULATE SUMMARY COUNTS (NO AMOUNTS)
// ================================================================
$summary_regular_count = 0;
$summary_otc_count = 0;
$summary_pharm_premium_count = 0;
$summary_cashier_premium_count = 0;
$summary_pharm_discount_count = 0;
$summary_cashier_discount_count = 0;
$summary_both_premium_count = 0;
$summary_both_discount_count = 0;

foreach ($paid_bills as $b) {
    $pharm_disc = (float)($b['pharmacy_discount'] ?? 0);
    $cashier_disc = (float)($b['cashier_discount'] ?? 0);
    $pharm_prem = (float)($b['pharmacy_premium'] ?? 0);
    $cashier_prem = (float)($b['cashier_premium'] ?? 0);
    
    if (($b['bill_type'] ?? '') === 'Regular') $summary_regular_count++;
    if (($b['bill_type'] ?? '') === 'OTC') $summary_otc_count++;
    
    if ($pharm_prem > 0) $summary_pharm_premium_count++;
    if ($cashier_prem > 0) $summary_cashier_premium_count++;
    if ($pharm_disc > 0) $summary_pharm_discount_count++;
    if ($cashier_disc > 0) $summary_cashier_discount_count++;
    if ($pharm_prem > 0 && $cashier_prem > 0) $summary_both_premium_count++;
    if ($pharm_disc > 0 && $cashier_disc > 0) $summary_both_discount_count++;
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
            --success: #059669;
            --success-dark: #047857;
            --success-light: #34D399;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --pharm-color: #D97706;
            --pharm-bg: #FEF3C7;
            --cashier-discount-color: #2563EB;
            --cashier-discount-bg: #DBEAFE;
            --cashier-premium-color: #7C3AED;
            --cashier-premium-bg: #EDE9FE;
            --otc-color: #8B5CF6;
            --otc-bg: #EDE9FE;
            --gray-100: #F1F5F9;
            --gray-200: #E2E8F0;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow: 0 1px 3px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.07);
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --table-hover: #D1FAE5;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --table-hover: #1A3A2A;
            --otc-bg: #2A1A3A;
            --pharm-bg: #3D2E0A;
            --cashier-discount-bg: #1E3A5F;
            --cashier-premium-bg: #2A1A3A;
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
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .page-header .page-title {
            color: white;
            font-size: 1.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .page-header .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
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
            cursor: pointer;
            white-space: nowrap;
        }
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }
        
        /* ✅ 8 SUMMARY CARDS - COUNTS ONLY (NO AMOUNTS) */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 16px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .summary-card {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 12px 14px;
            border: 2px solid var(--border-color);
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow: hidden;
            min-height: 82px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .summary-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
        }
        
        .summary-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .summary-card .card-label {
            font-size: 0.55rem;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            display: block;
            line-height: 1.15;
        }
        
        .summary-card .card-value {
            font-size: 1.5rem;
            font-weight: 800;
            display: block;
            margin-top: 3px;
            font-family: monospace;
            letter-spacing: -0.02em;
            line-height: 1.1;
        }
        
        .summary-card .card-sub {
            font-size: 0.5rem;
            color: var(--text-secondary);
            display: block;
            margin-top: 2px;
            font-weight: 500;
        }
        
        .summary-card.total-card { border-color: var(--primary); }
        .summary-card.total-card::before { background: var(--primary); }
        .summary-card.total-card .card-value { color: var(--primary); }
        
        .summary-card.regular-card { border-color: var(--success); }
        .summary-card.regular-card::before { background: var(--success); }
        .summary-card.regular-card .card-value { color: var(--success); }
        
        .summary-card.otc-card { border-color: var(--otc-color); }
        .summary-card.otc-card::before { background: var(--otc-color); }
        .summary-card.otc-card .card-value { color: var(--otc-color); }
        
        .summary-card.pharm-discount-card { 
            border-color: var(--pharm-color);
            background: linear-gradient(135deg, var(--bg-card) 0%, rgba(217, 119, 6, 0.04) 100%);
        }
        .summary-card.pharm-discount-card::before { background: var(--pharm-color); }
        .summary-card.pharm-discount-card .card-value { color: var(--pharm-color); }
        .summary-card.pharm-discount-card .card-label { color: var(--pharm-color); }
        
        .summary-card.cashier-discount-card { 
            border-color: var(--cashier-discount-color);
            background: linear-gradient(135deg, var(--bg-card) 0%, rgba(37, 99, 235, 0.04) 100%);
        }
        .summary-card.cashier-discount-card::before { background: var(--cashier-discount-color); }
        .summary-card.cashier-discount-card .card-value { color: var(--cashier-discount-color); }
        .summary-card.cashier-discount-card .card-label { color: var(--cashier-discount-color); }
        
        .summary-card.pharm-premium-card { 
            border-color: #F59E0B;
            background: linear-gradient(135deg, var(--bg-card) 0%, rgba(245, 158, 11, 0.04) 100%);
        }
        .summary-card.pharm-premium-card::before { background: #F59E0B; }
        .summary-card.pharm-premium-card .card-value { color: #F59E0B; }
        .summary-card.pharm-premium-card .card-label { color: #F59E0B; }
        
        .summary-card.cashier-premium-card { 
            border-color: var(--cashier-premium-color);
            background: linear-gradient(135deg, var(--bg-card) 0%, rgba(124, 58, 237, 0.04) 100%);
        }
        .summary-card.cashier-premium-card::before { background: var(--cashier-premium-color); }
        .summary-card.cashier-premium-card .card-value { color: var(--cashier-premium-color); }
        .summary-card.cashier-premium-card .card-label { color: var(--cashier-premium-color); }
        
        .summary-card.both-card { border-color: var(--danger); }
        .summary-card.both-card::before { background: var(--danger); }
        .summary-card.both-card .card-value { color: var(--danger); }
        
        /* FILTER SECTION */
        .filter-section {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 14px 18px;
            border: 1px solid var(--border-color);
            margin-bottom: 16px;
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
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.68rem;
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
        
        .filter-btn i { margin-right: 3px; }
        
        .filter-btn.pharm { border-color: var(--pharm-color); color: var(--pharm-color); }
        .filter-btn.pharm:hover { background: var(--pharm-bg); }
        .filter-btn.pharm.active { background: var(--pharm-color); color: white; }
        
        .filter-btn.cashier-disc { border-color: var(--cashier-discount-color); color: var(--cashier-discount-color); }
        .filter-btn.cashier-disc:hover { background: var(--cashier-discount-bg); }
        .filter-btn.cashier-disc.active { background: var(--cashier-discount-color); color: white; }
        
        .filter-btn.cashier-prem { border-color: var(--cashier-premium-color); color: var(--cashier-premium-color); }
        .filter-btn.cashier-prem:hover { background: var(--cashier-premium-bg); }
        .filter-btn.cashier-prem.active { background: var(--cashier-premium-color); color: white; }
        
        .filter-btn.both { border-color: var(--danger); color: var(--danger); }
        .filter-btn.both:hover { background: var(--danger-bg); }
        .filter-btn.both.active { background: var(--danger); color: white; }
        
        .filter-group {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px;
        }
        
        .filter-group .filter-label {
            font-size: 0.68rem;
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
            margin-left: 8px;
        }
        
        .table-search-bar .search-wrapper .search-clear.visible { display: flex; }
        
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
        }
        
        [data-theme="dark"] mark.search-highlight {
            background: #FCD34D;
            color: #422006;
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
        
        /* ✅ TABLE - NO AMOUNT COLUMN */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
            min-width: 1200px;
        }
        
        .data-table thead th {
            text-align: left;
            padding: 8px 8px;
            font-weight: 700;
            font-size: 0.55rem;
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
            padding: 6px 8px;
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
            font-size: 0.72rem;
            color: var(--success);
            background: var(--success-bg);
            font-family: 'Courier New', monospace;
            width: 42px;
            min-width: 42px;
        }
        
        [data-theme="dark"] .row-number-cell {
            background: #1A3A2A;
            color: #34D399;
        }
        
        .data-table tbody tr:hover .row-number-cell {
            background: var(--success);
            color: white;
        }
        
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
        
        /* ✅ Column value badges */
        .col-pharm-disc {
            color: var(--pharm-color);
            font-weight: 700;
            font-family: monospace;
            font-size: 0.7rem;
            text-align: right;
            white-space: nowrap;
        }
        
        .col-cashier-disc {
            color: var(--cashier-discount-color);
            font-weight: 700;
            font-family: monospace;
            font-size: 0.7rem;
            text-align: right;
            white-space: nowrap;
        }
        
        .col-pharm-prem {
            color: #F59E0B;
            font-weight: 700;
            font-family: monospace;
            font-size: 0.7rem;
            text-align: right;
            white-space: nowrap;
        }
        
        .col-cashier-prem {
            color: var(--cashier-premium-color);
            font-weight: 700;
            font-family: monospace;
            font-size: 0.7rem;
            text-align: right;
            white-space: nowrap;
        }
        
        .col-dash {
            color: var(--text-secondary);
            font-size: 0.65rem;
            text-align: right;
        }
        
        .badge-yes {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.55rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .badge-yes.pharm {
            background: var(--pharm-bg);
            color: var(--pharm-color);
            border: 1px solid var(--pharm-color);
        }
        
        .badge-yes.cashier-disc {
            background: var(--cashier-discount-bg);
            color: var(--cashier-discount-color);
            border: 1px solid var(--cashier-discount-color);
        }
        
        .badge-yes.cashier-prem {
            background: var(--cashier-premium-bg);
            color: var(--cashier-premium-color);
            border: 1px solid var(--cashier-premium-color);
        }
        
        .btn-print-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            padding: 5px 10px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.6rem;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            text-decoration: none;
            background: #059669;
            color: #FFFFFF;
            white-space: nowrap;
            width: 100%;
            min-width: 60px;
        }
        
        .btn-print-action:hover {
            background: var(--success-dark);
            transform: translateY(-2px);
            color: white;
        }
        
        .btn-print-action.otc { background: #7C3AED; }
        .btn-print-action.otc:hover { background: #6D28D9; }
        
        .received-by-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.6rem;
            font-weight: 600;
            background: #E8F0FE;
            color: #0B5ED7;
            white-space: nowrap;
        }
        
        .received-by-badge.cashier { background: #FEF3C7; color: #D97706; }
        .received-by-badge.reception { background: #DBEAFE; color: #1E40AF; }
        .received-by-badge.admin { background: #FCE7F3; color: #BE185D; }
        .received-by-badge.pharmacy { background: #D1FAE5; color: #059669; }
        
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        .footer .footer-brand { color: var(--success); font-weight: 600; }
        
        /* RESPONSIVE */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .summary-cards { grid-template-columns: repeat(2, 1fr); }
            .card { padding: 14px 16px; }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .summary-cards { grid-template-columns: 1fr 1fr; }
            .data-table { font-size: 0.58rem; min-width: 1000px; }
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
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-file-invoice"></i>
                All payments received in <strong><?= htmlspecialchars($user_branch_name) ?></strong>
                <span class="header-badge">
                    <i class="fas fa-receipt"></i>
                    <?= $total_bills ?> Payments
                </span>
                <?php if ($premium_filter !== 'all'): ?>
                    <span class="header-badge" style="background:rgba(217,119,6,0.3);color:#FCD34D;">
                        <i class="fas fa-filter"></i>
                        <?= strtoupper(str_replace('_', ' ', $premium_filter)) ?>
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <button onclick="manualRefresh()" class="btn-outline-light" id="refreshBtn">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="p-4 rounded-xl mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>" style="max-width:1200px;margin:0 auto 16px;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- ✅ 8 SUMMARY CARDS - ROW 1 (COUNTS ONLY, NO AMOUNTS) -->
    <div class="summary-cards">
        <div class="summary-card total-card">
            <span class="card-label">📋 Total</span>
            <span class="card-value"><?= $total_bills ?></span>
            <span class="card-sub">All records</span>
        </div>
        
        <div class="summary-card regular-card">
            <span class="card-label">✅ Regular</span>
            <span class="card-value"><?= $summary_regular_count ?></span>
            <span class="card-sub">Bill payments</span>
        </div>
        
        <div class="summary-card otc-card">
            <span class="card-label">🛒 OTC Sales</span>
            <span class="card-value"><?= $summary_otc_count ?></span>
            <span class="card-sub">Over-the-counter</span>
        </div>
        
        <div class="summary-card both-card">
            <span class="card-label">⚠️ Both Prem</span>
            <span class="card-value"><?= $summary_both_premium_count ?></span>
            <span class="card-sub">Pharm + Cashier</span>
        </div>
    </div>

    <!-- ✅ 8 SUMMARY CARDS - ROW 2 (COUNTS ONLY, NO AMOUNTS) -->
    <div class="summary-cards">
        <div class="summary-card pharm-discount-card">
            <span class="card-label">🏷️ Pharm Discount</span>
            <span class="card-value"><?= $summary_pharm_discount_count ?></span>
            <span class="card-sub">Bills with Pharm Disc</span>
        </div>
        
        <div class="summary-card cashier-discount-card">
            <span class="card-label">🏷️ Cashier Discount</span>
            <span class="card-value"><?= $summary_cashier_discount_count ?></span>
            <span class="card-sub">Bills with Cashier Disc</span>
        </div>
        
        <div class="summary-card pharm-premium-card">
            <span class="card-label">👑 Pharm Premium</span>
            <span class="card-value"><?= $summary_pharm_premium_count ?></span>
            <span class="card-sub">Bills with Pharm Prem</span>
        </div>
        
        <div class="summary-card cashier-premium-card">
            <span class="card-label">👑 Cashier Premium</span>
            <span class="card-value"><?= $summary_cashier_premium_count ?></span>
            <span class="card-sub">Bills with Cashier Prem</span>
        </div>
    </div>

    <!-- FILTERS -->
    <div class="filter-section">
        <!-- Date Filters -->
        <div class="filter-group" style="margin-bottom:8px;">
            <span class="filter-label"><i class="fas fa-calendar-alt"></i> Date:</span>
            
            <a href="?filter=all&search=<?= urlencode($search) ?>&premium_filter=<?= urlencode($premium_filter) ?>" class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">
                <i class="fas fa-globe"></i> All
            </a>
            <a href="?filter=today&search=<?= urlencode($search) ?>&premium_filter=<?= urlencode($premium_filter) ?>" class="filter-btn <?= $filter === 'today' ? 'active' : '' ?>">
                <i class="fas fa-calendar-day"></i> Today
            </a>
            <a href="?filter=week&search=<?= urlencode($search) ?>&premium_filter=<?= urlencode($premium_filter) ?>" class="filter-btn <?= $filter === 'week' ? 'active' : '' ?>">
                <i class="fas fa-calendar-week"></i> 7D
            </a>
            <a href="?filter=month&search=<?= urlencode($search) ?>&premium_filter=<?= urlencode($premium_filter) ?>" class="filter-btn <?= $filter === 'month' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i> 1M
            </a>
            <a href="?filter=3months&search=<?= urlencode($search) ?>&premium_filter=<?= urlencode($premium_filter) ?>" class="filter-btn <?= $filter === '3months' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i> 3M
            </a>
            <a href="?filter=6months&search=<?= urlencode($search) ?>&premium_filter=<?= urlencode($premium_filter) ?>" class="filter-btn <?= $filter === '6months' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i> 6M
            </a>
            <a href="?filter=year&search=<?= urlencode($search) ?>&premium_filter=<?= urlencode($premium_filter) ?>" class="filter-btn <?= $filter === 'year' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i> 1Y
            </a>
        </div>
        
        <!-- Premium/Discount Filter -->
        <div class="filter-group" style="margin-bottom:8px;border-top:1px solid var(--border-color);padding-top:8px;">
            <span class="filter-label"><i class="fas fa-crown"></i> Premium/Disc:</span>
            
            <a href="?filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>&premium_filter=all" 
               class="filter-btn <?= $premium_filter === 'all' ? 'active' : '' ?>">
                <i class="fas fa-globe"></i> All
            </a>
            
            <a href="?filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>&premium_filter=pharm_premium" 
               class="filter-btn pharm <?= $premium_filter === 'pharm_premium' ? 'active' : '' ?>">
                <i class="fas fa-pills"></i> Pharm Prem
            </a>
            
            <a href="?filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>&premium_filter=cashier_premium" 
               class="filter-btn cashier-prem <?= $premium_filter === 'cashier_premium' ? 'active' : '' ?>">
                <i class="fas fa-cash-register"></i> Cashier Prem
            </a>
            
            <a href="?filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>&premium_filter=any_premium" 
               class="filter-btn <?= $premium_filter === 'any_premium' ? 'active' : '' ?>">
                <i class="fas fa-crown"></i> Any Prem
            </a>
            
            <a href="?filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>&premium_filter=both_premium" 
               class="filter-btn both <?= $premium_filter === 'both_premium' ? 'active' : '' ?>">
                <i class="fas fa-exclamation-triangle"></i> Both Prem
            </a>
            
            <a href="?filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>&premium_filter=pharm_discount" 
               class="filter-btn pharm <?= $premium_filter === 'pharm_discount' ? 'active' : '' ?>">
                <i class="fas fa-tag"></i> Pharm Disc
            </a>
            
            <a href="?filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>&premium_filter=cashier_discount" 
               class="filter-btn cashier-disc <?= $premium_filter === 'cashier_discount' ? 'active' : '' ?>">
                <i class="fas fa-tag"></i> Cashier Disc
            </a>
            
            <a href="?filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>&premium_filter=any_discount" 
               class="filter-btn cashier-disc <?= $premium_filter === 'any_discount' ? 'active' : '' ?>">
                <i class="fas fa-tag"></i> Any Disc
            </a>
        </div>
        
        <!-- Custom Date -->
        <form method="GET" action="" class="filter-group" style="border-top:1px solid var(--border-color);padding-top:8px;margin-top:4px;">
            <input type="hidden" name="filter" value="custom">
            <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
            <input type="hidden" name="premium_filter" value="<?= htmlspecialchars($premium_filter) ?>">
            
            <span class="filter-label"><i class="fas fa-calendar-plus"></i> Custom:</span>
            
            <div class="date-picker-group">
                <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>">
                <span style="color:var(--text-secondary);font-size:0.7rem;">to</span>
                <input type="date" name="end_date" class="form-control" value="<?= $end_date ?>">
                <button type="submit" class="btn-apply">
                    <i class="fas fa-check"></i> Apply
                </button>
                <?php if ($filter === 'custom' && !empty($start_date) && !empty($end_date)): ?>
                    <a href="?filter=all&search=<?= urlencode($search) ?>&premium_filter=<?= urlencode($premium_filter) ?>" class="btn-apply" style="background:#DC2626;">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- PAID BILLS TABLE -->
    <div class="card">
        
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list" style="color:var(--success);"></i> Payments & OTC Sales
                <span style="font-size:0.8rem;font-weight:400;color:var(--text-secondary);">(<?= $total_bills ?> records)</span>
            </h3>
            
            <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                <span style="font-size:0.7rem;color:var(--text-secondary);">
                    <i class="fas fa-clock"></i> <?= date('h:i:s A') ?>
                </span>
                
                <div class="table-header-actions">
                    <button class="table-scroll-btn" onclick="scrollTableById('paidBillsTableWrap', 'left')">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button class="table-scroll-btn" onclick="scrollTableById('paidBillsTableWrap', 'right')">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- SEARCH BAR -->
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
                        <th style="min-width:40px;text-align:center;">#</th>
                        <th style="min-width:110px;">Receipt #</th>
                        <th style="min-width:90px;">Bill #</th>
                        <th style="min-width:55px;text-align:center;">Type</th>
                        <th style="min-width:140px;">Patient</th>
                        <th style="min-width:55px;text-align:center;">Items</th>
                        <th style="min-width:120px;">Received By</th>
                        <th style="min-width:85px;text-align:center;">Pharm Disc</th>
                        <th style="min-width:85px;text-align:center;">Cashier Disc</th>
                        <th style="min-width:85px;text-align:center;">Pharm Prem</th>
                        <th style="min-width:85px;text-align:center;">Cashier Prem</th>
                        <th style="min-width:80px;text-align:center;">Date</th>
                        <th style="min-width:70px;text-align:center;">Print</th>
                    </tr>
                </thead>
                <tbody id="paidBillsTableBody">
                    <?php if (is_array($paid_bills) && count($paid_bills) > 0): ?>
                        <?php 
                        $row_number = 1;
                        foreach ($paid_bills as $bill): 
                            $is_otc = ($bill['bill_type'] ?? '') === 'OTC';
                            $received_by_name = $bill['received_by_name'] ?? 'N/A';
                            $received_by_role = strtolower($bill['received_by_role'] ?? 'user');
                            
                            // ✅ PHARMACY values
                            $pharm_disc = (float)($bill['pharmacy_discount'] ?? 0);
                            $pharm_prem = (float)($bill['pharmacy_premium'] ?? 0);
                            
                            // ✅ CASHIER values
                            $cashier_disc = (float)($bill['cashier_discount'] ?? 0);
                            $cashier_prem = (float)($bill['cashier_premium'] ?? 0);
                            
                            // ✅ Booleans
                            $has_pharm_disc = ($pharm_disc > 0);
                            $has_cashier_disc = ($cashier_disc > 0);
                            $has_pharm_prem = ($pharm_prem > 0);
                            $has_cashier_prem = ($cashier_prem > 0);
                            $has_both_prem = ($has_pharm_prem && $has_cashier_prem);
                            
                            $search_data = strtolower(
                                ($bill['receipt_number'] ?? '') . ' ' .
                                ($bill['bill_number'] ?? '') . ' ' .
                                ($bill['patient_name'] ?? '') . ' ' .
                                ($bill['patient_phone'] ?? '') . ' ' .
                                ($bill['received_by_name'] ?? '') . ' ' .
                                ($bill['cashier_name'] ?? '') . ' ' .
                                ($bill['bill_type'] ?? '')
                            );
                        ?>
                            <tr class="bill-row" data-search="<?= htmlspecialchars($search_data) ?>">
                                <td class="row-number-cell">
                                    <?= $row_number++ ?>
                                    <?php if ($has_both_prem): ?>
                                        <div style="font-size:0.5rem;color:var(--danger);margin-top:2px;">⚠️</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="font-size:0.6rem;color:var(--success);font-weight:800;background:var(--success-bg);padding:3px 6px;border-radius:5px;display:inline-block;font-family:monospace;" data-searchable>
                                        <?= htmlspecialchars($bill['receipt_number'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size:0.6rem;font-weight:700;font-family:monospace;<?= $is_otc ? 'color:#7C3AED;' : 'color:#0B5ED7;' ?>" data-searchable>
                                        <?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?>
                                    </span>
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
                                    <div style="font-weight:500;font-size:0.75rem;" data-searchable><?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?></div>
                                    <div style="font-size:0.55rem;color:var(--text-secondary);" data-searchable>
                                        <?= htmlspecialchars($bill['patient_phone'] ?? 'No phone') ?>
                                    </div>
                                </td>
                                <td style="text-align:center;">
                                    <span style="font-weight:600;font-size:0.75rem;"><?= $bill['item_count'] ?? 0 ?></span>
                                </td>
                                <td>
                                    <span class="received-by-badge <?= htmlspecialchars($received_by_role) ?>" data-searchable>
                                        <i class="fas fa-user"></i>
                                        <?= htmlspecialchars($received_by_name) ?>
                                    </span>
                                    <div style="font-size:0.5rem;color:var(--text-secondary);margin-top:2px;text-transform:uppercase;font-weight:600;">
                                        <?= htmlspecialchars(strtoupper($received_by_role)) ?>
                                    </div>
                                </td>
                                
                                <!-- ✅ PHARMACY DISCOUNT - YES/NO -->
                                <td style="text-align:center;">
                                    <?php if ($has_pharm_disc): ?>
                                        <span class="badge-yes pharm">
                                            <i class="fas fa-check"></i> YES
                                        </span>
                                    <?php else: ?>
                                        <span class="col-dash">—</span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- ✅ CASHIER DISCOUNT - YES/NO -->
                                <td style="text-align:center;">
                                    <?php if ($has_cashier_disc): ?>
                                        <span class="badge-yes cashier-disc">
                                            <i class="fas fa-check"></i> YES
                                        </span>
                                    <?php else: ?>
                                        <span class="col-dash">—</span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- ✅ PHARMACY PREMIUM - YES/NO -->
                                <td style="text-align:center;">
                                    <?php if ($has_pharm_prem): ?>
                                        <span class="badge-yes pharm">
                                            <i class="fas fa-crown"></i> YES
                                        </span>
                                    <?php else: ?>
                                        <span class="col-dash">—</span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- ✅ CASHIER PREMIUM - YES/NO -->
                                <td style="text-align:center;">
                                    <?php if ($has_cashier_prem): ?>
                                        <span class="badge-yes cashier-prem">
                                            <i class="fas fa-crown"></i> YES
                                        </span>
                                    <?php else: ?>
                                        <span class="col-dash">—</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td style="text-align:center;">
                                    <div style="font-size:0.65rem;">
                                        <?= isset($bill['paid_date']) ? date('d/m/y', strtotime($bill['paid_date'])) : 'N/A' ?>
                                    </div>
                                    <div style="font-size:0.5rem;color:var(--text-secondary);">
                                        <?= isset($bill['paid_date']) ? date('h:i A', strtotime($bill['paid_date'])) : '' ?>
                                    </div>
                                </td>
                                <td style="text-align:center;">
                                    <?php if ($is_otc): ?>
                                        <a href="print_receipt.php?type=otc&sale_id=<?= $bill['reference_id'] ?>&print=1" 
                                           class="btn-print-action otc" 
                                           target="_blank">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    <?php else: ?>
                                        <a href="print_receipt.php?payment_id=<?= $bill['payment_id'] ?>&bill_id=<?= $bill['bill_id'] ?>&print=1" 
                                           class="btn-print-action" 
                                           target="_blank">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="13" style="text-align:center;padding:30px 20px;color:var(--text-secondary);">
                                <i class="fas fa-check-circle" style="font-size:2.5rem;display:block;margin-bottom:12px;color:var(--success);"></i>
                                <p style="font-size:1rem;font-weight:600;">No payments found</p>
                                <p style="font-size:0.8rem;margin-top:4px;">
                                    <?php if ($premium_filter !== 'all'): ?>
                                        No records match the selected filter
                                    <?php elseif ($filter !== 'all'): ?>
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
            <span>|</span> Paid Bills (Counts Only)
            <span>|</span>
            <span>👤 <?= htmlspecialchars($user_full_name) ?></span>
            <span>|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
            <span>|</span>
            &copy; <?= date('Y') ?>
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
    function scrollTableById(tableId, direction) {
        var wrapper = document.getElementById(tableId);
        if (!wrapper) return;
        
        var scrollAmount = 300;
        wrapper.scrollLeft += (direction === 'left' ? -scrollAmount : scrollAmount);
        
        showScrollToast(direction === 'left' ? '⬅️ Scrolled Left' : '➡️ Scrolled Right');
    }
    
    function showScrollToast(message) {
        var existing = document.querySelector('.scroll-toast');
        if (existing) existing.remove();
        
        var toast = document.createElement('div');
        toast.className = 'scroll-toast';
        toast.innerHTML = '<i class="fas fa-arrows-alt-h"></i> ' + message;
        toast.style.cssText = 'position:fixed;bottom:30px;right:30px;background:#059669;color:white;padding:10px 20px;border-radius:24px;font-size:0.8rem;font-weight:600;z-index:9999;box-shadow:0 6px 20px rgba(5,150,105,0.4);display:flex;align-items:center;gap:8px;';
        document.body.appendChild(toast);
        
        setTimeout(function() {
            if (toast.parentNode) toast.remove();
        }, 1200);
    }
    
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
    })();

    var tableSearch = document.getElementById('tableSearch');
    var searchClear = document.getElementById('searchClear');
    var searchResultsCount = document.getElementById('searchResultsCount');
    var billRows = document.querySelectorAll('.bill-row');
    var noSearchResults = document.getElementById('noSearchResults');
    var totalRows = billRows.length;
    
    function performLiveSearch() {
        var query = tableSearch.value.toLowerCase().trim();
        
        if (query.length > 0) {
            searchClear.classList.add('visible');
        } else {
            searchClear.classList.remove('visible');
        }
        
        var visibleCount = 0;
        
        billRows.forEach(function(row) {
            var searchData = row.getAttribute('data-search') || '';
            
            if (query === '' || searchData.includes(query)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        noSearchResults.style.display = (visibleCount === 0 && query !== '') ? 'block' : 'none';
        
        if (query !== '') {
            searchResultsCount.textContent = visibleCount + ' of ' + totalRows + ' found';
            searchResultsCount.classList.add('visible');
        } else {
            searchResultsCount.classList.remove('visible');
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

    function updateFooterTime() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var footerTimestamp = document.getElementById('footerTimestamp');
        if (footerTimestamp) footerTimestamp.textContent = 'Last updated: ' + timeStr;
    }
    updateFooterTime();
    setInterval(updateFooterTime, 1000);

    function manualRefresh() {
        var btn = document.getElementById('refreshBtn');
        if (btn) {
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
            btn.disabled = true;
        }
        setTimeout(function() { window.location.reload(); }, 800);
    }

    console.log('%c✅ Braick - Paid Bills V4 (COUNTS ONLY)', 'font-size:16px; font-weight:bold; color:#059669;');
    console.log('%c✅ NO AMOUNTS - Counts only', 'font-size:12px; color:#34D399;');
    console.log('%c📋 Total Payments: <?= $total_bills ?>', 'font-size:12px; color:#64748B;');
</script>

</body>
</html>