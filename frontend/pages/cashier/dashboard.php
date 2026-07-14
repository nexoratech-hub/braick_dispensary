<?php
// ================================================================
// FILE: frontend/pages/cashier/dashboard.php
// CASHIER DASHBOARD - FIXED (REMOVED DEPARTMENT QUERY)
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// INCLUDE CONFIG
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

// ================================================================
// SESSION - Default to reception.rose
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
// GET STATISTICS
// ================================================================
$today = date('Y-m-d');

// 1. Today's Revenue
$stmt = $db->prepare("
    SELECT COALESCE(SUM(amount), 0) as revenue 
    FROM payments 
    WHERE branch_id = ? AND DATE(received_at) = ?
");
$stmt->execute([$user_branch_id, $today]);
$today_revenue = $stmt->fetch()['revenue'] ?? 0;

// 2. Pending Bills
$stmt = $db->prepare("SELECT COUNT(*) as count FROM bills WHERE branch_id = ? AND status = 'pending'");
$stmt->execute([$user_branch_id]);
$pending_bills = $stmt->fetch()['count'] ?? 0;

// 3. Paid Bills Today
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM bills 
    WHERE branch_id = ? AND status = 'paid' AND DATE(updated_at) = ?
");
$stmt->execute([$user_branch_id, $today]);
$paid_today = $stmt->fetch()['count'] ?? 0;

// 4. Partial Payments
$stmt = $db->prepare("SELECT COUNT(*) as count FROM bills WHERE branch_id = ? AND status = 'partial'");
$stmt->execute([$user_branch_id]);
$partial_payments = $stmt->fetch()['count'] ?? 0;

// 5. Patients Waiting for Payment
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT patient_id) as count 
    FROM bills 
    WHERE branch_id = ? AND status IN ('pending', 'partial')
");
$stmt->execute([$user_branch_id]);
$patients_waiting = $stmt->fetch()['count'] ?? 0;

// 6. Today's Transactions
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM payments 
    WHERE branch_id = ? AND DATE(received_at) = ?
");
$stmt->execute([$user_branch_id, $today]);
$today_transactions = $stmt->fetch()['count'] ?? 0;

// 7. Recent Transactions (Last 10)
$stmt = $db->prepare("
    SELECT 
        b.bill_number,
        b.status,
        b.grand_total,
        b.amount_paid,
        b.balance,
        p.full_name as patient_name,
        v.visit_number,
        b.created_at
    FROM bills b
    JOIN patients p ON b.patient_id = p.id
    LEFT JOIN visits v ON b.visit_id = v.id
    WHERE b.branch_id = ?
    ORDER BY b.created_at DESC
    LIMIT 10
");
$stmt->execute([$user_branch_id]);
$recent_transactions = $stmt->fetchAll();

// 8. Daily Revenue Chart (Last 7 days)
$daily_labels = [];
$daily_revenue = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $daily_labels[] = date('D', strtotime($date));
    
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) as revenue 
        FROM payments 
        WHERE branch_id = ? AND DATE(received_at) = ?
    ");
    $stmt->execute([$user_branch_id, $date]);
    $daily_revenue[] = $stmt->fetch()['revenue'] ?? 0;
}

// 9. Monthly Revenue Chart (Last 6 months)
$monthly_labels = [];
$monthly_revenue = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('M', strtotime("-$i months"));
    $monthly_labels[] = $month;
    
    $start = date('Y-m-01', strtotime("-$i months"));
    $end = date('Y-m-t', strtotime("-$i months"));
    
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) as revenue 
        FROM payments 
        WHERE branch_id = ? AND DATE(received_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$user_branch_id, $start, $end]);
    $monthly_revenue[] = $stmt->fetch()['revenue'] ?? 0;
}

