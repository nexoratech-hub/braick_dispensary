<?php
// ================================================================
// FILE: frontend/pages/laboratory/dashboard.php
// LABORATORY DASHBOARD (BILA REVENUE CARD)
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
// GET STATISTICS
// ================================================================
$today = date('Y-m-d');
$start_of_month = date('Y-m-d', strtotime('first day of this month'));

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

// 5. Recent Requests (Last 10)
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

// 6. Daily Tests Chart (Last 7 days)
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
    $daily_tests[] = $stmt->fetch()['count'] ?? 0;
}

// 7. Monthly Tests Chart (Last 6 months)
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
    $monthly_tests[] = $stmt->fetch()['count'] ?? 0;
}

// 8. Most Requested Tests
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
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
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

    <!-- Page Header -->
    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-5">
        <div>
            <h1 class="page-title">
                <i class="fas fa-flask mr-2" style="color: var(--primary);"></i> Laboratory Dashboard
            </h1>
            <p class="page-subtitle">
                Welcome, <?= htmlspecialchars($user_full_name) ?>!
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
                <span class="ml-2 inline-flex bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs border border-green-200">
                    <i class="fas fa-calendar-day mr-1"></i> <?= date('F d, Y') ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="pending_requests.php" class="btn btn-blue btn-sm">
                <i class="fas fa-clock"></i> Pending (<?= $pending ?>)
            </a>
            <a href="in_progress.php" class="btn btn-outline btn-sm">
                <i class="fas fa-spinner"></i> In Progress (<?= $in_progress ?>)
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS - 4 CARDS (BILA REVENUE) -->
    <!-- ================================================================ -->
    <div class="stats-grid animate-fade-in-up">
        
        <!-- 1. Pending Requests - Orange -->
        <div class="stat-card orange">
            <div>
                <p class="stat-label">Pending Requests</p>
                <p class="stat-number"><?= $pending ?></p>
                <span class="stat-trend"><i class="fas fa-clock"></i> Awaiting</span>
            </div>
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
        </div>
        
        <!-- 2. In Progress - Blue -->
        <div class="stat-card blue">
            <div>
                <p class="stat-label">In Progress</p>
                <p class="stat-number"><?= $in_progress ?></p>
                <span class="stat-trend"><i class="fas fa-spinner"></i> Running</span>
            </div>
            <div class="stat-icon"><i class="fas fa-spinner"></i></div>
        </div>
        
        <!-- 3. Completed Today - Green -->
        <div class="stat-card green">
            <div>
                <p class="stat-label">Completed Today</p>
                <p class="stat-number"><?= $completed_today ?></p>
                <span class="stat-trend"><i class="fas fa-check-circle"></i> Done</span>
            </div>
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        </div>
        
        <!-- 4. Today's Tests - Purple -->
        <div class="stat-card purple">
            <div>
                <p class="stat-label">Today's Tests</p>
                <p class="stat-number"><?= $today_tests ?></p>
                <span class="stat-trend"><i class="fas fa-flask"></i> Completed</span>
            </div>
            <div class="stat-icon"><i class="fas fa-flask"></i></div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- CHARTS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">
        
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
                    <i class="fas fa-chart-line title-blue mr-2"></i>
                    Monthly Tests
                </h3>
                <span class="text-xs text-gray-400">Last 6 months</span>
            </div>
            <div class="chart-container">
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>
        
        <!-- Most Requested Tests -->
        <div class="card animate-fade-in-up">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-trophy title-purple mr-2"></i>
                    Most Requested Tests
                </h3>
            </div>
            <?php if (count($most_requested) > 0): ?>
                <div class="space-y-2">
                    <?php foreach ($most_requested as $index => $test): ?>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="text-sm font-bold text-gray-400">#<?= $index + 1 ?></span>
                                <span class="text-sm font-medium text-gray-700"><?= htmlspecialchars($test['test_name']) ?></span>
                            </div>
                            <span class="badge badge-blue"><?= $test['count'] ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-flask"></i>
                    <p>No tests completed yet</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- RECENT REQUESTS -->
    <!-- ================================================================ -->
    <div class="card animate-fade-in-up">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-history title-blue mr-2"></i>
                Recent Requests
                <span class="text-sm font-normal text-gray-400">(Last 10)</span>
            </h3>
            <a href="pending_requests.php" class="text-xs text-blue-600 hover:underline">View All →</a>
        </div>
        
        <?php if (count($recent_requests) > 0): ?>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>Patient</th>
                            <th>Visit</th>
                            <th>Doctor</th>
                            <th>Tests</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_requests as $req): ?>
                            <tr>
                                <td class="font-mono text-xs font-semibold text-blue-600">
                                    <?= htmlspecialchars($req['request_number']) ?>
                                </td>
                                <td><?= htmlspecialchars($req['patient_name'] ?? 'Unknown') ?></td>
                                <td class="font-mono text-xs"><?= htmlspecialchars($req['visit_id']) ?></td>
                                <td><?= htmlspecialchars($req['doctor_name'] ?? 'N/A') ?></td>
                                <td>
                                    <?= $req['test_count'] ?> tests
                                    <?php if ($req['completed_count'] > 0 && $req['status'] !== 'completed'): ?>
                                        <span class="text-xs text-gray-400">(<?= $req['completed_count'] ?> done)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= 
                                        $req['status'] === 'pending' ? 'badge-yellow' : 
                                        ($req['status'] === 'in_progress' ? 'badge-blue' : 
                                        ($req['status'] === 'completed' ? 'badge-green' : 'badge-red')) 
                                    ?>">
                                        <?= ucfirst(str_replace('_', ' ', $req['status'] ?? 'Pending')) ?>
                                    </span>
                                </td>
                                <td class="text-sm"><?= date('M d, Y', strtotime($req['requested_at'])) ?></td>
                                <td>
                                    <a href="view_request.php?id=<?= $req['id'] ?>" class="btn btn-outline btn-sm">
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
                <i class="fas fa-flask"></i>
                <p>No laboratory requests yet</p>
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
            Laboratory Dashboard
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
                        label: 'Tests Completed',
                        data: <?= json_encode($daily_tests) ?>,
                        backgroundColor: '#0B5ED7',
                        borderRadius: 4,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { stepSize: 1 } },
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
                        label: 'Tests Completed',
                        data: <?= json_encode($monthly_tests) ?>,
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
                        y: { beginAtZero: true, ticks: { stepSize: 1 } },
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
    });

    console.log('%c🧪 Braick - Laboratory Dashboard (Bila Revenue Card)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Pending: <?= $pending ?> | In Progress: <?= $in_progress ?> | Completed: <?= $completed_today ?> | Today Tests: <?= $today_tests ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c💰 Revenue Card REMOVED', 'font-size:13px; color:#EF4444;');
</script>

</body>
</html>