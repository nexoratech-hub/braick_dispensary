<?php
// ================================================================
// FILE: frontend/pages/doctor/view_prescription.php
// DOCTOR - VIEW SINGLE PRESCRIPTION (BEAUTIFUL CSS)
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
// GET PRESCRIPTION ID
// ================================================================
$prescription_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($prescription_id <= 0) {
    header('Location: view_prescriptions.php');
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
// GET PRESCRIPTION DETAILS
// ================================================================
$stmt = $db->prepare("
    SELECT 
        pr.*,
        p.full_name as patient_name,
        p.patient_id as patient_code,
        p.phone,
        p.email,
        p.date_of_birth,
        p.gender,
        u.full_name as doctor_name,
        u.specialty as doctor_specialty,
        v.visit_number,
        v.diagnosis as visit_diagnosis
    FROM prescriptions pr
    JOIN patients p ON pr.patient_id = p.id
    JOIN users u ON pr.doctor_id = u.id
    LEFT JOIN visits v ON pr.visit_id = v.id
    WHERE pr.id = ? AND pr.doctor_id = ?
");
$stmt->execute([$prescription_id, $doctor_id]);
$prescription = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$prescription) {
    header('Location: view_prescriptions.php?error=not_found');
    exit;
}

// ================================================================
// GET PRESCRIPTION ITEMS
// ================================================================
$stmt = $db->prepare("
    SELECT * FROM prescription_items 
    WHERE prescription_id = ?
");
$stmt->execute([$prescription_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STATUS BADGE CLASS
// ================================================================
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'dispensed': return 'badge-success';
        case 'cancelled': return 'badge-danger';
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
// CALCULATE AGE
// ================================================================
function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    $age = $birthDate->diff($today)->y;
    return $age;
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

    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-6">
        <div>
            <h1 class="page-title">
                <i class="fas fa-prescription mr-2" style="color: #0B5ED7;"></i> Prescription Details
            </h1>
            <p class="page-subtitle">
                View complete prescription information
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor_branch_name) ?>
                </span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-hashtag mr-1"></i> <?= htmlspecialchars($prescription['prescription_number']) ?>
                </span>
                <span class="ml-2 inline-flex bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs border border-green-200">
                    <i class="fas fa-user mr-1"></i> <?= htmlspecialchars($prescription['patient_name']) ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="view_prescriptions.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <button onclick="window.print()" class="btn btn-outline btn-sm">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PRESCRIPTION HEADER -->
    <!-- ================================================================ -->
    <div class="prescription-header">
        <div class="prescription-header-top">
            <div class="prescription-header-left">
                <h2 class="prescription-number">
                    <i class="fas fa-prescription text-blue-600"></i>
                    <?= htmlspecialchars($prescription['prescription_number']) ?>
                </h2>
                <div class="prescription-meta">
                    <span class="meta-item">
                        <i class="far fa-calendar-alt"></i>
                        <?= date('F d, Y h:i A', strtotime($prescription['created_at'])) ?>
                    </span>
                    <span class="meta-item">
                        <i class="fas fa-user-md"></i>
                        Dr. <?= htmlspecialchars($prescription['doctor_name']) ?>
                        <?= !empty($prescription['doctor_specialty']) ? '(' . htmlspecialchars($prescription['doctor_specialty']) . ')' : '' ?>
                    </span>
                    <?php if ($prescription['visit_number']): ?>
                        <span class="meta-item">
                            <i class="fas fa-clinic-medical"></i>
                            Visit: <?= htmlspecialchars($prescription['visit_number']) ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="prescription-header-right">
                <span class="status-badge <?= getStatusBadgeClass($prescription['status']) ?>">
                    <i class="fas fa-circle text-[6px]"></i>
                    <?= ucfirst($prescription['status'] ?? 'Pending') ?>
                </span>
                <?php if ($prescription['dispensed_at']): ?>
                    <span class="dispensed-date">
                        <i class="fas fa-check-circle text-green-500"></i>
                        Dispensed: <?= date('M d, Y h:i A', strtotime($prescription['dispensed_at'])) ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PATIENT & DIAGNOSIS INFO -->
    <!-- ================================================================ -->
    <div class="info-grid">
        <div class="info-card">
            <h4 class="info-card-title">
                <i class="fas fa-user text-blue-600"></i> Patient Information
            </h4>
            <div class="info-card-body">
                <div class="info-row">
                    <span class="info-label">Full Name</span>
                    <span class="info-value font-semibold"><?= htmlspecialchars($prescription['patient_name']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Patient ID</span>
                    <span class="info-value font-mono"><?= htmlspecialchars($prescription['patient_code']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Gender</span>
                    <span class="info-value"><?= htmlspecialchars($prescription['gender'] ?? 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Date of Birth</span>
                    <span class="info-value"><?= !empty($prescription['date_of_birth']) ? date('M d, Y', strtotime($prescription['date_of_birth'])) : 'N/A' ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Age</span>
                    <span class="info-value"><?= calculateAge($prescription['date_of_birth'] ?? '') ?> years</span>
                </div>
                <?php if ($prescription['phone']): ?>
                    <div class="info-row">
                        <span class="info-label">Phone</span>
                        <span class="info-value"><?= htmlspecialchars($prescription['phone']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($prescription['email']): ?>
                    <div class="info-row">
                        <span class="info-label">Email</span>
                        <span class="info-value"><?= htmlspecialchars($prescription['email']) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="info-card">
            <h4 class="info-card-title">
                <i class="fas fa-stethoscope text-green-600"></i> Diagnosis
            </h4>
            <div class="info-card-body">
                <div class="info-row">
                    <span class="info-label">Diagnosis</span>
                    <span class="info-value">
                        <?= !empty($prescription['diagnosis']) ? nl2br(htmlspecialchars($prescription['diagnosis'])) : '<span class="text-gray-400">No diagnosis recorded</span>' ?>
                    </span>
                </div>
                <?php if ($prescription['visit_diagnosis'] && $prescription['diagnosis'] !== $prescription['visit_diagnosis']): ?>
                    <div class="info-row">
                        <span class="info-label">Visit Diagnosis</span>
                        <span class="info-value text-sm text-gray-500"><?= htmlspecialchars($prescription['visit_diagnosis']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($prescription['notes']): ?>
                    <div class="info-row">
                        <span class="info-label">Notes</span>
                        <span class="info-value text-sm"><?= nl2br(htmlspecialchars($prescription['notes'])) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- MEDICATION ITEMS -->
    <!-- ================================================================ -->
    <div class="medication-card">
        <div class="medication-card-header">
            <h3 class="medication-card-title">
                <i class="fas fa-pills text-blue-600 mr-2"></i>
                Medication Items
                <span class="text-sm font-normal text-gray-400">(<?= count($items) ?> item<?= count($items) > 1 ? 's' : '' ?>)</span>
            </h3>
        </div>
        
        <?php if (count($items) > 0): ?>
            <div class="table-wrap">
                <table class="medication-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Medication</th>
                            <th>Dosage</th>
                            <th>Frequency</th>
                            <th>Quantity</th>
                            <th>Duration</th>
                            <th>Instructions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $index => $item): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td class="font-semibold text-gray-800"><?= htmlspecialchars($item['medication_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($item['dosage'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($item['frequency'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($item['quantity'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($item['duration'] ?? 'N/A') ?></td>
                                <td class="text-sm text-gray-500"><?= htmlspecialchars($item['instructions'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-pills"></i>
                <p>No medication items found for this prescription</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- SUMMARY CARD -->
    <!-- ================================================================ -->
    <div class="summary-card">
        <h4 class="summary-card-title">
            <i class="fas fa-file-alt text-blue-600"></i> Prescription Summary
        </h4>
        <div class="summary-grid">
            <div class="summary-item">
                <span class="summary-label">Prescription Number</span>
                <span class="summary-value font-mono"><?= htmlspecialchars($prescription['prescription_number']) ?></span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Patient</span>
                <span class="summary-value"><?= htmlspecialchars($prescription['patient_name']) ?></span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Doctor</span>
                <span class="summary-value">Dr. <?= htmlspecialchars($prescription['doctor_name']) ?></span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Status</span>
                <span class="summary-value">
                    <span class="badge <?= getStatusBadgeClass($prescription['status']) ?>">
                        <?= ucfirst($prescription['status'] ?? 'Pending') ?>
                    </span>
                </span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Total Items</span>
                <span class="summary-value"><?= count($items) ?> item(s)</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Created</span>
                <span class="summary-value"><?= date('M d, Y h:i A', strtotime($prescription['created_at'])) ?></span>
            </div>
            <?php if ($prescription['dispensed_at']): ?>
                <div class="summary-item">
                    <span class="summary-label">Dispensed</span>
                    <span class="summary-value text-green-600"><?= date('M d, Y h:i A', strtotime($prescription['dispensed_at'])) ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Prescription Details
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
       PRESCRIPTION HEADER
       ================================================================ */
    .prescription-header {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 24px 28px;
        border: 2px solid var(--border-color);
        margin-bottom: 24px;
        transition: all 0.3s ease;
    }
    
    .prescription-header:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.08);
    }
    
    .prescription-header-top {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
    }
    
    .prescription-number {
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0 0 6px 0;
    }
    
    .prescription-meta {
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
    
    .prescription-header-right {
        text-align: right;
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
    
    .dispensed-date {
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
    .info-value.text-gray-500 { color: var(--text-secondary); }
    
    /* ================================================================
       MEDICATION CARD
       ================================================================ */
    .medication-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 20px 24px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        margin-bottom: 24px;
    }
    
    .medication-card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.08);
    }
    
    .medication-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .medication-card-title {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
        display: flex;
        align-items: center;
    }
    
    .table-wrap {
        overflow-x: auto;
    }
    
    .medication-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }
    
    .medication-table thead th {
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
    
    .medication-table thead th:first-child {
        border-radius: 8px 0 0 0;
    }
    
    .medication-table thead th:last-child {
        border-radius: 0 8px 0 0;
    }
    
    .medication-table tbody tr:nth-child(even) {
        background: var(--primary-bg);
    }
    
    .medication-table tbody tr:nth-child(odd) {
        background: var(--bg-card);
    }
    
    .medication-table tbody tr:hover {
        background: #D1FAE5;
    }
    
    .medication-table td {
        padding: 10px 14px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        vertical-align: middle;
    }
    
    .medication-table td .font-semibold { font-weight: 600; }
    .medication-table td .text-gray-800 { color: var(--text-primary); }
    .medication-table td .text-sm { font-size: 0.8rem; }
    .medication-table td .text-gray-500 { color: var(--text-secondary); }
    
    /* ================================================================
       SUMMARY CARD
       ================================================================ */
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
    
    .summary-card-title {
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
    
    .summary-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 6px 24px;
    }
    
    .summary-item {
        display: flex;
        justify-content: space-between;
        padding: 4px 0;
        border-bottom: 1px solid var(--border-color);
    }
    
    .summary-item:last-child {
        border-bottom: none;
    }
    
    .summary-label {
        font-size: 0.8rem;
        color: var(--text-secondary);
        font-weight: 500;
    }
    
    .summary-value {
        font-size: 0.85rem;
        color: var(--text-primary);
        font-weight: 500;
        text-align: right;
    }
    
    .summary-value.font-mono { font-family: monospace; }
    .summary-value.text-green-600 { color: #059669; }
    
    /* ================================================================
       EMPTY STATE
       ================================================================ */
    .empty-state {
        text-align: center;
        padding: 30px 20px;
        color: var(--text-secondary);
    }
    
    .empty-state i {
        font-size: 2.5rem;
        color: var(--border-color);
        display: block;
        margin-bottom: 8px;
    }
    
    .empty-state p {
        font-size: 0.9rem;
    }
    
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
    .text-red-500 { color: #EF4444; }
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
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    
    /* ================================================================
       DARK MODE
       ================================================================ */
    [data-theme="dark"] .prescription-header {
        background: #1E293B;
        border-color: #334155;
    }
    [data-theme="dark"] .prescription-number {
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
    [data-theme="dark"] .medication-card {
        background: #1E293B;
        border-color: #334155;
    }
    [data-theme="dark"] .medication-card-title {
        color: #F1F5F9;
    }
    [data-theme="dark"] .medication-table tbody tr:nth-child(even) {
        background: #1E293B;
    }
    [data-theme="dark"] .medication-table tbody tr:nth-child(odd) {
        background: #1E293B;
    }
    [data-theme="dark"] .medication-table tbody tr:hover {
        background: #1A3A2A;
    }
    [data-theme="dark"] .medication-table td {
        color: #F1F5F9;
    }
    [data-theme="dark"] .medication-table td .text-gray-800 {
        color: #F1F5F9;
    }
    [data-theme="dark"] .summary-card {
        background: #1E293B;
        border-color: #334155;
    }
    [data-theme="dark"] .summary-card-title {
        color: #F1F5F9;
        border-color: #334155;
    }
    [data-theme="dark"] .summary-item {
        border-color: #334155;
    }
    [data-theme="dark"] .summary-value {
        color: #F1F5F9;
    }
    [data-theme="dark"] .footer {
        border-color: #334155;
        color: #64748B;
    }
    [data-theme="dark"] .empty-state i {
        color: #334155;
    }
    
    /* ================================================================
       RESPONSIVE
       ================================================================ */
    @media (max-width: 768px) {
        .info-grid {
            grid-template-columns: 1fr;
        }
        .summary-grid {
            grid-template-columns: 1fr;
        }
        .prescription-header-top {
            flex-direction: column;
            align-items: flex-start;
        }
        .prescription-header-right {
            text-align: left;
            align-items: flex-start;
            width: 100%;
        }
        .prescription-number {
            font-size: 1.1rem;
        }
        .prescription-meta {
            flex-direction: column;
            gap: 4px;
        }
        .page-header .page-title {
            font-size: 1.2rem;
        }
        .medication-card {
            padding: 14px 16px;
        }
        .medication-table {
            font-size: 0.75rem;
        }
        .medication-table th,
        .medication-table td {
            padding: 6px 10px;
        }
        .info-card {
            padding: 14px 16px;
        }
        .summary-card {
            padding: 14px 16px;
        }
        .prescription-header {
            padding: 16px 18px;
        }
        .info-row {
            flex-direction: column;
            align-items: flex-start;
        }
        .info-value {
            text-align: left;
            max-width: 100%;
        }
    }
    
    @media (max-width: 480px) {
        .info-grid {
            grid-template-columns: 1fr;
        }
        .summary-grid {
            grid-template-columns: 1fr;
        }
        .prescription-number {
            font-size: 1rem;
        }
        .prescription-meta {
            font-size: 0.7rem;
        }
        .prescription-header {
            padding: 12px 14px;
        }
        .page-header .page-title {
            font-size: 1rem;
        }
        .medication-card {
            padding: 10px 12px;
        }
        .medication-table th,
        .medication-table td {
            padding: 4px 6px;
            font-size: 0.7rem;
        }
        .info-card {
            padding: 10px 12px;
        }
        .summary-card {
            padding: 10px 12px;
        }
        .summary-grid {
            gap: 4px;
        }
        .summary-item {
            flex-direction: column;
            align-items: flex-start;
        }
        .summary-value {
            text-align: left;
        }
        .btn {
            font-size: 0.7rem;
            padding: 4px 10px;
        }
        .branch-tag {
            font-size: 0.6rem;
            padding: 2px 10px;
        }
        .status-badge {
            font-size: 0.7rem;
            padding: 4px 12px;
        }
    }
    
    @media print {
        .top-nav, .sidebar, .btn, .footer { display: none !important; }
        .main-content { margin: 0 !important; padding: 20px !important; }
        .prescription-header, .info-card, .medication-card, .summary-card { 
            border: 1px solid #ddd !important; 
            box-shadow: none !important; 
            page-break-inside: avoid;
        }
        .page-header { border-bottom: 2px solid #0B5ED7 !important; }
        .medication-table thead th { background: #0B5ED7 !important; color: white !important; }
        .prescription-header { background: white !important; }
        .info-card { background: white !important; }
        .medication-card { background: white !important; }
        .summary-card { background: white !important; }
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

    console.log('%c💊 Prescription Details - <?= htmlspecialchars($prescription['prescription_number']) ?>', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Patient: <?= htmlspecialchars($prescription['patient_name']) ?>', 'font-size:12px; color:#059669;');
    console.log('%c💊 Items: <?= count($items) ?>', 'font-size:12px; color:#64748B;');
    console.log('%c📋 Status: <?= ucfirst($prescription['status'] ?? 'Pending') ?>', 'font-size:12px; color:#64748B;');
</script>

</body>
</html>