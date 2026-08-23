<?php
// ================================================================
// FILE: frontend/pages/pharmacy/dispense.php
// PHARMACY - DISPENSE PRESCRIPTION
// UPDATED: Matches new database schema (bills, bill_items, stock_movements)
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// SESSION START
// ================================================================
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
// CHECK PHARMACY ACCESS
// ================================================================
$allowed_roles = ['pharmacy', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'reception': header('Location: ../reception/dashboard.php'); break;
        case 'doctor': header('Location: ../doctor/dashboard.php'); break;
        case 'laboratory': header('Location: ../laboratory/dashboard.php'); break;
        case 'cashier': header('Location: ../cashier/dashboard.php'); break;
        default: header('Location: ../login.php'); break;
    }
    exit;
}

// ================================================================
// USER DATA
// ================================================================
$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Pharmacy Staff';
$user_role = $_SESSION['role'] ?? 'pharmacy';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? 'pharmacy';

// ================================================================
// DATABASE CONNECTION
// ================================================================
require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$message = '';
$message_type = '';
$currency = 'TSh';

// ================================================================
// GET PRESCRIPTION ID
// ================================================================
$prescription_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($prescription_id <= 0) {
    header('Location: pending_prescriptions.php?error=invalid_id');
    exit;
}

// ================================================================
// GET PRESCRIPTION DETAILS
// ================================================================
$stmt = $db->prepare("
    SELECT 
        p.*,
        pat.id as patient_id,
        pat.patient_id as patient_code,
        pat.full_name as patient_name,
        pat.phone,
        pat.email,
        pat.date_of_birth,
        pat.gender,
        pat.address,
        pat.blood_group,
        pat.allergies,
        pat.emergency_contact,
        u.full_name as doctor_name,
        u.specialty,
        v.visit_number,
        v.visit_type,
        v.diagnosis,
        v.symptoms,
        v.notes as visit_notes,
        ph.full_name as pharmacy_name
    FROM prescriptions p
    JOIN patients pat ON p.patient_id = pat.id
    LEFT JOIN users u ON p.doctor_id = u.id
    LEFT JOIN visits v ON p.visit_id = v.id
    LEFT JOIN users ph ON p.pharmacy_id = ph.id
    WHERE p.id = ? AND p.branch_id = ?
");
$stmt->execute([$prescription_id, $user_branch_id]);
$prescription = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$prescription) {
    header('Location: pending_prescriptions.php?error=not_found');
    exit;
}

