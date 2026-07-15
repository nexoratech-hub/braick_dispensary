<?php
// ================================================================
// FILE: frontend/pages/pharmacy/low_stock.php
// PHARMACY - LOW STOCK & OUT OF STOCK REPORT
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// INCLUDE CONFIG
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

// ================================================================
// SESSION - Default to pharm.peter
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pharmacy') {
    $_SESSION['user_id'] = 5;
    $_SESSION['full_name'] = 'Peter Ngalula';
    $_SESSION['role'] = 'pharmacy';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'pharm.peter';
    $_SESSION['email'] = 'peter@braick.com';
    $_SESSION['phone'] = '+255 700 000 004';
    $_SESSION['is_admin'] = false;
    $_SESSION['profile_pic'] = '';
}

$user_id = $_SESSION['user_id'] ?? 5;
$user_full_name = $_SESSION['full_name'] ?? 'Peter Ngalula';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';

$db = getDB();

// ================================================================
// GET FILTERS
// ================================================================
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all'; // all, low, out
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// ================================================================
// BUILD QUERY
// ================================================================
$query = "
    SELECT *, 
        DATEDIFF(expiry_date, CURDATE()) as days_remaining
    FROM medications_inventory 
    WHERE branch_id = ?
";

$params = [$user_branch_id];

// Only show active medicines
$query .= " AND status = 'active'";

// Filter by stock status
if ($filter === 'low') {
    $query .= " AND quantity <= reorder_level AND quantity > 0";
} elseif ($filter === 'out') {
    $query .= " AND quantity = 0";
} elseif ($filter === 'all') {
    $query .= " AND quantity <= reorder_level";
}

// Search filter
if (!empty($search)) {
    $query .= " AND medication_name LIKE ?";
    $params[] = "%$search%";
}

$query .= " ORDER BY quantity ASC, medication_name ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$medicines = $stmt->fetchAll();

// ================================================================
// GET STATISTICS
// ================================================================

// Low Stock Count (quantity <= reorder_level AND quantity > 0)
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM medications_inventory 
    WHERE branch_id = ? AND quantity <= reorder_level AND quantity > 0 AND status = 'active'
");
$stmt->execute([$user_branch_id]);
$low_stock_count = $stmt->fetch()['count'] ?? 0;

// Out of Stock Count (quantity = 0)
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM medications_inventory 
    WHERE branch_id = ? AND quantity = 0 AND status = 'active'
");
$stmt->execute([$user_branch_id]);
$out_of_stock_count = $stmt->fetch()['count'] ?? 0;

// Total Low Stock (both low and out)
$total_low_stock = $low_stock_count + $out_of_stock_count;

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

