<?php
// ================================================================
// FILE: frontend/pages/laboratory/completed_requests.php
// LABORATORY - COMPLETED REQUESTS (FROM lab_requests + lab_tests)
// WITH REAL-TIME AUTO-UPDATE (3 SECONDS)
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
$patient_filter = isset($_GET['patient']) ? (int)$_GET['patient'] : 0;
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// ================================================================
// 1. COMPLETED REQUESTS FROM lab_requests (status = 'completed')
// ================================================================
$completed_requests_query = "
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
        p.patient_id as patient_number,
        COALESCE(u.full_name, 'Not Assigned') as doctor_name,
        u.specialty,
        v.visit_number,
        lab.full_name as lab_technician_name,
        'request' as source_type,
        (SELECT COUNT(*) FROM lab_request_items WHERE request_id = lr.id) as total_tests,
        (SELECT COUNT(*) FROM lab_request_items WHERE request_id = lr.id AND status = 'completed') as completed_tests,
        (SELECT GROUP_CONCAT(test_name SEPARATOR ', ') FROM lab_request_items WHERE request_id = lr.id) as test_names
    FROM lab_requests lr
    JOIN patients p ON lr.patient_id = p.id
    LEFT JOIN visits v ON lr.visit_id = v.id
    LEFT JOIN users u ON lr.doctor_id = u.id
    LEFT JOIN users lab ON lr.lab_technician_id = lab.id
    WHERE lr.branch_id = ? AND lr.status = 'completed'
";

$params = [$user_branch_id];

if (!empty($search)) {
    $completed_requests_query .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR lr.request_number LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($date_filter)) {
    $completed_requests_query .= " AND DATE(lr.completed_at) = ?";
    $params[] = $date_filter;
}

if ($patient_filter > 0) {
    $completed_requests_query .= " AND p.id = ?";
    $params[] = $patient_filter;
}

if ($filter === 'today') {
    $completed_requests_query .= " AND DATE(lr.completed_at) = CURDATE()";
} elseif ($filter === 'week') {
    $completed_requests_query .= " AND lr.completed_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
} elseif ($filter === 'month') {
    $completed_requests_query .= " AND lr.completed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
}

$completed_requests_query .= " ORDER BY lr.completed_at DESC";

$stmt = $db->prepare($completed_requests_query);
$stmt->execute($params);
$completed_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// 2. COMPLETED TESTS FROM lab_tests (status = 'completed')
// ================================================================
$completed_tests_query = "
    SELECT 
        lt.id,
        lt.visit_id,
        lt.test_name,
        lt.test_type,
        lt.status,
        lt.created_at,
        lt.completed_at,
        lt.results,
        lt.notes,
        lt.branch_id,
        p.id as patient_id,
        p.full_name as patient_name,
        p.patient_id as patient_number,
        COALESCE(u.full_name, 'Not Assigned') as doctor_name,
        u.specialty,
        v.visit_number,
        lab.full_name as lab_technician_name,
        'test' as source_type,
        1 as total_tests,
        CASE WHEN lt.status = 'completed' THEN 1 ELSE 0 END as completed_tests,
        lt.test_name as test_names
    FROM lab_tests lt
    JOIN visits v ON lt.visit_id = v.id
    JOIN patients p ON v.patient_id = p.id
    LEFT JOIN users u ON lt.doctor_id = u.id
    LEFT JOIN users lab ON lt.lab_technician_id = lab.id
    WHERE lt.branch_id = ? AND lt.status = 'completed'
";

$params2 = [$user_branch_id];

if (!empty($search)) {
    $completed_tests_query .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR lt.test_name LIKE ?)";
    $search_term = "%$search%";
    $params2[] = $search_term;
    $params2[] = $search_term;
    $params2[] = $search_term;
}

if (!empty($date_filter)) {
    $completed_tests_query .= " AND DATE(lt.completed_at) = ?";
    $params2[] = $date_filter;
}

if ($patient_filter > 0) {
    $completed_tests_query .= " AND p.id = ?";
    $params2[] = $patient_filter;
}

if ($filter === 'today') {
    $completed_tests_query .= " AND DATE(lt.completed_at) = CURDATE()";
} elseif ($filter === 'week') {
    $completed_tests_query .= " AND lt.completed_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
} elseif ($filter === 'month') {
    $completed_tests_query .= " AND lt.completed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
}

$completed_tests_query .= " ORDER BY lt.completed_at DESC";

$stmt = $db->prepare($completed_tests_query);
$stmt->execute($params2);
$completed_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// MERGE BOTH LISTS
// ================================================================
$completed_items = array_merge($completed_requests, $completed_tests);

