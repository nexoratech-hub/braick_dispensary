<?php
// ================================================================
// FILE: frontend/pages/cashier/partial_payments.php
// CASHIER - PARTIAL PAYMENTS HISTORY
// Shows all bills with status 'partial'
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// DEFAULT SESSION - Cashier Dodoma (ID: 10)
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    $_SESSION['user_id'] = 10;
    $_SESSION['full_name'] = 'Cashier Dodoma';
    $_SESSION['role'] = 'cashier';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'cashier.dodoma';
    $_SESSION['is_admin'] = false;
}

$user_id = $_SESSION['user_id'] ?? 10;
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_full_name = $_SESSION['full_name'] ?? 'Cashier Dodoma';

// ================================================================
// INCLUDE CONFIG
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

$db = getDB();

// ================================================================
// GET FILTERS
// ================================================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// ================================================================
// GET PARTIAL BILLS WITH FILTERS
// ================================================================
$params = [$user_branch_id];
$sql = "
    SELECT 
        pb.*,
        p.full_name as patient_name,
        p.patient_id as patient_number,
        p.phone as patient_phone,
        v.visit_number,
        v.visit_type,
        v.visit_date,
        u.full_name as doctor_name,
        u2.full_name as created_by_name,
        (
            SELECT COUNT(*) FROM bill_items WHERE bill_id = pb.id
        ) as item_count,
        (
            SELECT COUNT(*) FROM bill_items WHERE bill_id = pb.id AND is_paid = 1
        ) as paid_item_count,
        (
            SELECT COUNT(*) FROM bill_items WHERE bill_id = pb.id AND (is_paid = 0 OR is_paid IS NULL)
        ) as pending_item_count,
        (
            SELECT SUM(amount) FROM payments WHERE bill_id = pb.id
        ) as total_paid_amount,
        (
            SELECT COUNT(*) FROM payments WHERE bill_id = pb.id
        ) as payment_count
    FROM patient_bills pb
    JOIN patients p ON pb.patient_id = p.id
    LEFT JOIN visits v ON pb.visit_id = v.id
    LEFT JOIN users u ON v.doctor_id = u.id
    LEFT JOIN users u2 ON pb.created_by = u2.id
    WHERE pb.branch_id = ? AND pb.status = 'partial'
";

if (!empty($search)) {
    $sql .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR pb.bill_number LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($date_from) && !empty($date_to)) {
    $sql .= " AND DATE(pb.updated_at) BETWEEN ? AND ?";
    $params[] = $date_from;
    $params[] = $date_to;
}

$sql .= " ORDER BY pb.updated_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$partial_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET TOTAL COUNT FOR PAGINATION
// ================================================================
$count_sql = "
    SELECT COUNT(*) as total 
    FROM patient_bills pb
    JOIN patients p ON pb.patient_id = p.id
    WHERE pb.branch_id = ? AND pb.status = 'partial'
";
$count_params = [$user_branch_id];

if (!empty($search)) {
    $count_sql .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR pb.bill_number LIKE ?)";
    $count_params[] = $search_param;
    $count_params[] = $search_param;
    $count_params[] = $search_param;
}

if (!empty($date_from) && !empty($date_to)) {
    $count_sql .= " AND DATE(pb.updated_at) BETWEEN ? AND ?";
    $count_params[] = $date_from;
    $count_params[] = $date_to;
}

$stmt = $db->prepare($count_sql);
$stmt->execute($count_params);
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
$total_pages = ceil($total_records / $limit);

