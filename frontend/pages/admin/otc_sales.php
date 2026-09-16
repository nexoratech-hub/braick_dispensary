<?php
// ================================================================
// FILE: frontend/pages/admin/otc_sales.php
// OTC SALES MANAGEMENT - Admin Module
// ✅ Uses SHARED header & sidebar
// ✅ Full dark mode support
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

$allowed_roles = ['admin', 'pharmacy', 'cashier'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: ../dashboard.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'User';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// BRANCH SELECTION
// ================================================================
$selected_branch_id = $_GET['branch'] ?? 'all';
$branch_name_display = 'All Branches';

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
// FILTER PARAMETERS
// ================================================================
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'all';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// ================================================================
// BUILD WHERE
// ================================================================
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(os.sale_number LIKE ? OR os.customer_name LIKE ? OR os.customer_phone LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($status_filter !== 'all') {
    $where_conditions[] = "os.payment_status = ?";
    $params[] = $status_filter;
}

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

if ($selected_branch_id !== 'all') {
    if (!empty($where_clause)) {
        $where_clause .= " AND os.branch_id = " . (int)$selected_branch_id;
    } else {
        $where_clause = "WHERE os.branch_id = " . (int)$selected_branch_id;
    }
}

// ================================================================
// TOTAL COUNT
// ================================================================
$count_sql = "SELECT COUNT(*) as total FROM otc_sales os $where_clause";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
$total_pages = ceil($total_records / $limit);

// ================================================================
// GET OTC SALES
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

$query_params = $params;
$query_params[] = $limit;
$query_params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($query_params);
$otc_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// STATISTICS
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
// RECENT OTC SALES
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
// URLS
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

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC CSS - FULL DARK MODE -->
<!-- ================================================================ -->
<style>
    /* LIGHT MODE */
    :root {
        --page-bg-body: #F1F5F9;
        --page-bg-card: #FFFFFF;
        --page-text-primary: #1E293B;
        --page-text-secondary: #64748B;
        --page-text-muted: #94A3B8;
        --page-border: #E2E8F0;
        --page-hover: #E8F0FE;
    }

    /* DARK MODE */
    [data-theme="dark"] {
        --page-bg-body: #0F172A;
        --page-bg-card: #1E293B;
        --page-text-primary: #F1F5F9;
        --page-text-secondary: #94A3B8;
        --page-text-muted: #64748B;
        --page-border: #334155;
        --page-hover: #1E3A5F;
    }

    /* BODY & MAIN CONTENT */
    body { background: var(--page-bg-body, #F1F5F9); }
    html[data-theme="dark"] body { background: #0F172A !important; }
    .main-content { background: var(--page-bg-body, #F1F5F9); }
    html[data-theme="dark"] .main-content { background: #0F172A !important; color: #F1F5F9; }

    /* ================================================================
       BLUE PAGE HEADER
       ================================================================ */
    .page-header-custom {
        background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 50%, #083D8A 100%);
        border-radius: 18px;
        padding: 24px 32px;
        margin-bottom: 24px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        box-shadow: 0 8px 32px rgba(11, 94, 215, 0.35), 0 4px 12px rgba(11, 94, 215, 0.2);
        position: relative;
        overflow: hidden;
    }

    .page-header-custom::before {
        content: '';
        position: absolute;
        top: -60%;
        right: -10%;
        width: 400px;
        height: 400px;
        background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .page-header-custom::after {
        content: '';
        position: absolute;
        bottom: -60%;
        left: -5%;
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .page-header-custom .page-title {
        color: white;
        font-size: 1.6rem;
        font-weight: 800;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin: 0;
        letter-spacing: -0.02em;
    }

    .page-header-custom .page-title i {
        width: 44px;
        height: 44px;
        background: rgba(255,255,255,0.2);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.2);
    }

    .page-header-custom .page-subtitle {
        color: rgba(255,255,255,0.9);
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin-top: 8px;
    }

    .page-header-custom .header-badge {
        background: rgba(255,255,255,0.15);
        color: white;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        backdrop-filter: blur(4px);
        display: inline-flex;
        align-items: center;
        gap: 5px;
        border: 1px solid rgba(255,255,255,0.2);
    }

    .page-header-custom .btn-outline-light {
        background: rgba(255,255,255,0.15);
        color: white;
        border: 1.5px solid rgba(255,255,255,0.3);
        padding: 8px 18px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.8rem;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        backdrop-filter: blur(4px);
        position: relative;
        z-index: 1;
        cursor: pointer;
        white-space: nowrap;
    }

    .page-header-custom .btn-outline-light:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        color: white;
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

    .stat-card-custom {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 14px;
        padding: 18px 20px;
        border: 2px solid var(--page-border, #E2E8F0);
        transition: all 0.3s ease;
    }

    html[data-theme="dark"] .stat-card-custom {
        background: #1E293B;
        border-color: #334155;
    }

    .stat-card-custom:hover {
        border-color: #0B5ED7;
        transform: translateY(-4px);
        box-shadow: 0 8px 25px rgba(11, 94, 215, 0.15);
    }

    .stat-card-custom .stat-label {
        font-size: 0.7rem;
        color: var(--page-text-secondary, #64748B);
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .stat-card-custom .stat-number {
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--page-text-primary, #1E293B);
        line-height: 1.2;
        margin-top: 4px;
    }

    html[data-theme="dark"] .stat-card-custom .stat-number { color: #F1F5F9; }

    .stat-card-custom .stat-icon-custom {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        background: #E8F0FE;
        color: #0B5ED7;
        flex-shrink: 0;
    }

    html[data-theme="dark"] .stat-card-custom .stat-icon-custom {
        background: #1E3A5F;
        color: #6EA8FE;
    }

    .stat-card-custom .stat-icon-custom.green { background: #D1FAE5; color: #059669; }
    .stat-card-custom .stat-icon-custom.orange { background: #FEF3C7; color: #D97706; }
    html[data-theme="dark"] .stat-card-custom .stat-icon-custom.green { background: #1A3A2A; color: #34D399; }
    html[data-theme="dark"] .stat-card-custom .stat-icon-custom.orange { background: #3A2A1A; color: #FBBF24; }

    .stat-card-custom .stat-sub {
        font-size: 0.65rem;
        color: var(--page-text-muted, #94A3B8);
        margin-top: 2px;
    }

    /* ================================================================
       CARD
       ================================================================ */
    .card-custom {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 16px;
        border: 2px solid var(--page-border, #E2E8F0);
        overflow: hidden;
        margin-bottom: 24px;
    }

    html[data-theme="dark"] .card-custom {
        background: #1E293B;
        border-color: #334155;
    }

    .card-header-custom {
        padding: 14px 20px;
        background: var(--page-hover, #F8FAFC);
        border-bottom: 2px solid var(--page-border, #E2E8F0);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }

    html[data-theme="dark"] .card-header-custom {
        background: #0F172A;
        border-color: #334155;
    }

    .card-title-custom {
        font-size: 0.9rem;
        font-weight: 700;
        color: var(--page-text-primary, #1E293B);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    html[data-theme="dark"] .card-title-custom { color: #F1F5F9; }
    .card-title-custom i { color: #0B5ED7; }
    html[data-theme="dark"] .card-title-custom i { color: #6EA8FE; }

    .card-title-custom .count-badge {
        font-size: 0.72rem;
        color: var(--page-text-secondary, #64748B);
        font-weight: 400;
        margin-left: 4px;
    }

    /* ================================================================
       FILTER FORM
       ================================================================ */
    .filter-form-custom {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
    }

    .filter-form-custom input,
    .filter-form-custom select {
        padding: 6px 12px;
        border: 2px solid var(--page-border, #E2E8F0);
        border-radius: 8px;
        font-size: 0.72rem;
        background: var(--page-bg-body, #F1F5F9);
        color: var(--page-text-primary, #1E293B);
        outline: none;
        transition: all 0.3s;
    }

    html[data-theme="dark"] .filter-form-custom input,
    html[data-theme="dark"] .filter-form-custom select {
        background: #0F172A;
        color: #F1F5F9;
        border-color: #334155;
    }

    .filter-form-custom input:focus,
    .filter-form-custom select:focus {
        border-color: #0B5ED7;
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
    }

    .filter-form-custom input[type="date"] {
        max-width: 140px;
    }

    /* ================================================================
       DATA TABLE
       ================================================================ */
    .table-wrapper-custom {
        overflow-x: auto;
    }

    .data-table-custom {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.78rem;
    }

    .data-table-custom thead th {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
        font-weight: 700;
        padding: 10px 14px;
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        text-align: left;
        white-space: nowrap;
    }

    .data-table-custom thead th:first-child { border-radius: 10px 0 0 0; }
    .data-table-custom thead th:last-child { border-radius: 0 10px 0 0; }

    .data-table-custom td {
        padding: 10px 14px;
        border-bottom: 1px solid var(--page-border, #E2E8F0);
        color: var(--page-text-primary, #1E293B);
        vertical-align: middle;
    }

    html[data-theme="dark"] .data-table-custom td {
        color: #F1F5F9;
        border-bottom-color: #334155;
    }

    .data-table-custom tbody tr:nth-child(even) td {
        background: var(--page-hover, #F8FAFC);
    }

    html[data-theme="dark"] .data-table-custom tbody tr:nth-child(even) td {
        background: #1E3A5F;
    }

    .data-table-custom tbody tr:hover td {
        background: #E8F0FE;
    }

    html[data-theme="dark"] .data-table-custom tbody tr:hover td {
        background: #1E40AF;
    }

    .data-table-custom tbody tr:last-child td { border-bottom: none; }

    /* ================================================================
       STATUS BADGES
       ================================================================ */
    .status-badge-custom {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .status-badge-custom.paid {
        background: #D1FAE5;
        color: #065F46;
        border: 1px solid #A7F3D0;
    }

    .status-badge-custom.unpaid {
        background: #FEE2E2;
        color: #991B1B;
        border: 1px solid #FECACA;
    }

    .status-badge-custom.partial {
        background: #FEF3C7;
        color: #92400E;
        border: 1px solid #FCD34D;
    }

    html[data-theme="dark"] .status-badge-custom.paid {
        background: #1A3A2A;
        color: #34D399;
        border-color: #065F46;
    }

    html[data-theme="dark"] .status-badge-custom.unpaid {
        background: #3A1A1A;
        color: #F87171;
        border-color: #7F1D1D;
    }

    html[data-theme="dark"] .status-badge-custom.partial {
        background: #3A2A1A;
        color: #FBBF24;
        border-color: #78350F;
    }

    /* ================================================================
       BUTTONS
       ================================================================ */
    .btn-custom {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 6px 14px;
        border-radius: 8px;
        font-weight: 700;
        font-size: 0.72rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        white-space: nowrap;
    }

    .btn-primary-custom {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        color: white;
    }
    .btn-primary-custom:hover {
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }

    .btn-outline-custom {
        background: transparent;
        color: var(--page-text-secondary, #64748B);
        border: 2px solid var(--page-border, #E2E8F0);
    }
    .btn-outline-custom:hover {
        background: var(--page-bg-body, #F1F5F9);
        border-color: #0B5ED7;
        color: #0B5ED7;
        transform: translateY(-2px);
    }

    .btn-info-custom {
        background: #0B5ED7;
        color: white;
    }
    .btn-info-custom:hover {
        background: #0A4CA8;
        color: white;
        transform: scale(1.05);
    }

    .btn-success-custom {
        background: #059669;
        color: white;
    }
    .btn-success-custom:hover {
        background: #047857;
        color: white;
        transform: scale(1.05);
    }

    .btn-sm-custom {
        padding: 5px 10px;
        font-size: 0.68rem;
        border-radius: 6px;
    }

    /* ================================================================
       PAGINATION
       ================================================================ */
    .pagination-custom {
        display: flex;
        gap: 4px;
        flex-wrap: wrap;
    }

    .pagination-custom .page-link {
        padding: 5px 12px;
        border-radius: 6px;
        border: 1.5px solid var(--page-border, #E2E8F0);
        color: var(--page-text-secondary, #64748B);
        text-decoration: none;
        font-size: 0.72rem;
        font-weight: 600;
        transition: all 0.3s;
        background: var(--page-bg-card, #FFFFFF);
    }

    html[data-theme="dark"] .pagination-custom .page-link {
        background: #1E293B;
        color: #F1F5F9;
        border-color: #334155;
    }

    .pagination-custom .page-link:hover {
        background: #0B5ED7;
        color: white;
        border-color: #0B5ED7;
    }

    .pagination-custom .page-link.active {
        background: #0B5ED7;
        color: white;
        border-color: #0B5ED7;
    }

    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 992px) {
        .stat-grid { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 768px) {
        .stat-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
        .stat-card-custom .stat-number { font-size: 1.2rem; }
        .page-header-custom { padding: 18px; }
        .page-header-custom .page-title { font-size: 1.15rem; }
        .filter-form-custom input, .filter-form-custom select { font-size: 0.68rem; padding: 5px 8px; }
    }

    @media (max-width: 480px) {
        .stat-grid { grid-template-columns: 1fr; gap: 8px; }
        .stat-card-custom { padding: 14px 16px; }
    }

    @media print {
        .page-header-custom { background: #0B5ED7 !important; -webkit-print-color-adjust: exact; }
        .filter-form-custom, .btn-custom, .pagination-custom { display: none !important; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- BLUE PAGE HEADER -->
    <div class="page-header-custom">
        <div style="position:relative;z-index:1;">
            <h1 class="page-title">
                <i class="fas fa-cash-register"></i>
                OTC Sales
            </h1>
            <p class="page-subtitle">
                <span>Manage over-the-counter sales</span>
                <span class="header-badge"><i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name_display) ?></span>
                <span class="header-badge"><i class="fas fa-calendar-day"></i> <?= date('F d, Y') ?></span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="add_otc_sale.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-plus-circle"></i> New OTC Sale
            </a>
            <button onclick="location.reload()" class="btn-outline-light">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- STATS -->
    <div class="stat-grid">
        <div class="stat-card-custom">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                <div>
                    <p class="stat-label">Total Sales</p>
                    <p class="stat-number"><?= number_format($total_sales) ?></p>
                    <p class="stat-sub">Transactions</p>
                </div>
                <div class="stat-icon-custom"><i class="fas fa-shopping-cart"></i></div>
            </div>
        </div>
        
        <div class="stat-card-custom">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                <div>
                    <p class="stat-label">Total Revenue</p>
                    <p class="stat-number">TSh <?= number_format($total_revenue) ?></p>
                    <p class="stat-sub">All sales</p>
                </div>
                <div class="stat-icon-custom"><i class="fas fa-money-bill-wave"></i></div>
            </div>
        </div>
        
        <div class="stat-card-custom">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                <div>
                    <p class="stat-label">Amount Paid</p>
                    <p class="stat-number">TSh <?= number_format($total_paid) ?></p>
                    <p class="stat-sub">Collected</p>
                </div>
                <div class="stat-icon-custom green"><i class="fas fa-check-circle"></i></div>
            </div>
        </div>
        
        <div class="stat-card-custom">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                <div>
                    <p class="stat-label">Outstanding</p>
                    <p class="stat-number">TSh <?= number_format($total_balance) ?></p>
                    <p class="stat-sub">Pending</p>
                </div>
                <div class="stat-icon-custom orange"><i class="fas fa-clock"></i></div>
            </div>
        </div>
    </div>

    <!-- OTC SALES TABLE -->
    <div class="card-custom">
        <div class="card-header-custom">
            <h3 class="card-title-custom">
                <i class="fas fa-list"></i> OTC Sales List
                <span class="count-badge"><?= $total_records ?> records</span>
            </h3>
            
            <form method="GET" class="filter-form-custom" id="filterForm">
                <input type="hidden" name="branch" value="<?= $selected_branch_id ?>">
                
                <input type="text" name="search" placeholder="Search..." value="<?= htmlspecialchars($search) ?>" style="width:140px;">
                
                <select name="status">
                    <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                    <option value="paid" <?= $status_filter === 'paid' ? 'selected' : '' ?>>Paid</option>
                    <option value="unpaid" <?= $status_filter === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                    <option value="partial" <?= $status_filter === 'partial' ? 'selected' : '' ?>>Partial</option>
                </select>
                
                <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
                
                <button type="submit" class="btn-custom btn-primary-custom btn-sm-custom">
                    <i class="fas fa-filter"></i> Filter
                </button>
                
                <a href="otc_sales.php?branch=<?= $selected_branch_id ?>" class="btn-custom btn-outline-custom btn-sm-custom">
                    <i class="fas fa-times"></i> Reset
                </a>
            </form>
        </div>
        
        <div class="table-wrapper-custom">
            <?php if (count($otc_sales) > 0): ?>
                <table class="data-table-custom">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Sale Number</th>
                            <th>Customer</th>
                            <th>Phone</th>
                            <th>Total</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($otc_sales as $index => $sale): 
                            $balance = $sale['total_amount'] - ($sale['paid_amount'] ?? 0);
                            $status_class = $sale['payment_status'] ?? 'unpaid';
                        ?>
                            <tr>
                                <td style="font-weight:600;color:#0B5ED7;"><?= $offset + $index + 1 ?></td>
                                <td><strong><?= htmlspecialchars($sale['sale_number']) ?></strong></td>
                                <td><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?></td>
                                <td><?= htmlspecialchars($sale['customer_phone'] ?? '-') ?></td>
                                <td><strong>TSh <?= number_format($sale['total_amount']) ?></strong></td>
                                <td>TSh <?= number_format($sale['paid_amount'] ?? 0) ?></td>
                                <td>
                                    <?php if ($balance > 0): ?>
                                        <span style="color:#DC2626;font-weight:700;">TSh <?= number_format($balance) ?></span>
                                    <?php else: ?>
                                        <span style="color:#059669;font-weight:700;">TSh 0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge-custom <?= $status_class ?>">
                                        <?php if ($status_class === 'paid'): ?>
                                            <i class="fas fa-check-circle"></i> Paid
                                        <?php elseif ($status_class === 'partial'): ?>
                                            <i class="fas fa-clock"></i> Partial
                                        <?php else: ?>
                                            <i class="fas fa-times-circle"></i> Unpaid
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($sale['payment_method']): ?>
                                        <span style="font-size:0.7rem;">
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
                                        <span style="color:var(--page-text-muted);font-size:0.7rem;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="font-size:0.7rem;">
                                        <?= date('d/m/Y H:i', strtotime($sale['created_at'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display:flex;gap:4px;">
                                        <a href="view_otc_sale.php?id=<?= $sale['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                           class="btn-custom btn-info-custom btn-sm-custom" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($sale['payment_status'] !== 'paid'): ?>
                                            <a href="receive_payment.php?id=<?= $sale['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                               class="btn-custom btn-success-custom btn-sm-custom" title="Receive Payment">
                                                <i class="fas fa-money-bill-wave"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="print_otc_receipt.php?id=<?= $sale['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                           class="btn-custom btn-outline-custom btn-sm-custom" title="Print Receipt" target="_blank">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align:center;padding:48px 20px;color:var(--page-text-muted);">
                    <i class="fas fa-shopping-cart" style="font-size:3rem;color:var(--page-text-muted);opacity:0.5;display:block;margin-bottom:12px;"></i>
                    <p style="font-size:1rem;font-weight:600;color:var(--page-text-primary);margin-bottom:4px;">No OTC sales found</p>
                    <p style="font-size:0.85rem;margin-bottom:16px;">Try adjusting your filters or create a new sale</p>
                    <a href="add_otc_sale.php?branch=<?= $selected_branch_id ?>" class="btn-custom btn-primary-custom">
                        <i class="fas fa-plus-circle"></i> New OTC Sale
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- PAGINATION -->
        <?php if ($total_pages > 1): ?>
            <div style="padding:14px 20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;border-top:2px solid var(--page-border);">
                <span style="font-size:0.78rem;color:var(--page-text-secondary);">
                    Showing <?= $offset + 1 ?> - <?= min($offset + $limit, $total_records) ?> of <?= $total_records ?> records
                </span>
                <div class="pagination-custom">
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

    <!-- RECENT OTC SALES -->
    <?php if (count($recent_sales) > 0): ?>
        <div class="card-custom">
            <div class="card-header-custom">
                <h3 class="card-title-custom">
                    <i class="fas fa-clock"></i> Recent OTC Sales
                </h3>
                <a href="otc_sales.php?branch=<?= $selected_branch_id ?>" style="font-size:0.78rem;color:#0B5ED7;font-weight:600;text-decoration:none;">
                    View All →
                </a>
            </div>
            <div class="table-wrapper-custom">
                <table class="data-table-custom">
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
                                    <span class="status-badge-custom <?= $sale['payment_status'] ?? 'unpaid' ?>">
                                        <?= ucfirst($sale['payment_status'] ?? 'unpaid') ?>
                                    </span>
                                </td>
                                <td><span style="font-size:0.7rem;"><?= date('d/m/Y H:i', strtotime($sale['created_at'])) ?></span></td>
                                <td>
                                    <a href="view_otc_sale.php?id=<?= $sale['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                       class="btn-custom btn-info-custom btn-sm-custom">
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

</main>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE BACKGROUND ENFORCEMENT
    // ================================================================
    function enforceDarkModeBackground() {
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        var body = document.body;
        var mainContent = document.querySelector('.main-content');
        
        if (isDark) {
            if (body) body.style.background = '#0F172A';
            if (mainContent) mainContent.style.background = '#0F172A';
        } else {
            if (body) body.style.background = '#F1F5F9';
            if (mainContent) mainContent.style.background = '#F1F5F9';
        }
    }

    enforceDarkModeBackground();

    document.addEventListener('darkModeChanged', function(e) {
        setTimeout(enforceDarkModeBackground, 50);
    });

    var observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.attributeName === 'data-theme') {
                enforceDarkModeBackground();
            }
        });
    });
    observer.observe(document.documentElement, { attributes: true });

    // ================================================================
    // KEYBOARD SHORTCUT - Ctrl+K to focus search
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            var si = document.querySelector('.filter-form-custom input[name="search"]');
            if (si) si.focus();
        }
    });

    console.log('%c🏥 Braick - OTC Sales', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#059669;');
    console.log('%c🌙 FULL DARK MODE inafanya kazi', 'font-size:13px; color:#7C3AED;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c💰 Total Revenue: TSh <?= number_format($total_revenue) ?>', 'font-size:13px; color:#D97706;');
    console.log('%c💵 Total Paid: TSh <?= number_format($total_paid) ?>', 'font-size:13px; color:#059669;');
    console.log('%c⏳ Outstanding: TSh <?= number_format($total_balance) ?>', 'font-size:13px; color:#DC2626;');
    console.log('%c💡 Keyboard: Ctrl+K = Search', 'font-size:13px; color:#64748B;');
</script>

</body>
</html>