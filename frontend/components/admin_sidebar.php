<?php
// ================================================================
// FILE: frontend/components/admin_sidebar.php
// SUPER ADMIN - SHARED SIDEBAR (FULLY FIXED)
// FULLY RESPONSIVE - ALL DEVICES
// BACKGROUND: BLUE | HOVER: GREEN
// WITH AJAX REAL-TIME DATA UPDATES
// WITH LOGIN PROTECTION
// WITH ABSOLUTE PATHS
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// START SESSION
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION - CHECK IF USER IS LOGGED IN
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

// ================================================================
// CHECK IF USER HAS ACCESS (Admin only)
// ================================================================
if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: /dispensary_system/frontend/pages/doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: /dispensary_system/frontend/pages/pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: /dispensary_system/frontend/pages/laboratory/dashboard.php'); break;
        case 'cashier': header('Location: /dispensary_system/frontend/pages/cashier/dashboard.php'); break;
        case 'reception': header('Location: /dispensary_system/frontend/pages/reception/dashboard.php'); break;
        default: header('Location: /dispensary_system/frontend/pages/login.php'); break;
    }
    exit;
}

// ================================================================
// GET USER DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// Pass these variables from each page
$selected_branch_id = $selected_branch_id ?? 'all';
$total_employees = $total_employees ?? 0;
$total_doctors = $total_doctors ?? 0;
$total_branches = $total_branches ?? 0;
$pending_lab_tests = $pending_lab_tests ?? 0;
$pending_prescriptions = $pending_prescriptions ?? 0;

// ================================================================
// INCLUDE DATABASE FOR STATS
// ================================================================
require_once __DIR__ . '/../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    $db = null;
}

// Detect current page and module
$current_page = basename($_SERVER['PHP_SELF']);
$current_module = basename(dirname($_SERVER['PHP_SELF']));

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
$patient_counts = [];
$service_counts = [];
$real_employee_count = 0;