// 10. Payment Methods Breakdown
$stmt = $db->prepare("
    SELECT payment_method, COUNT(*) as count, COALESCE(SUM(amount), 0) as total
    FROM payments 
    WHERE branch_id = ? AND DATE(received_at) = ?
    GROUP BY payment_method
    ORDER BY total DESC
");
$stmt->execute([$user_branch_id, $today]);
$payment_methods = $stmt->fetchAll();

// ================================================================
// 11. Department Revenue - REMOVED (columns don't exist)
// ================================================================
// Department revenue chart removed - will be added when database is updated

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
    }
    
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }
    
    .stat-card:active {
        transform: scale(0.98);
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
    
    .stat-card .nav-arrow {
        opacity: 0;
        transition: all 0.3s ease;
        margin-left: 8px;
        font-size: 0.8rem;
    }
    
    .stat-card:hover .nav-arrow {
        opacity: 1;
        transform: translateX(4px);
    }
    
    .stat-card.blue { background: #0B5ED7; }
    .stat-card.blue-dark { background: #0A3D8A; }
    .stat-card.green { background: #059669; }
    .stat-card.orange { background: #D97706; }
    .stat-card.purple { background: #7C3AED; }
    .stat-card.teal { background: #0D9488; }
    
    .recent-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 14px;
        border-bottom: 1px solid var(--border-color);
        transition: background 0.2s ease;
    }
    
    .recent-item:hover {
        background: var(--table-hover);
        border-radius: 8px;
    }
    
    .recent-item:last-child {
        border-bottom: none;
    }
    
    .chart-container {
        height: 200px;
        max-height: 200px;
    }
    
    .chart-container canvas {
        height: 100% !important;
        max-height: 200px !important;
    }
    
    .empty-state {
        text-align: center;
        padding: 30px 20px;
        color: var(--text-secondary);
    }
    
    .empty-state i {
        font-size: 2.5rem;
        color: var(--border-color);
        display: block;
        margin-bottom: 10px;
    }
    
    .method-badge {
        font-size: 0.65rem;
        font-weight: 600;
        padding: 2px 10px;
        border-radius: 12px;
        display: inline-block;
    }
    
    .method-badge.cash { background: #E8F0FE; color: #0B5ED7; }
    .method-badge.m-pesa { background: #D1FAE5; color: #059669; }
    .method-badge.airtel_money { background: #FEF3C7; color: #D97706; }
    .method-badge.tigo_pesa { background: #F3E8FF; color: #7C3AED; }
    .method-badge.halopesa { background: #FCE4EC; color: #DB2777; }
    .method-badge.bank { background: #E0F2FE; color: #0284C7; }
    .method-badge.card { background: #F1F5F9; color: #475569; }
    .method-badge.other { background: #F1F5F9; color: #64748B; }
    
    [data-theme="dark"] .method-badge.cash { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .method-badge.m-pesa { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .method-badge.airtel_money { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .method-badge.tigo_pesa { background: #2A1A3A; color: #9B4DCA; }
    [data-theme="dark"] .method-badge.halopesa { background: #3A1A2A; color: #F472B6; }
    [data-theme="dark"] .method-badge.bank { background: #1A2A3A; color: #38BDF8; }
    [data-theme="dark"] .method-badge.card { background: #1E293B; color: #94A3B8; }
    [data-theme="dark"] .method-badge.other { background: #1E293B; color: #94A3B8; }
    
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .chart-container {
            height: 150px;
        }
    }
    
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
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
            <input type="text" id="searchInput" placeholder="Search bills, patients...">
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
                <i class="fas fa-cash-register mr-2" style="color: var(--primary);"></i> Cashier Dashboard
            </h1>
            <p class="page-subtitle">
                Welcome, <?= htmlspecialchars($user_full_name) ?>!
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
                <span class="ml-2 inline-flex bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs border border-green-200">
                    <i class="fas fa-calendar-day mr-1"></i> <?= date('F d, Y') ?>
                </span>
                <span class="ml-2 text-xs text-gray-400">
                    <i class="fas fa-hand-pointer mr-1"></i> Click cards to navigate
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="pending_bills.php" class="btn btn-warning btn-sm">
                <i class="fas fa-clock"></i> Pending (<?= $pending_bills ?>)
            </a>
            <a href="receive_payment.php" class="btn btn-green btn-sm">
                <i class="fas fa-hand-holding-usd"></i> Receive Payment
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-grid animate-fade-in-up">
        
        <a href="reports.php?tab=revenue" class="stat-card blue">
            <div>
                <p class="stat-label">Today's Revenue</p>
                <p class="stat-number">TSh <?= number_format($today_revenue) ?></p>
                <span class="stat-trend"><i class="fas fa-arrow-up"></i> <?= $today_transactions ?> transactions</span>
            </div>
            <div class="stat-icon">
                <i class="fas fa-money-bill-wave"></i>
                <i class="fas fa-chevron-right nav-arrow"></i>
            </div>
        </a>
        
        <a href="pending_bills.php" class="stat-card orange">
            <div>
                <p class="stat-label">Pending Bills</p>
                <p class="stat-number"><?= $pending_bills ?></p>
                <span class="stat-trend"><i class="fas fa-clock"></i> Awaiting payment</span>
            </div>
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
                <i class="fas fa-chevron-right nav-arrow"></i>
            </div>
        </a>
        
        <a href="paid_bills.php" class="stat-card green">
            <div>
                <p class="stat-label">Paid Bills Today</p>
                <p class="stat-number"><?= $paid_today ?></p>
                <span class="stat-trend"><i class="fas fa-check-circle"></i> Completed</span>
            </div>
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
                <i class="fas fa-chevron-right nav-arrow"></i>
            </div>
        </a>
        
        <a href="partial_payments.php" class="stat-card blue-dark">
            <div>
                <p class="stat-label">Partial Payments</p>
                <p class="stat-number"><?= $partial_payments ?></p>
                <span class="stat-trend"><i class="fas fa-hand-holding-usd"></i> Partially paid</span>
            </div>
            <div class="stat-icon">
                <i class="fas fa-hand-holding-usd"></i>
                <i class="fas fa-chevron-right nav-arrow"></i>
            </div>
        </a>
        
        <a href="pending_bills.php" class="stat-card purple">
            <div>
                <p class="stat-label">Patients Waiting</p>
                <p class="stat-number"><?= $patients_waiting ?></p>
                <span class="stat-trend"><i class="fas fa-users"></i> Need payment</span>
            </div>
            <div class="stat-icon">
                <i class="fas fa-users"></i>
                <i class="fas fa-chevron-right nav-arrow"></i>
            </div>
        </a>
        
        <a href="payment_history.php" class="stat-card teal">
            <div>
                <p class="stat-label">Today's Transactions</p>
                <p class="stat-number"><?= $today_transactions ?></p>
                <span class="stat-trend"><i class="fas fa-receipt"></i> Processed</span>
            </div>
            <div class="stat-icon">
                <i class="fas fa-receipt"></i>
                <i class="fas fa-chevron-right nav-arrow"></i>
            </div>
        </a>
        
    </div>

    <!-- ================================================================ -->
    <!-- CHARTS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">
        
        <!-- Daily Revenue Chart -->
        <div class="card animate-fade-in-up">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-chart-bar title-blue mr-2"></i>
                    Daily Revenue
                </h3>
                <span class="text-xs text-gray-400">Last 7 days</span>
            </div>
            <div class="chart-container">
                <canvas id="dailyChart"></canvas>
            </div>
        </div>
        
        <!-- Monthly Revenue Chart -->
        <div class="card animate-fade-in-up">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-chart-line title-green mr-2"></i>
                    Monthly Revenue
                </h3>
                <span class="text-xs text-gray-400">Last 6 months</span>
            </div>
            <div class="chart-container">
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>
        
        <!-- Payment Methods -->
        <div class="card animate-fade-in-up">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-chart-pie title-purple mr-2"></i>
                    Payment Methods
                </h3>
                <span class="text-xs text-gray-400">Today</span>
            </div>
            <?php if (count($payment_methods) > 0): ?>
                <div class="space-y-2">
                    <?php foreach ($payment_methods as $method): ?>
                        <div class="flex items-center justify-between">
                            <span class="method-badge <?= str_replace('-', '_', $method['payment_method']) ?>">
                                <?= ucfirst(str_replace('_', ' ', $method['payment_method'] ?? 'Other')) ?>
                            </span>
                            <div class="flex items-center gap-3">
                                <span class="text-sm font-semibold">TSh <?= number_format($method['total']) ?></span>
                                <span class="text-xs text-gray-400">(<?= $method['count'] ?>)</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-3 pt-3 border-t border-gray-200 flex justify-between">
                    <span class="font-semibold">Total Today</span>
                    <span class="font-bold text-green-600">TSh <?= number_format($today_revenue) ?></span>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-chart-pie"></i>
                    <p>No payments today</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT TRANSACTIONS -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-history title-blue mr-2"></i>
                Recent Transactions
                <span class="text-sm font-normal text-gray-400">(Last 10)</span>
            </h3>
            <a href="payment_history.php" class="text-xs text-blue-600 hover:underline">View All →</a>
        </div>
        
        <?php if (count($recent_transactions) > 0): ?>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Bill #</th>
                            <th>Patient</th>
                            <th>Visit</th>
                            <th>Amount</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_transactions as $trans): ?>
                            <tr>
                                <td class="font-mono text-xs"><?= htmlspecialchars($trans['bill_number']) ?></td>
                                <td><?= htmlspecialchars($trans['patient_name'] ?? 'Unknown') ?></td>
                                <td class="font-mono text-xs"><?= htmlspecialchars($trans['visit_number'] ?? 'N/A') ?></td>
                                <td class="font-semibold">TSh <?= number_format($trans['grand_total']) ?></td>
                                <td>TSh <?= number_format($trans['amount_paid']) ?></td>
                                <td class="font-semibold <?= $trans['balance'] > 0 ? 'text-red-600' : 'text-green-600' ?>">
                                    TSh <?= number_format($trans['balance']) ?>
                                </td>
                                <td>
                                    <span class="badge <?= 
                                        $trans['status'] === 'paid' ? 'badge-paid' :
                                        ($trans['status'] === 'partial' ? 'badge-partial' :
                                        ($trans['status'] === 'pending' ? 'badge-pending' : 'badge-cancelled'))
                                    ?>">
                                        <?= ucfirst($trans['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td class="text-sm"><?= date('M d, Y h:i A', strtotime($trans['created_at'])) ?></td>
                                <td>
                                    <a href="view_bill.php?id=<?= $trans['bill_number'] ?>" class="btn btn-outline btn-sm">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-receipt"></i>
                <p>No transactions recorded yet</p>
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
            Cashier Dashboard
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
            window.location.href = 'search.php?q=' + encodeURIComponent(query);
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
    // CHARTS
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        // Daily Chart
        var dailyCtx = document.getElementById('dailyChart')?.getContext('2d');
        if (dailyCtx && typeof Chart !== 'undefined') {
            new Chart(dailyCtx, {
                type: 'bar',
                data: {
                    labels: <?= json_encode($daily_labels) ?>,
                    datasets: [{
                        label: 'Revenue (TSh)',
                        data: <?= json_encode($daily_revenue) ?>,
                        backgroundColor: '#0B5ED7',
                        borderRadius: 4,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'TSh ' + context.raw.toLocaleString();
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'TSh ' + value.toLocaleString();
                                }
                            }
                        },
                        x: { grid: { display: false } }
                    }
                }
            });
        }
        
        // Monthly Chart
        var monthlyCtx = document.getElementById('monthlyChart')?.getContext('2d');
        if (monthlyCtx && typeof Chart !== 'undefined') {
            new Chart(monthlyCtx, {
                type: 'line',
                data: {
                    labels: <?= json_encode($monthly_labels) ?>,
                    datasets: [{
                        label: 'Revenue (TSh)',
                        data: <?= json_encode($monthly_revenue) ?>,
                        borderColor: '#059669',
                        backgroundColor: 'rgba(5, 150, 105, 0.1)',
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#059669',
                        pointRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'TSh ' + context.raw.toLocaleString();
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'TSh ' + value.toLocaleString();
                                }
                            }
                        },
                        x: { grid: { display: false } }
                    }
                }
            });
        }
    });

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            searchInput?.focus();
            searchInput?.select();
        }
        if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
            e.preventDefault();
            window.location.href = 'pending_bills.php';
        }
    });

    console.log('%c💰 Braick - Cashier Dashboard (FIXED - No Department Query)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Pending: <?= $pending_bills ?> | Paid: <?= $paid_today ?> | Partial: <?= $partial_payments ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c💰 Revenue: TSh <?= number_format($today_revenue) ?>', 'font-size:13px; color:#0D9488;');
    console.log('%c🔧 Removed department query (columns missing from bill_items)', 'font-size:13px; color:#EF4444;');
</script>

</body>
</html>