// ================================================================
// GET PRESCRIPTION ITEMS
// ================================================================
$stmt = $db->prepare("
    SELECT * FROM prescription_items 
    WHERE prescription_id = ? 
    ORDER BY id ASC
");
$stmt->execute([$prescription_id]);
$prescription_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================================
// GET BILL FOR THIS PRESCRIPTION
// ================================================================
$bill = null;
$bill_items = [];
$bill_total = 0;
$bill_discount = 0;
$bill_balance = 0;
$bill_status = 'pending';

$stmt = $db->prepare("
    SELECT b.* 
    FROM bills b
    WHERE b.visit_id = ? 
    ORDER BY b.id DESC 
    LIMIT 1
");
$stmt->execute([$prescription['visit_id']]);
$bill = $stmt->fetch(PDO::FCTCH_ASSOC);

if ($bill) {
    $bill_id = $bill['id'];
    $bill_number = $bill['bill_number'];
    $bill_total = (float)$bill['total_amount'];
    $bill_discount = (float)$bill['discount_amount'];
    $bill_balance = (float)$bill['balance'];
    $bill_status = $bill['status'];
    
    // Get bill items for this prescription
    $stmt = $db->prepare("
        SELECT * FROM bill_items 
        WHERE bill_id = ? AND reference_type = 'prescription' AND reference_id = ?
        ORDER BY id ASC
    ");
    $stmt->execute([$bill_id, $prescription_id]);
    $bill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ================================================================
// CHECK STATUSES
// ================================================================
$is_dispensed = ($prescription['status'] === 'dispensed');
$is_bill_paid = ($bill_status === 'paid' || $bill_status === 'partial');
$has_bill = ($bill !== null);

// ================================================================
// CALCULATE PRESCRIPTION TOTAL
// ================================================================
$prescription_total = 0;
foreach ($prescription_items as &$item) {
    // Get price from inventory if not set
    if ($item['unit_price'] == 0) {
        $stmt = $db->prepare("
            SELECT selling_price FROM medications_inventory 
            WHERE medication_name = ? AND branch_id = ? AND status = 'active'
            ORDER BY id LIMIT 1
        ");
        $stmt->execute([$item['medication_name'], $user_branch_id]);
        $price_result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($price_result) {
            $item['unit_price'] = (float)$price_result['selling_price'];
        }
    }
    $item['total_price'] = $item['unit_price'] * $item['quantity'];
    $prescription_total += $item['total_price'];
}
unset($item);

// ================================================================
// GET STOCK INFORMATION FOR EACH MEDICATION
// ================================================================
$stock_info = [];
foreach ($prescription_items as $item) {
    $stmt = $db->prepare("
        SELECT 
            id, 
            medication_name,
            quantity,
            batch_number,
            expiry_date,
            reorder_level,
            selling_price,
            unit_cost
        FROM medications_inventory 
        WHERE medication_name = ? AND branch_id = ? AND status = 'active'
        ORDER BY expiry_date ASC
    ");
    $stmt->execute([$item['medication_name'], $user_branch_id]);
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_available = 0;
    foreach ($batches as $batch) {
        $total_available += $batch['quantity'];
    }
    
    $stock_info[$item['medication_name']] = [
        'batches' => $batches,
        'total_available' => $total_available,
        'required' => $item['quantity'],
        'sufficient' => ($total_available >= $item['quantity'])
    ];
}

// ================================================================
// HANDLE FORM SUBMISSIONS
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // ================================================================
    // 1. CONFIRM/CREATE BILL
    // ================================================================
    if ($action === 'create_bill') {
        $discount_amount = isset($_POST['discount_amount']) ? floatval(str_replace(',', '', $_POST['discount_amount'])) : 0;
        $notes = trim($_POST['notes'] ?? '');
        
        if ($discount_amount < 0) $discount_amount = 0;
        if ($discount_amount > $prescription_total) $discount_amount = $prescription_total;
        
        $final_amount = $prescription_total - $discount_amount;
        if ($final_amount < 0) $final_amount = 0;
        
        try {
            $db->beginTransaction();
            
            $bill_number = 'BILL-' . date('Ymd') . '-' . str_pad($prescription['patient_id'], 4, '0', STR_PAD_LEFT) . '-' . rand(100, 999);
            
            // Create bill
            $stmt = $db->prepare("
                INSERT INTO bills (
                    bill_number,
                    patient_id,
                    visit_id,
                    branch_id,
                    created_by,
                    subtotal,
                    discount_amount,
                    discount_percent,
                    total_amount,
                    paid_amount,
                    balance,
                    status,
                    payment_method,
                    notes,
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $stmt->execute([
                $bill_number,
                $prescription['patient_id'],
                $prescription['visit_id'],
                $user_branch_id,
                $user_id,
                $prescription_total,
                $discount_amount,
                0, // discount_percent - using flat amount
                $final_amount,
                0, // paid_amount
                $final_amount, // balance
                'pending',
                'cash', // default
                $notes,
            ]);
            
            $bill_id = $db->lastInsertId();
            
            // Create bill items for each prescription item
            foreach ($prescription_items as $item) {
                $item_total = $item['unit_price'] * $item['quantity'];
                $final_item_price = $item_total; // Discount applied at bill level
                
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
                        discount_amount,
                        tax_amount,
                        final_price,
                        reference_id,
                        reference_type,
                        status,
                        created_at,
                        updated_at
                    ) VALUES (?, ?, ?, 'medication', ?, ?, ?, ?, ?, ?, ?, ?, 'prescription', 'pending', NOW(), NOW())
                ");
                
                $stmt->execute([
                    $bill_id,
                    $prescription['patient_id'],
                    $user_branch_id,
                    $item['medication_name'],
                    $item['quantity'],
                    $item['unit_price'],
                    $item_total,
                    0, // discount_amount per item
                    0, // tax_amount
                    $final_item_price,
                    $prescription_id,
                ]);
            }
            
            // Update prescription status to 'confirmed' - awaiting payment
            $stmt = $db->prepare("
                UPDATE prescriptions 
                SET status = 'confirmed',
                    pharmacy_id = ?,
                    updated_at = NOW()
                WHERE id = ? AND branch_id = ?
            ");
            $stmt->execute([$user_id, $prescription_id, $user_branch_id]);
            
            // Log activity
            $stmt = $db->prepare("
                INSERT INTO activity_logs (user_id, branch_id, action, details, created_at)
                VALUES (?, ?, 'prescription_bill_created', ?, NOW())
            ");
            $stmt->execute([
                $user_id,
                $user_branch_id,
                "Bill #{$bill_number} created for Prescription #{$prescription['prescription_number']} - Total: " . number_format($final_amount, 2)
            ]);
            
            $db->commit();
            
            $_SESSION['flash_message'] = "✅ Bill created successfully! Bill #: {$bill_number}";
            if ($discount_amount > 0) {
                $_SESSION['flash_message'] .= " | Discount: TSh " . number_format($discount_amount, 2);
            }
            $_SESSION['flash_type'] = 'success';
            
            header("Location: dispense.php?id={$prescription_id}");
            exit;
            
        } catch (Exception $e) {
            $db->rollBack();
            $message = "❌ Error: " . $e->getMessage();
            $message_type = 'error';
        }
    }
    
    // ================================================================
    // 2. UPDATE DISCOUNT
    // ================================================================
    if ($action === 'update_discount' && $has_bill && !$is_bill_paid) {
        $discount_amount = isset($_POST['discount_amount']) ? floatval(str_replace(',', '', $_POST['discount_amount'])) : 0;
        $notes = trim($_POST['notes'] ?? '');
        
        if ($discount_amount < 0) $discount_amount = 0;
        if ($discount_amount > $prescription_total) $discount_amount = $prescription_total;
        
        $final_amount = $prescription_total - $discount_amount;
        if ($final_amount < 0) $final_amount = 0;
        
        try {
            $db->beginTransaction();
            
            // Update bill
            $stmt = $db->prepare("
                UPDATE bills 
                SET discount_amount = ?,
                    total_amount = ?,
                    balance = ?,
                    notes = CONCAT(COALESCE(notes, ''), ' | Discount updated: ', ?),
                    updated_at = NOW()
                WHERE id = ? AND visit_id = ?
            ");
            $stmt->execute([
                $discount_amount,
                $final_amount,
                $final_amount,
                date('Y-m-d H:i:s') . ' - Updated by ' . $user_full_name,
                $bill['id'],
                $prescription['visit_id']
            ]);
            
            // Update bill items final prices (proportional discount)
            $discount_ratio = $prescription_total > 0 ? $discount_amount / $prescription_total : 0;
            foreach ($bill_items as $item) {
                $item_discount = $item['total_price'] * $discount_ratio;
                $item_final = $item['total_price'] - $item_discount;
                
                $stmt = $db->prepare("
                    UPDATE bill_items 
                    SET discount_amount = ?,
                        final_price = ?,
                        updated_at = NOW()
                    WHERE id = ? AND bill_id = ?
                ");
                $stmt->execute([$item_discount, $item_final, $item['id'], $bill['id']]);
            }
            
            // Log activity
            $stmt = $db->prepare("
                INSERT INTO activity_logs (user_id, branch_id, action, details, created_at)
                VALUES (?, ?, 'prescription_discount_updated', ?, NOW())
            ");
            $stmt->execute([
                $user_id,
                $user_branch_id,
                "Discount updated for Prescription #{$prescription['prescription_number']} - New discount: " . number_format($discount_amount, 2)
            ]);
            
            $db->commit();
            
            $_SESSION['flash_message'] = "✅ Discount updated successfully! New discount: TSh " . number_format($discount_amount, 2);
            $_SESSION['flash_type'] = 'success';
            
            header("Location: dispense.php?id={$prescription_id}");
            exit;
            
        } catch (Exception $e) {
            $db->rollBack();
            $message = "❌ Error: " . $e->getMessage();
            $message_type = 'error';
        }
    }
    
    // ================================================================
    // 3. DISPENSE PRESCRIPTION (MANUAL)
    // ================================================================
    if ($action === 'dispense') {
        if (!$is_bill_paid) {
            $message = "❌ Cannot dispense: Bill is not paid yet!";
            $message_type = 'error';
        } else {
            try {
                $db->beginTransaction();
                
                // Check stock availability
                $stock_errors = [];
                foreach ($prescription_items as $item) {
                    $available = $stock_info[$item['medication_name']]['total_available'] ?? 0;
                    if ($available < $item['quantity']) {
                        $stock_errors[] = "{$item['medication_name']} - Required: {$item['quantity']}, Available: {$available}";
                    }
                }
                
                if (!empty($stock_errors)) {
                    $db->rollBack();
                    $message = "❌ Insufficient stock for: <br>" . implode('<br>', $stock_errors);
                    $message_type = 'error';
                } else {
                    // Update prescription status to dispensed
                    $stmt = $db->prepare("
                        UPDATE prescriptions 
                        SET status = 'dispensed',
                            dispensed_at = NOW(),
                            pharmacy_id = ?,
                            updated_at = NOW()
                        WHERE id = ? AND branch_id = ?
                    ");
                    $stmt->execute([$user_id, $prescription_id, $user_branch_id]);
                    
                    // Update inventory - reduce stock from batches (FIFO)
                    foreach ($prescription_items as $item) {
                        $needed = $item['quantity'];
                        $batches = $stock_info[$item['medication_name']]['batches'] ?? [];
                        
                        foreach ($batches as $batch) {
                            if ($needed <= 0) break;
                            
                            $deduct = min($needed, $batch['quantity']);
                            $new_qty = $batch['quantity'] - $deduct;
                            
                            $stmt = $db->prepare("
                                UPDATE medications_inventory 
                                SET quantity = ?,
                                    updated_at = NOW()
                                WHERE id = ? AND branch_id = ?
                            ");
                            $stmt->execute([$new_qty, $batch['id'], $user_branch_id]);
                            
                            // Log stock movement
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
                                $batch['id'],
                                $prescription['patient_id'],
                                $deduct,
                                $batch['quantity'],
                                $new_qty,
                                $prescription_id,
                                $user_id,
                                $user_branch_id,
                                "Dispensed {$deduct} units from batch {$batch['batch_number']}"
                            ]);
                            
                            $needed -= $deduct;
                        }
                    }
                    
                    // Update bill status to paid if not already
                    if ($bill && $bill['status'] !== 'paid') {
                        $stmt = $db->prepare("
                            UPDATE bills 
                            SET status = 'paid',
                                paid_amount = total_amount,
                                balance = 0,
                                updated_at = NOW()
                            WHERE id = ? AND visit_id = ?
                        ");
                        $stmt->execute([$bill['id'], $prescription['visit_id']]);
                        
                        // Update bill items
                        $stmt = $db->prepare("
                            UPDATE bill_items 
                            SET status = 'paid',
                                updated_at = NOW()
                            WHERE bill_id = ? AND reference_type = 'prescription' AND reference_id = ?
                        ");
                        $stmt->execute([$bill['id'], $prescription_id]);
                    }
                    
                    // Log activity
                    $stmt = $db->prepare("
                        INSERT INTO activity_logs (user_id, branch_id, action, details, created_at)
                        VALUES (?, ?, 'prescription_dispensed', ?, NOW())
                    ");
                    $stmt->execute([
                        $user_id,
                        $user_branch_id,
                        "Prescription #{$prescription['prescription_number']} dispensed by {$user_full_name}"
                    ]);
                    
                    $db->commit();
                    
                    $_SESSION['flash_message'] = "✅ Prescription dispensed successfully!";
                    $_SESSION['flash_type'] = 'success';
                    
                    header("Location: dispense.php?id={$prescription_id}");
                    exit;
                }
                
            } catch (Exception $e) {
                $db->rollBack();
                $message = "❌ Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// ================================================================
// GET FLASH MESSAGES
// ================================================================
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $message_type = $_SESSION['flash_type'] ?? 'info';
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
}

// ================================================================
// HELPER FUNCTIONS
// ================================================================
function getStatusBadgeClass($status) {
    $map = [
        'pending' => 'badge-warning',
        'confirmed' => 'badge-info',
        'dispensed' => 'badge-success',
        'cancelled' => 'badge-danger'
    ];
    return $map[$status] ?? 'badge-warning';
}

function getStatusLabel($status) {
    $map = [
        'pending' => '⏳ Pending',
        'confirmed' => '✅ Confirmed - Awaiting Payment',
        'dispensed' => '💊 Dispensed',
        'cancelled' => '❌ Cancelled'
    ];
    return $map[$status] ?? ucfirst($status);
}

function getBillStatusLabel($status) {
    $map = [
        'pending' => '⏳ Pending Payment',
        'partial' => '🔶 Partial Payment',
        'paid' => '✅ Paid',
        'cancelled' => '❌ Cancelled'
    ];
    return $map[$status] ?? ucfirst($status);
}

function getBillStatusBadgeClass($status) {
    $map = [
        'pending' => 'badge-warning',
        'partial' => 'badge-warning',
        'paid' => 'badge-success',
        'cancelled' => 'badge-danger'
    ];
    return $map[$status] ?? 'badge-warning';
}

function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}

function formatDate($datetime) {
    if (empty($datetime)) return 'N/A';
    return date('d/m/Y h:i A', strtotime($datetime));
}

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once '../../components/pharmacy_header.php';
include_once '../../components/pharmacy_sidebar.php';

$logo_path = '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dispense Prescription - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?>" type="image/png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
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
            --warning-dark: #B45309;
            --warning-bg: #FEF3C7;
            --info: #0B5ED7;
            --info-bg: #E8F0FE;
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
            --bg-body: #F1F5F9;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
            --shadow: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 30px rgba(0,0,0,0.12);
        }
        
        [data-theme="dark"] {
            --bg-body: #0F172A;
            --bg-card: #1E293B;
            --bg-nav: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --border-color: #334155;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 20px rgba(11, 94, 215, 0.25);
        }
        
        .page-header .page-title {
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .page-header .page-title i {
            font-size: 1.6rem;
            opacity: 0.9;
        }
        
        .page-header .badge-display {
            background: rgba(255,255,255,0.15);
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.85rem;
            margin-top: 6px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .page-header .page-subtitle i { color: rgba(255,255,255,0.6); }
        
        .btn-outline-light {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 8px 18px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.8rem;
            border: 1px solid rgba(255,255,255,0.2);
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }
        
        /* Status Banner */
        .status-banner {
            padding: 14px 20px;
            border-radius: var(--radius);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .status-banner i { font-size: 1.2rem; }
        
        .status-banner.pending { background: var(--warning-bg); color: var(--warning-dark); border: 1px solid var(--warning); }
        .status-banner.paid { background: var(--success-bg); color: var(--success-dark); border: 1px solid var(--success); }
        .status-banner.dispensed { background: var(--success-bg); color: var(--success-dark); border: 1px solid var(--success); }
        .status-banner.info { background: var(--info-bg); color: var(--primary-dark); border: 1px solid var(--primary); }
        .status-banner.error { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger); }
        
        /* Cards */
        .card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            padding: 22px 26px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }
        
        .card:hover {
            border-color: var(--primary-light);
            box-shadow: var(--shadow-md);
        }
        
        .card-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 12px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .card-title i {
            color: var(--primary);
            font-size: 1rem;
        }
        
        .card-title .badge-count {
            background: var(--primary);
            color: white;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        
        /* Badges */
        .badge-status {
            display: inline-block;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        
        .badge-warning { background: var(--warning-bg); color: var(--warning-dark); border: 1px solid var(--warning); }
        .badge-info { background: var(--info-bg); color: var(--primary-dark); border: 1px solid var(--primary); }
        .badge-success { background: var(--success-bg); color: var(--success-dark); border: 1px solid var(--success); }
        .badge-danger { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger); }
        
        /* Detail Rows */
        .detail-row {
            display: flex;
            padding: 5px 0;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.82rem;
        }
        
        .detail-row:last-child { border-bottom: none; }
        
        .detail-label {
            font-weight: 600;
            color: var(--text-secondary);
            width: 150px;
            flex-shrink: 0;
        }
        
        .detail-value { flex: 1; color: var(--text-primary); }
        
        /* Table */
        .table-wrap {
            overflow-x: auto;
            margin-top: 12px;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
        }
        
        .items-table thead th {
            text-align: left;
            padding: 8px 12px;
            font-weight: 600;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-secondary);
            background: var(--bg-body);
            border-bottom: 2px solid var(--border-color);
            white-space: nowrap;
        }
        
        .items-table thead th:last-child { text-align: right; }
        .items-table tbody td {
            padding: 6px 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .items-table tbody td:last-child { text-align: right; font-weight: 600; font-family: monospace; }
        .items-table tbody tr:hover td { background: var(--primary-bg); }
        
        .items-table .total-row td {
            font-weight: 700;
            border-top: 2px solid var(--primary);
            background: var(--primary-bg);
            padding: 8px 12px;
        }
        .items-table .total-row td:last-child {
            color: var(--primary);
            font-size: 1rem;
        }
        
        .items-table .stock-badge {
            display: inline-block;
            padding: 1px 8px;
            border-radius: 10px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        
        .stock-badge.sufficient { background: var(--success-bg); color: var(--success-dark); }
        .stock-badge.insufficient { background: var(--danger-bg); color: var(--danger); }
        .stock-badge.low { background: var(--warning-bg); color: var(--warning-dark); }
        
        /* Discount Section */
        .discount-section {
            background: var(--warning-bg);
            border: 2px solid var(--warning);
            border-radius: var(--radius);
            padding: 18px 22px;
            margin-top: 16px;
        }
        
        .discount-section .discount-title {
            font-weight: 600;
            color: var(--warning-dark);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
        }
        
        .discount-section .form-group {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }
        
        .discount-section .form-group label {
            font-weight: 600;
            font-size: 0.82rem;
            color: var(--text-primary);
            white-space: nowrap;
        }
        
        .discount-section .amount-input-wrap {
            position: relative;
            display: inline-block;
        }
        
        .discount-section .amount-input-wrap .currency-prefix {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 600;
        }
        
        .discount-section .amount-input-wrap input {
            padding: 8px 12px 8px 32px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.9rem;
            font-family: monospace;
            font-weight: 600;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            width: 180px;
            transition: var(--transition);
            text-align: right;
        }
        
        .discount-section .amount-input-wrap input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
        }
        
        .discount-section textarea {
            padding: 8px 12px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.8rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: var(--transition);
            width: 100%;
            resize: vertical;
            min-height: 50px;
            font-family: inherit;
        }
        
        .discount-section textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
        }
        
        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 20px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.82rem;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background: var(--success-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
        }
        
        .btn-success:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }
        
        .btn-warning-custom {
            background: var(--warning);
            color: white;
        }
        
        .btn-warning-custom:hover {
            background: var(--warning-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }
        
        .btn-outline:hover {
            background: var(--bg-body);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .btn-lg {
            padding: 12px 32px;
            font-size: 0.95rem;
        }
        
        /* Bill Summary */
        .bill-summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
            margin-top: 12px;
        }
        
        .bill-summary-item {
            background: var(--bg-body);
            border-radius: var(--radius);
            padding: 10px 14px;
            text-align: center;
            border: 1px solid var(--border-color);
        }
        
        .bill-summary-item .label {
            font-size: 0.6rem;
            text-transform: uppercase;
            color: var(--text-secondary);
            font-weight: 600;
            letter-spacing: 0.05em;
        }
        
        .bill-summary-item .value {
            font-size: 1.1rem;
            font-weight: 700;
            font-family: monospace;
            margin-top: 2px;
        }
        
        .bill-summary-item .value.green { color: var(--success); }
        .bill-summary-item .value.red { color: var(--danger); }
        .bill-summary-item .value.blue { color: var(--primary); }
        .bill-summary-item .value.orange { color: var(--warning); }
        
        /* Stock Info */
        .stock-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 8px;
            margin-top: 8px;
        }
        
        .stock-info-item {
            padding: 6px 12px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            font-size: 0.75rem;
        }
        
        .stock-info-item .batch-label {
            font-weight: 600;
            color: var(--text-secondary);
        }
        
        .stock-info-item .batch-qty {
            font-weight: 700;
        }
        
        .stock-info-item .batch-expiry {
            font-size: 0.65rem;
            color: var(--text-secondary);
        }
        
        .stock-info-item.sufficient { border-color: var(--success); background: var(--success-bg); }
        .stock-info-item.insufficient { border-color: var(--danger); background: var(--danger-bg); }
        .stock-info-item.low { border-color: var(--warning); background: var(--warning-bg); }
        
        /* Payment Method Select */
        .payment-method-select {
            padding: 8px 14px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.8rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: var(--transition);
        }
        
        .payment-method-select:focus {
            border-color: var(--primary);
        }
        
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        .toast-custom {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 14px 20px;
            border-radius: 12px;
            z-index: 999;
            max-width: 400px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            box-shadow: var(--shadow-lg);
        }
        
        .toast-custom.show {
            transform: translateY(0);
            opacity: 1;
        }
        
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 20px; }
        }
        
        @media (max-width: 768px) {
            .detail-row { flex-direction: column; }
            .detail-label { width: 100%; margin-bottom: 2px; }
            .discount-section .form-group { flex-direction: column; align-items: stretch; }
            .discount-section .amount-input-wrap input { width: 100%; }
            .bill-summary-grid { grid-template-columns: 1fr 1fr; }
            .items-table { font-size: 0.7rem; }
            .card { padding: 16px; }
            .btn { width: 100%; justify-content: center; }
            .btn-lg { padding: 10px 20px; }
            .page-header { padding: 16px 20px; }
            .page-header .page-title { font-size: 1.1rem; }
            .stock-info-grid { grid-template-columns: 1fr; }
        }
        
        @media (max-width: 480px) {
            .bill-summary-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- TOP NAV -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <input type="text" id="searchInput" placeholder="Search prescriptions..." class="search-input">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <span class="branch-badge-display">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($user_branch_name) ?>
        </span>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $logo_path ?>" alt="Profile" class="avatar"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22%3E%3Crect width=%2240%22 height=%2240%22 fill=%22%230B5ED7%22 rx=%2250%25%22/%3E%3Ctext x=%2220%22 y=%2226%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22%3EA%3C/text%3E%3C/svg%3E'">
        </a>
    </div>
</nav>

<!-- ================================================================ -->
<!-- MAIN CONTENT -->
<!-- ================================================================ -->
<main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-prescription-bottle"></i>
                Dispense Prescription
                <span class="badge-display"><?= htmlspecialchars($prescription['prescription_number'] ?? 'N/A') ?></span>
            </h1>
            <div class="page-subtitle">
                <span><i class="fas fa-user"></i> <?= htmlspecialchars($prescription['patient_name'] ?? 'Unknown') ?></span>
                <span><i class="fas fa-id-card"></i> <?= htmlspecialchars($prescription['patient_code'] ?? 'N/A') ?></span>
                <span><i class="fas fa-stethoscope"></i> Dr. <?= htmlspecialchars($prescription['doctor_name'] ?? 'Not Assigned') ?></span>
                <span><i class="fas fa-calendar"></i> <?= date('d/m/Y', strtotime($prescription['created_at'])) ?></span>
                <?php if ($is_dispensed && !empty($prescription['dispensed_at'])): ?>
                    <span class="text-green-200"><i class="fas fa-check-circle"></i> Dispensed: <?= formatDate($prescription['dispensed_at']) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="pending_prescriptions.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- Messages -->
    <?php if ($message): ?>
        <div class="status-banner <?= $message_type === 'success' ? 'paid' : ($message_type === 'warning' ? 'pending' : 'error') ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle') ?>"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- Status Banner -->
    <?php if ($is_dispensed): ?>
        <div class="status-banner dispensed">
            <i class="fas fa-check-circle"></i>
            ✅ Prescription dispensed on <?= !empty($prescription['dispensed_at']) ? formatDate($prescription['dispensed_at']) : date('d/m/Y H:i') ?>
            <?php if (!empty($prescription['pharmacy_name'])): ?>
                <span class="text-xs">by <?= htmlspecialchars($prescription['pharmacy_name']) ?></span>
            <?php endif; ?>
        </div>
    <?php elseif ($is_bill_paid): ?>
        <div class="status-banner paid">
            <i class="fas fa-check-circle"></i>
            💰 Bill paid. Ready to dispense!
            <?php if ($bill): ?>
                <span class="text-xs">Bill #<?= htmlspecialchars($bill['bill_number']) ?></span>
            <?php endif; ?>
        </div>
    <?php elseif ($has_bill): ?>
        <div class="status-banner pending">
            <i class="fas fa-clock"></i>
            ⏳ Bill sent to Cashier. Waiting for payment.
            <?php if ($bill): ?>
                <span class="text-xs">Bill #<?= htmlspecialchars($bill['bill_number']) ?></span>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="status-banner info">
            <i class="fas fa-info-circle"></i>
            📋 Create bill to send to Cashier.
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- PATIENT INFORMATION -->
    <!-- ================================================================ -->
    <div class="card">
        <h3 class="card-title">
            <i class="fas fa-user-circle"></i>
            Patient Information
            <span class="badge-count">
                <?= ($prescription['gender'] ?? '') === 'Female' ? '👩' : '👨' ?>
                <?= $prescription['gender'] ?? 'N/A' ?>
                <?= !empty($prescription['date_of_birth']) ? '• ' . calculateAge($prescription['date_of_birth']) . ' yrs' : '' ?>
            </span>
        </h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:2px 20px;">
            <div class="detail-row"><span class="detail-label">Full Name</span><span class="detail-value"><strong><?= htmlspecialchars($prescription['patient_name'] ?? 'N/A') ?></strong></span></div>
            <div class="detail-row"><span class="detail-label">Patient ID</span><span class="detail-value"><?= htmlspecialchars($prescription['patient_code'] ?? 'N/A') ?></span></div>
            <div class="detail-row"><span class="detail-label">Gender</span><span class="detail-value"><?= htmlspecialchars($prescription['gender'] ?? 'N/A') ?></span></div>
            <div class="detail-row"><span class="detail-label">Date of Birth</span><span class="detail-value"><?= !empty($prescription['date_of_birth']) ? date('d/m/Y', strtotime($prescription['date_of_birth'])) : 'N/A' ?></span></div>
            <div class="detail-row"><span class="detail-label">Phone</span><span class="detail-value"><?= htmlspecialchars($prescription['phone'] ?? 'N/A') ?></span></div>
            <div class="detail-row"><span class="detail-label">Blood Group</span><span class="detail-value"><?= htmlspecialchars($prescription['blood_group'] ?? 'N/A') ?></span></div>
            <div class="detail-row"><span class="detail-label">Allergies</span><span class="detail-value"><?= htmlspecialchars($prescription['allergies'] ?? 'None') ?></span></div>
            <div class="detail-row"><span class="detail-label">Emergency Contact</span><span class="detail-value"><?= htmlspecialchars($prescription['emergency_contact'] ?? 'N/A') ?></span></div>
            <div class="detail-row" style="grid-column: span 2;"><span class="detail-label">Address</span><span class="detail-value"><?= htmlspecialchars($prescription['address'] ?? 'N/A') ?></span></div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PRESCRIPTION ITEMS -->
    <!-- ================================================================ -->
    <div class="card">
        <h3 class="card-title">
            <i class="fas fa-prescription"></i>
            Prescription Items
            <span class="badge-count"><?= count($prescription_items) ?> item(s)</span>
            <span class="badge-status <?= getStatusBadgeClass($prescription['status'] ?? 'pending') ?>">
                <?= getStatusLabel($prescription['status'] ?? 'pending') ?>
            </span>
        </h3>
        
        <?php if (!empty($prescription['diagnosis'])): ?>
            <div class="detail-row"><span class="detail-label">Diagnosis</span><span class="detail-value"><?= htmlspecialchars($prescription['diagnosis']) ?></span></div>
        <?php endif; ?>
        <?php if (!empty($prescription['visit_number'])): ?>
            <div class="detail-row"><span class="detail-label">Visit Number</span><span class="detail-value"><?= htmlspecialchars($prescription['visit_number']) ?></span></div>
        <?php endif; ?>
        <?php if (!empty($prescription['instructions'])): ?>
            <div class="detail-row"><span class="detail-label">Instructions</span><span class="detail-value"><?= nl2br(htmlspecialchars($prescription['instructions'])) ?></span></div>
        <?php endif; ?>
        
        <div class="table-wrap">
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width:30%;">Medication</th>
                        <th style="width:10%; text-align:center;">Qty</th>
                        <th style="width:12%; text-align:right;">Unit Price</th>
                        <th style="width:15%; text-align:right;">Total</th>
                        <th style="width:15%; text-align:center;">Stock Status</th>
                        <th style="width:18%; text-align:center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($prescription_items as $item): ?>
                        <?php 
                            $med_stock = $stock_info[$item['medication_name']] ?? null;
                            $is_sufficient = $med_stock ? $med_stock['sufficient'] : false;
                            $total_avail = $med_stock ? $med_stock['total_available'] : 0;
                            $stock_class = $is_sufficient ? 'sufficient' : ($total_avail > 0 ? 'low' : 'insufficient');
                            $stock_label = $is_sufficient ? '✅ Sufficient' : ($total_avail > 0 ? '⚠️ Low Stock' : '❌ Out of Stock');
                        ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($item['medication_name'] ?? 'N/A') ?></strong>
                                <?php if (!empty($item['dosage'])): ?>
                                    <br><span class="text-xs text-gray-400"><?= htmlspecialchars($item['dosage']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($item['frequency'])): ?>
                                    <span class="text-xs text-gray-400"> • <?= htmlspecialchars($item['frequency']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;"><?= $item['quantity'] ?? 0 ?></td>
                            <td style="text-align:right;font-family:monospace;">
                                <?= number_format($item['unit_price'] ?? 0, 2) ?>
                            </td>
                            <td style="text-align:right;font-family:monospace;font-weight:600;color:var(--primary);">
                                <?= number_format($item['total_price'] ?? 0, 2) ?>
                            </td>
                            <td style="text-align:center;">
                                <span class="stock-badge <?= $stock_class ?>">
                                    <?= $stock_label ?>
                                    <span class="text-xs">(<?= $total_avail ?> avail)</span>
                                </span>
                            </td>
                            <td style="text-align:center;">
                                <?php if ($is_dispensed): ?>
                                    <span class="badge-status badge-success">✅ Dispensed</span>
                                <?php elseif ($is_bill_paid): ?>
                                    <span class="badge-status badge-success">💳 Paid</span>
                                <?php elseif ($has_bill): ?>
                                    <span class="badge-status badge-warning">⏳ Awaiting Payment</span>
                                <?php else: ?>
                                    <span class="badge-status badge-warning">⏳ Pending</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <tr class="total-row">
                        <td colspan="3" style="text-align:right;font-weight:700;font-size:0.9rem;">
                            <i class="fas fa-receipt"></i> GRAND TOTAL:
                        </td>
                        <td style="text-align:right;font-family:monospace;font-size:1.1rem;color:var(--primary);">
                            <?= number_format($prescription_total, 2) ?>
                        </td>
                        <td colspan="2"></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- BILL SUMMARY -->
    <!-- ================================================================ -->
    <?php if ($has_bill): ?>
    <div class="card" style="border-color:<?= $is_bill_paid ? 'var(--success)' : 'var(--warning)' ?>;border-left:4px solid <?= $is_bill_paid ? 'var(--success)' : 'var(--warning)' ?>;">
        <h3 class="card-title">
            <i class="fas fa-receipt" style="color:<?= $is_bill_paid ? 'var(--success)' : 'var(--warning)' ?>;"></i>
            Bill Details
            <span class="badge-status <?= getBillStatusBadgeClass($bill_status) ?>">
                <?= getBillStatusLabel($bill_status) ?>
            </span>
            <?php if ($bill_number): ?>
            <span class="badge-count" style="background:var(--primary);">#<?= htmlspecialchars($bill_number) ?></span>
            <?php endif; ?>
        </h3>
        
        <div class="bill-summary-grid">
            <div class="bill-summary-item">
                <div class="label">Total Amount</div>
                <div class="value blue"><?= number_format($bill_total, 2) ?></div>
            </div>
            <div class="bill-summary-item" style="border-color:var(--warning);">
                <div class="label">Discount</div>
                <div class="value orange"><?= number_format($bill_discount, 2) ?></div>
            </div>
            <div class="bill-summary-item" style="border-color:<?= $is_bill_paid ? 'var(--success)' : 'var(--danger)' ?>;">
                <div class="label">Balance</div>
                <div class="value <?= $is_bill_paid ? 'green' : 'red' ?>">
                    <?= number_format($bill_balance, 2) ?>
                </div>
            </div>
            <div class="bill-summary-item" style="border-color:<?= $is_bill_paid ? 'var(--success)' : 'var(--warning)' ?>;">
                <div class="label">Status</div>
                <div class="value <?= $is_bill_paid ? 'green' : 'orange' ?>">
                    <?= $is_bill_paid ? '✅ PAID' : '⏳ PENDING' ?>
                </div>
            </div>
        </div>
        
        <?php if (!empty($bill_items)): ?>
        <div class="table-wrap" style="margin-top:12px;">
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width:40%;">Item</th>
                        <th style="width:15%; text-align:center;">Qty</th>
                        <th style="width:20%; text-align:right;">Unit Price</th>
                        <th style="width:25%; text-align:right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bill_items as $bitem): ?>
                    <tr>
                        <td><?= htmlspecialchars($bitem['item_name'] ?? 'N/A') ?></td>
                        <td style="text-align:center;"><?= $bitem['quantity'] ?? 0 ?></td>
                        <td style="text-align:right;font-family:monospace;"><?= number_format($bitem['unit_price'] ?? 0, 2) ?></td>
                        <td style="text-align:right;font-family:monospace;font-weight:600;"><?= number_format($bitem['total_price'] ?? 0, 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- STOCK INFORMATION -->
    <!-- ================================================================ -->
    <?php if (!$is_dispensed): ?>
    <div class="card">
        <h3 class="card-title">
            <i class="fas fa-boxes"></i>
            Stock Availability
            <span class="badge-count" style="background:var(--info);">
                <i class="fas fa-info-circle"></i> Batch Details
            </span>
        </h3>
        
        <?php foreach ($prescription_items as $item): ?>
            <?php $med_stock = $stock_info[$item['medication_name']] ?? null; ?>
            <div style="margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid var(--border-color);">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:6px;">
                    <strong><?= htmlspecialchars($item['medication_name']) ?></strong>
                    <span>
                        Required: <strong><?= $item['quantity'] ?></strong> 
                        | Available: <strong><?= $med_stock ? $med_stock['total_available'] : 0 ?></strong>
                        <?php if ($med_stock && !$med_stock['sufficient']): ?>
                            <span class="badge-status badge-danger">⚠️ Insufficient</span>
                        <?php endif; ?>
                    </span>
                </div>
                <?php if ($med_stock && !empty($med_stock['batches'])): ?>
                    <div class="stock-info-grid">
                        <?php foreach ($med_stock['batches'] as $batch): ?>
                            <?php 
                                $batch_class = $batch['quantity'] > 0 ? 'sufficient' : 'insufficient';
                                $is_expired = !empty($batch['expiry_date']) && strtotime($batch['expiry_date']) < time();
                                $is_expiring = !empty($batch['expiry_date']) && strtotime($batch['expiry_date']) < strtotime('+30 days') && !$is_expired;
                                if ($is_expired) $batch_class = 'insufficient';
                                elseif ($is_expiring && $batch['quantity'] > 0) $batch_class = 'low';
                            ?>
                            <div class="stock-info-item <?= $batch_class ?>">
                                <div class="batch-label">Batch: <?= htmlspecialchars($batch['batch_number'] ?? 'N/A') ?></div>
                                <div class="batch-qty">Qty: <?= $batch['quantity'] ?></div>
                                <div class="batch-expiry">
                                    <?php if (!empty($batch['expiry_date'])): ?>
                                        Exp: <?= date('d/m/Y', strtotime($batch['expiry_date'])) ?>
                                        <?php if ($is_expired): ?>
                                            <span class="text-danger">🔴 EXPIRED</span>
                                        <?php elseif ($is_expiring): ?>
                                            <span class="text-warning">🟡 Expiring Soon</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        No expiry
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-xs text-gray-400">No active stock available</div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- ACTION FORM -->
    <!-- ================================================================ -->
    <?php if (!$is_dispensed): ?>
    
    <!-- CASE 1: No bill yet - Create Bill -->
    <?php if (!$has_bill): ?>
    <form method="POST" action="" id="actionForm">
        <div class="card">
            <h3 class="card-title">
                <i class="fas fa-file-invoice"></i>
                Create Prescription Bill
                <span class="badge-count" style="background:var(--success);">
                    <i class="fas fa-plus-circle"></i> Send to Cashier
                </span>
            </h3>
            
            <div class="discount-section">
                <div class="discount-title">
                    <i class="fas fa-percent"></i>
                    Apply Discount
                    <span class="text-xs text-gray-500 font-normal">(Enter amount in TSh)</span>
                </div>
                
                <div class="form-group">
                    <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;flex:1;">
                        <div class="amount-input-wrap">
                            <span class="currency-prefix">TSh</span>
                            <input type="text" id="discountAmount" name="discount_amount" 
                                   placeholder="0.00" value="0.00"
                                   oninput="formatAmount(this); updateTotals();">
                        </div>
                        <span class="text-xs text-gray-400">
                            <i class="fas fa-info-circle"></i>
                            Max: TSh <?= number_format($prescription_total, 2) ?>
                        </span>
                    </div>
                </div>
                
                <div class="form-group" style="margin-top:10px;">
                    <label><i class="fas fa-sticky-note"></i> Notes:</label>
                    <textarea name="notes" placeholder="Optional notes..." rows="2"></textarea>
                </div>
            </div>
            
            <!-- Summary -->
            <div style="margin-top:16px;padding:16px 20px;background:var(--bg-body);border-radius:var(--radius);border:2px solid var(--border-color);">
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:12px;">
                    <div>
                        <div class="text-xs text-gray-400 font-semibold uppercase">Subtotal</div>
                        <div class="text-lg font-bold text-blue-600 font-mono" id="displaySubtotal">
                            <?= number_format($prescription_total, 2) ?>
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400 font-semibold uppercase">Discount</div>
                        <div class="text-lg font-bold text-orange-600 font-mono" id="displayDiscount">0.00</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400 font-semibold uppercase">Total</div>
                        <div class="text-lg font-bold text-green-600 font-mono" id="displayTotal">
                            <?= number_format($prescription_total, 2) ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div style="display:flex;flex-wrap:wrap;gap:12px;margin-top:16px;padding-top:16px;border-top:2px solid var(--border-color);">
                <button type="submit" name="action" value="create_bill" class="btn btn-primary btn-lg"
                        onclick="return confirm('Create bill and send to Cashier?\n\nSubtotal: TSh <?= number_format($prescription_total, 2) ?>\nDiscount: TSh ' + getDiscountDisplay() + '\nTotal: TSh ' + getNetTotalDisplay() + '\n\nPatient: <?= addslashes($prescription['patient_name'] ?? 'Unknown') ?>');">
                    <i class="fas fa-file-invoice"></i> Create Bill & Send to Cashier
                </button>
                
                <span class="text-xs text-blue-600 self-center">
                    <i class="fas fa-info-circle"></i> Bill will be sent to Cashier for payment.
                </span>
                
                <a href="pending_prescriptions.php" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </div>
    </form>
    
    <!-- CASE 2: Has bill, not paid - Update Discount -->
    <?php elseif ($has_bill && !$is_bill_paid): ?>
    <form method="POST" action="" id="actionForm">
        <div class="card">
            <h3 class="card-title">
                <i class="fas fa-edit"></i>
                Update Bill
                <span class="badge-count" style="background:var(--warning);">
                    <i class="fas fa-percent"></i> Adjust Discount
                </span>
            </h3>
            
            <div class="discount-section">
                <div class="discount-title">
                    <i class="fas fa-percent"></i>
                    Update Discount
                    <span class="text-xs text-gray-500 font-normal">(Enter amount in TSh)</span>
                </div>
                
                <div class="form-group">
                    <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;flex:1;">
                        <div class="amount-input-wrap">
                            <span class="currency-prefix">TSh</span>
                            <input type="text" id="discountAmount" name="discount_amount" 
                                   placeholder="0.00" value="<?= number_format($bill_discount, 2) ?>"
                                   oninput="formatAmount(this); updateTotals();">
                        </div>
                        <span class="text-xs text-gray-400">
                            <i class="fas fa-info-circle"></i>
                            Current: TSh <?= number_format($bill_discount, 2) ?> | Max: TSh <?= number_format($prescription_total, 2) ?>
                        </span>
                    </div>
                </div>
                
                <div class="form-group" style="margin-top:10px;">
                    <label><i class="fas fa-sticky-note"></i> Notes:</label>
                    <textarea name="notes" placeholder="Optional notes..." rows="2"></textarea>
                </div>
            </div>
            
            <!-- Summary -->
            <div style="margin-top:16px;padding:16px 20px;background:var(--bg-body);border-radius:var(--radius);border:2px solid var(--border-color);">
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:12px;">
                    <div>
                        <div class="text-xs text-gray-400 font-semibold uppercase">Subtotal</div>
                        <div class="text-lg font-bold text-blue-600 font-mono" id="displaySubtotal">
                            <?= number_format($prescription_total, 2) ?>
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400 font-semibold uppercase">Discount</div>
                        <div class="text-lg font-bold text-orange-600 font-mono" id="displayDiscount">
                            <?= number_format($bill_discount, 2) ?>
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400 font-semibold uppercase">Total</div>
                        <div class="text-lg font-bold text-green-600 font-mono" id="displayTotal">
                            <?= number_format($prescription_total - $bill_discount, 2) ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div style="display:flex;flex-wrap:wrap;gap:12px;margin-top:16px;padding-top:16px;border-top:2px solid var(--border-color);">
                <button type="submit" name="action" value="update_discount" class="btn btn-warning-custom btn-lg"
                        onclick="return confirm('Update discount?\n\nSubtotal: TSh <?= number_format($prescription_total, 2) ?>\nNew Discount: TSh ' + getDiscountDisplay() + '\nNew Total: TSh ' + getNetTotalDisplay());">
                    <i class="fas fa-save"></i> Update Discount
                </button>
                
                <span class="text-xs text-gray-500 self-center">
                    <i class="fas fa-info-circle"></i> Bill is awaiting payment from Cashier.
                </span>
                
                <a href="pending_prescriptions.php" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </div>
    </form>
    
    <!-- CASE 3: Has bill, paid - Dispense -->
    <?php elseif ($has_bill && $is_bill_paid): ?>
    <form method="POST" action="" id="actionForm">
        <div class="card" style="border-color:var(--success);border-left:4px solid var(--success);">
            <h3 class="card-title">
                <i class="fas fa-check-circle" style="color:var(--success);"></i>
                Bill Paid - Dispense Prescription
                <span class="badge-status badge-success">✅ Ready to Dispense</span>
            </h3>
            
            <?php
                // Check if all items have sufficient stock
                $all_sufficient = true;
                $stock_errors = [];
                foreach ($prescription_items as $item) {
                    $med_stock = $stock_info[$item['medication_name']] ?? null;
                    if (!$med_stock || !$med_stock['sufficient']) {
                        $all_sufficient = false;
                        $stock_errors[] = "{$item['medication_name']} - Required: {$item['quantity']}, Available: " . ($med_stock ? $med_stock['total_available'] : 0);
                    }
                }
            ?>
            
            <?php if ($all_sufficient): ?>
                <div class="status-banner paid" style="margin-bottom:16px;">
                    <i class="fas fa-check-circle"></i>
                    All medications have sufficient stock. Ready to dispense.
                </div>
                
                <div style="display:flex;flex-wrap:wrap;gap:12px;">
                    <button type="submit" name="action" value="dispense" class="btn btn-success btn-lg"
                            onclick="return confirm('Dispense prescription?\n\nPatient: <?= addslashes($prescription['patient_name'] ?? 'Unknown') ?>\nItems: <?= count($prescription_items) ?>\nTotal: TSh <?= number_format($prescription_total, 2) ?>\n\nStock will be reduced from inventory.\n\nConfirm dispense?');">
                        <i class="fas fa-prescription-bottle"></i> 💊 Dispense Now
                    </button>
                    
                    <span class="text-xs text-green-600 self-center">
                        <i class="fas fa-check-circle"></i> Stock will be automatically reduced.
                    </span>
                </div>
            <?php else: ?>
                <div class="status-banner error">
                    <i class="fas fa-exclamation-triangle"></i>
                    Insufficient stock for some items:
                    <ul style="margin-top:4px;list-style:none;padding-left:0;">
                        <?php foreach ($stock_errors as $error): ?>
                            <li>• <?= $error ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <p class="text-sm text-gray-500">
                    <i class="fas fa-info-circle"></i> Please restock the above items before dispensing.
                </p>
                
                <div style="margin-top:12px;">
                    <a href="pending_prescriptions.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Back to Prescriptions
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </form>
    <?php endif; ?>
    
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- DISPENSED STATUS -->
    <!-- ================================================================ -->
    <?php if ($is_dispensed): ?>
        <div class="card" style="border-color:var(--success);border-left:4px solid var(--success);">
            <h3 class="card-title">
                <i class="fas fa-check-circle" style="color:var(--success);"></i>
                Dispensed Successfully
                <span class="badge-status badge-success">✅ Dispensed</span>
            </h3>
            <p class="text-gray-500">
                Prescription dispensed on <strong><?= !empty($prescription['dispensed_at']) ? formatDate($prescription['dispensed_at']) : date('d/m/Y H:i') ?></strong>
            </p>
            <?php if (!empty($prescription['pharmacy_name'])): ?>
                <p class="text-gray-500">
                    Dispensed by: <strong><?= htmlspecialchars($prescription['pharmacy_name']) ?></strong>
                </p>
            <?php endif; ?>
            <div style="margin-top:12px;">
                <a href="pending_prescriptions.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Back to Prescriptions
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- FOOTER -->
    <!-- ================================================================ -->
    <footer class="footer">
        <p>
            <span class="footer-brand">Braick Dispensary</span> Management System
            <span class="text-gray-300 mx-2">|</span>
            Dispense Prescription
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
    <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
    <div>
        <p style="font-weight:600;font-size:0.85rem;margin:0;" id="toastTitle">Notification</p>
        <p style="font-size:0.75rem;opacity:0.9;margin:0;" id="toastMessage"></p>
    </div>
</div>

<!-- ================================================================ -->
<!-- JAVASCRIPT -->
<!-- ================================================================ -->
<script>
    var prescriptionTotal = <?= $prescription_total ?>;
    var currentDiscount = <?= $bill_discount ?>;
    
    // Format amount with commas
    function formatAmount(input) {
        var val = input.value.replace(/[^0-9.]/g, '');
        var parts = val.split('.');
        var whole = parts[0];
        var decimal = parts.length > 1 ? '.' + parts[1].slice(0, 2) : '';
        
        if (whole.length > 0) {
            whole = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }
        
        input.value = whole + decimal;
        var rawValue = parseFloat(val) || 0;
        input.dataset.rawValue = rawValue;
    }
    
    function getRawValue(input) {
        var raw = input.dataset.rawValue;
        if (raw !== undefined && raw !== '') {
            return parseFloat(raw) || 0;
        }
        var val = input.value.replace(/,/g, '');
        return parseFloat(val) || 0;
    }
    
    function getDiscountDisplay() {
        var discountInput = document.getElementById('discountAmount');
        return discountInput ? discountInput.value || '0.00' : '0.00';
    }
    
    function getNetTotalDisplay() {
        var discountInput = document.getElementById('discountAmount');
        if (!discountInput) return prescriptionTotal.toFixed(2);
        var discount = getRawValue(discountInput);
        var netTotal = prescriptionTotal - discount;
        if (netTotal < 0) netTotal = 0;
        return netTotal.toFixed(2);
    }
    
    function updateTotals() {
        var discountInput = document.getElementById('discountAmount');
        if (!discountInput) return;
        
        var discount = getRawValue(discountInput);
        
        if (discount > prescriptionTotal) {
            discount = prescriptionTotal;
            discountInput.value = discount.toFixed(2);
            discountInput.dataset.rawValue = discount;
        }
        if (discount < 0) {
            discount = 0;
            discountInput.value = '0.00';
            discountInput.dataset.rawValue = 0;
        }
        
        var netTotal = prescriptionTotal - discount;
        if (netTotal < 0) netTotal = 0;
        
        document.getElementById('displayDiscount').textContent = discount.toFixed(2);
        document.getElementById('displayTotal').textContent = netTotal.toFixed(2);
    }
    
    // Dark Mode
    var darkModeToggle = document.getElementById('darkModeToggle');
    var darkIcon = document.getElementById('darkIcon');
    var darkText = document.getElementById('darkText');
    var htmlElement = document.documentElement;
    
    var savedDarkMode = localStorage.getItem('darkMode');
    if (savedDarkMode === 'true') {
        htmlElement.setAttribute('data-theme', 'dark');
        darkIcon.className = 'fas fa-sun';
        darkText.textContent = 'Light';
    }
    
    darkModeToggle?.addEventListener('click', function() {
        var isDark = htmlElement.getAttribute('data-theme') === 'dark';
        if (isDark) {
            htmlElement.removeAttribute('data-theme');
            darkIcon.className = 'fas fa-moon';
            darkText.textContent = 'Dark';
            localStorage.setItem('darkMode', 'false');
        } else {
            htmlElement.setAttribute('data-theme', 'dark');
            darkIcon.className = 'fas fa-sun';
            darkText.textContent = 'Light';
            localStorage.setItem('darkMode', 'true');
        }
    });

    // Sidebar Toggle
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    
    sidebarToggle?.addEventListener('click', function() {
        sidebar.classList.toggle('open');
    });
    
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('open');
            }
        }
    });

    // Date & Time
    function updateDateTime() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        document.getElementById('currentDateTime').textContent = dateStr + ' • ' + timeStr;
        document.getElementById('footerTimestamp').textContent = timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // Init
    document.addEventListener('DOMContentLoaded', function() {
        var discountInput = document.getElementById('discountAmount');
        if (discountInput) {
            if (currentDiscount > 0) {
                discountInput.value = currentDiscount.toFixed(2);
                discountInput.dataset.rawValue = currentDiscount;
            }
        }
        updateTotals();
    });

    <?php if ($message && $message_type): ?>
    setTimeout(function() {
        showToast('<?= $message_type === 'success' ? '✅ Success' : ($message_type === 'warning' ? '⚠️ Warning' : '❌ Error') ?>', 
            '<?= addslashes($message) ?>', 
            '<?= $message_type ?>'
        );
    }, 500);
    <?php endif; ?>

    function showToast(title, message, type) {
        var toast = document.getElementById('toast');
        var toastTitle = document.getElementById('toastTitle');
        var toastMessage = document.getElementById('toastMessage');
        
        toast.className = 'toast-custom ' + type;
        toastTitle.textContent = title;
        toastMessage.textContent = message;
        toast.style.display = 'flex';
        
        toast.classList.add('show');
        clearTimeout(toast.timeout);
        toast.timeout = setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() {
                toast.style.display = 'none';
            }, 400);
        }, 3500);
    }

    console.log('%c💊 Braick - Dispense Prescription', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📋 Prescription: <?= htmlspecialchars($prescription['prescription_number'] ?? 'N/A') ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c👤 Patient: <?= htmlspecialchars($prescription['patient_name'] ?? 'Unknown') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c💰 Total: <?= number_format($prescription_total, 2) ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Bill Status: <?= $bill_status ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c✅ Using NEW database schema: bills, bill_items, stock_movements', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>