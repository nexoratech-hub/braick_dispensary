<?php
// ================================================================
// FILE: frontend/pages/doctor/edit_prescription.php
// DOCTOR - EDIT PRESCRIPTION
// BRAICK DISPENSARY
// ================================================================

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION - CHECK IF USER IS LOGGED IN
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// CHECK IF USER IS DOCTOR OR ADMIN
// ================================================================
if ($_SESSION['role'] !== 'doctor' && $_SESSION['role'] !== 'admin') {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'pharmacy': header('Location: ../pharmacy/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// GET DOCTOR INFO FROM SESSION
// ================================================================
$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['full_name'] ?? 'Dr. Sarah Mwamba';
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;
$doctor_role = $_SESSION['role'];
$is_admin = ($_SESSION['role'] === 'admin');

// ================================================================
// GET PRESCRIPTION ID
// ================================================================
$prescription_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($prescription_id <= 0) {
    header('Location: view_prescriptions.php');
    exit;
}

// ================================================================
// INCLUDE DATABASE - CORRECT PATH
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die('Database connection error: ' . $e->getMessage());
}

// ================================================================
// GET PRESCRIPTION DETAILS
// ================================================================
if ($is_admin) {
    // Admin can edit any prescription
    $stmt = $db->prepare("
        SELECT 
            pr.*,
            p.full_name as patient_name,
            p.patient_id as patient_code,
            u.full_name as doctor_name,
            v.visit_number
        FROM prescriptions pr
        JOIN patients p ON pr.patient_id = p.id
        JOIN users u ON pr.doctor_id = u.id
        LEFT JOIN visits v ON pr.visit_id = v.id
        WHERE pr.id = ? AND pr.status = 'pending'
    ");
    $stmt->execute([$prescription_id]);
} else {
    // Doctor can only edit their own prescriptions
    $stmt = $db->prepare("
        SELECT 
            pr.*,
            p.full_name as patient_name,
            p.patient_id as patient_code,
            u.full_name as doctor_name,
            v.visit_number
        FROM prescriptions pr
        JOIN patients p ON pr.patient_id = p.id
        JOIN users u ON pr.doctor_id = u.id
        LEFT JOIN visits v ON pr.visit_id = v.id
        WHERE pr.id = ? AND pr.doctor_id = ? AND pr.status = 'pending'
    ");
    $stmt->execute([$prescription_id, $doctor_id]);
}
$prescription = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$prescription) {
    header('Location: view_prescriptions.php?error=not_found_or_not_editable');
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
// GET MEDICATIONS FOR DROPDOWN
// ================================================================
$stmt = $db->prepare("
    SELECT id, name, strength, unit, category 
    FROM medications 
    WHERE status = 'active' 
    AND (branch_id IS NULL OR branch_id = ?)
    ORDER BY name
");
$stmt->execute([$doctor_branch_id]);
$medications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// HANDLE FORM SUBMISSION
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $diagnosis = trim($_POST['diagnosis'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $status = $_POST['status'] ?? 'pending';
    
    // Check if prescription can be edited (not dispensed)
    if ($prescription['status'] === 'dispensed' && $status !== 'dispensed') {
        $message = 'Cannot edit a dispensed prescription!';
        $message_type = 'error';
    } else {
        if ($is_admin) {
            // Admin can update any prescription
            $stmt = $db->prepare("
                UPDATE prescriptions 
                SET diagnosis = ?, notes = ?, status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$diagnosis, $notes, $status, $prescription_id]);
        } else {
            // Doctor can only update their own prescriptions
            $stmt = $db->prepare("
                UPDATE prescriptions 
                SET diagnosis = ?, notes = ?, status = ?, updated_at = NOW()
                WHERE id = ? AND doctor_id = ?
            ");
            $stmt->execute([$diagnosis, $notes, $status, $prescription_id, $doctor_id]);
        }
        
        if ($stmt->rowCount() > 0 || true) { // Allow even if no changes
            // Update items if needed
            if (isset($_POST['items']) && is_array($_POST['items'])) {
                foreach ($_POST['items'] as $item_id => $item_data) {
                    $stmt = $db->prepare("
                        UPDATE prescription_items 
                        SET medication_name = ?, dosage = ?, frequency = ?, 
                            quantity = ?, duration = ?, instructions = ?
                        WHERE id = ? AND prescription_id = ?
                    ");
                    $stmt->execute([
                        $item_data['medication_name'] ?? '',
                        $item_data['dosage'] ?? '',
                        $item_data['frequency'] ?? '',
                        (int)($item_data['quantity'] ?? 0),
                        $item_data['duration'] ?? '',
                        $item_data['instructions'] ?? '',
                        $item_id,
                        $prescription_id
                    ]);
                }
            }
            
            // Log activity
            try {
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) 
                    VALUES (?, ?, 'prescription_updated', ?, NOW())
                ");
                $stmt->execute([
                    $doctor_id,
                    $doctor_branch_id,
                    "Prescription #" . $prescription['prescription_number'] . " updated" . 
                    ($is_admin ? " (Admin)" : "") . 
                    " | Patient: " . $prescription['patient_name']
                ]);
            } catch (Exception $e) {}
            
            $message = '✅ Prescription updated successfully!';
            $message_type = 'success';
            
            // Refresh data
            $stmt = $db->prepare("SELECT * FROM prescriptions WHERE id = ?");
            $stmt->execute([$prescription_id]);
            $prescription = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $db->prepare("SELECT * FROM prescription_items WHERE prescription_id = ?");
            $stmt->execute([$prescription_id]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo '<script>setTimeout(function(){ window.location.href = "view_prescription.php?id=' . $prescription_id . '&updated=1"; }, 1500);</script>';
        } else {
            $message = 'Failed to update prescription!';
            $message_type = 'error';
        }
    }
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
// PROFILE PICTURE URL
// ================================================================
$profile_pic = $_SESSION['profile_pic'] ?? '';
$profile_pic_url = !empty($profile_pic) 
    ? '/dispensary_system/frontend/assets/uploads/profiles/' . $profile_pic 
    : '/dispensary_system/frontend/assets/uploads/profiles/default_avatar.png';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';

// ================================================================
// INCLUDE HEADER & SIDEBAR - CORRECT PATHS
// ================================================================
include_once __DIR__ . '/../../components/doctor_header.php';
include_once __DIR__ . '/../../components/doctor_sidebar.php';
?>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-6">
        <div>
            <h1 class="page-title">
                <i class="fas fa-edit mr-2" style="color: #0B5ED7;"></i> Edit Prescription
                <?php if ($is_admin): ?>
                    <span class="page-badge" style="background:#DC2626;color:white;font-size:0.65rem;">👑 Admin Mode</span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                Update prescription details
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-hashtag mr-1"></i> <?= htmlspecialchars($prescription['prescription_number']) ?>
                </span>
                <span class="ml-2 inline-flex bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs border border-green-200">
                    <i class="fas fa-user mr-1"></i> <?= htmlspecialchars($prescription['patient_name']) ?>
                </span>
                <?php if ($is_admin): ?>
                    <span class="ml-2 inline-flex bg-red-100 text-red-700 px-3 py-1 rounded-full text-xs border border-red-200">
                        <i class="fas fa-user-shield mr-1"></i> Admin Editing
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div class="flex gap-2">
            <a href="view_prescription.php?id=<?= $prescription_id ?>" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="p-4 rounded-xl mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- EDIT FORM -->
    <!-- ================================================================ -->
    <div class="edit-card">
        <form method="POST" action="" id="editForm">
            
            <!-- Patient & Visit Info -->
            <div class="info-bar">
                <div class="flex flex-wrap gap-4">
                    <div>
                        <span class="text-xs text-gray-400">Patient</span>
                        <p class="font-semibold"><?= htmlspecialchars($prescription['patient_name']) ?></p>
                        <p class="text-sm text-gray-500"><?= htmlspecialchars($prescription['patient_code']) ?></p>
                    </div>
                    <div>
                        <span class="text-xs text-gray-400">Visit</span>
                        <p class="font-semibold"><?= htmlspecialchars($prescription['visit_number'] ?? 'N/A') ?></p>
                    </div>
                    <div>
                        <span class="text-xs text-gray-400">Doctor</span>
                        <p class="font-semibold"><?= htmlspecialchars($prescription['doctor_name']) ?></p>
                    </div>
                    <div>
                        <span class="text-xs text-gray-400">Date</span>
                        <p class="font-semibold"><?= date('M d, Y', strtotime($prescription['created_at'])) ?></p>
                    </div>
                    <?php if ($is_admin): ?>
                    <div>
                        <span class="text-xs text-gray-400">Status</span>
                        <p class="font-semibold">
                            <span class="status-badge <?= $prescription['status'] === 'pending' ? 'badge-warning' : ($prescription['status'] === 'dispensed' ? 'badge-success' : 'badge-danger') ?>">
                                <?= ucfirst($prescription['status'] ?? 'Pending') ?>
                            </span>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Diagnosis -->
            <div class="mt-4">
                <label class="form-label">
                    <i class="fas fa-stethoscope text-purple-600 mr-1"></i> Diagnosis
                </label>
                <textarea name="diagnosis" class="form-control" rows="2"><?= htmlspecialchars($prescription['diagnosis'] ?? '') ?></textarea>
            </div>

            <!-- Notes -->
            <div class="mt-4">
                <label class="form-label">
                    <i class="fas fa-info-circle text-blue-600 mr-1"></i> Notes / Instructions
                </label>
                <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($prescription['notes'] ?? '') ?></textarea>
            </div>

            <!-- Status -->
            <div class="mt-4">
                <label class="form-label">
                    <i class="fas fa-circle text-blue-600 mr-1"></i> Status
                </label>
                <select name="status" class="form-control">
                    <option value="pending" <?= $prescription['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <?php if ($is_admin || $prescription['status'] !== 'dispensed'): ?>
                        <option value="dispensed" <?= $prescription['status'] === 'dispensed' ? 'selected' : '' ?>>Dispensed</option>
                    <?php endif; ?>
                    <option value="cancelled" <?= $prescription['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
                <?php if ($prescription['status'] === 'dispensed' && !$is_admin): ?>
                    <p class="text-xs text-red-500 mt-1"><i class="fas fa-info-circle"></i> Cannot change status - already dispensed</p>
                <?php endif; ?>
            </div>

            <!-- Medication Items -->
            <div class="mt-4">
                <h4 class="form-label">
                    <i class="fas fa-pills text-blue-600 mr-1"></i> Medication Items
                </h4>
                
                <?php if (count($items) > 0): ?>
                    <?php foreach ($items as $index => $item): ?>
                        <div class="item-row">
                            <input type="hidden" name="items[<?= $item['id'] ?>][id]" value="<?= $item['id'] ?>">
                            
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                                <div>
                                    <label class="text-xs text-gray-400">Medication</label>
                                    <?php if ($prescription['status'] === 'dispensed'): ?>
                                        <input type="text" name="items[<?= $item['id'] ?>][medication_name]" 
                                               class="form-control" value="<?= htmlspecialchars($item['medication_name'] ?? '') ?>" readonly style="background:var(--gray-100);">
                                    <?php else: ?>
                                        <input type="text" name="items[<?= $item['id'] ?>][medication_name]" 
                                               class="form-control" value="<?= htmlspecialchars($item['medication_name'] ?? '') ?>">
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-400">Dosage</label>
                                    <input type="text" name="items[<?= $item['id'] ?>][dosage]" 
                                           class="form-control" value="<?= htmlspecialchars($item['dosage'] ?? '') ?>">
                                </div>
                                <div>
                                    <label class="text-xs text-gray-400">Frequency</label>
                                    <input type="text" name="items[<?= $item['id'] ?>][frequency]" 
                                           class="form-control" value="<?= htmlspecialchars($item['frequency'] ?? '') ?>">
                                </div>
                                <div>
                                    <label class="text-xs text-gray-400">Quantity</label>
                                    <input type="number" name="items[<?= $item['id'] ?>][quantity]" 
                                           class="form-control" value="<?= $item['quantity'] ?? '' ?>" min="0">
                                </div>
                                <div>
                                    <label class="text-xs text-gray-400">Duration</label>
                                    <input type="text" name="items[<?= $item['id'] ?>][duration]" 
                                           class="form-control" value="<?= htmlspecialchars($item['duration'] ?? '') ?>">
                                </div>
                                <div>
                                    <label class="text-xs text-gray-400">Instructions</label>
                                    <input type="text" name="items[<?= $item['id'] ?>][instructions]" 
                                           class="form-control" value="<?= htmlspecialchars($item['instructions'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-gray-400 text-sm">No medication items found</p>
                <?php endif; ?>
            </div>

            <!-- Form Actions -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary" <?= $prescription['status'] === 'dispensed' && !$is_admin ? 'disabled' : '' ?>>
                    <i class="fas fa-save"></i> Update Prescription
                </button>
                <a href="view_prescription.php?id=<?= $prescription_id ?>" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>

        </form>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Edit Prescription
            <?php if ($is_admin): ?>
                <span class="text-gray-300 mx-2">|</span>
                <span style="color:#DC2626;">👑 Admin Mode</span>
            <?php endif; ?>
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- STYLES -->
<!-- ================================================================ -->
<style>
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
    
    .edit-card {
        background: var(--bg-card);
        border-radius: 20px;
        padding: 28px 32px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        max-width: 56rem;
        margin: 0 auto;
    }
    
    .edit-card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.08);
    }
    
    .info-bar {
        background: var(--primary-bg);
        border-radius: 12px;
        padding: 16px 20px;
        border: 1px solid rgba(11, 94, 215, 0.15);
    }
    
    [data-theme="dark"] .info-bar {
        background: #1E3A5F;
        border-color: #1E3A5F;
    }
    
    .form-label {
        display: block;
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 5px;
    }
    
    .form-control {
        width: 100%;
        padding: 10px 14px;
        border: 2px solid var(--border-color);
        border-radius: 10px;
        font-size: 0.9rem;
        background: var(--bg-card);
        color: var(--text-primary);
        outline: none;
        transition: all 0.3s ease;
        font-family: 'Inter', 'Segoe UI', sans-serif;
    }
    
    .form-control:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
    }
    
    .form-control:disabled,
    .form-control[readonly] {
        opacity: 0.7;
        cursor: not-allowed;
        background: var(--gray-100);
    }
    
    .item-row {
        background: var(--bg-body);
        border-radius: 12px;
        padding: 16px;
        margin-bottom: 12px;
        border: 1px solid var(--border-color);
    }
    
    [data-theme="dark"] .item-row {
        background: #0F172A;
    }
    
    .form-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        padding-top: 20px;
        margin-top: 20px;
        border-top: 2px solid var(--border-color);
    }
    
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 24px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        min-height: 44px;
    }
    
    .btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none !important;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: white;
        box-shadow: 0 4px 14px rgba(11, 94, 215, 0.3);
        flex: 1;
    }
    
    .btn-primary:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(11, 94, 215, 0.4);
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
        padding: 5px 14px;
        font-size: 0.75rem;
        min-height: 34px;
    }
    
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
    
    .badge-warning {
        background: #FEF3C7;
        color: #D97706;
        border-color: #FDE68A;
    }
    
    .badge-success {
        background: #D1FAE5;
        color: #059669;
        border-color: #A7F3D0;
    }
    
    .badge-danger {
        background: #FEE2E2;
        color: #DC2626;
        border-color: #FCA5A5;
    }
    
    [data-theme="dark"] .badge-warning {
        background: #3D2E0A;
        color: #FBBF24;
        border-color: #78350F;
    }
    
    [data-theme="dark"] .badge-success {
        background: #1A3A2A;
        color: #34D399;
        border-color: #065F46;
    }
    
    [data-theme="dark"] .badge-danger {
        background: #3A1A1A;
        color: #F87171;
        border-color: #7F1D1D;
    }
    
    .text-xs { font-size: 0.75rem; }
    .text-sm { font-size: 0.875rem; }
    .text-gray-400 { color: var(--text-muted); }
    .text-gray-500 { color: var(--text-secondary); }
    .text-red-500 { color: #EF4444; }
    .text-purple-600 { color: #7C3AED; }
    .text-blue-600 { color: var(--primary); }
    .font-semibold { font-weight: 600; }
    
    .grid { display: grid; }
    .grid-cols-2 { grid-template-columns: 1fr 1fr; }
    .md\:grid-cols-3 { grid-template-columns: 1fr 1fr 1fr; }
    .gap-3 { gap: 0.75rem; }
    .mt-4 { margin-top: 1rem; }
    .mb-6 { margin-bottom: 1.5rem; }
    .ml-2 { margin-left: 0.5rem; }
    .mr-1 { margin-right: 0.25rem; }
    .flex { display: flex; }
    .flex-wrap { flex-wrap: wrap; }
    .gap-4 { gap: 1rem; }
    .gap-2 { gap: 0.5rem; }
    .gap-3 { gap: 0.75rem; }
    
    .footer {
        padding: 14px 0;
        border-top: 2px solid var(--border-color);
        margin-top: 20px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--text-secondary);
    }
    .footer .footer-brand { color: var(--primary); font-weight: 600; }
    
    .bg-green-100 { background: #D1FAE5; }
    .text-green-700 { color: #059669; }
    .border-green-200 { border-color: #A7F3D0; }
    .bg-red-100 { background: #FEE2E2; }
    .text-red-700 { color: #DC2626; }
    .border-red-200 { border-color: #FCA5A5; }
    .bg-blue-100 { background: #E8F0FE; }
    .text-blue-700 { color: #0B5ED7; }
    .border-blue-200 { border-color: #BFDBFE; }
    .bg-green-100 { background: #D1FAE5; }
    .text-green-700 { color: #059669; }
    .border-green-200 { border-color: #A7F3D0; }
    .bg-red-100 { background: #FEE2E2; }
    .text-red-700 { color: #DC2626; }
    .border-red-200 { border-color: #FCA5A5; }
    
    [data-theme="dark"] .bg-green-100 { background: #1A3A2A; }
    [data-theme="dark"] .text-green-700 { color: #34D399; }
    [data-theme="dark"] .border-green-200 { border-color: #1A3A2A; }
    [data-theme="dark"] .bg-red-100 { background: #3A1A1A; }
    [data-theme="dark"] .text-red-700 { color: #F87171; }
    [data-theme="dark"] .border-red-200 { border-color: #3A1A1A; }
    [data-theme="dark"] .bg-blue-100 { background: #1E3A5F; }
    [data-theme="dark"] .text-blue-700 { color: #6EA8FE; }
    [data-theme="dark"] .border-blue-200 { border-color: #1E3A5F; }
    
    @media (max-width: 1024px) {
        .main-content { margin-left: 0; padding: 16px; }
    }
    
    @media (max-width: 768px) {
        .edit-card { padding: 18px 16px; }
        .md\:grid-cols-3 { grid-template-columns: 1fr; }
        .grid-cols-2 { grid-template-columns: 1fr; }
        .form-actions { flex-direction: column; }
        .form-actions .btn { width: 100%; justify-content: center; }
        .btn-primary { flex: none; }
        .page-title { font-size: 1.2rem; }
        .page-header { flex-direction: column; }
        .page-header .btn-sm { width: 100%; justify-content: center; }
        .info-bar .flex { flex-direction: column; gap: 8px; }
    }
    
    @media (max-width: 480px) {
        .main-content { padding: 10px; }
        .edit-card { padding: 12px; }
        .item-row { padding: 10px; }
        .form-control { font-size: 0.8rem; padding: 8px 10px; }
        .page-subtitle { flex-direction: column; align-items: flex-start; gap: 4px; }
    }
</style>

</body>
</html>