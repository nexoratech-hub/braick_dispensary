<?php
// ================================================================
// FILE: frontend/components/admin_sidebar.php
// SUPER ADMIN - SHARED SIDEBAR
// BACKGROUND: BLUE | HOVER: GREEN
// NAVIGATION KWA ALL DASHBOARDS
// BRAICK DISPENSARY
// ================================================================

// Pass these variables from each page
$selected_branch_id = $selected_branch_id ?? 'all';
$total_employees = $total_employees ?? 0;
$total_doctors = $total_doctors ?? 0;
$total_branches = $total_branches ?? 0;
$pending_lab_tests = $pending_lab_tests ?? 0;
$pending_prescriptions = $pending_prescriptions ?? 0;

// Detect current page and module
$current_page = basename($_SERVER['PHP_SELF']);
$current_module = basename(dirname($_SERVER['PHP_SELF']));

// ================================================================
// FUNCTION TO CHECK ACTIVE STATE - PAGE BASED
// ================================================================
function isActive($page) {
    global $current_page;
    if ($page === $current_page) {
        return 'active';
    }
    return '';
}

// ================================================================
// FUNCTION TO CHECK ACTIVE MODULE
// ================================================================
function isModuleActive($module_name) {
    global $current_module;
    if ($module_name === $current_module) {
        return 'active';
    }
    return '';
}

// ================================================================
// FUNCTION TO CHECK ADMIN PAGES
// ================================================================
function isAdminPage($pages) {
    global $current_page;
    if (in_array($current_page, $pages)) {
        return 'active';
    }
    return '';
}

// ================================================================
// GET MODULE COUNTS BASED ON BRANCH
// ================================================================
$module_counts = [];

try {
    global $db;
    if (isset($db)) {
        $modules = ['pharmacy', 'reception', 'laboratory', 'cashier'];
        foreach ($modules as $module) {
            if ($selected_branch_id === 'all') {
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role = ? AND status = 'active'");
                $stmt->execute([$module]);
            } else {
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role = ? AND status = 'active' AND branch_id = ?");
                $stmt->execute([$module, (int)$selected_branch_id]);
            }
            $module_counts[$module] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        }
    }
} catch (Exception $e) {
    $module_counts = ['pharmacy' => 0, 'reception' => 0, 'laboratory' => 0, 'cashier' => 0];
}

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
?>

