<?php
// ================================================================
// FILE: frontend/pages/admin/add_branch.php
// ADD / MANAGE BRANCHES - SUPER ADMIN
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION - Admin Only
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['user_id'] = 1;
    $_SESSION['full_name'] = 'Admin John';
    $_SESSION['role'] = 'admin';
    $_SESSION['branch_id'] = 1;
}

// Include database and helpers
require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// VARIABLES
// ================================================================
$message = '';
$message_type = '';
$branch_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = $branch_id > 0;

// ================================================================
// GET BRANCH DATA FOR EDIT
// ================================================================
$branch_data = [
    'name' => '',
    'location' => '',
    'phone' => '',
    'email' => '',
    'status' => 'active'
];

if ($is_edit) {
    $stmt = $db->prepare("SELECT * FROM branches WHERE id = ?");
    $stmt->execute([$branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$branch_data) {
        $message = 'Branch not found!';
        $message_type = 'error';
        $is_edit = false;
        $branch_id = 0;
    }
}

// ================================================================
// PROCESS FORM SUBMISSION
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $name = trim($_POST['name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    // Validation
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Branch name is required';
    }
    
    if (empty($location)) {
        $errors[] = 'Location is required';
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address';
    }
    
    if (empty($errors)) {
        try {
            if ($_POST['action'] === 'add') {
                // Check if branch name already exists
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM branches WHERE name = ?");
                $stmt->execute([$name]);
                $exists = $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
                
                if ($exists) {
                    $message = 'Branch name already exists!';
                    $message_type = 'error';
                } else {
                    // Insert new branch
                    $stmt = $db->prepare("
                        INSERT INTO branches (name, location, phone, email, status, created_at) 
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$name, $location, $phone, $email, $status]);
                    
                    // Log activity
                    $new_id = $db->lastInsertId();
                    logActivity($db, $_SESSION['user_id'], 'branch_added', "New branch added: $name (ID: $new_id)");
                    
                    $message = 'Branch added successfully!';
                    $message_type = 'success';
                    
                    // Clear form data after successful add
                    if ($_POST['action'] === 'add') {
                        $branch_data = [
                            'name' => '',
                            'location' => '',
                            'phone' => '',
                            'email' => '',
                            'status' => 'active'
                        ];
                    }
                }
            } elseif ($_POST['action'] === 'edit' && $branch_id > 0) {
                // Update branch
                $stmt = $db->prepare("
                    UPDATE branches 
                    SET name = ?, location = ?, phone = ?, email = ?, status = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$name, $location, $phone, $email, $status, $branch_id]);
                
                // Log activity
                logActivity($db, $_SESSION['user_id'], 'branch_updated', "Branch updated: $name (ID: $branch_id)");
                
                $message = 'Branch updated successfully!';
                $message_type = 'success';
                
                // Refresh data
                $stmt = $db->prepare("SELECT * FROM branches WHERE id = ?");
                $stmt->execute([$branch_id]);
                $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            $message = 'Database error: ' . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = implode('<br>', $errors);
        $message_type = 'error';
    }
}

// ================================================================
// HANDLE TOGGLE STATUS (Activate/Deactivate)
// ================================================================
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $toggle_id = (int)$_GET['toggle'];
    
    // Get current status
    $stmt = $db->prepare("SELECT name, status FROM branches WHERE id = ?");
    $stmt->execute([$toggle_id]);
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($branch) {
        $new_status = $branch['status'] === 'active' ? 'inactive' : 'active';
        $action_text = $new_status === 'active' ? 'activated' : 'deactivated';
        
        $stmt = $db->prepare("UPDATE branches SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$new_status, $toggle_id]);
        
        // Log activity
        logActivity($db, $_SESSION['user_id'], 'branch_status_toggled', "Branch $action_text: {$branch['name']} (ID: $toggle_id)");
        
        $message = "Branch '{$branch['name']}' has been " . ($new_status === 'active' ? 'activated' : 'deactivated') . " successfully!";
        $message_type = 'success';
        
        // Refresh branch list
        $stmt = $db->query("SELECT * FROM branches ORDER BY name ASC");
        $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// ================================================================
// GET ALL BRANCHES FOR LISTING
// ================================================================
$branches = [];
$stmt = $db->query("SELECT * FROM branches ORDER BY name ASC");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<style>
    /* ================================================================
       ADDITIONAL STYLES FOR BRANCH PAGE
       ================================================================ */
    
    /* White background for form fields */
    .form-input-white {
        background: #FFFFFF !important;
        color: #1E293B !important;
        border: 2px solid #E2E8F0 !important;
        transition: all 0.3s ease !important;
    }
    
    .form-input-white:focus {
        border-color: #0B5ED7 !important;
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15) !important;
        background: #FFFFFF !important;
    }
    
    .form-input-white::placeholder {
        color: #94A3B8 !important;
    }
    
    /* Dark mode override for form fields */
    [data-theme="dark"] .form-input-white {
        background: #1E293B !important;
        color: #F1F5F9 !important;
        border-color: #334155 !important;
    }
    
    [data-theme="dark"] .form-input-white:focus {
        border-color: #6EA8FE !important;
        background: #1E293B !important;
    }
    
    /* Table Header - Blue Theme */
    .table-blue thead th {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8) !important;
        color: #FFFFFF !important;
        font-weight: 700 !important;
        font-size: 0.7rem !important;
        text-transform: uppercase !important;
        letter-spacing: 0.05em !important;
        padding: 12px 16px !important;
        border-bottom: 3px solid #0A4CA8 !important;
        white-space: nowrap !important;
    }
    
    .table-blue thead th:first-child {
        border-radius: 8px 0 0 0 !important;
    }
    
    .table-blue thead th:last-child {
        border-radius: 0 8px 0 0 !important;
    }
    
    .table-blue tbody td {
        padding: 10px 16px !important;
        border-bottom: 1px solid #E2E8F0 !important;
        color: #1E293B !important;
        vertical-align: middle !important;
    }
    
    .table-blue tbody tr:hover td {
        background: #E8F0FE !important;
    }
    
    /* Dark mode table */
    [data-theme="dark"] .table-blue tbody td {
        color: #F1F5F9 !important;
        border-bottom-color: #334155 !important;
    }
    
    [data-theme="dark"] .table-blue tbody tr:hover td {
        background: #1A3A5F !important;
    }
    
    /* Badge styles */
    .badge {
        padding: 4px 14px !important;
        border-radius: 20px !important;
        font-size: 0.65rem !important;
        font-weight: 600 !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 4px !important;
        border: none !important;
    }
    
    .badge-active {
        background: #D1FAE5 !important;
        color: #059669 !important;
    }
    
    .badge-inactive {
        background: #FEE2E2 !important;
        color: #EF4444 !important;
    }
    
    [data-theme="dark"] .badge-active {
        background: #1A3A2A !important;
        color: #34D399 !important;
    }
    
    [data-theme="dark"] .badge-inactive {
        background: #3A1A1A !important;
        color: #F87171 !important;
    }
    
    /* Card header blue accent */
    .card-blue-header {
        border-left: 4px solid #0B5ED7 !important;
    }
    
    /* Form label */
    .form-label {
        font-weight: 600 !important;
        color: #1E293B !important;
        font-size: 0.85rem !important;
        margin-bottom: 6px !important;
        display: block !important;
    }
    
    [data-theme="dark"] .form-label {
        color: #F1F5F9 !important;
    }
    
    /* Required star */
    .required-star {
        color: #EF4444 !important;
        margin-left: 2px !important;
    }
    
    /* Card shadow */
    .card-shadow {
        box-shadow: 0 1px 3px rgba(0,0,0,0.08) !important;
    }
    
    [data-theme="dark"] .card-shadow {
        box-shadow: 0 1px 3px rgba(0,0,0,0.3) !important;
    }
    
    /* Toggle button styles */
    .btn-toggle-active {
        background: #059669 !important;
        color: white !important;
        border: none !important;
        padding: 4px 12px !important;
        border-radius: 6px !important;
        font-size: 0.65rem !important;
        font-weight: 600 !important;
        cursor: pointer !important;
        transition: all 0.3s !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 4px !important;
        text-decoration: none !important;
    }
    
    .btn-toggle-active:hover {
        background: #047857 !important;
        transform: translateY(-1px) !important;
        box-shadow: 0 2px 8px rgba(5, 150, 105, 0.3) !important;
    }
    
    .btn-toggle-inactive {
        background: #EF4444 !important;
        color: white !important;
        border: none !important;
        padding: 4px 12px !important;
        border-radius: 6px !important;
        font-size: 0.65rem !important;
        font-weight: 600 !important;
        cursor: pointer !important;
        transition: all 0.3s !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 4px !important;
        text-decoration: none !important;
    }
    
    .btn-toggle-inactive:hover {
        background: #DC2626 !important;
        transform: translateY(-1px) !important;
        box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3) !important;
    }
    
    /* Dark mode toggle buttons */
    [data-theme="dark"] .btn-toggle-active {
        background: #059669 !important;
    }
    
    [data-theme="dark"] .btn-toggle-active:hover {
        background: #047857 !important;
    }
    
    [data-theme="dark"] .btn-toggle-inactive {
        background: #EF4444 !important;
    }
    
    [data-theme="dark"] .btn-toggle-inactive:hover {
        background: #DC2626 !important;
    }
