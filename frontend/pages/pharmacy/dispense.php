<?php
// ================================================================
// FILE: frontend/pages/pharmacy/dispense.php
// PHARMACY - DISPENSE PRESCRIPTION
// FIXED: Confirm button shows when pending (even if bill exists)
// BRAICK DISPENSARY
// ================================================================

session_start();

// ================================================================
// FORCE SESSION - Pharmacy
// ================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pharmacy') {
    $_SESSION['user_id'] = 9;
    $_SESSION['full_name'] = 'Pharmacy Dodoma';
    $_SESSION['role'] = 'pharmacy';
    $_SESSION['branch_id'] = 1;
    $_SESSION['branch_name'] = 'Dodoma';
    $_SESSION['username'] = 'pharm.dodoma';
    $_SESSION['is_admin'] = false;
}

$user_branch_id = $_SESSION['branch_id'] ?? 1;
$branch_name = $_SESSION['branch_name'] ?? 'Dodoma';
$user_full_name = $_SESSION['full_name'] ?? 'Pharmacy';
$user_id = $_SESSION['user_id'] ?? 9;

// ================================================================
// GET PRESCRIPTION ID
// ================================================================
$prescription_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($prescription_id <= 0) {
    header('Location: pending_prescriptions.php?error=invalid_id');
    exit;
}

// ================================================================
// PATH SAHIHI
// ================================================================
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/database.php';

$message = '';
$message_type = '';
$currency = 'TSh';
$prescription = null;
$items = [];
$stock_warnings = [];
$can_dispense = true;
$bill_id = null;
$bill_status = null;
$subtotal = 0;

