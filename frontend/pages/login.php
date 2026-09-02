<?php
// ================================================================
// FILE: frontend/pages/login.php
// BRAICK DISPENSARY - LOGIN (SINGLE FORM WITH TOGGLE)
// ================================================================

// ================================================================
// START SESSION
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../backend/config/database.php';

// ================================================================
// CHECK IF AJAX REQUEST
// ================================================================
$is_ajax = isset($_POST['ajax']) || isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';

// ================================================================
// IF ALREADY LOGGED IN
// ================================================================
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    if ($is_ajax) {
        header('Content-Type: application/json');
        $role = $_SESSION['role'];
        switch ($role) {
            case 'admin': $redirect = 'admin/dashboard.php'; break;
            case 'doctor': $redirect = 'doctor/dashboard.php'; break;
            case 'reception': $redirect = 'reception/dashboard.php'; break;
            case 'pharmacy': $redirect = 'pharmacy/dashboard.php'; break;
            case 'laboratory': $redirect = 'laboratory/dashboard.php'; break;
            case 'cashier': $redirect = 'cashier/dashboard.php'; break;
            default: $redirect = 'dashboard.php'; break;
        }
        echo json_encode([
            'success' => true, 
            'redirect' => $redirect,
            'message' => 'Already logged in'
        ]);
        exit;
    }
    
    $role = $_SESSION['role'];
    switch ($role) {
        case 'admin': header('Location: admin/dashboard.php'); break;
        case 'doctor': header('Location: doctor/dashboard.php'); break;
        case 'reception': header('Location: reception/dashboard.php'); break;
        case 'pharmacy': header('Location: pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: laboratory/dashboard.php'); break;
        case 'cashier': header('Location: cashier/dashboard.php'); break;
        default: header('Location: login.php'); break;
    }
    exit;
}

$error = '';
$active_mode = 'general';

// ================================================================
// FUNCTION: Send JSON Response
// ================================================================
function sendJsonResponse($success, $message, $data = array()) {
    header('Content-Type: application/json');
    echo json_encode(array_merge(array('success' => $success, 'message' => $message), $data));
    exit;
}

