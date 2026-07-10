<?php
// ================================================================
// FILE: frontend/pages/admin/employees.php
// SUPER ADMIN - EMPLOYEE MANAGEMENT
// WITH SHARED HEADER, SIDEBAR & BEAUTIFUL PDF EXPORT
// BRAICK DISPENSARY
// ================================================================

session_start();

// Check if logged in as super admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit;
}

// Include database and helpers
require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// BRANCH SELECTION
// ================================================================
$selected_branch_id = $_GET['branch'] ?? 'all';
$branch_name = 'All Branches';

// Check if branch_id column exists
$columns = [];
$col_check = $db->query("SHOW COLUMNS FROM users");
while ($col = $col_check->fetch(PDO::FETCH_ASSOC)) {
    $columns[] = $col['Field'];
}

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
// SEARCH FROM TOP BAR
// ================================================================
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';

// ================================================================
// GET EMPLOYEES
// ================================================================
$query = "SELECT u.*, b.name as branch_name";

$has_employee_roles = false;
try {
    $check = $db->query("SHOW TABLES LIKE 'employee_roles'");
    if ($check->rowCount() > 0) {
        $has_employee_roles = true;
        $query .= ", GROUP_CONCAT(DISTINCT r.name SEPARATOR ', ') as role_names";
    } else {
        $query .= ", '' as role_names";
    }
} catch (Exception $e) {
    $query .= ", '' as role_names";
}

$has_employee_depts = false;
try {
    $check = $db->query("SHOW TABLES LIKE 'employee_departments'");
    if ($check->rowCount() > 0) {
        $has_employee_depts = true;
        $query .= ", GROUP_CONCAT(DISTINCT d.name SEPARATOR ', ') as department_names";
    } else {
        $query .= ", '' as department_names";
    }
} catch (Exception $e) {
    $query .= ", '' as department_names";
}

$query .= " FROM users u LEFT JOIN branches b ON ";

if (in_array('branch_id', $columns)) {
    $query .= "u.branch_id = b.id";
} else {
    $query .= "1=0";
}

if ($has_employee_roles) {
    $query .= " LEFT JOIN employee_roles er ON er.user_id = u.id LEFT JOIN roles r ON r.id = er.role_id";
}

if ($has_employee_depts) {
    $query .= " LEFT JOIN employee_departments ed ON ed.user_id = u.id LEFT JOIN departments d ON d.id = ed.department_id";
}

$query .= " WHERE u.role != 'admin'";

// Branch filter
if ($selected_branch_id !== 'all' && in_array('branch_id', $columns)) {
    $query .= " AND u.branch_id = " . (int)$selected_branch_id;
}

// Search filter
if ($search) {
    $query .= " AND (u.full_name LIKE '%$search%' OR u.username LIKE '%$search%' OR u.email LIKE '%$search%' OR u.phone LIKE '%$search%')";
}

// Role filter
if ($role_filter && $has_employee_roles) {
    $query .= " AND u.id IN (SELECT user_id FROM employee_roles WHERE role_id = " . (int)$role_filter . ")";
}

// Status filter
if ($status_filter && in_array('status', $columns)) {
    $query .= " AND u.status = '$status_filter'";
}

$query .= " GROUP BY u.id ORDER BY u.created_at DESC";

$stmt = $db->query($query);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STATISTICS
// ================================================================

$query_count = "SELECT COUNT(*) as count FROM users WHERE role != 'admin'";
if ($selected_branch_id !== 'all' && in_array('branch_id', $columns)) {
    $query_count .= " AND branch_id = " . (int)$selected_branch_id;
}
$stmt = $db->query($query_count);
$total_employees = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

if (in_array('status', $columns)) {
    $query_active = "SELECT COUNT(*) as count FROM users WHERE role != 'admin' AND status = 'active'";
    if ($selected_branch_id !== 'all' && in_array('branch_id', $columns)) {
        $query_active .= " AND branch_id = " . (int)$selected_branch_id;
    }
    $stmt = $db->query($query_active);
    $active_employees = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    $query_inactive = "SELECT COUNT(*) as count FROM users WHERE role != 'admin' AND status = 'inactive'";
    if ($selected_branch_id !== 'all' && in_array('branch_id', $columns)) {
        $query_inactive .= " AND branch_id = " . (int)$selected_branch_id;
    }
    $stmt = $db->query($query_inactive);
    $inactive_employees = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} else {
    $active_employees = 0;
    $inactive_employees = 0;
}

