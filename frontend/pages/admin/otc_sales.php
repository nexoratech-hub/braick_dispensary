<?php
// ================================================================
// FILE: frontend/pages/admin/otc_sales.php
// SUPER ADMIN - OTC SALES LIST
// BRAICK DISPENSARY - BLUE THEME
// ================================================================

// ================================================================
// START SESSION
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// CHECK IF USER IS ADMIN
// ================================================================
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../dashboard.php');
    exit;
}

// ================================================================
// GET USER DATA
// ================================================================
$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// GET BRANCH FILTER
// ================================================================
$selected_branch_id = isset($_GET['branch']) ? (int)$_GET['branch'] : 0;
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';

// ================================================================
// GET OTC SALES WITH FILTERS
// ================================================================
$query = "SELECT * FROM otc_sales WHERE 1=1";

if ($selected_branch_id > 0) {
    $query .= " AND branch_id = ?";
}

if (!empty($search_term)) {
    $query .= " AND (customer_name LIKE ? OR sale_number LIKE ? OR customer_phone LIKE ?)";
}

if (!empty($status_filter)) {
    $query .= " AND payment_status = ?";
}

$query .= " ORDER BY created_at DESC";

$stmt = $db->prepare($query);

$params = [];
if ($selected_branch_id > 0) {
    $params[] = $selected_branch_id;
}
if (!empty($search_term)) {
    $search = '%' . $search_term . '%';
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}
if (!empty($status_filter)) {
    $params[] = $status_filter;
}

$stmt->execute($params);
$otc_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STATISTICS
// ================================================================
$stats_query = "SELECT 
    COUNT(*) as total_sales,
    COUNT(CASE WHEN payment_status = 'paid' THEN 1 END) as paid_sales,
    COUNT(CASE WHEN payment_status = 'pending' THEN 1 END) as pending_sales,
    COUNT(CASE WHEN payment_status = 'partial' THEN 1 END) as partial_sales,
    COALESCE(SUM(net_amount), 0) as total_revenue,
    COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN net_amount ELSE 0 END), 0) as paid_revenue,
    COALESCE(SUM(CASE WHEN payment_status = 'pending' THEN net_amount ELSE 0 END), 0) as pending_revenue
    FROM otc_sales WHERE 1=1";

if ($selected_branch_id > 0) {
    $stats_query .= " AND branch_id = ?";
}
$stmt = $db->prepare($stats_query);
if ($selected_branch_id > 0) {
    $stmt->execute([$selected_branch_id]);
} else {
    $stmt->execute();
}
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// ================================================================
// GET BRANCHES FOR FILTER
// ================================================================
$branches = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET BRANCH NAME FOR DISPLAY
// ================================================================
$branch_name_display = 'All Branches';
foreach ($branches as $b) {
    if ($b['id'] == $selected_branch_id) {
        $branch_name_display = $b['name'];
        break;
    }
}

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
// INCLUDE THE SHARED HEADER
// ================================================================
include_once __DIR__ . '/../../components/admin_header.php';

// ================================================================
// INCLUDE THE SHARED SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/admin_sidebar.php';

// ================================================================
// Format currency
// ================================================================
function format_currency($amount) {
    if ($amount == 0) {
        return 'TSh 0';
    }
    return 'TSh ' . number_format($amount, 2);
}

// ================================================================
// Payment status badge
// ================================================================
function get_payment_status_badge($status) {
    $badges = [
        'paid' => 'success',
        'pending' => 'warning',
        'partial' => 'info',
        'cancelled' => 'danger'
    ];
    return $badges[$status] ?? 'secondary';
}