$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

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
        --success-dark: #047857;
        --success-light: #D1FAE5;
        --warning: #D97706;
        --warning-light: #FEF3C7;
        --danger: #DC2626;
        --danger-light: #FEE2E2;
        --purple: #7C3AED;
        --purple-light: #EDE9FE;
        
        --bg-body: #F1F5F9;
        --bg-card: #FFFFFF;
        --border-color: #E2E8F0;
        --text-primary: #0F172A;
        --text-secondary: #475569;
        --text-muted: #94A3B8;
        --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
        --shadow-lg: 0 8px 30px rgba(0,0,0,0.12);
    }
    
    [data-theme="dark"] {
        --bg-body: #0F172A;
        --bg-card: #1E293B;
        --border-color: #334155;
        --text-primary: #F1F5F9;
        --text-secondary: #94A3B8;
        --text-muted: #64748B;
        --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
        --shadow-lg: 0 8px 30px rgba(0,0,0,0.4);
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
        min-height: 90px;
    }
    
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 30px rgba(0,0,0,0.2);
    }
    
    .stat-card.orange { background: linear-gradient(135deg, #D97706, #B45309); }
    .stat-card.red { background: linear-gradient(135deg, #DC2626, #991B1B); }
    .stat-card.purple { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
    .stat-card.blue { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
    
    .stat-card .stat-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        background: rgba(255,255,255,0.15);
        color: white;
        flex-shrink: 0;
        transition: all 0.3s ease;
    }
    
    .stat-card:hover .stat-icon {
        transform: scale(1.1) rotate(-5deg);
        background: rgba(255,255,255,0.25);
    }
    
    .stat-card .stat-number {
        font-size: 1.8rem;
        font-weight: 700;
        color: white;
        line-height: 1.2;
    }
    
    .stat-card .stat-label {
        font-size: 0.75rem;
        color: rgba(255,255,255,0.85);
        font-weight: 500;
        margin-top: 2px;
    }
    
    .stat-card .stat-trend {
        font-size: 0.6rem;
        font-weight: 600;
        padding: 2px 10px;
        border-radius: 20px;
        background: rgba(255,255,255,0.15);
        color: white;
        display: inline-block;
        margin-top: 4px;
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
    
    .card-title .title-orange { color: var(--warning); }
    .card-title .title-red { color: var(--danger); }
    .card-title .title-blue { color: var(--primary); }
    
    .result-count {
        font-size: 0.8rem;
        color: var(--text-secondary);
    }
    
    .result-count strong {
        color: var(--primary);
    }
    
    /* ================================================================
       FILTERS
       ================================================================ */
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
    
    .filter-btn.orange.active {
        background: var(--warning);
        border-color: var(--warning);
    }
    
    .filter-btn.red.active {
        background: var(--danger);
        border-color: var(--danger);
    }
    
    /* ================================================================
       SEARCH FORM
       ================================================================ */
    .search-form {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        align-items: center;
    }
    
    .search-form input[type="text"] {
        padding: 8px 14px;
        border: 2px solid var(--border-color);
        border-radius: 10px;
        font-size: 0.85rem;
        background: var(--bg-card);
        color: var(--text-primary);
        outline: none;
        transition: all 0.3s ease;
        flex: 1;
        min-width: 200px;
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
    
    /* ================================================================
       BUTTONS
       ================================================================ */
    .btn-outline {
        background: transparent;
        color: var(--text-secondary);
        border: 2px solid var(--border-color);
        padding: 6px 16px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.78rem;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .btn-outline:hover {
        border-color: var(--primary);
        color: var(--primary);
    }
    
    .btn-sm {
        padding: 3px 10px;
        font-size: 0.7rem;
        border-radius: 6px;
    }
    
    .btn-add {
        background: var(--success);
        color: white;
        padding: 8px 20px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .btn-add:hover {
        background: var(--success-dark);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
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
        background: var(--primary-light);
    }
    
    .data-table tbody tr:hover td {
        background: var(--success-light);
    }
    
    [data-theme="dark"] .data-table tbody tr:nth-child(even) {
        background: #1E293B;
    }
    
    [data-theme="dark"] .data-table tbody tr:hover td {
        background: #1A3A2A;
    }
    
    .data-table td {
        padding: 10px 14px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        vertical-align: middle;
    }
    
    /* Status badges */
    .stock-badge {
        padding: 2px 10px;
        border-radius: 10px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .stock-badge.low {
        background: var(--warning-light);
        color: var(--warning);
        animation: pulse-low 1.5s infinite;
    }
    
    .stock-badge.out {
        background: var(--danger-light);
        color: var(--danger);
        animation: pulse-low 1s infinite;
    }
    
    @keyframes pulse-low {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.6; }
    }
    
    [data-theme="dark"] .stock-badge.low {
        background: #3D2E0A;
        color: #FBBF24;
    }
    
    [data-theme="dark"] .stock-badge.out {
        background: #3A1A1A;
        color: #F87171;
    }
    
    .status-badge {
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.65rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .status-badge.active {
        background: var(--success-light);
        color: var(--success);
    }
    
    [data-theme="dark"] .status-badge.active {
        background: #1A3A2A;
        color: #34D399;
    }
    
    .expiry-badge {
        padding: 2px 10px;
        border-radius: 10px;
        font-size: 0.65rem;
        font-weight: 600;
    }
    
    .expiry-badge.valid {
        background: var(--success-light);
        color: var(--success);
    }
    
    .expiry-badge.expiring {
        background: var(--warning-light);
        color: var(--warning);
        animation: pulse-low 1.5s infinite;
    }
    
    .expiry-badge.expired {
        background: var(--danger-light);
        color: var(--danger);
        animation: pulse-low 1s infinite;
    }
    
    .batch-number {
        font-family: monospace;
        font-size: 0.75rem;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: 4px;
        background: var(--primary-light);
        color: var(--primary);
    }
    
    [data-theme="dark"] .batch-number {
        background: #1E3A5F;
        color: #6EA8FE;
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
    
    .action-btn.edit {
        background: var(--primary);
        color: white;
    }
    
    .action-btn.edit:hover {
        background: var(--primary-dark);
        transform: scale(1.05);
    }
    
    .action-btn.view {
        background: var(--purple);
        color: white;
    }
    
    .action-btn.view:hover {
        background: #6D28D9;
        transform: scale(1.05);
    }
    
    .action-btn.restock {
        background: var(--success);
        color: white;
    }
    
    .action-btn.restock:hover {
        background: var(--success-dark);
        transform: scale(1.05);
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
    
    .empty-state .sub {
        font-size: 0.8rem;
        color: var(--text-muted);
        margin-top: 4px;
    }
    
    .message-box {
        padding: 14px 20px;
        border-radius: 12px;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 500;
        animation: slideDown 0.4s ease;
    }
    
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .message-box.success {
        background: var(--success-light);
        color: #065F46;
        border: 2px solid #6EE7B7;
    }
    
    .message-box.error {
        background: var(--danger-light);
        color: #991B1B;
        border: 2px solid #FCA5A5;
    }
    
    .message-box i {
        font-size: 1.3rem;
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
    
    .animate-fade-in-up {
        animation: fadeInUp 0.5s ease forwards;
        opacity: 0;
    }
    
    .animate-fade-in-up:nth-child(1) { animation-delay: 0.05s; }
    .animate-fade-in-up:nth-child(2) { animation-delay: 0.1s; }
    .animate-fade-in-up:nth-child(3) { animation-delay: 0.15s; }
    
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .search-form {
            flex-direction: column;
            align-items: stretch;
        }
        .search-form input[type="text"] {
            min-width: 100%;
        }
        .filter-group {
            justify-content: center;
        }
        .card {
            padding: 12px 14px;
        }
        .data-table {
            font-size: 0.7rem;
        }
        .data-table th,
        .data-table td {
            padding: 5px 8px;
        }
        .stat-card .stat-number {
            font-size: 1.3rem;
        }
        .stat-card {
            padding: 12px 16px;
            min-height: 70px;
        }
        .stat-card .stat-icon {
            width: 36px;
            height: 36px;
            font-size: 1rem;
        }
    }
    
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr 1fr;
        }
        .stat-card .stat-number {
            font-size: 1.1rem;
        }
        .stat-card .stat-label {
            font-size: 0.6rem;
        }
        .stat-card .stat-icon {
            width: 30px;
            height: 30px;
            font-size: 0.8rem;
        }
        .stat-card {
            padding: 8px 12px;
            min-height: 60px;
        }
        .data-table {
            font-size: 0.65rem;
        }
        .data-table th,
        .data-table td {
            padding: 4px 6px;
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
                <i class="fas fa-exclamation-triangle mr-2" style="color: var(--danger);"></i> Low Stock Report
            </h1>
            <p class="page-subtitle">
                View all medicines with low or out of stock
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
                <span class="ml-2 inline-flex bg-red-100 text-red-700 px-3 py-1 rounded-full text-xs border border-red-200">
                    <i class="fas fa-exclamation-triangle mr-1"></i> <?= $total_low_stock ?> need attention
                </span>
            </p>
        </div>
        <div>
            <a href="inventory.php" class="btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Inventory
            </a>
            <a href="inventory.php?add=1" class="btn-add">
                <i class="fas fa-plus-circle"></i> Add Stock
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-grid animate-fade-in-up">
        <a href="low_stock.php?filter=all" class="stat-card orange">
            <div>
                <p class="stat-label">Total Low Stock</p>
                <p class="stat-number"><?= number_format($total_low_stock) ?></p>
                <span class="stat-trend"><i class="fas fa-warehouse"></i> Need attention</span>
            </div>
            <div class="stat-icon"><i class="fas fa-warehouse"></i></div>
        </a>
        
        <a href="low_stock.php?filter=low" class="stat-card orange">
            <div>
                <p class="stat-label">Low Stock</p>
                <p class="stat-number"><?= number_format($low_stock_count) ?></p>
                <span class="stat-trend"><i class="fas fa-exclamation-triangle"></i> Below reorder level</span>
            </div>
            <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
        </a>
        
        <a href="low_stock.php?filter=out" class="stat-card red">
            <div>
                <p class="stat-label">Out of Stock</p>
                <p class="stat-number"><?= number_format($out_of_stock_count) ?></p>
                <span class="stat-trend"><i class="fas fa-times-circle"></i> Empty</span>
            </div>
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- MESSAGE -->
    <!-- ================================================================ -->
    <?php if (count($medicines) == 0 && empty($search)): ?>
        <div class="message-box success">
            <i class="fas fa-check-circle"></i>
            🎉 All medicines are well stocked! No low stock items found.
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FILTERS & SEARCH -->
    <!-- ================================================================ -->
    <div class="card mb-5 animate-fade-in-up">
        <div class="filter-group">
            <a href="low_stock.php?filter=all" class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">
                <i class="fas fa-list"></i> All
            </a>
            <a href="low_stock.php?filter=low" class="filter-btn orange <?= $filter === 'low' ? 'active' : '' ?>">
                <i class="fas fa-exclamation-triangle"></i> Low Stock
            </a>
            <a href="low_stock.php?filter=out" class="filter-btn red <?= $filter === 'out' ? 'active' : '' ?>">
                <i class="fas fa-times-circle"></i> Out of Stock
            </a>
        </div>
        
        <form method="GET" class="search-form">
            <input type="hidden" name="filter" value="<?= $filter ?>">
            
            <input type="text" name="search" placeholder="🔍 Search medicine..." 
                   value="<?= htmlspecialchars($search) ?>">
            
            <button type="submit" class="btn-search">
                <i class="fas fa-search"></i> Search
            </button>
            
            <a href="low_stock.php?filter=<?= $filter ?>" class="btn-reset">
                <i class="fas fa-times"></i> Reset
            </a>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- LOW STOCK TABLE -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-orange mr-2"></i>
                Medicines Needing Restock
                <span class="result-count ml-2">(<strong><?= number_format(count($medicines)) ?></strong> record(s))</span>
            </h3>
        </div>
        
        <?php if (count($medicines) > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="border-radius: 8px 0 0 0;">#</th>
                            <th>Medicine Name</th>
                            <th>Category</th>
                            <th>Current Qty</th>
                            <th>Reorder Level</th>
                            <th>Status</th>
                            <th>Batch Number</th>
                            <th>Expiry Date</th>
                            <th>Supplier</th>
                            <th style="border-radius: 0 8px 0 0;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $counter = 1; ?>
                        <?php foreach ($medicines as $item): ?>
                            <?php
                                $stock_status = 'low';
                                $stock_label = 'Low Stock';
                                if ($item['quantity'] <= 0) {
                                    $stock_status = 'out';
                                    $stock_label = 'Out of Stock';
                                }
                                
                                $expiry_status = 'valid';
                                if (!empty($item['expiry_date'])) {
                                    $days_until_expiry = (strtotime($item['expiry_date']) - time()) / 86400;
                                    if ($days_until_expiry < 0) {
                                        $expiry_status = 'expired';
                                    } elseif ($days_until_expiry <= 30) {
                                        $expiry_status = 'expiring';
                                    }
                                }
                            ?>
                            <tr>
                                <td><?= $counter++ ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($item['medication_name']) ?></strong>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($item['unit'] ?? 'pcs') ?></div>
                                </td>
                                <td><?= htmlspecialchars($item['category'] ?? 'N/A') ?></td>
                                <td>
                                    <strong style="color: <?= $item['quantity'] <= 0 ? 'var(--danger)' : 'var(--warning)' ?>;">
                                        <?= $item['quantity'] ?>
                                    </strong>
                                </td>
                                <td><?= $item['reorder_level'] ?></td>
                                <td>
                                    <span class="stock-badge <?= $stock_status ?>">
                                        <i class="fas <?= $stock_status === 'low' ? 'fa-exclamation-triangle' : 'fa-times-circle' ?>"></i>
                                        <?= $stock_label ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($item['batch_number'])): ?>
                                        <span class="batch-number"><?= htmlspecialchars($item['batch_number']) ?></span>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($item['expiry_date'])): ?>
                                        <span class="expiry-badge <?= $expiry_status ?>">
                                            <?= date('M d, Y', strtotime($item['expiry_date'])) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($item['supplier'] ?? 'N/A') ?></td>
                                <td>
                                    <div class="flex gap-1">
                                        <a href="inventory.php?view=<?= $item['id'] ?>" 
                                           class="action-btn view" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="inventory.php?edit=<?= $item['id'] ?>" 
                                           class="action-btn edit" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="inventory.php?add=1" 
                                           class="action-btn restock" title="Restock">
                                            <i class="fas fa-plus"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-check-circle" style="color: var(--success);"></i>
                <p>No low stock medicines found</p>
                <p class="sub">All medicines are well stocked! 🎉</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- RECOMMENDATIONS -->
    <!-- ================================================================ -->
    <?php if (count($medicines) > 0): ?>
    <div class="card mt-4 animate-fade-in-up" style="border-color: var(--warning);">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-lightbulb" style="color: var(--warning);"></i>
                Restock Recommendations
            </h3>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="p-4 border rounded-lg" style="border-color: var(--border-color);">
                <h4 class="font-semibold text-orange-600 mb-2">
                    <i class="fas fa-exclamation-triangle mr-1"></i> Priority - Out of Stock
                </h4>
                <p class="text-sm text-gray-600">
                    <?php 
                        $out_items = array_filter($medicines, function($item) {
                            return $item['quantity'] <= 0;
                        });
                    ?>
                    <strong><?= count($out_items) ?></strong> medicine(s) are completely out of stock.
                    <?php if (count($out_items) > 0): ?>
                        <span class="block text-xs text-gray-400 mt-1">
                            <?php 
                                $names = array_slice(array_column($out_items, 'medication_name'), 0, 5);
                                echo implode(', ', $names);
                                if (count($out_items) > 5) echo ' and ' . (count($out_items) - 5) . ' more';
                            ?>
                        </span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="p-4 border rounded-lg" style="border-color: var(--border-color);">
                <h4 class="font-semibold text-orange-600 mb-2">
                    <i class="fas fa-clock mr-1"></i> Low Stock - Order Soon
                </h4>
                <p class="text-sm text-gray-600">
                    <?php 
                        $low_items = array_filter($medicines, function($item) {
                            return $item['quantity'] > 0 && $item['quantity'] <= $item['reorder_level'];
                        });
                    ?>
                    <strong><?= count($low_items) ?></strong> medicine(s) are below reorder level.
                    <?php if (count($low_items) > 0): ?>
                        <span class="block text-xs text-gray-400 mt-1">
                            <?php 
                                $names = array_slice(array_column($low_items, 'medication_name'), 0, 5);
                                echo implode(', ', $names);
                                if (count($low_items) > 5) echo ' and ' . (count($low_items) - 5) . ' more';
                            ?>
                        </span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer mt-5">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Low Stock Report
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
    // SEARCH
    // ================================================================
    var searchInput = document.querySelector('.search-form input[type="text"]');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.closest('form').submit();
            }
        });
    }

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            var searchInput = document.querySelector('.search-form input[type="text"]');
            searchInput?.focus();
            searchInput?.select();
        }
        if (e.key === 'Escape') {
            var searchInput = document.querySelector('.search-form input[type="text"]');
            if (searchInput && document.activeElement === searchInput) {
                searchInput.value = '';
                searchInput.blur();
            }
        }
    });

    console.log('%c💊 Braick - Low Stock Report', 'font-size:18px; font-weight:bold; color:#D97706;');
    console.log('%c📦 Low Stock: <?= $low_stock_count ?> | Out of Stock: <?= $out_of_stock_count ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c⚠️ Total items needing attention: <?= $total_low_stock ?>', 'font-size:13px; color:#DC2626;');
</script>

</body>
</html>