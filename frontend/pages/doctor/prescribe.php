<?php
// ================================================================
// FILE: frontend/pages/doctor/prescribe.php
// DOCTOR - PRESCRIBE MEDICATION
// FEATURES:
// - Toggle dropdown with search filter (like consultation)
// - Checkboxes for selecting multiple medications
// - 3 medications in a row
// - Auto-dispense - stock reduced after dispensing
// - Bills go to cashiers
// - Stock movements recorded
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
$is_admin = ($_SESSION['role'] === 'admin');

// ================================================================
// GET PARAMETERS
// ================================================================
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$visit_id = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;

if ($patient_id <= 0) {
    header('Location: my_patients.php?error=invalid_patient');
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
// GET PATIENT DATA
// ================================================================
$stmt = $db->prepare("
    SELECT p.*, b.name as branch_name
    FROM patients p
    LEFT JOIN branches b ON p.branch_id = b.id
    WHERE p.id = ?
");
$stmt->execute([$patient_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    header('Location: my_patients.php?error=patient_not_found');
    exit;
}

// ================================================================
// GET OR CREATE ACTIVE VISIT
// ================================================================
if ($visit_id > 0) {
    $stmt = $db->prepare("
        SELECT id, status, visit_number
        FROM visits
        WHERE id = ? AND patient_id = ? AND doctor_id = ?
        AND status NOT IN ('completed', 'cancelled')
    ");
    $stmt->execute([$visit_id, $patient_id, $doctor_id]);
    $visit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$visit) {
        $visit_number = 'VIS-' . date('Ymd') . '-' . str_pad($patient_id, 4, '0', STR_PAD_LEFT);
        $stmt = $db->prepare("
            INSERT INTO visits (visit_number, patient_id, doctor_id, branch_id, visit_type, status, created_at)
            VALUES (?, ?, ?, ?, 'new', 'assigned', NOW())
        ");
        $stmt->execute([$visit_number, $patient_id, $doctor_id, $doctor_branch_id]);
        $visit_id = $db->lastInsertId();
        $visit = ['id' => $visit_id, 'status' => 'assigned', 'visit_number' => $visit_number];
    }
} else {
    $stmt = $db->prepare("
        SELECT id, status, visit_number
        FROM visits
        WHERE patient_id = ? AND doctor_id = ? AND status NOT IN ('completed', 'cancelled')
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$patient_id, $doctor_id]);
    $visit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$visit) {
        $visit_number = 'VIS-' . date('Ymd') . '-' . str_pad($patient_id, 4, '0', STR_PAD_LEFT);
        $stmt = $db->prepare("
            INSERT INTO visits (visit_number, patient_id, doctor_id, branch_id, visit_type, status, created_at)
            VALUES (?, ?, ?, ?, 'new', 'assigned', NOW())
        ");
        $stmt->execute([$visit_number, $patient_id, $doctor_id, $doctor_branch_id]);
        $visit_id = $db->lastInsertId();
        $visit = ['id' => $visit_id, 'status' => 'assigned', 'visit_number' => $visit_number];
    }
    $visit_id = $visit['id'];
}

// ================================================================
// GET OR CREATE BILL
// ================================================================
$bill_id = null;
$stmt = $db->prepare("SELECT id, status FROM bills WHERE visit_id = ?");
$stmt->execute([$visit_id]);
$bill = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$bill) {
    $bill_number = 'BILL-' . date('Ymd') . '-' . str_pad($patient_id, 6, '0', STR_PAD_LEFT);
    $stmt = $db->prepare("
        INSERT INTO bills (bill_number, patient_id, visit_id, subtotal, total_amount, balance, status, created_by, branch_id, created_at)
        VALUES (?, ?, ?, 0, 0, 0, 'pending', ?, ?, NOW())
    ");
    $stmt->execute([$bill_number, $patient_id, $visit_id, $doctor_id, $doctor_branch_id]);
    $bill_id = $db->lastInsertId();
    $bill = ['id' => $bill_id, 'status' => 'pending'];
} else {
    $bill_id = $bill['id'];
}

// ================================================================
// GET MEDICATIONS INVENTORY
// ================================================================
$medications = [];
try {
    $stmt = $db->prepare("
        SELECT id, medication_name, category, unit, selling_price, quantity, 
               batch_number, expiry_date
        FROM medications_inventory
        WHERE status = 'active' AND quantity > 0 AND branch_id = ?
        AND (expiry_date IS NULL OR expiry_date > CURDATE())
        ORDER BY medication_name
    ");
    $stmt->execute([$doctor_branch_id]);
    $medications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $medications = [];
}

// ================================================================
// GET EXISTING PRESCRIPTIONS FOR THIS VISIT
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
        LEFT JOIN prescription_items pi ON p.id = pi.prescription_id
        WHERE p.visit_id = ? AND p.status != 'cancelled'
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

// ================================================================
// HANDLE FORM SUBMISSIONS
// ================================================================
$flash_message = '';
$flash_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // ================================================================
    // ADD MULTIPLE MEDICATIONS (BATCH)
    // ================================================================
    if ($action === 'add_medications_batch') {
        $selected_meds = isset($_POST['medication_ids']) ? (array)$_POST['medication_ids'] : [];
        $quantities = isset($_POST['quantities']) ? (array)$_POST['quantities'] : [];
        $dosages = isset($_POST['dosages']) ? (array)$_POST['dosages'] : [];
        $frequencies = isset($_POST['frequencies']) ? (array)$_POST['frequencies'] : [];
        $durations = isset($_POST['durations']) ? (array)$_POST['durations'] : [];
        $routes = isset($_POST['routes']) ? (array)$_POST['routes'] : [];
        $instructions = isset($_POST['instructions']) ? (array)$_POST['instructions'] : [];
        
        if (empty($selected_meds)) {
            $flash_message = '❌ Please select at least one medication.';
            $flash_type = 'error';
        } else {
            $added_count = 0;
            $errors = [];
            
            try {
                $db->beginTransaction();
                
                foreach ($selected_meds as $index => $inventory_id) {
                    $inventory_id = (int)$inventory_id;
                    $quantity = (int)($quantities[$index] ?? 1);
                    $dosage = trim($dosages[$index] ?? '');
                    $frequency = trim($frequencies[$index] ?? '');
                    $duration = trim($durations[$index] ?? '7');
                    $route = trim($routes[$index] ?? '');
                    $instruction = trim($instructions[$index] ?? '');
                    
                    if ($inventory_id <= 0 || $quantity <= 0) {
                        $errors[] = "Invalid medication at index $index";
                        continue;
                    }
                    
                    if (empty($frequency)) {
                        $errors[] = "Frequency required for medication at index $index";
                        continue;
                    }
                    
                    if (empty($route)) {
                        $errors[] = "Route required for medication at index $index";
                        continue;
                    }
                    
                    // Get medication details
                    $stmt = $db->prepare("
                        SELECT id, medication_name, selling_price, unit, quantity as stock,
                               batch_number, expiry_date, category
                        FROM medications_inventory
                        WHERE id = ? AND status = 'active' AND branch_id = ?
                        FOR UPDATE
                    ");
                    $stmt->execute([$inventory_id, $doctor_branch_id]);
                    $med = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$med) {
                        $errors[] = "Medication not found: ID $inventory_id";
                        continue;
                    }
                    
                    if ($med['stock'] < $quantity) {
                        $errors[] = "Insufficient stock for " . $med['medication_name'] . ". Available: " . $med['stock'];
                        continue;
                    }
                    
                    // Create prescription
                    $prescription_number = 'PRES-' . date('Ymd') . '-' . str_pad($patient_id, 4, '0', STR_PAD_LEFT) . '-' . rand(100, 999);
                    
                    $stmt = $db->prepare("
                        INSERT INTO prescriptions (prescription_number, visit_id, patient_id, doctor_id, status, branch_id, created_at)
                        VALUES (?, ?, ?, ?, 'pending', ?, NOW())
                    ");
                    $stmt->execute([$prescription_number, $visit_id, $patient_id, $doctor_id, $doctor_branch_id]);
                    $prescription_id = $db->lastInsertId();
                    
                    $unit_price = $med['selling_price'];
                    $total_price = $unit_price * $quantity;
                    $new_stock = $med['stock'] - $quantity;
                    
                    // Update stock
                    $stmt = $db->prepare("UPDATE medications_inventory SET quantity = ? WHERE id = ?");
                    $stmt->execute([$new_stock, $inventory_id]);
                    
                    // Log stock movement
                    $stmt = $db->prepare("
                        INSERT INTO stock_movements 
                        (inventory_id, patient_id, movement_type, quantity, previous_stock, new_stock, 
                         reference_type, reference_id, performed_by, branch_id, notes)
                        VALUES (?, ?, 'out', ?, ?, ?, 'prescription', ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $inventory_id,
                        $patient_id,
                        $quantity,
                        $med['stock'],
                        $new_stock,
                        $prescription_id,
                        $doctor_id,
                        $doctor_branch_id,
                        "Prescribed: " . $med['medication_name'] . " | Batch: " . ($med['batch_number'] ?? 'N/A')
                    ]);
                    
                    // Add prescription item
                    $stmt = $db->prepare("
                        INSERT INTO prescription_items (prescription_id, patient_id, inventory_id, medication_name,
                            dosage, frequency, quantity, duration, route, instructions, unit_price, total_price, branch_id, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $prescription_id, $patient_id, $inventory_id, $med['medication_name'],
                        $dosage, $frequency, $quantity, $duration, $route, $instruction,
                        $unit_price, $total_price, $doctor_branch_id
                    ]);
                    
                    // Add to bill
                    $stmt = $db->prepare("
                        INSERT INTO bill_items (bill_id, patient_id, branch_id, item_type, item_name, quantity, unit_price, total_price, status, reference_id, reference_type, created_at)
                        VALUES (?, ?, ?, 'medication', ?, ?, ?, ?, 'pending', ?, 'prescription', NOW())
                    ");
                    $stmt->execute([
                        $bill_id, $patient_id, $doctor_branch_id,
                        $med['medication_name'] . ' (Batch: ' . ($med['batch_number'] ?? 'N/A') . ')',
                        $quantity, $unit_price, $total_price, $prescription_id
                    ]);
                    
                    $added_count++;
                }
                
                $db->commit();
                
                if ($added_count > 0) {
                    $flash_message = '✅ ' . $added_count . ' medication(s) prescribed successfully! Bill sent to cashier.';
                    $flash_type = 'success';
                    
                    // Refresh prescriptions list
                    $stmt = $db->prepare("
                        SELECT p.*, 
                               pi.id as item_id, pi.medication_name, pi.dosage, pi.frequency, 
                               pi.quantity, pi.duration, pi.route, pi.instructions,
                               pi.unit_price, pi.total_price,
                               pi.dispensed_at, pi.dispensed_by,
                               pi.inventory_id
                        FROM prescriptions p
                        LEFT JOIN prescription_items pi ON p.id = pi.prescription_id
                        WHERE p.visit_id = ? AND p.status != 'cancelled'
                        ORDER BY p.created_at DESC
                    ");
                    $stmt->execute([$visit_id]);
                    $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $medications_total = 0;
                    foreach ($prescriptions as $presc) {
                        $medications_total += $presc['total_price'] ?? 0;
                    }
                    
                    // Update bill totals
                    updateBillTotal($db, $bill_id);
                }
                
                if (!empty($errors)) {
                    $flash_message .= '<br>⚠️ Errors: ' . implode(', ', $errors);
                }
                
            } catch (Exception $e) {
                if (isset($db) && $db->inTransaction()) {
                    $db->rollBack();
                }
                $flash_message = '❌ Database error: ' . $e->getMessage();
                $flash_type = 'error';
            }
        }
    }
    
    // ================================================================
    // REMOVE MEDICATION
    // ================================================================
    if ($action === 'remove_medication') {
        $prescription_id = (int)($_POST['prescription_id'] ?? 0);
        
        if ($prescription_id > 0) {
            try {
                $stmt = $db->prepare("SELECT status FROM prescriptions WHERE id = ? AND visit_id = ?");
                $stmt->execute([$prescription_id, $visit_id]);
                $presc = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($presc && $presc['status'] === 'dispensed') {
                    $flash_message = '❌ Cannot remove - already dispensed.';
                    $flash_type = 'error';
                } else {
                    $db->beginTransaction();
                    
                    // Get medication details to return stock
                    $stmt = $db->prepare("
                        SELECT pi.medication_name, pi.quantity, pi.inventory_id, pi.total_price
                        FROM prescription_items pi
                        WHERE pi.prescription_id = ?
                    ");
                    $stmt->execute([$prescription_id]);
                    $med_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($med_items as $med) {
                        if ($med && $med['inventory_id']) {
                            $stmt = $db->prepare("
                                UPDATE medications_inventory
                                SET quantity = quantity + ?
                                WHERE id = ? AND branch_id = ?
                            ");
                            $stmt->execute([$med['quantity'], $med['inventory_id'], $doctor_branch_id]);
                            
                            // Log stock movement (return)
                            $stmt = $db->prepare("
                                INSERT INTO stock_movements 
                                (inventory_id, patient_id, movement_type, quantity, previous_stock, new_stock, 
                                 reference_type, reference_id, performed_by, branch_id, notes)
                                VALUES (?, ?, 'in', ?, ?, ?, 'adjustment', ?, ?, ?, ?)
                            ");
                            $stmt->execute([
                                $med['inventory_id'],
                                $patient_id,
                                $med['quantity'],
                                0,
                                $med['quantity'],
                                $prescription_id,
                                $doctor_id,
                                $doctor_branch_id,
                                "Stock returned from removed prescription: " . $med['medication_name']
                            ]);
                        }
                    }
                    
                    // Remove from bill
                    $stmt = $db->prepare("
                        DELETE FROM bill_items
                        WHERE bill_id = ? AND reference_id = ? AND reference_type = 'prescription'
                    ");
                    $stmt->execute([$bill_id, $prescription_id]);
                    
                    // Remove prescription items
                    $stmt = $db->prepare("DELETE FROM prescription_items WHERE prescription_id = ?");
                    $stmt->execute([$prescription_id]);
                    
                    // Remove prescription
                    $stmt = $db->prepare("DELETE FROM prescriptions WHERE id = ? AND visit_id = ?");
                    $stmt->execute([$prescription_id, $visit_id]);
                    
                    $db->commit();
                    
                    $flash_message = '✅ Medication removed! Stock returned.';
                    $flash_type = 'success';
                    
                    // Refresh prescriptions list
                    $stmt = $db->prepare("
                        SELECT p.*, 
                               pi.id as item_id, pi.medication_name, pi.dosage, pi.frequency, 
                               pi.quantity, pi.duration, pi.route, pi.instructions,
                               pi.unit_price, pi.total_price,
                               pi.dispensed_at, pi.dispensed_by,
                               pi.inventory_id
                        FROM prescriptions p
                        LEFT JOIN prescription_items pi ON p.id = pi.prescription_id
                        WHERE p.visit_id = ? AND p.status != 'cancelled'
                        ORDER BY p.created_at DESC
                    ");
                    $stmt->execute([$visit_id]);
                    $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $medications_total = 0;
                    foreach ($prescriptions as $presc) {
                        $medications_total += $presc['total_price'] ?? 0;
                    }
                    
                    updateBillTotal($db, $bill_id);
                }
            } catch (Exception $e) {
                if (isset($db) && $db->inTransaction()) {
                    $db->rollBack();
                }
                $flash_message = '❌ Error: ' . $e->getMessage();
                $flash_type = 'error';
            }
        }
    }
    
    // ================================================================
    // DISPENSE MEDICATION (Auto-dispense - reduces stock)
    // ================================================================
    if ($action === 'dispense_medication') {
        $prescription_id = (int)($_POST['prescription_id'] ?? 0);
        
        if ($prescription_id > 0) {
            try {
                $db->beginTransaction();
                
                // Check if already dispensed
                $stmt = $db->prepare("SELECT status FROM prescriptions WHERE id = ? AND visit_id = ?");
                $stmt->execute([$prescription_id, $visit_id]);
                $presc = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$presc) {
                    $flash_message = '❌ Prescription not found.';
                    $flash_type = 'error';
                } elseif ($presc['status'] === 'dispensed') {
                    $flash_message = '⚠️ Already dispensed.';
                    $flash_type = 'warning';
                } else {
                    // Get medication items with current stock
                    $stmt = $db->prepare("
                        SELECT pi.*, mi.quantity as current_stock
                        FROM prescription_items pi
                        LEFT JOIN medications_inventory mi ON pi.inventory_id = mi.id
                        WHERE pi.prescription_id = ?
                        FOR UPDATE
                    ");
                    $stmt->execute([$prescription_id]);
                    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $can_dispense = true;
                    foreach ($items as $item) {
                        if ($item['inventory_id'] && $item['current_stock'] < $item['quantity']) {
                            $flash_message = '❌ Insufficient stock for ' . $item['medication_name'] . '. Available: ' . $item['current_stock'];
                            $flash_type = 'error';
                            $can_dispense = false;
                            break;
                        }
                    }
                    
                    if ($can_dispense) {
                        // Update stock for each item
                        foreach ($items as $item) {
                            if ($item['inventory_id']) {
                                $new_stock = $item['current_stock'] - $item['quantity'];
                                $stmt = $db->prepare("
                                    UPDATE medications_inventory 
                                    SET quantity = ? 
                                    WHERE id = ? AND branch_id = ?
                                ");
                                $stmt->execute([$new_stock, $item['inventory_id'], $doctor_branch_id]);
                                
                                // Log stock movement
                                $stmt = $db->prepare("
                                    INSERT INTO stock_movements 
                                    (inventory_id, patient_id, movement_type, quantity, previous_stock, new_stock, 
                                     reference_type, reference_id, performed_by, branch_id, notes)
                                    VALUES (?, ?, 'out', ?, ?, ?, 'prescription', ?, ?, ?, ?)
                                ");
                                $stmt->execute([
                                    $item['inventory_id'],
                                    $patient_id,
                                    $item['quantity'],
                                    $item['current_stock'],
                                    $new_stock,
                                    $prescription_id,
                                    $doctor_id,
                                    $doctor_branch_id,
                                    "Dispensed: " . $item['medication_name']
                                ]);
                            }
                        }
                        
                        // Update prescription status
                        $stmt = $db->prepare("
                            UPDATE prescriptions 
                            SET status = 'dispensed', 
                                dispensed_at = NOW(),
                                dispensed_by = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$doctor_id, $prescription_id]);
                        
                        // Update bill items status to paid
                        $stmt = $db->prepare("
                            UPDATE bill_items 
                            SET status = 'paid' 
                            WHERE bill_id = ? AND reference_id = ? AND reference_type = 'prescription'
                        ");
                        $stmt->execute([$bill_id, $prescription_id]);
                        
                        $db->commit();
                        
                        $flash_message = '✅ Medication dispensed successfully! Stock updated.';
                        $flash_type = 'success';
                        
                        // Refresh prescriptions list
                        $stmt = $db->prepare("
                            SELECT p.*, 
                                   pi.id as item_id, pi.medication_name, pi.dosage, pi.frequency, 
                                   pi.quantity, pi.duration, pi.route, pi.instructions,
                                   pi.unit_price, pi.total_price,
                                   pi.dispensed_at, pi.dispensed_by,
                                   pi.inventory_id
                            FROM prescriptions p
                            LEFT JOIN prescription_items pi ON p.id = pi.prescription_id
                            WHERE p.visit_id = ? AND p.status != 'cancelled'
                            ORDER BY p.created_at DESC
                        ");
                        $stmt->execute([$visit_id]);
                        $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        $medications_total = 0;
                        foreach ($prescriptions as $presc) {
                            $medications_total += $presc['total_price'] ?? 0;
                        }
                        
                        updateBillTotal($db, $bill_id);
                    }
                }
            } catch (Exception $e) {
                if (isset($db) && $db->inTransaction()) {
                    $db->rollBack();
                }
                $flash_message = '❌ Error: ' . $e->getMessage();
                $flash_type = 'error';
            }
        }
    }
}

// ================================================================
// GET BILL TOTALS
// ================================================================
function updateBillTotal($db, $bill_id) {
    $stmt = $db->prepare("
        SELECT SUM(total_price) as total
        FROM bill_items
        WHERE bill_id = ? AND status != 'cancelled'
    ");
    $stmt->execute([$bill_id]);
    $subtotal = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    
    $stmt = $db->prepare("SELECT total_discount FROM bills WHERE id = ?");
    $stmt->execute([$bill_id]);
    $total_discount = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total_discount'] ?? 0);
    
    $total_amount = max(0, $subtotal - $total_discount);
    
    $stmt = $db->prepare("SELECT SUM(amount) as payment_total FROM payments WHERE bill_id = ?");
    $stmt->execute([$bill_id]);
    $paid_amount = (float)($stmt->fetch(PDO::FETCH_ASSOC)['payment_total'] ?? 0);
    
    $balance = $total_amount - $paid_amount;
    
    if ($total_amount == 0) {
        $status = 'pending';
    } elseif ($balance <= 0 && $total_amount > 0) {
        $status = 'paid';
    } elseif ($paid_amount > 0 && $balance > 0) {
        $status = 'partial';
    } else {
        $status = 'pending';
    }
    
    $stmt = $db->prepare("
        UPDATE bills
        SET subtotal = ?, total_amount = ?, paid_amount = ?, balance = ?, status = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$subtotal, $total_amount, $paid_amount, $balance, $status, $bill_id]);
    
    return ['subtotal' => $subtotal, 'total' => $total_amount, 'paid' => $paid_amount, 'balance' => $balance, 'status' => $status];
}

$bill_data = updateBillTotal($db, $bill_id);

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
    <title>Prescribe Medication - Braick Dispensary</title>
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
            --shadow-md: 0 4px 16px rgba(0,0,0,0.3);
            --shadow-lg: 0 8px 32px rgba(0,0,0,0.4);
            --primary-bg: #1E3A5F;
            --success-bg: #064E3B;
            --danger-bg: #7F1D1D;
            --warning-bg: #78350F;
            --purple-bg: #4C1D95;
            --teal-bg: #134E4A;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: var(--bg-body);
            color: var(--text-primary);
            font-family: 'Inter', 'Segoe UI', sans-serif;
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 24px 28px;
            min-height: calc(100vh - 68px);
            background: var(--bg-body);
            transition: background 0.3s ease;
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
            padding: 20px 24px;
            background: linear-gradient(135deg, #0B5ED7 0%, #1A7FE8 100%);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            color: #ffffff !important;
        }
        .page-header * { color: #ffffff !important; }
        .page-title {
            font-size: 1.4rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin: 0;
        }
        .page-title i { color: rgba(255,255,255,0.8) !important; }
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
            font-size: 0.85rem;
            opacity: 0.9;
            margin-top: 4px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
        }
        .page-subtitle strong { color: #ffffff !important; font-weight: 700; }
        
        /* ================================================================ */
        /* BUTTONS - 3 IN A ROW - SMALL SIZE */
        /* ================================================================ */
        .btn-action-group {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.65rem;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            border: none;
            cursor: pointer;
            white-space: nowrap;
            min-width: 65px;
            min-height: 26px;
            height: 26px;
            width: 65px;
            box-sizing: border-box;
            line-height: 1;
            flex-shrink: 0;
        }
        
        .btn-action i { font-size: 0.6rem; flex-shrink: 0; }
        .btn-action span { flex-shrink: 0; font-size: 0.6rem; }
        
        .btn-view {
            background: linear-gradient(135deg, #0B5ED7, #0A4CA8);
            color: white;
            box-shadow: 0 2px 6px rgba(11, 94, 215, 0.2);
        }
        .btn-view:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(11, 94, 215, 0.35); color: white; }
        
        .btn-visits {
            background: linear-gradient(135deg, #0D9488, #0F766E);
            color: white;
            box-shadow: 0 2px 6px rgba(13, 148, 136, 0.2);
        }
        .btn-visits:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(13, 148, 136, 0.35); color: white; }
        
        .btn-back {
            background: linear-gradient(135deg, #7C3AED, #6D28D9);
            color: white;
            box-shadow: 0 2px 6px rgba(124, 58, 237, 0.2);
        }
        .btn-back:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(124, 58, 237, 0.35); color: white; }
        
        [data-theme="dark"] .btn-view { background: linear-gradient(135deg, #0B5ED7, #0A4CA8); color: white; }
        [data-theme="dark"] .btn-visits { background: linear-gradient(135deg, #0D9488, #0F766E); color: white; }
        [data-theme="dark"] .btn-back { background: linear-gradient(135deg, #7C3AED, #6D28D9); color: white; }
        
        /* ================================================================ */
        /* CARD */
        /* ================================================================ */
        .consultation-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 18px 22px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            margin-bottom: 18px;
            box-shadow: var(--shadow-md);
        }
        .consultation-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-lg);
        }
        .card-title {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text-primary);
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 10px;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .card-title i { color: var(--primary); }
        
        /* ================================================================ */
        /* PATIENT INFO */
        /* ================================================================ */
        .patient-info-block {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 12px 16px;
            background: var(--primary-bg);
            border-radius: var(--radius);
            margin-bottom: 14px;
        }
        .patient-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            font-weight: 700;
            color: #ffffff;
            flex-shrink: 0;
        }
        .patient-info-details h4 {
            font-size: 1rem;
            font-weight: 600;
            margin: 0;
            color: var(--text-primary);
        }
        .patient-info-details p {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin: 2px 0;
        }
        
        /* ================================================================ */
        /* MEDICATION TOGGLE DROPDOWN - LIKE CONSULTATION */
        /* ================================================================ */
        .toggle-dropdown {
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            background: var(--bg-card);
            transition: var(--transition);
            position: relative;
            margin-bottom: 12px;
        }
        .toggle-dropdown:hover { border-color: var(--primary-light); }
        
        .toggle-dropdown-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 16px;
            cursor: pointer;
            user-select: none;
            transition: var(--transition);
            background: var(--gray-50);
            border-radius: var(--radius);
        }
        .toggle-dropdown-header:hover { background: var(--primary-bg); }
        .toggle-dropdown-header .toggle-title {
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .toggle-dropdown-header .toggle-icon {
            color: var(--text-secondary);
            font-size: 0.8rem;
            transition: var(--transition);
        }
        .toggle-dropdown-header.active .toggle-icon { transform: rotate(180deg); }
        
        .toggle-dropdown-body {
            padding: 0 16px 16px 16px;
            display: none;
            background: var(--bg-card);
            border-radius: 0 0 var(--radius) var(--radius);
        }
        .toggle-dropdown-body.open { display: block; }
        .toggle-dropdown-body .search-input { margin-bottom: 10px; margin-top: 10px; }
        
        /* ================================================================ */
        /* MEDICATION GRID - 3 IN A ROW WITH CHECKBOXES */
        /* ================================================================ */
        .med-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            padding: 8px;
            background: var(--gray-100);
            border-radius: var(--radius);
            max-height: 400px;
            overflow-y: auto;
        }
        @media (max-width: 992px) {
            .med-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 576px) {
            .med-grid { grid-template-columns: 1fr; }
        }
        
        .med-item-select {
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
            position: relative;
        }
        .med-item-select:hover {
            background: var(--primary-bg);
            border-color: var(--primary-light);
            transform: translateY(-1px);
        }
        .med-item-select.selected {
            background: var(--primary-bg);
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(11,94,215,0.1);
        }
        .med-item-select .item-check {
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
        .med-item-select.selected .item-check {
            background: var(--primary);
            border-color: var(--primary);
        }
        .med-item-select .item-check i {
            font-size: 0.7rem;
            color: #ffffff;
            opacity: 0;
            transition: var(--transition);
        }
        .med-item-select.selected .item-check i { opacity: 1; }
        
        .med-item-select .med-info {
            display: flex;
            flex-direction: column;
            flex: 1;
            min-width: 0;
        }
        .med-item-select .med-name {
            font-weight: 500;
            font-size: 0.85rem;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .med-item-select .med-details {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            margin-top: 2px;
        }
        .med-item-select .med-price {
            font-size: 0.7rem;
            color: var(--success);
            font-weight: 600;
        }
        .med-item-select .med-stock {
            font-size: 0.65rem;
            color: var(--text-secondary);
            background: var(--gray-100);
            padding: 0 8px;
            border-radius: 10px;
        }
        .med-item-select .med-category {
            font-size: 0.6rem;
            color: var(--purple);
            background: var(--purple-bg);
            padding: 0 8px;
            border-radius: 10px;
        }
        .med-item-select .med-batch {
            font-size: 0.55rem;
            color: var(--text-secondary);
            font-family: monospace;
        }
        .med-item-select .med-expiry {
            font-size: 0.55rem;
            color: var(--warning);
        }
        
        /* ================================================================ */
        /* SELECT ALL CHECKBOX */
        /* ================================================================ */
        .select-all-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 14px;
            background: var(--gray-50);
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
            margin-bottom: 12px;
        }
        .select-all-wrapper input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--primary);
            cursor: pointer;
        }
        .select-all-wrapper label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-primary);
            cursor: pointer;
        }
        [data-theme="dark"] .select-all-wrapper {
            background: #1E293B;
            border-color: #334155;
        }
        
        /* ================================================================ */
        /* ALERT */
        /* ================================================================ */
        .alert {
            padding: 10px 16px;
            border-radius: var(--radius);
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.8rem;
            border: 1px solid transparent;
            animation: slideDown 0.3s ease;
        }
        .alert-success { background: var(--success-bg); color: var(--success); border-color: var(--success); }
        .alert-error { background: var(--danger-bg); color: var(--danger); border-color: var(--danger); }
        .alert-warning { background: var(--warning-bg); color: var(--warning); border-color: var(--warning); }
        .alert-info { background: var(--primary-bg); color: var(--primary); border-color: var(--primary); }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* ================================================================ */
        /* BUTTONS */
        /* ================================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.75rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
            min-height: 32px;
        }
        .btn-primary {
            background: var(--primary);
            color: #ffffff;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11,94,215,0.3);
        }
        .btn-success {
            background: var(--success);
            color: #ffffff;
        }
        .btn-success:hover {
            background: #047857;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5,150,105,0.3);
        }
        .btn-danger {
            background: var(--danger);
            color: #ffffff;
        }
        .btn-danger:hover {
            background: #B91C1C;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220,38,38,0.3);
        }
        .btn-outline {
            background: transparent;
            color: var(--text-primary);
            border: 2px solid var(--border-color);
        }
        .btn-outline:hover {
            background: var(--gray-100);
            border-color: var(--gray-400);
            transform: translateY(-2px);
        }
        .btn-sm { padding: 4px 10px; font-size: 0.65rem; min-height: 26px; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none !important; }
        
        /* ================================================================ */
        /* MEDICATION LIST */
        /* ================================================================ */
        .medication-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 12px;
            border-bottom: 1px solid var(--border-color);
            transition: all 0.3s ease;
            animation: fadeIn 0.3s ease;
        }
        .medication-item:last-child { border-bottom: none; }
        .medication-item:hover { background: var(--primary-bg); border-radius: var(--radius); }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .medication-item-info { flex: 1; }
        .med-name { font-weight: 600; font-size: 0.85rem; color: var(--text-primary); }
        .med-details { font-size: 0.7rem; color: var(--text-secondary); display: block; }
        .med-qty {
            font-size: 0.65rem;
            color: var(--text-secondary);
            background: var(--gray-200);
            padding: 1px 10px;
            border-radius: 12px;
            margin-left: 6px;
        }
        .med-instruction-tag {
            font-size: 0.6rem;
            color: var(--primary);
            background: var(--primary-bg);
            padding: 1px 8px;
            border-radius: 12px;
            margin-left: 4px;
            border: 1px solid var(--primary-light);
        }
        .med-price { font-size: 0.75rem; font-weight: 600; color: var(--success); margin-left: 8px; }
        .med-status-dispensed {
            font-size: 0.55rem;
            background: var(--success-bg);
            color: var(--success);
            padding: 1px 8px;
            border-radius: 12px;
            margin-left: 6px;
            border: 1px solid var(--success);
        }
        .med-status-pending {
            font-size: 0.55rem;
            background: var(--warning-bg);
            color: var(--warning);
            padding: 1px 8px;
            border-radius: 12px;
            margin-left: 6px;
            border: 1px solid var(--warning);
        }
        
        .btn-remove {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            border: none;
            background: var(--danger-bg);
            color: var(--danger);
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 0.6rem;
        }
        .btn-remove:hover { background: var(--danger); color: #ffffff; transform: scale(1.1); }
        
        .btn-dispense {
            padding: 2px 10px;
            border-radius: 12px;
            border: none;
            background: var(--success);
            color: white;
            font-size: 0.6rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-dispense:hover {
            background: #047857;
            transform: scale(1.05);
        }
        .btn-dispense:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }
        
        /* ================================================================ */
        /* EMPTY STATE */
        /* ================================================================ */
        .empty-state {
            text-align: center;
            padding: 16px;
            color: var(--text-secondary);
        }
        .empty-state i { font-size: 1.5rem; color: var(--border-color); display: block; margin-bottom: 6px; }
        
        /* ================================================================ */
        /* SECTION TOTAL */
        /* ================================================================ */
        .section-total {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 3px 14px;
            border-radius: 20px;
            background: linear-gradient(135deg, #0B5ED7, #1A7FE8);
            color: #ffffff !important;
            border: none;
        }
        .section-total * { color: #ffffff !important; }
        .section-total .label { opacity: 0.8; font-weight: 400; }
        .section-total.green { background: linear-gradient(135deg, #059669, #10B981); }
        
        /* ================================================================ */
        /* TOAST */
        /* ================================================================ */
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 12px 18px;
            border-radius: 12px;
            z-index: 9999;
            max-width: 360px;
            transform: translateY(120px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
            border: 1px solid rgba(255,255,255,0.15);
        }
        .toast-custom.show { transform: translateY(0); opacity: 1; }
        .toast-custom.success { background: #059669; }
        .toast-custom.error { background: #DC2626; }
        .toast-custom.warning { background: #D97706; }
        .toast-custom.info { background: #0B5ED7; }
        .toast-custom .toast-close {
            background: none;
            border: none;
            color: rgba(255,255,255,0.7);
            font-size: 1.2rem;
            cursor: pointer;
            padding: 0 4px;
        }
        .toast-custom .toast-close:hover { color: #ffffff; }
        
        /* ================================================================ */
        /* FOOTER */
        /* ================================================================ */
        .footer {
            padding: 12px 0;
            border-top: 2px solid var(--border-color);
            margin-top: 18px;
            text-align: center;
            font-size: 0.65rem;
            color: var(--text-secondary);
        }
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        /* ================================================================ */
        /* RESPONSIVE */
        /* ================================================================ */
        @media (max-width: 1024px) {
            .main-content { padding: 16px; }
            .med-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 12px; }
            .page-header { flex-direction: column; }
            .med-grid { grid-template-columns: 1fr; }
            .btn-action { min-width: 55px; width: 55px; padding: 3px 6px; font-size: 0.55rem; min-height: 22px; height: 22px; }
            .btn-action i { font-size: 0.5rem; }
            .btn-action span { font-size: 0.5rem; }
            .consultation-card { padding: 12px 14px; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 8px; }
            .page-title { font-size: 1rem; }
            .consultation-card { padding: 10px 12px; }
            .btn-action { min-width: 45px; width: 45px; padding: 2px 4px; font-size: 0.5rem; min-height: 20px; height: 20px; }
            .btn-action i { font-size: 0.45rem; }
            .btn-action span { font-size: 0.45rem; }
        }
    </style>
</head>
<body>

<main class="main-content">

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-prescription"></i> Prescribe Medication
                <span class="page-badge"><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></span>
            </h1>
            <p class="page-subtitle">
                Patient: <strong><?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></strong>
                (<?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?>)
                <span style="color:rgba(255,255,255,0.4);">|</span>
                Branch: <?= htmlspecialchars($patient['branch_name'] ?? $doctor_branch_id) ?>
            </p>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
            <!-- ================================================================ -->
            <!-- ACTION BUTTONS - 3 IN A ROW (View, Visits, Back) -->
            <!-- ================================================================ -->
            <div class="btn-action-group">
                <a href="patient_details.php?id=<?= $patient_id ?>" class="btn-action btn-view" title="View Patient Details">
                    <i class="fas fa-eye"></i> <span>View</span>
                </a>
                <a href="patient_visits.php?id=<?= $patient_id ?>" class="btn-action btn-visits" title="View All Visits">
                    <i class="fas fa-clinic-medical"></i> <span>Visits</span>
                </a>
                <a href="my_patients.php" class="btn-action btn-back" title="Back to Patients">
                    <i class="fas fa-arrow-left"></i> <span>Back</span>
                </a>
            </div>
        </div>
    </div>

    <!-- FLASH MESSAGE -->
    <?php if ($flash_message): ?>
        <div class="alert alert-<?= $flash_type ?>">
            <i class="fas <?= $flash_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= $flash_message ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- PATIENT INFO -->
    <!-- ================================================================ -->
    <div class="consultation-card">
        <div class="patient-info-block">
            <div class="patient-avatar" style="background:<?= $patient['gender'] === 'Female' ? '#DC2626' : '#0B5ED7' ?>;">
                <?= strtoupper(substr($patient['full_name'] ?? 'U', 0, 1)) ?>
            </div>
            <div class="patient-info-details">
                <h4><?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?></h4>
                <p>
                    <i class="fas fa-id-card"></i> <?= htmlspecialchars($patient['patient_id'] ?? 'N/A') ?>
                    <span style="margin:0 6px;">|</span>
                    <i class="fas fa-phone"></i> <?= htmlspecialchars($patient['phone'] ?? 'N/A') ?>
                    <span style="margin:0 6px;">|</span>
                    <i class="fas fa-venus-mars"></i> <?= $patient['gender'] ?? 'N/A' ?>
                    <span style="margin:0 6px;">|</span>
                    <i class="fas fa-tint"></i> Blood: <?= htmlspecialchars($patient['blood_group'] ?? 'N/A') ?>
                </p>
            </div>
        </div>
        
        <!-- Visit Info -->
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;">
            <div><span style="font-size:0.6rem;color:var(--text-secondary);">Visit Number</span><br><strong><?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?></strong></div>
            <div><span style="font-size:0.6rem;color:var(--text-secondary);">Status</span><br><span class="badge badge-info"><?= ucfirst($visit['status'] ?? 'Assigned') ?></span></div>
            <div><span style="font-size:0.6rem;color:var(--text-secondary);">Medications</span><br><strong><?= count($prescriptions) ?></strong></div>
            <div><span style="font-size:0.6rem;color:var(--text-secondary);">Total</span><br><strong style="color:var(--success);">TSh <?= number_format($medications_total, 0) ?></strong></div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- SELECT MEDICATIONS - TOGGLE DROPDOWN WITH SEARCH -->
    <!-- ================================================================ -->
    <div class="consultation-card">
        <h3 class="card-title">
            <i class="fas fa-pills"></i> Select Medications
            <span style="font-size:0.7rem;font-weight:400;color:var(--text-secondary);">(Click to expand)</span>
            <span class="section-total green" id="selectedCountBadge">Selected: 0</span>
        </h3>
        
        <form method="POST" id="prescribeForm">
            <input type="hidden" name="action" value="add_medications_batch">
            
            <!-- Select All -->
            <div class="select-all-wrapper">
                <input type="checkbox" id="selectAllMedications" onchange="toggleAllMedications()">
                <label for="selectAllMedications"><i class="fas fa-check-double"></i> Select All Medications</label>
                <span style="font-size:0.7rem;color:var(--text-secondary);margin-left:auto;">
                    <span id="selectedCount">0</span> of <span id="totalCount"><?= count($medications) ?></span> selected
                </span>
            </div>
            
            <!-- TOGGLE DROPDOWN WITH SEARCH - LIKE CONSULTATION -->
            <div class="toggle-dropdown">
                <div class="toggle-dropdown-header" onclick="toggleMedicationDropdown()">
                    <span class="toggle-title">
                        <i class="fas fa-pills"></i> Click to Select Medications
                        <span style="font-size:0.7rem;color:var(--text-secondary);font-weight:400;">(<?= count($medications) ?> available)</span>
                    </span>
                    <span class="toggle-icon"><i class="fas fa-chevron-down"></i></span>
                </div>
                <div class="toggle-dropdown-body" id="medicationDropdownBody">
                    <div class="search-input">
                        <input type="text" class="form-control" id="medicationSearch" placeholder="🔍 Search medications..." oninput="filterMedicationsInside()">
                    </div>
                    <div class="med-grid" id="medicationsGrid">
                        <?php if (count($medications) > 0): ?>
                            <?php foreach ($medications as $med): 
                                $expiry_status = '';
                                $expiry_label = '';
                                if (!empty($med['expiry_date'])) {
                                    $expiry_timestamp = strtotime($med['expiry_date']);
                                    $days_remaining = floor(($expiry_timestamp - time()) / 86400);
                                    if ($days_remaining < 0) {
                                        $expiry_status = 'expired';
                                        $expiry_label = 'EXPIRED';
                                    } elseif ($days_remaining <= 30) {
                                        $expiry_status = 'expiring';
                                        $expiry_label = 'Expires: ' . date('d/m/Y', $expiry_timestamp);
                                    } else {
                                        $expiry_status = 'valid';
                                        $expiry_label = 'Expires: ' . date('d/m/Y', $expiry_timestamp);
                                    }
                                }
                            ?>
                                <div class="med-item-select" 
                                     data-med-id="<?= $med['id'] ?>"
                                     data-med-name="<?= strtolower(htmlspecialchars($med['medication_name'])) ?>"
                                     data-category="<?= strtolower(htmlspecialchars($med['category'] ?? '')) ?>"
                                     onclick="toggleMedicationCheckbox(this)">
                                    <span class="item-check"><i class="fas fa-check"></i></span>
                                    <div class="med-info">
                                        <span class="med-name"><?= htmlspecialchars($med['medication_name']) ?></span>
                                        <div class="med-details">
                                            <span class="med-price">TSh <?= number_format($med['selling_price'] ?? 0, 0) ?></span>
                                            <span class="med-stock">Stock: <?= $med['quantity'] ?></span>
                                            <?php if (!empty($med['category'])): ?>
                                                <span class="med-category"><?= htmlspecialchars($med['category']) ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($med['batch_number'])): ?>
                                                <span class="med-batch">Batch: <?= htmlspecialchars($med['batch_number']) ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($expiry_label)): ?>
                                                <span class="med-expiry <?= $expiry_status ?>"><?= $expiry_label ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state" style="grid-column:span 3;">
                                <i class="fas fa-pills"></i>
                                <p>No medications available in inventory</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="mt-2" style="margin-top:10px;">
                        <span style="font-size:0.75rem;color:var(--text-secondary);" id="medSelectedInfo">Selected: None</span>
                    </div>
                </div>
            </div>
            
            <!-- Hidden fields for selected medications data -->
            <div id="selectedMedicationsData"></div>
            
            <?php if (count($medications) === 0): ?>
                <div class="empty-state">
                    <i class="fas fa-pills"></i>
                    <p>No medications available in inventory</p>
                    <p style="font-size:0.75rem;">Please add medications in Pharmacy module</p>
                </div>
            <?php endif; ?>
            
            <div class="form-actions" style="display:flex;flex-wrap:wrap;gap:10px;padding-top:14px;margin-top:14px;border-top:2px solid var(--border-color);">
                <button type="submit" class="btn btn-primary" id="prescribeBtn" disabled>
                    <i class="fas fa-prescription"></i> Select Medications First
                </button>
                <button type="button" class="btn btn-outline" onclick="clearAllSelections()">
                    <i class="fas fa-times"></i> Clear All
                </button>
                <a href="consultation.php?visit_id=<?= $visit_id ?>" class="btn btn-outline">
                    <i class="fas fa-stethoscope"></i> Go to Consultation
                </a>
            </div>
        </form>
    </div>

    <!-- ================================================================ -->
    <!-- PRESCRIBED MEDICATIONS LIST -->
    <!-- ================================================================ -->
    <div class="consultation-card">
        <h3 class="card-title">
            <i class="fas fa-list"></i> Prescribed Medications
            <span class="section-total green">
                <span class="label">💰 Total:</span>
                <span class="amount">TSh <?= number_format($medications_total, 0) ?></span>
            </span>
            <span style="font-size:0.7rem;font-weight:400;color:var(--text-secondary);">
                (<?= count($prescriptions) ?> items)
            </span>
        </h3>
        
        <div id="medicationsList">
            <?php if (count($prescriptions) > 0): ?>
                <?php foreach ($prescriptions as $med): ?>
                    <div class="medication-item" id="med-item-<?= $med['id'] ?>">
                        <div class="medication-item-info">
                            <span class="med-name"><?= htmlspecialchars($med['medication_name'] ?? 'Unknown') ?></span>
                            <span class="med-details">
                                <?= htmlspecialchars($med['dosage'] ?? '') ?> • 
                                <?= htmlspecialchars($med['frequency'] ?? '') ?> • 
                                <?= htmlspecialchars($med['duration'] ?? '') ?> days • 
                                Route: <?= htmlspecialchars($med['route'] ?? '') ?>
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
                        <div style="display:flex;gap:4px;align-items:center;">
                            <?php if (($med['status'] ?? '') !== 'dispensed'): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Dispense this medication? Stock will be reduced.');">
                                    <input type="hidden" name="action" value="dispense_medication">
                                    <input type="hidden" name="prescription_id" value="<?= $med['id'] ?>">
                                    <button type="submit" class="btn-dispense" title="Dispense - Reduces stock">
                                        <i class="fas fa-check"></i> Dispense
                                    </button>
                                </form>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this medication? Stock will be returned.');">
                                    <input type="hidden" name="action" value="remove_medication">
                                    <input type="hidden" name="prescription_id" value="<?= $med['id'] ?>">
                                    <button type="submit" class="btn-remove" title="Remove medication">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state" id="emptyMedications">
                    <i class="fas fa-prescription"></i>
                    <p>No medications prescribed yet</p>
                    <p style="font-size:0.75rem;">Select medications above and click "Prescribe Selected"</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- BILL SUMMARY -->
    <!-- ================================================================ -->
    <div class="consultation-card">
        <h3 class="card-title">
            <i class="fas fa-receipt"></i> Bill Summary
        </h3>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;">
            <div style="background:var(--primary-bg);padding:10px 14px;border-radius:10px;text-align:center;">
                <span style="font-size:0.55rem;color:var(--text-secondary);text-transform:uppercase;">Subtotal</span>
                <p style="font-size:1.1rem;font-weight:700;color:var(--primary);">TSh <?= number_format($bill_data['subtotal'], 0) ?></p>
            </div>
            <div style="background:var(--success-bg);padding:10px 14px;border-radius:10px;text-align:center;">
                <span style="font-size:0.55rem;color:var(--text-secondary);text-transform:uppercase;">Paid</span>
                <p style="font-size:1.1rem;font-weight:700;color:var(--success);">TSh <?= number_format($bill_data['paid'], 0) ?></p>
            </div>
            <div style="background:var(--warning-bg);padding:10px 14px;border-radius:10px;text-align:center;">
                <span style="font-size:0.55rem;color:var(--text-secondary);text-transform:uppercase;">Balance</span>
                <p style="font-size:1.1rem;font-weight:700;color:var(--warning);">TSh <?= number_format($bill_data['balance'], 0) ?></p>
            </div>
            <div style="background:var(--purple-bg);padding:10px 14px;border-radius:10px;text-align:center;">
                <span style="font-size:0.55rem;color:var(--text-secondary);text-transform:uppercase;">Status</span>
                <p style="font-size:0.9rem;font-weight:700;color:var(--purple);">
                    <?= ucfirst($bill_data['status']) ?>
                </p>
            </div>
        </div>
        <div style="margin-top:10px;font-size:0.7rem;color:var(--text-secondary);text-align:center;">
            <i class="fas fa-info-circle"></i> Bill sent to Cashier for payment
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span style="color:var(--gray-300);margin:0 6px;">|</span>
            Prescribe Medication
            <span style="color:var(--gray-300);margin:0 6px;">|</span>
            <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>
            <span style="color:var(--gray-300);margin:0 6px;">|</span>
            &copy; <?= date('Y') ?> All rights reserved
        </p>
    </footer>

</main>

<!-- ================================================================ -->
<!-- TOAST -->
<!-- ================================================================ -->
<div id="toast" class="toast-custom" style="display:none;">
    <div style="flex:1;">
        <p style="font-weight:600;font-size:0.8rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.7rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
    <button class="toast-close" onclick="closeToast()">&times;</button>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    // ================================================================
    // DARK MODE
    // ================================================================
    if (localStorage.getItem('darkMode') === 'true') {
        document.documentElement.setAttribute('data-theme', 'dark');
    }
    
    document.addEventListener('darkModeChanged', function(e) {
        var isDark = e.detail && e.detail.isDark;
        var html = document.documentElement;
        if (isDark) {
            html.setAttribute('data-theme', 'dark');
        } else {
            html.removeAttribute('data-theme');
        }
    });

    // ================================================================
    // TOGGLE DROPDOWN - LIKE CONSULTATION
    // ================================================================
    function toggleMedicationDropdown() {
        var body = document.getElementById('medicationDropdownBody');
        var header = body.previousElementSibling;
        body.classList.toggle('open');
        header.classList.toggle('active');
        if (body.classList.contains('open')) {
            document.getElementById('medicationSearch').focus();
        }
    }

    // ================================================================
    // FILTER MEDICATIONS INSIDE
    // ================================================================
    function filterMedicationsInside() {
        var input = document.getElementById('medicationSearch');
        var filter = input.value.toLowerCase().trim();
        var items = document.querySelectorAll('#medicationsGrid .med-item-select');
        
        items.forEach(function(item) {
            var name = item.getAttribute('data-med-name') || '';
            var category = item.getAttribute('data-category') || '';
            var searchText = name + ' ' + category;
            if (!filter || searchText.includes(filter)) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    }

    // ================================================================
    // MEDICATION SELECTION - WITH CHECKBOX
    // ================================================================
    var selectedMedications = [];
    var selectedCount = 0;
    
    function toggleMedicationCheckbox(element) {
        element.classList.toggle('selected');
        
        var medId = element.getAttribute('data-med-id');
        var idx = selectedMedications.indexOf(medId);
        
        if (idx > -1) {
            selectedMedications.splice(idx, 1);
        } else {
            selectedMedications.push(medId);
        }
        
        // Update checkbox in hidden form
        updateHiddenFields();
        updateSelectionUI();
    }
    
    function toggleAllMedications() {
        var selectAll = document.getElementById('selectAllMedications');
        var items = document.querySelectorAll('#medicationsGrid .med-item-select');
        var isChecked = selectAll.checked;
        
        items.forEach(function(item) {
            var medId = item.getAttribute('data-med-id');
            if (isChecked) {
                if (!selectedMedications.includes(medId)) {
                    selectedMedications.push(medId);
                }
                item.classList.add('selected');
            } else {
                selectedMedications = selectedMedications.filter(id => id !== medId);
                item.classList.remove('selected');
            }
        });
        
        updateHiddenFields();
        updateSelectionUI();
    }
    
    function updateHiddenFields() {
        // Remove old hidden inputs
        var container = document.getElementById('selectedMedicationsData');
        container.innerHTML = '';
        
        // Add hidden inputs for each selected medication
        selectedMedications.forEach(function(medId) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'medication_ids[]';
            input.value = medId;
            container.appendChild(input);
        });
    }
    
    function updateSelectionUI() {
        var countEl = document.getElementById('selectedCount');
        var badgeEl = document.getElementById('selectedCountBadge');
        var btn = document.getElementById('prescribeBtn');
        var infoEl = document.getElementById('medSelectedInfo');
        
        var count = selectedMedications.length;
        
        if (countEl) countEl.textContent = count;
        if (badgeEl) badgeEl.textContent = 'Selected: ' + count;
        
        if (count > 0) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-prescription"></i> Prescribe ' + count + ' Medication(s)';
            if (infoEl) infoEl.textContent = 'Selected: ' + count + ' medication(s)';
        } else {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-prescription"></i> Select Medications First';
            if (infoEl) infoEl.textContent = 'Selected: None';
        }
    }
    
    function clearAllSelections() {
        var items = document.querySelectorAll('#medicationsGrid .med-item-select');
        items.forEach(function(item) {
            item.classList.remove('selected');
        });
        
        selectedMedications = [];
        document.getElementById('selectAllMedications').checked = false;
        updateHiddenFields();
        updateSelectionUI();
    }
    
    // Initialize UI
    document.addEventListener('DOMContentLoaded', function() {
        updateSelectionUI();
    });

    // ================================================================
    // TOAST
    // ================================================================
    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        
        toast.className = 'toast-custom ' + (type || 'info');
        toastTitle.textContent = title || 'Notification';
        toastMessage.textContent = message || '';
        toast.style.display = 'flex';
        
        setTimeout(function() { toast.classList.add('show'); }, 50);
        
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() { toast.style.display = 'none'; }, 400);
        }, 5000);
    }
    
    function closeToast() {
        var toast = document.getElementById('toast');
        toast.classList.remove('show');
        setTimeout(function() { toast.style.display = 'none'; }, 400);
    }

    // ================================================================
    // CONSOLE LOG
    // ================================================================
    console.log('%c💊 Braick Dispensary - Prescribe Medication', 'font-size:18px;font-weight:bold;color:#0B5ED7;');
    console.log('%c👤 Patient: <?= htmlspecialchars($patient['full_name'] ?? 'N/A') ?>', 'font-size:13px;color:#059669;');
    console.log('%c📋 Visit: <?= htmlspecialchars($visit['visit_number'] ?? 'N/A') ?>', 'font-size:13px;color:#0B5ED7;');
    console.log('%c💊 Medications Available: <?= count($medications) ?>', 'font-size:13px;color:#64748B;');
    console.log('%c💊 Prescribed: <?= count($prescriptions) ?>', 'font-size:13px;color:#64748B;');
    console.log('%c💰 Total: TSh <?= number_format($medications_total, 0) ?>', 'font-size:13px;color:#059669;');
    console.log('%c🔍 Search filter for medications', 'font-size:13px;color:#7C3AED;');
    console.log('%c✅ Toggle dropdown - click to expand', 'font-size:13px;color:#7C3AED;');
    console.log('%c✅ 3 medications in a row with checkboxes', 'font-size:13px;color:#7C3AED;');
    console.log('%c✅ Dispense - reduces stock automatically', 'font-size:13px;color:#059669;');
    console.log('%c✅ Bills sent to Cashier', 'font-size:13px;color:#0B5ED7;');
    console.log('%c✅ Stock movements recorded', 'font-size:13px;color:#059669;');
</script>

</body>
</html>