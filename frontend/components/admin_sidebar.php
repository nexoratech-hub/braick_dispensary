<?php
// ================================================================
// FILE: frontend/components/admin_sidebar.php
// SUPER ADMIN - SHARED SIDEBAR
// BACKGROUND: BLUE | HOVER: GREEN
// ACTIVE STATE: Kulingana na page iliyopo TU
// ================================================================

// Pass these variables from each page
$selected_branch_id = $selected_branch_id ?? 'all';
$total_employees = $total_employees ?? 0;
$total_doctors = $total_doctors ?? 0;
$total_branches = $total_branches ?? 0;
$pending_lab_tests = $pending_lab_tests ?? 0;
$pending_prescriptions = $pending_prescriptions ?? 0;

// Detect current page ONLY - NO MODULE DETECTION
$current_page = basename($_SERVER['PHP_SELF']);

// ================================================================
// FUNCTION TO CHECK ACTIVE STATE - PAGE BASED ONLY
// ================================================================
function isActive($page) {
    $current_page = basename($_SERVER['PHP_SELF']);
    
    // ONLY check if current page matches exactly
    if ($page === $current_page) {
        return 'active';
    }
    
    return '';
}

// ================================================================
// FUNCTION TO CHECK IF IN SPECIFIC MODULE FOLDER
// ================================================================
function isModuleActive($module_name) {
    $current_module = basename(dirname($_SERVER['PHP_SELF']));
    
    // Check if we are in the specific module folder
    if ($current_module === $module_name) {
        return 'active';
    }
    
    return '';
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
    
    <nav class="sidebar-nav">
        <!-- ===== MAIN MENU ===== -->
        <div class="nav-label">Main Menu</div>
        
        <!-- ================================================================ -->
        <!-- DASHBOARD - Active only on dashboard.php -->
        <!-- ================================================================ -->
        <a href="../admin/dashboard.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('dashboard.php') ?>">
            <i class="fas fa-home"></i> Dashboard
        </a>
        
        <!-- ================================================================ -->
        <!-- EMPLOYEES - Active only on employees.php -->
        <!-- ================================================================ -->
        <a href="../admin/employees.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('employees.php') ?>">
            <i class="fas fa-users"></i> Employees
            <span class="badge"><?= $total_employees ?></span>
        </a>
        
        <!-- ===== MODULES ===== -->
        <div class="nav-label mt-2">Modules</div>
        
        <!-- ================================================================ -->
        <!-- DOCTORS - Active only when in doctor/ folder -->
        <!-- ================================================================ -->
        <a href="../doctor/dashboard.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isModuleActive('doctor') ?>">
            <i class="fas fa-user-md"></i> Doctors
            <span class="badge"><?= $total_doctors ?></span>
        </a>
        
        <!-- ================================================================ -->
        <!-- RECEPTION - Active only when in reception/ folder -->
        <!-- ================================================================ -->
        <a href="../reception/dashboard.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isModuleActive('reception') ?>">
            <i class="fas fa-headset"></i> Reception
        </a>
        
        <!-- ================================================================ -->
        <!-- LABORATORY - Active only when in laboratory/ folder -->
        <!-- ================================================================ -->
        <a href="../laboratory/dashboard.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isModuleActive('laboratory') ?>">
            <i class="fas fa-flask"></i> Laboratory
            <span class="badge"><?= $pending_lab_tests ?></span>
        </a>
        
        <!-- ================================================================ -->
        <!-- PHARMACY - Active only when in pharmacy/ folder -->
        <!-- ================================================================ -->
        <a href="../pharmacy/dashboard.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isModuleActive('pharmacy') ?>">
            <i class="fas fa-pills"></i> Pharmacy
            <span class="badge"><?= $pending_prescriptions ?></span>
        </a>
        
        <!-- ================================================================ -->
        <!-- CASHIER - Active only when in cashier/ folder -->
        <!-- ================================================================ -->
        <a href="../cashier/dashboard.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isModuleActive('cashier') ?>">
            <i class="fas fa-cash-register"></i> Cashier
        </a>
        
        <!-- ===== MANAGEMENT ===== -->
        <div class="nav-label mt-2">Management</div>
        
        <!-- ================================================================ -->
        <!-- BRANCHES - Active only on branches.php -->
        <!-- ================================================================ -->
        <a href="../admin/branches.php" 
           class="sidebar-link <?= isActive('branches.php') ?>">
            <i class="fas fa-store-alt"></i> Branches
            <span class="badge"><?= $total_branches ?></span>
        </a>
        
        <!-- ================================================================ -->
        <!-- DEPARTMENTS - Active only on departments.php -->
        <!-- ================================================================ -->
        <a href="../admin/departments.php" 
           class="sidebar-link <?= isActive('departments.php') ?>">
            <i class="fas fa-building"></i> Departments
        </a>
        
        <!-- ================================================================ -->
        <!-- REPORTS - Active only on reports.php -->
        <!-- ================================================================ -->
        <a href="../admin/reports.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('reports.php') ?>">
            <i class="fas fa-chart-bar"></i> Reports
        </a>
        
        <!-- ===== SYSTEM ===== -->
        <div class="nav-label mt-2">System</div>
        
        <!-- ================================================================ -->
        <!-- SETTINGS - Active only on settings.php -->
        <!-- ================================================================ -->
        <a href="../admin/settings.php" 
           class="sidebar-link <?= isActive('settings.php') ?>">
            <i class="fas fa-cog"></i> Settings
        </a>
        
        <!-- ================================================================ -->
        <!-- BACKUPS - Active only on backups.php -->
        <!-- ================================================================ -->
        <a href="../admin/backups.php" 
           class="sidebar-link <?= isActive('backups.php') ?>">
            <i class="fas fa-database"></i> Backups
        </a>
        
        <!-- ================================================================ -->
        <!-- SYSTEM LOGS - Active only on system_logs.php -->
        <!-- ================================================================ -->
        <a href="../admin/system_logs.php" 
           class="sidebar-link <?= isActive('system_logs.php') ?>">
            <i class="fas fa-history"></i> System Logs
        </a>
        
        <!-- ===== ACCOUNT ===== -->
        <div class="nav-label mt-2">Account</div>
        
        <!-- ================================================================ -->
        <!-- PROFILE - Active only on profile.php -->
        <!-- ================================================================ -->
        <a href="../admin/profile.php" 
           class="sidebar-link <?= isActive('profile.php') ?>">
            <i class="fas fa-user-circle"></i> Profile
        </a>
        
        <!-- ================================================================ -->
        <!-- LOGOUT - NEVER ACTIVE -->
        <!-- ================================================================ -->
        <a href="../../../logout.php" 
           class="sidebar-link logout-link">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </nav>
</aside>