<?php
// ================================================================
// FILE: frontend/pages/admin/add_employee.php
// SUPER ADMIN - ADD EMPLOYEE
// ✅ Uses SHARED header & sidebar
// ✅ FIXED: "There is no active transaction" error
// ✅ FIXED: Audit role inahifadhiwa kwenye users.role
// ✅ Pre-create tables nje ya transaction
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

require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

$selected_branch_id = $_GET['branch'] ?? 'all';

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
// ✅ PRE-CREATE TABLES (nje ya transaction - mara moja tu)
// ================================================================
try {
    $db->exec("CREATE TABLE IF NOT EXISTS employee_roles (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) NOT NULL,
        role_name VARCHAR(50) NOT NULL,
        assigned_by INT(11),
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $db->exec("CREATE TABLE IF NOT EXISTS employee_departments (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) NOT NULL,
        department_id INT(11) NOT NULL,
        assigned_by INT(11),
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {
    error_log("Pre-create tables error: " . $e->getMessage());
}

// ================================================================
// ✅ ENSURE 'audit' ROLE INAINGIA KWENYE users.role
// ================================================================
function ensureAuditRoleSupported($db) {
    try {
        $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'role'");
        $column = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$column) return false;
        
        $type = strtolower($column['Type']);
        
        // Kama ni ENUM, badilisha kuwa VARCHAR
        if (strpos($type, 'enum') !== false) {
            $has_audit = (strpos($type, "'audit'") !== false);
            
            if (!$has_audit) {
                error_log("Converting users.role from ENUM to VARCHAR...");
                
                try {
                    $db->exec("ALTER TABLE users MODIFY COLUMN role VARCHAR(50) NOT NULL DEFAULT 'reception'");
                    error_log("✅ Converted users.role to VARCHAR(50)");
                    return true;
                } catch (Exception $e) {
                    error_log("ALTER TABLE failed: " . $e->getMessage());
                    
                    // Fallback: add audit to ENUM
                    try {
                        preg_match_all("/'([^']+)'/", $type, $matches);
                        $existing = $matches[1];
                        if (!in_array('audit', $existing)) $existing[] = 'audit';
                        $enum_values = "'" . implode("','", $existing) . "'";
                        $db->exec("ALTER TABLE users MODIFY COLUMN role ENUM($enum_values) NOT NULL");
                        error_log("✅ Added 'audit' to ENUM as fallback");
                        return true;
                    } catch (Exception $e2) {
                        error_log("Fallback failed: " . $e2->getMessage());
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("ensureAuditRoleSupported error: " . $e->getMessage());
    }
    return false;
}

// Run auto-fix
ensureAuditRoleSupported($db);

// ================================================================
// GET STATISTICS
// ================================================================
$total_employees = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role != 'admin'");
    $total_employees = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) { $total_employees = 0; }

$total_doctors = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'doctor' AND status = 'active'");
    $total_doctors = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) { $total_doctors = 0; }

$total_branches = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM branches WHERE status = 'active'");
    $total_branches = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) { $total_branches = 0; }

// ================================================================
// GET BRANCHES
// ================================================================
$branches = [];
try {
    $stmt = $db->query("SELECT id, name, location FROM branches WHERE status = 'active' ORDER BY name");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $branches[] = $row;
    }
} catch (Exception $e) { $branches = []; }

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

// Priority order
$primary_role_priority = ['audit', 'doctor', 'reception', 'pharmacy', 'laboratory', 'cashier'];

// ================================================================
// GET DEPARTMENTS
// ================================================================
$departments = [];
try {
    $stmt = $db->query("SELECT id, category_name, description, icon, color FROM service_categories WHERE is_active = 1 ORDER BY category_name");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $departments[] = $row;
    }
} catch (Exception $e) { $departments = []; }

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
// PASSWORD GENERATOR
// ================================================================
function generatePassword($full_name, $branch_id, $user_id = null) {
    $clean_name = preg_replace('/[^a-zA-Z]/', '', $full_name);
    $name_part = strtoupper(substr($clean_name, 0, 4));
    if (strlen($name_part) < 3) $name_part = 'USER';
    
    $branch_code = 'BR' . str_pad($branch_id, 2, '0', STR_PAD_LEFT);
    
    if ($user_id) {
        $user_code = 'UID' . str_pad($user_id, 4, '0', STR_PAD_LEFT);
    } else {
        $user_code = 'UID' . rand(1000, 9999);
    }
    
    $password = $name_part . $branch_code . $user_code;
    if (strlen($password) < 8) $password .= rand(100, 999);
    
    return $password;
}

// ================================================================
// AJAX - GENERATE PASSWORD
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
    
    if ($user_id <= 0) $user_id = rand(1000, 9999);
    
    $password = generatePassword($full_name, $branch_id, $user_id);
    echo json_encode(['success' => true, 'password' => $password, 'user_id' => $user_id]);
    exit;
}

