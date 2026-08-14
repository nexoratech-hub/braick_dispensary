<?php
// ================================================================
// FILE: frontend/pages/doctor/save_prescription.php
// DOCTOR - SAVE PRESCRIPTION
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
$doctor_name = $_SESSION['full_name'] ?? 'Doctor';
$branch_id = $_SESSION['branch_id'] ?? 1;
$is_admin = ($_SESSION['role'] === 'admin');

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
// GET FORM DATA
// ================================================================
$patient_id = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : 0;
$visit_id = isset($_POST['visit_id']) ? (int)$_POST['visit_id'] : 0;
$medication_id = isset($_POST['medication_id']) ? (int)$_POST['medication_id'] : 0;
$diagnosis = isset($_POST['diagnosis']) ? trim($_POST['diagnosis']) : '';
$dosage = isset($_POST['dosage']) ? trim($_POST['dosage']) : '';
$frequency = isset($_POST['frequency']) ? trim($_POST['frequency']) : '';
$duration = isset($_POST['duration']) ? trim($_POST['duration']) : '';
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
$route = isset($_POST['route']) ? trim($_POST['route']) : '';
$instructions = isset($_POST['instructions']) ? trim($_POST['instructions']) : '';
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

// ================================================================
// VALIDATION
// ================================================================
$errors = [];

if ($patient_id <= 0) {
    $errors[] = 'Patient is required';
}
if ($visit_id <= 0) {
    $errors[] = 'Visit is required';
}
if ($medication_id <= 0) {
    $errors[] = 'Medication is required';
}
if (empty($dosage)) {
    $errors[] = 'Dosage is required';
}
if (empty($frequency)) {
    $errors[] = 'Frequency is required';
}
if (empty($duration)) {
    $errors[] = 'Duration is required';
}
if ($quantity <= 0) {
    $errors[] = 'Quantity is required';
}
if (empty($route)) {
    $errors[] = 'Route is required';
}

// ================================================================
// IF ERRORS, REDIRECT BACK WITH ERROR MESSAGE
// ================================================================
if (!empty($errors)) {
    $error_msg = implode(' | ', $errors);
    header('Location: prescribe.php?patient_id=' . $patient_id . '&error=' . urlencode($error_msg));
    exit;
}

// ================================================================
// GET MEDICATION DETAILS
// ================================================================
$medication_name = '';
$medication_strength = '';
$medication_unit = '';
$medication_category = '';

$stmt = $db->prepare("
    SELECT name, strength, unit, category 
    FROM medications 
    WHERE id = ? AND status = 'active'
");
$stmt->execute([$medication_id]);
$med = $stmt->fetch(PDO::FETCH_ASSOC);

if ($med) {
    $medication_name = $med['name'];
    $medication_strength = $med['strength'] ?? '';
    $medication_unit = $med['unit'] ?? '';
    $medication_category = $med['category'] ?? '';
    
    // Build full medication name
    $medication_full = $medication_name;
    if (!empty($medication_strength)) {
        $medication_full .= ' ' . $medication_strength;
    }
    if (!empty($medication_unit)) {
        $medication_full .= ' ' . $medication_unit;
    }
} else {
    $error_msg = 'Medication not found or inactive';
    header('Location: prescribe.php?patient_id=' . $patient_id . '&error=' . urlencode($error_msg));
    exit;
}

// ================================================================
// GENERATE PRESCRIPTION NUMBER
// ================================================================
$year = date('Y');
$month = date('m');
$prescription_number = 'PRX-' . $year . $month . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);

// Ensure unique prescription number
$stmt = $db->prepare("SELECT COUNT(*) FROM prescriptions WHERE prescription_number = ?");
$stmt->execute([$prescription_number]);
while ($stmt->fetchColumn() > 0) {
    $prescription_number = 'PRX-' . $year . $month . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
    $stmt->execute([$prescription_number]);
}

