<?php
// ================================================================
// FILE: frontend/pages/doctor/api_save_diagnosis.php
// API - SAVE DIAGNOSIS ONLY
// BRAICK DISPENSARY
// ================================================================

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// ================================================================
// CHECK IF USER IS DOCTOR OR ADMIN
// ================================================================
if ($_SESSION['role'] !== 'doctor' && $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// ================================================================
// GET DOCTOR INFO
// ================================================================
$doctor_id = $_SESSION['user_id'];
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection error: ' . $e->getMessage()]);
    exit;
}

// ================================================================
// GET POST DATA
// ================================================================
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    // Try regular POST if JSON failed
    $visit_id = isset($_POST['visit_id']) ? (int)$_POST['visit_id'] : 0;
    $diagnosis_id = isset($_POST['diagnosis_id']) ? trim($_POST['diagnosis_id']) : '';
    $diagnosis_manual = isset($_POST['diagnosis_manual']) ? trim($_POST['diagnosis_manual']) : '';
    $treatment = isset($_POST['treatment']) ? trim($_POST['treatment']) : '';
    $disease_code_manual = isset($_POST['disease_code_manual']) ? trim($_POST['disease_code_manual']) : '';
    $symptoms = isset($_POST['symptoms']) ? trim($_POST['symptoms']) : '';
    $hpi = isset($_POST['hpi']) ? trim($_POST['hpi']) : '';
    $physical_exam = isset($_POST['physical_exam']) ? trim($_POST['physical_exam']) : '';
    $complaint = isset($_POST['complaint']) ? trim($_POST['complaint']) : '';
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
} else {
    $visit_id = isset($input['visit_id']) ? (int)$input['visit_id'] : 0;
    $diagnosis_id = isset($input['diagnosis_id']) ? trim($input['diagnosis_id']) : '';
    $diagnosis_manual = isset($input['diagnosis_manual']) ? trim($input['diagnosis_manual']) : '';
    $treatment = isset($input['treatment']) ? trim($input['treatment']) : '';
    $disease_code_manual = isset($input['disease_code_manual']) ? trim($input['disease_code_manual']) : '';
    $symptoms = isset($input['symptoms']) ? trim($input['symptoms']) : '';
    $hpi = isset($input['hpi']) ? trim($input['hpi']) : '';
    $physical_exam = isset($input['physical_exam']) ? trim($input['physical_exam']) : '';
    $complaint = isset($input['complaint']) ? trim($input['complaint']) : '';
    $notes = isset($input['notes']) ? trim($input['notes']) : '';
}

// ================================================================
// VALIDATION
// ================================================================
if ($visit_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Visit ID is required']);
    exit;
}

