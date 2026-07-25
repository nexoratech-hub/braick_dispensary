<?php
// ================================================================
// FILE: frontend/pages/cashier/partial_payments.php
// CASHIER - PARTIAL PAYMENTS LIST
// FIXED: Table format with expandable rows
// FIXED: Green header background
// FIXED: Pay button redirects to process_payment.php
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// IF NO SESSION, USE CASHIER.DODOMA (ID: 10) AS DEFAULT
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    $_SESSION['user_id'] = 10;
    $_SESSION['full_name'] = 'Rose Mwangi';
    $_SESSION['role'] = 'cashier';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'cashier.dodoma';
}

$user_id = $_SESSION['user_id'] ?? 10;
$user_full_name = $_SESSION['full_name'] ?? 'Cashier Dodoma';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';
$db = Database::getInstance()->getConnection();

// ================================================================
// GET FILTERS
// ================================================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'partial';

// ================================================================
// GET PARTIAL PAYMENTS
// ================================================================
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
    WHERE pb.branch_id = ? AND pb.status IN ('pending', 'partial')
";

$params = [$user_branch_id];

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
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $bill_items[$payment['id']] = $items;
}

// ================================================================
// GET COUNTS
// ================================================================
$stmt = $db->prepare("
    SELECT COUNT(*) as total FROM patient_bills 
    WHERE branch_id = ? AND status IN ('pending', 'partial')
");
$stmt->execute([$user_branch_id]);
$total_partial = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$today = date('Y-m-d');
$stmt = $db->prepare("
    SELECT COUNT(*) as total, COALESCE(SUM(balance), 0) as total_balance 
    FROM patient_bills 
    WHERE branch_id = ? AND status IN ('pending', 'partial') AND DATE(created_at) = ?
");
$stmt->execute([$user_branch_id, $today]);
$today_stats = $stmt->fetch(PDO::FETCH_ASSOC);
$today_total = $today_stats['total'] ?? 0;
$today_balance = $today_stats['total_balance'] ?? 0;

// ================================================================
// UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// ================================================================
// PROFILE PICTURE
// ================================================================
$profile_pic = $_SESSION['profile_pic'] ?? '';
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/cashier_header.php';
include_once __DIR__ . '/../../components/cashier_sidebar.php';
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
           ROOT VARIABLES
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
            --purple-bg: #2D1B5F;
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
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
        
        /* ================================================================
           TOP NAV
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
            border-color: var(--primary);
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
            background: var(--primary);
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
            background: var(--primary-dark);
        }
        
        .top-nav .datetime {
            font-size: 0.78rem;
            color: var(--text-secondary);
            font-weight: 500;
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
            border-color: var(--primary);
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
            color: var(--primary);
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
        
        .notif-dot.has-notif { background: var(--danger); }
        .notif-dot.no-notif { background: var(--gray-400); animation: none; }
        
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
            border-color: var(--primary);
            background: var(--bg-card);
        }
        
        .dark-toggle-btn i { font-size: 0.9rem; }
        
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
        .stat-card .number.pending { color: #D97706; }
        .stat-card .number.partial { color: #0B5ED7; }
        .stat-card .number.total { color: #7C3AED; }
        .stat-card .number.balance { color: #DC2626; }
        .stat-card .label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 500;
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
            color: var(--primary);
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
            background: #059669;
            border-bottom: 3px solid #047857;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 5;
        }
        
        [data-theme="dark"] .data-table thead th {
            background: #047857;
            border-bottom-color: #065F46;
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
            background: var(--primary-bg);
        }
        [data-theme="dark"] .data-table tbody tr:hover {
            background: #1E3A5F;
        }
        
        .data-table tbody tr.expanded {
            background: var(--primary-bg);
        }
        [data-theme="dark"] .data-table tbody tr.expanded {
            background: #1E3A5F;
        }
        
        .data-table tbody tr.detail-row td {
            padding: 0;
            border-bottom: none;
        }
        
        .data-table tbody tr.detail-row .detail-container {
            padding: 12px 20px;
            background: var(--gray-50);
            border-top: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);
        }
        [data-theme="dark"] .data-table tbody tr.detail-row .detail-container {
            background: var(--gray-700);
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
        .status-badge.pending { background: #FEF3C7; color: #D97706; }
        .status-badge.partial { background: #E8F0FE; color: #0B5ED7; }
        .status-badge.paid { background: #D1FAE5; color: #059669; }
        
        .item-type-badge {
            font-size: 0.5rem;
            font-weight: 600;
            padding: 1px 8px;
            border-radius: 8px;
        }
        .item-type-badge.consultation { background: #E8F0FE; color: #0B5ED7; }
        .item-type-badge.lab_test { background: #EDE9FE; color: #7C3AED; }
        .item-type-badge.medication { background: #D1FAE5; color: #059669; }
        .item-type-badge.procedure { background: #FEF3C7; color: #D97706; }
        .item-type-badge.tool { background: #FEE2E2; color: #DC2626; }
        .item-type-badge.registration { background: #E0E7FF; color: #4F46E5; }
        
        /* ================================================================
           BUTTONS
           ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.75rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-pay {
            background: linear-gradient(135deg, var(--success), var(--success-dark));
            color: white;
            box-shadow: 0 2px 8px rgba(5, 150, 105, 0.2);
        }
        .btn-pay:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(5, 150, 105, 0.35);
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
        
        .btn-sm {
            padding: 3px 10px;
            font-size: 0.65rem;
            border-radius: 6px;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        
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
            color: var(--text-secondary);
        }
        .empty-state i {
            font-size: 3rem;
            color: var(--border-color);
            display: block;
            margin-bottom: 10px;
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
            color: var(--primary); 
            font-weight: 600; 
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .data-table { font-size: 0.7rem; }
            .data-table thead th, .data-table td { padding: 6px 10px; }
            .detail-container .items-table { font-size: 0.65rem; }
            .detail-container .items-table thead th, 
            .detail-container .items-table tbody td { padding: 4px 8px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .top-nav .search-wrapper { max-width: 120px; }
            .top-nav .search-wrapper .search-btn { padding: 8px 10px; font-size: 0.7rem; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .data-table { font-size: 0.65rem; min-width: 600px; }
            .data-table thead th, .data-table td { padding: 4px 6px; }
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
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- TOP NAVIGATION -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <input type="text" id="searchInput" placeholder="Search bills, patients..." value="<?= htmlspecialchars($search) ?>">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <span class="branch-badge-display">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($user_branch_name) ?>
        </span>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= $unread_notifications > 0 ? 'has-notif' : 'no-notif' ?>"></span>
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

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-receipt"></i>
                Partial / Pending Payments
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;">CASHIER</span>
                <span class="update-badge-light" id="updateBadge">
                    <i class="fas fa-sync-alt fa-spin"></i> Live
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-credit-card"></i>
                View and process partial payments in <strong><?= htmlspecialchars($user_branch_name) ?></strong>
                
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
                    Balance: <strong>TSh <?= number_format($today_balance, 0) ?></strong>
                </span>
            </p>
        </div>
        <div class="header-right">
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <button onclick="window.location.reload()" class="btn-outline-light">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

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
            <p class="number balance">TSh <?= number_format($today_balance, 0) ?></p>
            <p class="label">💰 Today's Balance</p>
        </div>
        <div class="stat-card">
            <p class="number" style="color: #059669;"><?= count($payments) ?></p>
            <p class="label">👤 Patients</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <span class="text-sm font-medium text-gray-600">Date:</span>
        <input type="date" id="dateFilter" value="<?= $date_filter ?>"
               onchange="window.location.href='partial_payments.php?date='+this.value+'&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>'"
               class="form-control" style="width:auto;padding:4px 10px;font-size:0.8rem;border:2px solid var(--border-color);border-radius:8px;background:var(--bg-card);color:var(--text-primary);">
        
        <span class="text-sm font-medium text-gray-600 ml-2">Status:</span>
        <select id="statusFilter" class="form-control" style="width:auto;padding:4px 10px;font-size:0.8rem;border:2px solid var(--border-color);border-radius:8px;background:var(--bg-card);color:var(--text-primary);"
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
        
        <div class="ml-auto flex gap-2">
            <button onclick="expandAll()" class="btn btn-outline btn-sm">
                <i class="fas fa-expand"></i> Expand All
            </button>
            <button onclick="collapseAll()" class="btn btn-outline btn-sm">
                <i class="fas fa-compress"></i> Collapse All
            </button>
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
                <span class="text-xs font-normal text-gray-400">(<?= count($payments) ?> bills)</span>
            </div>
            <div class="text-xs text-gray-400" id="tableUpdateTime">
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
                                    <span class="font-mono font-semibold" style="color: #059669;"><?= htmlspecialchars($payment['bill_number']) ?></span>
                                </td>
                                <td>
                                    <div class="font-medium text-sm"><?= htmlspecialchars($payment['patient_name'] ?? 'Unknown') ?></div>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($payment['patient_code'] ?? 'N/A') ?></div>
                                </td>
                                <td>
                                    <span class="font-mono text-xs"><?= htmlspecialchars($payment['visit_number'] ?? 'N/A') ?></span>
                                </td>
                                <td style="text-align:right;font-weight:600;">TSh <?= number_format($total, 0) ?></td>
                                <td style="text-align:right;color:var(--success);">TSh <?= number_format($paid, 0) ?></td>
                                <td style="text-align:right;font-weight:700;color:var(--danger);">TSh <?= number_format($balance, 0) ?></td>
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
                                            <a href="process_payment.php?bill_id=<?= $payment['id'] ?>" class="btn btn-pay btn-sm" title="Pay TSh <?= number_format($balance, 0) ?>">
                                                <i class="fas fa-money-bill-wave"></i> Pay
                                            </a>
                                        <?php else: ?>
                                            <span class="text-xs text-green-600">✅ Paid</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <!-- Detail Row (Hidden - Shows items) -->
                            <tr class="detail-row" id="detail-<?= $payment['id'] ?>" style="display:none;">
                                <td colspan="11">
                                    <div class="detail-container">
                                        <div class="flex justify-between items-center mb-2">
                                            <span class="font-semibold text-sm">
                                                <i class="fas fa-list-ul"></i> Bill Items
                                                <span class="text-xs text-gray-400">(<?= count($items) ?> items)</span>
                                            </span>
                                            <span class="text-sm text-gray-500">
                                                Total: <strong style="color:#059669;">TSh <?= number_format($total, 0) ?></strong>
                                                | Balance: <strong style="color:#DC2626;">TSh <?= number_format($balance, 0) ?></strong>
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
                                                            <td style="text-align:right;">TSh <?= number_format($item_unit, 0) ?></td>
                                                            <td style="text-align:right;font-weight:600;">TSh <?= number_format($item_total, 0) ?></td>
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
                                            <span class="text-xs text-gray-400">
                                                <i class="fas fa-clock"></i> 
                                                Created: <?= date('d/m/Y h:i A', strtotime($payment['created_at'])) ?>
                                                <?php if (!empty($payment['created_by_name'])): ?>
                                                    <span class="mx-2">|</span>
                                                    <i class="fas fa-user"></i>
                                                    By: <?= htmlspecialchars($payment['created_by_name']) ?>
                                                <?php endif; ?>
                                            </span>
                                            <div class="flex gap-2">
                                                <a href="view_bill.php?id=<?= $payment['id'] ?>" class="btn btn-outline btn-sm">
                                                    <i class="fas fa-eye"></i> View Full Bill
                                                </a>
                                                <?php if ($balance > 0): ?>
                                                    <a href="process_payment.php?bill_id=<?= $payment['id'] ?>" class="btn btn-pay btn-sm">
                                                        <i class="fas fa-money-bill-wave"></i> Pay TSh <?= number_format($balance, 0) ?>
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
                                    <i class="fas fa-check-circle" style="color: #059669; font-size: 3rem;"></i>
                                    <h3 style="font-size:1.2rem;margin-top:12px;">No Partial Payments</h3>
                                    <p class="text-sm text-gray-400 mt-1">All bills are fully paid or no pending payments found</p>
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
        
        <div style="padding:10px 20px;border-top:2px solid var(--border-color);display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;font-size:0.75rem;color:var(--text-secondary);">
            <span>
                <i class="fas fa-file-invoice mr-1"></i>
                Showing <strong><?= count($payments) ?></strong> bill(s)
            </span>
            <span>
                <i class="fas fa-store-alt mr-1"></i>
                Branch: <strong><?= htmlspecialchars($user_branch_name) ?></strong>
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
    <i class="fas fa-info-circle"></i>
    <div>
        <p id="toastTitle">Notification</p>
        <p id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE
    // ================================================================
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
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
        }
    });

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    sidebarToggle?.addEventListener('click', function() {
        sidebar.classList.toggle('open');
    });
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    // ================================================================
    // DATE & TIME
    // ================================================================
    function updateDateTime() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        document.getElementById('currentDateTime').textContent = dateStr + ' • ' + timeStr;
        document.getElementById('footerTimestamp').textContent = 'Last updated: ' + timeStr;
        var updateTime = document.getElementById('tableUpdateTime');
        if (updateTime) updateTime.textContent = 'Updated: ' + timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        var date = '<?= $date_filter ?>';
        var status = '<?= $status_filter ?>';
        window.location.href = 'partial_payments.php?search=' + encodeURIComponent(query) + '&date=' + date + '&status=' + status;
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

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

    console.log('%c💰 Braick - Partial Payments (GREEN HEADERS)', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c📋 Total Bills: <?= $total_partial ?>', 'font-size:13px; color:#7C3AED;');
    console.log('%c📅 Today: <?= $today_total ?> | Balance: TSh <?= number_format($today_balance, 0) ?>', 'font-size:13px; color:#D97706;');
    console.log('%c✅ Click row to expand and see all items', 'font-size:13px; color:#059669;');
    console.log('%c💰 Pay button redirects to process_payment.php', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🟢 Table headers have GREEN background', 'font-size:13px; color:#059669;');
</script>

</body>
</html>