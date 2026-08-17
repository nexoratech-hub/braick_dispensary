<?php
// ================================================================
// FILE: frontend/pages/admin/profile.php
// SUPER ADMIN - PROFILE PAGE
// VIEW AND EDIT ADMIN PROFILE
// WITH PROFILE PICTURE UPLOAD & USERNAME CHANGE
// BRAICK DISPENSARY
// WITH SESSION MANAGEMENT & LOGIN PROTECTION
// ================================================================

// ================================================================
// SESSION START
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

// ================================================================
// ROLE CHECK - ONLY ADMIN CAN ACCESS
// ================================================================
if ($_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../../auth/login.php'); break;
    }
    exit;
}

// ================================================================
// GET ADMIN DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Admin';
$user_role = $_SESSION['role'] ?? 'admin';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

// Include database
require_once '../../../backend/config/database.php';
require_once '../../../backend/helpers/functions.php';

$db = Database::getInstance()->getConnection();

// ================================================================
// GET USER DATA
// ================================================================
$user_id = $_SESSION['user_id'] ?? 1;

$stmt = $db->prepare("
    SELECT 
        u.*,
        b.name as branch_name,
        b.location as branch_location
    FROM users u
    LEFT JOIN branches b ON u.branch_id = b.id
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    // If user not found, use session data
    $user = [
        'id' => $_SESSION['user_id'] ?? 1,
        'username' => $_SESSION['username'] ?? 'admin',
        'full_name' => $_SESSION['full_name'] ?? 'Admin John',
        'email' => $_SESSION['email'] ?? 'admin@braick.com',
        'phone' => $_SESSION['phone'] ?? '+255 700 000 000',
        'role' => $_SESSION['role'] ?? 'admin',
        'branch_id' => $_SESSION['branch_id'] ?? 1,
        'branch_name' => 'Dodoma',
        'branch_location' => 'Dodoma City, Tanzania',
        'profile_pic' => $_SESSION['profile_pic'] ?? '',
        'created_at' => date('Y-m-d H:i:s')
    ];
}

