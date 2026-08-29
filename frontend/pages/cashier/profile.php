<?php
// ================================================================
// FILE: frontend/pages/cashier/profile.php
// CASHIER - FULL PROFILE WITH PROFILE PICTURE UPLOAD (GREEN THEME)
// FIXED: Uses correct database tables
// 8 CARDS DESIGN: 4 TOP + 4 BOTTOM
// WITH AUTO-UPDATE (3 SECONDS)
// USES SHARED HEADER WITH DARK MODE
// ALLOWS RECEPTION, CASHIER AND ADMIN
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
    header('Location: ../login.php');
    exit;
}

// ================================================================
// ALLOWED ROLES: Cashier, Reception, Admin
// ================================================================
$allowed_roles = ['cashier', 'reception', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET USER DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Cashier';
$user_role = $_SESSION['role'] ?? 'cashier';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_username = $_SESSION['username'] ?? 'cashier';
$user_email = $_SESSION['email'] ?? '';
$user_phone = $_SESSION['phone'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// CHECK IF USER IS RECEPTION
// ================================================================
$is_reception = ($user_role === 'reception');

// ================================================================
// CHECK IF USER IS ADMIN
// ================================================================
$is_admin = ($user_role === 'admin');

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$message = '';
$message_type = '';

// ================================================================
// GET USER DATA FROM DATABASE
// ================================================================
try {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND status = 'active'");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $user_full_name = $user['full_name'] ?? $user_full_name;
        $user_email = $user['email'] ?? $user_email;
        $user_phone = $user['phone'] ?? $user_phone;
        $profile_pic = $user['profile_pic'] ?? '';
        $_SESSION['profile_pic'] = $profile_pic;
        $_SESSION['full_name'] = $user_full_name;
        $_SESSION['email'] = $user_email;
        $_SESSION['phone'] = $user_phone;
    }
    
    // ================================================================
    // HANDLE PROFILE PICTURE UPLOAD
    // ================================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_pic'])) {
        $file = $_FILES['profile_pic'];
        $upload_dir = __DIR__ . '/../../../assets/uploads/profiles/';
        
        // Create directory if not exists
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Validate file
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        $errors = [];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Failed to upload file. Error code: ' . $file['error'];
        }
        
        if (!in_array($file['type'], $allowed_types)) {
            $errors[] = 'Only JPG, PNG, GIF, and WEBP images are allowed.';
        }
        
        if ($file['size'] > $max_size) {
            $errors[] = 'File size must be less than 5MB.';
        }
        
        if (empty($errors)) {
            // Generate unique filename
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_filename = 'user_' . $user_id . '_' . time() . '.' . $file_extension;
            $file_path = $upload_dir . $new_filename;
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                // Delete old profile picture if exists
                if (!empty($profile_pic) && file_exists($upload_dir . $profile_pic)) {
                    unlink($upload_dir . $profile_pic);
                }
                
                // Update database
                $stmt = $db->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
                if ($stmt->execute([$new_filename, $user_id])) {
                    $profile_pic = $new_filename;
                    $_SESSION['profile_pic'] = $new_filename;
                    $message = "Profile picture updated successfully!";
                    $message_type = 'success';
                    
                    // Refresh user data
                    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    $profile_pic = $user['profile_pic'] ?? '';
                    $_SESSION['profile_pic'] = $profile_pic;
                } else {
                    $errors[] = 'Failed to update database.';
                }
            } else {
                $errors[] = 'Failed to move uploaded file.';
            }
        }
        
        if (!empty($errors)) {
            $message = implode('<br>', $errors);
            $message_type = 'error';
        }
    }
    
    // ================================================================
    // HANDLE PROFILE UPDATE (name, email, phone)
    // ================================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        
        $errors = [];
        if (empty($full_name)) $errors[] = 'Full name is required';
        if (empty($email)) $errors[] = 'Email is required';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address';
        
        if (empty($errors)) {
            $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?");
            if ($stmt->execute([$full_name, $email, $phone, $user_id])) {
                $_SESSION['full_name'] = $full_name;
                $_SESSION['email'] = $email;
                $_SESSION['phone'] = $phone;
                $user_full_name = $full_name;
                $user_email = $email;
                $user_phone = $phone;
                $message = "Profile updated successfully!";
                $message_type = 'success';
                
                // Refresh user data
                $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $errors[] = 'Failed to update profile.';
            }
        }
        
        if (!empty($errors)) {
            $message = implode('<br>', $errors);
            $message_type = 'error';
        }
    }
    
} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '';

