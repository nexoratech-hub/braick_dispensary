<?php
// ================================================================
// FILE: frontend/pages/admin/otc_sales.php
// OTC SALES MANAGEMENT - Admin Module
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
// CHECK IF USER HAS ACCESS
// ================================================================
$allowed_roles = ['admin', 'pharmacy', 'cashier'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: ../dashboard.php');
    exit;
}

// ================================================================
// GET USER DATA
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'User';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// BRANCH SELECTION
// ================================================================
$selected_branch_id = $_GET['branch'] ?? 'all';
$branch_name_display = 'All Branches';

// ================================================================
// BRANCH NAME
// ================================================================
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $branch_id_param = (int)$selected_branch_id;
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$branch_id_param]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) {
        $branch_name_display = $branch_data['name'];
    }
} else {
    $selected_branch_id = 'all';
}

// ================================================================
// BRANCH FILTER
// ================================================================
$branch_filter = "";
if ($selected_branch_id !== 'all') {
    $branch_filter = " AND os.branch_id = " . (int)$selected_branch_id;
}

// ================================================================
// GET BRANCHES
// ================================================================
$branches = [];
$stmt = $db->query("SELECT id, name, location FROM branches WHERE status = 'active'");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET OTC SALES DATA WITH FILTERS
// ================================================================

// Get filter parameters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'all';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// ================================================================
// BUILD WHERE CLAUSE - SEPARATE FOR DIFFERENT QUERIES
// ================================================================

// For main query with branch filter already in $branch_filter
$where_conditions = [];
$params = [];

