<?php
// ================================================================
// FILE: frontend/pages/admin/employee_profile.php
// SUPER ADMIN - EMPLOYEE PROFILE
// BRAICK DISPENSARY
// WITH SHARED HEADER & SIDEBAR
// ================================================================

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit;
}

require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

$employee_id = (int)($_GET['id'] ?? 0);
$selected_branch_id = $_GET['branch'] ?? 'all';

if ($employee_id <= 0) {
    header('Location: employees.php?branch=' . $selected_branch_id);
    exit;
}

// ================================================================
// GET EMPLOYEE DATA
// ================================================================
$stmt = $db->prepare("
    SELECT u.*, b.name as branch_name 
    FROM users u
    LEFT JOIN branches b ON u.branch_id = b.id
    WHERE u.id = ? AND u.role != 'admin'
");
$stmt->execute([$employee_id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    header('Location: employees.php?branch=' . $selected_branch_id);
    exit;
}

// ================================================================
// GET EMPLOYEE ROLES
// ================================================================
$employee_roles = [];
try {
    $stmt = $db->prepare("
        SELECT r.id, r.name, r.description 
        FROM employee_roles er
        JOIN roles r ON er.role_id = r.id
        WHERE er.user_id = ?
        ORDER BY r.name
    ");
    $stmt->execute([$employee_id]);
    $employee_roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $employee_roles = [];
}

// If no roles in employee_roles, check if user has role from users table
if (empty($employee_roles) && !empty($employee['role'])) {
    try {
        $stmt = $db->prepare("SELECT id, name, description FROM roles WHERE name = ?");
        $stmt->execute([$employee['role']]);
        $primary_role = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($primary_role) {
            $employee_roles[] = $primary_role;
        } else {
            $employee_roles[] = [
                'id' => 0,
                'name' => ucfirst($employee['role']),
                'description' => 'Primary role'
            ];
        }
    } catch (Exception $e) {
        $employee_roles[] = [
            'id' => 0,
            'name' => ucfirst($employee['role']),
            'description' => 'Primary role'
        ];
    }
}

// ================================================================
// GET EMPLOYEE DEPARTMENTS
// ================================================================
$employee_departments = [];
try {
    $stmt = $db->prepare("
        SELECT d.id, d.name, d.description 
        FROM employee_departments ed
        JOIN departments d ON ed.department_id = d.id
        WHERE ed.user_id = ?
        ORDER BY d.name
    ");
    $stmt->execute([$employee_id]);
    $employee_departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $employee_departments = [];
}

// ================================================================
// GET ALL ROLES AND DEPARTMENTS (for info)
// ================================================================
$all_roles = [];
try {
    $stmt = $db->query("SELECT id, name FROM roles ORDER BY name");
    $all_roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $all_roles = [];
}

$all_departments = [];
try {
    $stmt = $db->query("SELECT id, name FROM departments ORDER BY name");
    $all_departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $all_departments = [];
}

// ================================================================
// GET BRANCHES FOR SELECTOR
// ================================================================
$branches_list = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
$branches_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
// BRANCH NAME FOR DISPLAY
// ================================================================
$branch_name = 'All Branches';
if ($selected_branch_id !== 'all' && is_numeric($selected_branch_id)) {
    $branch_id = (int)$selected_branch_id;
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) {
        $branch_name = $branch_data['name'];
    }
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
       PROFILE STYLES
       ================================================================ */
    
    .profile-avatar {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3rem;
        font-weight: 700;
        background: #E8F0FE;
        color: #0B5ED7;
        border: 4px solid #0B5ED7;
        flex-shrink: 0;
    }
    
    [data-theme="dark"] .profile-avatar {
        background: #1E3A5F;
        color: #6EA8FE;
        border-color: #6EA8FE;
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
    
    .badge {
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
    
    .badge-success { background: #059669; color: white; }
    .badge-danger { background: #EF4444; color: white; }
    .badge-info { background: #0B5ED7; color: white; }
    .badge-warning { background: #F59E0B; color: white; }
    .badge-purple { background: #7B2FBE; color: white; }
    
    .role-badge {
        padding: 6px 16px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #E8F0FE;
        color: #0B5ED7;
        border: 1px solid #D2E3FC;
        transition: all 0.3s ease;
    }
    
    .role-badge:hover {
        transform: scale(1.02);
        box-shadow: 0 2px 8px rgba(11, 94, 215, 0.15);
    }
    
    [data-theme="dark"] .role-badge {
        background: #1E3A5F;
        color: #6EA8FE;
        border-color: #1E3A5F;
    }
    
    .dept-badge {
        padding: 6px 16px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #ECFDF5;
        color: #059669;
        border: 1px solid #D1FAE5;
        transition: all 0.3s ease;
    }
    
    .dept-badge:hover {
        transform: scale(1.02);
        box-shadow: 0 2px 8px rgba(5, 150, 105, 0.15);
    }
    
    [data-theme="dark"] .dept-badge {
        background: #1A3A2A;
        color: #34D399;
        border-color: #1A3A2A;
    }
    
    /* Card */
    .card {
        background: var(--bg-card);
        border-radius: 20px;
        padding: 24px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
    }
    
    .card:hover {
        border-color: #0B5ED7;
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.06);
    }
    
    .card-title {
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
    
    [data-theme="dark"] .card-title {
        color: #6EA8FE;
    }
    
    .empty-state {
        background: #FEF3C7;
        border: 1px solid #F59E0B;
        border-radius: 10px;
        padding: 14px 18px;
        color: #92400E;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    [data-theme="dark"] .empty-state {
        background: #3A2A1A;
        border-color: #F59E0B;
        color: #FBBF24;
    }
    
    /* Stat Box */
    .stat-box {
        text-align: center;
        padding: 16px;
        border-radius: 12px;
        background: var(--bg-body);
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
    }
    
    .stat-box:hover {
        border-color: #0B5ED7;
        background: #E8F0FE;
        transform: translateY(-2px);
    }
    
    [data-theme="dark"] .stat-box:hover {
        background: #1E3A5F;
    }
    
    .stat-box .stat-number {
        font-size: 1.5rem;
        font-weight: 700;
        color: #0B5ED7;
    }
    
    [data-theme="dark"] .stat-box .stat-number {
        color: #6EA8FE;
    }
    
    .stat-box .stat-label {
        font-size: 0.7rem;
        color: var(--text-secondary);
    }
    
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
    
    @media (max-width: 640px) {
        .profile-avatar {
            width: 70px;
            height: 70px;
            font-size: 2rem;
        }
        .card {
            padding: 16px;
        }
        .stat-box {
            padding: 12px;
        }
        .stat-box .stat-number {
            font-size: 1.2rem;
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
            <input type="text" id="searchInput" placeholder="Search employees...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
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
                <i class="fas fa-user-circle mr-2" style="color: var(--blue-600);"></i> Employee Profile
            </h1>
            <p class="page-subtitle">
                View employee details, roles and departments
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name) ?>
                </span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-user mr-1"></i> <?= htmlspecialchars($employee['full_name']) ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="edit_employee.php?id=<?= $employee['id'] ?>&branch=<?= $selected_branch_id ?>" class="btn btn-green btn-sm">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="employees.php?branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PROFILE HEADER -->
    <!-- ================================================================ -->
    <div class="card mb-5 animate-fade-in-up">
        <div class="flex flex-col md:flex-row items-center gap-6">
            <div class="profile-avatar">
                <?= strtoupper(substr($employee['full_name'], 0, 1)) ?>
            </div>
            
            <div class="flex-1 text-center md:text-left">
                <h2 class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($employee['full_name']) ?></h2>
                <p class="text-gray-500">
                    <i class="fas fa-briefcase mr-1"></i> 
                    <?= ucfirst(htmlspecialchars($employee['role'])) ?>
                </p>
                <div class="flex flex-wrap gap-2 mt-2 justify-center md:justify-start">
                    <span class="badge badge-info">
                        <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($employee['branch_name'] ?? 'Not Assigned') ?>
                    </span>
                    <span class="badge <?= ($employee['status'] ?? 'active') === 'active' ? 'badge-success' : 'badge-danger' ?>">
                        <i class="fas fa-circle text-[6px]"></i> <?= ucfirst($employee['status'] ?? 'Active') ?>
                    </span>
                    <span class="badge badge-warning">
                        <i class="fas fa-id-card mr-1"></i> ID: <?= htmlspecialchars($employee['username']) ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATS ROW -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5 animate-fade-in-up">
        <div class="stat-box">
            <p class="stat-number"><?= count($employee_roles) ?></p>
            <p class="stat-label"><i class="fas fa-user-tag mr-1"></i> Roles</p>
        </div>
        <div class="stat-box">
            <p class="stat-number"><?= count($employee_departments) ?></p>
            <p class="stat-label"><i class="fas fa-building mr-1"></i> Departments</p>
        </div>
        <div class="stat-box">
            <p class="stat-number"><?= date('d/m/Y', strtotime($employee['created_at'])) ?></p>
            <p class="stat-label"><i class="fas fa-calendar-plus mr-1"></i> Joined</p>
        </div>
        <div class="stat-box">
            <p class="stat-number">
                <?php
                    try {
                        $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE branch_id = ? AND role != 'admin'");
                        $stmt->execute([$employee['branch_id']]);
                        $branch_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
                        echo $branch_count;
                    } catch (Exception $e) {
                        echo '0';
                    }
                ?>
            </p>
            <p class="stat-label"><i class="fas fa-users mr-1"></i> Branch Staff</p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- DETAILS GRID -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        
        <!-- ================================================================ -->
        <!-- PERSONAL INFORMATION -->
        <!-- ================================================================ -->
        <div class="card animate-fade-in-up">
            <h3 class="card-title">
                <i class="fas fa-user-circle"></i> Personal Information
            </h3>
            <div class="space-y-3">
                <div>
                    <p class="info-label">Full Name</p>
                    <p class="info-value"><?= htmlspecialchars($employee['full_name']) ?></p>
                </div>
                <div>
                    <p class="info-label">Username</p>
                    <p class="info-value"><?= htmlspecialchars($employee['username']) ?></p>
                </div>
                <div>
                    <p class="info-label">Email</p>
                    <p class="info-value"><?= htmlspecialchars($employee['email']) ?></p>
                </div>
                <div>
                    <p class="info-label">Phone</p>
                    <p class="info-value"><?= htmlspecialchars($employee['phone'] ?? 'Not provided') ?></p>
                </div>
                <div>
                    <p class="info-label">Primary Role</p>
                    <p class="info-value capitalize"><?= htmlspecialchars($employee['role']) ?></p>
                </div>
                <div>
                    <p class="info-label">Branch</p>
                    <p class="info-value"><?= htmlspecialchars($employee['branch_name'] ?? 'Not Assigned') ?></p>
                </div>
                <div>
                    <p class="info-label">Status</p>
                    <p class="info-value">
                        <span class="badge <?= ($employee['status'] ?? 'active') === 'active' ? 'badge-success' : 'badge-danger' ?>">
                            <i class="fas fa-circle text-[6px]"></i> <?= ucfirst($employee['status'] ?? 'Active') ?>
                        </span>
                    </p>
                </div>
                <div>
                    <p class="info-label">Joined</p>
                    <p class="info-value"><?= date('F d, Y h:i A', strtotime($employee['created_at'])) ?></p>
                </div>
            </div>
        </div>

        <!-- ================================================================ -->
        <!-- ROLES & DEPARTMENTS -->
        <!-- ================================================================ -->
        <div class="space-y-5">
            
            <!-- ROLES -->
            <div class="card animate-fade-in-up">
                <h3 class="card-title">
                    <i class="fas fa-user-tag"></i> Assigned Roles
                    <span class="text-sm font-normal text-gray-400">(<?= count($employee_roles) ?>)</span>
                </h3>
                
                <?php if (count($employee_roles) > 0): ?>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($employee_roles as $role): ?>
                            <span class="role-badge">
                                <i class="fas fa-circle text-[8px] text-blue-600"></i>
                                <?= htmlspecialchars($role['name'] ?? 'Unknown') ?>
                                <?php if (!empty($role['description'])): ?>
                                    <span class="text-xs text-gray-400 font-normal ml-1">- <?= htmlspecialchars($role['description']) ?></span>
                                <?php endif; ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-exclamation-triangle text-yellow-600"></i>
                        <div>
                            <strong>No roles assigned</strong>
                            <span class="text-xs block">Edit this employee to assign roles.</span>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($all_roles) && count($all_roles) > 0): ?>
                    <div class="mt-3 text-xs text-gray-400">
                        <i class="fas fa-info-circle mr-1"></i>
                        <strong><?= count($all_roles) ?></strong> role(s) available in the system
                    </div>
                <?php endif; ?>
            </div>

            <!-- DEPARTMENTS -->
            <div class="card animate-fade-in-up">
                <h3 class="card-title">
                    <i class="fas fa-building"></i> Assigned Departments
                    <span class="text-sm font-normal text-gray-400">(<?= count($employee_departments) ?>)</span>
                </h3>
                
                <?php if (count($employee_departments) > 0): ?>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($employee_departments as $dept): ?>
                            <span class="dept-badge">
                                <i class="fas fa-circle text-[8px] text-green-600"></i>
                                <?= htmlspecialchars($dept['name'] ?? 'Unknown') ?>
                                <?php if (!empty($dept['description'])): ?>
                                    <span class="text-xs text-gray-400 font-normal ml-1">- <?= htmlspecialchars($dept['description']) ?></span>
                                <?php endif; ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-exclamation-triangle text-yellow-600"></i>
                        <div>
                            <strong>No departments assigned</strong>
                            <span class="text-xs block">Edit this employee to assign departments.</span>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($all_departments) && count($all_departments) > 0): ?>
                    <div class="mt-3 text-xs text-gray-400">
                        <i class="fas fa-info-circle mr-1"></i>
                        <strong><?= count($all_departments) ?></strong> department(s) available in the system
                    </div>
                <?php endif; ?>
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
            Employee Profile
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
        document.getElementById('currentDateTime').textContent = 
            now.toLocaleDateString('en-US', { 
                weekday: 'short', 
                month: 'short', 
                day: 'numeric', 
                year: 'numeric' 
            }) + 
            ' • ' + 
            now.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit', 
                second: '2-digit', 
                hour12: true 
            });
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    console.log('%c👤 Braick - Employee Profile', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Employee: <?= htmlspecialchars($employee['full_name']) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📋 Roles: <?= count($employee_roles) ?> assigned', 'font-size:13px; color:#64748B;');
    console.log('%c📋 Departments: <?= count($employee_departments) ?> assigned', 'font-size:13px; color:#64748B;');
    console.log('%c🔗 Shared Header & Sidebar: ACTIVE', 'font-size:13px; color:#64748B;');
    console.log('%c🌙 Dark Mode: ' + (localStorage.getItem('darkMode') === 'true' ? 'ON' : 'OFF'), 'font-size:13px; color:#64748B;');
</script>

</body>
</html>