// ================================================================
// FORM SUBMISSION - FIXED
// ================================================================
$message = '';
$message_type = '';
$errors = [];
$form_data = [
    'full_name' => '',
    'username' => '',
    'email' => '',
    'phone' => '',
    'password' => '',
    'branch_id' => $selected_branch_id !== 'all' ? (int)$selected_branch_id : '',
    'selected_roles' => [],
    'selected_departments' => [],
    'specialty' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $form_data['full_name'] = trim($_POST['full_name'] ?? '');
    $form_data['username'] = trim($_POST['username'] ?? '');
    $form_data['email'] = trim($_POST['email'] ?? '');
    $form_data['phone'] = trim($_POST['phone'] ?? '');
    $form_data['password'] = $_POST['password'] ?? '';
    $form_data['branch_id'] = (int)($_POST['branch_id'] ?? 0);
    $form_data['specialty'] = trim($_POST['specialty'] ?? '');
    $form_data['selected_roles'] = $_POST['roles'] ?? [];
    $form_data['selected_departments'] = $_POST['departments'] ?? [];
    
    if (!is_array($form_data['selected_roles'])) {
        $form_data['selected_roles'] = [];
    }
    $form_data['selected_roles'] = array_values(array_filter($form_data['selected_roles'], function($r) use ($valid_role_names) {
        return in_array($r, $valid_role_names, true);
    }));
    
    if (!is_array($form_data['selected_departments'])) {
        $form_data['selected_departments'] = [];
    }
    
    // ✅ Determine primary role with PRIORITY
    $primary_role = null;
    foreach ($primary_role_priority as $priority_role) {
        if (in_array($priority_role, $form_data['selected_roles'], true)) {
            $primary_role = $priority_role;
            break;
        }
    }
    
    if (empty($primary_role) && !empty($form_data['selected_roles'])) {
        $primary_role = $form_data['selected_roles'][0];
    }
    
    if (empty($primary_role) || !in_array($primary_role, $valid_role_names, true)) {
        $primary_role = 'doctor';
    }
    
    if ($primary_role !== 'doctor') {
        $form_data['specialty'] = '';
    }
    
    // Validation
    if (empty($form_data['full_name'])) $errors[] = 'Full name is required';
    if (empty($form_data['username'])) $errors[] = 'Username is required';
    if (empty($form_data['email'])) $errors[] = 'Email is required';
    if (empty($form_data['password'])) $errors[] = 'Password is required';
    if (empty($form_data['selected_roles'])) $errors[] = 'At least one role must be selected';
    if ($form_data['branch_id'] <= 0) $errors[] = 'Branch is required';
    
    if (count($form_data['selected_roles']) > 2) {
        $errors[] = 'Maximum of 2 roles allowed per employee';
    }
    if (count($form_data['selected_roles']) == 2 && !in_array('reception', $form_data['selected_roles'])) {
        $errors[] = 'If assigning 2 roles, one must be Reception';
    }
    
    if (empty($errors)) {
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$form_data['username']]);
        if ($stmt->fetch()) $errors[] = 'Username already exists';
    }
    
    if (empty($errors)) {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$form_data['email']]);
        if ($stmt->fetch()) $errors[] = 'Email already exists';
    }
    
    if (empty($errors)) {
        try {
            // ✅ Ensure audit support BEFORE starting transaction
            if ($primary_role === 'audit') {
                ensureAuditRoleSupported($db);
            }
            
            $db->beginTransaction();
            
            // Auto-create Audit role row
            try {
                $db->exec("INSERT IGNORE INTO roles (name, description) VALUES ('audit', 'Audit - System audit and compliance')");
            } catch (Exception $e) {
                error_log("Audit role insert error: " . $e->getMessage());
            }
            
            // Auto-create Audit department
            $audit_dept_id = null;
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
            
            $hashed_password = password_hash($form_data['password'], PASSWORD_DEFAULT);
            
            // INSERT USER
            $stmt = $db->prepare("
                INSERT INTO users 
                (username, password, password_changed_at, is_default_password, full_name, email, phone, role, branch_id, specialty, status, created_at) 
                VALUES (?, ?, NOW(), 1, ?, ?, ?, ?, ?, ?, 'active', NOW())
            ");
            
            $execute_success = $stmt->execute([
                $form_data['username'],
                $hashed_password,
                $form_data['full_name'],
                $form_data['email'],
                $form_data['phone'],
                $primary_role,
                $form_data['branch_id'],
                $form_data['specialty']
            ]);
            
            if ($execute_success) {
                $new_user_id = $db->lastInsertId();
                
                // Store roles in employee_roles
                try {
                    foreach ($form_data['selected_roles'] as $role_name) {
                        $stmt = $db->prepare("INSERT INTO employee_roles (user_id, role_name, assigned_by) VALUES (?, ?, ?)");
                        $stmt->execute([$new_user_id, $role_name, $_SESSION['user_id']]);
                    }
                } catch (Exception $e) {
                    error_log("Employee roles error: " . $e->getMessage());
                }
                
                // Store departments
                if (!empty($form_data['selected_departments'])) {
                    try {
                        foreach ($form_data['selected_departments'] as $dept_id) {
                            if ($dept_id === 'audit_virtual') {
                                if ($audit_dept_id) {
                                    $stmt = $db->prepare("INSERT INTO employee_departments (user_id, department_id, assigned_by) VALUES (?, ?, ?)");
                                    $stmt->execute([$new_user_id, $audit_dept_id, $_SESSION['user_id']]);
                                }
                                continue;
                            }
                            
                            $stmt = $db->prepare("INSERT INTO employee_departments (user_id, department_id, assigned_by) VALUES (?, ?, ?)");
                            $stmt->execute([$new_user_id, (int)$dept_id, $_SESSION['user_id']]);
                        }
                    } catch (Exception $e) {
                        error_log("Employee departments error: " . $e->getMessage());
                    }
                }
                
                // Log activity
                try {
                    $role_str = implode(', ', $form_data['selected_roles']);
                    $stmt = $db->prepare("INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) VALUES (?, ?, 'employee_added', ?, NOW())");
                    $stmt->execute([
                        $_SESSION['user_id'], 
                        $form_data['branch_id'], 
                        "Employee {$form_data['full_name']} added with roles: $role_str (Primary role: $primary_role)"
                    ]);
                } catch (Exception $e) {
                    error_log("Activity log error: " . $e->getMessage());
                }
                
                // ✅ COMMIT
                if ($db->inTransaction()) {
                    $db->commit();
                }
                
                $message = "✅ Employee added successfully!<br>
                            <strong>Primary Role (<code>role</code> column):</strong> <code style='background:#0B5ED7;color:white;padding:2px 8px;border-radius:4px;font-weight:600;'>{$primary_role}</code><br>
                            <strong>All Roles:</strong> " . implode(', ', $form_data['selected_roles']) . "<br>
                            <strong>Password:</strong> <code style='background:#F1F5F9;padding:2px 8px;border-radius:4px;color:#0B5ED7;font-weight:600;'>{$form_data['password']}</code><br>
                            <span style='font-size:0.8rem;color:#059669;'>
                                <i class='fas fa-info-circle'></i> User will be prompted to change password on first login.
                            </span>";
                $message_type = 'success';
                
                echo '<script>setTimeout(function(){ window.location.href = "employees.php?branch=' . $form_data['branch_id'] . '&success=1"; }, 4000);</script>';
                
            } else {
                // ✅ SAFE ROLLBACK
                safeRollback($db);
                $errors[] = 'Failed to add employee. Please try again.';
            }
            
        } catch (Exception $e) {
            // ✅ SAFE ROLLBACK - INAZUIA "no active transaction" ERROR
            safeRollback($db);
            $errors[] = 'Database error: ' . $e->getMessage();
            error_log("Add employee error: " . $e->getMessage());
        }
    }
    
    if (!empty($errors)) {
        $message = implode('<br>', $errors);
        $message_type = 'error';
    }
}

