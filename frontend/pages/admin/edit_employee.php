<?php
// ================================================================
// FILE: frontend/pages/admin/edit_employee.php
// SUPER ADMIN - EDIT EMPLOYEE (WITH ROLES & DEPARTMENTS & PASSWORD)
// WITH GENERATE BUTTON - CLICK TO GENERATE PASSWORD
// BRAICK DISPENSARY
// WITH SHARED HEADER & SIDEBAR
// FIXED: Password update now works properly for login
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
// CHECK IF USER IS ADMIN
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
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// GET EMPLOYEE ID
// ================================================================
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
// GENERATE PASSWORD FUNCTION
// ================================================================
function generatePassword($full_name, $branch_id, $user_id) {
    // Get first 4 letters of full name
    $clean_name = preg_replace('/[^a-zA-Z]/', '', $full_name);
    $name_part = strtoupper(substr($clean_name, 0, 4));
    if (strlen($name_part) < 3) {
        $name_part = 'USER';
    }
    
    // Get branch code (BR + 2-digit branch ID)
    $branch_code = 'BR' . str_pad($branch_id, 2, '0', STR_PAD_LEFT);
    
    // Get user ID part (UID + 4-digit user ID)
    $user_code = 'UID' . str_pad($user_id, 4, '0', STR_PAD_LEFT);
    
    // Combine: NAME + BRANCH + USER_ID
    $password = $name_part . $branch_code . $user_code;
    
    // If password is too short, add random numbers
    if (strlen($password) < 8) {
        $password .= rand(100, 999);
    }
    
    return $password;
}

// ================================================================
// HANDLE AJAX REQUEST FOR GENERATE PASSWORD
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_password') {
    header('Content-Type: application/json');
    
    $full_name = $_POST['full_name'] ?? '';
    $branch_id = (int)($_POST['branch_id'] ?? 0);
    $user_id = (int)($_POST['user_id'] ?? 0);
    
    if (empty($full_name) || $branch_id <= 0) {
        echo json_encode(['success' => false, 'password' => '', 'error' => 'Name and branch required']);
        exit;
    }
    
    if ($user_id <= 0) {
        $user_id = (int)$_POST['current_user_id'] ?? 0;
    }
    
    $password = generatePassword($full_name, $branch_id, $user_id);
    
    echo json_encode(['success' => true, 'password' => $password, 'user_id' => $user_id]);
    exit;
}

