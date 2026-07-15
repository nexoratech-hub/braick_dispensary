<?php
// ================================================================
// FILE: frontend/pages/cashier/receipt_history.php
// CASHIER - RECEIPT HISTORY
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
$payment_method = isset($_GET['payment_method']) ? trim($_GET['payment_method']) : '';
$cashier_filter = isset($_GET['cashier']) ? (int)$_GET['cashier'] : 0;

// ================================================================
// GET RECEIPTS (Payments with receipt numbers)
// ================================================================
$query = "
    SELECT 
        p.*,
        b.bill_number,
        pat.full_name as patient_name,
        pat.patient_id,
        u.full_name as cashier_name,
        b.grand_total as bill_total
    FROM payments p
    JOIN bills b ON p.bill_id = b.id
    JOIN patients pat ON p.patient_id = pat.id
    LEFT JOIN users u ON p.received_by = u.id
    WHERE p.branch_id = ? AND p.receipt_number IS NOT NULL
";

if (!empty($search)) {
    $query .= " AND (p.receipt_number LIKE ? OR pat.full_name LIKE ? OR pat.patient_id LIKE ? OR b.bill_number LIKE ?)";
}

if (!empty($date_from) && !empty($date_to)) {
    $query .= " AND DATE(p.received_at) BETWEEN ? AND ?";
}

if (!empty($payment_method)) {
    $query .= " AND p.payment_method = ?";
}

if ($cashier_filter > 0) {
    $query .= " AND p.received_by = ?";
}

$query .= " ORDER BY p.received_at DESC";

$stmt = $db->prepare($query);

$params = [$user_branch_id];

if (!empty($search)) {
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($date_from) && !empty($date_to)) {
    $params[] = $date_from;
    $params[] = $date_to;
}

if (!empty($payment_method)) {
    $params[] = $payment_method;
}

if ($cashier_filter > 0) {
    $params[] = $cashier_filter;
}

$stmt->execute($params);
$receipts = $stmt->fetchAll();

// ================================================================
// GET STATISTICS
// ================================================================
$total_receipts = count($receipts);
$total_amount = 0;
foreach ($receipts as $receipt) {
    $total_amount += $receipt['amount'];
}

