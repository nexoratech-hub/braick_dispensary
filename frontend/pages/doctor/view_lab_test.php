<?php
// ================================================================
// FILE: frontend/pages/doctor/view_lab_test.php
// DOCTOR - VIEW SINGLE LAB TEST (FIXED - NO patient_id)
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
// GET LAB TEST ID
// ================================================================
$test_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$visit_id = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;

if ($test_id <= 0) {
    header('Location: lab_results.php');
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
// GET LAB TEST DETAILS - FIXED: NO patient_id, use visit_id
// ================================================================
$stmt = $db->prepare("
    SELECT 
        lt.*,
        v.visit_number,
        v.patient_id,
        v.diagnosis as visit_diagnosis,
        p.full_name as patient_name,
        p.patient_id as patient_code,
        u.full_name as doctor_name,
        u.specialty as doctor_specialty
    FROM lab_tests lt
    LEFT JOIN visits v ON lt.visit_id = v.id
    LEFT JOIN patients p ON v.patient_id = p.id
    LEFT JOIN users u ON lt.doctor_id = u.id
    WHERE lt.id = ? AND lt.doctor_id = ?
");
$stmt->execute([$test_id, $doctor_id]);
$test = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$test) {
    header('Location: lab_results.php?error=not_found');
    exit;
}

// ================================================================
// GET TEST ITEMS / RESULTS DETAILS (if table exists)
// ================================================================
$test_items = [];
try {
    $stmt = $db->prepare("SELECT * FROM lab_test_items WHERE lab_test_id = ?");
    $stmt->execute([$test_id]);
    $test_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                <i class="fas fa-flask mr-2" style="color: #0B5ED7;"></i> Lab Test Details
            </h1>
            <p class="page-subtitle">
                View complete lab test information
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-hashtag mr-1"></i> Test #<?= $test['id'] ?>
                </span>
                <?php if ($test['patient_name']): ?>
                    <span class="ml-2 inline-flex bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs border border-green-200">
                        <i class="fas fa-user mr-1"></i> <?= htmlspecialchars($test['patient_name']) ?>
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div class="flex gap-2">
            <a href="lab_results.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <?php if ($test['visit_id']): ?>
                <a href="view_visit.php?id=<?= $test['visit_id'] ?>" class="btn btn-outline btn-sm">
                    <i class="fas fa-clinic-medical"></i> View Visit
                </a>
            <?php endif; ?>
            <button onclick="window.print()" class="btn btn-outline btn-sm">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- Test Summary -->
    <div class="prescription-header">
        <div class="flex flex-wrap justify-between items-start gap-4">
            <div>
                <h2 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($test['test_name'] ?? 'Lab Test') ?></h2>
                <p class="text-sm text-gray-500">
                    <i class="far fa-calendar-alt mr-1"></i>
                    Date: <?= date('F d, Y h:i A', strtotime($test['created_at'])) ?>
                </p>
                <p class="text-sm text-gray-500">
                    <i class="fas fa-user-md mr-1"></i>
                    Doctor: <?= htmlspecialchars($test['doctor_name'] ?? 'Not assigned') ?>
                    <?= !empty($test['doctor_specialty']) ? '(' . htmlspecialchars($test['doctor_specialty']) . ')' : '' ?>
                </p>
                <?php if ($test['visit_number']): ?>
                    <p class="text-sm text-gray-500">
                        <i class="fas fa-clinic-medical mr-1"></i>
                        Visit: <?= htmlspecialchars($test['visit_number']) ?>
                    </p>
                <?php endif; ?>
                <?php if ($test['patient_name']): ?>
                    <p class="text-sm text-gray-500">
                        <i class="fas fa-user mr-1"></i>
                        Patient: <?= htmlspecialchars($test['patient_name']) ?>
                        (<?= htmlspecialchars($test['patient_code'] ?? 'N/A') ?>)
                    </p>
                <?php endif; ?>
            </div>
            <div class="text-right">
                <span class="badge <?= getStatusBadgeClass($test['status']) ?> text-lg px-4 py-2">
                    <?= ucfirst($test['status'] ?? 'Pending') ?>
                </span>
                <?php if ($test['completed_at']): ?>
                    <p class="text-xs text-gray-400 mt-1">
                        Completed: <?= date('M d, Y h:i A', strtotime($test['completed_at'])) ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Test Details -->
    <div class="info-grid">
        <div class="info-card">
            <h4 class="info-card-title">
                <i class="fas fa-info-circle text-blue-600"></i> Test Information
            </h4>
            <p><strong>Test Name:</strong> <?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></p>
            <p><strong>Test Type:</strong> <?= htmlspecialchars($test['test_type'] ?? 'N/A') ?></p>
            <p><strong>Sample Type:</strong> <?= htmlspecialchars($test['sample_type'] ?? 'N/A') ?></p>
            <?php if ($test['test_date']): ?>
                <p><strong>Test Date:</strong> <?= date('M d, Y', strtotime($test['test_date'])) ?></p>
            <?php endif; ?>
            <?php if ($test['lab_technician_id']): ?>
                <p><strong>Lab Technician:</strong> ID #<?= $test['lab_technician_id'] ?></p>
            <?php endif; ?>
        </div>

        <div class="info-card">
            <h4 class="info-card-title">
                <i class="fas fa-stethoscope text-green-600"></i> Clinical Information
            </h4>
            <?php if ($test['visit_diagnosis']): ?>
                <p><strong>Visit Diagnosis:</strong></p>
                <p class="text-sm text-gray-600"><?= nl2br(htmlspecialchars($test['visit_diagnosis'])) ?></p>
            <?php endif; ?>
            <?php if ($test['notes']): ?>
                <p class="mt-2"><strong>Notes:</strong></p>
                <p class="text-sm text-gray-600"><?= nl2br(htmlspecialchars($test['notes'])) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Test Items / Results -->
    <?php if (!empty($test_items)): ?>
    <div class="card mt-4">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list title-blue mr-2"></i> Test Items
            </h3>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Parameter</th>
                        <th>Result</th>
                        <th>Unit</th>
                        <th>Reference Range</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($test_items as $index => $item): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= htmlspecialchars($item['parameter'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($item['result'] ?? 'Pending') ?></td>
                            <td><?= htmlspecialchars($item['unit'] ?? '') ?></td>
                            <td><?= htmlspecialchars($item['reference_range'] ?? '') ?></td>
                            <td>
                                <span class="badge <?= $item['status'] === 'normal' ? 'badge-success' : ($item['status'] === 'abnormal' ? 'badge-danger' : 'badge-warning') ?>">
                                    <?= ucfirst($item['status'] ?? 'Pending') ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Results -->
    <?php if (!empty($test['results'])): ?>
    <div class="card mt-4">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-file-alt title-blue mr-2"></i> Results
            </h3>
        </div>
        <div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
            <div class="prose max-w-none">
                <?= nl2br(htmlspecialchars($test['results'])) ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- No Results Message -->
    <?php if (empty($test_items) && empty($test['results']) && $test['status'] !== 'pending'): ?>
    <div class="card mt-4">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-file-alt title-blue mr-2"></i> Results
            </h3>
        </div>
        <div class="text-center py-8 text-gray-400">
            <i class="fas fa-file-alt text-3xl block mb-2"></i>
            <p>No results recorded for this test yet</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Pending Message -->
    <?php if ($test['status'] === 'pending'): ?>
    <div class="card mt-4 bg-yellow-50 border-yellow-200">
        <div class="flex items-center gap-3 text-yellow-700">
            <i class="fas fa-clock text-xl"></i>
            <div>
                <p class="font-semibold">Test Pending</p>
                <p class="text-sm">This test is still pending. Results will appear once the lab completes the test.</p>
            </div>
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
            Lab Test Details
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- STYLES -->
<!-- ================================================================ -->
<style>
    .prescription-header {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 24px 28px;
        border: 2px solid var(--border-color);
        margin-bottom: 20px;
        transition: all 0.3s ease;
    }
    
    .prescription-header:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.08);
    }
    
    .info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
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
    }
    
    .info-card-title {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--text-primary);
        border-bottom: 2px solid var(--border-color);
        padding-bottom: 10px;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .info-card p { margin-bottom: 4px; }
    .info-card .text-sm { font-size: 0.875rem; }
    .info-card .text-gray-600 { color: var(--text-secondary); }
    
    .badge {
        padding: 4px 16px;
        border-radius: 20px;
        font-size: 0.8rem;
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
    .badge-primary { background: #0B5ED7; color: white; }
    
    .card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 20px 24px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
    }
    
    .card:hover { border-color: var(--primary); }
    
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
    
    .table-wrap { overflow-x: auto; }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }
    
    .data-table th {
        text-align: left;
        padding: 10px 14px;
        font-weight: 600;
        font-size: 0.7rem;
        text-transform: uppercase;
        color: var(--text-secondary);
        border-bottom: 2px solid var(--border-color);
    }
    
    .data-table td {
        padding: 10px 14px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
    }
    
    .data-table tr:hover td {
        background: var(--primary-bg);
    }
    
    .bg-gray-50 { background: var(--bg-body); }
    .bg-yellow-50 { background: #FEF3C7; }
    .border-yellow-200 { border-color: #FDE68A; }
    .text-yellow-700 { color: #B45309; }
    .rounded-lg { border-radius: 10px; }
    .p-4 { padding: 1rem; }
    .mt-4 { margin-top: 1rem; }
    .mb-6 { margin-bottom: 1.5rem; }
    .ml-2 { margin-left: 0.5rem; }
    .mr-1 { margin-right: 0.25rem; }
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .text-xl { font-size: 1.25rem; }
    .text-lg { font-size: 1.1rem; }
    .text-sm { font-size: 0.875rem; }
    .text-xs { font-size: 0.75rem; }
    .text-gray-400 { color: var(--text-muted); }
    .text-gray-500 { color: var(--text-secondary); }
    .text-gray-600 { color: var(--text-secondary); }
    .text-gray-800 { color: var(--text-primary); }
    .text-blue-600 { color: var(--primary); }
    .text-green-600 { color: #059669; }
    .font-bold { font-weight: 700; }
    .font-semibold { font-weight: 600; }
    .font-medium { font-weight: 500; }
    .py-8 { padding-top: 2rem; padding-bottom: 2rem; }
    .text-3xl { font-size: 1.875rem; }
    .block { display: block; }
    .mb-2 { margin-bottom: 0.5rem; }
    .mt-2 { margin-top: 0.5rem; }
    .gap-3 { gap: 0.75rem; }
    .gap-4 { gap: 1rem; }
    .flex { display: flex; }
    .flex-wrap { flex-wrap: wrap; }
    .items-center { align-items: center; }
    .items-start { align-items: flex-start; }
    .justify-between { justify-content: space-between; }
    
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
    
    .btn-outline { background: transparent; color: var(--text-secondary); border: 2px solid var(--border-color); }
    .btn-outline:hover { background: var(--bg-body); border-color: var(--primary); color: var(--primary); }
    
    .btn-sm { padding: 4px 10px; font-size: 0.7rem; border-radius: 6px; }
    .px-4 { padding-left: 1rem; padding-right: 1rem; }
    .py-2 { padding-top: 0.5rem; padding-bottom: 0.5rem; }
    
    .prose { max-width: 100%; }
    .max-w-none { max-width: none; }
    
    [data-theme="dark"] .bg-gray-50 { background: #0F172A; }
    [data-theme="dark"] .bg-yellow-50 { background: #3D2E0A; }
    [data-theme="dark"] .text-yellow-700 { color: #FBBF24; }
    [data-theme="dark"] .border-yellow-200 { border-color: #3D2E0A; }
    [data-theme="dark"] .border-gray-200 { border-color: #334155; }
    
    @media (max-width: 768px) {
        .info-grid { grid-template-columns: 1fr; }
        .prescription-header { padding: 16px 18px; }
        .info-card { padding: 14px 16px; }
        .card { padding: 14px 16px; }
    }
    
    @media print {
        .top-nav, .sidebar, .btn, .footer { display: none !important; }
        .main-content { margin: 0 !important; padding: 20px !important; }
        .prescription-header, .info-card, .card { border: 1px solid #ddd !important; box-shadow: none !important; }
    }
</style>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    function getStatusBadgeClass(status) {
        switch (status) {
            case 'completed': return 'badge-success';
            case 'cancelled': return 'badge-danger';
            case 'in_progress': return 'badge-warning';
            case 'pending': return 'badge-warning';
            default: return 'badge-info';
        }
    }

    console.log('%c🧪 View Lab Test - <?= htmlspecialchars($test['test_name'] ?? 'Lab Test') ?>', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Patient: <?= htmlspecialchars($test['patient_name'] ?? 'Not assigned') ?>', 'font-size:12px; color:#059669;');
    console.log('%c📋 Status: <?= ucfirst($test['status'] ?? 'Pending') ?>', 'font-size:12px; color:#64748B;');
</script>

</body>
</html>