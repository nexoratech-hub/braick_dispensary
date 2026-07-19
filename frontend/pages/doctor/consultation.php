<?php
// ================================================================
// FILE: frontend/pages/doctor/consultation.php
// DOCTOR CONSULTATION - FULL VERSION
// - Lab Tests: pending_lab (Waiting for Laboratory)
// - Medications: Frozen until lab results available
// - Procedures & Tools: Toggle dropdown with 2-column layout
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
// GET OR CREATE BILL
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
// GET PROCEDURES FROM TABLE
// ================================================================
$procedures_list = [];
try {
    $stmt = $db->prepare("
        SELECT id, procedure_name, category, price, description 
        FROM procedures 
        WHERE is_active = 1 
        ORDER BY procedure_name
    ");
    $stmt->execute();
    $procedures_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Procedures fetch error: " . $e->getMessage());
    $procedures_list = [];
}

// ================================================================
// GET PROCEDURE TOOLS FROM DATABASE
// ================================================================
$procedure_tools = [];
try {
    $stmt = $db->prepare("
        SELECT id, procedure_name, tool_name, price 
        FROM procedure_tools 
        WHERE is_active = 1 
        ORDER BY procedure_name, tool_name
    ");
    $stmt->execute();
    $procedure_tools = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Procedure tools fetch error: " . $e->getMessage());
    $procedure_tools = [];
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
} catch (Exception $e) {
    error_log("Medications fetch error: " . $e->getMessage());
    $medications_list = [];
}

// ================================================================
// GET SELECTED MEDICATIONS
// ================================================================
$selected_medications = [];
try {
    $stmt = $db->prepare("
        SELECT id, medication, dosage, frequency, duration, route, quantity, instructions, status 
        FROM prescriptions 
        WHERE visit_id = ? AND status IN ('pending_pharmacy', 'dispensed')
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
        WHERE visit_id = ? AND status IN ('pending_lab', 'in_progress')
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
    error_log("Lab tests fetch error: " . $e->getMessage());
    $lab_requests = [];
    $lab_results = [];
}

// ================================================================
// CHECK IF MEDICATIONS ARE FROZEN (Lab tests pending)
// ================================================================
$medications_frozen = false;
if (count($lab_requests) > 0 && !$lab_results_available) {
    $medications_frozen = true;
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
// COMMON SYMPTOMS
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
    
    // ================================================================
    // AJAX: ADD MEDICATION (Only if not frozen)
    // ================================================================
    if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'add_medication') {
        header('Content-Type: application/json');
        
        if ($medications_frozen) {
            echo json_encode([
                'success' => false, 
                'message' => '❌ Cannot add medications. Lab tests pending!'
            ]);
            exit;
        }
        
        $inventory_id = (int)($_POST['inventory_id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 1);
        $dosage = trim($_POST['dosage'] ?? '');
        $frequency = trim($_POST['frequency'] ?? '');
        $duration = trim($_POST['duration'] ?? '');
        $route = trim($_POST['route'] ?? '');
        $instructions = trim($_POST['instructions'] ?? '');
        
        $response = ['success' => false, 'message' => '', 'medication' => null];
        
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
                
                $stmt = $db->prepare("
                    INSERT INTO prescriptions (
                        prescription_number, visit_id, patient_id, doctor_id, 
                        medication, dosage, frequency, duration, route, quantity, instructions,
                        status, branch_id, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_pharmacy', ?, NOW())
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
                
                $new_id = $db->lastInsertId();
                
                $new_stock = $med['stock'] - $quantity;
                $stmt = $db->prepare("UPDATE medications_inventory SET quantity = ? WHERE id = ?");
                $stmt->execute([$new_stock, $inventory_id]);
                
                $med_total = $med['selling_price'] * $quantity;
                $stmt = $db->prepare("
                    INSERT INTO bill_items (bill_id, item_type, item_name, quantity, unit_price, total_price)
                    VALUES (?, 'medication', ?, ?, ?, ?)
                ");
                $stmt->execute([$bill_id, $med['medication_name'], $quantity, $med['selling_price'], $med_total]);
                
                $stmt = $db->prepare("SELECT SUM(total_price) as total FROM bill_items WHERE bill_id = ?");
                $stmt->execute([$bill_id]);
                $new_total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
                
                $stmt = $db->prepare("
                    UPDATE patient_bills 
                    SET subtotal = ?, total_amount = ?, balance = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$new_total, $new_total, $new_total, $bill_id]);
                
                $response['success'] = true;
                $response['message'] = '✅ Medication added successfully!';
                $response['medication'] = [
                    'id' => $new_id,
                    'name' => $med['medication_name'],
                    'dosage' => $dosage,
                    'frequency' => $frequency,
                    'duration' => $duration,
                    'quantity' => $quantity
                ];
                
            } else {
                $response['message'] = '❌ Insufficient stock! Available: ' . ($med['stock'] ?? 0);
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
    if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'remove_medication') {
        header('Content-Type: application/json');
        
        $prescription_id = (int)($_POST['prescription_id'] ?? 0);
        $response = ['success' => false, 'message' => ''];
        
        if ($prescription_id > 0) {
            $stmt = $db->prepare("
                SELECT medication, quantity FROM prescriptions WHERE id = ? AND visit_id = ?
            ");
            $stmt->execute([$prescription_id, $visit_id]);
            $med = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($med) {
                $stmt = $db->prepare("
                    UPDATE medications_inventory 
                    SET quantity = quantity + ? 
                    WHERE medication_name = ? AND branch_id = ?
                ");
                $stmt->execute([$med['quantity'], $med['medication'], $doctor_branch_id]);
                
                $stmt = $db->prepare("
                    DELETE FROM bill_items 
                    WHERE bill_id = ? AND item_name = ? AND item_type = 'medication'
                ");
                $stmt->execute([$bill_id, $med['medication']]);
            }
            
            $stmt = $db->prepare("DELETE FROM prescriptions WHERE id = ? AND visit_id = ?");
            $stmt->execute([$prescription_id, $visit_id]);
            
            $stmt = $db->prepare("SELECT SUM(total_price) as total FROM bill_items WHERE bill_id = ?");
            $stmt->execute([$bill_id]);
            $new_total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            $stmt = $db->prepare("
                UPDATE patient_bills 
                SET subtotal = ?, total_amount = ?, balance = ? 
                WHERE id = ?
            ");
            $stmt->execute([$new_total, $new_total, $new_total, $bill_id]);
            
            $response['success'] = true;
            $response['message'] = '✅ Medication removed!';
        } else {
            $response['message'] = '❌ Invalid medication';
        }
        
        echo json_encode($response);
        exit;
    }
    
    // ================================================================
    // AJAX: CLEAR ALL MEDICATIONS
    // ================================================================
    if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'clear_medications') {
        header('Content-Type: application/json');
        
        $response = ['success' => false, 'message' => ''];
        
        $stmt = $db->prepare("
            SELECT medication, quantity FROM prescriptions WHERE visit_id = ? AND status = 'pending_pharmacy'
        ");
        $stmt->execute([$visit_id]);
        $meds = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($meds as $med) {
            $stmt = $db->prepare("
                UPDATE medications_inventory 
                SET quantity = quantity + ? 
                WHERE medication_name = ? AND branch_id = ?
            ");
            $stmt->execute([$med['quantity'], $med['medication'], $doctor_branch_id]);
        }
        
        $stmt = $db->prepare("
            DELETE FROM prescriptions WHERE visit_id = ? AND status = 'pending_pharmacy'
        ");
        $stmt->execute([$visit_id]);
        
        $stmt = $db->prepare("
            DELETE FROM bill_items 
            WHERE bill_id = ? AND item_type = 'medication'
        ");
        $stmt->execute([$bill_id]);
        
        $stmt = $db->prepare("SELECT SUM(total_price) as total FROM bill_items WHERE bill_id = ?");
        $stmt->execute([$bill_id]);
        $new_total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        $stmt = $db->prepare("
            UPDATE patient_bills 
            SET subtotal = ?, total_amount = ?, balance = ? 
            WHERE id = ?
        ");
        $stmt->execute([$new_total, $new_total, $new_total, $bill_id]);
        
        $response['success'] = true;
        $response['message'] = '✅ All medications cleared!';
        
        echo json_encode($response);
        exit;
    }
    
    // ================================================================
    // AJAX: ADD PROCEDURE
    // ================================================================
    if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'add_procedure') {
        header('Content-Type: application/json');
        
        $procedure_id = (int)($_POST['procedure_id'] ?? 0);
        $response = ['success' => false, 'message' => '', 'procedure' => null];
        
        if ($procedure_id > 0) {
            $stmt = $db->prepare("
                SELECT id, procedure_name, price, description 
                FROM procedures 
                WHERE id = ? AND is_active = 1
            ");
            $stmt->execute([$procedure_id]);
            $procedure = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($procedure) {
                $item_name = $procedure['procedure_name'];
                $item_price = $procedure['price'];
                
                $stmt = $db->prepare("
                    SELECT id, quantity FROM bill_items 
                    WHERE bill_id = ? AND item_name = ? AND item_type = 'procedure'
                ");
                $stmt->execute([$bill_id, $item_name]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing) {
                    $new_qty = $existing['quantity'] + 1;
                    $new_total = $item_price * $new_qty;
                    $stmt = $db->prepare("
                        UPDATE bill_items 
                        SET quantity = ?, total_price = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$new_qty, $new_total, $existing['id']]);
                    $proc_id = $existing['id'];
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO bill_items (bill_id, item_type, item_name, quantity, unit_price, total_price)
                        VALUES (?, 'procedure', ?, 1, ?, ?)
                    ");
                    $stmt->execute([$bill_id, $item_name, $item_price, $item_price]);
                    $proc_id = $db->lastInsertId();
                }
                
                $stmt = $db->prepare("SELECT SUM(total_price) as total FROM bill_items WHERE bill_id = ?");
                $stmt->execute([$bill_id]);
                $new_total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
                
                $stmt = $db->prepare("
                    UPDATE patient_bills 
                    SET subtotal = ?, total_amount = ?, balance = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$new_total, $new_total, $new_total, $bill_id]);
                
                $response['success'] = true;
                $response['message'] = '✅ Procedure added successfully!';
                $response['procedure'] = [
                    'id' => $proc_id,
                    'name' => $item_name,
                    'quantity' => $existing ? $existing['quantity'] + 1 : 1
                ];
            } else {
                $response['message'] = '❌ Procedure not found';
            }
        } else {
            $response['message'] = '❌ Please select a procedure';
        }
        
        echo json_encode($response);
        exit;
    }
    
    // ================================================================
    // AJAX: ADD TOOL
    // ================================================================
    if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'add_tool') {
        header('Content-Type: application/json');
        
        $tool_id = (int)($_POST['tool_id'] ?? 0);
        $response = ['success' => false, 'message' => '', 'tool' => null];
        
        if ($tool_id > 0) {
            $stmt = $db->prepare("
                SELECT id, procedure_name, tool_name, price 
                FROM procedure_tools 
                WHERE id = ? AND is_active = 1
            ");
            $stmt->execute([$tool_id]);
            $tool = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($tool) {
                $item_name = $tool['procedure_name'] . ' - ' . $tool['tool_name'];
                $item_price = $tool['price'];
                
                $stmt = $db->prepare("
                    SELECT id, quantity FROM bill_items 
                    WHERE bill_id = ? AND item_name = ? AND item_type = 'tool'
                ");
                $stmt->execute([$bill_id, $item_name]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing) {
                    $new_qty = $existing['quantity'] + 1;
                    $new_total = $item_price * $new_qty;
                    $stmt = $db->prepare("
                        UPDATE bill_items 
                        SET quantity = ?, total_price = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$new_qty, $new_total, $existing['id']]);
                    $tool_db_id = $existing['id'];
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO bill_items (bill_id, item_type, item_name, quantity, unit_price, total_price)
                        VALUES (?, 'tool', ?, 1, ?, ?)
                    ");
                    $stmt->execute([$bill_id, $item_name, $item_price, $item_price]);
                    $tool_db_id = $db->lastInsertId();
                }
                
                $stmt = $db->prepare("SELECT SUM(total_price) as total FROM bill_items WHERE bill_id = ?");
                $stmt->execute([$bill_id]);
                $new_total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
                
                $stmt = $db->prepare("
                    UPDATE patient_bills 
                    SET subtotal = ?, total_amount = ?, balance = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$new_total, $new_total, $new_total, $bill_id]);
                
                $response['success'] = true;
                $response['message'] = '✅ Tool added successfully!';
                $response['tool'] = [
                    'id' => $tool_db_id,
                    'name' => $item_name,
                    'quantity' => $existing ? $existing['quantity'] + 1 : 1
                ];
            } else {
                $response['message'] = '❌ Tool not found';
            }
        } else {
            $response['message'] = '❌ Please select a tool';
        }
        
        echo json_encode($response);
        exit;
    }
    
    // ================================================================
    // AJAX: REMOVE ITEM
    // ================================================================
    if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'remove_item') {
        header('Content-Type: application/json');
        
        $item_id = (int)($_POST['item_id'] ?? 0);
        $response = ['success' => false, 'message' => ''];
        
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
            
            $response['success'] = true;
            $response['message'] = '✅ Item removed from bill!';
        } else {
            $response['message'] = '❌ Invalid item';
        }
        
        echo json_encode($response);
        exit;
    }
    
    // ================================================================
    // AJAX: CLEAR ALL ITEMS (FIXED)
    // ================================================================
    if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'clear_all_items') {
        header('Content-Type: application/json');
        
        $item_ids = isset($_POST['item_ids']) ? $_POST['item_ids'] : [];
        $response = ['success' => false, 'message' => ''];
        
        if (!empty($item_ids) && is_array($item_ids)) {
            $deleted = 0;
            foreach ($item_ids as $item_id) {
                $item_id = (int)$item_id;
                $stmt = $db->prepare("DELETE FROM bill_items WHERE id = ? AND bill_id = ?");
                $stmt->execute([$item_id, $bill_id]);
                $deleted++;
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
            
            $response['success'] = true;
            $response['message'] = '✅ ' . $deleted . ' item(s) cleared from bill!';
        } else {
            $response['message'] = '❌ No items to clear';
        }
        
        echo json_encode($response);
        exit;
    }
    
    // ================================================================
    // 1. UPDATE VISIT (Save Draft)
    // ================================================================
    if (isset($_POST['save_draft'])) {
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
        
        $message = "✅ Draft saved successfully!";
        $message_type = 'success';
    }
    
    // ================================================================
    // 2. SEND LAB REQUESTS
    // ================================================================
    if (isset($_POST['send_lab']) && isset($_POST['lab_tests']) && is_array($_POST['lab_tests'])) {
        $stmt = $db->prepare("DELETE FROM lab_tests WHERE visit_id = ? AND status IN ('pending_lab', 'in_progress')");
        $stmt->execute([$visit_id]);
        
        $stmt = $db->prepare("
            DELETE FROM bill_items 
            WHERE bill_id = ? AND item_type = 'lab_test'
        ");
        $stmt->execute([$bill_id]);
        
        $lab_tests_sent = 0;
        foreach ($_POST['lab_tests'] as $test_name) {
            $test_name = trim($test_name);
            if (!empty($test_name)) {
                $test_price = 0;
                foreach ($lab_tests_catalog as $cat_test) {
                    if ($cat_test['test_name'] === $test_name) {
                        $test_price = $cat_test['price'];
                        break;
                    }
                }
                
                $stmt = $db->prepare("
                    INSERT INTO lab_tests (
                        visit_id, doctor_id, test_name, status, branch_id, created_at
                    ) VALUES (?, ?, ?, 'pending_lab', ?, NOW())
                ");
                $stmt->execute([$visit_id, $doctor_id, $test_name, $doctor_branch_id]);
                $lab_tests_sent++;
                
                if ($test_price > 0) {
                    $stmt = $db->prepare("
                        INSERT INTO bill_items (bill_id, item_type, item_name, quantity, unit_price, total_price)
                        VALUES (?, 'lab_test', ?, 1, ?, ?)
                    ");
                    $stmt->execute([$bill_id, $test_name, $test_price, $test_price]);
                }
            }
        }
        
        $stmt = $db->prepare("SELECT SUM(total_price) as total FROM bill_items WHERE bill_id = ?");
        $stmt->execute([$bill_id]);
        $new_total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        $stmt = $db->prepare("
            UPDATE patient_bills 
            SET subtotal = ?, total_amount = ?, balance = ? 
            WHERE id = ?
        ");
        $stmt->execute([$new_total, $new_total, $new_total, $bill_id]);
        
        $message = "✅ " . $lab_tests_sent . " lab request(s) sent to Laboratory!";
        $message_type = 'success';
        
        $stmt = $db->prepare("
            SELECT * FROM lab_tests 
            WHERE visit_id = ? AND status IN ('pending_lab', 'in_progress')
            ORDER BY created_at DESC
        ");
        $stmt->execute([$visit_id]);
        $lab_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($lab_requests) > 0 && !$lab_results_available) {
            $medications_frozen = true;
        }
    }
    
    // ================================================================
    // 3. COMPLETE VISIT
    // ================================================================
    if (isset($_POST['complete_visit'])) {
        if (isset($_POST['lab_tests']) && is_array($_POST['lab_tests'])) {
            $stmt = $db->prepare("DELETE FROM lab_tests WHERE visit_id = ? AND status IN ('pending_lab', 'in_progress')");
            $stmt->execute([$visit_id]);
            
            $stmt = $db->prepare("
                DELETE FROM bill_items 
                WHERE bill_id = ? AND item_type = 'lab_test'
            ");
            $stmt->execute([$bill_id]);
            
            foreach ($_POST['lab_tests'] as $test_name) {
                $test_name = trim($test_name);
                if (!empty($test_name)) {
                    $test_price = 0;
                    foreach ($lab_tests_catalog as $cat_test) {
                        if ($cat_test['test_name'] === $test_name) {
                            $test_price = $cat_test['price'];
                            break;
                        }
                    }
                    
                    $stmt = $db->prepare("
                        INSERT INTO lab_tests (
                            visit_id, doctor_id, test_name, status, branch_id, created_at
                        ) VALUES (?, ?, ?, 'pending_lab', ?, NOW())
                    ");
                    $stmt->execute([$visit_id, $doctor_id, $test_name, $doctor_branch_id]);
                    
                    if ($test_price > 0) {
                        $stmt = $db->prepare("
                            INSERT INTO bill_items (bill_id, item_type, item_name, quantity, unit_price, total_price)
                            VALUES (?, 'lab_test', ?, 1, ?, ?)
                        ");
                        $stmt->execute([$bill_id, $test_name, $test_price, $test_price]);
                    }
                }
            }
            
            $stmt = $db->prepare("SELECT SUM(total_price) as total FROM bill_items WHERE bill_id = ?");
            $stmt->execute([$bill_id]);
            $new_total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            $stmt = $db->prepare("
                UPDATE patient_bills 
                SET subtotal = ?, total_amount = ?, balance = ? 
                WHERE id = ?
            ");
            $stmt->execute([$new_total, $new_total, $new_total, $bill_id]);
        }
        
        $stmt = $db->prepare("
            UPDATE visits 
            SET status = 'completed', is_completed = 1, completed_at = NOW(), updated_at = NOW()
            WHERE id = ? AND doctor_id = ?
        ");
        $stmt->execute([$visit_id, $doctor_id]);
        
        $stmt = $db->prepare("
            UPDATE patient_bills 
            SET status = 'pending_cashier' 
            WHERE id = ?
        ");
        $stmt->execute([$bill_id]);
        
        $message = "✅ Visit completed! All bills sent to cashier.";
        $message_type = 'success';
        
        echo '<script>setTimeout(function(){ window.location.href = "my_patients.php?completed=1"; }, 2000);</script>';
    }
    
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
        'pending_pharmacy' => 'badge-warning',
        'pending_lab' => 'badge-warning',
        'pending_cashier' => 'badge-warning',
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

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultation - Braick Dispensary</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           CONSULTATION STYLES
           ================================================================ */
        :root {
            --primary: #0B5ED7;
            --primary-dark: #0A4CA8;
            --primary-light: #6EA8FE;
            --primary-bg: #E8F0FE;
            --success: #059669;
            --success-dark: #047857;
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
            --radius: 10px;
            --radius-lg: 14px;
            --transition: all 0.3s ease;
        }
        
        * { box-sizing: border-box; }
        
        body {
            background: var(--gray-50);
            color: var(--gray-800);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
        }
        
        [data-theme="dark"] body {
            background: var(--gray-900);
            color: var(--gray-100);
        }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
            background: var(--gray-50);
            color: var(--gray-800);
            transition: var(--transition);
        }
        
        [data-theme="dark"] .main-content {
            background: var(--gray-900);
            color: var(--gray-100);
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 24px;
            padding: 20px 24px;
            background: #ffffff;
            border-radius: var(--radius-lg);
            border-bottom: 3px solid var(--primary);
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        
        [data-theme="dark"] .page-header { background: var(--gray-800); }
        
        .page-header-left { flex: 1; }
        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        [data-theme="dark"] .page-title { color: var(--gray-100); }
        .page-title i { color: var(--primary); }
        .page-badge {
            font-size: 0.7rem;
            font-weight: 600;
            background: var(--primary-bg);
            color: var(--primary);
            padding: 4px 16px;
            border-radius: 20px;
            font-family: monospace;
        }
        [data-theme="dark"] .page-badge { background: #1E3A5F; color: var(--primary-light); }
        
        .page-subtitle {
            font-size: 0.9rem;
            color: var(--gray-500);
            margin-top: 6px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
        }
        .page-subtitle strong { color: var(--gray-700); }
        [data-theme="dark"] .page-subtitle strong { color: var(--gray-200); }
        .page-subtitle .patient-id { color: var(--gray-400); font-size: 0.8rem; font-family: monospace; }
        .separator { color: var(--gray-300); margin: 0 4px; }
        .ml-2 { margin-left: 8px; }
        
        .page-header-right { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        
        .status-badge {
            display: inline-block;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 4px 16px;
            border-radius: 20px;
            text-transform: capitalize;
        }
        .badge-warning { background: var(--warning-bg); color: var(--warning); }
        .badge-info { background: var(--primary-bg); color: var(--primary); }
        .badge-success { background: var(--success-bg); color: var(--success); }
        .badge-danger { background: var(--danger-bg); color: var(--danger); }
        .badge-purple { background: var(--purple-bg); color: var(--purple); }
        
        [data-theme="dark"] .badge-warning { background: #3D2E0A; color: #FBBF24; }
        [data-theme="dark"] .badge-info { background: #1E3A5F; color: var(--primary-light); }
        [data-theme="dark"] .badge-success { background: #1A3A2A; color: #34D399; }
        [data-theme="dark"] .badge-danger { background: #3A1A1A; color: #F87171; }
        [data-theme="dark"] .badge-purple { background: #2D1A3A; color: #A78BFA; }
        
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
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .alert-success { background: var(--success-bg); color: var(--success); border-color: var(--success); }
        .alert-error { background: var(--danger-bg); color: var(--danger); border-color: var(--danger); }
        .alert-warning { background: var(--warning-bg); color: var(--warning); border-color: var(--warning); }
        .alert-info { background: var(--primary-bg); color: var(--primary); border-color: var(--primary); }
        [data-theme="dark"] .alert-success { background: #1A3A2A; color: #34D399; border-color: #34D399; }
        [data-theme="dark"] .alert-error { background: #3A1A1A; color: #F87171; border-color: #F87171; }
        [data-theme="dark"] .alert-warning { background: #3D2E0A; color: #FBBF24; border-color: #FBBF24; }
        [data-theme="dark"] .alert-info { background: #1E3A5F; color: var(--primary-light); border-color: var(--primary-light); }
        
        .consultation-card {
            background: #ffffff;
            border-radius: var(--radius-lg);
            padding: 24px 28px;
            border: 1px solid var(--gray-200);
            transition: var(--transition);
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .consultation-card:hover { border-color: var(--primary); box-shadow: 0 4px 12px rgba(11,94,215,0.08); }
        [data-theme="dark"] .consultation-card { background: var(--gray-800); border-color: var(--gray-700); }
        
        .card-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-800);
            border-bottom: 2px solid var(--gray-200);
            padding-bottom: 14px;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        [data-theme="dark"] .card-title { color: var(--gray-100); border-color: var(--gray-700); }
        .title-blue { color: var(--primary); }
        .title-green { color: var(--success); }
        .title-purple { color: var(--purple); }
        .title-orange { color: var(--warning); }
        
        /* Toggle Dropdown */
        .toggle-section {
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            margin-bottom: 12px;
            overflow: hidden;
            transition: var(--transition);
        }
        [data-theme="dark"] .toggle-section { border-color: var(--gray-700); }
        
        .toggle-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 18px;
            background: var(--gray-50);
            cursor: pointer;
            transition: var(--transition);
            user-select: none;
        }
        [data-theme="dark"] .toggle-header { background: var(--gray-800); }
        
        .toggle-header:hover { background: var(--primary-bg); }
        [data-theme="dark"] .toggle-header:hover { background: #1E3A5F; }
        
        .toggle-header .toggle-title {
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--gray-700);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        [data-theme="dark"] .toggle-header .toggle-title { color: var(--gray-200); }
        
        .toggle-header .toggle-icon {
            transition: var(--transition);
            color: var(--gray-400);
            font-size: 0.8rem;
        }
        .toggle-header.open .toggle-icon { transform: rotate(180deg); }
        
        .toggle-body {
            padding: 0 18px 18px 18px;
            display: none;
            animation: slideDown 0.3s ease;
        }
        .toggle-body.open { display: block; }
        
        /* Two Column Layout */
        .row-2col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        
        @media (max-width: 768px) {
            .row-2col { grid-template-columns: 1fr; }
        }
        
        .patient-header {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 16px 20px;
            background: var(--primary-bg);
            border-radius: var(--radius);
            margin-bottom: 18px;
        }
        [data-theme="dark"] .patient-header { background: #1E3A5F; }
        
        .patient-avatar-large {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 700;
            color: white;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .patient-header-info .patient-name { font-size: 1.2rem; font-weight: 600; color: var(--gray-800); }
        [data-theme="dark"] .patient-header-info .patient-name { color: var(--gray-100); }
        .patient-header-info .patient-id { font-size: 0.8rem; color: var(--gray-500); font-family: monospace; }
        .patient-header-info .patient-gender-age { font-size: 0.85rem; color: var(--gray-500); }
        
        .grid { display: grid; }
        .grid-cols-1 { grid-template-columns: 1fr; }
        .grid-cols-2 { grid-template-columns: 1fr 1fr; }
        .gap-6 { gap: 24px; }
        .gap-3 { gap: 12px; }
        .gap-4 { gap: 16px; }
        .mb-6 { margin-bottom: 24px; }
        .mt-2 { margin-top: 8px; }
        .mt-3 { margin-top: 12px; }
        .mt-4 { margin-top: 16px; }
        .col-span-2 { grid-column: span 2; }
        
        .patient-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px 20px;
        }
        .visit-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 8px 20px;
        }
        .info-label {
            display: block;
            font-size: 0.65rem;
            color: var(--gray-500);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .info-value {
            display: block;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--gray-800);
        }
        [data-theme="dark"] .info-value { color: var(--gray-200); }
        .font-mono { font-family: 'Courier New', monospace; }
        .text-sm { font-size: 0.875rem; }
        .text-xs { font-size: 0.75rem; }
        .text-gray-400 { color: var(--gray-400); }
        .text-gray-500 { color: var(--gray-500); }
        .text-yellow-600 { color: var(--warning); }
        .text-green-600 { color: var(--success); }
        .text-danger { color: var(--danger); }
        
        .history-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .history-item .history-label { display: block; font-size: 0.7rem; font-weight: 600; color: var(--gray-500); margin-bottom: 6px; }
        .history-list { list-style: none; padding: 0; margin: 0; }
        .history-list li {
            font-size: 0.8rem;
            color: var(--gray-700);
            padding: 4px 0;
            border-bottom: 1px solid var(--gray-200);
        }
        [data-theme="dark"] .history-list li { color: var(--gray-300); border-color: var(--gray-700); }
        .history-list li:last-child { border-bottom: none; }
        
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 0.75rem; font-weight: 600; color: var(--gray-600); margin-bottom: 5px; }
        [data-theme="dark"] .form-label { color: var(--gray-400); }
        .required { color: var(--danger); margin-left: 2px; }
        
        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius);
            font-size: 0.85rem;
            background: #ffffff;
            color: var(--gray-800);
            outline: none;
            transition: var(--transition);
        }
        [data-theme="dark"] .form-control {
            background: var(--gray-900);
            border-color: var(--gray-700);
            color: var(--gray-100);
        }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(11,94,215,0.12); }
        .form-control::placeholder { color: var(--gray-400); opacity: 0.6; }
        
        .form-row { display: flex; gap: 12px; align-items: flex-end; }
        .form-row .form-group { flex: 1; margin-bottom: 0; }
        .form-row .add-btn-group { flex: 0 0 auto; }
        
        .symptom-select-wrapper { display: flex; gap: 10px; align-items: center; }
        .symptom-select-wrapper select { flex: 1; }
        .quick-symptoms { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; padding: 8px 0; }
        
        .selected-symptoms-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding: 6px 0;
            min-height: 36px;
        }
        .symptom-tag {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--primary-bg);
            color: var(--primary);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            border: 1px solid var(--primary);
            animation: fadeIn 0.2s ease;
        }
        [data-theme="dark"] .symptom-tag { background: #1E3A5F; border-color: var(--primary-light); color: var(--primary-light); }
        @keyframes fadeIn { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }
        .symptom-tag .remove-symptom {
            cursor: pointer;
            font-weight: 700;
            font-size: 1rem;
            color: var(--danger);
            transition: var(--transition);
            line-height: 1;
        }
        .symptom-tag .remove-symptom:hover { transform: scale(1.3); color: #DC2626; }
        
        .lab-test-row {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            align-items: center;
        }
        .lab-test-row .form-control { flex: 1; }
        
        /* Medications */
        .medication-form {
            background: var(--gray-50);
            border-radius: var(--radius);
            padding: 20px;
            border: 1px solid var(--gray-200);
        }
        [data-theme="dark"] .medication-form { background: var(--gray-900); border-color: var(--gray-700); }
        
        .med-row-2col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .med-row-2col .form-group { margin-bottom: 0; }
        
        @media (max-width: 768px) {
            .med-row-2col { grid-template-columns: 1fr; }
            .med-row-2col .form-group { margin-bottom: 12px; }
        }
        
        .selected-medications {
            background: var(--gray-50);
            border-radius: var(--radius);
            padding: 16px 20px;
            border: 1px solid var(--gray-200);
            margin-top: 16px;
        }
        [data-theme="dark"] .selected-medications { background: var(--gray-900); border-color: var(--gray-700); }
        
        .medications-list {
            max-height: 250px;
            overflow-y: auto;
        }
        .medications-list::-webkit-scrollbar { width: 5px; }
        .medications-list::-webkit-scrollbar-track { background: var(--gray-100); border-radius: 4px; }
        .medications-list::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 4px; }
        [data-theme="dark"] .medications-list::-webkit-scrollbar-track { background: var(--gray-800); }
        
        .medication-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 14px;
            border-bottom: 1px solid var(--gray-200);
            transition: var(--transition);
            animation: fadeIn 0.3s ease;
        }
        [data-theme="dark"] .medication-item { border-color: var(--gray-700); }
        .medication-item:last-child { border-bottom: none; }
        .medication-item:hover { background: var(--gray-100); }
        [data-theme="dark"] .medication-item:hover { background: var(--gray-700); }
        
        .medication-item-info { flex: 1; }
        .med-name { font-weight: 600; font-size: 0.9rem; color: var(--gray-800); }
        [data-theme="dark"] .med-name { color: var(--gray-100); }
        .med-details { font-size: 0.75rem; color: var(--gray-500); display: block; }
        .med-qty { font-size: 0.7rem; color: var(--gray-500); background: var(--gray-200); padding: 2px 12px; border-radius: 12px; margin-left: 8px; }
        [data-theme="dark"] .med-qty { background: var(--gray-700); }
        
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
        
        .flex { display: flex; }
        .flex-wrap { flex-wrap: wrap; }
        .gap-2 { gap: 8px; }
        .items-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        
        /* Procedures & Tools Grid */
        .procedure-grid {
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
        [data-theme="dark"] .procedure-grid { background: var(--gray-800); }
        
        .procedure-grid::-webkit-scrollbar { width: 4px; }
        .procedure-grid::-webkit-scrollbar-track { background: var(--gray-200); border-radius: 4px; }
        .procedure-grid::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 4px; }
        [data-theme="dark"] .procedure-grid::-webkit-scrollbar-track { background: var(--gray-700); }
        
        .procedure-item-select {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: var(--radius);
            font-size: 0.8rem;
            background: #ffffff;
            border: 2px solid var(--gray-200);
            cursor: pointer;
            transition: var(--transition);
            user-select: none;
        }
        [data-theme="dark"] .procedure-item-select { background: var(--gray-700); border-color: var(--gray-600); }
        
        .procedure-item-select:hover { 
            background: var(--primary-bg); 
            border-color: var(--primary);
            transform: translateY(-1px);
        }
        [data-theme="dark"] .procedure-item-select:hover { background: #1E3A5F; border-color: var(--primary-light); }
        
        .procedure-item-select.selected {
            background: var(--primary-bg);
            border-color: var(--primary);
            color: var(--primary);
            box-shadow: 0 0 0 2px rgba(11,94,215,0.2);
        }
        [data-theme="dark"] .procedure-item-select.selected {
            background: #1E3A5F;
            border-color: var(--primary-light);
            color: var(--primary-light);
        }
        
        .procedure-item-select .item-check {
            width: 18px;
            height: 18px;
            border: 2px solid var(--gray-300);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: var(--transition);
        }
        [data-theme="dark"] .procedure-item-select .item-check { border-color: var(--gray-500); }
        
        .procedure-item-select.selected .item-check {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }
        [data-theme="dark"] .procedure-item-select.selected .item-check {
            background: var(--primary-light);
            border-color: var(--primary-light);
        }
        
        .procedure-item-select .item-check i {
            font-size: 0.6rem;
            opacity: 0;
            transition: var(--transition);
        }
        .procedure-item-select.selected .item-check i {
            opacity: 1;
        }
        
        .tools-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 8px;
            margin-top: 8px;
            padding: 12px;
            background: var(--gray-100);
            border-radius: var(--radius);
            max-height: 200px;
            overflow-y: auto;
        }
        [data-theme="dark"] .tools-grid { background: var(--gray-800); }
        
        .tools-grid::-webkit-scrollbar { width: 4px; }
        .tools-grid::-webkit-scrollbar-track { background: var(--gray-200); border-radius: 4px; }
        .tools-grid::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 4px; }
        [data-theme="dark"] .tools-grid::-webkit-scrollbar-track { background: var(--gray-700); }
        
        .tool-item-select {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: var(--radius);
            font-size: 0.8rem;
            background: #ffffff;
            border: 2px solid var(--gray-200);
            cursor: pointer;
            transition: var(--transition);
            user-select: none;
        }
        [data-theme="dark"] .tool-item-select { background: var(--gray-700); border-color: var(--gray-600); }
        
        .tool-item-select:hover { 
            background: var(--primary-bg); 
            border-color: var(--primary);
            transform: translateY(-1px);
        }
        [data-theme="dark"] .tool-item-select:hover { background: #1E3A5F; border-color: var(--primary-light); }
        
        .tool-item-select.selected {
            background: var(--primary-bg);
            border-color: var(--primary);
            color: var(--primary);
            box-shadow: 0 0 0 2px rgba(11,94,215,0.2);
        }
        [data-theme="dark"] .tool-item-select.selected {
            background: #1E3A5F;
            border-color: var(--primary-light);
            color: var(--primary-light);
        }
        
        .tool-item-select .item-check {
            width: 18px;
            height: 18px;
            border: 2px solid var(--gray-300);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: var(--transition);
        }
        [data-theme="dark"] .tool-item-select .item-check { border-color: var(--gray-500); }
        
        .tool-item-select.selected .item-check {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }
        [data-theme="dark"] .tool-item-select.selected .item-check {
            background: var(--primary-light);
            border-color: var(--primary-light);
        }
        
        .tool-item-select .item-check i {
            font-size: 0.6rem;
            opacity: 0;
            transition: var(--transition);
        }
        .tool-item-select.selected .item-check i {
            opacity: 1;
        }
        
        .selected-items-list {
            max-height: 250px;
            overflow-y: auto;
        }
        .selected-items-list::-webkit-scrollbar { width: 4px; }
        .selected-items-list::-webkit-scrollbar-track { background: var(--gray-100); border-radius: 4px; }
        .selected-items-list::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 4px; }
        [data-theme="dark"] .selected-items-list::-webkit-scrollbar-track { background: var(--gray-800); }
        
        .selected-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 10px 14px;
            border-bottom: 1px solid var(--gray-200);
            transition: var(--transition);
            animation: fadeIn 0.3s ease;
        }
        [data-theme="dark"] .selected-item { border-color: var(--gray-700); }
        .selected-item:last-child { border-bottom: none; }
        .selected-item:hover { background: var(--gray-100); }
        [data-theme="dark"] .selected-item:hover { background: var(--gray-700); }
        
        .selected-item-name { font-weight: 500; font-size: 0.85rem; color: var(--gray-800); flex: 1; }
        [data-theme="dark"] .selected-item-name { color: var(--gray-200); }
        .selected-item-type { font-size: 0.65rem; color: var(--gray-500); background: var(--gray-200); padding: 2px 10px; border-radius: 12px; }
        [data-theme="dark"] .selected-item-type { background: var(--gray-700); }
        .selected-item-qty { font-size: 0.7rem; color: var(--gray-500); background: var(--gray-200); padding: 2px 12px; border-radius: 12px; }
        [data-theme="dark"] .selected-item-qty { background: var(--gray-700); }
        .selected-item-status { font-size: 0.6rem; font-weight: 600; padding: 2px 12px; border-radius: 12px; background: var(--warning-bg); color: var(--warning); }
        [data-theme="dark"] .selected-item-status { background: #3D2E0A; color: #FBBF24; }
        
        /* Frozen Overlay */
        .frozen-overlay {
            position: relative;
            opacity: 0.6;
            pointer-events: none;
        }
        
        .frozen-overlay::after {
            content: '🔒 Lab Tests Pending - Medications Frozen';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(217, 119, 6, 0.95);
            color: white;
            padding: 12px 24px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.9rem;
            z-index: 10;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            white-space: nowrap;
        }
        
        .frozen-badge {
            display: inline-block;
            background: var(--warning);
            color: white;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            margin-left: 8px;
            animation: pulse-badge 2s infinite;
        }
        
        @keyframes pulse-badge {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 22px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.85rem;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        .btn-primary { background: var(--primary); color: #ffffff; }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(11,94,215,0.3); }
        .btn-success { background: var(--success); color: #ffffff; }
        .btn-success:hover { background: var(--success-dark); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(5,150,105,0.3); }
        .btn-warning { background: var(--warning); color: #ffffff; }
        .btn-warning:hover { background: #B45309; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(217,119,6,0.3); }
        .btn-danger { background: var(--danger); color: #ffffff; padding: 6px 12px; font-size: 0.7rem; border-radius: 8px; }
        .btn-danger:hover { background: #B91C1C; transform: scale(1.05); }
        .btn-outline { background: transparent; color: var(--gray-600); border: 2px solid var(--gray-200); }
        .btn-outline:hover { background: var(--gray-50); border-color: var(--primary); color: var(--primary); }
        [data-theme="dark"] .btn-outline { color: var(--gray-400); border-color: var(--gray-700); }
        [data-theme="dark"] .btn-outline:hover { background: var(--gray-800); border-color: var(--primary-light); color: var(--primary-light); }
        .btn-sm { padding: 6px 14px; font-size: 0.75rem; border-radius: 8px; }
        .btn-xs { padding: 4px 10px; font-size: 0.65rem; border-radius: 6px; }
        
        .form-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            padding-top: 20px;
            margin-top: 20px;
            border-top: 2px solid var(--gray-200);
        }
        [data-theme="dark"] .form-actions { border-color: var(--gray-700); }
        
        .table-wrap { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        .data-table th {
            text-align: left;
            padding: 10px 14px;
            font-weight: 600;
            font-size: 0.7rem;
            text-transform: uppercase;
            color: var(--gray-500);
            border-bottom: 2px solid var(--gray-200);
            background: var(--gray-50);
        }
        [data-theme="dark"] .data-table th { color: var(--gray-400); border-color: var(--gray-700); background: var(--gray-800); }
        .data-table td { padding: 10px 14px; border-bottom: 1px solid var(--gray-200); color: var(--gray-700); }
        [data-theme="dark"] .data-table td { color: var(--gray-300); border-color: var(--gray-700); }
        .data-table tr:hover td { background: var(--gray-50); }
        [data-theme="dark"] .data-table tr:hover td { background: var(--gray-800); }
        
        .badge { padding: 3px 14px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; display: inline-block; color: #ffffff; }
        .badge-success { background: var(--success); }
        .badge-warning { background: var(--warning); }
        
        .empty-state { text-align: center; padding: 24px 16px; color: var(--gray-500); }
        .empty-state i { font-size: 2rem; color: var(--gray-300); display: block; margin-bottom: 8px; }
        [data-theme="dark"] .empty-state i { color: var(--gray-600); }
        .empty-state p { font-size: 0.85rem; margin: 4px 0; }
        
        .toast-custom {
            position: fixed;
            bottom: 30px;
            right: 30px;
            padding: 14px 22px;
            border-radius: var(--radius);
            z-index: 9999;
            max-width: 380px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s ease;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #ffffff;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        }
        .toast-custom.show { transform: translateY(0); opacity: 1; }
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }
        
        .footer {
            padding: 16px 0;
            border-top: 2px solid var(--gray-200);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--gray-500);
        }
        [data-theme="dark"] .footer { border-color: var(--gray-700); color: var(--gray-400); }
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        @media (max-width: 1200px) {
            .consultation-card { padding: 20px 22px; }
            .history-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 1024px) {
            .main-content { padding: 20px; }
            .grid-cols-2 { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; }
            .page-header-right { width: 100%; }
            .page-header-right .btn { flex: 1; justify-content: center; }
        }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px; }
            .patient-info-grid { grid-template-columns: 1fr; }
            .visit-info-grid { grid-template-columns: 1fr; }
            .form-row { flex-direction: column; }
            .form-row .form-group { width: 100%; }
            .form-actions { flex-direction: column; }
            .form-actions .btn { width: 100%; justify-content: center; }
            .lab-test-row { flex-direction: column; }
            .lab-test-row .form-control { width: 100%; }
            .patient-header { flex-direction: column; text-align: center; }
            .consultation-card { padding: 16px; }
            .page-title { font-size: 1.2rem; }
            .med-row-2col { grid-template-columns: 1fr; }
            .medication-item { flex-direction: column; align-items: flex-start; gap: 6px; }
            .procedure-grid { grid-template-columns: 1fr 1fr; }
            .tools-grid { grid-template-columns: 1fr 1fr; }
            .symptom-select-wrapper { flex-direction: column; }
            .symptom-select-wrapper .btn { width: 100%; justify-content: center; }
            .quick-symptoms { gap: 4px; }
            .quick-symptoms .btn-xs { font-size: 0.6rem; padding: 2px 8px; }
            .row-2col { grid-template-columns: 1fr; }
            .frozen-overlay::after {
                font-size: 0.7rem;
                padding: 8px 16px;
                white-space: normal;
                text-align: center;
                width: 80%;
            }
        }
        @media (max-width: 480px) {
            .main-content { padding: 12px; }
            .consultation-card { padding: 12px; }
            .page-title { font-size: 1rem; }
            .page-header { padding: 14px 16px; }
            .patient-avatar-large { width: 50px; height: 50px; font-size: 1.4rem; }
            .procedure-grid { grid-template-columns: 1fr; }
            .tools-grid { grid-template-columns: 1fr; }
        }
        @media print {
            .top-nav, .sidebar, .btn, .footer, .btn-remove, .form-actions { display: none !important; }
            .main-content { margin: 0 !important; padding: 20px !important; background: white !important; }
            .consultation-card { border: 1px solid #ddd !important; box-shadow: none !important; page-break-inside: avoid; }
            .page-header { border-bottom: 2px solid var(--primary) !important; background: white !important; }
            .patient-header { background: #E8F0FE !important; }
        }
    </style>
</head>
<body>

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
            <a href="my_patients.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> My Patients
            </a>
            <button onclick="window.print()" class="btn btn-outline btn-sm">
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

        <!-- SECTION 1: PATIENT INFORMATION -->
        <div class="row-2col mb-6">
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

        <!-- SECTION 2: PATIENT HISTORY -->
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

        <!-- SECTION 3: SYMPTOMS -->
        <div class="consultation-card mb-6">
            <h3 class="card-title">
                <i class="fas fa-list-ul title-blue"></i> Symptoms
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
                    <textarea name="symptoms" id="symptomsText" class="form-control symptoms-textarea" rows="4" 
                              placeholder="Describe patient symptoms..."><?= htmlspecialchars($visit['symptoms'] ?? '') ?></textarea>
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

        <!-- SECTION 4: DIAGNOSIS + TREATMENT -->
        <div class="row-2col mb-6">
            <div class="consultation-card">
                <h3 class="card-title">
                    <i class="fas fa-diagnoses title-blue"></i> Diagnosis
                </h3>
                <div class="form-group">
                    <label class="form-label">Diagnosis <span class="required">*</span></label>
                    <textarea name="diagnosis" class="form-control" rows="4" placeholder="Enter diagnosis..."><?= htmlspecialchars($visit['diagnosis'] ?? '') ?></textarea>
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

        <!-- SECTION 5: LAB REQUESTS -->
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
            
            <div class="mt-3 flex flex-wrap gap-3">
                <button type="submit" name="send_lab" class="btn btn-warning">
                    <i class="fas fa-paper-plane"></i> Send to Laboratory
                </button>
                <span class="text-xs text-gray-500 self-center">
                    <i class="fas fa-info-circle"></i> Lab tests waiting for confirmation
                </span>
            </div>
        </div>

        <!-- SECTION 6: LAB RESULTS -->
        <div class="consultation-card mb-6 <?= $lab_results_available ? 'border-green-500' : '' ?>">
            <h3 class="card-title">
                <i class="fas fa-file-medical-alt title-green"></i> Laboratory Results
                <?php if ($lab_results_available): ?>
                    <span class="frozen-badge" style="background:#059669;">✅ Results Available</span>
                <?php elseif (count($lab_requests) > 0): ?>
                    <span class="frozen-badge">⏳ Pending Results</span>
                <?php endif; ?>
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
                    <p class="text-xs text-gray-400 mt-1">⏳ Waiting for Laboratory to complete tests</p>
                    <p class="text-xs text-red-500 mt-2"><i class="fas fa-lock"></i> Medications are frozen until results are available</p>
                </div>
            <?php else: ?>
                <div class="text-center py-6 text-gray-400">
                    <i class="fas fa-flask text-3xl block mb-2"></i>
                    <p>No lab results available</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- SECTION 7: MEDICATIONS (Frozen if lab pending) -->
        <div class="consultation-card mb-6 <?= $medications_frozen ? 'frozen-overlay' : '' ?>">
            <h3 class="card-title">
                <i class="fas fa-prescription title-blue"></i> Medications
                <?php if ($medications_frozen): ?>
                    <span class="frozen-badge">🔒 Frozen - Lab Pending</span>
                <?php else: ?>
                    <span class="text-xs text-gray-400 ml-2"><i class="fas fa-check-circle text-green-600"></i> Available</span>
                <?php endif; ?>
                <button type="button" class="btn btn-outline btn-sm ml-2" onclick="addMedicationRow()" <?= $medications_frozen ? 'disabled style="opacity:0.5;cursor:not-allowed;"' : '' ?>>
                    <i class="fas fa-plus"></i> Add Medication
                </button>
            </h3>
            
            <?php if ($medications_frozen): ?>
                <div class="alert alert-warning" style="margin-bottom:16px;">
                    <i class="fas fa-lock"></i>
                    <strong>Medications are frozen!</strong> Lab tests are pending. Please wait for results before prescribing medications.
                </div>
            <?php endif; ?>
            
            <div class="medication-form" id="medicationForm">
                <div class="med-row-2col">
                    <div class="form-group">
                        <label class="form-label">Medication <span class="required">*</span></label>
                        <select name="inventory_id" class="form-control" id="medicationSelect" <?= $medications_frozen ? 'disabled' : '' ?>>
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
                    </div>
                    <div class="form-group">
                        <label class="form-label">Qty</label>
                        <input type="number" name="quantity" class="form-control" value="1" min="1" max="99" id="medQuantity" <?= $medications_frozen ? 'disabled' : '' ?>>
                    </div>
                </div>
                
                <div class="med-row-2col">
                    <div class="form-group">
                        <label class="form-label">Dosage</label>
                        <input type="text" name="dosage" class="form-control" placeholder="e.g. 500mg" id="medDosage" <?= $medications_frozen ? 'disabled' : '' ?>>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Frequency</label>
                        <select name="frequency" class="form-control" id="medFrequency" <?= $medications_frozen ? 'disabled' : '' ?>>
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
                
                <div class="med-row-2col">
                    <div class="form-group">
                        <label class="form-label">Duration (Days)</label>
                        <input type="number" name="duration" class="form-control" value="7" min="1" max="90" id="medDuration" <?= $medications_frozen ? 'disabled' : '' ?>>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Route</label>
                        <select name="route" class="form-control" id="medRoute" <?= $medications_frozen ? 'disabled' : '' ?>>
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
                </div>
                
                <div class="form-group mt-2">
                    <label class="form-label">Instructions</label>
                    <div class="flex flex-wrap gap-2 mb-2">
                        <button type="button" class="btn btn-outline btn-xs" onclick="setInstruction('After meals')" <?= $medications_frozen ? 'disabled' : '' ?>>After meals</button>
                        <button type="button" class="btn btn-outline btn-xs" onclick="setInstruction('Before meals')" <?= $medications_frozen ? 'disabled' : '' ?>>Before meals</button>
                        <button type="button" class="btn btn-outline btn-xs" onclick="setInstruction('With food')" <?= $medications_frozen ? 'disabled' : '' ?>>With food</button>
                        <button type="button" class="btn btn-outline btn-xs" onclick="setInstruction('Empty stomach')" <?= $medications_frozen ? 'disabled' : '' ?>>Empty stomach</button>
                        <button type="button" class="btn btn-outline btn-xs" onclick="setInstruction('At bedtime')" <?= $medications_frozen ? 'disabled' : '' ?>>At bedtime</button>
                        <button type="button" class="btn btn-outline btn-xs" onclick="setInstruction('As needed')" <?= $medications_frozen ? 'disabled' : '' ?>>As needed</button>
                    </div>
                    <input type="text" name="instructions" class="form-control" placeholder="e.g. After meals" id="medInstructions" <?= $medications_frozen ? 'disabled' : '' ?>>
                </div>
                
                <div class="mt-3">
                    <button type="button" class="btn btn-primary" onclick="addMedicationAjax()" <?= $medications_frozen ? 'disabled' : '' ?>>
                        <i class="fas fa-plus"></i> Add Medication
                    </button>
                    <span class="text-xs text-gray-400 ml-2"><i class="fas fa-info-circle"></i> Waiting for Pharmacy confirmation</span>
                    <?php if ($medications_frozen): ?>
                        <span class="text-xs text-red-500 ml-2"><i class="fas fa-lock"></i> Frozen until lab results</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="selected-medications mt-4">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="text-sm font-semibold text-gray-600">
                        <i class="fas fa-list"></i> Selected Medications
                        <span class="text-xs text-gray-400" id="medCount">(<?= count($selected_medications) ?> items)</span>
                    </h4>
                    <?php if (count($selected_medications) > 0): ?>
                        <button type="button" class="btn btn-danger btn-sm" onclick="clearAllMedications()">
                            <i class="fas fa-trash"></i> Clear All
                        </button>
                    <?php endif; ?>
                </div>
                
                <div id="medicationsList">
                    <?php if (count($selected_medications) > 0): ?>
                        <?php foreach ($selected_medications as $med): ?>
                            <div class="medication-item" id="med-item-<?= $med['id'] ?>">
                                <div class="medication-item-info">
                                    <span class="med-name"><?= htmlspecialchars($med['medication'] ?? 'Unknown') ?></span>
                                    <span class="med-details">
                                        <?= htmlspecialchars($med['dosage'] ?? '') ?> • 
                                        <?= htmlspecialchars($med['frequency'] ?? '') ?> • 
                                        <?= htmlspecialchars($med['duration'] ?? '') ?> days
                                    </span>
                                    <span class="med-qty">x<?= $med['quantity'] ?? 0 ?></span>
                                    <span class="text-xs text-yellow-600">Pending Pharmacy</span>
                                </div>
                                <button type="button" class="btn-remove" onclick="removeMedication(<?= $med['id'] ?>)" title="Remove medication">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state" id="emptyMedications">
                            <i class="fas fa-prescription"></i>
                            <p>No medications added yet</p>
                            <?php if ($medications_frozen): ?>
                                <p class="text-xs text-red-500 mt-1"><i class="fas fa-lock"></i> Medications frozen - lab tests pending</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- SECTION 8: PROCEDURES & TOOLS (Toggle Dropdown) -->
        <div class="consultation-card mb-6">
            <h3 class="card-title">
                <i class="fas fa-syringe title-blue"></i> Procedures & Tools
                <span class="text-xs text-gray-400 ml-2"><i class="fas fa-check-circle"></i> Direct to Cashier</span>
            </h3>
            
            <!-- PROCEDURES TOGGLE -->
            <div class="toggle-section">
                <div class="toggle-header" onclick="toggleDropdown('proceduresToggle')">
                    <span class="toggle-title">
                        <i class="fas fa-syringe title-blue"></i>
                        Procedures
                        <span class="text-xs text-gray-400">(Click to expand)</span>
                    </span>
                    <span class="toggle-icon"><i class="fas fa-chevron-down"></i></span>
                </div>
                <div class="toggle-body" id="proceduresToggle">
                    <div class="form-group mt-3">
                        <label class="form-label">Filter Procedures</label>
                        <select id="procedureFilter" class="form-control" onchange="filterProcedures()">
                            <option value="">-- All Procedures --</option>
                            <?php foreach ($procedures_list as $proc): ?>
                                <option value="<?= htmlspecialchars($proc['procedure_name']) ?>">
                                    <?= htmlspecialchars($proc['procedure_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mt-2">
                        <label class="form-label">Select Procedures</label>
                        <div class="procedure-grid" id="procedureGrid">
                            <?php foreach ($procedures_list as $proc): ?>
                                <div class="procedure-item-select" 
                                     data-procedure-id="<?= $proc['id'] ?>"
                                     data-procedure-name="<?= htmlspecialchars($proc['procedure_name']) ?>"
                                     onclick="toggleProcedure(this)">
                                    <span class="item-check"><i class="fas fa-check"></i></span>
                                    <span class="item-name"><?= htmlspecialchars($proc['procedure_name']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- TOOLS TOGGLE -->
            <div class="toggle-section">
                <div class="toggle-header" onclick="toggleDropdown('toolsToggle')">
                    <span class="toggle-title">
                        <i class="fas fa-tools title-orange"></i>
                        Tools
                        <span class="text-xs text-gray-400">(Click to expand)</span>
                    </span>
                    <span class="toggle-icon"><i class="fas fa-chevron-down"></i></span>
                </div>
                <div class="toggle-body" id="toolsToggle">
                    <div class="mt-2">
                        <label class="form-label">Select Tools</label>
                        <div class="tools-grid" id="toolsGrid">
                            <?php foreach ($procedure_tools as $tool): ?>
                                <div class="tool-item-select" 
                                     data-tool-id="<?= $tool['id'] ?>"
                                     data-tool-name="<?= htmlspecialchars($tool['tool_name']) ?>"
                                     data-procedure-name="<?= htmlspecialchars($tool['procedure_name']) ?>"
                                     onclick="toggleTool(this)">
                                    <span class="item-check"><i class="fas fa-check"></i></span>
                                    <span class="item-name"><?= htmlspecialchars($tool['tool_name']) ?></span>
                                    <small class="text-xs text-gray-400">(<?= htmlspecialchars($tool['procedure_name']) ?>)</small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Add Selected Buttons -->
            <div class="mt-3 flex flex-wrap gap-3">
                <button type="button" class="btn btn-primary" onclick="addSelectedItems()">
                    <i class="fas fa-plus"></i> Add Selected (Procedures & Tools)
                </button>
                <button type="button" class="btn btn-outline btn-sm" onclick="clearAllSelections()">
                    <i class="fas fa-times"></i> Clear Selections
                </button>
            </div>
            
            <!-- Selected Items List -->
            <div class="selected-items-list mt-3">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="text-sm font-semibold text-gray-600">
                        <i class="fas fa-list"></i> Selected Items
                        <span class="text-xs text-gray-400" id="selectedCount">
                            (<?= count(array_filter($bill_items, function($item) { return ($item['item_type'] ?? '') === 'procedure' || ($item['item_type'] ?? '') === 'tool'; })) ?> items)
                        </span>
                    </h4>
                    <?php if (count(array_filter($bill_items, function($item) { return ($item['item_type'] ?? '') === 'procedure' || ($item['item_type'] ?? '') === 'tool'; })) > 0): ?>
                        <button type="button" class="btn btn-danger btn-sm" onclick="clearAllItems()">
                            <i class="fas fa-trash"></i> Clear All
                        </button>
                    <?php endif; ?>
                </div>
                
                <div id="selectedItemsList">
                    <?php 
                    $selected_items = array_filter($bill_items, function($item) { 
                        return ($item['item_type'] ?? '') === 'procedure' || ($item['item_type'] ?? '') === 'tool'; 
                    });
                    ?>
                    
                    <?php if (count($selected_items) > 0): ?>
                        <?php foreach ($selected_items as $item): ?>
                            <div class="selected-item" id="selected-item-<?= $item['id'] ?>">
                                <span class="selected-item-name"><?= htmlspecialchars($item['item_name']) ?></span>
                                <span class="selected-item-type"><?= ucfirst($item['item_type']) ?></span>
                                <span class="selected-item-qty">x<?= $item['quantity'] ?></span>
                                <span class="selected-item-status">Pending Cashier</span>
                                <button type="button" class="btn-remove" onclick="removeSelectedItem(<?= $item['id'] ?>)" title="Remove item">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state" id="emptySelected">
                            <i class="fas fa-syringe"></i>
                            <p>No procedures or tools added yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- SECTION 9: FOLLOW-UP + REFERRAL -->
        <div class="row-2col mb-6">
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

        <!-- SECTION 10: FORM ACTIONS -->
        <div class="consultation-card">
            <div class="form-actions">
                <button type="submit" name="save_draft" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Draft
                </button>
                <button type="submit" name="complete_visit" class="btn btn-success" 
                        onclick="return confirm('Complete this consultation?\n\n- 💊 Medications: Waiting for Pharmacy confirmation\n- 🧪 Lab Tests: Waiting for Laboratory confirmation\n- 💉 Procedures & Tools: Direct to Cashier')">
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
            <span class="text-gray-300 mx-2">|</span>
            Consultation
            <span class="text-gray-300 mx-2">|</span>
            <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>
            <span class="text-gray-300 mx-2">|</span>
            <span id="footerTimestamp"><?= date('H:i:s') ?></span>
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
    // TOGGLE DROPDOWN
    // ================================================================
    function toggleDropdown(id) {
        var body = document.getElementById(id);
        var header = body.previousElementSibling;
        body.classList.toggle('open');
        header.classList.toggle('open');
    }

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
    // SET INSTRUCTION
    // ================================================================
    function setInstruction(text) {
        document.getElementById('medInstructions').value = text;
    }

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
    // FILTER PROCEDURES
    // ================================================================
    function filterProcedures() {
        var filter = document.getElementById('procedureFilter').value;
        var items = document.querySelectorAll('.procedure-item-select');
        
        items.forEach(function(item) {
            var name = item.dataset.procedureName;
            if (filter === '' || name === filter) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    }

    // ================================================================
    // TOGGLE PROCEDURE
    // ================================================================
    function toggleProcedure(element) {
        element.classList.toggle('selected');
    }

    // ================================================================
    // TOGGLE TOOL
    // ================================================================
    function toggleTool(element) {
        element.classList.toggle('selected');
    }

    // ================================================================
    // CLEAR ALL SELECTIONS
    // ================================================================
    function clearAllSelections() {
        document.querySelectorAll('.procedure-item-select.selected, .tool-item-select.selected').forEach(function(el) {
            el.classList.remove('selected');
        });
    }

    // ================================================================
    // GET SELECTED ITEMS
    // ================================================================
    function getSelectedItems() {
        var procedures = [];
        var tools = [];
        
        document.querySelectorAll('.procedure-item-select.selected').forEach(function(item) {
            procedures.push({
                id: item.dataset.procedureId,
                name: item.dataset.procedureName,
                type: 'procedure'
            });
        });
        
        document.querySelectorAll('.tool-item-select.selected').forEach(function(item) {
            tools.push({
                id: item.dataset.toolId,
                name: item.dataset.toolName,
                type: 'tool'
            });
        });
        
        return { procedures: procedures, tools: tools };
    }

    // ================================================================
    // ADD SELECTED ITEMS
    // ================================================================
    function addSelectedItems() {
        var selected = getSelectedItems();
        var total = selected.procedures.length + selected.tools.length;
        
        if (total === 0) {
            showToast('Error', 'Please select at least one procedure or tool', 'error');
            return;
        }
        
        var btn = document.querySelector('button[onclick="addSelectedItems()"]');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
        
        var procedurePromises = [];
        selected.procedures.forEach(function(proc) {
            var formData = new FormData();
            formData.append('ajax_action', 'add_procedure');
            formData.append('procedure_id', proc.id);
            procedurePromises.push(
                fetch(window.location.href, { method: 'POST', body: formData })
                .then(response => response.json())
            );
        });
        
        var toolPromises = [];
        selected.tools.forEach(function(tool) {
            var formData = new FormData();
            formData.append('ajax_action', 'add_tool');
            formData.append('tool_id', tool.id);
            toolPromises.push(
                fetch(window.location.href, { method: 'POST', body: formData })
                .then(response => response.json())
            );
        });
        
        Promise.all([...procedurePromises, ...toolPromises])
            .then(function(results) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-plus"></i> Add Selected (Procedures & Tools)';
                
                var successCount = 0;
                results.forEach(function(data) {
                    if (data.success) {
                        successCount++;
                        if (data.procedure) {
                            addItemToList(data.procedure, 'procedure');
                        } else if (data.tool) {
                            addItemToList(data.tool, 'tool');
                        }
                    }
                });
                
                if (successCount > 0) {
                    showToast('Success', '✅ ' + successCount + ' item(s) added successfully!', 'success');
                    clearAllSelections();
                    updateSelectedCount();
                } else {
                    showToast('Error', 'Failed to add items', 'error');
                }
            })
            .catch(function(error) {
                console.error('Error:', error);
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-plus"></i> Add Selected (Procedures & Tools)';
                showToast('Error', 'Failed to add items', 'error');
            });
    }

    // ================================================================
    // ADD ITEM TO LIST
    // ================================================================
    function addItemToList(item, type) {
        var list = document.getElementById('selectedItemsList');
        var emptyState = document.getElementById('emptySelected');
        if (emptyState) emptyState.remove();
        
        var itemEl = document.createElement('div');
        itemEl.className = 'selected-item';
        itemEl.id = 'selected-item-' + item.id;
        itemEl.innerHTML = `
            <span class="selected-item-name">${escapeHtml(item.name)}</span>
            <span class="selected-item-type">${type.charAt(0).toUpperCase() + type.slice(1)}</span>
            <span class="selected-item-qty">x${item.quantity || 1}</span>
            <span class="selected-item-status">Pending Cashier</span>
            <button type="button" class="btn-remove" onclick="removeSelectedItem(${item.id})" title="Remove item">
                <i class="fas fa-times"></i>
            </button>
        `;
        list.appendChild(itemEl);
    }

    // ================================================================
    // REMOVE SELECTED ITEM
    // ================================================================
    function removeSelectedItem(itemId) {
        if (!confirm('Remove this item from bill?')) return;
        
        var formData = new FormData();
        formData.append('ajax_action', 'remove_item');
        formData.append('item_id', itemId);
        
        fetch(window.location.href, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Info', data.message, 'info');
                var item = document.getElementById('selected-item-' + itemId);
                if (item) item.remove();
                updateSelectedCount();
                
                var list = document.getElementById('selectedItemsList');
                if (list.children.length === 0) {
                    list.innerHTML = `
                        <div class="empty-state" id="emptySelected">
                            <i class="fas fa-syringe"></i>
                            <p>No procedures or tools added yet</p>
                        </div>
                    `;
                    var clearBtn = document.querySelector('button[onclick="clearAllItems()"]');
                    if (clearBtn) clearBtn.style.display = 'none';
                }
            } else {
                showToast('Error', data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error', 'Failed to remove item', 'error');
        });
    }

    // ================================================================
    // CLEAR ALL ITEMS (FIXED)
    // ================================================================
    function clearAllItems() {
        if (!confirm('Remove all items from bill?')) return;
        
        var items = document.querySelectorAll('#selectedItemsList .selected-item');
        var itemIds = [];
        items.forEach(function(item) {
            var id = item.id.replace('selected-item-', '');
            itemIds.push(id);
        });
        
        if (itemIds.length === 0) return;
        
        var formData = new FormData();
        formData.append('ajax_action', 'clear_all_items');
        itemIds.forEach(function(id) {
            formData.append('item_ids[]', id);
        });
        
        var btn = document.querySelector('button[onclick="clearAllItems()"]');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Clearing...';
        
        fetch(window.location.href, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-trash"></i> Clear All';
            
            if (data.success) {
                showToast('Info', data.message, 'info');
                document.getElementById('selectedItemsList').innerHTML = `
                    <div class="empty-state" id="emptySelected">
                        <i class="fas fa-syringe"></i>
                        <p>No procedures or tools added yet</p>
                    </div>
                `;
                updateSelectedCount();
                var clearBtn = document.querySelector('button[onclick="clearAllItems()"]');
                if (clearBtn) clearBtn.style.display = 'none';
            } else {
                showToast('Error', data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-trash"></i> Clear All';
            showToast('Error', 'Failed to clear items', 'error');
        });
    }

    // ================================================================
    // UPDATE SELECTED COUNT
    // ================================================================
    function updateSelectedCount() {
        var list = document.getElementById('selectedItemsList');
        var count = list ? list.querySelectorAll('.selected-item').length : 0;
        var countEl = document.getElementById('selectedCount');
        if (countEl) countEl.textContent = '(' + count + ' items)';
        
        var clearBtn = document.querySelector('button[onclick="clearAllItems()"]');
        if (clearBtn) {
            clearBtn.style.display = count > 0 ? 'inline-flex' : 'none';
        }
    }

    // ================================================================
    // ESCAPE HTML
    // ================================================================
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ================================================================
    // AJAX: ADD MEDICATION
    // ================================================================
    function addMedicationAjax() {
        var medSelect = document.getElementById('medicationSelect');
        var qty = parseInt(document.getElementById('medQuantity').value) || 0;
        var dosage = document.getElementById('medDosage').value;
        var frequency = document.getElementById('medFrequency').value;
        var duration = document.getElementById('medDuration').value;
        var route = document.getElementById('medRoute').value;
        var instructions = document.getElementById('medInstructions').value;
        
        if (!medSelect.value) {
            showToast('Error', 'Please select a medication', 'error');
            return;
        }
        if (qty < 1) {
            showToast('Error', 'Quantity must be at least 1', 'error');
            return;
        }
        
        var formData = new FormData();
        formData.append('ajax_action', 'add_medication');
        formData.append('inventory_id', medSelect.value);
        formData.append('quantity', qty);
        formData.append('dosage', dosage);
        formData.append('frequency', frequency);
        formData.append('duration', duration);
        formData.append('route', route);
        formData.append('instructions', instructions);
        
        var btn = document.querySelector('button[onclick="addMedicationAjax()"]');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
        
        fetch(window.location.href, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-plus"></i> Add Medication';
            
            if (data.success) {
                showToast('Success', data.message, 'success');
                addMedicationToList(data.medication);
                medSelect.value = '';
                document.getElementById('medQuantity').value = '1';
                document.getElementById('medDosage').value = '';
                document.getElementById('medFrequency').value = '';
                document.getElementById('medDuration').value = '7';
                document.getElementById('medRoute').value = '';
                document.getElementById('medInstructions').value = '';
                updateMedCount();
            } else {
                showToast('Error', data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-plus"></i> Add Medication';
            showToast('Error', 'Failed to add medication', 'error');
        });
    }

    function addMedicationToList(med) {
        var list = document.getElementById('medicationsList');
        var emptyState = document.getElementById('emptyMedications');
        if (emptyState) emptyState.remove();
        
        var item = document.createElement('div');
        item.className = 'medication-item';
        item.id = 'med-item-' + med.id;
        item.innerHTML = `
            <div class="medication-item-info">
                <span class="med-name">${escapeHtml(med.name)}</span>
                <span class="med-details">
                    ${escapeHtml(med.dosage || '')} • 
                    ${escapeHtml(med.frequency || '')} • 
                    ${escapeHtml(med.duration || '')} days
                </span>
                <span class="med-qty">x${med.quantity}</span>
                <span class="text-xs text-yellow-600">Pending Pharmacy</span>
            </div>
            <button type="button" class="btn-remove" onclick="removeMedication(${med.id})" title="Remove medication">
                <i class="fas fa-times"></i>
            </button>
        `;
        list.appendChild(item);
        
        var clearBtn = document.querySelector('button[onclick="clearAllMedications()"]');
        if (clearBtn) clearBtn.style.display = 'inline-flex';
    }

    function removeMedication(prescriptionId) {
        if (!confirm('Remove this medication?')) return;
        
        var formData = new FormData();
        formData.append('ajax_action', 'remove_medication');
        formData.append('prescription_id', prescriptionId);
        
        fetch(window.location.href, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Info', data.message, 'info');
                var item = document.getElementById('med-item-' + prescriptionId);
                if (item) item.remove();
                updateMedCount();
                
                var list = document.getElementById('medicationsList');
                if (list.children.length === 0) {
                    list.innerHTML = `
                        <div class="empty-state" id="emptyMedications">
                            <i class="fas fa-prescription"></i>
                            <p>No medications added yet</p>
                        </div>
                    `;
                    var clearBtn = document.querySelector('button[onclick="clearAllMedications()"]');
                    if (clearBtn) clearBtn.style.display = 'none';
                }
            } else {
                showToast('Error', data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error', 'Failed to remove medication', 'error');
        });
    }

    function clearAllMedications() {
        if (!confirm('Remove all medications?')) return;
        
        var formData = new FormData();
        formData.append('ajax_action', 'clear_medications');
        
        fetch(window.location.href, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Info', data.message, 'info');
                document.getElementById('medicationsList').innerHTML = `
                    <div class="empty-state" id="emptyMedications">
                        <i class="fas fa-prescription"></i>
                        <p>No medications added yet</p>
                    </div>
                `;
                updateMedCount();
                var clearBtn = document.querySelector('button[onclick="clearAllMedications()"]');
                if (clearBtn) clearBtn.style.display = 'none';
            } else {
                showToast('Error', data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error', 'Failed to clear medications', 'error');
        });
    }

    function updateMedCount() {
        var list = document.getElementById('medicationsList');
        var count = list ? list.querySelectorAll('.medication-item').length : 0;
        var countEl = document.getElementById('medCount');
        if (countEl) countEl.textContent = '(' + count + ' items)';
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
    console.log('%c💊 Medications: <?= count($selected_medications) ?> pending', 'font-size:12px; color:#34D399;');
    console.log('%c🧪 Lab Tests: <?= count($lab_requests) ?> pending', 'font-size:12px; color:#7C3AED;');
    console.log('%c🔒 Medications Frozen: <?= $medications_frozen ? 'YES' : 'NO' ?>', 'font-size:12px; color:#D97706;');
    console.log('%c💉 Procedures: <?= count(array_filter($bill_items, function($item) { return ($item['item_type'] ?? '') === 'procedure'; })) ?> items', 'font-size:12px; color:#0B5ED7;');
    console.log('%c🛠️ Tools: <?= count(array_filter($bill_items, function($item) { return ($item['item_type'] ?? '') === 'tool'; })) ?> items', 'font-size:12px; color:#D97706;');
</script>

</body>
</html>