// ================================================================
// CREATE UPLOAD DIRECTORY IF NOT EXISTS
// ================================================================
$upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/dispensary_system/frontend/assets/uploads/profiles/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// ================================================================
// HANDLE PROFILE UPDATE
// ================================================================
$message = '';
$message_type = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // ================================================================
    // UPDATE PROFILE (with username)
    // ================================================================
    if ($action === 'update_profile') {
        $full_name = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        
        // Validate
        if (empty($full_name)) {
            $errors[] = 'Full name is required';
        }
        if (empty($username)) {
            $errors[] = 'Username is required';
        }
        if (empty($email)) {
            $errors[] = 'Email is required';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address';
        }
        
        // Check if username already exists (except current user)
        if (empty($errors) && $username !== $user['username']) {
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$username, $user_id]);
            if ($stmt->fetch()) {
                $errors[] = 'Username already exists';
            }
        }
        
        // Check if email already exists (except current user)
        if (empty($errors) && $email !== $user['email']) {
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetch()) {
                $errors[] = 'Email already exists';
            }
        }
        
        if (empty($errors)) {
            try {
                $stmt = $db->prepare("
                    UPDATE users 
                    SET full_name = ?, username = ?, email = ?, phone = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$full_name, $username, $email, $phone, $user_id]);
                
                // Update session
                $_SESSION['full_name'] = $full_name;
                $_SESSION['username'] = $username;
                $_SESSION['email'] = $email;
                $_SESSION['phone'] = $phone;
                
                // Refresh user data
                $user['full_name'] = $full_name;
                $user['username'] = $username;
                $user['email'] = $email;
                $user['phone'] = $phone;
                
                $message = 'Profile updated successfully!';
                $message_type = 'success';
            } catch (Exception $e) {
                $errors[] = 'Failed to update profile: ' . $e->getMessage();
            }
        }
        
        if (!empty($errors)) {
            $message = implode('<br>', $errors);
            $message_type = 'error';
        }
    }
    
    // ================================================================
    // UPDATE PASSWORD
    // ================================================================
    if ($action === 'update_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($current_password)) {
            $errors[] = 'Current password is required';
        }
        if (empty($new_password)) {
            $errors[] = 'New password is required';
        }
        if (strlen($new_password) < 6) {
            $errors[] = 'Password must be at least 6 characters';
        }
        if ($new_password !== $confirm_password) {
            $errors[] = 'Passwords do not match';
        }
        
        if (empty($errors)) {
            // Verify current password
            $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user_data && password_verify($current_password, $user_data['password'])) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $user_id]);
                
                $message = 'Password changed successfully!';
                $message_type = 'success';
            } else {
                $errors[] = 'Current password is incorrect';
            }
        }
        
        if (!empty($errors)) {
            $message = implode('<br>', $errors);
            $message_type = 'error';
        }
    }
    
    // ================================================================
    // UPLOAD PROFILE PICTURE
    // ================================================================
    if ($action === 'upload_profile_pic') {
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_pic'];
            $file_name = $file['name'];
            $file_tmp = $file['tmp_name'];
            $file_size = $file['size'];
            $file_error = $file['error'];
            
            // Validate file type
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $file_type = mime_content_type($file_tmp);
            
            if (!in_array($file_type, $allowed_types)) {
                $errors[] = 'Only JPG, PNG, GIF, and WEBP images are allowed';
            }
            
            // Validate file size (max 2MB)
            if ($file_size > 2 * 1024 * 1024) {
                $errors[] = 'File size must be less than 2MB';
            }
            
            if (empty($errors)) {
                // Generate unique filename
                $extension = pathinfo($file_name, PATHINFO_EXTENSION);
                $new_filename = 'user_' . $user_id . '_' . time() . '.' . $extension;
                $upload_path = $upload_dir . $new_filename;
                
                // Delete old profile picture if exists
                if (!empty($user['profile_pic'])) {
                    $old_file = $upload_dir . $user['profile_pic'];
                    if (file_exists($old_file)) {
                        unlink($old_file);
                    }
                }
                
                // Move uploaded file
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    // Update database
                    $stmt = $db->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
                    $stmt->execute([$new_filename, $user_id]);
                    
                    // Update session
                    $_SESSION['profile_pic'] = $new_filename;
                    
                    // Refresh user data
                    $user['profile_pic'] = $new_filename;
                    
                    $message = 'Profile picture updated successfully!';
                    $message_type = 'success';
                } else {
                    $errors[] = 'Failed to upload image';
                }
            }
        } else {
            $errors[] = 'Please select an image to upload';
        }
        
        if (!empty($errors)) {
            $message = implode('<br>', $errors);
            $message_type = 'error';
        }
    }
    
    // ================================================================
    // REMOVE PROFILE PICTURE
    // ================================================================
    if ($action === 'remove_profile_pic') {
        if (!empty($user['profile_pic'])) {
            $old_file = $upload_dir . $user['profile_pic'];
            if (file_exists($old_file)) {
                unlink($old_file);
            }
            
            $stmt = $db->prepare("UPDATE users SET profile_pic = NULL WHERE id = ?");
            $stmt->execute([$user_id]);
            
            $_SESSION['profile_pic'] = '';
            $user['profile_pic'] = '';
            
            $message = 'Profile picture removed successfully!';
            $message_type = 'success';
        }
    }
}

// ================================================================
// GET PROFILE PICTURE
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
        $profile_pic = '';
        $user['profile_pic'] = '';
    }
}

// ================================================================
// PROFILE PICTURE URL FOR AVATAR
// ================================================================
$profile_pic_avatar = !empty($profile_pic_url) 
    ? $profile_pic_url 
    : 'data:image/svg+xml,' . urlencode('<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40"><rect width="40" height="40" rx="50%" fill="#0B5ED7"/><text x="20" y="26" text-anchor="middle" fill="white" font-size="18" font-weight="bold" font-family="Arial">' . $initial . '</text></svg>');

