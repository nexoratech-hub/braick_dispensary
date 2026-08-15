<?php
// ================================================================
// FILE: frontend/pages/pharmacy/dispense.php
// PHARMACY - DISPENSE PRESCRIPTION WITH DISCOUNT
// FIXED: SESSION MANAGEMENT & LOGIN PROTECTION
// BRAICK DISPENSARY
// ================================================================

// ================================================================
// SESSION START
// ================================================================
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
// CHECK IF USER HAS PHARMACY ACCESS
// ================================================================
$allowed_roles = ['pharmacy', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    // Redirect to their own dashboard
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
// GET USER DATA FROM SESSION
// ================================================================
$user_id = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['full_name'] ?? 'Pharmacy Staff';
$user_role = $_SESSION['role'] ?? 'pharmacy';
$user_branch_id = $_SESSION['branch_id'] ?? 1;
$user_branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$username = $_SESSION['username'] ?? 'pharmacy';

// ================================================================
// IF SESSION IS INCOMPLETE, SET DEFAULTS
// ================================================================
if ($user_id <= 0) {
    // Try to get from database using username
    if (isset($username) && !empty($username)) {
        require_once __DIR__ . '/../../../backend/config/database.php';
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT id, full_name, role, branch_id FROM users WHERE username = ? AND status = 'active'");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['branch_id'] = $user['branch_id'];
                $user_id = $user['id'];
                $user_full_name = $user['full_name'];
                $user_role = $user['role'];
                $user_branch_id = $user['branch_id'];
            }
        } catch (Exception $e) {
            // Fallback to default
        }
    }
}

// If still no user_id, redirect to login
if ($user_id <= 0) {
    header('Location: ../login.php');
    exit;
}

// ================================================================
// PATH SAHIHI
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

$db = getDB();
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
// GET PRESCRIPTION DETAILS - FIXED: Gets prescription bill only
// ================================================================
$stmt = $db->prepare("
    SELECT 
        p.*,
        pat.id as patient_id,
        pat.full_name as patient_name,
        pat.patient_id as patient_code,
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
        u.email as doctor_email,
        u.phone as doctor_phone,
        v.visit_number,
        v.visit_type,
        v.diagnosis,
        v.symptoms,
        v.notes as visit_notes,
        ph.full_name as pharmacy_name,
        -- Get prescription bill ONLY (not all bills)
        (
            SELECT id FROM patient_bills 
            WHERE visit_id = p.visit_id AND prescription_id = p.id
            ORDER BY id DESC LIMIT 1
        ) as bill_id,
        (
            SELECT bill_number FROM patient_bills 
            WHERE visit_id = p.visit_id AND prescription_id = p.id
            ORDER BY id DESC LIMIT 1
        ) as bill_number,
        (
            SELECT total_amount FROM patient_bills 
            WHERE visit_id = p.visit_id AND prescription_id = p.id
            ORDER BY id DESC LIMIT 1
        ) as bill_total,
        (
            SELECT discount_amount FROM patient_bills 
            WHERE visit_id = p.visit_id AND prescription_id = p.id
            ORDER BY id DESC LIMIT 1
        ) as bill_discount,
        (
            SELECT balance FROM patient_bills 
            WHERE visit_id = p.visit_id AND prescription_id = p.id
            ORDER BY id DESC LIMIT 1
        ) as bill_balance,
        (
            SELECT status FROM patient_bills 
            WHERE visit_id = p.visit_id AND prescription_id = p.id
            ORDER BY id DESC LIMIT 1
        ) as bill_status
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
// CHECK STATUS
// ================================================================
$is_dispensed = ($prescription['status'] === 'dispensed');
$bill_status = $prescription['bill_status'] ?? 'pending';
$is_bill_paid = ($bill_status === 'paid');

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

// If no items, create from main prescription
if (empty($prescription_items) && !empty($prescription['medication'])) {
    // Get price from inventory
    $stmt = $db->prepare("
        SELECT selling_price FROM medications_inventory 
        WHERE medication_name = ? AND branch_id = ? AND status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$prescription['medication'], $user_branch_id]);
    $price_result = $stmt->fetch(PDO::FETCH_ASSOC);
    $unit_price = $price_result['selling_price'] ?? 0;
    
    $prescription_items = [[
        'id' => 0,
        'prescription_id' => $prescription_id,
        'medication_name' => $prescription['medication'],
        'dosage' => $prescription['dosage'] ?? '',
        'frequency' => $prescription['frequency'] ?? '',
        'quantity' => $prescription['quantity'] ?? 1,
        'duration' => $prescription['duration'] ?? '',
        'route' => $prescription['route'] ?? '',
        'instructions' => $prescription['instructions'] ?? '',
        'unit_price' => $unit_price,
        'total_price' => $unit_price * ($prescription['quantity'] ?? 1)
    ]];
}

