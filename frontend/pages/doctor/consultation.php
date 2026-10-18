<?php
// ================================================================
// FILE: frontend/pages/doctor/consultation.php
// V32 FINAL - MULTI-BATCH STOCK DISTRIBUTION
// ================================================================
// ✅ V32: Multi-batch stock distribution (FIFO by expiry)
// ✅ V32: Medication inachukua batches zote ikiisha ya kwanza
// ✅ V32: Stock validation kwa total available kwenye batches zote
// ✅ V32: Bill items + prescription items kwa kila batch
// ✅ V32: Display batches zilizotumika kwenye prescribed meds
// ✅ V31: ensureBillExists() - bill hata kama FREE (0 charge)
// ✅ V31: Error logging kamili
// ✅ V31: Status auto-promotion: waiting → lab_test / prescribed
// ✅ V31: BLUE CARDS kwa lab tests zilizo-linked na equipment
// ✅ V31: Search filter kwa procedures na equipment
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

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

$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['full_name'] ?? 'Doctor';
$doctor_branch_id = $_SESSION['branch_id'] ?? 1;
$doctor_specialty = $_SESSION['specialty'] ?? 'General Medicine';
$is_admin = ($_SESSION['role'] === 'admin');

$visit_id = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

if ($visit_id <= 0 && $patient_id <= 0) {
    header('Location: my_patients.php');
    exit;
}

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die('Database connection error: ' . $e->getMessage());
}

// ================================================================
// HELPER FUNCTIONS
// ================================================================
function cleanupEmptyPrescriptions($db, $visit_id, $branch_id) {
    try {
        $stmt = $db->prepare("SELECT p.id FROM prescriptions p LEFT JOIN prescription_items pi ON p.id = pi.prescription_id WHERE p.visit_id = ? AND p.branch_id = ? AND pi.id IS NULL");
        $stmt->execute([$visit_id, $branch_id]);
        $empty_rx = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($empty_rx)) return 0;
        $empty_ids = array_column($empty_rx, 'id');
        $placeholders = implode(',', array_fill(0, count($empty_ids), '?'));
        $db->prepare("DELETE FROM prescriptions WHERE id IN ($placeholders)")->execute($empty_ids);
        return count($empty_ids);
    } catch (Exception $e) { return 0; }
}

function deleteBillItemByReference($db, $bill_id, $reference_id, $reference_type, $item_type = null) {
    try {
        if ($item_type === null) $item_type = $reference_type;
        $stmt = $db->prepare("DELETE FROM bill_items WHERE bill_id = ? AND reference_id = ? AND reference_type = ?");
        $stmt->execute([$bill_id, $reference_id, $reference_type]);
        $deleted = $stmt->rowCount();
        if ($deleted == 0) {
            $stmt = $db->prepare("DELETE FROM bill_items WHERE bill_id = ? AND item_id = ? AND item_type = ?");
            $stmt->execute([$bill_id, $reference_id, $item_type]);
            $deleted = $stmt->rowCount();
        }
        return $deleted;
    } catch (Exception $e) {
        error_log("deleteBillItemByReference ERROR: " . $e->getMessage());
        return 0;
    }
}

