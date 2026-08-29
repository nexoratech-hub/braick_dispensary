<?php
// ================================================================
// FILE: frontend/pages/cashier/receipt_history.php
// CASHIER - RECEIPT HISTORY (GREEN THEME)
// FIXED: Uses bills table instead of patient_bills
// 8 CARDS DESIGN: 4 TOP + 4 BOTTOM
// WITH AUTO-UPDATE (3 SECONDS)
// USES SHARED HEADER WITH DARK MODE
// ALLOWS RECEPTION, CASHIER AND ADMIN
// BRAICK DISPENSARY
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
// ALLOWED ROLES: Cashier, Reception, Admin
// ================================================================
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

// ================================================================
// GET USER DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Cashier';
$user_role = $_SESSION['role'] ?? 'cashier';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// CHECK IF USER IS RECEPTION
// ================================================================
$is_reception = ($user_role === 'reception');

// ================================================================
// INCLUDE DATABASE
// ================================================================
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
// GET FILTER PARAMETERS
// ================================================================
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Initialize variables
$receipts = [];
$total_receipts = 0;
$total_amount = 0;

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
// BUILD DATE FILTER - Using receipts table with bills join
// ================================================================
$date_condition = "";
$params = [];

switch ($filter) {
    case 'today':
        $date_condition = "AND DATE(r.printed_at) = CURDATE()";
        break;
    case 'week':
        $date_condition = "AND r.printed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case 'month':
        $date_condition = "AND r.printed_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        break;
    case '3months':
        $date_condition = "AND r.printed_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
        break;
    case '6months':
        $date_condition = "AND r.printed_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
        break;
    case 'year':
        $date_condition = "AND r.printed_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        break;
    case 'custom':
        if (!empty($start_date) && !empty($end_date)) {
            $date_condition = "AND DATE(r.printed_at) BETWEEN ? AND ?";
            $params[] = $start_date;
            $params[] = $end_date;
        } else {
            $date_condition = "";
        }
        break;
    case 'all':
    default:
        $date_condition = "";
        break;
}

// ================================================================
// BUILD SEARCH CONDITION
// ================================================================
$search_condition = "";
if (!empty($search)) {
    $search_condition = "AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR r.receipt_number LIKE ? OR b.bill_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// ================================================================
// GET RECEIPTS - FIXED: Using bills table
// ================================================================
try {
    $sql = "
        SELECT 
            r.id,
            r.receipt_number,
            r.bill_id,
            r.patient_id,
            r.printed_by,
            r.printed_at,
            r.downloaded_at,
            r.receipt_data,
            b.bill_number,
            b.total_amount,
            b.paid_amount,
            b.branch_id,
            p.full_name as patient_name,
            p.patient_id as patient_code,
            p.phone,
            u.full_name as printed_by_name
        FROM receipts r
        LEFT JOIN bills b ON r.bill_id = b.id
        LEFT JOIN patients p ON r.patient_id = p.id
        LEFT JOIN users u ON r.printed_by = u.id
        WHERE 1=1
    ";
    
    // Add branch filter
    $sql .= " AND (b.branch_id = ? OR b.branch_id IS NULL)";
    $params[] = $user_branch_id;
    
    // Add date conditions
    if (!empty($date_condition)) {
        $sql .= " $date_condition";
    }
    
    // Add search conditions
    if (!empty($search_condition)) {
        $sql .= " $search_condition";
    }
    
    $sql .= " ORDER BY r.printed_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_receipts = count($receipts);
    
    // Calculate total amount
    $total_amount = 0;
    foreach ($receipts as $receipt) {
        $total_amount += $receipt['total_amount'] ?? 0;
    }
    
} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $receipts = [];
    $total_receipts = 0;
    $total_amount = 0;
    error_log("Receipt history error: " . $e->getMessage());
}

// ================================================================
// GET GLOBAL STATS FOR AUTO-UPDATE - Using bills table
// ================================================================
$today = date('Y-m-d');

