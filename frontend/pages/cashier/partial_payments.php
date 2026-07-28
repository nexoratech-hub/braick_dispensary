<?php
// ================================================================
// FILE: frontend/pages/cashier/partial_payments.php
// CASHIER - PARTIAL PAYMENTS LIST
// FIXED: Uses shared header with clock
// FIXED: Dark mode fully working with header
// FIXED: Table format with expandable rows
// FIXED: Green header background
// FIXED: Pay button redirects to process_payment.php
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION - Default to cashier (ID: 7)
// ================================================================
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 7;
    $_SESSION['full_name'] = 'Cashier User';
    $_SESSION['role'] = 'cashier';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'cashier';
    $_SESSION['email'] = 'cashier@braick.com';
    $_SESSION['phone'] = '+255 700 000 007';
    $_SESSION['is_admin'] = false;
    $_SESSION['profile_pic'] = '';
}

// ================================================================
// ALLOW RECEPTION TO ACCESS CASHIER PAGES
// ================================================================
$allowed_roles = ['cashier', 'reception', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: ../' . $_SESSION['role'] . '/dashboard.php');
    exit;
}

// ================================================================
// CHECK IF USER IS ADMIN
// ================================================================
$is_admin = ($_SESSION['role'] === 'admin' || $_SESSION['is_admin'] === true);

// ================================================================
// PATH SAHIHI
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

$user_branch_id = $_SESSION['branch_id'] ?? 1;
$selected_branch_id = $user_branch_id;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';

// ================================================================
// GET FILTERS
// ================================================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'partial';