try {
    if ($db !== null) {
        // Employee count
        if ($total_employees == 0) {
            if ($selected_branch_id === 'all') {
                $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role != 'admin' AND status = 'active'");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $real_employee_count = $result['count'] ?? 0;
            } else {
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role != 'admin' AND status = 'active' AND branch_id = ?");
                $stmt->execute([(int)$selected_branch_id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $real_employee_count = $result['count'] ?? 0;
            }
        } else {
            $real_employee_count = $total_employees;
        }
        
        // Module counts
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
        
        // Doctor count
        if ($selected_branch_id === 'all') {
            $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'doctor' AND status = 'active'");
        } else {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'doctor' AND status = 'active' AND branch_id = ?");
            $stmt->execute([(int)$selected_branch_id]);
        }
        $doctor_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        $total_doctors = $doctor_count;
        
        // Patient counts
        if ($selected_branch_id === 'all') {
            $stmt = $db->query("SELECT COUNT(*) as count FROM patients");
            $patient_counts['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
            
            $stmt = $db->query("SELECT COUNT(*) as count FROM patients WHERE DATE(created_at) = CURDATE()");
            $patient_counts['today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        } else {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM patients WHERE branch_id = ?");
            $stmt->execute([(int)$selected_branch_id]);
            $patient_counts['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
            
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM patients WHERE branch_id = ? AND DATE(created_at) = CURDATE()");
            $stmt->execute([(int)$selected_branch_id]);
            $patient_counts['today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        }
        
        // Service counts
        if ($selected_branch_id === 'all') {
            $stmt = $db->query("SELECT COUNT(*) as count FROM bill_items WHERE status != 'cancelled'");
            $service_counts['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
            
            $stmt = $db->query("SELECT COUNT(*) as count FROM bill_items WHERE status != 'cancelled' AND DATE(created_at) = CURDATE()");
            $service_counts['today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        } else {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM bill_items WHERE branch_id = ? AND status != 'cancelled'");
            $stmt->execute([(int)$selected_branch_id]);
            $service_counts['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
            
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM bill_items WHERE branch_id = ? AND status != 'cancelled' AND DATE(created_at) = CURDATE()");
            $stmt->execute([(int)$selected_branch_id]);
            $service_counts['today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        }
        
        // Pending prescriptions
        if ($selected_branch_id === 'all') {
            $stmt = $db->query("SELECT COUNT(*) as count FROM prescriptions WHERE status = 'pending'");
        } else {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions p INNER JOIN patient_bills pb ON p.id = pb.prescription_id WHERE p.status = 'pending' AND pb.branch_id = ?");
            $stmt->execute([(int)$selected_branch_id]);
        }
        $pending_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // Pending lab tests
        if ($selected_branch_id === 'all') {
            $stmt = $db->query("SELECT COUNT(*) as count FROM lab_tests WHERE status = 'pending'");
        } else {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_tests WHERE status = 'pending' AND branch_id = ?");
            $stmt->execute([(int)$selected_branch_id]);
        }
        $pending_lab_tests = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // Branch count
        $stmt = $db->query("SELECT COUNT(*) as count FROM branches WHERE status = 'active'");
        $total_branches = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
    }
} catch (Exception $e) {
    $module_counts = ['pharmacy' => 0, 'reception' => 0, 'laboratory' => 0, 'cashier' => 0];
    $patient_counts = ['total' => 0, 'today' => 0];
    $service_counts = ['total' => 0, 'today' => 0];
    $real_employee_count = $total_employees;
}

$total_employees = $real_employee_count;

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';
?>

<style>
    /* ================================================================
       SIDEBAR STYLES - FULL
       ================================================================ */
    
    /* Sidebar Container */
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
        overflow-x: hidden;
        transition: transform 0.3s ease-in-out;
        transform: translateX(-100%);
        box-shadow: 4px 0 20px rgba(0,0,0,0.15);
    }
    
    /* Sidebar Open State */
    .sidebar.open {
        transform: translateX(0);
    }
    
    /* Scrollbar */
    .sidebar::-webkit-scrollbar { width: 5px; }
    .sidebar::-webkit-scrollbar-track { background: #0B3D8A; }
    .sidebar::-webkit-scrollbar-thumb { background: #0AA84F; border-radius: 10px; }
    .sidebar::-webkit-scrollbar-thumb:hover { background: #0AA84F; }
    
    /* ================================================================
       SIDEBAR BRAND / HEADER
       ================================================================ */
    .sidebar-brand {
        padding: 18px 16px 14px;
        border-bottom: 2px solid #0B3D8A;
        background: #0B4EA8;
        position: sticky;
        top: 0;
        z-index: 5;
    }
    
    .sidebar-brand .logo {
        width: 42px; 
        height: 42px; 
        border-radius: 10px;
        object-fit: cover; 
        background: white; 
        padding: 4px;
        border: 2px solid rgba(255,255,255,0.1);
    }
    
    .sidebar-brand .brand-text { 
        color: white; 
        font-weight: 700; 
        font-size: 0.95rem; 
        line-height: 1.2;
    }
    
    .sidebar-brand .brand-sub { 
        color: #9EC5FE; 
        font-size: 0.65rem; 
        font-weight: 500;
    }
    
    /* ================================================================
       BRANCH SELECTOR
       ================================================================ */
    .sidebar-branch-selector {
        padding: 10px 14px;
        border-bottom: 2px solid #0B3D8A;
        background: #0B4EA8;
    }
    
    .sidebar-branch-selector select {
        width: 100%;
        padding: 7px 10px;
        border-radius: 8px;
        border: none;
        background: rgba(255,255,255,0.12);
        color: white;
        font-size: 0.75rem;
        cursor: pointer;
        outline: none;
        transition: all 0.3s ease;
        appearance: none;
        -webkit-appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='white' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 10px center;
    }
    
    .sidebar-branch-selector select:hover {
        background-color: rgba(255,255,255,0.2);
    }
    
    .sidebar-branch-selector select:focus {
        box-shadow: 0 0 0 2px rgba(10, 168, 79, 0.5);
    }
    
    .sidebar-branch-selector select option {
        background: #0B4EA8;
        color: white;
        padding: 8px;
    }
    
    /* ================================================================
       NAVIGATION
       ================================================================ */
    .sidebar-nav { 
        padding: 10px 8px 20px; 
    }
    
    .sidebar-nav .nav-label {
        font-size: 0.5rem; 
        text-transform: uppercase;
        letter-spacing: 0.08em; 
        color: #6EA8FE;
        padding: 0 10px; 
        margin: 12px 0 4px; 
        font-weight: 700;
    }
    
    .sidebar-nav .nav-label:first-of-type {
        margin-top: 0;
    }
    
    /* ================================================================
       SIDEBAR LINKS
       ================================================================ */
    .sidebar-link {
        display: flex; 
        align-items: center; 
        gap: 10px;
        padding: 8px 12px; 
        border-radius: 8px;
        color: #D2E3FC; 
        text-decoration: none;
        transition: all 0.25s ease; 
        font-size: 0.8rem; 
        font-weight: 500;
        margin: 1px 0;
        background: transparent;
        cursor: pointer;
        border: none;
        width: 100%;
        text-align: left;
        position: relative;
    }
    
    .sidebar-link:hover {
        background: #0AA84F;
        color: white;
        box-shadow: 0 4px 12px rgba(10, 168, 79, 0.35);
        transform: translateX(4px);
    }
    
    .sidebar-link.active {
        background: #0AA84F;
        color: white;
        box-shadow: 0 4px 12px rgba(10, 168, 79, 0.35);
    }
    
    .sidebar-link.active::before {
        content: '';
        position: absolute;
        left: 0;
        top: 20%;
        bottom: 20%;
        width: 4px;
        background: white;
        border-radius: 0 4px 4px 0;
    }
    
    .sidebar-link i { 
        width: 20px; 
        text-align: center; 
        font-size: 0.9rem; 
        flex-shrink: 0;
    }
    
    /* ================================================================
       BADGES ON SIDEBAR
       ================================================================ */
    .sidebar-link .badge {
        margin-left: auto;
        background: rgba(255,255,255,0.15);
        padding: 1px 8px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
        color: white;
        transition: all 0.3s ease;
        flex-shrink: 0;
        min-width: 20px;
        text-align: center;
    }
    
    .sidebar-link .badge.danger {
        background: #EF4444;
        animation: pulse-badge 2s infinite;
    }
    
    .sidebar-link .badge.success {
        background: #059669;
    }
    
    .sidebar-link .badge.warning {
        background: #F59E0B;
        color: #1E293B;
    }
    
    .sidebar-link:hover .badge {
        background: rgba(255,255,255,0.25);
    }
    
    .sidebar-link.active .badge {
        background: rgba(255,255,255,0.25);
        color: white;
    }
    
    @keyframes pulse-badge {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }
    
    /* ================================================================
       LOGOUT LINK
       ================================================================ */
    .sidebar-link.logout-link {
        border-top: 2px solid rgba(255,255,255,0.08);
        padding-top: 10px;
        margin-top: 6px;
        color: #FCA5A5;
    }
    
    .sidebar-link.logout-link:hover {
        background: #DC2626;
        color: white;
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.4);
    }
    
    /* ================================================================
       BADGE UPDATE ANIMATION
       ================================================================ */
    .badge-update {
        animation: badgePop 0.3s ease;
    }
    
    @keyframes badgePop {
        0% { transform: scale(0.5); opacity: 0; }
        70% { transform: scale(1.3); }
        100% { transform: scale(1); opacity: 1; }
    }
    
    /* ================================================================
       OVERLAY - For mobile
       ================================================================ */
    #sidebarOverlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        z-index: 45;
        display: none;
        backdrop-filter: blur(2px);
        -webkit-backdrop-filter: blur(2px);
    }
    
    /* ================================================================
       RESPONSIVE BREAKPOINTS
       ================================================================ */
    
    /* Desktop: Sidebar always visible */
    @media (min-width: 1025px) {
        .sidebar {
            transform: translateX(0) !important;
        }
        #sidebarOverlay {
            display: none !important;
        }
    }
    
    /* Tablet and below: Sidebar hidden by default */
    @media (max-width: 1024px) {
        .sidebar {
            width: 260px;
            transform: translateX(-100%);
        }
        .sidebar.open {
            transform: translateX(0);
        }
        .sidebar-brand {
            padding: 16px 14px 12px;
        }
        .sidebar-brand .logo {
            width: 38px;
            height: 38px;
        }
        .sidebar-brand .brand-text {
            font-size: 0.85rem;
        }
        .sidebar-link {
            padding: 7px 10px;
            font-size: 0.75rem;
            gap: 8px;
        }
        .sidebar-link i {
            width: 18px;
            font-size: 0.8rem;
        }
        .sidebar-link .badge {
            font-size: 0.55rem;
            padding: 1px 7px;
        }
    }
    
    /* Mobile phones */
    @media (max-width: 768px) {
        .sidebar {
            width: 280px;
            transform: translateX(-100%);
        }
        .sidebar.open {
            transform: translateX(0);
        }
        .sidebar-brand {
            padding: 14px 12px 10px;
        }
        .sidebar-brand .logo {
            width: 36px;
            height: 36px;
        }
        .sidebar-brand .brand-text {
            font-size: 0.8rem;
        }
        .sidebar-link {
            padding: 6px 10px;
            font-size: 0.7rem;
            gap: 8px;
        }
        .sidebar-link i {
            width: 16px;
            font-size: 0.75rem;
        }
        .sidebar-link .badge {
            font-size: 0.5rem;
            padding: 1px 6px;
        }
        .sidebar-nav .nav-label {
            font-size: 0.45rem;
        }
    }
    
    /* Small phones */
    @media (max-width: 480px) {
        .sidebar {
            width: 100%;
            max-width: 320px;
            transform: translateX(-100%);
        }
        .sidebar.open {
            transform: translateX(0);
        }
        .sidebar-brand {
            padding: 12px 12px 10px;
        }
        .sidebar-brand .logo {
            width: 32px;
            height: 32px;
        }
        .sidebar-brand .brand-text {
            font-size: 0.75rem;
        }
        .sidebar-link {
            padding: 5px 8px;
            font-size: 0.65rem;
            gap: 6px;
        }
        .sidebar-link i {
            width: 14px;
            font-size: 0.7rem;
        }
        .sidebar-link .badge {
            font-size: 0.45rem;
            padding: 1px 5px;
        }
    }
</style>

<!-- ================================================================ -->
<!-- SIDEBAR OVERLAY (Mobile) -->
<!-- ================================================================ -->
<div id="sidebarOverlay"></div>

<!-- ================================================================ -->
<!-- SIDEBAR -->
<!-- ================================================================ -->
<aside class="sidebar" id="sidebar">
    
    <!-- ================================================================ -->
    <!-- BRAND / HEADER -->
    <!-- ================================================================ -->
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
    
    <!-- ================================================================ -->
    <!-- BRANCH SELECTOR -->
    <!-- ================================================================ -->
    <div class="sidebar-branch-selector">
        <select id="sidebarBranchSelector" onchange="switchBranch(this.value)">
            <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php
            try {
                if ($db !== null) {
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
    
    <!-- ================================================================ -->
    <!-- NAVIGATION -->
    <!-- ================================================================ -->
    <nav class="sidebar-nav">
        
        <!-- ============================================================ -->
        <!-- MAIN MENU -->
        <!-- ============================================================ -->
        <div class="nav-label">Main Menu</div>
        
        <a href="/dispensary_system/frontend/pages/admin/dashboard.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('dashboard.php') ?>">
            <i class="fas fa-home"></i> Dashboard
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/employees.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('employees.php') ?>">
            <i class="fas fa-users"></i> Employees
            <span class="badge" id="badgeEmployees"><?= $total_employees ?></span>
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/patients.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('patients.php') || isAdminPage(['patient_details.php']) ? 'active' : '' ?>">
            <i class="fas fa-user-injured"></i> Patients
            <span class="badge" id="badgePatients"><?= $patient_counts['total'] ?></span>
            <?php if ($patient_counts['today'] > 0): ?>
                <span class="badge success" id="badgePatientsToday">+<?= $patient_counts['today'] ?></span>
            <?php endif; ?>
        </a>
        
        <!-- ============================================================ -->
        <!-- MODULES -->
        <!-- ============================================================ -->
        <div class="nav-label mt-2">Modules</div>
        
        <a href="/dispensary_system/frontend/pages/admin/doctors_list.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('doctors_list.php') || isAdminPage(['view_doctor.php']) ? 'active' : '' ?>">
            <i class="fas fa-user-md"></i> Doctors
            <span class="badge" id="badgeDoctors"><?= $total_doctors ?></span>
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/view_pharmacy.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('view_pharmacy.php') ?>">
            <i class="fas fa-prescription"></i> Pharmacy
            <span class="badge" id="badgePharmacy"><?= $module_counts['pharmacy'] ?? 0 ?></span>
            <?php if ($pending_prescriptions > 0): ?>
                <span class="badge danger" id="badgePendingPrescriptions"><?= $pending_prescriptions ?></span>
            <?php endif; ?>
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/view_reception.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('view_reception.php') ?>">
            <i class="fas fa-headset"></i> Reception
            <span class="badge" id="badgeReception"><?= $module_counts['reception'] ?? 0 ?></span>
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/view_laboratory.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('view_laboratory.php') ?>">
            <i class="fas fa-flask"></i> Laboratory
            <span class="badge" id="badgeLaboratory"><?= $module_counts['laboratory'] ?? 0 ?></span>
            <?php if ($pending_lab_tests > 0): ?>
                <span class="badge danger" id="badgePendingLabTests"><?= $pending_lab_tests ?></span>
            <?php endif; ?>
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/view_cashier.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('view_cashier.php') ?>">
            <i class="fas fa-cash-register"></i> Cashier
            <span class="badge" id="badgeCashier"><?= $module_counts['cashier'] ?? 0 ?></span>
        </a>
        
        <!-- ============================================================ -->
        <!-- SERVICES -->
        <!-- ============================================================ -->
        <div class="nav-label mt-2">Services</div>
        
        <a href="/dispensary_system/frontend/pages/admin/services.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('services.php') || isActive('service_categories.php') ? 'active' : '' ?>">
            <i class="fas fa-concierge-bell"></i> Services
            <span class="badge" id="badgeServices"><?= $service_counts['total'] ?></span>
            <?php if ($service_counts['today'] > 0): ?>
                <span class="badge success" id="badgeServicesToday">+<?= $service_counts['today'] ?></span>
            <?php endif; ?>
        </a>
        
        <!-- ============================================================ -->
        <!-- MANAGEMENT -->
        <!-- ============================================================ -->
        <div class="nav-label mt-2">Management</div>
        
        <a href="/dispensary_system/frontend/pages/admin/branches.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('branches.php') || isAdminPage(['view_branch.php', 'add_branch.php', 'edit_branch.php']) ? 'active' : '' ?>">
            <i class="fas fa-store-alt"></i> Branches
            <span class="badge" id="badgeBranches"><?= $total_branches ?></span>
        </a>
        
        <a href="/dispensary_system/frontend/pages/admin/departments.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('departments.php') || isAdminPage(['add_department.php', 'edit_department.php']) ? 'active' : '' ?>">
            <i class="fas fa-building"></i> Departments
        </a>
        
        <!-- BILLS MENU REMOVED -->
        
        <a href="/dispensary_system/frontend/pages/admin/reports.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('reports.php') ?>">
            <i class="fas fa-chart-bar"></i> Reports
        </a>
        
        <!-- ============================================================ -->
        <!-- SYSTEM -->
        <!-- ============================================================ -->
        <div class="nav-label mt-2">System</div>
        
        <a href="/dispensary_system/frontend/pages/admin/settings.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('settings.php') ? 'active' : '' ?>">
            <i class="fas fa-cog"></i> Settings
        </a>
        
        <!-- ============================================================ -->
        <!-- ACCOUNT -->
        <!-- ============================================================ -->
        <div class="nav-label mt-2">Account</div>
        
        <a href="/dispensary_system/frontend/pages/admin/profile.php" 
           class="sidebar-link <?= isActive('profile.php') ?>">
            <i class="fas fa-user-circle"></i> Profile
        </a>
        
        <!-- ============================================================ -->
        <!-- LOGOUT - ABSOLUTE PATH -->
        <!-- ============================================================ -->
        <a href="/dispensary_system/frontend/pages/logout.php" 
           class="sidebar-link logout-link">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
        
    </nav>
</aside>

<!-- ================================================================ -->
<!-- JAVASCRIPT - FULL SIDEBAR FUNCTIONALITY -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // BRANCH SWITCHER
    // ================================================================
    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        // Remove id parameter if exists (for view pages)
        if (url.searchParams.has('id')) {
            url.searchParams.delete('id');
        }
        window.location.href = url.toString();
    }

    // ================================================================
    // SIDEBAR TOGGLE - FULLY FIXED
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        var sidebar = document.getElementById('sidebar');
        var sidebarToggle = document.getElementById('sidebarToggle');
        var overlay = document.getElementById('sidebarOverlay');
        
        // Create overlay if not exists
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'sidebarOverlay';
            overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:45;display:none;backdrop-filter:blur(2px);-webkit-backdrop-filter:blur(2px);';
            document.body.appendChild(overlay);
        }
        
        // Toggle function
        function toggleSidebar() {
            sidebar.classList.toggle('open');
            overlay.style.display = sidebar.classList.contains('open') ? 'block' : 'none';
            document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
        }
        
        // ================================================================
        // EVENT: Sidebar toggle button (hamburger icon)
        // ================================================================
        if (sidebarToggle) {
            // Remove any existing listeners to avoid duplicates
            sidebarToggle.removeEventListener('click', toggleSidebar);
            sidebarToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                toggleSidebar();
            });
        }
        
        // ================================================================
        // EVENT: Also listen for any element with class .icon-btn
        // ================================================================
        document.querySelectorAll('.icon-btn, .menu-toggle, [data-toggle="sidebar"]').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                toggleSidebar();
            });
        });
        
        // ================================================================
        // EVENT: Close sidebar when clicking overlay
        // ================================================================
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('open');
            overlay.style.display = 'none';
            document.body.style.overflow = '';
        });
        
        // ================================================================
        // EVENT: Close sidebar with ESC key
        // ================================================================
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar.classList.contains('open')) {
                sidebar.classList.remove('open');
                overlay.style.display = 'none';
                document.body.style.overflow = '';
            }
        });
        
        // ================================================================
        // EVENT: Auto-close on window resize (desktop)
        // ================================================================
        window.addEventListener('resize', function() {
            if (window.innerWidth > 1024 && sidebar.classList.contains('open')) {
                sidebar.classList.remove('open');
                overlay.style.display = 'none';
                document.body.style.overflow = '';
            }
        });
        
        // ================================================================
        // LOG: Debug info
        // ================================================================
        console.log('✅ Admin Sidebar initialized');
        console.log('📱 Sidebar element:', sidebar);
        console.log('🔘 Toggle button:', sidebarToggle);
        console.log('📐 Window width:', window.innerWidth);
        console.log('📱 Is mobile:', window.innerWidth <= 1024);
        console.log('👤 Admin: <?= htmlspecialchars($user_full_name) ?>');
        console.log('🔒 Login protection: Active');
        console.log('🚫 Bills menu: REMOVED');
        console.log('🚪 Logout path: /dispensary_system/frontend/pages/logout.php (ABSOLUTE PATH - FIXED)');
        console.log('✅ All paths are ABSOLUTE starting with /dispensary_system/');
    });

    // ================================================================
    // AJAX - REAL-TIME DATA UPDATE
    // ================================================================
    var sidebarUpdateInterval = null;
    var isUpdating = false;

    function updateSidebarData() {
        if (isUpdating) return;
        isUpdating = true;
        
        var branch = '<?= $selected_branch_id ?>';
        var url = '/dispensary_system/frontend/pages/admin/get_sidebar_stats.php?branch=' + encodeURIComponent(branch) + '&t=' + Date.now();
        
        fetch(url)
            .then(function(response) { 
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json(); 
            })
            .then(function(data) {
                if (data.success) {
                    updateBadges(data);
                }
                isUpdating = false;
            })
            .catch(function(error) {
                console.error('Sidebar update error:', error);
                isUpdating = false;
            });
    }

    function updateBadges(data) {
        var badgeMap = {
            'badgeEmployees': data.total_employees,
            'badgePatients': data.total_patients,
            'badgePatientsToday': data.today_patients > 0 ? '+' + data.today_patients : null,
            'badgeDoctors': data.total_doctors,
            'badgePharmacy': data.pharmacy_count,
            'badgeReception': data.reception_count,
            'badgeLaboratory': data.laboratory_count,
            'badgeCashier': data.cashier_count,
            'badgeServices': data.total_services,
            'badgeServicesToday': data.today_services > 0 ? '+' + data.today_services : null,
            'badgeBranches': data.total_branches,
            'badgePendingPrescriptions': data.pending_prescriptions > 0 ? data.pending_prescriptions : null,
            'badgePendingLabTests': data.pending_lab_tests > 0 ? data.pending_lab_tests : null
        };
        
        for (var id in badgeMap) {
            var el = document.getElementById(id);
            if (el) {
                var val = badgeMap[id];
                if (val !== null && val !== undefined && val !== '' && val !== 'null' && val !== 'undefined') {
                    el.textContent = val;
                    el.style.display = '';
                    el.classList.remove('badge-update');
                    // Trigger reflow for animation
                    void el.offsetWidth;
                    el.classList.add('badge-update');
                } else {
                    el.style.display = 'none';
                }
            }
        }
    }

    function startSidebarUpdates() {
        if (sidebarUpdateInterval) {
            clearInterval(sidebarUpdateInterval);
        }
        // Initial update after 2 seconds
        setTimeout(function() {
            updateSidebarData();
        }, 2000);
        // Then every 15 seconds
        sidebarUpdateInterval = setInterval(updateSidebarData, 15000);
    }

    function stopSidebarUpdates() {
        if (sidebarUpdateInterval) {
            clearInterval(sidebarUpdateInterval);
            sidebarUpdateInterval = null;
        }
    }

    // Start/stop updates based on page visibility
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopSidebarUpdates();
        } else {
            startSidebarUpdates();
        }
    });

    // Start updates after page load
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            startSidebarUpdates();
        }, 3000);
    });

    // ================================================================
    // CONSOLE LOG
    // ================================================================
    console.log('%c🏥 Braick Dispensary - Admin Sidebar', 'font-size:16px; font-weight:bold; color:#0AA84F;');
    console.log('%c📁 Full Sidebar with all menu items', 'font-size:13px; color:#34D399;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#6EA8FE;');
    console.log('%c👤 Employees: <?= $total_employees ?>', 'font-size:13px; color:#6EA8FE;');
    console.log('%c🔄 AJAX updates every 15 seconds', 'font-size:13px; color:#6EA8FE;');
    console.log('%c📱 Responsive: Desktop fixed, Mobile hidden with toggle', 'font-size:13px; color:#F59E0B;');
    console.log('%c🔒 Login protection: Active', 'font-size:13px; color:#34D399;');
    console.log('%c🚫 Bills menu: REMOVED', 'font-size:13px; color:#DC2626;');
    console.log('%c🚪 Logout: /dispensary_system/frontend/pages/logout.php (ABSOLUTE PATH)', 'font-size:13px; color:#34D399;');
    console.log('%c✅ All paths are ABSOLUTE - No more relative path issues!', 'font-size:13px; color:#059669;');
</script>