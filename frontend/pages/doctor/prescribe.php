<?php
// ================================================================
// FILE: frontend/pages/doctor/prescribe.php
// DOCTOR - PRESCRIBE MEDICATIONS (SHARED HEADER & SIDEBAR)
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// IF NO SESSION, USE DR. SARAH MWAMBA
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
// GET PATIENTS (for dropdown)
// ================================================================
$db_path = 'C:/xampp/htdocs/dispensary_system/backend/config/database.php';
if (file_exists($db_path)) {
    require_once $db_path;
} else {
    die("❌ Database file not found at: " . $db_path);
}
$db = Database::getInstance()->getConnection();

// Get all patients for this doctor
$stmt = $db->prepare("
    SELECT DISTINCT p.* 
    FROM patients p
    JOIN visits v ON p.id = v.patient_id
    WHERE v.doctor_id = ?
    ORDER BY p.full_name
");
$stmt->execute([$doctor_id]);
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET SELECTED PATIENT
// ================================================================
$selected_patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$selected_patient = null;
$visits = [];

if ($selected_patient_id > 0) {
    $stmt = $db->prepare("SELECT * FROM patients WHERE id = ?");
    $stmt->execute([$selected_patient_id]);
    $selected_patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($selected_patient) {
        // Get visits
        $stmt = $db->prepare("
            SELECT * FROM visits 
            WHERE patient_id = ? AND doctor_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$selected_patient_id, $doctor_id]);
        $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// ================================================================
// GET MEDICATIONS (from medications_inventory)
// ================================================================
$medications = [];
$stmt = $db->prepare("
    SELECT id, medication_name, quantity 
    FROM medications_inventory 
    WHERE status = 'active' AND quantity > 0
    ORDER BY medication_name
");
$stmt->execute();
$medications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// PROCESS PRESCRIPTION
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    if ($action === 'prescribe') {
        $patient_id = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : 0;
        $visit_id = isset($_POST['visit_id']) ? (int)$_POST['visit_id'] : 0;
        $diagnosis = isset($_POST['diagnosis']) ? trim($_POST['diagnosis']) : '';
        $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
        $medications_json = isset($_POST['medications_json']) ? $_POST['medications_json'] : '[]';
        $medications_data = json_decode($medications_json, true);
        
        if ($patient_id <= 0) {
            $message = "Please select a patient!";
            $message_type = 'error';
        } elseif (empty($diagnosis)) {
            $message = "Please enter diagnosis!";
            $message_type = 'error';
        } elseif (empty($medications_data)) {
            $message = "Please add at least one medication!";
            $message_type = 'error';
        } else {
            try {
                // Get or create bill (background)
                $bill_id = $_SESSION['current_bill_id'] ?? 0;
                
                if ($bill_id == 0) {
                    $stmt = $db->prepare("
                        SELECT id FROM patient_bills 
                        WHERE patient_id = ? AND status IN ('pending', 'partial')
                        ORDER BY created_at DESC LIMIT 1
                    ");
                    $stmt->execute([$patient_id]);
                    $bill = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($bill) {
                        $bill_id = $bill['id'];
                        $_SESSION['current_bill_id'] = $bill_id;
                    }
                }
                
                // ================================================================
                // 1. CREATE PRESCRIPTION
                // ================================================================
                $prescription_number = 'RX-' . date('Ymd') . '-' . str_pad($patient_id, 4, '0', STR_PAD_LEFT) . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
                
                $stmt = $db->prepare("
                    INSERT INTO prescriptions (
                        prescription_number, patient_id, doctor_id, visit_id, diagnosis, notes, status, branch_id
                    ) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)
                ");
                $stmt->execute([
                    $prescription_number,
                    $patient_id,
                    $doctor_id,
                    $visit_id,
                    $diagnosis,
                    $notes,
                    $doctor_branch_id
                ]);
                $prescription_id = $db->lastInsertId();
                
                // ================================================================
                // 2. ADD PRESCRIPTION ITEMS
                // ================================================================
                $total_med_fees = 0;
                
                foreach ($medications_data as $med) {
                    $med_id = (int)$med['med_id'];
                    $quantity = (int)$med['quantity'];
                    $dosage = isset($med['dosage']) ? $med['dosage'] : '';
                    $frequency = isset($med['frequency']) ? $med['frequency'] : '';
                    $duration = isset($med['duration']) ? $med['duration'] : '';
                    $route = isset($med['route']) ? $med['route'] : '';
                    $instructions = isset($med['instructions']) ? $med['instructions'] : '';
                    
                    // Get medication details
                    $stmt = $db->prepare("
                        SELECT medication_name, selling_price 
                        FROM medications_inventory 
                        WHERE id = ? AND status = 'active'
                    ");
                    $stmt->execute([$med_id]);
                    $medication = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($medication) {
                        $unit_price = $medication['selling_price'] ?? 0;
                        $total_price = $unit_price * $quantity;
                        $total_med_fees += $total_price;
                        
                        // Add route to instructions if present
                        $full_instructions = $instructions;
                        if (!empty($route)) {
                            $full_instructions = $instructions . ' (Route: ' . $route . ')';
                        }
                        
                        // Add to prescription_items
                        $stmt = $db->prepare("
                            INSERT INTO prescription_items (
                                prescription_id, medication_name, dosage, frequency, quantity, duration, instructions, unit_price, total_price
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
                // 3. UPDATE BILL WITH MEDICATION FEES (BACKGROUND)
                // ================================================================
                if ($bill_id > 0 && $total_med_fees > 0) {
                    $stmt = $db->prepare("
                        UPDATE patient_bills 
                        SET medication_fees = medication_fees + ?,
                            subtotal = subtotal + ?,
                            total_amount = total_amount + ?,
                            balance = balance + ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $total_med_fees,
                        $total_med_fees,
                        $total_med_fees,
                        $total_med_fees,
                        $bill_id
                    ]);
                    
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
                
                echo '<script>setTimeout(function(){ window.location.href = "patient_details.php?id=' . $patient_id . '"; }, 2000);</script>';
                
            } catch (Exception $e) {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// ================================================================
// DOCTOR BRANCH NAME
// ================================================================
$doctor_branch_name = 'Not Assigned';
try {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$doctor_branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) {
        $doctor_branch_name = $branch_data['name'];
    }
} catch (Exception $e) {}

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
// INCLUDE SHARED HEADER & SIDEBAR (NOT CUSTOM)
// ================================================================
include_once 'C:/xampp/htdocs/dispensary_system/frontend/components/doctor_header.php';
include_once 'C:/xampp/htdocs/dispensary_system/frontend/components/doctor_sidebar.php';
?>

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
    [data-theme="dark"] .medication-item .med-details span {
        background: #1E293B;
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
    .branch-badge-display {
        display: inline-block;
        font-size: 0.6rem;
        font-weight: 600;
        padding: 2px 10px;
        border-radius: 20px;
        background: var(--success-bg);
        color: var(--success);
    }
    [data-theme="dark"] .branch-badge-display {
        background: #1A3A2A;
        color: #34D399;
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
    
    .grid { display: grid; }
    .grid-cols-1 { grid-template-columns: 1fr; }
    .md\:grid-cols-2 { grid-template-columns: 1fr 1fr; }
    .gap-4 { gap: 1rem; }
    .gap-3 { gap: 0.75rem; }
    .mt-2 { margin-top: 0.5rem; }
    .mt-3 { margin-top: 0.75rem; }
    .mt-4 { margin-top: 1rem; }
    .mb-2 { margin-bottom: 0.5rem; }
    .mb-6 { margin-bottom: 1.5rem; }
    .ml-2 { margin-left: 0.5rem; }
    .mr-1 { margin-right: 0.25rem; }
    .mr-2 { margin-right: 0.5rem; }
    .flex { display: flex; }
    .flex-wrap { flex-wrap: wrap; }
    .items-center { align-items: center; }
    .justify-between { justify-content: space-between; }
    .text-sm { font-size: 0.875rem; }
    .text-xs { font-size: 0.75rem; }
    .text-gray-400 { color: var(--text-muted); }
    .text-gray-500 { color: var(--text-secondary); }
    .text-gray-700 { color: var(--text-primary); }
    .text-gray-800 { color: var(--text-primary); }
    .font-semibold { font-weight: 600; }
    .font-bold { font-weight: 700; }
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
    
    @media (max-width: 992px) {
        .med-grid { grid-template-columns: 1fr 1fr 1fr; }
        .med-grid .btn-add-med { grid-column: span 3; }
    }
    @media (max-width: 768px) {
        .prescription-card { padding: 16px 18px; }
        .med-grid { grid-template-columns: 1fr 1fr; }
        .med-grid .btn-add-med { grid-column: span 2; }
        .doctor-info-bar { flex-direction: column; align-items: flex-start; gap: 8px; }
        .form-actions { flex-direction: column; }
        .form-actions .btn { width: 100%; justify-content: center; }
        .btn-primary { flex: none; }
        .page-header .page-title { font-size: 1.3rem; }
        .md\:grid-cols-2 { grid-template-columns: 1fr; }
    }
    @media (max-width: 480px) {
        .med-grid { grid-template-columns: 1fr; }
        .med-grid .btn-add-med { grid-column: span 1; }
        .prescription-card { padding: 12px; }
        .btn { padding: 8px 16px; font-size: 0.78rem; min-height: 38px; }
        .medication-item { flex-direction: column; align-items: flex-start; gap: 8px; }
    }
</style>

<!-- ================================================================ -->
<!-- TOP NAVIGATION - FROM SHARED HEADER (doctor_header.php) -->
<!-- ================================================================ -->

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <div class="page-header flex flex-wrap justify-between items-center gap-3 mb-6">
        <div>
            <h1 class="page-title">
                <i class="fas fa-prescription mr-2" style="color: #7C3AED;"></i> Prescribe Medication
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
        <div>
            <a href="my_patients.php" class="btn btn-outline btn-sm">
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
                    <p class="text-sm text-gray-500"><?= htmlspecialchars($_SESSION['specialty'] ?? 'General Practitioner') ?> • <?= htmlspecialchars($doctor_branch_name) ?></p>
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
                        <?php foreach ($visits as $v): ?>
                            <option value="<?= $v['id'] ?>">
                                <?= htmlspecialchars($v['visit_number'] ?? 'N/A') ?> 
                                - <?= date('M d, Y', strtotime($v['created_at'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
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
                        </select>
                    </div>
                    
                    <div>
                        <label class="form-label">Duration (Days)</label>
                        <input type="number" id="medDuration" class="form-control" placeholder="Days" min="1" value="7">
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
            <span class="text-gray-300 mx-2">|</span>
            Prescribe
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
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE
    // ================================================================
    var darkModeToggle = document.getElementById('darkModeToggle');
    var darkIcon = document.getElementById('darkIcon');
    var darkText = document.getElementById('darkText');
    var htmlElement = document.documentElement;
    
    var savedDarkMode = localStorage.getItem('darkMode');
    if (savedDarkMode === 'true') {
        htmlElement.setAttribute('data-theme', 'dark');
        darkIcon.className = 'fas fa-sun';
        darkText.textContent = 'Light';
    }
    
    darkModeToggle?.addEventListener('click', function() {
        var isDark = htmlElement.getAttribute('data-theme') === 'dark';
        if (isDark) {
            htmlElement.removeAttribute('data-theme');
            darkIcon.className = 'fas fa-moon';
            darkText.textContent = 'Dark';
            localStorage.setItem('darkMode', 'false');
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
        }
    });

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
    }

    // ================================================================
    // PATIENT CHANGE - Redirect to load data
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
            return false;
        }
        if (!visit) {
            showToast('Validation Error', 'Please select a visit!', 'error');
            return false;
        }
        if (!diagnosis) {
            showToast('Validation Error', 'Please enter diagnosis!', 'error');
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
        
        toast.className = 'toast-custom ' + type;
        toastTitle.textContent = title;
        toastMessage.textContent = message;
        toast.style.display = 'flex';
        
        toast.classList.add('show');
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() {
                toast.style.display = 'none';
            }, 400);
        }, 3500);
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

    console.log('%c💊 Braick - Prescribe (Shared Header & Sidebar)', 'font-size:18px; font-weight:bold; color:#7C3AED;');
    console.log('%c👤 Patient: <?= $selected_patient ? htmlspecialchars($selected_patient['full_name']) : 'Not selected' ?>', 'font-size:13px; color:#059669;');
    console.log('%c💊 Medications loaded: <?= count($medications) ?>', 'font-size:13px; color:#34D399;');
    console.log('%c📸 Profile picture from shared header', 'font-size:13px; color:#0B5ED7;');
</script>

</body>
</html>