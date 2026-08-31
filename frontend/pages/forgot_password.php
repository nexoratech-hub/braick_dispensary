<?php
// ================================================================
// FILE: frontend/pages/forgot_password.php
// BRAICK DISPENSARY - FORGOT PASSWORD
// WITH OTP VERIFICATION AND PASSWORD RESET
// *** ADMIN ONLY ACCESS ***
// ================================================================

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../backend/config/database.php';

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = '';
$success = '';
$email = '';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die('Database connection error: ' . $e->getMessage());
}

// ================================================================
// CHECK IF USER IS ADMIN - Only admins can reset password
// ================================================================
function isAdminEmail($db, $email) {
    try {
        $stmt = $db->prepare("SELECT id, role FROM users WHERE email = ? AND status = 'active'");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($user && $user['role'] === 'admin');
    } catch (Exception $e) {
        return false;
    }
}

// ================================================================
// STEP 1: REQUEST PASSWORD RESET (Send OTP) - ADMIN ONLY
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_reset') {
    $email = trim($_POST['email'] ?? '');
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // CHECK IF USER IS ADMIN
        if (!isAdminEmail($db, $email)) {
            $error = '❌ This feature is only available for Admin users. Please contact your system administrator.';
        } else {
            try {
                // Check if user exists and is admin
                $stmt = $db->prepare("SELECT id, full_name, username, role FROM users WHERE email = ? AND status = 'active' AND role = 'admin'");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$user) {
                    $error = '❌ Admin account not found with this email.';
                } else {
                    // Generate OTP
                    $otp = sprintf("%06d", rand(100000, 999999));
                    $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                    
                    // Save OTP in database
                    $stmt = $db->prepare("
                        INSERT INTO password_resets (email, token, otp, expires_at, created_at) 
                        VALUES (?, ?, ?, ?, NOW())
                        ON DUPLICATE KEY UPDATE 
                            token = VALUES(token),
                            otp = VALUES(otp),
                            expires_at = VALUES(expires_at),
                            created_at = NOW()
                    ");
                    $stmt->execute([$email, bin2hex(random_bytes(32)), $otp, $expires_at]);
                    
                    // Send email with OTP
                    $subject = "Password Reset OTP - Braick Dispensary (Admin)";
                    $message = "
                        <html>
                        <head>
                            <style>
                                body { font-family: Arial, sans-serif; background: #f8fafc; padding: 20px; }
                                .container { max-width: 500px; margin: 0 auto; background: #ffffff; border-radius: 12px; padding: 30px; box-shadow: 0 4px 16px rgba(0,0,0,0.1); }
                                .header { text-align: center; border-bottom: 2px solid #0B5ED7; padding-bottom: 20px; }
                                .header h1 { color: #0B5ED7; margin: 0; }
                                .admin-badge { background: #0B5ED7; color: white; padding: 2px 12px; border-radius: 20px; font-size: 12px; display: inline-block; }
                                .otp-code { font-size: 32px; font-weight: bold; color: #0B5ED7; text-align: center; padding: 20px; background: #E8F0FE; border-radius: 8px; letter-spacing: 8px; }
                                .footer { margin-top: 20px; text-align: center; font-size: 12px; color: #94A3B8; }
                                .warning { color: #DC2626; font-size: 14px; text-align: center; }
                            </style>
                        </head>
                        <body>
                            <div class='container'>
                                <div class='header'>
                                    <h1>🏥 Braick Dispensary</h1>
                                    <p style='color: #64748B;'>Admin Password Reset Request</p>
                                    <span class='admin-badge'>🔐 Admin Access</span>
                                </div>
                                <p>Hello <strong>" . htmlspecialchars($user['full_name']) . "</strong>,</p>
                                <p>We received a request to reset your admin password. Use the OTP code below to verify your identity:</p>
                                <div class='otp-code'>" . $otp . "</div>
                                <p>This OTP is valid for <strong>15 minutes</strong>.</p>
                                <p class='warning'>⚠️ If you didn't request this, please ignore this email and contact your system administrator immediately.</p>
                                <div class='footer'>
                                    <p>&copy; " . date('Y') . " Braick Dispensary Management System</p>
                                </div>
                            </div>
                        </body>
                        </html>
                    ";
                    
                    $headers = "MIME-Version: 1.0\r\n";
                    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                    $headers .= "From: Braick Dispensary <noreply@braick.com>\r\n";
                    $headers .= "Reply-To: support@braick.com\r\n";
                    
                    $mail_sent = mail($email, $subject, $message, $headers);
                    
                    if ($mail_sent) {
                        $_SESSION['reset_email'] = $email;
                        $_SESSION['reset_otp'] = $otp;
                        $_SESSION['reset_is_admin'] = true;
                        $success = "✅ An OTP has been sent to your admin email. Please check your inbox.";
                        $step = 2;
                    } else {
                        $error = "❌ Failed to send OTP email. Please try again or contact support.";
                    }
                }
            } catch (Exception $e) {
                $error = "❌ Error: " . $e->getMessage();
            }
        }
    }
}

// ================================================================
// STEP 2: VERIFY OTP - ADMIN ONLY
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_otp') {
    $otp = trim($_POST['otp'] ?? '');
    $email = $_SESSION['reset_email'] ?? '';
    $is_admin = $_SESSION['reset_is_admin'] ?? false;
    
    if (empty($otp) || strlen($otp) !== 6) {
        $error = 'Please enter the 6-digit OTP code.';
    } elseif (empty($email)) {
        $error = 'Session expired. Please request a new OTP.';
        $step = 1;
    } elseif (!$is_admin) {
        $error = '❌ Unauthorized access. Admin only.';
        $step = 1;
    } else {
        try {
            // Verify OTP and ensure user is still admin
            $stmt = $db->prepare("
                SELECT pr.*, u.role 
                FROM password_resets pr
                JOIN users u ON pr.email = u.email
                WHERE pr.email = ? AND pr.otp = ? AND pr.expires_at > NOW()
                AND u.role = 'admin' AND u.status = 'active'
                ORDER BY pr.created_at DESC LIMIT 1
            ");
            $stmt->execute([$email, $otp]);
            $reset = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($reset) {
                $_SESSION['reset_verified'] = true;
                $_SESSION['reset_email'] = $email;
                $_SESSION['reset_is_admin'] = true;
                $success = "✅ OTP verified! Please set your new admin password.";
                $step = 3;
            } else {
                $error = "❌ Invalid or expired OTP. Please request a new one.";
            }
        } catch (Exception $e) {
            $error = "❌ Error: " . $e->getMessage();
        }
    }
}

// ================================================================
// STEP 3: RESET PASSWORD - ADMIN ONLY
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $email = $_SESSION['reset_email'] ?? '';
    $is_admin = $_SESSION['reset_is_admin'] ?? false;
    
    if (!isset($_SESSION['reset_verified']) || $_SESSION['reset_verified'] !== true) {
        $error = 'Please verify your OTP first.';
        $step = 2;
    } elseif (!$is_admin) {
        $error = '❌ Unauthorized access. Admin only.';
        $step = 1;
    } elseif (empty($new_password) || strlen($new_password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (empty($email)) {
        $error = 'Session expired. Please request a new OTP.';
        $step = 1;
    } else {
        try {
            // Verify user is still admin before resetting
            $stmt = $db->prepare("SELECT id, role FROM users WHERE email = ? AND status = 'active' AND role = 'admin'");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                $error = '❌ Admin account not found or has been deactivated.';
            } else {
                // Hash the new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update user password
                $stmt = $db->prepare("
                    UPDATE users 
                    SET password = ?, 
                        is_default_password = 0, 
                        password_changed_at = NOW() 
                    WHERE email = ? AND role = 'admin' AND status = 'active'
                ");
                $stmt->execute([$hashed_password, $email]);
                
                if ($stmt->rowCount() > 0) {
                    // Delete used reset token
                    $stmt = $db->prepare("DELETE FROM password_resets WHERE email = ?");
                    $stmt->execute([$email]);
                    
                    // Clear session
                    unset($_SESSION['reset_email']);
                    unset($_SESSION['reset_otp']);
                    unset($_SESSION['reset_verified']);
                    unset($_SESSION['reset_is_admin']);
                    
                    $success = "✅ Password reset successfully! You can now login with your new password.";
                    $step = 4;
                } else {
                    $error = "❌ Failed to reset password. Please try again.";
                }
            }
        } catch (Exception $e) {
            $error = "❌ Error: " . $e->getMessage();
        }
    }
}

// ================================================================
// RESEND OTP - ADMIN ONLY
// ================================================================
if (isset($_GET['action']) && $_GET['action'] === 'resend_otp') {
    $email = $_SESSION['reset_email'] ?? '';
    $is_admin = $_SESSION['reset_is_admin'] ?? false;
    
    if (!$is_admin) {
        $error = '❌ Unauthorized access. Admin only.';
        $step = 1;
    } elseif (!empty($email)) {
        try {
            // Verify user is still admin
            $stmt = $db->prepare("SELECT full_name, role FROM users WHERE email = ? AND status = 'active' AND role = 'admin'");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $otp = sprintf("%06d", rand(100000, 999999));
                $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                
                $stmt = $db->prepare("
                    UPDATE password_resets 
                    SET otp = ?, expires_at = ?, created_at = NOW() 
                    WHERE email = ?
                ");
                $stmt->execute([$otp, $expires_at, $email]);
                
                // Send email with new OTP
                $subject = "New Password Reset OTP - Braick Dispensary (Admin)";
                $message = "
                    <html>
                    <head>
                        <style>
                            body { font-family: Arial, sans-serif; background: #f8fafc; padding: 20px; }
                            .container { max-width: 500px; margin: 0 auto; background: #ffffff; border-radius: 12px; padding: 30px; box-shadow: 0 4px 16px rgba(0,0,0,0.1); }
                            .header { text-align: center; border-bottom: 2px solid #0B5ED7; padding-bottom: 20px; }
                            .header h1 { color: #0B5ED7; margin: 0; }
                            .admin-badge { background: #0B5ED7; color: white; padding: 2px 12px; border-radius: 20px; font-size: 12px; display: inline-block; }
                            .otp-code { font-size: 32px; font-weight: bold; color: #0B5ED7; text-align: center; padding: 20px; background: #E8F0FE; border-radius: 8px; letter-spacing: 8px; }
                            .footer { margin-top: 20px; text-align: center; font-size: 12px; color: #94A3B8; }
                            .warning { color: #DC2626; font-size: 14px; text-align: center; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h1>🏥 Braick Dispensary</h1>
                                <p style='color: #64748B;'>New Admin OTP Code</p>
                                <span class='admin-badge'>🔐 Admin Access</span>
                            </div>
                            <p>Hello <strong>" . htmlspecialchars($user['full_name']) . "</strong>,</p>
                            <p>You requested a new OTP for admin password reset:</p>
                            <div class='otp-code'>" . $otp . "</div>
                            <p>This OTP is valid for <strong>15 minutes</strong>.</p>
                            <p class='warning'>⚠️ If you didn't request this, please ignore this email.</p>
                            <div class='footer'>
                                <p>&copy; " . date('Y') . " Braick Dispensary Management System</p>
                            </div>
                        </div>
                    </body>
                    </html>
                ";
                
                $headers = "MIME-Version: 1.0\r\n";
                $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                $headers .= "From: Braick Dispensary <noreply@braick.com>\r\n";
                $headers .= "Reply-To: support@braick.com\r\n";
                
                if (mail($email, $subject, $message, $headers)) {
                    $_SESSION['reset_otp'] = $otp;
                    $success = "✅ A new OTP has been sent to your admin email.";
                } else {
                    $error = "❌ Failed to send OTP. Please try again.";
                }
            }
        } catch (Exception $e) {
            $error = "❌ Error: " . $e->getMessage();
        }
    } else {
        $step = 1;
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

// ================================================================
// CREATE PASSWORD_RESETS TABLE IF NOT EXISTS
// ================================================================
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS `password_resets` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `email` varchar(100) NOT NULL,
            `token` varchar(255) NOT NULL,
            `otp` varchar(10) NOT NULL,
            `expires_at` datetime NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Braick Dispensary (Admin)</title>
    <link rel="icon" href="<?= $logo_url ?>" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-bg: #E8F0FE;
            --success: #059669;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
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
        
        .forgot-container {
            background: #FFFFFF;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            max-width: 480px;
            width: 100%;
            overflow: hidden;
            position: relative;
            z-index: 1;
            animation: fadeInUp 0.6s ease forwards;
            padding: 50px 45px;
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .brand-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .brand-logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .brand-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        .brand-logo .logo-placeholder {
            font-size: 3.5rem;
            font-weight: 900;
            color: var(--primary);
        }
        
        .brand-name {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--gray-900);
            margin: 0;
        }
        
        .brand-subtitle {
            font-size: 0.85rem;
            color: var(--gray-500);
            margin-top: 2px;
        }
        
        .admin-access-badge {
            display: inline-block;
            background: var(--primary);
            color: white;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 6px;
        }
        
        .step-indicators {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-bottom: 28px;
        }
        
        .step-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--gray-300);
            transition: all 0.3s ease;
        }
        
        .step-dot.active {
            background: var(--primary);
            transform: scale(1.2);
        }
        
        .step-dot.completed {
            background: var(--success);
        }
        
        .step-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--gray-400);
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .step-label.active {
            color: var(--primary);
        }
        
        .step-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 4px;
        }
        
        .step-description {
            color: var(--gray-500);
            font-size: 0.9rem;
            margin-bottom: 24px;
        }
        
        .form-group {
            margin-bottom: 18px;
        }
        
        .form-group label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 5px;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-wrapper .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            font-size: 0.9rem;
            z-index: 2;
            pointer-events: none;
            transition: color 0.3s ease;
        }
        
        .input-wrapper input {
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
        
        .input-wrapper input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.1);
            background: white;
            outline: none;
        }
        
        .input-wrapper input:focus ~ .input-icon {
            color: var(--primary);
        }
        
        .input-wrapper input::placeholder {
            color: var(--gray-400);
        }
        
        .input-wrapper input.otp-input {
            text-align: center;
            font-size: 1.8rem;
            letter-spacing: 12px;
            font-weight: 700;
            padding: 13px 16px;
        }
        
        .input-wrapper input.otp-input:focus {
            letter-spacing: 12px;
        }
        
        .otp-help {
            text-align: center;
            font-size: 0.85rem;
            color: var(--gray-500);
            margin-top: 8px;
        }
        
        .otp-help a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }
        
        .otp-help a:hover {
            text-decoration: underline;
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
        
        .alert-warning {
            background: var(--warning-bg);
            color: #D97706;
            border: 1px solid #FCD34D;
        }
        
        .btn {
            width: 100%;
            padding: 15px;
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
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 100%);
            color: white;
            box-shadow: 0 4px 16px rgba(11, 94, 215, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 32px rgba(11, 94, 215, 0.4);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: white;
            box-shadow: 0 4px 16px rgba(5, 150, 105, 0.3);
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 32px rgba(5, 150, 105, 0.4);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--gray-600);
            border: 2px solid var(--gray-200);
        }
        
        .btn-outline:hover {
            background: var(--gray-50);
            border-color: var(--gray-400);
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }
        
        .btn .btn-icon {
            transition: all 0.3s ease;
        }
        
        .btn.loading .btn-icon {
            animation: spinIcon 0.8s linear infinite;
        }
        
        @keyframes spinIcon {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .back-to-login {
            text-align: center;
            margin-top: 20px;
            font-size: 0.85rem;
            color: var(--gray-500);
        }
        
        .back-to-login a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }
        
        .back-to-login a:hover {
            text-decoration: underline;
        }
        
        .completion-icon {
            text-align: center;
            font-size: 4rem;
            color: var(--success);
            margin-bottom: 16px;
        }
        
        .completion-icon i {
            background: var(--success-bg);
            padding: 20px;
            border-radius: 50%;
        }
        
        .password-requirements {
            font-size: 0.75rem;
            color: var(--gray-500);
            margin-top: 4px;
        }
        
        .password-requirements ul {
            list-style: none;
            padding: 0;
            margin: 4px 0 0 0;
        }
        
        .password-requirements ul li {
            padding: 2px 0;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .password-requirements ul li i {
            font-size: 0.6rem;
            color: var(--gray-400);
        }
        
        .password-requirements ul li.valid i {
            color: var(--success);
        }
        
        .timer {
            font-size: 0.8rem;
            color: var(--gray-500);
            text-align: center;
            margin-top: 4px;
        }
        
        .timer .countdown {
            font-weight: 700;
            color: var(--warning);
        }
        
        .unauthorized-banner {
            background: var(--warning-bg);
            border: 2px solid #FCD34D;
            border-radius: var(--radius);
            padding: 16px 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        
        .unauthorized-banner i {
            color: var(--warning);
            font-size: 1.2rem;
            margin-top: 2px;
        }
        
        .unauthorized-banner .banner-content {
            flex: 1;
        }
        
        .unauthorized-banner .banner-content h4 {
            color: var(--gray-800);
            font-size: 0.95rem;
            margin: 0 0 2px 0;
        }
        
        .unauthorized-banner .banner-content p {
            color: var(--gray-600);
            font-size: 0.85rem;
            margin: 0;
        }
        
        @media (max-width: 600px) {
            .forgot-container {
                padding: 32px 24px;
            }
            .brand-logo {
                width: 60px;
                height: 60px;
            }
            .brand-name {
                font-size: 1.4rem;
            }
            .step-title {
                font-size: 1.2rem;
            }
            .input-wrapper input {
                padding: 11px 14px 11px 40px;
                font-size: 0.9rem;
            }
            .input-wrapper input.otp-input {
                font-size: 1.4rem;
                letter-spacing: 8px;
                padding: 11px 14px;
            }
            .btn {
                padding: 13px;
                font-size: 0.9rem;
            }
        }
        
        @media (max-width: 400px) {
            .forgot-container {
                padding: 20px 16px;
            }
            .brand-logo {
                width: 48px;
                height: 48px;
            }
            .brand-name {
                font-size: 1.2rem;
            }
            .step-title {
                font-size: 1rem;
            }
            .step-description {
                font-size: 0.8rem;
            }
            .input-wrapper input.otp-input {
                font-size: 1.2rem;
                letter-spacing: 6px;
            }
        }
    </style>
</head>
<body>

<div class="forgot-container">
    
    <!-- Brand Header -->
    <div class="brand-header">
        <div class="brand-logo">
            <img src="<?= $logo_url ?>" 
                 alt="Braick Dispensary" 
                 onerror="this.style.display='none'; this.parentElement.innerHTML='<span class=\'logo-placeholder\'><i class=\'fas fa-heartbeat\'></i></span>';">
        </div>
        <h1 class="brand-name">Braick Dispensary</h1>
        <p class="brand-subtitle">Admin Password Reset</p>
        <span class="admin-access-badge"><i class="fas fa-shield-alt"></i> Admin Only</span>
    </div>
    
    <!-- Admin Only Warning -->
    <div class="unauthorized-banner">
        <i class="fas fa-info-circle"></i>
        <div class="banner-content">
            <h4>🔐 Admin Only Access</h4>
            <p>This password reset feature is only available for users with <strong>Admin</strong> role. Regular users cannot reset their password through this portal.</p>
        </div>
    </div>
    
    <!-- Step Indicators -->
    <div style="display:flex;justify-content:center;gap:12px;margin-bottom:20px;">
        <div style="text-align:center;">
            <div class="step-dot <?= $step >= 1 ? 'active' : '' ?> <?= $step > 1 ? 'completed' : '' ?>"></div>
            <div class="step-label <?= $step >= 1 ? 'active' : '' ?>">Request</div>
        </div>
        <div style="text-align:center;">
            <div class="step-dot <?= $step >= 2 ? 'active' : '' ?> <?= $step > 2 ? 'completed' : '' ?>"></div>
            <div class="step-label <?= $step >= 2 ? 'active' : '' ?>">Verify OTP</div>
        </div>
        <div style="text-align:center;">
            <div class="step-dot <?= $step >= 3 ? 'active' : '' ?> <?= $step > 3 ? 'completed' : '' ?>"></div>
            <div class="step-label <?= $step >= 3 ? 'active' : '' ?>">Reset</div>
        </div>
    </div>
    
    <!-- Alert Container -->
    <div id="alertContainer">
        <?php if ($error): ?>
            <div class="alert alert-error" id="errorAlert">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success" id="successAlert">
                <i class="fas fa-check-circle"></i>
                <?= $success ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- ================================================================ -->
    <!-- STEP 1: REQUEST RESET - ADMIN ONLY -->
    <!-- ================================================================ -->
    <?php if ($step === 1): ?>
        
        <h2 class="step-title">Forgot Password?</h2>
        <p class="step-description">Enter your registered admin email address and we'll send you a 6-digit OTP to reset your password.</p>
        
        <form method="POST" action="" id="requestForm">
            <input type="hidden" name="action" value="request_reset">
            
            <div class="form-group">
                <label for="email">Admin Email Address</label>
                <div class="input-wrapper">
                    <i class="fas fa-envelope input-icon"></i>
                    <input type="email" id="email" name="email" 
                           placeholder="Enter your admin email" 
                           value="<?= htmlspecialchars($email) ?>" 
                           required autofocus>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary" id="requestBtn">
                <span class="btn-icon"><i class="fas fa-paper-plane"></i></span>
                <span class="btn-text">Send OTP</span>
            </button>
        </form>
        
        <div class="back-to-login">
            <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
        </div>
    
    <!-- ================================================================ -->
    <!-- STEP 2: VERIFY OTP - ADMIN ONLY -->
    <!-- ================================================================ -->
    <?php elseif ($step === 2): ?>
        
        <h2 class="step-title">Verify OTP</h2>
        <p class="step-description">Enter the 6-digit code sent to your admin email.</p>
        
        <form method="POST" action="" id="otpForm">
            <input type="hidden" name="action" value="verify_otp">
            
            <div class="form-group">
                <label for="otp">6-Digit OTP Code</label>
                <div class="input-wrapper">
                    <i class="fas fa-shield-alt input-icon"></i>
                    <input type="text" id="otp" name="otp" 
                           class="otp-input" 
                           placeholder="------" 
                           maxlength="6" 
                           inputmode="numeric" 
                           pattern="[0-9]{6}" 
                           required autofocus>
                </div>
            </div>
            
            <div class="otp-help">
                Didn't receive code? <a href="?action=resend_otp" onclick="return resendOtp(event)">Resend OTP</a>
                <span class="timer" id="timerDisplay">⏱ <span class="countdown" id="countdown">15:00</span></span>
            </div>
            
            <button type="submit" class="btn btn-primary" id="verifyBtn">
                <span class="btn-icon"><i class="fas fa-check-circle"></i></span>
                <span class="btn-text">Verify OTP</span>
            </button>
        </form>
        
        <div class="back-to-login">
            <a href="?step=1"><i class="fas fa-arrow-left"></i> Request New OTP</a> | 
            <a href="login.php">Back to Login</a>
        </div>
    
    <!-- ================================================================ -->
    <!-- STEP 3: RESET PASSWORD - ADMIN ONLY -->
    <!-- ================================================================ -->
    <?php elseif ($step === 3): ?>
        
        <h2 class="step-title">Set New Admin Password</h2>
        <p class="step-description">Create a strong password for your admin account.</p>
        
        <form method="POST" action="" id="resetForm">
            <input type="hidden" name="action" value="reset_password">
            
            <div class="form-group">
                <label for="new_password">New Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" id="new_password" name="new_password" 
                           placeholder="Enter new password (min 6 characters)" 
                           minlength="6" required>
                </div>
                <div class="password-requirements">
                    <ul>
                        <li id="req-length"><i class="fas fa-circle"></i> At least 6 characters</li>
                        <li id="req-number"><i class="fas fa-circle"></i> At least one number</li>
                        <li id="req-letter"><i class="fas fa-circle"></i> At least one letter</li>
                    </ul>
                </div>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-check-circle input-icon"></i>
                    <input type="password" id="confirm_password" name="confirm_password" 
                           placeholder="Confirm your new password" 
                           minlength="6" required>
                </div>
            </div>
            
            <button type="submit" class="btn btn-success" id="resetBtn">
                <span class="btn-icon"><i class="fas fa-key"></i></span>
                <span class="btn-text">Reset Password</span>
            </button>
        </form>
        
        <div class="back-to-login">
            <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
        </div>
    
    <!-- ================================================================ -->
    <!-- STEP 4: COMPLETION -->
    <!-- ================================================================ -->
    <?php elseif ($step === 4): ?>
        
        <div class="completion-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        
        <h2 class="step-title" style="text-align:center;">Password Reset Complete!</h2>
        <p class="step-description" style="text-align:center;">Your admin password has been successfully reset. You can now login with your new password.</p>
        
        <a href="login.php" class="btn btn-primary" style="text-decoration:none;margin-top:8px;">
            <i class="fas fa-sign-in-alt"></i> Go to Login
        </a>
        
        <div class="back-to-login" style="margin-top:12px;">
            <a href="login.php">Back to Login</a>
        </div>
    
    <?php endif; ?>
    
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // AUTO-FOCUS
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        const firstInput = document.querySelector('input[autofocus], input:not([readonly])');
        if (firstInput) {
            setTimeout(function() {
                firstInput.focus();
            }, 300);
        }
    });

    // ================================================================
    // OTP INPUT - AUTO ADVANCE
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        const otpInput = document.getElementById('otp');
        if (otpInput) {
            otpInput.addEventListener('input', function(e) {
                this.value = this.value.replace(/[^0-9]/g, '');
                if (this.value.length === 6) {
                    // Auto submit OTP
                    const form = document.getElementById('otpForm');
                    if (form) {
                        setTimeout(function() {
                            form.dispatchEvent(new Event('submit'));
                        }, 300);
                    }
                }
            });
            
            otpInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && this.value.length === 6) {
                    const form = document.getElementById('otpForm');
                    if (form) {
                        form.dispatchEvent(new Event('submit'));
                    }
                }
            });
        }
    });

    // ================================================================
    // PASSWORD STRENGTH CHECK
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        const passwordInput = document.getElementById('new_password');
        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                const val = this.value;
                
                const reqLength = document.getElementById('req-length');
                const reqNumber = document.getElementById('req-number');
                const reqLetter = document.getElementById('req-letter');
                
                if (reqLength) {
                    reqLength.className = val.length >= 6 ? 'valid' : '';
                    reqLength.querySelector('i').className = val.length >= 6 ? 'fas fa-check-circle' : 'fas fa-circle';
                }
                
                if (reqNumber) {
                    const hasNumber = /\d/.test(val);
                    reqNumber.className = hasNumber ? 'valid' : '';
                    reqNumber.querySelector('i').className = hasNumber ? 'fas fa-check-circle' : 'fas fa-circle';
                }
                
                if (reqLetter) {
                    const hasLetter = /[a-zA-Z]/.test(val);
                    reqLetter.className = hasLetter ? 'valid' : '';
                    reqLetter.querySelector('i').className = hasLetter ? 'fas fa-check-circle' : 'fas fa-circle';
                }
            });
        }
    });

    // ================================================================
    // CONFIRM PASSWORD MATCH
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        const confirmInput = document.getElementById('confirm_password');
        const passwordInput = document.getElementById('new_password');
        
        if (confirmInput && passwordInput) {
            confirmInput.addEventListener('input', function() {
                if (this.value && this.value !== passwordInput.value) {
                    this.style.borderColor = '#DC2626';
                    this.style.boxShadow = '0 0 0 4px rgba(220,38,38,0.1)';
                } else {
                    this.style.borderColor = '';
                    this.style.boxShadow = '';
                }
            });
        }
    });

    // ================================================================
    // OTP TIMER COUNTDOWN
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        const timerDisplay = document.getElementById('countdown');
        if (timerDisplay) {
            let minutes = 15;
            let seconds = 0;
            
            const timer = setInterval(function() {
                if (seconds === 0) {
                    if (minutes === 0) {
                        clearInterval(timer);
                        timerDisplay.textContent = 'Expired';
                        timerDisplay.style.color = '#DC2626';
                        return;
                    }
                    minutes--;
                    seconds = 59;
                } else {
                    seconds--;
                }
                
                const minStr = String(minutes).padStart(2, '0');
                const secStr = String(seconds).padStart(2, '0');
                timerDisplay.textContent = minStr + ':' + secStr;
                
                if (minutes < 2) {
                    timerDisplay.style.color = '#DC2626';
                }
            }, 1000);
            
            // Store timer for cleanup
            window.otpTimer = timer;
        }
    });

    // ================================================================
    // RESEND OTP
    // ================================================================
    function resendOtp(e) {
        e.preventDefault();
        
        const link = e.currentTarget;
        const originalText = link.textContent;
        link.textContent = '⏳ Sending...';
        link.style.pointerEvents = 'none';
        link.style.opacity = '0.6';
        
        fetch(window.location.href + '&action=resend_otp', {
            method: 'GET'
        })
        .then(function(response) {
            return response.text();
        })
        .then(function() {
            link.textContent = '✅ Sent!';
            setTimeout(function() {
                link.textContent = originalText;
                link.style.pointerEvents = 'auto';
                link.style.opacity = '1';
            }, 3000);
            
            // Show success message
            const alertContainer = document.getElementById('alertContainer');
            if (alertContainer) {
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-success';
                alertDiv.innerHTML = '<i class="fas fa-check-circle"></i> A new OTP has been sent to your admin email.';
                alertContainer.appendChild(alertDiv);
                
                setTimeout(function() {
                    alertDiv.remove();
                }, 5000);
            }
            
            // Reset timer
            const timerDisplay = document.getElementById('countdown');
            if (timerDisplay) {
                timerDisplay.textContent = '15:00';
                timerDisplay.style.color = '';
            }
        })
        .catch(function() {
            link.textContent = '❌ Failed';
            setTimeout(function() {
                link.textContent = originalText;
                link.style.pointerEvents = 'auto';
                link.style.opacity = '1';
            }, 3000);
        });
        
        return false;
    }

    // ================================================================
    // FORM SUBMISSION HANDLING
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        // Request Form
        const requestForm = document.getElementById('requestForm');
        if (requestForm) {
            requestForm.addEventListener('submit', function(e) {
                const btn = document.getElementById('requestBtn');
                const icon = btn.querySelector('.btn-icon i');
                const text = btn.querySelector('.btn-text');
                
                btn.disabled = true;
                btn.classList.add('loading');
                if (icon) icon.className = 'fas fa-spinner fa-spin';
                if (text) text.textContent = 'Sending...';
            });
        }
        
        // OTP Form
        const otpForm = document.getElementById('otpForm');
        if (otpForm) {
            otpForm.addEventListener('submit', function(e) {
                const btn = document.getElementById('verifyBtn');
                const icon = btn.querySelector('.btn-icon i');
                const text = btn.querySelector('.btn-text');
                
                btn.disabled = true;
                btn.classList.add('loading');
                if (icon) icon.className = 'fas fa-spinner fa-spin';
                if (text) text.textContent = 'Verifying...';
            });
        }
        
        // Reset Form
        const resetForm = document.getElementById('resetForm');
        if (resetForm) {
            resetForm.addEventListener('submit', function(e) {
                const password = document.getElementById('new_password').value;
                const confirm = document.getElementById('confirm_password').value;
                
                if (password !== confirm) {
                    e.preventDefault();
                    showAlert('Passwords do not match.', 'error');
                    return;
                }
                
                if (password.length < 6) {
                    e.preventDefault();
                    showAlert('Password must be at least 6 characters.', 'error');
                    return;
                }
                
                const btn = document.getElementById('resetBtn');
                const icon = btn.querySelector('.btn-icon i');
                const text = btn.querySelector('.btn-text');
                
                btn.disabled = true;
                btn.classList.add('loading');
                if (icon) icon.className = 'fas fa-spinner fa-spin';
                if (text) text.textContent = 'Resetting...';
            });
        }
    });

    // ================================================================
    // SHOW ALERT FUNCTION
    // ================================================================
    function showAlert(message, type) {
        const container = document.getElementById('alertContainer');
        if (!container) return;
        
        // Remove existing alerts
        const existingAlerts = container.querySelectorAll('.alert');
        existingAlerts.forEach(function(el) { el.remove(); });
        
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-' + type;
        alertDiv.innerHTML = '<i class="fas fa-' + (type === 'error' ? 'exclamation-circle' : 'check-circle') + '"></i> ' + message;
        container.appendChild(alertDiv);
        
        setTimeout(function() {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }

    // ================================================================
    // CONSOLE LOG
    // ================================================================
    console.log('%c🏥 Braick Dispensary - Admin Password Reset', 'font-size:24px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🔐 Admin Only - Regular users cannot reset password', 'font-size:14px; color:#D97706;');
    console.log('%c📧 Step 1: Request OTP via admin email', 'font-size:14px; color:#0B5ED7;');
    console.log('%c🔢 Step 2: Verify 6-digit OTP', 'font-size:14px; color:#D97706;');
    console.log('%c🔑 Step 3: Reset admin password', 'font-size:14px; color:#059669;');
    console.log('%c⚠️ Only users with role = "admin" can access this feature', 'font-size:14px; color:#DC2626;');
</script>

</body>
</html>