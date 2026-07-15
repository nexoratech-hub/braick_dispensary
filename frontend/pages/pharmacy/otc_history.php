<?php
// ================================================================
// FILE: frontend/pages/pharmacy/otc_history.php
// PHARMACY - OTC SALES HISTORY (FIXED - NO 'status' COLUMN)
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// INCLUDE CONFIG
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

// ================================================================
// VARIABLES
// ================================================================
$message = '';
$message_type = '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// ================================================================
// SESSION
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pharmacy') {
    $_SESSION['user_id'] = 5;
    $_SESSION['full_name'] = 'Peter Ngalula';
    $_SESSION['role'] = 'pharmacy';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'pharm.peter';
    $_SESSION['is_admin'] = false;
}

$user_id = $_SESSION['user_id'] ?? 5;
$user_full_name = $_SESSION['full_name'] ?? 'Peter Ngalula';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$is_admin = $_SESSION['is_admin'] ?? false;

$db = getDB();

// ================================================================
// HANDLE POST ACTIONS (Cancel) - Tumia payment_status
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $sale_id = (int)($_POST['sale_id'] ?? 0);
    
    if ($action === 'cancel' && $sale_id > 0) {
        // Use payment_status instead of status
        $stmt = $db->prepare("UPDATE otc_sales SET payment_status = 'cancelled' WHERE id = ? AND branch_id = ?");
        if ($stmt->execute([$sale_id, $user_branch_id])) {
            $message = "OTC sale cancelled successfully!";
            $message_type = 'success';
            header('Location: otc_history.php?filter=' . urlencode($filter));
            exit;
        } else {
            $message = "Failed to cancel OTC sale!";
            $message_type = 'error';
        }
    }
}

// ================================================================
// BUILD QUERY - USE payment_status NOT status
// ================================================================
$query = "
    SELECT 
        os.*,
        u.full_name as cashier_name,
        (SELECT COUNT(*) FROM otc_sale_items WHERE sale_id = os.id) as item_count,
        (SELECT GROUP_CONCAT(medicine_name) FROM otc_sale_items WHERE sale_id = os.id) as medicine_names
    FROM otc_sales os
    LEFT JOIN users u ON os.sold_by = u.id
    WHERE os.branch_id = ?
";

$params = [$user_branch_id];

// Filter by payment_status (NOT status)
if ($filter === 'pending') {
    $query .= " AND os.payment_status = 'pending'";
} elseif ($filter === 'paid' || $filter === 'dispensed' || $filter === 'completed') {
    $query .= " AND os.payment_status = 'paid'";
} elseif ($filter === 'cancelled') {
    $query .= " AND os.payment_status = 'cancelled'";
} elseif ($filter === 'partial') {
    $query .= " AND os.payment_status = 'partial'";
}

// Date range filter
if (!empty($date_from) && !empty($date_to)) {
    $query .= " AND DATE(os.created_at) BETWEEN ? AND ?";
    $params[] = $date_from;
    $params[] = $date_to;
}

// Search filter - customer name or sale number
if (!empty($search)) {
    $query .= " AND (os.customer_name LIKE ? OR os.sale_number LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$query .= " ORDER BY os.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$sales = $stmt->fetchAll();

// ================================================================
// GET STATISTICS - USE payment_status
// ================================================================

// Total OTC Sales
$stmt = $db->prepare("SELECT COUNT(*) as count FROM otc_sales WHERE branch_id = ?");
$stmt->execute([$user_branch_id]);
$total_otc = $stmt->fetch()['count'] ?? 0;

// Today's OTC Sales
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM otc_sales 
    WHERE branch_id = ? AND DATE(created_at) = CURDATE()
");
$stmt->execute([$user_branch_id]);
$today_otc = $stmt->fetch()['count'] ?? 0;

// Pending OTC Sales (payment_status = 'pending')
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM otc_sales 
    WHERE branch_id = ? AND payment_status = 'pending'
");
$stmt->execute([$user_branch_id]);
$pending_count = $stmt->fetch()['count'] ?? 0;

// Paid OTC Sales
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM otc_sales 
    WHERE branch_id = ? AND payment_status = 'paid'
");
$stmt->execute([$user_branch_id]);
$paid_count = $stmt->fetch()['count'] ?? 0;

// Cancelled OTC Sales
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM otc_sales 
    WHERE branch_id = ? AND payment_status = 'cancelled'
");
$stmt->execute([$user_branch_id]);
$cancelled_count = $stmt->fetch()['count'] ?? 0;

// Partial OTC Sales
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM otc_sales 
    WHERE branch_id = ? AND payment_status = 'partial'
");
$stmt->execute([$user_branch_id]);
$partial_count = $stmt->fetch()['count'] ?? 0;

// ================================================================
// GET STATISTICS FOR SIDEBAR
// ================================================================
$pending_prescriptions = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescription_sales WHERE branch_id = ? AND status = 'pending'");
    $stmt->execute([$user_branch_id]);
    $pending_prescriptions = $stmt->fetch()['count'] ?? 0;
} catch (Exception $e) {
    $pending_prescriptions = 0;
}