// ================================================================
// GET MEDICATION PRICES FROM INVENTORY
// ================================================================
$prescription_total = 0;
foreach ($prescription_items as &$item) {
    if ($item['unit_price'] == 0) {
        $stmt = $db->prepare("
            SELECT selling_price FROM medications_inventory 
            WHERE medication_name = ? AND branch_id = ? AND status = 'active'
            LIMIT 1
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

// ================================================================
// GET BILL DETAILS - PRESCRIPTION BILL ONLY
// ================================================================
$bill_items = [];
$bill_total = 0;
$bill_discount = 0;
$bill_balance = 0;
$bill_id = $prescription['bill_id'] ?? 0;
$bill_number = $prescription['bill_number'] ?? '';

if ($bill_id > 0) {
    // Get only medication items from this bill
    $stmt = $db->prepare("
        SELECT * FROM bill_items 
        WHERE bill_id = ? AND item_type = 'medication' AND status != 'cancelled'
        ORDER BY id ASC
    ");
    $stmt->execute([$bill_id]);
    $bill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $bill_total = (float)($prescription['bill_total'] ?? 0);
    $bill_discount = (float)($prescription['bill_discount'] ?? 0);
    $bill_balance = (float)($prescription['bill_balance'] ?? 0);
}

// ================================================================
// HANDLE FORM SUBMISSIONS
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // ================================================================
    // 1. CONFIRM PRESCRIPTION - Apply discount and send bill to Cashier
    // ================================================================
    if ($action === 'confirm_prescription') {
        $discount_amount = isset($_POST['discount_amount']) ? floatval(str_replace(',', '', $_POST['discount_amount'])) : 0;
        $notes = trim($_POST['notes'] ?? '');
        
        if ($discount_amount < 0) $discount_amount = 0;
        if ($discount_amount > $prescription_total) $discount_amount = $prescription_total;
        
        try {
            $db->beginTransaction();
            
            // If bill exists, update with discount
            if ($bill_id > 0) {
                $new_balance = $prescription_total - $discount_amount;
                if ($new_balance < 0) $new_balance = 0;
                
                // Update bill - status becomes 'pending' for Cashier
                $stmt = $db->prepare("
                    UPDATE patient_bills 
                    SET total_amount = ?,
                        discount_amount = ?,
                        balance = ?,
                        status = 'pending',
                        updated_at = NOW()
                    WHERE id = ? AND prescription_id = ?
                ");
                $stmt->execute([$prescription_total, $discount_amount, $new_balance, $bill_id, $prescription_id]);
                
            } else {
                // Create new prescription bill
                $bill_number_new = 'BILL-PRES-' . date('Ymd') . '-' . str_pad($prescription['patient_id'], 4, '0', STR_PAD_LEFT) . '-' . rand(100, 999);
                $final_amount = $prescription_total - $discount_amount;
                
                $stmt = $db->prepare("
                    INSERT INTO patient_bills (
                        bill_number, patient_id, visit_id, prescription_id,
                        subtotal, total_amount, discount_amount, balance, 
                        status, created_by, branch_id,
                        created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW(), NOW())
                ");
                $stmt->execute([
                    $bill_number_new,
                    $prescription['patient_id'],
                    $prescription['visit_id'],
                    $prescription_id,
                    $prescription_total,
                    $prescription_total,
                    $discount_amount,
                    $final_amount,
                    $user_id,
                    $user_branch_id
                ]);
                $bill_id = $db->lastInsertId();
                
                // Create bill items for this prescription
                foreach ($prescription_items as $item) {
                    $item_total = (float)($item['total_price'] ?? $item['unit_price'] * $item['quantity'] ?? 0);
                    $stmt = $db->prepare("
                        INSERT INTO bill_items (
                            bill_id, item_type, item_name, 
                            quantity, unit_price, total_price,
                            payment_status, is_paid, status, created_at
                        ) VALUES (?, 'medication', ?, ?, ?, ?, 'pending', 0, 'pending', NOW())
                    ");
                    $stmt->execute([
                        $bill_id,
                        $item['medication_name'],
                        $item['quantity'],
                        $item['unit_price'],
                        $item_total
                    ]);
                }
            }
            
            // Update prescription status to 'confirmed' (waiting for payment)
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
                VALUES (?, ?, 'prescription_confirmed', ?, NOW())
            ");
            $stmt->execute([
                $user_id,
                $user_branch_id,
                "Prescription #" . $prescription['prescription_number'] . " confirmed - Bill sent to Cashier for payment"
            ]);
            
            $db->commit();
            
            $_SESSION['flash_message'] = "✅ Prescription bill sent to Cashier!";
            if ($discount_amount > 0) {
                $_SESSION['flash_message'] .= " Discount: TSh " . number_format($discount_amount, 2);
            }
            $_SESSION['flash_type'] = 'success';
            
            header('Location: pending_prescriptions.php');
            exit;
            
        } catch (Exception $e) {
            $db->rollBack();
            $message = "❌ Error: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// ================================================================
// CHECK IF BILL IS PAID - If yes, auto dispense (FIXED: records dispensed_at)
// ================================================================
if ($is_bill_paid && !$is_dispensed && $bill_id > 0) {
    try {
        $db->beginTransaction();
        
        // ✅ FIX: Update prescription status to dispensed with dispensed_at
        $stmt = $db->prepare("
            UPDATE prescriptions 
            SET status = 'dispensed',
                dispensed_at = NOW(),
                pharmacy_id = ?,
                updated_at = NOW()
            WHERE id = ? AND branch_id = ?
        ");
        $stmt->execute([$user_id, $prescription_id, $user_branch_id]);
        
        // Update medication inventory - REDUCE STOCK
        foreach ($prescription_items as $item) {
            $quantity = (int)$item['quantity'];
            $medication_name = $item['medication_name'];
            
            // Check current stock
            $stmt = $db->prepare("
                SELECT quantity, id FROM medications_inventory 
                WHERE medication_name = ? AND branch_id = ? AND status = 'active'
            ");
            $stmt->execute([$medication_name, $user_branch_id]);
            $stock = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($stock) {
                $new_quantity = $stock['quantity'] - $quantity;
                if ($new_quantity < 0) $new_quantity = 0;
                
                $stmt = $db->prepare("
                    UPDATE medications_inventory 
                    SET quantity = ?,
                        updated_at = NOW()
                    WHERE id = ? AND branch_id = ? AND status = 'active'
                ");
                $stmt->execute([$new_quantity, $stock['id'], $user_branch_id]);
                
                // Log stock movement
                $stmt = $db->prepare("
                    INSERT INTO stock_movements (
                        inventory_id, patient_id, sale_type, sale_id, 
                        quantity, previous_stock, new_stock, 
                        movement_type, performed_by, notes, created_at
                    ) VALUES (?, ?, 'prescription', ?, ?, ?, ?, 'out', ?, ?, NOW())
                ");
                $stmt->execute([
                    $stock['id'],
                    $prescription['patient_id'],
                    $prescription_id,
                    $quantity,
                    $stock['quantity'],
                    $new_quantity,
                    $user_id,
                    "Prescription #" . $prescription['prescription_number'] . " dispensed"
                ]);
            }
        }
        
        // Update bill items to paid (if not already)
        $stmt = $db->prepare("
            UPDATE bill_items 
            SET is_paid = 1, 
                payment_status = 'paid',
                paid_at = NOW()
            WHERE bill_id = ? AND item_type = 'medication'
        ");
        $stmt->execute([$bill_id]);
        
        // Update bill status
        $stmt = $db->prepare("
            UPDATE patient_bills 
            SET status = 'paid',
                updated_at = NOW()
            WHERE id = ? AND prescription_id = ?
        ");
        $stmt->execute([$bill_id, $prescription_id]);
        
        // Log activity
        $stmt = $db->prepare("
            INSERT INTO activity_logs (user_id, branch_id, action, details, created_at)
            VALUES (?, ?, 'prescription_auto_dispensed', ?, NOW())
        ");
        $stmt->execute([
            $user_id,
            $user_branch_id,
            "Prescription #" . $prescription['prescription_number'] . " auto-dispensed after payment on " . date('Y-m-d H:i:s')
        ]);
        
        $db->commit();
        
        // Refresh prescription data
        $is_dispensed = true;
        $message = "✅ Prescription auto-dispensed after payment! Stock updated.";
        $message_type = 'success';
        
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Auto-dispense error: " . $e->getMessage());
        $message = "❌ Auto-dispense error: " . $e->getMessage();
        $message_type = 'error';
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
        'partial' => '🔶 Partial',
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
// INCLUDE PHARMACY HEADER & SIDEBAR
// ================================================================
include_once '../../components/pharmacy_header.php';
include_once '../../components/pharmacy_sidebar.php';

// ================================================================
// LOGO PATH
// ================================================================
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
        /* All your existing styles here... */
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
        
        /* Add all your existing CSS styles here... */
        
        /* Status Banner */
        .status-banner {
            padding: 12px 20px;
            border-radius: var(--radius);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
        }
        
        .status-banner.pending { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning); }
        .status-banner.paid { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
        .status-banner.dispensed { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
        .status-banner.info { background: var(--primary-bg); color: var(--primary); border: 1px solid var(--primary); }
        
        /* Cards */
        .card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            padding: 24px 28px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }
        
        .card:hover {
            border-color: var(--primary-light);
            box-shadow: var(--shadow-md);
        }
        
        .card-title {
            font-size: 1rem;
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
            font-size: 1.1rem;
        }
        
        .card-title .badge-count {
            background: var(--primary);
            color: white;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        
        .badge-status {
            display: inline-block;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        
        .badge-warning { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning); }
        .badge-info { background: var(--primary-bg); color: var(--primary); border: 1px solid var(--primary); }
        .badge-success { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
        .badge-danger { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger); }
        
        .detail-row {
            display: flex;
            padding: 6px 0;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.85rem;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            font-weight: 600;
            color: var(--text-secondary);
            width: 140px;
            flex-shrink: 0;
        }
        
        .detail-value {
            flex: 1;
            color: var(--text-primary);
        }
        
        .table-wrap {
            overflow-x: auto;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
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
        
        .discount-section {
            background: var(--success-bg);
            border: 2px solid var(--success);
            border-radius: var(--radius);
            padding: 20px 24px;
            margin-top: 16px;
        }
        
        .discount-section .discount-title {
            font-weight: 600;
            color: var(--success-dark);
            font-size: 0.95rem;
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
            font-size: 0.85rem;
            color: var(--text-primary);
            white-space: nowrap;
        }
        
        .discount-section .form-group .amount-input-wrap {
            position: relative;
            display: inline-block;
        }
        
        .discount-section .form-group .amount-input-wrap .currency-prefix {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 600;
        }
        
        .discount-section .form-group .amount-input-wrap input {
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
        
        .discount-section .form-group .amount-input-wrap input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
        }
        
        .discount-section .form-group textarea {
            padding: 8px 12px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.85rem;
            background: var(--bg-card);
            color: var(--text-primary);
            outline: none;
            transition: var(--transition);
            width: 100%;
            resize: vertical;
            min-height: 60px;
            font-family: inherit;
        }
        
        .discount-section .form-group textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 20px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.85rem;
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
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.3);
        }
        
        .btn-warning-custom {
            background: var(--warning);
            color: white;
        }
        
        .btn-warning-custom:hover {
            background: #B45309;
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
            font-size: 1rem;
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
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
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        }
        
        .toast-custom.show {
            transform: translateY(0);
            opacity: 1;
        }
        
        .toast-custom.success { background: var(--success); }
        .toast-custom.error { background: var(--danger); }
        .toast-custom.info { background: var(--primary); }
        .toast-custom.warning { background: var(--warning); }
        
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
            font-size: 1.2rem;
            font-weight: 700;
            font-family: monospace;
            margin-top: 2px;
        }
        
        .bill-summary-item .value.green { color: var(--success); }
        .bill-summary-item .value.red { color: var(--danger); }
        .bill-summary-item .value.blue { color: var(--primary); }
        .bill-summary-item .value.orange { color: var(--warning); }
        
        @media (max-width: 768px) {
            .detail-row { flex-direction: column; }
            .detail-label { width: 100%; margin-bottom: 2px; }
            .discount-section .form-group { flex-direction: column; align-items: stretch; }
            .discount-section .form-group .amount-input-wrap input { width: 100%; }
            .bill-summary-grid { grid-template-columns: 1fr 1fr; }
            .items-table { font-size: 0.7rem; }
            .card { padding: 16px; }
            .btn { width: 100%; justify-content: center; }
            .btn-lg { padding: 10px 20px; }
        }
        
        @media (max-width: 480px) {
            .bill-summary-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- ================================================================ -->
<!-- TOP NAVIGATION -->
<!-- ================================================================ -->
<nav class="top-nav">
    <div class="flex items-center gap-4 flex-1">
        <button id="sidebarToggle" class="lg:hidden icon-btn">
            <i class="fas fa-bars text-lg"></i>
        </button>
        
        <div class="search-wrapper">
            <i class="fas fa-search text-gray-400 ml-3"></i>
            <input type="text" id="searchInput" placeholder="Search prescriptions...">
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

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-prescription-bottle"></i>
                Dispense Prescription
                <span class="badge-display"><?= htmlspecialchars($prescription['prescription_number'] ?? 'N/A') ?></span>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-user"></i>
                Patient: <strong><?= htmlspecialchars($prescription['patient_name'] ?? 'Unknown') ?></strong>
                <span class="text-xs text-gray-400 ml-2">
                    (<?= htmlspecialchars($prescription['patient_code'] ?? 'N/A') ?>)
                </span>
                <span class="text-xs text-gray-400 ml-2">
                    <i class="fas fa-stethoscope"></i> Dr. <?= htmlspecialchars($prescription['doctor_name'] ?? 'Not Assigned') ?>
                </span>
                <span class="text-xs text-gray-400 ml-2">
                    <i class="fas fa-calendar"></i> <?= date('d/m/Y', strtotime($prescription['created_at'])) ?>
                </span>
                <?php if ($is_dispensed && !empty($prescription['dispensed_at'])): ?>
                    <span class="text-xs text-green-400 ml-2">
                        <i class="fas fa-check-circle"></i> Dispensed: <?= formatDate($prescription['dispensed_at']) ?>
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="pending_prescriptions.php" class="btn-outline-light">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="p-4 rounded-xl mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200 dark:bg-green-900/20 dark:text-green-300 dark:border-green-800' : ($message_type === 'warning' ? 'bg-yellow-100 text-yellow-700 border border-yellow-200 dark:bg-yellow-900/20 dark:text-yellow-300 dark:border-yellow-800' : 'bg-red-100 text-red-700 border border-red-200 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800') ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle') ?> mr-2"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- STATUS BANNER -->
    <!-- ================================================================ -->
    <?php if ($is_dispensed): ?>
        <div class="status-banner dispensed">
            <i class="fas fa-check-circle fa-lg"></i>
            ✅ Prescription dispensed on <?= !empty($prescription['dispensed_at']) ? formatDate($prescription['dispensed_at']) : date('d/m/Y H:i') ?>
            <?php if (!empty($prescription['pharmacy_name'])): ?>
                <span class="text-xs">by <?= htmlspecialchars($prescription['pharmacy_name']) ?></span>
            <?php endif; ?>
        </div>
    <?php elseif ($is_bill_paid): ?>
        <div class="status-banner paid">
            <i class="fas fa-check-circle fa-lg"></i>
            💰 Bill paid. Prescription will be auto-dispensed.
        </div>
    <?php elseif ($bill_id > 0): ?>
        <div class="status-banner pending">
            <i class="fas fa-clock fa-lg"></i>
            ⏳ Bill sent to Cashier. Waiting for payment.
        </div>
    <?php else: ?>
        <div class="status-banner info">
            <i class="fas fa-info-circle fa-lg"></i>
            📋 Create prescription bill and send to Cashier.
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
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px 20px;">
            <div class="detail-row"><span class="detail-label">Full Name</span><span class="detail-value"><strong><?= htmlspecialchars($prescription['patient_name'] ?? 'N/A') ?></strong></span></div>
            <div class="detail-row"><span class="detail-label">Patient ID</span><span class="detail-value"><?= htmlspecialchars($prescription['patient_code'] ?? 'N/A') ?></span></div>
            <div class="detail-row"><span class="detail-label">Gender</span><span class="detail-value"><?= htmlspecialchars($prescription['gender'] ?? 'N/A') ?></span></div>
            <div class="detail-row"><span class="detail-label">Date of Birth</span><span class="detail-value"><?= !empty($prescription['date_of_birth']) ? date('d/m/Y', strtotime($prescription['date_of_birth'])) : 'N/A' ?></span></div>
            <div class="detail-row"><span class="detail-label">Phone</span><span class="detail-value"><?= htmlspecialchars($prescription['phone'] ?? 'N/A') ?></span></div>
            <div class="detail-row"><span class="detail-label">Email</span><span class="detail-value"><?= htmlspecialchars($prescription['email'] ?? 'N/A') ?></span></div>
            <div class="detail-row"><span class="detail-label">Blood Group</span><span class="detail-value"><?= htmlspecialchars($prescription['blood_group'] ?? 'N/A') ?></span></div>
            <div class="detail-row"><span class="detail-label">Allergies</span><span class="detail-value"><?= htmlspecialchars($prescription['allergies'] ?? 'None') ?></span></div>
            <div class="detail-row" style="grid-column: span 2;"><span class="detail-label">Address</span><span class="detail-value"><?= htmlspecialchars($prescription['address'] ?? 'N/A') ?></span></div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PRESCRIPTION DETAILS -->
    <!-- ================================================================ -->
    <div class="card">
        <h3 class="card-title">
            <i class="fas fa-prescription"></i>
            Prescription Details
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
        
        <div class="table-wrap" style="margin-top:12px;">
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width:35%;">Medication</th>
                        <th style="width:12%; text-align:center;">Qty</th>
                        <th style="width:15%; text-align:right;">Unit Price</th>
                        <th style="width:18%; text-align:right;">Total</th>
                        <th style="width:10%; text-align:center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($prescription_items as $item): ?>
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
                                <?php if ($is_dispensed): ?>
                                    <span class="badge-status badge-success">✅ Dispensed</span>
                                <?php elseif ($is_bill_paid): ?>
                                    <span class="badge-status badge-success">💳 Paid</span>
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
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- PRESCRIPTION BILL SUMMARY (ONLY PRESCRIPTION BILL) -->
    <!-- ================================================================ -->
    <?php if ($bill_id > 0): ?>
    <div class="card" style="border-color:<?= $is_bill_paid ? 'var(--success)' : 'var(--warning)' ?>;border-left:4px solid <?= $is_bill_paid ? 'var(--success)' : 'var(--warning)' ?>;">
        <h3 class="card-title">
            <i class="fas fa-receipt" style="color:<?= $is_bill_paid ? 'var(--success)' : 'var(--warning)' ?>;"></i>
            Prescription Bill
            <span class="badge-status <?= getBillStatusBadgeClass($bill_status) ?>">
                <?= getBillStatusLabel($bill_status) ?>
            </span>
            <?php if ($bill_number): ?>
            <span class="badge-count">#<?= htmlspecialchars($bill_number) ?></span>
            <?php endif; ?>
            <span class="badge-count" style="background:var(--warning);">
                <i class="fas fa-pills"></i> Prescription Only
            </span>
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
    </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- ACTION FORM -->
    <!-- ================================================================ -->
    <?php if (!$is_dispensed && !$is_bill_paid): ?>
    <form method="POST" action="" id="actionForm">
        <div class="card">
            <h3 class="card-title">
                <i class="fas fa-cogs"></i>
                Confirm Prescription
                <span class="badge-count" style="background:var(--primary);">
                    <i class="fas fa-plus-circle"></i> Send to Cashier
                </span>
            </h3>
            
            <!-- ================================================================ -->
            <!-- DISCOUNT SECTION -->
            <!-- ================================================================ -->
            <div class="discount-section">
                <div class="discount-title">
                    <i class="fas fa-percent"></i>
                    Apply Discount
                    <span class="text-xs text-gray-500 font-normal">
                        (Enter amount in TSh)
                    </span>
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
                            Max discount: TSh <?= number_format($prescription_total, 2) ?>
                        </span>
                    </div>
                </div>
                
                <div class="form-group" style="margin-top:10px;">
                    <label><i class="fas fa-sticky-note"></i> Notes:</label>
                    <textarea name="notes" placeholder="Optional notes..." rows="2"></textarea>
                </div>
            </div>
            
            <!-- ================================================================ -->
            <!-- PAYMENT SUMMARY -->
            <!-- ================================================================ -->
            <div style="margin-top:16px;padding:16px 20px;background:var(--bg-body);border-radius:var(--radius);border:2px solid var(--border-color);">
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:12px;">
                    <div>
                        <div class="text-xs text-gray-400 font-semibold uppercase">Total</div>
                        <div class="text-lg font-bold text-blue-600 font-mono" id="displayTotal">
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
                        <div class="text-xs text-gray-400 font-semibold uppercase">Balance</div>
                        <div class="text-lg font-bold text-red-600 font-mono" id="displayNetTotal">
                            <?= number_format($prescription_total - $bill_discount, 2) ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- ================================================================ -->
            <!-- ACTION BUTTONS -->
            <!-- ================================================================ -->
            <div style="display:flex;flex-wrap:wrap;gap:12px;margin-top:16px;padding-top:16px;border-top:2px solid var(--border-color);">
                
                <?php if ($bill_id > 0): ?>
                    <button type="submit" name="action" value="confirm_prescription" class="btn btn-warning-custom btn-lg"
                            onclick="return confirm('Update prescription bill with discount?\n\nTotal: TSh <?= number_format($prescription_total, 2) ?>\nDiscount: TSh ' + getDiscountDisplay() + '\nBalance: TSh ' + getNetTotalDisplay() + '\n\nBill will be sent to Cashier for payment.');">
                        <i class="fas fa-save"></i> Update & Send to Cashier
                    </button>
                <?php else: ?>
                    <button type="submit" name="action" value="confirm_prescription" class="btn btn-primary btn-lg"
                            onclick="return confirm('Create prescription bill and send to Cashier?\n\nTotal: TSh <?= number_format($prescription_total, 2) ?>\nDiscount: TSh ' + getDiscountDisplay() + '\nBalance: TSh ' + getNetTotalDisplay() + '\n\nPatient: <?= addslashes($prescription['patient_name'] ?? 'Unknown') ?>');">
                        <i class="fas fa-file-invoice"></i> Create Bill & Send to Cashier
                    </button>
                <?php endif; ?>
                
                <span class="text-xs text-blue-600 self-center">
                    <i class="fas fa-info-circle"></i> Bill will be sent to Cashier for payment.
                </span>
                
                <a href="pending_prescriptions.php" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </div>
    </form>
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
            <span id="footerTimestamp">Last updated: <?= date('H:i:s') ?></span>
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
    
    // ================================================================
    // FORMAT AMOUNT WITH COMMAS
    // ================================================================
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
        return discountInput.value || '0.00';
    }
    
    function getNetTotalDisplay() {
        var discountInput = document.getElementById('discountAmount');
        var discount = getRawValue(discountInput);
        var netTotal = prescriptionTotal - discount;
        if (netTotal < 0) netTotal = 0;
        return netTotal.toFixed(2);
    }
    
    function updateTotals() {
        var discountInput = document.getElementById('discountAmount');
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
        document.getElementById('displayNetTotal').textContent = netTotal.toFixed(2);
    }
    
    // ================================================================
    // DARK MODE
    // ================================================================
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

    // ================================================================
    // SIDEBAR TOGGLE
    // ================================================================
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

    // ================================================================
    // DATE & TIME
    // ================================================================
    function updateDateTime() {
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
        });
        document.getElementById('currentDateTime').textContent = dateStr + ' • ' + timeStr;
        document.getElementById('footerTimestamp').textContent = 'Last updated: ' + timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ================================================================
    // INIT
    // ================================================================
    document.addEventListener('DOMContentLoaded', function() {
        var discountInput = document.getElementById('discountAmount');
        if (discountInput) {
            if (currentDiscount > 0) {
                discountInput.value = currentDiscount.toFixed(2);
                discountInput.dataset.rawValue = currentDiscount;
            } else {
                discountInput.value = '0.00';
                discountInput.dataset.rawValue = 0;
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

    console.log('%c💊 Braick - Dispense Prescription (FIXED)', 'font-size:18px; font-weight:bold; color:#059669;');
    console.log('%c👤 User: <?= htmlspecialchars($user_full_name) ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c📋 Prescription: <?= htmlspecialchars($prescription['prescription_number'] ?? 'N/A') ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c👤 Patient: <?= htmlspecialchars($prescription['patient_name'] ?? 'Unknown') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c💰 Total: <?= number_format($prescription_total, 2) ?>', 'font-size:13px; color:#059669;');
    console.log('%c💳 Discount: <?= number_format($bill_discount, 2) ?>', 'font-size:13px; color:#D97706;');
    console.log('%c📊 Bill Status: <?= $bill_status ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c✅ FIXED: Session management & login protection', 'font-size:13px; color:#34D399;');
</script>

</body>
</html>