<?php
// ================================================================
// FILE: frontend/components/reception_sidebar.php
// SHARED SIDEBAR - RECEPTION & CASHIER
// CLEAN VERSION - NO "Braick Dispensary - Dodoma"
// BRAICK DISPENSARY
// ================================================================

// Include database config
require_once __DIR__ . '/../../backend/config/config.php';

// ================================================================
// GET SESSION DATA - DEFAULT TO RECEPTION.ROSE
// ================================================================
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 6;  // reception.rose
    $_SESSION['full_name'] = 'Rose Mwangi';
    $_SESSION['role'] = 'reception';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'reception.rose';
    $_SESSION['is_admin'] = false;
}

$is_admin = ($_SESSION['role'] ?? '') === 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_role = $_SESSION['role'] ?? 'reception';
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';

// Display role name for sidebar
$display_role = ucfirst($user_role); // "Reception" or "Cashier"

// ================================================================
// BRANCH FILTER - Admin sees all, others see only their branch
// ================================================================
$selected_branch_id = $_GET['branch'] ?? 'all';

// If not admin, force to their branch
if (!$is_admin) {
    $selected_branch_id = $user_branch_id;
}

// ================================================================
// GET STATISTICS FROM DATABASE (WITH BRANCH FILTER)
// ================================================================
try {
    $db = getDB();
    
    // Build branch filter
    $branch_filter = '';
    $params = [];
    
    if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
        $branch_filter = " AND branch_id = ?";
        $params[] = $selected_branch_id;
    }
    
    // Total patients
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM patients WHERE 1=1 " . $branch_filter);
    $stmt->execute($params);
    $total_patients = $stmt->fetch()['total'] ?? 0;
    
    // Today's appointments
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM appointments WHERE DATE(appointment_date) = CURDATE() " . $branch_filter);
    $stmt->execute($params);
    $today_appointments = $stmt->fetch()['total'] ?? 0;
    
    // Pending payments
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM visits WHERE (status = 'pending' OR status = 'assigned') " . $branch_filter);
    $stmt->execute($params);
    $pending_payments = $stmt->fetch()['total'] ?? 0;
    
    // Today's revenue
    $stmt = $db->prepare("SELECT COALESCE(SUM(total), 0) as total FROM pharmacy_sales WHERE DATE(sale_date) = CURDATE() AND payment_status = 'paid' " . $branch_filter);
    $stmt->execute($params);
    $today_revenue = $stmt->fetch()['total'] ?? 0;
    
    // Get branches for selector (only if admin)
    if ($is_admin) {
        $branches = getBranches();
    } else {
        // Get only user's branch - but don't show branch name in selector label
        $branches = [];
        $branch = getBranch($user_branch_id);
        if ($branch) {
            $branches[] = $branch;
        }
    }
    
} catch (Exception $e) {
    $total_patients = 0;
    $today_appointments = 0;
    $pending_payments = 0;
    $today_revenue = 0;
    $branches = [];
}

// ================================================================
// DETECT CURRENT PAGE
// ================================================================
$current_page = basename($_SERVER['PHP_SELF']);
$current_module = basename(dirname($_SERVER['PHP_SELF']));

function isActive($page) {
    global $current_page;
    return $page === $current_page ? 'active' : '';
}

function isModuleActive($module) {
    global $current_module;
    return $module === $current_module ? 'active' : '';
}

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
?>

