<?php
// ================================================================
// FILE: frontend/pages/doctor/patient_details.php
// DOCTOR - PATIENT DETAILS (CLEAN CSS)
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
    $_SESSION['profile_pic'] = '';
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

function getStatusBadgeClass($status) {
    switch ($status) {
        case 'completed': return 'badge-success';
        case 'cancelled': return 'badge-danger';
        case 'in_progress': return 'badge-warning';
        case 'pending': return 'badge-warning';
        case 'dispensed': return 'badge-success';
        default: return 'badge-info';
    }
}

function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    $age = $birthDate->diff($today)->y;
    return $age;
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
// GET PATIENT LAB TESTS
// ================================================================
$stmt = $db->prepare("
    SELECT lt.*, 
           u.full_name as doctor_name,
           v.visit_number
    FROM lab_tests lt
    LEFT JOIN visits v ON lt.visit_id = v.id
    LEFT JOIN users u ON lt.doctor_id = u.id
    WHERE v.patient_id = ?
    ORDER BY lt.created_at DESC
");
$stmt->execute([$patient_id]);
$lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                <span class="ml-2 inline-flex bg-purple-100 text-purple-700 px-3 py-1 rounded-full text-xs border border-purple-200">
                    <i class="fas fa-id-card mr-1"></i> <?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <button onclick="window.print()" class="btn btn-outline btn-sm">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="consultation.php?patient_id=<?= $patient_id ?>" class="btn btn-consult btn-sm">
                <i class="fas fa-stethoscope"></i> Consult
            </a>
            <a href="prescribe.php?patient_id=<?= $patient_id ?>" class="btn btn-green btn-sm">
                <i class="fas fa-prescription"></i> Prescribe
            </a>
            <a href="my_patients.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PATIENT INFORMATION -->
    <!-- ================================================================ -->
    <div class="info-grid">
        <div class="info-card">
            <h4 class="info-card-title">
                <i class="fas fa-user text-blue-600"></i> Personal Information
            </h4>
            <div class="info-card-body">
                <div class="info-row">
                    <span class="info-label">Full Name</span>
                    <span class="info-value font-semibold"><?= htmlspecialchars($patient['full_name']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Patient ID</span>
                    <span class="info-value font-mono"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Gender</span>
                    <span class="info-value"><?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Date of Birth</span>
                    <span class="info-value"><?= !empty($patient['date_of_birth']) ? date('M d, Y', strtotime($patient['date_of_birth'])) : 'N/A' ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Age</span>
                    <span class="info-value"><?= calculateAge($patient['date_of_birth'] ?? '') ?> years</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Phone</span>
                    <span class="info-value"><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email</span>
                    <span class="info-value"><?= htmlspecialchars($patient['email'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Blood Group</span>
                    <span class="info-value"><?= htmlspecialchars($patient['blood_group'] ?? 'N/A') ?></span>
                </div>
            </div>
        </div>

        <div class="info-card">
            <h4 class="info-card-title">
                <i class="fas fa-info-circle text-green-600"></i> Additional Information
            </h4>
            <div class="info-card-body">
                <div class="info-row">
                    <span class="info-label">Allergies</span>
                    <span class="info-value"><?= htmlspecialchars($patient['allergies'] ?? 'None') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Emergency Contact</span>
                    <span class="info-value"><?= htmlspecialchars($patient['emergency_contact'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Address</span>
                    <span class="info-value"><?= htmlspecialchars($patient['address'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Branch</span>
                    <span class="info-value"><?= htmlspecialchars($doctor_branch_name) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Registered On</span>
                    <span class="info-value"><?= date('F d, Y h:i A', strtotime($patient['created_at'])) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Status</span>
                    <span class="info-value">
                        <span class="badge <?= ($patient['status'] ?? 'active') === 'active' ? 'badge-success' : 'badge-danger' ?>">
                            <?= ucfirst($patient['status'] ?? 'Active') ?>
                        </span>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATISTICS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-grid">
        <div class="stat-card blue">
            <div>
                <p class="stat-label">Total Visits</p>
                <p class="stat-number"><?= count($visits) ?></p>
            </div>
            <div class="stat-icon"><i class="fas fa-clinic-medical"></i></div>
        </div>
        <div class="stat-card green">
            <div>
                <p class="stat-label">Prescriptions</p>
                <p class="stat-number"><?= count($prescriptions) ?></p>
            </div>
            <div class="stat-icon"><i class="fas fa-prescription"></i></div>
        </div>
        <div class="stat-card purple">
            <div>
                <p class="stat-label">Lab Tests</p>
                <p class="stat-number"><?= count($lab_tests) ?></p>
            </div>
            <div class="stat-icon"><i class="fas fa-flask"></i></div>
        </div>
        <div class="stat-card orange">
            <div>
                <p class="stat-label">Pending</p>
                <p class="stat-number">
                    <?php 
                        $pending = 0;
                        foreach ($visits as $v) {
                            if ($v['status'] === 'pending' || $v['status'] === 'assigned') {
                                $pending++;
                            }
                        }
                        echo $pending;
                    ?>
                </p>
            </div>
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- VISITS HISTORY -->
    <!-- ================================================================ -->
    <div class="result-card">
        <h4 class="result-card-title">
            <i class="fas fa-history text-blue-600 mr-2"></i> Visit History
            <span class="text-sm font-normal text-gray-400">(<?= count($visits) ?> visits)</span>
        </h4>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Visit Date</th>
                        <th>Visit Number</th>
                        <th>Doctor</th>
                        <th>Diagnosis</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($visits) > 0): ?>
                        <?php foreach ($visits as $index => $visit): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= date('M d, Y h:i A', strtotime($visit['created_at'])) ?></td>
                                <td class="font-mono text-xs"><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($visit['doctor_name'] ?? 'Unknown') ?></td>
                                <td class="text-sm"><?= htmlspecialchars(substr($visit['diagnosis'] ?? '', 0, 50)) ?><?= strlen($visit['diagnosis'] ?? '') > 50 ? '...' : '' ?></td>
                                <td><span class="badge <?= getStatusBadgeClass($visit['status']) ?>"><?= ucfirst($visit['status'] ?? 'Pending') ?></span></td>
                                <td>
                                    <a href="view_visit.php?id=<?= $visit['id'] ?>" class="btn btn-view btn-sm">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-8 text-muted">
                                <i class="fas fa-history text-2xl block mb-2"></i>
                                No visits recorded
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PRESCRIPTIONS -->
    <!-- ================================================================ -->
    <div class="result-card">
        <h4 class="result-card-title">
            <i class="fas fa-prescription text-purple-600 mr-2"></i> Prescriptions
            <span class="text-sm font-normal text-gray-400">(<?= count($prescriptions) ?> prescriptions)</span>
        </h4>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Prescription #</th>
                        <th>Doctor</th>
                        <th>Diagnosis</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($prescriptions) > 0): ?>
                        <?php foreach ($prescriptions as $index => $pr): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td class="font-mono text-xs font-semibold text-blue-600"><?= htmlspecialchars($pr['prescription_number']) ?></td>
                                <td><?= htmlspecialchars($pr['doctor_name'] ?? 'Unknown') ?></td>
                                <td class="text-sm"><?= htmlspecialchars(substr($pr['diagnosis'] ?? '', 0, 40)) ?><?= strlen($pr['diagnosis'] ?? '') > 40 ? '...' : '' ?></td>
                                <td><span class="badge <?= getStatusBadgeClass($pr['status']) ?>"><?= ucfirst($pr['status'] ?? 'Pending') ?></span></td>
                                <td><?= date('M d, Y', strtotime($pr['created_at'])) ?></td>
                                <td>
                                    <a href="view_prescription.php?id=<?= $pr['id'] ?>" class="btn btn-view btn-sm">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-8 text-muted">
                                <i class="fas fa-prescription text-2xl block mb-2"></i>
                                No prescriptions recorded
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- LAB TESTS -->
    <!-- ================================================================ -->
    <div class="result-card">
        <h4 class="result-card-title">
            <i class="fas fa-flask text-teal-600 mr-2"></i> Lab Tests
            <span class="text-sm font-normal text-gray-400">(<?= count($lab_tests) ?> tests)</span>
        </h4>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Test Name</th>
                        <th>Type</th>
                        <th>Doctor</th>
                        <th>Visit #</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($lab_tests) > 0): ?>
                        <?php foreach ($lab_tests as $index => $test): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($test['test_type'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($test['doctor_name'] ?? 'Unknown') ?></td>
                                <td class="font-mono text-xs"><?= htmlspecialchars($test['visit_number'] ?? 'N/A') ?></td>
                                <td><span class="badge <?= getStatusBadgeClass($test['status']) ?>"><?= ucfirst($test['status'] ?? 'Pending') ?></span></td>
                                <td><?= date('M d, Y', strtotime($test['created_at'])) ?></td>
                                <td>
                                    <a href="view_test.php?id=<?= $test['id'] ?>" class="btn btn-view btn-sm">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-8 text-muted">
                                <i class="fas fa-flask text-2xl block mb-2"></i>
                                No lab tests recorded
                            </td>
                        </tr>
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
    /* ================================================================
       INFO GRID
       ================================================================ */
    .info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 24px;
    }
    
    .info-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 20px 24px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
    }
    
    .info-card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.08);
    }
    
    .info-card-title {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--text-primary);
        border-bottom: 2px solid var(--border-color);
        padding-bottom: 10px;
        margin-bottom: 14px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .info-card-body {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    
    .info-row {
        display: flex;
        justify-content: space-between;
        padding: 4px 0;
        border-bottom: 1px solid var(--border-color);
    }
    
    .info-row:last-child {
        border-bottom: none;
    }
    
    .info-label {
        font-size: 0.8rem;
        color: var(--text-secondary);
        font-weight: 500;
    }
    
    .info-value {
        font-size: 0.85rem;
        color: var(--text-primary);
        text-align: right;
        max-width: 60%;
        word-break: break-word;
    }
    
    .info-value.font-semibold { font-weight: 600; }
    .info-value.font-mono { font-family: monospace; }
    
    .text-blue-600 { color: var(--primary); }
    .text-green-600 { color: #059669; }
    .text-purple-600 { color: #7C3AED; }
    .text-teal-600 { color: #0D9488; }
    
    /* ================================================================
       STATS GRID
       ================================================================ */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }
    
    .stat-card {
        background: var(--bg-card);
        border-radius: 14px;
        padding: 18px 20px;
        border: 2px solid var(--border-color);
        display: flex;
        align-items: center;
        justify-content: space-between;
        transition: all 0.3s ease;
    }
    
    .stat-card:hover {
        border-color: var(--primary);
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    }
    
    .stat-card .stat-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        color: white;
        flex-shrink: 0;
    }
    
    .stat-card.blue .stat-icon { background: var(--primary); }
    .stat-card.green .stat-icon { background: #059669; }
    .stat-card.purple .stat-icon { background: #7C3AED; }
    .stat-card.orange .stat-icon { background: #D97706; }
    
    .stat-card .stat-number {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
        line-height: 1.2;
    }
    
    .stat-card .stat-label {
        font-size: 0.75rem;
        color: var(--text-secondary);
        font-weight: 500;
        margin-top: 2px;
    }
    
    /* ================================================================
       RESULT CARD
       ================================================================ */
    .result-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 20px 24px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        margin-bottom: 24px;
    }
    
    .result-card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.08);
    }
    
    .result-card-title {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--text-primary);
        border-bottom: 2px solid var(--border-color);
        padding-bottom: 10px;
        margin-bottom: 14px;
        display: flex;
        align-items: center;
    }
    
    /* ================================================================
       TABLE
       ================================================================ */
    .table-wrap {
        overflow-x: auto;
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }
    
    .data-table thead th {
        text-align: left;
        padding: 10px 14px;
        font-weight: 700;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #fff;
        background: var(--primary);
        border-bottom: 3px solid var(--primary-dark);
        white-space: nowrap;
    }
    
    .data-table thead th:first-child {
        border-radius: 8px 0 0 0;
    }
    
    .data-table thead th:last-child {
        border-radius: 0 8px 0 0;
    }
    
    .data-table tbody tr:nth-child(even) {
        background: var(--primary-bg);
    }
    
    .data-table tbody tr:nth-child(odd) {
        background: var(--bg-card);
    }
    
    .data-table tbody tr:hover {
        background: #D1FAE5;
    }
    
    [data-theme="dark"] .data-table tbody tr:hover {
        background: #1A3A2A;
    }
    
    .data-table td {
        padding: 10px 14px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        vertical-align: middle;
    }
    
    .data-table td .font-mono { font-family: monospace; }
    .data-table td .text-xs { font-size: 0.75rem; }
    .data-table td .text-sm { font-size: 0.8rem; }
    .data-table td .text-muted { color: var(--text-muted); }
    .data-table td .font-semibold { font-weight: 600; }
    
    /* ================================================================
       BADGES
       ================================================================ */
    .badge {
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: #fff;
        border: none;
    }
    
    .badge-success { background: #059669; }
    .badge-danger { background: #EF4444; }
    .badge-warning { background: #D97706; }
    .badge-info { background: var(--primary); }
    
    /* ================================================================
       BUTTONS
       ================================================================ */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 16px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.78rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
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
    
    .btn-consult {
        background: #7C3AED;
        color: #fff;
        padding: 4px 12px;
        font-size: 0.7rem;
        border-radius: 6px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        border: none;
        cursor: pointer;
    }
    .btn-consult:hover {
        background: #6D28D9;
        transform: scale(1.05);
    }
    
    .btn-green {
        background: #059669;
        color: #fff;
        padding: 4px 12px;
        font-size: 0.7rem;
        border-radius: 6px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        border: none;
        cursor: pointer;
    }
    .btn-green:hover {
        background: #047857;
        transform: scale(1.05);
    }
    
    .btn-view {
        background: var(--primary);
        color: #fff;
        padding: 4px 12px;
        font-size: 0.7rem;
        border-radius: 6px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        border: none;
        cursor: pointer;
    }
    .btn-view:hover {
        background: var(--primary-dark);
        transform: scale(1.05);
    }
    
    .btn-sm {
        padding: 4px 10px;
        font-size: 0.7rem;
        border-radius: 6px;
    }
    
    /* ================================================================
       PAGE HEADER
       ================================================================ */
    .page-header {
        border-bottom: 3px solid var(--primary);
        padding-bottom: 12px;
    }
    
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
    
    /* ================================================================
       FOOTER
       ================================================================ */
    .footer {
        padding: 14px 0;
        border-top: 2px solid var(--border-color);
        margin-top: 20px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--text-secondary);
    }
    
    .footer .footer-brand {
        color: var(--primary);
        font-weight: 600;
    }
    
    /* ================================================================
       UTILITIES
       ================================================================ */
    .text-xs { font-size: 0.75rem; }
    .text-sm { font-size: 0.875rem; }
    .text-muted { color: var(--text-muted); }
    .font-medium { font-weight: 500; }
    .font-semibold { font-weight: 600; }
    .font-mono { font-family: monospace; }
    .text-center { text-align: center; }
    .py-8 { padding-top: 2rem; padding-bottom: 2rem; }
    .text-2xl { font-size: 1.5rem; }
    .text-3xl { font-size: 1.875rem; }
    .block { display: block; }
    .mb-2 { margin-bottom: 0.5rem; }
    .ml-2 { margin-left: 0.5rem; }
    .mr-1 { margin-right: 0.25rem; }
    .mr-2 { margin-right: 0.5rem; }
    .gap-2 { gap: 0.5rem; }
    .gap-3 { gap: 0.75rem; }
    .gap-4 { gap: 1rem; }
    .flex { display: flex; }
    .flex-wrap { flex-wrap: wrap; }
    .items-center { align-items: center; }
    .justify-between { justify-content: space-between; }
    
    /* ================================================================
       DARK MODE
       ================================================================ */
    [data-theme="dark"] .info-card {
        background: #1E293B;
        border-color: #334155;
    }
    [data-theme="dark"] .info-card-title {
        color: #F1F5F9;
        border-color: #334155;
    }
    [data-theme="dark"] .info-row {
        border-color: #334155;
    }
    [data-theme="dark"] .info-value {
        color: #F1F5F9;
    }
    [data-theme="dark"] .stat-card {
        background: #1E293B;
        border-color: #334155;
    }
    [data-theme="dark"] .stat-card .stat-number {
        color: #F1F5F9;
    }
    [data-theme="dark"] .stat-card .stat-label {
        color: #94A3B8;
    }
    [data-theme="dark"] .result-card {
        background: #1E293B;
        border-color: #334155;
    }
    [data-theme="dark"] .result-card-title {
        color: #F1F5F9;
        border-color: #334155;
    }
    [data-theme="dark"] .data-table tbody tr:nth-child(even) {
        background: #1E293B;
    }
    [data-theme="dark"] .data-table tbody tr:nth-child(odd) {
        background: #1E293B;
    }
    [data-theme="dark"] .footer {
        border-color: #334155;
        color: #64748B;
    }
    
    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 768px) {
        .info-grid {
            grid-template-columns: 1fr;
        }
        .stats-grid {
            grid-template-columns: 1fr 1fr;
        }
        .info-card {
            padding: 14px 16px;
        }
        .result-card {
            padding: 14px 16px;
        }
        .data-table {
            font-size: 0.75rem;
        }
        .data-table th,
        .data-table td {
            padding: 6px 10px;
        }
        .btn-sm {
            padding: 3px 8px;
            font-size: 0.6rem;
        }
        .page-header .page-title {
            font-size: 1.2rem;
        }
        .info-row {
            flex-direction: column;
            align-items: flex-start;
        }
        .info-value {
            text-align: left;
            max-width: 100%;
        }
        .stat-card {
            padding: 14px 16px;
        }
        .stat-card .stat-number {
            font-size: 1.2rem;
        }
    }
    
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
        .data-table th,
        .data-table td {
            padding: 4px 6px;
            font-size: 0.7rem;
        }
        .btn-sm {
            padding: 2px 6px;
            font-size: 0.55rem;
        }
        .info-card {
            padding: 10px 12px;
        }
        .result-card {
            padding: 10px 12px;
        }
        .page-header .page-title {
            font-size: 1rem;
        }
        .page-header .page-subtitle {
            font-size: 0.75rem;
        }
    }
    
    @media print {
        .top-nav, .sidebar, .btn, .footer { display: none !important; }
        .main-content { margin: 0 !important; padding: 20px !important; }
        .info-card, .result-card, .stat-card { 
            border: 1px solid #ddd !important; 
            box-shadow: none !important; 
            page-break-inside: avoid;
        }
        .page-header { border-bottom: 2px solid #0B5ED7 !important; }
        .data-table thead th { background: #0B5ED7 !important; color: white !important; }
        .info-card { background: white !important; }
        .result-card { background: white !important; }
        .stat-card { background: white !important; }
    }
</style>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
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
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 3500);
    }

    console.log('%c👨‍⚕️ Patient Details - <?= htmlspecialchars($patient['full_name']) ?>', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📊 Visits: <?= count($visits) ?> | Prescriptions: <?= count($prescriptions) ?> | Lab Tests: <?= count($lab_tests) ?>', 'font-size:12px; color:#059669;');
    console.log('%c✅ Patient Details page loaded', 'font-size:12px; color:#64748B;');
</script>

</body>
</html>