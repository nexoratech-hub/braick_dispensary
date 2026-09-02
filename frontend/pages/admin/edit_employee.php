<?php
// ================================================================
// FILE: frontend/pages/admin/edit_employee.php
// SUPER ADMIN - EDIT EMPLOYEE
// BRAICK DISPENSARY - UPDATED WITH NEW DESIGN
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
// GET ADMIN DATA
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
require_once __DIR__ . '/../../../backend/config/database.php';
require_once __DIR__ . '/../../../backend/helpers/functions.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
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
// GET EMPLOYEE ROLES (from employee_roles table)
// ================================================================
$employee_roles = [];
try {
    $stmt = $db->prepare("SELECT role_name FROM employee_roles WHERE user_id = ?");
    $stmt->execute([$employee_id]);
    $employee_roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $employee_roles = [$employee['role']];
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
// GET STATISTICS
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
    ['name' => 'doctor', 'label' => 'Doctor', 'icon' => 'fa-user-md', 'color' => '#0B5ED7'],
    ['name' => 'reception', 'label' => 'Reception', 'icon' => 'fa-user-tie', 'color' => '#059669'],
    ['name' => 'pharmacy', 'label' => 'Pharmacy', 'icon' => 'fa-pills', 'color' => '#D97706'],
    ['name' => 'laboratory', 'label' => 'Laboratory', 'icon' => 'fa-microscope', 'color' => '#7C3AED'],
    ['name' => 'cashier', 'label' => 'Cashier', 'icon' => 'fa-cash-register', 'color' => '#0D9488']
];

// ================================================================
// GET DEPARTMENTS
// ================================================================
$departments = [];
$stmt = $db->query("SELECT id, category_name, description, icon, color FROM service_categories WHERE is_active = 1 ORDER BY category_name");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $departments[] = $row;
}

