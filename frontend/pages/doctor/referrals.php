<?php
// ================================================================
// FILE: frontend/pages/doctor/referrals.php
// DOCTOR - REFERRALS (FIXED - Using visit_id)
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
// GET REFERRALS (Both sent and received) - FIXED
// ================================================================
$type = isset($_GET['type']) ? $_GET['type'] : 'sent';

if ($type === 'sent') {
    // Referrals sent by this doctor
    $sql = "SELECT r.*, 
                   p.full_name as patient_name, 
                   p.patient_id,
                   p.phone as patient_phone,
                   u_from.full_name as from_doctor,
                   u_to.full_name as to_doctor,
                   v.visit_number
            FROM referrals r
            LEFT JOIN visits v ON r.visit_id = v.id
            LEFT JOIN patients p ON v.patient_id = p.id
            LEFT JOIN users u_from ON r.from_doctor_id = u_from.id
            LEFT JOIN users u_to ON r.to_doctor_id = u_to.id
            WHERE r.from_doctor_id = ?
            ORDER BY r.created_at DESC";
    $params = [$doctor_id];
} else {
    // Referrals received by this doctor
    $sql = "SELECT r.*, 
                   p.full_name as patient_name, 
                   p.patient_id,
                   p.phone as patient_phone,
                   u_from.full_name as from_doctor,
                   u_to.full_name as to_doctor,
                   v.visit_number
            FROM referrals r
            LEFT JOIN visits v ON r.visit_id = v.id
            LEFT JOIN patients p ON v.patient_id = p.id
            LEFT JOIN users u_from ON r.from_doctor_id = u_from.id
            LEFT JOIN users u_to ON r.to_doctor_id = u_to.id
            WHERE r.to_doctor_id = ?
            ORDER BY r.created_at DESC";
    $params = [$doctor_id];
}

$stmt = $db->prepare($sql);
$stmt->execute($params);
$referrals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET STATISTICS
// ================================================================
$total_referrals = count($referrals);
$pending_refs = 0;
$accepted_refs = 0;
$completed_refs = 0;
$rejected_refs = 0;
foreach ($referrals as $r) {
    if ($r['status'] === 'pending') $pending_refs++;
    elseif ($r['status'] === 'accepted') $accepted_refs++;
    elseif ($r['status'] === 'completed') $completed_refs++;
    elseif ($r['status'] === 'rejected') $rejected_refs++;
}

