<?php
// ================================================================
// FILE: frontend/pages/doctor/prescribe.php
// DOCTOR - PRESCRIBE MEDICATION (FIXED - NO AJAX FILES NEEDED)
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
$doctor_specialty = $_SESSION['specialty'] ?? 'General Practitioner';

// ================================================================
// GET SELECTED PATIENT FROM URL (from my_patients.php)
// ================================================================
$selected_patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$selected_visit_id = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;

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
// GET SELECTED PATIENT DATA
// ================================================================
$selected_patient = null;
if ($selected_patient_id > 0) {
    $stmt = $db->prepare("SELECT * FROM patients WHERE id = ?");
    $stmt->execute([$selected_patient_id]);
    $selected_patient = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ================================================================
// GET PATIENTS FOR DROPDOWN (ALL PATIENTS OF THIS DOCTOR)
// ================================================================
$stmt = $db->prepare("
    SELECT DISTINCT p.id, p.full_name, p.patient_id, p.phone 
    FROM patients p
    JOIN visits v ON p.id = v.patient_id
    WHERE v.doctor_id = ?
    ORDER BY p.full_name
");
$stmt->execute([$doctor_id]);
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET VISITS FOR SELECTED PATIENT - DIRECT QUERY
// ================================================================
$visits = [];
$patient_visits_json = '[]';
$selected_visit_diagnosis = '';

if ($selected_patient_id > 0) {
    // Get all visits for this patient (no status filter)
    $stmt = $db->prepare("
        SELECT 
            v.id, 
            v.visit_number, 
            v.diagnosis, 
            v.symptoms,
            v.notes,
            v.status,
            v.created_at,
            p.full_name as patient_name
        FROM visits v
        JOIN patients p ON v.patient_id = p.id
        WHERE v.patient_id = ? 
        AND v.doctor_id = ?
        ORDER BY v.created_at DESC
    ");
    $stmt->execute([$selected_patient_id, $doctor_id]);
    $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If no visits with this doctor, try to get any visit for this patient
    if (empty($visits)) {
        $stmt = $db->prepare("
            SELECT 
                v.id, 
                v.visit_number, 
                v.diagnosis, 
                v.symptoms,
                v.notes,
                v.status,
                v.created_at,
                p.full_name as patient_name
            FROM visits v
            JOIN patients p ON v.patient_id = p.id
            WHERE v.patient_id = ?
            ORDER BY v.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$selected_patient_id]);
        $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Convert visits to JSON for JavaScript
    $patient_visits_json = json_encode($visits);
    
    // Get selected visit diagnosis
    if ($selected_visit_id > 0) {
        foreach ($visits as $visit) {
            if ($visit['id'] == $selected_visit_id) {
                $selected_visit_diagnosis = $visit['diagnosis'] ?? $visit['symptoms'] ?? $visit['notes'] ?? '';
                break;
            }
        }
    } elseif (!empty($visits)) {
        // Auto-select first visit
        $selected_visit_id = $visits[0]['id'];
        $selected_visit_diagnosis = $visits[0]['diagnosis'] ?? $visits[0]['symptoms'] ?? $visits[0]['notes'] ?? '';
    }
}

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
                <?php if ($selected_patient): ?>
                    <span class="ml-2 inline-flex bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs border border-green-200">
                        <i class="fas fa-user mr-1"></i> Patient: <?= htmlspecialchars($selected_patient['full_name']) ?>
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div class="flex gap-2">
            <a href="my_patients.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Patients
            </a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PRESCRIPTION FORM -->
    <!-- ================================================================ -->
    <div class="prescription-card">
        
        <!-- Doctor Info Bar -->
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

        <form method="POST" action="save_prescription.php" id="prescriptionForm">
            
            <!-- Patient & Visit Selection -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                
                <!-- Patient Dropdown -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-user text-blue-600 mr-1"></i> Patient <span class="text-red-500">*</span>
                    </label>
                    <select name="patient_id" id="patient_id" class="form-control" required>
                        <option value="">-- Select Patient --</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?= $patient['id'] ?>" <?= $selected_patient_id == $patient['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($patient['full_name']) ?> 
                                (<?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?>)
                                <?= !empty($patient['phone']) ? '• ' . htmlspecialchars($patient['phone']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Visit Dropdown -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-clinic-medical text-green-600 mr-1"></i> Visit <span class="text-red-500">*</span>
                    </label>
                    <select name="visit_id" id="visit_id" class="form-control" required>
                        <option value="">-- Select Visit --</option>
                        <?php if (!empty($visits)): ?>
                            <?php foreach ($visits as $visit): ?>
                                <option value="<?= $visit['id'] ?>" <?= $selected_visit_id == $visit['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($visit['visit_number']) ?> 
                                    (<?= date('M d, Y', strtotime($visit['created_at'])) ?>)
                                    <?= !empty($visit['status']) ? '- ' . htmlspecialchars($visit['status']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="">-- No visits found --</option>
                        <?php endif; ?>
                    </select>
                    <?php if (empty($visits) && $selected_patient_id > 0): ?>
                        <p class="text-xs text-yellow-600 mt-1">
                            <i class="fas fa-exclamation-triangle mr-1"></i> No visits found for this patient. Please create a visit first.
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Diagnosis -->
            <div class="mt-4">
                <label class="form-label">
                    <i class="fas fa-stethoscope text-purple-600 mr-1"></i> Diagnosis 
                    <span class="text-xs text-gray-400">(Auto-loaded from visit)</span>
                </label>
                <textarea id="diagnosis" name="diagnosis" class="form-control" rows="2" placeholder="Select a visit to load diagnosis..."><?= htmlspecialchars($selected_visit_diagnosis) ?></textarea>
            </div>

            <!-- Medication Details -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                
                <!-- Medication Dropdown -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-pills text-blue-600 mr-1"></i> Medication <span class="text-red-500">*</span>
                    </label>
                    <select name="medication_id" id="medication_id" class="form-control" required>
                        <option value="">-- Select Medication --</option>
                        <?php foreach ($medications as $med): ?>
                            <option value="<?= $med['id'] ?>" 
                                    data-strength="<?= htmlspecialchars($med['strength'] ?? '') ?>" 
                                    data-unit="<?= htmlspecialchars($med['unit'] ?? '') ?>">
                                <?= htmlspecialchars($med['name']) ?> 
                                <?= !empty($med['strength']) ? htmlspecialchars($med['strength']) : '' ?>
                                <?= !empty($med['unit']) ? htmlspecialchars($med['unit']) : '' ?>
                                (<?= htmlspecialchars($med['category'] ?? 'General') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Dosage -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-weight-scale text-blue-600 mr-1"></i> Dosage <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="dosage" id="dosage" class="form-control" placeholder="e.g. 500mg" required>
                </div>

                <!-- Quantity -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-hashtag text-blue-600 mr-1"></i> Quantity <span class="text-red-500">*</span>
                    </label>
                    <input type="number" name="quantity" id="quantity" class="form-control" placeholder="e.g. 30" min="1" required>
                </div>
            </div>

            <!-- Frequency, Duration, Route -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                
                <!-- Frequency -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-clock text-blue-600 mr-1"></i> Frequency <span class="text-red-500">*</span>
                    </label>
                    <select name="frequency" id="frequency" class="form-control" required>
                        <option value="">-- Select --</option>
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

                <!-- Duration -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-calendar-alt text-blue-600 mr-1"></i> Duration <span class="text-red-500">*</span>
                    </label>
                    <select name="duration" id="duration" class="form-control" required>
                        <option value="">-- Select --</option>
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

                <!-- Route -->
                <div>
                    <label class="form-label">
                        <i class="fas fa-arrows-alt text-blue-600 mr-1"></i> Route <span class="text-red-500">*</span>
                    </label>
                    <select name="route" id="route" class="form-control" required>
                        <option value="">-- Select --</option>
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
            </div>

            <!-- Instructions -->
            <div class="mt-4">
                <label class="form-label">
                    <i class="fas fa-info-circle text-blue-600 mr-1"></i> Instructions
                </label>
                <textarea name="instructions" id="instructions" class="form-control" rows="3" placeholder="Special instructions for the patient..."></textarea>
                
                <!-- Quick Instruction Buttons -->
                <div class="flex flex-wrap gap-2 mt-2">
                    <button type="button" class="btn-quick" onclick="addInstruction('Take with food')">🍽️ With food</button>
                    <button type="button" class="btn-quick" onclick="addInstruction('Take on empty stomach')">🕐 Empty stomach</button>
                    <button type="button" class="btn-quick" onclick="addInstruction('Do not crush or chew')">🚫 Do not crush</button>
                    <button type="button" class="btn-quick" onclick="addInstruction('Finish full course')">✅ Finish course</button>
                    <button type="button" class="btn-quick" onclick="addInstruction('Drink plenty of water')">💧 Drink water</button>
                    <button type="button" class="btn-quick" onclick="addInstruction('Avoid alcohol')">🚫 Avoid alcohol</button>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Prescription
                </button>
                <button type="reset" class="btn btn-outline">
                    <i class="fas fa-undo"></i> Reset
                </button>
                <button type="button" class="btn btn-outline" onclick="window.location.href='my_patients.php'">
                    <i class="fas fa-times"></i> Cancel
                </button>
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

<!-- ================================================================ -->
<!-- STYLES -->
<!-- ================================================================ -->
<style>
    .prescription-card {
        background: var(--bg-card);
        border-radius: 20px;
        padding: 28px 32px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        max-width: 56rem;
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
        padding: 12px 16px;
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
        background: var(--bg-body);
        color: var(--text-secondary);
        cursor: not-allowed;
    }
    
    .form-control::placeholder {
        color: var(--text-secondary);
        opacity: 0.6;
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
    
    .btn-primary:active {
        transform: translateY(0);
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
    
    .btn-quick {
        background: var(--bg-body);
        color: var(--text-secondary);
        border: 1px solid var(--border-color);
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        cursor: pointer;
        transition: all 0.3s ease;
        font-weight: 500;
    }
    
    .btn-quick:hover {
        background: var(--primary-bg);
        border-color: var(--primary);
        color: var(--primary);
        transform: translateY(-1px);
    }
    
    [data-theme="dark"] .btn-quick:hover {
        background: #1E3A5F;
    }
    
    .text-red-500 { color: #EF4444; }
    .text-xs { font-size: 0.75rem; }
    .text-gray-400 { color: var(--text-muted); }
    .text-yellow-600 { color: #D97706; }
    
    .grid { display: grid; }
    .grid-cols-1 { grid-template-columns: 1fr; }
    .md\:grid-cols-2 { grid-template-columns: 1fr 1fr; }
    .md\:grid-cols-3 { grid-template-columns: 1fr 1fr 1fr; }
    .gap-4 { gap: 1rem; }
    .gap-5 { gap: 1.25rem; }
    .mt-2 { margin-top: 0.5rem; }
    .mt-4 { margin-top: 1rem; }
    .mb-6 { margin-bottom: 1.5rem; }
    .ml-2 { margin-left: 0.5rem; }
    .mr-1 { margin-right: 0.25rem; }
    .flex { display: flex; }
    .flex-wrap { flex-wrap: wrap; }
    .items-center { align-items: center; }
    .justify-between { justify-content: space-between; }
    .gap-2 { gap: 0.5rem; }
    .gap-3 { gap: 0.75rem; }
    .gap-4 { gap: 1rem; }
    .flex-1 { flex: 1; }
    .font-semibold { font-weight: 600; }
    .text-sm { font-size: 0.875rem; }
    .text-gray-500 { color: var(--text-secondary); }
    .text-gray-700 { color: var(--text-primary); }
    .text-gray-800 { color: var(--text-primary); }
    .font-medium { font-weight: 500; }
    .inline-flex { display: inline-flex; }
    .rounded-full { border-radius: 9999px; }
    .px-3 { padding-left: 0.75rem; padding-right: 0.75rem; }
    .py-1 { padding-top: 0.25rem; padding-bottom: 0.25rem; }
    .border { border-width: 1px; border-style: solid; }
    .border-green-200 { border-color: #A7F3D0; }
    .bg-green-100 { background: #D1FAE5; }
    .text-green-700 { color: #065F46; }
    
    [data-theme="dark"] .bg-green-100 { background: #1A3A2A; }
    [data-theme="dark"] .text-green-700 { color: #34D399; }
    [data-theme="dark"] .border-green-200 { border-color: #1A3A2A; }
    
    /* Responsive */
    @media (max-width: 768px) {
        .prescription-card { padding: 18px 16px; }
        .md\:grid-cols-2 { grid-template-columns: 1fr; }
        .md\:grid-cols-3 { grid-template-columns: 1fr; }
        .doctor-info-bar { flex-direction: column; align-items: flex-start; gap: 8px; }
        .form-actions { flex-direction: column; }
        .form-actions .btn { width: 100%; justify-content: center; }
        .btn-primary { flex: none; }
    }
    
    @media (max-width: 480px) {
        .prescription-card { padding: 12px; }
        .btn { padding: 8px 16px; font-size: 0.75rem; min-height: 38px; }
        .form-control { padding: 8px 12px; font-size: 0.8rem; }
    }
</style>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // PATIENT CHANGE - Load visits (NO AJAX - uses PHP data)
    // ================================================================
    document.getElementById('patient_id')?.addEventListener('change', function() {
        var patientId = this.value;
        var visitSelect = document.getElementById('visit_id');
        var diagnosisTextarea = document.getElementById('diagnosis');
        
        if (!patientId) {
            visitSelect.innerHTML = '<option value="">-- Select Visit --</option>';
            diagnosisTextarea.value = '';
            return;
        }
        
        // Redirect to same page with patient_id parameter
        window.location.href = 'prescribe.php?patient_id=' + patientId;
    });

    // ================================================================
    // VISIT CHANGE - Load diagnosis (NO AJAX - uses PHP data)
    // ================================================================
    document.getElementById('visit_id')?.addEventListener('change', function() {
        var visitId = this.value;
        var patientId = document.getElementById('patient_id').value;
        
        if (visitId && patientId) {
            // Redirect to same page with both parameters
            window.location.href = 'prescribe.php?patient_id=' + patientId + '&visit_id=' + visitId;
        }
    });

    // ================================================================
    // MEDICATION - Auto-fill dosage
    // ================================================================
    document.getElementById('medication_id')?.addEventListener('change', function() {
        var selected = this.options[this.selectedIndex];
        var dosage = document.getElementById('dosage');
        
        if (selected && selected.dataset.strength) {
            var strength = selected.dataset.strength || '';
            var unit = selected.dataset.unit || '';
            dosage.value = strength + ' ' + unit;
        } else {
            dosage.value = '';
        }
    });

    // ================================================================
    // ADD INSTRUCTION
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

    // ================================================================
    // SHOW MESSAGE IF NO VISITS
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        var visitSelect = document.getElementById('visit_id');
        var patientId = '<?= $selected_patient_id ?>';
        var visitCount = <?= count($visits) ?>;
        
        if (patientId && visitCount === 0) {
            showToast('Info', 'No visits found for this patient. Please create a visit first.', 'info');
        }
        
        <?php if (!empty($selected_visit_diagnosis)): ?>
            // Diagnosis already loaded from PHP
            console.log('✅ Diagnosis loaded: <?= addslashes($selected_visit_diagnosis) ?>');
        <?php endif; ?>
    });

    console.log('%c👨‍⚕️ Prescribe - <?= htmlspecialchars($doctor_name) ?>', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Patient: <?= $selected_patient_id > 0 ? htmlspecialchars($selected_patient['full_name'] ?? 'Selected') : 'None' ?>', 'font-size:12px; color:#059669;');
    console.log('%c📋 Visits: <?= count($visits) ?> found', 'font-size:12px; color:#64748B;');
    console.log('%c💊 Medications: <?= count($medications) ?> available', 'font-size:12px; color:#64748B;');
    console.log('%c✅ NO AJAX - All data loaded from PHP', 'font-size:12px; color:#0B5ED7;');
</script>

</body>
</html>