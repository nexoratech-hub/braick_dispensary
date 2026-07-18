<?php
// ================================================================
// FILE: frontend/pages/doctor/view_patient.php
// DOCTOR - VIEW PATIENT COMPLETE HISTORY
// NO QUICK ACTIONS - VIEW ONLY
// FIXED: Paid status - Text only, no background
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// IF NO SESSION, USE DR. JOHN MUSHI (ID: 5) AS DEFAULT
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    $_SESSION['user_id'] = 5;
    $_SESSION['doctor_id'] = 5;
    $_SESSION['full_name'] = 'Dr. John Mushi';
    $_SESSION['username'] = 'dr.john';
    $_SESSION['email'] = 'john@braick.com';
    $_SESSION['phone'] = '+255 700 000 011';
    $_SESSION['role'] = 'doctor';
    $_SESSION['branch_id'] = 1;
    $_SESSION['specialty'] = 'General Medicine';
    $_SESSION['profile_pic'] = '';
    $_SESSION['is_online'] = 1;
}

$doctor_id = $_SESSION['user_id'] ?? 5;
$doctor_name = $_SESSION['full_name'] ?? 'Dr. John Mushi';
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
// INCLUDE DATABASE
// ================================================================
require_once 'C:/xampp/htdocs/dispensary_system/backend/config/database.php';
$db = Database::getInstance()->getConnection();

// ================================================================
// GET PATIENT DETAILS
// ================================================================
$patient = null;
try {
    $stmt = $db->prepare("
        SELECT p.*, 
               b.name as branch_name,
               u.full_name as doctor_name,
               u.specialty as doctor_specialty,
               (SELECT COUNT(*) FROM visits WHERE patient_id = p.id) as total_visits,
               (SELECT COUNT(*) FROM prescriptions WHERE patient_id = p.id) as total_prescriptions,
               (SELECT COUNT(*) FROM lab_tests WHERE patient_id = p.id) as total_lab_tests,
               (SELECT COUNT(*) FROM appointments WHERE patient_id = p.id) as total_appointments
        FROM patients p
        LEFT JOIN branches b ON p.branch_id = b.id
        LEFT JOIN users u ON p.assigned_doctor_id = u.id
        WHERE p.id = ?
    ");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        header('Location: my_patients.php?error=patient_not_found');
        exit;
    }
} catch (Exception $e) {
    header('Location: my_patients.php?error=database');
    exit;
}

