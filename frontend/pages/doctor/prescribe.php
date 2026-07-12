<?php
// ================================================================
// FILE: frontend/pages/doctor/prescribe.php
// DOCTOR - PRESCRIBE MEDICATION (FULL DROPDOWNS)
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
// GET PATIENTS FOR DROPDOWN
// ================================================================
$stmt = $db->prepare("
    SELECT DISTINCT p.id, p.full_name, p.patient_id 
    FROM patients p
    JOIN visits v ON p.id = v.patient_id
    WHERE v.doctor_id = ?
    ORDER BY p.full_name
");
$stmt->execute([$doctor_id]);
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET VISITS FOR DROPDOWN
// ================================================================
$stmt = $db->prepare("
    SELECT v.id, v.visit_number, p.full_name as patient_name 
    FROM visits v
    JOIN patients p ON v.patient_id = p.id
    WHERE v.doctor_id = ? AND v.status != 'completed'
    ORDER BY v.created_at DESC
");
$stmt->execute([$doctor_id]);
$visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
// GET DIAGNOSIS FOR SELECTED VISIT (AJAX)
// ================================================================
// This will be loaded via AJAX when visit is selected

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
                <i class="fas fa-prescription mr-2" style="color: #0B5ED7;"></i> Prescribe Medication
            </h1>
            <p class="page-subtitle">
                Create a new prescription for a patient
                <span class="branch-tag ml-2">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor_branch_name) ?>
                </span>
            </p>
        </div>
        <a href="my_patients.php" class="btn btn-outline btn-sm">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>

    <div class="card max-w-3xl mx-auto">
        <form method="POST" action="save_prescription.php" id="prescriptionForm">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                
                <!-- Patient Dropdown -->
                <div>
                    <label class="form-label">Patient <span class="text-red-500">*</span></label>
                    <select name="patient_id" id="patient_id" class="form-control" required>
                        <option value="">-- Select Patient --</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?= $patient['id'] ?>">
                                <?= htmlspecialchars($patient['full_name']) ?> (<?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Visit Dropdown -->
                <div>
                    <label class="form-label">Visit <span class="text-red-500">*</span></label>
                    <select name="visit_id" id="visit_id" class="form-control" required>
                        <option value="">-- Select Visit --</option>
                        <?php foreach ($visits as $visit): ?>
                            <option value="<?= $visit['id'] ?>">
                                <?= htmlspecialchars($visit['visit_number']) ?> - <?= htmlspecialchars($visit['patient_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Diagnosis (Readonly - from visit) -->
                <div class="md:col-span-2">
                    <label class="form-label">Diagnosis <span class="text-xs text-gray-400">(Auto-loaded from visit)</span></label>
                    <textarea id="diagnosis" class="form-control" rows="2" readonly placeholder="Select a visit to load diagnosis..."></textarea>
                    <input type="hidden" name="diagnosis" id="diagnosis_hidden">
                </div>

                <!-- Medication Dropdown -->
                <div>
                    <label class="form-label">Medication <span class="text-red-500">*</span></label>
                    <select name="medication_id" id="medication_id" class="form-control" required>
                        <option value="">-- Select Medication --</option>
                        <?php foreach ($medications as $med): ?>
                            <option value="<?= $med['id'] ?>" data-strength="<?= htmlspecialchars($med['strength']) ?>" data-unit="<?= htmlspecialchars($med['unit']) ?>">
                                <?= htmlspecialchars($med['name']) ?> 
                                <?= htmlspecialchars($med['strength']) ?> 
                                <?= htmlspecialchars($med['unit'] ?? '') ?>
                                (<?= htmlspecialchars($med['category']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Dosage -->
                <div>
                    <label class="form-label">Dosage <span class="text-red-500">*</span></label>
                    <input type="text" name="dosage" id="dosage" class="form-control" placeholder="e.g. 500mg" required>
                </div>

                <!-- Frequency Dropdown -->
                <div>
                    <label class="form-label">Frequency <span class="text-red-500">*</span></label>
                    <select name="frequency" id="frequency" class="form-control" required>
                        <option value="">-- Select Frequency --</option>
                        <option value="Once daily">Once daily</option>
                        <option value="Twice daily">Twice daily</option>
                        <option value="Three times daily">Three times daily</option>
                        <option value="Four times daily">Four times daily</option>
                        <option value="Every 6 hours">Every 6 hours</option>
                        <option value="Every 8 hours">Every 8 hours</option>
                        <option value="Every 12 hours">Every 12 hours</option>
                        <option value="As needed (PRN)">As needed (PRN)</option>
                        <option value="Before meals">Before meals</option>
                        <option value="After meals">After meals</option>
                        <option value="At bedtime">At bedtime</option>
                        <option value="Once weekly">Once weekly</option>
                    </select>
                </div>

                <!-- Duration Dropdown -->
                <div>
                    <label class="form-label">Duration <span class="text-red-500">*</span></label>
                    <select name="duration" id="duration" class="form-control" required>
                        <option value="">-- Select Duration --</option>
                        <option value="1 day">1 day</option>
                        <option value="2 days">2 days</option>
                        <option value="3 days">3 days</option>
                        <option value="5 days">5 days</option>
                        <option value="7 days">7 days</option>
                        <option value="10 days">10 days</option>
                        <option value="14 days">14 days</option>
                        <option value="21 days">21 days</option>
                        <option value="30 days">30 days</option>
                        <option value="60 days">60 days</option>
                        <option value="90 days">90 days</option>
                        <option value="Ongoing">Ongoing</option>
                    </select>
                </div>

                <!-- Quantity -->
                <div>
                    <label class="form-label">Quantity <span class="text-red-500">*</span></label>
                    <input type="number" name="quantity" id="quantity" class="form-control" placeholder="e.g. 30" min="1" required>
                </div>

                <!-- Route Dropdown -->
                <div>
                    <label class="form-label">Route <span class="text-red-500">*</span></label>
                    <select name="route" id="route" class="form-control" required>
                        <option value="">-- Select Route --</option>
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

                <!-- Instructions -->
                <div class="md:col-span-2">
                    <label class="form-label">Instructions</label>
                    <textarea name="instructions" id="instructions" class="form-control" rows="3" placeholder="Special instructions for the patient..."></textarea>
                    <div class="flex flex-wrap gap-2 mt-2">
                        <button type="button" class="btn-outline-sm" onclick="addInstruction('Take with food')">+ With food</button>
                        <button type="button" class="btn-outline-sm" onclick="addInstruction('Take on empty stomach')">+ Empty stomach</button>
                        <button type="button" class="btn-outline-sm" onclick="addInstruction('Do not crush or chew')">+ Do not crush</button>
                        <button type="button" class="btn-outline-sm" onclick="addInstruction('Finish full course')">+ Finish course</button>
                        <button type="button" class="btn-outline-sm" onclick="addInstruction('Drink plenty of water')">+ Drink water</button>
                        <button type="button" class="btn-outline-sm" onclick="addInstruction('Avoid alcohol')">+ Avoid alcohol</button>
                    </div>
                </div>

                <!-- Buttons -->
                <div class="md:col-span-2 flex gap-3 pt-4 border-t">
                    <button type="submit" class="btn btn-green flex-1">
                        <i class="fas fa-save"></i> Save Prescription
                    </button>
                    <button type="reset" class="btn btn-outline">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Prescribe
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

<style>
    .max-w-3xl { max-width: 48rem; }
    .mx-auto { margin-left: auto; margin-right: auto; }
    .form-label { display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-primary); margin-bottom: 4px; }
    .form-control { width: 100%; padding: 10px 14px; border: 2px solid var(--border-color); border-radius: 10px; font-size: 0.9rem; background: var(--bg-card); color: var(--text-primary); outline: none; transition: all 0.3s; }
    .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12); }
    .form-control:disabled, .form-control[readonly] { background: var(--bg-body); color: var(--text-secondary); cursor: not-allowed; }
    .form-control::placeholder { color: var(--text-secondary); opacity: 0.6; }
    .btn { display: inline-flex; align-items: center; gap: 6px; padding: 10px 20px; border-radius: 10px; font-weight: 600; font-size: 0.85rem; transition: all 0.3s; cursor: pointer; border: none; text-decoration: none; }
    .btn-green { background: var(--green); color: white; }
    .btn-green:hover { background: var(--green-dark); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3); }
    .btn-outline { background: transparent; color: var(--text-secondary); border: 2px solid var(--border-color); }
    .btn-outline:hover { background: var(--bg-body); border-color: var(--primary); color: var(--primary); }
    .btn-outline-sm { background: transparent; color: var(--text-secondary); border: 1px solid var(--border-color); padding: 4px 12px; border-radius: 6px; font-size: 0.7rem; cursor: pointer; transition: all 0.3s; }
    .btn-outline-sm:hover { background: var(--primary-bg); border-color: var(--primary); color: var(--primary); }
    .btn-sm { padding: 4px 10px; font-size: 0.7rem; border-radius: 6px; }
    .flex-1 { flex: 1; }
    .text-red-500 { color: #EF4444; }
    .text-xs { font-size: 0.75rem; }
    .text-gray-400 { color: var(--text-muted); }
    .grid { display: grid; }
    .grid-cols-1 { grid-template-columns: 1fr; }
    .md\\:grid-cols-2 { grid-template-columns: 1fr 1fr; }
    .gap-4 { gap: 1rem; }
    .md\\:col-span-2 { grid-column: span 2; }
    .mt-2 { margin-top: 0.5rem; }
    .pt-4 { padding-top: 1rem; }
    .border-t { border-top: 2px solid var(--border-color); }
    .flex-wrap { flex-wrap: wrap; }
    [data-theme="dark"] .form-control:disabled, [data-theme="dark"] .form-control[readonly] { background: #0F172A; }
    [data-theme="dark"] .btn-outline-sm:hover { background: #1E3A5F; }
</style>

<script>
    // ================================================================
    // DIAGNOSIS AUTO-LOAD - When Visit is selected
    // ================================================================
    document.getElementById('visit_id')?.addEventListener('change', function() {
        var visitId = this.value;
        var diagnosisTextarea = document.getElementById('diagnosis');
        var diagnosisHidden = document.getElementById('diagnosis_hidden');
        
        if (visitId) {
            // Show loading state
            diagnosisTextarea.value = 'Loading diagnosis...';
            
            // AJAX request to get diagnosis
            fetch('get_diagnosis.php?visit_id=' + visitId)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.diagnosis) {
                        diagnosisTextarea.value = data.diagnosis;
                        diagnosisHidden.value = data.diagnosis;
                    } else {
                        diagnosisTextarea.value = 'No diagnosis found for this visit. Please enter manually.';
                        diagnosisHidden.value = '';
                    }
                })
                .catch(error => {
                    diagnosisTextarea.value = 'Error loading diagnosis. Please enter manually.';
                    diagnosisHidden.value = '';
                    console.error('Error:', error);
                });
        } else {
            diagnosisTextarea.value = '';
            diagnosisHidden.value = '';
        }
    });

    // ================================================================
    // MEDICATION - Auto-fill dosage when medication selected
    // ================================================================
    document.getElementById('medication_id')?.addEventListener('change', function() {
        var selected = this.options[this.selectedIndex];
        var dosage = document.getElementById('dosage');
        
        if (selected && selected.dataset.strength) {
            var strength = selected.dataset.strength;
            var unit = selected.dataset.unit || '';
            dosage.value = strength + ' ' + unit;
        } else {
            dosage.value = '';
        }
    });

    // ================================================================
    // ADD INSTRUCTION - Quick add buttons
    // ================================================================
    function addInstruction(text) {
        var instructions = document.getElementById('instructions');
        var current = instructions.value.trim();
        if (current) {
            instructions.value = current + '. ' + text;
        } else {
            instructions.value = text;
        }
        instructions.focus();
    }

    // ================================================================
    // SHOW TOAST
    // ================================================================
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

    // ================================================================
    // FORM VALIDATION
    // ================================================================
    document.getElementById('prescriptionForm')?.addEventListener('submit', function(e) {
        var patient = document.getElementById('patient_id').value;
        var visit = document.getElementById('visit_id').value;
        var medication = document.getElementById('medication_id').value;
        var dosage = document.getElementById('dosage').value.trim();
        var frequency = document.getElementById('frequency').value;
        var duration = document.getElementById('duration').value;
        var quantity = document.getElementById('quantity').value;
        var route = document.getElementById('route').value;
        
        if (!patient || !visit || !medication || !dosage || !frequency || !duration || !quantity || !route) {
            e.preventDefault();
            showToast('Validation Error', 'Please fill in all required fields!', 'error');
            return false;
        }
        return true;
    });

    console.log('%c👨‍⚕️ Prescribe - <?= htmlspecialchars($doctor_name) ?>', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c💊 Medications: <?= count($medications) ?> available', 'font-size:12px; color:#059669;');
    console.log('%c👥 Patients: <?= count($patients) ?> available', 'font-size:12px; color:#059669;');
    console.log('%c📋 Visits: <?= count($visits) ?> available', 'font-size:12px; color:#059669;');
</script>

</body>
</html>