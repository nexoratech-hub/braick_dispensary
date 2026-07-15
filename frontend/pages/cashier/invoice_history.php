<?php
// ================================================================
// FILE: frontend/pages/cashier/invoice_history.php
// CASHIER - INVOICE HISTORY
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// INCLUDE CONFIG
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

// ================================================================
// SESSION - Default to reception.rose (Cashier)
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'reception') {
    $_SESSION['user_id'] = 6;
    $_SESSION['full_name'] = 'Rose Mwangi';
    $_SESSION['role'] = 'reception';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'reception.rose';
    $_SESSION['is_admin'] = false;
}

$user_id = $_SESSION['user_id'] ?? 6;
$user_full_name = $_SESSION['full_name'] ?? 'Rose Mwangi';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';

$db = getDB();

// ================================================================
// GET FILTERS
// ================================================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';

// ================================================================
// GET INVOICES (BILLS)
// ================================================================
$query = "
    SELECT 
        b.*,
        p.full_name as patient_name,
        p.patient_id,
        v.visit_number,
        u.full_name as created_by_name,
        (SELECT COUNT(*) FROM payments WHERE bill_id = b.id) as payment_count,
        (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE bill_id = b.id) as total_paid
    FROM bills b
    JOIN patients p ON b.patient_id = p.id
    LEFT JOIN visits v ON b.visit_id = v.id
    LEFT JOIN users u ON b.created_by = u.id
    WHERE b.branch_id = ?
";

if (!empty($search)) {
    $query .= " AND (b.bill_number LIKE ? OR p.full_name LIKE ? OR p.patient_id LIKE ?)";
}

if (!empty($date_from) && !empty($date_to)) {
    $query .= " AND DATE(b.created_at) BETWEEN ? AND ?";
}

if (!empty($status_filter)) {
    $query .= " AND b.status = ?";
}

$query .= " ORDER BY b.created_at DESC";

$stmt = $db->prepare($query);

$params = [$user_branch_id];

if (!empty($search)) {
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($date_from) && !empty($date_to)) {
    $params[] = $date_from;
    $params[] = $date_to;
}

if (!empty($status_filter)) {
    $params[] = $status_filter;
}

$stmt->execute($params);
$invoices = $stmt->fetchAll();

// ================================================================
// GET STATISTICS
// ================================================================
$total_invoices = count($invoices);
$total_amount = 0;
$total_paid = 0;
$total_balance = 0;

$status_counts = [
    'pending' => 0,
    'partial' => 0,
    'paid' => 0,
    'cancelled' => 0
];

foreach ($invoices as $invoice) {
    $total_amount += $invoice['grand_total'];
    $total_paid += $invoice['amount_paid'];
    $total_balance += $invoice['balance'];
    
    if (isset($status_counts[$invoice['status']])) {
        $status_counts[$invoice['status']]++;
    }
}

// ================================================================
// GET SIDEBAR STATISTICS
// ================================================================
$pending_bills = 0;
$partial_payments = 0;
$paid_today = 0;
$patients_waiting = 0;

