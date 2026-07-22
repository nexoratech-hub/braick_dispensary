<?php
// ================================================================
// FILE: frontend/pages/laboratory/in_progress.php
// LABORATORY - IN PROGRESS (FROM lab_tests + lab_requests)
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// IF NO SESSION, USE LAB.DODOMA (ID: 8) AS DEFAULT
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'laboratory') {
    $_SESSION['user_id'] = 8;
    $_SESSION['full_name'] = 'Lab Technician Dodoma';
    $_SESSION['role'] = 'laboratory';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'lab.dodoma';
}

$user_id = $_SESSION['user_id'] ?? 8;
$user_full_name = $_SESSION['full_name'] ?? 'Lab Technician Dodoma';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once 'C:/xampp/htdocs/dispensary_system/backend/config/database.php';
$db = Database::getInstance()->getConnection();

// ================================================================
// GET FILTERS
// ================================================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'oldest';

// ================================================================
// 1. GET IN PROGRESS TESTS FROM lab_tests (status = 'in_progress')
// ================================================================
$in_progress_tests_query = "
    SELECT 
        lt.id,
        lt.visit_id,
        lt.test_name,
        lt.test_type,
        lt.status,
        lt.created_at,
        lt.completed_at,
        lt.results,
        lt.branch_id,
        p.id as patient_id,
        p.full_name as patient_name,
        p.patient_id as patient_code,
        COALESCE(u.full_name, 'Not Assigned') as doctor_name,
        u.specialty,
        v.visit_number,
        lab.full_name as lab_technician_name,
        'test' as source_type,
        NULL as request_number,
        TIMESTAMPDIFF(MINUTE, lt.created_at, NOW()) as processing_time,
        1 as total_tests,
        CASE WHEN lt.status = 'completed' THEN 1 ELSE 0 END as completed_tests,
        CASE WHEN lt.status = 'in_progress' THEN 1 ELSE 0 END as in_progress_tests,
        0 as pending_tests,
        NULL as test_names
    FROM lab_tests lt
    JOIN visits v ON lt.visit_id = v.id
    JOIN patients p ON v.patient_id = p.id
    LEFT JOIN users u ON lt.doctor_id = u.id
    LEFT JOIN users lab ON lt.lab_technician_id = lab.id
    WHERE lt.branch_id = ? AND lt.status = 'in_progress'
";

$params = [$user_branch_id];

if (!empty($search)) {
    $in_progress_tests_query .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR lt.test_name LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($date_filter)) {
    $in_progress_tests_query .= " AND DATE(lt.created_at) = ?";
    $params[] = $date_filter;
}

$stmt = $db->prepare($in_progress_tests_query);
$stmt->execute($params);
$in_progress_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// 2. GET IN PROGRESS REQUESTS FROM lab_requests (status 'accepted' or 'in_progress')
// ================================================================
$in_progress_requests_query = "
    SELECT 
        lr.id,
        lr.request_number,
        lr.visit_id,
        lr.patient_id,
        lr.status,
        lr.requested_at,
        lr.accepted_at,
        lr.completed_at,
        lr.branch_id,
        p.id as patient_id,
        p.full_name as patient_name,
        p.patient_id as patient_code,
        COALESCE(u.full_name, 'Not Assigned') as doctor_name,
        u.specialty,
        v.visit_number,
        lab.full_name as lab_technician_name,
        'request' as source_type,
        (SELECT COUNT(*) FROM lab_request_items WHERE request_id = lr.id) as total_tests,
        (SELECT COUNT(*) FROM lab_request_items WHERE request_id = lr.id AND status = 'completed') as completed_tests,
        (SELECT COUNT(*) FROM lab_request_items WHERE request_id = lr.id AND status = 'in_progress') as in_progress_tests,
        (SELECT COUNT(*) FROM lab_request_items WHERE request_id = lr.id AND status = 'pending') as pending_tests,
        TIMESTAMPDIFF(MINUTE, lr.accepted_at, NOW()) as processing_time,
        (SELECT GROUP_CONCAT(test_name SEPARATOR ', ') FROM lab_request_items WHERE request_id = lr.id) as test_names
    FROM lab_requests lr
    JOIN patients p ON lr.patient_id = p.id
    LEFT JOIN visits v ON lr.visit_id = v.id
    LEFT JOIN users u ON lr.doctor_id = u.id
    LEFT JOIN users lab ON lr.lab_technician_id = lab.id
    WHERE lr.branch_id = ? AND lr.status IN ('accepted', 'in_progress')