// Search filter
if (!empty($search)) {
    $where_conditions[] = "(os.sale_number LIKE ? OR os.customer_name LIKE ? OR os.customer_phone LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Status filter
if ($status_filter !== 'all') {
    $where_conditions[] = "os.payment_status = ?";
    $params[] = $status_filter;
}

// Date range filter
if (!empty($date_from)) {
    $where_conditions[] = "DATE(os.created_at) >= ?";
    $params[] = $date_from;
}
if (!empty($date_to)) {
    $where_conditions[] = "DATE(os.created_at) <= ?";
    $params[] = $date_to;
}

$where_clause = "";
if (!empty($where_conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
}

// Add branch filter to where clause (already has 'os.' prefix)
if ($selected_branch_id !== 'all') {
    if (!empty($where_clause)) {
        $where_clause .= " AND os.branch_id = " . (int)$selected_branch_id;
    } else {
        $where_clause = "WHERE os.branch_id = " . (int)$selected_branch_id;
    }
}

// ================================================================
// GET TOTAL COUNT FOR PAGINATION
// ================================================================
$count_sql = "SELECT COUNT(*) as total FROM otc_sales os $where_clause";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
$total_pages = ceil($total_records / $limit);

// ================================================================
// GET OTC SALES DATA with bill payment info
// ================================================================
$sql = "
    SELECT 
        os.id,
        os.sale_number,
        os.customer_name,
        os.customer_phone,
        os.total_amount,
        os.subtotal,
        os.discount_amount,
        os.payment_status,
        os.payment_method,
        os.bill_id,
        os.sold_by,
        os.notes,
        os.created_at,
        os.updated_at,
        os.branch_id,
        COALESCE(b.paid_amount, 0) as paid_amount,
        COALESCE(b.status, 'pending') as bill_status
    FROM otc_sales os
    LEFT JOIN bills b ON os.bill_id = b.id
    $where_clause
    ORDER BY os.created_at DESC
    LIMIT ? OFFSET ?
";

// Create new params array for this query (excluding branch filter since it's in the WHERE clause string)
$query_params = $params;
$query_params[] = $limit;
$query_params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($query_params);
$otc_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STATISTICS
// ================================================================
$stats_sql = "
    SELECT 
        COUNT(*) as total_sales,
        COALESCE(SUM(os.total_amount), 0) as total_revenue,
        COALESCE(SUM(b.paid_amount), 0) as total_paid,
        COALESCE(SUM(os.total_amount - COALESCE(b.paid_amount, 0)), 0) as total_balance
    FROM otc_sales os
    LEFT JOIN bills b ON os.bill_id = b.id
    $where_clause
";

$stmt = $db->prepare($stats_sql);
$stmt->execute($params);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

$total_sales = $stats['total_sales'] ?? 0;
$total_revenue = $stats['total_revenue'] ?? 0;
$total_paid = $stats['total_paid'] ?? 0;
$total_balance = $stats['total_balance'] ?? 0;

// ================================================================
// GET RECENT OTC SALES (Last 5)
// ================================================================
$recent_sql = "
    SELECT 
        os.id,
        os.sale_number,
        os.customer_name,
        os.total_amount,
        os.payment_status,
        os.created_at,
        COALESCE(b.paid_amount, 0) as paid_amount
    FROM otc_sales os
    LEFT JOIN bills b ON os.bill_id = b.id
    $where_clause
    ORDER BY os.created_at DESC
    LIMIT 5
";

$stmt = $db->prepare($recent_sql);
$stmt->execute($params);
$recent_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTC Sales - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_url ?>" type="image/png">
    
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
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 25px rgba(0,0,0,0.12);
            --radius: 12px;
            --radius-lg: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 8px 25px rgba(0,0,0,0.4);
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
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow-sm);
        }
        
        .top-nav .search-wrapper {
            display: flex;
            align-items: center;
            background: var(--bg-body);
            border-radius: var(--radius);
            border: 2px solid var(--border-color);
            transition: all 0.3s;
            flex: 1;
            max-width: 500px;
        }
        
        .top-nav .search-wrapper:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.12);
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
            border-radius: 0 var(--radius) var(--radius) 0;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .top-nav .search-wrapper .search-btn:hover {
            background: var(--primary-dark);
            transform: scale(1.02);
        }
        
        .top-nav .datetime {
            font-size: 0.78rem;
            color: var(--text-secondary);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .top-nav .datetime i {
            color: var(--primary-light);
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
        
        .dark-toggle-btn {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
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
        
        .branch-selector {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            padding: 6px 12px;
            font-size: 0.78rem;
            color: var(--text-primary);
            outline: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .branch-selector:focus {
            border-color: var(--primary);
        }
        
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
            background: var(--primary);
            border-radius: var(--radius-lg);
            padding: 24px 32px;
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            box-shadow: 0 4px 24px rgba(11, 94, 215, 0.2);
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
            background: rgba(255,255,255,0.04);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .page-header::after {
            content: '';
            position: absolute;
            bottom: -40%;
            left: -5%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.02);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .page-header .page-title {
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
        
        .page-header .page-title i {
            font-size: 1.8rem;
            opacity: 0.9;
        }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-subtitle strong {
            color: white;
            font-weight: 600;
        }
        
        .page-header .header-badge {
            background: rgba(255,255,255,0.1);
            color: white;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 500;
            backdrop-filter: blur(4px);
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.1);
            color: white;
            border: 1px solid rgba(255,255,255,0.15);
            padding: 6px 16px;
            border-radius: var(--radius);
            font-weight: 500;
            font-size: 0.8rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            backdrop-filter: blur(4px);
            position: relative;
            z-index: 1;
        }
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        /* ================================================================
           STAT CARDS
           ================================================================ */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 20px 22px;
            border: 1px solid var(--border-color);
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
        }
        
        .stat-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-4px);
        }
        
        .stat-card .stat-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        
        .stat-card .stat-number {
            font-size: 1.7rem;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.2;
            margin-top: 4px;
        }
        
        .stat-card .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            background: var(--primary-bg);
            color: var(--primary);
        }
        
        .stat-card .stat-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .stat-card .stat-sub {
            font-size: 0.65rem;
            color: var(--text-secondary);
            margin-top: 2px;
        }
        
        /* ================================================================
           CARD
           ================================================================ */
        .card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            overflow: hidden;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
        }
        
        .card:hover { box-shadow: var(--shadow-md); }
        
        .card-header {
            padding: 12px 16px;
            background: var(--bg-body);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        [data-theme="dark"] .card-header { background: #0F172A; }
        
        .card-title {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .card-title i { color: var(--primary); }
        
        .table-wrapper {
            overflow-x: auto;
            padding: 0;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
        }
        
        .data-table thead th {
            background: var(--primary);
            color: white;
            font-weight: 600;
            padding: 8px 12px;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            text-align: left;
            white-space: nowrap;
        }
        
        .data-table td {
            padding: 8px 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .data-table tbody tr:hover {
            background: var(--primary-bg);
        }
        
        [data-theme="dark"] .data-table tbody tr:hover {
            background: rgba(11, 94, 215, 0.1);
        }
        
        .data-table tbody tr:last-child td { border-bottom: none; }
        
        .status-badge {
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .status-badge.paid {
            background: #D1FAE5;
            color: #065F46;
        }
        
        .status-badge.unpaid {
            background: #FEE2E2;
            color: #991B1B;
        }
        
        .status-badge.partial {
            background: #FEF3C7;
            color: #92400E;
        }
        
        [data-theme="dark"] .status-badge.paid {
            background: #064E3B;
            color: #6EE7B7;
        }
        
        [data-theme="dark"] .status-badge.unpaid {
            background: #7F1D1D;
            color: #FCA5A5;
        }
        
        [data-theme="dark"] .status-badge.partial {
            background: #78350F;
            color: #FCD34D;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 5px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.7rem;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-sm {
            padding: 3px 10px;
            font-size: 0.65rem;
            border-radius: 4px;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: scale(1.02);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 1.5px solid var(--border-color);
        }
        
        .btn-outline:hover {
            background: var(--bg-body);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .btn-success {
            background: #059669;
            color: white;
        }
        
        .btn-success:hover {
            background: #047857;
        }
        
        .btn-danger {
            background: #DC2626;
            color: white;
        }
        
        .btn-danger:hover {
            background: #B91C1C;
        }
        
        .btn-info {
            background: #0891B2;
            color: white;
        }
        
        .btn-info:hover {
            background: #0E7490;
        }
        
        .btn-warning {
            background: #D97706;
            color: white;
        }
        
        .btn-warning:hover {
            background: #B45309;
        }
        
        .pagination {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
        }
        
        .pagination .page-link {
            padding: 4px 10px;
            border-radius: 4px;
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.7rem;
            transition: var(--transition);
        }
        
        .pagination .page-link:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .pagination .page-link.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }
        
        .filter-form input,
        .filter-form select {
            padding: 5px 10px;
            border: 1.5px solid var(--border-color);
            border-radius: 6px;
            font-size: 0.7rem;
            background: var(--bg-body);
            color: var(--text-primary);
            outline: none;
            transition: var(--transition);
        }
        
        .filter-form input:focus,
        .filter-form select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
        }
        
        .filter-form input[type="date"] {
            max-width: 140px;
        }
        
        .footer {
            margin-top: 16px;
            padding: 10px 0;
            border-top: 1px solid var(--border-color);
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
        @media (max-width: 992px) {
            .stat-grid { grid-template-columns: repeat(2, 1fr); }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav { left: 0; }
        }
        
        @media (max-width: 768px) {
            .stat-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
            .stat-card .stat-number { font-size: 1.2rem; }
            .page-header { padding: 14px 18px; }
            .page-header .page-title { font-size: 1.1rem; }
            .filter-form input,
            .filter-form select { font-size: 0.65rem; padding: 4px 8px; }
        }
        
        @media (max-width: 480px) {
            .stat-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
            .stat-card { padding: 12px 14px; }
            .stat-card .stat-number { font-size: 1rem; }
            .stat-card .stat-label { font-size: 0.55rem; }
            .stat-card .stat-icon { width: 32px; height: 32px; font-size: 0.9rem; }
            .main-content { padding: 10px; }
        }
        
        @media print {
            .top-nav, .sidebar, .btn, .dark-toggle-btn, .icon-btn,
            .search-wrapper, .page-header .btn-outline-light,
            .footer, #sidebarToggle, .filter-form { display: none !important; }
            .main-content { margin: 0; padding: 20px; }
            .stat-card { border: 1px solid #ddd !important; }
            .data-table thead th { background: #0B5ED7 !important; color: white !important; }
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
            <input type="text" id="searchInput" placeholder="Search OTC sales by invoice, customer, phone...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <select id="branchSelector" class="branch-selector" onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches as $branch): ?>
                <option value="<?= $branch['id'] ?>" <?= $selected_branch_id == $branch['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($branch['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn" title="Toggle Dark Mode">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <a href="../profile.php">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($user_full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-cash-register"></i> OTC Sales
            </h1>
            <p class="page-subtitle">
                Manage over-the-counter sales
                <span class="header-badge"><i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name_display) ?></span>
                <span class="header-badge"><i class="fas fa-calendar-day"></i> <?= date('F d, Y') ?></span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="add_otc_sale.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-plus-circle"></i> New OTC Sale
            </a>
            <button onclick="location.reload()" class="btn-outline-light">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS -->
    <!-- ================================================================ -->
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">Total Sales</p>
                    <p class="stat-number"><?= number_format($total_sales) ?></p>
                    <p class="stat-sub">Transactions</p>
                </div>
                <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">Total Revenue</p>
                    <p class="stat-number">TSh <?= number_format($total_revenue) ?></p>
                    <p class="stat-sub">All sales</p>
                </div>
                <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">Amount Paid</p>
                    <p class="stat-number">TSh <?= number_format($total_paid) ?></p>
                    <p class="stat-sub">Collected so far</p>
                </div>
                <div class="stat-icon"><i class="fas fa-check-circle" style="color: #059669;"></i></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">Outstanding Balance</p>
                    <p class="stat-number">TSh <?= number_format($total_balance) ?></p>
                    <p class="stat-sub">Pending payments</p>
                </div>
                <div class="stat-icon"><i class="fas fa-clock" style="color: #D97706;"></i></div>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- OTC SALES TABLE -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list"></i> OTC Sales List
                <span class="text-xs text-gray-400 font-normal ml-2"><?= $total_records ?> records found</span>
            </h3>
            
            <form method="GET" class="filter-form" id="filterForm">
                <input type="hidden" name="branch" value="<?= $selected_branch_id ?>">
                
                <input type="text" name="search" placeholder="Search..." value="<?= htmlspecialchars($search) ?>" class="w-32 md:w-40">
                
                <select name="status">
                    <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                    <option value="paid" <?= $status_filter === 'paid' ? 'selected' : '' ?>>Paid</option>
                    <option value="unpaid" <?= $status_filter === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                    <option value="partial" <?= $status_filter === 'partial' ? 'selected' : '' ?>>Partial</option>
                </select>
                
                <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" placeholder="From">
                <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" placeholder="To">
                
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-filter"></i> Filter
                </button>
                
                <a href="otc_sales.php?branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm">
                    <i class="fas fa-times"></i> Reset
                </a>
            </form>
        </div>
        
        <div class="table-wrapper">
            <?php if (count($otc_sales) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Sale Number</th>
                            <th>Customer</th>
                            <th>Phone</th>
                            <th>Total Amount</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Payment Method</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($otc_sales as $index => $sale): 
                            $balance = $sale['total_amount'] - ($sale['paid_amount'] ?? 0);
                            $status_class = match($sale['payment_status']) {
                                'paid' => 'paid',
                                'unpaid' => 'unpaid',
                                'partial' => 'partial',
                                default => 'unpaid'
                            };
                        ?>
                            <tr>
                                <td><?= $offset + $index + 1 ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($sale['sale_number']) ?></strong>
                                </td>
                                <td><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?></td>
                                <td><?= htmlspecialchars($sale['customer_phone'] ?? '-') ?></td>
                                <td><strong>TSh <?= number_format($sale['total_amount']) ?></strong></td>
                                <td>TSh <?= number_format($sale['paid_amount'] ?? 0) ?></td>
                                <td>
                                    <?php if ($balance > 0): ?>
                                        <span class="text-red-600 dark:text-red-400">TSh <?= number_format($balance) ?></span>
                                    <?php else: ?>
                                        <span class="text-green-600 dark:text-green-400">TSh 0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?= $status_class ?>">
                                        <?php if ($sale['payment_status'] === 'paid'): ?>
                                            <i class="fas fa-check-circle"></i> Paid
                                        <?php elseif ($sale['payment_status'] === 'partial'): ?>
                                            <i class="fas fa-clock"></i> Partial
                                        <?php else: ?>
                                            <i class="fas fa-times-circle"></i> Unpaid
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($sale['payment_method']): ?>
                                        <span class="text-xs">
                                            <?php if ($sale['payment_method'] === 'cash'): ?>
                                                <i class="fas fa-money-bill"></i> Cash
                                            <?php elseif ($sale['payment_method'] === 'mpesa'): ?>
                                                <i class="fas fa-mobile-alt"></i> M-Pesa
                                            <?php elseif ($sale['payment_method'] === 'bank'): ?>
                                                <i class="fas fa-university"></i> Bank
                                            <?php else: ?>
                                                <?= htmlspecialchars($sale['payment_method']) ?>
                                            <?php endif; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-400 text-xs">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="text-xs">
                                        <?= date('d/m/Y H:i', strtotime($sale['created_at'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="flex gap-1">
                                        <a href="view_otc_sale.php?id=<?= $sale['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                           class="btn btn-info btn-sm" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($sale['payment_status'] !== 'paid'): ?>
                                            <a href="receive_payment.php?id=<?= $sale['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                               class="btn btn-success btn-sm" title="Receive Payment">
                                                <i class="fas fa-money-bill-wave"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="print_otc_receipt.php?id=<?= $sale['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                           class="btn btn-outline btn-sm" title="Print Receipt" target="_blank">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="text-center py-8">
                    <i class="fas fa-shopping-cart text-4xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500">No OTC sales found</p>
                    <p class="text-gray-400 text-sm">Try adjusting your filters or create a new sale</p>
                    <a href="add_otc_sale.php?branch=<?= $selected_branch_id ?>" class="btn btn-primary mt-4">
                        <i class="fas fa-plus-circle"></i> New OTC Sale
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="p-3 flex justify-between items-center flex-wrap gap-2 border-t border-gray-200 dark:border-gray-700">
                <span class="text-xs text-gray-500">
                    Showing <?= $offset + 1 ?> - <?= min($offset + $limit, $total_records) ?> of <?= $total_records ?> records
                </span>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>&branch=<?= $selected_branch_id ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" 
                           class="page-link">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="page-link active"><?= $i ?></span>
                        <?php elseif ($i <= 3 || $i > $total_pages - 3 || abs($i - $page) <= 2): ?>
                            <a href="?page=<?= $i ?>&branch=<?= $selected_branch_id ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" 
                               class="page-link">
                                <?= $i ?>
                            </a>
                        <?php elseif ($i == 4 || $i == $total_pages - 3): ?>
                            <span class="page-link">...</span>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?>&branch=<?= $selected_branch_id ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" 
                           class="page-link">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT OTC SALES -->
    <!-- ================================================================ -->
    <?php if (count($recent_sales) > 0): ?>
        <div class="card mt-4">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-clock"></i> Recent OTC Sales
                </h3>
                <a href="otc_sales.php?branch=<?= $selected_branch_id ?>" class="text-xs text-blue-600 font-medium hover:underline">
                    View All →
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Sale Number</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Paid</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_sales as $sale): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($sale['sale_number']) ?></strong></td>
                                <td><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?></td>
                                <td><strong>TSh <?= number_format($sale['total_amount']) ?></strong></td>
                                <td>TSh <?= number_format($sale['paid_amount'] ?? 0) ?></td>
                                <td>
                                    <span class="status-badge <?= match($sale['payment_status']) { 'paid' => 'paid', 'unpaid' => 'unpaid', 'partial' => 'partial', default => 'unpaid' } ?>">
                                        <?= ucfirst($sale['payment_status']) ?>
                                    </span>
                                </td>
                                <td><span class="text-xs"><?= date('d/m/Y H:i', strtotime($sale['created_at'])) ?></span></td>
                                <td>
                                    <a href="view_otc_sale.php?id=<?= $sale['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                       class="btn btn-info btn-sm">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="mx-2">|</span>
            OTC Sales
            <span class="mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span class="mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

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
            document.cookie = "dark_mode=false; path=/";
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
            document.cookie = "dark_mode=true; path=/";
        }
    });

    // ================================================================
    // DOM ELEMENTS
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');

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

    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            var branch = '<?= $selected_branch_id ?>';
            window.location.href = 'otc_sales.php?search=' + encodeURIComponent(query) + '&branch=' + branch;
        }
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        window.location.href = url.toString();
    }

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

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            searchInput?.focus();
        }
    });

    console.log('%c🏥 Braick Dispensary - OTC Sales', 'font-size:18px; font-weight:bold; color:#D97706;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c💰 Total Revenue: TSh <?= number_format($total_revenue) ?>', 'font-size:13px; color:#D97706;');
    console.log('%c💵 Total Paid: TSh <?= number_format($total_paid) ?>', 'font-size:13px; color:#059669;');
    console.log('%c⏳ Outstanding: TSh <?= number_format($total_balance) ?>', 'font-size:13px; color:#DC2626;');
    console.log('%c📋 Total Transactions: <?= $total_sales ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c✅ FIXED: Invalid parameter number error resolved', 'font-size:13px; color:#059669;');
    console.log('%c✅ FIXED: paid_amount inaitwa kutoka bills table', 'font-size:13px; color:#059669;');
</script>

</body>
</html>