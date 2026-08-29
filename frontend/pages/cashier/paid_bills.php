<?php
// ================================================================
// FILE: frontend/pages/cashier/paid_bills.php
// CASHIER - PAID BILLS LIST WITH PDF EXPORT
// FIXED: Shows regular bills (with visit_id) AND OTC sales
// FIXED: Excludes payments without visit_id from regular bills
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

$paid_bills = [];
$total_paid_amount = 0;
$total_bills = 0;

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
        $date_condition = "AND DATE(updated_at) = CURDATE()";
        break;
    case 'week':
        $date_condition = "AND updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case 'month':
        $date_condition = "AND updated_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        break;
    case '3months':
        $date_condition = "AND updated_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
        break;
    case '6months':
        $date_condition = "AND updated_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
        break;
    case 'year':
        $date_condition = "AND updated_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        break;
    case 'custom':
        if (!empty($start_date) && !empty($end_date)) {
            $date_condition = "AND DATE(updated_at) BETWEEN ? AND ?";
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
    $search_condition = "AND (patient_name LIKE ? OR bill_number LIKE ? OR patient_phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// ================================================================
// GET PAID BILLS - COMBINE REGULAR BILLS (with visit_id) + OTC SALES
// ================================================================
try {
    $all_paid = [];
    
    // ================================================================
    // 1. GET REGULAR PAID BILLS FROM bills TABLE (ONLY WITH VISIT_ID)
    // ================================================================
    $sql_bills = "
        SELECT 
            b.id as bill_id,
            b.bill_number,
            b.patient_id,
            b.total_amount,
            b.paid_amount,
            b.balance,
            b.status,
            b.payment_method,
            b.updated_at as paid_date,
            b.created_by as cashier_id,
            p.full_name as patient_name,
            p.patient_id as patient_code,
            p.phone as patient_phone,
            p.gender as patient_gender,
            u.full_name as cashier_name,
            'Regular' as bill_type,
            b.id as reference_id,
            b.bill_number as reference_number,
            (SELECT COUNT(*) FROM bill_items WHERE bill_id = b.id AND status != 'cancelled') as item_count,
            (SELECT COUNT(*) FROM bill_items WHERE bill_id = b.id AND item_type = 'medication' AND status != 'cancelled') as med_count,
            v.visit_number,
            v.visit_type
        FROM bills b
        LEFT JOIN patients p ON b.patient_id = p.id
        LEFT JOIN users u ON b.created_by = u.id
        LEFT JOIN visits v ON b.visit_id = v.id
        WHERE b.branch_id = ? 
        AND b.status = 'paid'
        AND b.visit_id IS NOT NULL
        AND b.visit_id > 0
        $date_condition
        $search_condition
    ";
    
    $stmt = $db->prepare($sql_bills);
    $stmt->execute($params);
    $regular_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($regular_bills as $bill) {
        $all_paid[] = $bill;
    }
    
    // ================================================================
    // 2. GET OTC PAID SALES FROM otc_sales TABLE
    // ================================================================
    $otc_params = [$user_branch_id];
    $otc_date_condition = str_replace('updated_at', 'o.updated_at', $date_condition);
    $otc_search_condition = "";
    
    if (!empty($search)) {
        $otc_search_condition = "AND (o.customer_name LIKE ? OR o.sale_number LIKE ? OR o.customer_phone LIKE ?)";
        $otc_params[] = "%$search%";
        $otc_params[] = "%$search%";
        $otc_params[] = "%$search%";
    }
    
    $sql_otc = "
        SELECT 
            o.id as bill_id,
            o.sale_number as bill_number,
            o.patient_id,
            o.total_amount,
            o.total_amount as paid_amount,
            0 as balance,
            'paid' as status,
            o.payment_method,
            o.updated_at as paid_date,
            o.sold_by as cashier_id,
            COALESCE(o.customer_name, 'Walk-in Customer') as patient_name,
            o.patient_id as patient_code,
            o.customer_phone as patient_phone,
            NULL as patient_gender,
            u.full_name as cashier_name,
            'OTC' as bill_type,
            o.id as reference_id,
            o.sale_number as reference_number,
            (SELECT COUNT(*) FROM otc_sale_items WHERE sale_id = o.id) as item_count,
            (SELECT COUNT(*) FROM otc_sale_items WHERE sale_id = o.id) as med_count,
            NULL as visit_number,
            'OTC Sale' as visit_type
        FROM otc_sales o
        LEFT JOIN users u ON o.sold_by = u.id
        WHERE o.branch_id = ? 
        AND o.payment_status = 'paid'
        $otc_date_condition
        $otc_search_condition
    ";
    
    $stmt = $db->prepare($sql_otc);
    $stmt->execute($otc_params);
    $otc_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($otc_bills as $bill) {
        $all_paid[] = $bill;
    }
    
    // ================================================================
    // SORT BY PAID DATE (newest first)
    // ================================================================
    usort($all_paid, function($a, $b) {
        return strtotime($b['paid_date']) - strtotime($a['paid_date']);
    });
    
    $paid_bills = $all_paid;
    $total_bills = count($paid_bills);
    foreach ($paid_bills as $bill) {
        $total_paid_amount += (float)($bill['total_amount'] ?? 0);
    }
    
} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $paid_bills = [];
    $total_paid_amount = 0;
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
        [data-theme="dark"] .status-badge.paid { background: #1A3A2A; color: #34D399; }
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
            font-size: 0.8rem;
            min-width: 750px;
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
        
        .status-badge {
            display: inline-block;
            padding: 3px 14px;
            border-radius: 12px;
            font-size: 0.6rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-badge.paid {
            background: #D1FAE5;
            color: #059669;
        }
        
        .status-badge.otc {
            background: #EDE9FE;
            color: #6D28D9;
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
            background: #E8F0FE;
            color: #0B5ED7;
        }
        
        .bill-type-badge.otc {
            background: #EDE9FE;
            color: #6D28D9;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.72rem;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--success);
            color: white;
        }
        .btn-primary:hover {
            background: var(--success-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        .btn-success:hover {
            background: var(--success-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
        }
        
        .btn-otc {
            background: var(--otc-color);
            color: white;
        }
        .btn-otc:hover {
            background: #6D28D9;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
        }
        
        .btn-sm { 
            padding: 4px 10px; 
            font-size: 0.65rem; 
            border-radius: 6px; 
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
        
        .stat-card .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
        }
        .stat-card .stat-number.green { color: var(--success); }
        .stat-card .stat-number.purple { color: var(--otc-color); }
        .stat-card .stat-number.blue { color: var(--primary); }
        .stat-card .stat-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
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
        
        .role-badge-display {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            background: var(--primary-bg);
            color: var(--primary);
            text-transform: uppercase;
        }
        
        .branch-badge-display {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            background: var(--success-bg);
            color: var(--success);
        }
        
        .otc-badge {
            display: inline-block;
            font-size: 0.55rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 12px;
            background: var(--otc-bg);
            color: var(--otc-color);
            border: 1px solid rgba(139,92,246,0.2);
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
        .toast-custom.warning { background: var(--warning); }
        
        /* PDF MODAL */
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
        
        /* PDF Styles */
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
            font-size: 13px;
            margin: 4px 0;
        }
        
        .pdf-content .pdf-table th {
            background: #059669;
            color: white;
            padding: 4px 10px;
            text-align: left;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 700;
            border: 1px solid #047857;
        }
        
        .pdf-content .pdf-table td {
            padding: 4px 10px;
            border-bottom: 1px solid #E2E8F0;
            font-size: 13px;
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
            position: relative;
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
        
        .pdf-content .all-paid-stamp {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 72px;
            font-weight: 900;
            color: rgba(5, 150, 105, 0.15);
            text-transform: uppercase;
            letter-spacing: 8px;
            border: 8px solid rgba(5, 150, 105, 0.12);
            padding: 20px 40px;
            border-radius: 20px;
            pointer-events: none;
            text-shadow: none;
            white-space: nowrap;
            z-index: 10;
            font-family: 'Inter', sans-serif;
        }
        
        .pdf-content .pdf-table-wrap {
            position: relative;
        }
        
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .filter-section { padding: 12px 14px; }
            .filter-btn { font-size: 0.6rem; padding: 3px 10px; }
            .card { padding: 14px 16px; }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .filter-btn { font-size: 0.55rem; padding: 2px 8px; }
            .date-picker-group { flex-direction: column; align-items: stretch; }
            .date-picker-group .form-control { width: 100%; }
            .date-picker-group .btn-apply { width: 100%; justify-content: center; }
            .card { padding: 10px 12px; }
            .btn { padding: 4px 8px; font-size: 0.6rem; }
            .data-table { font-size: 0.65rem; min-width: 600px; }
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
                View all paid bills in <strong><?= htmlspecialchars($user_branch_name) ?></strong>
                
                <span class="header-badge">
                    <i class="fas fa-file-invoice"></i>
                    <?= $total_bills ?> Bills
                </span>
                
                <span class="header-badge" style="background:rgba(139,92,246,0.2);border-color:rgba(139,92,246,0.2);">
                    <i class="fas fa-shopping-cart"></i>
                    Including OTC Sales
                </span>
                
                <?php if ($filter !== 'all' && $filter !== 'custom'): ?>
                <span class="header-badge">
                    <i class="fas fa-filter"></i>
                    <?= ucfirst(str_replace('months', ' Months', $filter)) ?>
                </span>
                <?php endif; ?>
            </p>
        </div>
        <div class="header-right" style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <button onclick="manualRefresh()" class="btn-outline-light" id="refreshBtn">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
            <button onclick="generatePDF()" class="btn-outline-light" style="background:rgba(220,38,38,0.2);border-color:rgba(220,38,38,0.3);">
                <i class="fas fa-file-pdf"></i> Export PDF
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
                <input type="date" name="start_date" class="form-control" 
                       value="<?= $start_date ?>" placeholder="Start Date">
                <span style="color:var(--text-secondary);font-size:0.7rem;">to</span>
                <input type="date" name="end_date" class="form-control" 
                       value="<?= $end_date ?>" placeholder="End Date">
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
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5" style="max-width:1200px;margin:0 auto;">
        <div class="stat-card">
            <div class="stat-icon">📋</div>
            <p class="stat-number green"><?= $total_bills ?></p>
            <p class="stat-label">Total Bills Paid</p>
        </div>
        <div class="stat-card">
            <div class="stat-icon">💰</div>
            <p class="stat-number green"><?= $currency ?> <?= number_format($total_paid_amount, 0) ?></p>
            <p class="stat-label">Total Amount Paid</p>
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
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list" style="color:var(--success);"></i> Paid Bills & OTC Sales
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
                        <th>#</th>
                        <th>Bill / Sale #</th>
                        <th>Type</th>
                        <th>Patient / Customer</th>
                        <th>Visit #</th>
                        <th>Total Amount</th>
                        <th>Paid Amount</th>
                        <th>Items</th>
                        <th>Paid By</th>
                        <th>Status</th>
                        <th>Paid Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (is_array($paid_bills) && count($paid_bills) > 0): ?>
                        <?php $i = 1; foreach ($paid_bills as $bill): 
                            $is_otc = ($bill['bill_type'] ?? '') === 'OTC';
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
                                            <i class="fas fa-file-invoice"></i> Regular
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="font-medium text-sm"><?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?></div>
                                    <div class="text-xs text-gray-400">
                                        <?= htmlspecialchars($bill['patient_phone'] ?? 'No phone') ?>
                                        <?php if (!empty($bill['patient_code']) && $bill['patient_code'] !== 'N/A'): ?>
                                            <span class="ml-1">| <?= htmlspecialchars($bill['patient_code']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($is_otc): ?>
                                        <span class="text-xs text-gray-400">—</span>
                                    <?php else: ?>
                                        <span class="text-xs font-mono"><?= htmlspecialchars($bill['visit_number'] ?? 'N/A') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="font-semibold text-gray-800">
                                        <?= $currency ?> <?= number_format($bill['total_amount'] ?? 0, 0) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="font-semibold <?= $is_otc ? 'text-purple-600' : 'text-green-600' ?>">
                                        <?= $currency ?> <?= number_format($bill['paid_amount'] ?? 0, 0) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="text-xs font-semibold"><?= $bill['item_count'] ?? 0 ?></span>
                                    <?php if (($bill['med_count'] ?? 0) > 0 && !$is_otc): ?>
                                        <br><span style="font-size:0.55rem;color:var(--text-secondary);">💊 <?= $bill['med_count'] ?> meds</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="text-sm"><?= htmlspecialchars($bill['cashier_name'] ?? 'N/A') ?></span>
                                </td>
                                <td>
                                    <?php if ($is_otc): ?>
                                        <span class="status-badge otc">OTC Paid</span>
                                    <?php else: ?>
                                        <span class="status-badge paid">Paid</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-xs">
                                    <?= isset($bill['paid_date']) ? date('d/m/Y', strtotime($bill['paid_date'])) : 'N/A' ?>
                                    <br>
                                    <span class="text-gray-400 text-[0.6rem]">
                                        <?= isset($bill['paid_date']) ? date('h:i A', strtotime($bill['paid_date'])) : '' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="flex flex-wrap gap-1">
                                        <?php if ($is_otc): ?>
                                            <a href="receipt.php?sale_id=<?= $bill['reference_id'] ?>" class="btn btn-otc btn-sm" title="View OTC Receipt">
                                                <i class="fas fa-receipt"></i>
                                            </a>
                                            <a href="print_receipt.php?type=otc&sale_id=<?= $bill['reference_id'] ?>&print=1" class="btn btn-success btn-sm" title="Print OTC Receipt" target="_blank">
                                                <i class="fas fa-print"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="view_bill.php?id=<?= $bill['bill_id'] ?>" class="btn btn-primary btn-sm" title="View Bill">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="receipt.php?bill_id=<?= $bill['bill_id'] ?>&print=1" class="btn btn-success btn-sm" title="Print Receipt" target="_blank">
                                                <i class="fas fa-print"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="12" class="text-center py-8 text-gray-400">
                                <i class="fas fa-check-circle text-3xl block mb-2 text-green-500"></i>
                                <p class="text-lg">No paid bills or OTC sales found</p>
                                <p class="text-sm">
                                    <?php if ($filter !== 'all'): ?>
                                        No records found for the selected date range
                                    <?php else: ?>
                                        All bills are pending or no payments have been made yet
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
            Paid Bills & OTC Sales
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
                Paid Bills Report (Including OTC)
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
    // Note: Dark mode is controlled by header.

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
        var start_date = '<?= $start_date ?>';
        var end_date = '<?= $end_date ?>';
        if (query.length > 0) {
            window.location.href = 'paid_bills.php?search=' + encodeURIComponent(query) + '&filter=' + filter + '&start_date=' + start_date + '&end_date=' + end_date;
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
    // PDF GENERATION - With "ALL PAID" Stamp
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
        var totalAmount = <?= $total_paid_amount ?>;
        
        var billsHtml = '';
        var counter = 1;
        <?php foreach ($paid_bills as $bill): 
            $is_otc = ($bill['bill_type'] ?? '') === 'OTC';
            $typeLabel = $is_otc ? 'OTC' : 'Regular';
        ?>
            billsHtml += `
                <tr>
                    <td style="padding:3px 8px;border-bottom:1px solid #E2E8F0;text-align:center;font-size:13px;">${counter}</td>
                    <td style="padding:3px 8px;border-bottom:1px solid #E2E8F0;font-size:13px;font-weight:600;<?= $is_otc ? 'color:#6D28D9;' : 'color:#0B5ED7;' ?>"><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></td>
                    <td style="padding:3px 8px;border-bottom:1px solid #E2E8F0;font-size:12px;text-align:center;"><span style="background:<?= $is_otc ? '#EDE9FE;color:#6D28D9;' : '#E8F0FE;color:#0B5ED7;' ?>padding:2px 10px;border-radius:10px;font-weight:600;">${typeLabel}</span></td>
                    <td style="padding:3px 8px;border-bottom:1px solid #E2E8F0;font-size:13px;"><strong><?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?></strong><br><span style="font-size:12px;color:#64748B;"><?= htmlspecialchars($bill['patient_phone'] ?? 'N/A') ?></span></td>
                    <td style="padding:3px 8px;border-bottom:1px solid #E2E8F0;font-size:12px;text-align:center;"><?= $is_otc ? '—' : htmlspecialchars($bill['visit_number'] ?? 'N/A') ?></td>
                    <td style="padding:3px 8px;border-bottom:1px solid #E2E8F0;text-align:right;font-size:13px;">${currency} <?= number_format($bill['total_amount'] ?? 0, 0) ?></td>
                    <td style="padding:3px 8px;border-bottom:1px solid #E2E8F0;text-align:right;font-size:13px;<?= $is_otc ? 'color:#6D28D9;' : 'color:#059669;' ?>">${currency} <?= number_format($bill['paid_amount'] ?? 0, 0) ?></td>
                    <td style="padding:3px 8px;border-bottom:1px solid #E2E8F0;text-align:center;font-size:13px;"><?= $bill['item_count'] ?? 0 ?></td>
                    <td style="padding:3px 8px;border-bottom:1px solid #E2E8F0;font-size:13px;"><?= htmlspecialchars($bill['cashier_name'] ?? 'N/A') ?></td>
                    <td style="padding:3px 8px;border-bottom:1px solid #E2E8F0;font-size:13px;"><?= isset($bill['paid_date']) ? date('d/m/Y h:i A', strtotime($bill['paid_date'])) : 'N/A' ?></td>
                </tr>
            `;
            counter++;
        <?php endforeach; ?>
        
        if (!billsHtml) {
            billsHtml = `<tr><td colspan="10" style="text-align:center;padding:20px;font-size:14px;color:#64748B;">No paid bills or OTC sales found</td></tr>`;
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
                    <span>📞 Admin Contacts: ${adminPhones}</span>
                    <span>🏢 Branch: ${branchName}</span>
                    <span>📅 ${new Date().toLocaleDateString('en-US', { weekday:'short', month:'short', day:'numeric', year:'numeric' })}</span>
                </div>
                <div class="doc-title">
                    ✅ Paid Bills & OTC Sales Report
                </div>
            </div>
            
            <!-- SUMMARY -->
            <div style="margin-bottom:8px;">
                <div class="pdf-section-title"><i class="fas fa-chart-bar"></i> Summary</div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin:4px 0;">
                    <div style="background:#D1FAE5;padding:8px 12px;border-radius:8px;text-align:center;border:1px solid #059669;">
                        <div style="font-size:20px;font-weight:700;color:#059669;">${totalBills}</div>
                        <div style="font-size:10px;color:#64748B;text-transform:uppercase;font-weight:600;">📋 Total Records</div>
                    </div>
                    <div style="background:#E8F0FE;padding:8px 12px;border-radius:8px;text-align:center;border:1px solid #0B5ED7;">
                        <div style="font-size:20px;font-weight:700;color:#0B5ED7;">${filterDisplay}</div>
                        <div style="font-size:10px;color:#64748B;text-transform:uppercase;font-weight:600;">📅 Date Range</div>
                    </div>
                    <div style="background:#D1FAE5;padding:8px 12px;border-radius:8px;text-align:center;border:1px solid #059669;">
                        <div style="font-size:20px;font-weight:700;color:#059669;">${currency} <?= number_format($total_paid_amount, 0) ?></div>
                        <div style="font-size:10px;color:#64748B;text-transform:uppercase;font-weight:600;">💰 Total Paid</div>
                    </div>
                </div>
            </div>
            
            <!-- PAID BILLS TABLE -->
            <div style="margin-bottom:8px;">
                <div class="pdf-section-title"><i class="fas fa-list"></i> Paid Records (${totalBills})</div>
                <div class="pdf-table-wrap" style="position:relative;">
                    <div class="all-paid-stamp" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%) rotate(-30deg);font-size:72px;font-weight:900;color:rgba(5,150,105,0.12);text-transform:uppercase;letter-spacing:8px;border:8px solid rgba(5,150,105,0.08);padding:20px 40px;border-radius:20px;pointer-events:none;z-index:10;font-family:'Inter',sans-serif;white-space:nowrap;">
                        <div style="position:relative;z-index:2;color:rgba(5,150,105,0.15);font-size:64px;">
                            ALL <span style="color:rgba(220,38,38,0.12);font-weight:300;margin:0 4px;">//</span> PAID
                        </div>
                        <div style="position:absolute;top:-10%;left:-10%;width:120%;height:120%;border:4px solid rgba(5,150,105,0.05);transform:rotate(-45deg);border-radius:20px;pointer-events:none;"></div>
                    </div>
                    <table class="pdf-table" style="font-size:13px;width:100%;border-collapse:collapse;position:relative;">
                        <thead>
                            <tr>
                                <th style="background:#059669;color:white;padding:4px 8px;text-align:center;font-size:11px;">#</th>
                                <th style="background:#059669;color:white;padding:4px 8px;text-align:left;font-size:11px;">Bill / Sale #</th>
                                <th style="background:#059669;color:white;padding:4px 8px;text-align:center;font-size:11px;">Type</th>
                                <th style="background:#059669;color:white;padding:4px 8px;text-align:left;font-size:11px;">Patient / Customer</th>
                                <th style="background:#059669;color:white;padding:4px 8px;text-align:center;font-size:11px;">Visit #</th>
                                <th style="background:#059669;color:white;padding:4px 8px;text-align:right;font-size:11px;">Total</th>
                                <th style="background:#059669;color:white;padding:4px 8px;text-align:right;font-size:11px;">Paid</th>
                                <th style="background:#059669;color:white;padding:4px 8px;text-align:center;font-size:11px;">Items</th>
                                <th style="background:#059669;color:white;padding:4px 8px;text-align:left;font-size:11px;">Received By</th>
                                <th style="background:#059669;color:white;padding:4px 8px;text-align:left;font-size:11px;">Date</th>
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
                    <div class="stamp-box" style="position:relative;">
                        <div class="stamp-title">Official Stamp</div>
                        <div class="stamp-name">BRAICK DISPENSARY</div>
                        <div class="stamp-line">Approved By: _________________</div>
                        <div class="stamp-date">Date: <?= date('F d, Y') ?></div>
                        <div style="margin-top:4px;padding-top:4px;border-top:2px dashed rgba(5,150,105,0.3);font-size:12px;font-weight:800;color:#059669;letter-spacing:2px;">
                            ✅ ALL PAID
                        </div>
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
            margin: [8, 8, 8, 8],
            filename: 'Paid_Bills_OTC_<?= date('Y-m-d') ?>.pdf',
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
                orientation: 'portrait' 
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

    console.log('%c✅ Braick - Paid Bills & OTC Sales', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c✅ Shows BOTH regular bills (with visit_id) AND OTC paid sales', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Regular bills from bills table (status=paid, visit_id IS NOT NULL)', 'font-size:13px; color:#0B5ED7;');
    console.log('%c✅ OTC sales from otc_sales table (payment_status=paid)', 'font-size:13px; color:#8B5CF6;');
    console.log('%c📋 Total Records: <?= $total_bills ?>', 'font-size:13px; color:#64748B;');
    console.log('%c💰 Total Amount: <?= $currency ?> <?= number_format($total_paid_amount, 0) ?>', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>