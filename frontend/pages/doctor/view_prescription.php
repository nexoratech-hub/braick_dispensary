<?php
// ================================================================
// FILE: frontend/pages/doctor/view_prescription.php
// DOCTOR - VIEW SINGLE PRESCRIPTION
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
    die("❌ Database file not found");
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
                <i class="fas fa-prescription mr-2" style="color: #0B5ED7;"></i> Prescription Details
            </h1>
            <p class="page-subtitle">
                View complete prescription information
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-hashtag mr-1"></i> <?= htmlspecialchars($prescription['prescription_number']) ?>
                </span>
                <span class="ml-2 inline-flex bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs border border-green-200">
                    <i class="fas fa-user mr-1"></i> <?= htmlspecialchars($prescription['patient_name']) ?>
                </span>
            </p>
        </div>
        <div class="flex gap-2">
            <a href="view_prescriptions.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <?php if ($prescription['status'] === 'pending'): ?>
                <a href="edit_prescription.php?id=<?= $prescription_id ?>" class="btn btn-edit btn-sm">
                    <i class="fas fa-edit"></i> Edit
                </a>
            <?php endif; ?>
            <button onclick="window.print()" class="btn btn-outline btn-sm">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PRESCRIPTION HEADER CARD -->
    <!-- ================================================================ -->
    <div class="prescription-header">
        <div class="flex flex-wrap justify-between items-start gap-4">
            <div>
                <h2 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($prescription['prescription_number']) ?></h2>
                <p class="text-sm text-gray-500">
                    <i class="far fa-calendar-alt mr-1"></i>
                    Date: <?= date('F d, Y h:i A', strtotime($prescription['created_at'])) ?>
                </p>
                <p class="text-sm text-gray-500">
                    <i class="fas fa-user-md mr-1"></i>
                    Doctor: <?= htmlspecialchars($prescription['doctor_name']) ?>
                    <?= !empty($prescription['doctor_specialty']) ? '(' . htmlspecialchars($prescription['doctor_specialty']) . ')' : '' ?>
                </p>
            </div>
            <div class="text-right">
                <span class="badge <?= getStatusBadgeClass($prescription['status']) ?> text-lg px-4 py-2">
                    <?= ucfirst($prescription['status'] ?? 'Pending') ?>
                </span>
                <p class="text-xs text-gray-400 mt-1">
                    <?php if ($prescription['dispensed_at']): ?>
                        Dispensed: <?= date('M d, Y h:i A', strtotime($prescription['dispensed_at'])) ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PATIENT INFO -->
    <!-- ================================================================ -->
    <div class="info-grid">
        <div class="info-card">
            <h4 class="info-card-title">
                <i class="fas fa-user text-blue-600"></i> Patient Information
            </h4>
            <p><strong><?= htmlspecialchars($prescription['patient_name']) ?></strong></p>
            <p class="text-sm text-gray-500">
                ID: <?= htmlspecialchars($prescription['patient_code']) ?>
            </p>
            <?php if ($prescription['phone']): ?>
                <p class="text-sm text-gray-500">
                    <i class="fas fa-phone mr-1"></i> <?= htmlspecialchars($prescription['phone']) ?>
                </p>
            <?php endif; ?>
            <?php if ($prescription['email']): ?>
                <p class="text-sm text-gray-500">
                    <i class="fas fa-envelope mr-1"></i> <?= htmlspecialchars($prescription['email']) ?>
                </p>
            <?php endif; ?>
            <?php if ($prescription['visit_number']): ?>
                <p class="text-sm text-gray-500">
                    <i class="fas fa-clinic-medical mr-1"></i> Visit: <?= htmlspecialchars($prescription['visit_number']) ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="info-card">
            <h4 class="info-card-title">
                <i class="fas fa-stethoscope text-green-600"></i> Diagnosis
            </h4>
            <p class="text-gray-700">
                <?= !empty($prescription['diagnosis']) ? nl2br(htmlspecialchars($prescription['diagnosis'])) : '<span class="text-gray-400">No diagnosis recorded</span>' ?>
            </p>
            <?php if ($prescription['visit_diagnosis']): ?>
                <p class="text-xs text-gray-400 mt-2">
                    <i class="fas fa-info-circle mr-1"></i>
                    Visit Diagnosis: <?= htmlspecialchars($prescription['visit_diagnosis']) ?>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- MEDICATION DETAILS -->
    <!-- ================================================================ -->
    <div class="card mt-4">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-pills title-blue mr-2"></i> Medication Details
                <span class="text-sm font-normal text-gray-400">(<?= count($items) ?> item<?= count($items) > 1 ? 's' : '' ?>)</span>
            </h3>
        </div>
        
        <?php if (count($items) > 0): ?>
            <div class="table-wrap">
                <table class="data-table">
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
                                <td class="font-medium"><?= htmlspecialchars($item['medication_name'] ?? 'N/A') ?></td>
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
            <div class="text-center py-6 text-gray-400">
                <i class="fas fa-pills text-2xl block mb-2"></i>
                <p>No medication items found for this prescription</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================================================================ -->
    <!-- INSTRUCTIONS & NOTES -->
    <!-- ================================================================ -->
    <?php if (!empty($prescription['notes'])): ?>
    <div class="card mt-4">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-info-circle title-blue mr-2"></i> Notes & Instructions
            </h3>
        </div>
        <div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
            <p class="text-gray-700 whitespace-pre-wrap"><?= nl2br(htmlspecialchars($prescription['notes'])) ?></p>
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
            Prescription Details
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
    
    .info-card p {
        margin-bottom: 4px;
    }
    
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
    
    .text-xl { font-size: 1.25rem; }
    .text-lg { font-size: 1.1rem; }
    .text-sm { font-size: 0.875rem; }
    .text-xs { font-size: 0.75rem; }
    .text-gray-400 { color: var(--text-muted); }
    .text-gray-500 { color: var(--text-secondary); }
    .text-gray-700 { color: var(--text-primary); }
    .text-gray-800 { color: var(--text-primary); }
    .text-blue-600 { color: var(--primary); }
    .text-green-600 { color: #059669; }
    .font-bold { font-weight: 700; }
    .font-medium { font-weight: 500; }
    
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
    
    .table-wrap { overflow-x: auto; }
    .data-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
    .data-table th { text-align: left; padding: 10px 14px; font-weight: 600; font-size: 0.7rem; text-transform: uppercase; color: var(--text-secondary); border-bottom: 2px solid var(--border-color); }
    .data-table td { padding: 10px 14px; border-bottom: 1px solid var(--border-color); color: var(--text-primary); }
    .data-table tr:hover td { background: var(--primary-bg); }
    
    .bg-gray-50 { background: var(--bg-body); }
    .border-gray-200 { border-color: var(--border-color); }
    .rounded-lg { border-radius: 10px; }
    .p-4 { padding: 1rem; }
    .mt-4 { margin-top: 1rem; }
    .mb-6 { margin-bottom: 1.5rem; }
    .ml-2 { margin-left: 0.5rem; }
    .mr-1 { margin-right: 0.25rem; }
    .mr-2 { margin-right: 0.5rem; }
    .gap-3 { gap: 0.75rem; }
    .gap-4 { gap: 1rem; }
    .flex { display: flex; }
    .flex-wrap { flex-wrap: wrap; }
    .items-start { align-items: flex-start; }
    .items-center { align-items: center; }
    .justify-between { justify-content: space-between; }
    .text-right { text-align: right; }
    .whitespace-pre-wrap { white-space: pre-wrap; }
    .px-4 { padding-left: 1rem; padding-right: 1rem; }
    .py-2 { padding-top: 0.5rem; padding-bottom: 0.5rem; }
    
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
    
    .btn-edit { background: #059669; color: white; }
    .btn-edit:hover { background: #047857; transform: scale(1.05); }
    
    .btn-outline { background: transparent; color: var(--text-secondary); border: 2px solid var(--border-color); }
    .btn-outline:hover { background: var(--bg-body); border-color: var(--primary); color: var(--primary); }
    
    .btn-sm { padding: 4px 10px; font-size: 0.7rem; border-radius: 6px; }
    
    [data-theme="dark"] .bg-gray-50 { background: #0F172A; }
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
            case 'dispensed': return 'badge-success';
            case 'cancelled': return 'badge-danger';
            case 'pending': return 'badge-warning';
            default: return 'badge-info';
        }
    }

    console.log('%c💊 View Prescription - <?= htmlspecialchars($prescription['prescription_number']) ?>', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Patient: <?= htmlspecialchars($prescription['patient_name']) ?>', 'font-size:12px; color:#059669;');
    console.log('%c💊 Items: <?= count($items) ?>', 'font-size:12px; color:#64748B;');
    console.log('%c📋 Status: <?= ucfirst($prescription['status'] ?? 'Pending') ?>', 'font-size:12px; color:#64748B;');
</script>

</body>
</html>