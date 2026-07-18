<?php
// ================================================================
// FILE: frontend/pages/doctor/prescribe.php
// DOCTOR - PRESCRIBE MEDICATIONS
// WITH PATIENT DROPDOWN & VISIT AUTO-LOAD
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// IF NO SESSION, USE DR. JOHN MUSHI (ID: 5) AS DEFAULT
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    $_SESSION['user_id'] = 5;
    $_SESSION['doctor_id'] = 5;
    $_SESSION['full_name'] = 'Dr. John Mushi';
    $_SESSION['username'] = 'dr.john';
    $_SESSION['email'] = 'john@braick.com';
    $_SESSION['phone'] = '+255 700 000 011';
    $_SESSION['role'] = 'doctor';
    $_SESSION['branch_id'] = 1;
    $_SESSION['specialty'] = 'General Medicine';
    $_SESSION['profile_pic'] = '';
    $_SESSION['is_online'] = 1;
}

$doctor_id = $_SESSION['user_id'] ?? 5;
$doctor_name = $_SESSION['full_name'] ?? 'Dr. John Mushi';
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;
$doctor_specialty = $_SESSION['specialty'] ?? 'General Medicine';

// ================================================================
// GET PARAMETERS
// ================================================================
$selected_patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once 'C:/xampp/htdocs/dispensary_system/backend/config/database.php';
$db = Database::getInstance()->getConnection();