// ================================================================
// GET PARTIAL PAYMENTS
// ================================================================
try {
    $db = getDB();
    
    $query = "
        SELECT 
            pb.id,
            pb.bill_number,
            pb.patient_id,
            pb.visit_id,
            pb.total_amount,
            pb.paid_amount,
            pb.balance,
            pb.status,
            pb.created_at,
            p.full_name as patient_name,
            p.patient_id as patient_code,
            p.phone as patient_phone,
            v.visit_number,
            u.full_name as created_by_name,
            (SELECT COUNT(*) FROM bill_items WHERE bill_id = pb.id AND status != 'cancelled') as item_count
        FROM patient_bills pb
        JOIN patients p ON pb.patient_id = p.id
        LEFT JOIN visits v ON pb.visit_id = v.id
        LEFT JOIN users u ON pb.created_by = u.id
        WHERE pb.branch_id = ? 
    ";

    $params = [$selected_branch_id];

    // Status filter
    if ($status_filter === 'partial') {
        $query .= " AND pb.status = 'partial'";
    } elseif ($status_filter === 'pending') {
        $query .= " AND pb.status = 'pending'";
    } else {
        $query .= " AND pb.status IN ('pending', 'partial')";
    }

    if (!empty($search)) {
        $query .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR pb.bill_number LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }

    if (!empty($date_filter)) {
        $query .= " AND DATE(pb.created_at) = ?";
        $params[] = $date_filter;
    }

    $query .= " ORDER BY pb.created_at DESC";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $payments = $stmt->fetchAll();

    // ================================================================
    // GET BILL ITEMS FOR EACH BILL
    // ================================================================
    $bill_items = [];
    foreach ($payments as $payment) {
        $stmt = $db->prepare("
            SELECT id, item_name, item_type, quantity, unit_price, total_price, payment_status, status, created_at
            FROM bill_items 
            WHERE bill_id = ? AND status != 'cancelled'
            ORDER BY created_at DESC
        ");
        $stmt->execute([$payment['id']]);
        $items = $stmt->fetchAll();
        $bill_items[$payment['id']] = $items;
    }

    // ================================================================
    // GET COUNTS
    // ================================================================
    $stmt = $db->prepare("
        SELECT COUNT(*) as total FROM patient_bills 
        WHERE branch_id = ? AND status IN ('pending', 'partial')
    ");
    $stmt->execute([$selected_branch_id]);
    $total_partial = $stmt->fetch()['total'] ?? 0;

    $today = date('Y-m-d');
    $stmt = $db->prepare("
        SELECT COUNT(*) as total, COALESCE(SUM(balance), 0) as total_balance 
        FROM patient_bills 
        WHERE branch_id = ? AND status IN ('pending', 'partial') AND DATE(created_at) = ?
    ");
    $stmt->execute([$selected_branch_id, $today]);
    $today_stats = $stmt->fetch();
    $today_total = $today_stats['total'] ?? 0;
    $today_balance = $today_stats['total_balance'] ?? 0;

    // ================================================================
    // GET SYSTEM SETTINGS
    // ================================================================
    $settings = [];
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $currency = $settings['currency'] ?? 'TSh';

} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
    $payments = [];
    $bill_items = [];
    $total_partial = 0;
    $today_total = 0;
    $today_balance = 0;
    $currency = 'TSh';
    error_log("Partial payments error: " . $e->getMessage());
}

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
    <title>Partial Payments - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           ROOT VARIABLES - LIGHT MODE (DEFAULT)
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
            --toast-bg: #FFFFFF;
            --toast-text: #1E293B;
            --input-bg: #FFFFFF;
            --input-border: #E2E8F0;
            --input-text: #1E293B;
            --empty-state-color: #64748B;
            --footer-border: #E2E8F0;
            --badge-pending-bg: #FEF3C7;
            --badge-pending-text: #D97706;
            --badge-partial-bg: #E8F0FE;
            --badge-partial-text: #0B5ED7;
            --badge-paid-bg: #D1FAE5;
            --badge-paid-text: #059669;
            --detail-bg: #F8FAFC;
            --detail-border: #E2E8F0;
        }
        
        /* ================================================================
           ROOT VARIABLES - DARK MODE
           ================================================================ */
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
            --toast-bg: #1E293B;
            --toast-text: #F1F5F9;
            --input-bg: #1E293B;
            --input-border: #334155;
            --input-text: #F1F5F9;
            --empty-state-color: #94A3B8;
            --footer-border: #334155;
            --badge-pending-bg: #3D2E0A;
            --badge-pending-text: #FBBF24;
            --badge-partial-bg: #1E3A5F;
            --badge-partial-text: #6EA8FE;
            --badge-paid-bg: #1A3A2A;
            --badge-paid-text: #34D399;
            --detail-bg: #1E293B;
            --detail-border: #334155;
            --primary-bg: #1E3A5F;
            --success-bg: #1A3A2A;
            --danger-bg: #3A1A1A;
            --warning-bg: #3D2E0A;
            --purple-bg: #2D1B5F;
        }
        
        /* ================================================================
           GLOBAL STYLES
           ================================================================ */
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
           MAIN CONTENT OVERRIDE
           ================================================================ */
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
            transition: background 0.3s ease;
        }
        
        /* ================================================================
           PAGE HEADER
           ================================================================ */
        .page-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.25);
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
           STATS CARDS
           ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 14px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 14px 18px;
            border: 2px solid var(--border-color);
            text-align: center;
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
        }
        .stat-card .number {
            font-size: 1.6rem;
            font-weight: 700;
            line-height: 1.2;
        }
        .stat-card .number.pending { color: var(--warning); }
        .stat-card .number.partial { color: var(--primary); }
        .stat-card .number.total { color: var(--purple); }
        .stat-card .number.balance { color: var(--danger); }
        .stat-card .label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        /* ================================================================
           FILTERS
           ================================================================ */
        .filter-section {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 16px 20px;
            border: 1px solid var(--border-color);
            margin-bottom: 20px;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
        }
        
        .filter-section:hover {
            border-color: var(--success);
            box-shadow: var(--shadow-md);
        }
        
        .filter-group {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
        }
        
        .filter-group .filter-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-right: 4px;
        }
        
        .form-control {
            padding: 4px 10px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.75rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--success);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
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
        
        .btn-pay {
            background: var(--success);
            color: white;
        }
        .btn-pay:hover {
            background: var(--success-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        
        /* ================================================================
           TABLE STYLES - GREEN HEADERS
           ================================================================ */
        .table-wrapper {
            background: var(--bg-card);
            border-radius: 14px;
            border: 1px solid var(--border-color);
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }
        
        .table-wrapper .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 20px;
            background: var(--gray-50);
            border-bottom: 2px solid var(--border-color);
            flex-wrap: wrap;
            gap: 8px;
            transition: background 0.3s ease;
        }
        [data-theme="dark"] .table-wrapper .table-header {
            background: var(--gray-700);
        }
        
        .table-wrapper .table-header .table-title {
            font-weight: 600;
            font-size: 0.95rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .table-wrapper .table-header .table-title i {
            color: var(--success);
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
        }
        
        /* GREEN HEADER BACKGROUND */
        .data-table thead th {
            text-align: left;
            padding: 10px 14px;
            font-weight: 700;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #ffffff;
            background: var(--success);
            border-bottom: 3px solid var(--success-dark);
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 5;
        }
        
        .data-table td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .data-table tbody tr {
            transition: background 0.2s ease;
            cursor: pointer;
        }
        .data-table tbody tr:hover {
            background: var(--table-hover);
        }
        
        .data-table tbody tr.expanded {
            background: var(--primary-bg);
        }
        
        .data-table tbody tr.detail-row td {
            padding: 0;
            border-bottom: none;
        }
        
        .data-table tbody tr.detail-row .detail-container {
            padding: 12px 20px;
            background: var(--detail-bg);
            border-top: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);
            transition: background 0.3s ease;
        }
        
        .detail-container .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.78rem;
        }
        .detail-container .items-table thead th {
            text-align: left;
            padding: 6px 12px;
            font-weight: 600;
            font-size: 0.6rem;
            text-transform: uppercase;
            color: var(--text-secondary);
            background: var(--gray-100);
            border-bottom: 1px solid var(--border-color);
        }
        [data-theme="dark"] .detail-container .items-table thead th {
            background: var(--gray-600);
        }
        .detail-container .items-table tbody td {
            padding: 5px 12px;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.75rem;
            color: var(--text-primary);
        }
        .detail-container .items-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .detail-container .detail-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 10px;
            margin-top: 8px;
            border-top: 1px solid var(--border-color);
            flex-wrap: wrap;
            gap: 8px;
        }
        
        /* ================================================================
           STATUS BADGES
           ================================================================ */
        .status-badge {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 12px;
            border-radius: 12px;
        }
        .status-badge.pending {
            background: var(--badge-pending-bg);
            color: var(--badge-pending-text);
        }
        .status-badge.partial {
            background: var(--badge-partial-bg);
            color: var(--badge-partial-text);
        }
        .status-badge.paid {
            background: var(--badge-paid-bg);
            color: var(--badge-paid-text);
        }
        
        .item-type-badge {
            font-size: 0.5rem;
            font-weight: 600;
            padding: 1px 8px;
            border-radius: 8px;
        }
        .item-type-badge.consultation { background: #E8F0FE; color: #0B5ED7; }
        .item-type-badge.lab_test { background: var(--purple-bg); color: var(--purple); }
        .item-type-badge.medication { background: var(--success-bg); color: var(--success); }
        .item-type-badge.procedure { background: var(--warning-bg); color: var(--warning); }
        .item-type-badge.tool { background: var(--danger-bg); color: var(--danger); }
        .item-type-badge.registration { background: #E0E7FF; color: #4F46E5; }
        
        [data-theme="dark"] .item-type-badge.consultation { background: #1E3A5F; color: #6EA8FE; }
        [data-theme="dark"] .item-type-badge.registration { background: #1E3A5F; color: #818CF8; }
        
        /* ================================================================
           TOGGLE ICON
           ================================================================ */
        .toggle-icon {
            transition: transform 0.3s ease;
            display: inline-block;
            color: var(--text-secondary);
        }
        .toggle-icon.open {
            transform: rotate(180deg);
        }
        
        /* ================================================================
           EMPTY STATE
           ================================================================ */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--empty-state-color);
        }
        .empty-state i {
            font-size: 3rem;
            color: var(--border-color);
            display: block;
            margin-bottom: 10px;
        }
        .empty-state .sub {
            color: var(--text-secondary);
            font-size: 0.85rem;
        }
        
        /* ================================================================
           MESSAGE BOX
           ================================================================ */
        .message-box {
            max-width: 1400px;
            margin: 0 auto 16px;
            padding: 12px 16px;
            border-radius: 12px;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }
        .message-box.success {
            background: var(--success-bg);
            color: var(--success);
            border-color: var(--success);
        }
        .message-box.error {
            background: var(--danger-bg);
            color: var(--danger);
            border-color: var(--danger);
        }
        .message-box i { margin-right: 8px; }
        
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
           FOOTER
           ================================================================ */
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--footer-border);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
            transition: border-color 0.3s ease;
        }
        .footer .footer-brand { 
            color: var(--success); 
            font-weight: 600; 
        }
        
        /* ================================================================
           ANIMATIONS
           ================================================================ */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .fade-in {
            animation: fadeIn 0.3s ease forwards;
        }
        
        .spinner {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .data-table { font-size: 0.7rem; }
            .data-table thead th, .data-table td { padding: 6px 10px; }
            .detail-container .items-table { font-size: 0.65rem; }
            .detail-container .items-table thead th, 
            .detail-container .items-table tbody td { padding: 4px 8px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .filter-group { flex-direction: column; align-items: stretch; }
            .filter-group .filter-label { margin-bottom: 4px; }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .data-table { font-size: 0.65rem; min-width: 600px; }
            .data-table thead th, .data-table td { padding: 4px 6px; }
            .page-header .btn-outline-light { padding: 4px 10px; font-size: 0.7rem; }
            .detail-container .detail-footer { flex-direction: column; align-items: stretch; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- TOP NAVIGATION - FROM HEADER (Already included) -->
<!-- ================================================================ -->

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
                Partial / Pending Payments
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;">CASHIER</span>
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-sync-alt fa-spin"></i> Live
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-credit-card"></i>
                View and process partial payments in <strong><?= htmlspecialchars($branch_name) ?></strong>
                
                <span class="header-badge" id="totalBadge">
                    <i class="fas fa-file-invoice"></i>
                    Total: <strong id="totalCount"><?= $total_partial ?></strong>
                </span>
                
                <span class="header-badge" style="background:rgba(217,119,6,0.2);border-color:rgba(217,119,6,0.3);">
                    <i class="fas fa-clock"></i>
                    Today: <strong><?= $today_total ?></strong>
                </span>
                
                <span class="header-badge" style="background:rgba(220,38,38,0.2);border-color:rgba(220,38,38,0.3);">
                    <i class="fas fa-money-bill-wave"></i>
                    Balance: <strong><?= $currency ?> <?= number_format($today_balance, 0) ?></strong>
                </span>
            </p>
        </div>
        <div class="header-right" style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <button onclick="manualRefresh()" class="btn-outline-light" id="refreshBtn">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Message -->
    <?php if (isset($message) && $message): ?>
        <div class="message-box <?= $message_type === 'success' ? 'success' : 'error' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- STATS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-grid">
        <div class="stat-card">
            <p class="number total"><?= $total_partial ?></p>
            <p class="label">📋 Total Pending</p>
        </div>
        <div class="stat-card">
            <p class="number partial"><?= $today_total ?></p>
            <p class="label">📅 Today's Bills</p>
        </div>
        <div class="stat-card">
            <p class="number balance"><?= $currency ?> <?= number_format($today_balance, 0) ?></p>
            <p class="label">💰 Today's Balance</p>
        </div>
        <div class="stat-card">
            <p class="number" style="color: var(--success);"><?= count($payments) ?></p>
            <p class="label">👤 Patients</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="filter-section">
        <div class="filter-group">
            <span class="filter-label"><i class="fas fa-calendar-alt"></i> Date:</span>
            <input type="date" id="dateFilter" value="<?= $date_filter ?>"
                   onchange="window.location.href='partial_payments.php?date='+this.value+'&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>'"
                   class="form-control">
            
            <span class="filter-label"><i class="fas fa-filter"></i> Status:</span>
            <select id="statusFilter" class="form-control"
                    onchange="window.location.href='partial_payments.php?status='+this.value+'&date=<?= urlencode($date_filter) ?>&search=<?= urlencode($search) ?>'">
                <option value="partial" <?= $status_filter === 'partial' ? 'selected' : '' ?>>Partial</option>
                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All</option>
            </select>
            
            <?php if (!empty($search)): ?>
                <a href="partial_payments.php" class="btn btn-outline btn-sm">
                    <i class="fas fa-times"></i> Clear Search
                </a>
            <?php endif; ?>
            
            <div class="ml-auto flex gap-2" style="margin-left:auto;">
                <button onclick="expandAll()" class="btn btn-outline btn-sm">
                    <i class="fas fa-expand"></i> Expand All
                </button>
                <button onclick="collapseAll()" class="btn btn-outline btn-sm">
                    <i class="fas fa-compress"></i> Collapse All
                </button>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- TABLE WITH GREEN HEADERS -->
    <!-- ================================================================ -->
    <div class="table-wrapper animate-fade-in-up">
        <div class="table-header">
            <div class="table-title">
                <i class="fas fa-list"></i>
                Pending Payments
                <span class="text-xs font-normal" style="color:var(--text-secondary);">(<?= count($payments) ?> bills)</span>
            </div>
            <div class="text-xs" style="color:var(--text-secondary);" id="tableUpdateTime">
                <i class="fas fa-clock"></i> Updated: <?= date('h:i:s A') ?>
            </div>
        </div>
        
        <div style="overflow-x:auto;">
            <table class="data-table" id="paymentsTable">
                <thead>
                    <tr>
                        <th style="width:30px;text-align:center;">#</th>
                        <th>Bill #</th>
                        <th>Patient</th>
                        <th>Visit</th>
                        <th style="text-align:right;">Total</th>
                        <th style="text-align:right;">Paid</th>
                        <th style="text-align:right;">Balance</th>
                        <th style="text-align:center;">Status</th>
                        <th style="text-align:center;">Items</th>
                        <th style="text-align:center;">Date</th>
                        <th style="text-align:center;">Action</th>
                    </tr>
                </thead>
                <tbody id="paymentsTableBody">
                    <?php if (count($payments) > 0): ?>
                        <?php $i = 1; foreach ($payments as $payment): 
                            $items = $bill_items[$payment['id']] ?? [];
                            $has_items = count($items) > 0;
                            $balance = $payment['balance'] ?? 0;
                            $total = $payment['total_amount'] ?? 0;
                            $paid = $payment['paid_amount'] ?? 0;
                            $status = $payment['status'] ?? 'pending';
                            $row_id = 'row-' . $payment['id'];
                        ?>
                            <!-- Main Row -->
                            <tr class="main-row" id="<?= $row_id ?>" onclick="toggleRow(this, '<?= $payment['id'] ?>')">
                                <td style="text-align:center;"><?= $i++ ?></td>
                                <td>
                                    <span class="font-mono font-semibold" style="color: var(--success);"><?= htmlspecialchars($payment['bill_number']) ?></span>
                                </td>
                                <td>
                                    <div class="font-medium text-sm"><?= htmlspecialchars($payment['patient_name'] ?? 'Unknown') ?></div>
                                    <div class="text-xs" style="color:var(--text-secondary);"><?= htmlspecialchars($payment['patient_code'] ?? 'N/A') ?></div>
                                </td>
                                <td>
                                    <span class="font-mono text-xs"><?= htmlspecialchars($payment['visit_number'] ?? 'N/A') ?></span>
                                </td>
                                <td style="text-align:right;font-weight:600;"><?= $currency ?> <?= number_format($total, 0) ?></td>
                                <td style="text-align:right;color:var(--success);"><?= $currency ?> <?= number_format($paid, 0) ?></td>
                                <td style="text-align:right;font-weight:700;color:var(--danger);"><?= $currency ?> <?= number_format($balance, 0) ?></td>
                                <td style="text-align:center;">
                                    <span class="status-badge <?= $status ?>"><?= ucfirst($status) ?></span>
                                </td>
                                <td style="text-align:center;">
                                    <span class="text-sm font-medium"><?= count($items) ?></span>
                                    <span class="toggle-icon" id="toggle-<?= $payment['id'] ?>">
                                        <i class="fas fa-chevron-down"></i>
                                    </span>
                                </td>
                                <td style="text-align:center;font-size:0.7rem;color:var(--text-secondary);">
                                    <?= date('d/m/Y', strtotime($payment['created_at'])) ?>
                                </td>
                                <td style="text-align:center;">
                                    <div class="flex justify-center gap-1 flex-wrap">
                                        <a href="view_bill.php?id=<?= $payment['id'] ?>" class="btn btn-outline btn-sm" title="View Bill">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($balance > 0): ?>
                                            <a href="process_payment.php?bill_id=<?= $payment['id'] ?>" class="btn btn-pay btn-sm" title="Pay <?= $currency ?> <?= number_format($balance, 0) ?>">
                                                <i class="fas fa-money-bill-wave"></i> Pay
                                            </a>
                                        <?php else: ?>
                                            <span class="text-xs" style="color:var(--success);">✅ Paid</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <!-- Detail Row (Hidden - Shows items) -->
                            <tr class="detail-row" id="detail-<?= $payment['id'] ?>" style="display:none;">
                                <td colspan="11">
                                    <div class="detail-container">
                                        <div class="flex justify-between items-center mb-2 flex-wrap gap-2">
                                            <span class="font-semibold text-sm">
                                                <i class="fas fa-list-ul"></i> Bill Items
                                                <span class="text-xs" style="color:var(--text-secondary);">(<?= count($items) ?> items)</span>
                                            </span>
                                            <span class="text-sm" style="color:var(--text-secondary);">
                                                Total: <strong style="color:var(--success);"><?= $currency ?> <?= number_format($total, 0) ?></strong>
                                                | Balance: <strong style="color:var(--danger);"><?= $currency ?> <?= number_format($balance, 0) ?></strong>
                                            </span>
                                        </div>
                                        
                                        <?php if ($has_items): ?>
                                            <table class="items-table">
                                                <thead>
                                                    <tr>
                                                        <th>Item Name</th>
                                                        <th>Type</th>
                                                        <th style="text-align:center;">Qty</th>
                                                        <th style="text-align:right;">Unit Price</th>
                                                        <th style="text-align:right;">Total</th>
                                                        <th style="text-align:center;">Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($items as $item): 
                                                        $item_type = $item['item_type'] ?? 'other';
                                                        $item_status = $item['payment_status'] ?? 'pending';
                                                        $item_total = $item['total_price'] ?? 0;
                                                        $item_unit = $item['unit_price'] ?? 0;
                                                        $item_qty = $item['quantity'] ?? 1;
                                                    ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></td>
                                                            <td>
                                                                <span class="item-type-badge <?= $item_type ?>">
                                                                    <?= ucfirst(str_replace('_', ' ', $item_type)) ?>
                                                                </span>
                                                            </td>
                                                            <td style="text-align:center;"><?= $item_qty ?></td>
                                                            <td style="text-align:right;"><?= $currency ?> <?= number_format($item_unit, 0) ?></td>
                                                            <td style="text-align:right;font-weight:600;"><?= $currency ?> <?= number_format($item_total, 0) ?></td>
                                                            <td style="text-align:center;">
                                                                <span class="status-badge <?= $item_status === 'paid' ? 'paid' : ($item_status === 'partial' ? 'partial' : 'pending') ?>">
                                                                    <?= ucfirst($item_status ?? 'Pending') ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        <?php else: ?>
                                            <div class="empty-state" style="padding:12px;">
                                                <i class="fas fa-receipt" style="font-size:1.2rem;"></i>
                                                <p style="font-size:0.8rem;">No items found</p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="detail-footer">
                                            <span class="text-xs" style="color:var(--text-secondary);">
                                                <i class="fas fa-clock"></i> 
                                                Created: <?= date('d/m/Y h:i A', strtotime($payment['created_at'])) ?>
                                                <?php if (!empty($payment['created_by_name'])): ?>
                                                    <span class="mx-2">|</span>
                                                    <i class="fas fa-user"></i>
                                                    By: <?= htmlspecialchars($payment['created_by_name']) ?>
                                                <?php endif; ?>
                                            </span>
                                            <div class="flex gap-2 flex-wrap">
                                                <a href="view_bill.php?id=<?= $payment['id'] ?>" class="btn btn-outline btn-sm">
                                                    <i class="fas fa-eye"></i> View Full Bill
                                                </a>
                                                <?php if ($balance > 0): ?>
                                                    <a href="process_payment.php?bill_id=<?= $payment['id'] ?>" class="btn btn-pay btn-sm">
                                                        <i class="fas fa-money-bill-wave"></i> Pay <?= $currency ?> <?= number_format($balance, 0) ?>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="11">
                                <div class="empty-state" style="padding:40px 20px;">
                                    <i class="fas fa-check-circle" style="color: var(--success); font-size: 3rem;"></i>
                                    <h3 style="font-size:1.2rem;margin-top:12px;color:var(--text-primary);">No Partial Payments</h3>
                                    <p class="sub">All bills are fully paid or no pending payments found</p>
                                    <a href="dashboard.php" class="btn btn-primary mt-3">
                                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div style="padding:10px 20px;border-top:2px solid var(--border-color);display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;font-size:0.75rem;color:var(--text-secondary);transition:border-color 0.3s ease;">
            <span>
                <i class="fas fa-file-invoice mr-1"></i>
                Showing <strong><?= count($payments) ?></strong> bill(s)
            </span>
            <span>
                <i class="fas fa-store-alt mr-1"></i>
                Branch: <strong><?= htmlspecialchars($branch_name) ?></strong>
            </span>
            <span id="footerTimestamp">
                <i class="fas fa-clock mr-1"></i>
                Last updated: <?= date('h:i:s A') ?>
            </span>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Partial Payments
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
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE - SYNC WITH HEADER
    // ================================================================
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
            if (e.key === 'darkMode') {
                syncDarkMode();
            }
        });
        
        document.addEventListener('darkModeChanged', function(e) {
            var isDark = e.detail && e.detail.isDark;
            if (isDark) {
                htmlElement.setAttribute('data-theme', 'dark');
            } else {
                htmlElement.removeAttribute('data-theme');
            }
        });
    })();

    // ================================================================
    // DATE & TIME - LIVE UPDATE
    // ================================================================
    function updateDateTime() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        
        var clockDisplay = document.getElementById('clockDisplay');
        if (clockDisplay) {
            clockDisplay.textContent = dateStr + ' • ' + timeStr;
        }
        
        var footerTimestamp = document.getElementById('footerTimestamp');
        if (footerTimestamp) {
            footerTimestamp.textContent = 'Last updated: ' + timeStr;
        }
        
        var tableUpdateTime = document.getElementById('tableUpdateTime');
        if (tableUpdateTime) {
            tableUpdateTime.textContent = 'Updated: ' + timeStr;
        }
    }
    
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            if (sidebar) sidebar.classList.toggle('open');
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
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        var date = '<?= $date_filter ?>';
        var status = '<?= $status_filter ?>';
        if (query.length > 0) {
            window.location.href = 'partial_payments.php?search=' + encodeURIComponent(query) + '&date=' + date + '&status=' + status;
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
    // TOGGLE ROW (Expand/Collapse)
    // ================================================================
    function toggleRow(rowElement, billId) {
        var detailRow = document.getElementById('detail-' + billId);
        var toggleIcon = document.getElementById('toggle-' + billId);
        
        if (detailRow) {
            if (detailRow.style.display === 'none') {
                detailRow.style.display = 'table-row';
                rowElement.classList.add('expanded');
                if (toggleIcon) toggleIcon.classList.add('open');
            } else {
                detailRow.style.display = 'none';
                rowElement.classList.remove('expanded');
                if (toggleIcon) toggleIcon.classList.remove('open');
            }
        }
    }

    // ================================================================
    // EXPAND ALL / COLLAPSE ALL
    // ================================================================
    function expandAll() {
        document.querySelectorAll('.detail-row').forEach(function(row) {
            row.style.display = 'table-row';
        });
        document.querySelectorAll('.main-row').forEach(function(row) {
            row.classList.add('expanded');
        });
        document.querySelectorAll('.toggle-icon').forEach(function(icon) {
            icon.classList.add('open');
        });
        showToast('📂 Expand All', 'All bill items expanded', 'info');
    }

    function collapseAll() {
        document.querySelectorAll('.detail-row').forEach(function(row) {
            row.style.display = 'none';
        });
        document.querySelectorAll('.main-row').forEach(function(row) {
            row.classList.remove('expanded');
        });
        document.querySelectorAll('.toggle-icon').forEach(function(icon) {
            icon.classList.remove('open');
        });
        showToast('📁 Collapse All', 'All bill items collapsed', 'info');
    }

    // ================================================================
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        
        if (!toast) return;
        
        toast.className = 'toast-custom ' + (type || 'info');
        toastTitle.textContent = title || 'Notification';
        toastMessage.textContent = message || '';
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
        btn.innerHTML = '<span class="spinner"></span> Loading...';
        btn.disabled = true;
        
        setTimeout(function() {
            window.location.reload();
        }, 1000);
        
        setTimeout(function() {
            btn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh';
            btn.disabled = false;
            showToast('✅ Refreshed', 'Page data updated manually', 'success');
        }, 2000);
    }

    console.log('%c💰 Braick - Partial Payments (Shared Header + Dark Mode)', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c📋 Total Bills: <?= $total_partial ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c📅 Today: <?= $today_total ?> | Balance: <?= $currency ?> <?= number_format($today_balance, 0) ?>', 'font-size:13px; color:#D97706;');
    console.log('%c✅ Click row to expand and see all items', 'font-size:13px; color:#059669;');
    console.log('%c💰 Pay button redirects to process_payment.php', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🟢 Table headers have GREEN background', 'font-size:13px; color:#059669;');
    console.log('%c🌓 Dark mode synced with header via localStorage', 'font-size:13px; color:#8B5CF6;');
</script>

</body>
</html>