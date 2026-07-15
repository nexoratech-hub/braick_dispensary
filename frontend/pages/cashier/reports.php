<?php
// ================================================================
// FILE: frontend/pages/cashier/reports.php
// CASHIER - REPORTS (FIXED)
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
// DATE RANGE
// ================================================================
$today = date('Y-m-d');
$start_of_month = date('Y-m-d', strtotime('first day of this month'));
$start_of_week = date('Y-m-d', strtotime('monday this week'));

// ================================================================
// GET STATISTICS
// ================================================================

// 1. Revenue Today
$stmt = $db->prepare("
    SELECT COALESCE(SUM(amount), 0) as revenue 
    FROM payments 
    WHERE branch_id = ? AND DATE(received_at) = ?
");
$stmt->execute([$user_branch_id, $today]);
$revenue_today = $stmt->fetch()['revenue'] ?? 0;

// 2. Revenue This Month
$stmt = $db->prepare("
    SELECT COALESCE(SUM(amount), 0) as revenue 
    FROM payments 
    WHERE branch_id = ? AND DATE(received_at) BETWEEN ? AND ?
");
$stmt->execute([$user_branch_id, $start_of_month, $today]);
$revenue_month = $stmt->fetch()['revenue'] ?? 0;

// 3. Pending Bills
$stmt = $db->prepare("SELECT COUNT(*) as count FROM bills WHERE branch_id = ? AND status = 'pending'");
$stmt->execute([$user_branch_id]);
$pending_bills = $stmt->fetch()['count'] ?? 0;

// 4. Paid Bills
$stmt = $db->prepare("SELECT COUNT(*) as count FROM bills WHERE branch_id = ? AND status = 'paid'");
$stmt->execute([$user_branch_id]);
$paid_bills = $stmt->fetch()['count'] ?? 0;

// 5. Partial Payments
$stmt = $db->prepare("SELECT COUNT(*) as count FROM bills WHERE branch_id = ? AND status = 'partial'");
$stmt->execute([$user_branch_id]);
$partial_payments = $stmt->fetch()['count'] ?? 0;

// 6. Average Bill
$stmt = $db->prepare("
    SELECT COALESCE(AVG(grand_total), 0) as average 
    FROM bills 
    WHERE branch_id = ? AND status = 'paid'
");
$stmt->execute([$user_branch_id]);
$average_bill = $stmt->fetch()['average'] ?? 0;

// 7. Daily Revenue Chart (Last 7 days)
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

// 8. Monthly Revenue Chart (Last 6 months)
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

// ================================================================
// 9. Department Revenue - FIXED (use item_name)
// ================================================================
$stmt = $db->prepare("
    SELECT 
        bi.item_name as description,
        COALESCE(SUM(bi.total_price), 0) as revenue,
        COUNT(bi.id) as count
    FROM bill_items bi
    JOIN bills b ON bi.bill_id = b.id
    WHERE b.branch_id = ? AND b.status = 'paid'
    GROUP BY bi.item_name
    ORDER BY revenue DESC
    LIMIT 10
");
$stmt->execute([$user_branch_id]);
$department_revenue = $stmt->fetchAll();

// 10. Payment Methods Breakdown - Today
$stmt = $db->prepare("
    SELECT payment_method, COUNT(*) as count, COALESCE(SUM(amount), 0) as total
    FROM payments 
    WHERE branch_id = ? AND DATE(received_at) = ?
    GROUP BY payment_method
    ORDER BY total DESC
");
$stmt->execute([$user_branch_id, $today]);
$payment_methods_today = $stmt->fetchAll();

// 11. Payment Methods All Time
$stmt = $db->prepare("
    SELECT payment_method, COUNT(*) as count, COALESCE(SUM(amount), 0) as total
    FROM payments 
    WHERE branch_id = ?
    GROUP BY payment_method
    ORDER BY total DESC
");
$stmt->execute([$user_branch_id]);
$payment_methods_all = $stmt->fetchAll();

// 12. Daily Collections (Last 30 days)
$daily_collections = [];
$stmt = $db->prepare("
    SELECT DATE(received_at) as date, COUNT(*) as count, COALESCE(SUM(amount), 0) as total
    FROM payments 
    WHERE branch_id = ? AND DATE(received_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(received_at)
    ORDER BY date DESC
");
$stmt->execute([$user_branch_id]);
$daily_collections = $stmt->fetchAll();

// 13. Cashier Performance
$stmt = $db->prepare("
    SELECT 
        u.full_name as cashier,
        COUNT(p.id) as payment_count,
        COALESCE(SUM(p.amount), 0) as total_revenue,
        COALESCE(AVG(p.amount), 0) as average_payment
    FROM payments p
    JOIN users u ON p.received_by = u.id
    WHERE p.branch_id = ?
    GROUP BY p.received_by
    ORDER BY total_revenue DESC
");
$stmt->execute([$user_branch_id]);
$cashier_performance = $stmt->fetchAll();

// ================================================================
// GET SIDEBAR STATISTICS
// ================================================================
$pending_bills_sidebar = 0;
$partial_payments_sidebar = 0;
$paid_today_sidebar = 0;
$patients_waiting_sidebar = 0;

try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM bills WHERE branch_id = ? AND status = 'pending'");
    $stmt->execute([$user_branch_id]);
    $pending_bills_sidebar = $stmt->fetch()['count'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM bills WHERE branch_id = ? AND status = 'partial'");
    $stmt->execute([$user_branch_id]);
    $partial_payments_sidebar = $stmt->fetch()['count'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM bills WHERE branch_id = ? AND status = 'paid' AND DATE(updated_at) = CURDATE()");
    $stmt->execute([$user_branch_id]);
    $paid_today_sidebar = $stmt->fetch()['count'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as count FROM bills WHERE branch_id = ? AND status IN ('pending', 'partial')");
    $stmt->execute([$user_branch_id]);
    $patients_waiting_sidebar = $stmt->fetch()['count'] ?? 0;
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

<!-- ================================================================ -->
<!-- STYLES -->
<!-- ================================================================ -->
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
    .stat-card.green { background: #059669; }
    .stat-card.orange { background: #D97706; }
    .stat-card.purple { background: #7C3AED; }
    .stat-card.teal { background: #0D9488; }
    .stat-card.red { background: #DC2626; }
    
    .chart-container {
        height: 220px;
        max-height: 220px;
    }
    
    .chart-container canvas {
        height: 100% !important;
        max-height: 220px !important;
    }
    
    .report-table-wrap {
        overflow-x: auto;
        max-height: 400px;
        overflow-y: auto;
    }
    
    .report-table-wrap::-webkit-scrollbar {
        width: 5px;
        height: 5px;
    }
    
    .report-table-wrap::-webkit-scrollbar-thumb {
        background: var(--primary);
        border-radius: 4px;
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.82rem;
    }
    
    .data-table th {
        text-align: left;
        padding: 10px 14px;
        font-weight: 700;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #fff;
        background: var(--primary);
        border-bottom: 3px solid var(--primary-dark);
        white-space: nowrap;
        position: sticky;
        top: 0;
        z-index: 10;
    }
    
    .data-table th:first-child {
        border-radius: 8px 0 0 0;
    }
    
    .data-table th:last-child {
        border-radius: 0 8px 0 0;
    }
    
    .data-table tbody tr:nth-child(even) {
        background: var(--primary-bg);
    }
    
    .data-table tbody tr:nth-child(odd) {
        background: var(--bg-card);
    }
    
    .data-table tbody tr:hover {
        background: #D1FAE5;
    }
    
    [data-theme="dark"] .data-table tbody tr:hover {
        background: #1A3A2A;
    }
    
    .data-table td {
        padding: 10px 14px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        vertical-align: middle;
    }
    
    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: var(--text-secondary);
    }
    
    .empty-state i {
        font-size: 3rem;
        color: var(--border-color);
        display: block;
        margin-bottom: 12px;
    }
    
    .export-buttons {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    
    .btn-export {
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
    
    .btn-export:hover {
        transform: scale(1.05);
    }
    
    .btn-pdf { background: #DC2626; color: white; }
    .btn-pdf:hover { background: #B91C1C; }
    .btn-excel { background: #059669; color: white; }
    .btn-excel:hover { background: #047857; }
    .btn-csv { background: #0B5ED7; color: white; }
    .btn-csv:hover { background: #0A4CA8; }
    .btn-print { background: #64748B; color: white; }
    .btn-print:hover { background: #475569; }
    
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
    .method-badge.insurance { background: #E0F2FE; color: #0284C7; }
    .method-badge.other { background: #F1F5F9; color: #64748B; }
    
    [data-theme="dark"] .method-badge.cash { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .method-badge.m-pesa { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .method-badge.airtel_money { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .method-badge.tigo_pesa { background: #2A1A3A; color: #9B4DCA; }
    [data-theme="dark"] .method-badge.halopesa { background: #3A1A2A; color: #F472B6; }
    [data-theme="dark"] .method-badge.bank { background: #1A2A3A; color: #38BDF8; }
    [data-theme="dark"] .method-badge.card { background: #1E293B; color: #94A3B8; }
    [data-theme="dark"] .method-badge.insurance { background: #1A2A3A; color: #38BDF8; }
    [data-theme="dark"] .method-badge.other { background: #1E293B; color: #94A3B8; }
    
    @media (max-width: 768px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .chart-container { height: 160px; }
        .export-buttons { justify-content: center; }
    }
    
    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr; }
        .chart-container { height: 140px; }
        .data-table { font-size: 0.7rem; }
        .data-table th, .data-table td { padding: 6px 10px; }
    }
    
    @media print {
        .top-nav, .sidebar, .btn, .export-buttons, .footer { display: none !important; }
        .main-content { margin: 0 !important; padding: 20px !important; }
        .stat-card { border: 1px solid #ddd !important; page-break-inside: avoid; }
        .card { border: 1px solid #ddd !important; page-break-inside: avoid; }
        .page-header { border-bottom: 2px solid #0B5ED7 !important; }
        .data-table th { background: #0B5ED7 !important; color: white !important; }
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
            <input type="text" id="searchInput" placeholder="Search reports...">
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
                <i class="fas fa-chart-bar mr-2" style="color: var(--primary);"></i> Cashier Reports
            </h1>
            <p class="page-subtitle">
                View cashier statistics and analytics
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-calendar-alt mr-1"></i> <?= date('F d, Y') ?>
                </span>
            </p>
        </div>
        <div class="export-buttons">
            <button onclick="exportPDF()" class="btn-export btn-pdf">
                <i class="fas fa-file-pdf"></i> PDF
            </button>
            <button onclick="exportExcel()" class="btn-export btn-excel">
                <i class="fas fa-file-excel"></i> Excel
            </button>
            <button onclick="exportCSV()" class="btn-export btn-csv">
                <i class="fas fa-file-csv"></i> CSV
            </button>
            <button onclick="window.print()" class="btn-export btn-print">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-grid animate-fade-in-up">
        
        <div class="stat-card blue">
            <div>
                <p class="stat-label">Revenue Today</p>
                <p class="stat-number">TSh <?= number_format($revenue_today) ?></p>
                <span class="stat-trend"><i class="fas fa-calendar-day"></i> Today</span>
            </div>
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
        </div>
        
        <div class="stat-card green">
            <div>
                <p class="stat-label">Revenue This Month</p>
                <p class="stat-number">TSh <?= number_format($revenue_month) ?></p>
                <span class="stat-trend"><i class="fas fa-calendar-alt"></i> This month</span>
            </div>
            <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
        </div>
        
        <div class="stat-card orange">
            <div>
                <p class="stat-label">Pending Bills</p>
                <p class="stat-number"><?= $pending_bills ?></p>
                <span class="stat-trend"><i class="fas fa-clock"></i> Awaiting</span>
            </div>
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
        </div>
        
        <div class="stat-card purple">
            <div>
                <p class="stat-label">Paid Bills</p>
                <p class="stat-number"><?= $paid_bills ?></p>
                <span class="stat-trend"><i class="fas fa-check-circle"></i> Completed</span>
            </div>
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        </div>
        
        <div class="stat-card teal">
            <div>
                <p class="stat-label">Partial Payments</p>
                <p class="stat-number"><?= $partial_payments ?></p>
                <span class="stat-trend"><i class="fas fa-hand-holding-usd"></i> Partially paid</span>
            </div>
            <div class="stat-icon"><i class="fas fa-hand-holding-usd"></i></div>
        </div>
        
        <div class="stat-card red">
            <div>
                <p class="stat-label">Average Bill</p>
                <p class="stat-number">TSh <?= number_format($average_bill) ?></p>
                <span class="stat-trend"><i class="fas fa-calculator"></i> Per bill</span>
            </div>
            <div class="stat-icon"><i class="fas fa-calculator"></i></div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- CHARTS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">
        
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
        
    </div>

    <!-- ================================================================ -->
    <!-- PAYMENT METHODS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">
        
        <div class="card animate-fade-in-up">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-chart-pie title-purple mr-2"></i>
                    Payment Methods Today
                </h3>
                <span class="text-xs text-gray-400">Today</span>
            </div>
            <?php if (count($payment_methods_today) > 0): ?>
                <div class="space-y-2">
                    <?php foreach ($payment_methods_today as $method): ?>
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
                    <span class="font-bold text-green-600">TSh <?= number_format($revenue_today) ?></span>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-chart-pie"></i>
                    <p>No payments today</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="card animate-fade-in-up">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-chart-pie title-teal mr-2"></i>
                    Payment Methods (All Time)
                </h3>
                <span class="text-xs text-gray-400">All time</span>
            </div>
            <?php if (count($payment_methods_all) > 0): ?>
                <div class="space-y-2">
                    <?php foreach ($payment_methods_all as $method): ?>
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
                    <span class="font-semibold">Total Revenue</span>
                    <span class="font-bold text-green-600">TSh <?= number_format(array_sum(array_column($payment_methods_all, 'total'))) ?></span>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-chart-pie"></i>
                    <p>No payments recorded</p>
                </div>
            <?php endif; ?>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- DEPARTMENT REVENUE - FIXED -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-building title-blue mr-2"></i>
                Revenue by Service
            </h3>
            <span class="text-xs text-gray-400">Top 10</span>
        </div>
        <?php if (count($department_revenue) > 0): ?>
            <div class="report-table-wrap" style="max-height:300px;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Service</th>
                            <th>Revenue</th>
                            <th>Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($department_revenue as $index => $item): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= htmlspecialchars($item['description']) ?></td>
                                <td>TSh <?= number_format($item['revenue']) ?></td>
                                <td><?= $item['count'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-3 text-right text-sm text-gray-400">
                Total: TSh <?= number_format(array_sum(array_column($department_revenue, 'revenue'))) ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-building"></i>
                <p>No revenue data available</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- DAILY COLLECTIONS -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-calendar-day title-blue mr-2"></i>
                Daily Collections
            </h3>
            <span class="text-xs text-gray-400">Last 30 days</span>
        </div>
        <?php if (count($daily_collections) > 0): ?>
            <div class="report-table-wrap" style="max-height:300px;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Transactions</th>
                            <th>Total Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($daily_collections as $day): ?>
                            <tr>
                                <td><?= date('M d, Y', strtotime($day['date'])) ?></td>
                                <td><?= $day['count'] ?></td>
                                <td>TSh <?= number_format($day['total']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-3 text-right text-sm text-gray-400">
                Total: TSh <?= number_format(array_sum(array_column($daily_collections, 'total'))) ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-calendar-day"></i>
                <p>No collections data available</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- CASHIER PERFORMANCE -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-user-tie title-green mr-2"></i>
                Cashier Performance
            </h3>
        </div>
        <?php if (count($cashier_performance) > 0): ?>
            <div class="report-table-wrap" style="max-height:300px;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Cashier</th>
                            <th>Payments</th>
                            <th>Total Revenue</th>
                            <th>Average</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cashier_performance as $index => $cashier): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= htmlspecialchars($cashier['cashier']) ?></td>
                                <td><?= $cashier['payment_count'] ?></td>
                                <td>TSh <?= number_format($cashier['total_revenue']) ?></td>
                                <td>TSh <?= number_format($cashier['average_payment']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-user-tie"></i>
                <p>No cashier performance data available</p>
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
            Cashier Reports
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

    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
    }

    function exportPDF() {
        showToast('Exporting PDF', 'Generating PDF report...', 'info');
        window.location.href = 'export_report.php?type=pdf';
    }
    
    function exportExcel() {
        showToast('Exporting Excel', 'Generating Excel report...', 'info');
        window.location.href = 'export_report.php?type=excel';
    }
    
    function exportCSV() {
        showToast('Exporting CSV', 'Generating CSV report...', 'info');
        window.location.href = 'export_report.php?type=csv';
    }

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

    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            searchInput?.focus();
            searchInput?.select();
        }
        if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
            e.preventDefault();
            window.print();
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
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

    console.log('%c💰 Braick - Cashier Reports (FIXED)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Revenue Today: TSh <?= number_format($revenue_today) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🔧 Removed description column error (using item_name)', 'font-size:13px; color:#EF4444;');
</script>

</body>
</html>