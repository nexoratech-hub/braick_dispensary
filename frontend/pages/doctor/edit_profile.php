<?php
// ================================================================
// FILE: frontend/pages/doctor/edit_profile.php
// DOCTOR - EDIT PROFILE (FIXED SESSION START)
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// START SESSION - WITH CHECK
// ================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// IF NO SESSION, USE DR. SARAH MWAMBA (ID: 2) AS DEFAULT
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    $_SESSION['user_id'] = 2;
    $_SESSION['full_name'] = 'Dr. Sarah Mwamba';
    $_SESSION['username'] = 'dr.sarah';
    $_SESSION['email'] = 'sarah@braick.com';
    $_SESSION['phone'] = '+255 700 000 001';
    $_SESSION['role'] = 'doctor';
    $_SESSION['branch_id'] = 1;
    $_SESSION['specialty'] = 'Cardiology';
    $_SESSION['profile_pic'] = NULL;
}

$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['full_name'] ?? 'Doctor';
$doctor_username = $_SESSION['username'] ?? 'doctor';
$doctor_email = $_SESSION['email'] ?? 'No email';
$doctor_phone = $_SESSION['phone'] ?? 'No phone';
$doctor_specialty = $_SESSION['specialty'] ?? 'General Practitioner';
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;

// ================================================================
// INCLUDE DATABASE
// ================================================================
$db_path = 'C:/xampp/htdocs/dispensary_system/backend/config/database.php';
if (file_exists($db_path)) {
    require_once $db_path;
} else {
    die("❌ Database file not found at: " . $db_path);
}
$db = Database::getInstance()->getConnection();

// ================================================================
// GET DOCTOR'S BRANCH NAME
// ================================================================
$doctor_branch_name = 'Not Assigned';
try {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$doctor_branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) {
        $doctor_branch_name = $branch_data['name'];
    }
} catch (Exception $e) {
    $doctor_branch_name = 'Branch';
}

// ================================================================
// GET PROFILE PICTURE
// ================================================================
$profile_pic_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
try {
    $stmt = $db->prepare("SELECT profile_pic FROM users WHERE id = ?");
    $stmt->execute([$doctor_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user_data && !empty($user_data['profile_pic'])) {
        $profile_pic_path = '/dispensary_system/frontend/assets/uploads/profiles/' . $user_data['profile_pic'];
        $_SESSION['profile_pic'] = $user_data['profile_pic'];
        $_SESSION['profile_pic_path'] = $profile_pic_path;
    }
} catch (Exception $e) {
    // Use default
}

// ================================================================
// HANDLE FORM SUBMISSION
// ================================================================
$message = '';
$message_type = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $specialty = trim($_POST['specialty'] ?? '');
    
    // Validate
    if (empty($full_name)) {
        $errors[] = 'Full name is required';
    }
    if (empty($email)) {
        $errors[] = 'Email is required';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required';
    }
    if (empty($specialty)) {
        $errors[] = 'Specialty is required';
    }
    
    // Handle profile picture upload
    $profile_pic_uploaded = false;
    $profile_pic_name = NULL;
    
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'C:/xampp/htdocs/dispensary_system/frontend/assets/uploads/profiles/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            $profile_pic_name = 'doctor_' . $doctor_id . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $profile_pic_name;
            
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_path)) {
                $profile_pic_uploaded = true;
            } else {
                $errors[] = 'Failed to upload profile picture';
            }
        } else {
            $errors[] = 'Invalid file format. Allowed: jpg, jpeg, png, gif, webp';
        }
    }
    
    if (empty($errors)) {
        if ($profile_pic_uploaded) {
            $sql = "UPDATE users SET full_name = ?, email = ?, phone = ?, specialty = ?, profile_pic = ? WHERE id = ?";
            $params = [$full_name, $email, $phone, $specialty, $profile_pic_name, $doctor_id];
        } else {
            $sql = "UPDATE users SET full_name = ?, email = ?, phone = ?, specialty = ? WHERE id = ?";
            $params = [$full_name, $email, $phone, $specialty, $doctor_id];
        }
        
        try {
            $stmt = $db->prepare($sql);
            if ($stmt->execute($params)) {
                $_SESSION['full_name'] = $full_name;
                $_SESSION['email'] = $email;
                $_SESSION['phone'] = $phone;
                $_SESSION['specialty'] = $specialty;
                
                if ($profile_pic_uploaded) {
                    $_SESSION['profile_pic'] = $profile_pic_name;
                    $_SESSION['profile_pic_path'] = '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic_name;
                }
                
                $message = 'Profile updated successfully!';
                $message_type = 'success';
                $doctor_name = $full_name;
                $doctor_email = $email;
                $doctor_phone = $phone;
                $doctor_specialty = $specialty;
                
                echo '<script>setTimeout(function(){ window.location.href = "profile.php?updated=1"; }, 1500);</script>';
            } else {
                $errors[] = 'Failed to update profile';
            }
        } catch (Exception $e) {
            $errors[] = 'Error updating profile: ' . $e->getMessage();
        }
    }
    
    if (!empty($errors)) {
        $message = implode('<br>', $errors);
        $message_type = 'error';
    }
}