// ================================================================
// GENERATE PASSWORD FUNCTION - NEW FORMAT
// ================================================================
function generatePassword($full_name, $branch_id, $user_id = null) {
    // Clean name and get first 4 letters (mixed case)
    $clean_name = preg_replace('/[^a-zA-Z]/', '', $full_name);
    $name_part = substr($clean_name, 0, 4);
    
    // Ensure at least 3 characters
    if (strlen($name_part) < 3) {
        $name_part = 'User';
    }
    
    // Capitalize first letter, rest lowercase
    $name_part = ucfirst(strtolower($name_part));
    
    // Get branch code (BR + 2-digit branch ID)
    $branch_code = 'BR' . str_pad($branch_id, 2, '0', STR_PAD_LEFT);
    
    // Get user ID part (UID + last 2 digits of user_id or random)
    if ($user_id && $user_id > 0) {
        $user_code = 'U' . str_pad($user_id, 2, '0', STR_PAD_LEFT);
    } else {
        $user_code = 'U' . rand(10, 99);
    }
    
    // Combine: Name(4) + Branch(4) + User(3) = 11 characters
    $password = $name_part . $branch_code . $user_code;
    
    // Ensure exactly 8+ characters
    if (strlen($password) < 8) {
        $password .= rand(100, 999);
    }
    
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

$form_data = [
    'full_name' => $employee['full_name'],
    'username' => $employee['username'],
    'email' => $employee['email'],
    'phone' => $employee['phone'] ?? '',
    'branch_id' => $employee['branch_id'],
    'selected_roles' => $employee_roles,
    'selected_departments' => $employee_departments,
    'specialty' => $employee['specialty'] ?? '',
    'status' => $employee['status'] ?? 'active',
    'password' => '',
    'password_changed' => false
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
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
    $selected_roles = $_POST['roles'] ?? [];
    $selected_departments = $_POST['departments'] ?? [];
    $password_changed = false;
    
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
    if ($branch_id <= 0) {
        $errors[] = 'Branch is required';
    }
    if (empty($selected_roles)) {
        $errors[] = 'At least one role must be selected';
    }
    
    // Validate roles: Max 2 roles, if 2 roles one must be reception
    if (count($selected_roles) > 2) {
        $errors[] = 'Maximum of 2 roles allowed per employee';
    }
    if (count($selected_roles) == 2 && !in_array('reception', $selected_roles)) {
        $errors[] = 'If assigning 2 roles, one must be Reception';
    }
    
    // Password handling - NOT REQUIRED for editing
    $new_password = null;
    if (!empty($generated_password)) {
        $new_password = $generated_password;
        $password_changed = true;
    } else if (!empty($password)) {
        if (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters long';
        }
        if ($password !== $confirm_password) {
            $errors[] = 'Passwords do not match';
        }
        if (empty($errors)) {
            $new_password = $password;
            $password_changed = true;
        }
    }
    // If password is empty, keep the old password (no change)
    
    // Check if username exists
    if (empty($errors) && $username !== $employee['username']) {
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $employee_id]);
        if ($stmt->fetch()) {
            $errors[] = 'Username already exists';
        }
    }
    
    // Check if email exists
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
            
            // Primary role = first selected role
            $primary_role = $selected_roles[0];
            
            $sql = "UPDATE users SET 
                    full_name = ?, 
                    username = ?, 
                    email = ?, 
                    phone = ?, 
                    role = ?, 
                    branch_id = ?, 
                    status = ?, 
                    specialty = ?";
            $params = [$full_name, $username, $email, $phone, $primary_role, $branch_id, $status, $specialty];
            
            // Update password only if changed
            if ($password_changed && $new_password !== null) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $sql .= ", password = ?";
                $params[] = $hashed_password;
                $sql .= ", is_default_password = 0";
                $sql .= ", password_changed_at = NOW()";
            }
            
            $sql .= ", updated_at = NOW() WHERE id = ?";
            $params[] = $employee_id;
            
            $stmt = $db->prepare($sql);
            
            if ($stmt->execute($params)) {
                // Update employee_roles
                try {
                    // Delete old roles
                    $stmt = $db->prepare("DELETE FROM employee_roles WHERE user_id = ?");
                    $stmt->execute([$employee_id]);
                    
                    // Insert new roles
                    foreach ($selected_roles as $role_name) {
                        $stmt = $db->prepare("INSERT INTO employee_roles (user_id, role_name, assigned_by) VALUES (?, ?, ?)");
                        $stmt->execute([$employee_id, $role_name, $_SESSION['user_id']]);
                    }
                } catch (Exception $e) {
                    error_log("Employee roles update error: " . $e->getMessage());
                }
                
                // Update employee_departments
                try {
                    // Delete old departments
                    $stmt = $db->prepare("DELETE FROM employee_departments WHERE user_id = ?");
                    $stmt->execute([$employee_id]);
                    
                    // Insert new departments
                    foreach ($selected_departments as $dept_id) {
                        $stmt = $db->prepare("INSERT INTO employee_departments (user_id, department_id, assigned_by) VALUES (?, ?, ?)");
                        $stmt->execute([$employee_id, $dept_id, $_SESSION['user_id']]);
                    }
                } catch (Exception $e) {
                    error_log("Employee departments update error: " . $e->getMessage());
                }
                
                // Log activity
                try {
                    $stmt = $db->prepare("
                        INSERT INTO activity_logs (user_id, branch_id, action, details, created_at)
                        VALUES (?, ?, 'employee_updated', ?, NOW())
                    ");
                    $details = "Employee {$full_name} updated (Roles: " . implode(', ', $selected_roles) . ")";
                    if ($password_changed) {
                        $details .= " - Password UPDATED";
                    }
                    $stmt->execute([$_SESSION['user_id'], $branch_id, $details]);
                } catch (Exception $e) {}
                
                $db->commit();
                
                $message = "✅ Employee updated successfully!";
                if ($password_changed && $new_password !== null) {
                    $message .= "<br>🔑 <strong>New Password:</strong> <span style='font-family:monospace;background:#1E293B;color:#34D399;padding:4px 12px;border-radius:6px;'>" . htmlspecialchars($new_password) . "</span>";
                    $message .= "<br>📋 Please copy this password and share with the employee.";
                }
                $message_type = 'success';
                
                // Refresh employee data
                $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$employee_id]);
                $employee = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $form_data = [
                    'full_name' => $employee['full_name'],
                    'username' => $employee['username'],
                    'email' => $employee['email'],
                    'phone' => $employee['phone'] ?? '',
                    'branch_id' => $employee['branch_id'],
                    'selected_roles' => $selected_roles,
                    'selected_departments' => $selected_departments,
                    'specialty' => $employee['specialty'] ?? '',
                    'status' => $employee['status'] ?? 'active',
                    'password_changed' => $password_changed
                ];
                
                // Redirect after success
                echo '<script>
                    setTimeout(function(){ 
                        window.location.href = "employees.php?branch=' . $branch_id . '&updated=1"; 
                    }, 4000);
                </script>';
                
            } else {
                $errors[] = 'Failed to update employee. Please try again.';
            }
        } catch (Exception $e) {
            if (isset($db)) $db->rollBack();
            $errors[] = 'Error: ' . $e->getMessage();
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
            'selected_roles' => $selected_roles,
            'selected_departments' => $selected_departments,
            'specialty' => $specialty,
            'status' => $status,
            'password' => $password ?? '',
            'password_changed' => $password_changed
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
// INCLUDE HEADERS
// ================================================================
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Employee - Braick Dispensary</title>
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* ================================================================ */
        /* VARIABLES - SAME AS ADD EMPLOYEE */
        /* ================================================================ */
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
            --primary-gradient: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            --success: #059669;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --bg-body: #F0F4F8;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --radius: 12px;
            --radius-lg: 18px;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --primary: #3B82F6;
            --primary-bg: #1E3A5F;
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        /* ================================================================ */
        /* TOP NAV - SAME AS ADD EMPLOYEE */
        /* ================================================================ */
        .top-nav {
            position: fixed;
            top: 0;
            left: 270px;
            right: 0;
            height: 68px;
            background: var(--bg-nav);
            z-index: 40;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            border-bottom: 2px solid var(--border-color);
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow-sm);
        }
        
        .top-nav .search-wrapper {
            display: flex;
            align-items: center;
            background: var(--bg-body);
            border-radius: var(--radius);
            border: 2px solid var(--border-color);
            flex: 1;
            max-width: 500px;
        }
        
        .top-nav .search-wrapper input {
            border: none;
            background: transparent;
            padding: 8px 14px;
            width: 100%;
            font-size: 0.85rem;
            outline: none;
            color: var(--text-primary);
        }
        
        .top-nav .search-wrapper .search-btn {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 0 var(--radius) var(--radius) 0;
            cursor: pointer;
            font-size: 0.85rem;
        }
        
        .top-nav .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-color);
            cursor: pointer;
        }
        
        .top-nav .icon-btn {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            background: transparent;
            border: none;
            cursor: pointer;
            position: relative;
        }
        
        .notif-dot {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            border: 2px solid var(--bg-nav);
        }
        .notif-dot.has-notif { background: var(--danger); }
        .notif-dot.no-notif { background: var(--gray-400); }
        
        .dark-toggle-btn {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            padding: 6px 12px;
            cursor: pointer;
            font-size: 0.82rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .branch-selector {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            padding: 6px 12px;
            font-size: 0.78rem;
            color: var(--text-primary);
            outline: none;
            cursor: pointer;
        }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        /* ================================================================ */
        /* PAGE HEADER */
        /* ================================================================ */
        .page-header {
            background: var(--primary-gradient);
            border-radius: var(--radius-lg);
            padding: 28px 36px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 8px 32px rgba(10, 76, 168, 0.35);
            position: relative;
            overflow: hidden;
        }
        
        .page-header .page-title {
            color: white;
            font-size: 1.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-title i { font-size: 2rem; opacity: 0.9; }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            backdrop-filter: blur(4px);
        }
        
        .page-header .header-badge {
            background: rgba(255,255,255,0.12);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            backdrop-filter: blur(4px);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 18px;
            border-radius: var(--radius);
            font-weight: 500;
            font-size: 0.82rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(4px);
            position: relative;
            z-index: 1;
        }
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        /* ================================================================ */
        /* FORM CARD - SAME AS ADD EMPLOYEE */
        /* ================================================================ */
        .form-card {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 28px 32px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            max-width: 1100px;
            margin: 0 auto;
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
        
        /* Password Input Group */
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
        
        /* ================================================================ */
        /* CHECKBOX GROUP - SAME AS ADD EMPLOYEE */
        /* ================================================================ */
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
        
        /* ================================================================ */
        /* BUTTONS */
        /* ================================================================ */
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
        
        /* ================================================================ */
        /* PASSWORD STRENGTH */
        /* ================================================================ */
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
        
        /* ================================================================ */
        /* TOAST / ALERT */
        /* ================================================================ */
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 14px 20px;
            border-radius: var(--radius);
            z-index: 999;
            max-width: 400px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            box-shadow: var(--shadow-lg);
        }
        
        .toast-custom.show { transform: translateY(0); opacity: 1; }
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 0.82rem;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 2px solid transparent;
        }
        
        .alert-success {
            background: #D1FAE5;
            color: #065F46;
            border-color: #34D399;
        }
        .alert-danger {
            background: #FEE2E2;
            color: #991B1B;
            border-color: #F87171;
        }
        
        [data-theme="dark"] .alert-success {
            background: #1A3A2A;
            color: #34D399;
            border-color: #059669;
        }
        [data-theme="dark"] .alert-danger {
            background: #3A1A1A;
            color: #F87171;
            border-color: #DC2626;
        }
        
        /* ================================================================ */
        /* FOOTER */
        /* ================================================================ */
        .footer {
            padding: 14px 0;
            border-top: 2px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand { color: var(--primary); font-weight: 700; }
        
        /* ================================================================ */
        /* RESPONSIVE */
        /* ================================================================ */
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .form-card { padding: 16px; }
            .form-actions { flex-direction: column; }
            .form-actions .btn { width: 100%; justify-content: center; }
            .checkbox-group { grid-template-columns: 1fr 1fr; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .page-header { flex-direction: column; align-items: flex-start !important; }
            .checkbox-group { grid-template-columns: 1fr; }
            .form-header { flex-direction: column; text-align: center; }
            .form-header-icon { width: 48px; height: 48px; font-size: 1.2rem; }
            .btn { padding: 8px 16px; font-size: 0.8rem; min-height: 38px; min-width: 100%; }
            .form-actions .btn { width: 100%; justify-content: center; }
            .password-actions { flex-direction: column; align-items: stretch; }
            .btn-generate { width: 100%; justify-content: center; }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
        
        .flex { display: flex; }
        .flex-wrap { flex-wrap: wrap; }
        .items-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        .gap-3 { gap: 12px; }
        .gap-4 { gap: 16px; }
        .gap-6 { gap: 24px; }
        .grid { display: grid; }
        .grid-cols-1 { grid-template-columns: 1fr; }
        .grid-cols-2 { grid-template-columns: 1fr 1fr; }
        .md\:grid-cols-2 { grid-template-columns: 1fr 1fr; }
        .md\:grid-cols-4 { grid-template-columns: 1fr 1fr 1fr 1fr; }
        .md\:col-span-2 { grid-column: span 2; }
        .mt-2 { margin-top: 8px; }
        .mt-5 { margin-top: 20px; }
        .mb-2 { margin-bottom: 8px; }
        .mb-4 { margin-bottom: 16px; }
        .mb-5 { margin-bottom: 20px; }
        .mr-1 { margin-right: 4px; }
        .mr-2 { margin-right: 8px; }
        .ml-2 { margin-left: 8px; }
        .text-sm { font-size: 0.85rem; }
        .text-xs { font-size: 0.7rem; }
        .text-gray-400 { color: var(--text-secondary); }
        .text-blue-600 { color: #0B5ED7; }
        .text-green-600 { color: #059669; }
        .text-purple-600 { color: #7C3AED; }
        .text-yellow-600 { color: #D97706; }
        .text-center { text-align: center; }
        .col-span-full { grid-column: 1 / -1; }
        .inline-flex { display: inline-flex; }
        .underline { text-decoration: underline; }
        .rounded-full { border-radius: 9999px; }
        .border { border: 1px solid; }
        .border-green-200 { border-color: #A7F3D0; }
        .border-blue-200 { border-color: #BFDBFE; }
        .border-purple-200 { border-color: #DDD6FE; }
        .bg-green-100 { background: #D1FAE5; }
        .bg-blue-100 { background: #DBEAFE; }
        .bg-purple-100 { background: #EDE9FE; }
        .text-green-700 { color: #065F46; }
        .text-blue-700 { color: #1D4ED8; }
        .text-purple-700 { color: #5B21B6; }
        .px-3 { padding-left: 12px; padding-right: 12px; }
        .py-1 { padding-top: 4px; padding-bottom: 4px; }
        .p-4 { padding: 16px; }
        .rounded-xl { border-radius: 12px; }
        .bg-red-100 { background: #FEE2E2; }
        .text-red-700 { color: #991B1B; }
        .border-red-200 { border-color: #FCA5A5; }
        .lg\:hidden { display: none; }
        @media (max-width: 1024px) { .lg\:hidden { display: block; } }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- TOP NAV -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <input type="text" id="searchInput" placeholder="Search...">
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
    <div class="page-header">
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
                    <i class="fas fa-tag"></i>
                    <?= ucfirst($employee['role']) ?>
                </span>
                <?php if ($employee['is_default_password'] == 1): ?>
                    <span class="header-badge" style="background:rgba(251,191,36,0.2);border-color:rgba(251,191,36,0.3);color:#FBBF24;">
                        <i class="fas fa-key"></i> Default Password
                    </span>
                <?php else: ?>
                    <span class="header-badge" style="background:rgba(52,211,153,0.2);border-color:rgba(52,211,153,0.3);color:#34D399;">
                        <i class="fas fa-check-circle"></i> Password Set
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div>
            <a href="employees.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- Messages -->
    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'danger' ?>" style="max-width:1100px;margin:0 auto 16px;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FORM CARD -->
    <!-- ================================================================ -->
    <div class="form-card animate-fade-in-up">
        <div class="form-header">
            <div class="form-header-icon">
                <i class="fas fa-user-edit"></i>
            </div>
            <div>
                <h3>Edit Employee Information</h3>
                <p>Update employee details, roles, departments, and password</p>
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
                
                <!-- Status -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-toggle-on text-blue-600"></i> Status
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
                <!-- Password Section - NOT REQUIRED -->
                <!-- ================================================================ -->
                <div class="md:col-span-2">
                    <h3 class="section-title">
                        <i class="fas fa-key text-yellow-600"></i> Password Settings
                        <span class="badge-count">(Leave empty to keep current password)</span>
                    </h3>
                    <hr class="section-divider">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- New Password -->
                        <div>
                            <label class="form-label">
                                <i class="fas fa-lock text-blue-600"></i> New Password
                                <span style="font-weight:400;font-size:0.7rem;color:var(--text-secondary);">(Optional)</span>
                            </label>
                            <div class="password-input-group">
                                <input type="password" name="password" id="newPassword" class="form-control" 
                                       placeholder="Enter new password or leave empty">
                                <button type="button" class="password-toggle" onclick="togglePasswordVisibility('newPassword', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Confirm Password -->
                        <div>
                            <label class="form-label">
                                <i class="fas fa-lock text-blue-600"></i> Confirm Password
                            </label>
                            <div class="password-input-group">
                                <input type="password" name="confirm_password" id="confirmPassword" class="form-control" 
                                       placeholder="Confirm new password">
                                <button type="button" class="password-toggle" onclick="togglePasswordVisibility('confirmPassword', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Password Actions -->
                    <div class="password-actions mt-2">
                        <button type="button" class="btn-generate" id="generatePasswordBtn">
                            <i class="fas fa-sync-alt"></i> Generate Password
                        </button>
                        <span class="help-text">Format: Name + Branch Code + User ID (e.g. EricBR01U23)</span>
                    </div>
                    
                    <!-- Generated Password Box -->
                    <div class="generated-password-box" id="generatedPasswordBox" style="display:none;background:#1E293B;color:#34D399;padding:10px 16px;border-radius:8px;font-family:'Courier New',monospace;font-size:0.95rem;font-weight:600;margin-top:10px;border:1px solid #334155;word-break:break-all;">
                        <span id="generatedPasswordDisplay">****************</span>
                        <button type="button" class="copy-btn" onclick="copyGeneratedPassword()" style="background:rgba(255,255,255,0.1);border:none;color:#94A3B8;padding:3px 12px;border-radius:4px;cursor:pointer;font-size:0.65rem;margin-left:10px;">
                            <i class="fas fa-copy"></i> Copy
                        </button>
                        <div style="font-size:0.6rem;color:#94A3B8;margin-top:4px;font-family:'Inter',sans-serif;font-weight:400;">
                            <i class="fas fa-sync-alt"></i> Click "Generate Password" again to regenerate
                        </div>
                    </div>
                    
                    <!-- Password Strength -->
                    <div class="password-strength" id="passwordStrength">
                        <div class="strength-bar" data-index="0"></div>
                        <div class="strength-bar" data-index="1"></div>
                        <div class="strength-bar" data-index="2"></div>
                        <div class="strength-bar" data-index="3"></div>
                    </div>
                    <div class="password-strength-text" id="passwordStrengthText">Enter a password to check strength</div>
                    
                    <!-- Hidden field for generated password -->
                    <input type="hidden" name="generated_password" id="generatedPasswordHidden" value="">
                    
                    <div class="help-text mt-2" style="padding:8px 12px;background:var(--bg-body);border-radius:8px;border:1px solid var(--border-color);">
                        <i class="fas fa-info-circle text-blue-600 mr-1"></i>
                        <span style="font-size:0.75rem;">
                            <strong>Note:</strong> Password is optional. Leave fields empty to keep the current password.
                            <?php if ($employee['is_default_password'] == 1): ?>
                                <span style="color:#D97706;font-weight:600;">⚠️ This employee is using the default password. We recommend setting a new password.</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                
                <!-- ================================================================ -->
                <!-- Roles Selection - Max 2, One must be Reception -->
                <!-- ================================================================ -->
                <div class="md:col-span-2 mt-2">
                    <h3 class="section-title">
                        <i class="fas fa-user-tag"></i> Select Roles
                        <span class="required">*</span>
                        <span class="badge-count">(Max 2 roles, one must be Reception)</span>
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
            
            // Validate roles - max 2, one must be reception
            validateRoles();
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
    // VALIDATE ROLES - Max 2, one must be reception
    // ================================================================
    function validateRoles() {
        var rolesChecked = document.querySelectorAll('input[name="roles[]"]:checked');
        var rolesContainer = document.getElementById('rolesContainer');
        var errorMsg = document.getElementById('roleErrorMsg');
        
        // Remove existing error message
        if (errorMsg) {
            errorMsg.remove();
        }
        
        if (rolesChecked.length > 2) {
            // Uncheck the last checked checkbox
            var lastChecked = rolesChecked[rolesChecked.length - 1];
            lastChecked.checked = false;
            lastChecked.closest('.checkbox-item').classList.remove('checked');
            
            // Show error
            var msg = document.createElement('p');
            msg.id = 'roleErrorMsg';
            msg.className = 'help-text mt-1 text-red-700';
            msg.style.color = '#EF4444';
            msg.innerHTML = '<i class="fas fa-exclamation-circle mr-1"></i> Maximum of 2 roles allowed. Please select only 2 roles.';
            rolesContainer.parentNode.insertBefore(msg, rolesContainer.nextSibling);
            
            showToast('⚠️ Warning', 'Maximum of 2 roles allowed per employee', 'warning');
            updateCounts();
            return false;
        }
        
        if (rolesChecked.length == 2) {
            var hasReception = false;
            rolesChecked.forEach(function(cb) {
                if (cb.value === 'reception') {
                    hasReception = true;
                }
            });
            
            if (!hasReception) {
                // Uncheck the last checked checkbox
                var lastChecked = rolesChecked[rolesChecked.length - 1];
                lastChecked.checked = false;
                lastChecked.closest('.checkbox-item').classList.remove('checked');
                
                var msg = document.createElement('p');
                msg.id = 'roleErrorMsg';
                msg.className = 'help-text mt-1 text-red-700';
                msg.style.color = '#EF4444';
                msg.innerHTML = '<i class="fas fa-exclamation-circle mr-1"></i> If selecting 2 roles, one must be Reception.';
                rolesContainer.parentNode.insertBefore(msg, rolesContainer.nextSibling);
                
                showToast('⚠️ Warning', 'If selecting 2 roles, one must be Reception', 'warning');
                updateCounts();
                return false;
            }
        }
        
        return true;
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
    // VALIDATION - Form Submit
    // ================================================================
    document.getElementById('editEmployeeForm')?.addEventListener('submit', function(e) {
        var rolesChecked = document.querySelectorAll('input[name="roles[]"]:checked');
        if (rolesChecked.length === 0) {
            e.preventDefault();
            showToast('⚠️ Warning', 'Please select at least one role for this employee.', 'warning');
            document.getElementById('rolesContainer').style.borderColor = '#EF4444';
            setTimeout(function() {
                document.getElementById('rolesContainer').style.borderColor = '';
            }, 3000);
            return false;
        }
        
        // Validate roles again
        if (!validateRoles()) {
            e.preventDefault();
            return false;
        }
        
        // Check if default password is used and no new password set
        var isDefaultPassword = <?= $employee['is_default_password'] == 1 ? 'true' : 'false' ?>;
        var newPassword = document.getElementById('newPassword').value.trim();
        var generatedPassword = document.getElementById('generatedPasswordHidden').value.trim();
        
        if (isDefaultPassword && newPassword === '' && generatedPassword === '') {
            var confirmChange = confirm('⚠️ This employee is using the default password.\n\nWe strongly recommend setting a new password for security.\n\nClick "OK" to continue without changing the password, or "Cancel" to go back and set a new password.');
            if (!confirmChange) {
                e.preventDefault();
                document.getElementById('newPassword').focus();
                document.getElementById('newPassword').style.borderColor = '#EF4444';
                setTimeout(function() {
                    document.getElementById('newPassword').style.borderColor = '';
                }, 3000);
                return false;
            }
        }
        
        // Validate password if entered
        var pass = document.getElementById('newPassword').value.trim();
        var confirm = document.getElementById('confirmPassword').value.trim();
        var genPass = document.getElementById('generatedPasswordHidden').value.trim();
        
        // If password is entered (not empty) validate it
        if (pass !== '' || confirm !== '' || genPass !== '') {
            // If generated password is set, use it
            if (genPass !== '') {
                // Valid
            } else if (pass !== '' || confirm !== '') {
                if (pass !== confirm) {
                    e.preventDefault();
                    showToast('⚠️ Warning', 'Passwords do not match!', 'warning');
                    document.getElementById('confirmPassword').style.borderColor = '#EF4444';
                    setTimeout(function() {
                        document.getElementById('confirmPassword').style.borderColor = '';
                    }, 3000);
                    return false;
                }
                if (pass.length < 6) {
                    e.preventDefault();
                    showToast('⚠️ Warning', 'Password must be at least 6 characters long!', 'warning');
                    document.getElementById('newPassword').style.borderColor = '#EF4444';
                    setTimeout(function() {
                        document.getElementById('newPassword').style.borderColor = '';
                    }, 3000);
                    return false;
                }
            }
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
        var dtEl = document.getElementById('currentDateTime');
        if (dtEl) {
            dtEl.textContent = now.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' }) + ' • ' + 
                now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
        }
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
    // PASSWORD STRENGTH INDICATOR
    // ================================================================
    document.getElementById('newPassword')?.addEventListener('input', function() {
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
        var bars = document.querySelectorAll('#passwordStrength .strength-bar');
        var text = document.getElementById('passwordStrengthText');
        
        bars.forEach(function(bar, index) {
            bar.className = 'strength-bar';
            if (index < strength.score) {
                bar.classList.add(strength.class);
            }
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
    // GENERATE PASSWORD - NEW FORMAT
    // ================================================================
    document.getElementById('generatePasswordBtn')?.addEventListener('click', function() {
        var fullName = document.getElementById('fullName').value.trim();
        var branchId = document.getElementById('branchSelect').value;
        var userId = <?= $employee_id ?>;
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
        
        var passwordBox = document.getElementById('generatedPasswordBox');
        var passwordDisplay = document.getElementById('generatedPasswordDisplay');
        var passwordHidden = document.getElementById('generatedPasswordHidden');
        
        passwordDisplay.textContent = 'Generating...';
        passwordBox.style.display = 'block';
        
        var formData = new FormData();
        formData.append('action', 'generate_password');
        formData.append('full_name', fullName);
        formData.append('branch_id', branchId);
        formData.append('user_id', userId);
        formData.append('current_user_id', userId);
        formData.append('username', username);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            btn.innerHTML = originalText;
            btn.disabled = false;
            
            if (data.success) {
                var password = data.password;
                passwordDisplay.textContent = password;
                document.getElementById('newPassword').value = password;
                document.getElementById('confirmPassword').value = password;
                passwordHidden.value = password;
                
                // Trigger password strength check
                var event = new Event('input', { bubbles: true });
                document.getElementById('newPassword').dispatchEvent(event);
                
                showToast('✅ Success', 'Password generated successfully!', 'success');
                
                // Copy to clipboard
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(password).catch(function() {});
                }
                
                passwordBox.style.borderColor = '#34D399';
                setTimeout(function() {
                    passwordBox.style.borderColor = '#334155';
                }, 2000);
            } else {
                showToast('❌ Error', data.error || 'Failed to generate password', 'error');
                passwordBox.style.display = 'none';
                passwordHidden.value = '';
            }
        })
        .catch(function(error) {
            btn.innerHTML = originalText;
            btn.disabled = false;
            showToast('❌ Error', 'Network error: ' + error.message, 'error');
            passwordBox.style.display = 'none';
            passwordHidden.value = '';
        });
    });

    // ================================================================
    // COPY GENERATED PASSWORD
    // ================================================================
    function copyGeneratedPassword() {
        var passwordDisplay = document.getElementById('generatedPasswordDisplay');
        var password = passwordDisplay.textContent;
        if (password && password !== '****************' && password !== 'Generating...') {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(password).catch(function() {
                    fallbackCopy(password);
                });
            } else {
                fallbackCopy(password);
            }
            showToast('✅ Copied', 'Password copied to clipboard!', 'success');
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

    console.log('%c👤 Braick - Edit Employee', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Employee: <?= htmlspecialchars($employee['full_name']) ?> (ID: <?= $employee_id ?>)', 'font-size:13px; color:#059669;');
    console.log('%c✅ Max 2 roles, one must be Reception', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🔑 Password optional - leave empty to keep current', 'font-size:13px; color:#D97706;');
    console.log('%c🔒 is_default_password: <?= $employee['is_default_password'] ?>', 'font-size:13px; color:#D97706;');
    console.log('%c🔐 Password format: Name(4) + Branch(4) + User(3) = 11 chars', 'font-size:13px; color:#059669;');
</script>

</body>
</html>