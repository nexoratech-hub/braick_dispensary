<?php
// ================================================================
// FILE: frontend/pages/doctor/save_prescription.php
// DOCTOR - SAVE PRESCRIPTION
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
$branch_id = $_SESSION['branch_id'] ?? 1;

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
$medication_unit = '';
$stmt = $db->prepare("SELECT name, strength, unit FROM medications WHERE id = ?");
$stmt->execute([$medication_id]);
$med = $stmt->fetch(PDO::FETCH_ASSOC);
if ($med) {
    $medication_name = $med['name'] . ' ' . ($med['strength'] ?? '');
    $medication_unit = $med['unit'] ?? '';
}

// ================================================================
// GENERATE PRESCRIPTION NUMBER
// ================================================================
$prescription_number = 'PRX-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

// ================================================================
// INSERT PRESCRIPTION
// ================================================================
try {
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
            created_at,
            updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, 'pending', 1, NOW(), NOW())
    ");
    
    $stmt->execute([
        $prescription_number,
        $visit_id,
        $patient_id,
        $doctor_id,
        $diagnosis,
        $instructions,
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
            instructions,
            unit_price,
            total_price,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, NOW())
    ");
    
    $stmt->execute([
        $prescription_id,
        $medication_name,
        $dosage,
        $frequency,
        $quantity,
        $duration,
        $instructions,
    ]);
    
    // ================================================================
    // UPDATE VISIT STATUS TO 'prescribed'
    // ================================================================
    $stmt = $db->prepare("UPDATE visits SET status = 'prescribed', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$visit_id]);
    
    // ================================================================
    // LOG ACTIVITY
    // ================================================================
    try {
        $stmt = $db->prepare("
            INSERT INTO activity_logs (user_id, action, details, created_at) 
            VALUES (?, 'prescription_created', ?, NOW())
        ");
        $stmt->execute([
            $doctor_id,
            "Prescription #$prescription_number created for patient ID: $patient_id"
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
    // ================================================================
    // ERROR - REDIRECT BACK
    // ================================================================
    $error_msg = 'Database error: ' . $e->getMessage();
    header('Location: prescribe.php?patient_id=' . $patient_id . '&error=' . urlencode($error_msg));
    exit;
}
?>