<?php
// ================================================================
// FILE: frontend/pages/login.php
// BRAICK DISPENSARY - LOGIN PAGE
// ================================================================

session_start();

// ================================================================
// IF ALREADY LOGGED IN, REDIRECT TO DASHBOARD
// ================================================================
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'admin':
            header('Location: admin/dashboard.php');
            break;
        case 'doctor':
            header('Location: doctor/dashboard.php');
            break;
        case 'reception':
            header('Location: reception/dashboard.php');
            break;
        case 'pharmacy':
            header('Location: pharmacy/dashboard.php');
            break;
        case 'laboratory':
            header('Location: laboratory/dashboard.php');
            break;
        case 'cashier':
            header('Location: cashier/dashboard.php');
            break;
        default:
            header('Location: login.php');
            break;
    }
    exit;
}

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../backend/config/database.php';
$db = Database::getInstance()->getConnection();

$error = '';
$success = '';

// ================================================================
// HANDLE LOGIN
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']) ? true : false;
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username/email and password.';
    } else {
        try {
            // Get user by username OR email
            $stmt = $db->prepare("
                SELECT id, username, password, full_name, email, phone, role, branch_id, 
                       specialty, is_online, profile_pic, status, created_at 
                FROM users 
                WHERE (username = ? OR email = ?) AND status = 'active'
            ");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Check password - supports both plain and hashed
                $password_valid = false;
                
                // Check if password is hashed (starts with $2y$)
                if (str_starts_with($user['password'], '$2y$')) {
                    $password_valid = password_verify($password, $user['password']);
                } else {
                    // Plain text password match
                    $password_valid = ($password === $user['password']);
                }
                
                // Extra check for plain text (just in case)
                if (!$password_valid) {
                    $password_valid = ($password === $user['password']);
                }
                
                if ($password_valid) {
                    // Login successful - Set session
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
                    
                    // If doctor, also set doctor_id
                    if ($user['role'] === 'doctor') {
                        $_SESSION['doctor_id'] = $user['id'];
                    }
                    
                    // Update last login time
                    $stmt = $db->prepare("UPDATE users SET last_online = NOW() WHERE id = ?");
                    $stmt->execute([$user['id']]);
                    
                    // If doctor, set online status
                    if ($user['role'] === 'doctor') {
                        $stmt = $db->prepare("UPDATE users SET is_online = 1 WHERE id = ?");
                        $stmt->execute([$user['id']]);
                        $_SESSION['is_online'] = 1;
                    }
                    
                    // Log activity
                    try {
                        $stmt = $db->prepare("
                            INSERT INTO activity_logs (user_id, action, details, created_at) 
                            VALUES (?, 'user_login', ?, NOW())
                        ");
                        $stmt->execute([
                            $user['id'],
                            "User logged in: " . $user['full_name'] . " (Role: " . $user['role'] . ")"
                        ]);
                    } catch (Exception $e) {}
                    
                    // Redirect based on role
                    $role = $user['role'];
                    $redirect_url = '';
                    switch ($role) {
                        case 'admin':
                            $redirect_url = 'admin/dashboard.php';
                            break;
                        case 'doctor':
                            $redirect_url = 'doctor/dashboard.php';
                            break;
                        case 'reception':
                            $redirect_url = 'reception/dashboard.php';
                            break;
                        case 'pharmacy':
                            $redirect_url = 'pharmacy/dashboard.php';
                            break;
                        case 'laboratory':
                            $redirect_url = 'laboratory/dashboard.php';
                            break;
                        case 'cashier':
                            $redirect_url = 'cashier/dashboard.php';
                            break;
                        default:
                            $redirect_url = 'dashboard.php';
                            break;
                    }
                    
                    header('Location: ' . $redirect_url);
                    exit;
                } else {
                    $error = 'Invalid username/email or password. Please try again.';
                }
            } else {
                $error = 'Invalid username/email or password. Please try again.';
            }
        } catch (Exception $e) {
            $error = 'Login error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Braick Dispensary</title>
    <link rel="icon" href="../assets/uploads/profiles/braick_logo.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           ROOT VARIABLES
           ================================================================ */
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
            --shadow: 0 4px 24px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 48px rgba(0,0,0,0.15);
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
        
        /* Background decorative elements */
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
        }
        
        /* ================================================================
           LEFT PANEL - Branding with LARGE LOGO
           ================================================================ */
        .login-left {
            flex: 1.2;
            background: linear-gradient(160deg, #0B5ED7 0%, #0A4CA8 50%, #083A8A 100%);
            padding: 50px 45px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            position: relative;
            overflow: hidden;
            text-align: center;
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
        
        /* LARGE LOGO */
        .login-logo-wrapper {
            position: relative;
            z-index: 1;
            margin-bottom: 30px;
        }
        
        .login-logo {
            width: 120px;
            height: 120px;
            border-radius: 30px;
            background: rgba(255,255,255,0.12);
            backdrop-filter: blur(10px);
            border: 3px solid rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            padding: 20px;
            transition: all 0.5s ease;
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
        }
        
        .login-logo:hover {
            transform: scale(1.05) rotate(-2deg);
            border-color: rgba(255,255,255,0.4);
            box-shadow: 0 12px 48px rgba(0,0,0,0.3);
        }
        
        .login-logo img {
            width: 80px;
            height: 80px;
            object-fit: contain;
            filter: brightness(0) invert(1);
        }
        
        .login-logo .logo-placeholder {
            font-size: 4rem;
            font-weight: 900;
            color: white;
            letter-spacing: -2px;
        }
        
        /* DISPENSARY NAME - LARGE */
        .dispensary-name {
            position: relative;
            z-index: 1;
        }
        
        .dispensary-name h1 {
            font-size: 2.8rem;
            font-weight: 900;
            letter-spacing: -1px;
            margin-bottom: 4px;
            background: linear-gradient(135deg, #FFFFFF 0%, #93C5FD 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: none;
        }
        
        .dispensary-name .tagline {
            font-size: 1.1rem;
            font-weight: 400;
            opacity: 0.8;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.85);
            margin-top: 4px;
        }
        
        .dispensary-name .divider-line {
            width: 80px;
            height: 4px;
            background: linear-gradient(90deg, rgba(255,255,255,0.6), rgba(255,255,255,0.1));
            border-radius: 4px;
            margin: 16px auto 20px;
        }
        
        /* Features list */
        .login-features {
            margin-top: 30px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px 20px;
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 380px;
        }
        
        .login-features .feature {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
            opacity: 0.85;
            padding: 8px 12px;
            background: rgba(255,255,255,0.06);
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.05);
            transition: all 0.3s ease;
        }
        
        .login-features .feature:hover {
            background: rgba(255,255,255,0.12);
            transform: translateX(4px);
        }
        
        .login-features .feature i {
            width: 32px;
            height: 32px;
            background: rgba(255,255,255,0.12);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            flex-shrink: 0;
            border: 1px solid rgba(255,255,255,0.08);
        }
        
        /* ================================================================
           RIGHT PANEL - Login Form
           ================================================================ */
        .login-right {
            flex: 1;
            padding: 50px 45px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: #FFFFFF;
        }
        
        .login-right .welcome-text {
            margin-bottom: 32px;
        }
        
        .login-right .welcome-text h2 {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 6px;
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
        
        .login-right .form-group .input-wrapper i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }
        
        .login-right .form-group .input-wrapper input {
            width: 100%;
            padding: 13px 16px 13px 46px;
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
        
        .login-right .form-group .input-wrapper input:focus + i {
            color: var(--primary);
        }
        
        .login-right .form-group .input-wrapper input::placeholder {
            color: var(--gray-400);
        }
        
        .login-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 6px 0 22px 0;
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
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 32px rgba(11, 94, 215, 0.4);
        }
        
        .btn-login:active {
            transform: scale(0.98);
        }
        
        .btn-login:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-login .spinner {
            display: none;
            animation: spin 1s linear infinite;
        }
        
        .btn-login.loading .spinner {
            display: inline-block;
        }
        
        .btn-login.loading .btn-text {
            display: none;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
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
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* ================================================================
           DEMO CREDENTIALS
           ================================================================ */
        .demo-credentials {
            margin-top: 20px;
            padding: 14px 18px;
            background: var(--gray-50);
            border-radius: var(--radius);
            border: 1px solid var(--gray-200);
        }
        
        .demo-credentials .demo-title {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .demo-credentials .demo-title .key-icon {
            color: var(--warning);
        }
        
        .demo-credentials .demo-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2px 12px;
            font-size: 0.75rem;
        }
        
        .demo-credentials .demo-grid .demo-item {
            display: flex;
            align-items: center;
            gap: 4px;
            padding: 2px 0;
        }
        
        .demo-credentials .demo-grid .demo-role {
            font-weight: 600;
            color: var(--primary);
            min-width: 60px;
        }
        
        .demo-credentials .demo-grid .demo-user {
            font-family: monospace;
            color: var(--gray-700);
            font-size: 0.7rem;
            background: var(--gray-100);
            padding: 1px 8px;
            border-radius: 4px;
        }
        
        .demo-credentials .demo-grid .demo-pass {
            font-family: monospace;
            color: var(--gray-500);
            font-size: 0.65rem;
        }
        
        /* ================================================================
           FOOTER
           ================================================================ */
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
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 992px) {
            .login-container {
                flex-direction: column;
                max-width: 480px;
                min-height: auto;
            }
            
            .login-left {
                padding: 32px 24px;
                min-height: auto;
                border-radius: var(--radius-lg) var(--radius-lg) 0 0;
            }
            
            .login-logo {
                width: 90px;
                height: 90px;
                padding: 16px;
            }
            
            .login-logo img {
                width: 58px;
                height: 58px;
            }
            
            .dispensary-name h1 {
                font-size: 2rem;
            }
            
            .login-features {
                grid-template-columns: 1fr 1fr;
                gap: 6px 12px;
                max-width: 100%;
            }
            
            .login-features .feature {
                font-size: 0.75rem;
                padding: 6px 10px;
            }
            
            .login-right {
                padding: 32px 24px;
            }
            
            .login-right .welcome-text h2 {
                font-size: 1.4rem;
            }
            
            .demo-credentials .demo-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        @media (max-width: 600px) {
            body { padding: 12px; }
            
            .login-left {
                padding: 24px 16px;
            }
            
            .login-logo {
                width: 72px;
                height: 72px;
                padding: 12px;
            }
            
            .login-logo img {
                width: 44px;
                height: 44px;
            }
            
            .dispensary-name h1 {
                font-size: 1.6rem;
            }
            
            .dispensary-name .tagline {
                font-size: 0.85rem;
            }
            
            .login-features {
                grid-template-columns: 1fr;
            }
            
            .login-features .feature {
                font-size: 0.7rem;
                padding: 4px 8px;
            }
            
            .login-right {
                padding: 24px 16px;
            }
            
            .login-right .welcome-text h2 {
                font-size: 1.2rem;
            }
            
            .login-right .form-group .input-wrapper input {
                padding: 11px 14px 11px 40px;
                font-size: 0.9rem;
            }
            
            .login-options {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
            
            .demo-credentials .demo-grid {
                grid-template-columns: 1fr;
            }
            
            .demo-credentials .demo-grid .demo-item {
                padding: 1px 0;
            }
            
            .btn-login {
                padding: 13px;
                font-size: 0.9rem;
            }
        }
        
        @media (max-width: 400px) {
            .login-left {
                padding: 16px 12px;
            }
            
            .login-logo {
                width: 60px;
                height: 60px;
                padding: 10px;
            }
            
            .login-logo img {
                width: 36px;
                height: 36px;
            }
            
            .dispensary-name h1 {
                font-size: 1.3rem;
            }
            
            .login-right {
                padding: 16px 12px;
            }
        }
        
        /* ================================================================
           ANIMATIONS
           ================================================================ */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-container {
            animation: fadeInUp 0.6s ease forwards;
        }
        
        .login-left {
            animation: fadeInUp 0.6s ease 0.1s both;
        }
        
        .login-right {
            animation: fadeInUp 0.6s ease 0.2s both;
        }
    </style>
</head>
<body>

<div class="login-container">
    
    <!-- ================================================================ -->
    <!-- LEFT PANEL - Branding with LARGE LOGO -->
    <!-- ================================================================ -->
    <div class="login-left">
        
        <!-- LARGE LOGO -->
        <div class="login-logo-wrapper">
            <div class="login-logo">
                <img src="../assets/uploads/profiles/braick_logo.png" 
                     alt="Braick Dispensary" 
                     onerror="this.style.display='none'; this.parentElement.innerHTML='<span class=\'logo-placeholder\'>B</span>';">
            </div>
            
            <div class="dispensary-name">
                <h1>Braick</h1>
                <div class="divider-line"></div>
                <p class="tagline">Dispensary &amp; Healthcare</p>
            </div>
        </div>
        
        <!-- Features -->
        <div class="login-features">
            <div class="feature">
                <i class="fas fa-user-md"></i>
                <span>Doctor Consultations</span>
            </div>
            <div class="feature">
                <i class="fas fa-flask"></i>
                <span>Lab Tests</span>
            </div>
            <div class="feature">
                <i class="fas fa-prescription"></i>
                <span>Pharmacy</span>
            </div>
            <div class="feature">
                <i class="fas fa-receipt"></i>
                <span>Billing &amp; Payments</span>
            </div>
        </div>
    </div>
    
    <!-- ================================================================ -->
    <!-- RIGHT PANEL - Login Form -->
    <!-- ================================================================ -->
    <div class="login-right">
        
        <div class="welcome-text">
            <h2>Welcome Back</h2>
            <p class="subtitle">Enter your credentials to access your account</p>
        </div>
        
        <!-- Error / Success Messages -->
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        
        <!-- Login Form -->
        <form method="POST" action="" id="loginForm" autocomplete="off">
            
            <div class="form-group">
                <label for="username">Username or Email</label>
                <div class="input-wrapper">
                    <i class="fas fa-user"></i>
                    <input type="text" id="username" name="username" 
                           placeholder="Enter your username or email" 
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" 
                           required autofocus>
                </div>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="password" name="password" 
                           placeholder="Enter your password" required>
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
                <span class="btn-text"><i class="fas fa-sign-in-alt"></i> Sign In</span>
                <span class="spinner"><i class="fas fa-circle-notch"></i></span>
            </button>
            
        </form>
        
        <!-- Demo Credentials -->
        <div class="demo-credentials">
            <div class="demo-title">
                <span class="key-icon">🔑</span> Demo Credentials
                <span style="font-weight:400;color:var(--gray-400);font-size:0.65rem;">(Password: <strong style="color:var(--gray-600);">12345678</strong>)</span>
            </div>
            <div class="demo-grid">
                <div class="demo-item">
                    <span class="demo-role">Reception:</span>
                    <span class="demo-user">reception.rose</span>
                </div>
                <div class="demo-item">
                    <span class="demo-role">Doctor:</span>
                    <span class="demo-user">dr.john</span>
                </div>
                <div class="demo-item">
                    <span class="demo-role">Admin:</span>
                    <span class="demo-user">admin</span>
                </div>
                <div class="demo-item">
                    <span class="demo-role">Pharmacy:</span>
                    <span class="demo-user">pharm.dodoma</span>
                </div>
                <div class="demo-item">
                    <span class="demo-role">Lab:</span>
                    <span class="demo-user">lab.dodoma</span>
                </div>
                <div class="demo-item">
                    <span class="demo-role">Cashier:</span>
                    <span class="demo-user">cashier.dodoma</span>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="login-footer">
            &copy; <?= date('Y') ?> <span class="brand">Braick Dispensary</span> Management System
            <span style="margin:0 6px;">|</span>
            Made with <span class="heart">❤</span> for healthcare
        </div>
        
    </div>
    
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // Show loading state on submit
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        var btn = document.getElementById('loginBtn');
        btn.classList.add('loading');
        btn.disabled = true;
    });
    
    // Auto-focus username if empty
    document.addEventListener('DOMContentLoaded', function() {
        var username = document.getElementById('username');
        if (!username.value) {
            username.focus();
        }
    });
    
    // Allow Enter key to submit
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            var active = document.activeElement;
            if (active && (active.id === 'username' || active.id === 'password')) {
                document.getElementById('loginForm').submit();
            }
        }
    });
    
    console.log('%c🏥 Braick Dispensary Login', 'font-size:24px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🔑 Password: 12345678', 'font-size:14px; color:#059669;');
    console.log('%c👤 Usernames: reception.rose, dr.john, admin', 'font-size:14px; color:#64748B;');
</script>

</body>
</html>