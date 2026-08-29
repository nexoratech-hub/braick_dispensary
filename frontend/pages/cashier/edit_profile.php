<?php
// ================================================================
// FILE: frontend/pages/cashier/edit_profile.php
// CASHIER - EDIT PROFILE
// FIXED: Uses correct database tables (users only)
// 8 CARDS DESIGN: 4 TOP + 4 BOTTOM
// WITH AUTO-UPDATE (3 SECONDS)
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
// GET GLOBAL STATS FOR AUTO-UPDATE - Using bills table
// ================================================================
$today = date('Y-m-d');

try {
    // Today Payments
    $stmt = $db->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(paid_amount), 0) as total
        FROM bills 
        WHERE branch_id = ? 
        AND DATE(updated_at) = ?
        AND paid_amount > 0
        AND status IN ('paid', 'partial')
    ");
    $stmt->execute([$user_branch_id, $today]);
    $today_payments = $stmt->fetch(PDO::FETCH_ASSOC);
    $today_payments_count = $today_payments['count'] ?? 0;
    $today_payments_total = $today_payments['total'] ?? 0;

    // Pending Bills
    $stmt = $db->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total
        FROM bills 
        WHERE branch_id = ? AND status = 'pending'
    ");
    $stmt->execute([$user_branch_id]);
    $pending_bills = $stmt->fetch(PDO::FETCH_ASSOC);
    $pending_bills_count = $pending_bills['count'] ?? 0;
    $pending_bills_total = $pending_bills['total'] ?? 0;

    // Paid Bills
    $stmt = $db->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(paid_amount), 0) as total
        FROM bills 
        WHERE branch_id = ? AND status = 'paid'
    ");
    $stmt->execute([$user_branch_id]);
    $paid_bills = $stmt->fetch(PDO::FETCH_ASSOC);
    $paid_bills_count = $paid_bills['count'] ?? 0;
    $paid_bills_total = $paid_bills['total'] ?? 0;

    // Cancelled Bills
    $stmt = $db->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total
        FROM bills 
        WHERE branch_id = ? AND status = 'cancelled'
    ");
    $stmt->execute([$user_branch_id]);
    $cancelled_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $cancelled_bills_count = $cancelled_stats['count'] ?? 0;
    $cancelled_bills_total = $cancelled_stats['total'] ?? 0;

    // Total Bills
    $stmt = $db->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total
        FROM bills WHERE branch_id = ?
    ");
    $stmt->execute([$user_branch_id]);
    $total_bills = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_bills_count = $total_bills['count'] ?? 0;
    $total_bills_amount = $total_bills['total'] ?? 0;

    // Partial Bills
    $stmt = $db->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(paid_amount), 0) as total_paid, COALESCE(SUM(balance), 0) as total_balance
        FROM bills 
        WHERE branch_id = ? AND status = 'partial'
    ");
    $stmt->execute([$user_branch_id]);
    $partial_bills = $stmt->fetch(PDO::FETCH_ASSOC);
    $partial_bills_count = $partial_bills['count'] ?? 0;
    $partial_bills_paid = $partial_bills['total_paid'] ?? 0;
    $partial_bills_balance = $partial_bills['total_balance'] ?? 0;

    // Expenses
    $stmt = $db->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total
        FROM expenses 
        WHERE branch_id = ? AND status = 'paid'
    ");
    $stmt->execute([$user_branch_id]);
    $expenses = $stmt->fetch(PDO::FETCH_ASSOC);
    $expenses_count = $expenses['count'] ?? 0;
    $expenses_total = $expenses['total'] ?? 0;
    
    // Today's receipts count
    $stmt = $db->prepare("
        SELECT COUNT(*) as count
        FROM receipts 
        WHERE branch_id = ? AND DATE(printed_at) = ?
    ");
    $stmt->execute([$user_branch_id, $today]);
    $receipts_today = $stmt->fetch(PDO::FETCH_ASSOC);
    $today_receipts = $receipts_today['count'] ?? 0;
    
} catch (Exception $e) {
    error_log("Global stats error: " . $e->getMessage());
    $today_payments_count = 0;
    $today_payments_total = 0;
    $pending_bills_count = 0;
    $pending_bills_total = 0;
    $paid_bills_count = 0;
    $paid_bills_total = 0;
    $cancelled_bills_count = 0;
    $cancelled_bills_total = 0;
    $total_bills_count = 0;
    $total_bills_amount = 0;
    $partial_bills_count = 0;
    $partial_bills_paid = 0;
    $partial_bills_balance = 0;
    $expenses_count = 0;
    $expenses_total = 0;
    $today_receipts = 0;
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
            --purple: #7C3AED;
            --purple-bg: #EDE9FE;
            --pink: #DB2777;
            --pink-bg: #FCE4EC;
            --indigo: #4F46E5;
            --indigo-bg: #E0E7FF;
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
        }

        /* DARK MODE */
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
        }

        /* Dark mode fixes */
        [data-theme="dark"] .bg-white { background-color: #1E293B !important; }
        [data-theme="dark"] .text-gray-700 { color: #CBD5E1 !important; }
        [data-theme="dark"] .text-gray-800 { color: #E2E8F0 !important; }
        [data-theme="dark"] .text-gray-900 { color: #F1F5F9 !important; }
        [data-theme="dark"] .border-gray-200 { border-color: #334155 !important; }
        [data-theme="dark"] .bg-gray-50 { background-color: #1E293B !important; }
        [data-theme="dark"] .bg-gray-100 { background-color: #2D3748 !important; }
        [data-theme="dark"] .shadow { box-shadow: 0 1px 3px rgba(0,0,0,0.3) !important; }
        [data-theme="dark"] .shadow-md { box-shadow: 0 4px 12px rgba(0,0,0,0.3) !important; }
        [data-theme="dark"] .shadow-lg { box-shadow: 0 10px 25px rgba(0,0,0,0.4) !important; }
        [data-theme="dark"] .border-t { border-top-color: #334155 !important; }
        [data-theme="dark"] .border-t-gray-200 { border-top-color: #334155 !important; }
        [data-theme="dark"] .text-blue-600 { color: #6EA8FE !important; }
        [data-theme="dark"] .text-gray-500 { color: #94A3B8 !important; }
        [data-theme="dark"] .message-box.success { background: #1A3A2A; color: #34D399; border-color: #34D399; }
        [data-theme="dark"] .message-box.error { background: #3A1A1A; color: #F87171; border-color: #F87171; }
        [data-theme="dark"] .form-card { background: #1E293B; border-color: #334155; }
        [data-theme="dark"] .form-card:hover { border-color: #34D399; }
        [data-theme="dark"] .form-control { background: #1E293B; color: #F1F5F9; border-color: #334155; }
        [data-theme="dark"] .form-control:focus { border-color: #34D399; box-shadow: 0 0 0 3px rgba(52, 211, 153, 0.15); }
        [data-theme="dark"] .form-control:disabled { opacity: 0.5; }
        [data-theme="dark"] .avatar-upload { background: #0F172A; border-color: #334155; }
        [data-theme="dark"] .btn-outline { border-color: #334155; color: #94A3B8; }
        [data-theme="dark"] .btn-outline:hover { border-color: #34D399; color: #34D399; background: #1A3A2A; }
        [data-theme="dark"] .page-header-custom .page-title { color: var(--success-light); }
        [data-theme="dark"] .page-header-custom .branch-tag { background: #047857; }
        [data-theme="dark"] .page-header-custom .role-badge { background: #1E3A5F; color: #6EA8FE; }
        [data-theme="dark"] .page-header-custom .reception-badge { background: rgba(251, 191, 36, 0.15); color: #FCD34D; border-color: rgba(251, 191, 36, 0.2); }
        [data-theme="dark"] .stat-card { background: #1E293B; border-color: #334155; }
        [data-theme="dark"] .stat-card:hover { border-color: #34D399; transform: translateY(-4px); }
        [data-theme="dark"] .stat-card .stat-number { color: #F1F5F9 !important; }
        [data-theme="dark"] .stat-card .stat-number.green { color: #34D399 !important; }
        [data-theme="dark"] .stat-card .stat-number.red { color: #F87171 !important; }
        [data-theme="dark"] .stat-card .stat-number.blue { color: #6EA8FE !important; }
        [data-theme="dark"] .stat-card .stat-number.yellow { color: #FBBF24 !important; }
        [data-theme="dark"] .stat-card .stat-number.purple { color: #A78BFA !important; }
        [data-theme="dark"] .stat-card .stat-number.pink { color: #F472B6 !important; }
        [data-theme="dark"] .stat-card .stat-number.indigo { color: #818CF8 !important; }
        [data-theme="dark"] .footer { border-top-color: #334155; }
        [data-theme="dark"] .footer .footer-brand { color: #34D399; }
        [data-theme="dark"] .toast-custom.success { background: #059669; }
        [data-theme="dark"] .toast-custom.error { background: #DC2626; }
        
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

        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }

        /* ================================================================
           PAGE HEADER
           ================================================================ */
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

        .page-header-custom .role-badge {
            background: var(--primary-bg);
            color: var(--primary);
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
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

        .page-header-custom .btn-outline-light {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 18px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.82rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(4px);
        }
        
        .page-header-custom .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }

        /* ================================================================
           STATS CARDS - 4 TOP + 4 BOTTOM (8 CARDS TOTAL)
           ================================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            max-width: 1200px;
            margin: 0 auto 16px;
        }
        
        .stats-grid-bottom {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            max-width: 1200px;
            margin: 0 auto 20px;
        }
        
        .stat-card {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 16px 18px;
            border: 2px solid var(--border-color);
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            border-radius: 14px 14px 0 0;
        }
        
        .stat-card:hover {
            border-color: var(--success);
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }
        
        .stat-card .stat-icon {
            font-size: 1.6rem;
            margin-bottom: 4px;
            display: block;
        }
        
        .stat-card .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            line-height: 1.2;
            letter-spacing: -0.02em;
        }
        
        .stat-card .stat-number.green { color: var(--success); }
        .stat-card .stat-number.red { color: #DC2626; }
        .stat-card .stat-number.blue { color: var(--primary); }
        .stat-card .stat-number.yellow { color: var(--warning); }
        .stat-card .stat-number.purple { color: var(--purple); }
        .stat-card .stat-number.pink { color: #DB2777; }
        .stat-card .stat-number.indigo { color: #4F46E5; }
        
        .stat-card .stat-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 500;
            margin-top: 2px;
        }
        
        .stat-card .stat-sub {
            font-size: 0.6rem;
            color: var(--text-secondary);
            margin-top: 2px;
            opacity: 0.7;
        }
        
        /* Card accent colors */
        .stat-card.accent-blue::after { background: var(--primary); }
        .stat-card.accent-red::after { background: #DC2626; }
        .stat-card.accent-green::after { background: var(--success); }
        .stat-card.accent-yellow::after { background: var(--warning); }
        .stat-card.accent-purple::after { background: var(--purple); }
        .stat-card.accent-pink::after { background: #DB2777; }
        .stat-card.accent-indigo::after { background: #4F46E5; }
        .stat-card.accent-orange::after { background: #EA580C; }

        /* ================================================================
           FORM CARD
           ================================================================ */
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

        /* ================================================================
           FOOTER
           ================================================================ */
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

        /* ================================================================
           TOAST
           ================================================================ */
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 14px 20px;
            border-radius: 12px;
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
        
        .toast-custom.show {
            transform: translateY(0);
            opacity: 1;
        }
        
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }

        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .stats-grid-bottom { grid-template-columns: repeat(2, 1fr); }
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
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .stats-grid-bottom { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .stat-card { padding: 12px 14px; }
            .stat-card .stat-number { font-size: 1.4rem; }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .form-card { padding: 12px 14px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 6px; }
            .stats-grid-bottom { grid-template-columns: repeat(2, 1fr); gap: 6px; }
            .stat-card { padding: 8px 10px; }
            .stat-card .stat-number { font-size: 1.1rem; }
            .stat-card .stat-label { font-size: 0.55rem; }
            .stat-card .stat-icon { font-size: 1.2rem; }
        }
        
        @media (max-width: 400px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 4px; }
            .stats-grid-bottom { grid-template-columns: repeat(2, 1fr); gap: 4px; }
            .stat-card { padding: 6px 6px; }
            .stat-card .stat-number { font-size: 0.9rem; }
            .stat-card .stat-label { font-size: 0.5rem; }
            .stat-card .stat-icon { font-size: 1rem; }
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

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
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
    <!-- 8 STATS CARDS - 4 TOP + 4 BOTTOM -->
    <!-- ================================================================ -->
    
    <!-- TOP 4 CARDS -->
    <div class="stats-grid" id="globalStatsTop">
        <!-- Card 1: Today Payments -->
        <div class="stat-card accent-blue" onclick="window.location.href='payment_history.php'">
            <span class="stat-icon">💳</span>
            <p class="stat-number blue" id="statTodayPayments"><?= number_format($today_payments_count) ?></p>
            <p class="stat-label">Today Payments</p>
            <p class="stat-sub">TSh <?= number_format($today_payments_total) ?></p>
        </div>
        
        <!-- Card 2: Pending Bills -->
        <div class="stat-card accent-red" onclick="window.location.href='pending_bills.php'">
            <span class="stat-icon">⏳</span>
            <p class="stat-number red" id="statPending"><?= number_format($pending_bills_count) ?></p>
            <p class="stat-label">Pending Bills</p>
            <p class="stat-sub">TSh <?= number_format($pending_bills_total) ?></p>
        </div>
        
        <!-- Card 3: Paid Bills -->
        <div class="stat-card accent-green" onclick="window.location.href='paid_bills.php'">
            <span class="stat-icon">✅</span>
            <p class="stat-number green" id="statPaid"><?= number_format($paid_bills_count) ?></p>
            <p class="stat-label">Paid Bills</p>
            <p class="stat-sub">TSh <?= number_format($paid_bills_total) ?></p>
        </div>
        
        <!-- Card 4: Cancelled Bills -->
        <div class="stat-card accent-red" onclick="window.location.href='cancelled_bills.php'">
            <span class="stat-icon">❌</span>
            <p class="stat-number red" id="statCancelled"><?= number_format($cancelled_bills_count) ?></p>
            <p class="stat-label">Cancelled Bills</p>
            <p class="stat-sub">TSh <?= number_format($cancelled_bills_total) ?></p>
        </div>
    </div>
    
    <!-- BOTTOM 4 CARDS -->
    <div class="stats-grid-bottom" id="globalStatsBottom">
        <!-- Card 5: Total Bills -->
        <div class="stat-card accent-purple" onclick="window.location.href='all_bills.php'">
            <span class="stat-icon">📋</span>
            <p class="stat-number purple" id="statTotal"><?= number_format($total_bills_count) ?></p>
            <p class="stat-label">Total Bills</p>
            <p class="stat-sub">TSh <?= number_format($total_bills_amount) ?></p>
        </div>
        
        <!-- Card 6: Partial Bills -->
        <div class="stat-card accent-yellow" onclick="window.location.href='partial_payments.php'">
            <span class="stat-icon">💰</span>
            <p class="stat-number yellow" id="statPartial"><?= number_format($partial_bills_count) ?></p>
            <p class="stat-label">Partial Bills</p>
            <p class="stat-sub">Paid: TSh <?= number_format($partial_bills_paid) ?></p>
        </div>
        
        <!-- Card 7: Expenses -->
        <div class="stat-card accent-pink" onclick="window.location.href='expenses.php'">
            <span class="stat-icon">💸</span>
            <p class="stat-number pink" id="statExpenses"><?= number_format($expenses_count) ?></p>
            <p class="stat-label">Expenses</p>
            <p class="stat-sub">TSh <?= number_format($expenses_total) ?></p>
        </div>
        
        <!-- Card 8: Today Receipts -->
        <div class="stat-card accent-indigo" onclick="window.location.href='receipt_history.php'">
            <span class="stat-icon">🧾</span>
            <p class="stat-number indigo" id="statReceipts"><?= number_format($today_receipts) ?></p>
            <p class="stat-label">Today Receipts</p>
            <p class="stat-sub">Printed today</p>
        </div>
    </div>

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
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
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
<!-- JAVASCRIPT - AUTO UPDATE EVERY 3 SECONDS -->
<!-- ================================================================ -->
<script>
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

    // ================================================================
    // MANUAL REFRESH
    // ================================================================
    function manualRefresh() {
        var btn = document.getElementById('refreshBtn');
        if (btn) {
            btn.innerHTML = '<span class="spinner" style="display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,0.3);border-top-color:white;border-radius:50%;animation:spin 0.6s linear infinite;"></span> Loading...';
            btn.disabled = true;
        }
        
        fetchDashboardData();
        
        setTimeout(function() {
            if (btn) {
                btn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh';
                btn.disabled = false;
            }
            showToast('✅ Refreshed', 'Page data updated manually', 'success');
        }, 1500);
    }

    // ================================================================
    // FETCH DASHBOARD DATA (AJAX)
    // ================================================================
    function fetchDashboardData() {
        var url = 'get_dashboard_data.php?t=' + Date.now();
        
        fetch(url)
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    updateStats(data);
                } else {
                    console.error('Failed to fetch dashboard data:', data.message);
                }
            })
            .catch(function(error) {
                console.error('Fetch error:', error);
            });
    }

    // ================================================================
    // UPDATE STATS UI
    // ================================================================
    function updateStats(data) {
        // Update all 8 stat cards
        var statMap = {
            'statTodayPayments': data.today_payments_count || 0,
            'statPending': data.pending_bills || 0,
            'statPaid': data.paid_bills || 0,
            'statCancelled': data.cancelled_bills || 0,
            'statTotal': data.total_bills || 0,
            'statPartial': data.partial_bills || 0,
            'statExpenses': data.expenses_count || 0,
            'statReceipts': data.today_receipts || 0
        };
        
        for (var key in statMap) {
            var el = document.getElementById(key);
            if (el) {
                el.textContent = Number(statMap[key]).toLocaleString();
            }
        }
        
        // Update footer timestamp
        var footerTs = document.getElementById('footerTimestamp');
        if (footerTs) {
            var now = new Date();
            var timeStr = now.toLocaleTimeString('en-US', {
                hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
            });
            footerTs.textContent = 'Last updated: ' + timeStr;
        }
    }

    // ================================================================
    // AUTO UPDATE - EVERY 3 SECONDS
    // ================================================================
    var updateInterval = null;
    var isUpdating = false;
    
    function startAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
        }
        fetchDashboardData();
        updateInterval = setInterval(function() {
            if (!isUpdating) {
                isUpdating = true;
                fetchDashboardData();
                setTimeout(function() {
                    isUpdating = false;
                }, 1000);
            }
        }, 3000);
        console.log('%c🔄 Auto-update started (every 3s)', 'font-size:12px; color:#34D399;');
    }
    
    function stopAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
            updateInterval = null;
            console.log('%c⏹️ Auto-update stopped', 'font-size:12px; color:#DC2626;');
        }
    }

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopAutoUpdate();
        } else {
            startAutoUpdate();
        }
    });

    // ================================================================
    // ADD CSS ANIMATIONS
    // ================================================================
    var style = document.createElement('style');
    style.textContent = `
        @keyframes spin { to { transform: rotate(360deg); } }
        @keyframes pulse-dot { 
            0%, 100% { opacity: 1; transform: scale(1); } 
            50% { opacity: 0.5; transform: scale(0.8); } 
        }
        .stat-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .stat-card:hover { transform: translateY(-4px); }
        .form-control { transition: all 0.3s ease; }
        .btn { transition: all 0.3s ease; }
        .stat-number { transition: all 0.3s ease; }
    `;
    document.head.appendChild(style);

    // ================================================================
    // INIT
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            startAutoUpdate();
        }, 1000);
    });

    console.log('%c💰 Braick - Cashier Edit Profile (8 Cards + Auto-Update)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c👤 Role: <?= htmlspecialchars($user_role) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($user_branch_name) ?>', 'font-size:13px; color:#6EA8FE;');
    console.log('%c✅ ALLOWED ROLES: Cashier, Reception, Admin', 'font-size:13px; color:#34D399;');
    console.log('%c✅ 8 CARDS: 4 TOP + 4 BOTTOM', 'font-size:13px; color:#34D399;');
    console.log('%c🔄 Auto-update every 3 seconds', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>