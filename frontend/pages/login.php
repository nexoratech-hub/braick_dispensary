<?php
// ================================================================
// FILE: frontend/pages/login.php
// BRAICK DISPENSARY - LOGIN PAGE
// FIXED: AJAX JSON response for admin session
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
// IF ALREADY LOGGED IN - FIXED FOR AJAX
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
$success = '';

// ================================================================
// HANDLE LOGIN
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']) ? true : false;
    $is_ajax = isset($_POST['ajax']) ? true : $is_ajax;
    
    // Function to send JSON response
    function sendJsonResponse($success, $message, $data = []) {
        header('Content-Type: application/json');
        echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
        exit;
    }
    
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
                // ================================================================
                // CHECK PASSWORD
                // ================================================================
                $password_valid = false;
                $is_default_used = false;
                
                if (str_starts_with($user['password'], '$2y$')) {
                    $password_valid = password_verify($password, $user['password']);
                } else {
                    $password_valid = ($password === $user['password']);
                }
                
                if (!$password_valid && $password === '12345678') {
                    if ($user['is_default_password'] == 1) {
                        $password_valid = true;
                        $is_default_used = true;
                    }
                }
                
                if ($password_valid) {
                    // ================================================================
                    // LOGIN SUCCESSFUL
                    // ================================================================
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['phone'] = $user['phone'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['branch_id'] = $user['branch_id'];
                    $_SESSION['specialty'] = $user['specialty'];
                    $_SESSION['profile_pic'] = $user['profile_pic'];
                    $_SESSION['is_online'] = $user['is_online'] ?? 0;
                    $_SESSION['login_time'] = time();
                    
                    if ($is_default_used || $user['is_default_password'] == 1) {
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
                    
                    if ($user['role'] === 'doctor') {
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
                            "User logged in: " . $user['full_name'] . " (Role: " . $user['role'] . ")"
                        ]);
                    } catch (Exception $e) {}
                    
                    // ================================================================
                    // REDIRECT BASED ON ROLE
                    // ================================================================
                    $role = $user['role'];
                    switch ($role) {
                        case 'admin': $redirect_url = 'admin/dashboard.php'; break;
                        case 'doctor': $redirect_url = 'doctor/dashboard.php'; break;
                        case 'reception': $redirect_url = 'reception/dashboard.php'; break;
                        case 'pharmacy': $redirect_url = 'pharmacy/dashboard.php'; break;
                        case 'laboratory': $redirect_url = 'laboratory/dashboard.php'; break;
                        case 'cashier': $redirect_url = 'cashier/dashboard.php'; break;
                        default: $redirect_url = 'dashboard.php'; break;
                    }
                    
                    if ($is_ajax) {
                        sendJsonResponse(true, 'Login successful', ['redirect' => $redirect_url]);
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
            if ($is_ajax) {
                sendJsonResponse(false, 'Login error: ' . $e->getMessage());
            }
            $error = 'Login error: ' . $e->getMessage();
        }
    }
}

// ================================================================
// LOGO PATH
// ================================================================
$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.PNG';

$server_root = $_SERVER['DOCUMENT_ROOT'];
$possible_paths = [
    $server_root . '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.PNG',
    $server_root . '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png',
    $server_root . '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.jpg',
];

foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        $relative = str_replace($server_root, '', $path);
        $logo_url = $relative;
        break;
    }
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
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
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
            --radius: 14px;
            --radius-lg: 24px;
            --shadow-xl: 0 20px 60px rgba(0,0,0,0.2);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #E8F0FE 0%, #D1E0F9 50%, #B8D0F5 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: -50%;
            right: -30%;
            width: 800px;
            height: 800px;
            background: radial-gradient(circle, rgba(11, 94, 215, 0.05) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
            z-index: 0;
        }
        
        body::after {
            content: '';
            position: fixed;
            bottom: -40%;
            left: -20%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(11, 94, 215, 0.04) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
            z-index: 0;
        }
        
        .login-container {
            display: flex;
            background: #FFFFFF;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            max-width: 1100px;
            width: 100%;
            overflow: hidden;
            min-height: 600px;
            position: relative;
            z-index: 1;
            animation: fadeInUp 0.6s ease forwards;
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .login-left {
            flex: 1.2;
            background: linear-gradient(160deg, #0B5ED7 0%, #0A4CA8 50%, #083A8A 100%);
            padding: 50px 45px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .login-left::before {
            content: '';
            position: absolute;
            top: -40%;
            right: -30%;
            width: 500px;
            height: 500px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .login-left::after {
            content: '';
            position: absolute;
            bottom: -30%;
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
            margin-bottom: 0px;
        }
        
        .login-logo-image {
            width: 8rem;
            height: 8rem;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-bottom: 4px;
        }
        
        .login-logo-image img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        .login-logo-image .logo-placeholder {
            font-size: 5rem;
            font-weight: 900;
            color: white;
            letter-spacing: -2px;
        }
        
        .login-brand-text {
            text-align: center;
        }
        
        .login-brand-text .brand-name {
            font-size: 3rem;
            font-weight: 900;
            background: linear-gradient(135deg, #FFFFFF 0%, #93C5FD 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0;
            line-height: 1.1;
        }
        
        .login-brand-text .brand-tagline {
            font-size: 0.9rem;
            font-weight: 400;
            opacity: 0.8;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.85);
            margin-top: 2px;
        }
        
        .login-brand-text .divider-line {
            width: 60px;
            height: 3px;
            background: linear-gradient(90deg, rgba(255,255,255,0.6), rgba(255,255,255,0.1));
            border-radius: 4px;
            margin: 4px auto 4px auto;
        }
        
        .welcome-message {
            font-size: 0.95rem;
            font-weight: 500;
            color: rgba(255,255,255,0.9);
            margin-top: 8px;
            letter-spacing: 1px;
        }
        
        .roles-list {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 6px 14px;
            margin-top: 10px;
            padding: 8px 16px;
            background: rgba(255,255,255,0.08);
            border-radius: 30px;
            border: 1px solid rgba(255,255,255,0.06);
        }
        
        .roles-list .role-item {
            font-size: 0.6rem;
            font-weight: 500;
            color: rgba(255,255,255,0.7);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 2px 0;
        }
        
        .roles-list .role-item .role-dot {
            display: inline-block;
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: rgba(255,255,255,0.3);
            margin-right: 4px;
        }
        
        .roles-list .role-item.highlight {
            color: rgba(255,255,255,0.95);
        }
        
        .roles-list .role-item.highlight .role-dot {
            background: #6EE7B7;
        }
        
        .login-right {
            flex: 1;
            padding: 50px 45px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: #FFFFFF;
        }
        
        .login-right .welcome-text {
            margin-bottom: 28px;
        }
        
        .login-right .welcome-text h2 {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 4px;
        }
        
        .login-right .welcome-text .subtitle {
            color: var(--gray-500);
            font-size: 0.95rem;
        }
        
        .login-right .form-group {
            margin-bottom: 18px;
        }
        
        .login-right .form-group label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 5px;
        }
        
        .login-right .form-group .input-wrapper {
            position: relative;
        }
        
        .login-right .form-group .input-wrapper .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            font-size: 0.9rem;
            transition: color 0.3s ease;
            z-index: 2;
            pointer-events: none;
        }
        
        .login-right .form-group .input-wrapper input {
            width: 100%;
            padding: 13px 46px 13px 46px;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius);
            font-size: 0.95rem;
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
        
        .login-right .form-group .input-wrapper input:focus ~ .input-icon {
            color: var(--primary);
        }
        
        .login-right .form-group .input-wrapper input::placeholder {
            color: var(--gray-400);
        }
        
        .password-toggle {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--gray-400);
            cursor: pointer;
            font-size: 1rem;
            padding: 6px;
            transition: all 0.3s ease;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            width: 32px;
            height: 32px;
        }
        
        .password-toggle:hover {
            color: var(--primary);
            background: var(--gray-100);
        }
        
        .password-toggle:focus {
            outline: none;
            color: var(--primary);
        }
        
        .password-toggle i {
            font-size: 1rem;
        }
        
        .login-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 4px 0 22px 0;
        }
        
        .login-options .remember {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            color: var(--gray-600);
            cursor: pointer;
            user-select: none;
        }
        
        .login-options .remember input[type="checkbox"] {
            width: 17px;
            height: 17px;
            accent-color: var(--primary);
            cursor: pointer;
            border-radius: 4px;
        }
        
        .login-options .forgot {
            font-size: 0.85rem;
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }
        
        .login-options .forgot:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }
        
        /* ================================================================ */
        /* LOGIN BUTTON */
        /* ================================================================ */
        .btn-login {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 100%);
            color: white;
            border: none;
            border-radius: var(--radius);
            font-size: 1rem;
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
        
        .btn-login:hover:not(.loading):not(.success):not(.error) {
            transform: translateY(-2px);
            box-shadow: 0 8px 32px rgba(11, 94, 215, 0.4);
        }
        
        .btn-login:active:not(.loading):not(.success):not(.error) {
            transform: scale(0.98);
        }
        
        .btn-login:disabled {
            cursor: not-allowed;
        }
        
        .btn-login.loading {
            background: linear-gradient(135deg, #0B5ED7 0%, #1A73E8 100%);
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.4);
            animation: btnPulse 0.8s ease-in-out infinite;
        }
        
        @keyframes btnPulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.01); }
            100% { transform: scale(1); }
        }
        
        .btn-login.loading .btn-icon {
            animation: spinIcon 0.8s linear infinite;
        }
        
        @keyframes spinIcon {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .btn-login.success {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            box-shadow: 0 4px 30px rgba(5, 150, 105, 0.6);
            animation: btnSuccessGlow 0.5s ease forwards;
        }
        
        @keyframes btnSuccessGlow {
            0% { box-shadow: 0 4px 20px rgba(5, 150, 105, 0.3); transform: scale(1); }
            50% { box-shadow: 0 4px 50px rgba(5, 150, 105, 0.8); transform: scale(1.03); }
            100% { box-shadow: 0 4px 30px rgba(5, 150, 105, 0.5); transform: scale(1); }
        }
        
        .btn-login.success .btn-text { animation: textPop 0.3s ease forwards; }
        @keyframes textPop {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .btn-login.success .btn-icon { animation: checkPop 0.5s ease forwards; }
        @keyframes checkPop {
            0% { transform: scale(0); }
            50% { transform: scale(1.5); }
            100% { transform: scale(1); }
        }
        
        .btn-login.error {
            background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%);
            box-shadow: 0 4px 30px rgba(220, 38, 38, 0.6);
            animation: btnErrorShake 0.5s ease forwards;
        }
        
        @keyframes btnErrorShake {
            0% { transform: translateX(0); }
            20% { transform: translateX(-10px); }
            40% { transform: translateX(10px); }
            60% { transform: translateX(-6px); }
            80% { transform: translateX(6px); }
            100% { transform: translateX(0); }
        }
        
        .btn-login .ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
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
            font-size: 0.85rem;
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
            margin-top: 20px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--gray-400);
        }
        
        .login-footer .brand {
            color: var(--primary);
            font-weight: 600;
        }
        
        .login-footer .heart {
            color: #EF4444;
        }
        
        @media (max-width: 992px) {
            .login-container { flex-direction: column; max-width: 480px; min-height: auto; }
            .login-left { padding: 32px 24px; border-radius: var(--radius-lg) var(--radius-lg) 0 0; }
            .login-logo-image { width: 6rem; height: 6rem; }
            .login-brand-text .brand-name { font-size: 2.5rem; }
            .login-brand-text .brand-tagline { font-size: 0.8rem; }
            .welcome-message { font-size: 0.85rem; }
            .roles-list { gap: 4px 10px; padding: 6px 12px; }
            .roles-list .role-item { font-size: 0.55rem; }
            .login-right { padding: 32px 24px; }
        }
        
        @media (max-width: 600px) {
            .login-left { padding: 24px 16px; }
            .login-logo-image { width: 4.5rem; height: 4.5rem; }
            .login-brand-text .brand-name { font-size: 2rem; }
            .login-brand-text .brand-tagline { font-size: 0.7rem; letter-spacing: 2px; }
            .welcome-message { font-size: 0.75rem; }
            .roles-list { gap: 3px 8px; padding: 5px 10px; border-radius: 20px; }
            .roles-list .role-item { font-size: 0.5rem; }
            .login-right { padding: 24px 16px; }
            .login-right .welcome-text h2 { font-size: 1.3rem; }
            .login-right .form-group .input-wrapper input { padding: 11px 40px 11px 40px; font-size: 0.9rem; }
            .login-options { flex-direction: column; gap: 10px; align-items: flex-start; }
            .btn-login { padding: 13px; font-size: 0.9rem; }
            .password-toggle { width: 28px; height: 28px; padding: 4px; }
            .password-toggle i { font-size: 0.85rem; }
            .login-brand-text .divider-line { width: 40px; height: 2px; }
        }
        
        @media (max-width: 400px) {
            .login-left { padding: 16px 12px; }
            .login-logo-image { width: 3.5rem; height: 3.5rem; }
            .login-brand-text .brand-name { font-size: 1.5rem; }
            .login-brand-text .brand-tagline { font-size: 0.6rem; }
            .welcome-message { font-size: 0.65rem; }
            .roles-list .role-item { font-size: 0.45rem; }
            .login-right { padding: 16px 12px; }
            .login-right .welcome-text h2 { font-size: 1.1rem; }
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="login-left">
        <div class="login-brand-wrapper">
            <div class="login-logo-image">
                <img src="<?= $logo_url ?>" 
                     alt="Braick Dispensary" 
                     onerror="this.style.display='none'; this.parentElement.innerHTML='<span class=\'logo-placeholder\'>B</span>';">
            </div>
            <div class="login-brand-text">
                <h1 class="brand-name">Braick</h1>
                <div class="divider-line"></div>
                <p class="brand-tagline">Dispensary &amp; Healthcare</p>
            </div>
        </div>
        <div class="welcome-message">Welcome to Braick Dispensary</div>
        <div class="roles-list">
            <span class="role-item highlight"><span class="role-dot"></span>Admin</span>
            <span class="role-item"><span class="role-dot"></span>Reception</span>
            <span class="role-item"><span class="role-dot"></span>Doctor</span>
            <span class="role-item"><span class="role-dot"></span>Lab Technician</span>
            <span class="role-item"><span class="role-dot"></span>Pharmacy</span>
            <span class="role-item"><span class="role-dot"></span>Cashier</span>
        </div>
    </div>
    
    <div class="login-right">
        <div class="welcome-text">
            <h2>Welcome Back</h2>
            <p class="subtitle">Enter your credentials to access your account</p>
        </div>
        
        <div id="alertContainer"></div>
        
        <form method="POST" action="" id="loginForm" autocomplete="off">
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
                <span class="btn-text">Sign In</span>
            </button>
        </form>
        
        <div class="login-footer">
            &copy; <?= date('Y') ?> <span class="brand">Braick Dispensary</span> Management System
            <span style="margin:0 6px;">|</span>
            Made with <span class="heart">❤</span> for healthcare
        </div>
    </div>
</div>

<script>
// ================================================================
// PASSWORD TOGGLE
// ================================================================
document.addEventListener('DOMContentLoaded', function() {
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.getElementById('toggleIcon');
    
    if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', function(e) {
            e.preventDefault();
            const isPassword = passwordInput.getAttribute('type') === 'password';
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
// LOGIN BUTTON - WITH JSON RESPONSE HANDLING
// ================================================================
document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const loginBtn = document.getElementById('loginBtn');
    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');
    const alertContainer = document.getElementById('alertContainer');
    
    function showError(message) {
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-error';
        alertDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + message;
        alertContainer.appendChild(alertDiv);
        
        setTimeout(function() {
            if (alertDiv.parentNode) {
                alertDiv.style.opacity = '0';
                alertDiv.style.transition = 'opacity 0.5s ease';
                setTimeout(function() {
                    if (alertDiv.parentNode) alertDiv.remove();
                }, 500);
            }
        }, 5000);
    }
    
    function clearAlerts() {
        const alerts = alertContainer.querySelectorAll('.alert');
        alerts.forEach(function(el) { el.remove(); });
    }
    
    if (loginForm && loginBtn) {
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            loginBtn.disabled = true;
            
            const username = usernameInput.value.trim();
            const password = passwordInput.value;
            
            if (!username || !password) {
                showError('Please enter both username/email and password.');
                loginBtn.disabled = false;
                return;
            }
            
            // Loading state
            loginBtn.className = 'btn-login loading';
            const btnIcon = loginBtn.querySelector('.btn-icon i');
            const btnText = loginBtn.querySelector('.btn-text');
            
            if (btnIcon) btnIcon.className = 'fas fa-spinner fa-spin';
            if (btnText) btnText.textContent = 'Signing in...';
            
            clearAlerts();
            
            const formData = new FormData(loginForm);
            formData.append('ajax', '1');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(function(response) {
                // Check if response is JSON
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                } else {
                    // If not JSON, something went wrong
                    return response.text().then(function(text) {
                        console.error('Non-JSON response:', text.substring(0, 200));
                        throw new Error('Server returned HTML instead of JSON. Please check server logs.');
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
                    }, 2500);
                } else {
                    loginBtn.className = 'btn-login error';
                    if (btnIcon) btnIcon.className = 'fas fa-times-circle';
                    if (btnText) btnText.textContent = '❌ Failed';
                    
                    showError(data.message || 'Invalid username/email or password.');
                    
                    setTimeout(function() {
                        loginBtn.className = 'btn-login';
                        if (btnIcon) btnIcon.className = 'fas fa-sign-in-alt';
                        if (btnText) btnText.textContent = 'Sign In';
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
                    if (btnText) btnText.textContent = 'Sign In';
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
        const active = document.activeElement;
        if (active && (active.id === 'username' || active.id === 'password')) {
            document.getElementById('loginForm').dispatchEvent(new Event('submit'));
        }
    }
});

// ================================================================
// RIPPLE EFFECT
// ================================================================
document.addEventListener('DOMContentLoaded', function() {
    const loginBtn = document.getElementById('loginBtn');
    if (loginBtn) {
        loginBtn.addEventListener('click', function(e) {
            if (this.classList.contains('loading') || 
                this.classList.contains('success') || 
                this.classList.contains('error')) return;
            
            const ripple = document.createElement('span');
            ripple.classList.add('ripple');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = (e.clientX - rect.left - size/2) + 'px';
            ripple.style.top = (e.clientY - rect.top - size/2) + 'px';
            this.appendChild(ripple);
            setTimeout(function() { ripple.remove(); }, 600);
        });
    }
});

// ================================================================
// AUTO-FOCUS
// ================================================================
document.addEventListener('DOMContentLoaded', function() {
    const username = document.getElementById('username');
    if (!username.value) username.focus();
});

console.log('%c🏥 Braick Dispensary Login', 'font-size:24px; font-weight:bold; color:#0B5ED7;');
console.log('%c✅ AJAX Login with JSON response handling', 'font-size:14px; color:#059669;');
console.log('%c🔑 Default password: 12345678 (only if is_default_password=1)', 'font-size:14px; color:#D97706;');
</script>

</body>
</html>