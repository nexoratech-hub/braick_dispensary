<?php
// ================================================================
// FILE: frontend/pages/pharmacy/dashboard.php
// PHARMACY DASHBOARD (FIXED - WITH NAVIGATION)
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
    $_SESSION['is_admin'] = false;
}

$user_id = $_SESSION['user_id'] ?? 5;
$user_full_name = $_SESSION['full_name'] ?? 'Peter Ngalula';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';

$db = getDB();

// ================================================================
// GET STATISTICS
// ================================================================
$today = date('Y-m-d');
$thirty_days_later = date('Y-m-d', strtotime('+30 days'));
$start_of_month = date('Y-m-d', strtotime('first day of this month'));

// 1. Today's Prescription Sales
$stmt = $db->prepare("
    SELECT COUNT(*) as count, COALESCE(SUM(net_amount), 0) as revenue 
    FROM prescription_sales 
    WHERE branch_id = ? AND DATE(created_at) = ? AND status = 'dispensed'
");
$stmt->execute([$user_branch_id, $today]);
$prescription_stats = $stmt->fetch();
$today_prescriptions = $prescription_stats['count'] ?? 0;
$today_prescription_revenue = $prescription_stats['revenue'] ?? 0;

// 2. Today's OTC Sales
$stmt = $db->prepare("
    SELECT COUNT(*) as count, COALESCE(SUM(net_amount), 0) as revenue 
    FROM otc_sales 
    WHERE branch_id = ? AND DATE(created_at) = ?
");
$stmt->execute([$user_branch_id, $today]);
$otc_stats = $stmt->fetch();
$today_otc = $otc_stats['count'] ?? 0;
$today_otc_revenue = $otc_stats['revenue'] ?? 0;

// 3. Total Sales Today
$today_total_sales = $today_prescriptions + $today_otc;
$today_total_revenue = $today_prescription_revenue + $today_otc_revenue;

// 4. Low Stock Medicines
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM medications_inventory 
    WHERE branch_id = ? AND quantity <= reorder_level AND status = 'active'
");
$stmt->execute([$user_branch_id]);
$low_stock = $stmt->fetch()['count'] ?? 0;

// 5. Dispensed Prescriptions Today
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM prescription_sales 
    WHERE branch_id = ? AND DATE(dispensed_at) = ? AND status = 'dispensed'
");
$stmt->execute([$user_branch_id, $today]);
$dispensed_today = $stmt->fetch()['count'] ?? 0;

// ================================================================
// 6. EXPIRY STATISTICS - FIXED (no medication_id)
// ================================================================

// 6a. Expired Medicines (already expired)
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM medications_inventory 
    WHERE branch_id = ? AND expiry_date IS NOT NULL AND expiry_date < ? AND status = 'active'
");
$stmt->execute([$user_branch_id, $today]);
$expired_count = $stmt->fetch()['count'] ?? 0;

// 6b. Expiring Soon (within 30 days)
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM medications_inventory 
    WHERE branch_id = ? AND expiry_date IS NOT NULL 
    AND expiry_date BETWEEN ? AND ? 
    AND status = 'active'
");
$stmt->execute([$user_branch_id, $today, $thirty_days_later]);
$expiring_soon_count = $stmt->fetch()['count'] ?? 0;

// 6c. Get Expired Medicines List (Top 5) - FIXED
$stmt = $db->prepare("
    SELECT id, medication_name as name, quantity, expiry_date, 
           DATEDIFF(expiry_date, ?) as days_until_expiry
    FROM medications_inventory 
    WHERE branch_id = ? AND expiry_date IS NOT NULL 
    AND expiry_date < ? AND status = 'active'
    ORDER BY expiry_date ASC
    LIMIT 5
");
$stmt->execute([$today, $user_branch_id, $today]);
$expired_medicines = $stmt->fetchAll();

// 6d. Get Expiring Soon Medicines List (Top 5) - FIXED
$stmt = $db->prepare("
    SELECT id, medication_name as name, quantity, expiry_date, 
           DATEDIFF(expiry_date, ?) as days_until_expiry
    FROM medications_inventory 
    WHERE branch_id = ? AND expiry_date IS NOT NULL 
    AND expiry_date BETWEEN ? AND ? 
    AND status = 'active'
    ORDER BY expiry_date ASC
    LIMIT 5
");
$stmt->execute([$today, $user_branch_id, $today, $thirty_days_later]);
$expiring_medicines = $stmt->fetchAll();

// 7. Weekly Prescription Sales Chart
$weekly_labels = [];
$weekly_prescription = [];
$weekly_otc = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $weekly_labels[] = date('D', strtotime($date));
    
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM prescription_sales 
        WHERE branch_id = ? AND DATE(created_at) = ? AND status = 'dispensed'
    ");
    $stmt->execute([$user_branch_id, $date]);
    $weekly_prescription[] = $stmt->fetch()['count'] ?? 0;
    
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM otc_sales 
        WHERE branch_id = ? AND DATE(created_at) = ?
    ");
    $stmt->execute([$user_branch_id, $date]);
    $weekly_otc[] = $stmt->fetch()['count'] ?? 0;
}

// 8. Monthly Revenue Chart
$monthly_labels = [];
$monthly_revenue = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('M', strtotime("-$i months"));
    $monthly_labels[] = $month;
    
    $start = date('Y-m-01', strtotime("-$i months"));
    $end = date('Y-m-t', strtotime("-$i months"));
    
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(net_amount), 0) as revenue 
        FROM prescription_sales 
        WHERE branch_id = ? AND DATE(created_at) BETWEEN ? AND ? AND status = 'dispensed'
    ");
    $stmt->execute([$user_branch_id, $start, $end]);
    $prescription_rev = $stmt->fetch()['revenue'] ?? 0;
    
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(net_amount), 0) as revenue 
        FROM otc_sales 
        WHERE branch_id = ? AND DATE(created_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$user_branch_id, $start, $end]);
    $otc_rev = $stmt->fetch()['revenue'] ?? 0;
    
    $monthly_revenue[] = $prescription_rev + $otc_rev;
}

