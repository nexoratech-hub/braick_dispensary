<?php
// ================================================================
// FILE: frontend/pages/doctor/consultation.php
// COMPLETE CONSULTATION V4 - FIXED EQUIPMENT STOCK MOVEMENTS
// ================================================================
// ✅ V4 FIXED: add_equipment_batch inaingiza stock_movements
// ✅ V4 FIXED: remove_added_item (equipment) inarudisha + stock_movement
// ✅ V4 FIXED: remove_medication inaingiza stock_movement ya returned
// ✅ V4 FIXED: Notes zina include jina la doctor
// ✅ V3 FIXED: INNER JOIN badala ya LEFT JOIN (haionyeshi "Unknown")
// ✅ V3 FIXED: cleanupEmptyPrescriptions() function
// ✅ V3 FIXED: Filter empty rows kwenye loop na HTML
// ✅ V3 FIXED: Skip prescriptions zisizo na items
// WITH 7 VITAL SIGNS (INCLUDING OXYGEN SATURATION - SpO2)
// ✅ FIXED: Manual disease code inatumika kama user ameijaza
// ✅ FIXED: Auto-generate code kama user hajajaza
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
// ✅ V3 NEW: CLEANUP EMPTY PRESCRIPTIONS FUNCTION
// ================================================================
function cleanupEmptyPrescriptions($db, $visit_id, $branch_id) {
    try {
        $stmt = $db->prepare("
            SELECT p.id, p.prescription_number, p.status
            FROM prescriptions p
            LEFT JOIN prescription_items pi ON p.id = pi.prescription_id
            WHERE p.visit_id = ?
              AND p.branch_id = ?
              AND pi.id IS NULL
        ");
        $stmt->execute([$visit_id, $branch_id]);
        $empty_rx = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($empty_rx)) return 0;
        
        $empty_ids = array_column($empty_rx, 'id');
        $placeholders = implode(',', array_fill(0, count($empty_ids), '?'));
        
        $stmt_del = $db->prepare("DELETE FROM prescriptions WHERE id IN ($placeholders)");
        $stmt_del->execute($empty_ids);
        
        error_log("✅ Cleanup: Deleted " . count($empty_ids) . " empty prescriptions for visit #$visit_id");
        return count($empty_ids);
        
    } catch (Exception $e) {
        error_log("❌ Cleanup error: " . $e->getMessage());
        return 0;
    }
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

// ✅ V3: Cleanup empty prescriptions
cleanupEmptyPrescriptions($db, $visit_id, $doctor_branch_id);

// Get current visit status
$is_completed = ($visit['status'] === 'completed');
$is_waiting = ($visit['status'] === 'waiting');
$is_prescribed = ($visit['status'] === 'prescribed');
$is_lab_test = ($visit['status'] === 'lab_test');
$visit_status = $visit['status'] ?? 'assigned';

// ================================================================
// GET OR CREATE BILL - WITH PREMIUM
// ================================================================
$bill_id = null;
$bill_status = 'pending';
$bill_total = 0;
$bill_paid = 0;
$bill_balance = 0;
$bill_subtotal = 0;
$bill_discount = 0;
$bill_total_discount = 0;
$bill_premium = 0;

try {
    $stmt = $db->prepare("
        SELECT 
            id, status, total_amount, paid_amount, balance, subtotal, 
            discount_amount, pharmacy_discount, cashier_discount, 
            total_discount, premium_amount
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
        $bill_discount = $bill['pharmacy_discount'] ?? $bill['discount_amount'] ?? 0;
        $bill_total_discount = $bill['total_discount'] ?? 0;
        $bill_premium = $bill['premium_amount'] ?? 0;
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
// BILL TOTAL FUNCTION
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
        SELECT 
            discount_amount, 
            pharmacy_discount, 
            cashier_discount, 
            total_discount,
            premium_amount
        FROM bills WHERE id = ?
    ");
    $stmt->execute([$bill_id]);
    $bill_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $pharmacy_discount = (float)($bill_data['pharmacy_discount'] ?? 0);
    if ($pharmacy_discount == 0) {
        $pharmacy_discount = (float)($bill_data['discount_amount'] ?? 0);
    }
    $cashier_discount = (float)($bill_data['cashier_discount'] ?? 0);
    $total_discount = $pharmacy_discount + $cashier_discount;
    $discount_amount = $pharmacy_discount;
    $premium_amount = (float)($bill_data['premium_amount'] ?? 0);
    
    if ($total_discount > $subtotal) {
        $total_discount = $subtotal;
    }
    
    $total_amount = $subtotal + $premium_amount - $total_discount;
    if ($total_amount < 0) $total_amount = 0;
    
    $stmt = $db->prepare("
        SELECT SUM(amount) as payment_total 
        FROM payments 
        WHERE bill_id = ? 
    ");
    $stmt->execute([$bill_id]);
    $paid_amount = (float)($stmt->fetch(PDO::FETCH_ASSOC)['payment_total'] ?? 0);
    
    $balance = $total_amount - $paid_amount;
    if ($balance < 0) {
        $balance = 0;
        if ($paid_amount > $total_amount) {
            $paid_amount = $total_amount;
        }
    }
    
    if ($total_amount <= 0) {
        $status = 'paid';
    } elseif ($balance <= 0.01) {
        $status = 'paid';
    } elseif ($paid_amount > 0 && $balance > 0) {
        $status = 'partial';
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
            pharmacy_discount = ?,
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
        $pharmacy_discount,
        $total_discount,
        $bill_id
    ]);
    
    return [
        'subtotal' => $subtotal,
        'total' => $total_amount, 
        'discount' => $total_discount,
        'discount_amount' => $discount_amount,
        'pharmacy_discount' => $pharmacy_discount,
        'cashier_discount' => $cashier_discount,
        'premium' => $premium_amount,
        'paid' => $paid_amount, 
        'balance' => $balance, 
        'status' => $status,
        'amount_after_discount' => $total_amount
    ];
}

function getBillDiscount($db, $bill_id) {
    $stmt = $db->prepare("
        SELECT 
            discount_amount, 
            pharmacy_discount, 
            cashier_discount, 
            total_discount,
            premium_amount
        FROM bills WHERE id = ?
    ");
    $stmt->execute([$bill_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (($data['pharmacy_discount'] ?? 0) == 0 && ($data['discount_amount'] ?? 0) > 0) {
        $data['pharmacy_discount'] = $data['discount_amount'];
    }
    
    if (($data['total_discount'] ?? 0) == 0) {
        $data['total_discount'] = (float)($data['pharmacy_discount'] ?? 0) + (float)($data['cashier_discount'] ?? 0);
    }
    
    return $data;
}

// ================================================================
// AUTO-COMPLETE
// ================================================================
function checkAndAutoCompleteVisit($db, $visit_id, $bill_id) {
    $stmt = $db->prepare("SELECT status FROM visits WHERE id = ?");
    $stmt->execute([$visit_id]);
    $visit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$visit || $visit['status'] !== 'waiting') {
        return false;
    }
    
    $stmt = $db->prepare("SELECT diagnosis FROM visits WHERE id = ?");
    $stmt->execute([$visit_id]);
    $diagnosis = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$diagnosis || empty($diagnosis['diagnosis'])) {
        return false;
    }
    
    $stmt = $db->prepare("SELECT balance FROM bills WHERE id = ?");
    $stmt->execute([$bill_id]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$bill || $bill['balance'] > 0) {
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
// CHECK LAB RESULTS
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
                error_log("✅ Visit #$visit_id updated to 'prescribed'");
                return true;
            }
        }
    }
    return false;
}

// ================================================================
// SAVE DIAGNOSIS
// ================================================================
function saveDiagnosisToDatabase($db, $visit_id, $doctor_id, $doctor_branch_id, $data) {
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
        if ($disease_id > 0 && !in_array($disease_id, $clean_selected)) {
            $clean_selected[] = $disease_id;
        }
    }
    
    $clean_manual = [];
    foreach ($manual_diseases as $manual) {
        $manual = trim($manual);
        $manual = str_replace(['[', ']', '"', '\\', "'"], '', $manual);
        $manual = trim($manual);
        if (!empty($manual) && !in_array($manual, $clean_manual)) {
            $clean_manual[] = $manual;
        }
    }
    
    $clean_manual_codes = [];
    foreach ($manual_disease_codes as $code) {
        $code = trim($code);
        $code = str_replace(['[', ']', '"', '\\', "'"], '', $code);
        $code = trim($code);
        $clean_manual_codes[] = $code;
    }
    
    $saved_diseases = [];
    $disease_names = [];
    $disease_codes = [];
    
    foreach ($clean_selected as $disease_id) {
        if ($disease_id > 0) {
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
                    $stmt = $db->prepare("UPDATE diseases SET treatment = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$treatment, $disease_id]);
                }
            }
        }
    }
    
    foreach ($clean_manual as $index => $manual) {
        $manual = trim($manual);
        if (empty($manual)) continue;
        if (in_array($manual, $disease_names)) continue;
        
        $user_code = '';
        if (isset($clean_manual_codes[$index])) {
            $user_code = $clean_manual_codes[$index];
        }
        
        $stmt = $db->prepare("SELECT id, disease_name, disease_code FROM diseases WHERE disease_name = ? AND branch_id = ?");
        $stmt->execute([$manual, $doctor_branch_id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            if (!in_array($existing['disease_name'], $disease_names)) {
                $saved_diseases[] = $existing['id'];
                $disease_names[] = $existing['disease_name'];
                
                $final_code = !empty($user_code) ? $user_code : ($existing['disease_code'] ?? '');
                $disease_codes[] = $final_code;
                
                if (!empty($user_code) && $user_code !== $existing['disease_code']) {
                    $stmt_update = $db->prepare("UPDATE diseases SET disease_code = ?, updated_at = NOW() WHERE id = ?");
                    $stmt_update->execute([$user_code, $existing['id']]);
                }
            }
            
            if (!empty($treatment)) {
                $stmt = $db->prepare("UPDATE diseases SET treatment = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$treatment, $existing['id']]);
            }
        } else {
            if (!empty($user_code)) {
                $disease_code = $user_code;
                
                $stmt_check = $db->prepare("SELECT COUNT(*) FROM diseases WHERE disease_code = ? AND branch_id = ?");
                $stmt_check->execute([$disease_code, $doctor_branch_id]);
                if ($stmt_check->fetchColumn() > 0) {
                    $disease_code = $user_code . '-' . rand(10, 99);
                }
            } else {
                $clean_code_name = preg_replace('/[^a-zA-Z0-9]/', '', $manual);
                $clean_code_prefix = strtoupper(substr($clean_code_name, 0, 6));
                if (empty($clean_code_prefix)) $clean_code_prefix = 'DISEASE';
                
                $attempts = 0;
                do {
                    $disease_code = 'D-' . $clean_code_prefix . '-' . rand(100, 999);
                    $stmt_check = $db->prepare("SELECT COUNT(*) FROM diseases WHERE disease_code = ?");
                    $stmt_check->execute([$disease_code]);
                    $exists = $stmt_check->fetchColumn();
                    $attempts++;
                } while ($exists > 0 && $attempts < 10);
            }
            
            $stmt = $db->prepare("
                INSERT INTO diseases (disease_name, disease_code, branch_id, treatment, is_active, created_at)
                VALUES (?, ?, ?, ?, 1, NOW())
            ");
            $stmt->execute([$manual, $disease_code, $doctor_branch_id, $treatment]);
            $new_id = $db->lastInsertId();
            $saved_diseases[] = $new_id;
            $disease_names[] = $manual;
            $disease_codes[] = $disease_code;
        }
    }
    
    $diagnosis_string = implode(', ', $disease_names);
    $disease_code_string = implode(', ', $disease_codes);
    $disease_ids_string = implode(',', $saved_diseases);
    
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
        'disease_ids' => $saved_diseases,
        'disease_names' => $disease_names,
        'disease_codes' => $disease_codes,
        'treatment' => $treatment,
        'diagnosis_string' => $diagnosis_string
    ];
}

// ================================================================
// ✅ V4 NEW: HELPER FUNCTION - Log stock movement
// ================================================================
function logStockMovement($db, $item_type, $item_id, $patient_id, $movement_type, $quantity, $previous_stock, $new_stock, $reference_type, $reference_id, $performed_by, $branch_id, $notes) {
    try {
        if ($item_type === 'medicine') {
            $sql = "INSERT INTO stock_movements 
                    (inventory_id, patient_id, movement_type, quantity, previous_stock, new_stock, 
                     reference_type, reference_id, performed_by, branch_id, notes, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        } else {
            $sql = "INSERT INTO stock_movements 
                    (equipment_id, patient_id, movement_type, quantity, previous_stock, new_stock, 
                     reference_type, reference_id, performed_by, branch_id, notes, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $item_id, $patient_id, $movement_type, $quantity, 
            $previous_stock, $new_stock, 
            $reference_type, $reference_id, $performed_by, $branch_id, $notes
        ]);
        
        return $db->lastInsertId();
    } catch (Exception $e) {
        error_log("logStockMovement error: " . $e->getMessage());
        return false;
    }
}

// ================================================================
// GET DATA
// ================================================================
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
} catch (Exception $e) { $diseases_list = []; }

$lab_tests_catalog = [];
try {
    $stmt = $db->prepare("
        SELECT 
            lc.id, lc.test_name, lc.test_code, lc.category, lc.price,
            lc.description, lc.reference_range, lc.equipment_quantity_used,
            lc.is_active, lc.branch_id, lc.created_by, lc.created_at,
            le.equipment_id,
            me.equipment_name as linked_equipment_name,
            me.quantity as equipment_stock,
            me.batch_number as equipment_batch,
            me.expiry_date as equipment_expiry
        FROM lab_tests_catalog lc
        LEFT JOIN lab_test_equipment le ON lc.id = le.lab_test_id AND le.branch_id = lc.branch_id
        LEFT JOIN medical_equipment me ON le.equipment_id = me.id AND me.branch_id = lc.branch_id
        WHERE lc.is_active = 1 
        AND (lc.branch_id = ? OR lc.branch_id IS NULL)
        GROUP BY lc.id
        ORDER BY lc.category, lc.test_name
    ");
    $stmt->execute([$doctor_branch_id]);
    $lab_tests_catalog = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $lab_tests_catalog = []; }

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
} catch (Exception $e) { $medications_grouped = []; }

$procedures_list = [];
try {
    $stmt = $db->prepare("
        SELECT pc.id, pc.procedure_name, pc.procedure_code, pc.category, pc.price, pc.description
        FROM procedures_catalog pc
        WHERE pc.is_active = 1 
        AND (pc.branch_id IS NULL OR pc.branch_id = ?)
        ORDER BY pc.procedure_name
    ");
    $stmt->execute([$doctor_branch_id]);
    $procedures_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $procedures_list = []; }

$equipment_list = [];
try {
    $stmt = $db->prepare("
        SELECT id, equipment_name, category, quantity, unit, selling_price,
               batch_number, expiry_date, status
        FROM medical_equipment 
        WHERE status = 'active' 
        AND quantity > 0
        AND branch_id = ?
        ORDER BY equipment_name
    ");
    $stmt->execute([$doctor_branch_id]);
    $equipment_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $equipment_list = []; }

// ================================================================
// GET VITAL SIGNS
// ================================================================
$vital_signs = null;
if ($visit_id > 0) {
    $stmt = $db->prepare("
        SELECT temperature, blood_pressure_systolic, blood_pressure_diastolic,
               pulse_rate, oxygen_saturation, weight, height, bmi, notes, recorded_at,
               u.full_name as recorded_by_name
        FROM vital_signs vs
        LEFT JOIN users u ON vs.recorded_by = u.id
        WHERE vs.visit_id = ? 
        ORDER BY vs.recorded_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$visit_id]);
    $vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$vital_signs) {
        $stmt = $db->prepare("
            SELECT temperature, blood_pressure_systolic, blood_pressure_diastolic,
                   pulse_rate, oxygen_saturation, weight, height, bmi, notes, recorded_at,
                   u.full_name as recorded_by_name
            FROM vital_signs vs
            LEFT JOIN users u ON vs.recorded_by = u.id
            WHERE vs.patient_id = ? 
            ORDER BY vs.recorded_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$patient_id]);
        $vital_signs = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

$lab_requests = [];
$lab_results = [];
$lab_results_available = false;
$has_active_lab = false;

try {
    $stmt = $db->prepare("
        SELECT lt.*, le.equipment_id, me.equipment_name as linked_equipment_name
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
        SELECT lt.*, le.equipment_id, me.equipment_name as linked_equipment_name
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

// ================================================================
// ✅ V3 FIXED: GET PRESCRIPTIONS - INNER JOIN
// ================================================================
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
        INNER JOIN prescription_items pi ON p.id = pi.prescription_id
        WHERE p.visit_id = ?
          AND pi.id IS NOT NULL
          AND pi.medication_name IS NOT NULL
          AND pi.medication_name != ''
        ORDER BY p.created_at DESC, pi.id ASC
    ");
    $stmt->execute([$visit_id]);
    $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($prescriptions as $presc) {
        if (empty($presc['medication_name']) || empty($presc['item_id'])) {
            continue;
        }
        $medications_total += $presc['total_price'] ?? 0;
    }
} catch (Exception $e) { 
    $prescriptions = []; 
    error_log("Prescriptions fetch error: " . $e->getMessage());
}

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
} catch (Exception $e) { $procedures = []; }

// ================================================================
// GET BILL ITEMS
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
$total_premium = 0;

try {
    $stmt = $db->prepare("
        SELECT id, item_name, item_type, quantity, unit_price, total_price, status 
        FROM bill_items 
        WHERE bill_id = ? AND status != 'cancelled'
        ORDER BY created_at DESC
    ");
    $stmt->execute([$bill_id]);
    $bill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($bill_items as $item) {
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
    $total_premium = (float)($discount_data['premium_amount'] ?? 0);
} catch (Exception $e) { 
    $bill_items = [];
    $total_discount = 0;
    $total_premium = 0;
}

$amount_after_discount = max(0, $total_bill_amount - $total_discount);

$bill_data = updateBillTotal($db, $bill_id);
$bill_total = $bill_data['total'];
$bill_paid = $bill_data['paid'];
$bill_balance = $bill_data['balance'];
$bill_status = $bill_data['status'];
$bill_subtotal = $bill_data['subtotal'];
$total_discount = $bill_data['discount'];
$total_premium = $bill_data['premium'];
$amount_after_discount = $bill_data['amount_after_discount'];

// Equipment items from bill
$equipment_items_display = [];
try {
    $stmt = $db->prepare("
        SELECT id, item_name, item_id, quantity, unit_price, total_price, status 
        FROM bill_items 
        WHERE bill_id = ? AND item_type = 'equipment' AND status != 'cancelled'
    ");
    $stmt->execute([$bill_id]);
    $equipment_items_display = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $equipment_items_display = []; }

// Branch info
$doctor_branch_name = 'Not Assigned';
try {
    $stmt = $db->prepare("SELECT name FROM branches WHERE id = ? AND status = 'active'");
    $stmt->execute([$doctor_branch_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) $doctor_branch_name = $branch_data['name'];
} catch (Exception $e) { $doctor_branch_name = 'Branch'; }

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
        'assigned' => 'badge-purple',
        'pending' => 'badge-warning',
        'with_doctor' => 'badge-info',
        'lab_test' => 'badge-warning',
        'in_progress' => 'badge-info',
        'prescribed' => 'badge-purple',
        'waiting' => 'badge-purple',
        'completed' => 'badge-success',
        'cancelled' => 'badge-danger'
    ];
    return $map[$status] ?? 'badge-purple';
}

function getSpO2Status($spo2) {
    if ($spo2 === null || $spo2 === '') return ['label' => 'N/A', 'color' => '#64748B', 'bg' => '#F1F5F9', 'class' => 'unknown'];
    $spo2 = (int)$spo2;
    if ($spo2 >= 95) return ['label' => 'NORMAL', 'color' => '#059669', 'bg' => '#D1FAE5', 'class' => 'normal'];
    if ($spo2 >= 90) return ['label' => 'LOW', 'color' => '#D97706', 'bg' => '#FEF3C7', 'class' => 'low'];
    return ['label' => 'CRITICAL', 'color' => '#DC2626', 'bg' => '#FEE2E2', 'class' => 'high'];
}

$selected_disease_ids = [];
if (!empty($visit['disease_id'])) {
    $selected_disease_ids = array_map('trim', explode(',', $visit['disease_id']));
}
$manual_diseases_saved = [];
if (!empty($visit['diagnosis'])) {
    $manual_diseases_saved = array_map('trim', explode(',', $visit['diagnosis']));
}
$saved_codes_array = [];
if (!empty($visit['disease_code'])) {
    $saved_codes_array = array_map('trim', explode(',', $visit['disease_code']));
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
            $result = saveDiagnosisToDatabase($db, $visit_id_input, $doctor_id, $doctor_branch_id, $input);
            $response['success'] = true;
            $response['message'] = '✅ Data saved successfully';
            $response['data'] = [
                'diagnosis' => $result['diagnosis_string'],
                'disease_codes' => implode(', ', $result['disease_codes']),
                'treatment' => $result['treatment'],
                'disease_ids' => $result['disease_ids']
            ];
        } catch (Exception $e) {
            $response['message'] = '❌ Error: ' . $e->getMessage();
        }
        
        echo json_encode($response);
        exit;
    }
    
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
        $pharmacy_discount = (float)($discount_data['pharmacy_discount'] ?? 0);
        $cashier_discount = (float)($discount_data['cashier_discount'] ?? 0);
        
        $grand_total = $total_bill_amount + $premium_amount - $total_discount;
        if ($grand_total < 0) $grand_total = 0;
        
        $stmt = $db->prepare("SELECT SUM(amount) as payment_total FROM payments WHERE bill_id = ?");
        $stmt->execute([$bill_id]);
        $payment_total = (float)($stmt->fetch(PDO::FETCH_ASSOC)['payment_total'] ?? 0);
        
        $balance = $grand_total - $payment_total;
        if ($balance < 0) {
            $balance = 0;
            if ($payment_total > $grand_total) $payment_total = $grand_total;
        }
        
        $stmt = $db->prepare("SELECT status FROM bills WHERE id = ?");
        $stmt->execute([$bill_id]);
        $bill = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $auto_completed = false;
        if ($visit_status === 'waiting' && $balance <= 0) {
            $auto_completed = checkAndAutoCompleteVisit($db, $visit_id, $bill_id);
        }
        
        echo json_encode([
            'success' => true,
            'lab_total' => $lab_total,
            'medication_total' => $medication_total,
            'procedure_total' => $procedure_total_bill,
            'equipment_total' => $equipment_total,
            'subtotal' => $total_bill_amount,
            'discount' => $total_discount,
            'pharmacy_discount' => $pharmacy_discount,
            'cashier_discount' => $cashier_discount,
            'premium' => $premium_amount,
            'amount_after_discount' => $grand_total,
            'grand_total' => $grand_total,
            'paid_total' => $payment_total,
            'pending_total' => $balance,
            'bill_status' => $bill['status'] ?? 'pending',
            'bill_paid' => $payment_total,
            'bill_balance' => $balance,
            'bill_subtotal' => $total_bill_amount,
            'auto_completed' => $auto_completed,
            'timestamp' => date('H:i:s')
        ]);
        exit;
    }
    
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
            SELECT p.*, pi.id as item_id, pi.medication_name, pi.dosage, pi.frequency, 
                   pi.quantity, pi.duration, pi.route, pi.instructions,
                   pi.unit_price, pi.total_price, pi.dispensed_at, pi.dispensed_by, pi.inventory_id
            FROM prescriptions p
            INNER JOIN prescription_items pi ON p.id = pi.prescription_id
            WHERE p.visit_id = ?
              AND pi.id IS NOT NULL
              AND pi.medication_name IS NOT NULL
              AND pi.medication_name != ''
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([$visit_id]);
        $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($prescriptions as $presc) {
            if (empty($presc['medication_name']) || empty($presc['item_id'])) continue;
            $medications_total += $presc['total_price'] ?? 0;
        }
        
        $procedures = [];
        $procedure_total = 0;
        $stmt = $db->prepare("SELECT p.* FROM procedures p WHERE p.visit_id = ? AND p.status != 'cancelled' ORDER BY p.created_at DESC");
        $stmt->execute([$visit_id]);
        $procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($procedures as $proc) $procedure_total += $proc['procedure_price'] ?? 0;
        
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
            FROM bill_items WHERE bill_id = ? AND status != 'cancelled'
        ");
        $stmt->execute([$bill_id]);
        $bill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($bill_items as $item) {
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
        $pharmacy_discount = (float)($discount_data['pharmacy_discount'] ?? 0);
        $cashier_discount = (float)($discount_data['cashier_discount'] ?? 0);
        
        $grand_total = $total_bill_amount + $premium_amount - $total_discount;
        if ($grand_total < 0) $grand_total = 0;
        
        $stmt = $db->prepare("SELECT SUM(amount) as payment_total FROM payments WHERE bill_id = ?");
        $stmt->execute([$bill_id]);
        $payment_total = (float)($stmt->fetch(PDO::FETCH_ASSOC)['payment_total'] ?? 0);
        
        $balance = $grand_total - $payment_total;
        if ($balance < 0) {
            $balance = 0;
            if ($payment_total > $grand_total) $payment_total = $grand_total;
        }
        
        $stmt = $db->prepare("SELECT status FROM bills WHERE id = ?");
        $stmt->execute([$bill_id]);
        $bill = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $bill_data = [
            'subtotal' => $total_bill_amount,
            'discount' => $total_discount,
            'pharmacy_discount' => $pharmacy_discount,
            'cashier_discount' => $cashier_discount,
            'premium' => $premium_amount,
            'amount_after_discount' => $grand_total,
            'total' => $grand_total,
            'paid' => $payment_total,
            'pending' => $balance,
            'balance' => $balance,
            'status' => $bill['status'] ?? 'pending'
        ];
        
        $auto_completed = false;
        if ($visit_status === 'waiting' && $balance <= 0) {
            $auto_completed = checkAndAutoCompleteVisit($db, $visit_id, $bill_id);
        }
        
        $added_procedures = [];
        $stmt = $db->prepare("SELECT p.* FROM procedures p WHERE p.visit_id = ? AND p.status != 'cancelled' ORDER BY p.created_at DESC");
        $stmt->execute([$visit_id]);
        $added_procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $added_equipment = [];
        $stmt = $db->prepare("SELECT bi.* FROM bill_items bi WHERE bi.bill_id = ? AND bi.item_type = 'equipment' AND bi.status != 'cancelled'");
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
            'auto_completed' => $auto_completed,
            'timestamp' => date('H:i:s')
        ]);
        exit;
    }
    
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
            if ($stat === 'pending' || $stat === null) $pending = (int)$sc['count'];
            elseif ($stat === 'in_progress') $in_progress = (int)$sc['count'];
            elseif ($stat === 'completed') $completed = (int)$sc['count'];
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
            }
        } catch (Exception $e) {
            $response['message'] = '❌ Error: ' . $e->getMessage();
        }
        
        echo json_encode($response);
        exit;
    }
    
    if ($action === 'add_lab_test_cart') {
        header('Content-Type: application/json');
        $test_id = (int)($_POST['test_id'] ?? 0);
        $response = ['success' => false, 'message' => ''];
        
        if ($test_id > 0) {
            try {
                $stmt = $db->prepare("
                    SELECT lc.*, le.equipment_id, me.equipment_name as linked_equipment_name, me.quantity as equipment_stock
                    FROM lab_tests_catalog lc
                    LEFT JOIN lab_test_equipment le ON lc.id = le.lab_test_id
                    LEFT JOIN medical_equipment me ON le.equipment_id = me.id
                    WHERE lc.id = ? AND lc.is_active = 1
                    AND (lc.branch_id IS NULL OR lc.branch_id = ?)
                ");
                $stmt->execute([$test_id, $doctor_branch_id]);
                $test = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($test) {
                    if (!isset($_SESSION['lab_cart'])) $_SESSION['lab_cart'] = [];
                    
                    $exists = false;
                    foreach ($_SESSION['lab_cart'] as $item) {
                        if ($item['id'] == $test_id) { $exists = true; break; }
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
                            $response['message'] .= ' (Linked: ' . $test['linked_equipment_name'] . ')';
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
    
    if ($action === 'remove_lab_test') {
        header('Content-Type: application/json');
        $test_id = (int)($_POST['test_id'] ?? 0);
        $response = ['success' => false, 'message' => ''];
        
        if ($test_id > 0) {
            $stmt = $db->prepare("
                SELECT lt.id, lt.test_id, lt.test_price, lt.equipment_id
                FROM lab_tests lt
                WHERE lt.id = ? AND lt.visit_id = ? AND lt.status IN ('pending', 'in_progress')
            ");
            $stmt->execute([$test_id, $visit_id]);
            $test = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($test) {
                try {
                    $db->beginTransaction();
                    
                    // ✅ V4: Return equipment stock + log movement
                    if (!empty($test['equipment_id'])) {
                        $stmt_eq = $db->prepare("
                            SELECT id, equipment_name, quantity as stock
                            FROM medical_equipment 
                            WHERE id = ? AND branch_id = ?
                        ");
                        $stmt_eq->execute([$test['equipment_id'], $doctor_branch_id]);
                        $equip = $stmt_eq->fetch(PDO::FETCH_ASSOC);
                        
                        if ($equip) {
                            $qty_used = (int)($test['equipment_quantity_used'] ?? 1);
                            $previous_stock = (int)$equip['stock'];
                            $new_stock = $previous_stock + $qty_used;
                            
                            $stmt_upd = $db->prepare("UPDATE medical_equipment SET quantity = ? WHERE id = ?");
                            $stmt_upd->execute([$new_stock, $equip['id']]);
                            
                            // ✅ V4 FIX: Log stock movement (returned)
                            logStockMovement(
                                $db, 'equipment', $equip['id'], $patient_id, 'in', $qty_used,
                                $previous_stock, $new_stock, 'lab_test', $test_id,
                                $doctor_id, $doctor_branch_id,
                                "Stock returned - Removed lab test by Dr. {$doctor_name}: {$equip['equipment_name']}"
                            );
                        }
                    }
                    
                    $stmt = $db->prepare("DELETE FROM bill_items WHERE bill_id = ? AND item_type = 'lab_test' AND reference_id = ?");
                    $stmt->execute([$bill_id, $test_id]);
                    
                    $stmt = $db->prepare("DELETE FROM lab_tests WHERE id = ? AND visit_id = ?");
                    $stmt->execute([$test_id, $visit_id]);
                    
                    $db->commit();
                    $bill_data = updateBillTotal($db, $bill_id);
                    
                    $response['success'] = true;
                    $response['message'] = '✅ Lab test removed! Equipment stock returned.';
                    $response['bill_data'] = $bill_data;
                } catch (Exception $e) {
                    if ($db->inTransaction()) $db->rollBack();
                    $response['message'] = '❌ Error: ' . $e->getMessage();
                }
            } else {
                $response['message'] = '❌ Test not found or already processed';
            }
        } else {
            $response['message'] = '❌ Invalid test ID';
        }
        
        echo json_encode($response);
        exit;
    }
    
    if ($action === 'add_medication') {
        header('Content-Type: application/json');
        
        if ($sections_frozen && !$is_waiting) {
            echo json_encode(['success' => false, 'message' => '❌ Cannot add medications. Lab tests pending!']);
            exit;
        }
        
        $raw_diagnosis_ids = isset($_POST['diagnosis_ids']) ? $_POST['diagnosis_ids'] : [];
        $raw_manual_diseases = isset($_POST['manual_diseases']) ? $_POST['manual_diseases'] : [];
        $raw_manual_disease_codes = isset($_POST['manual_disease_codes']) ? $_POST['manual_disease_codes'] : [];
        
        $treatment = trim($_POST['treatment'] ?? '');
        $symptoms = trim($_POST['symptoms'] ?? '');
        $hpi = trim($_POST['hpi'] ?? '');
        $physical_exam = trim($_POST['physical_exam'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        
        $diagnosis_ids = [];
        if (is_string($raw_diagnosis_ids)) {
            $decoded = json_decode($raw_diagnosis_ids, true);
            if (is_array($decoded)) $diagnosis_ids = $decoded;
            else $diagnosis_ids = array_map('trim', explode(',', $raw_diagnosis_ids));
        } elseif (is_array($raw_diagnosis_ids)) {
            $diagnosis_ids = $raw_diagnosis_ids;
        }
        
        $manual_diseases = [];
        if (is_string($raw_manual_diseases)) {
            $decoded = json_decode($raw_manual_diseases, true);
            if (is_array($decoded)) $manual_diseases = $decoded;
            else $manual_diseases = array_map('trim', explode(',', $raw_manual_diseases));
        } elseif (is_array($raw_manual_diseases)) {
            $manual_diseases = $raw_manual_diseases;
        }
        
        $manual_disease_codes = [];
        if (is_string($raw_manual_disease_codes)) {
            $decoded = json_decode($raw_manual_disease_codes, true);
            if (is_array($decoded)) $manual_disease_codes = $decoded;
            else $manual_disease_codes = array_map('trim', explode(',', $raw_manual_disease_codes));
        } elseif (is_array($raw_manual_disease_codes)) {
            $manual_disease_codes = $raw_manual_disease_codes;
        }
        
        $clean_diagnosis_ids = [];
        foreach ($diagnosis_ids as $disease_id) {
            $disease_id = (int)$disease_id;
            if ($disease_id > 0 && !in_array($disease_id, $clean_diagnosis_ids)) $clean_diagnosis_ids[] = $disease_id;
        }
        
        $clean_manual_diseases = [];
        foreach ($manual_diseases as $manual) {
            $manual = trim($manual);
            $manual = str_replace(['[', ']', '"', '\\', "'"], '', $manual);
            $manual = trim($manual);
            if (!empty($manual) && !in_array($manual, $clean_manual_diseases)) $clean_manual_diseases[] = $manual;
        }
        
        $diagnosis_saved = false;
        $diagnosis_data = [];
        
        if (!empty($clean_diagnosis_ids) || !empty($clean_manual_diseases)) {
            try {
                $result = saveDiagnosisToDatabase($db, $visit_id, $doctor_id, $doctor_branch_id, [
                    'diagnosis_ids' => $clean_diagnosis_ids,
                    'manual_diseases' => $clean_manual_diseases,
                    'manual_disease_codes' => $manual_disease_codes,
                    'treatment' => $treatment,
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
                        $response['message'] = '❌ Insufficient stock! Available: ' . $med['stock'];
                        echo json_encode($response);
                        exit;
                    }
                    
                    $db->beginTransaction();
                    
                    $prescription_number = 'PRES-' . date('Ymd') . '-' . str_pad($patient_id, 4, '0', STR_PAD_LEFT) . '-' . rand(100, 999);
                    
                    $stmt = $db->prepare("
                        INSERT INTO prescriptions (prescription_number, visit_id, patient_id, doctor_id, status, branch_id, created_at)
                        VALUES (?, ?, ?, ?, 'pending', ?, NOW())
                    ");
                    $stmt->execute([$prescription_number, $visit_id, $patient_id, $doctor_id, $doctor_branch_id]);
                    $prescription_id = $db->lastInsertId();
                    
                    $unit_price = $med['selling_price'];
                    $total_price = $unit_price * $quantity;
                    $previous_stock = (int)$med['stock'];
                    $new_stock = $previous_stock - $quantity;
                    
                    $stmt = $db->prepare("UPDATE medications_inventory SET quantity = ? WHERE id = ?");
                    $stmt->execute([$new_stock, $inventory_id]);
                    
                    $stmt = $db->prepare("
                        INSERT INTO prescription_items (prescription_id, patient_id, inventory_id, medication_name, 
                            dosage, frequency, quantity, duration, route, instructions, unit_price, total_price, branch_id, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$prescription_id, $patient_id, $inventory_id, $med['medication_name'], 
                        $dosage, $frequency, $quantity, $duration, $route, $instructions, $unit_price, $total_price, $doctor_branch_id]);
                    
                    $stmt = $db->prepare("
                        INSERT INTO bill_items (bill_id, patient_id, branch_id, item_type, item_name, quantity, unit_price, total_price, status, reference_id, reference_type, created_at)
                        VALUES (?, ?, ?, 'medication', ?, ?, ?, ?, 'pending', ?, 'prescription', NOW())
                    ");
                    $stmt->execute([$bill_id, $patient_id, $doctor_branch_id,
                        $med['medication_name'] . ' (Batch: ' . ($med['batch_number'] ?? 'N/A') . ')',
                        $quantity, $unit_price, $total_price, $prescription_id]);
                    
                    // ✅ V4 FIX: Log stock movement for medication
                    logStockMovement(
                        $db, 'medicine', $inventory_id, $patient_id, 'out', $quantity,
                        $previous_stock, $new_stock, 'prescription', $prescription_id,
                        $doctor_id, $doctor_branch_id,
                        "Prescribed by Dr. {$doctor_name}: {$med['medication_name']} (Rx #$prescription_number) - Qty: $quantity"
                    );
                    
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
                    $response['diagnosis_string'] = $diagnosis_data['diagnosis_string'] ?? '';
                } else {
                    $response['message'] = '❌ Medication not found or inactive';
                }
            } catch (Exception $e) {
                if (isset($db) && $db->inTransaction()) $db->rollBack();
                $response['message'] = '❌ Database error: ' . $e->getMessage();
            }
        } else {
            $response['message'] = '❌ Please select a medication and quantity';
        }
        
        echo json_encode($response);
        exit;
    }
    
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
                SELECT pi.medication_name, pi.quantity, pi.inventory_id, pi.total_price
                FROM prescription_items pi
                WHERE pi.prescription_id = ?
            ");
            $stmt->execute([$prescription_id]);
            $med_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            try {
                $db->beginTransaction();
                
                foreach ($med_items as $med) {
                    if ($med && $med['inventory_id']) {
                        // Get current stock for logging
                        $stmt_stk = $db->prepare("SELECT quantity FROM medications_inventory WHERE id = ? AND branch_id = ?");
                        $stmt_stk->execute([$med['inventory_id'], $doctor_branch_id]);
                        $stock_row = $stmt_stk->fetch(PDO::FETCH_ASSOC);
                        $previous_stock = (int)($stock_row['quantity'] ?? 0);
                        $new_stock = $previous_stock + $med['quantity'];
                        
                        $stmt = $db->prepare("UPDATE medications_inventory SET quantity = ? WHERE id = ? AND branch_id = ?");
                        $stmt->execute([$new_stock, $med['inventory_id'], $doctor_branch_id]);
                        
                        // ✅ V4 FIX: Log stock movement (returned)
                        logStockMovement(
                            $db, 'medicine', $med['inventory_id'], $patient_id, 'in', $med['quantity'],
                            $previous_stock, $new_stock, 'prescription', $prescription_id,
                            $doctor_id, $doctor_branch_id,
                            "Stock returned - Removed by Dr. {$doctor_name}: {$med['medication_name']} (Qty: {$med['quantity']})"
                        );
                    }
                }
                
                $stmt = $db->prepare("DELETE FROM bill_items WHERE bill_id = ? AND reference_id = ? AND reference_type = 'prescription'");
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
                if (isset($db) && $db->inTransaction()) $db->rollBack();
                $response['message'] = '❌ Error: ' . $e->getMessage();
            }
        } else {
            $response['message'] = '❌ Invalid medication';
        }
        
        echo json_encode($response);
        exit;
    }
    
    if ($action === 'add_procedures_batch') {
        header('Content-Type: application/json');
        
        if ($sections_frozen && !$is_waiting) {
            echo json_encode(['success' => false, 'message' => '❌ Cannot add procedures. Lab tests pending!']);
            exit;
        }
        
        $raw_diagnosis_ids = isset($_POST['diagnosis_ids']) ? $_POST['diagnosis_ids'] : [];
        $raw_manual_diseases = isset($_POST['manual_diseases']) ? $_POST['manual_diseases'] : [];
        $raw_manual_disease_codes = isset($_POST['manual_disease_codes']) ? $_POST['manual_disease_codes'] : [];
        
        $treatment = trim($_POST['treatment'] ?? '');
        $symptoms = trim($_POST['symptoms'] ?? '');
        $hpi = trim($_POST['hpi'] ?? '');
        $physical_exam = trim($_POST['physical_exam'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        
        $diagnosis_ids = [];
        if (is_string($raw_diagnosis_ids)) {
            $decoded = json_decode($raw_diagnosis_ids, true);
            if (is_array($decoded)) $diagnosis_ids = $decoded;
            else $diagnosis_ids = array_map('trim', explode(',', $raw_diagnosis_ids));
        } elseif (is_array($raw_diagnosis_ids)) {
            $diagnosis_ids = $raw_diagnosis_ids;
        }
        
        $manual_diseases = [];
        if (is_string($raw_manual_diseases)) {
            $decoded = json_decode($raw_manual_diseases, true);
            if (is_array($decoded)) $manual_diseases = $decoded;
            else $manual_diseases = array_map('trim', explode(',', $raw_manual_diseases));
        } elseif (is_array($raw_manual_diseases)) {
            $manual_diseases = $raw_manual_diseases;
        }
        
        $manual_disease_codes = [];
        if (is_string($raw_manual_disease_codes)) {
            $decoded = json_decode($raw_manual_disease_codes, true);
            if (is_array($decoded)) $manual_disease_codes = $decoded;
            else $manual_disease_codes = array_map('trim', explode(',', $raw_manual_disease_codes));
        } elseif (is_array($raw_manual_disease_codes)) {
            $manual_disease_codes = $raw_manual_disease_codes;
        }
        
        $clean_diagnosis_ids = [];
        foreach ($diagnosis_ids as $disease_id) {
            $disease_id = (int)$disease_id;
            if ($disease_id > 0 && !in_array($disease_id, $clean_diagnosis_ids)) $clean_diagnosis_ids[] = $disease_id;
        }
        
        $clean_manual_diseases = [];
        foreach ($manual_diseases as $manual) {
            $manual = trim($manual);
            $manual = str_replace(['[', ']', '"', '\\', "'"], '', $manual);
            $manual = trim($manual);
            if (!empty($manual) && !in_array($manual, $clean_manual_diseases)) $clean_manual_diseases[] = $manual;
        }
        
        $diagnosis_saved = false;
        $diagnosis_data = [];
        
        if (!empty($clean_diagnosis_ids) || !empty($clean_manual_diseases)) {
            try {
                $result = saveDiagnosisToDatabase($db, $visit_id, $doctor_id, $doctor_branch_id, [
                    'diagnosis_ids' => $clean_diagnosis_ids,
                    'manual_diseases' => $clean_manual_diseases,
                    'manual_disease_codes' => $manual_disease_codes,
                    'treatment' => $treatment,
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
                    SELECT pc.* FROM procedures_catalog pc
                    WHERE pc.id = ? AND pc.is_active = 1 
                    AND (pc.branch_id IS NULL OR pc.branch_id = ?)
                ");
                $stmt->execute([$proc_id, $doctor_branch_id]);
                $procedure = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$procedure) { $failed++; continue; }
                
                $stmt = $db->prepare("
                    SELECT id FROM procedures 
                    WHERE visit_id = ? AND procedure_id = ? AND status != 'cancelled'
                ");
                $stmt->execute([$visit_id, $proc_id]);
                if ($stmt->fetch(PDO::FETCH_ASSOC)) { $failed++; continue; }
                
                $stmt = $db->prepare("
                    INSERT INTO procedures (
                        visit_id, patient_id, doctor_id, procedure_id, procedure_name,
                        category, procedure_price, status, branch_id, notes, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, NULL, NOW())
                ");
                $stmt->execute([
                    $visit_id, $patient_id, $doctor_id, $proc_id,
                    $procedure['procedure_name'], $procedure['category'],
                    $procedure['price'], $doctor_branch_id
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
                    $bill_id, $patient_id, $doctor_branch_id, $proc_id,
                    $procedure['procedure_name'] . ($procedure_price == 0 ? ' (FREE)' : ''),
                    $procedure_price, $procedure_price, $proc_id_inserted
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
            if (isset($db) && $db->inTransaction()) $db->rollBack();
            $response['message'] = '❌ Error: ' . $e->getMessage();
        }
        
        echo json_encode($response);
        exit;
    }
    
    // ================================================================
    // ✅✅✅ V4 FIX: add_equipment_batch - SASA INAINGIZA STOCK_MOVEMENTS ✅✅✅
    // ================================================================
    if ($action === 'add_equipment_batch') {
        header('Content-Type: application/json');
        
        if ($sections_frozen && !$is_waiting) {
            echo json_encode(['success' => false, 'message' => '❌ Cannot add equipment. Lab tests pending!']);
            exit;
        }
        
        $raw_diagnosis_ids = isset($_POST['diagnosis_ids']) ? $_POST['diagnosis_ids'] : [];
        $raw_manual_diseases = isset($_POST['manual_diseases']) ? $_POST['manual_diseases'] : [];
        $raw_manual_disease_codes = isset($_POST['manual_disease_codes']) ? $_POST['manual_disease_codes'] : [];
        
        $treatment = trim($_POST['treatment'] ?? '');
        $symptoms = trim($_POST['symptoms'] ?? '');
        $hpi = trim($_POST['hpi'] ?? '');
        $physical_exam = trim($_POST['physical_exam'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        
        $diagnosis_ids = [];
        if (is_string($raw_diagnosis_ids)) {
            $decoded = json_decode($raw_diagnosis_ids, true);
            if (is_array($decoded)) $diagnosis_ids = $decoded;
            else $diagnosis_ids = array_map('trim', explode(',', $raw_diagnosis_ids));
        } elseif (is_array($raw_diagnosis_ids)) {
            $diagnosis_ids = $raw_diagnosis_ids;
        }
        
        $manual_diseases = [];
        if (is_string($raw_manual_diseases)) {
            $decoded = json_decode($raw_manual_diseases, true);
            if (is_array($decoded)) $manual_diseases = $decoded;
            else $manual_diseases = array_map('trim', explode(',', $raw_manual_diseases));
        } elseif (is_array($raw_manual_diseases)) {
            $manual_diseases = $raw_manual_diseases;
        }
        
        $manual_disease_codes = [];
        if (is_string($raw_manual_disease_codes)) {
            $decoded = json_decode($raw_manual_disease_codes, true);
            if (is_array($decoded)) $manual_disease_codes = $decoded;
            else $manual_disease_codes = array_map('trim', explode(',', $raw_manual_disease_codes));
        } elseif (is_array($raw_manual_disease_codes)) {
            $manual_disease_codes = $raw_manual_disease_codes;
        }
        
        $clean_diagnosis_ids = [];
        foreach ($diagnosis_ids as $disease_id) {
            $disease_id = (int)$disease_id;
            if ($disease_id > 0 && !in_array($disease_id, $clean_diagnosis_ids)) $clean_diagnosis_ids[] = $disease_id;
        }
        
        $clean_manual_diseases = [];
        foreach ($manual_diseases as $manual) {
            $manual = trim($manual);
            $manual = str_replace(['[', ']', '"', '\\', "'"], '', $manual);
            $manual = trim($manual);
            if (!empty($manual) && !in_array($manual, $clean_manual_diseases)) $clean_manual_diseases[] = $manual;
        }
        
        $diagnosis_saved = false;
        $diagnosis_data = [];
        
        if (!empty($clean_diagnosis_ids) || !empty($clean_manual_diseases)) {
            try {
                $result = saveDiagnosisToDatabase($db, $visit_id, $doctor_id, $doctor_branch_id, [
                    'diagnosis_ids' => $clean_diagnosis_ids,
                    'manual_diseases' => $clean_manual_diseases,
                    'manual_disease_codes' => $manual_disease_codes,
                    'treatment' => $treatment,
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
                
                if ($equipment_id <= 0 || $quantity <= 0) { $failed++; continue; }
                
                $stmt = $db->prepare("
                    SELECT id, equipment_name, selling_price, quantity as stock, batch_number, expiry_date
                    FROM medical_equipment 
                    WHERE id = ? AND status = 'active' AND branch_id = ?
                    FOR UPDATE
                ");
                $stmt->execute([$equipment_id, $doctor_branch_id]);
                $equipment = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$equipment) { $failed++; continue; }
                
                if (!empty($equipment['expiry_date']) && $equipment['expiry_date'] !== '0000-00-00') {
                    if (strtotime($equipment['expiry_date']) < time()) { $failed++; continue; }
                }
                
                if ($equipment['stock'] < $quantity) { $failed++; continue; }
                
                $unit_price = $equipment['selling_price'] ?? 0;
                $total_price = $unit_price * $quantity;
                $previous_stock = (int)$equipment['stock'];
                $new_stock = $previous_stock - $quantity;
                
                // ✅ UPDATE stock
                $stmt = $db->prepare("UPDATE medical_equipment SET quantity = ? WHERE id = ?");
                $stmt->execute([$new_stock, $equipment_id]);
                
                $item_name = $equipment['equipment_name'];
                
                // ✅ INSERT bill_item
                $stmt = $db->prepare("
                    INSERT INTO bill_items (
                        bill_id, patient_id, branch_id, item_type, item_id,
                        item_name, quantity, unit_price, total_price,
                        reference_id, reference_type, status, created_at
                    ) VALUES (?, ?, ?, 'equipment', ?, ?, ?, ?, ?, ?, 'equipment', 'pending', NOW())
                ");
                $stmt->execute([
                    $bill_id, $patient_id, $doctor_branch_id, $equipment_id,
                    $item_name . ($total_price == 0 ? ' (FREE)' : ''),
                    $quantity, $unit_price, $total_price, $equipment_id
                ]);
                $bill_item_id = $db->lastInsertId();
                
                // ✅✅✅ V4 FIX: INSERT stock_movement HAPA! ✅✅✅
                logStockMovement(
                    $db, 'equipment', $equipment_id, $patient_id, 'out', $quantity,
                    $previous_stock, $new_stock, 'procedure', $bill_item_id,
                    $doctor_id, $doctor_branch_id,
                    "Doctor used (Dr. {$doctor_name}): {$item_name} - Bill #{$bill_id} - Qty: {$quantity}"
                );
                
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
            if (isset($db) && $db->inTransaction()) $db->rollBack();
            $response['message'] = '❌ Error: ' . $e->getMessage();
        }
        
        echo json_encode($response);
        exit;
    }
    
    // ================================================================
    // ✅✅✅ V4 FIX: remove_added_item - INAINGIZA STOCK_MOVEMENT YA RETURN ✅✅✅
    // ================================================================
    if ($action === 'remove_added_item') {
        header('Content-Type: application/json');
        
        $type = $_POST['type'] ?? '';
        $item_id = (int)($_POST['id'] ?? 0);
        $visit_id_input = (int)($_POST['visit_id'] ?? 0);
        
        $response = ['success' => false, 'message' => ''];
        
        if (empty($type) || $item_id <= 0 || $visit_id_input <= 0) {
            $response['message'] = '❌ Invalid request';
            echo json_encode($response);
            exit;
        }
        
        try {
            $db->beginTransaction();
            
            if ($type === 'procedure') {
                $stmt = $db->prepare("SELECT p.* FROM procedures p WHERE p.id = ? AND p.visit_id = ? AND p.status != 'cancelled'");
                $stmt->execute([$item_id, $visit_id_input]);
                $procedure = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$procedure) {
                    $response['message'] = '❌ Procedure not found';
                    echo json_encode($response);
                    exit;
                }
                
                $stmt = $db->prepare("DELETE FROM bill_items WHERE bill_id = ? AND reference_id = ? AND reference_type = 'procedure'");
                $stmt->execute([$bill_id, $item_id]);
                
                $stmt = $db->prepare("DELETE FROM procedures WHERE id = ? AND visit_id = ?");
                $stmt->execute([$item_id, $visit_id_input]);
                
                $response['message'] = '✅ Procedure removed!';
            } elseif ($type === 'equipment') {
                $stmt = $db->prepare("
                    SELECT bi.* FROM bill_items bi
                    WHERE bi.id = ? AND bi.bill_id = ? AND bi.item_type = 'equipment' AND bi.status != 'cancelled'
                ");
                $stmt->execute([$item_id, $bill_id]);
                $equip_item = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$equip_item) {
                    $response['message'] = '❌ Equipment not found';
                    echo json_encode($response);
                    exit;
                }
                
                // Use item_id (equipment_id) directly if available
                $equipment_id = (int)($equip_item['item_id'] ?? 0);
                
                if ($equipment_id > 0) {
                    $stmt_eq = $db->prepare("
                        SELECT id, equipment_name, quantity as stock 
                        FROM medical_equipment 
                        WHERE id = ? AND branch_id = ?
                        FOR UPDATE
                    ");
                    $stmt_eq->execute([$equipment_id, $doctor_branch_id]);
                } else {
                    // Fallback: find by name
                    $clean_name = preg_replace('/\s*\(FREE\)\s*$/i', '', $equip_item['item_name']);
                    $stmt_eq = $db->prepare("
                        SELECT id, equipment_name, quantity as stock 
                        FROM medical_equipment 
                        WHERE equipment_name = ? AND branch_id = ?
                        LIMIT 1
                        FOR UPDATE
                    ");
                    $stmt_eq->execute([$clean_name, $doctor_branch_id]);
                }
                $equipment = $stmt_eq->fetch(PDO::FETCH_ASSOC);
                
                if ($equipment) {
                    $quantity = (int)($equip_item['quantity'] ?? 1);
                    $previous_stock = (int)$equipment['stock'];
                    $new_stock = $previous_stock + $quantity;
                    
                    // ✅ UPDATE stock
                    $stmt = $db->prepare("UPDATE medical_equipment SET quantity = ? WHERE id = ? AND branch_id = ?");
                    $stmt->execute([$new_stock, $equipment['id'], $doctor_branch_id]);
                    
                    // ✅✅✅ V4 FIX: Log stock movement (returned) ✅✅✅
                    logStockMovement(
                        $db, 'equipment', $equipment['id'], $patient_id, 'in', $quantity,
                        $previous_stock, $new_stock, 'procedure', $item_id,
                        $doctor_id, $doctor_branch_id,
                        "Stock returned - Removed by Dr. {$doctor_name}: {$equipment['equipment_name']} from bill"
                    );
                }
                
                $stmt = $db->prepare("DELETE FROM bill_items WHERE id = ? AND bill_id = ?");
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
            if (isset($db) && $db->inTransaction()) $db->rollBack();
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
        
        $symptoms = trim($_POST['symptoms'] ?? '');
        $hpi = trim($_POST['hpi'] ?? '');
        $physical_exam = trim($_POST['physical_exam'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        
        $diagnosis_ids = isset($_POST['diagnosis_ids']) ? (array)$_POST['diagnosis_ids'] : [];
        $manual_diseases = isset($_POST['manual_diseases']) ? (array)$_POST['manual_diseases'] : [];
        $manual_disease_codes = isset($_POST['manual_disease_codes']) ? (array)$_POST['manual_disease_codes'] : [];
        $treatment = trim($_POST['treatment'] ?? '');
        
        $stmt = $db->prepare("
            UPDATE visits 
            SET symptoms = ?, hpi = ?, physical_exam = ?, notes = ?, updated_at = NOW()
            WHERE id = ? AND doctor_id = ?
        ");
        $stmt->execute([$symptoms, $hpi, $physical_exam, $notes, $visit_id, $doctor_id]);
        
        if (!empty($manual_diseases) || !empty($diagnosis_ids)) {
            try {
                saveDiagnosisToDatabase($db, $visit_id, $doctor_id, $doctor_branch_id, [
                    'diagnosis_ids' => $diagnosis_ids,
                    'manual_diseases' => $manual_diseases,
                    'manual_disease_codes' => $manual_disease_codes,
                    'treatment' => $treatment,
                    'symptoms' => $symptoms,
                    'hpi' => $hpi,
                    'physical_exam' => $physical_exam,
                    'notes' => $notes
                ]);
            } catch (Exception $e) {
                error_log("Failed to save diagnosis with lab: " . $e->getMessage());
            }
        }
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM lab_tests WHERE visit_id = ? AND status IN ('pending', 'in_progress')");
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
                if ($stmt_check->fetchColumn() > 0) { $lab_tests_skipped++; continue; }
                
                if ($required_equipment_id) {
                    $stmt_eq = $db->prepare("
                        SELECT id, equipment_name, quantity as stock, batch_number, expiry_date
                        FROM medical_equipment 
                        WHERE id = ? AND status = 'active' AND branch_id = ?
                        AND (expiry_date IS NULL OR expiry_date = '0000-00-00' OR expiry_date > CURDATE())
                        FOR UPDATE
                    ");
                    $stmt_eq->execute([$required_equipment_id, $doctor_branch_id]);
                    $equip = $stmt_eq->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$equip) { $errors[] = "❌ Equipment not found for test: $test_name"; continue; }
                    if ($equip['stock'] < $equipment_quantity_used) {
                        $errors[] = "❌ Insufficient equipment for $test_name.";
                        continue;
                    }
                    
                    $previous_stock = (int)$equip['stock'];
                    $new_equipment_stock = $previous_stock - $equipment_quantity_used;
                    
                    $stmt_update = $db->prepare("UPDATE medical_equipment SET quantity = ? WHERE id = ?");
                    $stmt_update->execute([$new_equipment_stock, $required_equipment_id]);
                    
                    // ✅ Lab test already logs stock movement correctly
                    $stmt_log = $db->prepare("
                        INSERT INTO stock_movements 
                        (equipment_id, patient_id, movement_type, quantity, previous_stock, new_stock, reference_type, reference_id, performed_by, branch_id, notes, created_at)
                        VALUES (?, ?, 'out', ?, ?, ?, 'lab_test', ?, ?, ?, ?, NOW())
                    ");
                    $stmt_log->execute([
                        $required_equipment_id, $patient_id, $equipment_quantity_used,
                        $previous_stock, $new_equipment_stock, $test_id,
                        $doctor_id, $doctor_branch_id, 
                        "Lab test (Dr. {$doctor_name}): $test_name"
                    ]);
                }
                
                $stmt = $db->prepare("
                    INSERT INTO lab_tests (
                        visit_id, patient_id, doctor_id, test_id, test_name, test_price,
                        status, branch_id, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
                ");
                $stmt->execute([$visit_id, $patient_id, $doctor_id, $test_id, $test_name, $test_price, $doctor_branch_id]);
                $lab_test_id = $db->lastInsertId();
                
                $stmt = $db->prepare("
                    INSERT INTO bill_items (
                        bill_id, patient_id, branch_id, item_type, item_id,
                        item_name, quantity, unit_price, total_price,
                        reference_id, reference_type, status, created_at
                    ) VALUES (?, ?, ?, 'lab_test', ?, ?, 1, ?, ?, ?, 'lab_test', 'pending', NOW())
                ");
                $stmt->execute([
                    $bill_id, $patient_id, $doctor_branch_id, $lab_test_id,
                    $test_name . ($required_equipment_id ? ' (equipment included)' : ''),
                    $test_price, $test_price, $lab_test_id
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
            if (isset($db) && $db->inTransaction()) $db->rollBack();
            $_SESSION['flash_message'] = "❌ Error: " . $e->getMessage();
            $_SESSION['flash_type'] = 'error';
            unset($_SESSION['lab_cart']);
            header('Location: consultation.php?visit_id=' . $visit_id);
            exit;
        }
        
        unset($_SESSION['lab_cart']);
        
        if ($lab_tests_sent > 0) {
            $msg = "✅ " . $lab_tests_sent . " lab request(s) sent!";
            $msg .= "<br>💰 Total: TSh " . number_format($total_lab_price, 0);
            if (!empty($errors)) $msg .= "<br>⚠️ " . implode(', ', $errors);
            
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
    
    if (isset($_POST['save_consultation'])) {
        $diagnosis_ids = isset($_POST['diagnosis_ids']) ? (array)$_POST['diagnosis_ids'] : [];
        $manual_diseases = isset($_POST['manual_diseases']) ? (array)$_POST['manual_diseases'] : [];
        $manual_disease_codes = isset($_POST['manual_disease_codes']) ? (array)$_POST['manual_disease_codes'] : [];
        $treatment = trim($_POST['treatment'] ?? '');
        $symptoms = trim($_POST['symptoms'] ?? '');
        $hpi = trim($_POST['hpi'] ?? '');
        $physical_exam = trim($_POST['physical_exam'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        
        if (empty($manual_diseases) && empty($diagnosis_ids)) {
            $_SESSION['flash_message'] = "❌ Please enter at least one diagnosis before saving!";
            $_SESSION['flash_type'] = 'error';
            header('Location: consultation.php?visit_id=' . $visit_id);
            exit;
        }
        
        try {
            saveDiagnosisToDatabase($db, $visit_id, $doctor_id, $doctor_branch_id, [
                'diagnosis_ids' => $diagnosis_ids,
                'manual_diseases' => $manual_diseases,
                'manual_disease_codes' => $manual_disease_codes,
                'treatment' => $treatment,
                'symptoms' => $symptoms,
                'hpi' => $hpi,
                'physical_exam' => $physical_exam,
                'notes' => $notes
            ]);
        } catch (Exception $e) {
            $_SESSION['flash_message'] = "❌ Error saving diagnosis: " . $e->getMessage();
            $_SESSION['flash_type'] = 'error';
            header('Location: consultation.php?visit_id=' . $visit_id);
            exit;
        }
        
        $stmt = $db->prepare("
            UPDATE visits 
            SET status = 'waiting', updated_at = NOW()
            WHERE id = ? AND doctor_id = ? AND status IN ('prescribed', 'lab_test', 'assigned')
        ");
        $stmt->execute([$visit_id, $doctor_id]);
        
        $stmt_check = $db->prepare("SELECT status FROM visits WHERE id = ?");
        $stmt_check->execute([$visit_id]);
        $new_status = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        if ($new_status && $new_status['status'] === 'waiting') {
            $auto_completed = checkAndAutoCompleteVisit($db, $visit_id, $bill_id);
            
            if ($auto_completed) {
                $_SESSION['flash_message'] = "✅ Consultation saved AND auto-completed!";
                $_SESSION['flash_type'] = 'success';
            } else {
                $_SESSION['flash_message'] = "✅ Consultation saved! Status changed to WAITING.";
                $_SESSION['flash_type'] = 'success';
            }
        } else {
            $_SESSION['flash_message'] = "⚠️ Consultation saved but status may not be WAITING.";
            $_SESSION['flash_type'] = 'warning';
        }
        
        header('Location: consultation.php?visit_id=' . $visit_id);
        exit;
    }
}

// Initialize lab cart
if (!isset($_SESSION['lab_cart'])) $_SESSION['lab_cart'] = [];
$lab_cart = $_SESSION['lab_cart'];
$lab_cart_total = array_sum(array_column($lab_cart, 'price'));
$lab_cart_count = count($lab_cart);

// ✅ V3: Filter valid prescriptions kwa HTML (skip empty)
$valid_prescriptions = array_filter($prescriptions, function($med) {
    return !empty($med['medication_name']) && !empty($med['item_id']);
});

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
            --teal: #0D9488;
            --teal-bg: #CCFBF1;
            --sky: #0EA5E9;
            --sky-dark: #0284C7;
            --sky-bg: #E0F2FE;
            --premium: #D97706;
            --premium-bg: #FEF3C7;
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
            --primary-bg: #1E3A5F;
            --success-bg: #064E3B;
            --danger-bg: #7F1D1D;
            --warning-bg: #78350F;
            --purple-bg: #4C1D95;
            --sky-bg: #0C2A3A;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            background: var(--bg-body); 
            color: var(--text-primary); 
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif; 
            line-height: 1.6; 
        }
        .main-content { 
            margin-left: 270px; 
            margin-top: 68px; 
            padding: 28px 32px; 
            min-height: calc(100vh - 68px); 
        }
        
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
        }
        .page-badge {
            font-size: 0.7rem;
            font-weight: 600;
            background: rgba(255,255,255,0.2);
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
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            background: #7C3AED !important;
            color: #ffffff !important;
            border: 1px solid rgba(255,255,255,0.3);
            box-shadow: 0 2px 8px rgba(124, 58, 237, 0.4);
        }
        .status-badge.badge-purple { background: #7C3AED !important; }
        .status-badge.badge-success { background: #059669 !important; }
        .status-badge.badge-warning { background: #D97706 !important; }
        .status-badge.badge-info { background: #0B5ED7 !important; }
        .status-badge.badge-danger { background: #DC2626 !important; }
        
        .badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        .badge-purple { background: var(--purple-bg); color: var(--purple); }
        .badge-info { background: var(--primary-bg); color: var(--primary); }
        .badge-success { background: var(--success-bg); color: var(--success); }
        
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
            top: 0; left: 0; right: 0;
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
        
        .bill-summary-card.premium-card { border-color: #D97706; }
        .bill-summary-card.premium-card::before { background: linear-gradient(90deg, #D97706, #F59E0B); }
        .bill-summary-card.premium-card .bill-summary-icon { background: #FEF3C7; color: #D97706; }
        .bill-summary-card.premium-card .bill-summary-value { color: #D97706; }
        [data-theme="dark"] .bill-summary-card.premium-card .bill-summary-icon { background: #3D2E0A; color: #FCD34D; }
        [data-theme="dark"] .bill-summary-card.premium-card .bill-summary-value { color: #FCD34D; }
        
        .disease-checkbox-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            min-height: 44px;
        }
        .disease-checkbox-item:hover {
            background: var(--primary-bg);
            border-color: var(--primary-light);
        }
        .disease-checkbox-item input[type="checkbox"] {
            width: 20px;
            height: 20px;
            accent-color: var(--primary);
            cursor: pointer;
            flex-shrink: 0;
            margin: 0;
        }
        .disease-checkbox-item .disease-name {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-primary);
            flex: 1;
        }
        .disease-checkbox-item .disease-code {
            font-size: 0.65rem;
            color: var(--text-secondary);
            font-family: monospace;
            background: var(--gray-100);
            padding: 1px 8px;
            border-radius: 10px;
            flex-shrink: 0;
        }
        .disease-checkbox-item .disease-category {
            font-size: 0.6rem;
            color: var(--primary);
            background: var(--primary-bg);
            padding: 1px 8px;
            border-radius: 10px;
            flex-shrink: 0;
        }
        
        #diseaseCheckboxContainer .diseases-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            padding: 4px;
        }

        .lab-test-item-select {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 0.82rem;
            border: 2px solid var(--border-color);
            cursor: pointer;
            transition: var(--transition);
            user-select: none;
            color: var(--text-primary);
            background: var(--bg-card);
            min-height: 48px;
        }
        .lab-test-item-select:hover {
            background: var(--primary-bg);
            border-color: var(--primary-light);
            transform: translateY(-1px);
        }
        .lab-test-item-select.selected {
            background: var(--primary-bg);
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(11,94,215,0.1);
        }
        .lab-test-item-select .item-check {
            width: 20px;
            height: 20px;
            border: 2px solid var(--border-color);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: var(--transition);
            background: var(--bg-card);
        }
        .lab-test-item-select.selected .item-check {
            background: var(--primary);
            border-color: var(--primary);
        }
        .lab-test-item-select .item-check i {
            font-size: 0.7rem;
            color: #ffffff;
            opacity: 0;
        }
        .lab-test-item-select.selected .item-check i { opacity: 1; }
        .lab-test-item-select .lab-test-info { display: flex; flex-direction: column; flex: 1; min-width: 0; }
        .lab-test-item-select .lab-test-name { font-weight: 500; font-size: 0.85rem; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .lab-test-item-select .lab-test-details { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 2px; }
        .lab-test-item-select .lab-test-price { font-size: 0.7rem; color: var(--success); font-weight: 600; }
        .lab-test-item-select .lab-test-category { font-size: 0.6rem; color: var(--purple); background: var(--purple-bg); padding: 0 8px; border-radius: 10px; }
        .lab-test-item-select .lab-test-equipment { font-size: 0.6rem; color: var(--teal); background: var(--teal-bg); padding: 0 8px; border-radius: 10px; display: inline-flex; align-items: center; gap: 3px; }
        .lab-test-item-select.out-of-stock { opacity: 0.6; border-color: var(--danger); }

        #labTestsGrid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            padding: 8px;
            background: var(--gray-100);
            border-radius: var(--radius);
            max-height: 320px;
            overflow-y: auto;
        }

        .medication-item-select {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 0.82rem;
            background: var(--bg-card);
            border: 2px solid var(--border-color);
            cursor: pointer;
            transition: var(--transition);
            user-select: none;
            color: var(--text-primary);
            min-height: 46px;
        }
        .medication-item-select:hover {
            background: var(--primary-bg);
            border-color: var(--primary-light);
            transform: translateY(-1px);
        }
        .medication-item-select.selected {
            background: var(--primary-bg);
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(11,94,215,0.1);
        }
        .medication-item-select .item-check {
            width: 20px;
            height: 20px;
            border: 2px solid var(--border-color);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: var(--transition);
            background: var(--bg-card);
        }
        .medication-item-select.selected .item-check { background: var(--primary); border-color: var(--primary); }
        .medication-item-select .item-check i { font-size: 0.7rem; color: #ffffff; opacity: 0; }
        .medication-item-select.selected .item-check i { opacity: 1; }
        .medication-item-select .medication-info { display: flex; flex-direction: column; flex: 1; min-width: 0; }
        .medication-item-select .medication-name { font-weight: 500; font-size: 0.85rem; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .medication-item-select .medication-details { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 2px; }
        .medication-item-select .medication-price { font-size: 0.7rem; color: var(--success); font-weight: 600; }
        .medication-item-select .medication-stock { font-size: 0.65rem; color: var(--text-secondary); background: var(--gray-100); padding: 0 8px; border-radius: 10px; }
        .medication-item-select .medication-category { font-size: 0.6rem; color: var(--purple); background: var(--purple-bg); padding: 0 8px; border-radius: 10px; }
        .medication-item-select .medication-batches { font-size: 0.55rem; color: var(--text-secondary); }

        #medicationsGrid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            padding: 8px;
            background: var(--gray-100);
            border-radius: var(--radius);
            max-height: 320px;
            overflow-y: auto;
        }

        #proceduresGrid, #equipmentGrid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            padding: 12px;
            background: var(--gray-100);
            border-radius: var(--radius);
            max-height: 280px;
            overflow-y: auto;
        }

        .procedure-item-select, .equipment-item-select {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 0.82rem;
            background: var(--bg-card);
            border: 2px solid var(--border-color);
            cursor: pointer;
            transition: var(--transition);
            user-select: none;
            color: var(--text-primary);
            min-height: 46px;
        }
        .procedure-item-select:hover, .equipment-item-select:hover {
            background: var(--primary-bg);
            border-color: var(--primary-light);
            transform: translateY(-1px);
        }
        .procedure-item-select.selected, .equipment-item-select.selected {
            background: var(--primary-bg);
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(11,94,215,0.1);
        }
        .procedure-item-select .item-check, .equipment-item-select .item-check {
            width: 20px; height: 20px;
            border: 2px solid var(--border-color);
            border-radius: 4px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; transition: var(--transition);
            background: var(--bg-card);
        }
        .procedure-item-select.selected .item-check, .equipment-item-select.selected .item-check {
            background: var(--primary); border-color: var(--primary);
        }
        .procedure-item-select .item-check i, .equipment-item-select .item-check i {
            font-size: 0.7rem; color: #ffffff; opacity: 0;
        }
        .procedure-item-select.selected .item-check i, .equipment-item-select.selected .item-check i { opacity: 1; }
        .procedure-item-select .procedure-info, .equipment-item-select .equipment-info {
            display: flex; flex-direction: column; flex: 1; min-width: 0;
        }
        .procedure-item-select .procedure-name, .equipment-item-select .equipment-name {
            font-weight: 500; font-size: 0.82rem; color: var(--text-primary);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .procedure-item-select .procedure-price, .equipment-item-select .equipment-price {
            font-size: 0.7rem; font-weight: 600;
        }
        .procedure-item-select .procedure-price.free, .equipment-item-select .equipment-price.free { color: var(--success); }
        .procedure-item-select .procedure-price.paid, .equipment-item-select .equipment-price.paid { color: var(--text-secondary); }
        .equipment-item-select .equipment-details { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 2px; }
        .equipment-item-select .equipment-stock { font-size: 0.6rem; color: var(--text-secondary); background: var(--gray-200); padding: 0 8px; border-radius: 10px; }
        .equipment-item-select .equipment-stock.low { color: var(--warning); background: var(--warning-bg); }
        .equipment-item-select .equipment-stock.out { color: var(--danger); background: var(--danger-bg); }
        .equipment-item-select .equipment-batch { font-size: 0.55rem; color: var(--text-secondary); font-family: monospace; }
        .equipment-item-select .equipment-expiry { font-size: 0.55rem; padding: 0 6px; border-radius: 8px; }
        .equipment-item-select .equipment-expiry.valid { color: var(--success); background: var(--success-bg); }
        .equipment-item-select .equipment-expiry.expiring { color: var(--warning); background: var(--warning-bg); }
        .equipment-item-select .equipment-expiry.expired { color: var(--danger); background: var(--danger-bg); }
        .equipment-item-select .equipment-expiry.no-expiry { color: var(--text-secondary); background: var(--gray-200); }

        .chief-complaint-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .chief-complaint-grid .left-col { grid-column: 1; }
        .chief-complaint-grid .right-col { grid-column: 2; }

        .vital-signs-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
        }

        .vital-sign-item {
            background: var(--bg-card);
            border-radius: 10px;
            padding: 12px 10px;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 1px 4px rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
            border: 2px solid var(--border-color);
        }
        .vital-sign-item::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            border-radius: 10px 10px 0 0;
        }
        .vital-sign-item:hover { transform: translateY(-2px) scale(1.01); box-shadow: 0 4px 16px rgba(0,0,0,0.1); }

        .vital-sign-item.temp-item::before { background: linear-gradient(90deg, #EF4444, #F87171); }
        .vital-sign-item.temp-item .vital-icon, .vital-sign-item.temp-item .vital-value { color: #EF4444; }
        .vital-sign-item.bp-item::before { background: linear-gradient(90deg, #3B82F6, #60A5FA); }
        .vital-sign-item.bp-item .vital-icon, .vital-sign-item.bp-item .vital-value { color: #3B82F6; }
        .vital-sign-item.pulse-item::before { background: linear-gradient(90deg, #EC4899, #F472B6); }
        .vital-sign-item.pulse-item .vital-icon, .vital-sign-item.pulse-item .vital-value { color: #EC4899; }

        .vital-sign-item.spo2-item::before { background: linear-gradient(90deg, #0EA5E9, #38BDF8); }
        .vital-sign-item.spo2-item .vital-icon, .vital-sign-item.spo2-item .vital-value { color: #0284C7; }
        .vital-sign-item.spo2-item {
            background: linear-gradient(135deg, rgba(14, 165, 233, 0.05), rgba(14, 165, 233, 0.12));
            border-color: var(--sky);
        }
        .vital-sign-item.spo2-item:hover {
            border-color: var(--sky-dark);
            box-shadow: 0 4px 16px rgba(14, 165, 233, 0.2);
        }

        .vital-sign-item.weight-item::before { background: linear-gradient(90deg, #8B5CF6, #A78BFA); }
        .vital-sign-item.weight-item .vital-icon, .vital-sign-item.weight-item .vital-value { color: #8B5CF6; }
        .vital-sign-item.height-item::before { background: linear-gradient(90deg, #22C55E, #4ADE80); }
        .vital-sign-item.height-item .vital-icon, .vital-sign-item.height-item .vital-value { color: #22C55E; }
        .vital-sign-item.bmi-item::before { background: linear-gradient(90deg, #F59E0B, #FBBF24); }
        .vital-sign-item.bmi-item .vital-icon, .vital-sign-item.bmi-item .vital-value { color: #F59E0B; }

        .vital-sign-item .vital-icon { font-size: 1.4rem; display: block; margin-bottom: 3px; }
        .vital-sign-item .vital-label { font-size: 0.5rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.06em; display: block; }
        .vital-sign-item .vital-value { font-size: 1.1rem; font-weight: 700; display: block; margin-top: 2px; }
        .vital-sign-item .vital-unit { font-size: 0.55rem; color: var(--text-secondary); font-weight: 400; }

        .vital-sign-item .vital-status {
            font-size: 0.5rem;
            font-weight: 700;
            padding: 1px 8px;
            border-radius: 8px;
            display: inline-block;
            margin-top: 4px;
            letter-spacing: 0.4px;
        }
        .vital-sign-item .vital-status.spo2-normal { background: #D1FAE5; color: #059669; border: 1px solid #6EE7B7; }
        .vital-sign-item .vital-status.spo2-low { background: #FEF3C7; color: #D97706; border: 1px solid #FCD34D; }
        .vital-sign-item .vital-status.spo2-critical { background: #FEE2E2; color: #DC2626; border: 1px solid #FCA5A5; }

        .vital-signs-footer {
            margin-top: 10px;
            padding: 6px 12px;
            background: linear-gradient(135deg, #F0F9FF, #E0F2FE);
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
            font-size: 0.7rem;
            border: 1px dashed var(--sky);
        }
        [data-theme="dark"] .vital-signs-footer {
            background: #0C2A3A;
            border-color: #0EA5E9;
        }

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
            top: 0; left: 0; right: 0;
            height: 4px;
            background: var(--primary-gradient);
            border-radius: 0 0 4px 4px;
        }
        .consultation-card:hover { border-color: var(--primary); box-shadow: var(--shadow-lg); }
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
        
        .bill-summary-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }
        
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
        .status-step.active { background: var(--primary); color: #ffffff; }
        .status-step.completed { background: var(--success); color: #ffffff; }
        .status-arrow { color: var(--text-secondary); font-size: 0.6rem; }
        
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
        .patient-info-details h4 { font-size: 1.2rem; font-weight: 600; color: var(--text-primary); margin: 0; }
        .patient-info-details p { font-size: 0.8rem; color: var(--text-secondary); margin: 2px 0; }
        .patient-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 20px; }
        .patient-info-grid .info-item span:first-child { display: block; font-size: 0.65rem; color: var(--text-secondary); font-weight: 500; text-transform: uppercase; }
        .patient-info-grid .info-item span:last-child { display: block; font-size: 0.9rem; font-weight: 500; color: var(--text-primary); }
        .col-span-2 { grid-column: span 2; }
        
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 5px; }
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
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(11,94,215,0.12); }
        .form-control:disabled { opacity: 0.6; cursor: not-allowed; background: var(--gray-100); }
        
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
        
        .form-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            padding-top: 20px;
            margin-top: 20px;
            border-top: 2px solid var(--border-color);
        }
        
        .alert {
            padding: 14px 20px;
            border-radius: var(--radius);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.9rem;
            border: 1px solid transparent;
        }
        .alert-success { background: var(--success-bg); color: var(--success); border-color: var(--success); }
        .alert-error { background: var(--danger-bg); color: var(--danger); border-color: var(--danger); }
        .alert-warning { background: var(--warning-bg); color: var(--warning); border-color: var(--warning); }
        .alert-info { background: var(--primary-bg); color: var(--primary); border-color: var(--primary); }
        
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
        }
        .toast-custom.show { transform: translateY(0); opacity: 1; }
        .toast-custom * { color: #ffffff !important; }
        .toast-custom .toast-icon { font-size: 1.5rem; flex-shrink: 0; }
        .toast-custom .toast-content { flex: 1; }
        .toast-custom .toast-content .toast-title { font-weight: 600; font-size: 0.85rem; margin: 0; }
        .toast-custom .toast-content .toast-message { font-size: 0.8rem; margin: 0; }
        .toast-custom .toast-close { background: none; border: none; font-size: 1.2rem; cursor: pointer; padding: 0 4px; }
        
        .footer {
            padding: 16px 0;
            border-top: 2px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
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
            top: 50%; left: 50%;
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
        }
        .lab-cart-item .btn-remove-cart:hover { background: var(--danger); color: #ffffff; }
        .lab-cart-empty { text-align: center; padding: 16px; color: var(--text-secondary); font-size: 0.85rem; }
        
        .medication-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-color);
        }
        .medication-item:last-child { border-bottom: none; }
        .medication-item:hover { background: var(--primary-bg); border-radius: var(--radius); }
        .medication-item-info { flex: 1; }
        .med-name { font-weight: 600; font-size: 0.9rem; color: var(--text-primary); }
        .med-details { font-size: 0.75rem; color: var(--text-secondary); display: block; }
        .med-qty { font-size: 0.7rem; color: var(--text-secondary); background: var(--gray-200); padding: 2px 12px; border-radius: 12px; margin-left: 8px; }
        .med-instruction-tag { font-size: 0.65rem; color: var(--primary); background: var(--primary-bg); padding: 1px 10px; border-radius: 12px; margin-left: 4px; border: 1px solid var(--primary-light); }
        .med-price { font-size: 0.8rem; font-weight: 600; color: var(--success); margin-left: 10px; }
        .med-status-dispensed { font-size: 0.6rem; background: var(--success-bg); color: var(--success); padding: 1px 10px; border-radius: 12px; margin-left: 6px; border: 1px solid var(--success); }
        .med-status-pending { font-size: 0.6rem; background: var(--warning-bg); color: var(--warning); padding: 1px 10px; border-radius: 12px; margin-left: 6px; border: 1px solid var(--warning); }
        .btn-remove {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: none;
            background: var(--danger-bg);
            color: var(--danger);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .btn-remove:hover { background: var(--danger); color: #ffffff; transform: scale(1.1); }
        
        .added-item-card {
            background: var(--bg-card);
            border-radius: 10px;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        .added-item-card:hover { border-color: var(--primary-light); background: var(--primary-bg); }
        .added-item-card .item-left { display: flex; align-items: center; gap: 12px; flex: 1; min-width: 0; flex-wrap: wrap; }
        .added-item-card .item-name { font-weight: 500; font-size: 0.85rem; color: var(--text-primary); }
        .added-item-card .item-details { font-size: 0.7rem; color: var(--text-secondary); }
        .added-item-card .item-price { font-weight: 600; color: var(--success); font-size: 0.85rem; }
        .added-item-card .item-free { color: var(--text-secondary); font-size: 0.7rem; font-weight: 500; background: var(--gray-200); padding: 2px 10px; border-radius: 12px; }
        .added-item-card .btn-remove-item {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            border: none;
            background: var(--danger-bg);
            color: var(--danger);
            cursor: pointer;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .added-item-card .btn-remove-item:hover { background: var(--danger); color: #ffffff; transform: scale(1.1); }
        
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
            box-shadow: 0 2px 8px rgba(11,94,215,0.2);
        }
        .section-total * { color: #ffffff !important; }
        .section-total .label { opacity: 0.8; font-weight: 400; }
        .section-total.green { background: linear-gradient(135deg, #059669, #10B981); }
        .section-total.purple { background: linear-gradient(135deg, #7C3AED, #8B5CF6); }
        .section-total.orange { background: linear-gradient(135deg, #D97706, #F59E0B); }
        
        .toggle-dropdown {
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            background: var(--bg-card);
        }
        .toggle-dropdown:hover { border-color: var(--primary-light); }
        .toggle-dropdown-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            cursor: pointer;
            user-select: none;
            background: var(--gray-50);
            border-radius: var(--radius);
        }
        .toggle-dropdown-header:hover { background: var(--primary-bg); }
        .toggle-dropdown-header .toggle-title { font-weight: 600; font-size: 0.85rem; color: var(--text-primary); display: flex; align-items: center; gap: 10px; }
        .toggle-dropdown-header .toggle-icon { color: var(--text-secondary); font-size: 0.8rem; transition: var(--transition); }
        .toggle-dropdown-header.active .toggle-icon { transform: rotate(180deg); }
        .toggle-dropdown-body { padding: 0 16px 16px 16px; display: none; background: var(--bg-card); border-radius: 0 0 var(--radius) var(--radius); }
        .toggle-dropdown-body.open { display: block; }
        
        .manual-disease-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            background: var(--primary-bg);
            border: 1px solid var(--primary);
            border-radius: 20px;
            font-size: 0.8rem;
            color: var(--text-primary);
        }
        .btn-remove-tag {
            background: none;
            border: none;
            color: var(--danger);
            cursor: pointer;
            font-size: 0.7rem;
            padding: 0 2px;
        }
        .btn-remove-tag:hover { transform: scale(1.2); }
        
        .toggle-section {
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            margin-bottom: 12px;
            overflow: hidden;
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
        }
        .toggle-header:hover { background: var(--primary-bg); }
        .toggle-header .toggle-title { font-weight: 600; font-size: 0.85rem; color: var(--text-primary); display: flex; align-items: center; gap: 10px; }
        .toggle-header .toggle-icon { color: var(--text-secondary); font-size: 0.8rem; transition: var(--transition); }
        .toggle-header.active .toggle-icon { transform: rotate(180deg); }
        .toggle-body { padding: 0 18px 18px 18px; display: none; background: var(--bg-card); }
        .toggle-body.open { display: block; }
        
        @media (max-width: 992px) {
            #diseaseCheckboxContainer .diseases-grid,
            #labTestsGrid, #medicationsGrid, #proceduresGrid, #equipmentGrid {
                grid-template-columns: repeat(2, 1fr) !important;
            }
            .bill-summary-grid { grid-template-columns: repeat(2, 1fr); }
            .vital-signs-grid { grid-template-columns: repeat(3, 1fr); }
            .row-2col { grid-template-columns: 1fr; }
        }
        
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px; }
            .page-header { flex-direction: column; }
            .form-actions { flex-direction: column; }
            .form-actions .btn { width: 100%; }
            .bill-summary-grid { grid-template-columns: 1fr 1fr; }
            .vital-signs-grid { grid-template-columns: repeat(3, 1fr); }
            .patient-info-grid { grid-template-columns: 1fr; }
            .chief-complaint-grid { grid-template-columns: 1fr; }
            .chief-complaint-grid .left-col,
            .chief-complaint-grid .right-col { grid-column: span 1; }
            #diseaseCheckboxContainer .diseases-grid,
            #labTestsGrid, #medicationsGrid, #proceduresGrid, #equipmentGrid {
                grid-template-columns: 1fr !important;
            }
        }
        
        @media (max-width: 480px) {
            .vital-signs-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media print {
            .top-nav, .sidebar, .btn, .btn-pdf, .footer, .no-print { display: none !important; }
            .main-content { margin: 0 !important; padding: 20px !important; }
            .consultation-card { border: 1px solid #ddd !important; box-shadow: none !important; page-break-inside: avoid; }
            .vital-sign-item { border: 1px solid #ddd !important; break-inside: avoid; }
            .vital-sign-item.spo2-item { 
                background: #E0F2FE !important; 
                -webkit-print-color-adjust: exact; 
                print-color-adjust: exact; 
            }
        }
    </style>
</head>
<body>

<main class="main-content <?= $is_completed ? 'view-mode' : '' ?>">

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

    <!-- STATUS FLOW -->
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
                Consultation saved. Auto-complete will trigger automatically when bills are fully paid.
            <?php elseif ($visit_status === 'completed'): ?>
                ✅ Consultation completed successfully!
            <?php endif; ?>
        </div>
    </div>

    <!-- BILL SUMMARY -->
    <div class="consultation-card mb-6">
        <h3 class="card-title"><i class="fas fa-receipt"></i> Bill Summary</h3>
        <div class="bill-summary-grid" id="billSummaryGrid">
            <div class="bill-summary-card total-card">
                <div class="bill-summary-icon"><i class="fas fa-file-invoice"></i></div>
                <div class="bill-summary-content">
                    <span class="bill-summary-label">Total Amount</span>
                    <span class="bill-summary-value" id="totalAmountDisplay">TSh <?= number_format($bill_total, 0) ?></span>
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
            <div class="bill-summary-card premium-card" style="<?= $total_premium > 0 ? '' : 'opacity: 0.5;' ?>">
                <div class="bill-summary-icon"><i class="fas fa-crown"></i></div>
                <div class="bill-summary-content">
                    <span class="bill-summary-label">Premium Amount</span>
                    <span class="bill-summary-value" id="premiumAmountDisplay">TSh <?= number_format($total_premium, 0) ?></span>
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
                <?php if ($total_premium > 0): ?>
                    <span style="color:#D97706;font-weight:600;margin-left:8px;">👑 Premium: TSh <?= number_format($total_premium, 0) ?></span>
                <?php endif; ?>
            </span>
            <span style="font-size:0.75rem;color:var(--text-secondary);">
                <i class="far fa-clock"></i> Last updated: <span id="billLastUpdated"><?= date('H:i:s') ?></span>
            </span>
        </div>
    </div>

    <!-- PATIENT & VISIT INFO -->
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
                <div><span style="display:block;font-size:0.65rem;color:var(--text-secondary);font-weight:500;text-transform:uppercase;">Visit Number</span><span style="display:block;font-size:0.9rem;font-weight:500;color:var(--text-primary);font-family:monospace;"><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></span></div>
                <div><span style="display:block;font-size:0.65rem;color:var(--text-secondary);font-weight:500;text-transform:uppercase;">Visit Type</span><span style="display:block;font-size:0.9rem;font-weight:500;color:var(--text-primary);"><?= ucfirst($visit['visit_type'] ?? 'New') ?></span></div>
                <div><span style="display:block;font-size:0.65rem;color:var(--text-secondary);font-weight:500;text-transform:uppercase;">Date</span><span style="display:block;font-size:0.9rem;font-weight:500;color:var(--text-primary);"><?= date('M d, Y', strtotime($visit['created_at'] ?? 'now')) ?></span></div>
                <div><span style="display:block;font-size:0.65rem;color:var(--text-secondary);font-weight:500;text-transform:uppercase;">Doctor</span><span style="display:block;font-size:0.9rem;font-weight:500;color:var(--text-primary);">Dr. <?= htmlspecialchars($visit['doctor_name'] ?? 'Not Assigned') ?></span></div>
                <div><span style="display:block;font-size:0.65rem;color:var(--text-secondary);font-weight:500;text-transform:uppercase;">Specialty</span><span style="display:block;font-size:0.9rem;font-weight:500;color:var(--text-primary);"><?= htmlspecialchars($visit['doctor_specialty'] ?? 'N/A') ?></span></div>
                <div><span style="display:block;font-size:0.65rem;color:var(--text-secondary);font-weight:500;text-transform:uppercase;">Branch</span><span style="display:block;font-size:0.9rem;font-weight:500;color:var(--text-primary);"><?= htmlspecialchars($visit['branch_name'] ?? $doctor_branch_name) ?></span></div>
            </div>
        </div>
    </div>

    <!-- VITAL SIGNS -->
    <div class="consultation-card mb-6">
        <h3 class="card-title">
            <i class="fas fa-heartbeat"></i> Vital Signs (7 Signs)
            <span style="font-size:0.7rem;font-weight:400;color:#0284C7;">🫁 SpO2 Normal: 95-100%</span>
        </h3>
        <?php if ($vital_signs): 
            $spo2_status = getSpO2Status($vital_signs['oxygen_saturation'] ?? null);
        ?>
            <div class="vital-signs-grid" style="margin-bottom:12px;">
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
                <div class="vital-sign-item spo2-item">
                    <span class="vital-icon">🫁</span>
                    <span class="vital-label">Oxygen (SpO2)</span>
                    <span class="vital-value"><?= $vital_signs['oxygen_saturation'] ?? '--' ?> <span class="vital-unit">%</span></span>
                    <?php if (!empty($vital_signs['oxygen_saturation'])): ?>
                        <span class="vital-status spo2-<?= strtolower($spo2_status['label']) ?>"><?= $spo2_status['label'] ?></span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="vital-signs-grid">
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
                <div style="visibility:hidden;"></div>
            </div>
            
            <div class="vital-signs-footer">
                <i class="fas fa-lungs" style="color:#0EA5E9;"></i>
                <span style="color:#0284C7;">SpO2 (Oxygen Saturation) Normal Range: <strong>95-100%</strong></span>
                <span style="color:#64748B;"> • 7 Vital Signs Tracked</span>
            </div>
            
            <?php if (!empty($vital_signs['notes'])): ?>
                <div class="mt-2 text-sm" style="color:var(--text-secondary);padding:6px 12px;background:var(--bg-body);border-radius:6px;border-left:3px solid var(--primary);">
                    <strong>Notes:</strong> <?= htmlspecialchars($vital_signs['notes']) ?>
                </div>
            <?php endif; ?>
            <div class="mt-2 text-xs" style="color:var(--text-secondary);">
                <i class="fas fa-user"></i> Recorded by: <?= htmlspecialchars($vital_signs['recorded_by_name'] ?? 'N/A') ?>
                <?php if (!empty($vital_signs['recorded_at'])): ?>
                    <span class="mx-2">|</span>
                    <i class="fas fa-clock"></i> <?= date('M d, Y h:i A', strtotime($vital_signs['recorded_at'])) ?>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="empty-state"><i class="fas fa-heartbeat"></i><p>No vital signs recorded</p></div>
        <?php endif; ?>
    </div>

    <?php if (!$is_completed): ?>
    
    <form method="POST" action="consultation.php?visit_id=<?= $visit_id ?>" id="consultationForm">
    
    <!-- CHIEF COMPLAINT & HISTORY -->
    <div class="consultation-card mb-6">
        <h3 class="card-title">
            <i class="fas fa-list-ul"></i> Chief Complaint & History
            <?php if ($sections_frozen && !$is_waiting): ?>
                <span class="frozen-badge">🔒 Frozen - Lab Pending</span>
            <?php elseif ($lab_results_available && !$is_waiting): ?>
                <span class="frozen-badge success">✅ Results Available - Unlocked</span>
            <?php endif; ?>
        </h3>
        
        <div class="chief-complaint-grid">
            <div class="left-col">
                <div class="form-group">
                    <label class="form-label">Additional Notes</label>
                    <textarea name="notes" class="form-control" rows="4" placeholder="Additional notes..." id="notesTextarea" <?= $sections_frozen && !$is_waiting ? 'disabled' : '' ?>><?= htmlspecialchars($visit['notes'] ?? '') ?></textarea>
                </div>
            </div>
            
            <div class="right-col">
                <div class="form-group">
                    <label class="form-label">Chief Complaint <span class="required">*</span></label>
                    <select class="form-control" id="complaintSelect" onchange="addComplaintOnSelect()">
                        <option value="">-- Select Common Complaint --</option>
                        <?php foreach ($common_complaints as $complaint): ?>
                            <option value="<?= htmlspecialchars($complaint) ?>"><?= htmlspecialchars($complaint) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="mt-2">
                        <textarea name="symptoms" class="form-control" rows="3" placeholder="Complaints..." id="symptomsTextarea" <?= $sections_frozen && !$is_waiting ? 'disabled' : '' ?> oninput="updateComplaints()"><?= htmlspecialchars($visit['symptoms'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
            
            <div class="left-col">
                <div class="form-group">
                    <label class="form-label">History of Presenting Illness (HPI)</label>
                    <textarea name="hpi" class="form-control" rows="4" placeholder="Describe HPI..." id="hpiTextarea" <?= $sections_frozen && !$is_waiting ? 'disabled' : '' ?>><?= htmlspecialchars($visit['hpi'] ?? '') ?></textarea>
                </div>
            </div>
            
            <div class="right-col">
                <div class="form-group">
                    <label class="form-label">Physical Examination</label>
                    <textarea name="physical_exam" class="form-control" rows="4" placeholder="Physical exam..." id="physicalExamTextarea" <?= $sections_frozen && !$is_waiting ? 'disabled' : '' ?>><?= htmlspecialchars($visit['physical_exam'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- LAB TESTS SECTION -->
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
        </div>
        
        <div class="toggle-dropdown" style="margin-bottom:12px;">
            <div class="toggle-dropdown-header" onclick="toggleLabDropdown()">
                <span class="toggle-title">
                    <i class="fas fa-flask"></i> Select Lab Tests
                    <span style="font-size:0.7rem;color:var(--text-secondary);font-weight:400;">(Click to expand)</span>
                </span>
                <span class="toggle-icon"><i class="fas fa-chevron-down"></i></span>
            </div>
            <div class="toggle-dropdown-body" id="labDropdownBody">
                <div style="margin-bottom:10px;margin-top:10px;">
                    <input type="text" class="form-control" id="labTestSearch" placeholder="🔍 Search lab tests..." oninput="filterLabTestsInside()">
                </div>
                <div id="labTestsGrid">
                    <?php if (count($lab_tests_catalog) > 0): ?>
                        <?php foreach ($lab_tests_catalog as $test): 
                            $has_equipment = !empty($test['equipment_id']);
                            $equipment_stock = (int)($test['equipment_stock'] ?? 0);
                            $equipment_name = htmlspecialchars($test['linked_equipment_name'] ?? '');
                            $equipment_used = (int)($test['equipment_quantity_used'] ?? 1);
                            $is_out_of_stock = $has_equipment && $equipment_stock < $equipment_used;
                            $stock_class = '';
                            if ($has_equipment) {
                                if ($equipment_stock <= 0) $stock_class = 'stock-out';
                                elseif ($equipment_stock < 10) $stock_class = 'stock-low';
                                else $stock_class = 'stock-ok';
                            }
                        ?>
                            <div class="lab-test-item-select <?= $is_out_of_stock ? 'out-of-stock' : '' ?>" 
                                 data-test-id="<?= $test['id'] ?>"
                                 data-test-name="<?= htmlspecialchars($test['test_name']) ?>"
                                 data-price="<?= $test['price'] ?>"
                                 data-equipment-id="<?= $test['equipment_id'] ?? '' ?>"
                                 data-equipment-name="<?= $equipment_name ?>"
                                 data-equipment-stock="<?= $equipment_stock ?>"
                                 data-equipment-used="<?= $equipment_used ?>"
                                 onclick="toggleLabTestCheckbox(this)">
                                <span class="item-check"><i class="fas fa-check"></i></span>
                                <div class="lab-test-info">
                                    <span class="lab-test-name"><?= htmlspecialchars($test['test_name']) ?></span>
                                    <div class="lab-test-details">
                                        <?php if (!empty($test['category'])): ?>
                                            <span class="lab-test-category"><?= htmlspecialchars($test['category']) ?></span>
                                        <?php endif; ?>
                                        <span class="lab-test-price">TSh <?= number_format($test['price'], 0) ?></span>
                                        <?php if ($has_equipment): ?>
                                            <span class="lab-test-equipment">🔗 <?= $equipment_name ?> (<?= $equipment_stock ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state" style="grid-column:span 2;"><i class="fas fa-flask"></i><p>No lab tests available</p></div>
                    <?php endif; ?>
                </div>
                <div class="mt-2">
                    <button type="button" class="btn btn-primary" onclick="addSelectedLabTests()" id="addLabToCartBtn">
                        <i class="fas fa-cart-plus"></i> Add Selected to Cart
                    </button>
                    <span style="font-size:0.75rem;color:var(--text-secondary);margin-left:8px;" id="labSelectedCount">Selected: 0</span>
                </div>
            </div>
        </div>
        
        <div style="background:var(--gray-50);border-radius:var(--radius);padding:16px;border:1px solid var(--border-color);">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                <h4 style="font-size:0.875rem;font-weight:600;color:var(--text-secondary);margin:0;">
                    <i class="fas fa-shopping-cart"></i> Lab Cart
                    <span style="font-size:0.75rem;" id="labCartCount">(<?= $lab_cart_count ?> items)</span>
                </h4>
                <span style="font-size:0.875rem;font-weight:700;color:#D97706;">Total: TSh <span id="labCartTotal"><?= number_format($lab_cart_total, 0) ?></span></span>
            </div>
            <div class="lab-cart-items" id="labCartItems">
                <?php if ($lab_cart_count > 0): ?>
                    <?php foreach ($lab_cart as $item): ?>
                        <div class="lab-cart-item" id="lab-cart-<?= $item['id'] ?>" data-test-id="<?= $item['id'] ?>">
                            <span class="cart-item-name"><?= htmlspecialchars($item['name']) ?></span>
                            <span class="cart-item-price">TSh <?= number_format($item['price'], 0) ?></span>
                            <button type="button" class="btn-remove-cart" onclick="removeLabTestFromCart(<?= $item['id'] ?>)"><i class="fas fa-times"></i></button>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="lab-cart-empty">No tests in cart.</div>
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
        </div>
        
        <div class="mt-3" id="sentTestsContainer">
            <?php if (count($lab_requests) > 0): ?>
                <h5 style="font-size:0.875rem;font-weight:600;color:var(--text-secondary);margin-bottom:8px;"><i class="fas fa-history"></i> Sent Tests</h5>
                <div id="sentTestsList">
                    <?php foreach ($lab_requests as $lab): ?>
                        <div style="display:flex;gap:10px;margin-bottom:6px;align-items:center;padding:6px 12px;background:var(--gray-50);border-radius:var(--radius);border:1px solid var(--border-color);" id="sent-test-<?= $lab['id'] ?>">
                            <div style="flex:1;">
                                <span style="font-weight:500;font-size:0.85rem;"><?= htmlspecialchars($lab['test_name']) ?></span>
                                <span style="font-size:0.75rem;color:var(--text-secondary);margin-left:8px;">- TSh <?= number_format($lab['test_price'] ?? 0, 0) ?></span>
                            </div>
                            <span class="status-badge badge-warning" style="font-size:0.6rem;" id="sent-status-<?= $lab['id'] ?>">⏳ <?= ucfirst($lab['status'] ?? 'Pending') ?></span>
                            <?php if ($lab['status'] !== 'completed'): ?>
                                <button type="button" class="btn-remove-cart" onclick="removeLabTest(<?= $lab['id'] ?>)" title="Remove test"><i class="fas fa-times"></i></button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- LAB RESULTS -->
    <div class="consultation-card mb-6" id="labResultsCard">
        <h3 class="card-title">
            <i class="fas fa-file-medical-alt"></i> Laboratory Results
            <span class="frozen-badge <?= $lab_results_available ? 'success' : '' ?>" id="resultsBadge" style="<?= ($lab_results_available || $has_active_lab) ? '' : 'display:none;' ?>">
                <?php if ($lab_results_available): ?>✅ Results Available
                <?php elseif ($has_active_lab): ?>⏳ Pending Results<?php endif; ?>
            </span>
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
                                    <td style="padding:10px 14px;border-bottom:1px solid var(--border-color);"><?= htmlspecialchars($result['test_name'] ?? 'N/A') ?></td>
                                    <td style="padding:10px 14px;border-bottom:1px solid var(--border-color);font-weight:600;color:var(--success);"><?= htmlspecialchars($result['results'] ?? 'N/A') ?></td>
                                    <td style="padding:10px 14px;border-bottom:1px solid var(--border-color);"><span class="status-badge badge-success">✅ Completed</span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif ($has_active_lab): ?>
                <div style="text-align:center;padding:24px 0;color:#D97706;">
                    <i class="fas fa-clock" style="font-size:1.5rem;display:block;margin-bottom:8px;"></i>
                    <p><?= count($lab_requests) ?> lab request(s) in progress</p>
                </div>
            <?php else: ?>
                <div style="text-align:center;padding:24px 0;color:var(--text-secondary);">
                    <i class="fas fa-flask" style="font-size:1.5rem;display:block;margin-bottom:8px;"></i>
                    <p>No lab results available</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- FROZEN SECTIONS CONTAINER -->
    <div id="frozenSectionsContainer" class="<?= ($sections_frozen && !$is_waiting) ? 'frozen-overlay-active' : '' ?>">

        <!-- DIAGNOSIS -->
        <div class="consultation-card mb-6" id="diagnosisCard">
            <h3 class="card-title">
                <i class="fas fa-diagnoses"></i> Diagnosis
                <span style="font-size:0.7rem;font-weight:400;color:var(--text-secondary);">(Select multiple diseases)</span>
                <?php if ($sections_frozen && !$is_waiting): ?>
                    <span class="frozen-badge">🔒 Frozen</span>
                <?php elseif ($lab_results_available && !$is_waiting): ?>
                    <span class="frozen-badge success">✅ Unlocked</span>
                <?php endif; ?>
                <span id="diagnosisStatus" style="font-size:0.65rem;font-weight:400;color:var(--text-secondary);">
                    <?php if (!empty($visit['diagnosis'])): ?>
                        ✅ Saved: <?= htmlspecialchars($visit['diagnosis']) ?>
                    <?php else: ?>
                        ⚠️ Diagnosis required
                    <?php endif; ?>
                </span>
            </h3>
            
            <div class="toggle-dropdown" style="margin-bottom:16px;">
                <div class="toggle-dropdown-header" onclick="toggleDiseaseDropdown()">
                    <span class="toggle-title">
                        <i class="fas fa-list"></i> Select Diseases
                        <span style="font-size:0.7rem;color:var(--text-secondary);font-weight:400;">(Click to expand)</span>
                    </span>
                    <span class="toggle-icon"><i class="fas fa-chevron-down"></i></span>
                </div>
                <div class="toggle-dropdown-body" id="diseaseDropdownBody">
                    <div style="margin-bottom:10px;margin-top:10px;">
                        <input type="text" class="form-control" id="diseaseSearch" placeholder="🔍 Search diseases..." oninput="filterDiseasesInside()">
                    </div>
                    <div id="diseaseCheckboxContainer" style="max-height:300px;overflow-y:auto;">
                        <div class="diseases-grid">
                            <?php foreach ($diseases_list as $disease): ?>
                                <label class="disease-checkbox-item" data-disease-name="<?= strtolower(htmlspecialchars($disease['disease_name'])) ?>">
                                    <input type="checkbox" name="diagnosis_ids[]" value="<?= $disease['id'] ?>" 
                                           <?= in_array($disease['id'], $selected_disease_ids) ? 'checked' : '' ?>
                                           <?= ($sections_frozen && !$is_waiting) ? 'disabled' : '' ?>
                                           onchange="saveDiseasesToVisit()">
                                    <span class="disease-name"><?= htmlspecialchars($disease['disease_name']) ?></span>
                                    <?php if (!empty($disease['disease_code'])): ?>
                                        <span class="disease-code"><?= htmlspecialchars($disease['disease_code']) ?></span>
                                    <?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-group mt-3">
                <label class="form-label">Manual Disease Entry</label>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <input type="text" class="form-control" id="manualDiseaseInput" placeholder="Enter disease name..." style="flex:1;min-width:200px;" <?= ($sections_frozen && !$is_waiting) ? 'disabled' : '' ?>>
                    <input type="text" class="form-control" id="manualDiseaseCodeInput" placeholder="Code (optional - kama unaijaza itatumika, kama haipo ita-generate)" style="flex:0.7;min-width:200px;" <?= ($sections_frozen && !$is_waiting) ? 'disabled' : '' ?>>
                    <button type="button" class="btn btn-primary" onclick="addManualDisease()" <?= ($sections_frozen && !$is_waiting) ? 'disabled' : '' ?>>
                        <i class="fas fa-plus"></i> Add
                    </button>
                </div>
                <div id="manualDiseasesContainer" class="mt-2" style="display:flex;flex-wrap:wrap;gap:8px;">
                    <?php foreach ($manual_diseases_saved as $idx => $manual_disease): ?>
                        <?php $manual_disease = trim($manual_disease); ?>
                        <?php if (!empty($manual_disease)): 
                            $saved_code = $saved_codes_array[$idx] ?? '';
                        ?>
                            <span class="manual-disease-tag" data-disease="<?= htmlspecialchars($manual_disease) ?>" <?= $saved_code ? 'data-disease-code="' . htmlspecialchars($saved_code) . '"' : '' ?>>
                                <i class="fas fa-user-md" style="color:var(--primary);font-size:0.6rem;"></i>
                                <?= htmlspecialchars($manual_disease) ?>
                                <?php if ($saved_code): ?>
                                    <small style="font-family:monospace;background:var(--gray-200);padding:0 6px;border-radius:6px;font-size:0.65rem;"><?= htmlspecialchars($saved_code) ?></small>
                                <?php endif; ?>
                                <button type="button" class="btn-remove-tag" onclick="removeManualDisease(this, '<?= htmlspecialchars($manual_disease) ?>')">×</button>
                                <input type="hidden" name="manual_diseases[]" value="<?= htmlspecialchars($manual_disease) ?>">
                                <?php if ($saved_code): ?>
                                    <input type="hidden" name="manual_disease_codes[]" value="<?= htmlspecialchars($saved_code) ?>" data-for="<?= htmlspecialchars($manual_disease) ?>">
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Treatment Plan</label>
                <textarea name="treatment" class="form-control" rows="3" placeholder="Treatment plan..." <?= ($sections_frozen && !$is_waiting) ? 'disabled' : '' ?> id="treatmentTextarea"><?= htmlspecialchars($visit['treatment'] ?? '') ?></textarea>
            </div>
            
            <div id="diagnosisAutoSaveStatus" style="font-size:0.7rem;color:var(--text-secondary);margin-top:8px;display:flex;align-items:center;gap:8px;">
                <span id="diagnosisSaveIndicator" style="display:none;"><i class="fas fa-spinner fa-spin"></i> Saving...</span>
                <span id="diagnosisSavedIndicator" style="display:none;color:var(--success);"><i class="fas fa-check-circle"></i> Data saved</span>
            </div>
        </div>

        <!-- MEDICATIONS -->
        <div class="consultation-card mb-6" id="medicationsCard">
            <h3 class="card-title">
                <i class="fas fa-prescription"></i> Medications
                <?php if ($sections_frozen && !$is_waiting): ?>
                    <span class="frozen-badge">🔒 Frozen</span>
                <?php endif; ?>
                <span class="section-total green">
                    <span class="label">💊 Total:</span>
                    <span class="amount">TSh <span id="medTotalDisplay"><?= number_format($medications_total, 0) ?></span></span>
                </span>
            </h3>
            
            <div style="background:var(--gray-50);border-radius:var(--radius);padding:20px;border:1px solid var(--border-color);">
                
                <div class="toggle-dropdown" style="margin-bottom:16px;">
                    <div class="toggle-dropdown-header" onclick="toggleMedicationDropdown()">
                        <span class="toggle-title">
                            <i class="fas fa-pills"></i> Select Medication
                            <span style="font-size:0.7rem;color:var(--text-secondary);font-weight:400;">(Click to expand)</span>
                        </span>
                        <span class="toggle-icon"><i class="fas fa-chevron-down"></i></span>
                    </div>
                    <div class="toggle-dropdown-body" id="medicationDropdownBody">
                        <div style="margin-bottom:10px;margin-top:10px;">
                            <input type="text" class="form-control" id="medicationSearch" placeholder="🔍 Search medications..." oninput="filterMedicationsInside()">
                        </div>
                        <div id="medicationsGrid">
                            <?php foreach ($medications_grouped as $key => $group): 
                                $total_stock = $group['total_quantity'];
                                $batch_count = count($group['batches']);
                                $first_batch = $group['batches'][0];
                            ?>
                                <div class="medication-item-select" 
                                     data-inventory-id="<?= $first_batch['id'] ?>"
                                     data-medication-name="<?= htmlspecialchars($group['name']) ?>"
                                     data-price="<?= $group['selling_price'] ?>"
                                     data-stock="<?= $total_stock ?>"
                                     data-category="<?= htmlspecialchars($group['category']) ?>"
                                     data-batches="<?= $batch_count ?>"
                                     onclick="toggleMedicationCheckbox(this)">
                                    <span class="item-check"><i class="fas fa-check"></i></span>
                                    <div class="medication-info">
                                        <span class="medication-name"><?= htmlspecialchars($group['name']) ?></span>
                                        <div class="medication-details">
                                            <span class="medication-price">TSh <?= number_format($group['selling_price'] ?? 0, 0) ?></span>
                                            <span class="medication-stock">Stock: <?= $total_stock ?></span>
                                            <?php if (!empty($group['category'])): ?>
                                                <span class="medication-category"><?= htmlspecialchars($group['category']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-2">
                            <span style="font-size:0.75rem;color:var(--text-secondary);" id="medSelectedInfo">Selected: None</span>
                        </div>
                    </div>
                </div>
                
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group">
                        <label class="form-label">Quantity</label>
                        <input type="number" id="medQuantity" class="form-control" value="1" min="1" max="999" <?= ($sections_frozen && !$is_waiting) ? 'disabled' : '' ?>>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Dosage</label>
                        <input type="text" id="medDosage" class="form-control" placeholder="e.g. 500mg" <?= ($sections_frozen && !$is_waiting) ? 'disabled' : '' ?>>
                    </div>
                </div>
                
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px;">
                    <div class="form-group">
                        <label class="form-label">Frequency <span class="required">*</span></label>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <select id="medFrequency" class="form-control" style="flex:1;" <?= ($sections_frozen && !$is_waiting) ? 'disabled' : '' ?>>
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
                            </select>
                            <input type="text" id="medFrequencyManual" class="form-control" placeholder="Other..." style="flex:1;" <?= ($sections_frozen && !$is_waiting) ? 'disabled' : '' ?>>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Duration (Days)</label>
                        <input type="number" id="medDuration" class="form-control" value="7" min="1" max="90" <?= ($sections_frozen && !$is_waiting) ? 'disabled' : '' ?>>
                    </div>
                </div>
                
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px;">
                    <div class="form-group">
                        <label class="form-label">Route <span class="required">*</span></label>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <select id="medRoute" class="form-control" style="flex:1;" <?= ($sections_frozen && !$is_waiting) ? 'disabled' : '' ?>>
                                <option value="">Select Route</option>
                                <option value="Oral">Oral</option>
                                <option value="Topical">Topical</option>
                                <option value="Injection">Injection</option>
                                <option value="IV">IV</option>
                                <option value="IM">IM</option>
                                <option value="Sublingual">Sublingual</option>
                                <option value="Inhalation">Inhalation</option>
                                <option value="Nasal">Nasal</option>
                                <option value="Ophthalmic">Ophthalmic</option>
                                <option value="Otic">Otic</option>
                                <option value="Rectal">Rectal</option>
                            </select>
                            <input type="text" id="medRouteManual" class="form-control" placeholder="Other..." style="flex:1;" <?= ($sections_frozen && !$is_waiting) ? 'disabled' : '' ?>>
                        </div>
                    </div>
                </div>
                
                <div class="form-group mt-3">
                    <label class="form-label">Instructions</label>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:6px;">
                        <button type="button" class="btn btn-outline btn-sm" onclick="addInstruction('Take after meals')">After Meals</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="addInstruction('Take before meals')">Before Meals</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="addInstruction('Take with plenty of water')">With Water</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="addInstruction('Take at bedtime')">At Bedtime</button>
                    </div>
                    <textarea id="medInstructions" class="form-control" rows="2" placeholder="Instructions..." <?= ($sections_frozen && !$is_waiting) ? 'disabled' : '' ?>></textarea>
                </div>
                
                <div class="mt-3">
                    <button type="button" class="btn btn-primary" onclick="addMedicationAjax()" id="addMedicationBtn" <?= ($sections_frozen && !$is_waiting) ? 'disabled' : '' ?>>
                        <i class="fas fa-plus"></i> Add Medication
                    </button>
                </div>
            </div>
            
            <div class="selected-medications mt-4" style="background:var(--gray-50);border-radius:var(--radius);padding:16px 20px;border:1px solid var(--border-color);">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                    <h4 style="font-size:0.875rem;font-weight:600;color:var(--text-secondary);margin:0;">
                        <i class="fas fa-list"></i> Prescribed Medications
                        <span style="font-size:0.75rem;" id="medCount">(<?= count($valid_prescriptions) ?> items)</span>
                    </h4>
                    <span style="font-size:0.875rem;font-weight:700;color:var(--success);">Total: TSh <span id="medListTotal"><?= number_format($medications_total, 0) ?></span></span>
                </div>
                <div id="medicationsList">
                    <?php if (count($valid_prescriptions) > 0): ?>
                        <?php foreach ($valid_prescriptions as $med): ?>
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
                                        <span class="med-status-pending">⏳ Pending</span>
                                    <?php endif; ?>
                                </div>
                                <?php if (($med['status'] ?? '') !== 'dispensed'): ?>
                                    <button type="button" class="btn-remove" onclick="removeMedication(<?= $med['id'] ?>)" title="Remove">
                                        <i class="fas fa-times"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state" id="emptyMedications"><i class="fas fa-prescription"></i><p>No medications prescribed yet</p></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- PROCEDURES & EQUIPMENT -->
        <div class="consultation-card mb-6" id="proceduresEquipmentCard">
            <h3 class="card-title">
                <i class="fas fa-syringe"></i> Procedures & Equipment
                <?php if ($sections_frozen && !$is_waiting): ?>
                    <span class="frozen-badge">🔒 Frozen</span>
                <?php endif; ?>
                <span class="section-total purple">
                    <span class="label">🛠️ Total:</span>
                    <span class="amount">TSh <span id="procEquipTotalDisplay"><?= number_format($procedure_total + $equipment_total, 0) ?></span></span>
                </span>
            </h3>
            
            <div class="toggle-section">
                <div class="toggle-header" onclick="toggleSection('proceduresToggle')">
                    <span class="toggle-title"><i class="fas fa-syringe"></i> Procedures</span>
                    <span class="toggle-icon"><i class="fas fa-chevron-down"></i></span>
                </div>
                <div class="toggle-body" id="proceduresToggle">
                    <div id="proceduresGrid">
                        <?php foreach ($procedures_list as $proc): ?>
                            <div class="procedure-item-select" 
                                 data-procedure-id="<?= $proc['id'] ?>"
                                 data-procedure-name="<?= htmlspecialchars($proc['procedure_name']) ?>"
                                 data-price="<?= $proc['price'] ?>"
                                 onclick="toggleProcedure(this)">
                                <span class="item-check"><i class="fas fa-check"></i></span>
                                <div class="procedure-info">
                                    <span class="procedure-name"><?= htmlspecialchars($proc['procedure_name']) ?></span>
                                    <span class="procedure-price <?= ($proc['price'] ?? 0) > 0 ? 'paid' : 'free' ?>">
                                        <?= ($proc['price'] ?? 0) > 0 ? 'TSh ' . number_format($proc['price'], 0) : 'FREE' ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button type="button" class="btn btn-primary btn-sm" onclick="addSelectedProcedures()" <?= ($sections_frozen && !$is_waiting) ? 'disabled' : '' ?>>
                            <i class="fas fa-plus"></i> Add Selected Procedures
                        </button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="clearProcedureSelections()">
                            <i class="fas fa-times"></i> Clear
                        </button>
                        <span style="font-size:0.75rem;color:var(--text-secondary);align-self:center;" id="procSelectedCount">Selected: 0</span>
                    </div>
                </div>
            </div>
            
            <div class="toggle-section">
                <div class="toggle-header" onclick="toggleSection('equipmentToggle')">
                    <span class="toggle-title"><i class="fas fa-tools"></i> Medical Equipment</span>
                    <span class="toggle-icon"><i class="fas fa-chevron-down"></i></span>
                </div>
                <div class="toggle-body" id="equipmentToggle">
                    <div id="equipmentGrid">
                        <?php foreach ($equipment_list as $eq): 
                            $expiry_status = 'no-expiry';
                            $expiry_label = 'No Expiry';
                            $expiry_date = $eq['expiry_date'] ?? '';
                            
                            if (!empty($expiry_date) && $expiry_date !== '0000-00-00') {
                                $expiry_timestamp = strtotime($expiry_date);
                                $days_remaining = floor(($expiry_timestamp - time()) / 86400);
                                if ($days_remaining < 0) { $expiry_status = 'expired'; $expiry_label = 'EXPIRED'; }
                                elseif ($days_remaining <= 30) { $expiry_status = 'expiring'; $expiry_label = 'Expires: ' . date('d/m/Y', $expiry_timestamp); }
                                else { $expiry_status = 'valid'; $expiry_label = 'Expires: ' . date('d/m/Y', $expiry_timestamp); }
                            }
                            
                            $stock_status = '';
                            if ($eq['quantity'] <= 0) $stock_status = 'out';
                            elseif ($eq['quantity'] <= 10) $stock_status = 'low';
                        ?>
                            <div class="equipment-item-select" 
                                 data-equipment-id="<?= $eq['id'] ?>"
                                 data-equipment-name="<?= htmlspecialchars($eq['equipment_name']) ?>"
                                 data-price="<?= $eq['selling_price'] ?? 0 ?>"
                                 data-stock="<?= $eq['quantity'] ?>"
                                 data-batch="<?= htmlspecialchars($eq['batch_number'] ?? '') ?>"
                                 data-expiry="<?= $expiry_date ?>"
                                 onclick="toggleEquipment(this)">
                                <span class="item-check"><i class="fas fa-check"></i></span>
                                <div class="equipment-info">
                                    <span class="equipment-name"><?= htmlspecialchars($eq['equipment_name']) ?></span>
                                    <div class="equipment-details">
                                        <span class="equipment-price <?= ($eq['selling_price'] ?? 0) > 0 ? 'paid' : 'free' ?>">
                                            <?= ($eq['selling_price'] ?? 0) > 0 ? 'TSh ' . number_format($eq['selling_price'], 0) : 'FREE' ?>
                                        </span>
                                        <span class="equipment-stock <?= $stock_status ?>">Stock: <?= $eq['quantity'] ?></span>
                                        <span class="equipment-expiry <?= $expiry_status ?>"><?= $expiry_label ?></span>
                                    </div>
                                </div>
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
                            <i class="fas fa-times"></i> Clear
                        </button>
                        <span style="font-size:0.75rem;color:var(--text-secondary);align-self:center;" id="equipSelectedCount">Selected: 0</span>
                    </div>
                </div>
            </div>
            
            <div class="selected-items mt-4" style="background:var(--gray-50);border-radius:var(--radius);padding:16px 20px;border:1px solid var(--border-color);">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                    <h4 style="font-size:0.875rem;font-weight:600;color:var(--text-secondary);margin:0;">
                        <i class="fas fa-list"></i> Added Procedures & Equipment
                        <span style="font-size:0.75rem;" id="addedCount">(<?= count($procedures) + count($equipment_items_display) ?> items)</span>
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
                                    <button type="button" class="btn-remove-item" onclick="removeAddedItem('procedure', <?= $proc['id'] ?>)" title="Remove">
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
                                    <button type="button" class="btn-remove-item" onclick="removeAddedItem('equipment', <?= $eq_item['id'] ?>)" title="Remove">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state" id="emptyAdded"><i class="fas fa-syringe"></i><p>No procedures or equipment added yet</p></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

    <!-- FORM ACTIONS -->
    <div class="consultation-card">
        <div class="form-actions">
            <button type="submit" name="save_consultation" class="btn btn-success" id="saveConsultationBtn" <?= ($sections_frozen && !$is_waiting) ? 'disabled' : '' ?>>
                <i class="fas fa-save"></i> Save Consultation → Change to WAITING
            </button>
            <?php if ($sections_frozen && !$is_waiting): ?>
                <span style="font-size:0.75rem;color:var(--danger);align-self:center;">
                    <i class="fas fa-lock"></i> Actions frozen - Lab tests pending
                </span>
            <?php elseif ($lab_results_available || $is_waiting): ?>
                <span style="font-size:0.75rem;color:var(--success);align-self:center;">
                    <i class="fas fa-check-circle"></i> <?= $is_waiting ? 'Auto-complete will trigger when bills are fully paid.' : 'Lab results available - All actions unlocked' ?>
                </span>
            <?php endif; ?>
            <a href="my_patients.php" class="btn btn-outline">
                <i class="fas fa-times"></i> Cancel
            </a>
        </div>
    </div>

    </form>

    <?php else: ?>
    
    <!-- COMPLETED VIEW -->
    <div class="consultation-card mb-6">
        <h3 class="card-title"><i class="fas fa-list-ul"></i> Chief Complaint & History</h3>
        <div class="row-2col">
            <div class="form-group">
                <label class="form-label">Chief Complaint</label>
                <div class="form-control" style="min-height:60px;background:var(--gray-50);">
                    <?= nl2br(htmlspecialchars($visit['symptoms'] ?? 'No complaint recorded')) ?>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Notes</label>
                <div class="form-control" style="min-height:60px;background:var(--gray-50);">
                    <?= nl2br(htmlspecialchars($visit['notes'] ?? 'No notes recorded')) ?>
                </div>
            </div>
        </div>
        <div class="row-2col">
            <div class="form-group">
                <label class="form-label">History of Presenting Illness (HPI)</label>
                <div class="form-control" style="min-height:60px;background:var(--gray-50);">
                    <?= nl2br(htmlspecialchars($visit['hpi'] ?? 'No HPI recorded')) ?>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Physical Examination</label>
                <div class="form-control" style="min-height:60px;background:var(--gray-50);">
                    <?= nl2br(htmlspecialchars($visit['physical_exam'] ?? 'No physical exam recorded')) ?>
                </div>
            </div>
        </div>
    </div>

    <div class="consultation-card mb-6">
        <h3 class="card-title">
            <i class="fas fa-flask"></i> Laboratory Results
            <?php if ($lab_results_available): ?>
                <span class="frozen-badge success">✅ Results Available</span>
            <?php endif; ?>
        </h3>
        <?php if ($lab_results_available): ?>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:0.85rem;">
                    <thead>
                        <tr>
                            <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);border-bottom:2px solid var(--border-color);">Test Name</th>
                            <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);border-bottom:2px solid var(--border-color);">Result</th>
                            <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);border-bottom:2px solid var(--border-color);">Reference Range</th>
                            <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);border-bottom:2px solid var(--border-color);">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lab_results as $result): ?>
                            <tr>
                                <td style="padding:10px 14px;border-bottom:1px solid var(--border-color);"><?= htmlspecialchars($result['test_name'] ?? 'N/A') ?></td>
                                <td style="padding:10px 14px;border-bottom:1px solid var(--border-color);font-weight:600;color:var(--success);"><?= htmlspecialchars($result['results'] ?? 'N/A') ?></td>
                                <td style="padding:10px 14px;border-bottom:1px solid var(--border-color);font-size:0.8rem;color:var(--text-secondary);"><?= htmlspecialchars($result['reference_range'] ?? 'N/A') ?></td>
                                <td style="padding:10px 14px;border-bottom:1px solid var(--border-color);"><span class="status-badge badge-success">✅ Completed</span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state"><i class="fas fa-flask"></i><p>No lab tests were requested</p></div>
        <?php endif; ?>
    </div>

    <div class="consultation-card mb-6">
        <h3 class="card-title"><i class="fas fa-diagnoses"></i> Diagnosis & Treatment</h3>
        <?php if (!empty($visit['diagnosis']) || !empty($visit['disease_name'])): ?>
            <div class="row-2col">
                <div class="form-group">
                    <label class="form-label">Diagnosis</label>
                    <div class="form-control" style="background:var(--gray-50);font-weight:600;color:var(--success);">
                        <?= htmlspecialchars($visit['diagnosis'] ?? $visit['disease_name'] ?? 'N/A') ?>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Disease Code</label>
                    <div class="form-control" style="background:var(--gray-50);font-family:monospace;">
                        <?= htmlspecialchars($visit['disease_code'] ?? 'N/A') ?>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Treatment Plan</label>
                <div class="form-control" style="min-height:60px;background:var(--gray-50);">
                    <?= nl2br(htmlspecialchars($visit['treatment'] ?? 'No treatment recorded')) ?>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state"><i class="fas fa-diagnoses"></i><p>No diagnosis recorded</p></div>
        <?php endif; ?>
    </div>

    <div class="consultation-card mb-6">
        <h3 class="card-title">
            <i class="fas fa-prescription"></i> Prescribed Medications
            <span class="section-total green">
                <span class="label">💊 Total:</span>
                <span class="amount">TSh <?= number_format($medications_total, 0) ?></span>
            </span>
        </h3>
        <?php if (count($valid_prescriptions) > 0): ?>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:0.85rem;">
                    <thead>
                        <tr>
                            <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);border-bottom:2px solid var(--border-color);">Medication</th>
                            <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);border-bottom:2px solid var(--border-color);">Dosage</th>
                            <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);border-bottom:2px solid var(--border-color);">Frequency</th>
                            <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);border-bottom:2px solid var(--border-color);">Duration</th>
                            <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);border-bottom:2px solid var(--border-color);">Qty</th>
                            <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);border-bottom:2px solid var(--border-color);">Total</th>
                            <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);border-bottom:2px solid var(--border-color);">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($valid_prescriptions as $med): ?>
                            <tr>
                                <td style="padding:10px 14px;border-bottom:1px solid var(--border-color);font-weight:500;"><?= htmlspecialchars($med['medication_name'] ?? 'Unknown') ?></td>
                                <td style="padding:10px 14px;border-bottom:1px solid var(--border-color);"><?= htmlspecialchars($med['dosage'] ?? '-') ?></td>
                                <td style="padding:10px 14px;border-bottom:1px solid var(--border-color);"><?= htmlspecialchars($med['frequency'] ?? '-') ?></td>
                                <td style="padding:10px 14px;border-bottom:1px solid var(--border-color);"><?= htmlspecialchars($med['duration'] ?? '-') ?> days</td>
                                <td style="padding:10px 14px;border-bottom:1px solid var(--border-color);">x<?= $med['quantity'] ?? 0 ?></td>
                                <td style="padding:10px 14px;border-bottom:1px solid var(--border-color);font-weight:600;color:var(--success);">TSh <?= number_format($med['total_price'] ?? 0, 0) ?></td>
                                <td style="padding:10px 14px;border-bottom:1px solid var(--border-color);">
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
            <div class="empty-state"><i class="fas fa-prescription"></i><p>No medications prescribed</p></div>
        <?php endif; ?>
    </div>

    <div class="consultation-card mb-6">
        <h3 class="card-title">
            <i class="fas fa-syringe"></i> Procedures & Equipment
            <span class="section-total purple">
                <span class="label">🛠️ Total:</span>
                <span class="amount">TSh <?= number_format($procedure_total + $equipment_total, 0) ?></span>
            </span>
        </h3>
        
        <?php if (count($procedures) > 0 || count($equipment_items_display) > 0): ?>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:0.85rem;">
                    <thead>
                        <tr>
                            <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);border-bottom:2px solid var(--border-color);">Item</th>
                            <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);border-bottom:2px solid var(--border-color);">Type</th>
                            <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);border-bottom:2px solid var(--border-color);">Qty</th>
                            <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);border-bottom:2px solid var(--border-color);">Unit Price</th>
                            <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);border-bottom:2px solid var(--border-color);">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($procedures as $proc): ?>
                            <tr>
                                <td style="padding:10px 14px;border-bottom:1px solid var(--border-color);font-weight:500;"><?= htmlspecialchars($proc['procedure_name'] ?? 'N/A') ?></td>
                                <td style="padding:10px 14px;border-bottom:1px solid var(--border-color);"><span class="badge badge-purple">Procedure</span></td>
                                <td style="padding:10px 14px;border-bottom:1px solid var(--border-color);">1</td>
                                <td style="padding:10px 14px;border-bottom:1px solid var(--border-color);">TSh <?= number_format($proc['procedure_price'] ?? 0, 0) ?></td>
                                <td style="padding:10px 14px;border-bottom:1px solid var(--border-color);font-weight:600;color:var(--success);">TSh <?= number_format($proc['procedure_price'] ?? 0, 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php foreach ($equipment_items_display as $eq_item): ?>
                            <tr>
                                <td style="padding:10px 14px;border-bottom:1px solid var(--border-color);font-weight:500;"><?= htmlspecialchars($eq_item['item_name'] ?? 'N/A') ?></td>
                                <td style="padding:10px 14px;border-bottom:1px solid var(--border-color);"><span class="badge badge-info">Equipment</span></td>
                                <td style="padding:10px 14px;border-bottom:1px solid var(--border-color);"><?= $eq_item['quantity'] ?? 0 ?></td>
                                <td style="padding:10px 14px;border-bottom:1px solid var(--border-color);">TSh <?= number_format($eq_item['unit_price'] ?? 0, 0) ?></td>
                                <td style="padding:10px 14px;border-bottom:1px solid var(--border-color);font-weight:600;color:var(--success);">TSh <?= number_format($eq_item['total_price'] ?? 0, 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state"><i class="fas fa-syringe"></i><p>No procedures or equipment used</p></div>
        <?php endif; ?>
    </div>

    <div class="consultation-card mb-6">
        <h3 class="card-title">
            <i class="fas fa-receipt"></i> Bill Items Summary
            <span class="section-total">
                <span class="label">💰 Grand Total:</span>
                <span class="amount">TSh <?= number_format($bill_total, 0) ?></span>
            </span>
        </h3>
        <?php if (count($bill_items) > 0): ?>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:0.85rem;">
                    <thead>
                        <tr>
                            <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);border-bottom:2px solid var(--border-color);">Item</th>
                            <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);border-bottom:2px solid var(--border-color);">Type</th>
                            <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);border-bottom:2px solid var(--border-color);">Qty</th>
                            <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);border-bottom:2px solid var(--border-color);">Price</th>
                            <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);border-bottom:2px solid var(--border-color);">Total</th>
                            <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);border-bottom:2px solid var(--border-color);">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bill_items as $item): ?>
                            <tr>
                                <td style="padding:10px 14px;border-bottom:1px solid var(--border-color);font-weight:500;"><?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></td>
                                <td style="padding:10px 14px;border-bottom:1px solid var(--border-color);">
                                    <?php 
                                    $type_labels = [
                                        'consultation' => 'Consultation',
                                        'lab_test' => 'Lab Test',
                                        'medication' => 'Medication',
                                        'procedure' => 'Procedure',
                                        'equipment' => 'Equipment',
                                        'registration' => 'Registration'
                                    ];
                                    echo $type_labels[$item['item_type']] ?? ucfirst($item['item_type']);
                                    ?>
                                </td>
                                <td style="padding:10px 14px;border-bottom:1px solid var(--border-color);"><?= $item['quantity'] ?? 0 ?></td>
                                <td style="padding:10px 14px;border-bottom:1px solid var(--border-color);">TSh <?= number_format($item['unit_price'] ?? 0, 0) ?></td>
                                <td style="padding:10px 14px;border-bottom:1px solid var(--border-color);font-weight:600;">TSh <?= number_format($item['total_price'] ?? 0, 0) ?></td>
                                <td style="padding:10px 14px;border-bottom:1px solid var(--border-color);">
                                    <?php if (($item['status'] ?? '') === 'paid'): ?>
                                        <span class="status-badge badge-success">✅ Paid</span>
                                    <?php else: ?>
                                        <span class="status-badge badge-warning">⏳ Pending</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div style="margin-top:16px;padding:14px 20px;background:var(--gray-50);border-radius:var(--radius);border:1px solid var(--border-color);">
                <div style="display:flex;justify-content:space-between;font-size:0.85rem;padding:4px 0;">
                    <span style="color:var(--text-secondary);">Subtotal:</span>
                    <span style="font-weight:500;">TSh <?= number_format($bill_subtotal, 0) ?></span>
                </div>
                <?php if ($total_discount > 0): ?>
                <div style="display:flex;justify-content:space-between;font-size:0.85rem;padding:4px 0;">
                    <span style="color:var(--purple);">Total Discount:</span>
                    <span style="font-weight:500;color:var(--purple);">- TSh <?= number_format($total_discount, 0) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($total_premium > 0): ?>
                <div style="display:flex;justify-content:space-between;font-size:0.85rem;padding:4px 0;">
                    <span style="color:#D97706;">Premium:</span>
                    <span style="font-weight:500;color:#D97706;">+ TSh <?= number_format($total_premium, 0) ?></span>
                </div>
                <?php endif; ?>
                <div style="display:flex;justify-content:space-between;font-size:1rem;padding:8px 0;border-top:2px solid var(--border-color);margin-top:4px;">
                    <span style="font-weight:700;">Grand Total:</span>
                    <span style="font-weight:700;color:var(--primary);">TSh <?= number_format($bill_total, 0) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:0.85rem;padding:4px 0;">
                    <span style="color:var(--success);">Paid:</span>
                    <span style="font-weight:500;color:var(--success);">TSh <?= number_format($bill_paid, 0) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:0.85rem;padding:4px 0;">
                    <span style="color:<?= $bill_balance > 0 ? 'var(--danger)' : 'var(--success)' ?>;">Balance:</span>
                    <span style="font-weight:600;color:<?= $bill_balance > 0 ? 'var(--danger)' : 'var(--success)' ?>;">TSh <?= number_format($bill_balance, 0) ?></span>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state"><i class="fas fa-receipt"></i><p>No bill items recorded</p></div>
        <?php endif; ?>
    </div>

    <?php endif; ?>

    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span style="color:var(--gray-300);margin:0 8px;">|</span>
            <?= $is_completed ? 'Consultation Summary' : 'Consultation' ?> (7 Vital Signs)
            <span style="color:var(--gray-300);margin:0 8px;">|</span>
            <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>
            <span style="color:var(--gray-300);margin:0 8px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<div id="toast" class="toast-custom" style="display:none;">
    <div class="toast-icon"><i class="fas fa-info-circle"></i></div>
    <div class="toast-content">
        <p class="toast-title" id="toastTitle">Notification</p>
        <p class="toast-message" id="toastMessage"></p>
    </div>
    <button class="toast-close" onclick="closeToast()">&times;</button>
</div>

<script>
// ================================================================
// CONSULTATION JAVASCRIPT - V4 (EQUIPMENT STOCK MOVEMENTS FIXED)
// ================================================================

var AUTO_UPDATE_INTERVAL = 3000;
var FULL_UPDATE_INTERVAL = 5000;
var LAB_CHECK_INTERVAL = 2000;
var AUTO_SAVE_INTERVAL = 10000;
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
var autoCompleteTriggered = false;

var selectedProcedures = [];
var selectedEquipment = [];
var selectedLabTests = [];
var selectedMedications = [];
var complaintsList = [];
var diagnosisSaving = false;
var diagnosisAlreadySaved = false;
var manualDiseaseCount = <?= count($manual_diseases_saved) ?>;
var lastSavedHash = '';

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

function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

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
    body.classList.toggle('open');
    header.classList.toggle('active');
}

function addComplaintOnSelect() {
    var select = document.getElementById('complaintSelect');
    var value = select.value;
    if (!value) return;
    if (value && !complaintsList.includes(value)) {
        complaintsList.push(value);
        updateComplaintsDisplay();
        select.value = '';
        saveDiseasesToVisit();
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

function toggleLabDropdown() {
    var body = document.getElementById('labDropdownBody');
    var header = body.previousElementSibling;
    body.classList.toggle('open');
    header.classList.toggle('active');
    if (body.classList.contains('open')) document.getElementById('labTestSearch').focus();
}

function toggleDiseaseDropdown() {
    var body = document.getElementById('diseaseDropdownBody');
    var header = body.previousElementSibling;
    body.classList.toggle('open');
    header.classList.toggle('active');
    if (body.classList.contains('open')) document.getElementById('diseaseSearch').focus();
}

function toggleMedicationDropdown() {
    var body = document.getElementById('medicationDropdownBody');
    var header = body.previousElementSibling;
    body.classList.toggle('open');
    header.classList.toggle('active');
    if (body.classList.contains('open')) document.getElementById('medicationSearch').focus();
}

function filterLabTestsInside() {
    var input = document.getElementById('labTestSearch');
    var filter = input.value.toLowerCase().trim();
    document.querySelectorAll('#labTestsGrid .lab-test-item-select').forEach(function(item) {
        var name = (item.getAttribute('data-test-name') || '').toLowerCase();
        item.style.display = (!filter || name.includes(filter)) ? 'flex' : 'none';
    });
}

function filterDiseasesInside() {
    var input = document.getElementById('diseaseSearch');
    var filter = input.value.toLowerCase().trim();
    document.querySelectorAll('#diseaseCheckboxContainer .disease-checkbox-item').forEach(function(item) {
        var name = item.getAttribute('data-disease-name') || '';
        item.style.display = (!filter || name.includes(filter)) ? 'flex' : 'none';
    });
}

function filterMedicationsInside() {
    var input = document.getElementById('medicationSearch');
    var filter = input.value.toLowerCase().trim();
    document.querySelectorAll('#medicationsGrid .medication-item-select').forEach(function(item) {
        var name = (item.getAttribute('data-medication-name') || '').toLowerCase();
        var category = (item.getAttribute('data-category') || '').toLowerCase();
        var searchText = name + ' ' + category;
        item.style.display = (!filter || searchText.includes(filter)) ? 'flex' : 'none';
    });
}

function toggleLabTestCheckbox(element) {
    element.classList.toggle('selected');
    var testId = element.getAttribute('data-test-id');
    var idx = selectedLabTests.indexOf(testId);
    if (idx > -1) selectedLabTests.splice(idx, 1);
    else selectedLabTests.push(testId);
    document.getElementById('labSelectedCount').textContent = 'Selected: ' + selectedLabTests.length;
    saveDiseasesToVisit();
}

function addSelectedLabTests() {
    if (selectedLabTests.length === 0) {
        showToast('⚠️ Warning', 'Please select at least one lab test', 'warning');
        return;
    }
    
    var added = 0;
    var errors = [];
    
    selectedLabTests.forEach(function(testId) {
        var selectedEl = document.querySelector('.lab-test-item-select[data-test-id="' + testId + '"]');
        if (!selectedEl) return;
        
        var testName = selectedEl.getAttribute('data-test-name');
        var equipmentId = selectedEl.getAttribute('data-equipment-id') || null;
        var equipmentStock = parseInt(selectedEl.getAttribute('data-equipment-stock') || 0);
        var equipmentUsed = parseInt(selectedEl.getAttribute('data-equipment-used') || 1);
        
        if (equipmentId && equipmentStock < equipmentUsed) {
            errors.push(testName + ' (No stock)');
            return;
        }
        
        var formData = new FormData();
        formData.append('action', 'add_lab_test_cart');
        formData.append('test_id', testId);
        
        fetch(window.location.href, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                added++;
                updateLabCartUI(data);
                selectedEl.classList.remove('selected');
            } else {
                errors.push(testName + ' (' + data.message + ')');
            }
        });
    });
    
    setTimeout(function() {
        if (added > 0) showToast('✅ Success', 'Added ' + added + ' test(s) to cart', 'success');
        else if (errors.length > 0) showToast('❌ Error', 'Failed: ' + errors.join(', '), 'error');
        selectedLabTests = [];
        document.getElementById('labSelectedCount').textContent = 'Selected: 0';
    }, 500);
}

function updateLabCartUI(data) {
    var container = document.getElementById('labCartItems');
    if (!container) return;
    
    if (data.cart_items && data.cart_items.length > 0) {
        var html = '';
        data.cart_items.forEach(function(item) {
            html += '<div class="lab-cart-item" id="lab-cart-' + item.id + '" data-test-id="' + item.id + '"><span class="cart-item-name">' + escapeHtml(item.name) + '</span><span class="cart-item-price">TSh ' + Number(item.price).toLocaleString() + '</span><button type="button" class="btn-remove-cart" onclick="removeLabTestFromCart(' + item.id + ')"><i class="fas fa-times"></i></button></div>';
        });
        container.innerHTML = html;
    } else {
        container.innerHTML = '<div class="lab-cart-empty">No tests in cart.</div>';
    }
    
    var sendBtn = document.getElementById('sendLabBtn');
    if (sendBtn) {
        sendBtn.disabled = data.cart_count === 0;
        sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send All to Laboratory (' + (data.cart_count || 0) + ' tests)';
    }
    
    var cartTotalSection = document.getElementById('labCartTotalSection');
    if (cartTotalSection) cartTotalSection.style.display = data.cart_count > 0 ? 'inline-flex' : 'none';
    
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
        if (data.success) window.location.reload();
        else showToast('❌ Error', data.message, 'error');
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
    if (!confirm('Remove this lab test? Equipment stock will be returned.')) return;
    var formData = new FormData();
    formData.append('action', 'remove_lab_test');
    formData.append('test_id', testId);
    fetch(window.location.href, { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if (data.success) { showToast('✅ Success', data.message, 'success'); window.location.reload(); }
        else showToast('❌ Error', data.message, 'error');
    });
}

function toggleMedicationCheckbox(element) {
    element.classList.toggle('selected');
    
    var invId = element.getAttribute('data-inventory-id');
    var name = element.getAttribute('data-medication-name');
    var price = parseFloat(element.getAttribute('data-price')) || 0;
    var stock = parseInt(element.getAttribute('data-stock')) || 0;
    var category = element.getAttribute('data-category') || '';
    var batches = parseInt(element.getAttribute('data-batches')) || 1;
    
    var idx = selectedMedications.findIndex(function(m) { return m.id === invId; });
    if (idx > -1) selectedMedications.splice(idx, 1);
    else selectedMedications.push({ id: invId, name: name, price: price, stock: stock, category: category, batches: batches });
    
    updateMedSelectedInfo();
    saveDiseasesToVisit();
}

function updateMedSelectedInfo() {
    var info = document.getElementById('medSelectedInfo');
    if (!info) return;
    if (selectedMedications.length === 0) {
        info.textContent = 'Selected: None';
        return;
    }
    var names = selectedMedications.map(function(m) { return m.name; });
    info.textContent = 'Selected: ' + names.join(', ') + ' (' + selectedMedications.length + ' items)';
}

function addMedicationAjax() {
    if (selectedMedications.length === 0) {
        showToast('❌ Error', 'Please select at least one medication', 'error');
        return;
    }
    
    var qty = parseInt(document.getElementById('medQuantity').value) || 0;
    var dosage = document.getElementById('medDosage').value;
    var freqSelect = document.getElementById('medFrequency');
    var freqManual = document.getElementById('medFrequencyManual');
    var frequency = freqManual.value.trim() || freqSelect.value;
    var duration = document.getElementById('medDuration').value;
    var routeSelect = document.getElementById('medRoute');
    var routeManual = document.getElementById('medRouteManual');
    var route = routeManual.value.trim() || routeSelect.value;
    var instructions = document.getElementById('medInstructions').value;
    
    if (qty < 1) { showToast('❌ Error', 'Quantity must be at least 1', 'error'); return; }
    if (!frequency) { showToast('❌ Error', 'Please enter frequency', 'error'); return; }
    if (!route) { showToast('❌ Error', 'Please enter route', 'error'); return; }
    
    for (var i = 0; i < selectedMedications.length; i++) {
        if (qty > selectedMedications[i].stock) {
            showToast('⚠️ Warning', 'Not enough stock for ' + selectedMedications[i].name, 'warning');
            return;
        }
    }
    
    var diagnosisData = getDiagnosisData();
    var btn = document.getElementById('addMedicationBtn');
    var originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
    
    var added = 0;
    var errors = [];
    
    selectedMedications.forEach(function(med, index) {
        var formData = new FormData();
        formData.append('action', 'add_medication');
        formData.append('inventory_id', med.id);
        formData.append('quantity', qty);
        formData.append('dosage', dosage);
        formData.append('frequency', frequency);
        formData.append('duration', duration);
        formData.append('route', route);
        formData.append('instructions', instructions);
        
        if (diagnosisData) {
            formData.append('diagnosis_ids', JSON.stringify(diagnosisData.diagnosis_ids || []));
            formData.append('manual_diseases', JSON.stringify(diagnosisData.manual_diseases || []));
            formData.append('manual_disease_codes', JSON.stringify(diagnosisData.manual_disease_codes || []));
            formData.append('treatment', diagnosisData.treatment || '');
            formData.append('symptoms', diagnosisData.symptoms || '');
            formData.append('hpi', diagnosisData.hpi || '');
            formData.append('physical_exam', diagnosisData.physical_exam || '');
            formData.append('notes', diagnosisData.notes || '');
        }
        
        fetch(window.location.href, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                added++;
                if (data.medication) addMedicationToList(data.medication);
                if (data.bill_data) updateBillTotals(data.bill_data);
            } else {
                errors.push(med.name + ': ' + data.message);
            }
            
            if (index === selectedMedications.length - 1) {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
                document.querySelectorAll('#medicationsGrid .medication-item-select').forEach(function(el) { el.classList.remove('selected'); });
                selectedMedications = [];
                updateMedSelectedInfo();
                
                if (added > 0) showToast('✅ Success', 'Added ' + added + ' medication(s)', 'success');
                else if (errors.length > 0) showToast('❌ Error', 'Failed: ' + errors.join(', '), 'error');
                
                document.getElementById('medQuantity').value = '1';
                document.getElementById('medDosage').value = '';
                document.getElementById('medFrequency').value = '';
                document.getElementById('medFrequencyManual').value = '';
                document.getElementById('medDuration').value = '7';
                document.getElementById('medRoute').value = '';
                document.getElementById('medRouteManual').value = '';
                document.getElementById('medInstructions').value = '';
            }
        })
        .catch(function(err) {
            errors.push(med.name + ': Network error');
            if (index === selectedMedications.length - 1) {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
                document.querySelectorAll('#medicationsGrid .medication-item-select').forEach(function(el) { el.classList.remove('selected'); });
                selectedMedications = [];
                updateMedSelectedInfo();
                showToast('❌ Error', 'Failed. ' + errors.join(', '), 'error');
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
    div.className = 'medication-item';
    div.id = 'med-item-' + med.id;
    var isDispensed = med.status === 'dispensed';
    div.innerHTML = '<div class="medication-item-info"><span class="med-name">' + escapeHtml(med.name) + '</span><span class="med-details">' + escapeHtml(med.dosage || '') + ' • ' + escapeHtml(med.frequency || '') + ' • ' + escapeHtml(med.duration || '') + ' days</span><span class="med-qty">x' + (med.quantity || 0) + '</span><span class="med-price">TSh ' + (med.total_price || 0).toLocaleString() + '</span>' + (med.instructions ? '<span class="med-instruction-tag">' + escapeHtml(med.instructions) + '</span>' : '') + (isDispensed ? '<span class="med-status-dispensed">✅ Dispensed</span>' : '<span class="med-status-pending">⏳ Pending</span>') + '</div>' + (!isDispensed ? '<button type="button" class="btn-remove" onclick="removeMedication(' + med.id + ')" title="Remove"><i class="fas fa-times"></i></button>' : '');
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
        } else showToast('❌ Error', data.message, 'error');
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

function saveDiseasesToVisit() {
    if (isCompleted || isWaiting) return;
    
    var diagnosis_ids = [];
    document.querySelectorAll('input[name="diagnosis_ids[]"]:checked').forEach(function(cb) {
        var val = parseInt(cb.value);
        if (val > 0 && !diagnosis_ids.includes(val)) diagnosis_ids.push(val);
    });
    
    var manual_diseases = [];
    var manual_disease_codes = [];
    
    document.querySelectorAll('.manual-disease-tag').forEach(function(tag) {
        var nameInput = tag.querySelector('input[name="manual_diseases[]"]');
        if (!nameInput) return;
        
        var name = nameInput.value.trim().replace(/[\[\]"]/g, '').trim();
        if (!name || manual_diseases.includes(name)) return;
        
        manual_diseases.push(name);
        
        var codeInput = tag.querySelector('input[name="manual_disease_codes[]"]');
        var code = '';
        if (codeInput) {
            code = codeInput.value.trim().replace(/[\[\]"]/g, '').trim();
        } else {
            code = tag.getAttribute('data-disease-code') || '';
        }
        manual_disease_codes.push(code);
    });
    
    var treatment = document.getElementById('treatmentTextarea')?.value || '';
    var symptoms = document.getElementById('symptomsTextarea')?.value || '';
    var hpi = document.getElementById('hpiTextarea')?.value || '';
    var physical_exam = document.getElementById('physicalExamTextarea')?.value || '';
    var notes = document.getElementById('notesTextarea')?.value || '';
    
    if (diagnosis_ids.length === 0 && manual_diseases.length === 0 && !treatment && !symptoms && !hpi && !physical_exam && !notes) return;
    
    var currentHash = JSON.stringify({
        d: diagnosis_ids.sort(), 
        m: manual_diseases.sort(),
        mc: manual_disease_codes,
        t: treatment, s: symptoms, h: hpi, p: physical_exam, n: notes
    });
    
    if (currentHash === lastSavedHash) return;
    lastSavedHash = currentHash;
    
    var saveIndicator = document.getElementById('diagnosisSaveIndicator');
    var savedIndicator = document.getElementById('diagnosisSavedIndicator');
    if (saveIndicator) saveIndicator.style.display = 'inline';
    if (savedIndicator) savedIndicator.style.display = 'none';
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'save_diagnosis',
            visit_id: visitId,
            diagnosis_ids: diagnosis_ids,
            manual_diseases: manual_diseases,
            manual_disease_codes: manual_disease_codes,
            treatment: treatment,
            symptoms: symptoms,
            hpi: hpi,
            physical_exam: physical_exam,
            notes: notes
        })
    })
    .then(response => response.json())
    .then(result => {
        if (saveIndicator) saveIndicator.style.display = 'none';
        if (result.success) {
            hasDiagnosis = true;
            diagnosisAlreadySaved = true;
            if (savedIndicator) {
                savedIndicator.style.display = 'inline';
                setTimeout(function() { if (savedIndicator) savedIndicator.style.display = 'none'; }, 2000);
            }
            var statusEl = document.getElementById('diagnosisStatus');
            if (statusEl && result.data && result.data.diagnosis) {
                statusEl.innerHTML = '✅ Saved: ' + result.data.diagnosis;
                statusEl.style.color = 'var(--success)';
            }
        }
    })
    .catch(function(error) { if (saveIndicator) saveIndicator.style.display = 'none'; });
}

function addManualDisease() {
    var input = document.getElementById('manualDiseaseInput');
    var codeInput = document.getElementById('manualDiseaseCodeInput');
    var container = document.getElementById('manualDiseasesContainer');
    var name = input.value.trim();
    
    if (!name) { showToast('⚠️ Warning', 'Please enter a disease name', 'warning'); return; }
    name = name.replace(/[\[\]"]/g, '').trim();
    if (!name) { showToast('⚠️ Warning', 'Please enter a valid disease name', 'warning'); return; }
    
    var customCode = codeInput ? codeInput.value.trim() : '';
    customCode = customCode.replace(/[\[\]"]/g, '').trim();
    
    var existing = container.querySelectorAll('.manual-disease-tag');
    for (var i = 0; i < existing.length; i++) {
        var tagName = existing[i].getAttribute('data-disease') || '';
        if (tagName.toLowerCase() === name.toLowerCase()) {
            showToast('⚠️ Warning', 'Disease already added', 'warning');
            input.value = '';
            if (codeInput) codeInput.value = '';
            return;
        }
    }
    
    var tag = document.createElement('span');
    tag.className = 'manual-disease-tag';
    tag.setAttribute('data-disease', name);
    if (customCode) tag.setAttribute('data-disease-code', customCode);
    
    var codeDisplay = customCode ? ' <small style="font-family:monospace;background:var(--gray-200);padding:0 6px;border-radius:6px;font-size:0.65rem;">' + escapeHtml(customCode) + '</small>' : '';
    
    tag.innerHTML = '<i class="fas fa-user-md" style="color:var(--primary);font-size:0.6rem;"></i> ' + escapeHtml(name) + codeDisplay +
                    '<button type="button" class="btn-remove-tag" onclick="removeManualDisease(this)">×</button>' +
                    '<input type="hidden" name="manual_diseases[]" value="' + escapeHtml(name) + '">' +
                    (customCode ? '<input type="hidden" name="manual_disease_codes[]" value="' + escapeHtml(customCode) + '" data-for="' + escapeHtml(name) + '">' : '');
    container.appendChild(tag);
    
    input.value = '';
    if (codeInput) codeInput.value = '';
    manualDiseaseCount++;
    saveDiseasesToVisit();
}

function removeManualDisease(btn) {
    btn.parentElement.remove();
    manualDiseaseCount--;
    saveDiseasesToVisit();
}

function getDiagnosisData() {
    var diagnosis_ids = [];
    document.querySelectorAll('input[name="diagnosis_ids[]"]:checked').forEach(function(cb) {
        var val = parseInt(cb.value);
        if (val > 0 && !diagnosis_ids.includes(val)) diagnosis_ids.push(val);
    });
    
    var manual_diseases = [];
    var manual_disease_codes = [];
    
    document.querySelectorAll('.manual-disease-tag').forEach(function(tag) {
        var nameInput = tag.querySelector('input[name="manual_diseases[]"]');
        if (!nameInput) return;
        
        var name = nameInput.value.trim().replace(/[\[\]"]/g, '').trim();
        if (!name || manual_diseases.includes(name)) return;
        
        manual_diseases.push(name);
        
        var codeInput = tag.querySelector('input[name="manual_disease_codes[]"]');
        var code = '';
        if (codeInput) {
            code = codeInput.value.trim().replace(/[\[\]"]/g, '').trim();
        } else {
            code = tag.getAttribute('data-disease-code') || '';
        }
        manual_disease_codes.push(code);
    });
    
    return {
        diagnosis_ids: diagnosis_ids,
        manual_diseases: manual_diseases,
        manual_disease_codes: manual_disease_codes,
        treatment: document.getElementById('treatmentTextarea')?.value || '',
        symptoms: document.getElementById('symptomsTextarea')?.value || '',
        hpi: document.getElementById('hpiTextarea')?.value || '',
        physical_exam: document.getElementById('physicalExamTextarea')?.value || '',
        notes: document.getElementById('notesTextarea')?.value || ''
    };
}

function checkLabResultsAndUpdateStatus() {
    if (isCompleted || isWaiting || isPrescribed) return;
    
    var formData = new FormData();
    formData.append('action', 'check_lab_results');
    formData.append('visit_id', visitId);
    
    fetch(window.location.href, { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.status_updated) {
            showToast('✅ Lab Results Completed!', 'Status updated to PRESCRIBED.', 'success');
            setTimeout(function() { window.location.reload(); }, 1500);
        }
    })
    .catch(function(err) {});
}

function checkAndTriggerAutoComplete() {
    if (isCompleted || autoCompleteTriggered) return;
    if (!isWaiting) return;
    if (!hasDiagnosis) return;
    
    var formData = new FormData();
    formData.append('action', 'get_bill_totals');
    
    fetch(window.location.href, { method: 'POST', body: formData })
    .then(response => response.json())
    .then(billData => {
        if (billData.success) {
            var balance = Number(billData.bill_balance || 0);
            if (balance <= 0) {
                autoCompleteTriggered = true;
                showToast('✅ Auto-Complete!', 'All bills paid. Completing...', 'success');
                setTimeout(function() { window.location.reload(); }, 2000);
            }
        }
    })
    .catch(function(err) {});
}

function updateBillTotals(billData) {
    if (!billData) return;
    
    var totalEl = document.getElementById('totalAmountDisplay');
    if (totalEl) totalEl.textContent = 'TSh ' + Number(billData.total || billData.subtotal || 0).toLocaleString();
    
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
            } else {
                card.className = 'bill-summary-card remaining-card zero-balance';
                var icon = card.querySelector('.bill-summary-icon i');
                if (icon) icon.className = 'fas fa-check-circle';
            }
        }
    }
    
    var discountEl = document.getElementById('discountAmountDisplay');
    if (discountEl) discountEl.textContent = 'TSh ' + Number(billData.discount || 0).toLocaleString();
    
    var premiumEl = document.getElementById('premiumAmountDisplay');
    if (premiumEl) {
        var premium = Number(billData.premium || 0);
        premiumEl.textContent = 'TSh ' + premium.toLocaleString();
        var premiumCard = premiumEl.closest('.bill-summary-card');
        if (premiumCard) {
            premiumCard.style.opacity = premium > 0 ? '1' : '0.5';
        }
    }
    
    if (billData.status) {
        var statusBadge = document.getElementById('billStatusBadge');
        if (statusBadge) {
            var statusMap = { 'pending': 'badge-warning', 'partial': 'badge-warning', 'paid': 'badge-success', 'cancelled': 'badge-danger' };
            statusBadge.className = 'status-badge ' + (statusMap[billData.status] || 'badge-warning');
            var statusText = billData.status.charAt(0).toUpperCase() + billData.status.slice(1);
            if (billData.status === 'partial') statusText = 'Partial';
            else if (billData.status === 'paid') statusText = '✅ FULLY PAID';
            statusBadge.textContent = statusText;
        }
    }
    
    var lastUpdated = document.getElementById('billLastUpdated');
    if (lastUpdated) lastUpdated.textContent = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    
    if (isWaiting && !autoCompleteTriggered && !isCompleted) checkAndTriggerAutoComplete();
}

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
            
            updateBillTotals({
                subtotal: data.subtotal || 0,
                discount: data.discount || 0,
                premium: data.premium || 0,
                total: data.grand_total || 0,
                paid: data.paid_total || 0,
                balance: data.bill_balance || 0,
                status: data.bill_status || 'pending'
            });
            
            if (data.auto_completed) {
                showToast('✅ Auto-Complete!', 'Consultation auto-completed!', 'success');
                setTimeout(function() { window.location.reload(); }, 2000);
            }
        }
    })
    .catch(function(err) {});
}

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
            if (data.auto_completed) {
                showToast('✅ Auto-Complete!', 'Consultation auto-completed!', 'success');
                setTimeout(function() { window.location.reload(); }, 2000);
            }
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
    
    if (data.lab && data.lab.results && data.lab.results.length > 0) updateLabResultsUI(data.lab.results);
    if (data.prescriptions && data.prescriptions.length > 0) {
        var hasValid = data.prescriptions.some(function(p) { return p.medication_name && p.item_id; });
        if (hasValid) updateMedicationsUI(data.prescriptions);
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
    if (!container || !results || results.length === 0) return;
    
    var html = '<div style="overflow-x:auto;"><table style="width:100%;border-collapse:collapse;font-size:0.85rem;"><thead><tr><th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);border-bottom:2px solid var(--border-color);">Test Name</th><th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);border-bottom:2px solid var(--border-color);">Result</th><th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--text-secondary);border-bottom:2px solid var(--border-color);">Status</th></tr></thead><tbody>';
    results.forEach(function(result) {
        html += '<tr><td style="padding:10px 14px;border-bottom:1px solid var(--border-color);">' + escapeHtml(result.test_name || 'N/A') + '</td><td style="padding:10px 14px;border-bottom:1px solid var(--border-color);font-weight:600;color:var(--success);">' + escapeHtml(result.results || 'N/A') + '</td><td style="padding:10px 14px;border-bottom:1px solid var(--border-color);"><span class="status-badge badge-success">✅ Completed</span></td></tr>';
    });
    html += '</tbody></table></div>';
    container.innerHTML = html;
}

function updateMedicationsUI(prescriptions) {
    var list = document.getElementById('medicationsList');
    if (!list) return;
    
    var valid = (prescriptions || []).filter(function(med) {
        return med && med.medication_name && med.item_id;
    });
    
    if (valid.length === 0) {
        list.innerHTML = '<div class="empty-state" id="emptyMedications"><i class="fas fa-prescription"></i><p>No medications prescribed yet</p></div>';
        return;
    }
    
    var html = '';
    valid.forEach(function(med) {
        var isDispensed = (med.status || '') === 'dispensed';
        html += '<div class="medication-item" id="med-item-' + med.id + '"><div class="medication-item-info"><span class="med-name">' + escapeHtml(med.medication_name || 'Unknown') + '</span><span class="med-details">' + escapeHtml(med.dosage || '') + ' • ' + escapeHtml(med.frequency || '') + ' • ' + escapeHtml(med.duration || '') + ' days</span><span class="med-qty">x' + (med.quantity || 0) + '</span><span class="med-price">TSh ' + Number(med.total_price || 0).toLocaleString() + '</span>' + (med.instructions ? '<span class="med-instruction-tag">' + escapeHtml(med.instructions) + '</span>' : '') + (isDispensed ? '<span class="med-status-dispensed">✅ Dispensed</span>' : '<span class="med-status-pending">⏳ Pending</span>') + '</div>' + (!isDispensed ? '<button type="button" class="btn-remove" onclick="removeMedication(' + med.id + ')"><i class="fas fa-times"></i></button>' : '') + '</div>';
    });
    list.innerHTML = html;
}

function toggleProcedure(element) {
    element.classList.toggle('selected');
    updateProcedureSelection();
    saveDiseasesToVisit();
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
        formData.append('diagnosis_ids', JSON.stringify(diagnosisData.diagnosis_ids || []));
        formData.append('manual_diseases', JSON.stringify(diagnosisData.manual_diseases || []));
        formData.append('manual_disease_codes', JSON.stringify(diagnosisData.manual_disease_codes || []));
        formData.append('treatment', diagnosisData.treatment || '');
        formData.append('symptoms', diagnosisData.symptoms || '');
        formData.append('hpi', diagnosisData.hpi || '');
        formData.append('physical_exam', diagnosisData.physical_exam || '');
        formData.append('notes', diagnosisData.notes || '');
    }
    
    var btn = document.querySelector('#proceduresToggle .btn-primary');
    if (!btn) return;
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
        } else showToast('❌ Error', data.message, 'error');
    })
    .catch(function(err) {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        showToast('❌ Error', 'Network error.', 'error');
    });
}

function clearProcedureSelections() {
    document.querySelectorAll('.procedure-item-select.selected').forEach(function(el) { el.classList.remove('selected'); });
    selectedProcedures = [];
    var countEl = document.getElementById('procSelectedCount');
    if (countEl) countEl.textContent = 'Selected: 0';
}

function toggleEquipment(element) {
    element.classList.toggle('selected');
    updateEquipmentSelection();
    saveDiseasesToVisit();
}

function updateEquipmentSelection() {
    selectedEquipment = [];
    document.querySelectorAll('.equipment-item-select.selected').forEach(function(item) {
        selectedEquipment.push({
            id: item.dataset.equipmentId,
            name: item.dataset.equipmentName,
            price: parseFloat(item.dataset.price) || 0,
            stock: parseInt(item.dataset.stock) || 0
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
    
    for (var i = 0; i < selectedEquipment.length; i++) {
        if (selectedEquipment[i].stock < quantity) {
            showToast('❌ Error', 'Not enough stock for ' + selectedEquipment[i].name, 'error');
            return;
        }
    }
    
    var equipmentData = selectedEquipment.map(function(eq) {
        return { id: eq.id, quantity: quantity };
    });
    
    var diagnosisData = getDiagnosisData();
    
    var formData = new FormData();
    formData.append('action', 'add_equipment_batch');
    formData.append('equipment_data', JSON.stringify(equipmentData));
    
    if (diagnosisData) {
        formData.append('diagnosis_ids', JSON.stringify(diagnosisData.diagnosis_ids || []));
        formData.append('manual_diseases', JSON.stringify(diagnosisData.manual_diseases || []));
        formData.append('manual_disease_codes', JSON.stringify(diagnosisData.manual_disease_codes || []));
        formData.append('treatment', diagnosisData.treatment || '');
        formData.append('symptoms', diagnosisData.symptoms || '');
        formData.append('hpi', diagnosisData.hpi || '');
        formData.append('physical_exam', diagnosisData.physical_exam || '');
        formData.append('notes', diagnosisData.notes || '');
    }
    
    var btn = document.querySelector('#equipmentToggle .btn-primary');
    if (!btn) return;
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
        } else showToast('❌ Error', data.message, 'error');
    })
    .catch(function(err) {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        showToast('❌ Error', 'Network error.', 'error');
    });
}

function clearEquipmentSelections() {
    document.querySelectorAll('.equipment-item-select.selected').forEach(function(el) { el.classList.remove('selected'); });
    selectedEquipment = [];
    var countEl = document.getElementById('equipSelectedCount');
    if (countEl) countEl.textContent = 'Selected: 0';
}

function removeAddedItem(type, id) {
    if (!confirm('Remove this ' + type + '? Stock will be returned.')) return;
    
    var formData = new FormData();
    formData.append('action', 'remove_added_item');
    formData.append('type', type);
    formData.append('id', id);
    formData.append('visit_id', visitId);
    
    var btn = document.getElementById('added-' + type + '-' + id)?.querySelector('.btn-remove-item');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; }
    
    fetch(window.location.href, { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-times"></i>'; }
        if (data.success) {
            showToast('✅ Success', data.message, 'success');
            var element = document.getElementById('added-' + type + '-' + id);
            if (element) element.remove();
            if (data.bill_data) updateBillTotals(data.bill_data);
            updateAddedItemsUI();
        } else showToast('❌ Error', data.message, 'error');
    })
    .catch(function(err) {
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-times"></i>'; }
        showToast('❌ Error', 'Network error.', 'error');
    });
}

function updateAddedItemsUI() {
    var items = document.querySelectorAll('.added-item-card');
    var countEl = document.getElementById('addedCount');
    if (countEl) countEl.textContent = '(' + items.length + ' items)';
    
    var total = 0;
    items.forEach(function(el) {
        var priceEl = el.querySelector('.item-price');
        if (priceEl) total += parseFloat(priceEl.textContent.replace(/[^0-9]/g, '')) || 0;
    });
    var totalEl = document.getElementById('addedTotal');
    if (totalEl) totalEl.textContent = total.toLocaleString();
    var procEquipEl = document.getElementById('procEquipTotalDisplay');
    if (procEquipEl) procEquipEl.textContent = total.toLocaleString();
    
    if (items.length === 0) {
        var list = document.getElementById('addedItemsList');
        if (list) list.innerHTML = '<div class="empty-state" id="emptyAdded"><i class="fas fa-syringe"></i><p>No procedures or equipment added yet</p></div>';
    }
}

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
                showToast('✅ Lab Status Updated', 'Refreshing...', 'success');
                setTimeout(function() { window.location.reload(); }, 1000);
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
        }
    })
    .catch(function(err) {});
}

function manualRefresh() {
    var btn = document.getElementById('refreshBtn');
    if (btn) { btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...'; btn.disabled = true; }
    fetchFullState();
    fetchBillTotals();
    setTimeout(function() {
        if (btn) { btn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh'; btn.disabled = false; }
        showToast('✅ Refreshed', 'Data updated manually', 'success');
    }, 1500);
}

function startAutoUpdate() {
    if (isCompleted) return;
    if (updateInterval) clearInterval(updateInterval);
    if (fullUpdateInterval) clearInterval(fullUpdateInterval);
    if (labCheckInterval) clearInterval(labCheckInterval);
    if (autoSaveInterval) clearInterval(autoSaveInterval);
    
    checkLabStatus();
    updateInterval = setInterval(checkLabStatus, AUTO_UPDATE_INTERVAL);
    
    fetchFullState();
    fetchBillTotals();
    fullUpdateInterval = setInterval(function() {
        fetchFullState();
        fetchBillTotals();
    }, FULL_UPDATE_INTERVAL);
    
    labCheckInterval = setInterval(checkLabResultsAndUpdateStatus, LAB_CHECK_INTERVAL);
    
    autoSaveInterval = setInterval(function() {
        if (!isCompleted && !isWaiting && !isLabTest) saveDiseasesToVisit();
    }, AUTO_SAVE_INTERVAL);
    
    if (isWaiting) {
        setTimeout(checkAndTriggerAutoComplete, 3000);
        setInterval(function() {
            if (isWaiting && !autoCompleteTriggered && !isCompleted) checkAndTriggerAutoComplete();
        }, 10000);
    }
    
    console.log('🔄 Auto-update started');
    console.log('✅ V4: Equipment stock movements FIXED');
    console.log('✅ 7 Vital Signs (SpO2 included)');
}

function stopAutoUpdate() {
    if (updateInterval) { clearInterval(updateInterval); updateInterval = null; }
    if (fullUpdateInterval) { clearInterval(fullUpdateInterval); fullUpdateInterval = null; }
    if (labCheckInterval) { clearInterval(labCheckInterval); labCheckInterval = null; }
    if (autoSaveInterval) { clearInterval(autoSaveInterval); autoSaveInterval = null; }
}

document.addEventListener('DOMContentLoaded', function() {
    if (!isCompleted) {
        setTimeout(startAutoUpdate, 1000);
        
        document.querySelectorAll('input[name="diagnosis_ids[]"]').forEach(function(cb) {
            cb.addEventListener('change', function() { saveDiseasesToVisit(); });
        });
        
        ['treatmentTextarea', 'symptomsTextarea', 'hpiTextarea', 'physicalExamTextarea', 'notesTextarea', 'manualDiseaseInput'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) {
                el.addEventListener('change', function() { saveDiseasesToVisit(); });
                el.addEventListener('blur', function() { saveDiseasesToVisit(); });
            }
        });
        
        window.addEventListener('beforeunload', function() {
            if (!isCompleted && !isWaiting) saveDiseasesToVisit();
        });
        
        if (isWaiting) setTimeout(checkAndTriggerAutoComplete, 2000);
    }
});

document.addEventListener('visibilitychange', function() {
    if (document.hidden) stopAutoUpdate();
    else startAutoUpdate();
});

console.log('%c🩺 Braick Consultation V4 (Equipment Stock Movements Fixed)', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
console.log('%c✅ V4: add_equipment_batch inaingiza stock_movements', 'font-size:12px; color:#DC2626;');
console.log('%c✅ V4: remove_added_item inarudisha stock + stock_movement', 'font-size:12px; color:#DC2626;');
console.log('%c✅ V4: remove_medication inaingiza stock_movement ya returned', 'font-size:12px; color:#DC2626;');
console.log('%c✅ V4: logStockMovement() helper function', 'font-size:12px; color:#059669;');
console.log('%c❤️ 7 Vital Signs: Temp, BP, Pulse, SpO2, Weight, Height, BMI', 'font-size:12px; color:#DC2626;');
</script>

</body>
</html>