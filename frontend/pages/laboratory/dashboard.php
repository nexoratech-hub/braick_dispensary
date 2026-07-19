<?php
// ================================================================
// FILE: frontend/pages/laboratory/dashboard.php
// LABORATORY DASHBOARD - FULL VERSION WITH AUTO-UPDATE
// DEFAULT USER: Lab Technician Dodoma (ID: 8)
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// INCLUDE CONFIG
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

// ================================================================
// DEFAULT SESSION - Lab Technician Dodoma (ID: 8)
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'laboratory') {
    $_SESSION['user_id'] = 8;
    $_SESSION['full_name'] = 'Lab Technician Dodoma';
    $_SESSION['role'] = 'laboratory';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'lab.dodoma';
    $_SESSION['is_admin'] = false;
    $_SESSION['profile_pic'] = '';
}

$user_id = $_SESSION['user_id'] ?? 8;
$user_full_name = $_SESSION['full_name'] ?? 'Lab Technician Dodoma';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_username = $_SESSION['username'] ?? 'lab.dodoma';

// ================================================================
// DATABASE CONNECTION
// ================================================================
$db = getDB();

// ================================================================
// GET STATISTICS
// ================================================================
$today = date('Y-m-d');
$start_of_month = date('Y-m-01');

// 1. Pending Requests
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_requests WHERE branch_id = ? AND status = 'pending'");
$stmt->execute([$user_branch_id]);
$pending = $stmt->fetch()['count'] ?? 0;

// 2. In Progress Requests
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_requests WHERE branch_id = ? AND status = 'in_progress'");
$stmt->execute([$user_branch_id]);
$in_progress = $stmt->fetch()['count'] ?? 0;

// 3. Completed Today
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_requests WHERE branch_id = ? AND status = 'completed' AND DATE(completed_at) = ?");
$stmt->execute([$user_branch_id, $today]);
$completed_today = $stmt->fetch()['count'] ?? 0;

// 4. Today's Tests
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM lab_request_items lri
    JOIN lab_requests lr ON lri.request_id = lr.id
    WHERE lr.branch_id = ? AND DATE(lri.completed_at) = ?
");
$stmt->execute([$user_branch_id, $today]);
$today_tests = $stmt->fetch()['count'] ?? 0;

// 5. Total Tests (All Time)
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM lab_request_items lri
    JOIN lab_requests lr ON lri.request_id = lr.id
    WHERE lr.branch_id = ?
");
$stmt->execute([$user_branch_id]);
$total_tests = $stmt->fetch()['count'] ?? 0;

// 6. Total Requests
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_requests WHERE branch_id = ?");
$stmt->execute([$user_branch_id]);
$total_requests = $stmt->fetch()['count'] ?? 0;

// 7. Completion Rate
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
    FROM lab_requests 
    WHERE branch_id = ?
");
$stmt->execute([$user_branch_id]);
$rate_data = $stmt->fetch();
$total_requests_all = $rate_data['total'] ?? 0;
$completed_requests = $rate_data['completed'] ?? 0;
$completion_rate = $total_requests_all > 0 ? round(($completed_requests / $total_requests_all) * 100, 1) : 0;

// 8. Recent Requests (Last 10)
$stmt = $db->prepare("
    SELECT lr.*, 
           p.full_name as patient_name, p.patient_id,
           u.full_name as doctor_name,
           (SELECT COUNT(*) FROM lab_request_items WHERE request_id = lr.id) as test_count,
           (SELECT COUNT(*) FROM lab_request_items WHERE request_id = lr.id AND status = 'completed') as completed_count
    FROM lab_requests lr
    JOIN patients p ON lr.patient_id = p.id
    JOIN users u ON lr.doctor_id = u.id
    WHERE lr.branch_id = ?
    ORDER BY lr.requested_at DESC
    LIMIT 10
");
$stmt->execute([$user_branch_id]);
$recent_requests = $stmt->fetchAll();

// 9. Daily Tests Chart (Last 7 days)
$daily_labels = [];
$daily_tests = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $daily_labels[] = date('D', strtotime($date));
    
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM lab_request_items lri
        JOIN lab_requests lr ON lri.request_id = lr.id
        WHERE lr.branch_id = ? AND DATE(lri.completed_at) = ?
    ");
    $stmt->execute([$user_branch_id, $date]);
    $daily_tests[] = (int)($stmt->fetch()['count'] ?? 0);
}

