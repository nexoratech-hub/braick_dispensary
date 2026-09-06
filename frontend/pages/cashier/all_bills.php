<?php
// ================================================================
// FILE: frontend/pages/cashier/all_bills.php
// CASHIER - ALL BILLS (FULL LIST)
// SHOWS: ALL bills including pending, partial, paid, cancelled
// INCLUDES: Regular bills (with visit_id) AND OTC sales
// FIXED: 5 Bill Summary Cards, Discount handling, PDF export
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
$is_admin = ($user_role === 'admin');

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$message = '';
$message_type = '';
$currency = 'TSh';

// ================================================================
// GET ADMIN CONTACT NUMBERS
// ================================================================
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

// ================================================================
// GET BRANCH PHONE
// ================================================================
$branch_phone = '';
try {
    $stmt = $db->prepare("SELECT phone FROM branches WHERE id = ?");
    $stmt->execute([$user_branch_id]);
    $branch_phone = $stmt->fetchColumn();
} catch (Exception $e) {
    $branch_phone = '';
}

// ================================================================
// GET FILTER PARAMETERS
// ================================================================
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

$all_bills = [];
$total_amount = 0;
$total_paid = 0;
$total_balance = 0;
$total_discount = 0;
$total_bills = 0;
$total_subtotal = 0;

// ================================================================
// GET SYSTEM SETTINGS
// ================================================================
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
// BUILD DATE FILTER
// ================================================================
$date_condition = "";
$params = [$user_branch_id];

switch ($filter) {
    case 'today':
        $date_condition = "AND DATE(b.updated_at) = CURDATE()";
        break;
    case 'week':
        $date_condition = "AND b.updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case 'month':
        $date_condition = "AND b.updated_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        break;
    case '3months':
        $date_condition = "AND b.updated_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
        break;
    case '6months':
        $date_condition = "AND b.updated_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
        break;
    case 'year':
        $date_condition = "AND b.updated_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        break;
    case 'custom':
        if (!empty($start_date) && !empty($end_date)) {
            $date_condition = "AND DATE(b.updated_at) BETWEEN ? AND ?";
            $params[] = $start_date;
            $params[] = $end_date;
        }
        break;
    default:
        $date_condition = "";
        break;
}

