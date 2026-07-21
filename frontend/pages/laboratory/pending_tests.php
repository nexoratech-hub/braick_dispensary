<?php
// ================================================================
// FILE: frontend/pages/laboratory/pending_tests.php
// LABORATORY - PENDING TESTS (USING lab_tests TABLE)
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

// ================================================================
// BUILD QUERY - Get pending tests (status NULL or 'pending')
// ================================================================
$query = "
    SELECT lt.*, 
           p.full_name as patient_name, p.patient_id, p.phone,
           u.full_name as doctor_name, u.specialty,
           v.visit_number,
           TIMESTAMPDIFF(MINUTE, lt.created_at, NOW()) as waiting_time
    FROM lab_tests lt
    JOIN visits v ON lt.visit_id = v.id
    JOIN patients p ON v.patient_id = p.id
    JOIN users u ON lt.doctor_id = u.id
    WHERE lt.branch_id = ? AND (lt.status IS NULL OR lt.status = 'pending')
";

$params = [$user_branch_id];

if (!empty($search)) {
    $query .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR lt.test_name LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($date_filter)) {
    $query .= " AND DATE(lt.created_at) = ?";
    $params[] = $date_filter;
}

$query .= " ORDER BY lt.created_at ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET COUNTS
// ================================================================
$stmt = $db->prepare("
    SELECT COUNT(*) as count FROM lab_tests 
    WHERE branch_id = ? AND (status IS NULL OR status = 'pending')
");
$stmt->execute([$user_branch_id]);
$pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$stmt = $db->prepare("
    SELECT COUNT(*) as count FROM lab_tests 
    WHERE branch_id = ? AND status = 'in_progress'
");
$stmt->execute([$user_branch_id]);
$in_progress_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$today = date('Y-m-d');
$stmt = $db->prepare("
    SELECT COUNT(*) as count FROM lab_tests 
    WHERE branch_id = ? AND status = 'completed' AND DATE(completed_at) = ?
");
$stmt->execute([$user_branch_id, $today]);
$completed_today_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

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
    }
    .stat-card .number {
        font-size: 1.6rem;
        font-weight: 700;
        line-height: 1.2;
    }
    .stat-card .number.pending { color: #D97706; }
    .stat-card .number.in-progress { color: #0B5ED7; }
    .stat-card .number.completed { color: #059669; }
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
    
    .test-row td {
        padding: 10px 14px;
        border-bottom: 1px solid var(--border-color);
        vertical-align: middle;
    }
    .test-row:hover td {
        background: var(--table-hover);
    }
    
    .status-badge {
        display: inline-block;
        font-size: 0.6rem;
        font-weight: 600;
        padding: 2px 12px;
        border-radius: 12px;
    }
    .status-badge.pending { background: #FEF3C7; color: #D97706; }
    .status-badge.in_progress { background: #E8F0FE; color: #0B5ED7; }
    .status-badge.completed { background: #D1FAE5; color: #059669; }
    .status-badge.cancelled { background: #FEE2E2; color: #DC2626; }
    
    [data-theme="dark"] .status-badge.pending { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .status-badge.in_progress { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .status-badge.completed { background: #1A3A2A; color: #34D399; }
    
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
        min-width: 800px;
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
    .table-wrap::-webkit-scrollbar {
        width: 5px;
        height: 5px;
    }
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
    
    .waiting-time {
        font-size: 0.7rem;
        font-weight: 500;
    }
    .waiting-time.long { color: #DC2626; }
    .waiting-time.medium { color: #D97706; }
    
    @media (max-width: 768px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .data-table { font-size: 0.7rem; min-width: 650px; }
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
            <input type="text" id="searchInput" placeholder="Search pending tests..." value="<?= htmlspecialchars($search) ?>">
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
                <i class="fas fa-clock mr-2" style="color: #D97706;"></i> Pending Tests
                <span class="role-badge ml-2">LABORATORY</span>
                <span class="update-badge ml-2" id="updateBadge">
                    <i class="fas fa-sync-alt fa-spin"></i> Live
                </span>
            </h1>
            <p class="page-subtitle">
                Manage all pending laboratory tests
                <span class="ml-2 inline-flex bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full text-xs border border-yellow-200">
                    <i class="fas fa-clock mr-1"></i> <?= $pending_count ?> Pending
                </span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-spinner mr-1"></i> <?= $in_progress_count ?> In Progress
                </span>
                <span class="ml-2 inline-flex bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs border border-green-200">
                    <i class="fas fa-check-circle mr-1"></i> <?= $completed_today_count ?> Completed Today
                </span>
            </p>
        </div>
        <div>
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
            <p class="number pending" id="statPending"><?= $pending_count ?></p>
            <p class="label">⏳ Pending Tests</p>
        </div>
        <div class="stat-card">
            <p class="number in-progress" id="statInProgress"><?= $in_progress_count ?></p>
            <p class="label">🔬 In Progress</p>
        </div>
        <div class="stat-card">
            <p class="number completed" id="statCompletedToday"><?= $completed_today_count ?></p>
            <p class="label">✅ Completed Today</p>
        </div>
        <div class="stat-card">
            <p class="number" id="statTotal" style="color: #7C3AED;"><?= count($tests) ?></p>
            <p class="label">📋 Total Pending</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-sm font-medium text-gray-600 mr-2">Date:</span>
            <input type="date" id="dateFilter" value="<?= $date_filter ?>"
                   onchange="window.location.href='pending_tests.php?date='+this.value+'&search=<?= urlencode($search) ?>'"
                   class="form-control" style="width:auto;padding:4px 10px;font-size:0.8rem;border:2px solid var(--border-color);border-radius:8px;background:var(--bg-card);color:var(--text-primary);">
            
            <?php if (!empty($search)): ?>
                <a href="pending_tests.php" class="btn btn-outline btn-sm">
                    <i class="fas fa-times"></i> Clear Search
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- TESTS TABLE -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-blue mr-2"></i> Pending Tests
                <span class="text-sm font-normal text-gray-400" id="testCount">(<?= count($tests) ?>)</span>
            </h3>
            <span class="text-sm text-gray-400">Scroll to view all</span>
        </div>
        
        <div class="table-wrap">
            <table class="data-table" id="testTable">
                <thead>
                    <tr>
                        <th style="border-radius: 8px 0 0 0;">#</th>
                        <th>Test Name</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Visit #</th>
                        <th>Status</th>
                        <th>Waiting</th>
                        <th>Requested</th>
                        <th style="border-radius: 0 8px 0 0;">Actions</th>
                    </tr>
                </thead>
                <tbody id="testTableBody">
                    <?php if (count($tests) > 0): ?>
                        <?php $i = 1; foreach ($tests as $test): 
                            $waiting = $test['waiting_time'] ?? 0;
                            $waiting_class = $waiting > 60 ? 'long' : ($waiting > 30 ? 'medium' : '');
                            $waiting_text = $waiting < 1 ? 'Just now' : ($waiting < 60 ? $waiting . ' min' : floor($waiting / 60) . 'h ' . ($waiting % 60) . 'm');
                            $status = $test['status'] ?? 'pending';
                        ?>
                            <tr class="test-row" data-id="<?= $test['id'] ?>">
                                <td><?= $i++ ?></td>
                                <td>
                                    <div class="font-medium text-sm"><?= htmlspecialchars($test['test_name']) ?></div>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($test['test_type'] ?? 'N/A') ?></div>
                                </td>
                                <td>
                                    <div class="font-medium text-sm"><?= htmlspecialchars($test['patient_name']) ?></div>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($test['patient_id'] ?? 'N/A') ?></div>
                                </td>
                                <td>
                                    <div class="text-sm">Dr. <?= htmlspecialchars($test['doctor_name']) ?></div>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($test['specialty'] ?? 'GP') ?></div>
                                </td>
                                <td class="font-mono text-xs"><?= htmlspecialchars($test['visit_number'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="status-badge <?= $status ?>">
                                        <?= $status === 'pending' ? '⏳ Pending' : ucfirst($status) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="waiting-time <?= $waiting_class ?>"><?= $waiting_text ?></span>
                                </td>
                                <td class="text-xs"><?= date('M d, Y h:i A', strtotime($test['created_at'])) ?></td>
                                <td>
                                    <div class="flex gap-1">
                                        <a href="view_test.php?id=<?= $test['id'] ?>" class="btn btn-blue btn-sm" title="View & Update">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="view_test.php?id=<?= $test['id'] ?>&action=start" class="btn btn-outline btn-sm" title="Start" style="border-color:#0B5ED7;color:#0B5ED7;">
                                            <i class="fas fa-play"></i>
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
                                    <p>No pending tests found</p>
                                    <p class="text-sm mt-1">All tests have been processed</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="card-footer">
            <span class="text-sm text-gray-500">
                <i class="fas fa-flask mr-1"></i> 
                Showing <strong id="recordCount"><?= count($tests) ?></strong> pending test(s)
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
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Pending Tests
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
        var date = '<?= $date_filter ?>';
        window.location.href = 'pending_tests.php?search=' + encodeURIComponent(query) + '&date=' + date;
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
    // AUTO-UPDATE (3 SECONDS)
    // ================================================================
    var updateInterval = null;
    var isUpdating = false;
    var lastHash = null;
    
    function fetchAndUpdate() {
        if (isUpdating) return;
        isUpdating = true;
        
        var date = '<?= $date_filter ?>';
        var search = '<?= urlencode($search) ?>';
        var url = 'get_lab_tests_stats.php?status=pending&date=' + date + '&search=' + search + '&t=' + new Date().getTime();
        
        fetch(url)
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(function(data) {
                if (data.success) {
                    if (lastHash !== data.hash) {
                        lastHash = data.hash;
                        updateTable(data);
                        
                        document.getElementById('statPending').textContent = data.pending_count || 0;
                        document.getElementById('statInProgress').textContent = data.in_progress_count || 0;
                        document.getElementById('statCompletedToday').textContent = data.completed_today_count || 0;
                        document.getElementById('statTotal').textContent = data.total || 0;
                        document.getElementById('testCount').textContent = '(' + (data.total || 0) + ')';
                        document.getElementById('recordCount').textContent = data.total || 0;
                        
                        var now = new Date();
                        var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                        document.getElementById('footerTimestamp').textContent = 'Last updated: ' + timeStr;
                        document.getElementById('updateBadge').innerHTML = '<i class="fas fa-check-circle" style="color:#34D399;"></i> Live ' + timeStr;
                    }
                }
                isUpdating = false;
            })
            .catch(function(error) {
                console.error('Error fetching pending tests:', error);
                document.getElementById('updateBadge').innerHTML = '<i class="fas fa-exclamation-circle" style="color:#EF4444;"></i> Error';
                isUpdating = false;
            });
    }
    
    function updateTable(data) {
        var tbody = document.getElementById('testTableBody');
        var tests = data.tests || [];
        
        if (tests.length > 0) {
            var html = '';
            var i = 1;
            tests.forEach(function(test) {
                var waiting = test.waiting_time || 0;
                var waitingClass = waiting > 60 ? 'long' : (waiting > 30 ? 'medium' : '');
                var waitingText = waiting < 1 ? 'Just now' : (waiting < 60 ? waiting + ' min' : Math.floor(waiting / 60) + 'h ' + (waiting % 60) + 'm');
                var status = test.status || 'pending';
                
                html += `
                    <tr class="test-row" data-id="${test.id}">
                        <td>${i++}</td>
                        <td>
                            <div class="font-medium text-sm">${escapeHtml(test.test_name)}</div>
                            <div class="text-xs text-gray-400">${escapeHtml(test.test_type || 'N/A')}</div>
                        </td>
                        <td>
                            <div class="font-medium text-sm">${escapeHtml(test.patient_name)}</div>
                            <div class="text-xs text-gray-400">${escapeHtml(test.patient_id || 'N/A')}</div>
                        </td>
                        <td>
                            <div class="text-sm">Dr. ${escapeHtml(test.doctor_name)}</div>
                            <div class="text-xs text-gray-400">${escapeHtml(test.specialty || 'GP')}</div>
                        </td>
                        <td class="font-mono text-xs">${escapeHtml(test.visit_number || 'N/A')}</td>
                        <td>
                            <span class="status-badge ${status}">
                                ${status === 'pending' ? '⏳ Pending' : status === 'in_progress' ? '🔬 In Progress' : status}
                            </span>
                        </td>
                        <td>
                            <span class="waiting-time ${waitingClass}">${waitingText}</span>
                        </td>
                        <td class="text-xs">${formatDate(test.created_at)}</td>
                        <td>
                            <div class="flex gap-1">
                                <a href="view_test.php?id=${test.id}" class="btn btn-blue btn-sm" title="View & Update">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="view_test.php?id=${test.id}&action=start" class="btn btn-outline btn-sm" title="Start" style="border-color:#0B5ED7;color:#0B5ED7;">
                                    <i class="fas fa-play"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        } else {
            tbody.innerHTML = `
                <tr>
                    <td colspan="9">
                        <div class="empty-state">
                            <i class="fas fa-check-circle" style="color: #059669; font-size: 3rem;"></i>
                            <p>No pending tests found</p>
                            <p class="text-sm mt-1">All tests have been processed</p>
                        </div>
                    </td>
                </tr>
            `;
        }
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function formatDate(datetime) {
        if (!datetime) return 'N/A';
        var d = new Date(datetime);
        if (isNaN(d.getTime())) return 'N/A';
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) + ' ' +
               d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    }
    
    function startAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
        }
        fetchAndUpdate();
        updateInterval = setInterval(fetchAndUpdate, 3000);
    }
    
    function stopAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
            updateInterval = null;
        }
    }
    
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopAutoUpdate();
        } else {
            startAutoUpdate();
        }
    });
    
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            searchInput?.focus();
            searchInput?.select();
        }
    });
    
    startAutoUpdate();

    console.log('%c🧪 Braick - Pending Tests (Auto-Update)', 'font-size:18px; font-weight:bold; color:#D97706;');
    console.log('%c📊 Pending: <?= $pending_count ?> | In Progress: <?= $in_progress_count ?> | Completed Today: <?= $completed_today_count ?>', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>