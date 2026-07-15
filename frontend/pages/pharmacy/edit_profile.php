<?php
// ================================================================
// FILE: frontend/pages/pharmacy/edit_profile.php
// PHARMACY - EDIT PROFILE
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// INCLUDE CONFIG
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

// ================================================================
// SESSION - Default to pharm.peter
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pharmacy') {
    $_SESSION['user_id'] = 5;
    $_SESSION['full_name'] = 'Peter Ngalula';
    $_SESSION['role'] = 'pharmacy';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'pharm.peter';
    $_SESSION['email'] = 'peter@braick.com';
    $_SESSION['phone'] = '+255 700 000 004';
    $_SESSION['is_admin'] = false;
    $_SESSION['profile_pic'] = '';
}

$user_id = $_SESSION['user_id'] ?? 5;
$user_full_name = $_SESSION['full_name'] ?? 'Peter Ngalula';
$user_role = $_SESSION['role'] ?? 'pharmacy';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_username = $_SESSION['username'] ?? 'pharm.peter';
$user_email = $_SESSION['email'] ?? 'peter@braick.com';
$user_phone = $_SESSION['phone'] ?? '+255 700 000 004';
$profile_pic = $_SESSION['profile_pic'] ?? '';

$db = getDB();

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
                $user_data = $stmt->fetch();
                if ($user_data && password_verify($current_password, $user_data['password'])) {
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
$pending_prescriptions = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescription_sales WHERE branch_id = ? AND status = 'pending'");
    $stmt->execute([$user_branch_id]);
    $pending_prescriptions = $stmt->fetch()['count'] ?? 0;
} catch (Exception $e) {
    $pending_prescriptions = 0;
}

$low_stock_count = 0;
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM medications_inventory 
        WHERE branch_id = ? AND quantity <= reorder_level AND status = 'active'
    ");
    $stmt->execute([$user_branch_id]);
    $low_stock_count = $stmt->fetch()['count'] ?? 0;
} catch (Exception $e) {
    $low_stock_count = 0;
}

$total_employees = 0;
$total_doctors = 0;
$total_branches = 0;

// ================================================================
// UNREAD NOTIFICATIONS
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
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/pharmacy_header.php';
include_once __DIR__ . '/../../components/pharmacy_sidebar.php';
?>

<style>
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
    
    .help-text {
        font-size: 0.7rem;
        color: var(--text-secondary);
        margin-top: 4px;
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
    }
</style>

<!-- ================================================================ -->
<!-- TOP NAVIGATION -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <input type="text" id="searchInput" placeholder="Search...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <span class="branch-badge">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($user_branch_name) ?>
        </span>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= $unread_notifications > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= strtoupper(substr($user_full_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-5">
        <div>
            <h1 class="page-title">
                <i class="fas fa-user-edit mr-2" style="color: var(--primary);"></i> Edit Profile
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
    <footer class="footer mt-5">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Edit Profile
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
    // DARK MODE
    // ================================================================
    var darkModeToggle = document.getElementById('darkModeToggle');
    var darkIcon = document.getElementById('darkIcon');
    var darkText = document.getElementById('darkText');
    var htmlElement = document.documentElement;
    
    var savedDarkMode = localStorage.getItem('darkMode');
    if (savedDarkMode === 'true') {
        htmlElement.setAttribute('data-theme', 'dark');
        darkIcon.className = 'fas fa-sun';
        darkText.textContent = 'Light';
    }
    
    darkModeToggle?.addEventListener('click', function() {
        var isDark = htmlElement.getAttribute('data-theme') === 'dark';
        if (isDark) {
            htmlElement.removeAttribute('data-theme');
            darkIcon.className = 'fas fa-moon';
            darkText.textContent = 'Dark';
            localStorage.setItem('darkMode', 'false');
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
        }
    });

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
    }

    // ================================================================
    // SEARCH
    // ================================================================
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
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
        var el = document.getElementById('currentDateTime');
        if (el) {
            el.textContent = dateStr + ' • ' + timeStr;
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
    document.getElementById('profilePicInput')?.addEventListener('change', function(e) {
        var file = this.files[0];
        if (file) {
            var maxSize = 5 * 1024 * 1024; // 5MB
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
            
            // Preview image
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

    console.log('%c💊 Braick - Pharmacy Edit Profile', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📁 Upload Dir: <?= $upload_dir ?>', 'font-size:13px; color:#64748B;');
</script>

</body>
</html>