// ================================================================
// BUILD SEARCH CONDITION
// ================================================================
$search_condition = "";
if (!empty($search)) {
    $search_condition = "AND (p.full_name LIKE ? OR b.bill_number LIKE ? OR p.phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// ================================================================
// BUILD STATUS FILTER
// ================================================================
$status_condition = "";
if ($status_filter !== 'all') {
    $status_condition = "AND b.status = ?";
    $params[] = $status_filter;
}

// ================================================================
// GET ALL BILLS - FROM bills TABLE
// ================================================================
try {
    $sql = "
        SELECT 
            b.*,
            p.full_name as patient_name,
            p.patient_id as patient_id_number,
            p.phone,
            p.gender,
            u.full_name as created_by_name,
            v.visit_number,
            v.visit_type,
            'regular' as bill_type,
            (SELECT COUNT(*) FROM bill_items WHERE bill_id = b.id AND status != 'cancelled') as item_count,
            (SELECT COUNT(*) FROM payments WHERE bill_id = b.id) as payment_count,
            (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE bill_id = b.id) as total_paid_amount,
            (SELECT COALESCE(SUM(discount_amount), 0) FROM bill_items WHERE bill_id = b.id) as bill_discount
        FROM bills b
        LEFT JOIN patients p ON b.patient_id = p.id
        LEFT JOIN users u ON b.created_by = u.id
        LEFT JOIN visits v ON b.visit_id = v.id
        WHERE b.branch_id = ? 
        AND b.visit_id IS NOT NULL
        AND b.visit_id > 0
        $date_condition
        $search_condition
        $status_condition
        ORDER BY b.updated_at DESC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $regular_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ================================================================
    // GET OTC SALES
    // ================================================================
    $otc_params = [$user_branch_id];
    $otc_date_condition = str_replace('b.updated_at', 'o.updated_at', $date_condition);
    $otc_search_condition = "";
    
    if (!empty($search)) {
        $otc_search_condition = "AND (o.customer_name LIKE ? OR o.sale_number LIKE ? OR o.customer_phone LIKE ?)";
        $otc_params[] = "%$search%";
        $otc_params[] = "%$search%";
        $otc_params[] = "%$search%";
    }
    
    $otc_status_condition = "";
    if ($status_filter !== 'all') {
        $otc_status_condition = "AND o.payment_status = ?";
        $otc_params[] = $status_filter;
    }
    
    $sql_otc = "
        SELECT 
            o.id,
            o.sale_number as bill_number,
            o.customer_name as patient_name,
            o.patient_id as patient_id_number,
            o.customer_phone as phone,
            NULL as gender,
            o.total_amount,
            o.total_amount as paid_amount,
            0 as balance,
            o.payment_status as status,
            o.payment_method,
            o.created_at,
            o.updated_at,
            o.sold_by,
            u.full_name as created_by_name,
            'OTC' as bill_type,
            (SELECT COUNT(*) FROM otc_sale_items WHERE sale_id = o.id) as item_count,
            0 as payment_count,
            o.total_amount as total_paid_amount,
            0 as bill_discount,
            o.id as otc_id
        FROM otc_sales o
        LEFT JOIN users u ON o.sold_by = u.id
        WHERE o.branch_id = ? 
        $otc_date_condition
        $otc_search_condition
        $otc_status_condition
        ORDER BY o.updated_at DESC
    ";
    
    $stmt = $db->prepare($sql_otc);
    $stmt->execute($otc_params);
    $otc_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ================================================================
    // COMBINE ALL BILLS
    // ================================================================
    $all_bills = array_merge($regular_bills, $otc_bills);
    
    // Sort by updated_at (newest first)
    usort($all_bills, function($a, $b) {
        $time_a = strtotime($a['updated_at'] ?? $a['created_at'] ?? 'now');
        $time_b = strtotime($b['updated_at'] ?? $b['created_at'] ?? 'now');
        return $time_b - $time_a;
    });
    
    // ================================================================
    // CALCULATE TOTALS
    // ================================================================
    $total_bills = count($all_bills);
    $total_amount = 0;
    $total_paid = 0;
    $total_balance = 0;
    $total_discount = 0;
    $total_subtotal = 0;
    
    foreach ($all_bills as $bill) {
        $bill_total = (float)($bill['total_amount'] ?? 0);
        $bill_paid = (float)($bill['paid_amount'] ?? $bill['total_paid_amount'] ?? 0);
        $bill_balance = (float)($bill['balance'] ?? 0);
        $bill_discount = (float)($bill['bill_discount'] ?? 0);
        $bill_subtotal = (float)($bill['subtotal'] ?? $bill_total);
        
        $total_amount += $bill_total;
        $total_paid += $bill_paid;
        $total_balance += $bill_balance;
        $total_discount += $bill_discount;
        $total_subtotal += $bill_subtotal;
    }
    
    $amount_after_discount = max(0, $total_amount - $total_discount);
    
} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $all_bills = [];
    $total_amount = 0;
    $total_paid = 0;
    $total_balance = 0;
    $total_discount = 0;
    $total_bills = 0;
    $total_subtotal = 0;
    $amount_after_discount = 0;
    error_log("All bills error: " . $e->getMessage());
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
    <title>All Bills - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
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
            --danger-light: #F87171;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --purple: #7C3AED;
            --purple-bg: #EDE9FE;
            --cyan: #0891B2;
            --cyan-bg: #CFFAFE;
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
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --table-stripe: #E8F0FE;
            --table-hover: #D1FAE5;
            --otc-color: #8B5CF6;
            --otc-bg: #EDE9FE;
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
            --table-stripe: #1E293B;
            --table-hover: #1A3A2A;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.3);
            --otc-bg: #2A1A3A;
            --primary-bg: #1E3A5F;
            --success-bg: #1A3A2A;
            --danger-bg: #3A1A1A;
            --warning-bg: #3D2E0A;
            --purple-bg: #2A1A3A;
            --cyan-bg: #0A2A3A;
        }

        [data-theme="dark"] .bg-white { background-color: #1E293B !important; }
        [data-theme="dark"] .text-gray-700 { color: #CBD5E1 !important; }
        [data-theme="dark"] .text-gray-800 { color: #E2E8F0 !important; }
        [data-theme="dark"] .text-gray-900 { color: #F1F5F9 !important; }
        [data-theme="dark"] .border-gray-200 { border-color: #334155 !important; }
        [data-theme="dark"] .bg-gray-50 { background-color: #1E293B !important; }
        [data-theme="dark"] .bg-gray-100 { background-color: #2D3748 !important; }
        [data-theme="dark"] .shadow { box-shadow: 0 1px 3px rgba(0,0,0,0.3) !important; }
        [data-theme="dark"] .shadow-md { box-shadow: 0 4px 12px rgba(0,0,0,0.3) !important; }
        [data-theme="dark"] .shadow-lg { box-shadow: 0 10px 25px rgba(0,0,0,0.4) !important; }
        [data-theme="dark"] .filter-btn { border-color: #334155; color: #94A3B8; }
        [data-theme="dark"] .filter-btn:hover { border-color: #34D399; color: #34D399; background: rgba(5, 150, 105, 0.15); }
        [data-theme="dark"] .filter-btn.active { background: #059669; color: white; border-color: #059669; }
        [data-theme="dark"] .filter-btn.active:hover { background: #047857; border-color: #047857; }
        [data-theme="dark"] .date-picker-group .form-control { background: #1E293B; color: #F1F5F9; border-color: #334155; }
        [data-theme="dark"] .date-picker-group .form-control:focus { border-color: #34D399; box-shadow: 0 0 0 3px rgba(52, 211, 153, 0.1); }
        [data-theme="dark"] .data-table tbody tr:hover td { background: #1A3A2A; }
        [data-theme="dark"] .data-table td { border-bottom-color: #334155; }
        [data-theme="dark"] .stat-card { background: #1E293B; border-color: #334155; }
        [data-theme="dark"] .stat-card:hover { border-color: #059669; }
        [data-theme="dark"] .card { background: #1E293B; border-color: #334155; }
        [data-theme="dark"] .card:hover { border-color: #059669; }
        [data-theme="dark"] .page-header { background: linear-gradient(135deg, #059669, #047857) !important; }
        [data-theme="dark"] .footer { border-top-color: #334155; }
        [data-theme="dark"] .toast-custom.success { background: #059669; }
        [data-theme="dark"] .toast-custom.error { background: #DC2626; }
        [data-theme="dark"] .header-badge { background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.1); }
        [data-theme="dark"] .status-badge.pending { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .status-badge.partial { background: #1E3A5F; color: #6EA8FE; }
        [data-theme="dark"] .status-badge.paid { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .status-badge.cancelled { background: #3A1A1A; color: #F87171; }
        [data-theme="dark"] .status-badge.otc { background: #2A1A3A; color: #A78BFA; }
        [data-theme="dark"] .text-gray-400 { color: #94A3B8 !important; }
        [data-theme="dark"] .text-gray-500 { color: #94A3B8 !important; }
        [data-theme="dark"] .text-gray-600 { color: #94A3B8 !important; }
        [data-theme="dark"] .font-mono.text-gray-700 { color: #CBD5E1 !important; }
        [data-theme="dark"] .font-semibold.text-gray-800 { color: #E2E8F0 !important; }
        [data-theme="dark"] .card-title { color: #F1F5F9 !important; }
        [data-theme="dark"] .footer .footer-brand { color: #34D399; }
        [data-theme="dark"] .footer .text-gray-300 { color: #475569 !important; }
        [data-theme="dark"] .toast-custom { box-shadow: 0 20px 25px rgba(0,0,0,0.4); }
        [data-theme="dark"] .role-badge-display { background: #1E3A5F; color: #6EA8FE; }
        [data-theme="dark"] .branch-badge-display { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .otc-badge { background: #2A1A3A; color: #A78BFA; border-color: rgba(139,92,246,0.2); }
        [data-theme="dark"] .btn-view { background: #1E3A5F; color: #6EA8FE; }
        [data-theme="dark"] .btn-view:hover { background: #0A4CA8; color: white; }
        [data-theme="dark"] .btn-process { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .btn-process:hover { background: #047857; color: white; }
        [data-theme="dark"] .btn-print { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .btn-print:hover { background: #047857; color: white; }
        
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
        }
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        /* ================================================================ */
        /* 5 BILL SUMMARY CARDS */
        /* ================================================================ */
        .bill-summary-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 16px;
            margin-bottom: 20px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .bill-summary-card {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .bill-summary-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            border-radius: 14px 14px 0 0;
        }
        .bill-summary-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
        .bill-summary-card .bill-summary-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
            background: var(--gray-100);
            color: var(--gray-600);
        }
        .bill-summary-card .bill-summary-content { flex: 1; }
        .bill-summary-card .bill-summary-label {
            font-size: 0.65rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: block;
        }
        .bill-summary-card .bill-summary-value {
            font-size: 1.2rem;
            font-weight: 700;
            display: block;
            margin-top: 2px;
            color: var(--text-primary);
        }
        
        /* Card 1: Total Amount */
        .bill-summary-card.total-card { border-color: var(--primary); }
        .bill-summary-card.total-card::before { background: var(--primary); }
        .bill-summary-card.total-card .bill-summary-icon { background: var(--primary-bg); color: var(--primary); }
        .bill-summary-card.total-card .bill-summary-value { color: var(--primary); }
        
        /* Card 2: Discount */
        .bill-summary-card.discount-card { border-color: var(--purple); }
        .bill-summary-card.discount-card::before { background: var(--purple); }
        .bill-summary-card.discount-card .bill-summary-icon { background: var(--purple-bg); color: var(--purple); }
        .bill-summary-card.discount-card .bill-summary-value { color: var(--purple); }
        
        /* Card 3: Amount After Discount */
        .bill-summary-card.after-discount-card { border-color: var(--cyan); }
        .bill-summary-card.after-discount-card::before { background: var(--cyan); }
        .bill-summary-card.after-discount-card .bill-summary-icon { background: var(--cyan-bg); color: var(--cyan); }
        .bill-summary-card.after-discount-card .bill-summary-value { color: var(--cyan); }
        
        /* Card 4: Paid Amount */
        .bill-summary-card.paid-card { border-color: var(--success); }
        .bill-summary-card.paid-card::before { background: var(--success); }
        .bill-summary-card.paid-card .bill-summary-icon { background: var(--success-bg); color: var(--success); }
        .bill-summary-card.paid-card .bill-summary-value { color: var(--success); }
        
        /* Card 5: Pending / Balance */
        .bill-summary-card.pending-card { border-color: var(--warning); }
        .bill-summary-card.pending-card::before { background: var(--warning); }
        .bill-summary-card.pending-card .bill-summary-icon { background: var(--warning-bg); color: var(--warning); }
        .bill-summary-card.pending-card .bill-summary-value { color: var(--warning); }
        .bill-summary-card.pending-card.zero-balance { border-color: var(--success); }
        .bill-summary-card.pending-card.zero-balance::before { background: var(--success); }
        .bill-summary-card.pending-card.zero-balance .bill-summary-icon { background: var(--success-bg); color: var(--success); }
        .bill-summary-card.pending-card.zero-balance .bill-summary-value { color: var(--success); }
        
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
        
        .table-wrap {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.78rem;
            min-width: 900px;
        }
        
        .data-table thead th {
            text-align: left;
            padding: 10px 14px;
            font-weight: 700;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: white;
            background: var(--success);
            border-bottom: 3px solid var(--success-dark);
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 2;
        }
        
        .data-table thead th:first-child { border-radius: 8px 0 0 0; }
        .data-table thead th:last-child { border-radius: 0 8px 0 0; }
        
        .data-table td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .data-table tbody tr:hover td {
            background: var(--table-hover);
        }
        
        .data-table .status-badge {
            display: inline-block;
            padding: 3px 14px;
            border-radius: 12px;
            font-size: 0.6rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-badge.pending {
            background: var(--warning-bg);
            color: var(--warning);
        }
        
        .status-badge.partial {
            background: var(--primary-bg);
            color: var(--primary);
        }
        
        .status-badge.paid {
            background: var(--success-bg);
            color: var(--success);
        }
        
        .status-badge.cancelled {
            background: var(--danger-bg);
            color: var(--danger);
        }
        
        .status-badge.otc {
            background: var(--otc-bg);
            color: var(--otc-color);
        }
        
        .bill-type-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 10px;
            font-size: 0.55rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .bill-type-badge.regular {
            background: var(--primary-bg);
            color: var(--primary);
        }
        
        .bill-type-badge.otc {
            background: var(--otc-bg);
            color: var(--otc-color);
        }
        
        .btn-group-vertical {
            display: flex;
            flex-direction: column;
            gap: 4px;
            align-items: stretch;
        }
        
        .btn-group-vertical .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.6rem;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            text-decoration: none;
            width: 100%;
            min-width: 55px;
            white-space: nowrap;
        }
        
        .btn-group-vertical .btn i {
            font-size: 0.65rem;
        }
        
        .btn-group-vertical .btn-view {
            background: var(--primary);
            color: white;
        }
        .btn-group-vertical .btn-view:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(11, 94, 215, 0.3);
        }
        
        .btn-group-vertical .btn-print {
            background: var(--success);
            color: white;
        }
        .btn-group-vertical .btn-print:hover {
            background: var(--success-dark);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(5, 150, 105, 0.3);
        }
        
        .btn-group-vertical .btn-process {
            background: var(--warning);
            color: white;
        }
        .btn-group-vertical .btn-process:hover {
            background: #B45309;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(217, 119, 6, 0.3);
        }
        
        .btn-group-vertical .btn-otc-view {
            background: var(--otc-color);
            color: white;
        }
        .btn-group-vertical .btn-otc-view:hover {
            background: #6D28D9;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(139, 92, 246, 0.3);
        }
        
        .btn-group-vertical .btn-otc-print {
            background: var(--success);
            color: white;
        }
        .btn-group-vertical .btn-otc-print:hover {
            background: var(--success-dark);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(5, 150, 105, 0.3);
        }
        
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
        
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        .footer .footer-brand { color: var(--success); font-weight: 600; }
        
        .pdf-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 9999;
            backdrop-filter: blur(4px);
            justify-content: center;
            align-items: center;
        }
        .pdf-modal-overlay.active { display: flex; }
        
        .pdf-modal {
            background: var(--bg-card);
            border-radius: 16px;
            width: 95%;
            max-width: 1100px;
            max-height: 95vh;
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow-lg);
            animation: slideUp 0.3s ease;
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        
        .pdf-modal-header {
            padding: 14px 22px;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            background: linear-gradient(135deg, #059669, #047857);
            border-radius: 16px 16px 0 0;
        }
        
        .pdf-modal-header .modal-title {
            font-size: 1rem;
            font-weight: 700;
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .pdf-modal-header .modal-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .pdf-modal-header .modal-actions .btn {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 6px 14px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.75rem;
            transition: all 0.3s;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .pdf-modal-header .modal-actions .btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }
        
        .pdf-modal-header .modal-actions .btn-danger-modal {
            background: rgba(220,38,38,0.3);
            border-color: rgba(220,38,38,0.2);
        }
        
        .pdf-modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 20px 28px;
            background: var(--bg-body);
        }
        
        .pdf-modal-body .pdf-content {
            max-width: 100%;
            font-size: 14px;
            background: var(--bg-card);
            padding: 24px 28px;
            border-radius: 12px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            line-height: 1.5;
            margin-top: 0;
            padding-top: 28px;
        }
        
        .pdf-content .pdf-header {
            text-align: center;
            padding-bottom: 12px;
            border-bottom: 3px solid #059669;
            margin-bottom: 16px;
            page-break-after: avoid;
            break-after: avoid;
            margin-top: 0;
            padding-top: 0;
        }
        
        .pdf-content .pdf-header .pdf-logo {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin-bottom: 4px;
        }
        
        .pdf-content .pdf-header .pdf-logo img {
            height: 55px;
            width: auto;
            object-fit: contain;
            display: block;
            margin: 0 auto;
        }
        
        .pdf-content .pdf-header .clinic-name {
            font-size: 1.4rem;
            font-weight: 800;
            color: #059669;
            letter-spacing: -0.5px;
            margin-top: 4px;
        }
        
        .pdf-content .pdf-header .clinic-sub {
            font-size: 0.75rem;
            color: var(--text-secondary);
            letter-spacing: 0.5px;
        }
        
        .pdf-content .pdf-header .doc-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: #059669;
            margin-top: 4px;
            background: #D1FAE5;
            padding: 4px 16px;
            border-radius: 20px;
            display: inline-block;
        }
        
        .pdf-content .pdf-section-title {
            font-weight: 700;
            font-size: 0.95rem;
            color: #059669;
            border-bottom: 2px solid #34D399;
            padding-bottom: 4px;
            margin: 6px 0 4px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .pdf-content .pdf-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin: 4px 0;
        }
        
        .pdf-content .pdf-table th {
            background: #059669;
            color: white;
            padding: 3px 6px;
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 700;
            border: 1px solid #047857;
        }
        
        .pdf-content .pdf-table td {
            padding: 3px 6px;
            border-bottom: 1px solid #E2E8F0;
            font-size: 11px;
            word-wrap: break-word;
        }
        
        .pdf-content .pdf-table tr:nth-child(even) td {
            background: #F8FAFC;
        }
        
        .pdf-content .pdf-empty {
            padding: 6px 0;
            color: var(--text-secondary);
            font-style: italic;
            font-size: 14px;
            text-align: center;
            background: #F8FAFC;
            border-radius: 4px;
            margin: 2px 0;
        }
        
        .pdf-content .pdf-footer {
            margin-top: 12px;
            padding-top: 10px;
            border-top: 2px solid #E2E8F0;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        
        .pdf-content .pdf-footer .footer-stamp {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .pdf-content .pdf-footer .footer-left {
            font-size: 14px;
            color: var(--text-secondary);
        }
        
        .pdf-content .pdf-footer .stamp-box {
            text-align: center;
            padding: 6px 14px;
            border: 3px solid #059669;
            border-radius: 10px;
            background: #D1FAE5;
            min-width: 150px;
        }
        
        .pdf-content .pdf-footer .stamp-box .stamp-title {
            font-size: 10px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
        }
        
        .pdf-content .pdf-footer .stamp-box .stamp-name {
            font-size: 14px;
            font-weight: 800;
            color: #059669;
        }
        
        .pdf-content .pdf-footer .stamp-box .stamp-line {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 2px;
        }
        
        .pdf-content .pdf-footer .stamp-box .stamp-date {
            font-size: 10px;
            color: #94A3B8;
            margin-top: 2px;
        }
        
        .pdf-content .pdf-footer .footer-bottom {
            text-align: center;
            margin-top: 6px;
            font-size: 12px;
            color: #94A3B8;
        }
        
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
            .bill-summary-grid { grid-template-columns: repeat(3, 1fr); }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .filter-section { padding: 12px 14px; }
            .filter-btn { font-size: 0.6rem; padding: 3px 10px; }
            .card { padding: 14px 16px; }
            .bill-summary-grid { grid-template-columns: 1fr 1fr; }
            .btn-group-vertical .btn { font-size: 0.5rem; padding: 3px 6px; min-width: 40px; }
            .btn-group-vertical .btn i { font-size: 0.55rem; }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .filter-btn { font-size: 0.55rem; padding: 2px 8px; }
            .date-picker-group { flex-direction: column; align-items: stretch; }
            .date-picker-group .form-control { width: 100%; }
            .date-picker-group .btn-apply { width: 100%; justify-content: center; }
            .card { padding: 10px 12px; }
            .data-table { font-size: 0.6rem; min-width: 650px; }
            .btn-group-vertical .btn { font-size: 0.45rem; padding: 2px 5px; min-width: 35px; }
            .btn-group-vertical .btn i { font-size: 0.5rem; }
            .bill-summary-grid { grid-template-columns: 1fr; }
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
                <i class="fas fa-file-invoice"></i>
                All Bills
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;"><?= strtoupper($user_role) ?></span>
                <?php if ($is_reception): ?>
                    <span class="role-badge-display" style="background:rgba(52,211,153,0.3);color:#34D399;border-color:rgba(52,211,153,0.3);">
                        <i class="fas fa-check-circle"></i> Full Access
                    </span>
                <?php endif; ?>
                <?php if ($is_admin): ?>
                    <span class="role-badge-display" style="background:rgba(139,92,246,0.3);color:#C4B5FD;border-color:rgba(139,92,246,0.3);">
                        <i class="fas fa-user-shield"></i> Admin
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-list"></i>
                Complete bill history at <strong><?= htmlspecialchars($user_branch_name) ?></strong>
                
                <span class="header-badge">
                    <i class="fas fa-file-invoice"></i>
                    <?= $total_bills ?> Bills
                </span>
                
                <span class="header-badge" style="background:rgba(139,92,246,0.2);border-color:rgba(139,92,246,0.2);">
                    <i class="fas fa-shopping-cart"></i>
                    Includes OTC Sales
                </span>
                
                <?php if ($filter !== 'all' && $filter !== 'custom'): ?>
                <span class="header-badge">
                    <i class="fas fa-filter"></i>
                    <?= ucfirst(str_replace('months', ' Months', $filter)) ?>
                </span>
                <?php endif; ?>
                
                <?php if ($status_filter !== 'all'): ?>
                <span class="header-badge" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.2);color:#FCD34D;">
                    <i class="fas fa-tag"></i>
                    <?= ucfirst($status_filter) ?>
                </span>
                <?php endif; ?>
            </p>
        </div>
        <div class="header-right" style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <button onclick="manualRefresh()" class="btn-outline-light" id="refreshBtn">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
            <button onclick="generatePDF()" class="btn-outline-light" style="background:rgba(220,38,38,0.2);border-color:rgba(220,38,38,0.3);">
                <i class="fas fa-file-pdf"></i> Export PDF
            </button>
        </div>
    </div>

    <!-- MESSAGE -->
    <?php if ($message): ?>
        <div class="p-4 rounded-xl mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200' ?>" style="max-width:1200px;margin:0 auto 16px;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- 5 BILL SUMMARY CARDS -->
    <!-- ================================================================ -->
    <div class="bill-summary-grid" id="billSummaryGrid">
        <!-- Card 1: Total Amount -->
        <div class="bill-summary-card total-card">
            <div class="bill-summary-icon"><i class="fas fa-file-invoice"></i></div>
            <div class="bill-summary-content">
                <span class="bill-summary-label">Total Amount</span>
                <span class="bill-summary-value" id="totalAmountDisplay">TSh <?= number_format($total_subtotal, 0) ?></span>
            </div>
        </div>
        
        <!-- Card 2: Discount -->
        <div class="bill-summary-card discount-card">
            <div class="bill-summary-icon"><i class="fas fa-percent"></i></div>
            <div class="bill-summary-content">
                <span class="bill-summary-label">Total Discount</span>
                <span class="bill-summary-value" id="discountAmountDisplay">TSh <?= number_format($total_discount, 0) ?></span>
            </div>
        </div>
        
        <!-- Card 3: Amount After Discount -->
        <div class="bill-summary-card after-discount-card">
            <div class="bill-summary-icon"><i class="fas fa-calculator"></i></div>
            <div class="bill-summary-content">
                <span class="bill-summary-label">After Discount</span>
                <span class="bill-summary-value" id="afterDiscountDisplay">TSh <?= number_format($amount_after_discount, 0) ?></span>
            </div>
        </div>
        
        <!-- Card 4: Paid Amount -->
        <div class="bill-summary-card paid-card">
            <div class="bill-summary-icon"><i class="fas fa-check-circle"></i></div>
            <div class="bill-summary-content">
                <span class="bill-summary-label">Paid Amount</span>
                <span class="bill-summary-value" id="paidAmountDisplay">TSh <?= number_format($total_paid, 0) ?></span>
            </div>
        </div>
        
        <!-- Card 5: Pending / Balance -->
        <div class="bill-summary-card pending-card <?= ($total_balance) <= 0 ? 'zero-balance' : '' ?>">
            <div class="bill-summary-icon"><i class="fas <?= ($total_balance) > 0 ? 'fa-exclamation-triangle' : 'fa-check-circle' ?>"></i></div>
            <div class="bill-summary-content">
                <span class="bill-summary-label"><?= ($total_balance) > 0 ? 'Pending' : 'Balance' ?></span>
                <span class="bill-summary-value" id="pendingAmountDisplay">TSh <?= number_format($total_balance, 0) ?></span>
            </div>
        </div>
    </div>

    <!-- FILTERS -->
    <div class="filter-section">
        <div class="filter-group" style="margin-bottom:8px;">
            <span class="filter-label"><i class="fas fa-calendar-alt"></i> Filter:</span>
            
            <a href="?filter=all&status=<?= $status_filter ?>&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">
                <i class="fas fa-globe"></i> All
            </a>
            <a href="?filter=today&status=<?= $status_filter ?>&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === 'today' ? 'active' : '' ?>">
                <i class="fas fa-calendar-day"></i> Today
            </a>
            <a href="?filter=week&status=<?= $status_filter ?>&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === 'week' ? 'active' : '' ?>">
                <i class="fas fa-calendar-week"></i> 1 Week
            </a>
            <a href="?filter=month&status=<?= $status_filter ?>&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === 'month' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i> 1 Month
            </a>
            <a href="?filter=3months&status=<?= $status_filter ?>&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === '3months' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i> 3 Months
            </a>
            <a href="?filter=6months&status=<?= $status_filter ?>&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === '6months' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i> 6 Months
            </a>
            <a href="?filter=year&status=<?= $status_filter ?>&search=<?= urlencode($search) ?>" class="filter-btn <?= $filter === 'year' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i> 1 Year
            </a>
        </div>
        
        <div class="filter-group" style="border-top:1px solid var(--border-color);padding-top:8px;margin-top:4px;flex-wrap:wrap;">
            <span class="filter-label"><i class="fas fa-tag"></i> Status:</span>
            
            <a href="?status=all&filter=<?= $filter ?>&search=<?= urlencode($search) ?>" class="filter-btn <?= $status_filter === 'all' ? 'active' : '' ?>">
                <i class="fas fa-list"></i> All
            </a>
            <a href="?status=pending&filter=<?= $filter ?>&search=<?= urlencode($search) ?>" class="filter-btn <?= $status_filter === 'pending' ? 'active' : '' ?>">
                <i class="fas fa-clock"></i> Pending
            </a>
            <a href="?status=partial&filter=<?= $filter ?>&search=<?= urlencode($search) ?>" class="filter-btn <?= $status_filter === 'partial' ? 'active' : '' ?>">
                <i class="fas fa-money-bill-wave"></i> Partial
            </a>
            <a href="?status=paid&filter=<?= $filter ?>&search=<?= urlencode($search) ?>" class="filter-btn <?= $status_filter === 'paid' ? 'active' : '' ?>">
                <i class="fas fa-check-circle"></i> Paid
            </a>
            <a href="?status=cancelled&filter=<?= $filter ?>&search=<?= urlencode($search) ?>" class="filter-btn <?= $status_filter === 'cancelled' ? 'active' : '' ?>">
                <i class="fas fa-times-circle"></i> Cancelled
            </a>
            
            <span class="filter-divider" style="width:1px;height:24px;background:var(--border-color);margin:0 4px;"></span>
            
            <form method="GET" action="" class="date-picker-group" style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                <input type="hidden" name="filter" value="custom">
                <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                
                <input type="date" name="start_date" class="form-control" 
                       value="<?= $start_date ?>" placeholder="Start Date">
                <span style="color:var(--text-secondary);font-size:0.7rem;">to</span>
                <input type="date" name="end_date" class="form-control" 
                       value="<?= $end_date ?>" placeholder="End Date">
                <button type="submit" class="btn-apply">
                    <i class="fas fa-check"></i> Apply
                </button>
                <?php if ($filter === 'custom' && !empty($start_date) && !empty($end_date)): ?>
                    <a href="?filter=all&status=<?= $status_filter ?>&search=<?= urlencode($search) ?>" class="btn-apply" style="background:#DC2626;">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- ALL BILLS TABLE -->
    <div class="card" style="max-width:1200px;margin:0 auto;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list" style="color:var(--success);"></i> All Bills & OTC Sales
                <span class="text-sm font-normal text-gray-400">(<?= $total_bills ?> records)</span>
            </h3>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                <span class="text-xs text-gray-400">
                    <i class="fas fa-clock"></i> Updated: <?= date('h:i:s A') ?>
                </span>
            </div>
        </div>
        
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:30px;">#</th>
                        <th style="min-width:110px;">Bill #</th>
                        <th style="min-width:60px;">Type</th>
                        <th style="min-width:140px;">Patient</th>
                        <th style="min-width:80px;">Visit</th>
                        <th style="min-width:80px;">Subtotal</th>
                        <th style="min-width:70px;">Discount</th>
                        <th style="min-width:80px;">Total</th>
                        <th style="min-width:80px;">Paid</th>
                        <th style="min-width:80px;">Balance</th>
                        <th style="min-width:40px;">Items</th>
                        <th style="min-width:70px;">Status</th>
                        <th style="min-width:80px;">Date</th>
                        <th style="min-width:70px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (is_array($all_bills) && count($all_bills) > 0): ?>
                        <?php $i = 1; foreach ($all_bills as $bill): 
                            $is_otc = ($bill['bill_type'] ?? '') === 'OTC';
                            $status = $bill['status'] ?? 'pending';
                            $bill_total = (float)($bill['total_amount'] ?? 0);
                            $bill_paid = (float)($bill['paid_amount'] ?? $bill['total_paid_amount'] ?? 0);
                            $bill_balance = (float)($bill['balance'] ?? 0);
                            $bill_discount = (float)($bill['bill_discount'] ?? 0);
                            $bill_subtotal = (float)($bill['subtotal'] ?? $bill_total);
                            
                            $status_class = $is_otc ? 'otc' : $status;
                            $type_label = $is_otc ? 'OTC' : 'Reg';
                        ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td>
                                    <span class="font-mono text-xs font-bold <?= $is_otc ? 'text-purple-600' : 'text-gray-700' ?>">
                                        <?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
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
                                    <div class="font-medium text-sm"><?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?></div>
                                    <div class="text-xs text-gray-400">
                                        <?= htmlspecialchars($bill['phone'] ?? 'No phone') ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($is_otc): ?>
                                        <span class="text-xs text-gray-400">—</span>
                                    <?php else: ?>
                                        <span class="text-xs font-mono"><?= htmlspecialchars($bill['visit_number'] ?? 'N/A') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right">
                                    <span class="font-semibold text-gray-600"><?= $currency ?> <?= number_format($bill_subtotal, 0) ?></span>
                                </td>
                                <td class="text-right">
                                    <?php if ($bill_discount > 0): ?>
                                        <span style="color:var(--warning);font-weight:600;">
                                            -<?= $currency ?> <?= number_format($bill_discount, 0) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right">
                                    <span class="font-semibold <?= $status === 'paid' ? 'text-green-600' : 'text-gray-800' ?>">
                                        <?= $currency ?> <?= number_format($bill_total, 0) ?>
                                    </span>
                                </td>
                                <td class="text-right">
                                    <span class="font-semibold <?= $is_otc ? 'text-purple-600' : 'text-green-600' ?>">
                                        <?= $currency ?> <?= number_format($bill_paid, 0) ?>
                                    </span>
                                </td>
                                <td class="text-right">
                                    <span class="font-semibold <?= $bill_balance > 0 ? 'text-red-600' : 'text-green-600' ?>">
                                        <?= $currency ?> <?= number_format($bill_balance, 0) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="text-xs font-semibold"><?= $bill['item_count'] ?? 0 ?></span>
                                </td>
                                <td>
                                    <span class="status-badge <?= $status_class ?>">
                                        <?= $is_otc ? 'OTC' : ucfirst($status) ?>
                                    </span>
                                </td>
                                <td class="text-xs">
                                    <?= isset($bill['updated_at']) ? date('d/m/y', strtotime($bill['updated_at'])) : 'N/A' ?>
                                    <br>
                                    <span class="text-gray-400 text-[0.55rem]">
                                        <?= isset($bill['updated_at']) ? date('h:i A', strtotime($bill['updated_at'])) : '' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group-vertical">
                                        <?php if ($is_otc): ?>
                                            <a href="view_otc_sale.php?id=<?= $bill['id'] ?>" class="btn btn-otc-view" title="View OTC Sale">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <?php if ($status === 'pending'): ?>
                                                <a href="process_otc_payment.php?sale_id=<?= $bill['id'] ?>" class="btn btn-process" title="Process Payment">
                                                    <i class="fas fa-money-bill-wave"></i> Pay
                                                </a>
                                            <?php endif; ?>
                                            <a href="print_receipt.php?type=otc&sale_id=<?= $bill['id'] ?>&print=1" class="btn btn-otc-print" title="Print Receipt" target="_blank">
                                                <i class="fas fa-print"></i> Print
                                            </a>
                                        <?php else: ?>
                                            <a href="view_bill.php?id=<?= $bill['id'] ?>" class="btn btn-view" title="View Bill">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <?php if ($status === 'pending' || $status === 'partial'): ?>
                                                <a href="process_payment.php?bill_id=<?= $bill['id'] ?>" class="btn btn-process" title="Process Payment">
                                                    <i class="fas fa-money-bill-wave"></i> Pay
                                                </a>
                                            <?php endif; ?>
                                            <a href="print_receipt.php?bill_id=<?= $bill['id'] ?>&print=1" class="btn btn-print" title="Print Receipt" target="_blank">
                                                <i class="fas fa-print"></i> Print
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="14" class="text-center py-8 text-gray-400">
                                <i class="fas fa-file-invoice text-3xl block mb-2 text-gray-300"></i>
                                <p class="text-lg">No bills found</p>
                                <p class="text-sm">
                                    <?php if ($filter !== 'all' || $status_filter !== 'all'): ?>
                                        No records found for the selected filters
                                    <?php else: ?>
                                        No bills have been created yet
                                    <?php endif; ?>
                                </p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            All Bills
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

<!-- PDF MODAL -->
<div class="pdf-modal-overlay" id="pdfModal">
    <div class="pdf-modal">
        <div class="pdf-modal-header">
            <div class="modal-title">
                <i class="fas fa-file-pdf" style="color:rgba(255,255,255,0.8);"></i>
                All Bills Report (Including OTC)
            </div>
            <div class="modal-actions">
                <button onclick="downloadPDF()" class="btn">
                    <i class="fas fa-download"></i> Download
                </button>
                <button onclick="window.print()" class="btn">
                    <i class="fas fa-print"></i> Print
                </button>
                <button onclick="closePDFModal()" class="btn btn-danger-modal">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
        <div class="pdf-modal-body" id="pdfModalBody">
            <div class="pdf-content" id="pdfContent">
                <!-- PDF content generated by JavaScript -->
            </div>
        </div>
    </div>
</div>

<!-- TOAST -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE
    // ================================================================

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
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
    // DATE & TIME
    // ================================================================
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

    // ================================================================
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    if (!searchBtn && !searchInput) {
        searchBtn = document.querySelector('.top-nav .search-btn');
        searchInput = document.querySelector('.top-nav #searchInput');
    }
    
    function performSearch() {
        var query = searchInput?.value?.trim() || '';
        var filter = '<?= $filter ?>';
        var status = '<?= $status_filter ?>';
        var start_date = '<?= $start_date ?>';
        var end_date = '<?= $end_date ?>';
        if (query.length > 0) {
            window.location.href = 'all_bills.php?search=' + encodeURIComponent(query) + '&filter=' + filter + '&status=' + status + '&start_date=' + start_date + '&end_date=' + end_date;
        }
    }
    
    if (searchBtn) {
        searchBtn.addEventListener('click', performSearch);
    }
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') performSearch();
        });
        searchInput.value = '<?= htmlspecialchars($search) ?>';
    }

    // ================================================================
    // TOAST
    // ================================================================
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

    // ================================================================
    // MANUAL REFRESH
    // ================================================================
    function manualRefresh() {
        var btn = document.getElementById('refreshBtn');
        if (btn) {
            btn.innerHTML = '<span class="spinner"></span> Loading...';
            btn.disabled = true;
        }
        
        setTimeout(function() {
            window.location.reload();
        }, 1000);
        
        setTimeout(function() {
            if (btn) {
                btn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh';
                btn.disabled = false;
            }
            showToast('✅ Refreshed', 'Page data updated', 'success');
        }, 2000);
    }

    // ================================================================
    // PDF GENERATION
    // ================================================================
    function generatePDF() {
        var modal = document.getElementById('pdfModal');
        var content = document.getElementById('pdfContent');
        
        var adminPhones = '<?= !empty($admin_phones) ? implode(' | ', $admin_phones) : ($branch_phone ?? '+255 700 000 001') ?>';
        var currency = '<?= $currency ?>';
        var branchName = '<?= htmlspecialchars($user_branch_name) ?>';
        var totalBills = <?= $total_bills ?>;
        var filterLabel = '<?= $filter ?>';
        var filterDisplay = filterLabel === 'all' ? 'All Time' : filterLabel;
        var statusFilter = '<?= $status_filter ?>';
        var statusDisplay = statusFilter === 'all' ? 'All Status' : ucfirst(statusFilter);
        var totalAmount = <?= $total_amount ?>;
        var totalPaid = <?= $total_paid ?>;
        var totalBalance = <?= $total_balance ?>;
        var totalDiscount = <?= $total_discount ?>;
        var amountAfterDiscount = <?= $amount_after_discount ?>;
        
        var billsHtml = '';
        var counter = 1;
        <?php foreach ($all_bills as $bill): 
            $is_otc = ($bill['bill_type'] ?? '') === 'OTC';
            $typeLabel = $is_otc ? 'OTC' : 'Reg';
            $status = $bill['status'] ?? 'pending';
            $bill_total = (float)($bill['total_amount'] ?? 0);
            $bill_paid = (float)($bill['paid_amount'] ?? $bill['total_paid_amount'] ?? 0);
            $bill_balance = (float)($bill['balance'] ?? 0);
            $bill_discount = (float)($bill['bill_discount'] ?? 0);
        ?>
            billsHtml += `
                <tr>
                    <td style="padding:2px 5px;border-bottom:1px solid #E2E8F0;font-size:11px;text-align:center;">${counter}</td>
                    <td style="padding:2px 5px;border-bottom:1px solid #E2E8F0;font-size:11px;font-weight:600;<?= $is_otc ? 'color:#6D28D9;' : 'color:#0B5ED7;' ?>"><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></td>
                    <td style="padding:2px 5px;border-bottom:1px solid #E2E8F0;font-size:10px;text-align:center;"><span style="background:<?= $is_otc ? '#EDE9FE;color:#6D28D9;' : '#E8F0FE;color:#0B5ED7;' ?>padding:1px 8px;border-radius:8px;font-weight:600;">${typeLabel}</span></td>
                    <td style="padding:2px 5px;border-bottom:1px solid #E2E8F0;font-size:11px;"><strong><?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?></strong></td>
                    <td style="padding:2px 5px;border-bottom:1px solid #E2E8F0;font-size:10px;text-align:center;"><?= $is_otc ? '—' : htmlspecialchars($bill['visit_number'] ?? 'N/A') ?></td>
                    <td style="padding:2px 5px;border-bottom:1px solid #E2E8F0;text-align:right;font-size:11px;">${currency} <?= number_format($bill_total, 0) ?></td>
                    <td style="padding:2px 5px;border-bottom:1px solid #E2E8F0;text-align:right;font-size:11px;<?= $bill_discount > 0 ? 'color:#D97706;' : 'color:#94A3B8;' ?>"><?= $bill_discount > 0 ? '-'.number_format($bill_discount, 0) : '—' ?></td>
                    <td style="padding:2px 5px;border-bottom:1px solid #E2E8F0;text-align:right;font-size:11px;font-weight:600;<?= $status === 'paid' ? 'color:#059669;' : '' ?>">${currency} <?= number_format($bill_total, 0) ?></td>
                    <td style="padding:2px 5px;border-bottom:1px solid #E2E8F0;text-align:right;font-size:11px;<?= $is_otc ? 'color:#6D28D9;' : 'color:#059669;' ?>">${currency} <?= number_format($bill_paid, 0) ?></td>
                    <td style="padding:2px 5px;border-bottom:1px solid #E2E8F0;text-align:right;font-size:11px;<?= $bill_balance > 0 ? 'color:#DC2626;' : 'color:#059669;' ?>">${currency} <?= number_format($bill_balance, 0) ?></td>
                    <td style="padding:2px 5px;border-bottom:1px solid #E2E8F0;text-align:center;font-size:11px;"><?= $bill['item_count'] ?? 0 ?></td>
                    <td style="padding:2px 5px;border-bottom:1px solid #E2E8F0;font-size:11px;"><span style="background:<?= $status === 'paid' ? '#D1FAE5;color:#059669;' : ($status === 'pending' ? '#FEF3C7;color:#D97706;' : ($status === 'partial' ? '#E8F0FE;color:#0B5ED7;' : '#FEE2E2;color:#DC2626;')) ?>padding:1px 8px;border-radius:8px;font-weight:600;"><?= $is_otc ? 'OTC' : ucfirst($status) ?></span></td>
                    <td style="padding:2px 5px;border-bottom:1px solid #E2E8F0;font-size:11px;"><?= isset($bill['updated_at']) ? date('d/m/y h:i A', strtotime($bill['updated_at'])) : 'N/A' ?></td>
                </tr>
            `;
            counter++;
        <?php endforeach; ?>
        
        if (!billsHtml) {
            billsHtml = `<tr><td colspan="13" style="text-align:center;padding:20px;font-size:14px;color:#64748B;">No bills found</td></tr>`;
        }
        
        var html = `
            <!-- PDF HEADER -->
            <div class="pdf-header">
                <div class="pdf-logo">
                    <img src="/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png" alt="Braick Logo" style="height:55px;width:auto;object-fit:contain;display:block;margin:0 auto;" onerror="this.style.display='none'">
                    <div class="clinic-name">BRAICK DISPENSARY</div>
                    <div class="clinic-sub">Tunajali Afya Yako</div>
                </div>
                <div style="display:flex;justify-content:center;gap:14px;flex-wrap:wrap;margin-top:4px;padding-top:4px;border-top:1px solid #E2E8F0;font-size:0.6rem;color:#64748B;">
                    <span>📞 Admin: ${adminPhones}</span>
                    <span>🏢 Branch: ${branchName}</span>
                    <span>📅 ${new Date().toLocaleDateString('en-US', { weekday:'short', month:'short', day:'numeric', year:'numeric' })}</span>
                </div>
                <div class="doc-title">
                    📋 All Bills Report (Including OTC Sales)
                </div>
            </div>
            
            <!-- SUMMARY -->
            <div style="margin-bottom:6px;">
                <div class="pdf-section-title"><i class="fas fa-chart-bar"></i> Summary</div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr 1fr;gap:6px;margin:4px 0;">
                    <div style="background:#D1FAE5;padding:4px 8px;border-radius:6px;text-align:center;border:1px solid #059669;">
                        <div style="font-size:16px;font-weight:700;color:#059669;">${totalBills}</div>
                        <div style="font-size:8px;color:#64748B;text-transform:uppercase;font-weight:600;">📋 Total Bills</div>
                    </div>
                    <div style="background:#E8F0FE;padding:4px 8px;border-radius:6px;text-align:center;border:1px solid #0B5ED7;">
                        <div style="font-size:16px;font-weight:700;color:#0B5ED7;">${filterDisplay}</div>
                        <div style="font-size:8px;color:#64748B;text-transform:uppercase;font-weight:600;">📅 Date Range</div>
                    </div>
                    <div style="background:#EDE9FE;padding:4px 8px;border-radius:6px;text-align:center;border:1px solid #7C3AED;">
                        <div style="font-size:16px;font-weight:700;color:#7C3AED;">${statusDisplay}</div>
                        <div style="font-size:8px;color:#64748B;text-transform:uppercase;font-weight:600;">🏷️ Status</div>
                    </div>
                    <div style="background:#D1FAE5;padding:4px 8px;border-radius:6px;text-align:center;border:1px solid #059669;">
                        <div style="font-size:16px;font-weight:700;color:#059669;">${currency} ${totalPaid.toLocaleString()}</div>
                        <div style="font-size:8px;color:#64748B;text-transform:uppercase;font-weight:600;">💰 Total Paid</div>
                    </div>
                    <div style="background:#FEF3C7;padding:4px 8px;border-radius:6px;text-align:center;border:1px solid #D97706;">
                        <div style="font-size:16px;font-weight:700;color:#D97706;">${currency} ${totalBalance.toLocaleString()}</div>
                        <div style="font-size:8px;color:#64748B;text-transform:uppercase;font-weight:600;">⚖️ Total Balance</div>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:6px;margin-top:4px;">
                    <div style="background:#CFFAFE;padding:3px 8px;border-radius:4px;text-align:center;border:1px solid #0891B2;">
                        <span style="font-size:13px;font-weight:700;color:#0891B2;">${currency} ${totalAmount.toLocaleString()}</span>
                        <span style="font-size:8px;color:#64748B;margin-left:4px;font-weight:500;">Subtotal</span>
                    </div>
                    <div style="background:#EDE9FE;padding:3px 8px;border-radius:4px;text-align:center;border:1px solid #7C3AED;">
                        <span style="font-size:13px;font-weight:700;color:#7C3AED;">-${currency} ${totalDiscount.toLocaleString()}</span>
                        <span style="font-size:8px;color:#64748B;margin-left:4px;font-weight:500;">Discount</span>
                    </div>
                    <div style="background:#D1FAE5;padding:3px 8px;border-radius:4px;text-align:center;border:1px solid #059669;">
                        <span style="font-size:13px;font-weight:700;color:#059669;">${currency} ${amountAfterDiscount.toLocaleString()}</span>
                        <span style="font-size:8px;color:#64748B;margin-left:4px;font-weight:500;">After Discount</span>
                    </div>
                </div>
            </div>
            
            <!-- ALL BILLS TABLE -->
            <div style="margin-bottom:6px;">
                <div class="pdf-section-title"><i class="fas fa-list"></i> All Bills (${totalBills})</div>
                <div class="pdf-table-wrap">
                    <table class="pdf-table" style="font-size:11px;width:100%;border-collapse:collapse;">
                        <thead>
                            <tr>
                                <th style="background:#059669;color:white;padding:2px 5px;text-align:center;font-size:9px;">#</th>
                                <th style="background:#059669;color:white;padding:2px 5px;text-align:left;font-size:9px;">Bill #</th>
                                <th style="background:#059669;color:white;padding:2px 5px;text-align:center;font-size:9px;">Type</th>
                                <th style="background:#059669;color:white;padding:2px 5px;text-align:left;font-size:9px;">Patient</th>
                                <th style="background:#059669;color:white;padding:2px 5px;text-align:center;font-size:9px;">Visit</th>
                                <th style="background:#059669;color:white;padding:2px 5px;text-align:right;font-size:9px;">Subtotal</th>
                                <th style="background:#059669;color:white;padding:2px 5px;text-align:right;font-size:9px;">Discount</th>
                                <th style="background:#059669;color:white;padding:2px 5px;text-align:right;font-size:9px;">Total</th>
                                <th style="background:#059669;color:white;padding:2px 5px;text-align:right;font-size:9px;">Paid</th>
                                <th style="background:#059669;color:white;padding:2px 5px;text-align:right;font-size:9px;">Balance</th>
                                <th style="background:#059669;color:white;padding:2px 5px;text-align:center;font-size:9px;">Items</th>
                                <th style="background:#059669;color:white;padding:2px 5px;text-align:left;font-size:9px;">Status</th>
                                <th style="background:#059669;color:white;padding:2px 5px;text-align:left;font-size:9px;">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${billsHtml}
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- PDF FOOTER WITH OFFICIAL STAMP -->
            <div class="pdf-footer">
                <div class="footer-stamp">
                    <div class="footer-left">
                        <span>Generated by: <?= htmlspecialchars($user_full_name) ?></span>
                        <span style="margin-left:14px;">Date: <?= date('F d, Y') ?></span>
                    </div>
                    <div class="stamp-box">
                        <div class="stamp-title">Official Stamp</div>
                        <div class="stamp-name">BRAICK DISPENSARY</div>
                        <div class="stamp-line">Approved By: _________________</div>
                        <div class="stamp-date">Date: <?= date('F d, Y') ?></div>
                    </div>
                </div>
                <div class="footer-bottom">
                    Braick Dispensary • Generated on <?= date('F d, Y h:i:s A') ?> • All rights reserved
                </div>
            </div>
        `;
        
        content.innerHTML = html;
        modal.classList.add('active');
        
        var modalBody = document.getElementById('pdfModalBody');
        if (modalBody) {
            modalBody.scrollTop = 0;
        }
    }
    
    function closePDFModal() {
        document.getElementById('pdfModal').classList.remove('active');
    }
    
    function downloadPDF() {
        var element = document.getElementById('pdfContent');
        var opt = {
            margin: [6, 6, 6, 6],
            filename: 'All_Bills_<?= date('Y-m-d') ?>.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { 
                scale: 2, 
                useCORS: true,
                backgroundColor: '#ffffff',
                logging: false,
                allowTaint: true
            },
            jsPDF: { 
                unit: 'mm', 
                format: 'a4', 
                orientation: 'landscape' 
            },
            pagebreak: { 
                mode: ['css', 'legacy']
            }
        };
        
        html2pdf().set(opt).from(element).save();
    }

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closePDFModal();
        }
    });

    document.getElementById('pdfModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closePDFModal();
        }
    });

    console.log('%c📋 Braick - All Bills (Complete List)', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c✅ Shows ALL bills: pending, partial, paid, cancelled', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Includes OTC Sales', 'font-size:13px; color:#8B5CF6;');
    console.log('%c✅ 5 Bill Summary Cards with Discount', 'font-size:13px; color:#D97706;');
    console.log('%c✅ Filter by date and status', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📋 Total Records: <?= $total_bills ?>', 'font-size:13px; color:#64748B;');
    console.log('%c💰 Total Amount: <?= $currency ?> <?= number_format($total_amount, 0) ?>', 'font-size:13px; color:#34D399;');
    console.log('%c🏷️ Total Discount: <?= $currency ?> <?= number_format($total_discount, 0) ?>', 'font-size:13px; color:#D97706;');
</script>

</body>
</html>