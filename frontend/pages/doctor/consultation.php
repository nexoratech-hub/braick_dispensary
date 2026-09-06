<?php
// ================================================================
// FILE: frontend/pages/doctor/consultation.php
// COMPLETE CONSULTATION - FULLY FIXED
// BRAICK DISPENSARY
// 
// ✅ Lab results → 'prescribed' ONLY
// ✅ NO auto-complete without diagnosis
// ✅ Save Consultation → only way to change to 'waiting'
// ✅ Auto-complete → only after Save Consultation + balance=0
// ================================================================

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ================================================================
// LOGIN PROTECTION
// ================================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// CHECK ROLE
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
// GET DOCTOR INFO
// ================================================================
$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['full_name'] ?? 'Doctor';
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;
$doctor_specialty = $_SESSION['specialty'] ?? 'General Medicine';
$is_admin = ($_SESSION['role'] === 'admin');

// ================================================================
// GET PARAMETERS
// ================================================================
$visit_id = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

if ($visit_id <= 0 && $patient_id <= 0) {
    header('Location: my_patients.php');
    exit;
}

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die('Database connection error: ' . $e->getMessage());
}

// ================================================================
// COMMON COMPLAINTS LIST
// ================================================================
$common_complaints = [
    'Fever', 'Headache', 'Cough', 'Sore Throat', 'Runny Nose',
    'Shortness of Breath', 'Chest Pain', 'Abdominal Pain', 'Nausea',
    'Vomiting', 'Diarrhea', 'Constipation', 'Fatigue', 'Dizziness',
    'Joint Pain', 'Muscle Ache', 'Back Pain', 'Rash', 'Itching',
    'Swelling', 'Loss of Appetite', 'Weight Loss', 'Weight Gain',
    'Night Sweats', 'Palpitations', 'Difficulty Sleeping', 'Anxiety',
    'Depression', 'Memory Loss', 'Seizures', 'Blurred Vision',
    'Fainting', 'Cough with Phlegm', 'Dry Cough', 'Loss of Smell',
    'Loss of Taste', 'Sneezing', 'Congestion', 'Weakness',
    'Confusion', 'Dehydration', 'Jaundice'
];

