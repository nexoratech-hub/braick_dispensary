<?php
// ================================================================
// FILE: frontend/pages/doctor/documents.php
// DOCTOR - DOCUMENTS
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
// GET DOCUMENTS
// ================================================================
$stmt = $db->prepare("
    SELECT pd.*, p.full_name as patient_name, p.patient_id
    FROM patient_documents pd
    JOIN patients p ON pd.patient_id = p.id
    WHERE pd.doctor_id = ?
    ORDER BY pd.upload_date DESC
");
$stmt->execute([$doctor_id]);
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STATISTICS
// ================================================================
$total_docs = count($documents);
$verified_docs = 0;
foreach ($documents as $d) {
    if ($d['is_verified']) $verified_docs++;
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

    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-6">
        <div>
            <h1 class="page-title">
                <i class="fas fa-folder mr-2" style="color: #0B5ED7;"></i> Documents
            </h1>
            <p class="page-subtitle">
                Manage patient documents
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor_branch_name) ?>
                </span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-file mr-1"></i> <?= $total_docs ?> documents
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="upload_document.php" class="btn btn-blue btn-sm">
                <i class="fas fa-upload"></i> Upload Document
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-6">
        <div class="stat-card blue animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Total Documents</p>
                    <p class="stat-number"><?= $total_docs ?></p>
                </div>
                <div class="stat-icon"><i class="fas fa-folder"></i></div>
            </div>
        </div>
        <div class="stat-card green animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Verified</p>
                    <p class="stat-number"><?= $verified_docs ?></p>
                </div>
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            </div>
        </div>
        <div class="stat-card orange animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Pending Verification</p>
                    <p class="stat-number"><?= $total_docs - $verified_docs ?></p>
                </div>
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
            </div>
        </div>
    </div>

    <!-- Documents Table -->
    <div class="card">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="border-radius: 8px 0 0 0;">#</th>
                        <th>Document Name</th>
                        <th>Patient</th>
                        <th>Type</th>
                        <th>Upload Date</th>
                        <th>Status</th>
                        <th style="border-radius: 0 8px 0 0; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($documents) > 0): ?>
                        <?php foreach ($documents as $index => $doc): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td class="font-medium"><?= htmlspecialchars($doc['document_name']) ?></td>
                                <td>
                                    <div>
                                        <div class="font-medium"><?= htmlspecialchars($doc['patient_name']) ?></div>
                                        <div class="text-xs text-gray-400"><?= htmlspecialchars($doc['patient_id'] ?? 'N/A') ?></div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-info"><?= str_replace('_', ' ', ucfirst($doc['document_type'] ?? 'Other')) ?></span>
                                </td>
                                <td><?= date('M d, Y', strtotime($doc['upload_date'])) ?></td>
                                <td>
                                    <span class="badge <?= $doc['is_verified'] ? 'badge-success' : 'badge-warning' ?>">
                                        <?= $doc['is_verified'] ? 'Verified' : 'Pending' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view_document.php?id=<?= $doc['id'] ?>" class="btn btn-view" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="download_document.php?id=<?= $doc['id'] ?>" class="btn btn-green btn-sm" title="Download">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <?php if (!$doc['is_verified']): ?>
                                            <button onclick="verifyDocument(<?= $doc['id'] ?>)" class="btn btn-success btn-sm" title="Verify">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-8 text-gray-400">
                                <i class="fas fa-folder-open text-3xl block mb-2"></i>
                                No documents found. Click "Upload Document" to add one.
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
            Documents
            <span class="text-gray-300 mx-2">|</span>
            Logged in as: <strong><?= htmlspecialchars($doctor_name) ?></strong>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<style>
    .table-wrap { overflow-x: auto; }
    .data-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
    .data-table thead th { text-align: left; padding: 10px 14px; font-weight: 700; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; color: white; background: var(--primary); border-bottom: 3px solid var(--primary-dark); white-space: nowrap; }
    .data-table tbody tr:nth-child(even) { background: var(--primary-bg); }
    .data-table tbody tr:nth-child(odd) { background: var(--bg-card); }
    .data-table tbody tr:hover { background: var(--green-bg); }
    .data-table td { padding: 10px 14px; border-bottom: 1px solid var(--border-color); color: var(--text-primary); vertical-align: middle; }
    .badge { padding: 3px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; color: white; border: none; }
    .badge-success { background: var(--green); }
    .badge-danger { background: var(--red); }
    .badge-info { background: var(--primary); }
    .badge-warning { background: var(--orange); }
    .btn-view { background: var(--primary); color: white; padding: 4px 10px; font-size: 0.7rem; border-radius: 6px; border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
    .btn-view:hover { background: var(--primary-dark); transform: scale(1.05); }
    .btn-green { background: var(--green); color: white; }
    .btn-green:hover { background: var(--green-dark); }
    .btn-success { background: var(--green); color: white; }
    .btn-success:hover { background: var(--green-dark); }
    .action-buttons { display: flex; align-items: center; gap: 4px; flex-wrap: nowrap; justify-content: center; }
    .btn-sm { padding: 4px 10px; font-size: 0.7rem; border-radius: 6px; }
    [data-theme="dark"] .data-table tbody tr:nth-child(even) { background: #1E293B; }
    [data-theme="dark"] .data-table tbody tr:nth-child(odd) { background: #1E293B; }
    [data-theme="dark"] .data-table tbody tr:hover { background: #1A3A2A; }
</style>

<script>
    function verifyDocument(id) {
        if (confirm('Verify this document?')) {
            showToast('Success', 'Document verified!', 'success');
        }
    }
    
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
    console.log('%c👨‍⚕️ Documents - <?= htmlspecialchars($doctor_name) ?>', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
</script>

</body>
</html>