<style>
    /* ================================================================
       SIDEBAR STYLES - BLUE BACKGROUND, GREEN HOVER
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
    
    .sidebar::-webkit-scrollbar { width: 5px; }
    .sidebar::-webkit-scrollbar-track { background: #0B3D8A; }
    .sidebar::-webkit-scrollbar-thumb { background: #0AA84F; border-radius: 10px; }
    
    .sidebar-brand {
        padding: 22px 20px 16px;
        border-bottom: 2px solid #0B3D8A;
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
    
    /* ===== BRANCH SELECTOR IN SIDEBAR ===== */
    .sidebar-branch-selector {
        padding: 12px 16px;
        border-bottom: 2px solid #0B3D8A;
    }
    
    .sidebar-branch-selector select {
        width: 100%;
        padding: 8px 12px;
        border-radius: 10px;
        border: none;
        background: rgba(255,255,255,0.12);
        color: white;
        font-size: 0.8rem;
        cursor: pointer;
        outline: none;
        transition: all 0.3s ease;
    }
    
    .sidebar-branch-selector select:hover {
        background: rgba(255,255,255,0.2);
    }
    
    .sidebar-branch-selector select option {
        background: #0B4EA8;
        color: white;
    }
    
    .sidebar-nav { 
        padding: 14px 10px; 
    }
    
    .sidebar-nav .nav-label {
        font-size: 0.55rem; 
        text-transform: uppercase;
        letter-spacing: 0.1em; 
        color: #6EA8FE;
        padding: 0 12px; 
        margin: 12px 0 6px; 
        font-weight: 700;
    }
    
    /* ================================================================
       SIDEBAR LINKS - BLUE BACKGROUND, GREEN HOVER
       ================================================================ */
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
    }
    
    .sidebar-link:hover .badge {
        background: rgba(255,255,255,0.25);
    }
    
    .sidebar-link.active .badge {
        background: rgba(255,255,255,0.25);
        color: white;
    }
    
    /* ===== LOGOUT ===== */
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
    
    /* ===== RESPONSIVE ===== */
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
<!-- SIDEBAR - SHARED FOR ALL SUPER ADMIN PAGES -->
<!-- ================================================================ -->
<aside class="sidebar" id="sidebar">
    
    <!-- Brand -->
    <div class="sidebar-brand">
        <div class="flex items-center gap-3">
            <img src="<?= $logo_url ?>" alt="Braick Logo" class="logo"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2248%22 height=%2248%22%3E%3Crect width=%2248%22 height=%2248%22 fill=%22%230B4EA8%22 rx=%2212%22/%3E%3Ctext x=%2224%22 y=%2232%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2220%22 font-weight=%22bold%22%3EB%3C/text%3E%3C/svg%3E'">
            <div>
                <p class="brand-text">Braick Dispensary</p>
                <p class="brand-sub">Super Admin</p>
            </div>
        </div>
    </div>
    
    <!-- Branch Selector -->
    <div class="sidebar-branch-selector">
        <select id="sidebarBranchSelector" onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php
            // Get branches for selector
            try {
                if (isset($db)) {
                    $branches_list = [];
                    $stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $branches_list[] = $row;
                    }
                    foreach ($branches_list as $branch) {
                        $sel = ($selected_branch_id == $branch['id']) ? 'selected' : '';
                        echo '<option value="' . $branch['id'] . '" ' . $sel . '>🏥 ' . htmlspecialchars($branch['name']) . '</option>';
                    }
                }
            } catch (Exception $e) {}
            ?>
        </select>
    </div>
    
    <nav class="sidebar-nav">
        
        <!-- ===== MAIN MENU ===== -->
        <div class="nav-label">Main Menu</div>
        
        <!-- ================================================================ -->
        <!-- DASHBOARD - Admin Dashboard -->
        <!-- ================================================================ -->
        <a href="../admin/dashboard.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('dashboard.php') ?>">
            <i class="fas fa-home"></i> Dashboard
        </a>
        
        <!-- ================================================================ -->
        <!-- EMPLOYEES - User Management -->
        <!-- ================================================================ -->
        <a href="../admin/employees.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('employees.php') ?>">
            <i class="fas fa-users"></i> Employees
            <span class="badge"><?= $total_employees ?></span>
        </a>
        
        <!-- ===== MODULES ===== -->
        <div class="nav-label mt-2">Modules</div>
        
        <!-- ================================================================ -->
        <!-- DOCTORS - Goes to doctors_list.php (All Doctors) -->
        <!-- ================================================================ -->
        <a href="../admin/doctors_list.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('doctors_list.php') || isAdminPage(['view_doctor.php']) ? 'active' : '' ?>">
            <i class="fas fa-user-md"></i> Doctors
            <span class="badge"><?= $total_doctors ?></span>
        </a>
        
        <!-- ================================================================ -->
        <!-- PHARMACY - Goes to view_pharmacy.php -->
        <!-- ================================================================ -->
        <a href="../admin/view_pharmacy.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('view_pharmacy.php') ?>">
            <i class="fas fa-prescription"></i> Pharmacy
            <span class="badge"><?= $module_counts['pharmacy'] ?? 0 ?></span>
            <?php if ($pending_prescriptions > 0): ?>
                <span class="badge" style="background: #EF4444;"><?= $pending_prescriptions ?></span>
            <?php endif; ?>
        </a>
        
        <!-- ================================================================ -->
        <!-- RECEPTION - Goes to view_reception.php -->
        <!-- ================================================================ -->
        <a href="../admin/view_reception.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('view_reception.php') ?>">
            <i class="fas fa-headset"></i> Reception
            <span class="badge"><?= $module_counts['reception'] ?? 0 ?></span>
        </a>
        
        <!-- ================================================================ -->
        <!-- LABORATORY - Goes to view_laboratory.php -->
        <!-- ================================================================ -->
        <a href="../admin/view_laboratory.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('view_laboratory.php') ?>">
            <i class="fas fa-flask"></i> Laboratory
            <span class="badge"><?= $module_counts['laboratory'] ?? 0 ?></span>
            <?php if ($pending_lab_tests > 0): ?>
                <span class="badge" style="background: #EF4444;"><?= $pending_lab_tests ?></span>
            <?php endif; ?>
        </a>
        
        <!-- ================================================================ -->
        <!-- CASHIER - Goes to view_cashier.php -->
        <!-- ================================================================ -->
        <a href="../admin/view_cashier.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('view_cashier.php') ?>">
            <i class="fas fa-cash-register"></i> Cashier
            <span class="badge"><?= $module_counts['cashier'] ?? 0 ?></span>
        </a>
        
        <!-- ===== MANAGEMENT ===== -->
        <div class="nav-label mt-2">Management</div>
        
        <!-- ================================================================ -->
        <!-- BRANCHES -->
        <!-- ================================================================ -->
        <a href="../admin/branches.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('branches.php') ?>">
            <i class="fas fa-store-alt"></i> Branches
            <span class="badge"><?= $total_branches ?></span>
        </a>
        
        <!-- ================================================================ -->
        <!-- DEPARTMENTS -->
        <!-- ================================================================ -->
        <a href="../admin/departments.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('departments.php') ?>">
            <i class="fas fa-building"></i> Departments
        </a>
        
        <!-- ================================================================ -->
        <!-- REPORTS -->
        <!-- ================================================================ -->
        <a href="../admin/reports.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('reports.php') ?>">
            <i class="fas fa-chart-bar"></i> Reports
        </a>
        
        <!-- ===== SYSTEM ===== -->
        <div class="nav-label mt-2">System</div>
        
        <!-- ================================================================ -->
        <!-- SETTINGS -->
        <!-- ================================================================ -->
        <a href="../admin/settings.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('settings.php') ?>">
            <i class="fas fa-cog"></i> Settings
        </a>
        
        <!-- ================================================================ -->
        <!-- BACKUPS -->
        <!-- ================================================================ -->
        <a href="../admin/backups.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('backups.php') ?>">
            <i class="fas fa-database"></i> Backups
        </a>
        
        <!-- ================================================================ -->
        <!-- SYSTEM LOGS -->
        <!-- ================================================================ -->
        <a href="../admin/system_logs.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('system_logs.php') ?>">
            <i class="fas fa-history"></i> System Logs
        </a>
        
        <!-- ===== ACCOUNT ===== -->
        <div class="nav-label mt-2">Account</div>
        
        <!-- ================================================================ -->
        <!-- PROFILE -->
        <!-- ================================================================ -->
        <a href="../admin/profile.php" 
           class="sidebar-link <?= isActive('profile.php') ?>">
            <i class="fas fa-user-circle"></i> Profile
        </a>
        
        <!-- ================================================================ -->
        <!-- LOGOUT -->
        <!-- ================================================================ -->
        <a href="../../../logout.php" 
           class="sidebar-link logout-link">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
        
    </nav>
</aside>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // BRANCH SWITCHER
    // ================================================================
    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        
        // Remove 'id' parameter if it exists (for view_doctor page)
        if (url.searchParams.has('id')) {
            url.searchParams.delete('id');
        }
        
        window.location.href = url.toString();
    }

    // ================================================================
    // SIDEBAR TOGGLE (Mobile)
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        var sidebar = document.getElementById('sidebar');
        var sidebarToggle = document.getElementById('sidebarToggle');
        
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('open');
            });
        }
        
        // Close sidebar on outside click (mobile)
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

    console.log('%c🏥 Admin Sidebar - Fixed (No Sub-list)', 'font-size:16px; font-weight:bold; color:#0AA84F;');
    console.log('%c📍 Branch: <?= $selected_branch_id === 'all' ? 'All' : $selected_branch_id ?>', 'font-size:12px; color:#6EA8FE;');
    console.log('%c👨‍⚕️ Doctors: Click to see all doctors', 'font-size:12px; color:#34D399;');
    console.log('%c📊 Modules: Doctor, Pharmacy, Reception, Laboratory, Cashier', 'font-size:12px; color:#9EC5FE;');
</script>