// ================================================================
// HANDLE FORM SUBMISSION
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
    'selected_departments' => $employee_departments,
    'password' => '',
    'password_changed' => false,
    'generated_password' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    // Get form data
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $branch_id = (int)($_POST['branch_id'] ?? 0);
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $generated_password = $_POST['generated_password'] ?? '';
    $password_changed = false;
    
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
    
    // ================================================================
    // PASSWORD HANDLING - Check if password was generated or entered manually
    // ================================================================
    if (!empty($generated_password)) {
        // Password was generated by the generate button
        $password = $generated_password;
        $confirm_password = $generated_password;
        $password_changed = true;
    } else if (!empty($password)) {
        // Manual password entry
        if (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters long';
        }
        if ($password !== $confirm_password) {
            $errors[] = 'Passwords do not match';
        }
        $password_changed = true;
    } else {
        // No password change - keep existing
        $password = null;
        $password_changed = false;
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
        try {
            $db->beginTransaction();
            
            // Build update query
            $sql = "UPDATE users SET full_name = ?, username = ?, email = ?, phone = ?, role = ?, branch_id = ?, status = ?";
            $params = [$full_name, $username, $email, $phone, $primary_role, $branch_id, $status];
            
            // Add password if changed
            if ($password_changed && $password !== null) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql .= ", password = ?";
                $params[] = $hashed_password;
            }
            
            $sql .= " WHERE id = ? AND role != 'admin'";
            $params[] = $employee_id;
            
            $stmt = $db->prepare($sql);
            
            if ($stmt->execute($params)) {
                
                // ================================================================
                // UPDATE ROLES
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
                // UPDATE DEPARTMENTS
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
                    $stmt = $db->prepare("INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) VALUES (?, ?, 'employee_updated', ?, NOW())");
                    $details = "Employee {$full_name} updated with " . count($selected_roles) . " roles and " . count($selected_departments) . " departments";
                    if ($password_changed) {
                        $details .= " - Password updated";
                    }
                    $stmt->execute([$_SESSION['user_id'], $branch_id, $details]);
                } catch (Exception $e) {}
                
                $db->commit();
                
                $message = "Employee updated successfully with " . count($selected_roles) . " role(s) and " . count($selected_departments) . " department(s)!";
                if ($password_changed && $password !== null) {
                    $message .= "<br>🔑 New Password: <strong>" . htmlspecialchars($password) . "</strong>";
                    $message .= "<br>📋 Please copy this password and share with the employee.";
                }
                $message_type = 'success';
                
                // Refresh employee data
                $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND role != 'admin'");
                $stmt->execute([$employee_id]);
                $employee = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Update form data
                $form_data['full_name'] = $employee['full_name'];
                $form_data['username'] = $employee['username'];
                $form_data['email'] = $employee['email'];
                $form_data['phone'] = $employee['phone'] ?? '';
                $form_data['branch_id'] = $employee['branch_id'];
                $form_data['status'] = $employee['status'] ?? 'active';
                $form_data['password_changed'] = $password_changed;
                
                // Redirect after success with flag
                header('Location: employees.php?branch=' . $branch_id . '&updated=1');
                exit;
            } else {
                $errors[] = 'Failed to update employee. Please try again.';
            }
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Error: ' . $e->getMessage();
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
            'selected_departments' => $selected_departments,
            'password' => $password ?? '',
            'password_changed' => $password_changed,
            'generated_password' => $generated_password
        ];
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
       ADDITIONAL FORM STYLES
       ================================================================ */
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
    
    .form-control {
        width: 100%;
        padding: 10px 16px;
        border: 2px solid var(--border-color);
        border-radius: 12px;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        outline: none;
        background: #FFFFFF !important;
        color: #1E293B !important;
        font-family: 'Inter', 'Segoe UI', sans-serif;
    }
    
    [data-theme="dark"] .form-control {
        background: #1E293B !important;
        color: #F1F5F9 !important;
        border-color: #334155 !important;
    }
    
    .form-control:focus {
        border-color: #0B5ED7;
        box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.12);
    }
    
    .form-control::placeholder {
        color: var(--text-secondary);
        opacity: 0.5;
    }
    
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
    
    /* Password Section */
    .password-section {
        background: var(--primary-bg);
        border-radius: var(--radius);
        padding: 16px 18px;
        border: 2px solid var(--primary-light);
        margin-top: 8px;
    }
    
    [data-theme="dark"] .password-section {
        background: #1E3A5F;
        border-color: var(--primary);
    }
    
    .password-section .password-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .password-section .password-header .password-title {
        font-weight: 600;
        color: var(--text-primary);
        font-size: 0.85rem;
    }
    
    .password-section .password-header .password-title i {
        color: var(--primary);
        margin-right: 6px;
    }
    
    .password-section .password-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }
    
    .password-section .password-row .form-group {
        position: relative;
    }
    
    .password-section .password-row .form-group .password-toggle {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: var(--text-secondary);
        cursor: pointer;
        padding: 4px;
        font-size: 0.9rem;
        transition: var(--transition);
    }
    
    .password-section .password-row .form-group .password-toggle:hover {
        color: var(--primary);
    }
    
    /* GENERATE BUTTON */
    .generate-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 8px 18px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.8rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        background: linear-gradient(135deg, #059669, #047857);
        color: white;
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
        min-height: 38px;
        min-width: 100px;
    }
    
    .generate-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(5, 150, 105, 0.4);
        background: linear-gradient(135deg, #047857, #065F46);
    }
    
    .generate-btn:active {
        transform: scale(0.95);
    }
    
    .generate-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }
    
    .generate-btn .spinner {
        display: none;
        animation: spin 1s linear infinite;
    }
    
    .generate-btn.loading .spinner {
        display: inline-block;
    }
    
    .generate-btn.loading .btn-text {
        display: none;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    
    .generated-password-box {
        background: #1E293B;
        color: #34D399;
        padding: 10px 16px;
        border-radius: 8px;
        font-family: 'Courier New', monospace;
        font-size: 0.95rem;
        font-weight: 600;
        letter-spacing: 0.5px;
        display: none;
        margin-top: 10px;
        border: 1px solid #334155;
        word-break: break-all;
        position: relative;
    }
    
    [data-theme="dark"] .generated-password-box {
        background: #0F172A;
        border-color: #1E293B;
    }
    
    .generated-password-box .copy-btn {
        background: rgba(255,255,255,0.1);
        border: none;
        color: #94A3B8;
        padding: 3px 12px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 0.65rem;
        transition: var(--transition);
        margin-left: 10px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .generated-password-box .copy-btn:hover {
        background: rgba(255,255,255,0.2);
        color: white;
    }
    
    .generated-password-box .regenerate-hint {
        font-size: 0.6rem;
        color: #94A3B8;
        margin-top: 4px;
        font-family: 'Inter', sans-serif;
        font-weight: 400;
    }
    
    .password-info-text {
        font-size: 0.7rem;
        color: var(--text-secondary);
        margin-top: 8px;
        padding: 8px 12px;
        background: var(--bg-body);
        border-radius: 8px;
        border: 1px solid var(--border-color);
    }
    
    [data-theme="dark"] .password-info-text {
        background: #0F172A;
        border-color: #1E293B;
    }
    
    .password-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.65rem;
        font-weight: 500;
        padding: 2px 10px;
        border-radius: 12px;
        background: #ECFDF5;
        color: #059669;
        border: 1px solid #6EE7B7;
    }
    
    [data-theme="dark"] .password-badge {
        background: #1A3A2A;
        color: #34D399;
        border-color: #065F46;
    }
    
    .password-badge.generated {
        background: #EFF6FF;
        color: #0B5ED7;
        border-color: #93C5FD;
    }
    
    [data-theme="dark"] .password-badge.generated {
        background: #1E3A5F;
        color: #6EA8FE;
        border-color: #1E3A5F;
    }
    
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
        background: #FFFFFF !important;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        cursor: pointer;
    }
    
    [data-theme="dark"] .checkbox-item {
        background: #1E293B !important;
    }
    
    .checkbox-item:hover {
        border-color: #0B5ED7;
        background: #E8F0FE !important;
        transform: translateY(-1px);
    }
    
    [data-theme="dark"] .checkbox-item:hover {
        background: #1E3A5F !important;
    }
    
    .checkbox-item.checked {
        border-color: #0B5ED7;
        background: #E8F0FE !important;
    }
    
    [data-theme="dark"] .checkbox-item.checked {
        background: #1E3A5F !important;
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
    
    .form-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        padding-top: 24px;
        margin-top: 24px;
        border-top: 2px solid var(--border-color);
    }
    
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
    
    .password-changed-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #D1FAE5;
        color: #065F46;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        border: 1px solid #6EE7B7;
    }
    
    [data-theme="dark"] .password-changed-badge {
        background: #1A3A2A;
        color: #34D399;
        border-color: #065F46;
    }
    
    .password-action-row {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 4px;
    }
    
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
        .password-section .password-row {
            grid-template-columns: 1fr;
        }
        .password-section .password-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .password-action-row {
            flex-direction: column;
            width: 100%;
        }
        .password-action-row .generate-btn {
            width: 100%;
            justify-content: center;
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
        
        <button id="darkModeToggle" class="dark-toggle-btn" title="Toggle Dark Mode">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot"></span>
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
                <i class="fas fa-user-edit mr-2" style="color: var(--blue-600);"></i> Edit Employee
            </h1>
            <p class="page-subtitle">
                Update employee information, roles, departments and password
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-user mr-1"></i> <?= htmlspecialchars($employee['full_name']) ?>
                </span>
                <?php if ($form_data['password_changed'] ?? false): ?>
                    <span class="ml-2 password-changed-badge">
                        <i class="fas fa-check-circle"></i> Password Updated
                    </span>
                <?php endif; ?>
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
        <div class="p-4 rounded-xl mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200 dark:bg-green-900/20 dark:text-green-300 dark:border-green-800' : 'bg-red-100 text-red-700 border border-red-200 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800' ?>">
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
                <p>Update employee details, roles, departments and password</p>
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
                        <input type="text" name="full_name" id="fullName" class="form-control" 
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
                        <input type="text" name="username" id="username" class="form-control" 
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
                        <select name="branch_id" id="branchSelect" class="form-control" required>
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
                <!-- Password Section - WITH GENERATE BUTTON -->
                <!-- ================================================================ -->
                <div class="md:col-span-2 mt-2">
                    <div class="password-section">
                        <div class="password-header">
                            <div class="password-title">
                                <i class="fas fa-key"></i> Password Settings
                                <span class="password-badge" id="passwordStatusBadge">
                                    <i class="fas fa-check-circle"></i> 
                                    <?= $employee['password'] ? 'Current password set' : 'No password set' ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="password-row" id="passwordFields">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-lock label-icon"></i> New Password
                                    <span class="label-badge" id="passwordFieldLabel">Leave empty to keep current</span>
                                </label>
                                <input type="password" name="password" id="newPassword" class="form-control" 
                                       placeholder="Enter new password...">
                                <button type="button" class="password-toggle" onclick="togglePasswordVisibility('newPassword', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-lock label-icon"></i> Confirm Password
                                </label>
                                <input type="password" name="confirm_password" id="confirmPassword" class="form-control" 
                                       placeholder="Confirm new password...">
                                <button type="button" class="password-toggle" onclick="togglePasswordVisibility('confirmPassword', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Generate Button & Generated Password -->
                        <div class="password-action-row">
                            <button type="button" class="generate-btn" id="generateBtn" onclick="generateAndFillPassword()">
                                <span class="spinner"><i class="fas fa-circle-notch"></i></span>
                                <span class="btn-text"><i class="fas fa-magic"></i> Generate Password</span>
                            </button>
                            
                            <span class="password-badge generated" id="generatedBadge" style="display:none;">
                                <i class="fas fa-check"></i> Generated
                            </span>
                        </div>
                        
                        <!-- Generated Password Display -->
                        <div class="generated-password-box" id="generatedPasswordBox">
                            <span id="generatedPasswordDisplay">****************</span>
                            <button type="button" class="copy-btn" onclick="copyGeneratedPassword()">
                                <i class="fas fa-copy"></i> Copy
                            </button>
                            <div class="regenerate-hint">
                                <i class="fas fa-sync-alt"></i> Click "Generate Password" again to regenerate
                            </div>
                        </div>
                        
                        <!-- Password Info -->
                        <div class="password-info-text">
                            <i class="fas fa-info-circle text-blue-600 mr-1"></i>
                            <?php if ($employee['password']): ?>
                                Current password is set. Leave password fields empty to keep current password.
                            <?php else: ?>
                                No password set. Please generate or enter a password for this employee.
                            <?php endif; ?>
                            <br>
                            <i class="fas fa-lightbulb text-yellow-600 mr-1"></i>
                            <span class="text-xs">Click <strong>"Generate Password"</strong> to auto-generate a secure password. You can also type manually.</span>
                        </div>
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
                
                <!-- Hidden field for generated password -->
                <input type="hidden" name="generated_password" id="generatedPasswordHidden" value="">
                
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
        
        // Check if password is required (no current password)
        var newPassword = document.getElementById('newPassword');
        var generatedPassword = document.getElementById('generatedPasswordHidden');
        var hasCurrentPassword = <?= $employee['password'] ? 'true' : 'false' ?>;
        
        if (!hasCurrentPassword && newPassword.value.trim() === '' && generatedPassword.value === '') {
            e.preventDefault();
            alert('⚠️ This employee has no password set. Please generate or enter a password.');
            document.getElementById('newPassword').focus();
            document.getElementById('newPassword').style.borderColor = '#EF4444';
            setTimeout(function() {
                document.getElementById('newPassword').style.borderColor = '';
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

    // ================================================================
    // GENERATE PASSWORD - CLICK BUTTON TO GENERATE
    // ================================================================
    function generateAndFillPassword() {
        var generateBtn = document.getElementById('generateBtn');
        var fullName = document.getElementById('fullName').value.trim();
        var branchId = document.getElementById('branchSelect').value;
        var newPassword = document.getElementById('newPassword');
        var confirmPassword = document.getElementById('confirmPassword');
        var passwordBox = document.getElementById('generatedPasswordBox');
        var passwordDisplay = document.getElementById('generatedPasswordDisplay');
        var passwordHidden = document.getElementById('generatedPasswordHidden');
        var generatedBadge = document.getElementById('generatedBadge');
        var passwordFieldLabel = document.getElementById('passwordFieldLabel');
        
        // Validate inputs
        if (!fullName) {
            showToast('⚠️ Warning', 'Please enter the full name first', 'warning');
            document.getElementById('fullName').focus();
            return;
        }
        
        if (!branchId || branchId === '') {
            showToast('⚠️ Warning', 'Please select a branch first', 'warning');
            document.getElementById('branchSelect').focus();
            return;
        }
        
        // Show loading state
        generateBtn.classList.add('loading');
        generateBtn.disabled = true;
        passwordDisplay.textContent = 'Generating...';
        passwordBox.style.display = 'block';
        
        // AJAX request to generate password
        var formData = new FormData();
        formData.append('action', 'generate_password');
        formData.append('full_name', fullName);
        formData.append('branch_id', branchId);
        formData.append('user_id', <?= $employee_id ?>);
        formData.append('current_user_id', <?= $employee_id ?>);
        formData.append('username', document.getElementById('username').value.trim());
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            // Remove loading state
            generateBtn.classList.remove('loading');
            generateBtn.disabled = false;
            
            if (data.success) {
                var password = data.password;
                passwordDisplay.textContent = password;
                newPassword.value = password;
                confirmPassword.value = password;
                passwordHidden.value = password;
                generatedBadge.style.display = 'inline-flex';
                passwordFieldLabel.textContent = 'Generated password';
                passwordFieldLabel.style.color = '#059669';
                
                // Auto copy to clipboard
                copyToClipboard(password);
                showToast('🔑 Password Generated', 'Password generated and copied to clipboard!', 'success');
                
                // Highlight the password box
                passwordBox.style.borderColor = '#34D399';
                setTimeout(function() {
                    passwordBox.style.borderColor = '#334155';
                }, 2000);
            } else {
                showToast('❌ Error', data.error || 'Failed to generate password', 'error');
                passwordBox.style.display = 'none';
                passwordHidden.value = '';
                generatedBadge.style.display = 'none';
                passwordFieldLabel.textContent = 'Leave empty to keep current';
                passwordFieldLabel.style.color = '';
            }
        })
        .catch(function(error) {
            // Remove loading state
            generateBtn.classList.remove('loading');
            generateBtn.disabled = false;
            
            showToast('❌ Error', 'Network error: ' + error.message, 'error');
            passwordBox.style.display = 'none';
            passwordHidden.value = '';
            generatedBadge.style.display = 'none';
            passwordFieldLabel.textContent = 'Leave empty to keep current';
            passwordFieldLabel.style.color = '';
        });
    }

    // ================================================================
    // COPY TO CLIPBOARD
    // ================================================================
    function copyToClipboard(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).catch(function() {
                fallbackCopy(text);
            });
        } else {
            fallbackCopy(text);
        }
    }

    function fallbackCopy(text) {
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
        } catch (e) {}
        document.body.removeChild(textarea);
    }

    // ================================================================
    // COPY GENERATED PASSWORD
    // ================================================================
    function copyGeneratedPassword() {
        var passwordDisplay = document.getElementById('generatedPasswordDisplay');
        var password = passwordDisplay.textContent;
        if (password && password !== '****************' && password !== 'Generating...') {
            copyToClipboard(password);
            showToast('✅ Copied', 'Password copied to clipboard!', 'success');
        }
    }

    // ================================================================
    // TOGGLE PASSWORD VISIBILITY
    // ================================================================
    function togglePasswordVisibility(inputId, button) {
        var input = document.getElementById(inputId);
        if (input.type === 'password') {
            input.type = 'text';
            button.innerHTML = '<i class="fas fa-eye-slash"></i>';
        } else {
            input.type = 'password';
            button.innerHTML = '<i class="fas fa-eye"></i>';
        }
    }

    // ================================================================
    // CLEAR GENERATED PASSWORD WHEN MANUAL TYPING STARTS
    // ================================================================
    document.getElementById('newPassword')?.addEventListener('input', function() {
        if (this.value.trim() !== '') {
            // User is typing manually, clear generated password
            document.getElementById('generatedPasswordHidden').value = '';
            document.getElementById('generatedBadge').style.display = 'none';
            document.getElementById('passwordFieldLabel').textContent = 'Manual entry';
            document.getElementById('passwordFieldLabel').style.color = '#0B5ED7';
        } else {
            document.getElementById('passwordFieldLabel').textContent = 'Leave empty to keep current';
            document.getElementById('passwordFieldLabel').style.color = '';
        }
    });

    console.log('%c👤 Braick - Edit Employee', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📋 Employee: <?= htmlspecialchars($employee['full_name']) ?>', 'font-size:13px; color:#059669;');
    console.log('%c🔑 Click "Generate Password" to generate a secure password', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📋 Password format: NAME + BRCODE + UID + USER_ID', 'font-size:13px; color:#34D399;');
    console.log('%c🔐 Password is properly hashed using password_hash()', 'font-size:13px; color:#F59E0B;');
</script>

</body>
</html>