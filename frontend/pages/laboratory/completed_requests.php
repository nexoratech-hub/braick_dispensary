<?php
// ================================================================
// FILE: frontend/pages/laboratory/completed_requests.php
// LABORATORY - COMPLETED REQUESTS
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// INCLUDE CONFIG
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

// ================================================================
// SESSION - Default to lab.anna
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'laboratory') {
    $_SESSION['user_id'] = 4;
    $_SESSION['full_name'] = 'Anna Mushi';
    $_SESSION['role'] = 'laboratory';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'lab.anna';
    $_SESSION['is_admin'] = false;
}

$user_id = $_SESSION['user_id'] ?? 4;
$user_full_name = $_SESSION['full_name'] ?? 'Anna Mushi';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';

$db = getDB();

// ================================================================
// GET FILTERS
// ================================================================
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'completed';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// ================================================================
// GET COMPLETED REQUESTS
// ================================================================
$query = "
    SELECT lr.*, 
           p.full_name as patient_name, p.patient_id, p.phone,
           u.full_name as doctor_name,
           lab.full_name as technician_name,
           (SELECT COUNT(*) FROM lab_request_items WHERE request_id = lr.id) as test_count,
           (SELECT COUNT(*) FROM lab_request_items WHERE request_id = lr.id AND status = 'completed') as completed_count,
           (SELECT GROUP_CONCAT(test_name SEPARATOR ', ') FROM lab_request_items WHERE request_id = lr.id) as test_names
    FROM lab_requests lr
    LEFT JOIN patients p ON lr.patient_id = p.id
    LEFT JOIN users u ON lr.doctor_id = u.id
    LEFT JOIN users lab ON lr.lab_technician_id = lab.id
    WHERE lr.branch_id = ? AND lr.status = 'completed'
";

if (!empty($search)) {
    $query .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR lr.request_number LIKE ? OR lr.test_names LIKE ?)";
}

if (!empty($date_from) && !empty($date_to)) {
    $query .= " AND DATE(lr.completed_at) BETWEEN ? AND ?";
}

$query .= " ORDER BY lr.completed_at DESC";

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

$stmt->execute($params);
$requests = $stmt->fetchAll();

// ================================================================
// GET STATUS COUNTS
// ================================================================
$counts = [];
$statuses = ['pending', 'in_progress', 'completed', 'cancelled'];
foreach ($statuses as $status) {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_requests WHERE branch_id = ? AND status = ?");
    $stmt->execute([$user_branch_id, $status]);
    $counts[$status] = $stmt->fetch()['count'] ?? 0;
}