// ================================================================
// FUNCTION: Get User's Additional Roles (SAFE)
// ================================================================
function getUserAdditionalRoles($db, $user_id) {
    $extra_roles = array();
    try {
        $stmt = $db->query("SHOW TABLES LIKE 'employee_roles'");
        if ($stmt->rowCount() > 0) {
            $stmt = $db->prepare("SELECT role_name FROM employee_roles WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $extra_roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
    } catch (Exception $e) {
        // Table doesn't exist - ignore
    }
    return $extra_roles;
}

// ================================================================
// FUNCTION: Get All User Roles
// ================================================================
function getAllUserRoles($db, $user_id, $primary_role) {
    $roles = array($primary_role);
    $extra = getUserAdditionalRoles($db, $user_id);
    foreach ($extra as $role) {
        if (!in_array($role, $roles)) {
            $roles[] = $role;
        }
    }
    return $roles;
}

// ================================================================
// FUNCTION: Get Primary Role (Non-Reception if available)
// ================================================================
function getPrimaryRoleFromRoles($roles) {
    if (count($roles) === 1) {
        return $roles[0];
    }
    
    $priority_roles = array('admin', 'doctor', 'pharmacy', 'laboratory', 'cashier');
    foreach ($priority_roles as $priority) {
        if (in_array($priority, $roles)) {
            return $priority;
        }
    }
    
    return $roles[0];
}

// ================================================================
// FUNCTION: Check if User has Reception Role
// ================================================================
function hasReceptionRoleInRoles($roles) {
    return in_array('reception', $roles);
}

// ================================================================
// FUNCTION: Get Dashboard URL
// ================================================================
function getDashboardUrlByRole($role) {
    switch ($role) {
        case 'admin': return 'admin/dashboard.php';
        case 'doctor': return 'doctor/dashboard.php';
        case 'reception': return 'reception/dashboard.php';
        case 'pharmacy': return 'pharmacy/dashboard.php';
        case 'laboratory': return 'laboratory/dashboard.php';
        case 'cashier': return 'cashier/dashboard.php';
        default: return 'dashboard.php';
    }
}

// ================================================================
// HANDLE LOGIN - SINGLE HANDLER FOR BOTH MODES
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $login_mode = $_POST['login_mode'] ?? 'general';
    $is_ajax = isset($_POST['ajax']) ? true : $is_ajax;
    
    $active_mode = $login_mode;
    
    if (empty($username) || empty($password)) {
        if ($is_ajax) {
            sendJsonResponse(false, 'Please enter both username/email and password.');
        }
        $error = 'Please enter both username/email and password.';
    } else {
        try {
            $db = Database::getInstance()->getConnection();
            
            $stmt = $db->prepare("
                SELECT id, username, password, full_name, email, phone, role, branch_id, 
                       specialty, is_online, profile_pic, status, created_at,
                       password_changed_at, is_default_password
                FROM users 
                WHERE (username = ? OR email = ?) AND status = 'active'
            ");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $password_valid = false;
                $hash = $user['password'];
                
                if (substr($hash, 0, 4) === '$2y$' || 
                    substr($hash, 0, 4) === '$2a$' || 
                    substr($hash, 0, 4) === '$2x$' || 
                    substr($hash, 0, 4) === '$2b$') {
                    $password_valid = password_verify($password, $hash);
                } else {
                    $password_valid = ($password === $hash);
                }
                
                if ($password_valid) {
                    $all_roles = getAllUserRoles($db, $user['id'], $user['role']);
                    
                    // ================================================================
                    // RECEPTION MODE CHECK
                    // ================================================================
                    if ($login_mode === 'reception' && !hasReceptionRoleInRoles($all_roles)) {
                        if ($is_ajax) {
                            sendJsonResponse(false, 'This user does not have Reception access.');
                        }
                        $error = 'This user does not have Reception access.';
                        goto end_login;
                    }
                    
                    $primary_role = getPrimaryRoleFromRoles($all_roles);
                    $has_reception = hasReceptionRoleInRoles($all_roles);
                    
                    // If reception mode, force role to reception
                    if ($login_mode === 'reception') {
                        $primary_role = 'reception';
                    }
                    
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['phone'] = $user['phone'];
                    $_SESSION['role'] = $primary_role;
                    $_SESSION['roles'] = $all_roles;
                    $_SESSION['has_reception'] = $has_reception;
                    $_SESSION['branch_id'] = $user['branch_id'];
                    $_SESSION['specialty'] = $user['specialty'];
                    $_SESSION['profile_pic'] = $user['profile_pic'];
                    $_SESSION['is_online'] = $user['is_online'] ?? 0;
                    $_SESSION['login_time'] = time();
                    $_SESSION['login_type'] = $login_mode;
                    
                    if ($user['is_default_password'] == 1) {
                        $_SESSION['force_password_change'] = true;
                        $_SESSION['force_password_change_message'] = '⚠️ Please change your default password for security reasons.';
                    }
                    
                    try {
                        $stmt2 = $db->prepare("SELECT name FROM branches WHERE id = ?");
                        $stmt2->execute([$user['branch_id']]);
                        $branch = $stmt2->fetch(PDO::FETCH_ASSOC);
                        $_SESSION['branch_name'] = $branch ? $branch['name'] : 'Dodoma';
                    } catch (Exception $e) {
                        $_SESSION['branch_name'] = 'Dodoma';
                    }
                    
                    if ($user['role'] === 'doctor') {
                        $_SESSION['doctor_id'] = $user['id'];
                    }
                    
                    $stmt = $db->prepare("UPDATE users SET last_online = NOW() WHERE id = ?");
                    $stmt->execute([$user['id']]);
                    
                    if ($primary_role === 'doctor') {
                        $stmt = $db->prepare("UPDATE users SET is_online = 1 WHERE id = ?");
                        $stmt->execute([$user['id']]);
                        $_SESSION['is_online'] = 1;
                    }
                    
                    try {
                        $stmt = $db->prepare("
                            INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) 
                            VALUES (?, ?, 'user_login', ?, NOW())
                        ");
                        $stmt->execute([
                            $user['id'],
                            $user['branch_id'],
                            "User logged in: " . $user['full_name'] . " (Mode: " . $login_mode . ", Role: " . $primary_role . ")"
                        ]);
                    } catch (Exception $e) {}
                    
                    $redirect_url = getDashboardUrlByRole($primary_role);
                    
                    if ($is_ajax) {
                        sendJsonResponse(true, 'Login successful', array('redirect' => $redirect_url));
                    }
                    
                    header('Location: ' . $redirect_url);
                    exit;
                } else {
                    if ($is_ajax) {
                        sendJsonResponse(false, 'Invalid username/email or password. Please try again.');
                    }
                    $error = 'Invalid username/email or password. Please try again.';
                }
            } else {
                if ($is_ajax) {
                    sendJsonResponse(false, 'Invalid username/email or password. Please try again.');
                }
                $error = 'Invalid username/email or password. Please try again.';
            }
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            if ($is_ajax) {
                sendJsonResponse(false, 'Login error: ' . $e->getMessage());
            }
            $error = 'Login error: ' . $e->getMessage();
        }
    }
    
    end_login:
    // End of login handling
}

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.PNG';

$server_root = $_SERVER['DOCUMENT_ROOT'];
$possible_paths = array(
    $server_root . '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.PNG',
    $server_root . '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png',
    $server_root . '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.jpg',
);

foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        $relative = str_replace($server_root, '', $path);
        $logo_url = $relative;
        break;
    }
}