<style>
    /* ================================================================
       SIDEBAR - RECEPTION & CASHIER
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
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    /* Branch Selector - Clean, just the dropdown */
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
    
    .sidebar-branch-selector select:disabled {
        opacity: 0.7;
        cursor: not-allowed;
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
       SIDEBAR LINKS - BLUE BG, GREEN HOVER
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
<!-- SIDEBAR -->
<!-- ================================================================ -->
<aside class="sidebar" id="sidebar">
    
    <!-- Brand -->
    <div class="sidebar-brand">
        <div class="flex items-center gap-3">
            <img src="<?= $logo_url ?>" alt="Braick Logo" class="logo"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2248%22 height=%2248%22%3E%3Crect width=%2248%22 height=%2248%22 fill=%22%230B4EA8%22 rx=%2212%22/%3E%3Ctext x=%2224%22 y=%2232%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2220%22 font-weight=%22bold%22%3EB%3C/text%3E%3C/svg%3E'">
            <div>
                <p class="brand-text">Braick Dispensary</p>
                <p class="brand-sub"><?= $display_role ?></p>
            </div>
        </div>
    </div>
    
    <!-- Branch Selector - Clean dropdown only -->
    <div class="sidebar-branch-selector">
        <select id="sidebarBranchSelector" onchange="switchBranch(this.value)" <?= !$is_admin ? 'disabled' : '' ?>>
            <?php if ($is_admin): ?>
                <option value="all" <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>🌐 All Branches</option>
            <?php endif; ?>
            <?php foreach ($branches as $branch): ?>
                <option value="<?= $branch['id'] ?>" <?= $selected_branch_id == $branch['id'] ? 'selected' : '' ?>>
                    🏥 <?= htmlspecialchars($branch['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <nav class="sidebar-nav">
        
        <!-- ===== RECEPTION MENU ===== -->
        <div class="nav-label">Reception</div>
        
        <a href="../reception/dashboard.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isModuleActive('reception') && isActive('dashboard.php') ? 'active' : '' ?>">
            <i class="fas fa-home"></i> Dashboard
        </a>
        
        <a href="../reception/patients.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('patients.php') || isActive('view_patient.php') || isActive('new_patient.php') ? 'active' : '' ?>">
            <i class="fas fa-users"></i> Patients
            <span class="badge"><?= $total_patients ?></span>
        </a>
        
        <a href="../reception/new_patient.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('new_patient.php') ? 'active' : '' ?>">
            <i class="fas fa-user-plus"></i> Register Patient
        </a>
        
        <a href="../reception/appointments.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('appointments.php') || isActive('new_appointment.php') ? 'active' : '' ?>">
            <i class="fas fa-calendar-check"></i> Appointments
            <span class="badge"><?= $today_appointments ?></span>
        </a>
        
        <a href="../reception/assign_doctor.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('assign_doctor.php') ? 'active' : '' ?>">
            <i class="fas fa-user-md"></i> Assign Doctor
        </a>
        
        <a href="../reception/search.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('search.php') ? 'active' : '' ?>">
            <i class="fas fa-search"></i> Search Patients
        </a>
        
        <!-- ===== CASHIER MENU ===== -->
        <div class="nav-label mt-2">Cashier</div>
        
        <a href="../cashier/dashboard.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isModuleActive('cashier') && isActive('dashboard.php') ? 'active' : '' ?>">
            <i class="fas fa-home"></i> Dashboard
        </a>
        
        <a href="../cashier/payments.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('payments.php') || isActive('new_payment.php') ? 'active' : '' ?>">
            <i class="fas fa-money-bill-wave"></i> Payments
            <span class="badge"><?= $pending_payments ?></span>
        </a>
        
        <a href="../cashier/new_payment.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('new_payment.php') ? 'active' : '' ?>">
            <i class="fas fa-plus-circle"></i> New Payment
        </a>
        
        <a href="../cashier/payment_history.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('payment_history.php') ? 'active' : '' ?>">
            <i class="fas fa-history"></i> Payment History
        </a>
        
        <a href="../cashier/daily_summary.php?branch=<?= $selected_branch_id ?>" 
           class="sidebar-link <?= isActive('daily_summary.php') ? 'active' : '' ?>">
            <i class="fas fa-file-alt"></i> Daily Summary
            <span class="badge">TSh <?= number_format($today_revenue) ?></span>
        </a>
        
        <!-- ===== ACCOUNT ===== -->
        <div class="nav-label mt-2">Account</div>
        
        <a href="../reception/profile.php" 
           class="sidebar-link <?= isActive('profile.php') ? 'active' : '' ?>">
            <i class="fas fa-user-circle"></i> Profile
        </a>
        
        <a href="../../auth/reception_logout.php" 
           class="sidebar-link logout-link">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
        
    </nav>
</aside>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        window.location.href = url.toString();
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        var sidebar = document.getElementById('sidebar');
        var sidebarToggle = document.getElementById('sidebarToggle');
        
        if (sidebarToggle) {
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
    
    console.log('%c🏥 Reception Sidebar (Clean)', 'font-size:16px; font-weight:bold; color:#0AA84F;');
    console.log('%c👤 User: <?= $_SESSION['full_name'] ?? 'Rose Mwangi' ?>', 'font-size:12px; color:#9EC5FE;');
    console.log('%c👑 Role: <?= $display_role ?>', 'font-size:12px; color:#9EC5FE;');
</script>