// ================================================================
// GET PAYMENT HISTORY FOR EACH BILL
// ================================================================
$payment_history = [];
foreach ($partial_bills as $bill) {
    $stmt = $db->prepare("
        SELECT * FROM payments 
        WHERE bill_id = ? 
        ORDER BY received_at DESC
    ");
    $stmt->execute([$bill['id']]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $payment_history[$bill['id']] = $payments;
}

// ================================================================
// CALCULATE TOTALS
// ================================================================
$total_partial_amount = 0;
$total_balance_amount = 0;
$total_discount_amount = 0;
foreach ($partial_bills as $bill) {
    $total_partial_amount += (float)($bill['total_paid_amount'] ?? 0);
    $total_balance_amount += (float)($bill['balance'] ?? 0);
    $total_discount_amount += (float)($bill['discount_amount'] ?? 0);
}

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/cashier_header.php';
include_once __DIR__ . '/../../components/cashier_sidebar.php';
?>

<style>
    /* ================================================================
       PARTIAL PAYMENTS STYLES
       ================================================================ */
    :root {
        --primary: #d97706;
        --primary-dark: #b45309;
        --primary-light: #f59e0b;
        --primary-bg: #fef3c7;
        --success: #059669;
        --success-bg: #ecfdf5;
        --warning: #d97706;
        --warning-bg: #fef3c7;
        --danger: #dc2626;
        --danger-bg: #fee2e2;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 12px;
        margin-bottom: 20px;
    }
    
    .stat-box {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 14px 16px;
        border: 2px solid var(--border-color);
        text-align: center;
        transition: all 0.3s ease;
    }
    
    .stat-box:hover {
        border-color: var(--primary);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(217, 119, 6, 0.1);
    }
    
    .stat-box .number {
        font-size: 1.6rem;
        font-weight: 700;
        color: var(--primary);
    }
    
    .stat-box .number.green { color: #059669; }
    .stat-box .number.orange { color: #d97706; }
    .stat-box .number.purple { color: #7c3aed; }
    .stat-box .number.red { color: #dc2626; }
    
    .stat-box .label {
        font-size: 0.7rem;
        color: var(--text-secondary);
        font-weight: 500;
        margin-top: 2px;
    }
    
    .filter-section {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 16px 20px;
        border: 2px solid var(--border-color);
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 12px;
        margin-bottom: 20px;
    }
    
    .filter-section .filter-group {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    
    .filter-section .filter-group label {
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--text-primary);
    }
    
    .filter-section input[type="date"],
    .filter-section input[type="text"],
    .filter-section select {
        padding: 6px 12px;
        border: 2px solid var(--border-color);
        border-radius: 8px;
        font-size: 0.85rem;
        background: var(--bg-card);
        color: var(--text-primary);
        outline: none;
    }
    
    .filter-section input:focus,
    .filter-section select:focus {
        border-color: #d97706;
        box-shadow: 0 0 0 3px rgba(217, 119, 6, 0.15);
    }
    
    .bills-table-wrap {
        overflow-x: auto;
        border-radius: 12px;
        border: 2px solid var(--border-color);
        background: var(--bg-card);
        margin-bottom: 20px;
    }
    
    .bills-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.82rem;
        min-width: 950px;
    }
    
    .bills-table thead th {
        text-align: left;
        padding: 10px 14px;
        font-weight: 700;
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: white;
        background: #d97706;
        border-bottom: 3px solid #b45309;
        white-space: nowrap;
        position: sticky;
        top: 0;
        z-index: 10;
    }
    
    .bills-table thead th:first-child { border-radius: 8px 0 0 0; }
    .bills-table thead th:last-child { border-radius: 0 8px 0 0; }
    
    .bills-table tbody td {
        padding: 10px 14px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        vertical-align: middle;
    }
    
    .bills-table tbody tr:hover td {
        background: var(--table-hover);
    }
    
    .bills-table tbody tr.partial-row td {
        background: var(--warning-bg);
    }
    
    .bill-status {
        font-size: 0.6rem;
        font-weight: 600;
        padding: 2px 10px;
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .bill-status.partial {
        background: var(--warning-bg);
        color: var(--warning);
    }
    
    .bill-status.paid {
        background: var(--success-bg);
        color: var(--success);
    }
    
    .bill-status.pending {
        background: var(--warning-bg);
        color: var(--warning);
    }
    
    .amount-paid {
        font-weight: 700;
        color: #059669;
        font-family: monospace;
    }
    
    .amount-balance {
        font-weight: 700;
        color: #dc2626;
        font-family: monospace;
    }
    
    .amount-discount {
        font-weight: 600;
        color: #d97706;
        font-family: monospace;
    }
    
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 18px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.82rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        white-space: nowrap;
    }
    
    .btn-warning {
        background: #d97706;
        color: white;
    }
    .btn-warning:hover {
        background: #b45309;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3);
    }
    
    .btn-success {
        background: #059669;
        color: white;
    }
    .btn-success:hover {
        background: #047857;
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
        border-color: #d97706;
        color: #d97706;
    }
    
    .btn-sm {
        padding: 4px 12px;
        font-size: 0.75rem;
    }
    
    .pagination {
        display: flex;
        justify-content: center;
        gap: 6px;
        margin-top: 16px;
        flex-wrap: wrap;
    }
    
    .pagination .page-btn {
        padding: 6px 14px;
        border-radius: 6px;
        border: 2px solid var(--border-color);
        background: var(--bg-card);
        color: var(--text-primary);
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 0.8rem;
        text-decoration: none;
    }
    
    .pagination .page-btn:hover {
        border-color: #d97706;
        color: #d97706;
    }
    
    .pagination .page-btn.active {
        background: #d97706;
        border-color: #d97706;
        color: white;
    }
    
    .pagination .page-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    .expand-btn {
        background: none;
        border: none;
        cursor: pointer;
        color: var(--primary);
        font-size: 0.7rem;
        padding: 2px 6px;
        border-radius: 4px;
        transition: all 0.2s;
    }
    .expand-btn:hover { background: var(--primary-bg); }
    
    .items-container {
        display: none;
        padding: 6px 0 6px 20px;
        border-left: 2px solid var(--primary);
        margin-top: 4px;
        background: var(--bg-body);
        border-radius: 0 4px 4px 0;
    }
    
    .items-container.open { display: block; }
    
    .item-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 3px 0;
        font-size: 0.7rem;
        border-bottom: 1px dashed var(--border-color);
    }
    
    .item-row:last-child { border-bottom: none; }
    
    .item-row .item-name { font-weight: 500; color: var(--text-primary); }
    .item-row .item-price { font-weight: 600; font-family: monospace; }
    .item-row .item-price.paid { color: #059669; }
    .item-row .item-price.pending { color: #dc2626; }
    
    .item-badge {
        font-size: 0.55rem;
        padding: 1px 6px;
        border-radius: 8px;
    }
    .item-badge.paid { background: #d1fae5; color: #059669; }
    .item-badge.pending { background: #fef3c7; color: #d97706; }
    
    .payment-history {
        padding: 8px 12px;
        background: #f8fafc;
        border-radius: 6px;
        margin-top: 4px;
    }
    
    .payment-history .payment-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 3px 0;
        font-size: 0.7rem;
        border-bottom: 1px dashed #e5e7eb;
    }
    
    .payment-history .payment-item:last-child { border-bottom: none; }
    
    .payment-history .payment-item .receipt { font-family: monospace; font-size: 0.65rem; color: #6b7280; }
    .payment-history .payment-item .method { font-size: 0.6rem; background: #e5e7eb; padding: 1px 8px; border-radius: 10px; color: #4b5563; }
    .payment-history .payment-item .amount { font-weight: 600; color: #059669; font-family: monospace; }
    .payment-history .payment-item .date { font-size: 0.6rem; color: #6b7280; }
    
    .no-results {
        text-align: center;
        padding: 40px 20px;
        color: var(--text-secondary);
    }
    
    .no-results i { font-size: 3rem; display: block; margin-bottom: 12px; color: var(--border-color); }
    
    @media (max-width: 768px) {
        .bills-table { font-size: 0.7rem; min-width: 650px; }
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .filter-section { flex-direction: column; align-items: stretch; }
        .filter-section .filter-group { justify-content: center; }
        .filter-section .btn { width: 100%; justify-content: center; }
    }
</style>

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
            <input type="text" id="searchInput" placeholder="Search partial bills by patient or bill number..." 
                   value="<?= htmlspecialchars($search) ?>">
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
        <a href="profile.php">
            <img src="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png' ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%23059669%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3EC%3C/text%3E%3C/svg%3E'">
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
                <i class="fas fa-hand-holding-heart mr-2" style="color: #d97706;"></i> Partial Payments
                <span class="role-badge-display ml-2">CASHIER</span>
            </h1>
            <p class="page-subtitle">
                View all bills with partial payments
                <span class="ml-2 inline-flex bg-orange-100 text-orange-700 px-3 py-1 rounded-full text-xs border border-orange-200">
                    <i class="fas fa-file-invoice mr-1"></i> <?= number_format($total_records) ?> partial bill(s)
                </span>
                <span class="ml-2 inline-flex bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs border border-green-200">
                    <i class="fas fa-money-bill mr-1"></i> Paid: <?= number_format($total_partial_amount, 2) ?>
                </span>
                <span class="ml-2 inline-flex bg-red-100 text-red-700 px-3 py-1 rounded-full text-xs border border-red-200">
                    <i class="fas fa-money-bill mr-1"></i> Balance: <?= number_format($total_balance_amount, 2) ?>
                </span>
            </p>
        </div>
        <div>
            <a href="dashboard.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <a href="process_payment.php" class="btn btn-success btn-sm">
                <i class="fas fa-money-bill-wave"></i> Process Payment
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS -->
    <!-- ================================================================ -->
    <div class="stats-grid">
        <div class="stat-box">
            <p class="number orange"><?= number_format($total_records) ?></p>
            <p class="label">Total Partial Bills</p>
        </div>
        <div class="stat-box">
            <p class="number green">TSh <?= number_format($total_partial_amount, 2) ?></p>
            <p class="label">Total Paid Amount</p>
        </div>
        <div class="stat-box">
            <p class="number red">TSh <?= number_format($total_balance_amount, 2) ?></p>
            <p class="label">Total Balance</p>
        </div>
        <div class="stat-box">
            <p class="number orange">TSh <?= number_format($total_discount_amount, 2) ?></p>
            <p class="label">Total Discount Given</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FILTER SECTION -->
    <!-- ================================================================ -->
    <form method="GET" action="" class="filter-section">
        <div class="filter-group">
            <label><i class="fas fa-calendar"></i> Date From:</label>
            <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
        </div>
        <div class="filter-group">
            <label><i class="fas fa-calendar"></i> Date To:</label>
            <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
        </div>
        <div class="filter-group">
            <label><i class="fas fa-list"></i> Per Page:</label>
            <select name="limit">
                <option value="20" <?= $limit == 20 ? 'selected' : '' ?>>20</option>
                <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                <option value="200" <?= $limit == 200 ? 'selected' : '' ?>>200</option>
            </select>
        </div>
        <button type="submit" class="btn btn-warning btn-sm">
            <i class="fas fa-filter"></i> Filter
        </button>
        <a href="partial_payments.php" class="btn btn-outline btn-sm">
            <i class="fas fa-times"></i> Clear
        </a>
    </form>

    <!-- ================================================================ -->
    <!-- PARTIAL BILLS TABLE -->
    <!-- ================================================================ -->
    <div class="bills-table-wrap">
        <table class="bills-table" id="billsTable">
            <thead>
                <tr>
                    <th style="width:40px;">#</th>
                    <th>Bill Number</th>
                    <th>Patient</th>
                    <th>Items</th>
                    <th style="text-align:right;">Total (TSh)</th>
                    <th style="text-align:right;">Paid (TSh)</th>
                    <th style="text-align:right;">Discount (TSh)</th>
                    <th style="text-align:right;">Balance (TSh)</th>
                    <th style="text-align:center;">Status</th>
                    <th style="text-align:center;">Last Payment</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($partial_bills) > 0): ?>
                    <?php $counter = $offset + 1; foreach ($partial_bills as $bill): 
                        $payments = $payment_history[$bill['id']] ?? [];
                        $items_count = (int)$bill['item_count'];
                        $paid_items = (int)$bill['paid_item_count'];
                        $pending_items = (int)$bill['pending_item_count'];
                        $total_paid = (float)($bill['total_paid_amount'] ?? 0);
                        $balance = (float)($bill['balance'] ?? 0);
                        $discount = (float)($bill['discount_amount'] ?? 0);
                        $last_payment = !empty($payments) ? $payments[0] : null;
                    ?>
                    <tr class="partial-row">
                        <td><?= $counter++ ?></td>
                        <td>
                            <span class="font-mono text-xs font-semibold" style="color:#d97706;">
                                <?= htmlspecialchars($bill['bill_number']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="font-medium text-sm"><?= htmlspecialchars($bill['patient_name']) ?></div>
                            <div class="text-xs text-gray-400"><?= htmlspecialchars($bill['patient_number'] ?? 'N/A') ?></div>
                            <?php if (!empty($bill['doctor_name'])): ?>
                                <div class="text-xs text-primary">
                                    <i class="fas fa-user-md"></i> <?= htmlspecialchars($bill['doctor_name']) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="expand-btn" onclick="toggleItems(this)">
                                <i class="fas fa-chevron-right"></i>
                                <?= $items_count ?> items
                                <?php if ($paid_items > 0): ?>
                                    <span style="color:#059669;">(<?= $paid_items ?> paid)</span>
                                <?php endif; ?>
                                <?php if ($pending_items > 0): ?>
                                    <span style="color:#dc2626;">(<?= $pending_items ?> pending)</span>
                                <?php endif; ?>
                            </button>
                            <div class="items-container" style="display:none;">
                                <?php 
                                // Get items for this bill
                                $stmt = $db->prepare("SELECT * FROM bill_items WHERE bill_id = ?");
                                $stmt->execute([$bill['id']]);
                                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($items as $item): 
                                    $is_paid = ($item['is_paid'] ?? 0) == 1;
                                    $price = (float)($item['total_price'] ?? $item['unit_price'] ?? 0);
                                ?>
                                <div class="item-row <?= $is_paid ? 'paid-item' : '' ?>">
                                    <span class="item-name">
                                        <?= htmlspecialchars($item['item_name']) ?>
                                        <span class="item-badge <?= $is_paid ? 'paid' : 'pending' ?>">
                                            <?= $is_paid ? '✅ Paid' : '⏳ Pending' ?>
                                        </span>
                                    </span>
                                    <span class="item-price <?= $is_paid ? 'paid' : 'pending' ?>">
                                        TSh <?= number_format($price, 2) ?>
                                    </span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td style="text-align:right; font-weight:700; color:#059669; font-family:monospace;">
                            <?= number_format($bill['total_amount'] ?? 0, 2) ?>
                        </td>
                        <td style="text-align:right; font-weight:700; color:#059669; font-family:monospace;">
                            <?= number_format($total_paid, 2) ?>
                        </td>
                        <td style="text-align:right; font-weight:600; color:#d97706; font-family:monospace;">
                            <?= number_format($discount, 2) ?>
                        </td>
                        <td style="text-align:right; font-weight:700; color:#dc2626; font-family:monospace;">
                            <?= number_format($balance, 2) ?>
                        </td>
                        <td style="text-align:center;">
                            <span class="bill-status partial">🔄 Partial</span>
                        </td>
                        <td style="text-align:center; font-size:0.7rem; color:#6b7280;">
                            <?php if ($last_payment): ?>
                                <?= date('d/m/Y H:i', strtotime($last_payment['received_at'])) ?>
                            <?php else: ?>
                                <?= date('d/m/Y H:i', strtotime($bill['updated_at'])) ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if (!empty($payments)): ?>
                    <tr>
                        <td colspan="10" style="padding:0;">
                            <div class="payment-history">
                                <div style="font-size:0.65rem; font-weight:600; color:#6b7280; margin-bottom:4px;">
                                    <i class="fas fa-receipt mr-1"></i> Payment Receipts (<?= count($payments) ?>)
                                </div>
                                <?php foreach ($payments as $payment): ?>
                                <div class="payment-item">
                                    <span class="receipt"><?= htmlspecialchars($payment['receipt_number']) ?></span>
                                    <span class="method"><?= $payment['payment_method'] ?></span>
                                    <span class="amount">TSh <?= number_format($payment['amount'], 2) ?></span>
                                    <span class="date"><?= date('d/m/Y H:i', strtotime($payment['received_at'])) ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10">
                            <div class="no-results">
                                <i class="fas fa-hand-holding-heart" style="color:#d97706;"></i>
                                <p style="font-size:1.1rem; font-weight:600;">No Partial Payments Found</p>
                                <p class="text-sm mt-1">Try adjusting your filters or check back later</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ================================================================ -->
    <!-- PAGINATION -->
    <!-- ================================================================ -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <a href="?page=1&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>&limit=<?= $limit ?>&search=<?= urlencode($search) ?>" 
           class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">&laquo; First</a>
        
        <a href="?page=<?= max(1, $page-1) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>&limit=<?= $limit ?>&search=<?= urlencode($search) ?>" 
           class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">&lsaquo; Prev</a>
        
        <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
            <a href="?page=<?= $i ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>&limit=<?= $limit ?>&search=<?= urlencode($search) ?>" 
               class="page-btn <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
        
        <a href="?page=<?= min($total_pages, $page+1) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>&limit=<?= $limit ?>&search=<?= urlencode($search) ?>" 
           class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">Next &rsaquo;</a>
        
        <a href="?page=<?= $total_pages ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>&limit=<?= $limit ?>&search=<?= urlencode($search) ?>" 
           class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">Last &raquo;</a>
    </div>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="footer" style="margin-top: 24px;">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Partial Payments
            <span class="text-gray-300 mx-2">|</span>
            Showing <?= count($partial_bills) ?> of <?= number_format($total_records) ?> records
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // TOGGLE ITEMS EXPAND
    // ================================================================
    function toggleItems(element) {
        var container = element.parentElement.querySelector('.items-container');
        var icon = element.querySelector('.fa-chevron-right');
        if (container) {
            if (container.classList.contains('open')) {
                container.classList.remove('open');
                if (icon) icon.style.transform = 'rotate(0deg)';
            } else {
                container.classList.add('open');
                if (icon) icon.style.transform = 'rotate(90deg)';
            }
        }
    }

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
        var currentUrl = new URL(window.location.href);
        if (query.length > 0) {
            currentUrl.searchParams.set('search', query);
        } else {
            currentUrl.searchParams.delete('search');
        }
        currentUrl.searchParams.set('page', '1');
        window.location.href = currentUrl.toString();
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
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }
    });

    console.log('%c🔄 Braick - Partial Payments', 'font-size:18px; font-weight:bold; color:#d97706;');
    console.log('%c📊 Total Partial Bills: <?= number_format($total_records) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c💰 Total Paid: <?= number_format($total_partial_amount, 2) ?>', 'font-size:13px; color:#059669;');
    console.log('%c💸 Total Balance: <?= number_format($total_balance_amount, 2) ?>', 'font-size:13px; color:#dc2626;');
</script>

</body>
</html>