// 10. Monthly Tests Chart (Last 6 months)
$monthly_labels = [];
$monthly_tests = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('M', strtotime("-$i months"));
    $monthly_labels[] = $month;
    
    $start = date('Y-m-01', strtotime("-$i months"));
    $end = date('Y-m-t', strtotime("-$i months"));
    
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM lab_request_items lri
        JOIN lab_requests lr ON lri.request_id = lr.id
        WHERE lr.branch_id = ? AND DATE(lri.completed_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$user_branch_id, $start, $end]);
    $monthly_tests[] = (int)($stmt->fetch()['count'] ?? 0);
}

// 11. Most Requested Tests
$stmt = $db->prepare("
    SELECT lri.test_name, COUNT(*) as count 
    FROM lab_request_items lri
    JOIN lab_requests lr ON lri.request_id = lr.id
    WHERE lr.branch_id = ? AND lri.status = 'completed'
    GROUP BY lri.test_name
    ORDER BY count DESC
    LIMIT 5
");
$stmt->execute([$user_branch_id]);
$most_requested = $stmt->fetchAll();

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
include_once __DIR__ . '/../../components/laboratory_header.php';
include_once __DIR__ . '/../../components/laboratory_sidebar.php';
?>

<style>
    /* ================================================================
       LABORATORY DASHBOARD STYLES
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
        cursor: pointer;
        text-decoration: none;
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: relative;
        overflow: hidden;
    }
    
    .stat-card::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -30%;
        width: 100px;
        height: 100px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
        pointer-events: none;
    }
    
    .stat-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 8px 30px rgba(0,0,0,0.2);
    }
    
    .stat-card:active {
        transform: scale(0.98);
    }
    
    .stat-card.orange { background: linear-gradient(135deg, #D97706, #B45309); }
    .stat-card.blue { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
    .stat-card.green { background: linear-gradient(135deg, #059669, #047857); }
    .stat-card.purple { background: linear-gradient(135deg, #7C3AED, #6D28D9); }
    .stat-card.teal { background: linear-gradient(135deg, #0D9488, #0F766E); }
    .stat-card.red { background: linear-gradient(135deg, #DC2626, #B91C1C); }
    .stat-card.pink { background: linear-gradient(135deg, #DB2777, #BE185D); }
    .stat-card.indigo { background: linear-gradient(135deg, #4F46E5, #4338CA); }
    
    .stat-card .stat-left {
        display: flex;
        flex-direction: column;
        gap: 2px;
        z-index: 1;
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
        z-index: 1;
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
        font-size: 0.8rem;
        z-index: 1;
    }
    
    .stat-card:hover .nav-arrow {
        opacity: 1;
        transform: translateX(4px);
    }
    
    /* ================================================================
       UPDATE BADGE
       ================================================================ */
    .update-badge {
        background: rgba(255,255,255,0.1);
        color: #93C5FD;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .update-badge .fa-spin {
        animation: fa-spin 2s infinite linear;
    }
    
    @keyframes fa-spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    /* ================================================================
       RECENT ITEMS
       ================================================================ */
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
    
    .recent-item .request-info .patient-name {
        font-weight: 500;
        font-size: 0.85rem;
        color: var(--text-primary);
    }
    
    .recent-item .request-info .test-count {
        font-size: 0.7rem;
        color: var(--text-secondary);
    }
    
    .recent-item .request-status {
        font-size: 0.6rem;
        font-weight: 600;
        padding: 2px 10px;
        border-radius: 12px;
    }
    
    .recent-item .request-status.pending {
        background: #FEF3C7;
        color: #D97706;
    }
    
    .recent-item .request-status.in_progress {
        background: #E8F0FE;
        color: var(--primary);
    }
    
    .recent-item .request-status.completed {
        background: #D1FAE5;
        color: #059669;
    }
    
    .recent-item .request-status.cancelled {
        background: #FEE2E2;
        color: #DC2626;
    }
    
    [data-theme="dark"] .recent-item .request-status.pending {
        background: #3D2E0A;
        color: #FBBF24;
    }
    
    [data-theme="dark"] .recent-item .request-status.in_progress {
        background: #1E3A5F;
        color: #6EA8FE;
    }
    
    [data-theme="dark"] .recent-item .request-status.completed {
        background: #1A3A2A;
        color: #34D399;
    }
    
    [data-theme="dark"] .recent-item .request-status.cancelled {
        background: #3A1A1A;
        color: #F87171;
    }
    
    /* ================================================================
       CHART CONTAINER
       ================================================================ */
    .chart-container {
        height: 200px;
        max-height: 200px;
    }
    
    .chart-container canvas {
        height: 100% !important;
        max-height: 200px !important;
    }
    
    /* ================================================================
       EMPTY STATE
       ================================================================ */
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
    
    /* ================================================================
       MOST REQUESTED
       ================================================================ */
    .most-requested-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid var(--border-color);
    }
    
    .most-requested-item:last-child {
        border-bottom: none;
    }
    
    .most-requested-item .rank {
        font-size: 0.75rem;
        font-weight: 700;
        color: var(--text-secondary);
        min-width: 28px;
    }
    
    .most-requested-item .test-name {
        font-size: 0.85rem;
        font-weight: 500;
        color: var(--text-primary);
        flex: 1;
    }
    
    .most-requested-item .count {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--primary);
        background: var(--primary-bg);
        padding: 2px 12px;
        border-radius: 20px;
    }
    
    /* ================================================================
       RESPONSIVE
       ================================================================ */
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
            <input type="text" id="searchInput" placeholder="Search requests, patients...">
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

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-5">
        <div>
            <h1 class="page-title">
                <i class="fas fa-flask mr-2" style="color: var(--primary);"></i> Laboratory Dashboard
                <span class="role-badge-display ml-2">LABORATORY</span>
                <span class="update-badge ml-2" id="updateBadge">
                    <i class="fas fa-sync-alt fa-spin"></i> Live
                </span>
            </h1>
            <p class="page-subtitle">
                Welcome, <strong><?= htmlspecialchars($user_full_name) ?></strong>!
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-calendar-day mr-1"></i> <?= date('F d, Y') ?>
                </span>
                <span class="ml-2 text-xs text-gray-400">
                    <i class="fas fa-hand-pointer mr-1"></i> Click cards to navigate
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="pending_requests.php" class="btn btn-blue btn-sm">
                <i class="fas fa-clock"></i> Pending (<span id="statPending"><?= $pending ?></span>)
            </a>
            <a href="in_progress.php" class="btn btn-outline btn-sm">
                <i class="fas fa-spinner"></i> In Progress (<span id="statInProgress"><?= $in_progress ?></span>)
            </a>
            <button onclick="manualRefresh()" class="btn btn-outline btn-sm" id="refreshBtn">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS - WITH NAVIGATION -->
    <!-- ================================================================ -->
    <div class="stats-grid animate-fade-in-up">
        
        <!-- 1. Pending Requests -->
        <a href="pending_requests.php" class="stat-card orange">
            <div class="stat-left">
                <p class="stat-label">Pending Requests</p>
                <p class="stat-number" id="statPendingCard"><?= $pending ?></p>
                <span class="stat-trend"><i class="fas fa-clock"></i> Awaiting</span>
            </div>
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
                <i class="fas fa-chevron-right nav-arrow"></i>
            </div>
        </a>
        
        <!-- 2. In Progress -->
        <a href="in_progress.php" class="stat-card blue">
            <div class="stat-left">
                <p class="stat-label">In Progress</p>
                <p class="stat-number" id="statInProgressCard"><?= $in_progress ?></p>
                <span class="stat-trend"><i class="fas fa-spinner"></i> Running</span>
            </div>
            <div class="stat-icon">
                <i class="fas fa-spinner"></i>
                <i class="fas fa-chevron-right nav-arrow"></i>
            </div>
        </a>
        
        <!-- 3. Completed Today -->
        <a href="completed_requests.php" class="stat-card green">
            <div class="stat-left">
                <p class="stat-label">Completed Today</p>
                <p class="stat-number" id="statCompletedTodayCard"><?= $completed_today ?></p>
                <span class="stat-trend"><i class="fas fa-check-circle"></i> Done</span>
            </div>
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
                <i class="fas fa-chevron-right nav-arrow"></i>
            </div>
        </a>
        
        <!-- 4. Today's Tests -->
        <a href="results_history.php?filter=today" class="stat-card purple">
            <div class="stat-left">
                <p class="stat-label">Today's Tests</p>
                <p class="stat-number" id="statTodayTestsCard"><?= $today_tests ?></p>
                <span class="stat-trend"><i class="fas fa-flask"></i> Completed</span>
            </div>
            <div class="stat-icon">
                <i class="fas fa-flask"></i>
                <i class="fas fa-chevron-right nav-arrow"></i>
            </div>
        </a>
        
        <!-- 5. Total Tests -->
        <a href="results_history.php" class="stat-card teal">
            <div class="stat-left">
                <p class="stat-label">Total Tests</p>
                <p class="stat-number" id="statTotalTestsCard"><?= number_format($total_tests) ?></p>
                <span class="stat-trend"><i class="fas fa-chart-line"></i> All Time</span>
            </div>
            <div class="stat-icon">
                <i class="fas fa-chart-line"></i>
                <i class="fas fa-chevron-right nav-arrow"></i>
            </div>
        </a>
        
        <!-- 6. Total Requests -->
        <a href="pending_requests.php" class="stat-card indigo">
            <div class="stat-left">
                <p class="stat-label">Total Requests</p>
                <p class="stat-number" id="statTotalRequestsCard"><?= number_format($total_requests) ?></p>
                <span class="stat-trend"><i class="fas fa-list"></i> All Time</span>
            </div>
            <div class="stat-icon">
                <i class="fas fa-list"></i>
                <i class="fas fa-chevron-right nav-arrow"></i>
            </div>
        </a>
        
        <!-- 7. Completion Rate -->
        <a href="completed_requests.php" class="stat-card pink">
            <div class="stat-left">
                <p class="stat-label">Completion Rate</p>
                <p class="stat-number" id="statCompletionRateCard"><?= $completion_rate ?>%</p>
                <span class="stat-trend"><i class="fas fa-percentage"></i> Success Rate</span>
            </div>
            <div class="stat-icon">
                <i class="fas fa-percentage"></i>
                <i class="fas fa-chevron-right nav-arrow"></i>
            </div>
        </a>
        
        <!-- 8. Average Tests per Request -->
        <a href="results_history.php" class="stat-card red">
            <div class="stat-left">
                <p class="stat-label">Avg Tests/Request</p>
                <p class="stat-number" id="statAvgTestsCard">
                    <?= $total_requests > 0 ? number_format($total_tests / $total_requests, 1) : '0.0' ?>
                </p>
                <span class="stat-trend"><i class="fas fa-calculator"></i> Per Request</span>
            </div>
            <div class="stat-icon">
                <i class="fas fa-calculator"></i>
                <i class="fas fa-chevron-right nav-arrow"></i>
            </div>
        </a>
        
    </div>

    <!-- ================================================================ -->
    <!-- CHARTS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">
        
        <!-- Daily Tests Chart -->
        <div class="card animate-fade-in-up">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-chart-bar title-blue mr-2"></i>
                    Daily Tests
                </h3>
                <span class="text-xs text-gray-400">Last 7 days</span>
            </div>
            <div class="chart-container">
                <canvas id="dailyChart"></canvas>
            </div>
        </div>
        
        <!-- Monthly Tests Chart -->
        <div class="card animate-fade-in-up">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-chart-line title-green mr-2"></i>
                    Monthly Tests
                </h3>
                <span class="text-xs text-gray-400">Last 6 months</span>
            </div>
            <div class="chart-container">
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- MOST REQUESTED TESTS & RECENT REQUESTS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">
        
        <!-- Most Requested Tests -->
        <div class="card animate-fade-in-up lg:col-span-1">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-trophy title-purple mr-2"></i>
                    Most Requested Tests
                </h3>
            </div>
            <div id="mostRequested">
                <?php if (count($most_requested) > 0): ?>
                    <?php foreach ($most_requested as $index => $test): ?>
                        <div class="most-requested-item">
                            <span class="rank">#<?= $index + 1 ?></span>
                            <span class="test-name"><?= htmlspecialchars($test['test_name']) ?></span>
                            <span class="count"><?= $test['count'] ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-flask"></i>
                        <p>No tests completed yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Requests -->
        <div class="card animate-fade-in-up lg:col-span-2">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-history title-blue mr-2"></i>
                    Recent Requests
                    <span class="text-sm font-normal text-gray-400">(Last 10)</span>
                </h3>
                <a href="pending_requests.php" class="text-xs text-blue-600 hover:underline">View All →</a>
            </div>
            
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Request #</th>
                            <th>Patient</th>
                            <th>Visit</th>
                            <th>Doctor</th>
                            <th>Tests</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="recentTableBody">
                        <?php if (count($recent_requests) > 0): ?>
                            <?php foreach ($recent_requests as $req): ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                                    <td class="font-mono text-xs font-semibold text-blue-600">
                                        <?= htmlspecialchars($req['request_number'] ?? 'N/A') ?>
                                    </td>
                                    <td>
                                        <div class="font-medium text-sm"><?= htmlspecialchars($req['patient_name'] ?? 'Unknown') ?></div>
                                        <div class="text-xs text-gray-400"><?= htmlspecialchars($req['patient_id'] ?? 'N/A') ?></div>
                                    </td>
                                    <td class="font-mono text-xs"><?= htmlspecialchars($req['visit_id'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($req['doctor_name'] ?? 'N/A') ?></td>
                                    <td>
                                        <?= $req['test_count'] ?? 0 ?> tests
                                        <?php if (($req['completed_count'] ?? 0) > 0 && ($req['status'] ?? '') !== 'completed'): ?>
                                            <span class="text-xs text-gray-400">(<?= $req['completed_count'] ?> done)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= 
                                            ($req['status'] ?? '') === 'pending' ? 'badge-yellow' : 
                                            (($req['status'] ?? '') === 'in_progress' ? 'badge-blue' : 
                                            (($req['status'] ?? '') === 'completed' ? 'badge-green' : 'badge-red')) 
                                        ?>">
                                            <?= ucfirst(str_replace('_', ' ', $req['status'] ?? 'Pending')) ?>
                                        </span>
                                    </td>
                                    <td class="text-sm"><?= isset($req['requested_at']) ? date('M d, Y', strtotime($req['requested_at'])) : 'N/A' ?></td>
                                    <td>
                                        <a href="view_request.php?id=<?= $req['id'] ?>" class="btn btn-outline btn-sm">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-8 text-gray-400">
                                    <i class="fas fa-flask text-3xl block mb-2"></i>
                                    <p>No laboratory requests yet</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Laboratory Dashboard
            <span class="text-gray-300 mx-2">|</span>
            Logged in as: <strong><?= htmlspecialchars($user_full_name) ?></strong>
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
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
<!-- JAVASCRIPT - WITH AUTO-UPDATE -->
<!-- ================================================================ -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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
    
    sidebarToggle?.addEventListener('click', function() {
        sidebar.classList.toggle('open');
    });
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
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
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
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
    // CHARTS - INITIAL RENDER
    // ================================================================
    var chartInstances = {
        daily: null,
        monthly: null
    };
    
    var dailyLabels = <?= json_encode($daily_labels) ?>;
    var dailyValues = <?= json_encode($daily_tests) ?>;
    var monthlyLabels = <?= json_encode($monthly_labels) ?>;
    var monthlyValues = <?= json_encode($monthly_tests) ?>;
    
    function renderDailyChart(labels, values) {
        var canvas = document.getElementById('dailyChart');
        if (!canvas) return;
        var ctx = canvas.getContext('2d');
        if (!ctx) return;
        
        if (chartInstances.daily) {
            chartInstances.daily.destroy();
            chartInstances.daily = null;
        }
        
        var isDark = htmlElement.getAttribute('data-theme') === 'dark';
        var gridColor = isDark ? '#334155' : '#E2E8F0';
        var textColor = isDark ? '#94A3B8' : '#64748B';
        
        var defaultLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        var defaultValues = [0, 0, 0, 0, 0, 0, 0];
        
        chartInstances.daily = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: (labels && labels.length > 0) ? labels : defaultLabels,
                datasets: [{
                    label: 'Tests Completed',
                    data: (values && values.length > 0) ? values : defaultValues,
                    backgroundColor: '#0B5ED7',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1, color: textColor },
                        grid: { color: gridColor }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: textColor }
                    }
                }
            }
        });
    }
    
    function renderMonthlyChart(labels, values) {
        var canvas = document.getElementById('monthlyChart');
        if (!canvas) return;
        var ctx = canvas.getContext('2d');
        if (!ctx) return;
        
        if (chartInstances.monthly) {
            chartInstances.monthly.destroy();
            chartInstances.monthly = null;
        }
        
        var isDark = htmlElement.getAttribute('data-theme') === 'dark';
        var gridColor = isDark ? '#334155' : '#E2E8F0';
        var textColor = isDark ? '#94A3B8' : '#64748B';
        
        var defaultLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
        var defaultValues = [0, 0, 0, 0, 0, 0];
        
        chartInstances.monthly = new Chart(ctx, {
            type: 'line',
            data: {
                labels: (labels && labels.length > 0) ? labels : defaultLabels,
                datasets: [{
                    label: 'Tests Completed',
                    data: (values && values.length > 0) ? values : defaultValues,
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
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1, color: textColor },
                        grid: { color: gridColor }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: textColor }
                    }
                }
            }
        });
    }
    
    // Initial chart render
    document.addEventListener('DOMContentLoaded', function() {
        renderDailyChart(dailyLabels, dailyValues);
        renderMonthlyChart(monthlyLabels, monthlyValues);
    });

    // ================================================================
    // AUTO-UPDATE - EVERY 3 SECONDS
    // ================================================================
    var updateInterval = null;
    var isUpdating = false;
    var lastHash = null;
    var updateCount = 0;
    
    function fetchAndUpdateStats() {
        if (isUpdating) return;
        isUpdating = true;
        
        updateCount++;
        
        if (updateCount % 3 === 0) {
            document.getElementById('updateBadge').innerHTML = '<i class="fas fa-sync-alt fa-spin"></i> Checking...';
        }
        
        fetch('get_lab_stats.php?t=' + new Date().getTime())
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(function(data) {
                if (data.success) {
                    if (lastHash !== data.hash) {
                        lastHash = data.hash;
                        updateDashboard(data.data);
                        document.getElementById('footerTimestamp').textContent = 'Last updated: ' + data.data.timestamp;
                        
                        if (updateCount > 1) {
                            showToast('🔄 Updated', 'Dashboard auto-updated at ' + data.data.timestamp, 'info');
                        }
                    }
                    
                    var now = new Date();
                    document.getElementById('updateBadge').innerHTML = 
                        '<i class="fas fa-check-circle" style="color:#34D399;"></i> Live ' + 
                        now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                }
                isUpdating = false;
            })
            .catch(function(error) {
                console.error('Error fetching stats:', error);
                document.getElementById('updateBadge').innerHTML = '<i class="fas fa-exclamation-circle" style="color:#EF4444;"></i> Error';
                isUpdating = false;
            });
    }
    
    function updateDashboard(data) {
        var stats = data.stats || {};
        var charts = data.charts || {};
        var lists = data.lists || {};
        
        // ================================================================
        // UPDATE STATS CARDS
        // ================================================================
        document.getElementById('statPending').textContent = stats.pending || 0;
        document.getElementById('statInProgress').textContent = stats.in_progress || 0;
        document.getElementById('statPendingCard').textContent = stats.pending || 0;
        document.getElementById('statInProgressCard').textContent = stats.in_progress || 0;
        document.getElementById('statCompletedTodayCard').textContent = stats.completed_today || 0;
        document.getElementById('statTodayTestsCard').textContent = stats.today_tests || 0;
        document.getElementById('statTotalTestsCard').textContent = Number(stats.total_tests || 0).toLocaleString();
        document.getElementById('statTotalRequestsCard').textContent = Number(stats.total_requests || 0).toLocaleString();
        document.getElementById('statCompletionRateCard').textContent = (stats.completion_rate || 0) + '%';
        
        // Average tests per request
        var avg = (stats.total_requests || 0) > 0 ? (stats.total_tests / stats.total_requests) : 0;
        document.getElementById('statAvgTestsCard').textContent = avg.toFixed(1);
        
        // ================================================================
        // UPDATE CHARTS
        // ================================================================
        if (charts.daily_labels && charts.daily_tests) {
            renderDailyChart(charts.daily_labels, charts.daily_tests);
        }
        if (charts.monthly_labels && charts.monthly_tests) {
            renderMonthlyChart(charts.monthly_labels, charts.monthly_tests);
        }
        
        // ================================================================
        // UPDATE MOST REQUESTED TESTS
        // ================================================================
        var mostRequested = document.getElementById('mostRequested');
        if (mostRequested && lists.most_requested) {
            var tests = lists.most_requested || [];
            if (tests.length > 0) {
                var html = '';
                tests.forEach(function(test, index) {
                    html += `
                        <div class="most-requested-item">
                            <span class="rank">#${index + 1}</span>
                            <span class="test-name">${escapeHtml(test.test_name)}</span>
                            <span class="count">${test.count}</span>
                        </div>
                    `;
                });
                mostRequested.innerHTML = html;
            } else {
                mostRequested.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-flask"></i>
                        <p>No tests completed yet</p>
                    </div>
                `;
            }
        }
        
        // ================================================================
        // UPDATE RECENT REQUESTS TABLE
        // ================================================================
        var tableBody = document.getElementById('recentTableBody');
        if (tableBody && lists.recent_requests) {
            var requests = lists.recent_requests || [];
            if (requests.length > 0) {
                var html = '';
                requests.forEach(function(req) {
                    var statusClass = req.status || 'pending';
                    var statusLabel = capitalize(req.status || 'Pending');
                    var badgeClass = 'badge-yellow';
                    if (statusClass === 'in_progress') badgeClass = 'badge-blue';
                    else if (statusClass === 'completed') badgeClass = 'badge-green';
                    else if (statusClass === 'cancelled') badgeClass = 'badge-red';
                    
                    var testInfo = (req.test_count || 0) + ' tests';
                    if ((req.completed_count || 0) > 0 && req.status !== 'completed') {
                        testInfo += ' <span class="text-xs text-gray-400">(' + req.completed_count + ' done)</span>';
                    }
                    
                    html += `
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                            <td class="font-mono text-xs font-semibold text-blue-600">${escapeHtml(req.request_number || 'N/A')}</td>
                            <td>
                                <div class="font-medium text-sm">${escapeHtml(req.patient_name || 'Unknown')}</div>
                                <div class="text-xs text-gray-400">${escapeHtml(req.patient_id || 'N/A')}</div>
                            </td>
                            <td class="font-mono text-xs">${escapeHtml(req.visit_id || 'N/A')}</td>
                            <td>${escapeHtml(req.doctor_name || 'N/A')}</td>
                            <td>${testInfo}</td>
                            <td>
                                <span class="badge ${badgeClass}">${statusLabel}</span>
                            </td>
                            <td class="text-sm">${formatDate(req.requested_at)}</td>
                            <td>
                                <a href="view_request.php?id=${req.id}" class="btn btn-outline btn-sm">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                    `;
                });
                tableBody.innerHTML = html;
            } else {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center py-8 text-gray-400">
                            <i class="fas fa-flask text-3xl block mb-2"></i>
                            <p>No laboratory requests yet</p>
                        </td>
                    </tr>
                `;
            }
        }
    }
    
    // ================================================================
    // HELPER FUNCTIONS
    // ================================================================
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function capitalize(text) {
        if (!text) return '';
        return text.charAt(0).toUpperCase() + text.slice(1);
    }
    
    function formatDate(datetime) {
        if (!datetime) return 'N/A';
        var d = new Date(datetime);
        if (isNaN(d.getTime())) return 'N/A';
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }
    
    function manualRefresh() {
        var btn = document.getElementById('refreshBtn');
        if (btn) {
            btn.innerHTML = '<i class="fas fa-sync-alt fa-spin"></i> Loading...';
            btn.disabled = true;
        }
        
        lastHash = null;
        fetchAndUpdateStats();
        
        setTimeout(function() {
            if (btn) {
                btn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh';
                btn.disabled = false;
            }
        }, 1000);
    }

    // ================================================================
    // START AUTO-UPDATE
    // ================================================================
    function startAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
        }
        updateInterval = setInterval(fetchAndUpdateStats, 3000);
        document.getElementById('updateBadge').innerHTML = '<i class="fas fa-sync-alt fa-spin"></i> Live mode active';
        fetchAndUpdateStats();
    }

    // ================================================================
    // STOP AUTO-UPDATE
    // ================================================================
    function stopAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
            updateInterval = null;
            document.getElementById('updateBadge').innerHTML = '<i class="fas fa-pause"></i> Paused';
        }
    }

    // ================================================================
    // VISIBILITY CHANGE - PAUSE WHEN HIDDEN
    // ================================================================
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopAutoUpdate();
        } else {
            startAutoUpdate();
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
        if (e.altKey && e.key === 'r') {
            e.preventDefault();
            manualRefresh();
        }
    });

    // ================================================================
    // INITIALIZE
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            startAutoUpdate();
        }, 2000);
    });

    // ================================================================
    // EXPOSE FOR CONSOLE
    // ================================================================
    window.LabDashboard = {
        start: startAutoUpdate,
        stop: stopAutoUpdate,
        refresh: manualRefresh,
        renderDaily: renderDailyChart,
        renderMonthly: renderMonthlyChart
    };

    console.log('%c🧪 Braick - Laboratory Dashboard', 'font-size:18px; font-weight:bold; color:#7C3AED;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($user_branch_name) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📊 Pending: <?= $pending ?> | In Progress: <?= $in_progress ?> | Completed: <?= $completed_today ?> | Today Tests: <?= $today_tests ?>', 'font-size:13px; color:#64748B;');
    console.log('%c🔄 Auto-update: Every 3 seconds (Smart - only when data changes)', 'font-size:13px; color:#34D399;');
    console.log('%c💡 Type LabDashboard.stop() or LabDashboard.start() to control', 'font-size:13px; color:#0B5ED7;');
    console.log('%c💡 Press Alt+R to refresh manually', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>