// ================================================================
// VERIFY DOCTOR HAS ACCESS TO THIS VISIT
// ================================================================
$stmt = $db->prepare("
    SELECT v.*, 
           p.id as patient_id,
           p.patient_id as patient_code,
           p.full_name as patient_name
    FROM visits v
    JOIN patients p ON v.patient_id = p.id
    WHERE v.id = ? AND v.doctor_id = ?
");
$stmt->execute([$visit_id, $doctor_id]);
$visit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$visit) {
    // Check if admin
    if ($_SESSION['role'] === 'admin') {
        $stmt = $db->prepare("
            SELECT v.*, 
                   p.id as patient_id,
                   p.patient_id as patient_code,
                   p.full_name as patient_name
            FROM visits v
            JOIN patients p ON v.patient_id = p.id
            WHERE v.id = ?
        ");
        $stmt->execute([$visit_id]);
        $visit = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    if (!$visit) {
        echo json_encode(['success' => false, 'message' => 'Visit not found or unauthorized']);
        exit;
    }
}

$patient_id = $visit['patient_id'];

// ================================================================
// PROCESS DIAGNOSIS
// ================================================================
$disease_id = null;
$disease_code = null;
$disease_name = null;
$diagnosis_text = '';

error_log("=== API SAVE DIAGNOSIS ===");
error_log("visit_id: " . $visit_id);
error_log("diagnosis_id: " . $diagnosis_id);
error_log("diagnosis_manual: " . $diagnosis_manual);

// ================================================================
// CASE 1: Numeric disease_id from dropdown
// ================================================================
if (is_numeric($diagnosis_id) && (int)$diagnosis_id > 0) {
    $disease_id = (int)$diagnosis_id;
    error_log("✅ Numeric disease_id: " . $disease_id);
    
    $stmt = $db->prepare("SELECT id, disease_name, disease_code, treatment FROM diseases WHERE id = ? AND is_active = 1");
    $stmt->execute([$disease_id]);
    $disease = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($disease) {
        $disease_name = $disease['disease_name'];
        $disease_code = $disease['disease_code'];
        $diagnosis_text = $disease['disease_name'];
        if (empty($treatment)) {
            $treatment = $disease['treatment'] ?? '';
        }
        error_log("✅ Disease found: ID=$disease_id, Name=$disease_name, Code=$disease_code");
    } else {
        error_log("❌ Disease with ID $disease_id not found");
        $disease_id = null;
    }
}

// ================================================================
// CASE 2: Manual entry
// ================================================================
if (empty($diagnosis_text) && ($diagnosis_id === '__manual__' || !empty($diagnosis_manual))) {
    $disease_name = !empty($diagnosis_manual) ? $diagnosis_manual : '';
    $diagnosis_text = $disease_name;
    error_log("📋 Manual disease: " . $disease_name);
    
    if (!empty($disease_name)) {
        // Check if disease exists
        $stmt = $db->prepare("SELECT id, disease_code FROM diseases WHERE disease_name = ? AND (branch_id = ? OR branch_id IS NULL)");
        $stmt->execute([$disease_name, $doctor_branch_id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            $disease_id = $existing['id'];
            $disease_code = $existing['disease_code'];
            error_log("✅ Existing disease: ID=$disease_id, Code=$disease_code");
        } else {
            // Create new disease
            $disease_code = !empty($disease_code_manual) ? $disease_code_manual : '';
            if (empty($disease_code)) {
                $prefix = 'D-' . strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $disease_name), 0, 3));
                $disease_code = $prefix . '-' . rand(100, 999);
            }
            
            $stmt = $db->prepare("
                INSERT INTO diseases (
                    disease_name, disease_code, treatment, branch_id, is_active, created_at
                ) VALUES (?, ?, ?, ?, 1, NOW())
            ");
            $stmt->execute([$disease_name, $disease_code, $treatment, $doctor_branch_id]);
            $disease_id = $db->lastInsertId();
            error_log("✅ New disease created: ID=$disease_id");
        }
    }
}

// ================================================================
// CASE 3: Use existing diagnosis from visit
// ================================================================
if (empty($diagnosis_text) && !empty($visit['diagnosis'])) {
    $diagnosis_text = $visit['diagnosis'];
    $disease_id = $visit['disease_id'] ?? null;
    $disease_code = $visit['disease_code'] ?? null;
    error_log("📋 Using existing diagnosis: " . $diagnosis_text);
}

if (!empty($diagnosis_text) && empty($disease_name)) {
    $disease_name = $diagnosis_text;
}

error_log("=== FINAL DIAGNOSIS VALUES ===");
error_log("diagnosis_text: " . $diagnosis_text);
error_log("disease_id: " . ($disease_id ?? 'NULL'));
error_log("disease_code: " . ($disease_code ?? 'NULL'));
error_log("treatment: " . $treatment);

// ================================================================
// UPDATE VISIT WITH DIAGNOSIS ONLY
// ================================================================
try {
    $stmt = $db->prepare("
        UPDATE visits 
        SET 
            symptoms = ?,
            hpi = ?,
            physical_exam = ?,
            complaint = ?,
            diagnosis = ?,
            disease_id = ?,
            disease_code = ?,
            treatment = ?,
            notes = ?,
            updated_at = NOW()
        WHERE id = ? AND doctor_id = ?
    ");
    
    $stmt->execute([
        $symptoms,
        $hpi,
        $physical_exam,
        $complaint,
        $diagnosis_text,
        $disease_id,
        $disease_code,
        $treatment,
        $notes,
        $visit_id,
        $doctor_id
    ]);
    
    $rows_affected = $stmt->rowCount();
    error_log("Rows affected: " . $rows_affected);
    
    // ================================================================
    // VERIFY UPDATE
    // ================================================================
    $stmt = $db->prepare("SELECT diagnosis, disease_id, disease_code, treatment FROM visits WHERE id = ?");
    $stmt->execute([$visit_id]);
    $verify = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // ================================================================
    // RETURN SUCCESS
    // ================================================================
    echo json_encode([
        'success' => true,
        'message' => 'Diagnosis saved successfully!',
        'data' => [
            'visit_id' => $visit_id,
            'diagnosis' => $diagnosis_text,
            'disease_id' => $disease_id,
            'disease_code' => $disease_code,
            'treatment' => $treatment,
            'verified' => $verify
        ]
    ]);
    exit;
    
} catch (Exception $e) {
    error_log("API Save Diagnosis Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    exit;
}
?>