$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC CSS -->
<!-- ================================================================ -->
<style>
    :root {
        --page-primary: #0B5ED7;
        --page-primary-dark: #0A4CA8;
        --page-primary-light: #6EA8FE;
        --page-primary-bg: #E8F0FE;
        --page-bg-body: #F1F5F9;
        --page-bg-card: #FFFFFF;
        --page-text-primary: #1E293B;
        --page-text-secondary: #64748B;
        --page-text-muted: #94A3B8;
        --page-border: #E2E8F0;
        --page-hover: #F8FAFC;
    }

    [data-theme="dark"] {
        --page-bg-body: #0F172A;
        --page-bg-card: #1E293B;
        --page-text-primary: #F1F5F9;
        --page-text-secondary: #94A3B8;
        --page-text-muted: #64748B;
        --page-border: #334155;
        --page-hover: #0F172A;
        --page-primary-bg: #1E3A5F;
    }

    html[data-theme="dark"] body { background: #0F172A !important; }
    html[data-theme="dark"] .main-content { background: #0F172A !important; }

    .page-header-card {
        background: linear-gradient(135deg, #0B5ED7 0%, #0A4FB0 50%, #083D8A 100%);
        border-radius: 20px;
        padding: 28px 32px;
        margin-bottom: 24px;
        color: white;
        box-shadow: 0 8px 32px rgba(11, 94, 215, 0.35), 0 4px 12px rgba(11, 94, 215, 0.2);
        position: relative;
        overflow: hidden;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 20px;
        transition: all 0.3s ease;
    }

    .page-header-card::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 400px;
        height: 400px;
        background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .page-header-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 40px rgba(11, 94, 215, 0.45);
    }

    .page-header-left {
        position: relative;
        z-index: 2;
        flex: 1;
        min-width: 280px;
    }

    .page-header-title {
        font-size: 1.6rem;
        font-weight: 800;
        margin: 0 0 8px 0;
        display: flex;
        align-items: center;
        gap: 12px;
        color: white;
    }

    .page-header-title i {
        width: 44px;
        height: 44px;
        background: rgba(255,255,255,0.2);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        border: 1px solid rgba(255,255,255,0.2);
    }

    .page-header-subtitle {
        font-size: 0.9rem;
        color: rgba(255,255,255,0.9);
        margin: 0 0 16px 0;
    }

    .page-header-stats {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        position: relative;
        z-index: 2;
    }

    .page-header-stat {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 14px;
        background: rgba(255,255,255,0.15);
        border: 1px solid rgba(255,255,255,0.25);
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        color: white;
    }

    .page-header-back-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        background: rgba(255,255,255,0.15);
        border: 1.5px solid rgba(255,255,255,0.3);
        border-radius: 12px;
        color: white;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        position: relative;
        z-index: 2;
        white-space: nowrap;
    }

    .page-header-back-btn:hover {
        background: rgba(255,255,255,0.25);
        transform: translateX(-3px);
        color: white;
    }

    .form-card {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 20px;
        padding: 28px 32px;
        border: 2px solid var(--page-border, #E2E8F0);
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
        border-bottom: 2px solid var(--page-border, #E2E8F0);
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
        color: var(--page-text-primary, #1E293B);
        margin: 0;
    }

    .form-header p {
        font-size: 0.85rem;
        color: var(--page-text-secondary, #64748B);
        margin: 0;
    }

    .form-label {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--page-text-primary, #1E293B);
        margin-bottom: 6px;
        display: block;
    }

    .form-label i { width: 20px; text-align: center; font-size: 0.85rem; }
    .form-label .required { color: #EF4444; margin-left: 2px; }

    .form-control {
        width: 100%;
        padding: 10px 16px;
        border: 2px solid var(--page-border, #E2E8F0);
        border-radius: 12px;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        outline: none;
        background: var(--page-bg-card, #FFFFFF);
        color: var(--page-text-primary, #1E293B);
        font-family: inherit;
    }

    .form-control:focus {
        border-color: #0B5ED7;
        box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.12);
    }

    .form-control::placeholder {
        color: var(--page-text-muted, #94A3B8);
        opacity: 0.7;
    }

    select.form-control { appearance: auto; cursor: pointer; }

    [data-theme="dark"] .form-control option {
        background: #1E293B;
        color: #F1F5F9;
    }

    .password-input-group { position: relative; display: flex; align-items: center; }
    .password-input-group .form-control { padding-right: 50px; }
    .password-input-group .password-toggle {
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
    .password-input-group .password-toggle:hover {
        color: #0B5ED7;
        background: var(--page-hover, #F8FAFC);
    }

    .form-row-icon { position: relative; }
    .form-row-icon .form-control { padding-left: 44px; }
    .form-row-icon .input-icon {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--page-text-secondary, #64748B);
        font-size: 1rem;
        pointer-events: none;
    }

    .btn-generate {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.8rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        background: linear-gradient(135deg, #0B5ED7, #1A73E8);
        color: white;
        white-space: nowrap;
    }

    .btn-generate:hover {
        background: linear-gradient(135deg, #0A4CA8, #1557B0);
        transform: translateY(-2px);
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
        font-family: inherit;
    }

    .btn-primary {
        background: linear-gradient(135deg, #0B5ED7, #1A73E8);
        color: white;
        box-shadow: 0 4px 14px rgba(11, 94, 215, 0.3);
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #0A4CA8, #1557B0);
        transform: translateY(-2px);
        color: white;
    }

    .btn-outline {
        background: transparent;
        color: var(--page-text-primary, #1E293B);
        border: 2px solid var(--page-border, #E2E8F0);
    }

    .btn-outline:hover {
        background: var(--page-hover, #F8FAFC);
        border-color: #0B5ED7;
        color: #0B5ED7;
        transform: translateY(-2px);
    }

    .section-title {
        font-size: 1rem;
        font-weight: 700;
        color: #0B5ED7;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .section-divider {
        border: none;
        border-top: 2px dashed var(--page-border, #E2E8F0);
        margin: 12px 0 16px;
    }

    .help-text {
        font-size: 0.72rem;
        color: var(--page-text-secondary, #64748B);
        margin-top: 4px;
    }

    .badge-count {
        font-size: 0.7rem;
        font-weight: 400;
        color: var(--page-text-secondary, #64748B);
        margin-left: 8px;
    }

    .checkbox-group {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 12px;
        padding: 16px;
        border: 2px solid var(--page-border, #E2E8F0);
        border-radius: 14px;
        background: var(--page-hover, #F8FAFC);
        min-height: 80px;
    }

    .checkbox-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        border-radius: 12px;
        background: var(--page-bg-card, #FFFFFF);
        border: 2px solid var(--page-border, #E2E8F0);
        transition: all 0.3s ease;
        cursor: pointer;
        position: relative;
        overflow: hidden;
    }

    .checkbox-item::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: #0B5ED7;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .checkbox-item:hover,
    .checkbox-item.checked {
        border-color: #0B5ED7;
        background: #E8F0FE;
    }

    .checkbox-item.checked::before { opacity: 1; }

    .checkbox-item.audit-item:hover,
    .checkbox-item.audit-item.checked {
        border-color: #DC2626;
        background: #FEE2E2;
    }

    .checkbox-item.audit-item.checked::before { background: #DC2626; }

    [data-theme="dark"] .checkbox-item:hover,
    [data-theme="dark"] .checkbox-item.checked {
        background: #1E3A5F;
    }

    [data-theme="dark"] .checkbox-item.audit-item:hover,
    [data-theme="dark"] .checkbox-item.audit-item.checked {
        background: #3A1A1A;
    }

    .checkbox-item input[type="checkbox"] {
        width: 20px;
        height: 20px;
        accent-color: #0B5ED7;
        cursor: pointer;
        flex-shrink: 0;
    }

    .checkbox-item.audit-item input[type="checkbox"] { accent-color: #DC2626; }

    .checkbox-item label {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--page-text-primary, #1E293B);
        cursor: pointer;
        width: 100%;
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .checkbox-item .role-desc {
        font-size: 0.68rem;
        color: var(--page-text-secondary, #64748B);
        font-weight: 400;
        opacity: 0.85;
    }

    .role-icon-wrapper {
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

    .role-icon-wrapper.audit { background: #FEE2E2; color: #DC2626; }

    .password-strength { display: flex; gap: 4px; margin-top: 6px; }
    .password-strength .strength-bar {
        height: 4px;
        flex: 1;
        border-radius: 4px;
        background: var(--page-border, #E2E8F0);
        transition: all 0.3s ease;
    }
    .password-strength .strength-bar.weak { background: #EF4444; }
    .password-strength .strength-bar.medium { background: #F59E0B; }
    .password-strength .strength-bar.strong { background: #10B981; }
    .password-strength .strength-bar.very-strong { background: #059669; }
    .password-strength-text {
        font-size: 0.7rem;
        color: var(--page-text-secondary, #64748B);
        margin-top: 4px;
    }

    .role-restriction-note {
        font-size: 0.75rem;
        padding: 10px 14px;
        border-radius: 10px;
        background: #FEF3C7;
        color: #92400E;
        border: 1px solid #FCD34D;
        margin-top: 4px;
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 500;
    }

    [data-theme="dark"] .role-restriction-note {
        background: #3D2E0A;
        color: #FBBF24;
        border-color: #F59E0B;
    }

    .tip-card {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 16px;
        padding: 16px 20px;
        border: 2px solid var(--page-border, #E2E8F0);
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .tip-card:hover {
        border-color: #0B5ED7;
        transform: translateY(-3px);
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

    .tip-card .tip-icon.blue { background: #E8F0FE; color: #0B5ED7; }
    .tip-card .tip-icon.green { background: #E6F7EE; color: #059669; }
    .tip-card .tip-icon.yellow { background: #FEF3C7; color: #F59E0B; }
    .tip-card .tip-icon.purple { background: #F3E8FF; color: #7C3AED; }

    .tip-card .tip-text h4 {
        font-size: 0.85rem;
        font-weight: 700;
        color: var(--page-text-primary, #1E293B);
        margin: 0 0 2px 0;
    }

    .tip-card .tip-text p {
        font-size: 0.75rem;
        color: var(--page-text-secondary, #64748B);
        margin: 0;
    }

    .page-message {
        padding: 16px 22px;
        border-radius: 14px;
        margin-bottom: 20px;
        font-size: 0.9rem;
        font-weight: 500;
        display: flex;
        align-items: flex-start;
        gap: 12px;
        animation: slideDown 0.4s ease;
    }

    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .page-message.success {
        background: #ECFDF5;
        color: #065F46;
        border: 2px solid #A7F3D0;
    }

    .page-message.error {
        background: #FEF2F2;
        color: #991B1B;
        border: 2px solid #FECACA;
    }

    [data-theme="dark"] .page-message.success {
        background: #1A3A2A;
        color: #34D399;
        border-color: #34D399;
    }

    [data-theme="dark"] .page-message.error {
        background: #3A1A1A;
        color: #F87171;
        border-color: #F87171;
    }

    .toast-custom {
        position: fixed;
        bottom: 24px;
        right: 24px;
        padding: 14px 20px;
        border-radius: 14px;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 12px;
        z-index: 9999;
        box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        transform: translateY(120%);
        transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        min-width: 280px;
        max-width: 400px;
    }

    .toast-custom.show { transform: translateY(0); }
    .toast-custom.success { background: #ECFDF5; color: #065F46; border: 2px solid #A7F3D0; }
    .toast-custom.error { background: #FEF2F2; color: #991B1B; border: 2px solid #FECACA; }
    .toast-custom.warning { background: #FFFBEB; color: #92400E; border: 2px solid #FCD34D; }

    @media (max-width: 768px) {
        .page-header-card { padding: 20px; }
        .page-header-title { font-size: 1.2rem; }
        .page-header-title i { width: 36px; height: 36px; font-size: 1rem; }
        .form-card { padding: 18px 16px; }
        .form-header { flex-direction: column; text-align: center; }
        .form-header-icon { width: 48px; height: 48px; font-size: 1.2rem; }
        .btn { padding: 8px 16px; font-size: 0.8rem; min-height: 38px; min-width: 100%; }
        .checkbox-group { grid-template-columns: 1fr; }
        .tip-card { padding: 12px 16px; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <div class="page-header-card">
        <div class="page-header-left">
            <h1 class="page-header-title">
                <i class="fas fa-user-plus"></i>
                Add New Employee
            </h1>
            <p class="page-header-subtitle">
                Create a new employee account with role & department assignments
            </p>
            <div class="page-header-stats">
                <span class="page-header-stat">
                    <i class="fas fa-users"></i> <?= $total_employees ?> Employees
                </span>
                <span class="page-header-stat">
                    <i class="fas fa-user-md"></i> <?= $total_doctors ?> Doctors
                </span>
                <span class="page-header-stat">
                    <i class="fas fa-store"></i> <?= count($branches) ?> Branches
                </span>
                <span class="page-header-stat">
                    <i class="fas fa-shield-alt"></i> <?= count($available_roles) ?> Roles
                </span>
            </div>
        </div>
        <a href="employees.php?branch=<?= $selected_branch_id ?>" class="page-header-back-btn">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>

    <?php if ($message): ?>
        <div class="page-message <?= $message_type === 'success' ? 'success' : 'error' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>" style="font-size:1.3rem;flex-shrink:0;"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <div class="form-card">
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
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
                
                <div style="grid-column:1/-1;">
                    <h3 class="section-title">
                        <i class="fas fa-user-circle"></i> Personal Information
                    </h3>
                    <hr class="section-divider">
                </div>
                
                <div>
                    <label class="form-label">
                        <i class="fas fa-user" style="color:#0B5ED7;"></i> Full Name
                        <span class="required">*</span>
                    </label>
                    <div class="form-row-icon">
                        <input type="text" name="full_name" id="fullName" class="form-control" 
                               placeholder="Enter full name" 
                               value="<?= htmlspecialchars($form_data['full_name']) ?>" required>
                        <span class="input-icon"><i class="fas fa-user"></i></span>
                    </div>
                </div>
                
                <div>
                    <label class="form-label">
                        <i class="fas fa-at" style="color:#0B5ED7;"></i> Username
                        <span class="required">*</span>
                    </label>
                    <div class="form-row-icon">
                        <input type="text" name="username" id="username" class="form-control" 
                               placeholder="Enter username" 
                               value="<?= htmlspecialchars($form_data['username']) ?>" required>
                        <span class="input-icon"><i class="fas fa-at"></i></span>
                    </div>
                </div>
                
                <div>
                    <label class="form-label">
                        <i class="fas fa-envelope" style="color:#059669;"></i> Email
                        <span class="required">*</span>
                    </label>
                    <div class="form-row-icon">
                        <input type="email" name="email" class="form-control" 
                               placeholder="Enter email" 
                               value="<?= htmlspecialchars($form_data['email']) ?>" required>
                        <span class="input-icon"><i class="fas fa-envelope"></i></span>
                    </div>
                </div>
                
                <div>
                    <label class="form-label">
                        <i class="fas fa-phone" style="color:#0B5ED7;"></i> Phone Number
                    </label>
                    <div class="form-row-icon">
                        <input type="text" name="phone" class="form-control" 
                               placeholder="Enter phone number" 
                               value="<?= htmlspecialchars($form_data['phone']) ?>">
                        <span class="input-icon"><i class="fas fa-phone"></i></span>
                    </div>
                </div>
                
                <div>
                    <label class="form-label">
                        <i class="fas fa-store-alt" style="color:#059669;"></i> Branch
                        <span class="required">*</span>
                    </label>
                    <div class="form-row-icon">
                        <select name="branch_id" id="branchSelect" class="form-control" required>
                            <option value="">Select Branch</option>
                            <?php foreach ($branches as $branch): ?>
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
                    <label class="form-label">
                        <i class="fas fa-stethoscope" style="color:#7C3AED;"></i> Specialty
                        <span class="badge-count">(Doctors only)</span>
                    </label>
                    <div class="form-row-icon">
                        <input type="text" name="specialty" class="form-control" 
                               placeholder="e.g. Cardiology, Pediatrics" 
                               value="<?= htmlspecialchars($form_data['specialty']) ?>">
                        <span class="input-icon"><i class="fas fa-stethoscope"></i></span>
                    </div>
                    <p class="help-text">Mainly for doctors, leave empty if not applicable</p>
                </div>
                
                <div style="grid-column:1/-1;">
                    <label class="form-label">
                        <i class="fas fa-key" style="color:#D97706;"></i> Password
                        <span class="required">*</span>
                    </label>
                    <div class="password-input-group">
                        <input type="password" name="password" id="passwordField" class="form-control" 
                               placeholder="Enter password or generate one" 
                               value="<?= htmlspecialchars($form_data['password']) ?>" required>
                        <button type="button" class="password-toggle" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:8px;">
                        <button type="button" class="btn-generate" id="generatePasswordBtn">
                            <i class="fas fa-sync-alt"></i> Generate Password
                        </button>
                        <span class="help-text">Format: Name + Branch + User ID</span>
                    </div>
                    <div class="password-strength" id="passwordStrength">
                        <div class="strength-bar" data-index="0"></div>
                        <div class="strength-bar" data-index="1"></div>
                        <div class="strength-bar" data-index="2"></div>
                        <div class="strength-bar" data-index="3"></div>
                    </div>
                    <div class="password-strength-text" id="passwordStrengthText">Enter a password to check strength</div>
                </div>
                
                <!-- ROLES -->
                <div style="grid-column:1/-1;margin-top:8px;">
                    <h3 class="section-title">
                        <i class="fas fa-user-tag"></i> Select Roles
                        <span class="required">*</span>
                        <span class="badge-count">(Max 2 roles, one must be Reception)</span>
                    </h3>
                    <p class="help-text" style="margin-bottom:8px;">
                        Role ya kwanza unayochagua itakuwa <strong>Primary Role</strong> (inaingia kwenye <code>role</code> column).
                        <br><strong style="color:#DC2626;">Priority:</strong> Kama mtu ni <code>audit</code>, role yake itakuwa <code>audit</code>.
                    </p>
                    
                    <div class="role-restriction-note">
                        <i class="fas fa-info-circle"></i>
                        <span><strong>Note:</strong> Maximum of <strong>2 roles</strong>. If selecting 2, <strong>one must be Reception</strong>.</span>
                    </div>
                    
                    <hr class="section-divider">
                    
                    <div class="checkbox-group" id="rolesContainer">
                        <?php foreach ($available_roles as $role): ?>
                            <div class="checkbox-item <?= $role['name'] === 'audit' ? 'audit-item' : '' ?>" onclick="toggleCheckbox(this)">
                                <input type="checkbox" name="roles[]" value="<?= $role['name'] ?>" 
                                       id="role_<?= $role['name'] ?>"
                                       <?= in_array($role['name'], $form_data['selected_roles']) ? 'checked' : '' ?>>
                                <div class="role-icon-wrapper <?= $role['name'] === 'audit' ? 'audit' : '' ?>" 
                                     style="<?= $role['name'] !== 'audit' ? 'background:' . $role['color'] . '15;color:' . $role['color'] . ';' : '' ?>">
                                    <i class="fas <?= $role['icon'] ?>"></i>
                                </div>
                                <label for="role_<?= $role['name'] ?>">
                                    <?= ucfirst($role['label']) ?>
                                    <span class="role-desc"><?= $role['desc'] ?></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="help-text" style="margin-top:8px;">Selected: <strong id="selectedRoleCount">0</strong> roles</p>
                    
                    <div id="roleErrorContainer"></div>
                </div>
                
                <!-- DEPARTMENTS -->
                <div style="grid-column:1/-1;margin-top:8px;">
                    <h3 class="section-title">
                        <i class="fas fa-building"></i> Select Departments
                        <span class="badge-count">(<?= count($departments) ?> available)</span>
                    </h3>
                    <hr class="section-divider">
                    
                    <div class="checkbox-group" id="departmentsContainer">
                        <?php if (!empty($departments)): ?>
                            <?php foreach ($departments as $dept): 
                                $is_audit = (strtolower($dept['category_name']) === 'audit');
                            ?>
                                <div class="checkbox-item <?= $is_audit ? 'audit-item' : '' ?>" onclick="toggleCheckbox(this)">
                                    <input type="checkbox" name="departments[]" value="<?= $dept['id'] ?>" 
                                           id="dept_<?= $dept['id'] ?>"
                                           <?= in_array($dept['id'], $form_data['selected_departments']) ? 'checked' : '' ?>>
                                    <div class="role-icon-wrapper <?= $is_audit ? 'audit' : '' ?>"
                                         style="<?= !$is_audit ? 'background:' . ($dept['color'] ?? '#0B5ED7') . '15;color:' . ($dept['color'] ?? '#0B5ED7') . ';' : '' ?>">
                                        <i class="fas <?= $dept['icon'] ?? 'fa-building' ?>"></i>
                                    </div>
                                    <label for="dept_<?= $dept['id'] ?>">
                                        <?= htmlspecialchars($dept['category_name']) ?>
                                        <?php if (!empty($dept['description'])): ?>
                                            <span class="role-desc"><?= htmlspecialchars($dept['description']) ?></span>
                                        <?php endif; ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="grid-column:1/-1;text-align:center;color:var(--page-text-secondary);padding:12px;">
                                <i class="fas fa-info-circle"></i> No departments available.
                            </p>
                        <?php endif; ?>
                    </div>
                    <p class="help-text" style="margin-top:8px;">Selected: <strong id="selectedDeptCount">0</strong> departments</p>
                </div>
                
            </div>
            
            <div style="display:flex;flex-wrap:wrap;gap:12px;padding-top:24px;margin-top:24px;border-top:2px solid var(--page-border);">
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

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-top:20px;">
        <div class="tip-card">
            <div class="tip-icon blue"><i class="fas fa-lightbulb"></i></div>
            <div class="tip-text">
                <h4>Tip #1</h4>
                <p>Select at least one role</p>
            </div>
        </div>
        <div class="tip-card">
            <div class="tip-icon green"><i class="fas fa-check-circle"></i></div>
            <div class="tip-text">
                <h4>Tip #2</h4>
                <p>Max 2 roles, one must be Reception</p>
            </div>
        </div>
        <div class="tip-card">
            <div class="tip-icon yellow"><i class="fas fa-key"></i></div>
            <div class="tip-text">
                <h4>Tip #3</h4>
                <p>Click Generate for strong password</p>
            </div>
        </div>
        <div class="tip-card">
            <div class="tip-icon purple"><i class="fas fa-eye"></i></div>
            <div class="tip-text">
                <h4>Tip #4</h4>
                <p>Click 👁️ to view password</p>
            </div>
        </div>
    </div>

</main>

<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<script>
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
            validateRoles();
        }
    }

    function updateCounts() {
        var rolesChecked = document.querySelectorAll('input[name="roles[]"]:checked');
        var roleCount = document.getElementById('selectedRoleCount');
        if (roleCount) roleCount.textContent = rolesChecked.length;
        
        var deptsChecked = document.querySelectorAll('input[name="departments[]"]:checked');
        var deptCount = document.getElementById('selectedDeptCount');
        if (deptCount) deptCount.textContent = deptsChecked.length;
    }

    function validateRoles() {
        var rolesChecked = document.querySelectorAll('input[name="roles[]"]:checked');
        var container = document.getElementById('rolesContainer');
        var errorContainer = document.getElementById('roleErrorContainer');
        
        errorContainer.innerHTML = '';
        container.style.borderColor = '';
        
        if (rolesChecked.length > 2) {
            var lastChecked = rolesChecked[rolesChecked.length - 1];
            lastChecked.checked = false;
            lastChecked.closest('.checkbox-item').classList.remove('checked');
            
            errorContainer.innerHTML = '<p class="help-text" style="color:#EF4444;margin-top:8px;"><i class="fas fa-exclamation-circle"></i> Maximum of <strong>2 roles</strong> allowed.</p>';
            container.style.borderColor = '#EF4444';
            showToast('⚠️ Warning', 'Maximum of 2 roles allowed', 'warning');
            updateCounts();
            return false;
        }
        
        if (rolesChecked.length == 2) {
            var hasReception = false;
            rolesChecked.forEach(function(cb) {
                if (cb.value === 'reception') hasReception = true;
            });
            
            if (!hasReception) {
                var lastChecked = rolesChecked[rolesChecked.length - 1];
                lastChecked.checked = false;
                lastChecked.closest('.checkbox-item').classList.remove('checked');
                
                errorContainer.innerHTML = '<p class="help-text" style="color:#EF4444;margin-top:8px;"><i class="fas fa-exclamation-circle"></i> If selecting 2 roles, one must be Reception.</p>';
                container.style.borderColor = '#EF4444';
                showToast('⚠️ Warning', 'If selecting 2 roles, one must be Reception', 'warning');
                updateCounts();
                return false;
            }
        }
        
        return true;
    }

    document.addEventListener('DOMContentLoaded', function() {
        var checkboxes = document.querySelectorAll('.checkbox-item input[type="checkbox"]');
        checkboxes.forEach(function(checkbox) {
            if (checkbox.checked) checkbox.closest('.checkbox-item').classList.add('checked');
        });
        updateCounts();
    });

    document.getElementById('addEmployeeForm')?.addEventListener('submit', function(e) {
        var rolesChecked = document.querySelectorAll('input[name="roles[]"]:checked');
        var container = document.getElementById('rolesContainer');
        var errorContainer = document.getElementById('roleErrorContainer');
        
        errorContainer.innerHTML = '';
        container.style.borderColor = '';
        
        if (rolesChecked.length === 0) {
            e.preventDefault();
            errorContainer.innerHTML = '<p class="help-text" style="color:#EF4444;margin-top:8px;"><i class="fas fa-exclamation-circle"></i> Please select at least <strong>one role</strong>.</p>';
            container.style.borderColor = '#EF4444';
            showToast('⚠️ Warning', 'Please select at least one role.', 'warning');
            return false;
        }
        
        if (!validateRoles()) {
            e.preventDefault();
            return false;
        }
        
        return true;
    });

    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        toast.className = 'toast-custom ' + type;
        document.getElementById('toastTitle').textContent = title;
        document.getElementById('toastMessage').textContent = message;
        toast.style.display = 'flex';
        void toast.offsetWidth;
        toast.classList.add('show');
        
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 3500);
    }

    document.getElementById('togglePassword')?.addEventListener('click', function() {
        var passwordField = document.getElementById('passwordField');
        var icon = this.querySelector('i');
        if (passwordField.type === 'password') {
            passwordField.type = 'text';
            icon.className = 'fas fa-eye-slash';
        } else {
            passwordField.type = 'password';
            icon.className = 'fas fa-eye';
        }
    });

    document.getElementById('passwordField')?.addEventListener('input', function() {
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
        var bars = document.querySelectorAll('.strength-bar');
        var text = document.getElementById('passwordStrengthText');
        bars.forEach(function(bar, i) {
            bar.className = 'strength-bar';
            if (i < strength.score) bar.classList.add(strength.class);
        });
        if (strength.score === 0) {
            text.textContent = 'Enter a password to check strength';
        } else {
            text.textContent = 'Strength: ' + strength.label;
            text.style.color = strength.class === 'weak' ? '#EF4444' : 
                               strength.class === 'medium' ? '#F59E0B' : 
                               strength.class === 'strong' ? '#10B981' : '#059669';
        }
    }

    document.getElementById('generatePasswordBtn')?.addEventListener('click', function() {
        var fullName = document.getElementById('fullName').value.trim();
        var branchId = document.getElementById('branchSelect').value;
        
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
        
        var btn = this;
        var originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
        btn.disabled = true;
        
        var formData = new FormData();
        formData.append('action', 'generate_password');
        formData.append('full_name', fullName);
        formData.append('branch_id', branchId);
        formData.append('user_id', 0);
        
        fetch(window.location.href, { method: 'POST', body: formData })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                var passwordField = document.getElementById('passwordField');
                passwordField.value = data.password;
                showToast('✅ Success', 'Password generated successfully!', 'success');
                
                var event = new Event('input', { bubbles: true });
                passwordField.dispatchEvent(event);
                
                passwordField.type = 'text';
                var icon = document.querySelector('#togglePassword i');
                if (icon) icon.className = 'fas fa-eye-slash';
                
                setTimeout(function() {
                    passwordField.type = 'password';
                    if (icon) icon.className = 'fas fa-eye';
                }, 3000);
            } else {
                showToast('❌ Error', data.error || 'Failed to generate password', 'error');
            }
        })
        .catch(function(error) {
            showToast('❌ Error', 'Network error: ' + error.message, 'error');
        })
        .finally(function() {
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    });

    console.log('%c👤 Braick - Add Employee (FIXED)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ FIXED: "There is no active transaction" error', 'font-size:13px; color:#059669;');
    console.log('%c✅ FIXED: Audit role inahifadhiwa kwenye users.role', 'font-size:13px; color:#DC2626;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>