// Sort by completed_at (newest first)
usort($completed_items, function($a, $b) {
    $time_a = $a['completed_at'] ?? $a['created_at'] ?? 0;
    $time_b = $b['completed_at'] ?? $b['created_at'] ?? 0;
    return strtotime($time_b) - strtotime($time_a);
});

// ================================================================
// GET STATISTICS
// ================================================================

// Total Completed Requests
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_requests WHERE branch_id = ? AND status = 'completed'");
$stmt->execute([$user_branch_id]);
$completed_requests_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Total Completed Tests
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ? AND status = 'completed'");
$stmt->execute([$user_branch_id]);
$completed_tests_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$total_completed = $completed_requests_count + $completed_tests_count;

// Completed Today
$today = date('Y-m-d');
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_requests WHERE branch_id = ? AND status = 'completed' AND DATE(completed_at) = ?");
$stmt->execute([$user_branch_id, $today]);
$requests_today = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ? AND status = 'completed' AND DATE(completed_at) = ?");
$stmt->execute([$user_branch_id, $today]);
$tests_today = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$completed_today = $requests_today + $tests_today;

// This Week
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_requests WHERE branch_id = ? AND status = 'completed' AND completed_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$stmt->execute([$user_branch_id]);
$requests_week = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ? AND status = 'completed' AND completed_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$stmt->execute([$user_branch_id]);
$tests_week = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$completed_week = $requests_week + $tests_week;

// This Month
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_requests WHERE branch_id = ? AND status = 'completed' AND completed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
$stmt->execute([$user_branch_id]);
$requests_month = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ? AND status = 'completed' AND completed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
$stmt->execute([$user_branch_id]);
$tests_month = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$completed_month = $requests_month + $tests_month;

// Completion Rate
$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_requests WHERE branch_id = ?");
$stmt->execute([$user_branch_id]);
$total_requests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE branch_id = ?");
$stmt->execute([$user_branch_id]);
$total_tests_all = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$total_all = $total_requests + $total_tests_all;
$completion_rate = $total_all > 0 ? round(($total_completed / $total_all) * 100, 1) : 0;

