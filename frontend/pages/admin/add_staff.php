<?php
// ================================================================
// FILE: frontend/pages/admin/add_staff.php
// SUPER ADMIN - ADD STAFF
// ✅ Uses SHARED header & sidebar
// ✅ Added Audit role
// ✅ Added Department selection
// ✅ Full dark mode support
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
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'audit': header('Location: ../audit/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

$branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 0;
$branch_name = '';

require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// GET BRANCH INFO
if ($branch_id > 0) {
    $stmt = $db->prepare("SELECT name, location FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$branch_id]);
    $branch_info = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_info) $branch_name = $branch_info['name'];
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

$selected_branch_id = $branch_id > 0 ? $branch_id : ($_GET['branch'] ?? 'all');

// ================================================================
// STATISTICS FOR SIDEBAR
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
} catch (Exception $e) { $pending_lab_tests = 0; }

$pending_prescriptions = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM prescriptions WHERE status = 'pending'");
    $pending_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) { $pending_prescriptions = 0; }

// ================================================================
// GET BRANCHES
// ================================================================
$branches = [];
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $branches[] = $row;
}

// ================================================================
// ✅ AVAILABLE ROLES - WITH AUDIT
// ================================================================
$available_roles = [
    'doctor'     => ['label' => 'Medical Doctor',   'icon' => 'fa-user-md',        'color' => '#0B5ED7'],
    'pharmacy'   => ['label' => 'Pharmacy Staff',   'icon' => 'fa-pills',          'color' => '#D97706'],
    'reception'  => ['label' => 'Receptionist',     'icon' => 'fa-user-tie',       'color' => '#059669'],
    'laboratory' => ['label' => 'Lab Technician',   'icon' => 'fa-microscope',     'color' => '#7C3AED'],
    'cashier'    => ['label' => 'Cashier',          'icon' => 'fa-cash-register',  'color' => '#0D9488'],
    'audit'      => ['label' => 'Audit Officer',    'icon' => 'fa-clipboard-check','color' => '#DC2626'],
    'admin'      => ['label' => 'Administrator',    'icon' => 'fa-user-shield',    'color' => '#1E293B']
];

// ================================================================
// ✅ GET DEPARTMENTS
// ================================================================
$departments = [];
try {
    $stmt = $db->query("SELECT id, category_name, description, icon, color FROM service_categories WHERE is_active = 1 ORDER BY category_name");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $departments[] = $row;
    }
} catch (Exception $e) { $departments = []; }