try {
    // Today Payments
    $stmt = $db->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(paid_amount), 0) as total
        FROM bills 
        WHERE branch_id = ? 
        AND DATE(updated_at) = ?
        AND paid_amount > 0
        AND status IN ('paid', 'partial')
    ");
    $stmt->execute([$user_branch_id, $today]);
    $today_payments = $stmt->fetch(PDO::FETCH_ASSOC);
    $today_payments_count = $today_payments['count'] ?? 0;
    $today_payments_total = $today_payments['total'] ?? 0;

    // Pending Bills
    $stmt = $db->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total
        FROM bills 
        WHERE branch_id = ? AND status = 'pending'
    ");
    $stmt->execute([$user_branch_id]);
    $pending_bills = $stmt->fetch(PDO::FETCH_ASSOC);
    $pending_bills_count = $pending_bills['count'] ?? 0;
    $pending_bills_total = $pending_bills['total'] ?? 0;

    // Paid Bills
    $stmt = $db->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(paid_amount), 0) as total
        FROM bills 
        WHERE branch_id = ? AND status = 'paid'
    ");
    $stmt->execute([$user_branch_id]);
    $paid_bills = $stmt->fetch(PDO::FETCH_ASSOC);
    $paid_bills_count = $paid_bills['count'] ?? 0;
    $paid_bills_total = $paid_bills['total'] ?? 0;

    // Cancelled Bills
    $stmt = $db->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total
        FROM bills 
        WHERE branch_id = ? AND status = 'cancelled'
    ");
    $stmt->execute([$user_branch_id]);
    $cancelled_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $cancelled_bills_count = $cancelled_stats['count'] ?? 0;
    $cancelled_bills_total = $cancelled_stats['total'] ?? 0;

    // Total Bills
    $stmt = $db->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total
        FROM bills WHERE branch_id = ?
    ");
    $stmt->execute([$user_branch_id]);
    $total_bills = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_bills_count = $total_bills['count'] ?? 0;
    $total_bills_amount = $total_bills['total'] ?? 0;

    // Partial Bills
    $stmt = $db->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(paid_amount), 0) as total_paid, COALESCE(SUM(balance), 0) as total_balance
        FROM bills 
        WHERE branch_id = ? AND status = 'partial'
    ");
    $stmt->execute([$user_branch_id]);
    $partial_bills = $stmt->fetch(PDO::FETCH_ASSOC);
    $partial_bills_count = $partial_bills['count'] ?? 0;
    $partial_bills_paid = $partial_bills['total_paid'] ?? 0;
    $partial_bills_balance = $partial_bills['total_balance'] ?? 0;

    // Expenses
    $stmt = $db->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total
        FROM expenses 
        WHERE branch_id = ? AND status = 'paid'
    ");
    $stmt->execute([$user_branch_id]);
    $expenses = $stmt->fetch(PDO::FETCH_ASSOC);
    $expenses_count = $expenses['count'] ?? 0;
    $expenses_total = $expenses['total'] ?? 0;
    
    // Today's receipts count
    $stmt = $db->prepare("
        SELECT COUNT(*) as count
        FROM receipts 
        WHERE branch_id = ? AND DATE(printed_at) = ?
    ");
    $stmt->execute([$user_branch_id, $today]);
    $receipts_today = $stmt->fetch(PDO::FETCH_ASSOC);
    $today_receipts = $receipts_today['count'] ?? 0;
    
} catch (Exception $e) {
    error_log("Global stats error: " . $e->getMessage());
    $today_payments_count = 0;
    $today_payments_total = 0;
    $pending_bills_count = 0;
    $pending_bills_total = 0;
    $paid_bills_count = 0;
    $paid_bills_total = 0;
    $cancelled_bills_count = 0;
    $cancelled_bills_total = 0;
    $total_bills_count = 0;
    $total_bills_amount = 0;
    $partial_bills_count = 0;
    $partial_bills_paid = 0;
    $partial_bills_balance = 0;
    $expenses_count = 0;
    $expenses_total = 0;
    $today_receipts = 0;
}