// ================================================================
// GET PATIENTS LIST FOR FILTER
// ================================================================
$patients_list = [];
$stmt = $db->prepare("
    SELECT DISTINCT p.id, p.full_name, p.patient_id
    FROM lab_requests lr
    JOIN patients p ON lr.patient_id = p.id
    WHERE lr.branch_id = ? AND lr.status = 'completed'
    UNION
    SELECT DISTINCT p.id, p.full_name, p.patient_id
    FROM lab_tests lt
    JOIN visits v ON lt.visit_id = v.id
    JOIN patients p ON v.patient_id = p.id
    WHERE lt.branch_id = ? AND lt.status = 'completed'
");
$stmt->execute([$user_branch_id, $user_branch_id]);
$patients_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    .stat-card .number.total { color: #059669; }
    .stat-card .number.today { color: #0B5ED7; }
    .stat-card .number.week { color: #7C3AED; }
    .stat-card .number.month { color: #D97706; }
    .stat-card .number.rate { color: #0D9488; }
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
    
    .source-badge {
        font-size: 0.55rem;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: 10px;
    }
    .source-badge.test { background: #E8F0FE; color: #0B5ED7; }
    .source-badge.request { background: #FEF3C7; color: #D97706; }
    
    .status-badge-completed {
        display: inline-block;
        font-size: 0.6rem;
        font-weight: 600;
        padding: 2px 12px;
        border-radius: 12px;
        background: #D1FAE5;
        color: #059669;
    }
    
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
    
    .result-preview {
        max-width: 150px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        font-size: 0.75rem;
        color: var(--text-secondary);
    }
    
    .result-preview.has-result {
        color: var(--text-primary);
        font-weight: 500;
    }
    
    .action-buttons {
        display: flex;
        gap: 4px;
        flex-wrap: wrap;
    }
    
    .form-control {
        padding: 4px 10px;
        border: 2px solid var(--border-color);
        border-radius: 8px;
        font-size: 0.8rem;
        background: var(--bg-card);
        color: var(--text-primary);
        outline: none;
        transition: all 0.3s ease;
    }
    .form-control:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
    }
    
    .test-count-badge {
        font-size: 0.6rem;
        background: var(--bg-body);
        padding: 1px 8px;
        border-radius: 10px;
        color: var(--text-secondary);
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
            <input type="text" id="searchInput" placeholder="Search completed..." value="<?= htmlspecialchars($search) ?>">
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
                <i class="fas fa-check-circle mr-2" style="color: #059669;"></i> Completed Requests
                <span class="role-badge ml-2">LABORATORY</span>
                <span class="update-badge ml-2" id="updateBadge">
                    <i class="fas fa-sync-alt fa-spin"></i> Live
                </span>
            </h1>
            <p class="page-subtitle">
                View all completed laboratory requests and tests
                <span class="ml-2 inline-flex bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs border border-green-200">
                    <i class="fas fa-check-circle mr-1"></i> <?= $total_completed ?> Total Completed
                </span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-calendar-day mr-1"></i> <?= $completed_today ?> Today
                </span>
                <span class="ml-2 inline-flex bg-teal-100 text-teal-700 px-3 py-1 rounded-full text-xs border border-teal-200">
                    <i class="fas fa-percentage mr-1"></i> <?= $completion_rate ?>% Completion Rate
                </span>
            </p>
        </div>
        <div>
            <a href="dashboard.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <a href="javascript:window.print()" class="btn btn-blue btn-sm">
                <i class="fas fa-print"></i> Print
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-grid">
        <div class="stat-card">
            <p class="number total" id="statTotal"><?= $total_completed ?></p>
            <p class="label">✅ Total Completed</p>
        </div>
        <div class="stat-card">
            <p class="number today" id="statToday"><?= $completed_today ?></p>
            <p class="label">📅 Today</p>
        </div>
        <div class="stat-card">
            <p class="number week" id="statWeek"><?= $completed_week ?></p>
            <p class="label">📆 This Week</p>
        </div>
        <div class="stat-card">
            <p class="number month" id="statMonth"><?= $completed_month ?></p>
            <p class="label">📈 This Month</p>
        </div>
        <div class="stat-card">
            <p class="number rate" id="statRate"><?= $completion_rate ?>%</p>
            <p class="label">📊 Completion Rate</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <div class="flex flex-wrap items-center gap-3 filter-group">
            <span class="text-sm font-medium text-gray-600 mr-2">Filter:</span>
            <a href="completed_requests.php" class="filter-btn <?= $filter === 'all' || empty($filter) ? 'active' : '' ?>">All</a>
            <a href="completed_requests.php?filter=today" class="filter-btn <?= $filter === 'today' ? 'active' : '' ?>">Today</a>
            <a href="completed_requests.php?filter=week" class="filter-btn <?= $filter === 'week' ? 'active' : '' ?>">This Week</a>
            <a href="completed_requests.php?filter=month" class="filter-btn <?= $filter === 'month' ? 'active' : '' ?>">This Month</a>
            
            <span class="text-sm font-medium text-gray-600 ml-4 mr-2">Date:</span>
            <input type="date" id="dateFilter" value="<?= $date_filter ?>"
                   onchange="window.location.href='completed_requests.php?date='+this.value+'&filter=<?= $filter ?>&search=<?= urlencode($search) ?>&patient=<?= $patient_filter ?>'"
                   class="form-control" style="width:auto;">
            
            <?php if (!empty($patients_list)): ?>
                <span class="text-sm font-medium text-gray-600 ml-4 mr-2">Patient:</span>
                <select id="patientFilter" class="form-control" style="width:auto;min-width:120px;"
                        onchange="window.location.href='completed_requests.php?patient='+this.value+'&filter=<?= $filter ?>&date=<?= $date_filter ?>&search=<?= urlencode($search) ?>'">
                    <option value="0">All Patients</option>
                    <?php foreach ($patients_list as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $patient_filter == $p['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['full_name']) ?> (<?= htmlspecialchars($p['patient_id']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
            
            <?php if (!empty($search) || !empty($date_filter) || $patient_filter > 0): ?>
                <a href="completed_requests.php" class="btn btn-outline btn-sm">
                    <i class="fas fa-times"></i> Clear
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- COMPLETED TABLE -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-blue mr-2"></i> Completed Items
                <span class="text-sm font-normal text-gray-400" id="itemCount">(<?= count($completed_items) ?>)</span>
            </h3>
            <span class="text-sm text-gray-400">Scroll to view all</span>
        </div>
        
        <div class="table-wrap">
            <table class="data-table" id="completedTable">
                <thead>
                    <tr>
                        <th style="border-radius: 8px 0 0 0;">#</th>
                        <th>Item</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Tests</th>
                        <th>Source</th>
                        <th>Status</th>
                        <th>Completed</th>
                        <th style="border-radius: 0 8px 0 0;">Actions</th>
                    </tr>
                </thead>
                <tbody id="completedTableBody">
                    <?php if (count($completed_items) > 0): ?>
                        <?php $i = 1; foreach ($completed_items as $item): 
                            $is_test = ($item['source_type'] === 'test');
                            $item_name = $is_test ? ($item['test_name'] ?? 'N/A') : ($item['request_number'] ?? 'N/A');
                            $source_label = $is_test ? '🔬 Test' : '📋 Request';
                            $source_class = $is_test ? 'test' : 'request';
                            $total_tests = $item['total_tests'] ?? 1;
                            $completed_tests = $item['completed_tests'] ?? 0;
                            $completed_date = $item['completed_at'] ?? $item['created_at'] ?? '';
                            $patient_name = $item['patient_name'] ?? 'Unknown';
                            $patient_number = $item['patient_number'] ?? $item['patient_id'] ?? 'N/A';
                            $doctor_name = $item['doctor_name'] ?? 'Not Assigned';
                            $specialty = $item['specialty'] ?? 'GP';
                            $has_result = !empty($item['results']);
                            
                            if ($is_test) {
                                $view_link = "view_test.php?id=" . $item['id'];
                                $print_link = "view_test.php?id=" . $item['id'] . "&print=1";
                            } else {
                                $view_link = "view_results.php?request_id=" . $item['id'];
                                $print_link = "view_results.php?request_id=" . $item['id'] . "&print=1";
                            }
                        ?>
                            <tr class="item-row" data-id="<?= $item['id'] ?>">
                                <td><?= $i++ ?></td>
                                <td>
                                    <div class="font-medium text-sm"><?= htmlspecialchars($item_name) ?></div>
                                    <?php if (!$is_test && isset($item['test_names']) && !empty($item['test_names'])): ?>
                                        <div class="text-xs text-gray-400"><?= htmlspecialchars(substr($item['test_names'], 0, 40)) ?>...</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="font-medium text-sm"><?= htmlspecialchars($patient_name) ?></div>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($patient_number) ?></div>
                                </td>
                                <td>
                                    <div class="text-sm"><?= htmlspecialchars($doctor_name) ?></div>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($specialty) ?></div>
                                </td>
                                <td>
                                    <span class="test-count-badge">
                                        <?= $completed_tests ?> / <?= $total_tests ?> tests
                                    </span>
                                    <?php if ($total_tests == $completed_tests && $total_tests > 0): ?>
                                        <span class="text-xs text-green-600">✅ All done</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="source-badge <?= $source_class ?>"><?= $source_label ?></span>
                                </td>
                                <td>
                                    <span class="status-badge-completed">✅ Completed</span>
                                </td>
                                <td class="text-xs">
                                    <?php if ($completed_date): ?>
                                        <?= date('M d, Y', strtotime($completed_date)) ?>
                                        <br><span class="text-green-600"><?= date('h:i A', strtotime($completed_date)) ?></span>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="<?= $view_link ?>" class="btn btn-blue btn-sm" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="<?= $print_link ?>" class="btn btn-outline btn-sm" title="Print" style="border-color:#059669;color:#059669;" target="_blank">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9">
                                <div class="empty-state">
                                    <i class="fas fa-check-circle" style="color: #059669; font-size: 3rem;"></i>
                                    <p>No completed requests found</p>
                                    <p class="text-sm mt-1">Completed requests will appear here</p>
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
                Showing <strong id="recordCount"><?= count($completed_items) ?></strong> completed item(s)
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
        <a href="pending_requests.php" class="card text-center hover:border-yellow-500 transition">
            <i class="fas fa-clock text-yellow-600 text-2xl block mb-2"></i>
            <span class="text-sm font-medium">Pending Requests</span>
            <p class="text-xs text-gray-400">View waiting tests</p>
        </a>
        <a href="in_progress.php" class="card text-center hover:border-blue-500 transition">
            <i class="fas fa-spinner text-blue-600 text-2xl block mb-2"></i>
            <span class="text-sm font-medium">In Progress</span>
            <p class="text-xs text-gray-400">View ongoing tests</p>
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
            Completed Requests
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
        var filter = '<?= $filter ?>';
        var date = '<?= $date_filter ?>';
        var patient = '<?= $patient_filter ?>';
        window.location.href = 'completed_requests.php?search=' + encodeURIComponent(query) + '&filter=' + filter + '&date=' + date + '&patient=' + patient;
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

    console.log('%c🧪 Braick - Completed Requests', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c📊 Total: <?= $total_completed ?> | Today: <?= $completed_today ?> | Week: <?= $completed_week ?> | Month: <?= $completed_month ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📈 Completion Rate: <?= $completion_rate ?>%', 'font-size:13px; color:#7C3AED;');
    console.log('%c📋 Showing: <?= count($completed_items) ?> items', 'font-size:13px; color:#64748B;');
</script>

</body>
</html>