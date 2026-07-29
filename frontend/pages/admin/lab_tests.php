<?php
// ================================================================
// FILE: frontend/pages/admin/lab_tests.php
// SUPER ADMIN - LAB TESTS MANAGEMENT
// VIEW ALL LAB TESTS WITH FILTERS
// BLUE THEME - FIXED: Removed Add Lab Test button
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION - Admin Only
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
$selected_branch_id = $_GET['branch'] ?? 'all';
$branch_name = 'All Branches';

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
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
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
        $date_condition = "DATE(lt.created_at) = CURDATE()";
        break;
    case 'week':
        $date_condition = "lt.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case 'month':
        $date_condition = "lt.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        break;
    case '3months':
        $date_condition = "lt.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
        break;
    case '6months':
        $date_condition = "lt.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
        break;
    case 'year':
        $date_condition = "lt.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
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
    $conditions[] = "lt.branch_id = ?";
    $params[] = (int)$selected_branch_id;
}

// Status filter - FIXED: Use lt.status
if (!empty($status_filter)) {
    $conditions[] = "lt.status = ?";
    $params[] = $status_filter;
}

// Search filter
if (!empty($search_filter)) {
    $conditions[] = "(lt.test_name LIKE ? OR p.full_name LIKE ? OR p.patient_id LIKE ? OR lt.test_type LIKE ?)";
    $params[] = "%$search_filter%";
    $params[] = "%$search_filter%";
    $params[] = "%$search_filter%";
    $params[] = "%$search_filter%";
}

// Date range filter (custom)
if (!empty($date_from) && $time_period === 'custom') {
    $conditions[] = "DATE(lt.created_at) >= ?";
    $params[] = $date_from;
}
if (!empty($date_to) && $time_period === 'custom') {
    $conditions[] = "DATE(lt.created_at) <= ?";
    $params[] = $date_to;
}

$where_clause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// ================================================================
// GET TOTAL LAB TESTS COUNT
// ================================================================
$count_sql = "
    SELECT COUNT(*) as total 
    FROM lab_tests lt
    LEFT JOIN visits v ON lt.visit_id = v.id
    LEFT JOIN patients p ON v.patient_id = p.id
    $where_clause
";
$count_stmt = $db->prepare($count_sql);
$count_stmt->execute($params);
$total_tests = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// ================================================================
// GET LAB TESTS WITH PAGINATION
// ================================================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;
$total_pages = $total_tests > 0 ? ceil($total_tests / $per_page) : 1;

// Sort
$sort_map = [
    'created_at_desc' => 'lt.created_at DESC',
    'created_at_asc' => 'lt.created_at ASC',
    'name_asc' => 'lt.test_name ASC',
    'name_desc' => 'lt.test_name DESC',
    'price_desc' => 'lt.test_price DESC',
    'price_asc' => 'lt.test_price ASC',
    'status_asc' => 'lt.status ASC',
    'status_desc' => 'lt.status DESC'
];
$order_by = $sort_map[$sort_by] ?? 'lt.created_at DESC';

$query_params = $params;

$sql = "
    SELECT 
        lt.*,
        p.id as patient_id,
        p.full_name as patient_name,
        p.patient_id as patient_id_number,
        p.phone as patient_phone,
        d.full_name as doctor_name,
        l.full_name as lab_technician_name,
        v.visit_number,
        v.visit_date,
        b.name as branch_name
    FROM lab_tests lt
    LEFT JOIN visits v ON lt.visit_id = v.id
    LEFT JOIN patients p ON v.patient_id = p.id
    LEFT JOIN users d ON lt.doctor_id = d.id
    LEFT JOIN users l ON lt.lab_technician_id = l.id
    LEFT JOIN branches b ON lt.branch_id = b.id
    $where_clause
    ORDER BY $order_by
    LIMIT ? OFFSET ?
";

$query_params[] = $per_page;
$query_params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($query_params);
$lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// CALCULATE TOTALS
// ================================================================
$total_amount = 0;
$status_counts = ['pending' => 0, 'in_progress' => 0, 'completed' => 0, 'cancelled' => 0];

foreach ($lab_tests as $test) {
    $total_amount += $test['test_price'] ?? 0;
    if (isset($status_counts[$test['status']])) {
        $status_counts[$test['status']]++;
    }
}

