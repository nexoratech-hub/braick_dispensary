<?php
// ================================================================
// FILE: frontend/pages/admin/profile.php
// SUPER ADMIN - PROFILE PAGE
// ✅ Uses SHARED header & sidebar (NO DUPLICATES)
// ✅ Blue theme + full dark mode support via --page-* variables
// ✅ Profile picture upload & username change
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../../auth/login.php');
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        case 'audit': header('Location: ../audit/dashboard.php'); break;
        default: header('Location: ../../auth/login.php'); break;
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

require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// GET USER DATA
// ================================================================
$stmt = $db->prepare("
    SELECT u.*, b.name as branch_name, b.location as branch_location
    FROM users u
    LEFT JOIN branches b ON u.branch_id = b.id
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $user = [
        'id' => $user_id, 'username' => $username ?: 'admin',
        'full_name' => $user_full_name, 'email' => $_SESSION['email'] ?? 'admin@braick.com',
        'phone' => $_SESSION['phone'] ?? '+255 700 000 000',
        'role' => $user_role, 'branch_id' => $user_branch_id,
        'branch_name' => 'Dodoma', 'branch_location' => 'Dodoma City, Tanzania',
        'profile_pic' => $profile_pic, 'created_at' => date('Y-m-d H:i:s')
    ];
}

// ================================================================
// CREATE UPLOAD DIRECTORY
// ================================================================
$upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/dispensary_system/frontend/assets/uploads/profiles/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// ================================================================
// HANDLE FORM SUBMISSIONS
// ================================================================
$message = '';
$message_type = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // UPDATE PROFILE
    if ($action === 'update_profile') {
        $full_name = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        
        if (empty($full_name)) $errors[] = 'Full name is required';
        if (empty($username)) $errors[] = 'Username is required';
        if (empty($email)) $errors[] = 'Email is required';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address';
        
        if (empty($errors) && $username !== $user['username']) {
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$username, $user_id]);
            if ($stmt->fetch()) $errors[] = 'Username already exists';
        }
        
        if (empty($errors) && $email !== $user['email']) {
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetch()) $errors[] = 'Email already exists';
        }
        
        if (empty($errors)) {
            try {
                $stmt = $db->prepare("UPDATE users SET full_name = ?, username = ?, email = ?, phone = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$full_name, $username, $email, $phone, $user_id]);
                
                $_SESSION['full_name'] = $full_name;
                $_SESSION['username'] = $username;
                $_SESSION['email'] = $email;
                $_SESSION['phone'] = $phone;
                
                $user['full_name'] = $full_name;
                $user['username'] = $username;
                $user['email'] = $email;
                $user['phone'] = $phone;
                
                try {
                    $stmt = $db->prepare("INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) VALUES (?, ?, 'profile_updated', ?, NOW())");
                    $stmt->execute([$user_id, $user_branch_id, "Profile updated by: $full_name"]);
                } catch (Exception $e) {}
                
                $message = '✅ Profile updated successfully!';
                $message_type = 'success';
            } catch (Exception $e) {
                $errors[] = 'Failed to update profile: ' . $e->getMessage();
            }
        }
        
        if (!empty($errors)) { $message = '❌ ' . implode('<br>❌ ', $errors); $message_type = 'error'; }
    }
    
    // UPDATE PASSWORD
    if ($action === 'update_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($current_password)) $errors[] = 'Current password is required';
        if (empty($new_password)) $errors[] = 'New password is required';
        if (strlen($new_password) < 6) $errors[] = 'Password must be at least 6 characters';
        if ($new_password !== $confirm_password) $errors[] = 'Passwords do not match';
        
        if (empty($errors)) {
            $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user_data && password_verify($current_password, $user_data['password'])) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $user_id]);
                
                try {
                    $stmt = $db->prepare("INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) VALUES (?, ?, 'password_changed', ?, NOW())");
                    $stmt->execute([$user_id, $user_branch_id, "Password changed for user: {$user['username']}"]);
                } catch (Exception $e) {}
                
                $message = '✅ Password changed successfully!';
                $message_type = 'success';
            } else {
                $errors[] = 'Current password is incorrect';
            }
        }
        
        if (!empty($errors)) { $message = '❌ ' . implode('<br>❌ ', $errors); $message_type = 'error'; }
    }
    
    // UPLOAD PROFILE PIC
    if ($action === 'upload_profile_pic') {
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_pic'];
            $file_name = $file['name'];
            $file_tmp = $file['tmp_name'];
            $file_size = $file['size'];
            
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $file_type = mime_content_type($file_tmp);
            
            if (!in_array($file_type, $allowed_types)) $errors[] = 'Only JPG, PNG, GIF, WEBP allowed';
            if ($file_size > 2 * 1024 * 1024) $errors[] = 'File size must be less than 2MB';
            
            if (empty($errors)) {
                $extension = pathinfo($file_name, PATHINFO_EXTENSION);
                $new_filename = 'user_' . $user_id . '_' . time() . '.' . $extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (!empty($user['profile_pic'])) {
                    $old_file = $upload_dir . $user['profile_pic'];
                    if (file_exists($old_file)) unlink($old_file);
                }
                
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    $stmt = $db->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
                    $stmt->execute([$new_filename, $user_id]);
                    
                    $_SESSION['profile_pic'] = $new_filename;
                    $user['profile_pic'] = $new_filename;
                    
                    $message = '✅ Profile picture updated!';
                    $message_type = 'success';
                } else {
                    $errors[] = 'Failed to upload image';
                }
            }
        } else {
            $errors[] = 'Please select an image';
        }
        
        if (!empty($errors)) { $message = '❌ ' . implode('<br>❌ ', $errors); $message_type = 'error'; }
    }
    
    // REMOVE PROFILE PIC
    if ($action === 'remove_profile_pic') {
        if (!empty($user['profile_pic'])) {
            $old_file = $upload_dir . $user['profile_pic'];
            if (file_exists($old_file)) unlink($old_file);
            
            $stmt = $db->prepare("UPDATE users SET profile_pic = NULL WHERE id = ?");
            $stmt->execute([$user_id]);
            
            $_SESSION['profile_pic'] = '';
            $user['profile_pic'] = '';
            
            $message = '✅ Profile picture removed!';
            $message_type = 'success';
        }
    }
}