$query_doctors = "SELECT COUNT(*) as count FROM users WHERE role = 'doctor' AND status = 'active'";
if ($selected_branch_id !== 'all' && in_array('branch_id', $columns)) {
    $query_doctors .= " AND branch_id = " . (int)$selected_branch_id;
}
$stmt = $db->query($query_doctors);
$total_doctors = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// ================================================================
// GET BRANCHES, ROLES
// ================================================================
$branches = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

$roles = [];
try {
    $stmt = $db->query("SELECT id, name FROM roles ORDER BY name");
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $roles = [];
}

// ================================================================
// HANDLE DELETE ACTION
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = (int)($_POST['user_id'] ?? 0);
    
    if ($action === 'delete') {
        $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && $user['role'] !== 'admin') {
            try {
                $stmt = $db->prepare("DELETE FROM employee_roles WHERE user_id = ?");
                $stmt->execute([$user_id]);
            } catch (Exception $e) {}
            
            try {
                $stmt = $db->prepare("DELETE FROM employee_departments WHERE user_id = ?");
                $stmt->execute([$user_id]);
            } catch (Exception $e) {}
            
            $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
            if ($stmt->execute([$user_id])) {
                $message = "Employee deleted successfully!";
                $message_type = 'success';
            } else {
                $message = "Failed to delete employee!";
                $message_type = 'error';
            }
        } else {
            $message = "Cannot delete admin user!";
            $message_type = 'error';
        }
    }
}

// ================================================================
// GET STATISTICS FOR SIDEBAR
// ================================================================
$total_employees = $total_employees ?? 0;
$total_doctors = $total_doctors ?? 0;
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
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

$search_display = $search;

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

<!-- PDF & EXCEL EXPORT LIBRARIES -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>

