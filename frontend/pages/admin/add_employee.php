<?php
// ================================================================
// FILE: frontend/pages/admin/add_employee.php
// SUPER ADMIN - ADD EMPLOYEE
// BRAICK DISPENSARY - WITH PROPER PASSWORD HANDLING
// ================================================================

// ================================================================
// START SESSION
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION
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
// BRANCH SELECTION FOR SIDEBAR
// ================================================================
$selected_branch_id = $_GET['branch'] ?? 'all';

// ================================================================
// GET STATISTICS FOR SIDEBAR BADGES
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
$stmt = $db->query("SELECT id, name, location FROM branches WHERE status = 'active' ORDER BY name");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $branches[] = $row;
}

// ================================================================
// DEFINED ROLES
// ================================================================
$available_roles = [
    ['name' => 'doctor', 'label' => 'Doctor', 'icon' => 'fa-user-md', 'color' => '#0B5ED7'],
    ['name' => 'reception', 'label' => 'Reception', 'icon' => 'fa-user-tie', 'color' => '#059669'],
    ['name' => 'pharmacy', 'label' => 'Pharmacy', 'icon' => 'fa-pills', 'color' => '#D97706'],
    ['name' => 'laboratory', 'label' => 'Laboratory', 'icon' => 'fa-microscope', 'color' => '#7C3AED'],
    ['name' => 'cashier', 'label' => 'Cashier', 'icon' => 'fa-cash-register', 'color' => '#0D9488']
];

// ================================================================
// GET EXISTING DEPARTMENTS
// ================================================================
$departments = [];
$stmt = $db->query("SELECT id, category_name, description, icon, color FROM service_categories WHERE is_active = 1 ORDER BY category_name");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $departments[] = $row;
}

// ================================================================
// AUTO-GENERATE PASSWORD FUNCTION - WITH BRANCH CODE
// ================================================================
function generatePassword($full_name, $branch_id, $user_id = null) {
    // Get first 4 letters of full name
    $clean_name = preg_replace('/[^a-zA-Z]/', '', $full_name);
    $name_part = strtoupper(substr($clean_name, 0, 4));
    if (strlen($name_part) < 3) {
        $name_part = 'USER';
    }
    
    // Get branch code (BR + 2-digit branch ID)
    $branch_code = 'BR' . str_pad($branch_id, 2, '0', STR_PAD_LEFT);
    
    // Get user ID part (UID + 4-digit user ID or random)
    if ($user_id) {
        $user_code = 'UID' . str_pad($user_id, 4, '0', STR_PAD_LEFT);
    } else {
        $user_code = 'UID' . rand(1000, 9999);
    }
    
    // Combine: NAME + BRANCH + USER_ID
    $password = $name_part . $branch_code . $user_code;
    
    // If password is too short, add random numbers
    if (strlen($password) < 8) {
        $password .= rand(100, 999);
    }
    
    return $password;
}

