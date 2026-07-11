<?php
// ================================================================
// FILE: frontend/pages/doctor/view_prescriptions.php
// DOCTOR - VIEW PRESCRIPTIONS
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
// GET PRESCRIPTIONS
// ================================================================
$stmt = $db->prepare("
    SELECT pr.*, p.full_name as patient_name, p.patient_id, 
           u.full_name as doctor_name, 
           COUNT(pi.id) as items_count
    FROM prescriptions pr
    JOIN patients p ON pr.patient_id = p.id
    LEFT JOIN users u ON pr.doctor_id = u.id
    LEFT JOIN prescription_items pi ON pr.id = pi.prescription_id
    WHERE pr.doctor_id = ?
    GROUP BY pr.id
    ORDER BY pr.created_at DESC
");
$stmt->execute([$doctor_id]);
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

    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-6">
        <div>
            <h1 class="page-title">
                <i class="fas fa-file-prescription mr-2" style="color: #0B5ED7;"></i> My Prescriptions
            </h1>
            <p class="page-subtitle">
                View all prescriptions you have created
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor_branch_name) ?>
                </span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-prescription mr-1"></i> <?= count($prescriptions) ?> prescriptions
                </span>
            </p>
        </div>
        <a href="prescribe.php" class="btn btn-green btn-sm">
            <i class="fas fa-plus"></i> New Prescription
        </a>
    </div>

    <!-- Prescriptions Table -->
    <div class="card">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="border-radius: 8px 0 0 0;">#</th>
                        <th>Prescription #</th>
                        <th>Patient</th>
                        <th>Items</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th style="border-radius: 0 8px 0 0; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($prescriptions) > 0): ?>
                        <?php foreach ($prescriptions as $index => $pres): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td class="font-mono text-xs"><?= htmlspecialchars($pres['prescription_number'] ?? 'N/A') ?></td>
                                <td>
                                    <div>
                                        <div class="font-medium"><?= htmlspecialchars($pres['patient_name']) ?></div>
                                        <div class="text-xs text-gray-400"><?= htmlspecialchars($pres['patient_id'] ?? 'N/A') ?></div>
                                    </div>
                                </td>
                                <td><?= $pres['items_count'] ?? 0 ?></td>
                                <td>
                                    <span class="badge <?= $pres['status'] === 'dispensed' ? 'badge-success' : ($pres['status'] === 'cancelled' ? 'badge-danger' : 'badge-info') ?>">
                                        <?= ucfirst($pres['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td><?= date('M d, Y', strtotime($pres['created_at'])) ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view_prescription.php?id=<?= $pres['id'] ?>" class="btn btn-view" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if (($pres['status'] ?? 'pending') === 'pending'): ?>
                                            <button onclick="editPrescription(<?= $pres['id'] ?>)" class="btn btn-edit" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-8 text-gray-400">
                                <i class="fas fa-prescription text-3xl block mb-2"></i>
                                No prescriptions found. Click "New Prescription" to create one.
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
            View Prescriptions
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
    .btn-view { background: var(--primary); color: white; padding: 4px 10px; font-size: 0.7rem; border-radius: 6px; border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
    .btn-view:hover { background: var(--primary-dark); transform: scale(1.05); }
    .btn-edit { background: var(--orange); color: white; padding: 4px 10px; font-size: 0.7rem; border-radius: 6px; border: none; cursor: pointer; }
    .btn-edit:hover { background: #B45309; transform: scale(1.05); }
    .action-buttons { display: flex; align-items: center; gap: 4px; flex-wrap: nowrap; justify-content: center; }
    .btn-sm { padding: 4px 10px; font-size: 0.7rem; border-radius: 6px; }
    [data-theme="dark"] .data-table tbody tr:nth-child(even) { background: #1E293B; }
    [data-theme="dark"] .data-table tbody tr:nth-child(odd) { background: #1E293B; }
    [data-theme="dark"] .data-table tbody tr:hover { background: #1A3A2A; }
</style>

<script>
    function editPrescription(id) {
        showToast('Info', 'Edit feature coming soon!', 'info');
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
    
    console.log('%c👨‍⚕️ View Prescriptions - <?= htmlspecialchars($doctor_name) ?>', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
</script>

</body>
</html>