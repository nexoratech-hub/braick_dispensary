<?php
// ================================================================
// FILE: frontend/pages/admin/edit_employee.php
// SUPER ADMIN - EDIT EMPLOYEE
// BRAICK DISPENSARY - FIXED WITH PASSWORD INVALIDATION
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
$stmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
$branches_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// AVAILABLE ROLES
// ================================================================
$available_roles = [
    'doctor' => 'Medical Doctor',
    'pharmacy' => 'Pharmacy Staff',
    'reception' => 'Receptionist',
    'laboratory' => 'Lab Technician',
    'cashier' => 'Cashier',
    'admin' => 'Administrator'
];

// ================================================================
// GENERATE PASSWORD FUNCTION
// ================================================================
function generatePassword($full_name, $branch_id, $user_id) {
    $clean_name = preg_replace('/[^a-zA-Z]/', '', $full_name);
    $name_part = strtoupper(substr($clean_name, 0, 4));
    if (strlen($name_part) < 3) {
        $name_part = 'USER';
    }
    
    $branch_code = 'BR' . str_pad($branch_id, 2, '0', STR_PAD_LEFT);
    $user_code = 'UID' . str_pad($user_id, 4, '0', STR_PAD_LEFT);
    
    $password = $name_part . $branch_code . $user_code;
    
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
    'role' => $employee['role'],
    'status' => $employee['status'] ?? 'active',
    'specialty' => $employee['specialty'] ?? '',
    'password' => '',
    'password_changed' => false
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'] ?? 'doctor';
    $status = $_POST['status'] ?? 'active';
    $branch_id = (int)($_POST['branch_id'] ?? 0);
    $specialty = trim($_POST['specialty'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $generated_password = $_POST['generated_password'] ?? '';
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
    
    // Password handling - FIXED: Proper invalidation
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
            
            $sql = "UPDATE users SET 
                    full_name = ?, 
                    username = ?, 
                    email = ?, 
                    phone = ?, 
                    role = ?, 
                    branch_id = ?, 
                    status = ?, 
                    specialty = ?";
            $params = [$full_name, $username, $email, $phone, $role, $branch_id, $status, $specialty];
            
            // ================================================================
            // FIXED: Password invalidation - set is_default_password = 0
            // and password_changed_at = NOW() when password is changed
            // ================================================================
            if ($password_changed && $new_password !== null) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $sql .= ", password = ?";
                $params[] = $hashed_password;
                
                // Invalidate old password - set is_default_password = 0
                $sql .= ", is_default_password = 0";
                
                // Set password_changed_at to NOW() to track when password was changed
                $sql .= ", password_changed_at = NOW()";
            }
            
            $sql .= ", updated_at = NOW() WHERE id = ?";
            $params[] = $employee_id;
            
            $stmt = $db->prepare($sql);
            
            if ($stmt->execute($params)) {
                // Log activity
                try {
                    $stmt = $db->prepare("
                        INSERT INTO activity_logs (user_id, branch_id, action, details, created_at)
                        VALUES (?, ?, 'employee_updated', ?, NOW())
                    ");
                    $details = "Employee {$full_name} updated (Role: {$role})";
                    if ($password_changed) {
                        $details .= " - Password UPDATED and INVALIDATED old password";
                    }
                    $stmt->execute([$user_id, $branch_id, $details]);
                } catch (Exception $e) {}
                
                $db->commit();
                
                $message = "✅ Employee updated successfully!";
                if ($password_changed && $new_password !== null) {
                    $message .= "<br>🔑 <strong>New Password:</strong> <span style='font-family:monospace;background:#1E293B;color:#34D399;padding:4px 12px;border-radius:6px;'>" . htmlspecialchars($new_password) . "</span>";
                    $message .= "<br>📋 Please copy this password and share with the employee.";
                    $message .= "<br>🔒 <span style='color:#EF4444;'>Old password is now INVALID</span>";
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
                    'role' => $employee['role'],
                    'status' => $employee['status'] ?? 'active',
                    'specialty' => $employee['specialty'] ?? '',
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
            'role' => $role,
            'status' => $status,
            'specialty' => $specialty,
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
        
        .form-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 32px 36px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            max-width: 1100px;
            margin: 0 auto;
            box-shadow: var(--shadow-md);
        }
        
        .form-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-lg);
        }
        
        .form-card .form-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 28px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .form-card .form-header .form-icon {
            width: 52px;
            height: 52px;
            background: var(--primary-gradient);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.4rem;
            flex-shrink: 0;
            box-shadow: 0 4px 16px rgba(11, 94, 215, 0.25);
        }
        
        .form-card .form-header .form-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .form-card .form-header .form-subtitle {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 2px;
        }
        
        .form-label {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 5px;
            display: block;
        }
        
        .form-label .required { color: var(--danger); margin-left: 2px; }
        .form-label .label-icon { margin-right: 4px; color: var(--primary); }
        .form-label .label-badge {
            font-weight: 400;
            font-size: 0.6rem;
            padding: 1px 10px;
            border-radius: 12px;
            background: var(--gray-100);
            color: var(--text-secondary);
            margin-left: 6px;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 16px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.85rem;
            transition: all 0.3s ease;
            outline: none;
            background: var(--bg-card);
            color: var(--text-primary);
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.12);
        }
        
        .form-control::placeholder {
            color: var(--text-secondary);
            opacity: 0.5;
        }
        
        select.form-control { appearance: auto; cursor: pointer; }
        
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-row { margin-bottom: 20px; }
        
        .form-row-icon { position: relative; }
        .form-row-icon .form-control { padding-left: 44px; }
        .form-row-icon .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 1rem;
            pointer-events: none;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn:hover { transform: translateY(-2px); }
        
        .btn-primary {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.25);
        }
        .btn-primary:hover { box-shadow: 0 6px 24px rgba(11, 94, 215, 0.35); }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        .btn-outline:hover { border-color: var(--primary); color: var(--primary); }
        
        .btn-sm { padding: 5px 14px; font-size: 0.75rem; border-radius: 8px; }
        
        .form-actions {
            display: flex;
            gap: 12px;
            padding-top: 20px;
            margin-top: 20px;
            border-top: 2px solid var(--border-color);
            flex-wrap: wrap;
        }
        
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            color: white;
            letter-spacing: 0.02em;
        }
        
        .badge-success { background: var(--success); }
        .badge-danger { background: var(--danger); }
        .badge-warning { background: var(--warning); color: #1E293B; }
        .badge-info { background: var(--primary); }
        .badge-secondary { background: #64748B; }
        
        .password-section {
            background: var(--primary-bg);
            border-radius: var(--radius);
            padding: 16px 18px;
            border: 2px solid var(--primary);
            margin-top: 8px;
        }
        
        .password-section .password-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        
        .password-section .form-group { position: relative; }
        
        .password-section .form-group .password-toggle {
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
        }
        
        .password-section .form-group .password-toggle:hover { color: var(--primary); }
        
        .generate-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 18px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            border: none;
            background: var(--success);
            color: white;
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
            min-height: 38px;
        }
        
        .generate-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(5, 150, 105, 0.4);
            background: #047857;
        }
        
        .generated-password-box {
            background: #1E293B;
            color: #34D399;
            padding: 10px 16px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 0.95rem;
            font-weight: 600;
            display: none;
            margin-top: 10px;
            border: 1px solid #334155;
            word-break: break-all;
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
            margin-left: 10px;
        }
        
        .generated-password-box .copy-btn:hover {
            background: rgba(255,255,255,0.2);
            color: white;
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
        
        .password-badge.invalidated {
            background: #FEE2E2;
            color: #DC2626;
            border-color: #F87171;
        }
        
        [data-theme="dark"] .password-badge.invalidated {
            background: #3A1A1A;
            color: #F87171;
            border-color: #DC2626;
        }
        
        .password-action-row {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 4px;
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
        
        .section-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
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
        
        .footer {
            padding: 14px 0;
            border-top: 2px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand { color: var(--primary); font-weight: 700; }
        
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
        
        .role-radio-group {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 10px;
            padding: 12px 14px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            background: var(--bg-body);
            min-height: 60px;
        }
        
        .role-radio-item {
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
        
        .role-radio-item:hover {
            border-color: var(--primary);
            background: var(--primary-bg);
        }
        
        .role-radio-item.selected {
            border-color: var(--primary);
            background: var(--primary-bg);
        }
        
        .role-radio-item input[type="radio"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
            cursor: pointer;
            flex-shrink: 0;
        }
        
        .role-radio-item label {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-primary);
            cursor: pointer;
            width: 100%;
        }
        
        .role-radio-item .role-desc {
            font-size: 0.65rem;
            color: var(--text-secondary);
            font-weight: 400;
            display: block;
            opacity: 0.7;
        }
        
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
            .grid-2 { grid-template-columns: 1fr; gap: 14px; }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .form-card { padding: 16px; }
            .form-actions { flex-direction: column; }
            .form-actions .btn { width: 100%; justify-content: center; }
            .password-section .password-row { grid-template-columns: 1fr; }
            .role-radio-group { grid-template-columns: 1fr 1fr; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .page-header { flex-direction: column; align-items: flex-start !important; }
            .role-radio-group { grid-template-columns: 1fr; }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }
    </style>
</head>
<body>

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

<main class="main-content">

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
        <div class="flex gap-2 flex-wrap" style="position:relative;z-index:1;">
            <a href="employees.php?branch=<?= $selected_branch_id ?>" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'danger' ?>" style="max-width:1100px;margin:0 auto 16px;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <div class="form-card animate-fade-in-up">
        <div class="form-header">
            <div class="form-icon">
                <i class="fas fa-user-edit"></i>
            </div>
            <div>
                <h3 class="form-title">Edit Employee Information</h3>
                <p class="form-subtitle">Update employee details, role, and password</p>
            </div>
        </div>
        
        <form method="POST" action="" id="editEmployeeForm">
            <div class="grid-2">
                <!-- Full Name -->
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-user label-icon"></i> Full Name <span class="required">*</span>
                    </label>
                    <div class="form-row-icon">
                        <input type="text" name="full_name" id="fullName" class="form-control" 
                               placeholder="Enter full name" 
                               value="<?= htmlspecialchars($form_data['full_name']) ?>" required>
                        <span class="input-icon"><i class="fas fa-user"></i></span>
                    </div>
                </div>
                
                <!-- Username -->
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-at label-icon"></i> Username <span class="required">*</span>
                    </label>
                    <div class="form-row-icon">
                        <input type="text" name="username" id="username" class="form-control" 
                               placeholder="Enter username" 
                               value="<?= htmlspecialchars($form_data['username']) ?>" required>
                        <span class="input-icon"><i class="fas fa-at"></i></span>
                    </div>
                </div>
                
                <!-- Email -->
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-envelope label-icon"></i> Email <span class="required">*</span>
                    </label>
                    <div class="form-row-icon">
                        <input type="email" name="email" class="form-control" 
                               placeholder="Enter email" 
                               value="<?= htmlspecialchars($form_data['email']) ?>" required>
                        <span class="input-icon"><i class="fas fa-envelope"></i></span>
                    </div>
                </div>
                
                <!-- Phone -->
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-phone label-icon"></i> Phone Number
                    </label>
                    <div class="form-row-icon">
                        <input type="text" name="phone" class="form-control" 
                               placeholder="Enter phone number" 
                               value="<?= htmlspecialchars($form_data['phone']) ?>">
                        <span class="input-icon"><i class="fas fa-phone"></i></span>
                    </div>
                </div>
                
                <!-- Branch -->
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-store label-icon"></i> Branch <span class="required">*</span>
                    </label>
                    <div class="form-row-icon">
                        <select name="branch_id" id="branchSelect" class="form-control" required>
                            <?php foreach ($branches_list as $branch): ?>
                                <option value="<?= $branch['id'] ?>" <?= $branch['id'] == $form_data['branch_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($branch['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="input-icon"><i class="fas fa-store"></i></span>
                    </div>
                </div>
                
                <!-- Status -->
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-toggle-on label-icon"></i> Status
                    </label>
                    <div class="form-row-icon">
                        <select name="status" class="form-control">
                            <option value="active" <?= $form_data['status'] === 'active' ? 'selected' : '' ?>>✅ Active</option>
                            <option value="inactive" <?= $form_data['status'] === 'inactive' ? 'selected' : '' ?>>⛔ Inactive</option>
                        </select>
                        <span class="input-icon"><i class="fas fa-toggle-on"></i></span>
                    </div>
                </div>
                
                <!-- Role -->
                <div class="form-row">
                    <label class="form-label">
                        <i class="fas fa-user-tag label-icon"></i> Role <span class="required">*</span>
                    </label>
                    <div class="role-radio-group" id="rolesContainer">
                        <?php foreach ($available_roles as $role_key => $role_desc): ?>
                            <div class="role-radio-item <?= $form_data['role'] === $role_key ? 'selected' : '' ?>" 
                                 onclick="selectRole(this, '<?= $role_key ?>')">
                                <input type="radio" name="role" value="<?= $role_key ?>" 
                                       id="role_<?= $role_key ?>"
                                       <?= $form_data['role'] === $role_key ? 'checked' : '' ?>>
                                <label for="role_<?= $role_key ?>">
                                    <i class="fas fa-circle text-[6px] text-primary mr-1"></i>
                                    <?= htmlspecialchars($role_desc) ?>
                                    <span class="role-desc"><?= ucfirst($role_key) ?></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Specialty -->
                <div class="form-row" id="specialtySection" style="display: <?= $form_data['role'] === 'doctor' ? 'block' : 'none' ?>;">
                    <label class="form-label">
                        <i class="fas fa-stethoscope label-icon"></i> Specialty
                        <span class="label-badge">For Doctors</span>
                    </label>
                    <div class="form-row-icon">
                        <input type="text" name="specialty" class="form-control" 
                               placeholder="Enter medical specialty" 
                               value="<?= htmlspecialchars($form_data['specialty']) ?>">
                        <span class="input-icon"><i class="fas fa-stethoscope"></i></span>
                    </div>
                </div>
                
                <!-- Password Section -->
                <div class="form-row" style="grid-column: 1 / -1;">
                    <div class="password-section">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px;">
                            <div style="font-weight:600;color:var(--text-primary);font-size:0.85rem;">
                                <i class="fas fa-key" style="color:var(--primary);margin-right:6px;"></i>
                                Password Settings
                                <?php if ($employee['is_default_password'] == 1): ?>
                                    <span class="password-badge" style="background:#FEF3C7;color:#D97706;border-color:#FCD34D;">
                                        <i class="fas fa-exclamation-triangle"></i> Default Password
                                    </span>
                                <?php else: ?>
                                    <span class="password-badge">
                                        <i class="fas fa-check-circle"></i> Password Set
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;" id="passwordFields">
                            <div style="position:relative;">
                                <label class="form-label" style="font-size:0.7rem;">
                                    <i class="fas fa-lock"></i> New Password
                                    <span style="font-weight:400;font-size:0.6rem;color:var(--text-secondary);margin-left:6px;">Leave empty to keep current</span>
                                </label>
                                <input type="password" name="password" id="newPassword" class="form-control" 
                                       placeholder="Enter new password..." style="padding-right:40px;">
                                <button type="button" class="password-toggle" onclick="togglePasswordVisibility('newPassword', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            
                            <div style="position:relative;">
                                <label class="form-label" style="font-size:0.7rem;">
                                    <i class="fas fa-lock"></i> Confirm Password
                                </label>
                                <input type="password" name="confirm_password" id="confirmPassword" class="form-control" 
                                       placeholder="Confirm new password..." style="padding-right:40px;">
                                <button type="button" class="password-toggle" onclick="togglePasswordVisibility('confirmPassword', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="password-action-row">
                            <button type="button" class="generate-btn" id="generateBtn" onclick="generateAndFillPassword()">
                                <i class="fas fa-magic"></i> Generate Password
                            </button>
                            <span class="password-badge generated" id="generatedBadge" style="display:none;">
                                <i class="fas fa-check"></i> Generated
                            </span>
                        </div>
                        
                        <div class="generated-password-box" id="generatedPasswordBox">
                            <span id="generatedPasswordDisplay">****************</span>
                            <button type="button" class="copy-btn" onclick="copyGeneratedPassword()">
                                <i class="fas fa-copy"></i> Copy
                            </button>
                            <div style="font-size:0.6rem;color:#94A3B8;margin-top:4px;font-family:'Inter',sans-serif;font-weight:400;">
                                <i class="fas fa-sync-alt"></i> Click "Generate Password" again to regenerate
                            </div>
                        </div>
                        
                        <div class="password-info-text">
                            <i class="fas fa-info-circle text-primary mr-1"></i>
                            <?php if ($employee['is_default_password'] == 1): ?>
                                <span style="color:#D97706;font-weight:600;">⚠️ This employee is using the default password.</span>
                                <strong>Please generate or set a new password to secure this account.</strong>
                            <?php else: ?>
                                Current password is set and secure.
                            <?php endif; ?>
                            <br>
                            <i class="fas fa-lightbulb text-warning mr-1"></i>
                            <span style="font-size:0.65rem;">
                                <strong style="color:#EF4444;">🔒 IMPORTANT:</strong> 
                                When you change the password, the <strong style="color:#EF4444;">old password will become INVALID</strong> immediately.
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Hidden field for generated password -->
                <input type="hidden" name="generated_password" id="generatedPasswordHidden" value="">
            </div>
            
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

<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

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

    function switchBranch(branchId) {
        var url = new URL(window.location.href);
        url.searchParams.set('branch', branchId);
        window.location.href = url.toString();
    }

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
    // SELECT ROLE
    // ================================================================
    function selectRole(element, roleKey) {
        document.querySelectorAll('.role-radio-item').forEach(function(el) {
            el.classList.remove('selected');
        });
        element.classList.add('selected');
        
        var radio = document.getElementById('role_' + roleKey);
        if (radio) radio.checked = true;
        
        var specialtySection = document.getElementById('specialtySection');
        if (roleKey === 'doctor') {
            specialtySection.style.display = 'block';
        } else {
            specialtySection.style.display = 'none';
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
    // GENERATE PASSWORD - FIXED
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
        
        generateBtn.disabled = true;
        generateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
        passwordDisplay.textContent = 'Generating...';
        passwordBox.style.display = 'block';
        
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
            generateBtn.disabled = false;
            generateBtn.innerHTML = '<i class="fas fa-magic"></i> Generate Password';
            
            if (data.success) {
                var password = data.password;
                passwordDisplay.textContent = password;
                newPassword.value = password;
                confirmPassword.value = password;
                passwordHidden.value = password;
                generatedBadge.style.display = 'inline-flex';
                
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(password).catch(function() {});
                }
                showToast('🔑 Password Generated', 'Password generated and copied to clipboard!', 'success');
                
                passwordBox.style.borderColor = '#34D399';
                setTimeout(function() {
                    passwordBox.style.borderColor = '#334155';
                }, 2000);
            } else {
                showToast('❌ Error', data.error || 'Failed to generate password', 'error');
                passwordBox.style.display = 'none';
                passwordHidden.value = '';
                generatedBadge.style.display = 'none';
            }
        })
        .catch(function(error) {
            generateBtn.disabled = false;
            generateBtn.innerHTML = '<i class="fas fa-magic"></i> Generate Password';
            showToast('❌ Error', 'Network error: ' + error.message, 'error');
            passwordBox.style.display = 'none';
            passwordHidden.value = '';
            generatedBadge.style.display = 'none';
        });
    }

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

    // ================================================================
    // VALIDATION
    // ================================================================
    document.getElementById('editEmployeeForm')?.addEventListener('submit', function(e) {
        var newPassword = document.getElementById('newPassword');
        var confirmPassword = document.getElementById('confirmPassword');
        var generatedPassword = document.getElementById('generatedPasswordHidden').value;
        var hasCurrentPassword = <?= $employee['password'] ? 'true' : 'false' ?>;
        var isDefaultPassword = <?= $employee['is_default_password'] == 1 ? 'true' : 'false' ?>;
        
        // If using default password, force password change
        if (isDefaultPassword && newPassword.value.trim() === '' && generatedPassword === '') {
            e.preventDefault();
            alert('⚠️ This employee is using the default password. You must generate or enter a new password.');
            document.getElementById('newPassword').focus();
            document.getElementById('newPassword').style.borderColor = '#EF4444';
            setTimeout(function() {
                document.getElementById('newPassword').style.borderColor = '';
            }, 3000);
            return false;
        }
        
        if (newPassword.value.trim() !== '' || confirmPassword.value.trim() !== '') {
            if (newPassword.value.trim() !== confirmPassword.value.trim()) {
                e.preventDefault();
                alert('⚠️ Passwords do not match!');
                confirmPassword.focus();
                confirmPassword.style.borderColor = '#EF4444';
                setTimeout(function() {
                    confirmPassword.style.borderColor = '';
                }, 3000);
                return false;
            }
            if (newPassword.value.trim().length < 6) {
                e.preventDefault();
                alert('⚠️ Password must be at least 6 characters long!');
                newPassword.focus();
                newPassword.style.borderColor = '#EF4444';
                setTimeout(function() {
                    newPassword.style.borderColor = '';
                }, 3000);
                return false;
            }
        }
        
        return true;
    });

    console.log('%c👤 Braick - Edit Employee', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Employee: <?= htmlspecialchars($employee['full_name']) ?> (ID: <?= $employee_id ?>)', 'font-size:13px; color:#059669;');
    console.log('%c🔒 is_default_password: <?= $employee['is_default_password'] ?>', 'font-size:13px; color:#D97706;');
    console.log('%c🔑 Click "Generate Password" to generate a secure password', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🔐 Old password will be INVALIDATED when new password is set', 'font-size:13px; color:#EF4444;');
    console.log('%c📋 Using users table: is_default_password, password_changed_at', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>