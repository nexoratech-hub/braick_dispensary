<?php
// ================================================================
// FILE: frontend/pages/doctor/consultation.php
// DOCTOR CONSULTATION - FULL WITH PICK OR WRITE INSTRUCTIONS
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
$view = isset($_GET['view']) ? $_GET['view'] : 'consult';

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

// Check if visit is completed
$is_completed = ($visit['status'] === 'completed');
if ($is_completed) {
    $view = 'view';
}

// ================================================================
// GET OR CREATE BILL
// ================================================================
$bill_id = null;
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
// GET ALL DATA
// ================================================================

// Lab Tests Catalog
$lab_tests_catalog = [];
try {
    $stmt = $db->prepare("SELECT id, test_name, price, category FROM lab_tests_catalog WHERE is_active = 1 ORDER BY category, test_name");
    $stmt->execute();
    $lab_tests_catalog = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $lab_tests_catalog = []; }

// Procedures
$procedures_list = [];
try {
    $stmt = $db->prepare("SELECT id, procedure_name, category, price, description FROM procedures WHERE is_active = 1 ORDER BY procedure_name");
    $stmt->execute();
    $procedures_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $procedures_list = []; }

// Procedure Tools
$procedure_tools = [];
try {
    $stmt = $db->prepare("SELECT id, procedure_name, tool_name, price FROM procedure_tools WHERE is_active = 1 ORDER BY procedure_name, tool_name");
    $stmt->execute();
    $procedure_tools = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $procedure_tools = []; }

// Medications
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
} catch (Exception $e) { $medications_list = []; }

// Selected Medications
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
} catch (Exception $e) { $selected_medications = []; }

// Bill Items
$bill_items = [];
$total_bill_amount = 0;
try {
    $stmt = $db->prepare("
        SELECT id, item_name, item_type, quantity, unit_price, total_price, payment_status, is_paid, status 
        FROM bill_items 
        WHERE bill_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$bill_id]);
    $bill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($bill_items as $item) {
        $total_bill_amount += $item['total_price'];
    }
} catch (Exception $e) { $bill_items = []; }

// ================================================================
// GET LAB STATUS
// ================================================================
$lab_requests = [];
$lab_results = [];
$lab_results_available = false;
$lab_status = 'none';
$sections_frozen = false;

function fetchLabData($db, $visit_id) {
    $lab_requests = [];
    $lab_results = [];
    $lab_results_available = false;
    $lab_status = 'none';
    
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
        
        if (count($lab_requests) > 0) {
            $lab_status = 'pending';
        } elseif ($lab_results_available) {
            $lab_status = 'completed';
        }
        
    } catch (Exception $e) {
        error_log("Lab fetch error: " . $e->getMessage());
    }
    
    return [
        'requests' => $lab_requests,
        'results' => $lab_results,
        'available' => $lab_results_available,
        'status' => $lab_status,
        'frozen' => (count($lab_requests) > 0 && !$lab_results_available)
    ];
}

$lab_data = fetchLabData($db, $visit_id);
$lab_requests = $lab_data['requests'];
$lab_results = $lab_data['results'];
$lab_results_available = $lab_data['available'];
$lab_status = $lab_data['status'];
$sections_frozen = ($lab_data['frozen'] && !$is_completed);

// ================================================================
// PRESCRIPTION INSTRUCTIONS - 10 Quick Options
// ================================================================
$prescription_instructions = [
    'After meals',
    'Before meals',
    'With meals',
    'On empty stomach',
    'At bedtime',
    'In the morning',
    'In the evening',
    'Every 4 hours',
    'Every 6 hours',
    'Every 8 hours'
];