// ================================================================
// GET STATUS COUNTS (ALL TESTS) - FIXED: Use lt.status
// ================================================================
$status_count_sql = "
    SELECT lt.status, COUNT(*) as count 
    FROM lab_tests lt
    LEFT JOIN visits v ON lt.visit_id = v.id
    LEFT JOIN patients p ON v.patient_id = p.id
    $where_clause
    GROUP BY lt.status
";
$status_count_stmt = $db->prepare($status_count_sql);
$status_count_stmt->execute($params);
$status_count_data = $status_count_stmt->fetchAll(PDO::FETCH_ASSOC);

$all_status_counts = [
    'pending' => 0,
    'in_progress' => 0,
    'completed' => 0,
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
        'completed' => 'success',
        'pending' => 'warning',
        'in_progress' => 'info',
        'cancelled' => 'danger'
    ];
    return $classes[$status] ?? 'secondary';
}

function getStatusIcon($status) {
    $icons = [
        'completed' => 'fa-check-circle',
        'pending' => 'fa-clock',
        'in_progress' => 'fa-spinner fa-spin',
        'cancelled' => 'fa-times-circle'
    ];
    return $icons[$status] ?? 'fa-circle';
}

function getStatusLabel($status) {
    $labels = [
        'pending' => 'Pending',
        'in_progress' => 'In Progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled'
    ];
    return $labels[$status] ?? ucfirst($status);
}

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<style>
    /* ================================================================
       BLUE THEME STYLES
       ================================================================ */
    
    /* Stat Cards */
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
    .stat-card.in_progress { background: #0B5ED7; }
    .stat-card.completed { background: #059669; }
    .stat-card.cancelled { background: #EF4444; }
    
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
    
    /* Financial Card */
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
    
    [data-theme="dark"] .financial-icon.blue { background: #1E3A5F; color: #6EA8FE; }
    
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
    
    /* Time Period Filters */
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
        font-weight: 700 !important;
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
    .status-badge.in_progress { background: #E8F0FE; color: #0B5ED7; }
    .status-badge.completed { background: #D1FAE5; color: #059669; }
    .status-badge.cancelled { background: #FEE2E2; color: #DC2626; }
    
    [data-theme="dark"] .status-badge.pending { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .status-badge.in_progress { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .status-badge.completed { background: #1A3A2A; color: #34D399; }
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
    
    .btn-edit { background: #F59E0B; color: white; }
    .btn-edit:hover { background: #D97706; transform: translateY(-1px); }
    
    .btn-delete { background: #EF4444; color: white; }
    .btn-delete:hover { background: #DC2626; transform: translateY(-1px); }
    
    .btn-blue { background: #0B5ED7; color: white; }
    .btn-blue:hover { background: #0A4CA8; transform: translateY(-2px); }
    
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
    
    /* Test Name */
    .test-name {
        font-weight: 600;
        font-size: 0.8rem;
        color: var(--text-primary);
    }
    
    .patient-name {
        font-weight: 500;
        font-size: 0.8rem;
        color: var(--text-primary);
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
            <input type="text" id="searchInput" placeholder="Search lab tests..." value="<?= htmlspecialchars($search_filter) ?>">
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
                <i class="fas fa-flask mr-2"></i> Lab Tests Management
            </h1>
            <p class="page-subtitle">
                View and manage all laboratory tests
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
                    <p class="stat-label">Total Tests</p>
                    <p class="stat-number"><?= number_format($total_tests) ?></p>
                    <span class="stat-trend"><?= $period_label ?></span>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-flask"></i>
                </div>
            </div>
        </div>
        
        <div class="stat-card pending">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Pending</p>
                    <p class="stat-number"><?= number_format($all_status_counts['pending'] ?? 0) ?></p>
                    <span class="stat-trend">Awaiting processing</span>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
        </div>
        
        <div class="stat-card in_progress">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">In Progress</p>
                    <p class="stat-number"><?= number_format($all_status_counts['in_progress'] ?? 0) ?></p>
                    <span class="stat-trend">Being processed</span>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-spinner"></i>
                </div>
            </div>
        </div>
        
        <div class="stat-card completed">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Completed</p>
                    <p class="stat-number"><?= number_format($all_status_counts['completed'] ?? 0) ?></p>
                    <span class="stat-trend">Done</span>
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
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
        
        <div class="financial-card">
            <div class="financial-icon blue">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div>
                <p class="financial-label">Total Revenue</p>
                <p class="financial-value">TSh <?= number_format($total_amount, 0) ?></p>
            </div>
        </div>
        
        <div class="financial-card">
            <div class="financial-icon blue">
                <i class="fas fa-flask"></i>
            </div>
            <div>
                <p class="financial-label">Total Tests</p>
                <p class="financial-value"><?= number_format($total_tests) ?></p>
            </div>
        </div>
        
        <div class="financial-card">
            <div class="financial-icon blue">
                <i class="fas fa-check-circle"></i>
            </div>
            <div>
                <p class="financial-label">Completion Rate</p>
                <p class="financial-value">
                    <?php 
                        $completed = $all_status_counts['completed'] ?? 0;
                        $rate = $total_tests > 0 ? round(($completed / $total_tests) * 100, 0) : 0;
                        echo $rate . '%';
                    ?>
                </p>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- TIME PERIOD FILTERS -->
    <!-- ================================================================ -->
    <div class="period-filters">
        <a href="?period=all&branch=<?= $selected_branch_id ?><?= !empty($search_filter) ? '&search='.urlencode($search_filter) : '' ?><?= !empty($status_filter) ? '&status='.urlencode($status_filter) : '' ?>" 
           class="period-btn <?= $time_period === 'all' ? 'active' : '' ?>">
            <i class="fas fa-globe"></i> All
        </a>
        <a href="?period=today&branch=<?= $selected_branch_id ?><?= !empty($search_filter) ? '&search='.urlencode($search_filter) : '' ?><?= !empty($status_filter) ? '&status='.urlencode($status_filter) : '' ?>" 
           class="period-btn <?= $time_period === 'today' ? 'active' : '' ?>">
            <i class="fas fa-calendar-day"></i> Today
        </a>
        <a href="?period=week&branch=<?= $selected_branch_id ?><?= !empty($search_filter) ? '&search='.urlencode($search_filter) : '' ?><?= !empty($status_filter) ? '&status='.urlencode($status_filter) : '' ?>" 
           class="period-btn <?= $time_period === 'week' ? 'active' : '' ?>">
            <i class="fas fa-calendar-week"></i> Week
        </a>
        <a href="?period=month&branch=<?= $selected_branch_id ?><?= !empty($search_filter) ? '&search='.urlencode($search_filter) : '' ?><?= !empty($status_filter) ? '&status='.urlencode($status_filter) : '' ?>" 
           class="period-btn <?= $time_period === 'month' ? 'active' : '' ?>">
            <i class="fas fa-calendar-alt"></i> Month
        </a>
        <a href="?period=3months&branch=<?= $selected_branch_id ?><?= !empty($search_filter) ? '&search='.urlencode($search_filter) : '' ?><?= !empty($status_filter) ? '&status='.urlencode($status_filter) : '' ?>" 
           class="period-btn <?= $time_period === '3months' ? 'active' : '' ?>">
            <i class="fas fa-calendar-alt"></i> 3 Months
        </a>
        <a href="?period=6months&branch=<?= $selected_branch_id ?><?= !empty($search_filter) ? '&search='.urlencode($search_filter) : '' ?><?= !empty($status_filter) ? '&status='.urlencode($status_filter) : '' ?>" 
           class="period-btn <?= $time_period === '6months' ? 'active' : '' ?>">
            <i class="fas fa-calendar-alt"></i> 6 Months
        </a>
        <a href="?period=year&branch=<?= $selected_branch_id ?><?= !empty($search_filter) ? '&search='.urlencode($search_filter) : '' ?><?= !empty($status_filter) ? '&status='.urlencode($status_filter) : '' ?>" 
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
                        <option value="in_progress" <?= $status_filter === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                        <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Sort By</label>
                    <select name="sort" class="filter-select" onchange="document.getElementById('filterForm').submit()">
                        <option value="created_at_desc" <?= $sort_by === 'created_at_desc' ? 'selected' : '' ?>>Newest First</option>
                        <option value="created_at_asc" <?= $sort_by === 'created_at_asc' ? 'selected' : '' ?>>Oldest First</option>
                        <option value="name_asc" <?= $sort_by === 'name_asc' ? 'selected' : '' ?>>Name A-Z</option>
                        <option value="name_desc" <?= $sort_by === 'name_desc' ? 'selected' : '' ?>>Name Z-A</option>
                        <option value="price_desc" <?= $sort_by === 'price_desc' ? 'selected' : '' ?>>Highest Price</option>
                        <option value="price_asc" <?= $sort_by === 'price_asc' ? 'selected' : '' ?>>Lowest Price</option>
                    </select>
                </div>
                
                <div class="filter-group filter-actions">
                    <a href="lab_tests.php?branch=<?= $selected_branch_id ?>&period=<?= $time_period ?>" class="btn btn-outline btn-sm">
                        <i class="fas fa-undo"></i> Reset
                    </a>
                </div>
            </div>
            
            <!-- Search -->
            <div class="filter-row mt-3">
                <div class="filter-group" style="flex: 2;">
                    <label>Search</label>
                    <input type="text" name="search" class="filter-input" placeholder="Search by test name, patient name or patient ID..." value="<?= htmlspecialchars($search_filter) ?>">
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
    <!-- LAB TESTS TABLE -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-blue mr-2"></i> Lab Tests List
                <span class="text-xs text-gray-400 font-normal">(<?= number_format($total_tests) ?> records)</span>
            </h3>
            <span class="text-xs text-gray-400">
                Page <?= $page ?> of <?= $total_pages ?>
            </span>
        </div>
        
        <div class="overflow-x-auto">
            <table class="data-table" id="labTestsTable">
                <thead>
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th>Test Name</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Branch</th>
                        <th>Created</th>
                        <th style="width: 120px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($lab_tests) > 0): ?>
                        <?php $i = $offset + 1; foreach ($lab_tests as $test): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td>
                                    <span class="test-name"><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></span>
                                    <?php if (!empty($test['test_type'])): ?>
                                        <br><span class="text-xs text-gray-400"><?= htmlspecialchars($test['test_type']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="patient-name"><?= htmlspecialchars($test['patient_name'] ?? 'N/A') ?></span>
                                    <br><span class="text-xs text-gray-400"><?= htmlspecialchars($test['patient_id_number'] ?? '') ?></span>
                                </td>
                                <td><?= htmlspecialchars($test['doctor_name'] ?? 'N/A') ?></td>
                                <td class="text-right font-semibold">TSh <?= number_format($test['test_price'] ?? 0, 0) ?></td>
                                <td>
                                    <span class="status-badge <?= $test['status'] ?>">
                                        <i class="fas <?= getStatusIcon($test['status']) ?>"></i>
                                        <?= getStatusLabel($test['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="branch-badge">
                                        <?= htmlspecialchars($test['branch_name'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td class="text-xs">
                                    <?= date('M d, Y', strtotime($test['created_at'])) ?>
                                    <br>
                                    <span class="text-gray-400"><?= date('h:i A', strtotime($test['created_at'])) ?></span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="lab_test_details.php?id=<?= $test['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                           class="btn btn-sm btn-view" title="View Test">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($test['status'] !== 'completed' && $test['status'] !== 'cancelled'): ?>
                                            <a href="edit_lab_test.php?id=<?= $test['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                               class="btn btn-sm btn-edit" title="Edit Test">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($test['status'] === 'completed'): ?>
                                            <a href="print_lab_result.php?id=<?= $test['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                               target="_blank" class="btn btn-sm btn-view" title="Print Results" style="background:#64748B;">
                                                <i class="fas fa-print"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center text-gray-400 text-sm py-5">
                                <i class="fas fa-flask text-2xl block mb-2"></i>
                                No lab tests found
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
            Lab Tests Management
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
            window.location.href = 'lab_tests.php?search=' + encodeURIComponent(query) + '&branch=' + branch + '&period=' + period + '&status=' + status + '&sort=' + sort;
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

    console.log('%c🔬 Braick Dispensary - Lab Tests Management', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Period: <?= $period_label ?>', 'font-size:13px; color:#7B2FBE;');
    console.log('%c📊 Total Tests: <?= number_format($total_tests) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c💰 Total Revenue: TSh <?= number_format($total_amount, 0) ?>', 'font-size:13px; color:#7B2FBE;');
    console.log('%c❌ Removed: Add Lab Test button', 'font-size:13px; color:#EF4444;');
</script>

</body>
</html>