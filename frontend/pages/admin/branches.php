<?php
// ================================================================
// FILE: frontend/pages/admin/branches.php
// SUPER ADMIN - BRANCH MANAGEMENT
// BRAICK DISPENSARY
// WITH SHARED HEADER, SIDEBAR & BRANCH FILTER
// ================================================================

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit;
}

require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// BRANCH SELECTION FOR FILTER
// ================================================================
$selected_branch_id = $_GET['branch'] ?? 'all';
$branch_name = 'All Branches';

if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $branch_id = (int)$selected_branch_id;
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) {
        $branch_name = $branch_data['name'];
    }
} else {
    $selected_branch_id = 'all';
}

// ================================================================
// GET STATISTICS FOR SIDEBAR
// ================================================================
$total_employees = 0;
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role != 'admin'");
$total_employees = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$total_doctors = 0;
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'doctor' AND status = 'active'");
$total_doctors = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$total_branches = 0;
$stmt = $db->query("SELECT COUNT(*) as count FROM branches WHERE status = 'active'");
$total_branches = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$pending_lab_tests = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM lab_tests WHERE status = 'pending'");
    $pending_lab_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {
    $pending_lab_tests = 0;
}

$pending_prescriptions = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM prescriptions WHERE status = 'pending'");
    $pending_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {
    $pending_prescriptions = 0;
}

// ================================================================
// HANDLE DELETE
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'delete') {
        $branch_id = (int)$_POST['branch_id'];
        
        // Check if branch has users
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE branch_id = ?");
        $stmt->execute([$branch_id]);
        $user_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        if ($user_count > 0) {
            $message = "Cannot delete branch! There are $user_count employees assigned to this branch.";
            $message_type = 'error';
        } else {
            // Check if branch has patients
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM patients WHERE branch_id = ?");
            $stmt->execute([$branch_id]);
            $patient_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
            
            if ($patient_count > 0) {
                $message = "Cannot delete branch! There are $patient_count patients assigned to this branch.";
                $message_type = 'error';
            } else {
                $stmt = $db->prepare("DELETE FROM branches WHERE id = ?");
                if ($stmt->execute([$branch_id])) {
                    $message = "Branch deleted successfully!";
                    $message_type = 'success';
                    // Refresh branch count
                    $stmt = $db->query("SELECT COUNT(*) as count FROM branches WHERE status = 'active'");
                    $total_branches = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
                } else {
                    $message = "Failed to delete branch!";
                    $message_type = 'error';
                }
            }
        }
    }
}

// ================================================================
// GET ALL BRANCHES WITH FILTER
// ================================================================
$branches = [];
$query = "SELECT * FROM branches";
if ($selected_branch_id !== 'all') {
    $query .= " WHERE id = " . (int)$selected_branch_id;
}
$query .= " ORDER BY name";
$branches = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET BRANCHES FOR SELECTOR
// ================================================================
$branches_list = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
$branches_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER
// ================================================================
include_once '../../components/admin_header.php';

// ================================================================
// INCLUDE SHARED SIDEBAR
// ================================================================
$selected_branch_id = $selected_branch_id ?? 'all';
$total_employees = $total_employees ?? 0;
$total_doctors = $total_doctors ?? 0;
$total_branches = $total_branches ?? 0;
$pending_lab_tests = $pending_lab_tests ?? 0;
$pending_prescriptions = $pending_prescriptions ?? 0;
include_once '../../components/admin_sidebar.php';
?>