// Check if reception mode is active
if (isset($_POST['login_mode']) && $_POST['login_mode'] === 'reception') {
    $active_mode = 'reception';
} elseif (isset($_GET['mode']) && $_GET['mode'] === 'reception') {
    $active_mode = 'reception';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Braick Dispensary</title>
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* All styles same as before */
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --reception: #059669;
            --reception-dark: #047857;
            --reception-light: #6EE7B7;
            --success: #059669;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-bg: #FEE2E2;
            --gray-50: #F8FAFC;
            --gray-100: #F1F5F9;
            --gray-200: #E2E8F0;
            --gray-300: #CBD5E1;
            --gray-400: #94A3B8;
            --gray-500: #64748B;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1E293B;
            --gray-900: #0F172A;
            --radius: 16px;
            --radius-lg: 28px;
            --shadow-xl: 0 20px 60px rgba(0,0,0,0.2);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 15px;
            position: relative;
            transition: all 0.4s ease;
            background: linear-gradient(135deg, #E8F0FE 0%, #D1E0F9 50%, #B8D0F5 100%);
        }
        
        body.general-mode {
            background: linear-gradient(135deg, #E8F0FE 0%, #D1E0F9 50%, #B8D0F5 100%);
        }
        
        body.reception-mode {
            background: linear-gradient(135deg, #D1FAE5 0%, #A7F3D0 50%, #6EE7B7 100%);
        }
        
        body.dark-mode {
            background: linear-gradient(135deg, #0F172A 0%, #1E293B 50%, #0F172A 100%);
        }
        
        body.dark-mode .login-container {
            background: #1E293B;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        }
        
        body.dark-mode .login-left {
            background: #0F172A;
        }
        
        body.dark-mode .login-left.reception-mode {
            background: #064E3B;
        }
        
        body.dark-mode .login-right {
            background: #1E293B;
        }
        
        body.dark-mode .login-right .form-group .input-wrapper input {
            background: #0F172A;
            border-color: #334155;
            color: #F1F5F9;
        }
        
        body.dark-mode .login-right .form-group .input-wrapper input:focus {
            border-color: var(--primary);
            background: #1E293B;
        }
        
        body.dark-mode .login-right .form-group .input-wrapper .input-icon {
            color: #64748B;
        }
        
        body.dark-mode .login-right .welcome-text h2 {
            color: #F1F5F9;
        }
        
        body.dark-mode .login-right .welcome-text .subtitle {
            color: #94A3B8;
        }
        
        body.dark-mode .login-options .remember {
            color: #94A3B8;
        }
        
        body.dark-mode .login-footer {
            color: #64748B;
        }
        
        body.dark-mode .login-footer .brand {
            color: var(--primary-light);
        }
        
        body.dark-mode .toggle-container {
            background: #0F172A;
            border-color: #334155;
        }
        
        body.dark-mode .toggle-btn {
            color: #94A3B8;
        }
        
        body.dark-mode .toggle-btn:hover:not(.active) {
            background: rgba(255,255,255,0.05);
            color: #F1F5F9;
        }
        
        body.dark-mode .toggle-btn.active {
            background: #1E293B;
            color: var(--primary-light);
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }
        
        body.dark-mode .toggle-btn.active.reception-active {
            color: var(--reception-light);
        }
        
        body.dark-mode .toggle-btn .badge {
            background: #334155;
            color: #94A3B8;
        }
        
        body.dark-mode .toggle-btn.active .badge {
            background: #1E3A5F;
            color: var(--primary-light);
        }
        
        body.dark-mode .toggle-btn.active.reception-active .badge {
            background: #064E3B;
            color: var(--reception-light);
        }
        
        body.dark-mode .login-container .btn-login {
            background: linear-gradient(135deg, #1A73E8 0%, #0B5ED7 100%);
        }
        
        body.dark-mode .login-container.reception-mode .btn-login {
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
        }
        
        body.dark-mode .login-container .btn-login.success {
            background: linear-gradient(135deg, #10B981 0%, #059669 100%) !important;
        }
        
        body.dark-mode .login-container .btn-login.error {
            background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%) !important;
        }
        
        body.dark-mode .password-toggle:hover {
            color: var(--primary-light);
            background: rgba(255,255,255,0.05);
        }
        
        body.dark-mode .login-container.reception-mode .password-toggle:hover {
            color: var(--reception-light);
        }
        
        body.dark-mode .alert-error {
            background: rgba(220, 38, 38, 0.15);
            color: #FCA5A5;
            border-color: rgba(220, 38, 38, 0.3);
        }
        
        body.dark-mode .alert-success {
            background: rgba(5, 150, 105, 0.15);
            color: #6EE7B7;
            border-color: rgba(5, 150, 105, 0.3);
        }
        
        .login-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            max-width: 95%;
            min-height: 90vh;
            position: relative;
            z-index: 1;
        }
        
        .login-container {
            width: 100%;
            max-width: 1200px;
            min-height: 550px;
            max-height: 92vh;
            background: #FFFFFF;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            overflow: hidden;
            display: flex;
            animation: fadeInUp 0.5s ease forwards;
            transition: all 0.4s ease;
            position: relative;
        }
        
        .login-container.reception-mode {
            box-shadow: 0 20px 60px rgba(5, 150, 105, 0.25);
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px) scale(0.97); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        
        .login-left {
            width: 50%;
            min-width: 300px;
            background: var(--primary);
            padding: 45px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            text-align: center;
            position: relative;
            overflow: hidden;
            transition: all 0.4s ease;
        }
        
        .login-left.reception-mode {
            background: var(--reception);
        }
        
        .login-left::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -30%;
            width: 500px;
            height: 500px;
            background: rgba(255,255,255,0.04);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .login-left::after {
            content: '';
            position: absolute;
            bottom: -40%;
            left: -20%;
            width: 400px;
            height: 400px;
            background: rgba(255,255,255,0.03);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .login-brand-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            z-index: 1;
            margin-bottom: 10px;
        }
        
        .login-logo-image {
            width: 7rem;
            height: 7rem;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-bottom: 8px;
        }
        
        .login-logo-image img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        .login-logo-image .logo-placeholder {
            font-size: 4rem;
            font-weight: 900;
            color: white;
            letter-spacing: -2px;
        }
        
        .login-brand-text {
            text-align: center;
        }
        
        .login-brand-text .brand-name {
            font-size: 3.2rem;
            font-weight: 900;
            background: linear-gradient(135deg, #FFFFFF 0%, #93C5FD 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0;
            line-height: 1.1;
        }
        
        .login-left.reception-mode .login-brand-text .brand-name {
            background: linear-gradient(135deg, #FFFFFF 0%, #6EE7B7 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .login-brand-text .brand-tagline {
            font-size: 0.9rem;
            font-weight: 400;
            opacity: 0.8;
            letter-spacing: 4px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.85);
            margin-top: 4px;
        }
        
        .login-brand-text .divider-line {
            width: 60px;
            height: 3px;
            background: linear-gradient(90deg, rgba(255,255,255,0.6), rgba(255,255,255,0.1));
            border-radius: 4px;
            margin: 6px auto 6px auto;
        }
        
        .login-mode-label {
            font-size: 0.85rem;
            font-weight: 500;
            color: rgba(255,255,255,0.9);
            margin-top: 8px;
            letter-spacing: 0.5px;
        }
        
        .login-mode-label i {
            margin-right: 6px;
        }
        
        .toggle-container {
            display: flex;
            flex-direction: column;
            gap: 10px;
            width: 100%;
            max-width: 280px;
            margin-top: 16px;
            position: relative;
            z-index: 1;
        }
        
        .toggle-btn {
            width: 100%;
            padding: 14px 24px;
            border: 2px solid rgba(255,255,255,0.15);
            border-radius: var(--radius);
            font-size: 1rem;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
            background: rgba(255,255,255,0.05);
            color: rgba(255,255,255,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            backdrop-filter: blur(4px);
        }
        
        .toggle-btn:hover:not(.active) {
            background: rgba(255,255,255,0.12);
            color: rgba(255,255,255,0.9);
            border-color: rgba(255,255,255,0.25);
        }
        
        .toggle-btn.active {
            background: rgba(255,255,255,0.2);
            color: #FFFFFF;
            border-color: rgba(255,255,255,0.4);
            box-shadow: 0 2px 12px rgba(0,0,0,0.15);
        }
        
        .toggle-btn i {
            font-size: 1.1rem;
        }
        
        .toggle-btn .badge {
            font-size: 0.6rem;
            background: rgba(255,255,255,0.15);
            padding: 2px 12px;
            border-radius: 10px;
            color: rgba(255,255,255,0.7);
            font-weight: 700;
        }
        
        .toggle-btn.active .badge {
            background: rgba(255,255,255,0.2);
            color: rgba(255,255,255,0.9);
        }
        
        .login-right {
            width: 50%;
            min-width: 300px;
            padding: 45px 50px 48px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: #FFFFFF;
            transition: all 0.4s ease;
        }
        
        .login-right .welcome-text {
            margin-bottom: 22px;
        }
        
        .login-right .welcome-text h2 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 4px;
            transition: all 0.4s ease;
        }
        
        .login-right .welcome-text .subtitle {
            color: var(--gray-500);
            font-size: 1rem;
            transition: all 0.4s ease;
        }
        
        .login-right .form-group {
            margin-bottom: 18px;
        }
        
        .login-right .form-group label {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 5px;
            transition: all 0.4s ease;
        }
        
        .login-right .form-group .input-wrapper {
            position: relative;
        }
        
        .login-right .form-group .input-wrapper .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            font-size: 1.05rem;
            transition: color 0.3s ease;
            z-index: 2;
            pointer-events: none;
        }
        
        .login-right .form-group .input-wrapper input {
            width: 100%;
            padding: 15px 52px 15px 52px;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius);
            font-size: 1.05rem;
            font-family: 'Inter', sans-serif;
            background: var(--gray-50);
            transition: all 0.3s ease;
            color: var(--gray-800);
        }
        
        .login-right .form-group .input-wrapper input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.1);
            background: white;
            outline: none;
        }
        
        .login-container.reception-mode .login-right .form-group .input-wrapper input:focus {
            border-color: var(--reception);
            box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.1);
        }
        
        .login-right .form-group .input-wrapper input::placeholder {
            color: var(--gray-400);
            font-size: 1rem;
        }
        
        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--gray-400);
            cursor: pointer;
            font-size: 1.05rem;
            padding: 6px;
            transition: all 0.3s ease;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            width: 38px;
            height: 38px;
        }
        
        .password-toggle:hover {
            color: var(--primary);
            background: var(--gray-100);
        }
        
        .login-container.reception-mode .password-toggle:hover {
            color: var(--reception);
        }
        
        .password-toggle:focus {
            outline: none;
        }
        
        .password-toggle i {
            font-size: 1.05rem;
        }
        
        .login-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 4px 0 20px 0;
        }
        
        .login-options .remember {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.95rem;
            color: var(--gray-600);
            cursor: pointer;
            user-select: none;
        }
        
        .login-options .remember input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
            cursor: pointer;
            border-radius: 4px;
        }
        
        .login-container.reception-mode .login-options .remember input[type="checkbox"] {
            accent-color: var(--reception);
        }
        
        .login-options .forgot {
            font-size: 0.95rem;
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }
        
        .login-options .forgot:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }
        
        .login-container.reception-mode .login-options .forgot {
            color: var(--reception);
        }
        
        .login-container.reception-mode .login-options .forgot:hover {
            color: var(--reception-dark);
        }
        
        .btn-login {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 100%);
            color: white;
            border: none;
            border-radius: var(--radius);
            font-size: 1.1rem;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 4px 16px rgba(11, 94, 215, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .login-container.reception-mode .btn-login {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            box-shadow: 0 4px 16px rgba(5, 150, 105, 0.3);
        }
        
        .btn-login:hover:not(.loading):not(.success):not(.error) {
            transform: translateY(-2px);
            box-shadow: 0 8px 32px rgba(11, 94, 215, 0.4);
        }
        
        .login-container.reception-mode .btn-login:hover:not(.loading):not(.success):not(.error) {
            box-shadow: 0 8px 32px rgba(5, 150, 105, 0.4);
        }
        
        .btn-login:active:not(.loading):not(.success):not(.error) {
            transform: scale(0.97);
        }
        
        .btn-login:disabled {
            cursor: not-allowed;
        }
        
        .btn-login.loading {
            background: linear-gradient(135deg, #0B5ED7 0%, #1A73E8 100%);
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.4);
            animation: btnPulse 0.8s ease-in-out infinite;
        }
        
        .login-container.reception-mode .btn-login.loading {
            background: linear-gradient(135deg, #059669 0%, #10B981 100%);
            box-shadow: 0 4px 20px rgba(5, 150, 105, 0.4);
        }
        
        .btn-login.success {
            background: linear-gradient(135deg, #059669 0%, #047857 100%) !important;
            box-shadow: 0 4px 30px rgba(5, 150, 105, 0.6) !important;
            animation: btnSuccessGlow 0.5s ease forwards;
        }
        
        .btn-login.error {
            background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%) !important;
            box-shadow: 0 4px 30px rgba(220, 38, 38, 0.6) !important;
            animation: btnErrorShake 0.5s ease forwards;
        }
        
        @keyframes btnPulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }
        
        @keyframes btnSuccessGlow {
            0% { box-shadow: 0 4px 20px rgba(5, 150, 105, 0.3); transform: scale(1); }
            50% { box-shadow: 0 4px 50px rgba(5, 150, 105, 0.8); transform: scale(1.03); }
            100% { box-shadow: 0 4px 30px rgba(5, 150, 105, 0.5); transform: scale(1); }
        }
        
        @keyframes btnErrorShake {
            0% { transform: translateX(0); }
            20% { transform: translateX(-10px); }
            40% { transform: translateX(10px); }
            60% { transform: translateX(-6px); }
            80% { transform: translateX(6px); }
            100% { transform: translateX(0); }
        }
        
        .btn-login .btn-icon i.fa-spinner {
            animation: spinIcon 0.8s linear infinite;
        }
        
        @keyframes spinIcon {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .btn-login .ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.25);
            transform: scale(0);
            animation: rippleAnim 0.6s linear;
            pointer-events: none;
        }
        
        @keyframes rippleAnim {
            to { transform: scale(4); opacity: 0; }
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: var(--radius);
            margin-bottom: 16px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .alert-error {
            background: var(--danger-bg);
            color: #DC2626;
            border: 1px solid #FCA5A5;
        }
        
        .alert-success {
            background: var(--success-bg);
            color: #059669;
            border: 1px solid #6EE7B7;
        }
        
        .login-footer {
            margin-top: 18px;
            text-align: center;
            font-size: 0.8rem;
            color: var(--gray-400);
            transition: all 0.4s ease;
        }
        
        .login-footer .brand {
            color: var(--primary);
            font-weight: 600;
            transition: all 0.4s ease;
        }
        
        .login-container.reception-mode .login-footer .brand {
            color: var(--reception);
        }
        
        .login-footer .heart {
            color: #EF4444;
        }
        
        .dark-mode-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            background: rgba(255,255,255,0.9);
            border: none;
            border-radius: 50%;
            width: 48px;
            height: 48px;
            font-size: 1.2rem;
            cursor: pointer;
            box-shadow: 0 2px 12px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            color: var(--gray-700);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .dark-mode-toggle:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        
        body.dark-mode .dark-mode-toggle {
            background: #1E293B;
            color: #F1F5F9;
            box-shadow: 0 2px 12px rgba(0,0,0,0.3);
        }
        
        body.dark-mode .dark-mode-toggle:hover {
            background: #334155;
        }
        
        @media (max-width: 1024px) {
            .login-wrapper { max-width: 98%; }
            .login-container { max-width: 100%; min-height: 480px; max-height: 95vh; }
            .login-left { padding: 35px 30px; min-width: 250px; }
            .login-right { padding: 35px 35px 38px 35px; min-width: 250px; }
            .login-logo-image { width: 5.5rem; height: 5.5rem; }
            .login-brand-text .brand-name { font-size: 2.6rem; }
            .login-brand-text .brand-tagline { font-size: 0.75rem; }
            .login-right .welcome-text h2 { font-size: 1.6rem; }
            .login-right .form-group .input-wrapper input { padding: 13px 46px 13px 46px; font-size: 0.95rem; }
            .btn-login { padding: 13px; font-size: 1rem; }
            .toggle-btn { font-size: 0.85rem; padding: 12px 18px; }
            .toggle-container { max-width: 240px; }
        }
        
        @media (max-width: 768px) {
            .login-wrapper { max-width: 100%; padding: 0 10px; min-height: auto; }
            .login-container { flex-direction: column; min-height: auto; max-height: none; border-radius: var(--radius-lg); }
            .login-left { width: 100%; padding: 30px 24px; border-radius: var(--radius-lg) var(--radius-lg) 0 0; min-width: auto; }
            .login-right { width: 100%; padding: 28px 28px 30px 28px; min-width: auto; }
            .login-logo-image { width: 5rem; height: 5rem; }
            .login-brand-text .brand-name { font-size: 2.4rem; }
            .login-brand-text .brand-tagline { font-size: 0.7rem; }
            .login-right .welcome-text h2 { font-size: 1.4rem; }
            .login-right .welcome-text .subtitle { font-size: 0.85rem; }
            .login-right .form-group .input-wrapper input { padding: 12px 42px 12px 42px; font-size: 0.9rem; }
            .btn-login { padding: 12px; font-size: 0.95rem; }
            .toggle-container { flex-direction: row; max-width: 100%; gap: 8px; margin-top: 10px; }
            .toggle-btn { font-size: 0.8rem; padding: 10px 16px; }
            .dark-mode-toggle { top: 14px; right: 14px; width: 42px; height: 42px; font-size: 1rem; }
        }
        
        @media (max-width: 480px) {
            .login-left { padding: 22px 16px; }
            .login-logo-image { width: 4rem; height: 4rem; }
            .login-brand-text .brand-name { font-size: 1.8rem; }
            .login-brand-text .brand-tagline { font-size: 0.6rem; letter-spacing: 2px; }
            .login-brand-text .divider-line { width: 40px; height: 2px; }
            .login-right { padding: 20px 16px 22px 16px; }
            .login-right .welcome-text h2 { font-size: 1.2rem; }
            .login-right .welcome-text .subtitle { font-size: 0.75rem; }
            .login-right .form-group { margin-bottom: 12px; }
            .login-right .form-group label { font-size: 0.75rem; }
            .login-right .form-group .input-wrapper input { padding: 10px 38px 10px 38px; font-size: 0.85rem; }
            .login-right .form-group .input-wrapper .input-icon { font-size: 0.85rem; left: 12px; }
            .password-toggle { width: 32px; height: 32px; right: 12px; }
            .password-toggle i { font-size: 0.85rem; }
            .login-options { flex-direction: column; gap: 6px; align-items: flex-start; margin: 2px 0 14px 0; }
            .login-options .remember { font-size: 0.75rem; }
            .login-options .forgot { font-size: 0.75rem; }
            .btn-login { padding: 10px; font-size: 0.85rem; gap: 8px; }
            .login-footer { font-size: 0.65rem; margin-top: 12px; }
            .toggle-btn { font-size: 0.65rem; padding: 8px 12px; }
            .toggle-btn i { font-size: 0.7rem; }
            .toggle-btn .badge { font-size: 0.45rem; padding: 1px 6px; }
            .dark-mode-toggle { top: 10px; right: 10px; width: 36px; height: 36px; font-size: 0.85rem; }
        }
    </style>
</head>
<body class="<?= $active_mode === 'reception' ? 'reception-mode' : 'general-mode' ?>" id="bodyElement">

<!-- ================================================================ -->
<!-- DARK MODE TOGGLE -->
<!-- ================================================================ -->
<button class="dark-mode-toggle" id="darkModeToggle" aria-label="Toggle Dark Mode" title="Toggle Dark Mode">
    <i class="fas fa-moon" id="darkModeIcon"></i>
</button>

<!-- ================================================================ -->
<!-- LOGIN FORM -->
<!-- ================================================================ -->
<div class="login-wrapper">
    <div class="login-container <?= $active_mode === 'reception' ? 'reception-mode' : '' ?>" id="loginContainer">
        <!-- ================================================================ -->
        <!-- LEFT PANEL -->
        <!-- ================================================================ -->
        <div class="login-left <?= $active_mode === 'reception' ? 'reception-mode' : '' ?>" id="leftPanel">
            <div class="login-brand-wrapper">
                <div class="login-logo-image">
                    <img src="<?= $logo_url ?>" 
                         alt="Braick Dispensary" 
                         onerror="this.style.display='none'; this.parentElement.innerHTML='<span class=\'logo-placeholder\'>B</span>';">
                </div>
                <div class="login-brand-text">
                    <h1 class="brand-name">Braick</h1>
                    <div class="divider-line"></div>
                    <p class="brand-tagline" id="brandTagline">Dispensary &amp; Healthcare</p>
                </div>
            </div>
            <div class="login-mode-label" id="modeLabel">
                <i class="fas fa-users"></i> General Access
            </div>
            
            <!-- ================================================================ -->
            <!-- TOGGLE BUTTONS -->
            <!-- ================================================================ -->
            <div class="toggle-container">
                <button type="button" class="toggle-btn <?= $active_mode === 'general' ? 'active' : '' ?>" 
                        id="generalToggle" data-mode="general">
                    <i class="fas fa-sign-in-alt"></i>
                    General
                    <span class="badge">All</span>
                </button>
                <button type="button" class="toggle-btn <?= $active_mode === 'reception' ? 'active' : '' ?>" 
                        id="receptionToggle" data-mode="reception">
                    <i class="fas fa-user-tie"></i>
                    Reception
                    <span class="badge">Only</span>
                </button>
            </div>
        </div>
        
        <!-- ================================================================ -->
        <!-- RIGHT PANEL - Form -->
        <!-- ================================================================ -->
        <div class="login-right">
            <div class="welcome-text">
                <h2 id="formTitle">Welcome Back</h2>
                <p class="subtitle" id="formSubtitle">Enter your credentials to access your account</p>
            </div>
            
            <div id="alertContainer"></div>
            
            <!-- ================================================================ -->
            <!-- SINGLE FORM -->
            <!-- ================================================================ -->
            <form method="POST" action="" id="loginForm" autocomplete="off">
                <input type="hidden" name="login_mode" id="loginMode" value="<?= $active_mode ?>">
                
                <div class="form-group">
                    <label for="username">Username or Email</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" id="username" name="username" 
                               placeholder="Enter your username or email" 
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" 
                               required autofocus>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" id="password" name="password" 
                               placeholder="Enter your password" required>
                        <button type="button" class="password-toggle" id="togglePassword" aria-label="Toggle password visibility" title="Show/Hide password">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>
                
                <div class="login-options">
                    <label class="remember">
                        <input type="checkbox" name="remember" <?= isset($_POST['remember']) ? 'checked' : '' ?>>
                        Remember me
                    </label>
                    <a href="forgot_password.php" class="forgot">Forgot password?</a>
                </div>
                
                <button type="submit" class="btn-login" id="loginBtn">
                    <span class="btn-icon"><i class="fas fa-sign-in-alt"></i></span>
                    <span class="btn-text" id="btnText">Sign In</span>
                </button>
            </form>
            
            <div class="login-footer">
                &copy; <?= date('Y') ?> <span class="brand">Braick Dispensary</span>
                <span style="margin:0 4px;">|</span>
                Made with <span class="heart">❤</span>
            </div>
        </div>
    </div>
</div>

<script>
// ================================================================
// DARK MODE TOGGLE
// ================================================================
document.addEventListener('DOMContentLoaded', function() {
    var darkToggle = document.getElementById('darkModeToggle');
    var darkIcon = document.getElementById('darkModeIcon');
    
    if (localStorage.getItem('darkMode') === 'enabled') {
        document.body.classList.add('dark-mode');
        darkIcon.className = 'fas fa-sun';
    }
    
    darkToggle.addEventListener('click', function() {
        document.body.classList.toggle('dark-mode');
        var isDark = document.body.classList.contains('dark-mode');
        darkIcon.className = isDark ? 'fas fa-sun' : 'fas fa-moon';
        localStorage.setItem('darkMode', isDark ? 'enabled' : 'disabled');
    });
});

// ================================================================
// TOGGLE BETWEEN GENERAL AND RECEPTION MODE
// ================================================================
document.addEventListener('DOMContentLoaded', function() {
    var generalToggle = document.getElementById('generalToggle');
    var receptionToggle = document.getElementById('receptionToggle');
    var loginContainer = document.getElementById('loginContainer');
    var leftPanel = document.getElementById('leftPanel');
    var bodyElement = document.getElementById('bodyElement');
    var brandTagline = document.getElementById('brandTagline');
    var modeLabel = document.getElementById('modeLabel');
    var formTitle = document.getElementById('formTitle');
    var formSubtitle = document.getElementById('formSubtitle');
    var loginMode = document.getElementById('loginMode');
    var loginBtn = document.getElementById('loginBtn');
    var btnText = document.getElementById('btnText');
    var btnIcon = loginBtn.querySelector('.btn-icon i');
    var alertContainer = document.getElementById('alertContainer');
    
    function setMode(mode) {
        // Update body class
        bodyElement.classList.remove('general-mode', 'reception-mode');
        if (mode === 'general') {
            bodyElement.classList.add('general-mode');
        } else {
            bodyElement.classList.add('reception-mode');
        }
        
        // Update container class
        loginContainer.classList.remove('reception-mode');
        if (mode === 'reception') {
            loginContainer.classList.add('reception-mode');
        }
        
        // Update left panel class
        leftPanel.classList.remove('reception-mode');
        if (mode === 'reception') {
            leftPanel.classList.add('reception-mode');
        }
        
        // Update toggle buttons
        generalToggle.classList.remove('active');
        receptionToggle.classList.remove('active');
        
        if (mode === 'general') {
            generalToggle.classList.add('active');
            brandTagline.textContent = 'Dispensary & Healthcare';
            modeLabel.innerHTML = '<i class="fas fa-users"></i> General Access';
            formTitle.textContent = 'Welcome Back';
            formSubtitle.textContent = 'Login with any role (Non-Reception preferred)';
            btnText.textContent = 'Sign In';
            loginMode.value = 'general';
            if (btnIcon) btnIcon.className = 'fas fa-sign-in-alt';
            loginBtn.className = 'btn-login';
            loginBtn.disabled = false;
        } else {
            receptionToggle.classList.add('active');
            brandTagline.textContent = 'Front Desk Access';
            modeLabel.innerHTML = '<i class="fas fa-user-tie"></i> Reception Only';
            formTitle.textContent = 'Reception Login';
            formSubtitle.textContent = 'Only users with <strong>Reception</strong> role can login here';
            btnText.textContent = 'Login as Reception';
            loginMode.value = 'reception';
            if (btnIcon) btnIcon.className = 'fas fa-sign-in-alt';
            loginBtn.className = 'btn-login';
            loginBtn.disabled = false;
        }
        
        // Clear alerts
        var alerts = alertContainer.querySelectorAll('.alert');
        alerts.forEach(function(el) { el.remove(); });
        
        document.getElementById('username').focus();
    }
    
    generalToggle.addEventListener('click', function() {
        if (!this.classList.contains('active')) {
            setMode('general');
        }
    });
    
    receptionToggle.addEventListener('click', function() {
        if (!this.classList.contains('active')) {
            setMode('reception');
        }
    });
});

// ================================================================
// PASSWORD TOGGLE
// ================================================================
document.addEventListener('DOMContentLoaded', function() {
    var togglePassword = document.getElementById('togglePassword');
    var passwordInput = document.getElementById('password');
    var toggleIcon = document.getElementById('toggleIcon');
    
    if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', function(e) {
            e.preventDefault();
            var isPassword = passwordInput.getAttribute('type') === 'password';
            passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
            
            if (isPassword) {
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
            passwordInput.focus();
        });
    }
});

