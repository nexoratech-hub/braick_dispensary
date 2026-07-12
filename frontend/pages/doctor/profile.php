<?php
// ================================================================
// FILE: frontend/pages/doctor/profile.php
// DOCTOR - MY PROFILE (BEAUTIFUL CSS)
// BRAICK DISPENSARY
// ================================================================

session_start();

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
    $_SESSION['status'] = 'active';
}

$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['full_name'] ?? 'Doctor';
$doctor_username = $_SESSION['username'] ?? 'doctor';
$doctor_email = $_SESSION['email'] ?? 'No email';
$doctor_phone = $_SESSION['phone'] ?? 'No phone';
$doctor_specialty = $_SESSION['specialty'] ?? 'General Practitioner';
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;
$doctor_status = $_SESSION['status'] ?? 'active';

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
// GET STATISTICS
// ================================================================
$stmt = $db->prepare("SELECT COUNT(DISTINCT patient_id) as patients FROM visits WHERE doctor_id = ?");
$stmt->execute([$doctor_id]);
$patients_count = $stmt->fetch(PDO::FETCH_ASSOC)['patients'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as visits FROM visits WHERE doctor_id = ?");
$stmt->execute([$doctor_id]);
$visits_count = $stmt->fetch(PDO::FETCH_ASSOC)['visits'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as prescriptions FROM prescriptions WHERE doctor_id = ?");
$stmt->execute([$doctor_id]);
$prescriptions_count = $stmt->fetch(PDO::FETCH_ASSOC)['prescriptions'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as appointments FROM appointments WHERE doctor_id = ?");
$stmt->execute([$doctor_id]);
$appointments_count = $stmt->fetch(PDO::FETCH_ASSOC)['appointments'] ?? 0;

// ================================================================
// GET LAST ONLINE
// ================================================================
$last_online = 'Never';
try {
    $stmt = $db->prepare("SELECT last_online FROM users WHERE id = ?");
    $stmt->execute([$doctor_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user_data && $user_data['last_online']) {
        $last_online = date('M d, Y h:i A', strtotime($user_data['last_online']));
    }
} catch (Exception $e) {
    $last_online = 'Never';
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

    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-6">
        <div>
            <h1 class="page-title">
                <i class="fas fa-user-circle mr-2" style="color: #0B5ED7;"></i> My Profile
            </h1>
            <p class="page-subtitle">
                View your profile information
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor_branch_name) ?>
                </span>
            </p>
        </div>
        <a href="edit_profile.php" class="btn btn-edit">
            <i class="fas fa-edit"></i> Edit Profile
        </a>
    </div>

    <!-- Profile Card -->
    <div class="profile-card">
        <div class="profile-header">
            <div class="profile-picture-wrapper">
                <img src="<?= $profile_pic_path ?>" alt="Profile Picture" class="profile-picture"
                     onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22120%22 height=%22120%22%3E%3Crect width=%22120%22 height=%22120%22 fill=%22%230B5ED7%22 rx=%2260%22/%3E%3Ctext x=%2260%22 y=%2272%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2248%22 font-weight=%22bold%22%3E<?= strtoupper(substr($doctor_name, 0, 1)) ?>%3C/text%3E%3C/svg%3E'">
            </div>
            <div class="profile-info">
                <h2 class="profile-name"><?= htmlspecialchars($doctor_name) ?></h2>
                <p class="profile-specialty"><?= htmlspecialchars($doctor_specialty) ?></p>
                <div class="profile-badges">
                    <span class="badge-status <?= $doctor_status === 'active' ? 'active' : 'inactive' ?>">
                        <i class="fas fa-circle"></i> <?= ucfirst($doctor_status) ?>
                    </span>
                    <span class="badge-status online">
                        <i class="fas fa-circle"></i> Online
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
                <span class="detail-value"><?= date('F d, Y', strtotime('2026-07-10')) ?></span>
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
                <h3><?= $patients_count ?></h3>
                <p>Total Patients</p>
            </div>
        </div>
        <div class="stat-box">
            <div class="stat-icon green">
                <i class="fas fa-clinic-medical"></i>
            </div>
            <div class="stat-content">
                <h3><?= $visits_count ?></h3>
                <p>Total Visits</p>
            </div>
        </div>
        <div class="stat-box">
            <div class="stat-icon purple">
                <i class="fas fa-prescription"></i>
            </div>
            <div class="stat-content">
                <h3><?= $prescriptions_count ?></h3>
                <p>Prescriptions</p>
            </div>
        </div>
        <div class="stat-box">
            <div class="stat-icon orange">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="stat-content">
                <h3><?= $appointments_count ?></h3>
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
            Logged in as: <strong><?= htmlspecialchars($doctor_name) ?></strong>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<style>
    /* ================================================================
       PROFILE PAGE - BEAUTIFUL CSS
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
    [data-theme="dark"] .badge-status.doctor {
        background: #2A1A3A;
        color: #9B4DCA;
        border-color: #2A1A3A;
    }
    
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
    
    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
    
    /* Buttons */
    .btn-edit {
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
        background: linear-gradient(135deg, #0B5ED7, #1A73E8);
        color: white;
        box-shadow: 0 4px 14px rgba(11, 94, 215, 0.3);
    }
    
    .btn-edit:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(11, 94, 215, 0.4);
        color: white;
    }
    
    /* Page Header */
    .page-header .page-title {
        color: var(--primary-dark);
        font-size: 1.8rem;
        font-weight: 700;
    }
    
    [data-theme="dark"] .page-header .page-title {
        color: var(--primary-light);
    }
    
    .page-header .page-subtitle {
        color: var(--text-secondary);
        font-size: 0.9rem;
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
    
    /* Footer */
    .footer {
        padding: 14px 0;
        border-top: 2px solid var(--border-color);
        margin-top: 24px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--text-secondary);
        transition: border-color 0.3s ease, color 0.3s ease;
    }
    
    .footer .footer-brand { color: var(--primary); font-weight: 600; }
    
    /* Responsive */
    @media (max-width: 768px) {
        .profile-header {
            flex-direction: column;
            text-align: center;
        }
        .profile-details {
            grid-template-columns: 1fr;
        }
        .profile-picture-wrapper {
            width: 90px;
            height: 90px;
        }
        .profile-name {
            font-size: 1.3rem;
        }
        .profile-badges {
            justify-content: center;
        }
        .stats-grid {
            grid-template-columns: 1fr 1fr;
        }
        .stat-box .stat-content h3 {
            font-size: 1.2rem;
        }
    }
    
    @media (max-width: 480px) {
        .profile-card {
            padding: 18px 16px;
        }
        .stats-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<script>
    console.log('%c👤 Profile - <?= htmlspecialchars($doctor_name) ?>', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📊 Patients: <?= $patients_count ?>', 'font-size:12px; color:#059669;');
</script>

</body>
</html>