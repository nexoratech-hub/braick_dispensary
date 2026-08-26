<?php
// ================================================================
// FILE: frontend/pages/doctor/save_prescription.php
// DOCTOR - SAVE PRESCRIPTION
// USING NEW DATABASE: dispensary_db
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
// GET MEDICATION DETAILS FROM medications_inventory
// ================================================================
$medication_name = '';
$medication_unit = '';
$medication_category = '';
$selling_price = 0;
$stock_quantity = 0;
$batch_number = '';

$stmt = $db->prepare("
    SELECT 
        medication_name, 
        unit, 
        category, 
        selling_price, 
        quantity as stock,
        batch_number,
        expiry_date
    FROM medications_inventory 
    WHERE id = ? AND status = 'active' AND branch_id = ?
");
$stmt->execute([$medication_id, $branch_id]);
$med = $stmt->fetch(PDO::FETCH_ASSOC);

if ($med) {
    $medication_name = $med['medication_name'];
    $medication_unit = $med['unit'] ?? '';
    $medication_category = $med['category'] ?? '';
    $selling_price = $med['selling_price'] ?? 0;
    $stock_quantity = $med['stock'] ?? 0;
    $batch_number = $med['batch_number'] ?? '';
    
    // Check if enough stock
    if ($stock_quantity < $quantity) {
        $error_msg = 'Not enough stock! Available: ' . $stock_quantity . ' | Requested: ' . $quantity;
        header('Location: prescribe.php?patient_id=' . $patient_id . '&error=' . urlencode($error_msg));
        exit;
    }
    
    // Check if expired
    if (!empty($med['expiry_date'])) {
        $expiry = strtotime($med['expiry_date']);
        if ($expiry < time()) {
            $error_msg = 'This medication has EXPIRED! Batch: ' . $batch_number;
            header('Location: prescribe.php?patient_id=' . $patient_id . '&error=' . urlencode($error_msg));
            exit;
        }
    }
} else {
    $error_msg = 'Medication not found or inactive';
    header('Location: prescribe.php?patient_id=' . $patient_id . '&error=' . urlencode($error_msg));
    exit;
}

// Build full medication name with batch
$medication_full = $medication_name;
if (!empty($batch_number)) {
    $medication_full .= ' (Batch: ' . $batch_number . ')';
}
if (!empty($medication_unit)) {
    $medication_full .= ' - ' . $medication_unit;
}

// ================================================================
// GENERATE PRESCRIPTION NUMBER
// ================================================================
$year = date('Y');
$month = date('m');
$prescription_number = 'PRES-' . date('Ymd') . '-' . str_pad($patient_id, 4, '0', STR_PAD_LEFT) . '-' . rand(100, 999);

// Ensure unique prescription number
$stmt = $db->prepare("SELECT COUNT(*) FROM prescriptions WHERE prescription_number = ?");
$stmt->execute([$prescription_number]);
while ($stmt->fetchColumn() > 0) {
    $prescription_number = 'PRES-' . date('Ymd') . '-' . str_pad($patient_id, 4, '0', STR_PAD_LEFT) . '-' . rand(100, 999);
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
// GET OR CREATE BILL
// ================================================================
$bill_id = null;
$stmt = $db->prepare("SELECT id FROM bills WHERE visit_id = ? AND status IN ('pending', 'partial')");
$stmt->execute([$visit_id]);
$bill = $stmt->fetch(PDO::FETCH_ASSOC);

if ($bill) {
    $bill_id = $bill['id'];
} else {
    $bill_number = 'BILL-' . date('Ymd') . '-' . str_pad($patient_id, 4, '0', STR_PAD_LEFT) . '-' . rand(100, 999);
    $stmt = $db->prepare("
        INSERT INTO bills (
            bill_number, patient_id, visit_id, branch_id, created_by,
            subtotal, discount_percent, discount_amount, total_amount, 
            paid_amount, balance, status, created_at
        ) VALUES (?, ?, ?, ?, ?, 0, 0, 0, 0, 0, 0, 'pending', NOW())
    ");
    $stmt->execute([
        $bill_number, $patient_id, $visit_id, $branch_id, $doctor_id
    ]);
    $bill_id = $db->lastInsertId();
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
            branch_id,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
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
    // CALCULATE PRICES
    // ================================================================
    $unit_price = $selling_price;
    $total_price = $unit_price * $quantity;
    
    // ================================================================
    // INSERT PRESCRIPTION ITEMS
    // ================================================================
    $stmt = $db->prepare("
        INSERT INTO prescription_items (
            prescription_id,
            patient_id,
            inventory_id,
            medication_name,
            dosage,
            frequency,
            quantity,
            duration,
            route,
            instructions,
            unit_price,
            total_price,
            branch_id,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $prescription_id,
        $patient_id,
        $medication_id,
        $medication_full,
        $dosage,
        $frequency,
        $quantity,
        $duration,
        $route,
        $instructions,
        $unit_price,
        $total_price,
        $branch_id
    ]);
    
    // ================================================================
    // UPDATE STOCK - DEDUCT QUANTITY
    // ================================================================
    $new_stock = $stock_quantity - $quantity;
    $stmt = $db->prepare("UPDATE medications_inventory SET quantity = ? WHERE id = ?");
    $stmt->execute([$new_stock, $medication_id]);
    
    // ================================================================
    // LOG STOCK MOVEMENT
    // ================================================================
    $stmt = $db->prepare("
        INSERT INTO stock_movements (
            inventory_id,
            patient_id,
            movement_type,
            quantity,
            previous_stock,
            new_stock,
            reference_type,
            reference_id,
            performed_by,
            branch_id,
            notes,
            created_at
        ) VALUES (?, ?, 'out', ?, ?, ?, 'prescription', ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $medication_id,
        $patient_id,
        $quantity,
        $stock_quantity,
        $new_stock,
        $prescription_id,
        $doctor_id,
        $branch_id,
        'Prescription: ' . $medication_name . ' | Batch: ' . ($batch_number ?? 'N/A')
    ]);
    
    // ================================================================
    // ADD MEDICATION TO BILL
    // ================================================================
    if ($bill_id > 0 && $total_price > 0) {
        // Update bill total
        $stmt = $db->prepare("
            UPDATE bills 
            SET subtotal = subtotal + ?,
                total_amount = total_amount + ?,
                balance = balance + ?
            WHERE id = ?
        ");
        $stmt->execute([$total_price, $total_price, $total_price, $bill_id]);
        
        // Add to bill_items
        $stmt = $db->prepare("
            INSERT INTO bill_items (
                bill_id,
                patient_id,
                branch_id,
                item_type,
                item_name,
                quantity,
                unit_price,
                total_price,
                status,
                reference_id,
                reference_type,
                created_at
            ) VALUES (?, ?, ?, 'medication', ?, ?, ?, ?, 'pending', ?, 'prescription', NOW())
        ");
        $stmt->execute([
            $bill_id,
            $patient_id,
            $branch_id,
            $medication_full,
            $quantity,
            $unit_price,
            $total_price,
            $prescription_id
        ]);
    }
    
    // ================================================================
    // UPDATE VISIT STATUS TO 'prescribed'
    // ================================================================
    $stmt = $db->prepare("
        UPDATE visits 
        SET status = 'prescribed', 
            pharmacy_fees_total = pharmacy_fees_total + ?,
            visit_total = visit_total + ?,
            updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$total_price, $total_price, $visit_id]);
    
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
            " | Medication: $medication_name | Qty: $quantity | Amount: TSh " . number_format($total_price, 0)
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