<?php
// ================================================================
// FILE: frontend/pages/cashier/partial_payments.php
// CASHIER - PARTIAL PAYMENTS
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

// ================================================================
// GET PARTIAL PAYMENTS
// ================================================================
$query = "
    SELECT 
        b.*,
        p.full_name as patient_name,
        p.patient_id,
        v.visit_number,
        u.full_name as cashier_name,
        (SELECT MAX(received_at) FROM payments WHERE bill_id = b.id) as last_payment_date,
        (SELECT COUNT(*) FROM payments WHERE bill_id = b.id) as payment_count
    FROM bills b
    JOIN patients p ON b.patient_id = p.id
    LEFT JOIN visits v ON b.visit_id = v.id
    LEFT JOIN users u ON b.created_by = u.id
    WHERE b.branch_id = ? AND b.status = 'partial'
";

if (!empty($search)) {
    $query .= " AND (b.bill_number LIKE ? OR p.full_name LIKE ? OR p.patient_id LIKE ?)";
}

if (!empty($date_from) && !empty($date_to)) {
    $query .= " AND DATE(b.updated_at) BETWEEN ? AND ?";
}

$query .= " ORDER BY b.balance DESC, b.updated_at DESC";

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

$stmt->execute($params);
$partial_bills = $stmt->fetchAll();