$profile_pic_exists = false;
if (!empty($profile_pic)) {
    $file_path = __DIR__ . '/../../../assets/uploads/profiles/' . $profile_pic;
    if (file_exists($file_path)) {
        $profile_pic_exists = true;
    }
}

// Default avatar
$default_avatar = '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';
$default_letter = strtoupper(substr($user_full_name, 0, 1));

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
// LOGO PATH
// ================================================================
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/cashier_header.php';
include_once '../../components/cashier_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Braick Dispensary</title>
    
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
        [data-theme="dark"] .profile-card { background: #1E293B; border-color: #334155; }
        [data-theme="dark"] .profile-card:hover { border-color: #059669; }
        [data-theme="dark"] .form-control { background: #0F172A; color: #F1F5F9; border-color: #334155; }
        [data-theme="dark"] .form-control:focus { border-color: #34D399; box-shadow: 0 0 0 4px rgba(52, 211, 153, 0.1); }
        [data-theme="dark"] .form-control:disabled { background: #1E293B; color: #94A3B8; }
        [data-theme="dark"] .info-row { border-bottom-color: #334155; }
        [data-theme="dark"] .info-label { color: #94A3B8; }
        [data-theme="dark"] .info-value { color: #F1F5F9; }
        [data-theme="dark"] .section-divider { border-top-color: #334155; }
        [data-theme="dark"] .page-header { background: linear-gradient(135deg, #059669, #047857) !important; }
        [data-theme="dark"] .footer { border-top-color: #334155; }
        [data-theme="dark"] .toast-custom.success { background: #059669; }
        [data-theme="dark"] .toast-custom.error { background: #DC2626; }
        [data-theme="dark"] .role-badge-display { background: #1E3A5F; color: #6EA8FE; }
        [data-theme="dark"] .role-badge-display[style*="background:rgba(255,255,255,0.2)"] { background: rgba(255,255,255,0.2) !important; color: white !important; }
        [data-theme="dark"] .branch-badge-display { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .text-gray-400 { color: #94A3B8 !important; }
        [data-theme="dark"] .text-gray-500 { color: #94A3B8 !important; }
        [data-theme="dark"] .text-gray-600 { color: #94A3B8 !important; }
        [data-theme="dark"] .bg-green-100 { background-color: rgba(5, 150, 105, 0.15) !important; }
        [data-theme="dark"] .text-green-700 { color: #34D399 !important; }
        [data-theme="dark"] .border-green-200 { border-color: rgba(5, 150, 105, 0.3) !important; }
        [data-theme="dark"] .bg-red-100 { background-color: rgba(220, 38, 38, 0.15) !important; }
        [data-theme="dark"] .text-red-700 { color: #F87171 !important; }
        [data-theme="dark"] .border-red-200 { border-color: rgba(220, 38, 38, 0.3) !important; }
        [data-theme="dark"] .dark-toggle-btn { background: #0F172A; border-color: #334155; color: #F1F5F9; }
        [data-theme="dark"] .dark-toggle-btn:hover { border-color: #34D399; background: #1E293B; }
        [data-theme="dark"] .icon-btn:hover { background: #0F172A; color: #34D399; }
        [data-theme="dark"] .avatar-default { border-color: #334155 !important; }
        [data-theme="dark"] .profile-avatar { border-color: #34D399; }
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
        
        /* ================================================================
           MAIN CONTENT
           ================================================================ */
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        /* ================================================================
           PAGE HEADER - GREEN THEME
           ================================================================ */
        .page-header {
            background: linear-gradient(135deg, var(--success), var(--success-dark));
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 20px rgba(5, 150, 105, 0.25);
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
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
        
        .page-header .page-title i {
            font-size: 2rem;
            opacity: 0.9;
        }
        
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
        
        .page-header .page-subtitle strong {
            color: white;
            font-weight: 600;
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
        
        .page-header .btn-outline-light {
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
            position: relative;
            z-index: 1;
        }
        
        .page-header .btn-outline-light:hover {
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
           PROFILE CARD
           ================================================================ */
        .profile-card {
            background: var(--bg-card);
            border-radius: 18px;
            padding: 32px 36px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            max-width: 800px;
            margin: 0 auto;
            box-shadow: var(--shadow-md);
        }
        
        .profile-card:hover {
            border-color: var(--success);
            box-shadow: 0 8px 30px rgba(5, 150, 105, 0.08);
        }
        
        .profile-avatar-wrapper {
            position: relative;
            display: inline-block;
        }
        
        .profile-avatar {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--success);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            transition: all 0.3s ease;
            background: var(--success-bg);
        }
        
        .profile-avatar:hover {
            transform: scale(1.02);
            box-shadow: 0 8px 30px rgba(5, 150, 105, 0.25);
        }
        
        .profile-avatar-wrapper .upload-overlay {
            position: absolute;
            bottom: 0;
            right: 0;
            background: var(--success);
            color: white;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
        }
        
        .profile-avatar-wrapper .upload-overlay:hover {
            background: var(--success-dark);
            transform: scale(1.1);
        }
        
        .profile-avatar-wrapper .upload-overlay input[type="file"] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .profile-name {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .profile-role {
            font-size: 0.9rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .profile-role .badge-role {
            background: var(--success);
            color: white;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .form-label {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
            display: block;
        }
        
        .form-label .label-icon {
            margin-right: 4px;
            color: var(--success);
        }
        
        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            outline: none;
            background: var(--bg-card);
            color: var(--text-primary);
        }
        
        .form-control:focus {
            border-color: var(--success);
            box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.08);
        }
        
        .form-control:disabled {
            background: var(--bg-body);
            color: var(--text-secondary);
            cursor: not-allowed;
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
            background: var(--success);
            color: white;
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);
        }
        
        .btn-primary:hover {
            background: var(--success-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(5, 150, 105, 0.35);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        
        .btn-outline:hover {
            background: var(--bg-body);
            border-color: var(--success);
            color: var(--success);
        }
        
        .btn-sm {
            padding: 6px 14px;
            font-size: 0.78rem;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 0.85rem;
        }
        
        .info-value {
            color: var(--text-primary);
            font-weight: 600;
            font-size: 0.85rem;
        }
        
        .section-divider {
            border: none;
            border-top: 2px solid var(--border-color);
            margin: 20px 0;
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
           BADGES
           ================================================================ */
        .role-badge-display {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            background: var(--primary-bg);
            color: var(--primary);
            text-transform: uppercase;
        }
        
        .branch-badge-display {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            background: var(--success-bg);
            color: var(--success);
        }
        
        .capitalize {
            text-transform: capitalize;
        }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 16px; }
            .profile-card { padding: 20px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .stats-grid-bottom { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .profile-card { padding: 16px; }
            .profile-avatar { width: 100px; height: 100px; }
            .profile-name { font-size: 1.3rem; }
            .profile-avatar-wrapper .upload-overlay { width: 32px; height: 32px; font-size: 0.8rem; }
            .btn { padding: 8px 16px; font-size: 0.78rem; }
            .info-row { flex-direction: column; gap: 4px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .stats-grid-bottom { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .stat-card { padding: 12px 14px; }
            .stat-card .stat-number { font-size: 1.4rem; }
        }
        
        @media (max-width: 640px) {
            .main-content { padding: 10px; }
            .profile-card { padding: 12px; }
            .profile-avatar { width: 80px; height: 80px; }
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
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-user-circle"></i>
                My Profile
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;"><?= strtoupper($user_role) ?></span>
                <?php if ($is_reception): ?>
                    <span class="role-badge-display" style="background:rgba(52,211,153,0.3);color:#34D399;border-color:rgba(52,211,153,0.3);font-size:0.6rem;">
                        <i class="fas fa-check-circle"></i> Full Access
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-id-card"></i>
                View and manage your profile information
                <?php if ($is_reception): ?>
                    <span class="header-badge" style="background:rgba(52,211,153,0.2);color:#34D399;border-color:rgba(52,211,153,0.2);padding:2px 12px;border-radius:16px;font-size:0.6rem;">
                        <i class="fas fa-user-tag"></i> Reception Access
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div>
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="p-4 rounded-xl mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200' ?>" style="max-width:800px;margin:0 auto 16px;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
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
    <!-- PROFILE CARD -->
    <!-- ================================================================ -->
    <div class="profile-card">
        
        <!-- Profile Picture -->
        <div class="flex flex-col md:flex-row items-center gap-6 mb-6">
            <div class="profile-avatar-wrapper">
                <?php if ($profile_pic_exists && !empty($profile_pic)): ?>
                    <img src="<?= '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic ?>" alt="Profile Picture" class="profile-avatar" id="profilePreview">
                <?php else: ?>
                    <img src="<?= $default_avatar ?>" alt="Default Avatar" class="profile-avatar" id="profilePreview"
                         onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22130%22 height=%22130%22%3E%3Crect width=%22130%22 height=%22130%22 fill=%22%23059669%22 rx=%2250%25%22/%3E%3Ctext x=%2265%22 y=%2285%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2250%22 font-weight=%22bold%22%3E<?= $default_letter ?>%3C/text%3E%3C/svg%3E'">
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <div class="upload-overlay" title="Upload Profile Picture">
                        <i class="fas fa-camera"></i>
                        <input type="file" name="profile_pic" accept="image/*" id="profilePicInput">
                    </div>
                </form>
            </div>
            
            <div class="text-center md:text-left">
                <h2 class="profile-name"><?= htmlspecialchars($user_full_name) ?></h2>
                <div class="profile-role">
                    <span class="badge-role"><?= ucfirst($user_role) ?></span>
                    <span><i class="fas fa-store-alt mr-1" style="color:var(--success);"></i> <?= htmlspecialchars($user_branch_name) ?></span>
                    <span><i class="fas fa-user mr-1" style="color:var(--success);"></i> <?= htmlspecialchars($user_username) ?></span>
                </div>
                <p class="text-sm text-gray-400 mt-1">
                    <i class="fas fa-calendar-alt mr-1" style="color:var(--success);"></i> Member since <?= date('F d, Y', strtotime($user['created_at'] ?? 'now')) ?>
                </p>
            </div>
        </div>
        
        <hr class="section-divider">
        
        <!-- ================================================================ -->
        <!-- PROFILE INFORMATION -->
        <!-- ================================================================ -->
        <form method="POST" action="">
            <input type="hidden" name="update_profile" value="1">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                
                <div>
                    <label class="form-label">
                        <i class="fas fa-user label-icon"></i> Full Name
                    </label>
                    <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user_full_name) ?>" required>
                </div>
                
                <div>
                    <label class="form-label">
                        <i class="fas fa-user-tag label-icon"></i> Username
                    </label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($user_username) ?>" disabled>
                </div>
                
                <div>
                    <label class="form-label">
                        <i class="fas fa-envelope label-icon"></i> Email Address
                    </label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user_email) ?>" required>
                </div>
                
                <div>
                    <label class="form-label">
                        <i class="fas fa-phone label-icon"></i> Phone Number
                    </label>
                    <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user_phone) ?>">
                </div>
                
                <div>
                    <label class="form-label">
                        <i class="fas fa-user-shield label-icon"></i> Role
                    </label>
                    <input type="text" class="form-control" value="<?= ucfirst($user_role) ?>" disabled>
                </div>
                
                <div>
                    <label class="form-label">
                        <i class="fas fa-store-alt label-icon"></i> Branch
                    </label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($user_branch_name) ?>" disabled>
                </div>
                
            </div>
            
            <div class="flex flex-wrap gap-3 mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Profile
                </button>
                <button type="reset" class="btn btn-outline">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </div>
        </form>
        
        <hr class="section-divider">
        
        <!-- ================================================================ -->
        <!-- ACCOUNT INFO -->
        <!-- ================================================================ -->
        <h3 class="text-lg font-semibold mb-3" style="color:var(--text-primary);">
            <i class="fas fa-info-circle mr-2" style="color:var(--success);"></i> Account Information
        </h3>
        
        <div class="info-row">
            <span class="info-label">User ID</span>
            <span class="info-value">#<?= $user_id ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Username</span>
            <span class="info-value"><?= htmlspecialchars($user_username) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Role</span>
            <span class="info-value capitalize"><?= ucfirst($user_role) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Branch</span>
            <span class="info-value"><?= htmlspecialchars($user_branch_name) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Status</span>
            <span class="info-value">
                <span class="badge" style="background:#D1FAE5;color:#059669;padding:2px 12px;border-radius:20px;font-size:0.7rem;font-weight:600;">Active</span>
            </span>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            My Profile
            <span class="text-gray-300 mx-2">|</span>
            <span class="text-gray-400">👤 <?= htmlspecialchars($user_full_name) ?></span>
            <?php if ($is_reception): ?>
                <span class="text-gray-300 mx-2">|</span>
                <span style="color:#34D399;">👀 Reception Access</span>
            <?php endif; ?>
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
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
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
        
        toast.className = 'toast-custom ' + (type || 'info');
        toastTitle.textContent = title || 'Notification';
        toastMessage.textContent = message || '';
        toast.style.display = 'flex';
        
        setTimeout(function() {
            toast.classList.add('show');
        }, 50);
        
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() {
                toast.style.display = 'none';
            }, 400);
        }, 3500);
    }

    // ================================================================
    // PROFILE PICTURE UPLOAD - AUTO SUBMIT
    // ================================================================
    document.getElementById('profilePicInput')?.addEventListener('change', function() {
        var file = this.files[0];
        if (file) {
            // Validate file size
            if (file.size > 5 * 1024 * 1024) {
                showToast('Error', 'File size must be less than 5MB', 'error');
                this.value = '';
                return;
            }
            
            // Validate file type
            var validTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];
            if (!validTypes.includes(file.type)) {
                showToast('Error', 'Only JPG, PNG, GIF, and WEBP images are allowed', 'error');
                this.value = '';
                return;
            }
            
            // Preview image
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('profilePreview').src = e.target.result;
            };
            reader.readAsDataURL(file);
            
            // Auto submit form
            document.getElementById('uploadForm').submit();
        }
    });

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
        .profile-avatar { transition: all 0.3s ease; }
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

    // ================================================================
    // CONSOLE
    // ================================================================
    console.log('%c👤 Braick - Cashier Profile (8 Cards + Auto-Update)', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?> (<?= htmlspecialchars($user_role) ?>)', 'font-size:13px; color:#0B5ED7;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($user_branch_name) ?>', 'font-size:13px; color:#64748B;');
    console.log('%c✅ Reception access: <?= $is_reception ? 'YES' : 'NO' ?>', 'font-size:13px; color:#34D399;');
    console.log('%c📸 Profile pic: <?= $profile_pic_exists ? 'Uploaded ✅' : 'Default' ?>', 'font-size:13px; color:#059669;');
    console.log('%c✅ 8 CARDS: 4 TOP + 4 BOTTOM', 'font-size:13px; color:#34D399;');
    console.log('%c🔄 Auto-update every 3 seconds', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>