function get_payment_status_icon($status) {
    $icons = [
        'paid' => 'fa-check-circle',
        'pending' => 'fa-clock',
        'partial' => 'fa-hourglass-half',
        'cancelled' => 'fa-times-circle'
    ];
    return $icons[$status] ?? 'fa-circle';
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTC Sales - Braick Dispensary</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #1A56DB;
            --primary-dark: #1A3E8C;
            --primary-light: #3B82F6;
            --primary-bg: #E8EFF9;
            --primary-solid: #1A56DB;
            
            --success: #1A56DB;
            --success-dark: #1A3E8C;
            --success-light: #3B82F6;
            --success-bg: #E8EFF9;
            
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
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
            --shadow-xl: 0 20px 30px rgba(0,0,0,0.12);
            
            --bg-body: #F0F4F8;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --radius: 12px;
            --radius-lg: 18px;
            --table-hover: #F8FAFC;
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
            --primary-solid: #2563EB;
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
            --purple-bg: #2D1B5F;
            --table-hover: #1E293B;
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
           MAIN CONTENT
           ================================================================ */
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        /* ================================================================
           PAGE HEADER - BLUE
           ================================================================ */
        .page-header {
            background: var(--primary-solid);
            border-radius: var(--radius-lg);
            padding: 28px 36px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 8px 32px rgba(26, 86, 219, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -60%;
            right: -10%;
            width: 400px;
            height: 400px;
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
            background: rgba(255,255,255,0.12);
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
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 18px;
            border-radius: var(--radius);
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
        }
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        /* ================================================================
           STATS CARDS - BLUE BACKGROUND
           ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }
        
        .stat-card {
            background: var(--primary-solid);
            border-radius: var(--radius);
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 16px rgba(26, 86, 219, 0.25);
            border: none;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 32px rgba(26, 86, 219, 0.35);
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
            background: rgba(255,255,255,0.2);
            color: white;
            backdrop-filter: blur(4px);
        }
        
        .stat-card .stat-label {
            font-size: 0.65rem;
            color: rgba(255,255,255,0.75);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin: 0;
        }
        
        .stat-card .stat-value {
            font-size: 1.3rem;
            font-weight: 700;
            color: white;
            margin: 0;
            line-height: 1.2;
        }
        
        /* ================================================================
           FILTERS BAR
           ================================================================ */
        .filters-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            margin-bottom: 20px;
            background: var(--bg-card);
            padding: 16px 20px;
            border-radius: var(--radius-lg);
            border: 2px solid var(--border-color);
        }
        
        .filters-bar .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .filters-bar .filter-group label {
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--text-secondary);
        }
        
        .filters-bar select,
        .filters-bar input {
            padding: 6px 12px;
            border-radius: 8px;
            border: 2px solid var(--border-color);
            background: var(--bg-body);
            color: var(--text-primary);
            font-size: 0.78rem;
            outline: none;
            transition: all 0.3s;
        }
        
        .filters-bar select:focus,
        .filters-bar input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 86, 219, 0.12);
        }
        
        .filters-bar .btn-filter {
            padding: 6px 16px;
            border-radius: 8px;
            background: var(--primary-solid);
            color: white;
            border: none;
            font-size: 0.78rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .filters-bar .btn-filter:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(26, 86, 219, 0.3);
        }
        
        .filters-bar .btn-clear {
            padding: 6px 16px;
            border-radius: 8px;
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
            font-size: 0.78rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .filters-bar .btn-clear:hover {
            border-color: var(--danger);
            color: var(--danger);
        }
        
        /* ================================================================
           TABLE
           ================================================================ */
        .table-container {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px solid var(--border-color);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }
        
        .table-container .table-header {
            padding: 14px 20px;
            background: var(--primary-solid);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .table-container .table-header .title {
            color: white;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .table-container .table-header .title i {
            margin-right: 8px;
        }
        
        .table-container .table-header .count {
            color: rgba(255,255,255,0.8);
            font-size: 0.75rem;
        }
        
        .table-container table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
        }
        
        .table-container table thead {
            background: var(--bg-body);
        }
        
        .table-container table th {
            padding: 10px 14px;
            text-align: left;
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid var(--border-color);
        }
        
        .table-container table td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        
        .table-container table tr:hover td {
            background: var(--table-hover);
        }
        
        .table-container table tr:last-child td {
            border-bottom: none;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        
        .status-badge.success { background: #D1FAE5; color: #059669; }
        .status-badge.warning { background: #FEF3C7; color: #D97706; }
        .status-badge.info { background: #EFF6FF; color: #0B5ED7; }
        .status-badge.danger { background: #FEE2E2; color: #DC2626; }
        .status-badge.secondary { background: #F1F5F9; color: #64748B; }
        
        [data-theme="dark"] .status-badge.success { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .status-badge.warning { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .status-badge.info { background: #1E3A5F; color: #3B82F6; }
        [data-theme="dark"] .status-badge.danger { background: #3A1A1A; color: #F87171; }
        [data-theme="dark"] .status-badge.secondary { background: #2D3748; color: #94A3B8; }
        
        /* ================================================================
           ACTION BUTTONS - VIEW, EDIT, DELETE
           ================================================================ */
        .action-buttons {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
        }
        
        .btn-action {
            padding: 4px 10px;
            font-size: 0.6rem;
            border-radius: 6px;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border: none;
            cursor: pointer;
        }
        
        .btn-action:hover {
            transform: scale(1.05);
        }
        
        .btn-action.view {
            background: #0B5ED7;
            color: white;
        }
        .btn-action.view:hover {
            background: #0A4CA8;
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .btn-action.edit {
            background: #D97706;
            color: white;
        }
        .btn-action.edit:hover {
            background: #B45309;
            box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3);
        }
        
        .btn-action.delete {
            background: #DC2626;
            color: white;
        }
        .btn-action.delete:hover {
            background: #B91C1C;
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
        }
        
        [data-theme="dark"] .btn-action.view {
            background: #2563EB;
        }
        [data-theme="dark"] .btn-action.view:hover {
            background: #1D4ED8;
        }
        
        [data-theme="dark"] .btn-action.edit {
            background: #D97706;
        }
        [data-theme="dark"] .btn-action.edit:hover {
            background: #B45309;
        }
        
        [data-theme="dark"] .btn-action.delete {
            background: #DC2626;
        }
        [data-theme="dark"] .btn-action.delete:hover {
            background: #B91C1C;
        }
        
        /* ================================================================
           EMPTY STATE
           ================================================================ */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 4rem;
            color: var(--border-color);
            display: block;
            margin-bottom: 16px;
        }
        
        .empty-state h3 {
            font-size: 1.2rem;
            color: var(--text-primary);
            margin: 0 0 8px 0;
        }
        
        .empty-state p {
            font-size: 0.9rem;
            margin: 0 0 20px 0;
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
            color: var(--primary-solid);
            font-weight: 500;
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
            .filters-bar { flex-direction: column; align-items: stretch; }
            .filters-bar .filter-group { flex-wrap: wrap; }
            .table-container table { font-size: 0.7rem; }
            .table-container table th, 
            .table-container table td { padding: 6px 10px; }
            .action-buttons { flex-direction: column; gap: 2px; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .stats-grid { grid-template-columns: 1fr; }
            .page-header .page-title { font-size: 1.1rem; }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
        
        /* ================================================================
           DELETE MODAL
           ================================================================ */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.6);
            z-index: 999;
            display: none;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }
        
        .modal-overlay.active {
            display: flex;
        }
        
        .modal-box {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 32px 36px;
            max-width: 420px;
            width: 90%;
            box-shadow: var(--shadow-xl);
            border: 2px solid var(--border-color);
            text-align: center;
        }
        
        .modal-box .modal-icon {
            font-size: 3rem;
            color: #DC2626;
            margin-bottom: 12px;
        }
        
        .modal-box h3 {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        
        .modal-box p {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 20px;
        }
        
        .modal-box .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        .modal-box .modal-actions .btn-modal {
            padding: 8px 24px;
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .modal-box .modal-actions .btn-modal:hover {
            transform: translateY(-2px);
        }
        
        .modal-box .modal-actions .btn-modal.cancel {
            background: var(--bg-body);
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        
        .modal-box .modal-actions .btn-modal.cancel:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .modal-box .modal-actions .btn-modal.confirm {
            background: #DC2626;
            color: white;
        }
        
        .modal-box .modal-actions .btn-modal.confirm:hover {
            background: #B91C1C;
            box-shadow: 0 4px 16px rgba(220, 38, 38, 0.35);
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- SIDEBAR - Provided by admin_sidebar.php -->
<!-- ================================================================ -->

<!-- ================================================================ -->
<!-- TOP NAV - Provided by admin_header.php -->
<!-- ================================================================ -->

<!-- ================================================================ -->
<!-- DELETE CONFIRMATION MODAL -->
<!-- ================================================================ -->
<div id="deleteModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-icon"><i class="fas fa-exclamation-triangle"></i></div>
        <h3>Confirm Delete</h3>
        <p>Are you sure you want to delete this OTC sale? This action cannot be undone.</p>
        <div class="modal-actions">
            <button class="btn-modal cancel" onclick="closeDeleteModal()">Cancel</button>
            <button class="btn-modal confirm" id="confirmDeleteBtn">Delete</button>
        </div>
    </div>
</div>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-shopping-cart"></i>
                OTC Sales
                <span class="role-badge-display"><?= strtoupper($user_role) ?></span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-store-alt"></i>
                <?= htmlspecialchars($branch_name_display) ?>
                <span class="header-badge">
                    <i class="fas fa-shopping-cart"></i> <?= number_format($stats['total_sales'] ?? 0) ?> Sales
                </span>
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-money-bill-wave"></i> <?= format_currency($stats['total_revenue'] ?? 0) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="view_pharmacy.php?id=<?= $selected_branch_id ?>&branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to Pharmacy
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid animate-fade-in-up">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
            <div>
                <p class="stat-label">Total Sales</p>
                <p class="stat-value"><?= number_format($stats['total_sales'] ?? 0) ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div>
                <p class="stat-label">Paid</p>
                <p class="stat-value"><?= number_format($stats['paid_sales'] ?? 0) ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div>
                <p class="stat-label">Pending</p>
                <p class="stat-value"><?= number_format($stats['pending_sales'] ?? 0) ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div>
                <p class="stat-label">Total Revenue</p>
                <p class="stat-value"><?= format_currency($stats['total_revenue'] ?? 0) ?></p>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-bar animate-fade-in-up" style="animation-delay:0.05s;">
        <div class="filter-group">
            <label for="statusFilter">Status:</label>
            <select id="statusFilter" onchange="applyFilters()">
                <option value="">All Status</option>
                <option value="paid" <?= $status_filter === 'paid' ? 'selected' : '' ?>>Paid</option>
                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="partial" <?= $status_filter === 'partial' ? 'selected' : '' ?>>Partial</option>
                <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
        </div>
        
        <div class="filter-group" style="flex:1;min-width:150px;">
            <input type="text" id="searchInputFilter" placeholder="Search customer, sale #..." value="<?= htmlspecialchars($search_term) ?>">
        </div>
        
        <button class="btn-filter" onclick="applyFilters()">
            <i class="fas fa-search"></i> Filter
        </button>
        
        <a href="otc_sales.php?branch=<?= $selected_branch_id ?>" class="btn-clear">
            <i class="fas fa-times"></i> Clear
        </a>
    </div>

    <!-- Table -->
    <div class="table-container animate-fade-in-up" style="animation-delay:0.1s;">
        <div class="table-header">
            <span class="title">
                <i class="fas fa-list"></i> OTC Sales List
            </span>
            <span class="count">
                <?= count($otc_sales) ?> sale(s) found
            </span>
        </div>
        
        <?php if (count($otc_sales) > 0): ?>
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Sale #</th>
                            <th>Customer</th>
                            <th>Phone</th>
                            <th class="text-right">Total</th>
                            <th class="text-right">Discount</th>
                            <th class="text-right">Net Amount</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($otc_sales as $sale): ?>
                            <tr>
                                <td class="font-mono text-xs font-semibold text-blue-600">
                                    <?= htmlspecialchars($sale['sale_number']) ?>
                                </td>
                                <td><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?></td>
                                <td><?= htmlspecialchars($sale['customer_phone'] ?? 'N/A') ?></td>
                                <td class="text-right"><?= format_currency($sale['total_amount'] ?? 0) ?></td>
                                <td class="text-right" style="color:var(--danger);">- <?= format_currency($sale['discount_amount'] ?? 0) ?></td>
                                <td class="text-right font-semibold"><?= format_currency($sale['net_amount'] ?? 0) ?></td>
                                <td class="text-xs"><?= strtoupper($sale['payment_method'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="status-badge <?= get_payment_status_badge($sale['payment_status'] ?? 'pending') ?>">
                                        <i class="fas <?= get_payment_status_icon($sale['payment_status'] ?? 'pending') ?>"></i>
                                        <?= ucfirst($sale['payment_status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td class="text-xs"><?= date('M d, Y', strtotime($sale['created_at'])) ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <!-- VIEW BUTTON -->
                                        <a href="view_otc_sale.php?id=<?= $sale['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                           class="btn-action view" title="View Sale">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        
                                        <!-- EDIT BUTTON -->
                                        <a href="edit_otc_sale.php?id=<?= $sale['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                           class="btn-action edit" title="Edit Sale">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        
                                        <!-- DELETE BUTTON -->
                                        <button onclick="confirmDelete(<?= $sale['id'] ?>)" 
                                                class="btn-action delete" title="Delete Sale">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-shopping-cart"></i>
                <h3>No OTC Sales Found</h3>
                <p>No OTC sales match your search criteria. Try adjusting your filters.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            OTC Sales
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<script>
    // ================================================================
    // DELETE FUNCTIONALITY
    // ================================================================
    var deleteId = null;
    var deleteModal = document.getElementById('deleteModal');
    var confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    
    function confirmDelete(id) {
        deleteId = id;
        deleteModal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    function closeDeleteModal() {
        deleteModal.classList.remove('active');
        deleteId = null;
        document.body.style.overflow = '';
    }
    
    // Close modal on outside click
    deleteModal.addEventListener('click', function(e) {
        if (e.target === deleteModal) {
            closeDeleteModal();
        }
    });
    
    // Close modal on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && deleteModal.classList.contains('active')) {
            closeDeleteModal();
        }
    });
    
    // Confirm delete
    confirmDeleteBtn.addEventListener('click', function() {
        if (deleteId) {
            var branch = '<?= $selected_branch_id ?>';
            window.location.href = 'delete_otc_sale.php?id=' + deleteId + '&branch=' + branch;
        }
        closeDeleteModal();
    });

    // ================================================================
    // BRANCH SWITCHER - Uses function from admin_header.php
    // ================================================================
    if (typeof switchBranch === 'undefined') {
        function switchBranch(branchId) {
            var url = new URL(window.location.href);
            url.searchParams.set('branch', branchId);
            if (url.searchParams.has('status')) {
                url.searchParams.delete('status');
            }
            if (url.searchParams.has('search')) {
                url.searchParams.delete('search');
            }
            window.location.href = url.toString();
        }
    }

    // ================================================================
    // APPLY FILTERS
    // ================================================================
    function applyFilters() {
        var branch = '<?= $selected_branch_id ?>';
        var status = document.getElementById('statusFilter').value;
        var search = document.getElementById('searchInputFilter').value.trim();
        
        var url = 'otc_sales.php?branch=' + branch;
        if (status) url += '&status=' + status;
        if (search) url += '&search=' + encodeURIComponent(search);
        
        window.location.href = url;
    }

    // ================================================================
    // SEARCH - Top bar (uses header search)
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput ? searchInput.value.trim() : '';
        if (query.length > 0) {
            var branch = '<?= $selected_branch_id ?>';
            window.location.href = 'otc_sales.php?branch=' + branch + '&search=' + encodeURIComponent(query);
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
    // DATE & TIME - Uses function from admin_header.php
    // ================================================================

    // ================================================================
    // DARK MODE - Uses function from admin_header.php
    // ================================================================

    console.log('%c🛒 Braick Dispensary - OTC Sales', 'font-size:18px; font-weight:bold; color:#1A56DB;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name_display) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📊 Total Sales: <?= number_format($stats['total_sales'] ?? 0) ?>', 'font-size:13px; color:#7B2FBE;');
    console.log('%c💰 Total Revenue: <?= format_currency($stats['total_revenue'] ?? 0) ?>', 'font-size:13px; color:#D97706;');
    console.log('%c✅ Using SHARED HEADER - Favicon included', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Using SHARED SIDEBAR', 'font-size:13px; color:#34D399;');
    console.log('%c✅ Buttons: VIEW, EDIT, DELETE (No New OTC)', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>