$low_stock_count = 0;
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM medications_inventory 
        WHERE branch_id = ? AND quantity <= reorder_level AND status = 'active'
    ");
    $stmt->execute([$user_branch_id]);
    $low_stock_count = $stmt->fetch()['count'] ?? 0;
} catch (Exception $e) {
    $low_stock_count = 0;
}

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
include_once __DIR__ . '/../../components/pharmacy_header.php';
include_once __DIR__ . '/../../components/pharmacy_sidebar.php';
?>

<!-- ================================================================ -->
<!-- STYLES -->
<!-- ================================================================ -->
<style>
    :root {
        --primary: #0B5ED7;
        --primary-dark: #0A3D8A;
        --primary-light: #E8F0FE;
        --success: #059669;
        --success-light: #D1FAE5;
        --warning: #D97706;
        --warning-light: #FEF3C7;
        --danger: #DC2626;
        --danger-light: #FEE2E2;
        --purple: #7C3AED;
        --purple-light: #EDE9FE;
        --otc-color: #7C3AED;
        --otc-bg: #EDE9FE;
        
        --bg-body: #F1F5F9;
        --bg-card: #FFFFFF;
        --border-color: #E2E8F0;
        --text-primary: #0F172A;
        --text-secondary: #475569;
        --text-muted: #94A3B8;
        --table-stripe: #F8FAFC;
        --table-hover: #E8F0FE;
    }
    
    [data-theme="dark"] {
        --bg-body: #0F172A;
        --bg-card: #1E293B;
        --border-color: #334155;
        --text-primary: #F1F5F9;
        --text-secondary: #94A3B8;
        --text-muted: #64748B;
        --table-stripe: #1E293B;
        --table-hover: #1E3A5F;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }
    
    .stat-card {
        border-radius: 16px;
        padding: 18px 20px;
        border: none;
        transition: all 0.3s;
        color: white;
        text-decoration: none;
        display: flex;
        align-items: center;
        justify-content: space-between;
        cursor: pointer;
    }
    
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 30px rgba(0,0,0,0.15);
    }
    
    .stat-card.purple { background: #7C3AED; }
    .stat-card.green { background: #059669; }
    .stat-card.orange { background: #D97706; }
    .stat-card.red { background: #DC2626; }
    .stat-card.blue { background: #0B5ED7; }
    .stat-card.teal { background: #0D9488; }
    
    .stat-card .stat-icon {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        background: rgba(255,255,255,0.15);
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
        color: rgba(255,255,255,0.8);
        font-weight: 500;
    }
    
    .stat-card .stat-trend {
        font-size: 0.65rem;
        font-weight: 600;
        padding: 2px 10px;
        border-radius: 20px;
        background: rgba(255,255,255,0.15);
        color: white;
        display: inline-block;
    }
    
    .card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 20px 24px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
    }
    
    .card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.06);
    }
    
    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .card-title {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
    }
    
    .filter-group {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 16px;
    }
    
    .filter-btn {
        padding: 6px 16px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        border: 2px solid var(--border-color);
        background: transparent;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
    }
    
    .filter-btn:hover {
        border-color: var(--primary);
        color: var(--primary);
    }
    
    .filter-btn.active {
        background: var(--primary);
        border-color: var(--primary);
        color: white;
    }
    
    .filter-btn.active:hover {
        background: var(--primary-dark);
        border-color: var(--primary-dark);
    }
    
    .search-form {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        align-items: center;
    }
    
    .search-form input[type="text"],
    .search-form input[type="date"] {
        padding: 8px 14px;
        border: 2px solid var(--border-color);
        border-radius: 10px;
        font-size: 0.85rem;
        background: var(--bg-card);
        color: var(--text-primary);
        outline: none;
        transition: all 0.3s ease;
    }
    
    .search-form input:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
    }
    
    .search-form .btn-search {
        padding: 8px 20px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        border: none;
        background: var(--primary);
        color: white;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .search-form .btn-search:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }
    
    .search-form .btn-reset {
        padding: 8px 16px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        border: 2px solid var(--border-color);
        background: transparent;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
    }
    
    .search-form .btn-reset:hover {
        border-color: var(--danger);
        color: var(--danger);
    }
    
    .type-badge {
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 0.6rem;
        font-weight: 600;
        text-transform: uppercase;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: var(--otc-bg);
        color: var(--otc-color);
    }
    
    [data-theme="dark"] .type-badge {
        background: #2A1A3A;
        color: #9B4DCA;
    }
    
    .status-badge {
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 0.65rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .status-badge.paid {
        background: var(--success-light);
        color: var(--success);
    }
    
    .status-badge.pending {
        background: var(--warning-light);
        color: var(--warning);
    }
    
    .status-badge.cancelled {
        background: var(--danger-light);
        color: var(--danger);
    }
    
    .status-badge.partial {
        background: var(--primary-light);
        color: var(--primary);
    }
    
    [data-theme="dark"] .status-badge.paid {
        background: #1A3A2A;
        color: #34D399;
    }
    
    [data-theme="dark"] .status-badge.pending {
        background: #3D2E0A;
        color: #FBBF24;
    }
    
    [data-theme="dark"] .status-badge.cancelled {
        background: #3A1A1A;
        color: #F87171;
    }
    
    [data-theme="dark"] .status-badge.partial {
        background: #1E3A5F;
        color: #6EA8FE;
    }
    
    .table-wrap {
        overflow-x: auto;
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }
    
    .data-table thead th {
        text-align: left;
        padding: 10px 14px;
        font-weight: 700;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: white;
        background: var(--primary);
        border-bottom: 3px solid var(--primary-dark);
        white-space: nowrap;
    }
    
    .data-table thead th:first-child {
        border-radius: 8px 0 0 0;
    }
    
    .data-table thead th:last-child {
        border-radius: 0 8px 0 0;
    }
    
    .data-table tbody tr:nth-child(even) {
        background: var(--table-stripe);
    }
    
    .data-table tbody tr:hover td {
        background: var(--table-hover);
    }
    
    .data-table td {
        padding: 10px 14px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        vertical-align: middle;
    }
    
    .sale-number {
        font-family: monospace;
        font-weight: 600;
        font-size: 0.8rem;
    }
    
    .medicine-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
    }
    
    .medicine-tag {
        background: var(--bg-body);
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 0.7rem;
        color: var(--text-secondary);
        border: 1px solid var(--border-color);
    }
    
    .result-count {
        font-size: 0.8rem;
        color: var(--text-secondary);
    }
    
    .result-count strong {
        color: var(--primary);
    }
    
    .empty-state {
        text-align: center;
        padding: 50px 20px;
        color: var(--text-secondary);
    }
    
    .empty-state i {
        font-size: 3rem;
        color: var(--border-color);
        display: block;
        margin-bottom: 12px;
    }
    
    .empty-state p {
        font-size: 0.95rem;
    }
    
    .action-btn {
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.7rem;
        font-weight: 600;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .action-btn.view {
        background: var(--primary);
        color: white;
    }
    
    .action-btn.view:hover {
        background: var(--primary-dark);
        transform: scale(1.05);
    }
    
    .action-btn.cancel {
        background: var(--danger);
        color: white;
    }
    
    .action-btn.cancel:hover {
        background: #B91C1C;
        transform: scale(1.05);
    }
    
    .action-btn.print {
        background: var(--purple);
        color: white;
    }
    
    .action-btn.print:hover {
        background: #6D28D9;
        transform: scale(1.05);
    }
    
    .animate-fade-in-up {
        animation: fadeInUp 0.5s ease forwards;
        opacity: 0;
    }
    
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .message-box {
        padding: 12px 18px;
        border-radius: 12px;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 500;
    }
    
    .message-box.success {
        background: var(--success-light);
        color: #065F46;
        border: 1px solid #6EE7B7;
    }
    
    .message-box.error {
        background: var(--danger-light);
        color: #991B1B;
        border: 1px solid #FCA5A5;
    }
    
    .message-box i {
        font-size: 1.1rem;
    }
    
    [data-theme="dark"] .message-box.success {
        background: #1A3A2A;
        color: #34D399;
        border-color: #34D399;
    }
    
    [data-theme="dark"] .message-box.error {
        background: #3A1A1A;
        color: #F87171;
        border-color: #F87171;
    }
    
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .search-form {
            flex-direction: column;
            align-items: stretch;
        }
        .filter-group {
            justify-content: center;
        }
        .data-table {
            font-size: 0.75rem;
        }
        .data-table th,
        .data-table td {
            padding: 6px 8px;
        }
        .card {
            padding: 12px 14px;
        }
        .medicine-tags {
            flex-direction: column;
        }
    }
    
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
        .stat-card .stat-number {
            font-size: 1.4rem;
        }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-5">
        <div>
            <h1 class="page-title">
                <i class="fas fa-shopping-cart mr-2" style="color: var(--otc-color);"></i> OTC Sales History
            </h1>
            <p class="page-subtitle">
                View all over-the-counter sales (walk-in customers)
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
                <?php if ($filter !== 'all'): ?>
                    <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                        <i class="fas fa-filter mr-1"></i> <?= ucfirst($filter) ?>
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="new_otc_sale.php" class="btn btn-success btn-sm">
                <i class="fas fa-plus-circle"></i> New OTC Sale
            </a>
            <a href="dashboard.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS - NO FINANCIAL DATA -->
    <!-- ================================================================ -->
    <div class="stats-grid animate-fade-in-up">
        <a href="otc_history.php?filter=all" class="stat-card purple">
            <div>
                <p class="stat-label">Total OTC Sales</p>
                <p class="stat-number"><?= number_format($total_otc) ?></p>
                <span class="stat-trend"><i class="fas fa-shopping-cart"></i> All time</span>
            </div>
            <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
        </a>
        
        <a href="otc_history.php?filter=paid" class="stat-card green">
            <div>
                <p class="stat-label">Paid</p>
                <p class="stat-number"><?= number_format($paid_count) ?></p>
                <span class="stat-trend"><i class="fas fa-check-circle"></i> Completed</span>
            </div>
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        </a>
        
        <a href="otc_history.php?filter=pending" class="stat-card orange">
            <div>
                <p class="stat-label">Pending</p>
                <p class="stat-number"><?= number_format($pending_count) ?></p>
                <span class="stat-trend"><i class="fas fa-clock"></i> Awaiting</span>
            </div>
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
        </a>
        
        <a href="otc_history.php?filter=cancelled" class="stat-card red">
            <div>
                <p class="stat-label">Cancelled</p>
                <p class="stat-number"><?= number_format($cancelled_count) ?></p>
                <span class="stat-trend"><i class="fas fa-times-circle"></i> Cancelled</span>
            </div>
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS & SEARCH -->
    <!-- ================================================================ -->
    <div class="card mb-5 animate-fade-in-up">
        <div class="filter-group">
            <a href="otc_history.php?filter=all" class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">
                <i class="fas fa-list"></i> All
            </a>
            <a href="otc_history.php?filter=paid" class="filter-btn <?= $filter === 'paid' ? 'active' : '' ?>">
                <i class="fas fa-check-circle"></i> Paid
            </a>
            <a href="otc_history.php?filter=pending" class="filter-btn <?= $filter === 'pending' ? 'active' : '' ?>">
                <i class="fas fa-clock"></i> Pending
            </a>
            <a href="otc_history.php?filter=partial" class="filter-btn <?= $filter === 'partial' ? 'active' : '' ?>">
                <i class="fas fa-money-bill-wave"></i> Partial
            </a>
            <a href="otc_history.php?filter=cancelled" class="filter-btn <?= $filter === 'cancelled' ? 'active' : '' ?>">
                <i class="fas fa-times-circle"></i> Cancelled
            </a>
            <a href="otc_history.php?filter=today" class="filter-btn <?= $filter === 'today' ? 'active' : '' ?>">
                <i class="fas fa-calendar-day"></i> Today
            </a>
        </div>
        
        <form method="GET" class="search-form">
            <input type="hidden" name="filter" value="<?= $filter ?>">
            
            <input type="text" name="search" placeholder="Search customer, sale #..." 
                   value="<?= htmlspecialchars($search) ?>">
            
            <input type="date" name="date_from" value="<?= $date_from ?>">
            <span class="text-xs text-gray-400">to</span>
            <input type="date" name="date_to" value="<?= $date_to ?>">
            
            <button type="submit" class="btn-search">
                <i class="fas fa-search mr-1"></i> Search
            </button>
            
            <a href="otc_history.php?filter=<?= $filter ?>" class="btn-reset">
                <i class="fas fa-times"></i> Reset
            </a>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- MESSAGE BOX -->
    <!-- ================================================================ -->
    <?php if (!empty($message)): ?>
        <div class="message-box <?= $message_type === 'success' ? 'success' : 'error' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- OTC SALES TABLE -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-blue mr-2"></i>
                OTC Sales
                <span class="result-count ml-2">(<strong><?= number_format(count($sales)) ?></strong> record(s))</span>
            </h3>
        </div>
        
        <?php if (count($sales) > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="border-radius: 8px 0 0 0;">Sale #</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Medicines</th>
                            <th>Payment Status</th>
                            <th>Cashier</th>
                            <th>Date</th>
                            <th style="border-radius: 0 8px 0 0;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sales as $sale): ?>
                            <tr>
                                <td class="sale-number">
                                    <?= htmlspecialchars($sale['sale_number'] ?? 'N/A') ?>
                                    <span class="type-badge">OTC</span>
                                </td>
                                <td>
                                    <div class="font-medium"><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer') ?></div>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($sale['customer_phone'] ?? 'No phone') ?></div>
                                </td>
                                <td class="text-center">
                                    <span class="font-semibold"><?= $sale['item_count'] ?? 0 ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($sale['medicine_names'])): ?>
                                        <div class="medicine-tags">
                                            <?php 
                                                $medicines = explode(',', $sale['medicine_names']);
                                                $display = array_slice($medicines, 0, 2);
                                                $remaining = count($medicines) - 2;
                                            ?>
                                            <?php foreach ($display as $med): ?>
                                                <span class="medicine-tag"><?= htmlspecialchars(trim($med)) ?></span>
                                            <?php endforeach; ?>
                                            <?php if ($remaining > 0): ?>
                                                <span class="medicine-tag">+<?= $remaining ?> more</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">No items</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?= $sale['payment_status'] ?? 'pending' ?>">
                                        <?= ucfirst($sale['payment_status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($sale['cashier_name'] ?? 'Unknown') ?></td>
                                <td class="text-sm">
                                    <?= date('M d, Y', strtotime($sale['created_at'] ?? 'now')) ?>
                                    <div class="text-xs text-gray-400"><?= date('h:i A', strtotime($sale['created_at'] ?? 'now')) ?></div>
                                </td>
                                <td>
                                    <div class="flex flex-wrap gap-1">
                                        <a href="view_otc_sale.php?id=<?= $sale['id'] ?? 0 ?>" 
                                           class="action-btn view" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php if (($sale['payment_status'] ?? '') === 'paid'): ?>
                                            <button onclick="printReceipt('otc', <?= $sale['id'] ?? 0 ?>)" 
                                                    class="action-btn print" title="Print Receipt">
                                                <i class="fas fa-print"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if (($sale['payment_status'] ?? '') === 'pending'): ?>
                                            <form method="POST" style="display:inline;" 
                                                  onsubmit="return confirm('Are you sure you want to cancel this OTC sale?')">
                                                <input type="hidden" name="action" value="cancel">
                                                <input type="hidden" name="sale_id" value="<?= $sale['id'] ?? 0 ?>">
                                                <button type="submit" class="action-btn cancel" title="Cancel">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
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
                <p>No OTC sales found</p>
                <?php if (!empty($search)): ?>
                    <p class="text-sm text-gray-400 mt-1">No results found for "<strong><?= htmlspecialchars($search) ?></strong>"</p>
                <?php else: ?>
                    <p class="text-sm text-gray-400 mt-1">Start by making an OTC sale</p>
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
            OTC Sales History
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
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
    }
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (sidebar && sidebarToggle) {
                if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                    sidebar.classList.remove('open');
                }
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
        if (query.length > 0) {
            window.location.href = 'otc_history.php?filter=<?= $filter ?>&search=' + encodeURIComponent(query);
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
        var el = document.getElementById('currentDateTime');
        if (el) {
            el.textContent = dateStr + ' • ' + timeStr;
        }
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

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
    // PRINT RECEIPT
    // ================================================================
    function printReceipt(type, id) {
        if (id) {
            window.open('print_receipt.php?type=' + type + '&id=' + id, '_blank', 'width=400,height=600');
        } else {
            showToast('Error', 'Invalid sale ID', 'error');
        }
    }

    // ================================================================
    // CONSOLE
    // ================================================================
    console.log('%c💊 Braick - OTC Sales History (FIXED)', 'font-size:18px; font-weight:bold; color:#7C3AED;');
    console.log('%c✅ Uses payment_status instead of status', 'font-size:13px; color:#059669;');
    console.log('%c🚫 No financial data shown', 'font-size:13px; color:#DC2626;');
    console.log('%c📊 Total: <?= $total_otc ?> | Paid: <?= $paid_count ?> | Pending: <?= $pending_count ?>', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>