// ================================================================
// VERIFY DOCTOR HAS ACCESS TO THIS PATIENT AND VISIT
// ================================================================
if (!$is_admin) {
    // Doctor can only prescribe for their own patients
    $stmt = $db->prepare("
        SELECT v.id FROM visits v
        WHERE v.id = ? AND v.patient_id = ? AND v.doctor_id = ?
    ");
    $stmt->execute([$visit_id, $patient_id, $doctor_id]);
    if (!$stmt->fetch()) {
        header('Location: my_patients.php?error=unauthorized');
        exit;
    }
} else {
    // Admin can prescribe for any patient
    $stmt = $db->prepare("
        SELECT v.id FROM visits v
        WHERE v.id = ? AND v.patient_id = ?
    ");
    $stmt->execute([$visit_id, $patient_id]);
    if (!$stmt->fetch()) {
        header('Location: my_patients.php?error=invalid_visit');
        exit;
    }
}

// ================================================================
// INSERT PRESCRIPTION
// ================================================================
try {
    // Start transaction
    $db->beginTransaction();

    $stmt = $db->prepare("
        INSERT INTO prescriptions (
            prescription_number,
            visit_id,
            patient_id,
            doctor_id,
            diagnosis,
            notes,
            status,
            is_indoor,
            branch_id,
            created_at,
            updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, 'pending', 1, ?, NOW(), NOW())
    ");
    
    $stmt->execute([
        $prescription_number,
        $visit_id,
        $patient_id,
        $doctor_id,
        $diagnosis,
        $notes,
        $branch_id
    ]);
    
    $prescription_id = $db->lastInsertId();
    
    // ================================================================
    // INSERT PRESCRIPTION ITEMS
    // ================================================================
    $stmt = $db->prepare("
        INSERT INTO prescription_items (
            prescription_id,
            medication_name,
            dosage,
            frequency,
            quantity,
            duration,
            route,
            instructions,
            unit_price,
            total_price,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, NOW())
    ");
    
    $stmt->execute([
        $prescription_id,
        $medication_full,
        $dosage,
        $frequency,
        $quantity,
        $duration,
        $route,
        $instructions
    ]);
    
    // ================================================================
    // UPDATE VISIT STATUS TO 'prescribed'
    // ================================================================
    $stmt = $db->prepare("
        UPDATE visits 
        SET status = 'prescribed', 
            updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$visit_id]);
    
    // ================================================================
    // UPDATE PATIENT BILL IF EXISTS
    // ================================================================
    $stmt = $db->prepare("
        SELECT id, status FROM patient_bills 
        WHERE visit_id = ? AND status IN ('pending', 'partial')
    ");
    $stmt->execute([$visit_id]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($bill) {
        // Bill exists and is pending/partial, no action needed
        // The bill items will be created when pharmacy dispenses
    }
    
    // Commit transaction
    $db->commit();
    
    // ================================================================
    // LOG ACTIVITY
    // ================================================================
    try {
        $stmt = $db->prepare("
            INSERT INTO activity_logs (user_id, branch_id, action, details, created_at) 
            VALUES (?, ?, 'prescription_created', ?, NOW())
        ");
        $stmt->execute([
            $doctor_id,
            $branch_id,
            "Prescription #$prescription_number created for patient ID: $patient_id" . 
            ($is_admin ? " (Admin)" : "") . 
            " | Medication: $medication_full"
        ]);
    } catch (Exception $e) {
        // Activity log failed - continue anyway
    }
    
    // ================================================================
    // REDIRECT TO SUCCESS
    // ================================================================
    header('Location: view_prescriptions.php?success=1&prescription=' . $prescription_number);
    exit;
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    // ================================================================
    // ERROR - REDIRECT BACK
    // ================================================================
    $error_msg = 'Database error: ' . $e->getMessage();
    header('Location: prescribe.php?patient_id=' . $patient_id . '&error=' . urlencode($error_msg));
    exit;
}
?>