// ================================================================
// AUTO-GENERATE PASSWORD FUNCTION
// ================================================================
function generatePassword($full_name, $branch_id, $user_id) {
    $clean_name = preg_replace('/[^a-zA-Z]/', '', $full_name);
    $name_part = strtoupper(substr($clean_name, 0, 4));
    if (strlen($name_part) < 3) $name_part = 'USER';

    $branch_code = 'BR' . str_pad($branch_id, 2, '0', STR_PAD_LEFT);
    $user_code = 'UID' . str_pad($user_id, 4, '0', STR_PAD_LEFT);

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
// FORM SUBMISSION
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
    'branch_id' => $branch_id > 0 ? $branch_id : 1,
    'role' => 'doctor',
    'specialty' => '',
    'selected_departments' => []
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $form_data['full_name'] = trim($_POST['full_name'] ?? '');
    $form_data['username'] = trim($_POST['username'] ?? '');
    $form_data['email'] = trim($_POST['email'] ?? '');
    $form_data['phone'] = trim($_POST['phone'] ?? '');
    $form_data['password'] = $_POST['password'] ?? '';
    $form_data['branch_id'] = (int)($_POST['branch_id'] ?? 0);
    $form_data['role'] = $_POST['role'] ?? 'doctor';
    $form_data['specialty'] = trim($_POST['specialty'] ?? '');
    $form_data['selected_departments'] = $_POST['departments'] ?? [];

    if (empty($form_data['full_name'])) $errors[] = 'Full name is required';
    if (empty($form_data['username'])) $errors[] = 'Username is required';
    if (empty($form_data['email'])) $errors[] = 'Email is required';
    if (empty($form_data['password'])) $errors[] = 'Password is required';
    if (empty($form_data['role'])) $errors[] = 'Role is required';
    if ($form_data['branch_id'] <= 0) $errors[] = 'Branch is required';

    if (!empty($form_data['email']) && !filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address';
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
        $hashed_password = password_hash($form_data['password'], PASSWORD_DEFAULT);

        try {
            // Auto-create Audit role in roles table if missing
            try {
                $db->exec("INSERT IGNORE INTO roles (name, description) VALUES ('audit', 'Audit - System audit and compliance')");
            } catch (Exception $e) {}

            // Auto-create Audit department if missing
            try {
                $stmt = $db->prepare("SELECT id FROM service_categories WHERE LOWER(category_name) = 'audit'");
                $stmt->execute();
                if (!$stmt->fetch()) {
                    $db->exec("INSERT INTO service_categories (category_name, description, icon, color, is_active) 
                               VALUES ('Audit', 'System audit & compliance', 'fa-clipboard-check', '#DC2626', 1)");
                }
            } catch (Exception $e) {}

            $stmt = $db->prepare("
                INSERT INTO users (username, password, password_changed_at, is_default_password, full_name, email, phone, role, branch_id, specialty, status, created_at, updated_at) 
                VALUES (?, ?, NOW(), 1, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())
            ");

            $stmt->execute([
                $form_data['username'],
                $hashed_password,
                $form_data['full_name'],
                $form_data['email'],
                $form_data['phone'],
                $form_data['role'],
                $form_data['branch_id'],
                $form_data['specialty']
            ]);

            $new_user_id = $db->lastInsertId();

            // Store departments
            if (!empty($form_data['selected_departments'])) {
                try {
                    $db->exec("CREATE TABLE IF NOT EXISTS employee_departments (
                        id INT(11) AUTO_INCREMENT PRIMARY KEY,
                        user_id INT(11) NOT NULL,
                        department_id INT(11) NOT NULL,
                        assigned_by INT(11),
                        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                        FOREIGN KEY (department_id) REFERENCES service_categories(id) ON DELETE CASCADE,
                        FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
                    )");

                    // Get Audit department ID if virtual was selected
                    $audit_dept_id = null;
                    $stmt = $db->prepare("SELECT id FROM service_categories WHERE LOWER(category_name) = 'audit'");
                    $stmt->execute();
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($row) $audit_dept_id = $row['id'];

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

            // Store role in employee_roles
            try {
                $db->exec("CREATE TABLE IF NOT EXISTS employee_roles (
                    id INT(11) AUTO_INCREMENT PRIMARY KEY,
                    user_id INT(11) NOT NULL,
                    role_name VARCHAR(50) NOT NULL,
                    assigned_by INT(11),
                    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
                )");

                $stmt = $db->prepare("INSERT INTO employee_roles (user_id, role_name, assigned_by) VALUES (?, ?, ?)");
                $stmt->execute([$new_user_id, $form_data['role'], $_SESSION['user_id']]);
            } catch (Exception $e) {}

            // Log activity
            try {
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) 
                    VALUES (?, ?, 'employee_added', ?, NOW())
                ");
                $details = "Staff {$form_data['full_name']} added with role: {$form_data['role']}";
                $stmt->execute([$_SESSION['user_id'], $form_data['branch_id'], $details]);
            } catch (Exception $e) {}

            $message = "✅ Staff added successfully with role: <strong>{$form_data['role']}</strong>! Password: <strong>{$form_data['password']}</strong>";
            $message_type = 'success';

            $form_data = [
                'full_name' => '', 'username' => '', 'email' => '', 'phone' => '',
                'password' => '', 'branch_id' => $branch_id > 0 ? $branch_id : 1,
                'role' => 'doctor', 'specialty' => '', 'selected_departments' => []
            ];

            echo '<script>
                setTimeout(function(){ 
                    window.location.href = "branch_staff.php?id=' . $form_data['branch_id'] . '&success=1"; 
                }, 3000);
            </script>';

        } catch (PDOException $e) {
            $errors[] = 'Failed to add staff: ' . $e->getMessage();
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

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// ✅ INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/admin_header.php';
include_once __DIR__ . '/../../components/admin_sidebar.php';
?>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC CSS - FULL DARK MODE SUPPORT -->
<!-- ================================================================ -->
<style>
    :root {
        --page-primary: #0B5ED7;
        --page-primary-dark: #0A4CA8;
        --page-primary-bg: #E8F0FE;
        --page-success: #059669;
        --page-success-bg: #D1FAE5;
        --page-danger: #DC2626;
        --page-danger-bg: #FEE2E2;
        --page-warning: #D97706;
        --page-warning-bg: #FEF3C7;
        --page-bg-body: #F1F5F9;
        --page-bg-card: #FFFFFF;
        --page-text-primary: #1E293B;
        --page-text-secondary: #64748B;
        --page-border: #E2E8F0;
        --page-input-bg: #FFFFFF;
        --page-hover: #F8FAFC;
    }

    [data-theme="dark"] {
        --page-bg-body: #0F172A;
        --page-bg-card: #1E293B;
        --page-text-primary: #F1F5F9;
        --page-text-secondary: #94A3B8;
        --page-border: #334155;
        --page-input-bg: #0F172A;
        --page-hover: #0F172A;
        --page-primary-bg: #1E3A5F;
        --page-success-bg: #1A3A2A;
        --page-danger-bg: #3A1A1A;
        --page-warning-bg: #3D2E0A;
    }

    body { background: var(--page-bg-body, #F1F5F9); }
    html[data-theme="dark"] body { background: #0F172A !important; }

    .main-content { background: var(--page-bg-body, #F1F5F9); }
    html[data-theme="dark"] .main-content { background: #0F172A !important; }

    /* ================================================================
       BLUE PAGE HEADER CARD
       ================================================================ */
    .page-header-card {
        background: linear-gradient(135deg, #0B5ED7 0%, #0A4FB0 50%, #083D8A 100%);
        border-radius: 20px;
        padding: 24px 32px;
        margin-bottom: 24px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        color: white;
        box-shadow: 0 8px 32px rgba(11, 94, 215, 0.3), 0 4px 12px rgba(11, 94, 215, 0.2);
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .page-header-card::before {
        content: '';
        position: absolute;
        top: -50%; right: -10%;
        width: 400px; height: 400px;
        background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .page-header-card::after {
        content: '';
        position: absolute;
        bottom: -60%; left: -5%;
        width: 300px; height: 300px;
        background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .page-header-title {
        font-size: 1.5rem;
        font-weight: 800;
        margin: 0 0 6px 0;
        display: flex;
        align-items: center;
        gap: 12px;
        color: white;
        position: relative;
        z-index: 2;
    }

    .page-header-title i {
        width: 44px; height: 44px;
        background: rgba(255,255,255,0.2);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.2);
    }

    .page-header-subtitle {
        font-size: 0.9rem;
        color: rgba(255,255,255,0.9);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        position: relative;
        z-index: 2;
    }

    .page-header-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 12px;
        background: rgba(255,255,255,0.15);
        border: 1px solid rgba(255,255,255,0.25);
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        backdrop-filter: blur(10px);
    }

    .role-badge-display {
        background: rgba(255,255,255,0.25);
        color: white;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
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
        backdrop-filter: blur(10px);
        white-space: nowrap;
        position: relative;
        z-index: 2;
    }

    .page-header-back-btn:hover {
        background: rgba(255,255,255,0.25);
        transform: translateX(-3px);
        color: white;
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }

    /* ================================================================
       FORM CARD
       ================================================================ */
    .form-card {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 20px;
        padding: 28px 32px;
        border: 2px solid var(--page-border, #E2E8F0);
        transition: all 0.3s ease;
        max-width: 1000px;
        margin: 0 auto;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }

    html[data-theme="dark"] .form-card {
        background: #1E293B;
        border-color: #334155;
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

    html[data-theme="dark"] .form-header { border-bottom-color: #334155; }

    .form-header-icon {
        width: 56px; height: 56px;
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

    html[data-theme="dark"] .form-header h3 { color: #F1F5F9; }

    .form-header p {
        font-size: 0.85rem;
        color: var(--page-text-secondary, #64748B);
        margin: 0;
    }

    /* ================================================================
       FORM ELEMENTS
       ================================================================ */
    .form-label {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--page-text-primary, #1E293B);
        margin-bottom: 6px;
        display: block;
    }

    html[data-theme="dark"] .form-label { color: #F1F5F9; }

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
        background: var(--page-input-bg, #FFFFFF);
        color: var(--page-text-primary, #1E293B);
        font-family: inherit;
    }

    html[data-theme="dark"] .form-control {
        background: #0F172A;
        color: #F1F5F9;
        border-color: #334155;
    }

    html[data-theme="dark"] .form-control::placeholder { color: #64748B; }
    html[data-theme="dark"] .form-control option { background: #1E293B; color: #F1F5F9; }

    .form-control:focus {
        border-color: #0B5ED7;
        box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.12);
    }

    html[data-theme="dark"] .form-control:focus {
        border-color: #6EA8FE;
        box-shadow: 0 0 0 4px rgba(110, 168, 254, 0.15);
    }

    .form-control:disabled {
        background: var(--page-hover, #F1F5F9);
        color: var(--page-text-secondary, #64748B);
        cursor: not-allowed;
    }

    /* Password Input Group */
    .password-input-group {
        position: relative;
        display: flex;
        align-items: center;
    }

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
        transition: all 0.3s ease;
        border-radius: 8px;
    }

    .password-input-group .password-toggle:hover {
        color: #0B5ED7;
        background: var(--page-hover, #F1F5F9);
    }

    html[data-theme="dark"] .password-input-group .password-toggle:hover {
        background: #0F172A;
        color: #6EA8FE;
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
        background: linear-gradient(135deg, #0B5ED7, #1A73E8);
        color: white;
        white-space: nowrap;
        min-height: 34px;
        box-shadow: 0 2px 8px rgba(11, 94, 215, 0.25);
    }

    .btn-generate:hover {
        background: linear-gradient(135deg, #0A4CA8, #1557B0);
        transform: translateY(-2px);
        box-shadow: 0 4px 14px rgba(11, 94, 215, 0.35);
    }

    .password-actions {
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
        margin-top: 4px;
    }

    /* Input Icon Row */
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
        transition: color 0.3s ease;
    }

    .form-row-icon .form-control:focus ~ .input-icon { color: #0B5ED7; }

    /* ================================================================
       BUTTONS
       ================================================================ */
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
        box-shadow: 0 8px 25px rgba(11, 94, 215, 0.4);
        color: white;
    }

    .btn-outline {
        background: transparent;
        color: var(--page-text-primary, #1E293B);
        border: 2px solid var(--page-border, #E2E8F0);
    }

    html[data-theme="dark"] .btn-outline {
        color: #F1F5F9;
        border-color: #334155;
    }

    .btn-outline:hover {
        background: var(--page-hover, #F1F5F9);
        border-color: #0B5ED7;
        color: #0B5ED7;
        transform: translateY(-2px);
    }

    html[data-theme="dark"] .btn-outline:hover {
        background: #0F172A;
        border-color: #6EA8FE;
        color: #6EA8FE;
    }

    .form-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        padding-top: 24px;
        margin-top: 24px;
        border-top: 2px solid var(--page-border, #E2E8F0);
    }

    html[data-theme="dark"] .form-actions { border-top-color: #334155; }

    /* ================================================================
       SECTION TITLES
       ================================================================ */
    .section-title {
        font-size: 1rem;
        font-weight: 700;
        color: #0B5ED7;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    html[data-theme="dark"] .section-title { color: #6EA8FE; }

    .section-divider {
        border: none;
        border-top: 2px dashed var(--page-border, #E2E8F0);
        margin: 12px 0 16px;
    }

    html[data-theme="dark"] .section-divider { border-top-color: #334155; }

    .help-text {
        font-size: 0.7rem;
        color: var(--page-text-secondary, #64748B);
        margin-top: 4px;
    }

    /* ================================================================
       ROLE / DEPARTMENT CHECKBOX CARDS
       ================================================================ */
    .checkbox-group {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 12px;
        padding: 16px;
        border: 2px solid var(--page-border, #E2E8F0);
        border-radius: 14px;
        background: var(--page-hover, #F8FAFC);
        min-height: 80px;
    }

    html[data-theme="dark"] .checkbox-group {
        background: #0F172A;
        border-color: #334155;
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

    html[data-theme="dark"] .checkbox-item {
        background: #1E293B;
        border-color: #334155;
    }

    .checkbox-item::before {
        content: '';
        position: absolute;
        left: 0; top: 0; bottom: 0;
        width: 4px;
        background: #0B5ED7;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .checkbox-item:hover {
        border-color: #0B5ED7;
        background: #E8F0FE;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.12);
    }

    html[data-theme="dark"] .checkbox-item:hover {
        background: #1E3A5F;
        border-color: #6EA8FE;
    }

    .checkbox-item.checked {
        border-color: #0B5ED7;
        background: #E8F0FE;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.15);
    }

    .checkbox-item.checked::before { opacity: 1; }

    html[data-theme="dark"] .checkbox-item.checked {
        background: #1E3A5F;
        border-color: #6EA8FE;
    }

    .checkbox-item input[type="radio"],
    .checkbox-item input[type="checkbox"] {
        width: 20px; height: 20px;
        accent-color: #0B5ED7;
        cursor: pointer;
        flex-shrink: 0;
    }

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

    html[data-theme="dark"] .checkbox-item label { color: #F1F5F9; }

    .checkbox-item .role-desc {
        font-size: 0.68rem;
        color: var(--page-text-secondary, #64748B);
        font-weight: 400;
        opacity: 0.85;
    }

    .role-icon-wrapper {
        width: 36px; height: 36px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
        background: #E8F0FE;
        color: #0B5ED7;
    }

    html[data-theme="dark"] .role-icon-wrapper {
        background: #1E3A5F;
        color: #6EA8FE;
    }

    /* ================================================================
       MESSAGE BOX
       ================================================================ */
    .message-box {
        padding: 14px 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        display: flex;
        align-items: flex-start;
        gap: 12px;
        max-width: 1000px;
        margin-left: auto;
        margin-right: auto;
        animation: slideDown 0.4s ease;
    }

    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .message-box.success { background: #D1FAE5; color: #047857; border: 2px solid #059669; }
    .message-box.error { background: #FEE2E2; color: #B91C1C; border: 2px solid #DC2626; }

    html[data-theme="dark"] .message-box.success { background: #1A3A2A; color: #34D399; border-color: #34D399; }
    html[data-theme="dark"] .message-box.error { background: #3A1A1A; color: #F87171; border-color: #F87171; }

    /* ================================================================
       TIP CARDS
       ================================================================ */
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

    html[data-theme="dark"] .tip-card {
        background: #1E293B;
        border-color: #334155;
    }

    .tip-card:hover {
        border-color: #0B5ED7;
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.06);
    }

    .tip-card .tip-icon {
        width: 44px; height: 44px;
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

    html[data-theme="dark"] .tip-card .tip-icon.blue { background: #1E3A5F; color: #6EA8FE; }
    html[data-theme="dark"] .tip-card .tip-icon.green { background: #1A3A2A; color: #34D399; }
    html[data-theme="dark"] .tip-card .tip-icon.yellow { background: #3A2A1A; color: #FBBF24; }
    html[data-theme="dark"] .tip-card .tip-icon.purple { background: #2A1A3A; color: #9B4DCA; }

    .tip-card .tip-text h4 {
        font-size: 0.85rem;
        font-weight: 700;
        color: var(--page-text-primary, #1E293B);
        margin: 0 0 2px 0;
    }

    html[data-theme="dark"] .tip-card .tip-text h4 { color: #F1F5F9; }

    .tip-card .tip-text p {
        font-size: 0.75rem;
        color: var(--page-text-secondary, #64748B);
        margin: 0;
    }

    /* ================================================================
       TOAST
       ================================================================ */
    .toast-custom {
        position: fixed;
        bottom: 24px;
        right: 24px;
        padding: 14px 20px;
        border-radius: 12px;
        z-index: 9999;
        max-width: 420px;
        transform: translateY(100px);
        opacity: 0;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        align-items: center;
        gap: 12px;
        color: white;
        box-shadow: 0 8px 30px rgba(0,0,0,0.2);
    }

    .toast-custom.show { transform: translateY(0); opacity: 1; }
    .toast-custom.success { background: #059669; }
    .toast-custom.error { background: #EF4444; }
    .toast-custom.warning { background: #F59E0B; color: #1E293B; }
    .toast-custom.info { background: #0B5ED7; }

    /* ================================================================
       GRID
       ================================================================ */
    .grid { display: grid; }
    .grid-cols-1 { grid-template-columns: 1fr; }
    .md\:grid-cols-2 { grid-template-columns: 1fr 1fr; }
    .md\:col-span-2 { grid-column: span 2; }
    .gap-6 { gap: 24px; }
    .mt-2 { margin-top: 8px; }
    .mb-2 { margin-bottom: 8px; }

    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 768px) {
        .page-header-card { padding: 18px 20px; }
        .page-header-title { font-size: 1.2rem; }
        .page-header-title i { width: 36px; height: 36px; font-size: 1rem; }
        .form-card { padding: 18px 16px; }
        .form-header { flex-direction: column; text-align: center; }
        .form-header-icon { width: 48px; height: 48px; font-size: 1.2rem; }
        .btn { padding: 8px 16px; font-size: 0.8rem; min-height: 38px; min-width: 100%; }
        .form-actions { flex-direction: column; }
        .form-actions .btn { width: 100%; justify-content: center; }
        .password-actions { flex-direction: column; align-items: stretch; }
        .btn-generate { width: 100%; justify-content: center; }
        .md\:grid-cols-2 { grid-template-columns: 1fr; }
        .md\:col-span-2 { grid-column: span 1; }
        .checkbox-group { grid-template-columns: 1fr; }
        .tip-card { padding: 12px 16px; }
    }

    /* Print */
    @media print {
        .page-header-card, .form-actions, .btn, .page-header-back-btn { display: none !important; }
        .form-card { box-shadow: none !important; border: 1px solid #ddd !important; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Blue Page Header Card -->
    <div class="page-header-card">
        <div>
            <h1 class="page-header-title">
                <i class="fas fa-user-plus"></i>
                Add New Staff
                <span class="role-badge-display"><?= strtoupper($user_role) ?></span>
            </h1>
            <p class="page-header-subtitle">
                <i class="fas fa-store"></i>
                Add staff to <strong><?= htmlspecialchars($branch_name) ?></strong>
                <span class="page-header-badge"><i class="fas fa-building"></i> Branch #<?= $branch_id ?></span>
                <span class="page-header-badge"><i class="fas fa-users"></i> <?= $total_employees ?> employees</span>
                <span class="page-header-badge"><i class="fas fa-user-md"></i> <?= $total_doctors ?> doctors</span>
                <span class="page-header-badge"><i class="fas fa-shield-alt"></i> <?= count($available_roles) ?> roles</span>
            </p>
        </div>
        <a href="branch_staff.php?id=<?= $branch_id ?>" class="page-header-back-btn">
            <i class="fas fa-arrow-left"></i> Back to Staff
        </a>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="message-box <?= $message_type === 'success' ? 'success' : 'error' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>" style="font-size:1.1rem;margin-top:2px;"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- Form Card -->
    <div class="form-card">
        <div class="form-header">
            <div class="form-header-icon">
                <i class="fas fa-user-plus"></i>
            </div>
            <div>
                <h3>Staff Information</h3>
                <p>Enter staff details, assign role & departments</p>
            </div>
        </div>

        <form method="POST" action="" id="addStaffForm">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                <!-- Personal Info Section -->
                <div class="md:col-span-2">
                    <h3 class="section-title"><i class="fas fa-user-circle"></i> Personal Information</h3>
                    <hr class="section-divider">
                </div>

                <!-- Full Name -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-user" style="color:#0B5ED7;"></i> Full Name <span class="required">*</span>
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
                        <i class="fas fa-at" style="color:#0B5ED7;"></i> Username <span class="required">*</span>
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
                        <i class="fas fa-envelope" style="color:#059669;"></i> Email <span class="required">*</span>
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
                        <i class="fas fa-phone" style="color:#0B5ED7;"></i> Phone Number
                    </label>
                    <div class="form-row-icon">
                        <input type="text" name="phone" class="form-control"
                               placeholder="Enter phone number"
                               value="<?= htmlspecialchars($form_data['phone']) ?>">
                        <span class="input-icon"><i class="fas fa-phone"></i></span>
                    </div>
                </div>

                <!-- Password with Auto-Generate -->
                <div class="md:col-span-2">
                    <label class="form-label">
                        <i class="fas fa-key" style="color:#D97706;"></i> Password <span class="required">*</span>
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
                        <span class="help-text">Format: NAME + BRCODE + UID + ID</span>
                    </div>
                </div>

                <!-- Branch -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-store-alt" style="color:#059669;"></i> Branch <span class="required">*</span>
                    </label>
                    <div class="form-row-icon">
                        <select name="branch_id" id="branchSelect" class="form-control" required>
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

                <!-- Specialty (for doctors) -->
                <div id="specialtySection" style="display: <?= $form_data['role'] === 'doctor' ? 'block' : 'none' ?>;">
                    <label class="form-label">
                        <i class="fas fa-stethoscope" style="color:#7C3AED;"></i> Specialty
                        <span style="font-size:0.65rem;color:#64748B;font-weight:400;">(For doctors)</span>
                    </label>
                    <div class="form-row-icon">
                        <input type="text" name="specialty" class="form-control"
                               placeholder="e.g. General Medicine, Pediatrics"
                               value="<?= htmlspecialchars($form_data['specialty']) ?>">
                        <span class="input-icon"><i class="fas fa-stethoscope"></i></span>
                    </div>
                </div>

                <!-- ================================================================
                     ✅ ROLES SELECTION - WITH AUDIT
                     ================================================================ -->
                <div class="md:col-span-2 mt-2">
                    <h3 class="section-title">
                        <i class="fas fa-user-tag"></i> Select Role <span class="required">*</span>
                    </h3>
                    <p class="help-text mb-2">Select the primary role for this staff member.</p>
                    <hr class="section-divider">

                    <div class="checkbox-group" id="rolesContainer">
                        <?php foreach ($available_roles as $role_key => $role_info): ?>
                            <div class="checkbox-item role-badge-<?= $role_key ?>" 
                                 onclick="selectRole(this, '<?= $role_key ?>')">
                                <input type="radio" name="role" value="<?= $role_key ?>"
                                       id="role_<?= $role_key ?>"
                                       <?= $form_data['role'] === $role_key ? 'checked' : '' ?>>
                                <div class="role-icon-wrapper" style="background:<?= $role_info['color'] ?>15;color:<?= $role_info['color'] ?>;">
                                    <i class="fas <?= $role_info['icon'] ?>"></i>
                                </div>
                                <label for="role_<?= $role_key ?>">
                                    <?= htmlspecialchars($role_info['label']) ?>
                                    <span class="role-desc"><?= ucfirst($role_key) ?></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- ================================================================
                     ✅ DEPARTMENTS SELECTION - WITH AUDIT
                     ================================================================ -->
                <div class="md:col-span-2 mt-2">
                    <h3 class="section-title">
                        <i class="fas fa-building"></i> Select Departments
                        <span style="font-size:0.7rem;font-weight:400;color:#64748B;">(<?= count($departments) ?> available)</span>
                    </h3>
                    <p class="help-text mb-2">Click on a department to select/deselect it. (Optional)</p>
                    <hr class="section-divider">

                    <div class="checkbox-group" id="departmentsContainer">
                        <?php if (!empty($departments)): ?>
                            <?php foreach ($departments as $dept): ?>
                                <div class="checkbox-item" onclick="toggleDepartment(this)">
                                    <input type="checkbox" name="departments[]" value="<?= $dept['id'] ?>"
                                           id="dept_<?= $dept['id'] ?>"
                                           <?= in_array($dept['id'], $form_data['selected_departments']) ? 'checked' : '' ?>>
                                    <div class="role-icon-wrapper"
                                         style="background:<?= $dept['color'] ?? '#0B5ED7' ?>15;color:<?= $dept['color'] ?? '#0B5ED7' ?>;">
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
                            <p style="grid-column: 1/-1;text-align:center;color:#94A3B8;font-size:0.85rem;padding:12px;">
                                <i class="fas fa-info-circle"></i> No departments available.
                            </p>
                        <?php endif; ?>
                    </div>
                    <p class="help-text mt-2">Selected: <strong id="selectedDeptCount">0</strong> departments</p>
                </div>

            </div>

            <!-- Form Actions -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <i class="fas fa-save"></i> Save Staff
                </button>
                <a href="branch_staff.php?id=<?= $branch_id ?>" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="reset" class="btn btn-outline">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </div>
        </form>
    </div>

    <!-- Quick Tips -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;max-width:1000px;margin:20px auto 0;">
        <div class="tip-card">
            <div class="tip-icon blue"><i class="fas fa-lightbulb"></i></div>
            <div class="tip-text">
                <h4>Tip #1</h4>
                <p>Select the correct role</p>
            </div>
        </div>
        <div class="tip-card">
            <div class="tip-icon green"><i class="fas fa-check-circle"></i></div>
            <div class="tip-text">
                <h4>Tip #2</h4>
                <p>One role per staff</p>
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
            <div class="tip-icon purple"><i class="fas fa-building"></i></div>
            <div class="tip-text">
                <h4>Tip #4</h4>
                <p>Assign departments for extra access</p>
            </div>
        </div>
    </div>

</main>

<!-- Toast -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE BACKGROUND ENFORCEMENT
    // ================================================================
    function enforceDarkModeBackground() {
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        var body = document.body;
        var mainContent = document.querySelector('.main-content');

        if (isDark) {
            if (body) body.style.background = '#0F172A';
            if (mainContent) mainContent.style.background = '#0F172A';
        } else {
            if (body) body.style.background = '#F1F5F9';
            if (mainContent) mainContent.style.background = '#F1F5F9';
        }
    }

    enforceDarkModeBackground();
    document.addEventListener('darkModeChanged', function() { setTimeout(enforceDarkModeBackground, 50); });
    new MutationObserver(function(mutations) {
        mutations.forEach(function(m) {
            if (m.attributeName === 'data-theme') enforceDarkModeBackground();
        });
    }).observe(document.documentElement, { attributes: true });

    // ================================================================
    // SELECT ROLE
    // ================================================================
    function selectRole(element, roleKey) {
        // Uncheck all radio buttons
        document.querySelectorAll('input[name="role"]').forEach(function(radio) { radio.checked = false; });

        // Check the selected one
        var radio = document.getElementById('role_' + roleKey);
        if (radio) radio.checked = true;

        // Update UI
        document.querySelectorAll('#rolesContainer .checkbox-item').forEach(function(el) {
            el.classList.remove('checked');
        });
        element.classList.add('checked');

        // Show/hide specialty section
        var specialtySection = document.getElementById('specialtySection');
        if (roleKey === 'doctor') {
            specialtySection.style.display = 'block';
        } else {
            specialtySection.style.display = 'none';
        }
    }

    // ================================================================
    // TOGGLE DEPARTMENT
    // ================================================================
    function toggleDepartment(element) {
        var checkbox = element.querySelector('input[type="checkbox"]');
        if (!checkbox) return;

        checkbox.checked = !checkbox.checked;
        if (checkbox.checked) element.classList.add('checked');
        else element.classList.remove('checked');

        updateDeptCount();
    }

    function updateDeptCount() {
        var checked = document.querySelectorAll('input[name="departments[]"]:checked');
        var el = document.getElementById('selectedDeptCount');
        if (el) el.textContent = checked.length;
    }

    // Initialize checked states on load
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('#rolesContainer .checkbox-item input[type="radio"]').forEach(function(radio) {
            if (radio.checked) radio.closest('.checkbox-item').classList.add('checked');
        });
        document.querySelectorAll('#departmentsContainer .checkbox-item input[type="checkbox"]').forEach(function(cb) {
            if (cb.checked) cb.closest('.checkbox-item').classList.add('checked');
        });
        updateDeptCount();
    });

    // ================================================================
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        if (!toast) return;
        toast.className = 'toast-custom ' + type;
        toastTitle.textContent = title;
        toastMessage.textContent = message;
        toast.style.display = 'flex';
        void toast.offsetWidth;
        toast.classList.add('show');
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 3500);
    }

    // ================================================================
    // PASSWORD TOGGLE
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
    // GENERATE PASSWORD
    // ================================================================
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
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        btn.disabled = true;

        var formData = new FormData();
        formData.append('action', 'generate_password');
        formData.append('full_name', fullName);
        formData.append('branch_id', branchId);
        formData.append('user_id', 0);

        fetch(window.location.href, { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    document.getElementById('passwordField').value = data.password;
                    showToast('✅ Success', 'Password generated successfully!', 'success');

                    var passwordField = document.getElementById('passwordField');
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

    // ================================================================
    // FORM VALIDATION
    // ================================================================
    document.getElementById('addStaffForm')?.addEventListener('submit', function(e) {
        var role = document.querySelector('input[name="role"]:checked');
        if (!role) {
            e.preventDefault();
            showToast('⚠️ Warning', 'Please select a role for this staff member', 'warning');
            return false;
        }

        var submitBtn = document.getElementById('submitBtn');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            setTimeout(function() {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-save"></i> Save Staff';
            }, 10000);
        }
    });

    console.log('%c👤 Braick - Add Staff', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#059669;');
    console.log('%c🛡️ Audit role & department added', 'font-size:13px; color:#DC2626;');
    console.log('%c🌙 FULL DARK MODE inafanya kazi', 'font-size:13px; color:#7C3AED;');
    console.log('%c🏥 Branch: <?= htmlspecialchars($branch_name) ?> (ID: <?= $branch_id ?>)', 'font-size:13px; color:#059669;');
</script>

</body>
</html>