// ================================================================
// LOGIN FORM HANDLER
// ================================================================
document.addEventListener('DOMContentLoaded', function() {
    var loginForm = document.getElementById('loginForm');
    var loginBtn = document.getElementById('loginBtn');
    var usernameInput = document.getElementById('username');
    var passwordInput = document.getElementById('password');
    var alertContainer = document.getElementById('alertContainer');
    var loginMode = document.getElementById('loginMode');
    
    function showError(message) {
        var alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-error';
        alertDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + message;
        alertContainer.appendChild(alertDiv);
        
        setTimeout(function() {
            if (alertDiv.parentNode) {
                alertDiv.style.opacity = '0';
                alertDiv.style.transition = 'opacity 0.4s ease';
                setTimeout(function() {
                    if (alertDiv.parentNode) alertDiv.remove();
                }, 400);
            }
        }, 5000);
    }
    
    function clearAlerts() {
        var alerts = alertContainer.querySelectorAll('.alert');
        alerts.forEach(function(el) { el.remove(); });
    }
    
    if (loginForm && loginBtn) {
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            loginBtn.disabled = true;
            
            var username = usernameInput.value.trim();
            var password = passwordInput.value;
            var mode = loginMode.value;
            
            if (!username || !password) {
                showError('Please enter both username/email and password.');
                loginBtn.disabled = false;
                return;
            }
            
            loginBtn.className = 'btn-login loading';
            var btnIcon = loginBtn.querySelector('.btn-icon i');
            var btnText = loginBtn.querySelector('.btn-text');
            
            if (btnIcon) btnIcon.className = 'fas fa-spinner fa-spin';
            if (btnText) btnText.textContent = mode === 'reception' ? 'Logging in...' : 'Signing in...';
            
            clearAlerts();
            
            var formData = new FormData(loginForm);
            formData.append('ajax', '1');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(function(response) {
                var contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                } else {
                    return response.text().then(function(text) {
                        console.error('Non-JSON response:', text.substring(0, 500));
                        throw new Error('Server returned non-JSON response.');
                    });
                }
            })
            .then(function(data) {
                if (data.success) {
                    loginBtn.className = 'btn-login success';
                    if (btnIcon) btnIcon.className = 'fas fa-check-circle';
                    if (btnText) btnText.textContent = '✅ Success!';
                    
                    setTimeout(function() {
                        window.location.href = data.redirect || 'dashboard.php';
                    }, 1200);
                } else {
                    loginBtn.className = 'btn-login error';
                    if (btnIcon) btnIcon.className = 'fas fa-times-circle';
                    if (btnText) btnText.textContent = '❌ Failed';
                    
                    showError(data.message || 'Invalid username/email or password.');
                    
                    setTimeout(function() {
                        loginBtn.className = 'btn-login';
                        if (btnIcon) btnIcon.className = 'fas fa-sign-in-alt';
                        if (btnText) btnText.textContent = mode === 'reception' ? 'Login as Reception' : 'Sign In';
                        loginBtn.disabled = false;
                        passwordInput.focus();
                        passwordInput.select();
                    }, 2500);
                }
            })
            .catch(function(error) {
                console.error('Login error:', error);
                loginBtn.className = 'btn-login error';
                if (btnIcon) btnIcon.className = 'fas fa-times-circle';
                if (btnText) btnText.textContent = '❌ Error';
                
                showError('Network error: ' + error.message);
                
                setTimeout(function() {
                    loginBtn.className = 'btn-login';
                    if (btnIcon) btnIcon.className = 'fas fa-sign-in-alt';
                    if (btnText) btnText.textContent = mode === 'reception' ? 'Login as Reception' : 'Sign In';
                    loginBtn.disabled = false;
                }, 2500);
            });
        });
    }
});

