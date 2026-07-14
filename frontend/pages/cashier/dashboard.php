
<?php
// ================================================================
// FILE: frontend/pages/cashier/dashboard.php
// CASHIER DASHBOARD - PATHS FIXED
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// INCLUDE CONFIG - FIXED PATH (3 levels up)
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

// ================================================================
// SESSION - Default to reception.rose (Cashier/Reception)
// ================================================================
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 6;
    $_SESSION['full_name'] = 'Rose Mwangi';
    $_SESSION['role'] = 'reception';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'reception.rose';
    $_SESSION['is_admin'] = false;
}

$user_id = $_SESSION['user_id'];
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_full_name = $_SESSION['full_name'] ?? 'Rose Mwangi';
$user_role = $_SESSION['role'] ?? 'reception';

// ================================================================
// DATABASE CONNECTION
// ================================================================
$db = getDB();

// ================================================================
// DATE RANGE
// ================================================================
$today = date('Y-m-d');
$start_of_week = date('Y-m-d', strtotime('monday this week'));
$start_of_month = date('Y-m-d', strtotime('first day of this month'));
$yesterday = date('Y-m-d', strtotime('-1 day'));

// ================================================================
// CASHIER STATISTICS (Using pharmacy_sales table)
// ================================================================

// 1. Today's Revenue
$stmt = $db->prepare("
    SELECT COALESCE(SUM(total), 0) as revenue 
    FROM pharmacy_sales 
    WHERE branch_id = ? 
    AND DATE(sale_date) = ? 
    AND payment_status = 'paid'
");
$stmt->execute([$user_branch_id, $today]);
$today_revenue = $stmt->fetch()['revenue'] ?? 0;

// 2. Yesterday's Revenue
$stmt = $db->prepare("
    SELECT COALESCE(SUM(total), 0) as revenue 
    FROM pharmacy_sales 
    WHERE branch_id = ? 
    AND DATE(sale_date) = ? 
    AND payment_status = 'paid'
");
$stmt->execute([$user_branch_id, $yesterday]);
$yesterday_revenue = $stmt->fetch()['revenue'] ?? 0;

// 3. Today's Sales Count
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM pharmacy_sales 
    WHERE branch_id = ? 
    AND DATE(sale_date) = ? 
    AND payment_status = 'paid'
");
$stmt->execute([$user_branch_id, $today]);
$today_sales = $stmt->fetch()['count'] ?? 0;

// 4. Weekly Revenue
$stmt = $db->prepare("
    SELECT COALESCE(SUM(total), 0) as revenue 
    FROM pharmacy_sales 
    WHERE branch_id = ? 
    AND DATE(sale_date) BETWEEN ? AND ? 
    AND payment_status = 'paid'
");
$stmt->execute([$user_branch_id, $start_of_week, $today]);
$weekly_revenue = $stmt->fetch()['revenue'] ?? 0;

// 5. Monthly Revenue
$stmt = $db->prepare("
    SELECT COALESCE(SUM(total), 0) as revenue 
    FROM pharmacy_sales 
    WHERE branch_id = ? 
    AND DATE(sale_date) BETWEEN ? AND ? 
    AND payment_status = 'paid'
");
$stmt->execute([$user_branch_id, $start_of_month, $today]);
$monthly_revenue = $stmt->fetch()['revenue'] ?? 0;

// 6. Pending Prescriptions (Ready for payment)
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM prescriptions 
    WHERE branch_id = ? 
    AND status = 'pending'
");
$stmt->execute([$user_branch_id]);
$pending_prescriptions = $stmt->fetch()['count'] ?? 0;

// 7. Total Patients (For this branch)
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM patients 
    WHERE branch_id = ?
");
$stmt->execute([$user_branch_id]);
$total_patients = $stmt->fetch()['count'] ?? 0;

// 8. Today's Appointments
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM appointments 
    WHERE branch_id = ? 
    AND DATE(appointment_date) = ?
");
$stmt->execute([$user_branch_id, $today]);
$today_appointments = $stmt->fetch()['count'] ?? 0;

// 9. Pending Payments (payment_status = pending)
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM pharmacy_sales 
    WHERE branch_id = ? 
    AND payment_status = 'pending'
");
$stmt->execute([$user_branch_id]);
$pending_payments = $stmt->fetch()['count'] ?? 0;

// 10. Weekly Sales Trend (Last 7 days)
$weekly_data = [];
$chart_labels = [];
$chart_values = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $label = date('D', strtotime($date));
    $chart_labels[] = $label;
    
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(total), 0) as revenue 
        FROM pharmacy_sales 
        WHERE branch_id = ? 
        AND DATE(sale_date) = ? 
        AND payment_status = 'paid'
    ");
    $stmt->execute([$user_branch_id, $date]);
    $revenue = $stmt->fetch()['revenue'] ?? 0;
    $chart_values[] = (float)$revenue;
}

// 11. Today's Recent Payments (Last 5)
$stmt = $db->prepare("
    SELECT ps.*, p.full_name as patient_name, pr.prescription_number 
    FROM pharmacy_sales ps
    LEFT JOIN patients p ON ps.patient_id = p.id
    LEFT JOIN prescriptions pr ON ps.prescription_id = pr.id
    WHERE ps.branch_id = ? 
    AND DATE(ps.sale_date) = ?
    AND ps.payment_status = 'paid'
    ORDER BY ps.sale_date DESC
    LIMIT 5
");
$stmt->execute([$user_branch_id, $today]);
$recent_payments = $stmt->fetchAll();

// 12. Today's Payment Methods Breakdown
$stmt = $db->prepare("
    SELECT payment_method, COUNT(*) as count, COALESCE(SUM(total), 0) as total
    FROM pharmacy_sales 
    WHERE branch_id = ? 
    AND DATE(sale_date) = ? 
    AND payment_status = 'paid'
    GROUP BY payment_method
");
$stmt->execute([$user_branch_id, $today]);
$payment_methods = $stmt->fetchAll();

// 13. Revenue Growth (Today vs Yesterday)
$revenue_growth = 0;
if ($yesterday_revenue > 0) {
    $revenue_growth = (($today_revenue - $yesterday_revenue) / $yesterday_revenue) * 100;
}

// ================================================================
// SIDEBAR VARIABLES
// ================================================================
$selected_branch_id = $user_branch_id;
$total_employees = 0;
$total_doctors = 0;
$total_branches = 0;
$pending_lab_tests = 0;
$pending_prescriptions_sidebar = $pending_prescriptions;

// ================================================================
// GET UNREAD NOTIFICATIONS
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
// INCLUDE SHARED HEADER (Reception Header) - FIXED PATH
// ================================================================
include_once __DIR__ . '/../../components/reception_header.php';

// ================================================================
// INCLUDE CASHIER SIDEBAR - FIXED PATH
// ================================================================
include_once __DIR__ . '/../../components/cashier_sidebar.php';
?>
<style>
    /* ================================================================
       CASHIER DASHBOARD - ADDITIONAL STYLES
       ================================================================ */
    
    .cashier-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }
    
    .cashier-stat-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 18px 20px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 16px;
    }
    
    .cashier-stat-card:hover {
        border-color: var(--primary);
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    }
    
    .cashier-stat-card .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        flex-shrink: 0;
        color: white;
    }
    
    .cashier-stat-card .stat-icon.blue { background: var(--primary); }
    .cashier-stat-card .stat-icon.green { background: var(--success); }
    .cashier-stat-card .stat-icon.orange { background: #D97706; }
    .cashier-stat-card .stat-icon.purple { background: #7C3AED; }
    .cashier-stat-card .stat-icon.red { background: var(--danger); }
    .cashier-stat-card .stat-icon.teal { background: #0D9488; }
    
    .cashier-stat-card .stat-number {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
    }
    
    .cashier-stat-card .stat-number.green { color: var(--success); }
    .cashier-stat-card .stat-number.blue { color: var(--primary); }
    .cashier-stat-card .stat-number.orange { color: #D97706; }
    .cashier-stat-card .stat-number.purple { color: #7C3AED; }
    .cashier-stat-card .stat-number.red { color: var(--danger); }
    
    .cashier-stat-card .stat-label {
        font-size: 0.75rem;
        color: var(--text-secondary);
        font-weight: 500;
    }
    
    .cashier-stat-card .stat-sub {
        font-size: 0.65rem;
        color: var(--text-secondary);
        opacity: 0.7;
    }
    
    /* Growth Indicator */
    .growth-indicator {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 0.7rem;
        font-weight: 600;
        padding: 2px 10px;
        border-radius: 20px;
    }
    
    .growth-indicator.positive {
        color: var(--success);
        background: var(--success-bg);
    }
    
    .growth-indicator.negative {
        color: var(--danger);
        background: var(--danger-bg);
    }
    
    [data-theme="dark"] .growth-indicator.positive {
        background: #1A3A2A;
        color: #34D399;
    }
    
    [data-theme="dark"] .growth-indicator.negative {
        background: #3A1A1A;
        color: #F87171;
    }
    
    /* Payment Method Badges */
    .method-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    
    .method-badge.cash { background: #E8F0FE; color: var(--primary); }
    .method-badge.card { background: #D1FAE5; color: var(--success); }
    .method-badge.insurance { background: #FEF3C7; color: #D97706; }
    .method-badge.m-pesa { background: #EDE7F6; color: #7C3AED; }
    .method-badge.other { background: var(--gray-200); color: var(--gray-600); }
    
    [data-theme="dark"] .method-badge.cash { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .method-badge.card { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .method-badge.insurance { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .method-badge.m-pesa { background: #2A1A3A; color: #9B4DCA; }
    [data-theme="dark"] .method-badge.other { background: #1E293B; color: #94A3B8; }
    
    /* Payment Item */
    .payment-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 12px;
        border-bottom: 1px solid var(--border-color);
        transition: background 0.2s ease;
    }
    
    .payment-item:hover {
        background: var(--table-hover);
        border-radius: 8px;
    }
    
    .payment-item:last-child {
        border-bottom: none;
    }
    
    .payment-item .payment-info {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    
    .payment-item .payment-info .patient-name {
        font-weight: 500;
        font-size: 0.85rem;
        color: var(--text-primary);
    }
    
    .payment-item .payment-info .prescription-id {
        font-size: 0.65rem;
        color: var(--text-secondary);
        font-family: monospace;
    }
    
    .payment-item .payment-amount {
        font-weight: 700;
        font-size: 1rem;
        color: var(--success);
    }
    
    .payment-item .payment-time {
        font-size: 0.65rem;
        color: var(--text-secondary);
    }
    
    /* Quick Stats Row */
    .quick-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 12px;
        margin-bottom: 20px;
    }
    
    .quick-stat {
        text-align: center;
        padding: 12px;
        border-radius: 12px;
        background: var(--bg-card);
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
    }
    
    .quick-stat:hover {
        border-color: var(--primary);
    }
    
    .quick-stat .qs-number {
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--primary);
    }
    
    .quick-stat .qs-number.green { color: var(--success); }
    .quick-stat .qs-number.orange { color: #D97706; }
    
    .quick-stat .qs-label {
        font-size: 0.65rem;
        color: var(--text-secondary);
        margin-top: 2px;
    }
    
    /* Chart Container */
    .chart-container {
        height: 180px;
        max-height: 180px;
    }
    
    .chart-container canvas {
        height: 100% !important;
        max-height: 180px !important;
    }
    
    /* Empty State */
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
    
    .empty-state p {
        font-size: 0.85rem;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .cashier-stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .quick-stats {
            grid-template-columns: repeat(2, 1fr);
        }
        .chart-container {
            height: 140px;
        }
    }
    
    @media (max-width: 480px) {
        .cashier-stats-grid {
            grid-template-columns: 1fr;
        }
        .quick-stats {
            grid-template-columns: 1fr 1fr;
        }
    }
</style>

<!-- ================================================================ -->
<!-- TOP NAVIGATION (From reception_header.php) -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <input type="text" id="searchInput" placeholder="Search payments, patients...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <!-- Branch Selector - Disabled for cashier (only their branch) -->
        <select class="branch-selector" disabled style="opacity:0.7;cursor:not-allowed;">
            <option value="<?= $user_branch_id ?>">
                🏥 <?= htmlspecialchars($user_branch_name) ?>
            </option>
        </select>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <!-- Dark Mode Toggle -->
        <button id="darkModeToggle" class="dark-toggle-btn" title="Toggle Dark Mode">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <!-- Notifications -->
        <button class="icon-btn" id="notifBtn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= $unread_notifications > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <!-- Profile Avatar -->
        <a href="../cashier/profile.php">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($user_full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
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
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-calendar-day mr-1"></i> <?= date('F d, Y') ?>
                </span>
                <?php if ($revenue_growth != 0): ?>
                    <span class="growth-indicator <?= $revenue_growth >= 0 ? 'positive' : 'negative' ?> ml-2">
                        <i class="fas <?= $revenue_growth >= 0 ? 'fa-arrow-up' : 'fa-arrow-down' ?>"></i>
                        <?= number_format(abs($revenue_growth), 1) ?>%
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="payments.php" class="btn btn-green btn-sm">
                <i class="fas fa-plus-circle"></i> New Payment
            </a>
            <a href="daily_summary.php" class="btn btn-outline btn-sm">
                <i class="fas fa-file-alt"></i> Daily Summary
            </a>
            <button onclick="window.print()" class="btn btn-outline btn-sm">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- QUICK STATS ROW -->
    <!-- ================================================================ -->
    <div class="quick-stats animate-fade-in-up">
        <div class="quick-stat">
            <p class="qs-number green">TSh <?= number_format($today_revenue) ?></p>
            <p class="qs-label">Today's Revenue</p>
        </div>
        <div class="quick-stat">
            <p class="qs-number">TSh <?= number_format($weekly_revenue) ?></p>
            <p class="qs-label">Weekly Revenue</p>
        </div>
        <div class="quick-stat">
            <p class="qs-number orange">TSh <?= number_format($monthly_revenue) ?></p>
            <p class="qs-label">Monthly Revenue</p>
        </div>
        <div class="quick-stat">
            <p class="qs-number"><?= $today_sales ?></p>
            <p class="qs-label">Today's Sales</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- MAIN STATS CARDS -->
    <!-- ================================================================ -->
    <div class="cashier-stats-grid animate-fade-in-up">
        
        <!-- Today's Revenue -->
        <div class="cashier-stat-card">
            <div class="stat-icon green">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div>
                <p class="stat-number green">TSh <?= number_format($today_revenue) ?></p>
                <p class="stat-label">Today's Revenue</p>
                <p class="stat-sub"><?= $today_sales ?> transactions</p>
            </div>
        </div>
        
        <!-- Pending Prescriptions -->
        <div class="cashier-stat-card">
            <div class="stat-icon orange">
                <i class="fas fa-prescription"></i>
            </div>
            <div>
                <p class="stat-number orange"><?= $pending_prescriptions ?></p>
                <p class="stat-label">Pending Prescriptions</p>
                <p class="stat-sub">Ready for payment</p>
            </div>
        </div>
        
        <!-- Total Patients -->
        <div class="cashier-stat-card">
            <div class="stat-icon blue">
                <i class="fas fa-users"></i>
            </div>
            <div>
                <p class="stat-number blue"><?= number_format($total_patients) ?></p>
                <p class="stat-label">Total Patients</p>
                <p class="stat-sub">In <?= htmlspecialchars($user_branch_name) ?></p>
            </div>
        </div>
        
        <!-- Today's Appointments -->
        <div class="cashier-stat-card">
            <div class="stat-icon purple">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div>
                <p class="stat-number purple"><?= $today_appointments ?></p>
                <p class="stat-label">Today's Appointments</p>
                <p class="stat-sub">Scheduled today</p>
            </div>
        </div>
        
        <!-- Pending Payments -->
        <div class="cashier-stat-card">
            <div class="stat-icon red">
                <i class="fas fa-clock"></i>
            </div>
            <div>
                <p class="stat-number red"><?= $pending_payments ?></p>
                <p class="stat-label">Pending Payments</p>
                <p class="stat-sub">Awaiting confirmation</p>
            </div>
        </div>
        
        <!-- Monthly Growth -->
        <div class="cashier-stat-card">
            <div class="stat-icon teal">
                <i class="fas fa-chart-line"></i>
            </div>
            <div>
                <p class="stat-number" style="color: #0D9488;"><?= $monthly_revenue > 0 ? number_format(($monthly_revenue / 30), 0) : 0 ?></p>
                <p class="stat-label">Daily Average</p>
                <p class="stat-sub">This month</p>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- CHART & PAYMENT METHODS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">
        
        <!-- Revenue Chart -->
        <div class="lg:col-span-2 card animate-fade-in-up">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-chart-line title-blue mr-2"></i>
                    Weekly Revenue Trend
                </h3>
                <span class="text-xs text-gray-400">Last 7 days</span>
            </div>
            <div class="chart-container">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>
        
        <!-- Payment Methods Breakdown -->
        <div class="card animate-fade-in-up">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-chart-pie title-green mr-2"></i>
                    Payment Methods
                </h3>
                <span class="text-xs text-gray-400">Today</span>
            </div>
            
            <?php if (count($payment_methods) > 0): ?>
                <div class="space-y-3">
                    <?php foreach ($payment_methods as $method): ?>
                        <div class="flex items-center justify-between">
                            <span class="method-badge <?= $method['payment_method'] ?? 'other' ?>">
                                <i class="fas fa-circle text-[6px]"></i>
                                <?= ucfirst(str_replace('-', ' ', $method['payment_method'] ?? 'Other')) ?>
                            </span>
                            <div class="flex items-center gap-3">
                                <span class="text-sm font-semibold text-gray-700">
                                    TSh <?= number_format($method['total']) ?>
                                </span>
                                <span class="text-xs text-gray-400">
                                    (<?= $method['count'] ?>)
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Total -->
                <div class="mt-3 pt-3 border-t border-gray-200 flex justify-between">
                    <span class="font-semibold text-gray-700">Total Today</span>
                    <span class="font-bold text-green-600">TSh <?= number_format($today_revenue) ?></span>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-chart-pie"></i>
                    <p>No payments processed today</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT PAYMENTS -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-history title-blue mr-2"></i>
                Recent Payments
                <span class="text-sm font-normal text-gray-400">(Today)</span>
            </h3>
            <a href="payment_history.php" class="text-xs text-blue-600 hover:underline">View All →</a>
        </div>
        
        <?php if (count($recent_payments) > 0): ?>
            <div class="space-y-1">
                <?php foreach ($recent_payments as $payment): ?>
                    <div class="payment-item">
                        <div class="payment-info">
                            <span class="patient-name">
                                <?= htmlspecialchars($payment['patient_name'] ?? 'Unknown Patient') ?>
                            </span>
                            <span class="prescription-id">
                                <i class="fas fa-prescription"></i>
                                <?= htmlspecialchars($payment['prescription_number'] ?? 'N/A') ?>
                            </span>
                        </div>
                        <div class="text-right">
                            <div class="payment-amount">TSh <?= number_format($payment['total']) ?></div>
                            <div class="payment-time">
                                <span class="method-badge <?= $payment['payment_method'] ?? 'other' ?>">
                                    <?= ucfirst(str_replace('-', ' ', $payment['payment_method'] ?? 'Other')) ?>
                                </span>
                                <span class="ml-2">
                                    <?= date('h:i A', strtotime($payment['sale_date'])) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-receipt"></i>
                <p>No payments processed today</p>
                <p class="text-xs text-gray-400 mt-1">Click "New Payment" to process a payment</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- QUICK ACTIONS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-5">
        <a href="payments.php" class="group bg-blue-600 text-white rounded-xl p-4 text-center hover:bg-blue-700 transition">
            <i class="fas fa-plus-circle text-2xl block mb-2"></i>
            <span class="text-sm font-medium">New Payment</span>
        </a>
        <a href="payment_history.php" class="group bg-green-600 text-white rounded-xl p-4 text-center hover:bg-green-700 transition">
            <i class="fas fa-history text-2xl block mb-2"></i>
            <span class="text-sm font-medium">Payment History</span>
        </a>
        <a href="daily_summary.php" class="group bg-orange-600 text-white rounded-xl p-4 text-center hover:bg-orange-700 transition">
            <i class="fas fa-file-alt text-2xl block mb-2"></i>
            <span class="text-sm font-medium">Daily Summary</span>
        </a>
        <a href="../reception/appointments.php" class="group bg-purple-600 text-white rounded-xl p-4 text-center hover:bg-purple-700 transition">
            <i class="fas fa-calendar-check text-2xl block mb-2"></i>
            <span class="text-sm font-medium">Appointments</span>
        </a>
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
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT - NO AUTO REFRESH -->
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
    // DATE & TIME - NO AUTO REFRESH
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
    // REVENUE CHART - RENDER ONCE
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        var ctx = document.getElementById('revenueChart')?.getContext('2d');
        if (ctx && typeof Chart !== 'undefined') {
            var labels = <?= json_encode($chart_labels) ?>;
            var values = <?= json_encode($chart_values) ?>;
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Revenue (TSh)',
                        data: values,
                        backgroundColor: '#0B5ED7',
                        borderColor: '#0A4CA8',
                        borderWidth: 1,
                        borderRadius: 6,
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
                            },
                            grid: { color: 'rgba(0,0,0,0.05)' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });
        }
    });

    console.log('%c💰 Braick - Cashier Dashboard', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🏥 Branch: <?= htmlspecialchars($user_branch_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Today\'s Revenue: TSh <?= number_format($today_revenue) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📋 Pending Prescriptions: <?= $pending_prescriptions ?>', 'font-size:13px; color:#D97706;');
    console.log('%c📅 Today\'s Sales: <?= $today_sales ?>', 'font-size:13px; color:#059669;');
    console.log('%c📈 Growth: <?= number_format($revenue_growth, 1) ?>%', 'font-size:13px; color:#059669;');
    console.log('%c🚫 Auto Refresh: DISABLED', 'font-size:13px; color:#EF4444;');
    console.log('%c✅ Using pharmacy_sales table (NOT payments)', 'font-size:13px; color:#34D399;');
    console.log('%c👤 Using Reception Session: <?= $user_full_name ?>', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>