// ================================================================
// HANDLE FORM SUBMISSIONS
// ================================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_completed) {
    $action = $_POST['action'] ?? '';
    
    // ================================================================
    // AJAX: GET LAB STATUS
    // ================================================================
    if ($action === 'get_lab_status') {
        header('Content-Type: application/json');
        $lab_data = fetchLabData($db, $visit_id);
        
        $pending_count = count($lab_data['requests']);
        $results_count = count($lab_data['results']);
        
        $results_html = '';
        if ($lab_data['available']) {
            foreach ($lab_data['results'] as $result) {
                $results_html .= '
                    <tr>
                        <td style="padding:10px 14px;border-bottom:1px solid var(--gray-200);color:var(--gray-700);">' . htmlspecialchars($result['test_name'] ?? 'N/A') . '</td>
                        <td style="padding:10px 14px;border-bottom:1px solid var(--gray-200);color:var(--gray-700);font-weight:600;color:#059669;">' . htmlspecialchars($result['results'] ?? 'N/A') . '</td>
                        <td style="padding:10px 14px;border-bottom:1px solid var(--gray-200);color:var(--gray-700);">' . htmlspecialchars($result['reference_range'] ?? '') . '</td>
                        <td style="padding:10px 14px;border-bottom:1px solid var(--gray-200);color:var(--gray-700);"><span class="badge badge-success">Completed</span></td>
                    </tr>
                ';
            }
        }
        
        echo json_encode([
            'success' => true,
            'pending_count' => $pending_count,
            'results_count' => $results_count,
            'frozen' => $lab_data['frozen'],
            'available' => $lab_data['available'],
            'status' => $lab_data['status'],
            'results_html' => $results_html,
            'timestamp' => date('H:i:s')
        ]);
        exit;
    }
    
    // ================================================================
    // AJAX: ADD MEDICATION
    // ================================================================
    if ($action === 'add_medication') {
        header('Content-Type: application/json');
        
        if ($sections_frozen) {
            echo json_encode(['success' => false, 'message' => '❌ Cannot add medications. Lab tests pending!']);
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
                
                $new_id = $db->lastInsertId();
                
                $new_stock = $med['stock'] - $quantity;
                $stmt = $db->prepare("UPDATE medications_inventory SET quantity = ? WHERE id = ?");
                $stmt->execute([$new_stock, $inventory_id]);
                
                $med_total = $med['selling_price'] * $quantity;
                
                $stmt = $db->prepare("
                    INSERT INTO bill_items (
                        bill_id, item_type, item_name, quantity, unit_price, total_price,
                        payment_status, is_paid, status, created_at
                    ) VALUES (?, 'medication', ?, ?, ?, ?, 'pending', 0, 'pending', NOW())
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
                    'quantity' => $quantity,
                    'instructions' => $instructions
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
    if ($action === 'remove_medication') {
        header('Content-Type: application/json');
        $prescription_id = (int)($_POST['prescription_id'] ?? 0);
        $response = ['success' => false, 'message' => ''];
        
        if ($prescription_id > 0) {
            $stmt = $db->prepare("SELECT medication, quantity FROM prescriptions WHERE id = ? AND visit_id = ?");
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
    // AJAX: ADD PROCEDURE
    // ================================================================
    if ($action === 'add_procedure') {
        header('Content-Type: application/json');
        
        if ($sections_frozen) {
            echo json_encode(['success' => false, 'message' => '❌ Cannot add procedures. Lab tests pending!']);
            exit;
        }
        
        $procedure_id = (int)($_POST['procedure_id'] ?? 0);
        $response = ['success' => false, 'message' => '', 'procedure' => null];
        
        if ($procedure_id > 0) {
            $stmt = $db->prepare("SELECT id, procedure_name, price FROM procedures WHERE id = ? AND is_active = 1");
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
                        INSERT INTO bill_items (
                            bill_id, item_type, item_name, quantity, unit_price, total_price,
                            payment_status, is_paid, status, created_at
                        ) VALUES (?, 'procedure', ?, 1, ?, ?, 'pending', 0, 'pending', NOW())
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
                $response['procedure'] = ['id' => $proc_id, 'name' => $item_name, 'quantity' => $existing ? $existing['quantity'] + 1 : 1];
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
    if ($action === 'add_tool') {
        header('Content-Type: application/json');
        
        if ($sections_frozen) {
            echo json_encode(['success' => false, 'message' => '❌ Cannot add tools. Lab tests pending!']);
            exit;
        }
        
        $tool_id = (int)($_POST['tool_id'] ?? 0);
        $response = ['success' => false, 'message' => '', 'tool' => null];
        
        if ($tool_id > 0) {
            $stmt = $db->prepare("SELECT id, procedure_name, tool_name, price FROM procedure_tools WHERE id = ? AND is_active = 1");
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
                        INSERT INTO bill_items (
                            bill_id, item_type, item_name, quantity, unit_price, total_price,
                            payment_status, is_paid, status, created_at
                        ) VALUES (?, 'tool', ?, 1, ?, ?, 'pending', 0, 'pending', NOW())
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
                $response['tool'] = ['id' => $tool_db_id, 'name' => $item_name, 'quantity' => $existing ? $existing['quantity'] + 1 : 1];
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
    if ($action === 'remove_item') {
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
            $response['message'] = '✅ Item removed!';
        } else {
            $response['message'] = '❌ Invalid item';
        }
        
        echo json_encode($response);
        exit;
    }
    
    // ================================================================
    // 1. SEND LAB REQUESTS
    // ================================================================
    if (isset($_POST['send_lab']) && isset($_POST['lab_tests']) && is_array($_POST['lab_tests'])) {
        $stmt = $db->prepare("DELETE FROM lab_tests WHERE visit_id = ? AND status IN ('pending', 'in_progress')");
        $stmt->execute([$visit_id]);
        
        $lab_tests_sent = 0;
        foreach ($_POST['lab_tests'] as $test_name) {
            $test_name = trim($test_name);
            if (!empty($test_name)) {
                $stmt = $db->prepare("
                    INSERT INTO lab_tests (
                        visit_id, doctor_id, test_name, status, branch_id, created_at
                    ) VALUES (?, ?, ?, 'pending', ?, NOW())
                ");
                $stmt->execute([$visit_id, $doctor_id, $test_name, $doctor_branch_id]);
                
                $stmt_price = $db->prepare("SELECT price FROM lab_tests_catalog WHERE test_name = ? LIMIT 1");
                $stmt_price->execute([$test_name]);
                $catalog = $stmt_price->fetch(PDO::FETCH_ASSOC);
                $price = $catalog['price'] ?? 0;
                
                if ($price > 0) {
                    $stmt_bill = $db->prepare("
                        INSERT INTO bill_items (
                            bill_id, item_type, item_name, quantity, unit_price, total_price,
                            payment_status, is_paid, status, created_at
                        ) VALUES (?, 'lab_test', ?, 1, ?, ?, 'pending', 0, 'pending', NOW())
                    ");
                    $stmt_bill->execute([$bill_id, $test_name, $price, $price]);
                    
                    $stmt_total = $db->prepare("SELECT SUM(total_price) as total FROM bill_items WHERE bill_id = ?");
                    $stmt_total->execute([$bill_id]);
                    $new_total = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
                    
                    $stmt_update = $db->prepare("
                        UPDATE patient_bills 
                        SET subtotal = ?, total_amount = ?, balance = ? 
                        WHERE id = ?
                    ");
                    $stmt_update->execute([$new_total, $new_total, $new_total, $bill_id]);
                }
                
                $lab_tests_sent++;
            }
        }
        
        $message = "✅ " . $lab_tests_sent . " lab request(s) sent to Laboratory! Bills sent to Cashier.";
        $message_type = 'success';
        
        $stmt = $db->prepare("UPDATE visits SET status = 'lab_test', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$visit_id]);
        
        $lab_data = fetchLabData($db, $visit_id);
        $lab_requests = $lab_data['requests'];
        $lab_results = $lab_data['results'];
        $lab_results_available = $lab_data['available'];
        $lab_status = $lab_data['status'];
        $sections_frozen = $lab_data['frozen'];
        
        if ($lab_tests_sent > 0) {
            $sections_frozen = true;
        }
    }
    
    // ================================================================
    // 2. SAVE DRAFT
    // ================================================================
    if (isset($_POST['save_draft'])) {
        if ($sections_frozen) {
            $message = "❌ Cannot save draft. Lab tests pending!";
            $message_type = 'error';
        } else {
            $symptoms = trim($_POST['symptoms'] ?? '');
            $diagnosis = trim($_POST['diagnosis'] ?? '');
            $treatment = trim($_POST['treatment'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            $instructions = trim($_POST['instructions'] ?? '');
            
            $stmt = $db->prepare("
                UPDATE visits 
                SET symptoms = ?, diagnosis = ?, treatment = ?, notes = ?, 
                    status = 'with_doctor', updated_at = NOW()
                WHERE id = ? AND doctor_id = ?
            ");
            $stmt->execute([$symptoms, $diagnosis, $treatment, $notes, $visit_id, $doctor_id]);
            
            $message = "✅ Draft saved successfully! Diagnosis: " . ($diagnosis ?: 'None');
            $message_type = 'success';
            
            $stmt = $db->prepare("SELECT * FROM visits WHERE id = ?");
            $stmt->execute([$visit_id]);
            $visit = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    
    // ================================================================
    // 3. COMPLETE VISIT
    // ================================================================
    if (isset($_POST['complete_visit'])) {
        if ($sections_frozen) {
            $message = "❌ Cannot complete visit. Lab tests pending!";
            $message_type = 'error';
        } else {
            $symptoms = trim($_POST['symptoms'] ?? '');
            $diagnosis = trim($_POST['diagnosis'] ?? '');
            $treatment = trim($_POST['treatment'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            
            $stmt = $db->prepare("
                UPDATE visits 
                SET symptoms = ?, diagnosis = ?, treatment = ?, notes = ?,
                    status = 'completed', is_completed = 1, completed_at = NOW(), updated_at = NOW()
                WHERE id = ? AND doctor_id = ?
            ");
            $stmt->execute([$symptoms, $diagnosis, $treatment, $notes, $visit_id, $doctor_id]);
            
            if ($bill_id) {
                $stmt = $db->prepare("
                    UPDATE patient_bills 
                    SET status = 'pending', updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$bill_id]);
            }
            
            $message = "✅ Visit completed! Diagnosis: " . ($diagnosis ?: 'None');
            $message_type = 'success';
            
            echo '<script>
                setTimeout(function(){ 
                    window.location.href = "consultation.php?visit_id=' . $visit_id . '&view=view"; 
                }, 2000);
            </script>';
            exit;
        }
    }
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
} catch (Exception $e) { $doctor_branch_name = 'Branch'; }

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
        'with_doctor' => 'badge-info',
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
    <title><?= $is_completed ? 'View Consultation' : 'Consultation' ?> - Braick Dispensary</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* ================================================================
           MAIN STYLES
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
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            background: var(--gray-50);
            color: var(--gray-800);
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            margin: 0;
            padding: 0;
            line-height: 1.6;
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
        
        /* ================================================================
           PAGE HEADER
           ================================================================ */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 28px;
            padding: 24px 28px;
            background: #ffffff;
            border-radius: var(--radius-lg);
            border: 1px solid var(--gray-200);
            box-shadow: var(--shadow);
            position: relative;
        }
        [data-theme="dark"] .page-header {
            background: var(--gray-800);
            border-color: var(--gray-700);
        }
        .page-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 28px;
            right: 28px;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            border-radius: 0 0 4px 4px;
        }
        .page-header-left { flex: 1; }
        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin: 0;
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
            border: 1px solid var(--primary-light);
        }
        [data-theme="dark"] .page-badge {
            background: #1E3A5F;
            color: var(--primary-light);
            border-color: var(--primary);
        }
        
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
        
        .view-mode-badge {
            background: var(--success);
            color: white;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        /* ================================================================
           CONSULTATION CARDS
           ================================================================ */
        .consultation-card {
            background: #ffffff;
            border-radius: var(--radius-lg);
            padding: 24px 28px;
            border: 1px solid var(--gray-200);
            transition: var(--transition);
            margin-bottom: 24px;
            box-shadow: var(--shadow);
        }
        .consultation-card:hover {
            border-color: var(--primary-light);
            box-shadow: var(--shadow-md);
        }
        [data-theme="dark"] .consultation-card {
            background: var(--gray-800);
            border-color: var(--gray-700);
        }
        [data-theme="dark"] .consultation-card:hover {
            border-color: var(--primary);
        }
        
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
            gap: 10px;
        }
        [data-theme="dark"] .card-title {
            color: var(--gray-100);
            border-color: var(--gray-700);
        }
        .title-blue { color: var(--primary); }
        .title-green { color: var(--success); }
        .title-purple { color: var(--purple); }
        .title-orange { color: var(--warning); }
        .card-title i { font-size: 1.1rem; }
        
        /* ================================================================
           FORM ELEMENTS
           ================================================================ */
        .form-group { margin-bottom: 16px; }
        .form-group:last-child { margin-bottom: 0; }
        
        .form-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--gray-600);
            margin-bottom: 5px;
            letter-spacing: 0.02em;
        }
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
        [data-theme="dark"] .form-control {
            background: var(--gray-700);
            color: var(--gray-100);
            border-color: var(--gray-600);
        }
        [data-theme="dark"] .form-control:disabled {
            background: var(--gray-600);
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 80px;
            font-family: inherit;
        }
        select.form-control { appearance: auto; cursor: pointer; }
        
        /* ================================================================
           INSTRUCTIONS GRID - 2 Rows of 5
           ================================================================ */
        .instructions-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 6px;
            margin-top: 8px;
            margin-bottom: 12px;
            padding: 12px;
            background: var(--gray-50);
            border-radius: var(--radius);
            border: 1px solid var(--gray-200);
        }
        [data-theme="dark"] .instructions-grid {
            background: var(--gray-700);
            border-color: var(--gray-600);
        }
        
        .instruction-btn {
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 500;
            border: 2px solid var(--gray-300);
            background: #ffffff;
            color: var(--gray-700);
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
            white-space: nowrap;
        }
        .instruction-btn:hover {
            border-color: var(--primary);
            background: var(--primary-bg);
            color: var(--primary);
            transform: translateY(-1px);
        }
        .instruction-btn.active {
            border-color: var(--primary);
            background: var(--primary);
            color: white;
        }
        .instruction-btn.clear-btn {
            border-color: var(--danger);
            color: var(--danger);
            background: transparent;
        }
        .instruction-btn.clear-btn:hover {
            background: var(--danger);
            color: white;
            border-color: var(--danger);
        }
        [data-theme="dark"] .instruction-btn {
            background: var(--gray-800);
            color: var(--gray-300);
            border-color: var(--gray-600);
        }
        [data-theme="dark"] .instruction-btn:hover {
            border-color: var(--primary-light);
            background: #1E3A5F;
            color: var(--primary-light);
        }
        [data-theme="dark"] .instruction-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        /* ================================================================
           INSTRUCTIONS BOX - Textarea + Tags
           ================================================================ */
        .instructions-box-wrapper {
            position: relative;
            margin-top: 8px;
        }

        .instructions-box-wrapper textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius);
            font-size: 0.9rem;
            background: #ffffff;
            color: var(--gray-800);
            outline: none;
            transition: var(--transition);
            font-family: inherit;
            min-height: 80px;
            resize: vertical;
            line-height: 1.6;
        }
        .instructions-box-wrapper textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11,94,215,0.12);
        }
        .instructions-box-wrapper textarea::placeholder {
            color: var(--gray-400);
            font-style: italic;
        }
        [data-theme="dark"] .instructions-box-wrapper textarea {
            background: var(--gray-700);
            color: var(--gray-100);
            border-color: var(--gray-600);
        }
        [data-theme="dark"] .instructions-box-wrapper textarea:focus {
            border-color: var(--primary-light);
        }

        .instructions-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 8px;
            padding: 8px 12px;
            min-height: 40px;
            background: var(--gray-50);
            border-radius: var(--radius);
            border: 1px dashed var(--gray-300);
            transition: var(--transition);
        }
        [data-theme="dark"] .instructions-tags {
            background: var(--gray-700);
            border-color: var(--gray-600);
        }
        .instructions-tags .tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--primary-bg);
            color: var(--primary);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            border: 1px solid var(--primary-light);
            animation: fadeIn 0.2s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        [data-theme="dark"] .instructions-tags .tag {
            background: #1E3A5F;
            color: var(--primary-light);
            border-color: var(--primary);
        }
        .instructions-tags .tag .remove-tag {
            cursor: pointer;
            font-size: 0.7rem;
            opacity: 0.5;
            transition: var(--transition);
            margin-left: 2px;
        }
        .instructions-tags .tag .remove-tag:hover {
            opacity: 1;
            color: var(--danger);
        }
        .instructions-tags .empty-tags {
            color: var(--gray-400);
            font-size: 0.8rem;
            font-style: italic;
            padding: 2px 0;
        }
        [data-theme="dark"] .instructions-tags .empty-tags {
            color: var(--gray-500);
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }
        
        /* ================================================================
           MEDICATION ITEMS
           ================================================================ */
        .medication-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 14px;
            border-bottom: 1px solid var(--gray-200);
            transition: var(--transition);
            animation: fadeIn 0.3s ease;
        }
        .medication-item:last-child { border-bottom: none; }
        .medication-item:hover { background: var(--gray-50); border-radius: var(--radius); }
        [data-theme="dark"] .medication-item:hover { background: var(--gray-700); }
        [data-theme="dark"] .medication-item { border-color: var(--gray-700); }
        
        .medication-item-info { flex: 1; }
        .med-name { font-weight: 600; font-size: 0.9rem; color: var(--gray-800); }
        .med-details { font-size: 0.75rem; color: var(--gray-500); display: block; }
        .med-qty { font-size: 0.7rem; color: var(--gray-500); background: var(--gray-200); padding: 2px 12px; border-radius: 12px; margin-left: 8px; }
        .med-instruction-tag {
            font-size: 0.65rem;
            color: var(--primary);
            background: var(--primary-bg);
            padding: 1px 10px;
            border-radius: 12px;
            margin-left: 4px;
            border: 1px solid var(--primary-light);
        }
        [data-theme="dark"] .med-instruction-tag {
            background: #1E3A5F;
            color: var(--primary-light);
            border-color: var(--primary);
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
        .btn-remove:hover {
            background: var(--danger);
            color: #ffffff;
            transform: scale(1.1);
        }
        
        /* ================================================================
           BUTTONS
           ================================================================ */
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
            font-family: inherit;
        }
        .btn-primary {
            background: var(--primary);
            color: #ffffff;
            box-shadow: 0 2px 8px rgba(11,94,215,0.2);
        }
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(11,94,215,0.3);
        }
        .btn-success {
            background: var(--success);
            color: #ffffff;
            box-shadow: 0 2px 8px rgba(5,150,105,0.2);
        }
        .btn-success:hover {
            background: var(--success-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(5,150,105,0.3);
        }
        .btn-warning {
            background: var(--warning);
            color: #ffffff;
        }
        .btn-warning:hover {
            background: #B45309;
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(217,119,6,0.3);
        }
        .btn-outline {
            background: transparent;
            color: var(--gray-600);
            border: 2px solid var(--gray-200);
        }
        .btn-outline:hover {
            background: var(--gray-50);
            border-color: var(--primary);
            color: var(--primary);
        }
        .btn-sm {
            padding: 6px 14px;
            font-size: 0.75rem;
            border-radius: 8px;
        }
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
            pointer-events: none;
        }
        
        /* ================================================================
           VIEW MODE
           ================================================================ */
        .view-mode .consultation-card {
            border-color: var(--success);
            border-left: 4px solid var(--success);
        }
        .view-mode .consultation-card:hover { border-color: var(--success); }
        .view-mode .form-control,
        .view-mode select.form-control,
        .view-mode textarea.form-control {
            background: var(--gray-100);
            color: var(--gray-700);
            cursor: default;
            border-color: var(--gray-300);
            opacity: 0.9;
        }
        [data-theme="dark"] .view-mode .form-control {
            background: var(--gray-700);
            color: var(--gray-300);
            border-color: var(--gray-600);
        }
        .view-mode .btn,
        .view-mode .btn-remove,
        .view-mode .instruction-btn,
        .view-mode .procedure-item-select,
        .view-mode .tool-item-select {
            display: none !important;
        }
        
        /* ================================================================
           DETAIL ROWS
           ================================================================ */
        .detail-row {
            display: flex;
            padding: 10px 0;
            border-bottom: 1px solid var(--gray-200);
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-label {
            font-weight: 600;
            color: var(--gray-500);
            width: 140px;
            flex-shrink: 0;
            font-size: 0.85rem;
        }
        .detail-value {
            flex: 1;
            color: var(--gray-800);
            font-size: 0.9rem;
        }
        [data-theme="dark"] .detail-value { color: var(--gray-200); }
        [data-theme="dark"] .detail-row { border-color: var(--gray-700); }
        
        .view-summary-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 16px;
            margin-top: 16px;
        }
        .summary-item {
            background: var(--gray-50);
            padding: 16px 20px;
            border-radius: var(--radius);
            text-align: center;
            border: 1px solid var(--gray-200);
        }
        [data-theme="dark"] .summary-item {
            background: var(--gray-700);
            border-color: var(--gray-600);
        }
        .summary-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary);
        }
        .summary-label {
            font-size: 0.75rem;
            color: var(--gray-500);
            margin-top: 4px;
        }
        
        /* ================================================================
           BILL SUMMARY
           ================================================================ */
        .bill-summary {
            background: var(--primary-bg);
            padding: 16px 20px;
            border-radius: var(--radius);
            margin-top: 16px;
            border: 2px solid var(--primary-light);
        }
        [data-theme="dark"] .bill-summary {
            background: #1E3A5F;
            border-color: var(--primary);
        }
        .bill-total {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        /* ================================================================
           STATUS BADGES
           ================================================================ */
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
        .frozen-badge.success {
            background: var(--success);
            animation: none;
        }
        .live-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--success);
            color: white;
            padding: 2px 14px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
            animation: pulse-dot 1.5s infinite;
        }
        .live-badge i { font-size: 0.4rem; }
        
        @keyframes pulse-badge { 0%, 100% { opacity: 1; } 50% { opacity: 0.6; } }
        @keyframes pulse-dot { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }
        
        /* ================================================================
           FROZEN OVERLAY
           ================================================================ */
        .frozen-overlay-active {
            position: relative;
            opacity: 0.5;
            pointer-events: none;
            transition: all 0.5s ease;
        }
        .frozen-overlay-active::after {
            content: '🔒 Lab Tests Pending - Wait for Results';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(217, 119, 6, 0.92);
            color: white;
            padding: 14px 28px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.9rem;
            z-index: 10;
            box-shadow: 0 8px 30px rgba(0,0,0,0.25);
            white-space: nowrap;
            backdrop-filter: blur(4px);
        }
        .frozen-overlay-active .consultation-card { border-color: var(--warning); }
        .frozen-overlay-active .consultation-card .card-title { border-color: var(--warning); }
        
        /* ================================================================
           ALERT
           ================================================================ */
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
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .alert-success { background: var(--success-bg); color: var(--success); border-color: var(--success); }
        .alert-error { background: var(--danger-bg); color: var(--danger); border-color: var(--danger); }
        .alert-warning { background: var(--warning-bg); color: var(--warning); border-color: var(--warning); }
        .alert-info { background: var(--primary-bg); color: var(--primary); border-color: var(--primary); }
        
        /* ================================================================
           TOAST
           ================================================================ */
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
        
        /* ================================================================
           GRID & UTILITIES
           ================================================================ */
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
        .text-gray-400 { color: var(--gray-400); }
        .text-gray-500 { color: var(--gray-500); }
        .text-green-600 { color: var(--success); }
        .text-yellow-600 { color: var(--warning); }
        .text-red-500 { color: var(--danger); }
        .font-mono { font-family: monospace; }
        .font-medium { font-weight: 500; }
        .font-semibold { font-weight: 600; }
        .font-bold { font-weight: 700; }
        .border-green-500 { border-color: var(--success) !important; }
        
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
        
        .form-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            padding-top: 20px;
            margin-top: 20px;
            border-top: 2px solid var(--gray-200);
        }
        [data-theme="dark"] .form-actions { border-color: var(--gray-700); }
        
        /* ================================================================
           PROCEDURE TOGGLE
           ================================================================ */
        .procedure-item-select, .tool-item-select {
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
        .procedure-item-select:hover, .tool-item-select:hover {
            background: var(--primary-bg);
            border-color: var(--primary);
            transform: translateY(-1px);
        }
        .procedure-item-select.selected, .tool-item-select.selected {
            background: var(--primary-bg);
            border-color: var(--primary);
            color: var(--primary);
            box-shadow: 0 0 0 2px rgba(11,94,215,0.2);
        }
        .procedure-item-select .item-check, .tool-item-select .item-check {
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
        .procedure-item-select.selected .item-check, .tool-item-select.selected .item-check {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }
        .procedure-item-select .item-check i, .tool-item-select .item-check i {
            font-size: 0.6rem;
            opacity: 0;
            transition: var(--transition);
        }
        .procedure-item-select.selected .item-check i, .tool-item-select.selected .item-check i {
            opacity: 1;
        }
        
        .empty-state {
            text-align: center;
            padding: 16px;
            color: var(--gray-500);
        }
        .empty-state i {
            font-size: 1.5rem;
            color: var(--gray-300);
            display: block;
            margin-bottom: 8px;
        }
        [data-theme="dark"] .empty-state i { color: var(--gray-600); }
        
        /* ================================================================
           RESPONSIVE
           ================================================================ */
        @media (max-width: 1024px) {
            .view-summary-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px; }
            .row-2col { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; }
            .form-actions { flex-direction: column; }
            .form-actions .btn { width: 100%; justify-content: center; }
            .consultation-card { padding: 16px; }
            .view-summary-grid { grid-template-columns: 1fr; }
            .detail-row { flex-direction: column; }
            .detail-label { width: 100%; margin-bottom: 4px; }
            .instructions-grid { grid-template-columns: repeat(3, 1fr); }
            .frozen-overlay-active::after {
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
            .instructions-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content <?= $is_completed ? 'view-mode' : '' ?>">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-left">
            <h1 class="page-title">
                <?php if ($is_completed): ?>
                    <i class="fas fa-check-circle" style="color: var(--success);"></i> Consultation Summary
                <?php else: ?>
                    <i class="fas fa-stethoscope"></i> Consultation
                <?php endif; ?>
                <span class="page-badge"><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></span>
                <?php if ($is_completed): ?>
                    <span class="view-mode-badge">✅ Completed</span>
                <?php endif; ?>
                <?php if ($sections_frozen && !$is_completed): ?>
                    <span class="frozen-badge" id="frozenBadgeHeader">🔒 Lab Pending</span>
                <?php elseif ($lab_results_available && !$is_completed): ?>
                    <span class="frozen-badge success" id="frozenBadgeHeader">✅ Lab Results Available</span>
                <?php endif; ?>
                <?php if (!$is_completed): ?>
                    <span class="live-badge" id="liveBadge">
                        <i class="fas fa-circle"></i> Live
                        <span id="liveTime" style="font-weight:400;font-size:0.55rem;"><?= date('H:i:s') ?></span>
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                Patient: <strong><?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?></strong>
                (<?= htmlspecialchars($visit['patient_code'] ?? 'N/A') ?>)
                <span class="separator">|</span>
                Status: 
                <span class="status-badge <?= getStatusBadgeClass($visit['status'] ?? 'pending') ?>">
                    <?= ucfirst(str_replace('_', ' ', $visit['status'] ?? 'Pending')) ?>
                </span>
                <?php if ($is_completed && isset($visit['completed_at'])): ?>
                    <span class="text-xs text-gray-400">Completed: <?= date('M d, Y h:i A', strtotime($visit['completed_at'])) ?></span>
                <?php endif; ?>
                <?php if (!$is_completed): ?>
                    <span class="text-xs text-gray-400" id="lastUpdateTime">⏱ <?= date('H:i:s') ?></span>
                <?php endif; ?>
            </p>
        </div>
        <div class="page-header-right">
            <a href="my_patients.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> My Patients
            </a>
            <?php if (!$is_completed): ?>
                <button onclick="manualRefresh()" class="btn btn-outline btn-sm" id="refreshBtn">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            <?php endif; ?>
            <button onclick="window.print()" class="btn btn-outline btn-sm">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>" id="alertMessage">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle') ?>"></i>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- VIEW MODE - COMPLETED CONSULTATION SUMMARY -->
    <!-- ================================================================ -->
    <?php if ($is_completed): ?>
        
        <!-- Summary Stats -->
        <div class="view-summary-grid">
            <div class="summary-item">
                <p class="summary-number"><?= count($lab_results) ?></p>
                <p class="summary-label">🧪 Lab Tests</p>
            </div>
            <div class="summary-item">
                <p class="summary-number"><?= count($selected_medications) ?></p>
                <p class="summary-label">💊 Medications</p>
            </div>
            <div class="summary-item">
                <p class="summary-number"><?= count($bill_items) ?></p>
                <p class="summary-label">📋 Bill Items</p>
            </div>
        </div>

        <!-- Patient Info -->
        <div class="consultation-card">
            <h3 class="card-title"><i class="fas fa-user title-blue"></i> Patient Information</h3>
            <div class="detail-row"><span class="detail-label">Full Name</span><span class="detail-value"><?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?></span></div>
            <div class="detail-row"><span class="detail-label">Patient ID</span><span class="detail-value"><?= htmlspecialchars($visit['patient_code'] ?? 'N/A') ?></span></div>
            <div class="detail-row"><span class="detail-label">Gender</span><span class="detail-value"><?= htmlspecialchars($visit['gender'] ?? 'N/A') ?></span></div>
            <div class="detail-row"><span class="detail-label">Date of Birth</span><span class="detail-value"><?= !empty($visit['date_of_birth']) ? date('M d, Y', strtotime($visit['date_of_birth'])) : 'N/A' ?> (<?= calculateAge($visit['date_of_birth'] ?? '') ?> years)</span></div>
            <div class="detail-row"><span class="detail-label">Phone</span><span class="detail-value"><?= htmlspecialchars($visit['phone'] ?? 'N/A') ?></span></div>
            <div class="detail-row"><span class="detail-label">Blood Group</span><span class="detail-value"><?= htmlspecialchars($visit['blood_group'] ?? 'N/A') ?></span></div>
            <div class="detail-row"><span class="detail-label">Allergies</span><span class="detail-value"><?= htmlspecialchars($visit['allergies'] ?? 'None') ?></span></div>
            <div class="detail-row"><span class="detail-label">Address</span><span class="detail-value"><?= htmlspecialchars($visit['address'] ?? 'N/A') ?></span></div>
        </div>

        <!-- Symptoms -->
        <div class="consultation-card">
            <h3 class="card-title"><i class="fas fa-list-ul title-blue"></i> Symptoms</h3>
            <div class="detail-row"><span class="detail-label">Description</span><span class="detail-value"><?= nl2br(htmlspecialchars($visit['symptoms'] ?? 'No symptoms recorded')) ?></span></div>
        </div>

        <!-- Lab Results -->
        <div class="consultation-card">
            <h3 class="card-title"><i class="fas fa-flask title-green"></i> Lab Results (<?= count($lab_results) ?>)</h3>
            <?php if (count($lab_results) > 0): ?>
                <table style="width:100%;border-collapse:collapse;font-size:0.85rem;margin-top:8px;">
                    <thead><tr>
                        <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--gray-500);border-bottom:2px solid var(--gray-200);">Test Name</th>
                        <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--gray-500);border-bottom:2px solid var(--gray-200);">Result</th>
                        <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--gray-500);border-bottom:2px solid var(--gray-200);">Reference Range</th>
                        <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--gray-500);border-bottom:2px solid var(--gray-200);">Status</th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($lab_results as $result): ?>
                            <tr>
                                <td style="padding:10px 14px;border-bottom:1px solid var(--gray-200);"><?= htmlspecialchars($result['test_name'] ?? 'N/A') ?></td>
                                <td style="padding:10px 14px;border-bottom:1px solid var(--gray-200);font-weight:600;color:var(--success);"><?= htmlspecialchars($result['results'] ?? 'N/A') ?></td>
                                <td style="padding:10px 14px;border-bottom:1px solid var(--gray-200);"><?= htmlspecialchars($result['reference_range'] ?? '') ?></td>
                                <td style="padding:10px 14px;border-bottom:1px solid var(--gray-200);"><span class="badge badge-success">✅ Completed</span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-gray-400">No lab tests performed</p>
            <?php endif; ?>
        </div>

        <!-- Diagnosis -->
        <div class="consultation-card">
            <h3 class="card-title"><i class="fas fa-diagnoses title-blue"></i> Diagnosis</h3>
            <div class="detail-row">
                <span class="detail-label">Diagnosis</span>
                <span class="detail-value"><?= nl2br(htmlspecialchars($visit['diagnosis'] ?? 'No diagnosis recorded')) ?></span>
            </div>
        </div>

        <!-- Medications -->
        <div class="consultation-card">
            <h3 class="card-title"><i class="fas fa-prescription title-blue"></i> Medications (<?= count($selected_medications) ?>)</h3>
            <?php if (count($selected_medications) > 0): ?>
                <table style="width:100%;border-collapse:collapse;font-size:0.85rem;margin-top:8px;">
                    <thead><tr>
                        <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--gray-500);border-bottom:2px solid var(--gray-200);">Medication</th>
                        <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--gray-500);border-bottom:2px solid var(--gray-200);">Dosage</th>
                        <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--gray-500);border-bottom:2px solid var(--gray-200);">Frequency</th>
                        <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--gray-500);border-bottom:2px solid var(--gray-200);">Duration</th>
                        <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--gray-500);border-bottom:2px solid var(--gray-200);">Qty</th>
                        <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--gray-500);border-bottom:2px solid var(--gray-200);">Instructions</th>
                        <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--gray-500);border-bottom:2px solid var(--gray-200);">Status</th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($selected_medications as $med): ?>
                            <tr>
                                <td style="padding:10px 14px;border-bottom:1px solid var(--gray-200);"><?= htmlspecialchars($med['medication'] ?? 'N/A') ?></td>
                                <td style="padding:10px 14px;border-bottom:1px solid var(--gray-200);"><?= htmlspecialchars($med['dosage'] ?? '') ?></td>
                                <td style="padding:10px 14px;border-bottom:1px solid var(--gray-200);"><?= htmlspecialchars($med['frequency'] ?? '') ?></td>
                                <td style="padding:10px 14px;border-bottom:1px solid var(--gray-200);"><?= htmlspecialchars($med['duration'] ?? '') ?> days</td>
                                <td style="padding:10px 14px;border-bottom:1px solid var(--gray-200);"><?= $med['quantity'] ?? 0 ?></td>
                                <td style="padding:10px 14px;border-bottom:1px solid var(--gray-200);"><?= htmlspecialchars($med['instructions'] ?? '') ?></td>
                                <td style="padding:10px 14px;border-bottom:1px solid var(--gray-200);"><span class="badge badge-warning">⏳ Pending</span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-gray-400">No medications prescribed</p>
            <?php endif; ?>
        </div>

        <!-- Bill Summary -->
        <div class="consultation-card">
            <h3 class="card-title"><i class="fas fa-receipt title-green"></i> Bill Summary</h3>
            <div class="bill-summary">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <div>
                        <p class="text-sm text-gray-500">Total Amount</p>
                        <p class="bill-total">TSh <?= number_format($total_bill_amount, 2) ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Status</p>
                        <p><span class="status-badge badge-warning">⏳ Pending Payment</span></p>
                    </div>
                </div>
                <div class="mt-3 text-sm text-gray-500">
                    <i class="fas fa-info-circle"></i> Bill sent to Cashier for payment processing
                </div>
            </div>
            
            <?php if (count($bill_items) > 0): ?>
                <table style="width:100%;border-collapse:collapse;font-size:0.85rem;margin-top:12px;">
                    <thead><tr>
                        <th style="text-align:left;padding:8px 12px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--gray-500);border-bottom:2px solid var(--gray-200);">Item</th>
                        <th style="text-align:left;padding:8px 12px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--gray-500);border-bottom:2px solid var(--gray-200);">Type</th>
                        <th style="text-align:left;padding:8px 12px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--gray-500);border-bottom:2px solid var(--gray-200);">Qty</th>
                        <th style="text-align:left;padding:8px 12px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--gray-500);border-bottom:2px solid var(--gray-200);">Total</th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($bill_items as $item): ?>
                            <tr>
                                <td style="padding:8px 12px;border-bottom:1px solid var(--gray-200);"><?= htmlspecialchars($item['item_name'] ?? 'N/A') ?></td>
                                <td style="padding:8px 12px;border-bottom:1px solid var(--gray-200);"><span class="badge badge-info"><?= ucfirst($item['item_type'] ?? 'N/A') ?></span></td>
                                <td style="padding:8px 12px;border-bottom:1px solid var(--gray-200);"><?= $item['quantity'] ?? 1 ?></td>
                                <td style="padding:8px 12px;border-bottom:1px solid var(--gray-200);font-weight:600;">TSh <?= number_format($item['total_price'] ?? 0, 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="mt-4 text-center">
            <a href="my_patients.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Back to My Patients
            </a>
        </div>

    <?php else: ?>

    <!-- ================================================================ -->
    <!-- CONSULTATION FORM - ACTIVE MODE -->
    <!-- ================================================================ -->
    <form method="POST" action="" id="consultationForm">

        <!-- SECTION 1: PATIENT INFORMATION -->
        <div class="row-2col mb-6">
            <div class="consultation-card">
                <h3 class="card-title"><i class="fas fa-user title-blue"></i> Patient Information</h3>
                <div style="display:flex;align-items:center;gap:20px;padding:16px 20px;background:var(--primary-bg);border-radius:var(--radius);margin-bottom:18px;">
                    <div style="width:70px;height:70px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:700;color:white;flex-shrink:0;background:<?= getUserColor($visit['patient_name'] ?? 'Unknown') ?>;">
                        <?= strtoupper(substr($visit['patient_name'] ?? 'U', 0, 1)) ?>
                    </div>
                    <div>
                        <h4 style="font-size:1.2rem;font-weight:600;color:var(--gray-800);"><?= htmlspecialchars($visit['patient_name'] ?? 'N/A') ?></h4>
                        <p style="font-size:0.8rem;color:var(--gray-500);font-family:monospace;">ID: <?= htmlspecialchars($visit['patient_code'] ?? 'N/A') ?></p>
                        <p style="font-size:0.85rem;color:var(--gray-500);"><?= htmlspecialchars($visit['gender'] ?? 'N/A') ?> • <?= calculateAge($visit['date_of_birth'] ?? '') ?> years</p>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px 20px;">
                    <div><span style="display:block;font-size:0.65rem;color:var(--gray-500);font-weight:500;text-transform:uppercase;letter-spacing:0.05em;">Date of Birth</span><span style="display:block;font-size:0.9rem;font-weight:500;color:var(--gray-800);"><?= !empty($visit['date_of_birth']) ? date('M d, Y', strtotime($visit['date_of_birth'])) : 'N/A' ?></span></div>
                    <div><span style="display:block;font-size:0.65rem;color:var(--gray-500);font-weight:500;text-transform:uppercase;letter-spacing:0.05em;">Phone</span><span style="display:block;font-size:0.9rem;font-weight:500;color:var(--gray-800);"><?= htmlspecialchars($visit['phone'] ?? 'N/A') ?></span></div>
                    <div><span style="display:block;font-size:0.65rem;color:var(--gray-500);font-weight:500;text-transform:uppercase;letter-spacing:0.05em;">Blood Group</span><span style="display:block;font-size:0.9rem;font-weight:500;color:var(--gray-800);"><?= htmlspecialchars($visit['blood_group'] ?? 'N/A') ?></span></div>
                    <div><span style="display:block;font-size:0.65rem;color:var(--gray-500);font-weight:500;text-transform:uppercase;letter-spacing:0.05em;">Allergies</span><span style="display:block;font-size:0.9rem;font-weight:500;color:var(--gray-800);"><?= htmlspecialchars($visit['allergies'] ?? 'None') ?></span></div>
                    <div class="col-span-2"><span style="display:block;font-size:0.65rem;color:var(--gray-500);font-weight:500;text-transform:uppercase;letter-spacing:0.05em;">Address</span><span style="display:block;font-size:0.9rem;font-weight:500;color:var(--gray-800);"><?= htmlspecialchars($visit['address'] ?? 'N/A') ?></span></div>
                </div>
            </div>

            <div class="consultation-card">
                <h3 class="card-title"><i class="fas fa-clinic-medical title-green"></i> Visit Information</h3>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px 20px;">
                    <div><span style="display:block;font-size:0.65rem;color:var(--gray-500);font-weight:500;text-transform:uppercase;letter-spacing:0.05em;">Visit Number</span><span style="display:block;font-size:0.9rem;font-weight:500;color:var(--gray-800);font-family:monospace;"><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></span></div>
                    <div><span style="display:block;font-size:0.65rem;color:var(--gray-500);font-weight:500;text-transform:uppercase;letter-spacing:0.05em;">Visit Type</span><span style="display:block;font-size:0.9rem;font-weight:500;color:var(--gray-800);"><?= ucfirst($visit['visit_type'] ?? 'New') ?></span></div>
                    <div><span style="display:block;font-size:0.65rem;color:var(--gray-500);font-weight:500;text-transform:uppercase;letter-spacing:0.05em;">Date</span><span style="display:block;font-size:0.9rem;font-weight:500;color:var(--gray-800);"><?= date('M d, Y', strtotime($visit['created_at'])) ?></span></div>
                    <div><span style="display:block;font-size:0.65rem;color:var(--gray-500);font-weight:500;text-transform:uppercase;letter-spacing:0.05em;">Time</span><span style="display:block;font-size:0.9rem;font-weight:500;color:var(--gray-800);"><?= date('h:i A', strtotime($visit['created_at'])) ?></span></div>
                    <div><span style="display:block;font-size:0.65rem;color:var(--gray-500);font-weight:500;text-transform:uppercase;letter-spacing:0.05em;">Branch</span><span style="display:block;font-size:0.9rem;font-weight:500;color:var(--gray-800);"><?= htmlspecialchars($visit['branch_name'] ?? 'N/A') ?></span></div>
                    <div><span style="display:block;font-size:0.65rem;color:var(--gray-500);font-weight:500;text-transform:uppercase;letter-spacing:0.05em;">Queue</span><span style="display:block;font-size:0.9rem;font-weight:500;color:var(--gray-800);">#<?= sprintf('%03d', $visit['id'] ?? 1) ?></span></div>
                </div>
            </div>
        </div>

        <!-- SECTION 2: SYMPTOMS -->
        <div class="consultation-card mb-6">
            <h3 class="card-title"><i class="fas fa-list-ul title-blue"></i> Symptoms</h3>
            <div class="form-group">
                <label class="form-label">Symptoms Description</label>
                <textarea name="symptoms" class="form-control" rows="4" 
                          placeholder="Describe patient symptoms..."><?= htmlspecialchars($visit['symptoms'] ?? '') ?></textarea>
            </div>
        </div>

        <!-- SECTION 3: LAB REQUESTS -->
        <div class="consultation-card mb-6">
            <h3 class="card-title">
                <i class="fas fa-flask title-blue"></i> Laboratory Requests
                <?php if (count($lab_requests) > 0): ?>
                    <span class="frozen-badge" id="pendingLabBadge">⏳ <?= count($lab_requests) ?> Pending</span>
                <?php endif; ?>
                <span class="text-xs text-gray-400" id="labResultsCount"><?= count($lab_results) ?> results</span>
                <button type="button" class="btn btn-outline btn-sm ml-2" onclick="addLabTest()">
                    <i class="fas fa-plus"></i> Add Test
                </button>
            </h3>
            
            <div class="alert alert-info" style="margin-bottom:16px;">
                <i class="fas fa-info-circle"></i>
                <strong>Flow:</strong> Send lab tests → Wait for results → Then proceed with Diagnosis, Medication & Procedures
            </div>
            
            <div id="labTestsContainer" class="mt-3">
                <?php if (count($lab_requests) > 0): ?>
                    <?php foreach ($lab_requests as $lab): ?>
                        <div style="display:flex;gap:10px;margin-bottom:10px;align-items:center;">
                            <input type="text" class="form-control" value="<?= htmlspecialchars($lab['test_name']) ?>" disabled style="flex:1;">
                            <span class="text-xs text-yellow-600">⏳ Pending</span>
                            <button type="button" class="btn btn-danger btn-sm" onclick="removeLabTest(this, <?= $lab['id'] ?>)" title="Remove test">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="display:flex;gap:10px;margin-bottom:10px;align-items:center;">
                        <select name="lab_tests[]" class="form-control" style="flex:1;">
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
                <button type="submit" name="send_lab" class="btn btn-warning" id="sendLabBtn">
                    <i class="fas fa-paper-plane"></i> Send to Laboratory
                </button>
                <span class="text-xs text-gray-500 self-center">
                    <i class="fas fa-info-circle"></i> Lab tests pending confirmation
                </span>
            </div>
        </div>

        <!-- SECTION 4: LAB RESULTS -->
        <div class="consultation-card mb-6 <?= $lab_results_available ? 'border-green-500' : '' ?>" id="labResultsCard">
            <h3 class="card-title">
                <i class="fas fa-file-medical-alt title-green"></i> Laboratory Results
                <?php if ($lab_results_available): ?>
                    <span class="frozen-badge success" id="resultsBadge">✅ Results Available</span>
                    <span class="text-sm font-normal text-gray-400 ml-2" id="resultsCount">(<?= count($lab_results) ?> results)</span>
                <?php elseif (count($lab_requests) > 0): ?>
                    <span class="frozen-badge" id="resultsBadge">⏳ Pending Results</span>
                <?php endif; ?>
                <span class="text-xs text-gray-400" id="resultsUpdateTime">⏱ Auto-update</span>
            </h3>
            
            <div id="labResultsContainer">
                <?php if ($lab_results_available): ?>
                    <div style="overflow-x:auto;">
                        <table style="width:100%;border-collapse:collapse;font-size:0.85rem;">
                            <thead><tr>
                                <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--gray-500);border-bottom:2px solid var(--gray-200);">Test Name</th>
                                <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--gray-500);border-bottom:2px solid var(--gray-200);">Result</th>
                                <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--gray-500);border-bottom:2px solid var(--gray-200);">Reference Range</th>
                                <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--gray-500);border-bottom:2px solid var(--gray-200);">Status</th>
                            </tr></thead>
                            <tbody id="labResultsBody">
                                <?php foreach ($lab_results as $result): ?>
                                    <tr>
                                        <td style="padding:10px 14px;border-bottom:1px solid var(--gray-200);color:var(--gray-700);"><?= htmlspecialchars($result['test_name'] ?? 'N/A') ?></td>
                                        <td style="padding:10px 14px;border-bottom:1px solid var(--gray-200);color:var(--gray-700);font-weight:600;color:#059669;"><?= htmlspecialchars($result['results'] ?? 'N/A') ?></td>
                                        <td style="padding:10px 14px;border-bottom:1px solid var(--gray-200);color:var(--gray-700);"><?= htmlspecialchars($result['reference_range'] ?? '') ?></td>
                                        <td style="padding:10px 14px;border-bottom:1px solid var(--gray-200);color:var(--gray-700);"><span class="badge badge-success">Completed</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3 text-sm text-green-600">
                        <i class="fas fa-check-circle"></i> Lab results available. You can now proceed with Diagnosis, Medication & Procedures.
                    </div>
                <?php elseif (count($lab_requests) > 0): ?>
                    <div class="text-center py-6 text-yellow-600" id="labPendingMessage">
                        <i class="fas fa-clock text-3xl block mb-2"></i>
                        <p id="pendingCountDisplay"><?= count($lab_requests) ?> lab request(s) pending</p>
                        <p class="text-xs text-gray-400 mt-1">⏳ Waiting for Laboratory to complete tests</p>
                        <div class="mt-3 text-sm text-red-500">
                            <i class="fas fa-lock"></i> Diagnosis, Medication & Procedures are <strong>FROZEN</strong> until results are available
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-6 text-gray-400" id="noLabResults">
                        <i class="fas fa-flask text-3xl block mb-2"></i>
                        <p>No lab results available</p>
                        <p class="text-xs mt-1">Send lab requests to get results</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ================================================================ -->
        <!-- FROZEN SECTIONS -->
        <!-- ================================================================ -->
        <div id="frozenSectionsContainer" class="<?= $sections_frozen ? 'frozen-overlay-active' : '' ?>">

            <!-- SECTION 5: DIAGNOSIS -->
            <div class="consultation-card mb-6">
                <h3 class="card-title">
                    <i class="fas fa-diagnoses title-blue"></i> Diagnosis
                    <?php if ($sections_frozen): ?>
                        <span class="frozen-badge" id="diagnosisFrozenBadge">🔒 Frozen - Lab Pending</span>
                    <?php endif; ?>
                </h3>
                <div class="form-group">
                    <label class="form-label">Diagnosis <span class="required">*</span></label>
                    <textarea name="diagnosis" class="form-control" rows="4" 
                              placeholder="Enter diagnosis based on lab results..." 
                              <?= $sections_frozen ? 'disabled' : '' ?> 
                              id="diagnosisInput"><?= htmlspecialchars($visit['diagnosis'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- SECTION 6: MEDICATIONS with Instructions Box -->
            <div class="consultation-card mb-6">
                <h3 class="card-title">
                    <i class="fas fa-prescription title-blue"></i> Medications
                    <?php if ($sections_frozen): ?>
                        <span class="frozen-badge" id="medicationFrozenBadge">🔒 Frozen - Lab Pending</span>
                    <?php endif; ?>
                </h3>
                
                <div style="background:var(--gray-50);border-radius:var(--radius);padding:20px;border:1px solid var(--gray-200);">
                    
                    <!-- 2 Rows: Medication & Qty -->
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="form-group">
                            <label class="form-label">Medication <span class="required">*</span></label>
                            <select name="inventory_id" class="form-control" id="medicationSelect" <?= $sections_frozen ? 'disabled' : '' ?>>
                                <option value="">Select Medication...</option>
                                <?php foreach ($medications_list as $med): ?>
                                    <option value="<?= $med['id'] ?>">
                                        <?= htmlspecialchars($med['medication_name']) ?> 
                                        (<?= $med['quantity'] ?? 0 ?> available)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Quantity</label>
                            <input type="number" id="medQuantity" class="form-control" value="1" min="1" max="99" <?= $sections_frozen ? 'disabled' : '' ?>>
                        </div>
                    </div>
                    
                    <!-- 2 Rows: Dosage & Frequency -->
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px;">
                        <div class="form-group">
                            <label class="form-label">Dosage</label>
                            <input type="text" id="medDosage" class="form-control" placeholder="e.g. 500mg" <?= $sections_frozen ? 'disabled' : '' ?>>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Frequency</label>
                            <select id="medFrequency" class="form-control" <?= $sections_frozen ? 'disabled' : '' ?>>
                                <option value="">Select</option>
                                <option value="Once Daily">Once Daily</option>
                                <option value="Twice Daily">Twice Daily</option>
                                <option value="Three Times Daily">Three Times Daily</option>
                                <option value="Four Times Daily">Four Times Daily</option>
                                <option value="Every 4 Hours">Every 4 Hours</option>
                                <option value="Every 6 Hours">Every 6 Hours</option>
                                <option value="Every 8 Hours">Every 8 Hours</option>
                                <option value="Every 12 Hours">Every 12 Hours</option>
                                <option value="As Needed (PRN)">As Needed (PRN)</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- 2 Rows: Duration & Route -->
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px;">
                        <div class="form-group">
                            <label class="form-label">Duration (Days)</label>
                            <input type="number" id="medDuration" class="form-control" value="7" min="1" max="90" <?= $sections_frozen ? 'disabled' : '' ?>>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Route</label>
                            <select id="medRoute" class="form-control" <?= $sections_frozen ? 'disabled' : '' ?>>
                                <option value="">Select</option>
                                <option value="Oral">Oral</option>
                                <option value="Topical">Topical</option>
                                <option value="Injection">Injection</option>
                                <option value="IV">IV</option>
                                <option value="Sublingual">Sublingual</option>
                                <option value="Inhalation">Inhalation</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- INSTRUCTIONS: Quick Buttons + Textarea + Tags -->
                    <div class="form-group mt-3">
                        <label class="form-label">Instructions <span class="text-xs text-gray-400">(Pick or Type)</span></label>
                        
                        <!-- Quick Instruction Buttons (10 options - 2 rows of 5) -->
                        <div class="instructions-grid">
                            <?php foreach ($prescription_instructions as $instruction): ?>
                                <button type="button" class="instruction-btn" onclick="addInstruction('<?= addslashes($instruction) ?>')">
                                    <?= htmlspecialchars($instruction) ?>
                                </button>
                            <?php endforeach; ?>
                            <button type="button" class="instruction-btn clear-btn" onclick="clearInstructions()">
                                <i class="fas fa-times"></i> Clear
                            </button>
                        </div>
                        
                        <!-- Instructions Textarea - Type OR Pick -->
                        <div class="instructions-box-wrapper">
                            <textarea id="instructionsTextarea" class="form-control" rows="3" 
                                      placeholder="Click buttons above OR type instructions here... (separate with ; )"
                                      oninput="onInstructionsInput()"
                                      style="resize:vertical;min-height:80px;font-size:0.9rem;line-height:1.6;"><?= htmlspecialchars($visit['instructions'] ?? '') ?></textarea>
                            
                            <!-- Tags Display -->
                            <div class="instructions-tags" id="instructionsTags">
                                <span class="empty-tags">No instructions added yet. Click buttons or type above.</span>
                            </div>
                        </div>
                        
                        <!-- Hidden input for form submission -->
                        <input type="hidden" id="medInstructions" name="instructions" value="<?= htmlspecialchars($visit['instructions'] ?? '') ?>">
                    </div>
                    
                    <!-- Add Medication Button -->
                    <div class="mt-3">
                        <button type="button" class="btn btn-primary" onclick="addMedicationAjax()" id="addMedicationBtn" <?= $sections_frozen ? 'disabled' : '' ?>>
                            <i class="fas fa-plus"></i> Add Medication
                        </button>
                        <?php if ($sections_frozen): ?>
                            <span class="text-xs text-red-500 ml-2"><i class="fas fa-lock"></i> Frozen until lab results</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Selected Medications List -->
                <div class="selected-medications mt-4" style="background:var(--gray-50);border-radius:var(--radius);padding:16px 20px;border:1px solid var(--gray-200);">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="text-sm font-semibold text-gray-600">
                            <i class="fas fa-list"></i> Selected Medications
                            <span class="text-xs text-gray-400" id="medCount">(<?= count($selected_medications) ?> items)</span>
                        </h4>
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
                                        <?php if (!empty($med['instructions'])): ?>
                                            <span class="med-instruction-tag"><?= htmlspecialchars($med['instructions']) ?></span>
                                        <?php endif; ?>
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
                                <?php if ($sections_frozen): ?>
                                    <p class="text-xs text-red-500 mt-1"><i class="fas fa-lock"></i> Medications frozen - lab tests pending</p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- SECTION 7: PROCEDURES & TOOLS -->
            <div class="consultation-card mb-6">
                <h3 class="card-title">
                    <i class="fas fa-syringe title-blue"></i> Procedures & Tools
                    <?php if ($sections_frozen): ?>
                        <span class="frozen-badge" id="procedureFrozenBadge">🔒 Frozen - Lab Pending</span>
                    <?php endif; ?>
                </h3>
                
                <!-- Procedures Dropdown -->
                <div style="border:1px solid var(--gray-200);border-radius:var(--radius-lg);margin-bottom:12px;overflow:hidden;">
                    <div onclick="toggleDropdown('proceduresToggle')" style="display:flex;align-items:center;justify-content:space-between;padding:12px 18px;background:var(--gray-50);cursor:pointer;user-select:none;">
                        <span style="font-weight:600;font-size:0.85rem;color:var(--gray-700);display:flex;align-items:center;gap:10px;">
                            <i class="fas fa-syringe title-blue"></i> Procedures
                            <span class="text-xs text-gray-400">(Click to expand)</span>
                        </span>
                        <span style="transition:var(--transition);color:var(--gray-400);font-size:0.8rem;"><i class="fas fa-chevron-down"></i></span>
                    </div>
                    <div id="proceduresToggle" style="padding:0 18px 18px 18px;display:none;">
                        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:8px;margin-top:8px;padding:12px;background:var(--gray-100);border-radius:var(--radius);max-height:250px;overflow-y:auto;">
                            <?php foreach ($procedures_list as $proc): ?>
                                <div class="procedure-item-select" 
                                     data-procedure-id="<?= $proc['id'] ?>"
                                     data-procedure-name="<?= htmlspecialchars($proc['procedure_name']) ?>"
                                     onclick="toggleProcedure(this)">
                                    <span class="item-check"><i class="fas fa-check"></i></span>
                                    <span><?= htmlspecialchars($proc['procedure_name']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Tools Dropdown -->
                <div style="border:1px solid var(--gray-200);border-radius:var(--radius-lg);margin-bottom:12px;overflow:hidden;">
                    <div onclick="toggleDropdown('toolsToggle')" style="display:flex;align-items:center;justify-content:space-between;padding:12px 18px;background:var(--gray-50);cursor:pointer;user-select:none;">
                        <span style="font-weight:600;font-size:0.85rem;color:var(--gray-700);display:flex;align-items:center;gap:10px;">
                            <i class="fas fa-tools title-orange"></i> Tools
                            <span class="text-xs text-gray-400">(Click to expand)</span>
                        </span>
                        <span style="transition:var(--transition);color:var(--gray-400);font-size:0.8rem;"><i class="fas fa-chevron-down"></i></span>
                    </div>
                    <div id="toolsToggle" style="padding:0 18px 18px 18px;display:none;">
                        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:8px;margin-top:8px;padding:12px;background:var(--gray-100);border-radius:var(--radius);max-height:200px;overflow-y:auto;">
                            <?php foreach ($procedure_tools as $tool): ?>
                                <div class="tool-item-select" 
                                     data-tool-id="<?= $tool['id'] ?>"
                                     data-tool-name="<?= htmlspecialchars($tool['tool_name']) ?>"
                                     data-procedure-name="<?= htmlspecialchars($tool['procedure_name']) ?>"
                                     onclick="toggleTool(this)">
                                    <span class="item-check"><i class="fas fa-check"></i></span>
                                    <span><?= htmlspecialchars($tool['tool_name']) ?></span>
                                    <small class="text-xs text-gray-400">(<?= htmlspecialchars($tool['procedure_name']) ?>)</small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="mt-3 flex flex-wrap gap-3">
                    <button type="button" class="btn btn-primary" onclick="addSelectedItems()" id="addSelectedBtn" <?= $sections_frozen ? 'disabled' : '' ?>>
                        <i class="fas fa-plus"></i> Add Selected (Procedures & Tools)
                    </button>
                    <button type="button" class="btn btn-outline btn-sm" onclick="clearAllSelections()">
                        <i class="fas fa-times"></i> Clear Selections
                    </button>
                </div>
                
                <div class="selected-items-list mt-3">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="text-sm font-semibold text-gray-600">
                            <i class="fas fa-list"></i> Selected Items
                            <span class="text-xs text-gray-400" id="selectedCount">(0 items)</span>
                        </h4>
                    </div>
                    <div id="selectedItemsList">
                        <div class="empty-state" id="emptySelected">
                            <i class="fas fa-syringe"></i>
                            <p>No procedures or tools added yet</p>
                            <?php if ($sections_frozen): ?>
                                <p class="text-xs text-red-500 mt-1"><i class="fas fa-lock"></i> Procedures frozen - lab tests pending</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- END FROZEN OVERLAY -->

        <!-- ================================================================ -->
        <!-- FORM ACTIONS -->
        <!-- ================================================================ -->
        <div class="consultation-card">
            <div class="form-actions">
                <button type="submit" name="save_draft" class="btn btn-primary" id="saveDraftBtn" <?= $sections_frozen ? 'disabled' : '' ?>>
                    <i class="fas fa-save"></i> Save Draft
                </button>
                <button type="submit" name="complete_visit" class="btn btn-success" id="completeVisitBtn" 
                        <?= $sections_frozen ? 'disabled' : '' ?>
                        onclick="return confirm('Complete this consultation?\n\n- 💊 Medications: Waiting for Pharmacy confirmation\n- 🧪 Lab Tests: Waiting for Laboratory confirmation\n- 💉 Procedures & Tools: Direct to Cashier')">
                    <i class="fas fa-check-circle"></i> Complete & Send to Cashier
                </button>
                <?php if ($sections_frozen): ?>
                    <span class="text-xs text-red-500 self-center" id="frozenActionsMessage">
                        <i class="fas fa-lock"></i> Actions frozen - Lab tests pending
                    </span>
                <?php endif; ?>
                <button type="button" class="btn btn-outline" onclick="window.print()">
                    <i class="fas fa-print"></i> Print
                </button>
                <a href="my_patients.php" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </div>

    </form>

    <?php endif; // End of active consultation mode ?>

    <!-- Footer -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            <?= $is_completed ? 'Consultation Summary' : 'Consultation' ?>
            <span class="text-gray-300 mx-2">|</span>
            <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>
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
    // CONFIGURATION
    // ================================================================
    var AUTO_UPDATE_INTERVAL = 3000;
    var updateInterval = null;
    var isUpdating = false;
    var visitId = <?= $visit_id ?>;
    var isCompleted = <?= $is_completed ? 'true' : 'false' ?>;
    
    // ================================================================
    // INSTRUCTION FUNCTIONS - Textarea + Tags
    // ================================================================
    var instructionList = [];
    
    // Initialize instructions from textarea
    function initInstructions() {
        var textarea = document.getElementById('instructionsTextarea');
        if (textarea && textarea.value) {
            var items = textarea.value.split(';').map(function(item) { return item.trim(); }).filter(function(item) { return item.length > 0; });
            instructionList = items;
            updateTags();
            updateHiddenInput();
        }
    }
    
    function addInstruction(instruction) {
        if (!instructionList.includes(instruction)) {
            instructionList.push(instruction);
        }
        updateTextarea();
        updateTags();
        updateHiddenInput();
        
        // Highlight button
        var buttons = document.querySelectorAll('.instruction-btn');
        buttons.forEach(function(btn) {
            btn.classList.remove('active');
            if (btn.textContent.trim() === instruction) {
                btn.classList.add('active');
            }
        });
    }
    
    function removeInstruction(instruction) {
        var index = instructionList.indexOf(instruction);
        if (index > -1) {
            instructionList.splice(index, 1);
        }
        updateTextarea();
        updateTags();
        updateHiddenInput();
    }
    
    function clearInstructions() {
        instructionList = [];
        updateTextarea();
        updateTags();
        updateHiddenInput();
        var buttons = document.querySelectorAll('.instruction-btn');
        buttons.forEach(function(btn) {
            btn.classList.remove('active');
        });
    }
    
    function updateTextarea() {
        var textarea = document.getElementById('instructionsTextarea');
        if (textarea) {
            textarea.value = instructionList.join('; ');
            // Trigger input event
            var event = new Event('input', { bubbles: true });
            textarea.dispatchEvent(event);
        }
    }
    
    function updateTags() {
        var tagsContainer = document.getElementById('instructionsTags');
        if (!tagsContainer) return;
        
        if (instructionList.length === 0) {
            tagsContainer.innerHTML = '<span class="empty-tags">No instructions added yet. Click buttons or type above.</span>';
            return;
        }
        
        var html = '';
        instructionList.forEach(function(inst) {
            html += '<span class="tag">' + escapeHtml(inst) + 
                    ' <span class="remove-tag" onclick="removeInstruction(\'' + escapeHtml(inst) + '\')">&times;</span></span>';
        });
        tagsContainer.innerHTML = html;
    }
    
    function updateHiddenInput() {
        var hidden = document.getElementById('medInstructions');
        if (hidden) {
            hidden.value = instructionList.join('; ');
        }
    }
    
    // ================================================================
    // MANUAL INPUT - User types in textarea
    // ================================================================
    function onInstructionsInput() {
        var textarea = document.getElementById('instructionsTextarea');
        if (!textarea) return;
        
        var text = textarea.value.trim();
        
        if (text.length > 0) {
            // Split by semicolon or newline
            var items = text.split(/[;\n]/).map(function(item) { return item.trim(); }).filter(function(item) { return item.length > 0; });
            instructionList = items;
            updateTags();
            updateHiddenInput();
        } else {
            instructionList = [];
            updateTags();
            updateHiddenInput();
        }
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    <?php if (!$is_completed): ?>
    
    // ================================================================
    // TOGGLE DROPDOWN
    // ================================================================
    function toggleDropdown(id) {
        var body = document.getElementById(id);
        var header = body.previousElementSibling;
        if (body.style.display === 'none' || body.style.display === '') {
            body.style.display = 'block';
            var icon = header.querySelector('.toggle-icon i');
            if (icon) icon.style.transform = 'rotate(180deg)';
        } else {
            body.style.display = 'none';
            var icon = header.querySelector('.toggle-icon i');
            if (icon) icon.style.transform = 'rotate(0deg)';
        }
    }

    // ================================================================
    // ADD LAB TEST
    // ================================================================
    function addLabTest() {
        var container = document.getElementById('labTestsContainer');
        var row = document.createElement('div');
        row.style.cssText = 'display:flex;gap:10px;margin-bottom:10px;align-items:center;';
        row.innerHTML = `
            <select name="lab_tests[]" class="form-control" style="flex:1;">
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
    // REMOVE LAB TEST
    // ================================================================
    function removeLabTest(element, testId) {
        if (!confirm('Remove this lab test?')) return;
        var formData = new FormData();
        formData.append('action', 'remove_lab_test');
        formData.append('test_id', testId);
        fetch(window.location.href, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                element.closest('.lab-test-row').remove();
                showToast('Success', 'Lab test removed', 'success');
                location.reload();
            } else {
                showToast('Error', data.message, 'error');
            }
        });
    }

    // ================================================================
    // TOGGLE PROCEDURE / TOOL
    // ================================================================
    function toggleProcedure(element) { element.classList.toggle('selected'); }
    function toggleTool(element) { element.classList.toggle('selected'); }
    function clearAllSelections() {
        document.querySelectorAll('.procedure-item-select.selected, .tool-item-select.selected').forEach(function(el) {
            el.classList.remove('selected');
        });
    }

    // ================================================================
    // GET SELECTED ITEMS
    // ================================================================
    function getSelectedItems() {
        var procedures = [], tools = [];
        document.querySelectorAll('.procedure-item-select.selected').forEach(function(item) {
            procedures.push({ id: item.dataset.procedureId, name: item.dataset.procedureName, type: 'procedure' });
        });
        document.querySelectorAll('.tool-item-select.selected').forEach(function(item) {
            tools.push({ id: item.dataset.toolId, name: item.dataset.toolName, type: 'tool' });
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
        
        var btn = document.getElementById('addSelectedBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
        
        var promises = [];
        selected.procedures.forEach(function(proc) {
            var formData = new FormData();
            formData.append('action', 'add_procedure');
            formData.append('procedure_id', proc.id);
            promises.push(fetch(window.location.href, { method: 'POST', body: formData }).then(response => response.json()));
        });
        selected.tools.forEach(function(tool) {
            var formData = new FormData();
            formData.append('action', 'add_tool');
            formData.append('tool_id', tool.id);
            promises.push(fetch(window.location.href, { method: 'POST', body: formData }).then(response => response.json()));
        });
        
        Promise.all(promises).then(function(results) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-plus"></i> Add Selected (Procedures & Tools)';
            var successCount = 0;
            results.forEach(function(data) {
                if (data.success) {
                    successCount++;
                    if (data.procedure) addItemToList(data.procedure, 'procedure');
                    else if (data.tool) addItemToList(data.tool, 'tool');
                }
            });
            if (successCount > 0) {
                showToast('Success', '✅ ' + successCount + ' item(s) added successfully!', 'success');
                clearAllSelections();
                updateSelectedCount();
            } else {
                showToast('Error', 'Failed to add items', 'error');
            }
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
            <button type="button" class="btn-remove" onclick="removeSelectedItem(${item.id})" title="Remove item"><i class="fas fa-times"></i></button>
        `;
        list.appendChild(itemEl);
        updateSelectedCount();
    }

    // ================================================================
    // REMOVE SELECTED ITEM
    // ================================================================
    function removeSelectedItem(itemId) {
        if (!confirm('Remove this item from bill?')) return;
        var formData = new FormData();
        formData.append('action', 'remove_item');
        formData.append('item_id', itemId);
        fetch(window.location.href, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Info', data.message, 'info');
                var item = document.getElementById('selected-item-' + itemId);
                if (item) item.remove();
                updateSelectedCount();
            } else {
                showToast('Error', data.message, 'error');
            }
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
    }

    // ================================================================
    // ADD MEDICATION AJAX
    // ================================================================
    function addMedicationAjax() {
        var medSelect = document.getElementById('medicationSelect');
        var qty = parseInt(document.getElementById('medQuantity').value) || 0;
        var dosage = document.getElementById('medDosage').value;
        var frequency = document.getElementById('medFrequency').value;
        var duration = document.getElementById('medDuration').value;
        var route = document.getElementById('medRoute').value;
        var instructions = document.getElementById('medInstructions').value;
        
        if (!medSelect.value) { showToast('Error', 'Please select a medication', 'error'); return; }
        if (qty < 1) { showToast('Error', 'Quantity must be at least 1', 'error'); return; }
        
        var formData = new FormData();
        formData.append('action', 'add_medication');
        formData.append('inventory_id', medSelect.value);
        formData.append('quantity', qty);
        formData.append('dosage', dosage);
        formData.append('frequency', frequency);
        formData.append('duration', duration);
        formData.append('route', route);
        formData.append('instructions', instructions);
        
        var btn = document.getElementById('addMedicationBtn');
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
                clearInstructions();
                updateMedCount();
            } else {
                showToast('Error', data.message, 'error');
            }
        });
    }

    // ================================================================
    // ADD MEDICATION TO LIST
    // ================================================================
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
                <span class="med-details">${escapeHtml(med.dosage || '')} • ${escapeHtml(med.frequency || '')} • ${escapeHtml(med.duration || '')} days</span>
                <span class="med-qty">x${med.quantity}</span>
                ${med.instructions ? `<span class="med-instruction-tag">${escapeHtml(med.instructions)}</span>` : ''}
            </div>
            <button type="button" class="btn-remove" onclick="removeMedication(${med.id})"><i class="fas fa-times"></i></button>
        `;
        list.appendChild(item);
        updateMedCount();
    }

    // ================================================================
    // REMOVE MEDICATION
    // ================================================================
    function removeMedication(prescriptionId) {
        if (!confirm('Remove this medication?')) return;
        var formData = new FormData();
        formData.append('action', 'remove_medication');
        formData.append('prescription_id', prescriptionId);
        fetch(window.location.href, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Info', data.message, 'info');
                var item = document.getElementById('med-item-' + prescriptionId);
                if (item) item.remove();
                updateMedCount();
            } else {
                showToast('Error', data.message, 'error');
            }
        });
    }

    // ================================================================
    // UPDATE MED COUNT
    // ================================================================
    function updateMedCount() {
        var list = document.getElementById('medicationsList');
        var count = list ? list.querySelectorAll('.medication-item').length : 0;
        var countEl = document.getElementById('medCount');
        if (countEl) countEl.textContent = '(' + count + ' items)';
    }

    // ================================================================
    // TOAST
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
    // AUTO-UPDATE
    // ================================================================
    function fetchLabStatus() {
        if (isUpdating || isCompleted) return;
        isUpdating = true;
        
        var formData = new FormData();
        formData.append('action', 'get_lab_status');
        formData.append('visit_id', visitId);
        
        fetch(window.location.href, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateUI(data);
            }
            isUpdating = false;
        })
        .catch(function(error) {
            console.error('Auto-update error:', error);
            isUpdating = false;
        });
    }

    function updateUI(data) {
        var now = new Date();
        var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        
        var liveTime = document.getElementById('liveTime');
        if (liveTime) liveTime.textContent = timeStr;
        
        var lastUpdate = document.getElementById('lastUpdateTime');
        if (lastUpdate) lastUpdate.textContent = '⏱ ' + timeStr;
        
        var resultsUpdateTime = document.getElementById('resultsUpdateTime');
        if (resultsUpdateTime) resultsUpdateTime.textContent = '⏱ ' + timeStr;
        
        var pendingBadge = document.getElementById('pendingLabBadge');
        if (pendingBadge) {
            if (data.pending_count > 0) {
                pendingBadge.textContent = '⏳ ' + data.pending_count + ' Pending';
                pendingBadge.style.display = 'inline-block';
            } else {
                pendingBadge.style.display = 'none';
            }
        }
        
        var resultsCount = document.getElementById('resultsCount');
        if (resultsCount) {
            if (data.results_count > 0) {
                resultsCount.textContent = '(' + data.results_count + ' results)';
            } else {
                resultsCount.textContent = '(0 results)';
            }
        }
        
        var resultsBadge = document.getElementById('resultsBadge');
        if (resultsBadge) {
            if (data.available) {
                resultsBadge.textContent = '✅ Results Available';
                resultsBadge.className = 'frozen-badge success';
            } else if (data.pending_count > 0) {
                resultsBadge.textContent = '⏳ Pending Results';
                resultsBadge.className = 'frozen-badge';
            } else {
                resultsBadge.textContent = '';
                resultsBadge.style.display = 'none';
            }
        }
        
        var frozenSections = document.getElementById('frozenSectionsContainer');
        var isFrozen = data.frozen;
        
        if (frozenSections) {
            if (isFrozen) {
                frozenSections.className = 'frozen-overlay-active';
            } else {
                frozenSections.className = '';
            }
        }
        
        var frozenBadgeHeader = document.getElementById('frozenBadgeHeader');
        if (frozenBadgeHeader) {
            if (isFrozen) {
                frozenBadgeHeader.textContent = '🔒 Lab Pending';
                frozenBadgeHeader.className = 'frozen-badge';
                frozenBadgeHeader.style.display = 'inline-block';
            } else if (data.available) {
                frozenBadgeHeader.textContent = '✅ Lab Results Available';
                frozenBadgeHeader.className = 'frozen-badge success';
                frozenBadgeHeader.style.display = 'inline-block';
            } else {
                frozenBadgeHeader.style.display = 'none';
            }
        }
        
        var frozenBadgeSub = document.getElementById('frozenBadgeSub');
        if (frozenBadgeSub) {
            if (isFrozen) {
                frozenBadgeSub.textContent = '⏳ Waiting for Lab Results';
                frozenBadgeSub.style.display = 'inline-block';
            } else {
                frozenBadgeSub.style.display = 'none';
            }
        }
        
        var buttons = ['saveDraftBtn', 'completeVisitBtn', 'addMedicationBtn', 'addSelectedBtn'];
        buttons.forEach(function(id) {
            var btn = document.getElementById(id);
            if (btn) {
                btn.disabled = isFrozen;
                if (isFrozen) {
                    btn.style.opacity = '0.5';
                    btn.style.cursor = 'not-allowed';
                } else {
                    btn.style.opacity = '1';
                    btn.style.cursor = 'pointer';
                }
            }
        });
        
        var frozenMsg = document.getElementById('frozenActionsMessage');
        if (frozenMsg) {
            if (isFrozen) {
                frozenMsg.style.display = 'inline-block';
            } else {
                frozenMsg.style.display = 'none';
            }
        }
        
        var diagnosisInput = document.getElementById('diagnosisInput');
        if (diagnosisInput) {
            diagnosisInput.disabled = isFrozen;
        }
        
        var medSelect = document.getElementById('medicationSelect');
        var medInputs = ['medQuantity', 'medDosage', 'medDuration'];
        if (medSelect) medSelect.disabled = isFrozen;
        medInputs.forEach(function(id) {
            var input = document.getElementById(id);
            if (input) input.disabled = isFrozen;
        });
        
        var frozenBadges = ['diagnosisFrozenBadge', 'medicationFrozenBadge', 'procedureFrozenBadge'];
        frozenBadges.forEach(function(id) {
            var badge = document.getElementById(id);
            if (badge) {
                if (isFrozen) {
                    badge.style.display = 'inline-block';
                } else {
                    badge.style.display = 'none';
                }
            }
        });
        
        var labContainer = document.getElementById('labResultsContainer');
        if (labContainer) {
            if (data.available && data.results_html) {
                labContainer.innerHTML = `
                    <div style="overflow-x:auto;">
                        <table style="width:100%;border-collapse:collapse;font-size:0.85rem;">
                            <thead><tr>
                                <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--gray-500);border-bottom:2px solid var(--gray-200);">Test Name</th>
                                <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--gray-500);border-bottom:2px solid var(--gray-200);">Result</th>
                                <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--gray-500);border-bottom:2px solid var(--gray-200);">Reference Range</th>
                                <th style="text-align:left;padding:10px 14px;font-weight:600;font-size:0.7rem;text-transform:uppercase;color:var(--gray-500);border-bottom:2px solid var(--gray-200);">Status</th>
                            </tr></thead>
                            <tbody id="labResultsBody">
                                ${data.results_html}
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3 text-sm text-green-600">
                        <i class="fas fa-check-circle"></i> Lab results available. You can now proceed with Diagnosis, Medication & Procedures.
                    </div>
                `;
                var card = document.getElementById('labResultsCard');
                if (card) card.className = 'consultation-card mb-6 border-green-500';
            } else if (data.pending_count > 0) {
                labContainer.innerHTML = `
                    <div class="text-center py-6 text-yellow-600">
                        <i class="fas fa-clock text-3xl block mb-2"></i>
                        <p>${data.pending_count} lab request(s) pending</p>
                        <p class="text-xs text-gray-400 mt-1">⏳ Waiting for Laboratory to complete tests</p>
                        <div class="mt-3 text-sm text-red-500">
                            <i class="fas fa-lock"></i> Diagnosis, Medication & Procedures are <strong>FROZEN</strong> until results are available
                        </div>
                    </div>
                `;
                var card = document.getElementById('labResultsCard');
                if (card) card.className = 'consultation-card mb-6';
            } else {
                labContainer.innerHTML = `
                    <div class="text-center py-6 text-gray-400">
                        <i class="fas fa-flask text-3xl block mb-2"></i>
                        <p>No lab results available</p>
                        <p class="text-xs mt-1">Send lab requests to get results</p>
                    </div>
                `;
                var card = document.getElementById('labResultsCard');
                if (card) card.className = 'consultation-card mb-6';
            }
        }
    }

    function manualRefresh() {
        var btn = document.getElementById('refreshBtn');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
        btn.disabled = true;
        fetchLabStatus();
        setTimeout(function() {
            btn.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh';
            btn.disabled = false;
            showToast('✅ Refreshed', 'Data updated manually', 'success');
        }, 1500);
    }

    function startAutoUpdate() {
        if (isCompleted) return;
        if (updateInterval) clearInterval(updateInterval);
        fetchLabStatus();
        updateInterval = setInterval(fetchLabStatus, AUTO_UPDATE_INTERVAL);
        console.log('%c🔄 Auto-update started (every ' + AUTO_UPDATE_INTERVAL/1000 + 's)', 'font-size:12px; color:#34D399;');
    }
    
    function stopAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
            updateInterval = null;
            console.log('%c⏹️ Auto-update stopped', 'font-size:12px; color:#DC2626;');
        }
    }

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopAutoUpdate();
        } else {
            startAutoUpdate();
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        if (!isCompleted) {
            setTimeout(function() { startAutoUpdate(); }, 1000);
        }
        updateSelectedCount();
        updateMedCount();
        initInstructions();
    });

    <?php endif; // End of active consultation mode ?>

    // ================================================================
    // DARK MODE
    // ================================================================
    if (localStorage.getItem('darkMode') === 'true') {
        document.documentElement.setAttribute('data-theme', 'dark');
    }

    <?php if ($message && $message_type): ?>
        setTimeout(function() {
            showToast('<?= $message_type === 'success' ? '✅ Success' : ($message_type === 'warning' ? '⚠️ Notice' : '❌ Error') ?>', 
                '<?= addslashes($message) ?>', 
                '<?= $message_type ?>'
            );
        }, 500);
    <?php endif; ?>

    console.log('%c👨‍⚕️ Consultation', 'font-size:16px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Visit: <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>', 'font-size:12px; color:#059669;');
    console.log('%c📊 Status: <?= $is_completed ? 'COMPLETED ✅' : 'ACTIVE' ?>', 'font-size:12px; color:#7C3AED;');
    console.log('%c💊 Instructions: Pick or Type', 'font-size:12px; color:#34D399;');
    <?php if ($is_completed): ?>
    console.log('%c✅ View Mode - All details shown', 'font-size:12px; color:#059669;');
    <?php else: ?>
    console.log('%c🔄 Auto-update every 3 seconds', 'font-size:12px; color:#34D399;');
    <?php endif; ?>
</script>

</body>
</html>