// ================================================================
// GET MONTHLY COMPLETED STATS
// ================================================================
$monthly_stats = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('M', strtotime("-$i months"));
    $monthly_stats[$month] = 0;
    
    $start = date('Y-m-01', strtotime("-$i months"));
    $end = date('Y-m-t', strtotime("-$i months"));
    
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM lab_requests 
        WHERE branch_id = ? AND status = 'completed' 
        AND DATE(completed_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$user_branch_id, $start, $end]);
    $monthly_stats[$month] = $stmt->fetch()['count'] ?? 0;
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
    /* ================================================================
       COMPLETED REQUESTS STYLES
       ================================================================ */
    
    .filter-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 16px;
        border-bottom: 2px solid var(--border-color);
        padding-bottom: 12px;
    }
    
    .filter-tab {
        padding: 6px 16px;
        border-radius: 20px;
        font-size: 0.78rem;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.3s ease;
        background: var(--bg-body);
        color: var(--text-secondary);
        border: 2px solid transparent;
    }
    
    .filter-tab:hover {
        background: var(--primary-bg);
        color: var(--primary);
    }
    
    .filter-tab.active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
    }
    
    .filter-tab .count {
        background: rgba(255,255,255,0.2);
        padding: 0 6px;
        border-radius: 10px;
        font-size: 0.6rem;
        margin-left: 4px;
    }
    
    .filter-tab.active .count {
        background: rgba(255,255,255,0.25);
    }
    
    .filter-tab.pending { background: #FEF3C7; color: #D97706; }
    .filter-tab.pending.active { background: #D97706; color: white; }
    
    .filter-tab.in_progress { background: #E8F0FE; color: var(--primary); }
    .filter-tab.in_progress.active { background: var(--primary); color: white; }
    
    .filter-tab.completed { background: #D1FAE5; color: #059669; }
    .filter-tab.completed.active { background: #059669; color: white; }
    
    .filter-tab.cancelled { background: #FEE2E2; color: #DC2626; }
    .filter-tab.cancelled.active { background: #DC2626; color: white; }
    
    [data-theme="dark"] .filter-tab.pending { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .filter-tab.in_progress { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .filter-tab.completed { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .filter-tab.cancelled { background: #3A1A1A; color: #F87171; }
    
    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 16px;
        margin-bottom: 20px;
    }
    
    .stat-card {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 16px 20px;
        border: 2px solid var(--border-color);
        text-align: center;
        transition: all 0.3s ease;
    }
    
    .stat-card:hover {
        border-color: var(--primary);
        transform: translateY(-3px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.08);
    }
    
    .stat-card .number {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--primary);
    }
    
    .stat-card .label {
        font-size: 0.75rem;
        color: var(--text-secondary);
        font-weight: 500;
        margin-top: 2px;
    }
    
    .stat-card .icon {
        font-size: 1.5rem;
        margin-bottom: 4px;
        display: block;
    }
    
    .stat-card.completed .number { color: #059669; }
    .stat-card.pending .number { color: #D97706; }
    .stat-card.in_progress .number { color: var(--primary); }
    .stat-card.cancelled .number { color: #DC2626; }
    
    /* Monthly Stats */
    .monthly-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
        gap: 8px;
        margin-bottom: 20px;
    }
    
    .monthly-stat {
        background: var(--bg-card);
        border-radius: 8px;
        padding: 10px 12px;
        border: 2px solid var(--border-color);
        text-align: center;
        transition: all 0.3s ease;
    }
    
    .monthly-stat:hover {
        border-color: var(--primary);
        transform: translateY(-2px);
    }
    
    .monthly-stat .month {
        font-size: 0.6rem;
        color: var(--text-secondary);
        text-transform: uppercase;
        font-weight: 600;
    }
    
    .monthly-stat .count {
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--primary);
    }
    
    /* Request Card */
    .request-card {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 16px 20px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        margin-bottom: 16px;
    }
    
    .request-card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.08);
    }
    
    .request-card .request-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 8px;
        padding-bottom: 8px;
        border-bottom: 2px solid var(--border-color);
    }
    
    .request-card .request-header .request-number {
        font-weight: 700;
        font-size: 1rem;
        color: var(--text-primary);
        font-family: monospace;
    }
    
    .request-card .request-header .completed-date {
        font-size: 0.75rem;
        color: var(--text-secondary);
    }
    
    .request-card .request-body {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr 1fr;
        gap: 12px;
        margin-bottom: 10px;
    }
    
    .request-card .request-body .info-item .label {
        font-size: 0.6rem;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .request-card .request-body .info-item .value {
        font-size: 0.9rem;
        font-weight: 500;
        color: var(--text-primary);
    }
    
    .request-card .request-body .info-item .value .phone {
        font-size: 0.7rem;
        color: var(--text-secondary);
        font-weight: 400;
    }
    
    .request-card .test-names {
        background: var(--bg-body);
        border-radius: 8px;
        padding: 8px 14px;
        margin-bottom: 10px;
        font-size: 0.8rem;
        color: var(--text-secondary);
    }
    
    .request-card .test-names strong {
        color: var(--text-primary);
    }
    
    .request-card .request-actions {
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
        background: #D97706;
        color: white;
    }
    .btn-print:hover {
        background: #B45309;
    }
    
    .btn-pdf {
        background: #DC2626;
        color: white;
    }
    .btn-pdf:hover {
        background: #B91C1C;
    }
    
    .btn-results {
        background: #7C3AED;
        color: white;
    }
    .btn-results:hover {
        background: #6D28D9;
    }
    
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
    
    .badge-completed {
        background: #059669;
        color: white;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.6rem;
        font-weight: 600;
    }
    
    @media (max-width: 768px) {
        .request-card .request-body {
            grid-template-columns: 1fr 1fr;
        }
        .request-card .request-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .filter-section {
            flex-direction: column;
            align-items: stretch;
        }
        .filter-tabs {
            flex-wrap: wrap;
        }
        .filter-tab {
            font-size: 0.7rem;
            padding: 4px 12px;
        }
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .monthly-stats {
            grid-template-columns: repeat(3, 1fr);
        }
    }
    
    @media (max-width: 480px) {
        .request-card .request-body {
            grid-template-columns: 1fr;
        }
        .btn-action {
            font-size: 0.6rem;
            padding: 3px 8px;
        }
        .request-card .request-actions {
            justify-content: flex-start;
        }
        .stats-grid {
            grid-template-columns: 1fr 1fr;
        }
        .monthly-stats {
            grid-template-columns: repeat(2, 1fr);
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
            <input type="text" id="searchInput" placeholder="Search completed requests..." 
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
                <i class="fas fa-check-circle mr-2" style="color: #059669;"></i> Completed Requests
            </h1>
            <p class="page-subtitle">
                View all completed laboratory requests
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
                <?php if ($counts['completed'] > 0): ?>
                    <span class="ml-2 inline-flex bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs border border-green-200">
                        <i class="fas fa-check-circle mr-1"></i> <?= $counts['completed'] ?> completed
                    </span>
                <?php endif; ?>
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
        <div class="stat-card completed">
            <span class="icon"><i class="fas fa-check-circle"></i></span>
            <p class="number"><?= $counts['completed'] ?? 0 ?></p>
            <p class="label">Total Completed</p>
        </div>
        <div class="stat-card pending">
            <span class="icon"><i class="fas fa-clock"></i></span>
            <p class="number"><?= $counts['pending'] ?? 0 ?></p>
            <p class="label">Pending</p>
        </div>
        <div class="stat-card in_progress">
            <span class="icon"><i class="fas fa-spinner"></i></span>
            <p class="number"><?= $counts['in_progress'] ?? 0 ?></p>
            <p class="label">In Progress</p>
        </div>
        <div class="stat-card cancelled">
            <span class="icon"><i class="fas fa-times-circle"></i></span>
            <p class="number"><?= $counts['cancelled'] ?? 0 ?></p>
            <p class="label">Cancelled</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- MONTHLY STATS -->
    <!-- ================================================================ -->
    <div class="monthly-stats animate-fade-in-up">
        <?php foreach ($monthly_stats as $month => $count): ?>
            <div class="monthly-stat">
                <div class="month"><?= $month ?></div>
                <div class="count"><?= $count ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- ================================================================ -->
    <!-- FILTER SECTION -->
    <!-- ================================================================ -->
    <div class="filter-section animate-fade-in-up">
        <form method="GET" action="" class="flex flex-wrap items-center gap-3 w-full">
            <input type="hidden" name="status" value="completed">
            
            <input type="text" name="search" class="form-control" placeholder="Search by patient, ID, test..." 
                   value="<?= htmlspecialchars($search) ?>" style="flex:1; min-width:150px;">
            
            <input type="date" name="date_from" class="form-control" value="<?= $date_from ?>" style="width:140px;">
            <span class="text-sm text-gray-400">to</span>
            <input type="date" name="date_to" class="form-control" value="<?= $date_to ?>" style="width:140px;">
            
            <button type="submit" class="btn-filter">
                <i class="fas fa-search mr-1"></i> Apply
            </button>
            
            <a href="completed_requests.php" class="btn-clear">
                <i class="fas fa-times mr-1"></i> Clear
            </a>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- REQUESTS LIST -->
    <!-- ================================================================ -->
    <div class="animate-fade-in-up">
        <?php if (count($requests) > 0): ?>
            <?php foreach ($requests as $request): ?>
                <div class="request-card">
                    <div class="request-header">
                        <div class="request-number">
                            <?= htmlspecialchars($request['request_number']) ?>
                            <span class="badge-completed">Completed</span>
                        </div>
                        <div class="completed-date">
                            <i class="fas fa-calendar-check mr-1"></i>
                            Completed: <?= date('M d, Y h:i A', strtotime($request['completed_at'])) ?>
                        </div>
                    </div>
                    
                    <div class="request-body">
                        <div class="info-item">
                            <div class="label">Patient</div>
                            <div class="value">
                                <?= htmlspecialchars($request['patient_name'] ?? 'Unknown') ?>
                                <span class="phone">(<?= htmlspecialchars($request['phone'] ?? 'N/A') ?>)</span>
                            </div>
                            <div class="text-xs text-gray-400">ID: <?= htmlspecialchars($request['patient_id'] ?? 'N/A') ?></div>
                        </div>
                        <div class="info-item">
                            <div class="label">Doctor</div>
                            <div class="value"><?= htmlspecialchars($request['doctor_name'] ?? 'N/A') ?></div>
                        </div>
                        <div class="info-item">
                            <div class="label">Technician</div>
                            <div class="value"><?= htmlspecialchars($request['technician_name'] ?? 'N/A') ?></div>
                        </div>
                        <div class="info-item">
                            <div class="label">Tests</div>
                            <div class="value"><?= $request['test_count'] ?> tests</div>
                            <div class="text-xs text-gray-400"><?= $request['completed_count'] ?> completed</div>
                        </div>
                    </div>
                    
                    <div class="test-names">
                        <strong>Tests:</strong> <?= htmlspecialchars(substr($request['test_names'] ?? '', 0, 200)) ?>
                        <?php if (strlen($request['test_names'] ?? '') > 200): ?>...<?php endif; ?>
                    </div>
                    
                    <div class="request-actions">
                        <a href="view_results.php?request_id=<?= $request['id'] ?>" class="btn-action btn-view">
                            <i class="fas fa-eye"></i> View Results
                        </a>
                        <a href="print_results.php?request_id=<?= $request['id'] ?>" class="btn-action btn-print" target="_blank">
                            <i class="fas fa-print"></i> Print
                        </a>
                        <a href="download_pdf.php?request_id=<?= $request['id'] ?>" class="btn-action btn-pdf">
                            <i class="fas fa-file-pdf"></i> PDF
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <p>No completed requests found</p>
                <p class="sub">
                    <?php if (!empty($search)): ?>
                        Try adjusting your search filters.
                    <?php elseif ($counts['in_progress'] > 0): ?>
                        There are <strong><?= $counts['in_progress'] ?></strong> requests in progress.
                        <a href="in_progress.php" class="text-blue-600 hover:underline">View in progress →</a>
                    <?php elseif ($counts['pending'] > 0): ?>
                        There are <strong><?= $counts['pending'] ?></strong> pending requests.
                        <a href="pending_requests.php" class="text-blue-600 hover:underline">View pending →</a>
                    <?php else: ?>
                        No laboratory requests have been completed yet.
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
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        var url = 'completed_requests.php';
        if (query) url += '?search=' + encodeURIComponent(query);
        window.location.href = url;
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

    console.log('%c✅ Braick - Completed Lab Requests', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Completed: <?= $counts['completed'] ?? 0 ?>', 'font-size:13px; color:#059669;');
    console.log('%c📋 Total Requests: <?= count($requests) ?>', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>