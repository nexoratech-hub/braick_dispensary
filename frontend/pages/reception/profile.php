<?php
// ================================================================
// FILE: frontend/pages/reception/profile.php
// RECEPTION - FULL PROFILE WITH PROFILE PICTURE UPLOAD
// PICTURE SHOWS ACROSS ALL PAGES
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION - Rose Mwangi (Reception)
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'reception') {
    $_SESSION['user_id'] = 6;
    $_SESSION['full_name'] = 'Rose Mwangi';
    $_SESSION['role'] = 'reception';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'reception.rose';
    $_SESSION['profile_pic'] = '';
    $_SESSION['email'] = 'rose@braick.com';
    $_SESSION['phone'] = '+255 700 000 005';
}

// ================================================================
// PATH SAHIHI
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'] ?? 'Rose Mwangi';
$user_role = $_SESSION['role'] ?? 'reception';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_username = $_SESSION['username'] ?? 'reception.rose';
$user_email = $_SESSION['email'] ?? 'rose@braick.com';
$user_phone = $_SESSION['phone'] ?? '+255 700 000 005';

$message = '';
$message_type = '';
$profile_pic = $_SESSION['profile_pic'] ?? '';

try {
    $db = getDB();
    
    // Get user data from database
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
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
        $upload_dir = 'C:/xampp/htdocs/dispensary_system/frontend/assets/uploads/profiles/';
        
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
            $new_filename = 'reception_' . $user_id . '_' . time() . '.' . $file_extension;
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
    $file_path = 'C:/xampp/htdocs/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic;
    if (file_exists($file_path)) {
        $profile_pic_exists = true;
    }
}

// Default avatar
$default_avatar = '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';
$default_letter = strtoupper(substr($user_full_name, 0, 1));

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once '../../components/reception_header.php';
include_once '../../components/reception_sidebar.php';
?>

