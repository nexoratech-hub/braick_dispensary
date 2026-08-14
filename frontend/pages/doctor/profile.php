<?php
// ================================================================
// FILE: frontend/pages/doctor/profile.php
// DOCTOR - MY PROFILE (BEAUTIFUL CSS)
// Session-based login (NO BYPASS)
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// CHECK SESSION - REDIRECT TO LOGIN IF NOT DOCTOR
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Location: /dispensary_system/frontend/pages/login.php');
    exit;
}

// ================================================================
// GET DOCTOR DATA FROM SESSION
// ================================================================
$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['full_name'] ?? 'Dr. Unknown';
$doctor_username = $_SESSION['username'] ?? 'doctor';
$doctor_email = $_SESSION['email'] ?? 'No email';
$doctor_phone = $_SESSION['phone'] ?? 'No phone';
$doctor_specialty = $_SESSION['specialty'] ?? 'General Practitioner';
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;
$doctor_status = $_SESSION['status'] ?? 'active';
$is_online = $_SESSION['is_online'] ?? 0;
$profile_pic = $_SESSION['profile_pic'] ?? '';

// ================================================================
// INCLUDE DATABASE
// ================================================================
$db_path = 'C:/xampp/htdocs/dispensary_system/backend/config/database.php';
if (file_exists($db_path)) {
    require_once $db_path;
} else {
    die("❌ Database file not found at: " . $db_path);
}

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================================================================
// VERIFY DOCTOR EXISTS AND IS ACTIVE
// ================================================================
try {
    $stmt = $db->prepare("SELECT id, full_name, username, email, phone, specialty, branch_id, status, is_online, profile_pic, last_online FROM users WHERE id = ? AND role = 'doctor'");
    $stmt->execute([$doctor_id]);
    $doctor_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$doctor_data || $doctor_data['status'] !== 'active') {
        session_destroy();
        header('Location: /dispensary_system/frontend/pages/login.php');
        exit;
    }
    
    // Update session with latest data
    $doctor_name = $doctor_data['full_name'];
    $doctor_username = $doctor_data['username'];
    $doctor_email = $doctor_data['email'];
    $doctor_phone = $doctor_data['phone'] ?? 'No phone';
    $doctor_specialty = $doctor_data['specialty'] ?? 'General Practitioner';
    $doctor_branch_id = $doctor_data['branch_id'] ?? 1;
    $doctor_status = $doctor_data['status'];
    $is_online = $doctor_data['is_online'] ?? 0;
    $profile_pic = $doctor_data['profile_pic'] ?? '';
    $last_online_db = $doctor_data['last_online'] ?? null;
    
    $_SESSION['full_name'] = $doctor_name;
    $_SESSION['username'] = $doctor_username;
    $_SESSION['email'] = $doctor_email;
    $_SESSION['phone'] = $doctor_phone;
    $_SESSION['specialty'] = $doctor_specialty;
    $_SESSION['branch_id'] = $doctor_branch_id;
    $_SESSION['status'] = $doctor_status;
    $_SESSION['is_online'] = $is_online;
    $_SESSION['profile_pic'] = $profile_pic;
    
} catch (Exception $e) {
    error_log("Profile verification error: " . $e->getMessage());
}

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
$profile_pic_path = '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';
if (!empty($profile_pic)) {
    $file_path = $_SERVER['DOCUMENT_ROOT'] . '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic;
    if (file_exists($file_path)) {
        $profile_pic_path = '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic;
    }
}

// ================================================================
// GET LAST ONLINE
// ================================================================
$last_online = 'Never';
if (!empty($last_online_db)) {
    $last_online = date('M d, Y h:i A', strtotime($last_online_db));
} elseif (!empty($doctor_data['last_online'])) {
    $last_online = date('M d, Y h:i A', strtotime($doctor_data['last_online']));
}

// ================================================================
// GET STATISTICS
// ================================================================
$patients_count = 0;
$visits_count = 0;
$prescriptions_count = 0;
$appointments_count = 0;

try {
    $stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as patients FROM visits WHERE doctor_id = ?");
    $stmt->execute([$doctor_id]);
    $patients_count = $stmt->fetch(PDO::FETCH_ASSOC)['patients'] ?? 0;
} catch (Exception $e) {}