// ================================================================
// LOGO PATH
// ================================================================
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/cashier_header.php';
include_once '../../components/cashier_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt History - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
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
            --danger-light: #F87171;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --purple: #7C3AED;
            --purple-bg: #EDE9FE;
            --pink: #DB2777;
            --pink-bg: #FCE4EC;
            --indigo: #4F46E5;
            --indigo-bg: #E0E7FF;
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
            --shadow-xl: 0 20px 25px rgba(0,0,0,0.1);
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --table-stripe: #E8F0FE;
            --table-hover: #D1FAE5;
        }
        
        /* DARK MODE */
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
        }

        /* Dark mode fixes */
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
        [data-theme="dark"] .stat-card:hover { border-color: #34D399; transform: translateY(-4px); }
        [data-theme="dark"] .card { background: #1E293B; border-color: #334155; }
        [data-theme="dark"] .card:hover { border-color: #34D399; }
        [data-theme="dark"] .page-header { background: linear-gradient(135deg, #059669, #047857) !important; }
        [data-theme="dark"] .footer { border-top-color: #334155; }
        [data-theme="dark"] .toast-custom.success { background: #059669; }
        [data-theme="dark"] .toast-custom.error { background: #DC2626; }
        [data-theme="dark"] .header-badge { background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.1); }
        [data-theme="dark"] .text-gray-400 { color: #94A3B8 !important; }
        [data-theme="dark"] .text-gray-500 { color: #94A3B8 !important; }
        [data-theme="dark"] .text-gray-600 { color: #94A3B8 !important; }
        [data-theme="dark"] .data-table thead th { color: white; }
        [data-theme="dark"] .font-mono.text-gray-700 { color: #CBD5E1 !important; }
        [data-theme="dark"] .font-semibold.text-green-600 { color: #34D399 !important; }
        [data-theme="dark"] .card-title { color: #F1F5F9 !important; }
        [data-theme="dark"] .footer .footer-brand { color: #34D399; }
        [data-theme="dark"] .footer .text-gray-300 { color: #475569 !important; }
        [data-theme="dark"] .role-badge-display { background: #1E3A5F; color: #6EA8FE; }
        [data-theme="dark"] .role-badge-display[style*="background:rgba(255,255,255,0.2)"] { background: rgba(255,255,255,0.2) !important; color: white !important; }
        [data-theme="dark"] .branch-badge-display { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .toast-custom { box-shadow: 0 20px 25px rgba(0,0,0,0.4); }
        [data-theme="dark"] .bg-green-100 { background-color: rgba(5, 150, 105, 0.15) !important; }
        [data-theme="dark"] .text-green-700 { color: #34D399 !important; }
        [data-theme="dark"] .border-green-200 { border-color: rgba(5, 150, 105, 0.3) !important; }
        [data-theme="dark"] .bg-red-100 { background-color: rgba(220, 38, 38, 0.15) !important; }
        [data-theme="dark"] .text-red-700 { color: #F87171 !important; }
        [data-theme="dark"] .border-red-200 { border-color: rgba(220, 38, 38, 0.3) !important; }
        [data-theme="dark"] .stat-card .stat-number { color: #F1F5F9 !important; }
        [data-theme="dark"] .stat-card .stat-number.green { color: #34D399 !important; }
        [data-theme="dark"] .stat-card .stat-number.red { color: #F87171 !important; }
        [data-theme="dark"] .stat-card .stat-number.blue { color: #6EA8FE !important; }
        [data-theme="dark"] .stat-card .stat-number.yellow { color: #FBBF24 !important; }
        [data-theme="dark"] .stat-card .stat-number.purple { color: #A78BFA !important; }
        [data-theme="dark"] .stat-card .stat-number.pink { color: #F472B6 !important; }
        [data-theme="dark"] .stat-card .stat-number.indigo { color: #818CF8 !important; }
        
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
        
        /* ================================================================
           MAIN CONTENT
           ================================================================ */
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        /* ================================================================
           PAGE HEADER - GREEN THEME
           ================================================================ */
        .page-header {
            background: linear-gradient(135deg, var(--success), var(--success-dark));
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 24px;
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
        
        .page-header .page-title i {
            font-size: 2rem;
            opacity: 0.9;
        }
        
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
        
        .page-header .page-subtitle strong {
            color: white;
            font-weight: 600;
        }
        
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
        
        /* ================================================================
           STATS CARDS - 4 TOP + 4 BOTTOM (8 CARDS TOTAL)
           ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            max-width: 1200px;
            margin: 0 auto 16px;
        }
        
        .stats-grid-bottom {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            max-width: 1200px;
            margin: 0 auto 20px;
        }
        
        .stat-card {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 16px 18px;
            border: 2px solid var(--border-color);
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            border-radius: 14px 14px 0 0;
        }
        
        .stat-card:hover {
            border-color: var(--success);
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }
        
        .stat-card .stat-icon {
            font-size: 1.6rem;
            margin-bottom: 4px;
            display: block;
        }
        
        .stat-card .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            line-height: 1.2;
            letter-spacing: -0.02em;
        }
        
        .stat-card .stat-number.green { color: var(--success); }
        .stat-card .stat-number.red { color: #DC2626; }
        .stat-card .stat-number.blue { color: var(--primary); }
        .stat-card .stat-number.yellow { color: var(--warning); }
        .stat-card .stat-number.purple { color: var(--purple); }
        .stat-card .stat-number.pink { color: #DB2777; }
        .stat-card .stat-number.indigo { color: #4F46E5; }
        
        .stat-card .stat-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 500;
            margin-top: 2px;
        }
        
        .stat-card .stat-sub {
            font-size: 0.6rem;
            color: var(--text-secondary);
            margin-top: 2px;
            opacity: 0.7;
        }
        
        /* Card accent colors */
        .stat-card.accent-blue::after { background: var(--primary); }
        .stat-card.accent-red::after { background: #DC2626; }
        .stat-card.accent-green::after { background: var(--success); }
        .stat-card.accent-yellow::after { background: var(--warning); }
        .stat-card.accent-purple::after { background: var(--purple); }
        .stat-card.accent-pink::after { background: #DB2777; }
        .stat-card.accent-indigo::after { background: #4F46E5; }
        .stat-card.accent-orange::after { background: #EA580C; }
        
        /* ================================================================
           FILTER SECTION
           ================================================================ */
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
        
        .filter-btn i {
            margin-right: 4px;
        }
        
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
        
        /* ================================================================
           DATE PICKER
           ================================================================ */
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
        
        /* ================================================================
           CARD
           ================================================================ */
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
        
        /* ================================================================
           TABLE
           ================================================================ */
        .table-wrap {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
            min-width: 700px;
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
        
        .data-table thead th:first-child {
            border-radius: 8px 0 0 0;
        }
        
        .data-table thead th:last-child {
            border-radius: 0 8px 0 0;
        }
        
        .data-table td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .data-table tbody tr:hover td {
            background: var(--table-hover);
        }
        
        /* ================================================================
           BUTTONS
           ================================================================ */
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
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
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
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        
        .btn-outline:hover {
            background: var(--bg-body);
            border-color: var(--success);
            color: var(--success);
        }
        
        .btn-sm { 
            padding: 4px 10px; 
            font-size: 0.65rem; 
            border-radius: 6px; 
        }
        
        /* ================================================================
           FOOTER
           ================================================================ */
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand { 
            color: var(--success); 
            font-weight: 600; 
        }
        
        /* ================================================================
           BADGES
           ================================================================ */
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
        
        /* ================================================================
           TOAST
           ================================================================ */
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
        
        .toast-custom.show {
            transform: translateY(0);
            opacity: 1;
        }
        
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .stats-grid-bottom { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .filter-section { padding: 12px 14px; }
            .filter-btn { font-size: 0.6rem; padding: 3px 10px; }
            .card { padding: 14px 16px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .stats-grid-bottom { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .stat-card { padding: 12px 14px; }
            .stat-card .stat-number { font-size: 1.4rem; }
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
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 6px; }
            .stats-grid-bottom { grid-template-columns: repeat(2, 1fr); gap: 6px; }
            .stat-card { padding: 8px 10px; }
            .stat-card .stat-number { font-size: 1.1rem; }
            .stat-card .stat-label { font-size: 0.55rem; }
            .stat-card .stat-icon { font-size: 1.2rem; }
        }
        
        @media (max-width: 400px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 4px; }
            .stats-grid-bottom { grid-template-columns: repeat(2, 1fr); gap: 4px; }
            .stat-card { padding: 6px 6px; }
            .stat-card .stat-number { font-size: 0.9rem; }
            .stat-card .stat-label { font-size: 0.5rem; }
            .stat-card .stat-icon { font-size: 1rem; }
        }
    </style>
    
    <!-- Preload dark mode from localStorage -->
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

<!-- TOP NAV is loaded from header -->

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-receipt"></i>
                Receipt History
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;"><?= strtoupper($user_role) ?></span>
                <?php if ($is_reception): ?>
                    <span class="role-badge-display" style="background:rgba(52,211,153,0.3);color:#34D399;border-color:rgba(52,211,153,0.3);">
                        <i class="fas fa-check-circle"></i> Full Access
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-history"></i>
                View all receipts in <strong><?= htmlspecialchars($user_branch_name) ?></strong>
                
                <span class="header-badge">
                    <i class="fas fa-receipt"></i>
                    <?= $total_receipts ?> Total Receipts
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
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="p-4 rounded-xl mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200' ?>" style="max-width:1200px;margin:0 auto 16px;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- 8 STATS CARDS - 4 TOP + 4 BOTTOM -->
    <!-- ================================================================ -->
    
    <!-- TOP 4 CARDS -->
    <div class="stats-grid" id="globalStatsTop">
        <!-- Card 1: Today Payments -->
        <div class="stat-card accent-blue" onclick="window.location.href='payment_history.php'">
            <span class="stat-icon">💳</span>
            <p class="stat-number blue" id="statTodayPayments"><?= number_format($today_payments_count) ?></p>
            <p class="stat-label">Today Payments</p>
            <p class="stat-sub">TSh <?= number_format($today_payments_total) ?></p>
        </div>
        
        <!-- Card 2: Pending Bills -->
        <div class="stat-card accent-red" onclick="window.location.href='pending_bills.php'">
            <span class="stat-icon">⏳</span>
            <p class="stat-number red" id="statPending"><?= number_format($pending_bills_count) ?></p>
            <p class="stat-label">Pending Bills</p>
            <p class="stat-sub">TSh <?= number_format($pending_bills_total) ?></p>
        </div>
        
        <!-- Card 3: Paid Bills -->
        <div class="stat-card accent-green" onclick="window.location.href='paid_bills.php'">
            <span class="stat-icon">✅</span>
            <p class="stat-number green" id="statPaid"><?= number_format($paid_bills_count) ?></p>
            <p class="stat-label">Paid Bills</p>
            <p class="stat-sub">TSh <?= number_format($paid_bills_total) ?></p>
        </div>
        
        <!-- Card 4: Cancelled Bills -->
        <div class="stat-card accent-red" onclick="window.location.href='cancelled_bills.php'">
            <span class="stat-icon">❌</span>
            <p class="stat-number red" id="statCancelled"><?= number_format($cancelled_bills_count) ?></p>
            <p class="stat-label">Cancelled Bills</p>
            <p class="stat-sub">TSh <?= number_format($cancelled_bills_total) ?></p>
        </div>
    </div>
    
    <!-- BOTTOM 4 CARDS -->
    <div class="stats-grid-bottom" id="globalStatsBottom">
        <!-- Card 5: Total Bills -->
        <div class="stat-card accent-purple" onclick="window.location.href='all_bills.php'">
            <span class="stat-icon">📋</span>
            <p class="stat-number purple" id="statTotal"><?= number_format($total_bills_count) ?></p>
            <p class="stat-label">Total Bills</p>
            <p class="stat-sub">TSh <?= number_format($total_bills_amount) ?></p>
        </div>
        
        <!-- Card 6: Partial Bills -->
        <div class="stat-card accent-yellow" onclick="window.location.href='partial_payments.php'">
            <span class="stat-icon">💰</span>
            <p class="stat-number yellow" id="statPartial"><?= number_format($partial_bills_count) ?></p>
            <p class="stat-label">Partial Bills</p>
            <p class="stat-sub">Paid: TSh <?= number_format($partial_bills_paid) ?></p>
        </div>
        
        <!-- Card 7: Expenses -->
        <div class="stat-card accent-pink" onclick="window.location.href='expenses.php'">
            <span class="stat-icon">💸</span>
            <p class="stat-number pink" id="statExpenses"><?= number_format($expenses_count) ?></p>
            <p class="stat-label">Expenses</p>
            <p class="stat-sub">TSh <?= number_format($expenses_total) ?></p>
        </div>
        
        <!-- Card 8: Receipts (Current Filter) -->
        <div class="stat-card accent-indigo" style="border-color:<?= $total_receipts > 0 ? '#4F46E5' : 'var(--border-color)' ?>;">
            <span class="stat-icon">🧾</span>
            <p class="stat-number indigo"><?= number_format($total_receipts) ?></p>
            <p class="stat-label">Filtered Receipts</p>
            <p class="stat-sub">TSh <?= number_format($total_amount) ?></p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
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
        
        <!-- ============================================================ -->
        <!-- DATE PICKER (Custom Range) -->
        <!-- ============================================================ -->
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

    <!-- ================================================================ -->
    <!-- RECEIPTS TABLE -->
    <!-- ================================================================ -->
    <div class="card" style="max-width:1200px;margin:0 auto;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list" style="color:var(--success);"></i> Receipt History
                <span class="text-sm font-normal text-gray-400">(<?= $total_receipts ?> receipts)</span>
            </h3>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                <span class="text-xs text-gray-400" id="liveTimestamp">
                    <i class="fas fa-clock"></i> Updated: <?= date('h:i:s A') ?>
                </span>
            </div>
        </div>
        
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Receipt #</th>
                        <th>Bill #</th>
                        <th>Patient</th>
                        <th>Amount</th>
                        <th>Printed By</th>
                        <th>Printed Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (is_array($receipts) && count($receipts) > 0): ?>
                        <?php $i = 1; foreach ($receipts as $receipt): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td>
                                    <span class="font-mono text-xs font-bold text-gray-700">
                                        <?= htmlspecialchars($receipt['receipt_number'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="font-mono text-xs text-gray-500">
                                        <?= htmlspecialchars($receipt['bill_number'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="font-medium text-sm">
                                        <?= htmlspecialchars($receipt['patient_name'] ?? 'Unknown Patient') ?>
                                    </div>
                                    <div class="text-xs text-gray-400">
                                        <?= htmlspecialchars($receipt['patient_code'] ?? $receipt['patient_id'] ?? 'N/A') ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="font-semibold text-green-600">
                                        <?= $currency ?> <?= number_format($receipt['total_amount'] ?? 0, 0) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="text-sm"><?= htmlspecialchars($receipt['printed_by_name'] ?? 'N/A') ?></span>
                                </td>
                                <td class="text-xs">
                                    <?= isset($receipt['printed_at']) ? date('d/m/Y', strtotime($receipt['printed_at'])) : 'N/A' ?>
                                    <br>
                                    <span class="text-gray-400 text-[0.6rem]">
                                        <?= isset($receipt['printed_at']) ? date('h:i A', strtotime($receipt['printed_at'])) : '' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="flex flex-wrap gap-1">
                                        <!-- View Bill -->
                                        <a href="view_bill.php?id=<?= $receipt['bill_id'] ?>" class="btn btn-primary btn-sm" title="View Bill">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <!-- Print Receipt -->
                                        <a href="print_receipt.php?bill_id=<?= $receipt['bill_id'] ?>&print=1" class="btn btn-success btn-sm" title="Print Receipt" target="_blank">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-8 text-gray-400">
                                <i class="fas fa-receipt text-3xl block mb-2 text-gray-300"></i>
                                <p class="text-lg">No receipts found</p>
                                <p class="text-sm">
                                    <?php if ($filter !== 'all'): ?>
                                        No receipts found for the selected date range
                                    <?php else: ?>
                                        No receipts have been generated yet. Print a receipt to see it here.
                                    <?php endif; ?>
                                </p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Receipt History
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

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT - AUTO UPDATE EVERY 3 SECONDS -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        
        toast.className = 'toast-custom ' + (type || 'info');
        toastTitle.textContent = title || 'Notification';
        toastMessage.textContent = message || '';
        toast.style.display = 'flex';
        
        setTimeout(function() {
            toast.classList.add('show');
        }, 50);
        
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
            btn.innerHTML = '<span class="spinner" style="display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,0.3);border-top-color:white;border-radius:50%;animation:spin 0.6s linear infinite;"></span> Loading...';
            btn.disabled = true;
        }
        
        fetchDashboardData();
        
        setTimeout(function() {
            if (btn) {
                btn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh';
                btn.disabled = false;
            }
            showToast('✅ Refreshed', 'Page data updated manually', 'success');
        }, 1500);
    }

    // ================================================================
    // FETCH DASHBOARD DATA (AJAX)
    // ================================================================
    function fetchDashboardData() {
        var url = 'get_dashboard_data.php?t=' + Date.now();
        
        fetch(url)
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    updateStats(data);
                } else {
                    console.error('Failed to fetch dashboard data:', data.message);
                }
            })
            .catch(function(error) {
                console.error('Fetch error:', error);
            });
    }

    // ================================================================
    // UPDATE STATS UI
    // ================================================================
    function updateStats(data) {
        // Update all 8 stat cards
        var statMap = {
            'statTodayPayments': data.today_payments_count || 0,
            'statPending': data.pending_bills || 0,
            'statPaid': data.paid_bills || 0,
            'statCancelled': data.cancelled_bills || 0,
            'statTotal': data.total_bills || 0,
            'statPartial': data.partial_bills || 0,
            'statExpenses': data.expenses_count || 0
        };
        
        for (var key in statMap) {
            var el = document.getElementById(key);
            if (el) {
                el.textContent = Number(statMap[key]).toLocaleString();
            }
        }
        
        // Update timestamp
        var ts = document.getElementById('liveTimestamp');
        if (ts) {
            var now = new Date();
            var timeStr = now.toLocaleTimeString('en-US', {
                hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
            });
            ts.innerHTML = '<i class="fas fa-clock"></i> Updated: ' + timeStr;
        }
        
        var footerTs = document.getElementById('footerTimestamp');
        if (footerTs) {
            var now = new Date();
            var timeStr = now.toLocaleTimeString('en-US', {
                hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
            });
            footerTs.textContent = 'Last updated: ' + timeStr;
        }
    }

    // ================================================================
    // AUTO UPDATE - EVERY 3 SECONDS
    // ================================================================
    var updateInterval = null;
    var isUpdating = false;
    
    function startAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
        }
        fetchDashboardData();
        updateInterval = setInterval(function() {
            if (!isUpdating) {
                isUpdating = true;
                fetchDashboardData();
                setTimeout(function() {
                    isUpdating = false;
                }, 1000);
            }
        }, 3000);
        console.log('%c🔄 Auto-update started (every 3s)', 'font-size:12px; color:#34D399;');
    }
    
    function stopAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
            updateInterval = null;
            console.log('%c⏹️ Auto-update stopped', 'font-size:12px; color:#DC2626;');
        }
    }

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopAutoUpdate();
        } else {
            startAutoUpdate();
        }
    });

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
            window.location.href = 'receipt_history.php?search=' + encodeURIComponent(query) + '&filter=' + filter + '&start_date=' + start_date + '&end_date=' + end_date;
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
    // ADD CSS ANIMATIONS
    // ================================================================
    var style = document.createElement('style');
    style.textContent = `
        @keyframes spin { to { transform: rotate(360deg); } }
        @keyframes pulse-dot { 
            0%, 100% { opacity: 1; transform: scale(1); } 
            50% { opacity: 0.5; transform: scale(0.8); } 
        }
        .stat-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .stat-card:hover { transform: translateY(-4px); }
        .filter-btn { transition: all 0.3s ease; }
        .data-table tbody tr { transition: background 0.2s ease; }
        .stat-number { transition: all 0.3s ease; }
    `;
    document.head.appendChild(style);

    // ================================================================
    // INIT
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            startAutoUpdate();
        }, 1000);
    });

    // ================================================================
    // CONSOLE
    // ================================================================
    console.log('%c🧾 Braick - Receipt History (FIXED - uses bills table)', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?> (<?= htmlspecialchars($user_role) ?>)', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($user_branch_name) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📋 Total Receipts: <?= $total_receipts ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📅 Filter: <?= ucfirst($filter) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c✅ 8 CARDS: 4 TOP + 4 BOTTOM', 'font-size:13px; color:#34D399;');
    console.log('%c✅ FIXED: Uses bills table instead of patient_bills', 'font-size:13px; color:#34D399;');
    console.log('%c🔄 Auto-update every 3 seconds', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>