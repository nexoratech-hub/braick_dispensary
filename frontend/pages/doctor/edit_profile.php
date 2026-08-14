<?php
// ================================================================
// FILE: frontend/pages/doctor/edit_profile.php
// DOCTOR - EDIT PROFILE
// BRAICK DISPENSARY
// FIXED: Image preview works correctly
// FIXED: Header with date and time
// ================================================================

// Start session
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
// CHECK IF USER IS DOCTOR OR ADMIN
// ================================================================
if ($_SESSION['role'] !== 'doctor' && $_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET USER INFO FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Dr. John Mushi';
$user_role = $_SESSION['role'] ?? 'doctor';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_username = $_SESSION['username'] ?? 'dr.john';
$user_email = $_SESSION['email'] ?? 'john@braick.com';
$user_phone = $_SESSION['phone'] ?? '+255 700 000 011';
$user_specialty = $_SESSION['specialty'] ?? 'General Medicine';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$is_admin = ($_SESSION['role'] === 'admin');

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die('Database connection error: ' . $e->getMessage());
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
    
    // ================================================================
    // UPDATE PROFILE
    // ================================================================
    if ($action === 'update_profile') {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $specialty = trim($_POST['specialty'] ?? '');
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
                $user_data = $stmt->fetch();
                
                if ($user_data) {
                    if (str_starts_with($user_data['password'], '$2y$')) {
                        $password_valid = password_verify($current_password, $user_data['password']);
                    } else {
                        $password_valid = ($current_password === $user_data['password']);
                    }
                    
                    if ($password_valid) {
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    } else {
                        $errors[] = 'Current password is incorrect';
                    }
                } else {
                    $errors[] = 'User not found';
                }
            }
        }
        
        if (empty($errors)) {
            if ($is_admin) {
                if (isset($hashed_password)) {
                    $stmt = $db->prepare("
                        UPDATE users 
                        SET full_name = ?, email = ?, phone = ?, specialty = ?, password = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$full_name, $email, $phone, $specialty, $hashed_password, $user_id]);
                } else {
                    $stmt = $db->prepare("
                        UPDATE users 
                        SET full_name = ?, email = ?, phone = ?, specialty = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$full_name, $email, $phone, $specialty, $user_id]);
                }
            } else {
                if (isset($hashed_password)) {
                    $stmt = $db->prepare("
                        UPDATE users 
                        SET full_name = ?, email = ?, phone = ?, specialty = ?, password = ?
                        WHERE id = ? AND role = 'doctor'
                    ");
                    $stmt->execute([$full_name, $email, $phone, $specialty, $hashed_password, $user_id]);
                } else {
                    $stmt = $db->prepare("
                        UPDATE users 
                        SET full_name = ?, email = ?, phone = ?, specialty = ?
                        WHERE id = ? AND role = 'doctor'
                    ");
                    $stmt->execute([$full_name, $email, $phone, $specialty, $user_id]);
                }
            }
            
            // Update session
            $_SESSION['full_name'] = $full_name;
            $_SESSION['email'] = $email;
            $_SESSION['phone'] = $phone;
            $_SESSION['specialty'] = $specialty;
            
            // Log activity
            try {
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) 
                    VALUES (?, ?, 'profile_updated', ?, NOW())
                ");
                $stmt->execute([
                    $user_id,
                    $user_branch_id,
                    "Profile updated: " . $full_name . ($is_admin ? " (Admin)" : "")
                ]);
            } catch (Exception $e) {}
            
            $message = "Profile updated successfully!";
            $message_type = 'success';
            $success = true;
            
            // Refresh variables
            $user_full_name = $full_name;
            $user_email = $email;
            $user_phone = $phone;
            $user_specialty = $specialty;
            
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
            
            if (!in_array($file_ext, $allowed_exts)) {
                $message = "Only JPG, PNG, GIF, and WEBP files are allowed!";
                $message_type = 'error';
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $message = "File size exceeds 5MB limit!";
                $message_type = 'error';
            } else {
                $filename = 'user_' . $user_id . '_' . time() . '.' . $file_ext;
                $filepath = $upload_dir . $filename;
                
                // Delete old profile picture if exists
                if (!empty($profile_pic)) {
                    $old_file = $upload_dir . $profile_pic;
                    if (file_exists($old_file)) {
                        @unlink($old_file);
                    }
                }
                
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    $stmt = $db->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
                    $stmt->execute([$filename, $user_id]);
                    
                    $_SESSION['profile_pic'] = $filename;
                    $profile_pic = $filename;
                    
                    try {
                        $stmt = $db->prepare("
                            INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) 
                            VALUES (?, ?, 'profile_picture_updated', ?, NOW())
                        ");
                        $stmt->execute([
                            $user_id,
                            $user_branch_id,
                            "Profile picture updated for: " . $user_full_name
                        ]);
                    } catch (Exception $e) {}
                    
                    $message = "Profile picture updated successfully!";
                    $message_type = 'success';
                    
                    echo '<script>setTimeout(function(){ window.location.href = "edit_profile.php?success=1"; }, 1500);</script>';
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
// GET UNREAD NOTIFICATIONS
// ================================================================
$unread_notifications = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}

// ================================================================
// LOGO PATH
// ================================================================
$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/doctor_header.php';
include_once __DIR__ . '/../../components/doctor_sidebar.php';
?>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div class="page-header-left">
            <h1 class="page-title">
                <i class="fas fa-user-edit"></i> Edit Profile
                <?php if ($is_admin): ?>
                    <span class="page-badge admin-badge">👑 Admin Mode</span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                Update your profile information
                <span class="branch-tag">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user_branch_name) ?>
                </span>
                <span class="update-badge" id="lastUpdateBadge">
                    <i class="fas fa-sync-alt fa-spin"></i> Loading...
                </span>
            </p>
        </div>
        <div class="page-header-right">
            <a href="profile.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Profile
            </a>
            <button onclick="window.location.href='edit_profile.php'" class="btn btn-outline">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- EDIT PROFILE FORM -->
    <!-- ================================================================ -->
    <div class="row-2col">
        
        <!-- Column 1: Profile Picture -->
        <div class="consultation-card">
            <h3 class="card-title">
                <i class="fas fa-camera title-blue"></i> Profile Picture
            </h3>
            
            <form method="POST" action="" enctype="multipart/form-data" id="avatarForm">
                <input type="hidden" name="action" value="update_avatar">
                
                <div class="avatar-upload">
                    <!-- Avatar Display -->
                    <div class="avatar-display" id="avatarDisplay">
                        <?php if (!empty($profile_pic) && file_exists($upload_dir . $profile_pic)): ?>
                            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar-preview" id="avatarPreview">
                        <?php else: ?>
                            <div class="avatar-placeholder" id="avatarPlaceholder">
                                <?= strtoupper(substr($user_full_name, 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="avatar-upload-info">
                        <p class="avatar-label">Change Profile Picture</p>
                        <p class="avatar-desc">Upload a new profile picture</p>
                        
                        <div class="file-input-wrapper">
                            <input type="file" name="profile_pic" accept="image/*" id="profilePicInput" class="file-input">
                            <button type="submit" class="btn btn-success btn-sm" id="uploadBtn">
                                <i class="fas fa-upload"></i> Upload
                            </button>
                        </div>
                        <p class="avatar-help">Allowed: JPG, PNG, GIF, WEBP (Max 5MB)</p>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Column 2: Profile Information -->
        <div class="consultation-card">
            <h3 class="card-title">
                <i class="fas fa-user-circle title-blue"></i> Personal Information
            </h3>
            
            <form method="POST" action="" id="profileForm">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="form-row">
                    <label class="form-label">Full Name <span class="required">*</span></label>
                    <input type="text" name="full_name" class="form-control" 
                           value="<?= htmlspecialchars($user_full_name) ?>" required>
                </div>
                
                <div class="form-row">
                    <label class="form-label">Username</label>
                    <input type="text" class="form-control" 
                           value="<?= htmlspecialchars($user_username) ?>" disabled>
                    <p class="help-text">Username cannot be changed</p>
                </div>
                
                <div class="form-row">
                    <label class="form-label">Email <span class="required">*</span></label>
                    <input type="email" name="email" class="form-control" 
                           value="<?= htmlspecialchars($user_email) ?>" required>
                </div>
                
                <div class="form-row">
                    <label class="form-label">Phone Number</label>
                    <input type="tel" name="phone" class="form-control" 
                           value="<?= htmlspecialchars($user_phone) ?>">
                </div>
                
                <div class="form-row">
                    <label class="form-label">Specialty <span class="required">*</span></label>
                    <input type="text" name="specialty" class="form-control" 
                           value="<?= htmlspecialchars($user_specialty) ?>" required>
                    <p class="help-text">e.g. Cardiology, Pediatrics, General Medicine</p>
                </div>
                
                <div class="form-row">
                    <label class="form-label">Branch</label>
                    <input type="text" class="form-control" 
                           value="<?= htmlspecialchars($user_branch_name) ?>" disabled>
                </div>
                
                <!-- ================================================================ -->
                <!-- PASSWORD CHANGE -->
                <!-- ================================================================ -->
                <div class="password-section">
                    <h4 class="password-title">
                        <i class="fas fa-key"></i> Change Password
                    </h4>
                    <p class="password-help">Leave blank if you don't want to change your password.</p>
                    
                    <div class="form-row">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control" 
                               placeholder="Enter current password">
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" 
                               placeholder="Enter new password (min 6 chars)">
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" 
                               placeholder="Confirm new password">
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

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="separator">|</span>
            Edit Profile
            <span class="separator">|</span>
            Logged in as: <strong><?= htmlspecialchars($user_full_name) ?></strong>
            <span class="separator">|</span>
            <span id="footerTimestamp">Last updated: <?= date('h:i:s A') ?></span>
            <span class="separator">|</span>
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
<!-- STYLES -->
<!-- ================================================================ -->
<style>
    .main-content {
        margin-left: 270px;
        margin-top: 68px;
        padding: 24px 28px;
        min-height: calc(100vh - 68px);
        background: var(--bg-body);
        color: var(--text-primary);
        transition: all 0.3s ease;
    }
    
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 16px;
        margin-bottom: 24px;
        padding: 20px 24px;
        background: var(--primary-gradient);
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.25);
        position: relative;
        overflow: hidden;
        color: white;
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
    
    .page-header-left { flex: 1; position: relative; z-index: 1; }
    .page-header-right { position: relative; z-index: 1; display: flex; gap: 8px; flex-wrap: wrap; }
    
    .page-title {
        font-size: 1.6rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        color: white;
    }
    .page-title i { color: rgba(255,255,255,0.9); }
    
    .page-badge {
        font-size: 0.65rem;
        font-weight: 600;
        padding: 2px 14px;
        border-radius: 20px;
        background: rgba(255,255,255,0.2);
        color: white;
        backdrop-filter: blur(4px);
        border: 1px solid rgba(255,255,255,0.15);
    }
    
    .admin-badge {
        background: rgba(220, 38, 38, 0.3);
        border-color: rgba(220, 38, 38, 0.3);
        color: #FCA5A5;
    }
    
    .page-subtitle {
        font-size: 0.9rem;
        opacity: 0.85;
        margin-top: 6px;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        color: rgba(255,255,255,0.9);
    }
    
    .page-subtitle strong { color: white; font-weight: 700; }
    
    .branch-tag {
        background: rgba(255,255,255,0.15);
        color: white;
        padding: 3px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        border: 1px solid rgba(255,255,255,0.1);
        backdrop-filter: blur(4px);
    }
    
    .update-badge {
        background: rgba(255,255,255,0.12);
        color: rgba(255,255,255,0.8);
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.6rem;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        backdrop-filter: blur(4px);
    }
    
    .separator { color: rgba(255,255,255,0.3); margin: 0 4px; }
    
    .btn-outline {
        background: rgba(255,255,255,0.15);
        color: white;
        border: 1px solid rgba(255,255,255,0.25);
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
    
    .btn-outline:hover {
        background: rgba(255,255,255,0.25);
        border-color: rgba(255,255,255,0.4);
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }
    
    .row-2col {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
        margin-bottom: 24px;
    }
    
    .consultation-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 24px 28px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        box-shadow: var(--shadow-sm);
    }
    
    .consultation-card:hover {
        border-color: var(--primary);
        box-shadow: var(--shadow-md);
    }
    
    .card-title {
        font-size: 1rem;
        font-weight: 700;
        color: var(--text-primary);
        border-bottom: 2px solid var(--border-color);
        padding-bottom: 14px;
        margin-bottom: 18px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .title-blue { color: var(--primary); }
    
    .avatar-upload {
        display: flex;
        align-items: center;
        gap: 24px;
        flex-wrap: wrap;
        padding: 16px;
        background: var(--bg-body);
        border-radius: 12px;
        border: 2px dashed var(--border-color);
    }
    
    .avatar-display {
        flex-shrink: 0;
    }
    
    .avatar-preview {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid var(--primary);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.2);
    }
    
    .avatar-placeholder {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
        font-weight: 700;
        color: white;
        background: var(--primary-gradient);
        border: 4px solid var(--primary);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.2);
        flex-shrink: 0;
    }
    
    .avatar-upload-info {
        flex: 1;
    }
    
    .avatar-label {
        font-weight: 600;
        color: var(--text-primary);
        margin: 0 0 2px 0;
        font-size: 0.95rem;
    }
    
    .avatar-desc {
        font-size: 0.75rem;
        color: var(--text-secondary);
        margin: 0 0 8px 0;
    }
    
    .file-input-wrapper {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        align-items: center;
    }
    
    .file-input {
        padding: 6px 10px;
        border: 2px solid var(--border-color);
        border-radius: 8px;
        font-size: 0.8rem;
        background: var(--bg-card);
        color: var(--text-primary);
        cursor: pointer;
        flex: 1;
        min-width: 150px;
    }
    
    .file-input::-webkit-file-upload-button {
        padding: 4px 12px;
        border: none;
        border-radius: 4px;
        background: var(--primary);
        color: white;
        cursor: pointer;
        font-weight: 600;
        font-size: 0.75rem;
    }
    
    .file-input::-webkit-file-upload-button:hover {
        background: var(--primary-dark);
    }
    
    .avatar-help {
        font-size: 0.65rem;
        color: var(--text-secondary);
        margin-top: 4px;
    }
    
    .form-row {
        margin-bottom: 14px;
    }
    
    .form-row:last-child { margin-bottom: 0; }
    
    .form-label {
        display: block;
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--text-secondary);
        margin-bottom: 4px;
    }
    
    .required {
        color: var(--danger);
        margin-left: 2px;
    }
    
    .help-text {
        font-size: 0.7rem;
        color: var(--text-secondary);
        margin-top: 4px;
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
        font-family: inherit;
    }
    
    .form-control:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
    }
    
    .form-control::placeholder {
        color: var(--text-secondary);
        opacity: 0.5;
    }
    
    .form-control:disabled {
        opacity: 0.7;
        cursor: not-allowed;
        background: var(--gray-100);
    }
    
    [data-theme="dark"] .form-control:disabled {
        background: var(--gray-700);
    }
    
    .password-section {
        margin-top: 16px;
        padding-top: 16px;
        border-top: 2px solid var(--border-color);
    }
    
    .password-title {
        font-weight: 600;
        color: var(--text-primary);
        margin: 0 0 2px 0;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.95rem;
    }
    
    .password-title i { color: var(--primary); }
    
    .password-help {
        font-size: 0.8rem;
        color: var(--text-secondary);
        margin: 0 0 12px 0;
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
        background: var(--primary-gradient);
        color: white;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(11, 94, 215, 0.4);
    }
    
    .btn-success {
        background: linear-gradient(135deg, #059669, #10B981);
        color: white;
        box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
    }
    
    .btn-success:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(5, 150, 105, 0.4);
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
    
    .btn-sm {
        padding: 6px 16px;
        font-size: 0.75rem;
    }
    
    .alert {
        padding: 12px 18px;
        border-radius: 12px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 0.9rem;
        border: 1px solid transparent;
    }
    
    .alert-success {
        background: #D1FAE5;
        color: #059669;
        border-color: #059669;
    }
    
    .alert-error {
        background: #FEE2E2;
        color: #DC2626;
        border-color: #DC2626;
    }
    
    [data-theme="dark"] .alert-success {
        background: #1A3A2A;
        color: #34D399;
        border-color: #34D399;
    }
    
    [data-theme="dark"] .alert-error {
        background: #3A1A1A;
        color: #F87171;
        border-color: #F87171;
    }
    
    .footer {
        padding: 14px 0;
        border-top: 2px solid var(--border-color);
        margin-top: 20px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--text-secondary);
    }
    
    .footer .footer-brand { color: var(--primary); font-weight: 600; }
    
    .toast-custom {
        position: fixed;
        bottom: 24px;
        right: 24px;
        padding: 12px 18px;
        border-radius: 12px;
        z-index: 999;
        max-width: 360px;
        transform: translateY(100px);
        opacity: 0;
        transition: all 0.4s ease;
        display: flex;
        align-items: center;
        gap: 10px;
        color: white;
        box-shadow: 0 8px 30px rgba(0,0,0,0.2);
    }
    
    .toast-custom.show { transform: translateY(0); opacity: 1; }
    .toast-custom.success { background: #059669; }
    .toast-custom.error { background: #EF4444; }
    .toast-custom.info { background: var(--primary); }
    .toast-custom.warning { background: #D97706; }
    
    @media (max-width: 1024px) {
        .main-content { padding: 16px; }
        .row-2col { grid-template-columns: 1fr; }
    }
    
    @media (max-width: 768px) {
        .main-content { margin-left: 0; padding: 12px; }
        .page-header { flex-direction: column; padding: 16px 18px; }
        .page-header-right { width: 100%; }
        .page-header-right .btn { flex: 1; justify-content: center; }
        .consultation-card { padding: 16px 18px; }
        .avatar-upload { flex-direction: column; text-align: center; }
        .avatar-preview, .avatar-placeholder { width: 80px; height: 80px; font-size: 2rem; }
        .file-input-wrapper { justify-content: center; }
        .file-input { min-width: 100%; }
        .form-actions { flex-direction: column; }
        .form-actions .btn { width: 100%; justify-content: center; }
        .page-title { font-size: 1.2rem; }
        .page-subtitle { flex-direction: column; align-items: flex-start; gap: 4px; }
    }
    
    @media (max-width: 480px) {
        .main-content { padding: 10px; }
        .consultation-card { padding: 12px; }
        .form-control { font-size: 0.8rem; padding: 6px 10px; }
        .page-title { font-size: 1rem; }
    }
    
    @media print {
        .top-nav, .sidebar, .btn, .footer { display: none !important; }
        .main-content { margin: 0 !important; padding: 20px !important; }
        .consultation-card { border: 1px solid #ddd !important; box-shadow: none !important; }
        .page-header { background: #0B5ED7 !important; }
    }
</style>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE
    // ================================================================
    var darkModeToggle = document.getElementById('darkModeToggle');
    var darkIcon = document.getElementById('darkIcon');
    var darkText = document.getElementById('darkText');
    var htmlElement = document.documentElement;
    
    var savedDarkMode = localStorage.getItem('darkMode');
    if (savedDarkMode === 'true') {
        htmlElement.setAttribute('data-theme', 'dark');
        if (darkIcon) darkIcon.className = 'fas fa-sun';
        if (darkText) darkText.textContent = 'Light';
    }
    
    if (darkModeToggle) {
        darkModeToggle.addEventListener('click', function() {
            var isDark = htmlElement.getAttribute('data-theme') === 'dark';
            if (isDark) {
                htmlElement.removeAttribute('data-theme');
                if (darkIcon) darkIcon.className = 'fas fa-moon';
                if (darkText) darkText.textContent = 'Dark';
                localStorage.setItem('darkMode', 'false');
            } else {
                htmlElement.setAttribute('data-theme', 'dark');
                if (darkIcon) darkIcon.className = 'fas fa-sun';
                if (darkText) darkText.textContent = 'Light';
                localStorage.setItem('darkMode', 'true');
            }
        });
    }

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            if (sidebar) sidebar.classList.toggle('open');
        });
    }

    // ================================================================
    // DATE & TIME
    // ================================================================
    function updateDateTime() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        
        var clockDisplay = document.getElementById('clockDisplay');
        if (clockDisplay) {
            clockDisplay.textContent = dateStr + ' • ' + timeStr;
        }
        
        var footerTimestamp = document.getElementById('footerTimestamp');
        if (footerTimestamp) {
            footerTimestamp.textContent = 'Last updated: ' + timeStr;
        }
        
        var updateBadge = document.getElementById('lastUpdateBadge');
        if (updateBadge) {
            updateBadge.innerHTML = '<i class="fas fa-check-circle" style="color:#34D399;"></i> Live ' + timeStr;
        }
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        if (!toast) return;
        
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
    // FILE INPUT PREVIEW - FIXED
    // ================================================================
    var profilePicInput = document.getElementById('profilePicInput');
    if (profilePicInput) {
        profilePicInput.addEventListener('change', function(e) {
            var file = this.files[0];
            if (!file) return;
            
            // Validate file size
            var maxSize = 5 * 1024 * 1024; // 5MB
            if (file.size > maxSize) {
                showToast('Error', 'File size exceeds 5MB limit!', 'error');
                this.value = '';
                return;
            }
            
            // Validate file type
            var allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                showToast('Error', 'Only JPG, PNG, GIF and WEBP files are allowed!', 'error');
                this.value = '';
                return;
            }
            
            // Preview image
            var reader = new FileReader();
            reader.onload = function(e) {
                var preview = document.getElementById('avatarPreview');
                var placeholder = document.getElementById('avatarPlaceholder');
                var display = document.getElementById('avatarDisplay');
                
                if (preview) {
                    // Update existing img
                    preview.src = e.target.result;
                } else if (placeholder) {
                    // Replace placeholder with img
                    var img = document.createElement('img');
                    img.id = 'avatarPreview';
                    img.className = 'avatar-preview';
                    img.src = e.target.result;
                    img.alt = 'Profile';
                    placeholder.parentNode.replaceChild(img, placeholder);
                } else if (display) {
                    // Create new img in display
                    display.innerHTML = '<img src="' + e.target.result + '" alt="Profile" class="avatar-preview" id="avatarPreview">';
                }
                
                showToast('Preview Ready', 'Image loaded. Click Upload to save.', 'info');
            };
            reader.readAsDataURL(file);
        });
    }

    // ================================================================
    // SHOW TOAST FOR MESSAGES
    // ================================================================
    <?php if ($message && $message_type): ?>
        setTimeout(function() {
            showToast('<?= $message_type === 'success' ? '✅ Success' : ($message_type === 'warning' ? '⚠️ Notice' : '❌ Error') ?>', 
                '<?= addslashes(strip_tags($message)) ?>', 
                '<?= $message_type ?>'
            );
        }, 500);
    <?php endif; ?>

    console.log('%c👨‍⚕️ Braick - Doctor Edit Profile', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c👑 Role: <?= $_SESSION['role'] ?>', 'font-size:13px; color:#64748B;');
    <?php if ($is_admin): ?>
    console.log('%c👑 Admin Mode', 'font-size:13px; color:#DC2626;');
    <?php endif; ?>
    console.log('%c✅ Image preview works correctly', 'font-size:13px; color:#34D399;');
    console.log('%c🔄 Header with date and time active', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>