function ensureBillExists($db, $visit_id, $patient_id, $doctor_id, $doctor_branch_id, &$bill_id) {
    try {
        if ($bill_id && $bill_id > 0) {
            $stmt = $db->prepare("SELECT id FROM bills WHERE id = ? AND visit_id = ?");
            $stmt->execute([$bill_id, $visit_id]);
            if ($stmt->fetch()) return $bill_id;
        }
        $stmt = $db->prepare("SELECT id FROM bills WHERE visit_id = ? ORDER BY id ASC LIMIT 1");
        $stmt->execute([$visit_id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $bill_id = $existing['id'];
            return $bill_id;
        }
        $bill_number = 'BILL-' . date('Ymd') . '-' . str_pad($patient_id, 6, '0', STR_PAD_LEFT) . '-' . rand(100, 999);
        $db->prepare("INSERT INTO bills (bill_number, patient_id, visit_id, subtotal, total_amount, balance, status, created_by, branch_id, created_at) VALUES (?, ?, ?, 0, 0, 0, 'pending', ?, ?, NOW())")
           ->execute([$bill_number, $patient_id, $visit_id, $doctor_id, $doctor_branch_id]);
        $bill_id = $db->lastInsertId();
        error_log("ensureBillExists: Created new bill #$bill_id for visit #$visit_id");
        return $bill_id;
    } catch (Exception $e) {
        error_log("ensureBillExists ERROR: " . $e->getMessage());
        return $bill_id;
    }
}

function getBMIStatus($bmi) {
    if ($bmi === null || $bmi === '' || $bmi <= 0) return ['label' => 'N/A', 'class' => 'unknown'];
    $bmi = (float)$bmi;
    if ($bmi < 18.5) return ['label' => 'Underweight', 'class' => 'underweight'];
    if ($bmi < 25)   return ['label' => 'Normal',      'class' => 'normal'];
    if ($bmi < 30)   return ['label' => 'Overweight',  'class' => 'overweight'];
    return                    ['label' => 'Obese',     'class' => 'obese'];
}

function getSpO2Status($spo2) {
    if ($spo2 === null || $spo2 === '') return ['label' => 'N/A', 'class' => 'unknown'];
    $spo2 = (int)$spo2;
    if ($spo2 >= 95) return ['label' => 'NORMAL', 'class' => 'normal'];
    if ($spo2 >= 90) return ['label' => 'LOW', 'class' => 'low'];
    return ['label' => 'CRITICAL', 'class' => 'critical'];
}

function getTempStatus($temp) {
    if ($temp === null || $temp === '') return ['label' => 'N/A', 'class' => 'unknown'];
    $temp = (float)$temp;
    if ($temp < 36.0) return ['label' => 'LOW', 'class' => 'low'];
    if ($temp <= 37.5) return ['label' => 'NORMAL', 'class' => 'normal'];
    if ($temp <= 38.5) return ['label' => 'FEVER', 'class' => 'warning'];
    return ['label' => 'HIGH FEVER', 'class' => 'critical'];
}

function getBPStatus($systolic, $diastolic) {
    if (!$systolic || !$diastolic) return ['label' => 'N/A', 'class' => 'unknown'];
    $sys = (int)$systolic; $dia = (int)$diastolic;
    if ($sys < 90 || $dia < 60) return ['label' => 'LOW', 'class' => 'low'];
    if ($sys < 120 && $dia < 80) return ['label' => 'NORMAL', 'class' => 'normal'];
    if ($sys < 130 && $dia < 85) return ['label' => 'ELEVATED', 'class' => 'warning'];
    if ($sys < 140 || $dia < 90) return ['label' => 'STAGE 1', 'class' => 'warning'];
    if ($sys < 180 || $dia < 120) return ['label' => 'STAGE 2', 'class' => 'critical'];
    return ['label' => 'CRISIS', 'class' => 'critical'];
}

function getPulseStatus($pulse) {
    if (!$pulse) return ['label' => 'N/A', 'class' => 'unknown'];
    $p = (int)$pulse;
    if ($p < 60) return ['label' => 'LOW', 'class' => 'low'];
    if ($p <= 100) return ['label' => 'NORMAL', 'class' => 'normal'];
    if ($p <= 120) return ['label' => 'HIGH', 'class' => 'warning'];
    return ['label' => 'VERY HIGH', 'class' => 'critical'];
}

function calculateAge($dob) {
    if (empty($dob) || $dob === '0000-00-00') return 'N/A';
    return (new DateTime($dob))->diff(new DateTime('today'))->y;
}

function getUserColor($name) {
    $colors = ['#0B5ED7', '#059669', '#7C3AED', '#DC2626', '#D97706', '#0D9488', '#DB2777', '#2563EB', '#0891B2'];
    return $colors[abs(crc32($name)) % count($colors)];
}

function formatMoneyDisplay($amount) {
    if ($amount === null || $amount === '') return '0';
    return number_format((float)$amount, 0, '.', ',');
}

function getPrescriptionStatusInfo($status, $is_paid = false) {
    if ($is_paid && $status === 'pending') {
        return ['label' => '💰 Paid', 'class' => 'badge-paid', 'icon' => 'fa-money-bill'];
    }
    $map = [
        'pending' => ['label' => '⏳ Pending', 'class' => 'badge-pending', 'icon' => 'fa-clock'],
        'confirmed' => ['label' => '✔️ Confirmed', 'class' => 'badge-confirmed', 'icon' => 'fa-check-double'],
        'dispensed' => ['label' => '💊 Dispensed', 'class' => 'badge-dispensed', 'icon' => 'fa-pills'],
        'cancelled' => ['label' => '❌ Cancelled', 'class' => 'badge-cancelled', 'icon' => 'fa-times-circle']
    ];
    return $map[$status] ?? ['label' => ucfirst($status), 'class' => 'badge-pending', 'icon' => 'fa-clock'];
}

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

$instruction_quick_picks = [
    'After Meals', 'Before Meals', 'With Water', 'At Bedtime',
    'With Food', 'Complete Course', 'As Needed', 'Empty Stomach'
];

// ================================================================
// GET OR CREATE VISIT
// ================================================================
if ($visit_id > 0) {
    if ($is_admin) {
        $stmt = $db->prepare("
            SELECT v.*, p.id as patient_id, p.patient_id as patient_code,
                   p.full_name as patient_name, p.phone, p.email, 
                   p.date_of_birth, p.gender, p.address, p.blood_group, 
                   p.allergies, p.emergency_contact, p.created_at as patient_registered,
                   u.full_name as doctor_name, u.specialty as doctor_specialty,
                   b.name as branch_name, b.location as branch_location, b.phone as branch_phone
            FROM visits v
            JOIN patients p ON v.patient_id = p.id
            LEFT JOIN users u ON v.doctor_id = u.id
            LEFT JOIN branches b ON v.branch_id = b.id
            WHERE v.id = ?
        ");
        $stmt->execute([$visit_id]);
    } else {
        $stmt = $db->prepare("
            SELECT v.*, p.id as patient_id, p.patient_id as patient_code,
                   p.full_name as patient_name, p.phone, p.email, 
                   p.date_of_birth, p.gender, p.address, p.blood_group, 
                   p.allergies, p.emergency_contact, p.created_at as patient_registered,
                   u.full_name as doctor_name, u.specialty as doctor_specialty,
                   b.name as branch_name, b.location as branch_location, b.phone as branch_phone
            FROM visits v
            JOIN patients p ON v.patient_id = p.id
            LEFT JOIN users u ON v.doctor_id = u.id
            LEFT JOIN branches b ON v.branch_id = b.id
            WHERE v.id = ? AND v.doctor_id = ?
        ");
        $stmt->execute([$visit_id, $doctor_id]);
    }
    $visit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$visit) { header('Location: my_patients.php?error=visit_not_found'); exit; }
    $patient_id = $visit['patient_id'];
} else {
    $stmt = $db->prepare("
        SELECT v.*, p.id as patient_id, p.patient_id as patient_code,
               p.full_name as patient_name, p.phone, p.email, 
               p.date_of_birth, p.gender, p.address, p.blood_group, 
               p.allergies, p.emergency_contact, p.created_at as patient_registered,
               u.full_name as doctor_name, u.specialty as doctor_specialty,
               b.name as branch_name, b.location as branch_location, b.phone as branch_phone
        FROM visits v
        JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON v.doctor_id = u.id
        LEFT JOIN branches b ON v.branch_id = b.id
        WHERE v.patient_id = ? AND v.doctor_id = ? AND v.status NOT IN ('completed', 'cancelled')
        ORDER BY v.created_at DESC LIMIT 1
    ");
    $stmt->execute([$patient_id, $doctor_id]);
    $visit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$visit) {
        $visit_number = 'VIS-' . date('Ymd') . '-' . str_pad($patient_id, 4, '0', STR_PAD_LEFT);
        $db->prepare("INSERT INTO visits (visit_number, patient_id, doctor_id, branch_id, visit_type, status, created_at) VALUES (?, ?, ?, ?, 'new', 'assigned', NOW())")
           ->execute([$visit_number, $patient_id, $doctor_id, $doctor_branch_id]);
        $visit_id = $db->lastInsertId();
        $stmt = $db->prepare("
            SELECT v.*, p.id as patient_id, p.patient_id as patient_code,
                   p.full_name as patient_name, p.phone, p.email, 
                   p.date_of_birth, p.gender, p.address, p.blood_group, 
                   p.allergies, p.emergency_contact, p.created_at as patient_registered,
                   u.full_name as doctor_name, u.specialty as doctor_specialty,
                   b.name as branch_name, b.location as branch_location, b.phone as branch_phone
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

cleanupEmptyPrescriptions($db, $visit_id, $doctor_branch_id);

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
$bill_total = 0; $bill_paid = 0; $bill_balance = 0; $bill_subtotal = 0;
$bill_discount = 0; $bill_total_discount = 0; $bill_premium = 0;

try {
    $stmt = $db->prepare("SELECT id, status, total_amount, paid_amount, balance, subtotal, discount_amount, pharmacy_discount, cashier_discount, total_discount, premium_amount FROM bills WHERE visit_id = ? ORDER BY id ASC LIMIT 1");
    $stmt->execute([$visit_id]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($bill) {
        $bill_id = $bill['id'];
        $bill_status = $bill['status'];
        $bill_total = $bill['total_amount'] ?? 0;
        $bill_paid = $bill['paid_amount'] ?? 0;
        $bill_balance = $bill['balance'] ?? 0;
        $bill_subtotal = $bill['subtotal'] ?? 0;
        $bill_discount = $bill['pharmacy_discount'] ?? $bill['discount_amount'] ?? 0;
        $bill_total_discount = $bill['total_discount'] ?? 0;
        $bill_premium = $bill['premium_amount'] ?? 0;
    } else {
        $bill_number = 'BILL-' . date('Ymd') . '-' . str_pad($patient_id, 6, '0', STR_PAD_LEFT) . '-' . rand(100, 999);
        $db->prepare("INSERT INTO bills (bill_number, patient_id, visit_id, subtotal, total_amount, balance, status, created_by, branch_id, created_at) VALUES (?, ?, ?, 0, 0, 0, 'pending', ?, ?, NOW())")
           ->execute([$bill_number, $patient_id, $visit_id, $doctor_id, $doctor_branch_id]);
        $bill_id = $db->lastInsertId();
    }
} catch (Exception $e) { 
    error_log("Bill fetch/create ERROR: " . $e->getMessage());
}

$bill_id = ensureBillExists($db, $visit_id, $patient_id, $doctor_id, $doctor_branch_id, $bill_id);

function updateBillTotal($db, $bill_id) {
    if (!$bill_id || $bill_id <= 0) {
        return ['subtotal' => 0, 'total' => 0, 'discount' => 0, 'discount_amount' => 0, 'pharmacy_discount' => 0, 'cashier_discount' => 0, 'premium' => 0, 'paid' => 0, 'balance' => 0, 'status' => 'pending'];
    }
    
    $stmt = $db->prepare("SELECT SUM(total_price) as total FROM bill_items WHERE bill_id = ? AND status != 'cancelled'");
    $stmt->execute([$bill_id]);
    $subtotal = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    
    $stmt = $db->prepare("SELECT discount_amount, pharmacy_discount, cashier_discount, total_discount, premium_amount FROM bills WHERE id = ?");
    $stmt->execute([$bill_id]);
    $bill_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $pharmacy_discount = (float)($bill_data['pharmacy_discount'] ?? 0);
    if ($pharmacy_discount == 0) $pharmacy_discount = (float)($bill_data['discount_amount'] ?? 0);
    $cashier_discount = (float)($bill_data['cashier_discount'] ?? 0);
    $total_discount = $pharmacy_discount + $cashier_discount;
    $discount_amount = $pharmacy_discount;
    $premium_amount = (float)($bill_data['premium_amount'] ?? 0);
    
    if ($total_discount > $subtotal) $total_discount = $subtotal;
    $total_amount = $subtotal + $premium_amount - $total_discount;
    if ($total_amount < 0) $total_amount = 0;
    
    $stmt = $db->prepare("SELECT SUM(amount) as payment_total FROM payments WHERE bill_id = ?");
    $stmt->execute([$bill_id]);
    $paid_amount = (float)($stmt->fetch(PDO::FETCH_ASSOC)['payment_total'] ?? 0);
    
    $balance = $total_amount - $paid_amount;
    if ($balance < 0) { $balance = 0; if ($paid_amount > $total_amount) $paid_amount = $total_amount; }
    
    if ($total_amount <= 0) $status = 'paid';
    elseif ($balance <= 0.01) $status = 'paid';
    elseif ($paid_amount > 0 && $balance > 0) $status = 'partial';
    else $status = 'pending';
    
    $db->prepare("UPDATE bills SET subtotal = ?, total_amount = ?, paid_amount = ?, balance = ?, status = ?, discount_amount = ?, pharmacy_discount = ?, total_discount = ?, updated_at = NOW() WHERE id = ?")
       ->execute([$subtotal, $total_amount, $paid_amount, $balance, $status, $discount_amount, $pharmacy_discount, $total_discount, $bill_id]);
    
    return ['subtotal' => $subtotal, 'total' => $total_amount, 'discount' => $total_discount, 'discount_amount' => $discount_amount, 'pharmacy_discount' => $pharmacy_discount, 'cashier_discount' => $cashier_discount, 'premium' => $premium_amount, 'paid' => $paid_amount, 'balance' => $balance, 'status' => $status];
}

function getBillDiscount($db, $bill_id) {
    if (!$bill_id) return ['total_discount' => 0, 'premium_amount' => 0];
    $stmt = $db->prepare("SELECT discount_amount, pharmacy_discount, cashier_discount, total_discount, premium_amount FROM bills WHERE id = ?");
    $stmt->execute([$bill_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$data) return ['total_discount' => 0, 'premium_amount' => 0];
    if (($data['pharmacy_discount'] ?? 0) == 0 && ($data['discount_amount'] ?? 0) > 0) $data['pharmacy_discount'] = $data['discount_amount'];
    if (($data['total_discount'] ?? 0) == 0) $data['total_discount'] = (float)($data['pharmacy_discount'] ?? 0) + (float)($data['cashier_discount'] ?? 0);
    return $data;
}

function isBillItemPaid($db, $bill_id, $reference_id, $reference_type) {
    if (!$bill_id) return false;
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as paid_count FROM bill_items WHERE bill_id = ? AND reference_id = ? AND reference_type = ? AND status = 'paid'");
        $stmt->execute([$bill_id, $reference_id, $reference_type]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result && $result['paid_count'] > 0) return true;
        $stmt = $db->prepare("SELECT COUNT(*) as paid_count FROM bill_items WHERE bill_id = ? AND item_id = ? AND item_type = ? AND status = 'paid'");
        $stmt->execute([$bill_id, $reference_id, $reference_type]);
        $result2 = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($result2 && $result2['paid_count'] > 0);
    } catch (Exception $e) { 
        error_log("isBillItemPaid ERROR: " . $e->getMessage());
        return false; 
    }
}

function checkAndAutoCompleteVisit($db, $visit_id, $bill_id) {
    $stmt = $db->prepare("SELECT status FROM visits WHERE id = ?");
    $stmt->execute([$visit_id]);
    $visit = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$visit || $visit['status'] !== 'waiting') return false;
    $stmt = $db->prepare("SELECT diagnosis FROM visits WHERE id = ?");
    $stmt->execute([$visit_id]);
    $diagnosis = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$diagnosis || empty($diagnosis['diagnosis'])) return false;
    $stmt = $db->prepare("SELECT balance FROM bills WHERE id = ?");
    $stmt->execute([$bill_id]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$bill || $bill['balance'] > 0) return false;
    try {
        $stmt = $db->prepare("UPDATE visits SET status = 'completed', is_completed = 1, completed_at = NOW(), updated_at = NOW() WHERE id = ? AND status = 'waiting'");
        $stmt->execute([$visit_id]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) { 
        error_log("checkAndAutoCompleteVisit ERROR: " . $e->getMessage());
        return false; 
    }
}

function saveDiagnosisToDatabase($db, $visit_id, $doctor_id, $doctor_branch_id, $data, $fallback_from_db = false) {
    $selected_diseases = isset($data['diagnosis_ids']) ? $data['diagnosis_ids'] : [];
    $manual_diseases = isset($data['manual_diseases']) ? $data['manual_diseases'] : [];
    $manual_disease_codes = isset($data['manual_disease_codes']) ? $data['manual_disease_codes'] : [];
    $treatment = trim($data['treatment'] ?? '');
    $symptoms = trim($data['symptoms'] ?? '');
    $hpi = trim($data['hpi'] ?? '');
    $physical_exam = trim($data['physical_exam'] ?? '');
    $notes = trim($data['notes'] ?? '');
    
    if (is_string($selected_diseases)) { 
        $decoded = json_decode($selected_diseases, true); 
        if (is_array($decoded)) $selected_diseases = $decoded; 
        else $selected_diseases = array_map('trim', explode(',', $selected_diseases)); 
    }
    if (is_string($manual_diseases)) { 
        $decoded = json_decode($manual_diseases, true); 
        if (is_array($decoded)) $manual_diseases = $decoded; 
        else $manual_diseases = array_map('trim', explode(',', $manual_diseases)); 
    }
    if (is_string($manual_disease_codes)) { 
        $decoded = json_decode($manual_disease_codes, true); 
        if (is_array($decoded)) $manual_disease_codes = $decoded; 
        else $manual_disease_codes = array_map('trim', explode(',', $manual_disease_codes)); 
    }
    if (!is_array($selected_diseases)) $selected_diseases = [];
    if (!is_array($manual_diseases)) $manual_diseases = [];
    if (!is_array($manual_disease_codes)) $manual_disease_codes = [];
    
    $clean_selected = [];
    foreach ($selected_diseases as $disease_id) { 
        $disease_id = (int)$disease_id; 
        if ($disease_id > 0 && !in_array($disease_id, $clean_selected)) $clean_selected[] = $disease_id; 
    }
    $clean_manual = [];
    foreach ($manual_diseases as $manual) { 
        $manual = trim(str_replace(['[', ']', '"', '\\', "'"], '', trim($manual))); 
        if (!empty($manual) && !in_array($manual, $clean_manual)) $clean_manual[] = $manual; 
    }
    $clean_manual_codes = [];
    foreach ($manual_disease_codes as $code) { 
        $code = trim(str_replace(['[', ']', '"', '\\', "'"], '', trim($code))); 
        $clean_manual_codes[] = $code; 
    }
    
    if ($fallback_from_db && empty($clean_selected) && empty($clean_manual)) {
        $stmt_db = $db->prepare("SELECT diagnosis, disease_id, disease_code, treatment, symptoms, hpi, physical_exam, notes FROM visits WHERE id = ?");
        $stmt_db->execute([$visit_id]);
        $db_visit = $stmt_db->fetch(PDO::FETCH_ASSOC);
        
        if ($db_visit) {
            if (!empty($db_visit['disease_id'])) {
                $db_ids = array_map('trim', explode(',', $db_visit['disease_id']));
                foreach ($db_ids as $db_id) {
                    $db_id = (int)$db_id;
                    if ($db_id > 0 && !in_array($db_id, $clean_selected)) $clean_selected[] = $db_id;
                }
            }
            if (!empty($db_visit['diagnosis'])) {
                $db_manuals = array_map('trim', explode(',', $db_visit['diagnosis']));
                foreach ($db_manuals as $db_manual) {
                    if (!empty($db_manual) && !in_array($db_manual, $clean_manual)) $clean_manual[] = $db_manual;
                }
            }
            if (empty($treatment) && !empty($db_visit['treatment'])) $treatment = $db_visit['treatment'];
            if (empty($symptoms) && !empty($db_visit['symptoms'])) $symptoms = $db_visit['symptoms'];
            if (empty($hpi) && !empty($db_visit['hpi'])) $hpi = $db_visit['hpi'];
            if (empty($physical_exam) && !empty($db_visit['physical_exam'])) $physical_exam = $db_visit['physical_exam'];
            if (empty($notes) && !empty($db_visit['notes'])) $notes = $db_visit['notes'];
        }
    }
    
    $saved_diseases = []; $disease_names = []; $disease_codes = [];
    
    foreach ($clean_selected as $disease_id) {
        $stmt = $db->prepare("SELECT id, disease_name, disease_code FROM diseases WHERE id = ? AND is_active = 1");
        $stmt->execute([$disease_id]);
        $disease = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($disease) {
            if (!in_array($disease['disease_name'], $disease_names)) { 
                $saved_diseases[] = $disease_id; 
                $disease_names[] = $disease['disease_name']; 
                $disease_codes[] = $disease['disease_code'] ?? ''; 
            }
            if (!empty($treatment)) { 
                $db->prepare("UPDATE diseases SET treatment = ?, updated_at = NOW() WHERE id = ?")->execute([$treatment, $disease_id]); 
            }
        }
    }
    
    foreach ($clean_manual as $index => $manual) {
        if (in_array($manual, $disease_names)) continue;
        $user_code = $clean_manual_codes[$index] ?? '';
        $stmt = $db->prepare("SELECT id, disease_name, disease_code FROM diseases WHERE disease_name = ? AND branch_id = ?");
        $stmt->execute([$manual, $doctor_branch_id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            if (!in_array($existing['disease_name'], $disease_names)) {
                $saved_diseases[] = $existing['id'];
                $disease_names[] = $existing['disease_name'];
                $final_code = !empty($user_code) ? $user_code : ($existing['disease_code'] ?? '');
                $disease_codes[] = $final_code;
            }
            if (!empty($treatment)) { 
                $db->prepare("UPDATE diseases SET treatment = ?, updated_at = NOW() WHERE id = ?")->execute([$treatment, $existing['id']]); 
            }
        } else {
            if (!empty($user_code)) { $disease_code = $user_code; }
            else {
                $clean_code_name = preg_replace('/[^a-zA-Z0-9]/', '', $manual);
                $clean_code_prefix = strtoupper(substr($clean_code_name, 0, 6));
                if (empty($clean_code_prefix)) $clean_code_prefix = 'DISEASE';
                $disease_code = 'D-' . $clean_code_prefix . '-' . rand(100, 999);
            }
            $db->prepare("INSERT INTO diseases (disease_name, disease_code, branch_id, treatment, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())")
               ->execute([$manual, $disease_code, $doctor_branch_id, $treatment]);
            $new_id = $db->lastInsertId();
            $saved_diseases[] = $new_id;
            $disease_names[] = $manual;
            $disease_codes[] = $disease_code;
        }
    }
    
    $diagnosis_string = implode(', ', $disease_names);
    $disease_code_string = implode(', ', $disease_codes);
    $disease_ids_string = implode(',', $saved_diseases);
    
    $db->prepare("
        UPDATE visits SET 
            disease_id = COALESCE(NULLIF(?, ''), disease_id), 
            diagnosis = COALESCE(NULLIF(?, ''), diagnosis), 
            disease_code = COALESCE(NULLIF(?, ''), disease_code), 
            treatment = COALESCE(NULLIF(?, ''), treatment), 
            symptoms = COALESCE(NULLIF(?, ''), symptoms), 
            hpi = COALESCE(NULLIF(?, ''), hpi), 
            physical_exam = COALESCE(NULLIF(?, ''), physical_exam), 
            notes = COALESCE(NULLIF(?, ''), notes), 
            updated_at = NOW() 
        WHERE id = ? AND doctor_id = ?
    ")->execute([
        $disease_ids_string ?: null, 
        $diagnosis_string ?: null, 
        $disease_code_string ?: null,
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
        'disease_names' => $disease_names, 
        'diagnosis_string' => $diagnosis_string,
        'disease_ids' => $saved_diseases,
        'disease_codes' => $disease_codes,
        'has_diagnosis' => (!empty($disease_names))
    ];
}

// ================================================================
// GET DATA
// ================================================================
$diseases_list = [];
try {
    $stmt = $db->prepare("SELECT id, disease_name, disease_code, category, description, treatment FROM diseases WHERE is_active = 1 AND (branch_id IS NULL OR branch_id = ?) ORDER BY disease_name");
    $stmt->execute([$doctor_branch_id]);
    $diseases_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $diseases_list = []; }

$lab_tests_catalog = [];
try {
    $stmt = $db->prepare("SELECT lc.id, lc.test_name, lc.test_code, lc.category, lc.price, lc.description, lc.reference_range FROM lab_tests_catalog lc WHERE lc.is_active = 1 AND (lc.branch_id = ? OR lc.branch_id IS NULL) ORDER BY lc.category, lc.test_name");
    $stmt->execute([$doctor_branch_id]);
    $lab_tests_catalog = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($lab_tests_catalog as &$test) {
        $stmt_eq = $db->prepare("
            SELECT lte.id as link_id, lte.equipment_id, lte.branch_id as link_branch_id,
                   me.equipment_name, me.quantity as equipment_stock,
                   me.batch_number as equipment_batch, me.expiry_date as equipment_expiry,
                   me.unit, 1 as equipment_quantity_used
            FROM lab_test_equipment lte
            LEFT JOIN medical_equipment me ON lte.equipment_id = me.id AND me.branch_id = lte.branch_id
            WHERE lte.lab_test_id = ? AND me.status = 'active'
            ORDER BY me.equipment_name        ");
        $stmt_eq->execute([$test['id']]);
        $test['equipment_links'] = $stmt_eq->fetchAll(PDO::FETCH_ASSOC);
        $test['total_equipment_links'] = count($test['equipment_links']);
        
        $all_available = true;
        foreach ($test['equipment_links'] as $eq) {
            if ((int)($eq['equipment_stock'] ?? 0) < (int)($eq['equipment_quantity_used'] ?? 1)) {
                $all_available = false;
                break;
            }
        }
        $test['all_equipment_available'] = $all_available;
    }
    unset($test);
} catch (Exception $e) { $lab_tests_catalog = []; }

// ✅ V32: Medications grouped - FIFO by expiry for batch selection
$medications_grouped = [];
try {
    $stmt = $db->prepare("
        SELECT id, medication_name, category, unit, selling_price, quantity, batch_number, expiry_date, status 
        FROM medications_inventory 
        WHERE branch_id = ? 
        ORDER BY 
            CASE WHEN quantity > 0 AND (expiry_date IS NULL OR expiry_date = '0000-00-00' OR expiry_date >= CURDATE()) AND status = 'active' THEN 0 ELSE 1 END ASC, 
            medication_name ASC,
            CASE WHEN expiry_date IS NULL OR expiry_date = '0000-00-00' THEN 1 ELSE 0 END ASC,
            expiry_date ASC,
            id ASC
    ");
    $stmt->execute([$doctor_branch_id]);
    $medications_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($medications_list as $med) {
        $key = $med['medication_name'] . '|' . $med['category'];
        if (!isset($medications_grouped[$key])) {
            $medications_grouped[$key] = [
                'name' => $med['medication_name'], 
                'category' => $med['category'], 
                'selling_price' => $med['selling_price'], 
                'total_quantity' => 0, 
                'available_quantity' => 0, 
                'batches' => [], 
                'has_available' => false, 
                'has_expired' => false, 
                'has_out_of_stock' => false, 
                'has_inactive' => false
            ];
        }
        $is_expired = (!empty($med['expiry_date']) && $med['expiry_date'] !== '0000-00-00' && strtotime($med['expiry_date']) < strtotime('today'));
        $is_available = ($med['quantity'] > 0 && $med['status'] === 'active' && !$is_expired);
        
        if ($is_available) { 
            $medications_grouped[$key]['has_available'] = true; 
            $medications_grouped[$key]['available_quantity'] += $med['quantity']; 
        }
        if ($is_expired) $medications_grouped[$key]['has_expired'] = true;
        if ($med['quantity'] <= 0) $medications_grouped[$key]['has_out_of_stock'] = true;
        if ($med['status'] !== 'active') $medications_grouped[$key]['has_inactive'] = true;
        
        $medications_grouped[$key]['total_quantity'] += $med['quantity'];
        $medications_grouped[$key]['batches'][] = [
            'id' => $med['id'], 
            'quantity' => $med['quantity'], 
            'batch_number' => $med['batch_number'], 
            'expiry_date' => $med['expiry_date'], 
            'selling_price' => $med['selling_price'], 
            'is_available' => $is_available
        ];
    }
    uasort($medications_grouped, function($a, $b) {
        if ($a['has_available'] && !$b['has_available']) return -1;
        if (!$a['has_available'] && $b['has_available']) return 1;
        return strcmp($a['name'], $b['name']);
    });
} catch (Exception $e) { $medications_grouped = []; }

$procedures_list = [];
try {
    $stmt = $db->prepare("SELECT pc.id, pc.procedure_name, pc.procedure_code, pc.category, pc.price FROM procedures_catalog pc WHERE pc.is_active = 1 AND (pc.branch_id IS NULL OR pc.branch_id = ?) ORDER BY pc.procedure_name");
    $stmt->execute([$doctor_branch_id]);
    $procedures_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $procedures_list = []; }

$equipment_list = [];
try {
    $stmt = $db->prepare("SELECT id, equipment_name, category, quantity, unit, selling_price, batch_number, expiry_date, status FROM medical_equipment WHERE status = 'active' AND branch_id = ? ORDER BY equipment_name");
    $stmt->execute([$doctor_branch_id]);
    $equipment_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $equipment_list = []; }

$vital_signs = null;
if ($visit_id > 0) {
    $stmt = $db->prepare("SELECT temperature, blood_pressure_systolic, blood_pressure_diastolic, pulse_rate, oxygen_saturation, weight, height, bmi, notes, recorded_at, u.full_name as recorded_by_name FROM vital_signs vs LEFT JOIN users u ON vs.recorded_by = u.id WHERE vs.visit_id = ? ORDER BY vs.recorded_at DESC LIMIT 1");
    $stmt->execute([$visit_id]);
    $vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$vital_signs) {
        $stmt = $db->prepare("SELECT temperature, blood_pressure_systolic, blood_pressure_diastolic, pulse_rate, oxygen_saturation, weight, height, bmi, notes, recorded_at, u.full_name as recorded_by_name FROM vital_signs vs LEFT JOIN users u ON vs.recorded_by = u.id WHERE vs.patient_id = ? ORDER BY vs.recorded_at DESC LIMIT 1");
        $stmt->execute([$patient_id]);
        $vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

$lab_requests = [];
$lab_results = [];
$all_lab_tests = [];
$lab_results_available = false;
$has_active_lab = false;

try {
    $stmt = $db->prepare("
        SELECT lt.*, COALESCE(tech1.full_name, tech2.full_name, tech3.full_name, 'N/A') as technician_name
        FROM lab_tests lt
        LEFT JOIN users tech1 ON lt.performed_by = tech1.id
        LEFT JOIN users tech2 ON lt.lab_technician_id = tech2.id
        LEFT JOIN users tech3 ON lt.technician_id = tech3.id
        WHERE lt.visit_id = ? AND lt.status IN ('pending', 'in_progress')
        ORDER BY lt.created_at DESC
    ");
    $stmt->execute([$visit_id]);
    $lab_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $has_active_lab = count($lab_requests) > 0;
    
    $stmt = $db->prepare("
        SELECT lt.*, COALESCE(tech1.full_name, tech2.full_name, tech3.full_name, 'N/A') as technician_name
        FROM lab_tests lt
        LEFT JOIN users tech1 ON lt.performed_by = tech1.id
        LEFT JOIN users tech2 ON lt.lab_technician_id = tech2.id
        LEFT JOIN users tech3 ON lt.technician_id = tech3.id
        WHERE lt.visit_id = ? AND lt.status = 'completed'
        ORDER BY lt.completed_at DESC
    ");
    $stmt->execute([$visit_id]);
    $lab_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $lab_results_available = count($lab_results) > 0;
    
    $stmt = $db->prepare("
        SELECT lt.*, COALESCE(tech1.full_name, tech2.full_name, tech3.full_name, 'N/A') as technician_name
        FROM lab_tests lt
        LEFT JOIN users tech1 ON lt.performed_by = tech1.id
        LEFT JOIN users tech2 ON lt.lab_technician_id = tech2.id
        LEFT JOIN users tech3 ON lt.technician_id = tech3.id
        WHERE lt.visit_id = ? AND lt.status != 'cancelled'
        ORDER BY lt.status DESC, lt.created_at DESC
    ");
    $stmt->execute([$visit_id]);
    $all_lab_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { error_log("Lab fetch error: " . $e->getMessage()); }

$prescriptions = [];
$medications_total = 0;

try {
    $stmt = $db->prepare("
        SELECT p.*, pi.id as item_id, pi.medication_name, pi.dosage, pi.frequency, 
               pi.quantity, pi.duration, pi.route, pi.instructions,
               pi.unit_price, pi.total_price, pi.dispensed_at, pi.dispensed_by, pi.inventory_id,
               pi.confirmed_at, pi.confirmed_by, pi.cancelled_at, pi.cancelled_by,
               p.status as prescription_status,
               mi.batch_number, mi.expiry_date,
               confirmer.full_name as confirmed_by_name,
               dispenser.full_name as dispensed_by_name,
               canceller.full_name as cancelled_by_name
        FROM prescriptions p
        INNER JOIN prescription_items pi ON p.id = pi.prescription_id
        LEFT JOIN medications_inventory mi ON pi.inventory_id = mi.id
        LEFT JOIN users confirmer ON pi.confirmed_by = confirmer.id
        LEFT JOIN users dispenser ON pi.dispensed_by = dispenser.id
        LEFT JOIN users canceller ON pi.cancelled_by = canceller.id
        WHERE p.visit_id = ? AND pi.id IS NOT NULL 
              AND pi.medication_name IS NOT NULL AND pi.medication_name != ''
        ORDER BY p.created_at DESC, pi.id ASC
    ");
    $stmt->execute([$visit_id]);
    $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($prescriptions as $presc) {
        if (empty($presc['medication_name']) || empty($presc['item_id'])) continue;
        if (empty($presc['cancelled_at'])) {
            $medications_total += $presc['total_price'] ?? 0;
        }
    }
} catch (Exception $e) { $prescriptions = []; }

$procedures = [];
$procedure_total = 0;
try {
    $stmt = $db->prepare("SELECT p.* FROM procedures p WHERE p.visit_id = ? AND p.status != 'cancelled' ORDER BY p.created_at DESC");
    $stmt->execute([$visit_id]);
    $procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($procedures as $proc) $procedure_total += $proc['procedure_price'] ?? 0;
} catch (Exception $e) { $procedures = []; }

$bill_items = [];
$lab_total = 0;
$equipment_total = 0;
$medication_total_bill = 0;
$procedure_total_bill = 0;
$total_discount = 0;
$total_premium = 0;

try {
    $stmt = $db->prepare("SELECT bi.id, bi.item_name, bi.item_type, bi.item_id, bi.reference_id, bi.reference_type, bi.quantity, bi.unit_price, bi.total_price, bi.status FROM bill_items bi WHERE bi.bill_id = ? AND bi.status != 'cancelled' ORDER BY bi.item_type ASC, bi.created_at DESC");
    $stmt->execute([$bill_id]);
    $bill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($bill_items as $item) {
        switch ($item['item_type']) {
            case 'lab_test': $lab_total += $item['total_price']; break;
            case 'medication': $medication_total_bill += $item['total_price']; break;
            case 'procedure': $procedure_total_bill += $item['total_price']; break;
            case 'equipment': $equipment_total += $item['total_price']; break;
        }
    }
    
    $discount_data = getBillDiscount($db, $bill_id);
    $total_discount = (float)($discount_data['total_discount'] ?? 0);
    $total_premium = (float)($discount_data['premium_amount'] ?? 0);
} catch (Exception $e) { $bill_items = []; $total_discount = 0; $total_premium = 0; }

$bill_data = updateBillTotal($db, $bill_id);
$bill_total = $bill_data['total'];
$bill_paid = $bill_data['paid'];
$bill_balance = $bill_data['balance'];
$bill_status = $bill_data['status'];
$bill_subtotal = $bill_data['subtotal'];
$total_discount = $bill_data['discount'];
$total_premium = $bill_data['premium'];

$equipment_items_display = [];
try {
    $stmt = $db->prepare("SELECT bi.id, bi.item_name, bi.item_id, bi.quantity, bi.unit_price, bi.total_price, bi.status, me.equipment_name, me.batch_number, me.expiry_date FROM bill_items bi LEFT JOIN medical_equipment me ON bi.item_id = me.id WHERE bi.bill_id = ? AND bi.item_type = 'equipment' AND bi.status != 'cancelled' ORDER BY bi.created_at DESC");
    $stmt->execute([$bill_id]);
    $equipment_items_display = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $equipment_items_display = []; }

$doctor_branch_name = 'Not Assigned';
try {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$doctor_branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) $doctor_branch_name = $branch_data['name'];
} catch (Exception $e) { $doctor_branch_name = 'Branch'; }

$selected_disease_ids = [];
if (!empty($visit['disease_id'])) $selected_disease_ids = array_map('trim', explode(',', $visit['disease_id']));
$manual_diseases_saved = [];
if (!empty($visit['diagnosis'])) $manual_diseases_saved = array_map('trim', explode(',', $visit['diagnosis']));
$saved_codes_array = [];
if (!empty($visit['disease_code'])) $saved_codes_array = array_map('trim', explode(',', $visit['disease_code']));

// ================================================================
// CHECK LOCK SECTIONS
// ================================================================
$has_completed_lab_with_result = false;
$pending_lab_count = 0;
$total_lab_count = 0;

foreach ($all_lab_tests as $lab_check) {
    if ($lab_check['status'] === 'completed' && !empty(trim($lab_check['results'] ?? ''))) {
        $has_completed_lab_with_result = true;
    }
    if (in_array($lab_check['status'], ['pending', 'in_progress'])) {
        $pending_lab_count++;
    }
    if ($lab_check['status'] !== 'cancelled') {
        $total_lab_count++;
    }
}

$lock_sections = ($total_lab_count > 0 && !$has_completed_lab_with_result);
if ($total_lab_count == 0) $lock_sections = false;

// ================================================================
// HANDLE FORM SUBMISSIONS
// ================================================================
$flash_message = isset($_SESSION['flash_message']) ? $_SESSION['flash_message'] : '';
$flash_type = isset($_SESSION['flash_type']) ? $_SESSION['flash_type'] : '';
unset($_SESSION['flash_message']);
unset($_SESSION['flash_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_completed) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'get_bill_totals') {
        header('Content-Type: application/json');
        $bill_id = ensureBillExists($db, $visit_id, $patient_id, $doctor_id, $doctor_branch_id, $bill_id);
        
        $lab_total = 0; $medication_total = 0; $procedure_total_bill = 0; $equipment_total = 0;
        $total_bill_amount = 0; $paid_total = 0; $pending_total = 0;
        
        $stmt = $db->prepare("SELECT id, item_type, total_price, status FROM bill_items WHERE bill_id = ? AND status != 'cancelled'");
        $stmt->execute([$bill_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($items as $item) {
            $total_bill_amount += $item['total_price'];
            if ($item['status'] === 'paid') $paid_total += $item['total_price'];
            else $pending_total += $item['total_price'];
            switch ($item['item_type']) {
                case 'lab_test': $lab_total += $item['total_price']; break;
                case 'medication': $medication_total += $item['total_price']; break;
                case 'procedure': $procedure_total_bill += $item['total_price']; break;
                case 'equipment': $equipment_total += $item['total_price']; break;
            }
        }
        
        $discount_data = getBillDiscount($db, $bill_id);
        $total_discount = (float)($discount_data['total_discount'] ?? 0);
        $premium_amount = (float)($discount_data['premium_amount'] ?? 0);
        $grand_total = $total_bill_amount + $premium_amount - $total_discount;
        if ($grand_total < 0) $grand_total = 0;
        
        $stmt = $db->prepare("SELECT SUM(amount) as payment_total FROM payments WHERE bill_id = ?");
        $stmt->execute([$bill_id]);
        $payment_total = (float)($stmt->fetch(PDO::FETCH_ASSOC)['payment_total'] ?? 0);
        $balance = $grand_total - $payment_total;
        if ($balance < 0) $balance = 0;
        
        $auto_completed = false;
        if ($visit_status === 'waiting' && $balance <= 0) $auto_completed = checkAndAutoCompleteVisit($db, $visit_id, $bill_id);
        
        echo json_encode(['success' => true, 'lab_total' => $lab_total, 'medication_total' => $medication_total, 'procedure_total' => $procedure_total_bill, 'equipment_total' => $equipment_total, 'subtotal' => $total_bill_amount, 'discount' => $total_discount, 'premium' => $premium_amount, 'grand_total' => $grand_total, 'paid_total' => $payment_total, 'bill_balance' => $balance, 'auto_completed' => $auto_completed]);
        exit;
    }
    
    if ($action === 'save_diagnosis') {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true);
        $visit_id_input = (int)($input['visit_id'] ?? 0);
        if ($visit_id_input <= 0) { echo json_encode(['success' => false, 'message' => 'Invalid visit ID']); exit; }
        try {
            $result = saveDiagnosisToDatabase($db, $visit_id_input, $doctor_id, $doctor_branch_id, $input, false);
            echo json_encode([
                'success' => true, 
                'message' => '✅ Diagnosis saved',
                'data' => [
                    'diagnosis' => $result['diagnosis_string'],
                    'disease_ids' => $result['disease_ids'],
                    'disease_codes' => $result['disease_codes']
                ]
            ]);
        } catch (Exception $e) { 
            error_log("save_diagnosis ERROR: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => '❌ Error: ' . $e->getMessage()]); 
        }
        exit;
    }
    
    if ($action === 'add_lab_test_cart') {
        header('Content-Type: application/json');
        $test_id = (int)($_POST['test_id'] ?? 0);
        $response = ['success' => false, 'message' => ''];
        if ($test_id > 0) {
            try {
                $stmt = $db->prepare("SELECT lc.* FROM lab_tests_catalog lc WHERE lc.id = ? AND lc.is_active = 1");
                $stmt->execute([$test_id]);
                $test = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($test) {
                    if (!isset($_SESSION['lab_cart'])) $_SESSION['lab_cart'] = [];
                    $exists = false;
                    foreach ($_SESSION['lab_cart'] as $item) { if ($item['id'] == $test_id) { $exists = true; break; } }
                    if (!$exists) {
                        $_SESSION['lab_cart'][] = ['id' => $test_id, 'name' => $test['test_name'], 'price' => $test['price']];
                        $response['success'] = true;
                        $response['message'] = '✅ ' . $test['test_name'] . ' added!';
                        $response['cart_count'] = count($_SESSION['lab_cart']);
                        $response['cart_total'] = array_sum(array_column($_SESSION['lab_cart'], 'price'));
                        $response['cart_items'] = $_SESSION['lab_cart'];
                    } else $response['message'] = '⚠️ Already in cart';
                }
            } catch (Exception $e) { 
                error_log("add_lab_test_cart ERROR: " . $e->getMessage());
                $response['message'] = '❌ Error: ' . $e->getMessage(); 
            }
        }
        echo json_encode($response);
        exit;
    }
    
    if ($action === 'remove_lab_test_cart') {
        header('Content-Type: application/json');
        $test_id = (int)($_POST['test_id'] ?? 0);
        $response = ['success' => false];
        if ($test_id > 0 && isset($_SESSION['lab_cart'])) {
            foreach ($_SESSION['lab_cart'] as $key => $item) { if ($item['id'] == $test_id) { unset($_SESSION['lab_cart'][$key]); break; } }
            $_SESSION['lab_cart'] = array_values($_SESSION['lab_cart']);
            $response['success'] = true;
            $response['cart_count'] = count($_SESSION['lab_cart']);
            $response['cart_total'] = array_sum(array_column($_SESSION['lab_cart'], 'price'));
            $response['cart_items'] = $_SESSION['lab_cart'];
        }
        echo json_encode($response);
        exit;
    }
    
    if ($action === 'remove_lab_test') {
        header('Content-Type: application/json');
        $test_id = (int)($_POST['test_id'] ?? 0);
        $response = ['success' => false, 'message' => ''];
        try {
            $bill_id = ensureBillExists($db, $visit_id, $patient_id, $doctor_id, $doctor_branch_id, $bill_id);
            $stmt = $db->prepare("SELECT lt.* FROM lab_tests lt WHERE lt.id = ? AND lt.visit_id = ?");
            $stmt->execute([$test_id, $visit_id]);
            $test = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($test && in_array($test['status'], ['pending', 'in_progress'])) {
                $is_paid = isBillItemPaid($db, $bill_id, $test_id, 'lab_test');
                if (!$is_paid) {
                    $db->beginTransaction();
                    deleteBillItemByReference($db, $bill_id, $test_id, 'lab_test', 'lab_test');
                    $db->prepare("DELETE FROM lab_tests WHERE id = ?")->execute([$test_id]);
                    $db->commit();
                    updateBillTotal($db, $bill_id);
                    $response['success'] = true;
                    $response['message'] = '✅ Lab test removed!';
                } else $response['message'] = '❌ Already PAID';
            } else $response['message'] = '❌ Cannot remove';
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log("remove_lab_test ERROR: " . $e->getMessage());
            $response['message'] = '❌ Error: ' . $e->getMessage();
        }
        echo json_encode($response);
        exit;
    }
    
    if ($action === 'cancel_completed_lab_test') {
        header('Content-Type: application/json');
        $test_id = (int)($_POST['test_id'] ?? 0);
        $response = ['success' => false, 'message' => ''];
        try {
            $bill_id = ensureBillExists($db, $visit_id, $patient_id, $doctor_id, $doctor_branch_id, $bill_id);
            $stmt = $db->prepare("SELECT lt.* FROM lab_tests lt WHERE lt.id = ? AND lt.visit_id = ?");
            $stmt->execute([$test_id, $visit_id]);
            $test = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($test && $test['status'] === 'completed') {
                $is_paid = isBillItemPaid($db, $bill_id, $test_id, 'lab_test');
                if (!$is_paid) {
                    $db->beginTransaction();
                    deleteBillItemByReference($db, $bill_id, $test_id, 'lab_test', 'lab_test');
                    $db->prepare("DELETE FROM lab_tests WHERE id = ?")->execute([$test_id]);
                    $db->commit();
                    updateBillTotal($db, $bill_id);
                    $response['success'] = true;
                    $response['message'] = '✅ Test canceled!';
                } else $response['message'] = '❌ Already PAID';
            } else $response['message'] = '❌ Not completed';
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log("cancel_completed_lab_test ERROR: " . $e->getMessage());
            $response['message'] = '❌ Error: ' . $e->getMessage();
        }
        echo json_encode($response);
        exit;
    }
    
    // ✅ V32: MULTI-BATCH add_medication
    if ($action === 'add_medication') {
        header('Content-Type: application/json');
        
        if ($lock_sections) {
            echo json_encode(['success' => false, 'message' => '🔒 Cannot add medication. Sections are locked.']);
            exit;
        }
        
        try {
            saveDiagnosisToDatabase($db, $visit_id, $doctor_id, $doctor_branch_id, [
                'diagnosis_ids' => $_POST['diagnosis_ids'] ?? [],
                'manual_diseases' => $_POST['manual_diseases'] ?? [],
                'manual_disease_codes' => $_POST['manual_disease_codes'] ?? [],
                'treatment' => $_POST['treatment'] ?? '',
                'symptoms' => $_POST['symptoms'] ?? '',
                'hpi' => $_POST['hpi'] ?? '',
                'physical_exam' => $_POST['physical_exam'] ?? '',
                'notes' => $_POST['notes'] ?? ''
            ], true);
        } catch (Exception $e) {
            error_log("add_medication diagnosis save failed: " . $e->getMessage());
        }
        
        $bill_id = ensureBillExists($db, $visit_id, $patient_id, $doctor_id, $doctor_branch_id, $bill_id);
        
        $medication_name = trim($_POST['medication_name'] ?? '');
        $medication_category = trim($_POST['medication_category'] ?? '');
        $quantity = (int)($_POST['quantity'] ?? 1);
        $dosage = trim($_POST['dosage'] ?? '');
        $frequency = trim($_POST['frequency'] ?? '');
        $duration = trim($_POST['duration'] ?? '');
        $route = trim($_POST['route'] ?? '');
        $instructions = trim($_POST['instructions'] ?? '');
        
        $response = ['success' => false, 'message' => ''];
        
        if (!empty($medication_name) && $quantity > 0) {
            try {
                // ✅ V32: FIFO - batches zote zenye stock, expiry ya karibu kwanza
                $stmt = $db->prepare("
                    SELECT id, medication_name, selling_price, quantity as stock, batch_number, expiry_date 
                    FROM medications_inventory 
                    WHERE medication_name = ? 
                      AND (category = ? OR (? = '' AND category IS NULL))
                      AND status = 'active' 
                      AND branch_id = ?
                      AND quantity > 0
                      AND (expiry_date IS NULL OR expiry_date = '0000-00-00' OR expiry_date >= CURDATE())
                    ORDER BY 
                        CASE WHEN expiry_date IS NULL OR expiry_date = '0000-00-00' THEN 1 ELSE 0 END ASC,
                        expiry_date ASC,
                        id ASC
                ");
                $stmt->execute([$medication_name, $medication_category, $medication_category, $doctor_branch_id]);
                $available_batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($available_batches)) {
                    echo json_encode(['success' => false, 'message' => '❌ Medication not available in stock']);
                    exit;
                }
                
                $total_available = 0;
                foreach ($available_batches as $b) { $total_available += (int)$b['stock']; }
                
                if ($total_available < $quantity) {
                    echo json_encode(['success' => false, 'message' => '❌ Insufficient stock. Available: ' . $total_available . ', Requested: ' . $quantity]);
                    exit;
                }
                
                // ✅ V32: Chukua kwenye batches zote (FIFO)
                $remaining_to_dispense = $quantity;
                $batches_used = [];
                $total_price = 0;
                
                foreach ($available_batches as $batch) {
                    if ($remaining_to_dispense <= 0) break;
                    $batch_stock = (int)$batch['stock'];
                    if ($batch_stock <= 0) continue;
                    
                    $take_from_batch = min($batch_stock, $remaining_to_dispense);
                    $batch_price = (float)$batch['selling_price'];
                    $batch_total = $batch_price * $take_from_batch;
                    
                    $batches_used[] = [
                        'inventory_id' => $batch['id'],
                        'batch_number' => $batch['batch_number'],
                        'expiry_date' => $batch['expiry_date'],
                        'unit_price' => $batch_price,
                        'quantity' => $take_from_batch,
                        'total_price' => $batch_total
                    ];
                    
                    $remaining_to_dispense -= $take_from_batch;
                    $total_price += $batch_total;
                }
                
                if ($remaining_to_dispense > 0) {
                    echo json_encode(['success' => false, 'message' => '❌ Could not dispense full quantity. Remaining: ' . $remaining_to_dispense]);
                    exit;
                }
                
                $db->beginTransaction();
                
                $prescription_number = 'PRES-' . date('Ymd') . '-' . str_pad($patient_id, 4, '0', STR_PAD_LEFT) . '-' . rand(100, 999);
                $db->prepare("INSERT INTO prescriptions (prescription_number, visit_id, patient_id, doctor_id, status, branch_id, created_at) VALUES (?, ?, ?, ?, 'pending', ?, NOW())")
                   ->execute([$prescription_number, $visit_id, $patient_id, $doctor_id, $doctor_branch_id]);
                $prescription_id = $db->lastInsertId();
                
                $batch_numbers_list = [];
                foreach ($batches_used as $batch_used) {
                    $db->prepare("
                        INSERT INTO prescription_items 
                        (prescription_id, patient_id, inventory_id, medication_name, dosage, frequency, quantity, duration, route, instructions, unit_price, total_price, branch_id, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ")->execute([
                        $prescription_id, 
                        $patient_id, 
                        $batch_used['inventory_id'], 
                        $medication_name, 
                        $dosage, 
                        $frequency, 
                        $batch_used['quantity'], 
                        $duration, 
                        $route, 
                        $instructions, 
                        $batch_used['unit_price'], 
                        $batch_used['total_price'], 
                        $doctor_branch_id
                    ]);
                    
                    $batch_label = ' (Batch: ' . ($batch_used['batch_number'] ?: 'N/A') . ')';
                    $batch_numbers_list[] = $batch_used['batch_number'] ?: 'N/A';
                    
                    $db->prepare("
                        INSERT INTO bill_items 
                        (bill_id, patient_id, branch_id, item_type, item_name, quantity, unit_price, total_price, status, reference_id, reference_type, created_at) 
                        VALUES (?, ?, ?, 'medication', ?, ?, ?, ?, 'pending', ?, 'prescription', NOW())
                    ")->execute([
                        $bill_id, 
                        $patient_id, 
                        $doctor_branch_id, 
                        $medication_name . $batch_label, 
                        $batch_used['quantity'], 
                        $batch_used['unit_price'], 
                        $batch_used['total_price'], 
                        $prescription_id
                    ]);
                }
                
                $db->prepare("UPDATE visits SET status = 'prescribed', updated_at = NOW() WHERE id = ? AND status IN ('assigned', 'waiting', 'lab_test')")->execute([$visit_id]);
                
                $db->commit();
                $bill_data = updateBillTotal($db, $bill_id);
                
                $response['success'] = true;
                $response['message'] = '✅ Medication added! (' . count($batches_used) . ' batch' . (count($batches_used) > 1 ? 'es' : '') . ' used: ' . implode(', ', $batch_numbers_list) . ')';
                $response['medication'] = [
                    'id' => $prescription_id, 
                    'name' => $medication_name, 
                    'dosage' => $dosage, 
                    'frequency' => $frequency, 
                    'duration' => $duration, 
                    'quantity' => $quantity, 
                    'instructions' => $instructions, 
                    'total_price' => $total_price,
                    'batches_used' => $batches_used
                ];
                $response['bill_data'] = $bill_data;
                
            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                error_log("add_medication ERROR: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
                $response['message'] = '❌ Error: ' . $e->getMessage();
            }
        } else {
            $response['message'] = '❌ Invalid medication name or quantity';
        }
        echo json_encode($response);
        exit;
    }
    
    if ($action === 'remove_medication') {
        header('Content-Type: application/json');
        $prescription_id = (int)($_POST['prescription_id'] ?? 0);
        $response = ['success' => false, 'message' => ''];
        try {
            $bill_id = ensureBillExists($db, $visit_id, $patient_id, $doctor_id, $doctor_branch_id, $bill_id);
            $stmt = $db->prepare("SELECT p.* FROM prescriptions p WHERE p.id = ? AND p.visit_id = ?");
            $stmt->execute([$prescription_id, $visit_id]);
            $presc = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($presc && $presc['status'] !== 'dispensed') {
                $is_paid = isBillItemPaid($db, $bill_id, $prescription_id, 'prescription');
                if (!$is_paid) {
                    $db->beginTransaction();
                    $db->prepare("DELETE FROM bill_items WHERE bill_id = ? AND reference_id = ? AND reference_type = 'prescription'")->execute([$bill_id, $prescription_id]);
                    $db->prepare("DELETE FROM prescription_items WHERE prescription_id = ?")->execute([$prescription_id]);
                    $db->prepare("DELETE FROM prescriptions WHERE id = ?")->execute([$prescription_id]);
                    $db->commit();
                    updateBillTotal($db, $bill_id);
                    $response['success'] = true;
                    $response['message'] = '✅ Medication removed!';
                } else $response['message'] = '❌ Already PAID';
            } else $response['message'] = '❌ Cannot remove';
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log("remove_medication ERROR: " . $e->getMessage());
            $response['message'] = '❌ Error: ' . $e->getMessage();
        }
        echo json_encode($response);
        exit;
    }
    
    if ($action === 'add_procedures_batch') {
        header('Content-Type: application/json');
        if ($lock_sections) { echo json_encode(['success' => false, 'message' => '🔒 Cannot add procedures.']); exit; }
        
        try {
            saveDiagnosisToDatabase($db, $visit_id, $doctor_id, $doctor_branch_id, [
                'diagnosis_ids' => $_POST['diagnosis_ids'] ?? [], 'manual_diseases' => $_POST['manual_diseases'] ?? [],
                'manual_disease_codes' => $_POST['manual_disease_codes'] ?? [], 'treatment' => $_POST['treatment'] ?? '',
                'symptoms' => $_POST['symptoms'] ?? '', 'hpi' => $_POST['hpi'] ?? '',
                'physical_exam' => $_POST['physical_exam'] ?? '', 'notes' => $_POST['notes'] ?? ''
            ], true);
        } catch (Exception $e) { error_log("add_procedures_batch diagnosis save failed: " . $e->getMessage()); }
        
        $bill_id = ensureBillExists($db, $visit_id, $patient_id, $doctor_id, $doctor_branch_id, $bill_id);
        
        $procedure_ids = isset($_POST['procedure_ids']) ? json_decode($_POST['procedure_ids'], true) : [];
        $response = ['success' => false, 'message' => '', 'added' => 0];
        if (!empty($procedure_ids)) {
            try {
                $db->beginTransaction();
                foreach ($procedure_ids as $proc_id) {
                    $proc_id = (int)$proc_id;
                    $stmt = $db->prepare("SELECT pc.* FROM procedures_catalog pc WHERE pc.id = ? AND pc.is_active = 1");
                    $stmt->execute([$proc_id]);
                    $procedure = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$procedure) continue;
                    
                    $db->prepare("INSERT INTO procedures (visit_id, patient_id, doctor_id, procedure_id, procedure_name, category, procedure_price, status, branch_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())")
                       ->execute([$visit_id, $patient_id, $doctor_id, $proc_id, $procedure['procedure_name'], $procedure['category'], $procedure['price'], $doctor_branch_id]);
                    $proc_id_inserted = $db->lastInsertId();
                    
                    $procedure_price = $procedure['price'];
                    $db->prepare("INSERT INTO bill_items (bill_id, patient_id, branch_id, item_type, item_id, item_name, quantity, unit_price, total_price, reference_id, reference_type, status, created_at) VALUES (?, ?, ?, 'procedure', ?, ?, 1, ?, ?, ?, 'procedure', 'pending', NOW())")
                       ->execute([$bill_id, $patient_id, $doctor_branch_id, $proc_id, $procedure['procedure_name'], $procedure_price, $procedure_price, $proc_id_inserted]);
                    $response['added']++;
                }
                $db->prepare("UPDATE visits SET status = 'prescribed', updated_at = NOW() WHERE id = ? AND status IN ('assigned', 'waiting', 'lab_test')")->execute([$visit_id]);
                $db->commit();
                updateBillTotal($db, $bill_id);
                $response['success'] = true;
                $response['message'] = '✅ ' . $response['added'] . ' procedure(s) added!';
            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                error_log("add_procedures_batch ERROR: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
                $response['message'] = '❌ Error: ' . $e->getMessage();
            }
        }
        echo json_encode($response);
        exit;
    }
    
    if ($action === 'add_equipment_batch') {
        header('Content-Type: application/json');
        if ($lock_sections) { echo json_encode(['success' => false, 'message' => '🔒 Cannot add equipment.']); exit; }
        
        try {
            saveDiagnosisToDatabase($db, $visit_id, $doctor_id, $doctor_branch_id, [
                'diagnosis_ids' => $_POST['diagnosis_ids'] ?? [], 'manual_diseases' => $_POST['manual_diseases'] ?? [],
                'manual_disease_codes' => $_POST['manual_disease_codes'] ?? [], 'treatment' => $_POST['treatment'] ?? '',
                'symptoms' => $_POST['symptoms'] ?? '', 'hpi' => $_POST['hpi'] ?? '',
                'physical_exam' => $_POST['physical_exam'] ?? '', 'notes' => $_POST['notes'] ?? ''
            ], true);
        } catch (Exception $e) { error_log("add_equipment_batch diagnosis save failed: " . $e->getMessage()); }
        
        $bill_id = ensureBillExists($db, $visit_id, $patient_id, $doctor_id, $doctor_branch_id, $bill_id);
        
        $equipment_data = isset($_POST['equipment_data']) ? json_decode($_POST['equipment_data'], true) : [];
        $response = ['success' => false, 'message' => '', 'added' => 0];
        if (!empty($equipment_data)) {
            try {
                $db->beginTransaction();
                foreach ($equipment_data as $eq_data) {
                    $equipment_id = (int)($eq_data['id'] ?? 0);
                    $quantity = (int)($eq_data['quantity'] ?? 1);
                    if ($equipment_id <= 0 || $quantity <= 0) continue;
                    
                    $stmt = $db->prepare("SELECT id, equipment_name, selling_price, quantity as stock FROM medical_equipment WHERE id = ? AND status = 'active' AND branch_id = ?");
                    $stmt->execute([$equipment_id, $doctor_branch_id]);
                    $equipment = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$equipment || $equipment['stock'] < $quantity) continue;
                    
                    $unit_price = $equipment['selling_price'] ?? 0;
                    $total_price = $unit_price * $quantity;
                    
                    $db->prepare("INSERT INTO bill_items (bill_id, patient_id, branch_id, item_type, item_id, item_name, quantity, unit_price, total_price, reference_id, reference_type, status, created_at) VALUES (?, ?, ?, 'equipment', ?, ?, ?, ?, ?, ?, 'equipment', 'pending', NOW())")
                       ->execute([$bill_id, $patient_id, $doctor_branch_id, $equipment_id, $equipment['equipment_name'], $quantity, $unit_price, $total_price, $equipment_id]);
                    $response['added']++;
                }
                $db->prepare("UPDATE visits SET status = 'prescribed', updated_at = NOW() WHERE id = ? AND status IN ('assigned', 'waiting', 'lab_test')")->execute([$visit_id]);
                $db->commit();
                updateBillTotal($db, $bill_id);
                $response['success'] = true;
                $response['message'] = '✅ ' . $response['added'] . ' equipment added!';
            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                error_log("add_equipment_batch ERROR: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
                $response['message'] = '❌ Error: ' . $e->getMessage();
            }
        }
        echo json_encode($response);
        exit;
    }
    
    if ($action === 'remove_added_item') {
        header('Content-Type: application/json');
        $type = $_POST['type'] ?? '';
        $item_id = (int)($_POST['id'] ?? 0);
        $response = ['success' => false, 'message' => ''];
        try {
            $bill_id = ensureBillExists($db, $visit_id, $patient_id, $doctor_id, $doctor_branch_id, $bill_id);
            $db->beginTransaction();
            if ($type === 'procedure') {
                deleteBillItemByReference($db, $bill_id, $item_id, 'procedure', 'procedure');
                $db->prepare("DELETE FROM procedures WHERE id = ? AND visit_id = ?")->execute([$item_id, $visit_id]);
                $response['message'] = '✅ Procedure removed!';
            } elseif ($type === 'equipment') {
                $stmt = $db->prepare("SELECT bi.* FROM bill_items bi WHERE bi.id = ? AND bi.bill_id = ?");
                $stmt->execute([$item_id, $bill_id]);
                $eq_item = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($eq_item && $eq_item['status'] !== 'paid') {
                    $db->prepare("DELETE FROM bill_items WHERE id = ?")->execute([$item_id]);
                    $response['message'] = '✅ Equipment removed!';
                }
            }
            $db->commit();
            updateBillTotal($db, $bill_id);
            $response['success'] = true;
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log("remove_added_item ERROR: " . $e->getMessage());
            $response['message'] = '❌ Error: ' . $e->getMessage();
        }
        echo json_encode($response);
        exit;
    }
    
    if (isset($_POST['send_lab'])) {
        $lab_cart = isset($_SESSION['lab_cart']) ? $_SESSION['lab_cart'] : [];
        if (empty($lab_cart)) {
            $_SESSION['flash_message'] = "❌ No lab tests in cart.";
            $_SESSION['flash_type'] = 'error';
            header('Location: consultation.php?visit_id=' . $visit_id);
            exit;
        }
        
        try {
            saveDiagnosisToDatabase($db, $visit_id, $doctor_id, $doctor_branch_id, [
                'diagnosis_ids' => $_POST['diagnosis_ids'] ?? [], 'manual_diseases' => $_POST['manual_diseases'] ?? [],
                'manual_disease_codes' => $_POST['manual_disease_codes'] ?? [], 'treatment' => $_POST['treatment'] ?? '',
                'symptoms' => $_POST['symptoms'] ?? '', 'hpi' => $_POST['hpi'] ?? '',
                'physical_exam' => $_POST['physical_exam'] ?? '', 'notes' => $_POST['notes'] ?? ''
            ], true);
        } catch (Exception $e) { error_log("send_lab diagnosis save failed: " . $e->getMessage()); }
        
        $bill_id = ensureBillExists($db, $visit_id, $patient_id, $doctor_id, $doctor_branch_id, $bill_id);
        
        $lab_tests_sent = 0;
        $total_lab_price = 0;
        
        try {
            $db->beginTransaction();
            foreach ($lab_cart as $cart_item) {
                $test_id = $cart_item['id'];
                $test_name = $cart_item['name'];
                $test_price = $cart_item['price'];
                
                $db->prepare("INSERT INTO lab_tests (visit_id, patient_id, doctor_id, test_id, test_name, test_price, status, branch_id, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, NOW())")
                   ->execute([$visit_id, $patient_id, $doctor_id, $test_id, $test_name, $test_price, $doctor_branch_id]);
                $lab_test_id = $db->lastInsertId();
                
                $db->prepare("INSERT INTO bill_items (bill_id, patient_id, branch_id, item_type, item_id, item_name, quantity, unit_price, total_price, reference_id, reference_type, status, created_at) VALUES (?, ?, ?, 'lab_test', ?, ?, 1, ?, ?, ?, 'lab_test', 'pending', NOW())")
                   ->execute([$bill_id, $patient_id, $doctor_branch_id, $lab_test_id, $test_name, $test_price, $test_price, $lab_test_id]);
                
                $total_lab_price += $test_price;
                $lab_tests_sent++;
            }
            
            if ($lab_tests_sent > 0) {
                $db->prepare("UPDATE visits SET status = 'lab_test', updated_at = NOW() WHERE id = ? AND status IN ('assigned', 'prescribed', 'waiting')")->execute([$visit_id]);
                updateBillTotal($db, $bill_id);
            }
            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log("send_lab ERROR: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            $_SESSION['flash_message'] = "❌ Error sending lab tests: " . $e->getMessage();
            $_SESSION['flash_type'] = 'error';
            header('Location: consultation.php?visit_id=' . $visit_id);
            exit;
        }
        
        unset($_SESSION['lab_cart']);
        if ($lab_tests_sent > 0) {
            $_SESSION['flash_message'] = "✅ " . $lab_tests_sent . " lab request(s) sent!";
            $_SESSION['flash_type'] = 'success';
        }
        header('Location: consultation.php?visit_id=' . $visit_id);
        exit;
    }
    
    if (isset($_POST['save_consultation'])) {
        $backend_has_completed_lab = false;
        $backend_total_lab = 0;
        $stmt_check = $db->prepare("SELECT status, results FROM lab_tests WHERE visit_id = ? AND status != 'cancelled'");
        $stmt_check->execute([$visit_id]);
        $backend_labs = $stmt_check->fetchAll(PDO::FETCH_ASSOC);
        foreach ($backend_labs as $bl) {
            $backend_total_lab++;
            if ($bl['status'] === 'completed' && !empty(trim($bl['results'] ?? ''))) $backend_has_completed_lab = true;
        }
        $backend_lock = ($backend_total_lab > 0 && !$backend_has_completed_lab);
        
        if ($backend_lock) {
            $_SESSION['flash_message'] = "🔒 Cannot save! Sections are locked.";
            $_SESSION['flash_type'] = 'error';
            header('Location: consultation.php?visit_id=' . $visit_id);
            exit;
        }
        
        $save_result = null;
        try {
            $save_result = saveDiagnosisToDatabase($db, $visit_id, $doctor_id, $doctor_branch_id, [
                'diagnosis_ids' => $_POST['diagnosis_ids'] ?? [], 'manual_diseases' => $_POST['manual_diseases'] ?? [],
                'manual_disease_codes' => $_POST['manual_disease_codes'] ?? [], 'treatment' => $_POST['treatment'] ?? '',
                'symptoms' => $_POST['symptoms'] ?? '', 'hpi' => $_POST['hpi'] ?? '',
                'physical_exam' => $_POST['physical_exam'] ?? '', 'notes' => $_POST['notes'] ?? ''
            ], true);
        } catch (Exception $e) {
            $_SESSION['flash_message'] = "❌ Error saving diagnosis: " . $e->getMessage();
            $_SESSION['flash_type'] = 'error';
            header('Location: consultation.php?visit_id=' . $visit_id);
            exit;
        }
        
        $has_diagnosis = false;
        if ($save_result && !empty($save_result['disease_names'])) $has_diagnosis = true;
        else {
            $stmt_check_diag = $db->prepare("SELECT diagnosis, disease_id FROM visits WHERE id = ?");
            $stmt_check_diag->execute([$visit_id]);
            $diag_row = $stmt_check_diag->fetch(PDO::FETCH_ASSOC);
            if ($diag_row && (!empty($diag_row['diagnosis']) || !empty($diag_row['disease_id']))) $has_diagnosis = true;
        }
        
        if (!$has_diagnosis) {
            $_SESSION['flash_message'] = "❌ Please enter at least one diagnosis before saving!";
            $_SESSION['flash_type'] = 'error';
            header('Location: consultation.php?visit_id=' . $visit_id);
            exit;
        }
        
        $db->prepare("UPDATE visits SET status = 'waiting', updated_at = NOW() WHERE id = ? AND doctor_id = ? AND status IN ('prescribed', 'lab_test', 'assigned')")->execute([$visit_id, $doctor_id]);
        
        $_SESSION['flash_message'] = "✅ Consultation saved!";
        $_SESSION['flash_type'] = 'success';
        header('Location: consultation.php?visit_id=' . $visit_id);
        exit;
    }
}

if (!isset($_SESSION['lab_cart'])) $_SESSION['lab_cart'] = [];
$lab_cart = $_SESSION['lab_cart'];
$lab_cart_total = array_sum(array_column($lab_cart, 'price'));
$lab_cart_count = count($lab_cart);

$valid_prescriptions = array_filter($prescriptions, function($med) {
    return !empty($med['medication_name']) && !empty($med['item_id']);
});

// ✅ V32: Group prescriptions by prescription_id (kwa batches nyingi)
$grouped_prescriptions = [];
foreach ($valid_prescriptions as $med) {
    $pid = $med['id'];
    if (!isset($grouped_prescriptions[$pid])) {
        $grouped_prescriptions[$pid] = [
            'prescription' => $med,
            'items' => [],
            'total_qty' => 0,
            'total_price' => 0
        ];
    }
    $grouped_prescriptions[$pid]['items'][] = $med;
    $grouped_prescriptions[$pid]['total_qty'] += $med['quantity'] ?? 0;
    $grouped_prescriptions[$pid]['total_price'] += $med['total_price'] ?? 0;
}

include_once __DIR__ . '/../../components/doctor_header.php';
include_once __DIR__ . '/../../components/doctor_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_completed ? 'View Consultation' : 'Consultation' ?> - Braick Dispensary</title>
    <link rel="icon" href="/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #0B5ED7; --primary-dark: #0A4CA8; --primary-light: #6EA8FE; --primary-bg: #E8F0FE;
            --primary-gradient: linear-gradient(135deg, #0B5ED7 0%, #1A7FE8 100%);
            --success: #059669; --success-dark: #047857; --success-bg: #D1FAE5;
            --success-gradient: linear-gradient(135deg, #059669 0%, #10B981 100%);
            --danger: #DC2626; --danger-dark: #B91C1C; --danger-bg: #FEE2E2;
            --danger-gradient: linear-gradient(135deg, #DC2626 0%, #EF4444 100%);
            --warning: #D97706; --warning-dark: #B45309; --warning-bg: #FEF3C7;
            --warning-gradient: linear-gradient(135deg, #D97706 0%, #F59E0B 100%);
            --purple: #7C3AED; --purple-bg: #EDE9FE;
            --purple-gradient: linear-gradient(135deg, #7C3AED 0%, #8B5CF6 100%);
            --sky: #0EA5E9; --sky-dark: #0284C7; --sky-bg: #E0F2FE;
            --sky-gradient: linear-gradient(135deg, #0EA5E9 0%, #38BDF8 100%);
            --blue-linked: #1E40AF; --blue-linked-bg: #DBEAFE; --blue-linked-light: #93C5FD;
            --gray-50: #F8FAFC; --gray-100: #F1F5F9; --gray-200: #E2E8F0; --gray-300: #CBD5E1;
            --gray-400: #94A3B8; --gray-500: #64748B; --gray-600: #475569; --gray-700: #334155;
            --radius: 12px; --radius-lg: 16px; --radius-xl: 20px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.04);
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 16px rgba(11,94,215,0.10);
            --shadow-lg: 0 8px 32px rgba(11,94,215,0.15);
            --bg-body: #F1F5F9; --bg-card: #ffffff;
            --text-primary: #1E293B; --text-secondary: #64748B; --border-color: #E2E8F0;
        }
        [data-theme="dark"] {
            --bg-body: #0F172A; --bg-card: #1E293B;
            --text-primary: #E2E8F0; --text-secondary: #94A3B8; --border-color: #334155;
            --gray-50: #1E293B; --gray-100: #1E293B; --gray-200: #334155;
            --primary-bg: #1E3A5F; --success-bg: #064E3B; --danger-bg: #7F1D1D;
            --warning-bg: #78350F; --purple-bg: #4C1D95; --sky-bg: #0C2A3A;
            --blue-linked-bg: #1E3A5F;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: var(--bg-body); color: var(--text-primary); font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif; line-height: 1.6; -webkit-font-smoothing: antialiased; }
        .mono, .big-number { font-family: 'JetBrains Mono', monospace !important; font-variant-numeric: tabular-nums; }
        .main-content { margin-left: 270px; margin-top: 68px; padding: 24px 28px; min-height: calc(100vh - 68px); max-width: 1400px; }
        
        .page-header {
            display: flex; justify-content: space-between; align-items: flex-start;
            flex-wrap: wrap; gap: 16px; margin-bottom: 24px; padding: 24px 28px;
            background: var(--primary-gradient); border-radius: var(--radius-xl);
            box-shadow: var(--shadow-lg); position: relative; color: #ffffff !important; overflow: hidden;
        }
        .page-header::before {
            content: ''; position: absolute; top: -50%; right: -10%;
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
            border-radius: 50%; pointer-events: none;
        }
        .page-header * { color: #ffffff !important; position: relative; z-index: 1; }
        .page-title { font-size: 1.5rem; font-weight: 800; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin: 0; }
        .page-title i { font-size: 1.8rem; }
        .page-badge { font-size: 0.7rem; font-weight: 700; background: rgba(255,255,255,0.2); padding: 5px 16px; border-radius: 20px; font-family: 'JetBrains Mono', monospace; border: 1px solid rgba(255,255,255,0.25); }
        .page-subtitle { font-size: 0.85rem; opacity: 0.9; margin-top: 8px; display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
        .status-badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 14px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; background: rgba(255,255,255,0.2) !important; color: #ffffff !important; border: 1px solid rgba(255,255,255,0.3); text-transform: uppercase; }
        
        .saved-indicator {
            position: fixed; top: 80px; right: 30px;
            background: linear-gradient(135deg, #059669, #10B981);
            color: white; padding: 12px 20px; border-radius: 12px;
            box-shadow: 0 8px 24px rgba(5,150,105,0.4); z-index: 9999;
            display: flex; align-items: center; gap: 10px;
            font-size: 0.85rem; font-weight: 700;
            transform: translateX(400px); opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .saved-indicator.show { transform: translateX(0); opacity: 1; }
        
        .bill-summary-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 14px; margin-bottom: 20px; }
        .bill-card {
            background: var(--bg-card); border-radius: var(--radius-lg);
            padding: 20px 16px 16px; border: 2px solid var(--border-color);
            transition: var(--transition); position: relative; overflow: hidden;
            display: flex; flex-direction: column; align-items: center;
            text-align: center; min-height: 160px; box-shadow: var(--shadow);
        }
        .bill-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); }
        .bill-card .bill-icon {
            width: 52px; height: 52px; border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem; margin-bottom: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .bill-card .bill-label { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-secondary); margin-bottom: 6px; display: block; }
        .bill-card .bill-value { font-size: 1.1rem; font-weight: 800; font-family: 'JetBrains Mono', monospace; line-height: 1.2; word-break: break-all; overflow-wrap: anywhere; max-width: 100%; display: block; }
        .bill-card .bill-sub { font-size: 0.6rem; color: var(--text-secondary); margin-top: 6px; font-weight: 500; }
        .bill-card.total { border-color: #0B5ED7; }
        .bill-card.total .bill-icon { background: var(--primary-gradient); color: white; }
        .bill-card.total .bill-value { color: #0B5ED7; }
        .bill-card.paid { border-color: #059669; }
        .bill-card.paid .bill-icon { background: var(--success-gradient); color: white; }
        .bill-card.paid .bill-value { color: #059669; }
        .bill-card.remaining { border-color: #DC2626; }
        .bill-card.remaining .bill-icon { background: var(--danger-gradient); color: white; }
        .bill-card.remaining .bill-value { color: #DC2626; }
        .bill-card.remaining.zero { border-color: #059669; }
        .bill-card.remaining.zero .bill-icon { background: var(--success-gradient); }
        .bill-card.remaining.zero .bill-value { color: #059669; }
        .bill-card.discount { border-color: #7C3AED; }
        .bill-card.discount .bill-icon { background: var(--purple-gradient); color: white; }
        .bill-card.discount .bill-value { color: #7C3AED; }
        .bill-card.premium { border-color: #D97706; }
        .bill-card.premium .bill-icon { background: var(--warning-gradient); color: white; }
        .bill-card.premium .bill-value { color: #D97706; }
        
        .section-card {
            background: var(--bg-card); border-radius: var(--radius-lg);
            border: 3px solid var(--border-color); margin-bottom: 20px;
            box-shadow: var(--shadow); overflow: hidden; transition: var(--transition);
        }
        .section-card:hover { box-shadow: var(--shadow-md); }
        .section-card.section-primary { border-color: var(--primary); }
        .section-card.section-success { border-color: var(--success); }
        .section-card.section-warning { border-color: var(--warning); }
        .section-card.section-purple { border-color: var(--purple); }
        .section-card.section-sky { border-color: var(--sky); }
        .section-card.section-danger { border-color: var(--danger); }
        .section-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 18px 24px; border-bottom: 3px solid var(--border-color);
            flex-wrap: wrap; gap: 12px;
            background: linear-gradient(135deg, var(--gray-50), var(--bg-card));
        }
        .section-card.section-primary .section-header { background: linear-gradient(135deg, var(--primary-bg), var(--bg-card)); border-bottom-color: var(--primary); }
        .section-card.section-success .section-header { background: linear-gradient(135deg, var(--success-bg), var(--bg-card)); border-bottom-color: var(--success); }
        .section-card.section-warning .section-header { background: linear-gradient(135deg, var(--warning-bg), var(--bg-card)); border-bottom-color: var(--warning); }
        .section-card.section-purple .section-header { background: linear-gradient(135deg, var(--purple-bg), var(--bg-card)); border-bottom-color: var(--purple); }
        .section-card.section-sky .section-header { background: linear-gradient(135deg, var(--sky-bg), var(--bg-card)); border-bottom-color: var(--sky); }
        .section-card.section-danger .section-header { background: linear-gradient(135deg, var(--danger-bg), var(--bg-card)); border-bottom-color: var(--danger); }
        .section-title { display: flex; align-items: center; gap: 12px; font-size: 1rem; font-weight: 800; color: var(--text-primary); }
        .section-title .title-icon {
            width: 42px; height: 42px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.15rem; color: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .section-body { padding: 20px 24px; }
        
        .patient-info-wrapper {
            display: flex; align-items: center; gap: 24px; padding: 24px;
            background: linear-gradient(135deg, var(--primary-bg), var(--bg-card));
            border-radius: var(--radius-lg); margin-bottom: 20px;
            border: 2px solid var(--primary-light); flex-wrap: wrap;
        }
        .patient-avatar-large {
            width: 90px; height: 90px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 2.4rem; font-weight: 800; color: white;
            flex-shrink: 0; position: relative;
            box-shadow: 0 8px 24px rgba(0,0,0,0.2);
            border: 4px solid var(--bg-card);
        }
        .patient-info-main { flex: 1; min-width: 250px; }
        .patient-info-main h2 { font-size: 1.4rem; font-weight: 800; color: var(--text-primary); display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 4px; }
        .patient-id-badge { font-size: 0.7rem; font-weight: 700; font-family: 'JetBrains Mono', monospace; background: var(--primary); color: white; padding: 3px 12px; border-radius: 12px; }
        .patient-demographics { display: flex; gap: 12px; flex-wrap: wrap; font-size: 0.8rem; color: var(--text-secondary); margin-top: 6px; }
        .patient-demographics span { display: inline-flex; align-items: center; gap: 5px; }
        .patient-demographics i { color: var(--primary); }
        .patient-info-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 16px; width: 100%; }
        .info-cell { background: var(--bg-card); padding: 12px 16px; border-radius: var(--radius); border: 1px solid var(--border-color); transition: var(--transition); }
        .info-cell:hover { border-color: var(--primary); transform: translateY(-2px); box-shadow: var(--shadow); }
        .info-cell .info-label { font-size: 0.6rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary); display: flex; align-items: center; gap: 5px; margin-bottom: 4px; }
        .info-cell .info-label i { color: var(--primary); font-size: 0.7rem; }
        .info-cell .info-value { font-size: 0.88rem; font-weight: 600; color: var(--text-primary); }
        .blood-group-badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 800; background: linear-gradient(135deg, #DC2626, #EF4444); color: white; }
        .allergy-badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 700; background: linear-gradient(135deg, #D97706, #F59E0B); color: white; }
        
        .visit-info-wrapper { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
        .visit-info-cell {
            background: linear-gradient(135deg, var(--gray-50), var(--bg-card));
            padding: 16px 18px; border-radius: var(--radius);
            border: 2px solid var(--border-color); transition: var(--transition);
            display: flex; align-items: center; gap: 14px;
        }
        .visit-info-cell:hover { border-color: var(--primary); transform: translateY(-3px); box-shadow: var(--shadow-md); }
        .visit-info-cell .cell-icon { width: 44px; height: 44px; border-radius: 12px; background: var(--primary-bg); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
        .visit-info-cell.visit-number .cell-icon { background: var(--warning-bg); color: var(--warning); }
        .visit-info-cell.visit-date .cell-icon { background: var(--success-bg); color: var(--success); }
        .visit-info-cell.doctor .cell-icon { background: var(--purple-bg); color: var(--purple); }
        .visit-info-cell.branch .cell-icon { background: var(--sky-bg); color: var(--sky-dark); }
        .visit-info-cell .cell-content { flex: 1; min-width: 0; }
        .visit-info-cell .cell-label { font-size: 0.6rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary); margin-bottom: 3px; }
        .visit-info-cell .cell-value { font-size: 0.9rem; font-weight: 700; color: var(--text-primary); font-family: 'JetBrains Mono', monospace; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        
        .vitals-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; }
        .vital-card {
            background: var(--bg-card); border-radius: var(--radius-lg);
            padding: 18px 16px; border: 2px solid var(--border-color);
            transition: var(--transition); position: relative;
            overflow: hidden; text-align: center;
        }
        .vital-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; }
        .vital-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); }
        .vital-card .vital-icon-wrap {
            width: 48px; height: 48px; margin: 0 auto 10px;
            border-radius: 14px; display: flex; align-items: center;
            justify-content: center; font-size: 1.3rem; color: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .vital-card .vital-name { font-size: 0.62rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-secondary); margin-bottom: 6px; }
        .vital-card .vital-reading { font-size: 1.4rem; font-weight: 800; font-family: 'JetBrains Mono', monospace; line-height: 1.1; word-break: break-all; }
        .vital-card .vital-reading .unit { font-size: 0.7rem; font-weight: 600; color: var(--text-secondary); margin-left: 3px; }
        .vital-card .vital-range { font-size: 0.6rem; color: var(--text-secondary); margin-top: 6px; font-weight: 500; }
        .vital-card .vital-status {
            display: inline-block; margin-top: 8px; padding: 3px 12px;
            border-radius: 12px; font-size: 0.6rem; font-weight: 800;
            text-transform: uppercase; letter-spacing: 0.05em;
        }
        .vital-card.temp::before { background: linear-gradient(90deg, #EF4444, #F87171); }
        .vital-card.temp .vital-icon-wrap { background: linear-gradient(135deg, #EF4444, #F87171); }
        .vital-card.temp .vital-name, .vital-card.temp .vital-reading { color: #DC2626; }
        .vital-card.bp::before { background: linear-gradient(90deg, #3B82F6, #60A5FA); }
        .vital-card.bp .vital-icon-wrap { background: linear-gradient(135deg, #3B82F6, #60A5FA); }
        .vital-card.bp .vital-name, .vital-card.bp .vital-reading { color: #2563EB; }
        .vital-card.pulse::before { background: linear-gradient(90deg, #EC4899, #F472B6); }
        .vital-card.pulse .vital-icon-wrap { background: linear-gradient(135deg, #EC4899, #F472B6); }
        .vital-card.pulse .vital-name, .vital-card.pulse .vital-reading { color: #DB2777; }
        .vital-card.spo2::before { background: linear-gradient(90deg, #0EA5E9, #38BDF8); }
        .vital-card.spo2 .vital-icon-wrap { background: linear-gradient(135deg, #0EA5E9, #38BDF8); }
        .vital-card.spo2 .vital-name, .vital-card.spo2 .vital-reading { color: #0284C7; }
        .vital-card.weight::before { background: linear-gradient(90deg, #8B5CF6, #A78BFA); }
        .vital-card.weight .vital-icon-wrap { background: linear-gradient(135deg, #8B5CF6, #A78BFA); }
        .vital-card.weight .vital-name, .vital-card.weight .vital-reading { color: #7C3AED; }
        .vital-card.height::before { background: linear-gradient(90deg, #22C55E, #4ADE80); }
        .vital-card.height .vital-icon-wrap { background: linear-gradient(135deg, #22C55E, #4ADE80); }
        .vital-card.height .vital-name, .vital-card.height .vital-reading { color: #16A34A; }
        .vital-card.bmi::before { background: linear-gradient(90deg, #F59E0B, #FBBF24); }
        .vital-card.bmi .vital-icon-wrap { background: linear-gradient(135deg, #F59E0B, #FBBF24); }
        .vital-card.bmi .vital-name, .vital-card.bmi .vital-reading { color: #D97706; }
        .vital-card.notes-card::before { background: linear-gradient(90deg, #64748B, #94A3B8); }
        .vital-card.notes-card .vital-icon-wrap { background: linear-gradient(135deg, #64748B, #94A3B8); }
        .vital-card.notes-card .vital-name { color: #475569; }
        .vital-card.notes-card .vital-reading { font-size: 0.85rem; font-family: 'Inter', sans-serif; font-weight: 600; color: #475569; }
        .vital-status.normal { background: linear-gradient(135deg, #D1FAE5, #A7F3D0); color: #047857; border: 1px solid #6EE7B7; }
        .vital-status.warning { background: linear-gradient(135deg, #FEF3C7, #FDE68A); color: #B45309; border: 1px solid #FCD34D; }
        .vital-status.critical { background: linear-gradient(135deg, #FEE2E2, #FECACA); color: #991B1B; border: 1px solid #FCA5A5; }
        .vital-status.low { background: linear-gradient(135deg, #DBEAFE, #BFDBFE); color: #1E40AF; border: 1px solid #93C5FD; }
        .vital-status.unknown { background: var(--gray-200); color: var(--text-secondary); }
        .vitals-footer {
            margin-top: 16px; padding: 12px 18px;
            background: linear-gradient(135deg, var(--gray-50), var(--primary-bg));
            border-radius: var(--radius); border: 1px dashed var(--primary-light);
            display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
            font-size: 0.75rem; color: var(--text-secondary);
        }
        
        .complaint-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
        .complaint-box {
            background: linear-gradient(135deg, var(--gray-50), var(--bg-card));
            border-radius: var(--radius-lg); padding: 18px 20px;
            border: 3px solid var(--border-color);
            display: flex; flex-direction: column;
            transition: var(--transition); min-height: 220px;
        }
        .complaint-box:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
        .complaint-box.complaint-notes { border-color: var(--purple); }
        .complaint-box.complaint-chief { border-color: var(--warning); }
        .complaint-box.complaint-hpi { border-color: var(--sky); }
        .complaint-box.complaint-exam { border-color: var(--success); }
        .complaint-box .box-header {
            display: flex; align-items: center; gap: 10px;
            margin-bottom: 12px; padding-bottom: 10px;
            border-bottom: 3px dashed var(--border-color);
            font-size: 0.9rem; font-weight: 800; color: var(--text-primary);
        }
        .complaint-box .box-header .box-icon {
            width: 36px; height: 36px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.95rem; color: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .complaint-box.complaint-notes .box-header { color: var(--purple); }
        .complaint-box.complaint-notes .box-icon { background: var(--purple-gradient); }
        .complaint-box.complaint-chief .box-header { color: var(--warning); }
        .complaint-box.complaint-chief .box-icon { background: var(--warning-gradient); }
        .complaint-box.complaint-hpi .box-header { color: var(--sky-dark); }
        .complaint-box.complaint-hpi .box-icon { background: var(--sky-gradient); }
        .complaint-box.complaint-exam .box-header { color: var(--success); }
        .complaint-box.complaint-exam .box-icon { background: var(--success-gradient); }
        .complaint-box .box-body { flex: 1; display: flex; flex-direction: column; }
        .complaint-box textarea {
            flex: 1; width: 100%; padding: 12px 14px;
            border: 2px solid var(--border-color); border-radius: var(--radius);
            font-size: 0.85rem; background: var(--bg-card); color: var(--text-primary);
            outline: none; transition: var(--transition);
            font-family: inherit; resize: vertical; min-height: 90px;
        }
        .complaint-box textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(11,94,215,0.12); }
        .complaint-chip {
            padding: 4px 12px; border-radius: 16px;
            background: var(--primary-bg); color: var(--primary);
            font-size: 0.75rem; font-weight: 600;
            display: inline-flex; align-items: center; gap: 6px;
            border: 1px solid var(--primary-light); transition: var(--transition);
        }
        .complaint-chip .chip-remove { cursor: pointer; font-weight: 800; opacity: 0.7; font-size: 0.9rem; line-height: 1; }
        .complaint-chip .chip-remove:hover { opacity: 1; }
        
        .dropdown-section {
            border: 3px solid var(--border-color); border-radius: var(--radius-lg);
            margin-bottom: 16px; overflow: hidden; transition: var(--transition);
            background: var(--bg-card); box-shadow: var(--shadow-sm);
        }
        .dropdown-section:hover { box-shadow: var(--shadow-md); }
        .dropdown-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 18px 24px; cursor: pointer; user-select: none;
            transition: var(--transition);
            background: linear-gradient(135deg, var(--gray-50), var(--bg-card));
            gap: 12px; flex-wrap: wrap;
        }
        .dropdown-header:hover { background: linear-gradient(135deg, var(--primary-bg), var(--bg-card)); }
        .dropdown-header .dh-left { display: flex; align-items: center; gap: 12px; flex: 1; min-width: 200px; }
        .dropdown-header .dh-icon {
            width: 42px; height: 42px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; color: white; flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .dropdown-header .dh-title { display: flex; flex-direction: column; gap: 2px; }
        .dropdown-header .dh-title h4 { font-size: 0.95rem; font-weight: 800; color: var(--text-primary); margin: 0; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .dropdown-header .dh-title p { font-size: 0.72rem; color: var(--text-secondary); margin: 0; font-weight: 500; }
        .dropdown-header .dh-right { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .dropdown-header .dh-stat {
            padding: 4px 12px; border-radius: 12px; font-size: 0.7rem; font-weight: 700;
            font-family: 'JetBrains Mono', monospace;
            display: inline-flex; align-items: center; gap: 5px;
        }
        .dropdown-header .dh-chevron { color: var(--text-secondary); font-size: 0.9rem; transition: var(--transition); }
        .dropdown-header.active .dh-chevron { transform: rotate(180deg); }
        .dropdown-body {
            padding: 0; max-height: 0; overflow: hidden;
            transition: max-height 0.5s cubic-bezier(0.4, 0, 0.2, 1), padding 0.3s ease;
            background: var(--bg-card);
        }
        .dropdown-body.open { max-height: 2500px; padding: 20px 24px 24px; border-top: 3px solid var(--border-color); }
        
        .items-grid {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; padding: 16px;
            background: linear-gradient(135deg, var(--gray-50) 0%, var(--gray-100) 100%);
            border-radius: var(--radius); max-height: 520px; overflow-y: auto;
            border: 2px solid var(--border-color);
        }
        .items-grid::-webkit-scrollbar { width: 10px; }
        .items-grid::-webkit-scrollbar-track { background: var(--gray-100); border-radius: 5px; }
        .items-grid::-webkit-scrollbar-thumb { background: linear-gradient(135deg, var(--primary-light), var(--primary)); border-radius: 5px; border: 2px solid var(--gray-100); }
        
        .item-card {
            display: flex; align-items: flex-start; gap: 12px;
            padding: 16px 14px; border-radius: 12px; font-size: 0.8rem;
            border: 3px solid var(--border-color); cursor: pointer;
            transition: var(--transition); user-select: none;
            color: var(--text-primary); background: var(--bg-card);
            position: relative; min-height: 130px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            flex-direction: column;
        }
        .item-card .item-check {
            width: 26px; height: 26px; border: 2px solid var(--border-color);
            border-radius: 8px; display: flex; align-items: center;
            justify-content: center; flex-shrink: 0;
            transition: var(--transition); background: var(--bg-card);
        }
        .item-card .item-check i { font-size: 0.75rem; color: white; opacity: 0; transition: var(--transition); }
        .item-card.selected .item-check { background: var(--primary); border-color: var(--primary); transform: scale(1.1); }
        .item-card.selected .item-check i { opacity: 1; }
        .item-card .item-info { display: flex; flex-direction: column; flex: 1; min-width: 0; gap: 8px; width: 100%; }
        .item-card .item-name {
            font-weight: 800; font-size: 0.92rem; color: var(--text-primary);
            line-height: 1.3; display: flex; align-items: center; flex-wrap: wrap; gap: 6px;
        }
        .item-card .item-details { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
        .item-card .item-price {
            font-size: 0.78rem; color: var(--success); font-weight: 800;
            padding: 4px 12px; background: var(--success-bg); border-radius: 20px;
            display: inline-flex; align-items: center; gap: 4px;
            font-family: 'JetBrains Mono', monospace; border: 1px solid var(--success);
        }
        .item-card .item-tag { font-size: 0.65rem; padding: 4px 12px; border-radius: 20px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; }
        .item-card .item-tag.category { color: var(--purple); background: var(--purple-bg); border: 1px solid var(--purple); }
        .item-card .item-tag.stock { color: var(--text-secondary); background: var(--gray-100); border: 1px solid var(--border-color); }
        .item-card .item-tag.stock.ok { color: var(--success); background: var(--success-bg); border-color: var(--success); }
        .item-card .item-tag.stock.low { color: var(--warning); background: var(--warning-bg); border-color: var(--warning); }
        .item-card .item-tag.stock.out { color: var(--danger); background: var(--danger-bg); border-color: var(--danger); }
        .item-card .item-tag.expiry.valid { color: var(--success); background: var(--success-bg); border: 1px solid var(--success); }
        .item-card .item-tag.expiry.expiring { color: var(--warning); background: var(--warning-bg); border: 1px solid var(--warning); }
        .item-card .item-tag.expiry.expired { color: var(--danger); background: var(--danger-bg); border: 1px solid var(--danger); font-weight: 700; }
        .item-card .item-tag.expiry.no-expiry { color: var(--text-secondary); background: var(--gray-100); border: 1px solid var(--border-color); }
        .item-card .item-status-badge {
            font-size: 0.55rem; font-weight: 800; padding: 3px 10px;
            border-radius: 10px; letter-spacing: 0.05em; text-transform: uppercase;
        }
        .item-card .item-status-badge.expired { background: linear-gradient(135deg, #DC2626, #EF4444); color: white; }
        .item-card .item-status-badge.out { background: linear-gradient(135deg, #D97706, #F59E0B); color: white; }
        .item-card .item-status-badge.inactive { background: linear-gradient(135deg, #64748B, #94A3B8); color: white; }
        .item-card.disabled {
            opacity: 0.55; cursor: not-allowed !important;
            border-color: var(--danger);
            background: linear-gradient(135deg, #FEF2F2 0%, #FEE2E2 100%);
        }
        .item-card.disabled:hover { transform: none !important; box-shadow: none !important; }
        .item-card.disabled .item-name { color: var(--danger) !important; text-decoration: line-through; }
        
        .item-card.medication-card.selected { border-color: #059669; background: linear-gradient(135deg, #D1FAE5 0%, #A7F3D0 100%); }
        .item-card.medication-card.selected .item-check { background: #059669; border-color: #059669; }
        .item-card.labtest-card.selected { border-color: #0284C7; background: linear-gradient(135deg, #E0F2FE 0%, #BAE6FD 100%); }
        .item-card.labtest-card.selected .item-check { background: #0284C7; border-color: #0284C7; }
        
        .item-card.labtest-card.linked-equipment {
            border-color: var(--blue-linked);
            background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 50%, #BFDBFE 100%);
            box-shadow: 0 4px 16px rgba(30, 64, 175, 0.15);
        }
        .item-card.labtest-card.linked-equipment::before {
            content: ''; position: absolute; top: 0; left: 0;
            width: 5px; height: 100%;
            background: linear-gradient(180deg, #1E40AF, #3B82F6, #60A5FA);
            border-radius: 12px 0 0 12px;
        }
        .item-card.labtest-card.linked-equipment .item-name { color: var(--blue-linked); font-weight: 900; }
        .item-card.labtest-card.linked-equipment .item-price {
            background: linear-gradient(135deg, #DBEAFE, #BFDBFE);
            color: var(--blue-linked); border-color: var(--blue-linked-light);
        }
        .item-card.labtest-card.linked-equipment .item-tag.category {
            background: var(--blue-linked-bg); color: var(--blue-linked); border-color: var(--blue-linked-light);
        }
        .item-card.labtest-card.linked-equipment.selected {
            border-color: var(--blue-linked);
            background: linear-gradient(135deg, #BFDBFE 0%, #93C5FD 100%);
            box-shadow: 0 8px 24px rgba(30, 64, 175, 0.25);
        }
        .item-card.labtest-card.linked-equipment.selected .item-check {
            background: var(--blue-linked); border-color: var(--blue-linked);
        }
        [data-theme="dark"] .item-card.labtest-card.linked-equipment {
            background: linear-gradient(135deg, #1E3A5F 0%, #1E40AF 100%); border-color: #60A5FA;
        }
        [data-theme="dark"] .item-card.labtest-card.linked-equipment .item-name { color: #93C5FD; }
        
        .item-card.procedure-card.selected { border-color: #7C3AED; background: linear-gradient(135deg, #EDE9FE 0%, #DDD6FE 100%); }
        .item-card.procedure-card.selected .item-check { background: #7C3AED; border-color: #7C3AED; }
        .item-card.equipment-card.selected { border-color: #D97706; background: linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%); }
        .item-card.equipment-card.selected .item-check { background: #D97706; border-color: #D97706; }
        
        .item-card .equipment-list {
            margin-top: 8px; padding: 8px 10px;
            background: linear-gradient(135deg, #F0F9FF 0%, #E0F2FE 100%);
            border-radius: 8px; border: 1px solid rgba(14, 165, 233, 0.3);
            display: flex; flex-direction: column; gap: 5px;
        }
        [data-theme="dark"] .item-card .equipment-list {
            background: linear-gradient(135deg, #0C2A3A 0%, #1E3A5F 100%);
            border-color: rgba(96, 165, 250, 0.3);
        }
        .item-card .equipment-list-header {
            font-size: 0.65rem; font-weight: 800; color: #0284C7;
            display: flex; align-items: center; gap: 5px;
            padding-bottom: 4px; border-bottom: 1px dashed rgba(14, 165, 233, 0.3);
            margin-bottom: 3px;
        }
        .item-card .equipment-item-row {
            display: flex; align-items: center; justify-content: space-between;
            gap: 6px; font-size: 0.65rem; padding: 4px 6px;
            background: rgba(255, 255, 255, 0.8); border-radius: 6px;
            border: 1px solid rgba(14, 165, 233, 0.15); flex-wrap: wrap;
        }
        [data-theme="dark"] .item-card .equipment-item-row { background: rgba(15, 23, 42, 0.6); }
        .item-card .equipment-item-row .eq-name {
            font-weight: 700; color: #0369A1;
            display: flex; align-items: center; gap: 4px;
            flex: 1; min-width: 80px;
        }
        .item-card .equipment-item-row .eq-name i { color: #0EA5E9; font-size: 0.65rem; }
        .item-card .equipment-item-row .eq-stock-badge {
            font-size: 0.6rem; font-weight: 800; padding: 2px 8px;
            border-radius: 8px; white-space: nowrap;
            font-family: 'JetBrains Mono', monospace;
        }
        .item-card .equipment-item-row .eq-stock-badge.ok { background: linear-gradient(135deg, #D1FAE5, #A7F3D0); color: #047857; border: 1px solid #6EE7B7; }
        .item-card .equipment-item-row .eq-stock-badge.low { background: linear-gradient(135deg, #FEF3C7, #FDE68A); color: #B45309; border: 1px solid #FCD34D; }
        .item-card .equipment-item-row .eq-stock-badge.out { background: linear-gradient(135deg, #FEE2E2, #FECACA); color: #991B1B; border: 1px solid #FCA5A5; }
        
        /* ✅ V32: Multi-batch badge */
        .item-card .multi-batch-badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 3px 10px; background: linear-gradient(135deg, #DBEAFE, #BFDBFE);
            color: #1E40AF; border-radius: 10px; font-size: 0.6rem; font-weight: 800;
            border: 1px solid #60A5FA; margin-top: 6px; width: 100%;
            justify-content: center; text-transform: uppercase; letter-spacing: 0.03em;
        }
        [data-theme="dark"] .item-card .multi-batch-badge {
            background: linear-gradient(135deg, #1E3A5F, #1E40AF);
            color: #93C5FD; border-color: #3B82F6;
        }
        
        .empty-state-grid { grid-column: 1 / -1; text-align: center; padding: 40px 20px; color: var(--text-secondary); }
        .empty-state-grid i { font-size: 3rem; display: block; margin-bottom: 12px; opacity: 0.3; }
        
        .dropdown-search-row { display: flex; gap: 12px; margin-bottom: 14px; flex-wrap: wrap; align-items: center; }
        .dropdown-search-row .search-input-wrap { position: relative; flex: 1; min-width: 220px; }
        .dropdown-search-row .search-input-wrap i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-secondary); font-size: 0.85rem; }
        .dropdown-search-row input {
            width: 100%; padding: 11px 14px 11px 40px;
            border: 2px solid var(--border-color); border-radius: var(--radius);
            font-size: 0.85rem; background: var(--bg-card); color: var(--text-primary);
            outline: none; transition: var(--transition);
        }
        .dropdown-search-row input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(11,94,215,0.12); }
        
        .dropdown-action-row {
            display: flex; gap: 12px; margin-top: 16px; flex-wrap: wrap;
            align-items: center; padding-top: 16px;
            border-top: 2px dashed var(--border-color);
        }
        .selected-badge {
            padding: 6px 14px; border-radius: 20px; font-size: 0.75rem; font-weight: 700;
            font-family: 'JetBrains Mono', monospace;
            background: var(--primary-bg); color: var(--primary);
            display: inline-flex; align-items: center; gap: 6px;
            border: 1px solid var(--primary-light);
        }
        
        .form-group { margin-bottom: 14px; }
        .form-label { display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-secondary); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.04em; }
        .required { color: var(--danger); margin-left: 3px; }
        .form-control {
            width: 100%; padding: 11px 14px;
            border: 2px solid var(--border-color); border-radius: var(--radius);
            font-size: 0.88rem; background: var(--bg-card); color: var(--text-primary);
            outline: none; transition: var(--transition); font-family: inherit;
        }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(11,94,215,0.12); }
        .row-2col { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .btn-manual-toggle {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 3px 12px; margin-left: 8px;
            border-radius: 12px; border: 1px solid var(--primary-light);
            background: var(--primary-bg); color: var(--primary);
            font-size: 0.6rem; font-weight: 700; cursor: pointer;
            transition: var(--transition); vertical-align: middle;
            text-transform: none; letter-spacing: 0; font-family: 'Inter', sans-serif;
        }
        .btn-manual-toggle:hover { background: var(--primary); color: #ffffff; transform: translateY(-1px); box-shadow: 0 2px 8px rgba(11,94,215,0.3); }
        .btn-manual-toggle.active { background: var(--success); border-color: var(--success); color: #ffffff; }
        .manual-input-field { display: none; margin-top: 8px; border-color: var(--success) !important; }
        .manual-input-field.show { display: block; }
        
        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            gap: 8px; padding: 10px 22px; border-radius: 10px;
            font-weight: 700; font-size: 0.82rem;
            transition: var(--transition); cursor: pointer;
            border: none; text-decoration: none; white-space: nowrap;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover:not(:disabled) { background: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(11,94,215,0.3); }
        .btn-success { background: var(--success); color: white; }
        .btn-success:hover:not(:disabled) { background: var(--success-dark); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(5,150,105,0.3); }
        .btn-warning { background: var(--warning); color: white; }
        .btn-warning:hover:not(:disabled) { background: var(--warning-dark); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(217,119,6,0.3); }
        .btn-outline { background: transparent; color: var(--text-primary); border: 2px solid var(--border-color); }
        .btn-outline:hover:not(:disabled) { background: var(--gray-100); border-color: var(--gray-400); }
        .btn-sm { padding: 6px 14px; font-size: 0.72rem; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none !important; }
        
        .status-flow {
            display: flex; align-items: center; gap: 6px;
            padding: 12px 16px; background: linear-gradient(135deg, var(--gray-50), var(--bg-card));
            border-radius: var(--radius); border: 2px solid var(--border-color); flex-wrap: wrap;
        }
        .status-step {
            display: flex; align-items: center; gap: 5px;
            font-size: 0.7rem; padding: 5px 14px; border-radius: 16px;
            background: var(--gray-200); color: var(--text-secondary); font-weight: 700;
        }
        .status-step.active { background: var(--primary); color: white; box-shadow: 0 4px 12px rgba(11,94,215,0.3); }
        .status-step.completed { background: var(--success); color: white; }
        .status-arrow { color: var(--text-secondary); font-size: 0.7rem; }
        
        .badge-pending { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning); }
        .badge-confirmed { background: var(--primary-bg); color: var(--primary); border: 1px solid var(--primary); }
        .badge-dispensed { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
        .badge-cancelled { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger); }
        .badge-paid { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
        
        .saved-banner {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 4px 12px; border-radius: 12px;
            font-size: 0.65rem; font-weight: 800;
            background: linear-gradient(135deg, #D1FAE5, #A7F3D0);
            color: #047857; border: 1px solid #10B981;
            animation: pulseGreen 2s ease-in-out infinite;
        }
        @keyframes pulseGreen {
            0%, 100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); }
            50% { box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
        }
        
        .med-form-card {
            background: var(--bg-card); border-radius: var(--radius-lg);
            border: 3px solid var(--success); overflow: hidden;
            box-shadow: var(--shadow-md); margin-top: 16px;
        }
        .med-form-header {
            background: linear-gradient(135deg, var(--success), var(--success-dark));
            padding: 16px 20px; position: relative; overflow: hidden;
        }
        .med-form-header::before {
            content: ''; position: absolute; top: -50%; right: -10%;
            width: 300px; height: 300px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            border-radius: 50%; pointer-events: none;
        }
        .med-form-header .header-title {
            color: white; font-size: 0.85rem; font-weight: 800;
            display: flex; align-items: center; gap: 8px;
            margin-bottom: 10px; position: relative; z-index: 1;
        }
        .med-form-header .header-title i {
            font-size: 1rem; width: 28px; height: 28px;
            background: rgba(255,255,255,0.2); border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            border: 1px solid rgba(255,255,255,0.3);
        }
        .med-form-header .quick-picks {
            display: flex; flex-wrap: wrap; gap: 5px;
            margin-bottom: 10px; position: relative; z-index: 1;
        }
        .med-form-header .quick-pick-btn {
            padding: 4px 10px; border-radius: 14px;
            font-size: 0.62rem; font-weight: 700;
            border: 1.5px solid rgba(255,255,255,0.3);
            background: rgba(255,255,255,0.15); color: white;
            cursor: pointer; transition: var(--transition);
            font-family: 'Inter', sans-serif;
        }
        .med-form-header .quick-pick-btn:hover {
            background: rgba(255,255,255,0.3); border-color: white; transform: translateY(-1px);
        }
        .med-form-header .quick-pick-btn.selected {
            background: white; color: var(--success); border-color: white; font-weight: 800;
        }
        .med-form-header .instr-textarea {
            width: 100%; padding: 8px 12px;
            border: 2px solid rgba(255,255,255,0.3); border-radius: var(--radius);
            font-size: 0.8rem; background: rgba(255,255,255,0.15);
            color: white; outline: none; transition: var(--transition);
            font-family: inherit; resize: vertical; min-height: 38px;
            position: relative; z-index: 1;
        }
        .med-form-header .instr-textarea::placeholder { color: rgba(255,255,255,0.6); }
        .med-form-header .instr-textarea:focus {
            background: rgba(255,255,255,0.25); border-color: white;
            box-shadow: 0 0 0 3px rgba(255,255,255,0.2);
        }
        .med-form-body { padding: 16px 20px; }
        .med-fields-grid {
            display: grid; grid-template-columns: 0.7fr 1fr 1fr 0.7fr 1fr;
            gap: 10px; margin-bottom: 14px;
        }
        .med-field { display: flex; flex-direction: column; gap: 4px; }
        .med-field label {
            font-size: 0.6rem; font-weight: 800;
            color: var(--text-secondary); text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .med-field input, .med-field select {
            padding: 8px 10px; border: 2px solid var(--border-color);
            border-radius: 8px; font-size: 0.8rem;
            background: var(--bg-card); color: var(--text-primary);
            outline: none; transition: var(--transition); height: 38px;
        }
        .med-field input:focus, .med-field select:focus {
            border-color: var(--success); box-shadow: 0 0 0 3px rgba(5,150,105,0.12);
        }
        .med-field .field-with-toggle { display: flex; gap: 4px; align-items: center; }
        .med-field .field-with-toggle select,
        .med-field .field-with-toggle input { flex: 1; }
        .med-field .btn-manual-toggle {
            padding: 0; width: 32px; height: 32px;
            margin-left: 0; border-radius: 8px;
            justify-content: center; flex-shrink: 0;
        }
        .med-field .manual-input-field { display: none; margin-top: 6px; }
        .med-field .manual-input-field.show { display: block; }
        .med-form-footer {
            display: flex; justify-content: flex-end;
            padding-top: 12px; border-top: 2px dashed var(--border-color);
        }
        
        .data-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
        .data-table thead th { text-align: left; padding: 12px 14px; font-weight: 800; font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.05em; color: white; white-space: nowrap; }
        .data-table thead th.th-primary { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); }
        .data-table tbody td { padding: 11px 14px; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        .data-table tbody tr:hover { background: var(--gray-50); }
        .data-table tbody tr:last-child td { border-bottom: none; }
        
        .alert { padding: 14px 20px; border-radius: var(--radius); margin-bottom: 20px; display: flex; align-items: center; gap: 12px; font-size: 0.85rem; border: 2px solid transparent; font-weight: 500; }
        .alert-success { background: var(--success-bg); color: var(--success); border-color: var(--success); }
        .alert-error { background: var(--danger-bg); color: var(--danger); border-color: var(--danger); }
        .alert-warning { background: var(--warning-bg); color: var(--warning); border-color: var(--warning); }
        .alert-info { background: var(--primary-bg); color: var(--primary); border-color: var(--primary); }
        
        .toast-custom {
            position: fixed; bottom: 30px; right: 30px;
            background: var(--success) !important;
            border-radius: 12px; padding: 16px 24px;
            box-shadow: 0 8px 32px rgba(5,150,105,0.35);
            display: flex; align-items: center; gap: 16px;
            z-index: 9999; min-width: 320px; max-width: 450px;
            transform: translateY(120px); opacity: 0;
            transition: var(--transition);
        }
        .toast-custom.show { transform: translateY(0); opacity: 1; }
        .toast-custom * { color: white !important; }
        .toast-custom .toast-icon { font-size: 1.5rem; flex-shrink: 0; }
        .toast-custom .toast-content { flex: 1; }
        .toast-custom .toast-close { background: none; border: none; font-size: 1.3rem; cursor: pointer; }
        
        .locked-card {
            background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 50%, #FDE68A 100%);
            border: 4px solid var(--warning); border-radius: var(--radius-xl);
            padding: 0; margin-bottom: 20px; overflow: hidden;
            box-shadow: 0 12px 40px rgba(217, 119, 6, 0.25); position: relative;
        }
        .locked-card-inner { padding: 40px 32px; text-align: center; position: relative; z-index: 1; }
        .locked-icon-circle {
            width: 96px; height: 96px; border-radius: 50%;
            background: linear-gradient(135deg, #F59E0B, #D97706);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 12px 32px rgba(217,119,6,0.4);
            animation: pulseLock 2.5s ease-in-out infinite;
        }
        @keyframes pulseLock {
            0%, 100% { box-shadow: 0 12px 32px rgba(217,119,6,0.4); transform: scale(1); }
            50% { box-shadow: 0 16px 48px rgba(217,119,6,0.6); transform: scale(1.03); }
        }
        .locked-icon-circle i { font-size: 2.6rem; color: white; }
        .locked-title { font-size: 1.5rem; font-weight: 900; color: #92400E; margin-bottom: 10px; letter-spacing: -0.02em; }
        .locked-description {
            font-size: 0.92rem; color: #78350F; font-weight: 600;
            max-width: 680px; margin: 0 auto 24px; line-height: 1.7;
        }
        .locked-description strong { color: #92400E; font-weight: 800; background: rgba(255,255,255,0.6); padding: 1px 6px; border-radius: 6px; }
        .locked-stats-box {
            display: inline-flex; align-items: center; gap: 20px;
            padding: 16px 28px; background: white; border-radius: 16px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
            border: 2px solid #FCD34D; margin-bottom: 24px;
        }
        .locked-stat-item { text-align: center; min-width: 60px; }
        .locked-stat-value { font-size: 1.8rem; font-weight: 900; font-family: 'JetBrains Mono', monospace; line-height: 1; }
        .locked-stat-label { font-size: 0.6rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; margin-top: 4px; }
        .locked-stat-divider { width: 2px; height: 44px; background: #FCD34D; }
        .locked-instruction-box {
            max-width: 680px; margin: 0 auto;
            padding: 20px 24px; background: rgba(255,255,255,0.85);
            border-radius: 14px; border: 2px solid #FCD34D;
            text-align: left; backdrop-filter: blur(10px);
        }
        .locked-instruction-title { font-size: 0.82rem; font-weight: 800; color: #78350F; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
        .locked-instruction-list { list-style: none; padding: 0; margin: 0; font-size: 0.78rem; color: #78350F; line-height: 1.9; font-weight: 500; }
        .locked-instruction-list li { display: flex; align-items: flex-start; gap: 10px; padding: 4px 0; }
        .locked-instruction-list li .list-icon {
            flex-shrink: 0; width: 20px; height: 20px;
            border-radius: 50%; display: flex;
            align-items: center; justify-content: center;
            font-size: 0.65rem; margin-top: 2px;
        }
        .locked-instruction-list li .list-icon.check { background: linear-gradient(135deg, #10B981, #059669); color: white; }
        .locked-instruction-list li .list-icon.clock { background: linear-gradient(135deg, #F59E0B, #D97706); color: white; }
        .locked-refresh-btn {
            display: inline-flex; align-items: center; gap: 10px;
            padding: 14px 32px;
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            color: white; border: none; border-radius: 12px;
            font-size: 0.9rem; font-weight: 800; cursor: pointer;
            transition: var(--transition);
            box-shadow: 0 6px 20px rgba(11,94,215,0.35);
            margin-top: 24px; font-family: inherit; letter-spacing: 0.02em;
        }
        .locked-refresh-btn:hover { transform: translateY(-3px); box-shadow: 0 10px 28px rgba(11,94,215,0.45); background: linear-gradient(135deg, #0A4CA8, #083C8A); }
        .locked-refresh-btn i { font-size: 1rem; transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1); }
        .locked-refresh-btn:hover i { transform: rotate(180deg); }
        
        #medicationsList, #addedItemsList {
            display: flex; flex-direction: column; gap: 10px;
        }
        
        .medication-item {
            display: flex; align-items: flex-start; justify-content: space-between;
            gap: 14px; padding: 14px 18px;
            background: linear-gradient(135deg, #FFFFFF 0%, #F0FDF4 100%);
            border: 2px solid #10B981; border-left: 6px solid #059669;
            border-radius: 14px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative; overflow: hidden;
            box-shadow: 0 2px 8px rgba(5, 150, 105, 0.08);
        }
        [data-theme="dark"] .medication-item {
            background: linear-gradient(135deg, #1E293B 0%, #1A3A2A 100%);
            border-color: #34D399; border-left-color: #10B981;
        }
        .medication-item::before {
            content: ''; position: absolute; top: 0; left: 0;
            width: 100%; height: 100%;
            background: radial-gradient(circle at top right, rgba(16, 185, 129, 0.08) 0%, transparent 60%);
            pointer-events: none;
        }
        .medication-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(5, 150, 105, 0.2);
            border-color: #059669;
        }
        .medication-item .medication-item-info {
            flex: 1; min-width: 0; display: flex;
            flex-wrap: wrap; align-items: center; gap: 8px 12px;
            position: relative; z-index: 1;
        }
        .medication-item .med-name {
            font-weight: 800; font-size: 0.95rem; color: #047857;
            display: inline-flex; align-items: center; gap: 8px;
            margin-right: 4px;
        }
        [data-theme="dark"] .medication-item .med-name { color: #34D399; }
        .medication-item .med-name::before {
            content: '\f484'; font-family: 'Font Awesome 6 Free'; font-weight: 900;
            font-size: 0.9rem; background: linear-gradient(135deg, #10B981, #059669);
            color: white; width: 28px; height: 28px; border-radius: 8px;
            display: inline-flex; align-items: center; justify-content: center;
            box-shadow: 0 3px 8px rgba(5, 150, 105, 0.3); flex-shrink: 0;
        }
        .medication-item .med-details {
            font-size: 0.75rem; color: var(--text-secondary);
            background: rgba(255, 255, 255, 0.7); padding: 4px 10px;
            border-radius: 8px; font-weight: 600;
            border: 1px solid rgba(5, 150, 105, 0.15);
            display: inline-flex; align-items: center; gap: 4px;
        }
        [data-theme="dark"] .medication-item .med-details {
            background: rgba(15, 23, 42, 0.5);
            border-color: rgba(52, 211, 153, 0.2);
        }
        .medication-item .med-qty {
            font-size: 0.72rem; color: white;
            background: linear-gradient(135deg, #059669, #047857);
            padding: 4px 12px; border-radius: 12px;
            font-weight: 800; font-family: 'JetBrains Mono', monospace;
            box-shadow: 0 3px 8px rgba(5, 150, 105, 0.25);
            display: inline-flex; align-items: center; gap: 3px;
        }
        .medication-item .med-price {
            font-size: 0.88rem; font-weight: 800; color: #059669;
            font-family: 'JetBrains Mono', monospace;
            background: linear-gradient(135deg, #D1FAE5, #A7F3D0);
            padding: 4px 14px; border-radius: 12px;
            border: 1px solid #10B981; margin-left: auto;
            box-shadow: 0 2px 6px rgba(5, 150, 105, 0.15);
        }
        [data-theme="dark"] .medication-item .med-price {
            background: linear-gradient(135deg, #064E3B, #065F46);
            color: #6EE7B7; border-color: #34D399;
        }
        .medication-item .med-instruction-tag {
            font-size: 0.7rem; color: #0B5ED7;
            background: linear-gradient(135deg, #E8F0FE, #DBEAFE);
            padding: 3px 12px; border-radius: 12px;
            border: 1px solid #6EA8FE; font-weight: 700;
            display: inline-flex; align-items: center; gap: 4px;
        }
        .medication-item .med-instruction-tag::before {
            content: '\f0f6'; font-family: 'Font Awesome 6 Free';
            font-weight: 900; font-size: 0.65rem;
        }
        .medication-item .med-status-pending, .medication-item .badge-pending {
            font-size: 0.62rem; padding: 4px 12px; border-radius: 12px;
            font-weight: 800; display: inline-flex; align-items: center; gap: 4px;
            background: linear-gradient(135deg, #FEF3C7, #FDE68A);
            color: #B45309; border: 1px solid #F59E0B;
            text-transform: uppercase; letter-spacing: 0.03em;
            animation: pulsePending 2s ease-in-out infinite;
        }
        @keyframes pulsePending {
            0%, 100% { box-shadow: 0 0 0 0 rgba(217, 119, 6, 0.4); }
            50% { box-shadow: 0 0 0 6px rgba(217, 119, 6, 0); }
        }
        
        /* ✅ V32: Batch info list */
        .medication-item .batch-info-list {
            margin-top: 8px; padding: 8px 10px;
            background: linear-gradient(135deg, #EFF6FF, #DBEAFE);
            border-radius: 8px; border: 1px solid #93C5FD;
            width: 100%; display: flex; flex-direction: column; gap: 4px;
        }
        [data-theme="dark"] .medication-item .batch-info-list {
            background: linear-gradient(135deg, #1E3A5F, #1E40AF);
            border-color: #60A5FA;
        }
        .medication-item .batch-info-header {
            font-size: 0.62rem; font-weight: 800;
            color: #1E40AF; text-transform: uppercase;
            letter-spacing: 0.05em; display: flex;
            align-items: center; gap: 5px;
            padding-bottom: 4px; border-bottom: 1px dashed #93C5FD;
        }
        [data-theme="dark"] .medication-item .batch-info-header {
            color: #93C5FD; border-color: #3B82F6;
        }
        .medication-item .batch-info-row {
            display: flex; align-items: center; justify-content: space-between;
            gap: 8px; font-size: 0.68rem; padding: 3px 6px;
            background: rgba(255, 255, 255, 0.7);
            border-radius: 6px; flex-wrap: wrap;
        }
        [data-theme="dark"] .medication-item .batch-info-row {
            background: rgba(15, 23, 42, 0.5);
        }
        .medication-item .batch-info-row .batch-num {
            font-weight: 700; color: #0369A1;
            display: flex; align-items: center; gap: 4px;
        }
        .medication-item .batch-info-row .batch-qty {
            font-weight: 800; font-family: 'JetBrains Mono', monospace;
            color: #1E40AF; background: #DBEAFE;
            padding: 2px 8px; border-radius: 8px; font-size: 0.65rem;
        }
        
        .medication-item.procedure-item {
            background: linear-gradient(135deg, #FFFFFF 0%, #F5F3FF 100%);
            border-color: #A78BFA; border-left-color: #7C3AED;
            box-shadow: 0 2px 8px rgba(124, 58, 237, 0.08);
        }
        [data-theme="dark"] .medication-item.procedure-item {
            background: linear-gradient(135deg, #1E293B 0%, #2D1B4E 100%);
            border-color: #8B5CF6; border-left-color: #7C3AED;
        }
        .medication-item.procedure-item .med-name { color: #6D28D9; }
        [data-theme="dark"] .medication-item.procedure-item .med-name { color: #C4B5FD; }
        .medication-item.procedure-item .med-name::before {
            content: '\f0f1'; font-family: 'Font Awesome 6 Free'; font-weight: 900;
            background: linear-gradient(135deg, #8B5CF6, #7C3AED);
            box-shadow: 0 3px 8px rgba(124, 58, 237, 0.3);
        }
        .medication-item.procedure-item .med-price {
            color: #7C3AED; background: linear-gradient(135deg, #EDE9FE, #DDD6FE);
            border-color: #A78BFA;
        }
        [data-theme="dark"] .medication-item.procedure-item .med-price {
            background: linear-gradient(135deg, #4C1D95, #5B21B6);
            color: #C4B5FD; border-color: #8B5CF6;
        }
        
        .medication-item.equipment-item {
            background: linear-gradient(135deg, #FFFFFF 0%, #FFFBEB 100%);
            border-color: #FCD34D; border-left-color: #D97706;
            box-shadow: 0 2px 8px rgba(217, 119, 6, 0.08);
        }
        [data-theme="dark"] .medication-item.equipment-item {
            background: linear-gradient(135deg, #1E293B 0%, #3D2E0A 100%);
            border-color: #F59E0B; border-left-color: #D97706;
        }
        .medication-item.equipment-item .med-name { color: #B45309; }
        [data-theme="dark"] .medication-item.equipment-item .med-name { color: #FCD34D; }
        .medication-item.equipment-item .med-name::before {
            content: '\f7d9'; font-family: 'Font Awesome 6 Free'; font-weight: 900;
            background: linear-gradient(135deg, #F59E0B, #D97706);
            box-shadow: 0 3px 8px rgba(217, 119, 6, 0.3);
        }
        .medication-item.equipment-item .med-qty {
            background: linear-gradient(135deg, #D97706, #B45309);
            box-shadow: 0 3px 8px rgba(217, 119, 6, 0.25);
        }
        .medication-item.equipment-item .med-price {
            color: #D97706; background: linear-gradient(135deg, #FEF3C7, #FDE68A);
            border-color: #F59E0B;
        }
        [data-theme="dark"] .medication-item.equipment-item .med-price {
            background: linear-gradient(135deg, #3D2E0A, #4A3A12);
            color: #FCD34D; border-color: #F59E0B;
        }
        
        .medication-item .btn-remove {
            width: 34px; height: 34px; border-radius: 10px;
            border: 2px solid #FCA5A5;
            background: linear-gradient(135deg, #FEE2E2, #FECACA);
            color: #DC2626; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 0.8rem; position: relative; z-index: 1;
        }
        .medication-item .btn-remove:hover {
            background: linear-gradient(135deg, #DC2626, #B91C1C);
            color: white; border-color: #DC2626;
            transform: scale(1.12) rotate(90deg);
            box-shadow: 0 4px 14px rgba(220, 38, 38, 0.4);
        }
        
        #medicationsList .empty-medications,
        #addedItemsList .empty-added {
            text-align: center; padding: 40px 20px;
            color: var(--text-secondary);
            background: linear-gradient(135deg, var(--gray-50), var(--bg-card));
            border-radius: 14px; border: 2px dashed var(--border-color);
        }
        #medicationsList .empty-medications i,
        #addedItemsList .empty-added i {
            font-size: 2.5rem; display: block; margin-bottom: 12px; opacity: 0.3;
        }
        
        @media (max-width: 1200px) {
            .bill-summary-grid { grid-template-columns: repeat(3, 1fr); }
            .items-grid { grid-template-columns: repeat(2, 1fr); }
            .vitals-grid { grid-template-columns: repeat(3, 1fr); }
            .visit-info-wrapper { grid-template-columns: repeat(2, 1fr); }
            .med-fields-grid { grid-template-columns: 1fr 1fr 1fr; }
        }
        @media (max-width: 992px) {
            .main-content { margin-left: 0; padding: 16px; }
            .patient-info-grid { grid-template-columns: repeat(2, 1fr); }
            .bill-summary-grid { grid-template-columns: repeat(2, 1fr); }
            .row-2col { grid-template-columns: 1fr; }
            .complaint-grid { grid-template-columns: 1fr; }
            .med-fields-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 768px) {
            .page-header { flex-direction: column; }
            .bill-summary-grid { grid-template-columns: repeat(2, 1fr); }
            .vitals-grid { grid-template-columns: repeat(2, 1fr); }
            .visit-info-wrapper { grid-template-columns: 1fr; }
            .patient-info-grid { grid-template-columns: 1fr; }
            .patient-info-wrapper { flex-direction: column; text-align: center; }
            .items-grid { grid-template-columns: 1fr; }
            .locked-stats-box { flex-wrap: wrap; gap: 12px; padding: 14px 18px; }
            .locked-stat-divider { display: none; }
            .med-fields-grid { grid-template-columns: 1fr; }
            .medication-item { flex-direction: column; align-items: stretch; gap: 10px; }
            .medication-item .med-price { margin-left: 0; align-self: flex-start; }
            .medication-item .btn-remove { align-self: flex-end; margin-top: -40px; }
        }
        @media (max-width: 480px) {
            .bill-summary-grid { grid-template-columns: 1fr; }
            .vitals-grid { grid-template-columns: 1fr; }
        }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: var(--gray-100); }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 3px; }
    </style>
</head>
<body>

<div id="savedIndicator" class="saved-indicator">
    <i class="fas fa-check-circle"></i>
    <span>Saved!</span>
</div>

<main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <?php if ($is_completed): ?>
                    <i class="fas fa-check-circle"></i> Consultation Completed
                <?php else: ?>
                    <i class="fas fa-stethoscope"></i> Consultation
                <?php endif; ?>
                <span class="page-badge"><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></span>
                <span class="status-badge">
                    <i class="fas fa-circle" style="font-size:0.5rem;"></i>
                    <?= ucfirst(str_replace('_', ' ', $visit['status'] ?? 'Assigned')) ?>
                </span>
            </h1>
            <p class="page-subtitle">
                <span><i class="fas fa-user"></i> <strong><?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?></strong></span>
                <span><i class="fas fa-id-card"></i> <?= htmlspecialchars($visit['patient_code'] ?? 'N/A') ?></span>
                <span><i class="fas fa-store-alt"></i> <?= htmlspecialchars($doctor_branch_name) ?></span>
            </p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="my_patients.php" class="btn" style="background:rgba(255,255,255,0.15);color:white;border:1px solid rgba(255,255,255,0.25);">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <a href="consultation.php?visit_id=<?= $visit_id ?>" class="btn" style="background:rgba(255,255,255,0.15);color:white;border:1px solid rgba(255,255,255,0.25);">
                <i class="fas fa-sync-alt"></i> Refresh
            </a>
        </div>
    </div>

    <!-- FLASH -->
    <?php if ($flash_message): ?>
        <div class="alert alert-<?= $flash_type ?>">
            <i class="fas <?= $flash_type === 'success' ? 'fa-check-circle' : ($flash_type === 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle') ?>" style="font-size:1.3rem;"></i>
            <div><?= $flash_message ?></div>
        </div>
    <?php endif; ?>

    <!-- BILL SUMMARY CARDS -->
    <div class="bill-summary-grid">
        <div class="bill-card total">
            <div class="bill-icon"><i class="fas fa-file-invoice-dollar"></i></div>
            <span class="bill-label">Total Amount</span>
            <span class="bill-value big-number" id="totalAmountDisplay"><?= formatMoneyDisplay($bill_total) ?></span>
            <span class="bill-sub">TSh</span>
        </div>
        <div class="bill-card paid">
            <div class="bill-icon"><i class="fas fa-check-circle"></i></div>
            <span class="bill-label">Paid Amount</span>
            <span class="bill-value big-number" id="paidAmountDisplay"><?= formatMoneyDisplay($bill_paid) ?></span>
            <span class="bill-sub">TSh</span>
        </div>
        <div class="bill-card remaining <?= $bill_balance <= 0 ? 'zero' : '' ?>">
            <div class="bill-icon"><i class="fas <?= $bill_balance > 0 ? 'fa-hourglass-half' : 'fa-check-double' ?>"></i></div>
            <span class="bill-label">Remaining</span>
            <span class="bill-value big-number" id="remainingAmountDisplay"><?= formatMoneyDisplay($bill_balance) ?></span>
            <span class="bill-sub"><?= $bill_balance <= 0 ? '✅ Fully Paid' : 'TSh' ?></span>
        </div>
        <div class="bill-card discount">
            <div class="bill-icon"><i class="fas fa-tags"></i></div>
            <span class="bill-label">Total Discount</span>
            <span class="bill-value big-number" id="discountAmountDisplay"><?= formatMoneyDisplay($total_discount) ?></span>
            <span class="bill-sub">TSh</span>
        </div>
        <div class="bill-card premium" style="<?= $total_premium > 0 ? '' : 'opacity:0.55;' ?>">
            <div class="bill-icon"><i class="fas fa-crown"></i></div>
            <span class="bill-label">Premium</span>
            <span class="bill-value big-number" id="premiumAmountDisplay"><?= formatMoneyDisplay($total_premium) ?></span>
            <span class="bill-sub">TSh</span>
        </div>
    </div>

    <!-- STATUS FLOW -->
    <?php if (!$is_completed): ?>
    <div class="section-card section-primary">
        <div class="section-body">
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
        </div>
    </div>
    <?php endif; ?>

    <!-- PATIENT INFORMATION -->
    <div class="section-card section-primary">
        <div class="section-header">
            <div class="section-title">
                <div class="title-icon" style="background: var(--primary-gradient);"><i class="fas fa-user"></i></div>
                Patient Information
            </div>
        </div>
        <div class="section-body">
            <div class="patient-info-wrapper">
                <div class="patient-avatar-large" style="background: <?= getUserColor($visit['patient_name'] ?? 'Unknown') ?>;">
                    <?= strtoupper(substr($visit['patient_name'] ?? 'U', 0, 1)) ?>
                </div>
                <div class="patient-info-main">
                    <h2>
                        <?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?>
                        <span class="patient-id-badge"><?= htmlspecialchars($visit['patient_code'] ?? 'N/A') ?></span>
                    </h2>
                    <div class="patient-demographics">
                        <span><i class="fas fa-<?= ($visit['gender'] ?? '') === 'Female' ? 'venus' : 'mars' ?>"></i> <?= htmlspecialchars($visit['gender'] ?? 'N/A') ?></span>
                        <span><i class="fas fa-birthday-cake"></i> <?= calculateAge($visit['date_of_birth'] ?? '') ?> years</span>
                        <?php if (!empty($visit['phone'])): ?>
                            <span><i class="fas fa-phone"></i> <?= htmlspecialchars($visit['phone']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="patient-info-grid">
                    <div class="info-cell">
                        <div class="info-label"><i class="fas fa-calendar-alt"></i> Date of Birth</div>
                        <div class="info-value"><?= !empty($visit['date_of_birth']) && $visit['date_of_birth'] !== '0000-00-00' ? date('d M Y', strtotime($visit['date_of_birth'])) : 'N/A' ?></div>
                    </div>
                    <div class="info-cell">
                        <div class="info-label"><i class="fas fa-tint"></i> Blood Group</div>
                        <div class="info-value">
                            <?php if (!empty($visit['blood_group'])): ?>
                                <span class="blood-group-badge"><?= htmlspecialchars($visit['blood_group']) ?></span>
                            <?php else: ?>
                                <span style="color:var(--text-secondary);">Not recorded</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="info-cell">
                        <div class="info-label"><i class="fas fa-exclamation-triangle"></i> Allergies</div>
                        <div class="info-value">
                            <?php if (!empty($visit['allergies']) && $visit['allergies'] !== 'None'): ?>
                                <span class="allergy-badge"><?= htmlspecialchars($visit['allergies']) ?></span>
                            <?php else: ?>
                                <span style="color:var(--success);font-weight:700;">None</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="info-cell">
                        <div class="info-label"><i class="fas fa-map-marker-alt"></i> Address</div>
                        <div class="info-value"><?= htmlspecialchars($visit['address'] ?? 'N/A') ?></div>
                    </div>
                    <div class="info-cell">
                        <div class="info-label"><i class="fas fa-phone-alt"></i> Emergency Contact</div>
                        <div class="info-value"><?= htmlspecialchars($visit['emergency_contact'] ?? 'N/A') ?></div>
                    </div>
                    <div class="info-cell">
                        <div class="info-label"><i class="fas fa-calendar-check"></i> Registered</div>
                        <div class="info-value"><?= !empty($visit['patient_registered']) ? date('d M Y', strtotime($visit['patient_registered'])) : 'N/A' ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- VISIT INFORMATION -->
    <div class="section-card section-success">
        <div class="section-header">
            <div class="section-title">
                <div class="title-icon" style="background: var(--success-gradient);"><i class="fas fa-clinic-medical"></i></div>
                Visit Information
            </div>
        </div>
        <div class="section-body">
            <div class="visit-info-wrapper">
                <div class="visit-info-cell visit-number">
                    <div class="cell-icon"><i class="fas fa-hashtag"></i></div>
                    <div class="cell-content">
                        <div class="cell-label">Visit Number</div>
                        <div class="cell-value"><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></div>
                    </div>
                </div>
                <div class="visit-info-cell visit-date">
                    <div class="cell-icon"><i class="fas fa-calendar-day"></i></div>
                    <div class="cell-content">
                        <div class="cell-label">Visit Date</div>
                        <div class="cell-value"><?= date('d M Y, H:i', strtotime($visit['created_at'] ?? 'now')) ?></div>
                    </div>
                </div>
                <div class="visit-info-cell doctor">
                    <div class="cell-icon"><i class="fas fa-user-md"></i></div>
                    <div class="cell-content">
                        <div class="cell-label">Doctor</div>
                        <div class="cell-value">Dr. <?= htmlspecialchars($visit['doctor_name'] ?? 'Not Assigned') ?></div>
                    </div>
                </div>
                <div class="visit-info-cell">
                    <div class="cell-icon"><i class="fas fa-stethoscope"></i></div>
                    <div class="cell-content">
                        <div class="cell-label">Specialty</div>
                        <div class="cell-value"><?= htmlspecialchars($visit['doctor_specialty'] ?? $doctor_specialty) ?></div>
                    </div>
                </div>
                <div class="visit-info-cell branch">
                    <div class="cell-icon"><i class="fas fa-store-alt"></i></div>
                    <div class="cell-content">
                        <div class="cell-label">Branch</div>
                        <div class="cell-value"><?= htmlspecialchars($visit['branch_name'] ?? $doctor_branch_name) ?></div>
                    </div>
                </div>
                <div class="visit-info-cell">
                    <div class="cell-icon"><i class="fas fa-info-circle"></i></div>
                    <div class="cell-content">
                        <div class="cell-label">Visit Type</div>
                        <div class="cell-value"><?= ucfirst($visit['visit_type'] ?? 'New') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- VITAL SIGNS -->
    <div class="section-card section-danger">
        <div class="section-header">
            <div class="section-title">
                <div class="title-icon" style="background: var(--danger-gradient);"><i class="fas fa-heartbeat"></i></div>
                Vital Signs
            </div>
        </div>
        <div class="section-body">
            <?php if ($vital_signs): 
                $spo2_status = getSpO2Status($vital_signs['oxygen_saturation'] ?? null);
                $temp_status = getTempStatus($vital_signs['temperature'] ?? null);
                $bp_status = getBPStatus($vital_signs['blood_pressure_systolic'] ?? null, $vital_signs['blood_pressure_diastolic'] ?? null);
                $pulse_status = getPulseStatus($vital_signs['pulse_rate'] ?? null);
                $bmi_status = getBMIStatus($vital_signs['bmi'] ?? null);
            ?>
                <div class="vitals-grid">
                    <div class="vital-card temp">
                        <div class="vital-icon-wrap"><i class="fas fa-thermometer-half"></i></div>
                        <div class="vital-name">Temperature</div>
                        <div class="vital-reading"><?= $vital_signs['temperature'] ?? '--' ?><span class="unit">°C</span></div>
                        <div class="vital-range">Normal: 36.1 - 37.5°C</div>
                        <?php if (!empty($vital_signs['temperature'])): ?>
                            <span class="vital-status <?= $temp_status['class'] === 'warning' ? 'warning' : ($temp_status['class'] === 'critical' ? 'critical' : ($temp_status['class'] === 'low' ? 'low' : 'normal')) ?>">
                                <?= $temp_status['label'] ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="vital-card bp">
                        <div class="vital-icon-wrap"><i class="fas fa-heart"></i></div>
                        <div class="vital-name">Blood Pressure</div>
                        <div class="vital-reading" style="font-size:1.15rem;">
                            <?= $vital_signs['blood_pressure_systolic'] ?? '--' ?>/<?= $vital_signs['blood_pressure_diastolic'] ?? '--' ?><span class="unit">mmHg</span>
                        </div>
                        <div class="vital-range">Normal: 120/80 mmHg</div>
                        <?php if (!empty($vital_signs['blood_pressure_systolic'])): ?>
                            <span class="vital-status <?= $bp_status['class'] === 'warning' ? 'warning' : ($bp_status['class'] === 'critical' ? 'critical' : ($bp_status['class'] === 'low' ? 'low' : 'normal')) ?>">
                                <?= $bp_status['label'] ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="vital-card pulse">
                        <div class="vital-icon-wrap"><i class="fas fa-heartbeat"></i></div>
                        <div class="vital-name">Pulse Rate</div>
                        <div class="vital-reading"><?= $vital_signs['pulse_rate'] ?? '--' ?><span class="unit">bpm</span></div>
                        <div class="vital-range">Normal: 60 - 100 bpm</div>
                        <?php if (!empty($vital_signs['pulse_rate'])): ?>
                            <span class="vital-status <?= $pulse_status['class'] === 'warning' ? 'warning' : ($pulse_status['class'] === 'critical' ? 'critical' : ($pulse_status['class'] === 'low' ? 'low' : 'normal')) ?>">
                                <?= $pulse_status['label'] ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="vital-card spo2">
                        <div class="vital-icon-wrap"><i class="fas fa-lungs"></i></div>
                        <div class="vital-name">Oxygen (SpO2)</div>
                        <div class="vital-reading"><?= $vital_signs['oxygen_saturation'] ?? '--' ?><span class="unit">%</span></div>
                        <div class="vital-range">Normal: 95 - 100%</div>
                        <?php if (!empty($vital_signs['oxygen_saturation'])): ?>
                            <span class="vital-status <?= $spo2_status['class'] ?>"><?= $spo2_status['label'] ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="vitals-grid" style="margin-top:14px;">
                    <div class="vital-card weight">
                        <div class="vital-icon-wrap"><i class="fas fa-weight"></i></div>
                        <div class="vital-name">Weight</div>
                        <div class="vital-reading"><?= $vital_signs['weight'] ?? '--' ?><span class="unit">kg</span></div>
                    </div>
                    <div class="vital-card height">
                        <div class="vital-icon-wrap"><i class="fas fa-ruler-vertical"></i></div>
                        <div class="vital-name">Height</div>
                        <div class="vital-reading"><?= $vital_signs['height'] ?? '--' ?><span class="unit">cm</span></div>
                    </div>
                    <div class="vital-card bmi">
                        <div class="vital-icon-wrap"><i class="fas fa-calculator"></i></div>
                        <div class="vital-name">BMI</div>
                        <div class="vital-reading"><?= $vital_signs['bmi'] ?? '--' ?><span class="unit">kg/m²</span></div>
                        <div class="vital-range">Normal: 18.5 - 24.9</div>
                        <?php if (!empty($vital_signs['bmi'])): ?>
                            <span class="vital-status <?= $bmi_status['class'] === 'normal' ? 'normal-bmi' : $bmi_status['class'] ?>">
                                <?= $bmi_status['label'] ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="vital-card notes-card">
                        <div class="vital-icon-wrap"><i class="fas fa-notes-medical"></i></div>
                        <div class="vital-name">Notes</div>
                        <div class="vital-reading">
                            <?= !empty($vital_signs['notes']) ? htmlspecialchars(substr($vital_signs['notes'], 0, 40)) : 'No notes' ?>
                        </div>
                    </div>
                </div>
                <div class="vitals-footer">
                    <i class="fas fa-lungs" style="color:var(--sky);font-size:1rem;"></i>
                    <span>SpO2 Normal: <strong>95-100%</strong></span>
                    <?php if (!empty($vital_signs['recorded_at'])): ?>
                        <span style="color:var(--border-color);">•</span>
                        <span><i class="fas fa-clock"></i> <?= date('d M Y H:i', strtotime($vital_signs['recorded_at'])) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($vital_signs['recorded_by_name'])): ?>
                        <span style="color:var(--border-color);">•</span>
                        <span><i class="fas fa-user-nurse"></i> <?= htmlspecialchars($vital_signs['recorded_by_name']) ?></span>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div style="text-align:center;padding:40px 20px;color:var(--text-secondary);">
                    <i class="fas fa-heartbeat" style="font-size:3rem;display:block;margin-bottom:12px;opacity:0.3;"></i>
                    <p>No vital signs recorded</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$is_completed): ?>
    <!-- EDITABLE VIEW -->
    <form method="POST" action="consultation.php?visit_id=<?= $visit_id ?>" id="consultationForm">
    
    <!-- CHIEF COMPLAINT & HISTORY -->
    <div class="section-card section-warning">
        <div class="section-header">
            <div class="section-title">
                <div class="title-icon" style="background: var(--warning-gradient);"><i class="fas fa-clipboard-list"></i></div>
                Chief Complaint & History
            </div>
            <span class="saved-banner">
                <i class="fas fa-cloud-upload-alt"></i> Auto-saved
            </span>
        </div>
        <div class="section-body">
            <div class="complaint-grid">
                <div class="complaint-box complaint-notes">
                    <div class="box-header">
                        <div class="box-icon"><i class="fas fa-sticky-note"></i></div>
                        Additional Notes
                    </div>
                    <div class="box-body">
                        <textarea name="notes" rows="6" placeholder="Additional clinical notes..." id="notesTextarea" oninput="debouncedAutoSave()"><?= htmlspecialchars($visit['notes'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="complaint-box complaint-chief">
                    <div class="box-header">
                        <div class="box-icon"><i class="fas fa-notes-medical"></i></div>
                        Chief Complaint <span style="color:var(--danger);">*</span>
                    </div>
                    <div class="box-body">
                        <div class="form-group" style="margin-bottom:10px;">
                            <select class="form-control" id="complaintSelect" onchange="addComplaintOnSelect()">
                                <option value="">-- Select Common Complaint --</option>
                                <?php foreach ($common_complaints as $complaint): ?>
                                    <option value="<?= htmlspecialchars($complaint) ?>"><?= htmlspecialchars($complaint) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="complaint-chips" id="complaintChips" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px;min-height:28px;">
                            <?php if (!empty($visit['symptoms'])): 
                                $symptom_list = array_filter(array_map('trim', explode(',', $visit['symptoms'])));
                                foreach ($symptom_list as $symptom): 
                            ?>
                                <span class="complaint-chip">
                                    <?= htmlspecialchars($symptom) ?>
                                    <span class="chip-remove" onclick="removeComplaintChip(this, '<?= htmlspecialchars(addslashes($symptom)) ?>')">×</span>
                                </span>
                            <?php endforeach; endif; ?>
                        </div>
                        <textarea name="symptoms" placeholder="Describe complaints..." id="symptomsTextarea" oninput="updateComplaints(); debouncedAutoSave();" style="flex:1;min-height:90px;"><?= htmlspecialchars($visit['symptoms'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="complaint-box complaint-hpi">
                    <div class="box-header">
                        <div class="box-icon"><i class="fas fa-history"></i></div>
                        History of Presenting Illness (HPI)
                    </div>
                    <div class="box-body">
                        <textarea name="hpi" rows="6" placeholder="Describe here..." id="hpiTextarea" oninput="debouncedAutoSave()"><?= htmlspecialchars($visit['hpi'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="complaint-box complaint-exam">
                    <div class="box-header">
                        <div class="box-icon"><i class="fas fa-stethoscope"></i></div>
                        Physical Examination
                    </div>
                    <div class="box-body">
                        <textarea name="physical_exam" rows="6" placeholder="Describe here all examination you see on your patient" id="physicalExamTextarea" oninput="debouncedAutoSave()"><?= htmlspecialchars($visit['physical_exam'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- LAB TESTS -->
    <div class="section-card section-sky">
        <div class="section-header">
            <div class="section-title">
                <div class="title-icon" style="background: var(--sky-gradient);"><i class="fas fa-flask"></i></div>
                Laboratory Tests
                <?php if ($has_active_lab): ?>
                    <span style="background:var(--warning-bg);color:var(--warning);padding:3px 12px;border-radius:12px;font-size:0.65rem;font-weight:700;border:1px solid var(--warning);">
                        ⏳ <?= count($lab_requests) ?> Active
                    </span>
                <?php endif; ?>
            </div>
            <span style="background:var(--sky-bg);color:var(--sky-dark);padding:5px 14px;border-radius:12px;font-size:0.7rem;font-weight:800;font-family:'JetBrains Mono',monospace;border:1px solid var(--sky);">
                🧪 Lab: TSh <?= formatMoneyDisplay($lab_total) ?>
            </span>
        </div>
        <div class="section-body">
            <div class="dropdown-section">
                <div class="dropdown-header" onclick="toggleDropdownSection('labDropdownBody')">
                    <div class="dh-left">
                        <div class="dh-icon" style="background: var(--sky-gradient);"><i class="fas fa-flask"></i></div>
                        <div class="dh-title">
                            <h4>Select Lab Tests</h4>
                            <p>Click to expand available tests</p>
                        </div>
                    </div>
                    <div class="dh-right">
                        <span class="dh-stat" style="background:var(--sky-bg);color:var(--sky-dark);">
                            <i class="fas fa-vials"></i> <?= count($lab_tests_catalog) ?>
                        </span>
                        <?php if ($lab_cart_count > 0): ?>
                            <span class="dh-stat" style="background:var(--warning-bg);color:var(--warning);">
                                <i class="fas fa-shopping-cart"></i> <?= $lab_cart_count ?>
                            </span>
                        <?php endif; ?>
                        <i class="fas fa-chevron-down dh-chevron"></i>
                    </div>
                </div>
                <div class="dropdown-body" id="labDropdownBody">
                    <div class="dropdown-search-row">
                        <div class="search-input-wrap">
                            <i class="fas fa-search"></i>
                            <input type="text" id="labTestSearch" placeholder="🔍 Search lab tests..." oninput="filterLabTestsInside()">
                        </div>
                    </div>
                    <div class="items-grid" id="labTestsGrid">
                        <?php if (count($lab_tests_catalog) > 0): ?>
                            <?php foreach ($lab_tests_catalog as $test): 
                                $has_equipment = !empty($test['equipment_links']);
                                $all_available = $test['all_equipment_available'] ?? true;
                                $is_disabled = $has_equipment && !$all_available;
                                $is_linked_class = $has_equipment ? 'linked-equipment' : '';
                            ?>
                                <div class="item-card labtest-card <?= $is_disabled ? 'disabled' : '' ?> <?= $is_linked_class ?>" 
                                     data-test-id="<?= $test['id'] ?>"
                                     data-test-name="<?= htmlspecialchars($test['test_name']) ?>"
                                     onclick="toggleLabTestCheckbox(this)">
                                    <div style="display:flex;align-items:flex-start;gap:10px;width:100%;">
                                        <span class="item-check"><i class="fas fa-check"></i></span>
                                        <div class="item-info">
                                            <span class="item-name"><?= htmlspecialchars($test['test_name']) ?></span>
                                            <div class="item-details">
                                                <?php if (!empty($test['category'])): ?>
                                                    <span class="item-tag category"><?= htmlspecialchars($test['category']) ?></span>
                                                <?php endif; ?>
                                                <span class="item-price">TSh <?= formatMoneyDisplay($test['price']) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <?php if ($has_equipment): ?>
                                        <div class="equipment-list">
                                            <div class="equipment-list-header">
                                                <i class="fas fa-tools"></i> 
                                                <span>REQUIRES <?= count($test['equipment_links']) ?> EQUIPMENT:</span>
                                                <?php if (!$all_available): ?>
                                                    <span style="margin-left:auto;color:#DC2626;font-size:0.6rem;">⚠️ UNAVAILABLE</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php foreach ($test['equipment_links'] as $eq): 
                                                $stock = (int)($eq['equipment_stock'] ?? 0);
                                                $used = (int)($eq['equipment_quantity_used'] ?? 1);
                                                $stock_class = $stock <= 0 ? 'out' : ($stock < $used ? 'low' : 'ok');
                                            ?>
                                                <div class="equipment-item-row">
                                                    <span class="eq-name">
                                                        <i class="fas fa-box"></i> 
                                                        <?= htmlspecialchars($eq['equipment_name'] ?? 'Unknown') ?>
                                                    </span>
                                                    <span class="eq-stock-badge <?= $stock_class ?>">
                                                        Stock: <?= $stock ?>
                                                    </span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state-grid">
                                <i class="fas fa-flask"></i>
                                <p>No lab tests available</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="dropdown-action-row">
                        <button type="button" class="btn btn-primary" onclick="addSelectedLabTests()">
                            <i class="fas fa-cart-plus"></i> Add Selected to Cart
                        </button>
                        <span class="selected-badge">
                            <i class="fas fa-check-circle"></i> Selected: <span id="labSelectedCount">0</span>
                        </span>
                    </div>
                </div>
            </div>
            
            <div style="background:linear-gradient(135deg, var(--gray-50), var(--bg-card));border-radius:var(--radius);padding:16px;border:2px solid var(--border-color);margin-top:12px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;flex-wrap:wrap;gap:8px;">
                    <h4 style="font-size:0.85rem;font-weight:800;color:var(--text-secondary);margin:0;display:flex;align-items:center;gap:8px;">
                        <i class="fas fa-shopping-cart" style="color:var(--warning);"></i> Lab Cart
                        <span style="font-size:0.7rem;font-family:'JetBrains Mono',monospace;" id="labCartCount">(<?= $lab_cart_count ?> items)</span>
                    </h4>
                    <span style="font-size:0.85rem;font-weight:800;color:var(--warning);font-family:'JetBrains Mono',monospace;">
                        Total: TSh <span id="labCartTotal"><?= formatMoneyDisplay($lab_cart_total) ?></span>
                    </span>
                </div>
                <div id="labCartItems" style="max-height:200px;overflow-y:auto;">
                    <?php if ($lab_cart_count > 0): ?>
                        <?php foreach ($lab_cart as $item): ?>
                            <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border-bottom:1px solid var(--border-color);border-radius:8px;" id="lab-cart-<?= $item['id'] ?>" data-test-id="<?= $item['id'] ?>">
                                <span style="font-weight:600;font-size:0.85rem;"><?= htmlspecialchars($item['name']) ?></span>
                                <span style="font-size:0.8rem;color:var(--success);font-weight:700;font-family:'JetBrains Mono',monospace;">TSh <?= formatMoneyDisplay($item['price']) ?></span>
                                <button type="button" class="btn-remove" style="width:26px;height:26px;font-size:0.7rem;" onclick="removeLabTestFromCart(<?= $item['id'] ?>)">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align:center;padding:20px;color:var(--text-secondary);font-size:0.85rem;">
                            <i class="fas fa-shopping-cart" style="font-size:1.5rem;display:block;margin-bottom:8px;opacity:0.3;"></i>
                            No tests in cart
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap;">
                <button type="submit" name="send_lab" class="btn btn-warning" <?= ($lab_cart_count == 0) ? 'disabled' : '' ?>>
                    <i class="fas fa-paper-plane"></i> Send All to Laboratory (<span id="sendLabCount"><?= $lab_cart_count ?></span>)
                </button>
                <button type="button" class="btn btn-outline" onclick="clearLabCart()">
                    <i class="fas fa-times"></i> Clear Cart
                </button>
            </div>
            
            <?php if (count($lab_requests) > 0): ?>
                <div style="margin-top:16px;">
                    <h5 style="font-size:0.85rem;font-weight:800;color:var(--text-secondary);margin-bottom:10px;display:flex;align-items:center;gap:8px;">
                        <i class="fas fa-clock" style="color:var(--warning);"></i> Sent Tests (In Progress)
                    </h5>
                    <div id="sentTestsList">
                        <?php foreach ($lab_requests as $lab): 
                            $is_paid = isBillItemPaid($db, $bill_id, $lab['id'], 'lab_test');
                        ?>
                            <div style="display:flex;gap:10px;margin-bottom:6px;align-items:center;padding:12px 16px;background:var(--warning-bg);border-radius:var(--radius);border:2px solid var(--warning);" id="sent-test-<?= $lab['id'] ?>">
                                <div style="flex:1;">
                                    <span style="font-weight:700;font-size:0.85rem;color:var(--warning);"><?= htmlspecialchars($lab['test_name']) ?></span>
                                    <span style="font-size:0.75rem;color:var(--text-secondary);margin-left:8px;font-family:'JetBrains Mono',monospace;">
                                        TSh <?= formatMoneyDisplay($lab['test_price'] ?? 0) ?>
                                    </span>
                                </div>
                                <span style="background:var(--warning);color:white;padding:3px 10px;border-radius:12px;font-size:0.6rem;font-weight:700;">
                                    ⏳ <?= ucfirst($lab['status'] ?? 'Pending') ?>
                                </span>
                                <?php if ($lab['status'] !== 'completed' && !$is_paid): ?>
                                    <button type="button" class="btn-remove" style="width:28px;height:28px;font-size:0.7rem;" onclick="removeLabTest(<?= $lab['id'] ?>)">
                                        <i class="fas fa-times"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- LAB RESULTS -->
    <?php if ($lab_results_available): ?>
    <div class="section-card section-success">
        <div class="section-header">
            <div class="section-title">
                <div class="title-icon" style="background: linear-gradient(135deg, #10B981, #059669);"><i class="fas fa-file-medical-alt"></i></div>
                Laboratory Results
                <span style="background:var(--success-bg);color:var(--success);padding:3px 12px;border-radius:12px;font-size:0.65rem;font-weight:700;border:1px solid var(--success);">
                    ✅ <?= count($lab_results) ?> Completed
                </span>
            </div>
        </div>
        <div class="section-body">
            <div style="display:flex;flex-direction:column;gap:16px;">
                <?php foreach ($lab_results as $result): 
                    $result_text = $result['results'] ?? '';
                    $is_long = strlen($result_text) > 150;
                    $ref_range = $result['reference_range'] ?? '';
                    $tech_name = $result['technician_name'] ?? 'N/A';
                ?>
                    <div style="background: linear-gradient(135deg, var(--bg-card), var(--sky-bg)); border: 3px solid var(--sky); border-radius: var(--radius-lg); overflow: hidden; position: relative;" id="lab-result-card-<?= $result['id'] ?>">
                        <div style="position:absolute;top:0;left:0;width:5px;height:100%;background:var(--sky-gradient);"></div>
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 20px 14px 24px;background:linear-gradient(135deg, var(--sky-dark), var(--sky));color:white;flex-wrap:wrap;gap:10px;">
                            <div style="display:flex;align-items:center;gap:12px;flex:1;min-width:200px;">
                                <div style="width:38px;height:38px;border-radius:10px;background:rgba(255,255,255,0.2);display:flex;align-items:center;justify-content:center;font-size:1rem;border:1px solid rgba(255,255,255,0.3);"><i class="fas fa-flask"></i></div>
                                <div>
                                    <div style="font-size:1.05rem;font-weight:800;color:white;"><?= htmlspecialchars($result['test_name'] ?? 'N/A') ?></div>
                                </div>
                            </div>
                            <div style="display:flex;gap:12px;flex-wrap:wrap;">
                                <?php if (!empty($result['completed_at'])): ?><span style="display:inline-flex;align-items:center;gap:6px;font-size:0.72rem;font-weight:600;background:rgba(255,255,255,0.15);padding:4px 12px;border-radius:20px;color:white;border:1px solid rgba(255,255,255,0.2);"><i class="fas fa-calendar-check"></i> <?= date('d M Y, H:i', strtotime($result['completed_at'])) ?></span><?php endif; ?>
                            </div>
                        </div>
                        <div style="padding:18px 20px 18px 24px;">
                            <div style="margin-bottom:14px;">
                                <div style="font-size:0.65rem;font-weight:800;text-transform:uppercase;letter-spacing:0.08em;color:var(--sky-dark);margin-bottom:6px;display:flex;align-items:center;gap:6px;"><i class="fas fa-clipboard-check"></i> TEST RESULT</div>
                                <?php if (!empty($result_text)): ?>
                                    <div style="background:var(--bg-card);border:2px solid var(--sky);border-radius:var(--radius);padding:14px 18px;font-size:0.9rem;font-weight:600;color:var(--text-primary);line-height:1.7;font-family:'JetBrains Mono',monospace;white-space:pre-wrap;<?= $is_long ? 'max-height:220px;overflow-y:auto;' : '' ?>"><?= nl2br(htmlspecialchars($result_text)) ?></div>
                                <?php else: ?>
                                    <div style="background:var(--bg-card);border:2px solid var(--sky);border-radius:var(--radius);padding:14px 18px;font-size:0.9rem;color:var(--text-secondary);font-style:italic;">No result recorded yet</div>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($ref_range)): ?>
                            <div style="margin-bottom:14px;">
                                <div style="font-size:0.65rem;font-weight:800;text-transform:uppercase;letter-spacing:0.08em;color:var(--sky-dark);margin-bottom:6px;display:flex;align-items:center;gap:6px;"><i class="fas fa-ruler"></i> REFERENCE RANGE</div>
                                <span style="background:var(--purple-bg);border:1px solid var(--purple);border-radius:var(--radius);padding:10px 16px;font-size:0.85rem;font-weight:600;color:var(--purple);display:inline-flex;align-items:center;gap:8px;font-family:'JetBrains Mono',monospace;"><i class="fas fa-chart-line"></i> <?= htmlspecialchars($ref_range) ?></span>
                            </div>
                            <?php endif; ?>
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-top:14px;padding-top:14px;border-top:2px dashed var(--border-color);flex-wrap:wrap;gap:10px;">
                                <div style="display:inline-flex;align-items:center;gap:8px;font-size:0.8rem;font-weight:600;color:var(--text-primary);background:var(--gray-50);padding:6px 14px;border-radius:20px;border:1px solid var(--border-color);">
                                    <i class="fas fa-user-md" style="color:var(--sky-dark);"></i>
                                    <span>Technician: <strong><?= htmlspecialchars($tech_name) ?></strong></span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- LOCK SECTIONS CARD -->
    <?php if ($lock_sections): ?>
    <div class="locked-card">
        <div class="locked-card-inner">
            <div class="locked-icon-circle">
                <i class="fas fa-lock"></i>
            </div>
            <h2 class="locked-title">🔒 Sections Locked</h2>
            <p class="locked-description">
                The <strong>Diagnosis</strong>, <strong>Medications</strong>, <strong>Procedures</strong>, and <strong>Equipment</strong> sections will unlock <strong>AFTER</strong> at least one lab test is <strong>COMPLETED</strong> and its <strong>RESULT is filled in</strong> by the Laboratory.
            </p>
            <div class="locked-stats-box">
                <div class="locked-stat-item">
                    <div class="locked-stat-value" style="color:#D97706;"><?= $pending_lab_count ?></div>
                    <div class="locked-stat-label" style="color:#92400E;">Pending</div>
                </div>
                <div class="locked-stat-divider"></div>
                <div class="locked-stat-item">
                    <div class="locked-stat-value" style="color:#059669;"><?= $total_lab_count - $pending_lab_count ?></div>
                    <div class="locked-stat-label" style="color:#047857;">Completed</div>
                </div>
                <div class="locked-stat-divider"></div>
                <div class="locked-stat-item">
                    <div class="locked-stat-value" style="color:#0B5ED7;"><?= $total_lab_count ?></div>
                    <div class="locked-stat-label" style="color:#0A4CA8;">Total</div>
                </div>
            </div>
            <div class="locked-instruction-box">
                <div class="locked-instruction-title">
                    <i class="fas fa-info-circle" style="color:#D97706;"></i>
                    What you can do now:
                </div>
                <ul class="locked-instruction-list">
                    <li><span class="list-icon check"><i class="fas fa-check"></i></span><span>You can <strong>continue adding lab tests</strong> to the cart</span></li>
                    <li><span class="list-icon check"><i class="fas fa-check"></i></span><span>You can <strong>fill in Chief Complaint, HPI, Physical Exam, and Notes</strong></span></li>
                    <li><span class="list-icon clock"><i class="fas fa-clock"></i></span><span>Wait for at least <strong>1 lab test to be COMPLETED</strong> with a result filled in</span></li>
                </ul>
            </div>
            <button type="button" class="locked-refresh-btn" onclick="checkLabStatusNow(this)">
                <i class="fas fa-sync-alt"></i> Check Lab Status Now
            </button>
        </div>
    </div>
    
    <?php else: ?>
    
    <!-- DIAGNOSIS -->
    <div class="section-card section-purple">
        <div class="section-header">
            <div class="section-title">
                <div class="title-icon" style="background: var(--purple-gradient);"><i class="fas fa-diagnoses"></i></div>
                Diagnosis
            </div>
            <span id="diagnosisStatus" style="font-size:0.7rem;color:var(--success);font-weight:600;">
                <?php if (!empty($visit['diagnosis'])): ?>
                    <span class="saved-banner">
                        <i class="fas fa-check-circle"></i> Saved: <?= htmlspecialchars($visit['diagnosis']) ?>
                    </span>
                <?php else: ?>
                    <span style="color:var(--warning);">⚠️ Required</span>
                <?php endif; ?>
            </span>
        </div>
        <div class="section-body">
            <div class="dropdown-section">
                <div class="dropdown-header" onclick="toggleDropdownSection('diseaseDropdownBody')">
                    <div class="dh-left">
                        <div class="dh-icon" style="background: var(--purple-gradient);"><i class="fas fa-list"></i></div>
                        <div class="dh-title">
                            <h4>Select Diseases</h4>
                            <p>Choose from list — auto-saves immediately</p>
                        </div>
                    </div>
                    <div class="dh-right">
                        <span class="dh-stat" style="background:var(--purple-bg);color:var(--purple);">
                            <i class="fas fa-database"></i> <?= count($diseases_list) ?>
                        </span>
                        <i class="fas fa-chevron-down dh-chevron"></i>
                    </div>
                </div>
                <div class="dropdown-body" id="diseaseDropdownBody">
                    <div class="dropdown-search-row">
                        <div class="search-input-wrap">
                            <i class="fas fa-search"></i>
                            <input type="text" id="diseaseSearch" placeholder="🔍 Search diseases..." oninput="filterDiseasesInside()">
                        </div>
                    </div>
                    <div id="diseaseCheckboxContainer" style="max-height:300px;overflow-y:auto;display:grid;grid-template-columns:repeat(3,1fr);gap:8px;padding:12px;background:var(--gray-50);border-radius:var(--radius);">
                        <?php foreach ($diseases_list as $disease): ?>
                            <label class="disease-checkbox-item" data-disease-name="<?= strtolower(htmlspecialchars($disease['disease_name'])) ?>" style="display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:10px;cursor:pointer;background:var(--bg-card);border:2px solid var(--border-color);transition:var(--transition);">
                                <input type="checkbox" name="diagnosis_ids[]" value="<?= $disease['id'] ?>" 
                                       <?= in_array($disease['id'], $selected_disease_ids) ? 'checked' : '' ?>
                                       onchange="saveDiagnosisImmediately()"
                                       style="width:20px;height:20px;accent-color:var(--primary);">
                                <span style="font-size:0.85rem;font-weight:500;flex:1;"><?= htmlspecialchars($disease['disease_name']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="form-group" style="margin-top:16px;">
                <label class="form-label">Manual Disease Entry</label>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <input type="text" class="form-control" id="manualDiseaseInput" placeholder="Enter disease name..." style="flex:1;min-width:200px;">
                    <input type="text" class="form-control" id="manualDiseaseCodeInput" placeholder="Code (optional)" style="flex:0.7;min-width:150px;">
                    <button type="button" class="btn btn-primary" onclick="addManualDisease()">
                        <i class="fas fa-plus"></i> Add & Save
                    </button>
                </div>
                <div id="manualDiseasesContainer" style="margin-top:12px;display:flex;flex-wrap:wrap;gap:8px;">
                    <?php foreach ($manual_diseases_saved as $idx => $manual_disease): ?>
                        <?php $manual_disease = trim($manual_disease); ?>
                        <?php if (!empty($manual_disease)): 
                            $saved_code = $saved_codes_array[$idx] ?? '';
                        ?>
                            <span style="display:inline-flex;align-items:center;gap:8px;padding:6px 14px;background:var(--primary-bg);border:1px solid var(--primary);border-radius:20px;font-size:0.8rem;color:var(--text-primary);font-weight:500;" data-disease="<?= htmlspecialchars($manual_disease) ?>" <?= $saved_code ? 'data-disease-code="' . htmlspecialchars($saved_code) . '"' : '' ?>>
                                <i class="fas fa-user-md" style="color:var(--primary);font-size:0.7rem;"></i>
                                <?= htmlspecialchars($manual_disease) ?>
                                <?php if ($saved_code): ?>
                                    <small style="font-family:'JetBrains Mono',monospace;background:var(--gray-200);padding:1px 6px;border-radius:6px;font-size:0.65rem;"><?= htmlspecialchars($saved_code) ?></small>
                                <?php endif; ?>
                                <button type="button" style="background:none;border:none;color:var(--danger);cursor:pointer;font-size:1rem;padding:0 2px;" onclick="removeManualDisease(this)">×</button>
                                <input type="hidden" name="manual_diseases[]" value="<?= htmlspecialchars($manual_disease) ?>">
                                <?php if ($saved_code): ?>
                                    <input type="hidden" name="manual_disease_codes[]" value="<?= htmlspecialchars($saved_code) ?>">
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Treatment Plan</label>
                <textarea name="treatment" class="form-control" rows="3" placeholder="Describe treatment plan..." id="treatmentTextarea" oninput="debouncedAutoSave()"><?= htmlspecialchars($visit['treatment'] ?? '') ?></textarea>
            </div>
        </div>
    </div>

    <!-- MEDICATIONS -->
    <div class="section-card section-success">
        <div class="section-header">
            <div class="section-title">
                <div class="title-icon" style="background: var(--success-gradient);"><i class="fas fa-pills"></i></div>
                Medications
            </div>
            <span style="background:var(--success-bg);color:var(--success);padding:5px 14px;border-radius:12px;font-size:0.7rem;font-weight:800;font-family:'JetBrains Mono',monospace;border:1px solid var(--success);">
                💊 TSh <span id="medTotalDisplay"><?= formatMoneyDisplay($medications_total) ?></span>
            </span>
        </div>
        <div class="section-body">
            <div class="dropdown-section">
                <div class="dropdown-header" onclick="toggleDropdownSection('medicationDropdownBody')">
                    <div class="dh-left">
                        <div class="dh-icon" style="background: var(--success-gradient);"><i class="fas fa-pills"></i></div>
                        <div class="dh-title">
                            <h4>Select Medication</h4>
                            <p>Search and select medications</p>
                        </div>
                    </div>
                    <div class="dh-right">
                        <span class="dh-stat" style="background:var(--success-bg);color:var(--success);">
                            <i class="fas fa-capsules"></i> <?= count($medications_grouped) ?>
                        </span>
                        <i class="fas fa-chevron-down dh-chevron"></i>
                    </div>
                </div>
                <div class="dropdown-body" id="medicationDropdownBody">
                    <div class="dropdown-search-row">
                        <div class="search-input-wrap">
                            <i class="fas fa-search"></i>
                            <input type="text" id="medicationSearch" placeholder="🔍 Search medications..." oninput="filterMedicationsInside()">
                        </div>
                    </div>
                    <div class="items-grid" id="medicationsGrid">
                        <?php foreach ($medications_grouped as $key => $group): 
                            $available_stock = $group['available_quantity'];
                            $is_selectable = $group['has_available'] && $available_stock > 0;
                            $batch_count = count($group['batches']);
                            
                            $status_badge = '';
                            $status_class = '';
                            if (!$group['has_available']) {
                                if ($group['has_expired']) { $status_badge = 'EXPIRED'; $status_class = 'expired'; }
                                elseif ($group['has_out_of_stock']) { $status_badge = 'OUT'; $status_class = 'out'; }
                                else { $status_badge = 'INACTIVE'; $status_class = 'inactive'; }
                            }
                        ?>
                            <div class="item-card medication-card <?= !$is_selectable ? 'disabled' : '' ?>" 
                                 data-medication-name="<?= htmlspecialchars($group['name']) ?>"
                                 data-medication-category="<?= htmlspecialchars($group['category'] ?? '') ?>"
                                 data-price="<?= $group['selling_price'] ?>"
                                 data-total-stock="<?= $available_stock ?>"
                                 data-batch-count="<?= $batch_count ?>"
                                 data-selectable="<?= $is_selectable ? '1' : '0' ?>"
                                 onclick="toggleMedicationCheckbox(this)">
                                <div style="display:flex;align-items:flex-start;gap:10px;width:100%;">
                                    <span class="item-check"><i class="fas fa-check"></i></span>
                                    <div class="item-info">
                                        <span class="item-name">
                                            <?= htmlspecialchars($group['name']) ?>
                                            <?php if ($status_badge): ?>
                                                <span class="item-status-badge <?= $status_class ?>"><?= $status_badge ?></span>
                                            <?php endif; ?>
                                        </span>
                                        <div class="item-details">
                                            <span class="item-price">TSh <?= formatMoneyDisplay($group['selling_price'] ?? 0) ?></span>
                                            <?php if ($is_selectable): ?>
                                                <span class="item-tag stock ok"><i class="fas fa-check-circle"></i> <?= $available_stock ?> total</span>
                                            <?php endif; ?>
                                            <?php if (!empty($group['category'])): ?>
                                                <span class="item-tag category"><?= htmlspecialchars($group['category']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($batch_count > 1 && $is_selectable): ?>
                                            <div class="multi-batch-badge">
                                                <i class="fas fa-layer-group"></i> <?= $batch_count ?> BATCHES — TOTAL: <?= $available_stock ?> UNITS
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="dropdown-action-row" style="border-top:none;padding-top:0;margin-top:10px;">
                        <span class="selected-badge">
                            <i class="fas fa-check-circle"></i> Selected: <span id="medSelectedInfo">None</span>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- MEDICATION FORM CARD -->
            <div class="med-form-card">
                <div class="med-form-header">
                    <div class="header-title">
                        <i class="fas fa-prescription-bottle-medical"></i>
                        Instructions — Quick Picks
                    </div>
                    <div class="quick-picks" id="quickPicksContainer">
                        <?php foreach ($instruction_quick_picks as $pick): ?>
                            <button type="button" class="quick-pick-btn" onclick="addInstructionQuickPick('<?= htmlspecialchars($pick) ?>', this)">
                                <i class="fas fa-plus"></i> <?= htmlspecialchars($pick) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <textarea id="medInstructions" class="instr-textarea" rows="2" placeholder="Instructions (click quick picks above or type custom)..."></textarea>
                </div>
                
                <div class="med-form-body">
                    <div class="med-fields-grid">
                        <div class="med-field">
                            <label><i class="fas fa-sort-numeric-up"></i> Qty</label>
                            <input type="number" id="medQuantity" value="1" min="1" max="999">
                        </div>
                        <div class="med-field">
                            <label><i class="fas fa-prescription-bottle"></i> Dosage</label>
                            <div class="field-with-toggle">
                                <select id="medDosage">
                                    <option value="">--</option>
                                    <option value="125mg">125mg</option>
                                    <option value="250mg">250mg</option>
                                    <option value="500mg">500mg</option>
                                    <option value="1g">1g</option>
                                    <option value="2g">2g</option>
                                    <option value="5mg">5mg</option>
                                    <option value="10mg">10mg</option>
                                    <option value="20mg">20mg</option>
                                    <option value="40mg">40mg</option>
                                    <option value="50mg">50mg</option>
                                    <option value="100mg">100mg</option>
                                    <option value="200mg">200mg</option>
                                    <option value="400mg">400mg</option>
                                    <option value="5ml">5ml</option>
                                    <option value="10ml">10ml</option>
                                    <option value="15ml">15ml</option>
                                    <option value="1 tablet">1 tablet</option>
                                    <option value="2 tablets">2 tablets</option>
                                    <option value="1 capsule">1 capsule</option>
                                </select>
                                <button type="button" class="btn-manual-toggle" onclick="toggleMedManual('dosage')" id="btnManualDosage" title="Manual">
                                    <i class="fas fa-keyboard"></i>
                                </button>
                            </div>
                            <input type="text" id="medDosageManual" class="manual-input-field" placeholder="Custom dosage...">
                        </div>
                        <div class="med-field">
                            <label><i class="fas fa-clock"></i> Frequency <span style="color:var(--danger);">*</span></label>
                            <div class="field-with-toggle">
                                <select id="medFrequency">
                                    <option value="">--</option>
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
                                <button type="button" class="btn-manual-toggle" onclick="toggleMedManual('frequency')" id="btnManualFrequency" title="Manual">
                                    <i class="fas fa-keyboard"></i>
                                </button>
                            </div>
                            <input type="text" id="medFrequencyManual" class="manual-input-field" placeholder="Custom frequency...">
                        </div>
                        <div class="med-field">
                            <label><i class="fas fa-calendar-alt"></i> Days</label>
                            <input type="number" id="medDuration" value="7" min="1" max="90">
                        </div>
                        <div class="med-field">
                            <label><i class="fas fa-route"></i> Route <span style="color:var(--danger);">*</span></label>
                            <div class="field-with-toggle">
                                <select id="medRoute">
                                    <option value="">--</option>
                                    <option value="Oral">Oral</option>
                                    <option value="Topical">Topical</option>
                                    <option value="Injection (IM)">Injection (IM)</option>
                                    <option value="Injection (IV)">Injection (IV)</option>
                                    <option value="Injection (SC)">Injection (SC)</option>
                                    <option value="Sublingual">Sublingual</option>
                                    <option value="Inhalation">Inhalation</option>
                                    <option value="Nasal">Nasal</option>
                                    <option value="Ophthalmic (Eye)">Ophthalmic (Eye)</option>
                                    <option value="Otic (Ear)">Otic (Ear)</option>
                                    <option value="Rectal">Rectal</option>
                                </select>
                                <button type="button" class="btn-manual-toggle" onclick="toggleMedManual('route')" id="btnManualRoute" title="Manual">
                                    <i class="fas fa-keyboard"></i>
                                </button>
                            </div>
                            <input type="text" id="medRouteManual" class="manual-input-field" placeholder="Custom route...">
                        </div>
                    </div>
                    <div class="med-form-footer">
                        <button type="button" class="btn btn-success" onclick="addMedicationAjax()" id="addMedicationBtn" style="padding:10px 28px;font-size:0.85rem;">
                            <i class="fas fa-plus"></i> Add Medication
                        </button>
                    </div>
                </div>
            </div>
            
            <div style="margin-top:20px;background:linear-gradient(135deg, var(--gray-50), var(--bg-card));border-radius:var(--radius);padding:18px;border:2px solid var(--border-color);">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;flex-wrap:wrap;gap:8px;">
                    <h4 style="font-size:0.9rem;font-weight:800;color:var(--text-secondary);margin:0;display:flex;align-items:center;gap:8px;">
                        <i class="fas fa-list" style="color:var(--success);"></i> Prescribed Medications
                        <span style="font-size:0.7rem;font-family:'JetBrains Mono',monospace;" id="medCount">(<?= count($grouped_prescriptions) ?> items)</span>
                    </h4>
                </div>
                <div id="medicationsList">
                    <?php if (count($grouped_prescriptions) > 0): ?>
                        <?php foreach ($grouped_prescriptions as $pid => $group): 
                            $main_med = $group['prescription'];
                            $is_paid_med = isBillItemPaid($db, $bill_id, $pid, 'prescription');
                            $can_remove = !$is_paid_med && !$is_completed;
                            $is_multi_batch = count($group['items']) > 1;
                        ?>
                            <div class="medication-item medication-prescription" id="med-item-<?= $pid ?>">
                                <div class="medication-item-info">
                                    <span class="med-name"><?= htmlspecialchars($main_med['medication_name'] ?? 'Unknown') ?></span>
                                    <span class="med-details">
                                        <?= htmlspecialchars($main_med['dosage'] ?? '') ?> • 
                                        <?= htmlspecialchars($main_med['frequency'] ?? '') ?> • 
                                        <?= htmlspecialchars($main_med['duration'] ?? '') ?> days
                                    </span>
                                    <span class="med-qty">x<?= $group['total_qty'] ?></span>
                                    <span class="med-price">TSh <?= formatMoneyDisplay($group['total_price']) ?></span>
                                    <?php if (!empty($main_med['instructions'])): ?>
                                        <span class="med-instruction-tag"><?= htmlspecialchars($main_med['instructions']) ?></span>
                                    <?php endif; ?>
                                    <span class="med-status-pending">
                                        <i class="fas fa-clock"></i> Pending
                                    </span>
                                    
                                    <?php if ($is_multi_batch): ?>
                                        <div class="batch-info-list">
                                            <div class="batch-info-header">
                                                <i class="fas fa-layer-group"></i> 
                                                Batches Used: <?= count($group['items']) ?>
                                            </div>
                                            <?php foreach ($group['items'] as $batch_item): ?>
                                                <div class="batch-info-row">
                                                    <span class="batch-num">
                                                        <i class="fas fa-box"></i>
                                                        Batch: <?= htmlspecialchars($batch_item['batch_number'] ?? 'N/A') ?>
                                                    </span>
                                                    <span class="batch-qty">× <?= $batch_item['quantity'] ?? 0 ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php if ($can_remove): ?>
                                    <button type="button" class="btn-remove" onclick="removeMedication(<?= $pid ?>)" title="Remove">
                                        <i class="fas fa-times"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-medications" id="emptyMedications">
                            <i class="fas fa-pills"></i>
                            <p>No medications prescribed yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- PROCEDURES & EQUIPMENT -->
    <div class="section-card section-purple">
        <div class="section-header">
            <div class="section-title">
                <div class="title-icon" style="background: var(--purple-gradient);"><i class="fas fa-syringe"></i></div>
                Procedures & Equipment
            </div>
            <span style="background:var(--purple-bg);color:var(--purple);padding:5px 14px;border-radius:12px;font-size:0.7rem;font-weight:800;font-family:'JetBrains Mono',monospace;border:1px solid var(--purple);">
                🛠️ TSh <span id="procEquipTotalDisplay"><?= formatMoneyDisplay($procedure_total + $equipment_total) ?></span>
            </span>
        </div>
        <div class="section-body">
            <!-- PROCEDURES -->
            <div class="dropdown-section">
                <div class="dropdown-header" onclick="toggleDropdownSection('proceduresToggle')">
                    <div class="dh-left">
                        <div class="dh-icon" style="background: var(--purple-gradient);"><i class="fas fa-syringe"></i></div>
                        <div class="dh-title">
                            <h4>Procedures</h4>
                            <p>Select medical procedures</p>
                        </div>
                    </div>
                    <div class="dh-right">
                        <span class="dh-stat" style="background:var(--purple-bg);color:var(--purple);">
                            <i class="fas fa-list"></i> <?= count($procedures_list) ?>
                        </span>
                        <i class="fas fa-chevron-down dh-chevron"></i>
                    </div>
                </div>
                <div class="dropdown-body" id="proceduresToggle">
                    <div class="dropdown-search-row">
                        <div class="search-input-wrap">
                            <i class="fas fa-search"></i>
                            <input type="text" id="procedureSearch" placeholder="🔍 Search procedures..." oninput="filterProceduresInside()">
                        </div>
                    </div>
                    <div class="items-grid" id="proceduresGrid">
                        <?php foreach ($procedures_list as $proc): ?>
                            <div class="item-card procedure-card" 
                                 data-procedure-id="<?= $proc['id'] ?>"
                                 data-procedure-name="<?= htmlspecialchars($proc['procedure_name']) ?>"
                                 onclick="toggleProcedure(this)">
                                <div style="display:flex;align-items:flex-start;gap:10px;width:100%;">
                                    <span class="item-check"><i class="fas fa-check"></i></span>
                                    <div class="item-info">
                                        <span class="item-name"><?= htmlspecialchars($proc['procedure_name']) ?></span>
                                        <div class="item-details">
                                            <span class="item-price"><?= ($proc['price'] ?? 0) > 0 ? 'TSh ' . formatMoneyDisplay($proc['price']) : 'FREE' ?></span>
                                            <?php if (!empty($proc['category'])): ?>
                                                <span class="item-tag category"><?= htmlspecialchars($proc['category']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="dropdown-action-row">
                        <button type="button" class="btn btn-primary btn-sm" onclick="addSelectedProcedures()">
                            <i class="fas fa-plus"></i> Add Selected Procedures
                        </button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="clearProcedureSelections()">
                            <i class="fas fa-times"></i> Clear
                        </button>
                        <span class="selected-badge">
                            <i class="fas fa-check-circle"></i> Selected: <span id="procSelectedCount">0</span>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- EQUIPMENT -->
            <div class="dropdown-section">
                <div class="dropdown-header" onclick="toggleDropdownSection('equipmentToggle')">
                    <div class="dh-left">
                        <div class="dh-icon" style="background: var(--warning-gradient);"><i class="fas fa-tools"></i></div>
                        <div class="dh-title">
                            <h4>Medical Equipment</h4>
                            <p>Select equipment to use</p>
                        </div>
                    </div>
                    <div class="dh-right">
                        <span class="dh-stat" style="background:var(--warning-bg);color:var(--warning);">
                            <i class="fas fa-boxes"></i> <?= count($equipment_list) ?>
                        </span>
                        <i class="fas fa-chevron-down dh-chevron"></i>
                    </div>
                </div>
                <div class="dropdown-body" id="equipmentToggle">
                    <div class="dropdown-search-row">
                        <div class="search-input-wrap">
                            <i class="fas fa-search"></i>
                            <input type="text" id="equipmentSearch" placeholder="🔍 Search equipment..." oninput="filterEquipmentInside()">
                        </div>
                    </div>
                    <div class="items-grid" id="equipmentGrid">
                        <?php foreach ($equipment_list as $eq): 
                            $expiry_status = 'no-expiry'; $expiry_label = 'No Expiry';
                            $expiry_date = $eq['expiry_date'] ?? '';
                            if (!empty($expiry_date) && $expiry_date !== '0000-00-00') {
                                $expiry_timestamp = strtotime($expiry_date);
                                $days_remaining = floor(($expiry_timestamp - time()) / 86400);
                                if ($days_remaining < 0) { $expiry_status = 'expired'; $expiry_label = 'EXPIRED'; }
                                elseif ($days_remaining <= 30) { $expiry_status = 'expiring'; $expiry_label = 'Exp: ' . date('d/m/Y', $expiry_timestamp); }
                                else { $expiry_status = 'valid'; $expiry_label = 'Exp: ' . date('d/m/Y', $expiry_timestamp); }
                            }
                            $stock_status = 'ok';
                            if ($eq['quantity'] <= 0) $stock_status = 'out';
                            elseif ($eq['quantity'] <= 10) $stock_status = 'low';
                            $is_disabled = ($eq['quantity'] <= 0) || ($expiry_status === 'expired');
                        ?>
                            <div class="item-card equipment-card <?= $is_disabled ? 'disabled' : '' ?>" 
                                 data-equipment-id="<?= $eq['id'] ?>"
                                 data-equipment-name="<?= htmlspecialchars($eq['equipment_name']) ?>"
                                 data-stock="<?= $eq['quantity'] ?>"
                                 onclick="toggleEquipment(this)">
                                <div style="display:flex;align-items:flex-start;gap:10px;width:100%;">
                                    <span class="item-check"><i class="fas fa-check"></i></span>
                                    <div class="item-info">
                                        <span class="item-name"><?= htmlspecialchars($eq['equipment_name']) ?></span>
                                        <div class="item-details">
                                            <span class="item-price"><?= ($eq['selling_price'] ?? 0) > 0 ? 'TSh ' . formatMoneyDisplay($eq['selling_price']) : 'FREE' ?></span>
                                            <span class="item-tag stock <?= $stock_status ?>"><i class="fas fa-box"></i> <?= $eq['quantity'] ?></span>
                                            <span class="item-tag expiry <?= $expiry_status ?>"><?= $expiry_label ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="dropdown-action-row">
                        <div style="display:flex;gap:8px;align-items:center;">
                            <label style="font-size:0.78rem;color:var(--text-secondary);font-weight:700;">Qty:</label>
                            <input type="number" id="equipmentQuantity" class="form-control" value="1" min="1" style="width:90px;">
                        </div>
                        <button type="button" class="btn btn-primary btn-sm" onclick="addSelectedEquipment()">
                            <i class="fas fa-plus"></i> Add Equipment
                        </button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="clearEquipmentSelections()">
                            <i class="fas fa-times"></i> Clear
                        </button>
                        <span class="selected-badge">
                            <i class="fas fa-check-circle"></i> Selected: <span id="equipSelectedCount">0</span>
                        </span>
                    </div>
                </div>
            </div>
            
            <div style="margin-top:20px;background:linear-gradient(135deg, var(--gray-50), var(--bg-card));border-radius:var(--radius);padding:18px;border:2px solid var(--border-color);">
                <h4 style="font-size:0.9rem;font-weight:800;color:var(--text-secondary);margin:0 0 10px 0;display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-list" style="color:var(--purple);"></i> Added Procedures & Equipment
                </h4>
                <div id="addedItemsList">
                    <?php if (count($procedures) > 0 || count($equipment_items_display) > 0): ?>
                        <?php foreach ($procedures as $proc): 
                            $is_paid_proc = isBillItemPaid($db, $bill_id, $proc['id'], 'procedure');
                        ?>
                            <div class="medication-item procedure-item" id="added-procedure-<?= $proc['id'] ?>">
                                <div class="medication-item-info">
                                    <span class="med-name"><?= htmlspecialchars($proc['procedure_name']) ?></span>
                                    <span class="med-details">
                                        <i class="fas fa-tag"></i> <?= htmlspecialchars($proc['category'] ?? 'Procedure') ?>
                                    </span>
                                    <span class="med-price">TSh <?= formatMoneyDisplay($proc['procedure_price']) ?></span>
                                </div>
                                <?php if (!$is_paid_proc && !$is_completed): ?>
                                    <button type="button" class="btn-remove" onclick="removeAddedItem('procedure', <?= $proc['id'] ?>)" title="Remove">
                                        <i class="fas fa-times"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <?php foreach ($equipment_items_display as $eq_item): 
                            $can_remove_eq = ($eq_item['status'] !== 'paid') && !$is_completed;
                        ?>
                            <div class="medication-item equipment-item" id="added-equipment-<?= $eq_item['id'] ?>">
                                <div class="medication-item-info">
                                    <span class="med-name"><?= htmlspecialchars($eq_item['item_name']) ?></span>
                                    <span class="med-details">
                                        <i class="fas fa-box"></i> Qty: <?= $eq_item['quantity'] ?>
                                        <?php if (!empty($eq_item['batch_number'])): ?>
                                            • Batch: <?= htmlspecialchars($eq_item['batch_number']) ?>
                                        <?php endif; ?>
                                    </span>
                                    <span class="med-qty">x<?= $eq_item['quantity'] ?></span>
                                    <span class="med-price">TSh <?= formatMoneyDisplay($eq_item['total_price']) ?></span>
                                </div>
                                <?php if ($can_remove_eq): ?>
                                    <button type="button" class="btn-remove" onclick="removeAddedItem('equipment', <?= $eq_item['id'] ?>)" title="Remove">
                                        <i class="fas fa-times"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-added">
                            <i class="fas fa-syringe"></i>
                            <p>No procedures or equipment added yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php endif; ?>
    
    <!-- FORM ACTIONS -->
    <div class="section-card section-primary">
        <div class="section-body">
            <div style="display:flex;gap:14px;flex-wrap:wrap;justify-content:center;padding:10px 0;">
                <?php if ($lock_sections): ?>
                    <button type="button" class="btn btn-outline" style="padding:14px 32px;font-size:0.9rem;opacity:0.6;cursor:not-allowed;" disabled>
                        <i class="fas fa-lock"></i> Save Locked — Wait for Lab Results
                    </button>
                <?php else: ?>
                    <button type="submit" name="save_consultation" class="btn btn-success" style="padding:14px 32px;font-size:0.9rem;">
                        <i class="fas fa-save"></i> Save Consultation → WAITING
                    </button>
                <?php endif; ?>
                <a href="my_patients.php" class="btn btn-outline" style="padding:14px 32px;font-size:0.9rem;">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </div>
    </div>
    
    </form>
    
    <?php else: ?>
    <!-- COMPLETED VIEW -->
    <div class="section-card section-purple">
        <div class="section-header">
            <div class="section-title">
                <div class="title-icon" style="background: var(--purple-gradient);"><i class="fas fa-diagnoses"></i></div>
                Diagnosis & Treatment Plan
            </div>
        </div>
        <div class="section-body">
            <?php if (!empty($visit['diagnosis']) || !empty($visit['treatment'])): ?>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                    <div class="info-cell" style="background: linear-gradient(135deg, var(--purple-bg), var(--bg-card));border-color:var(--purple);">
                        <div class="info-label"><i class="fas fa-stethoscope"></i> Diagnosis</div>
                        <div class="info-value" style="font-size:1rem;font-weight:800;color:var(--purple);"><?= htmlspecialchars($visit['diagnosis'] ?? 'N/A') ?></div>
                    </div>
                    <div class="info-cell" style="background: linear-gradient(135deg, var(--primary-bg), var(--bg-card));border-color:var(--primary);">
                        <div class="info-label"><i class="fas fa-barcode"></i> Disease Code</div>
                        <div class="info-value" style="font-family:'JetBrains Mono',monospace;font-weight:800;color:var(--primary);"><?= htmlspecialchars($visit['disease_code'] ?? 'N/A') ?></div>
                    </div>
                </div>
                <div class="info-cell" style="background: linear-gradient(135deg, var(--success-bg), var(--bg-card));border-color:var(--success);">
                    <div class="info-label"><i class="fas fa-prescription-bottle-medical"></i> Treatment Plan</div>
                    <div class="info-value" style="font-size:0.9rem;white-space:pre-wrap;"><?= nl2br(htmlspecialchars($visit['treatment'] ?? 'No treatment plan recorded')) ?></div>
                </div>
            <?php else: ?>
                <div style="text-align:center;padding:30px;color:var(--text-secondary);">
                    <i class="fas fa-diagnoses" style="font-size:2rem;display:block;margin-bottom:8px;opacity:0.3;"></i>
                    <p>No diagnosis recorded</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (count($grouped_prescriptions) > 0): ?>
    <div class="section-card section-success">
        <div class="section-header">
            <div class="section-title">
                <div class="title-icon" style="background: var(--success-gradient);"><i class="fas fa-pills"></i></div>
                Prescribed Medications
            </div>
            <span style="background:var(--success-bg);color:var(--success);padding:5px 14px;border-radius:12px;font-size:0.7rem;font-weight:800;font-family:'JetBrains Mono',monospace;border:1px solid var(--success);">
                💊 TSh <?= formatMoneyDisplay($medications_total) ?> (<?= count($grouped_prescriptions) ?>)
            </span>
        </div>
        <div class="section-body">
            <div id="medicationsList">
                <?php foreach ($grouped_prescriptions as $pid => $group): 
                    $main_med = $group['prescription'];
                    $med_rx_status = $main_med['prescription_status'] ?? 'pending';
                    $is_paid_med = isBillItemPaid($db, $bill_id, $pid, 'prescription');
                    $status_info = getPrescriptionStatusInfo($med_rx_status, $is_paid_med);
                    $is_multi_batch = count($group['items']) > 1;
                ?>
                    <div class="medication-item medication-prescription">
                        <div class="medication-item-info">
                            <span class="med-name"><?= htmlspecialchars($main_med['medication_name'] ?? 'Unknown') ?></span>
                            <span class="med-details">
                                <?= htmlspecialchars($main_med['dosage'] ?? '') ?> • 
                                <?= htmlspecialchars($main_med['frequency'] ?? '') ?> • 
                                <?= htmlspecialchars($main_med['duration'] ?? '') ?> days
                            </span>
                            <span class="med-qty">x<?= $group['total_qty'] ?></span>
                            <span class="med-price">TSh <?= formatMoneyDisplay($group['total_price']) ?></span>
                            <?php if (!empty($main_med['instructions'])): ?>
                                <span class="med-instruction-tag"><?= htmlspecialchars($main_med['instructions']) ?></span>
                            <?php endif; ?>
                            <span class="<?= $status_info['class'] ?>" style="font-size:0.62rem;padding:4px 12px;border-radius:12px;font-weight:800;display:inline-flex;align-items:center;gap:4px;text-transform:uppercase;">
                                <i class="fas <?= $status_info['icon'] ?>"></i> <?= $status_info['label'] ?>
                            </span>
                            <?php if ($is_multi_batch): ?>
                                <div class="batch-info-list">
                                    <div class="batch-info-header">
                                        <i class="fas fa-layer-group"></i> 
                                        Batches Used: <?= count($group['items']) ?>
                                    </div>
                                    <?php foreach ($group['items'] as $batch_item): ?>
                                        <div class="batch-info-row">
                                            <span class="batch-num">
                                                <i class="fas fa-box"></i>
                                                Batch: <?= htmlspecialchars($batch_item['batch_number'] ?? 'N/A') ?>
                                            </span>
                                            <span class="batch-qty">× <?= $batch_item['quantity'] ?? 0 ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (count($procedures) > 0): ?>
    <div class="section-card section-purple">
        <div class="section-header">
            <div class="section-title">
                <div class="title-icon" style="background: var(--purple-gradient);"><i class="fas fa-syringe"></i></div>
                Procedures
            </div>
            <span style="background:var(--purple-bg);color:var(--purple);padding:5px 14px;border-radius:12px;font-size:0.7rem;font-weight:800;font-family:'JetBrains Mono',monospace;border:1px solid var(--purple);">
                🩺 TSh <?= formatMoneyDisplay($procedure_total) ?> (<?= count($procedures) ?>)
            </span>
        </div>
        <div class="section-body">
            <div id="addedItemsList">
                <?php foreach ($procedures as $proc): 
                    $proc_status = $proc['status'] ?? 'pending';
                    $proc_class = 'badge-pending';
                    $proc_label = '⏳ Pending';
                    $proc_icon = 'fa-clock';
                    if ($proc_status === 'completed') { $proc_class = 'badge-dispensed'; $proc_label = '✅ Completed'; $proc_icon = 'fa-check-circle'; }
                    elseif ($proc_status === 'in_progress') { $proc_class = 'badge-confirmed'; $proc_label = '🔄 In Progress'; $proc_icon = 'fa-spinner'; }
                    elseif ($proc_status === 'cancelled') { $proc_class = 'badge-cancelled'; $proc_label = '❌ Cancelled'; $proc_icon = 'fa-times-circle'; }
                ?>
                    <div class="medication-item procedure-item">
                        <div class="medication-item-info">
                            <span class="med-name"><?= htmlspecialchars($proc['procedure_name'] ?? 'N/A') ?></span>
                            <span class="med-details">
                                <i class="fas fa-tag"></i> <?= htmlspecialchars($proc['category'] ?? 'Procedure') ?>
                            </span>
                            <span class="med-price">TSh <?= formatMoneyDisplay($proc['procedure_price'] ?? 0) ?></span>
                            <span class="<?= $proc_class ?>" style="font-size:0.62rem;padding:4px 12px;border-radius:12px;font-weight:800;display:inline-flex;align-items:center;gap:4px;text-transform:uppercase;">
                                <i class="fas <?= $proc_icon ?>"></i> <?= $proc_label ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (count($equipment_items_display) > 0): ?>
    <div class="section-card section-warning">
        <div class="section-header">
            <div class="section-title">
                <div class="title-icon" style="background: var(--warning-gradient);"><i class="fas fa-tools"></i></div>
                Medical Equipment
            </div>
            <span style="background:var(--warning-bg);color:var(--warning);padding:5px 14px;border-radius:12px;font-size:0.7rem;font-weight:800;font-family:'JetBrains Mono',monospace;border:1px solid var(--warning);">
                🛠️ TSh <?= formatMoneyDisplay($equipment_total) ?> (<?= count($equipment_items_display) ?>)
            </span>
        </div>
        <div class="section-body">
            <div id="addedItemsList">
                <?php foreach ($equipment_items_display as $eq_item): 
                    $eq_status = $eq_item['status'] ?? 'pending';
                    $eq_class = 'badge-pending';
                    $eq_label = '⏳ Pending';
                    $eq_icon = 'fa-clock';
                    if ($eq_status === 'paid') { $eq_class = 'badge-paid'; $eq_label = '💰 Paid'; $eq_icon = 'fa-money-bill'; }
                    elseif ($eq_status === 'cancelled') { $eq_class = 'badge-cancelled'; $eq_label = '❌ Cancelled'; $eq_icon = 'fa-times-circle'; }
                ?>
                    <div class="medication-item equipment-item">
                        <div class="medication-item-info">
                            <span class="med-name"><?= htmlspecialchars($eq_item['item_name'] ?? 'N/A') ?></span>
                            <span class="med-details">
                                <i class="fas fa-box"></i> Qty: <?= $eq_item['quantity'] ?? 1 ?>
                                <?php if (!empty($eq_item['batch_number'])): ?>
                                    • Batch: <?= htmlspecialchars($eq_item['batch_number']) ?>
                                <?php endif; ?>
                            </span>
                            <span class="med-qty">x<?= $eq_item['quantity'] ?? 1 ?></span>
                            <span class="med-price">TSh <?= formatMoneyDisplay($eq_item['total_price'] ?? 0) ?></span>
                            <span class="<?= $eq_class ?>" style="font-size:0.62rem;padding:4px 12px;border-radius:12px;font-weight:800;display:inline-flex;align-items:center;gap:4px;text-transform:uppercase;">
                                <i class="fas <?= $eq_icon ?>"></i> <?= $eq_label ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (count($bill_items) > 0): ?>
    <div class="section-card section-primary">
        <div class="section-header">
            <div class="section-title">
                <div class="title-icon" style="background: var(--primary-gradient);"><i class="fas fa-file-invoice-dollar"></i></div>
                Bill Items
            </div>
            <span style="background:var(--primary-bg);color:var(--primary);padding:5px 14px;border-radius:12px;font-size:0.7rem;font-weight:800;font-family:'JetBrains Mono',monospace;border:1px solid var(--primary);">
                🧾 TSh <?= formatMoneyDisplay($bill_subtotal) ?> (<?= count($bill_items) ?>)
            </span>
        </div>
        <div class="section-body" style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="th-primary">#</th>
                        <th class="th-primary">Item Name</th>
                        <th class="th-primary">Type</th>
                        <th class="th-primary" style="text-align:center;">Qty</th>
                        <th class="th-primary" style="text-align:right;">Unit Price</th>
                        <th class="th-primary" style="text-align:right;">Total</th>
                        <th class="th-primary" style="text-align:center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $bi_num = 1; foreach ($bill_items as $item): 
                        $item_status = $item['status'] ?? 'pending';
                        $item_class = 'badge-pending';
                        $item_label = '⏳ Pending';
                        $item_icon = 'fa-clock';
                        if ($item_status === 'paid') { $item_class = 'badge-paid'; $item_label = '💰 Paid'; $item_icon = 'fa-money-bill'; }
                        elseif ($item_status === 'partial') { $item_class = 'badge-confirmed'; $item_label = '🔄 Partial'; $item_icon = 'fa-hourglass-half'; }
                        elseif ($item_status === 'cancelled') { $item_class = 'badge-cancelled'; $item_label = '❌ Cancelled'; $item_icon = 'fa-times-circle'; }
                        
                        $item_icon_type = 'fa-cube';
                        if ($item['item_type'] === 'medication') $item_icon_type = 'fa-pills';
                        elseif ($item['item_type'] === 'lab_test') $item_icon_type = 'fa-flask';
                        elseif ($item['item_type'] === 'procedure') $item_icon_type = 'fa-syringe';
                        elseif ($item['item_type'] === 'equipment') $item_icon_type = 'fa-tools';
                    ?>
                        <tr>
                            <td style="font-family:'JetBrains Mono',monospace;font-size:0.72rem;color:var(--text-secondary);"><?= $bi_num++ ?></td>
                            <td style="font-weight:600;color:var(--text-primary);display:flex;align-items:center;gap:8px;">
                                <i class="fas <?= $item_icon_type ?>" style="color:var(--primary);"></i>
                                <?= htmlspecialchars($item['item_name'] ?? 'N/A') ?>
                            </td>
                            <td>
                                <span style="font-size:0.62rem;padding:3px 10px;border-radius:8px;background:var(--primary-bg);color:var(--primary);font-weight:700;text-transform:uppercase;">
                                    <?= htmlspecialchars($item['item_type'] ?? 'other') ?>
                                </span>
                            </td>
                            <td style="text-align:center;font-weight:700;font-family:'JetBrains Mono',monospace;">x<?= $item['quantity'] ?? 1 ?></td>
                            <td style="text-align:right;font-family:'JetBrains Mono',monospace;">TSh <?= formatMoneyDisplay($item['unit_price'] ?? 0) ?></td>
                            <td style="text-align:right;font-weight:700;color:var(--success);font-family:'JetBrains Mono',monospace;">TSh <?= formatMoneyDisplay($item['total_price'] ?? 0) ?></td>
                            <td style="text-align:center;">
                                <span class="<?= $item_class ?>" style="font-size:0.62rem;padding:4px 12px;border-radius:14px;font-weight:800;text-transform:uppercase;display:inline-flex;align-items:center;gap:4px;">
                                    <i class="fas <?= $item_icon ?>"></i> <?= $item_label ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot style="font-weight:800;">
                    <tr style="background:linear-gradient(135deg, var(--primary-bg), var(--bg-card));">
                        <td colspan="5" style="text-align:right;padding:12px 14px;font-size:0.75rem;color:var(--primary);text-transform:uppercase;">Subtotal:</td>
                        <td style="text-align:right;padding:12px 14px;color:var(--primary);font-family:'JetBrains Mono',monospace;font-size:0.9rem;">TSh <?= formatMoneyDisplay($bill_subtotal) ?></td>
                        <td></td>
                    </tr>
                    <tr style="background:linear-gradient(135deg, var(--success-bg), var(--bg-card));border-top:3px solid var(--success);">
                        <td colspan="5" style="text-align:right;padding:12px 14px;font-size:0.85rem;color:var(--success);text-transform:uppercase;">Grand Total:</td>
                        <td style="text-align:right;padding:12px 14px;color:var(--success);font-family:'JetBrains Mono',monospace;font-size:1.05rem;font-weight:900;">TSh <?= formatMoneyDisplay($bill_total) ?></td>
                        <td></td>
                    </tr>
                    <tr style="background:linear-gradient(135deg, var(--primary-bg), var(--bg-card));">
                        <td colspan="5" style="text-align:right;padding:10px 14px;font-size:0.75rem;color:var(--primary);text-transform:uppercase;">Paid:</td>
                        <td style="text-align:right;padding:10px 14px;color:var(--primary);font-family:'JetBrains Mono',monospace;">TSh <?= formatMoneyDisplay($bill_paid) ?></td>
                        <td></td>
                    </tr>
                    <tr style="background:<?= $bill_balance > 0 ? 'linear-gradient(135deg, var(--danger-bg), var(--bg-card))' : 'linear-gradient(135deg, var(--success-bg), var(--bg-card))' ?>;">
                        <td colspan="5" style="text-align:right;padding:10px 14px;font-size:0.75rem;color:<?= $bill_balance > 0 ? 'var(--danger)' : 'var(--success)' ?>;text-transform:uppercase;">Balance:</td>
                        <td style="text-align:right;padding:10px 14px;color:<?= $bill_balance > 0 ? 'var(--danger)' : 'var(--success)' ?>;font-family:'JetBrains Mono',monospace;">TSh <?= formatMoneyDisplay($bill_balance) ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <?php endif; ?>
    
    <?php endif; ?>

    <footer style="padding:20px 0;border-top:2px solid var(--border-color);margin-top:24px;text-align:center;font-size:0.7rem;color:var(--text-secondary);">
        <p>
            <span style="color:var(--primary);font-weight:700;">Braick Dispensary</span> Management System
            <span style="margin:0 8px;color:var(--border-color);">|</span>
            Consultation V32
            <span style="margin:0 8px;color:var(--border-color);">|</span>
            <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>
            <span style="margin:0 8px;color:var(--border-color);">|</span>
            &copy; <?= date('Y') ?>
        </p>
    </footer>

</main>

<div id="toast" class="toast-custom" style="display:none;">
    <div class="toast-icon"><i class="fas fa-info-circle"></i></div>
    <div class="toast-content">
        <p style="font-weight:700;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.8rem;margin:0;opacity:0.95;" id="toastMessage"></p>
    </div>
    <button class="toast-close" onclick="closeToast()">×</button>
</div>

<script>
var pauseAutoUpdate = false;
var visitId = <?= $visit_id ?>;
var isCompleted = <?= $is_completed ? 'true' : 'false' ?>;
var isWaiting = <?= $is_waiting ? 'true' : 'false' ?>;
var lockSections = <?= $lock_sections ? 'true' : 'false' ?>;

var selectedProcedures = [];
var selectedEquipment = [];
var selectedLabTests = [];
var selectedMedications = [];
var complaintsList = <?= json_encode(array_filter(array_map('trim', explode(',', $visit['symptoms'] ?? '')))) ?>;
var lastSavedHash = '';
var autoSaveTimer = null;
var diagnosisSaved = <?= !empty($visit['diagnosis']) ? 'true' : 'false' ?>;

var manualModes = { dosage: false, frequency: false, route: false };

function addInstructionQuickPick(text, btn) {
    var textarea = document.getElementById('medInstructions');
    if (!textarea) return;
    var currentValue = textarea.value.trim();
    var parts = currentValue ? currentValue.split(',').map(function(s) { return s.trim(); }).filter(function(s) { return s.length > 0; }) : [];
    if (parts.indexOf(text) > -1) {
        parts = parts.filter(function(p) { return p !== text; });
        if (btn) btn.classList.remove('selected');
    } else {
        parts.push(text);
        if (btn) btn.classList.add('selected');
    }
    textarea.value = parts.join(', ');
}

function showSavedIndicator(text) {
    var indicator = document.getElementById('savedIndicator');
    if (!indicator) return;
    indicator.querySelector('span').textContent = text || 'Saved!';
    indicator.classList.add('show');
    clearTimeout(indicator.timeout);
    indicator.timeout = setTimeout(function() {
        indicator.classList.remove('show');
    }, 2000);
}

function debouncedAutoSave() {
    if (isCompleted || isWaiting || lockSections) return;
    clearTimeout(autoSaveTimer);
    autoSaveTimer = setTimeout(function() {
        autoSaveFormData();
    }, 1500);
}

function autoSaveFormData() {
    if (isCompleted || isWaiting || lockSections) return;
    var symptoms = document.getElementById('symptomsTextarea')?.value || '';
    var hpi = document.getElementById('hpiTextarea')?.value || '';
    var physical_exam = document.getElementById('physicalExamTextarea')?.value || '';
    var notes = document.getElementById('notesTextarea')?.value || '';
    var treatment = document.getElementById('treatmentTextarea')?.value || '';
    if (!symptoms && !hpi && !physical_exam && !notes && !treatment) return;
    var currentHash = JSON.stringify({ s: symptoms, h: hpi, p: physical_exam, n: notes, t: treatment });
    if (currentHash === lastSavedHash) return;
    lastSavedHash = currentHash;
    var diagnosis_ids = [];
    document.querySelectorAll('input[name="diagnosis_ids[]"]:checked').forEach(function(cb) {
        var val = parseInt(cb.value);
        if (val > 0) diagnosis_ids.push(val);
    });
    var manual_diseases = [];
    var manual_disease_codes = [];
    document.querySelectorAll('#manualDiseasesContainer > span').forEach(function(tag) {
        var nameInput = tag.querySelector('input[name="manual_diseases[]"]');
        if (!nameInput) return;
        var name = nameInput.value.trim();
        if (!name) return;
        manual_diseases.push(name);
        var codeInput = tag.querySelector('input[name="manual_disease_codes[]"]');
        manual_disease_codes.push(codeInput ? codeInput.value.trim() : '');
    });
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            action: 'save_diagnosis', visit_id: visitId, diagnosis_ids: diagnosis_ids, 
            manual_diseases: manual_diseases, manual_disease_codes: manual_disease_codes, 
            treatment: treatment, symptoms: symptoms, hpi: hpi, physical_exam: physical_exam, notes: notes 
        })
    })
    .then(r => r.json())
    .then(function(result) {
        if (result.success) showSavedIndicator('Form auto-saved');
    })
    .catch(function() {});
}

function saveDiagnosisImmediately() {
    if (isCompleted || isWaiting) return;
    var diagnosis_ids = [];
    document.querySelectorAll('input[name="diagnosis_ids[]"]:checked').forEach(function(cb) {
        var val = parseInt(cb.value);
        if (val > 0) diagnosis_ids.push(val);
    });
    var manual_diseases = [];
    var manual_disease_codes = [];
    document.querySelectorAll('#manualDiseasesContainer > span').forEach(function(tag) {
        var nameInput = tag.querySelector('input[name="manual_diseases[]"]');
        if (!nameInput) return;
        var name = nameInput.value.trim();
        if (!name) return;
        manual_diseases.push(name);
        var codeInput = tag.querySelector('input[name="manual_disease_codes[]"]');
        manual_disease_codes.push(codeInput ? codeInput.value.trim() : '');
    });
    var symptoms = document.getElementById('symptomsTextarea')?.value || '';
    var hpi = document.getElementById('hpiTextarea')?.value || '';
    var physical_exam = document.getElementById('physicalExamTextarea')?.value || '';
    var notes = document.getElementById('notesTextarea')?.value || '';
    var treatment = document.getElementById('treatmentTextarea')?.value || '';
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            action: 'save_diagnosis', visit_id: visitId, diagnosis_ids: diagnosis_ids, 
            manual_diseases: manual_diseases, manual_disease_codes: manual_disease_codes, 
            treatment: treatment, symptoms: symptoms, hpi: hpi, physical_exam: physical_exam, notes: notes 
        })
    })
    .then(r => r.json())
    .then(function(result) {
        if (result.success) {
            showSavedIndicator('✅ Diagnosis saved');
            diagnosisSaved = true;
            var statusEl = document.getElementById('diagnosisStatus');
            if (statusEl && result.data && result.data.diagnosis) {
                statusEl.innerHTML = '<span class="saved-banner"><i class="fas fa-check-circle"></i> Saved: ' + escapeHtml(result.data.diagnosis) + '</span>';
            }
        }
    })
    .catch(function() {});
}

function collectFormData() {
    var formData = new FormData();
    var diagnosis_ids = [];
    document.querySelectorAll('input[name="diagnosis_ids[]"]:checked').forEach(function(cb) {
        var val = parseInt(cb.value);
        if (val > 0) diagnosis_ids.push(val);
    });
    var manual_diseases = [];
    var manual_disease_codes = [];
    document.querySelectorAll('#manualDiseasesContainer > span').forEach(function(tag) {
        var nameInput = tag.querySelector('input[name="manual_diseases[]"]');
        if (!nameInput) return;
        var name = nameInput.value.trim();
        if (!name) return;
        manual_diseases.push(name);
        var codeInput = tag.querySelector('input[name="manual_disease_codes[]"]');
        manual_disease_codes.push(codeInput ? codeInput.value.trim() : '');
    });
    formData.append('diagnosis_ids', JSON.stringify(diagnosis_ids));
    formData.append('manual_diseases', JSON.stringify(manual_diseases));
    formData.append('manual_disease_codes', JSON.stringify(manual_disease_codes));
    formData.append('treatment', document.getElementById('treatmentTextarea')?.value || '');
    formData.append('symptoms', document.getElementById('symptomsTextarea')?.value || '');
    formData.append('hpi', document.getElementById('hpiTextarea')?.value || '');
    formData.append('physical_exam', document.getElementById('physicalExamTextarea')?.value || '');
    formData.append('notes', document.getElementById('notesTextarea')?.value || '');
    return formData;
}

function checkLabStatusNow(btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';
    var formData = new FormData();
    formData.append('action', 'get_bill_totals');
    fetch(window.location.href, { method: 'POST', body: formData })
    .then(r => r.json())
    .then(function() { setTimeout(function() { window.location.reload(); }, 500); })
    .catch(function() { setTimeout(function() { window.location.reload(); }, 500); });
}

function showToast(title, message, type) {
    var toast = document.getElementById('toast');
    var toastTitle = document.getElementById('toastTitle');
    var toastMessage = document.getElementById('toastMessage');
    if (!toast) return;
    var icons = { success: 'fa-check-circle', error: 'fa-exclamation-circle', warning: 'fa-exclamation-triangle', info: 'fa-info-circle' };
    var colors = { success: '#059669', error: '#DC2626', warning: '#D97706', info: '#0B5ED7' };
    toast.className = 'toast-custom ' + (type || 'info');
    toast.style.background = colors[type] || colors.info;
    var icon = toast.querySelector('.toast-icon i');
    if (icon) icon.className = 'fas ' + (icons[type] || icons.info);
    if (toastTitle) toastTitle.textContent = title || 'Notification';
    if (toastMessage) toastMessage.innerHTML = message || '';
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

function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function toggleMedManual(field) {
    var selectEl, manualEl, btn;
    if (field === 'dosage') { selectEl = document.getElementById('medDosage'); manualEl = document.getElementById('medDosageManual'); btn = document.getElementById('btnManualDosage'); }
    else if (field === 'frequency') { selectEl = document.getElementById('medFrequency'); manualEl = document.getElementById('medFrequencyManual'); btn = document.getElementById('btnManualFrequency'); }
    else if (field === 'route') { selectEl = document.getElementById('medRoute'); manualEl = document.getElementById('medRouteManual'); btn = document.getElementById('btnManualRoute'); }
    else return;
    var isManual = manualModes[field];
    if (isManual) {
        selectEl.style.display = 'block';
        manualEl.classList.remove('show');
        btn.classList.remove('active');
        btn.innerHTML = '<i class="fas fa-keyboard"></i>';
        manualModes[field] = false;
        var manualValue = manualEl.value.trim();
        if (manualValue) {
            var found = false;
            for (var i = 0; i < selectEl.options.length; i++) {
                if (selectEl.options[i].value === manualValue) { selectEl.selectedIndex = i; found = true; break; }
            }
            if (!found) {
                selectEl.style.display = 'none';
                manualEl.classList.add('show');
                btn.classList.add('active');
                btn.innerHTML = '<i class="fas fa-list"></i>';
                manualModes[field] = true;
                return;
            }
        }
        manualEl.value = '';
    } else {
        selectEl.style.display = 'none';
        manualEl.classList.add('show');
        btn.classList.add('active');
        btn.innerHTML = '<i class="fas fa-list"></i>';
        manualModes[field] = true;
        if (selectEl.value) manualEl.value = selectEl.value;
        manualEl.focus();
    }
}

function getMedFieldValue(field) {
    if (manualModes[field]) {
        var manualEl = document.getElementById('med' + field.charAt(0).toUpperCase() + field.slice(1) + 'Manual');
        return manualEl ? manualEl.value.trim() : '';
    } else {
        var selectEl = document.getElementById('med' + field.charAt(0).toUpperCase() + field.slice(1));
        return selectEl ? selectEl.value : '';
    }
}

function resetMedicationForm() {
    document.getElementById('medQuantity').value = '1';
    document.getElementById('medDuration').value = '7';
    document.getElementById('medInstructions').value = '';
    document.querySelectorAll('.quick-pick-btn.selected').forEach(function(b) { b.classList.remove('selected'); });
    ['dosage', 'frequency', 'route'].forEach(function(field) {
        var selectId = 'med' + field.charAt(0).toUpperCase() + field.slice(1);
        var manualId = selectId + 'Manual';
        var btnId = 'btnManual' + field.charAt(0).toUpperCase() + field.slice(1);
        var selectEl = document.getElementById(selectId);
        var manualEl = document.getElementById(manualId);
        var btn = document.getElementById(btnId);
        if (selectEl) { selectEl.value = ''; selectEl.style.display = 'block'; }
        if (manualEl) { manualEl.value = ''; manualEl.classList.remove('show'); }
        if (btn) { btn.classList.remove('active'); btn.innerHTML = '<i class="fas fa-keyboard"></i>'; }
        manualModes[field] = false;
    });
}

function toggleDropdownSection(bodyId) {
    var body = document.getElementById(bodyId);
    if (!body) return;
    var header = body.previousElementSibling;
    body.classList.toggle('open');
    if (header) header.classList.toggle('active');
}

function addComplaintOnSelect() {
    var select = document.getElementById('complaintSelect');
    var value = select.value;
    if (!value) return;
    if (!complaintsList.includes(value)) {
        complaintsList.push(value);
        updateComplaintsUI();
        updateComplaintsTextarea();
        select.value = '';
        debouncedAutoSave();
    }
}

function updateComplaints() {
    var textarea = document.getElementById('symptomsTextarea');
    if (textarea) complaintsList = textarea.value.split(',').map(s => s.trim()).filter(s => s.length > 0);
    updateComplaintsUI();
}

function updateComplaintsUI() {
    var container = document.getElementById('complaintChips');
    if (!container) return;
    if (complaintsList.length === 0) {
        container.innerHTML = '<span style="font-size:0.7rem;color:var(--text-secondary);font-style:italic;">No complaints selected</span>';
        return;
    }
    container.innerHTML = complaintsList.map(function(c) {
        var escaped = c.replace(/'/g, "\\'");
        return '<span class="complaint-chip">' + escapeHtml(c) + '<span class="chip-remove" onclick="removeComplaintChip(this, \'' + escaped + '\')">×</span></span>';
    }).join('');
}

function updateComplaintsTextarea() {
    var textarea = document.getElementById('symptomsTextarea');
    if (textarea) textarea.value = complaintsList.join(', ');
}

function removeComplaintChip(el, complaint) {
    complaintsList = complaintsList.filter(function(c) { return c !== complaint; });
    updateComplaintsTextarea();
    updateComplaintsUI();
    debouncedAutoSave();
}

function filterLabTestsInside() {
    var filter = document.getElementById('labTestSearch').value.toLowerCase().trim();
    document.querySelectorAll('#labTestsGrid .item-card').forEach(function(item) {
        var name = (item.getAttribute('data-test-name') || '').toLowerCase();
        item.style.display = (!filter || name.includes(filter)) ? 'flex' : 'none';
    });
}

function filterDiseasesInside() {
    var filter = document.getElementById('diseaseSearch').value.toLowerCase().trim();
    document.querySelectorAll('#diseaseCheckboxContainer .disease-checkbox-item').forEach(function(item) {
        var name = item.getAttribute('data-disease-name') || '';
        item.style.display = (!filter || name.includes(filter)) ? 'flex' : 'none';
    });
}

function filterMedicationsInside() {
    var filter = document.getElementById('medicationSearch').value.toLowerCase().trim();
    document.querySelectorAll('#medicationsGrid .item-card').forEach(function(item) {
        var name = (item.getAttribute('data-medication-name') || '').toLowerCase();
        item.style.display = (!filter || name.includes(filter)) ? 'flex' : 'none';
    });
}

function filterProceduresInside() {
    var filter = document.getElementById('procedureSearch').value.toLowerCase().trim();
    document.querySelectorAll('#proceduresGrid .item-card').forEach(function(item) {
        var name = (item.getAttribute('data-procedure-name') || '').toLowerCase();
        item.style.display = (!filter || name.includes(filter)) ? 'flex' : 'none';
    });
}

function filterEquipmentInside() {
    var filter = document.getElementById('equipmentSearch').value.toLowerCase().trim();
    document.querySelectorAll('#equipmentGrid .item-card').forEach(function(item) {
        var name = (item.getAttribute('data-equipment-name') || '').toLowerCase();
        item.style.display = (!filter || name.includes(filter)) ? 'flex' : 'none';
    });
}

function toggleLabTestCheckbox(element) {
    if (element.classList.contains('disabled')) { showToast('⚠️ Cannot Select', 'This test requires equipment that is not available', 'warning'); return; }
    element.classList.toggle('selected');
    var testId = element.getAttribute('data-test-id');
    var idx = selectedLabTests.indexOf(testId);
    if (idx > -1) selectedLabTests.splice(idx, 1);
    else selectedLabTests.push(testId);
    document.getElementById('labSelectedCount').textContent = selectedLabTests.length;
}

function addSelectedLabTests() {
    if (selectedLabTests.length === 0) { showToast('⚠️ Warning', 'Please select at least one lab test', 'warning'); return; }
    var added = 0, completed = 0, total = selectedLabTests.length;
    selectedLabTests.forEach(function(testId) {
        var selectedEl = document.querySelector('#labTestsGrid .item-card[data-test-id="' + testId + '"]');
        if (!selectedEl) { completed++; return; }
        var formData = new FormData();
        formData.append('action', 'add_lab_test_cart');
        formData.append('test_id', testId);
        fetch(window.location.href, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) { added++; updateLabCartUI(data); selectedEl.classList.remove('selected'); }
            completed++;
            if (completed === total) {
                if (added > 0) showToast('✅ Success', 'Added ' + added + ' test(s)', 'success');
                selectedLabTests = [];
                document.getElementById('labSelectedCount').textContent = '0';
            }
        });
    });
}

function updateLabCartUI(data) {
    var container = document.getElementById('labCartItems');
    if (!container) return;
    if (data.cart_items && data.cart_items.length > 0) {
        var html = '';
        data.cart_items.forEach(function(item) {
            html += '<div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border-bottom:1px solid var(--border-color);border-radius:8px;" id="lab-cart-' + item.id + '" data-test-id="' + item.id + '"><span style="font-weight:600;font-size:0.85rem;">' + escapeHtml(item.name) + '</span><span style="font-size:0.8rem;color:var(--success);font-weight:700;font-family:\'JetBrains Mono\',monospace;">TSh ' + Number(item.price).toLocaleString() + '</span><button type="button" class="btn-remove" style="width:26px;height:26px;font-size:0.7rem;" onclick="removeLabTestFromCart(' + item.id + ')"><i class="fas fa-times"></i></button></div>';
        });
        container.innerHTML = html;
    } else {
        container.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-secondary);font-size:0.85rem;"><i class="fas fa-shopping-cart" style="font-size:1.5rem;display:block;margin-bottom:8px;opacity:0.3;"></i>No tests in cart</div>';
    }
    var sendBtn = document.querySelector('button[name="send_lab"]');
    if (sendBtn) { sendBtn.disabled = data.cart_count === 0; sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send All to Laboratory (' + (data.cart_count || 0) + ')'; }
    var el1 = document.getElementById('labCartCount'); if (el1) el1.textContent = '(' + (data.cart_count || 0) + ' items)';
    var el2 = document.getElementById('labCartTotal'); if (el2) el2.textContent = Number(data.cart_total || 0).toLocaleString();
    var el3 = document.getElementById('sendLabCount'); if (el3) el3.textContent = data.cart_count || 0;
}

function removeLabTestFromCart(testId) {
    var formData = new FormData();
    formData.append('action', 'remove_lab_test_cart');
    formData.append('test_id', testId);
    fetch(window.location.href, { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => { if (data.success) window.location.reload(); });
}

function clearLabCart() {
    if (!confirm('Clear all tests from cart?')) return;
    var promises = [];
    document.querySelectorAll('[id^="lab-cart-"]').forEach(function(item) {
        var testId = item.dataset.testId || item.id.replace('lab-cart-', '');
        var formData = new FormData();
        formData.append('action', 'remove_lab_test_cart');
        formData.append('test_id', testId);
        promises.push(fetch(window.location.href, { method: 'POST', body: formData }));
    });
    Promise.all(promises).then(() => window.location.reload());
}

function removeLabTest(testId) {
    if (!confirm('Remove this lab test?')) return;
    var formData = new FormData();
    formData.append('action', 'remove_lab_test');
    formData.append('test_id', testId);
    fetch(window.location.href, { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.success) { showToast('✅ Success', data.message, 'success'); setTimeout(() => window.location.reload(), 1500); }
        else showToast('❌ Error', data.message, 'error');
    });
}

function toggleMedicationCheckbox(element) {
    if (lockSections) { showToast('🔒 Locked', 'Sections are locked. Wait for lab results.', 'warning'); return; }
    var isSelectable = element.getAttribute('data-selectable') === '1';
    if (!isSelectable) { showToast('⚠️ Cannot Select', 'This medication is not available', 'warning'); return; }
    element.classList.toggle('selected');
    var medName = element.getAttribute('data-medication-name');
    var medCategory = element.getAttribute('data-medication-category');
    var totalStock = parseInt(element.getAttribute('data-total-stock')) || 0;
    var batchCount = parseInt(element.getAttribute('data-batch-count')) || 1;
    var idx = selectedMedications.findIndex(m => m.name === medName && m.category === medCategory);
    if (idx > -1) {
        selectedMedications.splice(idx, 1);
    } else {
        selectedMedications.push({ 
            name: medName, category: medCategory, total_stock: totalStock, batch_count: batchCount 
        });
    }
    updateMedSelectedInfo();
}

function updateMedSelectedInfo() {
    var info = document.getElementById('medSelectedInfo');
    if (!info) return;
    if (selectedMedications.length === 0) { info.textContent = 'None'; return; }
    info.textContent = selectedMedications.map(m => m.name + ' (' + m.total_stock + ' avail' + (m.batch_count > 1 ? ', ' + m.batch_count + ' batches' : '') + ')').join(', ');
}

function addMedicationAjax() {
    if (lockSections) { showToast('🔒 Locked', 'Sections are locked. Wait for lab results.', 'warning'); return; }
    if (selectedMedications.length === 0) { showToast('❌ Error', 'Please select medication(s)', 'error'); return; }
    var qty = parseInt(document.getElementById('medQuantity').value) || 0;
    var duration = document.getElementById('medDuration').value;
    var instructions = document.getElementById('medInstructions').value;
    var dosage = getMedFieldValue('dosage');
    var frequency = getMedFieldValue('frequency');
    var route = getMedFieldValue('route');
    if (qty < 1) { showToast('❌ Error', 'Quantity must be at least 1', 'error'); return; }
    if (!frequency) { showToast('❌ Error', 'Please enter frequency', 'error'); return; }
    if (!route) { showToast('❌ Error', 'Please enter route', 'error'); return; }
    
    for (var i = 0; i < selectedMedications.length; i++) {
        var m = selectedMedications[i];
        if (m.total_stock < qty) {
            showToast('❌ Insufficient Stock', m.name + ': Requested ' + qty + ', Available ' + m.total_stock, 'error');
            return;
        }
    }
    
    var btn = document.getElementById('addMedicationBtn');
    var originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
    var added = 0, completed = 0, total = selectedMedications.length;
    var messages = [];
    
    selectedMedications.forEach(function(med) {
        var formData = collectFormData();
        formData.append('action', 'add_medication');
        formData.append('medication_name', med.name);
        formData.append('medication_category', med.category || '');
        formData.append('quantity', qty);
        formData.append('dosage', dosage);
        formData.append('frequency', frequency);
        formData.append('duration', duration);
        formData.append('route', route);
        formData.append('instructions', instructions);
        
        fetch(window.location.href, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) { 
                added++; 
                messages.push(data.message);
                if (data.medication) addMedicationToList(data.medication); 
            } else { 
                showToast('❌ Error', data.message || 'Unknown error', 'error'); 
                console.error('add_medication failed:', data);
            }
            completed++;
            if (completed === total) {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
                document.querySelectorAll('#medicationsGrid .item-card').forEach(el => el.classList.remove('selected'));
                selectedMedications = [];
                updateMedSelectedInfo();
                if (added > 0) { 
                    showToast('✅ Success', messages.join(' | '), 'success'); 
                    showSavedIndicator('✅ Medication added + Diagnosis saved');
                    setTimeout(function() { window.location.reload(); }, 2000);
                }
                resetMedicationForm();
            }
        });
    });
}

function addMedicationToList(med) {
    if (!med) return;
    var list = document.getElementById('medicationsList');
    if (!list) return;
    var empty = document.getElementById('emptyMedications');
    if (empty) empty.remove();
    
    var div = document.createElement('div');
    div.className = 'medication-item medication-prescription';
    div.id = 'med-item-' + med.id;
    
    var instructions = med.instructions ? '<span class="med-instruction-tag">' + escapeHtml(med.instructions) + '</span>' : '';
    var batchesInfo = '';
    if (med.batches_used && med.batches_used.length > 1) {
        var batchRows = med.batches_used.map(function(b) {
            return '<div class="batch-info-row"><span class="batch-num"><i class="fas fa-box"></i> Batch: ' + escapeHtml(b.batch_number || 'N/A') + '</span><span class="batch-qty">× ' + b.quantity + '</span></div>';
        }).join('');
        batchesInfo = '<div class="batch-info-list"><div class="batch-info-header"><i class="fas fa-layer-group"></i> Batches Used: ' + med.batches_used.length + '</div>' + batchRows + '</div>';
    }
    
    div.innerHTML = 
        '<div class="medication-item-info">' +
            '<span class="med-name">' + escapeHtml(med.name) + '</span>' +
            '<span class="med-details">' + 
                escapeHtml(med.dosage || '') + ' • ' + 
                escapeHtml(med.frequency || '') + ' • ' + 
                escapeHtml(med.duration || '') + ' days' +
            '</span>' +
            '<span class="med-qty">x' + (med.quantity || 0) + '</span>' +
            '<span class="med-price">TSh ' + Number(med.total_price || 0).toLocaleString() + '</span>' +
            instructions +
            '<span class="med-status-pending"><i class="fas fa-clock"></i> Pending</span>' +
            batchesInfo +
        '</div>' +
        '<button type="button" class="btn-remove" onclick="removeMedication(' + med.id + ')" title="Remove">' +
            '<i class="fas fa-times"></i>' +
        '</button>';
    
    list.appendChild(div);
}

function removeMedication(prescriptionId) {
    if (!confirm('Remove this medication?')) return;
    var formData = new FormData();
    formData.append('action', 'remove_medication');
    formData.append('prescription_id', prescriptionId);
    fetch(window.location.href, { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.success) { showToast('✅ Success', data.message, 'success'); setTimeout(function() { window.location.reload(); }, 2000); }
        else showToast('❌ Error', data.message, 'error');
    });
}

function addManualDisease() {
    if (lockSections) { showToast('🔒 Locked', 'Sections are locked.', 'warning'); return; }
    var input = document.getElementById('manualDiseaseInput');
    var codeInput = document.getElementById('manualDiseaseCodeInput');
    var container = document.getElementById('manualDiseasesContainer');
    var name = input.value.trim();
    if (!name) { showToast('⚠️ Warning', 'Please enter disease name', 'warning'); return; }
    var customCode = codeInput ? codeInput.value.trim() : '';
    var tag = document.createElement('span');
    tag.style.cssText = 'display:inline-flex;align-items:center;gap:8px;padding:6px 14px;background:var(--primary-bg);border:1px solid var(--primary);border-radius:20px;font-size:0.8rem;color:var(--text-primary);font-weight:500;';
    tag.setAttribute('data-disease', name);
    tag.innerHTML = '<i class="fas fa-user-md" style="color:var(--primary);font-size:0.7rem;"></i> ' + escapeHtml(name) +
                    '<button type="button" style="background:none;border:none;color:var(--danger);cursor:pointer;font-size:1rem;padding:0 2px;" onclick="removeManualDisease(this)">×</button>' +
                    '<input type="hidden" name="manual_diseases[]" value="' + escapeHtml(name) + '">' +
                    (customCode ? '<input type="hidden" name="manual_disease_codes[]" value="' + escapeHtml(customCode) + '">' : '');
    container.appendChild(tag);
    input.value = '';
    if (codeInput) codeInput.value = '';
    saveDiagnosisImmediately();
}

function removeManualDisease(btn) {
    btn.parentElement.remove();
    saveDiagnosisImmediately();
}

function toggleProcedure(element) {
    if (lockSections) { showToast('🔒 Locked', 'Sections are locked.', 'warning'); return; }
    element.classList.toggle('selected');
    selectedProcedures = [];
    document.querySelectorAll('.procedure-card.selected').forEach(function(item) { selectedProcedures.push({ id: item.dataset.procedureId }); });
    document.getElementById('procSelectedCount').textContent = selectedProcedures.length;
}

function addSelectedProcedures() {
    if (lockSections) { showToast('🔒 Locked', 'Sections are locked. Wait for lab results.', 'warning'); return; }
    if (selectedProcedures.length === 0) { showToast('⚠️ Warning', 'Please select procedures', 'warning'); return; }
    var procedureIds = selectedProcedures.map(p => p.id);
    var formData = collectFormData();
    formData.append('action', 'add_procedures_batch');
    formData.append('procedure_ids', JSON.stringify(procedureIds));
    fetch(window.location.href, { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.success) { 
            showToast('✅ Success', data.message, 'success'); 
            showSavedIndicator('✅ Procedures added + Diagnosis saved');
            setTimeout(() => window.location.reload(), 1200); 
        } else {
            showToast('❌ Error', data.message || 'Unknown error', 'error');
            console.error('add_procedures_batch failed:', data);
        }
    })
    .catch(err => {
        showToast('❌ Network Error', err.message, 'error');
        console.error('Fetch error:', err);
    });
}

function clearProcedureSelections() {
    document.querySelectorAll('.procedure-card.selected').forEach(el => el.classList.remove('selected'));
    selectedProcedures = [];
    document.getElementById('procSelectedCount').textContent = '0';
}

function toggleEquipment(element) {
    if (lockSections) { showToast('🔒 Locked', 'Sections are locked.', 'warning'); return; }
    if (element.classList.contains('disabled')) { showToast('⚠️ Cannot Select', 'Equipment not available', 'warning'); return; }
    element.classList.toggle('selected');
    selectedEquipment = [];
    document.querySelectorAll('.equipment-card.selected').forEach(function(item) { selectedEquipment.push({ id: item.dataset.equipmentId, name: item.dataset.equipmentName }); });
    document.getElementById('equipSelectedCount').textContent = selectedEquipment.length;
}

function addSelectedEquipment() {
    if (lockSections) { showToast('🔒 Locked', 'Sections are locked. Wait for lab results.', 'warning'); return; }
    if (selectedEquipment.length === 0) { showToast('⚠️ Warning', 'Please select equipment', 'warning'); return; }
    var quantity = parseInt(document.getElementById('equipmentQuantity').value) || 1;
    var equipmentData = selectedEquipment.map(eq => ({ id: eq.id, quantity: quantity }));
    var formData = collectFormData();
    formData.append('action', 'add_equipment_batch');
    formData.append('equipment_data', JSON.stringify(equipmentData));
    fetch(window.location.href, { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.success) { 
            showToast('✅ Success', data.message, 'success'); 
            showSavedIndicator('✅ Equipment added + Diagnosis saved');
            setTimeout(() => window.location.reload(), 1200); 
        } else {
            showToast('❌ Error', data.message || 'Unknown error', 'error');
            console.error('add_equipment_batch failed:', data);
        }
    })
    .catch(err => {
        showToast('❌ Network Error', err.message, 'error');
        console.error('Fetch error:', err);
    });
}

function clearEquipmentSelections() {
    document.querySelectorAll('.equipment-card.selected').forEach(el => el.classList.remove('selected'));
    selectedEquipment = [];
    document.getElementById('equipSelectedCount').textContent = '0';
}

function removeAddedItem(type, id) {
    if (!confirm('Remove this ' + type + '?')) return;
    var formData = new FormData();
    formData.append('action', 'remove_added_item');
    formData.append('type', type);
    formData.append('id', id);
    formData.append('visit_id', visitId);
    fetch(window.location.href, { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.success) { showToast('✅ Success', data.message, 'success'); setTimeout(function() { window.location.reload(); }, 2000); }
        else showToast('❌ Error', data.message, 'error');
    });
}

document.addEventListener('DOMContentLoaded', function() {
    updateComplaintsUI();
});

console.log('%c🩺 Braick Consultation V32 FINAL', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
console.log('%c✅ V32: MULTI-BATCH STOCK DISTRIBUTION (FIFO)', 'font-size:12px; color:#059669; font-weight:bold;');
console.log('%c✅ V32: Inachukua batches zote ikiisha ya kwanza', 'font-size:12px; color:#1E40AF; font-weight:bold;');
console.log('%c✅ V32: Bill items kwa kila batch', 'font-size:12px; color:#7C3AED;');
console.log('%c✅ V32: Display batches zilizotumika', 'font-size:12px; color:#D97706;');
</script>

</body>
</html>