try {
    $stmt = $db->prepare("SELECT COUNT(*) as visits FROM visits WHERE doctor_id = ?");
    $stmt->execute([$doctor_id]);
    $visits_count = $stmt->fetch(PDO::FETCH_ASSOC)['visits'] ?? 0;
} catch (Exception $e) {}

try {
    $stmt = $db->prepare("SELECT COUNT(*) as prescriptions FROM prescriptions WHERE doctor_id = ?");
    $stmt->execute([$doctor_id]);
    $prescriptions_count = $stmt->fetch(PDO::FETCH_ASSOC)['prescriptions'] ?? 0;
} catch (Exception $e) {}

try {
    $stmt = $db->prepare("SELECT COUNT(*) as appointments FROM appointments WHERE doctor_id = ?");
    $stmt->execute([$doctor_id]);
    $appointments_count = $stmt->fetch(PDO::FETCH_ASSOC)['appointments'] ?? 0;
} catch (Exception $e) {}

// ================================================================
// MEMBER SINCE
// ================================================================
$member_since = date('F d, Y');
try {
    $stmt = $db->prepare("SELECT created_at FROM users WHERE id = ?");
    $stmt->execute([$doctor_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user_data && !empty($user_data['created_at'])) {
        $member_since = date('F d, Y', strtotime($user_data['created_at']));
    }
} catch (Exception $e) {}

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
                <i class="fas fa-user-circle mr-2" style="color: #0B5ED7;"></i> My Profile
            </h1>
            <p class="page-subtitle">
                View your profile information
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor_branch_name) ?>
                </span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-user-md mr-1"></i> Doctor
                </span>
            </p>
        </div>
        <div class="page-header-right">
            <a href="edit_profile.php" class="btn btn-edit">
                <i class="fas fa-edit"></i> Edit Profile
            </a>
            <a href="dashboard.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- Profile Card -->
    <div class="profile-card">
        <div class="profile-header">
            <div class="profile-picture-wrapper">
                <?php 
                    $initial = strtoupper(substr($doctor_name, 0, 1));
                    $colors = ['#0B5ED7', '#059669', '#7C3AED', '#DC2626', '#D97706', '#0D9488', '#DB2777'];
                    $color = $colors[abs(crc32($doctor_name)) % count($colors)];
                ?>
                <?php if (file_exists($_SERVER['DOCUMENT_ROOT'] . $profile_pic_path) && !empty($profile_pic)): ?>
                    <img src="<?= $profile_pic_path ?>" alt="Profile Picture" class="profile-picture">
                <?php else: ?>
                    <div class="profile-avatar" style="background: <?= $color ?>; width: 120px; height: 120px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 3rem; font-weight: 700; color: white;">
                        <?= $initial ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="profile-info">
                <h2 class="profile-name"><?= htmlspecialchars($doctor_name) ?></h2>
                <p class="profile-specialty"><?= htmlspecialchars($doctor_specialty) ?></p>
                <div class="profile-badges">
                    <span class="badge-status <?= $doctor_status === 'active' ? 'active' : 'inactive' ?>">
                        <i class="fas fa-circle"></i> <?= ucfirst($doctor_status) ?>
                    </span>
                    <span class="badge-status <?= $is_online ? 'online' : 'offline' ?>">
                        <i class="fas fa-circle"></i> <?= $is_online ? 'Online' : 'Offline' ?>
                    </span>
                    <span class="badge-status doctor">
                        <i class="fas fa-user-md"></i> Doctor
                    </span>
                </div>
            </div>
        </div>

        <div class="profile-details">
            <div class="detail-item">
                <span class="detail-label"><i class="fas fa-user"></i> Username</span>
                <span class="detail-value"><?= htmlspecialchars($doctor_username) ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label"><i class="fas fa-envelope"></i> Email</span>
                <span class="detail-value"><?= htmlspecialchars($doctor_email) ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label"><i class="fas fa-phone"></i> Phone</span>
                <span class="detail-value"><?= htmlspecialchars($doctor_phone) ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label"><i class="fas fa-store-alt"></i> Branch</span>
                <span class="detail-value"><?= htmlspecialchars($doctor_branch_name) ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label"><i class="fas fa-stethoscope"></i> Specialty</span>
                <span class="detail-value"><?= htmlspecialchars($doctor_specialty) ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label"><i class="fas fa-id-badge"></i> Doctor ID</span>
                <span class="detail-value">#<?= htmlspecialchars($doctor_id) ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label"><i class="fas fa-clock"></i> Last Online</span>
                <span class="detail-value"><?= $last_online ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label"><i class="fas fa-calendar-plus"></i> Member Since</span>
                <span class="detail-value"><?= $member_since ?></span>
            </div>
        </div>
    </div>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-box">
            <div class="stat-icon blue">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-content">
                <h3><?= number_format($patients_count) ?></h3>
                <p>Total Patients</p>
            </div>
        </div>
        <div class="stat-box">
            <div class="stat-icon green">
                <i class="fas fa-clinic-medical"></i>
            </div>
            <div class="stat-content">
                <h3><?= number_format($visits_count) ?></h3>
                <p>Total Visits</p>
            </div>
        </div>
        <div class="stat-box">
            <div class="stat-icon purple">
                <i class="fas fa-prescription"></i>
            </div>
            <div class="stat-content">
                <h3><?= number_format($prescriptions_count) ?></h3>
                <p>Prescriptions</p>
            </div>
        </div>
        <div class="stat-box">
            <div class="stat-icon orange">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="stat-content">
                <h3><?= number_format($appointments_count) ?></h3>
                <p>Appointments</p>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            My Profile
            <span class="text-gray-300 mx-2">|</span>
            <?= htmlspecialchars($doctor_name) ?>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- FULL CSS -->