try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM bills WHERE branch_id = ? AND status = 'pending'");
    $stmt->execute([$user_branch_id]);
    $pending_bills = $stmt->fetch()['count'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM bills WHERE branch_id = ? AND status = 'partial'");
    $stmt->execute([$user_branch_id]);
    $partial_payments = $stmt->fetch()['count'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM bills WHERE branch_id = ? AND status = 'paid' AND DATE(updated_at) = CURDATE()");
    $stmt->execute([$user_branch_id]);
    $paid_today = $stmt->fetch()['count'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as count FROM bills WHERE branch_id = ? AND status IN ('pending', 'partial')");
    $stmt->execute([$user_branch_id]);
    $patients_waiting = $stmt->fetch()['count'] ?? 0;
} catch (Exception $e) {
    // Keep counts as 0
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
include_once __DIR__ . '/../../components/cashier_header.php';
include_once __DIR__ . '/../../components/cashier_sidebar.php';
?>

<style>
    /* ================================================================
       INVOICE HISTORY STYLES
       ================================================================ */
    
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
        cursor: default;
    }
    
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }
    
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
    
    .stat-card.orange { background: #D97706; }
    .stat-card.blue { background: #0B5ED7; }
    .stat-card.green { background: #059669; }
    .stat-card.purple { background: #7C3AED; }
    .stat-card.teal { background: #0D9488; }
    .stat-card.red { background: #DC2626; }
    
    .filter-section {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 12px 16px;
        border: 2px solid var(--border-color);
        margin-bottom: 16px;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
    }
    
    .filter-section .form-control {
        padding: 6px 12px;
        border: 2px solid var(--border-color);
        border-radius: 8px;
        font-size: 0.8rem;
        background: var(--bg-card);
        color: var(--text-primary);
        outline: none;
        transition: all 0.3s ease;
    }
    
    .filter-section .form-control:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
    }
    
    .filter-section .btn-filter {
        padding: 6px 16px;
        border-radius: 8px;
        font-size: 0.8rem;
        font-weight: 600;
        background: var(--primary);
        color: white;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .filter-section .btn-filter:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
    }
    
    .filter-section .btn-clear {
        padding: 6px 16px;
        border-radius: 8px;
        font-size: 0.8rem;
        font-weight: 600;
        background: transparent;
        color: var(--text-secondary);
        border: 2px solid var(--border-color);
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
    }
    
    .filter-section .btn-clear:hover {
        border-color: var(--primary);
        color: var(--primary);
    }
    
    /* Invoice Card */
    .invoice-card {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 16px 20px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        margin-bottom: 16px;
    }
    
    .invoice-card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.08);
    }
    
    .invoice-card .invoice-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 8px;
        padding-bottom: 8px;
        border-bottom: 2px solid var(--border-color);
    }
    
    .invoice-card .invoice-header .invoice-number {
        font-weight: 700;
        font-size: 1rem;
        color: var(--text-primary);
        font-family: monospace;
    }
    
    .invoice-card .invoice-header .invoice-status {
        font-size: 0.7rem;
        font-weight: 600;
        padding: 4px 12px;
        border-radius: 20px;
    }
    
    .invoice-card .invoice-header .invoice-status.pending {
        background: #FEF3C7;
        color: #D97706;
    }
    
    .invoice-card .invoice-header .invoice-status.partial {
        background: #E8F0FE;
        color: #0B5ED7;
    }
    
    .invoice-card .invoice-header .invoice-status.paid {
        background: #D1FAE5;
        color: #059669;
    }
    
    .invoice-card .invoice-header .invoice-status.cancelled {
        background: #FEE2E2;
        color: #DC2626;
    }
    
    [data-theme="dark"] .invoice-card .invoice-header .invoice-status.pending {
        background: #3D2E0A;
        color: #FBBF24;
    }
    
    [data-theme="dark"] .invoice-card .invoice-header .invoice-status.partial {
        background: #1E3A5F;
        color: #6EA8FE;
    }
    
    [data-theme="dark"] .invoice-card .invoice-header .invoice-status.paid {
        background: #1A3A2A;
        color: #34D399;
    }
    
    [data-theme="dark"] .invoice-card .invoice-header .invoice-status.cancelled {
        background: #3A1A1A;
        color: #F87171;
    }
    
    .invoice-card .invoice-body {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr 1fr;
        gap: 12px;
        margin-bottom: 10px;
    }
    
    .invoice-card .invoice-body .info-item .label {
        font-size: 0.6rem;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .invoice-card .invoice-body .info-item .value {
        font-size: 0.9rem;
        font-weight: 500;
        color: var(--text-primary);
    }
    
    .invoice-card .invoice-amounts {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
        padding: 10px 14px;
        background: var(--bg-body);
        border-radius: 8px;
        margin-bottom: 10px;
    }
    
    .invoice-card .invoice-amounts .amount-item {
        text-align: center;
    }
    
    .invoice-card .invoice-amounts .amount-item .amount-label {
        font-size: 0.6rem;
        color: var(--text-secondary);
    }
    
    .invoice-card .invoice-amounts .amount-item .amount-value {
        font-size: 1rem;
        font-weight: 700;
    }
    
    .invoice-card .invoice-amounts .amount-item .amount-value.total {
        color: var(--primary);
    }
    
    .invoice-card .invoice-amounts .amount-item .amount-value.paid {
        color: #059669;
    }
    
    .invoice-card .invoice-amounts .amount-item .amount-value.balance {
        color: #DC2626;
    }
    
    .invoice-card .invoice-amounts .amount-item .amount-value.payments {
        color: #7C3AED;
    }
    
    .invoice-card .invoice-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }
    
    .btn-action {
        padding: 5px 14px;
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
    
    .btn-action:hover {
        transform: scale(1.05);
    }
    
    .btn-view {
        background: var(--primary);
        color: white;
    }
    .btn-view:hover {
        background: var(--primary-dark);
    }
    
    .btn-print {
        background: #64748B;
        color: white;
    }
    .btn-print:hover {
        background: #475569;
    }
    
    .btn-pdf {
        background: #DC2626;
        color: white;
    }
    .btn-pdf:hover {
        background: #B91C1C;
    }
    
    .btn-pay {
        background: #059669;
        color: white;
    }
    .btn-pay:hover {
        background: #047857;
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
    
    .empty-state .sub {
        font-size: 0.8rem;
        margin-top: 4px;
    }
    
    .summary-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
        gap: 12px;
        margin-bottom: 16px;
    }
    
    .summary-stats .stat-item {
        background: var(--bg-card);
        border-radius: 10px;
        padding: 10px 14px;
        border: 2px solid var(--border-color);
        text-align: center;
    }
    
    .summary-stats .stat-item .stat-number {
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--primary);
    }
    
    .summary-stats .stat-item .stat-label {
        font-size: 0.6rem;
        color: var(--text-secondary);
    }
    
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .invoice-card .invoice-body {
            grid-template-columns: 1fr 1fr;
        }
        .invoice-card .invoice-amounts {
            grid-template-columns: 1fr 1fr;
        }
        .filter-section {
            flex-direction: column;
            align-items: stretch;
        }
        .summary-stats {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr 1fr;
        }
        .invoice-card .invoice-body {
            grid-template-columns: 1fr;
        }
        .invoice-card .invoice-amounts {
            grid-template-columns: 1fr;
        }
        .btn-action {
            font-size: 0.6rem;
            padding: 3px 8px;
        }
        .summary-stats {
            grid-template-columns: 1fr 1fr;
        }
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
            <input type="text" id="searchInput" placeholder="Search by invoice, patient..." 
                   value="<?= htmlspecialchars($search) ?>">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <span class="branch-badge">
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

    <!-- Page Header -->
    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-5">
        <div>
            <h1 class="page-title">
                <i class="fas fa-file-invoice mr-2" style="color: var(--primary);"></i> Invoice History
            </h1>
            <p class="page-subtitle">
                View all invoices
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-file-invoice mr-1"></i> <?= $total_invoices ?> invoices
                </span>
            </p>
        </div>
        <div>
            <button onclick="window.print()" class="btn btn-outline btn-sm">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="dashboard.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-grid animate-fade-in-up">
        
        <a href="pending_bills.php" class="stat-card orange">
            <div>
                <p class="stat-label">Pending Bills</p>
                <p class="stat-number"><?= $pending_bills ?></p>
                <span class="stat-trend"><i class="fas fa-clock"></i> Awaiting</span>
            </div>
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
        </a>
        
        <a href="partial_payments.php" class="stat-card blue">
            <div>
                <p class="stat-label">Partial Payments</p>
                <p class="stat-number"><?= $partial_payments ?></p>
                <span class="stat-trend"><i class="fas fa-hand-holding-usd"></i> Partially paid</span>
            </div>
            <div class="stat-icon"><i class="fas fa-hand-holding-usd"></i></div>
        </a>
        
        <a href="paid_bills.php" class="stat-card green">
            <div>
                <p class="stat-label">Paid Bills Today</p>
                <p class="stat-number"><?= $paid_today ?></p>
                <span class="stat-trend"><i class="fas fa-check-circle"></i> Completed</span>
            </div>
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        </a>
        
        <a href="cancelled_bills.php" class="stat-card red">
            <div>
                <p class="stat-label">Cancelled Bills</p>
                <p class="stat-number"><?= $status_counts['cancelled'] ?? 0 ?></p>
                <span class="stat-trend"><i class="fas fa-times-circle"></i> Voided</span>
            </div>
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
        </a>
        
    </div>

    <!-- ================================================================ -->
    <!-- SUMMARY STATS -->
    <!-- ================================================================ -->
    <div class="summary-stats animate-fade-in-up">
        <div class="stat-item">
            <p class="stat-number"><?= $total_invoices ?></p>
            <p class="stat-label">Total Invoices</p>
        </div>
        <div class="stat-item">
            <p class="stat-number">TSh <?= number_format($total_amount) ?></p>
            <p class="stat-label">Total Amount</p>
        </div>
        <div class="stat-item">
            <p class="stat-number">TSh <?= number_format($total_paid) ?></p>
            <p class="stat-label">Total Paid</p>
        </div>
        <div class="stat-item">
            <p class="stat-number">TSh <?= number_format($total_balance) ?></p>
            <p class="stat-label">Total Balance</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FILTER SECTION -->
    <!-- ================================================================ -->
    <div class="filter-section animate-fade-in-up">
        <form method="GET" action="" class="flex flex-wrap items-center gap-3 w-full">
            <input type="text" name="search" class="form-control" placeholder="Search by invoice, patient..." 
                   value="<?= htmlspecialchars($search) ?>" style="flex:1; min-width:120px;">
            
            <input type="date" name="date_from" class="form-control" value="<?= $date_from ?>" style="width:140px;">
            <span class="text-sm text-gray-400">to</span>
            <input type="date" name="date_to" class="form-control" value="<?= $date_to ?>" style="width:140px;">
            
            <select name="status" class="form-control" style="min-width:120px;">
                <option value="">All Status</option>
                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="partial" <?= $status_filter === 'partial' ? 'selected' : '' ?>>Partial</option>
                <option value="paid" <?= $status_filter === 'paid' ? 'selected' : '' ?>>Paid</option>
                <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
            
            <button type="submit" class="btn-filter">
                <i class="fas fa-search mr-1"></i> Apply
            </button>
            
            <a href="invoice_history.php" class="btn-clear">
                <i class="fas fa-times mr-1"></i> Clear
            </a>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- INVOICES LIST -->
    <!-- ================================================================ -->
    <div class="animate-fade-in-up">
        <?php if (count($invoices) > 0): ?>
            <?php foreach ($invoices as $invoice): ?>
                <div class="invoice-card">
                    <div class="invoice-header">
                        <div class="invoice-number">
                            <?= htmlspecialchars($invoice['bill_number']) ?>
                            <span class="text-xs text-gray-400 ml-2">
                                <?= $invoice['payment_count'] ?> payment(s)
                            </span>
                        </div>
                        <div>
                            <span class="invoice-status <?= $invoice['status'] ?>">
                                <i class="fas fa-circle text-[5px] mr-1"></i>
                                <?= ucfirst($invoice['status'] ?? 'Pending') ?>
                            </span>
                            <span class="text-xs text-gray-400 ml-2">
                                <?= date('M d, Y', strtotime($invoice['created_at'])) ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="invoice-body">
                        <div class="info-item">
                            <div class="label">Patient</div>
                            <div class="value"><?= htmlspecialchars($invoice['patient_name'] ?? 'Unknown') ?></div>
                            <div class="text-xs text-gray-400">ID: <?= htmlspecialchars($invoice['patient_id'] ?? 'N/A') ?></div>
                        </div>
                        <div class="info-item">
                            <div class="label">Visit</div>
                            <div class="value"><?= htmlspecialchars($invoice['visit_number'] ?? 'N/A') ?></div>
                        </div>
                        <div class="info-item">
                            <div class="label">Created By</div>
                            <div class="value"><?= htmlspecialchars($invoice['created_by_name'] ?? 'N/A') ?></div>
                        </div>
                        <div class="info-item">
                            <div class="label">Updated</div>
                            <div class="value"><?= date('M d, Y', strtotime($invoice['updated_at'])) ?></div>
                        </div>
                    </div>
                    
                    <div class="invoice-amounts">
                        <div class="amount-item">
                            <div class="amount-label">Total</div>
                            <div class="amount-value total">TSh <?= number_format($invoice['grand_total']) ?></div>
                        </div>
                        <div class="amount-item">
                            <div class="amount-label">Paid</div>
                            <div class="amount-value paid">TSh <?= number_format($invoice['amount_paid']) ?></div>
                        </div>
                        <div class="amount-item">
                            <div class="amount-label">Balance</div>
                            <div class="amount-value balance">TSh <?= number_format($invoice['balance']) ?></div>
                        </div>
                        <div class="amount-item">
                            <div class="amount-label">Payments</div>
                            <div class="amount-value payments"><?= $invoice['payment_count'] ?></div>
                        </div>
                    </div>
                    
                    <div class="invoice-actions">
                        <a href="view_bill.php?id=<?= $invoice['bill_number'] ?>" class="btn-action btn-view">
                            <i class="fas fa-eye"></i> View
                        </a>
                        
                        <?php if ($invoice['status'] === 'pending' || $invoice['status'] === 'partial'): ?>
                            <a href="receive_payment.php?bill_id=<?= $invoice['id'] ?>" class="btn-action btn-pay">
                                <i class="fas fa-hand-holding-usd"></i> Pay
                            </a>
                        <?php endif; ?>
                        
                        <a href="print_invoice.php?bill_number=<?= $invoice['bill_number'] ?>" class="btn-action btn-print" target="_blank">
                            <i class="fas fa-print"></i> Print
                        </a>
                        <a href="#" class="btn-action btn-pdf" onclick="downloadPDF('<?= $invoice['bill_number'] ?>'); return false;">
                            <i class="fas fa-file-pdf"></i> PDF
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-file-invoice"></i>
                <p>No invoices found</p>
                <p class="sub">
                    <?php if (!empty($search) || !empty($date_from) || !empty($status_filter)): ?>
                        Try adjusting your filters.
                    <?php else: ?>
                        No invoices have been created yet.
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer mt-5">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Invoice History
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

    // ================================================================
    // DOWNLOAD PDF
    // ================================================================
    function downloadPDF(billNumber) {
        showToast('Downloading PDF', 'Preparing PDF...', 'info');
        window.location.href = 'download_pdf.php?bill_number=' + billNumber;
        setTimeout(function() {
            showToast('Success', 'PDF downloaded successfully!', 'success');
        }, 3000);
    }

    // ================================================================
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        var url = 'invoice_history.php';
        var params = [];
        if (query) params.push('search=' + encodeURIComponent(query));
        
        var date_from = document.querySelector('input[name="date_from"]')?.value || '';
        var date_to = document.querySelector('input[name="date_to"]')?.value || '';
        var status = document.querySelector('select[name="status"]')?.value || '';
        
        if (date_from) params.push('date_from=' + date_from);
        if (date_to) params.push('date_to=' + date_to);
        if (status) params.push('status=' + status);
        
        if (params.length > 0) {
            window.location.href = url + '?' + params.join('&');
        } else {
            window.location.href = url;
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
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            searchInput?.focus();
            searchInput?.select();
        }
        if (e.key === 'Escape' && document.activeElement === searchInput) {
            searchInput.value = '';
            performSearch();
        }
    });

    console.log('%c💰 Braick - Invoice History', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Total Invoices: <?= $total_invoices ?> | Total Amount: TSh <?= number_format($total_amount) ?>', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>