// ================================================================
// ENTER KEY SUPPORT
// ================================================================
document.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        var active = document.activeElement;
        if (active && (active.id === 'username' || active.id === 'password')) {
            document.getElementById('loginForm').dispatchEvent(new Event('submit'));
        }
    }
});

// ================================================================
// RIPPLE EFFECT
// ================================================================
document.addEventListener('DOMContentLoaded', function() {
    var loginBtn = document.getElementById('loginBtn');
    if (loginBtn) {
        loginBtn.addEventListener('click', function(e) {
            if (this.classList.contains('loading') || 
                this.classList.contains('success') || 
                this.classList.contains('error')) return;
            
            var ripple = document.createElement('span');
            ripple.classList.add('ripple');
            var rect = this.getBoundingClientRect();
            var size = Math.max(rect.width, rect.height);
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = (e.clientX - rect.left - size/2) + 'px';
            ripple.style.top = (e.clientY - rect.top - size/2) + 'px';
            this.appendChild(ripple);
            setTimeout(function() { ripple.remove(); }, 500);
        });
    }
});

// ================================================================
// AUTO-FOCUS
// ================================================================
document.addEventListener('DOMContentLoaded', function() {
    var username = document.getElementById('username');
    if (!username.value) username.focus();
});

console.log('%c🏥 Braick Dispensary - Login (Single Form with Toggle)', 'font-size:24px; font-weight:bold; color:#0B5ED7;');
console.log('%c✅ Single form - ONE handler for both General and Reception', 'font-size:14px; color:#059669;');
console.log('%c✅ Toggle buttons change UI mode only', 'font-size:14px; color:#D97706;');
console.log('%c✅ Reception mode checks if user has reception role', 'font-size:14px; color:#D97706;');
</script>

</body>
</html>