";

$params2 = [$user_branch_id];

if (!empty($search)) {
    $in_progress_requests_query .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR lr.request_number LIKE ?)";
    $search_term = "%$search%";
    $params2[] = $search_term;
    $params2[] = $search_term;
    $params2[] = $search_term;
}

if (!empty($date_filter)) {
    $in_progress_requests_query .= " AND DATE(lr.requested_at) = ?";
    $params2[] = $date_filter;
}

$stmt = $db->prepare($in_progress_requests_query);
$stmt->execute($params2);
$in_progress_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// MERGE BOTH LISTS
// ================================================================
$in_progress_items = array_merge($in_progress_tests, $in_progress_requests);

// Sort by processing time (longest first)
usort($in_progress_items, function($a, $b) {
    $time_a = $a['processing_time'] ?? 0;
    $time_b = $b['processing_time'] ?? 0;
    return $time_b - $time_a;
});

// ================================================================
// GET COUNTS
// ================================================================

// Total In Progress (from lab_tests)
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ? AND status = 'in_progress'");
$stmt->execute([$user_branch_id]);
$in_progress_tests_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Total In Progress (from lab_requests)
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_requests WHERE branch_id = ? AND status IN ('accepted', 'in_progress')");
$stmt->execute([$user_branch_id]);
$in_progress_requests_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$in_progress_total = $in_progress_tests_count + $in_progress_requests_count;

// Pending (from lab_tests)
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ? AND (status IS NULL OR status = 'pending' OR status = '')");
$stmt->execute([$user_branch_id]);
$pending_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Pending (from lab_requests)
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_requests WHERE branch_id = ? AND status = 'pending'");
$stmt->execute([$user_branch_id]);
$pending_requests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$pending_total = $pending_tests + $pending_requests;

// Completed Today
$today = date('Y-m-d');
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_requests WHERE branch_id = ? AND status = 'completed' AND DATE(completed_at) = ?");
$stmt->execute([$user_branch_id, $today]);
$completed_requests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ? AND status = 'completed' AND DATE(completed_at) = ?");
$stmt->execute([$user_branch_id, $today]);
$completed_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$completed_today_total = $completed_requests + $completed_tests;

// Average processing time
$avg_processing_time = 0;
if (count($in_progress_items) > 0) {
    $total_time = 0;
    foreach ($in_progress_items as $item) {
        $total_time += ($item['processing_time'] ?? 0);
    }
    $avg_processing_time = round($total_time / count($in_progress_items));
}

