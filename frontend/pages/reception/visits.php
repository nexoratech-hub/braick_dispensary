<?php
// ================================================================
// FILE: frontend/pages/reception/visits.php
// RECEPTION - VISITS LIST (BRANCH FILTERED)
// WITH AJAX REAL-TIME UPDATE
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION - Rose Mwangi (Reception)
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

// ================================================================
// PATH SAHIHI
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';

$user_branch_id = $_SESSION['branch_id'] ?? 1;
$selected_branch_id = $user_branch_id;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$status_filter = $_GET['status'] ?? '';
$date_filter = $_GET['date'] ?? date('Y-m-d');
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? '';

try {
    $db = getDB();
    
    // Build query
    $query = "
        SELECT v.*, p.full_name as patient_name, p.patient_id, p.phone,
               u.full_name as doctor_name, u.specialty
        FROM visits v
        JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON v.doctor_id = u.id
        WHERE v.branch_id = ?
    ";
    $params = [$selected_branch_id];
    
    if (!empty($status_filter)) {
        $query .= " AND v.status = ?";
        $params[] = $status_filter;
    }
    
    if ($filter === 'today') {
        $query .= " AND DATE(v.created_at) = CURDATE()";
    } elseif (!empty($date_filter)) {
        $query .= " AND DATE(v.created_at) = ?";
        $params[] = $date_filter;
    }
    
    if (!empty($search)) {
        $query .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ? OR p.phone LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $query .= " ORDER BY v.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $visits = $stmt->fetchAll();
    
    // Status counts
    $status_counts = [];
    $statuses = ['pending', 'assigned', 'with_doctor', 'completed', 'cancelled'];
    foreach ($statuses as $status) {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE status = ? AND branch_id = ?");
        $stmt->execute([$status, $selected_branch_id]);
        $status_counts[$status] = $stmt->fetch()['count'] ?? 0;
    }
    
} catch (Exception $e) {
    $visits = [];
    $status_counts = [];
}

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/reception_header.php';
include_once '../../components/reception_sidebar.php';
?>

<style>
    .visit-row td {
        padding: 10px 14px;
        border-bottom: 1px solid var(--border-color);
        vertical-align: middle;
    }
    .visit-row:hover td {
        background: var(--table-hover);
    }
    .table-wrap { overflow-x: auto; }
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
    .status-badge-visit {
        display: inline-block;
        font-size: 0.6rem;
        font-weight: 600;
        padding: 2px 12px;
        border-radius: 12px;
    }
    .status-badge-visit.pending { background: #FEF3C7; color: #D97706; }
    .status-badge-visit.assigned { background: #E8F0FE; color: #0B5ED7; }
    .status-badge-visit.with_doctor { background: #FEF3C7; color: #D97706; }
    .status-badge-visit.completed { background: #D1FAE5; color: #059669; }
    .status-badge-visit.cancelled { background: #FEE2E2; color: #DC2626; }
    
    [data-theme="dark"] .status-badge-visit.pending { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .status-badge-visit.assigned { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .status-badge-visit.with_doctor { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .status-badge-visit.completed { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .status-badge-visit.cancelled { background: #3A1A1A; color: #F87171; }
    
    .role-badge-display {
        display: inline-block;
        font-size: 0.6rem;
        font-weight: 600;
        padding: 2px 10px;
        border-radius: 20px;
        background: var(--primary-bg);
        color: var(--primary);
        text-transform: uppercase;
    }
    [data-theme="dark"] .role-badge-display {
        background: #1E3A5F;
        color: #6EA8FE;
    }
    .branch-badge-display {
        display: inline-block;
        font-size: 0.6rem;
        font-weight: 600;
        padding: 2px 10px;
        border-radius: 20px;
        background: var(--success-bg);
        color: var(--success);
    }
    [data-theme="dark"] .branch-badge-display {
        background: #1A3A2A;
        color: #34D399;
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
            <input type="text" id="searchInput" placeholder="Search visits..." value="<?= htmlspecialchars($search) ?>">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <span class="branch-badge-display">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($branch_name) ?>
        </span>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= ($unread_notifications ?? 0) > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3EA%3C/text%3E%3C/svg%3E'">
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
                <i class="fas fa-clinic-medical mr-2" style="color: var(--primary);"></i> Visits
                <span class="role-badge-display ml-2">RECEPTION</span>
            </h1>
            <p class="page-subtitle">
                Manage all patient visits in <?= htmlspecialchars($branch_name) ?>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-list mr-1"></i> <?= count($visits) ?> visits
                </span>
            </p>
        </div>
        <div>
            <a href="assign_doctor.php" class="btn btn-blue btn-sm">
                <i class="fas fa-user-md"></i> Assign Doctor
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-sm font-medium text-gray-600 mr-2">Status:</span>
            <a href="visits.php?date=<?= $date_filter ?>&filter=<?= $filter ?>" 
               class="filter-btn <?= empty($status_filter) ? 'active' : '' ?>">All (<?= array_sum($status_counts) ?>)</a>
            <?php foreach ($status_counts as $status => $count): ?>
                <a href="visits.php?status=<?= $status ?>&date=<?= $date_filter ?>&filter=<?= $filter ?>" 
                   class="filter-btn <?= $status_filter === $status ? 'active' : '' ?>">
                    <?= ucfirst(str_replace('_', ' ', $status)) ?> (<?= $count ?>)
                </a>
            <?php endforeach; ?>
            
            <span class="text-sm font-medium text-gray-600 ml-4 mr-2">Filter:</span>
            <a href="visits.php?filter=today" class="filter-btn <?= $filter === 'today' ? 'active' : '' ?>">Today</a>
            <a href="visits.php" class="filter-btn <?= empty($filter) && empty($status_filter) ? 'active' : '' ?>">All</a>
            
            <span class="text-sm font-medium text-gray-600 ml-4 mr-2">Date:</span>
            <input type="date" id="dateFilter" value="<?= $date_filter ?>" 
                   onchange="window.location.href='visits.php?date='+this.value+'&status=<?= $status_filter ?>&filter=<?= $filter ?>'"
                   class="form-control" style="width:auto;padding:4px 10px;font-size:0.8rem;border:2px solid var(--border-color);border-radius:8px;background:var(--bg-card);color:var(--text-primary);">
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- VISITS TABLE -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-blue mr-2"></i> Visits List
            </h3>
            <span class="text-sm text-gray-400"><?= count($visits) ?> record(s)</span>
        </div>
        
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="border-radius: 8px 0 0 0;">#</th>
                        <th>Visit #</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th style="border-radius: 0 8px 0 0;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($visits) > 0): ?>
                        <?php $i = 1; foreach ($visits as $visit): ?>
                            <tr class="visit-row">
                                <td><?= $i++ ?></td>
                                <td><span class="font-mono text-xs bg-gray-100 px-2 py-0.5 rounded"><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></span></td>
                                <td>
                                    <div class="font-medium text-sm"><?= htmlspecialchars($visit['patient_name']) ?></div>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($visit['patient_id'] ?? 'N/A') ?></div>
                                </td>
                                <td>
                                    <?php if ($visit['doctor_name']): ?>
                                        <div class="text-sm">Dr. <?= htmlspecialchars($visit['doctor_name']) ?></div>
                                        <div class="text-xs text-gray-400"><?= htmlspecialchars($visit['specialty'] ?? 'GP') ?></div>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="text-xs capitalize"><?= htmlspecialchars($visit['visit_type'] ?? 'N/A') ?></span></td>
                                <td>
                                    <span class="status-badge-visit <?= $visit['status'] ?>">
                                        <?= ucfirst(str_replace('_', ' ', $visit['status'])) ?>
                                    </span>
                                </td>
                                <td class="text-xs"><?= isset($visit['created_at']) ? date('M d, Y h:i A', strtotime($visit['created_at'])) : 'N/A' ?></td>
                                <td>
                                    <div class="flex gap-1">
                                        <a href="visit_details.php?id=<?= $visit['id'] ?>" 
                                           class="btn btn-blue btn-sm" title="View Visit">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="view_patient.php?id=<?= $visit['patient_id'] ?>" 
                                           class="btn btn-green btn-sm" title="View Patient">
                                            <i class="fas fa-user"></i>
                                        </a>
                                        <?php if ($visit['status'] !== 'completed' && $visit['status'] !== 'cancelled'): ?>
                                            <a href="visit_status.php?id=<?= $visit['id'] ?>&status=completed&redirect=visits.php" 
                                               class="btn btn-outline btn-sm" title="Complete" style="color:var(--success);border-color:var(--success);">
                                                <i class="fas fa-check"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-8 text-gray-400">
                                <i class="fas fa-clinic-medical text-3xl block mb-2"></i>
                                <?php if (!empty($search) || !empty($status_filter) || $filter === 'today'): ?>
                                    No visits found matching the filters in <?= htmlspecialchars($branch_name) ?>
                                <?php else: ?>
                                    No visits recorded yet in <?= htmlspecialchars($branch_name) ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- QUICK STATS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-5">
        <?php foreach ($status_counts as $status => $count): ?>
            <div class="card text-center <?= $status === 'completed' ? 'border-green-500' : ($status === 'pending' || $status === 'assigned' ? 'border-yellow-500' : '') ?>">
                <p class="text-2xl font-bold <?= $status === 'completed' ? 'text-green-600' : ($status === 'pending' || $status === 'assigned' ? 'text-yellow-600' : 'text-gray-600') ?>">
                    <?= $count ?>
                </p>
                <p class="text-sm text-gray-500 capitalize"><?= ucfirst(str_replace('_', ' ', $status)) ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Visits
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
        var status = '<?= $status_filter ?>';
        var date = '<?= $date_filter ?>';
        var filter = '<?= $filter ?>';
        window.location.href = 'visits.php?search=' + encodeURIComponent(query) + '&status=' + status + '&date=' + date + '&filter=' + filter;
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

    console.log('%c🏥 Braick - Visits List', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Total Visits: <?= count($visits) ?>', 'font-size:13px; color:#64748B;');
</script>

</body>
</html>