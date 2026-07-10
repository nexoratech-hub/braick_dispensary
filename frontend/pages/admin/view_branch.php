<?php
// ================================================================
// FILE: frontend/pages/admin/view_branch.php
// SUPER ADMIN - VIEW BRANCH DETAILS
// WITH SHARED HEADER & SIDEBAR - FULLY FIXED
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

// ================================================================
// GET BRANCH ID FROM URL
// ================================================================
$view_branch_id = (int)($_GET['id'] ?? 0);

if ($view_branch_id <= 0) {
    header('Location: branches.php');
    exit;
}

// ================================================================
// BRANCH FILTER FOR BACK BUTTON
// ================================================================
$selected_branch_id = $_GET['branch'] ?? 'all';
$branch_name = 'All Branches';

if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$selected_branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) {
        $branch_name = $branch_data['name'];
    }
} else {
    $selected_branch_id = 'all';
}

// ================================================================
// GET BRANCH DATA
// ================================================================
$stmt = $db->prepare("SELECT * FROM branches WHERE id = ?");
$stmt->execute([$view_branch_id]);
$branch = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$branch) {
    header('Location: branches.php');
    exit;
}

// ================================================================
// GET BRANCH STATISTICS
// ================================================================
$filter_branch_id = $view_branch_id;

