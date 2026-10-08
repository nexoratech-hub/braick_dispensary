<?php
// ================================================================
// FILE: frontend/pages/reception/profile.php
// RECEPTION - PROFILE (V2 - SHARED HEADER/SIDEBAR)
// ✅ Using shared reception_header.php & reception_sidebar.php
// ✅ WITH EDIT PROFILE BUTTON
// ✅ WITH REMOVE PROFILE PICTURE BUTTON + MODAL
// ✅ PROFILE PICTURE UPLOAD
// BRAICK DISPENSARY
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
// ACCESS CONTROL
// ================================================================
$allowed_roles = ['reception', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor':     header('Location: ../doctor/dashboard.php'); break;
        case 'pharmacy':   header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier':    header('Location: ../cashier/dashboard.php'); break;
        default:           header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// USER DATA FROM SESSION
// ================================================================
$user_id     = $_SESSION['user_id']     ?? 0;
$full_name   = $_SESSION['full_name']   ?? 'User';
$role        = $_SESSION['role']        ?? 'reception';
$branch_id   = $_SESSION['branch_id']   ?? 1;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username    = $_SESSION['username']    ?? '';
$email       = $_SESSION['email']       ?? '';
$phone       = $_SESSION['phone']       ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

require_once __DIR__ . '/../../../backend/config/database.php';

$message = '';
$message_type = '';

try {
    $db = Database::getInstance()->getConnection();

    // ================================================================
    // GET USER DATA
    // ================================================================
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $full_name   = $user['full_name']   ?? $full_name;
        $email       = $user['email']       ?? $email;
        $phone       = $user['phone']       ?? $phone;
        $profile_pic = $user['profile_pic'] ?? '';
        $username    = $user['username']    ?? $username;
        $role        = $user['role']        ?? $role;
        $branch_id   = $user['branch_id']   ?? $branch_id;

        $_SESSION['full_name']   = $full_name;
        $_SESSION['email']       = $email;
        $_SESSION['phone']       = $phone;
        $_SESSION['profile_pic'] = $profile_pic;
        $_SESSION['username']    = $username;
        $_SESSION['role']        = $role;

        if ($branch_id) {
            $stmt2 = $db->prepare("SELECT name FROM branches WHERE id = ?");
            $stmt2->execute([$branch_id]);
            $branch = $stmt2->fetch(PDO::FETCH_ASSOC);
            if ($branch) {
                $branch_name = $branch['name'];
                $_SESSION['branch_name'] = $branch_name;
            }
        }
    }

    // ================================================================
    // HANDLE PROFILE PICTURE UPLOAD
    // ================================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_pic'])) {
        $file       = $_FILES['profile_pic'];
        $upload_dir = __DIR__ . '/../../assets/uploads/profiles/';

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];
        $max_size      = 5 * 1024 * 1024;

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
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_filename   = 'reception_' . $user_id . '_' . time() . '.' . $file_extension;
            $file_path      = $upload_dir . $new_filename;

            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                if (!empty($profile_pic) && file_exists($upload_dir . $profile_pic)) {
                    @unlink($upload_dir . $profile_pic);
                }

                $stmt = $db->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
                if ($stmt->execute([$new_filename, $user_id])) {
                    $profile_pic = $new_filename;
                    $_SESSION['profile_pic'] = $new_filename;
                    $message = "Profile picture updated successfully!";
                    $message_type = 'success';

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
    // HANDLE REMOVE AVATAR
    // ================================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_avatar'])) {
        try {
            $stmt = $db->prepare("SELECT profile_pic FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $current     = $stmt->fetch(PDO::FETCH_ASSOC);
            $current_pic = $current['profile_pic'] ?? '';

            if (!empty($current_pic)) {
                $file_to_delete = __DIR__ . '/../../assets/uploads/profiles/' . $current_pic;
                if (file_exists($file_to_delete)) {
                    @unlink($file_to_delete);
                }
            }

            $stmt = $db->prepare("UPDATE users SET profile_pic = NULL WHERE id = ?");
            $stmt->execute([$user_id]);

            $_SESSION['profile_pic'] = '';
            $profile_pic = '';

            $message = "Profile picture removed successfully!";
            $message_type = 'success';

            try {
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (user_id, branch_id, action, details, created_at)
                    VALUES (?, ?, 'profile_pic_removed', ?, NOW())
                ");
                $stmt->execute([$user_id, $branch_id, "Reception removed profile picture"]);
            } catch (Exception $e) {}
        } catch (Exception $e) {
            $message = "Failed to remove profile picture: " . $e->getMessage();
            $message_type = 'error';
        }
    }

    // ================================================================
    // HANDLE PROFILE UPDATE
    // ================================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
        $full_name = trim($_POST['full_name'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');

        $errors = [];
        if (empty($full_name)) $errors[] = 'Full name is required';
        if (empty($email))     $errors[] = 'Email is required';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address';

        if (empty($errors)) {
            $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?");
            if ($stmt->execute([$full_name, $email, $phone, $user_id])) {
                $_SESSION['full_name'] = $full_name;
                $_SESSION['email']     = $email;
                $_SESSION['phone']     = $phone;
                $message = "Profile updated successfully!";
                $message_type = 'success';

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
    $file_path = __DIR__ . '/../../assets/uploads/profiles/' . $profile_pic;
    if (file_exists($file_path)) {
        $profile_pic_exists = true;
    }
}

$has_custom_pic = !empty($profile_pic) && $profile_pic_exists;

$default_avatar = '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';
$default_letter = strtoupper(substr($full_name, 0, 1));

// ================================================================
// UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    if (isset($_SESSION['user_id'])) {
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
        $unread_notifications = $stmt->fetch()['total'] ?? 0;
    }
} catch (Exception $e) {
    $unread_notifications = 0;
}

// ================================================================
// ROLE DISPLAY
// ================================================================
$role_display = ucfirst($role);
$role_icon    = '👤';
switch ($role) {
    case 'admin':      $role_icon = '👑'; break;
    case 'reception':  $role_icon = '📋'; break;
    case 'doctor':     $role_icon = '👨‍⚕️'; break;
    case 'pharmacy':   $role_icon = '💊'; break;
    case 'laboratory': $role_icon = '🔬'; break;
    case 'cashier':    $role_icon = '💰'; break;
}

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// NOTE: time_ago() is provided by reception_header.php
// DO NOT redeclare here.
// ================================================================

include_once '../../components/reception_header.php';
include_once '../../components/reception_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Braick Dispensary</title>

    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?>" type="image/png">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        /* ================================================================
           PROFILE PAGE - SPECIFIC STYLES ONLY
           (Base styles, variables, .card, .footer, .toast-custom,
            .main-content are provided by reception_header.php)
           ================================================================ */

        /* ---------- PAGE HEADER ---------- */
        .page-header {
            background: linear-gradient(135deg, #0B5ED7 0%, #0A4CA8 50%, #083B8A 100%);
            border-radius: 20px;
            padding: 24px 32px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 10px 40px rgba(11, 94, 215, 0.3);
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -60%;
            right: -10%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .page-header::after {
            content: '';
            position: absolute;
            bottom: -80%;
            left: -5%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .page-header .page-title {
            color: white;
            font-size: 1.75rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
            letter-spacing: -0.5px;
        }

        .page-header .page-title i { font-size: 1.9rem; opacity: 0.95; }

        .page-header .page-subtitle {
            color: rgba(255,255,255,0.9);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
            margin-top: 6px;
        }

        .page-header .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,0.15);
        }

        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.25);
            padding: 9px 18px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(8px);
            position: relative;
            z-index: 1;
            cursor: pointer;
        }

        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.28);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
            color: white;
        }

        .update-badge-light {
            background: rgba(255,255,255,0.15);
            color: rgba(255,255,255,0.9);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            backdrop-filter: blur(8px);
            font-weight: 600;
        }

        /* ---------- PROFILE CARD ---------- */
        .profile-card {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 32px 36px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            max-width: 900px;
            margin: 0 auto;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
        }

        .profile-card:hover {
            border-color: var(--primary);
            box-shadow: 0 10px 40px rgba(11, 94, 215, 0.1);
        }

        /* ---------- AVATAR WRAPPER ---------- */
        .profile-avatar-wrapper {
            position: relative;
            display: inline-block;
        }

        .profile-avatar {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--primary);
            box-shadow: 0 8px 30px rgba(11, 94, 215, 0.25);
            transition: all 0.3s ease;
            background: var(--primary-bg);
        }

        .profile-avatar:hover {
            transform: scale(1.03);
            box-shadow: 0 12px 40px rgba(11, 94, 215, 0.4);
        }

        .profile-avatar-wrapper .upload-overlay {
            position: absolute;
            bottom: 0;
            right: 0;
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 3px solid var(--bg-card);
            box-shadow: 0 4px 15px rgba(11, 94, 215, 0.4);
        }

        .profile-avatar-wrapper .upload-overlay:hover {
            transform: scale(1.15) rotate(-5deg);
            box-shadow: 0 6px 20px rgba(11, 94, 215, 0.6);
        }

        .profile-avatar-wrapper .upload-overlay input[type="file"] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .profile-avatar-wrapper .remove-overlay {
            position: absolute;
            top: 0;
            right: 0;
            background: linear-gradient(135deg, #DC2626, #B91C1C);
            color: white;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 3px solid var(--bg-card);
            box-shadow: 0 4px 15px rgba(220, 38, 38, 0.4);
            font-size: 0.75rem;
        }

        .profile-avatar-wrapper .remove-overlay:hover {
            transform: scale(1.15) rotate(90deg);
            box-shadow: 0 6px 20px rgba(220, 38, 38, 0.6);
        }

        .profile-name {
            font-size: 1.7rem;
            font-weight: 800;
            color: var(--text-primary);
            letter-spacing: -0.5px;
        }

        .profile-role {
            font-size: 0.9rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 6px;
        }

        .profile-role .badge-role {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }

        /* ---------- FORM CONTROLS ---------- */
        .form-label {
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 6px;
            display: block;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .form-label .label-icon {
            margin-right: 6px;
            color: var(--primary);
        }

        .form-control {
            width: 100%;
            padding: 11px 14px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 0.88rem;
            transition: all 0.3s ease;
            outline: none;
            background: var(--bg-card);
            color: var(--text-primary);
            font-family: inherit;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.1);
        }

        .form-control:disabled {
            background: var(--bg-body);
            color: var(--text-secondary);
            cursor: not-allowed;
            opacity: 0.8;
        }

        /* ---------- BUTTONS ---------- */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 11px 24px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.82rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            border: none;
            text-decoration: none;
            font-family: inherit;
            min-height: 42px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            color: white;
            box-shadow: 0 4px 15px rgba(11, 94, 215, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(11, 94, 215, 0.4);
            color: white;
        }

        .btn-outline {
            background: transparent;
            color: var(--text-primary);
            border: 2px solid var(--border-color);
        }

        .btn-outline:hover {
            background: var(--bg-body);
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-2px);
        }

        .btn-danger {
            background: linear-gradient(135deg, #DC2626, #B91C1C);
            color: white;
            box-shadow: 0 4px 15px rgba(220, 38, 38, 0.3);
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(220, 38, 38, 0.4);
            color: white;
        }

        .btn-sm {
            padding: 8px 16px;
            font-size: 0.75rem;
            min-height: 36px;
        }

        /* ---------- INFO ROWS ---------- */
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .info-row:last-child { border-bottom: none; }

        .info-label {
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
        }

        .info-label i { color: var(--primary); }

        .info-value {
            color: var(--text-primary);
            font-weight: 700;
            font-size: 0.85rem;
        }

        .section-divider {
            border: none;
            border-top: 2px solid var(--border-color);
            margin: 26px 0;
        }

        /* ---------- STATUS BADGE ---------- */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #D1FAE5;
            color: #059669;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border: 1px solid rgba(5, 150, 105, 0.3);
        }

        [data-theme="dark"] .status-badge { background: #1A3A2A; color: #34D399; border-color: rgba(52, 211, 153, 0.3); }

        /* ---------- ALERT MESSAGE ---------- */
        .alert-message {
            max-width: 900px;
            margin: 0 auto 20px;
            padding: 14px 20px;
            border-radius: 14px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-size: 0.85rem;
            font-weight: 600;
            animation: slideDown 0.4s ease;
        }

        .alert-message.success { background: #D1FAE5; color: #059669; border: 1px solid #34D399; }
        .alert-message.error   { background: #FEE2E2; color: #DC2626; border: 1px solid #F87171; }
        [data-theme="dark"] .alert-message.success { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .alert-message.error   { background: #3A1A1A; color: #F87171; }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ---------- MODAL ---------- */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(6px);
            animation: fadeIn 0.3s ease;
            padding: 20px;
        }

        .modal-overlay.show { display: flex; }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        @keyframes slideUp {
            from { transform: translateY(30px); opacity: 0; }
            to   { transform: translateY(0); opacity: 1; }
        }

        .modal-box {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 32px 36px;
            max-width: 440px;
            width: 100%;
            text-align: center;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.4);
            animation: slideUp 0.3s ease;
            border: 1px solid var(--border-color);
        }

        .modal-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #FEE2E2, #FECACA);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2.2rem;
            color: #DC2626;
            border: 4px solid #FCA5A5;
            animation: pulse-icon 2s infinite;
        }

        @keyframes pulse-icon {
            0%, 100% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.4); }
            50%      { box-shadow: 0 0 0 12px rgba(220, 38, 38, 0); }
        }

        [data-theme="dark"] .modal-icon {
            background: linear-gradient(135deg, #3A1A1A, #4A1A1A);
            border-color: #7F1D1D;
        }

        .modal-title {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 10px;
            letter-spacing: -0.3px;
        }

        .modal-message {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 26px;
            line-height: 1.6;
        }

        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
        }

        .modal-actions .btn { flex: 1; justify-content: center; }

        /* ---------- RESPONSIVE ---------- */
        @media (max-width: 768px) {
            .page-header { padding: 18px 20px; flex-direction: column; align-items: flex-start; }
            .page-header .page-title { font-size: 1.3rem; }
            .profile-card { padding: 22px 20px; border-radius: 16px; }
            .profile-avatar { width: 100px; height: 100px; }
            .profile-name { font-size: 1.3rem; }
            .profile-avatar-wrapper .upload-overlay { width: 34px; height: 34px; font-size: 0.85rem; }
            .profile-avatar-wrapper .remove-overlay { width: 30px; height: 30px; font-size: 0.7rem; }
            .info-row { flex-direction: column; gap: 4px; }
            .btn { padding: 9px 18px; font-size: 0.78rem; }
            .modal-box { padding: 24px 20px; }
            .modal-actions { flex-direction: column; }
        }

        @media (max-width: 640px) {
            .profile-card { padding: 16px 14px; }
            .page-header .page-title { font-size: 1.15rem; }
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .animate-fade-in-up { animation: fadeInUp 0.5s ease forwards; opacity: 0; }

        .capitalize { text-transform: capitalize; }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- SHARED HEADER & SIDEBAR (INCLUDED ABOVE) -->
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
                <span class="role-badge-display"><?= strtoupper($role) ?></span>
                <span class="update-badge-light">
                    <span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#34D399;animation:pulse-dot 1.5s infinite;margin-right:4px;"></span> Live
                </span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-info-circle"></i>
                View and manage your profile information
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="edit_profile.php" class="btn-outline-light" style="background:rgba(255,255,255,0.25);">
                <i class="fas fa-user-edit"></i> Edit Profile
            </a>
            <a href="dashboard.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- MESSAGE -->
    <!-- ================================================================ -->
    <?php if ($message): ?>
        <div class="alert-message <?= $message_type === 'success' ? 'success' : 'error' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>" style="font-size:1.1rem;margin-top:1px;"></i>
            <div style="flex:1;"><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- PROFILE CARD -->
    <!-- ================================================================ -->
    <div class="profile-card animate-fade-in-up">

        <!-- Avatar + Name -->
        <div class="flex flex-col md:flex-row items-center gap-6 mb-6">
            <div class="profile-avatar-wrapper">
                <?php if ($has_custom_pic): ?>
                    <img src="<?= $profile_pic_url ?>" alt="Profile Picture" class="profile-avatar" id="profilePreview">
                <?php else: ?>
                    <img src="<?= $default_avatar ?>" alt="Default Avatar" class="profile-avatar" id="profilePreview"
                         onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22130%22 height=%22130%22%3E%3Crect width=%22130%22 height=%22130%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2265%22 y=%2285%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2250%22 font-weight=%22bold%22%3E<?= $default_letter ?>%3C/text%3E%3C/svg%3E'">
                <?php endif; ?>

                <!-- Upload Form -->
                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <div class="upload-overlay" title="Upload Profile Picture">
                        <i class="fas fa-camera"></i>
                        <input type="file" name="profile_pic" accept="image/*" id="profilePicInput">
                    </div>
                </form>

                <!-- Remove Overlay -->
                <?php if ($has_custom_pic): ?>
                    <div class="remove-overlay" title="Remove Profile Picture" onclick="openRemoveModal()">
                        <i class="fas fa-times"></i>
                    </div>
                <?php endif; ?>
            </div>

            <div class="text-center md:text-left">
                <h2 class="profile-name"><?= htmlspecialchars($full_name) ?></h2>
                <div class="profile-role">
                    <span class="badge-role"><?= $role_icon ?> <?= ucfirst($role) ?></span>
                    <span><i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($branch_name) ?></span>
                    <span><i class="fas fa-user mr-1"></i> <?= htmlspecialchars($username) ?></span>
                </div>
                <p style="font-size:0.78rem;color:var(--text-secondary);margin-top:6px;">
                    <i class="fas fa-calendar-alt mr-1"></i>
                    Member since <?= date('F d, Y', strtotime($user['created_at'] ?? 'now')) ?>
                </p>

                <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;justify-content:center;" class="md:justify-start">
                    <a href="edit_profile.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-user-edit"></i> Edit Profile
                    </a>
                    <?php if ($has_custom_pic): ?>
                        <button type="button" class="btn btn-danger btn-sm" onclick="openRemoveModal()">
                            <i class="fas fa-trash-alt"></i> Remove Picture
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <hr class="section-divider">

        <!-- Profile Form -->
        <form method="POST" action="">
            <input type="hidden" name="update_profile" value="1">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <div>
                    <label class="form-label">
                        <i class="fas fa-user label-icon"></i> Full Name <span style="color:#DC2626;">*</span>
                    </label>
                    <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($full_name) ?>" required>
                </div>

                <div>
                    <label class="form-label">
                        <i class="fas fa-user-tag label-icon"></i> Username
                    </label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($username) ?>" disabled>
                </div>

                <div>
                    <label class="form-label">
                        <i class="fas fa-envelope label-icon"></i> Email Address <span style="color:#DC2626;">*</span>
                    </label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($email) ?>" required>
                </div>

                <div>
                    <label class="form-label">
                        <i class="fas fa-phone label-icon"></i> Phone Number
                    </label>
                    <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($phone) ?>">
                </div>

                <div>
                    <label class="form-label">
                        <i class="fas fa-shield-alt label-icon"></i> Role
                    </label>
                    <input type="text" class="form-control" value="<?= ucfirst($role) ?>" disabled>
                </div>

                <div>
                    <label class="form-label">
                        <i class="fas fa-store-alt label-icon"></i> Branch
                    </label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($branch_name) ?>" disabled>
                </div>

            </div>

            <div style="display:flex;flex-wrap:wrap;gap:12px;margin-top:22px;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Profile
                </button>
                <button type="reset" class="btn btn-outline">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </div>
        </form>

        <hr class="section-divider">

        <!-- Account Info -->
        <h3 style="font-size:1.05rem;font-weight:800;margin-bottom:14px;color:var(--text-primary);display:flex;align-items:center;gap:8px;">
            <i class="fas fa-info-circle" style="color:var(--primary);"></i> Account Information
        </h3>

        <div class="info-row">
            <span class="info-label"><i class="fas fa-id-badge mr-2"></i> User ID</span>
            <span class="info-value">#<?= $user_id ?></span>
        </div>
        <div class="info-row">
            <span class="info-label"><i class="fas fa-user mr-2"></i> Username</span>
            <span class="info-value"><?= htmlspecialchars($username) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label"><i class="fas fa-shield-alt mr-2"></i> Role</span>
            <span class="info-value capitalize"><?= ucfirst($role) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label"><i class="fas fa-store-alt mr-2"></i> Branch</span>
            <span class="info-value"><?= htmlspecialchars($branch_name) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label"><i class="fas fa-circle mr-2"></i> Status</span>
            <span class="info-value">
                <span class="status-badge">
                    <i class="fas fa-check-circle mr-1"></i> Active
                </span>
            </span>
        </div>

    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 dark:text-gray-700 mx-2">|</span>
            My Profile
            <span class="text-gray-300 dark:text-gray-700 mx-2">|</span>
            <span id="footerTimestamp"><?= date('h:i:s A') ?></span>
            <span class="text-gray-300 dark:text-gray-700 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- REMOVE PICTURE MODAL -->
<!-- ================================================================ -->
<div class="modal-overlay" id="removeModal">
    <div class="modal-box">
        <div class="modal-icon">
            <i class="fas fa-trash-alt"></i>
        </div>
        <h3 class="modal-title">Remove Profile Picture?</h3>
        <p class="modal-message">
            Are you sure you want to remove your profile picture?<br>
            This action cannot be undone.
        </p>
        <div class="modal-actions">
            <button type="button" class="btn btn-outline" onclick="closeRemoveModal()">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="button" class="btn btn-danger" onclick="confirmRemove()">
                <i class="fas fa-trash-alt"></i> Yes, Remove
            </button>
        </div>
    </div>
</div>

<!-- ================================================================ -->
<!-- HIDDEN FORM FOR DELETE -->
<!-- ================================================================ -->
<form method="POST" action="" id="deleteAvatarForm" style="display:none;">
    <input type="hidden" name="remove_avatar" value="1">
</form>

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

<script>
    // ================================================================
    // CLOCK
    // ================================================================
    function updateClock() {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var ft = document.getElementById('footerTimestamp');
        if (ft) ft.textContent = timeStr;
    }
    setInterval(updateClock, 1000);
    updateClock();

    // ================================================================
    // PROFILE PICTURE UPLOAD - AUTO SUBMIT
    // ================================================================
    document.getElementById('profilePicInput')?.addEventListener('change', function() {
        var file = this.files[0];
        if (file) {
            if (file.size > 5 * 1024 * 1024) {
                showToast('Error', 'File size must be less than 5MB', 'error');
                this.value = '';
                return;
            }

            var validTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];
            if (!validTypes.includes(file.type)) {
                showToast('Error', 'Only JPG, PNG, GIF, and WEBP images are allowed', 'error');
                this.value = '';
                return;
            }

            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('profilePreview').src = e.target.result;
            };
            reader.readAsDataURL(file);

            document.getElementById('uploadForm').submit();
        }
    });

    // ================================================================
    // REMOVE PICTURE MODAL
    // ================================================================
    function openRemoveModal() {
        var modal = document.getElementById('removeModal');
        if (modal) {
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
    }

    function closeRemoveModal() {
        var modal = document.getElementById('removeModal');
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }
    }

    function confirmRemove() {
        var form = document.getElementById('deleteAvatarForm');
        if (form) {
            var removeBtn = document.querySelector('#removeModal .btn-danger');
            if (removeBtn) {
                removeBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Removing...';
                removeBtn.disabled = true;
            }
            setTimeout(function() {
                form.submit();
            }, 300);
        }
    }

    // Close modal when clicking outside
    var removeModal = document.getElementById('removeModal');
    if (removeModal) {
        removeModal.addEventListener('click', function(e) {
            if (e.target === removeModal) closeRemoveModal();
        });
    }

    // Close modal with ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeRemoveModal();
    });

    // ================================================================
    // TOAST (fallback if not defined by header)
    // ================================================================
    if (typeof window.showToast !== 'function') {
        window.showToast = function(title, message, type) {
            var toast = document.getElementById('toast');
            if (!toast) return;
            var toastTitle = document.getElementById('toastTitle');
            var toastMessage = document.getElementById('toastMessage');
            toast.className = 'toast-custom ' + (type || 'info');
            toastTitle.textContent = title;
            toastMessage.textContent = message;
            toast.style.display = 'flex';
            toast.classList.add('show');
            clearTimeout(toast.timeout);
            toast.timeout = setTimeout(function() {
                toast.classList.remove('show');
                setTimeout(function() { toast.style.display = 'none'; }, 400);
            }, 3500);
        };
    }

    console.log('%c👤 Braick - Reception Profile (V2)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 User: <?= htmlspecialchars($full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📸 Profile pic: <?= $has_custom_pic ? 'Uploaded ✅' : 'Default' ?>', 'font-size:13px; color:#64748B;');
    console.log('%c✅ Using shared reception_header.php & reception_sidebar.php', 'font-size:13px; color:#34D399; font-weight:bold;');
</script>

</body>
</html>