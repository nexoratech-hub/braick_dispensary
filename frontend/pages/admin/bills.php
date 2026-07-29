<?php
// ================================================================
// FILE: frontend/pages/admin/bills.php
// SUPER ADMIN - BILLS MANAGEMENT
// WITH TIME PERIOD FILTERS & EXPORT (FIXED)
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['user_id'] = 1;
    $_SESSION['full_name'] = 'Admin John';
    $_SESSION['role'] = 'admin';
    $_SESSION['branch_id'] = 1;
}

// Include database
require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// BRANCH SELECTION
// ================================================================
$selected_branch_id = isset($_GET['branch']) ? $_GET['branch'] : 'all';
$branch_name = 'All Branches';

// ================================================================
// FUNCTION TO CHECK IF COLUMN EXISTS
// ================================================================
function columnExists($db, $table, $column) {
    try {
        $stmt = $db->query("SHOW COLUMNS FROM $table LIKE '$column'");
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

// ================================================================
// BRANCH NAME
// ================================================================
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $branch_id = (int)$selected_branch_id;
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) {
        $branch_name = $branch_data['name'];
    }
} else {
    $selected_branch_id = 'all';
}

// ================================================================
// GET BRANCHES FOR FILTER
// ================================================================
$branches = [];
$stmt = $db->query("SELECT id, name, location FROM branches WHERE status = 'active'");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET FILTER PARAMETERS
// ================================================================
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search_filter = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at_desc';
$time_period = isset($_GET['period']) ? $_GET['period'] : 'all';

// ================================================================
// BUILD TIME PERIOD FILTER
// ================================================================
$date_condition = '';

switch ($time_period) {
    case 'today':
        $date_condition = "DATE(pb.created_at) = CURDATE()";
        break;
    case 'week':
        $date_condition = "pb.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case 'month':
        $date_condition = "pb.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        break;
    case '3months':
        $date_condition = "pb.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
        break;
    case '6months':
        $date_condition = "pb.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
        break;
    case 'year':
        $date_condition = "pb.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        break;
    case 'all':
    default:
        $date_condition = "1=1";
        break;
}

// ================================================================
// PERIOD LABEL
// ================================================================
$period_labels = [
    'today' => 'Today',
    'week' => 'This Week',
    'month' => 'This Month',
    '3months' => '3 Months',
    '6months' => '6 Months',
    'year' => '1 Year',
    'all' => 'All Time'
];
$period_label = $period_labels[$time_period] ?? 'All Time';

// ================================================================
// BUILD QUERY CONDITIONS
// ================================================================
$conditions = [$date_condition];
$params = [];

// Branch filter
if ($selected_branch_id !== 'all') {
    $conditions[] = "pb.branch_id = ?";
    $params[] = (int)$selected_branch_id;
}

// Status filter
if (!empty($status_filter)) {
    $conditions[] = "pb.status = ?";
    $params[] = $status_filter;
}

// Search filter
if (!empty($search_filter)) {
    $conditions[] = "(pb.bill_number LIKE ? OR p.full_name LIKE ? OR p.patient_id LIKE ?)";
    $params[] = "%$search_filter%";
    $params[] = "%$search_filter%";
    $params[] = "%$search_filter%";
}

// Custom date range (only when period is 'custom' and dates are provided)
if ($time_period === 'custom') {
    if (!empty($date_from)) {
        $conditions[] = "DATE(pb.created_at) >= ?";
        $params[] = $date_from;
    }
    if (!empty($date_to)) {
        $conditions[] = "DATE(pb.created_at) <= ?";
        $params[] = $date_to;
    }
}

$where_clause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// ================================================================
// GET TOTAL BILLS COUNT
// ================================================================
$count_sql = "
    SELECT COUNT(*) as total 
    FROM patient_bills pb
    LEFT JOIN patients p ON pb.patient_id = p.id
    $where_clause
";
$count_stmt = $db->prepare($count_sql);
$count_stmt->execute($params);
$total_bills = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// ================================================================
// GET BILLS WITH PAGINATION
// ================================================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;
$total_pages = $total_bills > 0 ? ceil($total_bills / $per_page) : 1;