<style>
    /* ================================================================
       PROFILE STYLES
       ================================================================ */
    .profile-card {
        background: var(--bg-card);
        border-radius: 18px;
        padding: 28px 32px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        max-width: 800px;
        margin: 0 auto;
    }
    .profile-card:hover {
        border-color: #0B5ED7;
        box-shadow: 0 8px 30px rgba(11, 94, 215, 0.08);
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
        border: 4px solid #0B5ED7;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        transition: all 0.3s ease;
        background: #E8F0FE;
    }
    .profile-avatar:hover {
        transform: scale(1.02);
        box-shadow: 0 8px 30px rgba(11, 94, 215, 0.25);
    }
    
    .profile-avatar-wrapper .upload-overlay {
        position: absolute;
        bottom: 0;
        right: 0;
        background: #0B5ED7;
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
        background: #0A4CA8;
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
        background: #0B5ED7;
        color: white;
        padding: 2px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
    }
    
    .form-label {
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 4px;
        display: block;
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
        border-color: #0B5ED7;
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.1);
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
        background: #0B5ED7;
        color: white;
    }
    .btn-primary:hover {
        background: #0A4CA8;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
    }
    .btn-outline {
        background: transparent;
        color: var(--text-secondary);
        border: 2px solid var(--border-color);
    }
    .btn-outline:hover {
        background: var(--bg-body);
        border-color: #0B5ED7;
        color: #0B5ED7;
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
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        align-items: center;
        gap: 10px;
        color: white;
    }
    .toast-custom.show {
        transform: translateY(0);
        opacity: 1;
    }
    .toast-custom.success { background: #059669; }
    .toast-custom.error { background: #DC2626; }
    .toast-custom.info { background: #0B5ED7; }
    
    .footer {
        padding: 14px 0;
        border-top: 2px solid var(--border-color);
        margin-top: 20px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--text-secondary);
    }
    .footer .footer-brand { color: #0B5ED7; font-weight: 600; }
    
    /* ================================================================
       BRANCH BADGE DISPLAY
       ================================================================ */
    .branch-badge-display {
        display: inline-block;
        font-size: 0.6rem;
        font-weight: 600;
        padding: 2px 10px;
        border-radius: 20px;
        background: var(--success-bg);
        color: var(--success);
    }
    [data-theme="dark"] .branch-badge-display {
        background: #1A3A2A;
        color: #34D399;
    }
    
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
    [data-theme="dark"] .role-badge-display {
        background: #1E3A5F;
        color: #6EA8FE;
    }
    
    .capitalize {
        text-transform: capitalize;
    }
    
    @media (max-width: 640px) {
        .profile-card {
            padding: 18px 16px;
        }
        .profile-avatar {
            width: 100px;
            height: 100px;
        }
        .profile-name {
            font-size: 1.3rem;
        }
        .profile-avatar-wrapper .upload-overlay {
            width: 32px;
            height: 32px;
            font-size: 0.8rem;
        }
        .btn {
            padding: 8px 16px;
            font-size: 0.78rem;
        }
        .info-row {
            flex-direction: column;
            gap: 4px;
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
            <input type="text" id="searchInput" placeholder="Search patients...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <span class="branch-badge-display">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($user_branch_name) ?>
        </span>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= ($unread_notifications ?? 0) > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <?php if ($profile_pic_exists && !empty($profile_pic)): ?>
                <img src="<?= '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic ?>" alt="Profile" class="avatar" style="object-fit:cover;">
            <?php else: ?>
                <div class="avatar avatar-default" style="background:#0B5ED7; color:white; display:flex; align-items:center; justify-content:center; font-size:1rem; font-weight:700; width:40px; height:40px; border-radius:50%; border:2px solid #0B5ED7;">
                    <?= $default_letter ?>
                </div>
            <?php endif; ?>
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
                <i class="fas fa-user-circle mr-2" style="color: var(--primary);"></i> My Profile
                <span class="role-badge-display ml-2">RECEPTION</span>
            </h1>
            <p class="page-subtitle">
                View and manage your profile information
            </p>
        </div>
        <div>
            <a href="dashboard.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="p-4 rounded-xl mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

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
                         onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22130%22 height=%22130%22%3E%3Crect width=%22130%22 height=%22130%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2265%22 y=%2285%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2250%22 font-weight=%22bold%22%3E<?= $default_letter ?>%3C/text%3E%3C/svg%3E'">
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
                    <span><i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($user_branch_name) ?></span>
                    <span><i class="fas fa-user mr-1"></i> <?= htmlspecialchars($user_username) ?></span>
                </div>
                <p class="text-sm text-gray-400 mt-1">
                    <i class="fas fa-calendar-alt mr-1"></i> Member since <?= date('F d, Y', strtotime($user['created_at'] ?? 'now')) ?>
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
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user_full_name) ?>" required>
                </div>
                
                <div>
                    <label class="form-label">Username</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($user_username) ?>" disabled>
                </div>
                
                <div>
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user_email) ?>" required>
                </div>
                
                <div>
                    <label class="form-label">Phone Number</label>
                    <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user_phone) ?>">
                </div>
                
                <div>
                    <label class="form-label">Role</label>
                    <input type="text" class="form-control" value="<?= ucfirst($user_role) ?>" disabled>
                </div>
                
                <div>
                    <label class="form-label">Branch</label>
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
        <h3 class="text-lg font-semibold text-gray-800 mb-3">
            <i class="fas fa-info-circle text-primary mr-2"></i> Account Information
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
        document.getElementById('currentDateTime').textContent = dateStr + ' • ' + timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

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
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

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

    console.log('%c👤 Braick - Reception Profile', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📸 Profile pic: <?= $profile_pic_exists ? 'Uploaded ✅' : 'Default' ?>', 'font-size:13px; color:#64748B;');
    console.log('%c✅ Profile picture shows across all pages', 'font-size:13px; color:#059669;');
</script>

</body>
</html>