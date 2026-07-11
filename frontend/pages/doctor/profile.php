<?php
// ================================================================
// FILE: frontend/pages/doctor/profile.php
// DOCTOR - PROFILE
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

// ================================================================
// VARIABLES FOR SIDEBAR
// ================================================================
$selected_branch_id = $doctor_branch_id;
$total_employees = 0;
$total_doctors = 0;
$total_branches = 0;
$pending_lab_tests = 0;
$pending_prescriptions = 0;

// ================================================================
// INCLUDE HEADER & SIDEBAR
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
                View and manage your profile
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor_branch_name) ?>
                </span>
            </p>
        </div>
        <a href="edit_profile.php" class="btn btn-blue btn-sm">
            <i class="fas fa-edit"></i> Edit Profile
        </a>
    </div>

    <!-- Profile Card -->
    <div class="card max-w-2xl mx-auto">
        <div class="flex flex-col items-center mb-6">
            <!-- Avatar -->
            <div class="profile-avatar mb-4" style="background: <?= getUserColor($doctor_name) ?>;">
                <?= strtoupper(substr($doctor_name, 0, 1)) ?>
            </div>
            <h2 class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($doctor_name) ?></h2>
            <p class="text-gray-500"><?= htmlspecialchars($doctor_specialty) ?></p>
        </div>

        <!-- Profile Details -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <p class="text-sm text-gray-500">Username</p>
                <p class="font-medium text-gray-800"><?= htmlspecialchars($doctor_username) ?></p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Email</p>
                <p class="font-medium text-gray-800"><?= htmlspecialchars($doctor_email) ?></p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Phone</p>
                <p class="font-medium text-gray-800"><?= htmlspecialchars($doctor_phone) ?></p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Branch</p>
                <p class="font-medium text-gray-800"><?= htmlspecialchars($doctor_branch_name) ?></p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Role</p>
                <p class="font-medium text-gray-800"><span class="badge badge-info">Doctor</span></p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Status</p>
                <p class="font-medium text-gray-800"><span class="badge badge-success">Active</span></p>
            </div>
        </div>
    </div>

    <!-- Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
        <div class="card text-center">
            <p class="text-3xl font-bold text-blue-600"><?= $patients_count ?></p>
            <p class="text-sm text-gray-500">Total Patients</p>
        </div>
        <div class="card text-center">
            <p class="text-3xl font-bold text-green-600"><?= $visits_count ?></p>
            <p class="text-sm text-gray-500">Total Visits</p>
        </div>
        <div class="card text-center">
            <p class="text-3xl font-bold text-purple-600"><?= $prescriptions_count ?></p>
            <p class="text-sm text-gray-500">Prescriptions</p>
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
    .max-w-2xl { max-width: 42rem; }
    .mx-auto { margin-left: auto; margin-right: auto; }
    .profile-avatar {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
        font-weight: 700;
        color: white;
        flex-shrink: 0;
    }
    .badge { padding: 3px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; color: white; border: none; }
    .badge-success { background: var(--green); }
    .badge-info { background: var(--primary); }
    .text-blue-600 { color: var(--primary); }
    .text-green-600 { color: var(--green); }
    .text-purple-600 { color: var(--purple); }
    [data-theme="dark"] .text-blue-600 { color: #6EA8FE; }
    [data-theme="dark"] .text-green-600 { color: #34D399; }
    [data-theme="dark"] .text-purple-600 { color: #9B4DCA; }
</style>

<script>
    function getUserColor(name) {
        var colors = ['#0B5ED7', '#059669', '#7C3AED', '#DC2626', '#D97706', '#0D9488', '#DB2777'];
        var index = 0;
        for (var i = 0; i < name.length; i++) {
            index = (index + name.charCodeAt(i)) % colors.length;
        }
        return colors[index];
    }
    console.log('%c👨‍⚕️ Profile - <?= htmlspecialchars($doctor_name) ?>', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
</script>

</body>
</html>