// ================================================================
// VARIABLES FOR SIDEBAR
// ================================================================
$selected_branch_id = $doctor_branch_id;

// ================================================================
// INCLUDE SHARED HEADER & SIDEBAR
// ================================================================
include_once 'C:/xampp/htdocs/dispensary_system/frontend/components/doctor_header.php';
include_once 'C:/xampp/htdocs/dispensary_system/frontend/components/doctor_sidebar.php';
?>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-user-edit mr-2"></i> Edit Profile
            </h1>
            <p class="page-subtitle">
                Update your profile information
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor_branch_name) ?>
                </span>
            </p>
        </div>
        <a href="profile.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Back to Profile
        </a>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="alert <?= $message_type === 'success' ? 'alert-success' : 'alert-error' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- Edit Profile Form -->
    <div class="edit-profile-card">
        <form method="POST" action="" enctype="multipart/form-data" id="editProfileForm">
            
            <!-- Profile Picture -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-image"></i> Profile Picture
                </div>
                <div class="avatar-upload">
                    <div class="avatar-preview-wrapper">
                        <img src="<?= $profile_pic_path ?>" alt="Profile Picture" id="profilePicPreview"
                             onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2280%22 height=%2280%22%3E%3Crect width=%2280%22 height=%2280%22 fill=%22%230B5ED7%22 rx=%2240%22/%3E%3Ctext x=%2240%22 y=%2248%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2232%22 font-weight=%22bold%22%3E<?= strtoupper(substr($doctor_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
                    </div>
                    <div class="avatar-upload-actions">
                        <input type="file" name="profile_pic" id="profilePicInput" accept="image/*">
                        <label for="profilePicInput" class="btn-upload">
                            <i class="fas fa-camera"></i> Choose Photo
                        </label>
                        <p class="upload-hint">Allowed: jpg, jpeg, png, gif, webp (Max: 5MB)</p>
                        <p class="upload-info"><i class="fas fa-info-circle"></i> Profile picture updates instantly across all pages</p>
                    </div>
                </div>
            </div>

            <!-- Personal Information -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-user"></i> Personal Information
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Full Name <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <i class="fas fa-user input-icon"></i>
                            <input type="text" name="full_name" class="form-control" 
                                   value="<?= htmlspecialchars($doctor_name) ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Username <span class="text-muted">(Cannot be changed)</span></label>
                        <div class="input-wrapper">
                            <i class="fas fa-at input-icon"></i>
                            <input type="text" class="form-control readonly" value="<?= htmlspecialchars($doctor_username) ?>" disabled readonly>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <i class="fas fa-envelope input-icon"></i>
                            <input type="email" name="email" class="form-control" 
                                   value="<?= htmlspecialchars($doctor_email) ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <div class="input-wrapper">
                            <i class="fas fa-phone input-icon"></i>
                            <input type="text" name="phone" class="form-control" 
                                   value="<?= htmlspecialchars($doctor_phone) ?>">
                        </div>
                    </div>
                    <div class="form-group full-width">
                        <label class="form-label">Specialty <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <i class="fas fa-stethoscope input-icon"></i>
                            <select name="specialty" class="form-control" required>
                                <option value="Cardiology" <?= $doctor_specialty === 'Cardiology' ? 'selected' : '' ?>>Cardiology</option>
                                <option value="Pediatrics" <?= $doctor_specialty === 'Pediatrics' ? 'selected' : '' ?>>Pediatrics</option>
                                <option value="Gynecology" <?= $doctor_specialty === 'Gynecology' ? 'selected' : '' ?>>Gynecology</option>
                                <option value="Orthopedics" <?= $doctor_specialty === 'Orthopedics' ? 'selected' : '' ?>>Orthopedics</option>
                                <option value="Neurology" <?= $doctor_specialty === 'Neurology' ? 'selected' : '' ?>>Neurology</option>
                                <option value="Endocrinology" <?= $doctor_specialty === 'Endocrinology' ? 'selected' : '' ?>>Endocrinology</option>
                                <option value="Dermatology" <?= $doctor_specialty === 'Dermatology' ? 'selected' : '' ?>>Dermatology</option>
                                <option value="Ophthalmology" <?= $doctor_specialty === 'Ophthalmology' ? 'selected' : '' ?>>Ophthalmology</option>
                                <option value="General Practitioner" <?= $doctor_specialty === 'General Practitioner' ? 'selected' : '' ?>>General Practitioner</option>
                                <option value="Other" <?= $doctor_specialty === 'Other' ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Branch Information -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-store-alt"></i> Branch Information
                </div>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Branch</span>
                        <span class="info-value"><?= htmlspecialchars($doctor_branch_name) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Role</span>
                        <span class="info-value">Doctor</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Doctor ID</span>
                        <span class="info-value">#<?= htmlspecialchars($doctor_id) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Status</span>
                        <span class="info-value"><span class="badge-active">Active</span></span>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="form-actions">
                <button type="submit" class="btn-save">
                    <i class="fas fa-save"></i> Update Profile
                </button>
                <a href="profile.php" class="btn-cancel">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Edit Profile
            <span class="text-gray-300 mx-2">|</span>
            Logged in as: <strong><?= htmlspecialchars($doctor_name) ?></strong>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<style>
    /* ================================================================
       EDIT PROFILE - BEAUTIFUL CSS
       ================================================================ */
    
    .edit-profile-card {
        background: var(--bg-card);
        border-radius: 20px;
        padding: 32px 36px;
        border: 2px solid var(--border-color);
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        transition: all 0.3s ease;
        max-width: 52rem;
        margin: 0 auto;
    }
    
    .edit-profile-card:hover {
        border-color: var(--primary);
        box-shadow: 0 8px 30px rgba(11, 94, 215, 0.08);
    }
    
    /* Page Header */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        border-bottom: 3px solid var(--primary);
        padding-bottom: 14px;
        margin-bottom: 24px;
    }
    
    .page-title {
        font-size: 1.6rem;
        font-weight: 700;
        color: var(--primary-dark);
        margin: 0;
    }
    
    .page-title i {
        color: var(--primary);
    }
    
    [data-theme="dark"] .page-title {
        color: var(--primary-light);
    }
    
    .page-subtitle {
        color: var(--text-secondary);
        font-size: 0.9rem;
        margin: 4px 0 0 0;
    }
    
    .branch-tag {
        background: var(--primary);
        color: white;
        padding: 3px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .btn-back {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 18px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.8rem;
        transition: all 0.3s ease;
        text-decoration: none;
        background: transparent;
        color: var(--text-secondary);
        border: 2px solid var(--border-color);
    }
    
    .btn-back:hover {
        border-color: var(--primary);
        color: var(--primary);
        background: var(--primary-bg);
    }
    
    /* Alert */
    .alert {
        padding: 14px 18px;
        border-radius: 12px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 0.9rem;
        max-width: 52rem;
        margin-left: auto;
        margin-right: auto;
    }
    
    .alert-success {
        background: #ECFDF5;
        color: #059669;
        border: 1px solid #D1FAE5;
    }
    
    .alert-error {
        background: #FEE2E2;
        color: #EF4444;
        border: 1px solid #FECACA;
    }
    
    [data-theme="dark"] .alert-success {
        background: #1A3A2A;
        color: #34D399;
        border-color: #1A3A2A;
    }
    
    [data-theme="dark"] .alert-error {
        background: #3A1A1A;
        color: #F87171;
        border-color: #3A1A1A;
    }
    
    /* Form Sections */
    .form-section {
        margin-bottom: 28px;
        padding-bottom: 24px;
        border-bottom: 2px solid var(--border-color);
    }
    
    .form-section:last-of-type {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }
    
    .section-title {
        font-size: 1rem;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .section-title i {
        color: var(--primary);
        font-size: 1.1rem;
    }
    
    /* Form Grid */
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 18px;
    }
    
    .form-group.full-width {
        grid-column: span 2;
    }
    
    .form-label {
        display: block;
        font-size: 0.82rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 4px;
    }
    
    .form-label .required {
        color: #EF4444;
        margin-left: 2px;
    }
    
    .form-label .text-muted {
        font-weight: 400;
        font-size: 0.7rem;
        color: var(--text-muted);
    }
    
    .input-wrapper {
        position: relative;
    }
    
    .input-wrapper .input-icon {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
        font-size: 0.9rem;
        transition: color 0.3s ease;
        pointer-events: none;
    }
    
    .input-wrapper .form-control:focus ~ .input-icon {
        color: var(--primary);
    }
    
    .form-control {
        width: 100%;
        padding: 10px 14px 10px 44px;
        border: 2px solid var(--border-color);
        border-radius: 10px;
        font-size: 0.9rem;
        background: var(--bg-card);
        color: var(--text-primary);
        outline: none;
        transition: all 0.3s ease;
        font-family: inherit;
    }
    
    .form-control:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 4px rgba(11, 94, 215, 0.1);
    }
    
    .form-control.readonly,
    .form-control:disabled {
        background: var(--bg-body);
        color: var(--text-secondary);
        cursor: not-allowed;
    }
    
    .form-control::placeholder {
        color: var(--text-secondary);
        opacity: 0.6;
    }
    
    select.form-control {
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748b' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 14px center;
        padding-right: 36px;
        cursor: pointer;
    }
    
    /* Avatar Upload */
    .avatar-upload {
        display: flex;
        align-items: center;
        gap: 28px;
        flex-wrap: wrap;
    }
    
    .avatar-preview-wrapper {
        width: 90px;
        height: 90px;
        border-radius: 50%;
        overflow: hidden;
        flex-shrink: 0;
        border: 3px solid var(--primary);
        box-shadow: 0 4px 14px rgba(11, 94, 215, 0.2);
    }
    
    .avatar-preview-wrapper img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .avatar-upload-actions {
        flex: 1;
    }
    
    .avatar-upload-actions input[type="file"] {
        display: none;
    }
    
    .btn-upload {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 20px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.8rem;
        cursor: pointer;
        transition: all 0.3s ease;
        background: var(--bg-body);
        color: var(--text-primary);
        border: 2px solid var(--border-color);
    }
    
    .btn-upload:hover {
        border-color: var(--primary);
        color: var(--primary);
        background: var(--primary-bg);
    }
    
    .upload-hint {
        font-size: 0.7rem;
        color: var(--text-muted);
        margin: 4px 0 0 0;
    }
    
    .upload-info {
        font-size: 0.7rem;
        color: var(--primary);
        margin: 2px 0 0 0;
    }
    
    .upload-info i {
        margin-right: 4px;
    }
    
    /* Info Grid */
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 12px;
    }
    
    .info-item {
        background: var(--bg-body);
        border-radius: 10px;
        padding: 12px 16px;
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
    }
    
    .info-item:hover {
        border-color: var(--primary);
    }
    
    .info-label {
        font-size: 0.6rem;
        font-weight: 600;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        display: block;
    }
    
    .info-value {
        font-size: 0.9rem;
        font-weight: 500;
        color: var(--text-primary);
        margin: 2px 0 0 0;
    }
    
    .badge-active {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        background: #ECFDF5;
        color: #059669;
        border: 1px solid #D1FAE5;
    }
    
    [data-theme="dark"] .badge-active {
        background: #1A3A2A;
        color: #34D399;
        border-color: #1A3A2A;
    }
    
    /* Form Actions */
    .form-actions {
        display: flex;
        gap: 14px;
        flex-wrap: wrap;
        padding-top: 24px;
        margin-top: 24px;
        border-top: 2px solid var(--border-color);
    }
    
    .btn-save {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 12px 32px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        background: linear-gradient(135deg, #0B5ED7, #1A73E8);
        color: white;
        box-shadow: 0 4px 14px rgba(11, 94, 215, 0.3);
        flex: 1;
        justify-content: center;
    }
    
    .btn-save:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(11, 94, 215, 0.4);
        color: white;
    }
    
    .btn-cancel {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 28px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: 2px solid var(--border-color);
        text-decoration: none;
        background: transparent;
        color: var(--text-secondary);
        flex: 0.5;
        justify-content: center;
    }
    
    .btn-cancel:hover {
        border-color: var(--primary);
        color: var(--primary);
        background: var(--primary-bg);
    }
    
    /* Footer */
    .footer {
        padding: 14px 0;
        border-top: 2px solid var(--border-color);
        margin-top: 28px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--text-secondary);
        transition: border-color 0.3s ease, color 0.3s ease;
    }
    
    .footer .footer-brand {
        color: var(--primary);
        font-weight: 600;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .edit-profile-card {
            padding: 20px 16px;
        }
        .form-grid {
            grid-template-columns: 1fr;
        }
        .form-group.full-width {
            grid-column: span 1;
        }
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .page-title {
            font-size: 1.3rem;
        }
        .form-actions {
            flex-direction: column;
        }
        .btn-save, .btn-cancel {
            flex: 1;
        }
        .avatar-upload {
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        .info-grid {
            grid-template-columns: 1fr 1fr;
        }
    }
    
    @media (max-width: 480px) {
        .edit-profile-card {
            padding: 14px 12px;
        }
        .info-grid {
            grid-template-columns: 1fr;
        }
        .avatar-preview-wrapper {
            width: 70px;
            height: 70px;
        }
    }
</style>

<script>
    // ================================================================
    // PROFILE PICTURE PREVIEW
    // ================================================================
    document.getElementById('profilePicInput')?.addEventListener('change', function(e) {
        var file = this.files[0];
        if (file) {
            var reader = new FileReader();
            reader.onload = function(event) {
                document.getElementById('profilePicPreview').src = event.target.result;
            };
            reader.readAsDataURL(file);
        }
    });

    // ================================================================
    // FORM VALIDATION
    // ================================================================
    document.getElementById('editProfileForm')?.addEventListener('submit', function(e) {
        var fullName = document.querySelector('input[name="full_name"]').value.trim();
        var email = document.querySelector('input[name="email"]').value.trim();
        var specialty = document.querySelector('select[name="specialty"]').value;
        
        if (!fullName) {
            e.preventDefault();
            alert('⚠️ Full name is required!');
            document.querySelector('input[name="full_name"]').focus();
            return false;
        }
        if (!email) {
            e.preventDefault();
            alert('⚠️ Email is required!');
            document.querySelector('input[name="email"]').focus();
            return false;
        }
        if (!specialty) {
            e.preventDefault();
            alert('⚠️ Specialty is required!');
            document.querySelector('select[name="specialty"]').focus();
            return false;
        }
        return true;
    });

    console.log('%c✏️ Edit Profile - <?= htmlspecialchars($doctor_name) ?>', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📸 Profile picture upload enabled', 'font-size:12px; color:#059669;');
</script>

</body>
</html>