// ================================================================
// GET DOCTORS FOR NEW REFERRAL
// ================================================================
$stmt = $db->prepare("SELECT id, full_name, specialty FROM users WHERE role = 'doctor' AND id != ? AND status = 'active'");
$stmt->execute([$doctor_id]);
$doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                <i class="fas fa-share-alt mr-2" style="color: #0B5ED7;"></i> Referrals
            </h1>
            <p class="page-subtitle">
                Manage patient referrals
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor_branch_name) ?>
                </span>
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-share-alt mr-1"></i> <?= $total_referrals ?> referrals
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="new_referral.php" class="btn btn-blue btn-sm">
                <i class="fas fa-plus"></i> New Referral
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div class="stat-card blue animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Total</p>
                    <p class="stat-number"><?= $total_referrals ?></p>
                </div>
                <div class="stat-icon"><i class="fas fa-share-alt"></i></div>
            </div>
        </div>
        <div class="stat-card orange animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Pending</p>
                    <p class="stat-number"><?= $pending_refs ?></p>
                </div>
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
            </div>
        </div>
        <div class="stat-card green animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Accepted</p>
                    <p class="stat-number"><?= $accepted_refs ?></p>
                </div>
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            </div>
        </div>
        <div class="stat-card purple animate-fade-in-up">
            <div class="flex items-center justify-between">
                <div>
                    <p class="stat-label">Completed</p>
                    <p class="stat-number"><?= $completed_refs ?></p>
                </div>
                <div class="stat-icon"><i class="fas fa-flag-checkered"></i></div>
            </div>
        </div>
    </div>

    <!-- Type Filter -->
    <div class="flex gap-2 mb-4">
        <a href="referrals.php?type=sent" class="btn <?= $type === 'sent' ? 'btn-blue' : 'btn-outline' ?> btn-sm">
            <i class="fas fa-paper-plane"></i> Sent
        </a>
        <a href="referrals.php?type=received" class="btn <?= $type === 'received' ? 'btn-blue' : 'btn-outline' ?> btn-sm">
            <i class="fas fa-inbox"></i> Received
        </a>
    </div>

    <!-- Referrals Table -->
    <div class="card">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="border-radius: 8px 0 0 0;">#</th>
                        <th>Patient</th>
                        <th>Visit #</th>
                        <th><?= $type === 'sent' ? 'To' : 'From' ?></th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th style="border-radius: 0 8px 0 0; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($referrals) > 0): ?>
                        <?php foreach ($referrals as $index => $ref): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td>
                                    <div>
                                        <div class="font-medium"><?= htmlspecialchars($ref['patient_name'] ?? 'N/A') ?></div>
                                        <div class="text-xs text-gray-400"><?= htmlspecialchars($ref['patient_id'] ?? 'N/A') ?></div>
                                    </div>
                                </td>
                                <td class="font-mono text-xs"><?= htmlspecialchars($ref['visit_number'] ?? 'N/A') ?></td>
                                <td>
                                    <?php if ($type === 'sent'): ?>
                                        <span class="text-green-600">To: <?= htmlspecialchars($ref['to_doctor'] ?? 'Unknown') ?></span>
                                    <?php else: ?>
                                        <span class="text-blue-600">From: <?= htmlspecialchars($ref['from_doctor'] ?? 'Unknown') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars(substr($ref['reason'] ?? '', 0, 40)) ?>...</td>
                                <td>
                                    <span class="badge <?= $ref['status'] === 'completed' ? 'badge-success' : ($ref['status'] === 'rejected' ? 'badge-danger' : ($ref['status'] === 'accepted' ? 'badge-warning' : 'badge-info')) ?>">
                                        <?= ucfirst($ref['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td><?= date('M d, Y', strtotime($ref['created_at'])) ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view_referral.php?id=<?= $ref['id'] ?>" class="btn btn-view" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if (($ref['status'] ?? 'pending') === 'pending' && $type === 'received'): ?>
                                            <button onclick="acceptReferral(<?= $ref['id'] ?>)" class="btn btn-green btn-sm" title="Accept">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button onclick="rejectReferral(<?= $ref['id'] ?>)" class="btn btn-danger btn-sm" title="Reject">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-8 text-gray-400">
                                <i class="fas fa-share-alt text-3xl block mb-2"></i>
                                No <?= $type ?> referrals found.
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
            Referrals
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
    .btn-danger { background: var(--red); color: white; }
    .btn-danger:hover { background: #DC2626; }
    .action-buttons { display: flex; align-items: center; gap: 4px; flex-wrap: nowrap; justify-content: center; }
    .btn-sm { padding: 4px 10px; font-size: 0.7rem; border-radius: 6px; }
    .text-green-600 { color: var(--green); }
    .text-blue-600 { color: var(--primary); }
    .font-mono { font-family: monospace; }
    [data-theme="dark"] .data-table tbody tr:nth-child(even) { background: #1E293B; }
    [data-theme="dark"] .data-table tbody tr:nth-child(odd) { background: #1E293B; }
    [data-theme="dark"] .data-table tbody tr:hover { background: #1A3A2A; }
</style>

<script>
    function acceptReferral(id) {
        if (confirm('Accept this referral?')) {
            showToast('Success', 'Referral accepted!', 'success');
        }
    }
    
    function rejectReferral(id) {
        if (confirm('Reject this referral?')) {
            showToast('Info', 'Referral rejected', 'info');
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
    console.log('%c👨‍⚕️ Referrals (FIXED) - <?= htmlspecialchars($doctor_name) ?>', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c✅ Using visit_id to join with patients', 'font-size:12px; color:#059669;');
</script>

</body>
</html>