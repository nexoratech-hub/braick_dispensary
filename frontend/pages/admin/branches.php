<?php
// ================================================================
// FILE: frontend/pages/admin/branches.php
// BRANCHES LIST - VIEW AND MANAGE ALL BRANCHES
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION - Admin Only
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['user_id'] = 1;
    $_SESSION['full_name'] = 'Admin John';
    $_SESSION['role'] = 'admin';
    $_SESSION['branch_id'] = 1;
}

// Include database and helpers
require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// VARIABLES
// ================================================================
$message = '';
$message_type = '';
$per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// ================================================================
// HANDLE TOGGLE STATUS (Activate/Deactivate)
// ================================================================
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $toggle_id = (int)$_GET['toggle'];
    
    // Get current status
    $stmt = $db->prepare("SELECT name, status FROM branches WHERE id = ?");
    $stmt->execute([$toggle_id]);
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($branch) {
        $new_status = $branch['status'] === 'active' ? 'inactive' : 'active';
        $action_text = $new_status === 'active' ? 'activated' : 'deactivated';
        
        $stmt = $db->prepare("UPDATE branches SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$new_status, $toggle_id]);
        
        // Log activity
        logActivity($db, $_SESSION['user_id'], 'branch_status_toggled', "Branch $action_text: {$branch['name']} (ID: $toggle_id)");
        
        $message = "Branch '{$branch['name']}' has been " . ($new_status === 'active' ? 'activated' : 'deactivated') . " successfully!";
        $message_type = 'success';
        
        // Redirect to remove toggle parameter from URL
        header("Location: branches.php?page=$page" . ($search ? "&search=" . urlencode($search) : ""));
        exit();
    }
}

// ================================================================
// GET BRANCHES WITH PAGINATION AND SEARCH
// ================================================================
$where_clause = "";
$params = [];

if (!empty($search)) {
    $where_clause = " WHERE name LIKE ? OR location LIKE ? OR phone LIKE ? OR email LIKE ?";
    $search_param = "%$search%";
    $params = [$search_param, $search_param, $search_param, $search_param];
}

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM branches $where_clause";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_branches = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
$total_pages = ceil($total_branches / $per_page);