// 1. Employees
$stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE branch_id = ? AND role != 'admin'");
$stmt->execute([$filter_branch_id]);
$employee_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 2. Patients
$stmt = $db->prepare("SELECT COUNT(*) as count FROM patients WHERE branch_id = ?");
$stmt->execute([$filter_branch_id]);
$patient_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 3. Visits
$stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE branch_id = ?");
$stmt->execute([$filter_branch_id]);
$visit_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 4. Appointments
$stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE branch_id = ?");
$stmt->execute([$filter_branch_id]);
$appointment_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// 5. Doctors
$stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE branch_id = ? AND role = 'doctor' AND status = 'active'");
$stmt->execute([$filter_branch_id]);
$doctor_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// GET ALL BRANCHES FOR SELECTOR
// ================================================================
$branches = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $branches[] = $row;
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
    .stat-box {
        text-align: center;
        padding: 20px 16px;
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
        font-size: 2.2rem;
        font-weight: 700;
        color: #0B5ED7;
    }
    
    [data-theme="dark"] .stat-box .stat-number {
        color: #6EA8FE;
    }
    
    .stat-box .stat-icon-bg {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        margin: 0 auto 8px;
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
        font-size: 0.8rem;
        color: var(--text-secondary);
        font-weight: 500;
        margin-top: 4px;
    }
    
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
        border-color: #6EA8FE;
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
    
    .branch-id-badge {
        background: #0B5ED7;
        color: white;
        padding: 2px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
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
            <input type="text" id="searchInput" placeholder="Search...">
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
                <i class="fas fa-store-alt mr-2" style="color: var(--blue-600);"></i> Branch Details
            </h1>
            <p class="page-subtitle">
                View branch information and statistics
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($branch['name']) ?>
                </span>
                <span class="ml-2 inline-flex bg-purple-100 text-purple-700 px-3 py-1 rounded-full text-xs border border-purple-200">
                    <i class="fas fa-hashtag mr-1"></i> ID: <?= $branch['id'] ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="edit_branch.php?id=<?= $branch['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-blue btn-sm">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="branches.php?branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- BRANCH INFORMATION & STATISTICS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">
        
        <!-- Branch Information Card -->
        <div class="info-card animate-fade-in-up">
            <h3 class="info-card-title">
                <i class="fas fa-info-circle"></i> Branch Information
            </h3>
            <div class="space-y-3">
                <!-- Branch Name with ID -->
                <div>
                    <p class="info-label">Branch Name</p>
                    <p class="info-value">
                        <?= htmlspecialchars($branch['name']) ?>
                        <span class="branch-id-badge ml-2">#<?= $branch['id'] ?></span>
                    </p>
                </div>
                
                <!-- Location -->
                <div>
                    <p class="info-label">Location</p>
                    <p class="info-value">
                        <?php 
                            $location = $branch['location'] ?? '';
                            echo !empty($location) ? htmlspecialchars($location) : '<span class="text-gray-400">Not specified</span>';
                        ?>
                    </p>
                </div>
                
                <!-- Phone -->
                <div>
                    <p class="info-label">Phone</p>
                    <p class="info-value">
                        <?php 
                            $phone = $branch['phone'] ?? '';
                            echo !empty($phone) ? htmlspecialchars($phone) : '<span class="text-gray-400">Not specified</span>';
                        ?>
                    </p>
                </div>
                
                <!-- Email -->
                <div>
                    <p class="info-label">Email</p>
                    <p class="info-value">
                        <?php 
                            $email = $branch['email'] ?? '';
                            echo !empty($email) ? htmlspecialchars($email) : '<span class="text-gray-400">Not specified</span>';
                        ?>
                    </p>
                </div>
                
                <!-- Status -->
                <div>
                    <p class="info-label">Status</p>
                    <p class="info-value">
                        <?php if (isset($branch['status']) && $branch['status'] === 'active'): ?>
                            <span class="badge-status success">
                                <i class="fas fa-circle text-[6px]"></i> Active
                            </span>
                        <?php elseif (isset($branch['status']) && $branch['status'] === 'inactive'): ?>
                            <span class="badge-status danger">
                                <i class="fas fa-circle text-[6px]"></i> Inactive
                            </span>
                        <?php else: ?>
                            <span class="badge-status warning">
                                <i class="fas fa-circle text-[6px]"></i> <?= isset($branch['status']) ? ucfirst($branch['status']) : 'Unknown' ?>
                            </span>
                        <?php endif; ?>
                    </p>
                </div>
                
                <!-- Created -->
                <div>
                    <p class="info-label">Created</p>
                    <p class="info-value">
                        <?php 
                            if (isset($branch['created_at']) && !empty($branch['created_at'])) {
                                echo date('F d, Y h:i A', strtotime($branch['created_at']));
                            } else {
                                echo '<span class="text-gray-400">N/A</span>';
                            }
                        ?>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Branch Statistics Card -->
        <div class="info-card animate-fade-in-up">
            <h3 class="info-card-title">
                <i class="fas fa-chart-bar"></i> Branch Statistics
            </h3>
            <div class="grid grid-cols-2 gap-4">
                <!-- Employees -->
                <div class="stat-box">
                    <div class="stat-icon-bg blue">
                        <i class="fas fa-users"></i>
                    </div>
                    <p class="stat-number"><?= $employee_count ?></p>
                    <p class="stat-label">Employees</p>
                </div>
                
                <!-- Patients -->
                <div class="stat-box">
                    <div class="stat-icon-bg green">
                        <i class="fas fa-user-injured"></i>
                    </div>
                    <p class="stat-number"><?= $patient_count ?></p>
                    <p class="stat-label">Patients</p>
                </div>
                
                <!-- Visits -->
                <div class="stat-box">
                    <div class="stat-icon-bg blue">
                        <i class="fas fa-clinic-medical"></i>
                    </div>
                    <p class="stat-number"><?= $visit_count ?></p>
                    <p class="stat-label">Visits</p>
                </div>
                
                <!-- Doctors -->
                <div class="stat-box">
                    <div class="stat-icon-bg green">
                        <i class="fas fa-user-md"></i>
                    </div>
                    <p class="stat-number"><?= $doctor_count ?></p>
                    <p class="stat-label">Doctors</p>
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4 mt-4">
                <!-- Appointments -->
                <div class="stat-box" style="padding: 12px 16px;">
                    <div class="stat-icon-bg blue" style="width: 36px; height: 36px; font-size: 1rem;">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <p class="stat-number" style="font-size: 1.5rem;"><?= $appointment_count ?></p>
                    <p class="stat-label">Appointments</p>
                </div>
                
                <!-- Branch ID -->
                <div class="stat-box" style="padding: 12px 16px;">
                    <div class="stat-icon-bg green" style="width: 36px; height: 36px; font-size: 1rem;">
                        <i class="fas fa-hashtag"></i>
                    </div>
                    <p class="stat-number" style="font-size: 1.5rem;"><?= $branch['id'] ?></p>
                    <p class="stat-label">Branch ID</p>
                </div>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            View Branch
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

    console.log('%c🏢 Braick - View Branch', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Branch: <?= htmlspecialchars($branch['name']) ?> (ID: <?= $branch['id'] ?>)', 'font-size:13px; color:#059669;');
    console.log('%c📍 Location: <?= htmlspecialchars($branch['location'] ?? 'Not specified') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📱 Phone: <?= htmlspecialchars($branch['phone'] ?? 'Not specified') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📧 Email: <?= htmlspecialchars($branch['email'] ?? 'Not specified') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c📊 Status: <?= $branch['status'] ?? 'Unknown' ?>', 'font-size:13px; color:#64748B;');
    console.log('%c🌙 Dark Mode: ' + (localStorage.getItem('darkMode') === 'true' ? 'ON' : 'OFF'), 'font-size:13px; color:#64748B;');
</script>

</body>
</html>