// ================================================================
// GET DOCTOR'S PATIENTS
// ================================================================
$patients = [];
try {
    $stmt = $db->prepare("
        SELECT DISTINCT p.* 
        FROM patients p
        JOIN visits v ON p.id = v.patient_id
        WHERE v.doctor_id = ?
        ORDER BY p.full_name
    ");
    $stmt->execute([$doctor_id]);
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $patients = [];
}

// ================================================================
// GET SELECTED PATIENT DATA
// ================================================================
$selected_patient = null;
$visits = [];

if ($selected_patient_id > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM patients WHERE id = ?");
        $stmt->execute([$selected_patient_id]);
        $selected_patient = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($selected_patient) {
            // Get visits for this patient
            $stmt = $db->prepare("
                SELECT * FROM visits 
                WHERE patient_id = ? AND doctor_id = ?
                ORDER BY created_at DESC
            ");
            $stmt->execute([$selected_patient_id, $doctor_id]);
            $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Patient/Visit fetch error: " . $e->getMessage());
    }
}

// ================================================================
// GET MEDICATIONS FROM INVENTORY
// ================================================================
$medications = [];
try {
    $stmt = $db->prepare("
        SELECT id, medication_name, quantity, selling_price, unit 
        FROM medications_inventory 
        WHERE status = 'active' AND quantity > 0 AND branch_id = ?
        ORDER BY medication_name
    ");
    $stmt->execute([$doctor_branch_id]);
    $medications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $medications = [];
}

// ================================================================
// PROCESS PRESCRIPTION
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'prescribe') {
    $patient_id = (int)($_POST['patient_id'] ?? 0);
    $visit_id = (int)($_POST['visit_id'] ?? 0);
    $diagnosis = trim($_POST['diagnosis'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $medications_json = $_POST['medications_json'] ?? '[]';
    $medications_data = json_decode($medications_json, true);
    
    $errors = [];
    if ($patient_id <= 0) $errors[] = "Please select a patient";
    if ($visit_id <= 0) $errors[] = "Please select a visit (or create one first)";
    if (empty($diagnosis)) $errors[] = "Please enter diagnosis";
    if (empty($medications_data)) $errors[] = "Please add at least one medication";
    
    if (empty($errors)) {
        try {
            // ================================================================
            // GET OR CREATE BILL
            // ================================================================
            $bill_id = null;
            $stmt = $db->prepare("SELECT id FROM patient_bills WHERE visit_id = ? AND status IN ('pending', 'partial')");
            $stmt->execute([$visit_id]);
            $bill = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($bill) {
                $bill_id = $bill['id'];
            } else {
                $bill_number = 'BILL-' . date('Ymd') . '-' . str_pad($patient_id, 6, '0', STR_PAD_LEFT);
                $stmt = $db->prepare("
                    INSERT INTO patient_bills (
                        bill_number, patient_id, visit_id, subtotal, total_amount, balance, 
                        status, created_by, branch_id
                    ) VALUES (?, ?, ?, 0, 0, 0, 'pending', ?, ?)
                ");
                $stmt->execute([$bill_number, $patient_id, $visit_id, $doctor_id, $doctor_branch_id]);
                $bill_id = $db->lastInsertId();
            }
            
            // ================================================================
            // CREATE PRESCRIPTION
            // ================================================================
            $prescription_number = 'PRES-' . date('Ymd') . '-' . str_pad($patient_id, 4, '0', STR_PAD_LEFT) . '-' . rand(100, 999);
            
            $stmt = $db->prepare("
                INSERT INTO prescriptions (
                    prescription_number, visit_id, patient_id, doctor_id, 
                    diagnosis, notes, status, branch_id, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
            ");
            $stmt->execute([
                $prescription_number,
                $visit_id,
                $patient_id,
                $doctor_id,
                $diagnosis,
                $notes,
                $doctor_branch_id
            ]);
            $prescription_id = $db->lastInsertId();
            
            // ================================================================
            // ADD PRESCRIPTION ITEMS & UPDATE STOCK
            // ================================================================
            $total_med_fees = 0;
            
            foreach ($medications_data as $med) {
                $med_id = (int)$med['med_id'];
                $quantity = (int)$med['quantity'];
                $dosage = $med['dosage'] ?? '';
                $frequency = $med['frequency'] ?? '';
                $duration = $med['duration'] ?? '';
                $route = $med['route'] ?? '';
                $instructions = $med['instructions'] ?? '';
                
                // Get medication details
                $stmt = $db->prepare("
                    SELECT medication_name, selling_price, unit 
                    FROM medications_inventory 
                    WHERE id = ? AND status = 'active'
                ");
                $stmt->execute([$med_id]);
                $medication = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($medication) {
                    $unit_price = $medication['selling_price'] ?? 0;
                    $total_price = $unit_price * $quantity;
                    $total_med_fees += $total_price;
                    
                    // Full instructions with route
                    $full_instructions = $instructions;
                    if (!empty($route)) {
                        $full_instructions = $instructions . ' (Route: ' . $route . ')';
                    }
                    
                    // Insert prescription item
                    $stmt = $db->prepare("
                        INSERT INTO prescription_items (
                            prescription_id, medication_name, dosage, frequency, quantity, duration, instructions, 
                            unit_price, total_price
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $prescription_id,
                        $medication['medication_name'],
                        $dosage,
                        $frequency,
                        $quantity,
                        $duration,
                        $full_instructions,
                        $unit_price,
                        $total_price
                    ]);
                    
                    // Update stock
                    $stmt = $db->prepare("UPDATE medications_inventory SET quantity = quantity - ? WHERE id = ?");
                    $stmt->execute([$quantity, $med_id]);
                }
            }
            
            // ================================================================
            // UPDATE BILL WITH MEDICATION FEES
            // ================================================================
            if ($bill_id > 0 && $total_med_fees > 0) {
                // Update patient_bills
                $stmt = $db->prepare("
                    UPDATE patient_bills 
                    SET medication_fees = medication_fees + ?,
                        subtotal = subtotal + ?,
                        total_amount = total_amount + ?,
                        balance = balance + ?
                    WHERE id = ?
                ");
                $stmt->execute([$total_med_fees, $total_med_fees, $total_med_fees, $total_med_fees, $bill_id]);
                
                // Add to bill_items
                foreach ($medications_data as $med) {
                    $med_id = (int)$med['med_id'];
                    $quantity = (int)$med['quantity'];
                    
                    $stmt = $db->prepare("SELECT medication_name, selling_price FROM medications_inventory WHERE id = ?");
                    $stmt->execute([$med_id]);
                    $med_info = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($med_info) {
                        $total = $med_info['selling_price'] * $quantity;
                        $stmt = $db->prepare("
                            INSERT INTO bill_items (bill_id, item_type, item_name, quantity, unit_price, total_price)
                            VALUES (?, 'medication', ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $bill_id,
                            $med_info['medication_name'],
                            $quantity,
                            $med_info['selling_price'],
                            $total
                        ]);
                    }
                }
            }
            
            $message = "✅ Prescription created successfully! #: " . $prescription_number;
            $message_type = 'success';
            
            echo '<script>setTimeout(function(){ window.location.href = "view_patient.php?id=' . $patient_id . '"; }, 2000);</script>';
            
        } catch (Exception $e) {
            $message = "❌ Error: " . $e->getMessage();
            $message_type = 'error';
            error_log("Prescription error: " . $e->getMessage());
        }
    } else {
        $message = "❌ " . implode('<br>', $errors);
        $message_type = 'error';
    }
}

// ================================================================
// GET BRANCH NAME
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
    <div class="page-header">
        <div class="page-header-left">
            <h1 class="page-title">
                <i class="fas fa-prescription"></i> Prescribe Medication
                <span class="page-badge">Doctor</span>
            </h1>
            <p class="page-subtitle">
                Create prescription with multiple medications
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor_branch_name) ?>
                </span>
                <?php if ($selected_patient): ?>
                    <span class="patient-badge ml-2">
                        <i class="fas fa-user"></i> <?= htmlspecialchars($selected_patient['full_name']) ?>
                        <span class="text-xs opacity-70">(<?= htmlspecialchars($selected_patient['patient_id'] ?? 'N/A') ?>)</span>
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div class="page-header-right">
            <a href="my_patients.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> My Patients
            </a>
            <a href="consultation.php?patient_id=<?= $selected_patient_id ?>" class="btn btn-primary">
                <i class="fas fa-stethoscope"></i> Consultation
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle') ?>"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- PRESCRIPTION FORM -->
    <!-- ================================================================ -->
    <div class="prescription-card">
        
        <!-- Doctor Info -->
        <div class="doctor-info-bar">
            <div class="flex items-center gap-4">
                <div class="doctor-avatar-sm" style="background: #0B5ED7;">
                    <?= strtoupper(substr($doctor_name, 0, 1)) ?>
                </div>
                <div>
                    <p class="font-semibold text-gray-800"><?= htmlspecialchars($doctor_name) ?></p>
                    <p class="text-sm text-gray-500"><?= htmlspecialchars($doctor_specialty) ?> • <?= htmlspecialchars($doctor_branch_name) ?></p>
                </div>
            </div>
            <div class="text-sm text-gray-400">
                <i class="far fa-calendar-alt mr-1"></i> <?= date('F d, Y') ?>
            </div>
        </div>

        <form method="POST" id="prescriptionForm" onsubmit="return validateForm()">
            <input type="hidden" name="action" value="prescribe">
            <input type="hidden" name="medications_json" id="medicationsJson" value="[]">
            
            <!-- PATIENT & VISIT -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Patient <span class="required">*</span></label>
                    <select name="patient_id" id="patient_id" class="form-control" required>
                        <option value="">-- Select Patient --</option>
                        <?php foreach ($patients as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= $selected_patient_id == $p['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['full_name']) ?> 
                                (<?= htmlspecialchars($p['patient_id'] ?? 'N/A') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Visit <span class="required">*</span></label>
                    <select name="visit_id" id="visit_id" class="form-control" required>
                        <option value="">-- Select Visit --</option>
                        <?php if (count($visits) > 0): ?>
                            <?php foreach ($visits as $v): ?>
                                <option value="<?= $v['id'] ?>">
                                    <?= htmlspecialchars($v['visit_number'] ?? 'N/A') ?> 
                                    - <?= date('M d, Y', strtotime($v['created_at'])) ?>
                                    (<?= ucfirst($v['status'] ?? 'Pending') ?>)
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="" disabled>No visits found for this patient</option>
                        <?php endif; ?>
                    </select>
                    <?php if (count($visits) == 0 && $selected_patient_id > 0): ?>
                        <small class="text-xs text-yellow-600">
                            <i class="fas fa-exclamation-triangle"></i> 
                            No visits found. Please create a visit first from 
                            <a href="consultation.php?patient_id=<?= $selected_patient_id ?>" class="text-primary">Consultation</a>
                        </small>
                    <?php endif; ?>
                </div>
            </div>

            <!-- DIAGNOSIS & NOTES -->
            <div class="mt-4">
                <label class="form-label">Diagnosis <span class="required">*</span></label>
                <textarea name="diagnosis" id="diagnosis" class="form-control" rows="2" placeholder="Enter diagnosis..."></textarea>
            </div>
            <div class="mt-3">
                <label class="form-label">Notes</label>
                <textarea name="notes" id="notes" class="form-control" rows="2" placeholder="Additional notes..."></textarea>
            </div>

            <!-- ADD MEDICATIONS -->
            <div class="mt-4">
                <label class="form-label" style="font-size: 1rem; font-weight: 700;">
                    <i class="fas fa-pills text-blue-600 mr-2"></i> Add Medications
                    <span class="required">*</span>
                </label>
                <p class="text-xs text-gray-400 mb-3">Select medication, fill details, then click <strong>"Add Medication"</strong> button</p>
                
                <div class="med-grid">
                    <div>
                        <label class="form-label">Medication</label>
                        <select id="medSelect" class="form-control">
                            <option value="">Select...</option>
                            <?php foreach ($medications as $med): ?>
                                <option value="<?= $med['id'] ?>" data-stock="<?= $med['quantity'] ?>">
                                    <?= htmlspecialchars($med['medication_name']) ?>
                                    (Stock: <?= $med['quantity'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="form-label">Qty</label>
                        <input type="number" id="medQuantity" class="form-control" value="1" min="1">
                    </div>
                    
                    <div>
                        <label class="form-label">Dosage</label>
                        <input type="text" id="medDosage" class="form-control" placeholder="e.g. 500mg">
                    </div>
                    
                    <div>
                        <label class="form-label">Frequency</label>
                        <select id="medFrequency" class="form-control">
                            <option value="">Select</option>
                            <option value="Once Daily">Once Daily</option>
                            <option value="Twice Daily">Twice Daily</option>
                            <option value="Three Times Daily">Three Times Daily</option>
                            <option value="Four Times Daily">Four Times Daily</option>
                            <option value="Every 4 Hours">Every 4 Hours</option>
                            <option value="Every 6 Hours">Every 6 Hours</option>
                            <option value="Every 8 Hours">Every 8 Hours</option>
                            <option value="Every 12 Hours">Every 12 Hours</option>
                            <option value="As Needed (PRN)">As Needed (PRN)</option>
                            <option value="Before Meals">Before Meals</option>
                            <option value="After Meals">After Meals</option>
                            <option value="At Bedtime">At Bedtime</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="form-label">Duration (Days)</label>
                        <input type="number" id="medDuration" class="form-control" value="7" min="1" max="90">
                    </div>
                    
                    <div>
                        <label class="form-label">Route</label>
                        <select id="medRoute" class="form-control">
                            <option value="">Select</option>
                            <option value="Oral">Oral</option>
                            <option value="Sublingual">Sublingual</option>
                            <option value="Intravenous (IV)">Intravenous (IV)</option>
                            <option value="Intramuscular (IM)">Intramuscular (IM)</option>
                            <option value="Subcutaneous (SC)">Subcutaneous (SC)</option>
                            <option value="Topical">Topical</option>
                            <option value="Inhalation">Inhalation</option>
                            <option value="Rectal">Rectal</option>
                            <option value="Vaginal">Vaginal</option>
                            <option value="Ophthalmic">Ophthalmic</option>
                            <option value="Otic">Otic</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="form-label">&nbsp;</label>
                        <button type="button" id="addMedBtn" class="btn-add-med">
                            <i class="fas fa-plus-circle"></i> Add Medication
                        </button>
                    </div>
                </div>
                
                <div class="mt-2">
                    <label class="form-label" style="font-size: 0.75rem;">Instructions (for this medication)</label>
                    <input type="text" id="medInstructions" class="form-control" placeholder="e.g. Take after meals, with plenty of water">
                </div>
            </div>

            <!-- SELECTED MEDICATIONS LIST -->
            <div class="mt-4">
                <div class="flex justify-between items-center mb-2">
                    <h4 class="font-semibold text-gray-700">
                        <i class="fas fa-list-ul text-blue-600 mr-2"></i> Selected Medications
                    </h4>
                    <span class="text-sm text-gray-400" id="medCount">0 items</span>
                </div>
                <div id="medicationsList">
                    <div class="empty-med-msg">
                        <i class="fas fa-prescription"></i>
                        <p>No medications added yet</p>
                        <p class="text-xs mt-1">Select a medication above and click "Add Medication"</p>
                    </div>
                </div>
            </div>

            <!-- FORM ACTIONS -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary" id="saveBtn">
                    <i class="fas fa-save"></i> Save Prescription
                </button>
                <button type="reset" class="btn btn-outline">
                    <i class="fas fa-undo"></i> Reset
                </button>
                <a href="my_patients.php" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>

        </form>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="separator">|</span>
            Prescribe
            <span class="separator">|</span>
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
    
    .page-header-left { flex: 1; }
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
    
    .patient-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: var(--primary-bg);
        color: var(--primary);
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 500;
    }
    [data-theme="dark"] .patient-badge {
        background: #1E3A5F;
        color: #6EA8FE;
    }
    
    .page-header-right {
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
    }
    
    .alert {
        padding: 12px 18px;
        border-radius: 12px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 0.9rem;
        border: 1px solid transparent;
    }
    .alert-success { background: #D1FAE5; color: #059669; border-color: #059669; }
    .alert-error { background: #FEE2E2; color: #DC2626; border-color: #DC2626; }
    .alert-warning { background: #FEF3C7; color: #D97706; border-color: #D97706; }
    .alert-info { background: #E8F0FE; color: #0B5ED7; border-color: #0B5ED7; }
    
    [data-theme="dark"] .alert-success { background: #1A3A2A; color: #34D399; border-color: #34D399; }
    [data-theme="dark"] .alert-error { background: #3A1A1A; color: #F87171; border-color: #F87171; }
    [data-theme="dark"] .alert-warning { background: #3D2E0A; color: #FBBF24; border-color: #FBBF24; }
    [data-theme="dark"] .alert-info { background: #1E3A5F; color: #6EA8FE; border-color: #6EA8FE; }
    
    .prescription-card {
        background: var(--bg-card);
        border-radius: 20px;
        padding: 28px 32px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        max-width: 64rem;
        margin: 0 auto;
    }
    .prescription-card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.08);
    }
    
    .doctor-info-bar {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        padding: 12px 18px;
        background: var(--primary-bg);
        border-radius: 12px;
        margin-bottom: 24px;
        border: 1px solid rgba(11, 94, 215, 0.15);
    }
    [data-theme="dark"] .doctor-info-bar {
        background: #1E3A5F;
        border-color: #1E3A5F;
    }
    
    .doctor-avatar-sm {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        font-weight: 700;
        color: white;
        flex-shrink: 0;
    }
    
    .form-label {
        display: block;
        font-size: 0.82rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 4px;
    }
    .form-label .required {
        color: #EF4444;
        margin-left: 2px;
    }
    
    .form-control {
        width: 100%;
        padding: 9px 14px;
        border: 2px solid var(--border-color);
        border-radius: 10px;
        font-size: 0.85rem;
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
    .form-control::placeholder {
        color: var(--text-secondary);
        opacity: 0.6;
    }
    textarea.form-control {
        resize: vertical;
        min-height: 60px;
    }
    select.form-control {
        appearance: auto;
        cursor: pointer;
    }
    
    .grid { display: grid; }
    .grid-cols-1 { grid-template-columns: 1fr; }
    .gap-4 { gap: 1rem; }
    .mt-2 { margin-top: 0.5rem; }
    .mt-3 { margin-top: 0.75rem; }
    .mt-4 { margin-top: 1rem; }
    .mb-2 { margin-bottom: 0.5rem; }
    .ml-2 { margin-left: 0.5rem; }
    .mr-1 { margin-right: 0.25rem; }
    .md\:grid-cols-2 { grid-template-columns: 1fr 1fr; }
    
    .med-grid {
        display: grid;
        grid-template-columns: 1.8fr 0.8fr 1.2fr 1.2fr 0.8fr 1fr auto;
        gap: 10px;
        align-items: end;
    }
    .med-grid .form-label {
        font-size: 0.65rem;
        margin-bottom: 2px;
        font-weight: 500;
    }
    .med-grid .form-control {
        font-size: 0.78rem;
        padding: 7px 10px;
    }
    
    .btn-add-med {
        background: linear-gradient(135deg, #059669, #047857);
        color: white;
        border: none;
        border-radius: 10px;
        padding: 9px 20px;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        transition: all 0.3s ease;
        white-space: nowrap;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        min-height: 42px;
        width: 100%;
        justify-content: center;
    }
    .btn-add-med:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 14px rgba(5, 150, 105, 0.4);
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
        background: var(--primary);
        color: white;
        box-shadow: 0 4px 14px rgba(11, 94, 215, 0.3);
        flex: 1;
    }
    .btn-primary:hover {
        background: var(--primary-dark);
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
    
    .form-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        padding-top: 20px;
        margin-top: 20px;
        border-top: 2px solid var(--border-color);
    }
    
    .medication-item {
        background: var(--bg-card);
        border-radius: 10px;
        padding: 12px 16px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }
    .medication-item:hover {
        border-color: var(--primary);
    }
    .medication-item .med-info {
        flex: 1;
    }
    .medication-item .med-name {
        font-weight: 600;
        font-size: 0.9rem;
        color: var(--text-primary);
    }
    .medication-item .med-details {
        font-size: 0.72rem;
        color: var(--text-secondary);
        margin-top: 2px;
    }
    .medication-item .med-details span {
        background: var(--bg-body);
        padding: 1px 8px;
        border-radius: 12px;
        margin-right: 4px;
    }
    
    .btn-remove {
        background: #EF4444;
        color: white;
        border: none;
        border-radius: 6px;
        padding: 5px 12px;
        font-size: 0.65rem;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .btn-remove:hover {
        background: #DC2626;
        transform: scale(1.05);
    }
    
    .empty-med-msg {
        text-align: center;
        padding: 20px;
        color: var(--text-secondary);
        border: 2px dashed var(--border-color);
        border-radius: 10px;
        font-size: 0.85rem;
    }
    .empty-med-msg i {
        font-size: 2rem;
        color: var(--border-color);
        display: block;
        margin-bottom: 8px;
    }
    
    .text-xs { font-size: 0.75rem; }
    .text-sm { font-size: 0.875rem; }
    .text-gray-400 { color: var(--text-secondary); }
    .text-gray-500 { color: var(--text-secondary); }
    .text-gray-700 { color: var(--text-primary); }
    .text-yellow-600 { color: #D97706; }
    .text-primary { color: var(--primary); }
    .font-semibold { font-weight: 600; }
    .font-bold { font-weight: 700; }
    .inline-flex { display: inline-flex; }
    .items-center { align-items: center; }
    .justify-between { justify-content: space-between; }
    .flex { display: flex; }
    .flex-wrap { flex-wrap: wrap; }
    .gap-4 { gap: 1rem; }
    
    .toast-custom {
        position: fixed;
        bottom: 24px;
        right: 24px;
        padding: 12px 18px;
        border-radius: 12px;
        z-index: 999;
        max-width: 360px;
        transform: translateY(100px);
        opacity: 0;
        transition: all 0.4s ease;
        display: flex;
        align-items: center;
        gap: 10px;
        color: white;
        box-shadow: 0 8px 30px rgba(0,0,0,0.2);
    }
    .toast-custom.show { transform: translateY(0); opacity: 1; }
    .toast-custom.success { background: #059669; }
    .toast-custom.error { background: #EF4444; }
    .toast-custom.info { background: var(--primary); }
    .toast-custom.warning { background: #D97706; }
    
    .footer {
        padding: 14px 0;
        border-top: 2px solid var(--border-color);
        margin-top: 20px;
        text-align: center;
        font-size: 0.7rem;
        color: var(--text-secondary);
    }
    .footer .footer-brand { color: var(--primary); font-weight: 600; }
    .separator { color: var(--border-color); margin: 0 4px; }
    
    @media (max-width: 992px) {
        .med-grid { grid-template-columns: 1fr 1fr 1fr; }
        .med-grid .btn-add-med { grid-column: span 3; }
    }
    
    @media (max-width: 768px) {
        .main-content { margin-left: 0; padding: 16px; }
        .prescription-card { padding: 16px 18px; }
        .med-grid { grid-template-columns: 1fr 1fr; }
        .med-grid .btn-add-med { grid-column: span 2; }
        .doctor-info-bar { flex-direction: column; align-items: flex-start; gap: 8px; }
        .form-actions { flex-direction: column; }
        .form-actions .btn { width: 100%; justify-content: center; }
        .btn-primary { flex: none; }
        .page-title { font-size: 1.3rem; }
        .md\:grid-cols-2 { grid-template-columns: 1fr; }
        .page-header { flex-direction: column; }
        .page-header-right { width: 100%; }
        .page-header-right .btn { flex: 1; justify-content: center; }
    }
    
    @media (max-width: 480px) {
        .med-grid { grid-template-columns: 1fr; }
        .med-grid .btn-add-med { grid-column: span 1; }
        .prescription-card { padding: 12px; }
        .btn { padding: 8px 16px; font-size: 0.78rem; min-height: 38px; }
        .medication-item { flex-direction: column; align-items: flex-start; gap: 8px; }
        .page-subtitle { flex-direction: column; align-items: flex-start; gap: 4px; }
    }
    
    @media print {
        .top-nav, .sidebar, .btn, .footer { display: none !important; }
        .main-content { margin: 0 !important; padding: 20px !important; }
        .prescription-card { border: 1px solid #ddd !important; box-shadow: none !important; }
        .page-header { border-bottom: 2px solid #0B5ED7 !important; }
        .form-actions { display: none !important; }
    }
</style>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE
    // ================================================================
    if (localStorage.getItem('darkMode') === 'true') {
        document.documentElement.setAttribute('data-theme', 'dark');
    }

    // ================================================================
    // PATIENT CHANGE - Load visits dynamically
    // ================================================================
    var patientSelect = document.getElementById('patient_id');
    if (patientSelect) {
        patientSelect.addEventListener('change', function() {
            var patientId = this.value;
            if (patientId) {
                window.location.href = 'prescribe.php?patient_id=' + patientId;
            } else {
                window.location.href = 'prescribe.php';
            }
        });
    }

    // ================================================================
    // MEDICATION MANAGEMENT
    // ================================================================
    var selectedMeds = [];
    var medIdCounter = 0;

    var medSelect = document.getElementById('medSelect');
    var medQuantity = document.getElementById('medQuantity');
    var medDosage = document.getElementById('medDosage');
    var medFrequency = document.getElementById('medFrequency');
    var medDuration = document.getElementById('medDuration');
    var medRoute = document.getElementById('medRoute');
    var medInstructions = document.getElementById('medInstructions');
    var addMedBtn = document.getElementById('addMedBtn');

    function addMedication() {
        if (!medSelect) return;
        
        var option = medSelect.options[medSelect.selectedIndex];
        if (!option || !option.value) {
            showToast('Error', 'Please select a medication', 'error');
            return;
        }
        
        var quantity = parseInt(medQuantity.value) || 1;
        var dosage = medDosage.value.trim();
        var frequency = medFrequency.value;
        var duration = parseInt(medDuration.value) || 7;
        var route = medRoute.value;
        var instructions = medInstructions.value.trim();
        var stock = parseInt(option.dataset.stock) || 0;
        
        if (quantity > stock) {
            showToast('Error', 'Not enough stock! Available: ' + stock, 'error');
            return;
        }
        if (!dosage) {
            showToast('Error', 'Please enter dosage', 'error');
            return;
        }
        if (!frequency) {
            showToast('Error', 'Please select frequency', 'error');
            return;
        }
        
        var name = option.text.split(' (Stock:')[0];
        
        selectedMeds.push({
            id: ++medIdCounter,
            med_id: parseInt(option.value),
            name: name,
            quantity: quantity,
            dosage: dosage,
            frequency: frequency,
            duration: duration,
            route: route,
            instructions: instructions
        });
        
        medSelect.value = '';
        medQuantity.value = 1;
        medDosage.value = '';
        medFrequency.value = '';
        medDuration.value = 7;
        medRoute.value = '';
        medInstructions.value = '';
        
        renderMedications();
        showToast('Success', 'Medication added successfully!', 'success');
    }

    function removeMedication(id) {
        selectedMeds = selectedMeds.filter(function(m) { return m.id !== id; });
        renderMedications();
        showToast('Info', 'Medication removed', 'info');
    }

    function renderMedications() {
        var container = document.getElementById('medicationsList');
        var countEl = document.getElementById('medCount');
        
        countEl.textContent = selectedMeds.length + ' items';
        
        if (selectedMeds.length === 0) {
            container.innerHTML = `
                <div class="empty-med-msg">
                    <i class="fas fa-prescription"></i>
                    <p>No medications added yet</p>
                    <p class="text-xs mt-1">Select a medication above and click "Add Medication"</p>
                </div>
            `;
            document.getElementById('medicationsJson').value = '[]';
            return;
        }
        
        var html = '';
        selectedMeds.forEach(function(med, index) {
            html += `
                <div class="medication-item">
                    <div class="med-info">
                        <div class="med-name">
                            ${index + 1}. ${med.name}
                        </div>
                        <div class="med-details">
                            <span>Qty: ${med.quantity}</span>
                            <span>Dosage: ${med.dosage}</span>
                            <span>Freq: ${med.frequency}</span>
                            <span>${med.duration} days</span>
                            ${med.route ? '<span>Route: ' + med.route + '</span>' : ''}
                            ${med.instructions ? '<span>Instr: ' + med.instructions + '</span>' : ''}
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <button type="button" onclick="removeMedication(${med.id})" class="btn-remove">
                            <i class="fas fa-times"></i> Remove
                        </button>
                    </div>
                </div>
            `;
        });
        
        container.innerHTML = html;
        document.getElementById('medicationsJson').value = JSON.stringify(selectedMeds);
    }

    if (addMedBtn) {
        addMedBtn.addEventListener('click', addMedication);
    }

    // Enter key support
    var medFields = [medQuantity, medDosage, medFrequency, medDuration, medRoute, medInstructions];
    medFields.forEach(function(field) {
        if (field) {
            field.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    addMedication();
                }
            });
        }
    });

    // ================================================================
    // FORM VALIDATION
    // ================================================================
    function validateForm() {
        var patient = document.getElementById('patient_id').value;
        var visit = document.getElementById('visit_id').value;
        var diagnosis = document.getElementById('diagnosis').value.trim();
        var medications = document.getElementById('medicationsJson').value;
        
        if (!patient) {
            showToast('Validation Error', 'Please select a patient!', 'error');
            document.getElementById('patient_id').focus();
            return false;
        }
        if (!visit) {
            showToast('Validation Error', 'Please select a visit! Create one from Consultation page.', 'error');
            document.getElementById('visit_id').focus();
            return false;
        }
        if (!diagnosis) {
            showToast('Validation Error', 'Please enter diagnosis!', 'error');
            document.getElementById('diagnosis').focus();
            return false;
        }
        if (!medications || medications === '[]') {
            showToast('Validation Error', 'Please add at least one medication!', 'error');
            return false;
        }
        return true;
    }

    // ================================================================
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        if (!toast) return;
        toast.className = 'toast-custom ' + type;
        toastTitle.textContent = title;
        toastMessage.textContent = message;
        toast.style.display = 'flex';
        toast.classList.add('show');
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 5000);
    }

    // ================================================================
    // DATE & TIME
    // ================================================================
    function updateDateTime() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        var el = document.getElementById('currentDateTime');
        if (el) {
            el.textContent = dateStr + ' • ' + timeStr;
        }
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // SHOW TOAST FOR MESSAGES
    // ================================================================
    <?php if ($message && $message_type): ?>
        setTimeout(function() {
            showToast('<?= $message_type === 'success' ? '✅ Success' : ($message_type === 'warning' ? '⚠️ Notice' : '❌ Error') ?>', 
                '<?= addslashes($message) ?>', 
                '<?= $message_type ?>'
            );
        }, 500);
    <?php endif; ?>

    console.log('%c💊 Prescribe - <?= htmlspecialchars($selected_patient['full_name'] ?? 'Not selected') ?>', 'font-size:16px; font-weight:bold; color:#7C3AED;');
    console.log('%c👤 Patient: <?= $selected_patient_id > 0 ? 'Selected' : 'Not selected' ?>', 'font-size:12px; color:#059669;');
    console.log('%c📋 Visits: <?= count($visits) ?>', 'font-size:12px; color:#64748B;');
    console.log('%c💊 Medications available: <?= count($medications) ?>', 'font-size:12px; color:#34D399;');
    console.log('%c💡 Select patient to load their visits', 'font-size:12px; color:#0B5ED7;');
</script>

</body>
</html>