// Get branches for current page
$sql = "SELECT * FROM branches $where_clause ORDER BY name ASC LIMIT ? OFFSET ?";
$stmt = $db->prepare($sql);
$params[] = $per_page;
$params[] = $offset;
$stmt->execute($params);
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STATISTICS
// ================================================================
// Total branches
$stmt = $db->query("SELECT COUNT(*) as total FROM branches");
$total_all = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Active branches
$stmt = $db->query("SELECT COUNT(*) as total FROM branches WHERE status = 'active'");
$total_active = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Inactive branches
$stmt = $db->query("SELECT COUNT(*) as total FROM branches WHERE status = 'inactive'");
$total_inactive = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<style>
    /* ================================================================
       ADDITIONAL STYLES FOR BRANCHES PAGE
       ================================================================ */
    
    /* Stat Cards */
    .stat-card-mini {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 14px 18px;
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
        text-align: center;
    }
    
    .stat-card-mini:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        border-color: #0B5ED7;
    }
    
    .stat-card-mini .stat-number {
        font-size: 1.8rem;
        font-weight: 700;
        color: #0B5ED7;
    }
    
    .stat-card-mini .stat-number.green {
        color: #059669;
    }
    
    .stat-card-mini .stat-number.red {
        color: #EF4444;
    }
    
    .stat-card-mini .stat-label {
        font-size: 0.7rem;
        color: var(--text-secondary);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    
    .stat-card-mini .stat-icon {
        font-size: 1.5rem;
        margin-bottom: 4px;
    }
    
    [data-theme="dark"] .stat-card-mini {
        background: #1E293B;
        border-color: #334155;
    }
    
    [data-theme="dark"] .stat-card-mini:hover {
        border-color: #0B5ED7;
    }
    
    [data-theme="dark"] .stat-card-mini .stat-number {
        color: #6EA8FE;
    }
    
    [data-theme="dark"] .stat-card-mini .stat-number.green {
        color: #34D399;
    }
    
    [data-theme="dark"] .stat-card-mini .stat-number.red {
        color: #F87171;
    }
    
    /* Table Header - Blue Theme */
    .table-blue thead th {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8) !important;
        color: #FFFFFF !important;
        font-weight: 700 !important;
        font-size: 0.7rem !important;
        text-transform: uppercase !important;
        letter-spacing: 0.05em !important;
        padding: 12px 16px !important;
        border-bottom: 3px solid #0A4CA8 !important;
        white-space: nowrap !important;
    }
    
    .table-blue thead th:first-child {
        border-radius: 8px 0 0 0 !important;
    }
    
    .table-blue thead th:last-child {
        border-radius: 0 8px 0 0 !important;
    }
    
    .table-blue tbody td {
        padding: 10px 16px !important;
        border-bottom: 1px solid #E2E8F0 !important;
        color: #1E293B !important;
        vertical-align: middle !important;
    }
    
    .table-blue tbody tr:hover td {
        background: #E8F0FE !important;
    }
    
    /* Dark mode table */
    [data-theme="dark"] .table-blue tbody td {
        color: #F1F5F9 !important;
        border-bottom-color: #334155 !important;
    }
    
    [data-theme="dark"] .table-blue tbody tr:hover td {
        background: #1A3A5F !important;
    }
    
    /* Badge styles */
    .badge {
        padding: 4px 14px !important;
        border-radius: 20px !important;
        font-size: 0.65rem !important;
        font-weight: 600 !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 4px !important;
        border: none !important;
    }
    
    .badge-active {
        background: #D1FAE5 !important;
        color: #059669 !important;
    }
    
    .badge-inactive {
        background: #FEE2E2 !important;
        color: #EF4444 !important;
    }
    
    [data-theme="dark"] .badge-active {
        background: #1A3A2A !important;
        color: #34D399 !important;
    }
    
    [data-theme="dark"] .badge-inactive {
        background: #3A1A1A !important;
        color: #F87171 !important;
    }
    
    /* Toggle button styles */
    .btn-toggle-active {
        background: #059669 !important;
        color: white !important;
        border: none !important;
        padding: 4px 12px !important;
        border-radius: 6px !important;
        font-size: 0.65rem !important;
        font-weight: 600 !important;
        cursor: pointer !important;
        transition: all 0.3s !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 4px !important;
        text-decoration: none !important;
    }
    
    .btn-toggle-active:hover {
        background: #047857 !important;
        transform: translateY(-1px) !important;
        box-shadow: 0 2px 8px rgba(5, 150, 105, 0.3) !important;
    }
    
    .btn-toggle-inactive {
        background: #EF4444 !important;
        color: white !important;
        border: none !important;
        padding: 4px 12px !important;
        border-radius: 6px !important;
        font-size: 0.65rem !important;
        font-weight: 600 !important;
        cursor: pointer !important;
        transition: all 0.3s !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 4px !important;
        text-decoration: none !important;
    }
    
    .btn-toggle-inactive:hover {
        background: #DC2626 !important;
        transform: translateY(-1px) !important;
        box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3) !important;
    }
    
    /* Pagination */
    .pagination {
        display: flex;
        gap: 4px;
        flex-wrap: wrap;
    }
    
    .pagination .page-link {
        padding: 6px 12px;
        border-radius: 6px;
        border: 1px solid var(--border-color);
        color: var(--text-primary);
        text-decoration: none;
        font-size: 0.8rem;
        transition: all 0.3s;
        background: var(--bg-card);
    }
    
    .pagination .page-link:hover {
        background: #0B5ED7;
        color: white;
        border-color: #0B5ED7;
    }
    
    .pagination .page-link.active {
        background: #0B5ED7;
        color: white;
        border-color: #0B5ED7;
    }
    
    .pagination .page-link.disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    [data-theme="dark"] .pagination .page-link {
        background: #1E293B;
        border-color: #334155;
    }
    
    [data-theme="dark"] .pagination .page-link:hover {
        background: #0B5ED7;
        border-color: #0B5ED7;
    }
    
    /* Card header blue accent */
    .card-blue-header {
        border-left: 4px solid #0B5ED7 !important;
    }
    
    /* Search input */
    .search-input-white {
        background: #FFFFFF !important;
        color: #1E293B !important;
        border: 2px solid #E2E8F0 !important;
        transition: all 0.3s ease !important;
    }
    
    .search-input-white:focus {
        border-color: #0B5ED7 !important;
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15) !important;
        background: #FFFFFF !important;
    }
    
    [data-theme="dark"] .search-input-white {
        background: #1E293B !important;
        color: #F1F5F9 !important;
        border-color: #334155 !important;
    }
    
    [data-theme="dark"] .search-input-white:focus {
        border-color: #6EA8FE !important;
        background: #1E293B !important;
    }