// ================================================================
// GET STATISTICS
// ================================================================
$total_partial = count($partial_bills);
$total_balance = 0;
foreach ($partial_bills as $bill) {
    $total_balance += $bill['balance'];
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
       PARTIAL PAYMENTS STYLES
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
    
    .stat-card.blue { background: #0B5ED7; }
    .stat-card.blue-dark { background: #0A3D8A; }
    .stat-card.green { background: #059669; }
    .stat-card.orange { background: #D97706; }
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
    
    /* Bill Card */
    .bill-card {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 16px 20px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        margin-bottom: 16px;
    }
    
    .bill-card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.08);
    }
    
    .bill-card .bill-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 8px;
        padding-bottom: 8px;
        border-bottom: 2px solid var(--border-color);
    }
    
    .bill-card .bill-header .bill-number {
        font-weight: 700;
        font-size: 1rem;
        color: var(--text-primary);
        font-family: monospace;
    }
    
    .bill-card .bill-header .bill-status {
        font-size: 0.7rem;
        font-weight: 600;
        padding: 4px 12px;
        border-radius: 20px;
        background: #E8F0FE;
        color: #0B5ED7;
    }
    
    [data-theme="dark"] .bill-card .bill-header .bill-status {
        background: #1E3A5F;
        color: #6EA8FE;
    }
    
    .bill-card .bill-body {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr 1fr;
        gap: 12px;
        margin-bottom: 10px;
    }
    
    .bill-card .bill-body .info-item .label {
        font-size: 0.6rem;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .bill-card .bill-body .info-item .value {
        font-size: 0.9rem;
        font-weight: 500;
        color: var(--text-primary);
    }
    
    .bill-card .bill-amounts {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        padding: 10px 14px;
        background: var(--bg-body);
        border-radius: 8px;
        margin-bottom: 10px;
    }
    
    .bill-card .bill-amounts .amount-item {
        text-align: center;
    }
    
    .bill-card .bill-amounts .amount-item .amount-label {
        font-size: 0.6rem;
        color: var(--text-secondary);
    }
    
    .bill-card .bill-amounts .amount-item .amount-value {
        font-size: 1rem;
        font-weight: 700;
    }
    
    .bill-card .bill-amounts .amount-item .amount-value.total {
        color: var(--primary);
    }
    
    .bill-card .bill-amounts .amount-item .amount-value.paid {
        color: #059669;
    }
    
    .bill-card .bill-amounts .amount-item .amount-value.balance {
        color: #DC2626;
    }
    
    .bill-card .bill-actions {
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
    
    .btn-pay {
        background: #059669;
        color: white;
    }
    .btn-pay:hover {
        background: #047857;
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
    
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .bill-card .bill-body {
            grid-template-columns: 1fr 1fr;
        }
        .bill-card .bill-amounts {
            grid-template-columns: 1fr;
        }
        .filter-section {
            flex-direction: column;
            align-items: stretch;
        }
        .bill-card .bill-actions {
            justify-content: flex-start;
        }
    }
    
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr 1fr;
        }
        .bill-card .bill-body {
            grid-template-columns: 1fr;
        }
        .btn-action {
            font-size: 0.6rem;
            padding: 3px 8px;
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
            <input type="text" id="searchInput" placeholder="Search bills, patients..." 
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
                <i class="fas fa-hand-holding-usd mr-2" style="color: #0B5ED7;"></i> Partial Payments
            </h1>
            <p class="page-subtitle">
                View and manage bills with partial payments
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-file-invoice mr-1"></i> <?= $total_partial ?> partial bills
                </span>
            </p>
        </div>
        <div>
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
        
        <a href="pending_bills.php" class="stat-card purple">
            <div>
                <p class="stat-label">Patients Waiting</p>
                <p class="stat-number"><?= $patients_waiting ?></p>
                <span class="stat-trend"><i class="fas fa-users"></i> Need payment</span>
            </div>
            <div class="stat-icon"><i class="fas fa-users"></i></div>
        </a>
        
    </div>

    <!-- ================================================================ -->
    <!-- FILTER SECTION -->
    <!-- ================================================================ -->
    <div class="filter-section animate-fade-in-up">
        <form method="GET" action="" class="flex flex-wrap items-center gap-3 w-full">
            <input type="text" name="search" class="form-control" placeholder="Search by bill, patient..." 
                   value="<?= htmlspecialchars($search) ?>" style="flex:1; min-width:150px;">
            
            <input type="date" name="date_from" class="form-control" value="<?= $date_from ?>" style="width:140px;">
            <span class="text-sm text-gray-400">to</span>
            <input type="date" name="date_to" class="form-control" value="<?= $date_to ?>" style="width:140px;">
            
            <button type="submit" class="btn-filter">
                <i class="fas fa-search mr-1"></i> Apply
            </button>
            
            <a href="partial_payments.php" class="btn-clear">
                <i class="fas fa-times mr-1"></i> Clear
            </a>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- PARTIAL BILLS LIST -->
    <!-- ================================================================ -->
    <div class="animate-fade-in-up">
        <?php if (count($partial_bills) > 0): ?>
            <?php foreach ($partial_bills as $bill): ?>
                <div class="bill-card">
                    <div class="bill-header">
                        <div class="bill-number">
                            <?= htmlspecialchars($bill['bill_number']) ?>
                            <span class="bill-status">
                                <i class="fas fa-hand-holding-usd mr-1"></i> Partial Payment
                            </span>
                        </div>
                        <div class="text-sm text-gray-400">
                            <i class="fas fa-calendar-alt mr-1"></i>
                            <?= date('M d, Y h:i A', strtotime($bill['updated_at'])) ?>
                        </div>
                    </div>
                    
                    <div class="bill-body">
                        <div class="info-item">
                            <div class="label">Patient</div>
                            <div class="value"><?= htmlspecialchars($bill['patient_name'] ?? 'Unknown') ?></div>
                            <div class="text-xs text-gray-400">ID: <?= htmlspecialchars($bill['patient_id'] ?? 'N/A') ?></div>
                        </div>
                        <div class="info-item">
                            <div class="label">Visit</div>
                            <div class="value"><?= htmlspecialchars($bill['visit_number'] ?? 'N/A') ?></div>
                        </div>
                        <div class="info-item">
                            <div class="label">Payments</div>
                            <div class="value"><?= $bill['payment_count'] ?? 0 ?></div>
                            <div class="text-xs text-gray-400">Last: <?= isset($bill['last_payment_date']) ? date('M d, Y', strtotime($bill['last_payment_date'])) : 'N/A' ?></div>
                        </div>
                        <div class="info-item">
                            <div class="label">Cashier</div>
                            <div class="value"><?= htmlspecialchars($bill['cashier_name'] ?? 'N/A') ?></div>
                        </div>
                    </div>
                    
                    <div class="bill-amounts">
                        <div class="amount-item">
                            <div class="amount-label">Grand Total</div>
                            <div class="amount-value total">TSh <?= number_format($bill['grand_total']) ?></div>
                        </div>
                        <div class="amount-item">
                            <div class="amount-label">Amount Paid</div>
                            <div class="amount-value paid">TSh <?= number_format($bill['amount_paid']) ?></div>
                        </div>
                        <div class="amount-item">
                            <div class="amount-label">Remaining Balance</div>
                            <div class="amount-value balance">TSh <?= number_format($bill['balance']) ?></div>
                        </div>
                    </div>
                    
                    <div class="bill-actions">
                        <a href="view_bill.php?id=<?= $bill['bill_number'] ?>" class="btn-action btn-view">
                            <i class="fas fa-eye"></i> View Bill
                        </a>
                        <a href="receive_payment.php?bill_id=<?= $bill['id'] ?>" class="btn-action btn-pay">
                            <i class="fas fa-hand-holding-usd"></i> Receive Payment
                        </a>
                        <a href="#" class="btn-action btn-print" onclick="printInvoice('<?= $bill['bill_number'] ?>'); return false;">
                            <i class="fas fa-print"></i> Print Invoice
                        </a>
                        <a href="#" class="btn-action btn-pdf" onclick="downloadPDF('<?= $bill['bill_number'] ?>'); return false;">
                            <i class="fas fa-file-pdf"></i> Download PDF
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <!-- Summary -->
            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 text-center">
                <p class="text-sm text-gray-500">
                    <strong><?= $total_partial ?></strong> partial bills with total remaining balance of 
                    <strong class="text-red-600">TSh <?= number_format($total_balance) ?></strong>
                </p>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-hand-holding-usd"></i>
                <p>No partial payments found</p>
                <p class="sub">
                    <?php if (!empty($search) || !empty($date_from)): ?>
                        Try adjusting your filters.
                    <?php else: ?>
                        All bills are either fully paid or pending. Great job! 🎉
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
            Partial Payments
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
    // PRINT INVOICE
    // ================================================================
    function printInvoice(billNumber) {
        window.open('print_invoice.php?bill_number=' + billNumber, '_blank', 'width=800,height=600');
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
        var url = 'partial_payments.php';
        var params = [];
        if (query) params.push('search=' + encodeURIComponent(query));
        if (document.querySelector('input[name="date_from"]')) {
            var date_from = document.querySelector('input[name="date_from"]').value;
            var date_to = document.querySelector('input[name="date_to"]').value;
            if (date_from) params.push('date_from=' + date_from);
            if (date_to) params.push('date_to=' + date_to);
        }
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

    console.log('%c💰 Braick - Partial Payments', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Partial Bills: <?= $total_partial ?> | Total Balance: TSh <?= number_format($total_balance) ?>', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>