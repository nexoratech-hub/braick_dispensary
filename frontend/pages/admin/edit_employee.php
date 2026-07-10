<?php
// ================================================================
// FILE: frontend/pages/admin/edit_employee.php
// SUPER ADMIN - EDIT EMPLOYEE (WITH ROLES & DEPARTMENTS)
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
$stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND role != 'admin'");
$stmt->execute([$employee_id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    header('Location: employees.php?branch=' . $selected_branch_id);
    exit;
}

// ================================================================
// GET EMPLOYEE'S CURRENT ROLES
// ================================================================
$employee_roles = [];
try {
    $stmt = $db->prepare("SELECT role_id FROM employee_roles WHERE user_id = ?");
    $stmt->execute([$employee_id]);
    $employee_roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $employee_roles = [];
}

// ================================================================
// GET EMPLOYEE'S CURRENT DEPARTMENTS
// ================================================================
$employee_departments = [];
try {
    $stmt = $db->prepare("SELECT department_id FROM employee_departments WHERE user_id = ?");
    $stmt->execute([$employee_id]);
    $employee_departments = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $employee_departments = [];
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
$branches_list = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
$branches_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET ROLES & DEPARTMENTS
// ================================================================
$roles = [];
try {
    $stmt = $db->query("SELECT id, name, description FROM roles ORDER BY name");
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $roles = [];
}

$departments = [];
try {
    $stmt = $db->query("SELECT id, name, description FROM departments ORDER BY name");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $departments = [];
}

// ================================================================
// HANDLE FORM SUBMISSION - FIXED (SAVES ROLES & DEPARTMENTS)
// ================================================================
$message = '';
$message_type = '';
$errors = [];

// Form data for display
$form_data = [
    'full_name' => $employee['full_name'],
    'username' => $employee['username'],
    'email' => $employee['email'],
    'phone' => $employee['phone'] ?? '',
    'branch_id' => $employee['branch_id'],
    'status' => $employee['status'] ?? 'active',
    'selected_roles' => $employee_roles,
    'selected_departments' => $employee_departments
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $branch_id = (int)($_POST['branch_id'] ?? 0);
    
    // Get selected roles and departments from checkboxes
    $selected_roles = $_POST['roles'] ?? [];
    $selected_departments = $_POST['departments'] ?? [];
    
    // Get primary role (first selected role)
    $primary_role = !empty($selected_roles) ? $selected_roles[0] : $employee['role'];
    
    // Validation
    if (empty($full_name)) {
        $errors[] = 'Full name is required';
    }
    if (empty($username)) {
        $errors[] = 'Username is required';
    }
    if (empty($email)) {
        $errors[] = 'Email is required';
    }
    if (empty($selected_roles)) {
        $errors[] = 'At least one role must be selected';
    }
    if ($branch_id <= 0) {
        $errors[] = 'Branch is required';
    }
    
    // Check if username exists (excluding current user)
    if (empty($errors) && $username !== $employee['username']) {
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $employee_id]);
        if ($stmt->fetch()) {
            $errors[] = 'Username already exists';
        }
    }
    
    // Check if email exists (excluding current user)
    if (empty($errors) && $email !== $employee['email']) {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $employee_id]);
        if ($stmt->fetch()) {
            $errors[] = 'Email already exists';
        }
    }
    
    // Update employee
    if (empty($errors)) {
        $stmt = $db->prepare("
            UPDATE users 
            SET full_name = ?, username = ?, email = ?, phone = ?, role = ?, branch_id = ?, status = ?
            WHERE id = ? AND role != 'admin'
        ");
        
        if ($stmt->execute([$full_name, $username, $email, $phone, $primary_role, $branch_id, $status, $employee_id])) {
            
            // ================================================================
            // UPDATE ROLES - Delete old and insert new (FIXED)
            // ================================================================
            try {
                // Delete old roles
                $stmt = $db->prepare("DELETE FROM employee_roles WHERE user_id = ?");
                $stmt->execute([$employee_id]);
                
                // Insert new roles
                if (!empty($selected_roles)) {
                    foreach ($selected_roles as $role_id) {
                        $stmt = $db->prepare("INSERT INTO employee_roles (user_id, role_id, assigned_by) VALUES (?, ?, ?)");
                        $stmt->execute([$employee_id, $role_id, $_SESSION['user_id']]);
                    }
                }
            } catch (Exception $e) {
                $errors[] = 'Error updating roles: ' . $e->getMessage();
            }
            
            // ================================================================
            // UPDATE DEPARTMENTS - Delete old and insert new (FIXED)
            // ================================================================
            try {
                // Delete old departments
                $stmt = $db->prepare("DELETE FROM employee_departments WHERE user_id = ?");
                $stmt->execute([$employee_id]);
                
                // Insert new departments
                if (!empty($selected_departments)) {
                    foreach ($selected_departments as $dept_id) {
                        $stmt = $db->prepare("INSERT INTO employee_departments (user_id, department_id, assigned_by) VALUES (?, ?, ?)");
                        $stmt->execute([$employee_id, $dept_id, $_SESSION['user_id']]);
                    }
                }
            } catch (Exception $e) {
                $errors[] = 'Error updating departments: ' . $e->getMessage();
            }
            
            // Log activity
            try {
                $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'employee_updated', ?)");
                $stmt->execute([$_SESSION['user_id'], "Employee $full_name updated with " . count($selected_roles) . " roles and " . count($selected_departments) . " departments"]);
            } catch (Exception $e) {}
            
            if (empty($errors)) {
                $message = "Employee updated successfully with " . count($selected_roles) . " role(s) and " . count($selected_departments) . " department(s)!";
                $message_type = 'success';
                header('Location: employees.php?branch=' . $branch_id . '&updated=1');
                exit;
            }
        } else {
            $errors[] = 'Failed to update employee. Please try again.';
        }
    }
    
    if (!empty($errors)) {
        $message = implode('<br>', $errors);
        $message_type = 'error';
        // Update form data with submitted values
        $form_data = [
            'full_name' => $full_name,
            'username' => $username,
            'email' => $email,
            'phone' => $phone,
            'branch_id' => $branch_id,
            'status' => $status,
            'selected_roles' => $selected_roles,
            'selected_departments' => $selected_departments
        ];
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
       ADDITIONAL FORM STYLES - BEAUTIFUL LIKE DASHBOARD
       ================================================================ */
    
    /* Form Card */
    .form-card {
        background: var(--bg-card);
        border-radius: 20px;
        padding: 28px 32px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }
    
    .form-card:hover {
        border-color: #0B5ED7;
        box-shadow: 0 8px 30px rgba(11, 94, 215, 0.08);
    }
    
    /* Form Header */
    .form-header {
        display: flex;
        align-items: center;
        gap: 16px;
        padding-bottom: 20px;
        margin-bottom: 24px;
        border-bottom: 2px solid var(--border-color);
    }
    
    .form-header-icon {
        width: 56px;
        height: 56px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
        flex-shrink: 0;
        background: linear-gradient(135deg, #0B5ED7, #1A73E8);
        color: white;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }
    
    .form-header h3 {
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0;
    }
    
    .form-header p {
        font-size: 0.85rem;
        color: var(--text-secondary);
        margin: 0;
    }
    
    /* Form Labels */
    .form-label {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 6px;
        display: block;
    }
    
    .form-label i {
        width: 20px;
        text-align: center;
        font-size: 0.85rem;
    }
    
    .form-label .required {
        color: #EF4444;
        margin-left: 2px;
    }
    
    /* Form Controls */
    .form-control {
        width: 100%;
        padding: 10px 16px;
        border: 2px solid var(--border-color);
        border-radius: 12px;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        outline: none;
        background: var(--bg-card);
        color: var(--text-primary);
        font-family: 'Inter', 'Segoe UI', sans-serif;
    }
    
    .form-control:focus {
        border-color: #0B5ED7;
        box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.12);
    }
    
    .form-control::placeholder {
        color: var(--text-secondary);
        opacity: 0.5;
    }
    
    .form-control:disabled {
        background: var(--bg-body);
        color: var(--text-secondary);
        cursor: not-allowed;
    }
    
    /* Form Row with Icon */
    .form-row-icon {
        position: relative;
    }
    
    .form-row-icon .form-control {
        padding-left: 44px;
    }
    
    .form-row-icon .input-icon {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-secondary);
        font-size: 1rem;
        pointer-events: none;
        transition: color 0.3s ease;
    }
    
    .form-row-icon .form-control:focus + .input-icon,
    .form-row-icon .form-control:focus ~ .input-icon {
        color: #0B5ED7;
    }
    
    /* Checkbox Group */
    .checkbox-group {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 8px;
        padding: 12px 14px;
        border: 2px solid var(--border-color);
        border-radius: 12px;
        background: var(--bg-body);
        min-height: 60px;
        transition: border-color 0.3s ease;
    }
    
    .checkbox-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 14px;
        border-radius: 10px;
        background: var(--bg-card);
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        cursor: pointer;
    }
    
    .checkbox-item:hover {
        border-color: #0B5ED7;
        background: #E8F0FE;
        transform: translateY(-1px);
    }
    
    [data-theme="dark"] .checkbox-item:hover {
        background: #1E3A5F;
    }
    
    .checkbox-item.checked {
        border-color: #0B5ED7;
        background: #E8F0FE;
    }
    
    [data-theme="dark"] .checkbox-item.checked {
        background: #1E3A5F;
    }
    
    .checkbox-item input[type="checkbox"] {
        width: 18px;
        height: 18px;
        accent-color: #0B5ED7;
        cursor: pointer;
        flex-shrink: 0;
    }
    
    .checkbox-item label {
        font-size: 0.85rem;
        font-weight: 500;
        color: var(--text-primary);
        cursor: pointer;
        width: 100%;
    }
    
    .checkbox-item .role-desc {
        font-size: 0.65rem;
        color: var(--text-secondary);
        font-weight: 400;
        display: block;
        opacity: 0.7;
    }
    
    /* Buttons */
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding: 10px 24px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        min-height: 44px;
        min-width: 120px;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #0B5ED7, #1A73E8);
        color: white;
        box-shadow: 0 4px 14px rgba(11, 94, 215, 0.3);
    }
    
    .btn-primary:hover {
        background: linear-gradient(135deg, #0A4CA8, #1557B0);
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(11, 94, 215, 0.4);
    }
    
    .btn-primary:active {
        transform: translateY(0px);
    }
    
    .btn-outline {
        background: transparent;
        color: var(--text-primary);
        border: 2px solid var(--border-color);
    }
    
    .btn-outline:hover {
        background: var(--bg-body);
        border-color: #0B5ED7;
        color: #0B5ED7;
        transform: translateY(-2px);
    }
    
    .btn-sm {
        padding: 6px 16px;
        font-size: 0.8rem;
        min-height: 36px;
        min-width: 90px;
    }
    
    /* Button Group */
    .form-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        padding-top: 24px;
        margin-top: 24px;
        border-top: 2px solid var(--border-color);
    }
    
    /* Section Title */
    .section-title {
        font-size: 1rem;
        font-weight: 600;
        color: #0B5ED7;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    [data-theme="dark"] .section-title {
        color: #6EA8FE;
    }
    
    .section-divider {
        border: none;
        border-top: 2px dashed var(--border-color);
        margin: 12px 0 16px;
    }
    
    .help-text {
        font-size: 0.7rem;
        color: var(--text-secondary);
        margin-top: 4px;
    }
    
    .badge-count {
        font-size: 0.7rem;
        font-weight: 400;
        color: var(--text-secondary);
        margin-left: 8px;
    }
    
    /* Responsive */
    @media (max-width: 640px) {
        .form-card {
            padding: 18px 16px;
        }
        .form-header {
            flex-direction: column;
            text-align: center;
        }
        .form-header-icon {
            width: 48px;
            height: 48px;
            font-size: 1.2rem;
        }
        .btn {
            padding: 8px 16px;
            font-size: 0.8rem;
            min-height: 38px;
            min-width: 100%;
        }
        .form-actions {
            flex-direction: column;
        }
        .form-actions .btn {
            width: 100%;
            justify-content: center;
        }
        .checkbox-group {
            grid-template-columns: 1fr;
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
                <i class="fas fa-user-edit mr-2" style="color: var(--blue-600);"></i> Edit Employee
            </h1>
            <p class="page-subtitle">
                Update employee information, roles and departments
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-user mr-1"></i> <?= htmlspecialchars($employee['full_name']) ?>
                </span>
            </p>
        </div>
        <div>
            <a href="employees.php?branch=<?= $selected_branch_id ?>" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back
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
    <!-- FORM -->
    <!-- ================================================================ -->
    <div class="form-card">
        <!-- Form Header -->
        <div class="form-header">
            <div class="form-header-icon">
                <i class="fas fa-user-edit"></i>
            </div>
            <div>
                <h3>Edit Employee Information</h3>
                <p>Update employee details, roles and department assignments</p>
            </div>
        </div>
        
        <form method="POST" action="" id="editEmployeeForm">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                
                <!-- ================================================================ -->
                <!-- Personal Information -->
                <!-- ================================================================ -->
                <div class="md:col-span-2">
                    <h3 class="section-title">
                        <i class="fas fa-user-circle"></i> Personal Information
                    </h3>
                    <hr class="section-divider">
                </div>
                
                <!-- Full Name -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-user text-blue-600"></i> Full Name
                        <span class="required">*</span>
                    </label>
                    <div class="form-row-icon">
                        <input type="text" name="full_name" class="form-control" 
                               placeholder="Enter full name" 
                               value="<?= htmlspecialchars($form_data['full_name']) ?>" required>
                        <span class="input-icon"><i class="fas fa-user"></i></span>
                    </div>
                </div>
                
                <!-- Username -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-at text-blue-600"></i> Username
                        <span class="required">*</span>
                    </label>
                    <div class="form-row-icon">
                        <input type="text" name="username" class="form-control" 
                               placeholder="Enter username" 
                               value="<?= htmlspecialchars($form_data['username']) ?>" required>
                        <span class="input-icon"><i class="fas fa-at"></i></span>
                    </div>
                    <p class="help-text">Username can be changed. Must be unique.</p>
                </div>
                
                <!-- Email -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-envelope text-green-600"></i> Email
                        <span class="required">*</span>
                    </label>
                    <div class="form-row-icon">
                        <input type="email" name="email" class="form-control" 
                               placeholder="Enter email" 
                               value="<?= htmlspecialchars($form_data['email']) ?>" required>
                        <span class="input-icon"><i class="fas fa-envelope"></i></span>
                    </div>
                </div>
                
                <!-- Phone -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-phone text-blue-600"></i> Phone Number
                    </label>
                    <div class="form-row-icon">
                        <input type="text" name="phone" class="form-control" 
                               placeholder="Enter phone number" 
                               value="<?= htmlspecialchars($form_data['phone']) ?>">
                        <span class="input-icon"><i class="fas fa-phone"></i></span>
                    </div>
                </div>
                
                <!-- Branch -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-store-alt text-green-600"></i> Branch
                        <span class="required">*</span>
                    </label>
                    <div class="form-row-icon">
                        <select name="branch_id" class="form-control" required>
                            <?php foreach ($branches_list as $branch): ?>
                                <option value="<?= $branch['id'] ?>" <?= $branch['id'] == $form_data['branch_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($branch['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="input-icon"><i class="fas fa-store-alt"></i></span>
                    </div>
                </div>
                
                <!-- Status -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-circle text-blue-600"></i> Status
                    </label>
                    <div class="form-row-icon">
                        <select name="status" class="form-control">
                            <option value="active" <?= $form_data['status'] === 'active' ? 'selected' : '' ?>>✅ Active</option>
                            <option value="inactive" <?= $form_data['status'] === 'inactive' ? 'selected' : '' ?>>⛔ Inactive</option>
                        </select>
                        <span class="input-icon"><i class="fas fa-toggle-on"></i></span>
                    </div>
                </div>
                
                <!-- ================================================================ -->
                <!-- Roles Selection - MULTIPLE ROLES -->
                <!-- ================================================================ -->
                <div class="md:col-span-2 mt-2">
                    <h3 class="section-title">
                        <i class="fas fa-user-tag"></i> Select Roles
                        <span class="required">*</span>
                        <span class="badge-count">(<?= count($roles) ?> available)</span>
                    </h3>
                    <p class="help-text mb-2">Click on a role to select/deselect it. At least one role is required.</p>
                    <hr class="section-divider">
                    
                    <div class="checkbox-group" id="rolesContainer">
                        <?php if (!empty($roles)): ?>
                            <?php foreach ($roles as $role): ?>
                                <?php $checked = in_array($role['id'], $form_data['selected_roles']) ? 'checked' : ''; ?>
                                <div class="checkbox-item <?= $checked ? 'checked' : '' ?>" onclick="toggleCheckbox(this)">
                                    <input type="checkbox" name="roles[]" value="<?= $role['id'] ?>" 
                                           id="role_<?= $role['id'] ?>"
                                           <?= $checked ?>>
                                    <label for="role_<?= $role['id'] ?>">
                                        <i class="fas fa-circle text-[6px] text-blue-600 mr-1"></i>
                                        <?= htmlspecialchars($role['name']) ?>
                                        <?php if (!empty($role['description'])): ?>
                                            <span class="role-desc"><?= htmlspecialchars($role['description']) ?></span>
                                        <?php endif; ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-gray-400 text-sm col-span-full text-center">No roles available. Please add roles first.</p>
                        <?php endif; ?>
                    </div>
                    <p class="help-text mt-2" id="roleCount">Selected: <strong id="selectedRoleCount"><?= count($form_data['selected_roles']) ?></strong> roles</p>
                </div>
                
                <!-- ================================================================ -->
                <!-- Departments Selection - MULTIPLE DEPARTMENTS -->
                <!-- ================================================================ -->
                <div class="md:col-span-2 mt-2">
                    <h3 class="section-title">
                        <i class="fas fa-building"></i> Select Departments
                        <span class="badge-count">(<?= count($departments) ?> available)</span>
                    </h3>
                    <p class="help-text mb-2">Click on a department to select/deselect it.</p>
                    <hr class="section-divider">
                    
                    <div class="checkbox-group" id="departmentsContainer">
                        <?php if (!empty($departments)): ?>
                            <?php foreach ($departments as $dept): ?>
                                <?php $checked = in_array($dept['id'], $form_data['selected_departments']) ? 'checked' : ''; ?>
                                <div class="checkbox-item <?= $checked ? 'checked' : '' ?>" onclick="toggleCheckbox(this)">
                                    <input type="checkbox" name="departments[]" value="<?= $dept['id'] ?>" 
                                           id="dept_<?= $dept['id'] ?>"
                                           <?= $checked ?>>
                                    <label for="dept_<?= $dept['id'] ?>">
                                        <i class="fas fa-circle text-[6px] text-green-600 mr-1"></i>
                                        <?= htmlspecialchars($dept['name']) ?>
                                        <?php if (!empty($dept['description'])): ?>
                                            <span class="role-desc"><?= htmlspecialchars($dept['description']) ?></span>
                                        <?php endif; ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-gray-400 text-sm col-span-full text-center">No departments available. Please add departments first.</p>
                        <?php endif; ?>
                    </div>
                    <p class="help-text mt-2">Selected: <strong id="selectedDeptCount"><?= count($form_data['selected_departments']) ?></strong> departments</p>
                </div>
                
            </div>
            
            <!-- Form Actions -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Employee
                </button>
                <a href="employees.php?branch=<?= $selected_branch_id ?>" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="reset" class="btn btn-outline">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </div>
        </form>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Edit Employee
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
    // TOGGLE CHECKBOX
    // ================================================================
    function toggleCheckbox(element) {
        var checkbox = element.querySelector('input[type="checkbox"]');
        if (checkbox) {
            checkbox.checked = !checkbox.checked;
            if (checkbox.checked) {
                element.classList.add('checked');
            } else {
                element.classList.remove('checked');
            }
            var event = new Event('change', { bubbles: true });
            checkbox.dispatchEvent(event);
            updateCounts();
        }
    }

    // ================================================================
    // UPDATE CHECKBOX COUNTS
    // ================================================================
    function updateCounts() {
        var rolesChecked = document.querySelectorAll('input[name="roles[]"]:checked');
        var roleCount = document.getElementById('selectedRoleCount');
        if (roleCount) roleCount.textContent = rolesChecked.length;
        
        var deptsChecked = document.querySelectorAll('input[name="departments[]"]:checked');
        var deptCount = document.getElementById('selectedDeptCount');
        if (deptCount) deptCount.textContent = deptsChecked.length;
    }

    // ================================================================
    // UPDATE CHECKBOX STYLES ON LOAD
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        var checkboxes = document.querySelectorAll('.checkbox-item input[type="checkbox"]');
        checkboxes.forEach(function(checkbox) {
            if (checkbox.checked) {
                checkbox.closest('.checkbox-item').classList.add('checked');
            }
        });
        updateCounts();
    });

    // ================================================================
    // VALIDATION - Ensure at least one role is selected
    // ================================================================
    document.getElementById('editEmployeeForm')?.addEventListener('submit', function(e) {
        var rolesChecked = document.querySelectorAll('input[name="roles[]"]:checked');
        if (rolesChecked.length === 0) {
            e.preventDefault();
            alert('⚠️ Please select at least one role for this employee.');
            document.getElementById('rolesContainer').style.borderColor = '#EF4444';
            setTimeout(function() {
                document.getElementById('rolesContainer').style.borderColor = '#E2E8F0';
            }, 3000);
            return false;
        }
        return true;
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

    console.log('%c👤 Braick - Edit Employee', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Employee: <?= htmlspecialchars($employee['full_name']) ?>', 'font-size:13px; color:#059669;');
    console.log('%c✅ Multiple Roles: <?= count($form_data['selected_roles']) ?> selected', 'font-size:13px; color:#64748B;');
    console.log('%c✅ Multiple Departments: <?= count($form_data['selected_departments']) ?> selected', 'font-size:13px; color:#64748B;');
    console.log('%c🔗 Shared Header & Sidebar: ACTIVE', 'font-size:13px; color:#64748B;');
    console.log('%c🌙 Dark Mode: ' + (localStorage.getItem('darkMode') === 'true' ? 'ON' : 'OFF'), 'font-size:13px; color:#64748B;');
</script>

</body>
</html>