$logo_url = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// GET BRANCHES
// ================================================================
$branches = [];
$stmt = $db->query("SELECT id, name, location FROM branches WHERE status = 'active' ORDER BY name");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// INCLUDE SHARED HEADER
// ================================================================
include_once '../../components/admin_header.php';

// ================================================================
// INCLUDE SHARED SIDEBAR
// ================================================================
include_once '../../components/admin_sidebar.php';
?>

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
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn" title="Toggle Dark Mode">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $profile_pic_avatar ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3E<?= $initial ?>%3C/text%3E%3C/svg%3E'">
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
                <i class="fas fa-user-circle mr-2"></i> My Profile
            </h1>
            <p class="page-subtitle">
                View and manage your profile information
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($user['branch_name'] ?? 'N/A') ?>
                </span>
                <span class="ml-2 date-badge">
                    <i class="fas fa-calendar-day mr-1"></i> <?= date('F d, Y') ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="dashboard.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'error' ?> mb-5">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <span><?= $message ?></span>
            <button class="alert-close" onclick="this.parentElement.style.display='none'">&times;</button>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- PROFILE CARDS -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        
        <!-- ============================================================ -->
        <!-- PROFILE CARD WITH PICTURE UPLOAD -->
        <!-- ============================================================ -->
        <div class="profile-card">
            <div class="profile-avatar">
                <?php if ($show_initial): ?>
                    <div class="avatar-large">
                        <?= $initial ?>
                    </div>
                <?php else: ?>
                    <img src="<?= $profile_pic_url ?>" alt="Profile" class="avatar-large-img">
                <?php endif; ?>
                
                <!-- Upload button -->
                <form method="POST" action="" enctype="multipart/form-data" id="uploadForm" style="position:relative;">
                    <input type="hidden" name="action" value="upload_profile_pic">
                    <label class="avatar-upload-btn" title="Upload Profile Picture" for="profile_pic_input">
                        <i class="fas fa-camera"></i>
                    </label>
                    <input type="file" id="profile_pic_input" name="profile_pic" accept="image/*" style="display:none;" onchange="document.getElementById('uploadForm').submit();">
                </form>
                
                <?php if (!$show_initial): ?>
                    <form method="POST" action="" id="removePicForm" style="position:absolute; bottom:50px; right:4px;">
                        <input type="hidden" name="action" value="remove_profile_pic">
                        <button type="submit" class="avatar-remove-btn" title="Remove Profile Picture" onclick="return confirm('Are you sure you want to remove your profile picture?');">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            <div class="profile-name">
                <h2><?= htmlspecialchars($user['full_name'] ?? 'Admin User') ?></h2>
                <p class="profile-role">
                    <span class="badge badge-blue">
                        <i class="fas fa-user-tie"></i> <?= ucfirst($user['role'] ?? 'Admin') ?>
                    </span>
                </p>
                <p class="profile-username">
                    <i class="fas fa-at"></i> @<?= htmlspecialchars($user['username'] ?? 'admin') ?>
                </p>
            </div>
            <div class="profile-stats">
                <div class="stat-item">
                    <span class="stat-number"><?= date('Y', strtotime($user['created_at'] ?? 'now')) ?></span>
                    <span class="stat-label">Member Since</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number">1</span>
                    <span class="stat-label">Branch</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?= date('M d', strtotime($user['created_at'] ?? 'now')) ?></span>
                    <span class="stat-label">Joined Date</span>
                </div>
            </div>
        </div>
        
        <!-- ============================================================ -->
        <!-- PROFILE INFO -->
        <!-- ============================================================ -->
        <div class="card lg:col-span-2">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-user-edit title-blue mr-2"></i> Profile Information
                </h3>
            </div>
            
            <form method="POST" action="" class="profile-form" id="profileForm">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-user"></i> Full Name
                            <span class="required">*</span>
                        </label>
                        <input type="text" name="full_name" class="form-control" 
                               value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-at"></i> Username
                            <span class="required">*</span>
                        </label>
                        <input type="text" name="username" class="form-control" 
                               value="<?= htmlspecialchars($user['username'] ?? '') ?>" required>
                        <span class="form-help">Username must be unique</span>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-envelope"></i> Email
                            <span class="required">*</span>
                        </label>
                        <input type="email" name="email" class="form-control" 
                               value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-phone"></i> Phone Number
                        </label>
                        <input type="tel" name="phone" class="form-control" 
                               value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                    </div>
                </div>
                
                <div class="form-row single">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-store-alt"></i> Branch
                        </label>
                        <input type="text" class="form-control" 
                               value="<?= htmlspecialchars($user['branch_name'] ?? 'N/A') ?> - <?= htmlspecialchars($user['branch_location'] ?? '') ?>" disabled>
                        <span class="form-help">Branch is assigned by system administrator</span>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Profile
                    </button>
                </div>
            </form>
        </div>
        
    </div>

    <!-- ================================================================ -->
    <!-- CHANGE PASSWORD -->
    <!-- ================================================================ -->
    <div class="card mt-5">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-key title-orange mr-2"></i> Change Password
            </h3>
        </div>
        
        <form method="POST" action="" class="profile-form" id="passwordForm">
            <input type="hidden" name="action" value="update_password">
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-lock"></i> Current Password
                        <span class="required">*</span>
                    </label>
                    <input type="password" name="current_password" class="form-control" 
                           placeholder="Enter current password" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-lock"></i> New Password
                        <span class="required">*</span>
                    </label>
                    <input type="password" name="new_password" class="form-control" 
                           placeholder="Enter new password (min 6 characters)" required>
                </div>
            </div>
            
            <div class="form-row single">
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-check-circle"></i> Confirm New Password
                        <span class="required">*</span>
                    </label>
                    <input type="password" name="confirm_password" class="form-control" 
                           placeholder="Confirm new password" required>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-orange">
                    <i class="fas fa-key"></i> Change Password
                </button>
            </div>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Profile
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTime"><?= date('H:i:s') ?></span>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<style>
    /* ================================================================
       PROFILE PAGE STYLES
       ================================================================ */
    
    :root {
        --bg-body: #F1F5F9;
        --bg-card: #FFFFFF;
        --text-primary: #0F172A;
        --text-secondary: #64748B;
        --border-color: #E2E8F0;
        --shadow-sm: 0 2px 8px rgba(0,0,0,0.06);
        --shadow-md: 0 4px 20px rgba(0,0,0,0.08);
        --radius: 16px;
        --radius-sm: 10px;
        --blue: #0B5ED7;
        --blue-light: #EFF6FF;
        --orange: #D97706;
        --orange-light: #FFFBEB;
        --green: #059669;
        --success: #059669;
        --danger: #EF4444;
    }
    
    [data-theme="dark"] {
        --bg-body: #0F172A;
        --bg-card: #1E293B;
        --text-primary: #F1F5F9;
        --text-secondary: #94A3B8;
        --border-color: #334155;
        --shadow-sm: 0 2px 8px rgba(0,0,0,0.3);
        --shadow-md: 0 4px 20px rgba(0,0,0,0.4);
        --blue-light: #1E3A5F;
        --orange-light: #3D2E0A;
    }
    
    .main-content {
        padding: 20px 24px;
        background: var(--bg-body);
        min-height: 100vh;
        transition: all 0.3s ease;
    }
    
    /* ================================================================
       PROFILE CARD
       ================================================================ */
    .profile-card {
        background: var(--bg-card);
        border-radius: var(--radius);
        border: 1px solid var(--border-color);
        padding: 24px;
        text-align: center;
        box-shadow: var(--shadow-sm);
        transition: all 0.3s ease;
    }
    
    .profile-card:hover {
        box-shadow: var(--shadow-md);
        border-color: var(--blue);
    }
    
    .profile-avatar {
        position: relative;
        display: inline-block;
        margin-bottom: 16px;
    }
    
    .avatar-large {
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
        border: 4px solid var(--border-color);
        margin: 0 auto;
    }
    
    .avatar-large-img {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid var(--border-color);
        margin: 0 auto;
    }
    
    .avatar-upload-btn {
        position: absolute;
        bottom: 4px;
        right: 4px;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: var(--blue);
        color: white;
        border: 2px solid var(--bg-card);
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
    }
    
    .avatar-upload-btn:hover {
        background: #0A4CA8;
        transform: scale(1.1);
    }
    
    .avatar-remove-btn {
        position: absolute;
        bottom: 50px;
        right: 4px;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: var(--danger);
        color: white;
        border: 2px solid var(--bg-card);
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
    }
    
    .avatar-remove-btn:hover {
        background: #DC2626;
        transform: scale(1.1);
    }
    
    .profile-name h2 {
        font-size: 1.3rem;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0;
    }
    
    .profile-role {
        margin-top: 4px;
    }
    
    .profile-role .badge {
        font-size: 0.7rem;
        padding: 4px 16px;
    }
    
    .profile-username {
        font-size: 0.85rem;
        color: var(--text-secondary);
        margin-top: 4px;
    }
    
    .profile-username i {
        color: var(--blue);
    }
    
    .profile-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
        margin-top: 16px;
        padding-top: 16px;
        border-top: 1px solid var(--border-color);
    }
    
    .profile-stats .stat-item {
        text-align: center;
    }
    
    .profile-stats .stat-number {
        display: block;
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--text-primary);
    }
    
    .profile-stats .stat-label {
        font-size: 0.6rem;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    
    /* ================================================================
       FORM
       ================================================================ */
    .card {
        background: var(--bg-card);
        border-radius: var(--radius);
        border: 1px solid var(--border-color);
        overflow: hidden;
        transition: all 0.3s ease;
        box-shadow: var(--shadow-sm);
    }
    
    .card:hover {
        box-shadow: var(--shadow-md);
    }
    
    .card-header {
        padding: 16px 24px;
        background: var(--bg-body);
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    [data-theme="dark"] .card-header {
        background: #0F172A;
    }
    
    .card-title {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
        display: flex;
        align-items: center;
    }
    
    .title-blue { color: var(--blue); }
    .title-orange { color: var(--orange); }
    
    .profile-form {
        padding: 24px;
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 16px;
    }
    
    .form-row.single {
        grid-template-columns: 1fr;
    }
    
    .form-group {
        margin-bottom: 0;
    }
    
    .form-label {
        display: block;
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 6px;
    }
    
    .form-label i {
        margin-right: 6px;
        color: var(--blue);
        width: 16px;
        text-align: center;
    }
    
    [data-theme="dark"] .form-label i {
        color: #6EA8FE;
    }
    
    .form-label .required {
        color: var(--danger);
        margin-left: 2px;
    }
    
    .form-control {
        width: 100%;
        padding: 10px 14px;
        font-size: 0.9rem;
        color: var(--text-primary);
        background: var(--bg-card);
        border: 1.5px solid var(--border-color);
        border-radius: 8px;
        transition: all 0.3s ease;
        outline: none;
    }
    
    .form-control:focus {
        border-color: var(--blue);
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
    }
    
    .form-control:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        background: var(--bg-body);
    }
    
    .form-control::placeholder {
        color: var(--text-secondary);
        opacity: 0.6;
    }
    
    .form-help {
        display: block;
        font-size: 0.7rem;
        color: var(--text-secondary);
        margin-top: 4px;
    }
    
    .form-actions {
        display: flex;
        gap: 12px;
        margin-top: 8px;
        padding-top: 16px;
        border-top: 1px solid var(--border-color);
    }
    
    /* ================================================================
       BUTTONS
       ================================================================ */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        background: var(--bg-card);
        color: var(--text-primary);
        border: 1.5px solid var(--border-color);
    }
    
    .btn:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }
    
    .btn-sm {
        padding: 5px 12px;
        font-size: 0.7rem;
        border-radius: 6px;
    }
    
    .btn-primary {
        background: var(--blue);
        color: white;
        border-color: var(--blue);
    }
    
    .btn-primary:hover {
        background: #0A4CA8;
        border-color: #0A4CA8;
        color: white;
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.35);
    }
    
    .btn-orange {
        background: var(--orange);
        color: white;
        border-color: var(--orange);
    }
    
    .btn-orange:hover {
        background: #B45309;
        border-color: #B45309;
        color: white;
        box-shadow: 0 4px 12px rgba(217, 119, 6, 0.35);
    }
    
    .btn-outline {
        background: transparent;
        color: var(--text-secondary);
        border: 1.5px solid var(--border-color);
    }
    
    .btn-outline:hover {
        background: var(--bg-body);
        border-color: var(--blue);
        color: var(--blue);
    }
    
    /* ================================================================
       ALERTS
       ================================================================ */
    .alert {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 18px;
        border-radius: var(--radius-sm);
        font-size: 0.9rem;
        border: 1px solid transparent;
        position: relative;
    }
    
    .alert-success {
        background: #ECFDF5;
        color: #065F46;
        border-color: #A7F3D0;
    }
    
    .alert-error {
        background: #FEF2F2;
        color: #991B1B;
        border-color: #FECACA;
    }
    
    [data-theme="dark"] .alert-success {
        background: #1A3A2A;
        color: #34D399;
        border-color: #065F46;
    }
    
    [data-theme="dark"] .alert-error {
        background: #3A1A1A;
        color: #F87171;
        border-color: #7F1D1D;
    }
    
    .alert i {
        font-size: 1.1rem;
        flex-shrink: 0;
    }
    
    .alert-close {
        background: none;
        border: none;
        font-size: 1.3rem;
        cursor: pointer;
        color: inherit;
        opacity: 0.5;
        margin-left: auto;
        padding: 0 4px;
        transition: opacity 0.3s;
    }
    
    .alert-close:hover {
        opacity: 1;
    }
    
    /* ================================================================
       BADGES
       ================================================================ */
    .badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        color: white;
        letter-spacing: 0.02em;
    }
    
    .badge-blue { background: var(--blue); }
    .badge-green { background: var(--green); }
    .badge-orange { background: var(--orange); }
    
    /* ================================================================
       PAGE HEADER
       ================================================================ */
    .page-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0;
        display: flex;
        align-items: center;
    }
    
    .page-title i {
        color: var(--blue);
    }
    
    .page-subtitle {
        font-size: 0.85rem;
        color: var(--text-secondary);
        margin: 4px 0 0 0;
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 4px;
    }
    
    .branch-tag {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: var(--blue-light);
        color: var(--blue);
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 500;
    }
    
    [data-theme="dark"] .branch-tag {
        background: #1E3A5F;
        color: #6EA8FE;
    }
    
    .date-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: var(--text-secondary);
        font-size: 0.75rem;
    }
    
    .mt-5 { margin-top: 20px; }
    
    /* ================================================================
       FOOTER
       ================================================================ */
    .footer {
        margin-top: 30px;
        padding: 16px 20px;
        background: var(--bg-card);
        border-radius: var(--radius);
        border: 1px solid var(--border-color);
        text-align: center;
    }
    
    .footer p {
        margin: 0;
        font-size: 0.8rem;
        color: var(--text-secondary);
    }
    
    .footer-brand {
        font-weight: 700;
        color: var(--blue);
    }
    
    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 1024px) {
        .main-content { padding: 16px; }
        .grid-cols-1 { grid-template-columns: 1fr; }
        .lg\:col-span-2 { grid-column: span 1; }
    }
    
    @media (max-width: 768px) {
        .main-content { padding: 12px; }
        .form-row { grid-template-columns: 1fr; gap: 12px; }
        .profile-card { padding: 16px; }
        .avatar-large, .avatar-large-img { width: 100px; height: 100px; font-size: 2.5rem; }
        .profile-stats { grid-template-columns: repeat(3, 1fr); }
        .form-actions { flex-direction: column; }
        .form-actions .btn { width: 100%; justify-content: center; }
        .page-title { font-size: 1.2rem; }
        .page-subtitle { font-size: 0.75rem; }
        .card-header { padding: 12px 16px; }
        .profile-form { padding: 16px; }
    }
    
    @media (max-width: 480px) {
        .main-content { padding: 10px; }
        .profile-stats { grid-template-columns: 1fr; gap: 4px; }
        .avatar-large, .avatar-large-img { width: 80px; height: 80px; font-size: 2rem; }
        .profile-name h2 { font-size: 1.1rem; }
        .btn { font-size: 0.8rem; padding: 8px 14px; }
    }
    
    /* ================================================================
       PRINT
       ================================================================ */
    @media print {
        .top-nav, .sidebar, #sidebarToggle, .btn, .dark-toggle-btn,
        .icon-btn, .search-wrapper, .page-header .flex.gap-2, .footer,
        .form-actions { display: none !important; }
        
        .main-content { padding: 0 !important; background: white !important; }
        .card, .profile-card { box-shadow: none !important; border: 1px solid #ddd !important; }
        .avatar-large { background: #0B5ED7 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .badge { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .badge-blue { background: #0B5ED7 !important; color: white !important; }
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
            document.cookie = "dark_mode=false; path=/";
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
            document.cookie = "dark_mode=true; path=/";
        }
    });

    // ================================================================
    // DOM ELEMENTS
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    sidebarToggle?.addEventListener('click', function() {
        sidebar.classList.toggle('open');
    });
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    // ================================================================
    // SEARCH
    // ================================================================
    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            window.location.href = 'search.php?q=' + encodeURIComponent(query);
        }
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

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
        var dtEl = document.getElementById('currentDateTime');
        if (dtEl) dtEl.textContent = dateStr + ' • ' + timeStr;
        
        var ftEl = document.getElementById('footerTime');
        if (ftEl) ftEl.textContent = timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // PROFILE PICTURE UPLOAD
    // ================================================================
    document.getElementById('profile_pic_input')?.addEventListener('change', function(e) {
        var file = this.files[0];
        if (file) {
            // Validate file size (2MB max)
            if (file.size > 2 * 1024 * 1024) {
                alert('⚠️ File size must be less than 2MB!');
                this.value = '';
                return;
            }
            
            // Validate file type
            var validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!validTypes.includes(file.type)) {
                alert('⚠️ Only JPG, PNG, GIF, and WEBP images are allowed!');
                this.value = '';
                return;
            }
            
            document.getElementById('uploadForm').submit();
        }
    });

    // ================================================================
    // FORM VALIDATION
    // ================================================================
    document.getElementById('profileForm')?.addEventListener('submit', function(e) {
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

    document.getElementById('passwordForm')?.addEventListener('submit', function(e) {
        var currentPass = document.querySelector('input[name="current_password"]').value;
        var newPass = document.querySelector('input[name="new_password"]').value;
        var confirmPass = document.querySelector('input[name="confirm_password"]').value;
        
        if (!currentPass) {
            e.preventDefault();
            alert('⚠️ Please enter your current password!');
            return false;
        }
        
        if (newPass.length < 6) {
            e.preventDefault();
            alert('⚠️ New password must be at least 6 characters long!');
            return false;
        }
        
        if (newPass !== confirmPass) {
            e.preventDefault();
            alert('⚠️ Passwords do not match!');
            return false;
        }
        
        return true;
    });

    console.log('%c👤 Braick Dispensary - Profile Page', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c👤 Admin: <?= htmlspecialchars($user['full_name'] ?? 'Admin') ?>', 'font-size:13px; color:#059669;');
    console.log('%c📧 Email: <?= htmlspecialchars($user['email'] ?? '') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c🏢 Branch: <?= htmlspecialchars($user['branch_name'] ?? 'N/A') ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📸 Profile Pic: <?= !empty($profile_pic) ? '✅ Uploaded' : '❌ Not set' ?>', 'font-size:13px; color:#059669;');
    console.log('%c🔄 Username can be changed', 'font-size:13px; color:#34D399;');
    console.log('%c🔒 Login protection: ACTIVE', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>