// ================================================================
// GET ALL VISITS WITH DETAILS
// ================================================================
$visits = [];
try {
    $stmt = $db->prepare("
        SELECT v.*, 
               u.full_name as doctor_name,
               u.specialty as doctor_specialty,
               (SELECT COUNT(*) FROM prescriptions WHERE visit_id = v.id) as prescriptions_count,
               (SELECT COUNT(*) FROM lab_tests WHERE visit_id = v.id) as lab_tests_count
        FROM visits v
        LEFT JOIN users u ON v.doctor_id = u.id
        WHERE v.patient_id = ?
        ORDER BY v.created_at DESC
    ");
    $stmt->execute([$patient_id]);
    $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $visits = [];
}

// ================================================================
// GET ALL PRESCRIPTIONS WITH MEDICATION NAMES
// ================================================================
$prescriptions = [];
try {
    $stmt = $db->prepare("
        SELECT p.*, 
               u.full_name as doctor_name,
               GROUP_CONCAT(DISTINCT pi.medication_name SEPARATOR ', ') as medications_list,
               COUNT(pi.id) as medications_count
        FROM prescriptions p
        LEFT JOIN users u ON p.doctor_id = u.id
        LEFT JOIN prescription_items pi ON p.id = pi.prescription_id
        WHERE p.patient_id = ?
        GROUP BY p.id
        ORDER BY p.created_at DESC
        LIMIT 30
    ");
    $stmt->execute([$patient_id]);
    $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $prescriptions = [];
}

// ================================================================
// GET ALL LAB TESTS
// ================================================================
$lab_tests = [];
try {
    $stmt = $db->prepare("
        SELECT lt.*, 
               u.full_name as doctor_name,
               lab.full_name as lab_technician_name
        FROM lab_tests lt
        LEFT JOIN users u ON lt.doctor_id = u.id
        LEFT JOIN users lab ON lt.lab_technician_id = lab.id
        WHERE lt.patient_id = ?
        ORDER BY lt.created_at DESC
    ");
    $stmt->execute([$patient_id]);
    $lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $lab_tests = [];
}

// ================================================================
// GET ALL APPOINTMENTS
// ================================================================
$appointments = [];
try {
    $stmt = $db->prepare("
        SELECT a.*, 
               u.full_name as doctor_name,
               u.specialty as doctor_specialty
        FROM appointments a
        LEFT JOIN users u ON a.doctor_id = u.id
        WHERE a.patient_id = ?
        ORDER BY a.appointment_date DESC
    ");
    $stmt->execute([$patient_id]);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $appointments = [];
}

// ================================================================
// GET ALL BILLS
// ================================================================
$bills = [];
try {
    $stmt = $db->prepare("
        SELECT * FROM patient_bills 
        WHERE patient_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$patient_id]);
    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $bills = [];
}

// ================================================================
// GET ALL CONSULTATIONS
// ================================================================
$consultations = [];
try {
    $stmt = $db->prepare("
        SELECT v.id, v.visit_number, v.created_at, v.diagnosis, v.treatment, v.symptoms, v.notes,
               u.full_name as doctor_name,
               (SELECT COUNT(*) FROM prescriptions WHERE visit_id = v.id) as prescriptions_count,
               (SELECT COUNT(*) FROM lab_tests WHERE visit_id = v.id) as lab_tests_count
        FROM visits v
        LEFT JOIN users u ON v.doctor_id = u.id
        WHERE v.patient_id = ? AND v.diagnosis IS NOT NULL AND v.diagnosis != ''
        ORDER BY v.created_at DESC
    ");
    $stmt->execute([$patient_id]);
    $consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $consultations = [];
}

// ================================================================
// GET MEDICATIONS HISTORY
// ================================================================
$medications_history = [];
try {
    $stmt = $db->prepare("
        SELECT DISTINCT pi.medication_name, 
               COUNT(pi.id) as times_prescribed,
               MAX(p.created_at) as last_prescribed
        FROM prescription_items pi
        JOIN prescriptions p ON pi.prescription_id = p.id
        WHERE p.patient_id = ?
        GROUP BY pi.medication_name
        ORDER BY times_prescribed DESC
    ");
    $stmt->execute([$patient_id]);
    $medications_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $medications_history = [];
}

// ================================================================
// GET BRANCH NAME
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
// HELPER FUNCTIONS
// ================================================================
function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}

function getUserColor($name) {
    $colors = ['#0B5ED7', '#059669', '#7C3AED', '#DC2626', '#D97706', '#0D9488', '#DB2777'];
    $index = abs(crc32($name)) % count($colors);
    return $colors[$index];
}

function getStatusBadgeClass($status) {
    $map = [
        'pending' => 'badge-pending',
        'assigned' => 'badge-info',
        'with_doctor' => 'badge-primary',
        'lab_test' => 'badge-warning',
        'prescribed' => 'badge-purple',
        'completed' => 'badge-success',
        'cancelled' => 'badge-danger',
        'scheduled' => 'badge-info',
        'confirmed' => 'badge-success',
        'in-progress' => 'badge-warning',
        'paid' => 'badge-success',
        'partial' => 'badge-warning',
        'dispensed' => 'badge-success'
    ];
    return $map[$status] ?? 'badge-info';
}

function time_ago($timestamp) {
    if (empty($timestamp)) return 'N/A';
    try {
        $time = strtotime($timestamp);
        if ($time === false) return 'N/A';
        $diff = time() - $time;
        if ($diff < 60) return 'Just now';
        if ($diff < 3600) return floor($diff / 60) . 'm ago';
        if ($diff < 86400) return floor($diff / 3600) . 'h ago';
        if ($diff < 604800) return floor($diff / 86400) . 'd ago';
        if ($diff < 2592000) return floor($diff / 604800) . 'w ago';
        return date('M d, Y', $time);
    } catch (Exception $e) {
        return 'N/A';
    }
}

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
    <div class="page-header">
        <div class="page-header-left">
            <h1 class="page-title">
                <i class="fas fa-user-circle"></i> Patient History
                <span class="page-badge"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></span>
            </h1>
            <p class="page-subtitle">
                Complete medical history and patient records
                <span class="separator">|</span>
                <span class="status-badge badge-info">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?>
                </span>
                <span class="separator">|</span>
                <span class="status-badge badge-success">
                    <i class="fas fa-calendar-alt"></i> Registered: <?= date('M d, Y', strtotime($patient['created_at'] ?? 'now')) ?>
                </span>
            </p>
        </div>
        <div class="page-header-right">
            <a href="my_patients.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> My Patients
            </a>
            <button onclick="window.print()" class="btn btn-outline">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PATIENT PROFILE SECTION -->
    <!-- ================================================================ -->
    <div class="patient-profile">
        <div class="patient-avatar-large" style="background: <?= getUserColor($patient['full_name'] ?? 'Unknown') ?>;">
            <?= strtoupper(substr($patient['full_name'] ?? 'U', 0, 1)) ?>
        </div>
        <div class="patient-profile-info">
            <h2 class="patient-name"><?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></h2>
            <div class="patient-meta">
                <span><i class="fas fa-id-card"></i> <?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></span>
                <span><i class="fas fa-venus-mars"></i> <?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></span>
                <span><i class="fas fa-birthday-cake"></i> <?= calculateAge($patient['date_of_birth'] ?? '') ?> years</span>
                <span><i class="fas fa-phone"></i> <?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></span>
                <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($patient['email'] ?? 'N/A') ?></span>
                <span><i class="fas fa-tint"></i> <?= htmlspecialchars($patient['blood_group'] ?? 'N/A') ?></span>
            </div>
            <div class="patient-tags">
                <?php if (!empty($patient['allergies']) && $patient['allergies'] !== 'None' && $patient['allergies'] !== 'N/A'): ?>
                    <span class="tag tag-danger"><i class="fas fa-exclamation-triangle"></i> Allergies: <?= htmlspecialchars($patient['allergies']) ?></span>
                <?php endif; ?>
                <span class="tag tag-info"><i class="fas fa-address-book"></i> <?= htmlspecialchars($patient['address'] ?? 'No address') ?></span>
                <?php if (!empty($patient['emergency_contact'])): ?>
                    <span class="tag tag-warning"><i class="fas fa-phone-alt"></i> Emergency: <?= htmlspecialchars($patient['emergency_contact']) ?></span>
                <?php endif; ?>
                <span class="tag tag-success"><i class="fas fa-store-alt"></i> <?= htmlspecialchars($patient['branch_name'] ?? 'N/A') ?></span>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- STATS CARDS -->
    <!-- ================================================================ -->
    <div class="stats-grid">
        <div class="stat-card stat-card-blue">
            <div class="stat-card-inner">
                <div class="stat-card-icon"><i class="fas fa-clinic-medical"></i></div>
                <div class="stat-card-info">
                    <span class="stat-card-label">Total Visits</span>
                    <span class="stat-card-number"><?= $patient['total_visits'] ?? 0 ?></span>
                </div>
            </div>
        </div>
        <div class="stat-card stat-card-green">
            <div class="stat-card-inner">
                <div class="stat-card-icon"><i class="fas fa-prescription"></i></div>
                <div class="stat-card-info">
                    <span class="stat-card-label">Prescriptions</span>
                    <span class="stat-card-number"><?= $patient['total_prescriptions'] ?? 0 ?></span>
                </div>
            </div>
        </div>
        <div class="stat-card stat-card-purple">
            <div class="stat-card-inner">
                <div class="stat-card-icon"><i class="fas fa-flask"></i></div>
                <div class="stat-card-info">
                    <span class="stat-card-label">Lab Tests</span>
                    <span class="stat-card-number"><?= $patient['total_lab_tests'] ?? 0 ?></span>
                </div>
            </div>
        </div>
        <div class="stat-card stat-card-orange">
            <div class="stat-card-inner">
                <div class="stat-card-icon"><i class="fas fa-calendar-check"></i></div>
                <div class="stat-card-info">
                    <span class="stat-card-label">Appointments</span>
                    <span class="stat-card-number"><?= $patient['total_appointments'] ?? 0 ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PATIENT DETAILS GRID -->
    <!-- ================================================================ -->
    <div class="consultation-card">
        <h3 class="card-title">
            <i class="fas fa-info-circle title-blue"></i> Patient Details
        </h3>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Full Name</span>
                <span class="info-value"><?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Patient ID</span>
                <span class="info-value font-mono"><?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Date of Birth</span>
                <span class="info-value"><?= !empty($patient['date_of_birth']) ? date('M d, Y', strtotime($patient['date_of_birth'])) : 'N/A' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Age</span>
                <span class="info-value"><?= calculateAge($patient['date_of_birth'] ?? '') ?> years</span>
            </div>
            <div class="info-item">
                <span class="info-label">Gender</span>
                <span class="info-value"><?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Blood Group</span>
                <span class="info-value"><?= htmlspecialchars($patient['blood_group'] ?? 'N/A') ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Phone</span>
                <span class="info-value"><?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Email</span>
                <span class="info-value"><?= htmlspecialchars($patient['email'] ?? 'N/A') ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Emergency Contact</span>
                <span class="info-value"><?= htmlspecialchars($patient['emergency_contact'] ?? 'N/A') ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Branch</span>
                <span class="info-value"><?= htmlspecialchars($patient['branch_name'] ?? 'N/A') ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Allergies</span>
                <span class="info-value"><?= htmlspecialchars($patient['allergies'] ?? 'None') ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Address</span>
                <span class="info-value"><?= htmlspecialchars($patient['address'] ?? 'N/A') ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Assigned Doctor</span>
                <span class="info-value"><?= htmlspecialchars($patient['doctor_name'] ?? 'Not Assigned') ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Registered</span>
                <span class="info-value"><?= date('M d, Y h:i A', strtotime($patient['created_at'] ?? 'now')) ?></span>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- MEDICATIONS HISTORY -->
    <!-- ================================================================ -->
    <div class="consultation-card">
        <h3 class="card-title">
            <i class="fas fa-pills title-blue"></i> Medications History
            <span class="text-sm font-normal text-gray-400">(<?= count($medications_history) ?> unique medications)</span>
        </h3>
        
        <?php if (count($medications_history) > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Medication Name</th>
                            <th>Times Prescribed</th>
                            <th>Last Prescribed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($medications_history as $med): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><strong><?= htmlspecialchars($med['medication_name'] ?? 'N/A') ?></strong></td>
                                <td><span class="badge badge-info"><?= $med['times_prescribed'] ?? 0 ?></span></td>
                                <td><?= time_ago($med['last_prescribed'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-pills"></i>
                <p>No medication history found</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- VISITS HISTORY -->
    <!-- ================================================================ -->
    <div class="consultation-card">
        <h3 class="card-title">
            <i class="fas fa-history title-blue"></i> Visit History
            <span class="text-sm font-normal text-gray-400">(<?= count($visits) ?> visits)</span>
        </h3>
        
        <?php if (count($visits) > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Visit Number</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Doctor</th>
                            <th>Diagnosis</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($visits as $visit): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td class="font-mono"><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></td>
                                <td><?= date('M d, Y', strtotime($visit['created_at'])) ?></td>
                                <td><?= ucfirst($visit['visit_type'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($visit['doctor_name'] ?? 'Not Assigned') ?></td>
                                <td><?= htmlspecialchars(substr($visit['diagnosis'] ?? '', 0, 30)) . (strlen($visit['diagnosis'] ?? '') > 30 ? '...' : '') ?></td>
                                <td>
                                    <span class="status-badge <?= getStatusBadgeClass($visit['status'] ?? 'pending') ?>">
                                        <?= ucfirst($visit['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="consultation.php?visit_id=<?= $visit['id'] ?>" class="btn btn-primary btn-sm" title="View Consultation">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="view_visit.php?id=<?= $visit['id'] ?>" class="btn btn-outline btn-sm" title="View Visit Details">
                                        <i class="fas fa-file-alt"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-clinic-medical"></i>
                <p>No visits recorded for this patient</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- CONSULTATIONS -->
    <!-- ================================================================ -->
    <div class="consultation-card">
        <h3 class="card-title">
            <i class="fas fa-stethoscope title-blue"></i> Consultation History
            <span class="text-sm font-normal text-gray-400">(<?= count($consultations) ?> consultations)</span>
        </h3>
        
        <?php if (count($consultations) > 0): ?>
            <div class="consultation-list">
                <?php foreach ($consultations as $consult): ?>
                    <div class="consultation-item">
                        <div class="consultation-header">
                            <span class="consultation-date">
                                <i class="fas fa-calendar-alt"></i> <?= date('M d, Y', strtotime($consult['created_at'])) ?>
                            </span>
                            <span class="consultation-doctor">
                                <i class="fas fa-user-md"></i> <?= htmlspecialchars($consult['doctor_name'] ?? 'N/A') ?>
                            </span>
                            <span class="consultation-number">
                                <?= htmlspecialchars($consult['visit_number'] ?? 'N/A') ?>
                            </span>
                        </div>
                        <div class="consultation-body">
                            <div class="consultation-row">
                                <span class="consultation-label">Diagnosis:</span>
                                <span class="consultation-value"><?= htmlspecialchars($consult['diagnosis'] ?? 'N/A') ?></span>
                            </div>
                            <div class="consultation-row">
                                <span class="consultation-label">Treatment:</span>
                                <span class="consultation-value"><?= htmlspecialchars($consult['treatment'] ?? 'N/A') ?></span>
                            </div>
                            <?php if (!empty($consult['symptoms'])): ?>
                                <div class="consultation-row">
                                    <span class="consultation-label">Symptoms:</span>
                                    <span class="consultation-value"><?= htmlspecialchars($consult['symptoms']) ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($consult['notes'])): ?>
                                <div class="consultation-row">
                                    <span class="consultation-label">Notes:</span>
                                    <span class="consultation-value"><?= htmlspecialchars($consult['notes']) ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="consultation-stats">
                                <span class="badge badge-info"><?= $consult['prescriptions_count'] ?? 0 ?> Prescriptions</span>
                                <span class="badge badge-purple"><?= $consult['lab_tests_count'] ?? 0 ?> Lab Tests</span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-stethoscope"></i>
                <p>No consultations recorded</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- PRESCRIPTIONS HISTORY -->
    <!-- ================================================================ -->
    <div class="consultation-card">
        <h3 class="card-title">
            <i class="fas fa-prescription title-green"></i> Prescription History
            <span class="text-sm font-normal text-gray-400">(<?= count($prescriptions) ?> prescriptions)</span>
        </h3>
        
        <?php if (count($prescriptions) > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Prescription #</th>
                            <th>Date</th>
                            <th>Diagnosis</th>
                            <th>Doctor</th>
                            <th>Medications</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($prescriptions as $prescription): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td class="font-mono"><?= htmlspecialchars($prescription['prescription_number'] ?? 'N/A') ?></td>
                                <td><?= date('M d, Y', strtotime($prescription['created_at'])) ?></td>
                                <td><?= htmlspecialchars(substr($prescription['diagnosis'] ?? '', 0, 25)) . (strlen($prescription['diagnosis'] ?? '') > 25 ? '...' : '') ?></td>
                                <td><?= htmlspecialchars($prescription['doctor_name'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge badge-info"><?= $prescription['medications_count'] ?? 0 ?> med(s)</span>
                                    <?php if (!empty($prescription['medications_list'])): ?>
                                        <span class="text-xs text-gray-400 block"><?= htmlspecialchars(substr($prescription['medications_list'], 0, 40)) . (strlen($prescription['medications_list'] ?? '') > 40 ? '...' : '') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?= getStatusBadgeClass($prescription['status'] ?? 'pending') ?>">
                                        <?= ucfirst($prescription['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-prescription"></i>
                <p>No prescriptions found</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- LAB TESTS HISTORY -->
    <!-- ================================================================ -->
    <div class="consultation-card">
        <h3 class="card-title">
            <i class="fas fa-flask title-purple"></i> Lab Tests History
            <span class="text-sm font-normal text-gray-400">(<?= count($lab_tests) ?> tests)</span>
        </h3>
        
        <?php if (count($lab_tests) > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Test Name</th>
                            <th>Date</th>
                            <th>Doctor</th>
                            <th>Results</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($lab_tests as $test): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><strong><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></strong></td>
                                <td><?= date('M d, Y', strtotime($test['created_at'])) ?></td>
                                <td><?= htmlspecialchars($test['doctor_name'] ?? 'N/A') ?></td>
                                <td>
                                    <?php if ($test['status'] === 'completed' && !empty($test['results'])): ?>
                                        <span class="text-green-600"><?= htmlspecialchars(substr($test['results'], 0, 30)) . (strlen($test['results'] ?? '') > 30 ? '...' : '') ?></span>
                                    <?php elseif ($test['status'] === 'completed'): ?>
                                        <span class="text-green-600">Results available</span>
                                    <?php else: ?>
                                        <span class="text-gray-400">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?= getStatusBadgeClass($test['status'] ?? 'pending') ?>">
                                        <?= ucfirst($test['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-flask"></i>
                <p>No lab tests found</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- APPOINTMENTS HISTORY -->
    <!-- ================================================================ -->
    <div class="consultation-card">
        <h3 class="card-title">
            <i class="fas fa-calendar-check title-purple"></i> Appointments History
            <span class="text-sm font-normal text-gray-400">(<?= count($appointments) ?> appointments)</span>
        </h3>
        
        <?php if (count($appointments) > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date & Time</th>
                            <th>Doctor</th>
                            <th>Purpose</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($appointments as $appointment): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><?= date('M d, Y h:i A', strtotime($appointment['appointment_date'])) ?></td>
                                <td><?= htmlspecialchars($appointment['doctor_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($appointment['purpose'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="status-badge <?= getStatusBadgeClass($appointment['status'] ?? 'scheduled') ?>">
                                        <?= ucfirst($appointment['status'] ?? 'Scheduled') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-calendar-check"></i>
                <p>No appointments found</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- BILLS SUMMARY - FIXED: Paid text only, no background -->
    <!-- ================================================================ -->
    <div class="consultation-card">
        <h3 class="card-title">
            <i class="fas fa-receipt title-orange"></i> Bills History
            <span class="text-sm font-normal text-gray-400">(<?= count($bills) ?> bills)</span>
        </h3>
        
        <?php if (count($bills) > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Bill Number</th>
                            <th>Date</th>
                            <th>Total Amount</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($bills as $bill): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td class="font-mono"><?= htmlspecialchars($bill['bill_number'] ?? 'N/A') ?></td>
                                <td><?= date('M d, Y', strtotime($bill['created_at'])) ?></td>
                                <td class="font-mono">TSh <?= number_format($bill['total_amount'] ?? 0, 2) ?></td>
                                <td class="font-mono text-green-600">TSh <?= number_format($bill['paid_amount'] ?? 0, 2) ?></td>
                                <td class="font-mono <?= ($bill['balance'] ?? 0) > 0 ? 'text-red-600' : 'text-green-600' ?>">
                                    TSh <?= number_format($bill['balance'] ?? 0, 2) ?>
                                </td>
                                <td>
                                    <?php 
                                    $bill_status = $bill['status'] ?? 'pending';
                                    ?>
                                    <span class="status-badge <?= getStatusBadgeClass($bill_status) ?>">
                                        <?= ucfirst($bill_status) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-receipt"></i>
                <p>No bills found</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="separator">|</span>
            Patient History - <?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?>
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
    /* ================================================================
       ALL STYLES - SAME AS BEFORE
       ================================================================ */
    
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
        padding-bottom: 16px;
        border-bottom: 3px solid var(--primary);
    }
    
    .page-header-left { flex: 1; }
    .page-title {
        font-size: 1.6rem;
        font-weight: 700;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    .page-title i { color: var(--primary); }
    .page-badge {
        font-size: 0.7rem;
        font-weight: 600;
        background: var(--primary-bg);
        color: var(--primary);
        padding: 2px 14px;
        border-radius: 20px;
    }
    .page-subtitle {
        font-size: 0.9rem;
        color: var(--text-secondary);
        margin-top: 4px;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
    }
    .separator { color: var(--border-color); margin: 0 4px; }
    
    .page-header-right {
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
    }
    
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 18px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.8rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
    }
    .btn-primary {
        background: var(--primary);
        color: white;
    }
    .btn-primary:hover {
        background: var(--primary-dark);
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
        border-color: var(--primary);
        color: var(--primary);
    }
    .btn-sm { padding: 4px 12px; font-size: 0.7rem; border-radius: 6px; }
    
    /* ================================================================
       PATIENT PROFILE
       ================================================================ */
    .patient-profile {
        display: flex;
        align-items: center;
        gap: 24px;
        background: var(--bg-card);
        border-radius: 16px;
        padding: 24px 28px;
        border: 2px solid var(--border-color);
        margin-bottom: 24px;
        transition: all 0.3s ease;
    }
    .patient-profile:hover {
        border-color: var(--primary);
        box-shadow: var(--shadow-md);
    }
    
    .patient-avatar-large {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        font-weight: 700;
        color: white;
        flex-shrink: 0;
        box-shadow: 0 4px 14px rgba(0,0,0,0.15);
    }
    
    .patient-profile-info { flex: 1; }
    .patient-name {
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0 0 4px 0;
    }
    .patient-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 12px 20px;
        font-size: 0.85rem;
        color: var(--text-secondary);
        margin-bottom: 8px;
    }
    .patient-meta i { width: 18px; }
    
    .patient-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 0;
    }
    .tag {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 500;
    }
    .tag-primary { background: var(--primary-bg); color: var(--primary); }
    .tag-danger { background: #FEE2E2; color: #DC2626; }
    .tag-info { background: #E8F0FE; color: #0B5ED7; }
    .tag-success { background: #D1FAE5; color: #059669; }
    .tag-warning { background: #FEF3C7; color: #D97706; }
    
    [data-theme="dark"] .tag-primary { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .tag-danger { background: #3A1A1A; color: #F87171; }
    [data-theme="dark"] .tag-info { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .tag-success { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .tag-warning { background: #3D2E0A; color: #FBBF24; }
    
    /* ================================================================
       STATS CARDS
       ================================================================ */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }
    
    .stat-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 18px 20px;
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
    }
    .stat-card:hover {
        border-color: var(--primary);
        box-shadow: var(--shadow-md);
    }
    .stat-card-inner {
        display: flex;
        align-items: center;
        gap: 14px;
    }
    .stat-card-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        color: white;
        flex-shrink: 0;
    }
    .stat-card-blue .stat-card-icon { background: linear-gradient(135deg, #0B5ED7, #1A73E8); }
    .stat-card-green .stat-card-icon { background: linear-gradient(135deg, #059669, #34D399); }
    .stat-card-purple .stat-card-icon { background: linear-gradient(135deg, #7C3AED, #A78BFA); }
    .stat-card-orange .stat-card-icon { background: linear-gradient(135deg, #D97706, #F59E0B); }
    
    .stat-card-info {
        display: flex;
        flex-direction: column;
        flex: 1;
    }
    .stat-card-label {
        font-size: 0.7rem;
        font-weight: 500;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .stat-card-number {
        font-size: 1.6rem;
        font-weight: 700;
        color: var(--text-primary);
        line-height: 1.2;
    }
    
    /* ================================================================
       CONSULTATION CARDS
       ================================================================ */
    .consultation-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 20px 24px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        margin-bottom: 20px;
    }
    .consultation-card:hover {
        border-color: var(--primary);
        box-shadow: var(--shadow-md);
    }
    
    .card-title {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
        border-bottom: 2px solid var(--border-color);
        padding-bottom: 12px;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 6px;
    }
    .title-blue { color: var(--primary); }
    .title-green { color: #059669; }
    .title-purple { color: #7C3AED; }
    .title-orange { color: #D97706; }
    
    .info-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 8px 20px;
    }
    .info-item { display: flex; flex-direction: column; }
    .info-label {
        font-size: 0.65rem;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.04em;
        font-weight: 500;
    }
    .info-value {
        font-size: 0.9rem;
        font-weight: 500;
        color: var(--text-primary);
        padding: 2px 0;
    }
    .font-mono { font-family: monospace; }
    
    /* ================================================================
       TABLES
       ================================================================ */
    .table-wrap { overflow-x: auto; }
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }
    .data-table th {
        text-align: left;
        padding: 8px 12px;
        font-weight: 600;
        font-size: 0.7rem;
        text-transform: uppercase;
        color: var(--text-secondary);
        border-bottom: 2px solid var(--border-color);
    }
    .data-table td {
        padding: 8px 12px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
    }
    .data-table tr:hover td { background: var(--primary-bg); }
    
    /* ================================================================
       STATUS BADGES - Paid: Text only, no background
       ================================================================ */
    .status-badge {
        display: inline-block;
        font-size: 0.7rem;
        font-weight: 600;
        padding: 3px 14px;
        border-radius: 20px;
        line-height: 1.4;
        text-align: center;
        min-width: 60px;
        border: 1px solid transparent;
    }
    
    /* Pending */
    .badge-pending {
        background: #FEF3C7;
        color: #D97706;
        border-color: #FDE68A;
    }
    
    /* Warning/Partial */
    .badge-warning {
        background: #FEF3C7;
        color: #D97706;
        border-color: #FDE68A;
    }
    
    /* SUCCESS/PAID - TEXT ONLY, NO BACKGROUND */
    .badge-success {
        background: transparent !important;
        color: #059669;
        border: none !important;
        font-weight: 700;
        padding: 3px 14px;
    }
    
    /* Danger/Cancelled */
    .badge-danger {
        background: #FEE2E2;
        color: #DC2626;
        border-color: #FCA5A5;
    }
    
    /* Info */
    .badge-info {
        background: #E8F0FE;
        color: #0B5ED7;
        border-color: #BFDBFE;
    }
    
    /* Primary */
    .badge-primary {
        background: #E8F0FE;
        color: #0B5ED7;
        border-color: #BFDBFE;
    }
    
    /* Purple */
    .badge-purple {
        background: #EDE9FE;
        color: #7C3AED;
        border-color: #C4B5FD;
    }
    
    /* ================================================================
       DARK MODE STATUSES - Paid: Text only, no background
       ================================================================ */
    [data-theme="dark"] .badge-pending {
        background: #3D2E0A;
        color: #FBBF24;
        border-color: #78350F;
    }
    
    [data-theme="dark"] .badge-warning {
        background: #3D2E0A;
        color: #FBBF24;
        border-color: #78350F;
    }
    
    [data-theme="dark"] .badge-success {
        background: transparent !important;
        color: #34D399;
        border: none !important;
        font-weight: 700;
        padding: 3px 14px;
    }
    
    [data-theme="dark"] .badge-danger {
        background: #3A1A1A;
        color: #F87171;
        border-color: #7F1D1D;
    }
    
    [data-theme="dark"] .badge-info {
        background: #1E3A5F;
        color: #6EA8FE;
        border-color: #1E3A5F;
    }
    
    [data-theme="dark"] .badge-primary {
        background: #1E3A5F;
        color: #6EA8FE;
        border-color: #1E3A5F;
    }
    
    [data-theme="dark"] .badge-purple {
        background: #2D1A3A;
        color: #A78BFA;
        border-color: #2D1A3A;
    }
    
    /* ================================================================
       CONSULTATION LIST
       ================================================================ */
    .consultation-list { max-height: 500px; overflow-y: auto; }
    .consultation-list::-webkit-scrollbar { width: 4px; }
    .consultation-list::-webkit-scrollbar-track { background: var(--bg-body); border-radius: 4px; }
    .consultation-list::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 4px; }
    
    .consultation-item {
        background: var(--bg-body);
        border-radius: 10px;
        padding: 14px 18px;
        margin-bottom: 10px;
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
    }
    .consultation-item:hover {
        border-color: var(--primary);
        box-shadow: var(--shadow-sm);
    }
    .consultation-item:last-child { margin-bottom: 0; }
    
    .consultation-header {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 8px;
        padding-bottom: 6px;
        border-bottom: 1px solid var(--border-color);
        font-size: 0.8rem;
        color: var(--text-secondary);
    }
    .consultation-header i { width: 16px; }
    
    .consultation-body { font-size: 0.85rem; }
    .consultation-row {
        display: flex;
        padding: 2px 0;
        gap: 8px;
    }
    .consultation-label {
        font-weight: 600;
        color: var(--text-secondary);
        min-width: 80px;
        flex-shrink: 0;
    }
    .consultation-value {
        color: var(--text-primary);
        flex: 1;
    }
    .consultation-stats {
        display: flex;
        gap: 8px;
        margin-top: 8px;
    }
    
    .badge {
        padding: 2px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-block;
        color: white;
    }
    .badge-info { background: #0B5ED7; }
    .badge-success { background: #059669; }
    .badge-purple { background: #7C3AED; }
    .badge-warning { background: #D97706; }
    
    /* ================================================================
       TEXT COLORS
       ================================================================ */
    .text-red-600 { color: #DC2626; }
    .text-green-600 { color: #059669; }
    .text-gray-400 { color: var(--text-secondary); }
    .block { display: block; }
    .text-xs { font-size: 0.75rem; }
    .text-sm { font-size: 0.875rem; }
    
    /* ================================================================
       EMPTY STATE
       ================================================================ */
    .empty-state {
        text-align: center;
        padding: 30px 10px;
        color: var(--text-secondary);
    }
    .empty-state i {
        font-size: 2.5rem;
        color: var(--border-color);
        display: block;
        margin-bottom: 8px;
    }
    .empty-state p { font-size: 0.9rem; margin: 0; }
    
    /* ================================================================
       TOAST
       ================================================================ */
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
    .footer .footer-brand { color: var(--primary); font-weight: 600; }
    
    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 1024px) {
        .info-grid { grid-template-columns: repeat(2, 1fr); }
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .patient-profile { flex-direction: column; text-align: center; }
        .patient-meta { justify-content: center; }
        .patient-tags { justify-content: center; }
        .main-content { padding: 16px; }
        .page-title { font-size: 1.3rem; }
    }
    
    @media (max-width: 768px) {
        .main-content { margin-left: 0; padding: 12px; }
        .info-grid { grid-template-columns: 1fr; }
        .stats-grid { grid-template-columns: 1fr 1fr; }
        .page-header { flex-direction: column; }
        .page-header-right { width: 100%; }
        .page-header-right .btn { flex: 1; justify-content: center; }
        .consultation-card { padding: 14px 16px; }
        .patient-profile { padding: 16px; }
        .patient-avatar-large { width: 60px; height: 60px; font-size: 1.5rem; }
        .data-table { font-size: 0.75rem; }
        .data-table th, .data-table td { padding: 4px 8px; }
        .consultation-header { flex-direction: column; gap: 4px; }
        .consultation-row { flex-direction: column; gap: 2px; }
        .consultation-label { min-width: auto; }
    }
    
    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr; }
        .page-subtitle { flex-direction: column; align-items: flex-start; gap: 4px; }
        .separator { display: none; }
        .patient-meta { flex-direction: column; align-items: center; gap: 4px; }
    }
    
    @media print {
        .top-nav, .sidebar, .btn, .footer { display: none !important; }
        .main-content { margin: 0 !important; padding: 20px !important; }
        .consultation-card { border: 1px solid #ddd !important; box-shadow: none !important; page-break-inside: avoid; }
        .patient-profile { border: 1px solid #ddd !important; }
        .page-header { border-bottom: 2px solid #0B5ED7 !important; }
    }
</style>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE
    // ================================================================
    if (localStorage.getItem('darkMode') === 'true') {
        document.documentElement.setAttribute('data-theme', 'dark');
    }

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
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 5000);
    }

    console.log('%c👤 Patient History - <?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?>', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Patient ID: <?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?>', 'font-size:12px; color:#059669;');
    console.log('%c📊 Total Visits: <?= $patient['total_visits'] ?? 0 ?>', 'font-size:12px; color:#64748B;');
    console.log('%c💊 Prescriptions: <?= $patient['total_prescriptions'] ?? 0 ?>', 'font-size:12px; color:#7C3AED;');
    console.log('%c🧪 Lab Tests: <?= $patient['total_lab_tests'] ?? 0 ?>', 'font-size:12px; color:#D97706;');
    console.log('%c📅 Appointments: <?= $patient['total_appointments'] ?? 0 ?>', 'font-size:12px; color:#0B5ED7;');
    console.log('%c💰 Bills: <?= count($bills) ?> (Paid: Text only, no background)', 'font-size:12px; color:#D97706;');
</script>

</body>
</html>