<?php
// ================================================================
// FILE: frontend/pages/admin/employees.php
// SUPER ADMIN - EMPLOYEES MANAGEMENT
// WITH SOFT DELETE - User remains in database but cannot login
// VIEW ALL EMPLOYEES BY BRANCH
// ACTION BUTTONS: View, Edit, Deactivate (3 buttons only)
// BRAICK DISPENSARY - USING EXISTING DB TABLES
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
    header('Location: ../login.php');
    exit;
}

// ================================================================
// CHECK IF USER HAS ADMIN ACCESS
// ================================================================
if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET ADMIN DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// BRANCH SELECTION
// ================================================================
$selected_branch_id = $_GET['branch'] ?? 'all';
$branch_name = 'All Branches';

// ================================================================
// BRANCH NAME
// ================================================================
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
// GET BRANCHES FOR FILTER
// ================================================================
$branches = [];
$stmt = $db->query("SELECT id, name, location FROM branches WHERE status = 'active' ORDER BY name");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// ================================================================
// HANDLE DELETE ACTION (SOFT DELETE - Only status change)
// ================================================================
$message = '';
$message_type = '';

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $user_id_to_delete = (int)$_GET['delete'];
    
    $stmt = $db->prepare("SELECT id, role, full_name, branch_id FROM users WHERE id = ? AND role != 'admin'");
    $stmt->execute([$user_id_to_delete]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $stmt = $db->prepare("UPDATE users SET status = 'inactive', updated_at = NOW() WHERE id = ?");
        if ($stmt->execute([$user_id_to_delete])) {
            $message = "✅ Employee '" . htmlspecialchars($user['full_name']) . "' has been deactivated. They can no longer login but their records remain in the system.";
            $message_type = 'success';
            
            try {
                $log_stmt = $db->prepare("INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) VALUES (?, ?, 'employee_deactivated', ?, NOW())");
                $log_stmt->execute([$user_id, $user['branch_id'] ?? 1, "Deactivated employee: " . $user['full_name'] . " (ID: $user_id_to_delete) by " . $user_full_name]);
            } catch (Exception $e) {}
        } else {
            $message = "❌ Failed to deactivate employee. Please try again.";
            $message_type = 'error';
        }
    } else {
        $message = "❌ Employee not found or cannot be deactivated.";
        $message_type = 'error';
    }
}

// ================================================================
// HANDLE REACTIVATE ACTION
// ================================================================
if (isset($_GET['reactivate']) && is_numeric($_GET['reactivate'])) {
    $user_id_to_reactivate = (int)$_GET['reactivate'];
    
    $stmt = $db->prepare("SELECT id, role, full_name, branch_id FROM users WHERE id = ? AND role != 'admin'");
    $stmt->execute([$user_id_to_reactivate]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $stmt = $db->prepare("UPDATE users SET status = 'active', updated_at = NOW() WHERE id = ?");
        if ($stmt->execute([$user_id_to_reactivate])) {
            $message = "✅ Employee '" . htmlspecialchars($user['full_name']) . "' has been reactivated. They can now login again.";
            $message_type = 'success';
            
            try {
                $log_stmt = $db->prepare("INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) VALUES (?, ?, 'employee_reactivated', ?, NOW())");
                $log_stmt->execute([$user_id, $user['branch_id'] ?? 1, "Reactivated employee: " . $user['full_name'] . " (ID: $user_id_to_reactivate) by " . $user_full_name]);
            } catch (Exception $e) {}
        } else {
            $message = "❌ Failed to reactivate employee. Please try again.";
            $message_type = 'error';
        }
    } else {
        $message = "❌ Employee not found or cannot be reactivated.";
        $message_type = 'error';
    }
}

// ================================================================
// FETCH EMPLOYEES (Show all including inactive for management)
// ================================================================
$employees = [];
$filter = '';

if ($selected_branch_id !== 'all') {
    $filter = " AND u.branch_id = " . (int)$selected_branch_id;
}