// Calculate totals
$total_tests_all = 0;
$completed_tests_all = 0;
foreach ($in_progress_items as $item) {
    $total_tests_all += $item['total_tests'] ?? 1;
    $completed_tests_all += $item['completed_tests'] ?? 0;
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
include_once __DIR__ . '/../../components/laboratory_header.php';
include_once __DIR__ . '/../../components/laboratory_sidebar.php';
?>

<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 14px;
        margin-bottom: 20px;
    }
    .stat-card {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 14px 18px;
        border: 2px solid var(--border-color);
        text-align: center;
        transition: all 0.3s ease;
    }
    .stat-card:hover {
        border-color: var(--primary);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.06);
    }
    .stat-card .number {
        font-size: 1.6rem;
        font-weight: 700;
        line-height: 1.2;
    }
    .stat-card .number.in-progress { color: #0B5ED7; }
    .stat-card .number.pending { color: #D97706; }
    .stat-card .number.completed { color: #059669; }
    .stat-card .number.avg { color: #7C3AED; }
    .stat-card .label {
        font-size: 0.7rem;
        color: var(--text-secondary);
        font-weight: 500;
    }
    
    .filter-btn {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 500;
        border: 2px solid var(--border-color);
        background: transparent;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-block;
    }
    .filter-btn:hover {
        border-color: var(--primary);
        color: var(--primary);
    }
    .filter-btn.active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
    }
    
    .item-row td {
        padding: 10px 14px;
        border-bottom: 1px solid var(--border-color);
        vertical-align: middle;
    }
    .item-row:hover td {
        background: var(--table-hover);
    }
    .item-row.urgent {
        border-left: 3px solid #DC2626;
        background: rgba(220, 38, 38, 0.05);
    }
    
    .source-badge {
        font-size: 0.55rem;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: 10px;
    }
    .source-badge.test { background: #E8F0FE; color: #0B5ED7; }
    .source-badge.request { background: #FEF3C7; color: #D97706; }
    
    .status-badge-request {
        display: inline-block;
        font-size: 0.6rem;
        font-weight: 600;
        padding: 2px 12px;
        border-radius: 12px;
    }
    .status-badge-request.accepted { background: #E8F0FE; color: #0B5ED7; }
    .status-badge-request.in_progress { background: #E8F0FE; color: #0B5ED7; }
    .status-badge-request.completed { background: #D1FAE5; color: #059669; }
    .status-badge-request.pending { background: #FEF3C7; color: #D97706; }
    .status-badge-request.cancelled { background: #FEE2E2; color: #DC2626; }
    
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 5px 12px;
        border-radius: 6px;
        font-weight: 600;
        font-size: 0.7rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
    }
    .btn-blue { background: #0B5ED7; color: white; }
    .btn-blue:hover { background: #0A4CA8; transform: scale(1.05); }
    .btn-green { background: #059669; color: white; }
    .btn-green:hover { background: #047857; transform: scale(1.05); }
    .btn-outline { background: transparent; color: var(--text-secondary); border: 2px solid var(--border-color); }
    .btn-outline:hover { background: var(--bg-body); border-color: #0B5ED7; color: #0B5ED7; }
    .btn-sm { padding: 3px 8px; font-size: 0.65rem; border-radius: 4px; }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.82rem;
        min-width: 900px;
    }
    .data-table thead th {
        text-align: left;
        padding: 8px 12px;
        font-weight: 700;
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: white;
        background: #0B5ED7;
        border-bottom: 3px solid #0A4CA8;
        white-space: nowrap;
        position: sticky;
        top: 0;
        z-index: 10;
    }
    .data-table td {
        padding: 8px 12px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        vertical-align: middle;
    }
    
    .table-wrap {
        overflow-x: auto;
        max-height: 500px;
        overflow-y: auto;
    }
    .table-wrap::-webkit-scrollbar { width: 5px; height: 5px; }
    .table-wrap::-webkit-scrollbar-track { background: var(--bg-body); border-radius: 4px; }
    .table-wrap::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 4px; }
    
    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: var(--text-secondary);
    }
    .empty-state i {
        font-size: 3rem;
        color: var(--border-color);
        display: block;
        margin-bottom: 10px;
    }
    
    .update-badge {
        font-size: 0.65rem;
        color: var(--text-secondary);
        background: var(--bg-body);
        padding: 2px 12px;
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .progress-bar {
        height: 4px;
        background: #E2E8F0;
        border-radius: 2px;
        overflow: hidden;
        width: 80px;
        display: inline-block;
    }
    .progress-bar .fill {
        height: 100%;
        background: #0B5ED7;
        border-radius: 2px;
        transition: width 0.5s ease;
    }
    .progress-bar .fill.completed { background: #059669; }
    
    [data-theme="dark"] .progress-bar { background: #334155; }
    
    .processing-time {
        font-size: 0.7rem;
        font-weight: 500;
    }
    .processing-time.long { color: #DC2626; }
    .processing-time.medium { color: #D97706; }
    
    .urgent-badge {
        font-size: 0.55rem;
        font-weight: 700;
        background: #DC2626;
        color: white;
        padding: 2px 8px;
        border-radius: 10px;
        animation: pulse-badge 2s infinite;
        display: inline-block;
        margin-left: 4px;
    }
    
    @keyframes pulse-badge {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.6; transform: scale(0.95); }
    }
    
    .quick-stats {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        align-items: center;
    }
    .quick-stat {
        font-size: 0.7rem;
        padding: 2px 10px;
        border-radius: 12px;
        background: var(--bg-body);
        color: var(--text-secondary);
    }
    .quick-stat .num { font-weight: 600; color: var(--primary); }
    
    .action-buttons {
        display: flex;
        gap: 4px;
        flex-wrap: wrap;
    }
    
    @media (max-width: 768px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .data-table { font-size: 0.7rem; min-width: 750px; }
        .filter-group { flex-wrap: wrap; }
        .action-buttons { flex-direction: column; }
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
            <input type="text" id="searchInput" placeholder="Search in-progress..." value="<?= htmlspecialchars($search) ?>">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    <div class="flex items-center gap-3">
        <span class="branch-badge"><i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($user_branch_name) ?></span>
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
                <i class="fas fa-spinner mr-2" style="color: #0B5ED7;"></i> In Progress
                <span class="role-badge ml-2">LABORATORY</span>
                <span class="update-badge ml-2" id="updateBadge">
                    <i class="fas fa-sync-alt fa-spin"></i> Live
                </span>
            </h1>
            <p class="page-subtitle">
                Manage all laboratory tests currently being processed
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-spinner mr-1"></i> <?= $in_progress_total ?> In Progress
                </span>
                <span class="ml-2 inline-flex bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full text-xs border border-yellow-200">
                    <i class="fas fa-clock mr-1"></i> <?= $pending_total ?> Pending
                </span>
                <span class="ml-2 inline-flex bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs border border-green-200">
                    <i class="fas fa-check-circle mr-1"></i> <?= $completed_today_total ?> Completed Today
                </span>
            </p>
        </div>
        <div>
            <a href="pending_requests.php" class="btn btn-outline btn-sm">
                <i class="fas fa-clock"></i> Pending
            </a>
            <a href="dashboard.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-grid">
        <div class="stat-card">
            <p class="number in-progress" id="statInProgress"><?= $in_progress_total ?></p>
            <p class="label">🔬 In Progress</p>
        </div>
        <div class="stat-card">
            <p class="number pending" id="statPending"><?= $pending_total ?></p>
            <p class="label">⏳ Pending</p>
        </div>
        <div class="stat-card">
            <p class="number completed" id="statCompletedToday"><?= $completed_today_total ?></p>
            <p class="label">✅ Completed Today</p>
        </div>
        <div class="stat-card">
            <p class="number avg" id="statAvgTime"><?= $avg_processing_time ?> min</p>
            <p class="label">⏱️ Avg Processing</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <div class="flex flex-wrap items-center gap-3 filter-group">
            <span class="text-sm font-medium text-gray-600 mr-2">Sort by:</span>
            <a href="in_progress.php?sort=oldest&search=<?= urlencode($search) ?>&date=<?= $date_filter ?>" 
               class="filter-btn <?= $sort_by === 'oldest' || empty($sort_by) ? 'active' : '' ?>">⏳ Oldest First</a>
            <a href="in_progress.php?sort=newest&search=<?= urlencode($search) ?>&date=<?= $date_filter ?>" 
               class="filter-btn <?= $sort_by === 'newest' ? 'active' : '' ?>">🆕 Newest First</a>
            <a href="in_progress.php?sort=longest&search=<?= urlencode($search) ?>&date=<?= $date_filter ?>" 
               class="filter-btn <?= $sort_by === 'longest' ? 'active' : '' ?>">⏱️ Longest Processing</a>
            
            <span class="text-sm font-medium text-gray-600 ml-4 mr-2">Date:</span>
            <input type="date" id="dateFilter" value="<?= $date_filter ?>"
                   onchange="window.location.href='in_progress.php?date='+this.value+'&sort=<?= $sort_by ?>&search=<?= urlencode($search) ?>'"
                   class="form-control" style="width:auto;padding:4px 10px;font-size:0.8rem;">
            
            <?php if (!empty($search)): ?>
                <a href="in_progress.php" class="btn btn-outline btn-sm">
                    <i class="fas fa-times"></i> Clear
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- IN PROGRESS TABLE -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-blue mr-2"></i> In Progress Items
                <span class="text-sm font-normal text-gray-400" id="itemCount">(<?= count($in_progress_items) ?>)</span>
            </h3>
            <div class="quick-stats">
                <span class="quick-stat">Total Tests: <span class="num" id="totalTests"><?= $total_tests_all ?></span></span>
                <span class="quick-stat">Completed: <span class="num" id="completedTests"><?= $completed_tests_all ?></span></span>
                <span class="text-sm text-gray-400">Scroll to view</span>
            </div>
        </div>
        
        <div class="table-wrap">
            <table class="data-table" id="inProgressTable">
                <thead>
                    <tr>
                        <th style="border-radius: 8px 0 0 0;">#</th>
                        <th>Item</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Tests</th>
                        <th>Progress</th>
                        <th>Source</th>
                        <th>Processing</th>
                        <th>Date</th>
                        <th style="border-radius: 0 8px 0 0;">Actions</th>
                    </tr>
                </thead>
                <tbody id="inProgressTableBody">
                    <?php if (count($in_progress_items) > 0): ?>
                        <?php $i = 1; foreach ($in_progress_items as $item): 
                            $is_test = ($item['source_type'] === 'test');
                            $total = $item['total_tests'] ?? 1;
                            $completed = $item['completed_tests'] ?? 0;
                            $in_progress = $item['in_progress_tests'] ?? 0;
                            $pending = $item['pending_tests'] ?? 0;
                            $progress = $total > 0 ? round(($completed / $total) * 100) : 0;
                            $processing = $item['processing_time'] ?? 0;
                            $processing_class = $processing > 60 ? 'long' : ($processing > 30 ? 'medium' : '');
                            $processing_text = $processing < 1 ? 'Just started' : ($processing < 60 ? $processing . ' min' : floor($processing / 60) . 'h ' . ($processing % 60) . 'm');
                            $is_urgent = $processing > 45;
                            $item_name = $is_test ? $item['test_name'] : ($item['request_number'] ?? 'N/A');
                            $source_label = $is_test ? '🔬 Test' : '📋 Request';
                            $source_class = $is_test ? 'test' : 'request';
                            
                            if ($is_test) {
                                $view_link = "view_test.php?id=" . $item['id'];
                                $complete_link = "update_test_status.php?action=complete_test&id=" . $item['id'];
                            } else {
                                $view_link = "view_request.php?id=" . $item['id'];
                                $complete_link = "update_test_status.php?action=complete&id=" . $item['id'];
                            }
                        ?>
                            <tr class="item-row <?= $is_urgent ? 'urgent' : '' ?>" data-id="<?= $item['id'] ?>">
                                <td><?= $i++ ?></td>
                                <td>
                                    <div class="font-medium text-sm"><?= htmlspecialchars($item_name) ?></div>
                                    <?php if (!$is_test && isset($item['test_names']) && !empty($item['test_names'])): ?>
                                        <div class="text-xs text-gray-400"><?= htmlspecialchars(substr($item['test_names'], 0, 40)) ?>...</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="font-medium text-sm"><?= htmlspecialchars($item['patient_name']) ?></div>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($item['patient_code'] ?? 'N/A') ?></div>
                                </td>
                                <td>
                                    <div class="text-sm"><?= htmlspecialchars($item['doctor_name']) ?></div>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($item['specialty'] ?? 'GP') ?></div>
                                </td>
                                <td class="text-sm">
                                    <?= $total ?> test(s)
                                    <?php if ($pending > 0): ?>
                                        <span class="text-xs text-yellow-600">(<?= $pending ?> pending)</span>
                                    <?php endif; ?>
                                    <?php if ($in_progress > 0): ?>
                                        <span class="text-xs text-blue-600">(<?= $in_progress ?> in progress)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-medium"><?= $progress ?>%</span>
                                        <div class="progress-bar">
                                            <div class="fill <?= $progress == 100 ? 'completed' : '' ?>" style="width: <?= $progress ?>%;"></div>
                                        </div>
                                    </div>
                                    <span class="text-xs text-gray-400"><?= $completed ?> / <?= $total ?> done</span>
                                </td>
                                <td>
                                    <span class="source-badge <?= $source_class ?>"><?= $source_label ?></span>
                                </td>
                                <td>
                                    <span class="processing-time <?= $processing_class ?>">
                                        <?= $processing_text ?>
                                        <?php if ($is_urgent): ?>
                                            <span class="urgent-badge">URGENT</span>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td class="text-xs">
                                    <?php 
                                        $date = $item['created_at'] ?? $item['requested_at'] ?? '';
                                        if ($date) echo date('M d, Y h:i A', strtotime($date));
                                        else echo 'N/A';
                                    ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="<?= $view_link ?>" class="btn btn-blue btn-sm" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="<?= $complete_link ?>" 
                                           class="btn btn-green btn-sm" title="Complete"
                                           onclick="return confirm('Complete this item? Results will be sent to the doctor.')">
                                            <i class="fas fa-check"></i> Complete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10">
                                <div class="empty-state">
                                    <i class="fas fa-check-circle" style="color: #059669; font-size: 3rem;"></i>
                                    <p>No in-progress items found</p>
                                    <p class="text-sm mt-1">All tests have been completed or none have been started</p>
                                    <a href="pending_requests.php" class="btn btn-blue btn-sm mt-3">
                                        <i class="fas fa-clock"></i> View Pending Tests
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Card Footer -->
        <div class="card-footer">
            <span class="text-sm text-gray-500">
                <i class="fas fa-flask mr-1"></i> 
                Showing <strong id="recordCount"><?= count($in_progress_items) ?></strong> in-progress item(s)
            </span>
            <span class="text-sm text-gray-500">
                <i class="fas fa-store-alt mr-1"></i> 
                Branch: <strong><?= htmlspecialchars($user_branch_name) ?></strong>
            </span>
            <span class="text-sm text-gray-500">
                <i class="fas fa-clock mr-1"></i> 
                <span id="footerTimestamp">Last updated: <?= date('h:i:s A') ?></span>
            </span>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- QUICK ACTIONS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-5">
        <a href="pending_requests.php" class="card text-center hover:border-blue-500 transition">
            <i class="fas fa-clock text-yellow-600 text-2xl block mb-2"></i>
            <span class="text-sm font-medium">Pending Tests</span>
            <p class="text-xs text-gray-400"><?= $pending_total ?> tests waiting</p>
        </a>
        <a href="completed_requests.php" class="card text-center hover:border-green-500 transition">
            <i class="fas fa-check-circle text-green-600 text-2xl block mb-2"></i>
            <span class="text-sm font-medium">Completed</span>
            <p class="text-xs text-gray-400"><?= $completed_today_total ?> completed today</p>
        </a>
        <a href="dashboard.php" class="card text-center hover:border-purple-500 transition">
            <i class="fas fa-chart-bar text-purple-600 text-2xl block mb-2"></i>
            <span class="text-sm font-medium">Dashboard</span>
            <p class="text-xs text-gray-400">View all statistics</p>
        </a>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            In Progress
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
        document.getElementById('currentDateTime').textContent = dateStr + ' • ' + timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        var sort = '<?= $sort_by ?>';
        var date = '<?= $date_filter ?>';
        window.location.href = 'in_progress.php?search=' + encodeURIComponent(query) + '&sort=' + sort + '&date=' + date;
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
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
    // CHECK FOR SUCCESS/ERROR MESSAGES
    // ================================================================
    (function() {
        var urlParams = new URLSearchParams(window.location.search);
        var success = urlParams.get('success');
        var message = urlParams.get('message');
        
        if (success === '1' && message) {
            setTimeout(function() {
                showToast('✅ Success', decodeURIComponent(message), 'success');
            }, 500);
        } else if (success === '0' && message) {
            setTimeout(function() {
                showToast('❌ Error', decodeURIComponent(message), 'error');
            }, 500);
        }
    })();

    console.log('%c🧪 Braick - In Progress (From Both Tables)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📊 In Progress: <?= $in_progress_total ?> | Pending: <?= $pending_total ?> | Completed Today: <?= $completed_today_total ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c⏱️ Avg Processing: <?= $avg_processing_time ?> min', 'font-size:13px; color:#7C3AED;');
    console.log('%c📋 Total Items: <?= count($in_progress_items) ?>', 'font-size:13px; color:#64748B;');
</script>

</body>
</html>