// Sort
$sort_map = [
    'created_at_desc' => 'pb.created_at DESC',
    'created_at_asc' => 'pb.created_at ASC',
    'amount_desc' => 'pb.total_amount DESC',
    'amount_asc' => 'pb.total_amount ASC',
    'bill_number_asc' => 'pb.bill_number ASC',
    'bill_number_desc' => 'pb.bill_number DESC'
];
$order_by = $sort_map[$sort_by] ?? 'pb.created_at DESC';

$query_params = $params;

$sql = "
    SELECT 
        pb.*,
        p.full_name as patient_name,
        p.patient_id as patient_id_number,
        p.phone as patient_phone,
        u.full_name as created_by_name,
        b.name as branch_name,
        (SELECT COUNT(*) FROM bill_items WHERE bill_id = pb.id) as item_count,
        (SELECT COUNT(*) FROM payments WHERE bill_id = pb.id) as payment_count
    FROM patient_bills pb
    LEFT JOIN patients p ON pb.patient_id = p.id
    LEFT JOIN users u ON pb.created_by = u.id
    LEFT JOIN branches b ON pb.branch_id = b.id
    $where_clause
    ORDER BY $order_by
    LIMIT ? OFFSET ?
";

$query_params[] = $per_page;
$query_params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($query_params);
$bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// CALCULATE TOTALS FOR CURRENT PAGE
// ================================================================
$total_amount = 0;
$total_paid = 0;
$total_balance = 0;

foreach ($bills as $bill) {
    $total_amount += $bill['total_amount'];
    $total_paid += $bill['paid_amount'];
    $total_balance += $bill['balance'];
}

// ================================================================
// GET STATUS COUNTS (ALL BILLS)
// ================================================================
$status_count_sql = "
    SELECT status, COUNT(*) as count 
    FROM patient_bills pb
    LEFT JOIN patients p ON pb.patient_id = p.id
    $where_clause
    GROUP BY status
";
$status_count_stmt = $db->prepare($status_count_sql);
$status_count_stmt->execute($params);
$status_count_data = $status_count_stmt->fetchAll(PDO::FETCH_ASSOC);

$all_status_counts = [
    'pending' => 0,
    'partial' => 0,
    'paid' => 0,
    'cancelled' => 0
];
foreach ($status_count_data as $sc) {
    if (isset($all_status_counts[$sc['status']])) {
        $all_status_counts[$sc['status']] = (int)$sc['count'];
    }
}

// ================================================================
// GET STATUS BADGE CLASS
// ================================================================
function getStatusBadge($status) {
    $classes = [
        'paid' => 'success',
        'pending' => 'warning',
        'partial' => 'info',
        'cancelled' => 'danger'
    ];
    return $classes[$status] ?? 'secondary';
}

function getStatusIcon($status) {
    $icons = [
        'paid' => 'fa-check-circle',
        'pending' => 'fa-clock',
        'partial' => 'fa-hourglass-half',
        'cancelled' => 'fa-times-circle'
    ];
    return $icons[$status] ?? 'fa-circle';
}

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER
// ================================================================
include_once '../../components/admin_header.php';

// ================================================================
// INCLUDE SHARED SIDEBAR
// ================================================================
include_once '../../components/admin_sidebar.php';
?>