<!-- ================================================================ -->
<style>
    /* ================================================================
       ROOT VARIABLES - LIGHT & DARK MODE
       ================================================================ */
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
        --orange: #EA580C;
        --green: #059669;
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
        --bg-body: #F1F5F9;
        --bg-card: #FFFFFF;
        --bg-nav: #FFFFFF;
        --text-primary: #1E293B;
        --text-secondary: #64748B;
        --border-color: #E2E8F0;
        --shadow: 0 1px 3px rgba(0,0,0,0.08);
        --shadow-md: 0 4px 12px rgba(0,0,0,0.07);
        --shadow-lg: 0 8px 25px rgba(0,0,0,0.1);
    }
    
    [data-theme="dark"] {
        --bg-body: #0F172A;
        --bg-card: #1E293B;
        --bg-nav: #1E293B;
        --text-primary: #F1F5F9;
        --text-secondary: #94A3B8;
        --border-color: #334155;
        --shadow: 0 1px 3px rgba(0,0,0,0.3);
        --shadow-md: 0 4px 12px rgba(0,0,0,0.3);
        --shadow-lg: 0 8px 25px rgba(0,0,0,0.4);
    }
    
    /* ================================================================
       BASE STYLES
       ================================================================ */
    * { margin: 0; padding: 0; box-sizing: border-box; }
    
    body {
        font-family: 'Inter', 'Segoe UI', sans-serif;
        background: var(--bg-body);
        color: var(--text-primary);
        transition: background 0.3s ease, color 0.3s ease;
    }
    
    ::-webkit-scrollbar { width: 5px; height: 5px; }
    ::-webkit-scrollbar-track { background: var(--bg-body); }
    ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
    
    /* ================================================================
       MAIN CONTENT
       ================================================================ */
    .main-content {
        margin-left: 270px;
        margin-top: 68px;
        padding: 24px 28px;
        min-height: calc(100vh - 68px);
        transition: all 0.3s ease;
        background: var(--bg-body);
    }
    
    /* ================================================================
       PAGE HEADER
       ================================================================ */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 16px;
        margin-bottom: 24px;
        padding-bottom: 16px;
        border-bottom: 3px solid var(--primary);
    }
    
    .page-title {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--text-primary);
    }
    
    .page-title i { color: var(--primary); }
    
    .page-subtitle {
        font-size: 0.9rem;
        color: var(--text-secondary);
        margin-top: 4px;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
    }
    
    .page-header-right {
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
    }
    
    .branch-tag {
        background: #059669;
        color: white;
        padding: 3px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .ml-2 { margin-left: 8px; }
    .mr-2 { margin-right: 8px; }
    .mr-1 { margin-right: 4px; }
    .mx-2 { margin-left: 8px; margin-right: 8px; }
    
    /* ================================================================
       PROFILE CARD
       ================================================================ */
    .profile-card {
        background: var(--bg-card);
        border-radius: 20px;
        padding: 28px 32px;
        border: 2px solid var(--border-color);
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        transition: all 0.3s ease;
        max-width: 48rem;
        margin: 0 auto;
    }
    
    .profile-card:hover {
        border-color: var(--primary);
        box-shadow: 0 8px 30px rgba(11, 94, 215, 0.08);
    }
    
    [data-theme="dark"] .profile-card {
        background: #1E293B;
        border-color: #334155;
    }
    [data-theme="dark"] .profile-card:hover {
        border-color: #6EA8FE;
    }
    
    /* ================================================================
       PROFILE HEADER
       ================================================================ */
    .profile-header {
        display: flex;
        align-items: center;
        gap: 28px;
        padding-bottom: 24px;
        border-bottom: 2px solid var(--border-color);
        margin-bottom: 24px;
        flex-wrap: wrap;
    }
    
    .profile-picture-wrapper {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        overflow: hidden;
        flex-shrink: 0;
        border: 4px solid var(--primary);
        box-shadow: 0 4px 14px rgba(11, 94, 215, 0.2);
    }
    
    .profile-picture {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .profile-info {
        flex: 1;
    }
    
    .profile-name {
        font-size: 1.6rem;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0 0 4px 0;
    }
    
    .profile-specialty {
        font-size: 1rem;
        color: var(--text-secondary);
        margin: 0 0 12px 0;
    }
    
    .profile-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    /* ================================================================
       BADGES
       ================================================================ */
    .badge-status {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    
    .badge-status i {
        font-size: 0.5rem;
    }
    
    .badge-status.active {
        background: #ECFDF5;
        color: #059669;
        border: 1px solid #D1FAE5;
    }
    
    .badge-status.inactive {
        background: #FEE2E2;
        color: #EF4444;
        border: 1px solid #FECACA;
    }
    
    .badge-status.online {
        background: #EFF6FF;
        color: #0B5ED7;
        border: 1px solid #DBEAFE;
    }
    
    .badge-status.offline {
        background: #F1F5F9;
        color: #94A3B8;
        border: 1px solid #E2E8F0;
    }
    
    .badge-status.doctor {
        background: #F3E8FF;
        color: #7C3AED;
        border: 1px solid #E9D5FF;
    }
    
    [data-theme="dark"] .badge-status.active {
        background: #1A3A2A;
        color: #34D399;
        border-color: #1A3A2A;
    }
    [data-theme="dark"] .badge-status.inactive {
        background: #3A1A1A;
        color: #F87171;
        border-color: #3A1A1A;
    }
    [data-theme="dark"] .badge-status.online {
        background: #1E3A5F;
        color: #6EA8FE;
        border-color: #1E3A5F;
    }
    [data-theme="dark"] .badge-status.offline {
        background: #1E293B;
        color: #64748B;
        border-color: #334155;
    }
    [data-theme="dark"] .badge-status.doctor {
        background: #2A1A3A;
        color: #9B4DCA;
        border-color: #2A1A3A;
    }
    
    /* ================================================================
       PROFILE DETAILS
       ================================================================ */
    .profile-details {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px 24px;
    }
    
    .detail-item {
        display: flex;
        flex-direction: column;
        gap: 2px;
        padding: 8px 12px;
        background: var(--bg-body);
        border-radius: 10px;
        transition: all 0.3s ease;
    }
    
    .detail-item:hover {
        background: var(--primary-bg);
    }
    
    .detail-label {
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .detail-label i {
        margin-right: 4px;
        width: 16px;
        color: var(--primary);
    }
    
    .detail-value {
        font-size: 0.95rem;
        font-weight: 500;
        color: var(--text-primary);
    }
    
    [data-theme="dark"] .detail-item {
        background: #0F172A;
    }
    [data-theme="dark"] .detail-item:hover {
        background: #1E3A5F;
    }
    
    /* ================================================================
       STATS GRID
       ================================================================ */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        max-width: 48rem;
        margin: 24px auto 0;
    }
    
    .stat-box {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 18px 20px;
        border: 2px solid var(--border-color);
        display: flex;
        align-items: center;
        gap: 14px;
        transition: all 0.3s ease;
    }
    
    .stat-box:hover {
        border-color: var(--primary);
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.06);
    }
    
    .stat-box .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0;
        color: white;
    }
    
    .stat-box .stat-icon.blue { background: var(--primary); }
    .stat-box .stat-icon.green { background: var(--green); }
    .stat-box .stat-icon.purple { background: var(--purple); }
    .stat-box .stat-icon.orange { background: var(--orange); }
    
    .stat-box .stat-content h3 {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0;
        line-height: 1.2;
    }
    
    .stat-box .stat-content p {
        font-size: 0.7rem;
        color: var(--text-secondary);
        margin: 0;
        font-weight: 500;
    }
    
    [data-theme="dark"] .stat-box {
        background: #1E293B;
        border-color: #334155;
    }
    [data-theme="dark"] .stat-box:hover {
        border-color: #6EA8FE;
    }
    
    /* ================================================================
       BUTTONS
       ================================================================ */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 24px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        min-height: 44px;
    }
    
    .btn-edit {
        background: linear-gradient(135deg, #0B5ED7, #1A73E8);
        color: white;
        box-shadow: 0 4px 14px rgba(11, 94, 215, 0.3);
    }
    
    .btn-edit:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(11, 94, 215, 0.4);
        color: white;
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
        padding: 6px 14px;
        font-size: 0.75rem;
        border-radius: 8px;
        min-height: 34px;
    }
    
    [data-theme="dark"] .btn-edit {
        background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
    }
    
    /* ================================================================
       FOOTER
       ================================================================ */
    .footer {
        padding: 14px 0;
        border-top: 2px solid var(--border-color);
        margin-top: 24px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--text-secondary);
    }
    
    .footer .footer-brand {
        color: var(--primary);
        font-weight: 600;
    }
    
    .text-gray-300 { color: #D1D5DB; }
    
    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 1024px) {
        .main-content { padding: 16px; }
    }
    
    @media (max-width: 768px) {
        .main-content { padding: 12px; }
        .page-header { flex-direction: column; }
        .page-header-right { width: 100%; }
        .page-header-right .btn { flex: 1; justify-content: center; }
        .profile-header { flex-direction: column; text-align: center; }
        .profile-picture-wrapper { width: 90px; height: 90px; }
        .profile-name { font-size: 1.3rem; }
        .profile-badges { justify-content: center; }
        .profile-details { grid-template-columns: 1fr; }
        .stats-grid { grid-template-columns: 1fr 1fr; }
        .stat-box .stat-content h3 { font-size: 1.2rem; }
        .profile-card { padding: 18px 16px; }
    }
    
    @media (max-width: 480px) {
        .main-content { padding: 8px; }
        .profile-card { padding: 14px 12px; }
        .stats-grid { grid-template-columns: 1fr; }
        .page-title { font-size: 1.2rem; }
        .profile-picture-wrapper { width: 70px; height: 70px; }
        .profile-name { font-size: 1.1rem; }
        .btn { font-size: 0.75rem; padding: 8px 16px; min-height: 36px; }
        .stat-box { padding: 12px 14px; }
        .stat-box .stat-icon { width: 40px; height: 40px; font-size: 1rem; }
        .stat-box .stat-content h3 { font-size: 1rem; }
    }
</style>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE - SYNC WITH HEADER
    // ================================================================
    document.addEventListener('darkModeChanged', function(e) {
        var isDark = e.detail && e.detail.isDark;
        var html = document.documentElement;
        
        if (isDark) {
            html.setAttribute('data-theme', 'dark');
        } else {
            html.removeAttribute('data-theme');
        }
    });
    
    if (localStorage.getItem('darkMode') === 'true') {
        document.documentElement.setAttribute('data-theme', 'dark');
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

    console.log('%c👤 Profile - <?= htmlspecialchars($doctor_name) ?>', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c🔐 Session-based login active', 'font-size:12px; color:#34D399;');
    console.log('%c📊 Patients: <?= $patients_count ?> | Visits: <?= $visits_count ?>', 'font-size:12px; color:#059669;');
    console.log('%c💊 Prescriptions: <?= $prescriptions_count ?> | Appointments: <?= $appointments_count ?>', 'font-size:12px; color:#64748B;');
    console.log('%c📋 Doctor ID: #<?= $doctor_id ?>', 'font-size:12px; color:#0B5ED7;');
</script>

</body>
</html>