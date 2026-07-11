<?php
// ================================================================
// FILE: frontend/pages/doctor/patient_details.php
// DOCTOR - PATIENT DETAILS
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
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;

// ================================================================
// GET PATIENT ID
// ================================================================
$patient_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($patient_id <= 0) {
    header('Location: my_patients.php');
    exit;
}

// ================================================================
// FUNCTIONS
// ================================================================
function time_ago($timestamp) {
    if (empty($timestamp)) return 'N/A';
    $time = strtotime($timestamp);
    if ($time === false) return 'N/A';
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    if ($diff < 2592000) return floor($diff / 604800) . 'w ago';
    return date('M d, Y', $time);
}

function getUserColor($name) {
    $colors = ['#0B5ED7', '#059669', '#7C3AED', '#DC2626', '#D97706', '#0D9488', '#DB2777'];
    $index = 0;
    for ($i = 0; $i < strlen($name); $i++) {
        $index = ($index + ord($name[$i])) % count($colors);
    }
    return $colors[$index];
}

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
// GET PATIENT DETAILS
// ================================================================
$stmt = $db->prepare("SELECT * FROM patients WHERE id = ?");
$stmt->execute([$patient_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    header('Location: my_patients.php');
    exit;
}

// ================================================================
// GET PATIENT VISITS
// ================================================================
$stmt = $db->prepare("
    SELECT v.*, u.full_name as doctor_name 
    FROM visits v
    LEFT JOIN users u ON v.doctor_id = u.id
    WHERE v.patient_id = ?
    ORDER BY v.created_at DESC
");
$stmt->execute([$patient_id]);
$visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET PATIENT PRESCRIPTIONS
// ================================================================
$stmt = $db->prepare("
    SELECT pr.*, u.full_name as doctor_name 
    FROM prescriptions pr
    LEFT JOIN users u ON pr.doctor_id = u.id
    WHERE pr.patient_id = ?
    ORDER BY pr.created_at DESC
");
$stmt->execute([$patient_id]);
$prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

    <!-- Page Header -->
    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-6">
        <div>
            <h1 class="page-title">
                <i class="fas fa-user-injured mr-2" style="color: #0B5ED7;"></i> Patient Details
            </h1>
            <p class="page-subtitle">
                View patient information and history
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor_branch_name) ?>
                </span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-user mr-1"></i> <?= htmlspecialchars($patient['full_name']) ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="new_visit.php?patient_id=<?= $patient_id ?>" class="btn btn-blue btn-sm">
                <i class="fas fa-plus-circle"></i> New Visit
            </a>
            <a href="prescribe.php?patient_id=<?= $patient_id ?>" class="btn btn-green btn-sm">
                <i class="fas fa-prescription"></i> Prescribe
            </a>
            <a href="my_patients.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- Patient Information -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <div class="card lg:col-span-2">
            <h3 class="card-title mb-4"><i class="fas fa-info-circle title-blue mr-2"></i> Personal Information</h3>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-500">Full Name</p>
                    <p class="font-medium text-gray-800"><?= htmlspecialchars($patient['full_name']) ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Patient ID</p>
                    <p class="font-medium text-gray-800 font-mono"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Gender</p>
                    <p class="font-medium text-gray-800"><?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Date of Birth</p>
                    <p class="font-medium text-gray-800"><?= htmlspecialchars($patient['date_of_birth'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Phone</p>
                    <p class="font-medium text-gray-800"><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Email</p>
                    <p class="font-medium text-gray-800"><?= htmlspecialchars($patient['email'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Blood Group</p>
                    <p class="font-medium text-gray-800"><?= htmlspecialchars($patient['blood_group'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Status</p>
                    <p><span class="badge <?= ($patient['status'] ?? 'active') === 'active' ? 'badge-success' : 'badge-danger' ?>"><?= ucfirst($patient['status'] ?? 'Active') ?></span></p>
                </div>
                <div class="col-span-2">
                    <p class="text-sm text-gray-500">Address</p>
                    <p class="font-medium text-gray-800"><?= htmlspecialchars($patient['address'] ?? 'N/A') ?></p>
                </div>
                <div class="col-span-2">
                    <p class="text-sm text-gray-500">Allergies</p>
                    <p class="font-medium text-gray-800"><?= htmlspecialchars($patient['allergies'] ?? 'None') ?></p>
                </div>
            </div>
        </div>
        <div class="card">
            <h3 class="card-title mb-4"><i class="fas fa-chart-bar title-blue mr-2"></i> Statistics</h3>
            <div class="space-y-4">
                <div class="text-center p-4 bg-blue-50 rounded-xl">
                    <p class="text-3xl font-bold text-blue-600"><?= count($visits) ?></p>
                    <p class="text-sm text-gray-500">Total Visits</p>
                </div>
                <div class="text-center p-4 bg-green-50 rounded-xl">
                    <p class="text-3xl font-bold text-green-600"><?= count($prescriptions) ?></p>
                    <p class="text-sm text-gray-500">Prescriptions</p>
                </div>
                <div class="text-center p-4 bg-purple-50 rounded-xl">
                    <p class="text-3xl font-bold text-purple-600">0</p>
                    <p class="text-sm text-gray-500">Lab Tests</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Visits History -->
    <div class="card mb-6">
        <h3 class="card-title"><i class="fas fa-history title-blue mr-2"></i> Visit History</h3>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Visit Date</th>
                        <th>Visit Number</th>
                        <th>Doctor</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($visits) > 0): ?>
                        <?php foreach ($visits as $visit): ?>
                            <tr>
                                <td><?= date('M d, Y h:i A', strtotime($visit['created_at'])) ?></td>
                                <td class="font-mono text-xs"><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($visit['doctor_name'] ?? 'Unknown') ?></td>
                                <td><span class="badge badge-info"><?= ucfirst($visit['status'] ?? 'Pending') ?></span></td>
                                <td>
                                    <a href="view_visit.php?id=<?= $visit['id'] ?>" class="btn btn-view btn-sm">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center py-8 text-gray-400">No visits recorded</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Patient Details
            <span class="text-gray-300 mx-2">|</span>
            Logged in as: <strong><?= htmlspecialchars($doctor_name) ?></strong>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<style>
    .badge { padding: 3px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; color: white; border: none; }
    .badge-success { background: var(--green); }
    .badge-danger { background: var(--red); }
    .badge-info { background: var(--primary); }
    .table-wrap { overflow-x: auto; }
    .data-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
    .data-table thead th { text-align: left; padding: 10px 14px; font-weight: 700; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; color: white; background: var(--primary); border-bottom: 3px solid var(--primary-dark); white-space: nowrap; }
    .data-table tbody tr:nth-child(even) { background: var(--primary-bg); }
    .data-table tbody tr:nth-child(odd) { background: var(--bg-card); }
    .data-table tbody tr:hover { background: var(--green-bg); }
    .data-table td { padding: 10px 14px; border-bottom: 1px solid var(--border-color); color: var(--text-primary); vertical-align: middle; }
    .btn-view { background: var(--primary); color: white; padding: 4px 10px; font-size: 0.7rem; border-radius: 6px; border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
    .btn-view:hover { background: var(--primary-dark); transform: scale(1.05); }
    .btn-sm { padding: 4px 10px; font-size: 0.7rem; border-radius: 6px; }
    [data-theme="dark"] .data-table tbody tr:nth-child(even) { background: #1E293B; }
    [data-theme="dark"] .data-table tbody tr:nth-child(odd) { background: #1E293B; }
    [data-theme="dark"] .data-table tbody tr:hover { background: #1A3A2A; }
    [data-theme="dark"] .bg-blue-50 { background: #1E3A5F; }
    [data-theme="dark"] .bg-green-50 { background: #1A3A2A; }
    [data-theme="dark"] .bg-purple-50 { background: #2A1A3A; }
    [data-theme="dark"] .text-blue-600 { color: #6EA8FE; }
    [data-theme="dark"] .text-green-600 { color: #34D399; }
    [data-theme="dark"] .text-purple-600 { color: #9B4DCA; }
</style>

<script>
    console.log('%c👨‍⚕️ Patient Details - <?= htmlspecialchars($patient['full_name']) ?>', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
</script>

</body>
</html>