<style>
    /* ================================================================
       CUSTOM STYLES
       ================================================================ */
    
    .stat-card {
        border-radius: 12px;
        padding: 16px 20px;
        border: none;
        transition: all 0.3s ease;
        color: white;
        min-height: 90px;
        display: block;
        position: relative;
        overflow: hidden;
    }
    
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }
    
    .stat-card.total { background: #0B5ED7; }
    .stat-card.pending { background: #F59E0B; }
    .stat-card.partial { background: #7B2FBE; }
    .stat-card.paid { background: #059669; }
    
    .stat-card .stat-icon {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        background: rgba(255,255,255,0.2);
        color: white;
        flex-shrink: 0;
    }
    
    .stat-card .stat-number {
        font-size: 1.8rem;
        font-weight: 700;
        color: white;
        line-height: 1.2;
    }
    
    .stat-card .stat-label {
        font-size: 0.75rem;
        color: rgba(255,255,255,0.9);
        font-weight: 500;
        margin-bottom: 2px;
    }
    
    .stat-card .stat-trend {
        font-size: 0.6rem;
        font-weight: 500;
        padding: 2px 10px;
        border-radius: 12px;
        background: rgba(255,255,255,0.15);
        color: white;
        display: inline-block;
        margin-top: 2px;
    }
    
    /* Period Filters */
    .period-filters {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-bottom: 16px;
    }
    
    .period-btn {
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
    
    .period-btn:hover {
        border-color: #0B5ED7;
        color: #0B5ED7;
        background: #E8F0FE;
    }
    
    .period-btn.active {
        background: #0B5ED7;
        color: white;
        border-color: #0B5ED7;
    }
    
    .period-btn.active:hover {
        background: #0A4CA8;
        border-color: #0A4CA8;
    }
    
    [data-theme="dark"] .period-btn:hover {
        background: #1E3A5F;
        border-color: #6EA8FE;
        color: #6EA8FE;
    }
    
    [data-theme="dark"] .period-btn.active {
        background: #0B5ED7;
        color: white;
        border-color: #0B5ED7;
    }
    
    .period-btn i {
        margin-right: 4px;
    }
    
    .period-label-badge {
        display: inline-block;
        padding: 2px 12px;
        border-radius: 12px;
        font-size: 0.6rem;
        font-weight: 600;
        background: #E8F0FE;
        color: #0B5ED7;
    }
    
    [data-theme="dark"] .period-label-badge {
        background: #1E3A5F;
        color: #6EA8FE;
    }
    
    /* Financial Cards */
    .financial-card {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 14px 18px;
        border: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        gap: 14px;
        transition: all 0.3s ease;
    }
    
    .financial-card:hover {
        border-color: #0B5ED7;
        box-shadow: 0 2px 12px rgba(0,0,0,0.05);
    }
    
    .financial-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
    }
    
    .financial-icon.blue { background: #EFF6FF; color: #0B5ED7; }
    .financial-icon.green { background: #ECFDF5; color: #059669; }
    .financial-icon.orange { background: #FFFBEB; color: #F59E0B; }
    
    [data-theme="dark"] .financial-icon.blue { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .financial-icon.green { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .financial-icon.orange { background: #3D2E0A; color: #FBBF24; }
    
    .financial-label {
        font-size: 0.6rem;
        color: var(--text-secondary);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        margin: 0;
    }
    
    .financial-value {
        font-size: 1rem;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0;
    }
    
    /* Filter Section */
    .filter-section {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 16px 20px;
        border: 1px solid var(--border-color);
    }
    
    .filter-row {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: flex-end;
    }
    
    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 4px;
        flex: 1;
        min-width: 140px;
    }
    
    .filter-group label {
        font-size: 0.65rem;
        font-weight: 600;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    
    .filter-select,
    .filter-input {
        padding: 6px 10px;
        border: 2px solid var(--border-color);
        border-radius: 8px;
        background: var(--bg-body);
        color: var(--text-primary);
        font-size: 0.8rem;
        outline: none;
        transition: all 0.3s;
        width: 100%;
    }
    
    .filter-select:focus,
    .filter-input:focus {
        border-color: #0B5ED7;
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
    }
    
    .filter-actions {
        flex: 0 0 auto;
        flex-direction: row;
        align-items: center;
        gap: 6px;
        min-width: auto;
    }
    
    .filter-actions .btn {
        white-space: nowrap;
    }
    
    /* Table */
    .data-table thead th {
        background: #0B5ED7 !important;
        color: white !important;
        font-weight: 600;
        padding: 10px 12px;
        font-size: 0.6rem;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        border-bottom: none !important;
    }
    
    .data-table thead th:first-child {
        border-radius: 8px 0 0 0;
    }
    
    .data-table thead th:last-child {
        border-radius: 0 8px 0 0;
    }
    
    .data-table td {
        padding: 10px 12px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        font-size: 0.8rem;
        vertical-align: middle;
    }
    
    .data-table tbody tr:hover td {
        background: var(--table-hover);
    }
    
    .data-table .cancelled-row {
        opacity: 0.6;
    }
    
    .data-table .cancelled-row td {
        text-decoration: line-through;
    }
    
    /* Status Badges */
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
        text-transform: uppercase;
    }
    
    .status-badge.pending { background: #FEF3C7; color: #D97706; }
    .status-badge.partial { background: #EDE9FE; color: #7B2FBE; }
    .status-badge.paid { background: #D1FAE5; color: #059669; }
    .status-badge.cancelled { background: #FEE2E2; color: #DC2626; }
    
    [data-theme="dark"] .status-badge.pending { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .status-badge.partial { background: #2D1B4E; color: #A78BFA; }
    [data-theme="dark"] .status-badge.paid { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .status-badge.cancelled { background: #3A1A1A; color: #F87171; }
    
    /* Branch Badge */
    .branch-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.55rem;
        font-weight: 500;
        background: #F1F5F9;
        color: #64748B;
    }
    
    [data-theme="dark"] .branch-badge {
        background: #334155;
        color: #94A3B8;
    }
    
    /* Bill Number */
    .bill-number {
        font-weight: 600;
        font-size: 0.75rem;
        font-family: monospace;
        color: var(--text-primary);
    }
    
    .patient-name {
        font-weight: 500;
        font-size: 0.8rem;
        color: var(--text-primary);
    }
    
    /* Action Buttons */
    .action-buttons {
        display: flex;
        gap: 4px;
        align-items: center;
        flex-wrap: wrap;
    }
    
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        padding: 4px 10px;
        border-radius: 6px;
        font-weight: 600;
        font-size: 0.65rem;
        transition: all 0.3s;
        cursor: pointer;
        border: none;
        text-decoration: none;
    }
    
    .btn-sm { padding: 3px 8px; font-size: 0.6rem; }
    
    .btn-view { background: #0B5ED7; color: white; }
    .btn-view:hover { background: #0A4CA8; transform: translateY(-1px); }
    
    .btn-pay { background: #059669; color: white; }
    .btn-pay:hover { background: #047857; transform: translateY(-1px); }
    
    .btn-print { background: #64748B; color: white; }
    .btn-print:hover { background: #475569; transform: translateY(-1px); }
    
    .btn-blue { background: #0B5ED7; color: white; }
    .btn-blue:hover { background: #0A4CA8; transform: translateY(-2px); }
    
    .btn-green { background: #059669; color: white; }
    .btn-green:hover { background: #047857; transform: translateY(-2px); }
    
    .btn-outline {
        background: transparent;
        color: var(--text-secondary);
        border: 2px solid var(--border-color);
    }
    .btn-outline:hover {
        background: var(--bg-body);
        border-color: #0B5ED7;
        color: #0B5ED7;
        transform: translateY(-2px);
    }
    
    .btn-pdf {
        background: #DC2626;
        color: white;
    }
    .btn-pdf:hover {
        background: #B91C1C;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
    }
    
    .btn-excel {
        background: #059669;
        color: white;
    }
    .btn-excel:hover {
        background: #047857;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
    }
    
    /* Pagination */
    .pagination-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0 4px 0;
        border-top: 1px solid var(--border-color);
        margin-top: 12px;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .pagination-btn {
        padding: 6px 14px;
        border-radius: 8px;
        font-size: 0.75rem;
        font-weight: 500;
        background: var(--bg-body);
        color: var(--text-secondary);
        text-decoration: none;
        border: 1px solid var(--border-color);
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .pagination-btn:hover {
        background: #0B5ED7;
        color: white;
        border-color: #0B5ED7;
    }
    
    .pagination-pages {
        display: flex;
        gap: 4px;
    }
    
    .pagination-page {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        font-size: 0.75rem;
        font-weight: 500;
        color: var(--text-secondary);
        text-decoration: none;
        border: 1px solid transparent;
        transition: all 0.3s;
    }
    
    .pagination-page:hover {
        background: var(--bg-body);
        border-color: var(--border-color);
    }
    
    .pagination-page.active {
        background: #0B5ED7;
        color: white;
        border-color: #0B5ED7;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .stat-card {
            min-height: 70px;
            padding: 12px 14px;
        }
        .stat-card .stat-number {
            font-size: 1.3rem;
        }
        .stat-card .stat-icon {
            width: 36px;
            height: 36px;
            font-size: 1rem;
        }
        .filter-row {
            flex-direction: column;
            gap: 8px;
        }
        .filter-group {
            min-width: 100%;
        }
        .filter-actions {
            flex-direction: row;
            width: 100%;
        }
        .filter-actions .btn {
            flex: 1;
        }
        .pagination-container {
            flex-direction: column;
            gap: 8px;
        }
        .data-table {
            font-size: 0.7rem;
        }
        .data-table td,
        .data-table th {
            padding: 6px 8px;
        }
        .action-buttons {
            flex-direction: column;
            gap: 3px;
        }
        .action-buttons .btn {
            width: 100%;
            justify-content: center;
        }
        .grid-cols-4 {
            grid-template-columns: repeat(2, 1fr) !important;
        }
        .grid-cols-3 {
            grid-template-columns: repeat(2, 1fr) !important;
        }
        .period-filters {
            gap: 4px;
        }
        .period-btn {
            font-size: 0.6rem;
            padding: 3px 10px;
        }
    }
    
    @media (max-width: 480px) {
        .grid-cols-4 {
            grid-template-columns: 1fr !important;
        }
        .grid-cols-3 {
            grid-template-columns: 1fr !important;
        }
    }
</style>

<!-- ================================================================ -->
<!-- TOP NAVIGATION - SHARED HEADER -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <input type="text" id="searchInput" placeholder="Search bills..." value="<?= htmlspecialchars($search_filter) ?>">
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
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $logo_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3EA%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-5">
        <div>
            <h1 class="page-title">
                <i class="fas fa-file-invoice-dollar mr-2"></i> Bills Management
            </h1>
            <p class="page-subtitle">
                View and manage all bills
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name) ?>
                </span>
                <span class="period-label-badge ml-2">
                    <i class="fas fa-calendar-alt"></i> <?= $period_label ?>
                </span>
                <span class="ml-2 date-badge">
                    <i class="fas fa-calendar-day mr-1"></i> <?= date('F d, Y') ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="reports.php?type=bills&branch=<?= $selected_branch_id ?>" class="btn btn-blue btn-sm">
                <i class="fas fa-file-export"></i> Export
            </a>
            <button onclick="location.reload()" class="btn btn-outline btn-sm">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5">
        
        <div class="stat-card total">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Total Bills</p>
                    <p class="stat-number"><?= number_format($total_bills) ?></p>
                    <span class="stat-trend"><?= $period_label ?></span>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-file-invoice"></i>
                </div>
            </div>
        </div>
        
        <div class="stat-card pending">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Pending</p>
                    <p class="stat-number"><?= number_format($all_status_counts['pending'] ?? 0) ?></p>
                    <span class="stat-trend">Awaiting payment</span>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
        </div>
        
        <div class="stat-card partial">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Partial</p>
                    <p class="stat-number"><?= number_format($all_status_counts['partial'] ?? 0) ?></p>
                    <span class="stat-trend">Partially paid</span>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-hourglass-half"></i>
                </div>
            </div>
        </div>
        
        <div class="stat-card paid">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Paid</p>
                    <p class="stat-number"><?= number_format($all_status_counts['paid'] ?? 0) ?></p>
                    <span class="stat-trend">Completed</span>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- FINANCIAL SUMMARY -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-5">
        
        <div class="financial-card">
            <div class="financial-icon blue">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div>
                <p class="financial-label">Total Amount</p>
                <p class="financial-value">TSh <?= number_format($total_amount, 0) ?></p>
            </div>
        </div>
        
        <div class="financial-card">
            <div class="financial-icon green">
                <i class="fas fa-check-circle"></i>
            </div>
            <div>
                <p class="financial-label">Total Paid</p>
                <p class="financial-value">TSh <?= number_format($total_paid, 0) ?></p>
            </div>
        </div>
        
        <div class="financial-card">
            <div class="financial-icon orange">
                <i class="fas fa-clock"></i>
            </div>
            <div>
                <p class="financial-label">Total Balance</p>
                <p class="financial-value" style="color: <?= $total_balance > 0 ? '#EF4444' : '#059669' ?>;">
                    TSh <?= number_format($total_balance, 0) ?>
                </p>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- TIME PERIOD FILTERS -->
    <!-- ================================================================ -->
    <div class="period-filters">
        <a href="?period=all&branch=<?= $selected_branch_id ?><?= !empty($search_filter) ? '&search='.urlencode($search_filter) : '' ?><?= !empty($status_filter) ? '&status='.urlencode($status_filter) : '' ?><?= !empty($sort_by) ? '&sort='.urlencode($sort_by) : '' ?>" 
           class="period-btn <?= $time_period === 'all' ? 'active' : '' ?>">
            <i class="fas fa-globe"></i> All
        </a>
        <a href="?period=today&branch=<?= $selected_branch_id ?><?= !empty($search_filter) ? '&search='.urlencode($search_filter) : '' ?><?= !empty($status_filter) ? '&status='.urlencode($status_filter) : '' ?><?= !empty($sort_by) ? '&sort='.urlencode($sort_by) : '' ?>" 
           class="period-btn <?= $time_period === 'today' ? 'active' : '' ?>">
            <i class="fas fa-calendar-day"></i> Today
        </a>
        <a href="?period=week&branch=<?= $selected_branch_id ?><?= !empty($search_filter) ? '&search='.urlencode($search_filter) : '' ?><?= !empty($status_filter) ? '&status='.urlencode($status_filter) : '' ?><?= !empty($sort_by) ? '&sort='.urlencode($sort_by) : '' ?>" 
           class="period-btn <?= $time_period === 'week' ? 'active' : '' ?>">
            <i class="fas fa-calendar-week"></i> Week
        </a>
        <a href="?period=month&branch=<?= $selected_branch_id ?><?= !empty($search_filter) ? '&search='.urlencode($search_filter) : '' ?><?= !empty($status_filter) ? '&status='.urlencode($status_filter) : '' ?><?= !empty($sort_by) ? '&sort='.urlencode($sort_by) : '' ?>" 
           class="period-btn <?= $time_period === 'month' ? 'active' : '' ?>">
            <i class="fas fa-calendar-alt"></i> Month
        </a>
        <a href="?period=3months&branch=<?= $selected_branch_id ?><?= !empty($search_filter) ? '&search='.urlencode($search_filter) : '' ?><?= !empty($status_filter) ? '&status='.urlencode($status_filter) : '' ?><?= !empty($sort_by) ? '&sort='.urlencode($sort_by) : '' ?>" 
           class="period-btn <?= $time_period === '3months' ? 'active' : '' ?>">
            <i class="fas fa-calendar-alt"></i> 3 Months
        </a>
        <a href="?period=6months&branch=<?= $selected_branch_id ?><?= !empty($search_filter) ? '&search='.urlencode($search_filter) : '' ?><?= !empty($status_filter) ? '&status='.urlencode($status_filter) : '' ?><?= !empty($sort_by) ? '&sort='.urlencode($sort_by) : '' ?>" 
           class="period-btn <?= $time_period === '6months' ? 'active' : '' ?>">
            <i class="fas fa-calendar-alt"></i> 6 Months
        </a>
        <a href="?period=year&branch=<?= $selected_branch_id ?><?= !empty($search_filter) ? '&search='.urlencode($search_filter) : '' ?><?= !empty($status_filter) ? '&status='.urlencode($status_filter) : '' ?><?= !empty($sort_by) ? '&sort='.urlencode($sort_by) : '' ?>" 
           class="period-btn <?= $time_period === 'year' ? 'active' : '' ?>">
            <i class="fas fa-calendar-alt"></i> 1 Year
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="filter-section mb-5">
        <form method="GET" action="" class="filter-form" id="filterForm">
            <input type="hidden" name="branch" value="<?= $selected_branch_id ?>">
            <input type="hidden" name="period" value="<?= $time_period ?>">
            
            <div class="filter-row">
                <div class="filter-group">
                    <label>Status</label>
                    <select name="status" class="filter-select" onchange="document.getElementById('filterForm').submit()">
                        <option value="">All Status</option>
                        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="partial" <?= $status_filter === 'partial' ? 'selected' : '' ?>>Partial</option>
                        <option value="paid" <?= $status_filter === 'paid' ? 'selected' : '' ?>>Paid</option>
                        <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Sort By</label>
                    <select name="sort" class="filter-select" onchange="document.getElementById('filterForm').submit()">
                        <option value="created_at_desc" <?= $sort_by === 'created_at_desc' ? 'selected' : '' ?>>Newest First</option>
                        <option value="created_at_asc" <?= $sort_by === 'created_at_asc' ? 'selected' : '' ?>>Oldest First</option>
                        <option value="amount_desc" <?= $sort_by === 'amount_desc' ? 'selected' : '' ?>>Highest Amount</option>
                        <option value="amount_asc" <?= $sort_by === 'amount_asc' ? 'selected' : '' ?>>Lowest Amount</option>
                        <option value="bill_number_asc" <?= $sort_by === 'bill_number_asc' ? 'selected' : '' ?>>Bill # A-Z</option>
                        <option value="bill_number_desc" <?= $sort_by === 'bill_number_desc' ? 'selected' : '' ?>>Bill # Z-A</option>
                    </select>
                </div>
                
                <div class="filter-group filter-actions">
                    <a href="bills.php?branch=<?= $selected_branch_id ?>&period=<?= $time_period ?>" class="btn btn-outline btn-sm">
                        <i class="fas fa-undo"></i> Reset
                    </a>
                </div>
            </div>
            
            <!-- Search -->
            <div class="filter-row mt-3">
                <div class="filter-group" style="flex: 2;">
                    <label>Search</label>
                    <input type="text" name="search" class="filter-input" placeholder="Search by bill #, patient name or patient ID..." value="<?= htmlspecialchars($search_filter) ?>">
                </div>
                <div class="filter-group filter-actions" style="flex: 0; min-width: auto;">
                    <button type="submit" class="btn btn-blue btn-sm">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- EXPORT BUTTONS -->
    <!-- ================================================================ -->
    <div class="flex flex-wrap gap-2 mb-4">
        <button onclick="exportPDF()" class="btn btn-pdf btn-sm">
            <i class="fas fa-file-pdf"></i> Export PDF
        </button>
        <button onclick="exportExcel()" class="btn btn-excel btn-sm">
            <i class="fas fa-file-excel"></i> Export Excel
        </button>
    </div>

    <!-- ================================================================ -->
    <!-- BILLS TABLE -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-blue mr-2"></i> Bills List
                <span class="text-xs text-gray-400 font-normal">(<?= number_format($total_bills) ?> records)</span>
            </h3>
            <span class="text-xs text-gray-400">
                Page <?= $page ?> of <?= $total_pages ?>
            </span>
        </div>
        
        <div class="overflow-x-auto">
            <table class="data-table" id="billsTable">
                <thead>
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th>Bill Number</th>
                        <th>Patient</th>
                        <th>Amount</th>
                        <th>Paid</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th>Branch</th>
                        <th>Created</th>
                        <th style="width: 120px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($bills) > 0): ?>
                        <?php $i = $offset + 1; foreach ($bills as $bill): ?>
                            <tr class="<?= $bill['status'] === 'cancelled' ? 'cancelled-row' : '' ?>">
                                <td><?= $i++ ?></td>
                                <td>
                                    <span class="bill-number"><?= htmlspecialchars($bill['bill_number']) ?></span>
                                    <?php if ($bill['item_count'] > 0): ?>
                                        <br><span class="text-xs text-gray-400"><?= $bill['item_count'] ?> items</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="patient-name"><?= htmlspecialchars($bill['patient_name'] ?? 'N/A') ?></span>
                                    <br><span class="text-xs text-gray-400"><?= htmlspecialchars($bill['patient_id_number'] ?? '') ?></span>
                                </td>
                                <td class="text-right font-semibold">TSh <?= number_format($bill['total_amount'], 0) ?></td>
                                <td class="text-right text-green-600">TSh <?= number_format($bill['paid_amount'], 0) ?></td>
                                <td class="text-right <?= $bill['balance'] > 0 ? 'text-red-600 font-bold' : 'text-green-600' ?>">
                                    TSh <?= number_format($bill['balance'], 0) ?>
                                </td>
                                <td>
                                    <span class="status-badge <?= $bill['status'] ?>">
                                        <i class="fas <?= getStatusIcon($bill['status']) ?>"></i>
                                        <?= ucfirst($bill['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="branch-badge">
                                        <?= htmlspecialchars($bill['branch_name'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td class="text-xs">
                                    <?= date('M d, Y', strtotime($bill['created_at'])) ?>
                                    <br>
                                    <span class="text-gray-400"><?= date('h:i A', strtotime($bill['created_at'])) ?></span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="bill_details.php?id=<?= $bill['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                           class="btn btn-sm btn-view" title="View Bill">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($bill['status'] !== 'paid' && $bill['status'] !== 'cancelled'): ?>
                                            <a href="process_payment.php?bill_id=<?= $bill['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                               class="btn btn-sm btn-pay" title="Process Payment">
                                                <i class="fas fa-money-bill-wave"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="print_bill.php?id=<?= $bill['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                           target="_blank" class="btn btn-sm btn-print" title="Print Bill">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="text-center text-gray-400 text-sm py-5">
                                <i class="fas fa-inbox text-2xl block mb-2"></i>
                                No bills found
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination-container">
                <?php if ($page > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="pagination-btn">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                <?php endif; ?>
                
                <div class="pagination-pages">
                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>" 
                           class="pagination-page <?= $p === $page ? 'active' : '' ?>">
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>
                </div>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="pagination-btn">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Bills Management
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
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

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
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
    // SEARCH
    // ================================================================
    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            var branch = '<?= $selected_branch_id ?>';
            var period = '<?= $time_period ?>';
            var status = '<?= $status_filter ?>';
            var sort = '<?= $sort_by ?>';
            window.location.href = 'bills.php?search=' + encodeURIComponent(query) + '&branch=' + branch + '&period=' + period + '&status=' + status + '&sort=' + sort;
        }
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

    // ================================================================
    // BRANCH SWITCHER
    // ================================================================
    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        window.location.href = url.toString();
    }

    // ================================================================
    // EXPORT FUNCTIONS
    // ================================================================
    function exportPDF() {
        var branch = '<?= $selected_branch_id ?>';
        var period = '<?= $time_period ?>';
        var status = '<?= $status_filter ?>';
        var search = '<?= $search_filter ?>';
        var sort = '<?= $sort_by ?>';
        
        var url = 'export_bills.php?format=pdf&branch=' + encodeURIComponent(branch) + 
                  '&period=' + encodeURIComponent(period) + 
                  '&status=' + encodeURIComponent(status) + 
                  '&search=' + encodeURIComponent(search) +
                  '&sort=' + encodeURIComponent(sort);
        
        window.open(url, '_blank', 'width=1000,height=800');
        showToast('📄 Export PDF', 'PDF report is being generated...', 'info');
    }
    
    function exportExcel() {
        var branch = '<?= $selected_branch_id ?>';
        var period = '<?= $time_period ?>';
        var status = '<?= $status_filter ?>';
        var search = '<?= $search_filter ?>';
        var sort = '<?= $sort_by ?>';
        
        var url = 'export_bills.php?format=excel&branch=' + encodeURIComponent(branch) + 
                  '&period=' + encodeURIComponent(period) + 
                  '&status=' + encodeURIComponent(status) + 
                  '&search=' + encodeURIComponent(search) +
                  '&sort=' + encodeURIComponent(sort);
        
        window.location.href = url;
        showToast('📊 Export Excel', 'Excel file is being downloaded...', 'success');
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
                if (toast) toast.style.display = 'none'; 
            }, 400);
        }, 3000);
    }

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
        var dtEl = document.getElementById('currentDateTime');
        if (dtEl) dtEl.textContent = dateStr + ' • ' + timeStr;
        
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    console.log('%c📄 Braick Dispensary - Bills Management (FIXED)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Period: <?= $period_label ?>', 'font-size:13px; color:#7B2FBE;');
    console.log('%c📊 Total Bills: <?= number_format($total_bills) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c💰 Total Amount: TSh <?= number_format($total_amount, 0) ?>', 'font-size:13px; color:#7B2FBE;');
    console.log('%c📄 PDF Export: Opens in new window', 'font-size:13px; color:#DC2626;');
    console.log('%c📊 Excel Export: Downloads file', 'font-size:13px; color:#059669;');
</script>

</body>
</html>