<?php
// ================================================================
// FILE: frontend/pages/admin/view_department.php
// SUPER ADMIN - VIEW DEPARTMENT DETAILS
// WITH SHARED HEADER & SIDEBAR - DATE FIXED
// BRAICK DISPENSARY
// ================================================================

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit;
}

require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

$dept_id = (int)($_GET['id'] ?? 0);
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($dept_id <= 0) {
    header('Location: departments.php');
    exit;
}

// ================================================================
// GET DEPARTMENT DATA
// ================================================================
$stmt = $db->prepare("SELECT * FROM departments WHERE id = ?");
$stmt->execute([$dept_id]);
$department = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$department) {
    header('Location: departments.php');
    exit;
}

// ================================================================
// GET CREATED DATE - SHORT FORMAT
// ================================================================
$created_date = 'N/A';
$created_short = 'N/A';
if (isset($department['created_at']) && !empty($department['created_at'])) {
    $created_date = date('F d, Y h:i A', strtotime($department['created_at']));
    $created_short = date('d/m/Y', strtotime($department['created_at'])); // SHORT FORMAT
}

// ================================================================
// GET EMPLOYEES IN THIS DEPARTMENT
// ================================================================
$employees = [];
$employee_count = 0;
try {
    $stmt = $db->prepare("
        SELECT u.id, u.full_name, u.email, u.role, u.status 
        FROM users u
        JOIN employee_departments ed ON u.id = ed.user_id
        WHERE ed.department_id = ?
        ORDER BY u.full_name
    ");
    $stmt->execute([$dept_id]);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $employee_count = count($employees);
} catch (Exception $e) {
    $employees = [];
    $employee_count = 0;
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
// GET BRANCHES FOR SELECTOR
// ================================================================
$branches = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $branches[] = $row;
}

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
       VIEW DEPARTMENT - BLUE & GREEN THEME
       ================================================================ */
    
    .info-card {
        background: var(--bg-card);
        border-radius: 18px;
        padding: 24px 28px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
    }
    
    .info-card:hover {
        border-color: #0B5ED7;
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.06);
    }
    
    [data-theme="dark"] .info-card {
        background: #1E293B;
        border-color: #334155;
    }
    
    [data-theme="dark"] .info-card:hover {
        border-color: #0B5ED7;
    }
    
    .info-card-title {
        font-size: 1.05rem;
        font-weight: 700;
        color: #0B5ED7;
        border-bottom: 2px solid var(--border-color);
        padding-bottom: 12px;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    [data-theme="dark"] .info-card-title {
        color: #6EA8FE;
    }
    
    .info-label {
        font-size: 0.75rem;
        color: var(--text-secondary);
        font-weight: 500;
    }
    
    .info-value {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--text-primary);
    }
    
    .badge-status {
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: white;
        border: none;
    }
    
    .badge-status.success { background: #059669; color: white; }
    .badge-status.danger { background: #EF4444; color: white; }
    .badge-status.warning { background: #F59E0B; color: white; }
    .badge-status.blue { background: #0B5ED7; color: white; }
    
    .stat-box {
        text-align: center;
        padding: 16px 12px;
        border-radius: 16px;
        transition: all 0.3s ease;
        border: 2px solid var(--border-color);
        background: var(--bg-card);
    }
    
    .stat-box:hover {
        transform: translateY(-4px);
        border-color: #0B5ED7;
        box-shadow: 0 8px 25px rgba(11, 94, 215, 0.1);
    }
    
    [data-theme="dark"] .stat-box {
        background: #1E293B;
        border-color: #334155;
    }
    
    [data-theme="dark"] .stat-box:hover {
        border-color: #6EA8FE;
    }
    
    .stat-box .stat-number {
        font-size: 1.8rem;
        font-weight: 700;
        color: #0B5ED7;
    }
    
    [data-theme="dark"] .stat-box .stat-number {
        color: #6EA8FE;
    }
    
    .stat-box .stat-number-small {
        font-size: 1.2rem;
        font-weight: 700;
        color: #0B5ED7;
    }
    
    [data-theme="dark"] .stat-box .stat-number-small {
        color: #6EA8FE;
    }
    
    .stat-box .stat-icon-bg {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        margin: 0 auto 6px;
    }
    
    .stat-box .stat-icon-bg.blue {
        background: #E8F0FE;
        color: #0B5ED7;
    }
    
    [data-theme="dark"] .stat-box .stat-icon-bg.blue {
        background: #1E3A5F;
        color: #6EA8FE;
    }
    
    .stat-box .stat-icon-bg.green {
        background: #E6F7EE;
        color: #059669;
    }
    
    [data-theme="dark"] .stat-box .stat-icon-bg.green {
        background: #1A3A2A;
        color: #34D399;
    }
    
    .stat-box .stat-label {
        font-size: 0.7rem;
        color: var(--text-secondary);
        font-weight: 500;
        margin-top: 2px;
    }
    
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
    .badge-blue { background: #0B5ED7; color: white; }
    
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
            <input type="text" id="searchInput" placeholder="Search patients, doctors, medicines...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <select id="branchSelector" class="branch-selector" onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php foreach ($branches as $branch): ?>
                <option value="<?= $branch['id'] ?>" <?= $selected_branch_id == $branch['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($branch['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        
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
                <i class="fas fa-building mr-2" style="color: #0B5ED7;"></i> Department Details
            </h1>
            <p class="page-subtitle">
                View department information and employees
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-building mr-1"></i> <?= htmlspecialchars($department['name']) ?>
                </span>
                <span class="ml-2 inline-flex bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs border border-green-200">
                    <i class="fas fa-hashtag mr-1"></i> ID: <?= $department['id'] ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="edit_department.php?id=<?= $department['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-blue btn-sm">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="departments.php?branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- DEPARTMENT INFORMATION & STATISTICS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">
        
        <!-- Department Information Card -->
        <div class="info-card animate-fade-in-up">
            <h3 class="info-card-title">
                <i class="fas fa-info-circle"></i> Department Information
            </h3>
            <div class="space-y-3">
                <div>
                    <p class="info-label">Department Name</p>
                    <p class="info-value">
                        <strong><?= htmlspecialchars($department['name']) ?></strong>
                        <span class="badge-status blue ml-2">#<?= $department['id'] ?></span>
                    </p>
                </div>
                <div>
                    <p class="info-label">Head of Department</p>
                    <p class="info-value">
                        <?php 
                            $hod = $department['head_of_department'] ?? '';
                            echo !empty($hod) ? htmlspecialchars($hod) : '<span class="text-gray-400">Not assigned</span>';
                        ?>
                    </p>
                </div>
                <div>
                    <p class="info-label">Description</p>
                    <p class="info-value">
                        <?php 
                            $desc = $department['description'] ?? '';
                            echo !empty($desc) ? htmlspecialchars($desc) : '<span class="text-gray-400">No description</span>';
                        ?>
                    </p>
                </div>
                <div>
                    <p class="info-label">Status</p>
                    <p class="info-value">
                        <?php if (isset($department['status']) && $department['status'] === 'active'): ?>
                            <span class="badge-status success">
                                <i class="fas fa-circle text-[6px]"></i> Active
                            </span>
                        <?php elseif (isset($department['status']) && $department['status'] === 'inactive'): ?>
                            <span class="badge-status danger">
                                <i class="fas fa-circle text-[6px]"></i> Inactive
                            </span>
                        <?php else: ?>
                            <span class="badge-status warning">
                                <i class="fas fa-circle text-[6px]"></i> <?= isset($department['status']) ? ucfirst($department['status']) : 'Unknown' ?>
                            </span>
                        <?php endif; ?>
                    </p>
                </div>
                <div>
                    <p class="info-label">Created</p>
                    <p class="info-value"><?= $created_date ?></p>
                </div>
            </div>
        </div>
        
        <!-- Department Statistics Card -->
        <div class="info-card animate-fade-in-up">
            <h3 class="info-card-title">
                <i class="fas fa-chart-bar"></i> Department Statistics
            </h3>
            <div class="grid grid-cols-2 gap-3">
                <!-- Employees -->
                <div class="stat-box">
                    <div class="stat-icon-bg blue">
                        <i class="fas fa-users"></i>
                    </div>
                    <p class="stat-number"><?= $employee_count ?></p>
                    <p class="stat-label">Employees</p>
                </div>
                
                <!-- Created Date - SHORT FORMAT -->
                <div class="stat-box">
                    <div class="stat-icon-bg green">
                        <i class="fas fa-calendar-plus"></i>
                    </div>
                    <p class="stat-number-small"><?= $created_short ?></p>
                    <p class="stat-label">Created</p>
                </div>
                
                <!-- Department ID -->
                <div class="stat-box">
                    <div class="stat-icon-bg blue">
                        <i class="fas fa-hashtag"></i>
                    </div>
                    <p class="stat-number"><?= $department['id'] ?></p>
                    <p class="stat-label">Dept ID</p>
                </div>
                
                <!-- Status -->
                <div class="stat-box">
                    <div class="stat-icon-bg green">
                        <i class="fas fa-circle"></i>
                    </div>
                    <p class="stat-number-small">
                        <?= isset($department['status']) && $department['status'] === 'active' ? '✅' : '⛔' ?>
                    </p>
                    <p class="stat-label">Status</p>
                </div>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- EMPLOYEES IN THIS DEPARTMENT -->
    <!-- ================================================================ -->
    <div class="info-card animate-fade-in-up">
        <h3 class="info-card-title">
            <i class="fas fa-users"></i> Employees in this Department
            <span class="text-sm font-normal text-gray-400">(<?= $employee_count ?> employees)</span>
        </h3>
        
        <?php if ($employee_count > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="border-radius: 8px 0 0 0;">#</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th style="border-radius: 0 8px 0 0;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $index => $emp): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td class="font-medium"><?= htmlspecialchars($emp['full_name']) ?></td>
                                <td><?= htmlspecialchars($emp['email']) ?></td>
                                <td>
                                    <span class="badge badge-blue">
                                        <i class="fas fa-circle text-[6px]"></i>
                                        <?= ucfirst($emp['role']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?= $emp['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>">
                                        <i class="fas fa-circle text-[5px]"></i>
                                        <?= ucfirst($emp['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-8 text-gray-400">
                <i class="fas fa-users text-3xl block mb-2"></i>
                <p>No employees assigned to this department.</p>
                <p class="text-sm mt-1">Edit this department to assign employees.</p>
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
            View Department
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
    // BRANCH SWITCHER
    // ================================================================
    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        window.location.href = url.toString();
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
            setTimeout(function() {
                toast.style.display = 'none';
            }, 400);
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

    console.log('%c🏢 Braick - View Department', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Department: <?= htmlspecialchars($department['name']) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📅 Created: <?= $created_short ?>', 'font-size:13px; color:#64748B;');
    console.log('%c👥 Employees: <?= $employee_count ?>', 'font-size:13px; color:#64748B;');
</script>

</body>
</html>