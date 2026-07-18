<?php
// ================================================================
// FILE: frontend/pages/doctor/consultation.php
// DOCTOR CONSULTATION - FULL VERSION
// WITH SYMPTOMS DROPDOWN, LAB TESTS, MEDICATIONS, PROCEDURES
// ALL FEES IN BACKGROUND - DOCTOR SEES NOTHING
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
$visit_id = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

if ($visit_id <= 0 && $patient_id <= 0) {
    header('Location: my_patients.php');
    exit;
}

// ================================================================
// INCLUDE DATABASE
// ================================================================
require_once 'C:/xampp/htdocs/dispensary_system/backend/config/database.php';
$db = Database::getInstance()->getConnection();

// ================================================================
// GET OR CREATE VISIT
// ================================================================
if ($visit_id > 0) {
    $stmt = $db->prepare("
        SELECT v.*, 
               p.id as patient_id,
               p.full_name as patient_name,
               p.patient_id as patient_code,
               p.phone,
               p.email,
               p.date_of_birth,
               p.gender,
               p.address,
               p.blood_group,
               p.allergies,
               p.emergency_contact,
               p.created_at as patient_registered,
               u.full_name as doctor_name,
               b.name as branch_name
        FROM visits v
        JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON v.doctor_id = u.id
        LEFT JOIN branches b ON v.branch_id = b.id
        WHERE v.id = ? AND v.doctor_id = ?
    ");
    $stmt->execute([$visit_id, $doctor_id]);
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
               p.full_name as patient_name,
               p.patient_id as patient_code,
               p.phone,
               p.email,
               p.date_of_birth,
               p.gender,
               p.address,
               p.blood_group,
               p.allergies,
               p.emergency_contact,
               p.created_at as patient_registered,
               u.full_name as doctor_name,
               b.name as branch_name
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
            ) VALUES (?, ?, ?, ?, 'new', 'with_doctor', NOW())
        ");
        $stmt->execute([$visit_number, $patient_id, $doctor_id, $doctor_branch_id]);
        $visit_id = $db->lastInsertId();
        
        $stmt = $db->prepare("
            SELECT v.*, 
                   p.id as patient_id,
                   p.full_name as patient_name,
                   p.patient_id as patient_code,
                   p.phone,
                   p.email,
                   p.date_of_birth,
                   p.gender,
                   p.address,
                   p.blood_group,
                   p.allergies,
                   p.emergency_contact,
                   p.created_at as patient_registered,
                   u.full_name as doctor_name,
                   b.name as branch_name
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

// ================================================================
// GET OR CREATE BILL (BACKGROUND - DOCTOR DOESN'T SEE)
// ================================================================
$bill_id = null;
$bill_items_count = 0;

try {
    $stmt = $db->prepare("SELECT id, status FROM patient_bills WHERE visit_id = ?");
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
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM bill_items WHERE bill_id = ?");
    $stmt->execute([$bill_id]);
    $bill_items_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (Exception $e) {
    error_log("Bill error: " . $e->getMessage());
}

// ================================================================
// GET SERVICES
// ================================================================
$services_list = [];
try {
    $stmt = $db->prepare("
        SELECT id, service_name, price, category_id 
        FROM services 
        WHERE is_active = 1
        ORDER BY service_name ASC
    ");
    $stmt->execute();
    $services_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $services_list = [];
}

// ================================================================
// GET LAB TESTS CATALOG
// ================================================================
$lab_tests_catalog = [];
try {
    $stmt = $db->prepare("
        SELECT id, test_name, price, category 
        FROM lab_tests_catalog 
        WHERE is_active = 1
        ORDER BY category, test_name
    ");
    $stmt->execute();
    $lab_tests_catalog = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $lab_tests_catalog = [];
}

// ================================================================
// GET PROCEDURAL SERVICES
// ================================================================
$procedural_services = [];
try {
    $stmt = $db->prepare("
        SELECT s.id, s.service_name, s.price, sc.category_name
        FROM services s
        LEFT JOIN service_categories sc ON s.category_id = sc.id
        WHERE s.is_active = 1 AND sc.category_name IN ('Procedures', 'Procedure', 'Surgery', 'Treatment')
        ORDER BY s.service_name
    ");
    $stmt->execute();
    $procedural_services = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $procedural_services = [];
}

// ================================================================
// GET MEDICATIONS FROM INVENTORY
// ================================================================
$medications_list = [];
try {
    $stmt = $db->prepare("
        SELECT id, medication_name, category, unit, selling_price, quantity 
        FROM medications_inventory 
        WHERE status = 'active' AND quantity > 0 AND branch_id = ?
        ORDER BY medication_name ASC
    ");
    $stmt->execute([$doctor_branch_id]);
    $medications_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($medications_list)) {
        error_log("No medications found for branch: " . $doctor_branch_id);
    }
} catch (Exception $e) {
    error_log("Medications fetch error: " . $e->getMessage());
    $medications_list = [];
}

// ================================================================
// GET SELECTED MEDICATIONS FOR THIS VISIT (FIXED: medication column)
// ================================================================
$selected_medications = [];
try {
    $stmt = $db->prepare("
        SELECT id, medication, dosage, frequency, duration, route, quantity, instructions, status 
        FROM prescriptions 
        WHERE visit_id = ? AND status IN ('pending', 'dispensed')
        ORDER BY created_at DESC
    ");
    $stmt->execute([$visit_id]);
    $selected_medications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Selected medications fetch error: " . $e->getMessage());
    $selected_medications = [];
}

// ================================================================
// GET BILL ITEMS
// ================================================================
$bill_items = [];
try {
    $stmt = $db->prepare("
        SELECT id, item_name, item_type, quantity, total_price 
        FROM bill_items 
        WHERE bill_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$bill_id]);
    $bill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $bill_items = [];
}

// ================================================================
// GET LAB REQUESTS & RESULTS
// ================================================================
$lab_requests = [];
$lab_results = [];
$lab_results_available = false;

try {
    $stmt = $db->prepare("
        SELECT * FROM lab_tests 
        WHERE visit_id = ? AND status IN ('pending', 'in_progress')
        ORDER BY created_at DESC
    ");
    $stmt->execute([$visit_id]);
    $lab_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $db->prepare("
        SELECT * FROM lab_tests 
        WHERE visit_id = ? AND status = 'completed'
        ORDER BY completed_at DESC
    ");
    $stmt->execute([$visit_id]);
    $lab_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $lab_results_available = count($lab_results) > 0;
} catch (Exception $e) {
    $lab_requests = [];
    $lab_results = [];
}

// ================================================================
// GET PATIENT HISTORY
// ================================================================
$patient_history = [];
try {
    $stmt = $db->prepare("
        SELECT v.*, u.full_name as doctor_name 
        FROM visits v
        LEFT JOIN users u ON v.doctor_id = u.id
        WHERE v.patient_id = ? AND v.status = 'completed'
        ORDER BY v.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$patient_id]);
    $patient_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $patient_history = [];
}

// ================================================================
// COMMON SYMPTOMS FOR DROPDOWN
// ================================================================
$common_symptoms = [
    'Fever', 'Headache', 'Cough', 'Chest Pain', 'Vomiting', 'Diarrhea',
    'Abdominal Pain', 'Dizziness', 'Fatigue', 'Shortness of Breath',
    'Nausea', 'Muscle Pain', 'Joint Pain', 'Skin Rash', 'Sore Throat',
    'Runny Nose', 'Sneezing', 'Itching', 'Swelling', 'Redness',
    'Difficulty Breathing', 'Chest Tightness', 'Palpitations', 'Sweating',
    'Chills', 'Loss of Appetite', 'Weight Loss', 'Weight Gain',
    'Numbness', 'Tingling', 'Weakness', 'Confusion', 'Memory Loss',
    'Anxiety', 'Depression', 'Insomnia', 'Sleep Disturbance',
    'Ear Pain', 'Eye Pain', 'Blurred Vision', 'Hearing Loss',
    'Toothache', 'Mouth Sores', 'Sore Muscles', 'Stiff Neck',
    'Back Pain', 'Neck Pain', 'Shoulder Pain', 'Knee Pain',
    'Urinary Frequency', 'Painful Urination', 'Blood in Urine',
    'Constipation', 'Bloating', 'Heartburn', 'Acid Reflux'
];

// ================================================================
// HANDLE FORM SUBMISSIONS
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';
    
    // ================================================================
    // 1. UPDATE VISIT
    // ================================================================
    $symptoms = trim($_POST['symptoms'] ?? '');
    $diagnosis = trim($_POST['diagnosis'] ?? '');
    $treatment = trim($_POST['treatment'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $status = $_POST['status'] ?? 'with_doctor';
    
    $stmt = $db->prepare("
        UPDATE visits 
        SET symptoms = ?, diagnosis = ?, treatment = ?, notes = ?, status = ?, updated_at = NOW()
        WHERE id = ? AND doctor_id = ?
    ");
    $stmt->execute([$symptoms, $diagnosis, $treatment, $notes, $status, $visit_id, $doctor_id]);
    
    // ================================================================
    // 2. ADD SERVICE TO BILL (Background)
    // ================================================================
    if (isset($_POST['add_service'])) {
        $service_id = (int)($_POST['service_id'] ?? 0);
        if ($service_id > 0) {
            $stmt = $db->prepare("SELECT service_name, price FROM services WHERE id = ? AND is_active = 1");
            $stmt->execute([$service_id]);
            $service = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($service) {
                $stmt = $db->prepare("
                    SELECT id, quantity FROM bill_items 
                    WHERE bill_id = ? AND item_name = ? AND item_type = 'consultation'
                ");
                $stmt->execute([$bill_id, $service['service_name']]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing) {
                    $new_qty = $existing['quantity'] + 1;
                    $new_total = $service['price'] * $new_qty;
                    $stmt = $db->prepare("
                        UPDATE bill_items 
                        SET quantity = ?, total_price = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$new_qty, $new_total, $existing['id']]);
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO bill_items (bill_id, item_type, item_name, quantity, unit_price, total_price)
                        VALUES (?, 'consultation', ?, 1, ?, ?)
                    ");
                    $stmt->execute([$bill_id, $service['service_name'], $service['price'], $service['price']]);
                }
                
                // Update bill total
                $stmt = $db->prepare("SELECT SUM(total_price) as total FROM bill_items WHERE bill_id = ?");
                $stmt->execute([$bill_id]);
                $new_total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
                
                $stmt = $db->prepare("
                    UPDATE patient_bills 
                    SET subtotal = ?, total_amount = ?, balance = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$new_total, $new_total, $new_total, $bill_id]);
                
                $message = "✅ Service added successfully!";
                $message_type = 'success';
            }
        }
    }
    
    // ================================================================
    // 3. ADD PROCEDURAL SERVICE
    // ================================================================
    if (isset($_POST['add_procedure'])) {
        $procedure_id = (int)($_POST['procedure_id'] ?? 0);
        if ($procedure_id > 0) {
            $stmt = $db->prepare("SELECT service_name, price FROM services WHERE id = ? AND is_active = 1");
            $stmt->execute([$procedure_id]);
            $procedure = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($procedure) {
                $stmt = $db->prepare("
                    SELECT id, quantity FROM bill_items 
                    WHERE bill_id = ? AND item_name = ? AND item_type = 'procedure'
                ");
                $stmt->execute([$bill_id, $procedure['service_name']]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing) {
                    $new_qty = $existing['quantity'] + 1;
                    $new_total = $procedure['price'] * $new_qty;
                    $stmt = $db->prepare("
                        UPDATE bill_items 
                        SET quantity = ?, total_price = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$new_qty, $new_total, $existing['id']]);
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO bill_items (bill_id, item_type, item_name, quantity, unit_price, total_price)
                        VALUES (?, 'procedure', ?, 1, ?, ?)
                    ");
                    $stmt->execute([$bill_id, $procedure['service_name'], $procedure['price'], $procedure['price']]);
                }
                
                // Update bill total
                $stmt = $db->prepare("SELECT SUM(total_price) as total FROM bill_items WHERE bill_id = ?");
                $stmt->execute([$bill_id]);
                $new_total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
                
                $stmt = $db->prepare("
                    UPDATE patient_bills 
                    SET subtotal = ?, total_amount = ?, balance = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$new_total, $new_total, $new_total, $bill_id]);
                
                $message = "✅ Procedure added successfully!";
                $message_type = 'success';
            }
        }
    }
    
    // ================================================================
    // 4. REMOVE SERVICE/PROCEDURE
    // ================================================================
    if (isset($_POST['remove_service'])) {
        $item_id = (int)($_POST['item_id'] ?? 0);
        if ($item_id > 0) {
            $stmt = $db->prepare("DELETE FROM bill_items WHERE id = ? AND bill_id = ?");
            $stmt->execute([$item_id, $bill_id]);
            
            $stmt = $db->prepare("SELECT SUM(total_price) as total FROM bill_items WHERE bill_id = ?");
            $stmt->execute([$bill_id]);
            $new_total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            $stmt = $db->prepare("
                UPDATE patient_bills 
                SET subtotal = ?, total_amount = ?, balance = ? 
                WHERE id = ?
            ");
            $stmt->execute([$new_total, $new_total, $new_total, $bill_id]);
            
            $message = "✅ Item removed from bill";
            $message_type = 'info';
        }
    }
    
    // ================================================================
    // 5. ADD MEDICATION FROM INVENTORY (FIXED: uses 'medication' column)
    // ================================================================
    if (isset($_POST['add_medication'])) {
        $inventory_id = (int)($_POST['inventory_id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 1);
        $dosage = trim($_POST['dosage'] ?? '');
        $frequency = trim($_POST['frequency'] ?? '');
        $duration = trim($_POST['duration'] ?? '');
        $route = trim($_POST['route'] ?? '');
        $instructions = trim($_POST['instructions'] ?? '');
        
        if ($inventory_id > 0 && $quantity > 0) {
            $stmt = $db->prepare("
                SELECT medication_name, selling_price, unit, quantity as stock 
                FROM medications_inventory 
                WHERE id = ? AND status = 'active' AND branch_id = ?
            ");
            $stmt->execute([$inventory_id, $doctor_branch_id]);
            $med = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($med && $med['stock'] >= $quantity) {
                $prescription_number = 'PRES-' . date('Ymd') . '-' . str_pad($patient_id, 4, '0', STR_PAD_LEFT) . '-' . rand(100, 999);
                
                // FIXED: Using 'medication' column (not 'medication_name')
                $stmt = $db->prepare("
                    INSERT INTO prescriptions (
                        prescription_number, visit_id, patient_id, doctor_id, 
                        medication, dosage, frequency, duration, route, quantity, instructions,
                        status, branch_id, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
                ");
                $stmt->execute([
                    $prescription_number,
                    $visit_id,
                    $patient_id,
                    $doctor_id,
                    $med['medication_name'],
                    $dosage,
                    $frequency,
                    $duration,
                    $route,
                    $quantity,
                    $instructions,
                    $doctor_branch_id
                ]);
                
                // Reduce stock
                $new_stock = $med['stock'] - $quantity;
                $stmt = $db->prepare("UPDATE medications_inventory SET quantity = ? WHERE id = ?");
                $stmt->execute([$new_stock, $inventory_id]);
                
                $message = "✅ Medication added successfully!";
                $message_type = 'success';
                
                // Refresh selected medications
                $stmt = $db->prepare("
                    SELECT id, medication, dosage, frequency, duration, route, quantity, instructions, status 
                    FROM prescriptions 
                    WHERE visit_id = ? AND status IN ('pending', 'dispensed')
                    ORDER BY created_at DESC
                ");
                $stmt->execute([$visit_id]);
                $selected_medications = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
            } else {
                $message = "❌ Insufficient stock! Available: " . ($med['stock'] ?? 0);
                $message_type = 'error';
            }
        } else {
            $message = "❌ Please select a medication and quantity";
            $message_type = 'error';
        }
    }
    
    // ================================================================
    // 6. REMOVE MEDICATION
    // ================================================================
    if (isset($_POST['remove_medication'])) {
        $prescription_id = (int)($_POST['prescription_id'] ?? 0);
        if ($prescription_id > 0) {
            $stmt = $db->prepare("
                SELECT medication, quantity FROM prescriptions WHERE id = ? AND visit_id = ?
            ");
            $stmt->execute([$prescription_id, $visit_id]);
            $med = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($med) {
                // Restore stock
                $stmt = $db->prepare("
                    UPDATE medications_inventory 
                    SET quantity = quantity + ? 
                    WHERE medication_name = ? AND branch_id = ?
                ");
                $stmt->execute([$med['quantity'], $med['medication'], $doctor_branch_id]);
            }
            
            $stmt = $db->prepare("DELETE FROM prescriptions WHERE id = ? AND visit_id = ?");
            $stmt->execute([$prescription_id, $visit_id]);
            
            $message = "✅ Medication removed!";
            $message_type = 'info';
            
            // Refresh selected medications
            $stmt = $db->prepare("
                SELECT id, medication, dosage, frequency, duration, route, quantity, instructions, status 
                FROM prescriptions 
                WHERE visit_id = ? AND status IN ('pending', 'dispensed')
                ORDER BY created_at DESC
            ");
            $stmt->execute([$visit_id]);
            $selected_medications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    // ================================================================
    // 7. SEND LAB REQUESTS
    // ================================================================
    if (isset($_POST['lab_tests']) && is_array($_POST['lab_tests'])) {
        $stmt = $db->prepare("DELETE FROM lab_tests WHERE visit_id = ? AND status IN ('pending', 'in_progress')");
        $stmt->execute([$visit_id]);
        
        foreach ($_POST['lab_tests'] as $test_name) {
            $test_name = trim($test_name);
            if (!empty($test_name)) {
                $stmt = $db->prepare("
                    INSERT INTO lab_tests (
                        visit_id, patient_id, doctor_id, test_name, status, branch_id, created_at
                    ) VALUES (?, ?, ?, ?, 'pending', ?, NOW())
                ");
                $stmt->execute([$visit_id, $patient_id, $doctor_id, $test_name, $doctor_branch_id]);
            }
        }
        $message = "✅ Lab requests sent to Laboratory!";
        $message_type = 'success';
        
        // Refresh lab requests
        $stmt = $db->prepare("
            SELECT * FROM lab_tests 
            WHERE visit_id = ? AND status IN ('pending', 'in_progress')
            ORDER BY created_at DESC
        ");
        $stmt->execute([$visit_id]);
        $lab_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ================================================================
    // 8. SAVE FOLLOW-UP
    // ================================================================
    $followup_date = trim($_POST['followup_date'] ?? '');
    $followup_notes = trim($_POST['followup_notes'] ?? '');
    
    if (!empty($followup_date)) {
        try {
            $stmt = $db->prepare("
                INSERT INTO follow_ups (visit_id, patient_id, doctor_id, followup_date, notes, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE followup_date = ?, notes = ?, updated_at = NOW()
            ");
            $stmt->execute([$visit_id, $patient_id, $doctor_id, $followup_date, $followup_notes, $followup_date, $followup_notes]);
        } catch (Exception $e) {
            // Table might not exist
        }
    }
    
    // ================================================================
    // 9. SAVE REFERRAL
    // ================================================================
    $referral_hospital = trim($_POST['referral_hospital'] ?? '');
    $referral_reason = trim($_POST['referral_reason'] ?? '');
    
    if (!empty($referral_hospital)) {
        try {
            $stmt = $db->prepare("
                INSERT INTO referrals (visit_id, patient_id, doctor_id, hospital, reason, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'pending', NOW())
                ON DUPLICATE KEY UPDATE hospital = ?, reason = ?, updated_at = NOW()
            ");
            $stmt->execute([$visit_id, $patient_id, $doctor_id, $referral_hospital, $referral_reason, $referral_hospital, $referral_reason]);
        } catch (Exception $e) {
            // Table might not exist
        }
    }
    
    // ================================================================
    // 10. COMPLETE VISIT
    // ================================================================
    if ($action === 'complete') {
        $stmt = $db->prepare("
            UPDATE visits 
            SET status = 'completed', is_completed = 1, completed_at = NOW(), updated_at = NOW()
            WHERE id = ? AND doctor_id = ?
        ");
        $stmt->execute([$visit_id, $doctor_id]);
        
        $stmt = $db->prepare("
            UPDATE patient_bills 
            SET status = 'pending' 
            WHERE id = ?
        ");
        $stmt->execute([$bill_id]);
        
        $message = "✅ Visit completed! Bill sent to cashier.";
        $message_type = 'success';
        
        echo '<script>setTimeout(function(){ window.location.href = "my_patients.php?completed=1"; }, 2000);</script>';
    }
    
    // Update bill items count
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM bill_items WHERE bill_id = ?");
    $stmt->execute([$bill_id]);
    $bill_items_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
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
// HELPER FUNCTIONS
// ================================================================
function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}

function getUserColor($name) {
    $colors = ['#0B5ED7', '#059669', '#7C3AED', '#DC2626', '#D97706', '#0D9488', '#DB2777'];
    $index = abs(crc32($name)) % count($colors);
    return $colors[$index];
}

function getStatusBadgeClass($status) {
    $map = [
        'pending' => 'badge-warning',
        'assigned' => 'badge-info',
        'with_doctor' => 'badge-primary',
        'lab_test' => 'badge-warning',
        'prescribed' => 'badge-purple',
        'completed' => 'badge-success',
        'cancelled' => 'badge-danger'
    ];
    return $map[$status] ?? 'badge-info';
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
                <i class="fas fa-stethoscope"></i> Consultation
                <span class="page-badge"><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></span>
            </h1>
            <p class="page-subtitle">
                Patient: <strong><?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?></strong>
                <span class="patient-id">(<?= htmlspecialchars($visit['patient_code'] ?? 'N/A') ?>)</span>
                <span class="separator">|</span>
                Doctor: <strong><?= htmlspecialchars($doctor_name) ?></strong>
                <span class="separator">|</span>
                Status: 
                <span class="status-badge <?= getStatusBadgeClass($visit['status'] ?? 'pending') ?>">
                    <?= ucfirst(str_replace('_', ' ', $visit['status'] ?? 'Pending')) ?>
                </span>
                <?php if ($lab_results_available): ?>
                    <span class="status-badge badge-success ml-2">
                        <i class="fas fa-check-circle"></i> Lab Results Available
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div class="page-header-right">
            <a href="my_patients.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> My Patients
            </a>
            <button onclick="window.print()" class="btn btn-outline">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle') ?>"></i>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- CONSULTATION FORM -->
    <!-- ================================================================ -->
    <form method="POST" action="" id="consultationForm">

        <!-- ================================================================ -->
        <!-- SECTION 1: PATIENT INFORMATION -->
        <!-- ================================================================ -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

            <div class="consultation-card">
                <h3 class="card-title">
                    <i class="fas fa-user title-blue"></i> Patient Information
                </h3>
                <div class="patient-header">
                    <div class="patient-avatar-large" style="background: <?= getUserColor($visit['patient_name'] ?? 'Unknown') ?>;">
                        <?= strtoupper(substr($visit['patient_name'] ?? 'U', 0, 1)) ?>
                    </div>
                    <div class="patient-header-info">
                        <h4 class="patient-name"><?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?></h4>
                        <p class="patient-id">ID: <?= htmlspecialchars($visit['patient_code'] ?? 'N/A') ?></p>
                        <p class="patient-gender-age">
                            <?= htmlspecialchars($visit['gender'] ?? 'N/A') ?> • 
                            <?= calculateAge($visit['date_of_birth'] ?? '') ?> years
                        </p>
                    </div>
                </div>
                <div class="patient-info-grid">
                    <div><span class="info-label">Date of Birth</span><span class="info-value"><?= !empty($visit['date_of_birth']) ? date('M d, Y', strtotime($visit['date_of_birth'])) : 'N/A' ?></span></div>
                    <div><span class="info-label">Phone</span><span class="info-value"><?= htmlspecialchars($visit['phone'] ?? 'N/A') ?></span></div>
                    <div><span class="info-label">Blood Group</span><span class="info-value"><?= htmlspecialchars($visit['blood_group'] ?? 'N/A') ?></span></div>
                    <div><span class="info-label">Allergies</span><span class="info-value"><?= htmlspecialchars($visit['allergies'] ?? 'None') ?></span></div>
                    <div class="col-span-2"><span class="info-label">Address</span><span class="info-value"><?= htmlspecialchars($visit['address'] ?? 'N/A') ?></span></div>
                </div>
            </div>

            <div class="consultation-card">
                <h3 class="card-title">
                    <i class="fas fa-clinic-medical title-green"></i> Visit Information
                </h3>
                <div class="visit-info-grid">
                    <div><span class="info-label">Visit Number</span><span class="info-value font-mono"><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></span></div>
                    <div><span class="info-label">Visit Type</span><span class="info-value"><?= ucfirst($visit['visit_type'] ?? 'New') ?></span></div>
                    <div><span class="info-label">Date</span><span class="info-value"><?= date('M d, Y', strtotime($visit['created_at'])) ?></span></div>
                    <div><span class="info-label">Time</span><span class="info-value"><?= date('h:i A', strtotime($visit['created_at'])) ?></span></div>
                    <div><span class="info-label">Branch</span><span class="info-value"><?= htmlspecialchars($visit['branch_name'] ?? 'N/A') ?></span></div>
                    <div><span class="info-label">Queue</span><span class="info-value">#<?= sprintf('%03d', $visit['id'] ?? 1) ?></span></div>
                </div>
            </div>

        </div>

        <!-- ================================================================ -->
        <!-- SECTION 2: PATIENT HISTORY -->
        <!-- ================================================================ -->
        <div class="consultation-card mb-6">
            <h3 class="card-title">
                <i class="fas fa-history title-blue"></i> Patient History
                <span class="text-sm font-normal text-gray-400">(<?= count($patient_history) ?> previous visits)</span>
            </h3>
            <div class="history-grid">
                <div class="history-item">
                    <span class="history-label">Previous Diagnoses</span>
                    <ul class="history-list">
                        <?php 
                        $diagnoses_history = [];
                        foreach ($patient_history as $ph) {
                            if (!empty($ph['diagnosis'])) {
                                $diagnoses_history[] = $ph['diagnosis'];
                            }
                        }
                        if (count($diagnoses_history) > 0) {
                            foreach (array_slice($diagnoses_history, 0, 5) as $d) {
                                echo '<li>' . htmlspecialchars(substr($d, 0, 50)) . (strlen($d) > 50 ? '...' : '') . '</li>';
                            }
                        } else {
                            echo '<li class="text-gray-400">No previous diagnoses</li>';
                        }
                        ?>
                    </ul>
                </div>
                <div class="history-item">
                    <span class="history-label">Previous Visits</span>
                    <ul class="history-list">
                        <?php if (count($patient_history) > 0): ?>
                            <?php foreach (array_slice($patient_history, 0, 5) as $ph): ?>
                                <li><?= date('M d, Y', strtotime($ph['created_at'])) ?> - <?= ucfirst($ph['status'] ?? 'Pending') ?></li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="text-gray-400">No previous visits</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- ================================================================ -->
        <!-- SECTION 3: SYMPTOMS (Dropdown + Manual Input) -->
        <!-- ================================================================ -->
        <div class="consultation-card mb-6">
            <h3 class="card-title">
                <i class="fas fa-list-ul title-blue"></i> Symptoms
                <span class="text-sm font-normal text-gray-400">(Select from dropdown or type manually)</span>
            </h3>
            
            <div class="symptoms-container">
                <div class="form-row">
                    <div class="form-group flex-1">
                        <label class="form-label">Select Symptom</label>
                        <div class="symptom-select-wrapper">
                            <select id="symptomSelect" class="form-control" onchange="addSelectedSymptom()">
                                <option value="">-- Select a symptom --</option>
                                <?php foreach ($common_symptoms as $symptom): ?>
                                    <option value="<?= htmlspecialchars($symptom) ?>"><?= htmlspecialchars($symptom) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn btn-primary btn-sm" onclick="addSelectedSymptom()">
                                <i class="fas fa-plus"></i> Add
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="quick-symptoms mt-2">
                    <span class="text-xs text-gray-500">Quick add:</span>
                    <?php 
                    $quick_symptoms = ['Fever', 'Headache', 'Cough', 'Chest Pain', 'Vomiting', 'Diarrhea', 'Nausea', 'Fatigue'];
                    foreach ($quick_symptoms as $qs): 
                    ?>
                        <button type="button" class="btn btn-outline btn-xs" onclick="addSymptom('<?= htmlspecialchars($qs) ?>')">
                            <?= htmlspecialchars($qs) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                
                <div class="form-group mt-3">
                    <label class="form-label">Symptoms Description</label>
                    <textarea name="symptoms" id="symptomsText" class="form-control symptoms-textarea" rows="3" 
                              placeholder="Describe patient symptoms. You can type manually or select from dropdown above."><?= htmlspecialchars($visit['symptoms'] ?? '') ?></textarea>
                    <small class="text-xs text-gray-400">
                        <i class="fas fa-info-circle"></i> 
                        Selected symptoms will be added automatically. You can also type or edit manually.
                    </small>
                </div>
                
                <div class="selected-symptoms-tags mt-2" id="symptomsTags">
                    <?php 
                    $current_symptoms = explode(',', $visit['symptoms'] ?? '');
                    foreach ($current_symptoms as $s):
                        $s = trim($s);
                        if (!empty($s)): 
                    ?>
                        <span class="symptom-tag">
                            <?= htmlspecialchars($s) ?>
                            <span class="remove-symptom" onclick="removeSymptom('<?= htmlspecialchars($s) ?>')">&times;</span>
                        </span>
                    <?php endif; endforeach; ?>
                </div>
            </div>
        </div>

        <!-- ================================================================ -->
        <!-- SECTION 4: DIAGNOSIS + TREATMENT -->
        <!-- ================================================================ -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

            <div class="consultation-card">
                <h3 class="card-title">
                    <i class="fas fa-diagnoses title-blue"></i> Diagnosis
                </h3>
                <div class="form-group">
                    <label class="form-label">Diagnosis <span class="required">*</span></label>
                    <textarea name="diagnosis" class="form-control" rows="3" placeholder="Enter diagnosis..."><?= htmlspecialchars($visit['diagnosis'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="consultation-card">
                <h3 class="card-title">
                    <i class="fas fa-notes-medical title-green"></i> Treatment & Notes
                </h3>
                <div class="form-group">
                    <label class="form-label">Treatment Plan</label>
                    <textarea name="treatment" class="form-control" rows="3" placeholder="Enter treatment plan..."><?= htmlspecialchars($visit['treatment'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Additional Notes</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Any additional notes..."><?= htmlspecialchars($visit['notes'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Visit Status</label>
                    <select name="status" class="form-control">
                        <option value="with_doctor" <?= ($visit['status'] ?? '') === 'with_doctor' ? 'selected' : '' ?>>With Doctor</option>
                        <option value="lab_test" <?= ($visit['status'] ?? '') === 'lab_test' ? 'selected' : '' ?>>Lab Test</option>
                        <option value="prescribed" <?= ($visit['status'] ?? '') === 'prescribed' ? 'selected' : '' ?>>Prescribed</option>
                        <option value="completed" <?= ($visit['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Completed</option>
                    </select>
                </div>
            </div>

        </div>

        <!-- ================================================================ -->
        <!-- SECTION 5: LAB REQUESTS -->
        <!-- ================================================================ -->
        <div class="consultation-card mb-6">
            <h3 class="card-title">
                <i class="fas fa-flask title-blue"></i> Laboratory Requests
                <span class="text-sm font-normal text-gray-400">(<?= count($lab_requests) ?> pending)</span>
                <button type="button" class="btn btn-outline btn-sm ml-2" onclick="addLabTest()">
                    <i class="fas fa-plus"></i> Add Test
                </button>
            </h3>
            
            <div id="labTestsContainer" class="mt-3">
                <?php if (count($lab_requests) > 0): ?>
                    <?php foreach ($lab_requests as $lab): ?>
                        <div class="lab-test-row">
                            <input type="text" name="lab_tests[]" class="form-control" value="<?= htmlspecialchars($lab['test_name']) ?>" placeholder="Enter test name...">
                            <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove();">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="lab-test-row">
                        <select name="lab_tests[]" class="form-control">
                            <option value="">-- Select Test --</option>
                            <?php foreach ($lab_tests_catalog as $test): ?>
                                <option value="<?= htmlspecialchars($test['test_name']) ?>">
                                    <?= htmlspecialchars($test['test_name']) ?>
                                    <?php if (!empty($test['category'])): ?>
                                        (<?= htmlspecialchars($test['category']) ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove();">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="mt-3">
                <button type="submit" name="action" value="lab_request" class="btn btn-warning btn-sm">
                    <i class="fas fa-paper-plane"></i> Send to Laboratory
                </button>
                <span class="text-xs text-gray-400 ml-2"><?= count($lab_requests) ?> test(s) pending</span>
            </div>
        </div>

        <!-- ================================================================ -->
        <!-- SECTION 6: LAB RESULTS -->
        <!-- ================================================================ -->
        <div class="consultation-card mb-6 <?= $lab_results_available ? 'border-green-500' : '' ?>">
            <h3 class="card-title">
                <i class="fas fa-file-medical-alt title-green"></i> Laboratory Results
                <span class="text-sm font-normal text-gray-400">
                    <?php if ($lab_results_available): ?>
                        <span class="text-green-600">✅ <?= count($lab_results) ?> result(s) available</span>
                    <?php elseif (count($lab_requests) > 0): ?>
                        <span class="text-yellow-600">⏳ <?= count($lab_requests) ?> pending</span>
                    <?php else: ?>
                        <span class="text-gray-400">No lab results</span>
                    <?php endif; ?>
                </span>
            </h3>
            
            <?php if ($lab_results_available): ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Test Name</th>
                                <th>Result</th>
                                <th>Reference Range</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lab_results as $result): ?>
                                <tr>
                                    <td><?= htmlspecialchars($result['test_name'] ?? 'N/A') ?></td>
                                    <td class="font-medium"><?= htmlspecialchars($result['results'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($result['reference_range'] ?? '') ?></td>
                                    <td><span class="badge badge-success">Completed</span></td>
                                    <td><?= date('M d, Y', strtotime($result['completed_at'] ?? $result['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif (count($lab_requests) > 0): ?>
                <div class="text-center py-6 text-yellow-600">
                    <i class="fas fa-clock text-3xl block mb-2"></i>
                    <p><?= count($lab_requests) ?> lab request(s) pending</p>
                </div>
            <?php else: ?>
                <div class="text-center py-6 text-gray-400">
                    <i class="fas fa-flask text-3xl block mb-2"></i>
                    <p>No lab results available</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- ================================================================ -->
        <!-- SECTION 7: MEDICATIONS / PRESCRIPTION -->
        <!-- ================================================================ -->
        <div class="consultation-card mb-6">
            <h3 class="card-title">
                <i class="fas fa-prescription title-blue"></i> Medications
                <button type="button" class="btn btn-outline btn-sm ml-2" onclick="addMedicationRow()">
                    <i class="fas fa-plus"></i> Add Medication
                </button>
            </h3>
            
            <!-- Add Medication Form -->
            <div class="medication-form" id="medicationForm">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                    <div>
                        <label class="form-label">Medication <span class="required">*</span></label>
                        <select name="inventory_id" class="form-control" id="medicationSelect">
                            <option value="">Select Medication...</option>
                            <?php if (count($medications_list) > 0): ?>
                                <?php foreach ($medications_list as $med): ?>
                                    <option value="<?= $med['id'] ?>">
                                        <?= htmlspecialchars($med['medication_name'] ?? 'Unknown Medication') ?> 
                                        (<?= $med['quantity'] ?? 0 ?> available)
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled>No medications available</option>
                            <?php endif; ?>
                        </select>
                        <?php if (empty($medications_list)): ?>
                            <small class="text-xs text-danger">No medications in inventory. Please contact pharmacy.</small>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label class="form-label">Qty</label>
                        <input type="number" name="quantity" class="form-control" value="1" min="1" max="99" id="medQuantity">
                    </div>
                    <div>
                        <label class="form-label">Dosage</label>
                        <input type="text" name="dosage" class="form-control" placeholder="e.g. 500mg" id="medDosage">
                    </div>
                    <div>
                        <label class="form-label">Frequency</label>
                        <select name="frequency" class="form-control" id="medFrequency">
                            <option value="">Select</option>
                            <option value="Once Daily">Once Daily</option>
                            <option value="Twice Daily">Twice Daily</option>
                            <option value="Three Times Daily">Three Times Daily</option>
                            <option value="Four Times Daily">Four Times Daily</option>
                            <option value="Every 4 Hours">Every 4 Hours</option>
                            <option value="Every 6 Hours">Every 6 Hours</option>
                            <option value="Every 8 Hours">Every 8 Hours</option>
                            <option value="As Needed">As Needed</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-3">
                    <div>
                        <label class="form-label">Duration (Days)</label>
                        <input type="number" name="duration" class="form-control" value="7" min="1" max="90" id="medDuration">
                    </div>
                    <div>
                        <label class="form-label">Route</label>
                        <select name="route" class="form-control" id="medRoute">
                            <option value="">Select</option>
                            <option value="Oral">Oral</option>
                            <option value="Topical">Topical</option>
                            <option value="Injection">Injection</option>
                            <option value="IV">IV</option>
                            <option value="Sublingual">Sublingual</option>
                            <option value="Inhalation">Inhalation</option>
                            <option value="Rectal">Rectal</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Instructions</label>
                        <input type="text" name="instructions" class="form-control" placeholder="e.g. After meals" id="medInstructions">
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" name="add_medication" class="btn btn-primary" onclick="return validateMedication()">
                        <i class="fas fa-plus"></i> Add Medication
                    </button>
                </div>
            </div>
            
            <!-- Selected Medications List -->
            <div class="selected-medications mt-4">
                <h4 class="text-sm font-semibold text-gray-600 mb-2">
                    <i class="fas fa-list"></i> Selected Medications
                    <span class="text-xs text-gray-400">(<?= count($selected_medications) ?> items)</span>
                </h4>
                
                <?php if (count($selected_medications) > 0): ?>
                    <div class="medications-list">
                        <?php foreach ($selected_medications as $med): ?>
                            <div class="medication-item">
                                <div class="medication-item-info">
                                    <span class="med-name"><?= htmlspecialchars($med['medication'] ?? 'Unknown') ?></span>
                                    <span class="med-details">
                                        <?= htmlspecialchars($med['dosage'] ?? '') ?> • 
                                        <?= htmlspecialchars($med['frequency'] ?? '') ?> • 
                                        <?= htmlspecialchars($med['duration'] ?? '') ?> days
                                    </span>
                                    <span class="med-qty">x<?= $med['quantity'] ?? 0 ?></span>
                                </div>
                                <form method="POST" action="" style="display:inline;">
                                    <input type="hidden" name="prescription_id" value="<?= $med['id'] ?? 0 ?>">
                                    <button type="submit" name="remove_medication" class="btn-remove" title="Remove medication">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-prescription"></i>
                        <p>No medications added yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ================================================================ -->
        <!-- SECTION 8: PROCEDURAL SERVICES -->
        <!-- ================================================================ -->
        <div class="consultation-card mb-6">
            <h3 class="card-title">
                <i class="fas fa-syringe title-blue"></i> Procedural Services
                <button type="button" class="btn btn-outline btn-sm ml-2" onclick="addProcedureRow()">
                    <i class="fas fa-plus"></i> Add Procedure
                </button>
            </h3>
            
            <div class="procedure-form" id="procedureForm">
                <div class="form-row">
                    <div class="form-group flex-1">
                        <label class="form-label">Select Procedure</label>
                        <select name="procedure_id" class="form-control" id="procedureSelect">
                            <option value="">Choose a procedure...</option>
                            <?php foreach ($procedural_services as $proc): ?>
                                <option value="<?= $proc['id'] ?>">
                                    <?= htmlspecialchars($proc['service_name']) ?>
                                </option>
                            <?php endforeach; ?>
                            <?php if (empty($procedural_services)): ?>
                                <option value="" disabled>No procedures available</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-group add-btn-group">
                        <label>&nbsp;</label>
                        <button type="submit" name="add_procedure" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Procedure
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Selected Procedures List -->
            <div class="selected-procedures mt-3">
                <h4 class="text-sm font-semibold text-gray-600 mb-2">
                    <i class="fas fa-list"></i> Selected Procedures
                    <span class="text-xs text-gray-400">
                        (<?= count(array_filter($bill_items, function($item) { return ($item['item_type'] ?? '') === 'procedure'; })) ?> items)
                    </span>
                </h4>
                
                <?php 
                $procedures = array_filter($bill_items, function($item) { 
                    return ($item['item_type'] ?? '') === 'procedure'; 
                });
                ?>
                
                <?php if (count($procedures) > 0): ?>
                    <div class="procedures-list">
                        <?php foreach ($procedures as $item): ?>
                            <div class="procedure-item">
                                <span class="procedure-name"><?= htmlspecialchars($item['item_name']) ?></span>
                                <span class="procedure-qty">x<?= $item['quantity'] ?></span>
                                <form method="POST" action="" style="display:inline;">
                                    <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                    <button type="submit" name="remove_service" class="btn-remove" title="Remove procedure">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-syringe"></i>
                        <p>No procedures added yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ================================================================ -->
        <!-- SECTION 9: FOLLOW-UP + REFERRAL -->
        <!-- ================================================================ -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

            <div class="consultation-card">
                <h3 class="card-title">
                    <i class="fas fa-calendar-check title-blue"></i> Follow Up
                </h3>
                <div class="space-y-3">
                    <div>
                        <label class="form-label">Follow-up Date</label>
                        <input type="date" name="followup_date" class="form-control" value="<?= date('Y-m-d', strtotime('+7 days')) ?>">
                    </div>
                    <div>
                        <label class="form-label">Follow-up Notes</label>
                        <input type="text" name="followup_notes" class="form-control" placeholder="Notes...">
                    </div>
                </div>
            </div>

            <div class="consultation-card">
                <h3 class="card-title">
                    <i class="fas fa-ambulance title-blue"></i> Referral
                </h3>
                <div class="space-y-3">
                    <div>
                        <label class="form-label">Referral Hospital</label>
                        <input type="text" name="referral_hospital" class="form-control" placeholder="Hospital name...">
                    </div>
                    <div>
                        <label class="form-label">Referral Reason</label>
                        <input type="text" name="referral_reason" class="form-control" placeholder="Reason...">
                    </div>
                </div>
            </div>

        </div>

        <!-- ================================================================ -->
        <!-- SECTION 10: FORM ACTIONS -->
        <!-- ================================================================ -->
        <div class="consultation-card">
            <div class="form-actions">
                <button type="submit" name="action" value="save" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Draft
                </button>
                <button type="submit" name="action" value="complete" class="btn btn-success" 
                        onclick="return confirm('Complete this consultation?\n\nBill will be sent to Cashier.')">
                    <i class="fas fa-check-circle"></i> Complete & Send to Cashier
                </button>
                <button type="button" class="btn btn-outline" onclick="window.print()">
                    <i class="fas fa-print"></i> Print
                </button>
                <a href="my_patients.php" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </div>

    </form>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="separator">|</span>
            Consultation
            <span class="separator">|</span>
            <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>
            <span class="separator">|</span>
            <span id="footerTimestamp"><?= date('H:i:s') ?></span>
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
    /* ================================================================
       CONSULTATION STYLES
       ================================================================ */
    
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
    .page-subtitle strong { color: var(--text-primary); }
    .page-subtitle .patient-id { color: var(--text-secondary); font-size: 0.8rem; }
    .separator { color: var(--border-color); margin: 0 4px; }
    .ml-2 { margin-left: 8px; }
    
    .status-badge {
        display: inline-block;
        font-size: 0.7rem;
        font-weight: 600;
        padding: 2px 14px;
        border-radius: 20px;
    }
    .badge-warning { background: #FEF3C7; color: #D97706; }
    .badge-info { background: #E8F0FE; color: #0B5ED7; }
    .badge-primary { background: #E8F0FE; color: #0B5ED7; }
    .badge-success { background: #D1FAE5; color: #059669; }
    .badge-danger { background: #FEE2E2; color: #DC2626; }
    .badge-purple { background: #EDE9FE; color: #7C3AED; }
    
    [data-theme="dark"] .badge-warning { background: #3D2E0A; color: #FBBF24; }
    [data-theme="dark"] .badge-info { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .badge-primary { background: #1E3A5F; color: #6EA8FE; }
    [data-theme="dark"] .badge-success { background: #1A3A2A; color: #34D399; }
    [data-theme="dark"] .badge-danger { background: #3A1A1A; color: #F87171; }
    [data-theme="dark"] .badge-purple { background: #2D1A3A; color: #A78BFA; }
    
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
    
    .consultation-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 20px 24px;
        border: 2px solid var(--border-color);
        transition: all 0.3s ease;
        margin-bottom: 20px;
    }
    .consultation-card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 20px rgba(11, 94, 215, 0.08);
    }
    .consultation-card.border-green-500 { border-color: #059669; }
    
    .card-title {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
        border-bottom: 2px solid var(--border-color);
        padding-bottom: 12px;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 6px;
    }
    .title-blue { color: var(--primary); }
    .title-green { color: #059669; }
    
    .grid { display: grid; }
    .grid-cols-1 { grid-template-columns: 1fr; }
    .grid-cols-2 { grid-template-columns: 1fr 1fr; }
    .gap-6 { gap: 24px; }
    .gap-3 { gap: 12px; }
    .mb-6 { margin-bottom: 24px; }
    .mt-2 { margin-top: 8px; }
    .mt-3 { margin-top: 12px; }
    .mt-4 { margin-top: 16px; }
    .lg\:grid-cols-2 { grid-template-columns: 1fr 1fr; }
    .md\:grid-cols-3 { grid-template-columns: 1fr 1fr 1fr; }
    .md\:grid-cols-4 { grid-template-columns: 1fr 1fr 1fr 1fr; }
    
    .patient-header {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 12px 16px;
        background: var(--primary-bg);
        border-radius: 12px;
        margin-bottom: 16px;
    }
    [data-theme="dark"] .patient-header { background: #1E3A5F; }
    
    .patient-avatar-large {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        font-weight: 700;
        color: white;
        flex-shrink: 0;
    }
    .patient-header-info .patient-name { font-size: 1.1rem; font-weight: 600; color: var(--text-primary); }
    .patient-header-info .patient-id { font-size: 0.8rem; color: var(--text-secondary); font-family: monospace; }
    .patient-header-info .patient-gender-age { font-size: 0.85rem; color: var(--text-secondary); }
    
    .patient-info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 6px 16px;
    }
    .visit-info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 6px 16px;
    }
    .col-span-2 { grid-column: span 2; }
    
    .info-label {
        display: block;
        font-size: 0.65rem;
        color: var(--text-secondary);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    .info-value {
        display: block;
        font-size: 0.9rem;
        font-weight: 500;
        color: var(--text-primary);
    }
    .font-mono { font-family: monospace; }
    .text-sm { font-size: 0.875rem; }
    .text-xs { font-size: 0.75rem; }
    .text-gray-400 { color: var(--text-secondary); }
    .text-gray-500 { color: var(--text-secondary); }
    .text-gray-600 { color: var(--text-secondary); }
    .text-green-600 { color: #059669; }
    .text-yellow-600 { color: #D97706; }
    .text-blue-500 { color: var(--primary); }
    .text-danger { color: #EF4444; }
    .text-primary { color: var(--primary); }
    
    .history-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }
    .history-item .history-label {
        display: block;
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--text-secondary);
        margin-bottom: 4px;
    }
    .history-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .history-list li {
        font-size: 0.8rem;
        color: var(--text-primary);
        padding: 2px 0;
        border-bottom: 1px solid var(--border-color);
    }
    .history-list li:last-child { border-bottom: none; }
    .history-list li.text-gray-400 { color: var(--text-secondary); }
    
    .form-group { margin-bottom: 14px; }
    .form-label {
        display: block;
        font-size: 0.75rem;
        font-weight: 500;
        color: var(--text-secondary);
        margin-bottom: 4px;
    }
    .required { color: #EF4444; }
    
    .form-control {
        width: 100%;
        padding: 8px 12px;
        border: 2px solid var(--border-color);
        border-radius: 8px;
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
    .form-control::placeholder { color: var(--text-secondary); opacity: 0.6; }
    
    .form-row {
        display: flex;
        gap: 12px;
        align-items: flex-end;
    }
    .form-row .form-group { flex: 1; margin-bottom: 0; }
    .form-row .add-btn-group { flex: 0 0 auto; }
    
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 18px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.8rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
        text-decoration: none;
        background: transparent;
    }
    .btn-primary { background: var(--primary); color: white; }
    .btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3); }
    .btn-success { background: #059669; color: white; }
    .btn-success:hover { background: #047857; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3); }
    .btn-warning { background: #D97706; color: white; }
    .btn-warning:hover { background: #B45309; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3); }
    .btn-danger { background: #EF4444; color: white; padding: 4px 10px; font-size: 0.7rem; border-radius: 6px; }
    .btn-danger:hover { background: #DC2626; transform: scale(1.05); }
    .btn-outline { background: transparent; color: var(--text-secondary); border: 2px solid var(--border-color); }
    .btn-outline:hover { background: var(--bg-body); border-color: var(--primary); color: var(--primary); }
    .btn-sm { padding: 4px 12px; font-size: 0.7rem; border-radius: 6px; }
    .btn-xs { padding: 2px 10px; font-size: 0.65rem; border-radius: 4px; }
    
    .form-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        padding-top: 16px;
        margin-top: 16px;
        border-top: 2px solid var(--border-color);
    }
    
    .symptom-select-wrapper {
        display: flex;
        gap: 8px;
        align-items: center;
    }
    .symptom-select-wrapper select { flex: 1; }
    
    .quick-symptoms {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
        align-items: center;
    }
    
    .symptoms-textarea {
        transition: height 0.2s ease;
    }
    
    .selected-symptoms-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        padding: 4px 0;
        min-height: 30px;
    }
    
    .symptom-tag {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: var(--primary-bg);
        color: var(--primary);
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 500;
        border: 1px solid var(--primary);
    }
    .symptom-tag .remove-symptom {
        cursor: pointer;
        font-weight: 700;
        font-size: 1rem;
        color: var(--danger);
        transition: all 0.3s ease;
        line-height: 1;
    }
    .symptom-tag .remove-symptom:hover {
        transform: scale(1.3);
        color: #DC2626;
    }
    
    [data-theme="dark"] .symptom-tag {
        background: #1E3A5F;
        border-color: #6EA8FE;
        color: #6EA8FE;
    }
    [data-theme="dark"] .symptom-tag .remove-symptom {
        color: #F87171;
    }
    
    .lab-test-row {
        display: flex;
        gap: 8px;
        margin-bottom: 8px;
        align-items: center;
    }
    .lab-test-row .form-control { flex: 1; }
    
    .medication-form {
        background: var(--bg-body);
        border-radius: 12px;
        padding: 16px;
        border: 1px solid var(--border-color);
    }
    .selected-medications {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 12px 16px;
        border: 1px solid var(--border-color);
    }
    .medications-list { max-height: 200px; overflow-y: auto; }
    .medications-list::-webkit-scrollbar { width: 4px; }
    .medications-list::-webkit-scrollbar-track { background: var(--bg-body); border-radius: 4px; }
    .medications-list::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 4px; }
    
    .medication-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 8px 12px;
        border-bottom: 1px solid var(--border-color);
    }
    .medication-item:last-child { border-bottom: none; }
    .medication-item-info { flex: 1; }
    .med-name { font-weight: 600; font-size: 0.9rem; color: var(--text-primary); }
    .med-details { font-size: 0.75rem; color: var(--text-secondary); display: block; }
    .med-qty { font-size: 0.7rem; color: var(--text-secondary); background: var(--bg-body); padding: 1px 10px; border-radius: 12px; margin-left: 8px; }
    
    .procedure-form {
        background: var(--bg-body);
        border-radius: 12px;
        padding: 16px;
        border: 1px solid var(--border-color);
    }
    .selected-procedures {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 12px 16px;
        border: 1px solid var(--border-color);
    }
    .procedures-list { max-height: 150px; overflow-y: auto; }
    .procedures-list::-webkit-scrollbar { width: 4px; }
    .procedures-list::-webkit-scrollbar-track { background: var(--bg-body); border-radius: 4px; }
    .procedures-list::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 4px; }
    
    .procedure-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 6px 12px;
        border-bottom: 1px solid var(--border-color);
    }
    .procedure-item:last-child { border-bottom: none; }
    .procedure-name { font-weight: 500; font-size: 0.85rem; color: var(--text-primary); flex: 1; }
    .procedure-qty { font-size: 0.7rem; color: var(--text-secondary); background: var(--bg-body); padding: 1px 10px; border-radius: 12px; }
    
    .btn-remove {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        border: none;
        background: #FEE2E2;
        color: #DC2626;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .btn-remove:hover { background: #DC2626; color: white; transform: scale(1.1); }
    
    .empty-state {
        text-align: center;
        padding: 16px 10px;
        color: var(--text-secondary);
    }
    .empty-state i { font-size: 1.8rem; color: var(--border-color); display: block; margin-bottom: 4px; }
    .empty-state p { font-size: 0.85rem; margin: 2px 0; }
    
    .table-wrap { overflow-x: auto; }
    .data-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
    .data-table th { text-align: left; padding: 8px 12px; font-weight: 600; font-size: 0.7rem; text-transform: uppercase; color: var(--text-secondary); border-bottom: 2px solid var(--border-color); }
    .data-table td { padding: 8px 12px; border-bottom: 1px solid var(--border-color); color: var(--text-primary); }
    .data-table tr:hover td { background: var(--primary-bg); }
    
    .badge {
        padding: 2px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-block;
        color: white;
    }
    .badge-success { background: #059669; }
    .badge-warning { background: #D97706; }
    
    .space-y-3 > * + * { margin-top: 0.75rem; }
    .space-y-3 { margin-top: 0; }
    
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
    
    @media (max-width: 1200px) {
        .consultation-card { padding: 16px 18px; }
        .history-grid { grid-template-columns: 1fr; }
        .md\:grid-cols-3 { grid-template-columns: 1fr; }
        .md\:grid-cols-4 { grid-template-columns: 1fr 1fr; }
    }
    
    @media (max-width: 1024px) {
        .main-content { padding: 16px; }
        .lg\:grid-cols-2 { grid-template-columns: 1fr; }
    }
    
    @media (max-width: 768px) {
        .main-content { margin-left: 0; padding: 12px; }
        .page-header { flex-direction: column; }
        .page-header-right { width: 100%; }
        .page-header-right .btn { flex: 1; justify-content: center; }
        .patient-info-grid { grid-template-columns: 1fr; }
        .visit-info-grid { grid-template-columns: 1fr; }
        .form-row { flex-direction: column; }
        .form-row .form-group { width: 100%; }
        .form-actions { flex-direction: column; }
        .form-actions .btn { width: 100%; justify-content: center; }
        .lab-test-row { flex-direction: column; }
        .lab-test-row .form-control { width: 100%; }
        .history-grid { grid-template-columns: 1fr; }
        .patient-header { flex-direction: column; text-align: center; }
        .consultation-card { padding: 12px 14px; }
        .page-title { font-size: 1.2rem; }
        .md\:grid-cols-4 { grid-template-columns: 1fr 1fr; }
        .medication-item { flex-direction: column; align-items: flex-start; gap: 6px; }
        .procedure-item { flex-wrap: wrap; }
        .symptom-select-wrapper { flex-direction: column; }
        .symptom-select-wrapper .btn { width: 100%; justify-content: center; }
        .quick-symptoms { gap: 3px; }
        .quick-symptoms .btn-xs { font-size: 0.6rem; padding: 1px 8px; }
    }
    
    @media (max-width: 480px) {
        .md\:grid-cols-4 { grid-template-columns: 1fr; }
        .medication-form .grid { grid-template-columns: 1fr !important; }
    }
    
    @media print {
        .top-nav, .sidebar, .btn, .footer { display: none !important; }
        .main-content { margin: 0 !important; padding: 20px !important; }
        .consultation-card { border: 1px solid #ddd !important; box-shadow: none !important; page-break-inside: avoid; }
        .page-header { border-bottom: 2px solid #0B5ED7 !important; }
        .form-actions { display: none !important; }
    }
</style>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // SYMPTOMS MANAGEMENT
    // ================================================================
    
    function addSelectedSymptom() {
        var select = document.getElementById('symptomSelect');
        var symptom = select.value;
        if (symptom) {
            addSymptom(symptom);
            select.value = '';
        }
    }

    function addSymptom(symptom) {
        var textarea = document.getElementById('symptomsText');
        var currentText = textarea.value;
        var symptomsList = currentText.split(',').map(s => s.trim()).filter(s => s);
        
        if (symptomsList.includes(symptom)) {
            showToast('Info', '"' + symptom + '" already added', 'info');
            return;
        }
        
        symptomsList.push(symptom);
        textarea.value = symptomsList.join(', ');
        updateSymptomsTags();
        textarea.style.height = 'auto';
        textarea.style.height = textarea.scrollHeight + 'px';
    }

    function removeSymptom(symptom) {
        var textarea = document.getElementById('symptomsText');
        var currentText = textarea.value;
        var symptomsList = currentText.split(',').map(s => s.trim()).filter(s => s);
        symptomsList = symptomsList.filter(s => s !== symptom);
        textarea.value = symptomsList.join(', ');
        updateSymptomsTags();
    }

    function updateSymptomsTags() {
        var textarea = document.getElementById('symptomsText');
        var tagsContainer = document.getElementById('symptomsTags');
        var symptomsList = textarea.value.split(',').map(s => s.trim()).filter(s => s);
        
        tagsContainer.innerHTML = '';
        symptomsList.forEach(function(symptom) {
            var tag = document.createElement('span');
            tag.className = 'symptom-tag';
            tag.innerHTML = symptom + ' <span class="remove-symptom" onclick="removeSymptom(\'' + symptom.replace(/'/g, "\\'") + '\')">&times;</span>';
            tagsContainer.appendChild(tag);
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        var textarea = document.getElementById('symptomsText');
        if (textarea) {
            textarea.addEventListener('input', function() {
                updateSymptomsTags();
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
            });
            updateSymptomsTags();
        }
    });

    // ================================================================
    // ADD LAB TEST
    // ================================================================
    function addLabTest() {
        var container = document.getElementById('labTestsContainer');
        var row = document.createElement('div');
        row.className = 'lab-test-row';
        row.innerHTML = `
            <select name="lab_tests[]" class="form-control">
                <option value="">-- Select Test --</option>
                <?php foreach ($lab_tests_catalog as $test): ?>
                    <option value="<?= htmlspecialchars($test['test_name']) ?>">
                        <?= htmlspecialchars($test['test_name']) ?>
                        <?php if (!empty($test['category'])): ?>
                            (<?= htmlspecialchars($test['category']) ?>)
                        <?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove();">
                <i class="fas fa-times"></i>
            </button>
        `;
        container.appendChild(row);
    }

    // ================================================================
    // ADD MEDICATION ROW
    // ================================================================
    function addMedicationRow() {
        document.getElementById('medicationForm').scrollIntoView({ behavior: 'smooth' });
        document.getElementById('medicationSelect').focus();
    }

    // ================================================================
    // ADD PROCEDURE ROW
    // ================================================================
    function addProcedureRow() {
        document.getElementById('procedureForm').scrollIntoView({ behavior: 'smooth' });
        document.getElementById('procedureSelect').focus();
    }

    // ================================================================
    // VALIDATE MEDICATION
    // ================================================================
    function validateMedication() {
        var medSelect = document.getElementById('medicationSelect');
        var qty = parseInt(document.getElementById('medQuantity').value) || 0;
        
        if (!medSelect.value) {
            showToast('Error', 'Please select a medication', 'error');
            return false;
        }
        if (qty < 1) {
            showToast('Error', 'Quantity must be at least 1', 'error');
            return false;
        }
        return true;
    }

    // ================================================================
    // SHOW TOAST
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
    // DARK MODE
    // ================================================================
    if (localStorage.getItem('darkMode') === 'true') {
        document.documentElement.setAttribute('data-theme', 'dark');
    }

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

    console.log('%c👨‍⚕️ Consultation - <?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?>', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Visit: <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>', 'font-size:12px; color:#059669;');
    console.log('%c💊 Medications Available: <?= count($medications_list) ?>', 'font-size:12px; color:#34D399;');
    console.log('%c🧪 Lab Tests Available: <?= count($lab_tests_catalog) ?>', 'font-size:12px; color:#7C3AED;');
    console.log('%c💰 All fees go to cashier - Doctor sees nothing', 'font-size:12px; color:#0B5ED7;');
</script>

</body>
</html>