<style>
    /* ================================================================
       ADDITIONAL TABLE STYLES
       ================================================================ */
    
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
    
    .btn-pdf {
        background: #DC2626;
        color: white;
        padding: 4px 12px;
        font-size: 0.7rem;
        border-radius: 6px;
    }
    .btn-pdf:hover {
        background: #B91C1C;
        transform: translateY(-1px);
    }
    
    .btn-excel {
        background: #059669;
        color: white;
        padding: 4px 12px;
        font-size: 0.7rem;
        border-radius: 6px;
    }
    .btn-excel:hover {
        background: #047857;
        transform: translateY(-1px);
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
    
    .filter-select {
        padding: 5px 10px;
        border-radius: 8px;
        border: 2px solid var(--border-color);
        background: var(--bg-card);
        font-size: 0.75rem;
        outline: none;
        color: var(--text-primary);
        transition: all 0.3s;
    }
    
    .filter-select:focus {
        border-color: #0B5ED7;
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15);
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
    
    .role-badge {
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 500;
        background: #D2E3FC;
        color: #0B4EA8;
        margin: 1px;
        display: inline-block;
    }
    
    .dept-badge {
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 500;
        background: #D1FAE5;
        color: #047857;
        margin: 1px;
        display: inline-block;
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
    
    .result-count {
        font-size: 0.75rem;
        color: var(--text-secondary);
        font-weight: 500;
    }
    .result-count strong { color: #0B5ED7; }
    
    .highlight {
        background: #FEF08A !important;
        padding: 1px 4px;
        border-radius: 3px;
        font-weight: 600;
    }
    
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
            <input type="text" id="globalSearch" placeholder="Search employees by name, email, username...">
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
            <img src="<?= $logo_path ?>" alt="Profile" class="avatar"
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
                <i class="fas fa-users mr-2" style="color: var(--blue-600);"></i> Employee Management
            </h1>
            <p class="page-subtitle">
                Manage all employees • <?= htmlspecialchars($branch_name) ?>
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name) ?>
                </span>
                <?php if ($search_display): ?>
                    <span class="ml-2 inline-flex bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-xs border border-yellow-200">
                        <i class="fas fa-search mr-1"></i> Results for: "<?= htmlspecialchars($search_display) ?>"
                        <a href="?branch=<?= $selected_branch_id ?>" class="ml-2 text-yellow-600 hover:text-yellow-800" title="Clear search">
                            <i class="fas fa-times"></i>
                        </a>
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <button onclick="exportToPDF()" class="btn btn-pdf btn-sm">
                <i class="fas fa-file-pdf"></i> PDF
            </button>
            <button onclick="exportToExcel()" class="btn btn-excel btn-sm">
                <i class="fas fa-file-excel"></i> Excel
            </button>
            <a href="add_employee.php?branch=<?= $selected_branch_id ?>" class="btn btn-blue btn-sm">
                <i class="fas fa-user-plus"></i> Add
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
    <!-- STATISTICS CARDS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5">
        
        <div class="stat-card blue animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Total Employees</p>
                    <p class="stat-number"><?= $total_employees ?></p>
                    <span class="stat-trend"><i class="fas fa-users"></i> All staff</span>
                </div>
                <div class="stat-icon"><i class="fas fa-users"></i></div>
            </div>
        </div>
        
        <div class="stat-card green animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Active</p>
                    <p class="stat-number"><?= $active_employees ?></p>
                    <span class="stat-trend"><i class="fas fa-user-check"></i> Online</span>
                </div>
                <div class="stat-icon"><i class="fas fa-user-check"></i></div>
            </div>
        </div>
        
        <div class="stat-card blue-dark animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Inactive</p>
                    <p class="stat-number"><?= $inactive_employees ?></p>
                    <span class="stat-trend"><i class="fas fa-user-slash"></i> Offline</span>
                </div>
                <div class="stat-icon"><i class="fas fa-user-slash"></i></div>
            </div>
        </div>
        
        <div class="stat-card green-dark animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Doctors</p>
                    <p class="stat-number"><?= $total_doctors ?></p>
                    <span class="stat-trend"><i class="fas fa-user-md"></i> Active</span>
                </div>
                <div class="stat-icon"><i class="fas fa-user-md"></i></div>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- FILTERS -->
    <!-- ================================================================ -->
    <div class="card mb-5">
        <div class="flex flex-wrap items-center gap-3">
            <span class="text-sm font-medium text-gray-600"><i class="fas fa-filter mr-1"></i> Filters:</span>
            
            <select id="roleFilter" class="filter-select" onchange="applyFilters()">
                <option value="">All Roles</option>
                <?php foreach ($roles as $role): ?>
                    <option value="<?= $role['id'] ?>" <?= $role_filter == $role['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($role['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <select id="statusFilter" class="filter-select" onchange="applyFilters()">
                <option value="">All Status</option>
                <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
            
            <button onclick="clearFilters()" class="btn btn-outline btn-sm">
                <i class="fas fa-times"></i> Clear
            </button>
            
            <span class="result-count ml-auto">
                <strong><?= count($employees) ?></strong> record(s) found
            </span>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- EMPLOYEES TABLE -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-blue mr-2"></i> Employee List
            </h3>
        </div>
        
        <div class="table-wrap">
            <table class="data-table" id="employeeTable">
                <thead>
                    <tr>
                        <th style="border-radius: 8px 0 0 0;">Employee</th>
                        <th>Username</th>
                        <th>Branch</th>
                        <th>Departments</th>
                        <th>Roles</th>
                        <th>Status</th>
                        <th style="border-radius: 0 8px 0 0; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($employees) > 0): ?>
                        <?php foreach ($employees as $emp): ?>
                            <tr>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-sm">
                                            <?= strtoupper(substr($emp['full_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <p class="font-medium text-sm text-gray-800">
                                                <?php 
                                                    $name = htmlspecialchars($emp['full_name']);
                                                    if ($search_display) {
                                                        $name = preg_replace('/(' . preg_quote($search_display, '/') . ')/i', '<span class="highlight">$1</span>', $name);
                                                    }
                                                    echo $name;
                                                ?>
                                            </p>
                                            <p class="text-xs text-gray-400">
                                                <?php 
                                                    $email = htmlspecialchars($emp['email']);
                                                    if ($search_display) {
                                                        $email = preg_replace('/(' . preg_quote($search_display, '/') . ')/i', '<span class="highlight">$1</span>', $email);
                                                    }
                                                    echo $email;
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="text-xs font-mono bg-gray-100 px-2 py-0.5 rounded">
                                        <?php 
                                            $username = htmlspecialchars($emp['username'] ?? 'N/A');
                                            if ($search_display) {
                                                $username = preg_replace('/(' . preg_quote($search_display, '/') . ')/i', '<span class="highlight">$1</span>', $username);
                                            }
                                            echo $username;
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="text-sm"><?= htmlspecialchars($emp['branch_name'] ?? 'Not Assigned') ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($emp['department_names']) && $emp['department_names'] != ''): ?>
                                        <?php foreach (explode(', ', $emp['department_names']) as $dept): ?>
                                            <span class="dept-badge"><?= htmlspecialchars($dept) ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">None</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($emp['role_names']) && $emp['role_names'] != ''): ?>
                                        <?php foreach (explode(', ', $emp['role_names']) as $role): ?>
                                            <span class="role-badge"><?= htmlspecialchars($role) ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">None</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (isset($emp['status'])): ?>
                                        <span class="badge <?= $emp['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>">
                                            <i class="fas fa-circle text-[5px]"></i>
                                            <?= ucfirst($emp['status']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-info">
                                            <i class="fas fa-circle text-[5px]"></i> Active
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="employee_profile.php?id=<?= $emp['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                           class="btn btn-view" 
                                           title="View Profile">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <a href="edit_employee.php?id=<?= $emp['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                           class="btn btn-edit" 
                                           title="Edit Employee">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <button onclick="deleteEmployee(<?= $emp['id'] ?>)" 
                                                class="btn btn-delete" 
                                                title="Delete Employee">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-8 text-gray-400">
                                <i class="fas fa-users text-3xl block mb-2"></i>
                                <?php if ($search_display): ?>
                                    No employees found matching "<strong><?= htmlspecialchars($search_display) ?></strong>"
                                <?php else: ?>
                                    No employees found. Click "Add Employee" to get started.
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Employee Management
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
<!-- JAVASCRIPT - WITH BEAUTIFUL PDF EXPORT -->
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
    var globalSearch = document.getElementById('globalSearch');
    var searchBtn = document.getElementById('searchBtn');
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
    // GLOBAL SEARCH
    // ================================================================
    function performSearch() {
        var query = globalSearch.value.trim();
        var branch = '<?= $selected_branch_id ?>';
        var role = document.getElementById('roleFilter').value;
        var status = document.getElementById('statusFilter').value;
        
        var url = window.location.pathname + '?branch=' + branch;
        if (query) url += '&search=' + encodeURIComponent(query);
        if (role) url += '&role=' + role;
        if (status) url += '&status=' + status;
        
        globalSearch.value = '';
        window.location.href = url;
    }
    
    searchBtn?.addEventListener('click', performSearch);
    globalSearch?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

    // ================================================================
    // BRANCH SWITCHER
    // ================================================================
    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        var search = globalSearch.value.trim();
        if (search) url.searchParams.set('search', search);
        window.location.href = url.toString();
    }

    // ================================================================
    // FILTERS
    // ================================================================
    function applyFilters() {
        var search = globalSearch.value.trim();
        var role = document.getElementById('roleFilter').value;
        var status = document.getElementById('statusFilter').value;
        var branch = '<?= $selected_branch_id ?>';
        
        var url = window.location.pathname + '?branch=' + branch;
        if (search) url += '&search=' + encodeURIComponent(search);
        if (role) url += '&role=' + role;
        if (status) url += '&status=' + status;
        
        window.location.href = url;
    }
    
    function clearFilters() {
        document.getElementById('roleFilter').value = '';
        document.getElementById('statusFilter').value = '';
        globalSearch.value = '';
        applyFilters();
    }

    // ================================================================
    // DELETE EMPLOYEE
    // ================================================================
    function deleteEmployee(userId) {
        if (confirm('⚠️ Are you sure you want to DELETE this employee?\n\nThis action CANNOT be undone!')) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input type="hidden" name="action" value="delete"><input type="hidden" name="user_id" value="' + userId + '">';
            document.body.appendChild(form);
            form.submit();
        }
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
    // PDF EXPORT - BEAUTIFUL DESIGN WITH BLUE BORDERS & SUMMARY CARDS
    // ================================================================
    function exportToPDF() {
        showToast('PDF Export', 'Generating PDF report...', 'info');
        
        try {
            var { jsPDF } = window.jspdf;
            var doc = new jsPDF('l', 'mm', 'a4');
            
            var branchName = '<?= htmlspecialchars($branch_name) ?>';
            var searchTerm = '<?= htmlspecialchars($search_display) ?>';
            var totalEmployees = '<?= $total_employees ?>';
            var activeEmployees = '<?= $active_employees ?>';
            var inactiveEmployees = '<?= $inactive_employees ?>';
            var totalDoctors = '<?= $total_doctors ?>';
            var currentDate = new Date().toLocaleString();
            
            // ===== HEADER =====
            // Blue border at top
            doc.setFillColor('#0B5ED7');
            doc.rect(10, 8, 277, 5, 'F');
            
            // Title
            doc.setFontSize(22);
            doc.setTextColor('#0B5ED7');
            doc.text('BRAICK DISPENSARY', 14, 22);
            
            doc.setFontSize(14);
            doc.setTextColor('#1E293B');
            doc.text('Employee List Report', 14, 30);
            
            doc.setFontSize(9);
            doc.setTextColor('#64748B');
            var headerInfo = 'Branch: ' + branchName;
            if (searchTerm) {
                headerInfo += ' | Search: "' + searchTerm + '"';
            }
            headerInfo += ' | Generated: ' + currentDate;
            doc.text(headerInfo, 14, 38);
            
            // ===== SUMMARY CARDS =====
            var startY = 46;
            
            // Blue border around summary section
            doc.setDrawColor('#0B5ED7');
            doc.setLineWidth(1);
            doc.rect(10, startY - 4, 277, 34, 'S');
            
            // Card 1: Total Employees - Blue
            doc.setFillColor('#0B5ED7');
            doc.rect(14, startY, 48, 26, 'F');
            doc.setTextColor('#FFFFFF');
            doc.setFontSize(7);
            doc.setFont('helvetica', 'normal');
            doc.text('TOTAL EMPLOYEES', 18, startY + 8);
            doc.setFontSize(13);
            doc.setFont('helvetica', 'bold');
            doc.text(totalEmployees, 18, startY + 20);
            
            // Card 2: Active - Green
            doc.setFillColor('#059669');
            doc.rect(66, startY, 42, 26, 'F');
            doc.setTextColor('#FFFFFF');
            doc.setFontSize(7);
            doc.setFont('helvetica', 'normal');
            doc.text('ACTIVE', 70, startY + 8);
            doc.setFontSize(13);
            doc.setFont('helvetica', 'bold');
            doc.text(activeEmployees, 70, startY + 20);
            
            // Card 3: Inactive - Dark Blue
            doc.setFillColor('#0B3D8A');
            doc.rect(112, startY, 42, 26, 'F');
            doc.setTextColor('#FFFFFF');
            doc.setFontSize(7);
            doc.setFont('helvetica', 'normal');
            doc.text('INACTIVE', 116, startY + 8);
            doc.setFontSize(13);
            doc.setFont('helvetica', 'bold');
            doc.text(inactiveEmployees, 116, startY + 20);
            
            // Card 4: Doctors - Dark Green
            doc.setFillColor('#047857');
            doc.rect(158, startY, 42, 26, 'F');
            doc.setTextColor('#FFFFFF');
            doc.setFontSize(7);
            doc.setFont('helvetica', 'normal');
            doc.text('DOCTORS', 162, startY + 8);
            doc.setFontSize(13);
            doc.setFont('helvetica', 'bold');
            doc.text(totalDoctors, 162, startY + 20);
            
            // Card 5: Records - Light Blue
            doc.setFillColor('#1A73E8');
            doc.rect(204, startY, 36, 26, 'F');
            doc.setTextColor('#FFFFFF');
            doc.setFontSize(7);
            doc.setFont('helvetica', 'normal');
            doc.text('RECORDS', 208, startY + 8);
            doc.setFontSize(13);
            doc.setFont('helvetica', 'bold');
            var rowCount = document.querySelectorAll('#employeeTable tbody tr').length;
            doc.text(rowCount.toString(), 208, startY + 20);
            
            // Card 6: Branch - Dark Blue
            doc.setFillColor('#0B3D8A');
            doc.rect(244, startY, 38, 26, 'F');
            doc.setTextColor('#FFFFFF');
            doc.setFontSize(7);
            doc.setFont('helvetica', 'normal');
            doc.text('BRANCH', 248, startY + 8);
            doc.setFontSize(11);
            doc.setFont('helvetica', 'bold');
            doc.text(branchName.length > 10 ? branchName.substring(0, 10) + '..' : branchName, 248, startY + 20);
            
            // ===== TABLE =====
            var tableStartY = startY + 34;
            var table = document.getElementById('employeeTable');
            var headers = [];
            var rows = [];
            
            // Get headers (skip Actions column)
            var headerCells = table.querySelectorAll('thead th');
            for (var i = 0; i < headerCells.length - 1; i++) {
                headers.push(headerCells[i].textContent.trim());
            }
            
            // Get rows
            var rowElements = table.querySelectorAll('tbody tr');
            for (var r = 0; r < rowElements.length; r++) {
                var rowData = [];
                var cells = rowElements[r].querySelectorAll('td');
                for (var c = 0; c < cells.length - 1; c++) {
                    var cellText = cells[c].textContent.trim();
                    cellText = cellText.replace(/\s+/g, ' ');
                    // Clean up highlights
                    cellText = cellText.replace(/<span[^>]*>/g, '').replace(/<\/span>/g, '');
                    rowData.push(cellText);
                }
                rows.push(rowData);
            }
            
            doc.autoTable({
                head: [headers],
                body: rows,
                startY: tableStartY,
                styles: {
                    fontSize: 8,
                    cellPadding: 3,
                    font: 'helvetica',
                },
                headStyles: {
                    fillColor: '#0B5ED7',
                    textColor: '#FFFFFF',
                    fontSize: 8,
                    fontStyle: 'bold',
                },
                alternateRowStyles: {
                    fillColor: '#F8FAFC',
                },
                margin: { left: 14, right: 14 },
                tableWidth: 'auto',
            });
            
            // ===== FOOTER WITH BLUE BORDER =====
            var finalY = doc.lastAutoTable.finalY + 10;
            
            // Blue border at bottom
            doc.setFillColor('#0B5ED7');
            doc.rect(10, finalY, 277, 4, 'F');
            
            doc.setFontSize(7);
            doc.setTextColor('#94A3B8');
            doc.text('Braick Dispensary Management System - Employee List Report', 14, finalY + 12);
            doc.text('Page 1 of 1', 280, finalY + 12, { align: 'right' });
            
            // ===== SAVE =====
            doc.save('employees_' + branchName.replace(/\s/g, '_') + '_' + new Date().toISOString().slice(0,10) + '.pdf');
            
            showToast('Success', 'PDF exported successfully!', 'success');
        } catch (error) {
            console.error('PDF Export Error:', error);
            showToast('Error', 'Failed to export PDF: ' + error.message, 'error');
        }
    }

    // ================================================================
    // EXCEL EXPORT
    // ================================================================
    function exportToExcel() {
        showToast('Excel Export', 'Preparing Excel export...', 'info');
        
        try {
            var branchName = '<?= htmlspecialchars($branch_name) ?>';
            var searchTerm = '<?= htmlspecialchars($search_display) ?>';
            var totalEmployees = '<?= $total_employees ?>';
            var activeEmployees = '<?= $active_employees ?>';
            var inactiveEmployees = '<?= $inactive_employees ?>';
            var totalDoctors = '<?= $total_doctors ?>';
            var rowCount = document.querySelectorAll('#employeeTable tbody tr').length;
            
            var data = [];
            
            // Header
            data.push(['BRAICK DISPENSARY']);
            data.push(['Employee List Report']);
            data.push(['']);
            
            // Summary Cards
            data.push(['SUMMARY STATISTICS']);
            data.push(['']);
            data.push(['Total Employees', 'Active', 'Inactive', 'Doctors', 'Records']);
            data.push([totalEmployees, activeEmployees, inactiveEmployees, totalDoctors, rowCount]);
            data.push(['']);
            
            // Branch Info
            data.push(['Branch: ' + branchName + (searchTerm ? ' | Search: "' + searchTerm + '"' : '')]);
            data.push(['Generated: ' + new Date().toLocaleString()]);
            data.push(['']);
            
            // Table
            var table = document.getElementById('employeeTable');
            var headers = [];
            var headerCells = table.querySelectorAll('thead th');
            for (var h = 0; h < headerCells.length - 1; h++) {
                headers.push(headerCells[h].textContent.trim());
            }
            data.push(headers);
            
            var rowElements = table.querySelectorAll('tbody tr');
            for (var r = 0; r < rowElements.length; r++) {
                var rowData = [];
                var cells = rowElements[r].querySelectorAll('td');
                for (var c = 0; c < cells.length - 1; c++) {
                    rowData.push(cells[c].textContent.trim());
                }
                data.push(rowData);
            }
            
            // Footer
            data.push([]);
            data.push(['']);
            data.push(['Total Employees: ' + rowCount]);
            data.push(['Exported on: ' + new Date().toLocaleString()]);
            data.push(['Braick Dispensary Management System']);
            
            var wb = XLSX.utils.book_new();
            var ws = XLSX.utils.aoa_to_sheet(data);
            
            ws['!cols'] = [
                { wch: 25 },
                { wch: 18 },
                { wch: 20 },
                { wch: 22 },
                { wch: 22 },
                { wch: 15 }
            ];
            
            XLSX.utils.book_append_sheet(wb, ws, 'Employees');
            
            var fileName = 'employees_' + branchName.replace(/\s/g, '_') + '_' + new Date().toISOString().slice(0,10) + '.xlsx';
            XLSX.writeFile(wb, fileName);
            
            showToast('Success', 'Excel exported successfully!', 'success');
        } catch (error) {
            console.error('Excel Export Error:', error);
            showToast('Error', 'Failed to export Excel', 'error');
        }
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
        document.getElementById('currentDateTime').textContent = dateStr + ' • ' + timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // KEYBOARD SHORTCUTS
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            globalSearch?.focus();
            globalSearch?.select();
        }
        if (e.key === 'Escape' && document.activeElement === globalSearch) {
            globalSearch.value = '';
            performSearch();
        }
        // Ctrl+Shift+P - PDF Export
        if (e.ctrlKey && e.shiftKey && e.key === 'P') {
            e.preventDefault();
            exportToPDF();
        }
        // Ctrl+Shift+E - Excel Export
        if (e.ctrlKey && e.shiftKey && e.key === 'E') {
            e.preventDefault();
            exportToExcel();
        }
    });

    console.log('%c👥 Braick - Employee Management', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📊 Total Employees: <?= $total_employees ?>', 'font-size:13px; color:#059669;');
    console.log('%c📄 PDF Export: Beautiful design with blue borders', 'font-size:13px; color:#64748B;');
    console.log('%c📊 Excel Export: Included', 'font-size:13px; color:#64748B;');
    console.log('%c🔗 Shared Header & Sidebar: ACTIVE', 'font-size:13px; color:#64748B;');
    console.log('%c🌙 Dark Mode: ' + (localStorage.getItem('darkMode') === 'true' ? 'ON' : 'OFF'), 'font-size:13px; color:#64748B;');
</script>

</body>
</html>