try {
    $db = getDB();
    
    // ================================================================
    // GET PRESCRIPTION DETAILS
    // ================================================================
    $stmt = $db->prepare("
        SELECT p.*, 
               pat.full_name as patient_name,
               pat.patient_id as patient_code,
               pat.phone,
               pat.date_of_birth,
               pat.gender,
               pat.address,
               u.full_name as doctor_name,
               u.specialty,
               v.visit_number,
               v.visit_type,
               b.name as branch_name
        FROM prescriptions p
        LEFT JOIN patients pat ON p.patient_id = pat.id
        LEFT JOIN users u ON p.doctor_id = u.id
        LEFT JOIN visits v ON p.visit_id = v.id
        LEFT JOIN branches b ON p.branch_id = b.id
        WHERE p.id = ? AND p.branch_id = ?
    ");
    $stmt->execute([$prescription_id, $user_branch_id]);
    $prescription = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$prescription) {
        header('Location: pending_prescriptions.php?error=not_found');
        exit;
    }
    
    // ================================================================
    // CHECK IF PRESCRIPTION IS ALREADY DISPENSED
    // ================================================================
    if ($prescription['status'] === 'dispensed') {
        header('Location: view_prescription.php?id=' . $prescription_id . '&already_dispensed=1');
        exit;
    }
    
    // ================================================================
    // GET PRESCRIPTION ITEMS
    // ================================================================
    $stmt = $db->prepare("
        SELECT * FROM prescription_items 
        WHERE prescription_id = ?
        ORDER BY id
    ");
    $stmt->execute([$prescription_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If no items in prescription_items, create from main table
    if (count($items) == 0 && !empty($prescription['medication']) && !empty($prescription['quantity'])) {
        $stmt = $db->prepare("
            SELECT selling_price FROM medications_inventory 
            WHERE medication_name = ? AND branch_id = ? AND status = 'active' AND quantity > 0
            LIMIT 1
        ");
        $stmt->execute([$prescription['medication'], $user_branch_id]);
        $price_result = $stmt->fetch(PDO::FETCH_ASSOC);
        $unit_price = $price_result['selling_price'] ?? 0;
        
        $items[] = [
            'id' => 0,
            'prescription_id' => $prescription_id,
            'medication_name' => $prescription['medication'],
            'dosage' => $prescription['dosage'] ?? '',
            'frequency' => $prescription['frequency'] ?? '',
            'quantity' => (int)$prescription['quantity'],
            'duration' => $prescription['duration'] ?? '',
            'route' => $prescription['route'] ?? '',
            'instructions' => $prescription['instructions'] ?? '',
            'unit_price' => $unit_price,
            'total_price' => $unit_price * (int)$prescription['quantity'],
            'created_at' => $prescription['created_at'] ?? date('Y-m-d H:i:s')
        ];
    }
    
    if (count($items) == 0) {
        header('Location: pending_prescriptions.php?error=no_items');
        exit;
    }
    
    // Calculate subtotal
    $subtotal = 0;
    foreach ($items as $item) {
        $price = $item['unit_price'] ?? 0;
        $subtotal += $price * $item['quantity'];
    }
    
    // ================================================================
    // CHECK IF BILL EXISTS
    // ================================================================
    if (!empty($prescription['visit_id'])) {
        $stmt = $db->prepare("
            SELECT id, status FROM patient_bills 
            WHERE visit_id = ? AND branch_id = ?
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$prescription['visit_id'], $user_branch_id]);
        $bill = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($bill) {
            $bill_id = $bill['id'];
            $bill_status = $bill['status'];
        }
    }
    
    // ================================================================
    // CHECK INVENTORY
    // ================================================================
    foreach ($items as &$item) {
        $stmt = $db->prepare("
            SELECT id, medication_name, quantity, selling_price, unit, batch_number, expiry_date
            FROM medications_inventory 
            WHERE medication_name = ? AND branch_id = ? AND status = 'active'
            ORDER BY expiry_date ASC
        ");
        $stmt->execute([$item['medication_name'], $user_branch_id]);
        $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $item['inventory'] = $inventory;
        $item['available_stock'] = 0;
        $item['batch_info'] = [];
        
        foreach ($inventory as $inv) {
            $item['available_stock'] += $inv['quantity'];
            $item['batch_info'][] = [
                'id' => $inv['id'],
                'quantity' => $inv['quantity'],
                'batch_number' => $inv['batch_number'] ?? 'N/A',
                'expiry_date' => $inv['expiry_date'] ?? 'N/A',
                'selling_price' => $inv['selling_price'] ?? 0
            ];
        }
        
        if ($item['available_stock'] < $item['quantity']) {
            $can_dispense = false;
            $stock_warnings[] = [
                'medication' => $item['medication_name'],
                'required' => $item['quantity'],
                'available' => $item['available_stock']
            ];
        }
        
        foreach ($inventory as $inv) {
            if (!empty($inv['expiry_date'])) {
                $expiry = strtotime($inv['expiry_date']);
                $today = strtotime(date('Y-m-d'));
                $days_diff = floor(($expiry - $today) / (60 * 60 * 24));
                
                if ($days_diff < 0) {
                    $item['has_expired'] = true;
                    $can_dispense = false;
                    $stock_warnings[] = [
                        'medication' => $item['medication_name'],
                        'expired' => true,
                        'batch' => $inv['batch_number'] ?? 'N/A'
                    ];
                } elseif ($days_diff < 30) {
                    $item['expiring_soon'] = true;
                }
            }
        }
    }
    unset($item);
    
    // ================================================================
    // GET SYSTEM SETTINGS
    // ================================================================
    $settings = [];
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $currency = $settings['currency'] ?? 'TSh';
    
    // ================================================================
    // HANDLE ACTIONS
    // ================================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        // ================================================================
        // CONFIRM - Create/Update Bill and Send to Cashier
        // ================================================================
        if ($action === 'confirm') {
            if ($prescription['status'] !== 'pending') {
                $message = "❌ Prescription is already " . $prescription['status'];
                $message_type = 'error';
            } elseif (empty($prescription['visit_id'])) {
                $message = "❌ No visit found for this prescription.";
                $message_type = 'error';
            } else {
                try {
                    $db->beginTransaction();
                    
                    // Get discount amount from form
                    $discount_amount = isset($_POST['discount_amount']) ? (float)$_POST['discount_amount'] : 0;
                    $discount_amount = max(0, $discount_amount);
                    
                    // Calculate total after discount
                    $total_amount = max(0, $subtotal - $discount_amount);
                    
                    // Generate bill number
                    $bill_number = 'BILL-' . date('Ymd') . '-' . str_pad($prescription['patient_id'], 6, '0', STR_PAD_LEFT);
                    $stmt = $db->prepare("SELECT id FROM patient_bills WHERE bill_number = ?");
                    $stmt->execute([$bill_number]);
                    if ($stmt->fetch()) {
                        $bill_number = 'BILL-' . date('Ymd') . '-' . str_pad($prescription['patient_id'], 6, '0', STR_PAD_LEFT) . '-' . rand(100, 999);
                    }
                    
                    // Check if bill already exists
                    if ($bill_id) {
                        // Update existing bill
                        $stmt = $db->prepare("
                            UPDATE patient_bills 
                            SET subtotal = ?, discount_amount = ?, total_amount = ?, balance = ?,
                                updated_at = NOW()
                            WHERE id = ? AND branch_id = ?
                        ");
                        $stmt->execute([
                            $subtotal,
                            $discount_amount,
                            $total_amount,
                            $total_amount,
                            $bill_id,
                            $user_branch_id
                        ]);
                        
                        // Delete existing bill items
                        $stmt = $db->prepare("DELETE FROM bill_items WHERE bill_id = ?");
                        $stmt->execute([$bill_id]);
                        
                        $new_bill_id = $bill_id;
                    } else {
                        // Insert new bill
                        $stmt = $db->prepare("
                            INSERT INTO patient_bills (
                                bill_number, patient_id, visit_id, 
                                subtotal, discount_amount, total_amount, balance, status, 
                                created_by, branch_id,
                                created_at, updated_at
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW(), NOW())
                        ");
                        $stmt->execute([
                            $bill_number,
                            $prescription['patient_id'],
                            $prescription['visit_id'],
                            $subtotal,
                            $discount_amount,
                            $total_amount,
                            $total_amount,
                            $user_id,
                            $user_branch_id
                        ]);
                        $new_bill_id = $db->lastInsertId();
                    }
                    
                    // Insert bill items
                    foreach ($items as $item) {
                        $price = $item['unit_price'] ?? 0;
                        $total = $price * $item['quantity'];
                        $stmt = $db->prepare("
                            INSERT INTO bill_items (
                                bill_id, item_type, item_name, 
                                quantity, unit_price, total_price,
                                payment_status, is_paid, status, created_at
                            ) VALUES (?, 'medication', ?, ?, ?, ?, 'pending', 0, 'pending', NOW())
                        ");
                        $stmt->execute([
                            $new_bill_id,
                            $item['medication_name'],
                            $item['quantity'],
                            $price,
                            $total
                        ]);
                    }
                    
                    // Update prescription status to 'confirmed'
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
                        INSERT INTO activity_logs (user_id, action, details, created_at)
                        VALUES (?, 'prescription_confirmed', ?, NOW())
                    ");
                    $stmt->execute([
                        $user_id,
                        "Prescription #" . $prescription['prescription_number'] . " confirmed - Bill #" . $bill_number . " sent to Cashier"
                    ]);
                    
                    $db->commit();
                    
                    $bill_id = $new_bill_id;
                    $bill_status = 'pending';
                    $is_confirmed = true;
                    $message = "✅ Prescription confirmed! Bill sent to Cashier.";
                    $message_type = 'success';
                    
                    // Refresh prescription data
                    $stmt = $db->prepare("SELECT status FROM prescriptions WHERE id = ?");
                    $stmt->execute([$prescription_id]);
                    $prescription['status'] = $stmt->fetch(PDO::FETCH_ASSOC)['status'];
                    
                } catch (Exception $e) {
                    $db->rollBack();
                    $message = "❌ Error: " . $e->getMessage();
                    $message_type = 'error';
                }
            }
        }
        
        // ================================================================
        // DISPENSE - Give Medication and Update Stock
        // ================================================================
        if ($action === 'dispense') {
            if ($prescription['status'] !== 'confirmed') {
                $message = "❌ Please confirm prescription first before dispensing.";
                $message_type = 'error';
            } elseif ($bill_status !== 'paid') {
                $message = "⚠️ Cannot dispense. Bill is not paid yet. Please wait for payment confirmation.";
                $message_type = 'warning';
            } else {
                try {
                    $db->beginTransaction();
                    
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
                    
                    // Update inventory for each item
                    foreach ($items as $item) {
                        $quantity_to_deduct = $item['quantity'];
                        
                        // Get batches with FIFO (oldest first)
                        $stmt = $db->prepare("
                            SELECT id, quantity FROM medications_inventory 
                            WHERE medication_name = ? AND branch_id = ? AND status = 'active' AND quantity > 0
                            ORDER BY expiry_date ASC
                        ");
                        $stmt->execute([$item['medication_name'], $user_branch_id]);
                        $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($batches as $batch) {
                            if ($quantity_to_deduct <= 0) break;
                            
                            $deduct = min($batch['quantity'], $quantity_to_deduct);
                            
                            $stmt = $db->prepare("
                                UPDATE medications_inventory 
                                SET quantity = quantity - ? 
                                WHERE id = ? AND branch_id = ?
                            ");
                            $stmt->execute([$deduct, $batch['id'], $user_branch_id]);
                            
                            // Record stock movement
                            $stmt = $db->prepare("
                                INSERT INTO stock_movements (
                                    inventory_id, sale_type, sale_id, quantity,
                                    previous_stock, new_stock, movement_type,
                                    performed_by, notes, created_at
                                ) VALUES (?, 'prescription', ?, ?, ?, ?, 'out', ?, ?, NOW())
                            ");
                            $stmt->execute([
                                $batch['id'],
                                $prescription_id,
                                $deduct,
                                $batch['quantity'],
                                $batch['quantity'] - $deduct,
                                $user_id,
                                "Dispensed - " . $item['medication_name']
                            ]);
                            
                            $quantity_to_deduct -= $deduct;
                        }
                    }
                    
                    // Log activity
                    $stmt = $db->prepare("
                        INSERT INTO activity_logs (user_id, action, details, created_at)
                        VALUES (?, 'prescription_dispensed', ?, NOW())
                    ");
                    $stmt->execute([
                        $user_id,
                        "Prescription #" . $prescription['prescription_number'] . " dispensed for " . $prescription['patient_name']
                    ]);
                    
                    $db->commit();
                    
                    $message = "✅ Prescription dispensed successfully! Stock updated.";
                    $message_type = 'success';
                    
                    echo '<script>setTimeout(function(){ window.location.href = "view_prescription.php?id=' . $prescription_id . '&dispensed=1"; }, 2000);</script>';
                    
                } catch (Exception $e) {
                    $db->rollBack();
                    $message = "❌ Error dispensing: " . $e->getMessage();
                    $message_type = 'error';
                }
            }
        }
        
        // ================================================================
        // CANCEL
        // ================================================================
        if ($action === 'cancel') {
            if ($prescription['status'] === 'dispensed') {
                $message = "❌ Cannot cancel a dispensed prescription.";
                $message_type = 'error';
            } else {
                $cancel_reason = trim($_POST['cancel_reason'] ?? 'Cancelled by pharmacy');
                
                $stmt = $db->prepare("
                    UPDATE prescriptions 
                    SET status = 'cancelled', 
                        notes = CONCAT(IFNULL(notes, ''), ' | Cancelled: ', ?),
                        updated_at = NOW()
                    WHERE id = ? AND branch_id = ?
                ");
                $stmt->execute([$cancel_reason, $prescription_id, $user_branch_id]);
                
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (user_id, action, details, created_at)
                    VALUES (?, 'prescription_cancelled', ?, NOW())
                ");
                $stmt->execute([
                    $user_id,
                    "Prescription #" . $prescription['prescription_number'] . " cancelled. Reason: " . $cancel_reason
                ]);
                
                $message = "✅ Prescription cancelled successfully!";
                $message_type = 'success';
                
                echo '<script>setTimeout(function(){ window.location.href = "pending_prescriptions.php?cancelled=1"; }, 1500);</script>';
            }
        }
    }
    
} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = 'error';
}

// ================================================================
// HELPER FUNCTIONS
// ================================================================
function formatDate($datetime) {
    if (empty($datetime)) return 'N/A';
    return date('d/m/Y', strtotime($datetime));
}

function formatCurrency($amount) {
    return 'TSh ' . number_format($amount, 0);
}

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
        'confirmed' => '✅ Confirmed',
        'dispensed' => '💊 Dispensed',
        'cancelled' => '❌ Cancelled'
    ];
    return $map[$status] ?? ucfirst($status);
}

// ================================================================
// DETERMINE BUTTON STATES - FIXED!
// ================================================================
$is_pending = ($prescription['status'] ?? '') === 'pending';
$is_confirmed = ($prescription['status'] ?? '') === 'confirmed';
$is_dispensed = ($prescription['status'] ?? '') === 'dispensed';
$is_cancelled = ($prescription['status'] ?? '') === 'cancelled';
$is_paid = ($bill_status ?? '') === 'paid';
$bill_exists = ($bill_id !== null);

// BUTTON STATES - FIXED:
// 1. Show CONFIRM if: pending (hata kama bill ipo, lakini sio paid)
$show_confirm = $is_pending && (!$bill_exists || $bill_status !== 'paid');

// 2. Show DISPENSE if: confirmed AND bill is paid
$show_dispense = $is_confirmed && $is_paid && !$is_dispensed;

// 3. Show WAITING if: confirmed AND bill NOT paid
$show_waiting = $is_confirmed && !$is_paid && !$is_dispensed;

// 4. Show CANCEL if: pending OR (confirmed AND not dispensed)
$show_cancel = ($is_pending || $is_confirmed) && !$is_dispensed;

// ================================================================
// INCLUDE HEADER & SIDEBAR
// ================================================================
include_once '../../components/pharmacy_header.php';
include_once '../../components/pharmacy_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === 'true' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dispense Prescription - Braick Dispensary</title>
    
    <link rel="icon" href="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" type="image/png">
    <link rel="shortcut icon" href="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" type="image/png">
    
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
        
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
        
        .top-nav {
            position: fixed;
            top: 0;
            left: 270px;
            right: 0;
            height: 68px;
            background: var(--bg-nav);
            z-index: 40;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            border-bottom: 2px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .top-nav .search-wrapper {
            display: flex;
            align-items: center;
            background: var(--bg-body);
            border-radius: 10px;
            border: 2px solid var(--border-color);
            transition: all 0.3s;
            flex: 1;
            max-width: 500px;
        }
        
        .top-nav .search-wrapper:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.15);
        }
        
        .top-nav .search-wrapper input {
            border: none;
            background: transparent;
            padding: 8px 14px;
            width: 100%;
            font-size: 0.85rem;
            outline: none;
            color: var(--text-primary);
        }
        
        .top-nav .search-wrapper input::placeholder {
            color: var(--text-secondary);
        }
        
        .top-nav .search-wrapper .search-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 0 10px 10px 0;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .top-nav .search-wrapper .search-btn:hover {
            background: var(--primary-dark);
        }
        
        .top-nav .datetime {
            font-size: 0.78rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .top-nav .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-color);
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .top-nav .avatar:hover {
            border-color: var(--primary);
            transform: scale(1.05);
        }
        
        .top-nav .icon-btn {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            transition: all 0.3s;
            background: transparent;
            border: none;
            cursor: pointer;
            position: relative;
        }
        
        .top-nav .icon-btn:hover {
            background: var(--bg-body);
            color: var(--primary);
        }
        
        .notif-dot {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            border: 2px solid var(--bg-nav);
            animation: pulse-dot 2s infinite;
        }
        .notif-dot.has-notif { background: var(--danger); }
        .notif-dot.no-notif { background: var(--gray-400); animation: none; }
        
        @keyframes pulse-dot {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }
        
        .dark-toggle-btn {
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 6px 12px;
            cursor: pointer;
            font-size: 0.82rem;
            color: var(--text-primary);
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .dark-toggle-btn:hover {
            border-color: var(--primary);
            background: var(--bg-card);
        }
        
        .dark-toggle-btn i { font-size: 0.9rem; }
        
        .main-content {
            margin-left: 270px;
            margin-top: 68px;
            padding: 28px 32px;
            min-height: calc(100vh - 68px);
        }
        
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
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .page-header .page-title {
            color: white;
            font-size: 1.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-title i {
            font-size: 2rem;
            opacity: 0.9;
        }
        
        .page-header .page-subtitle {
            color: rgba(255,255,255,0.85);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .page-header .page-subtitle strong {
            color: white;
            font-weight: 600;
        }
        
        .page-header .role-badge-display {
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
        
        .page-header .header-badge {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            backdrop-filter: blur(4px);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .page-header .btn-outline-light {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 8px 18px;
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
        
        .page-header .btn-outline-light:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        .role-badge-display {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            background: var(--primary-bg);
            color: var(--primary);
            text-transform: uppercase;
        }
        
        [data-theme="dark"] .role-badge-display {
            background: #1E3A5F;
            color: #6EA8FE;
        }
        
        .branch-badge-display {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            background: var(--success-bg);
            color: var(--success);
        }
        
        [data-theme="dark"] .branch-badge-display {
            background: #1A3A2A;
            color: #34D399;
        }
        
        .card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            border: 1px solid var(--border-color);
            transition: var(--transition);
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        
        .card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(11, 94, 215, 0.08);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .card-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .detail-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        
        .detail-value {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: capitalize;
        }
        
        .badge-warning { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning); }
        .badge-info { background: var(--primary-bg); color: var(--primary); border: 1px solid var(--primary); }
        .badge-success { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
        .badge-danger { background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger); }
        
        /* BUTTONS */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 28px;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.95rem;
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
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        .btn-danger:hover {
            background: var(--danger-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
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
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }
        
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid var(--border-color);
            justify-content: center;
        }
        
        .action-buttons .btn {
            min-width: 160px;
            justify-content: center;
        }
        
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
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 94, 215, 0.12);
        }
        
        .form-control:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .form-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }
        
        .form-help {
            font-size: 0.7rem;
            color: var(--text-secondary);
            margin-top: 2px;
        }
        
        .inventory-item {
            padding: 10px 14px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            margin-bottom: 8px;
            transition: var(--transition);
            background: var(--bg-card);
        }
        
        .inventory-item:hover {
            border-color: var(--primary);
        }
        
        .inventory-item .batch-select {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .inventory-item .batch-select input[type="number"] {
            width: 70px;
            padding: 4px 8px;
            border: 2px solid var(--border-color);
            border-radius: 6px;
            font-size: 0.8rem;
            outline: none;
        }
        
        .batch-tag {
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        
        .batch-tag.available { background: var(--success-bg); color: var(--success); }
        .batch-tag.expired { background: var(--danger-bg); color: var(--danger); }
        .batch-tag.expiring { background: var(--warning-bg); color: var(--warning); }
        
        .stock-warning {
            padding: 8px 12px;
            border-radius: var(--radius);
            background: var(--danger-bg);
            color: var(--danger);
            border: 1px solid var(--danger);
            margin-bottom: 8px;
            font-size: 0.8rem;
        }
        
        .status-info {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: var(--radius);
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .status-info.pending { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning); }
        .status-info.confirmed { background: var(--primary-bg); color: var(--primary); border: 1px solid var(--primary); }
        .status-info.dispensed { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
        .status-info.paid { background: var(--success-bg); color: var(--success); border: 1px solid var(--success); }
        .status-info.unpaid { background: var(--warning-bg); color: var(--warning); border: 1px solid var(--warning); }
        
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
        
        .footer {
            padding: 14px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 24px;
            text-align: center;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }
        
        .footer .footer-brand { color: var(--primary); font-weight: 600; }
        
        @media (max-width: 1024px) {
            .top-nav { left: 0; }
            .main-content { margin-left: 0; padding: 16px; }
            .top-nav .search-wrapper { max-width: 300px; }
        }
        
        @media (max-width: 768px) {
            .top-nav .search-wrapper { max-width: 180px; }
            .top-nav .datetime { display: none; }
            .page-header { padding: 16px 18px; }
            .page-header .page-title { font-size: 1.3rem; }
            .card { padding: 14px 16px; }
            .action-buttons { flex-direction: column; align-items: center; }
            .action-buttons .btn { width: 100%; min-width: 0; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .top-nav .search-wrapper { max-width: 120px; }
            .card { padding: 10px 12px; }
            .page-title { font-size: 1.1rem; }
        }
        
        .btn-transition {
            transition: all 0.5s ease;
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
            <input type="text" id="searchInput" placeholder="Search...">
            <button id="searchBtn" class="search-btn">
                <i class="fas fa-search mr-1"></i> Search
            </button>
        </div>
    </div>
    
    <div class="flex items-center gap-3">
        <span class="branch-badge-display">
            <i class="fas fa-store-alt mr-1"></i> <?= htmlspecialchars($branch_name) ?>
        </span>
        
        <span class="datetime" id="currentDateTime"></span>
        
        <button id="darkModeToggle" class="dark-toggle-btn">
            <i id="darkIcon" class="fas fa-moon"></i>
            <span id="darkText">Dark</span>
        </button>
        
        <button class="icon-btn">
            <i class="fas fa-bell text-lg"></i>
            <span class="notif-dot <?= ($unread_notifications ?? 0) > 0 ? 'has-notif' : 'no-notif' ?>"></span>
        </button>
        
        <a href="profile.php">
            <img src="<?= $logo_path ?? '/dispensary_system/frontend/assets/uploads/profiles/braick_logo.png' ?>" alt="Profile" class="avatar"
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
                <span class="role-badge-display" style="background:rgba(255,255,255,0.2);color:white;">PHARMACY</span>
                <span class="header-badge" style="background:rgba(255,255,255,0.15);">
                    <i class="fas fa-hashtag"></i> <?= htmlspecialchars($prescription['prescription_number'] ?? 'N/A') ?>
                </span>
                <?php if ($is_dispensed): ?>
                    <span class="header-badge" style="background:rgba(5,150,105,0.3);border-color:#059669;">
                        <i class="fas fa-check-circle"></i> Dispensed
                    </span>
                <?php endif; ?>
            </h1>
            <p class="page-subtitle">
                <i class="fas fa-user"></i>
                Patient: <strong><?= htmlspecialchars($prescription['patient_name'] ?? 'N/A') ?></strong>
                (<?= htmlspecialchars($prescription['patient_code'] ?? 'N/A') ?>)
                <span class="separator">|</span>
                Doctor: <strong><?= htmlspecialchars($prescription['doctor_name'] ?? 'N/A') ?></strong>
                <span class="separator">|</span>
                Status: 
                <span class="status-badge <?= getStatusBadgeClass($prescription['status'] ?? 'pending') ?>">
                    <?= getStatusLabel($prescription['status'] ?? 'pending') ?>
                </span>
                <?php if ($bill_status): ?>
                    <span class="separator">|</span>
                    Bill: 
                    <span class="status-badge <?= $is_paid ? 'badge-success' : 'badge-warning' ?>">
                        <?= $is_paid ? '✅ Paid' : '⏳ Pending Payment' ?>
                    </span>
                <?php endif; ?>
                <?php if (count($items) > 0): ?>
                    <span class="separator">|</span>
                    <span class="text-xs text-gray-400"><?= count($items) ?> item(s)</span>
                <?php endif; ?>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;">
            <a href="view_prescription.php?id=<?= $prescription_id ?>" class="btn-outline-light">
                <i class="fas fa-eye"></i> View Details
            </a>
        </div>
    </div>

    <!-- Message -->
    <?php if ($message): ?>
        <div class="p-4 rounded-xl mb-4 <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200 dark:bg-green-900/20 dark:text-green-300 dark:border-green-800' : ($message_type === 'warning' ? 'bg-yellow-100 text-yellow-700 border border-yellow-200 dark:bg-yellow-900/20 dark:text-yellow-300 dark:border-yellow-800' : 'bg-red-100 text-red-700 border border-red-200 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800') ?>" style="max-width:1200px;margin:0 auto 16px;">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle') ?> mr-2"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- PRESCRIPTION SUMMARY -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 mb-5" style="max-width:1200px;margin:0 auto;">
        <div class="card">
            <p class="detail-label">Prescription Number</p>
            <p class="detail-value font-mono"><?= htmlspecialchars($prescription['prescription_number'] ?? 'N/A') ?></p>
        </div>
        <div class="card">
            <p class="detail-label">Patient</p>
            <p class="detail-value"><?= htmlspecialchars($prescription['patient_name'] ?? 'N/A') ?></p>
            <p class="text-xs text-gray-400"><?= htmlspecialchars($prescription['patient_code'] ?? 'N/A') ?></p>
        </div>
        <div class="card">
            <p class="detail-label">Medication</p>
            <p class="detail-value"><?= htmlspecialchars($prescription['medication'] ?? 'N/A') ?></p>
            <p class="text-xs text-gray-400">Qty: <?= $prescription['quantity'] ?? 0 ?></p>
        </div>
        <div class="card">
            <p class="detail-label">Status</p>
            <p class="detail-value">
                <span class="status-badge <?= getStatusBadgeClass($prescription['status'] ?? 'pending') ?>">
                    <?= getStatusLabel($prescription['status'] ?? 'pending') ?>
                </span>
                <?php if ($bill_id): ?>
                    <span class="text-xs text-gray-400 block">Bill #<?= $bill_id ?></span>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- DISPENSE FORM -->
    <!-- ================================================================ -->
    <form method="POST" action="" id="dispenseForm" style="max-width:1200px;margin:0 auto;">
        
        <?php foreach ($items as $item): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-pills title-blue mr-2"></i>
                        <?= htmlspecialchars($item['medication_name']) ?>
                        <span class="text-sm font-normal text-gray-400">
                            (x<?= $item['quantity'] ?>)
                            <?php if (!empty($item['dosage'])): ?>
                                • <?= htmlspecialchars($item['dosage']) ?>
                            <?php endif; ?>
                            <?php if (!empty($item['frequency'])): ?>
                                • <?= htmlspecialchars($item['frequency']) ?>
                            <?php endif; ?>
                        </span>
                    </h3>
                    <span class="text-sm font-semibold <?= ($item['available_stock'] ?? 0) >= $item['quantity'] ? 'text-green-600' : 'text-red-600' ?>">
                        <?= $item['available_stock'] ?? 0 ?> available
                    </span>
                </div>
                
                <?php if (isset($item['has_expired']) && $item['has_expired']): ?>
                    <div class="stock-warning">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        ⚠️ Expired batch detected for <?= htmlspecialchars($item['medication_name']) ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($item['expiring_soon']) && $item['expiring_soon']): ?>
                    <div class="stock-warning" style="background:var(--warning-bg);color:var(--warning);border-color:var(--warning);">
                        <i class="fas fa-clock mr-2"></i>
                        ⚠️ Some batches are expiring soon (within 30 days)
                    </div>
                <?php endif; ?>
                
                <div class="mt-3">
                    <p class="text-sm font-medium text-gray-600 mb-2">Select batches to dispense (FIFO recommended):</p>
                    
                    <?php if (isset($item['batch_info']) && count($item['batch_info']) > 0): ?>
                        <div class="space-y-2">
                            <?php 
                            $remaining = $item['quantity'];
                            $counter = 0;
                            foreach ($item['batch_info'] as $batch):
                                if ($remaining <= 0) break;
                                $counter++;
                                $batch_available = $batch['quantity'] ?? 0;
                                $batch_to_use = min($batch_available, $remaining);
                                $is_expired = !empty($batch['expiry_date']) && strtotime($batch['expiry_date']) < strtotime(date('Y-m-d'));
                                $is_expiring = !empty($batch['expiry_date']) && !$is_expired && strtotime($batch['expiry_date']) < strtotime('+30 days');
                            ?>
                                <div class="inventory-item">
                                    <div class="batch-select">
                                        <input type="checkbox" 
                                               name="batches[<?= $item['id'] ?>][<?= $counter ?>][id]" 
                                               value="<?= $batch['id'] ?>" 
                                               <?= $is_expired ? 'disabled' : '' ?>
                                               <?= (!$is_expired && $batch_available > 0) ? 'checked' : '' ?>
                                               <?= ($is_confirmed && !$is_paid) || $is_dispensed ? 'disabled' : '' ?>
                                               onchange="updateBatchQuantity(this, <?= $batch_available ?>, <?= $remaining ?>)">
                                        <span class="text-sm font-medium">
                                            Batch <?= htmlspecialchars($batch['batch_number'] ?? 'N/A') ?>
                                        </span>
                                        <span class="text-sm text-gray-500">
                                            <?= $batch_available ?> units available
                                        </span>
                                        <?php if (!empty($batch['expiry_date'])): ?>
                                            <span class="batch-tag <?= $is_expired ? 'expired' : ($is_expiring ? 'expiring' : 'available') ?>">
                                                <?php if ($is_expired): ?>
                                                    ❌ Expired <?= formatDate($batch['expiry_date']) ?>
                                                <?php elseif ($is_expiring): ?>
                                                    ⚠️ Expires <?= formatDate($batch['expiry_date']) ?>
                                                <?php else: ?>
                                                    ✅ Expires <?= formatDate($batch['expiry_date']) ?>
                                                <?php endif; ?>
                                            </span>
                                        <?php endif; ?>
                                        <span class="text-sm text-gray-500">
                                            Price: <?= formatCurrency($batch['selling_price'] ?? 0) ?>
                                        </span>
                                        <input type="number" 
                                               name="batches[<?= $item['id'] ?>][<?= $counter ?>][quantity]" 
                                               value="<?= $batch_to_use ?>" 
                                               min="0" max="<?= $batch_available ?>"
                                               <?= $is_expired ? 'disabled' : '' ?>
                                               <?= ($is_confirmed && !$is_paid) || $is_dispensed ? 'disabled' : '' ?>
                                               style="width:70px;padding:4px 8px;border:2px solid var(--border-color);border-radius:6px;font-size:0.8rem;outline:none;">
                                        <span class="text-xs text-gray-400">
                                            (max <?= $batch_available ?>)
                                        </span>
                                        <input type="hidden" name="batches[<?= $item['id'] ?>][<?= $counter ?>][selling_price]" value="<?= $batch['selling_price'] ?? 0 ?>">
                                        <input type="hidden" name="batches[<?= $item['id'] ?>][<?= $counter ?>][quantity_available]" value="<?= $batch_available ?>">
                                    </div>
                                </div>
                            <?php 
                                $remaining -= $batch_to_use;
                            endforeach; 
                            ?>
                        </div>
                    <?php else: ?>
                        <div class="text-sm text-red-500">
                            <i class="fas fa-exclamation-circle mr-1"></i>
                            No stock available for this medication!
                        </div>
                    <?php endif; ?>
                    
                    <?php if (($item['available_stock'] ?? 0) < $item['quantity']): ?>
                        <div class="stock-warning mt-2">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            Insufficient stock! Required: <?= $item['quantity'] ?>, Available: <?= $item['available_stock'] ?? 0 ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        
        <!-- ================================================================ -->
        <!-- DISCOUNT - AMOUNT (TSh) -->
        <!-- ================================================================ -->
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-percent title-green mr-2"></i>
                    Discount & Notes
                </h3>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Discount Amount (<?= $currency ?>)</label>
                    <input type="number" name="discount_amount" class="form-control" 
                           value="0" min="0" max="<?= $subtotal ?>" step="100" 
                           onchange="calculateTotal()" 
                           <?= ($is_confirmed && !$is_paid) || $is_dispensed ? 'disabled' : '' ?>>
                    <p class="form-help">Enter discount amount in <?= $currency ?> (max: <?= formatCurrency($subtotal) ?>)</p>
                </div>
                <div>
                    <label class="form-label">Notes</label>
                    <input type="text" name="notes" class="form-control" 
                           placeholder="Additional notes (optional)" 
                           <?= ($is_confirmed && !$is_paid) || $is_dispensed ? 'disabled' : '' ?>>
                </div>
            </div>
        </div>
        
        <!-- ================================================================ -->
        <!-- SUMMARY -->
        <!-- ================================================================ -->
        <div class="card mb-4" style="background:var(--primary-bg);border-color:var(--primary);">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                    <p class="detail-label">Total Items</p>
                    <p class="detail-value" style="font-size:1.2rem;"><?= count($items) ?></p>
                </div>
                <div>
                    <p class="detail-label">Total Quantity</p>
                    <p class="detail-value" style="font-size:1.2rem;">
                        <?php 
                            $total_qty = 0;
                            foreach ($items as $item) {
                                $total_qty += $item['quantity'];
                            }
                            echo $total_qty;
                        ?>
                    </p>
                </div>
                <div>
                    <p class="detail-label">Subtotal</p>
                    <p class="detail-value" style="font-size:1.2rem;" id="subtotalDisplay">
                        <?= formatCurrency($subtotal) ?>
                    </p>
                </div>
                <div>
                    <p class="detail-label">Total After Discount</p>
                    <p class="detail-value" style="font-size:1.2rem;color:var(--success);" id="totalDisplay">
                        <?= formatCurrency($subtotal) ?>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- ================================================================ -->
        <!-- ACTION BUTTONS -->
        <!-- ================================================================ -->
        <div class="action-buttons">
            
            <!-- Back to List Button -->
            <a href="pending_prescriptions.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
            
            <!-- ================================================================ -->
            <!-- CONFIRM BUTTON - Shows when pending -->
            <!-- FIXED: Inaonekana hata kama bill ipo (isipokuwa bill imelipwa) -->
            <!-- ================================================================ -->
            <?php if ($show_confirm): ?>
                <button type="submit" name="action" value="confirm" class="btn btn-success btn-transition" 
                        onclick="return confirm('Confirm this prescription?\n\n✅ Bill will be created/updated and sent to Cashier.\n💰 Patient will need to pay before medication is dispensed.');">
                    <i class="fas fa-check-circle"></i> Confirm & Send to Cashier
                </button>
            <?php endif; ?>
            
            <!-- ================================================================ -->
            <!-- DISPENSE BUTTON - Shows when confirmed AND paid -->
            <!-- ================================================================ -->
            <?php if ($show_dispense): ?>
                <button type="submit" name="action" value="dispense" class="btn btn-success btn-transition" 
                        style="background:var(--success);"
                        onclick="return confirm('Dispense this medication?\n\n💊 Patient has paid.\n📦 Stock will be updated.\n✅ Prescription will be marked as dispensed.');">
                    <i class="fas fa-prescription"></i> Dispense Medication
                </button>
            <?php endif; ?>
            
            <!-- ================================================================ -->
            <!-- WAITING FOR PAYMENT - Shows when confirmed but not paid -->
            <!-- ================================================================ -->
            <?php if ($show_waiting): ?>
                <div class="status-info unpaid" style="padding:12px 24px;">
                    <i class="fas fa-clock"></i>
                    ⏳ Waiting for Payment
                    <span class="text-xs text-gray-500 ml-2">(Bill sent to Cashier)</span>
                </div>
            <?php endif; ?>
            
            <!-- ================================================================ -->
            <!-- ALREADY DISPENSED - Shows when dispensed -->
            <!-- ================================================================ -->
            <?php if ($is_dispensed): ?>
                <div class="status-info dispensed" style="padding:12px 24px;">
                    <i class="fas fa-check-circle"></i>
                    ✅ Already Dispensed
                </div>
            <?php endif; ?>
            
            <!-- ================================================================ -->
            <!-- CANCEL BUTTON - Shows when pending OR confirmed (not dispensed) -->
            <!-- ================================================================ -->
            <?php if ($show_cancel): ?>
                <button type="submit" name="action" value="cancel" class="btn btn-danger" 
                        onclick="return confirm('Cancel this prescription?\n\n❌ This action cannot be undone.');">
                    <i class="fas fa-times"></i> Cancel Prescription
                </button>
            <?php endif; ?>
            
        </div>
        
    </form>

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

    var searchBtn = document.getElementById('searchBtn');
    var searchInput = document.getElementById('searchInput');
    
    function performSearch() {
        var query = searchInput.value.trim();
        if (query.length > 0) {
            window.location.href = 'search.php?q=' + encodeURIComponent(query);
        }
    }
    
    searchBtn?.addEventListener('click', performSearch);
    searchInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });

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

    function updateBatchQuantity(checkbox, maxQty, required) {
        var qtyInput = checkbox.closest('.batch-select').querySelector('input[type="number"]');
        if (checkbox.checked) {
            qtyInput.disabled = false;
            qtyInput.value = Math.min(parseInt(qtyInput.value) || 1, maxQty);
            if (parseInt(qtyInput.value) <= 0) {
                qtyInput.value = Math.min(required, maxQty);
            }
        } else {
            qtyInput.disabled = true;
            qtyInput.value = 0;
        }
        calculateTotal();
    }

    function calculateTotal() {
        var subtotal = <?= $subtotal ?>;
        var discount = parseFloat(document.querySelector('input[name="discount_amount"]')?.value) || 0;
        var total = Math.max(0, subtotal - discount);
        
        var subtotalEl = document.getElementById('subtotalDisplay');
        var totalEl = document.getElementById('totalDisplay');
        if (subtotalEl) subtotalEl.textContent = 'TSh ' + subtotal.toLocaleString();
        if (totalEl) totalEl.textContent = 'TSh ' + total.toLocaleString();
    }

    console.log('%c💊 Braick - Dispense Prescription (FIXED)', 'font-size:18px; font-weight:bold; color:#0B5ED7;');
    console.log('%c📋 Prescription: <?= htmlspecialchars($prescription['prescription_number'] ?? 'N/A') ?>', 'font-size:13px; color:#64748B;');
    console.log('%c👤 Patient: <?= htmlspecialchars($prescription['patient_name'] ?? 'N/A') ?>', 'font-size:13px; color:#059669;');
    console.log('%c📊 Status: <?= ucfirst($prescription['status'] ?? 'pending') ?>', 'font-size:13px; color:#0B5ED7;');
    console.log('%c💰 Bill Status: <?= $bill_status ?? 'No bill' ?>', 'font-size:13px; color:#D97706;');
    console.log('%c🔄 Buttons:', 'font-size:13px; color:#0B5ED7;');
    console.log('%c   - Confirm: Shows when pending (even if bill exists, as long as not paid)', 'font-size:12px; color:#059669;');
    console.log('%c   - Dispense: Shows when confirmed + paid', 'font-size:12px; color:#059669;');
    console.log('%c   - Cancel: Shows when pending or confirmed', 'font-size:12px; color:#DC2626;');
</script>

</body>
</html>