$stmt = $db->query("
    SELECT 
        u.id,
        u.username,
        u.full_name,
        u.email,
        u.phone,
        u.role,
        u.branch_id,
        u.status,
        u.profile_pic,
        u.created_at,
        u.last_online,
        b.name as branch_name,
        b.location as branch_location
    FROM users u
    LEFT JOIN branches b ON u.branch_id = b.id
    WHERE u.role != 'admin'
    $filter
    ORDER BY u.status DESC, u.full_name ASC
");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// COUNT EMPLOYEES BY ROLE (Only active)
// ================================================================
$total_active = 0;
$total_inactive = 0;
$doctors = 0;
$receptionists = 0;
$pharmacists = 0;
$lab_technicians = 0;
$cashiers = 0;

foreach ($employees as $emp) {
    if ($emp['status'] === 'active') {
        $total_active++;
        switch ($emp['role']) {
            case 'doctor': $doctors++; break;
            case 'reception': $receptionists++; break;
            case 'pharmacy': $pharmacists++; break;
            case 'laboratory': $lab_technicians++; break;
            case 'cashier': $cashiers++; break;
        }
    } else {
        $total_inactive++;
    }
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

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
            <input type="text" id="searchInput" placeholder="Search employees...">
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
            <span class="notif-dot <?= $unread_notifications > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($user_full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
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
                <i class="fas fa-users-cog mr-2"></i> Employees Management
            </h1>
            <p class="page-subtitle">
                Manage all employees across branches
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($branch_name) ?>
                </span>
                <span class="ml-2 date-badge">
                    <i class="fas fa-calendar-day mr-1"></i> <?= date('F d, Y') ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="add_employee.php?branch=<?= $selected_branch_id ?>" class="btn btn-blue btn-sm">
                <i class="fas fa-user-plus"></i> Add Employee
            </a>
            <button onclick="location.reload()" class="btn btn-outline btn-sm">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="message-box <?= $message_type === 'success' ? 'success' : 'error' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-5">
        
        <div class="stat-card solid-blue" style="min-height: 100px; padding: 16px 20px;">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label" style="font-size: 0.75rem;">Total Active</p>
                    <p class="stat-number" style="font-size: 1.6rem;"><?= number_format($total_active) ?></p>
                </div>
                <div class="stat-icon" style="width: 40px; height: 40px; font-size: 1rem;">
                    <i class="fas fa-user-check"></i>
                </div>
            </div>
        </div>
        
        <div class="stat-card solid-gray" style="min-height: 100px; padding: 16px 20px; background: #64748B;">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label" style="font-size: 0.75rem;">Inactive</p>
                    <p class="stat-number" style="font-size: 1.6rem;"><?= number_format($total_inactive) ?></p>
                </div>
                <div class="stat-icon" style="width: 40px; height: 40px; font-size: 1rem;">
                    <i class="fas fa-user-slash"></i>
                </div>
            </div>
        </div>
        
        <div class="stat-card solid-green" style="min-height: 100px; padding: 16px 20px;">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label" style="font-size: 0.75rem;">Doctors</p>
                    <p class="stat-number" style="font-size: 1.6rem;"><?= number_format($doctors) ?></p>
                </div>
                <div class="stat-icon" style="width: 40px; height: 40px; font-size: 1rem;">
                    <i class="fas fa-user-md"></i>
                </div>
            </div>
        </div>
        
        <div class="stat-card solid-dark-blue" style="min-height: 100px; padding: 16px 20px;">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label" style="font-size: 0.75rem;">Receptionists</p>
                    <p class="stat-number" style="font-size: 1.6rem;"><?= number_format($receptionists) ?></p>
                </div>
                <div class="stat-icon" style="width: 40px; height: 40px; font-size: 1rem;">
                    <i class="fas fa-headset"></i>
                </div>
            </div>
        </div>
        
        <div class="stat-card solid-purple" style="min-height: 100px; padding: 16px 20px;">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label" style="font-size: 0.75rem;">Pharmacy</p>
                    <p class="stat-number" style="font-size: 1.6rem;"><?= number_format($pharmacists) ?></p>
                </div>
                <div class="stat-icon" style="width: 40px; height: 40px; font-size: 1rem;">
                    <i class="fas fa-pills"></i>
                </div>
            </div>
        </div>
        
        <div class="stat-card solid-orange" style="min-height: 100px; padding: 16px 20px;">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label" style="font-size: 0.75rem;">Lab & Cashier</p>
                    <p class="stat-number" style="font-size: 1.6rem;"><?= number_format($lab_technicians + $cashiers) ?></p>
                </div>
                <div class="stat-icon" style="width: 40px; height: 40px; font-size: 1rem;">
                    <i class="fas fa-flask"></i>
                </div>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- EMPLOYEES TABLE - 3 ACTION BUTTONS ONLY -->
    <!-- ================================================================ -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-blue mr-2"></i> Employee List
                <span class="text-xs text-gray-400 font-normal">(<?= number_format(count($employees)) ?> employees)</span>
            </h3>
            <div class="flex gap-2">
                <input type="text" id="tableSearch" placeholder="Filter employees..." 
                       class="px-3 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="data-table" id="employeesTable">
                <thead>
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th>Employee</th>
                        <th>Role</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Branch</th>
                        <th>Status</th>
                        <th style="width: 120px; min-width: 120px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($employees) > 0): ?>
                        <?php $i = 1; foreach ($employees as $emp): ?>
                            <tr class="<?= $emp['status'] === 'inactive' ? 'inactive-row' : '' ?>">
                                <td><?= $i++ ?></td>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <?php if (!empty($emp['profile_pic'])): ?>
                                            <img src="/dispensary_system/frontend/assets/uploads/profiles/<?= $emp['profile_pic'] ?>" 
                                                 alt="<?= htmlspecialchars($emp['full_name']) ?>" 
                                                 class="w-8 h-8 rounded-full object-cover"
                                                 onerror="this.style.display='none'">
                                        <?php endif; ?>
                                        <?php if (empty($emp['profile_pic'])): ?>
                                            <div class="w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center text-sm font-bold">
                                                <?= strtoupper(substr($emp['full_name'], 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <p class="font-medium text-sm"><?= htmlspecialchars($emp['full_name']) ?></p>
                                            <p class="text-xs text-gray-400">@<?= htmlspecialchars($emp['username']) ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="role-badge role-<?= $emp['role'] ?>">
                                        <?php
                                            $role_labels = [
                                                'doctor' => 'Doctor',
                                                'reception' => 'Reception',
                                                'pharmacy' => 'Pharmacy',
                                                'laboratory' => 'Lab Tech',
                                                'cashier' => 'Cashier'
                                            ];
                                            echo $role_labels[$emp['role']] ?? ucfirst($emp['role']);
                                        ?>
                                    </span>
                                </td>
                                <td class="text-sm"><?= htmlspecialchars($emp['email']) ?></td>
                                <td class="text-sm"><?= htmlspecialchars($emp['phone'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="branch-badge">
                                        <i class="fas fa-store-alt mr-1"></i>
                                        <?= htmlspecialchars($emp['branch_name'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?= $emp['status'] === 'active' ? 'active' : 'inactive' ?>">
                                        <?= $emp['status'] === 'active' ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td>
                                    <!-- ACTION BUTTONS - 3 BUTTONS ONLY (View, Edit, Deactivate/Reactivate) -->
                                    <div class="action-buttons">
                                        <a href="view_employee.php?id=<?= $emp['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                           class="btn btn-sm btn-view action-btn" title="View Employee">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit_employee.php?id=<?= $emp['id'] ?>&branch=<?= $selected_branch_id ?>" 
                                           class="btn btn-sm btn-edit action-btn" title="Edit Employee">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($emp['status'] === 'active'): ?>
                                            <button onclick="confirmDelete(<?= $emp['id'] ?>, '<?= htmlspecialchars($emp['full_name']) ?>')" 
                                                    class="btn btn-sm btn-delete action-btn" title="Deactivate Employee">
                                                <i class="fas fa-user-slash"></i>
                                            </button>
                                        <?php else: ?>
                                            <button onclick="confirmReactivate(<?= $emp['id'] ?>, '<?= htmlspecialchars($emp['full_name']) ?>')" 
                                                    class="btn btn-sm btn-reactivate action-btn" title="Reactivate Employee">
                                                <i class="fas fa-undo"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center text-gray-400 text-sm py-5">
                                <i class="fas fa-users text-2xl block mb-2"></i>
                                No employees found in <?= htmlspecialchars($branch_name) ?>
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
            Employees Management
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- DELETE CONFIRMATION MODAL -->
<!-- ================================================================ -->
<div id="deleteModal" class="modal" style="display:none;">
    <div class="modal-overlay" onclick="closeModal()"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-exclamation-triangle text-red-500 mr-2"></i> Deactivate Employee</h3>
            <button onclick="closeModal()" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to deactivate <strong id="deleteName"></strong>?</p>
            <p class="text-sm text-gray-500 mt-2">
                <i class="fas fa-info-circle"></i> 
                This employee will no longer be able to login. Their records will remain in the system for historical data.
            </p>
        </div>
        <div class="modal-footer">
            <button onclick="closeModal()" class="btn btn-outline btn-sm">Cancel</button>
            <a href="#" id="deleteLink" class="btn btn-danger btn-sm">
                <i class="fas fa-user-slash"></i> Deactivate
            </a>
        </div>
    </div>
</div>

<!-- ================================================================ -->
<!-- REACTIVATE CONFIRMATION MODAL -->
<!-- ================================================================ -->
<div id="reactivateModal" class="modal" style="display:none;">
    <div class="modal-overlay" onclick="closeReactivateModal()"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-undo text-green-500 mr-2"></i> Reactivate Employee</h3>
            <button onclick="closeReactivateModal()" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to reactivate <strong id="reactivateName"></strong>?</p>
            <p class="text-sm text-gray-500 mt-2">
                <i class="fas fa-info-circle"></i> 
                This employee will be able to login again.
            </p>
        </div>
        <div class="modal-footer">
            <button onclick="closeReactivateModal()" class="btn btn-outline btn-sm">Cancel</button>
            <a href="#" id="reactivateLink" class="btn btn-success btn-sm">
                <i class="fas fa-undo"></i> Reactivate
            </a>
        </div>
    </div>
</div>

<style>
    /* ================================================================
       CUSTOM STYLES
       ================================================================ */
    
    .stat-card {
        border-radius: 12px;
        padding: 16px 20px;
        border: none;
        transition: all 0.3s ease;
        color: white;
        min-height: 100px;
        display: block;
        position: relative;
        overflow: hidden;
    }
    
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }
    
    .stat-card.solid-blue { background: #0B5ED7; }
    .stat-card.solid-green { background: #059669; }
    .stat-card.solid-dark-blue { background: #0A4CA8; }
    .stat-card.solid-purple { background: #7B2FBE; }
    .stat-card.solid-orange { background: #F59E0B; }
    .stat-card.solid-gray { background: #64748B; }
    
    .stat-card .stat-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        background: rgba(255,255,255,0.2);
        color: white;
        flex-shrink: 0;
    }
    
    .stat-card .stat-number {
        font-size: 1.6rem;
        font-weight: 700;
        color: white;
        line-height: 1.2;
    }
    
    .stat-card .stat-label {
        font-size: 0.75rem;
        color: rgba(255,255,255,0.9);
        font-weight: 500;
        margin-bottom: 2px;
    }
    
    /* Table Header - BLUE Background */
    .data-table thead th {
        background: #0B5ED7 !important;
        color: white !important;
        font-weight: 600;
        padding: 10px 12px;
        font-size: 0.6rem;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        border-bottom: none !important;
    }
    
    .data-table thead th:first-child {
        border-radius: 8px 0 0 0;
    }
    
    .data-table thead th:last-child {
        border-radius: 0 8px 0 0;
    }
    
    /* Inactive Row Styling */
    .inactive-row {
        opacity: 0.6;
        background: #F8FAFC !important;
    }
    
    .inactive-row td {
        border-bottom-color: #E2E8F0 !important;
    }
    
    [data-theme="dark"] .inactive-row {
        opacity: 0.5;
        background: #1E293B !important;
    }
    
    [data-theme="dark"] .inactive-row td {
        border-bottom-color: #334155 !important;
    }
    
    /* Action Buttons - 3 Buttons, Small Width */
    .action-buttons {
        display: flex;
        flex-direction: row;
        gap: 4px;
        align-items: center;
        justify-content: center;
        flex-wrap: nowrap;
    }
    
    .action-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 30px;
        height: 30px;
        padding: 0;
        border-radius: 6px;
        font-size: 0.7rem;
        transition: all 0.3s;
        cursor: pointer;
        border: none;
        text-decoration: none;
        flex-shrink: 0;
    }
    
    .action-btn i {
        font-size: 0.8rem;
    }
    
    .action-btn:hover {
        transform: scale(1.1);
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }
    
    .action-btn:active {
        transform: scale(0.95);
    }
    
    /* Role Badges */
    .role-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
        text-transform: uppercase;
    }
    
    .role-badge.role-doctor { background: #E8F0FE; color: #0B5ED7; }
    .role-badge.role-reception { background: #D1FAE5; color: #059669; }
    .role-badge.role-pharmacy { background: #FEF3C7; color: #D97706; }
    .role-badge.role-laboratory { background: #EDE9FE; color: #7B2FBE; }
    .role-badge.role-cashier { background: #FCE4EC; color: #DC2626; }
    
    [data-theme="dark"] .role-badge.role-doctor { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .role-badge.role-reception { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .role-badge.role-pharmacy { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .role-badge.role-laboratory { background: #2D1B4E; color: #A78BFA; }
    [data-theme="dark"] .role-badge.role-cashier { background: #3A1A1A; color: #F87171; }
    
    /* Branch Badge */
    .branch-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.55rem;
        font-weight: 500;
        background: #F1F5F9;
        color: #64748B;
    }
    
    [data-theme="dark"] .branch-badge {
        background: #334155;
        color: #94A3B8;
    }
    
    /* Status Badge */
    .status-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.55rem;
        font-weight: 600;
        text-transform: uppercase;
    }
    
    .status-badge.active { background: #D1FAE5; color: #059669; }
    .status-badge.inactive { background: #FEE2E2; color: #DC2626; }
    
    [data-theme="dark"] .status-badge.active { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .status-badge.inactive { background: #3A1A1A; color: #F87171; }
    
    /* Buttons */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        border-radius: 6px;
        font-weight: 600;
        font-size: 0.65rem;
        transition: all 0.3s;
        cursor: pointer;
        border: none;
        text-decoration: none;
    }
    
    .btn-sm { padding: 2px 6px; font-size: 0.6rem; }
    
    .btn-view { background: #0B5ED7; color: white; }
    .btn-view:hover { background: #0A4CA8; transform: scale(1.05); }
    
    .btn-edit { background: #F59E0B; color: white; }
    .btn-edit:hover { background: #D97706; transform: scale(1.05); }
    
    .btn-delete { background: #EF4444; color: white; }
    .btn-delete:hover { background: #DC2626; transform: scale(1.05); }
    
    .btn-reactivate { background: #059669; color: white; }
    .btn-reactivate:hover { background: #047857; transform: scale(1.05); }
    
    .btn-danger { background: #EF4444; color: white; }
    .btn-danger:hover { background: #DC2626; }
    
    .btn-success { background: #059669; color: white; }
    .btn-success:hover { background: #047857; }
    
    /* Message Box */
    .message-box {
        padding: 12px 16px;
        border-radius: 10px;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .message-box.success {
        background: #D1FAE5;
        color: #059669;
        border: 1px solid #059669;
    }
    
    .message-box.error {
        background: #FEE2E2;
        color: #DC2626;
        border: 1px solid #DC2626;
    }
    
    [data-theme="dark"] .message-box.success {
        background: #1A3A2A;
        color: #34D399;
        border-color: #34D399;
    }
    
    [data-theme="dark"] .message-box.error {
        background: #3A1A1A;
        color: #F87171;
        border-color: #F87171;
    }
    
    /* Modal */
    .modal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 1000;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .modal-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        cursor: pointer;
    }
    
    .modal-content {
        background: var(--bg-card);
        border-radius: 16px;
        max-width: 480px;
        width: 90%;
        position: relative;
        z-index: 1001;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        border: 1px solid var(--border-color);
    }
    
    .modal-header {
        padding: 16px 20px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .modal-header h3 {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }
    
    .modal-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: var(--text-secondary);
        padding: 0 4px;
    }
    
    .modal-close:hover {
        color: var(--text-primary);
    }
    
    .modal-body {
        padding: 20px;
        color: var(--text-primary);
    }
    
    .modal-footer {
        padding: 16px 20px;
        border-top: 1px solid var(--border-color);
        display: flex;
        justify-content: flex-end;
        gap: 8px;
    }
    
    /* Table Search */
    #tableSearch {
        min-width: 180px;
    }
    
    #tableSearch:focus {
        outline: none;
        ring: 2px solid #0B5ED7;
    }
    
    /* Toast */
    .toast-custom {
        position: fixed;
        bottom: 24px;
        right: 24px;
        padding: 14px 20px;
        border-radius: 12px;
        z-index: 999;
        max-width: 400px;
        transform: translateY(100px);
        opacity: 0;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        align-items: center;
        gap: 12px;
        color: white;
        box-shadow: 0 8px 30px rgba(0,0,0,0.15);
    }
    .toast-custom.show {
        transform: translateY(0);
        opacity: 1;
    }
    .toast-custom.success { background: #059669; }
    .toast-custom.error { background: #DC2626; }
    .toast-custom.info { background: #0B5ED7; }
    .toast-custom.warning { background: #D97706; }
    
    /* Responsive */
    @media (max-width: 768px) {
        .stat-card {
            min-height: 80px !important;
            padding: 12px 14px !important;
        }
        .stat-card .stat-number {
            font-size: 1.2rem !important;
        }
        .stat-card .stat-icon {
            width: 32px !important;
            height: 32px !important;
            font-size: 0.8rem !important;
        }
        .grid-cols-6 {
            grid-template-columns: repeat(3, 1fr) !important;
        }
        .action-btn {
            width: 26px !important;
            height: 26px !important;
        }
        .action-btn i {
            font-size: 0.65rem !important;
        }
        .data-table thead th {
            padding: 6px 8px !important;
            font-size: 0.5rem !important;
        }
        .data-table td {
            padding: 6px 8px !important;
            font-size: 0.7rem !important;
        }
    }
    
    @media (max-width: 480px) {
        .grid-cols-6 {
            grid-template-columns: repeat(2, 1fr) !important;
        }
        .action-btn {
            width: 22px !important;
            height: 22px !important;
        }
        .action-btn i {
            font-size: 0.55rem !important;
        }
        .action-buttons {
            gap: 2px !important;
        }
    }
</style>

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
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    var tableSearch = document.getElementById('tableSearch');

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
    // SEARCH
    // ================================================================
    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            var branch = '<?= $selected_branch_id ?>';
            window.location.href = 'search.php?q=' + encodeURIComponent(query) + '&branch=' + branch + '&type=employees';
        }
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

    // ================================================================
    // TABLE SEARCH (Filter)
    // ================================================================
    tableSearch?.addEventListener('keyup', function() {
        var filter = this.value.toLowerCase();
        var rows = document.querySelectorAll('#employeesTable tbody tr');
        
        rows.forEach(function(row) {
            var text = row.textContent.toLowerCase();
            row.style.display = text.indexOf(filter) > -1 ? '' : 'none';
        });
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
    // DELETE CONFIRMATION (Soft Delete - Deactivate)
    // ================================================================
    function confirmDelete(id, name) {
        document.getElementById('deleteName').textContent = name;
        document.getElementById('deleteLink').href = 'employees.php?delete=' + id + '&branch=<?= $selected_branch_id ?>';
        document.getElementById('deleteModal').style.display = 'flex';
    }
    
    function closeModal() {
        document.getElementById('deleteModal').style.display = 'none';
    }

    // ================================================================
    // REACTIVATE CONFIRMATION
    // ================================================================
    function confirmReactivate(id, name) {
        document.getElementById('reactivateName').textContent = name;
        document.getElementById('reactivateLink').href = 'employees.php?reactivate=' + id + '&branch=<?= $selected_branch_id ?>';
        document.getElementById('reactivateModal').style.display = 'flex';
    }
    
    function closeReactivateModal() {
        document.getElementById('reactivateModal').style.display = 'none';
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
        var dtEl = document.getElementById('currentDateTime');
        if (dtEl) dtEl.textContent = dateStr + ' • ' + timeStr;
        
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

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
    // CLOSE MODALS ON ESC KEY
    // ================================================================
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
            closeReactivateModal();
        }
    });

    console.log('%c👥 Braick Dispensary - Employees Management', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?> (<?= htmlspecialchars($user_role) ?>)', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($branch_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c👤 Active Employees: <?= number_format($total_active) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🚫 Inactive Employees: <?= number_format($total_inactive) ?>', 'font-size:13px; color:#EF4444;');
    console.log('%c🔒 Soft Delete: Users remain in database but cannot login', 'font-size:13px; color:#7B2FBE;');
    console.log('%c📌 Action Buttons: 3 buttons (View, Edit, Deactivate/Reactivate)', 'font-size:13px; color:#F59E0B;');
    console.log('%c📊 Tables: users, branches, activity_logs, notifications', 'font-size:13px; color:#34D399;');
    console.log('%c🔒 Login protection: ACTIVE', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>