<style>
    /* ================================================================
       ADDITIONAL TABLE STYLES
       ================================================================ */
    
    /* Card */
    .card {
        background: var(--bg-card);
        border-radius: 20px;
        padding: 20px 24px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
    }
    
    .card:hover {
        border-color: #0B5ED7;
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.06);
    }
    
    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .card-title {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
    }
    
    .card-title .title-blue { color: #0B5ED7; }
    
    /* Table */
    .table-wrap { overflow-x: auto; }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }
    
    .data-table thead th {
        text-align: left;
        padding: 10px 14px;
        font-weight: 700;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: white;
        background: #0B5ED7;
        border-bottom: 3px solid #0B3D8A;
        white-space: nowrap;
    }
    
    .data-table tbody tr:nth-child(even) {
        background: #E8F0FE;
    }
    
    .data-table tbody tr:nth-child(odd) {
        background: var(--bg-card);
    }
    
    .data-table tbody tr:hover {
        background: #D1FAE5;
    }
    
    .data-table td {
        padding: 10px 14px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        vertical-align: middle;
    }
    
    /* Badges */
    .badge {
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: white;
        border: none;
    }
    
    .badge-success { background: #059669; color: white; }
    .badge-danger { background: #EF4444; color: white; }
    .badge-info { background: #0B5ED7; color: white; }
    
    /* Buttons */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.75rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        white-space: nowrap;
    }
    
    .btn-blue {
        background: #0B5ED7;
        color: white;
    }
    .btn-blue:hover {
        background: #0A4CA8;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }
    
    .btn-outline {
        background: transparent;
        color: var(--text-secondary);
        border: 2px solid var(--border-color);
    }
    .btn-outline:hover {
        background: var(--bg-body);
        border-color: #0B5ED7;
        color: #0B5ED7;
    }
    
    .btn-sm { padding: 4px 10px; font-size: 0.7rem; border-radius: 6px; }
    
    .btn-view {
        background: #0B5ED7;
        color: white;
        padding: 4px 10px;
        font-size: 0.7rem;
        border-radius: 6px;
    }
    .btn-view:hover {
        background: #0A4CA8;
        transform: scale(1.05);
    }
    
    .btn-edit {
        background: #059669;
        color: white;
        padding: 4px 10px;
        font-size: 0.7rem;
        border-radius: 6px;
    }
    .btn-edit:hover {
        background: #047857;
        transform: scale(1.05);
    }
    
    .btn-delete {
        background: #EF4444;
        color: white;
        padding: 4px 10px;
        font-size: 0.7rem;
        border-radius: 6px;
    }
    .btn-delete:hover {
        background: #DC2626;
        transform: scale(1.05);
    }
    
    .action-buttons {
        display: flex;
        align-items: center;
        gap: 4px;
        flex-wrap: nowrap;
        justify-content: center;
    }
    
    /* Page Header */
    .page-header {
        border-bottom: 3px solid #0B5ED7;
        padding-bottom: 12px;
    }
    
    .page-header .page-title {
        color: #0B3D8A;
        font-size: 1.8rem;
        font-weight: 700;
    }
    
    [data-theme="dark"] .page-header .page-title {
        color: #6EA8FE;
    }
    
    .page-header .page-subtitle {
        color: var(--text-secondary);
        font-size: 0.9rem;
    }
    
    .page-header .branch-tag {
        background: #059669;
        color: white;
        padding: 3px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .footer {
        padding: 14px 0;
        border-top: 2px solid var(--border-color);
        margin-top: 20px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--text-secondary);
    }
    
    .footer .footer-brand { color: #0B5ED7; font-weight: 600; }
    
    /* Responsive */
    @media (max-width: 640px) {
        .card {
            padding: 12px 14px;
        }
        .btn {
            padding: 4px 8px;
            font-size: 0.65rem;
        }
        .data-table {
            font-size: 0.75rem;
        }
        .data-table th,
        .data-table td {
            padding: 6px 8px;
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
            <input type="text" id="searchInput" placeholder="Search branches...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <!-- Branch Selector - FILTER -->
        <select id="branchSelector" class="branch-selector" onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches_list as $branch): ?>
                <option value="<?= $branch['id'] ?>" <?= $selected_branch_id == $branch['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($branch['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <!-- Dark Mode Toggle -->
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
                <i class="fas fa-store-alt mr-2" style="color: var(--blue-600);"></i> Branch Management
            </h1>
            <p class="page-subtitle">
                Manage all dispensary branches
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name) ?>
                </span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-list mr-1"></i> <?= count($branches) ?> branches
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="add_branch.php" class="btn btn-blue btn-sm">
                <i class="fas fa-plus"></i> Add Branch
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="p-4 rounded-xl mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- BRANCHES TABLE -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-blue mr-2"></i> All Branches
                <span class="text-sm font-normal text-gray-400">(<?= count($branches) ?> branches)</span>
            </h3>
            <div class="flex gap-2">
                <button onclick="exportTable()" class="btn btn-outline btn-sm">
                    <i class="fas fa-file-export"></i> Export
                </button>
            </div>
        </div>
        
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="border-radius: 8px 0 0 0;">#</th>
                        <th>Branch Name</th>
                        <th>Location</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th style="border-radius: 0 8px 0 0; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($branches) > 0): ?>
                        <?php foreach ($branches as $index => $branch): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td class="font-medium"><?= htmlspecialchars($branch['name']) ?></td>
                                <td><?= htmlspecialchars($branch['location'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($branch['phone'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($branch['email'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge <?= $branch['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>">
                                        <i class="fas fa-circle text-[5px]"></i>
                                        <?= ucfirst($branch['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <!-- VIEW Button -->
                                        <a href="view_branch.php?id=<?= $branch['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                           class="btn btn-view" 
                                           title="View Branch">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <!-- EDIT Button -->
                                        <a href="edit_branch.php?id=<?= $branch['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                           class="btn btn-edit" 
                                           title="Edit Branch">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <!-- DELETE Button -->
                                        <button onclick="deleteBranch(<?= $branch['id'] ?>)" 
                                                class="btn btn-delete" 
                                                title="Delete Branch">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-8 text-gray-400">
                                <i class="fas fa-store-alt text-3xl block mb-2"></i>
                                <?php if ($selected_branch_id !== 'all'): ?>
                                    No branches found for the selected branch.
                                <?php else: ?>
                                    No branches found. Click "Add Branch" to get started.
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- QUICK STATS - Branch Summary -->
    <!-- ================================================================ -->
    <?php if (count($branches) > 0): ?>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-5">
        <div class="card text-center">
            <p class="text-2xl font-bold text-[#0B5ED7]"><?= count($branches) ?></p>
            <p class="text-sm text-gray-500">Total Branches</p>
        </div>
        <div class="card text-center">
            <p class="text-2xl font-bold text-[#059669]">
                <?php
                    $active = 0;
                    foreach ($branches as $b) {
                        if ($b['status'] === 'active') $active++;
                    }
                    echo $active;
                ?>
            </p>
            <p class="text-sm text-gray-500">Active Branches</p>
        </div>
        <div class="card text-center">
            <p class="text-2xl font-bold text-[#EF4444]">
                <?php
                    $inactive = 0;
                    foreach ($branches as $b) {
                        if ($b['status'] === 'inactive') $inactive++;
                    }
                    echo $inactive;
                ?>
            </p>
            <p class="text-sm text-gray-500">Inactive Branches</p>
        </div>
        <div class="card text-center">
            <p class="text-2xl font-bold text-[#0B5ED7]"><?= $total_employees ?></p>
            <p class="text-sm text-gray-500">Total Employees</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Branch Management
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
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
        }
    });

    // ================================================================
    // DOM ELEMENTS
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    var branchSelector = document.getElementById('branchSelector');
    var toast = document.getElementById('toast');

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
    // BRANCH SWITCHER (FILTER)
    // ================================================================
    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        window.location.href = url.toString();
    }

    // ================================================================
    // DELETE BRANCH
    // ================================================================
    function deleteBranch(branchId) {
        if (confirm('⚠️ Are you sure you want to DELETE this branch?\n\nThis action CANNOT be undone!\n\nEmployees and patients assigned to this branch will be affected.')) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="branch_id" value="${branchId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }

    // ================================================================
    // EXPORT
    // ================================================================
    function exportTable() {
        showToast('Export', 'Preparing branch list export...', 'info');
        var branch = '<?= $selected_branch_id ?>';
        window.location.href = 'reports.php?export=branches&branch=' + branch;
    }

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
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            var branch = '<?= $selected_branch_id ?>';
            window.location.href = 'search.php?q=' + encodeURIComponent(query) + '&branch=' + branch;
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
        document.getElementById('currentDateTime').textContent = dateStr + ' • ' + timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    console.log('%c🏢 Braick - Branch Management', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Shared Header & Sidebar: ACTIVE', 'font-size:13px; color:#059669;');
    console.log('%c🔍 Branch Filter: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c🌙 Dark Mode: ' + (localStorage.getItem('darkMode') === 'true' ? 'ON' : 'OFF'), 'font-size:13px; color:#64748B;');
</script>

</body>
</html>