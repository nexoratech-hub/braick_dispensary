<?php
// frontend/pages/auth/login.php
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin') {
    header('Location: ../admin/dashboard.php');
    exit;
}

// ================================================================
// FIX: Tumia path absolute kutoka root
// ================================================================
$root_path = $_SERVER['DOCUMENT_ROOT'] . '/dispensary_system/';
require_once $root_path . 'backend/config/database.php';
require_once $root_path . 'backend/helpers/functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter username and password';
    } else {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['branch_id'] = $user['branch_id'];
                
                header('Location: ../admin/dashboard.php');
                exit;
            } else {
                $error = 'Invalid username or password';
            }
        } catch (Exception $e) {
            $error = 'System error. Please try again.';
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
    <link rel="icon" href="<?= $root_path ?>frontend/assets/uploads/profiles/braick_logo.png" type="image/png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
        }
        .login-card {
            background: white;
            border-radius: 24px;
            padding: 48px 40px;
            max-width: 420px;
            width: 100%;
            box-shadow: 0 25px 50px rgba(0,0,0,0.25);
        }
        .login-logo {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            object-fit: cover;
            margin: 0 auto 16px;
            display: block;
            background: #f0f4ff;
            padding: 8px;
        }
        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.95rem;
            transition: all 0.2s;
            outline: none;
        }
        .form-input:focus {
            border-color: #0B5ED7;
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15);
        }
        .btn-login {
            width: 100%;
            padding: 12px;
            background: #0B5ED7;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-login:hover {
            background: #0A4CA8;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(11, 94, 215, 0.3);
        }
        .error-msg {
            background: #FEF2F2;
            color: #EF4444;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 0.9rem;
            border-left: 4px solid #EF4444;
        }
        .success-msg {
            background: #E6F7EE;
            color: #0AA84F;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 0.9rem;
            border-left: 4px solid #0AA84F;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <img src="<?= $root_path ?>frontend/assets/uploads/profiles/braick_logo.png" alt="Braick Logo" class="login-logo"
             onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2280%22 height=%2280%22%3E%3Crect width=%2280%22 height=%2280%22 fill=%22%230B5ED7%22 rx=%2220%22/%3E%3Ctext x=%2240%22 y=%2250%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2230%22 font-weight=%22bold%22%3EB%3C/text%3E%3C/svg%3E'">
        
        <h2 class="text-2xl font-bold text-center text-gray-800 mb-2">Welcome Back</h2>
        <p class="text-center text-gray-500 mb-6">Sign in to your Braick Dispensary account</p>
        
        <?php if ($error): ?>
            <div class="error-msg mb-4">
                <i class="fas fa-exclamation-circle mr-2"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                <input type="text" name="username" class="form-input" placeholder="Enter username" required value="admin">
            </div>
            
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <input type="password" name="password" class="form-input" placeholder="Enter password" required value="password123">
            </div>
            
            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt mr-2"></i> Sign In
            </button>
        </form>
        
        <div class="mt-4 p-3 bg-gray-50 rounded-xl text-center text-xs text-gray-500">
            <p>Default: <strong>admin</strong> / <strong>password123</strong></p>
        </div>
        
        <div class="mt-6 pt-4 border-t text-center text-xs text-gray-400">
            Braick Dispensary Management System v1.0
        </div>
    </div>
</body>
</html>