// ================================================================
// GET CASHIERS FOR FILTER
// ================================================================
$cashiers = [];
$stmt = $db->prepare("
    SELECT DISTINCT u.id, u.full_name 
    FROM users u
    JOIN payments p ON p.received_by = u.id
    WHERE p.branch_id = ?
    ORDER BY u.full_name
");
$stmt->execute([$user_branch_id]);
$cashiers = $stmt->fetchAll();

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
       RECEIPT HISTORY STYLES
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
    
    /* Receipt Card */
    .receipt-card {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 16px 20px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        margin-bottom: 16px;
    }
    
    .receipt-card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.08);
    }
    
    .receipt-card .receipt-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 8px;
        padding-bottom: 8px;
        border-bottom: 2px solid var(--border-color);
    }
    
    .receipt-card .receipt-header .receipt-number {
        font-weight: 700;
        font-size: 1rem;
        color: var(--text-primary);
        font-family: monospace;
    }
    
    .receipt-card .receipt-header .receipt-amount {
        font-size: 1.2rem;
        font-weight: 700;
        color: #059669;
    }
    
    .receipt-card .receipt-body {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr 1fr;
        gap: 12px;
        margin-bottom: 10px;
    }
    
    .receipt-card .receipt-body .info-item .label {
        font-size: 0.6rem;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .receipt-card .receipt-body .info-item .value {
        font-size: 0.9rem;
        font-weight: 500;
        color: var(--text-primary);
    }
    
    .receipt-card .receipt-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        padding-top: 10px;
        border-top: 2px solid var(--border-color);
    }
    
    .receipt-card .receipt-footer .payment-method {
        font-size: 0.7rem;
        font-weight: 600;
        padding: 2px 12px;
        border-radius: 20px;
    }
    
    .receipt-card .receipt-footer .payment-method.cash {
        background: #E8F0FE;
        color: #0B5ED7;
    }
    
    .receipt-card .receipt-footer .payment-method.m-pesa {
        background: #D1FAE5;
        color: #059669;
    }
    
    .receipt-card .receipt-footer .payment-method.card {
        background: #F1F5F9;
        color: #475569;
    }
    
    .receipt-card .receipt-footer .payment-method.airtel_money {
        background: #FEF3C7;
        color: #D97706;
    }
    
    .receipt-card .receipt-footer .payment-method.tigo_pesa {
        background: #F3E8FF;
        color: #7C3AED;
    }
    
    .receipt-card .receipt-footer .payment-method.halopesa {
        background: #FCE4EC;
        color: #DB2777;
    }
    
    .receipt-card .receipt-footer .payment-method.insurance {
        background: #E0F2FE;
        color: #0284C7;
    }
    
    .receipt-card .receipt-footer .payment-method.other {
        background: #F1F5F9;
        color: #64748B;
    }
    
    [data-theme="dark"] .receipt-card .receipt-footer .payment-method.cash {
        background: #1E3A5F;
        color: #6EA8FE;
    }
    
    [data-theme="dark"] .receipt-card .receipt-footer .payment-method.m-pesa {
        background: #1A3A2A;
        color: #34D399;
    }
    
    [data-theme="dark"] .receipt-card .receipt-footer .payment-method.card {
        background: #1E293B;
        color: #94A3B8;
    }
    
    [data-theme="dark"] .receipt-card .receipt-footer .payment-method.airtel_money {
        background: #3D2E0A;
        color: #FBBF24;
    }
    
    [data-theme="dark"] .receipt-card .receipt-footer .payment-method.tigo_pesa {
        background: #2A1A3A;
        color: #9B4DCA;
    }
    
    [data-theme="dark"] .receipt-card .receipt-footer .payment-method.halopesa {
        background: #3A1A2A;
        color: #F472B6;
    }
    
    [data-theme="dark"] .receipt-card .receipt-footer .payment-method.insurance {
        background: #1A2A3A;
        color: #38BDF8;
    }
    
    [data-theme="dark"] .receipt-card .receipt-footer .payment-method.other {
        background: #1E293B;
        color: #94A3B8;
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
    
    /* Summary Stats */
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
        .receipt-card .receipt-body {
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
        .receipt-card .receipt-body {
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
            <input type="text" id="searchInput" placeholder="Search by receipt, patient..." 
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
                <i class="fas fa-receipt mr-2" style="color: var(--primary);"></i> Receipt History
            </h1>
            <p class="page-subtitle">
                View all issued receipts
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-receipt mr-1"></i> <?= $total_receipts ?> receipts
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
        
        <a href="receipt_history.php" class="stat-card purple">
            <div>
                <p class="stat-label">Receipts Issued</p>
                <p class="stat-number"><?= $total_receipts ?></p>
                <span class="stat-trend"><i class="fas fa-receipt"></i> All time</span>
            </div>
            <div class="stat-icon"><i class="fas fa-receipt"></i></div>
        </a>
        
    </div>

    <!-- ================================================================ -->
    <!-- SUMMARY STATS -->
    <!-- ================================================================ -->
    <div class="summary-stats animate-fade-in-up">
        <div class="stat-item">
            <p class="stat-number"><?= $total_receipts ?></p>
            <p class="stat-label">Total Receipts</p>
        </div>
        <div class="stat-item">
            <p class="stat-number">TSh <?= number_format($total_amount) ?></p>
            <p class="stat-label">Total Amount</p>
        </div>
        <div class="stat-item">
            <p class="stat-number"><?= count(array_unique(array_column($receipts, 'patient_id'))) ?></p>
            <p class="stat-label">Patients</p>
        </div>
        <div class="stat-item">
            <p class="stat-number"><?= count(array_unique(array_column($receipts, 'payment_method'))) ?></p>
            <p class="stat-label">Payment Methods</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FILTER SECTION -->
    <!-- ================================================================ -->
    <div class="filter-section animate-fade-in-up">
        <form method="GET" action="" class="flex flex-wrap items-center gap-3 w-full">
            <input type="text" name="search" class="form-control" placeholder="Search by receipt, patient..." 
                   value="<?= htmlspecialchars($search) ?>" style="flex:1; min-width:120px;">
            
            <input type="date" name="date_from" class="form-control" value="<?= $date_from ?>" style="width:140px;">
            <span class="text-sm text-gray-400">to</span>
            <input type="date" name="date_to" class="form-control" value="<?= $date_to ?>" style="width:140px;">
            
            <select name="payment_method" class="form-control" style="min-width:120px;">
                <option value="">All Methods</option>
                <option value="cash" <?= $payment_method === 'cash' ? 'selected' : '' ?>>Cash</option>
                <option value="m-pesa" <?= $payment_method === 'm-pesa' ? 'selected' : '' ?>>M-Pesa</option>
                <option value="airtel_money" <?= $payment_method === 'airtel_money' ? 'selected' : '' ?>>Airtel Money</option>
                <option value="tigo_pesa" <?= $payment_method === 'tigo_pesa' ? 'selected' : '' ?>>Tigo Pesa</option>
                <option value="halopesa" <?= $payment_method === 'halopesa' ? 'selected' : '' ?>>Halopesa</option>
                <option value="bank" <?= $payment_method === 'bank' ? 'selected' : '' ?>>Bank</option>
                <option value="card" <?= $payment_method === 'card' ? 'selected' : '' ?>>Card</option>
                <option value="insurance" <?= $payment_method === 'insurance' ? 'selected' : '' ?>>Insurance</option>
                <option value="other" <?= $payment_method === 'other' ? 'selected' : '' ?>>Other</option>
            </select>
            
            <select name="cashier" class="form-control" style="min-width:130px;">
                <option value="">All Cashiers</option>
                <?php foreach ($cashiers as $cashier): ?>
                    <option value="<?= $cashier['id'] ?>" <?= $cashier_filter == $cashier['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cashier['full_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <button type="submit" class="btn-filter">
                <i class="fas fa-search mr-1"></i> Apply
            </button>
            
            <a href="receipt_history.php" class="btn-clear">
                <i class="fas fa-times mr-1"></i> Clear
            </a>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- RECEIPTS LIST -->
    <!-- ================================================================ -->
    <div class="animate-fade-in-up">
        <?php if (count($receipts) > 0): ?>
            <?php foreach ($receipts as $receipt): ?>
                <div class="receipt-card">
                    <div class="receipt-header">
                        <div class="receipt-number">
                            <i class="fas fa-receipt mr-2 text-blue-600"></i>
                            <?= htmlspecialchars($receipt['receipt_number']) ?>
                            <span class="text-xs text-gray-400 ml-2">
                                Bill: <?= htmlspecialchars($receipt['bill_number']) ?>
                            </span>
                        </div>
                        <div class="receipt-amount">
                            TSh <?= number_format($receipt['amount']) ?>
                        </div>
                    </div>
                    
                    <div class="receipt-body">
                        <div class="info-item">
                            <div class="label">Patient</div>
                            <div class="value"><?= htmlspecialchars($receipt['patient_name'] ?? 'Unknown') ?></div>
                            <div class="text-xs text-gray-400">ID: <?= htmlspecialchars($receipt['patient_id'] ?? 'N/A') ?></div>
                        </div>
                        <div class="info-item">
                            <div class="label">Issued By</div>
                            <div class="value"><?= htmlspecialchars($receipt['cashier_name'] ?? 'N/A') ?></div>
                        </div>
                        <div class="info-item">
                            <div class="label">Date Issued</div>
                            <div class="value"><?= date('M d, Y', strtotime($receipt['received_at'])) ?></div>
                            <div class="text-xs text-gray-400"><?= date('h:i A', strtotime($receipt['received_at'])) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="label">Reference</div>
                            <div class="value"><?= htmlspecialchars($receipt['reference_number'] ?? 'N/A') ?></div>
                        </div>
                    </div>
                    
                    <div class="receipt-footer">
                        <div>
                            <span class="payment-method <?= $receipt['payment_method'] ?>">
                                <i class="fas fa-circle text-[5px] mr-1"></i>
                                <?= ucfirst(str_replace('_', ' ', $receipt['payment_method'] ?? 'Cash')) ?>
                            </span>
                            <?php if (!empty($receipt['notes'])): ?>
                                <span class="text-xs text-gray-400 ml-2">
                                    <i class="fas fa-sticky-note mr-1"></i>
                                    <?= htmlspecialchars(substr($receipt['notes'], 0, 50)) ?><?= strlen($receipt['notes'] ?? '') > 50 ? '...' : '' ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="flex gap-2">
                            <a href="view_bill.php?id=<?= $receipt['bill_number'] ?>" class="btn-action btn-view">
                                <i class="fas fa-eye"></i> View Bill
                            </a>
                            <a href="print_receipt.php?payment_id=<?= $receipt['id'] ?>" class="btn-action btn-print" target="_blank">
                                <i class="fas fa-print"></i> Print
                            </a>
                            <a href="#" class="btn-action btn-pdf" onclick="downloadPDF('<?= $receipt['receipt_number'] ?>'); return false;">
                                <i class="fas fa-file-pdf"></i> PDF
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-receipt"></i>
                <p>No receipts found</p>
                <p class="sub">
                    <?php if (!empty($search) || !empty($date_from) || !empty($payment_method) || $cashier_filter > 0): ?>
                        Try adjusting your filters.
                    <?php else: ?>
                        No receipts have been issued yet.
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
            Receipt History
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
    function downloadPDF(receiptNumber) {
        showToast('Downloading PDF', 'Preparing PDF...', 'info');
        window.location.href = 'download_pdf.php?receipt_number=' + receiptNumber;
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
        var url = 'receipt_history.php';
        var params = [];
        if (query) params.push('search=' + encodeURIComponent(query));
        
        var date_from = document.querySelector('input[name="date_from"]')?.value || '';
        var date_to = document.querySelector('input[name="date_to"]')?.value || '';
        var payment_method = document.querySelector('select[name="payment_method"]')?.value || '';
        var cashier = document.querySelector('select[name="cashier"]')?.value || '';
        
        if (date_from) params.push('date_from=' + date_from);
        if (date_to) params.push('date_to=' + date_to);
        if (payment_method) params.push('payment_method=' + payment_method);
        if (cashier) params.push('cashier=' + cashier);
        
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
            if (e.key === 'Enter') performSearch());
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

    console.log('%c💰 Braick - Receipt History', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Total Receipts: <?= $total_receipts ?> | Total Amount: TSh <?= number_format($total_amount) ?>', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>