// ================================================================
// PROFILE PICTURE URL
// ================================================================
$profile_pic = $user['profile_pic'] ?? '';
$profile_pic_url = '';
$show_initial = true;
$initial = strtoupper(substr($user['full_name'] ?? 'A', 0, 1));

if (!empty($profile_pic)) {
    $file_path = $_SERVER['DOCUMENT_ROOT'] . '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic;
    if (file_exists($file_path)) {
        $profile_pic_url = '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic;
        $show_initial = false;
    } else {
        $_SESSION['profile_pic'] = '';
        $user['profile_pic'] = '';
    }
}

$profile_pic_avatar = !empty($profile_pic_url) ? $profile_pic_url : 'data:image/svg+xml,' . urlencode('<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40"><rect width="40" height="40" rx="50%" fill="#0B5ED7"/><text x="20" y="26" text-anchor="middle" fill="white" font-size="18" font-weight="bold" font-family="Arial">' . $initial . '</text></svg>');

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// ✅ SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>

<!-- ================================================================
     PAGE-SPECIFIC CSS - TUMIA VARIABLES ZA HEADER (--page-*)
     ================================================================ -->
<style>
    /* PAGE HEADER */
    .page-header-profile {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        border-radius: 18px;
        padding: 24px 32px;
        margin-bottom: 24px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.25);
        position: relative;
        overflow: hidden;
    }

    .page-header-profile::before {
        content: '';
        position: absolute;
        top: -60%; right: -10%;
        width: 400px; height: 400px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
        pointer-events: none;
    }

    .page-header-profile .page-title-profile {
        color: white;
        font-size: 1.5rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin: 0;
    }

    .page-header-profile .page-subtitle-profile {
        color: rgba(255,255,255,0.85);
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
        margin-top: 6px;
    }

    .page-header-profile .branch-tag-profile {
        background: rgba(255,255,255,0.15);
        color: white;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
    }

    .page-header-profile .btn-back-profile {
        background: rgba(255,255,255,0.12);
        color: white;
        border: 1px solid rgba(255,255,255,0.2);
        padding: 8px 16px;
        border-radius: 12px;
        font-weight: 500;
        font-size: 0.8rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.3s;
        position: relative;
        z-index: 1;
    }

    .page-header-profile .btn-back-profile:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-2px);
        color: white;
    }

    /* PROFILE CARD */
    .profile-card-profile {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 16px;
        border: 1px solid var(--page-border, #E2E8F0);
        padding: 24px;
        text-align: center;
        box-shadow: var(--page-shadow-sm);
        transition: all 0.3s ease;
    }

    .profile-card-profile:hover {
        box-shadow: var(--page-shadow-md);
        border-color: var(--page-primary, #0B5ED7);
    }

    .profile-avatar-profile {
        position: relative;
        display: inline-block;
        margin-bottom: 16px;
    }

    .avatar-large-profile {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3rem;
        font-weight: 700;
        color: white;
        border: 4px solid var(--page-border, #E2E8F0);
        margin: 0 auto;
    }

    .avatar-large-img-profile {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid var(--page-border, #E2E8F0);
        margin: 0 auto;
        display: block;
    }

    .avatar-upload-btn-profile {
        position: absolute;
        bottom: 4px;
        right: 4px;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: var(--page-primary, #0B5ED7);
        color: white;
        border: 2px solid var(--page-bg-card, #FFFFFF);
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
    }

    .avatar-upload-btn-profile:hover {
        background: #0A4CA8;
        transform: scale(1.1);
    }

    .avatar-remove-btn-profile {
        position: absolute;
        bottom: 50px;
        right: 4px;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: #DC2626;
        color: white;
        border: 2px solid var(--page-bg-card, #FFFFFF);
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
    }

    .avatar-remove-btn-profile:hover {
        background: #B91C1C;
        transform: scale(1.1);
    }

    .profile-name-profile h2 {
        font-size: 1.3rem;
        font-weight: 700;
        color: var(--page-text-primary, #1E293B);
        margin: 0;
    }

    .profile-badge-profile {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        color: white;
        background: #0B5ED7;
    }

    .profile-username-profile {
        font-size: 0.85rem;
        color: var(--page-text-secondary, #64748B);
        margin-top: 4px;
    }

    .profile-username-profile i {
        color: var(--page-primary, #0B5ED7);
    }

    .profile-stats-profile {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
        margin-top: 16px;
        padding-top: 16px;
        border-top: 1px solid var(--page-border, #E2E8F0);
    }

    .profile-stats-profile .stat-item-profile {
        text-align: center;
    }

    .profile-stats-profile .stat-number-profile {
        display: block;
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--page-text-primary, #1E293B);
    }

    .profile-stats-profile .stat-label-profile {
        font-size: 0.6rem;
        color: var(--page-text-secondary, #64748B);
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    /* CARD */
    .card-profile {
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 16px;
        border: 1px solid var(--page-border, #E2E8F0);
        overflow: hidden;
        transition: all 0.3s ease;
        box-shadow: var(--page-shadow-sm);
        margin-bottom: 20px;
    }

    .card-profile:hover {
        box-shadow: var(--page-shadow-md);
    }

    .card-header-profile {
        padding: 16px 24px;
        background: var(--page-hover, #F8FAFC);
        border-bottom: 1px solid var(--page-border, #E2E8F0);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    [data-theme="dark"] .card-header-profile {
        background: #0F172A;
    }

    .card-title-profile {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--page-text-primary, #1E293B);
        margin: 0;
        display: flex;
        align-items: center;
    }

    .title-blue-profile { color: var(--page-primary, #0B5ED7); }
    .title-orange-profile { color: #D97706; }

    /* FORM */
    .profile-form-profile { padding: 24px; }

    .form-row-profile {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 16px;
    }

    .form-row-profile.single { grid-template-columns: 1fr; }

    .form-group-profile { margin-bottom: 0; }

    .form-label-profile {
        display: block;
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--page-text-primary, #1E293B);
        margin-bottom: 6px;
    }

    .form-label-profile i {
        margin-right: 6px;
        color: var(--page-primary, #0B5ED7);
        width: 16px;
        text-align: center;
    }

    .form-label-profile .required-profile {
        color: #DC2626;
        margin-left: 2px;
    }

    .form-control-profile {
        width: 100%;
        padding: 10px 14px;
        font-size: 0.9rem;
        color: var(--page-text-primary, #1E293B);
        background: var(--page-input-bg, #FFFFFF);
        border: 1.5px solid var(--page-border, #E2E8F0);
        border-radius: 8px;
        transition: all 0.3s ease;
        outline: none;
        font-family: inherit;
    }

    .form-control-profile:focus {
        border-color: var(--page-primary, #0B5ED7);
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
    }

    .form-control-profile:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        background: var(--page-hover, #F8FAFC);
    }

    .form-control-profile::placeholder {
        color: var(--page-text-muted, #94A3B8);
        opacity: 0.6;
    }

    .form-help-profile {
        display: block;
        font-size: 0.7rem;
        color: var(--page-text-secondary, #64748B);
        margin-top: 4px;
    }

    .form-actions-profile {
        display: flex;
        gap: 12px;
        margin-top: 8px;
        padding-top: 16px;
        border-top: 1px solid var(--page-border, #E2E8F0);
        flex-wrap: wrap;
    }

    /* BUTTONS */
    .btn-profile {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        cursor: pointer;
        text-decoration: none;
        background: var(--page-bg-card, #FFFFFF);
        color: var(--page-text-primary, #1E293B);
        border: 1.5px solid var(--page-border, #E2E8F0);
        font-family: inherit;
    }

    .btn-profile:hover {
        transform: translateY(-2px);
        box-shadow: var(--page-shadow-md);
    }

    .btn-primary-profile {
        background: var(--page-primary, #0B5ED7);
        color: white;
        border-color: var(--page-primary, #0B5ED7);
    }

    .btn-primary-profile:hover {
        background: #0A4CA8;
        border-color: #0A4CA8;
        color: white;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.35);
    }

    .btn-orange-profile {
        background: #D97706;
        color: white;
        border-color: #D97706;
    }

    .btn-orange-profile:hover {
        background: #B45309;
        border-color: #B45309;
        color: white;
    }

    .btn-outline-profile {
        background: transparent;
        color: var(--page-text-secondary, #64748B);
        border: 1.5px solid var(--page-border, #E2E8F0);
    }

    .btn-outline-profile:hover {
        background: var(--page-hover, #F8FAFC);
        border-color: var(--page-primary, #0B5ED7);
        color: var(--page-primary, #0B5ED7);
    }

    /* MESSAGE BOX */
    .message-box-profile {
        padding: 12px 18px;
        border-radius: 10px;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 16px;
        border: 1px solid transparent;
        position: relative;
        animation: slideDownProfile 0.4s ease;
    }

    @keyframes slideDownProfile {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .message-box-profile.success {
        background: #ECFDF5;
        color: #065F46;
        border-color: #A7F3D0;
    }

    .message-box-profile.error {
        background: #FEF2F2;
        color: #991B1B;
        border-color: #FECACA;
    }

    [data-theme="dark"] .message-box-profile.success {
        background: #1A3A2A;
        color: #34D399;
        border-color: #065F46;
    }

    [data-theme="dark"] .message-box-profile.error {
        background: #3A1A1A;
        color: #F87171;
        border-color: #7F1D1D;
    }

    /* GRID HELPERS */
    .grid-profile {
        display: grid;
        grid-template-columns: 1fr;
        gap: 20px;
    }

    @media (min-width: 1024px) {
        .grid-profile {
            grid-template-columns: 1fr 2fr;
        }
    }

    /* FOOTER */
    .footer-profile {
        margin-top: 30px;
        padding: 16px 20px;
        background: var(--page-bg-card, #FFFFFF);
        border-radius: 16px;
        border: 1px solid var(--page-border, #E2E8F0);
        text-align: center;
    }

    .footer-profile p {
        margin: 0;
        font-size: 0.8rem;
        color: var(--page-text-secondary, #64748B);
    }

    .footer-brand-profile {
        font-weight: 700;
        color: var(--page-primary, #0B5ED7);
    }

    /* RESPONSIVE */
    @media (max-width: 768px) {
        .form-row-profile { grid-template-columns: 1fr; gap: 12px; }
        .profile-card-profile { padding: 16px; }
        .avatar-large-profile, .avatar-large-img-profile { width: 100px; height: 100px; font-size: 2.5rem; }
        .form-actions-profile { flex-direction: column; }
        .form-actions-profile .btn-profile { width: 100%; justify-content: center; }
        .page-header-profile { padding: 16px 18px; }
        .page-header-profile .page-title-profile { font-size: 1.2rem; }
        .card-header-profile { padding: 12px 16px; }
        .profile-form-profile { padding: 16px; }
    }

    @media (max-width: 480px) {
        .profile-stats-profile { grid-template-columns: 1fr; gap: 4px; }
        .avatar-large-profile, .avatar-large-img-profile { width: 80px; height: 80px; font-size: 2rem; }
        .profile-name-profile h2 { font-size: 1.1rem; }
    }
</style>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header-profile">
        <div>
            <h1 class="page-title-profile">
                <i class="fas fa-user-circle"></i> My Profile
            </h1>
            <p class="page-subtitle-profile">
                View and manage your profile information
                <span class="branch-tag-profile">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user['branch_name'] ?? 'N/A') ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="dashboard.php" class="btn-back-profile">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- MESSAGE -->
    <?php if ($message): ?>
        <div class="message-box-profile <?= $message_type === 'success' ? 'success' : 'error' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- PROFILE GRID -->
    <div class="grid-profile">

        <!-- PROFILE CARD -->
        <div class="profile-card-profile">
            <div class="profile-avatar-profile">
                <?php if ($show_initial): ?>
                    <div class="avatar-large-profile"><?= $initial ?></div>
                <?php else: ?>
                    <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar-large-img-profile">
                <?php endif; ?>
                
                <form method="POST" action="" enctype="multipart/form-data" id="uploadFormProfile" style="position:relative;">
                    <input type="hidden" name="action" value="upload_profile_pic">
                    <label class="avatar-upload-btn-profile" title="Upload Profile Picture" for="profile_pic_input_profile">
                        <i class="fas fa-camera"></i>
                    </label>
                    <input type="file" id="profile_pic_input_profile" name="profile_pic" accept="image/*" style="display:none;">
                </form>
                
                <?php if (!$show_initial): ?>
                    <form method="POST" action="" id="removePicFormProfile" style="position:absolute;bottom:50px;right:4px;">
                        <input type="hidden" name="action" value="remove_profile_pic">
                        <button type="submit" class="avatar-remove-btn-profile" title="Remove Profile Picture" onclick="return confirm('Remove your profile picture?');">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            
            <div class="profile-name-profile">
                <h2><?= htmlspecialchars($user['full_name'] ?? 'Admin User') ?></h2>
                <p style="margin-top:4px;">
                    <span class="profile-badge-profile">
                        <i class="fas fa-user-tie"></i> <?= ucfirst($user['role'] ?? 'Admin') ?>
                    </span>
                </p>
                <p class="profile-username-profile">
                    <i class="fas fa-at"></i> @<?= htmlspecialchars($user['username'] ?? 'admin') ?>
                </p>
            </div>
            
            <div class="profile-stats-profile">
                <div class="stat-item-profile">
                    <span class="stat-number-profile"><?= date('Y', strtotime($user['created_at'] ?? 'now')) ?></span>
                    <span class="stat-label-profile">Member Since</span>
                </div>
                <div class="stat-item-profile">
                    <span class="stat-number-profile">1</span>
                    <span class="stat-label-profile">Branch</span>
                </div>
                <div class="stat-item-profile">
                    <span class="stat-number-profile"><?= date('M d', strtotime($user['created_at'] ?? 'now')) ?></span>
                    <span class="stat-label-profile">Joined Date</span>
                </div>
            </div>
        </div>

        <!-- PROFILE INFO -->
        <div class="card-profile">
            <div class="card-header-profile">
                <h3 class="card-title-profile">
                    <i class="fas fa-user-edit title-blue-profile" style="margin-right:8px;"></i> Profile Information
                </h3>
            </div>
            
            <form method="POST" action="" class="profile-form-profile" id="profileFormProfile">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="form-row-profile">
                    <div class="form-group-profile">
                        <label class="form-label-profile">
                            <i class="fas fa-user"></i> Full Name
                            <span class="required-profile">*</span>
                        </label>
                        <input type="text" name="full_name" class="form-control-profile" 
                               value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" required>
                    </div>
                    
                    <div class="form-group-profile">
                        <label class="form-label-profile">
                            <i class="fas fa-at"></i> Username
                            <span class="required-profile">*</span>
                        </label>
                        <input type="text" name="username" class="form-control-profile" 
                               value="<?= htmlspecialchars($user['username'] ?? '') ?>" required>
                        <span class="form-help-profile">Username must be unique</span>
                    </div>
                </div>
                
                <div class="form-row-profile">
                    <div class="form-group-profile">
                        <label class="form-label-profile">
                            <i class="fas fa-envelope"></i> Email
                            <span class="required-profile">*</span>
                        </label>
                        <input type="email" name="email" class="form-control-profile" 
                               value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                    </div>
                    
                    <div class="form-group-profile">
                        <label class="form-label-profile">
                            <i class="fas fa-phone"></i> Phone Number
                        </label>
                        <input type="tel" name="phone" class="form-control-profile" 
                               value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                    </div>
                </div>
                
                <div class="form-row-profile single">
                    <div class="form-group-profile">
                        <label class="form-label-profile">
                            <i class="fas fa-store-alt"></i> Branch
                        </label>
                        <input type="text" class="form-control-profile" 
                               value="<?= htmlspecialchars($user['branch_name'] ?? 'N/A') ?> - <?= htmlspecialchars($user['branch_location'] ?? '') ?>" disabled>
                        <span class="form-help-profile">Branch is assigned by system administrator</span>
                    </div>
                </div>
                
                <div class="form-actions-profile">
                    <button type="submit" class="btn-profile btn-primary-profile">
                        <i class="fas fa-save"></i> Update Profile
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- CHANGE PASSWORD -->
    <div class="card-profile" style="margin-top:20px;">
        <div class="card-header-profile">
            <h3 class="card-title-profile">
                <i class="fas fa-key title-orange-profile" style="margin-right:8px;"></i> Change Password
            </h3>
        </div>
        
        <form method="POST" action="" class="profile-form-profile" id="passwordFormProfile">
            <input type="hidden" name="action" value="update_password">
            
            <div class="form-row-profile">
                <div class="form-group-profile">
                    <label class="form-label-profile">
                        <i class="fas fa-lock"></i> Current Password
                        <span class="required-profile">*</span>
                    </label>
                    <input type="password" name="current_password" class="form-control-profile" 
                           placeholder="Enter current password" required>
                </div>
                
                <div class="form-group-profile">
                    <label class="form-label-profile">
                        <i class="fas fa-lock"></i> New Password
                        <span class="required-profile">*</span>
                    </label>
                    <input type="password" name="new_password" class="form-control-profile" 
                           placeholder="Enter new password (min 6 characters)" required>
                </div>
            </div>
            
            <div class="form-row-profile single">
                <div class="form-group-profile">
                    <label class="form-label-profile">
                        <i class="fas fa-check-circle"></i> Confirm New Password
                        <span class="required-profile">*</span>
                    </label>
                    <input type="password" name="confirm_password" class="form-control-profile" 
                           placeholder="Confirm new password" required>
                </div>
            </div>
            
            <div class="form-actions-profile">
                <button type="submit" class="btn-profile btn-orange-profile">
                    <i class="fas fa-key"></i> Change Password
                </button>
            </div>
        </form>
    </div>

    <!-- FOOTER -->
    <footer class="footer-profile">
        <p>
            <span class="footer-brand-profile">Braick Dispensary</span> Management System
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            Profile
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span style="color:#CBD5E1;margin:0 8px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- PAGE-SPECIFIC JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // FOOTER TIME ONLY
    // ================================================================
    setInterval(function() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }, 1000);

    // ================================================================
    // PROFILE PICTURE UPLOAD
    // ================================================================
    document.getElementById('profile_pic_input_profile')?.addEventListener('change', function(e) {
        var file = this.files[0];
        if (file) {
            if (file.size > 2 * 1024 * 1024) {
                alert('⚠️ File size must be less than 2MB!');
                this.value = '';
                return;
            }
            var validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!validTypes.includes(file.type)) {
                alert('⚠️ Only JPG, PNG, GIF, and WEBP images are allowed!');
                this.value = '';
                return;
            }
            document.getElementById('uploadFormProfile').submit();
        }
    });

    // ================================================================
    // FORM VALIDATION
    // ================================================================
    document.getElementById('profileFormProfile')?.addEventListener('submit', function(e) {
        var username = document.querySelector('input[name="username"]').value.trim();
        var email = document.querySelector('input[name="email"]').value.trim();
        
        if (username.length < 3) {
            e.preventDefault();
            alert('⚠️ Username must be at least 3 characters long!');
            return false;
        }
        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            e.preventDefault();
            alert('⚠️ Please enter a valid email address!');
            return false;
        }
        return true;
    });

    document.getElementById('passwordFormProfile')?.addEventListener('submit', function(e) {
        var currentPass = document.querySelector('input[name="current_password"]').value;
        var newPass = document.querySelector('input[name="new_password"]').value;
        var confirmPass = document.querySelector('input[name="confirm_password"]').value;
        
        if (!currentPass) { e.preventDefault(); alert('⚠️ Please enter your current password!'); return false; }
        if (newPass.length < 6) { e.preventDefault(); alert('⚠️ New password must be at least 6 characters!'); return false; }
        if (newPass !== confirmPass) { e.preventDefault(); alert('⚠️ Passwords do not match!'); return false; }
        return true;
    });

    console.log('%c👤 Braick - Profile', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Uses SHARED header & sidebar', 'font-size:13px; color:#059669;');
    console.log('%c✅ NO duplicate dark mode JavaScript', 'font-size:13px; color:#059669;');
    console.log('%c🌙 Dark mode: Handled by header', 'font-size:13px; color:#7C3AED;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user['full_name'] ?? 'Admin') ?>', 'font-size:13px; color:#059669;');
</script>

</body>
</html>