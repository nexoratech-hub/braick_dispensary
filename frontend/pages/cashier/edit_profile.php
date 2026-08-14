<?php
// ================================================================
// FILE: frontend/pages/cashier/edit_profile.php
// CASHIER - EDIT PROFILE
// USES SHARED HEADER WITH DARK MODE
// ALLOWS: Cashier, Reception, Admin
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// START SESSION
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION - CHECK IF USER IS LOGGED IN
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

// ================================================================
// ALLOWED ROLES: Cashier, Reception, Admin
// ================================================================
$allowed_roles = ['cashier', 'reception', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: /dispensary_system/frontend/pages/doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: /dispensary_system/frontend/pages/pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: /dispensary_system/frontend/pages/laboratory/dashboard.php'); break;
        default: header('Location: /dispensary_system/frontend/pages/login.php'); break;
    }
    exit;
}

// ================================================================
// GET USER DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'User';
$user_role = $_SESSION['role'] ?? 'cashier';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_username = $_SESSION['username'] ?? 'user';
$user_email = $_SESSION['email'] ?? '';
$user_phone = $_SESSION['phone'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// UPLOAD DIRECTORY
// ================================================================
$upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/dispensary_system/frontend/assets/uploads/profiles/';
$upload_url = '/dispensary_system/frontend/assets/uploads/profiles/';

// Create directory if not exists
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// ================================================================
// PROCESS FORM SUBMISSION
// ================================================================
$message = '';
$message_type = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validation
        $errors = [];
        if (empty($full_name)) {
            $errors[] = 'Full name is required';
        }
        if (empty($email)) {
            $errors[] = 'Email is required';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        }
        
        // Check if email exists (excluding current user)
        if (empty($errors) && $email !== $user_email) {
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetch()) {
                $errors[] = 'Email already exists';
            }
        }
        
        // Password change
        if (!empty($new_password) || !empty($current_password)) {
            if (empty($current_password)) {
                $errors[] = 'Current password is required to change password';
            } elseif (empty($new_password)) {
                $errors[] = 'New password is required';
            } elseif ($new_password !== $confirm_password) {
                $errors[] = 'Passwords do not match';
            } elseif (strlen($new_password) < 6) {
                $errors[] = 'Password must be at least 6 characters';
            } else {
                // Verify current password
                $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Check password - supports both hashed and plain
                $password_valid = false;
                if ($user_data) {
                    if (str_starts_with($user_data['password'], '$2y$')) {
                        $password_valid = password_verify($current_password, $user_data['password']);
                    } else {
                        $password_valid = ($current_password === $user_data['password']);
                    }
                }
                
                if ($password_valid) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                } else {
                    $errors[] = 'Current password is incorrect';
                }
            }
        }
        
        if (empty($errors)) {
            // Update profile
            if (isset($hashed_password)) {
                $stmt = $db->prepare("
                    UPDATE users 
                    SET full_name = ?, email = ?, phone = ?, password = ?
                    WHERE id = ?
                ");
                $stmt->execute([$full_name, $email, $phone, $hashed_password, $user_id]);
            } else {
                $stmt = $db->prepare("
                    UPDATE users 
                    SET full_name = ?, email = ?, phone = ?
                    WHERE id = ?
                ");
                $stmt->execute([$full_name, $email, $phone, $user_id]);
            }
            
            // Update session
            $_SESSION['full_name'] = $full_name;
            $_SESSION['email'] = $email;
            $_SESSION['phone'] = $phone;
            
            $message = "Profile updated successfully!";
            $message_type = 'success';
            $success = true;
            
            // Refresh variables
            $user_full_name = $full_name;
            $user_email = $email;
            $user_phone = $phone;
            
            echo '<script>setTimeout(function(){ window.location.href = "profile.php?success=1"; }, 1500);</script>';
        } else {
            $message = implode('<br>', $errors);
            $message_type = 'error';
        }
    }
    
    // ================================================================
    // UPDATE AVATAR
    // ================================================================
    if ($action === 'update_avatar') {
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_pic'];
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            // Validate file
            if (!in_array($file_ext, $allowed_exts)) {
                $message = "Only JPG, PNG, GIF, and WEBP files are allowed!";
                $message_type = 'error';
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $message = "File size exceeds 5MB limit!";
                $message_type = 'error';
            } else {
                // Generate unique filename
                $filename = 'user_' . $user_id . '_' . time() . '.' . $file_ext;
                $filepath = $upload_dir . $filename;
                
                // Delete old profile picture if exists
                if (!empty($profile_pic)) {
                    $old_file = $upload_dir . $profile_pic;
                    if (file_exists($old_file)) {
                        @unlink($old_file);
                    }
                }
                
                // Move uploaded file
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    // Update database
                    $stmt = $db->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
                    $stmt->execute([$filename, $user_id]);
                    
                    // Update session
                    $_SESSION['profile_pic'] = $filename;
                    $profile_pic = $filename;
                    
                    $message = "Profile picture updated successfully!";
                    $message_type = 'success';
                    
                    echo '<script>setTimeout(function(){ window.location.href = "profile.php?success=1"; }, 1500);</script>';
                } else {
                    $message = "Failed to upload profile picture! Please check folder permissions.";
                    $message_type = 'error';
                }
            }
        } else {
            $message = "Please select a file to upload!";
            $message_type = 'error';
        }
    }
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? $upload_url . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

