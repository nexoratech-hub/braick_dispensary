<?php
// ================================================================
// FILE: frontend/pages/admin/add_employee.php
// SUPER ADMIN - ADD EMPLOYEE
// BRAICK DISPENSARY
// WITH SHARED HEADER & SIDEBAR
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
$branches = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $branches[] = $row;
}

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
// HANDLE FORM SUBMISSION
// ================================================================
$message = '';
$message_type = '';
$errors = [];
$form_data = [
    'full_name' => '',
    'username' => '',
    'email' => '',
    'phone' => '',
    'password' => 'password123',
    'branch_id' => $selected_branch_id,
    'selected_roles' => [],
    'selected_departments' => []
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $form_data['full_name'] = trim($_POST['full_name'] ?? '');
    $form_data['username'] = trim($_POST['username'] ?? '');
    $form_data['email'] = trim($_POST['email'] ?? '');
    $form_data['phone'] = trim($_POST['phone'] ?? '');
    $form_data['password'] = $_POST['password'] ?? 'password123';
    $form_data['branch_id'] = (int)($_POST['branch_id'] ?? 0);
    
    // Get selected roles from checkboxes
    $form_data['selected_roles'] = $_POST['roles'] ?? [];
    $form_data['selected_departments'] = $_POST['departments'] ?? [];
    
    // Get primary role (first selected role)
    $primary_role = !empty($form_data['selected_roles']) ? $form_data['selected_roles'][0] : '';
    
    // Validation
    if (empty($form_data['full_name'])) {
        $errors[] = 'Full name is required';
    }
    if (empty($form_data['username'])) {
        $errors[] = 'Username is required';
    }
    if (empty($form_data['email'])) {
        $errors[] = 'Email is required';
    }
    if (empty($form_data['selected_roles'])) {
        $errors[] = 'At least one role must be selected';
    }
    if ($form_data['branch_id'] <= 0) {
        $errors[] = 'Branch is required';
    }
    
    // Check if username exists
    if (empty($errors)) {
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$form_data['username']]);
        if ($stmt->fetch()) {
            $errors[] = 'Username already exists';
        }
    }
    
    // Check if email exists
    if (empty($errors)) {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$form_data['email']]);
        if ($stmt->fetch()) {
            $errors[] = 'Email already exists';
        }
    }
    
    // Save employee
    if (empty($errors)) {
        $hashed_password = password_hash($form_data['password'], PASSWORD_DEFAULT);
        
        $stmt = $db->prepare("
            INSERT INTO users (username, password, full_name, email, phone, role, branch_id, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())
        ");
        
        if ($stmt->execute([$form_data['username'], $hashed_password, $form_data['full_name'], $form_data['email'], $form_data['phone'], $primary_role, $form_data['branch_id']])) {
            $user_id = $db->lastInsertId();
            
            // Assign all selected roles
            if (!empty($form_data['selected_roles'])) {
                foreach ($form_data['selected_roles'] as $role_id) {
                    try {
                        $stmt = $db->prepare("INSERT INTO employee_roles (user_id, role_id, assigned_by) VALUES (?, ?, ?)");
                        $stmt->execute([$user_id, $role_id, $_SESSION['user_id']]);
                    } catch (Exception $e) {}
                }
            }
            
            // Assign selected departments
            if (!empty($form_data['selected_departments'])) {
                foreach ($form_data['selected_departments'] as $dept_id) {
                    try {
                        $stmt = $db->prepare("INSERT INTO employee_departments (user_id, department_id, assigned_by) VALUES (?, ?, ?)");
                        $stmt->execute([$user_id, $dept_id, $_SESSION['user_id']]);
                    } catch (Exception $e) {}
                }
            }
            
            // Log activity
            try {
                $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'employee_added', ?)");
                $stmt->execute([$_SESSION['user_id'], "Employee {$form_data['full_name']} added with " . count($form_data['selected_roles']) . " roles"]);
            } catch (Exception $e) {}
            
            $message = "Employee added successfully with " . count($form_data['selected_roles']) . " role(s)!";
            $message_type = 'success';
            
            // Redirect to employees list
            echo '<script>setTimeout(function(){ window.location.href = "employees.php?branch=' . $form_data['branch_id'] . '&success=1"; }, 1500);</script>';
        } else {
            $errors[] = 'Failed to add employee. Please try again.';
        }
    }
    
    if (!empty($errors)) {
        $message = implode('<br>', $errors);
        $message_type = 'error';
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
    
    /* Tips Cards */
    .tip-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 16px 20px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 14px;
    }
    
    .tip-card:hover {
        border-color: #0B5ED7;
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.06);
    }
    
    .tip-card .tip-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0;
    }
    
    .tip-card .tip-icon.blue { 
        background: #E8F0FE; 
        color: #0B5ED7; 
    }
    .tip-card .tip-icon.green { 
        background: #E6F7EE; 
        color: #059669; 
    }
    .tip-card .tip-icon.yellow { 
        background: #FEF3C7; 
        color: #F59E0B; 
    }
    
    .tip-card .tip-text h4 {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }
    
    .tip-card .tip-text p {
        font-size: 0.75rem;
        color: var(--text-secondary);
        margin: 0;
    }
    
    /* Dark Mode Support */
    [data-theme="dark"] .tip-card .tip-icon.blue { 
        background: #1E3A5F; 
        color: #6EA8FE; 
    }
    [data-theme="dark"] .tip-card .tip-icon.green { 
        background: #1A3A2A; 
        color: #34D399; 
    }
    [data-theme="dark"] .tip-card .tip-icon.yellow { 
        background: #3A2A1A; 
        color: #FBBF24; 
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
        .tip-card {
            padding: 12px 16px;
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
            <input type="text" id="searchInput" placeholder="Search patients, doctors, medicines...">
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
                <i class="fas fa-user-plus mr-2" style="color: var(--blue-600);"></i> Add New Employee
            </h1>
            <p class="page-subtitle">
                Create a new employee account with multiple roles and departments
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-users mr-1"></i> <?= $total_employees ?> employees
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
    <!-- FORM - BEAUTIFUL LIKE DASHBOARD -->
    <!-- ================================================================ -->
    <div class="form-card">
        <!-- Form Header -->
        <div class="form-header">
            <div class="form-header-icon">
                <i class="fas fa-user-plus"></i>
            </div>
            <div>
                <h3>Employee Information</h3>
                <p>Enter employee details and assign roles & departments</p>
            </div>
        </div>
        
        <form method="POST" action="" id="addEmployeeForm">
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
                
                <!-- Password -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-key text-yellow-600"></i> Password
                    </label>
                    <div class="form-row-icon">
                        <input type="text" name="password" class="form-control" 
                               placeholder="Default: password123" 
                               value="<?= htmlspecialchars($form_data['password']) ?>">
                        <span class="input-icon"><i class="fas fa-key"></i></span>
                    </div>
                    <p class="help-text">Default password: <strong>password123</strong></p>
                </div>
                
                <!-- Branch -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-store-alt text-green-600"></i> Branch
                        <span class="required">*</span>
                    </label>
                    <div class="form-row-icon">
                        <select name="branch_id" class="form-control" required>
                            <option value="">Select Branch</option>
                            <?php foreach ($branches as $branch): ?>
                                <option value="<?= $branch['id'] ?>" <?= $branch['id'] == $form_data['branch_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($branch['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="input-icon"><i class="fas fa-store-alt"></i></span>
                    </div>
                </div>
                
                <!-- ================================================================ -->
                <!-- Roles Selection -->
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
                                <div class="checkbox-item" onclick="toggleCheckbox(this)">
                                    <input type="checkbox" name="roles[]" value="<?= $role['id'] ?>" 
                                           id="role_<?= $role['id'] ?>"
                                           <?= in_array($role['id'], $form_data['selected_roles']) ? 'checked' : '' ?>>
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
                    <p class="help-text mt-2" id="roleCount">Selected: <strong id="selectedRoleCount">0</strong> roles</p>
                </div>
                
                <!-- ================================================================ -->
                <!-- Departments Selection -->
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
                                <div class="checkbox-item" onclick="toggleCheckbox(this)">
                                    <input type="checkbox" name="departments[]" value="<?= $dept['id'] ?>" 
                                           id="dept_<?= $dept['id'] ?>"
                                           <?= in_array($dept['id'], $form_data['selected_departments']) ? 'checked' : '' ?>>
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
                    <p class="help-text mt-2">Selected: <strong id="selectedDeptCount">0</strong> departments</p>
                </div>
                
            </div>
            
            <!-- Form Actions -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Employee
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

    <!-- ================================================================ -->
    <!-- QUICK TIPS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-5">
        <div class="tip-card">
            <div class="tip-icon blue">
                <i class="fas fa-lightbulb"></i>
            </div>
            <div class="tip-text">
                <h4>Tip #1</h4>
                <p>Select at least one role</p>
            </div>
        </div>
        <div class="tip-card">
            <div class="tip-icon green">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="tip-text">
                <h4>Tip #2</h4>
                <p>Multiple roles allowed</p>
            </div>
        </div>
        <div class="tip-card">
            <div class="tip-icon yellow">
                <i class="fas fa-info-circle"></i>
            </div>
            <div class="tip-text">
                <h4>Tip #3</h4>
                <p>Default password: password123</p>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Add Employee
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
    document.getElementById('addEmployeeForm')?.addEventListener('submit', function(e) {
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

    console.log('%c👤 Braick - Add Employee', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Shared Header & Sidebar: ACTIVE', 'font-size:13px; color:#059669;');
    console.log('%c✅ Roles & Departments will be saved', 'font-size:13px; color:#64748B;');
    console.log('%c🌙 Dark Mode: ' + (localStorage.getItem('darkMode') === 'true' ? 'ON' : 'OFF'), 'font-size:13px; color:#64748B;');
</script>

</body>
</html>