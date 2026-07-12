<?php
// ================================================================
// FILE: frontend/pages/doctor/patient_details.php
// DOCTOR - PATIENT DETAILS (NO NEW VISIT - ONLY CONSULTATION)
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

function getStatusBadgeClass($status) {
    switch ($status) {
        case 'completed': return 'badge-success';
        case 'cancelled': return 'badge-danger';
        case 'in_progress': return 'badge-warning';
        case 'pending': return 'badge-warning';
        case 'dispensed': return 'badge-success';
        case 'with_doctor': return 'badge-primary';
        case 'assigned': return 'badge-info';
        case 'prescribed': return 'badge-purple';
        default: return 'badge-info';
    }
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
// GET PATIENT LAB TESTS - FIXED: Use visit_id to get patient_id
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
            <!-- PDF Button -->
            <button onclick="generatePDF()" class="btn btn-pdf btn-sm">
                <i class="fas fa-file-pdf"></i> PDF
            </button>
            <!-- Print Button -->
            <button onclick="window.print()" class="btn btn-outline btn-sm">
                <i class="fas fa-print"></i> Print
            </button>
            <!-- Consultation Button - REPLACES New Visit -->
            <a href="consultation.php?patient_id=<?= $patient_id ?>" class="btn btn-consult btn-sm">
                <i class="fas fa-stethoscope"></i> Start Consultation
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
    <!-- PATIENT INFORMATION - FULL DETAILS -->
    <!-- ================================================================ -->
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
                    <p class="font-medium text-gray-800"><?= !empty($patient['date_of_birth']) ? date('M d, Y', strtotime($patient['date_of_birth'])) : 'N/A' ?></p>
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
                <div>
                    <p class="text-sm text-gray-500">Emergency Contact</p>
                    <p class="font-medium text-gray-800"><?= htmlspecialchars($patient['emergency_contact'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Branch</p>
                    <p class="font-medium text-gray-800"><?= htmlspecialchars($doctor_branch_name) ?></p>
                </div>
                <div class="col-span-2">
                    <p class="text-sm text-gray-500">Address</p>
                    <p class="font-medium text-gray-800"><?= htmlspecialchars($patient['address'] ?? 'N/A') ?></p>
                </div>
                <div class="col-span-2">
                    <p class="text-sm text-gray-500">Allergies</p>
                    <p class="font-medium text-gray-800"><?= htmlspecialchars($patient['allergies'] ?? 'None') ?></p>
                </div>
                <div class="col-span-2">
                    <p class="text-sm text-gray-500">Registered On</p>
                    <p class="font-medium text-gray-800"><?= date('F d, Y h:i A', strtotime($patient['created_at'])) ?></p>
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
                    <p class="text-3xl font-bold text-purple-600"><?= count($lab_tests) ?></p>
                    <p class="text-sm text-gray-500">Lab Tests</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- VISITS HISTORY -->
    <!-- ================================================================ -->
    <div class="card mb-6">
        <h3 class="card-title"><i class="fas fa-history title-blue mr-2"></i> Visit History <span class="text-sm font-normal text-gray-400">(<?= count($visits) ?> visits)</span></h3>
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
                        <th>Actions</th>
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
                                    <?php if ($visit['status'] === 'pending' || $visit['status'] === 'assigned'): ?>
                                        <a href="consultation.php?visit_id=<?= $visit['id'] ?>&patient_id=<?= $patient_id ?>" class="btn btn-consult btn-sm" title="Start Consultation">
                                            <i class="fas fa-stethoscope"></i> Consult
                                        </a>
                                    <?php else: ?>
                                        <a href="view_visit.php?id=<?= $visit['id'] ?>" class="btn btn-view btn-sm">View</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center py-8 text-gray-400">No visits recorded</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PRESCRIPTIONS -->
    <!-- ================================================================ -->
    <div class="card mb-6">
        <h3 class="card-title"><i class="fas fa-prescription title-blue mr-2"></i> Prescriptions <span class="text-sm font-normal text-gray-400">(<?= count($prescriptions) ?> prescriptions)</span></h3>
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
                        <th>Actions</th>
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
                        <tr><td colspan="7" class="text-center py-8 text-gray-400">No prescriptions recorded</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- LAB TESTS -->
    <!-- ================================================================ -->
    <div class="card mb-6">
        <h3 class="card-title"><i class="fas fa-flask title-blue mr-2"></i> Lab Tests <span class="text-sm font-normal text-gray-400">(<?= count($lab_tests) ?> tests)</span></h3>
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
                        <th>Actions</th>
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
                        <tr><td colspan="8" class="text-center py-8 text-gray-400">No lab tests recorded</td></tr>
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
    .badge { padding: 3px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; color: white; border: none; }
    .badge-success { background: #059669; }
    .badge-danger { background: #EF4444; }
    .badge-warning { background: #D97706; }
    .badge-info { background: var(--primary); }
    .badge-primary { background: #0B5ED7; }
    .badge-purple { background: #7C3AED; }
    
    .table-wrap { overflow-x: auto; }
    .data-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
    .data-table thead th { text-align: left; padding: 10px 14px; font-weight: 700; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; color: white; background: var(--primary); border-bottom: 3px solid var(--primary-dark); white-space: nowrap; }
    .data-table tbody tr:nth-child(even) { background: var(--primary-bg); }
    .data-table tbody tr:nth-child(odd) { background: var(--bg-card); }
    .data-table tbody tr:hover { background: #D1FAE5; }
    .data-table td { padding: 10px 14px; border-bottom: 1px solid var(--border-color); color: var(--text-primary); vertical-align: middle; }
    
    .btn-view { background: var(--primary); color: white; padding: 4px 10px; font-size: 0.7rem; border-radius: 6px; border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
    .btn-view:hover { background: var(--primary-dark); transform: scale(1.05); }
    
    .btn-consult { background: #7C3AED; color: white; padding: 6px 14px; font-size: 0.75rem; border-radius: 6px; border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
    .btn-consult:hover { background: #6D28D9; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(124, 58, 237, 0.3); }
    
    .btn-pdf { background: #DC2626; color: white; padding: 6px 14px; font-size: 0.75rem; border-radius: 6px; border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
    .btn-pdf:hover { background: #B91C1C; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3); }
    
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
    
    @media print {
        .top-nav, .sidebar, .btn, .footer { display: none !important; }
        .main-content { margin: 0 !important; padding: 20px !important; }
        .card { border: 1px solid #ddd !important; box-shadow: none !important; }
        .page-header { border-bottom: 2px solid #0B5ED7 !important; }
        .data-table thead th { background: #0B5ED7 !important; color: white !important; }
    }
</style>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>

<script>
    // ================================================================
    // GENERATE PDF
    // ================================================================
    function generatePDF() {
        showToast('Generating PDF', 'Please wait...', 'info');
        
        try {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('p', 'mm', 'a4');
            
            // Header
            doc.setFillColor('#0B5ED7');
            doc.rect(10, 8, 190, 5, 'F');
            
            doc.setFontSize(18);
            doc.setTextColor('#0B5ED7');
            doc.text('BRAICK DISPENSARY', 14, 22);
            
            doc.setFontSize(12);
            doc.setTextColor('#1E293B');
            doc.text('Patient Details Report', 14, 30);
            
            doc.setFontSize(9);
            doc.setTextColor('#64748B');
            doc.text('Patient: <?= htmlspecialchars($patient['full_name']) ?>', 14, 38);
            doc.text('Patient ID: <?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?>', 14, 44);
            doc.text('Generated: ' + new Date().toLocaleString(), 14, 50);
            doc.text('Doctor: <?= htmlspecialchars($doctor_name) ?>', 14, 56);
            
            // Patient Information
            doc.setFontSize(11);
            doc.setTextColor('#0B5ED7');
            doc.text('Personal Information', 14, 66);
            
            doc.setFontSize(9);
            doc.setTextColor('#1E293B');
            const patientInfo = [
                ['Full Name', '<?= htmlspecialchars($patient['full_name']) ?>'],
                ['Patient ID', '<?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?>'],
                ['Gender', '<?= htmlspecialchars($patient['gender'] ?? 'N/A') ?>'],
                ['Date of Birth', '<?= !empty($patient['date_of_birth']) ? date('M d, Y', strtotime($patient['date_of_birth'])) : 'N/A' ?>'],
                ['Phone', '<?= htmlspecialchars($patient['phone'] ?? 'N/A') ?>'],
                ['Email', '<?= htmlspecialchars($patient['email'] ?? 'N/A') ?>'],
                ['Blood Group', '<?= htmlspecialchars($patient['blood_group'] ?? 'N/A') ?>'],
                ['Allergies', '<?= htmlspecialchars($patient['allergies'] ?? 'None') ?>'],
                ['Address', '<?= htmlspecialchars($patient['address'] ?? 'N/A') ?>']
            ];
            
            doc.autoTable({
                startY: 70,
                head: [['Field', 'Value']],
                body: patientInfo,
                theme: 'striped',
                headStyles: { fillColor: '#0B5ED7', textColor: '#FFFFFF', fontSize: 8 },
                bodyStyles: { fontSize: 8 },
                columnStyles: {
                    0: { cellWidth: 50 },
                    1: { cellWidth: 120 }
                },
                margin: { left: 14, right: 14 }
            });
            
            let finalY = doc.lastAutoTable.finalY + 8;
            
            // Visits Summary
            doc.setFontSize(11);
            doc.setTextColor('#0B5ED7');
            doc.text('Visit History (<?= count($visits) ?> visits)', 14, finalY);
            
            const visitData = [
                ['#', 'Date', 'Visit #', 'Doctor', 'Status']
            ];
            
            <?php foreach ($visits as $index => $visit): ?>
                visitData.push([
                    '<?= $index + 1 ?>',
                    '<?= date('M d, Y', strtotime($visit['created_at'])) ?>',
                    '<?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>',
                    '<?= htmlspecialchars($visit['doctor_name'] ?? 'Unknown') ?>',
                    '<?= ucfirst($visit['status'] ?? 'Pending') ?>'
                ]);
            <?php endforeach; ?>
            
            if (visitData.length > 1) {
                doc.autoTable({
                    startY: finalY + 4,
                    head: [visitData[0]],
                    body: visitData.slice(1),
                    theme: 'striped',
                    headStyles: { fillColor: '#0B5ED7', textColor: '#FFFFFF', fontSize: 7 },
                    bodyStyles: { fontSize: 7 },
                    margin: { left: 14, right: 14 }
                });
                finalY = doc.lastAutoTable.finalY + 8;
            }
            
            // Footer
            doc.setFontSize(7);
            doc.setTextColor('#94A3B8');
            doc.text('Braick Dispensary Management System - Patient Details', 14, doc.internal.pageSize.height - 10);
            doc.text('Page 1 of 1', 190, doc.internal.pageSize.height - 10, { align: 'right' });
            
            // Blue border at bottom
            doc.setFillColor('#0B5ED7');
            doc.rect(10, doc.internal.pageSize.height - 6, 190, 3, 'F');
            
            doc.save('patient_<?= htmlspecialchars($patient['patient_id'] ?? $patient['id']) ?>_details.pdf');
            
            showToast('Success', 'PDF downloaded successfully!', 'success');
        } catch (error) {
            console.error('PDF Error:', error);
            showToast('Error', 'Failed to generate PDF', 'error');
        }
    }

    // ================================================================
    // SHOW TOAST
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
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 3500);
    }

    console.log('%c👨‍⚕️ Patient Details - <?= htmlspecialchars($patient['full_name']) ?>', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📊 Visits: <?= count($visits) ?> | Prescriptions: <?= count($prescriptions) ?> | Lab Tests: <?= count($lab_tests) ?>', 'font-size:12px; color:#059669;');
    console.log('%c🔄 New Visit REMOVED - Added Start Consultation', 'font-size:12px; color:#7C3AED;');
    console.log('%c✅ Doctor can only consult on existing visits', 'font-size:12px; color:#64748B;');
</script>

</body>
</html>