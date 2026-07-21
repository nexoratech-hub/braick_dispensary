<?php
// ================================================================
// FILE: frontend/components/laboratory_sidebar.php
// LABORATORY - SHARED SIDEBAR (FIXED - WITH REAL DATA FROM BOTH TABLES)
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// GET REAL DATA FOR BADGES
// ================================================================
$pending_count = 0;
$in_progress_count = 0;
$completed_count = 0;
$today_tests = 0;

if (isset($db) && $db !== null && isset($_SESSION['user_id'])) {
    $user_branch_id = $_SESSION['branch_id'] ?? 1;
    
    try {
        // ================================================================
        // 1. PENDING: FROM lab_tests (status NULL or 'pending') + lab_requests (status 'pending')
        // ================================================================
        
        // Pending from lab_tests
        $stmt = $db->prepare("
            SELECT COUNT(*) as count FROM lab_tests 
            WHERE branch_id = ? AND (status IS NULL OR status = 'pending' OR status = '')
        ");
        $stmt->execute([$user_branch_id]);
        $pending_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // Pending from lab_requests
        $stmt = $db->prepare("
            SELECT COUNT(*) as count FROM lab_requests 
            WHERE branch_id = ? AND status = 'pending'
        ");
        $stmt->execute([$user_branch_id]);
        $pending_requests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        $pending_count = $pending_tests + $pending_requests;
        
        // ================================================================
        // 2. IN PROGRESS: FROM lab_requests (status 'accepted' or 'in_progress')
        // ================================================================
        $stmt = $db->prepare("
            SELECT COUNT(*) as count FROM lab_requests 
            WHERE branch_id = ? AND status IN ('accepted', 'in_progress')
        ");
        $stmt->execute([$user_branch_id]);
        $in_progress_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // ================================================================
        // 3. COMPLETED TODAY: FROM lab_requests (completed today)
        // ================================================================
        $stmt = $db->prepare("
            SELECT COUNT(*) as count FROM lab_requests 
            WHERE branch_id = ? AND status = 'completed' AND DATE(completed_at) = CURDATE()
        ");
        $stmt->execute([$user_branch_id]);
        $completed_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // ================================================================
        // 4. TODAY'S TESTS: FROM lab_tests (completed today) + lab_request_items (completed today)
        // ================================================================
        
        // From lab_tests
        $stmt = $db->prepare("
            SELECT COUNT(*) as count FROM lab_tests 
            WHERE branch_id = ? AND status = 'completed' AND DATE(completed_at) = CURDATE()
        ");
        $stmt->execute([$user_branch_id]);
        $tests_completed_today = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // From lab_request_items
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM lab_request_items lri
            JOIN lab_requests lr ON lri.request_id = lr.id
            WHERE lr.branch_id = ? AND lri.status = 'completed' AND DATE(lri.completed_at) = CURDATE()
        ");
        $stmt->execute([$user_branch_id]);
        $items_completed_today = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        $today_tests = $tests_completed_today + $items_completed_today;
        
    } catch (Exception $e) {
        // Keep counts as 0
        error_log("Sidebar stats error: " . $e->getMessage());
    }
}

// ================================================================
// DETECT CURRENT PAGE
// ================================================================
$current_page = basename($_SERVER['PHP_SELF']);

// ================================================================
// FUNCTION TO CHECK ACTIVE STATE
// ================================================================
function isActive($page) {
    global $current_page;
    if ($page === $current_page) {
        return 'active';
    }
    return '';
}

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
?>
<style>
    /* ================================================================
       SIDEBAR STYLES - BLUE THEME
       ================================================================ */
    .sidebar {
        position: fixed; 
        top: 0; 
        left: 0; 
        bottom: 0;
        width: 270px; 
        background: #0B4EA8;
        color: white;
        z-index: 50; 
        overflow-y: auto;
        transition: transform 0.3s ease;
    }
    
    [data-theme="dark"] .sidebar {
        background: #0A3D7A;
    }
    
    .sidebar::-webkit-scrollbar { width: 5px; }
    .sidebar::-webkit-scrollbar-track { background: #0A3D7A; }
    .sidebar::-webkit-scrollbar-thumb { background: #6EA8FE; border-radius: 10px; }
    
    .sidebar-brand {
        padding: 22px 20px 16px;
        border-bottom: 2px solid #0A3D7A;
    }
    
    .sidebar-brand .logo {
        width: 48px; 
        height: 48px; 
        border-radius: 12px;
        object-fit: cover; 
        background: white; 
        padding: 4px;
    }
    
    .sidebar-brand .brand-text { 
        color: white; 
        font-weight: 700; 
        font-size: 1rem; 
    }
    
    .sidebar-brand .brand-sub { 
        color: #9EC5FE; 
        font-size: 0.7rem; 
    }
    
    .sidebar-nav { 
        padding: 14px 10px; 
    }
    
    .sidebar-nav .nav-label {
        font-size: 0.55rem; 
        text-transform: uppercase;
        letter-spacing: 0.1em; 
        color: #9EC5FE;
        padding: 0 12px; 
        margin: 12px 0 6px; 
        font-weight: 700;
    }
    
    .sidebar-link {
        display: flex; 
        align-items: center; 
        gap: 12px;
        padding: 9px 14px; 
        border-radius: 10px;
        color: #D2E3FC; 
        text-decoration: none;
        transition: all 0.3s ease; 
        font-size: 0.85rem; 
        font-weight: 500;
        margin: 2px 0;
        background: transparent;
        cursor: pointer;
        position: relative;
    }
    
    .sidebar-link:hover {
        background: #0AA84F;
        color: white;
        box-shadow: 0 4px 12px rgba(10, 168, 79, 0.4);
        transform: translateX(4px);
    }
    
    .sidebar-link.active {
        background: #0AA84F;
        color: white;
        box-shadow: 0 4px 12px rgba(10, 168, 79, 0.4);
    }
    
    .sidebar-link i { 
        width: 20px; 
        text-align: center; 
        font-size: 1rem; 
    }
    
    .sidebar-link .badge {
        margin-left: auto;
        background: rgba(255,255,255,0.15);
        padding: 1px 9px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
        color: white;
        transition: all 0.3s ease;
        min-width: 18px;
        text-align: center;
    }
    
    .sidebar-link:hover .badge {
        background: rgba(255,255,255,0.25);
    }
    
    .sidebar-link.active .badge {
        background: rgba(255,255,255,0.25);
        color: white;
    }
    
    .sidebar-link .badge.danger {
        background: #EF4444;
        animation: pulse-badge 2s infinite;
    }
    
    .sidebar-link .badge.green {
        background: #059669;
    }
    
    .sidebar-link .badge.orange {
        background: #D97706;
    }
    
    @keyframes pulse-badge {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }
    
    .sidebar-link.logout-link {
        border-top: 2px solid rgba(255,255,255,0.1);
        padding-top: 12px;
        margin-top: 8px;
        color: #FCA5A5;
    }
    
    .sidebar-link.logout-link:hover {
        background: #DC2626;
        color: white;
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.4);
    }
    
    .sidebar-status {
        padding: 12px 20px;
        border-top: 2px solid #0A3D7A;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .sidebar-status .status-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        display: inline-block;
    }
    
    .sidebar-status .status-dot.online {
        background: #34D399;
        animation: pulse-dot 1.5s infinite;
    }
    
    .sidebar-status .status-dot.offline {
        background: #94A3B8;
    }
    
    .sidebar-status .status-text {
        font-size: 0.75rem;
        color: #D2E3FC;
    }
    
    .sidebar-status .status-time {
        font-size: 0.6rem;
        color: #9EC5FE;
        margin-left: auto;
    }
    
    @keyframes pulse-dot {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.4; }
    }
    
    .mt-2 { margin-top: 8px; }
    
    @media (max-width: 1024px) {
        .sidebar { 
            transform: translateX(-100%); 
        }
        .sidebar.open { 
            transform: translateX(0); 
        }
    }
</style>

<!-- ================================================================ -->
<!-- SIDEBAR - LABORATORY -->
<!-- ================================================================ -->
<aside class="sidebar" id="sidebar">
    
    <!-- Brand -->
    <div class="sidebar-brand">
        <div class="flex items-center gap-3">
            <img src="<?= $logo_url ?>" alt="Braick Logo" class="logo"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2248%22 height=%2248%22%3E%3Crect width=%2248%22 height=%2248%22 fill=%22%230B4EA8%22 rx=%2212%22/%3E%3Ctext x=%2224%22 y=%2232%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2220%22 font-weight=%22bold%22%3EB%3C/text%3E%3C/svg%3E'">
            <div>
                <p class="brand-text">Braick Dispensary</p>
                <p class="brand-sub">Laboratory Panel</p>
            </div>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        
        <!-- ===== LABORATORY MENU ===== -->
        <div class="nav-label">Laboratory</div>
        
        <!-- 1. Dashboard -->
        <a href="../laboratory/dashboard.php" class="sidebar-link <?= isActive('dashboard.php') ?>">
            <i class="fas fa-home"></i> Dashboard
        </a>
        
        <!-- ===== LAB REQUESTS ===== -->
        <div class="nav-label mt-2">Lab Requests</div>
        
        <!-- Pending (Counts BOTH lab_tests AND lab_requests) -->
        <a href="../laboratory/pending_requests.php" class="sidebar-link <?= isActive('pending_requests.php') ?>">
            <i class="fas fa-clock"></i> Pending
            <?php if ($pending_count > 0): ?>
                <span class="badge danger" id="sidebarPendingBadge"><?= $pending_count ?></span>
            <?php else: ?>
                <span class="badge" id="sidebarPendingBadge">0</span>
            <?php endif; ?>
        </a>
        
        <!-- In Progress -->
        <a href="../laboratory/in_progress.php" class="sidebar-link <?= isActive('in_progress.php') ?>">
            <i class="fas fa-spinner"></i> In Progress
            <?php if ($in_progress_count > 0): ?>
                <span class="badge orange" id="sidebarInProgressBadge"><?= $in_progress_count ?></span>
            <?php else: ?>
                <span class="badge" id="sidebarInProgressBadge">0</span>
            <?php endif; ?>
        </a>
        
        <!-- Completed -->
        <a href="../laboratory/completed_requests.php" class="sidebar-link <?= isActive('completed_requests.php') ?>">
            <i class="fas fa-check-circle"></i> Completed
            <?php if ($completed_count > 0): ?>
                <span class="badge green" id="sidebarCompletedBadge"><?= $completed_count ?></span>
            <?php else: ?>
                <span class="badge" id="sidebarCompletedBadge">0</span>
            <?php endif; ?>
        </a>
        
        <!-- ===== RESULTS ===== -->
        <div class="nav-label mt-2">Results</div>
        
        <!-- Results History -->
        <a href="../laboratory/results_history.php" class="sidebar-link <?= isActive('results_history.php') ?>">
            <i class="fas fa-history"></i> Results History
            <span class="badge" id="sidebarTodayTests"><?= $today_tests ?></span>
        </a>
        
        <!-- Reports -->
        <a href="../laboratory/reports.php" class="sidebar-link <?= isActive('reports.php') ?>">
            <i class="fas fa-chart-bar"></i> Reports
        </a>
        
        <!-- ===== ACCOUNT ===== -->
        <div class="nav-label mt-2">Account</div>
        
        <!-- Profile -->
        <a href="../laboratory/profile.php" class="sidebar-link <?= isActive('profile.php') ?>">
            <i class="fas fa-user-circle"></i> Profile
        </a>
        
        <!-- Logout -->
        <a href="../../../logout.php" class="sidebar-link logout-link">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
        
    </nav>
    
    <!-- Online Status -->
    <div class="sidebar-status">
        <span class="status-dot online" id="sidebarStatusDot"></span>
        <span class="status-text" id="sidebarStatusText">Online</span>
        <span class="status-time" id="sidebarStatusTime"><?= date('H:i:s') ?></span>
    </div>
</aside>

<script>
    // ================================================================
    // SIDEBAR TOGGLE (Mobile)
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        var sidebar = document.getElementById('sidebar');
        var sidebarToggle = document.getElementById('sidebarToggle');
        
        if (sidebarToggle && sidebar) {
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
    });

    // ================================================================
    // UPDATE SIDEBAR BADGES (AJAX every 3 seconds)
    // ================================================================
    function updateSidebarBadges(pending, inProgress, completed, todayTests) {
        // Update Pending Badge
        var pendingBadge = document.getElementById('sidebarPendingBadge');
        if (pendingBadge) {
            pendingBadge.textContent = pending;
            if (pending > 0) {
                pendingBadge.className = 'badge danger';
            } else {
                pendingBadge.className = 'badge';
            }
        }
        
        // Update In Progress Badge
        var inProgressBadge = document.getElementById('sidebarInProgressBadge');
        if (inProgressBadge) {
            inProgressBadge.textContent = inProgress;
            if (inProgress > 0) {
                inProgressBadge.className = 'badge orange';
            } else {
                inProgressBadge.className = 'badge';
            }
        }
        
        // Update Completed Badge
        var completedBadge = document.getElementById('sidebarCompletedBadge');
        if (completedBadge) {
            completedBadge.textContent = completed;
            if (completed > 0) {
                completedBadge.className = 'badge green';
            } else {
                completedBadge.className = 'badge';
            }
        }
        
        // Update Today Tests Badge
        var todayTestsBadge = document.getElementById('sidebarTodayTests');
        if (todayTestsBadge) {
            todayTestsBadge.textContent = todayTests;
            if (todayTests > 0) {
                todayTestsBadge.className = 'badge green';
            } else {
                todayTestsBadge.className = 'badge';
            }
        }
        
        // Update status time
        var timeEl = document.getElementById('sidebarStatusTime');
        if (timeEl) {
            var now = new Date();
            timeEl.textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        }
    }

    // ================================================================
    // AJAX AUTO-UPDATE
    // ================================================================
    var sidebarUpdateInterval = null;
    var sidebarIsUpdating = false;

    function fetchSidebarData() {
        if (sidebarIsUpdating) return;
        sidebarIsUpdating = true;
        
        fetch('../laboratory/get_sidebar_stats.php?t=' + new Date().getTime())
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    updateSidebarBadges(
                        data.pending || 0,
                        data.in_progress || 0,
                        data.completed || 0,
                        data.today_tests || 0
                    );
                }
                sidebarIsUpdating = false;
            })
            .catch(function(error) {
                console.error('Sidebar update error:', error);
                sidebarIsUpdating = false;
            });
    }

    function startSidebarAutoUpdate() {
        if (sidebarUpdateInterval) {
            clearInterval(sidebarUpdateInterval);
        }
        fetchSidebarData();
        sidebarUpdateInterval = setInterval(fetchSidebarData, 3000);
    }

    function stopSidebarAutoUpdate() {
        if (sidebarUpdateInterval) {
            clearInterval(sidebarUpdateInterval);
            sidebarUpdateInterval = null;
        }
    }

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopSidebarAutoUpdate();
        } else {
            startSidebarAutoUpdate();
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            startSidebarAutoUpdate();
        }, 2000);
    });

    console.log('%c🧪 Laboratory Sidebar (FIXED - Real Data from BOTH tables)', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Pending: <?= $pending_count ?> | In Progress: <?= $in_progress_count ?> | Completed: <?= $completed_count ?> | Today: <?= $today_tests ?>', 'font-size:12px; color:#9EC5FE;');
    console.log('%c🔄 Auto-updates every 3 seconds', 'font-size:12px; color:#34D399;');
</script>