// ================================================================
// HANDLE AJAX REQUEST FOR AUTO-GENERATE PASSWORD
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_password') {
    header('Content-Type: application/json');
    
    $full_name = $_POST['full_name'] ?? '';
    $branch_id = (int)($_POST['branch_id'] ?? 0);
    $user_id = (int)($_POST['user_id'] ?? 0);
    $username = $_POST['username'] ?? '';
    
    if (empty($full_name) || $branch_id <= 0) {
        echo json_encode(['success' => false, 'password' => '', 'error' => 'Name and branch required']);
        exit;
    }
    
    // If user_id is 0, generate a random one for preview
    if ($user_id <= 0) {
        $user_id = rand(1000, 9999);
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
    // Get form data
    $form_data['full_name'] = trim($_POST['full_name'] ?? '');
    $form_data['username'] = trim($_POST['username'] ?? '');
    $form_data['email'] = trim($_POST['email'] ?? '');
    $form_data['phone'] = trim($_POST['phone'] ?? '');
    $form_data['password'] = $_POST['password'] ?? '';
    $form_data['branch_id'] = (int)($_POST['branch_id'] ?? 0);
    $form_data['specialty'] = trim($_POST['specialty'] ?? '');
    
    // Get selected roles (checkboxes)
    $form_data['selected_roles'] = $_POST['roles'] ?? [];
    $form_data['selected_departments'] = $_POST['departments'] ?? [];
    
    // PRIMARY ROLE (first selected role, or default to 'doctor')
    $primary_role = !empty($form_data['selected_roles']) ? $form_data['selected_roles'][0] : 'doctor';
    
    // Validate role is valid
    $valid_roles = ['admin', 'reception', 'doctor', 'laboratory', 'pharmacy', 'cashier'];
    if (!in_array($primary_role, $valid_roles)) {
        $primary_role = 'doctor';
    }
    
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
    if (empty($form_data['password'])) {
        $errors[] = 'Password is required';
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
        
        // Insert into users table - with password_changed_at and is_default_password
        $stmt = $db->prepare("
            INSERT INTO users 
            (username, password, password_changed_at, is_default_password, full_name, email, phone, role, branch_id, specialty, status, created_at) 
            VALUES (?, ?, NOW(), 1, ?, ?, ?, ?, ?, ?, 'active', NOW())
        ");
        
        if ($stmt->execute([
            $form_data['username'], 
            $hashed_password, 
            $form_data['full_name'], 
            $form_data['email'], 
            $form_data['phone'], 
            $primary_role,
            $form_data['branch_id'],
            $form_data['specialty']
        ])) {
            $new_user_id = $db->lastInsertId();
            
            // Store multiple roles
            try {
                $db->exec("
                    CREATE TABLE IF NOT EXISTS employee_roles (
                        id INT(11) AUTO_INCREMENT PRIMARY KEY,
                        user_id INT(11) NOT NULL,
                        role_name VARCHAR(50) NOT NULL,
                        assigned_by INT(11),
                        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                        FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
                    )
                ");
                
                foreach ($form_data['selected_roles'] as $role_name) {
                    $stmt = $db->prepare("INSERT INTO employee_roles (user_id, role_name, assigned_by) VALUES (?, ?, ?)");
                    $stmt->execute([$new_user_id, $role_name, $_SESSION['user_id']]);
                }
            } catch (Exception $e) {
                error_log("Employee roles table error: " . $e->getMessage());
            }
            
            // Store departments
            if (!empty($form_data['selected_departments'])) {
                try {
                    $db->exec("
                        CREATE TABLE IF NOT EXISTS employee_departments (
                            id INT(11) AUTO_INCREMENT PRIMARY KEY,
                            user_id INT(11) NOT NULL,
                            department_id INT(11) NOT NULL,
                            assigned_by INT(11),
                            assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                            FOREIGN KEY (department_id) REFERENCES service_categories(id) ON DELETE CASCADE,
                            FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
                        )
                    ");
                    
                    foreach ($form_data['selected_departments'] as $dept_id) {
                        $stmt = $db->prepare("INSERT INTO employee_departments (user_id, department_id, assigned_by) VALUES (?, ?, ?)");
                        $stmt->execute([$new_user_id, $dept_id, $_SESSION['user_id']]);
                    }
                } catch (Exception $e) {
                    error_log("Employee departments table error: " . $e->getMessage());
                }
            }
            
            // Log activity
            try {
                $role_str = implode(', ', $form_data['selected_roles']);
                $stmt = $db->prepare("INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) VALUES (?, ?, 'employee_added', ?, NOW())");
                $stmt->execute([$_SESSION['user_id'], $form_data['branch_id'], "Employee {$form_data['full_name']} added with roles: $role_str"]);
            } catch (Exception $e) {}
            
            $message = "Employee added successfully!<br>Roles: <strong>" . implode('</strong>, <strong>', $form_data['selected_roles']) . "</strong><br>Password: <strong>{$form_data['password']}</strong><br><span style='font-size:0.8rem;color:#059669;'><i class='fas fa-info-circle'></i> User will be prompted to change password on first login.</span>";
            $message_type = 'success';
            
            // Redirect to employees list
            echo '<script>setTimeout(function(){ window.location.href = "employees.php?branch=' . $form_data['branch_id'] . '&success=1"; }, 3000);</script>';
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
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

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
    /* ================================================================ */
    /* ADDITIONAL FORM STYLES */
    /* ================================================================ */
    
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
    
    .password-input-group {
        position: relative;
        display: flex;
        align-items: center;
    }
    
    .password-input-group .form-control {
        padding-right: 50px;
    }
    
    .password-input-group .password-toggle {
        position: absolute;
        right: 12px;
        background: none;
        border: none;
        color: var(--text-secondary);
        cursor: pointer;
        padding: 6px 8px;
        font-size: 1rem;
        transition: all 0.3s ease;
        border-radius: 8px;
    }
    
    .password-input-group .password-toggle:hover {
        color: #0B5ED7;
        background: var(--bg-body);
    }
    
    .password-input-group .password-toggle i {
        pointer-events: none;
    }
    
    .btn-generate {
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
        background: #0B5ED7;
        color: white;
        white-space: nowrap;
        min-height: 34px;
        box-shadow: 0 2px 8px rgba(11, 94, 215, 0.25);
    }
    
    .btn-generate:hover {
        background: #0A4CA8;
        transform: translateY(-2px);
        box-shadow: 0 4px 14px rgba(11, 94, 215, 0.35);
    }
    
    .btn-generate:active {
        transform: translateY(0px);
    }
    
    .btn-generate i {
        font-size: 0.8rem;
    }
    
    .password-actions {
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
        margin-top: 4px;
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
    
    .role-badge-doctor { border-color: #0B5ED7; }
    .role-badge-reception { border-color: #059669; }
    .role-badge-pharmacy { border-color: #D97706; }
    .role-badge-laboratory { border-color: #7C3AED; }
    .role-badge-cashier { border-color: #0D9488; }
    
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
    
    .tip-card .tip-icon.blue { background: #E8F0FE; color: #0B5ED7; }
    .tip-card .tip-icon.green { background: #E6F7EE; color: #059669; }
    .tip-card .tip-icon.yellow { background: #FEF3C7; color: #F59E0B; }
    .tip-card .tip-icon.purple { background: #F3E8FF; color: #7C3AED; }
    
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
    
    [data-theme="dark"] .tip-card .tip-icon.blue { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .tip-card .tip-icon.green { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .tip-card .tip-icon.yellow { background: #3A2A1A; color: #FBBF24; }
    [data-theme="dark"] .tip-card .tip-icon.purple { background: #2A1A3A; color: #9B4DCA; }
    
    /* Password Strength Indicator */
    .password-strength {
        display: flex;
        gap: 4px;
        margin-top: 4px;
    }
    
    .password-strength .strength-bar {
        height: 4px;
        flex: 1;
        border-radius: 4px;
        background: var(--border-color);
        transition: all 0.3s ease;
    }
    
    .password-strength .strength-bar.weak { background: #EF4444; }
    .password-strength .strength-bar.medium { background: #F59E0B; }
    .password-strength .strength-bar.strong { background: #10B981; }
    .password-strength .strength-bar.very-strong { background: #059669; }
    
    .password-strength-text {
        font-size: 0.7rem;
        color: var(--text-secondary);
        margin-top: 2px;
    }
    
    @media (max-width: 640px) {
        .form-card { padding: 18px 16px; }
        .form-header { flex-direction: column; text-align: center; }
        .form-header-icon { width: 48px; height: 48px; font-size: 1.2rem; }
        .btn { padding: 8px 16px; font-size: 0.8rem; min-height: 38px; min-width: 100%; }
        .form-actions { flex-direction: column; }
        .form-actions .btn { width: 100%; justify-content: center; }
        .checkbox-group { grid-template-columns: 1fr; }
        .tip-card { padding: 12px 16px; }
        .password-actions { flex-direction: column; align-items: stretch; }
        .btn-generate { width: 100%; justify-content: center; }
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
                <i class="fas fa-user-plus mr-2" style="color: #0B5ED7;"></i> Add New Employee
            </h1>
            <p class="page-subtitle">
                Create a new employee account with role assignments
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-users mr-1"></i> <?= $total_employees ?> employees
                </span>
                <span class="ml-2 inline-flex bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs border border-green-200">
                    <i class="fas fa-user-md mr-1"></i> <?= $total_doctors ?> doctors
                </span>
                <span class="ml-2 inline-flex bg-purple-100 text-purple-700 px-3 py-1 rounded-full text-xs border border-purple-200">
                    <i class="fas fa-store mr-1"></i> <?= count($branches) ?> branches
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
                
                <!-- Specialty -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-stethoscope text-purple-600"></i> Specialty
                    </label>
                    <div class="form-row-icon">
                        <input type="text" name="specialty" class="form-control" 
                               placeholder="e.g. Cardiology, Pediatrics, etc." 
                               value="<?= htmlspecialchars($form_data['specialty']) ?>">
                        <span class="input-icon"><i class="fas fa-stethoscope"></i></span>
                    </div>
                    <p class="help-text">Mainly for doctors, leave empty if not applicable</p>
                </div>
                
                <!-- Branch -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-store-alt text-green-600"></i> Branch
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
                
                <!-- Password with Auto-Generate -->
                <div class="md:col-span-2">
                    <label class="form-label">
                        <i class="fas fa-key text-yellow-600"></i> Password
                        <span class="required">*</span>
                    </label>
                    <div class="password-input-group">
                        <input type="password" name="password" id="passwordField" class="form-control" 
                               placeholder="Enter password or generate one" 
                               value="<?= htmlspecialchars($form_data['password']) ?>" required>
                        <button type="button" class="password-toggle" id="togglePassword" title="Show/Hide Password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-actions">
                        <button type="button" class="btn-generate" id="generatePasswordBtn">
                            <i class="fas fa-sync-alt"></i> Generate Password
                        </button>
                        <span class="help-text">Format: Name + Branch + User ID</span>
                    </div>
                    <!-- Password Strength Indicator -->
                    <div class="password-strength" id="passwordStrength">
                        <div class="strength-bar" data-index="0"></div>
                        <div class="strength-bar" data-index="1"></div>
                        <div class="strength-bar" data-index="2"></div>
                        <div class="strength-bar" data-index="3"></div>
                    </div>
                    <div class="password-strength-text" id="passwordStrengthText">Enter a password to check strength</div>
                </div>
                
                <!-- ================================================================ -->
                <!-- Roles Selection -->
                <!-- ================================================================ -->
                <div class="md:col-span-2 mt-2">
                    <h3 class="section-title">
                        <i class="fas fa-user-tag"></i> Select Roles
                        <span class="required">*</span>
                        <span class="badge-count">(<?= count($available_roles) ?> available)</span>
                    </h3>
                    <p class="help-text mb-2">Click on a role to select/deselect it. At least one role is required.</p>
                    <hr class="section-divider">
                    
                    <div class="checkbox-group" id="rolesContainer">
                        <?php foreach ($available_roles as $role): ?>
                            <div class="checkbox-item role-badge-<?= $role['name'] ?>" onclick="toggleCheckbox(this)">
                                <input type="checkbox" name="roles[]" value="<?= $role['name'] ?>" 
                                       id="role_<?= $role['name'] ?>"
                                       <?= in_array($role['name'], $form_data['selected_roles']) ? 'checked' : '' ?>>
                                <label for="role_<?= $role['name'] ?>">
                                    <i class="fas <?= $role['icon'] ?>" style="color: <?= $role['color'] ?>;"></i>
                                    <?= ucfirst($role['label']) ?>
                                    <span class="role-desc"><?= ucfirst($role['name']) ?></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
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
                                        <i class="fas <?= $dept['icon'] ?? 'fa-building' ?>" style="color: <?= $dept['color'] ?? '#0B5ED7' ?>;"></i>
                                        <?= htmlspecialchars($dept['category_name']) ?>
                                        <?php if (!empty($dept['description'])): ?>
                                            <span class="role-desc"><?= htmlspecialchars($dept['description']) ?></span>
                                        <?php endif; ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-gray-400 text-sm col-span-full text-center">
                                <i class="fas fa-info-circle mr-1"></i> 
                                No departments available. Please add departments first via 
                                <a href="departments.php" class="text-blue-600 underline">Departments</a> page.
                            </p>
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
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-5">
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
                <p>Multiple roles allowed</p>
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

    // ================================================================
    // PASSWORD TOGGLE SHOW/HIDE
    // ================================================================
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

    // ================================================================
    // PASSWORD STRENGTH INDICATOR
    // ================================================================
    document.getElementById('passwordField')?.addEventListener('input', function() {
        var password = this.value;
        var strength = checkPasswordStrength(password);
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
        var scoreIndex = Math.min(score, 4);
        
        return { score: scoreIndex, label: labels[scoreIndex], class: classes[scoreIndex] };
    }

    function updatePasswordStrength(strength) {
        var bars = document.querySelectorAll('.strength-bar');
        var text = document.getElementById('passwordStrengthText');
        
        bars.forEach(function(bar, index) {
            bar.className = 'strength-bar';
            if (index < strength.score) {
                bar.classList.add(strength.class);
            }
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

    // ================================================================
    // GENERATE PASSWORD BUTTON
    // ================================================================
    document.getElementById('generatePasswordBtn')?.addEventListener('click', function() {
        var fullName = document.getElementById('fullName').value.trim();
        var branchId = document.getElementById('branchSelect').value;
        var username = document.getElementById('username').value.trim();
        
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
        var btn = this;
        var originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
        btn.disabled = true;
        
        // AJAX request to generate password
        var formData = new FormData();
        formData.append('action', 'generate_password');
        formData.append('full_name', fullName);
        formData.append('branch_id', branchId);
        formData.append('user_id', 0);
        formData.append('username', username);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                var passwordField = document.getElementById('passwordField');
                passwordField.value = data.password;
                showToast('✅ Success', 'Password generated successfully!', 'success');
                
                // Trigger password strength check
                var event = new Event('input', { bubbles: true });
                passwordField.dispatchEvent(event);
                
                // Show password briefly
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

    console.log('%c👤 Braick - Add Employee', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c✅ Table: users (role is ENUM)', 'font-size:13px; color:#0B5ED7;');
    console.log('%c✅ Department: service_categories', 'font-size:13px; color:#7C3AED;');
    console.log('%c🔑 Password format: NAME + BRCODE + UID + ID', 'font-size:13px; color:#D97706;');
    console.log('%c🔒 is_default_password = 1 for new users', 'font-size:13px; color:#059669;');
    console.log('%c🔄 password_changed_at = NOW() on creation', 'font-size:13px; color:#059669;');
    console.log('%c👁️ Password toggle show/hide', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>