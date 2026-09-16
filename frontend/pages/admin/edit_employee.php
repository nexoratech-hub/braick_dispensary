<?php
// ================================================================
// FILE: frontend/pages/admin/edit_employee.php
// SUPER ADMIN - EDIT EMPLOYEE
// ✅ FIXED: "There is no active transaction" error
// ✅ FIXED: Role inaingia kwenye users.role column
// ✅ Uses SHARED header & sidebar
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        case 'audit': header('Location: ../audit/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// ================================================================
// ✅ HELPER: SAFE ROLLBACK (kuzuia "no active transaction" error)
// ================================================================
function safeRollback($db) {
    try {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
    } catch (Exception $e) {
        error_log("Safe rollback error: " . $e->getMessage());
    }
}

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
$stmt = $db->prepare("
    SELECT id, username, password, full_name, email, phone, role, branch_id, 
           specialty, status, created_at, updated_at, 
           password_changed_at, is_default_password 
    FROM users WHERE id = ?
");
$stmt->execute([$employee_id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    header('Location: employees.php?branch=' . $selected_branch_id);
    exit;
}

// ================================================================
// AUTO-FIX: Kama users.role ni EMPTY LAKINI employee_roles ina data
// ================================================================
if (empty($employee['role'])) {
    try {
        $stmt = $db->prepare("SELECT role_name FROM employee_roles WHERE user_id = ? ORDER BY id ASC LIMIT 1");
        $stmt->execute([$employee_id]);
        $first_role = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($first_role && !empty($first_role['role_name'])) {
            $stmt = $db->prepare("UPDATE users SET role = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$first_role['role_name'], $employee_id]);
            $employee['role'] = $first_role['role_name'];
        }
    } catch (Exception $e) {}
}

// ================================================================
// GET EMPLOYEE DEPARTMENTS
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
// GET BRANCHES
// ================================================================
$branches_list = [];
$stmt = $db->query("SELECT id, name, location FROM branches WHERE status = 'active' ORDER BY name");
$branches_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// AVAILABLE ROLES
// ================================================================
$available_roles = [
    ['name' => 'doctor',     'label' => 'Doctor',     'icon' => 'fa-user-md',        'color' => '#0B5ED7', 'desc' => 'Patient consultation & prescriptions'],
    ['name' => 'reception',  'label' => 'Reception',  'icon' => 'fa-user-tie',       'color' => '#059669', 'desc' => 'Patient registration & appointments'],
    ['name' => 'pharmacy',   'label' => 'Pharmacy',   'icon' => 'fa-pills',          'color' => '#D97706', 'desc' => 'Medicine dispensing & inventory'],
    ['name' => 'laboratory', 'label' => 'Laboratory', 'icon' => 'fa-microscope',     'color' => '#7C3AED', 'desc' => 'Lab tests & results'],
    ['name' => 'cashier',    'label' => 'Cashier',    'icon' => 'fa-cash-register',  'color' => '#0D9488', 'desc' => 'Payments & billing'],
    ['name' => 'audit',      'label' => 'Audit',      'icon' => 'fa-clipboard-check','color' => '#DC2626', 'desc' => 'System audit & compliance']
];

$valid_role_names = array_column($available_roles, 'name');

// ================================================================
// GET DEPARTMENTS
// ================================================================
$departments = [];
$stmt = $db->query("SELECT id, category_name, description, icon, color FROM service_categories WHERE is_active = 1 ORDER BY category_name");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $departments[] = $row;
}

$audit_exists = false;
foreach ($departments as $dept) {
    if (strtolower($dept['category_name']) === 'audit') {
        $audit_exists = true;
        break;
    }
}
if (!$audit_exists) {
    $departments[] = [
        'id' => 'audit_virtual',
        'category_name' => 'Audit',
        'description' => 'System audit & compliance',
        'icon' => 'fa-clipboard-check',
        'color' => '#DC2626'
    ];
}

// ================================================================
// GENERATE PASSWORD FUNCTION
// ================================================================
function generatePassword($full_name, $branch_id, $user_id = null) {
    $clean_name = preg_replace('/[^a-zA-Z]/', '', $full_name);
    $name_part = substr($clean_name, 0, 4);
    if (strlen($name_part) < 3) $name_part = 'User';
    $name_part = ucfirst(strtolower($name_part));
    $branch_code = 'BR' . str_pad($branch_id, 2, '0', STR_PAD_LEFT);
    if ($user_id && $user_id > 0) {
        $user_code = 'U' . str_pad($user_id, 2, '0', STR_PAD_LEFT);
    } else {
        $user_code = 'U' . rand(10, 99);
    }
    $password = $name_part . $branch_code . $user_code;
    if (strlen($password) < 8) $password .= rand(100, 999);
    return $password;
}

// ================================================================
// HANDLE AJAX - GENERATE PASSWORD
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
    if ($user_id <= 0) $user_id = (int)($_POST['current_user_id'] ?? 0);
    
    $password = generatePassword($full_name, $branch_id, $user_id);
    echo json_encode(['success' => true, 'password' => $password, 'user_id' => $user_id]);
    exit;
}

// ================================================================
// ✅ HANDLE FORM SUBMISSION - FIXED
// ================================================================
$message = '';
$message_type = '';
$errors = [];

$form_data = [
    'full_name' => $employee['full_name'],
    'username' => $employee['username'],
    'email' => $employee['email'],
    'phone' => $employee['phone'] ?? '',
    'branch_id' => $employee['branch_id'],
    'selected_role' => $employee['role'] ?? '',
    'selected_departments' => $employee_departments,
    'specialty' => $employee['specialty'] ?? '',
    'status' => $employee['status'] ?? 'active',
    'password' => '',
    'password_changed' => false
];

$is_form_submission = ($_SERVER['REQUEST_METHOD'] === 'POST') 
    && (!isset($_POST['action']) || $_POST['action'] !== 'generate_password');

if ($is_form_submission) {
    // GET ROLE
    $selected_role = trim($_POST['role'] ?? '');
    
    if (empty($selected_role) && isset($_POST['roles'])) {
        $roles_post = $_POST['roles'];
        if (is_array($roles_post) && count($roles_post) > 0) {
            $selected_role = trim($roles_post[0]);
        } elseif (is_string($roles_post)) {
            $selected_role = trim($roles_post);
        }
    }
    
    error_log("=== EDIT EMPLOYEE FORM SUBMISSION ===");
    error_log("POST role: " . ($_POST['role'] ?? 'NOT SET'));
    error_log("Selected role: " . $selected_role);
    error_log("Employee ID: " . $employee_id);
    
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $branch_id = (int)($_POST['branch_id'] ?? 0);
    $specialty = trim($_POST['specialty'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $generated_password = $_POST['generated_password'] ?? '';
    
    $selected_departments = $_POST['departments'] ?? [];
    if (!is_array($selected_departments)) $selected_departments = [];
    
    $password_changed = false;
    
    // Validate role
    if (empty($selected_role)) {
        $errors[] = 'Please select a role';
    } elseif (!in_array($selected_role, $valid_role_names, true)) {
        $errors[] = 'Please select a valid role';
    }
    
    if ($selected_role !== 'doctor') {
        $specialty = '';
    }
    
    if (empty($full_name)) $errors[] = 'Full name is required';
    if (empty($username)) $errors[] = 'Username is required';
    if (empty($email)) $errors[] = 'Email is required';
    if ($branch_id <= 0) $errors[] = 'Branch is required';
    
    // Password handling
    $new_password = null;
    if (!empty($generated_password)) {
        $new_password = $generated_password;
        $password_changed = true;
    } else if (!empty($password)) {
        if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters';
        if ($password !== $confirm_password) $errors[] = 'Passwords do not match';
        if (empty($errors)) {
            $new_password = $password;
            $password_changed = true;
        }
    }
    
    // Check username uniqueness
    if (empty($errors) && $username !== $employee['username']) {
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $employee_id]);
        if ($stmt->fetch()) $errors[] = 'Username already exists';
    }
    
    // Check email uniqueness
    if (empty($errors) && $email !== $employee['email']) {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $employee_id]);
        if ($stmt->fetch()) $errors[] = 'Email already exists';
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            $transaction_active = true;  // ✅ TRACK TRANSACTION STATE
            
            // Auto-create role kwenye `roles` table
            try {
                $stmt = $db->prepare("SELECT id FROM roles WHERE name = ?");
                $stmt->execute([$selected_role]);
                if (!$stmt->fetch()) {
                    $stmt = $db->prepare("INSERT INTO roles (name, description) VALUES (?, ?)");
                    $stmt->execute([$selected_role, ucfirst($selected_role) . ' - Auto-created role']);
                }
            } catch (Exception $e) {
                error_log("Role creation error: " . $e->getMessage());
            }
            
            // Auto-create Audit department
            $audit_dept_id = null;
            if ($selected_role === 'audit' || in_array('audit_virtual', $selected_departments, true)) {
                try {
                    $stmt = $db->prepare("SELECT id FROM service_categories WHERE LOWER(category_name) = 'audit'");
                    $stmt->execute();
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($row) {
                        $audit_dept_id = $row['id'];
                    } else {
                        $db->exec("INSERT INTO service_categories (category_name, description, icon, color, is_active) 
                                   VALUES ('Audit', 'System audit & compliance', 'fa-clipboard-check', '#DC2626', 1)");
                        $audit_dept_id = $db->lastInsertId();
                    }
                } catch (Exception $e) {
                    error_log("Audit dept error: " . $e->getMessage());
                }
            }
            
            // ✅ UPDATE users.role
            $sql = "UPDATE users SET 
                    full_name = ?, username = ?, email = ?, phone = ?, 
                    role = ?,
                    branch_id = ?, status = ?, specialty = ?";
            $params = [$full_name, $username, $email, $phone, $selected_role, $branch_id, $status, $specialty];
            
            if ($password_changed && $new_password !== null) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $sql .= ", password = ?, is_default_password = 0, password_changed_at = NOW()";
                $params[] = $hashed_password;
            }
            
            $sql .= ", updated_at = NOW() WHERE id = ?";
            $params[] = $employee_id;
            
            $stmt = $db->prepare($sql);
            
            if ($stmt->execute($params)) {
                // Sync employee_roles
                try {
                    $db->exec("CREATE TABLE IF NOT EXISTS employee_roles (
                        id INT(11) AUTO_INCREMENT PRIMARY KEY,
                        user_id INT(11) NOT NULL,
                        role_name VARCHAR(50) NOT NULL,
                        assigned_by INT(11),
                        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                    )");
                    
                    $stmt = $db->prepare("DELETE FROM employee_roles WHERE user_id = ?");
                    $stmt->execute([$employee_id]);
                    
                    $stmt = $db->prepare("INSERT INTO employee_roles (user_id, role_name, assigned_by) VALUES (?, ?, ?)");
                    $stmt->execute([$employee_id, $selected_role, $user_id]);
                } catch (Exception $e) {
                    error_log("employee_roles sync error: " . $e->getMessage());
                }
                
                // Sync employee_departments
                try {
                    $db->exec("CREATE TABLE IF NOT EXISTS employee_departments (
                        id INT(11) AUTO_INCREMENT PRIMARY KEY,
                        user_id INT(11) NOT NULL,
                        department_id INT(11) NOT NULL,
                        assigned_by INT(11),
                        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                    )");
                    
                    $stmt = $db->prepare("DELETE FROM employee_departments WHERE user_id = ?");
                    $stmt->execute([$employee_id]);
                    
                    foreach ($selected_departments as $dept_id) {
                        if ($dept_id === 'audit_virtual') {
                            if ($audit_dept_id) {
                                $stmt = $db->prepare("INSERT INTO employee_departments (user_id, department_id, assigned_by) VALUES (?, ?, ?)");
                                $stmt->execute([$employee_id, $audit_dept_id, $user_id]);
                            }
                            continue;
                        }
                        $stmt = $db->prepare("INSERT INTO employee_departments (user_id, department_id, assigned_by) VALUES (?, ?, ?)");
                        $stmt->execute([$employee_id, (int)$dept_id, $user_id]);
                    }
                } catch (Exception $e) {
                    error_log("employee_departments sync error: " . $e->getMessage());
                }
                
                // Log activity
                try {
                    $stmt = $db->prepare("
                        INSERT INTO activity_logs (user_id, branch_id, action, details, created_at)
                        VALUES (?, ?, 'employee_updated', ?, NOW())
                    ");
                    $details = "Employee {$full_name} updated (Role: {$selected_role})";
                    if ($password_changed) $details .= " - Password UPDATED";
                    $stmt->execute([$user_id, $branch_id, $details]);
                } catch (Exception $e) {}
                
                // ✅ COMMIT
                if ($db->inTransaction()) {
                    $db->commit();
                    $transaction_active = false;
                }
                
                // Verify
                $verify_stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
                $verify_stmt->execute([$employee_id]);
                $verified_role = $verify_stmt->fetchColumn();
                
                error_log("VERIFIED role after update: " . ($verified_role ?: 'STILL EMPTY!'));
                
                $message = "✅ Employee updated successfully!<br>
                            <strong>Role:</strong> <code style='background:#0B5ED7;color:white;padding:2px 8px;border-radius:4px;font-weight:600;'>{$selected_role}</code>";
                
                if ($password_changed && $new_password !== null) {
                    $message .= "<br>🔑 <strong>New Password:</strong> <code style='background:#0F172A;color:#34D399;padding:4px 10px;border-radius:6px;font-family:monospace;'>" . htmlspecialchars($new_password) . "</code>";
                }
                $message_type = 'success';
                
                // Refresh
                $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$employee_id]);
                $employee = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $form_data['selected_role'] = $selected_role;
                $form_data['selected_departments'] = $selected_departments;
                $form_data['specialty'] = $specialty;
                
                echo '<script>setTimeout(function(){ window.location.href = "employees.php?branch=' . $branch_id . '&updated=1"; }, 4000);</script>';
            } else {
                // ✅ SAFE ROLLBACK
                if ($db->inTransaction()) {
                    $db->rollBack();
                    $transaction_active = false;
                }
                $errors[] = 'Failed to update employee.';
            }
            
        } catch (Exception $e) {
            // ✅ SAFE ROLLBACK - INAZUIA "no active transaction" ERROR
            if ($db->inTransaction()) {
                try {
                    $db->rollBack();
                } catch (Exception $rollback_error) {
                    error_log("Rollback failed: " . $rollback_error->getMessage());
                }
            }
            $errors[] = 'Error: ' . $e->getMessage();
            error_log("Employee update error: " . $e->getMessage());
        }
    }
    
    if (!empty($errors)) {
        $message = implode('<br>', $errors);
        $message_type = 'error';
        $form_data = [
            'full_name' => $full_name,
            'username' => $username,
            'email' => $email,
            'phone' => $phone,
            'branch_id' => $branch_id,
            'selected_role' => $selected_role,
            'selected_departments' => $selected_departments,
            'specialty' => $specialty,
            'status' => $status,
            'password' => $password ?? '',
            'password_changed' => $password_changed
        ];
    }
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC CSS -->
<!-- ================================================================ -->
<style>
    .page-header-emp {
        background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 100%);
        border-radius: 18px;
        padding: 26px 34px;
        margin-bottom: 26px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        box-shadow: 0 8px 32px rgba(10, 76, 168, 0.35);
        position: relative;
        overflow: hidden;
    }
    .page-header-emp::before {
        content: '';
        position: absolute;
        top: -60%;
        right: -10%;
        width: 400px;
        height: 400px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
        pointer-events: none;
    }
    .page-header-emp .page-title {
        color: white;
        font-size: 1.7rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin: 0;
    }
    .page-header-emp .page-subtitle {
        color: rgba(255,255,255,0.88);
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin-top: 6px;
    }
    .page-header-emp .role-badge-display {
        background: rgba(255,255,255,0.2);
        color: white;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
        text-transform: uppercase;
    }
    .page-header-emp .header-badge {
        background: rgba(255,255,255,0.15);
        color: white;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        backdrop-filter: blur(4px);
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 1px solid rgba(255,255,255,0.1);
    }
    .page-header-emp .btn-outline-light {
        background: rgba(255,255,255,0.12);
        color: white;
        border: 1px solid rgba(255,255,255,0.2);
        padding: 8px 18px;
        border-radius: 12px;
        font-weight: 500;
        font-size: 0.82rem;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        position: relative;
        z-index: 1;
    }
    .page-header-emp .btn-outline-light:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        color: white;
    }

    .form-card-emp {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 20px;
        padding: 28px 32px;
        border: 2px solid var(--page-border, #E2E8F0);
        box-shadow: var(--page-shadow-sm, 0 1px 3px rgba(0,0,0,0.06));
        max-width: 1100px;
        margin: 0 auto;
    }
    .form-card-emp:hover {
        border-color: var(--page-primary, #0B5ED7);
        box-shadow: 0 8px 30px rgba(11, 94, 215, 0.08);
    }
    .form-header-emp {
        display: flex;
        align-items: center;
        gap: 16px;
        padding-bottom: 20px;
        margin-bottom: 24px;
        border-bottom: 2px solid var(--page-border, #E2E8F0);
    }
    .form-header-emp .form-header-icon {
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
    .form-header-emp h3 {
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--page-text-primary, #1E293B);
        margin: 0;
    }
    .form-header-emp p {
        font-size: 0.85rem;
        color: var(--page-text-secondary, #64748B);
        margin: 0;
    }

    .form-label-emp {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--page-text-primary, #1E293B);
        margin-bottom: 6px;
        display: block;
    }
    .form-label-emp i { width: 20px; text-align: center; font-size: 0.85rem; }
    .form-label-emp .required { color: #EF4444; margin-left: 2px; }

    .form-control-emp {
        width: 100%;
        padding: 10px 16px;
        border: 2px solid var(--page-border, #E2E8F0);
        border-radius: 12px;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        outline: none;
        background: var(--page-input-bg, #FFFFFF);
        color: var(--page-text-primary, #1E293B);
        font-family: inherit;
    }
    .form-control-emp:focus {
        border-color: var(--page-primary, #0B5ED7);
        box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.12);
    }
    select.form-control-emp { appearance: auto; cursor: pointer; }

    .form-row-icon-emp { position: relative; }
    .form-row-icon-emp .form-control-emp { padding-left: 44px; }
    .form-row-icon-emp .input-icon {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--page-text-secondary, #64748B);
        font-size: 1rem;
        pointer-events: none;
    }

    .password-input-group-emp { position: relative; display: flex; align-items: center; }
    .password-input-group-emp .form-control-emp { padding-right: 50px; }
    .password-input-group-emp .password-toggle {
        position: absolute;
        right: 12px;
        background: none;
        border: none;
        color: var(--page-text-secondary, #64748B);
        cursor: pointer;
        padding: 6px 8px;
        font-size: 1rem;
        border-radius: 8px;
    }
    .password-input-group-emp .password-toggle:hover {
        color: var(--page-primary, #0B5ED7);
        background: var(--page-hover, #F8FAFC);
    }

    .btn-generate-emp {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.78rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        background: var(--page-primary, #0B5ED7);
        color: white;
    }
    .btn-generate-emp:hover {
        background: var(--page-primary-dark, #0A4CA8);
        transform: translateY(-2px);
    }

    .radio-group-emp {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 12px;
        padding: 16px;
        border: 2px solid var(--page-border, #E2E8F0);
        border-radius: 14px;
        background: var(--page-hover, #F8FAFC);
    }
    .radio-item-emp {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 16px;
        border-radius: 12px;
        background: var(--page-bg-card, #FFFFFF);
        border: 2px solid var(--page-border, #E2E8F0);
        transition: all 0.3s ease;
        cursor: pointer;
        position: relative;
        overflow: hidden;
    }
    .radio-item-emp::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: var(--page-primary, #0B5ED7);
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    .radio-item-emp:hover {
        border-color: var(--page-primary, #0B5ED7);
        background: #E8F0FE;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.12);
    }
    .radio-item-emp.checked {
        border-color: var(--page-primary, #0B5ED7);
        background: #E8F0FE;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.15);
    }
    .radio-item-emp.checked::before { opacity: 1; }
    .radio-item-emp.audit-item:hover,
    .radio-item-emp.audit-item.checked {
        border-color: #DC2626;
        background: #FEE2E2;
    }
    .radio-item-emp.audit-item.checked::before { background: #DC2626; }

    .radio-item-emp input[type="radio"] {
        width: 20px;
        height: 20px;
        accent-color: var(--page-primary, #0B5ED7);
        cursor: pointer;
        flex-shrink: 0;
    }
    .radio-item-emp.audit-item input[type="radio"] { accent-color: #DC2626; }

    .radio-item-emp label {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--page-text-primary, #1E293B);
        cursor: pointer;
        width: 100%;
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    .radio-item-emp .role-desc-emp {
        font-size: 0.68rem;
        color: var(--page-text-secondary, #64748B);
        font-weight: 400;
        opacity: 0.85;
    }

    .role-icon-wrapper-emp {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
        background: #E8F0FE;
        color: #0B5ED7;
    }
    .role-icon-wrapper-emp.audit { background: #FEE2E2; color: #DC2626; }

    .checkbox-group-emp {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 10px;
        padding: 14px;
        border: 2px solid var(--page-border, #E2E8F0);
        border-radius: 12px;
        background: var(--page-hover, #F8FAFC);
    }
    .checkbox-item-emp {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 14px;
        border-radius: 10px;
        background: var(--page-bg-card, #FFFFFF);
        border: 2px solid var(--page-border, #E2E8F0);
        transition: all 0.3s ease;
        cursor: pointer;
    }
    .checkbox-item-emp:hover,
    .checkbox-item-emp.checked {
        border-color: var(--page-primary, #0B5ED7);
        background: #E8F0FE;
    }
    .checkbox-item-emp.audit-item:hover,
    .checkbox-item-emp.audit-item.checked {
        border-color: #DC2626;
        background: #FEE2E2;
    }
    .checkbox-item-emp input[type="checkbox"] {
        width: 18px;
        height: 18px;
        accent-color: var(--page-primary, #0B5ED7);
        cursor: pointer;
        flex-shrink: 0;
    }
    .checkbox-item-emp.audit-item input[type="checkbox"] { accent-color: #DC2626; }
    .checkbox-item-emp label {
        font-size: 0.82rem;
        font-weight: 600;
        color: var(--page-text-primary, #1E293B);
        cursor: pointer;
        width: 100%;
    }
    .checkbox-item-emp .dept-desc-emp {
        font-size: 0.68rem;
        color: var(--page-text-secondary, #64748B);
        font-weight: 400;
        display: block;
        opacity: 0.85;
        margin-top: 2px;
    }

    .section-title-emp {
        font-size: 1rem;
        font-weight: 700;
        color: var(--page-primary, #0B5ED7);
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .section-title-emp .badge-count-emp {
        font-size: 0.7rem;
        font-weight: 400;
        color: var(--page-text-secondary, #64748B);
        margin-left: 8px;
    }
    .section-divider-emp {
        border: none;
        border-top: 2px dashed var(--page-border, #E2E8F0);
        margin: 12px 0 16px;
    }
    .help-text-emp {
        font-size: 0.72rem;
        color: var(--page-text-secondary, #64748B);
        margin-top: 6px;
    }

    .password-strength-emp { display: flex; gap: 4px; margin-top: 6px; }
    .password-strength-emp .strength-bar {
        height: 4px;
        flex: 1;
        border-radius: 4px;
        background: var(--page-border, #E2E8F0);
        transition: all 0.3s ease;
    }
    .password-strength-emp .strength-bar.weak { background: #EF4444; }
    .password-strength-emp .strength-bar.medium { background: #F59E0B; }
    .password-strength-emp .strength-bar.strong { background: #10B981; }
    .password-strength-emp .strength-bar.very-strong { background: #059669; }
    .password-strength-text-emp {
        font-size: 0.7rem;
        color: var(--page-text-secondary, #64748B);
        margin-top: 4px;
    }

    .btn-emp {
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
        font-family: inherit;
    }
    .btn-primary-emp {
        background: linear-gradient(135deg, #0B5ED7, #1A73E8);
        color: white;
        box-shadow: 0 4px 14px rgba(11, 94, 215, 0.3);
    }
    .btn-primary-emp:hover {
        background: linear-gradient(135deg, #0A4CA8, #1557B0);
        transform: translateY(-2px);
        color: white;
    }
    .btn-outline-emp {
        background: transparent;
        color: var(--page-text-primary, #1E293B);
        border: 2px solid var(--page-border, #E2E8F0);
    }
    .btn-outline-emp:hover {
        background: var(--page-hover, #F8FAFC);
        border-color: var(--page-primary, #0B5ED7);
        color: var(--page-primary, #0B5ED7);
    }
    .form-actions-emp {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        padding-top: 24px;
        margin-top: 24px;
        border-top: 2px solid var(--page-border, #E2E8F0);
    }

    .message-box-emp {
        padding: 14px 20px;
        border-radius: 12px;
        margin-bottom: 18px;
        display: flex;
        align-items: flex-start;
        gap: 12px;
        font-weight: 500;
        max-width: 1100px;
        margin-left: auto;
        margin-right: auto;
    }
    .message-box-emp.success {
        background: #D1FAE5;
        color: #065F46;
        border: 2px solid #6EE7B7;
    }
    .message-box-emp.error {
        background: #FEE2E2;
        color: #991B1B;
        border: 2px solid #FCA5A5;
    }

    .footer-emp {
        padding: 14px 0;
        border-top: 2px solid var(--page-border, #E2E8F0);
        margin-top: 24px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--page-text-secondary, #64748B);
    }
    .footer-emp .footer-brand-emp { color: var(--page-primary, #0B5ED7); font-weight: 700; }

    .grid-2-emp { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
    .col-span-2-emp { grid-column: 1 / -1; }

    @media (max-width: 768px) {
        .grid-2-emp { grid-template-columns: 1fr; }
        .col-span-2-emp { grid-column: span 1; }
        .form-card-emp { padding: 20px; }
        .form-actions-emp { flex-direction: column; }
        .form-actions-emp .btn-emp { width: 100%; justify-content: center; }
        .page-header-emp { padding: 18px 20px; }
        .page-header-emp .page-title { font-size: 1.3rem; }
        .radio-group-emp, .checkbox-group-emp { grid-template-columns: 1fr; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <div class="page-header-emp">
        <div>
            <h1 class="page-title">
                <i class="fas fa-user-edit"></i>
                Edit Employee
                <span class="role-badge-display">ADMIN</span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-user"></i>
                <strong><?= htmlspecialchars($employee['full_name']) ?></strong>
                <span class="header-badge">
                    <i class="fas fa-<?= ($employee['status'] ?? 'active') === 'active' ? 'check-circle' : 'times-circle' ?>"></i>
                    <?= ucfirst($employee['status'] ?? 'Active') ?>
                </span>
                <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                    <i class="fas fa-tag"></i> <?= ucfirst($employee['role'] ?: 'No Role') ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="employees.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to Employees
            </a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="message-box-emp <?= $message_type === 'success' ? 'success' : 'error' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>" style="font-size:1.2rem;flex-shrink:0;"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <div class="form-card-emp">
        <div class="form-header-emp">
            <div class="form-header-icon">
                <i class="fas fa-user-edit"></i>
            </div>
            <div>
                <h3>Edit Employee Information</h3>
                <p>Update employee details, role, departments, and password</p>
            </div>
        </div>
        
        <form method="POST" action="" id="editEmployeeForm">
            <div class="grid-2-emp">
                
                <div class="col-span-2-emp">
                    <h3 class="section-title-emp">
                        <i class="fas fa-user-circle"></i> Personal Information
                    </h3>
                    <hr class="section-divider-emp">
                </div>
                
                <div>
                    <label class="form-label-emp">
                        <i class="fas fa-user" style="color:#0B5ED7;"></i> Full Name <span class="required">*</span>
                    </label>
                    <div class="form-row-icon-emp">
                        <input type="text" name="full_name" id="fullName" class="form-control-emp" 
                               placeholder="Enter full name" 
                               value="<?= htmlspecialchars($form_data['full_name']) ?>" required>
                        <span class="input-icon"><i class="fas fa-user"></i></span>
                    </div>
                </div>
                
                <div>
                    <label class="form-label-emp">
                        <i class="fas fa-at" style="color:#0B5ED7;"></i> Username <span class="required">*</span>
                    </label>
                    <div class="form-row-icon-emp">
                        <input type="text" name="username" id="username" class="form-control-emp" 
                               placeholder="Enter username" 
                               value="<?= htmlspecialchars($form_data['username']) ?>" required>
                        <span class="input-icon"><i class="fas fa-at"></i></span>
                    </div>
                </div>
                
                <div>
                    <label class="form-label-emp">
                        <i class="fas fa-envelope" style="color:#059669;"></i> Email <span class="required">*</span>
                    </label>
                    <div class="form-row-icon-emp">
                        <input type="email" name="email" class="form-control-emp" 
                               placeholder="Enter email" 
                               value="<?= htmlspecialchars($form_data['email']) ?>" required>
                        <span class="input-icon"><i class="fas fa-envelope"></i></span>
                    </div>
                </div>
                
                <div>
                    <label class="form-label-emp">
                        <i class="fas fa-phone" style="color:#0B5ED7;"></i> Phone Number
                    </label>
                    <div class="form-row-icon-emp">
                        <input type="text" name="phone" class="form-control-emp" 
                               placeholder="Enter phone number" 
                               value="<?= htmlspecialchars($form_data['phone']) ?>">
                        <span class="input-icon"><i class="fas fa-phone"></i></span>
                    </div>
                </div>
                
                <div>
                    <label class="form-label-emp">
                        <i class="fas fa-store-alt" style="color:#059669;"></i> Branch <span class="required">*</span>
                    </label>
                    <div class="form-row-icon-emp">
                        <select name="branch_id" id="branchSelect" class="form-control-emp" required>
                            <option value="">Select Branch</option>
                            <?php foreach ($branches_list as $branch): ?>
                                <option value="<?= $branch['id'] ?>" <?= $branch['id'] == $form_data['branch_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($branch['name']) ?>
                                    <?= !empty($branch['location']) ? '- ' . htmlspecialchars($branch['location']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="input-icon"><i class="fas fa-store-alt"></i></span>
                    </div>
                </div>
                
                <div>
                    <label class="form-label-emp">
                        <i class="fas fa-stethoscope" style="color:#7C3AED;"></i> Specialty
                    </label>
                    <div class="form-row-icon-emp">
                        <input type="text" name="specialty" class="form-control-emp" 
                               placeholder="e.g. Cardiology, Pediatrics" 
                               value="<?= htmlspecialchars($form_data['specialty']) ?>">
                        <span class="input-icon"><i class="fas fa-stethoscope"></i></span>
                    </div>
                </div>
                
                <div>
                    <label class="form-label-emp">
                        <i class="fas fa-toggle-on" style="color:#0B5ED7;"></i> Status
                    </label>
                    <div class="form-row-icon-emp">
                        <select name="status" class="form-control-emp">
                            <option value="active" <?= $form_data['status'] === 'active' ? 'selected' : '' ?>>✅ Active</option>
                            <option value="inactive" <?= $form_data['status'] === 'inactive' ? 'selected' : '' ?>>⛔ Inactive</option>
                        </select>
                        <span class="input-icon"><i class="fas fa-toggle-on"></i></span>
                    </div>
                </div>
                
                <div class="col-span-2-emp">
                    <h3 class="section-title-emp">
                        <i class="fas fa-key" style="color:#D97706;"></i> Password Settings
                        <span class="badge-count-emp">(Leave empty to keep current password)</span>
                    </h3>
                    <hr class="section-divider-emp">
                    
                    <div class="grid-2-emp">
                        <div>
                            <label class="form-label-emp">
                                <i class="fas fa-lock" style="color:#0B5ED7;"></i> New Password
                            </label>
                            <div class="password-input-group-emp">
                                <input type="password" name="password" id="newPassword" class="form-control-emp" 
                                       placeholder="Enter new password or leave empty">
                                <button type="button" class="password-toggle" onclick="togglePasswordVisibility('newPassword', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div>
                            <label class="form-label-emp">
                                <i class="fas fa-lock" style="color:#0B5ED7;"></i> Confirm Password
                            </label>
                            <div class="password-input-group-emp">
                                <input type="password" name="confirm_password" id="confirmPassword" class="form-control-emp" 
                                       placeholder="Confirm new password">
                                <button type="button" class="password-toggle" onclick="togglePasswordVisibility('confirmPassword', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:10px;">
                        <button type="button" class="btn-generate-emp" id="generatePasswordBtn">
                            <i class="fas fa-sync-alt"></i> Generate Password
                        </button>
                        <span class="help-text-emp">Format: Name + Branch Code + User ID</span>
                    </div>
                    
                    <div class="password-strength-emp" id="passwordStrength">
                        <div class="strength-bar" data-index="0"></div>
                        <div class="strength-bar" data-index="1"></div>
                        <div class="strength-bar" data-index="2"></div>
                        <div class="strength-bar" data-index="3"></div>
                    </div>
                    <div class="password-strength-text-emp" id="passwordStrengthText">Enter a password to check strength</div>
                    
                    <input type="hidden" name="generated_password" id="generatedPasswordHidden" value="">
                </div>
                
                <div class="col-span-2-emp">
                    <h3 class="section-title-emp">
                        <i class="fas fa-user-tag"></i> Select Role
                        <span class="required">*</span>
                        <span class="badge-count-emp">(Role MOJA pekee)</span>
                    </h3>
                    <p class="help-text-emp">
                        <i class="fas fa-info-circle"></i> Chagua role moja. Role hii itahifadhiwa kwenye <code>users.role</code> column.
                    </p>
                    <hr class="section-divider-emp">
                    
                    <div class="radio-group-emp" id="rolesContainer">
                        <?php foreach ($available_roles as $role): ?>
                            <label class="radio-item-emp <?= $role['name'] === 'audit' ? 'audit-item' : '' ?> <?= $form_data['selected_role'] === $role['name'] ? 'checked' : '' ?>" 
                                   for="role_<?= $role['name'] ?>">
                                <input type="radio" 
                                       name="role" 
                                       value="<?= $role['name'] ?>" 
                                       id="role_<?= $role['name'] ?>"
                                       onchange="updateRoleSelection(this)"
                                       <?= $form_data['selected_role'] === $role['name'] ? 'checked' : '' ?>>
                                <div class="role-icon-wrapper-emp <?= $role['name'] === 'audit' ? 'audit' : '' ?>"
                                     style="<?= $role['name'] !== 'audit' ? 'background:' . $role['color'] . '15;color:' . $role['color'] . ';' : '' ?>">
                                    <i class="fas <?= $role['icon'] ?>"></i>
                                </div>
                                <span>
                                    <?= ucfirst($role['label']) ?>
                                    <span class="role-desc-emp"><?= $role['desc'] ?></span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="help-text-emp">
                        Selected: <strong id="selectedRoleLabel" style="color:#0B5ED7;">
                            <?= !empty($form_data['selected_role']) ? ucfirst($form_data['selected_role']) : 'None' ?>
                        </strong>
                    </p>
                </div>
                
                <div class="col-span-2-emp">
                    <h3 class="section-title-emp">
                        <i class="fas fa-building"></i> Select Departments
                        <span class="badge-count-emp">(Optional)</span>
                    </h3>
                    <hr class="section-divider-emp">
                    
                    <div class="checkbox-group-emp" id="departmentsContainer">
                        <?php foreach ($departments as $dept): 
                            $is_audit_dept = (strtolower($dept['category_name']) === 'audit');
                        ?>
                            <label class="checkbox-item-emp <?= $is_audit_dept ? 'audit-item' : '' ?> <?= in_array($dept['id'], $form_data['selected_departments']) ? 'checked' : '' ?>"
                                   for="dept_<?= $dept['id'] ?>">
                                <input type="checkbox" 
                                       name="departments[]" 
                                       value="<?= $dept['id'] ?>" 
                                       id="dept_<?= $dept['id'] ?>"
                                       onchange="updateDeptCount()"
                                       <?= in_array($dept['id'], $form_data['selected_departments']) ? 'checked' : '' ?>>
                                <span>
                                    <?= htmlspecialchars($dept['category_name']) ?>
                                    <?php if (!empty($dept['description'])): ?>
                                        <span class="dept-desc-emp"><?= htmlspecialchars($dept['description']) ?></span>
                                    <?php endif; ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="help-text-emp">Selected: <strong id="selectedDeptCount">0</strong> departments</p>
                </div>
                
            </div>
            
            <div class="form-actions-emp">
                <button type="submit" class="btn-emp btn-primary-emp">
                    <i class="fas fa-save"></i> Update Employee
                </button>
                <a href="employees.php?branch=<?= $selected_branch_id ?>" class="btn-emp btn-outline-emp">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="reset" class="btn-emp btn-outline-emp">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </div>
        </form>
    </div>

    <footer class="footer-emp">
        <p>
            <span class="footer-brand-emp">Braick Dispensary</span> Management System
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            Edit Employee
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<script>
    // ================================================================
    // FOOTER TIME
    // ================================================================
    setInterval(function() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }, 1000);

    // ================================================================
    // ROLE SELECTION - UPDATE VISUAL
    // ================================================================
    function updateRoleSelection(radio) {
        // Remove checked class from all
        document.querySelectorAll('.radio-item-emp').forEach(function(item) {
            item.classList.remove('checked');
        });
        
        // Add checked class to selected
        if (radio.checked) {
            radio.closest('.radio-item-emp').classList.add('checked');
        }
        
        // Update label
        var labelEl = document.getElementById('selectedRoleLabel');
        if (labelEl) {
            labelEl.textContent = radio.value.charAt(0).toUpperCase() + radio.value.slice(1);
        }
        
        console.log('✅ Role selected: ' + radio.value);
    }

    // ================================================================
    // DEPARTMENT COUNT
    // ================================================================
    function updateDeptCount() {
        // Update checked classes
        document.querySelectorAll('.checkbox-item-emp').forEach(function(item) {
            var checkbox = item.querySelector('input[type="checkbox"]');
            if (checkbox && checkbox.checked) {
                item.classList.add('checked');
            } else {
                item.classList.remove('checked');
            }
        });
        
        var deptsChecked = document.querySelectorAll('input[name="departments[]"]:checked');
        var deptCount = document.getElementById('selectedDeptCount');
        if (deptCount) deptCount.textContent = deptsChecked.length;
    }

    // ================================================================
    // INITIALIZE ON LOAD
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        // Update department count
        updateDeptCount();
        
        // Log initial role
        var checkedRole = document.querySelector('input[name="role"]:checked');
        if (checkedRole) {
            console.log('Initial role: ' + checkedRole.value);
        } else {
            console.log('No role selected initially');
        }
    });

    // ================================================================
    // FORM VALIDATION
    // ================================================================
    document.getElementById('editEmployeeForm')?.addEventListener('submit', function(e) {
        var selectedRole = document.querySelector('input[name="role"]:checked');
        
        console.log('=== FORM SUBMIT ===');
        console.log('Selected role:', selectedRole ? selectedRole.value : 'NONE');
        
        if (!selectedRole) {
            e.preventDefault();
            alert('⚠️ Please select a role for this employee.');
            document.getElementById('rolesContainer').style.borderColor = '#EF4444';
            setTimeout(function() {
                document.getElementById('rolesContainer').style.borderColor = '';
            }, 3000);
            return false;
        }
        
        return true;
    });

    // ================================================================
    // PASSWORD TOGGLE
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
    // PASSWORD STRENGTH
    // ================================================================
    document.getElementById('newPassword')?.addEventListener('input', function() {
        var strength = checkPasswordStrength(this.value);
        updatePasswordStrength(strength);
    });

    function checkPasswordStrength(password) {
        if (password.length === 0) return { score: 0, label: 'Enter a password to check strength', class: '' };
        var score = 0;
        if (password.length >= 8) score++;
        if (password.length >= 12) score++;
        if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score++;
        if (/\d/.test(password)) score++;
        if (/[^a-zA-Z0-9]/.test(password)) score++;
        
        var labels = ['Weak', 'Weak', 'Medium', 'Strong', 'Very Strong'];
        var classes = ['', 'weak', 'medium', 'strong', 'very-strong'];
        var idx = Math.min(score, 4);
        return { score: idx, label: labels[idx], class: classes[idx] };
    }

    function updatePasswordStrength(strength) {
        var bars = document.querySelectorAll('#passwordStrength .strength-bar');
        var text = document.getElementById('passwordStrengthText');
        bars.forEach(function(bar, i) {
            bar.className = 'strength-bar';
            if (i < strength.score) bar.classList.add(strength.class);
        });
        if (strength.score === 0) {
            text.textContent = 'Enter a password to check strength';
            text.style.color = '';
        } else {
            text.textContent = 'Strength: ' + strength.label;
            text.style.color = strength.class === 'weak' ? '#EF4444' : 
                               strength.class === 'medium' ? '#F59E0B' : 
                               strength.class === 'strong' ? '#10B981' : '#059669';
        }
    }

    // ================================================================
    // GENERATE PASSWORD
    // ================================================================
    document.getElementById('generatePasswordBtn')?.addEventListener('click', function() {
        var fullName = document.getElementById('fullName').value.trim();
        var branchId = document.getElementById('branchSelect').value;
        var userId = <?= $employee_id ?>;
        
        if (!fullName) {
            alert('⚠️ Please enter the full name first');
            document.getElementById('fullName').focus();
            return;
        }
        
        if (!branchId) {
            alert('⚠️ Please select a branch first');
            document.getElementById('branchSelect').focus();
            return;
        }
        
        var btn = this;
        var originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
        btn.disabled = true;
        
        var formData = new FormData();
        formData.append('action', 'generate_password');
        formData.append('full_name', fullName);
        formData.append('branch_id', branchId);
        formData.append('user_id', userId);
        formData.append('current_user_id', userId);
        
        fetch(window.location.href, { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.innerHTML = originalText;
            btn.disabled = false;
            
            if (data.success) {
                document.getElementById('newPassword').value = data.password;
                document.getElementById('confirmPassword').value = data.password;
                document.getElementById('generatedPasswordHidden').value = data.password;
                
                var event = new Event('input', { bubbles: true });
                document.getElementById('newPassword').dispatchEvent(event);
                
                alert('✅ Password generated: ' + data.password);
            } else {
                alert('❌ Error: ' + (data.error || 'Failed to generate password'));
            }
        })
        .catch(function(error) {
            btn.innerHTML = originalText;
            btn.disabled = false;
            alert('❌ Network error: ' + error.message);
        });
    });

    console.log('%c👤 Braick - Edit Employee (FIXED)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ FIXED: "There is no active transaction" error', 'font-size:13px; color:#059669;');
    console.log('%c✅ FIXED: Role inaingia kwenye users.role', 'font-size:13px; color:#059669;');
    console.log('%c👤 Employee: <?= htmlspecialchars($employee['full_name']) ?> (ID: <?= $employee_id ?>)', 'font-size:13px; color:#059669;');
    console.log('%c📋 Current role: <?= htmlspecialchars($employee['role'] ?: 'NONE') ?>', 'font-size:13px; color:#7C3AED;');
</script>

</body>
</html>