</style>

<!-- ================================================================ -->
<!-- TOP NAVIGATION - SHARED HEADER -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <form method="GET" action="" class="flex-1 flex">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                       placeholder="Search branches..." 
                       class="flex-1 px-3 py-2 bg-transparent border-none outline-none text-sm" 
                       style="color: var(--text-primary);">
                <button type="submit" class="search-btn">
                    <i class="fas fa-search mr-1"></i> Search
                </button>
            </form>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn" title="Toggle Dark Mode">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $logo_url ?>" alt="Profile" class="avatar"
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
                <i class="fas fa-store-alt mr-2"></i> Branches
            </h1>
            <p class="page-subtitle">
                Manage all branches in the system
                <span class="branch-tag ml-2">
                    <i class="fas fa-building"></i> <?= $total_all ?> Branches Total
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="dashboard.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <a href="add_branch.php" class="btn btn-blue btn-sm">
                <i class="fas fa-plus"></i> Add New Branch
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="p-4 rounded-xl mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200 dark:bg-green-900/20 dark:text-green-300 dark:border-green-800' : 'bg-red-100 text-red-700 border border-red-200 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
        
        <div class="stat-card-mini">
            <div class="stat-icon">🏢</div>
            <p class="stat-number"><?= $total_all ?></p>
            <p class="stat-label">Total Branches</p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">✅</div>
            <p class="stat-number green"><?= $total_active ?></p>
            <p class="stat-label">Active Branches</p>
        </div>
        
        <div class="stat-card-mini">
            <div class="stat-icon">⛔</div>
            <p class="stat-number red"><?= $total_inactive ?></p>
            <p class="stat-label">Inactive Branches</p>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- BRANCHES LIST - WITH BLUE TABLE HEADER -->
    <!-- ================================================================ -->
    <div class="card card-shadow">
        <div class="card-header card-blue-header">
            <h3 class="card-title">
                <i class="fas fa-list title-blue mr-2"></i>
                All Branches
                <span class="text-sm font-normal text-gray-400">(<?= $total_branches ?> branches)</span>
            </h3>
            <div class="flex gap-2">
                <?php if (!empty($search)): ?>
                    <a href="branches.php" class="btn btn-outline btn-sm">
                        <i class="fas fa-times"></i> Clear Search
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="data-table table-blue w-full" id="branchesTable">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Branch Name</th>
                        <th>Location</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th style="width: 100px;">Status</th>
                        <th style="width: 110px;">Created</th>
                        <th style="width: 140px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($branches) > 0): ?>
                        <?php $i = $offset + 1; foreach ($branches as $branch): ?>
                            <tr>
                                <td class="font-bold text-blue-600 dark:text-blue-400"><?= $i++ ?></td>
                                <td class="font-semibold"><?= htmlspecialchars($branch['name']) ?></td>
                                <td><?= htmlspecialchars($branch['location']) ?></td>
                                <td><?= htmlspecialchars($branch['phone'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($branch['email'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge <?= $branch['status'] === 'active' ? 'badge-active' : 'badge-inactive' ?>">
                                        <i class="fas fa-<?= $branch['status'] === 'active' ? 'check-circle' : 'times-circle' ?> mr-1"></i>
                                        <?= ucfirst($branch['status']) ?>
                                    </span>
                                </td>
                                <td class="text-xs text-gray-500 dark:text-gray-400"><?= date('M d, Y', strtotime($branch['created_at'])) ?></td>
                                <td>
                                    <div class="flex gap-1 flex-wrap">
                                        <!-- Edit Button -->
                                        <a href="add_branch.php?id=<?= $branch['id'] ?>" class="btn btn-blue btn-sm" title="Edit Branch">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <!-- Toggle Active/Inactive Button -->
                                        <?php if ($branch['status'] === 'active'): ?>
                                            <a href="?toggle=<?= $branch['id'] ?>&page=<?= $page ?><?= $search ? '&search='.urlencode($search) : '' ?>" 
                                               class="btn-toggle-inactive" 
                                               onclick="return confirm('Are you sure you want to deactivate this branch?\n\nBranch: <?= htmlspecialchars($branch['name']) ?>')" 
                                               title="Deactivate Branch">
                                                <i class="fas fa-times-circle"></i> Deactivate
                                            </a>
                                        <?php else: ?>
                                            <a href="?toggle=<?= $branch['id'] ?>&page=<?= $page ?><?= $search ? '&search='.urlencode($search) : '' ?>" 
                                               class="btn-toggle-active" 
                                               onclick="return confirm('Are you sure you want to activate this branch?\n\nBranch: <?= htmlspecialchars($branch['name']) ?>')" 
                                               title="Activate Branch">
                                                <i class="fas fa-check-circle"></i> Activate
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-8 text-gray-400">
                                <i class="fas fa-store-alt text-4xl block mb-3" style="color: #0B5ED7;"></i>
                                <p class="text-lg font-medium" style="color: #1E293B; dark:text-white;">
                                    <?= !empty($search) ? 'No branches found matching "' . htmlspecialchars($search) . '"' : 'No branches found' ?>
                                </p>
                                <p class="text-sm">
                                    <?= !empty($search) ? 'Try a different search term' : 'Click "Add New Branch" to create one' ?>
                                </p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- ================================================================ -->
        <!-- PAGINATION -->
        <!-- ================================================================ -->
        <?php if ($total_pages > 1): ?>
            <div class="flex flex-wrap justify-between items-center gap-3 mt-4 pt-3 border-t border-gray-200 dark:border-gray-700">
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    Showing <?= $offset + 1 ?> - <?= min($offset + $per_page, $total_branches) ?> of <?= $total_branches ?> branches
                </div>
                
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?><?= $search ? '&search='.urlencode($search) : '' ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-link disabled">
                            <i class="fas fa-chevron-left"></i>
                        </span>
                    <?php endif; ?>
                    
                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                        <a href="?page=<?= $p ?><?= $search ? '&search='.urlencode($search) : '' ?>" 
                           class="page-link <?= $p === $page ? 'active' : '' ?>">
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?><?= $search ? '&search='.urlencode($search) : '' ?>" class="page-link">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-link disabled">
                            <i class="fas fa-chevron-right"></i>
                        </span>
                    <?php endif; ?>
                </div>
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
            Branches Management
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
            document.cookie = "dark_mode=false; path=/";
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
            document.cookie = "dark_mode=true; path=/";
        }
    });

    // ================================================================
    // DOM ELEMENTS
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
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
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 3500);
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

    console.log('%c🏥 Braick Dispensary - Branches Management', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📊 Total Branches: <?= $total_all ?>', 'font-size:13px; color:#059669;');
    console.log('%c✅ Active: <?= $total_active ?> | ⛔ Inactive: <?= $total_inactive ?>', 'font-size:13px; color:#64748B;');
    console.log('%c🔵 Blue Theme Applied to Table Headers', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🔄 Branches use Activate/Deactivate instead of Delete', 'font-size:13px; color:#7B2FBE;');
</script>

</body>
</html>