// ================================================================
// GET STATISTICS FOR SIDEBAR
// ================================================================
$pending_bills = 0;
$partial_payments = 0;
$paid_today = 0;
$patients_waiting = 0;

try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM patient_bills WHERE branch_id = ? AND status = 'pending'");
    $stmt->execute([$user_branch_id]);
    $pending_bills = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM patient_bills WHERE branch_id = ? AND status = 'partial'");
    $stmt->execute([$user_branch_id]);
    $partial_payments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM patient_bills WHERE branch_id = ? AND status = 'paid' AND DATE(updated_at) = CURDATE()");
    $stmt->execute([$user_branch_id]);
    $paid_today = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as count FROM patient_bills WHERE branch_id = ? AND status IN ('pending', 'partial')");
    $stmt->execute([$user_branch_id]);
    $patients_waiting = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {
    // Keep counts as 0
}

// ================================================================
// UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// ================================================================
// CHECK IF USER IS RECEPTION
// ================================================================
$is_reception = ($user_role === 'reception');

// ================================================================
// LOGO PATH
// ================================================================
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/cashier_header.php';
include_once __DIR__ . '/../../components/cashier_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
            --success: #059669;
            --success-dark: #047857;
            --success-light: #34D399;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-dark: #B91C1C;
            --danger-light: #F87171;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --white: #FFFFFF;
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
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow: 0 1px 3px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.07);
            --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
            --shadow-xl: 0 20px 25px rgba(0,0,0,0.1);
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --table-stripe: #E8F0FE;
            --table-hover: #D1FAE5;
        }

        /* DARK MODE - MATCH HEADER */
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --shadow: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.4);
            --table-stripe: #1E293B;
            --table-hover: #1A3A2A;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.3);
        }

        [data-theme="dark"] .bg-white {
            background-color: #1E293B !important;
        }

        [data-theme="dark"] .text-gray-700 {
            color: #CBD5E1 !important;
        }

        [data-theme="dark"] .text-gray-800 {
            color: #E2E8F0 !important;
        }

        [data-theme="dark"] .text-gray-900 {
            color: #F1F5F9 !important;
        }

        [data-theme="dark"] .border-gray-200 {
            border-color: #334155 !important;
        }

        [data-theme="dark"] .bg-gray-50 {
            background-color: #1E293B !important;
        }

        [data-theme="dark"] .bg-gray-100 {
            background-color: #2D3748 !important;
        }

        [data-theme="dark"] .shadow {
            box-shadow: 0 1px 3px rgba(0,0,0,0.3) !important;
        }

        [data-theme="dark"] .shadow-md {
            box-shadow: 0 4px 12px rgba(0,0,0,0.3) !important;
        }

        [data-theme="dark"] .shadow-lg {
            box-shadow: 0 10px 25px rgba(0,0,0,0.4) !important;
        }

        [data-theme="dark"] .border-t {
            border-top-color: #334155 !important;
        }

        [data-theme="dark"] .border-t-gray-200 {
            border-top-color: #334155 !important;
        }

        [data-theme="dark"] .text-blue-600 {
            color: #6EA8FE !important;
        }

        [data-theme="dark"] .text-gray-500 {
            color: #94A3B8 !important;
        }

        [data-theme="dark"] .message-box.success {
            background: #1A3A2A;
            color: #34D399;
            border-color: #34D399;
        }

        [data-theme="dark"] .message-box.error {
            background: #3A1A1A;
            color: #F87171;
            border-color: #F87171;
        }

        [data-theme="dark"] .form-card {
            background: #1E293B;
            border-color: #334155;
        }

        [data-theme="dark"] .form-card:hover {
            border-color: #34D399;
        }

        [data-theme="dark"] .form-control {
            background: #1E293B;
            color: #F1F5F9;
            border-color: #334155;
        }

        [data-theme="dark"] .form-control:focus {
            border-color: #34D399;
            box-shadow: 0 0 0 3px rgba(52, 211, 153, 0.15);
        }

        [data-theme="dark"] .form-control:disabled {
            opacity: 0.5;
        }

        [data-theme="dark"] .avatar-upload {
            background: #0F172A;
            border-color: #334155;
        }

        [data-theme="dark"] .btn-outline {
            border-color: #334155;
            color: #94A3B8;
        }

        [data-theme="dark"] .btn-outline:hover {
            border-color: #34D399;
            color: #34D399;
            background: #1A3A2A;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--success); border-radius: 10px; }

        .form-card {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 24px 28px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .form-card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.06);
        }
        
        .form-card .form-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-primary);
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-card .form-title i {
            color: var(--primary);
        }
        
        .form-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
            display: block;
        }
        
        .form-label .required {
            color: var(--danger);
            margin-left: 2px;
        }
        
        .form-control {
            width: 100%;
            padding: 8px 14px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            outline: none;
            background: var(--bg-card);
            color: var(--text-primary);
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
        }
        
        .form-control:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .form-control::placeholder {
            color: var(--text-secondary);
            opacity: 0.5;
        }
        
        .form-row {
            margin-bottom: 14px;
        }
        
        .form-row:last-child {
            margin-bottom: 0;
        }
        
        .form-actions {
            display: flex;
            gap: 12px;
            padding-top: 16px;
            margin-top: 16px;
            border-top: 2px solid var(--border-color);
            flex-wrap: wrap;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(11, 94, 215, 0.4);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        
        .btn-outline:hover {
            background: var(--bg-body);
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: #059669;
            color: white;
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
        }
        
        .btn-success:hover {
            background: #047857;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(5, 150, 105, 0.4);
        }
        
        .avatar-upload {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
            padding: 16px;
            background: var(--bg-body);
            border-radius: 10px;
            border: 2px dashed var(--border-color);
            margin-bottom: 20px;
        }
        
        .avatar-upload .current-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary);
            flex-shrink: 0;
        }
        
        .avatar-upload .avatar-placeholder {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 700;
            color: white;
            background: var(--primary);
            flex-shrink: 0;
            border: 3px solid var(--primary);
        }
        
        .avatar-upload .upload-info .upload-label {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .avatar-upload .upload-info .upload-desc {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }
        
        .avatar-upload .upload-info .file-input-wrapper {
            margin-top: 8px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .avatar-upload .upload-info .file-input-wrapper input[type="file"] {
            padding: 6px 10px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.8rem;
            background: var(--bg-card);
            color: var(--text-primary);
            cursor: pointer;
        }
        
        .avatar-upload .upload-info .file-input-wrapper input[type="file"]::-webkit-file-upload-button {
            padding: 4px 12px;
            border: none;
            border-radius: 4px;
            background: var(--primary);
            color: white;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.75rem;
        }
        
        .avatar-upload .upload-info .file-input-wrapper input[type="file"]::-webkit-file-upload-button:hover {
            background: var(--primary-dark);
        }
        
        .message-box {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .message-box.success {
            background: #D1FAE5;
            color: #059669;
            border: 1px solid #059669;
        }
        
        .message-box.error {
            background: #FEE2E2;
            color: #DC2626;
            border: 1px solid #DC2626;
        }
        
        .help-text {
            font-size: 0.7rem;
            color: var(--text-secondary);
            margin-top: 4px;
        }

        .page-header-custom {
            border-bottom: 3px solid var(--success);
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        
        .page-header-custom .page-title {
            color: var(--success-dark);
            font-size: 1.8rem;
            font-weight: 700;
        }
        
        [data-theme="dark"] .page-header-custom .page-title {
            color: var(--success-light);
        }
        
        .page-header-custom .page-subtitle {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .page-header-custom .branch-tag {
            background: var(--success);
            color: white;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        [data-theme="dark"] .page-header-custom .branch-tag {
            background: #047857;
        }

        .page-header-custom .role-badge {
            background: var(--primary-bg);
            color: var(--primary);
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        [data-theme="dark"] .page-header-custom .role-badge {
            background: #1E3A5F;
            color: #6EA8FE;
        }

        .page-header-custom .reception-badge {
            background: rgba(251, 191, 36, 0.2);
            color: #D97706;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            border: 1px solid rgba(251, 191, 36, 0.2);
        }

        [data-theme="dark"] .page-header-custom .reception-badge {
            background: rgba(251, 191, 36, 0.15);
            color: #FCD34D;
            border-color: rgba(251, 191, 36, 0.2);
        }

        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }

        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand { 
            color: var(--success); 
            font-weight: 600; 
        }

        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
        }

        @media (max-width: 768px) {
            .form-card {
                padding: 16px 18px;
            }
            .avatar-upload {
                flex-direction: column;
                text-align: center;
            }
            .avatar-upload .upload-info .file-input-wrapper {
                justify-content: center;
            }
            .form-actions {
                flex-direction: column;
            }
            .form-actions .btn {
                width: 100%;
                justify-content: center;
            }
            .page-header-custom .page-title { font-size: 1.3rem; }
        }

        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .form-card { padding: 12px 14px; }
        }
    </style>
    
    <!-- Preload dark mode from localStorage -->
    <script>
        (function() {
            var darkMode = localStorage.getItem('darkMode');
            if (darkMode === 'true') {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>
</head>
<body>

<!-- TOP NAV is loaded from header -->

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header-custom flex flex-wrap justify-between items-center gap-3 mb-5">
        <div>
            <h1 class="page-title">
                <i class="fas fa-user-edit mr-2" style="color: var(--primary);"></i> Edit Profile
                <span class="role-badge ml-2"><?= strtoupper($user_role) ?></span>
                <?php if ($is_reception): ?>
                    <span class="reception-badge ml-1">
                        <i class="fas fa-eye"></i> Reception
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                Update your profile information
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
            </p>
        </div>
        <div>
            <a href="profile.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Profile
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="message-box <?= $message_type ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- EDIT PROFILE FORM -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        
        <!-- Profile Picture Upload -->
        <div class="lg:col-span-1">
            <div class="form-card">
                <div class="form-title">
                    <i class="fas fa-camera"></i>
                    Profile Picture
                </div>
                
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update_avatar">
                    
                    <div class="avatar-upload">
                        <?php if (!empty($profile_pic)): ?>
                            <img src="<?= $profile_pic_url ?>" alt="Profile" class="current-avatar">
                        <?php else: ?>
                            <div class="avatar-placeholder">
                                <?= strtoupper(substr($user_full_name, 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="upload-info">
                            <div class="upload-label">Change Profile Picture</div>
                            <div class="upload-desc">Upload a new profile picture</div>
                            <div class="file-input-wrapper">
                                <input type="file" name="profile_pic" accept="image/*" id="profilePicInput">
                                <button type="submit" class="btn btn-success" style="padding: 6px 16px; font-size:0.8rem;">
                                    <i class="fas fa-upload"></i> Upload
                                </button>
                            </div>
                            <div class="help-text">Allowed: JPG, PNG, GIF, WEBP (Max 5MB)</div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Profile Information -->
        <div class="lg:col-span-2">
            <div class="form-card">
                <div class="form-title">
                    <i class="fas fa-user-circle"></i>
                    Personal Information
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        
                        <!-- Full Name -->
                        <div class="form-row">
                            <label class="form-label">Full Name <span class="required">*</span></label>
                            <input type="text" name="full_name" class="form-control" 
                                   value="<?= htmlspecialchars($user_full_name) ?>" required>
                        </div>
                        
                        <!-- Username (Read Only) -->
                        <div class="form-row">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" 
                                   value="<?= htmlspecialchars($user_username) ?>" disabled>
                            <div class="help-text">Username cannot be changed</div>
                        </div>
                        
                        <!-- Email -->
                        <div class="form-row">
                            <label class="form-label">Email <span class="required">*</span></label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?= htmlspecialchars($user_email) ?>" required>
                        </div>
                        
                        <!-- Phone -->
                        <div class="form-row">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" name="phone" class="form-control" 
                                   value="<?= htmlspecialchars($user_phone) ?>">
                        </div>
                        
                        <!-- Branch (Read Only) -->
                        <div class="form-row">
                            <label class="form-label">Branch</label>
                            <input type="text" class="form-control" 
                                   value="<?= htmlspecialchars($user_branch_name) ?>" disabled>
                        </div>
                        
                        <!-- Role (Read Only) -->
                        <div class="form-row">
                            <label class="form-label">Role</label>
                            <input type="text" class="form-control" 
                                   value="<?= ucfirst($user_role) ?>" disabled>
                        </div>
                        
                    </div>
                    
                    <!-- ================================================================ -->
                    <!-- PASSWORD CHANGE SECTION -->
                    <!-- ================================================================ -->
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <h4 class="font-semibold text-gray-700 mb-3">
                            <i class="fas fa-key mr-2 text-blue-600"></i> Change Password
                        </h4>
                        <div class="text-sm text-gray-500 mb-3">
                            Leave blank if you don't want to change your password.
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            
                            <!-- Current Password -->
                            <div class="form-row">
                                <label class="form-label">Current Password</label>
                                <input type="password" name="current_password" class="form-control" 
                                       placeholder="Enter current password">
                            </div>
                            
                            <!-- New Password -->
                            <div class="form-row">
                                <label class="form-label">New Password</label>
                                <input type="password" name="new_password" class="form-control" 
                                       placeholder="Enter new password (min 6 chars)">
                            </div>
                            
                            <!-- Confirm Password -->
                            <div class="form-row">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control" 
                                       placeholder="Confirm new password">
                            </div>
                            
                        </div>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <a href="profile.php" class="btn btn-outline">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="reset" class="btn btn-outline">
                            <i class="fas fa-undo"></i> Reset
                        </button>
                    </div>
                    
                </form>
            </div>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Edit Profile
            <span class="text-gray-300 mx-2">|</span>
            <span style="color:<?= $is_reception ? '#FCD34D' : '#FFD700' ?>;font-weight:600;">
                👤 <?= htmlspecialchars($user_full_name) ?>
                <?php if ($is_reception): ?>
                    <span style="color:#FCD34D;font-weight:500;font-size:0.6rem;background:rgba(251,191,36,0.15);padding:2px 10px;border-radius:10px;margin-left:4px;">👀 Reception</span>
                <?php endif; ?>
            </span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <i class="fas fa-info-circle"></i>
    <div>
        <p id="toastTitle">Notification</p>
        <p id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE - SYNC WITH HEADER
    // ================================================================
    // Note: Dark mode is controlled by header.

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
        
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 1024) {
                if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                    sidebar.classList.remove('open');
                }
            }
        });
    }

    // ================================================================
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    if (!searchBtn && !searchInput) {
        searchBtn = document.querySelector('.top-nav .search-btn');
        searchInput = document.querySelector('.top-nav #searchInput');
    }
    
    function performSearch() {
        var query = searchInput?.value?.trim() || '';
        if (query.length > 0) {
            window.location.href = 'search.php?q=' + encodeURIComponent(query);
        }
    }
    
    if (searchBtn) {
        searchBtn.addEventListener('click', performSearch);
    }
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') performSearch();
        });
    }

    // ================================================================
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'toast';
            toast.className = 'toast-custom';
            toast.innerHTML = `
                <i class="fas fa-info-circle"></i>
                <div>
                    <p id="toastTitle">Notification</p>
                    <p id="toastMessage"></p>
                </div>
            `;
            document.body.appendChild(toast);
        }
        
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
    // FILE INPUT PREVIEW
    // ================================================================
    var profilePicInput = document.getElementById('profilePicInput');
    if (profilePicInput) {
        profilePicInput.addEventListener('change', function(e) {
            var file = this.files[0];
            if (file) {
                var maxSize = 5 * 1024 * 1024;
                if (file.size > maxSize) {
                    showToast('Error', 'File size exceeds 5MB limit!', 'error');
                    this.value = '';
                    return;
                }
                
                var allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    showToast('Error', 'Only JPG, PNG, GIF and WEBP files are allowed!', 'error');
                    this.value = '';
                    return;
                }
                
                var reader = new FileReader();
                var preview = document.querySelector('.current-avatar') || document.querySelector('.avatar-placeholder');
                reader.onload = function(e) {
                    if (preview) {
                        if (preview.tagName === 'IMG') {
                            preview.src = e.target.result;
                        } else {
                            preview.outerHTML = '<img src="' + e.target.result + '" alt="Profile" class="current-avatar" style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid #0B5ED7;">';
                        }
                    }
                };
                reader.readAsDataURL(file);
                
                showToast('Success', 'Image preview loaded. Click Upload to save.', 'info');
            }
        });
    }

    console.log('%c💰 Braick - Cashier Edit Profile', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c👤 Role: <?= htmlspecialchars($user_role) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($user_branch_name) ?>', 'font-size:13px; color:#6EA8FE;');
    console.log('%c✅ ALLOWED ROLES: Cashier, Reception, Admin', 'font-size:13px; color:#34D399;');
    console.log('%c📁 Upload Dir: <?= $upload_dir ?>', 'font-size:13px; color:#64748B;');
    console.log('%c🌙 Dark mode controlled by header', 'font-size:13px; color:#8B5CF6;');
</script>

</body>
</html>