// ================================================================
// GET OR CREATE VISIT
// ================================================================
if ($visit_id > 0) {
    if ($is_admin) {
        $stmt = $db->prepare("
            SELECT v.*, 
                   p.id as patient_id,
                   p.patient_id as patient_code,
                   p.full_name as patient_name,
                   p.phone, p.email, p.date_of_birth, p.gender,
                   p.address, p.blood_group, p.allergies, p.emergency_contact,
                   p.created_at as patient_registered,
                   u.full_name as doctor_name,
                   u.specialty as doctor_specialty,
                   b.name as branch_name,
                   b.location as branch_location,
                   b.phone as branch_phone,
                   d.disease_name, d.disease_code, d.treatment as disease_treatment
            FROM visits v
            JOIN patients p ON v.patient_id = p.id
            LEFT JOIN users u ON v.doctor_id = u.id
            LEFT JOIN branches b ON v.branch_id = b.id
            LEFT JOIN diseases d ON v.disease_id = d.id
            WHERE v.id = ?
        ");
        $stmt->execute([$visit_id]);
    } else {
        $stmt = $db->prepare("
            SELECT v.*, 
                   p.id as patient_id,
                   p.patient_id as patient_code,
                   p.full_name as patient_name,
                   p.phone, p.email, p.date_of_birth, p.gender,
                   p.address, p.blood_group, p.allergies, p.emergency_contact,
                   p.created_at as patient_registered,
                   u.full_name as doctor_name,
                   u.specialty as doctor_specialty,
                   b.name as branch_name,
                   b.location as branch_location,
                   b.phone as branch_phone,
                   d.disease_name, d.disease_code, d.treatment as disease_treatment
            FROM visits v
            JOIN patients p ON v.patient_id = p.id
            LEFT JOIN users u ON v.doctor_id = u.id
            LEFT JOIN branches b ON v.branch_id = b.id
            LEFT JOIN diseases d ON v.disease_id = d.id
            WHERE v.id = ? AND v.doctor_id = ?
        ");
        $stmt->execute([$visit_id, $doctor_id]);
    }
    $visit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$visit) {
        header('Location: my_patients.php?error=visit_not_found');
        exit;
    }
    $patient_id = $visit['patient_id'];
} else {
    // Get existing active visit or create new
    $stmt = $db->prepare("
        SELECT v.*, 
               p.id as patient_id,
               p.patient_id as patient_code,
               p.full_name as patient_name,
               p.phone, p.email, p.date_of_birth, p.gender,
               p.address, p.blood_group, p.allergies, p.emergency_contact,
               p.created_at as patient_registered,
               u.full_name as doctor_name,
               u.specialty as doctor_specialty,
               b.name as branch_name,
               b.location as branch_location,
               b.phone as branch_phone
        FROM visits v
        JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON v.doctor_id = u.id
        LEFT JOIN branches b ON v.branch_id = b.id
        WHERE v.patient_id = ? AND v.doctor_id = ? AND v.status NOT IN ('completed', 'cancelled')
        ORDER BY v.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$patient_id, $doctor_id]);
    $visit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$visit) {
        $visit_number = 'VIS-' . date('Ymd') . '-' . str_pad($patient_id, 4, '0', STR_PAD_LEFT);
        $stmt = $db->prepare("
            INSERT INTO visits (
                visit_number, patient_id, doctor_id, branch_id, visit_type, status, created_at
            ) VALUES (?, ?, ?, ?, 'new', 'assigned', NOW())
        ");
        $stmt->execute([$visit_number, $patient_id, $doctor_id, $doctor_branch_id]);
        $visit_id = $db->lastInsertId();
        
        $stmt = $db->prepare("
            SELECT v.*, 
                   p.id as patient_id,
                   p.patient_id as patient_code,
                   p.full_name as patient_name,
                   p.phone, p.email, p.date_of_birth, p.gender,
                   p.address, p.blood_group, p.allergies, p.emergency_contact,
                   p.created_at as patient_registered,
                   u.full_name as doctor_name,
                   u.specialty as doctor_specialty,
                   b.name as branch_name,
                   b.location as branch_location,
                   b.phone as branch_phone
            FROM visits v
            JOIN patients p ON v.patient_id = p.id
            LEFT JOIN users u ON v.doctor_id = u.id
            LEFT JOIN branches b ON v.branch_id = b.id
            WHERE v.id = ?
        ");
        $stmt->execute([$visit_id]);
        $visit = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    $visit_id = $visit['id'];
}

// Get current visit status
$is_completed = ($visit['status'] === 'completed');
$is_waiting = ($visit['status'] === 'waiting');
$is_prescribed = ($visit['status'] === 'prescribed');
$is_lab_test = ($visit['status'] === 'lab_test');
$visit_status = $visit['status'] ?? 'assigned';

// ================================================================
// GET OR CREATE BILL
// ================================================================
$bill_id = null;
$bill_status = 'pending';
$bill_total = 0;
$bill_paid = 0;
$bill_balance = 0;
$bill_subtotal = 0;
$bill_discount = 0;
$bill_total_discount = 0;

try {
    $stmt = $db->prepare("
        SELECT id, status, total_amount, paid_amount, balance, subtotal, 
               discount_amount, total_discount
        FROM bills WHERE visit_id = ?
    ");
    $stmt->execute([$visit_id]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($bill) {
        $bill_id = $bill['id'];
        $bill_status = $bill['status'];
        $bill_total = $bill['total_amount'] ?? 0;
        $bill_paid = $bill['paid_amount'] ?? 0;
        $bill_balance = $bill['balance'] ?? 0;
        $bill_subtotal = $bill['subtotal'] ?? 0;
        $bill_discount = $bill['discount_amount'] ?? 0;
        $bill_total_discount = $bill['total_discount'] ?? 0;
    } else {
        $bill_number = 'BILL-' . date('Ymd') . '-' . str_pad($patient_id, 6, '0', STR_PAD_LEFT);
        $stmt = $db->prepare("
            INSERT INTO bills (
                bill_number, patient_id, visit_id, subtotal, total_amount, balance, 
                status, created_by, branch_id, created_at
            ) VALUES (?, ?, ?, 0, 0, 0, 'pending', ?, ?, NOW())
        ");
        $stmt->execute([$bill_number, $patient_id, $visit_id, $doctor_id, $doctor_branch_id]);
        $bill_id = $db->lastInsertId();
    }
} catch (Exception $e) {
    error_log("Bill error: " . $e->getMessage());
}

// ================================================================
// BILL TOTAL FUNCTION - 4 CARDS
// ================================================================
function updateBillTotal($db, $bill_id) {
    $stmt = $db->prepare("
        SELECT SUM(total_price) as total 
        FROM bill_items 
        WHERE bill_id = ? AND status != 'cancelled'
    ");
    $stmt->execute([$bill_id]);
    $subtotal = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    
    $stmt = $db->prepare("
        SELECT discount_amount, pharmacy_discount, cashier_discount, total_discount 
        FROM bills WHERE id = ?
    ");
    $stmt->execute([$bill_id]);
    $bill_discounts = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_discount = (float)($bill_discounts['total_discount'] ?? 0);
    $discount_amount = (float)($bill_discounts['discount_amount'] ?? 0);
    
    $total_amount = max(0, $subtotal - $total_discount);
    
    $stmt = $db->prepare("
        SELECT SUM(amount) as payment_total 
        FROM payments 
        WHERE bill_id = ? 
    ");
    $stmt->execute([$bill_id]);
    $paid_amount = (float)($stmt->fetch(PDO::FETCH_ASSOC)['payment_total'] ?? 0);
    
    $balance = $total_amount - $paid_amount;
    
    if ($total_amount == 0) {
        $status = 'pending';
    } elseif ($balance <= 0 && $total_amount > 0) {
        $status = 'paid';
    } elseif ($paid_amount > 0 && $balance > 0) {
        $status = 'partial';
    } elseif ($balance > 0 && $paid_amount == 0) {
        $status = 'pending';
    } else {
        $status = 'pending';
    }
    
    $stmt = $db->prepare("
        UPDATE bills 
        SET subtotal = ?, 
            total_amount = ?, 
            paid_amount = ?, 
            balance = ?, 
            status = ?,
            discount_amount = ?,
            total_discount = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([
        $subtotal, 
        $total_amount, 
        $paid_amount, 
        $balance, 
        $status,
        $discount_amount,
        $total_discount,
        $bill_id
    ]);
    
    return [
        'subtotal' => $subtotal,
        'total' => $total_amount, 
        'discount' => $total_discount,
        'discount_amount' => $discount_amount,
        'paid' => $paid_amount, 
        'balance' => $balance, 
        'status' => $status,
        'amount_after_discount' => $total_amount
    ];
}

function getBillDiscount($db, $bill_id) {
    $stmt = $db->prepare("
        SELECT discount_amount, pharmacy_discount, cashier_discount, total_discount 
        FROM bills WHERE id = ?
    ");
    $stmt->execute([$bill_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// ================================================================
// ✅ AUTO-COMPLETE - ONLY CALLED FROM SAVE CONSULTATION
// ================================================================
function canAutoCompleteVisit($db, $visit_id, $bill_id) {
    // Step 1: MUST be 'waiting'
    $stmt = $db->prepare("SELECT status FROM visits WHERE id = ?");
    $stmt->execute([$visit_id]);
    $visit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$visit || $visit['status'] !== 'waiting') {
        error_log("❌ AUTO-COMPLETE SKIPPED: Status is '" . ($visit['status'] ?? 'null') . "', must be 'waiting'");
        return false;
    }
    
    // Step 2: MUST have diagnosis filled
    $stmt = $db->prepare("SELECT diagnosis FROM visits WHERE id = ?");
    $stmt->execute([$visit_id]);
    $diagnosis = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$diagnosis || empty($diagnosis['diagnosis'])) {
        error_log("❌ AUTO-COMPLETE SKIPPED: No diagnosis recorded");
        return false;
    }
    
    // Step 3: Balance must be 0
    $stmt = $db->prepare("SELECT balance FROM bills WHERE id = ?");
    $stmt->execute([$bill_id]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$bill || $bill['balance'] > 0) {
        error_log("❌ AUTO-COMPLETE SKIPPED: Balance is " . ($bill['balance'] ?? 'null') . ", must be 0");
        return false;
    }
    
    error_log("✅ AUTO-COMPLETE READY: Visit #$visit_id is 'waiting', has diagnosis, and balance is 0");
    return true;
}

function autoCompleteVisit($db, $visit_id) {
    // Double-check before updating
    $stmt = $db->prepare("
        SELECT v.status, v.diagnosis, b.balance 
        FROM visits v 
        LEFT JOIN bills b ON v.id = b.visit_id 
        WHERE v.id = ?
    ");
    $stmt->execute([$visit_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$data || $data['status'] !== 'waiting' || empty($data['diagnosis']) || ($data['balance'] ?? 0) > 0) {
        error_log("❌ AUTO-COMPLETE FAILED: Pre-check failed");
        return false;
    }
    
    try {
        $stmt = $db->prepare("
            UPDATE visits 
            SET status = 'completed', 
                is_completed = 1,
                completed_at = NOW(),
                updated_at = NOW()
            WHERE id = ? AND status = 'waiting'
        ");
        $stmt->execute([$visit_id]);
        
        if ($stmt->rowCount() > 0) {
            error_log("✅ AUTO-COMPLETE SUCCESS: Visit #$visit_id completed");
            return true;
        }
        return false;
    } catch (Exception $e) {
        error_log("❌ AUTO-COMPLETE ERROR: " . $e->getMessage());
        return false;
    }
}

// ================================================================
// ✅ CHECK LAB RESULTS - ONLY UPDATES TO 'prescribed'
// ================================================================
function checkLabResultsAndUpdateStatus($db, $visit_id) {
    $stmt = $db->prepare("
        SELECT COUNT(*) as total, 
               SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
               SUM(CASE WHEN status IN ('pending', 'in_progress') THEN 1 ELSE 0 END) as active
        FROM lab_tests 
        WHERE visit_id = ?
    ");
    $stmt->execute([$visit_id]);
    $lab_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $total = (int)($lab_stats['total'] ?? 0);
    $completed = (int)($lab_stats['completed'] ?? 0);
    $active = (int)($lab_stats['active'] ?? 0);
    
    if ($total > 0 && $active == 0 && $completed == $total) {
        $stmt = $db->prepare("SELECT status FROM visits WHERE id = ?");
        $stmt->execute([$visit_id]);
        $visit_check = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($visit_check && $visit_check['status'] === 'lab_test') {
            $stmt = $db->prepare("
                UPDATE visits 
                SET status = 'prescribed',
                    updated_at = NOW()
                WHERE id = ? AND status = 'lab_test'
            ");
            $stmt->execute([$visit_id]);
            
            if ($stmt->rowCount() > 0) {
                error_log("✅ Visit #$visit_id updated to 'prescribed' ONLY");
                return true;
            }
        }
    }
    return false;
}

// ================================================================
// SAVE DIAGNOSIS - DATA ONLY, NO STATUS CHANGE
// ================================================================
function saveDiagnosisToDatabase($db, $visit_id, $doctor_id, $doctor_branch_id, $data) {
    $diagnosis_id = $data['diagnosis_id'] ?? '';
    $diagnosis_manual = trim($data['diagnosis_manual'] ?? '');
    $treatment = trim($data['treatment'] ?? '');
    $disease_code_manual = trim($data['disease_code_manual'] ?? '');
    $symptoms = trim($data['symptoms'] ?? '');
    $hpi = trim($data['hpi'] ?? '');
    $physical_exam = trim($data['physical_exam'] ?? '');
    $notes = trim($data['notes'] ?? '');
    
    $disease_name = '';
    $disease_code = '';
    $disease_id_val = null;
    
    if ($diagnosis_id === '__manual__' && !empty($diagnosis_manual)) {
        $disease_name = $diagnosis_manual;
        $disease_code = !empty($disease_code_manual) ? $disease_code_manual : 'D-' . strtoupper(substr(str_replace(' ', '', $diagnosis_manual), 0, 6)) . '-' . rand(100, 999);
        
        $stmt = $db->prepare("SELECT id FROM diseases WHERE disease_name = ? AND branch_id = ?");
        $stmt->execute([$disease_name, $doctor_branch_id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            $disease_id_val = $existing['id'];
        } else {
            $stmt = $db->prepare("
                INSERT INTO diseases (disease_name, disease_code, branch_id, is_active, created_at)
                VALUES (?, ?, ?, 1, NOW())
            ");
            $stmt->execute([$disease_name, $disease_code, $doctor_branch_id]);
            $disease_id_val = $db->lastInsertId();
            error_log("✅ Manual disease saved: $disease_name ($disease_code)");
        }
    } elseif ($diagnosis_id > 0) {
        $stmt = $db->prepare("SELECT id, disease_name, disease_code FROM diseases WHERE id = ? AND is_active = 1");
        $stmt->execute([$diagnosis_id]);
        $disease = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($disease) {
            $disease_id_val = $disease['id'];
            $disease_name = $disease['disease_name'];
            $disease_code = $disease['disease_code'] ?? 'D-' . strtoupper(substr(str_replace(' ', '', $disease_name), 0, 6)) . '-' . rand(100, 999);
        }
    }
    
    $stmt = $db->prepare("
        UPDATE visits 
        SET disease_id = ?,
            diagnosis = ?,
            disease_code = ?,
            treatment = ?,
            symptoms = ?,
            hpi = ?,
            physical_exam = ?,
            notes = ?,
            updated_at = NOW()
        WHERE id = ? AND doctor_id = ?
    ");
    $stmt->execute([
        $disease_id_val,
        $disease_name ?: null,
        $disease_code ?: null,
        $treatment ?: null,
        $symptoms ?: null,
        $hpi ?: null,
        $physical_exam ?: null,
        $notes ?: null,
        $visit_id,
        $doctor_id
    ]);
    
    return [
        'success' => true,
        'disease_id' => $disease_id_val,
        'disease_name' => $disease_name,
        'disease_code' => $disease_code,
        'treatment' => $treatment
    ];
}

// ================================================================
// GET DATA
// ================================================================

// Diseases
$diseases_list = [];
try {
    $stmt = $db->prepare("
        SELECT id, disease_name, disease_code, category, description, treatment 
        FROM diseases 
        WHERE is_active = 1 
        AND (branch_id IS NULL OR branch_id = ?)
        ORDER BY disease_name
    ");
    $stmt->execute([$doctor_branch_id]);
    $diseases_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { 
    $diseases_list = []; 
}

// Lab Tests Catalog
$lab_tests_catalog = [];
try {
    $stmt = $db->prepare("
        SELECT lc.*, 
               le.equipment_id,
               me.equipment_name as linked_equipment_name,
               me.quantity as equipment_stock
        FROM lab_tests_catalog lc
        LEFT JOIN lab_test_equipment le ON lc.id = le.lab_test_id
        LEFT JOIN medical_equipment me ON le.equipment_id = me.id
        WHERE lc.is_active = 1 
        AND (lc.branch_id IS NULL OR lc.branch_id = ?)
        ORDER BY lc.category, lc.test_name
    ");
    $stmt->execute([$doctor_branch_id]);
    $lab_tests_catalog = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { 
    $lab_tests_catalog = []; 
}

// Medications - GROUPED
$medications_grouped = [];
try {
    $stmt = $db->prepare("
        SELECT id, medication_name, category, unit, selling_price, quantity, 
               batch_number, expiry_date
        FROM medications_inventory 
        WHERE status = 'active' 
        AND quantity > 0 
        AND branch_id = ?
        AND (expiry_date IS NULL OR expiry_date > CURDATE())
        ORDER BY medication_name ASC, category ASC
    ");
    $stmt->execute([$doctor_branch_id]);
    $medications_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($medications_list as $med) {
        $key = $med['medication_name'] . '|' . $med['category'];
        if (!isset($medications_grouped[$key])) {
            $medications_grouped[$key] = [
                'name' => $med['medication_name'],
                'category' => $med['category'],
                'unit' => $med['unit'],
                'selling_price' => $med['selling_price'],
                'total_quantity' => 0,
                'batches' => []
            ];
        }
        $medications_grouped[$key]['total_quantity'] += $med['quantity'];
        $medications_grouped[$key]['batches'][] = [
            'id' => $med['id'],
            'quantity' => $med['quantity'],
            'batch_number' => $med['batch_number'],
            'expiry_date' => $med['expiry_date'],
            'selling_price' => $med['selling_price']
        ];
    }
} catch (Exception $e) { 
    $medications_grouped = []; 
}

// Procedures
$procedures_list = [];
try {
    $stmt = $db->prepare("
        SELECT pc.id, pc.procedure_name, pc.procedure_code, pc.category, pc.price, 
               pc.description
        FROM procedures_catalog pc
        WHERE pc.is_active = 1 
        AND (pc.branch_id IS NULL OR pc.branch_id = ?)
        ORDER BY pc.procedure_name
    ");
    $stmt->execute([$doctor_branch_id]);
    $procedures_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { 
    $procedures_list = []; 
}

// Medical Equipment
$equipment_list = [];
try {
    $stmt = $db->prepare("
        SELECT id, equipment_name, category, quantity, unit, selling_price,
               batch_number, expiry_date
        FROM medical_equipment 
        WHERE status = 'active' 
        AND quantity > 0
        AND branch_id = ?
        AND (expiry_date IS NULL OR expiry_date > CURDATE())
        ORDER BY equipment_name
    ");
    $stmt->execute([$doctor_branch_id]);
    $equipment_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { 
    $equipment_list = []; 
}

// Vital Signs
$vital_signs = null;
if ($visit_id > 0) {
    $stmt = $db->prepare("
        SELECT 
            temperature,
            blood_pressure_systolic,
            blood_pressure_diastolic,
            pulse_rate,
            weight,
            height,
            bmi,
            notes,
            recorded_at,
            u.full_name as recorded_by_name
        FROM vital_signs vs
        LEFT JOIN users u ON vs.recorded_by = u.id
        WHERE vs.visit_id = ? 
        ORDER BY vs.recorded_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$visit_id]);
    $vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Lab Tests
$lab_requests = [];
$lab_results = [];
$lab_results_available = false;
$has_active_lab = false;

try {
    $stmt = $db->prepare("
        SELECT lt.*,
               le.equipment_id,
               me.equipment_name as linked_equipment_name
        FROM lab_tests lt
        LEFT JOIN lab_test_equipment le ON lt.test_id = le.lab_test_id
        LEFT JOIN medical_equipment me ON le.equipment_id = me.id
        WHERE lt.visit_id = ? AND lt.status IN ('pending', 'in_progress')
        ORDER BY lt.created_at DESC
    ");
    $stmt->execute([$visit_id]);
    $lab_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $has_active_lab = count($lab_requests) > 0;
    
    $stmt = $db->prepare("
        SELECT lt.*,
               le.equipment_id,
               me.equipment_name as linked_equipment_name
        FROM lab_tests lt
        LEFT JOIN lab_test_equipment le ON lt.test_id = le.lab_test_id
        LEFT JOIN medical_equipment me ON le.equipment_id = me.id
        WHERE lt.visit_id = ? AND lt.status = 'completed'
        ORDER BY lt.completed_at DESC
    ");
    $stmt->execute([$visit_id]);
    $lab_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $lab_results_available = count($lab_results) > 0;
} catch (Exception $e) {
    error_log("Lab fetch error: " . $e->getMessage());
}

$sections_frozen = ($has_active_lab && !$lab_results_available && !$is_completed && !$is_waiting);

// Prescriptions
$prescriptions = [];
$medications_total = 0;

try {
    $stmt = $db->prepare("
        SELECT p.*, 
               pi.id as item_id, pi.medication_name, pi.dosage, pi.frequency, 
               pi.quantity, pi.duration, pi.route, pi.instructions,
               pi.unit_price, pi.total_price,
               pi.dispensed_at, pi.dispensed_by,
               pi.inventory_id
        FROM prescriptions p
        LEFT JOIN prescription_items pi ON p.id = pi.prescription_id
        WHERE p.visit_id = ?
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$visit_id]);
    $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($prescriptions as $presc) {
        $medications_total += $presc['total_price'] ?? 0;
    }
} catch (Exception $e) { 
    $prescriptions = []; 
}

// Procedures
$procedures = [];
$procedure_total = 0;
try {
    $stmt = $db->prepare("
        SELECT p.*
        FROM procedures p
        WHERE p.visit_id = ? AND p.status != 'cancelled'
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$visit_id]);
    $procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($procedures as $proc) {
        $procedure_total += $proc['procedure_price'] ?? 0;
    }
} catch (Exception $e) { 
    $procedures = []; 
}

// ================================================================
// GET BILL ITEMS - 4 CARDS
// ================================================================
$bill_items = [];
$lab_total = 0;
$medication_total = 0;
$procedure_total_bill = 0;
$equipment_total = 0;
$total_bill_amount = 0;
$paid_total = 0;
$pending_total = 0;
$total_discount = 0;

try {
    $stmt = $db->prepare("
        SELECT id, item_name, item_type, quantity, unit_price, total_price, 
               status 
        FROM bill_items 
        WHERE bill_id = ? AND status != 'cancelled'
        ORDER BY created_at DESC
    ");
    $stmt->execute([$bill_id]);
    $bill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($bill_items as $item) {
        $total_bill_amount += $item['total_price'];
        
        if ($item['status'] === 'paid') {
            $paid_total += $item['total_price'];
        } else {
            $pending_total += $item['total_price'];
        }
        
        switch ($item['item_type']) {
            case 'lab_test': $lab_total += $item['total_price']; break;
            case 'medication': $medication_total += $item['total_price']; break;
            case 'procedure': $procedure_total_bill += $item['total_price']; break;
            case 'equipment': $equipment_total += $item['total_price']; break;
        }
    }
    
    $discount_data = getBillDiscount($db, $bill_id);
    $total_discount = (float)($discount_data['total_discount'] ?? 0);
} catch (Exception $e) { 
    $bill_items = [];
    $total_discount = 0;
}

$amount_after_discount = max(0, $total_bill_amount - $total_discount);

$bill_data = updateBillTotal($db, $bill_id);
$bill_total = $bill_data['total'];
$bill_paid = $bill_data['paid'];
$bill_balance = $bill_data['balance'];
$bill_status = $bill_data['status'];
$bill_subtotal = $bill_data['subtotal'];
$total_discount = $bill_data['discount'];
$amount_after_discount = $bill_data['amount_after_discount'];

// Equipment items from bill
$equipment_items_display = [];
try {
    $stmt = $db->prepare("
        SELECT id, item_name, quantity, unit_price, total_price, status 
        FROM bill_items 
        WHERE bill_id = ? AND item_type = 'equipment' AND status != 'cancelled'
    ");
    $stmt->execute([$bill_id]);
    $equipment_items_display = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { 
    $equipment_items_display = []; 
}

// Branch info
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

// Helper functions
function calculateAge($dob) {
    if (empty($dob) || $dob === '0000-00-00') return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}

function getUserColor($name) {
    $colors = ['#0B5ED7', '#059669', '#7C3AED', '#DC2626', '#D97706', '#0D9488', '#DB2777', '#2563EB', '#0891B2'];
    $index = abs(crc32($name)) % count($colors);
    return $colors[$index];
}

function getStatusBadgeClass($status) {
    $map = [
        'assigned' => 'badge-info',
        'pending' => 'badge-warning',
        'with_doctor' => 'badge-info',
        'lab_test' => 'badge-warning',
        'in_progress' => 'badge-info',
        'prescribed' => 'badge-purple',
        'waiting' => 'badge-purple',
        'completed' => 'badge-success',
        'cancelled' => 'badge-danger'
    ];
    return $map[$status] ?? 'badge-info';
}

// ================================================================
// HANDLE FORM SUBMISSIONS
// ================================================================
$flash_message = isset($_SESSION['flash_message']) ? $_SESSION['flash_message'] : '';
$flash_type = isset($_SESSION['flash_type']) ? $_SESSION['flash_type'] : '';
unset($_SESSION['flash_message']);
unset($_SESSION['flash_type']);

$auto_refresh_needed = isset($_SESSION['auto_refresh_needed']) ? $_SESSION['auto_refresh_needed'] : false;
unset($_SESSION['auto_refresh_needed']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_completed) {
    $action = $_POST['action'] ?? '';
    
    // ================================================================
    // AJAX: GET VISIT STATUS
    // ================================================================
    if ($action === 'get_visit_status') {
        header('Content-Type: application/json');
        $visit_id_input = (int)($_POST['visit_id'] ?? 0);
        $response = ['success' => false, 'status' => 'unknown'];
        
        if ($visit_id_input > 0) {
            $stmt = $db->prepare("SELECT status FROM visits WHERE id = ?");
            $stmt->execute([$visit_id_input]);
            $visit = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($visit) {
                $response['success'] = true;
                $response['status'] = $visit['status'];
            }
        }
        echo json_encode($response);
        exit;
    }
    
    // ================================================================
    // ✅ AJAX: SAVE DIAGNOSIS - DATA ONLY, NO STATUS CHANGE
    // ================================================================
    if ($action === 'save_diagnosis') {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true);
        $visit_id_input = (int)($input['visit_id'] ?? 0);
        $response = ['success' => false, 'message' => ''];
        
        if ($visit_id_input <= 0) {
            $response['message'] = 'Invalid visit ID';
            echo json_encode($response);
            exit;
        }
        
        try {
            // ✅ SAVE DATA ONLY - NO STATUS CHANGE!
            $result = saveDiagnosisToDatabase($db, $visit_id_input, $doctor_id, $doctor_branch_id, $input);
            $response['success'] = true;
            $response['message'] = '✅ Data saved successfully';
            $response['data'] = [
                'diagnosis' => $result['disease_name'],
                'disease_code' => $result['disease_code'],
                'treatment' => $result['treatment'],
                'disease_id' => $result['disease_id']
            ];
            // ❌ DO NOT CHANGE STATUS HERE!
        } catch (Exception $e) {
            $response['message'] = '❌ Error: ' . $e->getMessage();
        }
        
        echo json_encode($response);
        exit;
    }
    
    // ================================================================
    // AJAX: GET BILL TOTALS - 4 CARDS
    // ================================================================
    if ($action === 'get_bill_totals') {
        header('Content-Type: application/json');
        
        $lab_total = 0;
        $medication_total = 0;
        $procedure_total_bill = 0;
        $equipment_total = 0;
        $total_bill_amount = 0;
        $paid_total = 0;
        $pending_total = 0;
        
        $stmt = $db->prepare("
            SELECT id, item_name, item_type, quantity, unit_price, total_price, status 
            FROM bill_items 
            WHERE bill_id = ? AND status != 'cancelled'
        ");
        $stmt->execute([$bill_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($items as $item) {
            $total_bill_amount += $item['total_price'];
            if ($item['status'] === 'paid') {
                $paid_total += $item['total_price'];
            } else {
                $pending_total += $item['total_price'];
            }
            switch ($item['item_type']) {
                case 'lab_test': $lab_total += $item['total_price']; break;
                case 'medication': $medication_total += $item['total_price']; break;
                case 'procedure': $procedure_total_bill += $item['total_price']; break;
                case 'equipment': $equipment_total += $item['total_price']; break;
            }
        }
        
        $discount_data = getBillDiscount($db, $bill_id);
        $total_discount = (float)($discount_data['total_discount'] ?? 0);
        $amount_after_discount = max(0, $total_bill_amount - $total_discount);
        
        $stmt = $db->prepare("SELECT SUM(amount) as payment_total FROM payments WHERE bill_id = ?");
        $stmt->execute([$bill_id]);
        $payment_total = (float)($stmt->fetch(PDO::FETCH_ASSOC)['payment_total'] ?? 0);
        
        $stmt = $db->prepare("
            SELECT status, paid_amount, balance, subtotal, total_amount 
            FROM bills WHERE id = ?
        ");
        $stmt->execute([$bill_id]);
        $bill = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'lab_total' => $lab_total,
            'medication_total' => $medication_total,
            'procedure_total' => $procedure_total_bill,
            'equipment_total' => $equipment_total,
            'subtotal' => $total_bill_amount,
            'discount' => $total_discount,
            'amount_after_discount' => $amount_after_discount,
            'grand_total' => $amount_after_discount,
            'paid_total' => $payment_total,
            'pending_total' => $amount_after_discount - $payment_total,
            'bill_status' => $bill['status'] ?? 'pending',
            'bill_paid' => $payment_total,
            'bill_balance' => $amount_after_discount - $payment_total,
            'bill_subtotal' => $bill['subtotal'] ?? 0,
            'timestamp' => date('H:i:s')
        ]);
        exit;
    }
    
    // ================================================================
    // AJAX: GET FULL STATE - NO AUTO-COMPLETE
    // ================================================================
    if ($action === 'get_full_state') {
        header('Content-Type: application/json');
        
        $lab_requests = [];
        $lab_results = [];
        $lab_results_available = false;
        $has_active_lab = false;
        
        $stmt = $db->prepare("
            SELECT lt.*, p.full_name as patient_name
            FROM lab_tests lt
            JOIN visits v ON lt.visit_id = v.id
            JOIN patients p ON v.patient_id = p.id
            WHERE lt.visit_id = ? AND lt.status IN ('pending', 'in_progress')
            ORDER BY lt.created_at DESC
        ");
        $stmt->execute([$visit_id]);
        $lab_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $has_active_lab = count($lab_requests) > 0;
        
        $stmt = $db->prepare("
            SELECT lt.*, p.full_name as patient_name
            FROM lab_tests lt
            JOIN visits v ON lt.visit_id = v.id
            JOIN patients p ON v.patient_id = p.id
            WHERE lt.visit_id = ? AND lt.status = 'completed'
            ORDER BY lt.completed_at DESC
        ");
        $stmt->execute([$visit_id]);
        $lab_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $lab_results_available = count($lab_results) > 0;
        
        $pending_count = 0;
        $in_progress_count = 0;
        foreach ($lab_requests as $req) {
            if ($req['status'] === 'pending') $pending_count++;
            if ($req['status'] === 'in_progress') $in_progress_count++;
        }
        
        $prescriptions = [];
        $medications_total = 0;
        $stmt = $db->prepare("
            SELECT p.*, 
                   pi.id as item_id, pi.medication_name, pi.dosage, pi.frequency, 
                   pi.quantity, pi.duration, pi.route, pi.instructions,
                   pi.unit_price, pi.total_price,
                   pi.dispensed_at, pi.dispensed_by,
                   pi.inventory_id
            FROM prescriptions p
            LEFT JOIN prescription_items pi ON p.id = pi.prescription_id
            WHERE p.visit_id = ?
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([$visit_id]);
        $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($prescriptions as $presc) {
            $medications_total += $presc['total_price'] ?? 0;
        }
        
        $procedures = [];
        $procedure_total = 0;
        $stmt = $db->prepare("
            SELECT p.*
            FROM procedures p
            WHERE p.visit_id = ? AND p.status != 'cancelled'
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([$visit_id]);
        $procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($procedures as $proc) {
            $procedure_total += $proc['procedure_price'] ?? 0;
        }
        
        $bill_items = [];
        $lab_total = 0;
        $medication_total = 0;
        $procedure_total_bill = 0;
        $equipment_total = 0;
        $total_bill_amount = 0;
        $paid_total = 0;
        $pending_total = 0;
        
        $stmt = $db->prepare("
            SELECT id, item_name, item_type, quantity, unit_price, total_price, status 
            FROM bill_items 
            WHERE bill_id = ? AND status != 'cancelled'
        ");
        $stmt->execute([$bill_id]);
        $bill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($bill_items as $item) {
            $total_bill_amount += $item['total_price'];
            if ($item['status'] === 'paid') {
                $paid_total += $item['total_price'];
            } else {
                $pending_total += $item['total_price'];
            }
            switch ($item['item_type']) {
                case 'lab_test': $lab_total += $item['total_price']; break;
                case 'medication': $medication_total += $item['total_price']; break;
                case 'procedure': $procedure_total_bill += $item['total_price']; break;
                case 'equipment': $equipment_total += $item['total_price']; break;
            }
        }
        
        $discount_data = getBillDiscount($db, $bill_id);
        $total_discount = (float)($discount_data['total_discount'] ?? 0);
        $amount_after_discount = max(0, $total_bill_amount - $total_discount);
        
        $stmt = $db->prepare("SELECT SUM(amount) as payment_total FROM payments WHERE bill_id = ?");
        $stmt->execute([$bill_id]);
        $payment_total = (float)($stmt->fetch(PDO::FETCH_ASSOC)['payment_total'] ?? 0);
        
        $stmt = $db->prepare("
            SELECT status, paid_amount, balance, subtotal, total_amount 
            FROM bills WHERE id = ?
        ");
        $stmt->execute([$bill_id]);
        $bill = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $bill_data = [
            'subtotal' => $total_bill_amount,
            'discount' => $total_discount,
            'amount_after_discount' => $amount_after_discount,
            'total' => $amount_after_discount,
            'paid' => $payment_total,
            'pending' => $amount_after_discount - $payment_total,
            'balance' => $amount_after_discount - $payment_total,
            'status' => $bill['status'] ?? 'pending'
        ];
        
        // ✅ NO AUTO-COMPLETE HERE! Only return data for UI update.
        
        $added_procedures = [];
        try {
            $stmt = $db->prepare("
                SELECT p.*
                FROM procedures p
                WHERE p.visit_id = ? AND p.status != 'cancelled'
                ORDER BY p.created_at DESC
            ");
            $stmt->execute([$visit_id]);
            $added_procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) { 
            $added_procedures = []; 
        }
        
        $added_equipment = [];
        $stmt = $db->prepare("
            SELECT bi.* 
            FROM bill_items bi 
            WHERE bi.bill_id = ? AND bi.item_type = 'equipment' AND bi.status != 'cancelled'
        ");
        $stmt->execute([$bill_id]);
        $added_equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'lab' => [
                'pending_count' => $pending_count,
                'in_progress_count' => $in_progress_count,
                'results' => $lab_results,
                'requests' => $lab_requests,
                'available' => $lab_results_available,
                'has_active' => $has_active_lab,
                'frozen' => $sections_frozen
            ],
            'prescriptions' => $prescriptions,
            'medications_total' => $medications_total,
            'procedures' => $procedures,
            'procedure_total' => $procedure_total,
            'bill' => $bill_data,
            'lab_total' => $lab_total,
            'medication_total' => $medication_total,
            'procedure_total_bill' => $procedure_total_bill,
            'equipment_total' => $equipment_total,
            'added_procedures' => $added_procedures,
            'added_equipment' => $added_equipment,
            'timestamp' => date('H:i:s')
        ]);
        exit;
    }
    
    // ================================================================
    // AJAX: GET LAB STATUS
    // ================================================================
    if ($action === 'get_lab_status') {
        header('Content-Type: application/json');
        
        $stmt = $db->prepare("
            SELECT COUNT(*) as count, status 
            FROM lab_tests 
            WHERE visit_id = ? 
            GROUP BY status
        ");
        $stmt->execute([$visit_id]);
        $status_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $pending = 0;
        $in_progress = 0;
        $completed = 0;
        
        foreach ($status_counts as $sc) {
            $stat = $sc['status'] ?? 'pending';
            if ($stat === 'pending' || $stat === null) {
                $pending = (int)$sc['count'];
            } elseif ($stat === 'in_progress') {
                $in_progress = (int)$sc['count'];
            } elseif ($stat === 'completed') {
                $completed = (int)$sc['count'];
            }
        }
        
        $has_active = ($pending > 0 || $in_progress > 0);
        
        echo json_encode([
            'success' => true,
            'pending' => $pending,
            'in_progress' => $in_progress,
            'completed' => $completed,
            'has_active' => $has_active,
            'timestamp' => date('H:i:s')
        ]);
        exit;
    }
    
    // ================================================================
    // ✅ CHECK LAB RESULTS - ONLY UPDATES TO 'prescribed'
    // ================================================================
    if ($action === 'check_lab_results') {
        header('Content-Type: application/json');
        
        $visit_id_input = (int)($_POST['visit_id'] ?? 0);
        $response = ['success' => false, 'message' => '', 'status_updated' => false];
        
        if ($visit_id_input <= 0) {
            $response['message'] = 'Invalid visit ID';
            echo json_encode($response);
            exit;
        }
        
        try {
            $updated = checkLabResultsAndUpdateStatus($db, $visit_id_input);
            
            if ($updated) {
                $response['success'] = true;
                $response['status_updated'] = true;
                $response['new_status'] = 'prescribed';
                $response['message'] = '✅ Lab results completed! Status updated to PRESCRIBED.';
            } else {
                $stmt = $db->prepare("SELECT status FROM visits WHERE id = ?");
                $stmt->execute([$visit_id_input]);
                $visit_check = $stmt->fetch(PDO::FETCH_ASSOC);
                $response['success'] = true;
                $response['status_updated'] = false;
                $response['current_status'] = $visit_check['status'] ?? 'unknown';
                $response['message'] = 'Lab results check: Status is ' . ($visit_check['status'] ?? 'unknown');
            }
        } catch (Exception $e) {
            $response['message'] = '❌ Error: ' . $e->getMessage();
        }
        
        echo json_encode($response);
        exit;
    }
    
    // ================================================================
    // AJAX: ADD LAB TEST TO CART
    // ================================================================
    if ($action === 'add_lab_test_cart') {
        header('Content-Type: application/json');
        
        $test_id = (int)($_POST['test_id'] ?? 0);
        $response = ['success' => false, 'message' => ''];
        
        if ($test_id > 0) {
            try {
                $stmt = $db->prepare("
                    SELECT lc.*,
                           le.equipment_id,
                           me.equipment_name as linked_equipment_name,
                           me.quantity as equipment_stock
                    FROM lab_tests_catalog lc
                    LEFT JOIN lab_test_equipment le ON lc.id = le.lab_test_id
                    LEFT JOIN medical_equipment me ON le.equipment_id = me.id
                    WHERE lc.id = ? AND lc.is_active = 1
                    AND (lc.branch_id IS NULL OR lc.branch_id = ?)
                ");
                $stmt->execute([$test_id, $doctor_branch_id]);
                $test = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($test) {
                    if (!isset($_SESSION['lab_cart'])) {
                        $_SESSION['lab_cart'] = [];
                    }
                    
                    $exists = false;
                    foreach ($_SESSION['lab_cart'] as $item) {
                        if ($item['id'] == $test_id) {
                            $exists = true;
                            break;
                        }
                    }
                    
                    if (!$exists) {
                        $_SESSION['lab_cart'][] = [
                            'id' => $test_id,
                            'name' => $test['test_name'],
                            'price' => $test['price'],
                            'required_equipment_id' => $test['equipment_id'] ?? null,
                            'linked_equipment_name' => $test['linked_equipment_name'] ?? null,
                            'equipment_quantity_used' => $test['equipment_quantity_used'] ?? 1
                        ];
                        
                        $response['success'] = true;
                        $response['message'] = '✅ ' . $test['test_name'] . ' added to cart!';
                        if ($test['equipment_id']) {
                            $response['message'] .= ' (Linked equipment: ' . $test['linked_equipment_name'] . ' - FREE)';
                        }
                        $response['cart_count'] = count($_SESSION['lab_cart']);
                        $response['cart_total'] = array_sum(array_column($_SESSION['lab_cart'], 'price'));
                        $response['cart_items'] = $_SESSION['lab_cart'];
                    } else {
                        $response['message'] = '⚠️ Test already in cart';
                    }
                } else {
                    $response['message'] = '❌ Test not found';
                }
            } catch (Exception $e) {
                $response['message'] = '❌ Error: ' . $e->getMessage();
            }
        } else {
            $response['message'] = '❌ Please select a test';
        }
        
        echo json_encode($response);
        exit;
    }
    
    // ================================================================
    // AJAX: REMOVE LAB TEST FROM CART
    // ================================================================
    if ($action === 'remove_lab_test_cart') {
        header('Content-Type: application/json');
        $test_id = (int)($_POST['test_id'] ?? 0);
        $response = ['success' => false, 'message' => ''];
        
        if ($test_id > 0 && isset($_SESSION['lab_cart'])) {
            foreach ($_SESSION['lab_cart'] as $key => $item) {
                if ($item['id'] == $test_id) {
                    unset($_SESSION['lab_cart'][$key]);
                    break;
                }
            }
            $_SESSION['lab_cart'] = array_values($_SESSION['lab_cart']);
            
            $response['success'] = true;
            $response['message'] = '✅ Test removed from cart';
            $response['cart_count'] = count($_SESSION['lab_cart']);
            $response['cart_total'] = array_sum(array_column($_SESSION['lab_cart'], 'price'));
            $response['cart_items'] = $_SESSION['lab_cart'];
        } else {
            $response['message'] = '❌ Test not in cart';
        }
        
        echo json_encode($response);
        exit;
    }
    
    // ================================================================
    // AJAX: REMOVE LAB TEST
    // ================================================================
    if ($action === 'remove_lab_test') {
        header('Content-Type: application/json');
        $test_id = (int)($_POST['test_id'] ?? 0);
        $response = ['success' => false, 'message' => ''];
        
        if ($test_id > 0) {
            $stmt = $db->prepare("
                SELECT lt.id, lt.test_id, lt.test_price
                FROM lab_tests lt
                WHERE lt.id = ? AND lt.visit_id = ? AND lt.status IN ('pending', 'in_progress')
            ");
            $stmt->execute([$test_id, $visit_id]);
            $test = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($test) {
                $stmt = $db->prepare("
                    DELETE FROM bill_items 
                    WHERE bill_id = ? AND item_type = 'lab_test' AND reference_id = ?
                ");
                $stmt->execute([$bill_id, $test_id]);
                
                $stmt = $db->prepare("DELETE FROM lab_tests WHERE id = ? AND visit_id = ?");
                $stmt->execute([$test_id, $visit_id]);
                
                $bill_data = updateBillTotal($db, $bill_id);
                
                $response['success'] = true;
                $response['message'] = '✅ Lab test removed!';
                $response['bill_data'] = $bill_data;
            } else {
                $response['message'] = '❌ Test not found or already processed';
            }
        } else {
            $response['message'] = '❌ Invalid test ID';
        }
        
        echo json_encode($response);
        exit;
    }
    
    // ================================================================
    // AJAX: ADD MEDICATION
    // ================================================================
    if ($action === 'add_medication') {
        header('Content-Type: application/json');
        
        if ($sections_frozen && !$is_waiting) {
            echo json_encode(['success' => false, 'message' => '❌ Cannot add medications. Lab tests pending!']);
            exit;
        }
        
        $diagnosis_saved = false;
        $diagnosis_data = [];
        
        $diagnosis_id = $_POST['diagnosis_id'] ?? '';
        $diagnosis_manual = trim($_POST['diagnosis_manual'] ?? '');
        $treatment = trim($_POST['treatment'] ?? '');
        $disease_code_manual = trim($_POST['disease_code_manual'] ?? '');
        $symptoms = trim($_POST['symptoms'] ?? '');
        $hpi = trim($_POST['hpi'] ?? '');
        $physical_exam = trim($_POST['physical_exam'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        
        if (!empty($diagnosis_id) || !empty($diagnosis_manual)) {
            try {
                $result = saveDiagnosisToDatabase($db, $visit_id, $doctor_id, $doctor_branch_id, [
                    'diagnosis_id' => $diagnosis_id,
                    'diagnosis_manual' => $diagnosis_manual,
                    'treatment' => $treatment,
                    'disease_code_manual' => $disease_code_manual,
                    'symptoms' => $symptoms,
                    'hpi' => $hpi,
                    'physical_exam' => $physical_exam,
                    'notes' => $notes
                ]);
                $diagnosis_saved = true;
                $diagnosis_data = $result;
            } catch (Exception $e) {
                error_log("Failed to save diagnosis: " . $e->getMessage());
            }
        }
        
        $inventory_id = (int)($_POST['inventory_id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 1);
        $dosage = trim($_POST['dosage'] ?? '');
        $frequency = trim($_POST['frequency'] ?? '');
        $duration = trim($_POST['duration'] ?? '');
        $route = trim($_POST['route'] ?? '');
        $instructions = trim($_POST['instructions'] ?? '');
        
        $response = ['success' => false, 'message' => ''];
        
        if ($inventory_id > 0 && $quantity > 0) {
            try {
                $stmt = $db->prepare("
                    SELECT id, medication_name, selling_price, unit, quantity as stock, 
                           batch_number, expiry_date, category
                    FROM medications_inventory 
                    WHERE id = ? AND status = 'active' AND branch_id = ?
                ");
                $stmt->execute([$inventory_id, $doctor_branch_id]);
                $med = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($med) {
                    if (!empty($med['expiry_date'])) {
                        $expiry = strtotime($med['expiry_date']);
                        if ($expiry < time()) {
                            $response['message'] = '❌ This medication has EXPIRED!';
                            echo json_encode($response);
                            exit;
                        }
                    }
                    
                    if ($med['stock'] < $quantity) {
                        $stmt = $db->prepare("
                            SELECT SUM(quantity) as total_stock
                            FROM medications_inventory 
                            WHERE medication_name = ? AND category = ? 
                            AND status = 'active' AND branch_id = ?
                            AND (expiry_date IS NULL OR expiry_date > CURDATE())
                        ");
                        $stmt->execute([$med['medication_name'], $med['category'], $doctor_branch_id]);
                        $total_stock = $stmt->fetch(PDO::FETCH_ASSOC)['total_stock'] ?? 0;
                        
                        if ($total_stock < $quantity) {
                            $response['message'] = '❌ Insufficient stock! Available: ' . $total_stock;
                            echo json_encode($response);
                            exit;
                        }
                    }
                    
                    $db->beginTransaction();
                    
                    $prescription_number = 'PRES-' . date('Ymd') . '-' . str_pad($patient_id, 4, '0', STR_PAD_LEFT) . '-' . rand(100, 999);
                    
                    $stmt = $db->prepare("
                        INSERT INTO prescriptions (
                            prescription_number, visit_id, patient_id, doctor_id, 
                            status, branch_id, created_at
                        ) VALUES (?, ?, ?, ?, 'pending', ?, NOW())
                    ");
                    $stmt->execute([
                        $prescription_number, $visit_id, $patient_id, $doctor_id,
                        $doctor_branch_id
                    ]);
                    $prescription_id = $db->lastInsertId();
                    
                    $unit_price = $med['selling_price'];
                    $total_price = $unit_price * $quantity;
                    $new_stock = $med['stock'] - $quantity;
                    
                    $stmt = $db->prepare("UPDATE medications_inventory SET quantity = ? WHERE id = ?");
                    $stmt->execute([$new_stock, $inventory_id]);
                    
                    $stmt = $db->prepare("
                        INSERT INTO prescription_items (
                            prescription_id, patient_id, inventory_id, medication_name, 
                            dosage, frequency, quantity, duration, route, instructions, 
                            unit_price, total_price, branch_id, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $prescription_id, $patient_id, $inventory_id,
                        $med['medication_name'], $dosage, $frequency, $quantity, 
                        $duration, $route, $instructions,
                        $unit_price, $total_price, $doctor_branch_id
                    ]);
                    
                    $stmt = $db->prepare("
                        INSERT INTO bill_items (
                            bill_id, patient_id, branch_id, item_type, item_name,
                            quantity, unit_price, total_price, status, 
                            reference_id, reference_type, created_at
                        ) VALUES (?, ?, ?, 'medication', ?, ?, ?, ?, 'pending', ?, 'prescription', NOW())
                    ");
                    $stmt->execute([
                        $bill_id, $patient_id, $doctor_branch_id,
                        $med['medication_name'] . ' (Batch: ' . ($med['batch_number'] ?? 'N/A') . ')',
                        $quantity, $unit_price, $total_price,
                        $prescription_id
                    ]);
                    
                    $db->commit();
                    $bill_data = updateBillTotal($db, $bill_id);
                    
                    $response['success'] = true;
                    $response['message'] = '✅ Medication added! Remaining: ' . $new_stock;
                    $response['prescription_id'] = $prescription_id;
                    $response['medication'] = [
                        'id' => $prescription_id,
                        'name' => $med['medication_name'],
                        'dosage' => $dosage,
                        'frequency' => $frequency,
                        'duration' => $duration,
                        'quantity' => $quantity,
                        'instructions' => $instructions,
                        'unit_price' => $unit_price,
                        'total_price' => $total_price,
                        'batch_number' => $med['batch_number'] ?? '',
                        'new_stock' => $new_stock,
                        'status' => 'pending'
                    ];
                    $response['bill_data'] = $bill_data;
                    $response['diagnosis_saved'] = $diagnosis_saved;
                    $response['diagnosis_data'] = $diagnosis_data;
                } else {
                    $response['message'] = '❌ Medication not found or inactive';
                }
            } catch (Exception $e) {
                if (isset($db) && $db->inTransaction()) {
                    $db->rollBack();
                }
                $response['message'] = '❌ Database error: ' . $e->getMessage();
            }
        } else {
            $response['message'] = '❌ Please select a medication and quantity';
        }
        
        echo json_encode($response);
        exit;
    }
    
    // ================================================================
    // AJAX: REMOVE MEDICATION
    // ================================================================
    if ($action === 'remove_medication') {
        header('Content-Type: application/json');
        $prescription_id = (int)($_POST['prescription_id'] ?? 0);
        $response = ['success' => false, 'message' => ''];
        
        if ($prescription_id > 0) {
            $stmt = $db->prepare("SELECT status FROM prescriptions WHERE id = ? AND visit_id = ?");
            $stmt->execute([$prescription_id, $visit_id]);
            $presc = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($presc && $presc['status'] === 'dispensed') {
                $response['message'] = '❌ Cannot remove - already dispensed by Pharmacy';
                echo json_encode($response);
                exit;
            }
            
            $stmt = $db->prepare("
                SELECT pi.medication_name, pi.quantity, pi.inventory_id,
                       pi.total_price
                FROM prescription_items pi
                WHERE pi.prescription_id = ?
            ");
            $stmt->execute([$prescription_id]);
            $med_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            try {
                $db->beginTransaction();
                
                foreach ($med_items as $med) {
                    if ($med && $med['inventory_id']) {
                        $stmt = $db->prepare("
                            UPDATE medications_inventory 
                            SET quantity = quantity + ? 
                            WHERE id = ? AND branch_id = ?
                        ");
                        $stmt->execute([$med['quantity'], $med['inventory_id'], $doctor_branch_id]);
                    }
                }
                
                $stmt = $db->prepare("
                    DELETE FROM bill_items 
                    WHERE bill_id = ? AND reference_id = ? AND reference_type = 'prescription'
                ");
                $stmt->execute([$bill_id, $prescription_id]);
                
                $stmt = $db->prepare("DELETE FROM prescription_items WHERE prescription_id = ?");
                $stmt->execute([$prescription_id]);
                
                $stmt = $db->prepare("DELETE FROM prescriptions WHERE id = ? AND visit_id = ?");
                $stmt->execute([$prescription_id, $visit_id]);
                
                $db->commit();
                $bill_data = updateBillTotal($db, $bill_id);
                
                $response['success'] = true;
                $response['message'] = '✅ Medication removed! Stock returned.';
                $response['bill_data'] = $bill_data;
            } catch (Exception $e) {
                if (isset($db) && $db->inTransaction()) {
                    $db->rollBack();
                }
                $response['message'] = '❌ Error: ' . $e->getMessage();
            }
        } else {
            $response['message'] = '❌ Invalid medication';
        }
        
        echo json_encode($response);
        exit;
    }
    
    // ================================================================
    // AJAX: ADD PROCEDURES BATCH
    // ================================================================
    if ($action === 'add_procedures_batch') {
        header('Content-Type: application/json');
        
        if ($sections_frozen && !$is_waiting) {
            echo json_encode(['success' => false, 'message' => '❌ Cannot add procedures. Lab tests pending!']);
            exit;
        }
        
        $diagnosis_saved = false;
        $diagnosis_data = [];
        
        $diagnosis_id = $_POST['diagnosis_id'] ?? '';
        $diagnosis_manual = trim($_POST['diagnosis_manual'] ?? '');
        $treatment = trim($_POST['treatment'] ?? '');
        $disease_code_manual = trim($_POST['disease_code_manual'] ?? '');
        $symptoms = trim($_POST['symptoms'] ?? '');
        $hpi = trim($_POST['hpi'] ?? '');
        $physical_exam = trim($_POST['physical_exam'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        
        if (!empty($diagnosis_id) || !empty($diagnosis_manual)) {
            try {
                $result = saveDiagnosisToDatabase($db, $visit_id, $doctor_id, $doctor_branch_id, [
                    'diagnosis_id' => $diagnosis_id,
                    'diagnosis_manual' => $diagnosis_manual,
                    'treatment' => $treatment,
                    'disease_code_manual' => $disease_code_manual,
                    'symptoms' => $symptoms,
                    'hpi' => $hpi,
                    'physical_exam' => $physical_exam,
                    'notes' => $notes
                ]);
                $diagnosis_saved = true;
                $diagnosis_data = $result;
            } catch (Exception $e) {
                error_log("Failed to save diagnosis: " . $e->getMessage());
            }
        }
        
        $procedure_ids = isset($_POST['procedure_ids']) ? json_decode($_POST['procedure_ids'], true) : [];
        $response = ['success' => false, 'message' => '', 'added' => 0, 'failed' => 0];
        
        if (empty($procedure_ids)) {
            $response['message'] = '❌ No procedures selected';
            echo json_encode($response);
            exit;
        }
        
        $added = 0;
        $failed = 0;
        
        try {
            $db->beginTransaction();
            
            foreach ($procedure_ids as $proc_id) {
                $proc_id = (int)$proc_id;
                if ($proc_id <= 0) continue;
                
                $stmt = $db->prepare("
                    SELECT pc.*
                    FROM procedures_catalog pc
                    WHERE pc.id = ? AND pc.is_active = 1 
                    AND (pc.branch_id IS NULL OR pc.branch_id = ?)
                ");
                $stmt->execute([$proc_id, $doctor_branch_id]);
                $procedure = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$procedure) {
                    $failed++;
                    continue;
                }
                
                $stmt = $db->prepare("
                    SELECT id FROM procedures 
                    WHERE visit_id = ? AND procedure_id = ? AND status != 'cancelled'
                ");
                $stmt->execute([$visit_id, $proc_id]);
                $exists = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($exists) {
                    $failed++;
                    continue;
                }
                
                $stmt = $db->prepare("
                    INSERT INTO procedures (
                        visit_id, patient_id, doctor_id, procedure_id, procedure_name,
                        category, procedure_price, status, branch_id,
                        notes, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, NULL, NOW())
                ");
                $stmt->execute([
                    $visit_id, $patient_id, $doctor_id, $proc_id,
                    $procedure['procedure_name'],
                    $procedure['category'],
                    $procedure['price'],
                    $doctor_branch_id
                ]);
                $proc_id_inserted = $db->lastInsertId();
                
                $procedure_price = $procedure['price'];
                
                $stmt = $db->prepare("
                    INSERT INTO bill_items (
                        bill_id, patient_id, branch_id, item_type, item_id,
                        item_name, quantity, unit_price, total_price,
                        reference_id, reference_type, status, created_at
                    ) VALUES (?, ?, ?, 'procedure', ?, ?, 1, ?, ?, ?, 'procedure', 'pending', NOW())
                ");
                $stmt->execute([
                    $bill_id,
                    $patient_id,
                    $doctor_branch_id,
                    $proc_id,
                    $procedure['procedure_name'] . ($procedure_price == 0 ? ' (FREE)' : ''),
                    $procedure_price,
                    $procedure_price,
                    $proc_id_inserted
                ]);
                
                $added++;
            }
            
            $db->commit();
            $bill_data = updateBillTotal($db, $bill_id);
            
            $response['success'] = true;
            $response['added'] = $added;
            $response['failed'] = $failed;
            $response['message'] = '✅ ' . $added . ' procedure(s) added!' . ($failed > 0 ? ' ⚠️ ' . $failed . ' failed.' : '');
            $response['bill_data'] = $bill_data;
            $response['diagnosis_saved'] = $diagnosis_saved;
            $response['diagnosis_data'] = $diagnosis_data;
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            $response['message'] = '❌ Error: ' . $e->getMessage();
        }
        
        echo json_encode($response);
        exit;
    }
    
    // ================================================================
    // AJAX: ADD EQUIPMENT BATCH
    // ================================================================
    if ($action === 'add_equipment_batch') {
        header('Content-Type: application/json');
        
        if ($sections_frozen && !$is_waiting) {
            echo json_encode(['success' => false, 'message' => '❌ Cannot add equipment. Lab tests pending!']);
            exit;
        }
        
        $diagnosis_saved = false;
        $diagnosis_data = [];
        
        $diagnosis_id = $_POST['diagnosis_id'] ?? '';
        $diagnosis_manual = trim($_POST['diagnosis_manual'] ?? '');
        $treatment = trim($_POST['treatment'] ?? '');
        $disease_code_manual = trim($_POST['disease_code_manual'] ?? '');
        $symptoms = trim($_POST['symptoms'] ?? '');
        $hpi = trim($_POST['hpi'] ?? '');
        $physical_exam = trim($_POST['physical_exam'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        
        if (!empty($diagnosis_id) || !empty($diagnosis_manual)) {
            try {
                $result = saveDiagnosisToDatabase($db, $visit_id, $doctor_id, $doctor_branch_id, [
                    'diagnosis_id' => $diagnosis_id,
                    'diagnosis_manual' => $diagnosis_manual,
                    'treatment' => $treatment,
                    'disease_code_manual' => $disease_code_manual,
                    'symptoms' => $symptoms,
                    'hpi' => $hpi,
                    'physical_exam' => $physical_exam,
                    'notes' => $notes
                ]);
                $diagnosis_saved = true;
                $diagnosis_data = $result;
            } catch (Exception $e) {
                error_log("Failed to save diagnosis: " . $e->getMessage());
            }
        }
        
        $equipment_data = isset($_POST['equipment_data']) ? json_decode($_POST['equipment_data'], true) : [];
        $response = ['success' => false, 'message' => '', 'added' => 0, 'failed' => 0];
        
        if (empty($equipment_data)) {
            $response['message'] = '❌ No equipment selected';
            echo json_encode($response);
            exit;
        }
        
        $added = 0;
        $failed = 0;
        
        try {
            $db->beginTransaction();
            
            foreach ($equipment_data as $eq_data) {
                $equipment_id = (int)($eq_data['id'] ?? 0);
                $quantity = (int)($eq_data['quantity'] ?? 1);
                
                if ($equipment_id <= 0 || $quantity <= 0) {
                    $failed++;
                    continue;
                }
                
                $stmt = $db->prepare("
                    SELECT id, equipment_name, selling_price, quantity as stock,
                           batch_number, expiry_date
                    FROM medical_equipment 
                    WHERE id = ? AND status = 'active' AND branch_id = ?
                    FOR UPDATE
                ");
                $stmt->execute([$equipment_id, $doctor_branch_id]);
                $equipment = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$equipment) {
                    $failed++;
                    continue;
                }
                
                if (!empty($equipment['expiry_date'])) {
                    $expiry = strtotime($equipment['expiry_date']);
                    if ($expiry < time()) {
                        $failed++;
                        continue;
                    }
                }
                
                if ($equipment['stock'] < $quantity) {
                    $failed++;
                    continue;
                }
                
                $unit_price = $equipment['selling_price'] ?? 0;
                $total_price = $unit_price * $quantity;
                $new_stock = $equipment['stock'] - $quantity;
                
                $stmt = $db->prepare("UPDATE medical_equipment SET quantity = ? WHERE id = ?");
                $stmt->execute([$new_stock, $equipment_id]);
                
                $item_name = $equipment['equipment_name'];
                
                $stmt = $db->prepare("
                    INSERT INTO bill_items (
                        bill_id, patient_id, branch_id, item_type, item_id,
                        item_name, quantity, unit_price, total_price,
                        reference_id, reference_type, status, created_at
                    ) VALUES (?, ?, ?, 'equipment', ?, ?, ?, ?, ?, ?, 'equipment', 'pending', NOW())
                ");
                $stmt->execute([
                    $bill_id,
                    $patient_id,
                    $doctor_branch_id,
                    $equipment_id,
                    $item_name . ($total_price == 0 ? ' (FREE)' : ''),
                    $quantity,
                    $unit_price,
                    $total_price,
                    $equipment_id
                ]);
                
                $added++;
            }
            
            $db->commit();
            $bill_data = updateBillTotal($db, $bill_id);
            
            $response['success'] = true;
            $response['added'] = $added;
            $response['failed'] = $failed;
            $response['message'] = '✅ ' . $added . ' equipment item(s) added!' . ($failed > 0 ? ' ⚠️ ' . $failed . ' failed.' : '');
            $response['bill_data'] = $bill_data;
            $response['diagnosis_saved'] = $diagnosis_saved;
            $response['diagnosis_data'] = $diagnosis_data;
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            $response['message'] = '❌ Error: ' . $e->getMessage();
        }
        
        echo json_encode($response);
        exit;
    }
    
    // ================================================================
    // AJAX: REMOVE ADDED ITEM
    // ================================================================
    if ($action === 'remove_added_item') {
        header('Content-Type: application/json');
        
        $type = $_POST['type'] ?? '';
        $item_id = (int)($_POST['id'] ?? 0);
        $visit_id = (int)($_POST['visit_id'] ?? 0);
        
        $response = ['success' => false, 'message' => ''];
        
        if (empty($type) || $item_id <= 0 || $visit_id <= 0) {
            $response['message'] = '❌ Invalid request';
            echo json_encode($response);
            exit;
        }
        
        try {
            $db->beginTransaction();
            
            if ($type === 'procedure') {
                $stmt = $db->prepare("
                    SELECT p.*
                    FROM procedures p
                    WHERE p.id = ? AND p.visit_id = ? AND p.status != 'cancelled'
                ");
                $stmt->execute([$item_id, $visit_id]);
                $procedure = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$procedure) {
                    $response['message'] = '❌ Procedure not found';
                    echo json_encode($response);
                    exit;
                }
                
                $stmt = $db->prepare("
                    DELETE FROM bill_items 
                    WHERE bill_id = ? AND reference_id = ? AND reference_type = 'procedure'
                ");
                $stmt->execute([$bill_id, $item_id]);
                
                $stmt = $db->prepare("
                    DELETE FROM procedures WHERE id = ? AND visit_id = ?
                ");
                $stmt->execute([$item_id, $visit_id]);
                
                $response['message'] = '✅ Procedure removed!';
                
            } elseif ($type === 'equipment') {
                $stmt = $db->prepare("
                    SELECT bi.*
                    FROM bill_items bi
                    WHERE bi.id = ? AND bi.bill_id = ? AND bi.item_type = 'equipment' AND bi.status != 'cancelled'
                ");
                $stmt->execute([$item_id, $bill_id]);
                $equip_item = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$equip_item) {
                    $response['message'] = '❌ Equipment not found';
                    echo json_encode($response);
                    exit;
                }
                
                $stmt_eq = $db->prepare("
                    SELECT id, equipment_name FROM medical_equipment 
                    WHERE equipment_name = ? AND branch_id = ?
                    LIMIT 1
                ");
                $stmt_eq->execute([$equip_item['item_name'], $doctor_branch_id]);
                $equipment = $stmt_eq->fetch(PDO::FETCH_ASSOC);
                
                if ($equipment) {
                    $quantity = $equip_item['quantity'] ?? 1;
                    $stmt = $db->prepare("
                        UPDATE medical_equipment 
                        SET quantity = quantity + ? 
                        WHERE id = ? AND branch_id = ?
                    ");
                    $stmt->execute([$quantity, $equipment['id'], $doctor_branch_id]);
                }
                
                $stmt = $db->prepare("
                    DELETE FROM bill_items WHERE id = ? AND bill_id = ?
                ");
                $stmt->execute([$item_id, $bill_id]);
                
                $response['message'] = '✅ Equipment removed! Stock returned.';
            } else {
                $response['message'] = '❌ Invalid item type';
                echo json_encode($response);
                exit;
            }
            
            $bill_data = updateBillTotal($db, $bill_id);
            $db->commit();
            
            $response['success'] = true;
            $response['bill_data'] = $bill_data;
            
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            $response['message'] = '❌ Error: ' . $e->getMessage();
        }
        
        echo json_encode($response);
        exit;
    }
    
    // ================================================================
    // ✅ AUTO-COMPLETE - ONLY TRIGGERED BY SAVE CONSULTATION BUTTON
    // ================================================================
    if ($action === 'auto_complete_visit') {
        header('Content-Type: application/json');
        $visit_id_auto = (int)($_POST['visit_id'] ?? 0);
        $response = ['success' => false, 'message' => ''];
        
        if ($visit_id_auto > 0) {
            $stmt = $db->prepare("SELECT id, status FROM visits WHERE id = ?");
            $stmt->execute([$visit_id_auto]);
            $visit_check = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$visit_check) {
                $response['message'] = 'Visit not found';
                echo json_encode($response);
                exit;
            }
            
            if ($visit_check['status'] !== 'waiting') {
                $response['message'] = '❌ Visit is not in waiting status. Current: ' . $visit_check['status'] . '. Auto-complete requires status = "waiting"';
                echo json_encode($response);
                exit;
            }
            
            // Check diagnosis
            $stmt = $db->prepare("SELECT diagnosis FROM visits WHERE id = ?");
            $stmt->execute([$visit_id_auto]);
            $diagnosis_check = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$diagnosis_check || empty($diagnosis_check['diagnosis'])) {
                $response['message'] = '❌ Diagnosis is required before auto-complete. Please enter a diagnosis first.';
                echo json_encode($response);
                exit;
            }
            
            $stmt = $db->prepare("SELECT id, balance FROM bills WHERE visit_id = ?");
            $stmt->execute([$visit_id_auto]);
            $bill_check = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$bill_check) {
                $response['message'] = 'Bill not found';
                echo json_encode($response);
                exit;
            }
            
            if ($bill_check['balance'] > 0) {
                $response['message'] = '❌ Bill balance is ' . $bill_check['balance'] . '. Must be 0 to auto-complete.';
                echo json_encode($response);
                exit;
            }
            
            $completed = autoCompleteVisit($db, $visit_id_auto);
            if ($completed) {
                $response['success'] = true;
                $response['message'] = '✅ Visit #' . $visit_id_auto . ' auto-completed successfully!';
            } else {
                $response['message'] = 'Auto-complete failed. Visit may already be completed.';
            }
        } else {
            $response['message'] = 'Invalid visit ID';
        }
        
        echo json_encode($response);
        exit;
    }
    
    // ================================================================
    // SEND LAB REQUESTS FROM CART
    // ================================================================
    if (isset($_POST['send_lab'])) {
        $lab_cart = isset($_SESSION['lab_cart']) ? $_SESSION['lab_cart'] : [];
        
        if (empty($lab_cart)) {
            $_SESSION['flash_message'] = "❌ No lab tests in cart. Please add tests first.";
            $_SESSION['flash_type'] = 'error';
            header('Location: consultation.php?visit_id=' . $visit_id);
            exit;
        }
        
        $symptoms = trim($_POST['symptoms'] ?? '');
        $hpi = trim($_POST['hpi'] ?? '');
        $physical_exam = trim($_POST['physical_exam'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        
        $stmt = $db->prepare("
            UPDATE visits 
            SET 
                symptoms = ?,
                hpi = ?,
                physical_exam = ?,
                notes = ?,
                updated_at = NOW()
            WHERE id = ? AND doctor_id = ?
        ");
        $stmt->execute([
            $symptoms,
            $hpi,
            $physical_exam,
            $notes,
            $visit_id,
            $doctor_id
        ]);
        
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM lab_tests 
            WHERE visit_id = ? AND status IN ('pending', 'in_progress')
        ");
        $stmt->execute([$visit_id]);
        $active_tests = $stmt->fetchColumn();
        
        if ($active_tests > 0) {
            $_SESSION['flash_message'] = "⚠️ Lab tests already sent!";
            $_SESSION['flash_type'] = 'warning';
            header('Location: consultation.php?visit_id=' . $visit_id);
            exit;
        }
        
        $lab_tests_sent = 0;
        $lab_tests_skipped = 0;
        $errors = [];
        $total_lab_price = 0;
        
        try {
            $db->beginTransaction();
            
            foreach ($lab_cart as $cart_item) {
                $test_id = $cart_item['id'];
                $test_name = $cart_item['name'];
                $test_price = $cart_item['price'];
                $required_equipment_id = $cart_item['required_equipment_id'] ?? null;
                $equipment_quantity_used = $cart_item['equipment_quantity_used'] ?? 1;
                
                $stmt_check = $db->prepare("
                    SELECT COUNT(*) FROM lab_tests 
                    WHERE visit_id = ? AND test_id = ? AND status != 'cancelled'
                ");
                $stmt_check->execute([$visit_id, $test_id]);
                $exists = $stmt_check->fetchColumn();
                
                if ($exists > 0) {
                    $lab_tests_skipped++;
                    continue;
                }
                
                // LAB TEST LINKED EQUIPMENT - FREE (not charged)
                if ($required_equipment_id) {
                    $stmt_eq = $db->prepare("
                        SELECT id, equipment_name, quantity as stock, batch_number, expiry_date
                        FROM medical_equipment 
                        WHERE id = ? AND status = 'active' AND branch_id = ?
                        AND (expiry_date IS NULL OR expiry_date > CURDATE())
                        FOR UPDATE
                    ");
                    $stmt_eq->execute([$required_equipment_id, $doctor_branch_id]);
                    $equip = $stmt_eq->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$equip) {
                        $errors[] = "❌ Equipment not found for test: $test_name";
                        continue;
                    }
                    
                    if ($equip['stock'] < $equipment_quantity_used) {
                        $errors[] = "❌ Insufficient equipment stock for $test_name";
                        continue;
                    }
                    
                    $new_equipment_stock = $equip['stock'] - $equipment_quantity_used;
                    $stmt_update = $db->prepare("UPDATE medical_equipment SET quantity = ? WHERE id = ?");
                    $stmt_update->execute([$new_equipment_stock, $required_equipment_id]);
                }
                
                $stmt = $db->prepare("
                    INSERT INTO lab_tests (
                        visit_id, patient_id, doctor_id, test_id, test_name, test_price,
                        status, branch_id, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
                ");
                $stmt->execute([
                    $visit_id, $patient_id, $doctor_id, $test_id,
                    $test_name, $test_price,
                    $doctor_branch_id
                ]);
                $lab_test_id = $db->lastInsertId();
                
                $stmt = $db->prepare("
                    INSERT INTO bill_items (
                        bill_id, patient_id, branch_id, item_type, item_id,
                        item_name, quantity, unit_price, total_price,
                        reference_id, reference_type, status, created_at
                    ) VALUES (?, ?, ?, 'lab_test', ?, ?, 1, ?, ?, ?, 'lab_test', 'pending', NOW())
                ");
                $stmt->execute([
                    $bill_id,
                    $patient_id,
                    $doctor_branch_id,
                    $lab_test_id,
                    $test_name . ($required_equipment_id ? ' (equipment included)' : ''),
                    $test_price,
                    $test_price,
                    $lab_test_id
                ]);
                
                $total_lab_price += $test_price;
                $lab_tests_sent++;
            }
            
            if ($lab_tests_sent > 0) {
                $stmt = $db->prepare("UPDATE visits SET status = 'lab_test', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$visit_id]);
                $bill_data = updateBillTotal($db, $bill_id);
            }
            
            $db->commit();
            
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            $_SESSION['flash_message'] = "❌ Error sending lab tests: " . $e->getMessage();
            $_SESSION['flash_type'] = 'error';
            unset($_SESSION['lab_cart']);
            header('Location: consultation.php?visit_id=' . $visit_id);
            exit;
        }
        
        unset($_SESSION['lab_cart']);
        
        if ($lab_tests_sent > 0) {
            $msg = "✅ " . $lab_tests_sent . " lab request(s) sent to Laboratory!";
            $msg .= "<br>📝 Consultation data saved!";
            $msg .= "<br>⏳ Status changed to LAB TEST";
            $msg .= "<br>⏳ Waiting for results (updates to PRESCRIBED when complete)";
            $msg .= "<br>💰 Total Lab Fees: TSh " . number_format($total_lab_price, 0);
            
            $_SESSION['flash_message'] = $msg;
            $_SESSION['flash_type'] = 'success';
            $_SESSION['auto_refresh_needed'] = true;
        } else {
            $_SESSION['flash_message'] = "❌ No new lab tests sent. " . implode(', ', $errors);
            $_SESSION['flash_type'] = 'error';
        }
        
        header('Location: consultation.php?visit_id=' . $visit_id);
        exit;
    }
    
    // ================================================================
    // ✅ SAVE CONSULTATION - ONLY THIS CHANGES STATUS TO 'waiting'
    // ================================================================
    if (isset($_POST['save_consultation'])) {
        $diagnosis_id = $_POST['diagnosis_id'] ?? '';
        $diagnosis_manual = trim($_POST['diagnosis_manual'] ?? '');
        $treatment = trim($_POST['treatment'] ?? '');
        $disease_code_manual = trim($_POST['disease_code_manual'] ?? '');
        $symptoms = trim($_POST['symptoms'] ?? '');
        $hpi = trim($_POST['hpi'] ?? '');
        $physical_exam = trim($_POST['physical_exam'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        
        // ✅ CHECK: Diagnosis lazima ijazwe
        if (empty($diagnosis_manual) && (empty($diagnosis_id) || $diagnosis_id == '0')) {
            $_SESSION['flash_message'] = "❌ Please enter a diagnosis before saving consultation!";
            $_SESSION['flash_type'] = 'error';
            header('Location: consultation.php?visit_id=' . $visit_id);
            exit;
        }
        
        // Save diagnosis
        try {
            $stmt = $db->prepare("
                UPDATE visits 
                SET 
                    diagnosis = ?,
                    disease_code = ?,
                    treatment = ?,
                    symptoms = ?,
                    hpi = ?,
                    physical_exam = ?,
                    notes = ?,
                    updated_at = NOW()
                WHERE id = ? AND doctor_id = ?
            ");
            $stmt->execute([
                $diagnosis_manual ?: null,
                $disease_code_manual ?: null,
                $treatment ?: null,
                $symptoms ?: null,
                $hpi ?: null,
                $physical_exam ?: null,
                $notes ?: null,
                $visit_id,
                $doctor_id
            ]);
        } catch (Exception $e) {
            error_log("Failed to save diagnosis: " . $e->getMessage());
            $_SESSION['flash_message'] = "❌ Error saving diagnosis: " . $e->getMessage();
            $_SESSION['flash_type'] = 'error';
            header('Location: consultation.php?visit_id=' . $visit_id);
            exit;
        }
        
        // ✅ ONLY HERE - CHANGE STATUS TO 'waiting'
        $stmt = $db->prepare("
            UPDATE visits 
            SET status = 'waiting',
                updated_at = NOW()
            WHERE id = ? AND doctor_id = ? AND status IN ('prescribed', 'lab_test', 'assigned')
        ");
        $stmt->execute([$visit_id, $doctor_id]);
        
        $stmt_check = $db->prepare("SELECT status FROM visits WHERE id = ?");
        $stmt_check->execute([$visit_id]);
        $new_status = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        if ($new_status && $new_status['status'] === 'waiting') {
            error_log("✅ Visit #$visit_id status changed to 'waiting' by Save Consultation button");
            
            // ✅ NOW CHECK IF AUTO-COMPLETE SHOULD RUN (status='waiting', diagnosis exists, balance=0)
            $can_auto = canAutoCompleteVisit($db, $visit_id, $bill_id);
            if ($can_auto) {
                error_log("✅ AUTO-COMPLETE triggered after Save Consultation (status=waiting, diagnosis exists, balance=0)");
                $auto_completed = autoCompleteVisit($db, $visit_id);
                if ($auto_completed) {
                    $_SESSION['flash_message'] = "✅ Consultation saved AND auto-completed! All bills paid. Status: COMPLETED";
                    $_SESSION['flash_type'] = 'success';
                    header('Location: consultation.php?visit_id=' . $visit_id);
                    exit;
                }
            }
            
            $_SESSION['flash_message'] = "✅ Consultation saved! Status changed to WAITING. Auto-complete will run when diagnosis is filled and balance is 0.";
            $_SESSION['flash_type'] = 'success';
        } else {
            error_log("❌ Failed to update visit #$visit_id status");
            $_SESSION['flash_message'] = "⚠️ Consultation saved but status may not be WAITING.";
            $_SESSION['flash_type'] = 'warning';
        }
        
        header('Location: consultation.php?visit_id=' . $visit_id);
        exit;
    }
}

// Initialize lab cart
if (!isset($_SESSION['lab_cart'])) {
    $_SESSION['lab_cart'] = [];
}
$lab_cart = $_SESSION['lab_cart'];
$lab_cart_total = array_sum(array_column($lab_cart, 'price'));
$lab_cart_count = count($lab_cart);

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once __DIR__ . '/../../components/doctor_header.php';
include_once __DIR__ . '/../../components/doctor_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_completed ? 'View Consultation' : 'Consultation' ?> - Braick Dispensary</title>
    <link rel="icon" href="/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        /* ================================================================ */
        /* ROOT VARIABLES - LIGHT & DARK MODE */
        /* ================================================================ */
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
            --primary-gradient: linear-gradient(135deg, #0B5ED7 0%, #1A7FE8 100%);
            --success: #059669;
            --success-bg: #D1FAE5;
            --danger: #DC2626;
            --danger-bg: #FEE2E2;
            --warning: #D97706;
            --warning-bg: #FEF3C7;
            --purple: #7C3AED;
            --purple-bg: #EDE9FE;
            --gray-50: #F8FAFC;
            --gray-100: #F1F5F9;
            --gray-200: #E2E8F0;
            --gray-300: #CBD5E1;
            --gray-400: #94A3B8;
            --gray-500: #64748B;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1E293B;
            --gray-900: #0F172A;
            --radius: 12px;
            --radius-lg: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 16px rgba(11,94,215,0.10);
            --shadow-lg: 0 8px 32px rgba(11,94,215,0.15);
            --bg-body: #F8FAFC;
            --bg-card: #ffffff;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --text-primary: #E2E8F0;
            --text-secondary: #94A3B8;
            --border-color: #334155;
            --gray-50: #1E293B;
            --gray-100: #1E293B;
            --gray-200: #334155;
            --gray-300: #475569;
            --gray-400: #64748B;
            --gray-500: #94A3B8;
            --gray-600: #A3B8CC;
            --gray-700: #CBD5E1;
            --gray-800: #E2E8F0;
            --gray-900: #F1F5F9;
            --shadow-md: 0 4px 16px rgba(0,0,0,0.3);
            --shadow-lg: 0 8px 32px rgba(0,0,0,0.4);
            --primary-bg: #1E3A5F;
            --success-bg: #064E3B;
            --danger-bg: #7F1D1D;
            --warning-bg: #78350F;
            --purple-bg: #4C1D95;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            background: var(--bg-body); 
            color: var(--text-primary); 
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif; 
            line-height: 1.6; 
            transition: background 0.3s ease, color 0.3s ease; 
        }
        .main-content { 
            margin-left: 270px; 
            margin-top: 68px; 
            padding: 28px 32px; 
            min-height: calc(100vh - 68px); 
            background: var(--bg-body); 
            color: var(--text-primary); 
            transition: background 0.3s ease, color 0.3s ease; 
        }
        
        /* ================================================================ */
        /* PAGE HEADER */
        /* ================================================================ */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 28px;
            padding: 24px 28px;
            background: var(--primary-gradient);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            position: relative;
            color: #ffffff !important;
        }
        .page-header * { color: #ffffff !important; }
        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin: 0;
            color: #ffffff !important;
        }
        .page-title i { color: rgba(255,255,255,0.8) !important; }
        .page-badge {
            font-size: 0.7rem;
            font-weight: 600;
            background: rgba(255,255,255,0.2);
            color: #ffffff !important;
            padding: 4px 16px;
            border-radius: 20px;
            font-family: monospace;
            border: 1px solid rgba(255,255,255,0.2);
        }
        .page-subtitle {
            font-size: 0.9rem;
            opacity: 0.85;
            margin-top: 6px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            color: rgba(255,255,255,0.9) !important;
        }
        .page-subtitle strong { color: #ffffff !important; font-weight: 700; }
        
        .role-badge-display {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            backdrop-filter: blur(4px);
        }
        
        .btn-outline-light {
            background: rgba(255,255,255,0.12);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 16px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.82rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(4px);
            position: relative;
            z-index: 1;
        }
        .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        .status-badge-lg {
            display: inline-block;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-warning { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning); }
        .badge-info { background: var(--primary-bg); color: var(--primary); border: 1px solid var(--primary); }
        .badge-success { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
        .badge-danger { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger); }
        .badge-purple { background: var(--purple-bg); color: var(--purple); border: 1px solid var(--purple); }
        
        /* ================================================================ */
        /* CONSULTATION CARDS */
        /* ================================================================ */
        .consultation-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 24px 28px;
            border: 1px solid var(--border-color);
            transition: var(--transition);
            margin-bottom: 24px;
            box-shadow: var(--shadow-md);
            position: relative;
            overflow: hidden;
        }
        .consultation-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
            border-radius: 0 0 4px 4px;
        }
        .consultation-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }
        .card-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary);
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 14px;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .card-title i { color: var(--primary); font-size: 1.2rem; }
        
        /* ================================================================ */
        /* 4 BILL SUMMARY CARDS */
        /* ================================================================ */
        .bill-summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }
        .bill-summary-card {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .bill-summary-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            border-radius: 14px 14px 0 0;
        }
        .bill-summary-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
        .bill-summary-card .bill-summary-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
            background: var(--gray-100);
            color: var(--gray-600);
        }
        .bill-summary-card .bill-summary-content { flex: 1; }
        .bill-summary-card .bill-summary-label {
            font-size: 0.65rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: block;
        }
        .bill-summary-card .bill-summary-value {
            font-size: 1.2rem;
            font-weight: 700;
            display: block;
            margin-top: 2px;
            color: var(--text-primary);
        }
        
        .bill-summary-card.total-card { border-color: var(--primary); }
        .bill-summary-card.total-card::before { background: var(--primary); }
        .bill-summary-card.total-card .bill-summary-icon { background: var(--primary-bg); color: var(--primary); }
        .bill-summary-card.total-card .bill-summary-value { color: var(--primary); }
        
        .bill-summary-card.paid-card { border-color: var(--success); }
        .bill-summary-card.paid-card::before { background: var(--success); }
        .bill-summary-card.paid-card .bill-summary-icon { background: var(--success-bg); color: var(--success); }
        .bill-summary-card.paid-card .bill-summary-value { color: var(--success); }
        
        .bill-summary-card.remaining-card { border-color: var(--warning); }
        .bill-summary-card.remaining-card::before { background: var(--warning); }
        .bill-summary-card.remaining-card .bill-summary-icon { background: var(--warning-bg); color: var(--warning); }
        .bill-summary-card.remaining-card .bill-summary-value { color: var(--warning); }
        .bill-summary-card.remaining-card.zero-balance { border-color: var(--success); }
        .bill-summary-card.remaining-card.zero-balance::before { background: var(--success); }
        .bill-summary-card.remaining-card.zero-balance .bill-summary-icon { background: var(--success-bg); color: var(--success); }
        .bill-summary-card.remaining-card.zero-balance .bill-summary-value { color: var(--success); }
        
        .bill-summary-card.discount-card { border-color: var(--purple); }
        .bill-summary-card.discount-card::before { background: var(--purple); }
        .bill-summary-card.discount-card .bill-summary-icon { background: var(--purple-bg); color: var(--purple); }
        .bill-summary-card.discount-card .bill-summary-value { color: var(--purple); }
        
        /* ================================================================ */
        /* STATUS FLOW */
        /* ================================================================ */
        .status-flow {
            display: flex;
            align-items: center;
            gap: 4px;
            padding: 8px 12px;
            background: var(--gray-50);
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
            flex-wrap: wrap;
        }
        .status-step {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 0.65rem;
            padding: 2px 10px;
            border-radius: 12px;
            background: var(--gray-200);
            color: var(--text-secondary);
        }
        .status-step.active {
            background: var(--primary);
            color: #ffffff;
        }
        .status-step.completed {
            background: var(--success);
            color: #ffffff;
        }
        .status-arrow {
            color: var(--text-secondary);
            font-size: 0.6rem;
        }
        
        /* ================================================================ */
        /* VITAL SIGNS */
        /* ================================================================ */
        .vital-signs-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }
        .vital-sign-item {
            background: var(--bg-card);
            border-radius: 14px;
            padding: 18px 20px;
            border: 2px solid var(--border-color);
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }
        .vital-sign-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            border-radius: 14px 14px 0 0;
        }
        .vital-sign-item:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-light);
        }
        .vital-sign-item .vital-icon { font-size: 1.8rem; display: block; margin-bottom: 4px; }
        .vital-sign-item .vital-label {
            font-size: 0.6rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            display: block;
        }
        .vital-sign-item .vital-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            display: block;
            margin-top: 2px;
        }
        .vital-sign-item .vital-unit { font-size: 0.7rem; color: var(--text-secondary); font-weight: 400; }
        
        .vital-sign-item.bp-item { border-color: var(--primary-light); }
        .vital-sign-item.bp-item::before { background: var(--primary); }
        .vital-sign-item.bp-item .vital-value { color: var(--primary); }
        .vital-sign-item.temp-item { border-color: #FCA5A5; }
        .vital-sign-item.temp-item::before { background: #DC2626; }
        .vital-sign-item.temp-item .vital-value { color: #DC2626; }
        .vital-sign-item.pulse-item { border-color: #C4B5FD; }
        .vital-sign-item.pulse-item::before { background: #7C3AED; }
        .vital-sign-item.pulse-item .vital-value { color: #7C3AED; }
        .vital-sign-item.weight-item { border-color: #FCD34D; }
        .vital-sign-item.weight-item::before { background: #D97706; }
        .vital-sign-item.weight-item .vital-value { color: #D97706; }
        .vital-sign-item.height-item { border-color: #6EE7B7; }
        .vital-sign-item.height-item::before { background: #059669; }
        .vital-sign-item.height-item .vital-value { color: #059669; }
        .vital-sign-item.bmi-item { border-color: #93C5FD; }
        .vital-sign-item.bmi-item::before { background: #2563EB; }
        .vital-sign-item.bmi-item .vital-value { color: #2563EB; }
        
        /* ================================================================ */
        /* PATIENT INFO */
        /* ================================================================ */
        .patient-info-block {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 16px 20px;
            background: var(--primary-bg);
            border-radius: var(--radius);
            margin-bottom: 18px;
        }
        .patient-avatar {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 700;
            color: #ffffff;
            flex-shrink: 0;
        }
        .patient-info-details h4 {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }
        .patient-info-details p {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin: 2px 0;
        }
        .patient-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px 20px;
        }
        .patient-info-grid .info-item span:first-child {
            display: block;
            font-size: 0.65rem;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .patient-info-grid .info-item span:last-child {
            display: block;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-primary);
        }
        .col-span-2 { grid-column: span 2; }
        
        /* ================================================================ */
        /* FORM ELEMENTS */
        /* ================================================================ */
        .form-group { margin-bottom: 16px; }
        .form-group:last-child { margin-bottom: 0; }
        .form-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 5px;
            letter-spacing: 0.02em;
        }
        .required { color: var(--danger); margin-left: 2px; }
        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.85rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: var(--transition);
            font-family: inherit;
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11,94,215,0.12);
        }
        .form-control:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            background: var(--gray-100);
        }
        
        /* ================================================================ */
        /* BUTTONS */
        /* ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 18px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
            min-height: 38px;
        }
        .btn-primary { background: var(--primary); color: #ffffff; }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(11,94,215,0.3); }
        .btn-success { background: var(--success); color: #ffffff; }
        .btn-success:hover { background: #047857; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(5,150,105,0.3); }
        .btn-warning { background: var(--warning); color: #ffffff; }
        .btn-warning:hover { background: #B45309; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(217,119,6,0.3); }
        .btn-outline { background: transparent; color: var(--text-primary); border: 2px solid var(--border-color); }
        .btn-outline:hover { background: var(--gray-100); border-color: var(--gray-400); transform: translateY(-2px); }
        .btn-sm { padding: 4px 12px; font-size: 0.7rem; min-height: 30px; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none !important; }
        
        /* ================================================================ */
        /* FORM ACTIONS */
        /* ================================================================ */
        .form-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            padding-top: 20px;
            margin-top: 20px;
            border-top: 2px solid var(--border-color);
        }
        
        /* ================================================================ */
        /* ALERT */
        /* ================================================================ */
        .alert {
            padding: 14px 20px;
            border-radius: var(--radius);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.9rem;
            border: 1px solid transparent;
            animation: slideDown 0.3s ease;
        }
        .alert-success { background: var(--success-bg); color: var(--success); border-color: var(--success); }
        .alert-error { background: var(--danger-bg); color: var(--danger); border-color: var(--danger); }
        .alert-warning { background: var(--warning-bg); color: var(--warning); border-color: var(--warning); }
        .alert-info { background: var(--primary-bg); color: var(--primary); border-color: var(--primary); }
        
        /* ================================================================ */
        /* TOAST */
        /* ================================================================ */
        .toast-custom {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: var(--success) !important;
            border-radius: 12px;
            padding: 16px 24px;
            box-shadow: 0 8px 32px rgba(5,150,105,0.35);
            display: flex;
            align-items: center;
            gap: 16px;
            z-index: 9999;
            min-width: 320px;
            max-width: 450px;
            transform: translateY(120px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(255,255,255,0.2);
        }
        .toast-custom.show {
            transform: translateY(0);
            opacity: 1;
        }
        .toast-custom * { color: #ffffff !important; }
        .toast-custom .toast-icon { font-size: 1.5rem; flex-shrink: 0; color: #ffffff !important; }
        .toast-custom .toast-content { flex: 1; }
        .toast-custom .toast-content .toast-title { 
            font-weight: 600; 
            font-size: 0.85rem; 
            color: #ffffff !important; 
            margin: 0; 
        }
        .toast-custom .toast-content .toast-message { 
            font-size: 0.8rem; 
            color: rgba(255,255,255,0.9) !important; 
            margin: 0; 
        }
        .toast-custom .toast-close {
            background: none;
            border: none;
            font-size: 1.2rem;
            color: rgba(255,255,255,0.7) !important;
            cursor: pointer;
            padding: 0 4px;
            transition: var(--transition);
        }
        .toast-custom .toast-close:hover {
            color: #ffffff !important;
            transform: scale(1.1);
        }
        
        /* ================================================================ */
        /* FOOTER */
        /* ================================================================ */
        .footer {
            padding: 16px 0;
            border-top: 2px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        /* ================================================================ */
        /* UTILITY CLASSES */
        /* ================================================================ */
        .row-2col { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .mb-6 { margin-bottom: 24px; }
        .mt-2 { margin-top: 8px; }
        .mt-3 { margin-top: 12px; }
        .mt-4 { margin-top: 16px; }
        .flex { display: flex; }
        .flex-wrap { flex-wrap: wrap; }
        .gap-2 { gap: 8px; }
        .gap-3 { gap: 12px; }
        .items-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        .text-sm { font-size: 0.875rem; }
        .text-xs { font-size: 0.75rem; }
        .text-gray-400 { color: var(--text-secondary); }
        .text-green-600 { color: var(--success); }
        .text-yellow-600 { color: var(--warning); }
        .text-red-500 { color: var(--danger); }
        .text-purple-600 { color: var(--purple); }
        .font-mono { font-family: monospace; }
        .font-medium { font-weight: 500; }
        .font-semibold { font-weight: 600; }
        .font-bold { font-weight: 700; }
        .ml-2 { margin-left: 8px; }
        .self-center { align-self: center; }
        .empty-state { text-align: center; padding: 16px; color: var(--text-secondary); }
        .empty-state i { font-size: 1.5rem; color: var(--border-color); display: block; margin-bottom: 8px; }
        .frozen-badge {
            background: rgba(255,255,255,0.2);
            color: #ffffff !important;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            border: 1px solid rgba(255,255,255,0.2);
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .frozen-badge.success { background: rgba(5,150,105,0.4); border-color: var(--success); }
        
        .frozen-overlay-active { position: relative; }
        .frozen-overlay-active::after {
            content: '🔒 Lab tests pending - Sections Frozen';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0,0,0,0.75);
            color: #ffffff;
            padding: 12px 24px;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            z-index: 100;
            pointer-events: none;
            border: 2px solid var(--warning);
        }
        .frozen-overlay-active > * { opacity: 0.4; pointer-events: none; }
        .frozen-overlay-active .frozen-badge { opacity: 1; pointer-events: auto; }
        .frozen-overlay-active .bill-summary-grid { opacity: 1; pointer-events: auto; }
        
        .diagnosis-manual-box {
            margin-top: 12px;
            padding: 16px;
            background: var(--gray-50);
            border-radius: var(--radius);
            border: 1px dashed var(--border-color);
        }
        
        /* ================================================================ */
        /* LAB CART ITEMS */
        /* ================================================================ */
        .lab-cart-items { max-height: 200px; overflow-y: auto; }
        .lab-cart-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 12px;
            border-bottom: 1px solid var(--border-color);
        }
        .lab-cart-item:last-child { border-bottom: none; }
        .lab-cart-item .cart-item-name { font-weight: 500; font-size: 0.85rem; color: var(--text-primary); }
        .lab-cart-item .cart-item-price { font-size: 0.8rem; color: var(--success); font-weight: 600; }
        .lab-cart-item .btn-remove-cart {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            border: none;
            background: var(--danger-bg);
            color: var(--danger);
            cursor: pointer;
            font-size: 0.7rem;
            transition: var(--transition);
        }
        .lab-cart-item .btn-remove-cart:hover { background: var(--danger); color: #ffffff; }
        .lab-cart-empty { text-align: center; padding: 16px; color: var(--text-secondary); font-size: 0.85rem; }
        
        /* ================================================================ */
        /* MEDICATION ITEMS */
        /* ================================================================ */
        .medication-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-color);
            transition: var(--transition);
            animation: fadeIn 0.3s ease;
        }
        .medication-item:last-child { border-bottom: none; }
        .medication-item:hover { background: var(--primary-bg); border-radius: var(--radius); }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .medication-item-info { flex: 1; }
        .med-name { font-weight: 600; font-size: 0.9rem; color: var(--text-primary); }
        .med-details { font-size: 0.75rem; color: var(--text-secondary); display: block; }
        .med-qty { font-size: 0.7rem; color: var(--text-secondary); background: var(--gray-200); padding: 2px 12px; border-radius: 12px; margin-left: 8px; }
        .med-instruction-tag {
            font-size: 0.65rem;
            color: var(--primary);
            background: var(--primary-bg);
            padding: 1px 10px;
            border-radius: 12px;
            margin-left: 4px;
            border: 1px solid var(--primary-light);
        }
        .med-price { font-size: 0.8rem; font-weight: 600; color: var(--success); margin-left: 10px; }
        .med-status-dispensed {
            font-size: 0.6rem;
            background: var(--success-bg);
            color: var(--success);
            padding: 1px 10px;
            border-radius: 12px;
            margin-left: 6px;
            border: 1px solid var(--success);
        }
        .med-status-pending {
            font-size: 0.6rem;
            background: var(--warning-bg);
            color: var(--warning);
            padding: 1px 10px;
            border-radius: 12px;
            margin-left: 6px;
            border: 1px solid var(--warning);
        }
        .btn-remove {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: none;
            background: var(--danger-bg);
            color: var(--danger);
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .btn-remove:hover { background: var(--danger); color: #ffffff; transform: scale(1.1); }
        
        /* ================================================================ */
        /* ADDED ITEMS */
        /* ================================================================ */
        .added-item-card {
            background: var(--bg-card);
            border-radius: 10px;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
            transition: var(--transition);
            box-shadow: var(--shadow);
        }
        .added-item-card:hover {
            border-color: var(--primary-light);
            background: var(--primary-bg);
            box-shadow: var(--shadow-md);
        }
        .added-item-card .item-left {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
            min-width: 0;
            flex-wrap: wrap;
        }
        .added-item-card .item-name { 
            font-weight: 500; 
            font-size: 0.85rem; 
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .added-item-card .item-details { 
            font-size: 0.7rem; 
            color: var(--text-secondary);
            white-space: nowrap;
        }
        .added-item-card .item-price { 
            font-weight: 600; 
            color: var(--success); 
            font-size: 0.85rem;
            white-space: nowrap;
        }
        .added-item-card .item-free { 
            color: var(--text-secondary); 
            font-size: 0.7rem; 
            font-weight: 500;
            background: var(--gray-200);
            padding: 2px 10px;
            border-radius: 12px;
        }
        .added-item-card .btn-remove-item {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            border: none;
            background: var(--danger-bg);
            color: var(--danger);
            cursor: pointer;
            font-size: 0.7rem;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-left: 8px;
        }
        .added-item-card .btn-remove-item:hover {
            background: var(--danger);
            color: #ffffff;
            transform: scale(1.1);
        }
        .added-item-card .item-right {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }
        
        /* ================================================================ */
        /* SECTION TOTALS */
        /* ================================================================ */
        .section-total {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 4px 16px;
            border-radius: 20px;
            background: var(--primary-gradient);
            color: #ffffff !important;
            border: none;
            box-shadow: 0 2px 8px rgba(11,94,215,0.2);
        }
        .section-total * { color: #ffffff !important; }
        .section-total .amount { color: #ffffff !important; }
        .section-total .label { opacity: 0.8; font-weight: 400; color: rgba(255,255,255,0.8) !important; }
        .section-total.green { background: linear-gradient(135deg, #059669, #10B981); }
        .section-total.purple { background: linear-gradient(135deg, #7C3AED, #8B5CF6); }
        .section-total.orange { background: linear-gradient(135deg, #D97706, #F59E0B); }
        
        /* ================================================================ */
        /* TOGGLE SECTIONS */
        /* ================================================================ */
        .toggle-section {
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            margin-bottom: 12px;
            overflow: hidden;
            transition: var(--transition);
        }
        .toggle-section:hover { border-color: var(--primary-light); }
        .toggle-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 18px;
            background: var(--gray-50);
            cursor: pointer;
            user-select: none;
            transition: var(--transition);
        }
        .toggle-header:hover { background: var(--primary-bg); }
        .toggle-header .toggle-title { font-weight: 600; font-size: 0.85rem; color: var(--text-primary); display: flex; align-items: center; gap: 10px; }
        .toggle-header .toggle-icon { color: var(--text-secondary); font-size: 0.8rem; transition: var(--transition); }
        .toggle-header.active .toggle-icon { transform: rotate(180deg); }
        .toggle-body { padding: 0 18px 18px 18px; display: none; background: var(--bg-card); }
        .toggle-body.open { display: block; }
        
        /* ================================================================ */
        /* ITEMS GRID */
        /* ================================================================ */
        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 8px;
            margin-top: 8px;
            padding: 12px;
            background: var(--gray-100);
            border-radius: var(--radius);
            max-height: 250px;
            overflow-y: auto;
        }
        .procedure-item-select, .equipment-item-select {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: var(--radius);
            font-size: 0.8rem;
            background: var(--bg-card);
            border: 2px solid var(--border-color);
            cursor: pointer;
            transition: var(--transition);
            user-select: none;
            color: var(--text-primary);
        }
        .procedure-item-select:hover, .equipment-item-select:hover {
            background: var(--primary-bg);
            border-color: var(--primary);
            transform: translateY(-1px);
        }
        .procedure-item-select.selected, .equipment-item-select.selected {
            background: var(--primary-bg);
            border-color: var(--primary);
            color: var(--primary);
        }
        .procedure-item-select .item-check, .equipment-item-select .item-check {
            width: 18px;
            height: 18px;
            border: 2px solid var(--border-color);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: var(--transition);
            color: var(--text-secondary);
        }
        .procedure-item-select.selected .item-check, .equipment-item-select.selected .item-check {
            background: var(--primary);
            border-color: var(--primary);
            color: #ffffff;
        }
        .procedure-item-select .item-check i, .equipment-item-select .item-check i {
            font-size: 0.6rem;
            opacity: 0;
            transition: var(--transition);
        }
        .procedure-item-select.selected .item-check i, .equipment-item-select.selected .item-check i { opacity: 1; }
        
        /* ================================================================ */
        /* RESPONSIVE */
        /* ================================================================ */
        @media (max-width: 1024px) {
            .bill-summary-grid { grid-template-columns: repeat(2, 1fr); }
            .vital-signs-grid { grid-template-columns: repeat(2, 1fr); }
            .row-2col { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px; }
            .page-header { flex-direction: column; }
            .form-actions { flex-direction: column; }
            .form-actions .btn { width: 100%; justify-content: center; }
            .consultation-card { padding: 16px; }
            .bill-summary-grid { grid-template-columns: 1fr 1fr; }
            .vital-signs-grid { grid-template-columns: 1fr 1fr; }
            .frozen-overlay-active::after {
                font-size: 0.7rem;
                padding: 8px 16px;
                white-space: normal;
                text-align: center;
                width: 80%;
            }
            .patient-info-grid { grid-template-columns: 1fr; }
            .toast-custom { min-width: 280px; max-width: 90%; right: 10px; bottom: 10px; padding: 14px 18px; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 12px; }
            .consultation-card { padding: 12px; }
            .page-title { font-size: 1rem; }
            .vital-signs-grid { grid-template-columns: 1fr 1fr; }
            .bill-summary-grid { grid-template-columns: 1fr; }
            .toast-custom { min-width: 240px; padding: 12px 14px; }
            .toast-custom .toast-icon { font-size: 1.2rem; }
        }
        @media print {
            .top-nav, .sidebar, .footer, .page-header-right, .form-actions { display: none !important; }
            .main-content { margin: 0 !important; padding: 20px !important; }
            .consultation-card { 
                border: 1px solid #ddd !important; 
                box-shadow: none !important; 
                page-break-inside: avoid;
                background: #ffffff !important;
            }
            .page-header { background: #0B5ED7 !important; }
            .bill-summary-card { border: 1px solid #ddd !important; box-shadow: none !important; }
        }
    </style>
</head>
<body>

<main class="main-content <?= $is_completed ? 'view-mode' : '' ?>">

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <?php if ($is_completed): ?>
                    <i class="fas fa-check-circle"></i> Consultation Completed
                <?php else: ?>
                    <i class="fas fa-stethoscope"></i> Consultation
                <?php endif; ?>
                <span class="page-badge"><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></span>
                <?php if ($is_completed): ?>
                    <span style="background:var(--success);color:#ffffff;padding:4px 16px;border-radius:20px;font-size:0.7rem;font-weight:600;">✅ Completed</span>
                <?php endif; ?>
                <?php if ($sections_frozen && !$is_completed && !$is_waiting): ?>
                    <span class="frozen-badge" id="frozenBadgeHeader">🔒 Lab Pending</span>
                <?php elseif ($lab_results_available && !$is_completed && !$is_waiting): ?>
                    <span class="frozen-badge success" id="frozenBadgeHeader">✅ Lab Results Available</span>
                <?php endif; ?>
                <?php if (!$is_completed): ?>
                    <span style="background:rgba(255,255,255,0.15);color:#ffffff;padding:2px 12px;border-radius:20px;font-size:0.65rem;font-weight:600;border:1px solid rgba(255,255,255,0.2);display:inline-flex;align-items:center;gap:4px;">
                        <i class="fas fa-circle" style="color:#34D399;font-size:0.4rem;"></i> Live
                        <span id="liveTime" style="font-weight:400;font-size:0.55rem;"><?= date('H:i:s') ?></span>
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                Patient: <strong><?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?></strong>
                (<?= htmlspecialchars($visit['patient_code'] ?? 'N/A') ?>)
                <span style="color:rgba(255,255,255,0.4);">|</span>
                Status: 
                <span class="status-badge <?= getStatusBadgeClass($visit['status'] ?? 'pending') ?>" id="visitStatusBadge">
                    <?= ucfirst(str_replace('_', ' ', $visit['status'] ?? 'Assigned')) ?>
                </span>
                <span style="font-size:0.75rem;color:rgba(255,255,255,0.7);" id="lastUpdateTime">⏱ <?= date('H:i:s') ?></span>
                <span style="background:rgba(255,255,255,0.2);color:#ffffff;padding:2px 12px;border-radius:20px;font-size:0.65rem;font-weight:600;border:1px solid rgba(255,255,255,0.2);display:inline-flex;align-items:center;gap:4px;">
                    <i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor_branch_name) ?>
                </span>
            </p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="my_patients.php" class="btn btn-outline btn-sm" style="background:rgba(255,255,255,0.15);color:#ffffff;border:1px solid rgba(255,255,255,0.25);">
                <i class="fas fa-arrow-left"></i> My Patients
            </a>
            <?php if (!$is_completed): ?>
                <button onclick="manualRefresh()" class="btn btn-outline btn-sm" id="refreshBtn" style="background:rgba(255,255,255,0.15);color:#ffffff;border:1px solid rgba(255,255,255,0.25);">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            <?php endif; ?>
            <a href="view_consultation_pdf.php?visit_id=<?= $visit_id ?>" class="btn btn-primary btn-sm" target="_blank" style="background:rgba(255,255,255,0.2);color:#ffffff;border:1px solid rgba(255,255,255,0.3);">
                <i class="fas fa-file-pdf"></i> View PDF
            </a>
        </div>
    </div>

    <!-- FLASH MESSAGE -->
    <?php if ($flash_message): ?>
        <div class="alert alert-<?= $flash_type ?>" id="alertMessage">
            <i class="fas <?= $flash_type === 'success' ? 'fa-check-circle' : ($flash_type === 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle') ?>"></i>
            <div><?= $flash_message ?></div>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- STATUS FLOW INDICATOR -->
    <!-- ================================================================ -->
    <div class="consultation-card mb-6">
        <div class="status-flow">
            <span class="status-step <?= in_array($visit_status, ['assigned', 'lab_test', 'prescribed', 'waiting', 'completed']) ? 'completed' : '' ?>">
                <i class="fas fa-user-md"></i> Assigned
            </span>
            <span class="status-arrow">→</span>
            <span class="status-step <?= in_array($visit_status, ['lab_test', 'prescribed', 'waiting', 'completed']) ? ($visit_status === 'lab_test' ? 'active' : 'completed') : '' ?>">
                <i class="fas fa-flask"></i> Lab Test
            </span>
            <span class="status-arrow">→</span>
            <span class="status-step <?= in_array($visit_status, ['prescribed', 'waiting', 'completed']) ? ($visit_status === 'prescribed' ? 'active' : 'completed') : '' ?>">
                <i class="fas fa-prescription"></i> Prescribed
            </span>
            <span class="status-arrow">→</span>
            <span class="status-step <?= in_array($visit_status, ['waiting', 'completed']) ? ($visit_status === 'waiting' ? 'active' : 'completed') : '' ?>">
                <i class="fas fa-clock"></i> Waiting
            </span>
            <span class="status-arrow">→</span>
            <span class="status-step <?= $visit_status === 'completed' ? 'active' : '' ?>">
                <i class="fas fa-check-circle"></i> Completed
            </span>
        </div>
        <div style="margin-top:8px;font-size:0.7rem;color:var(--text-secondary);">
            <i class="fas fa-info-circle"></i>
            <?php if ($visit_status === 'assigned'): ?>
                Complete consultation and send lab tests to proceed.
            <?php elseif ($visit_status === 'lab_test'): ?>
                Lab tests in progress. Results will auto-update to PRESCRIBED.
            <?php elseif ($visit_status === 'prescribed'): ?>
                ✅ Lab results complete! Add medications, procedures, equipment then click <strong>"Save Consultation"</strong> to change to WAITING.
            <?php elseif ($visit_status === 'waiting'): ?>
                Consultation saved. Waiting for payment to complete.
            <?php elseif ($visit_status === 'completed'): ?>
                ✅ Consultation completed successfully!
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- BILL SUMMARY - 4 CARDS -->
    <!-- ================================================================ -->
    <div class="consultation-card mb-6">
        <h3 class="card-title"><i class="fas fa-receipt"></i> Bill Summary</h3>
        <div class="bill-summary-grid" id="billSummaryGrid">
            <div class="bill-summary-card total-card">
                <div class="bill-summary-icon"><i class="fas fa-file-invoice"></i></div>
                <div class="bill-summary-content">
                    <span class="bill-summary-label">Total Amount</span>
                    <span class="bill-summary-value" id="totalAmountDisplay">TSh <?= number_format($bill_subtotal, 0) ?></span>
                </div>
            </div>
            <div class="bill-summary-card paid-card">
                <div class="bill-summary-icon"><i class="fas fa-check-circle"></i></div>
                <div class="bill-summary-content">
                    <span class="bill-summary-label">Paid Amount</span>
                    <span class="bill-summary-value" id="paidAmountDisplay">TSh <?= number_format($bill_paid, 0) ?></span>
                </div>
            </div>
            <div class="bill-summary-card remaining-card <?= ($bill_balance) <= 0 ? 'zero-balance' : '' ?>">
                <div class="bill-summary-icon"><i class="fas <?= ($bill_balance) > 0 ? 'fa-exclamation-triangle' : 'fa-check-circle' ?>"></i></div>
                <div class="bill-summary-content">
                    <span class="bill-summary-label">Remaining</span>
                    <span class="bill-summary-value" id="remainingAmountDisplay">TSh <?= number_format($bill_balance, 0) ?></span>
                </div>
            </div>
            <div class="bill-summary-card discount-card">
                <div class="bill-summary-icon"><i class="fas fa-percent"></i></div>
                <div class="bill-summary-content">
                    <span class="bill-summary-label">Total Discount</span>
                    <span class="bill-summary-value" id="discountAmountDisplay">TSh <?= number_format($total_discount, 0) ?></span>
                </div>
            </div>
        </div>
        
        <div style="margin-top:12px;padding:10px 16px;background:var(--gray-50);border-radius:var(--radius);border:1px solid var(--border-color);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
            <span style="font-size:0.8rem;font-weight:500;color:var(--text-secondary);">
                <i class="fas fa-info-circle"></i> Bill Status:
                <span class="status-badge <?= getStatusBadgeClass($bill_status) ?>" id="billStatusBadge">
                    <?php if ($bill_status === 'paid'): ?>
                        ✅ FULLY PAID
                    <?php elseif ($bill_status === 'partial'): ?>
                        🔄 PARTIAL
                    <?php else: ?>
                        <?= ucfirst($bill_status) ?>
                    <?php endif; ?>
                </span>
                <?php if ($bill_balance <= 0 && $bill_status === 'paid'): ?>
                    <span style="color:var(--success);font-weight:600;margin-left:8px;">🎯 Balance is ZERO</span>
                <?php endif; ?>
                <?php if ($total_discount > 0): ?>
                    <span style="color:var(--purple);font-weight:500;margin-left:8px;">💳 Discount: TSh <?= number_format($total_discount, 0) ?></span>
                <?php endif; ?>
            </span>
            <span style="font-size:0.75rem;color:var(--text-secondary);">
                <i class="far fa-clock"></i> Last updated: <span id="billLastUpdated"><?= date('H:i:s') ?></span>
            </span>
            <span style="font-size:0.7rem;color:var(--text-secondary);" id="autoCompleteStatus">
                <?php if ($is_waiting && $bill_balance <= 0 && !empty($visit['diagnosis'])): ?>
                    <span style="color:var(--success);">⏳ Status is WAITING, diagnosis exists, and balance is 0. Auto-complete in 3 seconds...</span>
                <?php elseif ($is_waiting && $bill_balance <= 0 && empty($visit['diagnosis'])): ?>
                    <span style="color:var(--danger);">❌ Status is WAITING and balance is 0, but NO diagnosis! Please enter diagnosis first.</span>
                <?php elseif ($is_waiting && $bill_balance > 0): ?>
                    <span style="color:var(--warning);">⏳ Status is WAITING. Waiting for payment. Balance: TSh <?= number_format($bill_balance, 0) ?></span>
                <?php elseif ($visit_status === 'prescribed'): ?>
                    <span style="color:var(--purple);">💊 Status is PRESCRIBED. Click <strong>"Save Consultation"</strong> to change to WAITING</span>
                <?php elseif ($visit_status === 'lab_test'): ?>
                    <span style="color:var(--primary);">🧪 Status is LAB TEST. Waiting for results</span>
                <?php elseif ($visit_status === 'assigned'): ?>
                    <span style="color:var(--warning);">⏳ Status is ASSIGNED. Complete consultation and send lab tests</span>
                <?php elseif ($visit_status === 'completed'): ?>
                    <span style="color:var(--success);">✅ Consultation completed!</span>
                <?php endif; ?>
            </span>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PATIENT & VISIT INFO -->
    <!-- ================================================================ -->
    <div class="row-2col mb-6">
        <div class="consultation-card">
            <h3 class="card-title"><i class="fas fa-user"></i> Patient Information</h3>
            <div class="patient-info-block">
                <div class="patient-avatar" style="background:<?= getUserColor($visit['patient_name'] ?? 'Unknown') ?>;">
                    <?= strtoupper(substr($visit['patient_name'] ?? 'U', 0, 1)) ?>
                </div>
                <div class="patient-info-details">
                    <h4><?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?></h4>
                    <p>ID: <?= htmlspecialchars($visit['patient_code'] ?? 'N/A') ?></p>
                    <p><?= htmlspecialchars($visit['gender'] ?? 'N/A') ?> • <?= calculateAge($visit['date_of_birth'] ?? '') ?> years</p>
                </div>
            </div>
            <div class="patient-info-grid">
                <div class="info-item"><span>Date of Birth</span><span><?= !empty($visit['date_of_birth']) && $visit['date_of_birth'] !== '0000-00-00' ? date('M d, Y', strtotime($visit['date_of_birth'])) : 'N/A' ?></span></div>
                <div class="info-item"><span>Phone</span><span><?= htmlspecialchars($visit['phone'] ?? 'N/A') ?></span></div>
                <div class="info-item"><span>Blood Group</span><span><?= htmlspecialchars($visit['blood_group'] ?? 'N/A') ?></span></div>
                <div class="info-item"><span>Allergies</span><span><?= htmlspecialchars($visit['allergies'] ?? 'None') ?></span></div>
                <div class="col-span-2 info-item"><span>Address</span><span><?= htmlspecialchars($visit['address'] ?? 'N/A') ?></span></div>
            </div>
        </div>

        <div class="consultation-card">
            <h3 class="card-title"><i class="fas fa-clinic-medical"></i> Visit Information</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px 20px;">
                <div><span style="display:block;font-size:0.65rem;color:var(--text-secondary);font-weight:500;text-transform:uppercase;letter-spacing:0.05em;">Visit Number</span><span style="display:block;font-size:0.9rem;font-weight:500;color:var(--text-primary);font-family:monospace;"><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></span></div>
                <div><span style="display:block;font-size:0.65rem;color:var(--text-secondary);font-weight:500;text-transform:uppercase;letter-spacing:0.05em;">Visit Type</span><span style="display:block;font-size:0.9rem;font-weight:500;color:var(--text-primary);"><?= ucfirst($visit['visit_type'] ?? 'New') ?></span></div>
                <div><span style="display:block;font-size:0.65rem;color:var(--text-secondary);font-weight:500;text-transform:uppercase;letter-spacing:0.05em;">Date</span><span style="display:block;font-size:0.9rem;font-weight:500;color:var(--text-primary);"><?= date('M d, Y', strtotime($visit['created_at'] ?? 'now')) ?></span></div>
                <div><span style="display:block;font-size:0.65rem;color:var(--text-secondary);font-weight:500;text-transform:uppercase;letter-spacing:0.05em;">Doctor</span><span style="display:block;font-size:0.9rem;font-weight:500;color:var(--text-primary);">Dr. <?= htmlspecialchars($visit['doctor_name'] ?? 'Not Assigned') ?></span></div>
                <div><span style="display:block;font-size:0.65rem;color:var(--text-secondary);font-weight:500;text-transform:uppercase;letter-spacing:0.05em;">Specialty</span><span style="display:block;font-size:0.9rem;font-weight:500;color:var(--text-primary);"><?= htmlspecialchars($visit['doctor_specialty'] ?? 'N/A') ?></span></div>
                <div><span style="display:block;font-size:0.65rem;color:var(--text-secondary);font-weight:500;text-transform:uppercase;letter-spacing:0.05em;">Branch</span><span style="display:block;font-size:0.9rem;font-weight:500;color:var(--text-primary);"><?= htmlspecialchars($visit['branch_name'] ?? $doctor_branch_name) ?></span></div>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- VITAL SIGNS -->
    <!-- ================================================================ -->
    <div class="consultation-card mb-6">
        <h3 class="card-title"><i class="fas fa-heartbeat"></i> Vital Signs</h3>
        <?php if ($vital_signs): ?>
            <div class="vital-signs-grid">
                <div class="vital-sign-item temp-item">
                    <span class="vital-icon">🌡️</span>
                    <span class="vital-label">Temperature</span>
                    <span class="vital-value"><?= $vital_signs['temperature'] ?? '--' ?> <span class="vital-unit">°C</span></span>
                </div>
                <div class="vital-sign-item bp-item">
                    <span class="vital-icon">💓</span>
                    <span class="vital-label">Blood Pressure</span>
                    <span class="vital-value"><?= ($vital_signs['blood_pressure_systolic'] ?? '--') . '/' . ($vital_signs['blood_pressure_diastolic'] ?? '--') ?> <span class="vital-unit">mmHg</span></span>
                </div>
                <div class="vital-sign-item pulse-item">
                    <span class="vital-icon">💓</span>
                    <span class="vital-label">Pulse Rate</span>
                    <span class="vital-value"><?= $vital_signs['pulse_rate'] ?? '--' ?> <span class="vital-unit">bpm</span></span>
                </div>
                <div class="vital-sign-item weight-item">
                    <span class="vital-icon">⚖️</span>
                    <span class="vital-label">Weight</span>
                    <span class="vital-value"><?= $vital_signs['weight'] ?? '--' ?> <span class="vital-unit">kg</span></span>
                </div>
                <div class="vital-sign-item height-item">
                    <span class="vital-icon">📏</span>
                    <span class="vital-label">Height</span>
                    <span class="vital-value"><?= $vital_signs['height'] ?? '--' ?> <span class="vital-unit">cm</span></span>
                </div>
                <div class="vital-sign-item bmi-item">
                    <span class="vital-icon">📊</span>
                    <span class="vital-label">BMI</span>
                    <span class="vital-value"><?= $vital_signs['bmi'] ?? '--' ?> <span class="vital-unit">kg/m²</span></span>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state"><i class="fas fa-heartbeat"></i><p>No vital signs recorded</p></div>
        <?php endif; ?>
    </div>

    <?php if (!$is_completed): ?>
    
    <!-- ================================================================ -->
    <!-- START OF FORM -->
    <!-- ================================================================ -->
    <form method="POST" action="consultation.php?visit_id=<?= $visit_id ?>" id="consultationForm">
    
    <!-- Symptoms & History -->
    <div class="consultation-card mb-6">
        <h3 class="card-title">
            <i class="fas fa-list-ul"></i> Chief Complaint & History
            <?php if ($sections_frozen && !$is_waiting): ?>
                <span class="frozen-badge">🔒 Frozen - Lab Pending</span>
            <?php elseif ($lab_results_available && !$is_waiting): ?>
                <span class="frozen-badge success">✅ Results Available - Unlocked</span>
            <?php endif; ?>
        </h3>
        
        <div class="form-group">
            <label class="form-label">Chief Complaint <span class="required">*</span></label>
            <select class="form-control" id="complaintSelect" onchange="addComplaintOnSelect()">
                <option value="">-- Select Common Complaint --</option>
                <?php foreach ($common_complaints as $complaint): ?>
                    <option value="<?= htmlspecialchars($complaint) ?>"><?= htmlspecialchars($complaint) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="mt-2">
                <textarea name="symptoms" class="form-control" rows="3" 
                          placeholder="Complaints will appear here when you select from dropdown. Or type manually..."
                          id="symptomsTextarea"
                          <?= $sections_frozen && !$is_waiting ? 'disabled' : '' ?>
                          oninput="updateComplaints()"><?= htmlspecialchars($visit['symptoms'] ?? '') ?></textarea>
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label">History of Presenting Illness (HPI)</label>
            <textarea name="hpi" class="form-control" rows="3" 
                      placeholder="Describe the history of presenting illness..."
                      <?= $sections_frozen && !$is_waiting ? 'disabled' : '' ?>
                      id="hpiTextarea"><?= htmlspecialchars($visit['hpi'] ?? '') ?></textarea>
        </div>
        
        <div class="form-group">
            <label class="form-label">Physical Examination</label>
            <textarea name="physical_exam" class="form-control" rows="3" 
                      placeholder="Describe physical examination findings..."
                      <?= $sections_frozen && !$is_waiting ? 'disabled' : '' ?>
                      id="physicalExamTextarea"><?= htmlspecialchars($visit['physical_exam'] ?? '') ?></textarea>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- LAB TESTS SECTION -->
    <!-- ================================================================ -->
    <div class="consultation-card mb-6" id="labTestsCard">
        <h3 class="card-title">
            <i class="fas fa-flask"></i> Laboratory Tests
            <span class="frozen-badge" id="pendingLabBadge" style="<?= $has_active_lab && !$is_completed ? '' : 'display:none;' ?>">⏳ <span id="pendingLabCount"><?= count($lab_requests) ?></span> Active</span>
            <span class="section-total" id="labSectionTotal">
                <span class="label">🧪 Total:</span>
                <span class="amount">TSh <span id="labTotalDisplay"><?= number_format($lab_total, 0) ?></span></span>
            </span>
            <span class="section-total orange" id="labCartTotalSection" style="<?= $lab_cart_count > 0 ? '' : 'display:none;' ?>">
                <span class="label">🛒 Cart:</span>
                <span class="amount">TSh <span id="labCartTotalDisplay"><?= number_format($lab_cart_total, 0) ?></span></span>
                <span class="amount" style="font-size:0.6rem;opacity:0.8;">(<span id="labCartCountDisplay"><?= $lab_cart_count ?></span> items)</span>
            </span>
        </h3>
        
        <div class="alert alert-info" style="margin-bottom:16px;">
            <i class="fas fa-info-circle"></i>
            <strong>Flow:</strong> Select lab tests → Add to Cart → Send All to Laboratory
            <br><small>Note: Lab tests linked with equipment are FREE (equipment not charged)</small>
        </div>
        
        <div style="display:flex;gap:10px;margin-bottom:12px;align-items:center;flex-wrap:wrap;">
            <select class="form-control" style="flex:1;min-width:200px;" id="labTestSelect">
                <option value="">-- Select Lab Test --</option>
                <?php foreach ($lab_tests_catalog as $test): ?>
                    <option value="<?= $test['id'] ?>" 
                            data-price="<?= $test['price'] ?>"
                            data-equipment-id="<?= $test['equipment_id'] ?? '' ?>"
                            data-equipment-name="<?= htmlspecialchars($test['linked_equipment_name'] ?? '') ?>"
                            data-equipment-used="<?= $test['equipment_quantity_used'] ?? 1 ?>">
                        <?= htmlspecialchars($test['test_name']) ?>
                        <?php if (!empty($test['category'])): ?>
                            (<?= htmlspecialchars($test['category']) ?>)
                        <?php endif; ?>
                        - TSh <?= number_format($test['price'], 0) ?>
                        <?php if (!empty($test['linked_equipment_name'])): ?>
                            🔗 <span style="color:var(--success);">FREE Equipment: <?= htmlspecialchars($test['linked_equipment_name']) ?></span>
                        <?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="btn btn-primary" onclick="addLabTestToCart()" id="addLabToCartBtn">
                <i class="fas fa-cart-plus"></i> Add to Cart
            </button>
        </div>
        
        <div style="background:var(--gray-50);border-radius:var(--radius);padding:16px;border:1px solid var(--border-color);">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                <h4 style="font-size:0.875rem;font-weight:600;color:var(--text-secondary);margin:0;">
                    <i class="fas fa-shopping-cart"></i> Lab Cart
                    <span style="font-size:0.75rem;color:var(--text-secondary);" id="labCartCount">(<?= $lab_cart_count ?> items)</span>
                </h4>
                <span style="font-size:0.875rem;font-weight:700;color:#D97706;">Total: TSh <span id="labCartTotal"><?= number_format($lab_cart_total, 0) ?></span></span>
            </div>
            <div class="lab-cart-items" id="labCartItems">
                <?php if ($lab_cart_count > 0): ?>
                    <?php foreach ($lab_cart as $item): ?>
                        <div class="lab-cart-item" id="lab-cart-<?= $item['id'] ?>" data-test-id="<?= $item['id'] ?>">
                            <span class="cart-item-name"><?= htmlspecialchars($item['name']) ?>
                                <?php if (!empty($item['linked_equipment_name'])): ?>
                                    <span style="font-size:0.6rem;color:var(--success);margin-left:4px;">🔗 FREE: <?= htmlspecialchars($item['linked_equipment_name']) ?></span>
                                <?php endif; ?>
                            </span>
                            <span class="cart-item-price">TSh <?= number_format($item['price'], 0) ?></span>
                            <button type="button" class="btn-remove-cart" onclick="removeLabTestFromCart(<?= $item['id'] ?>)">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="lab-cart-empty">No tests in cart. Add tests above.</div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="mt-3 flex flex-wrap gap-3">
            <button type="submit" name="send_lab" class="btn btn-warning" id="sendLabBtn" <?= ($lab_cart_count == 0) ? 'disabled' : '' ?>>
                <i class="fas fa-paper-plane"></i> Send All to Laboratory (<span id="sendLabCount"><?= $lab_cart_count ?></span> tests)
            </button>
            <button type="button" class="btn btn-outline btn-sm" onclick="clearLabCart()">
                <i class="fas fa-times"></i> Clear Cart
            </button>
            <span style="font-size:0.75rem;color:var(--text-secondary);align-self:center;" id="labCartStatus"><?= $lab_cart_count > 0 ? 'Ready to send ' . $lab_cart_count . ' test(s)' : 'Add tests to cart first' ?></span>
        </div>
        
        <div class="mt-3" id="sentTestsContainer">
            <?php if (count($lab_requests) > 0): ?>
                <h5 style="font-size:0.875rem;font-weight:600;color:var(--text-secondary);margin-bottom:8px;"><i class="fas fa-history"></i> Sent Tests</h5>
                <div id="sentTestsList">
                    <?php foreach ($lab_requests as $lab): ?>
                        <div style="display:flex;gap:10px;margin-bottom:6px;align-items:center;padding:6px 12px;background:var(--gray-50);border-radius:var(--radius);border:1px solid var(--border-color);" id="sent-test-<?= $lab['id'] ?>">
                            <div style="flex:1;">
                                <span style="font-weight:500;font-size:0.85rem;color:var(--text-primary);"><?= htmlspecialchars($lab['test_name']) ?></span>
                                <?php if (!empty($lab['linked_equipment_name'])): ?>
                                    <span style="font-size:0.6rem;color:var(--success);margin-left:4px;">🔗 FREE: <?= htmlspecialchars($lab['linked_equipment_name']) ?></span>
                                <?php endif; ?>
                                <span style="font-size:0.75rem;color:var(--text-secondary);margin-left:8px;">- TSh <?= number_format($lab['test_price'] ?? 0, 0) ?></span>
                            </div>
                            <span class="status-badge badge-warning" style="font-size:0.6rem;" id="sent-status-<?= $lab['id'] ?>">⏳ <?= ucfirst($lab['status'] ?? 'Pending') ?></span>
                            <?php if ($lab['status'] !== 'completed'): ?>
                                <button type="button" class="btn-remove-cart" onclick="removeLabTest(<?= $lab['id'] ?>)" title="Remove test">
                                    <i class="fas fa-times"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- LAB RESULTS -->
    <!-- ================================================================ -->
    <div class="consultation-card mb-6 <?= $lab_results_available ? 'border-green-500' : '' ?>" id="labResultsCard">
        <h3 class="card-title">
            <i class="fas fa-file-medical-alt"></i> Laboratory Results
            <span class="frozen-badge <?= $lab_results_available ? 'success' : '' ?>" id="resultsBadge" style="<?= ($lab_results_available || $has_active_lab) ? '' : 'display:none;' ?>">
                <?php if ($lab_results_available): ?>
                    ✅ Results Available
                <?php elseif ($has_active_lab): ?>
                    ⏳ Pending Results
                <?php endif; ?>
            </span>
            <span style="font-size:0.875rem;font-weight:400;color:var(--text-secondary);margin-left:8px;" id="resultsCount" style="<?= $lab_results_available ? '' : 'display:none;' ?>">(<?= count($lab_results) ?> results)</span>
            <span style="font-size:0.75rem;color:var(--text-secondary);" id="resultsUpdateTime">⏱ Auto-update</span>
        </h3>
        
        <div id="labResultsContainer">
            <?php if ($lab_results_available): ?>
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:0.85rem;">
                        <thead><tr>
                            <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);border-bottom:2px solid var(--border-color);">Test Name</th>
                            <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);border-bottom:2px solid var(--border-color);">Result</th>
                            <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);border-bottom:2px solid var(--border-color);">Status</th>
                        </tr></thead>
                        <tbody id="labResultsBody">
                            <?php foreach ($lab_results as $result): ?>
                                <tr>
                                    <td style="padding:10px 14px;border-bottom:1px solid var(--border-color);color:var(--text-primary);"><?= htmlspecialchars($result['test_name'] ?? 'N/A') ?></td>
                                    <td style="padding:10px 14px;border-bottom:1px solid var(--border-color);color:var(--text-primary);font-weight:600;color:var(--success);"><?= htmlspecialchars($result['results'] ?? 'N/A') ?></td>
                                    <td style="padding:10px 14px;border-bottom:1px solid var(--border-color);color:var(--text-primary);"><span class="status-badge badge-success">✅ Completed</span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3 text-sm text-green-600">
                    <i class="fas fa-check-circle"></i> Lab results available. Status updated to PRESCRIBED.
                </div>
            <?php elseif ($has_active_lab): ?>
                <div style="text-align:center;padding:24px 0;color:#D97706;" id="labPendingMessage">
                    <i class="fas fa-clock" style="font-size:1.5rem;display:block;margin-bottom:8px;"></i>
                    <p id="pendingCountDisplay"><?= count($lab_requests) ?> lab request(s) in progress</p>
                    <p style="font-size:0.75rem;color:var(--text-secondary);margin-top:4px;">⏳ Waiting for Laboratory to complete tests</p>
                    <?php if ($sections_frozen && !$is_waiting): ?>
                        <div style="margin-top:12px;font-size:0.875rem;color:var(--danger);">
                            <i class="fas fa-lock"></i> Diagnosis, Medications & Procedures are <strong>FROZEN</strong> until results are available
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div style="text-align:center;padding:24px 0;color:var(--text-secondary);" id="noLabResults">
                    <i class="fas fa-flask" style="font-size:1.5rem;display:block;margin-bottom:8px;"></i>
                    <p>No lab results available</p>
                    <p style="font-size:0.75rem;margin-top:4px;">Send lab requests to get results</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FROZEN SECTIONS CONTAINER -->
    <!-- ================================================================ -->
    <div id="frozenSectionsContainer" class="<?= ($sections_frozen && !$is_waiting) ? 'frozen-overlay-active' : ($lab_results_available ? 'results-available' : '') ?>">

        <!-- DIAGNOSIS -->
        <div class="consultation-card mb-6" id="diagnosisCard">
            <h3 class="card-title">
                <i class="fas fa-diagnoses"></i> Diagnosis
                <?php if ($sections_frozen && !$is_waiting): ?>
                    <span class="frozen-badge">🔒 Frozen - Lab Pending</span>
                <?php elseif ($lab_results_available && !$is_waiting): ?>
                    <span class="frozen-badge success">✅ Results Available - Unlocked</span>
                <?php endif; ?>
                <span id="diagnosisStatus" style="font-size:0.65rem;font-weight:400;color:var(--text-secondary);">
                    <?php if (!empty($visit['diagnosis'])): ?>
                        ✅ Saved: <?= htmlspecialchars($visit['diagnosis']) ?>
                    <?php else: ?>
                        ⚠️ Diagnosis required
                    <?php endif; ?>
                </span>
            </h3>
            
            <div class="form-group">
                <label class="form-label">Select Disease <span class="required">*</span></label>
                <select name="diagnosis_id" class="form-control" id="diagnosisSelect" 
                        <?= ($sections_frozen && !$is_waiting) ? 'disabled' : '' ?>>
                    <option value="">-- Select Disease --</option>
                    <?php foreach ($diseases_list as $disease): ?>
                        <option value="<?= $disease['id'] ?>" 
                                data-code="<?= htmlspecialchars($disease['disease_code'] ?? '') ?>"
                                data-treatment="<?= htmlspecialchars($disease['treatment'] ?? '') ?>"
                                data-name="<?= htmlspecialchars($disease['disease_name']) ?>"
                                <?= ($disease['id'] == ($visit['disease_id'] ?? 0)) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($disease['disease_name']) ?>
                            <?php if (!empty($disease['disease_code'])): ?>
                                (<?= htmlspecialchars($disease['disease_code']) ?>)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="__manual__">✏️ Manual Entry (Saves to database)</option>
                </select>
            </div>
            
            <div class="diagnosis-manual-box" id="manualDiagnosisBox" style="display:none;">
                <div class="form-group">
                    <label class="form-label">Disease Name <span class="required">*</span></label>
                    <input type="text" name="diagnosis_manual" class="form-control" 
                           placeholder="Enter disease name..." 
                           value="<?= htmlspecialchars($visit['diagnosis'] ?? '') ?>"
                           <?= ($sections_frozen && !$is_waiting) ? 'disabled' : '' ?>
                           id="diagnosisManualInput">
                </div>
                <div class="form-group">
                    <label class="form-label">Disease Code <span style="font-size:0.75rem;color:var(--text-secondary);">(Optional - Auto-generated if left blank)</span></label>
                    <input type="text" name="disease_code_manual" class="form-control" 
                           placeholder="e.g. D-ABC-001" 
                           value="<?= htmlspecialchars($visit['disease_code'] ?? '') ?>"
                           <?= ($sections_frozen && !$is_waiting) ? 'disabled' : '' ?>
                           id="diseaseCodeManual">
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Treatment Plan</label>
                <textarea name="treatment" class="form-control" rows="3" 
                          placeholder="Describe treatment plan..."
                          <?= ($sections_frozen && !$is_waiting) ? 'disabled' : '' ?>
                          id="treatmentTextarea"><?= htmlspecialchars($visit['treatment'] ?? '') ?></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label">Additional Notes</label>
                <textarea name="notes" class="form-control" rows="2" 
                          placeholder="Additional notes..."
                          <?= ($sections_frozen && !$is_waiting) ? 'disabled' : '' ?>
                          id="notesTextarea"><?= htmlspecialchars($visit['notes'] ?? '') ?></textarea>
            </div>
            
            <div id="diagnosisAutoSaveStatus" style="font-size:0.7rem;color:var(--text-secondary);margin-top:8px;display:flex;align-items:center;gap:8px;">
                <span id="diagnosisSaveIndicator" style="display:none;">
                    <i class="fas fa-spinner fa-spin"></i> Saving data...
                </span>
                <span id="diagnosisSavedIndicator" style="display:none;color:var(--success);">
                    <i class="fas fa-check-circle"></i> Data saved
                </span>
            </div>
        </div>

        <!-- MEDICATIONS -->
        <div class="consultation-card mb-6" id="medicationsCard">
            <h3 class="card-title">
                <i class="fas fa-prescription"></i> Medications
                <?php if ($sections_frozen && !$is_waiting): ?>
                    <span class="frozen-badge">🔒 Frozen - Lab Pending</span>
                <?php elseif ($lab_results_available && !$is_waiting): ?>
                    <span class="frozen-badge success">✅ Results Available - Unlocked</span>
                <?php endif; ?>
                <span class="section-total green">
                    <span class="label">💊 Total:</span>
                    <span class="amount">TSh <span id="medTotalDisplay"><?= number_format($medications_total, 0) ?></span></span>
                </span>
            </h3>
            
            <div style="background:var(--gray-50);border-radius:var(--radius);padding:20px;border:1px solid var(--border-color);">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group">
                        <label class="form-label">Medication <span class="required">*</span></label>
                        <select class="form-control" id="medicationSelect" <?= ($sections_frozen && !$is_waiting) ? 'disabled' : '' ?>>
                            <option value="">Select Medication...</option>
                            <?php foreach ($medications_grouped as $key => $group): 
                                $total_stock = $group['total_quantity'];
                                $batch_count = count($group['batches']);
                            ?>
                                <option value="<?= $group['batches'][0]['id'] ?>" 
                                        data-price="<?= $group['selling_price'] ?>" 
                                        data-stock="<?= $total_stock ?>" 
                                        data-batch-count="<?= $batch_count ?>"
                                        data-medication-name="<?= htmlspecialchars($group['name']) ?>"
                                        data-category="<?= htmlspecialchars($group['category']) ?>">
                                    <?= htmlspecialchars($group['name']) ?> 
                                    (<?= $total_stock ?> available across <?= $batch_count ?> batches) - TSh <?= number_format($group['selling_price'] ?? 0, 0) ?>
                                    <?php if (!empty($group['category'])): ?>
                                        [<?= htmlspecialchars($group['category']) ?>]
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div style="font-size:0.6rem;color:var(--text-secondary);margin-top:2px;">
                            <i class="fas fa-info-circle"></i> Medications are grouped by name and category. Stock shows total across all batches.
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Quantity</label>
                        <input type="number" id="medQuantity" class="form-control" value="1" min="1" max="999" <?= ($sections_frozen && !$is_waiting) ? 'disabled' : '' ?>>
                    </div>
                </div>
                
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px;">
                    <div class="form-group">
                        <label class="form-label">Dosage <span style="font-size:0.75rem;color:var(--text-secondary);">(e.g. 500mg)</span></label>
                        <input type="text" id="medDosage" class="form-control" placeholder="e.g. 500mg" <?= ($sections_frozen && !$is_waiting) ? 'disabled' : '' ?>>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Frequency</label>
                        <select id="medFrequency" class="form-control" <?= ($sections_frozen && !$is_waiting) ? 'disabled' : '' ?>>
                            <option value="">Select Frequency</option>
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
                            <option value="With Meals">With Meals</option>
                            <option value="At Bedtime">At Bedtime</option>
                            <option value="On Empty Stomach">On Empty Stomach</option>
                        </select>
                    </div>
                </div>
                
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px;">
                    <div class="form-group">
                        <label class="form-label">Duration (Days)</label>
                        <input type="number" id="medDuration" class="form-control" value="7" min="1" max="90" <?= ($sections_frozen && !$is_waiting) ? 'disabled' : '' ?>>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Route</label>
                        <select id="medRoute" class="form-control" <?= ($sections_frozen && !$is_waiting) ? 'disabled' : '' ?>>
                            <option value="">Select Route</option>
                            <option value="Oral">Oral</option>
                            <option value="Topical">Topical</option>
                            <option value="Injection">Injection</option>
                            <option value="IV">IV</option>
                            <option value="IM">IM</option>
                            <option value="Sublingual">Sublingual</option>
                            <option value="Inhalation">Inhalation</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group mt-3">
                    <label class="form-label">Instructions</label>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:6px;">
                        <button type="button" class="btn btn-outline btn-sm" onclick="addInstruction('Take after meals')">After Meals</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="addInstruction('Take before meals')">Before Meals</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="addInstruction('Take with plenty of water')">With Water</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="addInstruction('Take at bedtime')">At Bedtime</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="addInstruction('Do not crush or chew')">Do Not Crush</button>
                    </div>
                    <textarea id="medInstructions" class="form-control" rows="2" 
                              placeholder="e.g. Take after meals, with plenty of water..."
                              <?= ($sections_frozen && !$is_waiting) ? 'disabled' : '' ?>></textarea>
                </div>
                
                <div class="mt-3">
                    <button type="button" class="btn btn-primary" onclick="addMedicationAjax()" id="addMedicationBtn" <?= ($sections_frozen && !$is_waiting) ? 'disabled' : '' ?>>
                        <i class="fas fa-plus"></i> Add Medication
                    </button>
                    <?php if ($sections_frozen && !$is_waiting): ?>
                        <span style="font-size:0.75rem;color:var(--danger);margin-left:8px;"><i class="fas fa-lock"></i> Frozen until lab results</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="selected-medications mt-4" style="background:var(--gray-50);border-radius:var(--radius);padding:16px 20px;border:1px solid var(--border-color);">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                    <h4 style="font-size:0.875rem;font-weight:600;color:var(--text-secondary);margin:0;">
                        <i class="fas fa-list"></i> Prescribed Medications
                        <span style="font-size:0.75rem;color:var(--text-secondary);" id="medCount">(<?= count($prescriptions) ?> items)</span>
                    </h4>
                    <span style="font-size:0.875rem;font-weight:700;color:var(--success);">Total: TSh <span id="medListTotal"><?= number_format($medications_total, 0) ?></span></span>
                </div>
                <div id="medicationsList">
                    <?php if (count($prescriptions) > 0): ?>
                        <?php foreach ($prescriptions as $med): ?>
                            <div class="medication-item" id="med-item-<?= $med['id'] ?>">
                                <div class="medication-item-info">
                                    <span class="med-name"><?= htmlspecialchars($med['medication_name'] ?? 'Unknown') ?></span>
                                    <span class="med-details">
                                        <?= htmlspecialchars($med['dosage'] ?? '') ?> • 
                                        <?= htmlspecialchars($med['frequency'] ?? '') ?> • 
                                        <?= htmlspecialchars($med['duration'] ?? '') ?> days
                                    </span>
                                    <span class="med-qty">x<?= $med['quantity'] ?? 0 ?></span>
                                    <span class="med-price">TSh <?= number_format($med['total_price'] ?? 0, 0) ?></span>
                                    <?php if (!empty($med['instructions'])): ?>
                                        <span class="med-instruction-tag"><?= htmlspecialchars($med['instructions']) ?></span>
                                    <?php endif; ?>
                                    <?php if (($med['status'] ?? '') === 'dispensed'): ?>
                                        <span class="med-status-dispensed">✅ Dispensed</span>
                                    <?php else: ?>
                                        <span class="med-status-pending">⏳ Pending Dispense</span>
                                    <?php endif; ?>
                                </div>
                                <?php if (($med['status'] ?? '') !== 'dispensed'): ?>
                                    <button type="button" class="btn-remove" onclick="removeMedication(<?= $med['id'] ?>)" title="Remove medication">
                                        <i class="fas fa-times"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state" id="emptyMedications">
                            <i class="fas fa-prescription"></i>
                            <p>No medications prescribed yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- PROCEDURES & EQUIPMENT -->
        <div class="consultation-card mb-6" id="proceduresEquipmentCard">
            <h3 class="card-title">
                <i class="fas fa-syringe"></i> Procedures & Equipment
                <?php if ($sections_frozen && !$is_waiting): ?>
                    <span class="frozen-badge">🔒 Frozen - Lab Pending</span>
                <?php elseif ($lab_results_available && !$is_waiting): ?>
                    <span class="frozen-badge success">✅ Results Available - Unlocked</span>
                <?php endif; ?>
                <span class="section-total purple">
                    <span class="label">🛠️ Total:</span>
                    <span class="amount">TSh <span id="procEquipTotalDisplay"><?= number_format($procedure_total + $equipment_total, 0) ?></span></span>
                </span>
            </h3>
            
            <!-- PROCEDURES SECTION -->
            <div class="toggle-section">
                <div class="toggle-header" onclick="toggleSection('proceduresToggle')">
                    <span class="toggle-title">
                        <i class="fas fa-syringe"></i> Procedures
                        <span style="font-size:0.75rem;color:var(--text-secondary);">(Independent - Equipment not included)</span>
                    </span>
                    <span class="toggle-icon"><i class="fas fa-chevron-down"></i></span>
                </div>
                <div class="toggle-body" id="proceduresToggle">
                    <div class="items-grid" id="proceduresGrid">
                        <?php foreach ($procedures_list as $proc): ?>
                            <div class="procedure-item-select" 
                                 data-procedure-id="<?= $proc['id'] ?>"
                                 data-procedure-name="<?= htmlspecialchars($proc['procedure_name']) ?>"
                                 data-price="<?= $proc['price'] ?>"
                                 onclick="toggleProcedure(this)">
                                <span class="item-check"><i class="fas fa-check"></i></span>
                                <span><?= htmlspecialchars($proc['procedure_name']) ?></span>
                                <small style="font-size:0.75rem;<?= ($proc['price'] ?? 0) > 0 ? 'color:var(--text-secondary);' : 'color:var(--success);' ?>">
                                    <?= ($proc['price'] ?? 0) > 0 ? 'TSh ' . number_format($proc['price'], 0) : 'FREE' ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button type="button" class="btn btn-primary btn-sm" onclick="addSelectedProcedures()" <?= ($sections_frozen && !$is_waiting) ? 'disabled' : '' ?>>
                            <i class="fas fa-plus"></i> Add Selected Procedures
                        </button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="clearProcedureSelections()">
                            <i class="fas fa-times"></i> Clear Selection
                        </button>
                        <span style="font-size:0.75rem;color:var(--text-secondary);align-self:center;" id="procSelectedCount">Selected: 0</span>
                    </div>
                </div>
            </div>
            
            <!-- EQUIPMENT SECTION -->
            <div class="toggle-section">
                <div class="toggle-header" onclick="toggleSection('equipmentToggle')">
                    <span class="toggle-title">
                        <i class="fas fa-tools"></i> Medical Equipment
                        <span style="font-size:0.75rem;color:var(--text-secondary);">(Charged separately)</span>
                    </span>
                    <span class="toggle-icon"><i class="fas fa-chevron-down"></i></span>
                </div>
                <div class="toggle-body" id="equipmentToggle">
                    <div class="items-grid" id="equipmentGrid">
                        <?php foreach ($equipment_list as $eq): ?>
                            <div class="equipment-item-select" 
                                 data-equipment-id="<?= $eq['id'] ?>"
                                 data-equipment-name="<?= htmlspecialchars($eq['equipment_name']) ?>"
                                 data-price="<?= $eq['selling_price'] ?? 0 ?>"
                                 data-stock="<?= $eq['quantity'] ?>"
                                 data-batch="<?= htmlspecialchars($eq['batch_number'] ?? '') ?>"
                                 data-expiry="<?= $eq['expiry_date'] ?? '' ?>"
                                 onclick="toggleEquipment(this)">
                                <span class="item-check"><i class="fas fa-check"></i></span>
                                <span><?= htmlspecialchars($eq['equipment_name']) ?></span>
                                <small style="font-size:0.75rem;<?= ($eq['selling_price'] ?? 0) > 0 ? 'color:var(--text-secondary);' : 'color:var(--success);' ?>">
                                    <?= ($eq['selling_price'] ?? 0) > 0 ? 'TSh ' . number_format($eq['selling_price'], 0) : 'FREE' ?>
                                </small>
                                <small style="font-size:0.75rem;color:var(--text-secondary);">Stock: <?= $eq['quantity'] ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-2 flex flex-wrap gap-2 items-center">
                        <div style="display:flex;gap:8px;align-items:center;">
                            <label style="font-size:0.75rem;color:var(--text-secondary);">Qty:</label>
                            <input type="number" id="equipmentQuantity" class="form-control" value="1" min="1" style="width:80px;" <?= ($sections_frozen && !$is_waiting) ? 'disabled' : '' ?>>
                        </div>
                        <button type="button" class="btn btn-primary btn-sm" onclick="addSelectedEquipment()" <?= ($sections_frozen && !$is_waiting) ? 'disabled' : '' ?>>
                            <i class="fas fa-plus"></i> Add Selected Equipment
                        </button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="clearEquipmentSelections()">
                            <i class="fas fa-times"></i> Clear Selection
                        </button>
                        <span style="font-size:0.75rem;color:var(--text-secondary);align-self:center;" id="equipSelectedCount">Selected: 0</span>
                    </div>
                </div>
            </div>
            
            <!-- ADDED ITEMS DISPLAY -->
            <div class="selected-items mt-4" style="background:var(--gray-50);border-radius:var(--radius);padding:16px 20px;border:1px solid var(--border-color);">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                    <h4 style="font-size:0.875rem;font-weight:600;color:var(--text-secondary);margin:0;">
                        <i class="fas fa-list"></i> Added Procedures & Equipment
                        <span style="font-size:0.75rem;color:var(--text-secondary);" id="addedCount">(<?= count($procedures) + count($equipment_items_display) ?> items)</span>
                    </h4>
                    <span style="font-size:0.875rem;font-weight:700;color:#7C3AED;">Total: TSh <span id="addedTotal"><?= number_format($procedure_total + $equipment_total, 0) ?></span></span>
                </div>
                <div id="addedItemsList">
                    <?php if (count($procedures) > 0 || count($equipment_items_display) > 0): ?>
                        <?php foreach ($procedures as $proc): ?>
                            <div class="added-item-card" id="added-procedure-<?= $proc['id'] ?>">
                                <div class="item-left">
                                    <span class="item-name"><?= htmlspecialchars($proc['procedure_name']) ?></span>
                                    <span class="item-details">| Qty: 1</span>
                                    <?php if ($proc['procedure_price'] > 0): ?>
                                        <span class="item-price">TSh <?= number_format($proc['procedure_price'], 0) ?></span>
                                    <?php else: ?>
                                        <span class="item-free">FREE</span>
                                    <?php endif; ?>
                                </div>
                                <div class="item-right">
                                    <button type="button" class="btn-remove-item" onclick="removeAddedItem('procedure', <?= $proc['id'] ?>)" title="Remove item">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php foreach ($equipment_items_display as $eq_item): ?>
                            <div class="added-item-card" id="added-equipment-<?= $eq_item['id'] ?>">
                                <div class="item-left">
                                    <span class="item-name"><?= htmlspecialchars($eq_item['item_name']) ?></span>
                                    <span class="item-details">| Qty: <?= $eq_item['quantity'] ?></span>
                                    <?php if ($eq_item['total_price'] > 0): ?>
                                        <span class="item-price">TSh <?= number_format($eq_item['total_price'], 0) ?></span>
                                    <?php else: ?>
                                        <span class="item-free">FREE</span>
                                    <?php endif; ?>
                                </div>
                                <div class="item-right">
                                    <button type="button" class="btn-remove-item" onclick="removeAddedItem('equipment', <?= $eq_item['id'] ?>)" title="Remove item">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state" id="emptyAdded">
                            <i class="fas fa-syringe"></i>
                            <p>No procedures or equipment added yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
    <!-- END FROZEN SECTIONS CONTAINER -->

    <!-- ================================================================ -->
    <!-- FORM ACTIONS -->
    <!-- ================================================================ -->
    <div class="consultation-card">
        <div class="form-actions">
            <button type="submit" name="save_consultation" class="btn btn-success" id="saveConsultationBtn" 
                    <?= ($sections_frozen && !$is_waiting) ? 'disabled' : '' ?>>
                <i class="fas fa-save"></i> Save Consultation → Change to WAITING
            </button>
            <?php if ($sections_frozen && !$is_waiting): ?>
                <span style="font-size:0.75rem;color:var(--danger);align-self:center;" id="frozenActionsMessage">
                    <i class="fas fa-lock"></i> Actions frozen - Lab tests pending
                </span>
            <?php elseif ($lab_results_available || $is_waiting): ?>
                <span style="font-size:0.75rem;color:var(--success);align-self:center;" id="frozenActionsMessage">
                    <i class="fas fa-check-circle"></i> <?= $is_waiting ? 'Consultation saved - Waiting for payment' : 'Lab results available - All actions unlocked' ?>
                </span>
            <?php endif; ?>
            <a href="my_patients.php" class="btn btn-outline">
                <i class="fas fa-times"></i> Cancel
            </a>
        </div>
    </div>

    </form>

    <?php else: ?>
    
    <!-- ================================================================ -->
    <!-- COMPLETED CONSULTATION - READ ONLY -->
    <!-- ================================================================ -->
    <div class="consultation-card mb-6">
        <h3 class="card-title"><i class="fas fa-list-ul"></i> Chief Complaint & History</h3>
        <div class="form-group">
            <label class="form-label">Chief Complaint</label>
            <div class="form-control" style="min-height:60px;background:var(--gray-50);"><?= nl2br(htmlspecialchars($visit['symptoms'] ?? 'No complaint recorded')) ?></div>
        </div>
        <div class="form-group">
            <label class="form-label">HPI</label>
            <div class="form-control" style="min-height:60px;background:var(--gray-50);"><?= nl2br(htmlspecialchars($visit['hpi'] ?? 'No HPI recorded')) ?></div>
        </div>
        <div class="form-group">
            <label class="form-label">Physical Examination</label>
            <div class="form-control" style="min-height:60px;background:var(--gray-50);"><?= nl2br(htmlspecialchars($visit['physical_exam'] ?? 'No physical exam recorded')) ?></div>
        </div>
    </div>

    <div class="consultation-card mb-6">
        <h3 class="card-title"><i class="fas fa-flask"></i> Laboratory Tests & Results</h3>
        <?php if ($lab_results_available): ?>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:0.85rem;">
                    <thead><tr>
                        <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);border-bottom:2px solid var(--border-color);">Test Name</th>
                        <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);border-bottom:2px solid var(--border-color);">Result</th>
                        <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);border-bottom:2px solid var(--border-color);">Status</th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($lab_results as $result): ?>
                            <tr>
                                <td style="padding:10px 14px;border-bottom:1px solid var(--border-color);color:var(--text-primary);"><?= htmlspecialchars($result['test_name'] ?? 'N/A') ?></td>
                                <td style="padding:10px 14px;border-bottom:1px solid var(--border-color);color:var(--text-primary);font-weight:600;color:var(--success);"><?= htmlspecialchars($result['results'] ?? 'N/A') ?></td>
                                <td style="padding:10px 14px;border-bottom:1px solid var(--border-color);color:var(--text-primary);"><span class="status-badge badge-success">✅ Completed</span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state"><i class="fas fa-flask"></i><p>No lab tests found</p></div>
        <?php endif; ?>
    </div>

    <div class="consultation-card mb-6">
        <h3 class="card-title"><i class="fas fa-diagnoses"></i> Diagnosis</h3>
        <?php if (!empty($visit['diagnosis']) || !empty($visit['disease_name'])): ?>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div>
                    <label class="form-label">Diagnosis</label>
                    <div class="form-control" style="background:var(--gray-50);font-weight:600;color:var(--success);">
                        <?= htmlspecialchars($visit['diagnosis'] ?? $visit['disease_name'] ?? 'N/A') ?>
                    </div>
                </div>
                <div>
                    <label class="form-label">Disease Code</label>
                    <div class="form-control" style="background:var(--gray-50);font-family:monospace;">
                        <?= htmlspecialchars($visit['disease_code'] ?? 'N/A') ?>
                    </div>
                </div>
            </div>
            <div class="form-group mt-3">
                <label class="form-label">Treatment</label>
                <div class="form-control" style="min-height:60px;background:var(--gray-50);">
                    <?= nl2br(htmlspecialchars($visit['treatment'] ?? 'No treatment recorded')) ?>
                </div>
            </div>
            <div class="form-group mt-3">
                <label class="form-label">Notes</label>
                <div class="form-control" style="min-height:60px;background:var(--gray-50);">
                    <?= nl2br(htmlspecialchars($visit['notes'] ?? 'No notes recorded')) ?>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state"><i class="fas fa-diagnoses"></i><p>No diagnosis recorded</p></div>
        <?php endif; ?>
    </div>

    <div class="consultation-card mb-6">
        <h3 class="card-title"><i class="fas fa-prescription"></i> Prescriptions & Medications</h3>
        <?php if (count($prescriptions) > 0): ?>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:0.85rem;">
                    <thead><tr>
                        <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);border-bottom:2px solid var(--border-color);">Medication</th>
                        <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);border-bottom:2px solid var(--border-color);">Dosage</th>
                        <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);border-bottom:2px solid var(--border-color);">Qty</th>
                        <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);border-bottom:2px solid var(--border-color);">Status</th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($prescriptions as $med): ?>
                            <tr>
                                <td style="padding:10px 14px;border-bottom:1px solid var(--border-color);color:var(--text-primary);"><?= htmlspecialchars($med['medication_name'] ?? 'N/A') ?></td>
                                <td style="padding:10px 14px;border-bottom:1px solid var(--border-color);color:var(--text-primary);"><?= htmlspecialchars($med['dosage'] ?? '') ?></td>
                                <td style="padding:10px 14px;border-bottom:1px solid var(--border-color);color:var(--text-primary);"><?= $med['quantity'] ?? 0 ?></td>
                                <td style="padding:10px 14px;border-bottom:1px solid var(--border-color);color:var(--text-primary);">
                                    <?php if (($med['status'] ?? '') === 'dispensed'): ?>
                                        <span class="status-badge badge-success">✅ Dispensed</span>
                                    <?php else: ?>
                                        <span class="status-badge badge-warning">⏳ Pending</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state"><i class="fas fa-prescription"></i><p>No prescriptions found</p></div>
        <?php endif; ?>
    </div>

    <div class="consultation-card mb-6">
        <h3 class="card-title"><i class="fas fa-syringe"></i> Procedures & Medical Equipment</h3>
        <?php if (count($procedures) > 0 || count($equipment_items_display) > 0): ?>
            <?php if (count($procedures) > 0): ?>
                <h4 style="font-size:0.85rem;font-weight:600;color:var(--text-secondary);margin-bottom:8px;">Procedures</h4>
                <div style="overflow-x:auto;margin-bottom:16px;">
                    <table style="width:100%;border-collapse:collapse;font-size:0.85rem;">
                        <thead><tr>
                            <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);border-bottom:2px solid var(--border-color);">Procedure</th>
                            <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);border-bottom:2px solid var(--border-color);">Price</th>
                            <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);border-bottom:2px solid var(--border-color);">Status</th>
                        </tr></thead>
                        <tbody>
                            <?php foreach ($procedures as $proc): ?>
                                <tr>
                                    <td style="padding:10px 14px;border-bottom:1px solid var(--border-color);color:var(--text-primary);"><?= htmlspecialchars($proc['procedure_name']) ?></td>
                                    <td style="padding:10px 14px;border-bottom:1px solid var(--border-color);color:var(--text-primary);">TSh <?= number_format($proc['procedure_price'] ?? 0, 0) ?></td>
                                    <td style="padding:10px 14px;border-bottom:1px solid var(--border-color);color:var(--text-primary);">✅ Completed</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            <?php if (count($equipment_items_display) > 0): ?>
                <h4 style="font-size:0.85rem;font-weight:600;color:var(--text-secondary);margin-bottom:8px;">Medical Equipment</h4>
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:0.85rem;">
                        <thead><tr>
                            <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);border-bottom:2px solid var(--border-color);">Equipment</th>
                            <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);border-bottom:2px solid var(--border-color);">Qty</th>
                            <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);border-bottom:2px solid var(--border-color);">Price</th>
                            <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);border-bottom:2px solid var(--border-color);">Status</th>
                        </tr></thead>
                        <tbody>
                            <?php foreach ($equipment_items_display as $eq): ?>
                                <tr>
                                    <td style="padding:10px 14px;border-bottom:1px solid var(--border-color);color:var(--text-primary);"><?= htmlspecialchars($eq['item_name']) ?></td>
                                    <td style="padding:10px 14px;border-bottom:1px solid var(--border-color);color:var(--text-primary);"><?= $eq['quantity'] ?></td>
                                    <td style="padding:10px 14px;border-bottom:1px solid var(--border-color);color:var(--text-primary);">TSh <?= number_format($eq['total_price'] ?? 0, 0) ?></td>
                                    <td style="padding:10px 14px;border-bottom:1px solid var(--border-color);color:var(--text-primary);">✅ Completed</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state"><i class="fas fa-syringe"></i><p>No procedures or equipment used</p></div>
        <?php endif; ?>
    </div>

    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span style="color:var(--gray-300);margin:0 8px;">|</span>
            <?= $is_completed ? 'Consultation Summary' : 'Consultation' ?>
            <span style="color:var(--gray-300);margin:0 8px;">|</span>
            <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>
            <span style="color:var(--gray-300);margin:0 8px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <div class="toast-icon"><i class="fas fa-info-circle"></i></div>
    <div class="toast-content">
        <p class="toast-title" id="toastTitle">Notification</p>
        <p class="toast-message" id="toastMessage"></p>
    </div>
    <button class="toast-close" onclick="closeToast()">&times;</button>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT - WITH AUTO-SAVE (DATA ONLY) -->
<!-- ================================================================ -->
<script>
// ================================================================
// CONSULTATION JAVASCRIPT - WITH AUTO-SAVE (DATA ONLY)
// ================================================================

var AUTO_UPDATE_INTERVAL = 3000;
var FULL_UPDATE_INTERVAL = 5000;
var LAB_CHECK_INTERVAL = 2000;
var AUTO_SAVE_INTERVAL = 30000; // 30 seconds
var updateInterval = null;
var fullUpdateInterval = null;
var labCheckInterval = null;
var autoSaveInterval = null;
var isUpdating = false;
var visitId = <?= $visit_id ?>;
var isCompleted = <?= $is_completed ? 'true' : 'false' ?>;
var isWaiting = <?= $is_waiting ? 'true' : 'false' ?>;
var isPrescribed = <?= $is_prescribed ? 'true' : 'false' ?>;
var isLabTest = <?= $is_lab_test ? 'true' : 'false' ?>;
var autoRefreshNeeded = <?= $auto_refresh_needed ? 'true' : 'false' ?>;
var hasDiagnosis = <?= !empty($visit['diagnosis']) ? 'true' : 'false' ?>;

var selectedProcedures = [];
var selectedEquipment = [];
var complaintsList = [];
var diagnosisSaving = false;
var diagnosisAlreadySaved = false;

// ================================================================
// TOAST FUNCTIONS
// ================================================================
function showToast(title, message, type) {
    var toast = document.getElementById('toast');
    var toastTitle = document.getElementById('toastTitle');
    var toastMessage = document.getElementById('toastMessage');
    if (!toast) return;
    
    var icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };
    
    toast.className = 'toast-custom ' + (type || 'info');
    var icon = toast.querySelector('.toast-icon i');
    if (icon) icon.className = 'fas ' + (icons[type] || icons.info);
    toastTitle.textContent = title || 'Notification';
    toastMessage.innerHTML = message || '';
    toast.style.display = 'flex';
    
    void toast.offsetWidth;
    toast.classList.add('show');
    
    clearTimeout(toast.timeout);
    toast.timeout = setTimeout(function() {
        toast.classList.remove('show');
        setTimeout(function() { toast.style.display = 'none'; }, 400);
    }, 6000);
}

function closeToast() {
    var toast = document.getElementById('toast');
    toast.classList.remove('show');
    setTimeout(function() { toast.style.display = 'none'; }, 400);
}

// ================================================================
// COMPLAINT FUNCTIONS
// ================================================================
function addInstruction(text) {
    var textarea = document.getElementById('medInstructions');
    if (textarea) {
        var current = textarea.value;
        textarea.value = current ? current + ', ' + text : text;
        textarea.focus();
    }
}

function toggleSection(id) {
    var body = document.getElementById(id);
    var header = body.previousElementSibling;
    if (body.classList.contains('open')) {
        body.classList.remove('open');
        header.classList.remove('active');
    } else {
        body.classList.add('open');
        header.classList.add('active');
    }
}

function addComplaintOnSelect() {
    var select = document.getElementById('complaintSelect');
    var textarea = document.getElementById('symptomsTextarea');
    var value = select.value;
    if (!value) { select.value = ''; return; }
    if (value && !complaintsList.includes(value)) {
        complaintsList.push(value);
        updateComplaintsDisplay();
        select.value = '';
    }
}

function updateComplaintsDisplay() {
    var textarea = document.getElementById('symptomsTextarea');
    if (textarea) textarea.value = complaintsList.join(', ');
}

function updateComplaints() {
    var textarea = document.getElementById('symptomsTextarea');
    if (textarea) {
        complaintsList = textarea.value.split(',').map(function(s) { return s.trim(); }).filter(function(s) { return s.length > 0; });
    }
}

// ================================================================
// GET DIAGNOSIS DATA - FOR AUTO-SAVE
// ================================================================
function getDiagnosisData() {
    var select = document.getElementById('diagnosisSelect');
    var diagnosisId = select ? select.value : '';
    var manualInput = document.getElementById('diagnosisManualInput');
    var manualValue = manualInput ? manualInput.value.trim() : '';
    var manualBox = document.getElementById('manualDiagnosisBox');
    
    if (diagnosisId === '__manual__') {
        manualBox.style.display = 'block';
        if (!manualValue) {
            return null;
        }
    } else {
        manualBox.style.display = 'none';
    }
    
    if (!diagnosisId && !manualValue) {
        return null;
    }
    
    return {
        diagnosis_id: diagnosisId,
        diagnosis_manual: manualValue,
        treatment: document.getElementById('treatmentTextarea')?.value || '',
        disease_code_manual: document.getElementById('diseaseCodeManual')?.value || '',
        symptoms: document.getElementById('symptomsTextarea')?.value || '',
        hpi: document.getElementById('hpiTextarea')?.value || '',
        physical_exam: document.getElementById('physicalExamTextarea')?.value || '',
        notes: document.getElementById('notesTextarea')?.value || ''
    };
}

// ================================================================
// ✅ AUTO-SAVE DIAGNOSIS - SAVES DATA ONLY, NO STATUS CHANGE!
// ================================================================
function autoSaveDiagnosis() {
    // Don't auto-save if completed, waiting, or currently saving
    if (isCompleted || isWaiting || diagnosisSaving) return;
    
    var diagnosisData = getDiagnosisData();
    if (!diagnosisData) return;
    
    // Check if data has actually changed (avoid unnecessary saves)
    var diagnosisManual = diagnosisData.diagnosis_manual || '';
    var treatment = diagnosisData.treatment || '';
    var symptoms = diagnosisData.symptoms || '';
    
    // If empty data, don't save
    if (!diagnosisManual && !treatment && !symptoms) return;
    
    var saveIndicator = document.getElementById('diagnosisSaveIndicator');
    var savedIndicator = document.getElementById('diagnosisSavedIndicator');
    if (saveIndicator) saveIndicator.style.display = 'inline';
    if (savedIndicator) savedIndicator.style.display = 'none';
    
    diagnosisSaving = true;
    console.log('🔄 Auto-saving data (NO status change):', diagnosisData);
    
    var dataToSend = {
        action: 'save_diagnosis',
        visit_id: visitId,
        ...diagnosisData
    };
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(dataToSend)
    })
    .then(response => response.json())
    .then(result => {
        diagnosisSaving = false;
        if (saveIndicator) saveIndicator.style.display = 'none';
        
        if (result.success) {
            diagnosisAlreadySaved = true;
            hasDiagnosis = true;
            if (savedIndicator) savedIndicator.style.display = 'inline';
            setTimeout(function() {
                if (savedIndicator) savedIndicator.style.display = 'none';
            }, 3000);
            
            var statusEl = document.getElementById('diagnosisStatus');
            if (statusEl && result.data && result.data.diagnosis) {
                statusEl.innerHTML = '✅ Saved: ' + result.data.diagnosis;
                statusEl.style.color = 'var(--success)';
            }
            
            console.log('✅ Data auto-saved (NO status change):', result.data);
        } else {
            console.error('❌ Auto-save failed:', result.message);
        }
    })
    .catch(function(error) {
        diagnosisSaving = false;
        if (saveIndicator) saveIndicator.style.display = 'none';
        console.error('❌ Auto-save error:', error);
    });
}

// ================================================================
// CHECK LAB RESULTS - ONLY UPDATES TO 'prescribed'
// ================================================================
function checkLabResultsAndUpdateStatus() {
    if (isCompleted || isWaiting || isPrescribed) return;
    
    var formData = new FormData();
    formData.append('action', 'check_lab_results');
    formData.append('visit_id', visitId);
    
    fetch(window.location.href, { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.status_updated) {
            showToast('✅ Lab Results Completed!', 'Status updated to PRESCRIBED. Add medications and procedures then click "Save Consultation".', 'success');
            console.log('✅ Status updated to prescribed:', data.message);
            
            var statusBadge = document.getElementById('visitStatusBadge');
            if (statusBadge) {
                statusBadge.textContent = 'Prescribed';
                statusBadge.className = 'status-badge badge-purple';
            }
            
            updateStatusFlow('prescribed');
            
            // ✅ NO AUTO-COMPLETE HERE! Just update UI.
        }
    })
    .catch(function(err) {
        console.error('❌ Error checking lab results:', err);
    });
}

// ================================================================
// UPDATE STATUS FLOW UI
// ================================================================
function updateStatusFlow(newStatus) {
    var steps = document.querySelectorAll('.status-step');
    var statuses = ['assigned', 'lab_test', 'prescribed', 'waiting', 'completed'];
    var currentIndex = statuses.indexOf(newStatus);
    
    steps.forEach(function(step, index) {
        step.classList.remove('active', 'completed');
        if (index < currentIndex) {
            step.classList.add('completed');
        } else if (index === currentIndex) {
            step.classList.add('active');
        }
    });
}

// ================================================================
// CHECK AND AUTO-COMPLETE - ONLY IF STATUS IS 'waiting'
// ================================================================
function checkAndAutoComplete() {
    if (isCompleted) return;
    
    var formData = new FormData();
    formData.append('action', 'get_visit_status');
    formData.append('visit_id', visitId);
    
    fetch(window.location.href, { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            var currentStatus = data.status || 'unknown';
            
            if (currentStatus !== 'waiting') {
                console.log('ℹ️ Auto-complete skipped: Status is "' + currentStatus + '", must be "waiting"');
                var statusEl = document.getElementById('autoCompleteStatus');
                if (statusEl) {
                    statusEl.innerHTML = '<span style="color:var(--warning);">⏳ Auto-complete requires status "waiting". Current: "' + currentStatus + '"</span>';
                }
                return;
            }
            
            // Check if diagnosis exists
            if (!hasDiagnosis) {
                console.log('ℹ️ Auto-complete skipped: No diagnosis exists');
                var statusEl = document.getElementById('autoCompleteStatus');
                if (statusEl) {
                    statusEl.innerHTML = '<span style="color:var(--danger);">❌ Auto-complete requires diagnosis. Please enter diagnosis first.</span>';
                }
                return;
            }
            
            var formData2 = new FormData();
            formData2.append('action', 'get_bill_totals');
            
            fetch(window.location.href, { method: 'POST', body: formData2 })
            .then(response => response.json())
            .then(billData => {
                if (billData.success && billData.bill_balance <= 0) {
                    console.log('✅ Status is "waiting", diagnosis exists, and balance is 0. Auto-completing in 3 seconds...');
                    var statusEl = document.getElementById('autoCompleteStatus');
                    if (statusEl) {
                        statusEl.innerHTML = '<span style="color:var(--success);">⏳ Status is WAITING, diagnosis exists, and balance is 0. Auto-complete in 3 seconds...</span>';
                    }
                    setTimeout(function() {
                        autoCompleteVisit();
                    }, 3000);
                } else {
                    console.log('⏳ Status is "waiting" but balance is ' + (billData.bill_balance || 'unknown'));
                    var statusEl = document.getElementById('autoCompleteStatus');
                    if (statusEl) {
                        statusEl.innerHTML = '<span style="color:var(--warning);">⏳ Status is WAITING. Waiting for payment. Balance: TSh ' + Number(billData.bill_balance || 0).toLocaleString() + '</span>';
                    }
                }
            });
        }
    })
    .catch(function(err) {
        console.error('❌ Error checking status:', err);
    });
}

// ================================================================
// AUTO-COMPLETE VISIT - ONLY TRIGGERED AFTER SAVE CONSULTATION
// ================================================================
function autoCompleteVisit() {
    var formData = new FormData();
    formData.append('action', 'get_visit_status');
    formData.append('visit_id', visitId);
    
    fetch(window.location.href, { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.status === 'waiting') {
            // Check diagnosis again
            if (!hasDiagnosis) {
                showToast('❌ Error', 'Auto-complete requires diagnosis. Please enter diagnosis first.', 'error');
                return;
            }
            
            var formData2 = new FormData();
            formData2.append('action', 'auto_complete_visit');
            formData2.append('visit_id', visitId);
            
            fetch(window.location.href, { method: 'POST', body: formData2 })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    showToast('✅ Auto-Completed!', 'Consultation completed automatically! Status is WAITING, diagnosis exists, and balance is 0.', 'success');
                    console.log('✅ Auto-complete successful:', result.message);
                    setTimeout(function() { window.location.reload(); }, 2000);
                } else {
                    console.log('ℹ️ Auto-complete not triggered:', result.message);
                    showToast('ℹ️ Info', result.message, 'info');
                }
            })
            .catch(function(err) {
                console.error('❌ Auto-complete error:', err);
            });
        } else {
            var status = data ? data.status : 'unknown';
            console.log('ℹ️ Auto-complete aborted: Status is "' + status + '", must be "waiting"');
            showToast('ℹ️ Info', 'Auto-complete requires status "waiting". Current: "' + status + '"', 'info');
        }
    })
    .catch(function(err) {
        console.error('❌ Error checking status:', err);
    });
}

// ================================================================
// BILL TOTALS UPDATE - 4 CARDS
// ================================================================
function updateBillTotals(billData) {
    if (!billData) return;
    
    var totalEl = document.getElementById('totalAmountDisplay');
    if (totalEl) totalEl.textContent = 'TSh ' + Number(billData.subtotal || 0).toLocaleString();
    
    var paidEl = document.getElementById('paidAmountDisplay');
    if (paidEl) paidEl.textContent = 'TSh ' + Number(billData.paid || 0).toLocaleString();
    
    var remainingEl = document.getElementById('remainingAmountDisplay');
    if (remainingEl) {
        var balance = Number(billData.balance || 0);
        remainingEl.textContent = 'TSh ' + balance.toLocaleString();
        var card = remainingEl.closest('.bill-summary-card');
        if (card) {
            if (balance > 0) {
                card.className = 'bill-summary-card remaining-card';
                var icon = card.querySelector('.bill-summary-icon i');
                if (icon) icon.className = 'fas fa-exclamation-triangle';
                var label = card.querySelector('.bill-summary-label');
                if (label) label.textContent = 'Remaining';
            } else {
                card.className = 'bill-summary-card remaining-card zero-balance';
                var icon = card.querySelector('.bill-summary-icon i');
                if (icon) icon.className = 'fas fa-check-circle';
                var label = card.querySelector('.bill-summary-label');
                if (label) label.textContent = 'Remaining';
            }
        }
    }
    
    var discountEl = document.getElementById('discountAmountDisplay');
    if (discountEl) discountEl.textContent = 'TSh ' + Number(billData.discount || 0).toLocaleString();
    
    if (billData.status) {
        var statusBadge = document.getElementById('billStatusBadge');
        if (statusBadge) {
            var statusMap = {
                'pending': 'badge-warning',
                'partial': 'badge-warning',
                'paid': 'badge-success',
                'cancelled': 'badge-danger'
            };
            statusBadge.className = 'status-badge ' + (statusMap[billData.status] || 'badge-warning');
            var statusText = billData.status.charAt(0).toUpperCase() + billData.status.slice(1);
            if (billData.status === 'partial') {
                statusText = 'Partial (Balance: TSh ' + Number(billData.balance || 0).toLocaleString() + ')';
            } else if (billData.status === 'paid') {
                statusText = '✅ FULLY PAID';
            }
            statusBadge.textContent = statusText;
        }
    }
    
    var lastUpdated = document.getElementById('billLastUpdated');
    if (lastUpdated) {
        var now = new Date();
        lastUpdated.textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }
}

// ================================================================
// FETCH BILL TOTALS
// ================================================================
function fetchBillTotals() {
    var formData = new FormData();
    formData.append('action', 'get_bill_totals');
    fetch(window.location.href, { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            var labTotalEl = document.getElementById('labTotalDisplay');
            if (labTotalEl) labTotalEl.textContent = data.lab_total.toLocaleString();
            var medTotalEl = document.getElementById('medTotalDisplay');
            if (medTotalEl) medTotalEl.textContent = data.medication_total.toLocaleString();
            var medListEl = document.getElementById('medListTotal');
            if (medListEl) medListEl.textContent = data.medication_total.toLocaleString();
            var procTotal = (data.procedure_total || 0) + (data.equipment_total || 0);
            var procEquipEl = document.getElementById('procEquipTotalDisplay');
            if (procEquipEl) procEquipEl.textContent = procTotal.toLocaleString();
            
            var billData = {
                subtotal: data.subtotal || 0,
                discount: data.discount || 0,
                amount_after_discount: data.amount_after_discount || 0,
                total: data.grand_total || 0,
                paid: data.paid_total || 0,
                pending: data.pending_total || 0,
                balance: data.bill_balance || 0,
                status: data.bill_status || 'pending'
            };
            updateBillTotals(billData);
        }
    })
    .catch(function(err) { console.error('Fetch bill totals error:', err); });
}

// ================================================================
// FULL STATE UPDATE - NO AUTO-COMPLETE
// ================================================================
function fetchFullState() {
    if (isUpdating || isCompleted) return;
    isUpdating = true;
    var formData = new FormData();
    formData.append('action', 'get_full_state');
    fetch(window.location.href, { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateFullUI(data);
        }
        isUpdating = false;
    })
    .catch(function() { isUpdating = false; });
}

function updateFullUI(data) {
    var timeStr = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    var liveTime = document.getElementById('liveTime');
    if (liveTime) liveTime.textContent = timeStr;
    var lastUpdate = document.getElementById('lastUpdateTime');
    if (lastUpdate) lastUpdate.textContent = '⏱ ' + timeStr;
    
    if (data.lab && data.lab.results && data.lab.results.length > 0) {
        updateLabResultsUI(data.lab.results);
    }
    
    if (data.prescriptions && data.prescriptions.length > 0) {
        updateMedicationsUI(data.prescriptions);
    }
    var medTotalEl = document.getElementById('medTotalDisplay');
    if (medTotalEl) medTotalEl.textContent = (data.medications_total || 0).toLocaleString();
    var medListEl = document.getElementById('medListTotal');
    if (medListEl) medListEl.textContent = (data.medications_total || 0).toLocaleString();
    
    if (data.bill) updateBillTotals(data.bill);
    var labTotalEl = document.getElementById('labTotalDisplay');
    if (labTotalEl) labTotalEl.textContent = (data.lab_total || 0).toLocaleString();
    var procEquipEl = document.getElementById('procEquipTotalDisplay');
    if (procEquipEl) procEquipEl.textContent = ((data.procedure_total_bill || 0) + (data.equipment_total || 0)).toLocaleString();
}

function updateLabResultsUI(results) {
    var container = document.getElementById('labResultsContainer');
    if (!container) return;
    if (!results || results.length === 0) {
        container.innerHTML = '<div class="text-center py-6 text-gray-400"><i class="fas fa-flask text-3xl block mb-2"></i><p>No lab results available</p></div>';
        return;
    }
    var html = '<div style="overflow-x:auto;"><table style="width:100%;border-collapse:collapse;font-size:0.85rem;"><thead><tr><th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);border-bottom:2px solid var(--border-color);">Test Name</th><th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);border-bottom:2px solid var(--border-color);">Result</th><th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);border-bottom:2px solid var(--border-color);">Status</th></tr></thead><tbody>';
    results.forEach(function(result) {
        html += '<tr><td style="padding:10px 14px;border-bottom:1px solid var(--border-color);color:var(--text-primary);">' + escapeHtml(result.test_name || 'N/A') + '</td><td style="padding:10px 14px;border-bottom:1px solid var(--border-color);color:var(--text-primary);font-weight:600;color:var(--success);">' + escapeHtml(result.results || 'N/A') + '</td><td style="padding:10px 14px;border-bottom:1px solid var(--border-color);color:var(--text-primary);"><span class="status-badge badge-success">✅ Completed</span></td></tr>';
    });
    html += '</tbody></table></div><div class="mt-3 text-sm text-green-600"><i class="fas fa-check-circle"></i> Lab results available.</div>';
    container.innerHTML = html;
}

function updateMedicationsUI(prescriptions) {
    var list = document.getElementById('medicationsList');
    if (!list) return;
    if (!prescriptions || prescriptions.length === 0) {
        list.innerHTML = '<div class="empty-state" id="emptyMedications"><i class="fas fa-prescription"></i><p>No medications prescribed yet</p></div>';
        var countEl = document.getElementById('medCount');
        if (countEl) countEl.textContent = '(0 items)';
        return;
    }
    var html = '';
    prescriptions.forEach(function(med) {
        var isDispensed = (med.status || '') === 'dispensed';
        html += '<div class="medication-item" id="med-item-' + med.id + '"><div class="medication-item-info"><span class="med-name">' + escapeHtml(med.medication_name || 'Unknown') + '</span><span class="med-details">' + escapeHtml(med.dosage || '') + ' • ' + escapeHtml(med.frequency || '') + ' • ' + escapeHtml(med.duration || '') + ' days</span><span class="med-qty">x' + (med.quantity || 0) + '</span><span class="med-price">TSh ' + Number(med.total_price || 0).toLocaleString() + '</span>' + (med.instructions ? '<span class="med-instruction-tag">' + escapeHtml(med.instructions) + '</span>' : '') + (isDispensed ? '<span class="med-status-dispensed">✅ Dispensed</span>' : '<span class="med-status-pending">⏳ Pending Dispense</span>') + '</div>' + (!isDispensed ? '<button type="button" class="btn-remove" onclick="removeMedication(' + med.id + ')" title="Remove medication"><i class="fas fa-times"></i></button>' : '') + '</div>';
    });
    list.innerHTML = html;
    var countEl = document.getElementById('medCount');
    if (countEl) countEl.textContent = '(' + prescriptions.length + ' items)';
}

// ================================================================
// LAB TESTS FUNCTIONS
// ================================================================
function addLabTestToCart() {
    var select = document.getElementById('labTestSelect');
    var testId = select.value;
    if (!testId) { showToast('Error', 'Please select a lab test', 'error'); return; }
    
    var formData = new FormData();
    formData.append('action', 'add_lab_test_cart');
    formData.append('test_id', testId);
    
    var btn = document.getElementById('addLabToCartBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    
    fetch(window.location.href, { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-cart-plus"></i> Add to Cart';
        if (data.success) {
            updateLabCartUI(data);
            showToast('✅ Success', data.message, 'success');
            select.value = '';
        } else {
            showToast('❌ Error', data.message, 'error');
        }
    })
    .catch(function(err) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-cart-plus"></i> Add to Cart';
        showToast('❌ Error', 'Network error. Please try again.', 'error');
    });
}

function updateLabCartUI(data) {
    var container = document.getElementById('labCartItems');
    if (!container) return;
    
    if (data.cart_items && data.cart_items.length > 0) {
        var html = '';
        data.cart_items.forEach(function(item) {
            var equipmentLabel = item.linked_equipment_name ? ' 🔗 FREE: ' + escapeHtml(item.linked_equipment_name) : '';
            html += '<div class="lab-cart-item" id="lab-cart-' + item.id + '" data-test-id="' + item.id + '"><span class="cart-item-name">' + escapeHtml(item.name) + '<span style="font-size:0.6rem;color:var(--success);margin-left:4px;">' + equipmentLabel + '</span></span><span class="cart-item-price">TSh ' + Number(item.price).toLocaleString() + '</span><button type="button" class="btn-remove-cart" onclick="removeLabTestFromCart(' + item.id + ')"><i class="fas fa-times"></i></button></div>';
        });
        container.innerHTML = html;
    } else {
        container.innerHTML = '<div class="lab-cart-empty">No tests in cart. Add tests above.</div>';
    }
    
    var sendBtn = document.getElementById('sendLabBtn');
    if (sendBtn) {
        sendBtn.disabled = data.cart_count === 0;
        sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send All to Laboratory (' + (data.cart_count || 0) + ' tests)';
    }
    
    var cartTotalSection = document.getElementById('labCartTotalSection');
    if (cartTotalSection) {
        cartTotalSection.style.display = data.cart_count > 0 ? 'inline-flex' : 'none';
    }
    
    var cartStatus = document.getElementById('labCartStatus');
    if (cartStatus) {
        cartStatus.textContent = data.cart_count > 0 ? 'Ready to send ' + data.cart_count + ' test(s)' : 'Add tests to cart first';
    }
    
    var cartCount = document.getElementById('labCartCount');
    if (cartCount) cartCount.textContent = '(' + (data.cart_count || 0) + ' items)';
    var cartTotal = document.getElementById('labCartTotal');
    if (cartTotal) cartTotal.textContent = Number(data.cart_total || 0).toLocaleString();
    var cartTotalDisplay = document.getElementById('labCartTotalDisplay');
    if (cartTotalDisplay) cartTotalDisplay.textContent = Number(data.cart_total || 0).toLocaleString();
    var cartCountDisplay = document.getElementById('labCartCountDisplay');
    if (cartCountDisplay) cartCountDisplay.textContent = data.cart_count || 0;
    var sendLabCount = document.getElementById('sendLabCount');
    if (sendLabCount) sendLabCount.textContent = data.cart_count || 0;
}

function removeLabTestFromCart(testId) {
    var formData = new FormData();
    formData.append('action', 'remove_lab_test_cart');
    formData.append('test_id', testId);
    fetch(window.location.href, { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if (data.success) { window.location.reload(); }
        else { showToast('❌ Error', data.message, 'error'); }
    });
}

function clearLabCart() {
    if (!confirm('Clear all tests from cart?')) return;
    var items = document.querySelectorAll('.lab-cart-item');
    var promises = [];
    items.forEach(function(item) {
        var testId = item.dataset.testId || item.id.replace('lab-cart-', '');
        var formData = new FormData();
        formData.append('action', 'remove_lab_test_cart');
        formData.append('test_id', testId);
        promises.push(fetch(window.location.href, { method: 'POST', body: formData }));
    });
    Promise.all(promises).then(function() { window.location.reload(); });
}

function removeLabTest(testId) {
    if (!confirm('Remove this lab test?')) return;
    var formData = new FormData();
    formData.append('action', 'remove_lab_test');
    formData.append('test_id', testId);
    fetch(window.location.href, { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if (data.success) { showToast('✅ Success', data.message, 'success'); window.location.reload(); }
        else { showToast('❌ Error', data.message, 'error'); }
    });
}

// ================================================================
// PROCEDURES FUNCTIONS
// ================================================================
function toggleProcedure(element) {
    element.classList.toggle('selected');
    updateProcedureSelection();
}

function updateProcedureSelection() {
    selectedProcedures = [];
    document.querySelectorAll('.procedure-item-select.selected').forEach(function(item) {
        selectedProcedures.push({
            id: item.dataset.procedureId,
            name: item.dataset.procedureName,
            price: parseFloat(item.dataset.price) || 0
        });
    });
    var countEl = document.getElementById('procSelectedCount');
    if (countEl) countEl.textContent = 'Selected: ' + selectedProcedures.length;
}

function addSelectedProcedures() {
    if (selectedProcedures.length === 0) {
        showToast('⚠️ Warning', 'Please select at least one procedure', 'warning');
        return;
    }
    
    var procedureIds = selectedProcedures.map(function(p) { return p.id; });
    var diagnosisData = getDiagnosisData();
    
    var formData = new FormData();
    formData.append('action', 'add_procedures_batch');
    formData.append('procedure_ids', JSON.stringify(procedureIds));
    
    if (diagnosisData) {
        formData.append('diagnosis_id', diagnosisData.diagnosis_id || '');
        formData.append('diagnosis_manual', diagnosisData.diagnosis_manual || '');
        formData.append('treatment', diagnosisData.treatment || '');
        formData.append('disease_code_manual', diagnosisData.disease_code_manual || '');
        formData.append('symptoms', diagnosisData.symptoms || '');
        formData.append('hpi', diagnosisData.hpi || '');
        formData.append('physical_exam', diagnosisData.physical_exam || '');
        formData.append('notes', diagnosisData.notes || '');
    }
    
    var btn = document.querySelector('#proceduresToggle .btn-primary');
    if (!btn) { showToast('❌ Error', 'Button not found', 'error'); return; }
    var originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
    fetch(window.location.href, { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        if (data.success) {
            showToast('✅ Success', data.message, 'success');
            clearProcedureSelections();
            setTimeout(function() { window.location.reload(); }, 1000);
        } else {
            showToast('❌ Error', data.message, 'error');
        }
    })
    .catch(function(err) {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        showToast('❌ Error', 'Network error. Please try again.', 'error');
    });
}

function clearProcedureSelections() {
    document.querySelectorAll('.procedure-item-select.selected').forEach(function(el) {
        el.classList.remove('selected');
    });
    selectedProcedures = [];
    var countEl = document.getElementById('procSelectedCount');
    if (countEl) countEl.textContent = 'Selected: 0';
}

// ================================================================
// EQUIPMENT FUNCTIONS
// ================================================================
function toggleEquipment(element) {
    element.classList.toggle('selected');
    updateEquipmentSelection();
}

function updateEquipmentSelection() {
    selectedEquipment = [];
    document.querySelectorAll('.equipment-item-select.selected').forEach(function(item) {
        selectedEquipment.push({
            id: item.dataset.equipmentId,
            name: item.dataset.equipmentName,
            price: parseFloat(item.dataset.price) || 0,
            stock: parseInt(item.dataset.stock) || 0,
            batchNumber: item.dataset.batch || '',
            expiryDate: item.dataset.expiry || ''
        });
    });
    var countEl = document.getElementById('equipSelectedCount');
    if (countEl) countEl.textContent = 'Selected: ' + selectedEquipment.length;
}

function addSelectedEquipment() {
    if (selectedEquipment.length === 0) {
        showToast('⚠️ Warning', 'Please select at least one equipment', 'warning');
        return;
    }
    
    var quantity = parseInt(document.getElementById('equipmentQuantity').value) || 1;
    if (quantity < 1) quantity = 1;
    var equipmentData = selectedEquipment.map(function(eq) {
        return { id: eq.id, quantity: quantity };
    });
    
    var diagnosisData = getDiagnosisData();
    
    var formData = new FormData();
    formData.append('action', 'add_equipment_batch');
    formData.append('equipment_data', JSON.stringify(equipmentData));
    
    if (diagnosisData) {
        formData.append('diagnosis_id', diagnosisData.diagnosis_id || '');
        formData.append('diagnosis_manual', diagnosisData.diagnosis_manual || '');
        formData.append('treatment', diagnosisData.treatment || '');
        formData.append('disease_code_manual', diagnosisData.disease_code_manual || '');
        formData.append('symptoms', diagnosisData.symptoms || '');
        formData.append('hpi', diagnosisData.hpi || '');
        formData.append('physical_exam', diagnosisData.physical_exam || '');
        formData.append('notes', diagnosisData.notes || '');
    }
    
    var btn = document.querySelector('#equipmentToggle .btn-primary');
    if (!btn) { showToast('❌ Error', 'Button not found', 'error'); return; }
    var originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
    fetch(window.location.href, { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        if (data.success) {
            showToast('✅ Success', data.message, 'success');
            clearEquipmentSelections();
            setTimeout(function() { window.location.reload(); }, 1000);
        } else {
            showToast('❌ Error', data.message, 'error');
        }
    })
    .catch(function(err) {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        showToast('❌ Error', 'Network error. Please try again.', 'error');
    });
}

function clearEquipmentSelections() {
    document.querySelectorAll('.equipment-item-select.selected').forEach(function(el) {
        el.classList.remove('selected');
    });
    selectedEquipment = [];
    var countEl = document.getElementById('equipSelectedCount');
    if (countEl) countEl.textContent = 'Selected: 0';
}

// ================================================================
// MEDICATION FUNCTIONS
// ================================================================
function addMedicationAjax() {
    var medSelect = document.getElementById('medicationSelect');
    var qty = parseInt(document.getElementById('medQuantity').value) || 0;
    var dosage = document.getElementById('medDosage').value;
    var frequency = document.getElementById('medFrequency').value;
    var duration = document.getElementById('medDuration').value;
    var route = document.getElementById('medRoute').value;
    var instructions = document.getElementById('medInstructions').value;
    
    if (!medSelect.value) { showToast('❌ Error', 'Please select a medication', 'error'); return; }
    if (qty < 1) { showToast('❌ Error', 'Quantity must be at least 1', 'error'); return; }
    
    var stock = parseInt(medSelect.options[medSelect.selectedIndex]?.dataset?.stock) || 0;
    if (qty > stock) { showToast('⚠️ Warning', 'Not enough stock across all batches! Available: ' + stock, 'warning'); return; }
    
    var diagnosisData = getDiagnosisData();
    
    var formData = new FormData();
    formData.append('action', 'add_medication');
    formData.append('inventory_id', medSelect.value);
    formData.append('quantity', qty);
    formData.append('dosage', dosage);
    formData.append('frequency', frequency);
    formData.append('duration', duration);
    formData.append('route', route);
    formData.append('instructions', instructions);
    
    if (diagnosisData) {
        formData.append('diagnosis_id', diagnosisData.diagnosis_id || '');
        formData.append('diagnosis_manual', diagnosisData.diagnosis_manual || '');
        formData.append('treatment', diagnosisData.treatment || '');
        formData.append('disease_code_manual', diagnosisData.disease_code_manual || '');
        formData.append('symptoms', diagnosisData.symptoms || '');
        formData.append('hpi', diagnosisData.hpi || '');
        formData.append('physical_exam', diagnosisData.physical_exam || '');
        formData.append('notes', diagnosisData.notes || '');
    }
    
    var btn = document.getElementById('addMedicationBtn');
    var originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
    
    fetch(window.location.href, { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        if (data.success) {
            showToast('✅ Success', data.message, 'success');
            if (data.medication) addMedicationToList(data.medication);
            if (data.bill_data) updateBillTotals(data.bill_data);
            
            document.getElementById('medicationSelect').value = '';
            document.getElementById('medQuantity').value = '1';
            document.getElementById('medDosage').value = '';
            document.getElementById('medFrequency').value = '';
            document.getElementById('medDuration').value = '7';
            document.getElementById('medRoute').value = '';
            document.getElementById('medInstructions').value = '';
        } else {
            showToast('❌ Error', data.message, 'error');
        }
    })
    .catch(function(err) {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        showToast('❌ Error', 'Network error. Please try again.', 'error');
    });
}

function addMedicationToList(med) {
    if (!med) return;
    var list = document.getElementById('medicationsList');
    if (!list) return;
    var empty = document.getElementById('emptyMedications');
    if (empty) empty.remove();
    var div = document.createElement('div');
    div.className = 'medication-item';
    div.id = 'med-item-' + med.id;
    var isDispensed = med.status === 'dispensed';
    div.innerHTML = '<div class="medication-item-info"><span class="med-name">' + escapeHtml(med.name) + '</span><span class="med-details">' + escapeHtml(med.dosage || '') + ' • ' + escapeHtml(med.frequency || '') + ' • ' + escapeHtml(med.duration || '') + ' days</span><span class="med-qty">x' + (med.quantity || 0) + '</span><span class="med-price">TSh ' + (med.total_price || 0).toLocaleString() + '</span>' + (med.instructions ? '<span class="med-instruction-tag">' + escapeHtml(med.instructions) + '</span>' : '') + (isDispensed ? '<span class="med-status-dispensed">✅ Dispensed</span>' : '<span class="med-status-pending">⏳ Pending Dispense</span>') + '</div>' + (!isDispensed ? '<button type="button" class="btn-remove" onclick="removeMedication(' + med.id + ')" title="Remove medication"><i class="fas fa-times"></i></button>' : '');
    list.appendChild(div);
    updateMedicationTotals();
}

function removeMedication(prescriptionId) {
    if (!confirm('Remove this medication? Stock will be returned.')) return;
    var formData = new FormData();
    formData.append('action', 'remove_medication');
    formData.append('prescription_id', prescriptionId);
    fetch(window.location.href, { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('ℹ️ Info', data.message, 'info');
            var item = document.getElementById('med-item-' + prescriptionId);
            if (item) item.remove();
            if (data.bill_data) updateBillTotals(data.bill_data);
            updateMedicationTotals();
        } else {
            showToast('❌ Error', data.message, 'error');
        }
    });
}

function updateMedicationTotals() {
    var items = document.querySelectorAll('.medication-item');
    var count = items.length;
    var total = 0;
    items.forEach(function(el) {
        var priceEl = el.querySelector('.med-price');
        if (priceEl) {
            var val = parseFloat(priceEl.textContent.replace(/[^0-9]/g, '')) || 0;
            total += val;
        }
    });
    var countEl = document.getElementById('medCount');
    if (countEl) countEl.textContent = '(' + count + ' items)';
    var totalEl = document.getElementById('medListTotal');
    if (totalEl) totalEl.textContent = total.toLocaleString();
    var medTotalEl = document.getElementById('medTotalDisplay');
    if (medTotalEl) medTotalEl.textContent = total.toLocaleString();
    
    if (count === 0) {
        var list = document.getElementById('medicationsList');
        if (list) {
            list.innerHTML = '<div class="empty-state" id="emptyMedications"><i class="fas fa-prescription"></i><p>No medications prescribed yet</p></div>';
        }
    }
}

// ================================================================
// REMOVE ADDED ITEM
// ================================================================
function removeAddedItem(type, id) {
    if (!confirm('Remove this ' + type + '? Stock will be returned for equipment.')) return;
    
    var formData = new FormData();
    formData.append('action', 'remove_added_item');
    formData.append('type', type);
    formData.append('id', id);
    formData.append('visit_id', visitId);
    
    var btn = document.getElementById('added-' + type + '-' + id)?.querySelector('.btn-remove-item');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    }
    
    fetch(window.location.href, { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-times"></i>';
        }
        if (data.success) {
            showToast('✅ Success', data.message, 'success');
            var element = document.getElementById('added-' + type + '-' + id);
            if (element) element.remove();
            if (data.bill_data) updateBillTotals(data.bill_data);
            updateAddedItemsUI();
        } else {
            showToast('❌ Error', data.message, 'error');
        }
    })
    .catch(function(err) {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-times"></i>';
        }
        showToast('❌ Error', 'Network error. Please try again.', 'error');
        console.error('Remove item error:', err);
    });
}

function updateAddedItemsUI() {
    var items = document.querySelectorAll('.added-item-card');
    var countEl = document.getElementById('addedCount');
    if (countEl) countEl.textContent = '(' + items.length + ' items)';
    
    var total = 0;
    items.forEach(function(el) {
        var priceEl = el.querySelector('.item-price');
        if (priceEl) {
            var val = parseFloat(priceEl.textContent.replace(/[^0-9]/g, '')) || 0;
            total += val;
        }
    });
    var totalEl = document.getElementById('addedTotal');
    if (totalEl) totalEl.textContent = total.toLocaleString();
    var procEquipEl = document.getElementById('procEquipTotalDisplay');
    if (procEquipEl) procEquipEl.textContent = total.toLocaleString();
    
    if (items.length === 0) {
        var list = document.getElementById('addedItemsList');
        if (list) {
            list.innerHTML = '<div class="empty-state" id="emptyAdded"><i class="fas fa-syringe"></i><p>No procedures or equipment added yet</p></div>';
        }
    }
}

// ================================================================
// LAB STATUS AUTO-REFRESH
// ================================================================
var labStatusHash = '';

function checkLabStatus() {
    if (isCompleted) return;
    
    var formData = new FormData();
    formData.append('action', 'get_lab_status');
    
    fetch(window.location.href, { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            var newHash = data.pending + '|' + data.in_progress + '|' + data.completed;
            
            if (labStatusHash && labStatusHash !== newHash) {
                console.log('🔄 Lab status changed! Refreshing page...');
                showToast('✅ Lab Status Updated', 'Lab tests status changed. Refreshing page...', 'success');
                setTimeout(function() {
                    window.location.reload();
                }, 1000);
            }
            
            labStatusHash = newHash;
            
            var totalActive = data.pending + data.in_progress;
            var pendingBadge = document.getElementById('pendingLabBadge');
            if (pendingBadge) {
                if (totalActive > 0) {
                    pendingBadge.style.display = 'inline-block';
                    var countEl = document.getElementById('pendingLabCount');
                    if (countEl) countEl.textContent = totalActive;
                } else {
                    pendingBadge.style.display = 'none';
                }
            }
            
            var resultsBadge = document.getElementById('resultsBadge');
            if (resultsBadge) {
                if (data.completed > 0 && totalActive === 0) {
                    resultsBadge.textContent = '✅ Results Available';
                    resultsBadge.className = 'frozen-badge success';
                    resultsBadge.style.display = 'inline-block';
                } else if (totalActive > 0) {
                    resultsBadge.textContent = '⏳ Pending Results';
                    resultsBadge.className = 'frozen-badge';
                    resultsBadge.style.display = 'inline-block';
                } else {
                    resultsBadge.style.display = 'none';
                }
            }
            
            var updateTime = document.getElementById('resultsUpdateTime');
            if (updateTime) {
                var now = new Date();
                var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                updateTime.textContent = '⏱ ' + timeStr;
            }
        }
    })
    .catch(function(err) {});
}

// ================================================================
// MANUAL REFRESH
// ================================================================
function manualRefresh() {
    var btn = document.getElementById('refreshBtn');
    if (btn) {
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
        btn.disabled = true;
    }
    fetchFullState();
    fetchBillTotals();
    setTimeout(function() {
        if (btn) {
            btn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh';
            btn.disabled = false;
        }
        showToast('✅ Refreshed', 'Data updated manually', 'success');
    }, 1500);
}

function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ================================================================
// AUTO-UPDATE
// ================================================================
function startAutoUpdate() {
    if (isCompleted) return;
    if (updateInterval) clearInterval(updateInterval);
    if (fullUpdateInterval) clearInterval(fullUpdateInterval);
    if (labCheckInterval) clearInterval(labCheckInterval);
    if (autoSaveInterval) clearInterval(autoSaveInterval);
    
    checkLabStatus();
    updateInterval = setInterval(function() {
        checkLabStatus();
    }, AUTO_UPDATE_INTERVAL);
    
    fetchFullState();
    fetchBillTotals();
    fullUpdateInterval = setInterval(function() {
        fetchFullState();
        fetchBillTotals();
    }, FULL_UPDATE_INTERVAL);
    
    labCheckInterval = setInterval(function() {
        checkLabResultsAndUpdateStatus();
    }, LAB_CHECK_INTERVAL);
    
    // ✅ AUTO-SAVE EVERY 30 SECONDS - DATA ONLY
    autoSaveInterval = setInterval(function() {
        if (!isCompleted && !isWaiting && !isLabTest) {
            autoSaveDiagnosis();
        }
    }, AUTO_SAVE_INTERVAL);
    
    console.log('🔄 Auto-update started (Auto-save every 30s - data only)');
}

function stopAutoUpdate() {
    if (updateInterval) {
        clearInterval(updateInterval);
        updateInterval = null;
    }
    if (fullUpdateInterval) {
        clearInterval(fullUpdateInterval);
        fullUpdateInterval = null;
    }
    if (labCheckInterval) {
        clearInterval(labCheckInterval);
        labCheckInterval = null;
    }
    if (autoSaveInterval) {
        clearInterval(autoSaveInterval);
        autoSaveInterval = null;
    }
}

// ================================================================
// DOM READY
// ================================================================
document.addEventListener('DOMContentLoaded', function() {
    if (!isCompleted) {
        setTimeout(startAutoUpdate, 1000);
        
        var diagSelect = document.getElementById('diagnosisSelect');
        if (diagSelect) {
            diagSelect.addEventListener('change', function() {
                if (this.value === '__manual__') {
                    document.getElementById('manualDiagnosisBox').style.display = 'block';
                } else {
                    document.getElementById('manualDiagnosisBox').style.display = 'none';
                }
                // ✅ AUTO-SAVE on change (data only)
                autoSaveDiagnosis();
            });
        }
        
        // ✅ AUTO-SAVE on input changes
        var inputs = [
            'diagnosisManualInput', 'treatmentTextarea', 'symptomsTextarea',
            'hpiTextarea', 'physicalExamTextarea', 'notesTextarea'
        ];
        inputs.forEach(function(id) {
            var el = document.getElementById(id);
            if (el) {
                el.addEventListener('change', function() {
                    autoSaveDiagnosis();
                });
                el.addEventListener('blur', function() {
                    autoSaveDiagnosis();
                });
            }
        });
        
        // ✅ AUTO-SAVE on page unload (before navigating away)
        window.addEventListener('beforeunload', function() {
            if (!isCompleted && !isWaiting) {
                autoSaveDiagnosis();
            }
        });
        
        console.log('✅ Auto-save ENABLED - saves data only (NO status change)');
        console.log('ℹ️ Save Consultation button = changes status to "waiting"');
        console.log('ℹ️ Auto-complete = only after Save Consultation + diagnosis exists + balance=0');
        console.log('ℹ️ Auto-save does NOT change status!');
        
        // ✅ Only check auto-complete if status is 'waiting'
        if (isWaiting) {
            console.log('✅ Visit is "waiting". Checking auto-complete...');
            setTimeout(function() {
                checkAndAutoComplete();
            }, 3000);
        } else {
            var statusBadge = document.getElementById('visitStatusBadge');
            var currentStatus = statusBadge ? statusBadge.textContent.toLowerCase().trim() : 'unknown';
            console.log('ℹ️ Visit status is "' + currentStatus + '". Auto-complete requires "waiting".');
            console.log('ℹ️ Click "Save Consultation" to change to "waiting"');
        }
        
        if (autoRefreshNeeded) {
            showToast('✅ Lab Results Updated', 'New lab results are available!', 'success');
            setTimeout(function() {
                fetchFullState();
                fetchBillTotals();
            }, 500);
        }
        
        console.log('💰 Braick - Consultation (AUTO-SAVE DATA ONLY)');
        console.log('✅ 4 Cards: Total Amount, Paid Amount, Remaining, Total Discount');
        console.log('✅ Status Flow: assigned → lab_test → prescribed → waiting → completed');
        console.log('✅ Lab results → PRESCRIBED ONLY');
        console.log('✅ Auto-save: Saves DATA only (NO status change)');
        console.log('✅ Save Consultation: Changes status to "waiting"');
        console.log('✅ Auto-complete: Only after Save Consultation + diagnosis exists + balance=0');
        console.log('✅ NO auto-complete on Ajax refresh or sidebar navigation');
        console.log('✅ Diagnosis required for auto-complete!');
    }
});

document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        stopAutoUpdate();
    } else {
        startAutoUpdate();
    }
});
</script>

</body>
</html>