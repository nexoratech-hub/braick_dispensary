<?php
// ================================================================
// FILE: frontend/pages/doctor/edit_prescription.php
// DOCTOR - EDIT PRESCRIPTION
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
        u.full_name as doctor_name,
        v.visit_number
    FROM prescriptions pr
    JOIN patients p ON pr.patient_id = p.id
    JOIN users u ON pr.doctor_id = u.id
    LEFT JOIN visits v ON pr.visit_id = v.id
    WHERE pr.id = ? AND pr.doctor_id = ? AND pr.status = 'pending'
");
$stmt->execute([$prescription_id, $doctor_id]);
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
    ORDER BY name
");
$stmt->execute([]);
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
    
    // Update prescription
    $stmt = $db->prepare("
        UPDATE prescriptions 
        SET diagnosis = ?, notes = ?, status = ?, updated_at = NOW()
        WHERE id = ? AND doctor_id = ?
    ");
    
    if ($stmt->execute([$diagnosis, $notes, $status, $prescription_id, $doctor_id])) {
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
                    $item_data['medication_name'],
                    $item_data['dosage'],
                    $item_data['frequency'],
                    $item_data['quantity'],
                    $item_data['duration'],
                    $item_data['instructions'],
                    $item_id,
                    $prescription_id
                ]);
            }
        }
        
        $message = 'Prescription updated successfully!';
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
                <i class="fas fa-edit mr-2" style="color: #0B5ED7;"></i> Edit Prescription
            </h1>
            <p class="page-subtitle">
                Update prescription details
                <span class="ml-2 inline-flex bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs border border-blue-200">
                    <i class="fas fa-hashtag mr-1"></i> <?= htmlspecialchars($prescription['prescription_number']) ?>
                </span>
                <span class="ml-2 inline-flex bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs border border-green-200">
                    <i class="fas fa-user mr-1"></i> <?= htmlspecialchars($prescription['patient_name']) ?>
                </span>
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
                    <option value="dispensed" <?= $prescription['status'] === 'dispensed' ? 'selected' : '' ?>>Dispensed</option>
                    <option value="cancelled" <?= $prescription['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
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
                                    <input type="text" name="items[<?= $item['id'] ?>][medication_name]" 
                                           class="form-control" value="<?= htmlspecialchars($item['medication_name'] ?? '') ?>">
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
                                           class="form-control" value="<?= $item['quantity'] ?? '' ?>">
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
                <button type="submit" class="btn btn-primary">
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
            <span class="text-gray-300 mx-2">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- STYLES -->
<!-- ================================================================ -->
<style>
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
    
    .btn-primary {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: white;
        box-shadow: 0 4px 14px rgba(11, 94, 215, 0.3);
        flex: 1;
    }
    
    .btn-primary:hover {
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
    
    .text-xs { font-size: 0.75rem; }
    .text-sm { font-size: 0.875rem; }
    .text-gray-400 { color: var(--text-muted); }
    .text-gray-500 { color: var(--text-secondary); }
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
    
    [data-theme="dark"] .bg-green-100 { background: #1A3A2A; }
    [data-theme="dark"] .text-green-700 { color: #34D399; }
    [data-theme="dark"] .border-green-200 { border-color: #1A3A2A; }
    [data-theme="dark"] .bg-red-100 { background: #3A1A1A; }
    [data-theme="dark"] .text-red-700 { color: #F87171; }
    [data-theme="dark"] .border-red-200 { border-color: #3A1A1A; }
    
    @media (max-width: 768px) {
        .edit-card { padding: 18px 16px; }
        .md\:grid-cols-3 { grid-template-columns: 1fr; }
        .grid-cols-2 { grid-template-columns: 1fr; }
        .form-actions { flex-direction: column; }
        .form-actions .btn { width: 100%; justify-content: center; }
        .btn-primary { flex: none; }
    }
</style>

</body>
</html>