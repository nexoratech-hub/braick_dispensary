<?php
// ================================================================
// FILE: frontend/pages/doctor/view_lab_test.php
// DOCTOR - VIEW LAB TEST (CLEAN CSS - NO EDIT)
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
    die("❌ Database file not found at: " . $db_path);
}
$db = Database::getInstance()->getConnection();

// ================================================================
// GET LAB TEST DETAILS
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
// GET TEST ITEMS / RESULTS DETAILS
// ================================================================
$test_items = [];
try {
    $stmt = $db->prepare("SELECT * FROM lab_test_items WHERE lab_test_id = ?");
    $stmt->execute([$test_id]);
    $test_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $test_items = [];
}

// ================================================================
// FUNCTIONS
// ================================================================
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'completed': return 'badge-success';
        case 'cancelled': return 'badge-danger';
        case 'in_progress': return 'badge-warning';
        case 'pending': return 'badge-warning';
        default: return 'badge-info';
    }
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
                <i class="fas fa-flask mr-2" style="color: #0B5ED7;"></i> Lab Test Details
            </h1>
            <p class="page-subtitle">
                View complete laboratory test information
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor_branch_name) ?>
                </span>
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
        <div class="flex gap-2 flex-wrap">
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

    <!-- ================================================================ -->
    <!-- TEST SUMMARY -->
    <!-- ================================================================ -->
    <div class="summary-header">
        <div class="summary-header-left">
            <h2 class="summary-title"><?= htmlspecialchars($test['test_name'] ?? 'Lab Test') ?></h2>
            <div class="summary-meta">
                <span class="meta-item">
                    <i class="far fa-calendar-alt"></i>
                    <?= date('F d, Y h:i A', strtotime($test['created_at'])) ?>
                </span>
                <span class="meta-item">
                    <i class="fas fa-user-md"></i>
                    Doctor: <?= htmlspecialchars($test['doctor_name'] ?? 'Not assigned') ?>
                    <?= !empty($test['doctor_specialty']) ? '(' . htmlspecialchars($test['doctor_specialty']) . ')' : '' ?>
                </span>
                <?php if ($test['visit_number']): ?>
                    <span class="meta-item">
                        <i class="fas fa-clinic-medical"></i>
                        Visit: <?= htmlspecialchars($test['visit_number']) ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <div class="summary-header-right">
            <span class="status-badge <?= getStatusBadgeClass($test['status']) ?>">
                <i class="fas fa-circle text-[6px]"></i>
                <?= ucfirst($test['status'] ?? 'Pending') ?>
            </span>
            <?php if ($test['completed_at']): ?>
                <span class="completed-date">
                    <i class="fas fa-check-circle text-green-500"></i>
                    Completed: <?= date('M d, Y h:i A', strtotime($test['completed_at'])) ?>
                </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- TEST & PATIENT INFO -->
    <!-- ================================================================ -->
    <div class="info-grid">
        <div class="info-card">
            <h4 class="info-card-title">
                <i class="fas fa-info-circle text-blue-600"></i> Test Information
            </h4>
            <div class="info-card-body">
                <div class="info-row">
                    <span class="info-label">Test Name</span>
                    <span class="info-value font-semibold"><?= htmlspecialchars($test['test_name'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Test Type</span>
                    <span class="info-value"><?= htmlspecialchars($test['test_type'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Sample Type</span>
                    <span class="info-value"><?= htmlspecialchars($test['sample_type'] ?? 'N/A') ?></span>
                </div>
                <?php if ($test['test_date']): ?>
                    <div class="info-row">
                        <span class="info-label">Test Date</span>
                        <span class="info-value"><?= date('M d, Y', strtotime($test['test_date'])) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($test['lab_technician_id']): ?>
                    <div class="info-row">
                        <span class="info-label">Lab Technician</span>
                        <span class="info-value">ID #<?= $test['lab_technician_id'] ?></span>
                    </div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="info-label">Status</span>
                    <span class="info-value">
                        <span class="badge <?= getStatusBadgeClass($test['status']) ?>">
                            <?= ucfirst($test['status'] ?? 'Pending') ?>
                        </span>
                    </span>
                </div>
            </div>
        </div>

        <div class="info-card">
            <h4 class="info-card-title">
                <i class="fas fa-user text-green-600"></i> Patient Information
            </h4>
            <div class="info-card-body">
                <div class="info-row">
                    <span class="info-label">Full Name</span>
                    <span class="info-value font-semibold"><?= htmlspecialchars($test['patient_name'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Patient ID</span>
                    <span class="info-value font-mono"><?= htmlspecialchars($test['patient_code'] ?? 'N/A') ?></span>
                </div>
                <?php if ($test['visit_number']): ?>
                    <div class="info-row">
                        <span class="info-label">Visit Number</span>
                        <span class="info-value font-mono"><?= htmlspecialchars($test['visit_number']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($test['visit_diagnosis']): ?>
                    <div class="info-row">
                        <span class="info-label">Visit Diagnosis</span>
                        <span class="info-value text-sm"><?= nl2br(htmlspecialchars($test['visit_diagnosis'])) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($test['notes']): ?>
                    <div class="info-row">
                        <span class="info-label">Notes</span>
                        <span class="info-value text-sm"><?= nl2br(htmlspecialchars($test['notes'])) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- TEST ITEMS / RESULTS -->
    <!-- ================================================================ -->
    <?php if (!empty($test_items)): ?>
    <div class="result-card">
        <h4 class="result-card-title">
            <i class="fas fa-list text-blue-600 mr-2"></i> Test Results
        </h4>
        <div class="table-wrap">
            <table class="result-table">
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
                            <td class="font-medium"><?= htmlspecialchars($item['parameter'] ?? 'N/A') ?></td>
                            <td class="font-semibold"><?= htmlspecialchars($item['result'] ?? 'Pending') ?></td>
                            <td><?= htmlspecialchars($item['unit'] ?? '') ?></td>
                            <td><?= htmlspecialchars($item['reference_range'] ?? '') ?></td>
                            <td>
                                <span class="badge <?= ($item['status'] ?? '') === 'normal' ? 'badge-success' : (($item['status'] ?? '') === 'abnormal' ? 'badge-danger' : 'badge-warning') ?>">
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

    <!-- ================================================================ -->
    <!-- RESULTS TEXT -->
    <!-- ================================================================ -->
    <?php if (!empty($test['results'])): ?>
    <div class="result-card">
        <h4 class="result-card-title">
            <i class="fas fa-file-alt text-blue-600 mr-2"></i> Results Summary
        </h4>
        <div class="results-content">
            <?= nl2br(htmlspecialchars($test['results'])) ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- PENDING MESSAGE -->
    <!-- ================================================================ -->
    <?php if (($test['status'] ?? '') === 'pending'): ?>
    <div class="pending-message">
        <div class="pending-message-icon">
            <i class="fas fa-clock"></i>
        </div>
        <div>
            <p class="pending-message-title">Test Pending</p>
            <p class="pending-message-text">This test is still pending. Results will appear once the lab completes the test.</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- NO RESULTS MESSAGE -->
    <!-- ================================================================ -->
    <?php if (empty($test_items) && empty($test['results']) && ($test['status'] ?? '') !== 'pending'): ?>
    <div class="no-results">
        <i class="fas fa-file-alt"></i>
        <p>No results recorded for this test yet</p>
    </div>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Lab Test Details
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
       SUMMARY HEADER
       ================================================================ */
    .summary-header {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 24px 28px;
        border: 2px solid var(--border-color);
        margin-bottom: 24px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        transition: all 0.3s ease;
    }
    
    .summary-header:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.08);
    }
    
    .summary-title {
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0 0 6px 0;
    }
    
    .summary-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
    }
    
    .meta-item {
        font-size: 0.8rem;
        color: var(--text-secondary);
        display: flex;
        align-items: center;
        gap: 6px;
    }
    
    .meta-item i {
        font-size: 0.8rem;
        color: var(--primary);
    }
    
    .summary-header-right {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 4px;
    }
    
    .status-badge {
        padding: 6px 18px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: white;
        border: none;
    }
    
    .status-badge.badge-success { background: #059669; }
    .status-badge.badge-danger { background: #EF4444; }
    .status-badge.badge-warning { background: #D97706; }
    .status-badge.badge-info { background: var(--primary); }
    
    .completed-date {
        font-size: 0.7rem;
        color: var(--text-secondary);
        display: flex;
        align-items: center;
        gap: 4px;
    }
    
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
    .info-value.text-sm { font-size: 0.8rem; }
    
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
    
    .results-content {
        padding: 16px;
        background: var(--bg-body);
        border-radius: 10px;
        border: 1px solid var(--border-color);
        font-size: 0.9rem;
        color: var(--text-primary);
        line-height: 1.6;
        white-space: pre-wrap;
    }
    
    /* ================================================================
       RESULT TABLE
       ================================================================ */
    .table-wrap {
        overflow-x: auto;
    }
    
    .result-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }
    
    .result-table thead th {
        text-align: left;
        padding: 10px 14px;
        font-weight: 700;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: white;
        background: var(--primary);
        border-bottom: 3px solid var(--primary-dark);
        white-space: nowrap;
    }
    
    .result-table thead th:first-child {
        border-radius: 8px 0 0 0;
    }
    
    .result-table thead th:last-child {
        border-radius: 0 8px 0 0;
    }
    
    .result-table tbody tr:nth-child(even) {
        background: var(--primary-bg);
    }
    
    .result-table tbody tr:nth-child(odd) {
        background: var(--bg-card);
    }
    
    .result-table tbody tr:hover {
        background: #D1FAE5;
    }
    
    [data-theme="dark"] .result-table tbody tr:hover {
        background: #1A3A2A;
    }
    
    .result-table td {
        padding: 10px 14px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        vertical-align: middle;
    }
    
    .result-table td .font-medium { font-weight: 500; }
    .result-table td .font-semibold { font-weight: 600; }
    
    /* ================================================================
       BADGE
       ================================================================ */
    .badge {
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: white;
        border: none;
    }
    
    .badge-success { background: #059669; }
    .badge-danger { background: #EF4444; }
    .badge-warning { background: #D97706; }
    .badge-info { background: var(--primary); }
    
    /* ================================================================
       PENDING MESSAGE
       ================================================================ */
    .pending-message {
        background: #FEF3C7;
        border: 1px solid #FDE68A;
        border-radius: 16px;
        padding: 20px 24px;
        display: flex;
        align-items: center;
        gap: 16px;
        margin-bottom: 24px;
    }
    
    [data-theme="dark"] .pending-message {
        background: #3D2E0A;
        border-color: #F59E0B;
    }
    
    .pending-message-icon {
        width: 48px;
        height: 48px;
        background: #F59E0B;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        color: white;
        flex-shrink: 0;
    }
    
    .pending-message-title {
        font-size: 1rem;
        font-weight: 600;
        color: #92400E;
        margin: 0;
    }
    
    [data-theme="dark"] .pending-message-title {
        color: #FBBF24;
    }
    
    .pending-message-text {
        font-size: 0.85rem;
        color: #B45309;
        margin: 0;
    }
    
    [data-theme="dark"] .pending-message-text {
        color: #FDE68A;
    }
    
    /* ================================================================
       NO RESULTS
       ================================================================ */
    .no-results {
        text-align: center;
        padding: 40px 20px;
        background: var(--bg-card);
        border-radius: 16px;
        border: 2px solid var(--border-color);
        color: var(--text-muted);
        margin-bottom: 24px;
    }
    
    .no-results i {
        font-size: 2.5rem;
        color: var(--border-color);
        display: block;
        margin-bottom: 8px;
    }
    
    .no-results p {
        font-size: 0.9rem;
    }
    
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
    .text-gray-400 { color: var(--text-muted); }
    .text-gray-500 { color: var(--text-secondary); }
    .text-blue-600 { color: var(--primary); }
    .text-green-600 { color: #059669; }
    .text-green-500 { color: #059669; }
    .font-semibold { font-weight: 600; }
    .font-bold { font-weight: 700; }
    .font-mono { font-family: monospace; }
    .gap-2 { gap: 0.5rem; }
    .gap-3 { gap: 0.75rem; }
    .gap-4 { gap: 1rem; }
    .gap-6 { gap: 1.5rem; }
    .ml-2 { margin-left: 0.5rem; }
    .mr-1 { margin-right: 0.25rem; }
    .mr-2 { margin-right: 0.5rem; }
    .mb-6 { margin-bottom: 1.5rem; }
    .flex { display: flex; }
    .flex-wrap { flex-wrap: wrap; }
    .items-center { align-items: center; }
    .items-start { align-items: flex-start; }
    .justify-between { justify-content: space-between; }
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    
    /* ================================================================
       DARK MODE
       ================================================================ */
    [data-theme="dark"] .summary-header {
        background: #1E293B;
        border-color: #334155;
    }
    [data-theme="dark"] .summary-title {
        color: #F1F5F9;
    }
    [data-theme="dark"] .meta-item {
        color: #94A3B8;
    }
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
    [data-theme="dark"] .result-card {
        background: #1E293B;
        border-color: #334155;
    }
    [data-theme="dark"] .result-card-title {
        color: #F1F5F9;
        border-color: #334155;
    }
    [data-theme="dark"] .results-content {
        background: #0F172A;
        border-color: #334155;
        color: #F1F5F9;
    }
    [data-theme="dark"] .no-results {
        background: #1E293B;
        border-color: #334155;
    }
    [data-theme="dark"] .footer {
        border-color: #334155;
        color: #64748B;
    }
    [data-theme="dark"] .result-table tbody tr:nth-child(even) {
        background: #1E293B;
    }
    [data-theme="dark"] .result-table tbody tr:nth-child(odd) {
        background: #1E293B;
    }
    
    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 768px) {
        .info-grid {
            grid-template-columns: 1fr;
        }
        .summary-header {
            flex-direction: column;
            align-items: flex-start;
            padding: 16px 18px;
        }
        .summary-header-right {
            align-items: flex-start;
            width: 100%;
        }
        .summary-title {
            font-size: 1.1rem;
        }
        .summary-meta {
            flex-direction: column;
            gap: 4px;
        }
        .page-header .page-title {
            font-size: 1.2rem;
        }
        .info-card {
            padding: 14px 16px;
        }
        .result-card {
            padding: 14px 16px;
        }
        .result-table {
            font-size: 0.75rem;
        }
        .result-table th,
        .result-table td {
            padding: 6px 10px;
        }
        .info-row {
            flex-direction: column;
            align-items: flex-start;
        }
        .info-value {
            text-align: left;
            max-width: 100%;
        }
        .pending-message {
            flex-direction: column;
            text-align: center;
            padding: 16px;
        }
    }
    
    @media (max-width: 480px) {
        .info-grid {
            grid-template-columns: 1fr;
        }
        .summary-title {
            font-size: 1rem;
        }
        .summary-header {
            padding: 12px 14px;
        }
        .page-header .page-title {
            font-size: 1rem;
        }
        .info-card {
            padding: 10px 12px;
        }
        .result-card {
            padding: 10px 12px;
        }
        .result-table th,
        .result-table td {
            padding: 4px 6px;
            font-size: 0.7rem;
        }
        .btn {
            font-size: 0.7rem;
            padding: 4px 10px;
        }
        .status-badge {
            font-size: 0.7rem;
            padding: 4px 12px;
        }
        .branch-tag {
            font-size: 0.6rem;
            padding: 2px 10px;
        }
    }
    
    @media print {
        .top-nav, .sidebar, .btn, .footer { display: none !important; }
        .main-content { margin: 0 !important; padding: 20px !important; }
        .summary-header, .info-card, .result-card { 
            border: 1px solid #ddd !important; 
            box-shadow: none !important; 
            page-break-inside: avoid;
        }
        .page-header { border-bottom: 2px solid #0B5ED7 !important; }
        .result-table thead th { background: #0B5ED7 !important; color: white !important; }
        .summary-header { background: white !important; }
        .info-card { background: white !important; }
        .result-card { background: white !important; }
        .pending-message { background: #FEF3C7 !important; }
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

    console.log('%c🧪 View Lab Test - <?= htmlspecialchars($test['test_name'] ?? 'Lab Test') ?>', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Patient: <?= htmlspecialchars($test['patient_name'] ?? 'N/A') ?>', 'font-size:12px; color:#059669;');
    console.log('%c📋 Status: <?= ucfirst($test['status'] ?? 'Pending') ?>', 'font-size:12px; color:#64748B;');
    console.log('%c🔒 Doctor: View Only - No Edit Button', 'font-size:12px; color:#EF4444;');
</script>

</body>
</html>