// 9. Top Selling Medicines
$stmt = $db->prepare("
    SELECT 
        medicine_name, 
        SUM(quantity) as total_quantity,
        COUNT(*) as sale_count
    FROM (
        SELECT medicine_name, quantity FROM prescription_sale_items
        UNION ALL
        SELECT medicine_name, quantity FROM otc_sale_items
    ) as all_sales
    GROUP BY medicine_name
    ORDER BY total_quantity DESC
    LIMIT 5
");
$stmt->execute();
$top_medicines = $stmt->fetchAll();

// 10. Recent Sales (Last 10)
$stmt = $db->prepare("
    (SELECT 
        'prescription' as type,
        sale_number as number,
        patient_id as patient_or_customer,
        total_amount,
        status,
        created_at
    FROM prescription_sales 
    WHERE branch_id = ?)
    UNION ALL
    (SELECT 
        'otc' as type,
        sale_number as number,
        customer_name as patient_or_customer,
        total_amount,
        'completed' as status,
        created_at
    FROM otc_sales 
    WHERE branch_id = ?)
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute([$user_branch_id, $user_branch_id]);
$recent_sales = $stmt->fetchAll();

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

<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }
    
    /* Navigation Cards - with pointer cursor */
    .stat-card {
        border-radius: 16px;
        padding: 18px 20px;
        border: none;
        transition: all 0.3s;
        color: white;
        cursor: pointer;
        text-decoration: none;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    
    .stat-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 8px 30px rgba(0,0,0,0.2);
    }
    
    .stat-card:active {
        transform: scale(0.98);
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
    .stat-card.purple { background: #7C3AED; }
    .stat-card.orange { background: #D97706; }
    .stat-card.teal { background: #0D9488; }
    
    .stat-card.expired {
        background: #DC2626;
    }
    
    .stat-card.expiring {
        background: #D97706;
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
    
    .stat-card .expiry-list {
        margin-top: 8px;
        font-size: 0.7rem;
        color: rgba(255,255,255,0.9);
        max-height: 80px;
        overflow-y: auto;
    }
    
    .stat-card .expiry-list::-webkit-scrollbar {
        width: 3px;
    }
    
    .stat-card .expiry-list::-webkit-scrollbar-thumb {
        background: rgba(255,255,255,0.3);
        border-radius: 10px;
    }
    
    .stat-card .expiry-list .expiry-item {
        display: flex;
        justify-content: space-between;
        padding: 2px 0;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    
    .stat-card .expiry-list .expiry-item:last-child {
        border-bottom: none;
    }
    
    .stat-card .expiry-list .expiry-item .days {
        font-weight: 600;
        padding: 0 6px;
        border-radius: 4px;
        font-size: 0.6rem;
    }
    
    .stat-card .expiry-list .expiry-item .days.expired {
        background: rgba(255,255,255,0.2);
        color: #FEE2E2;
    }
    
    .stat-card .expiry-list .expiry-item .days.soon {
        background: rgba(255,255,255,0.2);
        color: #FEF3C7;
    }
    
    .stat-card .view-all-link {
        font-size: 0.6rem;
        color: rgba(255,255,255,0.8);
        text-decoration: underline;
        display: inline-block;
        margin-top: 4px;
    }
    
    .stat-card .view-all-link:hover {
        color: white;
    }
    
    .expiry-empty {
        font-size: 0.65rem;
        color: rgba(255,255,255,0.7);
        padding: 4px 0;
    }
    
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
    
    .recent-item .sale-info .sale-number {
        font-weight: 500;
        font-size: 0.85rem;
        color: var(--text-primary);
    }
    
    .recent-item .sale-info .sale-type {
        font-size: 0.65rem;
        padding: 1px 8px;
        border-radius: 10px;
    }
    
    .recent-item .sale-info .sale-type.prescription {
        background: #E8F0FE;
        color: #0B5ED7;
    }
    
    .recent-item .sale-info .sale-type.otc {
        background: #D1FAE5;
        color: #059669;
    }
    
    [data-theme="dark"] .recent-item .sale-info .sale-type.prescription {
        background: #1E3A5F;
        color: #6EA8FE;
    }
    
    [data-theme="dark"] .recent-item .sale-info .sale-type.otc {
        background: #1A3A2A;
        color: #34D399;
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
        .stat-card .expiry-list {
            max-height: 60px;
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
            <input type="text" id="searchInput" placeholder="Search sales, medicines...">
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
                <i class="fas fa-prescription mr-2" style="color: var(--primary);"></i> Pharmacy Dashboard
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
                <?php if ($expired_count > 0): ?>
                    <span class="ml-2 inline-flex bg-red-100 text-red-700 px-3 py-1 rounded-full text-xs border border-red-200">
                        <i class="fas fa-exclamation-circle mr-1"></i> <?= $expired_count ?> expired
                    </span>
                <?php endif; ?>
                <?php if ($expiring_soon_count > 0): ?>
                    <span class="ml-2 inline-flex bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full text-xs border border-yellow-200">
                        <i class="fas fa-clock mr-1"></i> <?= $expiring_soon_count ?> expiring soon
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="pending_prescriptions.php" class="btn btn-blue btn-sm">
                <i class="fas fa-clock"></i> Pending (<?= $today_prescriptions ?>)
            </a>
            <a href="new_otc_sale.php" class="btn btn-green btn-sm">
                <i class="fas fa-plus-circle"></i> New OTC Sale
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS (8 CARDS WITH NAVIGATION) -->
    <!-- ================================================================ -->
    <div class="stats-grid animate-fade-in-up">
        
        <!-- 1. Today's Prescriptions - Navigates to prescription_history.php -->
        <a href="prescription_history.php?filter=today" class="stat-card blue">
            <div>
                <p class="stat-label">Today's Prescriptions</p>
                <p class="stat-number"><?= $today_prescriptions ?></p>
                <span class="stat-trend">TSh <?= number_format($today_prescription_revenue) ?></span>
            </div>
            <div class="stat-icon">
                <i class="fas fa-prescription"></i>
                <i class="fas fa-chevron-right nav-arrow"></i>
            </div>
        </a>
        
        <!-- 2. Today's OTC Sales - Navigates to otc_history.php -->
        <a href="otc_history.php?filter=today" class="stat-card green">
            <div>
                <p class="stat-label">Today's OTC Sales</p>
                <p class="stat-number"><?= $today_otc ?></p>
                <span class="stat-trend">TSh <?= number_format($today_otc_revenue) ?></span>
            </div>
            <div class="stat-icon">
                <i class="fas fa-shopping-cart"></i>
                <i class="fas fa-chevron-right nav-arrow"></i>
            </div>
        </a>
        
        <!-- 3. Total Sales Today - Navigates to reports.php -->
        <a href="reports.php" class="stat-card purple">
            <div>
                <p class="stat-label">Total Sales Today</p>
                <p class="stat-number"><?= $today_total_sales ?></p>
                <span class="stat-trend">TSh <?= number_format($today_total_revenue) ?></span>
            </div>
            <div class="stat-icon">
                <i class="fas fa-chart-line"></i>
                <i class="fas fa-chevron-right nav-arrow"></i>
            </div>
        </a>
        
        <!-- 4. Revenue Today - Navigates to reports.php -->
        <a href="reports.php" class="stat-card teal">
            <div>
                <p class="stat-label">Revenue Today</p>
                <p class="stat-number">TSh <?= number_format($today_total_revenue) ?></p>
                <span class="stat-trend"><i class="fas fa-money-bill-wave"></i> Total</span>
            </div>
            <div class="stat-icon">
                <i class="fas fa-money-bill-wave"></i>
                <i class="fas fa-chevron-right nav-arrow"></i>
            </div>
        </a>
        
        <!-- 5. Low Stock - Navigates to low_stock.php -->
        <a href="low_stock.php" class="stat-card orange">
            <div>
                <p class="stat-label">Low Stock</p>
                <p class="stat-number"><?= $low_stock ?></p>
                <span class="stat-trend"><i class="fas fa-exclamation-triangle"></i> Needs restock</span>
            </div>
            <div class="stat-icon">
                <i class="fas fa-exclamation-triangle"></i>
                <i class="fas fa-chevron-right nav-arrow"></i>
            </div>
        </a>
        
        <!-- 6. Dispensed Today - Navigates to prescription_history.php -->
        <a href="prescription_history.php?filter=dispensed" class="stat-card blue-dark">
            <div>
                <p class="stat-label">Dispensed Today</p>
                <p class="stat-number"><?= $dispensed_today ?></p>
                <span class="stat-trend"><i class="fas fa-check-circle"></i> Completed</span>
            </div>
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
                <i class="fas fa-chevron-right nav-arrow"></i>
            </div>
        </a>
        
        <!-- 7. Expired Medicines - Navigates to expired_medicines.php -->
        <a href="expired_medicines.php" class="stat-card expired">
            <div>
                <p class="stat-label">
                    <i class="fas fa-times-circle mr-1"></i> Expired Medicines
                </p>
                <p class="stat-number"><?= $expired_count ?></p>
                <span class="stat-trend"><?= $expired_count > 0 ? '⚠️ Needs attention!' : '✅ All good' ?></span>
                
                <?php if (count($expired_medicines) > 0): ?>
                    <div class="expiry-list">
                        <?php foreach ($expired_medicines as $med): ?>
                            <div class="expiry-item">
                                <span><?= htmlspecialchars($med['name']) ?> (<?= $med['quantity'] ?>)</span>
                                <span class="days expired">
                                    Expired <?= abs($med['days_until_expiry']) ?> days ago
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($expired_count > 5): ?>
                        <span class="view-all-link">View all <?= $expired_count ?> expired →</span>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="expiry-empty">No expired medicines</div>
                <?php endif; ?>
            </div>
            <div class="stat-icon">
                <i class="fas fa-calendar-times"></i>
                <i class="fas fa-chevron-right nav-arrow"></i>
            </div>
        </a>
        
        <!-- 8. Expiring Soon - Navigates to expiring_medicines.php -->
        <a href="expiring_medicines.php" class="stat-card expiring">
            <div>
                <p class="stat-label">
                    <i class="fas fa-clock mr-1"></i> Expiring Soon
                </p>
                <p class="stat-number"><?= $expiring_soon_count ?></p>
                <span class="stat-trend">Within 30 days</span>
                
                <?php if (count($expiring_medicines) > 0): ?>
                    <div class="expiry-list">
                        <?php foreach ($expiring_medicines as $med): ?>
                            <div class="expiry-item">
                                <span><?= htmlspecialchars($med['name']) ?> (<?= $med['quantity'] ?>)</span>
                                <span class="days soon">
                                    <?= $med['days_until_expiry'] ?> days left
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($expiring_soon_count > 5): ?>
                        <span class="view-all-link">View all <?= $expiring_soon_count ?> expiring →</span>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="expiry-empty">No medicines expiring soon</div>
                <?php endif; ?>
            </div>
            <div class="stat-icon">
                <i class="fas fa-calendar-alt"></i>
                <i class="fas fa-chevron-right nav-arrow"></i>
            </div>
        </a>
        
    </div>

    <!-- ================================================================ -->
    <!-- CHARTS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">
        
        <!-- Weekly Prescription vs OTC -->
        <div class="card animate-fade-in-up">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-chart-bar title-blue mr-2"></i>
                    Prescription vs OTC
                </h3>
                <span class="text-xs text-gray-400">Last 7 days</span>
            </div>
            <div class="chart-container">
                <canvas id="weeklyChart"></canvas>
            </div>
        </div>
        
        <!-- Monthly Revenue -->
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
        
        <!-- Top Selling Medicines -->
        <div class="card animate-fade-in-up">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-trophy title-purple mr-2"></i>
                    Top Selling Medicines
                </h3>
            </div>
            <?php if (count($top_medicines) > 0): ?>
                <div class="space-y-2">
                    <?php foreach ($top_medicines as $index => $med): ?>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="text-sm font-bold text-gray-400">#<?= $index + 1 ?></span>
                                <span class="text-sm font-medium text-gray-700"><?= htmlspecialchars($med['medicine_name']) ?></span>
                            </div>
                            <span class="badge badge-blue"><?= $med['total_quantity'] ?> sold</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-pills"></i>
                    <p>No sales recorded yet</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT SALES -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-history title-blue mr-2"></i>
                Recent Sales
                <span class="text-sm font-normal text-gray-400">(Last 10)</span>
            </h3>
            <a href="prescription_history.php" class="text-xs text-blue-600 hover:underline">View All →</a>
        </div>
        
        <?php if (count($recent_sales) > 0): ?>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Sale #</th>
                            <th>Type</th>
                            <th>Patient/Customer</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_sales as $sale): ?>
                            <tr>
                                <td class="font-mono text-xs font-semibold"><?= htmlspecialchars($sale['number']) ?></td>
                                <td>
                                    <span class="sale-type <?= $sale['type'] ?>">
                                        <?= ucfirst($sale['type']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($sale['patient_or_customer']) ?></td>
                                <td class="font-semibold">TSh <?= number_format($sale['total_amount']) ?></td>
                                <td>
                                    <span class="badge <?= 
                                        $sale['status'] === 'dispensed' || $sale['status'] === 'completed' ? 'badge-green' :
                                        ($sale['status'] === 'pending' ? 'badge-yellow' : 'badge-red')
                                    ?>">
                                        <?= ucfirst($sale['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td class="text-sm"><?= date('M d, Y h:i A', strtotime($sale['created_at'])) ?></td>
                                <td>
                                    <a href="view_sale.php?type=<?= $sale['type'] ?>&id=<?= $sale['number'] ?>" class="btn btn-outline btn-sm">
                                        <i class="fas fa-eye"></i> View
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
                <p>No sales recorded yet</p>
                <p class="text-xs text-gray-400 mt-1">Start by dispensing a prescription or creating an OTC sale</p>
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
            Pharmacy Dashboard
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
        // Weekly Chart
        var weeklyCtx = document.getElementById('weeklyChart')?.getContext('2d');
        if (weeklyCtx && typeof Chart !== 'undefined') {
            new Chart(weeklyCtx, {
                type: 'bar',
                data: {
                    labels: <?= json_encode($weekly_labels) ?>,
                    datasets: [
                        {
                            label: 'Prescription',
                            data: <?= json_encode($weekly_prescription) ?>,
                            backgroundColor: '#0B5ED7',
                            borderRadius: 4,
                            barPercentage: 0.4
                        },
                        {
                            label: 'OTC',
                            data: <?= json_encode($weekly_otc) ?>,
                            backgroundColor: '#059669',
                            borderRadius: 4,
                            barPercentage: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                pointStyle: 'circle',
                                padding: 20
                            }
                        }
                    },
                    scales: {
                        y: { beginAtZero: true, ticks: { stepSize: 1 } },
                        x: { grid: { display: false } }
                    }
                }
            });
        }
        
        // Monthly Revenue Chart
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
        if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
            e.preventDefault();
            window.location.href = 'new_otc_sale.php';
        }
    });

    // ================================================================
    // CARD CLICK NAVIGATION - Track clicks
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        var cards = document.querySelectorAll('.stat-card');
        cards.forEach(function(card) {
            card.addEventListener('click', function(e) {
                var href = this.getAttribute('href');
                if (href) {
                    console.log('🔗 Navigating to: ' + href);
                }
            });
        });
    });

    console.log('%c💊 Braick - Pharmacy Dashboard (FIXED + Navigation)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Prescriptions: <?= $today_prescriptions ?> | OTC: <?= $today_otc ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c💰 Revenue: TSh <?= number_format($today_total_revenue) ?>', 'font-size:13px; color:#0D9488;');
    console.log('%c⏰ Expired: <?= $expired_count ?> | Expiring Soon: <?= $expiring_soon_count ?>', 'font-size:13px; color:#DC2626;');
    console.log('%c🖱️ All cards are clickable with navigation', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>