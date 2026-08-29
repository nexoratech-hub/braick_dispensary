<?php
// ================================================================
// FILE: frontend/pages/cashier/cancelled_bills.php
// CASHIER - CANCELLED BILLS LIST (GREEN THEME)
// WITH CANCELLED ITEMS DISPLAY AND PDF EXPORT
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

$allowed_roles = ['cashier', 'reception', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: /dispensary_system/frontend/pages/doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: /dispensary_system/frontend/pages/pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: /dispensary_system/frontend/pages/laboratory/dashboard.php'); break;
        default: header('Location: /dispensary_system/frontend/pages/login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'User';
$user_role = $_SESSION['role'] ?? 'cashier';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
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

$message = '';
$message_type = '';
$currency = 'TSh';

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$cancelled_bills = [];
$total_cancelled = 0;
$total_amount = 0;
$all_cancelled_items = [];

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
    $search_condition = "AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR b.bill_number LIKE ? OR p.phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// ================================================================
// GET CANCELLED BILLS WITH ITEMS
// ================================================================
try {
    // Get cancelled bills
    $sql = "
        SELECT 
            b.*,
            p.full_name as patient_name,
            p.patient_id as patient_code,
            p.phone,
            u.full_name as created_by_name,
            (SELECT COUNT(*) FROM bill_items WHERE bill_id = b.id AND status = 'cancelled') as cancelled_items_count,
            (SELECT COUNT(*) FROM bill_items WHERE bill_id = b.id AND status != 'cancelled') as other_items_count
        FROM bills b
        JOIN patients p ON b.patient_id = p.id
        LEFT JOIN users u ON b.created_by = u.id
        WHERE b.branch_id = ? 
        AND b.status = 'cancelled'
        $date_condition
        $search_condition
        ORDER BY b.updated_at DESC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $cancelled_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_cancelled = count($cancelled_bills);
    $total_amount = 0;
    
    // Get cancelled items for each bill
    foreach ($cancelled_bills as $bill) {
        $total_amount += (float)($bill['total_amount'] ?? 0);
        
        $stmt = $db->prepare("
            SELECT * FROM bill_items 
            WHERE bill_id = ? AND status = 'cancelled'
            ORDER BY created_at DESC
        ");
        $stmt->execute([$bill['id']]);
        $all_cancelled_items[$bill['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $cancelled_bills = [];
    $total_cancelled = 0;
    $total_amount = 0;
    $all_cancelled_items = [];
}

// ================================================================
// GET GLOBAL STATS
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
    <title>Cancelled Bills - Braick Dispensary</title>
    
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
            --indigo: #4F46E5;
            --indigo-bg: #E0E7FF;
            --pink: #DB2777;
            --pink-bg: #FCE4EC;
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
        [data-theme="dark"] .page-header { background: linear-gradient(135deg, #DC2626, #7F1D1D) !important; }
        [data-theme="dark"] .footer { border-top-color: #334155; }
        [data-theme="dark"] .toast-custom.success { background: #059669; }
        [data-theme="dark"] .toast-custom.error { background: #DC2626; }
        [data-theme="dark"] .header-badge { background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.1); }
        [data-theme="dark"] .text-gray-400 { color: #94A3B8 !important; }
        [data-theme="dark"] .text-gray-500 { color: #94A3B8 !important; }
        [data-theme="dark"] .text-gray-600 { color: #94A3B8 !important; }
        [data-theme="dark"] .data-table thead th { color: white; }
        [data-theme="dark"] .font-mono.text-gray-700 { color: #CBD5E1 !important; }
        [data-theme="dark"] .font-semibold.text-gray-800 { color: #E2E8F0 !important; }
        [data-theme="dark"] .card-title { color: #F1F5F9 !important; }
        [data-theme="dark"] .footer .footer-brand { color: #34D399; }
        [data-theme="dark"] .footer .text-gray-300 { color: #475569 !important; }
        [data-theme="dark"] .status-badge.cancelled { background: #3A1A1A; color: #F87171; }
        [data-theme="dark"] .role-badge-display { background: #1E3A5F; color: #6EA8FE; }
        [data-theme="dark"] .role-badge-display[style*="background:rgba(255,255,255,0.2)"] { background: rgba(255,255,255,0.2) !important; color: white !important; }
        [data-theme="dark"] .branch-badge-display { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .toast-custom { box-shadow: 0 20px 25px rgba(0,0,0,0.4); }
        [data-theme="dark"] .dark-toggle-btn { background: #0F172A; border-color: #334155; color: #F1F5F9; }
        [data-theme="dark"] .dark-toggle-btn:hover { border-color: #34D399; background: #1E293B; }
        [data-theme="dark"] .icon-btn:hover { background: #0F172A; color: #34D399; }
        [data-theme="dark"] .bg-green-100 { background-color: rgba(5, 150, 105, 0.15) !important; }
        [data-theme="dark"] .text-green-700 { color: #34D399 !important; }
        [data-theme="dark"] .border-green-200 { border-color: rgba(5, 150, 105, 0.3) !important; }
        [data-theme="dark"] .bg-red-100 { background-color: rgba(220, 38, 38, 0.15) !important; }
        [data-theme="dark"] .text-red-700 { color: #F87171 !important; }
        [data-theme="dark"] .border-red-200 { border-color: rgba(220, 38, 38, 0.3) !important; }
        [data-theme="dark"] .stat-card .stat-number { color: #F1F5F9 !important; }
        [data-theme="dark"] .stat-card .stat-number.red { color: #F87171 !important; }
        [data-theme="dark"] .stat-card .stat-number.green { color: #34D399 !important; }
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
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        .page-header {
            background: linear-gradient(135deg, #DC2626, #B91C1C);
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 20px rgba(220, 38, 38, 0.25);
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
        
        /* Stats Grid */
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
        
        .stat-card .stat-icon { font-size: 1.6rem; margin-bottom: 4px; display: block; }
        .stat-card .stat-number { font-size: 1.8rem; font-weight: 700; line-height: 1.2; letter-spacing: -0.02em; }
        .stat-card .stat-number.red { color: #DC2626; }
        .stat-card .stat-number.green { color: var(--success); }
        .stat-card .stat-number.blue { color: var(--primary); }
        .stat-card .stat-number.yellow { color: var(--warning); }
        .stat-card .stat-number.purple { color: var(--purple); }
        .stat-card .stat-number.pink { color: #DB2777; }
        .stat-card .stat-number.indigo { color: #4F46E5; }
        .stat-card .stat-label { font-size: 0.7rem; color: var(--text-secondary); font-weight: 500; margin-top: 2px; }
        .stat-card .stat-sub { font-size: 0.6rem; color: var(--text-secondary); margin-top: 2px; opacity: 0.7; }
        
        .stat-card.accent-blue::after { background: var(--primary); }
        .stat-card.accent-red::after { background: #DC2626; }
        .stat-card.accent-green::after { background: var(--success); }
        .stat-card.accent-yellow::after { background: var(--warning); }
        .stat-card.accent-purple::after { background: var(--purple); }
        .stat-card.accent-pink::after { background: #DB2777; }
        .stat-card.accent-indigo::after { background: #4F46E5; }
        .stat-card.accent-orange::after { background: #EA580C; }
        
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
        
        .data-table .cancelled-row td {
            background: var(--danger-bg);
            opacity: 0.85;
        }
        
        .data-table .cancelled-row:hover td {
            background: var(--danger-bg);
        }
        
        .status-badge {
            display: inline-block;
            padding: 3px 14px;
            border-radius: 12px;
            font-size: 0.6rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-badge.cancelled {
            background: #FEE2E2;
            color: #DC2626;
        }
        
        .status-badge.cancelled-item {
            background: #FEE2E2;
            color: #DC2626;
            font-size: 0.5rem;
            padding: 1px 8px;
            border-radius: 8px;
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
        
        .btn-sm { padding: 4px 10px; font-size: 0.65rem; border-radius: 6px; }
        
        .expand-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--primary);
            font-size: 0.7rem;
            padding: 2px 10px;
            border-radius: 6px;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--bg-body);
            border: 1px solid var(--border-color);
        }
        .expand-btn:hover {
            background: var(--primary-bg);
            border-color: var(--primary);
        }
        .expand-btn .badge-count {
            background: var(--danger);
            color: white;
            border-radius: 50%;
            padding: 0 6px;
            font-size: 0.55rem;
            font-weight: 700;
            min-width: 18px;
            text-align: center;
        }
        
        .items-container {
            padding: 6px 14px 6px 30px;
            border-top: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);
            background: var(--bg-body);
        }
        
        .items-detail-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
        }
        
        .items-detail-table thead th {
            text-align: left;
            padding: 4px 10px;
            font-weight: 600;
            font-size: 0.55rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-secondary);
            background: var(--bg-body);
            border-bottom: 2px solid var(--border-color);
            white-space: nowrap;
        }
        
        .items-detail-table tbody td {
            padding: 4px 10px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .items-detail-table tbody tr.cancelled-item-row td {
            background: var(--danger-bg);
        }
        
        .items-detail-table .items-total-row td {
            font-weight: 700;
            border-top: 2px solid var(--danger);
            background: var(--danger-bg);
            padding: 6px 10px;
        }
        
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
            background: linear-gradient(135deg, #DC2626, #B91C1C);
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
            border-bottom: 3px solid #DC2626;
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
            color: #DC2626;
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
            color: #DC2626;
            margin-top: 4px;
            background: #FEE2E2;
            padding: 4px 16px;
            border-radius: 20px;
            display: inline-block;
        }
        
        .pdf-content .pdf-section-title {
            font-weight: 700;
            font-size: 0.95rem;
            color: #DC2626;
            border-bottom: 2px solid #F87171;
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
            background: #DC2626;
            color: white;
            padding: 4px 10px;
            text-align: left;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 700;
            border: 1px solid #B91C1C;
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
            border: 3px solid #DC2626;
            border-radius: 10px;
            background: #FEE2E2;
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
            color: #DC2626;
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
</head>
<body>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-times-circle"></i>
                Cancelled Bills
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;"><?= strtoupper($user_role) ?></span>
                <?php if ($is_reception): ?>
                    <span class="role-badge-display" style="background:rgba(251,191,36,0.3);color:#FCD34D;border:1px solid rgba(251,191,36,0.2);">
                        <i class="fas fa-eye"></i> Reception
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-file-invoice"></i>
                View cancelled bills in <strong><?= htmlspecialchars($user_branch_name) ?></strong>
                
                <span class="header-badge">
                    <i class="fas fa-file-invoice"></i>
                    <?= $total_cancelled ?> Cancelled Bills
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

    <?php if ($message): ?>
        <div class="p-4 rounded-xl mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200' ?>" style="max-width:1200px;margin:0 auto 16px;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- TOP 4 CARDS -->
    <div class="stats-grid" id="globalStatsTop">
        <div class="stat-card accent-blue" onclick="window.location.href='payment_history.php'">
            <span class="stat-icon">💳</span>
            <p class="stat-number blue" id="statTodayPayments"><?= number_format($today_payments_count) ?></p>
            <p class="stat-label">Today Payments</p>
            <p class="stat-sub">TSh <?= number_format($today_payments_total) ?></p>
        </div>
        <div class="stat-card accent-red" onclick="window.location.href='pending_bills.php'">
            <span class="stat-icon">⏳</span>
            <p class="stat-number red" id="statPending"><?= number_format($pending_bills_count) ?></p>
            <p class="stat-label">Pending Bills</p>
            <p class="stat-sub">TSh <?= number_format($pending_bills_total) ?></p>
        </div>
        <div class="stat-card accent-green" onclick="window.location.href='paid_bills.php'">
            <span class="stat-icon">✅</span>
            <p class="stat-number green" id="statPaid"><?= number_format($paid_bills_count) ?></p>
            <p class="stat-label">Paid Bills</p>
            <p class="stat-sub">TSh <?= number_format($paid_bills_total) ?></p>
        </div>
        <div class="stat-card accent-red" onclick="window.location.href='cancelled_bills.php'">
            <span class="stat-icon">❌</span>
            <p class="stat-number red" id="statCancelled"><?= number_format($cancelled_bills_count) ?></p>
            <p class="stat-label">Cancelled Bills</p>
            <p class="stat-sub">TSh <?= number_format($cancelled_bills_total) ?></p>
        </div>
    </div>
    
    <!-- BOTTOM 4 CARDS -->
    <div class="stats-grid-bottom" id="globalStatsBottom">
        <div class="stat-card accent-purple" onclick="window.location.href='all_bills.php'">
            <span class="stat-icon">📋</span>
            <p class="stat-number purple" id="statTotal"><?= number_format($total_bills_count) ?></p>
            <p class="stat-label">Total Bills</p>
            <p class="stat-sub">TSh <?= number_format($total_bills_amount) ?></p>
        </div>
        <div class="stat-card accent-yellow" onclick="window.location.href='partial_payments.php'">
            <span class="stat-icon">💰</span>
            <p class="stat-number yellow" id="statPartial"><?= number_format($partial_bills_count) ?></p>
            <p class="stat-label">Partial Bills</p>
            <p class="stat-sub">Paid: TSh <?= number_format($partial_bills_paid) ?></p>
        </div>
        <div class="stat-card accent-pink" onclick="window.location.href='expenses.php'">
            <span class="stat-icon">💸</span>
            <p class="stat-number pink" id="statExpenses"><?= number_format($expenses_count) ?></p>
            <p class="stat-label">Expenses</p>
            <p class="stat-sub">TSh <?= number_format($expenses_total) ?></p>
        </div>
        <div class="stat-card accent-orange" style="border-color:#DC2626;">
            <span class="stat-icon">📊</span>
            <p class="stat-number red"><?= number_format($total_cancelled) ?></p>
            <p class="stat-label">Filtered Cancelled</p>
            <p class="stat-sub">TSh <?= number_format($total_amount) ?></p>
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

    <!-- CANCELLED BILLS TABLE -->
    <div class="card" style="max-width:1200px;margin:0 auto;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list" style="color:#DC2626;"></i> Cancelled Bills List
                <span class="text-sm font-normal text-gray-400">(<?= $total_cancelled ?> bills)</span>
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
                        <th>Bill #</th>
                        <th>Patient</th>
                        <th>Patient ID</th>
                        <th>Total Amount</th>
                        <th>Items</th>
                        <th>Created By</th>
                        <th>Status</th>
                        <th>Cancelled Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="billsTableBody">
                    <?php if (is_array($cancelled_bills) && count($cancelled_bills) > 0): ?>
                        <?php $i = 1; foreach ($cancelled_bills as $bill): 
                            $cancelled_items = $all_cancelled_items[$bill['id']] ?? [];
                            $cancelled_count = count($cancelled_items);
                        ?>
                            <tr class="cancelled-row">
                                <td><?= $i++ ?></td>
                                <td>
                                    <span class="font-mono text-xs font-bold text-gray-700">
                                        <?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="font-medium text-sm"><?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?></div>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($bill['phone'] ?? 'No phone') ?></div>
                                </td>
                                <td>
                                    <span class="text-xs font-mono"><?= htmlspecialchars($bill['patient_code'] ?? 'N/A') ?></span>
                                </td>
                                <td>
                                    <span class="font-semibold text-gray-800">
                                        <?= $currency ?> <?= number_format($bill['total_amount'] ?? 0, 0) ?>
                                    </span>
                                </td>
                                <td style="text-align:center;">
                                    <?php if ($cancelled_count > 0): ?>
                                        <button class="expand-btn" onclick="toggleItems(this)" 
                                                id="items-btn-<?= $bill['id'] ?>" 
                                                data-count="<?= $cancelled_count ?>">
                                            <i class="fas fa-chevron-right" id="items-icon-<?= $bill['id'] ?>"></i>
                                            <span>Show Items</span>
                                            <span class="badge-count"><?= $cancelled_count ?></span>
                                        </button>
                                        <div class="items-container" id="items-container-<?= $bill['id'] ?>" style="display:none;">
                                            <table class="items-detail-table">
                                                <thead>
                                                    <tr>
                                                        <th style="width:30%;">Item Name</th>
                                                        <th style="width:12%;">Type</th>
                                                        <th style="width:8%; text-align:center;">Qty</th>
                                                        <th style="width:15%; text-align:right;">Unit Price</th>
                                                        <th style="width:20%; text-align:right;">Total</th>
                                                        <th style="width:15%; text-align:center;">Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($cancelled_items as $item): 
                                                        $price = (float)($item['total_price'] ?? $item['unit_price'] ?? 0);
                                                        $unit_price = (float)($item['unit_price'] ?? $item['total_price'] ?? 0);
                                                        $qty = (int)($item['quantity'] ?? 1);
                                                        if ($unit_price == 0 && $qty > 0) {
                                                            $unit_price = $price / $qty;
                                                        }
                                                    ?>
                                                        <tr class="cancelled-item-row">
                                                            <td>
                                                                <strong><?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></strong>
                                                                <?php if (!empty($item['description'])): ?>
                                                                    <br><small style="color:var(--text-secondary);"><?= htmlspecialchars($item['description']) ?></small>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <span style="font-size:0.55rem; background:var(--bg-body); padding:1px 8px; border-radius:4px; border:1px solid var(--border-color);">
                                                                    <?= ucfirst($item['item_type'] ?? 'item') ?>
                                                                </span>
                                                            </td>
                                                            <td style="text-align:center;"><?= $qty ?></td>
                                                            <td style="text-align:right; font-family:monospace;">
                                                                <?= $currency ?> <?= number_format($unit_price, 0) ?>
                                                            </td>
                                                            <td style="text-align:right; font-family:monospace; font-weight:600; color:var(--danger); text-decoration:line-through;">
                                                                <?= $currency ?> <?= number_format($price, 0) ?>
                                                            </td>
                                                            <td style="text-align:center;">
                                                                <span class="status-badge cancelled-item">❌ Cancelled</span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                                <tfoot>
                                                    <tr class="items-total-row">
                                                        <td colspan="4" style="text-align:right; font-weight:700; color:var(--text-primary);">
                                                            <i class="fas fa-calculator"></i> Cancelled Items Total:
                                                        </td>
                                                        <td style="text-align:right; font-weight:700; color:var(--danger); font-family:monospace; font-size:0.9rem; text-decoration:line-through;">
                                                            <?= $currency ?> <?= number_format($bill['total_amount'] ?? 0, 0) ?>
                                                        </td>
                                                        <td></td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">No items</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="text-sm"><?= htmlspecialchars($bill['created_by_name'] ?? 'N/A') ?></span>
                                </td>
                                <td>
                                    <span class="status-badge cancelled">
                                        Cancelled
                                    </span>
                                </td>
                                <td class="text-xs">
                                    <?= isset($bill['updated_at']) ? date('d/m/Y', strtotime($bill['updated_at'])) : 'N/A' ?>
                                    <br>
                                    <span class="text-gray-400 text-[0.6rem]">
                                        <?= isset($bill['updated_at']) ? date('h:i A', strtotime($bill['updated_at'])) : '' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="flex flex-wrap gap-1">
                                        <a href="view_bill.php?id=<?= $bill['id'] ?>" class="btn btn-primary btn-sm" title="View Bill">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="text-center py-8 text-gray-400">
                                <i class="fas fa-check-circle text-3xl block mb-2 text-green-500"></i>
                                <p class="text-lg">No cancelled bills</p>
                                <p class="text-sm">
                                    <?php if ($filter !== 'all'): ?>
                                        No cancelled bills found for the selected date range
                                    <?php else: ?>
                                        All bills are active
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
            Cancelled Bills
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
                Cancelled Bills Report
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
    // TOGGLE ITEMS EXPAND
    // ================================================================
    function toggleItems(element) {
        var billId = element.id.replace('items-btn-', '');
        var container = document.getElementById('items-container-' + billId);
        var icon = document.getElementById('items-icon-' + billId);
        if (!icon) return;
        
        if (container) {
            if (container.style.display === 'none' || container.style.display === '') {
                container.style.display = 'block';
                container.classList.add('open');
                icon.className = 'fas fa-chevron-down';
                var text = element.querySelector('span:not(.badge-count):not([style])');
                if (text) text.textContent = ' Hide Items';
            } else {
                container.style.display = 'none';
                container.classList.remove('open');
                icon.className = 'fas fa-chevron-right';
                var text = element.querySelector('span:not(.badge-count):not([style])');
                if (text) text.textContent = ' Show Items';
            }
        }
    }

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
    // PDF GENERATION
    // ================================================================
    function generatePDF() {
        var modal = document.getElementById('pdfModal');
        var content = document.getElementById('pdfContent');
        
        var adminPhones = '<?= !empty($admin_phones) ? implode(' | ', $admin_phones) : ($branch_phone ?? '+255 700 000 001') ?>';
        var currency = '<?= $currency ?>';
        var branchName = '<?= htmlspecialchars($user_branch_name) ?>';
        var totalCancelled = <?= $total_cancelled ?>;
        var totalAmount = <?= $total_amount ?>;
        var filterLabel = '<?= $filter ?>';
        var filterDisplay = filterLabel === 'all' ? 'All Time' : filterLabel;
        
        var billsHtml = '';
        var counter = 1;
        <?php foreach ($cancelled_bills as $bill): 
            $cancelled_items = $all_cancelled_items[$bill['id']] ?? [];
        ?>
            billsHtml += `
                <tr>
                    <td style="padding:3px 8px;border-bottom:1px solid #E2E8F0;text-align:center;font-size:13px;">${counter}</td>
                    <td style="padding:3px 8px;border-bottom:1px solid #E2E8F0;font-size:13px;font-weight:600;color:#DC2626;text-decoration:line-through;"><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></td>
                    <td style="padding:3px 8px;border-bottom:1px solid #E2E8F0;font-size:13px;"><strong><?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?></strong><br><span style="font-size:12px;color:#64748B;"><?= htmlspecialchars($bill['patient_code'] ?? 'N/A') ?></span></td>
                    <td style="padding:3px 8px;border-bottom:1px solid #E2E8F0;text-align:right;font-size:13px;color:#DC2626;text-decoration:line-through;">${currency} <?= number_format($bill['total_amount'] ?? 0, 0) ?></td>
                    <td style="padding:3px 8px;border-bottom:1px solid #E2E8F0;font-size:13px;"><?= $bill['created_by_name'] ?? 'N/A' ?></td>
                    <td style="padding:3px 8px;border-bottom:1px solid #E2E8F0;font-size:13px;"><?= isset($bill['updated_at']) ? date('d/m/Y h:i A', strtotime($bill['updated_at'])) : 'N/A' ?></td>
                </tr>
                <?php if (!empty($cancelled_items)): ?>
                <tr>
                    <td colspan="6" style="padding:3px 8px;background:#FEF2F2;font-size:12px;color:#64748B;">
                        <strong>Cancelled Items:</strong>
                        <ul style="margin:2px 0 0 14px;padding:0;">
                            <?php foreach ($cancelled_items as $item): ?>
                            <li style="list-style:disc;color:#DC2626;text-decoration:line-through;">
                                <?= htmlspecialchars($item['item_name'] ?? 'N/A') ?> 
                                (<?= $currency ?> <?= number_format($item['total_price'] ?? 0, 0) ?>)
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </td>
                </tr>
                <?php endif; ?>
            `;
            counter++;
        <?php endforeach; ?>
        
        if (!billsHtml) {
            billsHtml = `<tr><td colspan="6" style="text-align:center;padding:20px;font-size:14px;color:#64748B;">No cancelled bills found</td></tr>`;
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
                    ❌ Cancelled Bills Report
                </div>
            </div>
            
            <!-- SUMMARY -->
            <div style="margin-bottom:8px;">
                <div class="pdf-section-title"><i class="fas fa-chart-bar"></i> Summary</div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin:4px 0;">
                    <div style="background:#FEE2E2;padding:8px 12px;border-radius:8px;text-align:center;border:1px solid #DC2626;">
                        <div style="font-size:20px;font-weight:700;color:#DC2626;">${totalCancelled}</div>
                        <div style="font-size:10px;color:#64748B;text-transform:uppercase;font-weight:600;">📋 Total Cancelled</div>
                    </div>
                    <div style="background:#E8F0FE;padding:8px 12px;border-radius:8px;text-align:center;border:1px solid #0B5ED7;">
                        <div style="font-size:20px;font-weight:700;color:#0B5ED7;">${filterDisplay}</div>
                        <div style="font-size:10px;color:#64748B;text-transform:uppercase;font-weight:600;">📅 Date Range</div>
                    </div>
                    <div style="background:#FEE2E2;padding:8px 12px;border-radius:8px;text-align:center;border:1px solid #DC2626;">
                        <div style="font-size:20px;font-weight:700;color:#DC2626;text-decoration:line-through;">${currency} ${totalAmount.toLocaleString()}</div>
                        <div style="font-size:10px;color:#64748B;text-transform:uppercase;font-weight:600;">💰 Cancelled Amount</div>
                    </div>
                </div>
            </div>
            
            <!-- CANCELLED BILLS TABLE -->
            <div style="margin-bottom:8px;">
                <div class="pdf-section-title"><i class="fas fa-list"></i> Cancelled Bills (${totalCancelled})</div>
                <div class="pdf-table-wrap">
                    <table class="pdf-table" style="font-size:13px;width:100%;border-collapse:collapse;">
                        <thead>
                            <tr>
                                <th style="background:#DC2626;color:white;padding:4px 8px;text-align:center;font-size:11px;">#</th>
                                <th style="background:#DC2626;color:white;padding:4px 8px;text-align:left;font-size:11px;">Bill #</th>
                                <th style="background:#DC2626;color:white;padding:4px 8px;text-align:left;font-size:11px;">Patient</th>
                                <th style="background:#DC2626;color:white;padding:4px 8px;text-align:right;font-size:11px;">Amount</th>
                                <th style="background:#DC2626;color:white;padding:4px 8px;text-align:left;font-size:11px;">Cancelled By</th>
                                <th style="background:#DC2626;color:white;padding:4px 8px;text-align:left;font-size:11px;">Date</th>
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
            margin: [8, 8, 8, 8],
            filename: 'Cancelled_Bills_<?= date('Y-m-d') ?>.pdf',
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
    // INIT
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            startAutoUpdate();
        }, 1000);
    });

    console.log('%c❌ Braick - Cancelled Bills (With Items & PDF)', 'font-size:18px; font-weight:bold; color:#DC2626;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?> (<?= htmlspecialchars($user_role) ?>)', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📋 Total Cancelled: <?= $total_cancelled ?>', 'font-size:13px; color:#64748B;');
    console.log('%c✅ Shows cancelled items for each bill', 'font-size:13px; color:#34D399;');
    console.log('%c✅ PDF Export with Official Stamp', 'font-size:13px; color:#34D399;');
    console.log('%c📞 Admin Contacts: <?= !empty($admin_phones) ? implode(' | ', $admin_phones) : ($branch_phone ?? '+255 700 000 001') ?>', 'font-size:13px; color:#D97706;');
</script>

</body>
</html>