</style>

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
            <input type="text" id="searchInput" placeholder="Search branches...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
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
                <i class="fas fa-store-alt mr-2"></i> Manage Branches
            </h1>
            <p class="page-subtitle">
                <?= $is_edit ? 'Edit branch details' : 'Add a new branch to the system' ?>
                <span class="branch-tag ml-2">
                    <i class="fas fa-building"></i> <?= count($branches) ?> Branches Total
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="dashboard.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <?php if ($is_edit): ?>
                <a href="add_branch.php" class="btn btn-blue btn-sm">
                    <i class="fas fa-plus"></i> Add New
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="p-4 rounded-xl mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200 dark:bg-green-900/20 dark:text-green-300 dark:border-green-800' : 'bg-red-100 text-red-700 border border-red-200 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- ADD / EDIT BRANCH FORM -->
    <!-- ================================================================ -->
    <div class="card card-shadow mb-5">
        <div class="card-header card-blue-header">
            <h3 class="card-title">
                <i class="fas fa-<?= $is_edit ? 'edit' : 'plus-circle' ?> title-blue mr-2"></i>
                <?= $is_edit ? 'Edit Branch' : 'Add New Branch' ?>
            </h3>
        </div>
        
        <form method="POST" action="" class="space-y-4" style="margin-top: 12px;">
            <input type="hidden" name="action" value="<?= $is_edit ? 'edit' : 'add' ?>">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                
                <!-- Branch Name -->
                <div>
                    <label class="form-label">
                        Branch Name <span class="required-star">*</span>
                    </label>
                    <input type="text" name="name" value="<?= htmlspecialchars($branch_data['name'] ?? '') ?>" 
                           class="w-full px-4 py-2.5 rounded-lg form-input-white"
                           placeholder="e.g. Dodoma Branch" required>
                </div>
                
                <!-- Location -->
                <div>
                    <label class="form-label">
                        Location <span class="required-star">*</span>
                    </label>
                    <input type="text" name="location" value="<?= htmlspecialchars($branch_data['location'] ?? '') ?>" 
                           class="w-full px-4 py-2.5 rounded-lg form-input-white"
                           placeholder="e.g. Dodoma City, Tanzania" required>
                </div>
                
                <!-- Phone -->
                <div>
                    <label class="form-label">
                        Phone Number
                    </label>
                    <input type="tel" name="phone" value="<?= htmlspecialchars($branch_data['phone'] ?? '') ?>" 
                           class="w-full px-4 py-2.5 rounded-lg form-input-white"
                           placeholder="e.g. +255 700 000 001">
                </div>
                
                <!-- Email -->
                <div>
                    <label class="form-label">
                        Email Address
                    </label>
                    <input type="email" name="email" value="<?= htmlspecialchars($branch_data['email'] ?? '') ?>" 
                           class="w-full px-4 py-2.5 rounded-lg form-input-white"
                           placeholder="e.g. branch@braick.com">
                </div>
                
                <!-- Status -->
                <div>
                    <label class="form-label">
                        Status
                    </label>
                    <select name="status" 
                            class="w-full px-4 py-2.5 rounded-lg form-input-white">
                        <option value="active" <?= ($branch_data['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($branch_data['status'] ?? 'active') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                
                <!-- Submit -->
                <div class="flex items-end">
                    <button type="submit" class="btn btn-blue w-full md:w-auto" style="padding: 10px 24px;">
                        <i class="fas fa-<?= $is_edit ? 'save' : 'plus' ?> mr-2"></i>
                        <?= $is_edit ? 'Update Branch' : 'Add Branch' ?>
                    </button>
                    <?php if ($is_edit): ?>
                        <a href="add_branch.php" class="btn btn-outline ml-2" style="padding: 10px 20px;">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    <?php endif; ?>
                </div>
                
            </div>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- BRANCHES LIST - WITH BLUE TABLE HEADER -->
    <!-- ================================================================ -->
    <div class="card card-shadow">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-blue mr-2"></i>
                All Branches
                <span class="text-sm font-normal text-gray-400">(<?= count($branches) ?> branches)</span>
            </h3>
            <div class="flex gap-2">
                <input type="text" id="branchSearch" placeholder="Filter branches..." 
                       class="px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm form-input-white">
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="data-table table-blue w-full" id="branchesTable">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Branch Name</th>
                        <th>Location</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th style="width: 100px;">Status</th>
                        <th style="width: 110px;">Created</th>
                        <th style="width: 140px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($branches) > 0): ?>
                        <?php $i = 1; foreach ($branches as $branch): ?>
                            <tr>
                                <td class="font-bold text-blue-600 dark:text-blue-400"><?= $i++ ?></td>
                                <td class="font-semibold"><?= htmlspecialchars($branch['name']) ?></td>
                                <td><?= htmlspecialchars($branch['location']) ?></td>
                                <td><?= htmlspecialchars($branch['phone'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($branch['email'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge <?= $branch['status'] === 'active' ? 'badge-active' : 'badge-inactive' ?>">
                                        <i class="fas fa-<?= $branch['status'] === 'active' ? 'check-circle' : 'times-circle' ?> mr-1"></i>
                                        <?= ucfirst($branch['status']) ?>
                                    </span>
                                </td>
                                <td class="text-xs text-gray-500 dark:text-gray-400"><?= date('M d, Y', strtotime($branch['created_at'])) ?></td>
                                <td>
                                    <div class="flex gap-1">
                                        <!-- Edit Button -->
                                        <a href="add_branch.php?id=<?= $branch['id'] ?>" class="btn btn-blue btn-sm" title="Edit Branch">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <!-- Toggle Active/Inactive Button -->
                                        <?php if ($branch['status'] === 'active'): ?>
                                            <a href="?toggle=<?= $branch['id'] ?>" 
                                               class="btn-toggle-inactive" 
                                               onclick="return confirm('Are you sure you want to deactivate this branch?')" 
                                               title="Deactivate Branch">
                                                <i class="fas fa-times-circle"></i> Deactivate
                                            </a>
                                        <?php else: ?>
                                            <a href="?toggle=<?= $branch['id'] ?>" 
                                               class="btn-toggle-active" 
                                               onclick="return confirm('Are you sure you want to activate this branch?')" 
                                               title="Activate Branch">
                                                <i class="fas fa-check-circle"></i> Activate
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-8 text-gray-400">
                                <i class="fas fa-store-alt text-4xl block mb-3" style="color: #0B5ED7;"></i>
                                <p class="text-lg font-medium" style="color: #1E293B; dark:text-white;">No branches found</p>
                                <p class="text-sm">Click "Add New Branch" to create one</p>
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
            Manage Branches
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
    // SEARCH BRANCHES
    // ================================================================
    document.getElementById('branchSearch')?.addEventListener('keyup', function() {
        var filter = this.value.toLowerCase();
        var rows = document.querySelectorAll('#branchesTable tbody tr');
        
        rows.forEach(function(row) {
            var text = row.textContent.toLowerCase();
            row.style.display = text.includes(filter) ? '' : 'none';
        });
    });

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
        var el = document.getElementById('currentDateTime');
        if (el) {
            el.textContent = dateStr + ' • ' + timeStr;
        }
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    console.log('%c🏥 Braick Dispensary - Manage Branches', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📊 Total Branches: <?= count($branches) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🔵 Blue Theme Applied to Table Headers', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🔄 Branches use Activate/Deactivate instead of Delete', 'font-size:13px; color:#7B2FBE;');
</script>

</body>
</html>