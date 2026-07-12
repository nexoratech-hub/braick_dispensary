<?php
// ================================================================
// FILE: frontend/pages/doctor/view_visit.php
// DOCTOR - VIEW VISIT DETAILS
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// CHECK SESSION
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    $_SESSION['user_id'] = 2;
    $_SESSION['full_name'] = 'Dr. Sarah Mwamba';
    $_SESSION['role'] = 'doctor';
    $_SESSION['branch_id'] = 1;
}

$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['full_name'] ?? 'Doctor';
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;

// ================================================================
// GET VISIT ID
// ================================================================
$visit_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

if ($visit_id <= 0) {
    header('Location: my_patients.php');
    exit;
}

// ================================================================
// INCLUDE DATABASE
// ================================================================
$db_path = 'C:/xampp/htdocs/dispensary_system/backend/config/database.php';
if (file_exists($db_path)) {
    require_once $db_path;
} else {
    die("❌ Database file not found");
}
$db = Database::getInstance()->getConnection();

// ================================================================
// GET VISIT DETAILS
// ================================================================
$stmt = $db->prepare("
    SELECT 
        v.*,
        p.full_name as patient_name,
        p.patient_id as patient_code,
        p.phone,
        p.email,
        p.date_of_birth,
        p.gender,
        p.address,
        u.full_name as doctor_name,
        u.specialty as doctor_specialty,
        b.name as branch_name
    FROM visits v
    JOIN patients p ON v.patient_id = p.id
    LEFT JOIN users u ON v.doctor_id = u.id
    LEFT JOIN branches b ON v.branch_id = b.id
    WHERE v.id = ? AND v.doctor_id = ?
");
$stmt->execute([$visit_id, $doctor_id]);
$visit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$visit) {
    header('Location: my_patients.php?error=visit_not_found');
    exit;
}

// ================================================================
// GET PRESCRIPTIONS FOR THIS VISIT
// ================================================================
$stmt = $db->prepare("
    SELECT 
        pr.*,
        pi.*
    FROM prescriptions pr
    LEFT JOIN prescription_items pi ON pr.id = pi.prescription_id
    WHERE pr.visit_id = ?
    ORDER BY pr.created_at DESC
");
$stmt->execute([$visit_id]);
$prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET LAB TESTS FOR THIS VISIT
// ================================================================
$stmt = $db->prepare("
    SELECT * FROM lab_tests 
    WHERE visit_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$visit_id]);
$lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET VITAL SIGNS (if table exists)
// ================================================================
$vital_signs = [];
try {
    $stmt = $db->prepare("
        SELECT * FROM vital_signs 
        WHERE visit_id = ?
        ORDER BY recorded_at DESC
    ");
    $stmt->execute([$visit_id]);
    $vital_signs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table might not exist - ignore
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
                <i class="fas fa-clinic-medical mr-2" style="color: #0B5ED7;"></i> Visit Details
            </h1>
            <p class="page-subtitle">
                View complete visit information
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-hashtag mr-1"></i> <?= htmlspecialchars($visit['visit_number']) ?>
                </span>
                <span class="ml-2 inline-flex bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs border border-green-200">
                    <i class="fas fa-user mr-1"></i> <?= htmlspecialchars($visit['patient_name']) ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2">
            <a href="my_patients.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <a href="prescribe.php?patient_id=<?= $visit['patient_id'] ?>&visit_id=<?= $visit['id'] ?>" class="btn btn-blue btn-sm">
                <i class="fas fa-prescription"></i> Prescribe
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- VISIT SUMMARY CARD -->
    <!-- ================================================================ -->
    <div class="summary-cards">
        
        <!-- Patient Info -->
        <div class="summary-card">
            <div class="summary-card-header">
                <i class="fas fa-user text-blue-600"></i>
                <span>Patient Information</span>
            </div>
            <div class="summary-card-body">
                <p><strong><?= htmlspecialchars($visit['patient_name']) ?></strong></p>
                <p class="text-sm text-gray-500">
                    <span class="inline-block mr-3">ID: <?= htmlspecialchars($visit['patient_code']) ?></span>
                    <?php if ($visit['gender']): ?>
                        <span class="inline-block mr-3">Gender: <?= htmlspecialchars($visit['gender']) ?></span>
                    <?php endif; ?>
                    <?php if ($visit['date_of_birth']): ?>
                        <span class="inline-block">DOB: <?= date('M d, Y', strtotime($visit['date_of_birth'])) ?></span>
                    <?php endif; ?>
                </p>
                <?php if ($visit['phone']): ?>
                    <p class="text-sm"><i class="fas fa-phone mr-1"></i> <?= htmlspecialchars($visit['phone']) ?></p>
                <?php endif; ?>
                <?php if ($visit['email']): ?>
                    <p class="text-sm"><i class="fas fa-envelope mr-1"></i> <?= htmlspecialchars($visit['email']) ?></p>
                <?php endif; ?>
                <?php if ($visit['address']): ?>
                    <p class="text-sm"><i class="fas fa-map-marker-alt mr-1"></i> <?= htmlspecialchars($visit['address']) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Visit Info -->
        <div class="summary-card">
            <div class="summary-card-header">
                <i class="fas fa-clinic-medical text-green-600"></i>
                <span>Visit Information</span>
            </div>
            <div class="summary-card-body">
                <p><strong><?= htmlspecialchars($visit['visit_number']) ?></strong></p>
                <p class="text-sm text-gray-500">
                    <span class="inline-block mr-3">Type: <?= ucfirst($visit['visit_type'] ?? 'New') ?></span>
                    <span class="inline-block">
                        Status: 
                        <span class="badge <?= getStatusBadgeClass($visit['status']) ?>">
                            <?= ucfirst($visit['status'] ?? 'Pending') ?>
                        </span>
                    </span>
                </p>
                <p class="text-sm text-gray-500">
                    <i class="far fa-calendar-alt mr-1"></i>
                    Date: <?= date('F d, Y h:i A', strtotime($visit['created_at'])) ?>
                </p>
                <p class="text-sm text-gray-500">
                    <i class="fas fa-user-md mr-1"></i>
                    Doctor: <?= htmlspecialchars($visit['doctor_name'] ?? 'Not assigned') ?>
                    <?= !empty($visit['doctor_specialty']) ? '(' . htmlspecialchars($visit['doctor_specialty']) . ')' : '' ?>
                </p>
                <p class="text-sm text-gray-500">
                    <i class="fas fa-store-alt mr-1"></i>
                    Branch: <?= htmlspecialchars($visit['branch_name'] ?? 'Not assigned') ?>
                </p>
            </div>
        </div>

    </div>

    <!-- ================================================================ -->
    <!-- CLINICAL DETAILS -->
    <!-- ================================================================ -->
    <div class="card mt-4">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-stethoscope title-blue mr-2"></i> Clinical Details
            </h3>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="text-xs text-gray-500 font-medium">Symptoms</label>
                <p class="text-gray-700 bg-gray-50 p-3 rounded-lg border border-gray-200 min-h-[60px]">
                    <?= !empty($visit['symptoms']) ? nl2br(htmlspecialchars($visit['symptoms'])) : '<span class="text-gray-400">No symptoms recorded</span>' ?>
                </p>
            </div>
            <div>
                <label class="text-xs text-gray-500 font-medium">Diagnosis</label>
                <p class="text-gray-700 bg-gray-50 p-3 rounded-lg border border-gray-200 min-h-[60px]">
                    <?= !empty($visit['diagnosis']) ? nl2br(htmlspecialchars($visit['diagnosis'])) : '<span class="text-gray-400">No diagnosis recorded</span>' ?>
                </p>
            </div>
            <div class="md:col-span-2">
                <label class="text-xs text-gray-500 font-medium">Notes</label>
                <p class="text-gray-700 bg-gray-50 p-3 rounded-lg border border-gray-200 min-h-[60px]">
                    <?= !empty($visit['notes']) ? nl2br(htmlspecialchars($visit['notes'])) : '<span class="text-gray-400">No notes recorded</span>' ?>
                </p>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PRESCRIPTIONS -->
    <!-- ================================================================ -->
    <div class="card mt-4">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-prescription title-blue mr-2"></i> Prescriptions
                <span class="text-sm font-normal text-gray-400">(<?= count($prescriptions) ?>)</span>
            </h3>
        </div>
        <?php if (count($prescriptions) > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Prescription</th>
                            <th>Medication</th>
                            <th>Dosage</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prescriptions as $index => $pr): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td class="font-mono text-sm font-semibold text-blue-600">
                                    <?= htmlspecialchars($pr['prescription_number']) ?>
                                </td>
                                <td><?= htmlspecialchars($pr['medication_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($pr['dosage'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge <?= $pr['status'] === 'dispensed' ? 'badge-success' : ($pr['status'] === 'cancelled' ? 'badge-danger' : 'badge-warning') ?>">
                                        <?= ucfirst($pr['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td><?= date('M d, Y', strtotime($pr['created_at'])) ?></td>
                                <td>
                                    <a href="view_prescription.php?id=<?= $pr['id'] ?>" class="btn btn-view btn-sm">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-6 text-gray-400">
                <i class="fas fa-prescription text-2xl block mb-2"></i>
                <p>No prescriptions for this visit</p>
                <a href="prescribe.php?patient_id=<?= $visit['patient_id'] ?>&visit_id=<?= $visit['id'] ?>" class="btn btn-blue btn-sm mt-2">
                    <i class="fas fa-plus"></i> Create Prescription
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- LAB TESTS -->
    <!-- ================================================================ -->
    <div class="card mt-4">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-flask title-blue mr-2"></i> Lab Tests
                <span class="text-sm font-normal text-gray-400">(<?= count($lab_tests) ?>)</span>
            </h3>
        </div>
        <?php if (count($lab_tests) > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Test Name</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lab_tests as $index => $test): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($test['test_type'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge <?= $test['status'] === 'completed' ? 'badge-success' : ($test['status'] === 'cancelled' ? 'badge-danger' : 'badge-warning') ?>">
                                        <?= ucfirst($test['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td><?= date('M d, Y', strtotime($test['created_at'])) ?></td>
                                <td>
                                    <a href="view_lab_test.php?id=<?= $test['id'] ?>" class="btn btn-view btn-sm">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-6 text-gray-400">
                <i class="fas fa-flask text-2xl block mb-2"></i>
                <p>No lab tests for this visit</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- VITAL SIGNS -->
    <!-- ================================================================ -->
    <?php if (!empty($vital_signs)): ?>
    <div class="card mt-4">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-heartbeat title-blue mr-2"></i> Vital Signs
            </h3>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <?php foreach ($vital_signs as $vital): ?>
                <div class="text-center p-3 bg-gray-50 rounded-lg border border-gray-200">
                    <p class="text-2xl font-bold text-blue-600">
                        <?= htmlspecialchars($vital['value'] ?? 'N/A') ?>
                    </p>
                    <p class="text-xs text-gray-500"><?= htmlspecialchars($vital['type'] ?? '') ?></p>
                    <p class="text-xs text-gray-400"><?= date('h:i A', strtotime($vital['recorded_at'] ?? 'now')) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Visit Details
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- STYLES -->
<!-- ================================================================ -->
<style>
    .summary-cards {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
    }
    
    .summary-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 20px 24px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
    }
    
    .summary-card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.08);
    }
    
    .summary-card-header {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--text-primary);
        border-bottom: 2px solid var(--border-color);
        padding-bottom: 10px;
        margin-bottom: 12px;
    }
    
    .summary-card-body {
        color: var(--text-primary);
    }
    
    .summary-card-body p {
        margin-bottom: 4px;
    }
    
    .card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 20px 24px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
    }
    
    .card:hover {
        border-color: var(--primary);
    }
    
    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .card-title {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--text-primary);
    }
    
    .title-blue { color: var(--primary); }
    
    .badge {
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: white;
    }
    
    .badge-success { background: #059669; color: white; }
    .badge-warning { background: #F59E0B; color: white; }
    .badge-danger { background: #EF4444; color: white; }
    .badge-info { background: #0B5ED7; color: white; }
    
    .text-xs { font-size: 0.75rem; }
    .text-sm { font-size: 0.875rem; }
    .text-gray-400 { color: var(--text-muted); }
    .text-gray-500 { color: var(--text-secondary); }
    .text-gray-700 { color: var(--text-primary); }
    .text-blue-600 { color: var(--primary); }
    .text-green-600 { color: #059669; }
    .font-medium { font-weight: 500; }
    .font-semibold { font-weight: 600; }
    .font-mono { font-family: monospace; }
    
    .table-wrap { overflow-x: auto; }
    .data-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
    .data-table th { text-align: left; padding: 10px 14px; font-weight: 600; font-size: 0.7rem; text-transform: uppercase; color: var(--text-secondary); border-bottom: 2px solid var(--border-color); }
    .data-table td { padding: 10px 14px; border-bottom: 1px solid var(--border-color); color: var(--text-primary); }
    .data-table tr:hover td { background: var(--primary-bg); }
    
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.8rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
    }
    
    .btn-blue { background: var(--primary); color: white; }
    .btn-blue:hover { background: var(--primary-dark); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3); }
    
    .btn-outline { background: transparent; color: var(--text-secondary); border: 2px solid var(--border-color); }
    .btn-outline:hover { background: var(--bg-body); border-color: var(--primary); color: var(--primary); }
    
    .btn-view { background: var(--primary); color: white; padding: 4px 10px; font-size: 0.7rem; border-radius: 6px; }
    .btn-view:hover { background: var(--primary-dark); transform: scale(1.05); }
    
    .btn-sm { padding: 4px 10px; font-size: 0.7rem; border-radius: 6px; }
    
    .grid { display: grid; }
    .grid-cols-1 { grid-template-columns: 1fr; }
    .grid-cols-2 { grid-template-columns: 1fr 1fr; }
    .md\:grid-cols-2 { grid-template-columns: 1fr 1fr; }
    .md\:grid-cols-4 { grid-template-columns: 1fr 1fr 1fr 1fr; }
    .gap-4 { gap: 1rem; }
    .mt-4 { margin-top: 1rem; }
    .mb-6 { margin-bottom: 1.5rem; }
    .ml-2 { margin-left: 0.5rem; }
    .mr-1 { margin-right: 0.25rem; }
    .mr-2 { margin-right: 0.5rem; }
    .mr-3 { margin-right: 0.75rem; }
    .inline-block { display: inline-block; }
    .min-h-\[60px\] { min-height: 60px; }
    
    .bg-gray-50 { background: var(--bg-body); }
    .border-gray-200 { border-color: var(--border-color); }
    .rounded-lg { border-radius: 10px; }
    .p-3 { padding: 0.75rem; }
    
    [data-theme="dark"] .bg-gray-50 { background: #0F172A; }
    [data-theme="dark"] .border-gray-200 { border-color: #334155; }
    
    @media (max-width: 768px) {
        .summary-cards { grid-template-columns: 1fr; }
        .md\:grid-cols-2 { grid-template-columns: 1fr; }
        .md\:grid-cols-4 { grid-template-columns: 1fr 1fr; }
        .card { padding: 14px 16px; }
        .summary-card { padding: 14px 16px; }
    }
</style>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // FUNCTION TO GET STATUS BADGE CLASS
    // ================================================================
    function getStatusBadgeClass(status) {
        switch (status) {
            case 'completed': return 'badge-success';
            case 'cancelled': return 'badge-danger';
            case 'pending':
            case 'assigned':
            case 'with_doctor':
                return 'badge-warning';
            case 'prescribed':
                return 'badge-info';
            default: return 'badge-info';
        }
    }

    console.log('%c👨‍⚕️ View Visit - <?= htmlspecialchars($visit['visit_number']) ?>', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Patient: <?= htmlspecialchars($visit['patient_name']) ?>', 'font-size:12px; color:#059669;');
    console.log('%c💊 Prescriptions: <?= count($prescriptions) ?>', 'font-size:12px; color:#64748B;');
    console.log('%c🧪 Lab Tests: <?= count($lab_tests) ?>', 'font-size:12px; color:#64748B;');
</script>

</body>
</html>